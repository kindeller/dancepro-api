import AppKit
import PreparationCore
import SwiftUI

@main
struct DanceProUploaderApp: App {
  @NSApplicationDelegateAdaptor(UploaderAppDelegate.self) private var appDelegate
  @StateObject private var model = AppModel()
  @State private var showConcertVideos = false
  var body: some Scene {
    Window("DancePro Uploader", id: "main") {
      Group {
        if let review = model.review {
          if showConcertVideos && review.local.manifest.programme.eventType == "concert" {
            ConcertVideoUploadView(eventUuid: review.local.manifest.programme.eventUuid,
              eventName: review.local.manifest.programme.eventName, close: { showConcertVideos = false },
              upload: { await model.uploadConcertVideos($0) }, finish: { await model.finishConcertVideos($0) })
          } else if review.local.manifest.programme.eventType == "competition",
             review.photographerSelection?.selected?.isVideo == true {
            CompetitionVideoUploadView(review: review, close: { model.review = nil },
              refreshRoles: { await model.refreshPhotographers(review) },
              upload: { video, role in await model.uploadVideos(video, roleUuid: role) })
          } else {
            VStack(spacing: 0) {
            if review.local.manifest.programme.eventType == "concert" {
              HStack {
                Spacer()
                Button("Upload Edited Concert Videos…", systemImage: "video.badge.plus") { showConcertVideos = true }
              }.padding(.horizontal, 20).padding(.top, 10)
            }
            PhotoReviewView(
            model: review, close: { model.review = nil },
            uploadPhotos: { model.uploadPhotos(review) },
            finishUploads: { await model.finishPhotoUploads(review) },
            refreshProgress: { await model.refreshPhotoProgress(review) },
            refreshPhotographers: { await model.refreshPhotographers(review) },
            refreshSharedProgramme: { await model.refreshSharedProgramme(review) },
            loadSharedProgramme: { await model.loadSharedProgramme(review) },
            shareProgramme: { await model.shareProgramme(review) },
            retryTopPics: { await model.retryTopPics(review) },
            checkCompetitionFolders: { await model.checkCompetitionFolders(review) },
            downloadVideoTitles: {
              await model.downloadVideoTitles(review.local.manifest.programme)
              review.error = model.error
            })
            }
          }
        } else {
          ContentView(model: model)
        }
      }
      .frame(minWidth: 900, minHeight: 620)
      .task { if model.session != nil { await model.refresh() } }
      .task {
        while !Task.isCancelled {
          await model.syncTopPics()
          do { try await Task.sleep(for: .seconds(2)) } catch { return }
        }
      }
    }
    .defaultSize(width: 1080, height: 740)
  }
}

struct ContentView: View {
  @ObservedObject var model: AppModel
  var body: some View {
    VStack(spacing: 0) {
      HStack {
        Image(nsImage: NSApplication.shared.applicationIconImage).resizable().scaledToFit()
          .frame(width: 44, height: 44)
        VStack(alignment: .leading) {
          Text("DancePro Uploader").font(.title2.bold())
          Text("Prepare your event on this Mac").foregroundStyle(.secondary)
        }
        Spacer()
        if let session = model.session {
          VStack(alignment: .trailing) {
            Text(session.name)
            Text(session.server).font(.caption).foregroundStyle(.secondary)
          }
          Button("Sign Out") { Task { await model.signOut() } }.disabled(model.busy)
        }
      }.padding(20)
      Divider()
      HSplitView {
        VStack(alignment: .leading, spacing: 12) {
          if model.session == nil { login } else { events }
        }.padding(20).frame(minWidth: 300, idealWidth: 340, maxWidth: 390)
        ScrollView {
          VStack(alignment: .leading, spacing: 22) {
            if let programme = model.programme {
              VStack(alignment: .leading, spacing: 10) {
                Text(programme.eventName).font(.title.bold())
                Text("Approved version \(programme.version) · \(model.folders.count) folders")
                  .foregroundStyle(.secondary)
                Text(
                  programme.sourceUrl == nil
                    ? "Programme data will be saved. No PDF attached."
                    : "Programme PDF and data will be saved for offline use.")
                Button("Prepare Event…", systemImage: "folder.badge.plus") {
                  Task { await model.prepare() }
                }
                .buttonStyle(.borderedProminent).controlSize(.large).disabled(model.busy)
                Text(
                  "Choose a destination, then the app creates the folders and saves the programme. Existing media is kept."
                ).font(.caption).foregroundStyle(.secondary)
                if programme.eventType == "concert" {
                  Button("Download Video Titles…", systemImage: "arrow.down.doc") {
                    Task { await model.downloadVideoTitles(programme) }
                  }.disabled(model.busy || model.session == nil)
                }
                Divider()
                ForEach(model.folders) { folder in
                  Text(folder.relativePath).textSelection(.enabled).font(
                    .system(.body, design: .monospaced))
                }
              }
            } else if let selected = model.selected, !selected.preparationReady {
              ContentUnavailableView(
                "Approve the programme first", systemImage: "doc.badge.clock",
                description: Text(
                  "Open \(selected.name) in the DancePro website and click Approve programme again. Then refresh the event list here, select the event and click Prepare Event."
                ))
            } else {
              ContentUnavailableView(
                "Prepare before the event", systemImage: "folder.badge.plus",
                description: Text(
                  "Sign in and select an approved event to download its programme and create the media folders."
                ))
            }
            Divider()
            HStack {
              Text("Prepared on this Mac").font(.title2.bold())
              Spacer()
              Button("Open Existing Event Folder…") { Task { await model.openPreparedFolder() } }
            }
            if model.locals.isEmpty {
              Text(
                "No events prepared yet. Select an event on the left, click Prepare Event and choose where to save it. Photo review opens automatically when it is ready."
              ).foregroundStyle(
                .secondary)
            }
            ForEach(model.locals) { local in
              VStack(alignment: .leading, spacing: 8) {
                Text(local.manifest.programme.eventName).font(.headline)
                Text(
                  "Version \(local.manifest.programme.version) · \(local.manifest.folders.count) folders · \(local.manifest.preparedAt.formatted(date: .abbreviated, time: .shortened))"
                ).font(.caption).foregroundStyle(.secondary)
                DisclosureGroup("View saved programme") {
                  VStack(alignment: .leading, spacing: 6) {
                    ForEach(local.manifest.programme.nodes.filter { $0.kind == "item" }) { node in
                      Text((node.folderNumber.map { $0 + "  " } ?? "") + node.title)
                    }
                  }.frame(maxWidth: .infinity, alignment: .leading).padding(.top, 8)
                }
                HStack {
                  Button("Open Event", systemImage: "photo.on.rectangle") {
                    model.openReview(local)
                  }
                  .buttonStyle(.borderedProminent)
                  Button("Open Folders", systemImage: "folder") {
                    NSWorkspace.shared.open(local.url)
                  }
                  if local.manifest.programme.eventType == "concert" {
                    Button("Download Video Titles…", systemImage: "arrow.down.doc") {
                      Task { await model.downloadVideoTitles(local.manifest.programme) }
                    }.disabled(model.busy || model.session == nil)
                  }
                  if local.manifest.hasPdf {
                    Button("Open Programme", systemImage: "doc") {
                      NSWorkspace.shared.open(local.url.appendingPathComponent("programme.pdf"))
                    }
                  }
                }
              }.padding(14).frame(maxWidth: .infinity, alignment: .leading).background(
                .quaternary.opacity(0.4), in: RoundedRectangle(cornerRadius: 10))
            }
          }.padding(24).frame(maxWidth: .infinity, alignment: .leading)
        }.frame(minWidth: 480)
      }
      Divider()
      HStack(alignment: .top) {
        if model.busy { ProgressView().controlSize(.small) }
        if let error = model.error {
          Image(systemName: "exclamationmark.circle").foregroundStyle(.red)
          Text(error).foregroundStyle(.red).textSelection(.enabled)
        } else {
          Text(
            model.message.isEmpty
              ? "Prepare events · Review photos · Upload privately" : model.message
          ).foregroundStyle(.secondary)
        }
        Spacer()
      }.font(.callout).padding(14)
    }
  }

