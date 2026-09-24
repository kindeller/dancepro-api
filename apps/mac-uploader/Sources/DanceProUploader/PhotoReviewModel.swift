import AppKit
import Combine
import PhotoReviewCore
import PreparationCore

enum ReviewUndoAction { case trash, move }

@MainActor
final class PhotoReviewModel: ObservableObject {
  @Published var local: LocalEvent
  @Published var photographerSelection: PhotographerSelection?
  @Published var loadingPhotographers = false
  @Published var photographerError: String?
  @Published var serverProgress: PhotoUploadProgress?
  @Published var loadingProgress = false
  @Published var progressError: String?
  @Published var syncReceipt: ProgrammeSyncReceipt?
  @Published var isUploading = false
  @Published var uploadPaused = false
  @Published var uploadStatus = ""
  @Published var lockedItemIDs = Set<String>()
  private var uploadTask: Task<Void, Never>?
  @Published var queuedIDs = Set<String>()
  private let library: PhotoLibrary
  @Published var movingPhotos = false
  @Published var moveSelection = Set<String>()
  private let dragSession = UUID().uuidString
  @Published var photos: [ReviewPhoto] = []
  @Published var pending: [String: Int] = [:]
  @Published var folderID: String
  @Published var photoID: String?
  @Published var image: NSImage?
  @Published var error: String?
  @Published var canUndo = false
  @Published var canUndoMove = false
  @Published var busy = false
  @Published var loading = false
  @Published var notice: String?
  @Published var noticeUndo: ReviewUndoAction?
  private var noticeTask: Task<Void, Never>?
  private var previewTask: Task<Void, Never>?
  private var previewKey: String?
  private var refreshing = false

  init(local: LocalEvent, quietInterval: TimeInterval = 2) throws {
    self.local = LocalEvent(
      url: local.url, manifest: try ConcertLayout.load(root: local.url, base: local.manifest))
    self.library = try PhotoLibrary(
      root: local.url, manifest: local.manifest, quietInterval: quietInterval)
    syncReceipt = ProgrammeSyncReceipt.read(root: local.url)
    photographerSelection = PhotographerSelection.read(
      root: local.url, manifest: local.manifest)
    folderID = local.manifest.folders.first?.itemUuid ?? "top-pics"
  }
  @Published var competitionProgress: CompetitionFolderProgress?
  @Published var competitionProgressError: String?
  @Published var checkingCompetitionFolders = false
  var competitionTag: String {
    if competitionFolders?.videos.contains(where: { $0.itemUuid == nil }) == true { return "Video filenames to check" }
    if competitionFolders?.issues.isEmpty == false || competitionProgress?.hasDifferences == true { return "Folder differences to check" }
    if competitionProgressError != nil { return "Check needed" }
    guard let progress = competitionProgress else { return "Folders not checked" }
    return progress.items.contains { $0.roles.contains { $0.status == "not_checked" } } ? "Awaiting folder checks" : "Folders match"
  }
  func checkCompetitionFolders(client: APIClient) async {
    guard local.manifest.programme.eventType == "competition", !busy, !checkingCompetitionFolders else { return }
    checkingCompetitionFolders = true
    defer { checkingCompetitionFolders = false }
    do {
      applySnapshot(try await library.scan())
      let defaults = UserDefaults.standard
      let key = "competitionUploaderDeviceUUID"
      let stored = defaults.string(forKey: key)
      let device = stored.flatMap { UUID(uuidString: $0)?.uuidString } ?? UUID().uuidString
      if stored != device { defaults.set(device, forKey: key) }
      competitionProgress = try await client.competitionFolders(local.manifest,
        discovery: competitionFolders, role: photographerSelection?.selected, deviceUuid: device)
      competitionProgressError = nil
    } catch { competitionProgressError = error.localizedDescription }
  }

  @Published var sharedProgramme: SharedConcertProgramme?
  @Published var sharedProgrammeError: String?
  @Published var checkingProgramme = false

