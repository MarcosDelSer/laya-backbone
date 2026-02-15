//
//  InvoiceRowView.swift
//  LAYAAdmin
//
//  Row view for displaying an invoice in the invoice list.
//  Shows invoice number, family name, amount, status, and due date.
//

import SwiftUI

// MARK: - Invoice Row View

/// A row view displaying an invoice's information in the list.
///
/// Features:
/// - Invoice number and family name
/// - Total amount and balance due
/// - Payment status badge
/// - Due date with overdue indicator
/// - Selection checkbox for bulk operations
struct InvoiceRowView: View {

    // MARK: - Properties

    /// The invoice to display
    let invoice: Invoice

    /// Whether this invoice is selected
    let isSelected: Bool

    /// Callback for toggling selection
    let onToggleSelect: (() -> Void)?

    // MARK: - Initialization

    /// Creates a new InvoiceRowView
    /// - Parameters:
    ///   - invoice: The invoice to display
    ///   - isSelected: Whether this invoice is selected (default: false)
    ///   - onToggleSelect: Optional callback for selection toggle
    init(
        invoice: Invoice,
        isSelected: Bool = false,
        onToggleSelect: (() -> Void)? = nil
    ) {
        self.invoice = invoice
        self.isSelected = isSelected
        self.onToggleSelect = onToggleSelect
    }

    // MARK: - Body

    var body: some View {
        HStack(spacing: 12) {
            // Selection checkbox (if selection is enabled)
            if onToggleSelect != nil {
                Button(action: {
                    onToggleSelect?()
                }) {
                    Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                        .font(.title3)
                        .foregroundColor(isSelected ? .accentColor : .secondary)
                }
                .buttonStyle(.plain)
            }

            // Invoice icon
            invoiceIcon

            // Main content
            VStack(alignment: .leading, spacing: 4) {
                // Top row: Invoice number and status
                HStack(spacing: 8) {
                    Text(invoice.number)
                        .font(.headline)
                        .lineLimit(1)

                    InvoiceStatusBadge(status: invoice.status)

                    if invoice.isPastDue {
                        OverdueAlertBadge()
                    }
                }

                // Bottom row: Details
                HStack(spacing: 16) {
                    // Family name
                    Label(invoice.familyName, systemImage: "person.2")
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                        .lineLimit(1)

                    // Child name (if available)
                    if let childName = invoice.childName {
                        Label(childName, systemImage: "figure.child")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                            .lineLimit(1)
                    }

                    // Due date
                    Label(invoice.dueDate.displayDate, systemImage: "calendar")
                        .font(.subheadline)
                        .foregroundColor(invoice.isPastDue ? .red : .secondary)
                }
            }

            Spacer()

            // Amount column
            VStack(alignment: .trailing, spacing: 2) {
                Text(invoice.formattedTotal)
                    .font(.headline)
                    .foregroundColor(.primary)

                if invoice.balanceDue > 0 && invoice.balanceDue != invoice.totalAmount {
                    Text(String(localized: "Due: \(invoice.formattedBalanceDue)"))
                        .font(.caption)
                        .foregroundColor(.orange)
                }
            }

            // Chevron indicator
            Image(systemName: "chevron.right")
                .font(.caption)
                .foregroundColor(.tertiary)
        }
        .padding(.vertical, 8)
        .contentShape(Rectangle())
    }

    // MARK: - Invoice Icon

    private var invoiceIcon: some View {
        RoundedRectangle(cornerRadius: 8)
            .fill(iconBackgroundColor)
            .frame(width: 44, height: 44)
            .overlay {
                Image(systemName: iconName)
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundColor(iconColor)
            }
    }

    private var iconBackgroundColor: Color {
        switch invoice.status {
        case .paid:
            return Color.green.opacity(0.15)
        case .pending:
            return Color.orange.opacity(0.15)
        case .overdue:
            return Color.red.opacity(0.15)
        case .draft:
            return Color.gray.opacity(0.15)
        case .cancelled:
            return Color.gray.opacity(0.15)
        }
    }

    private var iconColor: Color {
        switch invoice.status {
        case .paid:
            return .green
        case .pending:
            return .orange
        case .overdue:
            return .red
        case .draft:
            return .gray
        case .cancelled:
            return .gray
        }
    }