  private var login: some View {
    VStack(alignment: .leading, spacing: 14) {
      Text("Sign in to DancePro").font(.title2.bold())
      Text("Use your DancePro administrator account. Your sign-in is saved in macOS Keychain.")
        .foregroundStyle(.secondary)
      TextField("Server address", text: $model.server)
      TextField("Email", text: $model.email)
      SecureField("Password", text: $model.password)
      DisclosureGroup("Two-factor authentication") {
        VStack(alignment: .leading) {
          Toggle("Use a recovery code", isOn: $model.useRecoveryCode)
          SecureField(
            model.useRecoveryCode ? "Recovery code" : "Six-digit code", text: $model.secondFactor)
        }.padding(.top, 8)
      }
      Button("Sign In") { Task { await model.signIn() } }
        .buttonStyle(.borderedProminent).disabled(
          model.busy || model.email.isEmpty || model.password.isEmpty)
      Spacer()
    }.textFieldStyle(.roundedBorder)
  }
  private var events: some View {
    VStack(alignment: .leading, spacing: 12) {
      Text("Approved events").font(.title2.bold())
      HStack {
        TextField("Search events", text: $model.search).onSubmit { Task { await model.refresh() } }
        Button {
          Task { await model.refresh() }
        } label: {
          Image(systemName: "arrow.clockwise")
        }.help("Refresh events")
      }.disabled(model.busy)
      Toggle("Include past events", isOn: $model.includePast).onChange(of: model.includePast) {
        Task { await model.refresh() }
      }.disabled(model.busy)
      ScrollView {
        LazyVStack(spacing: 8) {
          ForEach(model.events) { event in
            Button {
              Task { await model.select(event) }
            } label: {
              VStack(alignment: .leading, spacing: 5) {
                Text(event.name).font(.headline)
                Text("\(event.eventDate ?? "Date to be confirmed") · \(event.itemCount) items")
                  .font(.caption)
                if !event.preparationReady {
                  Text("Needs programme reapproval").font(.caption).foregroundStyle(.orange)
                }
              }.frame(maxWidth: .infinity, alignment: .leading).padding(12)
                .background(
                  model.selected?.uuid == event.uuid
                    ? Color.indigo.opacity(0.16) : Color.secondary.opacity(0.07),
                  in: RoundedRectangle(cornerRadius: 8))
            }.buttonStyle(.plain).disabled(model.busy)
          }
          if model.hasMore {
            Button("Load More") { Task { await model.refresh(more: true) } }.disabled(model.busy)
          }
        }
      }
    }.textFieldStyle(.roundedBorder)
  }
}

@MainActor
final class UploaderAppDelegate: NSObject, NSApplicationDelegate {
  func applicationDidFinishLaunching(_ notification: Notification) {
    if let url = Bundle.main.url(forResource: "Uploader", withExtension: "icns"),
      let icon = NSImage(contentsOf: url)
    {
      NSApplication.shared.applicationIconImage = icon
    }
  }
}
