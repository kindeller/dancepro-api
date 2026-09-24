import Foundation

/// A local working copy; the downloaded programme and preparation remain immutable.
public enum ConcertLayout {
  public static func newItem(title: String = "New item") -> ProgrammeNode {
    ProgrammeNode(uuid: UUID().uuidString, kind: "item", title: title, itemKind: "routine")
  }

  /// Selection order never affects the combined title or its position.
  public static func combining(
    _ ids: Set<String>, in programme: Programme,
    existingIDs: Set<String>
  ) throws -> Programme {
    let selected = programme.nodes.filter { ids.contains($0.uuid) && $0.isActiveItem }
    guard programme.eventType == "concert", selected.count >= 2, selected.count == ids.count else {
      throw PreparationError.invalid("Select at least two concert items to combine.")
    }
    // Keep an existing folder identity when combining saved and newly added rows.
    var survivor = selected.first { existingIDs.contains($0.uuid) } ?? selected[0]
    survivor.title = selected.map { $0.title.trimmingCharacters(in: .whitespacesAndNewlines) }
      .joined(separator: " ")
    let targetID = survivor.uuid
    let firstID = selected[0].uuid
    var result = programme
    result.nodes = programme.nodes.flatMap { node -> [ProgrammeNode] in
      if node.uuid == firstID { return [survivor] }
      if ids.contains(node.uuid) { return [] }
      return [node]
    }
    for var node in selected where node.uuid != targetID {
      // Unsaved empty items need no retired folder identity.
      if existingIDs.contains(node.uuid) {
        node.mergedIntoUuid = targetID
        result.nodes.append(node)
      }
    }
    for index in result.nodes.indices {
      if let alias = result.nodes[index].mergedIntoUuid, ids.contains(alias) {
        result.nodes[index].mergedIntoUuid = targetID
      }
    }
    return try normalized(result)
  }

  public static func normalized(_ programme: Programme) throws -> Programme {
    guard programme.eventType == "concert" else {
      throw PreparationError.invalid("Competition structure is managed before the event.")
    }
    var result = programme
    var number = 0
    for index in result.nodes.indices
    where result.nodes[index].isActiveItem {
      let title = result.nodes[index].title.trimmingCharacters(in: .whitespacesAndNewlines)
      guard !FolderPlan.clean(title).isEmpty else {
        throw PreparationError.invalid("Every item needs a title containing letters or numbers.")
      }
      number += 1
      result.nodes[index].title = title
      result.nodes[index].folderTitle = FolderPlan.clean(title)
      result.nodes[index].folderNumber = String(format: "%02d", number)
    }
    for index in result.nodes.indices where result.nodes[index].mergedIntoUuid != nil {
      result.nodes[index].folderNumber = nil
      result.nodes[index].folderTitle = nil
    }
    _ = try FolderPlan(programme: result)
    return result
  }

  public static func load(root: URL, base: PreparedEvent) throws -> PreparedEvent {
    if base.programme.eventType == "competition" { return try CompetitionFolders.discover(root: root, base: base).manifest }
    if FileManager.default.fileExists(
      atPath: root.appendingPathComponent("layout-change.json").path)
    {
      try resume(root: root, base: base)
    }
    let url = root.appendingPathComponent("local-programme.json")
    guard FileManager.default.fileExists(atPath: url.path) else { return base }
    try requireRegular(url)
    let programme = try ProgrammeCoding.decoder().decode(
      Programme.self, from: Data(contentsOf: url))
    try validate(programme, against: base.programme)
    return try replacing(base, programme: programme)
  }

