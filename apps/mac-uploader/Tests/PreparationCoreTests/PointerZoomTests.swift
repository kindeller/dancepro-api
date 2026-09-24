import AppKit
import XCTest

@testable import DanceProUploader

@MainActor
final class PointerZoomTests: XCTestCase {
  func testZoomKeepsPointerLocationFixedAndFitResets() {
    let view = PointerZoomScrollView(frame: NSRect(x: 0, y: 0, width: 800, height: 600))
    view.layoutSubtreeIfNeeded()
    let point = NSPoint(x: 520, y: 330)
    let before = view.contentView.convert(point, to: view)
    view.zoom(by: 1, at: point)
    let after = view.contentView.convert(point, to: view)
    XCTAssertEqual(view.magnification, 2, accuracy: 0.01)
    XCTAssertEqual(before.x, after.x, accuracy: 1)
    XCTAssertEqual(before.y, after.y, accuracy: 1)
    view.fit()
    XCTAssertEqual(view.magnification, 1, accuracy: 0.01)
    XCTAssertEqual(view.contentView.bounds.origin.x, 0, accuracy: 1)
    XCTAssertEqual(view.contentView.bounds.origin.y, 0, accuracy: 1)
  }
  func testPhotoChangeResetsZoomButOrdinaryUpdatesKeepIt() {
    let view = PointerZoomScrollView(frame: NSRect(x: 0, y: 0, width: 800, height: 600))
    view.layoutSubtreeIfNeeded()
    let request = UUID()
    let image = NSImage(size: NSSize(width: 800, height: 600))
    view.display(image, photoKey: "one", fitRequest: request)
    view.zoom(by: 1, at: NSPoint(x: 400, y: 300))
    view.display(image, photoKey: "one", fitRequest: request)
    XCTAssertEqual(view.magnification, 2, accuracy: 0.01)
    view.display(image, photoKey: "two", fitRequest: request)
    XCTAssertEqual(view.magnification, 1, accuracy: 0.01)
  }
}
