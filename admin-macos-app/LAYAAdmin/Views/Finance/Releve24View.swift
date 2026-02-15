//
//  Releve24View.swift
//  LAYAAdmin
//
//  Quebec RL-24 tax slip management view with list, filter, and export capabilities.
//  Displays RL-24 slips for Quebec childcare expense deductions.
//
//  Critical Business Rule: RL-24 amounts must reflect PAID amounts at filing time,
//  NOT invoiced amounts. If additional payments are received after initial RL-24 filing,
//  an amended RL-24 (type A) must be issued.
//

import SwiftUI

// MARK: - Releve24 View

/// Main view for managing Quebec RL-24 tax slips.
///
/// Features:
/// - List view with search and filtering by tax year/status
/// - Generate new RL-24 slips for children
/// - Export individual or batch PDFs
/// - Multi-select for bulk operations
/// - Quebec RL-24 box breakdown display
struct Releve24View: View {

    // MARK: - Properties

    /// The RL-24 view model
    @StateObject private var viewModel = Releve24ViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Whether to show the generate RL-24 sheet
    @State private var showGenerateSheet = false

    /// Whether to show the batch export dialog
    @State private var showBatchExportDialog = false

    /// Selected export format for batch export
    @State private var selectedExportFormat: Releve24ExportFormat = .combinedPdf

