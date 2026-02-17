//
//  BiometricAuthService.swift
//  LAYAAdmin
//
//  Biometric authentication service for macOS Touch ID and Face ID.
//  Handles biometric availability checks and authentication prompts.
//

import Foundation
import LocalAuthentication
import Combine

// MARK: - Biometric Auth Error

/// Errors that can occur during biometric authentication.
enum BiometricAuthError: Error, Equatable {

    /// Biometric authentication is not available on this device
    case notAvailable

    /// No biometric sensors are enrolled (no fingerprints or face registered)
    case notEnrolled

    /// User cancelled the biometric prompt
    case userCancelled

    /// User failed biometric authentication (wrong fingerprint/face)
    case authenticationFailed

    /// System cancelled the authentication (e.g., user switched apps)
    case systemCancelled

    /// Biometric authentication is locked out due to too many failed attempts
    case biometryLockout

    /// User chose to use password instead of biometrics
    case userFallback

    /// Unknown error occurred
    case unknown(String)

    // MARK: - Localized Description

    var localizedDescription: String {
        switch self {
        case .notAvailable:
            return String(localized: "Biometric authentication is not available on this device.")
        case .notEnrolled:
            return String(localized: "No fingerprints or faces are enrolled. Please set up Touch ID or Face ID in System Preferences.")
        case .userCancelled:
            return String(localized: "Biometric authentication was cancelled.")
        case .authenticationFailed:
            return String(localized: "Biometric authentication failed. Please try again.")
        case .systemCancelled:
            return String(localized: "Authentication was interrupted by the system.")
        case .biometryLockout:
            return String(localized: "Biometric authentication is locked. Please use your password.")
        case .userFallback:
            return String(localized: "Password authentication selected.")
        case .unknown(let message):
            return String(localized: "Biometric authentication error: \(message)")
        }
    }

    // MARK: - Recovery Suggestion

    var recoverySuggestion: String {
        switch self {
        case .notAvailable:
            return String(localized: "Use your password to log in.")
        case .notEnrolled:
            return String(localized: "Set up Touch ID or Face ID in System Preferences, or use your password.")
        case .userCancelled:
            return String(localized: "Try biometric authentication again or use your password.")
        case .authenticationFailed:
            return String(localized: "Verify your fingerprint or face and try again.")
        case .systemCancelled:
            return String(localized: "Please try biometric authentication again.")
        case .biometryLockout:
            return String(localized: "Too many failed attempts. Use your password to unlock.")
        case .userFallback:
            return String(localized: "Enter your password to continue.")
        case .unknown:
            return String(localized: "Please try again or use your password.")
        }
    }

    // MARK: - Equatable

    static func == (lhs: BiometricAuthError, rhs: BiometricAuthError) -> Bool {
        switch (lhs, rhs) {
        case (.notAvailable, .notAvailable):
            return true
        case (.notEnrolled, .notEnrolled):
            return true
        case (.userCancelled, .userCancelled):
            return true
        case (.authenticationFailed, .authenticationFailed):
            return true
        case (.systemCancelled, .systemCancelled):
            return true
        case (.biometryLockout, .biometryLockout):
            return true
        case (.userFallback, .userFallback):
            return true
        case (.unknown(let lhsMsg), .unknown(let rhsMsg)):
            return lhsMsg == rhsMsg
        default:
            return false
        }
    }
}

// MARK: - Biometric Type

/// Type of biometric authentication available on the device.
enum BiometricType {
    /// Touch ID is available
    case touchID

    /// Face ID is available
    case faceID

    /// No biometric authentication available
    case none

    var displayName: String {
        switch self {
        case .touchID:
            return String(localized: "Touch ID")
        case .faceID:
            return String(localized: "Face ID")
        case .none:
            return String(localized: "None")
        }
    }
}

// MARK: - Biometric Auth Service Protocol

/// Protocol defining the biometric authentication service interface
protocol BiometricAuthServiceProtocol {
    /// Type of biometric authentication available
    var biometricType: BiometricType { get }

    /// Whether biometric authentication is available on this device
    var isBiometricAvailable: Bool { get }

    /// Checks if biometric authentication is available
    func checkBiometricAvailability() async -> Result<BiometricType, BiometricAuthError>

    /// Authenticates the user with biometrics
    func authenticateWithBiometrics(reason: String) async -> Result<Void, BiometricAuthError>
}

// MARK: - Biometric Auth Service

/// Biometric authentication service for macOS.
///
/// Features:
/// - Touch ID and Face ID support
/// - Device capability detection
/// - Secure biometric prompts via LocalAuthentication framework
/// - Graceful fallback handling
@MainActor
final class BiometricAuthService: ObservableObject, BiometricAuthServiceProtocol {

    // MARK: - Singleton

    /// Shared instance for the app
    static let shared = BiometricAuthService()

    // MARK: - Published Properties

    /// Type of biometric authentication available
    @Published private(set) var biometricType: BiometricType = .none

