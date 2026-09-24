import CryptoKit
import Darwin
import Foundation
import ImageIO
import PreparationCore

public struct ReviewPhoto: Identifiable, Sendable {
  public let id: String
  public let itemID: String
  public let relativePath: String
  public let isTopPic: Bool
  public let uploaded: Bool
  public let signature: String
  public var name: String { (relativePath as NSString).lastPathComponent }
}

public struct ReviewSnapshot: Sendable {
  public let photos: [ReviewPhoto]
  public let pending: [String: Int]
  public let canUndo: Bool
  public let canUndoMove: Bool
  public let queuedIDs: Set<String>
  public let lockedItemIDs: Set<String>
  public var competition: CompetitionFolderDiscovery? = nil
}

public struct QueuedUploadPhoto: Sendable {
  public let photoUuid: String
  public let itemUuid: String
  public let fileName: String
  public let byteCount: Int64
  public let sha256: String
  public let isTopPic: Bool
  public var signature: String? = nil
}

public struct PhotoSplitResult: Sendable {
  public let manifest: PreparedEvent
  public let itemID: String
  public let moveError: String?
}

private struct PhotoRecord: Codable {
  var id: String
  var itemID: String
  var relativePath: String
  var identity: String
  var topPic = false
  // Future verified-upload integration must set this before allowing more review.
  var uploaded = false
  var uploadedSignature: String?
  var serverFileUuid: String?
}
private struct TrashRecord: Codable {
  let photoID: String
  let originalPath: String
  let trashPath: String
  let identity: String
}
private struct MoveRecord: Codable {
  let photoID: String
  let originalItemID: String
  let destinationItemID: String
  let signature: String
}
private struct QueuedPhoto: Codable {
  let signature: String
  let itemID: String
  let relativePath: String
}
private struct ReviewState: Codable {
  var version = 1
  let eventID: String
  let revisionID: String
  var records: [String: PhotoRecord] = [:]
  var trash: [TrashRecord] = []
  var moves: [[MoveRecord]]?
  var queued: [String: QueuedPhoto]?
  var uploadedItems: [String]?
}
private struct Observation {
  let signature: String
  let since: Date
}

