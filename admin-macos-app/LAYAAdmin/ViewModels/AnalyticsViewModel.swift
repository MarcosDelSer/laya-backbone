//
//  AnalyticsViewModel.swift
//  LAYAAdmin
//
//  ViewModel for the AI Analytics Dashboard in the LAYA Admin application.
//  Handles loading and managing analytics data including KPIs, enrollment
//  forecasts, compliance status, staff efficiency, and financial health.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Analytics ViewModel

/// ViewModel for managing AI analytics dashboard state and data loading.
///
/// This ViewModel provides comprehensive analytics capabilities:
/// - KPI metrics with trends and comparisons
/// - Enrollment forecasting with confidence intervals
/// - Compliance monitoring across all check types
/// - Staff efficiency metrics
/// - Financial health indicators
///
/// Features:
/// - Concurrent data loading from AI Service
/// - Time period filtering
/// - Category-based filtering for KPIs
/// - Auto-refresh capability
/// - Graceful error handling with partial data display
@MainActor
final class AnalyticsViewModel: ObservableObject {

    // MARK: - Published Properties

    /// Key performance indicators
    @Published private(set) var kpis: [KPIMetric] = []

    /// Enrollment forecast data with historical context
    @Published private(set) var forecast: ForecastData?

    /// Compliance status for all check types
    @Published private(set) var compliance: ComplianceListResponse?

    /// Staff efficiency metrics
    @Published private(set) var staffEfficiency: StaffEfficiencyResponse?

    /// Financial health indicators
    @Published private(set) var financialHealth: FinancialHealthResponse?

    /// Aggregated analytics insights
    @Published private(set) var insights: AnalyticsInsights?

    /// Whether data loading is in progress
    @Published private(set) var isLoading = false

    /// Whether initial data has been loaded
    @Published private(set) var hasLoaded = false

    /// Current error, if any
    @Published private(set) var error: Error?

    /// Whether the error alert should be shown
    @Published var showError = false

    /// Last time analytics were refreshed
    @Published private(set) var lastRefreshed: Date?

    /// Whether the AI Service is available
    @Published private(set) var aiServiceAvailable = false

    // MARK: - Filter Properties

    /// Selected time period for analytics
    @Published var selectedPeriod: AnalyticsTimePeriod = .month

    /// Selected metric categories to display
    @Published var selectedCategories: Set<MetricCategory> = Set(MetricCategory.allCases)

    /// Selected compliance check types to display
    @Published var selectedComplianceTypes: Set<ComplianceCheckType> = Set(ComplianceCheckType.allCases)

    /// Number of forecast periods to display
    @Published var forecastPeriods: Int = 6

    // MARK: - Refresh Control

    /// Whether auto-refresh is enabled
    @Published var autoRefreshEnabled = true

    /// Auto-refresh interval in seconds (default: 5 minutes)
    let autoRefreshInterval: TimeInterval = 300

    // MARK: - Computed Properties

    /// Whether any analytics data is available
    var hasData: Bool {
        !kpis.isEmpty || forecast != nil || compliance != nil ||
        staffEfficiency != nil || financialHealth != nil
    }

    /// Whether all data sources loaded successfully
    var isComplete: Bool {
        !kpis.isEmpty && forecast != nil && compliance != nil &&
        staffEfficiency != nil && financialHealth != nil
    }

    /// KPIs filtered by selected categories
    var filteredKPIs: [KPIMetric] {
        kpis.filter { selectedCategories.contains($0.category) }
    }

    /// KPIs grouped by category
    var kpisByCategory: [MetricCategory: [KPIMetric]] {
        Dictionary(grouping: filteredKPIs) { $0.category }
    }

    /// Enrollment KPIs only
    var enrollmentKPIs: [KPIMetric] {
        kpis.filter { $0.category == .enrollment }
    }

    /// Attendance KPIs only
    var attendanceKPIs: [KPIMetric] {
        kpis.filter { $0.category == .attendance }
    }

    /// Revenue KPIs only
    var revenueKPIs: [KPIMetric] {
        kpis.filter { $0.category == .revenue }
    }

    /// Staffing KPIs only
    var staffingKPIs: [KPIMetric] {
        kpis.filter { $0.category == .staffing }
    }

    /// Compliance checks filtered by selected types
    var filteredComplianceChecks: [ComplianceCheckResponse] {
        guard let compliance = compliance else { return [] }
        return compliance.checks.filter { selectedComplianceTypes.contains($0.checkType) }
    }

