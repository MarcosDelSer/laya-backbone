//
//  ComplianceView.swift
//  LAYAAdmin
//
//  Compliance status view for the LAYA Admin application.
//  Provides detailed Quebec regulatory compliance monitoring including
//  staff-to-child ratios, certifications, capacity, and safety inspections.
//

import SwiftUI

// MARK: - Compliance View

/// Comprehensive compliance status view for Quebec regulatory monitoring.
///
/// Features:
/// - Overall compliance score and status
/// - Staff-to-child ratio compliance by age group
/// - Certification status tracking for all staff
/// - Capacity monitoring against licensed limits
/// - Safety inspection status
/// - Actionable recommendations for non-compliant items
/// - Drill-down into individual compliance checks
///
/// This view provides facility directors with a clear overview of
/// regulatory compliance status to ensure adherence to Quebec
/// childcare regulations.
struct ComplianceView: View {

    // MARK: - Properties

    /// The compliance data to display
    let compliance: ComplianceListResponse

    /// Callback when a compliance check is selected for drill-down
    var onSelectCheck: ((ComplianceCheckResponse) -> Void)?

    /// Whether to show detailed view
    @State private var showDetailedView = false

    /// Currently selected check type for filtering
    @State private var selectedCheckType: ComplianceCheckType?

    // MARK: - Body

    var body: some View {
        VStack(alignment: .leading, spacing: 24) {
            // Overall compliance header
            complianceHeader

            // Compliance check type cards
            complianceCheckTypeGrid

            // Detailed checks list (when filter applied)
            if let selectedType = selectedCheckType {
                filteredChecksSection(for: selectedType)
            } else {
                // Issues requiring attention
                if !compliance.checksRequiringAttention.isEmpty {
                    issuesSection
                }
            }
        }
    }

    // MARK: - Compliance Header

    private var complianceHeader: some View {
        HStack(spacing: 24) {
            // Overall compliance score
            ComplianceScoreGauge(
                percentage: compliance.compliancePercentage,
                status: compliance.overallStatus
            )

            // Status summary
            VStack(alignment: .leading, spacing: 12) {
                HStack(spacing: 8) {
                    Image(systemName: compliance.overallStatus.iconName)
                        .font(.title2)
                        .foregroundColor(Color(compliance.overallStatus.color))

                    Text(compliance.overallStatus.displayName)
                        .font(.title2)
                        .fontWeight(.semibold)
                }

                Text(String(localized: "Quebec Regulatory Compliance"))
                    .font(.subheadline)
                    .foregroundColor(.secondary)

                // Quick stats
                HStack(spacing: 16) {
                    complianceStatBadge(
                        count: compliance.compliantCount,
                        label: String(localized: "Passing"),
                        color: .green
                    )

                    complianceStatBadge(
                        count: compliance.warningCount,
                        label: String(localized: "Warnings"),
                        color: .orange
                    )

                    complianceStatBadge(
                        count: compliance.violationCount,
                        label: String(localized: "Violations"),
                        color: .red
                    )
                }
            }

            Spacer()

            // Last checked timestamp
            VStack(alignment: .trailing, spacing: 4) {
                Text(String(localized: "Last Checked"))
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(compliance.generatedAt.displayDateTime)
                    .font(.caption)
                    .fontWeight(.medium)
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func complianceStatBadge(count: Int, label: String, color: Color) -> some View {
        HStack(spacing: 4) {
            Text("\(count)")
                .font(.headline)
                .fontWeight(.bold)
                .foregroundColor(color)

            Text(label)
                .font(.caption)
                .foregroundColor(.secondary)
        }
    }

    // MARK: - Compliance Check Type Grid

    private var complianceCheckTypeGrid: some View {
        LazyVGrid(columns: [
            GridItem(.flexible()),
            GridItem(.flexible())
        ], spacing: 16) {
            ForEach(ComplianceCheckType.allCases, id: \.self) { checkType in
                ComplianceCheckTypeCard(
                    checkType: checkType,
                    check: compliance.checks.first(where: { $0.checkType == checkType }),
                    isSelected: selectedCheckType == checkType,
                    onTap: {
                        withAnimation(.easeInOut(duration: 0.2)) {
                            if selectedCheckType == checkType {
                                selectedCheckType = nil
                            } else {
                                selectedCheckType = checkType
                            }
                        }
                    }
                )
            }
        }
    }

    // MARK: - Filtered Checks Section

    private func filteredChecksSection(for checkType: ComplianceCheckType) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Image(systemName: checkType.iconName)
                    .foregroundColor(.accentColor)

                Text(checkType.displayName)
                    .font(.headline)

                Spacer()

                Button(action: {
                    withAnimation {
                        selectedCheckType = nil
                    }
                }) {
                    Image(systemName: "xmark.circle.fill")
                        .foregroundColor(.secondary)
                }
                .buttonStyle(.plain)
            }

            // Regulatory reference
            Text(checkType.regulatoryReference)
                .font(.caption)
                .foregroundColor(.secondary)
                .padding(.bottom, 8)

            // Check details
            if let check = compliance.checks.first(where: { $0.checkType == checkType }) {
                ComplianceCheckDetailCard(check: check)
            } else {
                Text(String(localized: "No data available for this check type"))
                    .font(.subheadline)
                    .foregroundColor(.secondary)
                    .frame(maxWidth: .infinity)
                    .padding()
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    // MARK: - Issues Section

    private var issuesSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Image(systemName: "exclamationmark.triangle.fill")
                    .foregroundColor(.orange)

                Text(String(localized: "Action Required"))
                    .font(.headline)

                Spacer()

                Text(String(localized: "\(compliance.checksRequiringAttention.count) items"))
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            ForEach(compliance.checksRequiringAttention) { check in
                ComplianceIssueRow(
                    check: check,
                    onResolve: {
                        onSelectCheck?(check)
                    }
                )
            }
        }
        .padding()
        .background(Color.orange.opacity(0.1))
        .cornerRadius(12)
    }
}

// MARK: - Compliance Score Gauge

/// Circular gauge showing overall compliance percentage.
struct ComplianceScoreGauge: View {

