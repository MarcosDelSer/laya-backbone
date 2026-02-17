//
//  LAYAAdminApp.swift
//  LAYAAdmin
//
//  LAYA Administration macOS Application
//  A native desktop experience for kindergarten/childcare facility administrators
//

import SwiftUI
import UserNotifications

/// Main entry point for the LAYA Admin macOS application.
/// Uses SwiftUI App lifecycle for modern macOS development.
///
/// Features:
/// - Main window with navigation and content
/// - Menu bar status item with quick actions
/// - Settings window for app preferences
/// - Custom keyboard shortcuts for navigation
@main
struct LAYAAdminApp: App {

    // MARK: - App State

    /// App delegate for macOS-specific functionality
    @NSApplicationDelegateAdaptor(AppDelegate.self) var appDelegate

    /// Notification service instance
    @StateObject private var notificationService = NotificationService.shared

    /// Auth service instance for menu bar
    @StateObject private var authService = AuthService.shared

    /// Whether to show the menu bar extra
    @AppStorage("showMenuBarExtra") private var showMenuBarExtra: Bool = true

    // MARK: - Body

    @SceneBuilder
    var body: some Scene {
        // Main application window
        WindowGroup {
            MainView()
                .environmentObject(notificationService)
                .environmentObject(authService)
        }
        .windowStyle(.automatic)
        .windowToolbarStyle(.unified)
        .commands {
            // Custom menu commands
            CommandGroup(replacing: .newItem) {
                Button("New Child") {
                    NotificationCenter.default.post(name: .newChild, object: nil)
                }
                .keyboardShortcut("n", modifiers: .command)

                Button("New Staff Member") {
                    NotificationCenter.default.post(name: .newStaff, object: nil)
                }
                .keyboardShortcut("n", modifiers: [.command, .shift])
            }

            CommandGroup(after: .sidebar) {
                Button("Show Dashboard") {
                    NotificationCenter.default.post(name: .showDashboard, object: nil)
                }
                .keyboardShortcut("1", modifiers: .command)

                Button("Show Children") {
                    NotificationCenter.default.post(name: .showChildren, object: nil)
                }
                .keyboardShortcut("2", modifiers: .command)

                Button("Show Staff") {
                    NotificationCenter.default.post(name: .showStaff, object: nil)
                }
                .keyboardShortcut("3", modifiers: .command)

                Button("Show Finance") {
                    NotificationCenter.default.post(name: .showFinance, object: nil)
                }
                .keyboardShortcut("4", modifiers: .command)
            }
        }

        // Menu bar extra with quick actions
        MenuBarExtra("LAYA Admin", systemImage: "building.2.fill") {
            MenuBarView(
                authService: authService,
                notificationService: notificationService
            )
        }
        .menuBarExtraStyle(.window)

        // Settings window
        Settings {
            SettingsView()
                .environmentObject(notificationService)
        }
    }
}

// Note: SettingsView is now defined in Views/Settings/SettingsView.swift
// It includes General, Server, Notifications, Sync, and Data settings tabs.

// MARK: - Notification Names

extension Notification.Name {
    static let newChild = Notification.Name("newChild")
    static let newStaff = Notification.Name("newStaff")
    static let showDashboard = Notification.Name("showDashboard")
    static let showChildren = Notification.Name("showChildren")
    static let showStaff = Notification.Name("showStaff")
    static let showFinance = Notification.Name("showFinance")
}