  public static func apply(_ programme: Programme, root: URL, base: PreparedEvent) throws
    -> PreparedEvent
  {
    let current = try load(root: root, base: base)
    let next = try normalized(programme)
    try validate(next, against: current.programme)
    if try ProgrammeCoding.encoder().encode(next)
      == ProgrammeCoding.encoder().encode(current.programme)
    {
      return current
    }
    let destinations = try transitionFolders(previous: current.programme, next: next)
    let fm = FileManager.default
    try requireDirectory(root)
    let oldByID = Dictionary(uniqueKeysWithValues: current.folders.map { ($0.itemUuid, $0) })
    let oldPaths = Set(current.folders.map(\.relativePath))
    for folder in destinations {
      let url = root.appendingPathComponent(folder.relativePath)
      if (try? url.resourceValues(forKeys: [.isDirectoryKey])) != nil
        && !oldPaths.contains(folder.relativePath)
      {
        throw PreparationError.invalid(
          "A folder already exists at \(folder.relativePath). Nothing was changed.")
      }
    }
    for folder in destinations where folder.relativePath.hasPrefix(".dancepro-combined-") {
      guard let old = oldByID[folder.itemUuid],
        try fm.contentsOfDirectory(atPath: root.appendingPathComponent(old.relativePath).path)
          .allSatisfy({ $0 == ".DS_Store" })
      else {
        throw PreparationError.invalid(
          "Move all media out of an item before combining its structure.")
      }
    }
    // Stage every folder before assigning final numbered names, including swaps.
    let staging = ".dancepro-layout-" + UUID().uuidString
    let stagingURL = root.appendingPathComponent(staging)
    try fm.createDirectory(at: stagingURL, withIntermediateDirectories: false)
    var moves: [LayoutMove] = []
    var finalMoves: [LayoutMove] = []
    for folder in destinations {
      let temporary = staging + "/" + folder.itemUuid
      let source: URL
      if let old = oldByID[folder.itemUuid] {
        source = root.appendingPathComponent(old.relativePath)
        try requireDirectory(source)
        moves.append(
          LayoutMove(from: old.relativePath, to: temporary, identity: try identity(source)))
      } else {
        source = root.appendingPathComponent(temporary)
        try fm.createDirectory(at: source, withIntermediateDirectories: false)
      }
      finalMoves.append(
        LayoutMove(from: temporary, to: folder.relativePath, identity: try identity(source)))
    }
    let journal = LayoutJournal(
      previous: current.programme, programme: next, staging: staging, moves: moves + finalMoves,
      completed: 0)
    try writeJournal(journal, root: root)
    try resume(root: root, base: base)
    return try replacing(base, programme: next)
  }

  private static func transitionFolders(previous: Programme, next: Programme) throws
    -> [PlannedFolder]
  {
    let active = try FolderPlan(programme: next).folders
    let retired = try FolderPlan(programme: previous).folders.filter { old in
      !active.contains(where: { $0.itemUuid == old.itemUuid })
    }
    return active
      + retired.map {
        PlannedFolder(itemUuid: $0.itemUuid, relativePath: ".dancepro-combined-" + $0.itemUuid)
      }
  }

  private static func replacing(_ base: PreparedEvent, programme: Programme) throws -> PreparedEvent
  {
    PreparedEvent(
      server: base.server, programme: programme,
      folders: try FolderPlan(programme: programme).folders,
      preparedAt: base.preparedAt, hasPdf: base.hasPdf)
  }

  private static func validate(_ programme: Programme, against base: Programme) throws {
    guard programme.eventType == "concert", base.eventType == "concert",
      programme.eventUuid == base.eventUuid, programme.revisionUuid == base.revisionUuid,
      programme.programmeUuid == base.programmeUuid,
      Set(base.nodes.map(\.uuid)).isSubset(of: Set(programme.nodes.map(\.uuid)))
    else {
      throw PreparationError.invalid(
        "The local programme must retain this concert’s existing items and identities.")
    }
    guard
      base.nodes.allSatisfy({ node in
        programme.nodes.contains {
          $0.uuid == node.uuid && $0.kind == node.kind && $0.itemKind == node.itemKind
        }
      })
    else { throw PreparationError.invalid("Existing concert item types must be preserved.") }
    guard
      base.nodes.filter({ $0.mergedIntoUuid != nil }).allSatisfy({ old in
        programme.nodes.first(where: { $0.uuid == old.uuid })?.mergedIntoUuid != nil
      })
    else {
      throw PreparationError.invalid("Combined item identities cannot be reused as new items.")
    }
    let normalized = try normalized(programme)
    guard
      try ProgrammeCoding.encoder().encode(normalized)
        == ProgrammeCoding.encoder().encode(programme)
    else {
      throw PreparationError.invalid("The local programme numbering is invalid.")
    }
  }

