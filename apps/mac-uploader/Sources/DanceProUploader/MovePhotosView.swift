import AppKit
import PhotoReviewCore
import SwiftUI

struct MovePhotosView: View {
  @ObservedObject var model: PhotoReviewModel
  @State private var showSplit = false

  var body: some View {
    VStack(spacing: 0) {
      HStack {
        VStack(alignment: .leading, spacing: 4) {
          Text("Move Photos · \(model.folderTitle)").font(.headline)
          Text("Click to select photos, then drag them onto a folder on the left.")
            .font(.callout).foregroundStyle(.secondary)
        }
        Spacer()
        Button("Done") {
          model.movingPhotos = false
          model.moveSelection.removeAll()
        }.buttonStyle(.borderedProminent).tint(.blue).disabled(model.busy)
      }.padding(16)
      HStack(spacing: 10) {
        PhotoUndoButtons(model: model)
        Spacer()
      }.font(.callout).padding(.horizontal, 16).padding(.bottom, 10)
      HStack {
        Text("\(model.moveSelection.count) selected")
        Button("Select All") {
          model.moveSelection = Set(model.visiblePhotos.filter { !$0.uploaded }.map(\.id))
        }
        Button("Clear") { model.moveSelection.removeAll() }
        Spacer()
        if model.local.manifest.programme.eventType == "concert" {
          Button("Split into New Item…") { showSplit = true }
            .disabled(model.moveSelection.isEmpty)
        }
        Menu("Move to…") {
          ForEach(
            model.local.manifest.folders.filter { $0.itemUuid != model.folderID }, id: \.itemUuid
          ) { folder in
            Button(folder.relativePath) {
              let ids = model.moveSelection.sorted()
              Task { await model.movePhotos(ids, to: folder.itemUuid) }
            }
          }
        }.disabled(model.moveSelection.isEmpty)
      }.font(.callout).padding(.horizontal, 16).padding(.bottom, 12)
        .disabled(model.busy)
      Divider()
      ScrollView {
        LazyVGrid(columns: [GridItem(.adaptive(minimum: 150, maximum: 210))], spacing: 12) {
          ForEach(model.visiblePhotos) { photo in
            MovePhotoThumbnail(model: model, photo: photo)
          }
        }.padding(16)
      }
      if model.visiblePhotos.isEmpty {
        Text("No photos in this folder").foregroundStyle(.secondary).padding()
      }
      if model.busy { ProgressView("Moving photos…").padding(12) }
    }.background(Color.black.opacity(0.15))
      .overlay(alignment: .bottom) { ReviewNoticeView(model: model) }
      .animation(.easeOut(duration: 0.15), value: model.notice)
      .sheet(isPresented: $showSplit) { SplitPhotosView(model: model) }
  }
}

private struct MovePhotoThumbnail: View {
  @ObservedObject var model: PhotoReviewModel
  let photo: ReviewPhoto
  @State private var image: NSImage?
  @State private var failed = false

  var body: some View {
    VStack(spacing: 6) {
      ZStack(alignment: .topTrailing) {
        Group {
          if let image {
            Image(nsImage: image).resizable().scaledToFit()
          } else if failed {
            Image(systemName: "photo.badge.exclamationmark")
          } else {
            ProgressView()
          }
        }.frame(maxWidth: .infinity).frame(height: 125)
        Circle()
          .fill(model.moveSelection.contains(photo.id) ? Color.cyan : Color.black.opacity(0.5))
          .frame(width: 18, height: 18)
          .overlay {
            if photo.uploaded {
              Image(systemName: "lock.fill").font(.system(size: 10, weight: .bold)).foregroundStyle(
                .white)
            } else if model.moveSelection.contains(photo.id) {
              Image(systemName: "checkmark").font(.system(size: 10, weight: .bold)).foregroundStyle(
                .white)
            }
          }
      }
      Text(photo.name).font(.caption).lineLimit(1).truncationMode(.middle)
    }.padding(7)
      .background(
        model.moveSelection.contains(photo.id)
          ? Color.blue.opacity(0.25) : Color.white.opacity(0.04),
        in: RoundedRectangle(cornerRadius: 8)
      )
      .overlay(
        RoundedRectangle(cornerRadius: 8).stroke(
          model.moveSelection.contains(photo.id) ? Color.cyan : Color.clear, lineWidth: 2)
      )
      .contentShape(Rectangle())
      .overlay {
        PhotoDragSurface(
          image: image,
          payload: {
            guard !photo.uploaded, !model.busy else { return nil }
            return model.dragPayload(for: photo.id)
          }, clicked: { model.toggleMoveSelection(photo.id) })
      }
      .help(
        photo.uploaded
          ? "Uploaded photos cannot be moved" : "Click to select; drag selected photos to a folder"
      )
      .task(id: photo.id + photo.signature) {
        do {
          let thumbnail = try await model.thumbnail(photo.id)
          guard !Task.isCancelled else { return }
          image = thumbnail
          failed = thumbnail == nil
        } catch {
          guard !Task.isCancelled else { return }
          failed = true
        }
      }
  }
}
