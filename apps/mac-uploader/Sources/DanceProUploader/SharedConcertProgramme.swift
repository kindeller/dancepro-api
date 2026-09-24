import Foundation
import PreparationCore

struct SharedConcertProgramme: Decodable {
  let eventUuid: String
  let revisionUuid: String
  let plan: ProgrammeSyncResponse?
  let programme: Programme?

  func localProgramme(for manifest: PreparedEvent) throws -> Programme? {
    guard eventUuid.lowercased() == manifest.programme.eventUuid.lowercased(),
      revisionUuid.lowercased() == manifest.programme.revisionUuid.lowercased() else {
      throw PreparationError.invalid("A different approved programme is available. Prepare that version separately; your photos are kept.")
    }
    guard let plan, let programme else {
      guard plan == nil && programme == nil else {
        throw PreparationError.invalid("The shared programme response is incomplete.")
      }
      return nil
    }
    guard UUID(uuidString: plan.uuid) != nil,
      plan.eventUuid.lowercased() == eventUuid.lowercased(),
      plan.revisionUuid.lowercased() == revisionUuid.lowercased(),
      plan.digest.range(of: "^[0-9a-f]{64}$", options: .regularExpression) != nil,
      programme.eventType == "concert", manifest.programme.eventType == "concert",
      programme.eventUuid.lowercased() == eventUuid.lowercased(),
      programme.revisionUuid.lowercased() == revisionUuid.lowercased(),
      programme.programmeUuid.lowercased() == manifest.programme.programmeUuid.lowercased()
    else { throw PreparationError.invalid("The shared programme does not match this concert.") }
    var local = manifest.programme
    let localIDs = Dictionary(uniqueKeysWithValues: manifest.programme.nodes.map { ($0.uuid.lowercased(), $0.uuid) })
    local.nodes = programme.nodes.map { value in
      var node = value
      node.uuid = localIDs[node.uuid.lowercased()] ?? node.uuid.lowercased()
      node.parentUuid = node.parentUuid.map { localIDs[$0.lowercased()] ?? $0.lowercased() }
      node.mergedIntoUuid = node.mergedIntoUuid.map { localIDs[$0.lowercased()] ?? $0.lowercased() }
      return node
    }
    _ = try FolderPlan(programme: local)
    let normalized = try ConcertLayout.normalized(local)
    guard try Self.sameLayout(local, normalized) else {
      throw PreparationError.invalid("The shared concert numbering is invalid.")
    }
    return local
  }

  static func sameLayout(_ lhs: Programme, _ rhs: Programme) throws -> Bool {
    try ProgrammeCoding.encoder().encode(lhs.nodes) == ProgrammeCoding.encoder().encode(rhs.nodes)
  }
}

extension APIClient {
  func sharedConcertProgramme(_ manifest: PreparedEvent) async throws -> SharedConcertProgramme {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port else {
      throw PreparationError.invalid("Sign in to this event's server before checking its shared programme.")
    }
    let data = try await request("/api/staff/events/\(manifest.programme.eventUuid)/programme/shared")
    let shared = try ProgrammeCoding.decoder().decode(APIEnvelope<SharedConcertProgramme>.self, from: data).data
    _ = try shared.localProgramme(for: manifest)
    return shared
  }
}
