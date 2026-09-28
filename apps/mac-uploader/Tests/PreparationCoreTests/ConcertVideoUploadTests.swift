import CryptoKit
import XCTest
@testable import DanceProUploader

@MainActor
final class ConcertVideoUploadTests: XCTestCase {
  func testResumeVerifiedQueueRequiresExplicitFinishAndRemembersCompletion() async throws {
    let context = try fixture()
    defer { clean(context) }
    let base = "/api/staff/events/\(context.event)/concert-videos/batches"
    let summary: [String: Any] = ["batch_uuid": context.batch, "queued": 1, "verified": 1, "completed_at": NSNull()]
    PhotoUploadProtocol.state.configure([
      base: try envelope(summary),
      "\(base)/\(context.batch)/files/\(context.file)/address": try envelope(["verified": true, "upload": NSNull()]),
      "\(base)/\(context.batch)/complete": try envelope(["completed_at": "2026-09-27T12:00:00Z"])
    ])
    let model = ConcertVideoUploadModel(eventUuid: context.event)
    model.source = context.root; model.scan()
    await model.upload(client: try client())
    XCTAssertNil(model.error)
    XCTAssertTrue(model.canFinish)
    XCTAssertFalse(PhotoUploadProtocol.state.paths().contains { $0.hasSuffix("/complete") })
    await model.finish(client: try client())
    XCTAssertNil(model.error)
    XCTAssertFalse(model.canFinish)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().contains { $0.hasSuffix("/complete") })
  }

  func testChangingOrAddingAFileAfterUploadBlocksFinishedUploading() async throws {
    let context = try fixture()
    defer { clean(context) }
    let base = "/api/staff/events/\(context.event)/concert-videos/batches"
    PhotoUploadProtocol.state.configure([
      base: try envelope(["batch_uuid": context.batch, "queued": 1, "verified": 1, "completed_at": NSNull()]),
      "\(base)/\(context.batch)/files/\(context.file)/address": try envelope(["verified": true, "upload": NSNull()])
    ])
    let model = ConcertVideoUploadModel(eventUuid: context.event)
    model.source = context.root; model.scan()
    await model.upload(client: try client())
    XCTAssertTrue(model.canFinish)
    try Data("later slideshow".utf8).write(to: context.root.appendingPathComponent("Opening.mp4"))
    await model.finish(client: try client())
    XCTAssertFalse(model.canFinish)
    XCTAssertNotNil(model.error)
    XCTAssertFalse(PhotoUploadProtocol.state.paths().contains { $0.hasSuffix("/complete") })
  }

  func testRecentlyChangingExportDoesNotRegisterAnIncompleteQueue() async throws {
    let context = try fixture()
    defer { clean(context) }
    try FileManager.default.setAttributes([.modificationDate: Date()], ofItemAtPath: context.root.appendingPathComponent("Concert.mp4").path)
    PhotoUploadProtocol.state.configure([:])
    let model = ConcertVideoUploadModel(eventUuid: context.event)
    model.source = context.root; model.scan()
    await model.upload(client: try client())
    XCTAssertNotNil(model.error)
    XCTAssertFalse(model.canFinish)
    XCTAssertTrue(PhotoUploadProtocol.state.paths().isEmpty)
  }

  private struct Context { let root: URL; let event: String; let batch: String; let file: String }
  private func fixture() throws -> Context {
    let context = Context(root: FileManager.default.temporaryDirectory.resolvingSymlinksInPath().appendingPathComponent(UUID().uuidString),
      event: UUID().uuidString.lowercased(), batch: UUID().uuidString.lowercased(), file: UUID().uuidString.lowercased())
    try FileManager.default.createDirectory(at: context.root, withIntermediateDirectories: true)
    let url = context.root.appendingPathComponent("Concert.mp4")
    let bytes = Data("concert video".utf8); try bytes.write(to: url)
    try FileManager.default.setAttributes([.modificationDate: Date(timeIntervalSinceNow: -300)], ofItemAtPath: url.path)
    let modified = try url.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate!
    let hash = SHA256.hash(data: bytes).map { String(format: "%02x", $0) }.joined()
    let json: [String: Any] = ["id": context.batch, "files": [["id": context.file, "path": url.path,
      "name": "Concert.mp4", "size": bytes.count, "modified": modified.timeIntervalSinceReferenceDate, "checksum": hash]]]
    UserDefaults.standard.set(try JSONSerialization.data(withJSONObject: json), forKey: "concert-video-queue-\(context.event)")
    return context
  }
  private func clean(_ context: Context) {
    UserDefaults.standard.removeObject(forKey: "concert-video-queue-\(context.event)")
    try? FileManager.default.removeItem(at: context.root)
  }
  private func envelope(_ object: [String: Any]) throws -> Data { try JSONSerialization.data(withJSONObject: ["data": object]) }
  private func client() throws -> APIClient {
    let config = URLSessionConfiguration.ephemeral; config.protocolClasses = [PhotoUploadProtocol.self]
    return try APIClient(server: "https://uploads.test", token: "test-token", configuration: config)
  }
}
