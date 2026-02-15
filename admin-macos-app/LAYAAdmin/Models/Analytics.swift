//
//  Analytics.swift
//  LAYAAdmin
//
//  Analytics domain models for the LAYA Admin application.
//  Includes KPI metrics and enrollment forecasting for data-driven
//  decision-making in childcare facility management.
//

import Foundation

// MARK: - Metric Category

/// Categories of analytics metrics.
enum MetricCategory: String, Codable, CaseIterable {
    /// Enrollment-related metrics
    case enrollment = "enrollment"

    /// Attendance tracking metrics
    case attendance = "attendance"

    /// Financial and revenue metrics
    case revenue = "revenue"

    /// Staff-to-child ratio metrics
    case staffing = "staffing"

    var displayName: String {
        switch self {
        case .enrollment:
            return String(localized: "Enrollment")
        case .attendance:
            return String(localized: "Attendance")
        case .revenue:
            return String(localized: "Revenue")
        case .staffing:
            return String(localized: "Staffing")
        }
    }

    /// Icon name for this category (SF Symbols)
    var iconName: String {
        switch self {
        case .enrollment:
            return "person.2.fill"
        case .attendance:
            return "calendar.badge.clock"
        case .revenue:
            return "dollarsign.circle.fill"
        case .staffing:
            return "person.3.fill"
        }
    }
}

// MARK: - KPI Metric

/// A single KPI metric with value and metadata.
/// Represents a key performance indicator with its current value,
/// historical comparison, and category classification.
struct KPIMetric: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the metric
    let id: String

    /// Name of the KPI metric
    let metricName: String

    /// Current value of the metric
    let metricValue: Double

    /// Unit of measurement (e.g., %, count, CAD)
    let metricUnit: String?

    /// Category this metric belongs to
    let category: MetricCategory

    /// Start of the measurement period
    let periodStart: Date

    /// End of the measurement period
    let periodEnd: Date

    /// Value from the previous period for comparison
    let previousValue: Double?

    /// Percentage change from previous period
    let changePercentage: Double?

    /// Optional facility identifier for facility-specific metrics
    let facilityId: String?

    /// Date when the record was created
    let createdAt: Date?

    /// Date when the record was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case metricName = "metric_name"
        case metricValue = "metric_value"
        case metricUnit = "metric_unit"
        case category
        case periodStart = "period_start"
        case periodEnd = "period_end"
        case previousValue = "previous_value"
        case changePercentage = "change_percentage"
        case facilityId = "facility_id"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Formatted metric value string
    var formattedValue: String {
        if let unit = metricUnit {
            if unit == "%" {
                return metricValue.asPercentage
            } else if unit == "CAD" || unit == "$" {
                return metricValue.asCurrency
            } else {
                return "\(Int(metricValue)) \(unit)"
            }
        }
        return String(format: "%.1f", metricValue)
    }

    /// Formatted change percentage string with sign
    var formattedChange: String? {
        guard let change = changePercentage else { return nil }
        let sign = change >= 0 ? "+" : ""
        return "\(sign)\(String(format: "%.1f", change))%"
    }

    /// Whether the change is positive
    var isPositiveChange: Bool {
        (changePercentage ?? 0) >= 0
    }

    /// Color name for the change indicator
    var changeColor: String {
        guard let change = changePercentage else { return "gray" }
        // For some metrics, negative change might be good (e.g., costs)
        return change >= 0 ? "green" : "red"
    }

    /// Display name for the category
    var categoryDisplayName: String {
        category.displayName
    }
}

// MARK: - KPI Metric Extensions

extension KPIMetric {

