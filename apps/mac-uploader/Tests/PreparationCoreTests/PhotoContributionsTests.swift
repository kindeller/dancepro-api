import XCTest

@testable import DanceProUploader
@testable import PhotoReviewCore
@testable import PreparationCore

final class PhotoContributionsTests: XCTestCase {
  private func fixture() throws -> (
    PreparedEvent, ProgrammeSyncReceipt, EventPhotographer, PhotoContributionLinks
  ) {
    let item = ProgrammeNode(
      uuid: UUID().uuidString, kind: "item", title: "Dance", folderNumber: "01",
      folderTitle: "Dance")
    let programme = Programme(
      schemaVersion: 1, eventUuid: UUID().uuidString, eventName: "Test",
      eventType: "concert", programmeUuid: UUID().uuidString, revisionUuid: UUID().uuidString,
      version: 1, nodes: [item])
    let manifest = PreparedEvent(
      server: "https://dancepro.test", programme: programme,
      folders: try FolderPlan(programme: programme).folders, preparedAt: Date(), hasPdf: false)
    let receipt = ProgrammeSyncReceipt(
      server: manifest.server,
      localDigest: try ProgrammeSyncReceipt.fingerprint(programme),
      plan: ProgrammeSyncResponse(
        uuid: UUID().uuidString, eventUuid: programme.eventUuid,
        revisionUuid: programme.revisionUuid, digest: String(repeating: "a", count: 64)),
      syncedAt: Date())
    let person = EventPhotographer(
      id: "selection", crewUuid: UUID().uuidString, roleUuid: UUID().uuidString,
      name: "Alex", roleName: "P1", shifts: [])
    let links = PhotoContributionLinks(
      eventUuid: programme.eventUuid, planUuid: receipt.plan.uuid,
      crewUuid: person.crewUuid, roleUuid: person.roleUuid,
      contributions: [
        PhotoContributionLink(
          uuid: UUID().uuidString, itemUuid: item.uuid.lowercased(), status: "awaiting_files")
      ])
    return (manifest, receipt, person, links)
  }

  func testFileReservationsBindExactPhotoBytesAndContribution() throws {
    let (manifest, receipt, _, links) = try fixture()
    let photo = QueuedUploadPhoto(
      photoUuid: UUID().uuidString, itemUuid: manifest.programme.nodes[0].uuid,
      fileName: "Photo.jpg", byteCount: 100, sha256: String(repeating: "b", count: 64),
      isTopPic: true)
    func response(hash: String, size: Int64, contribution: String, status: String = "reserved")
      -> PhotoFileReservations
    {
      PhotoFileReservations(
        eventUuid: links.eventUuid, planUuid: links.planUuid,
        crewUuid: links.crewUuid, roleUuid: links.roleUuid,
        files: [
          PhotoFileReservation(
            uuid: UUID().uuidString, photoUuid: photo.photoUuid,
            itemUuid: photo.itemUuid, contributionUuid: contribution, sha256: hash,
            byteCount: size, status: status)
        ])
    }
    let valid = response(
      hash: photo.sha256, size: photo.byteCount, contribution: links.contributions[0].uuid)
    try valid.validate(links: links, photos: [photo])
    for bad in [
      response(hash: "wrong", size: 100, contribution: links.contributions[0].uuid),
      response(hash: photo.sha256, size: 101, contribution: links.contributions[0].uuid),
      response(hash: photo.sha256, size: 100, contribution: UUID().uuidString),
      response(
        hash: photo.sha256, size: 100, contribution: links.contributions[0].uuid, status: "unknown"
      ),
    ] {
      XCTAssertThrowsError(try bad.validate(links: links, photos: [photo]))
    }
    var saved = receipt
    saved.photoContributions = links
    saved.photoFiles = valid
    let decoded = try ProgrammeCoding.decoder().decode(
      ProgrammeSyncReceipt.self,
      from: ProgrammeCoding.encoder().encode(saved))
    try XCTUnwrap(decoded.photoFiles).validate(links: links, photos: [photo])
  }

  func testContributionReceiptRoundTripPreservesIDsWithoutTreatingRegistrationAsUpload() throws {
    let (manifest, receipt, person, links) = try fixture()
    let items = Set(manifest.programme.nodes.map(\.uuid))
    try links.validate(receipt: receipt, photographer: person, itemIDs: items)
    var saved = receipt
    saved.photoContributions = links
    let data = try ProgrammeCoding.encoder().encode(saved)
    let decoded = try ProgrammeCoding.decoder().decode(ProgrammeSyncReceipt.self, from: data)
    try XCTUnwrap(decoded.photoContributions).validate(
      receipt: receipt, photographer: person, itemIDs: items)
    XCTAssertEqual(decoded.photoContributions?.contributions[0].status, "awaiting_files")
    let oldData = try ProgrammeCoding.encoder().encode(receipt)
    XCTAssertNil(
      try ProgrammeCoding.decoder().decode(ProgrammeSyncReceipt.self, from: oldData)
        .photoContributions)
  }

  func testMissingDuplicateOrWrongPersonResponsesCannotBeAccepted() throws {
    let (manifest, receipt, person, links) = try fixture()
    let items = Set(manifest.programme.nodes.map(\.uuid))
    for entries in [
      [], links.contributions + links.contributions,
      [
        PhotoContributionLink(
          uuid: UUID().uuidString, itemUuid: UUID().uuidString, status: "awaiting_files")
      ],
      [PhotoContributionLink(uuid: UUID().uuidString, itemUuid: items.first!, status: "unknown")],
    ] {
      let invalid = PhotoContributionLinks(
        eventUuid: links.eventUuid, planUuid: links.planUuid,
        crewUuid: links.crewUuid, roleUuid: links.roleUuid, contributions: entries)
      XCTAssertThrowsError(
        try invalid.validate(receipt: receipt, photographer: person, itemIDs: items))
    }
    let invalid = PhotoContributionLinks(
      eventUuid: links.eventUuid, planUuid: links.planUuid,
      crewUuid: links.crewUuid, roleUuid: UUID().uuidString, contributions: links.contributions)
    XCTAssertThrowsError(
      try invalid.validate(receipt: receipt, photographer: person, itemIDs: items))
  }

  func testRegistrationRejectsOtherOriginAndStaleProgrammeBeforeNetworking() async throws {
    let (manifest, receipt, person, _) = try fixture()
    let items = Set(manifest.programme.nodes.map(\.uuid))
    do {
      _ = try await APIClient(server: "https://other.test", token: "test-only")
        .registerContributions(
          manifest: manifest, receipt: receipt, photographer: person, itemIDs: items)
      XCTFail("Sent contribution details to a different server")
    } catch { XCTAssertTrue(error is PreparationError) }
    var programme = manifest.programme
    programme.nodes[0].title = "Changed"
    let changed = PreparedEvent(
      server: manifest.server, programme: programme, folders: manifest.folders,
      preparedAt: manifest.preparedAt, hasPdf: false)
    do {
      _ = try await APIClient(server: manifest.server, token: "test-only").registerContributions(
        manifest: changed, receipt: receipt, photographer: person, itemIDs: items)
      XCTFail("Accepted stale programme receipt")
    } catch { XCTAssertTrue(error is PreparationError) }
  }
}
