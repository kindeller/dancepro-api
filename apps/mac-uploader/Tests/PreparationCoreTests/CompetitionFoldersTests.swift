import Foundation
import XCTest
import PhotoReviewCore
@testable import PreparationCore

final class CompetitionFoldersTests: XCTestCase {
  private func fixture() throws -> (URL, PreparedEvent) {
    let destination = FileManager.default.temporaryDirectory.appendingPathComponent("competition-test-" + UUID().uuidString)
    try FileManager.default.createDirectory(at: destination, withIntermediateDirectories: false)
    addTeardownBlock { try FileManager.default.removeItem(at: destination) }
    let day = ProgrammeNode(uuid: UUID().uuidString.lowercased(), kind: "day", title: "Friday", date: "2026-09-04")
    let section = ProgrammeNode(uuid: UUID().uuidString.lowercased(), parentUuid: day.uuid, kind: "section", title: "Jazz 12 & Under", number: "07")
    let item = ProgrammeNode(uuid: UUID().uuidString.lowercased(), parentUuid: section.uuid, kind: "item", title: "Jessica Smith", number: "03")
    let programme = Programme(schemaVersion: 1, eventUuid: UUID().uuidString, eventCode: "EDC926", eventName: "EDC", eventType: "competition", programmeUuid: UUID().uuidString.lowercased(), revisionUuid: UUID().uuidString, version: 1, nodes: [day, section, item])
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil, server: "test", destination: destination)
    let manifest = try ProgrammeCoding.decoder().decode(PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    return (root, manifest)
  }
  func testPreparedCaptureStructureAndStableCrossPlatformIdentity() throws {
    let (root, manifest) = try fixture()
    XCTAssertEqual(root.lastPathComponent, "EDC926")
    XCTAssertEqual(manifest.folders[0].relativePath, "07 Jazz 12 & Under/03 Jessica Smith")
    let namespace = "00000000-0000-4000-8000-000000000001"
    XCTAssertEqual(try CompetitionFolders.identity(programme: namespace, kind: "section", number: "07"), "373a4923-5b88-5cbb-b7a9-8c50adc5c3da")
    XCTAssertEqual(try CompetitionFolders.identity(programme: namespace, kind: "section", number: "007"), try CompetitionFolders.identity(programme: namespace, kind: "section", number: "7"))
  }
  func testDetectsExtraCompetitorsAndSectionsWithoutRenamingCaptureFolders() throws {
    let (root, manifest) = try fixture()
    let fm = FileManager.default
    try fm.createDirectory(at: root.appendingPathComponent("07 Jazz 12 & Under/04 Alex Jones"), withIntermediateDirectories: true)
    try fm.createDirectory(at: root.appendingPathComponent("08 Tap/01 Sam"), withIntermediateDirectories: true)
    let first = try CompetitionFolders.discover(root: root, base: manifest)
    let again = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertTrue(first.issues.isEmpty)
    XCTAssertEqual(first.manifest.folders.count, 3)
    XCTAssertEqual(first.manifest.programme.nodes.count, 6)
    XCTAssertEqual(first.manifest.folders.map(\.itemUuid), again.manifest.folders.map(\.itemUuid))
    XCTAssertTrue(fm.fileExists(atPath: root.appendingPathComponent("07 Jazz 12 & Under/04 Alex Jones").path))
    XCTAssertEqual(first.manifest.folders.first?.itemUuid, manifest.folders.first?.itemUuid)
  }
  func testRenamedCompetitorKeepsIdentityAndReportsObservedName() throws {
    let (root, manifest) = try fixture()
    let source = root.appendingPathComponent(manifest.folders[0].relativePath)
    let renamed = source.deletingLastPathComponent().appendingPathComponent("003 Jess Smith")
    try FileManager.default.moveItem(at: source, to: renamed)
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.manifest.folders[0].itemUuid, manifest.folders[0].itemUuid)
    XCTAssertEqual(found.manifest.programme.nodes.last?.title, "Jessica Smith")
    XCTAssertEqual(found.observed.last?.title, "Jess Smith")
    XCTAssertTrue(found.issues.isEmpty)
  }
  func testAmbiguousAndUnsafeFoldersAreFlaggedWithoutFollowingLinks() throws {
    let (root, manifest) = try fixture()
    let fm = FileManager.default
    try fm.createDirectory(at: root.appendingPathComponent("07 Jazz 12 & Under/003 Other"), withIntermediateDirectories: false)
    try fm.createDirectory(at: root.appendingPathComponent("Unknown folder"), withIntermediateDirectories: false)
    try fm.createSymbolicLink(at: root.appendingPathComponent("07 Jazz 12 & Under/04 Linked"), withDestinationURL: root)
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.manifest.folders.count, 0)
    XCTAssertEqual(found.issues.count, 3)
    XCTAssertTrue(found.issues.contains { $0.contains("Duplicate competitor") })
    XCTAssertTrue(found.issues.contains { $0.contains("symbolic link") })
    XCTAssertTrue(fm.fileExists(atPath: root.appendingPathComponent(manifest.folders[0].relativePath).path))
  }
  func testExistingDateSectionFoldersRemainReadable() throws {
    let (root, manifest) = try fixture()
    let fm = FileManager.default
    try fm.createDirectory(at: root.appendingPathComponent("2026-09-04"), withIntermediateDirectories: false)
    try fm.moveItem(at: root.appendingPathComponent("07 Jazz 12 & Under"), to: root.appendingPathComponent("2026-09-04/Section 07"))
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.manifest.folders[0].itemUuid, manifest.folders[0].itemUuid)
    XCTAssertTrue(found.issues.isEmpty)
  }
  func testVideoStationCanDiscoverFoldersWithoutJPEGs() async throws {
    let (root, manifest) = try fixture()
    let folder = root.appendingPathComponent("07 Jazz 12 & Under/04 Alex Jones")
    try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: false)
    try Data("test-only video placeholder".utf8).write(to: folder.appendingPathComponent("capture.mp4"))
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let scan = try await library.scan()
    XCTAssertEqual(scan.competition?.manifest.folders.count, 2)
    XCTAssertTrue(scan.photos.isEmpty)
    XCTAssertEqual(scan.competition?.observed.count, 3)
  }
  func testMultipleDaysKeepDayFoldersAndFlatSectionsAreNotAssignedToAnAssumedDay() throws {
    let (root, single) = try fixture()
    var programme = single.programme
    let day = ProgrammeNode(uuid: UUID().uuidString, kind: "day", title: "Saturday", date: "2026-09-05")
    let section = ProgrammeNode(uuid: UUID().uuidString, parentUuid: day.uuid, kind: "section", title: "Tap", number: "08")
    let item = ProgrammeNode(uuid: UUID().uuidString, parentUuid: section.uuid, kind: "item", title: "Alex", number: "03")
    programme.nodes += [day, section, item]
    let plan = try FolderPlan(programme: programme)
    XCTAssertEqual(plan.folders.map(\.relativePath), ["01 Friday/07 Jazz 12 & Under/03 Jessica Smith", "02 Saturday/08 Tap/03 Alex"])
    let manifest = PreparedEvent(server: single.server, programme: programme, folders: plan.folders, preparedAt: single.preparedAt, hasPdf: false)
    let unresolved = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertTrue(unresolved.manifest.folders.isEmpty)
    XCTAssertTrue(unresolved.issues.contains { $0.contains("Unmatched day") })
    try FileManager.default.createDirectory(at: root.appendingPathComponent("01 Friday"), withIntermediateDirectories: false)
    try FileManager.default.moveItem(at: root.appendingPathComponent("07 Jazz 12 & Under"), to: root.appendingPathComponent("01 Friday/07 Jazz 12 & Under"))
    try FileManager.default.createDirectory(at: root.appendingPathComponent(plan.folders[1].relativePath), withIntermediateDirectories: true)
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertTrue(found.issues.isEmpty)
    XCTAssertEqual(found.manifest.folders.count, 2)
    XCTAssertEqual(CompetitionVideoNames.match(filename: "EDC926_07_03.mp4", relativePath: "file.mp4", programme: programme).itemUuid, single.programme.nodes.last?.uuid)
    XCTAssertEqual(CompetitionVideoNames.match(filename: "EDC926_08_03.mov", relativePath: "file.mov", programme: programme).itemUuid, item.uuid)
  }

  func testPreparingAnExistingOneDayFolderPreservesTheOlderDayLayout() throws {
    let (root, manifest) = try fixture()
    let fm = FileManager.default
    try fm.createDirectory(at: root.appendingPathComponent("01 Friday"), withIntermediateDirectories: false)
    try fm.moveItem(at: root.appendingPathComponent("07 Jazz 12 & Under"), to: root.appendingPathComponent("01 Friday/07 Jazz 12 & Under"))
    let old = PreparedEvent(server: manifest.server, programme: manifest.programme,
      folders: try FolderPlan(programme: manifest.programme, includeSingleDay: true).folders, preparedAt: manifest.preparedAt, hasPdf: false)
    try ProgrammeCoding.encoder().encode(old).write(to: root.appendingPathComponent("preparation.json"))
    let media = root.appendingPathComponent(old.folders[0].relativePath).appendingPathComponent("keep.txt")
    try Data("keep".utf8).write(to: media)
    let prepared = try LocalPreparation.prepare(programme: manifest.programme, pdf: nil, server: manifest.server, destination: root.deletingLastPathComponent())
    XCTAssertEqual(prepared, root)
    XCTAssertEqual(try Data(contentsOf: media), Data("keep".utf8))
    let found = try CompetitionFolders.discover(root: root, base: old)
    XCTAssertEqual(found.manifest.folders.map(\.relativePath), old.folders.map(\.relativePath))
    XCTAssertTrue(found.issues.isEmpty)
  }

  func testVideoFilenameMatchingIsVisibleAndDoesNotReadOrMoveVideoBytes() throws {
    let (root, manifest) = try fixture()
    let name = "EDC926_07_03.mp4"
    let bytes = Data("test-only placeholder, not playable video".utf8)
    try bytes.write(to: root.appendingPathComponent(name))
    try bytes.write(to: root.appendingPathComponent("OTHER_07_03.mov"))
    try bytes.write(to: root.appendingPathComponent("EDC926_07_99.mov"))
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.videos.count, 3)
    XCTAssertEqual(found.videos.first { $0.relativePath == name }?.itemUuid, manifest.programme.nodes.last?.uuid)
    XCTAssertEqual(found.videos.filter { $0.itemUuid == nil }.count, 2)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent(name)), bytes)
    XCTAssertEqual(CompetitionVideoNames.match(filename: "edc926_7_003.MOV", relativePath: "test", programme: manifest.programme).itemUuid, manifest.programme.nodes.last?.uuid)
    XCTAssertEqual(CompetitionVideoNames.match(filename: "EDC926_07_03a.mp4", relativePath: "test", programme: manifest.programme).itemUuid, manifest.programme.nodes.last?.uuid)
    XCTAssertNil(CompetitionVideoNames.match(filename: "EDC926_01_07_03.mp4", relativePath: "test", programme: manifest.programme).itemUuid)
  }

  func testVideoFilenameConflictingWithItsCompetitorFolderIsFlagged() throws {
    let (root, manifest) = try fixture()
    let extra = root.appendingPathComponent("07 Jazz 12 & Under/04 Alex")
    try FileManager.default.createDirectory(at: extra, withIntermediateDirectories: false)
    try Data("test-only".utf8).write(to: extra.appendingPathComponent("EDC926_07_03.mp4"))
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.videos.count, 1)
    XCTAssertNil(found.videos[0].itemUuid)
    XCTAssertEqual(found.videos[0].label, "Filename and containing folder disagree")
  }

  func testSingleDaySectionNamedFridayIsNotMistakenForADayWrapper() throws {
    let (root, original) = try fixture()
    var programme = original.programme
    programme.nodes[1].number = "01"
    programme.nodes[1].title = "Friday"
    let manifest = PreparedEvent(server: original.server, programme: programme,
      folders: try FolderPlan(programme: programme).folders, preparedAt: original.preparedAt, hasPdf: false)
    try FileManager.default.moveItem(at: root.appendingPathComponent("07 Jazz 12 & Under"), to: root.appendingPathComponent("01 Friday"))
    let found = try CompetitionFolders.discover(root: root, base: manifest)
    XCTAssertEqual(found.manifest.folders.first?.relativePath, "01 Friday/03 Jessica Smith")
    XCTAssertTrue(found.issues.isEmpty)
  }

}
