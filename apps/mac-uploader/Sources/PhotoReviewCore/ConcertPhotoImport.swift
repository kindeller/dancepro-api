import CoreGraphics
import Foundation
import ImageIO
import PreparationCore

public struct ImportPhoto: Identifiable, Sendable {
  public let source: URL
  public let bytes: Int
  public let modified: Date
  public let suggestedSeparator: Bool
  public var filename: String
  public var id: String { source.path }
}

public struct ImportPhotoGroup: Identifiable, Sendable {
  public var id = UUID()
  public var photos: [ImportPhoto]
  public var itemID: String?

  public init(photos: [ImportPhoto], itemID: String?) {
    self.photos = photos
    self.itemID = itemID
  }
}

public struct ConcertImportPlan: Sendable {
  public var groups: [ImportPhotoGroup]
  public var separators: [ImportPhoto]
  public var skipped: [String]

  public mutating func split(groupID: UUID, before photoID: String) {
    guard let group = groups.firstIndex(where: { $0.id == groupID }),
      let photo = groups[group].photos.firstIndex(where: { $0.id == photoID }), photo > 0 else { return }
    let next = ImportPhotoGroup(photos: Array(groups[group].photos[photo...]), itemID: nil)
    groups[group].photos.removeSubrange(photo...)
    groups.insert(next, at: group + 1)
  }

  public mutating func mergeWithPrevious(_ groupID: UUID) {
    guard let index = groups.firstIndex(where: { $0.id == groupID }), index > 0 else { return }
    groups[index - 1].photos += groups[index].photos
    groups.remove(at: index)
  }

  public mutating func exclude(_ photoID: String, from groupID: UUID) {
    guard let group = groups.firstIndex(where: { $0.id == groupID }),
      let index = groups[group].photos.firstIndex(where: { $0.id == photoID }) else { return }
    separators.append(groups[group].photos.remove(at: index))
    if groups[group].photos.isEmpty { groups.remove(at: group) }
  }

  public mutating func restore(_ photoID: String, to groupID: UUID) {
    guard let group = groups.firstIndex(where: { $0.id == groupID }),
      let index = separators.firstIndex(where: { $0.id == photoID }) else { return }
    groups[group].photos.append(separators.remove(at: index))
    groups[group].photos.sort { $0.id.localizedStandardCompare($1.id) == .orderedAscending }
  }
}

public struct ConcertImportResult: Sendable {
  public let copiedIDs: Set<String>
  public let error: String?
}

public enum ConcertPhotoImport {
  /// Scans JPEGs in natural filename order. Detection proposes boundaries; it never changes originals.
  public static func scan(_ directory: URL, itemIDs: [String], separateBlackFrames: Bool = true, progress: @Sendable (Int, Int) -> Void = { _, _ in }) throws -> ConcertImportPlan {
    let manager = FileManager.default
    let keys: [URLResourceKey] = [.isRegularFileKey, .isSymbolicLinkKey, .isDirectoryKey]
    guard let walker = manager.enumerator(at: directory, includingPropertiesForKeys: keys, options: [.skipsHiddenFiles]) else {
      throw PreparationError.invalid("This folder could not be read.")
    }
    var urls: [URL] = []
    for case let url as URL in walker {
      try Task.checkCancellation()
      let values = try url.resourceValues(forKeys: Set(keys))
      if values.isSymbolicLink == true { walker.skipDescendants(); continue }
      if values.isRegularFile == true, ["jpg", "jpeg"].contains(url.pathExtension.lowercased()) { urls.append(url) }
    }
    urls.sort { $0.path.localizedStandardCompare($1.path) == .orderedAscending }
    var groups: [ImportPhotoGroup] = []
    var current: [ImportPhoto] = []
    var separators: [ImportPhoto] = []
    var skipped: [String] = []
    func appendGroup() {
      if !current.isEmpty {
        groups.append(ImportPhotoGroup(photos: current, itemID: groups.count < itemIDs.count ? itemIDs[groups.count] : nil))
        current = []
      }
    }
    for (index, url) in urls.enumerated() {
      try Task.checkCancellation()
      do {
        let info = try fingerprint(url)
        let image = try thumbnail(url, size: 96)
        guard try fingerprint(url) == info else { throw PreparationError.invalid("File changed during scanning") }
        let black = separateBlackFrames && isBlack(image)
        let photo = ImportPhoto(source: url, bytes: info.bytes, modified: info.modified, suggestedSeparator: black, filename: url.lastPathComponent)
        if black { appendGroup(); separators.append(photo) } else { current.append(photo) }
      } catch {
        // Break the group at an unreadable frame: it may have been a separator.
        if separateBlackFrames { appendGroup() }
        skipped.append(url.lastPathComponent + ": " + error.localizedDescription)
      }
      progress(index + 1, urls.count)
    }
    appendGroup()
    return ConcertImportPlan(groups: groups, separators: separators, skipped: skipped)
  }

