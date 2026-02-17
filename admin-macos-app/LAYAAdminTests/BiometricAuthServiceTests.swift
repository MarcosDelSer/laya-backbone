//
//  BiometricAuthServiceTests.swift
//  LAYAAdminTests
//
//  Unit tests for BiometricAuthService verifying biometric availability,
//  authentication flows, error handling, and caching behavior.
//

import XCTest
import LocalAuthentication
import Combine
@testable import LAYAAdmin

// MARK: - Mock LAContext

/// Mock LAContext for testing biometric authentication
final class MockLAContext {

    var canEvaluateResult = true
    var canEvaluateError: NSError?
    var mockBiometryType: LABiometryType = .touchID
    var evaluatePolicyResult = true
    var evaluatePolicyError: NSError?
    var evaluateCallCount = 0

    func canEvaluatePolicy(_ policy: LAPolicy, error: inout NSError?) -> Bool {
        if let mockError = canEvaluateError {
            error = mockError
            return false
        }
        return canEvaluateResult
    }

    func evaluatePolicy(
        _ policy: LAPolicy,
        localizedReason: String,
        reply: @escaping (Bool, Error?) -> Void
    ) {
        evaluateCallCount += 1

        DispatchQueue.global().asyncAfter(deadline: .now() + 0.01) {
            if self.evaluatePolicyResult {
                reply(true, nil)
            } else {
                reply(false, self.evaluatePolicyError)
            }
        }
    }

    func reset() {
        canEvaluateResult = true
        canEvaluateError = nil
        mockBiometryType = .touchID
        evaluatePolicyResult = true
        evaluatePolicyError = nil
        evaluateCallCount = 0
    }
}

// MARK: - BiometricAuthService Tests

@MainActor
final class BiometricAuthServiceTests: XCTestCase {

    // MARK: - Properties

    var sut: BiometricAuthService!
    var cancellables: Set<AnyCancellable>!

    // MARK: - Setup / Teardown

    override func setUp() async throws {
        try await super.setUp()

        cancellables = Set<AnyCancellable>()

        // Clear environment variables
        setenv("MOCK_BIOMETRIC_AVAILABLE", "", 1)
        setenv("MOCK_BIOMETRIC_SUCCESS", "", 1)
        setenv("MOCK_BIOMETRIC_FAILURE", "", 1)
    }

    override func tearDown() async throws {
        sut = nil
        cancellables = nil

        // Clear environment variables
        unsetenv("MOCK_BIOMETRIC_AVAILABLE")
        unsetenv("MOCK_BIOMETRIC_SUCCESS")
        unsetenv("MOCK_BIOMETRIC_FAILURE")

        try await super.tearDown()
    }

    // MARK: - Helper Methods

    private func createBiometricAuthService() -> BiometricAuthService {
        return BiometricAuthService()
    }

    // MARK: - Initial State Tests

    func testInitialState_defaultValues() async throws {
        // Given/When
        sut = createBiometricAuthService()

        // Allow time for async initialization
        try await Task.sleep(nanoseconds: 100_000_000) // 0.1 seconds

        // Then - Initial values are set (actual values depend on device)
        XCTAssertNotNil(sut.biometricType)
    }

    func testBiometricTypePublisher_isPublished() async throws {
        // Given
        sut = createBiometricAuthService()
        var receivedTypes: [BiometricType] = []
        let expectation = expectation(description: "Type published")
        expectation.expectedFulfillmentCount = 1

        // When
        sut.$biometricType
            .sink { type in
                receivedTypes.append(type)
                if receivedTypes.count >= 1 {
                    expectation.fulfill()
                }
            }
            .store(in: &cancellables)

        await fulfillment(of: [expectation], timeout: 1.0)

        // Then
        XCTAssertGreaterThan(receivedTypes.count, 0)
    }

    func testIsBiometricAvailablePublisher_isPublished() async throws {
        // Given
        sut = createBiometricAuthService()
        var receivedValues: [Bool] = []
        let expectation = expectation(description: "Availability published")
        expectation.expectedFulfillmentCount = 1

        // When
        sut.$isBiometricAvailable
            .sink { available in
                receivedValues.append(available)
                if receivedValues.count >= 1 {
                    expectation.fulfill()
                }
            }
            .store(in: &cancellables)

        await fulfillment(of: [expectation], timeout: 1.0)

        // Then
        XCTAssertGreaterThan(receivedValues.count, 0)
    }

