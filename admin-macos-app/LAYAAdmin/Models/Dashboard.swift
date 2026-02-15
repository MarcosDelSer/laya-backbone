//
//  Dashboard.swift
//  LAYAAdmin
//
//  Dashboard domain models for the LAYA Admin application.
//  Provides aggregated views for facility directors including
//  KPIs, enrollment forecasts, and compliance summaries.
//

import Foundation

// MARK: - Dashboard Summary

/// Summary metrics for the dashboard overview.
/// Provides high-level KPIs for quick facility status assessment.
struct DashboardSummary: Codable, Equatable {

    // MARK: - Properties

    /// Total number of enrolled children
    let totalEnrolled: Int

    /// Total facility capacity
    let totalCapacity: Int

    /// Enrollment rate as percentage (0-100)
    let enrollmentRate: Double

    /// Average daily attendance percentage (0-100)
    let averageAttendance: Double

    /// Overall compliance score (0-100)
    let complianceScore: Double

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case totalEnrolled = "total_enrolled"
        case totalCapacity = "total_capacity"
        case enrollmentRate = "enrollment_rate"
        case averageAttendance = "average_attendance"
        case complianceScore = "compliance_score"
    }

    // MARK: - Computed Properties

    /// Formatted enrollment rate string
    var formattedEnrollmentRate: String {
        enrollmentRate.asPercentage
    }

    /// Formatted attendance rate string
    var formattedAttendance: String {
        averageAttendance.asPercentage
    }

    /// Formatted compliance score string
    var formattedComplianceScore: String {
        complianceScore.asPercentage
    }

    /// Available spots (capacity - enrolled)
    var availableSpots: Int {
        max(0, totalCapacity - totalEnrolled)
    }

    /// Enrollment status level based on rate
    var enrollmentLevel: DashboardLevel {
        if enrollmentRate >= 90 {
            return .high
        } else if enrollmentRate >= 70 {
            return .medium
        } else {
            return .low
        }
    }

    /// Attendance status level based on average
    var attendanceLevel: DashboardLevel {
        if averageAttendance >= 85 {
            return .high
        } else if averageAttendance >= 70 {
            return .medium
        } else {
            return .low
        }
    }

    /// Compliance status level based on score
    var complianceLevel: DashboardLevel {
        if complianceScore >= 90 {
            return .high
        } else if complianceScore >= 70 {
            return .medium
        } else {
            return .low
        }
    }
}

// MARK: - Dashboard Summary Extensions

extension DashboardSummary {

    /// Creates a sample dashboard summary for previews
    static var preview: DashboardSummary {
        DashboardSummary(
            totalEnrolled: 48,
            totalCapacity: 52,
            enrollmentRate: 92.3,
            averageAttendance: 87.5,
            complianceScore: 95.0
        )
    }

    /// Creates a sample summary with warnings for previews
    static var previewWithWarnings: DashboardSummary {
        DashboardSummary(
            totalEnrolled: 35,
            totalCapacity: 52,
            enrollmentRate: 67.3,
            averageAttendance: 72.0,
            complianceScore: 78.0
        )
    }
}

// MARK: - Dashboard Level

/// Status level for dashboard metrics.
enum DashboardLevel: String, Codable, CaseIterable {
    case high = "high"
    case medium = "medium"
    case low = "low"

    var displayName: String {
        switch self {
        case .high:
            return String(localized: "Good")
        case .medium:
            return String(localized: "Moderate")
        case .low:
            return String(localized: "Needs Attention")
        }
    }

    /// Color name for this level
    var color: String {
        switch self {
        case .high:
            return "green"
        case .medium:
            return "orange"
        case .low:
            return "red"
        }
    }

    /// SF Symbol name for this level
    var iconName: String {
        switch self {
        case .high:
            return "checkmark.circle.fill"
        case .medium:
            return "exclamationmark.triangle.fill"
        case .low:
            return "xmark.circle.fill"
        }
    }
}

// MARK: - Dashboard Alert

/// An alert item for the dashboard requiring attention.
struct DashboardAlert: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the alert
    let id: String

    /// Alert title
    let title: String

    /// Alert message/description
    let message: String

    /// Severity level of the alert
    let severity: AlertSeverity

    /// Category of the alert
    let category: AlertCategory

    /// Related entity ID (optional)
    let relatedEntityId: String?

    /// Related entity type (optional)
    let relatedEntityType: String?

    /// When the alert was created
    let createdAt: Date

    /// Whether the alert has been acknowledged
    let isAcknowledged: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case title
        case message
        case severity
        case category
        case relatedEntityId = "related_entity_id"
        case relatedEntityType = "related_entity_type"
        case createdAt = "created_at"
        case isAcknowledged = "is_acknowledged"
    }

    // MARK: - Computed Properties

    /// Formatted creation date string
    var formattedDate: String {
        createdAt.displayDate
    }

    /// Color name for the alert severity
    var severityColor: String {
        severity.color
    }

    /// Icon name for the alert category
    var categoryIcon: String {
        category.iconName
    }
}