  private static func resume(root: URL, base: PreparedEvent) throws {
    let fm = FileManager.default
    let url = root.appendingPathComponent("layout-change.json")
    try requireDirectory(root)
    try requireRegular(url)
    var journal = try JSONDecoder().decode(LayoutJournal.self, from: Data(contentsOf: url))
    try validate(journal.previous, against: base.programme)
    try validate(journal.programme, against: journal.previous)
    guard journal.staging.hasPrefix(".dancepro-layout-"),
      UUID(uuidString: String(journal.staging.dropFirst(".dancepro-layout-".count))) != nil,
      journal.completed >= 0, journal.completed <= journal.moves.count
    else {
      throw PreparationError.invalid(
        "The interrupted folder change has an invalid recovery record.")
    }
    let oldFolders = try FolderPlan(programme: journal.previous).folders
    let newFolders = try transitionFolders(previous: journal.previous, next: journal.programme)
    var expected: [(String, String)] = []
    for folder in newFolders {
      if let old = oldFolders.first(where: { $0.itemUuid == folder.itemUuid }) {
        expected.append((old.relativePath, journal.staging + "/" + folder.itemUuid))
      }
    }
    for folder in newFolders {
      expected.append((journal.staging + "/" + folder.itemUuid, folder.relativePath))
    }
    guard expected.count == journal.moves.count,
      zip(expected, journal.moves).allSatisfy({ pair, move in
        pair.0 == move.from && pair.1 == move.to && !move.identity.isEmpty
      })
    else {
      throw PreparationError.invalid("The interrupted folder change contains an invalid move.")
    }
    while journal.completed < journal.moves.count {
      let move = journal.moves[journal.completed]
      let source = root.appendingPathComponent(move.from)
      let target = root.appendingPathComponent(move.to)
      try requireDirectory(source.deletingLastPathComponent())
      try requireDirectory(target.deletingLastPathComponent())
      if fm.fileExists(atPath: source.path) {
        try requireDirectory(source)
        guard try identity(source) == move.identity else {
          throw PreparationError.invalid(
            "A folder changed during renumbering. Keep the event folders in place and reopen the event."
          )
        }
        try fm.moveItem(at: source, to: target)
      } else {
        try requireDirectory(target)
        guard try identity(target) == move.identity else {
          throw PreparationError.invalid(
            "An interrupted folder change needs its original folder restored before it can continue."
          )
        }
      }
      journal.completed += 1
      try writeJournal(journal, root: root)
    }
    for move in journal.moves.suffix(newFolders.count) {
      let target = root.appendingPathComponent(move.to)
      try requireDirectory(target)
      guard try identity(target) == move.identity else {
        throw PreparationError.invalid("A concert folder changed before renumbering finished.")
      }
    }
    let localURL = root.appendingPathComponent("local-programme.json")
    if fm.fileExists(atPath: localURL.path) { try requireRegular(localURL) }
    try ProgrammeCoding.encoder().encode(journal.programme).write(to: localURL, options: .atomic)
    try fm.removeItem(at: url)
    // Only remove our now-empty staging directory, never recursively remove media.
    let stagingURL = root.appendingPathComponent(journal.staging)
    if (try? fm.contentsOfDirectory(atPath: stagingURL.path).isEmpty) == true {
      try? fm.removeItem(at: stagingURL)
    }
  }

  private static func identity(_ url: URL) throws -> String {
    let info = try FileManager.default.attributesOfItem(atPath: url.path)
    guard let device = info[.systemNumber] as? NSNumber,
      let inode = info[.systemFileNumber] as? NSNumber
    else {
      throw PreparationError.invalid("Unable to identify a concert folder.")
    }
    return "\(device):\(inode)"
  }
  private static func writeJournal(_ journal: LayoutJournal, root: URL) throws {
    let url = root.appendingPathComponent("layout-change.json")
    if FileManager.default.fileExists(atPath: url.path) { try requireRegular(url) }
    try JSONEncoder().encode(journal).write(to: url, options: .atomic)
  }
  private static func requireRegular(_ url: URL) throws {
    let values = try url.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
    guard values.isRegularFile == true, values.isSymbolicLink != true else {
      throw PreparationError.invalid("A local programme file is missing or is a symbolic link.")
    }
  }
  private static func requireDirectory(_ url: URL) throws {
    let values = try url.resourceValues(forKeys: [.isDirectoryKey, .isSymbolicLinkKey])
    guard values.isDirectory == true, values.isSymbolicLink != true else {
      throw PreparationError.invalid("A concert folder is missing or is a symbolic link.")
    }
  }
}

private struct LayoutMove: Codable {
  let from: String
  let to: String
  let identity: String
}
private struct LayoutJournal: Codable {
  let previous: Programme
  let programme: Programme
  let staging: String
  let moves: [LayoutMove]
  var completed: Int
}
