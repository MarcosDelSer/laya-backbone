//
//  AlertCard.swift
//  LAYAAdmin
//
//  Reusable alert card components for displaying dashboard alerts,
//  pending items, and notifications requiring attention.
//

import SwiftUI

// MARK: - Alert Card View

/// A card component for displaying an individual alert with full details.
///
/// Features:
/// - Severity-based color coding (critical, warning, info)
/// - Category icon and label
/// - Timestamp display
/// - Expandable details
/// - Action buttons for acknowledge/dismiss
///
/// Usage:
/// ```swift
/// AlertCard(
///     alert: dashboardAlert,
///     onAcknowledge: { /* handle */ },
///     onNavigate: { /* navigate to related entity */ }
/// )
/// ```
struct AlertCard: View {

    // MARK: - Properties

    /// The alert to display
    let alert: DashboardAlert

    /// Callback when alert is acknowledged
    let onAcknowledge: () -> Void

    /// Optional callback to navigate to related entity
    let onNavigate: (() -> Void)?

    /// Whether to show the full message (expanded state)
    @State private var isExpanded = false

    // MARK: - Initializers

    init(
        alert: DashboardAlert,
        onAcknowledge: @escaping () -> Void,
        onNavigate: (() -> Void)? = nil
    ) {
        self.alert = alert
        self.onAcknowledge = onAcknowledge
        self.onNavigate = onNavigate
    }

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Header row with severity icon and timestamp
            headerRow

            // Title and message content
            contentSection

            // Action buttons
            actionRow
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(cardBackground)
        .cornerRadius(12)
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(severityColor.opacity(0.4), lineWidth: 1)
        )
        .shadow(color: Color.black.opacity(0.05), radius: 4, x: 0, y: 2)
    }

    // MARK: - Header Row

    private var headerRow: some View {
        HStack(spacing: 12) {
            // Severity icon
            severityIcon

            // Category badge
            categoryBadge

            Spacer()

            // Timestamp
            Text(alert.formattedDate)
                .font(.caption)
                .foregroundColor(.secondary)
        }
    }

    private var severityIcon: some View {
        ZStack {
            Circle()
                .fill(severityColor.opacity(0.15))
                .frame(width: 36, height: 36)

            Image(systemName: alert.severity.iconName)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(severityColor)
        }
    }

    private var categoryBadge: some View {
        HStack(spacing: 4) {
            Image(systemName: alert.category.iconName)
                .font(.caption2)

            Text(alert.category.displayName)
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(Color(NSColor.quaternaryLabelColor).opacity(0.3))
        .cornerRadius(6)
    }

    // MARK: - Content Section

    private var contentSection: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(alert.title)
                .font(.subheadline)
                .fontWeight(.semibold)
                .foregroundColor(.primary)
                .lineLimit(isExpanded ? nil : 1)

            Text(alert.message)
                .font(.caption)
                .foregroundColor(.secondary)
                .lineLimit(isExpanded ? nil : 2)

            // Expand/collapse button for long messages
            if alert.message.count > 80 {
                Button(action: { isExpanded.toggle() }) {
                    Text(isExpanded
                        ? String(localized: "Show less")
                        : String(localized: "Show more"))
                        .font(.caption)
                        .foregroundColor(.accentColor)
                }
                .buttonStyle(.plain)
            }
        }
    }

    // MARK: - Action Row

    private var actionRow: some View {
        HStack(spacing: 12) {
            // Acknowledge button
            Button(action: onAcknowledge) {
                Label(String(localized: "Acknowledge"), systemImage: "checkmark.circle")
                    .font(.caption)
            }
            .buttonStyle(.bordered)
            .controlSize(.small)

            // Navigate button (if available)
            if let onNavigate = onNavigate, alert.relatedEntityId != nil {
                Button(action: onNavigate) {
                    Label(String(localized: "View Details"), systemImage: "arrow.right.circle")
                        .font(.caption)
                }
                .buttonStyle(.borderedProminent)
                .controlSize(.small)
            }

            Spacer()
        }
    }

    // MARK: - Background

    private var cardBackground: some View {
        Color(NSColor.controlBackgroundColor)
    }

    // MARK: - Helper Properties

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
}

