import PreparationCore
import SwiftUI

struct ConcertEditorView: View {
  @ObservedObject var model: PhotoReviewModel
  @Environment(\.dismiss) private var dismiss
  @State private var draft: Programme
  @State private var error: String?
  @State private var retiredNodes: [ProgrammeNode]
  @State private var showCombine = false
  private let originalIDs: Set<String>

  init(model: PhotoReviewModel) {
    self.model = model
    var active = model.local.manifest.programme
    _retiredNodes = State(initialValue: active.nodes.filter { $0.mergedIntoUuid != nil })
    active.nodes.removeAll { $0.mergedIntoUuid != nil }
    _draft = State(initialValue: active)
    originalIDs = Set(model.local.manifest.programme.nodes.map(\.uuid))
  }

  var body: some View {
    VStack(alignment: .leading, spacing: 14) {
      HStack {
        Text("Edit Concert Items").font(.title2.bold())
        Spacer()
        Button("Cancel") { dismiss() }.disabled(model.busy)
        Button("Save Changes") {
          Task {
            var programme = draft
            programme.nodes += retiredNodes
            if await model.saveConcertLayout(programme) { dismiss() } else { error = model.error }
          }
        }.buttonStyle(.borderedProminent).disabled(model.busy)
      }
      Text(
        "Pause capture and copying before saving. Folder numbers follow this order; titles keep punctuation. Cancel discards edits. Saved combinations have no one-click undo."
      )
      .font(.callout).foregroundStyle(.secondary)
      List {
        ForEach(Array(draft.nodes.enumerated()), id: \.element.uuid) { index, node in
          HStack {
            Image(systemName: "line.3.horizontal").foregroundStyle(.secondary)
            if node.kind == "item", node.itemKind != "break" {
              Text(
                String(
                  format: "%02d",
                  draft.nodes.prefix(index + 1).filter {
                    $0.kind == "item" && $0.itemKind != "break"
                  }.count)
              )
              .monospacedDigit().frame(width: 30)
              TextField("Item title", text: $draft.nodes[index].title)
            } else {
              Text(node.title).foregroundStyle(.secondary)
              Spacer()
            }
            Button {
              swap(index, index - 1)
            } label: {
              Image(systemName: "chevron.up")
            }
            .disabled(index == 0).help("Move earlier")
            Button {
              swap(index, index + 1)
            } label: {
              Image(systemName: "chevron.down")
            }
            .disabled(index == draft.nodes.count - 1).help("Move later")
            if !originalIDs.contains(node.uuid) {
              Button {
                draft.nodes.remove(at: index)
              } label: {
                Image(systemName: "minus.circle")
              }
              .help("Remove this unsaved item")
            }
          }.padding(.vertical, 3)
        }.onMove { offsets, destination in
          draft.nodes.move(fromOffsets: offsets, toOffset: destination)
        }
      }.disabled(model.busy)
      HStack {
        Button("Add Item", systemImage: "plus") { draft.nodes.append(ConcertLayout.newItem()) }
          .disabled(model.busy)
        Button("Combine Item…") { showCombine = true }
          .disabled(model.busy || draft.nodes.filter(\.isActiveItem).count < 2)
        Text("Drag rows or use the arrows to reorder.").font(.caption).foregroundStyle(.secondary)
        Spacer()
        if model.busy { ProgressView().controlSize(.small) }
      }
      if let error { Text(error).foregroundStyle(.red).textSelection(.enabled) }
    }.padding(20).frame(minWidth: 720, minHeight: 510)
      .sheet(isPresented: $showCombine) {
        CombineItemsView(programme: draft) { ids in
          do {
            var programme = draft
            programme.nodes += retiredNodes
            let combined = try ConcertLayout.combining(ids, in: programme, existingIDs: originalIDs)
            retiredNodes = combined.nodes.filter { $0.mergedIntoUuid != nil }
            draft = combined
            draft.nodes.removeAll { $0.mergedIntoUuid != nil }
            error = nil
          } catch { self.error = error.localizedDescription }
        }
      }
  }

  private func swap(_ first: Int, _ second: Int) {
    guard draft.nodes.indices.contains(second) else { return }
    draft.nodes.swapAt(first, second)
  }
}
