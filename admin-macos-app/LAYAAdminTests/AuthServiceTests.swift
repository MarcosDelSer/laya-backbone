//
//  AuthServiceTests.swift
//  LAYAAdminTests
//
//  Unit tests for AuthService verifying authentication flows,
//  token storage, session management, and error handling.
//

import XCTest
import Combine
import KeychainAccess
@testable import LAYAAdmin

// MARK: - Mock Keychain

/// Mock Keychain for testing credential storage
final class MockKeychain {

    var storage: [String: String] = [:]
    var shouldThrowOnSet = false
    var shouldThrowOnGet = false
    var shouldThrowOnRemove = false

    func get(_ key: String) throws -> String? {
        if shouldThrowOnGet {
            throw KeychainError.mockError
        }
        return storage[key]
    }

    func set(_ value: String, key: String) throws {
        if shouldThrowOnSet {
            throw KeychainError.mockError
        }
        storage[key] = value
    }

    func remove(_ key: String) throws {
        if shouldThrowOnRemove {
            throw KeychainError.mockError
        }
        storage.removeValue(forKey: key)
    }

    func removeAll() {
        storage.removeAll()
    }

    enum KeychainError: Error {
        case mockError
    }
}

// MARK: - Mock API Service

/// Mock API Service for testing authentication requests
@MainActor
final class MockAPIService: APIServiceProtocol {

    var mockLoginResponse: LoginResponse?
    var mockTokenRefreshResponse: TokenRefreshResponse?
    var mockUser: User?
    var mockPasswordResetResponse: PasswordResetResponse?
    var mockSessions: [SessionInfo]?
    var shouldThrowError: APIError?
    var capturedRequests: [(path: String, method: String, body: Any?)] = []

    // Track callbacks
    var authTokenProvider: (() -> String?)?
    var onAuthenticationFailure: (() -> Void)?

    func get<T: Decodable>(_ path: String, parameters: [String: Any]?, headers: HTTPHeaders?) async throws -> T {
        capturedRequests.append((path: path, method: "GET", body: parameters))

        if let error = shouldThrowError {
            throw error
        }

        if path == "/auth/me", let user = mockUser as? T {
            return user
        }

        if path == "/auth/sessions", let sessions = mockSessions as? T {
            return sessions
        }

        throw APIError.notFound(response: nil)
    }

    func post<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T {
        capturedRequests.append((path: path, method: "POST", body: body))

        if let error = shouldThrowError {
            throw error
        }

        if path == GibbonEndpoints.login, let response = mockLoginResponse as? T {
            return response
        }

        if path == GibbonEndpoints.refreshToken, let response = mockTokenRefreshResponse as? T {
            return response
        }

        if path == "/auth/password-reset", let response = mockPasswordResetResponse as? T {
            return response
        }

        throw APIError.notFound(response: nil)
    }

    func put<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T {
        throw APIError.notFound(response: nil)
    }

    func patch<T: Decodable>(_ path: String, body: Encodable?, headers: HTTPHeaders?) async throws -> T {
        throw APIError.notFound(response: nil)
    }

    func delete<T: Decodable>(_ path: String, headers: HTTPHeaders?) async throws -> T {
        capturedRequests.append((path: path, method: "DELETE", body: nil))
        throw APIError.notFound(response: nil)
    }

    func delete(_ path: String, headers: HTTPHeaders?) async throws {
        capturedRequests.append((path: path, method: "DELETE", body: nil))
        if let error = shouldThrowError {
            throw error
        }
    }

    func request<T: Decodable>(
        path: String,
        method: HTTPMethod,
        body: Encodable?,
        options: RequestOptions
    ) async throws -> T {
        capturedRequests.append((path: path, method: method.rawValue, body: body))

        if let error = shouldThrowError {
            throw error
        }

        if path == GibbonEndpoints.login, let response = mockLoginResponse as? T {
            return response
        }

        if path == GibbonEndpoints.refreshToken, let response = mockTokenRefreshResponse as? T {
            return response
        }

        if path == "/auth/password-reset", let response = mockPasswordResetResponse as? T {
            return response
        }

        throw APIError.notFound(response: nil)
    }

    func reset() {
        mockLoginResponse = nil
        mockTokenRefreshResponse = nil
        mockUser = nil
        mockPasswordResetResponse = nil
        mockSessions = nil
        shouldThrowError = nil
        capturedRequests = []
    }
}