    private var iconName: String {
        switch invoice.status {
        case .paid:
            return "checkmark.circle"
        case .pending:
            return "clock"
        case .overdue:
            return "exclamationmark.circle"
        case .draft:
            return "doc"
        case .cancelled:
            return "xmark.circle"
        }
    }
}

// MARK: - Invoice Status Badge

/// A badge displaying the payment status with appropriate styling.
struct InvoiceStatusBadge: View {

    let status: InvoiceStatus

    var body: some View {
        Text(status.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(statusBackgroundColor)
            .foregroundColor(statusTextColor)
            .cornerRadius(4)
    }

    private var statusBackgroundColor: Color {
        switch status {
        case .draft:
            return Color.gray.opacity(0.15)
        case .pending:
            return Color.orange.opacity(0.15)
        case .paid:
            return Color.green.opacity(0.15)
        case .overdue:
            return Color.red.opacity(0.15)
        case .cancelled:
            return Color.gray.opacity(0.15)
        }
    }

    private var statusTextColor: Color {
        switch status {
        case .draft:
            return .gray
        case .pending:
            return .orange
        case .paid:
            return .green
        case .overdue:
            return .red
        case .cancelled:
            return .gray
        }
    }
}

// MARK: - Overdue Alert Badge

/// A badge indicating the invoice is past due.
struct OverdueAlertBadge: View {

    var body: some View {
        HStack(spacing: 2) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.caption2)

            Text(String(localized: "Past Due"))
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 4)
        .padding(.vertical, 2)
        .background(Color.red.opacity(0.15))
        .foregroundColor(.red)
        .cornerRadius(4)
    }
}

// MARK: - Compact Invoice Row View

/// A more compact version of the invoice row for use in tight spaces.
struct CompactInvoiceRowView: View {

    let invoice: Invoice

    var body: some View {
        HStack(spacing: 10) {
            // Icon
            RoundedRectangle(cornerRadius: 6)
                .fill(statusColor.opacity(0.15))
                .frame(width: 32, height: 32)
                .overlay {
                    Image(systemName: "doc.text")
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundColor(statusColor)
                }

            // Invoice info
            VStack(alignment: .leading, spacing: 2) {
                Text(invoice.number)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Text(invoice.familyName)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Spacer()

            // Amount and status
            VStack(alignment: .trailing, spacing: 2) {
                Text(invoice.formattedTotal)
                    .font(.subheadline)
                    .fontWeight(.medium)

                // Status indicator
                Circle()
                    .fill(statusColor)
                    .frame(width: 8, height: 8)
            }
        }
        .contentShape(Rectangle())
    }

    private var statusColor: Color {
        switch invoice.status {
        case .paid:
            return .green
        case .pending:
            return .orange
        case .overdue:
            return .red
        case .draft, .cancelled:
            return .gray
        }
    }
}

// MARK: - Preview

#Preview("Invoice Row View") {
    VStack(spacing: 0) {
        InvoiceRowView(invoice: .preview, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        InvoiceRowView(invoice: .previewPaid, isSelected: true, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        InvoiceRowView(invoice: .previewOverdue, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)
    }
    .frame(width: 700)
}

#Preview("Invoice Row View - Without Selection") {
    VStack(spacing: 0) {
        InvoiceRowView(invoice: .preview)
            .padding(.horizontal)

        Divider()

        InvoiceRowView(invoice: .previewPaid)
            .padding(.horizontal)
    }
    .frame(width: 700)
}

#Preview("Invoice Status Badge") {
    HStack(spacing: 12) {
        InvoiceStatusBadge(status: .draft)
        InvoiceStatusBadge(status: .pending)
        InvoiceStatusBadge(status: .paid)
        InvoiceStatusBadge(status: .overdue)
        InvoiceStatusBadge(status: .cancelled)
    }
    .padding()
}

#Preview("Overdue Alert Badge") {
    OverdueAlertBadge()
        .padding()
}

#Preview("Compact Invoice Row View") {
    VStack(spacing: 8) {
        CompactInvoiceRowView(invoice: .preview)
        CompactInvoiceRowView(invoice: .previewPaid)
        CompactInvoiceRowView(invoice: .previewOverdue)
    }
    .frame(width: 300)
    .padding()
}
