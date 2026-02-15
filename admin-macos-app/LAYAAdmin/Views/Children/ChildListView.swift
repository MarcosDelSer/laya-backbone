//
//  ChildListView.swift
//  LAYAAdmin
//
//  Child list view with search, filter, and navigation capabilities.
//  Displays enrolled children in a list format with status filtering,
//  sorting options, and navigation to detail view.
//

import SwiftUI

// MARK: - Child List View

/// List view for managing enrolled children.
///
/// Features:
/// - Searchable list with debounced search
/// - Filter by enrollment status
/// - Sort by name, age, enrollment date, or classroom
/// - Multi-select support for bulk operations
/// - Pull-to-refresh support
/// - Pagination with infinite scroll
/// - Navigation to child detail view
/// - Add new child action
struct ChildListView: View {

    // MARK: - Properties

    /// The child list view model
    @StateObject private var viewModel = ChildListViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Whether to show the add child sheet
    @State private var showAddChild = false

    /// Whether to show delete confirmation dialog
    @State private var showDeleteConfirmation = false

    /// Child ID pending deletion
    @State private var childPendingDeletion: String?

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if viewModel.isEmpty {
                emptyStateView
            } else {
                childListContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Children"))
        .searchable(
            text: $viewModel.searchText,
            placement: .toolbar,
            prompt: String(localized: "Search by name, guardian, or classroom")
        )
        .toolbar {
            childListToolbar
        }
        .task {
            await viewModel.loadChildren(reset: true)
        }
        .alert(
            String(localized: "Error Loading Children"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadChildren(reset: true)
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
            String(localized: "Delete Child"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                if let childId = childPendingDeletion {
                    Task {
                        await viewModel.deleteChild(childId: childId)
                        childPendingDeletion = nil
                    }
                }
            }
            Button(String(localized: "Cancel"), role: .cancel) {
                childPendingDeletion = nil
            }
        } message: {
            Text(String(localized: "Are you sure you want to delete this child? This action cannot be undone."))
        }
    }

    // MARK: - Child List Content

    private var childListContent: some View {
        VStack(spacing: 0) {
            // Stats header
            statsHeader

            Divider()

            // Filter chips (when filters are active)
            if viewModel.hasActiveFilters {
                activeFiltersBar
            }

            // Child list
            List(selection: Binding(
                get: { viewModel.selectedChild?.id },
                set: { newId in
                    viewModel.selectedChild = newId.flatMap { viewModel.getChild(byId: $0) }
                }
            )) {
                ForEach(viewModel.filteredChildren) { child in
                    NavigationLink(value: child.id) {
                        ChildRowView(
                            child: child,
                            isSelected: viewModel.selectedChildIds.contains(child.id),
                            onToggleSelect: {
                                viewModel.toggleSelection(childId: child.id)
                            }
                        )
                    }
                    .contextMenu {
                        childContextMenu(for: child)
                    }
                    .onAppear {
                        // Load more when reaching the end
                        if child.id == viewModel.filteredChildren.last?.id && viewModel.hasMoreData {
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
                title: String(localized: "Active"),
                value: "\(viewModel.activeCount)",
                color: .green
            )

            StatBadge(
                title: String(localized: "Pending"),
                value: "\(viewModel.pendingCount)",
                color: .orange
            )

            StatBadge(
                title: String(localized: "Waitlist"),
                value: "\(viewModel.waitlistCount)",
                color: .purple
            )

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

            if let classroomId = viewModel.classroomFilter {
                FilterChip(
                    label: String(localized: "Classroom: \(classroomId)"),
                    onRemove: {
                        viewModel.classroomFilter = nil
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
    private func childContextMenu(for child: Child) -> some View {
        Button(action: {
            viewModel.selectedChild = child
        }) {
            Label(String(localized: "View Details"), systemImage: "info.circle")
        }

        Divider()

        Button(action: {
            viewModel.toggleSelection(childId: child.id)
        }) {
            if viewModel.selectedChildIds.contains(child.id) {
                Label(String(localized: "Deselect"), systemImage: "checkmark.circle.fill")
            } else {
                Label(String(localized: "Select"), systemImage: "circle")
            }
        }

        Divider()

        Button(role: .destructive, action: {
            childPendingDeletion = child.id
            showDeleteConfirmation = true
        }) {
            Label(String(localized: "Delete"), systemImage: "trash")
        }
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading children..."))
                .font(.headline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Empty State View

    private var emptyStateView: some View {
        VStack(spacing: 20) {
            Image(systemName: "person.2.slash")
                .font(.system(size: 64))
                .foregroundColor(.secondary)

            VStack(spacing: 8) {
                if viewModel.isSearching {
                    Text(String(localized: "No Results"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "No children match \"\(viewModel.searchText)\""))
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

                    Text(String(localized: "No children match the selected filters"))
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
                    Text(String(localized: "No Children"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "Get started by adding your first child"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        showAddChild = true
                    }) {
                        Label(String(localized: "Add Child"), systemImage: "plus")
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
    private var childListToolbar: some ToolbarContent {
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

                ForEach(EnrollmentStatus.allCases, id: \.self) { status in
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
            .help(String(localized: "Filter by enrollment status"))
        }

        // Sort menu
        ToolbarItem(placement: .primaryAction) {
            Menu {
                ForEach(ChildSortOrder.allCases) { order in
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
            .help(String(localized: "Sort children"))
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
            .help(viewModel.allSelected ? String(localized: "Deselect all children") : String(localized: "Select all children"))
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
            .help(String(localized: "Refresh child list (Cmd+R)"))
        }

        // Add child button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                showAddChild = true
            }) {
                Label(String(localized: "Add Child"), systemImage: "plus")
            }
            .keyboardShortcut("n", modifiers: [.command])
            .help(String(localized: "Add new child (Cmd+N)"))
        }
    }
}

// MARK: - Stat Badge

/// A compact badge showing a statistic with a colored indicator.
struct StatBadge: View {

    let title: String
    let value: String
    let color: Color

    var body: some View {
        HStack(spacing: 6) {
            Circle()
                .fill(color)
                .frame(width: 8, height: 8)

            Text(title)
                .font(.subheadline)
                .foregroundColor(.secondary)

            Text(value)
                .font(.subheadline)
                .fontWeight(.semibold)
        }
    }
}

// MARK: - Filter Chip

/// A removable chip displaying an active filter.
struct FilterChip: View {

    let label: String
    let onRemove: () -> Void

    var body: some View {
        HStack(spacing: 4) {
            Text(label)
                .font(.caption)

            Button(action: onRemove) {
                Image(systemName: "xmark.circle.fill")
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
            .buttonStyle(.plain)
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(Color.accentColor.opacity(0.1))
        .cornerRadius(4)
    }
}

// MARK: - Preview

#Preview("Child List View") {
    NavigationStack {
        ChildListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}

#Preview("Child List View - Loading") {
    NavigationStack {
        ChildListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}

#Preview("Child List View - Empty") {
    NavigationStack {
        ChildListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}

#Preview("Stat Badge") {
    HStack(spacing: 20) {
        StatBadge(title: "Active", value: "45", color: .green)
        StatBadge(title: "Pending", value: "12", color: .orange)
        StatBadge(title: "Waitlist", value: "8", color: .purple)
    }
    .padding()
}

#Preview("Filter Chip") {
    HStack {
        FilterChip(label: "Active", onRemove: {})
        FilterChip(label: "Sunflowers", onRemove: {})
    }
    .padding()
}
