import AppKit
import SwiftUI

struct ConcertVideoPreparationView: View {
  @ObservedObject var review: PhotoReviewModel
  let close: () -> Void
  let refreshRoles: () async -> Void
  let upload: () -> Void
  @State private var showRoles = false

  var body: some View {
    VStack(alignment: .leading, spacing: 20) {
      HStack {
        Button("Back to events", action: close)
        Spacer()
        Button(review.photographerSelection?.selected?.roleName ?? "Choose role") { showRoles = true }
      }
      Text(review.local.manifest.programme.eventName).font(.largeTitle.bold())
      Label("Prepared for video on this Mac", systemImage: "checkmark.circle.fill")
        .foregroundStyle(.green)
      Text("Your programme and video titles are saved here. Open the title ZIP to access its JPEGs.")
      HStack {
        Button("Open Video Titles", systemImage: "text.rectangle") {
          NSWorkspace.shared.open(review.local.url.appendingPathComponent("Video Titles.zip"))
        }
        if review.local.manifest.hasPdf {
          Button("Open Programme", systemImage: "doc") {
            NSWorkspace.shared.open(review.local.url.appendingPathComponent("programme.pdf"))
          }
        }
        Button("Open Folder", systemImage: "folder") { NSWorkspace.shared.open(review.local.url) }
      }
      DisclosureGroup("View programme items") {
        ScrollView {
          VStack(alignment: .leading, spacing: 8) {
            ForEach(review.local.manifest.programme.nodes.filter { $0.isActiveItem }) { node in
              Text([node.folderNumber, node.title].compactMap { $0 }.joined(separator: " · "))
            }
          }
        }
      }
      Button("Upload Edited Concert Videos…", systemImage: "video.badge.plus", action: upload)
        .buttonStyle(.borderedProminent).disabled(!review.roleConfirmed)
      Spacer()
    }.padding(24)
      .onAppear { if !review.roleConfirmed { showRoles = true } }
      .sheet(isPresented: $showRoles) {
        PhotographerSelectionView(model: review, refresh: refreshRoles, back: close)
      }
  }
}
