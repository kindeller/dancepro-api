import CryptoKit
import XCTest
@testable import DanceProUploader

final class TitleCardDownloadsTests: XCTestCase {
  private let event = UUID().uuidString
  private let revision = UUID().uuidString
  private let package = UUID().uuidString

  func testDownloadsVerifiedPackageAndRejectsWrongRevisionOrDamagedBytes() async throws {
    let config = URLSessionConfiguration.ephemeral
    config.protocolClasses = [PhotoUploadProtocol.self]
    let client = try APIClient(server: "https://uploads.test", token: "test-token", configuration: config)
    let base = "/api/staff/events/\(event)/programme/title-cards"
    let bytes = Data("PK test title package".utf8)
    let hash = SHA256.hash(data: bytes).map { String(format: "%02x", $0) }.joined()
    let valid: [String: Any] = ["ready": true, "event_uuid": event, "revision_uuid": revision,
      "package_uuid": package, "size_bytes": bytes.count, "sha256": hash,
      "download_url": base + "/" + package]
    func configure(_ metadata: [String: Any], download: Data) throws {
      PhotoUploadProtocol.state.configure([
        base: try JSONSerialization.data(withJSONObject: ["data": metadata]),
        base + "/" + package: download])
    }
    try configure(valid, download: bytes)
    let downloaded = try await TitleCardDownloads.download(client: client, eventUuid: event, revisionUuid: revision)
    XCTAssertEqual(downloaded, bytes)
    for field in ["revision_uuid", "event_uuid", "download_url", "sha256", "size_bytes", "ready"] {
      var bad = valid
      switch field {
      case "size_bytes": bad[field] = 500 * 1024 * 1024
      case "ready": bad[field] = false
      default: bad[field] = "wrong"
      }
      try configure(bad, download: bytes)
      do {
        _ = try await TitleCardDownloads.download(client: client, eventUuid: event, revisionUuid: revision)
        XCTFail("Accepted invalid \(field)")
      } catch {}
      XCTAssertEqual(PhotoUploadProtocol.state.paths(), [base])
    }
    try configure(valid, download: Data("damaged".utf8))
    do {
      _ = try await TitleCardDownloads.download(client: client, eventUuid: event, revisionUuid: revision)
      XCTFail("Accepted damaged download")
    } catch {}
  }
}
