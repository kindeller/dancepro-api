import Foundation

final class UploadStubState: @unchecked Sendable {
  private let lock = NSLock()
  private var responses: [String: Data] = [:]
  private var requests: [String] = []
  private var hold = false
  private var transientFailures = 0
  private var offline = false

  func configure(_ responses: [String: Data], hold: Bool = false, transientFailures: Int = 0, offline: Bool = false) {
    lock.lock()
    defer { lock.unlock() }
    self.responses = responses
    self.hold = hold
    self.transientFailures = transientFailures
    self.offline = offline
    requests = []
  }
  func paths() -> [String] {
    lock.lock()
    defer { lock.unlock() }
    return requests
  }
  func response(_ path: String) -> (Int, Data)? {
    lock.lock()
    defer { lock.unlock() }
    requests.append(path)
    if offline { return (URLError.notConnectedToInternet.rawValue, Data()) }
    if path.hasSuffix("/content") {
      if hold { return nil }
      if transientFailures > 0 {
        transientFailures -= 1
        return (502, Data(#"{"message":"Temporary network gateway failure"}"#.utf8))
      }
    }
    return responses[path].map { (200, $0) } ?? (
      404, Data(#"{"message":"Unknown test route"}"#.utf8)
    )
  }
}

final class PhotoUploadProtocol: URLProtocol, @unchecked Sendable {
  static let state = UploadStubState()
  override class func canInit(with request: URLRequest) -> Bool {
    request.url?.host == "uploads.test"
  }
  override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
  override func startLoading() {
    guard let url = request.url, let (status, data) = Self.state.response(url.path) else { return }
    if status < 0 {
      client?.urlProtocol(self, didFailWithError: URLError(URLError.Code(rawValue: status)))
      return
    }
    let response = HTTPURLResponse(
      url: url, statusCode: status, httpVersion: "HTTP/1.1",
      headerFields: ["Content-Type": "application/json"])!
    client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
    client?.urlProtocol(self, didLoad: data)
    client?.urlProtocolDidFinishLoading(self)
  }
  override func stopLoading() {}
}
