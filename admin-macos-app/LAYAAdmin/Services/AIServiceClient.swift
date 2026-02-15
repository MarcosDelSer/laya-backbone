//
//  AIServiceClient.swift
//  LAYAAdmin
//
//  Type-safe AI Service API client for LAYA Admin.
//  Provides methods for interacting with the AI Intelligence Service
//  for analytics dashboards, KPIs, enrollment forecasting, staff
//  efficiency metrics, compliance checks, and financial health.
//

import Foundation

// MARK: - AI Service Client

/// Type-safe client for the AI Intelligence Service API.
///
/// Provides high-level methods for analytics operations:
/// - Dashboard metrics and KPIs
/// - Enrollment forecasting
/// - Staff efficiency metrics
/// - Compliance monitoring
/// - Financial health indicators
///
/// Uses `APIService.aiService` for all network requests.
@MainActor
final class AIServiceClient {

    // MARK: - Singleton

    /// Shared instance of the AI Service client
    static let shared = AIServiceClient()

    // MARK: - Properties

    /// The API service used for network requests
    private let apiService: APIService

    // MARK: - Initialization

    /// Creates a new AI Service client instance
    /// - Parameter apiService: The API service to use (defaults to AI Service instance)
    init(apiService: APIService = .aiService) {
        self.apiService = apiService
    }

    // MARK: - Health Check

    /// Check if the AI service is healthy and accessible.
    /// - Returns: Health check response with service status
    func checkHealth() async throws -> AIServiceHealthResponse {
        return try await apiService.get("/")
    }

    /// Check if the AI service is available.
    /// - Returns: True if healthy, false otherwise
    func isServiceAvailable() async -> Bool {
        do {
            let response = try await checkHealth()
            return response.status == "healthy"
        } catch {
            return false
        }
    }

    // MARK: - Dashboard API

