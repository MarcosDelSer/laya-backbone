//
//  APIService.swift
//  LAYAAdmin
//
//  Base API service with Alamofire for network requests.
//  Provides type-safe HTTP methods with standardized error handling,
//  request/response transformation, and retry logic.
//

import Foundation
import Alamofire
import Combine

// MARK: - API Service Protocol

/// Protocol defining the API service interface
protocol APIServiceProtocol {
    /// Makes a GET request
    func get<T: Decodable>(_ path: String, parameters: [String: Any]?, headers: HTTPHeaders?) async throws -> T

    /// Makes a POST request
    func post<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T

    /// Makes a PUT request
    func put<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T

    /// Makes a PATCH request
    func patch<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T

    /// Makes a DELETE request
    func delete<T: Decodable>(_ path: String, headers: HTTPHeaders?) async throws -> T

    /// Makes a DELETE request with no response body
    func delete(_ path: String, headers: HTTPHeaders?) async throws
}

// MARK: - API Service Target

/// Enum representing different API targets (backends)
enum APITarget {
    case gibbon
    case aiService

    var baseURL: String {
        switch self {
        case .gibbon:
            return AppConstants.gibbonAPIURL
        case .aiService:
            return AppConstants.aiServiceURL
        }
    }
}

// MARK: - Request Options

/// Options for customizing API requests
struct RequestOptions {
    /// Request timeout in seconds
    var timeout: TimeInterval = AppConstants.requestTimeout

    /// Number of retry attempts for failed requests
    var retries: Int = AppConstants.maxRetryAttempts

    /// Whether to include authentication headers
    var requiresAuth: Bool = true

    /// Custom headers to add to the request
    var additionalHeaders: HTTPHeaders?

    /// Query parameters for GET requests
    var queryParameters: [String: Any]?

    static let `default` = RequestOptions()
}

// MARK: - API Service

/// Base API service for making HTTP requests to LAYA backends.
///
/// Features:
/// - Type-safe request/response handling with Codable
/// - Automatic JSON serialization/deserialization
/// - Request timeout support
/// - Retry logic with exponential backoff
/// - Structured error handling via APIError
/// - JWT authentication header injection
@MainActor
final class APIService: APIServiceProtocol {

    // MARK: - Singleton

    /// Shared instance for Gibbon CMS API
    static let shared = APIService(target: .gibbon)

    /// Shared instance for AI Service API
    static let aiService = APIService(target: .aiService)

    // MARK: - Properties

    /// The API target (backend) for this service instance
    let target: APITarget

    /// Base URL for API requests
    var baseURL: String {
        target.baseURL
    }

    /// Alamofire session with custom configuration
    private let session: Session

    /// JSON encoder for request bodies
    private let encoder: JSONEncoder

    /// JSON decoder for responses
    private let decoder: JSONDecoder

    /// Closure to retrieve the current auth token
    var authTokenProvider: (() -> String?)?

    /// Closure called when authentication fails (401 response)
    var onAuthenticationFailure: (() -> Void)?

    // MARK: - Initialization

    /// Creates a new API service instance
    /// - Parameters:
    ///   - target: The API target (gibbon or aiService)
    ///   - configuration: Optional URLSession configuration
    init(target: APITarget, configuration: URLSessionConfiguration? = nil) {
        self.target = target

        // Configure session
        let config = configuration ?? URLSessionConfiguration.default
        config.timeoutIntervalForRequest = AppConstants.requestTimeout
        config.timeoutIntervalForResource = AppConstants.requestTimeout * 2
        config.waitsForConnectivity = true

        // Create session with retry interceptor
        self.session = Session(configuration: config)

        // Configure encoder
        self.encoder = JSONEncoder()
        self.encoder.dateEncodingStrategy = .iso8601
        self.encoder.keyEncodingStrategy = .convertToSnakeCase

        // Configure decoder
        self.decoder = JSONDecoder()
        self.decoder.dateDecodingStrategy = .iso8601
        self.decoder.keyDecodingStrategy = .convertFromSnakeCase
    }

    // MARK: - Public API Methods

    /// Makes a GET request
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - parameters: Optional query parameters
    ///   - headers: Optional additional headers
    /// - Returns: Decoded response of type T
    func get<T: Decodable>(
        _ path: String,
        parameters: [String: Any]? = nil,
        headers: HTTPHeaders? = nil
    ) async throws -> T {
        return try await request(
            path: path,
            method: .get,
            parameters: parameters,
            encoding: URLEncoding.default,
            headers: headers
        )
    }

    /// Makes a POST request
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - body: Optional request body (Encodable)
    ///   - headers: Optional additional headers
    /// - Returns: Decoded response of type T
    func post<T: Decodable>(
        _ path: String,
        body: Encodable? = nil,
        headers: HTTPHeaders? = nil
    ) async throws -> T {
        return try await request(
            path: path,
            method: .post,
            body: body,
            headers: headers
        )
    }

