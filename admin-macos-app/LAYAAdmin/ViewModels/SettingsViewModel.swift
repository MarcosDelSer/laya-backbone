//
//  SettingsViewModel.swift
//  LAYAAdmin
//
//  ViewModel for managing application settings and preferences.
//  Handles API configuration, notification preferences, appearance settings,
//  and sync configuration.
//

import Foundation
import Combine
import SwiftUI
import UserNotifications

// MARK: - Settings ViewModel

/// ViewModel for managing application settings and preferences.
///
/// This ViewModel acts as a centralized manager for all user preferences,
/// providing observable state for the settings UI and persistence to UserDefaults.
///
/// Features:
/// - API URL configuration for Gibbon CMS and AI Service
/// - Notification preferences by category
/// - Appearance and language settings
/// - Sync and cache configuration
/// - Data management (clear cache, reset settings)
@MainActor
final class SettingsViewModel: ObservableObject {

    // MARK: - Published Properties

    // MARK: Server Settings

    /// Gibbon CMS API URL
    @Published var gibbonAPIURL: String {
        didSet {
            if validateURL(gibbonAPIURL) || gibbonAPIURL.isEmpty {
                UserDefaults.standard.set(gibbonAPIURL, forKey: UserDefaultsKeys.gibbonAPIURL)
                isDirty = true
            }
        }
    }

    /// AI Service API URL
    @Published var aiServiceURL: String {
        didSet {
            if validateURL(aiServiceURL) || aiServiceURL.isEmpty {
                UserDefaults.standard.set(aiServiceURL, forKey: UserDefaultsKeys.aiServiceURL)
                isDirty = true
            }
        }
    }

    /// Connection test status for Gibbon
    @Published private(set) var gibbonConnectionStatus: ConnectionStatus = .unknown

    /// Connection test status for AI Service
    @Published private(set) var aiServiceConnectionStatus: ConnectionStatus = .unknown

    /// Whether a connection test is in progress
    @Published private(set) var isTestingConnection = false

    // MARK: General Settings

    /// Selected language preference
    @Published var selectedLanguage: AppLanguage {
        didSet {
            UserDefaults.standard.set(selectedLanguage.rawValue, forKey: UserDefaultsKeys.selectedLanguage)
            NotificationCenter.default.post(name: .preferencesChanged, object: nil, userInfo: ["key": "language"])
        }
    }

    /// Whether to show the menu bar extra
    @Published var showMenuBarExtra: Bool {
        didSet {
            UserDefaults.standard.set(showMenuBarExtra, forKey: "showMenuBarExtra")
        }
    }

    /// Whether to launch at login
    @Published var launchAtLogin: Bool {
        didSet {
            UserDefaults.standard.set(launchAtLogin, forKey: "launchAtLogin")
            updateLaunchAtLogin()
        }
    }

    /// Default section to show on launch
    @Published var defaultSection: NavigationSection {
        didSet {
            UserDefaults.standard.set(defaultSection.rawValue, forKey: UserDefaultsKeys.lastViewedSection)
        }
    }

    /// Color scheme preference
    @Published var colorSchemePreference: ColorSchemePreference {
        didSet {
            UserDefaults.standard.set(colorSchemePreference.rawValue, forKey: "colorSchemePreference")
            NotificationCenter.default.post(name: .preferencesChanged, object: nil, userInfo: ["key": "colorScheme"])
        }
    }

    // MARK: Sync Settings

    /// Whether auto sync is enabled
    @Published var autoSyncEnabled: Bool {
        didSet {
            UserDefaults.standard.set(autoSyncEnabled, forKey: UserDefaultsKeys.autoSyncEnabled)
        }
    }

    /// Sync interval in minutes
    @Published var syncIntervalMinutes: Int {
        didSet {
            UserDefaults.standard.set(syncIntervalMinutes, forKey: UserDefaultsKeys.syncIntervalMinutes)
        }
    }

    /// Whether to sync over cellular (if supported)
    @Published var syncOverCellular: Bool {
        didSet {
            UserDefaults.standard.set(syncOverCellular, forKey: "syncOverCellular")
        }
    }

    /// Last successful sync date
    @Published private(set) var lastSyncDate: Date?

    // MARK: Cache Settings

    /// Cache size in megabytes (estimated)
    @Published private(set) var cacheSize: Int = 0

    /// Whether cache is being cleared
    @Published private(set) var isClearingCache = false

    // MARK: Notification Settings

