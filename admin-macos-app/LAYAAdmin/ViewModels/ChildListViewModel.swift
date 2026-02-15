//
//  ChildListViewModel.swift
//  LAYAAdmin
//
//  ViewModel for child list management in the LAYA Admin application.
//  Handles loading, searching, filtering, and CRUD operations for children.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Child List ViewModel

/// ViewModel for managing the child list view state.
///
/// This ViewModel acts as a bridge between the UI layer and the Gibbon CMS API,
/// providing observable state for the child list view.
///
/// Features:
/// - Load children with pagination support
/// - Search by name with debounce
/// - Filter by enrollment status
/// - Sort by various fields
/// - CRUD operations (create, update, delete)
/// - Selection tracking for bulk operations
@MainActor
final class ChildListViewModel: ObservableObject {

    // MARK: - Published Properties

    /// List of children to display
    @Published private(set) var children: [Child] = []

    /// Whether a load operation is in progress
    @Published private(set) var isLoading = false

    /// Whether more data is available for pagination
    @Published private(set) var hasMoreData = false

    /// Whether initial data has been loaded
    @Published private(set) var hasLoaded = false

    /// Current error, if any
    @Published private(set) var error: Error?

    /// Whether the error alert should be shown
    @Published var showError = false

    // MARK: - Search & Filter

    /// Current search query
    @Published var searchText = "" {
        didSet {
            searchSubject.send(searchText)
        }
    }

    /// Current enrollment status filter
    @Published var statusFilter: EnrollmentStatus? = nil {
        didSet {
            Task {
                await loadChildren(reset: true)
            }
        }
    }

    /// Current classroom filter
    @Published var classroomFilter: String? = nil {
        didSet {
            Task {
                await loadChildren(reset: true)
            }
        }
    }

    /// Current sort order
    @Published var sortOrder: ChildSortOrder = .nameAsc {
        didSet {
            applySorting()
        }
    }

    // MARK: - Selection

    /// Currently selected child IDs (for bulk operations)
    @Published var selectedChildIds: Set<String> = []

    /// The currently focused/selected child for detail view
    @Published var selectedChild: Child?

    // MARK: - CRUD State

    /// Whether a create/update operation is in progress
    @Published private(set) var isSaving = false

    /// Whether a delete operation is in progress
    @Published private(set) var isDeleting = false

    /// Success message to display after an operation
    @Published var successMessage: String?

    /// Whether to show the success alert
    @Published var showSuccess = false

    // MARK: - Statistics

    /// Total count of children matching the current filter
    @Published private(set) var totalCount = 0

    // MARK: - Computed Properties

    /// Whether the list is empty (after loading)
    var isEmpty: Bool {
        hasLoaded && children.isEmpty
    }

    /// Whether search is active
    var isSearching: Bool {
        !searchText.trimmingCharacters(in: .whitespaces).isEmpty
    }

    /// Whether any filter is active
    var hasActiveFilters: Bool {
        statusFilter != nil || classroomFilter != nil
    }

    /// Filtered children based on local search
    var filteredChildren: [Child] {
        guard isSearching else { return children }

        let query = searchText.lowercased().trimmingCharacters(in: .whitespaces)
        return children.filter { child in
            child.fullName.lowercased().contains(query) ||
            child.primaryGuardianName.lowercased().contains(query) ||
            (child.classroomName?.lowercased().contains(query) ?? false)
        }
    }

    /// Number of active children in the current list
    var activeCount: Int {
        children.filter { $0.enrollmentStatus == .active }.count
    }

    /// Number of pending children in the current list
    var pendingCount: Int {
        children.filter { $0.enrollmentStatus == .pending }.count
    }

    /// Number of waitlist children in the current list
    var waitlistCount: Int {
        children.filter { $0.enrollmentStatus == .waitlist }.count
    }

    /// Number of selected children
    var selectedCount: Int {
        selectedChildIds.count
    }

    /// Whether all visible children are selected
    var allSelected: Bool {
        !filteredChildren.isEmpty && selectedChildIds.count == filteredChildren.count
    }

    // MARK: - Private Properties

