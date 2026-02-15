//
//  AnalyticsDashboardView.swift
//  LAYAAdmin
//
//  AI Analytics Dashboard view for the LAYA Admin application.
//  Provides comprehensive business intelligence including KPIs,
//  enrollment forecasting, compliance monitoring, and financial health.
//

import SwiftUI
import Charts

// MARK: - Analytics Dashboard View

/// Main analytics dashboard view providing AI-powered business intelligence.
///
/// Features:
/// - KPI cards organized by category (enrollment, attendance, revenue, staffing)
/// - Enrollment forecast chart with confidence intervals
/// - Compliance status overview with drill-down
/// - Staff efficiency metrics
/// - Financial health indicators
/// - Time period filtering (day, week, month, quarter, year)
/// - Category filtering for KPIs
/// - Auto-refresh capability
/// - Loading and error states with graceful degradation
///
/// The dashboard aggregates data from the AI Service to provide
/// predictive insights for childcare facility management.
struct AnalyticsDashboardView: View {

    // MARK: - Properties

    /// The analytics view model
    @StateObject private var viewModel = AnalyticsViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Selected tab for analytics sections
    @State private var selectedTab: AnalyticsTab = .overview

    /// Whether the filter sheet is presented
    @State private var showFilterSheet = false

    /// Whether the export options sheet is presented
    @State private var showExportSheet = false

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if !viewModel.aiServiceAvailable && viewModel.hasLoaded {
                serviceUnavailableView
            } else if let error = viewModel.error, !viewModel.hasData {
                errorView(error)
            } else {
                analyticsContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "AI Analytics"))
        .toolbar {
            analyticsToolbar
        }
        .task {
            await viewModel.loadAnalytics()
        }
        .onAppear {
            viewModel.startAutoRefresh()
        }
        .onDisappear {
            viewModel.stopAutoRefresh()
        }
        .alert(
            String(localized: "Error Loading Analytics"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadAnalytics()
                }
            }
            Button(String(localized: "Dismiss"), role: .cancel) {
                viewModel.clearError()
            }
        } message: { error in
            Text(error.localizedDescription)
        }
        .sheet(isPresented: $showFilterSheet) {
            FilterSheet(viewModel: viewModel)
        }
    }

    // MARK: - Analytics Content

    private var analyticsContent: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                // Header with summary and quick stats
                analyticsHeader

                // Tab selector for different analytics sections
                analyticsTabSelector

                // Content based on selected tab
                analyticsTabContent
            }
            .padding(24)
        }
        .refreshable {
            await viewModel.refresh()
        }
    }

    // MARK: - Analytics Header

    private var analyticsHeader: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Title and service status
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Business Intelligence"))
                        .font(.largeTitle)
                        .fontWeight(.bold)

                    Text(viewModel.getSummaryMessage())
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }

                Spacer()

                // AI Service status indicator
                aiServiceStatusBadge

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

            // Quick KPI summary
            if viewModel.hasData {
                quickKPISummary
            }
        }
    }

    private var aiServiceStatusBadge: some View {
        HStack(spacing: 6) {
            Circle()
                .fill(viewModel.aiServiceAvailable ? Color.green : Color.orange)
                .frame(width: 8, height: 8)

            Text(viewModel.aiServiceAvailable
                 ? String(localized: "AI Connected")
                 : String(localized: "AI Unavailable"))
                .font(.caption)
                .foregroundColor(.secondary)

            // Data freshness indicator
            HStack(spacing: 4) {
                Image(systemName: viewModel.dataFreshness.iconName)
                    .font(.caption2)
                Text(viewModel.formattedLastRefresh)
                    .font(.caption2)
            }
            .foregroundColor(Color(viewModel.dataFreshness.color))
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 6)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }

    private var quickKPISummary: some View {
        HStack(spacing: 16) {
            // Enrollment
            if !viewModel.enrollmentKPIs.isEmpty {
                QuickStatCard(
                    title: String(localized: "Enrollment"),
                    value: viewModel.enrollmentKPIs.first?.formattedValue ?? "--",
                    trend: viewModel.enrollmentKPIs.first?.formattedChange,
                    isPositive: viewModel.enrollmentKPIs.first?.isPositiveChange ?? true,
                    icon: "person.2.fill",
                    color: .blue
                )
            }

            // Compliance
            QuickStatCard(
                title: String(localized: "Compliance"),
                value: viewModel.compliancePercentage.asPercentage,
                trend: viewModel.complianceIssues.isEmpty ? nil : "\(viewModel.complianceIssues.count) issues",
                isPositive: viewModel.complianceIssues.isEmpty,
                icon: viewModel.overallComplianceStatus.iconName,
                color: Color(viewModel.overallComplianceStatus.color)
            )

            // Financial Health
            if let financial = viewModel.financialHealth {
                QuickStatCard(
                    title: String(localized: "Collection Rate"),
                    value: financial.formattedCollectionRate,
                    trend: financial.revenueTrend.displayName,
                    isPositive: financial.revenueTrend == .up,
                    icon: "dollarsign.circle.fill",
                    color: financial.isHealthy ? .green : .orange
                )
            }

            // Staff Efficiency
            if viewModel.overallStaffEfficiency > 0 {
                QuickStatCard(
                    title: String(localized: "Staff Efficiency"),
                    value: viewModel.overallStaffEfficiency.asPercentage,
                    trend: nil,
                    isPositive: viewModel.overallStaffEfficiency >= 80,
                    icon: "person.3.fill",
                    color: viewModel.overallStaffEfficiency >= 80 ? .green : .orange
                )
            }

            Spacer()
        }
    }

    // MARK: - Tab Selector

    private var analyticsTabSelector: some View {
        HStack(spacing: 0) {
            ForEach(AnalyticsTab.allCases, id: \.self) { tab in
                Button(action: {
                    withAnimation(.easeInOut(duration: 0.2)) {
                        selectedTab = tab
                    }
                }) {
                    VStack(spacing: 4) {
                        HStack(spacing: 6) {
                            Image(systemName: tab.iconName)
                            Text(tab.displayName)
                        }
                        .font(.subheadline)
                        .fontWeight(selectedTab == tab ? .semibold : .regular)
                        .foregroundColor(selectedTab == tab ? .accentColor : .secondary)

                        Rectangle()
                            .fill(selectedTab == tab ? Color.accentColor : Color.clear)
                            .frame(height: 2)
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 8)
                }
                .buttonStyle(.plain)
            }

            Spacer()
        }
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }

    // MARK: - Tab Content

    @ViewBuilder
    private var analyticsTabContent: some View {
        switch selectedTab {
        case .overview:
            overviewTabContent
        case .enrollment:
            enrollmentTabContent
        case .compliance:
            complianceTabContent
        case .financial:
            financialTabContent
        case .staff:
            staffTabContent
        }
    }

    // MARK: - Overview Tab

    private var overviewTabContent: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Enrollment forecast section
            if let forecast = viewModel.forecast {
                sectionHeader(
                    title: String(localized: "Enrollment Forecast"),
                    subtitle: String(localized: "AI-predicted enrollment trends"),
                    icon: "chart.line.uptrend.xyaxis"
                )

                EnrollmentChartView(
                    forecast: forecast,
                    chartHeight: 280,
                    showConfidenceBands: true,
                    showLegend: true
                )
            }

            // KPI grid section
            if !viewModel.filteredKPIs.isEmpty {
                sectionHeader(
                    title: String(localized: "Key Performance Indicators"),
                    subtitle: String(localized: "\(viewModel.filteredKPIs.count) metrics for \(viewModel.selectedPeriod.displayName)"),
                    icon: "chart.bar.fill"
                )

                AnalyticsKPIGrid(
                    kpis: viewModel.filteredKPIs,
                    groupedByCategory: true
                )
            }

            // Compliance summary
            if let compliance = viewModel.compliance {
                sectionHeader(
                    title: String(localized: "Compliance Overview"),
                    subtitle: String(localized: "\(compliance.compliantCount) of \(compliance.totalChecks) checks passing"),
                    icon: "checkmark.shield.fill"
                )

                ComplianceSummaryCard(compliance: compliance)
            }
        }
    }

    // MARK: - Enrollment Tab

    private var enrollmentTabContent: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Enrollment KPIs
            if !viewModel.enrollmentKPIs.isEmpty {
                sectionHeader(
                    title: String(localized: "Enrollment Metrics"),
                    subtitle: String(localized: "Key enrollment indicators"),
                    icon: "person.2.fill"
                )

                AnalyticsKPIGrid(kpis: viewModel.enrollmentKPIs, groupedByCategory: false)
            }

            // Full enrollment chart
            if let forecast = viewModel.forecast {
                sectionHeader(
                    title: String(localized: "Enrollment Trend & Forecast"),
                    subtitle: String(localized: "Historical data and AI predictions"),
                    icon: "chart.line.uptrend.xyaxis"
                )

                EnrollmentChartView(
                    forecast: forecast,
                    chartHeight: 350,
                    showConfidenceBands: true,
                    showLegend: true
                )

                // Enrollment statistics
                EnrollmentStatsCard(forecast: forecast)
            }

            // Attendance metrics
            if !viewModel.attendanceKPIs.isEmpty {
                sectionHeader(
                    title: String(localized: "Attendance Metrics"),
                    subtitle: String(localized: "Daily attendance trends"),
                    icon: "calendar.badge.clock"
                )

                AnalyticsKPIGrid(kpis: viewModel.attendanceKPIs, groupedByCategory: false)
            }
        }
    }

    // MARK: - Compliance Tab

    private var complianceTabContent: some View {
        VStack(alignment: .leading, spacing: 24) {
            if let compliance = viewModel.compliance {
                // Overall compliance status
                sectionHeader(
                    title: String(localized: "Compliance Status"),
                    subtitle: String(localized: "Quebec regulatory compliance monitoring"),
                    icon: "checkmark.shield.fill"
                )

                ComplianceOverviewCard(compliance: compliance)

                // Individual compliance checks
                sectionHeader(
                    title: String(localized: "Compliance Checks"),
                    subtitle: String(localized: "\(viewModel.filteredComplianceChecks.count) checks"),
                    icon: "checklist"
                )

                ComplianceChecksList(
                    checks: viewModel.filteredComplianceChecks,
                    onSelectCheck: { check in
                        // Handle check selection for drill-down
                    }
                )

                // Issues requiring attention
                if !viewModel.complianceIssues.isEmpty {
                    sectionHeader(
                        title: String(localized: "Action Required"),
                        subtitle: String(localized: "\(viewModel.complianceIssues.count) items need attention"),
                        icon: "exclamationmark.triangle.fill"
                    )

                    ComplianceIssuesList(issues: viewModel.complianceIssues)
                }
            } else {
                emptyStateView(
                    title: String(localized: "No Compliance Data"),
                    message: String(localized: "Compliance data is not available. Please check AI Service connection."),
                    icon: "shield.slash"
                )
            }
        }
    }

    // MARK: - Financial Tab

    private var financialTabContent: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Revenue KPIs
            if !viewModel.revenueKPIs.isEmpty {
                sectionHeader(
                    title: String(localized: "Revenue Metrics"),
                    subtitle: String(localized: "Financial performance indicators"),
                    icon: "dollarsign.circle.fill"
                )

                AnalyticsKPIGrid(kpis: viewModel.revenueKPIs, groupedByCategory: false)
            }

            // Financial health
            if let financial = viewModel.financialHealth {
                sectionHeader(
                    title: String(localized: "Financial Health"),
                    subtitle: String(localized: "Overall financial status"),
                    icon: "heart.text.square.fill"
                )

                FinancialHealthCard(financial: financial)

                // Revenue breakdown
                if let streams = financial.revenueStreams, !streams.isEmpty {
                    sectionHeader(
                        title: String(localized: "Revenue Breakdown"),
                        subtitle: String(localized: "Revenue by source"),
                        icon: "chart.pie.fill"
                    )

                    RevenueBreakdownView(streams: streams)
                }
            } else {
                emptyStateView(
                    title: String(localized: "No Financial Data"),
                    message: String(localized: "Financial data is not available. Please check AI Service connection."),
                    icon: "dollarsign.circle"
                )
            }
        }
    }

    // MARK: - Staff Tab

    private var staffTabContent: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Staffing KPIs
            if !viewModel.staffingKPIs.isEmpty {
                sectionHeader(
                    title: String(localized: "Staffing Metrics"),
                    subtitle: String(localized: "Staff-to-child ratios and efficiency"),
                    icon: "person.3.fill"
                )

                AnalyticsKPIGrid(kpis: viewModel.staffingKPIs, groupedByCategory: false)
            }

            // Staff efficiency
            if let efficiency = viewModel.staffEfficiency {
                sectionHeader(
                    title: String(localized: "Staff Efficiency"),
                    subtitle: String(localized: "Individual and facility-wide efficiency"),
                    icon: "gauge.with.dots.needle.33percent"
                )

                StaffEfficiencyCard(efficiency: efficiency)

                // Individual staff metrics
                if !efficiency.staffMetrics.isEmpty {
                    sectionHeader(
                        title: String(localized: "Individual Performance"),
                        subtitle: String(localized: "\(efficiency.staffMetrics.count) staff members"),
                        icon: "person.text.rectangle"
                    )

                    StaffMetricsList(metrics: efficiency.staffMetrics)
                }
            } else {
                emptyStateView(
                    title: String(localized: "No Staff Efficiency Data"),
                    message: String(localized: "Staff efficiency data is not available. Please check AI Service connection."),
                    icon: "person.3"
                )
            }
        }
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading AI Analytics..."))
                .font(.headline)
                .foregroundColor(.secondary)

            Text(String(localized: "Fetching data from AI Service"))
                .font(.subheadline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Service Unavailable View

    private var serviceUnavailableView: some View {
        VStack(spacing: 20) {
            Image(systemName: "cloud.bolt.fill")
                .font(.system(size: 48))
                .foregroundColor(.orange)

            VStack(spacing: 8) {
                Text(String(localized: "AI Service Unavailable"))
                    .font(.headline)

                Text(String(localized: "The AI analytics service is not responding. Some features may be limited."))
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
            }

            HStack(spacing: 12) {
                Button(action: {
                    Task {
                        await viewModel.checkAIServiceAvailability()
                        if viewModel.aiServiceAvailable {
                            await viewModel.loadAnalytics()
                        }
                    }
                }) {
                    Label(String(localized: "Retry Connection"), systemImage: "arrow.clockwise")
                }
                .buttonStyle(.borderedProminent)

                Button(action: {
                    // Continue with partial data
                }) {
                    Text(String(localized: "Continue Anyway"))
                }
                .buttonStyle(.bordered)
            }
        }
        .padding(40)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Error View

    private func errorView(_ error: Error) -> some View {
        VStack(spacing: 20) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.system(size: 48))
                .foregroundColor(.orange)

            VStack(spacing: 8) {
                Text(String(localized: "Unable to Load Analytics"))
                    .font(.headline)

                Text(error.localizedDescription)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
            }

            Button(action: {
                Task {
                    await viewModel.loadAnalytics()
                }
            }) {
                Label(String(localized: "Try Again"), systemImage: "arrow.clockwise")
            }
            .buttonStyle(.borderedProminent)
        }
        .padding(40)
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Empty State View

    private func emptyStateView(title: String, message: String, icon: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: icon)
                .font(.system(size: 40))
                .foregroundColor(.secondary)

            VStack(spacing: 4) {
                Text(title)
                    .font(.headline)

                Text(message)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .multilineTextAlignment(.center)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(40)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var analyticsToolbar: some ToolbarContent {
        ToolbarItemGroup(placement: .primaryAction) {
            // Time period picker
            Picker(String(localized: "Period"), selection: $viewModel.selectedPeriod) {
                ForEach(AnalyticsTimePeriod.allCases, id: \.self) { period in
                    Text(period.displayName).tag(period)
                }
            }
            .pickerStyle(.segmented)
            .frame(width: 250)
            .onChange(of: viewModel.selectedPeriod) { _ in
                Task {
                    await viewModel.loadAnalytics()
                }
            }

            // Filter button
            Button(action: {
                showFilterSheet = true
            }) {
                Label(String(localized: "Filter"), systemImage: "line.3.horizontal.decrease.circle")
            }
            .help(String(localized: "Filter analytics data"))

            // Auto-refresh toggle
            Toggle(isOn: Binding(
                get: { viewModel.autoRefreshEnabled },
                set: { _ in viewModel.toggleAutoRefresh() }
            )) {
                Label(
                    String(localized: "Auto-refresh"),
                    systemImage: viewModel.autoRefreshEnabled
                        ? "arrow.triangle.2.circlepath.circle.fill"
                        : "arrow.triangle.2.circlepath.circle"
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
            .help(String(localized: "Refresh analytics (Cmd+R)"))
        }
    }

    // MARK: - Helper Views

    private func sectionHeader(title: String, subtitle: String, icon: String) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.title2)
                .foregroundColor(.accentColor)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.title2)
                    .fontWeight(.semibold)

                Text(subtitle)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
            }

            Spacer()
        }
    }
}

