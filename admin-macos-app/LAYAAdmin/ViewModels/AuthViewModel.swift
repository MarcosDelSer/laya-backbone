//
//  AuthViewModel.swift
//  LAYAAdmin
//
//  ViewModel for authentication state management in the LAYA Admin application.
//  Handles login, logout, session restoration, and form validation.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Auth ViewModel

/// ViewModel for managing authentication state and login flow.
///
/// This ViewModel acts as a bridge between the UI layer and the AuthService,
/// providing observable state for login forms and authentication status.
///
/// Features:
/// - Form validation for email and password
/// - Login state management with loading indicators
/// - Error handling with user-friendly messages
/// - Session restoration on app launch
/// - Password reset flow support
@MainActor
final class AuthViewModel: ObservableObject {

    // MARK: - Published Properties

    /// Current authentication state from the service
    @Published private(set) var authState: AuthState = .unauthenticated

    /// Whether a login operation is in progress
    @Published private(set) var isLoading = false

    /// Current error, if any
    @Published private(set) var error: AuthError?

    /// Whether the error alert should be shown
    @Published var showError = false

    // MARK: - Form Fields

    /// Email input from the login form
    @Published var email = ""

    /// Password input from the login form
    @Published var password = ""

    /// Whether to remember the user for extended sessions
    @Published var rememberMe = false

    // MARK: - Validation State

    /// Whether the email field has been touched (for validation display)
    @Published var emailTouched = false

    /// Whether the password field has been touched (for validation display)
    @Published var passwordTouched = false

    // MARK: - Password Reset

    /// Email for password reset (may differ from login email)
    @Published var resetEmail = ""

    /// Whether password reset sheet is showing
    @Published var showPasswordReset = false

    /// Whether password reset is in progress
    @Published private(set) var isResettingPassword = false

    /// Success message after password reset
    @Published var resetSuccessMessage: String?

    // MARK: - Computed Properties

    /// Whether the user is currently authenticated
    var isAuthenticated: Bool {
        authState.isAuthenticated
    }

    /// The current authenticated user, if any
    var currentUser: User? {
        authState.currentUser
    }

    /// Whether the form is valid and can be submitted
    var isFormValid: Bool {
        isEmailValid && isPasswordValid
    }

    /// Whether the email is valid
    var isEmailValid: Bool {
        email.isValidEmail
    }

    /// Whether the password is valid (non-empty)
    var isPasswordValid: Bool {
        !password.isEmpty && password.count >= 6
    }

    /// Email validation error message, if any
    var emailError: String? {
        guard emailTouched else { return nil }
        if email.isEmpty {
            return String(localized: "Email is required")
        }
        if !email.isValidEmail {
            return String(localized: "Please enter a valid email address")
        }
        return nil
    }

    /// Password validation error message, if any
    var passwordError: String? {
        guard passwordTouched else { return nil }
        if password.isEmpty {
            return String(localized: "Password is required")
        }
        if password.count < 6 {
            return String(localized: "Password must be at least 6 characters")
        }
        return nil
    }

    /// Whether the login button should be enabled
    var canLogin: Bool {
        isFormValid && !isLoading
    }

    // MARK: - Private Properties

    /// The authentication service
    private let authService: AuthServiceProtocol

    /// Combine cancellables for subscriptions
    private var cancellables = Set<AnyCancellable>()

    // MARK: - Initialization

    /// Creates a new AuthViewModel
    /// - Parameter authService: The authentication service to use (defaults to shared instance)
    init(authService: AuthServiceProtocol? = nil) {
        self.authService = authService ?? AuthService.shared

        // Subscribe to auth state changes from the service
        setupAuthStateSubscription()
    }

    // MARK: - Public Methods

    /// Attempts to log in with the current form values
    func login() async {
        guard isFormValid else {
            // Mark all fields as touched to show validation errors
            emailTouched = true
            passwordTouched = true
            return
        }

        isLoading = true
        error = nil
        showError = false

        do {
            _ = try await authService.login(
                email: email.trimmingCharacters(in: .whitespaces),
                password: password,
                rememberMe: rememberMe
            )

            // Clear form on successful login
            clearForm()

        } catch let authError as AuthError {
            self.error = authError
            self.showError = true
        } catch {
            self.error = .unknown(error.localizedDescription)
            self.showError = true
        }

        isLoading = false
    }

