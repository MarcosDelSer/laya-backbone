//
//  APIError.swift
//  LAYAAdmin
//
//  API error types and error handling for network requests.
//  Provides structured error information including HTTP status codes,
//  response body, and user-friendly messages.
//

import Foundation

// MARK: - API Error Response

/// Response structure for API errors
struct APIErrorResponse: Codable {
    /// Error detail message from the server
    let detail: String?

    /// Error code if provided
    let code: String?

    /// Additional error context
    let context: [String: String]?

    enum CodingKeys: String, CodingKey {
        case detail
        case code
        case context
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        detail = try container.decodeIfPresent(String.self, forKey: .detail)
        code = try container.decodeIfPresent(String.self, forKey: .code)
        context = try container.decodeIfPresent([String: String].self, forKey: .context)
    }

    init(detail: String?, code: String? = nil, context: [String: String]? = nil) {
        self.detail = detail
        self.code = code
        self.context = context
    }
}

// MARK: - API Error

/// Comprehensive error type for API operations.
///
/// Provides structured error information including HTTP status code,
/// response body, and user-friendly messages for display.
enum APIError: LocalizedError {

    // MARK: - Network Errors

    /// Network connectivity error (no internet, DNS failure, etc.)
    case networkError(underlying: Error?)

    /// Request timed out
    case timeout

    /// Server is unreachable
    case serverUnreachable

    // MARK: - HTTP Errors

    /// Bad request (400)
    case badRequest(response: APIErrorResponse?)

    /// Authentication failure (401)
    case unauthorized(response: APIErrorResponse?)

    /// Access forbidden (403)
    case forbidden(response: APIErrorResponse?)

    /// Resource not found (404)
    case notFound(response: APIErrorResponse?)

    /// Validation error (422)
    case validationError(response: APIErrorResponse?)

    /// Rate limited (429)
    case rateLimited(retryAfter: TimeInterval?)

    /// Server error (500-599)
    case serverError(statusCode: Int, response: APIErrorResponse?)

    /// Unexpected HTTP status code
    case httpError(statusCode: Int, response: APIErrorResponse?)

    // MARK: - Data Errors

    /// Failed to encode request body
    case encodingError(underlying: Error)

    /// Failed to decode response
    case decodingError(underlying: Error)

    /// Empty response when data was expected
    case emptyResponse

    /// Invalid response format
    case invalidResponse

    // MARK: - Auth Errors

    /// Token refresh failed
    case tokenRefreshFailed

    /// No authentication token available
    case noAuthToken

    // MARK: - Other Errors

    /// Request was cancelled
    case cancelled

    /// Unknown error
    case unknown(underlying: Error?)

    // MARK: - Computed Properties

    /// The HTTP status code associated with this error, if applicable
    var statusCode: Int? {
        switch self {
        case .badRequest:
            return 400
        case .unauthorized:
            return 401
        case .forbidden:
            return 403
        case .notFound:
            return 404
        case .validationError:
            return 422
        case .rateLimited:
            return 429
        case .serverError(let code, _):
            return code
        case .httpError(let code, _):
            return code
        default:
            return nil
        }
    }

    /// Whether this error is due to network connectivity issues
    var isNetworkError: Bool {
        switch self {
        case .networkError, .serverUnreachable:
            return true
        default:
            return false
        }
    }

    /// Whether this error is due to a timeout
    var isTimeout: Bool {
        if case .timeout = self {
            return true
        }
        return false
    }

    /// Whether this error is an authentication failure
    var isUnauthorized: Bool {
        if case .unauthorized = self {
            return true
        }
        return false
    }

    /// Whether this error is a forbidden access error
    var isForbidden: Bool {
        if case .forbidden = self {
            return true
        }
        return false
    }

    /// Whether this error indicates resource not found
    var isNotFound: Bool {
        if case .notFound = self {
            return true
        }
        return false
    }

    /// Whether this error is a validation error
    var isValidationError: Bool {
        if case .validationError = self {
            return true
        }
        return false
    }

    /// Whether this error is a server error (5xx)
    var isServerError: Bool {
        if case .serverError = self {
            return true
        }
        return false
    }

    /// HTTP status codes that should trigger a retry
    private static let retryableStatusCodes = [408, 429, 500, 502, 503, 504]

    /// Whether this error can be retried
    var isRetryable: Bool {
        switch self {
        case .networkError, .timeout, .serverUnreachable, .rateLimited:
            return true
        case .serverError(let code, _):
            return Self.retryableStatusCodes.contains(code)
        case .httpError(let code, _):
            return Self.retryableStatusCodes.contains(code)
        default:
            return false
        }
    }

    /// The API error response body, if available
    var response: APIErrorResponse? {
        switch self {
        case .badRequest(let response),
             .unauthorized(let response),
             .forbidden(let response),
             .notFound(let response),
             .validationError(let response),
             .serverError(_, let response),
             .httpError(_, let response):
            return response
        default:
            return nil
        }
    }

    // MARK: - LocalizedError