    /// Fetches the comprehensive dashboard with all key metrics.
    /// - Parameters:
    ///   - facilityId: Optional facility identifier for facility-specific data
    ///   - includeForecast: Whether to include forecast data (default: true)
    ///   - includeCompliance: Whether to include compliance data (default: true)
    /// - Returns: Complete dashboard response with summary, KPIs, forecast, and compliance
    func fetchDashboard(
        facilityId: String? = nil,
        includeForecast: Bool = true,
        includeCompliance: Bool = true
    ) async throws -> DashboardResponse {
        var params: [String: Any] = [
            "include_forecast": includeForecast,
            "include_compliance": includeCompliance
        ]

        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.dashboard, parameters: params)
    }

    /// Fetches the dashboard summary with high-level metrics.
    /// - Parameter facilityId: Optional facility identifier
    /// - Returns: Dashboard summary with enrollment, attendance, and compliance scores
    func fetchDashboardSummary(facilityId: String? = nil) async throws -> DashboardSummary {
        var params: [String: Any] = ["summary_only": true]

        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.dashboard, parameters: params)
    }

    // MARK: - KPI API

    /// Fetches key performance indicators with optional filters.
    /// - Parameters:
    ///   - period: Time period for the metrics (default: month)
    ///   - categories: Optional categories to include
    ///   - facilityId: Optional facility identifier
    /// - Returns: KPI metrics list response
    func fetchKPIs(
        period: AnalyticsTimePeriod = .month,
        categories: [MetricCategory]? = nil,
        facilityId: String? = nil
    ) async throws -> KPIMetricsListResponse {
        var params: [String: Any] = [
            "period": period.rawValue
        ]

        if let categories = categories {
            params["categories"] = categories.map { $0.rawValue }.joined(separator: ",")
        }
        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.kpis, parameters: params)
    }

    /// Fetches a single KPI metric by name.
    /// - Parameters:
    ///   - metricName: Name of the metric to fetch
    ///   - period: Time period for the metric
    /// - Returns: Single KPI metric
    func fetchKPI(metricName: String, period: AnalyticsTimePeriod = .month) async throws -> KPIMetric {
        let params: [String: Any] = [
            "metric_name": metricName,
            "period": period.rawValue
        ]
        return try await apiService.get(AIServiceEndpoints.kpis, parameters: params)
    }

    /// Fetches KPIs for a specific category.
    /// - Parameters:
    ///   - category: Metric category to fetch
    ///   - period: Time period for the metrics
    /// - Returns: KPI metrics list response for the category
    func fetchKPIsByCategory(
        category: MetricCategory,
        period: AnalyticsTimePeriod = .month
    ) async throws -> KPIMetricsListResponse {
        return try await fetchKPIs(period: period, categories: [category])
    }

    // MARK: - Enrollment Forecast API

    /// Fetches enrollment forecast data.
    /// - Parameters:
    ///   - forecastPeriods: Number of periods to forecast (default: 6)
    ///   - periodUnit: Time unit for each period (default: month)
    ///   - includeHistorical: Whether to include historical data (default: true)
    ///   - facilityId: Optional facility identifier
    /// - Returns: Forecast data with historical and predicted values
    func fetchEnrollmentForecast(
        forecastPeriods: Int = 6,
        periodUnit: AnalyticsTimePeriod = .month,
        includeHistorical: Bool = true,
        facilityId: String? = nil
    ) async throws -> ForecastData {
        var params: [String: Any] = [
            "forecast_periods": forecastPeriods,
            "period_unit": periodUnit.rawValue,
            "include_historical": includeHistorical
        ]

        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.enrollmentForecast, parameters: params)
    }

    /// Fetches a short-term enrollment forecast (3 months).
    /// - Parameter facilityId: Optional facility identifier
    /// - Returns: Forecast data for the next 3 months
    func fetchShortTermForecast(facilityId: String? = nil) async throws -> ForecastData {
        return try await fetchEnrollmentForecast(
            forecastPeriods: 3,
            periodUnit: .month,
            facilityId: facilityId
        )
    }

    /// Fetches a long-term enrollment forecast (12 months).
    /// - Parameter facilityId: Optional facility identifier
    /// - Returns: Forecast data for the next 12 months
    func fetchLongTermForecast(facilityId: String? = nil) async throws -> ForecastData {
        return try await fetchEnrollmentForecast(
            forecastPeriods: 12,
            periodUnit: .month,
            facilityId: facilityId
        )
    }

    // MARK: - Staff Efficiency API

    /// Fetches staff efficiency metrics.
    /// - Parameters:
    ///   - period: Time period for the metrics (default: month)
    ///   - staffId: Optional specific staff member ID
    ///   - facilityId: Optional facility identifier
    /// - Returns: Staff efficiency metrics response
    func fetchStaffEfficiency(
        period: AnalyticsTimePeriod = .month,
        staffId: String? = nil,
        facilityId: String? = nil
    ) async throws -> StaffEfficiencyResponse {
        var params: [String: Any] = [
            "period": period.rawValue
        ]

        if let staffId = staffId {
            params["staff_id"] = staffId
        }
        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.staffEfficiency, parameters: params)
    }

    /// Fetches staff efficiency summary for dashboard display.
    /// - Returns: Staff efficiency summary metrics
    func fetchStaffEfficiencySummary() async throws -> StaffEfficiencySummary {
        let params: [String: Any] = ["summary_only": true]
        return try await apiService.get(AIServiceEndpoints.staffEfficiency, parameters: params)
    }

    // MARK: - Compliance API

    /// Fetches compliance status for all check types.
    /// - Parameters:
    ///   - checkTypes: Optional specific check types to include
    ///   - includeDetails: Whether to include detailed breakdown (default: true)
    ///   - facilityId: Optional facility identifier
    /// - Returns: Compliance list response with all checks
    func fetchCompliance(
        checkTypes: [ComplianceCheckType]? = nil,
        includeDetails: Bool = true,
        facilityId: String? = nil
    ) async throws -> ComplianceListResponse {
        var params: [String: Any] = [
            "include_details": includeDetails
        ]

        if let checkTypes = checkTypes {
            params["check_types"] = checkTypes.map { $0.rawValue }.joined(separator: ",")
        }
        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.compliance, parameters: params)
    }

    /// Fetches compliance status for a specific check type.
    /// - Parameters:
    ///   - checkType: Type of compliance check
    ///   - facilityId: Optional facility identifier
    /// - Returns: Compliance check response for the specified type
    func fetchComplianceCheck(
        checkType: ComplianceCheckType,
        facilityId: String? = nil
    ) async throws -> ComplianceCheckResponse {
        var params: [String: Any] = [
            "check_type": checkType.rawValue
        ]

        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.compliance, parameters: params)
    }

    /// Fetches compliance issues requiring attention.
    /// - Returns: Array of compliance checks with warnings or violations
    func fetchComplianceIssues() async throws -> [ComplianceCheckResponse] {
        let response = try await fetchCompliance()
        return response.checksRequiringAttention
    }

    // MARK: - Financial Health API

    /// Fetches financial health indicators.
    /// - Parameters:
    ///   - period: Time period for the metrics (default: month)
    ///   - facilityId: Optional facility identifier
    /// - Returns: Financial health response with revenue and expense metrics
    func fetchFinancialHealth(
        period: AnalyticsTimePeriod = .month,
        facilityId: String? = nil
    ) async throws -> FinancialHealthResponse {
        var params: [String: Any] = [
            "period": period.rawValue
        ]

        if let facilityId = facilityId {
            params["facility_id"] = facilityId
        }

        return try await apiService.get(AIServiceEndpoints.financialHealth, parameters: params)
    }

    /// Fetches financial health summary for dashboard display.
    /// - Returns: Financial health summary metrics
    func fetchFinancialHealthSummary() async throws -> FinancialHealthSummary {
        let params: [String: Any] = ["summary_only": true]
        return try await apiService.get(AIServiceEndpoints.financialHealth, parameters: params)
    }

    // MARK: - Batch Operations

    /// Fetches all analytics data for the dashboard in one call.
    /// Uses concurrent requests to improve performance.
    /// - Parameter facilityId: Optional facility identifier
    /// - Returns: Analytics insights with all available data
    func fetchAnalyticsInsights(facilityId: String? = nil) async -> AnalyticsInsights {
        async let kpisResult = fetchKPIs(facilityId: facilityId)
        async let forecastResult = fetchEnrollmentForecast(facilityId: facilityId)
        async let complianceResult = fetchCompliance(facilityId: facilityId)
        async let financialResult = fetchFinancialHealth(facilityId: facilityId)
        async let staffResult = fetchStaffEfficiency(facilityId: facilityId)

        // Collect results, handling failures gracefully
        let kpis = try? await kpisResult
        let forecast = try? await forecastResult
        let compliance = try? await complianceResult
        let financial = try? await financialResult
        let staff = try? await staffResult

        return AnalyticsInsights(
            kpis: kpis,
            forecast: forecast,
            compliance: compliance,
            financialHealth: financial,
            staffEfficiency: staff,
            generatedAt: Date()
        )
    }

    /// Fetches quick analytics summary for menu bar or notifications.
    /// - Returns: Quick analytics summary with key metrics
    func fetchQuickAnalytics() async throws -> QuickAnalytics {
        let params: [String: Any] = ["quick": true]
        return try await apiService.get(AIServiceEndpoints.dashboard, parameters: params)
    }

    // MARK: - Error Handling Helpers

    /// Check if an error is an API error.
    /// - Parameter error: The error to check
    /// - Returns: True if the error is an APIError
    static func isAPIError(_ error: Error) -> Bool {
        return error is APIError
    }

    /// Get a user-friendly error message from an error.
    /// - Parameter error: The error to get a message for
    /// - Returns: User-friendly error message
    static func getErrorMessage(_ error: Error) -> String {
        if let apiError = error as? APIError {
            return apiError.userMessage
        }
        return error.localizedDescription
    }

    /// Wraps an AI service call with fallback behavior.
    /// - Parameters:
    ///   - operation: The operation to perform
    ///   - fallback: Fallback value if operation fails with server error
    /// - Returns: Result of operation or fallback value
    func withFallback<T>(
        operation: () async throws -> T,
        fallback: T
    ) async -> T {
        do {
            return try await operation()
        } catch let error as APIError where error.isServerError {
            return fallback
        } catch {
            return fallback
        }
    }
}