// MARK: - Test Fixtures

extension User {
    static var testUser: User {
        User(
            id: "test-user-123",
            email: "test@laya.ca",
            firstName: "Test",
            lastName: "User",
            role: .director,
            isActive: true,
            profilePhotoURL: nil,
            phoneNumber: "(514) 555-1234",
            createdAt: Date(),
            updatedAt: Date(),
            lastLoginAt: Date()
        )
    }

    static var testAccountant: User {
        User(
            id: "test-user-456",
            email: "accountant@laya.ca",
            firstName: "Jane",
            lastName: "Doe",
            role: .accountant,
            isActive: true,
            profilePhotoURL: nil,
            phoneNumber: nil,
            createdAt: Date(),
            updatedAt: Date(),
            lastLoginAt: nil
        )
    }
}

extension LoginResponse {
    static func mock(user: User = .testUser, expiresIn: Int = 3600) -> LoginResponse {
        LoginResponse(
            accessToken: "mock-access-token-\(UUID().uuidString)",
            refreshToken: "mock-refresh-token-\(UUID().uuidString)",
            tokenType: "Bearer",
            expiresIn: expiresIn,
            user: user
        )
    }
}

extension TokenRefreshResponse {
    static var mock: TokenRefreshResponse {
        TokenRefreshResponse(
            accessToken: "new-access-token-\(UUID().uuidString)",
            refreshToken: "new-refresh-token-\(UUID().uuidString)",
            tokenType: "Bearer",
            expiresIn: 3600
        )
    }
}

// MARK: - AuthService Tests

@MainActor
final class AuthServiceTests: XCTestCase {

    // MARK: - Properties

    var sut: AuthService!
    var mockKeychain: Keychain!
    var mockAPIService: MockAPIService!
    var cancellables: Set<AnyCancellable>!

    // MARK: - Setup / Teardown

    override func setUp() async throws {
        try await super.setUp()

        // Create a unique keychain service for tests to avoid conflicts
        let testService = "com.laya.admin.tests.\(UUID().uuidString)"
        mockKeychain = Keychain(service: testService)
            .accessibility(.whenUnlocked)
            .synchronizable(false)

        mockAPIService = MockAPIService()
        cancellables = Set<AnyCancellable>()

        // Clear any existing data
        try? mockKeychain.removeAll()
    }

    override func tearDown() async throws {
        // Clean up keychain
        try? mockKeychain.removeAll()

        mockKeychain = nil
        mockAPIService = nil
        sut = nil
        cancellables = nil

        try await super.tearDown()
    }

    // MARK: - Helper Methods

    private func createAuthService() -> AuthService {
        let service = AuthService(keychain: mockKeychain, apiService: mockAPIService)
        return service
    }

    // MARK: - Initial State Tests

    func testInitialState_isUnauthenticated() async throws {
        // Given
        sut = createAuthService()

        // Then
        XCTAssertEqual(sut.authState, .unauthenticated)
        XCTAssertFalse(sut.isAuthenticated)
        XCTAssertNil(sut.currentUser)
    }

    func testAuthStatePublisher_emitsCurrentState() async throws {
        // Given
        sut = createAuthService()
        var receivedStates: [AuthState] = []
        let expectation = expectation(description: "State published")

        // When
        sut.authStatePublisher
            .sink { state in
                receivedStates.append(state)
                if receivedStates.count == 1 {
                    expectation.fulfill()
                }
            }
            .store(in: &cancellables)

        await fulfillment(of: [expectation], timeout: 1.0)

        // Then
        XCTAssertEqual(receivedStates.first, .unauthenticated)
    }

    // MARK: - Login Tests

    func testLogin_withValidCredentials_returnsUserAndUpdatesState() async throws {
        // Given
        sut = createAuthService()
        let expectedUser = User.testUser
        mockAPIService.mockLoginResponse = .mock(user: expectedUser)

        // When
        let user = try await sut.login(email: "test@laya.ca", password: "password123", rememberMe: false)

        // Then
        XCTAssertEqual(user.id, expectedUser.id)
        XCTAssertEqual(user.email, expectedUser.email)
        XCTAssertEqual(sut.authState, .authenticated(expectedUser))
        XCTAssertTrue(sut.isAuthenticated)
        XCTAssertNotNil(sut.currentUser)
    }