    let percentage: Double
    let status: ComplianceStatus

    var body: some View {
        ZStack {
            // Background circle
            Circle()
                .stroke(Color.gray.opacity(0.2), lineWidth: 8)
                .frame(width: 100, height: 100)

            // Progress circle
            Circle()
                .trim(from: 0, to: CGFloat(percentage / 100))
                .stroke(
                    Color(status.color),
                    style: StrokeStyle(lineWidth: 8, lineCap: .round)
                )
                .frame(width: 100, height: 100)
                .rotationEffect(.degrees(-90))
                .animation(.easeInOut(duration: 0.5), value: percentage)

            // Center content
            VStack(spacing: 2) {
                Text(percentage.asPercentage)
                    .font(.title2)
                    .fontWeight(.bold)
                    .foregroundColor(Color(status.color))

                Text(String(localized: "Score"))
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }
        }
    }
}

// MARK: - Compliance Check Type Card

/// Card displaying a single compliance check type with status.
struct ComplianceCheckTypeCard: View {

    let checkType: ComplianceCheckType
    let check: ComplianceCheckResponse?
    let isSelected: Bool
    var onTap: (() -> Void)?

    private var status: ComplianceStatus {
        check?.status ?? .unknown
    }

    var body: some View {
        Button(action: { onTap?() }) {
            VStack(alignment: .leading, spacing: 12) {
                // Header
                HStack {
                    Image(systemName: checkType.iconName)
                        .font(.title2)
                        .foregroundColor(Color(status.color))
                        .frame(width: 32)

                    VStack(alignment: .leading, spacing: 2) {
                        Text(checkType.displayName)
                            .font(.headline)
                            .foregroundColor(.primary)

                        Text(status.displayName)
                            .font(.caption)
                            .foregroundColor(Color(status.color))
                    }

                    Spacer()

                    Image(systemName: status.iconName)
                        .foregroundColor(Color(status.color))
                }

                // Description
                Text(checkType.description)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(2)
                    .multilineTextAlignment(.leading)

                // Details if available
                if let check = check {
                    Divider()

                    HStack {
                        if let nextDue = check.formattedNextCheckDue {
                            HStack(spacing: 4) {
                                Image(systemName: "calendar")
                                    .font(.caption2)
                                Text(String(localized: "Next: \(nextDue)"))
                                    .font(.caption2)
                            }
                            .foregroundColor(.secondary)
                        }

                        Spacer()

                        if check.requiresAttention {
                            Text(String(localized: "Action needed"))
                                .font(.caption2)
                                .fontWeight(.medium)
                                .foregroundColor(.orange)
                        }
                    }
                }
            }
            .padding()
            .background(
                RoundedRectangle(cornerRadius: 12)
                    .fill(Color(NSColor.controlBackgroundColor))
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(
                                isSelected ? Color.accentColor : Color.clear,
                                lineWidth: 2
                            )
                    )
            )
        }
        .buttonStyle(.plain)
    }
}

// MARK: - Compliance Check Detail Card

/// Detailed card for a single compliance check with full information.
struct ComplianceCheckDetailCard: View {

