import CryptoKit
import Foundation
import PreparationCore

struct ProgrammeSyncRequest: Encodable {
  let revisionUuid: String
  let nodes: [ProgrammeNode]
  var basePlanUuid: String? = nil
}

struct ProgrammeSyncResponse: Codable {
  let uuid: String
  let eventUuid: String
  let revisionUuid: String
  let digest: String
}

struct ProgrammeSyncReceipt: Codable {
  let server: String
  let localDigest: String
  let plan: ProgrammeSyncResponse
  let syncedAt: Date
  var photoContributions: PhotoContributionLinks? = nil
  var photoFiles: PhotoFileReservations? = nil
  var photoCompletion: PhotoCompletion? = nil

  static func fingerprint(_ programme: Programme) throws -> String {
    SHA256.hash(data: try ProgrammeCoding.encoder().encode(programme))
      .map { String(format: "%02x", $0) }.joined()
  }

  func matches(_ manifest: PreparedEvent) -> Bool {
    server == manifest.server
      && plan.eventUuid.lowercased() == manifest.programme.eventUuid.lowercased()
      && plan.revisionUuid.lowercased() == manifest.programme.revisionUuid.lowercased()
      && localDigest == (try? Self.fingerprint(manifest.programme))
  }

  static func read(root: URL) -> Self? {
    let url = root.appendingPathComponent("programme-sync.json")
    guard (try? url.resourceValues(forKeys: [.isSymbolicLinkKey]).isSymbolicLink) != true else {
      return nil
    }
    return try? ProgrammeCoding.decoder().decode(Self.self, from: Data(contentsOf: url))
  }

  func save(root: URL) throws {
    let url = root.appendingPathComponent("programme-sync.json")
    guard (try? url.resourceValues(forKeys: [.isSymbolicLinkKey]).isSymbolicLink) != true else {
      throw PreparationError.invalid("The sync receipt location is a symbolic link.")
    }
    try ProgrammeCoding.encoder().encode(self).write(to: url, options: .atomic)
  }
}

extension APIClient {
  func syncProgramme(_ manifest: PreparedEvent, basePlanUuid: String? = nil) async throws -> ProgrammeSyncReceipt {
    // Compare origins before sending any authenticated request for a local event.
    let preparedServer = try APIClient(server: manifest.server).base
    guard base.scheme == preparedServer.scheme, base.host == preparedServer.host,
      base.port == preparedServer.port, token != nil
    else {
      throw PreparationError.invalid(
        "Sign in to the server used to prepare this event before syncing.")
    }
    let programme = manifest.programme
    _ = try FolderPlan(programme: programme)
    let body = ProgrammeSyncRequest(revisionUuid: programme.revisionUuid, nodes: programme.nodes, basePlanUuid: basePlanUuid)
    let data = try await request(
      "/api/staff/events/\(programme.eventUuid)/programme/sync",
      jsonBody: ProgrammeCoding.encoder().encode(body))
    let response = try ProgrammeCoding.decoder().decode(
      APIEnvelope<ProgrammeSyncResponse>.self, from: data
    ).data
    guard UUID(uuidString: response.uuid) != nil,
      response.eventUuid.lowercased() == programme.eventUuid.lowercased(),
      response.revisionUuid.lowercased() == programme.revisionUuid.lowercased(),
      response.digest.range(of: "^[0-9a-f]{64}$", options: .regularExpression) != nil
    else {
      throw PreparationError.invalid("DancePro returned a receipt for a different programme.")
    }
    return ProgrammeSyncReceipt(
      server: manifest.server, localDigest: try ProgrammeSyncReceipt.fingerprint(programme),
      plan: response, syncedAt: Date())
  }
}