    /// Compliance issues requiring attention
    var complianceIssues: [ComplianceCheckResponse] {
        compliance?.checksRequiringAttention ?? []
    }

    /// Number of compliance warnings
    var complianceWarningCount: Int {
        compliance?.warningCount ?? 0
    }

    /// Number of compliance violations
    var complianceViolationCount: Int {
        compliance?.violationCount ?? 0
    }

    /// Overall compliance status
    var overallComplianceStatus: ComplianceStatus {
        compliance?.overallStatus ?? .unknown
    }

    /// Compliance percentage
    var compliancePercentage: Double {
        compliance?.compliancePercentage ?? 0
    }

    /// Historical enrollment data points
    var historicalEnrollment: [ForecastDataPoint] {
        forecast?.historical ?? []
    }

    /// Future forecast data points
    var forecastedEnrollment: [ForecastDataPoint] {
        forecast?.forecast ?? []
    }

    /// All enrollment data points (historical + forecast)
    var allEnrollmentData: [ForecastDataPoint] {
        forecast?.allDataPoints ?? []
    }

    /// Current enrollment count
    var currentEnrollment: Int {
        forecast?.currentEnrollment ?? 0
    }

    /// Predicted enrollment change
    var predictedEnrollmentChange: Int {
        forecast?.predictedChange ?? 0
    }

    /// Predicted enrollment change percentage
    var predictedEnrollmentChangePercentage: Double? {
        forecast?.predictedChangePercentage
    }

    /// Overall staff efficiency score
    var overallStaffEfficiency: Double {
        staffEfficiency?.overallEfficiency ?? 0
    }

    /// Average staff-to-child ratio
    var averageStaffRatio: Double {
        staffEfficiency?.averageStaffRatio ?? 0
    }

    /// Financial health level
    var financialHealthLevel: DashboardLevel {
        financialHealth?.healthLevel ?? .medium
    }

    /// Net income from financial health
    var netIncome: Double {
        financialHealth?.netIncome ?? 0
    }

    /// Collection rate from financial health
    var collectionRate: Double {
        financialHealth?.collectionRate ?? 0
    }

