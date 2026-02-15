//
//  EnrollmentChartView.swift
//  LAYAAdmin
//
//  Enrollment forecast chart view using Swift Charts.
//  Displays historical enrollment data and AI-predicted forecasts
//  with confidence intervals for childcare facility planning.
//

import SwiftUI
import Charts

// MARK: - Enrollment Chart View

/// A chart view displaying enrollment trends with historical data and AI forecasts.
///
/// Features:
/// - Line chart showing historical enrollment numbers
/// - Forecast line with confidence interval bands
/// - Interactive hover to show point details
/// - Configurable time range display
/// - Support for both historical and predicted data points
///
/// Uses Swift Charts framework with LineMark for trend visualization.
struct EnrollmentChartView: View {

    // MARK: - Properties

    /// The forecast data to display
    let forecast: ForecastData

    /// Chart height (default: 250)
    var chartHeight: CGFloat = 250

    /// Whether to show confidence bands (default: true)
    var showConfidenceBands: Bool = true

    /// Whether to show legend (default: true)
    var showLegend: Bool = true

    /// Whether to show grid lines (default: true)
    var showGridLines: Bool = true

    /// Selected data point for highlighting
    @State private var selectedPoint: ForecastDataPoint?

    /// Hover position for tooltip
    @State private var hoverPosition: CGPoint?

    // MARK: - Computed Properties

    /// All data points combined for chart display
    private var allDataPoints: [ForecastDataPoint] {
        forecast.allDataPoints
    }

    /// Historical data points only
    private var historicalData: [ForecastDataPoint] {
        forecast.historical
    }

    /// Forecast data points only
    private var forecastData: [ForecastDataPoint] {
        forecast.forecast
    }

    /// Y-axis range for the chart
    private var yAxisRange: ClosedRange<Int> {
        let allValues = allDataPoints.map { $0.predictedEnrollment }
        let minValue = max(0, (allValues.min() ?? 0) - 5)
        let maxValue = (allValues.max() ?? 50) + 10
        return minValue...maxValue
    }

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Chart header with title
            chartHeader

            // Main chart
            chartContent
                .frame(height: chartHeight)

            // Legend
            if showLegend {
                chartLegend
            }

            // Confidence note
            if let note = forecast.confidenceNote {
                confidenceNoteView(note)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    // MARK: - Chart Header

    private var chartHeader: some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                Text(String(localized: "Enrollment Trend"))
                    .font(.headline)

                Text(String(localized: "Historical data and AI forecast"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Spacer()

            // Model version badge
            Text("v\(forecast.modelVersion)")
                .font(.caption2)
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color.accentColor.opacity(0.1))
                .cornerRadius(4)
        }
    }

    // MARK: - Chart Content