    let check: ComplianceCheckResponse

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Status header
            HStack {
                Image(systemName: check.statusIcon)
                    .font(.title)
                    .foregroundColor(Color(check.statusColor))

                VStack(alignment: .leading, spacing: 2) {
                    Text(check.statusDisplayName)
                        .font(.headline)

                    Text(String(localized: "Last checked: \(check.formattedCheckedAt)"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Spacer()
            }

            // Details breakdown
            if let details = check.details, !details.isEmpty {
                Divider()

                VStack(alignment: .leading, spacing: 8) {
                    Text(String(localized: "Details"))
                        .font(.subheadline)
                        .fontWeight(.medium)

                    ForEach(Array(details.sorted(by: { $0.key < $1.key })), id: \.key) { key, value in
                        HStack {
                            Text(formatDetailKey(key))
                                .font(.caption)
                                .foregroundColor(.secondary)

                            Spacer()

                            Text(value)
                                .font(.caption)
                                .fontWeight(.medium)
                        }
                    }
                }
            }

            // Recommendation
            if let recommendation = check.recommendation {
                Divider()

                VStack(alignment: .leading, spacing: 8) {
                    HStack(spacing: 4) {
                        Image(systemName: "lightbulb.fill")
                            .font(.caption)
                        Text(String(localized: "Recommendation"))
                            .font(.subheadline)
                            .fontWeight(.medium)
                    }
                    .foregroundColor(.orange)

                    Text(recommendation)
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            // Next check due
            if let daysUntil = check.daysUntilNextCheck {
                Divider()

                HStack {
                    Image(systemName: daysUntil < 0 ? "exclamationmark.circle.fill" : "calendar.badge.clock")
                        .foregroundColor(daysUntil < 0 ? .red : .secondary)

                    if daysUntil < 0 {
                        Text(String(localized: "Overdue by \(abs(daysUntil)) days"))
                            .font(.caption)
                            .foregroundColor(.red)
                    } else if daysUntil == 0 {
                        Text(String(localized: "Due today"))
                            .font(.caption)
                            .foregroundColor(.orange)
                    } else {
                        Text(String(localized: "Next check in \(daysUntil) days"))
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Spacer()
                }
            }
        }
        .padding()
        .background(Color(check.statusColor).opacity(0.05))
        .cornerRadius(8)
    }

    private func formatDetailKey(_ key: String) -> String {
        key.replacingOccurrences(of: "_", with: " ").capitalized
    }
}

// MARK: - Compliance Issue Row

/// Row displaying a compliance issue with action button.
struct ComplianceIssueRow: View {

    let check: ComplianceCheckResponse
    var onResolve: (() -> Void)?

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: check.status == .violation
                  ? "xmark.octagon.fill"
                  : "exclamationmark.triangle.fill")
                .font(.title3)
                .foregroundColor(Color(check.statusColor))

            VStack(alignment: .leading, spacing: 2) {
                Text(check.checkTypeDisplayName)
                    .font(.subheadline)
                    .fontWeight(.medium)

                if let recommendation = check.recommendation {
                    Text(recommendation)
                        .font(.caption)
                        .foregroundColor(.secondary)
                        .lineLimit(2)
                }
            }

            Spacer()

            Button(action: { onResolve?() }) {
                Text(String(localized: "Resolve"))
                    .font(.caption)
            }
            .buttonStyle(.bordered)
        }
        .padding(12)
        .background(Color(check.statusColor).opacity(0.1))
        .cornerRadius(8)
    }
}

// MARK: - Staff Ratio Compliance View

/// Detailed view for staff-to-child ratio compliance by age group.
struct StaffRatioComplianceView: View {

    let ratioDetails: [StaffRatioDetails]

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header
            HStack {
                Image(systemName: "person.3.fill")
                    .foregroundColor(.accentColor)

                Text(String(localized: "Staff-to-Child Ratios"))
                    .font(.headline)

                Spacer()
            }

            // Age group breakdown
            ForEach(AgeGroup.allCases, id: \.self) { ageGroup in
                if let details = ratioDetails.first(where: { $0.ageGroup == ageGroup }) {
                    StaffRatioRow(details: details)
                }
            }

            // Legend
            HStack(spacing: 16) {
                legendItem(color: .green, text: String(localized: "Compliant"))
                legendItem(color: .orange, text: String(localized: "Warning"))
                legendItem(color: .red, text: String(localized: "Violation"))
            }
            .font(.caption)
            .foregroundColor(.secondary)
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }

    private func legendItem(color: Color, text: String) -> some View {
        HStack(spacing: 4) {
            Circle()
                .fill(color)
                .frame(width: 8, height: 8)
            Text(text)
        }
    }
}

