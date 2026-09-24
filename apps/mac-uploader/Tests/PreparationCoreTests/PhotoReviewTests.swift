import CoreGraphics
import CryptoKit
import ImageIO
import PhotoReviewCore
import XCTest

@testable import DanceProUploader
@testable import PreparationCore

final class PhotoReviewTests: XCTestCase {
  private func fixture() throws -> (URL, PreparedEvent) {
    let destination = FileManager.default.temporaryDirectory.appendingPathComponent(
      "dancepro-review-test-" + UUID().uuidString)
    try FileManager.default.createDirectory(at: destination, withIntermediateDirectories: false)
    addTeardownBlock { try FileManager.default.removeItem(at: destination) }
    let programme = Programme(
      schemaVersion: 1, eventUuid: UUID().uuidString, eventName: "Review test",
      eventType: "concert", programmeUuid: UUID().uuidString, revisionUuid: UUID().uuidString,
      version: 1,
      nodes: [
        ProgrammeNode(
          uuid: UUID().uuidString, kind: "item", title: "Ballet", folderNumber: "01",
          folderTitle: "Ballet"),
        ProgrammeNode(
          uuid: UUID().uuidString, kind: "item", title: "Jazz", folderNumber: "02",
          folderTitle: "Jazz"),
      ])
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "test", destination: destination)
    let manifest = try ProgrammeCoding.decoder().decode(
      PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    return (root, manifest)
  }
  private func jpeg(_ url: URL) throws {
    let context = CGContext(
      data: nil, width: 64, height: 48, bitsPerComponent: 8, bytesPerRow: 256,
      space: CGColorSpaceCreateDeviceRGB(), bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
    context.setFillColor(CGColor(red: 0.2, green: 0.5, blue: 0.8, alpha: 1))
    context.fill(CGRect(x: 0, y: 0, width: 64, height: 48))
    let output = CGImageDestinationCreateWithURL(url as CFURL, "public.jpeg" as CFString, 1, nil)!
    CGImageDestinationAddImage(output, context.makeImage()!, nil)
    XCTAssertTrue(CGImageDestinationFinalize(output))
  }
  @MainActor
  func testCaptureFolderChangesPreservePhotoIdentityAndDiscoverAdditions() async throws {
    let (temporaryRoot, original) = try fixture()
    var programme = original.programme
    programme.eventType = "competition"
    programme.eventCode = "EDC926"
    let day = ProgrammeNode(uuid: UUID().uuidString, kind: "day", title: "Friday", date: "2026-09-04")
    let section = ProgrammeNode(uuid: UUID().uuidString, parentUuid: day.uuid, kind: "section", title: "Jazz", number: "07")
    let item = ProgrammeNode(uuid: UUID().uuidString, parentUuid: section.uuid, kind: "item", title: "Jessica Smith", number: "03")
    programme.nodes = [day, section, item]
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil, server: "test", destination: temporaryRoot.deletingLastPathComponent())
    let manifest = try ProgrammeCoding.decoder().decode(PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    let path = root.appendingPathComponent("07 Jazz/03 Jessica Smith/Photo.jpg")
    try jpeg(path)
    let bytes = try Data(contentsOf: path)
    let model = try PhotoReviewModel(local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    let id = try XCTUnwrap(model.photos.first?.id)
    await model.toggleTopPic()
    try FileManager.default.moveItem(at: path.deletingLastPathComponent(), to: path.deletingLastPathComponent().deletingLastPathComponent().appendingPathComponent("03 Jess Smith"))
    let extra = root.appendingPathComponent("08 Tap/01 Alex")
    try FileManager.default.createDirectory(at: extra, withIntermediateDirectories: true)
    try jpeg(extra.appendingPathComponent("New.jpg"))
    await model.refresh()
    XCTAssertEqual(model.photos.count, 2)
    XCTAssertEqual(model.photos.first { $0.id == id }?.itemID, item.uuid)
    XCTAssertTrue(model.photos.first { $0.id == id }?.isTopPic == true)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("07 Jazz/03 Jess Smith/Photo.jpg")), bytes)
    XCTAssertEqual(model.local.manifest.folders.count, 2)
    XCTAssertEqual(model.competitionFolders?.observed.first { $0.nodeUuid == item.uuid }?.title, "Jess Smith")
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertFalse(model.isUploading)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    _ = try await library.scan()
    try await library.markReady(itemIDs: [item.uuid])
    let queued = try await library.queuedUploadPhotos()
    let uploaded = try XCTUnwrap(queued.first)
    try await library.acknowledgeUpload(uploaded, serverFileUuid: UUID().uuidString)
    try FileManager.default.moveItem(at: root.appendingPathComponent("07 Jazz/03 Jess Smith"), to: root.appendingPathComponent("07 Jazz/05 Jess Smith"))
    let checked = try await library.scan()
    XCTAssertEqual(checked.photos.count, 1) // Other competitors can still upload.
    XCTAssertEqual(checked.pending.values.reduce(0, +), 1)
    XCTAssertTrue(checked.competition?.issues.contains { $0.contains("Uploaded photo moved") } == true)
    XCTAssertTrue(checked.lockedItemIDs.contains(item.uuid))
  }

  @MainActor
  func testDeleteInTopPicsOnlyRemovesSelectionButCompetitorDeleteUsesTrash() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let original = try Data(contentsOf: file)
    let model = try PhotoReviewModel(
      local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    await model.toggleTopPic()
    model.selectFolder("top-pics")
    XCTAssertEqual(model.visiblePhotos.count, 1)
    await model.trash()
    XCTAssertEqual(model.visiblePhotos.count, 0)
    XCTAssertFalse(model.canUndo)
    XCTAssertEqual(try Data(contentsOf: file), original)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let saved = try await reopened.scan()
    XCTAssertEqual(saved.photos.count, 1)
    XCTAssertFalse(saved.photos[0].isTopPic)
    model.selectFolder(manifest.folders[0].itemUuid)
    XCTAssertEqual(model.visiblePhotos.count, 1)
    await model.trash()
    XCTAssertFalse(FileManager.default.fileExists(atPath: file.path))
    XCTAssertTrue(model.canUndo)
    await model.undo()
    XCTAssertEqual(try Data(contentsOf: file), original)
  }

