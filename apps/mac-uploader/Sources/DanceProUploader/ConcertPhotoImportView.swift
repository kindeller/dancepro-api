import AppKit
import PhotoReviewCore
import PreparationCore
import SwiftUI

struct ConcertPhotoImportView: View {
  @ObservedObject var model: PhotoReviewModel
  @Environment(\.dismiss) private var dismiss
  @State private var plan: ConcertImportPlan?
  @State private var source: URL?
  @State private var scoped = false
  @State private var working = false
  @State private var scanning = false
  @State private var scanTask: Task<Void, Never>?
  @State private var status = "Choose a folder or SD card containing JPEG photos from one camera."
  @State private var error: String?
  @State private var reviewID: UUID?
  @State private var confirmed = false
  @State private var importedCount = 0

  private var folders: [PlannedFolder] {
    model.local.manifest.folders.filter { !model.lockedItemIDs.contains($0.itemUuid) }
  }
  private var count: Int { plan?.groups.reduce(0) { $0 + $1.photos.count } ?? 0 }

  private var importComplete: Bool { importedCount > 0 && count == 0 && error == nil && !working }

  var body: some View {
    VStack(alignment: .leading, spacing: 14) {
      HStack {
        Text(model.isPortraitSession ? "Import dress rehearsal portraits" : "Import concert photos").font(.title2.bold())
        Spacer()
        Button("Close") { dismiss() }.disabled(working)
      }
      Text(model.isPortraitSession ? "All JPEG photos will be copied into Dress Rehearsal Portraits. Originals stay untouched. You can also copy photos into that folder yourself." : "Black frames suggest a new programme item. Check every group: dark stage photos can look like separators. Photos are ordered by folder and filename; originals stay untouched.")
        .foregroundStyle(.secondary)
      HStack {
        Button("Choose source folder…", action: chooseSource).disabled(working)
        Text(source?.lastPathComponent ?? "No source selected").lineLimit(1)
        Spacer()
        if working { ProgressView().controlSize(.small) }
        if scanning { Button("Cancel scan") { scanTask?.cancel() } }
      }
      if importComplete {
        HStack(alignment: .top, spacing: 10) {
          Image(systemName: "checkmark.circle.fill").font(.title2).foregroundStyle(.green)
          VStack(alignment: .leading, spacing: 4) {
            Text("Import complete").font(.headline)
            Text(model.isPortraitSession ? "\(importedCount) photos copied into Dress Rehearsal Portraits. Click Done to return to photo review." : "\(importedCount) photos copied into their programme folders. Click Done to return to photo review.")
            Text("These photos are saved on this Mac. Click Upload when you are ready to send all of the concert photos.")
              .font(.callout).foregroundStyle(.secondary)
          }
          Spacer()
        }.padding(14).background(Color.green.opacity(0.12), in: RoundedRectangle(cornerRadius: 8))
      } else {
        Text(status).font(.callout)
      }
      if let error { Text(error).foregroundStyle(.orange).textSelection(.enabled) }
      ScrollView {
        VStack(alignment: .leading, spacing: 12) {
          if let current = plan {
            if !current.skipped.isEmpty {
              DisclosureGroup("\(current.skipped.count) unreadable photos were skipped — check these before importing") {
                ForEach(current.skipped, id: \.self) { Text($0).font(.caption) }
              }.foregroundStyle(.orange)
            }
            ForEach(Array(current.groups.enumerated()), id: \.element.id) { index, group in
              VStack(alignment: .leading, spacing: 10) {
                HStack {
                  Text(model.isPortraitSession ? "Dress Rehearsal Portraits · \(group.photos.count) photos" : "Group \(index + 1) · \(group.photos.count) photos").font(.headline)
                  Spacer()
                  Button("Review photos") { reviewID = group.id }
                  if index > 0 && !model.isPortraitSession {
                    Button("Combine with previous") { plan?.mergeWithPrevious(group.id); confirmed = false }
                  }
                }
                HStack {
                  if let first = group.photos.first { ImportThumbnail(photo: first, caption: "First") }
                  if group.photos.count > 1, let last = group.photos.last { ImportThumbnail(photo: last, caption: "Last") }
                  Spacer()
                  if !model.isPortraitSession {
                  Picker("Programme item", selection: Binding(
                    get: { plan?.groups.first(where: { $0.id == group.id })?.itemID ?? "" },
                    set: { value in
                      if let i = plan?.groups.firstIndex(where: { $0.id == group.id }) {
                        plan?.groups[i].itemID = value.isEmpty ? nil : value
                        confirmed = false
                      }
                    })) {
                      Text("Choose item…").tag("")
                      ForEach(folders, id: \.itemUuid) { folder in
                        Text(folder.relativePath).tag(folder.itemUuid)
                      }
                    }.frame(maxWidth: 380)
                  }
                }
              }.padding(12).background(.quaternary, in: RoundedRectangle(cornerRadius: 8))
            }
            if !current.separators.isEmpty {
              DisclosureGroup(model.isPortraitSession ? "\(current.separators.count) excluded photos — will not be imported" : "\(current.separators.count) separator / excluded photos — will not be imported") {
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 150))]) {
                  ForEach(current.separators) { photo in
                    VStack {
                      ImportThumbnail(photo: photo, caption: "Excluded")
                      Menu("Include photo") {
                        ForEach(Array(current.groups.enumerated()), id: \.element.id) { index, group in
                          Button("Group \(index + 1)") { plan?.restore(photo.id, to: group.id); confirmed = false }
                        }
                        Button("New group") {
                          plan?.separators.removeAll { $0.id == photo.id }
                          plan?.groups.append(ImportPhotoGroup(photos: [photo], itemID: model.isPortraitSession ? folders.first?.itemUuid : nil))
                          confirmed = false
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }.disabled(working)
      Divider()
      if !importComplete {
        Toggle(model.isPortraitSession ? "I have checked the photos to import" : "I have checked the groups, excluded photos and destination items", isOn: $confirmed)
          .disabled(working || count == 0)
      }
      HStack {
        Text("Copy only · No automatic upload").foregroundStyle(.secondary)
        Spacer()
        if importComplete {
          Button("Done") { dismiss() }.buttonStyle(.borderedProminent).keyboardShortcut(.defaultAction)
        } else {
          Button("Import \(count) photos") { Task { await importPhotos() } }
            .buttonStyle(.borderedProminent)
            .disabled(working || !confirmed || count == 0 || plan?.groups.contains(where: { $0.itemID == nil }) == true)
        }
      }
    }.padding(22).frame(width: 850, height: 650)
      .interactiveDismissDisabled(working)
      .sheet(isPresented: Binding(get: { reviewID != nil }, set: { if !$0 { reviewID = nil } })) {
        if let id = reviewID { reviewGroup(id) }
      }
      .onDisappear {
        scanTask?.cancel()
        if scoped { source?.stopAccessingSecurityScopedResource() }
      }
  }

  private func chooseSource() {
    let panel = NSOpenPanel()
    panel.canChooseDirectories = true
    panel.canChooseFiles = false
    panel.prompt = "Scan photos"
    guard panel.runModal() == .OK, let url = panel.url else { return }
    let root = model.local.url.standardizedFileURL.path
    let selected = url.standardizedFileURL.path
    guard selected != root, !selected.hasPrefix(root + "/"), !root.hasPrefix(selected + "/") else {
      error = "Choose a source folder outside this prepared event so existing photos are not imported again."
      return
    }
    if scoped { source?.stopAccessingSecurityScopedResource() }
    source = url
    scoped = url.startAccessingSecurityScopedResource()
    working = true
    plan = nil
    importedCount = 0
    error = nil
    confirmed = false
    scanning = true
    status = "Scanning JPEGs on this Mac…"
    let ids = folders.map(\.itemUuid)
    let portraits = model.isPortraitSession
    scanTask = Task {
      do {
        let scan = Task.detached(priority: .userInitiated) {
          try ConcertPhotoImport.scan(url, itemIDs: ids, separateBlackFrames: !portraits)
        }
        let scanned = try await withTaskCancellationHandler {
          try await scan.value
        } onCancel: { scan.cancel() }
        try Task.checkCancellation()
        plan = scanned
        status = portraits ? "\(scanned.groups.reduce(0) { $0 + $1.photos.count }) portraits found. They will be copied into Dress Rehearsal Portraits." : "\(scanned.groups.count) groups found. Match each group to its programme item before importing."
      } catch is CancellationError { status = "Scan cancelled. No photos were copied." }
      catch { self.error = error.localizedDescription }
      scanning = false
      working = false
    }
  }

  private func importPhotos() async {
    guard let plan else { return }
    working = true
    error = nil
    do {
      let result = try await model.importConcertPhotos(plan)
      importedCount += result.copiedIDs.count
      self.plan?.groups = plan.groups.compactMap { group in
        var remaining = group
        remaining.photos.removeAll { result.copiedIDs.contains($0.id) }
        return remaining.photos.isEmpty ? nil : remaining
      }
      confirmed = false
      error = result.error
      status = "Imported \(result.copiedIDs.count) photos. Originals remain in the source folder."
    } catch { self.error = error.localizedDescription }
    working = false
  }

  private func reviewGroup(_ id: UUID) -> some View {
    VStack(alignment: .leading, spacing: 12) {
      HStack {
        Text("Review group photos").font(.title2.bold())
        Spacer()
        Button("Done") { reviewID = nil }
      }
      Text(model.isPortraitSession ? "Check the photos, exclude any you do not want, or edit a filename if it conflicts with an existing photo." : "Split before a photo if a separator was missed. Exclude a separator, or edit a filename if it conflicts with an existing photo.")
      ScrollView {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 170))]) {
          if let group = plan?.groups.first(where: { $0.id == id }) {
            ForEach(group.photos) { photo in
              VStack {
                ImportThumbnail(photo: photo, caption: "")
                TextField("Filename", text: Binding(get: {
                  plan?.groups.first(where: { $0.id == id })?.photos.first(where: { $0.id == photo.id })?.filename ?? photo.filename
                }, set: { value in
                  if let g = plan?.groups.firstIndex(where: { $0.id == id }),
                    let p = plan?.groups[g].photos.firstIndex(where: { $0.id == photo.id }) {
                    plan?.groups[g].photos[p].filename = value
                    confirmed = false
                  }
                }))
                if !model.isPortraitSession {
                Button("Split before") { plan?.split(groupID: id, before: photo.id); confirmed = false }
                  .disabled(photo.id == group.photos.first?.id)
                }
                Button(model.isPortraitSession ? "Exclude photo" : "Exclude separator") { plan?.exclude(photo.id, from: id); confirmed = false }
              }.padding(8)
            }
          }
        }
      }
    }.padding(20).frame(width: 780, height: 560)
  }
}

private struct ImportThumbnail: View {
  let photo: ImportPhoto
  let caption: String
  @State private var image: NSImage?
  var body: some View {
    VStack(spacing: 3) {
      if let image {
        Image(nsImage: image).resizable().scaledToFit().frame(width: 145, height: 95)
      } else { Rectangle().fill(.quaternary).frame(width: 145, height: 95).overlay(Image(systemName: "photo")) }
      Text(caption.isEmpty ? photo.filename : caption + ": " + photo.filename).font(.caption).lineLimit(1).help(photo.filename)
    }.task(id: photo.id) {
      let url = photo.source
      if let cgImage = try? await Task.detached(priority: .utility, operation: { try ConcertPhotoImport.thumbnail(url) }).value {
        image = NSImage(cgImage: cgImage, size: .zero)
      }
    }
  }
}
