//
//  Releve24ViewModel.swift
//  LAYAAdmin
//
//  ViewModel for Quebec RL-24 tax slip management in the LAYA Admin application.
//  Handles loading, filtering, generation, and export operations for RL-24 slips.
//
//  Critical Business Rule: RL-24 amounts must reflect PAID amounts at filing time,
//  NOT invoiced amounts. If additional payments are received after initial RL-24 filing,
//  an amended RL-24 (type A) must be issued.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Releve24 ViewModel

/// ViewModel for managing the RL-24 list view state.
///
/// This ViewModel acts as a bridge between the UI layer and the Gibbon CMS API,
/// providing observable state for the RL-24 view.
///
/// Features:
/// - Load RL-24 slips with pagination support
/// - Filter by tax year, family, and status
/// - Generate new RL-24 slips
/// - Export to PDF (individual or batch)
/// - Calculate RL-24 values before generation
@MainActor
final class Releve24ViewModel: ObservableObject {

    // MARK: - Published Properties

    /// List of RL-24 slips to display
    @Published private(set) var releve24s: [Releve24] = []

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

    // MARK: - Filter Properties

    /// Current tax year filter
    @Published var taxYear: Int {
        didSet {
            Task {
                await loadReleve24s(reset: true)
            }
        }
    }

    /// Current status filter
    @Published var statusFilter: Releve24Status? = nil {
        didSet {
            Task {
                await loadReleve24s(reset: true)
            }
        }
    }

    /// Current family filter
    @Published var familyFilter: String? = nil {
        didSet {
            Task {
                await loadReleve24s(reset: true)
            }
        }
    }

    /// Search text for filtering
    @Published var searchText = "" {
        didSet {
            searchSubject.send(searchText)
        }
    }

    /// Current sort order
    @Published var sortOrder: Releve24SortOrder = .childNameAsc {
        didSet {
            applySorting()
        }
    }

    // MARK: - Selection

    /// Currently selected RL-24 IDs (for bulk operations)
    @Published var selectedReleve24Ids: Set<String> = []

    /// The currently focused/selected RL-24 for detail view
    @Published var selectedReleve24: Releve24?

    // MARK: - Operation State

    /// Whether a generation operation is in progress
    @Published private(set) var isGenerating = false

    /// Whether an export operation is in progress
    @Published private(set) var isExporting = false

    /// Whether a calculation operation is in progress
    @Published private(set) var isCalculating = false

    /// Current calculation result
    @Published private(set) var calculation: Releve24Calculation?

    /// Success message to display after an operation
    @Published var successMessage: String?

    /// Whether to show the success alert
    @Published var showSuccess = false

    // MARK: - Statistics

    /// Total count of RL-24s matching the current filter
    @Published private(set) var totalCount = 0

    // MARK: - Computed Properties

    /// Whether the list is empty (after loading)
    var isEmpty: Bool {
        hasLoaded && releve24s.isEmpty
    }

    /// Whether search is active
    var isSearching: Bool {
        !searchText.trimmingCharacters(in: .whitespaces).isEmpty
    }

    /// Whether any filter is active (besides tax year which is always set)
    var hasActiveFilters: Bool {
        statusFilter != nil || familyFilter != nil
    }

    /// Filtered RL-24s based on local search
    var filteredReleve24s: [Releve24] {
        guard isSearching else { return releve24s }

        let query = searchText.lowercased().trimmingCharacters(in: .whitespaces)
        return releve24s.filter { releve24 in
            releve24.childName.lowercased().contains(query) ||
            releve24.familyName.lowercased().contains(query) ||
            releve24.recipientName.lowercased().contains(query) ||
            (releve24.referenceNumber?.lowercased().contains(query) ?? false)
        }
    }

    /// Number of draft RL-24s in the current list
    var draftCount: Int {
        releve24s.filter { $0.status == .draft }.count
    }

    /// Number of generated RL-24s in the current list
    var generatedCount: Int {
        releve24s.filter { $0.status == .generated }.count
    }

