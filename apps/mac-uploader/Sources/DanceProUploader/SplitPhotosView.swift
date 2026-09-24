import PreparationCore
import SwiftUI

struct SplitPhotosView: View {
  @ObservedObject var model: PhotoReviewModel
  @Environment(\.dismiss) private var dismiss
  @State private var title = ""
  @State private var error: String?
  @FocusState private var titleFocused: Bool

  var body: some View {
    VStack(alignment: .leading, spacing: 16) {
      Text("Split into New Item").font(.title2.bold())
      Text(
        "Move \(model.moveSelection.count) selected photos from \(model.folderTitle) into a new item immediately after it."
      )
      TextField("New item title", text: $title).textFieldStyle(.roundedBorder)
        .focused($titleFocused).disabled(model.busy)
      Text(
        "Pause capture and copying before continuing. Folders will be renumbered; Top Pics stay with their photos. Undo Move can return the photos, keeping the new item."
      )
      .font(.callout).foregroundStyle(.secondary)
      if let error { Text(error).foregroundStyle(.red).textSelection(.enabled) }
      HStack {
        if model.busy { ProgressView().controlSize(.small) }
        Spacer()
        Button("Cancel") { dismiss() }.keyboardShortcut(.cancelAction).disabled(model.busy)
        Button("Create and Move") {
          Task {
            if await model.splitSelection(title: title) { dismiss() } else { error = model.error }
          }
        }.buttonStyle(.borderedProminent).tint(.blue)
          .keyboardShortcut(.defaultAction)
          .disabled(model.busy || FolderPlan.clean(title).isEmpty)
      }
    }.padding(22).frame(width: 480)
      .onAppear { titleFocused = true }
  }
}