// MARK: - Analytics Tab

/// Tabs for the analytics dashboard.
enum AnalyticsTab: String, CaseIterable {
    case overview
    case enrollment
    case compliance
    case financial
    case staff

    var displayName: String {
        switch self {
        case .overview:
            return String(localized: "Overview")
        case .enrollment:
            return String(localized: "Enrollment")
        case .compliance:
            return String(localized: "Compliance")
        case .financial:
            return String(localized: "Financial")
        case .staff:
            return String(localized: "Staff")
        }
    }

    var iconName: String {
        switch self {
        case .overview:
            return "square.grid.2x2"
        case .enrollment:
            return "person.2.fill"
        case .compliance:
            return "checkmark.shield.fill"
        case .financial:
            return "dollarsign.circle.fill"
        case .staff:
            return "person.3.fill"
        }
    }
}

// MARK: - Quick Stat Card

/// A compact card showing a single quick statistic.
struct QuickStatCard: View {
    let title: String
    let value: String
    let trend: String?
    let isPositive: Bool
    let icon: String
    let color: Color

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .font(.title2)
                .foregroundColor(color)
                .frame(width: 32)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.caption)
                    .foregroundColor(.secondary)

                HStack(spacing: 4) {
                    Text(value)
                        .font(.headline)
                        .fontWeight(.semibold)

                    if let trend = trend {
                        Text(trend)
                            .font(.caption)
                            .foregroundColor(isPositive ? .green : .red)
                    }
                }
            }
        }
        .padding(12)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