    /// Whether notifications are enabled globally
    @Published var notificationsEnabled: Bool {
        didSet {
            UserDefaults.standard.set(notificationsEnabled, forKey: UserDefaultsKeys.notificationsEnabled)
            notificationService?.notificationsEnabled = notificationsEnabled
        }
    }

    /// Enabled notification categories
    @Published var enabledNotificationCategories: Set<NotificationCategory> {
        didSet {
            let rawValues = enabledNotificationCategories.map { $0.rawValue }
            UserDefaults.standard.set(rawValues, forKey: "enabledNotificationCategories")
            updateNotificationCategories()
        }
    }

    /// System notification authorization status
    @Published private(set) var notificationAuthorizationStatus: UNAuthorizationStatus = .notDetermined

    // MARK: State

    /// Whether settings have unsaved changes
    @Published private(set) var isDirty = false

    /// Whether settings are being saved
    @Published private(set) var isSaving = false

    /// Error message to display
    @Published var errorMessage: String?

    /// Whether to show error alert
    @Published var showError = false

    /// Success message to display
    @Published var successMessage: String?

    /// Whether to show success alert
    @Published var showSuccess = false

    // MARK: - Private Properties

    /// Notification service reference
    private weak var notificationService: NotificationService?

    /// Combine cancellables
    private var cancellables = Set<AnyCancellable>()

    // MARK: - Computed Properties

    /// Whether the Gibbon URL is valid
    var isGibbonURLValid: Bool {
        validateURL(gibbonAPIURL)
    }

    /// Whether the AI Service URL is valid
    var isAIServiceURLValid: Bool {
        validateURL(aiServiceURL)
    }

    /// Validation error for Gibbon URL
    var gibbonURLError: String? {
        guard !gibbonAPIURL.isEmpty else { return nil }
        return isGibbonURLValid ? nil : String(localized: "Invalid URL format")
    }

    /// Validation error for AI Service URL
    var aiServiceURLError: String? {
        guard !aiServiceURL.isEmpty else { return nil }
        return isAIServiceURLValid ? nil : String(localized: "Invalid URL format")
    }

    /// Formatted last sync date string
    var lastSyncDateString: String {
        guard let date = lastSyncDate else {
            return String(localized: "Never")
        }
        return date.formatted(date: .abbreviated, time: .shortened)
    }

    /// Formatted cache size string
    var cacheSizeString: String {
        if cacheSize < 1 {
            return String(localized: "< 1 MB")
        }
        return String(localized: "\(cacheSize) MB")
    }

    // MARK: - Initialization

    /// Creates a new SettingsViewModel
    /// - Parameter notificationService: Optional notification service (defaults to shared instance)
    init(notificationService: NotificationService? = nil) {
        // Load settings from UserDefaults
        self.gibbonAPIURL = UserDefaults.standard.string(forKey: UserDefaultsKeys.gibbonAPIURL) ?? AppConstants.gibbonAPIURL
        self.aiServiceURL = UserDefaults.standard.string(forKey: UserDefaultsKeys.aiServiceURL) ?? AppConstants.aiServiceURL

        self.selectedLanguage = AppLanguage(
            rawValue: UserDefaults.standard.string(forKey: UserDefaultsKeys.selectedLanguage) ?? ""
        ) ?? .system

        self.showMenuBarExtra = UserDefaults.standard.bool(forKey: "showMenuBarExtra")
        if !UserDefaults.standard.bool(forKey: "showMenuBarExtraInitialized") {
            self.showMenuBarExtra = true
            UserDefaults.standard.set(true, forKey: "showMenuBarExtra")
            UserDefaults.standard.set(true, forKey: "showMenuBarExtraInitialized")
        }

        self.launchAtLogin = UserDefaults.standard.bool(forKey: "launchAtLogin")

        self.defaultSection = NavigationSection(
            rawValue: UserDefaults.standard.string(forKey: UserDefaultsKeys.lastViewedSection) ?? ""
        ) ?? .dashboard

        self.colorSchemePreference = ColorSchemePreference(
            rawValue: UserDefaults.standard.string(forKey: "colorSchemePreference") ?? ""
        ) ?? .system

        self.autoSyncEnabled = UserDefaults.standard.bool(forKey: UserDefaultsKeys.autoSyncEnabled)
        if !UserDefaults.standard.bool(forKey: "autoSyncEnabledInitialized") {
            self.autoSyncEnabled = true
            UserDefaults.standard.set(true, forKey: UserDefaultsKeys.autoSyncEnabled)
            UserDefaults.standard.set(true, forKey: "autoSyncEnabledInitialized")
        }

        self.syncIntervalMinutes = UserDefaults.standard.integer(forKey: UserDefaultsKeys.syncIntervalMinutes)
        if syncIntervalMinutes == 0 {
            self.syncIntervalMinutes = 15
            UserDefaults.standard.set(15, forKey: UserDefaultsKeys.syncIntervalMinutes)
        }

        self.syncOverCellular = UserDefaults.standard.bool(forKey: "syncOverCellular")

        self.notificationsEnabled = UserDefaults.standard.bool(forKey: UserDefaultsKeys.notificationsEnabled)

        // Load notification categories
        if let rawValues = UserDefaults.standard.stringArray(forKey: "enabledNotificationCategories") {
            self.enabledNotificationCategories = Set(rawValues.compactMap { NotificationCategory(rawValue: $0) })
        } else {
            self.enabledNotificationCategories = Set(NotificationCategory.allCases)
        }

        // Load last sync date
        if let timestamp = UserDefaults.standard.object(forKey: UserDefaultsKeys.lastSyncDate) as? Date {
            self.lastSyncDate = timestamp
        }

        self.notificationService = notificationService ?? NotificationService.shared

        // Calculate initial cache size
        Task {
            await calculateCacheSize()
        }

        // Check notification authorization
        Task {
            await checkNotificationAuthorization()
        }

        // Setup observers
        setupObservers()
    }

