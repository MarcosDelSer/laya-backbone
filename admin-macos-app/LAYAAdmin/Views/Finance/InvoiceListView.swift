//
//  InvoiceListView.swift
//  LAYAAdmin
//
//  Invoice list view with search, filter, and navigation capabilities.
//  Displays invoices in a list format with status filtering,
//  sorting options, and navigation to detail view.
//

import SwiftUI

// MARK: - Invoice List View

/// List view for managing invoices.
///
/// Features:
/// - Searchable list with debounced search
/// - Filter by payment status (pending, paid, overdue)
/// - Sort by date, due date, amount, or family name
/// - Multi-select support for bulk operations
/// - Pull-to-refresh support
/// - Pagination with infinite scroll
/// - Navigation to invoice detail view
/// - Add new invoice action
struct InvoiceListView: View {

    // MARK: - Properties

    /// The invoice list view model
    @StateObject private var viewModel = InvoiceListViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Whether to show the add invoice sheet
    @State private var showAddInvoice = false

    /// Whether to show delete confirmation dialog
    @State private var showDeleteConfirmation = false

    /// Invoice ID pending deletion
    @State private var invoicePendingDeletion: String?

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if viewModel.isEmpty {
                emptyStateView
            } else {
                invoiceListContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Invoices"))
        .searchable(
            text: $viewModel.searchText,
            placement: .toolbar,
            prompt: String(localized: "Search by invoice number, family, or child")
        )
        .toolbar {
            invoiceListToolbar
        }
        .task {
            await viewModel.loadInvoices(reset: true)
        }
        .alert(
            String(localized: "Error Loading Invoices"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadInvoices(reset: true)
                }
            }
            Button(String(localized: "Dismiss"), role: .cancel) {
                viewModel.clearError()
            }
        } message: { error in
            Text(error.localizedDescription)
        }
        .alert(
            String(localized: "Success"),
            isPresented: $viewModel.showSuccess,
            presenting: viewModel.successMessage
        ) { _ in
            Button(String(localized: "OK")) {
                viewModel.clearSuccess()
            }
        } message: { message in
            Text(message)
        }
        .confirmationDialog(
            String(localized: "Delete Invoice"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                if let invoiceId = invoicePendingDeletion {
                    Task {
                        await viewModel.deleteInvoice(invoiceId: invoiceId)
                        invoicePendingDeletion = nil
                    }
                }
            }
            Button(String(localized: "Cancel"), role: .cancel) {
                invoicePendingDeletion = nil
            }
        } message: {
            Text(String(localized: "Are you sure you want to delete this invoice? This action cannot be undone."))
        }
    }

    // MARK: - Invoice List Content

    private var invoiceListContent: some View {
        VStack(spacing: 0) {
            // Stats header
            statsHeader

            Divider()

            // Filter chips (when filters are active)
            if viewModel.hasActiveFilters {
                activeFiltersBar
            }

            // Invoice list
            List(selection: Binding(
                get: { viewModel.selectedInvoice?.id },
                set: { newId in
                    viewModel.selectedInvoice = newId.flatMap { viewModel.getInvoice(byId: $0) }
                }
            )) {
                ForEach(viewModel.filteredInvoices) { invoice in
                    NavigationLink(value: invoice.id) {
                        InvoiceRowView(
                            invoice: invoice,
                            isSelected: viewModel.selectedInvoiceIds.contains(invoice.id),
                            onToggleSelect: {
                                viewModel.toggleSelection(invoiceId: invoice.id)
                            }
                        )
                    }
                    .contextMenu {
                        invoiceContextMenu(for: invoice)
                    }
                    .onAppear {
                        // Load more when reaching the end
                        if invoice.id == viewModel.filteredInvoices.last?.id && viewModel.hasMoreData {
                            Task {
                                await viewModel.loadMore()
                            }
                        }
                    }
                }
            }
            .listStyle(.inset(alternatesRowBackgrounds: true))
            .refreshable {
                await viewModel.refresh()
            }

            // Loading indicator for pagination
            if viewModel.isLoading && viewModel.hasLoaded {
                HStack {
                    ProgressView()
                        .controlSize(.small)
                    Text(String(localized: "Loading more..."))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
                .padding(.vertical, 8)
            }
        }
    }

    // MARK: - Stats Header

    private var statsHeader: some View {
        HStack(spacing: 24) {
            StatBadge(
                title: String(localized: "Total"),
                value: "\(viewModel.totalCount)",
                color: .blue
            )

            StatBadge(
                title: String(localized: "Pending"),
                value: "\(viewModel.pendingCount)",
                color: .orange
            )

            StatBadge(
                title: String(localized: "Paid"),
                value: "\(viewModel.paidCount)",
                color: .green
            )

            StatBadge(
                title: String(localized: "Overdue"),
                value: "\(viewModel.overdueCount)",
                color: .red
            )

            // Outstanding balance
            if viewModel.totalOutstanding > 0 {
                HStack(spacing: 4) {
                    Text(String(localized: "Outstanding:"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(viewModel.formattedTotalOutstanding)
                        .font(.subheadline)
                        .fontWeight(.semibold)
                        .foregroundColor(.orange)
                }
            }

            Spacer()

            if viewModel.selectedCount > 0 {
                Text(String(localized: "\(viewModel.selectedCount) selected"))
                    .font(.subheadline)
                    .foregroundColor(.secondary)

                Button(action: {
                    viewModel.deselectAll()
                }) {
                    Text(String(localized: "Clear"))
                        .font(.subheadline)
                }
                .buttonStyle(.link)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(Color(NSColor.controlBackgroundColor))
    }

    // MARK: - Active Filters Bar

    private var activeFiltersBar: some View {
        HStack(spacing: 8) {
            Text(String(localized: "Filters:"))
                .font(.subheadline)
                .foregroundColor(.secondary)

            if let status = viewModel.statusFilter {
                FilterChip(
                    label: status.displayName,
                    onRemove: {
                        viewModel.statusFilter = nil
                    }
                )
            }

            if let familyId = viewModel.familyFilter {
                FilterChip(
                    label: String(localized: "Family: \(familyId)"),
                    onRemove: {
                        viewModel.familyFilter = nil
                    }
                )
            }

            if viewModel.startDateFilter != nil || viewModel.endDateFilter != nil {
                FilterChip(
                    label: String(localized: "Date Range"),
                    onRemove: {
                        viewModel.startDateFilter = nil
                        viewModel.endDateFilter = nil
                    }
                )
            }

            Spacer()

            Button(action: {
                viewModel.clearFilters()
            }) {
                Text(String(localized: "Clear All"))
                    .font(.subheadline)
            }
            .buttonStyle(.link)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 8)
        .background(Color.accentColor.opacity(0.05))
    }

    // MARK: - Context Menu

    @ViewBuilder
    private func invoiceContextMenu(for invoice: Invoice) -> some View {
        Button(action: {
            viewModel.selectedInvoice = invoice
        }) {
            Label(String(localized: "View Details"), systemImage: "info.circle")
        }

        if let pdfUrl = viewModel.getInvoicePdfUrl(invoiceId: invoice.id) {
            Button(action: {
                NSWorkspace.shared.open(pdfUrl)
            }) {
                Label(String(localized: "Download PDF"), systemImage: "arrow.down.doc")
            }
        }

        Divider()

        Button(action: {
            viewModel.toggleSelection(invoiceId: invoice.id)
        }) {
            if viewModel.selectedInvoiceIds.contains(invoice.id) {
                Label(String(localized: "Deselect"), systemImage: "checkmark.circle.fill")
            } else {
                Label(String(localized: "Select"), systemImage: "circle")
            }
        }

        Divider()

        if invoice.status != .paid && invoice.status != .cancelled {
            Button(role: .destructive, action: {
                invoicePendingDeletion = invoice.id
                showDeleteConfirmation = true
            }) {
                Label(String(localized: "Delete"), systemImage: "trash")
            }
        }
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading invoices..."))
                .font(.headline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Empty State View

    private var emptyStateView: some View {
        VStack(spacing: 20) {
            Image(systemName: "doc.text.magnifyingglass")
                .font(.system(size: 64))
                .foregroundColor(.secondary)

            VStack(spacing: 8) {
                if viewModel.isSearching {
                    Text(String(localized: "No Results"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "No invoices match \"\(viewModel.searchText)\""))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        viewModel.searchText = ""
                    }) {
                        Text(String(localized: "Clear Search"))
                    }
                    .buttonStyle(.bordered)
                    .padding(.top, 8)
                } else if viewModel.hasActiveFilters {
                    Text(String(localized: "No Results"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "No invoices match the selected filters"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        viewModel.clearFilters()
                    }) {
                        Text(String(localized: "Clear Filters"))
                    }
                    .buttonStyle(.bordered)
                    .padding(.top, 8)
                } else {
                    Text(String(localized: "No Invoices"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "Get started by creating your first invoice"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        showAddInvoice = true
                    }) {
                        Label(String(localized: "Create Invoice"), systemImage: "plus")
                    }
                    .buttonStyle(.borderedProminent)
                    .padding(.top, 8)
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var invoiceListToolbar: some ToolbarContent {
        // Status filter picker
        ToolbarItem(placement: .primaryAction) {
            Picker(
                String(localized: "Status"),
                selection: Binding(
                    get: { viewModel.statusFilter ?? .pending },
                    set: { newValue in
                        // Use a special "all" option
                        viewModel.statusFilter = newValue
                    }
                )
            ) {
                Text(String(localized: "All")).tag(InvoiceStatus?.none)
                Divider()
                Text(String(localized: "Pending")).tag(InvoiceStatus?.some(.pending))
                Text(String(localized: "Paid")).tag(InvoiceStatus?.some(.paid))
                Text(String(localized: "Overdue")).tag(InvoiceStatus?.some(.overdue))
                Text(String(localized: "Draft")).tag(InvoiceStatus?.some(.draft))
                Text(String(localized: "Cancelled")).tag(InvoiceStatus?.some(.cancelled))
            }
            .pickerStyle(.menu)
            .help(String(localized: "Filter by payment status"))
        }

        // Status filter menu (alternate implementation)
        ToolbarItem(placement: .primaryAction) {
            Menu {
                Button(action: {
                    viewModel.statusFilter = nil
                }) {
                    HStack {
                        Text(String(localized: "All Statuses"))
                        if viewModel.statusFilter == nil {
                            Image(systemName: "checkmark")
                        }
                    }
                }

                Divider()

                ForEach(InvoiceStatus.allCases, id: \.self) { status in
                    Button(action: {
                        viewModel.statusFilter = status
                    }) {
                        HStack {
                            Text(status.displayName)
                            if viewModel.statusFilter == status {
                                Image(systemName: "checkmark")
                            }
                        }
                    }
                }
            } label: {
                Label(
                    viewModel.statusFilter?.displayName ?? String(localized: "Filter"),
                    systemImage: viewModel.hasActiveFilters ? "line.3.horizontal.decrease.circle.fill" : "line.3.horizontal.decrease.circle"
                )
            }
            .help(String(localized: "Filter by payment status"))
        }

        // Sort menu
        ToolbarItem(placement: .primaryAction) {
            Menu {
                ForEach(InvoiceSortOrder.allCases) { order in
                    Button(action: {
                        viewModel.sortOrder = order
                    }) {
                        HStack {
                            Text(order.displayName)
                            if viewModel.sortOrder == order {
                                Image(systemName: "checkmark")
                            }
                        }
                    }
                }
            } label: {
                Label(String(localized: "Sort"), systemImage: "arrow.up.arrow.down")
            }
            .help(String(localized: "Sort invoices"))
        }

        // Select all toggle
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                viewModel.toggleSelectAll()
            }) {
                Label(
                    viewModel.allSelected ? String(localized: "Deselect All") : String(localized: "Select All"),
                    systemImage: viewModel.allSelected ? "checkmark.circle.fill" : "checkmark.circle"
                )
            }
            .help(viewModel.allSelected ? String(localized: "Deselect all invoices") : String(localized: "Select all invoices"))
        }

        // Refresh button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                Task {
                    await viewModel.refresh()
                }
            }) {
                Label(String(localized: "Refresh"), systemImage: "arrow.clockwise")
            }
            .keyboardShortcut("r", modifiers: [.command])
            .disabled(viewModel.isLoading)
            .help(String(localized: "Refresh invoice list (Cmd+R)"))
        }

        // Add invoice button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                showAddInvoice = true
            }) {
                Label(String(localized: "Create Invoice"), systemImage: "plus")
            }
            .keyboardShortcut("n", modifiers: [.command])
            .help(String(localized: "Create new invoice (Cmd+N)"))
        }
    }
}

// MARK: - Preview

#Preview("Invoice List View") {
    NavigationStack {
        InvoiceListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 800, height: 600)
}

#Preview("Invoice List View - Loading") {
    NavigationStack {
        InvoiceListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 800, height: 600)
}

#Preview("Invoice List View - Empty") {
    NavigationStack {
        InvoiceListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 800, height: 600)
}
