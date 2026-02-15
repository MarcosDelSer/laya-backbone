//
//  LoginView.swift
//  LAYAAdmin
//
//  Login view with credentials form for authenticating with the LAYA Admin application.
//  Provides email and password fields, remember me option, and password reset flow.
//

import SwiftUI

// MARK: - Login View

/// Login view for user authentication.
///
/// Features:
/// - Email and password input fields with validation
/// - Remember me checkbox for extended sessions
/// - Loading state during authentication
/// - Error display with recovery suggestions
/// - Password reset flow
/// - Keyboard navigation support
struct LoginView: View {

    // MARK: - Properties

    /// The authentication view model
    @StateObject private var viewModel = AuthViewModel()

    /// Focus state for keyboard navigation
    @FocusState private var focusedField: Field?

    // MARK: - Field Enum

    /// Enum for tracking focused field
    private enum Field: Hashable {
        case email
        case password
    }

    // MARK: - Body

    var body: some View {
        VStack(spacing: 0) {
            Spacer()

            // Logo and branding
            brandingSection

            Spacer()
                .frame(height: 40)

            // Login form
            loginForm

            Spacer()

            // Footer
            footerSection
        }
        .frame(minWidth: 400, idealWidth: 450, maxWidth: 500)
        .frame(minHeight: 600, idealHeight: 650, maxHeight: 700)
        .background(Color(NSColor.windowBackgroundColor))
        .alert(
            String(localized: "Authentication Error"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "OK")) {
                viewModel.clearError()
            }
        } message: { error in
            VStack {
                Text(error.localizedDescription)
                if !error.recoverySuggestion.isEmpty {
                    Text(error.recoverySuggestion)
                        .font(.caption)
                }
            }
        }
        .sheet(isPresented: $viewModel.showPasswordReset) {
            PasswordResetSheet(viewModel: viewModel)
        }
        .onSubmit {
            handleSubmit()
        }
    }

    // MARK: - Branding Section

    private var brandingSection: some View {
        VStack(spacing: 16) {
            // App icon/logo
            Image(systemName: "building.2.fill")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)
                .symbolRenderingMode(.hierarchical)

            VStack(spacing: 8) {
                Text("LAYA Admin")
                    .font(.largeTitle)
                    .fontWeight(.bold)

                Text("Sign in to manage your facility")
                    .font(.subheadline)
                    .foregroundColor(.secondary)
            }
        }
    }

    // MARK: - Login Form

    private var loginForm: some View {
        VStack(spacing: 24) {
            // Form fields
            VStack(spacing: 16) {
                // Email field
                VStack(alignment: .leading, spacing: 6) {
                    Text("Email")
                        .font(.subheadline)
                        .fontWeight(.medium)
                        .foregroundColor(.secondary)

                    TextField(String(localized: "Enter your email"), text: $viewModel.email)
                        .textFieldStyle(.roundedBorder)
                        .textContentType(.emailAddress)
                        .autocorrectionDisabled()
                        .focused($focusedField, equals: .email)
                        .onSubmit {
                            focusedField = .password
                        }
                        .onChange(of: viewModel.email) { _, _ in
                            if viewModel.emailTouched {
                                viewModel.validateEmail()
                            }
                        }

                    if let error = viewModel.emailError {
                        Text(error)
                            .font(.caption)
                            .foregroundColor(.red)
                    }
                }

                // Password field
                VStack(alignment: .leading, spacing: 6) {
                    Text("Password")
                        .font(.subheadline)
                        .fontWeight(.medium)
                        .foregroundColor(.secondary)

                    SecureField(String(localized: "Enter your password"), text: $viewModel.password)
                        .textFieldStyle(.roundedBorder)
                        .textContentType(.password)
                        .focused($focusedField, equals: .password)
                        .onSubmit {
                            handleSubmit()
                        }
                        .onChange(of: viewModel.password) { _, _ in
                            if viewModel.passwordTouched {
                                viewModel.validatePassword()
                            }
                        }

                    if let error = viewModel.passwordError {
                        Text(error)
                            .font(.caption)
                            .foregroundColor(.red)
                    }
                }
            }

            // Remember me and forgot password row
            HStack {
                Toggle(isOn: $viewModel.rememberMe) {
                    Text("Remember me")
                        .font(.subheadline)
                }
                .toggleStyle(.checkbox)

                Spacer()

                Button(action: {
                    viewModel.resetEmail = viewModel.email
                    viewModel.showPasswordReset = true
                }) {
                    Text("Forgot Password?")
                        .font(.subheadline)
                }
                .buttonStyle(.link)
            }

            // Login button
            Button(action: {
                performLogin()
            }) {
                HStack(spacing: 8) {
                    if viewModel.isLoading {
                        ProgressView()
                            .progressViewStyle(.circular)
                            .controlSize(.small)
                    }

                    Text(viewModel.isLoading ? "Signing in..." : "Sign In")
                        .fontWeight(.semibold)
                }
                .frame(maxWidth: .infinity)
                .frame(height: 38)
            }
            .buttonStyle(.borderedProminent)
            .controlSize(.large)
            .disabled(!viewModel.canLogin)
            .keyboardShortcut(.return, modifiers: [])
        }
        .padding(.horizontal, 40)
    }

    // MARK: - Footer Section

    private var footerSection: some View {
        VStack(spacing: 8) {
            Text("LAYA Admin \(Bundle.main.appVersion)")
                .font(.caption)
                .foregroundColor(.secondary)

            Text("Quebec Childcare Management System")
                .font(.caption2)
                .foregroundColor(.secondary.opacity(0.7))
        }
        .padding(.bottom, 20)
    }

    // MARK: - Actions

    private func performLogin() {
        // Mark fields as touched for validation
        viewModel.validateEmail()
        viewModel.validatePassword()

        guard viewModel.canLogin else { return }

        Task {
            await viewModel.login()
        }
    }

    private func handleSubmit() {
        switch focusedField {
        case .email:
            focusedField = .password
        case .password:
            performLogin()
        case nil:
            performLogin()
        }
    }
}

