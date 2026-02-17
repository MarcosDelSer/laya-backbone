//
//  SettingsView.swift
//  LAYAAdmin
//
//  Settings view for configuring application preferences.
//  Provides tabs for General, Server, Notifications, Sync, and Data settings.
//

import SwiftUI
import UserNotifications

// MARK: - Settings View

/// Main settings view for configuring application preferences.
///
/// This view provides a tabbed interface for managing:
/// - General settings (language, appearance, startup options)
/// - Server settings (API URLs, connection testing)
/// - Notification preferences (categories, permissions)
/// - Sync settings (auto-sync, intervals)
/// - Data management (cache, reset)
struct SettingsView: View {

    // MARK: - Properties

    /// The settings view model
    @StateObject private var viewModel = SettingsViewModel()

    /// Environment notification service
    @EnvironmentObject private var notificationService: NotificationService

    // MARK: - Body

    var body: some View {
        TabView {
            GeneralSettingsTab(viewModel: viewModel)
                .tabItem {
                    Label("General", systemImage: "gear")
                }

            ServerSettingsTab(viewModel: viewModel)
                .tabItem {
                    Label("Server", systemImage: "server.rack")
                }

            NotificationSettingsTab(viewModel: viewModel)
                .tabItem {
                    Label("Notifications", systemImage: "bell")
                }

            SyncSettingsTab(viewModel: viewModel)
                .tabItem {
                    Label("Sync", systemImage: "arrow.triangle.2.circlepath")
                }

            DataSettingsTab(viewModel: viewModel)
                .tabItem {
                    Label("Data", systemImage: "cylinder.split.1x2")
                }
        }
        .frame(width: 500, height: 400)
        .alert("Error", isPresented: $viewModel.showError) {
            Button("OK", role: .cancel) { }
        } message: {
            Text(viewModel.errorMessage ?? "An unknown error occurred")
        }
        .alert("Success", isPresented: $viewModel.showSuccess) {
            Button("OK", role: .cancel) { }
        } message: {
            Text(viewModel.successMessage ?? "Operation completed")
        }
    }
}

// MARK: - General Settings Tab

/// General settings including language, appearance, and startup options.
struct GeneralSettingsTab: View {

    // MARK: - Properties

    @ObservedObject var viewModel: SettingsViewModel

    // MARK: - Body