  var sharedProgrammeMatches: Bool {
    guard let remote = try? sharedProgramme?.localProgramme(for: local.manifest) else { return false }
    return (try? SharedConcertProgramme.sameLayout(local.manifest.programme, remote)) == true
  }
  var programmeTag: String {
    if sharedProgrammeError != nil { return "Check needed" }
    if sharedProgrammeMatches { return "Confirmed" }
    if let plan = sharedProgramme?.plan {
      return syncReceipt?.plan.uuid == plan.uuid ? "Local changes" : "Update available"
    }
    return checkingProgramme ? "Checking…" : "Not confirmed"
  }
  var canShareProgramme: Bool {
    !busy && !checkingProgramme && sharedProgrammeError == nil && sharedProgramme != nil
      && (sharedProgramme?.plan == nil || syncReceipt?.plan.uuid == sharedProgramme?.plan?.uuid)
      && !sharedProgrammeMatches
  }
  func refreshSharedProgramme(client: APIClient) async {
    guard local.manifest.programme.eventType == "concert", !checkingProgramme, !busy else { return }
    checkingProgramme = true
    defer { checkingProgramme = false }
    do {
      sharedProgramme = try await client.sharedConcertProgramme(local.manifest)
      sharedProgrammeError = nil
    } catch { sharedProgrammeError = error.localizedDescription }
  }
  func loadSharedProgramme(client: APIClient) async {
    guard !busy else { return }
    busy = true
    error = nil
    defer { busy = false }
    do {
      let latest = try await client.sharedConcertProgramme(local.manifest)
      guard let programme = try latest.localProgramme(for: local.manifest), let plan = latest.plan else {
        throw PreparationError.invalid("Nobody has confirmed a shared programme yet.")
      }
      // Validate against the current local identities before moving any media.
      // Locally added items absent online require review; they are never discarded.
      guard Set(local.manifest.programme.nodes.map(\.uuid)).isSubset(of: Set(programme.nodes.map(\.uuid))) else {
        throw PreparationError.invalid("This Mac has additional local items. Match them to the shared programme before loading; your folders and photos have been kept.")
      }
      if !(try SharedConcertProgramme.sameLayout(local.manifest.programme, programme)) {
        let manifest = try await library.saveConcertEdits(programme)
        local = LocalEvent(url: local.url, manifest: manifest)
      }
      let receipt = ProgrammeSyncReceipt(server: local.manifest.server,
        localDigest: try ProgrammeSyncReceipt.fingerprint(local.manifest.programme), plan: plan, syncedAt: Date())
      // Preserve upload acknowledgements when loading the same plan again.
      if syncReceipt?.matches(local.manifest) != true || syncReceipt?.plan.uuid != plan.uuid {
        try receipt.save(root: local.url)
        syncReceipt = receipt
      }
      sharedProgramme = latest
      sharedProgrammeError = nil
      applySnapshot(try await library.scan())
      if !local.manifest.folders.contains(where: { $0.itemUuid == folderID }) {
        selectFolder(local.manifest.folders.first?.itemUuid ?? "top-pics")
      }
      showNotice("Shared programme loaded · Confirmed")
    } catch { self.error = error.localizedDescription }
  }
  func shareProgramme(client: APIClient) async {
    guard canShareProgramme else { return }
    busy = true
    error = nil
    do {
      let receipt = try await client.syncProgramme(local.manifest, basePlanUuid: syncReceipt?.plan.uuid)
      try receipt.save(root: local.url)
      syncReceipt = receipt
      showNotice("Programme confirmed · crew notified")
    } catch { self.error = error.localizedDescription }
    busy = false
    await refreshSharedProgramme(client: client)
  }

  func refreshPhotographers(client: APIClient) async {
    guard !loadingPhotographers else { return }
    loadingPhotographers = true
    photographerError = nil
    defer { loadingPhotographers = false }
    do {
      let roster = try await client.photographers(for: local.manifest)
      var saved =
        photographerSelection
        ?? PhotographerSelection(
          server: local.manifest.server,
          roster: roster, selectedId: nil)
      try saved.refresh(roster)
      // Apply the refreshed roster even if this Mac cannot persist it: a removed
      // assignment must not remain selected after a successful server refresh.
      photographerSelection = saved
      try saved.save(root: local.url)
    } catch { photographerError = error.localizedDescription }
  }

  func refreshProgress(client: APIClient) async {
    guard !loadingProgress else { return }
    serverProgress = nil
    progressError = nil
    guard let receipt = syncReceipt, receipt.matches(local.manifest) else {
      progressError = "Upload from this working programme first to connect its server progress."
      return
    }
    loadingProgress = true
    defer { loadingProgress = false }
    do {
      let result = try await client.photoProgress(manifest: local.manifest, receipt: receipt)
      guard receipt.matches(local.manifest), syncReceipt?.plan.uuid == receipt.plan.uuid else { return }
      serverProgress = result
    } catch { progressError = error.localizedDescription }
  }

