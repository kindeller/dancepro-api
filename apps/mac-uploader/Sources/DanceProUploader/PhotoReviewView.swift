import AppKit
import PhotoReviewCore
import PreparationCore
import SwiftUI

struct PhotoReviewView: View {
  @ObservedObject var model: PhotoReviewModel
  let close: () -> Void
  let uploadPhotos: () -> Void
  let finishUploads: () async -> Void
  let refreshProgress: () async -> Void
  let refreshPhotographers: () async -> Void
  let refreshSharedProgramme: () async -> Void
  let loadSharedProgramme: () async -> Void
  let shareProgramme: () async -> Void
  let retryTopPics: () async -> Void
  let checkCompetitionFolders: () async -> Void
  let downloadVideoTitles: () async -> Void
  @State private var downloadingTitles = false
  @State private var showPhotographers = false
  @State private var fitRequest = UUID()
  @State private var expanded = false
  @State private var showPhotoImport = false
  @State private var showProgramme = false
  @State private var showConcertEditor = false
  @State private var showQueue = false
  @State private var confirmFinish = false
  @State private var dropTargetID: String?

  var body: some View {
    VStack(spacing: 0) {
      header
      Divider()
      HSplitView {
        if !expanded || model.movingPhotos {
          sidebar.frame(minWidth: 250, idealWidth: 285, maxWidth: 340)
        }
        Group {
          if model.movingPhotos { MovePhotosView(model: model) } else { preview }
        }.frame(minWidth: 480)
      }
      Divider()
      HStack {
        VStack(alignment: .leading, spacing: 3) {
          Label("Saved on this Mac", systemImage: "internaldrive")
          HStack(spacing: 8) {
            Label(model.topPicSyncStatus, systemImage: model.pendingTopPicCount > 0 ? "arrow.triangle.2.circlepath" : "checkmark.icloud")
              .foregroundStyle(model.pendingTopPicCount > 0 ? Color.orange : Color.secondary)
              .help(model.topPicSyncError ?? "Stars sync automatically after upload while this app is open. Unuploaded photos send their stars with the upload.")
            if model.pendingTopPicCount > 0 && !model.syncingTopPics {
              Button("Retry sync") { Task { await retryTopPics() } }.buttonStyle(.link)
            }
          }.font(.caption)
          if let syncError = model.topPicSyncError {
            Text(syncError).font(.caption).foregroundStyle(.orange).lineLimit(2).help(syncError)
          }
          if !model.uploadStatus.isEmpty {
            Text(model.uploadStatus).font(.caption).foregroundStyle(.secondary).lineLimit(1)
              .help(model.uploadStatus)
          }
        }
        Spacer(minLength: 12)
        Button(model.isConcert ? "\(model.uploadListPhotos.count) to upload" : "\(model.queuedIDs.count) queued") { showQueue = true }
        if model.isUploading {
          Button("Pause", action: model.pauseUploads).buttonStyle(.borderedProminent)
        } else {
          Button(model.needsConcertCompletion ? "Retry completion" : (model.uploadPaused ? "Resume" : "Upload"), action: uploadPhotos)
            .buttonStyle(.borderedProminent).tint(.blue)
            .disabled(!model.canStartPhotoUpload)
        }
        if model.isConcert {
          Label(model.completionRecorded ? "Complete" : "Completes automatically after upload",
                systemImage: model.completionRecorded ? "checkmark.circle.fill" : "arrow.triangle.2.circlepath")
            .font(.caption).foregroundStyle(model.completionRecorded ? Color.green : Color.secondary)
        } else {
          Button("Upload Complete") {
            confirmFinish = true
          }
          .disabled(!model.canFinishUploads)
          .help("Confirm this event role has finished the event. All local photos must be uploaded first.")
        }
      }.font(.callout).padding(14)
      if let error = model.error {
        HStack {
          Image(systemName: "exclamationmark.circle").foregroundStyle(.orange)
          Text(error).textSelection(.enabled)
          Spacer()
          Button("Dismiss") { model.error = nil }
        }.font(.callout).padding(12).background(Color.orange.opacity(0.12))
      }
    }
    .background(Color(red: 0.12, green: 0.13, blue: 0.15))
    .preferredColorScheme(.dark)
    .onAppear { if model.isConcert && !model.roleConfirmed { showPhotographers = true } }
    .task { await model.watch() }
    .task { await refreshPhotographers() }
    .task {
      while !Task.isCancelled {
        if model.isConcert && !model.isPortraitSession { await refreshSharedProgramme() }
        else { await checkCompetitionFolders() }
        do { try await Task.sleep(for: .seconds(30)) } catch { return }
      }
    }

    .confirmationDialog("Finished Uploading Event?", isPresented: $confirmFinish) {
      Button("Finished Uploading Event") { Task { await finishUploads() } }
      Button("Cancel", role: .cancel) {}
    } message: {
      Text("Confirm that all photos for \(model.photographerSelection?.selected?.roleName ?? "this role") have been uploaded, including work from other Macs. Empty items are included. The gallery stays private.")
    }
    .sheet(isPresented: $showPhotoImport) { ConcertPhotoImportView(model: model) }
    .sheet(isPresented: $showProgramme) { programmeSheet }
    .sheet(isPresented: $showConcertEditor) { ConcertEditorView(model: model) }
    .sheet(isPresented: $showPhotographers) {
      PhotographerSelectionView(model: model, refresh: refreshPhotographers, back: close)
    }
    .sheet(isPresented: $showQueue) { PhotoQueueView(model: model, refreshProgress: refreshProgress) }
  }

