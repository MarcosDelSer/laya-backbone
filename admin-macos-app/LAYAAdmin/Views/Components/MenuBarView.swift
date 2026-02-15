//
//  MenuBarView.swift
//  LAYAAdmin
//
//  Menu bar status item view with quick actions.
//  Provides quick access to common actions and status information
//  from the macOS menu bar.
//

import SwiftUI

// MARK: - Menu Bar View

/// Menu bar view providing quick access to app features.
///
/// Features:
/// - Status summary (connection status, alert count)
/// - Quick navigation to app sections
/// - Quick actions (new child, new staff)
/// - Connection status indicator
/// - Open main window action
///
/// This view is used with SwiftUI's `MenuBarExtra` scene for
/// native menu bar integration on macOS 14+.
struct MenuBarView: View {

    // MARK: - Properties

    /// Auth service for authentication state
    @ObservedObject var authService: AuthService

    /// Notification service for alert count
    @ObservedObject var notificationService: NotificationService

    /// Whether the app is connected to the server
    @State private var isConnected: Bool = true

    /// Number of unread alerts
    @State private var alertCount: Int = 0

    /// Current sync status
    @State private var syncStatus: SyncStatus = .synced

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Header with status
            statusHeader
                .padding(.horizontal, 12)
                .padding(.top, 12)
                .padding(.bottom, 8)

            Divider()

            // Quick navigation section
            if authService.isAuthenticated {
                quickNavigationSection
                    .padding(.vertical, 4)

                Divider()

                // Quick actions section
                quickActionsSection
                    .padding(.vertical, 4)

                Divider()
            }

