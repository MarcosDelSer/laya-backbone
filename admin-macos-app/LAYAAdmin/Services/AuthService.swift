//
//  AuthService.swift
//  LAYAAdmin
//
//  Authentication service with secure Keychain storage for JWT tokens.
//  Handles login, logout, token refresh, and session management.
//

import Foundation
import Combine
import KeychainAccess

// MARK: - Auth Service Protocol

/// Protocol defining the authentication service interface
protocol AuthServiceProtocol {
    /// Current authentication state
    var authState: AuthState { get }

    /// Publisher for authentication state changes
    var authStatePublisher: AnyPublisher<AuthState, Never> { get }

    /// Whether the user is currently authenticated
    var isAuthenticated: Bool { get }

    /// The current user, if authenticated
    var currentUser: User? { get }

    /// Logs in with email and password
    func login(email: String, password: String, rememberMe: Bool) async throws -> User

    /// Logs out the current user
    func logout() async

    /// Refreshes the current access token
    func refreshToken() async throws

    /// Retrieves the current access token for API requests
    func getAccessToken() -> String?

    /// Checks if stored credentials are valid and attempts to restore session
    func restoreSession() async -> Bool
}

// MARK: - Auth Service

/// Authentication service for managing user sessions.
///
/// Features:
/// - Secure credential storage in macOS Keychain
/// - JWT token management with automatic refresh
/// - Session restoration on app launch
/// - Observable authentication state
@MainActor
final class AuthService: ObservableObject, AuthServiceProtocol {

    // MARK: - Singleton

    /// Shared instance for the app
    static let shared = AuthService()

    // MARK: - Published Properties

    /// Current authentication state
    @Published private(set) var authState: AuthState = .unauthenticated

    /// Publisher for authentication state changes
    var authStatePublisher: AnyPublisher<AuthState, Never> {
        $authState.eraseToAnyPublisher()
    }

    // MARK: - Computed Properties

    /// Whether the user is currently authenticated
    var isAuthenticated: Bool {
        authState.isAuthenticated
    }

    /// The current user, if authenticated
    var currentUser: User? {
        authState.currentUser
    }

    // MARK: - Private Properties

    /// Keychain instance for secure storage
    private let keychain: Keychain

    /// API service for authentication requests
    private let apiService: APIService

    /// Stored credentials from Keychain
    private var storedCredentials: StoredCredentials = .empty

    /// Timer for automatic token refresh
    private var tokenRefreshTimer: Timer?

    /// Buffer time before token expiry to trigger refresh (5 minutes)
    private let tokenRefreshBuffer: TimeInterval = 300

    /// Cancellables for Combine subscriptions
    private var cancellables = Set<AnyCancellable>()

    // MARK: - Initialization

    /// Creates a new AuthService instance
    /// - Parameters:
    ///   - keychain: Optional custom Keychain instance (for testing)
    ///   - apiService: Optional custom API service (for testing)
    init(keychain: Keychain? = nil, apiService: APIService? = nil) {
        self.keychain = keychain ?? Keychain(service: AppConstants.keychainService)
            .accessibility(.whenUnlocked)
            .synchronizable(false)
        self.apiService = apiService ?? APIService.shared

        // Configure API service to use this auth service for tokens
        self.apiService.authTokenProvider = { [weak self] in
            self?.getAccessToken()
        }

        // Handle authentication failures from API
        self.apiService.onAuthenticationFailure = { [weak self] in
            Task { @MainActor in
                await self?.handleAuthenticationFailure()
            }
        }

        // Load stored credentials on init
        loadStoredCredentials()
    }

    // MARK: - Public Methods

    /// Logs in with email and password
    /// - Parameters:
    ///   - email: User's email address
    ///   - password: User's password
    ///   - rememberMe: Whether to persist credentials for extended sessions
    /// - Returns: The authenticated user
    func login(email: String, password: String, rememberMe: Bool = false) async throws -> User {
        // Update state to authenticating
        authState = .authenticating

        do {
            // Create login request
            let request = LoginRequest(email: email, password: password, rememberMe: rememberMe)

            // Make login API call (without auth header)
            let response: LoginResponse = try await apiService.request(
                path: GibbonEndpoints.login,
                method: .post,
                body: request,
                options: RequestOptions(requiresAuth: false)
            )

            // Store credentials securely
            try storeCredentials(
                accessToken: response.accessToken,
                refreshToken: response.refreshToken,
                expiresIn: response.expiresIn,
                user: response.user
            )

            // Update auth state
            authState = .authenticated(response.user)

            // Schedule token refresh
            scheduleTokenRefresh(expiresIn: response.expiresIn)

            // Post notification
            NotificationCenter.default.post(name: .authStateChanged, object: self)

            return response.user

        } catch let error as APIError {
            let authError = mapToAuthError(error)
            authState = .failed(authError)
            throw authError
        } catch {
            let authError = AuthError.unknown(error.localizedDescription)
            authState = .failed(authError)
            throw authError
        }
    }