// MARK: - Analytics KPI Grid

/// Grid display of KPI metrics.
struct AnalyticsKPIGrid: View {
    let kpis: [KPIMetric]
    var groupedByCategory: Bool = true

    var body: some View {
        if groupedByCategory {
            let grouped = Dictionary(grouping: kpis) { $0.category }

            ForEach(MetricCategory.allCases, id: \.self) { category in
                if let categoryKPIs = grouped[category], !categoryKPIs.isEmpty {
                    VStack(alignment: .leading, spacing: 12) {
                        HStack(spacing: 6) {
                            Image(systemName: category.iconName)
                                .foregroundColor(.secondary)
                            Text(category.displayName)
                                .font(.subheadline)
                                .fontWeight(.medium)
                        }

                        LazyVGrid(columns: [
                            GridItem(.flexible()),
                            GridItem(.flexible()),
                            GridItem(.flexible())
                        ], spacing: 16) {
                            ForEach(categoryKPIs) { kpi in
                                AnalyticsKPICard(kpi: kpi)
                            }
                        }
                    }
                }
            }
        } else {
            LazyVGrid(columns: [
                GridItem(.flexible()),
                GridItem(.flexible()),
                GridItem(.flexible())
            ], spacing: 16) {
                ForEach(kpis) { kpi in
                    AnalyticsKPICard(kpi: kpi)
                }
            }
        }
    }
}

