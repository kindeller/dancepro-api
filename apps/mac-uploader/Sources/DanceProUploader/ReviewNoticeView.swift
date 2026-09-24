import SwiftUI

struct ReviewNoticeView: View {
  @ObservedObject var model: PhotoReviewModel

  var body: some View {
    if let notice = model.notice {
      HStack(spacing: 12) {
        Image(
          systemName: model.noticeUndo == .move
            ? "arrow.right.square.fill"
            : model.noticeUndo == .trash ? "trash.fill" : "checkmark.circle.fill"
        )
        .foregroundStyle(
          model.noticeUndo == .move
            ? Color.cyan : model.noticeUndo == .trash ? Color.red : Color.green)
        Text(notice).font(.body.weight(.medium)).lineLimit(2).truncationMode(.middle)
        if let action = model.noticeUndo {
          Button("Undo") { Task { await model.undoNotice() } }
            .buttonStyle(.borderedProminent)
            .disabled(model.busy || (action == .move ? !model.canUndoMove : !model.canUndo))
        }
      }
      .padding(.horizontal, 16).padding(.vertical, 12)
      .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 10))
      .overlay(RoundedRectangle(cornerRadius: 10).stroke(Color.white.opacity(0.16)))
      .shadow(radius: 8, y: 3)
      .padding(16)
      .transition(.opacity)
    }
  }
}