// MARK: - Alert List Section

/// A section component for displaying a grouped list of alerts.
///
/// Features:
/// - Grouped by severity (critical, warning, info)
/// - Collapsible sections
/// - Count badges
/// - Empty state handling
struct AlertListSection: View {

    // MARK: - Properties

    /// All alerts to display
    let alerts: [DashboardAlert]

    /// Callback when an alert is acknowledged
    let onAcknowledge: (String) -> Void

    /// Optional callback to navigate to a related entity
    let onNavigate: ((DashboardAlert) -> Void)?

    /// Maximum alerts to show before "View all" button
    let maxDisplayCount: Int

    /// Whether section is expanded
    @State private var isExpanded = true

    // MARK: - Initializers

    init(
        alerts: [DashboardAlert],
        maxDisplayCount: Int = 5,
        onAcknowledge: @escaping (String) -> Void,
        onNavigate: ((DashboardAlert) -> Void)? = nil
    ) {
        self.alerts = alerts
        self.maxDisplayCount = maxDisplayCount
        self.onAcknowledge = onAcknowledge
        self.onNavigate = onNavigate
    }

    // MARK: - Computed Properties

    private var sortedAlerts: [DashboardAlert] {
        alerts
            .filter { !$0.isAcknowledged }
            .sorted { $0.severity.sortOrder < $1.severity.sortOrder }
    }

    private var criticalCount: Int {
        alerts.filter { $0.severity == .critical && !$0.isAcknowledged }.count
    }

    private var warningCount: Int {
        alerts.filter { $0.severity == .warning && !$0.isAcknowledged }.count
    }

    private var infoCount: Int {
        alerts.filter { $0.severity == .info && !$0.isAcknowledged }.count
    }

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Section header with summary badges
            sectionHeader

