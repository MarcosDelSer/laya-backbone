//
//  InvoiceDetailView.swift
//  LAYAAdmin
//
//  Detail view for displaying comprehensive invoice information.
//  Shows invoice details, line items, payment status, and PDF download.
//

import SwiftUI

// MARK: - Invoice Detail View

/// A comprehensive detail view displaying all information about an invoice.
///
/// Features:
/// - Invoice header with number and status
/// - Amount summary with balance due
/// - Line items table
/// - Billing period information
/// - Payment history
/// - PDF download functionality
/// - Edit and delete actions
struct InvoiceDetailView: View {

    // MARK: - Properties

    /// The invoice to display
    let invoice: Invoice

    /// Callback when edit is requested
    var onEdit: ((Invoice) -> Void)?

    /// Callback when delete is requested
    var onDelete: ((Invoice) -> Void)?

    /// Callback when record payment is requested
    var onRecordPayment: ((Invoice) -> Void)?

    /// Environment to dismiss the view
    @Environment(\.dismiss) private var dismiss

    /// Whether to show delete confirmation dialog
    @State private var showDeleteConfirmation = false

    /// Whether PDF download is in progress
    @State private var isDownloadingPDF = false

    // MARK: - Body

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                // Header section with invoice info and status
                headerSection

                Divider()

                // Amount summary section
                amountSummarySection

                // Line items section
                itemsSection

                // Billing period section
                if invoice.formattedBillingPeriod != nil {
                    billingPeriodSection
                }

                // Notes section
                notesSection