  private var header: some View {
    HStack(spacing: 14) {
      Button(action: close) { Image(systemName: "chevron.left") }.help("Back to events").disabled(
        model.busy)
      VStack(alignment: .leading, spacing: 4) {
        Text(model.local.manifest.programme.eventName).font(.headline)
        Text("\(model.local.manifest.programme.eventCode ?? "") · Prepared on this Mac").font(
          .caption
        ).foregroundStyle(.secondary)
        Button {
          showPhotographers = true
        } label: {
          Label(
            model.photographerSelection?.selected.map { $0.roleName }
              ?? "Choose event role…", systemImage: "person.crop.circle"
          )
          .font(.caption).lineLimit(1)
        }.buttonStyle(.link)
      }
      Spacer()
      Button("Open Folders", systemImage: "folder") { NSWorkspace.shared.open(model.local.url) }
      if model.isConcert && !model.isPortraitSession {
        Button(downloadingTitles ? "Downloading…" : "Video Titles…", systemImage: "arrow.down.doc") {
          Task {
            downloadingTitles = true
            await downloadVideoTitles()
            downloadingTitles = false
          }
        }.disabled(model.busy || downloadingTitles)
        Button("Edit Items", systemImage: "pencil") { showConcertEditor = true }.disabled(
          model.busy || !model.lockedItemIDs.isEmpty)
      }
      if !model.isPortraitSession {
        Button("Programme", systemImage: "doc.text") { showProgramme = true }
      }
      if model.local.manifest.programme.eventType == "competition" {
        Text(model.competitionTag).font(.caption.weight(.semibold))
          .padding(.horizontal, 8).padding(.vertical, 4)
          .background(Color.orange.opacity(0.18), in: Capsule())
          .help("Open Programme to compare capture folders across P1/P2/V. Uploads can continue.")
      }
      if model.isConcert && !model.isPortraitSession {
        Text(model.programmeTag).font(.caption.weight(.semibold))
          .padding(.horizontal, 8).padding(.vertical, 4)
          .background((model.sharedProgrammeMatches && model.sharedProgrammeError == nil ? Color.green : Color.orange).opacity(0.18), in: Capsule())
          .help("Open Programme to check, load or confirm the shared concert version.")
      }
    }.padding(16)
  }

