import SwiftUI

struct PhotographerSelectionView: View {
  @ObservedObject var model: PhotoReviewModel
  let refresh: () async -> Void
  @State private var pendingRole: String?
  @State private var confirmChange = false
  @Environment(\.dismiss) private var dismiss

  var body: some View {
    VStack(alignment: .leading, spacing: 14) {
      HStack {
        Text("Event role for this Mac").font(.title2.bold())
        Spacer()
        Button("Refresh", systemImage: "arrow.clockwise") { Task { await refresh() } }
          .disabled(model.loadingPhotographers || model.busy)
        Button("Done") { dismiss() }.buttonStyle(.borderedProminent).tint(.blue)
      }
      Text(
        "Concerts default to your assigned role. You can upload for another role using your own account. Competition laptops keep their equipment role through crew changes."
      )
      .font(.callout).foregroundStyle(.secondary)
      if model.loadingPhotographers { ProgressView("Checking event roles…") }
      if let saved = model.photographerSelection {
        Text("Last checked: \(checkedAt(saved.roster.checkedAt))")
          .font(.caption).foregroundStyle(.secondary)
        ScrollView {
          VStack(alignment: .leading, spacing: 12) {
            ForEach(saved.roster.photographers) { person in
              Button {
                if saved.selected != nil && saved.selectedId != person.id {
                  pendingRole = person.id
                  confirmChange = true
                } else { model.selectPhotographer(person.id) }
              } label: {
                HStack(alignment: .top) {
                  Image(
                    systemName: saved.selectedId == person.id ? "checkmark.circle.fill" : "circle"
                  )
                  .foregroundStyle(saved.selectedId == person.id ? Color.blue : Color.secondary)
                  VStack(alignment: .leading, spacing: 5) {
                    Text(person.roleName).font(.headline)
                    if person.isVideo {
                      Text("V station · competition video upload").font(.caption).foregroundStyle(.secondary)
                    }
                    ForEach(person.shifts) { shift in
                      Text("\(shift.date) · \(shift.period.capitalized)")
                        .font(.caption).foregroundStyle(.secondary)
                    }
                  }
                  Spacer()
                }.padding(12).contentShape(Rectangle())
                  .background(Color.secondary.opacity(0.08), in: RoundedRectangle(cornerRadius: 8))
              }.buttonStyle(.plain).disabled(model.loadingPhotographers || model.busy)
            }
            if saved.roster.photographers.isEmpty {
              Text(
                saved.roster.hasShifts
                  ? "No event roles configured. Add the required roles in Crew Management, then refresh here."
                  : "This event has no scheduled shifts. Add its shifts and required roles in Crew Management."
              )
            }
            if !saved.roster.unfilled.isEmpty {
              Text("Unfilled photographer roles").font(.headline)
              ForEach(Array(saved.roster.unfilled.enumerated()), id: \.offset) { _, role in
                Text(
                  "\(role.date) · \(role.period.capitalized) · \(role.roleName): \(role.missingCount) unfilled"
                )
                .foregroundStyle(.orange)
              }
            }
          }
        }
      } else if !model.loadingPhotographers {
        Text(
          "Refresh while signed in to load this event’s roles. Your selection will be saved on this Mac for offline use."
        )
      }
      if let error = model.photographerError {
        Text(error).foregroundStyle(.orange).textSelection(.enabled)
      }
      Text(
        "This identifies the media role. Your signed-in account records who uploads. It does not mark contributions complete or release a gallery. Uploading keeps galleries private."
      )
      .font(.caption).foregroundStyle(.secondary)
    }.padding(20).frame(width: 650, height: 540)
      .task { await refresh() }
      .confirmationDialog("Change this event’s laptop role?", isPresented: $confirmChange) {
        Button("Change Role") { if let pendingRole { model.selectPhotographer(pendingRole) } }
        Button("Cancel", role: .cancel) {}
      } message: {
        Text("Previously uploaded photos keep their original role. Upload or clear the current queue before switching, then mark the new role’s photos Ready.")
      }
  }
  private func checkedAt(_ value: String) -> String {
    guard let date = ISO8601DateFormatter().date(from: value) else { return value }
    return date.formatted(date: .abbreviated, time: .shortened)
  }

}
