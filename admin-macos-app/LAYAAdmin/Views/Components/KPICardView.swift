//
//  KPICardView.swift
//  LAYAAdmin
//
//  Reusable KPI card component for displaying key performance indicators
//  in the dashboard. Supports value display, trends, and status indicators.
//

import SwiftUI

// MARK: - KPI Card View

/// A reusable card component for displaying KPI metrics.
///
/// Features:
/// - Icon with customizable color
/// - Title and primary value display
/// - Optional subtitle/description
/// - Trend indicator with change percentage
/// - Status level coloring
///
/// Usage:
/// ```swift
/// KPICardView(
///     title: "Enrollment",
///     value: "48/52",
///     subtitle: "4 spots available",
///     iconName: "person.2.fill",
///     color: .green,
///     trend: .up,
///     changeValue: "+3"
/// )
/// ```
struct KPICardView: View {

    // MARK: - Properties

    /// Card title
    let title: String

    /// Primary value to display
    let value: String

    /// Optional subtitle or description
    let subtitle: String?

    /// SF Symbol icon name
    let iconName: String

    /// Primary color for the card
    let color: Color

    /// Optional trend direction
    let trend: TrendDirection?

    /// Optional change value (e.g., "+5%")
    let changeValue: String?

    /// Optional status level
    let level: DashboardLevel?

    // MARK: - Initializers

    /// Creates a KPI card with all properties
    init(
        title: String,
        value: String,
        subtitle: String? = nil,
        iconName: String,
        color: Color,
        trend: TrendDirection? = nil,
        changeValue: String? = nil,
        level: DashboardLevel? = nil
    ) {
        self.title = title
        self.value = value
        self.subtitle = subtitle
        self.iconName = iconName
        self.color = color
        self.trend = trend
        self.changeValue = changeValue
        self.level = level
    }

    /// Creates a KPI card from DashboardCardData
    init(cardData: DashboardCardData) {
        self.title = cardData.title
        self.value = cardData.value
        self.subtitle = cardData.subtitle
        self.iconName = cardData.iconName
        self.color = Color(cardData.color)
        self.trend = cardData.trend
        self.changeValue = cardData.changeValue
        self.level = cardData.level
    }

    /// Creates a KPI card from KPIMetric
    init(metric: KPIMetric) {
        self.title = metric.metricName
        self.value = metric.formattedValue
        self.subtitle = metric.categoryDisplayName
        self.iconName = metric.category.iconName
        self.color = Color(metric.changeColor)
        self.trend = metric.isPositiveChange ? .up : .down
        self.changeValue = metric.formattedChange
        self.level = nil
    }

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Header row with icon and trend
            headerRow

            // Value display
            valueSection

            // Subtitle and change indicator
            if subtitle != nil || changeValue != nil {
                footerRow
            }
        }
        .padding()
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(cardBackground)
        .cornerRadius(12)
        .shadow(color: Color.black.opacity(0.05), radius: 4, x: 0, y: 2)
    }

    // MARK: - Header Row

    private var headerRow: some View {
        HStack {
            // Icon with colored background
            iconView

            Spacer()

            // Level indicator badge
            if let level = level {
                levelBadge(level)
            }
        }
    }

    private var iconView: some View {
        ZStack {
            Circle()
                .fill(color.opacity(0.15))
                .frame(width: 40, height: 40)

            Image(systemName: iconName)
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(color)
        }
    }

    private func levelBadge(_ level: DashboardLevel) -> some View {
        HStack(spacing: 4) {
            Image(systemName: level.iconName)
                .font(.caption2)

            Text(level.displayName)
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(levelColor(level).opacity(0.15))
        .foregroundColor(levelColor(level))
        .cornerRadius(6)
    }

    // MARK: - Value Section

    private var valueSection: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.subheadline)
                .foregroundColor(.secondary)
                .lineLimit(1)

            Text(value)
                .font(.system(size: 28, weight: .bold, design: .rounded))
                .foregroundColor(.primary)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
        }
    }

    // MARK: - Footer Row

    private var footerRow: some View {
        HStack(spacing: 8) {
            // Subtitle
            if let subtitle = subtitle {
                Text(subtitle)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(1)
            }

            Spacer()

            // Trend indicator
            if let trend = trend, let change = changeValue {
                trendIndicator(trend: trend, change: change)
            }
        }
    }

    private func trendIndicator(trend: TrendDirection, change: String) -> some View {
        HStack(spacing: 2) {
            Image(systemName: trend.iconName)
                .font(.caption2)

            Text(change)
                .font(.caption)
                .fontWeight(.medium)
        }
        .foregroundColor(trendColor(trend))
    }

    // MARK: - Background

    private var cardBackground: some View {
        Color(NSColor.controlBackgroundColor)
    }

    // MARK: - Helper Methods

    private func levelColor(_ level: DashboardLevel) -> Color {
        switch level {
        case .high:
            return .green
        case .medium:
            return .orange
        case .low:
            return .red
        }
    }

    private func trendColor(_ trend: TrendDirection) -> Color {
        switch trend {
        case .up:
            return .green
        case .down:
            return .red
        case .stable:
            return .secondary
        }
    }
}

