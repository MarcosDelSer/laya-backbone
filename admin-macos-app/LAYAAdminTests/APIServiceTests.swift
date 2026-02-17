//
//  APIServiceTests.swift
//  LAYAAdminTests
//
//  Unit tests for APIService verifying request/response handling,
//  error cases, URL building, header construction, and retry logic.
//

import XCTest
import Alamofire
@testable import LAYAAdmin

// MARK: - Mock URL Protocol

/// Mock URL protocol for intercepting network requests in tests
final class MockURLProtocol: URLProtocol {

    /// Handler for processing mock requests
    static var requestHandler: ((URLRequest) throws -> (HTTPURLResponse, Data))?

    /// Captured requests for verification
    static var capturedRequests: [URLRequest] = []

    override class func canInit(with request: URLRequest) -> Bool {
        return true
    }

    override class func canonicalRequest(for request: URLRequest) -> URLRequest {
        return request
    }

    override func startLoading() {
        MockURLProtocol.capturedRequests.append(request)

        guard let handler = MockURLProtocol.requestHandler else {
            XCTFail("Mock request handler not set")
            return
        }

        do {
            let (response, data) = try handler(request)
            client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
            client?.urlProtocol(self, didLoad: data)
            client?.urlProtocolDidFinishLoading(self)
        } catch {
            client?.urlProtocol(self, didFailWithError: error)
        }
    }

    override func stopLoading() {
        // No-op
    }

    /// Reset captured requests between tests
    static func reset() {
        capturedRequests = []
        requestHandler = nil
    }
}

// MARK: - Test Models

/// Simple test model for decoding responses
struct TestResponse: Codable, Equatable {
    let id: String
    let name: String
    let value: Int
}

/// Test request model for encoding
struct TestRequest: Codable {
    let title: String
    let count: Int
}

// MARK: - APIService Tests

final class APIServiceTests: XCTestCase {

    // MARK: - Properties

    var sut: APIService!
    var mockConfiguration: URLSessionConfiguration!

    // MARK: - Setup / Teardown

    override func setUp() {
        super.setUp()

        // Configure mock URL session
        mockConfiguration = URLSessionConfiguration.ephemeral
        mockConfiguration.protocolClasses = [MockURLProtocol.self]

        // Create API service with mock configuration
        sut = APIService(target: .gibbon, configuration: mockConfiguration)

        // Reset mock state
        MockURLProtocol.reset()
    }

    override func tearDown() {
        sut = nil
        mockConfiguration = nil
        MockURLProtocol.reset()
        super.tearDown()
    }

    // MARK: - URL Building Tests

    func testBuildURL_withRelativePath_prependsBaseURL() async throws {
        // Given
        let expectedPath = "/api/v1/test"
        MockURLProtocol.requestHandler = { request in
            XCTAssertTrue(request.url?.absoluteString.contains(expectedPath) ?? false)
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then - should not throw
        let _: TestResponse = try await sut.get(expectedPath)
    }

    func testBuildURL_withAbsoluteURL_usesAsIs() async throws {
        // Given
        let absoluteURL = "https://custom.api.com/test"
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.url?.absoluteString, absoluteURL)
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get(absoluteURL)
    }

