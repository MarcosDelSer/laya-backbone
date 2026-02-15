//
//  NotificationService.swift
//  LAYAAdmin
//
//  Native macOS notification service for important events.
//  Handles notification permissions, scheduling, and delivery using
//  UNUserNotificationCenter.
//

import Foundation
import Combine
import UserNotifications

// MARK: - Notification Service Protocol

/// Protocol defining the notification service interface
protocol NotificationServiceProtocol {
    /// Current authorization status
    var authorizationStatus: UNAuthorizationStatus { get }

    /// Whether notifications are enabled in app settings
    var notificationsEnabled: Bool { get }

    /// Publisher for authorization status changes
    var authorizationStatusPublisher: AnyPublisher<UNAuthorizationStatus, Never> { get }

    /// Requests notification authorization from the user
    func requestAuthorization() async throws -> Bool

    /// Schedules a notification
    func scheduleNotification(_ notification: LAYANotification) async throws

    /// Schedules a notification for a dashboard alert
    func scheduleNotification(for alert: DashboardAlert) async throws

    /// Cancels a pending notification by identifier
    func cancelNotification(identifier: String)

    /// Cancels all pending notifications
    func cancelAllNotifications()

    /// Gets all pending notification requests
    func getPendingNotifications() async -> [UNNotificationRequest]

    /// Gets all delivered notifications
    func getDeliveredNotifications() async -> [UNNotification]

    /// Removes delivered notifications by identifiers
    func removeDeliveredNotifications(identifiers: [String])
}

// MARK: - LAYA Notification

/// Represents a notification to be scheduled.
struct LAYANotification: Identifiable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the notification
    let id: String

    /// Notification title
    let title: String

    /// Notification body/message
    let body: String

    /// Subtitle (optional)
    let subtitle: String?

    /// Category for the notification
    let category: NotificationCategory

    /// When to deliver the notification (nil = immediately)
    let triggerDate: Date?

    /// Whether the notification should be delivered silently
    let silent: Bool

    /// Sound to play (nil = default)
    let sound: UNNotificationSound?

    /// Badge count to set (nil = don't change)
    let badge: Int?

    /// User info dictionary for additional data
    let userInfo: [String: Any]

    // MARK: - Initialization

    /// Creates a new notification
    init(
        id: String = UUID().uuidString,
        title: String,
        body: String,
        subtitle: String? = nil,
        category: NotificationCategory = .general,
        triggerDate: Date? = nil,
        silent: Bool = false,
        sound: UNNotificationSound? = .default,
        badge: Int? = nil,
        userInfo: [String: Any] = [:]
    ) {
        self.id = id
        self.title = title
        self.body = body
        self.subtitle = subtitle
        self.category = category
        self.triggerDate = triggerDate
        self.silent = silent
        self.sound = sound
        self.badge = badge
        self.userInfo = userInfo
    }

    // MARK: - Equatable

    static func == (lhs: LAYANotification, rhs: LAYANotification) -> Bool {
        lhs.id == rhs.id
    }
}

// MARK: - Notification Category

/// Categories for LAYA notifications.
enum NotificationCategory: String, CaseIterable {
    case enrollment = "enrollment"
    case attendance = "attendance"
    case staffing = "staffing"
    case compliance = "compliance"
    case finance = "finance"
    case general = "general"
    case sync = "sync"
    case reminder = "reminder"

    /// Display name for the category
    var displayName: String {
        switch self {
        case .enrollment:
            return String(localized: "Enrollment")
        case .attendance:
            return String(localized: "Attendance")
        case .staffing:
            return String(localized: "Staffing")
        case .compliance:
            return String(localized: "Compliance")
        case .finance:
            return String(localized: "Finance")
        case .general:
            return String(localized: "General")
        case .sync:
            return String(localized: "Sync")
        case .reminder:
            return String(localized: "Reminder")
        }
    }

    /// Creates a notification category from an alert category
    init(from alertCategory: AlertCategory) {
        switch alertCategory {
        case .enrollment:
            self = .enrollment
        case .attendance:
            self = .attendance
        case .staffing:
            self = .staffing
        case .compliance:
            self = .compliance
        case .finance:
            self = .finance
        case .general:
            self = .general
        }
    }
}

// MARK: - Notification Service

