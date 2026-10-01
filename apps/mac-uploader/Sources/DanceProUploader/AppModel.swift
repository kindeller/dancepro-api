import AppKit
import Combine
import Foundation
import PreparationCore
import UniformTypeIdentifiers

struct LocalEvent: Identifiable {
  let url: URL
  let manifest: PreparedEvent
  var id: String { url.path }
}

@MainActor
final class AppModel: ObservableObject {
  @Published var server = UserDefaults.standard.string(forKey: "server") ?? "http://localhost"
  @Published var email = ""
  @Published var password = ""
  @Published var secondFactor = ""
  @Published var useRecoveryCode = false
  @Published var session: SavedSession?
  @Published var events: [EventSummary] = []
  @Published var selected: EventSummary?
  @Published var programme: Programme?
  @Published var folders: [PlannedFolder] = []
  @Published var preparationRoles: PhotographerRoster?
  @Published var preparationRoleID: String?
  var preparationRole: EventPhotographer? {
    preparationRoles?.photographers.first { $0.id == preparationRoleID }
  }
  var canPrepare: Bool {
    !busy && programme != nil && (programme?.eventType != "concert" || preparationRole != nil)
  }
  @Published var locals: [LocalEvent] = []
  @Published var review: PhotoReviewModel?
  @Published var busy = false
  @Published var message = ""
  @Published var error: String?
  @Published var search = ""
  @Published var includePast = false
  @Published var hasMore = false
  private var eventSearchTask: Task<Void, Never>?
  private var page = 0
  private var bookmarks: [Data] = []
  private var accessed: [URL] = []
  private var reviewModels: [String: PhotoReviewModel] = [:]

  private func reviewModel(for local: LocalEvent) throws -> PhotoReviewModel {
    if let existing = reviewModels[local.id] { return existing }
    let created = try PhotoReviewModel(local: local)
    reviewModels[local.id] = created
    return created
  }

  // Runs while the app is open, including on the event list. Reuse each library
  // actor so background acknowledgements cannot overwrite active review edits.
  func syncTopPics() async {
    for local in locals where reviewModels[local.id] == nil {
      _ = try? reviewModel(for: local)
    }
    for model in Array(reviewModels.values) {
      guard !Task.isCancelled else { return }
      await model.syncTopPics(client: try? client())
    }
  }

  func retryTopPics(_ review: PhotoReviewModel) async {
    await review.syncTopPics(client: try? client(), retryNow: true)
  }

  init() {
    loadLocalEvents()
    do {
      session = try KeychainSession.read()
      if let session { server = session.server }
    } catch { self.error = error.localizedDescription }
  }

  func signIn() async {
    await perform("Signing in…") {
      let client = try APIClient(server: self.server)
      var body = [
        "email": self.email, "password": self.password, "device_name": "DancePro Mac Uploader",
      ]
      if !self.secondFactor.isEmpty {
        body[self.useRecoveryCode ? "recovery_code" : "two_factor_code"] = self.secondFactor
      }
      defer {
        self.password = ""
        self.secondFactor = ""
      }
      let login = try await client.decode(LoginData.self, path: "/api/auth/login", body: body).data
      let saved = SavedSession(
        server: client.base.absoluteString, token: login.token, name: login.user.name)
      do { try KeychainSession.save(saved) } catch {
        _ = try? await APIClient(server: saved.server, token: saved.token).request(
          "/api/auth/logout", body: [:])
        throw error
      }
      self.session = saved
      UserDefaults.standard.set(saved.server, forKey: "server")
      self.message = "Signed in."
    }
    if session != nil { await refresh() }
  }

  func signOut() async {
    guard let session else { return }
    await perform("Signing out…") {
      // Keep the local session if revocation fails, so logout can be retried.
      _ = try await APIClient(server: session.server, token: session.token).request(
        "/api/auth/logout", body: [:])
      try KeychainSession.remove()
      self.eventSearchTask?.cancel()
      self.session = nil
      self.events = []
      self.selected = nil
      self.programme = nil
      self.folders = []
      self.message = "Signed out. Your prepared events remain on this Mac."
    }
  }

  func searchEvents() {
    eventSearchTask?.cancel()
    eventSearchTask = Task { [weak self] in
      do {
        try await Task.sleep(for: .milliseconds(300))
        // Let an active prepare/upload operation finish before loading results.
        while self?.busy == true {
          try await Task.sleep(for: .milliseconds(100))
        }
        try Task.checkCancellation()
        await self?.refresh()
      } catch { /* A newer search superseded this one. */ }
    }
  }

