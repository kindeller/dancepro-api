import Foundation
import PreparationCore

struct PhotoContributionLink: Codable {
  let uuid: String
  let itemUuid: String
  let status: String
}

struct PhotoContributionLinks: Codable {
  let eventUuid: String
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
  let contributions: [PhotoContributionLink]

  func validate(
    receipt: ProgrammeSyncReceipt, photographer: EventPhotographer, itemIDs: Set<String>
  ) throws {
    let ids = contributions.map { $0.itemUuid.lowercased() }
    guard eventUuid.lowercased() == receipt.plan.eventUuid.lowercased(),
      planUuid.lowercased() == receipt.plan.uuid.lowercased(),
      roleUuid.lowercased() == photographer.roleUuid.lowercased(),
      ids.count == Set(ids).count, Set(ids) == Set(itemIDs.map { $0.lowercased() }),
      Set(contributions.map { $0.uuid.lowercased() }).count == contributions.count,
      contributions.allSatisfy({
        UUID(uuidString: $0.uuid) != nil && ["awaiting_files", "receiving", "complete", "waived"].contains($0.status)
      }
      )
    else {
      throw PreparationError.invalid(
        "DancePro returned contribution records for a different queue or photographer.")
    }
  }
}

struct PhotoContributionRequest: Encodable {
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
  let itemUuids: [String]
}

extension APIClient {
  func registerContributions(
    manifest: PreparedEvent, receipt: ProgrammeSyncReceipt,
    photographer: EventPhotographer, itemIDs: Set<String>
  ) async throws -> PhotoContributionLinks {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      token != nil, receipt.matches(manifest), UUID(uuidString: receipt.plan.uuid) != nil,
      UUID(uuidString: photographer.roleUuid) != nil,
      !itemIDs.isEmpty,
      itemIDs.isSubset(of: Set(manifest.programme.nodes.filter(\.isActiveItem).map(\.uuid)))
    else {
      throw PreparationError.invalid(
        "Sync this programme and choose its photographer before connecting queued items.")
    }
    let body = PhotoContributionRequest(
      planUuid: receipt.plan.uuid, crewUuid: photographer.crewUuid,
      roleUuid: photographer.roleUuid, itemUuids: itemIDs.sorted())
    let data = try await request(
      "/api/staff/events/\(manifest.programme.eventUuid)/programme/photo-contributions",
      jsonBody: ProgrammeCoding.encoder().encode(body))
    let result = try ProgrammeCoding.decoder().decode(
      APIEnvelope<PhotoContributionLinks>.self, from: data
    ).data
    try result.validate(receipt: receipt, photographer: photographer, itemIDs: itemIDs)
    return result
  }
}
