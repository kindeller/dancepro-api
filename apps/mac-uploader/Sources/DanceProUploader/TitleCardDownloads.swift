import CryptoKit
import Foundation
import PreparationCore

struct TitleCardPackage: Decodable {
  let ready: Bool
  let eventUuid: String
  let packageUuid: String?
  let revisionUuid: String?
  let sizeBytes: Int?
  let sha256: String?
  let downloadUrl: String?
}

struct TitleCardDownloads {
  static func download(client: APIClient, eventUuid: String, revisionUuid: String) async throws -> Data {
    let base = "/api/staff/events/\(eventUuid)/programme/title-cards"
    let package = try await client.decode(TitleCardPackage.self, path: base).data
    guard package.ready else {
      throw PreparationError.invalid(
        "Video titles are not ready. Open 2.3 Video Titles in DancePro and click Video titles ready.")
    }
    guard package.eventUuid == eventUuid, package.revisionUuid == revisionUuid else {
      throw PreparationError.invalid(
        "These titles use a different programme version. Refresh the event programme before downloading.")
    }
    guard let uuid = package.packageUuid, UUID(uuidString: uuid) != nil,
      package.downloadUrl == base + "/" + uuid,
      let size = package.sizeBytes, size > 0, size <= 200 * 1024 * 1024,
      let checksum = package.sha256, checksum.count == 64,
      checksum.allSatisfy({ $0.isHexDigit })
    else { throw PreparationError.invalid("DancePro returned an invalid title package.") }
    let bytes = try await client.request(base + "/" + uuid)
    let actual = SHA256.hash(data: bytes).map { String(format: "%02x", $0) }.joined()
    guard bytes.count == size, actual == checksum.lowercased() else {
      throw PreparationError.invalid("The title download is incomplete or damaged. Please try again.")
    }
    return bytes
  }
}