    func testBuildURL_withoutLeadingSlash_addsSlash() async throws {
        // Given
        let pathWithoutSlash = "api/test"
        MockURLProtocol.requestHandler = { request in
            XCTAssertTrue(request.url?.path.starts(with: "/") ?? false)
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get(pathWithoutSlash)
    }

    // MARK: - Header Building Tests

    func testHeaders_includesContentTypeJSON() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.value(forHTTPHeaderField: "Content-Type"), "application/json")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test")
    }

    func testHeaders_includesAcceptJSON() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.value(forHTTPHeaderField: "Accept"), "application/json")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test")
    }

    func testHeaders_withAuthToken_includesBearer() async throws {
        // Given
        let testToken = "test-jwt-token-12345"
        sut.authTokenProvider = { testToken }

        MockURLProtocol.requestHandler = { request in
            let authHeader = request.value(forHTTPHeaderField: "Authorization")
            XCTAssertEqual(authHeader, "Bearer \(testToken)")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test")
    }

    func testHeaders_withoutAuthToken_noAuthHeader() async throws {
        // Given
        sut.authTokenProvider = nil

        MockURLProtocol.requestHandler = { request in
            XCTAssertNil(request.value(forHTTPHeaderField: "Authorization"))
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test")
    }

    func testHeaders_withCustomHeaders_mergesHeaders() async throws {
        // Given
        let customHeaders: HTTPHeaders = [
            "X-Custom-Header": "custom-value",
            "X-Request-Id": "123456"
        ]

        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.value(forHTTPHeaderField: "X-Custom-Header"), "custom-value")
            XCTAssertEqual(request.value(forHTTPHeaderField: "X-Request-Id"), "123456")
            // Also verify standard headers are still present
            XCTAssertEqual(request.value(forHTTPHeaderField: "Content-Type"), "application/json")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test", headers: customHeaders)
    }

    // MARK: - GET Request Tests

    func testGet_returnsDecodedResponse() async throws {
        // Given
        let expectedResponse = TestResponse(id: "abc123", name: "Test Item", value: 100)

        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "GET")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let encoder = JSONEncoder()
            encoder.keyEncodingStrategy = .convertToSnakeCase
            let data = try encoder.encode(expectedResponse)
            return (response, data)
        }

        // When
        let result: TestResponse = try await sut.get("/test")

        // Then
        XCTAssertEqual(result.id, expectedResponse.id)
        XCTAssertEqual(result.name, expectedResponse.name)
        XCTAssertEqual(result.value, expectedResponse.value)
    }

    func testGet_withParameters_includesQueryString() async throws {
        // Given
        let parameters: [String: Any] = [
            "page": 1,
            "limit": 10,
            "status": "active"
        ]

        MockURLProtocol.requestHandler = { request in
            let urlComponents = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)
            let queryItems = urlComponents?.queryItems ?? []

            XCTAssertTrue(queryItems.contains { $0.name == "page" && $0.value == "1" })
            XCTAssertTrue(queryItems.contains { $0.name == "limit" && $0.value == "10" })
            XCTAssertTrue(queryItems.contains { $0.name == "status" && $0.value == "active" })

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Test", value: 42))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.get("/test", parameters: parameters)
    }

    // MARK: - POST Request Tests

    func testPost_sendsCorrectHTTPMethod() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "POST")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 201,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Created", value: 1))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.post("/test")
    }

    func testPost_withBody_sendsJSONEncodedBody() async throws {
        // Given
        let requestBody = TestRequest(title: "New Item", count: 5)

        MockURLProtocol.requestHandler = { request in
            XCTAssertNotNil(request.httpBody)

            // Verify body contains expected data (as JSON)
            if let body = request.httpBody {
                let json = try JSONSerialization.jsonObject(with: body) as? [String: Any]
                XCTAssertEqual(json?["title"] as? String, "New Item")
                XCTAssertEqual(json?["count"] as? Int, 5)
            }

            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 201,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "new", name: "Created", value: 5))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.post("/test", body: requestBody)
    }

    // MARK: - PUT Request Tests

    func testPut_sendsCorrectHTTPMethod() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "PUT")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Updated", value: 10))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.put("/test/1")
    }

    // MARK: - PATCH Request Tests

    func testPatch_sendsCorrectHTTPMethod() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "PATCH")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Patched", value: 15))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.patch("/test/1")
    }

    // MARK: - DELETE Request Tests

    func testDelete_sendsCorrectHTTPMethod() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "DELETE")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Deleted", value: 0))
            return (response, data)
        }

        // When/Then
        let _: TestResponse = try await sut.delete("/test/1")
    }

    func testDelete_withNoContent_succeeds() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            XCTAssertEqual(request.httpMethod, "DELETE")
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 204,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When/Then - should not throw
        try await sut.delete("/test/1")
    }

    // MARK: - Error Handling Tests

    func testError_unauthorized_throws401Error() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 401,
                httpVersion: nil,
                headerFields: nil
            )!
            let errorResponse = APIErrorResponse(detail: "Invalid credentials")
            let data = try JSONEncoder().encode(errorResponse)
            return (response, data)
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected unauthorized error")
        } catch let error as APIError {
            XCTAssertTrue(error.isUnauthorized)
            XCTAssertEqual(error.statusCode, 401)
        }
    }

    func testError_forbidden_throws403Error() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 403,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected forbidden error")
        } catch let error as APIError {
            XCTAssertTrue(error.isForbidden)
            XCTAssertEqual(error.statusCode, 403)
        }
    }

    func testError_notFound_throws404Error() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 404,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected not found error")
        } catch let error as APIError {
            XCTAssertTrue(error.isNotFound)
            XCTAssertEqual(error.statusCode, 404)
        }
    }

    func testError_validationError_throws422Error() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 422,
                httpVersion: nil,
                headerFields: nil
            )!
            let errorResponse = APIErrorResponse(detail: "Validation failed", code: "VALIDATION_ERROR")
            let data = try JSONEncoder().encode(errorResponse)
            return (response, data)
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected validation error")
        } catch let error as APIError {
            XCTAssertTrue(error.isValidationError)
            XCTAssertEqual(error.statusCode, 422)
        }
    }

    func testError_serverError_throws500Error() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 500,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected server error")
        } catch let error as APIError {
            XCTAssertTrue(error.isServerError)
            XCTAssertEqual(error.statusCode, 500)
        }
    }

    func testError_badRequest_throws400Error() async throws {
        // Given
        let errorDetail = "Invalid request parameters"
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 400,
                httpVersion: nil,
                headerFields: nil
            )!
            let errorResponse = APIErrorResponse(detail: errorDetail)
            let data = try JSONEncoder().encode(errorResponse)
            return (response, data)
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected bad request error")
        } catch let error as APIError {
            XCTAssertEqual(error.statusCode, 400)
            XCTAssertEqual(error.response?.detail, errorDetail)
        }
    }

    func testError_decodingError_throwsDecodingError() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            // Return invalid JSON for TestResponse
            let data = "{ invalid json }".data(using: .utf8)!
            return (response, data)
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected decoding error")
        } catch let error as APIError {
            if case .decodingError = error {
                // Expected
            } else {
                XCTFail("Expected decoding error, got \(error)")
            }
        }
    }

    func testError_emptyResponse_throwsEmptyResponseError() async throws {
        // Given
        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When/Then
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected empty response error")
        } catch let error as APIError {
            XCTAssertEqual(error, APIError.emptyResponse)
        }
    }

    // MARK: - Authentication Failure Callback Tests

    func testAuthFailure_callsOnAuthenticationFailure() async throws {
        // Given
        var authFailureCalled = false
        sut.onAuthenticationFailure = {
            authFailureCalled = true
        }

        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 401,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected unauthorized error")
        } catch {
            // Expected
        }

        // Then
        XCTAssertTrue(authFailureCalled)
    }

    // MARK: - Retry Logic Tests

    func testRetry_retryableError_retriesRequest() async throws {
        // Given
        var attemptCount = 0

        MockURLProtocol.requestHandler = { request in
            attemptCount += 1

            if attemptCount < 2 {
                // First attempt: return 503
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 503,
                    httpVersion: nil,
                    headerFields: nil
                )!
                return (response, Data())
            } else {
                // Second attempt: success
                let response = HTTPURLResponse(
                    url: request.url!,
                    statusCode: 200,
                    httpVersion: nil,
                    headerFields: nil
                )!
                let data = try JSONEncoder().encode(TestResponse(id: "1", name: "Success", value: 1))
                return (response, data)
            }
        }

        // When
        let result: TestResponse = try await sut.get("/test")

        // Then
        XCTAssertGreaterThanOrEqual(attemptCount, 2)
        XCTAssertEqual(result.name, "Success")
    }

    func testRetry_nonRetryableError_doesNotRetry() async throws {
        // Given
        var attemptCount = 0

        MockURLProtocol.requestHandler = { request in
            attemptCount += 1
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 400,
                httpVersion: nil,
                headerFields: nil
            )!
            return (response, Data())
        }

        // When
        do {
            let _: TestResponse = try await sut.get("/test")
            XCTFail("Expected error")
        } catch {
            // Expected
        }

        // Then - should only attempt once (400 is not retryable)
        XCTAssertEqual(attemptCount, 1)
    }

    // MARK: - Paginated Response Tests

    func testPaginatedResponse_decodesCorrectly() async throws {
        // Given
        let items = [
            TestResponse(id: "1", name: "Item 1", value: 10),
            TestResponse(id: "2", name: "Item 2", value: 20),
            TestResponse(id: "3", name: "Item 3", value: 30)
        ]

        MockURLProtocol.requestHandler = { request in
            let response = HTTPURLResponse(
                url: request.url!,
                statusCode: 200,
                httpVersion: nil,
                headerFields: nil
            )!

            let encoder = JSONEncoder()
            encoder.keyEncodingStrategy = .convertToSnakeCase

            let paginatedResponse: [String: Any] = [
                "items": try items.map { item in
                    try JSONSerialization.jsonObject(
                        with: encoder.encode(item)
                    )
                },
                "total": 100,
                "skip": 0,
                "limit": 10
            ]

            let data = try JSONSerialization.data(withJSONObject: paginatedResponse)
            return (response, data)
        }

        // When
        let result: PaginatedResponse<TestResponse> = try await sut.get("/test")

        // Then
        XCTAssertEqual(result.items.count, 3)
        XCTAssertEqual(result.total, 100)
        XCTAssertEqual(result.skip, 0)
        XCTAssertEqual(result.limit, 10)
        XCTAssertTrue(result.hasMore)
        XCTAssertEqual(result.currentPage, 1)
        XCTAssertEqual(result.totalPages, 10)
    }

    func testPaginatedResponse_hasMoreFalse_whenOnLastPage() {
        // Given
        let response = PaginatedResponse<TestResponse>(
            items: [],
            total: 25,
            skip: 20,
            limit: 10
        )

        // Then
        XCTAssertFalse(response.hasMore)
        XCTAssertEqual(response.currentPage, 3)
        XCTAssertEqual(response.totalPages, 3)
    }

    // MARK: - API Target Tests

    func testAPITarget_gibbon_returnsCorrectBaseURL() {
        // Given
        let gibbonService = APIService(target: .gibbon)

        // Then
        XCTAssertEqual(gibbonService.target, .gibbon)
        XCTAssertEqual(gibbonService.baseURL, APITarget.gibbon.baseURL)
    }

    func testAPITarget_aiService_returnsCorrectBaseURL() {
        // Given
        let aiService = APIService(target: .aiService)

        // Then
        XCTAssertEqual(aiService.target, .aiService)
        XCTAssertEqual(aiService.baseURL, APITarget.aiService.baseURL)
    }

    // MARK: - Request Options Tests

    func testRequestOptions_defaultValues() {
        // Given
        let options = RequestOptions.default

        // Then
        XCTAssertEqual(options.timeout, AppConstants.requestTimeout)
        XCTAssertEqual(options.retries, AppConstants.maxRetryAttempts)
        XCTAssertTrue(options.requiresAuth)
        XCTAssertNil(options.additionalHeaders)
        XCTAssertNil(options.queryParameters)
    }

    func testRequestOptions_customValues() {
        // Given
        var options = RequestOptions()
        options.timeout = 60.0
        options.retries = 5
        options.requiresAuth = false
        options.additionalHeaders = ["X-Custom": "Value"]
        options.queryParameters = ["key": "value"]

        // Then
        XCTAssertEqual(options.timeout, 60.0)
        XCTAssertEqual(options.retries, 5)
        XCTAssertFalse(options.requiresAuth)
        XCTAssertNotNil(options.additionalHeaders)
        XCTAssertNotNil(options.queryParameters)
    }
}