            // App actions section
            appActionsSection
                .padding(.vertical, 4)
        }
        .frame(width: 260)
        .onAppear {
            updateAlertCount()
        }
    }

    // MARK: - Status Header

    /// Header showing connection status and alerts
    private var statusHeader: some View {
        HStack(spacing: 12) {
            // App icon
            Image("AppIconSmall")
                .resizable()
                .frame(width: 32, height: 32)
                .cornerRadius(6)
                .overlay(
                    RoundedRectangle(cornerRadius: 6)
                        .stroke(Color.gray.opacity(0.2), lineWidth: 0.5)
                )

            VStack(alignment: .leading, spacing: 2) {
                Text("LAYA Admin")
                    .font(.headline)
                    .fontWeight(.semibold)

                HStack(spacing: 6) {
                    // Connection status
                    connectionStatusBadge

                    if authService.isAuthenticated {
                        // Alert count badge
                        if alertCount > 0 {
                            alertCountBadge
                        }
                    }
                }
            }

            Spacer()

            // Sync indicator
            if authService.isAuthenticated {
                syncStatusIndicator
            }
        }
    }

    /// Connection status badge
    private var connectionStatusBadge: some View {
        HStack(spacing: 4) {
            Circle()
                .fill(isConnected ? Color.green : Color.red)
                .frame(width: 6, height: 6)

            Text(isConnected ? "Connected" : "Offline")
                .font(.caption2)
                .foregroundColor(.secondary)
        }
    }

    /// Alert count badge
    private var alertCountBadge: some View {
        HStack(spacing: 3) {
            Image(systemName: "bell.fill")
                .font(.caption2)

            Text("\(alertCount)")
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 6)
        .padding(.vertical, 2)
        .background(Color.orange.opacity(0.2))
        .foregroundColor(.orange)
        .cornerRadius(4)
    }

    /// Sync status indicator
    private var syncStatusIndicator: some View {
        Group {
            switch syncStatus {
            case .syncing:
                ProgressView()
                    .controlSize(.small)
                    .help("Syncing...")
            case .synced:
                Image(systemName: "checkmark.circle.fill")
                    .foregroundColor(.green)
                    .font(.caption)
                    .help("Up to date")
            case .pending:
                Image(systemName: "arrow.triangle.2.circlepath")
                    .foregroundColor(.orange)
                    .font(.caption)
                    .help("Pending changes")
            case .error:
                Image(systemName: "exclamationmark.triangle.fill")
                    .foregroundColor(.red)
                    .font(.caption)
                    .help("Sync error")
            }
        }
    }

    // MARK: - Quick Navigation Section

    /// Navigation shortcuts to main app sections
    private var quickNavigationSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Navigation")
                .font(.caption)
                .foregroundColor(.secondary)
                .padding(.horizontal, 12)
                .padding(.bottom, 4)

            MenuBarButton(
                title: "Dashboard",
                icon: "chart.bar.fill",
                shortcut: "1"
            ) {
                navigateTo(.dashboard)
            }

            MenuBarButton(
                title: "Children",
                icon: "person.2.fill",
                shortcut: "2"
            ) {
                navigateTo(.children)
            }

            MenuBarButton(
                title: "Staff",
                icon: "person.badge.key.fill",
                shortcut: "3"
            ) {
                navigateTo(.staff)
            }

            MenuBarButton(
                title: "Finance",
                icon: "dollarsign.circle.fill",
                shortcut: "4"
            ) {
                navigateTo(.finance)
            }
        }
    }

    // MARK: - Quick Actions Section

    /// Quick action buttons
    private var quickActionsSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Quick Actions")
                .font(.caption)
                .foregroundColor(.secondary)
                .padding(.horizontal, 12)
                .padding(.bottom, 4)

            MenuBarButton(
                title: "New Child",
                icon: "person.badge.plus",
                shortcut: "N"
            ) {
                performQuickAction(.newChild)
            }

            MenuBarButton(
                title: "New Staff Member",
                icon: "person.fill.badge.plus",
                shortcut: nil
            ) {
                performQuickAction(.newStaff)
            }

            if alertCount > 0 {
                MenuBarButton(
                    title: "View Alerts (\(alertCount))",
                    icon: "bell.badge.fill",
                    iconColor: .orange,
                    shortcut: nil
                ) {
                    performQuickAction(.viewAlerts)
                }
            }
        }
    }

    // MARK: - App Actions Section

    /// Application-level actions
    private var appActionsSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            MenuBarButton(
                title: "Open LAYA Admin",
                icon: "macwindow",
                shortcut: nil
            ) {
                openMainWindow()
            }

            if authService.isAuthenticated {
                MenuBarButton(
                    title: "Settings",
                    icon: "gear",
                    shortcut: ","
                ) {
                    openSettings()
                }

                MenuBarButton(
                    title: "Sign Out",
                    icon: "rectangle.portrait.and.arrow.right",
                    shortcut: nil
                ) {
                    performSignOut()
                }
            }

            Divider()
                .padding(.vertical, 4)

            MenuBarButton(
                title: "Quit LAYA Admin",
                icon: "power",
                shortcut: "Q"
            ) {
                quitApp()
            }
        }
    }

    // MARK: - Actions

    /// Navigates to the specified section
    private func navigateTo(_ section: NavigationSection) {
        openMainWindow()
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
            NotificationCenter.default.post(name: section.notificationName, object: nil)
        }
    }

    /// Performs a quick action
    private func performQuickAction(_ action: QuickAction) {
        openMainWindow()
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
            NotificationCenter.default.post(name: action.notificationName, object: nil)
        }
    }

    /// Opens the main application window
    private func openMainWindow() {
        NSApp.activate(ignoringOtherApps: true)

        // Find or create main window
        if let mainWindow = NSApp.windows.first(where: { $0.identifier?.rawValue == "main-window" }) {
            mainWindow.makeKeyAndOrderFront(nil)
        } else if let window = NSApp.windows.first(where: { !($0 is NSPanel) }) {
            window.makeKeyAndOrderFront(nil)
        } else {
            // No existing window, trigger new window creation
            NSApp.setActivationPolicy(.regular)
            if #available(macOS 14.0, *) {
                NSApp.activate()
            } else {
                NSApp.activate(ignoringOtherApps: true)
            }
        }
    }

    /// Opens the settings window
    private func openSettings() {
        NSApp.activate(ignoringOtherApps: true)
        // Use settings URL scheme for macOS 14+
        if #available(macOS 14, *) {
            NSApp.sendAction(Selector(("showSettingsWindow:")), to: nil, from: nil)
        } else {
            NSApp.sendAction(Selector(("showPreferencesWindow:")), to: nil, from: nil)
        }
    }

    /// Signs out the current user
    private func performSignOut() {
        Task {
            await authService.logout()
        }
    }

    /// Quits the application
    private func quitApp() {
        NSApplication.shared.terminate(nil)
    }

    /// Updates the alert count from dashboard data
    private func updateAlertCount() {
        // In a real implementation, this would fetch from a shared state or service
        // For now, we'll use a placeholder
        alertCount = 0
    }
}