  private var sidebar: some View {
    VStack(spacing: 0) {
      if !model.movingPhotos {
        if model.isConcert {
          Button("Import Photos…", systemImage: "square.and.arrow.down") { showPhotoImport = true }
            .disabled(model.busy || model.isUploading).padding(.top, 12)
        }
        if model.isPortraitSession {
          Text("Import Photos, or use Open Folders to copy portraits into Dress Rehearsal Portraits. Then click Upload.")
            .font(.caption).foregroundStyle(.secondary).padding(12)
        }
        folderButton(
          id: "top-pics", title: "Top Pics", count: model.photos.filter(\.isTopPic).count,
          symbol: "star.fill"
        )
        .padding(12)
        Divider()
      }
      ScrollViewReader { proxy in
        ScrollView {
          LazyVStack(alignment: .leading, spacing: 4) {
            ForEach(model.local.manifest.programme.nodes) { node in
              if node.kind == "day" && model.local.manifest.programme.nodes.filter({ $0.kind == "day" }).count > 1 {
                Text(node.title.uppercased()).font(.caption).foregroundStyle(.secondary).padding(
                  .top, 14)
              } else if node.kind == "section" {
                HStack {
                  Text([node.number, node.title].compactMap { $0 }.joined(separator: " ")).font(
                    .headline)
                  Spacer()
                  if !model.isConcert { readyButton(node.uuid) }
                }.padding(.top, 12)
              } else if node.isActiveItem {
                HStack(spacing: 4) {
                  folderButton(
                    id: node.uuid,
                    title: [node.folderNumber ?? node.number, node.title].compactMap { $0 }.joined(
                      separator: " · "),
                    count: model.photos.filter { $0.itemID == node.uuid }.count, symbol: nil
                  )
                }.id(node.uuid)
              }
            }
          }.padding(12)
        }
        .onChange(of: model.folderID) {
          if model.folderID != "top-pics" {
            withAnimation(.easeOut(duration: 0.15)) { proxy.scrollTo(model.folderID) }
          }
        }
      }
    }.background(Color.black.opacity(0.10))
  }

  private func folderButton(id: String, title: String, count: Int, symbol: String?) -> some View {
    Button {
      model.selectFolder(id)
    } label: {
      VStack(alignment: .leading, spacing: 5) {
        HStack {
          if let symbol { Image(systemName: symbol).foregroundStyle(.yellow) }
          Text(title).lineLimit(2).multilineTextAlignment(.leading)
          Spacer(minLength: 0)
        }
        HStack {
          Text("\(count) photos")
          Spacer()
          if model.pending[id, default: 0] > 0 {
            Text("Shooting").foregroundStyle(.orange)
          } else if count > 0, id != "top-pics" {
            let queued = model.photos.filter { $0.itemID == id && model.queuedIDs.contains($0.id) }
              .count
            let uploaded = model.photos.filter { $0.itemID == id && $0.uploaded }.count
            Text(
              uploaded == count
                ? "Uploaded" : queued > 0 ? "Queued" : uploaded > 0 ? "New photos" : "Review"
            )
            .foregroundStyle(uploaded == count ? .green : queued > 0 ? .cyan : .secondary)
          }
        }.font(.caption).foregroundStyle(.secondary)
      }.padding(10).frame(maxWidth: .infinity, alignment: .leading)
        .background(
          dropTargetID == id
            ? Color.blue.opacity(0.4)
            : model.folderID == id ? Color(red: 0.13, green: 0.26, blue: 0.33) : .clear,
          in: RoundedRectangle(cornerRadius: 7)
        )
        .contentShape(Rectangle())
    }.buttonStyle(.plain)
      .overlay {
        if model.movingPhotos {
          PhotoDragSurface(
            accepts: { model.canAcceptMove($0, to: id) },
            dropped: { model.acceptMove([$0], to: id) },
            targeted: { targeted in
              if targeted { dropTargetID = id } else if dropTargetID == id { dropTargetID = nil }
            },
            clicked: { model.selectFolder(id) })
        }
      }
  }

  private func readyButton(_ id: String) -> some View {
    Button("Ready") { Task { await model.markReady(id) } }
      .font(.caption).controlSize(.small)
      .help("Queue the photos currently ready in this folder or section")
      .disabled((model.busy && !model.isUploading) || model.readyCount(id) == 0)
  }

