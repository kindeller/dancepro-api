import Foundation

public struct PreparedEvent: Codable, Identifiable, Sendable {
  public let server: String
  public let programme: Programme
  public let folders: [PlannedFolder]
  public let preparedAt: Date
  public let hasPdf: Bool
  public var id: String { server + programme.eventUuid + programme.revisionUuid }
}

public enum LocalPreparation {
  public static func prepare(programme: Programme, pdf: Data?, server: String, destination: URL)
    throws -> URL
  {
    let plan = try FolderPlan(programme: programme)
    let fm = FileManager.default
    let root = destination.appendingPathComponent(plan.rootName, isDirectory: true)
    if let pdf, !pdf.starts(with: Data("%PDF-".utf8)) {
      throw PreparationError.invalid("The server did not return a valid programme PDF.")
    }
    guard (programme.sourceUrl != nil) == (pdf != nil) else {
      throw PreparationError.invalid(
        "The programme PDF is missing. Retry preparation while online.")
    }
    if fm.fileExists(atPath: root.path) {
      try requireDirectory(root)
      let manifestURL = root.appendingPathComponent("preparation.json")
      guard
        try manifestURL.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
          .isSymbolicLink != true
      else {
        throw PreparationError.invalid(
          "The existing preparation uses a symbolic link. Choose another destination.")
      }
      let existing = try ProgrammeCoding.decoder().decode(
        PreparedEvent.self, from: Data(contentsOf: manifestURL))
      let existingPaths = existing.folders.map(\.relativePath)
      let oldDayPaths = programme.eventType == "competition"
        ? try FolderPlan(programme: programme, includeSingleDay: true).folders.map(\.relativePath) : []
      guard existing.server == server, existing.programme.eventUuid == programme.eventUuid,
        existing.programme.revisionUuid == programme.revisionUuid,
        (existingPaths == plan.folders.map(\.relativePath) || existingPaths == oldDayPaths),
        existing.folders.map(\.itemUuid) == plan.folders.map(\.itemUuid),
        existing.hasPdf == (pdf != nil)
      else {
        throw PreparationError.invalid(
          "This destination already contains a different preparation. Choose another destination.")
      }
      for folder in existing.folders {
        var current = root
        for part in folder.relativePath.split(separator: "/") {
          current.appendPathComponent(String(part))
          try requireDirectory(current)
        }
      }
      for filename in ["programme.json"] + (pdf == nil ? [] : ["programme.pdf"]) {
        let file = root.appendingPathComponent(filename)
        let values = try file.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
        guard values.isRegularFile == true, values.isSymbolicLink != true else {
          throw PreparationError.invalid(
            "A saved programme file is missing or uses a symbolic link. Choose another destination."
          )
        }
      }
      guard
        try Data(contentsOf: root.appendingPathComponent("programme.json"))
          == ProgrammeCoding.encoder().encode(programme)
      else {
        throw PreparationError.invalid(
          "The saved programme has changed. Choose another destination to preserve the existing files."
        )
      }
      if let pdf, try Data(contentsOf: root.appendingPathComponent("programme.pdf")) != pdf {
        throw PreparationError.invalid(
          "The saved PDF has changed. Choose another destination to preserve the existing files.")
      }
      return root
    }
    let staging = destination.appendingPathComponent(".dancepro-preparing-" + UUID().uuidString)
    try fm.createDirectory(at: staging, withIntermediateDirectories: false)
    defer { if fm.fileExists(atPath: staging.path) { try? fm.removeItem(at: staging) } }
    for folder in plan.folders {
      try fm.createDirectory(
        at: staging.appendingPathComponent(folder.relativePath), withIntermediateDirectories: true)
    }
    let manifest = PreparedEvent(
      server: server, programme: programme, folders: plan.folders, preparedAt: Date(),
      hasPdf: pdf != nil)
    try ProgrammeCoding.encoder().encode(programme).write(
      to: staging.appendingPathComponent("programme.json"), options: .atomic)
    try pdf?.write(to: staging.appendingPathComponent("programme.pdf"), options: .atomic)
    try ProgrammeCoding.encoder().encode(manifest).write(
      to: staging.appendingPathComponent("preparation.json"), options: .atomic)
    try fm.moveItem(at: staging, to: root)
    return root
  }

  private static func requireDirectory(_ url: URL) throws {
    let values = try url.resourceValues(forKeys: [.isDirectoryKey, .isSymbolicLinkKey])
    guard values.isDirectory == true, values.isSymbolicLink != true else {
      throw PreparationError.invalid(
        "An existing folder is missing or uses a symbolic link. Choose another destination.")
    }
  }
}