// MARK: - API Error Tests

final class APIErrorTests: XCTestCase {

    // MARK: - Status Code Tests

    func testStatusCode_forHTTPErrors() {
        XCTAssertEqual(APIError.badRequest(response: nil).statusCode, 400)
        XCTAssertEqual(APIError.unauthorized(response: nil).statusCode, 401)
        XCTAssertEqual(APIError.forbidden(response: nil).statusCode, 403)
        XCTAssertEqual(APIError.notFound(response: nil).statusCode, 404)
        XCTAssertEqual(APIError.validationError(response: nil).statusCode, 422)
        XCTAssertEqual(APIError.rateLimited(retryAfter: nil).statusCode, 429)
        XCTAssertEqual(APIError.serverError(statusCode: 500, response: nil).statusCode, 500)
        XCTAssertEqual(APIError.httpError(statusCode: 418, response: nil).statusCode, 418)
    }

    func testStatusCode_nilForNonHTTPErrors() {
        XCTAssertNil(APIError.networkError(underlying: nil).statusCode)
        XCTAssertNil(APIError.timeout.statusCode)
        XCTAssertNil(APIError.serverUnreachable.statusCode)
        XCTAssertNil(APIError.encodingError(underlying: NSError(domain: "", code: 0)).statusCode)
        XCTAssertNil(APIError.decodingError(underlying: NSError(domain: "", code: 0)).statusCode)
        XCTAssertNil(APIError.emptyResponse.statusCode)
        XCTAssertNil(APIError.cancelled.statusCode)
    }

