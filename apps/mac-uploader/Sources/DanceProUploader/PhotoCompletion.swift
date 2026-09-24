import Foundation
import PreparationCore

struct PhotoCompletion: Codable {
  let eventUuid: String
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
  let status: String
  let contributionCount: Int
  let verifiedFileCount: Int
  let visibility: String
  let completedAt: String
  var waivedItemCount: Int? = nil

  func matches(receipt: ProgrammeSyncReceipt, photographer: EventPhotographer) -> Bool {
    eventUuid.lowercased() == receipt.plan.eventUuid.lowercased()
      && planUuid.lowercased() == receipt.plan.uuid.lowercased()
      && roleUuid.lowercased() == photographer.roleUuid.lowercased()
      && status == "complete" && visibility == "private"
      && (waivedItemCount ?? 0) >= 0 && (waivedItemCount ?? 0) <= contributionCount
      && contributionCount > 0 && verifiedFileCount >= 0
      && ISO8601DateFormatter().date(from: completedAt) != nil
  }
}

private struct PhotoCompletionRequest: Encodable {
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
}

extension APIClient {
  func finishPhotoUploads(
    manifest: PreparedEvent, receipt: ProgrammeSyncReceipt, photographer: EventPhotographer
  ) async throws -> PhotoCompletion {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      receipt.matches(manifest), UUID(uuidString: receipt.plan.uuid) != nil,
      UUID(uuidString: photographer.roleUuid) != nil
    else { throw PreparationError.invalid("Sync this event and choose its photographer before finishing.") }
    let body = PhotoCompletionRequest(
      planUuid: receipt.plan.uuid, crewUuid: photographer.crewUuid, roleUuid: photographer.roleUuid)
    let data = try await request(
      "/api/staff/events/\(manifest.programme.eventUuid)/programme/photo-completion",
      jsonBody: ProgrammeCoding.encoder().encode(body))
    let result = try ProgrammeCoding.decoder().decode(APIEnvelope<PhotoCompletion>.self, from: data).data
    guard result.matches(receipt: receipt, photographer: photographer) else {
      throw PreparationError.invalid("The completion receipt does not match this photographer and event. Retry to confirm.")
    }
    return result
  }
}
