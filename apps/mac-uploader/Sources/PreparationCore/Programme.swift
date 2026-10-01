import Foundation

public struct EventSummary: Codable, Identifiable, Hashable, Sendable {
  public var uuid: String
  public var name: String
  public var studioName: String? = nil
  public var eventTitle: String? = nil
  public var eventType: String
  public var eventDate: String?
  public var eventCode: String?
  public var revisionUuid: String
  public var itemCount: Int
  public var hasPdf: Bool
  public var captureMode: String? = nil
  public var preparationReady: Bool
  public var id: String { uuid }
}

public struct ProgrammeNode: Codable, Identifiable, Sendable {
  public var uuid: String
  public var parentUuid: String?
  public var kind: String
  public var title: String
  public var number: String?
  public var date: String?
  public var className: String?
  public var itemKind: String?
  public var folderNumber: String?
  public var folderTitle: String?
  public var mergedIntoUuid: String? = nil
  public var isActiveItem: Bool { kind == "item" && itemKind != "break" && mergedIntoUuid == nil }
  public var id: String { uuid }
}

public struct Programme: Codable, Sendable {
  public var schemaVersion: Int
  public var eventUuid: String
  public var eventCode: String?
  public var eventName: String
  public var eventType: String
  public var programmeUuid: String
  public var revisionUuid: String
  public var version: Int
  public var sourceUuid: String?
  public var sourceUrl: String?
  public var nodes: [ProgrammeNode]
  public var captureMode: String? = nil
  public var isPortraitSession: Bool { captureMode == "dress_rehearsal" }
}

public enum PreparationError: LocalizedError, Equatable {
  case invalid(String)
  public var errorDescription: String? {
    if case .invalid(let message) = self { return message }
    return "Unable to prepare this event."
  }
}

public enum ProgrammeCoding {
  public static func decoder() -> JSONDecoder {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return decoder
  }
  public static func encoder() -> JSONEncoder {
    let encoder = JSONEncoder()
    encoder.keyEncodingStrategy = .convertToSnakeCase
    encoder.outputFormatting = [.prettyPrinted, .sortedKeys]
    return encoder
  }
}
