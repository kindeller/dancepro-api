import XCTest

@testable import DanceProUploader
@testable import PreparationCore

final class ProgrammeSyncTests: XCTestCase {
  private func manifest() throws -> PreparedEvent {
    let programme = Programme(
      schemaVersion: 1, eventUuid: UUID().uuidString,
      eventCode: "TEST", eventName: "Sync test", eventType: "concert",
      programmeUuid: UUID().uuidString, revisionUuid: UUID().uuidString, version: 1,
      nodes: [
        ProgrammeNode(
          uuid: UUID().uuidString, kind: "item", title: "It's Jazz!",
          folderNumber: "01", folderTitle: "Its Jazz")
      ])
    return PreparedEvent(
      server: "https://dancepro.test", programme: programme,
      folders: try FolderPlan(programme: programme).folders, preparedAt: Date(), hasPdf: false)
  }

  private func replacingProgramme(_ base: PreparedEvent, _ programme: Programme) -> PreparedEvent {
    PreparedEvent(
      server: base.server, programme: programme, folders: base.folders,
      preparedAt: base.preparedAt, hasPdf: base.hasPdf)
  }

  private func receipt(_ manifest: PreparedEvent) throws -> ProgrammeSyncReceipt {
    ProgrammeSyncReceipt(
      server: manifest.server,
      localDigest: try ProgrammeSyncReceipt.fingerprint(manifest.programme),
      plan: ProgrammeSyncResponse(
        uuid: UUID().uuidString,
        eventUuid: manifest.programme.eventUuid.lowercased(),
        revisionUuid: manifest.programme.revisionUuid.lowercased(),
        digest: String(repeating: "a", count: 64)),
      syncedAt: Date())
  }

  func testReceiptBecomesStaleAfterRenameSplitCombineOrRevisionChange() throws {
    let base = try manifest()
    let synced = try receipt(base)
    XCTAssertTrue(synced.matches(base))
    var changed = base.programme
    changed.nodes[0].title = "New title"
    XCTAssertFalse(synced.matches(replacingProgramme(base, changed)))
    changed = base.programme
    changed.nodes.append(ProgrammeNode(uuid: UUID().uuidString, kind: "item", title: "Split"))
    XCTAssertFalse(synced.matches(replacingProgramme(base, changed)))
    changed = base.programme
    changed.nodes[0].mergedIntoUuid = UUID().uuidString
    XCTAssertFalse(synced.matches(replacingProgramme(base, changed)))
    changed = base.programme
    changed.revisionUuid = UUID().uuidString
    XCTAssertFalse(synced.matches(replacingProgramme(base, changed)))
  }

  func testReceiptRoundTripAndSymlinkProtection() throws {
    let root = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
    try FileManager.default.createDirectory(at: root, withIntermediateDirectories: false)
    defer { try? FileManager.default.removeItem(at: root) }
    let base = try manifest()
    let synced = try receipt(base)
    try synced.save(root: root)
    XCTAssertTrue(try XCTUnwrap(ProgrammeSyncReceipt.read(root: root)).matches(base))
    let path = root.appendingPathComponent("programme-sync.json")
    try FileManager.default.removeItem(at: path)
    let target = root.appendingPathComponent("original.txt")
    try Data("keep".utf8).write(to: target)
    try FileManager.default.createSymbolicLink(at: path, withDestinationURL: target)
    XCTAssertThrowsError(try synced.save(root: root))
    XCTAssertNil(ProgrammeSyncReceipt.read(root: root))
    XCTAssertEqual(try String(contentsOf: target, encoding: .utf8), "keep")
  }

  func testSyncPayloadCarriesAliasesAndPunctuationWithoutLocalPaths() throws {
    let base = try manifest()
    var programme = base.programme
    programme.nodes[0].mergedIntoUuid = UUID().uuidString
    let data = try ProgrammeCoding.encoder().encode(
      ProgrammeSyncRequest(
        revisionUuid: base.programme.revisionUuid, nodes: programme.nodes))
    let json = try XCTUnwrap(JSONSerialization.jsonObject(with: data) as? [String: Any])
    XCTAssertEqual(Set(json.keys), ["revision_uuid", "nodes"])
    let node = try XCTUnwrap((json["nodes"] as? [[String: Any]])?.first)
    XCTAssertEqual(node["title"] as? String, "It's Jazz!")
    XCTAssertEqual(node["merged_into_uuid"] as? String, programme.nodes[0].mergedIntoUuid)
  }

  func testOtherServerAndUnsignedSessionAreRejectedBeforeNetworkRequest() async throws {
    let base = try manifest()
    for client in [
      try APIClient(server: "https://other.test", token: "test-only"),
      try APIClient(server: base.server),
    ] {
      do {
        _ = try await client.syncProgramme(base)
        XCTFail("An unrelated or unsigned server should never receive this event")
      } catch { XCTAssertTrue(error is PreparationError) }
    }
  }
}
