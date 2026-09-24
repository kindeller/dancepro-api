import AppKit
import CryptoKit
import PreparationCore
import SwiftUI

private struct VideoBatchReply: Decodable {
  let batchUuid: String
  let status: String
  let verified: Int
  let needsAssignment: Int
}

private struct VideoReserveReply: Decodable {
  let fileUuid: String
  let itemUuid: String?
  let alreadyUploaded: Bool
  let needsAssignment: Bool
  let upload: VideoUploadAddress?
}

private struct VideoUploadAddress: Decodable {
  let url: String
  let headers: [String: String]
}

struct LocalVideo: Identifiable {
  let url: URL
  let match: CompetitionVideoMatch
  let size: Int64
  var id: String { url.path }
}

@MainActor
final class VideoUploadModel: ObservableObject {
  @Published var source: URL?
  @Published var videos: [LocalVideo] = []
  @Published var status = "Choose the video source folder for this event."
  @Published var error: String?
  @Published var busy = false
  private let local: LocalEvent
  private var sourceAccess = false
  private var sourceKey: String { "competition-video-source-\(local.manifest.programme.eventUuid)" }
  private var batchKey: String { "competition-video-batch-\(local.manifest.programme.eventUuid)" }

  init(local: LocalEvent) {
    self.local = local
    if let bookmark = UserDefaults.standard.data(forKey: sourceKey) {
      var stale = false
      if let url = try? URL(resolvingBookmarkData: bookmark, options: [.withSecurityScope],
                        relativeTo: nil, bookmarkDataIsStale: &stale), !stale {
        sourceAccess = url.startAccessingSecurityScopedResource()
        source = url
      }
    }
    scan()
  }

  func chooseSource() async {
    let panel = NSOpenPanel()
    panel.canChooseDirectories = true
    panel.canChooseFiles = false
    panel.prompt = "Use Video Folder"
    panel.message = "Choose the folder where this event's video files are recorded. DancePro remembers it for this event."
    guard await panel.begin() == .OK, let url = panel.url else { return }
    if sourceAccess { source?.stopAccessingSecurityScopedResource() }
    sourceAccess = url.startAccessingSecurityScopedResource()
    source = url
    do {
      let bookmark = try url.bookmarkData(options: [.withSecurityScope], includingResourceValuesForKeys: nil,
                                          relativeTo: nil)
      UserDefaults.standard.set(bookmark, forKey: sourceKey)
    } catch { self.error = error.localizedDescription }
    scan()
  }

  func stopAccess() {
    if sourceAccess { source?.stopAccessingSecurityScopedResource() }
    sourceAccess = false
  }