  func selectPhotographer(_ id: String?) {
    guard !busy, !loadingPhotographers, var saved = photographerSelection else { return }
    guard saved.selectedId == nil || id == saved.selectedId || queuedIDs.isEmpty else {
      photographerError = "Upload or clear queued photos before changing this event's role. Then mark the new role's photos Ready."
      return
    }
    guard id == nil || saved.roster.photographers.contains(where: { $0.id == id }) else { return }
    saved.selectedId = id
    do {
      try saved.save(root: local.url)
      photographerSelection = saved
      photographerError = nil
    } catch { photographerError = error.localizedDescription }
  }

  var programmeSynced: Bool { syncReceipt?.matches(local.manifest) == true }

  var completionRecorded: Bool {
    guard let receipt = syncReceipt, receipt.matches(local.manifest),
      let photographer = photographerSelection?.selected,
      let completion = receipt.photoCompletion else { return false }
    return completion.matches(receipt: receipt, photographer: photographer)
      && photos.allSatisfy(\.uploaded) && queuedIDs.isEmpty && pending.values.allSatisfy { $0 == 0 }
  }

  private var completionSummary: String {
    let waived = syncReceipt?.photoCompletion?.waivedItemCount ?? 0
    return waived > 0 ? "Completion recorded · \(waived) items waived · gallery private" : "Finish recorded · gallery private"
  }

  var canFinishUploads: Bool {
    competitionFolders?.issues.isEmpty != false && !busy && photographerSelection?.selected != nil && photographerSelection?.selected?.isVideo != true && queuedIDs.isEmpty
      && photos.allSatisfy(\.uploaded) && pending.values.allSatisfy { $0 == 0 }
  }

  func finishUploads(client: APIClient) async {
    guard !busy, let photographer = photographerSelection?.selected else { return }
    guard !photographer.isVideo else {
      error = "This is the V station. Video uploading is not connected yet."
      return
    }
    busy = true
    error = nil
    defer { busy = false }
    do {
      applySnapshot(try await library.scan())
      guard competitionFolders?.issues.isEmpty != false else {
        throw PreparationError.invalid("Check the unmatched or unsafe competition folders in Programme before marking Upload Complete. Other uploads can continue.")
      }
      guard queuedIDs.isEmpty, photos.allSatisfy(\.uploaded), pending.values.allSatisfy({ $0 == 0 }) else {
        throw PreparationError.invalid("Mark the remaining photos Ready and upload them before finishing.")
      }
      var receipt = try await client.syncProgramme(local.manifest)
      receipt.photoCompletion = try await client.finishPhotoUploads(
        manifest: local.manifest, receipt: receipt, photographer: photographer)
      try receipt.save(root: local.url)
      syncReceipt = receipt
      applySnapshot(try await library.scan())
      uploadStatus = completionRecorded
        ? completionSummary
        : "Completion recorded · new photos need Ready and Upload"
    } catch { self.error = error.localizedDescription }
  }

  func startUploads(client: APIClient) {
    guard !busy, !isUploading else { return }
    guard let photographer = photographerSelection?.selected else {
      error = "Choose this event’s role beneath the event name before uploading."
      return
    }
    guard !photographer.isVideo else {
      error = "This is the V station. Select a photo role before uploading JPEGs. Video upload is not connected yet."
      return
    }
    isUploading = true
    busy = true
    uploadPaused = false
    error = nil
    uploadTask = Task { await runUploads(client: client, photographer: photographer) }
  }

  func pauseUploads() {
    uploadStatus = "Pausing…"
    uploadTask?.cancel()
  }