// MARK: - AI Service Health Response

/// Response from the AI service health check endpoint.
struct AIServiceHealthResponse: Codable, Equatable {

    /// Service status (e.g., "healthy", "degraded", "unhealthy")
    let status: String

    /// Service version
    let version: String?

    /// Optional details about service health
    let details: [String: String]?
}

// MARK: - Staff Efficiency Response

/// Response from the staff efficiency endpoint.
struct StaffEfficiencyResponse: Codable, Equatable {

    /// List of staff efficiency metrics
    let staffMetrics: [StaffEfficiencyMetric]

    /// Overall facility efficiency score (0-100)
    let overallEfficiency: Double

    /// Average staff-to-child ratio
    let averageStaffRatio: Double

    /// When the metrics were generated
    let generatedAt: Date

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case staffMetrics = "staff_metrics"
        case overallEfficiency = "overall_efficiency"
        case averageStaffRatio = "average_staff_ratio"
        case generatedAt = "generated_at"
    }

    // MARK: - Computed Properties

    /// Formatted overall efficiency string
    var formattedEfficiency: String {
        overallEfficiency.asPercentage
    }

    /// Formatted staff ratio string
    var formattedStaffRatio: String {
        String(format: "1:%.1f", averageStaffRatio)
    }
}

// MARK: - Staff Efficiency Metric

