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

        // Menu bar extra with quick actions (macOS 14+)
        #if os(macOS)
        if showMenuBarExtra {
            MenuBarExtra {
                MenuBarView(
                    authService: authService,
                    notificationService: notificationService
                )
            } label: {
                MenuBarLabel()
            }
            .menuBarExtraStyle(.window)
        }

        // Settings window
        Settings {
            SettingsView()
                .environmentObject(notificationService)
        }
        #endif
    }
}

// MARK: - Menu Bar Label

/// Label view for the menu bar extra.
/// Shows an icon that indicates app status.
private struct MenuBarLabel: View {

    var body: some View {
        Image(systemName: "building.2.fill")
            .symbolRenderingMode(.hierarchical)
            .accessibilityLabel("LAYA Admin")
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
