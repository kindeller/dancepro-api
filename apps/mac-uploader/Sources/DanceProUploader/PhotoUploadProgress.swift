import Foundation
import PreparationCore

struct UploadActorProgress: Decodable {
  let name: String
  let submissionCount: Int
  let duplicateCount: Int
}

struct PhotographerProgress: Decodable, Identifiable {
  let id: String
  let crewUuid: String?
  let roleUuid: String
  let name: String
  let roleName: String
  let status: String
  let expectedItemCount: Int
  let completeItemCount: Int
  var waivedItemCount: Int? = nil
  let verifiedFileCount: Int
  let pendingFileCount: Int
  var uploaders: [UploadActorProgress]? = nil

  var label: String {
    switch status {
    case "complete": return "Upload Complete"
    case "waived": return "Waived by admin"
    case "complete_with_exceptions": return "Complete with exceptions"
    case "receiving": return "In progress"
    case "missing": return "Not finished"
    case "no_items": return "No matching programme items"
    default: return "Programme needs attention"
    }
  }
}

struct PhotoUploadProgress: Decodable {
  let eventUuid: String
  let planUuid: String
  let checkedAt: String
  let planCurrent: Bool
  let visibility: String
  let photographers: [PhotographerProgress]
  let unfilled: [UnfilledPhotographer]

  func validate(receipt: ProgrammeSyncReceipt) throws {
    guard eventUuid.lowercased() == receipt.plan.eventUuid.lowercased(),
      planUuid.lowercased() == receipt.plan.uuid.lowercased(), visibility == "private",
      ISO8601DateFormatter().date(from: checkedAt) != nil,
      Set(photographers.map(\.id)).count == photographers.count,
      photographers.allSatisfy({
        UUID(uuidString: $0.roleUuid) != nil
          && ["complete", "complete_with_exceptions", "waived", "receiving", "missing", "no_items", "needs_attention"].contains($0.status)
          && $0.expectedItemCount >= 0 && $0.completeItemCount >= 0
          && ($0.waivedItemCount ?? 0) >= 0
          && $0.completeItemCount + ($0.waivedItemCount ?? 0) <= $0.expectedItemCount
          && ($0.status != "waived" || (planCurrent && $0.expectedItemCount > 0 && $0.waivedItemCount == $0.expectedItemCount))
          && ($0.status != "complete_with_exceptions" || (planCurrent && ($0.waivedItemCount ?? 0) > 0
            && $0.completeItemCount > 0 && $0.completeItemCount + ($0.waivedItemCount ?? 0) == $0.expectedItemCount))
          && $0.verifiedFileCount >= 0 && $0.pendingFileCount >= 0
          && ($0.status != "complete" || (planCurrent && $0.expectedItemCount > 0
            && $0.completeItemCount == $0.expectedItemCount && $0.pendingFileCount == 0))
      }), unfilled.allSatisfy({ $0.missingCount > 0 })
    else { throw PreparationError.invalid("The server progress does not match this event or contains invalid counts.") }
  }
}

extension APIClient {
  func photoProgress(manifest: PreparedEvent, receipt: ProgrammeSyncReceipt) async throws -> PhotoUploadProgress {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      receipt.matches(manifest), UUID(uuidString: receipt.plan.uuid) != nil else {
      throw PreparationError.invalid("Upload from this working programme before checking its server progress.")
    }
    let data = try await request(
      "/api/staff/events/\(manifest.programme.eventUuid)/programme/photo-progress?plan_uuid=\(receipt.plan.uuid)")
    let progress = try ProgrammeCoding.decoder().decode(APIEnvelope<PhotoUploadProgress>.self, from: data).data
    try progress.validate(receipt: receipt)
    return progress
  }
}