    /// Number of sent RL-24s in the current list
    var sentCount: Int {
        releve24s.filter { $0.status == .sent }.count
    }

    /// Number of filed RL-24s in the current list
    var filedCount: Int {
        releve24s.filter { $0.status == .filed }.count
    }

    /// Total qualifying expenses for all displayed RL-24s
    var totalQualifyingExpenses: Double {
        releve24s.reduce(0) { $0 + $1.qualifyingExpenses }
    }

    /// Formatted total qualifying expenses string
    var formattedTotalQualifyingExpenses: String {
        totalQualifyingExpenses.asCurrency
    }

    /// Number of selected RL-24s
    var selectedCount: Int {
        selectedReleve24Ids.count
    }

    /// Whether all visible RL-24s are selected
    var allSelected: Bool {
        !filteredReleve24s.isEmpty && selectedReleve24Ids.count == filteredReleve24s.count
    }

    /// Available tax years for selection
    var availableTaxYears: [Int] {
        let currentYear = Calendar.current.component(.year, from: Date())
        return Array((currentYear - 5)...currentYear).reversed()
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

    /// Creates a new Releve24ViewModel
    /// - Parameter gibbonClient: The Gibbon client to use (defaults to shared instance)
    init(gibbonClient: GibbonClient = .shared) {
        self.gibbonClient = gibbonClient
        // Default to previous year for tax slip generation
        self.taxYear = Calendar.current.component(.year, from: Date()) - 1
        setupSearchDebounce()
    }

    // MARK: - Public Methods

    /// Loads RL-24 slips from the API
    /// - Parameter reset: Whether to reset pagination and reload from the beginning
    func loadReleve24s(reset: Bool = false) async {
        if reset {
            currentOffset = 0
            releve24s = []
        }

        // Don't load if already loading or if no more data
        guard !isLoading else { return }
        if !reset && !hasMoreData && hasLoaded { return }

        isLoading = true
        error = nil
        showError = false

        do {
            let response = try await gibbonClient.fetchReleve24s(
                taxYear: taxYear,
                familyId: familyFilter,
                status: statusFilter,
                skip: currentOffset,
                limit: pageSize
            )

            if reset {
                releve24s = response.items
            } else {
                releve24s.append(contentsOf: response.items)
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

    /// Refreshes the RL-24 list (reloads from the beginning)
    func refresh() async {
        await loadReleve24s(reset: true)
    }

    /// Loads more RL-24s (pagination)
    func loadMore() async {
        guard hasMoreData && !isLoading else { return }
        await loadReleve24s(reset: false)
    }

    // MARK: - Calculation Methods

    /// Calculates RL-24 values for a child and tax year
    /// - Parameters:
    ///   - childId: The child's unique identifier
    ///   - familyId: The family's unique identifier
    func calculateReleve24(childId: String, familyId: String) async {
        isCalculating = true
        error = nil
        calculation = nil

        do {
            calculation = try await gibbonClient.calculateReleve24(
                childId: childId,
                familyId: familyId,
                taxYear: taxYear
            )
        } catch {
            self.error = error
            self.showError = true
        }

        isCalculating = false
    }

    /// Clears the current calculation
    func clearCalculation() {
        calculation = nil
    }

    // MARK: - Generation Methods

    /// Generates a new RL-24 slip
    /// - Parameter request: The RL-24 generation request
    /// - Returns: The generated RL-24, or nil if failed
    @discardableResult
    func generateReleve24(_ request: Releve24Request) async -> Releve24? {
        isGenerating = true
        error = nil

        do {
            let releve24 = try await gibbonClient.generateReleve24(request)

            // Add to the beginning of the list
            releve24s.insert(releve24, at: 0)
            totalCount += 1
            applySorting()

            successMessage = String(localized: "RL-24 generated successfully")
            showSuccess = true

            isGenerating = false
            return releve24

        } catch {
            self.error = error
            self.showError = true
            isGenerating = false
            return nil
        }
    }

    // MARK: - Export Methods

    /// Exports an RL-24 to PDF
    /// - Parameters:
    ///   - releve24Id: The RL-24's unique identifier
    ///   - markAsSent: Whether to mark as sent after export
    /// - Returns: The updated RL-24, or nil if failed
    @discardableResult
    func exportPDF(releve24Id: String, markAsSent: Bool = false) async -> Releve24? {
        isExporting = true
        error = nil

        do {
            let request = Releve24ExportRequest(
                releve24Id: releve24Id,
                markAsSent: markAsSent
            )
            let updatedReleve24 = try await gibbonClient.exportReleve24(request)

            // Update in the local list
            if let index = releve24s.firstIndex(where: { $0.id == releve24Id }) {
                releve24s[index] = updatedReleve24
            }

            // Update selected RL-24 if it's the same
            if selectedReleve24?.id == releve24Id {
                selectedReleve24 = updatedReleve24
            }

            successMessage = String(localized: "RL-24 exported to PDF successfully")
            showSuccess = true

            isExporting = false
            return updatedReleve24

        } catch {
            self.error = error
            self.showError = true
            isExporting = false
            return nil
        }
    }

    /// Batch exports multiple RL-24s
    /// - Parameters:
    ///   - format: Export format
    ///   - markAsSent: Whether to mark as sent after export
    /// - Returns: Array of exported RL-24 summaries, or nil if failed
    @discardableResult
    func batchExport(
        format: Releve24ExportFormat = .combinedPdf,
        markAsSent: Bool = false
    ) async -> [Releve24Summary]? {
        isExporting = true
        error = nil

        do {
            let familyIds = selectedReleve24Ids.isEmpty ? nil : Array(selectedReleve24Ids)
            let request = Releve24BatchExportRequest(
                taxYear: taxYear,
                familyIds: familyIds,
                format: format,
                markAsSent: markAsSent
            )
            let summaries = try await gibbonClient.batchExportReleve24(request)

            // Refresh the list to get updated statuses
            await loadReleve24s(reset: true)

            let count = summaries.count
            successMessage = String(localized: "\(count) RL-24(s) exported successfully")
            showSuccess = true

            isExporting = false
            return summaries

        } catch {
            self.error = error
            self.showError = true
            isExporting = false
            return nil
        }
    }

    /// Gets the PDF download URL for an RL-24
    /// - Parameter releve24Id: The RL-24's unique identifier
    /// - Returns: The URL to download the RL-24 PDF
    func getReleve24PdfUrl(releve24Id: String) -> URL? {
        gibbonClient.getReleve24PdfUrl(releve24Id: releve24Id)
    }

    // MARK: - Selection Methods

    /// Toggles selection for an RL-24
    /// - Parameter releve24Id: The RL-24's ID to toggle
    func toggleSelection(releve24Id: String) {
        if selectedReleve24Ids.contains(releve24Id) {
            selectedReleve24Ids.remove(releve24Id)
        } else {
            selectedReleve24Ids.insert(releve24Id)
        }
    }

    /// Selects all visible RL-24s
    func selectAll() {
        selectedReleve24Ids = Set(filteredReleve24s.map { $0.id })
    }

    /// Deselects all RL-24s
    func deselectAll() {
        selectedReleve24Ids.removeAll()
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

    /// Clears all active filters (except tax year)
    func clearFilters() {
        statusFilter = nil
        familyFilter = nil
        searchText = ""
    }

    /// Sets the status filter and reloads
    /// - Parameter status: The RL-24 status to filter by
    func filterByStatus(_ status: Releve24Status?) {
        statusFilter = status
    }

    /// Sets the tax year and reloads
    /// - Parameter year: The tax year to filter by
    func setTaxYear(_ year: Int) {
        taxYear = year
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

    /// Gets an RL-24 by ID from the local cache
    /// - Parameter releve24Id: The RL-24's unique identifier
    /// - Returns: The RL-24, or nil if not in cache
    func getReleve24(byId releve24Id: String) -> Releve24? {
        releve24s.first { $0.id == releve24Id }
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
                    self?.objectWillChange.send()
                }
            }
            .store(in: &cancellables)
    }

    /// Applies current sorting to the RL-24s array
    private func applySorting() {
        switch sortOrder {
        case .childNameAsc:
            releve24s.sort { $0.childName.localizedCompare($1.childName) == .orderedAscending }
        case .childNameDesc:
            releve24s.sort { $0.childName.localizedCompare($1.childName) == .orderedDescending }
        case .familyNameAsc:
            releve24s.sort { $0.familyName.localizedCompare($1.familyName) == .orderedAscending }
        case .familyNameDesc:
            releve24s.sort { $0.familyName.localizedCompare($1.familyName) == .orderedDescending }
        case .amountAsc:
            releve24s.sort { $0.qualifyingExpenses < $1.qualifyingExpenses }
        case .amountDesc:
            releve24s.sort { $0.qualifyingExpenses > $1.qualifyingExpenses }
        case .statusAsc:
            releve24s.sort { $0.statusDisplayName.localizedCompare($1.statusDisplayName) == .orderedAscending }
        case .dateAsc:
            releve24s.sort { ($0.generatedAt ?? Date.distantPast) < ($1.generatedAt ?? Date.distantPast) }
        case .dateDesc:
            releve24s.sort { ($0.generatedAt ?? Date.distantPast) > ($1.generatedAt ?? Date.distantPast) }
        }
    }
}

// MARK: - Releve24 Sort Order

/// Sort order options for the RL-24 list
enum Releve24SortOrder: String, CaseIterable, Identifiable {
    case childNameAsc = "child_name_asc"
    case childNameDesc = "child_name_desc"
    case familyNameAsc = "family_name_asc"
    case familyNameDesc = "family_name_desc"
    case amountAsc = "amount_asc"
    case amountDesc = "amount_desc"
    case statusAsc = "status_asc"
    case dateAsc = "date_asc"
    case dateDesc = "date_desc"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .childNameAsc:
            return String(localized: "Child Name (A-Z)")
        case .childNameDesc:
            return String(localized: "Child Name (Z-A)")
        case .familyNameAsc:
            return String(localized: "Family Name (A-Z)")
        case .familyNameDesc:
            return String(localized: "Family Name (Z-A)")
        case .amountAsc:
            return String(localized: "Amount (Low to High)")
        case .amountDesc:
            return String(localized: "Amount (High to Low)")
        case .statusAsc:
            return String(localized: "Status (A-Z)")
        case .dateAsc:
            return String(localized: "Generated Date (Oldest first)")
        case .dateDesc:
            return String(localized: "Generated Date (Newest first)")
        }
    }

    var systemImage: String {
        switch self {
        case .childNameAsc, .familyNameAsc, .amountAsc, .statusAsc, .dateAsc:
            return "arrow.up"
        case .childNameDesc, .familyNameDesc, .amountDesc, .dateDesc:
            return "arrow.down"
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension Releve24ViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.releve24s = [.preview, .previewAmended, .previewFiled]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with empty state for previews
    static var previewEmpty: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.releve24s = []
        viewModel.totalCount = 0
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel with calculation for previews
    static var previewWithCalculation: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.releve24s = [.preview]
        viewModel.calculation = .preview
        viewModel.totalCount = 1
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with selections for previews
    static var previewWithSelections: Releve24ViewModel {
        let viewModel = Releve24ViewModel()
        viewModel.releve24s = [.preview, .previewAmended, .previewFiled]
        viewModel.selectedReleve24Ids = Set([Releve24.preview.id, Releve24.previewFiled.id])
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when the RL-24 list is refreshed
    static let releve24ListRefreshed = Notification.Name("releve24ListRefreshed")

    /// Posted when an RL-24 is generated
    static let releve24Generated = Notification.Name("releve24Generated")

    /// Posted when an RL-24 is exported
    static let releve24Exported = Notification.Name("releve24Exported")
}