            if isExpanded {
                if sortedAlerts.isEmpty {
                    emptyState
                } else {
                    alertsList
                }
            }
        }
    }

    // MARK: - Section Header

    private var sectionHeader: some View {
        Button(action: { withAnimation { isExpanded.toggle() } }) {
            HStack(spacing: 12) {
                Image(systemName: isExpanded ? "chevron.down" : "chevron.right")
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .frame(width: 12)

                Text(String(localized: "Alerts"))
                    .font(.headline)
                    .fontWeight(.semibold)

                // Severity count badges
                if criticalCount > 0 {
                    severityBadge(count: criticalCount, color: .red)
                }

                if warningCount > 0 {
                    severityBadge(count: warningCount, color: .orange)
                }

                if infoCount > 0 {
                    severityBadge(count: infoCount, color: .blue)
                }

                Spacer()
            }
        }
        .buttonStyle(.plain)
    }

    private func severityBadge(count: Int, color: Color) -> some View {
        Text("\(count)")
            .font(.caption2)
            .fontWeight(.bold)
            .foregroundColor(.white)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(color)
            .cornerRadius(10)
    }

    // MARK: - Alerts List

    private var alertsList: some View {
        VStack(spacing: 12) {
            ForEach(sortedAlerts.prefix(maxDisplayCount)) { alert in
                AlertCard(
                    alert: alert,
                    onAcknowledge: { onAcknowledge(alert.id) },
                    onNavigate: onNavigate != nil ? { onNavigate?(alert) } : nil
                )
            }

            if sortedAlerts.count > maxDisplayCount {
                viewAllButton
            }
        }
    }

    private var viewAllButton: some View {
        Button(action: {
            // Navigate to full alerts view
        }) {
            Text(String(localized: "View all \(sortedAlerts.count) alerts"))
                .font(.subheadline)
        }
        .buttonStyle(.link)
        .frame(maxWidth: .infinity, alignment: .center)
        .padding(.top, 8)
    }

    // MARK: - Empty State

    private var emptyState: some View {
        HStack(spacing: 12) {
            Image(systemName: "checkmark.seal.fill")
                .font(.title2)
                .foregroundColor(.green)

            VStack(alignment: .leading, spacing: 2) {
                Text(String(localized: "All Clear"))
                    .font(.subheadline)
                    .fontWeight(.medium)

                Text(String(localized: "No alerts require your attention"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Spacer()
        }
        .padding()
        .background(Color.green.opacity(0.1))
        .cornerRadius(10)
    }
}

// MARK: - Pending Item

/// Represents a pending item requiring action (approval, review, etc.)
struct PendingItem: Identifiable, Equatable {

    /// Unique identifier
    let id: String

    /// Item type
    let type: PendingItemType

    /// Title/description of the item
    let title: String

    /// Subtitle with additional context
    let subtitle: String?

    /// When the item was created/submitted
    let createdAt: Date

    /// Priority level
    let priority: PendingItemPriority

    /// Related entity ID for navigation
    let relatedEntityId: String?
}

// MARK: - Pending Item Type

/// Types of pending items
enum PendingItemType: String, CaseIterable {
    case enrollmentApplication = "enrollment_application"
    case staffLeaveRequest = "staff_leave_request"
    case invoiceApproval = "invoice_approval"
    case documentReview = "document_review"
    case certificationRenewal = "certification_renewal"
    case other = "other"

    var displayName: String {
        switch self {
        case .enrollmentApplication:
            return String(localized: "Enrollment Application")
        case .staffLeaveRequest:
            return String(localized: "Leave Request")
        case .invoiceApproval:
            return String(localized: "Invoice Approval")
        case .documentReview:
            return String(localized: "Document Review")
        case .certificationRenewal:
            return String(localized: "Certification Renewal")
        case .other:
            return String(localized: "Pending Item")
        }
    }

    var iconName: String {
        switch self {
        case .enrollmentApplication:
            return "person.badge.plus"
        case .staffLeaveRequest:
            return "calendar.badge.clock"
        case .invoiceApproval:
            return "dollarsign.circle"
        case .documentReview:
            return "doc.text.magnifyingglass"
        case .certificationRenewal:
            return "checkmark.seal"
        case .other:
            return "square.and.pencil"
        }
    }

    var color: Color {
        switch self {
        case .enrollmentApplication:
            return .blue
        case .staffLeaveRequest:
            return .purple
        case .invoiceApproval:
            return .green
        case .documentReview:
            return .orange
        case .certificationRenewal:
            return .yellow
        case .other:
            return .gray
        }
    }
}

// MARK: - Pending Item Priority

/// Priority levels for pending items
enum PendingItemPriority: String, CaseIterable {
    case urgent = "urgent"
    case normal = "normal"
    case low = "low"

    var displayName: String {
        switch self {
        case .urgent:
            return String(localized: "Urgent")
        case .normal:
            return String(localized: "Normal")
        case .low:
            return String(localized: "Low")
        }
    }

    var color: Color {
        switch self {
        case .urgent:
            return .red
        case .normal:
            return .blue
        case .low:
            return .gray
        }
    }

    var sortOrder: Int {
        switch self {
        case .urgent:
            return 0
        case .normal:
            return 1
        case .low:
            return 2
        }
    }
}

// MARK: - Pending Item Card

/// A compact card for displaying a pending item
struct PendingItemCard: View {

    // MARK: - Properties

    let item: PendingItem
    let onAction: () -> Void

    // MARK: - Body

    var body: some View {
        HStack(spacing: 12) {
            // Type icon
            iconView

            // Content
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(item.title)
                        .font(.subheadline)
                        .fontWeight(.medium)
                        .lineLimit(1)

                    if item.priority == .urgent {
                        urgentBadge
                    }
                }

                HStack(spacing: 8) {
                    Text(item.type.displayName)
                        .font(.caption)
                        .foregroundColor(.secondary)

                    Text("•")
                        .foregroundColor(.secondary)

                    Text(formattedDate)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()

            // Action button
            Button(action: onAction) {
                Image(systemName: "arrow.right.circle.fill")
                    .font(.title3)
                    .foregroundColor(.accentColor)
            }
            .buttonStyle(.plain)
            .help(String(localized: "Review item"))
        }
        .padding(12)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(10)
    }

    // MARK: - Icon View

    private var iconView: some View {
        ZStack {
            RoundedRectangle(cornerRadius: 8)
                .fill(item.type.color.opacity(0.15))
                .frame(width: 36, height: 36)

            Image(systemName: item.type.iconName)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(item.type.color)
        }
    }

    // MARK: - Urgent Badge

    private var urgentBadge: some View {
        Text(String(localized: "URGENT"))
            .font(.system(size: 9, weight: .bold))
            .foregroundColor(.white)
            .padding(.horizontal, 4)
            .padding(.vertical, 2)
            .background(Color.red)
            .cornerRadius(4)
    }

    // MARK: - Formatted Date

    private var formattedDate: String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        return formatter.localizedString(for: item.createdAt, relativeTo: Date())
    }
}

// MARK: - Pending Items Section

/// A section component for displaying pending items requiring action
struct PendingItemsSection: View {

    // MARK: - Properties

    /// All pending items
    let items: [PendingItem]

    /// Callback when an item is selected
    let onItemSelected: (PendingItem) -> Void

    /// Maximum items to show before "View all" button
    let maxDisplayCount: Int

    /// Whether section is expanded
    @State private var isExpanded = true

    // MARK: - Initializers

    init(
        items: [PendingItem],
        maxDisplayCount: Int = 4,
        onItemSelected: @escaping (PendingItem) -> Void
    ) {
        self.items = items
        self.maxDisplayCount = maxDisplayCount
        self.onItemSelected = onItemSelected
    }

    // MARK: - Computed Properties

    private var sortedItems: [PendingItem] {
        items.sorted { $0.priority.sortOrder < $1.priority.sortOrder }
    }

    private var urgentCount: Int {
        items.filter { $0.priority == .urgent }.count
    }

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Section header
            sectionHeader

            if isExpanded {
                if items.isEmpty {
                    emptyState
                } else {
                    itemsList
                }
            }
        }
    }

    // MARK: - Section Header

    private var sectionHeader: some View {
        Button(action: { withAnimation { isExpanded.toggle() } }) {
            HStack(spacing: 12) {
                Image(systemName: isExpanded ? "chevron.down" : "chevron.right")
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .frame(width: 12)

                Text(String(localized: "Pending Approvals"))
                    .font(.headline)
                    .fontWeight(.semibold)

                // Count badge
                if !items.isEmpty {
                    Text("\(items.count)")
                        .font(.caption2)
                        .fontWeight(.bold)
                        .foregroundColor(.white)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(urgentCount > 0 ? Color.red : Color.orange)
                        .cornerRadius(10)
                }

                Spacer()
            }
        }
        .buttonStyle(.plain)
    }

    // MARK: - Items List

    private var itemsList: some View {
        VStack(spacing: 8) {
            ForEach(sortedItems.prefix(maxDisplayCount)) { item in
                PendingItemCard(item: item) {
                    onItemSelected(item)
                }
            }

            if items.count > maxDisplayCount {
                viewAllButton
            }
        }
    }

    private var viewAllButton: some View {
        Button(action: {
            // Navigate to full pending items view
        }) {
            Text(String(localized: "View all \(items.count) pending items"))
                .font(.subheadline)
        }
        .buttonStyle(.link)
        .frame(maxWidth: .infinity, alignment: .center)
        .padding(.top, 8)
    }

    // MARK: - Empty State

    private var emptyState: some View {
        HStack(spacing: 12) {
            Image(systemName: "tray.fill")
                .font(.title2)
                .foregroundColor(.secondary)

            VStack(alignment: .leading, spacing: 2) {
                Text(String(localized: "No Pending Items"))
                    .font(.subheadline)
                    .fontWeight(.medium)

                Text(String(localized: "All caught up! No items require your approval."))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Spacer()
        }
        .padding()
        .background(Color(NSColor.quaternaryLabelColor).opacity(0.2))
        .cornerRadius(10)
    }
}

