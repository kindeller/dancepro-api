import XCTest

@testable import PreparationCore

final class PreparationTests: XCTestCase {
  private func concert(_ count: Int = 2) -> Programme {
    Programme(
      schemaVersion: 1, eventUuid: UUID().uuidString, eventCode: "TEST2026",
      eventName: "Test Concert", eventType: "concert", programmeUuid: UUID().uuidString,
      revisionUuid: UUID().uuidString, version: 1,
      nodes: (1...count).map {
        ProgrammeNode(
          uuid: UUID().uuidString, kind: "item", title: "It's Ballet / Jazz!",
          folderNumber: String(format: "%02d", $0), folderTitle: "Its Ballet Jazz")
      })
  }
  private func destination() throws -> URL {
    let url = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
    try FileManager.default.createDirectory(at: url, withIntermediateDirectories: false)
    addTeardownBlock { try FileManager.default.removeItem(at: url) }
    return url
  }
  func testDressRehearsalPreparationUsesOnePlainPhotoFolderWithoutPdf() throws {
    var programme = concert(1)
    programme.captureMode = "dress_rehearsal"
    programme.nodes[0].title = "Dress Rehearsal Portraits"
    programme.nodes[0].folderTitle = "Dress Rehearsal Portraits"
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination())
    let saved = try ProgrammeCoding.decoder().decode(PreparedEvent.self,
      from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    XCTAssertTrue(saved.programme.isPortraitSession)
    XCTAssertEqual(saved.folders.map(\.relativePath), ["Dress Rehearsal Portraits"])
    XCTAssertFalse(saved.hasPdf)
    XCTAssertFalse(FileManager.default.fileExists(atPath: root.appendingPathComponent("programme.pdf").path))
    XCTAssertTrue(FileManager.default.fileExists(atPath: root.appendingPathComponent("Dress Rehearsal Portraits").path))
    let reopened = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: root)
    XCTAssertEqual(reopened, root)
  }

  func testSelectingPreparedFolderReopensItWithoutCreatingNestedFoldersOrReplacingWork() throws {
    let programme = concert()
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination())
    let photo = root.appendingPathComponent("01 Its Ballet Jazz/Keep.jpg")
    let state = root.appendingPathComponent("photo-review.json")
    try Data("sorted photo".utf8).write(to: photo)
    try Data("existing review state".utf8).write(to: state)
    let manifestBytes = try Data(contentsOf: root.appendingPathComponent("preparation.json"))
    let reopened = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: root)
    XCTAssertEqual(reopened, root)
    XCTAssertFalse(FileManager.default.fileExists(atPath: root.appendingPathComponent(root.lastPathComponent).path))
    XCTAssertEqual(try Data(contentsOf: photo), Data("sorted photo".utf8))
    XCTAssertEqual(try Data(contentsOf: state), Data("existing review state".utf8))
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("preparation.json")), manifestBytes)
    XCTAssertThrowsError(try LocalPreparation.prepare(programme: concert(), pdf: nil,
      server: "test", destination: root))
    XCTAssertThrowsError(try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "another-server", destination: root))
    XCTAssertThrowsError(try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: root, videoTitles: Data("PK titles".utf8)))
    XCTAssertEqual(try Data(contentsOf: photo), Data("sorted photo".utf8))
  }

  func testVideoPreparationSavesTitlesWithoutPhotoFoldersAndKeepsExistingPhotos() throws {
    let programme = concert()
    let destination = try destination()
    let photoRoot = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination)
    let photo = photoRoot.appendingPathComponent("01 Its Ballet Jazz/Keep.jpg")
    try Data("existing photo".utf8).write(to: photo)
    let titles = Data("PK titles".utf8)
    let videoRoot = try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination, videoTitles: titles)
    XCTAssertNotEqual(photoRoot, videoRoot)
    XCTAssertEqual(try Data(contentsOf: photo), Data("existing photo".utf8))
    let manifest = try ProgrammeCoding.decoder().decode(PreparedEvent.self,
      from: Data(contentsOf: videoRoot.appendingPathComponent("preparation.json")))
    XCTAssertTrue(manifest.isVideoPreparation)
    XCTAssertTrue(manifest.folders.isEmpty)
    XCTAssertFalse(FileManager.default.fileExists(atPath: videoRoot.appendingPathComponent("01 Its Ballet Jazz").path))
    XCTAssertEqual(try Data(contentsOf: videoRoot.appendingPathComponent("Video Titles.zip")), titles)
    XCTAssertEqual(try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination, videoTitles: titles), videoRoot)
    XCTAssertThrowsError(try LocalPreparation.prepare(programme: programme, pdf: nil,
      server: "test", destination: destination, videoTitles: Data("changed".utf8)))
    let photos = try ProgrammeCoding.decoder().decode(PreparedEvent.self,
      from: Data(contentsOf: photoRoot.appendingPathComponent("preparation.json")))
    XCTAssertFalse(photos.isVideoPreparation)
    XCTAssertEqual(photos.folders.count, 2)
  }

  func testCombineDraftKeepsFirstPositionAndExistingIdentityWithUnsavedRows() throws {
    var programme = concert(3)
    programme.nodes[0].title = "First!"
    programme.nodes[1].title = "Middle"
    programme.nodes[2].title = "Last?"
    let existingIDs = Set(programme.nodes.map(\.uuid))
    let added = ConcertLayout.newItem(title: "New")
    programme.nodes.insert(added, at: 0)
    let result = try ConcertLayout.combining(
      [added.uuid, programme.nodes[3].uuid],
      in: programme, existingIDs: existingIDs)
    XCTAssertEqual(
      result.nodes.filter(\.isActiveItem).map(\.title), ["New Last?", "First!", "Middle"])
    XCTAssertEqual(result.nodes[0].uuid, programme.nodes[3].uuid)
    XCTAssertEqual(result.nodes.filter(\.isActiveItem).map(\.folderNumber), ["01", "02", "03"])
    XCTAssertThrowsError(
      try ConcertLayout.combining([added.uuid], in: programme, existingIDs: existingIDs))
  }

  func testConcertLayoutRejectsCollisionAndIdentityRemoval() throws {
    let programme = concert()
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "test", destination: destination())
    let base = try ProgrammeCoding.decoder().decode(
      PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    var draft = programme
    draft.nodes[0].title = "Conflict"
    try FileManager.default.createDirectory(
      at: root.appendingPathComponent("01 Conflict"), withIntermediateDirectories: false)
    XCTAssertThrowsError(try ConcertLayout.apply(draft, root: root, base: base))
    XCTAssertTrue(
      FileManager.default.fileExists(
        atPath: root.appendingPathComponent(base.folders[0].relativePath).path))
    draft.nodes.removeFirst()
    XCTAssertThrowsError(try ConcertLayout.apply(draft, root: root, base: base))
    var competition = programme
    competition.eventType = "competition"
    XCTAssertThrowsError(try ConcertLayout.normalized(competition))
  }

  func testInterruptedFolderRenameResumesWithoutMovingWrongIdentity() throws {
    let programme = concert()
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "test", destination: destination())
    let base = try ProgrammeCoding.decoder().decode(
      PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    var next = programme
    next.nodes.reverse()
    next.nodes[0].title = "New Title"
    next = try ConcertLayout.normalized(next)
    let staging = ".dancepro-layout-" + UUID().uuidString
    try FileManager.default.createDirectory(
      at: root.appendingPathComponent(staging), withIntermediateDirectories: false)
    var first: [[String: String]] = []
    var last: [[String: String]] = []
    for folder in try FolderPlan(programme: next).folders {
      let old = base.folders.first { $0.itemUuid == folder.itemUuid }!
      let source = root.appendingPathComponent(old.relativePath)
      let attributes = try FileManager.default.attributesOfItem(atPath: source.path)
      let identity =
        "\(attributes[.systemNumber] as! NSNumber):\(attributes[.systemFileNumber] as! NSNumber)"
      let staged = staging + "/" + folder.itemUuid
      first.append(["from": old.relativePath, "to": staged, "identity": identity])
      last.append(["from": staged, "to": folder.relativePath, "identity": identity])
    }
    let journal: [String: Any] = [
      "previous": try JSONSerialization.jsonObject(with: JSONEncoder().encode(programme)),
      "programme": try JSONSerialization.jsonObject(with: JSONEncoder().encode(next)),
      "staging": staging, "moves": first + last, "completed": 0,
    ]
    let url = root.appendingPathComponent("layout-change.json")
    var brokenJournal = journal
    var wrongMoves = first + last
    wrongMoves[0]["identity"] = "wrong-folder"
    brokenJournal["moves"] = wrongMoves
    try JSONSerialization.data(withJSONObject: brokenJournal).write(to: url)
    XCTAssertThrowsError(try ConcertLayout.load(root: root, base: base))
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent(first[0]["from"]!).path))
    try JSONSerialization.data(withJSONObject: journal).write(to: url)
    // Simulate a crash after moving the first folder but before advancing the journal.
    try FileManager.default.moveItem(
      at: root.appendingPathComponent(first[0]["from"]!),
      to: root.appendingPathComponent(first[0]["to"]!))
    let recovered = try ConcertLayout.load(root: root, base: base)
    XCTAssertEqual(recovered.folders.map(\.relativePath), ["01 New Title", "02 Its Ballet Jazz"])
    XCTAssertFalse(FileManager.default.fileExists(atPath: url.path))
    for folder in recovered.folders {
      XCTAssertTrue(
        FileManager.default.fileExists(
          atPath: root.appendingPathComponent(folder.relativePath).path))
    }
  }

  func testCombinedAliasesRejectCyclesAndMissingTargets() throws {
    var programme = concert()
    programme.nodes[0].mergedIntoUuid = UUID().uuidString
    XCTAssertThrowsError(try ConcertLayout.normalized(programme))
    programme.nodes[0].mergedIntoUuid = programme.nodes[1].uuid
    programme.nodes[1].mergedIntoUuid = programme.nodes[0].uuid
    XCTAssertThrowsError(try ConcertLayout.normalized(programme))
  }

  func testInterruptedCombineLayoutResumesArchiveAndRenumbering() throws {
    let programme = concert()
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "test", destination: destination())
    let base = try ProgrammeCoding.decoder().decode(
      PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    var next = programme
    next.nodes[0].mergedIntoUuid = next.nodes[1].uuid
    next.nodes[1].title = "Together"
    next = try ConcertLayout.normalized(next)
    let staging = ".dancepro-layout-" + UUID().uuidString
    try FileManager.default.createDirectory(
      at: root.appendingPathComponent(staging), withIntermediateDirectories: false)
    let destinations = [
      (base.folders[1], "01 Together"),
      (base.folders[0], ".dancepro-combined-" + base.folders[0].itemUuid),
    ]
    var first: [[String: String]] = []
    var last: [[String: String]] = []
    for (old, target) in destinations {
      let info = try FileManager.default.attributesOfItem(
        atPath: root.appendingPathComponent(old.relativePath).path)
      let identity = "\(info[.systemNumber] as! NSNumber):\(info[.systemFileNumber] as! NSNumber)"
      let staged = staging + "/" + old.itemUuid
      first.append(["from": old.relativePath, "to": staged, "identity": identity])
      last.append(["from": staged, "to": target, "identity": identity])
    }
    let journal: [String: Any] = [
      "previous": try JSONSerialization.jsonObject(with: JSONEncoder().encode(programme)),
      "programme": try JSONSerialization.jsonObject(with: JSONEncoder().encode(next)),
      "staging": staging, "moves": first + last, "completed": 0,
    ]
    try JSONSerialization.data(withJSONObject: journal).write(
      to: root.appendingPathComponent("layout-change.json"))
    try FileManager.default.moveItem(
      at: root.appendingPathComponent(first[0]["from"]!),
      to: root.appendingPathComponent(first[0]["to"]!))
    let recovered = try ConcertLayout.load(root: root, base: base)
    XCTAssertEqual(recovered.folders.map(\.relativePath), ["01 Together"])
    XCTAssertEqual(recovered.programme.nodes[0].mergedIntoUuid, programme.nodes[1].uuid)
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent(destinations[1].1).path))
    XCTAssertFalse(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("layout-change.json").path)
    )
  }

  func testContinuousTwoDigitNumberingAndNumericOrderAbove99() throws {
    var programme = concert(101)
    programme.nodes.reverse()
    let plan = try FolderPlan(programme: programme)
    XCTAssertEqual(plan.folders.first?.relativePath, "01 Its Ballet Jazz")
    XCTAssertEqual(plan.folders[98].relativePath, "99 Its Ballet Jazz")
    XCTAssertEqual(plan.folders[99].relativePath, "100 Its Ballet Jazz")
    XCTAssertEqual(programme.nodes[0].title, "It's Ballet / Jazz!")
  }
  func testBreaksAreExcludedAndPunctuationOnlyRemovedFromFolders() throws {
    var programme = concert()
    programme.nodes.append(
      ProgrammeNode(uuid: UUID().uuidString, kind: "item", title: "Interval", itemKind: "break"))
    XCTAssertEqual(try FolderPlan(programme: programme).folders.count, 2)
    XCTAssertEqual(FolderPlan.clean("It's / Ballet: Café—Jazz?!"), "Its Ballet CafeJazz")
    let roundTrip = try ProgrammeCoding.decoder().decode(
      Programme.self, from: ProgrammeCoding.encoder().encode(programme))
    XCTAssertEqual(roundTrip.nodes[0].title, "It's Ballet / Jazz!")
  }
  func testAccentCleanupPreservesLegacyFoldersUntilExplicitSave() throws {
    XCTAssertEqual(FolderPlan.clean("BREAKÍN DISHES"), "BREAKIN DISHES")
    XCTAssertEqual(FolderPlan.clean("BREAKI\u{0301}N DISHES"), "BREAKIN DISHES")
    var programme = concert(1)
    programme.nodes[0].title = "BREAKI\u{0301}N DISHES"
    programme.nodes[0].folderTitle = programme.nodes[0].title
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil, server: "test", destination: destination())
    let base = try ProgrammeCoding.decoder().decode(PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    let oldPath = base.folders[0].relativePath
    try Data("original photo".utf8).write(to: root.appendingPathComponent(oldPath + "/photo.jpg"))
    // A legacy local working copy must still load after an app update.
    try ProgrammeCoding.encoder().encode(programme).write(to: root.appendingPathComponent("local-programme.json"))
    XCTAssertEqual(try ConcertLayout.load(root: root, base: base).folders[0].relativePath, oldPath)
    let updated = try ConcertLayout.apply(programme, root: root, base: base)
    XCTAssertEqual(updated.folders[0].relativePath, "01 BREAKIN DISHES")
    XCTAssertEqual(updated.programme.nodes[0].title, programme.nodes[0].title)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent("01 BREAKIN DISHES/photo.jpg")), Data("original photo".utf8))
    XCTAssertEqual(try ConcertLayout.load(root: root, base: base).folders[0].relativePath, "01 BREAKIN DISHES")
  }

  func testUnsafeNamesDuplicateIdentitiesAndMissingNumberingAreRejected() throws {
    for title in ["../escape", "bad\\path", "bad:path", "bad\npath"] {
      var programme = concert()
      programme.nodes[0].folderTitle = title
      XCTAssertThrowsError(try FolderPlan(programme: programme))
    }
    var programme = concert()
    programme.nodes[1].uuid = programme.nodes[0].uuid
    XCTAssertThrowsError(try FolderPlan(programme: programme))
    programme = concert()
    programme.nodes[1].folderNumber = "03"
    XCTAssertThrowsError(try FolderPlan(programme: programme))
    programme = concert()
    programme.nodes[0].folderTitle = nil
    XCTAssertThrowsError(try FolderPlan(programme: programme))
  }
  func testCompetitionRetainsPrintedNumberGaps() throws {
    var programme = concert()
    programme.eventType = "competition"
    let day = ProgrammeNode(
      uuid: UUID().uuidString, kind: "day", title: "Day 1", date: "2026-09-21")
    let section = ProgrammeNode(
      uuid: UUID().uuidString, parentUuid: day.uuid, kind: "section", title: "Ballet", number: "2")
    programme.nodes = [
      day, section,
      ProgrammeNode(
        uuid: UUID().uuidString, parentUuid: section.uuid, kind: "item", title: "Dancer / Two!",
        number: "3"),
    ]
    XCTAssertEqual(
      try FolderPlan(programme: programme).folders[0].relativePath,
      "02 Ballet/03 Dancer Two!")
  }
  func testPreparationIsOfflineReadableIdempotentAndPreservesMedia() throws {
    let programme = concert()
    let destination = try destination()
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "https://dancepro.test", destination: destination)
    let media = root.appendingPathComponent("01 Its Ballet Jazz/photo.jpg")
    try Data("existing media".utf8).write(to: media)
    let again = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "https://dancepro.test", destination: destination)
    XCTAssertEqual(root, again)
    XCTAssertEqual(try String(contentsOf: media, encoding: .utf8), "existing media")
    let saved = try ProgrammeCoding.decoder().decode(
      PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    XCTAssertEqual(saved.programme.nodes[0].title, "It's Ballet / Jazz!")
    XCTAssertEqual(saved.folders.count, 2)
    var newer = programme
    newer.revisionUuid = UUID().uuidString
    newer.version = 2
    XCTAssertNotEqual(
      try LocalPreparation.prepare(
        programme: newer, pdf: nil, server: "https://dancepro.test", destination: destination), root
    )
    XCTAssertEqual(try String(contentsOf: media, encoding: .utf8), "existing media")
  }
  func testMissingOrInvalidPdfDoesNotLeavePartialFolders() throws {
    var programme = concert()
    programme.sourceUrl = "/api/test.pdf"
    let destination = try destination()
    XCTAssertThrowsError(
      try LocalPreparation.prepare(
        programme: programme, pdf: nil, server: "test", destination: destination))
    XCTAssertThrowsError(
      try LocalPreparation.prepare(
        programme: programme, pdf: Data("not pdf".utf8), server: "test", destination: destination))
    XCTAssertEqual(try FileManager.default.contentsOfDirectory(atPath: destination.path), [])
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: Data("%PDF-1.4 test".utf8), server: "test",
      destination: destination)
    XCTAssertTrue(
      FileManager.default.fileExists(atPath: root.appendingPathComponent("programme.pdf").path))
  }
  func testExistingSymlinkFolderIsRejected() throws {
    let programme = concert()
    let destination = try destination()
    let root = try LocalPreparation.prepare(
      programme: programme, pdf: nil, server: "test", destination: destination)
    let folder = root.appendingPathComponent("01 Its Ballet Jazz")
    try FileManager.default.removeItem(at: folder)
    try FileManager.default.createSymbolicLink(at: folder, withDestinationURL: destination)
    XCTAssertThrowsError(
      try LocalPreparation.prepare(
        programme: programme, pdf: nil, server: "test", destination: destination))
  }
}
