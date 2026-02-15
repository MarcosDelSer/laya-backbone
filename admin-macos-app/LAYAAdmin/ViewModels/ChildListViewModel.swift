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
/// - Offline support with local caching
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

    // MARK: - Offline Support

    /// Whether the app is currently offline
    @Published private(set) var isOffline = false

    /// Whether data is being loaded from local cache
    @Published private(set) var isLoadingFromCache = false

    /// Number of pending sync operations for children
    @Published private(set) var pendingSyncCount: Int = 0

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

    /// Sync service for offline support
    private let syncService: SyncService

    /// Realm manager for local data persistence
    private let realmManager: RealmManager

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
    /// - Parameters:
    ///   - gibbonClient: The Gibbon client to use (defaults to shared instance)
    ///   - syncService: The sync service for offline support (defaults to shared instance)
    ///   - realmManager: The Realm manager for local persistence (defaults to shared instance)
    init(
        gibbonClient: GibbonClient = .shared,
        syncService: SyncService = .shared,
        realmManager: RealmManager = .shared
    ) {
        self.gibbonClient = gibbonClient
        self.syncService = syncService
        self.realmManager = realmManager
        setupSearchDebounce()
        setupOfflineSupport()
    }

    // MARK: - Offline Support Setup

    /// Sets up observers for offline support
    private func setupOfflineSupport() {
        // Observe network status changes
        syncService.$networkStatus
            .receive(on: DispatchQueue.main)
            .sink { [weak self] status in
                self?.isOffline = !status.isConnected
            }
            .store(in: &cancellables)

        // Observe pending sync count
        syncService.$pendingOperationsCount
            .receive(on: DispatchQueue.main)
            .sink { [weak self] _ in
                self?.updatePendingSyncCount()
            }
            .store(in: &cancellables)

        // Initial state
        isOffline = !syncService.isOnline
        updatePendingSyncCount()
    }

    /// Updates the pending sync count for child operations
    private func updatePendingSyncCount() {
        let pendingOps = realmManager.fetchPendingSyncOperations()
        pendingSyncCount = pendingOps.filter { $0.entityType == SyncEntityType.child.rawValue }.count
    }

    // MARK: - Public Methods

    /// Loads children from the API (or local cache if offline)
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

        // Check if offline - load from local cache
        if isOffline {
            await loadChildrenFromCache()
            isLoading = false
            return
        }

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

            // Cache fetched children locally for offline access
            await cacheChildrenLocally(response.items)

            // Apply current sorting
            applySorting()

        } catch {
            // On network error, try loading from cache
            if isNetworkError(error) {
                isOffline = true
                await loadChildrenFromCache()
            } else {
                self.error = error
                self.showError = true
            }
        }

        isLoading = false
    }

    /// Loads children from local cache
    private func loadChildrenFromCache() async {
        isLoadingFromCache = true

        let cachedChildren = realmManager.fetchChildren(
            status: statusFilter,
            classroomId: classroomFilter,
            searchQuery: isSearching ? searchText : nil
        )

        children = cachedChildren
        totalCount = cachedChildren.count
        hasMoreData = false
        hasLoaded = true

        // Apply current sorting
        applySorting()

        isLoadingFromCache = false
    }

    /// Caches children to local storage for offline access
    private func cacheChildrenLocally(_ childrenToCache: [Child]) async {
        do {
            try await realmManager.saveChildren(childrenToCache)
        } catch {
            // Silently fail - caching is best effort
        }
    }

    /// Checks if an error is a network-related error
    private func isNetworkError(_ error: Error) -> Bool {
        if let apiError = error as? APIError {
            switch apiError {
            case .networkError:
                return true
            default:
                return false
            }
        }
        // Check for URLSession network errors
        let nsError = error as NSError
        return nsError.domain == NSURLErrorDomain
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

        // If offline, create locally and queue for sync
        if isOffline {
            return await createChildOffline(request)
        }

        do {
            let child = try await gibbonClient.createChild(request)

            // Add to the beginning of the list
            children.insert(child, at: 0)
            totalCount += 1
            applySorting()

            // Cache locally
            await cacheChildrenLocally([child])

            successMessage = String(localized: "Child added successfully")
            showSuccess = true

            isSaving = false
            return child

        } catch {
            // If network error, try creating offline
            if isNetworkError(error) {
                isOffline = true
                return await createChildOffline(request)
            }
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Creates a child locally when offline and queues for sync
    private func createChildOffline(_ request: ChildRequest) async -> Child? {
        // Create a temporary child with a local ID
        let localId = "local-\(UUID().uuidString)"
        let child = Child(
            id: localId,
            firstName: request.firstName,
            lastName: request.lastName,
            dateOfBirth: request.dateOfBirth,
            enrollmentStatus: request.enrollmentStatus ?? .pending,
            classroomName: nil,
            classroomId: request.classroomId,
            primaryGuardianId: request.primaryGuardianId,
            primaryGuardianName: request.primaryGuardianName ?? "",
            primaryGuardianEmail: request.primaryGuardianEmail ?? "",
            primaryGuardianPhone: request.primaryGuardianPhone ?? "",
            secondaryGuardianId: request.secondaryGuardianId,
            secondaryGuardianName: request.secondaryGuardianName,
            allergies: request.allergies,
            medicalNotes: request.medicalNotes,
            dietaryRequirements: request.dietaryRequirements,
            profilePhotoURL: nil,
            enrollmentDate: request.enrollmentDate,
            expectedGraduationDate: request.expectedGraduationDate,
            notes: request.notes,
            createdAt: Date(),
            updatedAt: Date()
        )

        do {
            // Save to local database and queue for sync
            try await realmManager.saveChild(child, queueSync: true)

            // Add to the beginning of the list
            children.insert(child, at: 0)
            totalCount += 1
            applySorting()

            updatePendingSyncCount()

            successMessage = String(localized: "Child saved locally. Will sync when online.")
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

        // If offline, update locally and queue for sync
        if isOffline {
            return await updateChildOffline(childId: childId, request: request)
        }

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

            // Cache locally
            await cacheChildrenLocally([updatedChild])

            successMessage = String(localized: "Child updated successfully")
            showSuccess = true

            isSaving = false
            return updatedChild

        } catch {
            // If network error, try updating offline
            if isNetworkError(error) {
                isOffline = true
                return await updateChildOffline(childId: childId, request: request)
            }
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Updates a child locally when offline and queues for sync
    private func updateChildOffline(childId: String, request: ChildRequest) async -> Child? {
        // Get existing child data to preserve non-updated fields
        let existingChild = children.first { $0.id == childId } ?? realmManager.fetchChild(id: childId)

        let updatedChild = Child(
            id: childId,
            firstName: request.firstName,
            lastName: request.lastName,
            dateOfBirth: request.dateOfBirth,
            enrollmentStatus: request.enrollmentStatus ?? existingChild?.enrollmentStatus ?? .pending,
            classroomName: existingChild?.classroomName,
            classroomId: request.classroomId ?? existingChild?.classroomId,
            primaryGuardianId: request.primaryGuardianId ?? existingChild?.primaryGuardianId ?? "",
            primaryGuardianName: request.primaryGuardianName ?? existingChild?.primaryGuardianName ?? "",
            primaryGuardianEmail: request.primaryGuardianEmail ?? existingChild?.primaryGuardianEmail ?? "",
            primaryGuardianPhone: request.primaryGuardianPhone ?? existingChild?.primaryGuardianPhone ?? "",
            secondaryGuardianId: request.secondaryGuardianId ?? existingChild?.secondaryGuardianId,
            secondaryGuardianName: request.secondaryGuardianName ?? existingChild?.secondaryGuardianName,
            allergies: request.allergies ?? existingChild?.allergies,
            medicalNotes: request.medicalNotes ?? existingChild?.medicalNotes,
            dietaryRequirements: request.dietaryRequirements ?? existingChild?.dietaryRequirements,
            profilePhotoURL: existingChild?.profilePhotoURL,
            enrollmentDate: request.enrollmentDate ?? existingChild?.enrollmentDate,
            expectedGraduationDate: request.expectedGraduationDate ?? existingChild?.expectedGraduationDate,
            notes: request.notes ?? existingChild?.notes,
            createdAt: existingChild?.createdAt ?? Date(),
            updatedAt: Date()
        )

        do {
            // Save to local database and queue for sync
            try await realmManager.saveChild(updatedChild, queueSync: true)

            // Update in the local list
            if let index = children.firstIndex(where: { $0.id == childId }) {
                children[index] = updatedChild
            }

            // Update selected child if it's the same
            if selectedChild?.id == childId {
                selectedChild = updatedChild
            }

            updatePendingSyncCount()

            successMessage = String(localized: "Child saved locally. Will sync when online.")
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

        // If offline, delete locally and queue for sync
        if isOffline {
            return await deleteChildOffline(childId: childId)
        }

        do {
            try await gibbonClient.deleteChild(childId: childId)

            // Remove from local list
            children.removeAll { $0.id == childId }
            totalCount -= 1

            // Remove from local cache
            try? await realmManager.deleteChild(id: childId, queueSync: false)

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
            // If network error, try deleting offline
            if isNetworkError(error) {
                isOffline = true
                return await deleteChildOffline(childId: childId)
            }
            self.error = error
            self.showError = true
            isDeleting = false
            return false
        }
    }

    /// Deletes a child locally when offline and queues for sync
    private func deleteChildOffline(childId: String) async -> Bool {
        do {
            // Delete from local database and queue for sync
            try await realmManager.deleteChild(id: childId, queueSync: true)

            // Remove from local list
            children.removeAll { $0.id == childId }
            totalCount -= 1

            // Clear selection if deleted child was selected
            selectedChildIds.remove(childId)
            if selectedChild?.id == childId {
                selectedChild = nil
            }

            updatePendingSyncCount()

            successMessage = String(localized: "Child deleted locally. Will sync when online.")
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
            // If offline, delete locally
            if isOffline {
                do {
                    try await realmManager.deleteChild(id: childId, queueSync: true)
                    children.removeAll { $0.id == childId }
                    totalCount -= 1
                    selectedChildIds.remove(childId)
                    deletedCount += 1
                } catch {
                    // Continue with other deletions even if one fails
                }
            } else {
                do {
                    try await gibbonClient.deleteChild(childId: childId)
                    children.removeAll { $0.id == childId }
                    totalCount -= 1
                    selectedChildIds.remove(childId)
                    // Remove from local cache
                    try? await realmManager.deleteChild(id: childId, queueSync: false)
                    deletedCount += 1
                } catch {
                    // If network error, try deleting offline
                    if isNetworkError(error) {
                        isOffline = true
                        do {
                            try await realmManager.deleteChild(id: childId, queueSync: true)
                            children.removeAll { $0.id == childId }
                            totalCount -= 1
                            selectedChildIds.remove(childId)
                            deletedCount += 1
                        } catch {
                            // Continue with other deletions even if one fails
                        }
                    }
                }
            }
        }

        if deletedCount > 0 {
            let messageKey = isOffline
                ? String(localized: "\(deletedCount) children deleted locally. Will sync when online.")
                : String(localized: "\(deletedCount) children deleted successfully")
            successMessage = messageKey
            showSuccess = true

            if isOffline {
                updatePendingSyncCount()
            }
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

    /// Creates a mock ViewModel in offline state for previews
    static var previewOffline: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.children = [.preview, .previewInfant]
        viewModel.isOffline = true
        viewModel.pendingSyncCount = 2
        viewModel.totalCount = 2
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel loading from cache for previews
    static var previewLoadingFromCache: ChildListViewModel {
        let viewModel = ChildListViewModel()
        viewModel.isOffline = true
        viewModel.isLoadingFromCache = true
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
