//
//  LAYAAdminApp.swift
//  LAYAAdmin
//
//  LAYA Administration macOS Application
//  A native desktop experience for kindergarten/childcare facility administrators
//

import SwiftUI

/// Main entry point for the LAYA Admin macOS application.
/// Uses SwiftUI App lifecycle for modern macOS development.
@main
struct LAYAAdminApp: App {

    // MARK: - App State

    @NSApplicationDelegateAdaptor(AppDelegate.self) var appDelegate

    // MARK: - Body

    var body: some Scene {
        WindowGroup {
            MainView()
        }
        .windowStyle(.automatic)
        .windowToolbarStyle(.unified)
        .commands {
            // Custom menu commands will be added here
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

        #if os(macOS)
        Settings {
            SettingsView()
        }
        #endif
    }
}

// MARK: - App Delegate

/// AppDelegate for handling macOS-specific functionality
/// including notifications and menu bar integration.
class AppDelegate: NSObject, NSApplicationDelegate {

    func applicationDidFinishLaunching(_ notification: Notification) {
        // Configure app on launch
        configureAppearance()
    }

    func applicationWillTerminate(_ notification: Notification) {
        // Cleanup on termination
    }

    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool {
        // Keep app running in menu bar even if main window is closed
        return false
    }

    private func configureAppearance() {
        // Allow the app to follow system appearance (Dark/Light mode)
    }
}

// MARK: - Settings View

/// Placeholder settings view for app preferences
struct SettingsView: View {
    var body: some View {
        TabView {
            GeneralSettingsView()
                .tabItem {
                    Label("General", systemImage: "gear")
                }

            ServerSettingsView()
                .tabItem {
                    Label("Server", systemImage: "server.rack")
                }

            NotificationSettingsView()
                .tabItem {
                    Label("Notifications", systemImage: "bell")
                }
        }
        .frame(width: 450, height: 300)
    }
}

struct GeneralSettingsView: View {
    var body: some View {
        Form {
            Text("General settings will be configured here.")
                .foregroundColor(.secondary)
        }
        .padding()
    }
}

struct ServerSettingsView: View {
    var body: some View {
        Form {
            Text("Server connection settings will be configured here.")
                .foregroundColor(.secondary)
        }
        .padding()
    }
}

struct NotificationSettingsView: View {
    var body: some View {
        Form {
            Text("Notification preferences will be configured here.")
                .foregroundColor(.secondary)
        }
        .padding()
    }
}

// MARK: - Notification Names

extension Notification.Name {
    static let newChild = Notification.Name("newChild")
    static let newStaff = Notification.Name("newStaff")
    static let showDashboard = Notification.Name("showDashboard")
    static let showChildren = Notification.Name("showChildren")
    static let showStaff = Notification.Name("showStaff")
    static let showFinance = Notification.Name("showFinance")
}
