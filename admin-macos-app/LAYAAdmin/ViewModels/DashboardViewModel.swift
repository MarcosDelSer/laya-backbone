//
//  DashboardViewModel.swift
//  LAYAAdmin
//
//  ViewModel for the main dashboard in the LAYA Admin application.
//  Handles loading and aggregating data from both Gibbon CMS and AI Service
//  to provide a comprehensive overview for facility directors.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Dashboard ViewModel

/// ViewModel for managing dashboard state and data loading.
///
/// This ViewModel acts as a bridge between the UI layer and multiple backend services,
/// providing observable state for the main dashboard view.
///
/// Features:
/// - Aggregates data from Gibbon CMS and AI Service
/// - Provides KPIs, alerts, and summary metrics
/// - Supports pull-to-refresh and auto-refresh
/// - Graceful error handling with partial data display
/// - Quick stats for header section
@MainActor
final class DashboardViewModel: ObservableObject {

    // MARK: - Published Properties

    /// Dashboard summary with high-level metrics
    @Published private(set) var summary: DashboardSummary?

    /// List of key performance indicators
    @Published private(set) var kpis: [KPIMetric] = []

    /// Enrollment forecast data
    @Published private(set) var forecast: ForecastData?

    /// Compliance status summary
    @Published private(set) var complianceSummary: ComplianceListResponse?

    /// List of dashboard alerts requiring attention
    @Published private(set) var alerts: [DashboardAlert] = []

    /// Quick stats for the header section
    @Published private(set) var quickStats: QuickStats?

    /// Finance summary from Gibbon
    @Published private(set) var financeSummary: FinanceSummary?

    /// Dashboard data from Gibbon CMS
    @Published private(set) var gibbonDashboard: DashboardData?

    /// Whether a dashboard load is in progress
    @Published private(set) var isLoading = false

    /// Whether initial data has been loaded
    @Published private(set) var hasLoaded = false

    /// Current error, if any
    @Published private(set) var error: Error?

    /// Whether the error alert should be shown
    @Published var showError = false

    /// Last time the dashboard was refreshed
    @Published private(set) var lastRefreshed: Date?

    /// Whether the AI Service is available
    @Published private(set) var aiServiceAvailable = false

    // MARK: - Refresh Control

    /// Whether auto-refresh is enabled
    @Published var autoRefreshEnabled = true

    /// Auto-refresh interval in seconds (default: 5 minutes)
    let autoRefreshInterval: TimeInterval = 300

    // MARK: - Computed Properties

    /// Whether any data is available to display
    var hasData: Bool {
        summary != nil || !kpis.isEmpty || gibbonDashboard != nil
    }

    /// Whether there are any alerts
    var hasAlerts: Bool {
        !alerts.isEmpty
    }

    /// Number of unacknowledged alerts
    var unacknowledgedAlertCount: Int {
        alerts.filter { !$0.isAcknowledged }.count
    }

    /// Critical alerts requiring immediate attention
    var criticalAlerts: [DashboardAlert] {
        alerts.filter { $0.severity == .critical && !$0.isAcknowledged }
    }

    /// Warning alerts
    var warningAlerts: [DashboardAlert] {
        alerts.filter { $0.severity == .warning && !$0.isAcknowledged }
    }

    /// Info alerts
    var infoAlerts: [DashboardAlert] {
        alerts.filter { $0.severity == .info && !$0.isAcknowledged }
    }

    /// Sorted alerts (critical first, then warning, then info)
    var sortedAlerts: [DashboardAlert] {
        alerts
            .filter { !$0.isAcknowledged }
            .sorted { $0.severity.sortOrder < $1.severity.sortOrder }
    }

