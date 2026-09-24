import PreparationCore
import XCTest

@testable import DanceProUploader

final class APIContractTests: XCTestCase {
  func testOnlyHttpsOrExactLoopbackServersAreAccepted() throws {
    for address in [
      "https://dancepro.test", "http://localhost", "http://127.0.0.1:8080", "http://[::1]",
    ] {
      XCTAssertNoThrow(try APIClient(server: address))
    }
    for address in [
      "http://dancepro.test", "http://localhost.evil.test", "https://user:password@dancepro.test",
      "https://dancepro.test?token=secret", "file:///tmp", "https://dancepro.test/other",
      "https://dancepro.test/#fragment",
    ] {
      XCTAssertThrowsError(try APIClient(server: address))
    }
  }
  func testApiEnvelopeDecodesServerSnakeCaseAndNullableFields() throws {
    let json =
      #"{"success":true,"data":[{"uuid":"event","name":"Test","event_type":"concert","event_date":null,"event_code":null,"revision_uuid":"revision","item_count":2,"has_pdf":true,"preparation_ready":true}],"meta":{"current_page":1,"last_page":2}}"#
    let value = try ProgrammeCoding.decoder().decode(
      APIEnvelope<[EventSummary]>.self, from: Data(json.utf8))
    XCTAssertTrue(value.data[0].hasPdf)
    XCTAssertEqual(value.data[0].revisionUuid, "revision")
    XCTAssertEqual(value.meta?.lastPage, 2)
  }
  func testBearerRequestsRejectOtherOriginsBeforeNetworking() async throws {
    let client = try APIClient(server: "https://dancepro.test", token: "test-only-placeholder")
    for path in [
      "https://other.test/api/file", "//other.test/api/file", "/api/../outside", "/not-api",
    ] {
      do {
        _ = try await client.request(path)
        XCTFail("Unsafe URL accepted")
      } catch { XCTAssertTrue(error is PreparationError) }
    }
  }
}
