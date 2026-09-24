import PreparationCore
import SwiftUI

struct CombineItemsView: View {
  let programme: Programme
  let combine: (Set<String>) -> Void
  @Environment(\.dismiss) private var dismiss
  @State private var selection = Set<String>()
  private var items: [ProgrammeNode] { programme.nodes.filter(\.isActiveItem) }
  private var combinedTitle: String {
    items.filter { selection.contains($0.uuid) }
      .map { $0.title.trimmingCharacters(in: .whitespacesAndNewlines) }.joined(separator: " ")
  }

  var body: some View {
    VStack(alignment: .leading, spacing: 16) {
      Text("Combine Items").font(.title2.bold())
      Text("Select the items to combine. Their titles join in programme order.")
        .foregroundStyle(.secondary)
      List {
        ForEach(Array(items.enumerated()), id: \.element.uuid) { index, item in
          Button {
            if !selection.insert(item.uuid).inserted { selection.remove(item.uuid) }
          } label: {
            HStack {
              Image(systemName: selection.contains(item.uuid) ? "checkmark.circle.fill" : "circle")
                .foregroundStyle(selection.contains(item.uuid) ? Color.blue : Color.secondary)
              Text(String(format: "%02d", index + 1)).monospacedDigit()
              Text(item.title)
              Spacer()
            }.contentShape(Rectangle()).padding(.vertical, 4)
          }.buttonStyle(.plain)
        }
      }
      if !selection.isEmpty {
        Text(combinedTitle).font(.headline).textSelection(.enabled)
      }
      Text(
        "The combined item stays at the first selected position. Save Changes in Edit Items to move the photos and renumber folders. Top Pics are kept."
      )
      .font(.callout).foregroundStyle(.secondary)
      HStack {
        Button("Cancel") { dismiss() }.keyboardShortcut(.cancelAction)
        Spacer()
        Button("Combine \(selection.count) Items") {
          combine(selection)
          dismiss()
        }.buttonStyle(.borderedProminent).tint(.blue).disabled(selection.count < 2)
      }
    }.padding(22).frame(width: 560, height: 510)
  }
}
