import Foundation

public struct PreparedEvent: Codable, Identifiable, Sendable {
  public let server: String
  public let programme: Programme
  public let folders: [PlannedFolder]
  public let preparedAt: Date
  public let hasPdf: Bool
  public var preparationMode: String? = nil
  public var isVideoPreparation: Bool { preparationMode == "video" }
  public var id: String { server + programme.eventUuid + programme.revisionUuid }

  public init(server: String, programme: Programme, folders: [PlannedFolder], preparedAt: Date,
    hasPdf: Bool, preparationMode: String? = nil) {
    self.server = server
    self.programme = programme
    self.folders = folders
    self.preparedAt = preparedAt
    self.hasPdf = hasPdf
    self.preparationMode = preparationMode
  }
}

public enum LocalPreparation {
  public static func prepare(programme: Programme, pdf: Data?, server: String, destination: URL, videoTitles: Data? = nil)
    throws -> URL
  {
    let plan = try FolderPlan(programme: programme)
    let fm = FileManager.default
    let videoOnly = videoTitles != nil
    guard !videoOnly || programme.eventType == "concert" else {
      throw PreparationError.invalid("Video-title preparation is only available for concerts.")
    }
    let folders = videoOnly ? [] : plan.folders
    // A user may select the prepared event itself, rather than its parent.
    // Reopen it without rebuilding folders or replacing local review/programme files.
    let selectedManifest = destination.appendingPathComponent("preparation.json")
    if fm.fileExists(atPath: selectedManifest.path) {
      try requireDirectory(destination)
      let values = try selectedManifest.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
      guard values.isRegularFile == true, values.isSymbolicLink != true else {
        throw PreparationError.invalid("The selected preparation manifest is not a regular file.")
      }
      let existing = try ProgrammeCoding.decoder().decode(
        PreparedEvent.self, from: Data(contentsOf: selectedManifest))
      guard existing.server == server, existing.programme.eventUuid == programme.eventUuid,
        existing.programme.revisionUuid == programme.revisionUuid,
        existing.isVideoPreparation == videoOnly else {
        throw PreparationError.invalid(
          "This is an existing prepared event for a different event, version or media type. Use Open Existing Event Folder to resume it, or choose a parent folder for a new preparation. Your files have not been changed.")
      }
      return destination
    }
    let root = destination.appendingPathComponent(plan.rootName + (videoOnly ? " - Video" : ""), isDirectory: true)
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
        existing.isVideoPreparation == videoOnly,
        (existingPaths == folders.map(\.relativePath) || (!videoOnly && existingPaths == oldDayPaths)),
        existing.folders.map(\.itemUuid) == folders.map(\.itemUuid),
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
      if let videoTitles {
        let titlesURL = root.appendingPathComponent("Video Titles.zip")
        let values = try titlesURL.resourceValues(forKeys: [.isRegularFileKey, .isSymbolicLinkKey])
        guard values.isRegularFile == true, values.isSymbolicLink != true,
          try Data(contentsOf: titlesURL) == videoTitles else {
          throw PreparationError.invalid("The saved video titles differ. Choose another destination to preserve them.")
        }
      }
      return root
    }
    let staging = destination.appendingPathComponent(".dancepro-preparing-" + UUID().uuidString)
    try fm.createDirectory(at: staging, withIntermediateDirectories: false)
    defer { if fm.fileExists(atPath: staging.path) { try? fm.removeItem(at: staging) } }
    for folder in folders {
      try fm.createDirectory(
        at: staging.appendingPathComponent(folder.relativePath), withIntermediateDirectories: true)
    }
    let manifest = PreparedEvent(
      server: server, programme: programme, folders: folders, preparedAt: Date(),
      hasPdf: pdf != nil, preparationMode: videoOnly ? "video" : nil)
    try ProgrammeCoding.encoder().encode(programme).write(
      to: staging.appendingPathComponent("programme.json"), options: .atomic)
    try pdf?.write(to: staging.appendingPathComponent("programme.pdf"), options: .atomic)
    try videoTitles?.write(to: staging.appendingPathComponent("Video Titles.zip"), options: .atomic)
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