  func scan() {
    guard let source else { videos = []; return }
    guard let enumerator = FileManager.default.enumerator(at: source, includingPropertiesForKeys:
      [.isRegularFileKey, .isSymbolicLinkKey, .fileSizeKey, .contentModificationDateKey],
      options: [.skipsHiddenFiles, .skipsPackageDescendants]) else {
      error = "The video source folder could not be read."
      return
    }
    var found: [LocalVideo] = []
    for case let url as URL in enumerator {
      guard CompetitionVideoNames.extensions.contains(url.pathExtension.lowercased()),
        let values = try? url.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey,
          .fileSizeKey, .contentModificationDateKey]),
        values.isRegularFile == true, values.isSymbolicLink != true,
        let size = values.fileSize, size > 0,
        let modified = values.contentModificationDate, modified.timeIntervalSinceNow < -120
      else { continue }
      let relative = String(url.path.dropFirst(source.path.count)).trimmingCharacters(in: CharacterSet(charactersIn: "/"))
      found.append(LocalVideo(url: url, match: CompetitionVideoNames.match(
        filename: url.lastPathComponent, relativePath: relative, programme: local.manifest.programme),
        size: Int64(size)))
    }
    videos = found.sorted { $0.match.relativePath.localizedStandardCompare($1.match.relativePath) == .orderedAscending }
    status = "\(videos.count) finished video file\(videos.count == 1 ? "" : "s") found. Files changed in the last two minutes are skipped."
  }

  func upload(client: APIClient, roleUuid: String) async {
    guard !busy, !videos.isEmpty, let source else { return }
    busy = true
    error = nil
    defer { busy = false }
    do {
      let receipt: ProgrammeSyncReceipt
      if let saved = ProgrammeSyncReceipt.read(root: local.url), saved.matches(local.manifest) {
        receipt = saved
      } else {
        receipt = try await client.syncProgramme(local.manifest)
        try receipt.save(root: local.url)
      }
      let batchUuid = UserDefaults.standard.string(forKey: batchKey) ?? UUID().uuidString.lowercased()
      UserDefaults.standard.set(batchUuid, forKey: batchKey)
      let eventPath = "/api/staff/events/\(local.manifest.programme.eventUuid)/competition-videos/batches"
      let batch = try await client.decode(VideoBatchReply.self, path: eventPath,
        body: ["batch_uuid": batchUuid, "plan_uuid": receipt.plan.uuid, "role_uuid": roleUuid]).data
      guard batch.status == "open" else {
        UserDefaults.standard.removeObject(forKey: batchKey)
        throw PreparationError.invalid("The previous batch is complete. Click Upload again for any new files.")
      }
      var included = batch.verified > 0
      var unmatched = 0
      for (index, video) in videos.enumerated() {
        status = "Checking \(index + 1) of \(videos.count): \(video.url.lastPathComponent)"
        let before = try video.url.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
        let checksum = try sha256(video.url)
        let after = try video.url.resourceValues(forKeys: [.fileSizeKey, .contentModificationDateKey])
        guard before.fileSize == after.fileSize,
          before.contentModificationDate == after.contentModificationDate,
          Int64(after.fileSize ?? 0) == video.size else {
          throw PreparationError.invalid("\(video.url.lastPathComponent) changed while being read. Wait for recording to finish and scan again.")
        }
        let reply = try await client.decode(VideoReserveReply.self, path: "\(eventPath)/\(batchUuid)/files",
          body: ["file_uuid": UUID().uuidString.lowercased(), "file_name": video.url.lastPathComponent,
                 "sha256": checksum, "byte_count": String(video.size)]).data
        if reply.needsAssignment { unmatched += 1 }
        if reply.alreadyUploaded { continue }
        guard let address = reply.upload else {
          throw PreparationError.invalid("DancePro did not provide an upload address for \(video.url.lastPathComponent).")
        }
        included = true
        status = "Uploading \(index + 1) of \(videos.count): \(video.url.lastPathComponent)"
        try await client.uploadPresigned(address.url, headers: address.headers, from: video.url)
        _ = try await client.request("\(eventPath)/\(batchUuid)/files/\(reply.fileUuid)/verify", body: [:])
      }
      if unmatched > 0 {
        status = "\(unmatched) video filename\(unmatched == 1 ? "" : "s") need assignment in Media → Competition Photos → Videos. Assign them, then click Upload again."
        return
      }
      guard included else {
        status = "All scanned videos were uploaded previously."
        UserDefaults.standard.removeObject(forKey: batchKey)
        return
      }
      let result = try await client.decode(VideoBatchReply.self,
        path: "\(eventPath)/\(batchUuid)/complete", body: [:]).data
      guard result.status == "complete" else { throw PreparationError.invalid("The batch did not complete.") }
      UserDefaults.standard.removeObject(forKey: batchKey)
      status = "\(result.verified) video\(result.verified == 1 ? "" : "s") verified. Unheld routines are now available."
    } catch {
      self.error = error.localizedDescription
      status = "Upload paused. Fix the issue and click Upload to resume."
    }
    _ = source // retain security-scoped access through the operation
  }

  private func sha256(_ url: URL) throws -> String {
    let handle = try FileHandle(forReadingFrom: url)
    defer { try? handle.close() }
    var hash = SHA256()
    while let chunk = try handle.read(upToCount: 4 * 1024 * 1024), !chunk.isEmpty {
      hash.update(data: chunk)
    }
    return hash.finalize().map { String(format: "%02x", $0) }.joined()
  }
}

