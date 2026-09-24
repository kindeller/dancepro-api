import Foundation
import PhotoReviewCore
import PreparationCore

struct PhotoFileReservation: Codable {
  let uuid: String
  let photoUuid: String
  let itemUuid: String
  let contributionUuid: String
  let sha256: String
  let byteCount: Int64
  let status: String
}
struct PhotoFileReservations: Codable {
  let eventUuid: String
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
  let files: [PhotoFileReservation]

  func validate(links: PhotoContributionLinks, photos: [QueuedUploadPhoto]) throws {
    let expected = Dictionary(uniqueKeysWithValues: photos.map { ($0.photoUuid.lowercased(), $0) })
    let contributions = Dictionary(
      uniqueKeysWithValues: links.contributions.map {
        ($0.itemUuid.lowercased(), $0.uuid.lowercased())
      })
    guard eventUuid.lowercased() == links.eventUuid.lowercased(),
      planUuid.lowercased() == links.planUuid.lowercased(),
      roleUuid.lowercased() == links.roleUuid.lowercased(),
      files.count == photos.count,
      Set(files.map { $0.photoUuid.lowercased() }).count == files.count,
      Set(files.map { $0.uuid.lowercased() }).count == files.count,
      files.allSatisfy({ file in
        guard let photo = expected[file.photoUuid.lowercased()] else { return false }
        return UUID(uuidString: file.uuid) != nil
          && file.itemUuid.lowercased() == photo.itemUuid.lowercased()
          && file.contributionUuid.lowercased() == contributions[file.itemUuid.lowercased()]
          && file.sha256 == photo.sha256 && file.byteCount == photo.byteCount
          && ["reserved", "verified"].contains(file.status)
      })
    else {
      throw PreparationError.invalid(
        "DancePro returned reservations for different photos. No photos have been marked uploaded.")
    }
  }
}
private struct PhotoFileRequest: Encodable {
  let planUuid: String
  let crewUuid: String?
  let roleUuid: String
  let photos: [PhotoFileInput]
}
private struct PhotoFileInput: Encodable {
  let photoUuid: String
  let itemUuid: String
  let fileName: String
  let byteCount: Int64
  let sha256: String
  let isTopPic: Bool
  init(_ photo: QueuedUploadPhoto) {
    photoUuid = photo.photoUuid
    itemUuid = photo.itemUuid
    fileName = photo.fileName
    byteCount = photo.byteCount
    sha256 = photo.sha256
    isTopPic = photo.isTopPic
  }
}

extension APIClient {
  func reservePhotos(
    manifest: PreparedEvent, receipt: ProgrammeSyncReceipt,
    photographer: EventPhotographer, photos: [QueuedUploadPhoto]
  ) async throws -> PhotoFileReservations {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      token != nil, receipt.matches(manifest), let links = receipt.photoContributions,
      !photos.isEmpty, Set(photos.map { $0.photoUuid.lowercased() }).count == photos.count
    else {
      throw PreparationError.invalid(
        "Sync the programme and photographer before preparing photo uploads.")
    }
    try links.validate(
      receipt: receipt, photographer: photographer, itemIDs: Set(photos.map(\.itemUuid)))
    var reserved: [PhotoFileReservation] = []
    for start in stride(from: 0, to: photos.count, by: 500) {
      try Task.checkCancellation()
      let batch = Array(photos[start..<min(start + 500, photos.count)])
      let body = PhotoFileRequest(
        planUuid: receipt.plan.uuid, crewUuid: photographer.crewUuid,
        roleUuid: photographer.roleUuid, photos: batch.map(PhotoFileInput.init))
      let data = try await request(
        "/api/staff/events/\(manifest.programme.eventUuid)/programme/photo-files",
        jsonBody: ProgrammeCoding.encoder().encode(body))
      let response = try ProgrammeCoding.decoder().decode(
        APIEnvelope<PhotoFileReservations>.self, from: data
      ).data
      try response.validate(links: links, photos: batch)
      reserved += response.files
    }
    let result = PhotoFileReservations(
      eventUuid: links.eventUuid, planUuid: links.planUuid,
      crewUuid: links.crewUuid, roleUuid: links.roleUuid, files: reserved)
    try result.validate(links: links, photos: photos)
    return result
  }
}