    /// Overall dashboard health level
    var overallHealth: DashboardLevel {
        guard let summary = summary else {
            return .medium
        }

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

    /// Card data generated from summary
    var dashboardCards: [DashboardCardData] {
        guard let summary = summary else { return [] }

        return [
            .enrollment(from: summary),
            .attendance(from: summary),
            .compliance(from: summary)
        ]
    }

    /// Formatted last refresh time
    var formattedLastRefresh: String {
        guard let date = lastRefreshed else {
            return String(localized: "Never")
        }

        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        return formatter.localizedString(for: date, relativeTo: Date())
    }

    // MARK: - Private Properties

    /// Gibbon CMS client
    private let gibbonClient: GibbonClient

    /// AI Service client
    private let aiServiceClient: AIServiceClient

    /// Combine cancellables for subscriptions
    private var cancellables = Set<AnyCancellable>()

    /// Auto-refresh timer
    private var refreshTimer: Timer?

    // MARK: - Initialization

    /// Creates a new DashboardViewModel
    /// - Parameters:
    ///   - gibbonClient: The Gibbon client to use (defaults to shared instance)
    ///   - aiServiceClient: The AI Service client to use (defaults to shared instance)
    init(
        gibbonClient: GibbonClient = .shared,
        aiServiceClient: AIServiceClient = .shared
    ) {
        self.gibbonClient = gibbonClient
        self.aiServiceClient = aiServiceClient
    }

    deinit {
        stopAutoRefresh()
    }

    // MARK: - Public Methods

    /// Loads all dashboard data from both services.
    /// This is the primary method to refresh the dashboard.
    func loadDashboard() async {
        isLoading = true
        error = nil
        showError = false

        // Run both API calls concurrently
        await withTaskGroup(of: Void.self) { group in
            // Load from Gibbon CMS
            group.addTask { [weak self] in
                await self?.loadGibbonData()
            }

            // Load from AI Service
            group.addTask { [weak self] in
                await self?.loadAIServiceData()
            }
        }

        hasLoaded = true
        lastRefreshed = Date()
        isLoading = false
    }

    /// Refreshes dashboard data (alias for loadDashboard for semantic clarity)
    func refresh() async {
        await loadDashboard()
    }

    /// Loads only quick statistics (faster, for header updates)
    func loadQuickStats() async {
        do {
            let stats = try await aiServiceClient.fetchQuickAnalytics()
            self.quickStats = QuickStats(
                childrenPresentToday: stats.todayAttendance,
                staffOnDutyToday: gibbonDashboard?.activeStaff ?? 0,
                pendingActions: stats.totalIssues,
                unreadMessages: 0
            )
        } catch {
            // Quick stats are non-critical, fail silently
        }
    }

    /// Loads only KPI data
    func loadKPIs() async {
        do {
            let response = try await aiServiceClient.fetchKPIs()
            self.kpis = response.metrics
        } catch {
            // KPIs failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads only alerts
    func loadAlerts() async {
        // Try to get alerts from Gibbon
        if let gibbonAlerts = gibbonDashboard?.alerts {
            self.alerts = gibbonAlerts
        }

        // Supplement with compliance issues from AI Service
        do {
            let issues = try await aiServiceClient.fetchComplianceIssues()
            let complianceAlerts = issues.map { issue in
                DashboardAlert(
                    id: issue.id,
                    title: issue.title,
                    message: issue.details,
                    severity: issue.status == .violation ? .critical : .warning,
                    category: .compliance,
                    relatedEntityId: nil,
                    relatedEntityType: nil,
                    createdAt: issue.lastCheckedAt,
                    isAcknowledged: false
                )
            }

            // Merge with existing alerts, avoiding duplicates
            let existingIds = Set(self.alerts.map { $0.id })
            let newAlerts = complianceAlerts.filter { !existingIds.contains($0.id) }
            self.alerts.append(contentsOf: newAlerts)
        } catch {
            // Compliance alerts are supplementary, fail silently
        }
    }

    /// Acknowledges an alert
    /// - Parameter alertId: The ID of the alert to acknowledge
    func acknowledgeAlert(alertId: String) {
        if let index = alerts.firstIndex(where: { $0.id == alertId }) {
            // Create a new alert with acknowledged status
            let alert = alerts[index]
            let acknowledgedAlert = DashboardAlert(
                id: alert.id,
                title: alert.title,
                message: alert.message,
                severity: alert.severity,
                category: alert.category,
                relatedEntityId: alert.relatedEntityId,
                relatedEntityType: alert.relatedEntityType,
                createdAt: alert.createdAt,
                isAcknowledged: true
            )
            alerts[index] = acknowledgedAlert
        }
    }

    /// Starts auto-refresh timer
    func startAutoRefresh() {
        guard autoRefreshEnabled else { return }
        stopAutoRefresh()

        refreshTimer = Timer.scheduledTimer(
            withTimeInterval: autoRefreshInterval,
            repeats: true
        ) { [weak self] _ in
            Task { @MainActor [weak self] in
                await self?.loadDashboard()
            }
        }
    }

    /// Stops auto-refresh timer
    func stopAutoRefresh() {
        refreshTimer?.invalidate()
        refreshTimer = nil
    }

    /// Toggles auto-refresh
    func toggleAutoRefresh() {
        autoRefreshEnabled.toggle()
        if autoRefreshEnabled {
            startAutoRefresh()
        } else {
            stopAutoRefresh()
        }
    }

    /// Clears the current error
    func clearError() {
        error = nil
        showError = false
    }

    /// Checks if AI Service is available
    func checkAIServiceAvailability() async {
        aiServiceAvailable = await aiServiceClient.isServiceAvailable()
    }

    // MARK: - Private Methods

    /// Loads dashboard data from Gibbon CMS
    private func loadGibbonData() async {
        do {
            let dashboard = try await gibbonClient.fetchDashboard()
            self.gibbonDashboard = dashboard

            // Extract alerts if available
            if let gibbonAlerts = dashboard.alerts {
                self.alerts = gibbonAlerts
            }

            // Extract finance summary if available
            if let finance = dashboard.financeSummary {
                self.financeSummary = finance
            }
        } catch {
            // Gibbon is critical, set error
            self.error = error
            self.showError = true
        }
    }

    /// Loads dashboard data from AI Service
    private func loadAIServiceData() async {
        // First check if service is available
        aiServiceAvailable = await aiServiceClient.isServiceAvailable()

        guard aiServiceAvailable else {
            // AI Service unavailable is not a critical error
            return
        }

        do {
            // Fetch comprehensive dashboard from AI Service
            let dashboardResponse = try await aiServiceClient.fetchDashboard()

            self.summary = dashboardResponse.summary
            self.kpis = dashboardResponse.kpis
            self.forecast = dashboardResponse.forecastSummary
            self.complianceSummary = dashboardResponse.complianceSummary

        } catch {
            // AI Service failure is not critical if we have Gibbon data
            // Create a basic summary from Gibbon data if available
            if let gibbon = gibbonDashboard {
                self.summary = createSummaryFromGibbonData(gibbon)
            }
        }
    }

    /// Creates a basic dashboard summary from Gibbon data when AI Service is unavailable
    /// - Parameter gibbon: The Gibbon dashboard data
    /// - Returns: A basic dashboard summary
    private func createSummaryFromGibbonData(_ gibbon: DashboardData) -> DashboardSummary {
        let enrollmentRate = gibbon.capacityUtilization ?? 0.0
        let attendance = Double(gibbon.todayAttendance ?? 0) / Double(max(gibbon.activeChildren, 1)) * 100

        return DashboardSummary(
            totalEnrolled: gibbon.activeChildren,
            totalCapacity: gibbon.totalChildren > gibbon.activeChildren
                ? gibbon.totalChildren
                : Int(Double(gibbon.activeChildren) / max(enrollmentRate / 100, 0.7)),
            enrollmentRate: enrollmentRate * 100,
            averageAttendance: min(attendance, 100),
            complianceScore: 85.0 // Default when AI Service unavailable
        )
    }
}

// MARK: - Preview Support

#if DEBUG
extension DashboardViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: DashboardViewModel {
        let viewModel = DashboardViewModel()
        viewModel.summary = .preview
        viewModel.kpis = [.preview, .previewAttendance, .previewRevenue, .previewStaffing]
        viewModel.forecast = .preview
        viewModel.complianceSummary = .preview
        viewModel.alerts = [.preview, .previewCritical, .previewInfo]
        viewModel.quickStats = .preview
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        viewModel.aiServiceAvailable = true
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: DashboardViewModel {
        let viewModel = DashboardViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: DashboardViewModel {
        let viewModel = DashboardViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with alerts for previews
    static var previewWithAlerts: DashboardViewModel {
        let viewModel = DashboardViewModel()
        viewModel.summary = .previewWithWarnings
        viewModel.alerts = [
            .previewCritical,
            .preview,
            .previewInfo
        ]
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        return viewModel
    }

    /// Creates a mock ViewModel with minimal data (AI Service unavailable)
    static var previewMinimal: DashboardViewModel {
        let viewModel = DashboardViewModel()
        viewModel.gibbonDashboard = DashboardData(
            totalChildren: 52,
            activeChildren: 48,
            totalStaff: 10,
            activeStaff: 8,
            financeSummary: nil,
            alerts: nil,
            todayAttendance: 42,
            capacityUtilization: 0.92
        )
        viewModel.summary = viewModel.createSummaryFromGibbonData(viewModel.gibbonDashboard!)
        viewModel.aiServiceAvailable = false
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when dashboard data is refreshed
    static let dashboardRefreshed = Notification.Name("dashboardRefreshed")

    /// Posted when a critical alert is received
    static let criticalAlertReceived = Notification.Name("criticalAlertReceived")
}
