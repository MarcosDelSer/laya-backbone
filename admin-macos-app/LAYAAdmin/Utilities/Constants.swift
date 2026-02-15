//
//  Constants.swift
//  LAYAAdmin
//
//  App-wide constants and configuration values
//

import Foundation

// MARK: - App Constants

/// App-wide constants used throughout the application
enum AppConstants {

    // MARK: - App Info

    /// Bundle identifier for the app
    static let bundleIdentifier = "com.laya.admin"

    /// App name for display purposes
    static let appName = "LAYA Admin"

    /// Keychain service identifier
    static let keychainService = "com.laya.admin"

    // MARK: - API Configuration

    /// Default Gibbon CMS API base URL
    /// Can be overridden via Config.xcconfig or UserDefaults
    static var gibbonAPIURL: String {
        if let configURL = Bundle.main.object(forInfoDictionaryKey: "GIBBON_API_URL") as? String,
           !configURL.isEmpty,
           configURL != "$(GIBBON_API_URL)" {
            return configURL
        }
        return "http://localhost/gibbon/api"
    }

    /// Default AI Service API base URL
    /// Can be overridden via Config.xcconfig or UserDefaults
    static var aiServiceURL: String {
        if let configURL = Bundle.main.object(forInfoDictionaryKey: "AI_SERVICE_URL") as? String,
           !configURL.isEmpty,
           configURL != "$(AI_SERVICE_URL)" {
            return configURL
        }
        return "http://localhost:8000"
    }

    // MARK: - Network Configuration

    /// Default request timeout in seconds
    static let requestTimeout: TimeInterval = 30.0

    /// Maximum number of retry attempts for failed requests
    static let maxRetryAttempts = 3

    /// Base delay for exponential backoff (in seconds)
    static let retryBaseDelay: TimeInterval = 1.0

    // MARK: - Cache Configuration

    /// Cache expiry time in seconds (default: 5 minutes)
    static let cacheExpiryTime: TimeInterval = 300

    /// Maximum number of items to keep in memory cache
    static let maxCacheItems = 100

    // MARK: - UI Configuration

    /// Default sidebar width
    static let sidebarMinWidth: CGFloat = 200
    static let sidebarMaxWidth: CGFloat = 300

    /// Default detail view minimum width
    static let detailMinWidth: CGFloat = 400

    /// Animation duration for standard transitions
    static let animationDuration: TimeInterval = 0.25

    // MARK: - Pagination

    /// Default page size for list views
    static let defaultPageSize = 50

    /// Maximum page size allowed
    static let maxPageSize = 100
}

// MARK: - API Endpoints

/// API endpoint paths for Gibbon CMS
enum GibbonEndpoints {
    static let auth = "/auth"
    static let login = "/auth/login"
    static let logout = "/auth/logout"
    static let refreshToken = "/auth/refresh"

    static let students = "/students"
    static let staff = "/staff"
    static let invoices = "/finance/invoices"
    static let payments = "/finance/payments"
    static let releve24 = "/finance/releve24"
    static let releve24Export = "/finance/releve24/export"

    static let dashboard = "/dashboard"
    static let notifications = "/notifications"
}

/// API endpoint paths for AI Service
enum AIServiceEndpoints {
    static let dashboard = "/analytics/dashboard"
    static let kpis = "/analytics/kpis"
    static let enrollmentForecast = "/analytics/enrollment-forecast"
    static let staffEfficiency = "/analytics/staff-efficiency"
    static let compliance = "/analytics/compliance"
    static let financialHealth = "/analytics/financial-health"
}

// MARK: - User Defaults Keys

/// Keys for UserDefaults storage
enum UserDefaultsKeys {
    static let gibbonAPIURL = "gibbonAPIURL"
    static let aiServiceURL = "aiServiceURL"
    static let lastSyncDate = "lastSyncDate"
    static let selectedLanguage = "selectedLanguage"
    static let notificationsEnabled = "notificationsEnabled"
    static let autoSyncEnabled = "autoSyncEnabled"
    static let syncIntervalMinutes = "syncIntervalMinutes"
    static let lastViewedSection = "lastViewedSection"
}

// MARK: - Keychain Keys

