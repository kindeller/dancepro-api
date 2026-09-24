import Foundation
import PhotoReviewCore
import PreparationCore

struct VerifiedPhotoReceipt: Decodable {
  let uuid: String
  let photoUuid: String
  let eventUuid: String
  let planUuid: String
  let galleryUuid: String
  let itemUuid: String
  let contributionUuid: String
  let sha256: String
  let byteCount: Int64
  let status: String
  let visibility: String

  func validate(reservation: PhotoFileReservation, receipt: ProgrammeSyncReceipt) throws {
    guard uuid.lowercased() == reservation.uuid.lowercased(),
      photoUuid.lowercased() == reservation.photoUuid.lowercased(),
      eventUuid.lowercased() == receipt.plan.eventUuid.lowercased(),
      planUuid.lowercased() == receipt.plan.uuid.lowercased(), UUID(uuidString: galleryUuid) != nil,
      itemUuid.lowercased() == reservation.itemUuid.lowercased(),
      contributionUuid.lowercased() == reservation.contributionUuid.lowercased(),
      sha256 == reservation.sha256, byteCount == reservation.byteCount,
      status == "verified", visibility == "private"
    else {
      throw PreparationError.invalid(
        "The upload receipt does not match this photo. It remains queued for retry.")
    }
  }
}

extension APIClient {
  func transferPhoto(
    manifest: PreparedEvent, receipt: ProgrammeSyncReceipt,
    reservation: PhotoFileReservation, stagedFile: URL
  ) async throws -> VerifiedPhotoReceipt {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      receipt.matches(manifest), UUID(uuidString: reservation.uuid) != nil
    else {
      throw PreparationError.invalid("The upload destination does not match this prepared event.")
    }
    let data = try await upload(
      "/api/staff/events/\(manifest.programme.eventUuid)/programme/photo-files/\(reservation.uuid)/content",
      from: stagedFile)
    let verified = try ProgrammeCoding.decoder().decode(
      APIEnvelope<VerifiedPhotoReceipt>.self, from: data
    ).data
    try verified.validate(reservation: reservation, receipt: receipt)
    return verified
  }
}

enum PhotoUploadRetry {
  static func isTransient(_ error: Error) -> Bool {
    if let error = error as? URLError {
      return [
        .timedOut, .cannotFindHost, .cannotConnectToHost, .networkConnectionLost,
        .dnsLookupFailed, .notConnectedToInternet, .resourceUnavailable,
      ].contains(error.code)
    }
    if case APIError.server(let status, _) = error { return [429, 500, 502, 504].contains(status) }
    return false
  }
}