    // MARK: - Retryable Tests

    func testIsRetryable_trueForRetryableErrors() {
        XCTAssertTrue(APIError.networkError(underlying: nil).isRetryable)
        XCTAssertTrue(APIError.timeout.isRetryable)
        XCTAssertTrue(APIError.serverUnreachable.isRetryable)
        XCTAssertTrue(APIError.rateLimited(retryAfter: nil).isRetryable)
        XCTAssertTrue(APIError.serverError(statusCode: 500, response: nil).isRetryable)
        XCTAssertTrue(APIError.serverError(statusCode: 502, response: nil).isRetryable)
        XCTAssertTrue(APIError.serverError(statusCode: 503, response: nil).isRetryable)
        XCTAssertTrue(APIError.serverError(statusCode: 504, response: nil).isRetryable)
    }

    func testIsRetryable_falseForNonRetryableErrors() {
        XCTAssertFalse(APIError.badRequest(response: nil).isRetryable)
        XCTAssertFalse(APIError.unauthorized(response: nil).isRetryable)
        XCTAssertFalse(APIError.forbidden(response: nil).isRetryable)
        XCTAssertFalse(APIError.notFound(response: nil).isRetryable)
        XCTAssertFalse(APIError.validationError(response: nil).isRetryable)
        XCTAssertFalse(APIError.encodingError(underlying: NSError(domain: "", code: 0)).isRetryable)
        XCTAssertFalse(APIError.decodingError(underlying: NSError(domain: "", code: 0)).isRetryable)
    }

