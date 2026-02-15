//
//  StaffListViewModel.swift
//  LAYAAdmin
//
//  ViewModel for staff list management in the LAYA Admin application.
//  Handles loading, searching, filtering, and CRUD operations for staff members.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Staff List ViewModel

/// ViewModel for managing the staff list view state.
///
/// This ViewModel acts as a bridge between the UI layer and the Gibbon CMS API,
/// providing observable state for the staff list view.
///
/// Features:
/// - Load staff with pagination support
/// - Search by name with debounce
/// - Filter by employment status and role
/// - Sort by various fields
/// - CRUD operations (create, update, delete)
/// - Selection tracking for bulk operations
@MainActor
final class StaffListViewModel: ObservableObject {

    // MARK: - Published Properties

    /// List of staff members to display
    @Published private(set) var staff: [Staff] = []

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

    /// Current employment status filter
    @Published var statusFilter: StaffStatus? = nil {
        didSet {
            Task {
                await loadStaff(reset: true)
            }
        }
    }

    /// Current role filter
    @Published var roleFilter: StaffRole? = nil {
        didSet {
            Task {
                await loadStaff(reset: true)
            }
        }
    }

    /// Current classroom filter
    @Published var classroomFilter: String? = nil {
        didSet {
            Task {
                await loadStaff(reset: true)
            }
        }
    }

    /// Current sort order
    @Published var sortOrder: StaffSortOrder = .nameAsc {
        didSet {
            applySorting()
        }
    }

    // MARK: - Selection

    /// Currently selected staff IDs (for bulk operations)
    @Published var selectedStaffIds: Set<String> = []

    /// The currently focused/selected staff member for detail view
    @Published var selectedStaff: Staff?

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

    /// Total count of staff members matching the current filter
    @Published private(set) var totalCount = 0

    // MARK: - Computed Properties

    /// Whether the list is empty (after loading)
    var isEmpty: Bool {
        hasLoaded && staff.isEmpty
    }

    /// Whether search is active
    var isSearching: Bool {
        !searchText.trimmingCharacters(in: .whitespaces).isEmpty
    }

    /// Whether any filter is active
    var hasActiveFilters: Bool {
        statusFilter != nil || roleFilter != nil || classroomFilter != nil
    }

    /// Filtered staff based on local search
    var filteredStaff: [Staff] {
        guard isSearching else { return staff }

        let query = searchText.lowercased().trimmingCharacters(in: .whitespaces)
        return staff.filter { member in
            member.fullName.lowercased().contains(query) ||
            member.email.lowercased().contains(query) ||
            member.roleDisplayName.lowercased().contains(query) ||
            (member.assignedClassroomName?.lowercased().contains(query) ?? false) ||
            (member.employeeNumber?.lowercased().contains(query) ?? false)
        }
    }

    /// Number of active staff members in the current list
    var activeCount: Int {
        staff.filter { $0.status == .active }.count
    }

    /// Number of staff members on leave in the current list
    var onLeaveCount: Int {
        staff.filter { $0.status == .onLeave }.count
    }

    /// Number of childcare staff (educators) in the current list
    var childcareStaffCount: Int {
        staff.filter { $0.isChildcareStaff }.count
    }

    /// Number of staff with certification concerns
    var certificationConcernsCount: Int {
        staff.filter { $0.hasCertificationConcerns }.count
    }

    /// Number of selected staff members
    var selectedCount: Int {
        selectedStaffIds.count
    }