// MARK: - Dashboard Alert Extensions

extension DashboardAlert {

    /// Creates a sample alert for previews
    static var preview: DashboardAlert {
        DashboardAlert(
            id: "alert-1",
            title: "Staff Certification Expiring",
            message: "Marie Dupont's First Aid certification expires in 14 days",
            severity: .warning,
            category: .compliance,
            relatedEntityId: "staff-1",
            relatedEntityType: "staff",
            createdAt: Date(),
            isAcknowledged: false
        )
    }

    /// Creates a sample critical alert for previews
    static var previewCritical: DashboardAlert {
        DashboardAlert(
            id: "alert-2",
            title: "Staff Ratio Below Minimum",
            message: "Sunflowers classroom is below required staff-to-child ratio",
            severity: .critical,
            category: .staffing,
            relatedEntityId: "classroom-1",
            relatedEntityType: "classroom",
            createdAt: Date(),
            isAcknowledged: false
        )
    }

    /// Creates a sample info alert for previews
    static var previewInfo: DashboardAlert {
        DashboardAlert(
            id: "alert-3",
            title: "New Enrollment Application",
            message: "New application received from the Roy family",
            severity: .info,
            category: .enrollment,
            relatedEntityId: "application-1",
            relatedEntityType: "application",
            createdAt: Date(),
            isAcknowledged: false
        )
    }
}

// MARK: - Alert Severity

/// Severity levels for dashboard alerts.
enum AlertSeverity: String, Codable, CaseIterable {
    case info = "info"
    case warning = "warning"
    case critical = "critical"

    var displayName: String {
        switch self {
        case .info:
            return String(localized: "Information")
        case .warning:
            return String(localized: "Warning")
        case .critical:
            return String(localized: "Critical")
        }
    }

    /// Color name for this severity
    var color: String {
        switch self {
        case .info:
            return "blue"
        case .warning:
            return "orange"
        case .critical:
            return "red"
        }
    }

    /// SF Symbol name for this severity
    var iconName: String {
        switch self {
        case .info:
            return "info.circle.fill"
        case .warning:
            return "exclamationmark.triangle.fill"
        case .critical:
            return "exclamationmark.octagon.fill"
        }
    }

    /// Sort order (critical first)
    var sortOrder: Int {
        switch self {
        case .critical:
            return 0
        case .warning:
            return 1
        case .info:
            return 2
        }
    }
}

// MARK: - Alert Category

/// Categories for dashboard alerts.
enum AlertCategory: String, Codable, CaseIterable {
    case enrollment = "enrollment"
    case attendance = "attendance"
    case staffing = "staffing"
    case compliance = "compliance"
    case finance = "finance"
    case general = "general"

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
        }
    }

    /// SF Symbol name for this category
    var iconName: String {
        switch self {
        case .enrollment:
            return "person.badge.plus"
        case .attendance:
            return "calendar.badge.clock"
        case .staffing:
            return "person.3.fill"
        case .compliance:
            return "checkmark.shield.fill"
        case .finance:
            return "dollarsign.circle.fill"
        case .general:
            return "bell.fill"
        }
    }
}

// MARK: - Dashboard Response

/// Aggregated dashboard response with all key metrics.
/// Provides a comprehensive view for facility directors including
/// KPIs, enrollment forecast summary, and compliance alerts.
struct DashboardResponse: Codable, Equatable {

    // MARK: - Properties

    /// High-level summary metrics
    let summary: DashboardSummary

    /// List of key performance indicators
    let kpis: [KPIMetric]

    /// Summary of enrollment forecast
    let forecastSummary: ForecastData

    /// Summary of compliance status
    let complianceSummary: ComplianceListResponse

    /// List of items requiring attention
    let alerts: [String]

    /// When the dashboard data was generated
    let generatedAt: Date

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case summary
        case kpis
        case forecastSummary = "forecast_summary"
        case complianceSummary = "compliance_summary"
        case alerts
        case generatedAt = "generated_at"
    }

    // MARK: - Computed Properties

    /// Whether there are any alerts requiring attention
    var hasAlerts: Bool {
        !alerts.isEmpty
    }

    /// Number of alerts
    var alertCount: Int {
        alerts.count
    }

    /// Overall health status based on summary metrics
    var overallHealth: DashboardLevel {
        let enrollmentOk = summary.enrollmentRate >= 70
        let attendanceOk = summary.averageAttendance >= 70
        let complianceOk = summary.complianceScore >= 70

        if enrollmentOk && attendanceOk && complianceOk {
            if summary.enrollmentRate >= 90 && summary.averageAttendance >= 85 && summary.complianceScore >= 90 {
                return .high
            }
            return .medium
        }
        return .low
    }
}

// MARK: - Dashboard Response Extensions

extension DashboardResponse {