    // MARK: - Availability Tests (Debug Mode)

    func testCheckBiometricAvailability_withMockAvailable_returnsSuccess() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When
        let result = await sut.checkBiometricAvailability()

        // Then
        switch result {
        case .success(let type):
            XCTAssertEqual(type, .touchID)
            XCTAssertTrue(sut.isBiometricAvailable)
            XCTAssertEqual(sut.biometricType, .touchID)
        case .failure:
            XCTFail("Expected success with mock available")
        }
    }

    func testCheckBiometricAvailability_withMockAvailable_updatesBiometricType() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When
        _ = await sut.checkBiometricAvailability()

        // Then
        XCTAssertEqual(sut.biometricType, .touchID)
    }

    func testCheckBiometricAvailability_withMockAvailable_updatesIsBiometricAvailable() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When
        _ = await sut.checkBiometricAvailability()

        // Then
        XCTAssertTrue(sut.isBiometricAvailable)
    }

    // MARK: - Authentication Tests (Debug Mode)

    func testAuthenticateWithBiometrics_withMockSuccess_returnsSuccess() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_SUCCESS", "true", 1)
        sut = createBiometricAuthService()

        // When
        let result = await sut.authenticateWithBiometrics(reason: "Test authentication")

        // Then
        switch result {
        case .success:
            break // Expected
        case .failure:
            XCTFail("Expected success with mock success")
        }
    }

    func testAuthenticateWithBiometrics_withMockFailure_returnsFailure() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_FAILURE", "true", 1)
        sut = createBiometricAuthService()

        // When
        let result = await sut.authenticateWithBiometrics(reason: "Test authentication")

        // Then
        switch result {
        case .success:
            XCTFail("Expected failure with mock failure")
        case .failure(let error):
            XCTAssertEqual(error, .authenticationFailed)
        }
    }

    func testAuthenticateWithBiometrics_withDefaultReason_usesProvidedReason() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_SUCCESS", "true", 1)
        sut = createBiometricAuthService()

        // When
        let result = await sut.authenticateWithBiometrics()

        // Then - Should use default reason without error
        switch result {
        case .success:
            break // Expected
        case .failure:
            XCTFail("Expected success")
        }
    }

    func testAuthenticateWithBiometrics_withCustomReason_acceptsCustomReason() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_SUCCESS", "true", 1)
        sut = createBiometricAuthService()
        let customReason = "Custom authentication reason"

        // When
        let result = await sut.authenticateWithBiometrics(reason: customReason)

        // Then
        switch result {
        case .success:
            break // Expected
        case .failure:
            XCTFail("Expected success")
        }
    }

    // MARK: - Cache Tests

    func testCheckBiometricAvailability_cachesBiometricType() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When - Call twice
        let result1 = await sut.checkBiometricAvailability()
        let result2 = await sut.checkBiometricAvailability()

        // Then - Both should return the same result
        switch (result1, result2) {
        case (.success(let type1), .success(let type2)):
            XCTAssertEqual(type1, type2)
        default:
            XCTFail("Expected both calls to succeed")
        }
    }

    func testInvalidateCache_clearsCache() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()
        _ = await sut.checkBiometricAvailability()

        // When
        sut.invalidateCache()

        // Allow time for async re-check
        try await Task.sleep(nanoseconds: 100_000_000) // 0.1 seconds

        // Then - Service should still function
        let result = await sut.checkBiometricAvailability()
        switch result {
        case .success:
            break // Expected
        case .failure:
            XCTFail("Expected availability check to succeed after cache invalidation")
        }
    }

    func testInvalidateCache_triggersRecheck() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()
        _ = await sut.checkBiometricAvailability()

        var publishedTypes: [BiometricType] = []
        let expectation = expectation(description: "Type re-published after invalidation")

        sut.$biometricType
            .dropFirst() // Drop initial value
            .sink { type in
                publishedTypes.append(type)
                expectation.fulfill()
            }
            .store(in: &cancellables)

        // When
        sut.invalidateCache()

        // Then - Should trigger a re-check and publish
        await fulfillment(of: [expectation], timeout: 1.0)
        XCTAssertGreaterThan(publishedTypes.count, 0)
    }

    // MARK: - BiometricAuthError Tests

    func testBiometricAuthError_localizedDescription_providesUserFriendlyMessage() {
        // Test various error descriptions
        XCTAssertFalse(BiometricAuthError.notAvailable.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.notEnrolled.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.userCancelled.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.authenticationFailed.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.systemCancelled.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.biometryLockout.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.userFallback.localizedDescription.isEmpty)
        XCTAssertFalse(BiometricAuthError.unknown("Test").localizedDescription.isEmpty)
    }

    func testBiometricAuthError_recoverySuggestion_providesHelpfulGuidance() {
        // Test various recovery suggestions
        XCTAssertFalse(BiometricAuthError.notAvailable.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.notEnrolled.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.userCancelled.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.authenticationFailed.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.systemCancelled.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.biometryLockout.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.userFallback.recoverySuggestion.isEmpty)
        XCTAssertFalse(BiometricAuthError.unknown("Test").recoverySuggestion.isEmpty)
    }

    func testBiometricAuthError_equatable_comparesCorrectly() {
        // Given
        let error1 = BiometricAuthError.unknown("Error 1")
        let error2 = BiometricAuthError.unknown("Error 1")
        let error3 = BiometricAuthError.unknown("Error 2")

        // Then
        XCTAssertEqual(error1, error2)
        XCTAssertNotEqual(error1, error3)
        XCTAssertEqual(BiometricAuthError.notAvailable, BiometricAuthError.notAvailable)
        XCTAssertNotEqual(BiometricAuthError.notAvailable, BiometricAuthError.notEnrolled)
        XCTAssertEqual(BiometricAuthError.userCancelled, BiometricAuthError.userCancelled)
        XCTAssertEqual(BiometricAuthError.authenticationFailed, BiometricAuthError.authenticationFailed)
        XCTAssertEqual(BiometricAuthError.systemCancelled, BiometricAuthError.systemCancelled)
        XCTAssertEqual(BiometricAuthError.biometryLockout, BiometricAuthError.biometryLockout)
        XCTAssertEqual(BiometricAuthError.userFallback, BiometricAuthError.userFallback)
    }

    func testBiometricAuthError_unknownError_includesMessage() {
        // Given
        let message = "Custom error message"
        let error = BiometricAuthError.unknown(message)

        // Then
        XCTAssertTrue(error.localizedDescription.contains(message))
    }

    // MARK: - BiometricType Tests

    func testBiometricType_displayName_providesLocalizedNames() {
        // Test display names
        XCTAssertFalse(BiometricType.touchID.displayName.isEmpty)
        XCTAssertFalse(BiometricType.faceID.displayName.isEmpty)
        XCTAssertFalse(BiometricType.none.displayName.isEmpty)
    }

    func testBiometricType_touchID_hasCorrectDisplayName() {
        // Given
        let type = BiometricType.touchID

        // Then
        XCTAssertEqual(type.displayName, String(localized: "Touch ID"))
    }

    func testBiometricType_faceID_hasCorrectDisplayName() {
        // Given
        let type = BiometricType.faceID

        // Then
        XCTAssertEqual(type.displayName, String(localized: "Face ID"))
    }

    func testBiometricType_none_hasCorrectDisplayName() {
        // Given
        let type = BiometricType.none

        // Then
        XCTAssertEqual(type.displayName, String(localized: "None"))
    }

    // MARK: - Singleton Tests

    func testSharedInstance_returnsSameInstance() {
        // Given
        let instance1 = BiometricAuthService.shared
        let instance2 = BiometricAuthService.shared

        // Then
        XCTAssertTrue(instance1 === instance2)
    }

    // MARK: - Error Scenarios Tests

    func testAuthenticateWithBiometrics_differentErrorTypes_mapsCorrectly() async throws {
        // This test verifies that different error types are handled
        // Note: Actual error mapping is tested through debug mode flags

        // Given
        setenv("MOCK_BIOMETRIC_FAILURE", "true", 1)
        sut = createBiometricAuthService()

        // When
        let result = await sut.authenticateWithBiometrics()

        // Then - Should return an error
        switch result {
        case .success:
            XCTFail("Expected failure")
        case .failure(let error):
            XCTAssertNotNil(error.localizedDescription)
            XCTAssertNotNil(error.recoverySuggestion)
        }
    }

    // MARK: - Concurrent Access Tests

    func testConcurrentAvailabilityChecks_handleSafely() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When - Make multiple concurrent calls
        async let result1 = sut.checkBiometricAvailability()
        async let result2 = sut.checkBiometricAvailability()
        async let result3 = sut.checkBiometricAvailability()

        let results = await [result1, result2, result3]

        // Then - All should succeed with same result
        for result in results {
            switch result {
            case .success(let type):
                XCTAssertEqual(type, .touchID)
            case .failure:
                XCTFail("Expected all concurrent calls to succeed")
            }
        }
    }

    func testConcurrentAuthentications_handleSafely() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_SUCCESS", "true", 1)
        sut = createBiometricAuthService()

        // When - Make multiple concurrent authentication calls
        async let result1 = sut.authenticateWithBiometrics(reason: "Test 1")
        async let result2 = sut.authenticateWithBiometrics(reason: "Test 2")

        let results = await [result1, result2]

        // Then - All should complete without crashes
        for result in results {
            switch result {
            case .success:
                break // Expected
            case .failure:
                XCTFail("Expected all concurrent authentications to succeed")
            }
        }
    }

    // MARK: - Protocol Conformance Tests

    func testBiometricAuthServiceProtocol_conformance() {
        // Given
        sut = createBiometricAuthService()

        // Then - Should conform to protocol
        XCTAssertTrue(sut is BiometricAuthServiceProtocol)
    }

    func testBiometricAuthServiceProtocol_hasRequiredProperties() {
        // Given
        sut = createBiometricAuthService()
        let protocolInstance: BiometricAuthServiceProtocol = sut

        // Then - Should expose protocol properties
        _ = protocolInstance.biometricType
        _ = protocolInstance.isBiometricAvailable
    }

    func testBiometricAuthServiceProtocol_hasRequiredMethods() async throws {
        // Given
        sut = createBiometricAuthService()
        let protocolInstance: BiometricAuthServiceProtocol = sut

        // When - Should be able to call protocol methods
        _ = await protocolInstance.checkBiometricAvailability()
        _ = await protocolInstance.authenticateWithBiometrics(reason: "Test")

        // Then - No crash
    }

    // MARK: - ObservableObject Conformance Tests

    func testObservableObject_conformance() {
        // Given
        sut = createBiometricAuthService()

        // Then - Should conform to ObservableObject
        XCTAssertTrue(sut is ObservableObject)
    }

    func testPublishedProperties_emitChanges() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        var biometricTypeChanges = 0
        var availabilityChanges = 0

        let typeExpectation = expectation(description: "Biometric type changed")
        let availabilityExpectation = expectation(description: "Availability changed")

        sut.$biometricType
            .dropFirst()
            .sink { _ in
                biometricTypeChanges += 1
                typeExpectation.fulfill()
            }
            .store(in: &cancellables)

        sut.$isBiometricAvailable
            .dropFirst()
            .sink { _ in
                availabilityChanges += 1
                availabilityExpectation.fulfill()
            }
            .store(in: &cancellables)

        // When
        _ = await sut.checkBiometricAvailability()

        // Then
        await fulfillment(of: [typeExpectation, availabilityExpectation], timeout: 1.0)
        XCTAssertGreaterThan(biometricTypeChanges, 0)
        XCTAssertGreaterThan(availabilityChanges, 0)
    }

    // MARK: - Edge Cases Tests

    func testMultipleInvalidations_handleSafely() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        sut = createBiometricAuthService()

        // When - Invalidate cache multiple times
        sut.invalidateCache()
        sut.invalidateCache()
        sut.invalidateCache()

        // Allow time for async operations
        try await Task.sleep(nanoseconds: 200_000_000) // 0.2 seconds

        // Then - Should not crash and availability check should work
        let result = await sut.checkBiometricAvailability()
        switch result {
        case .success:
            break // Expected
        case .failure:
            XCTFail("Expected availability check to succeed after multiple invalidations")
        }
    }

    func testCheckAvailability_afterMultipleAuthentications_remainsConsistent() async throws {
        // Given
        setenv("MOCK_BIOMETRIC_AVAILABLE", "true", 1)
        setenv("MOCK_BIOMETRIC_SUCCESS", "true", 1)
        sut = createBiometricAuthService()

        // When - Authenticate multiple times then check availability
        _ = await sut.authenticateWithBiometrics()
        _ = await sut.authenticateWithBiometrics()
        let result = await sut.checkBiometricAvailability()

        // Then - Availability should still be correct
        switch result {
        case .success(let type):
            XCTAssertEqual(type, .touchID)
        case .failure:
            XCTFail("Expected availability check to succeed")
        }
    }
}