    var errorDescription: String? {
        switch self {
        case .networkError:
            return String(localized: "Network error occurred")
        case .timeout:
            return String(localized: "Request timed out")
        case .serverUnreachable:
            return String(localized: "Server is unreachable")
        case .badRequest(let response):
            return response?.detail ?? String(localized: "Bad request")
        case .unauthorized:
            return String(localized: "Authentication required")
        case .forbidden:
            return String(localized: "Access denied")
        case .notFound:
            return String(localized: "Resource not found")
        case .validationError(let response):
            return response?.detail ?? String(localized: "Validation error")
        case .rateLimited:
            return String(localized: "Too many requests")
        case .serverError(let code, let response):
            return response?.detail ?? String(localized: "Server error (\(code))")
        case .httpError(let code, let response):
            return response?.detail ?? String(localized: "HTTP error (\(code))")
        case .encodingError:
            return String(localized: "Failed to encode request")
        case .decodingError:
            return String(localized: "Failed to decode response")
        case .emptyResponse:
            return String(localized: "Empty response received")
        case .invalidResponse:
            return String(localized: "Invalid response format")
        case .tokenRefreshFailed:
            return String(localized: "Session expired")
        case .noAuthToken:
            return String(localized: "Not authenticated")
        case .cancelled:
            return String(localized: "Request cancelled")
        case .unknown:
            return String(localized: "An unexpected error occurred")
        }
    }

    /// User-friendly error message suitable for display
    var userMessage: String {
        switch self {
        case .networkError, .serverUnreachable:
            return String(localized: "Unable to connect to the server. Please check your internet connection.")
        case .timeout:
            return String(localized: "The request timed out. Please try again.")
        case .unauthorized, .tokenRefreshFailed, .noAuthToken:
            return String(localized: "Your session has expired. Please log in again.")
        case .forbidden:
            return String(localized: "You do not have permission to perform this action.")
        case .notFound:
            return String(localized: "The requested resource was not found.")
        case .validationError(let response):
            return response?.detail ?? String(localized: "The submitted data is invalid.")
        case .rateLimited:
            return String(localized: "Too many requests. Please wait a moment and try again.")
        case .serverError:
            return String(localized: "A server error occurred. Please try again later.")
        case .badRequest(let response):
            return response?.detail ?? String(localized: "The request could not be processed.")
        case .cancelled:
            return String(localized: "The request was cancelled.")
        default:
            return response?.detail ?? errorDescription ?? String(localized: "An unexpected error occurred.")
        }
    }

    // MARK: - Factory Methods

    /// Creates an APIError from an HTTP status code and optional response body
    static func from(statusCode: Int, response: APIErrorResponse?) -> APIError {
        switch statusCode {
        case 400:
            return .badRequest(response: response)
        case 401:
            return .unauthorized(response: response)
        case 403:
            return .forbidden(response: response)
        case 404:
            return .notFound(response: response)
        case 422:
            return .validationError(response: response)
        case 429:
            return .rateLimited(retryAfter: nil)
        case 500...599:
            return .serverError(statusCode: statusCode, response: response)
        default:
            return .httpError(statusCode: statusCode, response: response)
        }
    }

    /// Creates an APIError from an underlying Error
    static func from(error: Error) -> APIError {
        if let apiError = error as? APIError {
            return apiError
        }

        let nsError = error as NSError

        // Check for common NSURLError codes
        switch nsError.code {
        case NSURLErrorTimedOut:
            return .timeout
        case NSURLErrorNotConnectedToInternet,
             NSURLErrorNetworkConnectionLost:
            return .networkError(underlying: error)
        case NSURLErrorCannotFindHost,
             NSURLErrorCannotConnectToHost,
             NSURLErrorDNSLookupFailed:
            return .serverUnreachable
        case NSURLErrorCancelled:
            return .cancelled
        default:
            return .unknown(underlying: error)
        }
    }
}

// MARK: - Equatable

extension APIError: Equatable {
    static func == (lhs: APIError, rhs: APIError) -> Bool {
        switch (lhs, rhs) {
        case (.networkError, .networkError):
            return true
        case (.timeout, .timeout):
            return true
        case (.serverUnreachable, .serverUnreachable):
            return true
        case (.badRequest, .badRequest):
            return true
        case (.unauthorized, .unauthorized):
            return true
        case (.forbidden, .forbidden):
            return true
        case (.notFound, .notFound):
            return true
        case (.validationError, .validationError):
            return true
        case (.rateLimited, .rateLimited):
            return true
        case (.serverError(let lhsCode, _), .serverError(let rhsCode, _)):
            return lhsCode == rhsCode
        case (.httpError(let lhsCode, _), .httpError(let rhsCode, _)):
            return lhsCode == rhsCode
        case (.encodingError, .encodingError):
            return true
        case (.decodingError, .decodingError):
            return true
        case (.emptyResponse, .emptyResponse):
            return true
        case (.invalidResponse, .invalidResponse):
            return true
        case (.tokenRefreshFailed, .tokenRefreshFailed):
            return true
        case (.noAuthToken, .noAuthToken):
            return true
        case (.cancelled, .cancelled):
            return true
        case (.unknown, .unknown):
            return true
        default:
            return false
        }
    }
}