    var body: some View {
        Form {
            // Appearance Section
            Section {
                Picker("Color Scheme", selection: $viewModel.colorSchemePreference) {
                    ForEach(ColorSchemePreference.allCases) { preference in
                        Text(preference.displayName).tag(preference)
                    }
                }
                .pickerStyle(.segmented)

                Picker("Language", selection: $viewModel.selectedLanguage) {
                    ForEach(AppLanguage.allCases) { language in
                        Text(language.displayName).tag(language)
                    }
                }
            } header: {
                Text("Appearance")
            } footer: {
                Text("Changes to language require restarting the app.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // Startup Section
            Section {
                Toggle("Launch at Login", isOn: $viewModel.launchAtLogin)

                Toggle("Show Menu Bar Icon", isOn: $viewModel.showMenuBarExtra)

                Picker("Default Section", selection: $viewModel.defaultSection) {
                    ForEach(NavigationSection.allCases) { section in
                        Label(section.localizedTitle, systemImage: section.icon)
                            .tag(section)
                    }
                }
            } header: {
                Text("Startup")
            }
        }
        .formStyle(.grouped)
        .padding()
    }
}

// MARK: - Server Settings Tab

/// Server connection settings for API URLs and testing.
struct ServerSettingsTab: View {

    // MARK: - Properties

    @ObservedObject var viewModel: SettingsViewModel

    // MARK: - Body

    var body: some View {
        Form {
            // Gibbon CMS Section
            Section {
                VStack(alignment: .leading, spacing: 8) {
                    TextField("API URL", text: $viewModel.gibbonAPIURL)
                        .textFieldStyle(.roundedBorder)

                    if let error = viewModel.gibbonURLError {
                        Text(error)
                            .foregroundColor(.red)
                            .font(.caption)
                    }

                    HStack {
                        ConnectionStatusView(status: viewModel.gibbonConnectionStatus)
                        Spacer()
                    }
                }
            } header: {
                Text("Gibbon CMS")
            } footer: {
                Text("The URL for the Gibbon Content Management System API.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // AI Service Section
            Section {
                VStack(alignment: .leading, spacing: 8) {
                    TextField("API URL", text: $viewModel.aiServiceURL)
                        .textFieldStyle(.roundedBorder)

                    if let error = viewModel.aiServiceURLError {
                        Text(error)
                            .foregroundColor(.red)
                            .font(.caption)
                    }

                    HStack {
                        ConnectionStatusView(status: viewModel.aiServiceConnectionStatus)
                        Spacer()
                    }
                }
            } header: {
                Text("AI Service")
            } footer: {
                Text("The URL for the AI Analytics Service API.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // Actions Section
            Section {
                HStack {
                    Button("Test Connections") {
                        Task {
                            await viewModel.testConnections()
                        }
                    }
                    .disabled(viewModel.isTestingConnection)

                    if viewModel.isTestingConnection {
                        ProgressView()
                            .controlSize(.small)
                            .padding(.leading, 8)
                    }

                    Spacer()

                    Button("Reset to Defaults") {
                        viewModel.resetToDefaults()
                    }
                    .foregroundColor(.red)
                }
            }
        }
        .formStyle(.grouped)
        .padding()
    }
}

// MARK: - Notification Settings Tab

/// Notification preferences including categories and permissions.
struct NotificationSettingsTab: View {

    // MARK: - Properties

    @ObservedObject var viewModel: SettingsViewModel

    /// State for showing permission alert
    @State private var showingPermissionAlert = false

    // MARK: - Body

    var body: some View {
        Form {
            // Authorization Status Section
            Section {
                HStack {
                    Text("System Permission")
                    Spacer()
                    authorizationStatusView
                }

                if viewModel.notificationAuthorizationStatus == .denied {
                    Button("Open System Preferences") {
                        viewModel.openSystemNotificationPreferences()
                    }
                    .foregroundColor(.accentColor)
                } else if viewModel.notificationAuthorizationStatus == .notDetermined {
                    Button("Request Permission") {
                        Task {
                            await viewModel.requestNotificationAuthorization()
                        }
                    }
                    .foregroundColor(.accentColor)
                }
            } header: {
                Text("Permission")
            }

            // Enable/Disable Toggle
            Section {
                Toggle("Enable Notifications", isOn: $viewModel.notificationsEnabled)
                    .disabled(viewModel.notificationAuthorizationStatus != .authorized)
            } header: {
                Text("General")
            }

            // Category Preferences Section
            Section {
                ForEach(NotificationCategory.allCases, id: \.self) { category in
                    Toggle(category.displayName, isOn: categoryBinding(for: category))
                        .disabled(!viewModel.notificationsEnabled)
                }
            } header: {
                Text("Notification Categories")
            } footer: {
                Text("Choose which types of notifications you want to receive.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }
        }
        .formStyle(.grouped)
        .padding()
    }

    // MARK: - Views

    @ViewBuilder
    private var authorizationStatusView: some View {
        switch viewModel.notificationAuthorizationStatus {
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

    // MARK: - Helpers

    private func categoryBinding(for category: NotificationCategory) -> Binding<Bool> {
        Binding(
            get: { viewModel.isCategoryEnabled(category) },
            set: { _ in viewModel.toggleCategory(category) }
        )
    }
}

// MARK: - Sync Settings Tab

/// Sync settings for automatic data synchronization.
struct SyncSettingsTab: View {

    // MARK: - Properties

    @ObservedObject var viewModel: SettingsViewModel

    /// State for sync in progress
    @State private var isSyncing = false

    // MARK: - Body

    var body: some View {
        Form {
            // Auto Sync Section
            Section {
                Toggle("Enable Auto Sync", isOn: $viewModel.autoSyncEnabled)

                Picker("Sync Interval", selection: $viewModel.syncIntervalMinutes) {
                    ForEach(SyncInterval.allCases) { interval in
                        Text(interval.displayName).tag(interval.rawValue)
                    }
                }
                .disabled(!viewModel.autoSyncEnabled)

                Toggle("Sync Over Cellular", isOn: $viewModel.syncOverCellular)
                    .disabled(!viewModel.autoSyncEnabled)
            } header: {
                Text("Automatic Sync")
            } footer: {
                Text("Automatically synchronize data with the server at regular intervals.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // Manual Sync Section
            Section {
                HStack {
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Last Sync")
                            .font(.subheadline)
                        Text(viewModel.lastSyncDateString)
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Spacer()

                    Button {
                        performSync()
                    } label: {
                        if isSyncing {
                            ProgressView()
                                .controlSize(.small)
                        } else {
                            Label("Sync Now", systemImage: "arrow.triangle.2.circlepath")
                        }
                    }
                    .disabled(isSyncing)
                }
            } header: {
                Text("Manual Sync")
            }

            // Offline Mode Info
            Section {
                HStack {
                    Image(systemName: "wifi.slash")
                        .foregroundColor(.orange)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Offline Mode")
                            .font(.subheadline)
                        Text("Changes made while offline are queued and synced automatically when connection is restored.")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
            } header: {
                Text("Information")
            }
        }
        .formStyle(.grouped)
        .padding()
    }

    // MARK: - Actions

    private func performSync() {
        isSyncing = true
        Task {
            await viewModel.syncNow()
            isSyncing = false
        }
    }
}

// MARK: - Data Settings Tab

/// Data management settings for cache and data reset.
struct DataSettingsTab: View {

    // MARK: - Properties

    @ObservedObject var viewModel: SettingsViewModel

    /// State for confirmation dialogs
    @State private var showClearCacheConfirmation = false
    @State private var showResetSettingsConfirmation = false

    // MARK: - Body

    var body: some View {
        Form {
            // Cache Section
            Section {
                HStack {
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Cache Size")
                            .font(.subheadline)
                        Text(viewModel.cacheSizeString)
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Spacer()

                    Button("Clear Cache") {
                        showClearCacheConfirmation = true
                    }
                    .disabled(viewModel.isClearingCache)
                }

                if viewModel.isClearingCache {
                    HStack {
                        ProgressView()
                            .controlSize(.small)
                        Text("Clearing cache...")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
            } header: {
                Text("Cache")
            } footer: {
                Text("Cached data improves performance and enables offline access. Clearing the cache will require re-downloading data from the server.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // Reset Section
            Section {
                Button("Reset All Settings") {
                    showResetSettingsConfirmation = true
                }
                .foregroundColor(.red)
            } header: {
                Text("Reset")
            } footer: {
                Text("This will reset all settings to their default values. Your data will not be affected.")
                    .foregroundColor(.secondary)
                    .font(.caption)
            }

            // About Section
            Section {
                LabeledContent("Version") {
                    Text(appVersion)
                }

                LabeledContent("Build") {
                    Text(buildNumber)
                }

                LabeledContent("Bundle ID") {
                    Text(AppConstants.bundleIdentifier)
                }
            } header: {
                Text("About")
            }
        }
        .formStyle(.grouped)
        .padding()
        .confirmationDialog(
            "Clear Cache",
            isPresented: $showClearCacheConfirmation,
            titleVisibility: .visible
        ) {
            Button("Clear Cache", role: .destructive) {
                Task {
                    await viewModel.clearCache()
                }
            }
            Button("Cancel", role: .cancel) { }
        } message: {
            Text("This will clear all cached data. You will need to be online to reload data.")
        }
        .confirmationDialog(
            "Reset Settings",
            isPresented: $showResetSettingsConfirmation,
            titleVisibility: .visible
        ) {
            Button("Reset All Settings", role: .destructive) {
                viewModel.resetAllSettings()
            }
            Button("Cancel", role: .cancel) { }
        } message: {
            Text("This will reset all settings to their default values. This action cannot be undone.")
        }
    }

    // MARK: - Computed Properties

    private var appVersion: String {
        Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0.0"
    }

    private var buildNumber: String {
        Bundle.main.infoDictionary?["CFBundleVersion"] as? String ?? "1"
    }
}

// MARK: - Connection Status View

/// View for displaying connection status with appropriate styling.
struct ConnectionStatusView: View {

    // MARK: - Properties

    let status: ConnectionStatus

    // MARK: - Body

    var body: some View {
        HStack(spacing: 6) {
            Image(systemName: status.icon)
                .foregroundColor(status.color)

            Text(status.displayText)
                .font(.caption)
                .foregroundColor(status.color)
        }
    }
}

// MARK: - Preview

#Preview("Settings View") {
    SettingsView()
        .environmentObject(NotificationService.shared)
}

#Preview("General Settings") {
    GeneralSettingsTab(viewModel: .preview)
        .frame(width: 500, height: 400)
}

#Preview("Server Settings") {
    ServerSettingsTab(viewModel: .preview)
        .frame(width: 500, height: 400)
}

#Preview("Server Settings - Connected") {
    ServerSettingsTab(viewModel: .previewConnected)
        .frame(width: 500, height: 400)
}

#Preview("Server Settings - Testing") {
    ServerSettingsTab(viewModel: .previewTesting)
        .frame(width: 500, height: 400)
}

#Preview("Notification Settings") {
    NotificationSettingsTab(viewModel: .preview)
        .frame(width: 500, height: 400)
}

#Preview("Sync Settings") {
    SyncSettingsTab(viewModel: .preview)
        .frame(width: 500, height: 400)
}

#Preview("Data Settings") {
    DataSettingsTab(viewModel: .preview)
        .frame(width: 500, height: 400)
}
