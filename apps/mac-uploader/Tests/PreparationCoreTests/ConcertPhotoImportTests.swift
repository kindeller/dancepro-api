import CoreGraphics
import ImageIO
import XCTest
@testable import PhotoReviewCore
@testable import PreparationCore

final class ConcertPhotoImportTests: XCTestCase {
  private func directory() throws -> URL {
    let url = FileManager.default.temporaryDirectory.appendingPathComponent("concert-import-" + UUID().uuidString)
    try FileManager.default.createDirectory(at: url, withIntermediateDirectories: true)
    addTeardownBlock { try FileManager.default.removeItem(at: url) }
    return url
  }

  private func jpeg(_ root: URL, _ name: String, black: Bool = false, subject: Bool = false) throws -> URL {
    let url = root.appendingPathComponent(name)
    let context = CGContext(data: nil, width: 100, height: 100, bitsPerComponent: 8, bytesPerRow: 400,
      space: CGColorSpaceCreateDeviceRGB(), bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
    context.setFillColor(black ? CGColor(gray: 0, alpha: 1) : CGColor(red: 0.5, green: 0.3, blue: 0.7, alpha: 1))
    context.fill(CGRect(x: 0, y: 0, width: 100, height: 100))
    if subject {
      context.setFillColor(CGColor(gray: 1, alpha: 1))
      context.fill(CGRect(x: 40, y: 40, width: 20, height: 20))
    }
    let output = CGImageDestinationCreateWithURL(url as CFURL, "public.jpeg" as CFString, 1, nil)!
    CGImageDestinationAddImage(output, context.makeImage()!, nil)
    XCTAssertTrue(CGImageDestinationFinalize(output))
    return url
  }

  private func prepared(_ destination: URL) throws -> (URL, PreparedEvent) {
    let programme = Programme(schemaVersion: 1, eventUuid: UUID().uuidString, eventName: "Concert",
      eventType: "concert", programmeUuid: UUID().uuidString, revisionUuid: UUID().uuidString, version: 1,
      nodes: [ProgrammeNode(uuid: UUID().uuidString, kind: "item", title: "Ballet", folderNumber: "01", folderTitle: "Ballet"),
              ProgrammeNode(uuid: UUID().uuidString, kind: "item", title: "Jazz", folderNumber: "02", folderTitle: "Jazz")])
    let root = try LocalPreparation.prepare(programme: programme, pdf: nil, server: "test", destination: destination)
    let manifest = try ProgrammeCoding.decoder().decode(PreparedEvent.self, from: Data(contentsOf: root.appendingPathComponent("preparation.json")))
    return (root, manifest)
  }

  func testBlackSeparatorsCreateGroupsWithoutEmptyGroupsAndKeepLitSubjects() throws {
    let source = try directory()
    for n in [1, 4, 5, 8] { _ = try jpeg(source, "photo\(n).jpg", black: true) }
    for n in [2, 3, 6] { _ = try jpeg(source, "photo\(n).jpg") }
    _ = try jpeg(source, "photo7.jpg", black: true, subject: true)
    let plan = try ConcertPhotoImport.scan(source, itemIDs: ["ballet", "jazz"])
    XCTAssertEqual(plan.groups.map { $0.photos.map(\.filename) }, [["photo2.jpg", "photo3.jpg"], ["photo6.jpg", "photo7.jpg"]])
    XCTAssertEqual(plan.groups.map(\.itemID), ["ballet", "jazz"])
    XCTAssertEqual(plan.separators.count, 4)
    XCTAssertTrue(plan.skipped.isEmpty)
    XCTAssertEqual(try FileManager.default.contentsOfDirectory(atPath: source.path).count, 8)
  }

  func testPortraitImportKeepsOneFolderIncludingDarkPhotos() throws {
    let source = try directory()
    _ = try jpeg(source, "photo1.jpg")
    _ = try jpeg(source, "photo2.jpg", black: true)
    try Data("unreadable".utf8).write(to: source.appendingPathComponent("photo3.jpg"))
    _ = try jpeg(source, "photo4.jpg")
    let plan = try ConcertPhotoImport.scan(source, itemIDs: ["portraits"], separateBlackFrames: false)
    XCTAssertEqual(plan.groups.count, 1)
    XCTAssertEqual(plan.groups[0].itemID, "portraits")
    XCTAssertEqual(plan.groups[0].photos.map(\.filename), ["photo1.jpg", "photo2.jpg", "photo4.jpg"])
    XCTAssertTrue(plan.separators.isEmpty)
    XCTAssertEqual(plan.skipped.count, 1)
  }

  func testReviewCanSplitCombineExcludeAndRestore() throws {
    let source = try directory()
    for n in 1...3 { _ = try jpeg(source, "photo\(n).jpg") }
    var plan = try ConcertPhotoImport.scan(source, itemIDs: ["ballet", "jazz"])
    let first = plan.groups[0].id
    let secondPhoto = plan.groups[0].photos[1].id
    plan.split(groupID: first, before: secondPhoto)
    XCTAssertEqual(plan.groups.map { $0.photos.count }, [1, 2])
    XCTAssertNil(plan.groups[1].itemID)
    plan.mergeWithPrevious(plan.groups[1].id)
    XCTAssertEqual(plan.groups[0].photos.count, 3)
    plan.exclude(secondPhoto, from: first)
    XCTAssertEqual(plan.separators.count, 1)
    plan.restore(secondPhoto, to: first)
    XCTAssertEqual(plan.groups[0].photos.map(\.filename), ["photo1.jpg", "photo2.jpg", "photo3.jpg"])
  }

  func testImportCopiesGroupsButNotSeparatorsAndPreservesSource() async throws {
    let source = try directory()
    let a = try jpeg(source, "photo1.jpg")
    _ = try jpeg(source, "photo2.jpg", black: true)
    _ = try jpeg(source, "photo3.jpg")
    let original = try Data(contentsOf: a)
    let (root, manifest) = try prepared(directory())
    let library = try PhotoLibrary(root: root, manifest: manifest, quietInterval: 0)
    let plan = try ConcertPhotoImport.scan(source, itemIDs: manifest.folders.map(\.itemUuid))
    let result = try await library.importConcertPhotos(plan)
    XCTAssertEqual(result.copiedIDs.count, 2)
    XCTAssertNil(result.error)
    XCTAssertEqual(try Data(contentsOf: a), original)
    XCTAssertEqual(try Data(contentsOf: root.appendingPathComponent(manifest.folders[0].relativePath + "/photo1.jpg")), original)
    XCTAssertFalse(FileManager.default.fileExists(atPath: root.appendingPathComponent(manifest.folders[0].relativePath + "/photo2.jpg").path))
    do { _ = try await library.importConcertPhotos(plan); XCTFail("Collision accepted") } catch {}
    XCTAssertEqual(try Data(contentsOf: a), original)
  }

  func testChangedSourceAndUnsafeRenameAreRejectedBeforeCopying() async throws {
    let source = try directory()
    let photo = try jpeg(source, "photo1.jpg")
    let (root, manifest) = try prepared(directory())
    let library = try PhotoLibrary(root: root, manifest: manifest)
    var plan = try ConcertPhotoImport.scan(source, itemIDs: manifest.folders.map(\.itemUuid))
    plan.groups[0].photos[0].filename = "../escape.jpg"
    do { _ = try await library.importConcertPhotos(plan); XCTFail("Unsafe filename accepted") } catch {}
    plan.groups[0].photos[0].filename = "photo1.jpg"
    try Data("changed".utf8).write(to: photo)
    do { _ = try await library.importConcertPhotos(plan); XCTFail("Changed source accepted") } catch {}
    XCTAssertFalse(FileManager.default.fileExists(atPath: root.appendingPathComponent(manifest.folders[0].relativePath + "/photo1.jpg").path))
  }

  func testUnreadableImagesBreakGroupsAndExtraGroupsNeedADestination() throws {
    let source = try directory()
    _ = try jpeg(source, "photo1.jpg")
    try Data("not a JPEG".utf8).write(to: source.appendingPathComponent("photo2.jpg"))
    _ = try jpeg(source, "photo3.jpg")
    let plan = try ConcertPhotoImport.scan(source, itemIDs: ["ballet"])
    XCTAssertEqual(plan.skipped.count, 1)
    XCTAssertEqual(plan.groups.count, 2)
    XCTAssertNil(plan.groups[1].itemID)
  }

  func testDuplicateDestinationNamesAreRejectedWithoutPartialCopy() async throws {
    let source = try directory()
    _ = try jpeg(source, "photo1.jpg")
    _ = try jpeg(source, "photo2.jpg")
    let (root, manifest) = try prepared(directory())
    let library = try PhotoLibrary(root: root, manifest: manifest)
    var plan = try ConcertPhotoImport.scan(source, itemIDs: manifest.folders.map(\.itemUuid))
    plan.groups[0].photos[1].filename = "photo1.jpg"
    do { _ = try await library.importConcertPhotos(plan); XCTFail("Duplicate filename accepted") } catch {}
    XCTAssertFalse(FileManager.default.fileExists(atPath: root.appendingPathComponent(manifest.folders[0].relativePath + "/photo1.jpg").path))
  }
}