    /// Revenue trend direction
    var revenueTrend: TrendDirection {
        financialHealth?.revenueTrend ?? .stable
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

    /// Data freshness indicator
    var dataFreshness: DataFreshness {
        guard let lastRefresh = lastRefreshed else { return .stale }

        let timeSinceRefresh = Date().timeIntervalSince(lastRefresh)
        if timeSinceRefresh < 60 {
            return .fresh
        } else if timeSinceRefresh < 300 {
            return .recent
        } else {
            return .stale
        }
    }

    // MARK: - Private Properties

    /// AI Service client
    private let aiServiceClient: AIServiceClient

    /// Combine cancellables for subscriptions
    private var cancellables = Set<AnyCancellable>()

    /// Auto-refresh timer
    private var refreshTimer: Timer?

    // MARK: - Initialization

    /// Creates a new AnalyticsViewModel
    /// - Parameter aiServiceClient: The AI Service client to use (defaults to shared instance)
    init(aiServiceClient: AIServiceClient = .shared) {
        self.aiServiceClient = aiServiceClient
        setupFilterSubscriptions()
    }

    deinit {
        stopAutoRefresh()
    }

    // MARK: - Public Methods

    /// Loads all analytics data from the AI Service.
    /// This is the primary method to refresh all analytics.
    func loadAnalytics() async {
        isLoading = true
        error = nil
        showError = false

        // First check if AI Service is available
        aiServiceAvailable = await aiServiceClient.isServiceAvailable()

        guard aiServiceAvailable else {
            error = APIError.serviceUnavailable(message: "AI Service is not available")
            showError = true
            isLoading = false
            return
        }

        // Run all API calls concurrently
        await withTaskGroup(of: Void.self) { group in
            group.addTask { [weak self] in
                await self?.loadKPIs()
            }

            group.addTask { [weak self] in
                await self?.loadForecast()
            }

            group.addTask { [weak self] in
                await self?.loadCompliance()
            }

            group.addTask { [weak self] in
                await self?.loadStaffEfficiency()
            }

            group.addTask { [weak self] in
                await self?.loadFinancialHealth()
            }
        }

        hasLoaded = true
        lastRefreshed = Date()
        isLoading = false

        // Post notification that analytics were refreshed
        NotificationCenter.default.post(name: .analyticsRefreshed, object: nil)
    }

    /// Refreshes analytics data (alias for loadAnalytics for semantic clarity)
    func refresh() async {
        await loadAnalytics()
    }

    /// Loads only KPI metrics with current filters
    func loadKPIs() async {
        do {
            let categories = selectedCategories.isEmpty ? nil : Array(selectedCategories)
            let response = try await aiServiceClient.fetchKPIs(
                period: selectedPeriod,
                categories: categories
            )
            self.kpis = response.metrics
        } catch {
            // KPIs failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads enrollment forecast data
    func loadForecast() async {
        do {
            let data = try await aiServiceClient.fetchEnrollmentForecast(
                forecastPeriods: forecastPeriods,
                periodUnit: selectedPeriod
            )
            self.forecast = data
        } catch {
            // Forecast failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads compliance status data
    func loadCompliance() async {
        do {
            let checkTypes = selectedComplianceTypes.isEmpty ? nil : Array(selectedComplianceTypes)
            let response = try await aiServiceClient.fetchCompliance(checkTypes: checkTypes)
            self.compliance = response
        } catch {
            // Compliance failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads staff efficiency metrics
    func loadStaffEfficiency() async {
        do {
            let response = try await aiServiceClient.fetchStaffEfficiency(period: selectedPeriod)
            self.staffEfficiency = response
        } catch {
            // Staff efficiency failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads financial health indicators
    func loadFinancialHealth() async {
        do {
            let response = try await aiServiceClient.fetchFinancialHealth(period: selectedPeriod)
            self.financialHealth = response
        } catch {
            // Financial health failed but don't show error if we have other data
            if !hasData {
                self.error = error
            }
        }
    }

    /// Loads all analytics insights in a single batch operation
    func loadInsights() async {
        isLoading = true
        aiServiceAvailable = await aiServiceClient.isServiceAvailable()

        guard aiServiceAvailable else {
            error = APIError.serviceUnavailable(message: "AI Service is not available")
            showError = true
            isLoading = false
            return
        }

        let fetchedInsights = await aiServiceClient.fetchAnalyticsInsights()
        self.insights = fetchedInsights

        // Extract individual components from insights
        if let kpisResponse = fetchedInsights.kpis {
            self.kpis = kpisResponse.metrics
        }
        if let forecastData = fetchedInsights.forecast {
            self.forecast = forecastData
        }
        if let complianceData = fetchedInsights.compliance {
            self.compliance = complianceData
        }
        if let staffData = fetchedInsights.staffEfficiency {
            self.staffEfficiency = staffData
        }
        if let financialData = fetchedInsights.financialHealth {
            self.financialHealth = financialData
        }

        hasLoaded = true
        lastRefreshed = Date()
        isLoading = false
    }

    /// Changes the selected time period and reloads data
    /// - Parameter period: The new time period to use
    func changePeriod(_ period: AnalyticsTimePeriod) async {
        selectedPeriod = period
        await loadAnalytics()
    }

    /// Toggles a metric category filter
    /// - Parameter category: The category to toggle
    func toggleCategory(_ category: MetricCategory) {
        if selectedCategories.contains(category) {
            selectedCategories.remove(category)
        } else {
            selectedCategories.insert(category)
        }
    }

    /// Selects all metric categories
    func selectAllCategories() {
        selectedCategories = Set(MetricCategory.allCases)
    }

    /// Clears all metric category selections
    func clearCategories() {
        selectedCategories.removeAll()
    }

    /// Toggles a compliance check type filter
    /// - Parameter checkType: The check type to toggle
    func toggleComplianceType(_ checkType: ComplianceCheckType) {
        if selectedComplianceTypes.contains(checkType) {
            selectedComplianceTypes.remove(checkType)
        } else {
            selectedComplianceTypes.insert(checkType)
        }
    }

    /// Selects all compliance check types
    func selectAllComplianceTypes() {
        selectedComplianceTypes = Set(ComplianceCheckType.allCases)
    }

    /// Sets the number of forecast periods
    /// - Parameter periods: Number of periods to forecast
    func setForecastPeriods(_ periods: Int) async {
        forecastPeriods = max(1, min(24, periods)) // Clamp to 1-24
        await loadForecast()
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
                await self?.loadAnalytics()
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

    /// Gets a summary message for the current analytics state
    func getSummaryMessage() -> String {
        guard hasData else {
            return String(localized: "No analytics data available")
        }

        var parts: [String] = []

        if let compliance = compliance, compliance.hasIssues {
            let issues = compliance.checksRequiringAttention.count
            parts.append(String(localized: "\(issues) compliance issue(s)"))
        }

        if let forecast = forecast, let change = forecast.predictedChange, change != 0 {
            let direction = change > 0 ? String(localized: "increase") : String(localized: "decrease")
            parts.append(String(localized: "Enrollment expected to \(direction) by \(abs(change))"))
        }

        if let financial = financialHealth {
            if financial.healthLevel == .low {
                parts.append(String(localized: "Financial health needs attention"))
            }
        }

        if parts.isEmpty {
            return String(localized: "All metrics within normal ranges")
        }

        return parts.joined(separator: "; ")
    }

    // MARK: - Private Methods

    /// Sets up Combine subscriptions for filter changes
    private func setupFilterSubscriptions() {
        // Debounce category changes and reload KPIs
        $selectedCategories
            .dropFirst()
            .debounce(for: .milliseconds(300), scheduler: RunLoop.main)
            .sink { [weak self] _ in
                Task { @MainActor [weak self] in
                    await self?.loadKPIs()
                }
            }
            .store(in: &cancellables)

        // Debounce compliance type changes and reload compliance
        $selectedComplianceTypes
            .dropFirst()
            .debounce(for: .milliseconds(300), scheduler: RunLoop.main)
            .sink { [weak self] _ in
                Task { @MainActor [weak self] in
                    await self?.loadCompliance()
                }
            }
            .store(in: &cancellables)
    }
}

// MARK: - Data Freshness

/// Indicator of how fresh the analytics data is
enum DataFreshness: String, CaseIterable {
    /// Data was refreshed within the last minute
    case fresh = "fresh"

    /// Data was refreshed within the last 5 minutes
    case recent = "recent"

    /// Data is more than 5 minutes old
    case stale = "stale"

    var displayName: String {
        switch self {
        case .fresh:
            return String(localized: "Fresh")
        case .recent:
            return String(localized: "Recent")
        case .stale:
            return String(localized: "Stale")
        }
    }

    var color: String {
        switch self {
        case .fresh:
            return "green"
        case .recent:
            return "orange"
        case .stale:
            return "gray"
        }
    }

    var iconName: String {
        switch self {
        case .fresh:
            return "checkmark.circle.fill"
        case .recent:
            return "clock.fill"
        case .stale:
            return "clock.badge.exclamationmark"
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension AnalyticsViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.kpis = [.preview, .previewAttendance, .previewRevenue, .previewStaffing]
        viewModel.forecast = .preview
        viewModel.compliance = .preview
        viewModel.staffEfficiency = .preview
        viewModel.financialHealth = .preview
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        viewModel.aiServiceAvailable = true
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with AI Service unavailable
    static var previewServiceUnavailable: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.aiServiceAvailable = false
        viewModel.error = APIError.serviceUnavailable(message: "AI Service is not available")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with compliance issues
    static var previewWithComplianceIssues: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.kpis = [.preview, .previewAttendance]
        viewModel.forecast = .preview
        viewModel.compliance = .previewWithWarnings
        viewModel.staffEfficiency = .preview
        viewModel.financialHealth = .preview
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        viewModel.aiServiceAvailable = true
        return viewModel
    }

    /// Creates a mock ViewModel with partial data
    static var previewPartialData: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.kpis = [.preview]
        viewModel.forecast = .preview
        // compliance, staffEfficiency, and financialHealth are nil
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        viewModel.aiServiceAvailable = true
        return viewModel
    }

    /// Creates a mock ViewModel with enrollment category only
    static var previewEnrollmentOnly: AnalyticsViewModel {
        let viewModel = AnalyticsViewModel()
        viewModel.selectedCategories = [.enrollment]
        viewModel.kpis = [.preview]
        viewModel.forecast = .preview
        viewModel.hasLoaded = true
        viewModel.lastRefreshed = Date()
        viewModel.aiServiceAvailable = true
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when analytics data is refreshed
    static let analyticsRefreshed = Notification.Name("analyticsRefreshed")

    /// Posted when a compliance issue is detected
    static let complianceIssueDetected = Notification.Name("complianceIssueDetected")

    /// Posted when forecast data shows significant change
    static let forecastChangeDetected = Notification.Name("forecastChangeDetected")
}
