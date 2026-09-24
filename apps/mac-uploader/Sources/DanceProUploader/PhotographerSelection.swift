import Foundation
import PreparationCore

struct PhotographerShift: Codable, Identifiable {
  let assignmentUuid: String
  let shiftUuid: String
  let date: String
  let period: String
  var id: String { assignmentUuid }
}
struct EventPhotographer: Codable, Identifiable {
  let id: String
  let crewUuid: String?
  let roleUuid: String
  let name: String
  let roleName: String
  let shifts: [PhotographerShift]
  var mediaType: String? = nil
  var isVideo: Bool { mediaType == "video" }
}
struct UnfilledPhotographer: Codable {
  let shiftUuid: String
  let date: String
  let period: String
  let roleName: String
  let missingCount: Int
}
struct PhotographerRoster: Codable {
  let eventUuid: String
  let checkedAt: String
  let hasShifts: Bool
  let photographers: [EventPhotographer]
  let unfilled: [UnfilledPhotographer]
  var defaultRoleUuid: String? = nil
  var identityVersion: Int? = nil
}
struct PhotographerSelection: Codable {
  let server: String
  var roster: PhotographerRoster
  var selectedId: String?
  var selected: EventPhotographer? { roster.photographers.first { $0.id == selectedId } }

  mutating func refresh(_ roster: PhotographerRoster) throws {
    guard roster.eventUuid.lowercased() == self.roster.eventUuid.lowercased() else {
      throw PreparationError.invalid("The photographer list belongs to a different event.")
    }
    let previousRole = selected?.roleUuid
    self.roster = roster
    if let previousRole, let role = roster.photographers.first(where: { $0.roleUuid == previousRole }) {
      selectedId = role.id
    } else if selected == nil {
      selectedId = nil
      if previousRole == nil, let role = roster.photographers.first(where: { $0.roleUuid == roster.defaultRoleUuid }) {
        selectedId = role.id
      }
    }
  }

  static func read(root: URL, manifest: PreparedEvent) -> Self? {
    let url = root.appendingPathComponent("upload-photographer.json")
    guard (try? url.resourceValues(forKeys: [.isSymbolicLinkKey]).isSymbolicLink) != true,
      let data = try? Data(contentsOf: url),
      let saved = try? ProgrammeCoding.decoder().decode(Self.self, from: data),
      saved.server == manifest.server,
      saved.roster.eventUuid.lowercased() == manifest.programme.eventUuid.lowercased()
    else { return nil }
    return saved
  }

  func save(root: URL) throws {
    let url = root.appendingPathComponent("upload-photographer.json")
    guard (try? url.resourceValues(forKeys: [.isSymbolicLinkKey]).isSymbolicLink) != true else {
      throw PreparationError.invalid("The photographer selection location is a symbolic link.")
    }
    try ProgrammeCoding.encoder().encode(self).write(to: url, options: .atomic)
  }
}

extension APIClient {
  func photographers(for manifest: PreparedEvent) async throws -> PhotographerRoster {
    let origin = try APIClient(server: manifest.server).base
    guard origin.scheme == base.scheme, origin.host == base.host, origin.port == base.port,
      token != nil, UUID(uuidString: manifest.programme.eventUuid) != nil
    else {
      throw PreparationError.invalid(
        "Sign in to the server used to prepare this event to refresh its photographers.")
    }
    let response = try await decode(
      PhotographerRoster.self,
      path: "/api/staff/events/\(manifest.programme.eventUuid)/programme/upload-roles"
    ).data
    guard response.eventUuid.lowercased() == manifest.programme.eventUuid.lowercased(),
      Set(response.photographers.map(\.id)).count == response.photographers.count
    else {
      throw PreparationError.invalid("DancePro returned an invalid photographer list.")
    }
    return response
  }
}