    // MARK: - Boolean Properties Tests

    func testIsNetworkError() {
        XCTAssertTrue(APIError.networkError(underlying: nil).isNetworkError)
        XCTAssertTrue(APIError.serverUnreachable.isNetworkError)
        XCTAssertFalse(APIError.timeout.isNetworkError)
        XCTAssertFalse(APIError.unauthorized(response: nil).isNetworkError)
    }

    func testIsTimeout() {
        XCTAssertTrue(APIError.timeout.isTimeout)
        XCTAssertFalse(APIError.networkError(underlying: nil).isTimeout)
    }

    func testIsUnauthorized() {
        XCTAssertTrue(APIError.unauthorized(response: nil).isUnauthorized)
        XCTAssertFalse(APIError.forbidden(response: nil).isUnauthorized)
    }

    func testIsForbidden() {
        XCTAssertTrue(APIError.forbidden(response: nil).isForbidden)
        XCTAssertFalse(APIError.unauthorized(response: nil).isForbidden)
    }

    func testIsNotFound() {
        XCTAssertTrue(APIError.notFound(response: nil).isNotFound)
        XCTAssertFalse(APIError.badRequest(response: nil).isNotFound)
    }

    func testIsValidationError() {
        XCTAssertTrue(APIError.validationError(response: nil).isValidationError)
        XCTAssertFalse(APIError.badRequest(response: nil).isValidationError)
    }