    /// Logs out the current user
    func logout() async {
        // Cancel refresh timer
        tokenRefreshTimer?.invalidate()
        tokenRefreshTimer = nil

        // Attempt to invalidate refresh token on server
        if let refreshToken = storedCredentials.refreshToken {
            do {
                let request = LogoutRequest(refreshToken: refreshToken)
                try await apiService.post(GibbonEndpoints.logout, body: request)
            } catch {
                // Ignore logout API errors - still clear local state
            }
        }

        // Clear stored credentials
        clearStoredCredentials()

        // Update auth state
        authState = .unauthenticated

        // Post notification
        NotificationCenter.default.post(name: .authStateChanged, object: self)
    }

    /// Refreshes the current access token
    func refreshToken() async throws {
        guard let refreshToken = storedCredentials.refreshToken else {
            throw AuthError.invalidRefreshToken
        }

        do {
            // Create refresh request
            let request = TokenRefreshRequest(refreshToken: refreshToken)

            // Make refresh API call (without auth header - uses refresh token)
            let response: TokenRefreshResponse = try await apiService.request(
                path: GibbonEndpoints.refreshToken,
                method: .post,
                body: request,
                options: RequestOptions(requiresAuth: false)
            )

            // Update stored credentials with new tokens
            try updateTokens(
                accessToken: response.accessToken,
                refreshToken: response.refreshToken,
                expiresIn: response.expiresIn
            )

            // Schedule next token refresh
            scheduleTokenRefresh(expiresIn: response.expiresIn)

        } catch let error as APIError {
            // Handle refresh failure
            if error.isUnauthorized {
                await handleAuthenticationFailure()
                throw AuthError.invalidRefreshToken
            }
            throw mapToAuthError(error)
        }
    }

    /// Retrieves the current access token for API requests
    func getAccessToken() -> String? {
        // Check if token is valid
        if storedCredentials.hasValidCredentials {
            return storedCredentials.accessToken
        }

        // If token needs refresh and we have a refresh token, return old token
        // The API service will handle 401 errors and trigger refresh
        if storedCredentials.needsRefresh {
            return storedCredentials.accessToken
        }

        return nil
    }

    /// Checks if stored credentials are valid and attempts to restore session
    /// - Returns: Whether session was successfully restored
    @discardableResult
    func restoreSession() async -> Bool {
        // Load credentials from Keychain
        loadStoredCredentials()

        // Check if we have valid credentials
        if storedCredentials.hasValidCredentials {
            // Verify token with server by fetching user info
            do {
                let user: User = try await apiService.get("/auth/me")
                authState = .authenticated(user)

                // Schedule token refresh based on remaining time
                if let expiresAt = storedCredentials.tokenExpiresAt {
                    let remainingTime = expiresAt.timeIntervalSinceNow
                    if remainingTime > 0 {
                        scheduleTokenRefresh(expiresIn: Int(remainingTime))
                    }
                }

                NotificationCenter.default.post(name: .authStateChanged, object: self)
                return true
            } catch {
                // Token is invalid, try to refresh
                if storedCredentials.needsRefresh {
                    do {
                        try await refreshToken()
                        return await restoreSession()
                    } catch {
                        await logout()
                        return false
                    }
                }
                await logout()
                return false
            }
        }

        // Check if we need to refresh
        if storedCredentials.needsRefresh {
            do {
                try await refreshToken()
                return await restoreSession()
            } catch {
                await logout()
                return false
            }
        }

        // No valid credentials
        authState = .unauthenticated
        return false
    }

    // MARK: - Private Methods

    /// Loads stored credentials from Keychain
    private func loadStoredCredentials() {
        storedCredentials = StoredCredentials(
            accessToken: try? keychain.get(KeychainKeys.jwtToken),
            refreshToken: try? keychain.get(KeychainKeys.refreshToken),
            userId: try? keychain.get(KeychainKeys.userId),
            userRole: loadUserRole(),
            tokenExpiresAt: loadTokenExpirationDate()
        )
    }

    /// Loads the user role from Keychain
    private func loadUserRole() -> UserRole? {
        guard let roleString = try? keychain.get(KeychainKeys.userRole) else {
            return nil
        }
        return UserRole(rawValue: roleString)
    }

    /// Loads the token expiration date from Keychain
    private func loadTokenExpirationDate() -> Date? {
        guard let expiresString = try? keychain.get("token_expires_at"),
              let timestamp = TimeInterval(expiresString) else {
            return nil
        }
        return Date(timeIntervalSince1970: timestamp)
    }

    /// Stores credentials securely in Keychain
    private func storeCredentials(
        accessToken: String,
        refreshToken: String,
        expiresIn: Int,
        user: User
    ) throws {
        let expiresAt = Date().addingTimeInterval(TimeInterval(expiresIn))

        do {
            // Store tokens
            try keychain.set(accessToken, key: KeychainKeys.jwtToken)
            try keychain.set(refreshToken, key: KeychainKeys.refreshToken)

            // Store user info
            try keychain.set(user.id, key: KeychainKeys.userId)
            try keychain.set(user.role.rawValue, key: KeychainKeys.userRole)

            // Store expiration timestamp
            try keychain.set(String(expiresAt.timeIntervalSince1970), key: "token_expires_at")

            // Update local cache
            storedCredentials = StoredCredentials(
                accessToken: accessToken,
                refreshToken: refreshToken,
                userId: user.id,
                userRole: user.role,
                tokenExpiresAt: expiresAt
            )
        } catch {
            throw AuthError.unknown("Failed to store credentials: \(error.localizedDescription)")
        }
    }