    func testLogin_storesTokensInKeychain() async throws {
        // Given
        sut = createAuthService()
        let loginResponse = LoginResponse.mock()
        mockAPIService.mockLoginResponse = loginResponse

        // When
        _ = try await sut.login(email: "test@laya.ca", password: "password123", rememberMe: false)

        // Then
        let storedToken = try mockKeychain.get(KeychainKeys.jwtToken)
        let storedRefreshToken = try mockKeychain.get(KeychainKeys.refreshToken)
        let storedUserId = try mockKeychain.get(KeychainKeys.userId)

        XCTAssertEqual(storedToken, loginResponse.accessToken)
        XCTAssertEqual(storedRefreshToken, loginResponse.refreshToken)
        XCTAssertEqual(storedUserId, loginResponse.user.id)
    }

    func testLogin_withInvalidCredentials_throwsInvalidCredentialsError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .unauthorized(response: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "wrong@laya.ca", password: "wrongpassword", rememberMe: false)
            XCTFail("Expected error to be thrown")
        } catch let error as AuthError {
            XCTAssertEqual(error, .invalidCredentials)
            XCTAssertEqual(sut.authState, .failed(.invalidCredentials))
        }
    }

    func testLogin_withLockedAccount_throwsAccountLockedError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .forbidden(response: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "locked@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error to be thrown")
        } catch let error as AuthError {
            XCTAssertEqual(error, .accountLocked)
        }
    }

    func testLogin_withNetworkError_throwsNetworkError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .networkError(underlying: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error to be thrown")
        } catch let error as AuthError {
            if case .networkError = error {
                // Expected
            } else {
                XCTFail("Expected network error, got \(error)")
            }
        }
    }

    func testLogin_updatesStateToAuthenticating() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        var stateChanges: [AuthState] = []

        sut.authStatePublisher
            .sink { state in
                stateChanges.append(state)
            }
            .store(in: &cancellables)

        // When
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // Then - State should transition through authenticating
        XCTAssertTrue(stateChanges.contains(.authenticating))
    }

    func testLogin_postsAuthStateChangedNotification() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        let expectation = expectation(forNotification: .authStateChanged, object: sut)

        // When
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // Then
        await fulfillment(of: [expectation], timeout: 1.0)
    }

    // MARK: - Logout Tests

    func testLogout_clearsAuthState() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
        XCTAssertTrue(sut.isAuthenticated)

        // When
        await sut.logout()

        // Then
        XCTAssertEqual(sut.authState, .unauthenticated)
        XCTAssertFalse(sut.isAuthenticated)
        XCTAssertNil(sut.currentUser)
    }

    func testLogout_clearsKeychainCredentials() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // When
        await sut.logout()

        // Then
        let storedToken = try mockKeychain.get(KeychainKeys.jwtToken)
        let storedRefreshToken = try mockKeychain.get(KeychainKeys.refreshToken)
        let storedUserId = try mockKeychain.get(KeychainKeys.userId)

        XCTAssertNil(storedToken)
        XCTAssertNil(storedRefreshToken)
        XCTAssertNil(storedUserId)
    }

    func testLogout_attemptsToInvalidateRefreshTokenOnServer() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
        mockAPIService.capturedRequests = []

        // When
        await sut.logout()

        // Then - Should have attempted logout API call
        let logoutRequests = mockAPIService.capturedRequests.filter { $0.path == GibbonEndpoints.logout }
        XCTAssertEqual(logoutRequests.count, 1)
    }

    func testLogout_postsAuthStateChangedNotification() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        let expectation = expectation(forNotification: .authStateChanged, object: sut)
        expectation.expectedFulfillmentCount = 1

        // When
        await sut.logout()

        // Then
        await fulfillment(of: [expectation], timeout: 1.0)
    }

    // MARK: - Token Retrieval Tests

    func testGetAccessToken_whenAuthenticated_returnsToken() async throws {
        // Given
        sut = createAuthService()
        let loginResponse = LoginResponse.mock()
        mockAPIService.mockLoginResponse = loginResponse
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // When
        let token = sut.getAccessToken()

        // Then
        XCTAssertEqual(token, loginResponse.accessToken)
    }

    func testGetAccessToken_whenUnauthenticated_returnsNil() async throws {
        // Given
        sut = createAuthService()

        // When
        let token = sut.getAccessToken()

        // Then
        XCTAssertNil(token)
    }

    // MARK: - Token Refresh Tests

    func testRefreshToken_withValidRefreshToken_updatesAccessToken() async throws {
        // Given
        sut = createAuthService()
        let loginResponse = LoginResponse.mock()
        mockAPIService.mockLoginResponse = loginResponse
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        let refreshResponse = TokenRefreshResponse.mock
        mockAPIService.mockTokenRefreshResponse = refreshResponse

        // When
        try await sut.refreshToken()

        // Then
        let storedToken = try mockKeychain.get(KeychainKeys.jwtToken)
        XCTAssertEqual(storedToken, refreshResponse.accessToken)
    }

    func testRefreshToken_withoutRefreshToken_throwsInvalidRefreshTokenError() async throws {
        // Given
        sut = createAuthService()

        // When/Then
        do {
            try await sut.refreshToken()
            XCTFail("Expected error to be thrown")
        } catch let error as AuthError {
            XCTAssertEqual(error, .invalidRefreshToken)
        }
    }

    func testRefreshToken_withInvalidRefreshToken_triggersLogout() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockLoginResponse = .mock()
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        mockAPIService.shouldThrowError = .unauthorized(response: nil)

        // When/Then
        do {
            try await sut.refreshToken()
            XCTFail("Expected error to be thrown")
        } catch let error as AuthError {
            XCTAssertEqual(error, .invalidRefreshToken)
            // State should be tokenExpired
            XCTAssertEqual(sut.authState, .tokenExpired)
        }
    }

    // MARK: - Session Restore Tests

    func testRestoreSession_withValidStoredCredentials_restoresAuthentication() async throws {
        // Given
        sut = createAuthService()
        let user = User.testUser
        mockAPIService.mockLoginResponse = .mock(user: user)
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // Create new service instance to simulate app restart
        mockAPIService.mockUser = user
        let newService = createAuthService()

        // When
        let restored = await newService.restoreSession()

        // Then
        XCTAssertTrue(restored)
        XCTAssertTrue(newService.isAuthenticated)
        XCTAssertEqual(newService.currentUser?.id, user.id)
    }

    func testRestoreSession_withoutStoredCredentials_remainsUnauthenticated() async throws {
        // Given
        sut = createAuthService()

        // When
        let restored = await sut.restoreSession()

        // Then
        XCTAssertFalse(restored)
        XCTAssertEqual(sut.authState, .unauthenticated)
    }

    func testRestoreSession_withExpiredToken_attemptsRefresh() async throws {
        // Given
        sut = createAuthService()

        // Store expired token
        try mockKeychain.set("expired-token", key: KeychainKeys.jwtToken)
        try mockKeychain.set("valid-refresh-token", key: KeychainKeys.refreshToken)
        try mockKeychain.set("user-123", key: KeychainKeys.userId)
        try mockKeychain.set("director", key: KeychainKeys.userRole)

        // Set expired timestamp (1 hour ago)
        let expiredTimestamp = Date().addingTimeInterval(-3600).timeIntervalSince1970
        try mockKeychain.set(String(expiredTimestamp), key: "token_expires_at")

        mockAPIService.mockTokenRefreshResponse = .mock
        mockAPIService.mockUser = .testUser

        // Create new service to load credentials
        let newService = createAuthService()

        // When
        let restored = await newService.restoreSession()

        // Then - Should have attempted to refresh
        let refreshRequests = mockAPIService.capturedRequests.filter { $0.path == GibbonEndpoints.refreshToken }
        XCTAssertGreaterThan(refreshRequests.count, 0)
    }

    // MARK: - Error Mapping Tests

    func testErrorMapping_unauthorized_mapsToInvalidCredentials() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .unauthorized(response: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "wrong", rememberMe: false)
            XCTFail("Expected error")
        } catch let error as AuthError {
            XCTAssertEqual(error, .invalidCredentials)
        }
    }

    func testErrorMapping_forbidden_mapsToAccountLocked() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .forbidden(response: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error")
        } catch let error as AuthError {
            XCTAssertEqual(error, .accountLocked)
        }
    }

    func testErrorMapping_serverError_mapsToServerError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .serverError(statusCode: 500, response: APIErrorResponse(detail: "Server down"))

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error")
        } catch let error as AuthError {
            if case .serverError(let message) = error {
                XCTAssertEqual(message, "Server down")
            } else {
                XCTFail("Expected server error")
            }
        }
    }

    func testErrorMapping_networkError_mapsToNetworkError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .networkError(underlying: nil)

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error")
        } catch let error as AuthError {
            if case .networkError = error {
                // Expected
            } else {
                XCTFail("Expected network error")
            }
        }
    }

    func testErrorMapping_timeout_mapsToNetworkError() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.shouldThrowError = .timeout

        // When/Then
        do {
            _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)
            XCTFail("Expected error")
        } catch let error as AuthError {
            if case .networkError = error {
                // Expected
            } else {
                XCTFail("Expected network error")
            }
        }
    }

    // MARK: - AuthState Tests

    func testAuthState_isAuthenticated_returnsCorrectValue() {
        // Test all states
        XCTAssertFalse(AuthState.unauthenticated.isAuthenticated)
        XCTAssertFalse(AuthState.authenticating.isAuthenticated)
        XCTAssertTrue(AuthState.authenticated(.testUser).isAuthenticated)
        XCTAssertFalse(AuthState.failed(.invalidCredentials).isAuthenticated)
        XCTAssertFalse(AuthState.tokenExpired.isAuthenticated)
    }

    func testAuthState_currentUser_returnsUserOnlyWhenAuthenticated() {
        // Test all states
        XCTAssertNil(AuthState.unauthenticated.currentUser)
        XCTAssertNil(AuthState.authenticating.currentUser)
        XCTAssertNotNil(AuthState.authenticated(.testUser).currentUser)
        XCTAssertNil(AuthState.failed(.invalidCredentials).currentUser)
        XCTAssertNil(AuthState.tokenExpired.currentUser)
    }

    func testAuthState_equatable_comparesCorrectly() {
        // Given
        let user1 = User.testUser
        let user2 = User.testAccountant

        // Then
        XCTAssertEqual(AuthState.unauthenticated, AuthState.unauthenticated)
        XCTAssertEqual(AuthState.authenticating, AuthState.authenticating)
        XCTAssertEqual(AuthState.authenticated(user1), AuthState.authenticated(user1))
        XCTAssertNotEqual(AuthState.authenticated(user1), AuthState.authenticated(user2))
        XCTAssertEqual(AuthState.failed(.invalidCredentials), AuthState.failed(.invalidCredentials))
        XCTAssertNotEqual(AuthState.failed(.invalidCredentials), AuthState.failed(.accountLocked))
        XCTAssertEqual(AuthState.tokenExpired, AuthState.tokenExpired)
    }

    // MARK: - AuthError Tests

    func testAuthError_localizedDescription_providesUserFriendlyMessage() {
        // Test various error descriptions
        XCTAssertFalse(AuthError.invalidCredentials.localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.accountLocked.localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.emailNotVerified.localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.networkError("Test").localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.serverError("Test").localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.invalidToken.localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.invalidRefreshToken.localizedDescription.isEmpty)
        XCTAssertFalse(AuthError.unknown("Test").localizedDescription.isEmpty)
    }

    func testAuthError_recoverySuggestion_providesHelpfulGuidance() {
        // Test various recovery suggestions
        XCTAssertFalse(AuthError.invalidCredentials.recoverySuggestion.isEmpty)
        XCTAssertFalse(AuthError.accountLocked.recoverySuggestion.isEmpty)
        XCTAssertFalse(AuthError.networkError("Test").recoverySuggestion.isEmpty)
        XCTAssertFalse(AuthError.invalidToken.recoverySuggestion.isEmpty)
    }

    func testAuthError_equatable_comparesCorrectly() {
        // Given
        let error1 = AuthError.networkError("Error 1")
        let error2 = AuthError.networkError("Error 1")
        let error3 = AuthError.networkError("Error 2")

        // Then
        XCTAssertEqual(error1, error2)
        XCTAssertNotEqual(error1, error3)
        XCTAssertEqual(AuthError.invalidCredentials, AuthError.invalidCredentials)
        XCTAssertNotEqual(AuthError.invalidCredentials, AuthError.accountLocked)
    }

    // MARK: - StoredCredentials Tests

    func testStoredCredentials_hasValidCredentials_checksTokenAndExpiry() {
        // Given - Valid credentials with future expiry
        let validCredentials = StoredCredentials(
            accessToken: "valid-token",
            refreshToken: "refresh-token",
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(3600)
        )

        // Then
        XCTAssertTrue(validCredentials.hasValidCredentials)

        // Given - Expired credentials
        let expiredCredentials = StoredCredentials(
            accessToken: "expired-token",
            refreshToken: "refresh-token",
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(-3600)
        )

        // Then
        XCTAssertFalse(expiredCredentials.hasValidCredentials)

        // Given - Missing token
        let missingTokenCredentials = StoredCredentials(
            accessToken: nil,
            refreshToken: "refresh-token",
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(3600)
        )

        // Then
        XCTAssertFalse(missingTokenCredentials.hasValidCredentials)
    }

    func testStoredCredentials_needsRefresh_checksExpiryWithRefreshToken() {
        // Given - Expired but has refresh token
        let needsRefresh = StoredCredentials(
            accessToken: "expired-token",
            refreshToken: "valid-refresh-token",
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(-3600)
        )

        // Then
        XCTAssertTrue(needsRefresh.needsRefresh)

        // Given - No refresh token
        let noRefreshToken = StoredCredentials(
            accessToken: "expired-token",
            refreshToken: nil,
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(-3600)
        )

        // Then
        XCTAssertFalse(noRefreshToken.needsRefresh)

        // Given - Valid (not expired)
        let valid = StoredCredentials(
            accessToken: "valid-token",
            refreshToken: "refresh-token",
            userId: "user-123",
            userRole: .director,
            tokenExpiresAt: Date().addingTimeInterval(3600)
        )

        // Then
        XCTAssertFalse(valid.needsRefresh)
    }

    func testStoredCredentials_empty_hasNoData() {
        // Given
        let empty = StoredCredentials.empty

        // Then
        XCTAssertNil(empty.accessToken)
        XCTAssertNil(empty.refreshToken)
        XCTAssertNil(empty.userId)
        XCTAssertNil(empty.userRole)
        XCTAssertNil(empty.tokenExpiresAt)
        XCTAssertFalse(empty.hasValidCredentials)
        XCTAssertFalse(empty.needsRefresh)
    }

    // MARK: - User Role Tests

    func testLogin_storesUserRole() async throws {
        // Given
        sut = createAuthService()
        let director = User.testUser
        mockAPIService.mockLoginResponse = .mock(user: director)

        // When
        _ = try await sut.login(email: "test@laya.ca", password: "password", rememberMe: false)

        // Then
        let storedRole = try mockKeychain.get(KeychainKeys.userRole)
        XCTAssertEqual(storedRole, "director")
    }

    func testLogin_withAccountantRole_storesCorrectRole() async throws {
        // Given
        sut = createAuthService()
        let accountant = User.testAccountant
        mockAPIService.mockLoginResponse = .mock(user: accountant)

        // When
        _ = try await sut.login(email: "accountant@laya.ca", password: "password", rememberMe: false)

        // Then
        let storedRole = try mockKeychain.get(KeychainKeys.userRole)
        XCTAssertEqual(storedRole, "accountant")
        XCTAssertEqual(sut.currentUser?.role, .accountant)
    }

    // MARK: - API Service Integration Tests

    func testAPIService_tokenProviderIsConfigured() async throws {
        // Given
        sut = createAuthService()

        // Then - Auth token provider should be configured on API service
        XCTAssertNotNil(mockAPIService.authTokenProvider)
    }

    func testAPIService_authFailureHandlerIsConfigured() async throws {
        // Given
        sut = createAuthService()

        // Then - Auth failure handler should be configured
        XCTAssertNotNil(mockAPIService.onAuthenticationFailure)
    }

    // MARK: - Password Reset Tests

    func testRequestPasswordReset_sendsRequestToCorrectEndpoint() async throws {
        // Given
        sut = createAuthService()
        mockAPIService.mockPasswordResetResponse = PasswordResetResponse(
            message: "Password reset email sent",
            success: true
        )

        // When
        let message = try await sut.requestPasswordReset(email: "test@laya.ca")

        // Then
        XCTAssertEqual(message, "Password reset email sent")

        let resetRequests = mockAPIService.capturedRequests.filter { $0.path == "/auth/password-reset" }
        XCTAssertEqual(resetRequests.count, 1)
    }

    // MARK: - Concurrent Login Tests

    func testMultipleLogins_overwritesPreviousSession() async throws {
        // Given
        sut = createAuthService()
        let user1 = User.testUser
        let user2 = User.testAccountant

        // First login
        mockAPIService.mockLoginResponse = .mock(user: user1)
        _ = try await sut.login(email: "user1@laya.ca", password: "password", rememberMe: false)
        XCTAssertEqual(sut.currentUser?.id, user1.id)

        // Second login
        mockAPIService.mockLoginResponse = .mock(user: user2)
        _ = try await sut.login(email: "user2@laya.ca", password: "password", rememberMe: false)

        // Then - Second user should be current
        XCTAssertEqual(sut.currentUser?.id, user2.id)
    }
}

