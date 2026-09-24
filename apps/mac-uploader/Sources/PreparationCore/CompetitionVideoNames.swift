import Foundation

public struct CompetitionVideoMatch: Sendable, Identifiable {
  public let relativePath: String
  public let itemUuid: String?
  public let label: String
  public var id: String { relativePath }
}

/// Filename matching only; no video bytes are read, renamed or uploaded.
public enum CompetitionVideoNames {
  public static let extensions: Set<String> = ["mp4", "mov", "m4v", "mxf"]

  public static func match(filename: String, relativePath: String, programme: Programme) -> CompetitionVideoMatch {
    func unresolved(_ reason: String) -> CompetitionVideoMatch {
      CompetitionVideoMatch(relativePath: relativePath, itemUuid: nil, label: reason)
    }
    guard programme.eventType == "competition", let eventCode = programme.eventCode, !eventCode.isEmpty else {
      return unresolved("A competition event code is required")
    }
    let parts = (filename as NSString).deletingPathExtension.split(separator: "_", omittingEmptySubsequences: false)
    guard parts.count >= 3 else { return unresolved("Use event code_section_competitor, for example EDC926_07_03") }
    let code = parts.dropLast(2).joined(separator: "_")
    guard code.caseInsensitiveCompare(eventCode) == .orderedSame else { return unresolved("Different event code") }
    let sectionNumber = String(parts[parts.count - 2])
    let competitorToken = String(parts[parts.count - 1])
    guard sectionNumber.range(of: "^[0-9]+$", options: .regularExpression) != nil,
      competitorToken.range(of: "^[0-9]+[a-zA-Z]?$", options: .regularExpression) != nil else {
      return unresolved("Invalid section or competitor number")
    }
    let competitorNumber = String(competitorToken.prefix(while: { $0.isNumber }))
    let sections = programme.nodes.filter {
      $0.kind == "section" && CompetitionFolders.number($0.number ?? "") == CompetitionFolders.number(sectionNumber)
    }
    guard sections.count == 1 else { return unresolved(sections.isEmpty ? "Section not in this programme" : "Section number is ambiguous") }
    let items = programme.nodes.filter {
      $0.isActiveItem && $0.parentUuid == sections[0].uuid
        && CompetitionFolders.number($0.number ?? "") == CompetitionFolders.number(competitorNumber)
    }
    guard items.count == 1 else { return unresolved(items.isEmpty ? "Competitor not in this section" : "Competitor number is ambiguous") }
    return CompetitionVideoMatch(relativePath: relativePath, itemUuid: items[0].uuid,
      label: "Section \(sectionNumber) · \(competitorNumber) \(items[0].title)")
  }
}