// MARK: - Staff Ratio Row

/// Row displaying staff ratio for a single age group.
struct StaffRatioRow: View {

    let details: StaffRatioDetails

    var body: some View {
        HStack(spacing: 16) {
            // Age group
            VStack(alignment: .leading, spacing: 2) {
                Text(details.ageGroup.displayName)
                    .font(.subheadline)
                    .fontWeight(.medium)

                Text(details.ageGroup.frenchDescription)
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }
            .frame(minWidth: 180, alignment: .leading)

            // Current ratio
            VStack(alignment: .center, spacing: 2) {
                Text(details.formattedCurrentRatio)
                    .font(.headline)
                    .fontWeight(.bold)
                    .foregroundColor(Color(details.status.color))

                Text(String(localized: "Current"))
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }

            // Required ratio
            VStack(alignment: .center, spacing: 2) {
                Text(details.formattedRequiredRatio)
                    .font(.headline)
                    .foregroundColor(.secondary)

                Text(String(localized: "Required"))
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }

            Spacer()

            // Status indicator
            HStack(spacing: 4) {
                Image(systemName: details.status.iconName)
                Text(details.status.displayName)
            }
            .font(.caption)
            .foregroundColor(Color(details.status.color))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(Color(details.status.color).opacity(0.15))
            .cornerRadius(4)

            // Additional staff needed
            if details.additionalStaffNeeded > 0 {
                Text(String(localized: "+\(details.additionalStaffNeeded) staff needed"))
                    .font(.caption)
                    .foregroundColor(.red)
            }
        }
        .padding(.vertical, 8)
    }
}

// MARK: - Certification Compliance View

/// Detailed view for staff certification compliance.
struct CertificationComplianceView: View {

    let certifications: [CertificationStatus]

    private var groupedCertifications: [CertificationType: [CertificationStatus]] {
        Dictionary(grouping: certifications) { $0.certificationType }
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header
            HStack {
                Image(systemName: "checkmark.seal.fill")
                    .foregroundColor(.accentColor)

                Text(String(localized: "Staff Certifications"))
                    .font(.headline)

                Spacer()

                // Summary badges
                let expiring = certifications.filter { $0.isExpiringSoon }.count
                let expired = certifications.filter { $0.isExpired }.count

                if expired > 0 {
                    Text(String(localized: "\(expired) expired"))
                        .font(.caption)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color.red.opacity(0.15))
                        .foregroundColor(.red)
                        .cornerRadius(4)
                }

                if expiring > 0 {
                    Text(String(localized: "\(expiring) expiring"))
                        .font(.caption)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(Color.orange.opacity(0.15))
                        .foregroundColor(.orange)
                        .cornerRadius(4)
                }
            }