    private var chartContent: some View {
        Chart {
            // Confidence bands (area between lower and upper bounds)
            if showConfidenceBands {
                ForEach(forecastData) { point in
                    if let lower = point.confidenceLower, let upper = point.confidenceUpper {
                        AreaMark(
                            x: .value("Date", point.forecastDate),
                            yStart: .value("Lower", lower),
                            yEnd: .value("Upper", upper)
                        )
                        .foregroundStyle(
                            LinearGradient(
                                colors: [Color.purple.opacity(0.2), Color.purple.opacity(0.05)],
                                startPoint: .top,
                                endPoint: .bottom
                            )
                        )
                        .interpolationMethod(.catmullRom)
                    }
                }
            }

            // Historical enrollment line
            ForEach(historicalData) { point in
                LineMark(
                    x: .value("Date", point.forecastDate),
                    y: .value("Enrollment", point.predictedEnrollment)
                )
                .foregroundStyle(Color.blue)
                .interpolationMethod(.catmullRom)
                .lineStyle(StrokeStyle(lineWidth: 2.5))
                .symbol {
                    Circle()
                        .fill(Color.blue)
                        .frame(width: 8, height: 8)
                }
            }

            // Forecast line
            ForEach(forecastData) { point in
                LineMark(
                    x: .value("Date", point.forecastDate),
                    y: .value("Enrollment", point.predictedEnrollment)
                )
                .foregroundStyle(Color.purple)
                .interpolationMethod(.catmullRom)
                .lineStyle(StrokeStyle(lineWidth: 2.5, dash: [5, 3]))
                .symbol {
                    Circle()
                        .strokeBorder(Color.purple, lineWidth: 2)
                        .background(Circle().fill(Color.white))
                        .frame(width: 8, height: 8)
                }
            }

            // Connection line between historical and forecast
            if let lastHistorical = historicalData.last, let firstForecast = forecastData.first {
                LineMark(
                    x: .value("Date", lastHistorical.forecastDate),
                    y: .value("Enrollment", lastHistorical.predictedEnrollment)
                )
                .foregroundStyle(Color.gray.opacity(0.5))
                .interpolationMethod(.linear)
                .lineStyle(StrokeStyle(lineWidth: 1.5, dash: [2, 2]))

                LineMark(
                    x: .value("Date", firstForecast.forecastDate),
                    y: .value("Enrollment", firstForecast.predictedEnrollment)
                )
                .foregroundStyle(Color.gray.opacity(0.5))
                .interpolationMethod(.linear)
                .lineStyle(StrokeStyle(lineWidth: 1.5, dash: [2, 2]))
            }

            // Selection rule line
            if let selected = selectedPoint {
                RuleMark(x: .value("Selected", selected.forecastDate))
                    .foregroundStyle(Color.gray.opacity(0.5))
                    .lineStyle(StrokeStyle(lineWidth: 1, dash: [4, 2]))
                    .annotation(position: .top, alignment: .center) {
                        selectedPointAnnotation(selected)
                    }
            }
        }
        .chartXAxis {
            AxisMarks(values: .stride(by: .month, count: 2)) { value in
                if let date = value.as(Date.self) {
                    AxisValueLabel {
                        Text(date, format: .dateTime.month(.abbreviated))
                            .font(.caption)
                    }
                    if showGridLines {
                        AxisGridLine(stroke: StrokeStyle(lineWidth: 0.5, dash: [2, 2]))
                    }
                }
            }
        }
        .chartYAxis {
            AxisMarks(position: .leading) { value in
                if let enrollment = value.as(Int.self) {
                    AxisValueLabel {
                        Text("\(enrollment)")
                            .font(.caption)
                    }
                    if showGridLines {
                        AxisGridLine(stroke: StrokeStyle(lineWidth: 0.5, dash: [2, 2]))
                    }
                }
            }
        }
        .chartYScale(domain: yAxisRange)
        .chartOverlay { proxy in
            GeometryReader { geometry in
                Rectangle()
                    .fill(Color.clear)
                    .contentShape(Rectangle())
                    .onContinuousHover { phase in
                        switch phase {
                        case .active(let location):
                            hoverPosition = location
                            selectedPoint = findNearestPoint(at: location, in: proxy, geometry: geometry)
                        case .ended:
                            hoverPosition = nil
                            selectedPoint = nil
                        }
                    }
            }
        }
    }

    // MARK: - Chart Legend