    /// Gibbon CMS client
    private let gibbonClient: GibbonClient

    /// Combine cancellables for subscriptions
    private var cancellables = Set<AnyCancellable>()

    /// Subject for debounced search
    private let searchSubject = PassthroughSubject<String, Never>()

    /// Current pagination offset
    private var currentOffset = 0

    /// Page size for pagination
    private let pageSize = AppConstants.defaultPageSize

    // MARK: - Initialization

    /// Creates a new ChildListViewModel
    /// - Parameter gibbonClient: The Gibbon client to use (defaults to shared instance)
    init(gibbonClient: GibbonClient = .shared) {
        self.gibbonClient = gibbonClient
        setupSearchDebounce()
    }

    // MARK: - Public Methods

    /// Loads children from the API
    /// - Parameter reset: Whether to reset pagination and reload from the beginning
    func loadChildren(reset: Bool = false) async {
        if reset {
            currentOffset = 0
            children = []
        }

        // Don't load if already loading or if no more data
        guard !isLoading else { return }
        if !reset && !hasMoreData && hasLoaded { return }

        isLoading = true
        error = nil
        showError = false

        do {
            let response = try await gibbonClient.fetchChildren(
                status: statusFilter,
                classroomId: classroomFilter,
                skip: currentOffset,
                limit: pageSize
            )

            if reset {
                children = response.items
            } else {
                children.append(contentsOf: response.items)
            }

            totalCount = response.total
            hasMoreData = response.hasMore
            currentOffset += response.items.count
            hasLoaded = true

            // Apply current sorting
            applySorting()

        } catch {
            self.error = error
            self.showError = true
        }

        isLoading = false
    }

    /// Refreshes the child list (reloads from the beginning)
    func refresh() async {
        await loadChildren(reset: true)
    }

    /// Loads more children (pagination)
    func loadMore() async {
        guard hasMoreData && !isLoading else { return }
        await loadChildren(reset: false)
    }

    /// Fetches a specific child by ID
    /// - Parameter childId: The child's unique identifier
    /// - Returns: The child details, or nil if not found
    func fetchChild(childId: String) async -> Child? {
        do {
            let child = try await gibbonClient.fetchChild(childId: childId)
            return child
        } catch {
            self.error = error
            self.showError = true
            return nil
        }
    }

