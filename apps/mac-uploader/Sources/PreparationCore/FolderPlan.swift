import Foundation

public struct PlannedFolder: Codable, Identifiable, Sendable {
  public let itemUuid: String
  public let relativePath: String
  public var id: String { itemUuid }
}

public struct FolderPlan: Sendable {
  public let folders: [PlannedFolder]
  public let rootName: String

  public init(programme: Programme, includeSingleDay: Bool = false) throws {
    guard programme.schemaVersion == 1, UUID(uuidString: programme.eventUuid) != nil,
      UUID(uuidString: programme.revisionUuid) != nil, programme.nodes.count <= 10000,
      Set(programme.nodes.map(\.uuid)).count == programme.nodes.count,
      programme.nodes.allSatisfy({ UUID(uuidString: $0.uuid) != nil })
    else {
      throw PreparationError.invalid(
        "The downloaded programme has an unsupported format or invalid identities.")
    }
    let byID = Dictionary(uniqueKeysWithValues: programme.nodes.map { ($0.uuid, $0) })
    for item in programme.nodes where item.mergedIntoUuid != nil {
      guard programme.eventType == "concert", item.kind == "item", item.itemKind != "break",
        let target = item.mergedIntoUuid, target != item.uuid, byID[target]?.isActiveItem == true
      else {
        throw PreparationError.invalid(
          "A combined item must point to an active item in the same concert.")
      }
    }
    var output: [PlannedFolder] = []
    for item in programme.nodes where item.isActiveItem {
      let path: String
      if programme.eventType == "concert" {
        guard let number = item.folderNumber,
          number.range(of: "^[0-9]{2,}$", options: .regularExpression) != nil,
          let title = item.folderTitle, !title.isEmpty
        else {
          throw PreparationError.invalid(
            "This concert needs to be approved again in Programme preparation before its folders can be downloaded."
          )
        }
        path = try Self.component(programme.isPortraitSession ? title : number + " " + title)
      } else if programme.eventType == "competition" {
        guard let parent = item.parentUuid, let section = byID[parent], section.kind == "section",
          let dayID = section.parentUuid, let day = byID[dayID], day.kind == "day",
          let date = day.date,
          date.range(of: "^[0-9]{4}-[0-9]{2}-[0-9]{2}$", options: .regularExpression) != nil,
          let sectionNumber = section.number, let itemNumber = item.number,
          sectionNumber.range(of: "^[0-9]+[a-zA-Z]?$", options: .regularExpression) != nil,
          itemNumber.range(of: "^[0-9]+[a-zA-Z]?$", options: .regularExpression) != nil
        else {
          throw PreparationError.invalid(
            "A competition row has no valid day, section or printed competitor number.")
        }
        let dayParts = programme.nodes.filter { $0.kind == "day" }.count > 1 || includeSingleDay
          ? [try Self.component(CompetitionFolders.dayFolder(day, in: programme))] : []
        path = try (dayParts + [
          Self.component(CompetitionFolders.padded(sectionNumber) + " " + CompetitionFolders.captureTitle(section.title)),
          Self.component(CompetitionFolders.padded(itemNumber) + " " + CompetitionFolders.captureTitle(item.title)),
        ]).joined(separator: "/")
      } else {
        throw PreparationError.invalid("This event type is not supported.")
      }
      output.append(PlannedFolder(itemUuid: item.uuid, relativePath: path))
    }
    guard !output.isEmpty,
      Set(
        output.map {
          $0.relativePath.folding(
            options: [.caseInsensitive, .diacriticInsensitive],
            locale: Locale(identifier: "en_US_POSIX"))
        }
      ).count == output.count
    else {
      throw PreparationError.invalid(
        "The programme contains no folders, or two rows would use the same folder. Correct and approve it again."
      )
    }
    if programme.eventType == "concert" {
      let numbers = programme.nodes.filter { $0.isActiveItem }
        .compactMap { Int($0.folderNumber ?? "") }.sorted()
      guard numbers == Array(1...output.count) else {
        throw PreparationError.invalid(
          "Concert folder numbers are not continuous. Approve the programme again.")
      }
      output.sort { a, b in
        a.relativePath.compare(b.relativePath, options: .numeric) == .orderedAscending
      }
    }
    folders = output
    var label = Self.clean(programme.eventCode ?? programme.eventName, stripAccents: false)
    while label.utf8.count > 120 { label.removeLast() }
    rootName = programme.eventType == "competition" ? (label.isEmpty ? "Event" : label) :
      (label.isEmpty ? "Event" : label) + " - v\(programme.version) - "
      + String(programme.revisionUuid.prefix(8))
  }

  public static func clean(_ text: String, stripAccents: Bool = true) -> String {
    let title = stripAccents
      ? text.decomposedStringWithCanonicalMapping
        .replacingOccurrences(of: "\\p{M}+", with: "", options: .regularExpression)
        .precomposedStringWithCanonicalMapping
      : text
    return title.replacingOccurrences(of: "/", with: " ")
      .replacingOccurrences(of: "[^\\p{L}\\p{M}\\p{N}\\s]", with: "", options: .regularExpression)
      .replacingOccurrences(of: "\\s+", with: " ", options: .regularExpression)
      .trimmingCharacters(in: .whitespacesAndNewlines)
  }

  private static func component(_ text: String) throws -> String {
    guard !text.isEmpty, text != ".", text != "..", text.utf8.count <= 220,
      !text.contains("/"), !text.contains("\\"), !text.contains(":"),
      text.unicodeScalars.allSatisfy({ !CharacterSet.controlCharacters.contains($0) })
    else {
      throw PreparationError.invalid(
        "A folder name is empty, too long, or contains an unsafe path. Shorten or correct its programme title."
      )
    }
    return text
  }
}