    /// Whether to mark as sent on export
    @State private var markAsSentOnExport = false

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if viewModel.isEmpty {
                emptyStateView
            } else {
                releve24ListContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "RL-24 Tax Slips"))
        .searchable(
            text: $viewModel.searchText,
            placement: .toolbar,
            prompt: String(localized: "Search by child, family, or reference number")
        )
        .toolbar {
            releve24Toolbar
        }
        .task {
            await viewModel.loadReleve24s(reset: true)
        }
        .alert(
            String(localized: "Error"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadReleve24s(reset: true)
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
            String(localized: "Batch Export RL-24"),
            isPresented: $showBatchExportDialog,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Export as Combined PDF")) {
                Task {
                    await viewModel.batchExport(format: .combinedPdf, markAsSent: markAsSentOnExport)
                }
            }
            Button(String(localized: "Export as Individual PDFs")) {
                Task {
                    await viewModel.batchExport(format: .pdf, markAsSent: markAsSentOnExport)
                }
            }
            Button(String(localized: "Export as XML (Electronic Filing)")) {
                Task {
                    await viewModel.batchExport(format: .xml, markAsSent: markAsSentOnExport)
                }
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        } message: {
            if viewModel.selectedCount > 0 {
                Text(String(localized: "Export \(viewModel.selectedCount) selected RL-24(s) for tax year \(String(viewModel.taxYear))"))
            } else {
                Text(String(localized: "Export all RL-24 slips for tax year \(String(viewModel.taxYear))"))
            }
        }
    }

    // MARK: - Releve24 List Content

    private var releve24ListContent: some View {
        VStack(spacing: 0) {
            // Stats header
            statsHeader

            Divider()

            // Filter chips (when filters are active)
            if viewModel.hasActiveFilters {
                activeFiltersBar
            }

            // RL-24 list
            List(selection: Binding(
                get: { viewModel.selectedReleve24?.id },
                set: { newId in
                    viewModel.selectedReleve24 = newId.flatMap { viewModel.getReleve24(byId: $0) }
                }
            )) {
                ForEach(viewModel.filteredReleve24s) { releve24 in
                    Releve24RowView(
                        releve24: releve24,
                        isSelected: viewModel.selectedReleve24Ids.contains(releve24.id),
                        onToggleSelect: {
                            viewModel.toggleSelection(releve24Id: releve24.id)
                        }
                    )
                    .contextMenu {
                        releve24ContextMenu(for: releve24)
                    }
                    .onAppear {
                        // Load more when reaching the end
                        if releve24.id == viewModel.filteredReleve24s.last?.id && viewModel.hasMoreData {
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
            // Tax year indicator
            HStack(spacing: 4) {
                Text(String(localized: "Tax Year:"))
                    .font(.subheadline)
                    .foregroundColor(.secondary)

                Text(String(viewModel.taxYear))
                    .font(.subheadline)
                    .fontWeight(.semibold)
            }

            StatBadge(
                title: String(localized: "Total"),
                value: "\(viewModel.totalCount)",
                color: .blue
            )

            StatBadge(
                title: String(localized: "Draft"),
                value: "\(viewModel.draftCount)",
                color: .gray
            )

            StatBadge(
                title: String(localized: "Generated"),
                value: "\(viewModel.generatedCount)",
                color: .blue
            )

            StatBadge(
                title: String(localized: "Sent"),
                value: "\(viewModel.sentCount)",
                color: .orange
            )

            StatBadge(
                title: String(localized: "Filed"),
                value: "\(viewModel.filedCount)",
                color: .green
            )

            // Total qualifying expenses
            if viewModel.totalQualifyingExpenses > 0 {
                HStack(spacing: 4) {
                    Text(String(localized: "Box E Total:"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Text(viewModel.formattedTotalQualifyingExpenses)
                        .font(.subheadline)
                        .fontWeight(.semibold)
                        .foregroundColor(.green)
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
    private func releve24ContextMenu(for releve24: Releve24) -> some View {
        Button(action: {
            viewModel.selectedReleve24 = releve24
        }) {
            Label(String(localized: "View Details"), systemImage: "info.circle")
        }

        Divider()

        if releve24.status == .generated || releve24.status == .sent || releve24.status == .filed {
            if let pdfUrl = viewModel.getReleve24PdfUrl(releve24Id: releve24.id) {
                Button(action: {
                    NSWorkspace.shared.open(pdfUrl)
                }) {
                    Label(String(localized: "Download PDF"), systemImage: "arrow.down.doc")
                }
            }
        }

        if releve24.status == .draft || releve24.status == .generated {
            Button(action: {
                Task {
                    await viewModel.exportPDF(releve24Id: releve24.id, markAsSent: false)
                }
            }) {
                Label(String(localized: "Export to PDF"), systemImage: "doc.badge.arrow.up")
            }
        }

        Divider()

        Button(action: {
            viewModel.toggleSelection(releve24Id: releve24.id)
        }) {
            if viewModel.selectedReleve24Ids.contains(releve24.id) {
                Label(String(localized: "Deselect"), systemImage: "checkmark.circle.fill")
            } else {
                Label(String(localized: "Select"), systemImage: "circle")
            }
        }
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading RL-24 slips..."))
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

                    Text(String(localized: "No RL-24 slips match \"\(viewModel.searchText)\""))
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

                    Text(String(localized: "No RL-24 slips match the selected filters"))
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
                    Text(String(localized: "No RL-24 Slips"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "No RL-24 slips have been generated for tax year \(String(viewModel.taxYear))"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        showGenerateSheet = true
                    }) {
                        Label(String(localized: "Generate RL-24"), systemImage: "plus")
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
    private var releve24Toolbar: some ToolbarContent {
        // Tax year picker
        ToolbarItem(placement: .primaryAction) {
            Picker(
                String(localized: "Tax Year"),
                selection: $viewModel.taxYear
            ) {
                ForEach(viewModel.availableTaxYears, id: \.self) { year in
                    Text(String(year)).tag(year)
                }
            }
            .pickerStyle(.menu)
            .help(String(localized: "Select tax year"))
        }

        // Status filter menu
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

                ForEach(Releve24Status.allCases, id: \.self) { status in
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
            .help(String(localized: "Filter by status"))
        }

        // Sort menu
        ToolbarItem(placement: .primaryAction) {
            Menu {
                ForEach(Releve24SortOrder.allCases) { order in
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
            .help(String(localized: "Sort RL-24 slips"))
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
            .help(viewModel.allSelected ? String(localized: "Deselect all RL-24s") : String(localized: "Select all RL-24s"))
        }

        // Batch export button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                showBatchExportDialog = true
            }) {
                Label(String(localized: "Batch Export"), systemImage: "square.and.arrow.up.on.square")
            }
            .disabled(viewModel.isEmpty || viewModel.isExporting)
            .help(String(localized: "Export multiple RL-24 slips"))
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
            .help(String(localized: "Refresh RL-24 list (Cmd+R)"))
        }

        // Generate RL-24 button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                showGenerateSheet = true
            }) {
                Label(String(localized: "Generate RL-24"), systemImage: "plus")
            }
            .keyboardShortcut("n", modifiers: [.command])
            .help(String(localized: "Generate new RL-24 slip (Cmd+N)"))
        }
    }
}

// MARK: - Releve24 Row View

/// A row view for displaying an RL-24 slip in a list.
struct Releve24RowView: View {

    let releve24: Releve24
    let isSelected: Bool
    let onToggleSelect: () -> Void

    var body: some View {
        HStack(spacing: 12) {
            // Selection checkbox
            Button(action: onToggleSelect) {
                Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                    .foregroundColor(isSelected ? .accentColor : .secondary)
                    .font(.title3)
            }
            .buttonStyle(.plain)

            // Main content
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(releve24.childName)
                        .font(.headline)

                    if releve24.slipType != .original {
                        Text("(\(releve24.slipTypeDisplayName))")
                            .font(.caption)
                            .foregroundColor(.purple)
                    }

                    Spacer()

                    StatusBadge(status: releve24.status)
                }

                HStack {
                    Text(releve24.familyName)
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    if let refNumber = releve24.referenceNumber {
                        Text("•")
                            .foregroundColor(.secondary)
                        Text(refNumber)
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                    }
                }
            }

            Spacer()

            // Box values
            VStack(alignment: .trailing, spacing: 4) {
                HStack(spacing: 16) {
                    BoxValueView(label: "B", value: "\(releve24.daysOfCare)")
                    BoxValueView(label: "E", value: releve24.formattedQualifyingExpenses)
                }
            }
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 4)
    }
}

// MARK: - Box Value View

/// Displays an RL-24 box label and value.
struct BoxValueView: View {

    let label: String
    let value: String

    var body: some View {
        VStack(alignment: .trailing, spacing: 2) {
            Text(String(localized: "Box \(label)"))
                .font(.caption2)
                .foregroundColor(.secondary)

            Text(value)
                .font(.subheadline)
                .fontWeight(.medium)
        }
    }
}

// MARK: - Status Badge

/// Displays the RL-24 status as a colored badge.
struct StatusBadge: View {

    let status: Releve24Status

    var body: some View {
        Text(status.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(statusColor.opacity(0.15))
            .foregroundColor(statusColor)
            .cornerRadius(4)
    }

    private var statusColor: Color {
        switch status {
        case .draft:
            return .gray
        case .generated:
            return .blue
        case .sent:
            return .orange
        case .filed:
            return .green
        case .amended:
            return .purple
        }
    }
}

// MARK: - Preview

#Preview("Releve24 View") {
    NavigationStack {
        Releve24View()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 900, height: 600)
}

#Preview("Releve24 View - Loading") {
    NavigationStack {
        Releve24View()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 900, height: 600)
}

#Preview("Releve24 View - Empty") {
    NavigationStack {
        Releve24View()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 900, height: 600)
}