    /// Logs out the current user
    func logout() async {
        isLoading = true

        await authService.logout()
        clearForm()

        isLoading = false
    }

    /// Attempts to restore a previous session on app launch
    /// - Returns: Whether a session was successfully restored
    @discardableResult
    func restoreSession() async -> Bool {
        isLoading = true
        let restored = await authService.restoreSession()
        isLoading = false
        return restored
    }

    /// Refreshes the authentication token
    func refreshToken() async throws {
        try await authService.refreshToken()
    }

    /// Requests a password reset for the given email
    func requestPasswordReset() async {
        guard !resetEmail.isEmpty, resetEmail.isValidEmail else {
            error = .invalidCredentials
            showError = true
            return
        }

        isResettingPassword = true
        error = nil
        resetSuccessMessage = nil

        do {
            if let authService = authService as? AuthService {
                let message = try await authService.requestPasswordReset(email: resetEmail)
                resetSuccessMessage = message
                // Clear reset form but keep success message visible
                resetEmail = ""
            }
        } catch let authError as AuthError {
            self.error = authError
            self.showError = true
        } catch {
            self.error = .unknown(error.localizedDescription)
            self.showError = true
        }

        isResettingPassword = false
    }

    /// Validates the email field
    func validateEmail() {
        emailTouched = true
    }

    /// Validates the password field
    func validatePassword() {
        passwordTouched = true
    }

    /// Clears the login form
    func clearForm() {
        email = ""
        password = ""
        rememberMe = false
        emailTouched = false
        passwordTouched = false
    }

    /// Clears the current error
    func clearError() {
        error = nil
        showError = false
    }

    /// Dismisses the password reset sheet
    func dismissPasswordReset() {
        showPasswordReset = false
        resetEmail = ""
        resetSuccessMessage = nil
    }

    // MARK: - Private Methods

    /// Sets up subscription to auth state changes from the service
    private func setupAuthStateSubscription() {
        authService.authStatePublisher
            .receive(on: DispatchQueue.main)
            .sink { [weak self] state in
                self?.authState = state

                // Extract error from failed state
                if case .failed(let authError) = state {
                    self?.error = authError
                    self?.showError = true
                }
            }
            .store(in: &cancellables)
    }
}

// MARK: - Preview Support

#if DEBUG
extension AuthViewModel {

    /// Creates a mock authenticated ViewModel for previews
    static var previewAuthenticated: AuthViewModel {
        let viewModel = AuthViewModel()
        viewModel.authState = .authenticated(.preview)
        return viewModel
    }

    /// Creates a mock unauthenticated ViewModel for previews
    static var previewUnauthenticated: AuthViewModel {
        let viewModel = AuthViewModel()
        viewModel.authState = .unauthenticated
        return viewModel
    }

    /// Creates a mock loading ViewModel for previews
    static var previewLoading: AuthViewModel {
        let viewModel = AuthViewModel()
        viewModel.authState = .authenticating
        viewModel.isLoading = true
        viewModel.email = "user@example.com"
        return viewModel
    }

    /// Creates a mock error ViewModel for previews
    static var previewError: AuthViewModel {
        let viewModel = AuthViewModel()
        viewModel.error = .invalidCredentials
        viewModel.showError = true
        return viewModel
    }

    /// Creates a ViewModel with pre-filled form for previews
    static var previewWithForm: AuthViewModel {
        let viewModel = AuthViewModel()
        viewModel.email = "director@laya.ca"
        viewModel.password = "password123"
        viewModel.rememberMe = true
        return viewModel
    }
}
#endif

// MARK: - Notification Extension

extension Notification.Name {

    /// Posted when authentication state changes
    static let authStateChanged = Notification.Name("authStateChanged")
}
