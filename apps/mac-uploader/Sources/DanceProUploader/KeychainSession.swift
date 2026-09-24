import Foundation
import PreparationCore
import Security

/// Only this app's own session is read. Passwords and recovery codes are never persisted.
enum KeychainSession {
  private static var query: [String: Any] {
    [
      kSecClass as String: kSecClassGenericPassword,
      kSecAttrService as String: "au.com.dancepro.uploader.session",
      kSecAttrAccount as String: "current-session",
    ]
  }
  static func read() throws -> SavedSession? {
    var attributes = query
    attributes[kSecReturnData as String] = true
    attributes[kSecMatchLimit as String] = kSecMatchLimitOne
    var result: CFTypeRef?
    let status = SecItemCopyMatching(attributes as CFDictionary, &result)
    if status == errSecItemNotFound { return nil }
    guard status == errSecSuccess, let data = result as? Data else { throw failure(status) }
    return try JSONDecoder().decode(SavedSession.self, from: data)
  }
  static func save(_ session: SavedSession) throws {
    let data = try JSONEncoder().encode(session)
    let status = SecItemUpdate(
      query as CFDictionary, [kSecValueData as String: data] as CFDictionary)
    if status == errSecItemNotFound {
      var attributes = query
      attributes[kSecValueData as String] = data
      attributes[kSecAttrAccessible as String] = kSecAttrAccessibleWhenUnlockedThisDeviceOnly
      let added = SecItemAdd(attributes as CFDictionary, nil)
      guard added == errSecSuccess else { throw failure(added) }
    } else if status != errSecSuccess {
      throw failure(status)
    }
  }
  static func remove() throws {
    let status = SecItemDelete(query as CFDictionary)
    guard status == errSecSuccess || status == errSecItemNotFound else { throw failure(status) }
  }
  private static func failure(_ status: OSStatus) -> PreparationError {
    .invalid(
      "macOS Keychain could not save or read this app’s sign-in (\(status)). Unlock your login Keychain and retry."
    )
  }
}
