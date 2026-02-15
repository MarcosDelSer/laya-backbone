//
//  StaffListView.swift
//  LAYAAdmin
//
//  Staff list view with search, filter, and navigation capabilities.
//  Displays staff members in a list format with status and role filtering,
//  sorting options, and navigation to detail view.
//

import SwiftUI

// MARK: - Staff List View

/// List view for managing staff members.
///
/// Features:
/// - Searchable list with debounced search
/// - Filter by employment status and role
/// - Sort by name, role, hire date, or classroom
/// - Multi-select support for bulk operations
/// - Pull-to-refresh support
/// - Pagination with infinite scroll
/// - Navigation to staff detail view
/// - Add new staff action
struct StaffListView: View {

    // MARK: - Properties

    /// The staff list view model
    @StateObject private var viewModel = StaffListViewModel()

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Whether to show the add staff sheet
    @State private var showAddStaff = false

    /// Whether to show delete confirmation dialog
    @State private var showDeleteConfirmation = false

    /// Staff ID pending deletion
    @State private var staffPendingDeletion: String?

    // MARK: - Body

    var body: some View {
        Group {
            if viewModel.isLoading && !viewModel.hasLoaded {
                loadingView
            } else if viewModel.isEmpty {
                emptyStateView
            } else {
                staffListContent
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Staff"))
        .searchable(
            text: $viewModel.searchText,
            placement: .toolbar,
            prompt: String(localized: "Search by name, role, or classroom")
        )
        .toolbar {
            staffListToolbar
        }
        .task {
            await viewModel.loadStaff(reset: true)
        }
        .alert(
            String(localized: "Error Loading Staff"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await viewModel.loadStaff(reset: true)
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
            String(localized: "Delete Staff Member"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                if let staffId = staffPendingDeletion {
                    Task {
                        await viewModel.deleteStaffMember(staffId: staffId)
                        staffPendingDeletion = nil
                    }
                }
            }
            Button(String(localized: "Cancel"), role: .cancel) {
                staffPendingDeletion = nil
            }
        } message: {
            Text(String(localized: "Are you sure you want to delete this staff member? This action cannot be undone."))
        }
    }

    // MARK: - Staff List Content

    private var staffListContent: some View {
        VStack(spacing: 0) {
            // Stats header
            statsHeader

            Divider()

            // Filter chips (when filters are active)
            if viewModel.hasActiveFilters {
                activeFiltersBar
            }

            // Staff list
            List(selection: Binding(
                get: { viewModel.selectedStaff?.id },
                set: { newId in
                    viewModel.selectedStaff = newId.flatMap { viewModel.getStaffMember(byId: $0) }
                }
            )) {
                ForEach(viewModel.filteredStaff) { member in
                    NavigationLink(value: member.id) {
                        StaffRowView(
                            staff: member,
                            isSelected: viewModel.selectedStaffIds.contains(member.id),
                            onToggleSelect: {
                                viewModel.toggleSelection(staffId: member.id)
                            }
                        )
                    }
                    .contextMenu {
                        staffContextMenu(for: member)
                    }
                    .onAppear {
                        // Load more when reaching the end
                        if member.id == viewModel.filteredStaff.last?.id && viewModel.hasMoreData {
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
                title: String(localized: "On Leave"),
                value: "\(viewModel.onLeaveCount)",
                color: .orange
            )

            StatBadge(
                title: String(localized: "Childcare"),
                value: "\(viewModel.childcareStaffCount)",
                color: .purple
            )

            if viewModel.certificationConcernsCount > 0 {
                StatBadge(
                    title: String(localized: "Cert Issues"),
                    value: "\(viewModel.certificationConcernsCount)",
                    color: .red
                )
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

            if let role = viewModel.roleFilter {
                FilterChip(
                    label: role.displayName,
                    onRemove: {
                        viewModel.roleFilter = nil
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
    private func staffContextMenu(for member: Staff) -> some View {
        Button(action: {
            viewModel.selectedStaff = member
        }) {
            Label(String(localized: "View Details"), systemImage: "info.circle")
        }

        Divider()

        Button(action: {
            viewModel.toggleSelection(staffId: member.id)
        }) {
            if viewModel.selectedStaffIds.contains(member.id) {
                Label(String(localized: "Deselect"), systemImage: "checkmark.circle.fill")
            } else {
                Label(String(localized: "Select"), systemImage: "circle")
            }
        }

        Divider()

        Button(role: .destructive, action: {
            staffPendingDeletion = member.id
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

            Text(String(localized: "Loading staff..."))
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

                    Text(String(localized: "No staff members match \"\(viewModel.searchText)\""))
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

                    Text(String(localized: "No staff members match the selected filters"))
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
                    Text(String(localized: "No Staff Members"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Text(String(localized: "Get started by adding your first staff member"))
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Button(action: {
                        showAddStaff = true
                    }) {
                        Label(String(localized: "Add Staff Member"), systemImage: "plus")
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
    private var staffListToolbar: some ToolbarContent {
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

                ForEach(StaffStatus.allCases, id: \.self) { status in
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
                    viewModel.statusFilter?.displayName ?? String(localized: "Status"),
                    systemImage: viewModel.statusFilter != nil ? "line.3.horizontal.decrease.circle.fill" : "line.3.horizontal.decrease.circle"
                )
            }
            .help(String(localized: "Filter by employment status"))
        }

        // Role filter menu
        ToolbarItem(placement: .primaryAction) {
            Menu {
                Button(action: {
                    viewModel.roleFilter = nil
                }) {
                    HStack {
                        Text(String(localized: "All Roles"))
                        if viewModel.roleFilter == nil {
                            Image(systemName: "checkmark")
                        }
                    }
                }

                Divider()

                ForEach(StaffRole.allCases, id: \.self) { role in
                    Button(action: {
                        viewModel.roleFilter = role
                    }) {
                        HStack {
                            Text(role.displayName)
                            if viewModel.roleFilter == role {
                                Image(systemName: "checkmark")
                            }
                        }
                    }
                }
            } label: {
                Label(
                    viewModel.roleFilter?.displayName ?? String(localized: "Role"),
                    systemImage: viewModel.roleFilter != nil ? "person.badge.shield.checkmark.fill" : "person.badge.shield.checkmark"
                )
            }
            .help(String(localized: "Filter by role"))
        }

        // Sort menu
        ToolbarItem(placement: .primaryAction) {
            Menu {
                ForEach(StaffSortOrder.allCases) { order in
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
            .help(String(localized: "Sort staff"))
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
            .help(viewModel.allSelected ? String(localized: "Deselect all staff") : String(localized: "Select all staff"))
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
            .help(String(localized: "Refresh staff list (Cmd+R)"))
        }

        // Add staff button
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                showAddStaff = true
            }) {
                Label(String(localized: "Add Staff"), systemImage: "plus")
            }
            .help(String(localized: "Add new staff member"))
        }
    }
}

// MARK: - Preview

#Preview("Staff List View") {
    NavigationStack {
        StaffListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}

#Preview("Staff List View - Loading") {
    NavigationStack {
        StaffListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}

#Preview("Staff List View - Empty") {
    NavigationStack {
        StaffListView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 700, height: 600)
}