    // MARK: - Public Methods

    /// Tests the connection to both API servers
    func testConnections() async {
        isTestingConnection = true
        gibbonConnectionStatus = .testing
        aiServiceConnectionStatus = .testing

        // Test Gibbon connection
        async let gibbonResult = testGibbonConnection()

        // Test AI Service connection
        async let aiResult = testAIServiceConnection()

        // Await both results
        let (gibbon, ai) = await (gibbonResult, aiResult)

        gibbonConnectionStatus = gibbon
        aiServiceConnectionStatus = ai
        isTestingConnection = false
    }

    /// Tests the connection to Gibbon CMS
    func testGibbonConnection() async -> ConnectionStatus {
        guard !gibbonAPIURL.isEmpty, validateURL(gibbonAPIURL) else {
            return .failed(String(localized: "Invalid URL"))
        }

        guard let url = URL(string: gibbonAPIURL)?.appendingPathComponent("/health") else {
            return .failed(String(localized: "Invalid URL"))
        }

        do {
            var request = URLRequest(url: url)
            request.timeoutInterval = 10

            let (_, response) = try await URLSession.shared.data(for: request)

            if let httpResponse = response as? HTTPURLResponse {
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    return .connected
                } else if httpResponse.statusCode == 401 || httpResponse.statusCode == 403 {
                    return .connected // Server is reachable but requires auth
                } else {
                    return .failed(String(localized: "Server returned status \(httpResponse.statusCode)"))
                }
            }
            return .connected
        } catch {
            return .failed(error.localizedDescription)
        }
    }

    /// Tests the connection to AI Service
    func testAIServiceConnection() async -> ConnectionStatus {
        guard !aiServiceURL.isEmpty, validateURL(aiServiceURL) else {
            return .failed(String(localized: "Invalid URL"))
        }

        guard let url = URL(string: aiServiceURL)?.appendingPathComponent("/health") else {
            return .failed(String(localized: "Invalid URL"))
        }

        do {
            var request = URLRequest(url: url)
            request.timeoutInterval = 10

            let (_, response) = try await URLSession.shared.data(for: request)

            if let httpResponse = response as? HTTPURLResponse {
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    return .connected
                } else if httpResponse.statusCode == 401 || httpResponse.statusCode == 403 {
                    return .connected // Server is reachable but requires auth
                } else {
                    return .failed(String(localized: "Server returned status \(httpResponse.statusCode)"))
                }
            }
            return .connected
        } catch {
            return .failed(error.localizedDescription)
        }
    }

    /// Resets server URLs to defaults
    func resetToDefaults() {
        gibbonAPIURL = AppConstants.gibbonAPIURL
        aiServiceURL = AppConstants.aiServiceURL

        successMessage = String(localized: "Server URLs reset to defaults")
        showSuccess = true
    }

    /// Clears all cached data
    func clearCache() async {
        isClearingCache = true

        do {
            // Clear Realm database cache
            let realmManager = RealmManager.shared
            try await realmManager.clearAllCachedData()

            // Clear URL cache
            URLCache.shared.removeAllCachedResponses()

            // Clear temporary files
            let fileManager = FileManager.default
            let tempDir = fileManager.temporaryDirectory
            let tempFiles = try? fileManager.contentsOfDirectory(at: tempDir, includingPropertiesForKeys: nil)
            tempFiles?.forEach { try? fileManager.removeItem(at: $0) }

            // Recalculate cache size
            await calculateCacheSize()

            successMessage = String(localized: "Cache cleared successfully")
            showSuccess = true
        } catch {
            errorMessage = String(localized: "Failed to clear cache: \(error.localizedDescription)")
            showError = true
        }

        isClearingCache = false
    }

    /// Resets all settings to defaults
    func resetAllSettings() {
        // Server settings
        gibbonAPIURL = AppConstants.gibbonAPIURL
        aiServiceURL = AppConstants.aiServiceURL

        // General settings
        selectedLanguage = .system
        showMenuBarExtra = true
        launchAtLogin = false
        defaultSection = .dashboard
        colorSchemePreference = .system

        // Sync settings
        autoSyncEnabled = true
        syncIntervalMinutes = 15
        syncOverCellular = false

        // Notification settings
        notificationsEnabled = true
        enabledNotificationCategories = Set(NotificationCategory.allCases)

        isDirty = false

        successMessage = String(localized: "All settings reset to defaults")
        showSuccess = true

        NotificationCenter.default.post(name: .preferencesChanged, object: nil)
    }

    /// Requests notification authorization from the system
    func requestNotificationAuthorization() async {
        do {
            let granted = try await notificationService?.requestAuthorization() ?? false

            if granted {
                notificationAuthorizationStatus = .authorized
                notificationsEnabled = true
            } else {
                notificationAuthorizationStatus = .denied
            }
        } catch {
            errorMessage = error.localizedDescription
            showError = true
        }
    }

    /// Opens system notification preferences
    func openSystemNotificationPreferences() {
        if let url = URL(string: "x-apple.systempreferences:com.apple.preference.notifications") {
            NSWorkspace.shared.open(url)
        }
    }

    /// Triggers manual sync
    func syncNow() async {
        do {
            let syncService = SyncService.shared
            try await syncService.syncAll()

            lastSyncDate = Date()
            UserDefaults.standard.set(lastSyncDate, forKey: UserDefaultsKeys.lastSyncDate)

            successMessage = String(localized: "Sync completed successfully")
            showSuccess = true
        } catch {
            errorMessage = String(localized: "Sync failed: \(error.localizedDescription)")
            showError = true
        }
    }

    /// Checks if a notification category is enabled
    func isCategoryEnabled(_ category: NotificationCategory) -> Bool {
        enabledNotificationCategories.contains(category)
    }

    /// Toggles a notification category
    func toggleCategory(_ category: NotificationCategory) {
        if enabledNotificationCategories.contains(category) {
            enabledNotificationCategories.remove(category)
        } else {
            enabledNotificationCategories.insert(category)
        }
    }

    // MARK: - Private Methods

    /// Validates a URL string
    private func validateURL(_ urlString: String) -> Bool {
        guard !urlString.isEmpty,
              let url = URL(string: urlString),
              let scheme = url.scheme,
              (scheme == "http" || scheme == "https"),
              url.host != nil else {
            return false
        }
        return true
    }

    /// Calculates the current cache size
    private func calculateCacheSize() async {
        var totalSize: Int64 = 0

        // URL Cache size
        totalSize += Int64(URLCache.shared.currentDiskUsage)

        // Temporary directory size
        let fileManager = FileManager.default
        let tempDir = fileManager.temporaryDirectory
        if let enumerator = fileManager.enumerator(at: tempDir, includingPropertiesForKeys: [.fileSizeKey]) {
            for case let fileURL as URL in enumerator {
                if let size = try? fileURL.resourceValues(forKeys: [.fileSizeKey]).fileSize {
                    totalSize += Int64(size)
                }
            }
        }

        // Convert to MB
        cacheSize = Int(totalSize / (1024 * 1024))
    }

    /// Updates notification categories in the notification service
    private func updateNotificationCategories() {
        guard let service = notificationService else { return }

        for category in NotificationCategory.allCases {
            let enabled = enabledNotificationCategories.contains(category)
            service.setCategoryEnabled(category, enabled: enabled)
        }
    }

    /// Checks notification authorization status
    private func checkNotificationAuthorization() async {
        let settings = await UNUserNotificationCenter.current().notificationSettings()
        notificationAuthorizationStatus = settings.authorizationStatus
    }

    /// Updates launch at login setting
    private func updateLaunchAtLogin() {
        // Note: In a real app, this would use SMAppService (macOS 13+) or
        // ServiceManagement framework for launch agent registration
        // For now, we just persist the preference
    }

    /// Sets up observers for external changes
    private func setupObservers() {
        // Observe sync completion notifications
        NotificationCenter.default.publisher(for: .syncCompleted)
            .sink { [weak self] _ in
                self?.lastSyncDate = Date()
            }
            .store(in: &cancellables)

        // Observe notification authorization changes
        notificationService?.authorizationStatusPublisher
            .receive(on: DispatchQueue.main)
            .sink { [weak self] status in
                self?.notificationAuthorizationStatus = status
            }
            .store(in: &cancellables)
    }
}

