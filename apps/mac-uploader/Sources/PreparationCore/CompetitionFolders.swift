import CryptoKit
import Foundation

public struct CompetitionObservedFolder: Codable, Sendable {
  public let nodeUuid: String
  public let title: String
}
public struct CompetitionFolderDiscovery: Sendable {
  public let manifest: PreparedEvent
  public let observed: [CompetitionObservedFolder]
  public var issues: [String]
  public var videos: [CompetitionVideoMatch] = []
}

/// Reads capture folders without creating, moving, renaming or deleting them.
public enum CompetitionFolders {
  public static func number(_ value: String) -> String {
    let clean = value.lowercased().drop(while: { $0 == "0" })
    return clean.isEmpty ? "0" : String(clean)
  }
  public static func identity(programme: String, kind: String, number value: String, section: String? = nil) throws -> String {
    guard let namespace = UUID(uuidString: programme) else { throw PreparationError.invalid("Invalid competition identity.") }
    var uuid = namespace.uuid
    var bytes = withUnsafeBytes(of: &uuid) { Array($0) }
    let name = "competition/" + kind + "/" + (section.map { $0.lowercased() + "/" } ?? "") + number(value)
    bytes += Array(name.utf8)
    var hash = Array(Insecure.SHA1.hash(data: Data(bytes)).prefix(16))
    hash[6] = (hash[6] & 0x0f) | 0x50
    hash[8] = (hash[8] & 0x3f) | 0x80
    let text = hash.map { String(format: "%02x", $0) }.joined()
    return [String(text.prefix(8)), String(text.dropFirst(8).prefix(4)), String(text.dropFirst(12).prefix(4)), String(text.dropFirst(16).prefix(4)), String(text.suffix(12))].joined(separator: "-")
  }
  public static func captureTitle(_ value: String) -> String {
    value.components(separatedBy: .controlCharacters).joined()
      .replacingOccurrences(of: "/", with: " ")
      .replacingOccurrences(of: "\\", with: " ")
      .replacingOccurrences(of: ":", with: " ")
      .split(whereSeparator: { $0.isWhitespace }).joined(separator: " ")
  }
  public static func padded(_ value: String) -> String {
    let digits = value.prefix(while: { $0.isNumber })
    return String(repeating: "0", count: max(0, 2 - digits.count)) + value
  }
  public static func dayFolder(_ day: ProgrammeNode, in programme: Programme) throws -> String {
    let days = programme.nodes.filter { $0.kind == "day" }.sorted { ($0.date ?? "") < ($1.date ?? "") }
    guard let index = days.firstIndex(where: { $0.uuid == day.uuid }), let date = day.date else { throw PreparationError.invalid("A competition day needs a date.") }
    let formatter = DateFormatter()
    formatter.locale = Locale(identifier: "en_US_POSIX")
    formatter.timeZone = TimeZone(secondsFromGMT: 0)
    formatter.dateFormat = "yyyy-MM-dd"
    formatter.isLenient = false
    guard let parsed = formatter.date(from: date) else { throw PreparationError.invalid("Invalid competition date.") }
    formatter.dateFormat = "EEEE"
    return String(format: "%02d", index + 1) + " " + formatter.string(from: parsed)
  }
  private static func numbered(_ name: String) -> (String, String)? {
    let parts = name.split(maxSplits: 1, whereSeparator: { $0.isWhitespace })
    guard let first = parts.first,
      first.range(of: "^[0-9]+[a-zA-Z]?$", options: .regularExpression) != nil else { return nil }
    return (String(first), parts.count > 1 ? String(parts[1]) : "")
  }
  public static func discover(root: URL, base: PreparedEvent) throws -> CompetitionFolderDiscovery {
    guard base.programme.eventType == "competition" else { throw PreparationError.invalid("Choose a competition event.") }
    let fm = FileManager.default
    var programme = base.programme
    var folders: [PlannedFolder] = []
    var observed: [CompetitionObservedFolder] = []
    var issues: [String] = []
    var seen = Set<String>()
    var visited = 0
    var videoURLs: [URL] = []
    var sectionPaths: [String: String] = [:]
    func directories(_ url: URL) throws -> [URL] {
      let values = try url.resourceValues(forKeys: [.isDirectoryKey, .isSymbolicLinkKey])
      guard values.isDirectory == true, values.isSymbolicLink != true else { throw PreparationError.invalid("Competition folders cannot use symbolic links.") }
      return try fm.contentsOfDirectory(at: url, includingPropertiesForKeys: [.isDirectoryKey, .isSymbolicLinkKey], options: [.skipsHiddenFiles])
        .sorted { $0.lastPathComponent.localizedStandardCompare($1.lastPathComponent) == .orderedAscending }
        .filter { child in
          visited += 1
          guard visited <= 20000 else { return false }
          let values = try? child.resourceValues(forKeys: [.isDirectoryKey, .isSymbolicLinkKey])
          if values?.isSymbolicLink == true { issues.append("Skipped symbolic link: \(child.lastPathComponent)"); return false }
          if values?.isDirectory != true && CompetitionVideoNames.extensions.contains(child.pathExtension.lowercased()) {
            if (try? child.resourceValues(forKeys: [.isRegularFileKey]).isRegularFile) == true { videoURLs.append(child) }
          }
          return values?.isDirectory == true
        }
    }
    let days = programme.nodes.filter { $0.kind == "day" }.sorted { ($0.date ?? "") < ($1.date ?? "") }
    var sections: [(day: ProgrammeNode, url: URL, prefix: String)] = []
    for entry in try directories(root) {
      let label = entry.lastPathComponent
      let day = try days.first { day in
        if day.date == label { return true }
        return try dayFolder(day, in: base.programme).caseInsensitiveCompare(label) == .orderedSame
      }
      // Old preparation manifests retain their day wrapper; new one-day sections
      // can legitimately have weekday titles, so do not infer a day from the name alone.
      let isPreparedDay = base.folders.contains {
        let components = $0.relativePath.split(separator: "/")
        return components.count == 3 && String(components[0]).caseInsensitiveCompare(label) == .orderedSame
      }
      if let day, days.count > 1 || day.date == label || isPreparedDay {
        for sectionURL in try directories(entry) { sections.append((day, sectionURL, label)) }
      } else if days.count == 1 {
        sections.append((days[0], entry, ""))
      } else { issues.append("Unmatched day folder: \(label)") }
    }
    for context in sections {
      let day = context.day
      let label = context.prefix
      let sectionURL = context.url
        let name = sectionURL.lastPathComponent
        let legacy = name.hasPrefix("Section ") ? String(name.dropFirst(8)) : name
        guard let (sectionNumber, sectionTitle) = numbered(legacy) else { issues.append("Unmatched section: \(label)/\(name)"); continue }
        var section = programme.nodes.first { $0.kind == "section" && number($0.number ?? "") == number(sectionNumber) }
        if let section, section.parentUuid != day.uuid { issues.append("Section \(sectionNumber) is on a different programme day"); continue }
        if section == nil {
          let id = try identity(programme: programme.programmeUuid, kind: "section", number: sectionNumber)
          let added = ProgrammeNode(uuid: id, parentUuid: day.uuid, kind: "section", title: sectionTitle.isEmpty ? "Section \(sectionNumber)" : sectionTitle, number: sectionNumber)
          programme.nodes.append(added); section = added
        }
        let parent = section!
        let sectionPath = [label, name].filter { !$0.isEmpty }.joined(separator: "/")
        sectionPaths[sectionPath] = parent.uuid
        if seen.insert(parent.uuid).inserted { observed.append(CompetitionObservedFolder(nodeUuid: parent.uuid, title: sectionTitle.isEmpty ? parent.title : sectionTitle)) }
        for itemURL in try directories(sectionURL) {
          let itemName = itemURL.lastPathComponent
          guard let (itemNumber, title) = numbered(itemName) else { issues.append("Unmatched competitor: \(name)/\(itemName)"); continue }
          var item = programme.nodes.first { $0.kind == "item" && $0.parentUuid == parent.uuid && number($0.number ?? "") == number(itemNumber) }
          if item == nil {
            let id = try identity(programme: programme.programmeUuid, kind: "item", number: itemNumber, section: parent.uuid)
            let added = ProgrammeNode(uuid: id, parentUuid: parent.uuid, kind: "item", title: title.isEmpty ? "Competitor \(itemNumber)" : title, number: itemNumber, itemKind: "dance")
            programme.nodes.append(added); item = added
          }
          let node = item!
          guard node.isActiveItem else { issues.append("Non-performance folder: \(itemName)"); continue }
          guard seen.insert(node.uuid).inserted else {
            folders.removeAll { $0.itemUuid == node.uuid }
            observed.removeAll { $0.nodeUuid == node.uuid }
            issues.append("Duplicate competitor folder: \(name)/\(itemName)")
            continue
          }
          let path = [label, name, itemName].filter { !$0.isEmpty }.joined(separator: "/")
          folders.append(PlannedFolder(itemUuid: node.uuid, relativePath: path))
          observed.append(CompetitionObservedFolder(nodeUuid: node.uuid, title: title.isEmpty ? node.title : title))
          _ = try directories(itemURL) // Collect video names without reading media bytes.
        }
    }
    if visited > 20000 { issues.append("Too many folders to scan. Check this event's folder structure.") }
    guard programme.nodes.count <= 10000 else { throw PreparationError.invalid("Too many competition items.") }
    // Group additions with their day and section for the review sidebar.
    let all = programme.nodes
    programme.nodes = days.flatMap { day in
      [day] + all.filter { $0.kind == "section" && $0.parentUuid == day.uuid }.sorted {
        ($0.number ?? "").localizedStandardCompare($1.number ?? "") == .orderedAscending
      }.flatMap { section in
        [section] + all.filter { $0.kind == "item" && $0.parentUuid == section.uuid }.sorted {
          ($0.number ?? "").localizedStandardCompare($1.number ?? "") == .orderedAscending
        }
      }
    }
    let videoMatches = videoURLs.map { url -> CompetitionVideoMatch in
      let rootPath = root.resolvingSymlinksInPath().path
      let fullPath = url.resolvingSymlinksInPath().path
      guard fullPath.hasPrefix(rootPath + "/") else {
        return CompetitionVideoMatch(relativePath: url.lastPathComponent, itemUuid: nil, label: "Video is outside this event folder")
      }
      let path = String(fullPath.dropFirst(rootPath.count + 1))
      var match = CompetitionVideoNames.match(filename: url.lastPathComponent, relativePath: path, programme: programme)
      if let itemID = match.itemUuid {
        let parentPath = (path as NSString).deletingLastPathComponent
        let containingItem = folders.first { $0.relativePath == parentPath }?.itemUuid
        let containingSection = sectionPaths[parentPath]
        let item = programme.nodes.first { $0.uuid == itemID }
        if (containingItem != nil && containingItem != itemID)
          || (containingSection != nil && containingSection != item?.parentUuid) {
          match = CompetitionVideoMatch(relativePath: path, itemUuid: nil, label: "Filename and containing folder disagree")
        }
      }
      return match
    }
    return CompetitionFolderDiscovery(manifest: PreparedEvent(server: base.server, programme: programme, folders: folders, preparedAt: base.preparedAt, hasPdf: base.hasPdf), observed: observed, issues: issues, videos: videoMatches)
  }
}