/// Service for managing native macOS notifications.
///
/// Features:
/// - Request and track notification authorization
/// - Schedule immediate and time-based notifications
/// - Convert dashboard alerts to notifications
/// - Category-based notification filtering
/// - Observable status for UI updates
@MainActor
final class NotificationService: NSObject, ObservableObject, NotificationServiceProtocol {

    // MARK: - Singleton

    /// Shared instance for the app
    static let shared = NotificationService()

    // MARK: - Published Properties

    /// Current authorization status
    @Published private(set) var authorizationStatus: UNAuthorizationStatus = .notDetermined

    /// Whether notifications are enabled in app settings
    @Published var notificationsEnabled: Bool {
        didSet {
            UserDefaults.standard.set(notificationsEnabled, forKey: UserDefaultsKeys.notificationsEnabled)
        }
    }

    /// Publisher for authorization status changes
    var authorizationStatusPublisher: AnyPublisher<UNAuthorizationStatus, Never> {
        $authorizationStatus.eraseToAnyPublisher()
    }

    // MARK: - Private Properties

    /// The notification center instance
    private let notificationCenter: UNUserNotificationCenter

    /// Categories enabled for notifications
    private var enabledCategories: Set<NotificationCategory> = Set(NotificationCategory.allCases)

    /// Cancellables for Combine subscriptions
    private var cancellables = Set<AnyCancellable>()

    // MARK: - Initialization

    /// Creates a new NotificationService instance
    /// - Parameter notificationCenter: Optional custom notification center (for testing)
    init(notificationCenter: UNUserNotificationCenter = .current()) {
        self.notificationCenter = notificationCenter
        self.notificationsEnabled = UserDefaults.standard.bool(forKey: UserDefaultsKeys.notificationsEnabled)

        super.init()

        // Set delegate for handling notification events
        self.notificationCenter.delegate = self

        // Register notification categories
        registerNotificationCategories()

        // Check initial authorization status
        Task {
            await checkAuthorizationStatus()
        }
    }

    // MARK: - Public Methods

    /// Requests notification authorization from the user
    /// - Returns: Whether authorization was granted
    @discardableResult
    func requestAuthorization() async throws -> Bool {
        do {
            let granted = try await notificationCenter.requestAuthorization(
                options: [.alert, .sound, .badge]
            )

            // Update authorization status
            await checkAuthorizationStatus()

            // Enable notifications if granted
            if granted {
                notificationsEnabled = true
            }

            // Post notification for UI updates
            NotificationCenter.default.post(
                name: .notificationAuthorizationChanged,
                object: self,
                userInfo: ["granted": granted]
            )

            return granted
        } catch {
            throw NotificationError.authorizationFailed(error.localizedDescription)
        }
    }

    /// Schedules a notification
    /// - Parameter notification: The notification to schedule
    func scheduleNotification(_ notification: LAYANotification) async throws {
        // Check if notifications are enabled
        guard notificationsEnabled else {
            throw NotificationError.notificationsDisabled
        }

        // Check if this category is enabled
        guard enabledCategories.contains(notification.category) else {
            throw NotificationError.categoryDisabled(notification.category)
        }

        // Check authorization
        guard authorizationStatus == .authorized else {
            throw NotificationError.notAuthorized
        }

        // Create notification content
        let content = UNMutableNotificationContent()
        content.title = notification.title
        content.body = notification.body

        if let subtitle = notification.subtitle {
            content.subtitle = subtitle
        }

        if !notification.silent {
            content.sound = notification.sound
        }

        if let badge = notification.badge {
            content.badge = NSNumber(value: badge)
        }

        content.categoryIdentifier = notification.category.rawValue

        // Add user info
        var userInfo = notification.userInfo
        userInfo["notificationId"] = notification.id
        userInfo["category"] = notification.category.rawValue
        content.userInfo = userInfo

        // Create trigger
        let trigger: UNNotificationTrigger?
        if let triggerDate = notification.triggerDate {
            let components = Calendar.current.dateComponents(
                [.year, .month, .day, .hour, .minute, .second],
                from: triggerDate
            )
            trigger = UNCalendarNotificationTrigger(dateMatching: components, repeats: false)
        } else {
            // Immediate notification (1 second delay)
            trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
        }

        // Create request
        let request = UNNotificationRequest(
            identifier: notification.id,
            content: content,
            trigger: trigger
        )

        // Schedule the notification
        do {
            try await notificationCenter.add(request)

            // Post notification for tracking
            NotificationCenter.default.post(
                name: .notificationScheduled,
                object: self,
                userInfo: ["notification": notification]
            )
        } catch {
            throw NotificationError.schedulingFailed(error.localizedDescription)
        }
    }

