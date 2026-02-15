//
//  DashboardView.swift
//  LAYAAdmin
//
//  Main dashboard view for the LAYA Admin application.
//  Displays KPI cards, alerts, enrollment statistics, and quick actions
//  for facility directors and administrators.
//

import SwiftUI

// MARK: - Dashboard View

/// Main dashboard view providing an overview of key metrics and alerts.
///
/// Features:
/// - Quick stats header with today's key numbers
/// - KPI cards for enrollment, attendance, and compliance
/// - Alerts section for items requiring attention
/// - Auto-refresh capability
/// - Pull-to-refresh support
/// - Loading and error states
///
/// The dashboard aggregates data from both Gibbon CMS and AI Service
/// to provide a comprehensive facility overview.
struct DashboardView: View {

    // MARK: - Properties

    /// The dashboard view model
    @StateObject private var viewModel = DashboardViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Scroll position for pull-to-refresh
    @State private var scrollPosition: CGFloat = 0

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if let error = viewModel.error, !viewModel.hasData {
                errorView(error)
            } else {
                dashboardContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Dashboard"))
        .toolbar {
            dashboardToolbar
        }
        .task {
            await viewModel.loadDashboard()
        }
        .onAppear {
            viewModel.startAutoRefresh()
        }
        .onDisappear {
            viewModel.stopAutoRefresh()
        }
        .alert(
            String(localized: "Error Loading Dashboard"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadDashboard()
                }
            }
            Button(String(localized: "Dismiss"), role: .cancel) {
                viewModel.clearError()
            }
        } message: { error in
            Text(error.localizedDescription)
        }
    }

    // MARK: - Dashboard Content

    private var dashboardContent: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                // Welcome header with quick stats
                welcomeHeader

                // Main KPI cards section
                kpiCardsSection

                // Detailed KPI metrics from AI Service
                if !viewModel.kpis.isEmpty {
                    aiKPIMetricsSection
                }

                // Alerts and pending items in a two-column layout on wider screens
                alertsAndPendingSection

                // Enrollment forecast preview (if available)
                if viewModel.forecast != nil {
                    forecastPreviewSection
                }

                // Footer with last refresh time
                refreshFooter
            }
            .padding(24)
        }
        .refreshable {
            await viewModel.refresh()
        }
    }

    // MARK: - Alerts and Pending Section

    /// Combined section for alerts and pending items
    private var alertsAndPendingSection: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Section title
            sectionHeader(
                title: String(localized: "Action Required"),
                subtitle: String(localized: "Items needing your attention")
            )

            // Two-column layout for alerts and pending items
            HStack(alignment: .top, spacing: 24) {
                // Alerts column
                VStack(alignment: .leading, spacing: 0) {
                    AlertListSection(
                        alerts: viewModel.alerts,
                        maxDisplayCount: 4,
                        onAcknowledge: { alertId in
                            viewModel.acknowledgeAlert(alertId: alertId)
                        },
                        onNavigate: { alert in
                            handleAlertNavigation(alert)
                        }
                    )
                }
                .frame(maxWidth: .infinity)

                // Pending items column
                VStack(alignment: .leading, spacing: 0) {
                    PendingItemsSection(
                        items: pendingApprovals,
                        maxDisplayCount: 4,
                        onItemSelected: { item in
                            handlePendingItemNavigation(item)
                        }
                    )
                }
                .frame(maxWidth: .infinity)
            }
        }
    }

    /// Sample pending approvals (in production, this would come from the ViewModel)
    private var pendingApprovals: [PendingItem] {
        // Generate pending items from alerts and dashboard data
        var items: [PendingItem] = []

        // Convert enrollment-related alerts to pending items
        let enrollmentAlerts = viewModel.alerts.filter {
            $0.category == .enrollment && !$0.isAcknowledged
        }

        for alert in enrollmentAlerts {
            items.append(PendingItem(
                id: "pending-\(alert.id)",
                type: .enrollmentApplication,
                title: alert.title,
                subtitle: alert.message,
                createdAt: alert.createdAt,
                priority: alert.severity == .critical ? .urgent : .normal,
                relatedEntityId: alert.relatedEntityId
            ))
        }

        // Convert compliance-related alerts to certification renewals
        let complianceAlerts = viewModel.alerts.filter {
            $0.category == .compliance && !$0.isAcknowledged
        }

        for alert in complianceAlerts {
            items.append(PendingItem(
                id: "pending-\(alert.id)",
                type: .certificationRenewal,
                title: alert.title,
                subtitle: alert.message,
                createdAt: alert.createdAt,
                priority: alert.severity == .critical ? .urgent : .normal,
                relatedEntityId: alert.relatedEntityId
            ))
        }

        // Convert finance-related alerts to invoice approvals
        let financeAlerts = viewModel.alerts.filter {
            $0.category == .finance && !$0.isAcknowledged
        }

        for alert in financeAlerts {
            items.append(PendingItem(
                id: "pending-\(alert.id)",
                type: .invoiceApproval,
                title: alert.title,
                subtitle: alert.message,
                createdAt: alert.createdAt,
                priority: alert.severity == .critical ? .urgent : .normal,
                relatedEntityId: alert.relatedEntityId
            ))
        }

        // Convert staffing-related alerts to leave requests
        let staffingAlerts = viewModel.alerts.filter {
            $0.category == .staffing && !$0.isAcknowledged
        }

        for alert in staffingAlerts {
            items.append(PendingItem(
                id: "pending-\(alert.id)",
                type: .staffLeaveRequest,
                title: alert.title,
                subtitle: alert.message,
                createdAt: alert.createdAt,
                priority: alert.severity == .critical ? .urgent : .normal,
                relatedEntityId: alert.relatedEntityId
            ))
        }

        return items
    }

    /// Handles navigation when an alert is tapped
    private func handleAlertNavigation(_ alert: DashboardAlert) {
        // Navigate based on alert category and related entity
        switch alert.category {
        case .enrollment:
            // Navigate to enrollment/children section
            break
        case .staffing:
            // Navigate to staff section
            break
        case .compliance:
            // Navigate to compliance/analytics section
            break
        case .finance:
            // Navigate to finance section
            break
        case .attendance:
            // Navigate to attendance reports
            break
        case .general:
            // Show general notification detail
            break
        }
    }

    /// Handles navigation when a pending item is tapped
    private func handlePendingItemNavigation(_ item: PendingItem) {
        // Navigate based on item type
        switch item.type {
        case .enrollmentApplication:
            // Navigate to enrollment application detail
            break
        case .staffLeaveRequest:
            // Navigate to leave request detail
            break
        case .invoiceApproval:
            // Navigate to invoice detail
            break
        case .documentReview:
            // Navigate to document detail
            break
        case .certificationRenewal:
            // Navigate to staff certification detail
            break
        case .other:
            // Generic navigation
            break
        }
    }

    // MARK: - Welcome Header

    private var welcomeHeader: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Greeting
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(greetingText)
                        .font(.largeTitle)
                        .fontWeight(.bold)

                    Text(String(localized: "Here's your facility overview"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }

                Spacer()

                // Refresh button
                Button(action: {
                    Task {
                        await viewModel.refresh()
                    }
                }) {
                    Label(String(localized: "Refresh"), systemImage: "arrow.clockwise")
                }
                .buttonStyle(.bordered)
                .disabled(viewModel.isLoading)
            }

            // Quick stats row
            if let quickStats = viewModel.quickStats {
                quickStatsRow(quickStats)
            } else if let summary = viewModel.summary {
                quickStatsFromSummary(summary)
            }
        }
    }

    private var greetingText: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let name = authViewModel.currentUser?.firstName ?? ""

        if hour < 12 {
            return name.isEmpty
                ? String(localized: "Good morning")
                : String(localized: "Good morning, \(name)")
        } else if hour < 17 {
            return name.isEmpty
                ? String(localized: "Good afternoon")
                : String(localized: "Good afternoon, \(name)")
        } else {
            return name.isEmpty
                ? String(localized: "Good evening")
                : String(localized: "Good evening, \(name)")
        }
    }

    private func quickStatsRow(_ stats: QuickStats) -> some View {
        HStack(spacing: 16) {
            CompactKPICardView(
                title: String(localized: "Present Today"),
                value: "\(stats.childrenPresentToday)",
                iconName: "person.fill.checkmark",
                color: .green
            )

            CompactKPICardView(
                title: String(localized: "Staff on Duty"),
                value: "\(stats.staffOnDutyToday)",
                iconName: "person.badge.key.fill",
                color: .blue
            )

            CompactKPICardView(
                title: String(localized: "Pending Actions"),
                value: "\(stats.pendingActions)",
                iconName: "exclamationmark.circle.fill",
                color: stats.pendingActions > 0 ? .orange : .gray
            )

            if stats.unreadMessages > 0 {
                CompactKPICardView(
                    title: String(localized: "Messages"),
                    value: "\(stats.unreadMessages)",
                    iconName: "envelope.badge.fill",
                    color: .purple
                )
            }

            Spacer()
        }
    }

    private func quickStatsFromSummary(_ summary: DashboardSummary) -> some View {
        HStack(spacing: 16) {
            CompactKPICardView(
                title: String(localized: "Enrolled"),
                value: "\(summary.totalEnrolled)",
                iconName: "person.2.fill",
                color: .blue
            )

            CompactKPICardView(
                title: String(localized: "Capacity"),
                value: "\(summary.totalCapacity)",
                iconName: "building.2.fill",
                color: .purple
            )

            CompactKPICardView(
                title: String(localized: "Available"),
                value: "\(summary.availableSpots)",
                iconName: "person.badge.plus",
                color: summary.availableSpots > 0 ? .green : .orange
            )

            Spacer()
        }
    }

    // MARK: - KPI Cards Section

    private var kpiCardsSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionHeader(
                title: String(localized: "Key Metrics"),
                subtitle: String(localized: "Facility performance overview")
            )

            if let summary = viewModel.summary {
                KPICardGrid(cards: [
                    .enrollment(from: summary),
                    .attendance(from: summary),
                    .compliance(from: summary)
                ])
            } else {
                // Placeholder cards when no data
                KPICardGrid(cards: [])
                    .overlay {
                        Text(String(localized: "Loading metrics..."))
                            .foregroundColor(.secondary)
                    }
            }
        }
    }

    // MARK: - AI KPI Metrics Section

    private var aiKPIMetricsSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                sectionHeader(
                    title: String(localized: "Detailed Analytics"),
                    subtitle: String(localized: "AI-powered insights")
                )

                Spacer()

                // AI Service status indicator
                if viewModel.aiServiceAvailable {
                    HStack(spacing: 4) {
                        Circle()
                            .fill(.green)
                            .frame(width: 8, height: 8)
                        Text(String(localized: "AI Service Connected"))
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
            }

            KPIMetricsGrid(metrics: viewModel.kpis)
        }
    }

    // MARK: - Alerts Section (Legacy/Simple)

    /// A simple alerts section for compact layouts or when pending items are disabled.
    /// This is kept for backwards compatibility and can be used as an alternative
    /// to the full alertsAndPendingSection.
    private var alertsSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionHeader(
                title: String(localized: "Alerts & Notifications"),
                subtitle: String(localized: "\(viewModel.unacknowledgedAlertCount) items require attention")
            )

            VStack(spacing: 12) {
                ForEach(viewModel.sortedAlerts.prefix(5)) { alert in
                    DashboardAlertRow(
                        alert: alert,
                        onAcknowledge: {
                            viewModel.acknowledgeAlert(alertId: alert.id)
                        }
                    )
                }

                if viewModel.sortedAlerts.count > 5 {
                    Button(action: {
                        // Navigate to full alerts view
                    }) {
                        Text(String(localized: "View all \(viewModel.sortedAlerts.count) alerts"))
                            .font(.subheadline)
                    }
                    .buttonStyle(.link)
                }
            }
        }
    }

    // MARK: - Critical Alerts Banner

    /// A prominent banner shown when critical alerts exist
    private var criticalAlertsBanner: some View {
        Group {
            if !viewModel.criticalAlerts.isEmpty {
                VStack(spacing: 0) {
                    ForEach(viewModel.criticalAlerts.prefix(2)) { alert in
                        HStack(spacing: 12) {
                            Image(systemName: "exclamationmark.octagon.fill")
                                .font(.title3)
                                .foregroundColor(.white)

                            VStack(alignment: .leading, spacing: 2) {
                                Text(alert.title)
                                    .font(.subheadline)
                                    .fontWeight(.semibold)
                                    .foregroundColor(.white)

                                Text(alert.message)
                                    .font(.caption)
                                    .foregroundColor(.white.opacity(0.9))
                                    .lineLimit(1)
                            }

                            Spacer()

                            Button(action: {
                                viewModel.acknowledgeAlert(alertId: alert.id)
                            }) {
                                Text(String(localized: "Acknowledge"))
                                    .font(.caption)
                                    .fontWeight(.medium)
                            }
                            .buttonStyle(.borderedProminent)
                            .tint(.white.opacity(0.2))
                        }
                        .padding()
                        .background(Color.red)
                    }
                }
                .cornerRadius(12)
            }
        }
    }

    // MARK: - Forecast Preview Section

    private var forecastPreviewSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            sectionHeader(
                title: String(localized: "Enrollment Forecast"),
                subtitle: String(localized: "Predicted enrollment trend")
            )

            if let forecast = viewModel.forecast {
                ForecastPreviewCard(forecast: forecast)
            }
        }
    }

    // MARK: - Refresh Footer

    private var refreshFooter: some View {
        HStack {
            Spacer()

            HStack(spacing: 8) {
                Image(systemName: "clock")
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(String(localized: "Last updated: \(viewModel.formattedLastRefresh)"))
                    .font(.caption)
                    .foregroundColor(.secondary)

                if viewModel.autoRefreshEnabled {
                    Text("•")
                        .foregroundColor(.secondary)

                    Text(String(localized: "Auto-refresh enabled"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()
        }
        .padding(.top, 16)
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading dashboard..."))
                .font(.headline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Error View

    private func errorView(_ error: Error) -> some View {
        VStack(spacing: 20) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.system(size: 48))
                .foregroundColor(.orange)

            VStack(spacing: 8) {
                Text(String(localized: "Unable to Load Dashboard"))
                    .font(.headline)

                Text(error.localizedDescription)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
            }

            Button(action: {
                Task {
                    await viewModel.loadDashboard()
                }
            }) {
                Label(String(localized: "Try Again"), systemImage: "arrow.clockwise")
            }
            .buttonStyle(.borderedProminent)
        }
        .padding(40)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var dashboardToolbar: some ToolbarContent {
        ToolbarItemGroup(placement: .primaryAction) {
            // Auto-refresh toggle
            Toggle(isOn: Binding(
                get: { viewModel.autoRefreshEnabled },
                set: { _ in viewModel.toggleAutoRefresh() }
            )) {
                Label(
                    String(localized: "Auto-refresh"),
                    systemImage: viewModel.autoRefreshEnabled ? "arrow.triangle.2.circlepath.circle.fill" : "arrow.triangle.2.circlepath.circle"
                )
            }
            .help(String(localized: "Toggle automatic refresh"))

            // Manual refresh
            Button(action: {
                Task {
                    await viewModel.refresh()
                }
            }) {
                Label(String(localized: "Refresh"), systemImage: "arrow.clockwise")
            }
            .keyboardShortcut("r", modifiers: [.command])
            .disabled(viewModel.isLoading)
            .help(String(localized: "Refresh dashboard (Cmd+R)"))
        }
    }

    // MARK: - Helper Views

    private func sectionHeader(title: String, subtitle: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.title2)
                .fontWeight(.semibold)

            Text(subtitle)
                .font(.subheadline)
                .foregroundColor(.secondary)
        }
    }
}

// MARK: - Dashboard Alert Row

/// A row displaying a single dashboard alert with acknowledge action.
struct DashboardAlertRow: View {

    // MARK: - Properties

    let alert: DashboardAlert
    let onAcknowledge: () -> Void

    // MARK: - Body

    var body: some View {
        HStack(spacing: 12) {
            // Severity icon
            Image(systemName: alert.severity.iconName)
                .font(.title3)
                .foregroundColor(severityColor)
                .frame(width: 32)

            // Content
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(alert.title)
                        .font(.subheadline)
                        .fontWeight(.medium)

                    Spacer()

                    Text(alert.formattedDate)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Text(alert.message)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(2)
            }

            // Acknowledge button
            Button(action: onAcknowledge) {
                Image(systemName: "checkmark.circle")
                    .foregroundColor(.secondary)
            }
            .buttonStyle(.plain)
            .help(String(localized: "Acknowledge alert"))
        }
        .padding(12)
        .background(alertBackground)
        .cornerRadius(8)
    }

    private var severityColor: Color {
        switch alert.severity {
        case .critical:
            return .red
        case .warning:
            return .orange
        case .info:
            return .blue
        }
    }

    private var alertBackground: some View {
        RoundedRectangle(cornerRadius: 8)
            .fill(Color(NSColor.controlBackgroundColor))
            .overlay(
                RoundedRectangle(cornerRadius: 8)
                    .stroke(severityColor.opacity(0.3), lineWidth: 1)
            )
    }
}

// MARK: - Forecast Preview Card

/// A preview card showing enrollment forecast summary.
struct ForecastPreviewCard: View {

    // MARK: - Properties

    let forecast: ForecastData

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Header
            HStack {
                Image(systemName: "chart.line.uptrend.xyaxis")
                    .font(.title2)
                    .foregroundColor(.accentColor)

                Text(String(localized: "Enrollment Trend"))
                    .font(.headline)

                Spacer()

                if let changePercentage = forecast.predictedChangePercentage {
                    trendBadge(changePercentage)
                }
            }

            Divider()

            // Stats
            HStack(spacing: 24) {
                forecastStat(
                    title: String(localized: "Current"),
                    value: "\(forecast.currentEnrollment ?? 0)"
                )

                forecastStat(
                    title: String(localized: "Next Month"),
                    value: "\(forecast.nextPeriodEnrollment ?? 0)"
                )

                if let change = forecast.predictedChange {
                    forecastStat(
                        title: String(localized: "Change"),
                        value: change >= 0 ? "+\(change)" : "\(change)",
                        color: change >= 0 ? .green : .red
                    )
                }

                Spacer()
            }

            // Confidence note if available
            if let note = forecast.confidenceNote {
                HStack(spacing: 4) {
                    Image(systemName: "info.circle")
                        .font(.caption)

                    Text(note)
                        .font(.caption)
                }
                .foregroundColor(.secondary)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func trendBadge(_ percentage: Double) -> some View {
        HStack(spacing: 4) {
            Image(systemName: percentage >= 0 ? "arrow.up.right" : "arrow.down.right")
                .font(.caption)

            Text(String(format: "%+.1f%%", percentage))
                .font(.caption)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(percentage >= 0 ? Color.green.opacity(0.15) : Color.red.opacity(0.15))
        .foregroundColor(percentage >= 0 ? .green : .red)
        .cornerRadius(6)
    }

    private func forecastStat(title: String, value: String, color: Color = .primary) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.caption)
                .foregroundColor(.secondary)

            Text(value)
                .font(.title3)
                .fontWeight(.semibold)
                .foregroundColor(color)
        }
    }
}

// MARK: - Preview

#Preview("Dashboard View") {
    DashboardView()
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 800, height: 700)
}

#Preview("Dashboard View - Loading") {
    let view = DashboardView()
    return view
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 800, height: 600)
}

#Preview("Dashboard Alert Row") {
    VStack(spacing: 12) {
        DashboardAlertRow(alert: .previewCritical, onAcknowledge: {})
        DashboardAlertRow(alert: .preview, onAcknowledge: {})
        DashboardAlertRow(alert: .previewInfo, onAcknowledge: {})
    }
    .padding()
}

#Preview("Forecast Preview Card") {
    ForecastPreviewCard(forecast: .preview)
        .frame(width: 500)
        .padding()
}