/// All disk scanning and mutations are serialized off the UI actor.
public actor PhotoLibrary {
  private let root: URL
  private var folders: [PlannedFolder]
  private var itemAliases: [String: String]
  private let baseManifest: PreparedEvent
  private var layoutBlocked = false
  private let stateURL: URL
  private var state: ReviewState
  private var observations: [String: Observation] = [:]
  private var visible: [String: ReviewPhoto] = [:]
  private var validJPEGs: [String: String] = [:]
  private let quietInterval: TimeInterval

  public init(root: URL, manifest: PreparedEvent, quietInterval: TimeInterval = 2) throws {
    self.root = root.standardizedFileURL
    self.baseManifest = manifest
    let resolved = try ConcertLayout.load(root: root, base: manifest)
    self.folders = resolved.folders
    self.itemAliases = Dictionary(
      uniqueKeysWithValues: resolved.programme.nodes.compactMap { node in
        node.mergedIntoUuid.map { (node.uuid, $0) }
      })
    self.quietInterval = quietInterval
    stateURL = root.appendingPathComponent("photo-review.json")
    if FileManager.default.fileExists(atPath: stateURL.path) {
      let values = try stateURL.resourceValues(forKeys: [.isSymbolicLinkKey, .isRegularFileKey])
      guard values.isSymbolicLink != true, values.isRegularFile == true else {
        throw PreparationError.invalid("The photo-review file is not a regular file.")
      }
      state = try JSONDecoder().decode(ReviewState.self, from: Data(contentsOf: stateURL))
      guard state.version == 1, state.eventID == manifest.programme.eventUuid,
        state.revisionID == manifest.programme.revisionUuid
      else {
        throw PreparationError.invalid(
          "The saved review belongs to another programme or uses an unsupported format.")
      }
    } else {
      state = ReviewState(
        eventID: manifest.programme.eventUuid, revisionID: manifest.programme.revisionUuid)
    }
  }

  public func scan(now: Date = Date()) throws -> ReviewSnapshot {
    guard !layoutBlocked else {
      throw PreparationError.invalid("Reopen this event to finish the interrupted folder change.")
    }
    var competition = baseManifest.programme.eventType == "competition" ? try CompetitionFolders.discover(root: root, base: baseManifest) : nil
    if let competition { folders = competition.manifest.folders }
    let previousState = state
    var photos: [ReviewPhoto] = []
    var pending: [String: Int] = [:]
    var seen = Set<String>()
    var currentSignatures: [String: String] = [:]
    var changed = false
    for id in Array(state.records.keys) {
      if let itemID = state.records[id]?.itemID, let target = itemAliases[itemID] {
        state.records[id]?.itemID = target
        state.queued?[id] = nil
        changed = true
      }
    }
    if let history = state.moves {
      let retained = history.filter { batch in
        !batch.contains {
          itemAliases[$0.originalItemID] != nil || itemAliases[$0.destinationItemID] != nil
        }
      }
      if retained.count != history.count {
        state.moves = retained
        changed = true
      }
    }
    let byIdentity = Dictionary(
      state.records.values.map { ($0.identity, $0.id) }, uniquingKeysWith: { first, _ in first })
    for folder in folders {
      let directory = try safeURL(folder.relativePath, directory: true)
      let entries = try FileManager.default.contentsOfDirectory(
        at: directory, includingPropertiesForKeys: nil, options: [.skipsHiddenFiles])
      for url in entries where ["jpg", "jpeg"].contains(url.pathExtension.lowercased()) {
        let path = folder.relativePath + "/" + url.lastPathComponent
        guard let info = try? fileInfo(try safeURL(path)), seen.insert(info.identity).inserted
        else { continue }
        currentSignatures[path] = info.signature
        if observations[path]?.signature != info.signature {
          observations[path] = Observation(signature: info.signature, since: now)
        }
        guard let observed = observations[path],
          now.timeIntervalSince(observed.since) >= quietInterval,
          now.timeIntervalSince(info.modified) >= quietInterval
        else {
          pending[folder.itemUuid, default: 0] += 1
          continue
        }
        if validJPEGs[path] != info.signature {
          guard Self.completeJPEG(url), let after = try? fileInfo(url),
            after.signature == info.signature
          else {
            pending[folder.itemUuid, default: 0] += 1
            continue
          }
          validJPEGs[path] = info.signature
        }
        let id = byIdentity[info.identity] ?? UUID().uuidString
        var record =
          state.records[id]
          ?? PhotoRecord(
            id: id, itemID: folder.itemUuid, relativePath: path, identity: info.identity)
        if competition != nil && record.uploaded && record.itemID != folder.itemUuid {
          pending[folder.itemUuid, default: 0] += 1
          competition?.issues.append("Uploaded photo moved to a different competitor: " + path + ". Restore its original competitor folder or request an administrator correction.")
          continue
        }
        if state.records[id] == nil || record.relativePath != path
          || record.itemID != folder.itemUuid
        {
          record.relativePath = path
          record.itemID = folder.itemUuid
          state.records[id] = record
          changed = true
        }
        if record.uploaded, let uploadedSignature = record.uploadedSignature,
          uploadedSignature != info.signature
        {
          record.uploaded = false
          state.records[id] = record
          changed = true
        }
        photos.append(
          ReviewPhoto(
            id: id, itemID: folder.itemUuid, relativePath: path, isTopPic: record.topPic,
            uploaded: record.uploaded, signature: info.signature))
      }
    }
    photos.sort { $0.relativePath.localizedStandardCompare($1.relativePath) == .orderedAscending }
    if let queued = state.queued {
      let retained = queued.filter { id, entry in
        currentSignatures[entry.relativePath] == entry.signature
          && state.records[id]?.itemID == entry.itemID && state.records[id]?.uploaded == false
      }
      if retained.count != queued.count {
        state.queued = retained
        changed = true
      }
    }
    if changed {
      do { try save() } catch {
        state = previousState
        throw error
      }
    }
    visible = Dictionary(uniqueKeysWithValues: photos.map { ($0.id, $0) })
    let queuedIDs = Set(
      photos.filter { photo in
        guard let queued = state.queued?[photo.id] else { return false }
        return !photo.uploaded && queued.signature == photo.signature
          && queued.itemID == photo.itemID
          && queued.relativePath == photo.relativePath
      }.map(\.id))
    return ReviewSnapshot(
      photos: photos, pending: pending, canUndo: !state.trash.isEmpty,
      canUndoMove: !(state.moves ?? []).isEmpty, queuedIDs: queuedIDs, lockedItemIDs: lockedItemIDs, competition: competition)
  }

  /// Hash only explicitly queued, unchanged JPEGs, without loading full files into memory.
  public func queuedUploadPhotos() throws -> [QueuedUploadPhoto] {
    let snapshot = try scan()
    return try snapshot.photos.filter { snapshot.queuedIDs.contains($0.id) }.map { photo in
      try Task.checkCancellation()
      let url = try currentURL(photo.id)
      let descriptor = Darwin.open(url.path, O_RDONLY | O_NOFOLLOW)
      guard descriptor >= 0 else {
        throw PreparationError.invalid("Unable to open \(photo.name) for upload preparation.")
      }
      let handle = FileHandle(fileDescriptor: descriptor, closeOnDealloc: true)
      defer { try? handle.close() }
      var opened = stat()
      var pathInfo = stat()
      guard fstat(descriptor, &opened) == 0, lstat(url.path, &pathInfo) == 0,
        opened.st_dev == pathInfo.st_dev, opened.st_ino == pathInfo.st_ino,
        opened.st_mode & S_IFMT == S_IFREG, opened.st_size >= 4, opened.st_size <= 536_870_912
      else {
        throw PreparationError.invalid(
          "The photo changed or exceeds the 512 MB upload limit. Check \(photo.name).")
      }
      var hasher = SHA256()
      var count: Int64 = 0
      while let data = try handle.read(upToCount: 1_048_576), !data.isEmpty {
        try Task.checkCancellation()
        count += Int64(data.count)
        guard count <= opened.st_size else {
          throw PreparationError.invalid(
            "The photo is still changing. Mark it Ready again when capture finishes.")
        }
        hasher.update(data: data)
      }
      var finished = stat()
      guard fstat(descriptor, &finished) == 0, count == opened.st_size,
        opened.st_size == finished.st_size,
        opened.st_mtimespec.tv_sec == finished.st_mtimespec.tv_sec,
        opened.st_mtimespec.tv_nsec == finished.st_mtimespec.tv_nsec,
        opened.st_ctimespec.tv_sec == finished.st_ctimespec.tv_sec,
        opened.st_ctimespec.tv_nsec == finished.st_ctimespec.tv_nsec
      else {
        throw PreparationError.invalid(
          "The photo changed while preparing it. Mark it Ready again when capture finishes.")
      }
      _ = try currentURL(photo.id)
      return QueuedUploadPhoto(
        photoUuid: photo.id, itemUuid: photo.itemID, fileName: photo.name,
        byteCount: count, sha256: hasher.finalize().map { String(format: "%02x", $0) }.joined(),
        isTopPic: photo.isTopPic, signature: photo.signature)
    }
  }

  private var lockedItemIDs: Set<String> {
    Set(state.uploadedItems ?? []).union(state.records.values.filter { $0.uploaded }.map(\.itemID))
  }

  /// Copy the exact expected version to a private temporary file for URLSession.
  /// The caller removes only this generated directory after the request finishes.
  public func stageUpload(_ photo: QueuedUploadPhoto) throws -> URL {
    guard state.queued?[photo.photoUuid] != nil, visible[photo.photoUuid]?.itemID == photo.itemUuid
    else {
      throw PreparationError.invalid("This photo is no longer queued for that item.")
    }
    let source = try currentURL(photo.photoUuid)
    let directory = FileManager.default.temporaryDirectory.appendingPathComponent(
      "dancepro-upload-" + UUID().uuidString)
    try FileManager.default.createDirectory(
      at: directory, withIntermediateDirectories: false,
      attributes: [.posixPermissions: 0o700])
    let target = directory.appendingPathComponent("photo.jpg")
    do {
      try FileManager.default.copyItem(at: source, to: target)
      let values = try target.resourceValues(forKeys: [.isSymbolicLinkKey, .isRegularFileKey])
      guard values.isSymbolicLink != true, values.isRegularFile == true else {
        throw PreparationError.invalid("The queued photo was replaced during preparation.")
      }
      try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: target.path)
      let handle = try FileHandle(forReadingFrom: target)
      defer { try? handle.close() }
      var hash = SHA256()
      var count: Int64 = 0
      while let chunk = try handle.read(upToCount: 1_048_576), !chunk.isEmpty {
        try Task.checkCancellation()
        count += Int64(chunk.count)
        guard count <= photo.byteCount else {
          throw PreparationError.invalid("The queued photo changed. Mark it Ready again.")
        }
        hash.update(data: chunk)
      }
      _ = try currentURL(photo.photoUuid)
      guard count == photo.byteCount,
        hash.finalize().map({ String(format: "%02x", $0) }).joined() == photo.sha256
      else {
        throw PreparationError.invalid("The queued photo changed. Mark it Ready again.")
      }
      return target
    } catch {
      try? FileManager.default.removeItem(at: directory)
      throw error
    }
  }

  /// Called only after the app validates a server verified-file receipt.
  public func acknowledgeUpload(_ photo: QueuedUploadPhoto, serverFileUuid: String) throws {
    guard let record = state.records[photo.photoUuid], record.itemID == photo.itemUuid,
      UUID(uuidString: serverFileUuid) != nil, let signature = photo.signature
    else {
      throw PreparationError.invalid(
        "The verified photo no longer matches this local review record.")
    }
    let previous = state
    state.records[photo.photoUuid]?.uploaded = true
    state.records[photo.photoUuid]?.uploadedSignature = signature
    state.records[photo.photoUuid]?.serverFileUuid = serverFileUuid
    state.uploadedItems = Array(lockedItemIDs.union([photo.itemUuid])).sorted()
    state.queued?[photo.photoUuid] = nil
    do { try save() } catch {
      state = previous
      throw error
    }
  }

  public func markReady(itemIDs: Set<String>) throws {
    let previous = state.queued
    var queued = state.queued ?? [:]
    for photo in visible.values where itemIDs.contains(photo.itemID) && !photo.uploaded {
      _ = try currentURL(photo.id)
      queued[photo.id] = QueuedPhoto(
        signature: photo.signature, itemID: photo.itemID, relativePath: photo.relativePath)
    }
    state.queued = queued
    do { try save() } catch {
      state.queued = previous
      throw error
    }
  }

  public func removeFromQueue(_ ids: Set<String>) throws {
    let previous = state.queued
    for id in ids { state.queued?[id] = nil }
    do { try save() } catch {
      state.queued = previous
      throw error
    }
  }

  public func combineItems(from sourceID: String, into targetID: String, title: String) throws
    -> PreparedEvent
  {
    guard baseManifest.programme.eventType == "concert", sourceID != targetID,
      lockedItemIDs.isEmpty
    else {
      throw PreparationError.invalid("Choose two different unuploaded concert items.")
    }
    var programme = try ConcertLayout.load(root: root, base: baseManifest).programme
    guard
      let sourceIndex = programme.nodes.firstIndex(where: { $0.uuid == sourceID && $0.isActiveItem }
      ),
      let targetIndex = programme.nodes.firstIndex(where: { $0.uuid == targetID && $0.isActiveItem }
      )
    else {
      throw PreparationError.invalid("Choose two active concert items.")
    }
    programme.nodes[sourceIndex].mergedIntoUuid = targetID
    programme.nodes[targetIndex].title = title
    for index in programme.nodes.indices where programme.nodes[index].mergedIntoUuid == sourceID {
      programme.nodes[index].mergedIntoUuid = targetID
    }
    return try saveConcertEdits(programme)
  }

  public func saveConcertEdits(_ draft: Programme) throws -> PreparedEvent {
    let current = try ConcertLayout.load(root: root, base: baseManifest).programme
    let retiring = draft.nodes.filter { node in
      node.mergedIntoUuid != nil
        && current.nodes.contains { $0.uuid == node.uuid && $0.isActiveItem }
    }
    guard !retiring.isEmpty else { return try changeConcertLayout(draft) }
    guard current.eventType == "concert", lockedItemIDs.isEmpty
    else {
      throw PreparationError.invalid("Only unuploaded concert items can be combined.")
    }
    let snapshot = try scan()
    guard snapshot.pending.values.allSatisfy({ $0 == 0 }) else {
      throw PreparationError.invalid("Wait for capture to finish before combining items.")
    }
    var destinations: [(id: String, itemID: String)] = []
    for node in retiring {
      guard let folder = folders.first(where: { $0.itemUuid == node.uuid }),
        let target = node.mergedIntoUuid,
        draft.nodes.contains(where: { $0.uuid == target && $0.isActiveItem }),
        folders.contains(where: { $0.itemUuid == target })
      else { throw PreparationError.invalid("Choose existing concert folders to combine.") }
      let photos = snapshot.photos.filter { $0.itemID == node.uuid }
      let allowedNames = Set(photos.map(\.name) + [".DS_Store"])
      let directory = try safeURL(folder.relativePath, directory: true)
      guard
        try FileManager.default.contentsOfDirectory(atPath: directory.path).allSatisfy({
          allowedNames.contains($0)
        })
      else {
        throw PreparationError.invalid(
          "An item contains files other than ready JPEGs. Move those files separately before combining items."
        )
      }
      destinations += photos.map { ($0.id, target) }
    }
    let programme = try ConcertLayout.normalized(draft)
    if !destinations.isEmpty { try movePhotos(destinations, undoing: false) }
    do {
      return try changeConcertLayout(programme)
    } catch {
      if !layoutBlocked && !destinations.isEmpty {
        do {
          _ = try scan()
          try undoMove()
        } catch {
          throw PreparationError.invalid(
            "The photos moved, but combining the items could not finish. Reopen the event and check the folders before retrying."
          )
        }
      }
      throw error
    }
  }

  public func splitPhotos(_ ids: [String], from itemID: String, title: String) throws
    -> PhotoSplitResult
  {
    guard baseManifest.programme.eventType == "concert", !ids.isEmpty,
      Set(ids).count == ids.count
    else {
      throw PreparationError.invalid("Select concert photos to split into a new item.")
    }
    for id in ids {
      _ = try currentURL(id)
      guard visible[id]?.itemID == itemID, visible[id]?.uploaded == false else {
        throw PreparationError.invalid(
          "The selection must contain unuploaded photos from the current item.")
      }
    }
    let signatures = Dictionary(uniqueKeysWithValues: ids.map { ($0, visible[$0]!.signature) })
    var programme = try ConcertLayout.load(root: root, base: baseManifest).programme
    guard
      let index = programme.nodes.firstIndex(where: {
        $0.uuid == itemID && $0.isActiveItem
      })
    else {
      throw PreparationError.invalid("The source concert item is no longer available.")
    }
    var newItem = ConcertLayout.newItem(title: title)
    newItem.parentUuid = programme.nodes[index].parentUuid
    programme.nodes.insert(newItem, at: index + 1)
    let manifest = try changeConcertLayout(programme)
    do {
      _ = try scan()
      guard ids.allSatisfy({ visible[$0]?.signature == signatures[$0] }) else {
        throw PreparationError.invalid(
          "A selected photo changed during folder preparation. Wait for capture to finish.")
      }
      try move(ids, to: newItem.uuid)
      return PhotoSplitResult(manifest: manifest, itemID: newItem.uuid, moveError: nil)
    } catch {
      // The new structure has committed. Return it even if the subsequent file
      // move fails so the UI cannot keep showing stale folder assignments.
      return PhotoSplitResult(
        manifest: manifest, itemID: newItem.uuid,
        moveError:
          "The new item was created, but moving the photos failed: \(error.localizedDescription) Use Move Photos to retry into that item."
      )
    }
  }

  public func changeConcertLayout(_ programme: Programme) throws -> PreparedEvent {
    guard lockedItemIDs.isEmpty else {
      throw PreparationError.invalid(
        "Uploaded concert items must be corrected in the management portal.")
    }
    // Recheck all folders before renumbering; capture must be paused by the operator.
    let snapshot = try scan()
    guard snapshot.pending.values.allSatisfy({ $0 == 0 }) else {
      throw PreparationError.invalid(
        "Some photos are still arriving. Pause capture and wait for files to finish before changing folders."
      )
    }
    do {
      let updated = try ConcertLayout.apply(programme, root: root, base: baseManifest)
      let priorObservations = observations
      folders = updated.folders
      itemAliases = Dictionary(
        uniqueKeysWithValues: updated.programme.nodes.compactMap { node in
          node.mergedIntoUuid.map { (node.uuid, $0) }
        })
      observations.removeAll()
      validJPEGs.removeAll()
      for photo in snapshot.photos {
        if let folder = folders.first(where: { $0.itemUuid == photo.itemID }),
          let observed = priorObservations[photo.relativePath]
        {
          let path = folder.relativePath + "/" + photo.name
          observations[path] = observed
          validJPEGs[path] = photo.signature
        }
      }
      visible.removeAll()
      return updated
    } catch {
      layoutBlocked = FileManager.default.fileExists(
        atPath: root.appendingPathComponent("layout-change.json").path)
      throw error
    }
  }

  public func toggleTopPic(_ id: String) throws {
    guard let record = state.records[id] else { return }
    try setTopPic(id, selected: !record.topPic)
  }

  public func setTopPic(_ id: String, selected: Bool) throws {
    _ = try currentURL(id)
    guard var record = state.records[id] else { return }
    let original = record
    record.topPic = selected
    state.records[id] = record
    do { try save() } catch {
      state.records[id] = original
      throw error
    }
  }

  /// Preflight the entire selection, then move without overwriting. Same-volume
  /// moves retain filesystem identity even if the app exits before state is saved.
  public func move(_ ids: [String], to itemID: String) throws {
    try movePhotos(ids.map { ($0, itemID) }, undoing: false)
  }

  public func undoMove() throws {
    guard let entries = state.moves?.last else { return }
    for entry in entries {
      _ = try currentURL(entry.photoID)
      guard let photo = visible[entry.photoID], photo.itemID == entry.destinationItemID,
        photo.signature == entry.signature
      else {
        throw PreparationError.invalid(
          "A moved photo has changed or moved again. Undo will not alter it.")
      }
    }
    try movePhotos(entries.map { ($0.photoID, $0.originalItemID) }, undoing: true)
  }

  private func movePhotos(_ destinations: [(id: String, itemID: String)], undoing: Bool) throws {
    guard !destinations.isEmpty, Set(destinations.map(\.id)).count == destinations.count else {
      throw PreparationError.invalid("Choose photos and a prepared destination folder.")
    }
    var targets = Set<String>()
    let moves = try destinations.map {
      id, itemID -> (id: String, itemID: String, source: URL, target: URL, path: String) in
      guard let folder = folders.first(where: { $0.itemUuid == itemID }) else {
        throw PreparationError.invalid("The destination folder is no longer available.")
      }
      let directory = try safeURL(folder.relativePath, directory: true)
      let device =
        try FileManager.default.attributesOfItem(atPath: directory.path)[.systemNumber] as? NSNumber
      let source = try currentURL(id)
      guard let record = state.records[id], !record.uploaded,
        !lockedItemIDs.contains(record.itemID), !lockedItemIDs.contains(itemID)
      else {
        throw PreparationError.invalid(
          "Uploaded photos must be reassigned in the management portal.")
      }
      guard record.itemID != itemID else {
        throw PreparationError.invalid("These photos are already in that folder.")
      }
      let sourceDevice =
        try FileManager.default.attributesOfItem(atPath: source.path)[.systemNumber] as? NSNumber
      guard device != nil, device == sourceDevice else {
        throw PreparationError.invalid("Photo moves must stay on the same disk.")
      }
      let path = folder.relativePath + "/" + source.lastPathComponent
      let target = try safeDestination(path)
      guard targets.insert(path.lowercased()).inserted,
        !FileManager.default.fileExists(atPath: target.path),
        (try? target.resourceValues(forKeys: [.isSymbolicLinkKey])) == nil
      else {
        throw PreparationError.invalid(
          "A photo named \(source.lastPathComponent) already exists in the destination. Nothing was moved."
        )
      }
      return (id, itemID, source, target, path)
    }
    let previous = state
    var completed: [(id: String, itemID: String, source: URL, target: URL, path: String)] = []
    do {
      for move in moves {
        _ = try currentURL(move.id)
        _ = try safeDestination(move.path)
        try FileManager.default.moveItem(at: move.source, to: move.target)
        completed.append(move)
        state.records[move.id]?.relativePath = move.path
        state.records[move.id]?.itemID = move.itemID
      }
      if undoing {
        state.moves?.removeLast()
      } else {
        let entries = moves.map { move in
          MoveRecord(
            photoID: move.id, originalItemID: previous.records[move.id]!.itemID,
            destinationItemID: move.itemID, signature: visible[move.id]!.signature)
        }
        if state.moves == nil { state.moves = [] }
        state.moves?.append(entries)
      }
      for move in moves { state.queued?[move.id] = nil }
      try save()
    } catch {
      state = previous
      var rollbackFailed = false
      for move in completed.reversed() {
        do { try FileManager.default.moveItem(at: move.target, to: move.source) } catch {
          rollbackFailed = true
        }
      }
      if rollbackFailed {
        throw PreparationError.invalid(
          "Some photos moved before an error. Refresh to see their current folders; no existing files were overwritten."
        )
      }
      throw error
    }
    for move in moves {
      // The validated file is unchanged, so show it immediately in its new folder.
      observations[move.path] = observations[(previous.records[move.id]?.relativePath ?? "")]
      validJPEGs[move.path] = visible[move.id]?.signature
      visible[move.id] = nil
    }
  }

  public func trash(_ id: String) throws {
    guard let itemID = state.records[id]?.itemID, !lockedItemIDs.contains(itemID) else {
      throw PreparationError.invalid("Uploaded albums cannot be culled in the uploader.")
    }
    let source = try currentURL(id)
    guard let record = state.records[id], !record.uploaded else {
      throw PreparationError.invalid("Uploaded photos cannot be culled in the uploader.")
    }
    var result: NSURL?
    try FileManager.default.trashItem(at: source, resultingItemURL: &result)
    guard let trashed = result as URL? else {
      throw PreparationError.invalid(
        "The photo is in macOS Trash. Restore it from Finder if needed.")
    }
    let entry = TrashRecord(
      photoID: id, originalPath: record.relativePath, trashPath: trashed.path,
      identity: try fileInfo(trashed).identity)
    state.trash.append(entry)
    do { try save() } catch {
      state.trash.removeLast()
      // Return our own just-trashed file if persistence failed; never overwrite.
      do { try FileManager.default.moveItem(at: trashed, to: source) } catch {
        throw PreparationError.invalid(
          "The photo is in macOS Trash, but undo could not be saved. Restore it using Finder.")
      }
      throw error
    }
    visible[id] = nil
  }

  public func undoTrash() throws {
    guard let entry = state.trash.last else { return }
    let source = URL(fileURLWithPath: entry.trashPath)
    let trashDirectory = try FileManager.default.url(
      for: .trashDirectory, in: .userDomainMask, appropriateFor: root, create: false)
    guard
      source.deletingLastPathComponent().resolvingSymlinksInPath()
        == trashDirectory.resolvingSymlinksInPath(),
      try fileInfo(source).identity == entry.identity
    else {
      throw PreparationError.invalid(
        "That photo is no longer in its original Trash location. Restore it using Finder if available."
      )
    }
    let restorePath: String
    if let record = state.records[entry.photoID],
      let folder = folders.first(where: {
        $0.itemUuid == (itemAliases[record.itemID] ?? record.itemID)
      })
    {
      restorePath = folder.relativePath + "/" + (entry.originalPath as NSString).lastPathComponent
    } else {
      restorePath = entry.originalPath
    }
    let target = try safeDestination(restorePath)
    guard !FileManager.default.fileExists(atPath: target.path) else {
      throw PreparationError.invalid(
        "A photo already uses the original filename. Undo will not overwrite it.")
    }
    try FileManager.default.moveItem(at: source, to: target)
    let previous = state.records[entry.photoID]
    state.records[entry.photoID]?.identity = try fileInfo(target).identity
    state.trash.removeLast()
    do { try save() } catch {
      state.records[entry.photoID] = previous
      state.trash.append(entry)
      do { try FileManager.default.moveItem(at: target, to: source) } catch {
        throw PreparationError.invalid(
          "The photo was restored, but review state could not be saved. Reopen the event to refresh."
        )
      }
      throw error
    }
    observations[entry.originalPath] = nil
  }

  public func preview(_ id: String, maxPixelSize: Int = 2400) throws -> Data {
    let url = try currentURL(id)
    guard let source = CGImageSourceCreateWithURL(url as CFURL, nil),
      let image = CGImageSourceCreateThumbnailAtIndex(
        source, 0,
        [
          kCGImageSourceCreateThumbnailFromImageAlways: true,
          kCGImageSourceCreateThumbnailWithTransform: true,
          kCGImageSourceThumbnailMaxPixelSize: min(2400, max(64, maxPixelSize)),
        ] as CFDictionary)
    else { throw PreparationError.invalid("This JPEG could not be displayed.") }
    _ = try currentURL(id)
    let data = NSMutableData()
    guard let output = CGImageDestinationCreateWithData(data, "public.jpeg" as CFString, 1, nil)
    else {
      throw PreparationError.invalid("The photo preview could not be created.")
    }
    CGImageDestinationAddImage(output, image, nil)
    guard CGImageDestinationFinalize(output) else {
      throw PreparationError.invalid("The photo preview could not be created.")
    }
    return data as Data
  }

  private func currentURL(_ id: String) throws -> URL {
    guard !layoutBlocked else {
      throw PreparationError.invalid("Reopen this event to finish the folder change.")
    }
    guard let photo = visible[id], let record = state.records[id] else {
      throw PreparationError.invalid("The photo is no longer available. Refresh the folder.")
    }
    let url = try safeURL(photo.relativePath)
    let info = try fileInfo(url)
    guard info.identity == record.identity, info.signature == photo.signature else {
      throw PreparationError.invalid("This photo is still changing. Wait for capture to finish.")
    }
    return url
  }

  private func safeDestination(_ path: String) throws -> URL {
    guard
      folders.contains(where: {
        path.hasPrefix($0.relativePath + "/")
          && String(path.dropFirst($0.relativePath.count + 1)).contains("/") == false
      })
    else {
      throw PreparationError.invalid("The photo is outside the prepared folders.")
    }
    let parent = (path as NSString).deletingLastPathComponent
    _ = try safeURL(parent, directory: true)
    guard !(path as NSString).lastPathComponent.isEmpty,
      ![".", ".."].contains((path as NSString).lastPathComponent)
    else {
      throw PreparationError.invalid("The photo path is invalid.")
    }
    return root.appendingPathComponent(path)
  }

  private func safeURL(_ path: String, directory: Bool = false) throws -> URL {
    guard !path.hasPrefix("/"),
      !path.split(separator: "/").contains(where: { $0 == ".." || $0 == "." })
    else {
      throw PreparationError.invalid("The photo path is invalid.")
    }
    var url = root
    for component in [""] + path.split(separator: "/").map(String.init) {
      if !component.isEmpty { url.appendPathComponent(component) }
      let values = try url.resourceValues(forKeys: [.isSymbolicLinkKey])
      guard values.isSymbolicLink != true else {
        throw PreparationError.invalid("Symbolic links are not supported in photo folders.")
      }
    }
    let values = try url.resourceValues(forKeys: [.isRegularFileKey, .isDirectoryKey])
    guard directory ? values.isDirectory == true : values.isRegularFile == true else {
      throw PreparationError.invalid("A prepared photo folder or file is missing.")
    }
    return url
  }

  private func fileInfo(_ url: URL) throws -> (identity: String, signature: String, modified: Date)
  {
    let info = try FileManager.default.attributesOfItem(atPath: url.path)
    guard info[.type] as? FileAttributeType == .typeRegular,
      let device = info[.systemNumber] as? NSNumber,
      let inode = info[.systemFileNumber] as? NSNumber,
      let created = info[.creationDate] as? Date, let modified = info[.modificationDate] as? Date,
      let size = info[.size] as? NSNumber
    else { throw PreparationError.invalid("Unable to read photo information.") }
    let identity = "\(device):\(inode):\(created.timeIntervalSince1970)"
    return (identity, "\(identity):\(size):\(modified.timeIntervalSince1970)", modified)
  }

  private static func completeJPEG(_ url: URL) -> Bool {
    guard let handle = try? FileHandle(forReadingFrom: url) else { return false }
    defer { try? handle.close() }
    guard let size = try? handle.seekToEnd(), size >= 4,
      (try? handle.seek(toOffset: size - 2)) != nil,
      (try? handle.read(upToCount: 2)) == Data([0xff, 0xd9]),
      let source = CGImageSourceCreateWithURL(url as CFURL, nil),
      CGImageSourceGetType(source) as String? == "public.jpeg",
      CGImageSourceGetStatus(source) == .statusComplete,
      CGImageSourceGetStatusAtIndex(source, 0) == .statusComplete
    else { return false }
    return true
  }

  private func save() throws {
    // Revalidate the containing root and refuse a substituted review-state symlink.
    _ = try safeURL("", directory: true)
    if FileManager.default.fileExists(atPath: stateURL.path),
      try stateURL.resourceValues(forKeys: [.isSymbolicLinkKey]).isSymbolicLink == true
    {
      throw PreparationError.invalid("The photo-review file was replaced by a symbolic link.")
    }
    try JSONEncoder().encode(state).write(to: stateURL, options: .atomic)
  }
}