    /// Schedules a notification for a dashboard alert
    /// - Parameter alert: The dashboard alert to notify about
    func scheduleNotification(for alert: DashboardAlert) async throws {
        // Skip if already acknowledged
        guard !alert.isAcknowledged else { return }

        // Create notification from alert
        let notification = LAYANotification(
            id: "alert-\(alert.id)",
            title: alert.title,
            body: alert.message,
            subtitle: alert.category.displayName,
            category: NotificationCategory(from: alert.category),
            triggerDate: nil, // Immediate
            silent: alert.severity == .info,
            sound: soundForSeverity(alert.severity),
            badge: nil,
            userInfo: [
                "alertId": alert.id,
                "severity": alert.severity.rawValue,
                "category": alert.category.rawValue,
                "relatedEntityId": alert.relatedEntityId ?? "",
                "relatedEntityType": alert.relatedEntityType ?? ""
            ]
        )

        try await scheduleNotification(notification)
    }

    /// Cancels a pending notification by identifier
    /// - Parameter identifier: The notification identifier to cancel
    func cancelNotification(identifier: String) {
        notificationCenter.removePendingNotificationRequests(withIdentifiers: [identifier])
    }

    /// Cancels all pending notifications
    func cancelAllNotifications() {
        notificationCenter.removeAllPendingNotificationRequests()
    }

    /// Gets all pending notification requests
    /// - Returns: Array of pending notification requests
    func getPendingNotifications() async -> [UNNotificationRequest] {
        await notificationCenter.pendingNotificationRequests()
    }

    /// Gets all delivered notifications
    /// - Returns: Array of delivered notifications
    func getDeliveredNotifications() async -> [UNNotification] {
        await notificationCenter.deliveredNotifications()
    }

    /// Removes delivered notifications by identifiers
    /// - Parameter identifiers: Array of notification identifiers to remove
    func removeDeliveredNotifications(identifiers: [String]) {
        notificationCenter.removeDeliveredNotifications(withIdentifiers: identifiers)
    }

    /// Removes all delivered notifications
    func removeAllDeliveredNotifications() {
        notificationCenter.removeAllDeliveredNotifications()
    }

    // MARK: - Category Management

    /// Enables or disables a notification category
    /// - Parameters:
    ///   - category: The category to update
    ///   - enabled: Whether to enable or disable
    func setCategoryEnabled(_ category: NotificationCategory, enabled: Bool) {
        if enabled {
            enabledCategories.insert(category)
        } else {
            enabledCategories.remove(category)
        }

        // Persist to UserDefaults
        saveCategoryPreferences()
    }

    /// Checks if a category is enabled
    /// - Parameter category: The category to check
    /// - Returns: Whether the category is enabled
    func isCategoryEnabled(_ category: NotificationCategory) -> Bool {
        enabledCategories.contains(category)
    }

    /// Gets all enabled categories
    /// - Returns: Set of enabled categories
    func getEnabledCategories() -> Set<NotificationCategory> {
        enabledCategories
    }

    // MARK: - Convenience Methods

    /// Schedules a notification for low enrollment
    func notifyLowEnrollment(currentCount: Int, capacity: Int) async throws {
        let rate = Double(currentCount) / Double(capacity) * 100
        let notification = LAYANotification(
            id: "low-enrollment-\(Date().timeIntervalSince1970)",
            title: String(localized: "Low Enrollment Alert"),
            body: String(localized: "Current enrollment is \(Int(rate))% (\(currentCount)/\(capacity))"),
            category: .enrollment,
            sound: .default
        )
        try await scheduleNotification(notification)
    }

    /// Schedules a notification for a payment due reminder
    func notifyPaymentDue(familyName: String, amount: Double, dueDate: Date) async throws {
        let notification = LAYANotification(
            id: "payment-due-\(familyName)-\(dueDate.timeIntervalSince1970)",
            title: String(localized: "Payment Due"),
            body: String(localized: "\(familyName) has a payment of \(amount.asCurrency) due on \(dueDate.displayDate)"),
            category: .finance,
            triggerDate: Calendar.current.date(byAdding: .day, value: -3, to: dueDate), // 3 days before
            sound: .default
        )
        try await scheduleNotification(notification)
    }

