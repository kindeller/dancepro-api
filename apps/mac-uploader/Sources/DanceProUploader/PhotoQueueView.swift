import SwiftUI

struct PhotoQueueView: View {
  @ObservedObject var model: PhotoReviewModel
  let refreshProgress: () async -> Void
  @State private var showServer = false
  @Environment(\.dismiss) private var dismiss

  var body: some View {
    VStack(alignment: .leading, spacing: 14) {
      HStack {
        Text("Upload Progress").font(.title2.bold())
        Spacer()
        Button("Done") { dismiss() }
      }
      Picker("Progress", selection: $showServer) {
        Text("This Mac · \(model.queuedPhotos.count) queued").tag(false)
        Text("Server · Event roles").tag(true)
      }.pickerStyle(.segmented)
      if showServer {
        serverProgress
      } else {
      Text(
        "Saved on this Mac, including when offline. Press Upload in the bottom bar to start. New or changed photos need another Ready action. Uploaded photos remain private until release."
      )
      .font(.callout).foregroundStyle(.secondary)
      List(model.queuedPhotos) { photo in
        HStack {
          VStack(alignment: .leading) {
            Text(photo.name)
            Text((photo.relativePath as NSString).deletingLastPathComponent)
              .font(.caption).foregroundStyle(.secondary)
          }
          Spacer()
          Button("Remove from Queue") { Task { await model.unqueue([photo.id]) } }
            .disabled(model.busy)
        }
      }
      Button("Clear Queue") {
        let ids = model.queuedIDs
        Task { await model.unqueue(ids) }
      }.disabled(model.queuedIDs.isEmpty || model.busy)
      if let error = model.error { Text(error).foregroundStyle(.red) }
      }
    }.padding(20).frame(width: 700, height: 540)
      .task(id: showServer) { if showServer { await refreshProgress() } }
  }

  private var serverProgress: some View {
    VStack(alignment: .leading, spacing: 12) {
      HStack {
        Text("Includes uploads from other Macs. Gallery remains private.")
          .font(.callout).foregroundStyle(.secondary)
        Spacer()
        Button("Refresh", systemImage: "arrow.clockwise") { Task { await refreshProgress() } }
          .disabled(model.loadingProgress)
      }
      if model.loadingProgress { ProgressView("Checking server…") }
      if let error = model.progressError { Text(error).foregroundStyle(.orange).textSelection(.enabled) }
      if let progress = model.serverProgress {
        Text("Checked \(checkedAt(progress.checkedAt))").font(.caption).foregroundStyle(.secondary)
        if !progress.planCurrent {
          Text("This working programme differs from the current server programme. Resolve it before completing uploads.")
            .foregroundStyle(.orange)
        }
        List {
          ForEach(progress.photographers) { person in
            VStack(alignment: .leading, spacing: 5) {
              HStack {
                Text(person.roleName).font(.headline)
                Spacer()
                Text(person.label).foregroundStyle(person.status == "complete" ? .green : .secondary)
              }
              Text("\(person.verifiedFileCount) verified · \(person.pendingFileCount) awaiting upload")
              Text("\(person.completeItemCount) of \(person.expectedItemCount) items confirmed complete · \(person.waivedItemCount ?? 0) waived")
                .font(.caption).foregroundStyle(.secondary)
              ForEach(Array((person.uploaders ?? []).enumerated()), id: \.offset) { _, actor in
                Text("Uploaded by \(actor.name): \(actor.submissionCount) verified submissions" +
                  (actor.duplicateCount > 0 ? " · \(actor.duplicateCount) already stored" : ""))
                  .font(.caption).foregroundStyle(.secondary)
              }
            }.padding(.vertical, 5)
          }
          if progress.photographers.isEmpty {
            Text("No photo roles configured. Check Crew Management.")
          }
          ForEach(Array(progress.unfilled.enumerated()), id: \.offset) { _, role in
            VStack(alignment: .leading) {
              Text("Unfilled · " + role.roleName).foregroundStyle(.orange)
              Text("\(role.date) · \(role.period.capitalized) · \(role.missingCount) required")
                .font(.caption)
            }
          }
        }
      } else { Spacer() }
    }
  }

  private func checkedAt(_ value: String) -> String {
    ISO8601DateFormatter().date(from: value)?.formatted(date: .abbreviated, time: .shortened) ?? value
  }

}