    func testIsServerError() {
        XCTAssertTrue(APIError.serverError(statusCode: 500, response: nil).isServerError)
        XCTAssertFalse(APIError.badRequest(response: nil).isServerError)
    }

    // MARK: - Factory Method Tests

    func testFromStatusCode_createsCorrectError() {
        XCTAssertEqual(APIError.from(statusCode: 400, response: nil), .badRequest(response: nil))
        XCTAssertEqual(APIError.from(statusCode: 401, response: nil), .unauthorized(response: nil))
        XCTAssertEqual(APIError.from(statusCode: 403, response: nil), .forbidden(response: nil))
        XCTAssertEqual(APIError.from(statusCode: 404, response: nil), .notFound(response: nil))
        XCTAssertEqual(APIError.from(statusCode: 422, response: nil), .validationError(response: nil))
        XCTAssertEqual(APIError.from(statusCode: 429, response: nil), .rateLimited(retryAfter: nil))
        XCTAssertEqual(APIError.from(statusCode: 500, response: nil), .serverError(statusCode: 500, response: nil))
        XCTAssertEqual(APIError.from(statusCode: 502, response: nil), .serverError(statusCode: 502, response: nil))
        XCTAssertEqual(APIError.from(statusCode: 418, response: nil), .httpError(statusCode: 418, response: nil))
    }

    func testFromError_mapsNSURLErrorCorrectly() {
        // Timeout
        let timeoutError = NSError(domain: NSURLErrorDomain, code: NSURLErrorTimedOut)
        XCTAssertEqual(APIError.from(error: timeoutError), .timeout)

        // Network errors
        let notConnectedError = NSError(domain: NSURLErrorDomain, code: NSURLErrorNotConnectedToInternet)
        XCTAssertEqual(APIError.from(error: notConnectedError), .networkError(underlying: notConnectedError))

        // Server unreachable
        let dnsError = NSError(domain: NSURLErrorDomain, code: NSURLErrorDNSLookupFailed)
        XCTAssertEqual(APIError.from(error: dnsError), .serverUnreachable)

        // Cancelled
        let cancelledError = NSError(domain: NSURLErrorDomain, code: NSURLErrorCancelled)
        XCTAssertEqual(APIError.from(error: cancelledError), .cancelled)
    }

    func testFromError_returnsAPIErrorAsIs() {
        let originalError = APIError.forbidden(response: nil)
        XCTAssertEqual(APIError.from(error: originalError), originalError)
    }

    // MARK: - Error Description Tests