    /// Schedules a notification for a certification expiring
    func notifyCertificationExpiring(staffName: String, certification: String, expiryDate: Date) async throws {
        let notification = LAYANotification(
            id: "cert-expiring-\(staffName)-\(certification)",
            title: String(localized: "Certification Expiring"),
            body: String(localized: "\(staffName)'s \(certification) expires on \(expiryDate.displayDate)"),
            category: .compliance,
            triggerDate: Calendar.current.date(byAdding: .day, value: -14, to: expiryDate), // 14 days before
            sound: .default
        )
        try await scheduleNotification(notification)
    }

    /// Schedules a notification for staff ratio warning
    func notifyStaffRatioWarning(classroom: String, currentRatio: String, requiredRatio: String) async throws {
        let notification = LAYANotification(
            id: "staff-ratio-\(classroom)-\(Date().timeIntervalSince1970)",
            title: String(localized: "Staff Ratio Warning"),
            body: String(localized: "\(classroom) has staff ratio \(currentRatio), required: \(requiredRatio)"),
            category: .staffing,
            sound: UNNotificationSound.defaultCritical
        )
        try await scheduleNotification(notification)
    }

    /// Schedules a sync completion notification
    func notifySyncCompleted(itemsSynced: Int) async throws {
        let notification = LAYANotification(
            id: "sync-\(Date().timeIntervalSince1970)",
            title: String(localized: "Sync Completed"),
            body: String(localized: "\(itemsSynced) items synchronized successfully"),
            category: .sync,
            silent: true,
            sound: nil
        )
        try await scheduleNotification(notification)
    }

    // MARK: - Private Methods

    /// Checks and updates the current authorization status
    private func checkAuthorizationStatus() async {
        let settings = await notificationCenter.notificationSettings()
        authorizationStatus = settings.authorizationStatus
    }

    /// Registers notification categories with the system
    private func registerNotificationCategories() {
        // Create action for viewing details
        let viewAction = UNNotificationAction(
            identifier: "VIEW_ACTION",
            title: String(localized: "View"),
            options: [.foreground]
        )

        // Create action for dismissing
        let dismissAction = UNNotificationAction(
            identifier: "DISMISS_ACTION",
            title: String(localized: "Dismiss"),
            options: [.destructive]
        )

        // Create action for acknowledging alerts
        let acknowledgeAction = UNNotificationAction(
            identifier: "ACKNOWLEDGE_ACTION",
            title: String(localized: "Acknowledge"),
            options: []
        )

        // Create categories with actions
        let categories = NotificationCategory.allCases.map { category -> UNNotificationCategory in
            var actions: [UNNotificationAction] = [viewAction, dismissAction]

            // Add acknowledge action for alert categories
            switch category {
            case .enrollment, .attendance, .staffing, .compliance, .finance:
                actions.insert(acknowledgeAction, at: 1)
            default:
                break
            }

            return UNNotificationCategory(
                identifier: category.rawValue,
                actions: actions,
                intentIdentifiers: [],
                options: [.customDismissAction]
            )
        }

        notificationCenter.setNotificationCategories(Set(categories))
    }

    /// Returns the appropriate sound for an alert severity
    private func soundForSeverity(_ severity: AlertSeverity) -> UNNotificationSound {
        switch severity {
        case .critical:
            return UNNotificationSound.defaultCritical
        case .warning:
            return UNNotificationSound.default
        case .info:
            return UNNotificationSound.default
        }
    }

    /// Saves category preferences to UserDefaults
    private func saveCategoryPreferences() {
        let enabledRawValues = enabledCategories.map { $0.rawValue }
        UserDefaults.standard.set(enabledRawValues, forKey: "enabledNotificationCategories")
    }

    /// Loads category preferences from UserDefaults
    private func loadCategoryPreferences() {
        if let rawValues = UserDefaults.standard.stringArray(forKey: "enabledNotificationCategories") {
            enabledCategories = Set(rawValues.compactMap { NotificationCategory(rawValue: $0) })
        }
    }
}

// MARK: - UNUserNotificationCenterDelegate

extension NotificationService: UNUserNotificationCenterDelegate {