struct CompetitionVideoUploadView: View {
  @ObservedObject var review: PhotoReviewModel
  @StateObject private var video: VideoUploadModel
  let close: () -> Void
  let refreshRoles: () async -> Void
  let upload: (VideoUploadModel, String) async -> Void
  @State private var showRoles = false
  @State private var confirmBatch = false

  init(review: PhotoReviewModel, close: @escaping () -> Void,
       refreshRoles: @escaping () async -> Void,
       upload: @escaping (VideoUploadModel, String) async -> Void) {
    self.review = review
    self.close = close
    self.refreshRoles = refreshRoles
    self.upload = upload
    _video = StateObject(wrappedValue: VideoUploadModel(local: review.local))
  }

  var body: some View {
    VStack(alignment: .leading, spacing: 18) {
      HStack {
        Button("Events", systemImage: "chevron.left", action: close).disabled(video.busy)
        Text(review.local.manifest.programme.eventName).font(.title2.bold())
        Spacer()
        Button(review.photographerSelection?.selected?.roleName ?? "Choose role") { showRoles = true }
          .disabled(video.busy)
      }
      HStack {
        Image(systemName: "video.fill").font(.largeTitle).foregroundStyle(.blue)
        VStack(alignment: .leading) {
          Text("Competition videos").font(.title.bold())
          Text("Record as \(review.photographerSelection?.selected?.roleName ?? "V station") · upload to private storage")
            .foregroundStyle(.secondary)
        }
      }
      HStack {
        Text(video.source?.path ?? "No source folder selected").lineLimit(2).textSelection(.enabled)
        Spacer()
        Button("Choose Folder…") { Task { await video.chooseSource() } }
        Button("Scan") { video.scan() }.disabled(video.source == nil || video.busy)
      }
      List(video.videos) { file in
        HStack {
          Image(systemName: file.match.itemUuid == nil ? "exclamationmark.triangle" : "film")
            .foregroundStyle(file.match.itemUuid == nil ? .orange : .blue)
          VStack(alignment: .leading) {
            Text(file.match.relativePath).font(.headline)
            Text(file.match.label).font(.caption).foregroundStyle(.secondary)
          }
          Spacer()
          Text(ByteCountFormatter.string(fromByteCount: file.size, countStyle: .file))
            .foregroundStyle(.secondary)
        }
      }
      Text(video.status).font(.callout).textSelection(.enabled)
      if let error = video.error { Text(error).foregroundStyle(.red).textSelection(.enabled) }
      HStack {
        Text("Unmatched files stay private until assigned in Competition Videos. Use HOLD for routines awaiting another clip.")
          .font(.caption).foregroundStyle(.secondary)
        Spacer()
        if video.busy { ProgressView() }
        Button("Upload Videos") { confirmBatch = true }
        .buttonStyle(.borderedProminent).tint(.blue)
        .disabled(video.busy || video.videos.isEmpty)
      }
    }
    .padding(24)
    .sheet(isPresented: $showRoles) { PhotographerSelectionView(model: review, refresh: refreshRoles) }
    .onDisappear { video.stopAccess() }
    .confirmationDialog("Upload and complete this video batch?", isPresented: $confirmBatch) {
      Button("Upload \(video.videos.count) Videos") {
        guard let role = review.photographerSelection?.selected?.roleUuid else { return }
        Task { await upload(video, role) }
      }
      Button("Cancel", role: .cancel) {}
    } message: {
      Text("Confirm all clips for these routines are in the source folder. Files still being recorded are excluded. Put any incomplete routine on HOLD in Competition Videos before uploading.")
    }
    .task { await refreshRoles() }
  }
}