// MARK: - Supporting Types

/// Connection status for API servers
enum ConnectionStatus: Equatable {
    case unknown
    case testing
    case connected
    case failed(String)

    var displayText: String {
        switch self {
        case .unknown:
            return String(localized: "Not tested")
        case .testing:
            return String(localized: "Testing...")
        case .connected:
            return String(localized: "Connected")
        case .failed(let reason):
            return String(localized: "Failed: \(reason)")
        }
    }

    var color: Color {
        switch self {
        case .unknown:
            return .secondary
        case .testing:
            return .orange
        case .connected:
            return .green
        case .failed:
            return .red
        }
    }

    var icon: String {
        switch self {
        case .unknown:
            return "questionmark.circle"
        case .testing:
            return "arrow.triangle.2.circlepath"
        case .connected:
            return "checkmark.circle.fill"
        case .failed:
            return "xmark.circle.fill"
        }
    }
}

/// App language preference
enum AppLanguage: String, CaseIterable, Identifiable {
    case system = ""
    case english = "en"
    case french = "fr"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .system:
            return String(localized: "System Default")
        case .english:
            return "English"
        case .french:
            return "Français"
        }
    }
}

/// Color scheme preference
enum ColorSchemePreference: String, CaseIterable, Identifiable {
    case system = ""
    case light = "light"
    case dark = "dark"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .system:
            return String(localized: "System Default")
        case .light:
            return String(localized: "Light")
        case .dark:
            return String(localized: "Dark")
        }
    }

    var colorScheme: ColorScheme? {
        switch self {
        case .system:
            return nil
        case .light:
            return .light
        case .dark:
            return .dark
        }
    }
}