  func refresh(more: Bool = false) async {
    guard session != nil else { return }
    let query = search
    let past = includePast
    await perform("Loading approved events…") {
      var parts = URLComponents()
      parts.path = "/api/staff/programme-events"
      parts.queryItems = [
        URLQueryItem(name: "page", value: String(more ? self.page + 1 : 1)),
        URLQueryItem(name: "search", value: query),
      ]
      if !past {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.locale = Locale(identifier: "en_US_POSIX")
        parts.queryItems?.append(URLQueryItem(name: "from", value: formatter.string(from: Date())))
      }
      let response = try await self.client().decode([EventSummary].self, path: parts.string!)
      try Task.checkCancellation()
      guard query == self.search, past == self.includePast, self.session != nil else { return }
      self.events = more ? self.events + response.data : response.data
      self.page = response.meta?.currentPage ?? 1
      self.hasMore = self.page < (response.meta?.lastPage ?? 1)
      self.message =
        self.events.isEmpty
        ? "No approved events found. Approve a programme in DancePro, or include past events."
        : "Choose an event to prepare."
    }
  }

  func select(_ event: EventSummary) async {
    guard !busy else { return }
    selected = event
    preparationRoles = nil
    preparationRoleID = nil
    programme = nil
    folders = []
    await perform("Downloading approved programme…") {
      let programme = try await self.client().decode(
        Programme.self, path: "/api/staff/events/\(event.uuid)/programme"
      ).data
      guard programme.eventUuid == event.uuid else {
        throw PreparationError.invalid("The server returned a different event.")
      }
      let plan = try FolderPlan(programme: programme)
      self.programme = programme
      self.folders = plan.folders
      if programme.eventType == "concert" {
        let manifest = PreparedEvent(server: self.session!.server, programme: programme,
          folders: plan.folders, preparedAt: Date(), hasPdf: programme.sourceUrl != nil)
        self.preparationRoles = try await self.client().photographers(for: manifest)
      }
      self.message = programme.eventType == "concert"
        ? (programme.isPortraitSession ? "Choose your photographer role, then prepare the portrait folder. No programme is needed." : "Choose your role, then prepare this concert.")
        : "Ready to prepare \(plan.folders.count) folders from approved version \(programme.version)."
    }
  }

  func downloadVideoTitles(_ programme: Programme) async {
    guard !busy else { return }
    let panel = NSSavePanel()
    panel.allowedContentTypes = [.zip]
    panel.nameFieldStringValue = "Video titles \(programme.eventUuid).zip"
    panel.title = "Save Video Titles"
    panel.message = "Download the titles published as ready in DancePro."
    guard await panel.begin() == .OK, let destination = panel.url else { return }
    let accessing = destination.startAccessingSecurityScopedResource()
    defer { if accessing { destination.stopAccessingSecurityScopedResource() } }
    await perform("Downloading video titles…") {
      let bytes = try await TitleCardDownloads.download(
        client: self.client(), eventUuid: programme.eventUuid, revisionUuid: programme.revisionUuid)
      try bytes.write(to: destination, options: .atomic)
      self.message = "Video titles saved. Open the ZIP to access the JPEGs."
      NSWorkspace.shared.activateFileViewerSelecting([destination])
    }
  }

