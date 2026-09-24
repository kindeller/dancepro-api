import Foundation
import PreparationCore

struct CompetitionRoleFolder: Decodable, Identifiable {
  let roleUuid: String
  let roleName: String
  let status: String
  let names: [String]
  let checkedAt: String?
  var id: String { roleUuid }
  var label: String {
    switch status {
    case "matched": return "Folder seen"
    case "name_difference": return "Name differs: " + names.joined(separator: ", ")
    case "not_seen": return "Not seen on reported stations"
    default: return "Not checked yet"
    }
  }
}
struct CompetitionFolderRow: Decodable, Identifiable {
  let uuid: String
  let kind: String
  let date: String
  let sectionNumber: String
  let number: String
  let title: String
  let roles: [CompetitionRoleFolder]
  var id: String { uuid }
  var differs: Bool { roles.contains { ["name_difference", "not_seen"].contains($0.status) } }
}
struct CompetitionFolderProgress: Decodable {
  let eventUuid: String
  let revisionUuid: String
  let checkedAt: String
  let items: [CompetitionFolderRow]
  var hasDifferences: Bool { items.contains(where: \.differs) }
  func validate(_ manifest: PreparedEvent) throws {
    guard eventUuid.lowercased() == manifest.programme.eventUuid.lowercased(),
      revisionUuid.lowercased() == manifest.programme.revisionUuid.lowercased(),
      ISO8601DateFormatter().date(from: checkedAt) != nil,
      Set(items.map(\.uuid)).count == items.count,
      items.allSatisfy({ row in
        UUID(uuidString: row.uuid) != nil && ["section", "item"].contains(row.kind)
          && Set(row.roles.map(\.roleUuid)).count == row.roles.count
          && row.roles.allSatisfy { role in
            UUID(uuidString: role.roleUuid) != nil
              && ["matched", "name_difference", "not_seen", "not_checked"].contains(role.status)
          }
      }) else { throw PreparationError.invalid("The folder comparison does not match this competition.") }
  }
}
private struct CompetitionFolderReport: Encodable {
  let revisionUuid: String
  let roleUuid: String
  let deviceUuid: String
  let nodes: [ProgrammeNode]
  let folders: [CompetitionObservedFolder]
}
extension APIClient {
  func competitionFolders(_ manifest: PreparedEvent, discovery: CompetitionFolderDiscovery?, role: EventPhotographer?, deviceUuid: String) async throws -> CompetitionFolderProgress {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      manifest.programme.eventType == "competition", UUID(uuidString: deviceUuid) != nil else {
      throw PreparationError.invalid("Sign in to this competition's server before checking folders.")
    }
    let path = "/api/staff/events/\(manifest.programme.eventUuid)/programme/competition-folders"
    let data: Data
    if let discovery, let role {
      // Report only dates served by this station role; the approved programme stays intact.
      let nodes = Dictionary(uniqueKeysWithValues: manifest.programme.nodes.map { ($0.uuid, $0) })
      let dates = Set(role.shifts.map(\.date))
      let folders = discovery.observed.filter { folder in
        guard let node = nodes[folder.nodeUuid] else { return false }
        let section = node.kind == "section" ? node : nodes[node.parentUuid ?? ""]
        guard let day = nodes[section?.parentUuid ?? ""], let date = day.date else { return false }
        return dates.contains(date)
      }
      let body = CompetitionFolderReport(revisionUuid: manifest.programme.revisionUuid,
        roleUuid: role.roleUuid, deviceUuid: deviceUuid, nodes: manifest.programme.nodes, folders: folders)
      data = try await request(path, jsonBody: ProgrammeCoding.encoder().encode(body))
    } else {
      data = try await request(path + "?revision_uuid=" + manifest.programme.revisionUuid)
    }
    let result = try ProgrammeCoding.decoder().decode(APIEnvelope<CompetitionFolderProgress>.self, from: data).data
    try result.validate(manifest)
    return result
  }
}