  @MainActor
  func testMoveSelectionPersistsIdentityTopPicsAndRejectsForeignDrag() async throws {
    let (root, manifest) = try fixture()
    for name in ["One.jpg", "Two.jpg"] {
      try jpeg(root.appendingPathComponent("01 Ballet/" + name))
    }
    let original = try Data(contentsOf: root.appendingPathComponent("01 Ballet/One.jpg"))
    let model = try PhotoReviewModel(
      local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    let ids = model.photos.map(\.id)
    await model.toggleTopPic()
    let starredID = model.selected!.id
    model.movingPhotos = true
    for id in ids { model.toggleMoveSelection(id) }
    XCTAssertEqual(model.moveSelection.count, 2)
    XCTAssertFalse(
      model.acceptMove(["foreign-session\n" + ids[0]], to: manifest.folders[1].itemUuid))
    XCTAssertFalse(model.acceptMove([model.dragPayload(for: ids[0])], to: "top-pics"))
    await model.movePhotos(ids, to: manifest.folders[1].itemUuid)
    XCTAssertNil(model.error)
    XCTAssertTrue(model.visiblePhotos.isEmpty)
    XCTAssertTrue(model.moveSelection.isEmpty)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let snapshot = try await reopened.scan()
    XCTAssertEqual(Set(snapshot.photos.map(\.id)), Set(ids))
    XCTAssertTrue(snapshot.photos.allSatisfy { $0.itemID == manifest.folders[1].itemUuid })
    XCTAssertTrue(snapshot.photos.first { $0.id == starredID }!.isTopPic)
    XCTAssertFalse(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("01 Ballet/One.jpg").path))
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("02 Jazz/One.jpg")), original)
  }

  func testMoveCollisionPreflightsEntireBatchWithoutOverwrite() async throws {
    let (root, manifest) = try fixture()
    for path in ["01 Ballet/One.jpg", "01 Ballet/Two.jpg", "02 Jazz/Two.jpg"] {
      try jpeg(root.appendingPathComponent(path))
    }
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photos = try await library.scan().photos.filter {
      $0.itemID == manifest.folders[0].itemUuid
    }
    let original = try Data(contentsOf: root.appendingPathComponent("02 Jazz/Two.jpg"))
    do {
      try await library.move(photos.map(\.id), to: manifest.folders[1].itemUuid)
      XCTFail("A conflicting batch moved")
    } catch {}
    for photo in photos {
      XCTAssertTrue(
        FileManager.default.fileExists(atPath: root.appendingPathComponent(photo.relativePath).path)
      )
    }
    XCTAssertFalse(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("02 Jazz/One.jpg").path))
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("02 Jazz/Two.jpg")), original)
  }

  func testMoveRollsBackWhenReviewStateCannotBeSaved() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    let stateURL = root.appendingPathComponent("photo-review.json")
    try FileManager.default.removeItem(at: stateURL)
    try FileManager.default.createDirectory(at: stateURL, withIntermediateDirectories: false)
    do {
      try await library.move([photo.id], to: manifest.folders[1].itemUuid)
      XCTFail("Move succeeded despite unsaved state")
    } catch {}
    XCTAssertTrue(FileManager.default.fileExists(atPath: file.path))
    XCTAssertFalse(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("02 Jazz/Photo.jpg").path))
  }

  func testMoveRejectsInvalidDestinationAndStaleSelection() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    for target in ["../outside", "top-pics", manifest.folders[0].itemUuid] {
      do {
        try await library.move([photo.id], to: target)
        XCTFail("Invalid target accepted")
      } catch {}
    }
    try Data("changing capture".utf8).write(to: file)
    do {
      try await library.move([photo.id], to: manifest.folders[1].itemUuid)
      XCTFail("Stale photo moved")
    } catch {}
    XCTAssertTrue(FileManager.default.fileExists(atPath: file.path))
  }

  func testOfflineQueuePersistsAndDoesNotApproveLaterOrChangedPhotos() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/One.jpg")
    try jpeg(file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let first = try await library.scan().photos[0]
    try await library.markReady(itemIDs: [manifest.folders[0].itemUuid])
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    var snapshot = try await reopened.scan()
    XCTAssertEqual(snapshot.queuedIDs, [first.id])
    try jpeg(root.appendingPathComponent("01 Ballet/Two.jpg"))
    snapshot = try await reopened.scan()
    XCTAssertEqual(snapshot.photos.count, 2)
    XCTAssertEqual(snapshot.queuedIDs, [first.id])
    try FileManager.default.setAttributes(
      [.modificationDate: Date().addingTimeInterval(-30)], ofItemAtPath: file.path)
    snapshot = try await reopened.scan()
    XCTAssertTrue(snapshot.queuedIDs.isEmpty)
    try await reopened.markReady(itemIDs: [manifest.folders[0].itemUuid])
    snapshot = try await reopened.scan()
    XCTAssertEqual(snapshot.queuedIDs.count, 2)
    try await reopened.removeFromQueue(snapshot.queuedIDs)
    let after = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let cleared = try await after.scan()
    XCTAssertTrue(cleared.queuedIDs.isEmpty)
  }

  func testQueueSurvivesQuietIntervalOnReopenButMoveRevokesReadiness() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    try await library.markReady(itemIDs: [manifest.folders[0].itemUuid])
    let reopened = try PhotoLibrary(root: root, manifest: manifest)
    let now = Date().addingTimeInterval(5)
    _ = try await reopened.scan(now: now)
    let snapshot = try await reopened.scan(now: now.addingTimeInterval(3))
    XCTAssertEqual(snapshot.queuedIDs, [photo.id])
    try await reopened.move([photo.id], to: manifest.folders[1].itemUuid)
    let moved = try await reopened.scan(now: now.addingTimeInterval(6))
    XCTAssertTrue(moved.queuedIDs.isEmpty)
  }

  func testConcertEditsPreservePhotoIdentityTopPicsAndTrashUndo() async throws {
    let (root, manifest) = try fixture()
    let approved = try Data(contentsOf: root.appendingPathComponent("programme.json"))
    try jpeg(root.appendingPathComponent("01 Ballet/Keep.jpg"))
    try jpeg(root.appendingPathComponent("01 Ballet/Trash.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photos = try await library.scan().photos
    let keep = photos.first { $0.name == "Keep.jpg" }!
    let trash = photos.first { $0.name == "Trash.jpg" }!
    try await library.toggleTopPic(keep.id)
    try await library.trash(trash.id)
    var draft = manifest.programme
    draft.nodes[0].title = "It's / New: Ballet!"
    draft.nodes.reverse()
    draft.nodes.append(ConcertLayout.newItem(title: "Speech"))
    let updated = try await library.changeConcertLayout(draft)
    XCTAssertEqual(
      updated.folders.map(\.relativePath), ["01 Jazz", "02 Its New Ballet", "03 Speech"])
    try await library.undoTrash()
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let snapshot = try await reopened.scan()
    XCTAssertEqual(Set(snapshot.photos.map(\.id)), Set(photos.map(\.id)))
    XCTAssertTrue(snapshot.photos.first { $0.id == keep.id }!.isTopPic)
    XCTAssertTrue(snapshot.photos.allSatisfy { $0.relativePath.hasPrefix("02 Its New Ballet/") })
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("programme.json")), approved)
    let restored = try ConcertLayout.load(root: root, base: manifest)
    XCTAssertEqual(restored.programme.nodes[1].title, "It's / New: Ballet!")
    XCTAssertEqual(restored.folders.count, 3)
  }

  func testConcertEditRejectsCaptureStillWriting() async throws {
    let (root, manifest) = try fixture()
    try Data([0xff, 0xd8]).write(to: root.appendingPathComponent("01 Ballet/Partial.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    var draft = manifest.programme
    draft.nodes[0].title = "Changed"
    do {
      _ = try await library.changeConcertLayout(draft)
      XCTFail("Changed during capture")
    } catch {}
    XCTAssertFalse(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent("local-programme.json").path))
  }

  func testMoveUndoSurvivesReopenAndRestoresMultipleOriginalFolders() async throws {
    let (root, manifest) = try fixture()
    var draft = manifest.programme
    draft.nodes.append(ConcertLayout.newItem(title: "Combined"))
    let updated = try ConcertLayout.apply(draft, root: root, base: manifest)
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    try jpeg(root.appendingPathComponent("02 Jazz/Two.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let originals = try await library.scan().photos
    try await library.toggleTopPic(originals[0].id)
    try await library.move(originals.map(\.id), to: updated.folders[2].itemUuid)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let moved = try await reopened.scan()
    XCTAssertTrue(moved.canUndoMove)
    try await reopened.undoMove()
    let restored = try await reopened.scan()
    XCTAssertFalse(restored.canUndoMove)
    XCTAssertEqual(restored.photos.map(\.id), originals.map(\.id))
    XCTAssertEqual(restored.photos.map(\.relativePath), originals.map(\.relativePath))
    XCTAssertTrue(restored.photos[0].isTopPic)
  }

  func testMoveUndoCollisionLeavesEntireBatchAndHistoryIntact() async throws {
    let (root, manifest) = try fixture()
    for name in ["One.jpg", "Two.jpg"] {
      try jpeg(root.appendingPathComponent("01 Ballet/" + name))
    }
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photos = try await library.scan().photos
    try await library.move(photos.map(\.id), to: manifest.folders[1].itemUuid)
    _ = try await library.scan()
    let conflict = root.appendingPathComponent("01 Ballet/Two.jpg")
    try Data("new capture".utf8).write(to: conflict)
    do {
      try await library.undoMove()
      XCTFail("Undo overwrote a new file")
    } catch {}
    XCTAssertEqual(try String(contentsOf: conflict, encoding: .utf8), "new capture")
    for photo in photos {
      XCTAssertTrue(
        FileManager.default.fileExists(
          atPath: root.appendingPathComponent("02 Jazz/" + photo.name).path))
    }
    let blocked = try await library.scan()
    XCTAssertTrue(blocked.canUndoMove)
    try FileManager.default.removeItem(at: conflict)
    try await library.undoMove()
    let restored = try await library.scan()
    XCTAssertFalse(restored.canUndoMove)
    XCTAssertEqual(restored.photos.map(\.relativePath), photos.map(\.relativePath))
  }

  @MainActor
  func testMoveNoticeUndoTargetsMoveRatherThanTrash() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let model = try PhotoReviewModel(
      local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    let id = model.photos[0].id
    await model.movePhotos([id], to: manifest.folders[1].itemUuid)
    XCTAssertEqual(model.noticeUndo, .move)
    XCTAssertTrue(model.canUndoMove)
    XCTAssertFalse(model.canUndo)
    await model.undoNotice()
    XCTAssertNil(model.error)
    XCTAssertNil(model.noticeUndo)
    XCTAssertFalse(model.canUndoMove)
    XCTAssertEqual(model.photos[0].relativePath, "01 Ballet/One.jpg")
  }

  func testSplitCreatesFollowingItemMovesOnlySelectionAndPreservesUndo() async throws {
    let (root, manifest) = try fixture()
    for name in ["One.jpg", "Two.jpg"] {
      try jpeg(root.appendingPathComponent("01 Ballet/" + name))
    }
    let original = try Data(contentsOf: root.appendingPathComponent("01 Ballet/One.jpg"))
    let now = Date()
    for name in ["One.jpg", "Two.jpg"] {
      try FileManager.default.setAttributes(
        [.modificationDate: now.addingTimeInterval(-10)],
        ofItemAtPath: root.appendingPathComponent("01 Ballet/" + name).path)
    }
    let working = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 2)
    _ = try await working.scan(now: now.addingTimeInterval(-5))
    let photos = try await working.scan(now: now).photos
    try await working.toggleTopPic(photos[0].id)
    try await working.markReady(itemIDs: [manifest.folders[0].itemUuid])
    let result = try await working.splitPhotos(
      [photos[0].id], from: manifest.folders[0].itemUuid, title: "Speech / It's New!")
    XCTAssertNil(result.moveError)
    XCTAssertEqual(
      result.manifest.folders.map(\.relativePath), ["01 Ballet", "02 Speech Its New", "03 Jazz"])
    XCTAssertEqual(result.manifest.programme.nodes[1].title, "Speech / It's New!")
    XCTAssertEqual(result.manifest.programme.nodes[0].uuid, manifest.programme.nodes[0].uuid)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let split = try await reopened.scan()
    let moved = split.photos.first { $0.id == photos[0].id }!
    XCTAssertEqual(moved.itemID, result.itemID)
    XCTAssertTrue(moved.isTopPic)
    XCTAssertEqual(
      split.photos.first { $0.id == photos[1].id }!.itemID, manifest.folders[0].itemUuid)
    XCTAssertFalse(split.queuedIDs.contains(moved.id))
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent(moved.relativePath)), original)
    try await reopened.undoMove()
    let restored = try await reopened.scan()
    XCTAssertTrue(restored.photos.allSatisfy { $0.itemID == manifest.folders[0].itemUuid })
    XCTAssertTrue(restored.photos.first { $0.id == moved.id }!.isTopPic)
  }

  func testSplitRejectsInvalidSelectionAndTitleBeforeCreatingFolders() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    for (ids, item, title) in [
      ([photo.id], manifest.folders[0].itemUuid, " /?! "),
      ([photo.id], manifest.folders[1].itemUuid, "New"),
      ([photo.id, photo.id], manifest.folders[0].itemUuid, "New"),
      ([String](), manifest.folders[0].itemUuid, "New"),
    ] {
      do {
        _ = try await library.splitPhotos(ids, from: item, title: title)
        XCTFail("Invalid split succeeded")
      } catch {}
    }
    XCTAssertFalse(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent("local-programme.json").path))
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent(photo.relativePath).path))
  }

  func testSplitReportsCommittedItemWhenMoveStateCannotBeSaved() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    let stateURL = root.appendingPathComponent("photo-review.json")
    try FileManager.default.removeItem(at: stateURL)
    try FileManager.default.createDirectory(at: stateURL, withIntermediateDirectories: false)
    let result = try await library.splitPhotos(
      [photo.id], from: manifest.folders[0].itemUuid, title: "New Item")
    XCTAssertNotNil(result.moveError)
    XCTAssertEqual(result.manifest.folders.count, 3)
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent(photo.relativePath).path))
    XCTAssertFalse(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent("02 New Item/One.jpg").path))
    XCTAssertEqual(try ConcertLayout.load(root: root, base: manifest).folders.count, 3)
  }

  @MainActor
  func testSplitSelectionOpensNewItemAndOffersMoveUndo() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let model = try PhotoReviewModel(
      local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    model.toggleMoveSelection(model.photos[0].id)
    let result = await model.splitSelection(title: "New Item")
    XCTAssertTrue(result)
    XCTAssertNil(model.error)
    XCTAssertEqual(model.folderTitle, "New Item")
    XCTAssertEqual(model.visiblePhotos.count, 1)
    XCTAssertEqual(model.noticeUndo, .move)
    XCTAssertTrue(model.moveSelection.isEmpty)
  }

  func testEditorCombinesMultipleItemsInOrderAndRenumbersOnSave() async throws {
    let (root, manifest) = try fixture()
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    var programme = manifest.programme
    programme.nodes.append(ConcertLayout.newItem(title: "Finale!"))
    programme.nodes.append(ConcertLayout.newItem(title: "Encore"))
    let expanded = try await library.changeConcertLayout(programme)
    for (index, folder) in expanded.folders.enumerated() {
      try jpeg(root.appendingPathComponent(folder.relativePath + "/Photo\(index).jpg"))
    }
    let before = try await library.scan()
    try await library.toggleTopPic(before.photos[0].id)
    let ids = Set([programme.nodes[2].uuid, programme.nodes[0].uuid, programme.nodes[1].uuid])
    let draft = try ConcertLayout.combining(
      ids, in: expanded.programme,
      existingIDs: Set(programme.nodes.map(\.uuid)))
    XCTAssertEqual(
      draft.nodes.filter(\.isActiveItem).map(\.title), ["Ballet Jazz Finale!", "Encore"])
    // Drafting must not change the folders or photos.
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("02 Jazz/Photo1.jpg").path)
    )
    let saved = try await library.saveConcertEdits(draft)
    XCTAssertEqual(saved.folders.map(\.relativePath), ["01 Ballet Jazz Finale", "02 Encore"])
    let after = try await library.scan()
    XCTAssertEqual(Set(before.photos.map(\.id)), Set(after.photos.map(\.id)))
    XCTAssertTrue(after.photos.first { $0.id == before.photos[0].id }!.isTopPic)
    XCTAssertEqual(after.photos.filter { $0.itemID == programme.nodes[0].uuid }.count, 3)
    XCTAssertEqual(after.photos.filter { $0.itemID == programme.nodes[3].uuid }.count, 1)
  }

  func testMultiCombineCollisionDoesNotPartiallyMoveEarlierItems() async throws {
    let (root, manifest) = try fixture()
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    var programme = manifest.programme
    programme.nodes.append(ConcertLayout.newItem(title: "Finale"))
    let expanded = try await library.changeConcertLayout(programme)
    try jpeg(root.appendingPathComponent("02 Jazz/Same.jpg"))
    try jpeg(root.appendingPathComponent("03 Finale/Same.jpg"))
    _ = try await library.scan()
    let ids = Set(programme.nodes.map(\.uuid))
    let draft = try ConcertLayout.combining(ids, in: expanded.programme, existingIDs: ids)
    do {
      _ = try await library.saveConcertEdits(draft)
      XCTFail("Combined colliding filenames")
    } catch {}
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("02 Jazz/Same.jpg").path))
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("03 Finale/Same.jpg").path)
    )
    XCTAssertEqual(try ConcertLayout.load(root: root, base: manifest).folders.count, 3)
  }

  @MainActor
  private func uploadFixture() async throws -> (PhotoReviewModel, APIClient, [String: Data]) {
    let (root, original) = try fixture()
    let manifest = PreparedEvent(
      server: "https://uploads.test", programme: original.programme,
      folders: original.folders, preparedAt: original.preparedAt, hasPdf: false)
    try jpeg(root.appendingPathComponent("01 Ballet/Upload.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    _ = try await library.scan()
    try await library.markReady(itemIDs: [manifest.folders[0].itemUuid])
    let descriptors = try await library.queuedUploadPhotos()
    let photo = try XCTUnwrap(descriptors.first)
    let model = try PhotoReviewModel(
      local: LocalEvent(url: root, manifest: manifest), quietInterval: 0)
    await model.refresh()
    let person = EventPhotographer(
      id: "test-person", crewUuid: UUID().uuidString, roleUuid: UUID().uuidString,
      name: "Alex", roleName: "Photographer 1", shifts: [])
    model.photographerSelection = PhotographerSelection(
      server: manifest.server,
      roster: PhotographerRoster(
        eventUuid: manifest.programme.eventUuid, checkedAt: "2026-09-22T00:00:00Z",
        hasShifts: true, photographers: [person], unfilled: []), selectedId: person.id)
    let plan = UUID().uuidString
    let contribution = UUID().uuidString
    let file = UUID().uuidString
    let base = "/api/staff/events/" + manifest.programme.eventUuid + "/programme"
    func envelope(_ data: [String: Any]) throws -> Data {
      try JSONSerialization.data(withJSONObject: ["success": true, "data": data])
    }
    let identity: [String: Any] = [
      "event_uuid": manifest.programme.eventUuid, "plan_uuid": plan,
      "crew_uuid": person.crewUuid!, "role_uuid": person.roleUuid,
    ]
    let fileFields: [String: Any] = [
      "uuid": file, "photo_uuid": photo.photoUuid, "item_uuid": photo.itemUuid,
      "contribution_uuid": contribution, "sha256": photo.sha256, "byte_count": photo.byteCount,
    ]
    let responses = [
      base + "/photo-progress": try envelope([
        "event_uuid": manifest.programme.eventUuid, "plan_uuid": plan,
        "checked_at": "2026-09-22T00:00:00Z", "plan_current": true, "visibility": "private",
        "photographers": [[
          "id": person.id, "crew_uuid": person.crewUuid!, "role_uuid": person.roleUuid,
          "name": person.name, "role_name": person.roleName, "status": "receiving",
          "expected_item_count": 2, "complete_item_count": 0,
          "verified_file_count": 1, "pending_file_count": 0,
        ]], "unfilled": [],
      ]),
      base + "/photo-completion": try envelope(
        identity.merging([
          "status": "complete", "contribution_count": 2, "verified_file_count": 1,
          "visibility": "private", "completed_at": "2026-09-22T00:00:00Z",
        ]) { _, new in new }),
      base + "/sync": try envelope([
        "uuid": plan, "event_uuid": manifest.programme.eventUuid,
        "revision_uuid": manifest.programme.revisionUuid,
        "digest": String(repeating: "a", count: 64),
      ]),
      base + "/photo-contributions": try envelope(
        identity.merging([
          "contributions": [
            [
              "uuid": contribution,
              "item_uuid": photo.itemUuid, "status": "receiving",
            ]
          ]
        ]) { _, new in new }),
      base + "/photo-files": try envelope(
        identity.merging(["files": [fileFields.merging(["status": "verified"]) { _, new in new }]])
        { _, new in new }),
      base + "/photo-files/" + file + "/content": try envelope(
        fileFields.merging([
          "event_uuid": manifest.programme.eventUuid, "plan_uuid": plan,
          "gallery_uuid": UUID().uuidString,
          "status": "verified", "visibility": "private",
        ]) { _, new in new }),
    ]
    let config = URLSessionConfiguration.ephemeral
    config.protocolClasses = [PhotoUploadProtocol.self]
    let client = try APIClient(server: manifest.server, token: "test-only", configuration: config)
    return (model, client, responses)
  }

  @MainActor
  private func awaitUpload(_ condition: () -> Bool) async throws {
    for _ in 0..<500 {
      if condition() { return }
      try await Task.sleep(for: .milliseconds(10))
    }
    XCTFail("Timed out waiting for the upload test")
  }

  @MainActor
  func testRoleChangeRequiresEmptyQueueAndVideoCannotUploadPhotos() async throws {
    let (model, client, responses) = try await uploadFixture()
    let original = try XCTUnwrap(model.photographerSelection?.selected)
    let video = EventPhotographer(id: UUID().uuidString, crewUuid: nil,
      roleUuid: UUID().uuidString, name: "V", roleName: "Videographer V", shifts: [], mediaType: "video")
    let roster = PhotographerRoster(eventUuid: model.local.manifest.programme.eventUuid,
      checkedAt: "2026-09-22T00:00:00Z", hasShifts: true, photographers: [original, video], unfilled: [])
    model.photographerSelection = PhotographerSelection(server: model.local.manifest.server,
      roster: roster, selectedId: original.id)
    model.selectPhotographer(video.id)
    XCTAssertEqual(model.photographerSelection?.selectedId, original.id)
    XCTAssertNotNil(model.photographerError)
    await model.unqueue(model.queuedIDs)
    model.selectPhotographer(video.id)
    XCTAssertEqual(model.photographerSelection?.selectedId, video.id)
    let reopened = try PhotoReviewModel(local: model.local, quietInterval: 0)
    XCTAssertEqual(reopened.photographerSelection?.selectedId, video.id)
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    XCTAssertFalse(model.isUploading)
    XCTAssertFalse(model.canFinishUploads)
    XCTAssertNotNil(model.error)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
  }

  @MainActor
  func testManualUploadAutomaticallyPreparesQueueAndPersistsVerifiedReceipt() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses)
    XCTAssertFalse(model.isUploading)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    XCTAssertNil(model.error)
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertTrue(model.photos[0].uploaded)
    XCTAssertEqual(PhotoUploadProtocol.state.paths().count, 4)
    let reopened = try PhotoReviewModel(local: model.local, quietInterval: 0)
    await reopened.refresh()
    XCTAssertTrue(reopened.photos[0].uploaded)
    XCTAssertFalse(reopened.isUploading)
    XCTAssertFalse(reopened.canRemoveSelected)
  }

  @MainActor
  func testFinishRequiresVerifiedPhotosAndPersistsUntilNewWorkArrives() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses)
    XCTAssertFalse(model.canFinishUploads)
    await model.finishUploads(client: client)
    XCTAssertNotNil(model.error)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    XCTAssertTrue(model.canFinishUploads)
    XCTAssertFalse(model.completionRecorded)
    await model.finishUploads(client: client)
    XCTAssertNil(model.error)
    XCTAssertTrue(model.completionRecorded)
    let reopened = try PhotoReviewModel(local: model.local, quietInterval: 0)
    reopened.photographerSelection = model.photographerSelection
    await reopened.refresh()
    XCTAssertTrue(reopened.completionRecorded)
    try jpeg(model.local.url.appendingPathComponent("02 Jazz/Later.jpg"))
    await reopened.refresh()
    XCTAssertFalse(reopened.completionRecorded)
    XCTAssertFalse(reopened.canFinishUploads)
    XCTAssertTrue(reopened.uploadStatus.contains("New photos"))
    reopened.selectPhotographer(nil)
    XCTAssertFalse(reopened.completionRecorded)
  }

  @MainActor
  func testServerProgressRefreshIsReadOnlyAndRejectsForeignResponses() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses)
    await model.refreshProgress(client: client)
    XCTAssertNotNil(model.progressError)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    PhotoUploadProtocol.state.configure(responses)
    await model.refreshProgress(client: client)
    XCTAssertNil(model.progressError)
    XCTAssertEqual(model.serverProgress?.photographers.first?.verifiedFileCount, 1)
    XCTAssertEqual(model.serverProgress?.photographers.first?.label, "In progress")
    XCTAssertEqual(PhotoUploadProtocol.state.paths().count, 1)
    XCTAssertTrue(PhotoUploadProtocol.state.paths()[0].hasSuffix("/photo-progress"))
    var wrong = responses
    let path = try XCTUnwrap(wrong.keys.first { $0.hasSuffix("/photo-progress") })
    var envelope = try XCTUnwrap(JSONSerialization.jsonObject(with: wrong[path]!) as? [String: Any])
    var data = try XCTUnwrap(envelope["data"] as? [String: Any])
    data["plan_uuid"] = UUID().uuidString
    envelope["data"] = data
    wrong[path] = try JSONSerialization.data(withJSONObject: envelope)
    PhotoUploadProtocol.state.configure(wrong)
    await model.refreshProgress(client: client)
    XCTAssertNil(model.serverProgress)
    XCTAssertNotNil(model.progressError)
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertTrue(model.photos[0].uploaded)
  }

  @MainActor
  private func sharedResponse(_ programme: Programme, plan: String = UUID().uuidString) throws -> Data {
    let snapshot = try JSONSerialization.jsonObject(with: ProgrammeCoding.encoder().encode(programme))
    return try JSONSerialization.data(withJSONObject: ["success": true, "data": [
      "event_uuid": programme.eventUuid, "revision_uuid": programme.revisionUuid,
      "plan": ["uuid": plan, "event_uuid": programme.eventUuid,
        "revision_uuid": programme.revisionUuid, "digest": String(repeating: "a", count: 64)],
      "programme": snapshot
    ]])
  }

  @MainActor
  func testSharedConcertTagConfirmsOnlyAfterDownloadedProgrammeIsApplied() async throws {
    let (model, client, original) = try await uploadFixture()
    let originalBytes = try Data(contentsOf: model.local.url.appendingPathComponent("01 Ballet/Upload.jpg"))
    var remote = model.local.manifest.programme
    remote.nodes[0].title = "New Ballet!"
    remote.nodes[0].folderTitle = "New Ballet"
    // The server canonicalises UUID case; local media identities must survive.
    remote.nodes = remote.nodes.map { value in var node = value; node.uuid = node.uuid.lowercased(); return node }
    var responses = original
    responses["/api/staff/events/" + remote.eventUuid + "/programme/shared"] = try sharedResponse(remote)
    PhotoUploadProtocol.state.configure(responses)
    await model.refreshSharedProgramme(client: client)
    XCTAssertEqual(model.programmeTag, "Update available")
    XCTAssertTrue(FileManager.default.fileExists(atPath: model.local.url.appendingPathComponent("01 Ballet/Upload.jpg").path))
    await model.loadSharedProgramme(client: client)
    XCTAssertNil(model.error)
    XCTAssertEqual(model.programmeTag, "Confirmed")
    XCTAssertEqual(model.local.manifest.programme.nodes[0].title, "New Ballet!")
    XCTAssertEqual(try Data(contentsOf: model.local.url.appendingPathComponent("01 New Ballet/Upload.jpg")), originalBytes)
    XCTAssertTrue(model.programmeSynced)
    XCTAssertFalse(model.isUploading)
    let saved = try ConcertLayout.load(root: model.local.url, base: model.local.manifest)
    XCTAssertEqual(saved.programme.nodes[0].title, "New Ballet!")
  }

  @MainActor
  func testLoadingSharedCombinationPreservesPhotosAndTopPicks() async throws {
    let (model, client, original) = try await uploadFixture()
    await model.toggleTopPic()
    let id = try XCTUnwrap(model.photos.first?.id)
    var remote = model.local.manifest.programme
    remote.nodes[0].mergedIntoUuid = remote.nodes[1].uuid
    remote.nodes[1].title = "Ballet Jazz"
    remote = try ConcertLayout.normalized(remote)
    var responses = original
    responses["/api/staff/events/" + remote.eventUuid + "/programme/shared"] = try sharedResponse(remote)
    PhotoUploadProtocol.state.configure(responses)
    await model.loadSharedProgramme(client: client)
    XCTAssertNil(model.error)
    XCTAssertEqual(model.programmeTag, "Confirmed")
    XCTAssertEqual(model.photos.count, 1)
    XCTAssertEqual(model.photos.first?.id, id)
    XCTAssertTrue(model.photos.first?.isTopPic == true)
    XCTAssertEqual(model.local.manifest.folders.count, 1)
  }

  @MainActor
  func testSharedProgrammeDoesNotDiscardAdditionalLocalItemsOrClaimOfflineConfirmation() async throws {
    let (model, client, original) = try await uploadFixture()
    let remote = model.local.manifest.programme
    var draft = remote
    draft.nodes.append(ConcertLayout.newItem(title: "Only on this Mac"))
    let saved = await model.saveConcertLayout(draft)
    XCTAssertTrue(saved)
    var responses = original
    let path = "/api/staff/events/" + remote.eventUuid + "/programme/shared"
    responses[path] = try sharedResponse(remote)
    PhotoUploadProtocol.state.configure(responses)
    await model.loadSharedProgramme(client: client)
    XCTAssertNotNil(model.error)
    XCTAssertEqual(model.local.manifest.folders.count, 3)
    XCTAssertEqual(model.photos.count, 1)
    responses[path] = Data("invalid".utf8)
    PhotoUploadProtocol.state.configure(responses)
    await model.refreshSharedProgramme(client: client)
    XCTAssertNotNil(model.sharedProgrammeError)
    XCTAssertNotEqual(model.programmeTag, "Confirmed")
  }

  @MainActor
  func testProgressRejectsCompleteStatusWithOutstandingFiles() async throws {
    let (model, client, original) = try await uploadFixture()
    var responses = original
    let path = try XCTUnwrap(responses.keys.first { $0.hasSuffix("/photo-progress") })
    var envelope = try XCTUnwrap(JSONSerialization.jsonObject(with: responses[path]!) as? [String: Any])
    var data = try XCTUnwrap(envelope["data"] as? [String: Any])
    var people = try XCTUnwrap(data["photographers"] as? [[String: Any]])
    people[0]["status"] = "complete"
    people[0]["complete_item_count"] = 2
    people[0]["pending_file_count"] = 1
    data["photographers"] = people
    envelope["data"] = data
    responses[path] = try JSONSerialization.data(withJSONObject: envelope)
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    await model.refreshProgress(client: client)
    XCTAssertNil(model.serverProgress)
    XCTAssertNotNil(model.progressError)
  }

  @MainActor
  func testProgressSeparatesWaiversFromVerifiedCompletion() async throws {
    let (model, client, original) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(original)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    let path = try XCTUnwrap(original.keys.first { $0.hasSuffix("/photo-progress") })
    for (status, complete, waived, valid) in [
      ("waived", 0, 2, true),
      ("complete_with_exceptions", 1, 1, true),
      ("complete_with_exceptions", 1, 2, false),
      ("complete", 0, 2, false)
    ] {
      var responses = original
      var envelope = try XCTUnwrap(JSONSerialization.jsonObject(with: responses[path]!) as? [String: Any])
      var data = try XCTUnwrap(envelope["data"] as? [String: Any])
      var roles = try XCTUnwrap(data["photographers"] as? [[String: Any]])
      roles[0]["status"] = status
      roles[0]["complete_item_count"] = complete
      roles[0]["waived_item_count"] = waived
      data["photographers"] = roles
      envelope["data"] = data
      responses[path] = try JSONSerialization.data(withJSONObject: envelope)
      PhotoUploadProtocol.state.configure(responses)
      await model.refreshProgress(client: client)
      XCTAssertEqual(model.serverProgress != nil, valid, status)
      if valid { XCTAssertEqual(model.serverProgress?.photographers.first?.waivedItemCount, waived) }
    }
  }

  @MainActor
  func testReadyPhotosRemovedFromQueueStillBlockFinish() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses)
    XCTAssertFalse(model.queuedIDs.isEmpty)
    await model.unqueue(model.queuedIDs)
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertEqual(model.photos.count, 1)
    XCTAssertFalse(model.photos[0].uploaded)
    XCTAssertFalse(model.canFinishUploads)
    await model.finishUploads(client: client)
    XCTAssertNotNil(model.error)
    XCTAssertFalse(model.completionRecorded)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
  }

  @MainActor
  func testIncompleteJpegBlocksFinishEvenWithAnEmptyQueue() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    try Data([0xff, 0xd8]).write(to: model.local.url.appendingPathComponent("02 Jazz/Copying.jpg"))
    await model.refresh()
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertFalse(model.pending.isEmpty)
    XCTAssertFalse(model.canFinishUploads)
    let before = PhotoUploadProtocol.state.paths().count
    await model.finishUploads(client: client)
    XCTAssertNotNil(model.error)
    XCTAssertEqual(PhotoUploadProtocol.state.paths().count, before)
  }

  @MainActor
  func testFinishRejectsReceiptForAnotherRole() async throws {
    let (model, client, original) = try await uploadFixture()
    var responses = original
    let path = try XCTUnwrap(responses.keys.first { $0.hasSuffix("/photo-completion") })
    var envelope = try XCTUnwrap(JSONSerialization.jsonObject(with: responses[path]!) as? [String: Any])
    var data = try XCTUnwrap(envelope["data"] as? [String: Any])
    data["role_uuid"] = UUID().uuidString
    envelope["data"] = data
    responses[path] = try JSONSerialization.data(withJSONObject: envelope)
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    await model.finishUploads(client: client)
    XCTAssertNotNil(model.error)
    XCTAssertFalse(model.completionRecorded)
    XCTAssertNil(ProgrammeSyncReceipt.read(root: model.local.url)?.photoCompletion)
  }

  @MainActor
  func testPauseKeepsQueueAndExplicitResumeFinishesLostResponseSafely() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses, hold: true)
    model.startUploads(client: client)
    try await awaitUpload {
      PhotoUploadProtocol.state.paths().contains { $0.hasSuffix("/content") }
    }
    model.pauseUploads()
    try await awaitUpload { !model.isUploading }
    XCTAssertTrue(model.uploadPaused)
    XCTAssertEqual(model.queuedIDs.count, 1)
    XCTAssertFalse(model.photos[0].uploaded)
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    XCTAssertTrue(model.queuedIDs.isEmpty)
    XCTAssertTrue(model.photos[0].uploaded)
  }

  @MainActor
  func testActiveUploadRetriesTemporaryFailureWithoutLosingQueue() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses, transientFailures: 1)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    XCTAssertNil(model.error)
    XCTAssertEqual(PhotoUploadProtocol.state.paths().filter { $0.hasSuffix("/content") }.count, 2)
    XCTAssertTrue(model.photos[0].uploaded)
  }

  @MainActor
  func testIncorrectServerReceiptLeavesPhotoQueuedAndUnverified() async throws {
    let (model, client, original) = try await uploadFixture()
    var responses = original
    let path = try XCTUnwrap(responses.keys.first { $0.hasSuffix("/content") })
    var json = try XCTUnwrap(JSONSerialization.jsonObject(with: responses[path]!) as? [String: Any])
    var data = try XCTUnwrap(json["data"] as? [String: Any])
    data["sha256"] = String(repeating: "0", count: 64)
    json["data"] = data
    responses[path] = try JSONSerialization.data(withJSONObject: json)
    PhotoUploadProtocol.state.configure(responses)
    model.startUploads(client: client)
    try await awaitUpload { !model.isUploading }
    XCTAssertNotNil(model.error)
    XCTAssertTrue(model.uploadPaused)
    XCTAssertEqual(model.queuedIDs.count, 1)
    XCTAssertFalse(model.photos[0].uploaded)
    XCTAssertTrue(model.lockedItemIDs.isEmpty)
  }

  @MainActor
  func testReadyCanAddNewPhotosWhileUploadIsActive() async throws {
    let (model, client, responses) = try await uploadFixture()
    PhotoUploadProtocol.state.configure(responses, hold: true)
    model.startUploads(client: client)
    try await awaitUpload {
      PhotoUploadProtocol.state.paths().contains { $0.hasSuffix("/content") }
    }
    try jpeg(model.local.url.appendingPathComponent("02 Jazz/Later.jpg"))
    await model.markReady(model.local.manifest.folders[1].itemUuid)
    XCTAssertTrue(model.isUploading)
    XCTAssertEqual(model.queuedIDs.count, 2)
    model.pauseUploads()
    try await awaitUpload { !model.isUploading }
    XCTAssertEqual(model.queuedIDs.count, 2)
  }

  func testVerifiedAlbumRemainsCullLockedAndLaterPhotosNeedReady() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/First.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    _ = try await library.scan()
    try await library.markReady(itemIDs: [manifest.folders[0].itemUuid])
    let queued = try await library.queuedUploadPhotos()
    let photo = try XCTUnwrap(queued.first)
    let staged = try await library.stageUpload(photo)
    defer { try? FileManager.default.removeItem(at: staged.deletingLastPathComponent()) }
    XCTAssertEqual(
      try Data(contentsOf: staged),
      try Data(contentsOf: root.appendingPathComponent("01 Ballet/First.jpg")))
    try await library.acknowledgeUpload(photo, serverFileUuid: UUID().uuidString)
    try jpeg(root.appendingPathComponent("01 Ballet/Later.jpg"))
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let after = try await reopened.scan()
    XCTAssertTrue(after.queuedIDs.isEmpty)
    let later = try XCTUnwrap(after.photos.first { $0.name == "Later.jpg" })
    XCTAssertFalse(later.uploaded)
    do {
      try await reopened.trash(later.id)
      XCTFail("Culled uploaded album")
    } catch {}
    try await reopened.markReady(itemIDs: [later.itemID])
    let ready = try await reopened.scan()
    XCTAssertEqual(ready.queuedIDs, [later.id])
    try await reopened.toggleTopPic(photo.photoUuid)
  }

  func testQueuedUploadFingerprintsMatchBytesAndPreserveTopPicsWithoutUploading() async throws {
    let (root, manifest) = try fixture()
    let photoURL = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(photoURL)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let snapshot = try await library.scan()
    let empty = try await library.queuedUploadPhotos()
    XCTAssertTrue(empty.isEmpty)
    let photo = try XCTUnwrap(snapshot.photos.first)
    try await library.toggleTopPic(photo.id)
    try await library.markReady(itemIDs: [photo.itemID])
    let uploads = try await library.queuedUploadPhotos()
    let upload = try XCTUnwrap(uploads.first)
    let bytes = try Data(contentsOf: photoURL)
    XCTAssertEqual(
      upload.sha256, SHA256.hash(data: bytes).map { String(format: "%02x", $0) }.joined())
    XCTAssertEqual(upload.byteCount, Int64(bytes.count))
    XCTAssertEqual(upload.photoUuid, photo.id)
    XCTAssertTrue(upload.isTopPic)
    XCTAssertEqual(upload.fileName, "Photo.jpg")
    let after = try await library.scan()
    XCTAssertFalse(after.photos[0].uploaded)
    XCTAssertEqual(after.queuedIDs, [photo.id])
    try FileManager.default.setAttributes(
      [.modificationDate: Date().addingTimeInterval(5)], ofItemAtPath: photoURL.path)
    let changed = try await library.queuedUploadPhotos()
    XCTAssertTrue(changed.isEmpty)
  }

  func testCombinePreservesIDsTopPicsAndTrashRestoreAcrossReopen() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    try jpeg(root.appendingPathComponent("01 Ballet/Trash.jpg"))
    try jpeg(root.appendingPathComponent("02 Jazz/Two.jpg"))
    let original = try Data(contentsOf: root.appendingPathComponent("01 Ballet/One.jpg"))
    let approved = try Data(contentsOf: root.appendingPathComponent("programme.json"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photos = try await library.scan().photos
    let first = photos.first { $0.name == "One.jpg" }!
    let trash = photos.first { $0.name == "Trash.jpg" }!
    try await library.toggleTopPic(first.id)
    try await library.trash(trash.id)
    let combined = try await library.combineItems(
      from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid,
      title: "Ballet / Jazz!")
    XCTAssertEqual(combined.folders.map(\.relativePath), ["01 Ballet Jazz"])
    XCTAssertEqual(combined.programme.nodes[0].mergedIntoUuid, manifest.folders[1].itemUuid)
    XCTAssertEqual(
      Set(combined.programme.nodes.map(\.uuid)), Set(manifest.programme.nodes.map(\.uuid)))
    // Restore directly after reopening, before scanning has remapped saved records.
    let restore = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    try await restore.undoTrash()
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let snapshot = try await reopened.scan()
    XCTAssertEqual(snapshot.photos.count, 3)
    XCTAssertTrue(snapshot.photos.first { $0.id == first.id }!.isTopPic)
    XCTAssertTrue(snapshot.photos.allSatisfy { $0.itemID == manifest.folders[1].itemUuid })
    XCTAssertFalse(snapshot.canUndoMove)
    XCTAssertEqual(Set(snapshot.photos.map(\.id)), Set(photos.map(\.id)))
    XCTAssertEqual(
      try Data(contentsOf: root.appendingPathComponent("01 Ballet Jazz/One.jpg")), original)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("programme.json")), approved)
    let archive = root.appendingPathComponent(".dancepro-combined-" + manifest.folders[0].itemUuid)
    XCTAssertTrue(try FileManager.default.contentsOfDirectory(atPath: archive.path).isEmpty)
  }

  func testCombineCollisionAndNonPhotoFilesLeaveStructureUntouched() async throws {
    let (root, manifest) = try fixture()
    for folder in manifest.folders {
      try jpeg(root.appendingPathComponent(folder.relativePath + "/Same.jpg"))
    }
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    _ = try await library.scan()
    do {
      _ = try await library.combineItems(
        from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid, title: "Together")
      XCTFail("Overwrote collision")
    } catch {}
    for folder in manifest.folders {
      XCTAssertTrue(
        FileManager.default.fileExists(
          atPath: root.appendingPathComponent(folder.relativePath + "/Same.jpg").path))
    }
    XCTAssertFalse(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent("local-programme.json").path))
    try Data("video".utf8).write(to: root.appendingPathComponent("01 Ballet/clip.mov"))
    do {
      _ = try await library.combineItems(
        from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid, title: "Together")
      XCTFail("Hid non-photo media")
    } catch {}
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("01 Ballet/clip.mov").path)
    )
  }

  func testCombineRollsPhotosBackWhenFinalFolderConflicts() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    try FileManager.default.createDirectory(
      at: root.appendingPathComponent("01 Together"), withIntermediateDirectories: false)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    do {
      _ = try await library.combineItems(
        from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid, title: "Together")
      XCTFail("Overwrote a folder")
    } catch {}
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent(photo.relativePath).path))
    XCTAssertFalse(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("02 Jazz/One.jpg").path))
    let state = try await library.scan()
    XCTAssertFalse(state.canUndoMove)
    XCTAssertFalse(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent("local-programme.json").path))
  }

  func testRepeatedCombineKeepsAliasesPointingAtActiveItem() async throws {
    let (root, manifest) = try fixture()
    var programme = manifest.programme
    programme.nodes.append(ConcertLayout.newItem(title: "Finale"))
    let three = try ConcertLayout.apply(programme, root: root, base: manifest)
    try jpeg(root.appendingPathComponent("01 Ballet/One.jpg"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let id = try await library.scan().photos[0].id
    _ = try await library.combineItems(
      from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid, title: "First pair")
    _ = try await library.scan()
    let result = try await library.combineItems(
      from: manifest.folders[1].itemUuid, into: three.folders[2].itemUuid, title: "All together")
    XCTAssertEqual(result.folders.count, 1)
    XCTAssertEqual(
      result.programme.nodes.filter { $0.mergedIntoUuid == three.folders[2].itemUuid }.count, 2)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await reopened.scan().photos[0]
    XCTAssertEqual(photo.id, id)
    XCTAssertEqual(photo.relativePath, "01 All together/One.jpg")
  }

  func testStableCompleteJPEGsOnlyAndNaturalOrdering() async throws {
    let (root, manifest) = try fixture()
    try jpeg(root.appendingPathComponent("01 Ballet/Photo10.JPG"))
    try jpeg(root.appendingPathComponent("01 Ballet/Photo2.jpg"))
    try Data([0xff, 0xd8, 0xff]).write(to: root.appendingPathComponent("01 Ballet/incomplete.jpg"))
    try Data("raw".utf8).write(to: root.appendingPathComponent("01 Ballet/camera.CR3"))
    let library = try PhotoLibrary(root: root, manifest: manifest)
    let now = Date().addingTimeInterval(5)
    let first = try await library.scan(now: now)
    XCTAssertTrue(first.photos.isEmpty)
    let second = try await library.scan(now: now.addingTimeInterval(3))
    XCTAssertEqual(second.photos.map(\.name), ["Photo2.jpg", "Photo10.JPG"])
    XCTAssertEqual(second.pending[manifest.folders[0].itemUuid], 1)
    let data = try await library.preview(second.photos[0].id)
    XCTAssertNotNil(CGImageSourceCreateWithData(data as CFData, nil))
  }
  func testTopPicsPersistAcrossRestartAndExternalMoveWithoutChangingOriginal() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let original = try Data(contentsOf: file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    try await library.toggleTopPic(photo.id)
    try FileManager.default.moveItem(at: file, to: root.appendingPathComponent("02 Jazz/Moved.jpg"))
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let moved = try await reopened.scan().photos[0]
    XCTAssertEqual(moved.id, photo.id)
    XCTAssertTrue(moved.isTopPic)
    XCTAssertEqual(moved.itemID, manifest.folders[1].itemUuid)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent(moved.relativePath)), original)
  }
  func testChangedFileIsNotTrashedUsingAStaleSelection() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    try Data("new incomplete capture".utf8).write(to: file)
    do {
      try await library.trash(photo.id)
      XCTFail("A changed file was trashed")
    } catch {}
    XCTAssertTrue(FileManager.default.fileExists(atPath: file.path))
  }
  func testTrashUndoPersistsAndNeverOverwritesANewerPhoto() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    let original = try Data(contentsOf: file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let photo = try await library.scan().photos[0]
    try await library.toggleTopPic(photo.id)
    try await library.trash(photo.id)
    XCTAssertFalse(FileManager.default.fileExists(atPath: file.path))
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    try Data("replacement".utf8).write(to: file)
    do {
      try await reopened.undoTrash()
      XCTFail("Undo overwrote a newer file")
    } catch {}
    XCTAssertEqual(try String(contentsOf: file, encoding: .utf8), "replacement")
    // Remove only the synthetic replacement created by this test, then restore the original.
    try FileManager.default.removeItem(at: file)
    try await reopened.undoTrash()
    let restored = try await reopened.scan().photos[0]
    XCTAssertEqual(try Data(contentsOf: file), original)
    XCTAssertEqual(restored.id, photo.id)
    XCTAssertTrue(restored.isTopPic)
  }
  func testSymlinksAreSkippedAndUploadedPhotosCannotBeCulled() async throws {
    let (root, manifest) = try fixture()
    let file = root.appendingPathComponent("01 Ballet/Photo.jpg")
    try jpeg(file)
    try FileManager.default.createSymbolicLink(
      at: root.appendingPathComponent("02 Jazz/Link.jpg"), withDestinationURL: file)
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let snapshot = try await library.scan()
    XCTAssertEqual(snapshot.photos.count, 1)
    let photo = snapshot.photos[0]
    let stateURL = root.appendingPathComponent("photo-review.json")
    var state = try JSONSerialization.jsonObject(with: Data(contentsOf: stateURL)) as! [String: Any]
    var records = state["records"] as! [String: [String: Any]]
    records[photo.id]?["uploaded"] = true
    state["records"] = records
    try JSONSerialization.data(withJSONObject: state).write(to: stateURL)
    let reopened = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    _ = try await reopened.scan()
    do {
      try await reopened.trash(photo.id)
      XCTFail("Uploaded photo was trashed")
    } catch {}
    do {
      try await reopened.move([photo.id], to: manifest.folders[1].itemUuid)
      XCTFail("Uploaded photo moved")
    } catch {}
    do {
      _ = try await reopened.changeConcertLayout(manifest.programme)
      XCTFail("Uploaded concert layout changed")
    } catch {}
    do {
      _ = try await reopened.combineItems(
        from: manifest.folders[0].itemUuid, into: manifest.folders[1].itemUuid, title: "Together")
      XCTFail("Uploaded items were combined")
    } catch {}
    try await reopened.toggleTopPic(photo.id)
    let updated = try await reopened.scan()
    XCTAssertTrue(updated.photos[0].isTopPic)
    try await reopened.setTopPic(photo.id, selected: false)
    let removed = try await reopened.scan()
    XCTAssertFalse(removed.photos[0].isTopPic)
    XCTAssertTrue(FileManager.default.fileExists(atPath: file.path))
  }
}
