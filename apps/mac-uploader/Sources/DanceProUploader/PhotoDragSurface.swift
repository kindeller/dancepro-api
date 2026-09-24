import AppKit
import SwiftUI

/// Use the same native pasteboard type at both ends. This also keeps thumbnail
/// button tracking from consuming the mouse drag before a session can start.
struct PhotoDragSurface: NSViewRepresentable {
  var image: NSImage? = nil
  var payload: (() -> String?)? = nil
  var accepts: ((String) -> Bool)? = nil
  var dropped: ((String) -> Bool)? = nil
  var targeted: (Bool) -> Void = { _ in }
  let clicked: () -> Void

  func makeNSView(context: Context) -> PhotoDragView { PhotoDragView() }
  func updateNSView(_ view: PhotoDragView, context: Context) {
    view.preview = image
    view.payload = payload
    view.accepts = accepts
    view.dropped = dropped
    view.targeted = targeted
    view.clicked = clicked
  }
}

final class PhotoDragView: NSView, NSDraggingSource {
  static let pasteboardType = NSPasteboard.PasteboardType("au.com.dancepro.uploader.photo-move")
  var preview: NSImage?
  var payload: (() -> String?)?
  var accepts: ((String) -> Bool)?
  var dropped: ((String) -> Bool)?
  var targeted: (Bool) -> Void = { _ in }
  var clicked: () -> Void = {}
  private var start: NSPoint?
  private var dragging = false

  override init(frame: NSRect) {
    super.init(frame: frame)
    registerForDraggedTypes([Self.pasteboardType])
  }
  required init?(coder: NSCoder) { fatalError("init(coder:) is not used") }
  override func acceptsFirstMouse(for event: NSEvent?) -> Bool { true }
  override func mouseDown(with event: NSEvent) {
    start = convert(event.locationInWindow, from: nil)
    dragging = false
  }
  override func mouseUp(with event: NSEvent) {
    if !dragging, bounds.contains(convert(event.locationInWindow, from: nil)) { clicked() }
    start = nil
  }
  override func mouseDragged(with event: NSEvent) {
    guard !dragging, let start, let text = payload?() else { return }
    let point = convert(event.locationInWindow, from: nil)
    guard hypot(point.x - start.x, point.y - start.y) >= 4 else { return }
    dragging = true
    let item = NSPasteboardItem()
    item.setString(text, forType: Self.pasteboardType)
    let drag = NSDraggingItem(pasteboardWriter: item)
    let count = max(1, text.components(separatedBy: "\n").count - 1)
    let image = NSImage(size: NSSize(width: 130, height: 108), flipped: false) { rect in
      NSColor.windowBackgroundColor.withAlphaComponent(0.95).setFill()
      NSBezierPath(roundedRect: rect, xRadius: 8, yRadius: 8).fill()
      self.preview?.draw(
        in: NSRect(x: 8, y: 28, width: 114, height: 72), from: .zero, operation: .sourceOver,
        fraction: 1, respectFlipped: true, hints: nil)
      ("\(count) photo\(count == 1 ? "" : "s")" as NSString).draw(
        at: NSPoint(x: 10, y: 8),
        withAttributes: [
          .font: NSFont.boldSystemFont(ofSize: 13), .foregroundColor: NSColor.labelColor,
        ])
      return true
    }
    drag.setDraggingFrame(
      NSRect(x: point.x - 65, y: point.y - 54, width: 130, height: 108), contents: image)
    beginDraggingSession(with: [drag], event: event, source: self)
  }
  func draggingSession(
    _ session: NSDraggingSession, sourceOperationMaskFor context: NSDraggingContext
  ) -> NSDragOperation { .move }
  func ignoreModifierKeys(for session: NSDraggingSession) -> Bool { true }
  override func draggingEntered(_ sender: NSDraggingInfo) -> NSDragOperation {
    updateTarget(sender)
  }
  override func draggingUpdated(_ sender: NSDraggingInfo) -> NSDragOperation {
    updateTarget(sender)
  }
  override func draggingExited(_ sender: NSDraggingInfo?) { targeted(false) }
  override func draggingEnded(_ sender: NSDraggingInfo) { targeted(false) }
  override func performDragOperation(_ sender: NSDraggingInfo) -> Bool {
    targeted(false)
    guard let text = sender.draggingPasteboard.string(forType: Self.pasteboardType),
      accepts?(text) == true
    else { return false }
    return dropped?(text) ?? false
  }
  private func updateTarget(_ sender: NSDraggingInfo) -> NSDragOperation {
    let valid =
      sender.draggingPasteboard.string(forType: Self.pasteboardType).map { accepts?($0) == true }
      ?? false
    targeted(valid)
    return valid ? .move : []
  }
}
