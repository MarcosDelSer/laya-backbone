//
//  AuthModels.swift
//  LAYAAdmin
//
//  Authentication models for login, token management, and session handling.
//

import Foundation

// MARK: - Login Request

/// Request payload for user authentication.
struct LoginRequest: Codable {

    /// User's email address
    let email: String

    /// User's password
    let password: String

    /// Whether to remember the user for extended sessions
    let rememberMe: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case email
        case password
        case rememberMe = "remember_me"
    }

    // MARK: - Initialization

    init(email: String, password: String, rememberMe: Bool = false) {
        self.email = email
        self.password = password
        self.rememberMe = rememberMe
    }
}

// MARK: - Login Response

/// Response from successful authentication.
struct LoginResponse: Codable {

    /// JWT access token for API authentication
    let accessToken: String

    /// Refresh token for obtaining new access tokens
    let refreshToken: String

    /// Token type (typically "Bearer")
    let tokenType: String

    /// Access token expiration time in seconds
    let expiresIn: Int

    /// The authenticated user's information
    let user: User

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case accessToken = "access_token"
        case refreshToken = "refresh_token"
        case tokenType = "token_type"
        case expiresIn = "expires_in"
        case user
    }
}

// MARK: - Token Refresh Request

/// Request payload for refreshing an expired access token.
struct TokenRefreshRequest: Codable {

    /// The refresh token to use
    let refreshToken: String

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case refreshToken = "refresh_token"
    }
}

// MARK: - Token Refresh Response

/// Response from token refresh request.
struct TokenRefreshResponse: Codable {

    /// New JWT access token
    let accessToken: String

    /// New refresh token (may be the same or rotated)
    let refreshToken: String

    /// Token type (typically "Bearer")
    let tokenType: String

    /// New access token expiration time in seconds
    let expiresIn: Int

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case accessToken = "access_token"
        case refreshToken = "refresh_token"
        case tokenType = "token_type"
        case expiresIn = "expires_in"
    }
}

// MARK: - Logout Request

/// Request payload for logging out.
struct LogoutRequest: Codable {

    /// The refresh token to invalidate
    let refreshToken: String

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case refreshToken = "refresh_token"
    }
}

// MARK: - Authentication State

/// Represents the current authentication state of the app.
enum AuthState: Equatable {

    /// Not authenticated, showing login screen
    case unauthenticated

    /// Currently authenticating
    case authenticating

    /// Successfully authenticated with user data
    case authenticated(User)

    /// Authentication failed with an error
    case failed(AuthError)

    /// Token expired, attempting refresh
    case tokenExpired

    // MARK: - Computed Properties

    /// Whether the user is currently authenticated
    var isAuthenticated: Bool {
        if case .authenticated = self {
            return true
        }
        return false
    }

    /// The currently authenticated user, if any
    var currentUser: User? {
        if case .authenticated(let user) = self {
            return user
        }
        return nil
    }

    // MARK: - Equatable

    static func == (lhs: AuthState, rhs: AuthState) -> Bool {
        switch (lhs, rhs) {
        case (.unauthenticated, .unauthenticated):
            return true
        case (.authenticating, .authenticating):
            return true
        case (.authenticated(let lhsUser), .authenticated(let rhsUser)):
            return lhsUser == rhsUser
        case (.failed(let lhsError), .failed(let rhsError)):
            return lhsError == rhsError
        case (.tokenExpired, .tokenExpired):
            return true
        default:
            return false
        }
    }
}

// MARK: - Authentication Error

/// Errors that can occur during authentication.
enum AuthError: Error, Equatable {

    /// Invalid email or password
    case invalidCredentials

    /// Account is locked or disabled
    case accountLocked

    /// Account requires email verification
    case emailNotVerified

    /// Network error occurred
    case networkError(String)

    /// Server returned an error
    case serverError(String)

    /// Token is invalid or expired
    case invalidToken

    /// Refresh token is invalid or expired
    case invalidRefreshToken

    /// Unknown error occurred
    case unknown(String)

    // MARK: - Localized Description

