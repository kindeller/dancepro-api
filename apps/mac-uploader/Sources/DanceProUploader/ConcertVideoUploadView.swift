import AppKit
import CryptoKit
import PreparationCore
import SwiftUI

private struct ConcertUploadFile: Codable, Sendable, Identifiable {
  let id: String
  let path: String
  let name: String
  let size: Int64
  let modified: Date
  let checksum: String
}
private struct ConcertUploadQueue: Codable {
  let id: String
  let files: [ConcertUploadFile]
}
private struct ConcertUploadSummary: Decodable {
  let batchUuid: String
  let queued: Int
  let verified: Int
  let completedAt: String?
}
private struct ConcertUploadAddress: Decodable {
  let verified: Bool
  let upload: Address?
  struct Address: Decodable { let url: String; let headers: [String: String] }
}

@MainActor
final class ConcertVideoUploadModel: ObservableObject {
  @Published var source: URL?
  @Published var names: [String] = []
  @Published var status = "Choose the folder containing every final concert MP4, including opening videos and slideshows."
  @Published var error: String?
  @Published var busy = false
  @Published var canFinish = false
  private let eventUuid: String
  private var sourceAccess = false
  private var queue: ConcertUploadQueue?
  private var sourceKey: String { "concert-video-source-\(eventUuid)" }
  private var queueKey: String { "concert-video-queue-\(eventUuid)" }
  private var endpoint: String { "/api/staff/events/\(eventUuid)/concert-videos/batches" }

  init(eventUuid: String) {
    self.eventUuid = eventUuid
    if let data = UserDefaults.standard.data(forKey: queueKey) {
      queue = try? JSONDecoder().decode(ConcertUploadQueue.self, from: data)
    }
    if let bookmark = UserDefaults.standard.data(forKey: sourceKey) {
      var stale = false
      if let url = try? URL(resolvingBookmarkData: bookmark, options: [.withSecurityScope],
        relativeTo: nil, bookmarkDataIsStale: &stale), !stale {
        sourceAccess = url.startAccessingSecurityScopedResource(); source = url
      }
    }
    scan()
  }

  func chooseSource() async {
    let panel = NSOpenPanel(); panel.canChooseDirectories = true; panel.canChooseFiles = false
    panel.prompt = "Use Final Videos Folder"
    guard await panel.begin() == .OK, let url = panel.url else { return }
    stopAccess(); sourceAccess = url.startAccessingSecurityScopedResource(); source = url
    do { UserDefaults.standard.set(try url.bookmarkData(options: [.withSecurityScope],
      includingResourceValuesForKeys: nil, relativeTo: nil), forKey: sourceKey) }
    catch { self.error = error.localizedDescription }
    scan()
  }

  func stopAccess() { if sourceAccess { source?.stopAccessingSecurityScopedResource() }; sourceAccess = false }

  private func files() throws -> [URL] {
    guard let source, let enumerator = FileManager.default.enumerator(at: source,
      includingPropertiesForKeys: [.isRegularFileKey, .isSymbolicLinkKey],
      options: [.skipsHiddenFiles, .skipsPackageDescendants]) else {
      throw PreparationError.invalid("Choose a readable folder of final concert videos.")
    }
    var found: [URL] = []
    for case let url as URL in enumerator {
      let values = try url.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
      if values.isSymbolicLink == true { enumerator.skipDescendants(); continue }
      if values.isRegularFile == true, url.pathExtension.lowercased() == "mp4" { found.append(url.resolvingSymlinksInPath()) }
    }
    return found.sorted { $0.path < $1.path }
  }

  func scan() {
    canFinish = false
    guard source != nil else { return }
    do { names = try files().map(\.lastPathComponent); status = "\(names.count) final MP4 files found." }
    catch { self.error = error.localizedDescription }
  }

