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
@main
struct LAYAAdminApp: App {

    // MARK: - App State

    @NSApplicationDelegateAdaptor(AppDelegate.self) var appDelegate

    /// Notification service instance
    @StateObject private var notificationService = NotificationService.shared

    // MARK: - Body

    var body: some Scene {
        WindowGroup {
            MainView()
                .environmentObject(notificationService)
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
                .environmentObject(notificationService)
        }
        #endif
    }
}

// MARK: - App Delegate

/// AppDelegate for handling macOS-specific functionality
/// including notifications and menu bar integration.
class AppDelegate: NSObject, NSApplicationDelegate {

    /// Key for tracking first launch
    private let hasRequestedNotificationPermissionKey = "hasRequestedNotificationPermission"

    func applicationDidFinishLaunching(_ notification: Notification) {
        // Configure app on launch
        configureAppearance()

        // Setup notification service
        setupNotifications()

        // Register for notification action handlers
        registerNotificationHandlers()
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

    /// Sets up the notification service and requests permission if needed
    private func setupNotifications() {
        // Request notification permission on first launch
        let hasRequestedPermission = UserDefaults.standard.bool(forKey: hasRequestedNotificationPermissionKey)

        if !hasRequestedPermission {
            Task { @MainActor in
                do {
                    let granted = try await NotificationService.shared.requestAuthorization()
                    UserDefaults.standard.set(true, forKey: hasRequestedNotificationPermissionKey)

                    if granted {
                        // Notification permission granted on first launch
                    }
                } catch {
                    // Handle permission error silently
                    UserDefaults.standard.set(true, forKey: hasRequestedNotificationPermissionKey)
                }
            }
        }
    }

    /// Registers handlers for notification actions
    private func registerNotificationHandlers() {
        // Handle navigation to alert
        NotificationCenter.default.addObserver(
            forName: .navigateToAlert,
            object: nil,
            queue: .main
        ) { notification in
            guard let alertId = notification.userInfo?["alertId"] as? String else { return }
            // Post a notification to show the alert in the UI
            NotificationCenter.default.post(name: .showDashboard, object: nil)
            // Additional navigation logic would go here
            _ = alertId // Use alertId for navigation
        }

        // Handle alert acknowledgment
        NotificationCenter.default.addObserver(
            forName: .acknowledgeAlert,
            object: nil,
            queue: .main
        ) { notification in
            guard let alertId = notification.userInfo?["alertId"] as? String else { return }
            // Handle alert acknowledgment
            // This would typically call an API to mark the alert as acknowledged
            _ = alertId // Use alertId for acknowledgment
        }
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

    @EnvironmentObject private var notificationService: NotificationService

    /// State for showing permission alert
    @State private var showingPermissionAlert = false

    var body: some View {
        Form {
            // Authorization Status Section
            Section {
                HStack {
                    Text("System Permission")
                    Spacer()
                    authorizationStatusView
                }

                if notificationService.authorizationStatus == .denied {
                    Button("Open System Preferences") {
                        openSystemPreferences()
                    }
                    .foregroundColor(.accentColor)
                } else if notificationService.authorizationStatus == .notDetermined {
                    Button("Request Permission") {
                        requestPermission()
                    }
                    .foregroundColor(.accentColor)
                }
            } header: {
                Text("Notification Permission")
            }

            // Enable/Disable Toggle
            Section {
                Toggle("Enable Notifications", isOn: $notificationService.notificationsEnabled)
                    .disabled(notificationService.authorizationStatus != .authorized)
            } header: {
                Text("General")
            }

            // Category Preferences Section
            Section {
                ForEach(NotificationCategory.allCases, id: \.self) { category in
                    Toggle(category.displayName, isOn: categoryBinding(for: category))
                        .disabled(!notificationService.notificationsEnabled)
                }
            } header: {
                Text("Notification Categories")
            } footer: {
                Text("Choose which types of notifications you want to receive.")
                    .foregroundColor(.secondary)
            }
        }
        .padding()
        .alert("Permission Required", isPresented: $showingPermissionAlert) {
            Button("Open System Preferences") {
                openSystemPreferences()
            }
            Button("Cancel", role: .cancel) { }
        } message: {
            Text("Notification permission was denied. Please enable notifications in System Preferences.")
        }
    }

    // MARK: - Views

    @ViewBuilder
    private var authorizationStatusView: some View {
        switch notificationService.authorizationStatus {
        case .authorized:
            Label("Authorized", systemImage: "checkmark.circle.fill")
                .foregroundColor(.green)
        case .denied:
            Label("Denied", systemImage: "xmark.circle.fill")
                .foregroundColor(.red)
        case .notDetermined:
            Label("Not Set", systemImage: "questionmark.circle.fill")
                .foregroundColor(.orange)
        case .provisional:
            Label("Provisional", systemImage: "checkmark.circle")
                .foregroundColor(.yellow)
        case .ephemeral:
            Label("Ephemeral", systemImage: "clock.circle.fill")
                .foregroundColor(.blue)
        @unknown default:
            Label("Unknown", systemImage: "questionmark.circle")
                .foregroundColor(.secondary)
        }
    }

    // MARK: - Actions

    private func requestPermission() {
        Task {
            do {
                let granted = try await notificationService.requestAuthorization()
                if !granted {
                    showingPermissionAlert = true
                }
            } catch {
                showingPermissionAlert = true
            }
        }
    }

    private func openSystemPreferences() {
        if let url = URL(string: "x-apple.systempreferences:com.apple.preference.notifications") {
            NSWorkspace.shared.open(url)
        }
    }

    private func categoryBinding(for category: NotificationCategory) -> Binding<Bool> {
        Binding(
            get: { notificationService.isCategoryEnabled(category) },
            set: { notificationService.setCategoryEnabled(category, enabled: $0) }
        )
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
