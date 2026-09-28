import Foundation
import PhotoReviewCore
import PreparationCore

private struct TopPicInput: Codable, Equatable {
  let fileUuid: String
  let changeUuid: String
  let isTopPic: Bool

  init(_ change: TopPicChange) {
    fileUuid = change.fileUuid.lowercased()
    changeUuid = change.changeUuid.lowercased()
    isTopPic = change.isTopPic
  }
}
private struct TopPicRequest: Encodable { let changes: [TopPicInput] }
private struct TopPicReceipt: Decodable {
  let eventUuid: String
  let changes: [TopPicInput]
}

extension APIClient {
  func syncTopPics(manifest: PreparedEvent, changes: [TopPicChange]) async throws {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      token != nil, !changes.isEmpty, changes.count <= 100 else {
      throw PreparationError.invalid("Sign in to this event's server to sync Top Pics.")
    }
    let inputs = changes.map(TopPicInput.init)
    let data = try await request(
      "/api/staff/events/\(manifest.programme.eventUuid)/programme/top-pics",
      jsonBody: ProgrammeCoding.encoder().encode(TopPicRequest(changes: inputs)))
    let receipt = try ProgrammeCoding.decoder().decode(APIEnvelope<TopPicReceipt>.self, from: data).data
    guard receipt.eventUuid.lowercased() == manifest.programme.eventUuid.lowercased(),
      receipt.changes == inputs else {
      throw PreparationError.invalid("Top Pic confirmation did not match. Changes remain queued for retry.")
    }
  }
}