  public static func thumbnail(_ url: URL, size: Int = 240) throws -> CGImage {
    guard let source = CGImageSourceCreateWithURL(url as CFURL, nil),
      CGImageSourceGetStatus(source) == .statusComplete,
      let image = CGImageSourceCreateThumbnailAtIndex(source, 0, [
        kCGImageSourceCreateThumbnailFromImageAlways: true,
        kCGImageSourceCreateThumbnailWithTransform: true,
        kCGImageSourceThumbnailMaxPixelSize: size,
      ] as CFDictionary) else { throw PreparationError.invalid("JPEG could not be read completely.") }
    return image
  }

  public static func isBlack(_ image: CGImage) -> Bool {
    let size = 64
    var pixels = [UInt8](repeating: 0, count: size * size)
    let drawn = pixels.withUnsafeMutableBytes { buffer -> Bool in
      guard let context = CGContext(data: buffer.baseAddress, width: size, height: size,
        bitsPerComponent: 8, bytesPerRow: size, space: CGColorSpaceCreateDeviceGray(), bitmapInfo: 0) else { return false }
      context.draw(image, in: CGRect(x: 0, y: 0, width: size, height: size))
      return true
    }
    guard drawn else { return false }
    return Double(pixels.reduce(0) { $0 + Int($1) }) / Double(pixels.count) < 12
      && Double(pixels.filter { $0 < 24 }.count) / Double(pixels.count) >= 0.995
  }

  public struct Fingerprint: Equatable {
    let bytes: Int
    let modified: Date
  }

  public static func fingerprint(_ url: URL) throws -> Fingerprint {
    guard url.standardizedFileURL == url.resolvingSymlinksInPath().standardizedFileURL else {
      throw PreparationError.invalid("Symbolic links are not supported for import.")
    }
    var freshURL = URL(fileURLWithPath: url.path)
    freshURL.removeAllCachedResourceValues()
    let values = try freshURL.resourceValues(forKeys: [.isRegularFileKey, .fileSizeKey, .contentModificationDateKey])
    guard values.isRegularFile == true, let bytes = values.fileSize, let date = values.contentModificationDate else {
      throw PreparationError.invalid("The source photo is no longer available.")
    }
    return Fingerprint(bytes: bytes, modified: date)
  }

  public static func validate(_ photo: ImportPhoto) throws {
    let current = try fingerprint(photo.source)
    guard current.bytes == photo.bytes, current.modified == photo.modified else {
      throw PreparationError.invalid("\(photo.filename) changed after scanning. Scan the source folder again.")
    }
    guard photo.filename == (photo.filename as NSString).lastPathComponent,
      !photo.filename.hasPrefix("."), !photo.filename.contains("/"), !photo.filename.contains(":"),
      ["jpg", "jpeg"].contains((photo.filename as NSString).pathExtension.lowercased()) else {
      throw PreparationError.invalid("Use a JPEG filename without folders for \(photo.filename).")
    }
  }

  public static func copy(_ photo: ImportPhoto, to destination: URL) throws {
    try validate(photo)
    let temporary = destination.deletingLastPathComponent().appendingPathComponent(".import-" + UUID().uuidString)
    try FileManager.default.copyItem(at: photo.source, to: temporary)
    defer { try? FileManager.default.removeItem(at: temporary) }
    try validate(photo)
    guard try temporary.resourceValues(forKeys: [.fileSizeKey]).fileSize == photo.bytes else {
      throw PreparationError.invalid("The copied file size does not match \(photo.filename).")
    }
    // moveItem refuses an existing destination; never overwrite a different photo.
    try FileManager.default.moveItem(at: temporary, to: destination)
  }
}