/// Keys for Keychain storage
enum KeychainKeys {
    static let jwtToken = "jwt_token"
    static let refreshToken = "refresh_token"
    static let userId = "user_id"
    static let userRole = "user_role"
}

// MARK: - Date Formats

/// Standard date format strings used in the app
enum DateFormats {
    /// ISO 8601 format for API communication
    static let iso8601 = "yyyy-MM-dd'T'HH:mm:ss.SSSZ"

    /// Date only format for display
    static let dateOnly = "yyyy-MM-dd"

    /// Display format for dates (localized)
    static let displayDate = "MMM d, yyyy"

    /// Display format for date and time (localized)
    static let displayDateTime = "MMM d, yyyy 'at' h:mm a"

    /// Time only format
    static let timeOnly = "HH:mm"

    /// Quebec fiscal year format (for Releve 24)
    static let fiscalYear = "yyyy"
}

// MARK: - Notification Names (App-specific)

/// Custom notification names for app-wide communication
extension Notification.Name {
    /// Posted when user authentication state changes
    static let authStateChanged = Notification.Name("authStateChanged")

    /// Posted when network connectivity changes
    static let networkStatusChanged = Notification.Name("networkStatusChanged")

    /// Posted when sync operation completes
    static let syncCompleted = Notification.Name("syncCompleted")

    /// Posted when sync operation fails
    static let syncFailed = Notification.Name("syncFailed")

    /// Posted when data needs to be refreshed
    static let dataRefreshNeeded = Notification.Name("dataRefreshNeeded")

    /// Posted when user preferences change
    static let preferencesChanged = Notification.Name("preferencesChanged")
}

// MARK: - User Roles

/// User roles in the LAYA system
enum UserRole: String, Codable, CaseIterable {
    case director = "director"
    case accountant = "accountant"
    case admin = "admin"

    var displayName: String {
        switch self {
        case .director:
            return String(localized: "Director")
        case .accountant:
            return String(localized: "Accountant")
        case .admin:
            return String(localized: "Administrator")
        }
    }

    /// Permissions for each role
    var canManageChildren: Bool {
        switch self {
        case .director, .admin:
            return true
        case .accountant:
            return false
        }
    }

    var canManageStaff: Bool {
        switch self {
        case .director, .admin:
            return true
        case .accountant:
            return false
        }
    }

    var canManageFinance: Bool {
        switch self {
        case .accountant, .admin:
            return true
        case .director:
            return false // Read-only for directors
        }
    }

    var canViewAnalytics: Bool {
        return true // All roles can view analytics
    }
}

// MARK: - Enrollment Status

/// Enrollment status for children
enum EnrollmentStatus: String, Codable, CaseIterable {
    case active = "active"
    case pending = "pending"
    case waitlist = "waitlist"
    case graduated = "graduated"
    case withdrawn = "withdrawn"

    var displayName: String {
        switch self {
        case .active:
            return String(localized: "Active")
        case .pending:
            return String(localized: "Pending")
        case .waitlist:
            return String(localized: "Waitlist")
        case .graduated:
            return String(localized: "Graduated")
        case .withdrawn:
            return String(localized: "Withdrawn")
        }
    }

    var color: String {
        switch self {
        case .active:
            return "green"
        case .pending:
            return "orange"
        case .waitlist:
            return "blue"
        case .graduated:
            return "purple"
        case .withdrawn:
            return "gray"
        }
    }
}

// MARK: - Invoice Status

/// Payment status for invoices
enum InvoiceStatus: String, Codable, CaseIterable {
    case draft = "draft"
    case pending = "pending"
    case paid = "paid"
    case overdue = "overdue"
    case cancelled = "cancelled"

    var displayName: String {
        switch self {
        case .draft:
            return String(localized: "Draft")
        case .pending:
            return String(localized: "Pending")
        case .paid:
            return String(localized: "Paid")
        case .overdue:
            return String(localized: "Overdue")
        case .cancelled:
            return String(localized: "Cancelled")
        }
    }

    var color: String {
        switch self {
        case .draft:
            return "gray"
        case .pending:
            return "orange"
        case .paid:
            return "green"
        case .overdue:
            return "red"
        case .cancelled:
            return "gray"
        }
    }
}