    /// Whether all visible staff members are selected
    var allSelected: Bool {
        !filteredStaff.isEmpty && selectedStaffIds.count == filteredStaff.count
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

    /// Creates a new StaffListViewModel
    /// - Parameter gibbonClient: The Gibbon client to use (defaults to shared instance)
    init(gibbonClient: GibbonClient = .shared) {
        self.gibbonClient = gibbonClient
        setupSearchDebounce()
    }

    // MARK: - Public Methods

    /// Loads staff members from the API
    /// - Parameter reset: Whether to reset pagination and reload from the beginning
    func loadStaff(reset: Bool = false) async {
        if reset {
            currentOffset = 0
            staff = []
        }

        // Don't load if already loading or if no more data
        guard !isLoading else { return }
        if !reset && !hasMoreData && hasLoaded { return }

        isLoading = true
        error = nil
        showError = false

        do {
            let response = try await gibbonClient.fetchStaff(
                status: statusFilter,
                role: roleFilter,
                classroomId: classroomFilter,
                skip: currentOffset,
                limit: pageSize
            )

            if reset {
                staff = response.items
            } else {
                staff.append(contentsOf: response.items)
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

    /// Refreshes the staff list (reloads from the beginning)
    func refresh() async {
        await loadStaff(reset: true)
    }

    /// Loads more staff members (pagination)
    func loadMore() async {
        guard hasMoreData && !isLoading else { return }
        await loadStaff(reset: false)
    }

    /// Fetches a specific staff member by ID
    /// - Parameter staffId: The staff member's unique identifier
    /// - Returns: The staff member details, or nil if not found
    func fetchStaffMember(staffId: String) async -> Staff? {
        do {
            let staffMember = try await gibbonClient.fetchStaffMember(staffId: staffId)
            return staffMember
        } catch {
            self.error = error
            self.showError = true
            return nil
        }
    }

    /// Creates a new staff member
    /// - Parameter request: The staff creation request
    /// - Returns: The created staff member, or nil if failed
    @discardableResult
    func createStaffMember(_ request: StaffRequest) async -> Staff? {
        isSaving = true
        error = nil

        do {
            let staffMember = try await gibbonClient.createStaffMember(request)

            // Add to the beginning of the list
            staff.insert(staffMember, at: 0)
            totalCount += 1
            applySorting()

            successMessage = String(localized: "Staff member added successfully")
            showSuccess = true

            isSaving = false
            return staffMember

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Updates an existing staff member
    /// - Parameters:
    ///   - staffId: The staff member's unique identifier
    ///   - request: The staff update request
    /// - Returns: The updated staff member, or nil if failed
    @discardableResult
    func updateStaffMember(staffId: String, request: StaffRequest) async -> Staff? {
        isSaving = true
        error = nil

        do {
            let updatedStaff = try await gibbonClient.updateStaffMember(staffId: staffId, request: request)

            // Update in the local list
            if let index = staff.firstIndex(where: { $0.id == staffId }) {
                staff[index] = updatedStaff
            }

            // Update selected staff if it's the same
            if selectedStaff?.id == staffId {
                selectedStaff = updatedStaff
            }

            successMessage = String(localized: "Staff member updated successfully")
            showSuccess = true

            isSaving = false
            return updatedStaff

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Deletes a staff member
    /// - Parameter staffId: The staff member's unique identifier
    /// - Returns: Whether the deletion was successful
    @discardableResult
    func deleteStaffMember(staffId: String) async -> Bool {
        isDeleting = true
        error = nil

        do {
            try await gibbonClient.deleteStaffMember(staffId: staffId)

            // Remove from local list
            staff.removeAll { $0.id == staffId }
            totalCount -= 1

            // Clear selection if deleted staff was selected
            selectedStaffIds.remove(staffId)
            if selectedStaff?.id == staffId {
                selectedStaff = nil
            }

            successMessage = String(localized: "Staff member deleted successfully")
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

    /// Deletes multiple staff members
    /// - Parameter staffIds: The staff member IDs to delete
    /// - Returns: Number of successfully deleted staff members
    @discardableResult
    func deleteStaffMembers(staffIds: Set<String>) async -> Int {
        isDeleting = true
        error = nil
        var deletedCount = 0

        for staffId in staffIds {
            do {
                try await gibbonClient.deleteStaffMember(staffId: staffId)
                staff.removeAll { $0.id == staffId }
                totalCount -= 1
                selectedStaffIds.remove(staffId)
                deletedCount += 1
            } catch {
                // Continue with other deletions even if one fails
            }
        }

        if deletedCount > 0 {
            successMessage = String(localized: "\(deletedCount) staff members deleted successfully")
            showSuccess = true
        }

        // Clear selected staff if it was deleted
        if let selected = selectedStaff, staffIds.contains(selected.id) {
            selectedStaff = nil
        }

        isDeleting = false
        return deletedCount
    }

    // MARK: - Selection Methods

    /// Toggles selection for a staff member
    /// - Parameter staffId: The staff member's ID to toggle
    func toggleSelection(staffId: String) {
        if selectedStaffIds.contains(staffId) {
            selectedStaffIds.remove(staffId)
        } else {
            selectedStaffIds.insert(staffId)
        }
    }

    /// Selects all visible staff members
    func selectAll() {
        selectedStaffIds = Set(filteredStaff.map { $0.id })
    }

    /// Deselects all staff members
    func deselectAll() {
        selectedStaffIds.removeAll()
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
        roleFilter = nil
        classroomFilter = nil
        searchText = ""
    }

    /// Sets the status filter and reloads
    /// - Parameter status: The employment status to filter by
    func filterByStatus(_ status: StaffStatus?) {
        statusFilter = status
    }

    /// Sets the role filter and reloads
    /// - Parameter role: The staff role to filter by
    func filterByRole(_ role: StaffRole?) {
        roleFilter = role
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

    /// Gets a staff member by ID from the local cache
    /// - Parameter staffId: The staff member's unique identifier
    /// - Returns: The staff member, or nil if not in cache
    func getStaffMember(byId staffId: String) -> Staff? {
        staff.first { $0.id == staffId }
    }

    /// Gets staff members with certification concerns
    /// - Returns: Array of staff members with expiring or expired certifications
    func getStaffWithCertificationConcerns() -> [Staff] {
        staff.filter { $0.hasCertificationConcerns }
    }

    /// Gets active childcare staff (for ratio calculations)
    /// - Returns: Array of active educators and lead educators
    func getActiveChildcareStaff() -> [Staff] {
        staff.filter { $0.isActive && $0.isChildcareStaff }
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
                    // If server-side search is needed, call loadStaff(reset: true) here
                    self?.objectWillChange.send()
                }
            }
            .store(in: &cancellables)
    }

    /// Applies current sorting to the staff array
    private func applySorting() {
        switch sortOrder {
        case .nameAsc:
            staff.sort { $0.fullName.localizedCompare($1.fullName) == .orderedAscending }
        case .nameDesc:
            staff.sort { $0.fullName.localizedCompare($1.fullName) == .orderedDescending }
        case .roleAsc:
            staff.sort { $0.roleDisplayName.localizedCompare($1.roleDisplayName) == .orderedAscending }
        case .roleDesc:
            staff.sort { $0.roleDisplayName.localizedCompare($1.roleDisplayName) == .orderedDescending }
        case .hireDateAsc:
            staff.sort { $0.hireDate < $1.hireDate }
        case .hireDateDesc:
            staff.sort { $0.hireDate > $1.hireDate }
        case .classroomAsc:
            staff.sort {
                ($0.assignedClassroomName ?? "").localizedCompare($1.assignedClassroomName ?? "") == .orderedAscending
            }
        case .statusAsc:
            staff.sort { $0.statusDisplayName.localizedCompare($1.statusDisplayName) == .orderedAscending }
        }
    }
}

// MARK: - Staff Sort Order

/// Sort order options for the staff list
enum StaffSortOrder: String, CaseIterable, Identifiable {
    case nameAsc = "name_asc"
    case nameDesc = "name_desc"
    case roleAsc = "role_asc"
    case roleDesc = "role_desc"
    case hireDateAsc = "hire_date_asc"
    case hireDateDesc = "hire_date_desc"
    case classroomAsc = "classroom_asc"
    case statusAsc = "status_asc"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .nameAsc:
            return String(localized: "Name (A-Z)")
        case .nameDesc:
            return String(localized: "Name (Z-A)")
        case .roleAsc:
            return String(localized: "Role (A-Z)")
        case .roleDesc:
            return String(localized: "Role (Z-A)")
        case .hireDateAsc:
            return String(localized: "Hire Date (Oldest first)")
        case .hireDateDesc:
            return String(localized: "Hire Date (Newest first)")
        case .classroomAsc:
            return String(localized: "Classroom (A-Z)")
        case .statusAsc:
            return String(localized: "Status (A-Z)")
        }
    }

    var systemImage: String {
        switch self {
        case .nameAsc, .roleAsc, .classroomAsc, .statusAsc:
            return "arrow.up"
        case .nameDesc, .roleDesc:
            return "arrow.down"
        case .hireDateAsc:
            return "arrow.up"
        case .hireDateDesc:
            return "arrow.down"
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension StaffListViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = [.preview, .previewSubstitute, .previewOnLeave]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with empty state for previews
    static var previewEmpty: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = []
        viewModel.totalCount = 0
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel with search active for previews
    static var previewSearching: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = [.preview, .previewSubstitute, .previewOnLeave]
        viewModel.searchText = "Isabelle"
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with filter active for previews
    static var previewFiltered: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = [.preview, .previewSubstitute]
        viewModel.statusFilter = .active
        viewModel.totalCount = 2
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel filtered by role for previews
    static var previewFilteredByRole: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = [.preview]
        viewModel.roleFilter = .leadEducator
        viewModel.totalCount = 1
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with many staff for testing pagination
    static var previewManyStaff: StaffListViewModel {
        let viewModel = StaffListViewModel()
        var staffMembers: [Staff] = []
        let roles: [StaffRole] = [.educator, .leadEducator, .substitute, .administrative]
        let statuses: [StaffStatus] = [.active, .active, .active, .onLeave]

        for i in 1...50 {
            staffMembers.append(Staff(
                id: "staff-\(i)",
                firstName: "Staff",
                lastName: "Member \(i)",
                email: "staff\(i)@laya.ca",
                phone: "(514) 555-\(String(format: "%04d", i))",
                role: roles[i % roles.count],
                status: statuses[i % statuses.count],
                hireDate: Calendar.current.date(byAdding: .month, value: -i * 2, to: Date()) ?? Date(),
                terminationDate: nil,
                assignedClassroomId: i % 3 == 0 ? nil : "classroom-\(i % 4 + 1)",
                assignedClassroomName: i % 3 == 0 ? nil : "Classroom \(i % 4 + 1)",
                employeeNumber: "EMP-\(String(format: "%03d", i))",
                profilePhotoURL: nil,
                emergencyContactName: "Contact \(i)",
                emergencyContactPhone: "(514) 555-\(String(format: "%04d", i + 1000))",
                certifications: nil,
                hourlyRate: Double(18 + i % 10),
                contractedHours: 37.5,
                notes: nil,
                createdAt: Date(),
                updatedAt: Date()
            ))
        }
        viewModel.staff = staffMembers
        viewModel.totalCount = 100
        viewModel.hasLoaded = true
        viewModel.hasMoreData = true
        return viewModel
    }

    /// Creates a mock ViewModel with selections for previews
    static var previewWithSelections: StaffListViewModel {
        let viewModel = StaffListViewModel()
        viewModel.staff = [.preview, .previewSubstitute, .previewOnLeave]
        viewModel.selectedStaffIds = Set([Staff.preview.id, Staff.previewSubstitute.id])
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with certification concerns for previews
    static var previewWithCertificationConcerns: StaffListViewModel {
        let viewModel = StaffListViewModel()
        // Create staff with expiring certification
        let staffWithExpiringCert = Staff(
            id: "staff-expiring",
            firstName: "Jean",
            lastName: "Tremblay",
            email: "jean.tremblay@laya.ca",
            phone: "(514) 555-9999",
            role: .educator,
            status: .active,
            hireDate: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
            terminationDate: nil,
            assignedClassroomId: "classroom-1",
            assignedClassroomName: "Sunflowers",
            employeeNumber: "EMP-100",
            profilePhotoURL: nil,
            emergencyContactName: "Marie Tremblay",
            emergencyContactPhone: "(514) 555-8888",
            certifications: [
                StaffCertification(
                    id: "cert-expiring",
                    name: "First Aid & CPR",
                    issuingBody: "Red Cross",
                    issueDate: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
                    expiryDate: Calendar.current.date(byAdding: .day, value: 15, to: Date()),
                    certificateNumber: "FA-99999"
                )
            ],
            hourlyRate: 20.00,
            contractedHours: 37.5,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
        viewModel.staff = [.preview, staffWithExpiringCert, .previewSubstitute]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when the staff list is refreshed
    static let staffListRefreshed = Notification.Name("staffListRefreshed")

    /// Posted when a staff member is created
    static let staffCreated = Notification.Name("staffCreated")

    /// Posted when a staff member is updated
    static let staffUpdated = Notification.Name("staffUpdated")

    /// Posted when a staff member is deleted
    static let staffDeleted = Notification.Name("staffDeleted")
}