    /// Whether biometric authentication is available
    @Published private(set) var isBiometricAvailable: Bool = false

    // MARK: - Private Properties

    /// Cached availability result to avoid repeated checks
    private var cachedAvailability: (type: BiometricType, timestamp: Date)?

    /// Cache validity duration (5 minutes)
    private let cacheValidityDuration: TimeInterval = 300

    // MARK: - Initialization

    /// Creates a new BiometricAuthService instance
    init() {
        // Initialize biometric availability on creation
        Task {
            _ = await checkBiometricAvailability()
        }
    }

    // MARK: - Public Methods

    /// Checks if biometric authentication is available on this device.
    ///
    /// - Returns: Result containing BiometricType or BiometricAuthError
    func checkBiometricAvailability() async -> Result<BiometricType, BiometricAuthError> {
        #if DEBUG
        // In debug mode, allow mock availability for testing
        if ProcessInfo.processInfo.environment["MOCK_BIOMETRIC_AVAILABLE"] == "true" {
            let mockType: BiometricType = .touchID
            self.biometricType = mockType
            self.isBiometricAvailable = true
            return .success(mockType)
        }
        #endif

        // Check cache first
        if let cached = cachedAvailability,
           Date().timeIntervalSince(cached.timestamp) < cacheValidityDuration {
            return .success(cached.type)
        }

        let context = LAContext()
        var error: NSError?

        // Check if biometric authentication is available
        let canEvaluate = context.canEvaluatePolicy(
            .deviceOwnerAuthenticationWithBiometrics,
            error: &error
        )

        if canEvaluate {
            // Determine biometric type
            let type: BiometricType
            if #available(macOS 10.15, *) {
                switch context.biometryType {
                case .touchID:
                    type = .touchID
                case .faceID:
                    type = .faceID
                case .none:
                    type = .none
                @unknown default:
                    type = .none
                }
            } else {
                // On macOS < 10.15, assume Touch ID if available
                type = .touchID
            }

            // Update state
            self.biometricType = type
            self.isBiometricAvailable = true

            // Cache result
            cachedAvailability = (type: type, timestamp: Date())

            return .success(type)
        } else {
            // Biometric authentication not available
            let authError = mapLAError(error)

            // Update state
            self.biometricType = .none
            self.isBiometricAvailable = false

            // Cache result
            cachedAvailability = (type: .none, timestamp: Date())

            return .failure(authError)
        }
    }

    /// Authenticates the user using biometric authentication.
    ///
    /// - Parameter reason: Localized reason for authentication (displayed to user)
    /// - Returns: Result indicating success or BiometricAuthError
    func authenticateWithBiometrics(reason: String = "Authenticate to access LAYA Admin") async -> Result<Void, BiometricAuthError> {
        #if DEBUG
        // In debug mode, allow mock authentication for testing
        if ProcessInfo.processInfo.environment["MOCK_BIOMETRIC_SUCCESS"] == "true" {
            return .success(())
        }
        if ProcessInfo.processInfo.environment["MOCK_BIOMETRIC_FAILURE"] == "true" {
            return .failure(.authenticationFailed)
        }
        #endif

        let context = LAContext()
        var error: NSError?

        // First check if biometric authentication is available
        guard context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error) else {
            let authError = mapLAError(error)
            return .failure(authError)
        }

        // Perform biometric authentication
        return await withCheckedContinuation { continuation in
            context.evaluatePolicy(
                .deviceOwnerAuthenticationWithBiometrics,
                localizedReason: reason
            ) { success, error in
                Task { @MainActor in
                    if success {
                        continuation.resume(returning: .success(()))
                    } else {
                        let authError = self.mapLAError(error as? NSError)
                        continuation.resume(returning: .failure(authError))
                    }
                }
            }
        }
    }

    // MARK: - Private Methods

    /// Maps LocalAuthentication errors to BiometricAuthError.
    ///
    /// - Parameter error: NSError from LocalAuthentication framework
    /// - Returns: BiometricAuthError
    private func mapLAError(_ error: NSError?) -> BiometricAuthError {
        guard let error = error else {
            return .unknown("Unknown error occurred")
        }

        let laError = LAError(_nsError: error)

        switch laError.code {
        case .biometryNotAvailable:
            return .notAvailable
        case .biometryNotEnrolled:
            return .notEnrolled
        case .userCancel:
            return .userCancelled
        case .authenticationFailed:
            return .authenticationFailed
        case .systemCancel:
            return .systemCancelled
        case .biometryLockout:
            return .biometryLockout
        case .userFallback:
            return .userFallback
        default:
            return .unknown(error.localizedDescription)
        }
    }

    /// Invalidates the availability cache.
    /// Call this if biometric enrollment status might have changed.
    func invalidateCache() {
        cachedAvailability = nil

        // Re-check availability
        Task {
            _ = await checkBiometricAvailability()
        }
    }
}