    /// Makes a POST request with no response body expected
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - body: Optional request body (Encodable)
    ///   - headers: Optional additional headers
    func post(
        _ path: String,
        body: Encodable? = nil,
        headers: HTTPHeaders? = nil
    ) async throws {
        let _: EmptyResponse = try await request(
            path: path,
            method: .post,
            body: body,
            headers: headers
        )
    }

    /// Makes a PUT request
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - body: Optional request body (Encodable)
    ///   - headers: Optional additional headers
    /// - Returns: Decoded response of type T
    func put<T: Decodable>(
        _ path: String,
        body: Encodable? = nil,
        headers: HTTPHeaders? = nil
    ) async throws -> T {
        return try await request(
            path: path,
            method: .put,
            body: body,
            headers: headers
        )
    }

    /// Makes a PATCH request
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - body: Optional request body (Encodable)
    ///   - headers: Optional additional headers
    /// - Returns: Decoded response of type T
    func patch<T: Decodable>(
        _ path: String,
        body: Encodable? = nil,
        headers: HTTPHeaders? = nil
    ) async throws -> T {
        return try await request(
            path: path,
            method: .patch,
            body: body,
            headers: headers
        )
    }

    /// Makes a DELETE request
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - headers: Optional additional headers
    /// - Returns: Decoded response of type T
    func delete<T: Decodable>(
        _ path: String,
        headers: HTTPHeaders? = nil
    ) async throws -> T {
        return try await request(
            path: path,
            method: .delete,
            headers: headers
        )
    }

    /// Makes a DELETE request with no response body
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - headers: Optional additional headers
    func delete(
        _ path: String,
        headers: HTTPHeaders? = nil
    ) async throws {
        let _: EmptyResponse = try await request(
            path: path,
            method: .delete,
            headers: headers
        )
    }

    // MARK: - Request with Options

    /// Makes a request with custom options
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - method: HTTP method
    ///   - body: Optional request body
    ///   - options: Request options (timeout, retries, etc.)
    /// - Returns: Decoded response of type T
    func request<T: Decodable>(
        path: String,
        method: HTTPMethod,
        body: Encodable? = nil,
        options: RequestOptions = .default
    ) async throws -> T {
        return try await request(
            path: path,
            method: method,
            body: body,
            headers: options.additionalHeaders,
            options: options
        )
    }

    // MARK: - Private Request Implementation

    /// Core request method with retry logic
    private func request<T: Decodable>(
        path: String,
        method: HTTPMethod,
        parameters: [String: Any]? = nil,
        encoding: ParameterEncoding = URLEncoding.default,
        body: Encodable? = nil,
        headers: HTTPHeaders? = nil,
        options: RequestOptions = .default
    ) async throws -> T {
        let url = buildURL(path: path)
        var requestHeaders = buildHeaders(additionalHeaders: headers, requiresAuth: options.requiresAuth)

        // Encode body if provided
        var bodyParameters: [String: Any]?
        if let body = body {
            do {
                let data = try encoder.encode(body)
                bodyParameters = try JSONSerialization.jsonObject(with: data) as? [String: Any]
            } catch {
                throw APIError.encodingError(underlying: error)
            }
        }

        // Determine parameters and encoding
        let finalParameters = parameters ?? bodyParameters
        let finalEncoding: ParameterEncoding = body != nil ? JSONEncoding.default : encoding

        // Execute with retry logic
        var lastError: APIError?

        for attempt in 0...options.retries {
            do {
                let response: T = try await executeRequest(
                    url: url,
                    method: method,
                    parameters: finalParameters,
                    encoding: finalEncoding,
                    headers: requestHeaders,
                    timeout: options.timeout
                )
                return response
            } catch let error as APIError {
                lastError = error

                // Handle authentication failure
                if error.isUnauthorized {
                    onAuthenticationFailure?()
                    throw error
                }

                // Check if error is retryable
                guard error.isRetryable && attempt < options.retries else {
                    throw error
                }

                // Wait with exponential backoff before retry
                let delay = calculateBackoffDelay(attempt: attempt)
                try await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
            } catch {
                lastError = APIError.from(error: error)

                guard lastError?.isRetryable == true && attempt < options.retries else {
                    throw lastError ?? APIError.unknown(underlying: error)
                }

                let delay = calculateBackoffDelay(attempt: attempt)
                try await Task.sleep(nanoseconds: UInt64(delay * 1_000_000_000))
            }
        }

        throw lastError ?? APIError.unknown(underlying: nil)
    }

    /// Executes a single request without retry logic
    private func executeRequest<T: Decodable>(
        url: String,
        method: HTTPMethod,
        parameters: [String: Any]?,
        encoding: ParameterEncoding,
        headers: HTTPHeaders,
        timeout: TimeInterval
    ) async throws -> T {
        return try await withCheckedThrowingContinuation { continuation in
            let request = session.request(
                url,
                method: method,
                parameters: parameters,
                encoding: encoding,
                headers: headers
            )
            .validate()

            request.responseData { response in
                switch response.result {
                case .success(let data):
                    // Handle empty response
                    if data.isEmpty {
                        if T.self == EmptyResponse.self {
                            continuation.resume(returning: EmptyResponse() as! T)
                            return
                        }

                        // Check for 204 No Content
                        if let statusCode = response.response?.statusCode, statusCode == 204 {
                            if T.self == EmptyResponse.self {
                                continuation.resume(returning: EmptyResponse() as! T)
                                return
                            }
                        }

                        continuation.resume(throwing: APIError.emptyResponse)
                        return
                    }

                    // Decode response
                    do {
                        let decoded = try self.decoder.decode(T.self, from: data)
                        continuation.resume(returning: decoded)
                    } catch {
                        continuation.resume(throwing: APIError.decodingError(underlying: error))
                    }

                case .failure(let error):
                    let apiError = self.mapAlamofireError(error, response: response.response, data: response.data)
                    continuation.resume(throwing: apiError)
                }
            }
        }
    }