// MARK: - Menu Bar Button

/// A styled button for use in the menu bar dropdown.
struct MenuBarButton: View {

    // MARK: - Properties

    let title: String
    let icon: String
    var iconColor: Color = .primary
    let shortcut: String?
    let action: () -> Void

    // MARK: - State

    @State private var isHovered = false

    // MARK: - Body

    var body: some View {
        Button(action: action) {
            HStack(spacing: 10) {
                Image(systemName: icon)
                    .font(.system(size: 14))
                    .foregroundColor(iconColor)
                    .frame(width: 20)

                Text(title)
                    .font(.system(size: 13))

                Spacer()

                if let shortcut = shortcut {
                    Text("\u{2318}\(shortcut)")
                        .font(.system(size: 11))
                        .foregroundColor(.secondary)
                }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 6)
            .background(isHovered ? Color.accentColor.opacity(0.1) : Color.clear)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .onHover { hovering in
            isHovered = hovering
        }
    }
}

// MARK: - Supporting Types

/// Sync status for menu bar display
enum SyncStatus {
    case synced
    case syncing
    case pending
    case error
}

/// Quick actions available from the menu bar
enum QuickAction {
    case newChild
    case newStaff
    case viewAlerts

    var notificationName: Notification.Name {
        switch self {
        case .newChild:
            return .newChild
        case .newStaff:
            return .newStaff
        case .viewAlerts:
            return .showDashboard
        }
    }
}

// MARK: - Navigation Section Extension

extension NavigationSection {

    /// Notification name for menu bar navigation
    var notificationName: Notification.Name {
        switch self {
        case .dashboard:
            return .showDashboard
        case .children:
            return .showChildren
        case .staff:
            return .showStaff
        case .finance:
            return .showFinance
        case .analytics:
            return .showDashboard // Analytics shows in dashboard
        }
    }
}

// MARK: - Menu Bar Status Icon

/// Creates a status icon for the menu bar.
/// Shows different states based on connection and alert status.
struct MenuBarStatusIcon: View {

    // MARK: - Properties

    let isConnected: Bool
    let hasAlerts: Bool

    // MARK: - Body

    var body: some View {
        Image(systemName: iconName)
            .symbolRenderingMode(.palette)
            .foregroundStyle(primaryColor, secondaryColor)
    }

    private var iconName: String {
        if !isConnected {
            return "exclamationmark.icloud.fill"
        } else if hasAlerts {
            return "bell.badge.fill"
        } else {
            return "building.2.fill"
        }
    }

    private var primaryColor: Color {
        if !isConnected {
            return .red
        } else if hasAlerts {
            return .orange
        } else {
            return .primary
        }
    }

    private var secondaryColor: Color {
        if !isConnected {
            return .red.opacity(0.7)
        } else if hasAlerts {
            return .red
        } else {
            return .accentColor
        }
    }
}

// MARK: - Preview

#Preview("Menu Bar View - Authenticated") {
    MenuBarView(
        authService: .previewAuthenticated,
        notificationService: .previewAuthorized
    )
}

#Preview("Menu Bar View - Unauthenticated") {
    MenuBarView(
        authService: .previewUnauthenticated,
        notificationService: .previewNotDetermined
    )
}

#Preview("Menu Bar Button") {
    VStack(spacing: 0) {
        MenuBarButton(
            title: "Dashboard",
            icon: "chart.bar.fill",
            shortcut: "1"
        ) {}

        MenuBarButton(
            title: "New Child",
            icon: "person.badge.plus",
            shortcut: "N"
        ) {}

        MenuBarButton(
            title: "View Alerts",
            icon: "bell.badge.fill",
            iconColor: .orange,
            shortcut: nil
        ) {}
    }
    .frame(width: 260)
}

#Preview("Menu Bar Status Icon") {
    HStack(spacing: 20) {
        MenuBarStatusIcon(isConnected: true, hasAlerts: false)
        MenuBarStatusIcon(isConnected: true, hasAlerts: true)
        MenuBarStatusIcon(isConnected: false, hasAlerts: false)
    }
    .padding()
}