// MARK: - LoginRequest Tests

final class LoginRequestTests: XCTestCase {

    func testLoginRequest_encodesCorrectly() throws {
        // Given
        let request = LoginRequest(email: "test@laya.ca", password: "password123", rememberMe: true)
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase

        // When
        let data = try encoder.encode(request)
        let json = try JSONSerialization.jsonObject(with: data) as? [String: Any]

        // Then
        XCTAssertEqual(json?["email"] as? String, "test@laya.ca")
        XCTAssertEqual(json?["password"] as? String, "password123")
        XCTAssertEqual(json?["remember_me"] as? Bool, true)
    }
}

// MARK: - LoginResponse Tests

final class LoginResponseTests: XCTestCase {

    func testLoginResponse_decodesCorrectly() throws {
        // Given
        let json = """
        {
            "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
            "refresh_token": "refresh-token-12345",
            "token_type": "Bearer",
            "expires_in": 3600,
            "user": {
                "id": "user-123",
                "email": "test@laya.ca",
                "first_name": "Test",
                "last_name": "User",
                "role": "director",
                "is_active": true
            }
        }
        """
        let data = json.data(using: .utf8)!
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        // When
        let response = try decoder.decode(LoginResponse.self, from: data)

        // Then
        XCTAssertTrue(response.accessToken.hasPrefix("eyJ"))
        XCTAssertEqual(response.refreshToken, "refresh-token-12345")
        XCTAssertEqual(response.tokenType, "Bearer")
        XCTAssertEqual(response.expiresIn, 3600)
        XCTAssertEqual(response.user.id, "user-123")
        XCTAssertEqual(response.user.email, "test@laya.ca")
        XCTAssertEqual(response.user.role, .director)
    }
}

