import XCTest

@testable import DanceProUploader
@testable import PreparationCore

final class PhotographerSelectionTests: XCTestCase {
  private func roster(event: String, id: String? = "p1:crew") -> PhotographerRoster {
    PhotographerRoster(
      eventUuid: event, checkedAt: "2026-09-21T12:00:00Z", hasShifts: true,
      photographers: id.map {
        [
          EventPhotographer(
            id: $0, crewUuid: "crew", roleUuid: "p1",
            name: "Alex", roleName: "Photographer 1", shifts: [])
        ]
      } ?? [], unfilled: [])
  }

  private func manifest(event: String, server: String = "https://dancepro.test") -> PreparedEvent {
    PreparedEvent(
      server: server,
      programme: Programme(
        schemaVersion: 1, eventUuid: event, eventName: "Test",
        eventType: "concert", programmeUuid: UUID().uuidString, revisionUuid: UUID().uuidString,
        version: 1, nodes: []), folders: [], preparedAt: Date(), hasPdf: false)
  }

  func testRefreshPreservesEventRoleAcrossCrewHandovers() throws {
    let event = UUID().uuidString
    var saved = PhotographerSelection(
      server: "https://dancepro.test", roster: roster(event: event), selectedId: "p1:crew")
    try saved.refresh(roster(event: event))
    XCTAssertEqual(saved.selected?.name, "Alex")
    try saved.refresh(roster(event: event, id: "p1:replacement"))
    XCTAssertEqual(saved.selectedId, "p1:replacement")
    XCTAssertEqual(saved.selected?.roleUuid, "p1")
    XCTAssertThrowsError(try saved.refresh(roster(event: UUID().uuidString)))
    XCTAssertEqual(saved.roster.eventUuid, event)
  }

  func testConcertDefaultIsAppliedOnceAndManualRoleSurvivesRefresh() throws {
    let event = UUID().uuidString
    var list = roster(event: event)
    list.defaultRoleUuid = "p1"
    var saved = PhotographerSelection(server: "test", roster: list, selectedId: nil)
    try saved.refresh(list)
    XCTAssertEqual(saved.selected?.roleUuid, "p1")
    list.defaultRoleUuid = "another-role"
    try saved.refresh(list)
    XCTAssertEqual(saved.selected?.roleUuid, "p1")
    var station = PhotographerSelection(server: "test", roster: roster(event: event), selectedId: nil)
    try station.refresh(roster(event: event))
    XCTAssertNil(station.selected)
  }

  func testNewRoleRosterDecodesWithoutCrewIdentity() throws {
    let role = UUID().uuidString
    let data = try JSONSerialization.data(withJSONObject: [
      "event_uuid": UUID().uuidString, "checked_at": "2026-09-22T00:00:00Z",
      "has_shifts": true, "identity_version": 2, "default_role_uuid": role,
      "photographers": [["id": role, "role_uuid": role, "name": "P1",
        "role_name": "Photographer P1", "media_type": "photo", "shifts": []]], "unfilled": [],
    ])
    let list = try ProgrammeCoding.decoder().decode(PhotographerRoster.self, from: data)
    XCTAssertNil(list.photographers[0].crewUuid)
    XCTAssertEqual(list.defaultRoleUuid, role)
    XCTAssertFalse(list.photographers[0].isVideo)
  }

  func testOfflineSelectionIsScopedToEventAndServerAndProtectsSymlinks() throws {
    let root = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
    try FileManager.default.createDirectory(at: root, withIntermediateDirectories: false)
    defer { try? FileManager.default.removeItem(at: root) }
    let event = UUID().uuidString
    let base = manifest(event: event)
    let saved = PhotographerSelection(
      server: base.server, roster: roster(event: event), selectedId: "p1:crew")
    try saved.save(root: root)
    XCTAssertEqual(PhotographerSelection.read(root: root, manifest: base)?.selected?.name, "Alex")
    XCTAssertNil(
      PhotographerSelection.read(root: root, manifest: manifest(event: UUID().uuidString)))
    XCTAssertNil(
      PhotographerSelection.read(
        root: root, manifest: manifest(event: event, server: "https://other.test")))
    let url = root.appendingPathComponent("upload-photographer.json")
    try FileManager.default.removeItem(at: url)
    let target = root.appendingPathComponent("keep.txt")
    try Data("keep".utf8).write(to: target)
    try FileManager.default.createSymbolicLink(at: url, withDestinationURL: target)
    XCTAssertThrowsError(try saved.save(root: root))
    XCTAssertNil(PhotographerSelection.read(root: root, manifest: base))
    XCTAssertEqual(try String(contentsOf: target, encoding: .utf8), "keep")
  }

  func testWrongServerDoesNotReceiveRosterRequest() async throws {
    let client = try APIClient(server: "https://other.test", token: "test-only")
    do {
      _ = try await client.photographers(for: manifest(event: UUID().uuidString))
      XCTFail("Requested roster from another server")
    } catch { XCTAssertTrue(error is PreparationError) }
  }
}