/// Efficiency metrics for a single staff member.
struct StaffEfficiencyMetric: Identifiable, Codable, Equatable {

    /// Staff member ID
    let id: String

    /// Staff member name
    let staffName: String

    /// Role/position
    let role: String?

    /// Efficiency score (0-100)
    let efficiencyScore: Double

    /// Hours worked in the period
    let hoursWorked: Double

    /// Number of children supervised
    let childrenSupervised: Int

    /// Attendance reliability percentage
    let attendanceReliability: Double?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case staffName = "staff_name"
        case role
        case efficiencyScore = "efficiency_score"
        case hoursWorked = "hours_worked"
        case childrenSupervised = "children_supervised"
        case attendanceReliability = "attendance_reliability"
    }

    // MARK: - Computed Properties

    /// Formatted efficiency score string
    var formattedEfficiency: String {
        efficiencyScore.asPercentage
    }
}

// MARK: - Staff Efficiency Summary

/// Summary of staff efficiency metrics for dashboard display.
struct StaffEfficiencySummary: Codable, Equatable {

    /// Overall efficiency score (0-100)
    let overallEfficiency: Double

    /// Average staff-to-child ratio
    let averageRatio: Double

    /// Number of staff below efficiency threshold
    let staffBelowThreshold: Int

    /// Change from previous period
    let changeFromPrevious: Double?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case overallEfficiency = "overall_efficiency"
        case averageRatio = "average_ratio"
        case staffBelowThreshold = "staff_below_threshold"
        case changeFromPrevious = "change_from_previous"
    }
}

// MARK: - Financial Health Response

/// Response from the financial health endpoint.
struct FinancialHealthResponse: Codable, Equatable {

    /// Total revenue for the period
    let totalRevenue: Double

    /// Total expenses for the period
    let totalExpenses: Double

    /// Net income (revenue - expenses)
    let netIncome: Double

    /// Collection rate percentage (0-100)
    let collectionRate: Double

    /// Days sales outstanding (average days to collect payment)
    let daysSalesOutstanding: Double

    /// Budget variance percentage
    let budgetVariance: Double?

    /// Revenue trend compared to previous period
    let revenueTrend: TrendDirection

    /// List of revenue streams
    let revenueStreams: [RevenueStream]?

    /// When the metrics were generated
    let generatedAt: Date

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case totalRevenue = "total_revenue"
        case totalExpenses = "total_expenses"
        case netIncome = "net_income"
        case collectionRate = "collection_rate"
        case daysSalesOutstanding = "days_sales_outstanding"
        case budgetVariance = "budget_variance"
        case revenueTrend = "revenue_trend"
        case revenueStreams = "revenue_streams"
        case generatedAt = "generated_at"
    }

    // MARK: - Computed Properties

    /// Formatted total revenue string
    var formattedRevenue: String {
        totalRevenue.asCurrency
    }

    /// Formatted total expenses string
    var formattedExpenses: String {
        totalExpenses.asCurrency
    }

    /// Formatted net income string
    var formattedNetIncome: String {
        netIncome.asCurrency
    }

    /// Formatted collection rate string
    var formattedCollectionRate: String {
        collectionRate.asPercentage
    }

    /// Whether the financial health is good (positive net income and high collection rate)
    var isHealthy: Bool {
        netIncome > 0 && collectionRate >= 90
    }

    /// Overall health level
    var healthLevel: DashboardLevel {
        if netIncome > 0 && collectionRate >= 95 {
            return .high
        } else if netIncome >= 0 && collectionRate >= 80 {
            return .medium
        } else {
            return .low
        }
    }
}

// MARK: - Revenue Stream

/// Breakdown of revenue by source.
struct RevenueStream: Identifiable, Codable, Equatable {

    /// Unique identifier
    var id: String { name }

    /// Revenue source name
    let name: String

    /// Amount for this source
    let amount: Double

    /// Percentage of total revenue
    let percentage: Double

    // MARK: - Computed Properties

    /// Formatted amount string
    var formattedAmount: String {
        amount.asCurrency
    }

    /// Formatted percentage string
    var formattedPercentage: String {
        percentage.asPercentage
    }
}

// MARK: - Financial Health Summary

/// Summary of financial health for dashboard display.
struct FinancialHealthSummary: Codable, Equatable {

    /// Net income for the period
    let netIncome: Double

    /// Collection rate percentage
    let collectionRate: Double

    /// Number of overdue invoices
    let overdueInvoices: Int