// MARK: - Pending Item Extensions

extension PendingItem {

    /// Creates a sample enrollment application for previews
    static var previewEnrollment: PendingItem {
        PendingItem(
            id: "pending-1",
            type: .enrollmentApplication,
            title: "Roy Family Application",
            subtitle: "2 children - Ages 3 and 5",
            createdAt: Date().addingTimeInterval(-86400),
            priority: .normal,
            relatedEntityId: "application-1"
        )
    }

    /// Creates a sample leave request for previews
    static var previewLeaveRequest: PendingItem {
        PendingItem(
            id: "pending-2",
            type: .staffLeaveRequest,
            title: "Marie Dupont - Vacation",
            subtitle: "March 15-22, 2026",
            createdAt: Date().addingTimeInterval(-3600),
            priority: .urgent,
            relatedEntityId: "leave-1"
        )
    }

    /// Creates a sample invoice approval for previews
    static var previewInvoice: PendingItem {
        PendingItem(
            id: "pending-3",
            type: .invoiceApproval,
            title: "Invoice #2024-0042",
            subtitle: "$1,250.00 - Smith Family",
            createdAt: Date().addingTimeInterval(-172800),
            priority: .normal,
            relatedEntityId: "invoice-42"
        )
    }

    /// Creates a sample certification renewal for previews
    static var previewCertification: PendingItem {
        PendingItem(
            id: "pending-4",
            type: .certificationRenewal,
            title: "First Aid Certification",
            subtitle: "Jean Tremblay - Expires in 7 days",
            createdAt: Date().addingTimeInterval(-7200),
            priority: .urgent,
            relatedEntityId: "staff-2"
        )
    }
}