  private func runUploads(client: APIClient, photographer: EventPhotographer) async {
    var completed = 0
    var failures = 0
    defer {
      isUploading = false
      busy = false
      uploadTask = nil
    }
    while !Task.isCancelled {
      do {
        uploadStatus = "Preparing queued photos…"
        let prepared = try await library.queuedUploadPhotos()
        let refreshed = try await library.scan()
        applySnapshot(refreshed)
        if prepared.isEmpty && !refreshed.queuedIDs.isEmpty { continue }
        guard !prepared.isEmpty else {
          uploadStatus =
            completed > 0 ? "\(completed) photos uploaded · gallery private" : "No photos queued"
          uploadPaused = false
          return
        }
        var receipt = try await client.syncProgramme(local.manifest)
        receipt.photoContributions = try await client.registerContributions(
          manifest: local.manifest,
          receipt: receipt, photographer: photographer, itemIDs: Set(prepared.map(\.itemUuid)))
        receipt.photoFiles = try await client.reservePhotos(
          manifest: local.manifest,
          receipt: receipt, photographer: photographer, photos: prepared)
        try receipt.save(root: local.url)
        syncReceipt = receipt
        let reservations = Dictionary(
          uniqueKeysWithValues: receipt.photoFiles!.files.map { ($0.photoUuid.lowercased(), $0) })
        for (index, photo) in prepared.enumerated() {
          try Task.checkCancellation()
          uploadStatus = "Uploading \(index + 1) of \(prepared.count)…"
          let staged = try await library.stageUpload(photo)
          defer { try? FileManager.default.removeItem(at: staged.deletingLastPathComponent()) }
          let reservation = reservations[photo.photoUuid.lowercased()]!
          let verified = try await client.transferPhoto(
            manifest: local.manifest, receipt: receipt,
            reservation: reservation, stagedFile: staged)
          // Record a received acknowledgement even if Pause was pressed just after
          // the response. Otherwise a lost response is safely replayed on Resume.
          try await library.acknowledgeUpload(photo, serverFileUuid: verified.uuid)
          completed += 1
          failures = 0
          applySnapshot(try await library.scan())
        }
        // Ready actions during this session join the next batch.
      } catch {
        if Task.isCancelled { break }
        if PhotoUploadRetry.isTransient(error) {
          failures += 1
          uploadStatus = "Connection interrupted · retrying…"
          do { try await Task.sleep(for: .seconds(min(30, 2 * failures))) } catch { break }
          continue
        }
        self.error = error.localizedDescription
        uploadStatus = "Upload paused · photos kept on this Mac"
        uploadPaused = true
        return
      }
    }
    uploadPaused = !queuedIDs.isEmpty
    uploadStatus = uploadPaused ? "Upload paused" : "Uploads verified · gallery private"
  }

  @Published var competitionFolders: CompetitionFolderDiscovery?
  private func applySnapshot(_ snapshot: ReviewSnapshot) {
    if let competition = snapshot.competition {
      competitionFolders = competition
      local = LocalEvent(url: local.url, manifest: competition.manifest)
    }
    photos = snapshot.photos
    pending = snapshot.pending
    canUndo = snapshot.canUndo
    canUndoMove = snapshot.canUndoMove
    queuedIDs = snapshot.queuedIDs
    lockedItemIDs = snapshot.lockedItemIDs
    if !isUploading, let receipt = syncReceipt, receipt.matches(local.manifest),
      let person = photographerSelection?.selected,
      receipt.photoCompletion?.matches(receipt: receipt, photographer: person) == true {
      uploadStatus = completionRecorded
        ? completionSummary
        : "New photos · Ready and Upload, then finish again"
    }
    reconcileSelection()
  }