    func testErrorDescription_notNil() {
        let errors: [APIError] = [
            .networkError(underlying: nil),
            .timeout,
            .serverUnreachable,
            .badRequest(response: nil),
            .unauthorized(response: nil),
            .forbidden(response: nil),
            .notFound(response: nil),
            .validationError(response: nil),
            .rateLimited(retryAfter: nil),
            .serverError(statusCode: 500, response: nil),
            .encodingError(underlying: NSError(domain: "", code: 0)),
            .decodingError(underlying: NSError(domain: "", code: 0)),
            .emptyResponse,
            .invalidResponse,
            .tokenRefreshFailed,
            .noAuthToken,
            .cancelled,
            .unknown(underlying: nil)
        ]

        for error in errors {
            XCTAssertNotNil(error.errorDescription, "Error \(error) should have a description")
        }
    }

    func testUserMessage_notNil() {
        let errors: [APIError] = [
            .networkError(underlying: nil),
            .timeout,
            .serverUnreachable,
            .badRequest(response: nil),
            .unauthorized(response: nil),
            .forbidden(response: nil),
            .notFound(response: nil),
            .validationError(response: nil),
            .rateLimited(retryAfter: nil),
            .serverError(statusCode: 500, response: nil),
            .cancelled,
            .unknown(underlying: nil)
        ]

        for error in errors {
            XCTAssertFalse(error.userMessage.isEmpty, "Error \(error) should have a user message")
        }
    }

    // MARK: - Response Property Tests

    func testResponse_returnsAPIErrorResponse() {
        let errorResponse = APIErrorResponse(detail: "Test error", code: "TEST_ERROR")

        XCTAssertEqual(APIError.badRequest(response: errorResponse).response?.detail, "Test error")
        XCTAssertEqual(APIError.unauthorized(response: errorResponse).response?.code, "TEST_ERROR")
        XCTAssertEqual(APIError.forbidden(response: errorResponse).response?.detail, "Test error")
        XCTAssertEqual(APIError.notFound(response: errorResponse).response?.detail, "Test error")
        XCTAssertEqual(APIError.validationError(response: errorResponse).response?.detail, "Test error")
        XCTAssertEqual(APIError.serverError(statusCode: 500, response: errorResponse).response?.detail, "Test error")
        XCTAssertEqual(APIError.httpError(statusCode: 418, response: errorResponse).response?.detail, "Test error")
    }

    func testResponse_nilForNonHTTPErrors() {
        XCTAssertNil(APIError.networkError(underlying: nil).response)
        XCTAssertNil(APIError.timeout.response)
        XCTAssertNil(APIError.serverUnreachable.response)
        XCTAssertNil(APIError.cancelled.response)
    }

    // MARK: - Equatable Tests

    func testEquatable_sameErrors_areEqual() {
        XCTAssertEqual(APIError.timeout, APIError.timeout)
        XCTAssertEqual(APIError.serverUnreachable, APIError.serverUnreachable)
        XCTAssertEqual(APIError.emptyResponse, APIError.emptyResponse)
        XCTAssertEqual(APIError.cancelled, APIError.cancelled)
        XCTAssertEqual(
            APIError.serverError(statusCode: 500, response: nil),
            APIError.serverError(statusCode: 500, response: nil)
        )
    }

    func testEquatable_differentErrors_areNotEqual() {
        XCTAssertNotEqual(APIError.timeout, APIError.cancelled)
        XCTAssertNotEqual(APIError.unauthorized(response: nil), APIError.forbidden(response: nil))
        XCTAssertNotEqual(
            APIError.serverError(statusCode: 500, response: nil),
            APIError.serverError(statusCode: 502, response: nil)
        )
    }
}

// MARK: - Empty Response Tests

final class EmptyResponseTests: XCTestCase {

    func testEmptyResponse_decodesFromEmptyJSON() throws {
        let data = "{}".data(using: .utf8)!
        let response = try JSONDecoder().decode(EmptyResponse.self, from: data)
        XCTAssertNotNil(response)
    }

    func testEmptyResponse_init() {
        let response = EmptyResponse()
        XCTAssertNotNil(response)
    }
}