// MARK: - Preview

#Preview("Alert Card - Critical") {
    AlertCard(
        alert: .previewCritical,
        onAcknowledge: {},
        onNavigate: {}
    )
    .frame(width: 400)
    .padding()
}

#Preview("Alert Card - Warning") {
    AlertCard(
        alert: .preview,
        onAcknowledge: {},
        onNavigate: {}
    )
    .frame(width: 400)
    .padding()
}

#Preview("Alert Card - Info") {
    AlertCard(
        alert: .previewInfo,
        onAcknowledge: {}
    )
    .frame(width: 400)
    .padding()
}

#Preview("Alert List Section") {
    AlertListSection(
        alerts: [.previewCritical, .preview, .previewInfo],
        onAcknowledge: { _ in }
    )
    .frame(width: 450)
    .padding()
}

#Preview("Alert List Section - Empty") {
    AlertListSection(
        alerts: [],
        onAcknowledge: { _ in }
    )
    .frame(width: 450)
    .padding()
}

#Preview("Pending Item Card") {
    VStack(spacing: 12) {
        PendingItemCard(item: .previewLeaveRequest) {}
        PendingItemCard(item: .previewEnrollment) {}
        PendingItemCard(item: .previewInvoice) {}
    }
    .frame(width: 400)
    .padding()
}

#Preview("Pending Items Section") {
    PendingItemsSection(
        items: [
            .previewLeaveRequest,
            .previewCertification,
            .previewEnrollment,
            .previewInvoice
        ],
        onItemSelected: { _ in }
    )
    .frame(width: 450)
    .padding()
}

#Preview("Pending Items Section - Empty") {
    PendingItemsSection(
        items: [],
        onItemSelected: { _ in }
    )
    .frame(width: 450)
    .padding()
}