                // Metadata section
                metadataSection
            }
            .padding(24)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Invoice \(invoice.number)"))
        .toolbar {
            detailToolbar
        }
        .confirmationDialog(
            String(localized: "Delete Invoice"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                onDelete?(invoice)
                dismiss()
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        } message: {
            Text(String(localized: "Are you sure you want to delete invoice \(invoice.number)? This action cannot be undone."))
        }
    }

    // MARK: - Header Section

    private var headerSection: some View {
        HStack(spacing: 20) {
            // Invoice icon
            invoiceIcon

            VStack(alignment: .leading, spacing: 8) {
                // Invoice number and status
                HStack(spacing: 12) {
                    Text(String(localized: "Invoice #\(invoice.number)"))
                        .font(.title)
                        .fontWeight(.bold)

                    InvoiceStatusBadge(status: invoice.status)

                    if invoice.isPastDue {
                        OverdueAlertBadge()
                    }
                }

                // Family and child info
                HStack(spacing: 20) {
                    Label(invoice.familyName, systemImage: "person.2")
                        .font(.headline)
                        .foregroundColor(.secondary)

                    if let childName = invoice.childName {
                        Label(childName, systemImage: "figure.child")
                            .font(.headline)
                            .foregroundColor(.secondary)
                    }

                    Label(String(localized: "Issued: \(invoice.date.displayDate)"), systemImage: "calendar")
                        .font(.headline)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()
        }
    }

    // MARK: - Invoice Icon

    private var invoiceIcon: some View {
        RoundedRectangle(cornerRadius: 12)
            .fill(iconBackgroundColor)
            .frame(width: 64, height: 64)
            .overlay {
                Image(systemName: iconName)
                    .font(.system(size: 28, weight: .semibold))
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

    // MARK: - Amount Summary Section

    private var amountSummarySection: some View {
        DetailSection(title: String(localized: "Payment Summary")) {
            HStack(spacing: 32) {
                // Total Amount
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Total Amount"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(invoice.formattedTotal)
                        .font(.system(size: 28, weight: .bold))
                        .foregroundColor(.primary)
                }

                Divider()
                    .frame(height: 50)

                // Amount Paid
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Amount Paid"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(invoice.formattedAmountPaid)
                        .font(.title2)
                        .fontWeight(.semibold)
                        .foregroundColor(.green)
                }

                Divider()
                    .frame(height: 50)

                // Balance Due
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Balance Due"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(invoice.formattedBalanceDue)
                        .font(.title2)
                        .fontWeight(.semibold)
                        .foregroundColor(invoice.balanceDue > 0 ? .orange : .green)
                }

                Divider()
                    .frame(height: 50)

                // Due Date
                VStack(alignment: .leading, spacing: 4) {
                    Text(String(localized: "Due Date"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(invoice.dueDate.displayDate)
                        .font(.title3)
                        .fontWeight(.medium)
                        .foregroundColor(invoice.isPastDue ? .red : .primary)

                    if invoice.status != .paid {
                        Text(dueDateStatusText)
                            .font(.caption)
                            .foregroundColor(invoice.isPastDue ? .red : .secondary)
                    }
                }

                Spacer()
            }
            .padding()
            .background(Color(NSColor.controlBackgroundColor))
            .cornerRadius(8)
        }
    }

    private var dueDateStatusText: String {
        let days = invoice.daysUntilDue
        if days < 0 {
            let absDays = abs(days)
            return String(localized: "\(absDays) day\(absDays != 1 ? "s" : "") overdue")
        } else if days == 0 {
            return String(localized: "Due today")
        } else {
            return String(localized: "\(days) day\(days != 1 ? "s" : "") remaining")
        }
    }

    // MARK: - Items Section

    private var itemsSection: some View {
        DetailSection(title: String(localized: "Invoice Items")) {
            VStack(spacing: 0) {
                if invoice.items.isEmpty {
                    emptyItemsView
                } else {
                    itemsTable
                }
            }
            .background(Color(NSColor.controlBackgroundColor))
            .cornerRadius(8)
        }
    }

    private var emptyItemsView: some View {
        HStack {
            Spacer()
            VStack(spacing: 8) {
                Image(systemName: "doc.text")
                    .font(.largeTitle)
                    .foregroundColor(.secondary)
                Text(String(localized: "No items on this invoice"))
                    .font(.subheadline)
                    .foregroundColor(.secondary)
            }
            .padding(32)
            Spacer()
        }
    }

    private var itemsTable: some View {
        VStack(spacing: 0) {
            // Table header
            HStack(spacing: 0) {
                Text(String(localized: "Description"))
                    .frame(maxWidth: .infinity, alignment: .leading)

                Text(String(localized: "Qty"))
                    .frame(width: 60, alignment: .trailing)

                Text(String(localized: "Unit Price"))
                    .frame(width: 100, alignment: .trailing)

                Text(String(localized: "Total"))
                    .frame(width: 100, alignment: .trailing)
            }
            .font(.caption)
            .fontWeight(.medium)
            .foregroundColor(.secondary)
            .textCase(.uppercase)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(Color(NSColor.windowBackgroundColor).opacity(0.5))

            Divider()

            // Table rows
            ForEach(invoice.items) { item in
                itemRow(item: item)
            }

            Divider()

            // Subtotal
            HStack(spacing: 0) {
                Text(String(localized: "Subtotal"))
                    .frame(maxWidth: .infinity, alignment: .trailing)
                    .fontWeight(.medium)

                Text(invoice.formattedSubtotal)
                    .frame(width: 100, alignment: .trailing)
                    .fontWeight(.medium)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 8)

            // Tax
            HStack(spacing: 0) {
                Text(String(localized: "Tax (QST + GST)"))
                    .frame(maxWidth: .infinity, alignment: .trailing)
                    .foregroundColor(.secondary)

                Text(invoice.formattedTax)
                    .frame(width: 100, alignment: .trailing)
                    .foregroundColor(.secondary)
            }
            .font(.subheadline)
            .padding(.horizontal, 16)
            .padding(.vertical, 4)

            Divider()

            // Total
            HStack(spacing: 0) {
                Text(String(localized: "Total"))
                    .frame(maxWidth: .infinity, alignment: .trailing)
                    .font(.headline)

                Text(invoice.formattedTotal)
                    .frame(width: 100, alignment: .trailing)
                    .font(.headline)
                    .fontWeight(.bold)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .background(Color.accentColor.opacity(0.05))
        }
    }

    private func itemRow(item: InvoiceItem) -> some View {
        HStack(spacing: 0) {
            VStack(alignment: .leading, spacing: 2) {
                Text(item.description)
                    .lineLimit(2)

                HStack(spacing: 8) {
                    Text(item.category.displayName)
                        .font(.caption)
                        .foregroundColor(.secondary)

                    if item.isQualifyingExpense {
                        Text(String(localized: "RL-24"))
                            .font(.caption2)
                            .fontWeight(.medium)
                            .padding(.horizontal, 4)
                            .padding(.vertical, 1)
                            .background(Color.green.opacity(0.15))
                            .foregroundColor(.green)
                            .cornerRadius(2)
                    }
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)

            Text(String(format: "%.0f", item.quantity))
                .frame(width: 60, alignment: .trailing)
                .foregroundColor(.secondary)

            Text(item.formattedUnitPrice)
                .frame(width: 100, alignment: .trailing)
                .foregroundColor(.secondary)

            Text(item.formattedTotal)
                .frame(width: 100, alignment: .trailing)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
    }

    // MARK: - Billing Period Section

    @ViewBuilder
    private var billingPeriodSection: some View {
        if let billingPeriod = invoice.formattedBillingPeriod {
            DetailSection(title: String(localized: "Billing Period")) {
                HStack(spacing: 12) {
                    Image(systemName: "calendar.badge.clock")
                        .font(.title2)
                        .foregroundColor(.accentColor)

                    Text(billingPeriod)
                        .font(.body)
                }
                .padding()
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color(NSColor.controlBackgroundColor))
                .cornerRadius(8)
            }
        }
    }

    // MARK: - Notes Section

    @ViewBuilder
    private var notesSection: some View {
        if let notes = invoice.notes, !notes.isEmpty {
            DetailSection(title: String(localized: "Notes")) {
                Text(notes)
                    .font(.body)
                    .foregroundColor(.primary)
                    .padding()
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(Color(NSColor.controlBackgroundColor))
                    .cornerRadius(8)
            }
        }
    }

    // MARK: - Metadata Section

    private var metadataSection: some View {
        HStack(spacing: 24) {
            if let createdAt = invoice.createdAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Created:"))
                        .foregroundColor(.secondary)
                    Text(createdAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            if let updatedAt = invoice.updatedAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Updated:"))
                        .foregroundColor(.secondary)
                    Text(updatedAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            Spacer()

            Text(String(localized: "ID: \(invoice.id)"))
                .font(.caption)
                .foregroundColor(.tertiary)
        }
        .padding(.top, 8)
    }

    // MARK: - PDF Download

    private func downloadPDF() {
        guard let pdfUrlString = invoice.pdfUrl,
              let pdfUrl = URL(string: pdfUrlString) else {
            return
        }

        isDownloadingPDF = true

        // Open the PDF URL in the default browser/PDF viewer
        NSWorkspace.shared.open(pdfUrl)

        // Reset the loading state after a short delay
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.5) {
            isDownloadingPDF = false
        }
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var detailToolbar: some ToolbarContent {
        // PDF Download button
        ToolbarItem(placement: .primaryAction) {
            Button(action: downloadPDF) {
                if isDownloadingPDF {
                    ProgressView()
                        .controlSize(.small)
                } else {
                    Label(String(localized: "Download PDF"), systemImage: "arrow.down.doc")
                }
            }
            .disabled(invoice.pdfUrl == nil || isDownloadingPDF)
            .help(invoice.pdfUrl != nil
                  ? String(localized: "Download invoice as PDF")
                  : String(localized: "PDF not available"))
        }

        // Record payment button (only for unpaid invoices)
        if invoice.status != .paid && invoice.status != .cancelled {
            ToolbarItem(placement: .primaryAction) {
                Button(action: {
                    onRecordPayment?(invoice)
                }) {
                    Label(String(localized: "Record Payment"), systemImage: "dollarsign.circle")
                }
                .keyboardShortcut("p", modifiers: [.command, .shift])
                .help(String(localized: "Record a payment for this invoice"))
            }
        }

        // Edit button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                onEdit?(invoice)
            }) {
                Label(String(localized: "Edit"), systemImage: "pencil")
            }
            .keyboardShortcut("e", modifiers: [.command])
            .help(String(localized: "Edit invoice (Cmd+E)"))
        }

        // Delete button (only for draft or pending invoices)
        if invoice.status == .draft || invoice.status == .pending {
            ToolbarItem(placement: .destructiveAction) {
                Button(role: .destructive, action: {
                    showDeleteConfirmation = true
                }) {
                    Label(String(localized: "Delete"), systemImage: "trash")
                }
                .help(String(localized: "Delete invoice"))
            }
        }
    }
}

// MARK: - Preview

#Preview("Invoice Detail View - Pending") {
    NavigationStack {
        InvoiceDetailView(
            invoice: .preview,
            onEdit: { _ in },
            onDelete: { _ in },
            onRecordPayment: { _ in }
        )
    }
    .frame(width: 800, height: 700)
}

#Preview("Invoice Detail View - Paid") {
    NavigationStack {
        InvoiceDetailView(
            invoice: .previewPaid,
            onEdit: { _ in },
            onDelete: { _ in },
            onRecordPayment: { _ in }
        )
    }
    .frame(width: 800, height: 700)
}

#Preview("Invoice Detail View - Overdue") {
    NavigationStack {
        InvoiceDetailView(
            invoice: .previewOverdue,
            onEdit: { _ in },
            onDelete: { _ in },
            onRecordPayment: { _ in }
        )
    }
    .frame(width: 800, height: 700)
}