  private var preview: some View {
    VStack(spacing: 0) {
      HStack(alignment: .top, spacing: 16) {
        VStack(alignment: .leading, spacing: 5) {
          Text(model.folderTitle).font(.headline).lineLimit(1)
          HStack(spacing: 8) {
            Text(model.selected == nil
              ? "No photo selected" : "\(model.position + 1) of \(model.visiblePhotos.count)")
              .fixedSize()
            Text(model.selected?.name ?? "").lineLimit(1).truncationMode(.middle)
              .help(model.selected?.name ?? "")
          }.font(.callout).foregroundStyle(.secondary)
        }
        Spacer(minLength: 0)
        VStack(spacing: 6) {
          HStack(spacing: 8) {
            Button { model.step(-1) } label: {
              Label("Previous Photo", systemImage: "chevron.up").frame(width: 122)
            }
            .keyboardShortcut(.upArrow, modifiers: [])
            .help("Previous Photo · ↑")
            .disabled(model.position == 0 || model.selected == nil)
            Button { model.step(1) } label: {
              Label("Next Photo", systemImage: "chevron.down").frame(width: 122)
            }
            .keyboardShortcut(.downArrow, modifiers: [])
            .help("Next Photo · ↓")
            .disabled(model.position >= model.visiblePhotos.count - 1 || model.selected == nil)
          }
          HStack(spacing: 8) {
            Button { model.previousItem() } label: {
              Label("Previous Item", systemImage: "arrow.up.to.line").frame(width: 122)
            }
            .keyboardShortcut(.upArrow, modifiers: .command)
            .help("Previous Item · ⌘↑")
            .disabled(model.previousItemID == nil)
            Button { model.nextItem() } label: {
              Label("Next Item", systemImage: "arrow.down.to.line").frame(width: 122)
            }
            .keyboardShortcut(.downArrow, modifiers: .command)
            .help("Next Item · ⌘↓")
            .disabled(model.nextItemID == nil)
          }
        }.font(.callout).fixedSize()
      }.padding(.horizontal, 16).padding(.top, 12)
      HStack(spacing: 10) {
        if !model.isTopPics {
          Button("Move Photos", systemImage: "arrow.right.square.fill") {
            model.moveSelection.removeAll()
            model.movingPhotos = true
          }.disabled(model.visiblePhotos.isEmpty || model.busy)
        }
        PhotoUndoButtons(model: model)
        Spacer()
      }.font(.callout).padding(.horizontal, 16).padding(.vertical, 10)
      ZStack(alignment: .topTrailing) {
        Group {
          if let image = model.image {
            PointerZoomPreview(
              image: image, photoKey: (model.photoID ?? "") + (model.selected?.signature ?? ""),
              fitRequest: fitRequest)
          } else if model.loading {
            ProgressView()
          } else {
            ContentUnavailableView(
              model.isTopPics ? "No Top Pics yet" : "No photos ready to review",
              systemImage: model.isTopPics ? "star" : "photo",
              description: Text(
                model.isTopPics
                  ? "Press Return or click the star while viewing a photo."
                  : "JPEGs placed in this event’s folders appear automatically after capture finishes."
              ))
          }
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
        HStack(spacing: 8) {
          Button { fitRequest = UUID() } label: {
            Image(systemName: "viewfinder")
          }
          .buttonStyle(PreviewIconButtonStyle())
          .help("Fit photo to preview")
          .accessibilityLabel("Fit photo to preview")
          .disabled(model.image == nil)

          Button {
            expanded.toggle()
          } label: {
            Image(
              systemName: expanded
                ? "arrow.down.right.and.arrow.up.left" : "arrow.up.left.and.arrow.down.right")
          }
          .buttonStyle(PreviewIconButtonStyle())
          .help("Expand preview")
          Button {
            Task { await model.toggleTopPic() }
          } label: {
            Image(systemName: model.selected?.isTopPic == true ? "star.fill" : "star")
              .foregroundStyle(model.selected?.isTopPic == true ? .yellow : .white)
          }
          .buttonStyle(PreviewIconButtonStyle())
          .help("Toggle Top Pic · Return").keyboardShortcut(.return, modifiers: [])
          .disabled(model.selected == nil || model.busy)
          if !model.isTopPics {
            Button {
              Task { await model.trash() }
            } label: {
              Image(systemName: "trash.fill")
            }
            .buttonStyle(PreviewIconButtonStyle(background: .red))
            .help("Move photo to macOS Trash · Delete")
            .accessibilityLabel("Move photo to Trash")
            .keyboardShortcut(.delete, modifiers: [])
            .disabled(!model.canRemoveSelected)
          }
        }.padding(12)
      }.background(Color(red: 0.06, green: 0.07, blue: 0.09))
        .overlay(alignment: .bottom) {
          ReviewNoticeView(model: model)
        }
        .animation(.easeOut(duration: 0.15), value: model.notice)

    }
  }

  private var programmeSheet: some View {
    VStack(alignment: .leading, spacing: 14) {
      HStack {
        Text(model.local.manifest.programme.eventName).font(.title2.bold())
        Spacer()
        Button("Done") { showProgramme = false }
      }
      if model.isConcert && !model.isPortraitSession {
        HStack {
          Text(model.programmeTag).font(.headline)
          Spacer()
          Button("Check online") { Task { await refreshSharedProgramme() } }
            .disabled(model.busy || model.checkingProgramme)
          if model.sharedProgramme?.plan != nil && (!model.sharedProgrammeMatches || !model.programmeSynced) {
            Button("Load shared programme") { Task { await loadSharedProgramme() } }
              .disabled(model.busy || model.checkingProgramme)
          }
          if model.canShareProgramme {
            Button(model.sharedProgramme?.plan == nil ? "Confirm Programme" : "Share Changes") {
              Task { await shareProgramme() }
            }.buttonStyle(.borderedProminent)
          }
        }
        Text("Confirmed means this Mac matches the shared online programme. Pause capture and copying before loading folder changes.")
          .font(.caption).foregroundStyle(.secondary)
        if let error = model.sharedProgrammeError { Text(error).foregroundStyle(.orange).font(.caption) }
        if let error = model.error { Text(error).foregroundStyle(.orange).font(.caption) }
      }
      if model.local.manifest.hasPdf {
        Button("Open Programme PDF") {
          NSWorkspace.shared.open(model.local.url.appendingPathComponent("programme.pdf"))
        }
      }
      if model.local.manifest.programme.eventType == "competition" {
        HStack {
          Text(model.competitionTag).font(.headline)
          Spacer()
          Button("Check folders") { Task { await checkCompetitionFolders() } }
            .disabled(model.busy || model.checkingCompetitionFolders)
        }
        Text("Folders are compared by day, section and competitor number. Names can differ without stopping uploads. Video folder checks do not mean video has uploaded.")
          .font(.caption).foregroundStyle(.secondary)
        if let error = model.competitionProgressError { Text(error).foregroundStyle(.orange).font(.caption) }
        if let checked = model.competitionProgress?.checkedAt { Text("Last server check: " + checked).font(.caption).foregroundStyle(.secondary) }
        List {
          ForEach(model.competitionFolders?.issues ?? [], id: \.self) { Text($0).foregroundStyle(.orange) }
          if let videos = model.competitionFolders?.videos, !videos.isEmpty {
            Section("Video filename matches · not uploaded") {
              ForEach(videos) { video in
                VStack(alignment: .leading, spacing: 3) {
                  Text(video.relativePath).font(.headline)
                  Text(video.label).font(.caption).foregroundStyle(video.itemUuid == nil ? Color.orange : Color.secondary)
                }
              }
            }
          }
          ForEach(model.competitionProgress?.items ?? []) { row in
            VStack(alignment: .leading, spacing: 4) {
              Text(row.date + " · Section " + row.sectionNumber + (row.kind == "item" ? " · " + row.number : "") + " · " + row.title).font(.headline)
              ForEach(row.roles) { role in
                Text(role.roleName + ": " + role.label).font(.caption)
                  .foregroundStyle(role.status == "name_difference" || role.status == "not_seen" ? Color.orange : Color.secondary)
              }
            }.padding(.vertical, 4)
          }
        }
      } else {
      List(model.local.manifest.programme.nodes.filter { $0.mergedIntoUuid == nil }) { node in
        Text(
          [node.folderNumber ?? node.number, node.title].compactMap { $0 }.joined(separator: " · "))
      }
      }
    }.padding(20).frame(width: 720, height: 560)
  }
}

private struct PreviewIconButtonStyle: ButtonStyle {
  var background: Color = Color(red: 0.18, green: 0.20, blue: 0.24)
  @Environment(\.isEnabled) private var isEnabled

  func makeBody(configuration: Configuration) -> some View {
    configuration.label
      .font(.system(size: 14, weight: .medium))
      .foregroundStyle(.white)
      .frame(width: 32, height: 32)
      .background(background, in: RoundedRectangle(cornerRadius: 6))
      .overlay(RoundedRectangle(cornerRadius: 6).stroke(Color.white.opacity(0.14)))
      .contentShape(Rectangle())
      .opacity(!isEnabled ? 0.35 : configuration.isPressed ? 0.7 : 1)
  }
}

struct PhotoUndoButtons: View {
  @ObservedObject var model: PhotoReviewModel

  var body: some View {
    Button("Undo Move", systemImage: "arrow.uturn.backward") {
      Task { await model.undoMove() }
    }.disabled(!model.canUndoMove || model.busy)
    Button("Undo Trash", systemImage: "arrow.uturn.backward") {
      Task { await model.undo() }
    }
    .keyboardShortcut("z", modifiers: .command)
    .disabled(!model.canUndo || model.busy)
  }
}