    /// Change from previous period
    let changeFromPrevious: Double?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case netIncome = "net_income"
        case collectionRate = "collection_rate"
        case overdueInvoices = "overdue_invoices"
        case changeFromPrevious = "change_from_previous"
    }

    /// Formatted net income string
    var formattedNetIncome: String {
        netIncome.asCurrency
    }
}

// MARK: - Analytics Insights

/// Aggregated analytics insights from all AI service endpoints.
struct AnalyticsInsights {

    /// KPI metrics (nil if fetch failed)
    let kpis: KPIMetricsListResponse?

    /// Enrollment forecast (nil if fetch failed)
    let forecast: ForecastData?

    /// Compliance data (nil if fetch failed)
    let compliance: ComplianceListResponse?

    /// Financial health (nil if fetch failed)
    let financialHealth: FinancialHealthResponse?

    /// Staff efficiency (nil if fetch failed)
    let staffEfficiency: StaffEfficiencyResponse?

    /// When the insights were generated
    let generatedAt: Date

    // MARK: - Computed Properties

    /// Whether all data was successfully fetched
    var isComplete: Bool {
        kpis != nil && forecast != nil && compliance != nil &&
        financialHealth != nil && staffEfficiency != nil
    }

    /// Whether any data is available
    var hasData: Bool {
        kpis != nil || forecast != nil || compliance != nil ||
        financialHealth != nil || staffEfficiency != nil
    }

    /// Number of data sources that returned successfully
    var successCount: Int {
        var count = 0
        if kpis != nil { count += 1 }
        if forecast != nil { count += 1 }
        if compliance != nil { count += 1 }
        if financialHealth != nil { count += 1 }
        if staffEfficiency != nil { count += 1 }
        return count
    }
}

// MARK: - Quick Analytics

/// Quick analytics summary for menu bar or notifications.
struct QuickAnalytics: Codable, Equatable {

    /// Current enrollment count
    let currentEnrollment: Int

    /// Today's attendance count
    let todayAttendance: Int

    /// Number of compliance issues
    let complianceIssues: Int

    /// Number of overdue invoices
    let overdueInvoices: Int

    /// Overall health status
    let healthStatus: DashboardLevel

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case currentEnrollment = "current_enrollment"
        case todayAttendance = "today_attendance"
        case complianceIssues = "compliance_issues"
        case overdueInvoices = "overdue_invoices"
        case healthStatus = "health_status"
    }

    // MARK: - Computed Properties

    /// Whether there are any items requiring attention
    var hasIssues: Bool {
        complianceIssues > 0 || overdueInvoices > 0
    }

    /// Total number of issues
    var totalIssues: Int {
        complianceIssues + overdueInvoices
    }
}

// MARK: - Preview Extensions

extension AIServiceHealthResponse {

    /// Sample health response for previews
    static var preview: AIServiceHealthResponse {
        AIServiceHealthResponse(
            status: "healthy",
            version: "1.0.0",
            details: ["uptime": "99.9%"]
        )
    }
}

extension StaffEfficiencyResponse {

    /// Sample response for previews
    static var preview: StaffEfficiencyResponse {
        StaffEfficiencyResponse(
            staffMetrics: [
                StaffEfficiencyMetric(
                    id: "staff-1",
                    staffName: "Marie Dupont",
                    role: "Educator",
                    efficiencyScore: 92.5,
                    hoursWorked: 160,
                    childrenSupervised: 12,
                    attendanceReliability: 98.0
                )
            ],
            overallEfficiency: 88.5,
            averageStaffRatio: 5.2,
            generatedAt: Date()
        )
    }
}

extension FinancialHealthResponse {

    /// Sample response for previews
    static var preview: FinancialHealthResponse {
        FinancialHealthResponse(
            totalRevenue: 125000.00,
            totalExpenses: 98000.00,
            netIncome: 27000.00,
            collectionRate: 94.5,
            daysSalesOutstanding: 18.5,
            budgetVariance: 3.2,
            revenueTrend: .up,
            revenueStreams: [
                RevenueStream(name: "Tuition", amount: 110000.00, percentage: 88.0),
                RevenueStream(name: "Government Subsidy", amount: 15000.00, percentage: 12.0)
            ],
            generatedAt: Date()
        )
    }
}

extension QuickAnalytics {

    /// Sample quick analytics for previews
    static var preview: QuickAnalytics {
        QuickAnalytics(
            currentEnrollment: 48,
            todayAttendance: 42,
            complianceIssues: 1,
            overdueInvoices: 3,
            healthStatus: .medium
        )
    }
}