  var visiblePhotos: [ReviewPhoto] {
    photos.filter { folderID == "top-pics" ? $0.isTopPic : $0.itemID == folderID }
  }
  var selected: ReviewPhoto? { visiblePhotos.first { $0.id == photoID } }
  var position: Int { visiblePhotos.firstIndex { $0.id == photoID } ?? 0 }
  var folderTitle: String {
    if folderID == "top-pics" { return "Top Pics" }
    return local.manifest.programme.nodes.first { $0.uuid == folderID }?.title ?? "Photos"
  }
  func watch() async {
    while !Task.isCancelled {
      await refresh()
      do { try await Task.sleep(for: .seconds(2)) } catch { return }
    }
  }
  func refresh() async {
    guard !refreshing, !busy else { return }
    refreshing = true
    defer { refreshing = false }
    do {
      let snapshot = try await library.scan()
      guard !Task.isCancelled else { return }
      applySnapshot(snapshot)
    } catch {
      self.error = error.localizedDescription
      photos = []
      queuedIDs = []
      image = nil
      previewKey = nil
    }
  }
  func selectFolder(_ id: String) {
    moveSelection.removeAll()
    folderID = id
    photoID = nil
    reconcileSelection()
  }
  var previousItemID: String? {
    let items = local.manifest.programme.nodes.filter {
      $0.isActiveItem
    }
    guard let index = items.firstIndex(where: { $0.uuid == folderID }), index > 0 else {
      return nil
    }
    return items[index - 1].uuid
  }
  func previousItem() {
    if let previousItemID { selectFolder(previousItemID) }
  }
  var nextItemID: String? {
    let items = local.manifest.programme.nodes.filter {
      $0.isActiveItem
    }
    if folderID == "top-pics" { return items.first?.uuid }
    guard let index = items.firstIndex(where: { $0.uuid == folderID }), index + 1 < items.count
    else { return nil }
    return items[index + 1].uuid
  }
  func nextItem() {
    if let nextItemID { selectFolder(nextItemID) }
  }
  func step(_ offset: Int) {
    guard !visiblePhotos.isEmpty else { return }
    photoID = visiblePhotos[min(max(0, position + offset), visiblePhotos.count - 1)].id
    loadPreview()
  }
  var queuedPhotos: [ReviewPhoto] { photos.filter { queuedIDs.contains($0.id) } }
  func itemIDs(in groupID: String) -> Set<String> {
    let nodes = local.manifest.programme.nodes
    if nodes.first(where: { $0.uuid == groupID })?.kind == "section" {
      return Set(
        nodes.filter { $0.parentUuid == groupID && $0.isActiveItem }
          .map(\.uuid))
    }
    return [groupID]
  }
  func readyCount(_ groupID: String) -> Int {
    let items = itemIDs(in: groupID)
    return photos.filter { items.contains($0.itemID) && !$0.uploaded && !queuedIDs.contains($0.id) }
      .count
  }
  func markReady(_ groupID: String) async {
    let items = itemIDs(in: groupID)
    if isUploading {
      do {
        _ = try await library.scan()
        try await library.markReady(itemIDs: items)
        applySnapshot(try await library.scan())
      } catch { self.error = error.localizedDescription }
      return
    }
    await mutate {
      try await self.library.markReady(itemIDs: items)
      self.showNotice("Photos queued on this Mac")
    }
  }
  func unqueue(_ ids: Set<String>) async {
    await mutate { try await self.library.removeFromQueue(ids) }
  }
  func saveConcertLayout(_ programme: Programme) async -> Bool {
    guard !busy else { return false }
    var saved = false
    await mutate {
      let manifest = try await self.library.saveConcertEdits(programme)
      self.local = LocalEvent(url: self.local.url, manifest: manifest)
      self.moveSelection.removeAll()
      if let target = manifest.programme.nodes.first(where: { $0.uuid == self.folderID })?
        .mergedIntoUuid
      {
        self.selectFolder(target)
      }
      self.showNotice("Concert folders updated")
      saved = true
    }
    return saved
  }
  func splitSelection(title: String) async -> Bool {
    guard !busy, !moveSelection.isEmpty else { return false }
    let ids = moveSelection.sorted()
    let sourceItemID = folderID
    var created = false
    await mutate {
      let result = try await self.library.splitPhotos(ids, from: sourceItemID, title: title)
      self.local = LocalEvent(url: self.local.url, manifest: result.manifest)
      self.moveSelection.removeAll()
      created = true
      if let error = result.moveError { throw PreparationError.invalid(error) }
      self.selectFolder(result.itemID)
      self.showNotice(
        "Moved \(ids.count) photo\(ids.count == 1 ? "" : "s") to \(title.trimmingCharacters(in: .whitespacesAndNewlines))",
        undo: .move)
    }
    return created
  }
  func toggleMoveSelection(_ id: String) {
    guard !busy, visiblePhotos.contains(where: { $0.id == id && !$0.uploaded }) else { return }
    if !moveSelection.insert(id).inserted { moveSelection.remove(id) }
  }
  func dragPayload(for id: String) -> String {
    let ids = moveSelection.contains(id) ? moveSelection.sorted() : [id]
    return ([dragSession] + ids).joined(separator: "\n")
  }
  func canAcceptMove(_ text: String, to itemID: String) -> Bool {
    guard movingPhotos, !isTopPics, !busy, itemID != folderID,
      !lockedItemIDs.contains(folderID), !lockedItemIDs.contains(itemID),
      local.manifest.folders.contains(where: { $0.itemUuid == itemID })
    else { return false }
    let parts = text.components(separatedBy: "\n")
    guard parts.first == dragSession, parts.count > 1 else { return false }
    let ids = Array(parts.dropFirst())
    return Set(ids).count == ids.count
      && ids.allSatisfy { id in
        visiblePhotos.contains { $0.id == id && !$0.uploaded }
      }
  }
  func acceptMove(_ payloads: [String], to itemID: String) -> Bool {
    guard payloads.count == 1, canAcceptMove(payloads[0], to: itemID) else { return false }
    let ids = Array(payloads[0].components(separatedBy: "\n").dropFirst())
    Task { await movePhotos(ids, to: itemID) }
    return true
  }
  func movePhotos(_ ids: [String], to itemID: String) async {
    await mutate {
      try await self.library.move(ids, to: itemID)
      self.moveSelection.subtract(ids)
      self.showNotice("Moved \(ids.count) photo\(ids.count == 1 ? "" : "s")", undo: .move)
    }
  }
  func thumbnail(_ id: String) async throws -> NSImage? {
    let data = try await library.preview(id, maxPixelSize: 360)
    return NSImage(data: data)
  }
  func toggleTopPic() async {
    guard let selected else { return }
    await mutate { try await self.library.toggleTopPic(selected.id) }
  }
  var isTopPics: Bool { folderID == "top-pics" }
  var canRemoveSelected: Bool {
    selected != nil && !busy
      && (isTopPics || (selected?.uploaded != true && !lockedItemIDs.contains(folderID)))
  }
  func trash() async {
    guard let selected, canRemoveSelected else { return }
    // Capture the selected collection before any asynchronous work. Top Pics
    // removal must never fall through to a filesystem delete, even if navigation changes.
    let removingTopPic = isTopPics
    let originalFolder = folderID
    let oldPosition = position
    await mutate {
      if removingTopPic {
        try await self.library.setTopPic(selected.id, selected: false)
        self.showNotice("Removed \(selected.name) from Top Pics — original kept")
      } else {
        try await self.library.trash(selected.id)
        self.showNotice("Moved \(selected.name) to Trash", undo: .trash)
        self.photos.removeAll { $0.id == selected.id }
        if self.folderID == originalFolder {
          self.photoID =
            self.visiblePhotos.isEmpty
            ? nil : self.visiblePhotos[min(oldPosition, self.visiblePhotos.count - 1)].id
        }
      }
    }
  }
  func undo() async {
    guard canUndo else { return }
    await mutate {
      try await self.library.undoTrash()
      self.showNotice("Photo restored")
    }
  }
  func undoMove() async {
    guard canUndoMove else { return }
    await mutate {
      try await self.library.undoMove()
      self.showNotice("Photos returned to their original folders")
    }
  }
  func undoNotice() async {
    switch noticeUndo {
    case .move: await undoMove()
    case .trash: await undo()
    case nil: break
    }
  }
  private func showNotice(_ message: String, undo: ReviewUndoAction? = nil) {
    noticeTask?.cancel()
    notice = message
    noticeUndo = undo
    noticeTask = Task { [weak self] in
      do { try await Task.sleep(for: .seconds(5)) } catch { return }
      guard !Task.isCancelled else { return }
      self?.notice = nil
    }
  }
  private func mutate(_ work: () async throws -> Void) async {
    guard !busy else { return }
    busy = true
    error = nil
    do { try await work() } catch {
      noticeTask?.cancel()
      notice = nil
      self.error = error.localizedDescription
    }
    busy = false
    await refresh()
  }
  private func reconcileSelection() {
    moveSelection.formIntersection(visiblePhotos.filter { !$0.uploaded }.map(\.id))
    if !visiblePhotos.contains(where: { $0.id == photoID }) { photoID = visiblePhotos.first?.id }
    loadPreview()
  }
  private func loadPreview() {
    let key = selected.map { $0.id + $0.signature }
    guard key != previewKey else { return }
    previewKey = key
    previewTask?.cancel()
    image = nil
    guard let selected else {
      loading = false
      return
    }
    loading = true
    previewTask = Task { [weak self, library] in
      do {
        let data = try await library.preview(selected.id)
        guard !Task.isCancelled, let self, self.previewKey == key else { return }
        self.image = NSImage(data: data)
        self.loading = false
      } catch {
        guard !Task.isCancelled, let self, self.previewKey == key else { return }
        self.loading = false
        self.error = error.localizedDescription
      }
    }
  }
}