    private var chartLegend: some View {
        HStack(spacing: 20) {
            legendItem(color: .blue, label: String(localized: "Historical"), isDashed: false)
            legendItem(color: .purple, label: String(localized: "Forecast"), isDashed: true)
            if showConfidenceBands {
                legendItem(color: .purple.opacity(0.3), label: String(localized: "Confidence"), isArea: true)
            }

            Spacer()

            // Summary stats
            if let current = forecast.currentEnrollment {
                Text(String(localized: "Current: \(current)"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding(.top, 8)
    }

    private func legendItem(color: Color, label: String, isDashed: Bool = false, isArea: Bool = false) -> some View {
        HStack(spacing: 6) {
            if isArea {
                RoundedRectangle(cornerRadius: 2)
                    .fill(color)
                    .frame(width: 16, height: 10)
            } else {
                ZStack {
                    Rectangle()
                        .fill(color)
                        .frame(width: 20, height: isDashed ? 0 : 3)
                    if isDashed {
                        HStack(spacing: 2) {
                            Rectangle()
                                .fill(color)
                                .frame(width: 6, height: 3)
                            Rectangle()
                                .fill(color)
                                .frame(width: 6, height: 3)
                        }
                    }
                }
            }

            Text(label)
                .font(.caption)
                .foregroundColor(.secondary)
        }
    }

    // MARK: - Confidence Note

    private func confidenceNoteView(_ note: String) -> some View {
        HStack(spacing: 6) {
            Image(systemName: "info.circle")
                .font(.caption)
                .foregroundColor(.orange)

            Text(note)
                .font(.caption)
                .foregroundColor(.secondary)
                .lineLimit(2)
        }
        .padding(8)
        .background(Color.orange.opacity(0.1))
        .cornerRadius(6)
    }

    // MARK: - Selection Annotation

    private func selectedPointAnnotation(_ point: ForecastDataPoint) -> some View {
        VStack(alignment: .center, spacing: 4) {
            Text(point.isHistorical ? String(localized: "Actual") : String(localized: "Predicted"))
                .font(.caption2)
                .foregroundColor(.secondary)

            Text("\(point.predictedEnrollment)")
                .font(.subheadline)
                .fontWeight(.semibold)

            Text(point.forecastDate, format: .dateTime.month().year())
                .font(.caption2)
                .foregroundColor(.secondary)

            if let range = point.confidenceRange {
                Text("(\(range))")
                    .font(.caption2)
                    .foregroundColor(.purple)
            }
        }
        .padding(8)
        .background(Color(NSColor.windowBackgroundColor))
        .cornerRadius(8)
        .shadow(radius: 3)
    }

    // MARK: - Helper Methods

    private func findNearestPoint(at location: CGPoint, in proxy: ChartProxy, geometry: GeometryProxy) -> ForecastDataPoint? {
        let xPosition = location.x - geometry[proxy.plotAreaFrame].origin.x

        guard let date: Date = proxy.value(atX: xPosition) else { return nil }

        // Find the nearest point by date
        var nearestPoint: ForecastDataPoint?
        var minDistance: TimeInterval = .infinity

        for point in allDataPoints {
            let distance = abs(point.forecastDate.timeIntervalSince(date))
            if distance < minDistance {
                minDistance = distance
                nearestPoint = point
            }
        }

        return nearestPoint
    }
}

// MARK: - Compact Enrollment Chart

/// A compact version of the enrollment chart for dashboard display.
struct CompactEnrollmentChartView: View {

    // MARK: - Properties

    let forecast: ForecastData
    var chartHeight: CGFloat = 120

    // MARK: - Body

    var body: some View {
        Chart {
            // Historical line
            ForEach(forecast.historical) { point in
                LineMark(
                    x: .value("Date", point.forecastDate),
                    y: .value("Enrollment", point.predictedEnrollment)
                )
                .foregroundStyle(Color.blue)
                .interpolationMethod(.catmullRom)
                .lineStyle(StrokeStyle(lineWidth: 2))
            }

            // Forecast line
            ForEach(forecast.forecast) { point in
                LineMark(
                    x: .value("Date", point.forecastDate),
                    y: .value("Enrollment", point.predictedEnrollment)
                )
                .foregroundStyle(Color.purple)
                .interpolationMethod(.catmullRom)
                .lineStyle(StrokeStyle(lineWidth: 2, dash: [4, 2]))
            }
        }
        .chartXAxis(.hidden)
        .chartYAxis(.hidden)
        .frame(height: chartHeight)
    }
}

// MARK: - Enrollment Stats Card

/// Card showing enrollment statistics alongside the chart.
struct EnrollmentStatsCard: View {

    // MARK: - Properties

    let forecast: ForecastData

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header
            HStack {
                Image(systemName: "chart.line.uptrend.xyaxis")
                    .font(.title2)
                    .foregroundColor(.accentColor)

                Text(String(localized: "Enrollment Statistics"))
                    .font(.headline)

                Spacer()
            }

            Divider()

            // Stats grid
            LazyVGrid(columns: [
                GridItem(.flexible()),
                GridItem(.flexible())
            ], spacing: 16) {
                statItem(
                    title: String(localized: "Current"),
                    value: "\(forecast.currentEnrollment ?? 0)",
                    icon: "person.2.fill",
                    color: .blue
                )

                statItem(
                    title: String(localized: "Predicted"),
                    value: "\(forecast.nextPeriodEnrollment ?? 0)",
                    icon: "sparkles",
                    color: .purple
                )

                if let change = forecast.predictedChange {
                    statItem(
                        title: String(localized: "Change"),
                        value: change >= 0 ? "+\(change)" : "\(change)",
                        icon: change >= 0 ? "arrow.up.right" : "arrow.down.right",
                        color: change >= 0 ? .green : .red
                    )
                }

                if let percentage = forecast.predictedChangePercentage {
                    statItem(
                        title: String(localized: "Growth"),
                        value: String(format: "%+.1f%%", percentage),
                        icon: "percent",
                        color: percentage >= 0 ? .green : .red
                    )
                }
            }

            // Model info
            HStack {
                Text(String(localized: "Model: \(forecast.modelVersion)"))
                    .font(.caption)
                    .foregroundColor(.secondary)

                Spacer()

                Text(String(localized: "Generated: \(forecast.generatedAt.displayDate)"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func statItem(title: String, value: String, icon: String, color: Color) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.title3)
                .foregroundColor(color)
                .frame(width: 32)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(value)
                    .font(.title3)
                    .fontWeight(.semibold)
                    .foregroundColor(color == .blue ? .primary : color)
            }

            Spacer()
        }
    }
}

// MARK: - Forecast Summary View

/// A summary view of the forecast for quick display.
struct ForecastSummaryView: View {

    // MARK: - Properties

    let forecast: ForecastData
    var showChart: Bool = true

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Enrollment Forecast"))
                        .font(.headline)

                    if let change = forecast.predictedChange, let percentage = forecast.predictedChangePercentage {
                        HStack(spacing: 4) {
                            Image(systemName: change >= 0 ? "arrow.up.right" : "arrow.down.right")
                                .font(.caption)

                            Text(String(format: "%+d (%+.1f%%)", change, percentage))
                                .font(.subheadline)
                        }
                        .foregroundColor(change >= 0 ? .green : .red)
                    }
                }

                Spacer()

                VStack(alignment: .trailing, spacing: 2) {
                    Text("\(forecast.nextPeriodEnrollment ?? 0)")
                        .font(.title)
                        .fontWeight(.bold)

                    Text(String(localized: "Predicted"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            if showChart {
                CompactEnrollmentChartView(forecast: forecast, chartHeight: 80)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Preview

#Preview("Enrollment Chart View") {
    EnrollmentChartView(forecast: .preview)
        .frame(width: 600, height: 400)
        .padding()
}

#Preview("Compact Enrollment Chart") {
    CompactEnrollmentChartView(forecast: .preview)
        .frame(width: 400, height: 150)
        .padding()
}

#Preview("Enrollment Stats Card") {
    EnrollmentStatsCard(forecast: .preview)
        .frame(width: 350)
        .padding()
}

#Preview("Forecast Summary") {
    ForecastSummaryView(forecast: .preview)
        .frame(width: 400)
        .padding()
}

#Preview("Chart with Limited Data") {
    EnrollmentChartView(forecast: .previewLimitedData)
        .frame(width: 600, height: 400)
        .padding()
}