// MARK: - Analytics KPI Card

/// Individual KPI card in the analytics grid.
struct AnalyticsKPICard: View {
    let kpi: KPIMetric

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: kpi.category.iconName)
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(kpi.metricName)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(1)

                Spacer()
            }

            Text(kpi.formattedValue)
                .font(.title2)
                .fontWeight(.bold)

            if let change = kpi.formattedChange {
                HStack(spacing: 4) {
                    Image(systemName: kpi.isPositiveChange ? "arrow.up.right" : "arrow.down.right")
                        .font(.caption2)
                    Text(change)
                        .font(.caption)
                }
                .foregroundColor(Color(kpi.changeColor))
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Compliance Summary Card

/// Summary card showing overall compliance status.
struct ComplianceSummaryCard: View {
    let compliance: ComplianceListResponse

    var body: some View {
        HStack(spacing: 24) {
            // Compliance percentage
            VStack(spacing: 8) {
                Text(compliance.formattedCompliancePercentage)
                    .font(.system(size: 36, weight: .bold))
                    .foregroundColor(Color(compliance.overallStatus.color))

                Text(String(localized: "Compliance Rate"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Divider()

            // Status breakdown
            VStack(alignment: .leading, spacing: 8) {
                complianceStatRow(
                    label: String(localized: "Compliant"),
                    count: compliance.compliantCount,
                    color: .green
                )
                complianceStatRow(
                    label: String(localized: "Warnings"),
                    count: compliance.warningCount,
                    color: .orange
                )
                complianceStatRow(
                    label: String(localized: "Violations"),
                    count: compliance.violationCount,
                    color: .red
                )
            }

            Spacer()
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func complianceStatRow(label: String, count: Int, color: Color) -> some View {
        HStack(spacing: 8) {
            Circle()
                .fill(color)
                .frame(width: 8, height: 8)

            Text(label)
                .font(.subheadline)

            Spacer()

            Text("\(count)")
                .font(.subheadline)
                .fontWeight(.medium)
        }
    }
}

// MARK: - Compliance Overview Card

/// Detailed compliance overview with all check types.
struct ComplianceOverviewCard: View {
    let compliance: ComplianceListResponse

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Overall status
            HStack {
                Image(systemName: compliance.overallStatus.iconName)
                    .font(.title)
                    .foregroundColor(Color(compliance.overallStatus.color))

                VStack(alignment: .leading, spacing: 2) {
                    Text(compliance.overallStatus.displayName)
                        .font(.headline)

                    Text(String(localized: "Overall facility compliance status"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Spacer()

                Text(compliance.formattedCompliancePercentage)
                    .font(.title)
                    .fontWeight(.bold)
                    .foregroundColor(Color(compliance.overallStatus.color))
            }

            Divider()

            // Check type breakdown
            LazyVGrid(columns: [
                GridItem(.flexible()),
                GridItem(.flexible())
            ], spacing: 12) {
                ForEach(compliance.sortedChecks) { check in
                    ComplianceCheckCard(check: check)
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Compliance Check Card

/// Individual compliance check card.
struct ComplianceCheckCard: View {
    let check: ComplianceCheckResponse

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: check.checkType.iconName)
                .font(.title3)
                .foregroundColor(Color(check.statusColor))
                .frame(width: 32)

            VStack(alignment: .leading, spacing: 2) {
                Text(check.checkTypeDisplayName)
                    .font(.subheadline)
                    .fontWeight(.medium)

                HStack(spacing: 4) {
                    Image(systemName: check.statusIcon)
                        .font(.caption2)
                    Text(check.statusDisplayName)
                        .font(.caption)
                }
                .foregroundColor(Color(check.statusColor))
            }

            Spacer()
        }
        .padding(10)
        .background(Color(check.statusColor).opacity(0.1))
        .cornerRadius(8)
    }
}

// MARK: - Compliance Checks List

/// List of compliance checks with details.
struct ComplianceChecksList: View {
    let checks: [ComplianceCheckResponse]
    var onSelectCheck: ((ComplianceCheckResponse) -> Void)?

    var body: some View {
        VStack(spacing: 12) {
            ForEach(checks) { check in
                Button(action: {
                    onSelectCheck?(check)
                }) {
                    ComplianceCheckRow(check: check)
                }
                .buttonStyle(.plain)
            }
        }
    }
}

// MARK: - Compliance Check Row

/// Row displaying a compliance check with full details.
struct ComplianceCheckRow: View {
    let check: ComplianceCheckResponse

    var body: some View {
        HStack(spacing: 16) {
            // Status icon
            Image(systemName: check.statusIcon)
                .font(.title2)
                .foregroundColor(Color(check.statusColor))
                .frame(width: 40)

            // Check info
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(check.checkTypeDisplayName)
                        .font(.headline)

                    Spacer()

                    Text(check.statusDisplayName)
                        .font(.caption)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color(check.statusColor).opacity(0.15))
                        .foregroundColor(Color(check.statusColor))
                        .cornerRadius(4)
                }

                Text(check.checkType.description)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(2)

                if let recommendation = check.recommendation {
                    HStack(spacing: 4) {
                        Image(systemName: "lightbulb.fill")
                            .font(.caption2)
                        Text(recommendation)
                            .font(.caption)
                    }
                    .foregroundColor(.orange)
                }

                HStack {
                    Text(String(localized: "Checked: \(check.formattedCheckedAt)"))
                        .font(.caption2)
                        .foregroundColor(.secondary)

                    if let nextDue = check.formattedNextCheckDue {
                        Text("•")
                            .foregroundColor(.secondary)
                        Text(String(localized: "Next: \(nextDue)"))
                            .font(.caption2)
                            .foregroundColor(.secondary)
                    }
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Compliance Issues List

/// List of compliance issues requiring attention.
struct ComplianceIssuesList: View {
    let issues: [ComplianceCheckResponse]

    var body: some View {
        VStack(spacing: 8) {
            ForEach(issues) { issue in
                HStack(spacing: 12) {
                    Image(systemName: issue.status == .violation ? "xmark.octagon.fill" : "exclamationmark.triangle.fill")
                        .font(.title3)
                        .foregroundColor(Color(issue.statusColor))

                    VStack(alignment: .leading, spacing: 2) {
                        Text(issue.checkTypeDisplayName)
                            .font(.subheadline)
                            .fontWeight(.medium)

                        if let recommendation = issue.recommendation {
                            Text(recommendation)
                                .font(.caption)
                                .foregroundColor(.secondary)
                                .lineLimit(2)
                        }
                    }

                    Spacer()

                    Button(action: {
                        // Handle action
                    }) {
                        Text(String(localized: "Resolve"))
                            .font(.caption)
                    }
                    .buttonStyle(.bordered)
                }
                .padding()
                .background(Color(issue.statusColor).opacity(0.1))
                .cornerRadius(8)
            }
        }
    }
}

// MARK: - Financial Health Card

/// Card showing financial health metrics.
struct FinancialHealthCard: View {
    let financial: FinancialHealthResponse

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header with health level
            HStack {
                Image(systemName: financial.isHealthy ? "heart.fill" : "heart")
                    .font(.title2)
                    .foregroundColor(financial.isHealthy ? .green : .orange)

                VStack(alignment: .leading, spacing: 2) {
                    Text(String(localized: "Financial Health"))
                        .font(.headline)

                    Text(financial.healthLevel.displayName)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Spacer()

                // Revenue trend
                HStack(spacing: 4) {
                    Image(systemName: financial.revenueTrend.iconName)
                    Text(financial.revenueTrend.displayName)
                }
                .font(.subheadline)
                .foregroundColor(Color(financial.revenueTrend.defaultColor))
            }

            Divider()

            // Financial metrics
            LazyVGrid(columns: [
                GridItem(.flexible()),
                GridItem(.flexible()),
                GridItem(.flexible())
            ], spacing: 16) {
                financialMetric(
                    title: String(localized: "Revenue"),
                    value: financial.formattedRevenue,
                    color: .green
                )

                financialMetric(
                    title: String(localized: "Expenses"),
                    value: financial.formattedExpenses,
                    color: .red
                )

                financialMetric(
                    title: String(localized: "Net Income"),
                    value: financial.formattedNetIncome,
                    color: financial.netIncome >= 0 ? .green : .red
                )

                financialMetric(
                    title: String(localized: "Collection Rate"),
                    value: financial.formattedCollectionRate,
                    color: financial.collectionRate >= 90 ? .green : .orange
                )

                financialMetric(
                    title: String(localized: "Days Outstanding"),
                    value: String(format: "%.1f", financial.daysSalesOutstanding),
                    color: financial.daysSalesOutstanding <= 30 ? .green : .orange
                )

                if let variance = financial.budgetVariance {
                    financialMetric(
                        title: String(localized: "Budget Variance"),
                        value: String(format: "%+.1f%%", variance),
                        color: variance >= 0 ? .green : .red
                    )
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func financialMetric(title: String, value: String, color: Color) -> some View {
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

// MARK: - Revenue Breakdown View

/// Chart showing revenue breakdown by source.
struct RevenueBreakdownView: View {
    let streams: [RevenueStream]

    var body: some View {
        HStack(spacing: 24) {
            // Pie chart
            Chart(streams) { stream in
                SectorMark(
                    angle: .value("Amount", stream.amount),
                    innerRadius: .ratio(0.5),
                    angularInset: 1
                )
                .foregroundStyle(by: .value("Source", stream.name))
                .annotation(position: .overlay) {
                    Text(stream.formattedPercentage)
                        .font(.caption2)
                        .fontWeight(.bold)
                        .foregroundColor(.white)
                }
            }
            .frame(width: 200, height: 200)

            // Legend
            VStack(alignment: .leading, spacing: 8) {
                ForEach(streams) { stream in
                    HStack(spacing: 8) {
                        Circle()
                            .fill(Color.accentColor)
                            .frame(width: 10, height: 10)

                        Text(stream.name)
                            .font(.subheadline)

                        Spacer()

                        Text(stream.formattedAmount)
                            .font(.subheadline)
                            .fontWeight(.medium)
                    }
                }
            }

            Spacer()
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Staff Efficiency Card

/// Card showing staff efficiency metrics.
struct StaffEfficiencyCard: View {
    let efficiency: StaffEfficiencyResponse

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Overall efficiency
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Overall Efficiency"))
                        .font(.headline)

                    Text(String(localized: "Facility-wide staff performance"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Spacer()

                Text(efficiency.formattedEfficiency)
                    .font(.system(size: 36, weight: .bold))
                    .foregroundColor(efficiency.overallEfficiency >= 80 ? .green : .orange)
            }

            Divider()

            // Metrics
            HStack(spacing: 24) {
                efficiencyMetric(
                    title: String(localized: "Staff Ratio"),
                    value: efficiency.formattedStaffRatio,
                    icon: "person.3.fill"
                )

                efficiencyMetric(
                    title: String(localized: "Staff Count"),
                    value: "\(efficiency.staffMetrics.count)",
                    icon: "person.fill"
                )

                Spacer()
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func efficiencyMetric(title: String, value: String, icon: String) -> some View {
        HStack(spacing: 8) {
            Image(systemName: icon)
                .foregroundColor(.secondary)

            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(value)
                    .font(.headline)
            }
        }
    }
}

// MARK: - Staff Metrics List

/// List of individual staff efficiency metrics.
struct StaffMetricsList: View {
    let metrics: [StaffEfficiencyMetric]

    var body: some View {
        LazyVGrid(columns: [
            GridItem(.flexible()),
            GridItem(.flexible())
        ], spacing: 12) {
            ForEach(metrics) { metric in
                StaffMetricCard(metric: metric)
            }
        }
    }
}

// MARK: - Staff Metric Card

/// Individual staff member efficiency card.
struct StaffMetricCard: View {
    let metric: StaffEfficiencyMetric

    var body: some View {
        HStack(spacing: 12) {
            // Avatar placeholder
            Circle()
                .fill(Color.accentColor.opacity(0.2))
                .frame(width: 40, height: 40)
                .overlay {
                    Text(metric.staffName.prefix(1))
                        .font(.headline)
                        .foregroundColor(.accentColor)
                }

            VStack(alignment: .leading, spacing: 2) {
                Text(metric.staffName)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .lineLimit(1)

                if let role = metric.role {
                    Text(role)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()

            VStack(alignment: .trailing, spacing: 2) {
                Text(metric.formattedEfficiency)
                    .font(.headline)
                    .foregroundColor(metric.efficiencyScore >= 80 ? .green : .orange)

                Text(String(localized: "\(metric.childrenSupervised) children"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

// MARK: - Filter Sheet

/// Sheet for filtering analytics data.
struct FilterSheet: View {
    @ObservedObject var viewModel: AnalyticsViewModel
    @Environment(\.dismiss) var dismiss

    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Header
            HStack {
                Text(String(localized: "Filter Analytics"))
                    .font(.title2)
                    .fontWeight(.bold)

                Spacer()

                Button(action: { dismiss() }) {
                    Image(systemName: "xmark.circle.fill")
                        .font(.title2)
                        .foregroundColor(.secondary)
                }
                .buttonStyle(.plain)
            }

            Divider()

            // Category filters
            VStack(alignment: .leading, spacing: 12) {
                Text(String(localized: "KPI Categories"))
                    .font(.headline)

                LazyVGrid(columns: [GridItem(.adaptive(minimum: 120))], spacing: 8) {
                    ForEach(MetricCategory.allCases, id: \.self) { category in
                        Toggle(isOn: Binding(
                            get: { viewModel.selectedCategories.contains(category) },
                            set: { _ in viewModel.toggleCategory(category) }
                        )) {
                            HStack {
                                Image(systemName: category.iconName)
                                Text(category.displayName)
                            }
                        }
                        .toggleStyle(.button)
                    }
                }

                HStack {
                    Button(String(localized: "Select All")) {
                        viewModel.selectAllCategories()
                    }
                    Button(String(localized: "Clear")) {
                        viewModel.clearCategories()
                    }
                }
            }

            // Compliance type filters
            VStack(alignment: .leading, spacing: 12) {
                Text(String(localized: "Compliance Checks"))
                    .font(.headline)

                LazyVGrid(columns: [GridItem(.adaptive(minimum: 120))], spacing: 8) {
                    ForEach(ComplianceCheckType.allCases, id: \.self) { checkType in
                        Toggle(isOn: Binding(
                            get: { viewModel.selectedComplianceTypes.contains(checkType) },
                            set: { _ in viewModel.toggleComplianceType(checkType) }
                        )) {
                            HStack {
                                Image(systemName: checkType.iconName)
                                Text(checkType.displayName)
                            }
                        }
                        .toggleStyle(.button)
                    }
                }
            }

            // Forecast periods
            VStack(alignment: .leading, spacing: 8) {
                Text(String(localized: "Forecast Periods"))
                    .font(.headline)

                Picker(String(localized: "Periods"), selection: $viewModel.forecastPeriods) {
                    Text("3").tag(3)
                    Text("6").tag(6)
                    Text("12").tag(12)
                }
                .pickerStyle(.segmented)
            }

            Spacer()

            // Done button
            Button(action: { dismiss() }) {
                Text(String(localized: "Done"))
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .controlSize(.large)
        }
        .padding(24)
        .frame(width: 500, height: 500)
    }
}

// MARK: - Preview

#Preview("Analytics Dashboard") {
    AnalyticsDashboardView()
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 1000, height: 800)
}

#Preview("Analytics Dashboard - Loading") {
    let view = AnalyticsDashboardView()
    return view
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 1000, height: 600)
}

#Preview("Quick Stat Card") {
    QuickStatCard(
        title: "Enrollment",
        value: "92.5%",
        trend: "+4.5%",
        isPositive: true,
        icon: "person.2.fill",
        color: .blue
    )
    .padding()
}

#Preview("Analytics KPI Card") {
    AnalyticsKPICard(kpi: .preview)
        .frame(width: 200)
        .padding()
}

#Preview("Compliance Summary") {
    ComplianceSummaryCard(compliance: .preview)
        .frame(width: 400)
        .padding()
}
