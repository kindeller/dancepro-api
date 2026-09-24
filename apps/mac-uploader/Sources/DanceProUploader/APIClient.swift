import Foundation
import PreparationCore

struct APIEnvelope<Value: Decodable>: Decodable {
  let data: Value
  let meta: PageMeta?
}
struct PageMeta: Decodable {
  let currentPage: Int
  let lastPage: Int
}
struct LoginData: Decodable {
  let token: String
  let user: LoginUser
}
struct LoginUser: Decodable { let name: String }
struct SavedSession: Codable {
  let server: String
  let token: String
  let name: String
}

final class NoRedirects: NSObject, URLSessionTaskDelegate, @unchecked Sendable {
  func urlSession(
    _ session: URLSession, task: URLSessionTask,
    willPerformHTTPRedirection response: HTTPURLResponse,
    newRequest request: URLRequest, completionHandler: @escaping @Sendable (URLRequest?) -> Void
  ) {
    completionHandler(nil)
  }
}

enum APIError: LocalizedError {
  case expired
  case server(Int, String)
  var errorDescription: String? {
    switch self {
    case .expired:
      return "Your sign-in has expired. Sign in again. Prepared events remain available offline."
    case .server(_, let message): return message
    }
  }
}

struct APIClient {
  let base: URL
  let token: String?
  private let session: URLSession

  init(server: String, token: String? = nil, configuration: URLSessionConfiguration = .ephemeral)
    throws
  {
    guard let parts = URLComponents(string: server.trimmingCharacters(in: .whitespacesAndNewlines)),
      let host = parts.host, !host.isEmpty, parts.user == nil, parts.password == nil,
      parts.query == nil, parts.fragment == nil, parts.path.isEmpty || parts.path == "/",
      parts.scheme == "https"
        || (parts.scheme == "http" && ["localhost", "127.0.0.1", "[::1]"].contains(host)),
      let url = parts.url
    else {
      throw PreparationError.invalid(
        "Enter your DancePro server address using HTTPS. HTTP is allowed only for localhost development."
      )
    }
    base = url
    self.token = token
    let config = configuration
    config.timeoutIntervalForRequest = 45
    config.timeoutIntervalForResource = 3600
    config.httpCookieStorage = nil
    config.urlCredentialStorage = nil
    session = URLSession(configuration: config, delegate: NoRedirects(), delegateQueue: nil)
  }

  func request(_ path: String, body: [String: String]? = nil) async throws -> Data {
    try await request(path, jsonBody: body.map { try JSONSerialization.data(withJSONObject: $0) })
  }

  func request(_ path: String, jsonBody: Data?) async throws -> Data {
    guard path.hasPrefix("/api/"), !path.contains(".."),
      let url = URL(string: path, relativeTo: base)?.absoluteURL,
      url.scheme == base.scheme, url.host == base.host, url.port == base.port
    else {
      throw PreparationError.invalid("The server returned an invalid download address.")
    }
    var request = URLRequest(url: url)
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    if let token { request.setValue("Bearer " + token, forHTTPHeaderField: "Authorization") }
    if let jsonBody {
      request.httpMethod = "POST"
      request.setValue("application/json", forHTTPHeaderField: "Content-Type")
      request.httpBody = jsonBody
    }
    let (data, response) = try await session.data(for: request)
    return try checked(data, response: response)
  }

  func upload(_ path: String, from file: URL) async throws -> Data {
    guard path.hasPrefix("/api/"), !path.contains(".."),
      let url = URL(string: path, relativeTo: base)?.absoluteURL,
      url.scheme == base.scheme, url.host == base.host, url.port == base.port, token != nil
    else {
      throw PreparationError.invalid("Invalid photo upload destination.")
    }
    var request = URLRequest(url: url)
    request.httpMethod = "PUT"
    request.timeoutInterval = 180
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    request.setValue("image/jpeg", forHTTPHeaderField: "Content-Type")
    request.setValue("Bearer " + token!, forHTTPHeaderField: "Authorization")
    let (data, response) = try await session.upload(for: request, fromFile: file)
    return try checked(data, response: response)
  }

  func uploadPresigned(_ address: String, headers: [String: String], from file: URL) async throws {
    guard let url = URL(string: address), let host = url.host,
      url.user == nil, url.password == nil,
      url.scheme == "https" || (url.scheme == "http" && ["localhost", "127.0.0.1", "::1"].contains(host))
    else { throw PreparationError.invalid("DancePro returned an invalid video upload address.") }
    var request = URLRequest(url: url)
    request.httpMethod = "PUT"
    request.timeoutInterval = 3600
    for (name, value) in headers { request.setValue(value, forHTTPHeaderField: name) }
    let (data, response) = try await session.upload(for: request, fromFile: file)
    _ = try checked(data, response: response)
  }

  private func checked(_ data: Data, response: URLResponse) throws -> Data {
    guard let response = response as? HTTPURLResponse else {
      throw PreparationError.invalid("No response from DancePro.")
    }
    guard (200...299).contains(response.statusCode) else {
      if response.statusCode == 401, token != nil { throw APIError.expired }
      if response.statusCode == 401 {
        throw PreparationError.invalid(
          "Your sign-in has expired or the details are incorrect. Sign in again. Prepared events remain available offline."
        )
      }
      let json = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any]
      let message =
        json?["message"] as? String ?? "DancePro returned an error (\(response.statusCode))."
      throw APIError.server(response.statusCode, message)
    }
    return data
  }

  func decode<Value: Decodable>(_ type: Value.Type, path: String, body: [String: String]? = nil)
    async throws -> APIEnvelope<Value>
  {
    try ProgrammeCoding.decoder().decode(
      APIEnvelope<Value>.self, from: await request(path, body: body))
  }
}