/// Available sync interval options
enum SyncInterval: Int, CaseIterable, Identifiable {
    case fiveMinutes = 5
    case fifteenMinutes = 15
    case thirtyMinutes = 30
    case oneHour = 60

    var id: Int { rawValue }

    var displayName: String {
        switch self {
        case .fiveMinutes:
            return String(localized: "5 minutes")
        case .fifteenMinutes:
            return String(localized: "15 minutes")
        case .thirtyMinutes:
            return String(localized: "30 minutes")
        case .oneHour:
            return String(localized: "1 hour")
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension SettingsViewModel {

    /// Creates a preview ViewModel with default settings
    static var preview: SettingsViewModel {
        let viewModel = SettingsViewModel()
        return viewModel
    }

    /// Creates a preview ViewModel with connection errors
    static var previewWithErrors: SettingsViewModel {
        let viewModel = SettingsViewModel()
        viewModel.gibbonConnectionStatus = .failed("Connection refused")
        viewModel.aiServiceConnectionStatus = .failed("Timeout")
        return viewModel
    }

    /// Creates a preview ViewModel with successful connections
    static var previewConnected: SettingsViewModel {
        let viewModel = SettingsViewModel()
        viewModel.gibbonConnectionStatus = .connected
        viewModel.aiServiceConnectionStatus = .connected
        return viewModel
    }

    /// Creates a preview ViewModel that is testing connections
    static var previewTesting: SettingsViewModel {
        let viewModel = SettingsViewModel()
        viewModel.isTestingConnection = true
        viewModel.gibbonConnectionStatus = .testing
        viewModel.aiServiceConnectionStatus = .testing
        return viewModel
    }
}
#endif