    /// Creates a sample dashboard response for previews
    static var preview: DashboardResponse {
        DashboardResponse(
            summary: .preview,
            kpis: [.preview, .previewAttendance, .previewRevenue, .previewStaffing],
            forecastSummary: .preview,
            complianceSummary: .preview,
            alerts: ["Staff certification expiring in 14 days"],
            generatedAt: Date()
        )
    }

    /// Creates a sample dashboard with multiple alerts for previews
    static var previewWithAlerts: DashboardResponse {
        DashboardResponse(
            summary: .previewWithWarnings,
            kpis: [.preview, .previewAttendance],
            forecastSummary: .previewLimitedData,
            complianceSummary: .previewWithWarnings,
            alerts: [
                "Staff certification expiring in 14 days",
                "Enrollment below target for current month",
                "3 invoices overdue by more than 30 days"
            ],
            generatedAt: Date()
        )
    }
}

// MARK: - Dashboard Card Data

/// Data for a dashboard metric card.
struct DashboardCardData: Identifiable, Equatable {

    /// Unique identifier
    let id: String

    /// Card title
    let title: String

    /// Primary value to display
    let value: String

    /// Secondary label or description
    let subtitle: String?

    /// Trend direction (optional)
    let trend: TrendDirection?

    /// Change value (e.g., "+5%")
    let changeValue: String?

    /// Icon name (SF Symbol)
    let iconName: String

    /// Color name for the card
    let color: String

    /// Status level
    let level: DashboardLevel?

    // MARK: - Computed Properties

    /// Whether to show trend indicator
    var showTrend: Bool {
        trend != nil && changeValue != nil
    }
}

// MARK: - Dashboard Card Data Extensions

extension DashboardCardData {

    /// Creates enrollment card data from dashboard summary
    static func enrollment(from summary: DashboardSummary) -> DashboardCardData {
        DashboardCardData(
            id: "enrollment",
            title: String(localized: "Enrollment"),
            value: "\(summary.totalEnrolled)/\(summary.totalCapacity)",
            subtitle: "\(summary.availableSpots) spots available",
            trend: nil,
            changeValue: nil,
            iconName: "person.2.fill",
            color: summary.enrollmentLevel.color,
            level: summary.enrollmentLevel
        )
    }

    /// Creates attendance card data from dashboard summary
    static func attendance(from summary: DashboardSummary) -> DashboardCardData {
        DashboardCardData(
            id: "attendance",
            title: String(localized: "Attendance"),
            value: summary.formattedAttendance,
            subtitle: String(localized: "Daily average"),
            trend: nil,
            changeValue: nil,
            iconName: "calendar.badge.clock",
            color: summary.attendanceLevel.color,
            level: summary.attendanceLevel
        )
    }

    /// Creates compliance card data from dashboard summary
    static func compliance(from summary: DashboardSummary) -> DashboardCardData {
        DashboardCardData(
            id: "compliance",
            title: String(localized: "Compliance"),
            value: summary.formattedComplianceScore,
            subtitle: String(localized: "Overall score"),
            trend: nil,
            changeValue: nil,
            iconName: "checkmark.shield.fill",
            color: summary.complianceLevel.color,
            level: summary.complianceLevel
        )
    }

    /// Creates sample card data for previews
    static var preview: DashboardCardData {
        DashboardCardData(
            id: "preview",
            title: "Enrollment",
            value: "48/52",
            subtitle: "4 spots available",
            trend: .up,
            changeValue: "+3",
            iconName: "person.2.fill",
            color: "green",
            level: .high
        )
    }
}

// MARK: - Quick Stats

/// Quick statistics for the dashboard header.
struct QuickStats: Codable, Equatable {

    /// Number of children present today
    let childrenPresentToday: Int

    /// Number of staff on duty today
    let staffOnDutyToday: Int

    /// Number of pending actions/tasks
    let pendingActions: Int

    /// Number of unread messages
    let unreadMessages: Int

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case childrenPresentToday = "children_present_today"
        case staffOnDutyToday = "staff_on_duty_today"
        case pendingActions = "pending_actions"
        case unreadMessages = "unread_messages"
    }
}

// MARK: - Quick Stats Extensions

extension QuickStats {

    /// Creates sample quick stats for previews
    static var preview: QuickStats {
        QuickStats(
            childrenPresentToday: 42,
            staffOnDutyToday: 8,
            pendingActions: 5,
            unreadMessages: 3
        )
    }
}

// MARK: - Dashboard Refresh Request

/// Request parameters for refreshing dashboard data.
struct DashboardRefreshRequest: Codable {

    /// Facility ID for facility-specific dashboard
    let facilityId: String?

    /// Whether to include forecast data
    let includeForecast: Bool

    /// Whether to include compliance data
    let includeCompliance: Bool

    /// Whether to include alerts
    let includeAlerts: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case facilityId = "facility_id"
        case includeForecast = "include_forecast"
        case includeCompliance = "include_compliance"
        case includeAlerts = "include_alerts"
    }
}