    var localizedDescription: String {
        switch self {
        case .invalidCredentials:
            return String(localized: "Invalid email or password. Please try again.")
        case .accountLocked:
            return String(localized: "Your account has been locked. Please contact an administrator.")
        case .emailNotVerified:
            return String(localized: "Please verify your email address before logging in.")
        case .networkError(let message):
            return String(localized: "Network error: \(message)")
        case .serverError(let message):
            return String(localized: "Server error: \(message)")
        case .invalidToken:
            return String(localized: "Your session has expired. Please log in again.")
        case .invalidRefreshToken:
            return String(localized: "Unable to refresh your session. Please log in again.")
        case .unknown(let message):
            return String(localized: "An unexpected error occurred: \(message)")
        }
    }

    // MARK: - Recovery Suggestion

    var recoverySuggestion: String {
        switch self {
        case .invalidCredentials:
            return String(localized: "Check your email and password and try again.")
        case .accountLocked:
            return String(localized: "Contact your system administrator for assistance.")
        case .emailNotVerified:
            return String(localized: "Check your email for a verification link.")
        case .networkError:
            return String(localized: "Check your internet connection and try again.")
        case .serverError:
            return String(localized: "Please try again later or contact support.")
        case .invalidToken, .invalidRefreshToken:
            return String(localized: "Please log in again to continue.")
        case .unknown:
            return String(localized: "Please try again or contact support if the problem persists.")
        }
    }

    // MARK: - Equatable

    static func == (lhs: AuthError, rhs: AuthError) -> Bool {
        switch (lhs, rhs) {
        case (.invalidCredentials, .invalidCredentials):
            return true
        case (.accountLocked, .accountLocked):
            return true
        case (.emailNotVerified, .emailNotVerified):
            return true
        case (.networkError(let lhsMsg), .networkError(let rhsMsg)):
            return lhsMsg == rhsMsg
        case (.serverError(let lhsMsg), .serverError(let rhsMsg)):
            return lhsMsg == rhsMsg
        case (.invalidToken, .invalidToken):
            return true
        case (.invalidRefreshToken, .invalidRefreshToken):
            return true
        case (.unknown(let lhsMsg), .unknown(let rhsMsg)):
            return lhsMsg == rhsMsg
        default:
            return false
        }
    }
}

// MARK: - Stored Credentials

/// Secure credential storage model (values stored in Keychain).
struct StoredCredentials {

    /// JWT access token
    var accessToken: String?

    /// Refresh token
    var refreshToken: String?

    /// User ID
    var userId: String?

    /// User role
    var userRole: UserRole?

    /// Token expiration date
    var tokenExpiresAt: Date?

    // MARK: - Computed Properties

    /// Whether we have valid stored credentials
    var hasValidCredentials: Bool {
        guard let accessToken = accessToken,
              !accessToken.isEmpty,
              let expiresAt = tokenExpiresAt else {
            return false
        }
        return expiresAt > Date()
    }

    /// Whether the access token is expired but we have a refresh token
    var needsRefresh: Bool {
        guard let accessToken = accessToken,
              !accessToken.isEmpty,
              let refreshToken = refreshToken,
              !refreshToken.isEmpty else {
            return false
        }

        guard let expiresAt = tokenExpiresAt else {
            return true
        }

        return expiresAt <= Date()
    }

    // MARK: - Empty State

    /// Empty credentials for initialization
    static var empty: StoredCredentials {
        StoredCredentials(
            accessToken: nil,
            refreshToken: nil,
            userId: nil,
            userRole: nil,
            tokenExpiresAt: nil
        )
    }
}

// MARK: - Password Reset Request

/// Request payload for initiating password reset.
struct PasswordResetRequest: Codable {

    /// Email address for password reset
    let email: String
}

// MARK: - Password Reset Response

/// Response from password reset request.
struct PasswordResetResponse: Codable {

    /// Success message
    let message: String

    /// Whether the request was successful
    let success: Bool
}

// MARK: - Session Info

/// Information about the current session.
struct SessionInfo: Codable {

    /// Session ID
    let sessionId: String

    /// Device name/identifier
    let deviceName: String

    /// IP address of the session
    let ipAddress: String

    /// User agent string
    let userAgent: String

    /// When the session was created
    let createdAt: Date

    /// When the session was last active
    let lastActiveAt: Date

    /// Whether this is the current session
    let isCurrent: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case sessionId = "session_id"
        case deviceName = "device_name"
        case ipAddress = "ip_address"
        case userAgent = "user_agent"
        case createdAt = "created_at"
        case lastActiveAt = "last_active_at"
        case isCurrent = "is_current"
    }
}