    /// Updates stored tokens without changing user info
    private func updateTokens(
        accessToken: String,
        refreshToken: String,
        expiresIn: Int
    ) throws {
        let expiresAt = Date().addingTimeInterval(TimeInterval(expiresIn))

        do {
            try keychain.set(accessToken, key: KeychainKeys.jwtToken)
            try keychain.set(refreshToken, key: KeychainKeys.refreshToken)
            try keychain.set(String(expiresAt.timeIntervalSince1970), key: "token_expires_at")

            // Update local cache
            storedCredentials.accessToken = accessToken
            storedCredentials.refreshToken = refreshToken
            storedCredentials.tokenExpiresAt = expiresAt
        } catch {
            throw AuthError.unknown("Failed to update tokens: \(error.localizedDescription)")
        }
    }

    /// Clears all stored credentials from Keychain
    private func clearStoredCredentials() {
        do {
            try keychain.remove(KeychainKeys.jwtToken)
            try keychain.remove(KeychainKeys.refreshToken)
            try keychain.remove(KeychainKeys.userId)
            try keychain.remove(KeychainKeys.userRole)
            try keychain.remove("token_expires_at")
        } catch {
            // Log error but don't throw - clearing should always succeed from user perspective
        }

        storedCredentials = .empty
    }

    /// Schedules automatic token refresh before expiration
    private func scheduleTokenRefresh(expiresIn: Int) {
        // Cancel existing timer
        tokenRefreshTimer?.invalidate()

        // Calculate refresh time (refresh 5 minutes before expiry)
        let refreshInterval = max(TimeInterval(expiresIn) - tokenRefreshBuffer, 60)

        // Schedule refresh on main thread
        tokenRefreshTimer = Timer.scheduledTimer(withTimeInterval: refreshInterval, repeats: false) { [weak self] _ in
            Task { @MainActor in
                guard let self = self else { return }

                do {
                    try await self.refreshToken()
                } catch {
                    // If refresh fails, mark as token expired
                    self.authState = .tokenExpired
                }
            }
        }
    }

    /// Handles authentication failure from API
    private func handleAuthenticationFailure() async {
        // Try to refresh token first
        if storedCredentials.refreshToken != nil {
            do {
                try await refreshToken()
                return
            } catch {
                // Refresh failed, logout
            }
        }

        // Clear credentials and update state
        clearStoredCredentials()
        authState = .tokenExpired

        NotificationCenter.default.post(name: .authStateChanged, object: self)
    }

    /// Maps API errors to authentication errors
    private func mapToAuthError(_ error: APIError) -> AuthError {
        switch error {
        case .unauthorized:
            return .invalidCredentials
        case .forbidden:
            return .accountLocked
        case .serverError(_, let message):
            // Check for specific error messages
            if message?.contains("locked") == true {
                return .accountLocked
            }
            if message?.contains("verify") == true || message?.contains("email") == true {
                return .emailNotVerified
            }
            return .serverError(message ?? "Server error")
        case .networkError:
            return .networkError("Unable to connect to server")
        case .timeout:
            return .networkError("Request timed out")
        case .noConnection:
            return .networkError("No internet connection")
        default:
            return .unknown(error.localizedDescription)
        }
    }
}

// MARK: - Auth Service Extensions

extension AuthService {

    /// Initiates password reset for the given email
    /// - Parameter email: Email address to send reset link to
    /// - Returns: Success message from server
    func requestPasswordReset(email: String) async throws -> String {
        let request = PasswordResetRequest(email: email)

        let response: PasswordResetResponse = try await apiService.request(
            path: "/auth/password-reset",
            method: .post,
            body: request,
            options: RequestOptions(requiresAuth: false)
        )

        return response.message
    }

    /// Gets active sessions for the current user
    /// - Returns: List of active sessions
    func getActiveSessions() async throws -> [SessionInfo] {
        let response: [SessionInfo] = try await apiService.get("/auth/sessions")
        return response
    }

    /// Terminates a specific session
    /// - Parameter sessionId: The session ID to terminate
    func terminateSession(sessionId: String) async throws {
        try await apiService.delete("/auth/sessions/\(sessionId)")
    }

    /// Terminates all sessions except the current one
    func terminateAllOtherSessions() async throws {
        try await apiService.delete("/auth/sessions/others")
    }
}

// MARK: - Preview Support

#if DEBUG
extension AuthService {

    /// Creates a mock authenticated auth service for previews
    static var previewAuthenticated: AuthService {
        let service = AuthService()
        service.authState = .authenticated(.preview)
        return service
    }

    /// Creates a mock unauthenticated auth service for previews
    static var previewUnauthenticated: AuthService {
        let service = AuthService()
        service.authState = .unauthenticated
        return service
    }
}
#endif