  func prepare() async {
    guard canPrepare, let programme, let session else { return }
    let role = preparationRole
    let panel = NSOpenPanel()
    panel.canChooseDirectories = true
    panel.canChooseFiles = false
    panel.canCreateDirectories = true
    panel.prompt = "Prepare Here"
    panel.message = role?.isVideo == true
      ? "Choose where the programme and video titles should be saved."
      : "Choose where this event’s photo folders and programme should be saved."
    guard await panel.begin() == .OK, let destination = panel.url else { return }
    let accessing = destination.startAccessingSecurityScopedResource()
    defer { if accessing { destination.stopAccessingSecurityScopedResource() } }
    await perform("Preparing event…") {
      // Recheck the event is still available before using the pinned revision.
      let current = try await self.client().decode(
        Programme.self, path: "/api/staff/events/\(programme.eventUuid)/programme"
      ).data
      guard current.revisionUuid == programme.revisionUuid else {
        throw PreparationError.invalid(
          "A newer programme has been approved. Select the event again to review it before preparing."
        )
      }
      var selection: PhotographerSelection?
      if programme.eventType == "concert" {
        let manifest = PreparedEvent(server: session.server, programme: programme,
          folders: self.folders, preparedAt: Date(), hasPdf: programme.sourceUrl != nil)
        let roster = try await self.client().photographers(for: manifest)
        guard let role, let currentRole = roster.photographers.first(where: { $0.roleUuid == role.roleUuid }),
          currentRole.isVideo == role.isVideo else {
          throw PreparationError.invalid("This role is no longer available. Refresh the event and choose a role again.")
        }
        selection = PhotographerSelection(server: session.server, roster: roster, selectedId: currentRole.id)
      }
      let titles = role?.isVideo == true ? try await TitleCardDownloads.download(
        client: self.client(), eventUuid: programme.eventUuid, revisionUuid: programme.revisionUuid) : nil
      var pdf: Data?
      if let path = programme.sourceUrl {
        let expected =
          "/api/staff/events/\(programme.eventUuid)/programme/source/\(programme.revisionUuid)"
        guard path == expected else {
          throw PreparationError.invalid("The PDF is not pinned to this approved programme.")
        }
        pdf = try await self.client().request(path)
      }
      let root = try LocalPreparation.prepare(
        programme: programme, pdf: pdf, server: session.server, destination: destination, videoTitles: titles)
      let bookmark = try root.bookmarkData(
        options: .withSecurityScope, includingResourceValuesForKeys: nil, relativeTo: nil)
      self.bookmarks.append(bookmark)
      UserDefaults.standard.set(self.bookmarks, forKey: "preparedBookmarks")
      self.loadLocalEvents()
      self.message = role?.isVideo == true
        ? "Programme and video titles saved for offline use."
        : "Prepared \(self.folders.count) folders. Programme\(pdf == nil ? "" : " and PDF") saved for offline use."
      let manifest = try ProgrammeCoding.decoder().decode(
        PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
      let preparedReview = try self.reviewModel(for: LocalEvent(url: root, manifest: manifest))
      preparedReview.beginVisit()
      if let selection {
        let review = preparedReview
        await review.refresh()
        var saved = review.photographerSelection ?? selection
        try saved.refresh(selection.roster)
        review.photographerSelection = saved
        review.selectPhotographer(selection.selectedId)
      }
      self.review = preparedReview
    }
  }

  func openPreparedFolder() async {
    let panel = NSOpenPanel()
    panel.canChooseDirectories = true
    panel.canChooseFiles = false
    panel.prompt = "Open Event"
    panel.message =
      "Choose an event folder previously created by DancePro’s Prepare Event. To prepare a new event, cancel and select it in the event list."
    guard await panel.begin() == .OK, let root = panel.url else { return }
    let accessing = root.startAccessingSecurityScopedResource()
    do {
      guard
        FileManager.default.fileExists(atPath: root.appendingPathComponent("preparation.json").path)
      else {
        throw PreparationError.invalid(
          "This folder has not been prepared by DancePro. Select your event on the left, then click Prepare Event. If it needs programme reapproval, click Approve programme on the website first, then refresh the event list."
        )
      }
      let manifest = try ProgrammeCoding.decoder().decode(
        PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
      let local = LocalEvent(url: root, manifest: manifest)
      let review = try reviewModel(for: local)
      if !locals.contains(where: { $0.url.standardizedFileURL == root.standardizedFileURL }) {
        let bookmark = try root.bookmarkData(
          options: .withSecurityScope, includingResourceValuesForKeys: nil, relativeTo: nil)
        bookmarks.append(bookmark)
        UserDefaults.standard.set(bookmarks, forKey: "preparedBookmarks")
        locals.append(local)
      }
      if accessing { accessed.append(root) }
      review.beginVisit()
      self.review = review
    } catch {
      if accessing { root.stopAccessingSecurityScopedResource() }
      self.error = error.localizedDescription
    }
  }

  func openReview(_ local: LocalEvent) {
    do {
      let opened = try reviewModel(for: local)
      opened.beginVisit()
      review = opened
    } catch {
      self.error = error.localizedDescription
    }
  }

  func refreshPhotographers(_ review: PhotoReviewModel) async {
    guard session != nil else {
      review.photographerError =
        "Go back to events and sign in to refresh the photographer list. The saved list remains available offline."
      return
    }
    do { await review.refreshPhotographers(client: try client()) } catch {
      review.photographerError = error.localizedDescription
    }
  }

  func uploadPhotos(_ review: PhotoReviewModel) {
    guard session != nil else {
      review.error =
        "Go back to events and sign in before uploading. Your photos remain queued on this Mac."
      return
    }
    do { review.startUploads(client: try client()) } catch {
      review.error = error.localizedDescription
    }
  }

  func uploadConcertVideos(_ video: ConcertVideoUploadModel) async {
    do { await video.upload(client: try client()) } catch { video.error = error.localizedDescription }
  }

  func finishConcertVideos(_ video: ConcertVideoUploadModel) async {
    do { await video.finish(client: try client()) } catch { video.error = error.localizedDescription }
  }

  func uploadVideos(_ video: VideoUploadModel, roleUuid: String) async {
    do { await video.upload(client: try client(), roleUuid: roleUuid) }
    catch { video.error = error.localizedDescription }
  }

  func refreshPhotoProgress(_ review: PhotoReviewModel) async {
    do { await review.refreshProgress(client: try client()) } catch {
      review.serverProgress = nil
      review.progressError = error.localizedDescription
    }
  }

  func finishPhotoUploads(_ review: PhotoReviewModel) async {
    do { await review.finishUploads(client: try client()) } catch {
      review.error = error.localizedDescription
    }
  }

  func checkCompetitionFolders(_ review: PhotoReviewModel) async {
    do { await review.checkCompetitionFolders(client: try client()) }
    catch { review.competitionProgressError = error.localizedDescription }
  }

  func refreshSharedProgramme(_ review: PhotoReviewModel) async {
    do { await review.refreshSharedProgramme(client: try client()) }
    catch { review.sharedProgrammeError = error.localizedDescription }
  }
  func loadSharedProgramme(_ review: PhotoReviewModel) async {
    do { await review.loadSharedProgramme(client: try client()) }
    catch { review.error = error.localizedDescription }
  }
  func shareProgramme(_ review: PhotoReviewModel) async {
    do { await review.shareProgramme(client: try client()) }
    catch { review.error = error.localizedDescription }
  }

  private func client() throws -> APIClient {
    guard let session else { throw PreparationError.invalid("Sign in to download an event.") }
    return try APIClient(server: session.server, token: session.token)
  }

  private func perform(_ status: String, work: () async throws -> Void) async {
    guard !busy else { return }
    busy = true
    error = nil
    message = status
    defer { busy = false }
    do { try await work() } catch {
      if Task.isCancelled || error is CancellationError { return }
      if case APIError.expired = error {
        do {
          try KeychainSession.remove()
          session = nil
          events = []
          selected = nil
          programme = nil
          folders = []
        } catch {
          self.error = error.localizedDescription
          message = ""
          return
        }
      }
      self.error = error.localizedDescription
      message = ""
    }
  }

  private func loadLocalEvents() {
    for url in accessed { url.stopAccessingSecurityScopedResource() }
    accessed = []
    locals = []
    bookmarks = UserDefaults.standard.array(forKey: "preparedBookmarks") as? [Data] ?? []
    var unavailable = 0
    for (index, bookmark) in bookmarks.enumerated() {
      do {
        var stale = false
        let url = try URL(
          resolvingBookmarkData: bookmark, options: .withSecurityScope, relativeTo: nil,
          bookmarkDataIsStale: &stale)
        if url.startAccessingSecurityScopedResource() { accessed.append(url) }
        if stale {
          bookmarks[index] = try url.bookmarkData(
            options: .withSecurityScope, includingResourceValuesForKeys: nil, relativeTo: nil)
        }
        let manifest = try ProgrammeCoding.decoder().decode(
          PreparedEvent.self, from: Data(contentsOf: url.appendingPathComponent("preparation.json"))
        )
        if !locals.contains(where: { $0.url == url }) {
          locals.append(LocalEvent(url: url, manifest: manifest))
        }
      } catch { unavailable += 1 }
    }
    locals.sort { $0.manifest.preparedAt > $1.manifest.preparedAt }
    UserDefaults.standard.set(bookmarks, forKey: "preparedBookmarks")
    if unavailable > 0 {
      message =
        "\(unavailable) saved location(s) unavailable. Connect the event drive and reopen the app."
    }
  }
}