            // Certification type breakdown
            ForEach(CertificationType.allCases, id: \.self) { certType in
                if let certs = groupedCertifications[certType], !certs.isEmpty {
                    CertificationTypeSection(
                        certificationType: certType,
                        certifications: certs
                    )
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Certification Type Section

/// Section showing certifications of a specific type.
struct CertificationTypeSection: View {

    let certificationType: CertificationType
    let certifications: [CertificationStatus]

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(certificationType.displayName)
                    .font(.subheadline)
                    .fontWeight(.medium)

                if certificationType.expires, let years = certificationType.validityYears {
                    Text(String(localized: "(\(years) year validity)"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }

                Spacer()
            }

            ForEach(certifications) { cert in
                CertificationStatusRow(certification: cert)
            }
        }
        .padding(.vertical, 8)
    }
}

// MARK: - Certification Status Row

/// Row displaying a single certification status.
struct CertificationStatusRow: View {

    let certification: CertificationStatus

    var body: some View {
        HStack(spacing: 12) {
            // Staff name
            Text(certification.staffName)
                .font(.caption)
                .frame(minWidth: 120, alignment: .leading)

            // Status badge
            HStack(spacing: 4) {
                Image(systemName: statusIcon)
                Text(certification.status.displayName)
            }
            .font(.caption2)
            .foregroundColor(Color(certification.status.color))

            Spacer()

            // Expiration info
            if let expDate = certification.formattedExpirationDate {
                Text(expDate)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            // Days until expiration
            if let days = certification.daysUntilExpiration {
                if days < 0 {
                    Text(String(localized: "Expired"))
                        .font(.caption)
                        .foregroundColor(.red)
                } else if days <= 30 {
                    Text(String(localized: "\(days) days"))
                        .font(.caption)
                        .foregroundColor(.orange)
                }
            }
        }
        .padding(.vertical, 4)
        .padding(.horizontal, 8)
        .background(Color(certification.status.color).opacity(0.05))
        .cornerRadius(4)
    }

    private var statusIcon: String {
        switch certification.status {
        case .valid:
            return "checkmark.circle.fill"
        case .expiringSoon:
            return "exclamationmark.triangle.fill"
        case .expired:
            return "xmark.circle.fill"
        case .missing:
            return "questionmark.circle.fill"
        }
    }
}

// MARK: - Capacity Compliance View

/// View showing facility capacity compliance status.
struct CapacityComplianceView: View {

    let currentEnrollment: Int
    let licensedCapacity: Int
    let status: ComplianceStatus

    private var utilizationPercentage: Double {
        guard licensedCapacity > 0 else { return 0 }
        return (Double(currentEnrollment) / Double(licensedCapacity)) * 100
    }

    private var availableSpots: Int {
        max(0, licensedCapacity - currentEnrollment)
    }

    private var overCapacityBy: Int {
        max(0, currentEnrollment - licensedCapacity)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header
            HStack {
                Image(systemName: "building.2.fill")
                    .foregroundColor(.accentColor)

                Text(String(localized: "Facility Capacity"))
                    .font(.headline)

                Spacer()

                // Status badge
                HStack(spacing: 4) {
                    Image(systemName: status.iconName)
                    Text(status.displayName)
                }
                .font(.caption)
                .foregroundColor(Color(status.color))
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color(status.color).opacity(0.15))
                .cornerRadius(4)
            }

            // Capacity visualization
            VStack(alignment: .leading, spacing: 8) {
                HStack {
                    Text(String(localized: "\(currentEnrollment) / \(licensedCapacity)"))
                        .font(.title)
                        .fontWeight(.bold)

                    Text(String(localized: "enrolled"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Spacer()

                    Text(utilizationPercentage.asPercentage)
                        .font(.title2)
                        .fontWeight(.semibold)
                        .foregroundColor(Color(status.color))
                }

                // Progress bar
                GeometryReader { geometry in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: 4)
                            .fill(Color.gray.opacity(0.2))
                            .frame(height: 12)

                        RoundedRectangle(cornerRadius: 4)
                            .fill(Color(status.color))
                            .frame(width: min(geometry.size.width, geometry.size.width * CGFloat(utilizationPercentage / 100)), height: 12)
                    }
                }
                .frame(height: 12)

                // Status message
                HStack {
                    if overCapacityBy > 0 {
                        Image(systemName: "exclamationmark.triangle.fill")
                            .foregroundColor(.red)
                        Text(String(localized: "Over capacity by \(overCapacityBy) children"))
                            .font(.caption)
                            .foregroundColor(.red)
                    } else {
                        Image(systemName: "checkmark.circle.fill")
                            .foregroundColor(.green)
                        Text(String(localized: "\(availableSpots) spots available"))
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Spacer()
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(12)
    }
}

// MARK: - Date Extension for DateTime

extension Date {

    /// Formatted date and time string for display
    var displayDateTime: String {
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        return formatter.string(from: self)
    }
}

// MARK: - Preview

#Preview("Compliance View") {
    ComplianceView(compliance: .preview)
        .frame(width: 800)
        .padding()
}

#Preview("Compliance View - With Warnings") {
    ComplianceView(compliance: .previewWithWarnings)
        .frame(width: 800)
        .padding()
}

#Preview("Compliance Score Gauge") {
    HStack(spacing: 40) {
        ComplianceScoreGauge(percentage: 95.0, status: .compliant)
        ComplianceScoreGauge(percentage: 75.0, status: .warning)
        ComplianceScoreGauge(percentage: 50.0, status: .violation)
    }
    .padding()
}

#Preview("Compliance Check Type Card") {
    VStack(spacing: 16) {
        ComplianceCheckTypeCard(
            checkType: .staffRatio,
            check: .previewCompliant,
            isSelected: false
        )

        ComplianceCheckTypeCard(
            checkType: .certification,
            check: .previewWarning,
            isSelected: true
        )

        ComplianceCheckTypeCard(
            checkType: .capacity,
            check: .previewViolation,
            isSelected: false
        )
    }
    .frame(width: 350)
    .padding()
}

#Preview("Capacity Compliance View") {
    VStack(spacing: 16) {
        CapacityComplianceView(
            currentEnrollment: 48,
            licensedCapacity: 52,
            status: .compliant
        )

        CapacityComplianceView(
            currentEnrollment: 55,
            licensedCapacity: 52,
            status: .violation
        )
    }
    .frame(width: 500)
    .padding()
}