// MARK: - TokenRefreshRequest Tests

final class TokenRefreshRequestTests: XCTestCase {

    func testTokenRefreshRequest_encodesCorrectly() throws {
        // Given
        let request = TokenRefreshRequest(refreshToken: "refresh-token-123")
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase

        // When
        let data = try encoder.encode(request)
        let json = try JSONSerialization.jsonObject(with: data) as? [String: Any]

        // Then
        XCTAssertEqual(json?["refresh_token"] as? String, "refresh-token-123")
    }
}

// MARK: - SessionInfo Tests

final class SessionInfoTests: XCTestCase {

    func testSessionInfo_decodesCorrectly() throws {
        // Given
        let json = """
        {
            "session_id": "session-123",
            "device_name": "MacBook Pro",
            "ip_address": "192.168.1.100",
            "user_agent": "LAYAAdmin/1.0",
            "created_at": "2024-01-15T10:00:00Z",
            "last_active_at": "2024-01-15T12:00:00Z",
            "is_current": true
        }
        """
        let data = json.data(using: .utf8)!
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        decoder.dateDecodingStrategy = .iso8601

        // When
        let session = try decoder.decode(SessionInfo.self, from: data)

        // Then
        XCTAssertEqual(session.sessionId, "session-123")
        XCTAssertEqual(session.deviceName, "MacBook Pro")
        XCTAssertEqual(session.ipAddress, "192.168.1.100")
        XCTAssertEqual(session.userAgent, "LAYAAdmin/1.0")
        XCTAssertTrue(session.isCurrent)
    }
}
