//
//  MainView.swift
//  LAYAAdmin
//
//  Main application view that handles authentication state routing.
//  Shows LoginView when unauthenticated, or the main navigation when authenticated.
//

import SwiftUI
import Combine

// MARK: - Main View

/// Main application view that manages authentication-based navigation.
///
/// This view acts as the root container for the application, observing
/// authentication state and displaying the appropriate content:
/// - `LoginView` when the user is not authenticated
/// - Main navigation with `NavigationSplitView` when authenticated
///
/// Features:
/// - Session restoration on app launch
/// - Smooth transitions between auth states
/// - Navigation sidebar with all app sections
/// - User profile display in sidebar header
struct MainView: View {

    // MARK: - Properties

    /// The shared authentication view model
    @StateObject private var authViewModel = AuthViewModel()

    /// Current selected navigation section
    @State private var selectedSection: NavigationSection? = .dashboard

    /// Navigation column visibility
    @State private var columnVisibility: NavigationSplitViewVisibility = .all

    /// Whether session restoration is in progress
    @State private var isRestoringSession = true

    // MARK: - Body

    var body: some View {
        Group {
            if isRestoringSession {
                // Show loading while restoring session
                sessionRestorationView
            } else if authViewModel.isAuthenticated {
                // Show main navigation when authenticated
                authenticatedView
            } else {
                // Show login when not authenticated
                LoginView()
            }
        }
        .animation(.easeInOut(duration: 0.3), value: authViewModel.isAuthenticated)
        .animation(.easeInOut(duration: 0.2), value: isRestoringSession)
        .task {
            await restoreSession()
        }
        .environmentObject(authViewModel)
    }

    // MARK: - Session Restoration View

    /// View shown while attempting to restore a previous session
    private var sessionRestorationView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text("Restoring session...")
                .font(.headline)
                .foregroundColor(.secondary)
        }
        .frame(minWidth: 400, minHeight: 300)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }

    // MARK: - Authenticated View

    /// Main navigation view shown when the user is authenticated
    private var authenticatedView: some View {
        NavigationSplitView(columnVisibility: $columnVisibility) {
            // Sidebar with navigation sections
            sidebarContent
        } detail: {
            // Detail content based on selection
            DetailView(section: selectedSection)
        }
        .navigationSplitViewStyle(.balanced)
        .frame(minWidth: 900, minHeight: 600)
        .onReceive(NotificationCenter.default.publisher(for: .showDashboard)) { _ in
            selectedSection = .dashboard
        }
        .onReceive(NotificationCenter.default.publisher(for: .showChildren)) { _ in
            selectedSection = .children
        }
        .onReceive(NotificationCenter.default.publisher(for: .showStaff)) { _ in
            selectedSection = .staff
        }
        .onReceive(NotificationCenter.default.publisher(for: .showFinance)) { _ in
            selectedSection = .finance
        }
        .onReceive(NotificationCenter.default.publisher(for: .newChild)) { _ in
            selectedSection = .children
            // TODO: Open new child form when child management is implemented
        }
        .onReceive(NotificationCenter.default.publisher(for: .newStaff)) { _ in
            selectedSection = .staff
            // TODO: Open new staff form when staff management is implemented
        }
        .onReceive(NotificationCenter.default.publisher(for: .showAnalytics)) { _ in
            selectedSection = .analytics
        }
        .onReceive(NotificationCenter.default.publisher(for: .newInvoice)) { _ in
            selectedSection = .finance
            // TODO: Open new invoice form when finance is implemented
        }
    }

    // MARK: - Sidebar Content

    /// Sidebar navigation content with user profile header
    private var sidebarContent: some View {
        List(selection: $selectedSection) {
            // User profile header
            userProfileHeader
                .listRowSeparator(.hidden)
                .listRowInsets(EdgeInsets(top: 12, leading: 12, bottom: 12, trailing: 12))

            // Main navigation
            Section("Main") {
                ForEach([NavigationSection.dashboard]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }

            // Management sections
            Section("Management") {
                ForEach([NavigationSection.children, .staff]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }

            // Finance & Analytics
            Section("Finance & Analytics") {
                ForEach([NavigationSection.finance, .analytics]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }
        }
        .listStyle(.sidebar)
        .frame(minWidth: 220)
        .toolbar {
            ToolbarItemGroup {
                Button(action: toggleSidebar) {
                    Image(systemName: "sidebar.left")
                        .help("Toggle Sidebar")
                }

                Spacer()

                Button(action: { performLogout() }) {
                    Image(systemName: "rectangle.portrait.and.arrow.right")
                        .help("Sign Out")
                }
            }
        }
    }

    // MARK: - User Profile Header

    /// Header showing current user info
    private var userProfileHeader: some View {
        HStack(spacing: 12) {
            // User avatar
            Circle()
                .fill(Color.accentColor.opacity(0.2))
                .frame(width: 40, height: 40)
                .overlay {
                    Text(userInitials)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.accentColor)
                }

            VStack(alignment: .leading, spacing: 2) {
                Text(userName)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Text(userRole)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(1)
            }

            Spacer()
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 4)
        .background(
            RoundedRectangle(cornerRadius: 8)
                .fill(Color(NSColor.controlBackgroundColor))
        )
    }

    // MARK: - Computed Properties

    /// User's display name
    private var userName: String {
        if let user = authViewModel.currentUser {
            return "\(user.firstName) \(user.lastName)"
        }
        return "User"
    }

    /// User's initials for avatar
    private var userInitials: String {
        if let user = authViewModel.currentUser {
            let firstInitial = user.firstName.prefix(1).uppercased()
            let lastInitial = user.lastName.prefix(1).uppercased()
            return "\(firstInitial)\(lastInitial)"
        }
        return "U"
    }

    /// User's role display string
    private var userRole: String {
        if let user = authViewModel.currentUser {
            return user.role.rawValue.capitalized
        }
        return "Administrator"
    }

    // MARK: - Actions

    /// Restores a previous session on app launch
    private func restoreSession() async {
        // Attempt to restore session
        _ = await authViewModel.restoreSession()

        // Brief delay for smoother transition
        try? await Task.sleep(nanoseconds: 300_000_000)

        // Mark restoration complete
        isRestoringSession = false
    }

    /// Toggles the sidebar visibility
    private func toggleSidebar() {
        NSApp.keyWindow?.firstResponder?.tryToPerform(
            #selector(NSSplitViewController.toggleSidebar(_:)),
            with: nil
        )
    }

    /// Performs user logout
    private func performLogout() {
        Task {
            await authViewModel.logout()
        }
    }
}

// MARK: - Preview

#Preview("Main View - Authenticated") {
    MainView()
}

#Preview("Main View - Login") {
    LoginView()
}