// MARK: - Password Reset Sheet

/// Sheet for password reset flow.
struct PasswordResetSheet: View {

    // MARK: - Properties

    @ObservedObject var viewModel: AuthViewModel
    @Environment(\.dismiss) private var dismiss
    @FocusState private var isEmailFocused: Bool

    // MARK: - Body

    var body: some View {
        VStack(spacing: 24) {
            // Header
            VStack(spacing: 8) {
                Image(systemName: "key.fill")
                    .font(.system(size: 40))
                    .foregroundColor(.accentColor)
                    .symbolRenderingMode(.hierarchical)

                Text("Reset Password")
                    .font(.title2)
                    .fontWeight(.bold)

                Text("Enter your email address and we'll send you a link to reset your password.")
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
            }

            // Success message
            if let message = viewModel.resetSuccessMessage {
                HStack(spacing: 8) {
                    Image(systemName: "checkmark.circle.fill")
                        .foregroundColor(.green)

                    Text(message)
                        .font(.subheadline)
                }
                .padding()
                .frame(maxWidth: .infinity)
                .background(Color.green.opacity(0.1))
                .cornerRadius(8)
            }

            // Email field
            if viewModel.resetSuccessMessage == nil {
                VStack(alignment: .leading, spacing: 6) {
                    Text("Email Address")
                        .font(.subheadline)
                        .fontWeight(.medium)
                        .foregroundColor(.secondary)

                    TextField(String(localized: "Enter your email"), text: $viewModel.resetEmail)
                        .textFieldStyle(.roundedBorder)
                        .textContentType(.emailAddress)
                        .autocorrectionDisabled()
                        .focused($isEmailFocused)
                        .onSubmit {
                            performReset()
                        }
                }
            }

            // Buttons
            HStack(spacing: 12) {
                Button(action: {
                    viewModel.dismissPasswordReset()
                    dismiss()
                }) {
                    Text(viewModel.resetSuccessMessage != nil ? "Done" : "Cancel")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .controlSize(.large)

                if viewModel.resetSuccessMessage == nil {
                    Button(action: {
                        performReset()
                    }) {
                        HStack(spacing: 8) {
                            if viewModel.isResettingPassword {
                                ProgressView()
                                    .progressViewStyle(.circular)
                                    .controlSize(.small)
                            }

                            Text(viewModel.isResettingPassword ? "Sending..." : "Send Reset Link")
                        }
                        .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(.borderedProminent)
                    .controlSize(.large)
                    .disabled(viewModel.resetEmail.isEmpty || !viewModel.resetEmail.isValidEmail || viewModel.isResettingPassword)
                }
            }
        }
        .padding(24)
        .frame(width: 400)
        .onAppear {
            isEmailFocused = true
        }
    }

    // MARK: - Actions

    private func performReset() {
        Task {
            await viewModel.requestPasswordReset()
        }
    }
}

// MARK: - Preview

#Preview("Login View") {
    LoginView()
}

#Preview("Login View - Loading") {
    let view = LoginView()
    return view
}

#Preview("Password Reset Sheet") {
    PasswordResetSheet(viewModel: .previewUnauthenticated)
}