    /// Called when a notification is about to be presented while app is in foreground
    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification
    ) async -> UNNotificationPresentationOptions {
        // Show notification even when app is in foreground
        return [.banner, .sound, .badge]
    }

    /// Called when user interacts with a notification
    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse
    ) async {
        let userInfo = response.notification.request.content.userInfo
        let actionIdentifier = response.actionIdentifier

        // Post notification for app to handle
        await MainActor.run {
            NotificationCenter.default.post(
                name: .notificationActionReceived,
                object: nil,
                userInfo: [
                    "actionIdentifier": actionIdentifier,
                    "notificationUserInfo": userInfo
                ]
            )
        }

        // Handle specific actions
        switch actionIdentifier {
        case "VIEW_ACTION":
            // Navigate to related content
            if let alertId = userInfo["alertId"] as? String {
                await MainActor.run {
                    NotificationCenter.default.post(
                        name: .navigateToAlert,
                        object: nil,
                        userInfo: ["alertId": alertId]
                    )
                }
            }

        case "ACKNOWLEDGE_ACTION":
            // Mark alert as acknowledged
            if let alertId = userInfo["alertId"] as? String {
                await MainActor.run {
                    NotificationCenter.default.post(
                        name: .acknowledgeAlert,
                        object: nil,
                        userInfo: ["alertId": alertId]
                    )
                }
            }

        case "DISMISS_ACTION", UNNotificationDismissActionIdentifier:
            // Notification was dismissed
            break

        case UNNotificationDefaultActionIdentifier:
            // User tapped the notification
            if let alertId = userInfo["alertId"] as? String {
                await MainActor.run {
                    NotificationCenter.default.post(
                        name: .navigateToAlert,
                        object: nil,
                        userInfo: ["alertId": alertId]
                    )
                }
            }

        default:
            break
        }
    }
}

// MARK: - Notification Error

/// Errors that can occur during notification operations
enum NotificationError: LocalizedError {
    case notAuthorized
    case notificationsDisabled
    case categoryDisabled(NotificationCategory)
    case authorizationFailed(String)
    case schedulingFailed(String)

    var errorDescription: String? {
        switch self {
        case .notAuthorized:
            return String(localized: "Notification permission not granted")
        case .notificationsDisabled:
            return String(localized: "Notifications are disabled in settings")
        case .categoryDisabled(let category):
            return String(localized: "Notifications for \(category.displayName) are disabled")
        case .authorizationFailed(let message):
            return String(localized: "Failed to request notification permission: \(message)")
        case .schedulingFailed(let message):
            return String(localized: "Failed to schedule notification: \(message)")
        }
    }
}

// MARK: - Notification Names

extension Notification.Name {
    /// Posted when notification authorization status changes
    static let notificationAuthorizationChanged = Notification.Name("notificationAuthorizationChanged")

    /// Posted when a notification is scheduled
    static let notificationScheduled = Notification.Name("notificationScheduled")

    /// Posted when a notification action is received
    static let notificationActionReceived = Notification.Name("notificationActionReceived")

    /// Posted to navigate to an alert
    static let navigateToAlert = Notification.Name("navigateToAlert")

    /// Posted to acknowledge an alert
    static let acknowledgeAlert = Notification.Name("acknowledgeAlert")
}

// MARK: - Preview Support

#if DEBUG
extension NotificationService {

    /// Creates a mock notification service with authorized status for previews
    static var previewAuthorized: NotificationService {
        let service = NotificationService()
        service.authorizationStatus = .authorized
        service.notificationsEnabled = true
        return service
    }

    /// Creates a mock notification service with denied status for previews
    static var previewDenied: NotificationService {
        let service = NotificationService()
        service.authorizationStatus = .denied
        service.notificationsEnabled = false
        return service
    }

    /// Creates a mock notification service with not determined status for previews
    static var previewNotDetermined: NotificationService {
        let service = NotificationService()
        service.authorizationStatus = .notDetermined
        service.notificationsEnabled = false
        return service
    }
}

extension LAYANotification {

    /// Sample notification for previews
    static var preview: LAYANotification {
        LAYANotification(
            title: "Staff Certification Expiring",
            body: "Marie Dupont's First Aid certification expires in 14 days",
            category: .compliance
        )
    }

    /// Sample critical notification for previews
    static var previewCritical: LAYANotification {
        LAYANotification(
            title: "Staff Ratio Warning",
            body: "Sunflowers classroom is below required staff-to-child ratio",
            category: .staffing,
            sound: UNNotificationSound.defaultCritical
        )
    }

    /// Sample finance notification for previews
    static var previewFinance: LAYANotification {
        LAYANotification(
            title: "Payment Due",
            body: "The Roy family has a payment of $450.00 due on Feb 15, 2026",
            category: .finance
        )
    }
}
#endif