// MARK: - Color Extension for String Color Names

extension Color {

    /// Creates a Color from a string color name.
    /// Supports common color names used in the dashboard models.
    init(_ colorName: String) {
        switch colorName.lowercased() {
        case "green":
            self = .green
        case "red":
            self = .red
        case "orange":
            self = .orange
        case "blue":
            self = .blue
        case "yellow":
            self = .yellow
        case "purple":
            self = .purple
        case "gray", "grey":
            self = .gray
        default:
            self = .accentColor
        }
    }
}

// MARK: - Compact KPI Card

/// A smaller, more compact version of the KPI card.
/// Useful for displaying quick stats in the dashboard header.
struct CompactKPICardView: View {

    // MARK: - Properties

    let title: String
    let value: String
    let iconName: String
    let color: Color

    // MARK: - Body

    var body: some View {
        HStack(spacing: 12) {
            // Icon
            ZStack {
                RoundedRectangle(cornerRadius: 8)
                    .fill(color.opacity(0.15))
                    .frame(width: 36, height: 36)

                Image(systemName: iconName)
                    .font(.system(size: 16, weight: .semibold))
                    .foregroundColor(color)
            }

            // Content
            VStack(alignment: .leading, spacing: 2) {
                Text(value)
                    .font(.headline)
                    .fontWeight(.bold)
                    .foregroundColor(.primary)

                Text(title)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(10)
    }
}

// MARK: - KPI Card Grid

/// A grid layout for displaying multiple KPI cards.
/// Automatically adjusts columns based on available width.
struct KPICardGrid: View {

    // MARK: - Properties

    let cards: [DashboardCardData]

    /// Minimum width for each card
    let minCardWidth: CGFloat

    // MARK: - Initializers

    init(cards: [DashboardCardData], minCardWidth: CGFloat = 200) {
        self.cards = cards
        self.minCardWidth = minCardWidth
    }

    // MARK: - Body

    var body: some View {
        LazyVGrid(
            columns: [
                GridItem(.adaptive(minimum: minCardWidth), spacing: 16)
            ],
            spacing: 16
        ) {
            ForEach(cards) { card in
                KPICardView(cardData: card)
            }
        }
    }
}

// MARK: - KPI Metrics Grid

/// A grid layout for displaying KPI metrics.
struct KPIMetricsGrid: View {

    // MARK: - Properties

    let metrics: [KPIMetric]

    /// Minimum width for each card
    let minCardWidth: CGFloat

    // MARK: - Initializers

    init(metrics: [KPIMetric], minCardWidth: CGFloat = 220) {
        self.metrics = metrics
        self.minCardWidth = minCardWidth
    }

    // MARK: - Body

    var body: some View {
        LazyVGrid(
            columns: [
                GridItem(.adaptive(minimum: minCardWidth), spacing: 16)
            ],
            spacing: 16
        ) {
            ForEach(metrics) { metric in
                KPICardView(metric: metric)
            }
        }
    }
}

// MARK: - Preview

#Preview("KPI Card - Enrollment") {
    KPICardView(
        title: "Enrollment",
        value: "48/52",
        subtitle: "4 spots available",
        iconName: "person.2.fill",
        color: .green,
        trend: .up,
        changeValue: "+3",
        level: .high
    )
    .frame(width: 240)
    .padding()
}

#Preview("KPI Card - Attendance") {
    KPICardView(
        title: "Attendance",
        value: "87.5%",
        subtitle: "Daily average",
        iconName: "calendar.badge.clock",
        color: .blue,
        trend: .down,
        changeValue: "-2.1%",
        level: .medium
    )
    .frame(width: 240)
    .padding()
}

#Preview("KPI Card - Compliance Warning") {
    KPICardView(
        title: "Compliance",
        value: "72.0%",
        subtitle: "Overall score",
        iconName: "checkmark.shield.fill",
        color: .orange,
        trend: .stable,
        changeValue: "0%",
        level: .low
    )
    .frame(width: 240)
    .padding()
}

#Preview("KPI Card from DashboardCardData") {
    KPICardView(cardData: .preview)
        .frame(width: 240)
        .padding()
}

#Preview("KPI Card from KPIMetric") {
    KPICardView(metric: .preview)
        .frame(width: 240)
        .padding()
}

#Preview("Compact KPI Card") {
    CompactKPICardView(
        title: "Present Today",
        value: "42",
        iconName: "person.fill.checkmark",
        color: .green
    )
    .padding()
}

#Preview("KPI Card Grid") {
    KPICardGrid(cards: [
        .enrollment(from: .preview),
        .attendance(from: .preview),
        .compliance(from: .preview)
    ])
    .padding()
}

#Preview("KPI Metrics Grid") {
    KPIMetricsGrid(metrics: [
        .preview,
        .previewAttendance,
        .previewRevenue,
        .previewStaffing
    ])
    .padding()
}