  func upload(client: APIClient) async {
    guard !busy else { return }
    busy = true; canFinish = false; error = nil
    defer { busy = false }
    do {
      let urls = try files()
      guard !urls.isEmpty else { throw PreparationError.invalid("No MP4 files found.") }
      guard Set(urls.map { $0.lastPathComponent.lowercased() }).count == urls.count else {
        throw PreparationError.invalid("Two videos have the same filename. Give each final export a unique name.")
      }
      var entries: [ConcertUploadFile] = []
      for (index, url) in urls.enumerated() {
        status = "Checking \(index + 1) of \(urls.count): \(url.lastPathComponent)"
        let before = try url.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
        guard let size = before.fileSize, size > 0, Int64(size) <= 5_368_709_120,
          let modified = before.contentModificationDate, modified.timeIntervalSinceNow < -120 else {
          throw PreparationError.invalid("\(url.lastPathComponent) is empty, still changing, or over the 5 GB single-file upload limit. Wait for export to finish; larger files need the multipart uploader.")
        }
        let checksum = try await Task.detached(priority: .utility) {
          let handle = try FileHandle(forReadingFrom: url); defer { try? handle.close() }
          var hash = SHA256()
          while let chunk = try handle.read(upToCount: 4 * 1024 * 1024), !chunk.isEmpty { hash.update(data: chunk) }
          return hash.finalize().map { String(format: "%02x", $0) }.joined()
        }.value
        let after = try url.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
        guard before.fileSize == after.fileSize, before.contentModificationDate == after.contentModificationDate else {
          throw PreparationError.invalid("\(url.lastPathComponent) changed while being read. Wait for export to finish.")
        }
        let previous = queue?.files.first { URL(fileURLWithPath: $0.path).resolvingSymlinksInPath().path == url.path && $0.checksum == checksum && $0.size == Int64(size) }
        entries.append(ConcertUploadFile(id: previous?.id ?? UUID().uuidString.lowercased(), path: url.path,
          name: url.lastPathComponent, size: Int64(size), modified: modified, checksum: checksum))
      }
      let same = queue?.files.map(\.id) == entries.map(\.id)
      if !same {
        entries = entries.map { ConcertUploadFile(id: UUID().uuidString.lowercased(), path: $0.path,
          name: $0.name, size: $0.size, modified: $0.modified, checksum: $0.checksum) }
      }
      let next = ConcertUploadQueue(id: same ? queue!.id : UUID().uuidString.lowercased(), files: entries)
      queue = next
      UserDefaults.standard.set(try JSONEncoder().encode(next), forKey: queueKey)
      let body: [String: Any] = ["batch_uuid": next.id, "files": entries.map {
        ["file_uuid": $0.id, "file_name": $0.name, "byte_count": $0.size, "sha256": $0.checksum] as [String: Any]
      }]
      // A changed queue uses fresh identities; completed files are re-used by the server checksum.
      let data = try await client.request(endpoint, jsonBody: JSONSerialization.data(withJSONObject: body))
      let summary = try ProgrammeCoding.decoder().decode(APIEnvelope<ConcertUploadSummary>.self, from: data).data
      if summary.completedAt != nil { status = "Finished Uploading already recorded for these videos."; return }
      for (index, file) in entries.enumerated() {
        let address = try await client.decode(ConcertUploadAddress.self,
          path: "\(endpoint)/\(next.id)/files/\(file.id)/address", body: [:]).data
        if !address.verified {
          guard let upload = address.upload else { throw PreparationError.invalid("Missing upload address.") }
          status = "Uploading \(index + 1) of \(entries.count): \(file.name)"
          try await client.uploadPresigned(upload.url, headers: upload.headers, from: URL(fileURLWithPath: file.path))
          _ = try await client.request("\(endpoint)/\(next.id)/files/\(file.id)/verify", body: [:])
        }
        status = "\(index + 1) of \(entries.count) videos verified."
      }
      canFinish = true
      status = "All \(entries.count) videos verified. Confirm Finished Uploading when the complete concert is included."
    } catch { self.error = error.localizedDescription; status = "Upload paused. Click Upload / Resume to retry safely." }
  }

  func finish(client: APIClient) async {
    guard !busy, canFinish, let queue else { return }
    busy = true; error = nil; defer { busy = false }
    do {
      let urls = try files()
      guard urls.map(\.path) == queue.files.map(\.path) else {
        canFinish = false; throw PreparationError.invalid("The folder contents changed. Upload the current queue before finishing.")
      }
      for file in queue.files {
        let values = try URL(fileURLWithPath: file.path).resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
        guard Int64(values.fileSize ?? 0) == file.size, values.contentModificationDate == file.modified else {
          canFinish = false; throw PreparationError.invalid("A video changed. Upload the current queue before finishing.")
        }
      }
      _ = try await client.request("\(endpoint)/\(queue.id)/complete", body: ["all_items_included": "1"])
      canFinish = false
      status = "Finished Uploading recorded. Videos remain private for the studio review checks."
    } catch { self.error = error.localizedDescription }
  }
}

struct ConcertVideoUploadView: View {
  @StateObject private var video: ConcertVideoUploadModel
  let eventName: String
  let close: () -> Void
  let upload: (ConcertVideoUploadModel) async -> Void
  let finish: (ConcertVideoUploadModel) async -> Void
  @State private var confirmFinish = false

  init(eventUuid: String, eventName: String, close: @escaping () -> Void,
       upload: @escaping (ConcertVideoUploadModel) async -> Void,
       finish: @escaping (ConcertVideoUploadModel) async -> Void) {
    _video = StateObject(wrappedValue: ConcertVideoUploadModel(eventUuid: eventUuid))
    self.eventName = eventName; self.close = close; self.upload = upload; self.finish = finish
  }
  var body: some View {
    VStack(alignment: .leading, spacing: 16) {
      HStack { Button("Back", action: close).disabled(video.busy); Text(eventName).font(.title2.bold()); Spacer() }
      Text("Edited concert videos").font(.title.bold())
      Text("Upload the complete set of final MP4 exports, including opening videos and studio slideshows. Admin or the assigned editor can upload. Each MP4 can be up to 5 GB.").foregroundStyle(.secondary)
      HStack {
        Text(video.source?.path ?? "Choose a final exports folder").lineLimit(2); Spacer()
        Button("Choose Folder…") { Task { await video.chooseSource() } }.disabled(video.busy)
        Button("Scan") { video.scan() }.disabled(video.busy)
      }
      List(video.names, id: \.self) { Text($0) }
      Text(video.status).textSelection(.enabled)
      if let error = video.error { Text(error).foregroundStyle(.red).textSelection(.enabled) }
      HStack {
        if video.busy { ProgressView() }
        Spacer()
        Button("Upload / Resume") { Task { await upload(video) } }.disabled(video.busy || video.names.isEmpty)
        Button("Finished Uploading") { confirmFinish = true }.buttonStyle(.borderedProminent)
          .disabled(video.busy || !video.canFinish)
      }
    }.padding(24)
    .onDisappear { video.stopAccess() }
    .confirmationDialog("Is the whole concert included?", isPresented: $confirmFinish) {
      Button("Confirm Finished Uploading") { Task { await finish(video) } }
      Button("Cancel", role: .cancel) {}
    } message: { Text("Confirm every final routine and required studio video/slideshow is included in these exports. This records your name and finish time; it does not release videos to customers.") }
  }
}