    /// Creates a sample KPI metric for previews
    static var preview: KPIMetric {
        KPIMetric(
            id: "kpi-1",
            metricName: "Enrollment Rate",
            metricValue: 92.5,
            metricUnit: "%",
            category: .enrollment,
            periodStart: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            periodEnd: Date(),
            previousValue: 88.0,
            changePercentage: 4.5,
            facilityId: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample attendance KPI for previews
    static var previewAttendance: KPIMetric {
        KPIMetric(
            id: "kpi-2",
            metricName: "Average Daily Attendance",
            metricValue: 85.3,
            metricUnit: "%",
            category: .attendance,
            periodStart: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            periodEnd: Date(),
            previousValue: 87.0,
            changePercentage: -1.7,
            facilityId: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample revenue KPI for previews
    static var previewRevenue: KPIMetric {
        KPIMetric(
            id: "kpi-3",
            metricName: "Monthly Revenue",
            metricValue: 45250.00,
            metricUnit: "CAD",
            category: .revenue,
            periodStart: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            periodEnd: Date(),
            previousValue: 42800.00,
            changePercentage: 5.7,
            facilityId: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample staffing KPI for previews
    static var previewStaffing: KPIMetric {
        KPIMetric(
            id: "kpi-4",
            metricName: "Staff-to-Child Ratio",
            metricValue: 5.2,
            metricUnit: nil,
            category: .staffing,
            periodStart: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            periodEnd: Date(),
            previousValue: 5.0,
            changePercentage: 4.0,
            facilityId: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - KPI Metrics List Response

/// Response schema for a list of KPI metrics.
struct KPIMetricsListResponse: Codable, Equatable {

    /// List of KPI metrics
    let metrics: [KPIMetric]

    /// When the metrics were calculated
    let generatedAt: Date

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case metrics
        case generatedAt = "generated_at"
    }
}

// MARK: - Forecast Data Point

/// A single data point in the forecast time series.
struct ForecastDataPoint: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier (using forecast date as ID)
    var id: String {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withFullDate]
        return formatter.string(from: forecastDate)
    }

    /// Date for this forecast point
    let forecastDate: Date

    /// Predicted enrollment count
    let predictedEnrollment: Int

    /// Lower bound of confidence interval
    let confidenceLower: Int?

    /// Upper bound of confidence interval
    let confidenceUpper: Int?

    /// Whether this is historical data (not a prediction)
    let isHistorical: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case forecastDate = "forecast_date"
        case predictedEnrollment = "predicted_enrollment"
        case confidenceLower = "confidence_lower"
        case confidenceUpper = "confidence_upper"
        case isHistorical = "is_historical"
    }

    // MARK: - Computed Properties

    /// Formatted date string
    var formattedDate: String {
        forecastDate.displayDate
    }

    /// Confidence range as string (e.g., "45-52")
    var confidenceRange: String? {
        guard let lower = confidenceLower, let upper = confidenceUpper else { return nil }
        return "\(lower)-\(upper)"
    }
}

// MARK: - Forecast Data Point Extensions

extension ForecastDataPoint {

    /// Creates a sample forecast data point for previews
    static var preview: ForecastDataPoint {
        ForecastDataPoint(
            forecastDate: Date(),
            predictedEnrollment: 48,
            confidenceLower: 45,
            confidenceUpper: 51,
            isHistorical: false
        )
    }

    /// Creates a sample historical data point for previews
    static var previewHistorical: ForecastDataPoint {
        ForecastDataPoint(
            forecastDate: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            predictedEnrollment: 46,
            confidenceLower: nil,
            confidenceUpper: nil,
            isHistorical: true
        )
    }
}

// MARK: - Forecast Data

/// Enrollment forecast data with historical context.
/// Contains both historical enrollment data and future predictions
/// with confidence intervals.
struct ForecastData: Codable, Equatable {

    // MARK: - Properties

    /// Optional facility identifier
    let facilityId: String?

    /// Historical enrollment data points
    let historical: [ForecastDataPoint]

    /// Predicted future enrollment data points
    let forecast: [ForecastDataPoint]

    /// Version of the forecasting model used
    let modelVersion: String

    /// When the forecast was generated
    let generatedAt: Date

    /// Note about forecast confidence (e.g., limited historical data)
    let confidenceNote: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case facilityId = "facility_id"
        case historical
        case forecast
        case modelVersion = "model_version"
        case generatedAt = "generated_at"
        case confidenceNote = "confidence_note"
    }

    // MARK: - Computed Properties

    /// All data points combined (historical + forecast)
    var allDataPoints: [ForecastDataPoint] {
        historical + forecast
    }

    /// Latest historical enrollment count
    var currentEnrollment: Int? {
        historical.last?.predictedEnrollment
    }

    /// Predicted enrollment for next period
    var nextPeriodEnrollment: Int? {
        forecast.first?.predictedEnrollment
    }

    /// Predicted change from current to next period
    var predictedChange: Int? {
        guard let current = currentEnrollment, let next = nextPeriodEnrollment else { return nil }
        return next - current
    }

    /// Predicted change percentage
    var predictedChangePercentage: Double? {
        guard let current = currentEnrollment, let change = predictedChange, current > 0 else { return nil }
        return (Double(change) / Double(current)) * 100
    }
}

// MARK: - Forecast Data Extensions

extension ForecastData {

    /// Creates a sample forecast data for previews
    static var preview: ForecastData {
        let calendar = Calendar.current
        let now = Date()

        // Generate historical data points (past 6 months)
        var historical: [ForecastDataPoint] = []
        for i in (1...6).reversed() {
            if let date = calendar.date(byAdding: .month, value: -i, to: now) {
                historical.append(ForecastDataPoint(
                    forecastDate: date,
                    predictedEnrollment: 44 + i,
                    confidenceLower: nil,
                    confidenceUpper: nil,
                    isHistorical: true
                ))
            }
        }

        // Generate forecast data points (next 6 months)
        var forecast: [ForecastDataPoint] = []
        for i in 1...6 {
            if let date = calendar.date(byAdding: .month, value: i, to: now) {
                forecast.append(ForecastDataPoint(
                    forecastDate: date,
                    predictedEnrollment: 48 + i,
                    confidenceLower: 45 + i,
                    confidenceUpper: 51 + i,
                    isHistorical: false
                ))
            }
        }

        return ForecastData(
            facilityId: nil,
            historical: historical,
            forecast: forecast,
            modelVersion: "v1",
            generatedAt: now,
            confidenceNote: nil
        )
    }

    /// Creates a sample forecast with limited data warning
    static var previewLimitedData: ForecastData {
        ForecastData(
            facilityId: "facility-1",
            historical: [ForecastDataPoint.previewHistorical],
            forecast: [ForecastDataPoint.preview],
            modelVersion: "v1",
            generatedAt: Date(),
            confidenceNote: "Limited historical data available - forecast confidence is lower than usual"
        )
    }
}

// MARK: - Trend Direction

/// Direction of a metric trend.
enum TrendDirection: String, Codable, CaseIterable {
    case up = "up"
    case down = "down"
    case stable = "stable"

    var displayName: String {
        switch self {
        case .up:
            return String(localized: "Increasing")
        case .down:
            return String(localized: "Decreasing")
        case .stable:
            return String(localized: "Stable")
        }
    }

    /// SF Symbol name for this trend
    var iconName: String {
        switch self {
        case .up:
            return "arrow.up.right"
        case .down:
            return "arrow.down.right"
        case .stable:
            return "arrow.right"
        }
    }

    /// Color name for this trend (contextual - may vary by metric)
    var defaultColor: String {
        switch self {
        case .up:
            return "green"
        case .down:
            return "red"
        case .stable:
            return "gray"
        }
    }
}

// MARK: - Analytics Time Period

/// Time periods for analytics queries.
enum AnalyticsTimePeriod: String, Codable, CaseIterable {
    case day = "day"
    case week = "week"
    case month = "month"
    case quarter = "quarter"
    case year = "year"

    var displayName: String {
        switch self {
        case .day:
            return String(localized: "Day")
        case .week:
            return String(localized: "Week")
        case .month:
            return String(localized: "Month")
        case .quarter:
            return String(localized: "Quarter")
        case .year:
            return String(localized: "Year")
        }
    }

    /// Number of days in this period (approximate)
    var approximateDays: Int {
        switch self {
        case .day:
            return 1
        case .week:
            return 7
        case .month:
            return 30
        case .quarter:
            return 90
        case .year:
            return 365
        }
    }
}

// MARK: - Analytics Request

/// Request parameters for fetching analytics data.
struct AnalyticsRequest: Codable {

    /// Time period for the analytics
    let period: AnalyticsTimePeriod

    /// Start date for the analytics range
    let startDate: Date?

    /// End date for the analytics range
    let endDate: Date?

    /// Facility ID for facility-specific analytics
    let facilityId: String?

    /// Categories to include (nil for all)
    let categories: [MetricCategory]?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case period
        case startDate = "start_date"
        case endDate = "end_date"
        case facilityId = "facility_id"
        case categories
    }
}

// MARK: - Forecast Request

/// Request parameters for fetching enrollment forecast.
struct ForecastRequest: Codable {

    /// Facility ID for facility-specific forecast
    let facilityId: String?

    /// Number of periods to forecast
    let forecastPeriods: Int

    /// Time period unit for the forecast
    let periodUnit: AnalyticsTimePeriod

    /// Whether to include historical data
    let includeHistorical: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case facilityId = "facility_id"
        case forecastPeriods = "forecast_periods"
        case periodUnit = "period_unit"
        case includeHistorical = "include_historical"
    }
}
