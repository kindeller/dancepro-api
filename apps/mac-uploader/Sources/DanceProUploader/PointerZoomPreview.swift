import AppKit
import SwiftUI

/// AppKit keeps the content point under the pointer fixed while magnifying.
struct PointerZoomPreview: NSViewRepresentable {
  let image: NSImage
  let photoKey: String
  let fitRequest: UUID

  func makeNSView(context: Context) -> PointerZoomScrollView {
    PointerZoomScrollView(frame: .zero)
  }
  func updateNSView(_ view: PointerZoomScrollView, context: Context) {
    view.display(image, photoKey: photoKey, fitRequest: fitRequest)
  }
}

final class PointerZoomScrollView: NSScrollView {
  private let photoView = NSImageView()
  private var photoKey: String?
  private var fitRequest: UUID?
  private var viewportSize = NSSize.zero
  override var acceptsFirstResponder: Bool { false }

  override init(frame frameRect: NSRect) {
    super.init(frame: frameRect)
    drawsBackground = false
    borderType = .noBorder
    hasVerticalScroller = true
    hasHorizontalScroller = true
    autohidesScrollers = true
    scrollerStyle = .overlay
    allowsMagnification = true
    minMagnification = 1
    maxMagnification = 5
    photoView.imageScaling = .scaleProportionallyUpOrDown
    photoView.imageAlignment = .alignCenter
    photoView.refusesFirstResponder = true
    documentView = photoView
  }
  required init?(coder: NSCoder) { fatalError("init(coder:) is not used") }

  func display(_ image: NSImage, photoKey: String, fitRequest: UUID) {
    photoView.image = image
    if self.photoKey != photoKey || self.fitRequest != fitRequest {
      self.photoKey = photoKey
      self.fitRequest = fitRequest
      fit()
    }
  }
  override func layout() {
    super.layout()
    let size = contentView.frame.size
    if size != viewportSize {
      viewportSize = size
      photoView.setFrameSize(size)
      fit()
    }
  }
  func fit() {
    magnification = 1
    contentView.scroll(to: .zero)
    reflectScrolledClipView(contentView)
  }
  override func magnify(with event: NSEvent) {
    let point = contentView.convert(event.locationInWindow, from: nil)
    zoom(by: event.magnification, at: point)
  }
  func zoom(by delta: CGFloat, at point: NSPoint) {
    let scale = min(maxMagnification, max(minMagnification, magnification * (1 + delta)))
    setMagnification(scale, centeredAt: point)
  }
}