    /// Creates a new child
    /// - Parameter request: The child creation request
    /// - Returns: The created child, or nil if failed
    @discardableResult
    func createChild(_ request: ChildRequest) async -> Child? {
        isSaving = true
        error = nil

        do {
            let child = try await gibbonClient.createChild(request)

            // Add to the beginning of the list
            children.insert(child, at: 0)
            totalCount += 1
            applySorting()

            successMessage = String(localized: "Child added successfully")
            showSuccess = true

            isSaving = false
            return child

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Updates an existing child
    /// - Parameters:
    ///   - childId: The child's unique identifier
    ///   - request: The child update request
    /// - Returns: The updated child, or nil if failed
    @discardableResult
    func updateChild(childId: String, request: ChildRequest) async -> Child? {
        isSaving = true
        error = nil

        do {
            let updatedChild = try await gibbonClient.updateChild(childId: childId, request: request)

            // Update in the local list
            if let index = children.firstIndex(where: { $0.id == childId }) {
                children[index] = updatedChild
            }

            // Update selected child if it's the same
            if selectedChild?.id == childId {
                selectedChild = updatedChild
            }

            successMessage = String(localized: "Child updated successfully")
            showSuccess = true

            isSaving = false
            return updatedChild

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Deletes a child
    /// - Parameter childId: The child's unique identifier
    /// - Returns: Whether the deletion was successful
    @discardableResult
    func deleteChild(childId: String) async -> Bool {
        isDeleting = true
        error = nil

        do {
            try await gibbonClient.deleteChild(childId: childId)

            // Remove from local list
            children.removeAll { $0.id == childId }
            totalCount -= 1

            // Clear selection if deleted child was selected
            selectedChildIds.remove(childId)
            if selectedChild?.id == childId {
                selectedChild = nil
            }

            successMessage = String(localized: "Child deleted successfully")
            showSuccess = true

            isDeleting = false
            return true

        } catch {
            self.error = error
            self.showError = true
            isDeleting = false
            return false
        }
    }

    /// Deletes multiple children
    /// - Parameter childIds: The child IDs to delete
    /// - Returns: Number of successfully deleted children
    @discardableResult
    func deleteChildren(childIds: Set<String>) async -> Int {
        isDeleting = true
        error = nil
        var deletedCount = 0

        for childId in childIds {
            do {
                try await gibbonClient.deleteChild(childId: childId)
                children.removeAll { $0.id == childId }
                totalCount -= 1
                selectedChildIds.remove(childId)
                deletedCount += 1
            } catch {
                // Continue with other deletions even if one fails
            }
        }

        if deletedCount > 0 {
            successMessage = String(localized: "\(deletedCount) children deleted successfully")
            showSuccess = true
        }

        // Clear selected child if it was deleted
        if let selected = selectedChild, childIds.contains(selected.id) {
            selectedChild = nil
        }

        isDeleting = false
        return deletedCount
    }

    // MARK: - Selection Methods

    /// Toggles selection for a child
    /// - Parameter childId: The child's ID to toggle
    func toggleSelection(childId: String) {
        if selectedChildIds.contains(childId) {
            selectedChildIds.remove(childId)
        } else {
            selectedChildIds.insert(childId)
        }
    }

    /// Selects all visible children
    func selectAll() {
        selectedChildIds = Set(filteredChildren.map { $0.id })
    }

    /// Deselects all children
    func deselectAll() {
        selectedChildIds.removeAll()
    }

    /// Toggles select all/none
    func toggleSelectAll() {
        if allSelected {
            deselectAll()
        } else {
            selectAll()
        }
    }

    // MARK: - Filter Methods

    /// Clears all active filters
    func clearFilters() {
        statusFilter = nil
        classroomFilter = nil
        searchText = ""
    }

    /// Sets the status filter and reloads
    /// - Parameter status: The enrollment status to filter by
    func filterByStatus(_ status: EnrollmentStatus?) {
        statusFilter = status
    }

    /// Sets the classroom filter and reloads
    /// - Parameter classroomId: The classroom ID to filter by
    func filterByClassroom(_ classroomId: String?) {
        classroomFilter = classroomId
    }

    // MARK: - Utility Methods

    /// Clears the current error
    func clearError() {
        error = nil
        showError = false
    }

    /// Clears the success message
    func clearSuccess() {
        successMessage = nil
        showSuccess = false
    }

    /// Gets a child by ID from the local cache
    /// - Parameter childId: The child's unique identifier
    /// - Returns: The child, or nil if not in cache
    func getChild(byId childId: String) -> Child? {
        children.first { $0.id == childId }
    }

    // MARK: - Private Methods

    /// Sets up debounced search subscription
    private func setupSearchDebounce() {
        searchSubject
            .debounce(for: .milliseconds(300), scheduler: DispatchQueue.main)
            .removeDuplicates()
            .sink { [weak self] _ in
                Task { @MainActor [weak self] in
                    // For now, we do local filtering
                    // If server-side search is needed, call loadChildren(reset: true) here
                    self?.objectWillChange.send()
                }
            }
            .store(in: &cancellables)
    }

    /// Applies current sorting to the children array
    private func applySorting() {
        switch sortOrder {
        case .nameAsc:
            children.sort { $0.fullName.localizedCompare($1.fullName) == .orderedAscending }
        case .nameDesc:
            children.sort { $0.fullName.localizedCompare($1.fullName) == .orderedDescending }
        case .ageAsc:
            children.sort { $0.dateOfBirth > $1.dateOfBirth }
        case .ageDesc:
            children.sort { $0.dateOfBirth < $1.dateOfBirth }
        case .enrollmentDateAsc:
            children.sort {
                ($0.enrollmentDate ?? .distantPast) < ($1.enrollmentDate ?? .distantPast)
            }
        case .enrollmentDateDesc:
            children.sort {
                ($0.enrollmentDate ?? .distantPast) > ($1.enrollmentDate ?? .distantPast)
            }
        case .classroomAsc:
            children.sort {
                ($0.classroomName ?? "").localizedCompare($1.classroomName ?? "") == .orderedAscending
            }
        }
    }
}

// MARK: - Child Sort Order

/// Sort order options for the child list
enum ChildSortOrder: String, CaseIterable, Identifiable {
    case nameAsc = "name_asc"
    case nameDesc = "name_desc"
    case ageAsc = "age_asc"
    case ageDesc = "age_desc"
    case enrollmentDateAsc = "enrollment_date_asc"
    case enrollmentDateDesc = "enrollment_date_desc"
    case classroomAsc = "classroom_asc"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .nameAsc:
            return String(localized: "Name (A-Z)")
        case .nameDesc:
            return String(localized: "Name (Z-A)")
        case .ageAsc:
            return String(localized: "Age (Youngest first)")
        case .ageDesc:
            return String(localized: "Age (Oldest first)")
        case .enrollmentDateAsc:
            return String(localized: "Enrollment (Oldest first)")
        case .enrollmentDateDesc:
            return String(localized: "Enrollment (Newest first)")
        case .classroomAsc:
            return String(localized: "Classroom (A-Z)")
        }
    }

    var systemImage: String {
        switch self {
        case .nameAsc, .classroomAsc:
            return "arrow.up"
        case .nameDesc:
            return "arrow.down"
        case .ageAsc:
            return "arrow.up"
        case .ageDesc:
            return "arrow.down"
        case .enrollmentDateAsc:
            return "arrow.up"
        case .enrollmentDateDesc:
            return "arrow.down"
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension ChildListViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = [.preview, .previewInfant, .previewWaitlist]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with empty state for previews
    static var previewEmpty: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = []
        viewModel.totalCount = 0
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel with search active for previews
    static var previewSearching: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = [.preview, .previewInfant, .previewWaitlist]
        viewModel.searchText = "Emma"
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with filter active for previews
    static var previewFiltered: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = [.preview, .previewInfant]
        viewModel.statusFilter = .active
        viewModel.totalCount = 2
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with many children for testing pagination
    static var previewManyChildren: ChildListViewModel {
        let viewModel = ChildListViewModel()
        var children: [Child] = []
        for i in 1...50 {
            children.append(Child(
                id: "child-\(i)",
                firstName: "Child",
                lastName: "Number \(i)",
                dateOfBirth: Calendar.current.date(byAdding: .year, value: -Int.random(in: 1...5), to: Date()) ?? Date(),
                enrollmentStatus: .active,
                classroomName: "Classroom \(i % 4 + 1)",
                classroomId: "classroom-\(i % 4 + 1)",
                primaryGuardianId: "guardian-\(i)",
                primaryGuardianName: "Parent \(i)",
                primaryGuardianEmail: "parent\(i)@email.com",
                primaryGuardianPhone: "(514) 555-\(String(format: "%04d", i))",
                secondaryGuardianId: nil,
                secondaryGuardianName: nil,
                allergies: i % 5 == 0 ? "Peanuts" : nil,
                medicalNotes: nil,
                dietaryRequirements: nil,
                profilePhotoURL: nil,
                enrollmentDate: Calendar.current.date(byAdding: .month, value: -i, to: Date()),
                expectedGraduationDate: nil,
                notes: nil,
                createdAt: Date(),
                updatedAt: Date()
            ))
        }
        viewModel.children = children
        viewModel.totalCount = 100
        viewModel.hasLoaded = true
        viewModel.hasMoreData = true
        return viewModel
    }

    /// Creates a mock ViewModel with selections for previews
    static var previewWithSelections: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = [.preview, .previewInfant, .previewWaitlist]
        viewModel.selectedChildIds = Set([Child.preview.id, Child.previewInfant.id])
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when the child list is refreshed
    static let childListRefreshed = Notification.Name("childListRefreshed")

    /// Posted when a child is created
    static let childCreated = Notification.Name("childCreated")

    /// Posted when a child is updated
    static let childUpdated = Notification.Name("childUpdated")

    /// Posted when a child is deleted
    static let childDeleted = Notification.Name("childDeleted")
}