    // MARK: - Helper Methods

    /// Builds the full URL for a request
    private func buildURL(path: String) -> String {
        // If path already contains the base URL, return as-is
        if path.hasPrefix("http://") || path.hasPrefix("https://") {
            return path
        }

        // Ensure path starts with /
        let normalizedPath = path.hasPrefix("/") ? path : "/\(path)"
        return baseURL + normalizedPath
    }

    /// Builds HTTP headers for a request
    private func buildHeaders(additionalHeaders: HTTPHeaders?, requiresAuth: Bool) -> HTTPHeaders {
        var headers: HTTPHeaders = [
            .contentType("application/json"),
            .accept("application/json")
        ]

        // Add auth header if required and available
        if requiresAuth, let token = authTokenProvider?() {
            headers.add(.authorization(bearerToken: token))
        }

        // Merge additional headers
        if let additional = additionalHeaders {
            for header in additional {
                headers.add(header)
            }
        }

        return headers
    }

    /// Calculates exponential backoff delay for retry attempts
    private func calculateBackoffDelay(attempt: Int) -> TimeInterval {
        let baseDelay = AppConstants.retryBaseDelay
        let delay = baseDelay * pow(2.0, Double(attempt))
        // Add jitter to prevent thundering herd
        let jitter = Double.random(in: 0...(delay * 0.1))
        return min(delay + jitter, 30.0) // Cap at 30 seconds
    }

    /// Maps Alamofire errors to APIError
    private func mapAlamofireError(_ error: AFError, response: HTTPURLResponse?, data: Data?) -> APIError {
        // Parse error response body if available
        var errorResponse: APIErrorResponse?
        if let data = data {
            errorResponse = try? decoder.decode(APIErrorResponse.self, from: data)
        }

        // Handle based on status code if available
        if let statusCode = response?.statusCode {
            return APIError.from(statusCode: statusCode, response: errorResponse)
        }

        // Handle specific Alamofire errors
        switch error {
        case .sessionTaskFailed(let underlyingError):
            return APIError.from(error: underlyingError)

        case .responseValidationFailed(let reason):
            switch reason {
            case .unacceptableStatusCode(let code):
                return APIError.from(statusCode: code, response: errorResponse)
            default:
                return APIError.invalidResponse
            }

        case .responseSerializationFailed:
            return APIError.decodingError(underlying: error)

        case .requestAdaptationFailed, .urlRequestValidationFailed, .createUploadableFailed, .createURLRequestFailed, .parameterEncodingFailed, .parameterEncoderFailed:
            return APIError.encodingError(underlying: error)

        case .explicitlyCancelled:
            return APIError.cancelled

        default:
            return APIError.unknown(underlying: error)
        }
    }
}

// MARK: - Empty Response

/// Represents an empty response for requests that don't return data
struct EmptyResponse: Decodable {
    init() {}

    init(from decoder: Decoder) throws {
        // Accept empty response
    }
}

// MARK: - Paginated Response

/// Generic paginated response structure matching backend API
struct PaginatedResponse<T: Decodable>: Decodable {
    /// Array of items
    let items: [T]

    /// Total number of items
    let total: Int

    /// Number of items skipped
    let skip: Int

    /// Maximum number of items per page
    let limit: Int

    /// Whether there are more pages
    var hasMore: Bool {
        return skip + items.count < total
    }

    /// Current page number (1-indexed)
    var currentPage: Int {
        return (skip / max(limit, 1)) + 1
    }

    /// Total number of pages
    var totalPages: Int {
        return Int(ceil(Double(total) / Double(max(limit, 1))))
    }
}

// MARK: - API Service Extensions

extension APIService {

    /// Fetches all pages of a paginated resource
    /// - Parameters:
    ///   - path: The API endpoint path
    ///   - pageSize: Number of items per page
    /// - Returns: All items from all pages
    func fetchAllPages<T: Decodable>(
        path: String,
        pageSize: Int = AppConstants.defaultPageSize
    ) async throws -> [T] {
        var allItems: [T] = []
        var skip = 0
        var hasMore = true

        while hasMore {
            let params: [String: Any] = [
                "skip": skip,
                "limit": pageSize
            ]

            let response: PaginatedResponse<T> = try await get(path, parameters: params)
            allItems.append(contentsOf: response.items)

            hasMore = response.hasMore
            skip += pageSize
        }

        return allItems
    }
}
