//
//  InvoiceListViewModel.swift
//  LAYAAdmin
//
//  ViewModel for invoice list management in the LAYA Admin application.
//  Handles loading, searching, filtering, and CRUD operations for invoices.
//

import Foundation
import Combine
import SwiftUI

// MARK: - Invoice List ViewModel

/// ViewModel for managing the invoice list view state.
///
/// This ViewModel acts as a bridge between the UI layer and the Gibbon CMS API,
/// providing observable state for the invoice list view.
///
/// Features:
/// - Load invoices with pagination support
/// - Search by invoice number or family name with debounce
/// - Filter by payment status, family, child, and date range
/// - Sort by various fields
/// - CRUD operations (create, update, delete)
/// - Selection tracking for bulk operations
/// - Payment recording support
/// - Offline support with local caching
@MainActor
final class InvoiceListViewModel: ObservableObject {

    // MARK: - Published Properties

    /// List of invoices to display
    @Published private(set) var invoices: [Invoice] = []

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

    /// Number of pending sync operations for invoices
    @Published private(set) var pendingSyncCount: Int = 0

    // MARK: - Search & Filter

    /// Current search query
    @Published var searchText = "" {
        didSet {
            searchSubject.send(searchText)
        }
    }

    /// Current invoice status filter
    @Published var statusFilter: InvoiceStatus? = nil {
        didSet {
            Task {
                await loadInvoices(reset: true)
            }
        }
    }

    /// Current family filter
    @Published var familyFilter: String? = nil {
        didSet {
            Task {
                await loadInvoices(reset: true)
            }
        }
    }

    /// Current child filter
    @Published var childFilter: String? = nil {
        didSet {
            Task {
                await loadInvoices(reset: true)
            }
        }
    }

    /// Date range filter - start date
    @Published var startDateFilter: Date? = nil {
        didSet {
            Task {
                await loadInvoices(reset: true)
            }
        }
    }

    /// Date range filter - end date
    @Published var endDateFilter: Date? = nil {
        didSet {
            Task {
                await loadInvoices(reset: true)
            }
        }
    }

    /// Current sort order
    @Published var sortOrder: InvoiceSortOrder = .dateDesc {
        didSet {
            applySorting()
        }
    }

    // MARK: - Selection

    /// Currently selected invoice IDs (for bulk operations)
    @Published var selectedInvoiceIds: Set<String> = []

    /// The currently focused/selected invoice for detail view
    @Published var selectedInvoice: Invoice?

    // MARK: - CRUD State

    /// Whether a create/update operation is in progress
    @Published private(set) var isSaving = false

    /// Whether a delete operation is in progress
    @Published private(set) var isDeleting = false

    /// Whether a payment recording operation is in progress
    @Published private(set) var isRecordingPayment = false

    /// Success message to display after an operation
    @Published var successMessage: String?

    /// Whether to show the success alert
    @Published var showSuccess = false

    // MARK: - Statistics

    /// Total count of invoices matching the current filter
    @Published private(set) var totalCount = 0

    /// Finance summary data
    @Published private(set) var financeSummary: FinanceSummary?

    // MARK: - Computed Properties

    /// Whether the list is empty (after loading)
    var isEmpty: Bool {
        hasLoaded && invoices.isEmpty
    }

    /// Whether search is active
    var isSearching: Bool {
        !searchText.trimmingCharacters(in: .whitespaces).isEmpty
    }

    /// Whether any filter is active
    var hasActiveFilters: Bool {
        statusFilter != nil || familyFilter != nil || childFilter != nil ||
        startDateFilter != nil || endDateFilter != nil
    }

    /// Filtered invoices based on local search
    var filteredInvoices: [Invoice] {
        guard isSearching else { return invoices }

        let query = searchText.lowercased().trimmingCharacters(in: .whitespaces)
        return invoices.filter { invoice in
            invoice.number.lowercased().contains(query) ||
            invoice.familyName.lowercased().contains(query) ||
            (invoice.childName?.lowercased().contains(query) ?? false)
        }
    }

    /// Number of pending invoices in the current list
    var pendingCount: Int {
        invoices.filter { $0.status == .pending }.count
    }

    /// Number of paid invoices in the current list
    var paidCount: Int {
        invoices.filter { $0.status == .paid }.count
    }

    /// Number of overdue invoices in the current list
    var overdueCount: Int {
        invoices.filter { $0.status == .overdue }.count
    }

    /// Total outstanding balance for current invoices
    var totalOutstanding: Double {
        invoices.reduce(0) { $0 + $1.balanceDue }
    }

    /// Formatted total outstanding string
    var formattedTotalOutstanding: String {
        totalOutstanding.asCurrency
    }

    /// Number of selected invoices
    var selectedCount: Int {
        selectedInvoiceIds.count
    }

    /// Whether all visible invoices are selected
    var allSelected: Bool {
        !filteredInvoices.isEmpty && selectedInvoiceIds.count == filteredInvoices.count
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

    /// Creates a new InvoiceListViewModel
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

    /// Updates the pending sync count for invoice operations
    private func updatePendingSyncCount() {
        let pendingOps = realmManager.fetchPendingSyncOperations()
        pendingSyncCount = pendingOps.filter {
            $0.entityType == SyncEntityType.invoice.rawValue ||
            $0.entityType == SyncEntityType.payment.rawValue
        }.count
    }

    // MARK: - Public Methods

    /// Loads invoices from the API (or local cache if offline)
    /// - Parameter reset: Whether to reset pagination and reload from the beginning
    func loadInvoices(reset: Bool = false) async {
        if reset {
            currentOffset = 0
            invoices = []
        }

        // Don't load if already loading or if no more data
        guard !isLoading else { return }
        if !reset && !hasMoreData && hasLoaded { return }

        isLoading = true
        error = nil
        showError = false

        // Check if offline - load from local cache
        if isOffline {
            await loadInvoicesFromCache()
            isLoading = false
            return
        }

        do {
            let response = try await gibbonClient.fetchInvoices(
                status: statusFilter,
                familyId: familyFilter,
                childId: childFilter,
                startDate: startDateFilter,
                endDate: endDateFilter,
                skip: currentOffset,
                limit: pageSize
            )

            if reset {
                invoices = response.items
            } else {
                invoices.append(contentsOf: response.items)
            }

            totalCount = response.total
            hasMoreData = response.hasMore
            currentOffset += response.items.count
            hasLoaded = true

            // Cache fetched invoices locally for offline access
            await cacheInvoicesLocally(response.items)

            // Apply current sorting
            applySorting()

        } catch {
            // On network error, try loading from cache
            if isNetworkError(error) {
                isOffline = true
                await loadInvoicesFromCache()
            } else {
                self.error = error
                self.showError = true
            }
        }

        isLoading = false
    }

    /// Loads invoices from local cache
    private func loadInvoicesFromCache() async {
        isLoadingFromCache = true

        let cachedInvoices = realmManager.fetchInvoices(
            status: statusFilter,
            familyId: familyFilter,
            childId: childFilter,
            searchQuery: isSearching ? searchText : nil
        )

        invoices = cachedInvoices
        totalCount = cachedInvoices.count
        hasMoreData = false
        hasLoaded = true

        // Apply current sorting
        applySorting()

        isLoadingFromCache = false
    }

    /// Caches invoices to local storage for offline access
    private func cacheInvoicesLocally(_ invoicesToCache: [Invoice]) async {
        do {
            try await realmManager.saveInvoices(invoicesToCache)
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

    /// Refreshes the invoice list (reloads from the beginning)
    func refresh() async {
        await loadInvoices(reset: true)
    }

    /// Loads more invoices (pagination)
    func loadMore() async {
        guard hasMoreData && !isLoading else { return }
        await loadInvoices(reset: false)
    }

    /// Loads finance summary from the API
    func loadFinanceSummary() async {
        do {
            financeSummary = try await gibbonClient.fetchFinanceSummary()
        } catch {
            // Finance summary is optional, so we don't show an error
            financeSummary = nil
        }
    }

    /// Fetches a specific invoice by ID
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: The invoice details, or nil if not found
    func fetchInvoice(invoiceId: String) async -> Invoice? {
        do {
            let invoice = try await gibbonClient.fetchInvoice(invoiceId: invoiceId)
            return invoice
        } catch {
            self.error = error
            self.showError = true
            return nil
        }
    }

    /// Creates a new invoice
    /// - Parameter request: The invoice creation request
    /// - Returns: The created invoice, or nil if failed
    @discardableResult
    func createInvoice(_ request: InvoiceRequest) async -> Invoice? {
        isSaving = true
        error = nil

        // If offline, create locally and queue for sync
        if isOffline {
            return await createInvoiceOffline(request)
        }

        do {
            let invoice = try await gibbonClient.createInvoice(request)

            // Add to the beginning of the list
            invoices.insert(invoice, at: 0)
            totalCount += 1
            applySorting()

            // Cache locally
            await cacheInvoicesLocally([invoice])

            successMessage = String(localized: "Invoice created successfully")
            showSuccess = true

            isSaving = false
            return invoice

        } catch {
            // If network error, try creating offline
            if isNetworkError(error) {
                isOffline = true
                return await createInvoiceOffline(request)
            }
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Creates an invoice locally when offline and queues for sync
    private func createInvoiceOffline(_ request: InvoiceRequest) async -> Invoice? {
        // Create a temporary invoice with a local ID
        let localId = "local-\(UUID().uuidString)"
        let invoiceNumber = "DRAFT-\(Int.random(in: 10000...99999))"
        let subtotal = request.items?.reduce(0.0) { $0 + ($1.quantity * $1.unitPrice) } ?? 0
        let taxAmount = subtotal * 0.14975 // Quebec tax rate
        let totalAmount = subtotal + taxAmount

        let invoice = Invoice(
            id: localId,
            number: invoiceNumber,
            familyId: request.familyId,
            familyName: request.familyName ?? "",
            childId: request.childId,
            childName: request.childName,
            date: request.date,
            dueDate: request.dueDate,
            status: .draft,
            subtotal: subtotal,
            taxAmount: taxAmount,
            totalAmount: totalAmount,
            amountPaid: 0,
            items: request.items?.map { item in
                InvoiceItem(
                    id: UUID().uuidString,
                    description: item.description,
                    quantity: item.quantity,
                    unitPrice: item.unitPrice,
                    total: item.quantity * item.unitPrice,
                    category: item.category,
                    isQualifyingExpense: item.category.qualifiesForRL24
                )
            } ?? [],
            periodStartDate: request.periodStartDate,
            periodEndDate: request.periodEndDate,
            pdfUrl: nil,
            notes: request.notes,
            createdAt: Date(),
            updatedAt: Date()
        )

        do {
            // Save to local database and queue for sync
            try await realmManager.saveInvoice(invoice, queueSync: true)

            // Add to the beginning of the list
            invoices.insert(invoice, at: 0)
            totalCount += 1
            applySorting()

            updatePendingSyncCount()

            successMessage = String(localized: "Invoice saved locally. Will sync when online.")
            showSuccess = true

            isSaving = false
            return invoice

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Updates an existing invoice
    /// - Parameters:
    ///   - invoiceId: The invoice's unique identifier
    ///   - request: The invoice update request
    /// - Returns: The updated invoice, or nil if failed
    @discardableResult
    func updateInvoice(invoiceId: String, request: InvoiceRequest) async -> Invoice? {
        isSaving = true
        error = nil

        // If offline, update locally and queue for sync
        if isOffline {
            return await updateInvoiceOffline(invoiceId: invoiceId, request: request)
        }

        do {
            let updatedInvoice = try await gibbonClient.updateInvoice(invoiceId: invoiceId, request: request)

            // Update in the local list
            if let index = invoices.firstIndex(where: { $0.id == invoiceId }) {
                invoices[index] = updatedInvoice
            }

            // Update selected invoice if it's the same
            if selectedInvoice?.id == invoiceId {
                selectedInvoice = updatedInvoice
            }

            // Cache locally
            await cacheInvoicesLocally([updatedInvoice])

            successMessage = String(localized: "Invoice updated successfully")
            showSuccess = true

            isSaving = false
            return updatedInvoice

        } catch {
            // If network error, try updating offline
            if isNetworkError(error) {
                isOffline = true
                return await updateInvoiceOffline(invoiceId: invoiceId, request: request)
            }
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Updates an invoice locally when offline and queues for sync
    private func updateInvoiceOffline(invoiceId: String, request: InvoiceRequest) async -> Invoice? {
        // Get existing invoice data to preserve non-updated fields
        let existingInvoice = invoices.first { $0.id == invoiceId } ?? realmManager.fetchInvoice(id: invoiceId)

        let subtotal = request.items?.reduce(0.0) { $0 + ($1.quantity * $1.unitPrice) }
            ?? existingInvoice?.subtotal ?? 0
        let taxAmount = subtotal * 0.14975
        let totalAmount = subtotal + taxAmount

        let updatedInvoice = Invoice(
            id: invoiceId,
            number: existingInvoice?.number ?? "",
            familyId: request.familyId,
            familyName: request.familyName ?? existingInvoice?.familyName ?? "",
            childId: request.childId ?? existingInvoice?.childId,
            childName: request.childName ?? existingInvoice?.childName,
            date: request.date,
            dueDate: request.dueDate,
            status: request.status ?? existingInvoice?.status ?? .draft,
            subtotal: subtotal,
            taxAmount: taxAmount,
            totalAmount: totalAmount,
            amountPaid: existingInvoice?.amountPaid ?? 0,
            items: request.items?.map { item in
                InvoiceItem(
                    id: UUID().uuidString,
                    description: item.description,
                    quantity: item.quantity,
                    unitPrice: item.unitPrice,
                    total: item.quantity * item.unitPrice,
                    category: item.category,
                    isQualifyingExpense: item.category.qualifiesForRL24
                )
            } ?? existingInvoice?.items ?? [],
            periodStartDate: request.periodStartDate ?? existingInvoice?.periodStartDate,
            periodEndDate: request.periodEndDate ?? existingInvoice?.periodEndDate,
            pdfUrl: existingInvoice?.pdfUrl,
            notes: request.notes ?? existingInvoice?.notes,
            createdAt: existingInvoice?.createdAt ?? Date(),
            updatedAt: Date()
        )

        do {
            // Save to local database and queue for sync
            try await realmManager.saveInvoice(updatedInvoice, queueSync: true)

            // Update in the local list
            if let index = invoices.firstIndex(where: { $0.id == invoiceId }) {
                invoices[index] = updatedInvoice
            }

            // Update selected invoice if it's the same
            if selectedInvoice?.id == invoiceId {
                selectedInvoice = updatedInvoice
            }

            updatePendingSyncCount()

            successMessage = String(localized: "Invoice saved locally. Will sync when online.")
            showSuccess = true

            isSaving = false
            return updatedInvoice

        } catch {
            self.error = error
            self.showError = true
            isSaving = false
            return nil
        }
    }

    /// Deletes an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: Whether the deletion was successful
    @discardableResult
    func deleteInvoice(invoiceId: String) async -> Bool {
        isDeleting = true
        error = nil

        // If offline, delete locally and queue for sync
        if isOffline {
            return await deleteInvoiceOffline(invoiceId: invoiceId)
        }

        do {
            try await gibbonClient.deleteInvoice(invoiceId: invoiceId)

            // Remove from local list
            invoices.removeAll { $0.id == invoiceId }
            totalCount -= 1

            // Remove from local cache
            try? await realmManager.deleteInvoice(id: invoiceId, queueSync: false)

            // Clear selection if deleted invoice was selected
            selectedInvoiceIds.remove(invoiceId)
            if selectedInvoice?.id == invoiceId {
                selectedInvoice = nil
            }

            successMessage = String(localized: "Invoice deleted successfully")
            showSuccess = true

            isDeleting = false
            return true

        } catch {
            // If network error, try deleting offline
            if isNetworkError(error) {
                isOffline = true
                return await deleteInvoiceOffline(invoiceId: invoiceId)
            }
            self.error = error
            self.showError = true
            isDeleting = false
            return false
        }
    }

    /// Deletes an invoice locally when offline and queues for sync
    private func deleteInvoiceOffline(invoiceId: String) async -> Bool {
        do {
            // Delete from local database and queue for sync
            try await realmManager.deleteInvoice(id: invoiceId, queueSync: true)

            // Remove from local list
            invoices.removeAll { $0.id == invoiceId }
            totalCount -= 1

            // Clear selection if deleted invoice was selected
            selectedInvoiceIds.remove(invoiceId)
            if selectedInvoice?.id == invoiceId {
                selectedInvoice = nil
            }

            updatePendingSyncCount()

            successMessage = String(localized: "Invoice deleted locally. Will sync when online.")
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

    /// Deletes multiple invoices
    /// - Parameter invoiceIds: The invoice IDs to delete
    /// - Returns: Number of successfully deleted invoices
    @discardableResult
    func deleteInvoices(invoiceIds: Set<String>) async -> Int {
        isDeleting = true
        error = nil
        var deletedCount = 0

        for invoiceId in invoiceIds {
            // If offline, delete locally
            if isOffline {
                do {
                    try await realmManager.deleteInvoice(id: invoiceId, queueSync: true)
                    invoices.removeAll { $0.id == invoiceId }
                    totalCount -= 1
                    selectedInvoiceIds.remove(invoiceId)
                    deletedCount += 1
                } catch {
                    // Continue with other deletions even if one fails
                }
            } else {
                do {
                    try await gibbonClient.deleteInvoice(invoiceId: invoiceId)
                    invoices.removeAll { $0.id == invoiceId }
                    totalCount -= 1
                    selectedInvoiceIds.remove(invoiceId)
                    // Remove from local cache
                    try? await realmManager.deleteInvoice(id: invoiceId, queueSync: false)
                    deletedCount += 1
                } catch {
                    // If network error, try deleting offline
                    if isNetworkError(error) {
                        isOffline = true
                        do {
                            try await realmManager.deleteInvoice(id: invoiceId, queueSync: true)
                            invoices.removeAll { $0.id == invoiceId }
                            totalCount -= 1
                            selectedInvoiceIds.remove(invoiceId)
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
                ? String(localized: "\(deletedCount) invoices deleted locally. Will sync when online.")
                : String(localized: "\(deletedCount) invoices deleted successfully")
            successMessage = messageKey
            showSuccess = true

            if isOffline {
                updatePendingSyncCount()
            }
        }

        // Clear selected invoice if it was deleted
        if let selected = selectedInvoice, invoiceIds.contains(selected.id) {
            selectedInvoice = nil
        }

        isDeleting = false
        return deletedCount
    }

    // MARK: - Payment Methods

    /// Records a payment for an invoice
    /// - Parameter request: The payment request
    /// - Returns: The recorded payment, or nil if failed
    @discardableResult
    func recordPayment(_ request: PaymentRequest) async -> Payment? {
        isRecordingPayment = true
        error = nil

        // If offline, record locally and queue for sync
        if isOffline {
            return await recordPaymentOffline(request)
        }

        do {
            let payment = try await gibbonClient.recordPayment(request)

            // Refresh the invoice to get updated balance
            if let updatedInvoice = await fetchInvoice(invoiceId: request.invoiceId) {
                if let index = invoices.firstIndex(where: { $0.id == request.invoiceId }) {
                    invoices[index] = updatedInvoice
                }
                if selectedInvoice?.id == request.invoiceId {
                    selectedInvoice = updatedInvoice
                }

                // Cache the updated invoice
                await cacheInvoicesLocally([updatedInvoice])
            }

            successMessage = String(localized: "Payment recorded successfully")
            showSuccess = true

            isRecordingPayment = false
            return payment

        } catch {
            // If network error, try recording offline
            if isNetworkError(error) {
                isOffline = true
                return await recordPaymentOffline(request)
            }
            self.error = error
            self.showError = true
            isRecordingPayment = false
            return nil
        }
    }

    /// Records a payment locally when offline and queues for sync
    private func recordPaymentOffline(_ request: PaymentRequest) async -> Payment? {
        // Create a local payment
        let localId = "local-\(UUID().uuidString)"
        let payment = Payment(
            id: localId,
            invoiceId: request.invoiceId,
            invoiceNumber: nil,
            amount: request.amount,
            paymentDate: request.paymentDate,
            paymentMethod: request.paymentMethod,
            referenceNumber: request.referenceNumber,
            notes: request.notes,
            recordedById: nil,
            recordedByName: nil,
            createdAt: Date()
        )

        do {
            // Save payment to local database and queue for sync
            try await realmManager.savePayment(payment, queueSync: true)

            // Update the local invoice with the new payment
            if let index = invoices.firstIndex(where: { $0.id == request.invoiceId }) {
                var invoice = invoices[index]
                // Create an updated invoice with new amountPaid
                let updatedInvoice = Invoice(
                    id: invoice.id,
                    number: invoice.number,
                    familyId: invoice.familyId,
                    familyName: invoice.familyName,
                    childId: invoice.childId,
                    childName: invoice.childName,
                    date: invoice.date,
                    dueDate: invoice.dueDate,
                    status: invoice.amountPaid + request.amount >= invoice.totalAmount ? .paid : invoice.status,
                    subtotal: invoice.subtotal,
                    taxAmount: invoice.taxAmount,
                    totalAmount: invoice.totalAmount,
                    amountPaid: invoice.amountPaid + request.amount,
                    items: invoice.items,
                    periodStartDate: invoice.periodStartDate,
                    periodEndDate: invoice.periodEndDate,
                    pdfUrl: invoice.pdfUrl,
                    notes: invoice.notes,
                    createdAt: invoice.createdAt,
                    updatedAt: Date()
                )
                invoices[index] = updatedInvoice

                if selectedInvoice?.id == request.invoiceId {
                    selectedInvoice = updatedInvoice
                }

                // Save updated invoice to local database
                try await realmManager.saveInvoice(updatedInvoice, queueSync: false)
            }

            updatePendingSyncCount()

            successMessage = String(localized: "Payment saved locally. Will sync when online.")
            showSuccess = true

            isRecordingPayment = false
            return payment

        } catch {
            self.error = error
            self.showError = true
            isRecordingPayment = false
            return nil
        }
    }

    /// Fetches payments for an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: Array of payments for the invoice
    func fetchPayments(invoiceId: String) async -> [Payment] {
        do {
            return try await gibbonClient.fetchPayments(invoiceId: invoiceId)
        } catch {
            self.error = error
            self.showError = true
            return []
        }
    }

    // MARK: - Selection Methods

    /// Toggles selection for an invoice
    /// - Parameter invoiceId: The invoice's ID to toggle
    func toggleSelection(invoiceId: String) {
        if selectedInvoiceIds.contains(invoiceId) {
            selectedInvoiceIds.remove(invoiceId)
        } else {
            selectedInvoiceIds.insert(invoiceId)
        }
    }

    /// Selects all visible invoices
    func selectAll() {
        selectedInvoiceIds = Set(filteredInvoices.map { $0.id })
    }

    /// Deselects all invoices
    func deselectAll() {
        selectedInvoiceIds.removeAll()
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
        familyFilter = nil
        childFilter = nil
        startDateFilter = nil
        endDateFilter = nil
        searchText = ""
    }

    /// Sets the status filter and reloads
    /// - Parameter status: The invoice status to filter by
    func filterByStatus(_ status: InvoiceStatus?) {
        statusFilter = status
    }

    /// Sets the family filter and reloads
    /// - Parameter familyId: The family ID to filter by
    func filterByFamily(_ familyId: String?) {
        familyFilter = familyId
    }

    /// Sets the child filter and reloads
    /// - Parameter childId: The child ID to filter by
    func filterByChild(_ childId: String?) {
        childFilter = childId
    }

    /// Sets the date range filter and reloads
    /// - Parameters:
    ///   - startDate: The start date for the range
    ///   - endDate: The end date for the range
    func filterByDateRange(startDate: Date?, endDate: Date?) {
        // Set both dates without triggering double reload
        let previousStartDate = startDateFilter
        startDateFilter = startDate
        if previousStartDate == nil && endDate != nil {
            endDateFilter = endDate
        } else {
            endDateFilter = endDate
        }
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

    /// Gets an invoice by ID from the local cache
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: The invoice, or nil if not in cache
    func getInvoice(byId invoiceId: String) -> Invoice? {
        invoices.first { $0.id == invoiceId }
    }

    /// Gets overdue invoices from the local cache
    /// - Returns: Array of overdue invoices
    func getOverdueInvoices() -> [Invoice] {
        invoices.filter { $0.status == .overdue }
    }

    /// Gets invoices for a specific family from the local cache
    /// - Parameter familyId: The family's unique identifier
    /// - Returns: Array of invoices for the family
    func getInvoices(forFamilyId familyId: String) -> [Invoice] {
        invoices.filter { $0.familyId == familyId }
    }

    /// Gets the PDF URL for an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: The URL to download the invoice PDF
    func getInvoicePdfUrl(invoiceId: String) -> URL? {
        gibbonClient.getInvoicePdfUrl(invoiceId: invoiceId)
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
                    // If server-side search is needed, call loadInvoices(reset: true) here
                    self?.objectWillChange.send()
                }
            }
            .store(in: &cancellables)
    }

    /// Applies current sorting to the invoices array
    private func applySorting() {
        switch sortOrder {
        case .dateAsc:
            invoices.sort { $0.date < $1.date }
        case .dateDesc:
            invoices.sort { $0.date > $1.date }
        case .dueDateAsc:
            invoices.sort { $0.dueDate < $1.dueDate }
        case .dueDateDesc:
            invoices.sort { $0.dueDate > $1.dueDate }
        case .numberAsc:
            invoices.sort { $0.number.localizedCompare($1.number) == .orderedAscending }
        case .numberDesc:
            invoices.sort { $0.number.localizedCompare($1.number) == .orderedDescending }
        case .familyAsc:
            invoices.sort { $0.familyName.localizedCompare($1.familyName) == .orderedAscending }
        case .familyDesc:
            invoices.sort { $0.familyName.localizedCompare($1.familyName) == .orderedDescending }
        case .amountAsc:
            invoices.sort { $0.totalAmount < $1.totalAmount }
        case .amountDesc:
            invoices.sort { $0.totalAmount > $1.totalAmount }
        case .statusAsc:
            invoices.sort { $0.statusDisplayName.localizedCompare($1.statusDisplayName) == .orderedAscending }
        }
    }
}

// MARK: - Invoice Sort Order

/// Sort order options for the invoice list
enum InvoiceSortOrder: String, CaseIterable, Identifiable {
    case dateAsc = "date_asc"
    case dateDesc = "date_desc"
    case dueDateAsc = "due_date_asc"
    case dueDateDesc = "due_date_desc"
    case numberAsc = "number_asc"
    case numberDesc = "number_desc"
    case familyAsc = "family_asc"
    case familyDesc = "family_desc"
    case amountAsc = "amount_asc"
    case amountDesc = "amount_desc"
    case statusAsc = "status_asc"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .dateAsc:
            return String(localized: "Invoice Date (Oldest first)")
        case .dateDesc:
            return String(localized: "Invoice Date (Newest first)")
        case .dueDateAsc:
            return String(localized: "Due Date (Earliest first)")
        case .dueDateDesc:
            return String(localized: "Due Date (Latest first)")
        case .numberAsc:
            return String(localized: "Invoice Number (A-Z)")
        case .numberDesc:
            return String(localized: "Invoice Number (Z-A)")
        case .familyAsc:
            return String(localized: "Family Name (A-Z)")
        case .familyDesc:
            return String(localized: "Family Name (Z-A)")
        case .amountAsc:
            return String(localized: "Amount (Low to High)")
        case .amountDesc:
            return String(localized: "Amount (High to Low)")
        case .statusAsc:
            return String(localized: "Status (A-Z)")
        }
    }

    var systemImage: String {
        switch self {
        case .dateAsc, .dueDateAsc, .numberAsc, .familyAsc, .amountAsc, .statusAsc:
            return "arrow.up"
        case .dateDesc, .dueDateDesc, .numberDesc, .familyDesc, .amountDesc:
            return "arrow.down"
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension InvoiceListViewModel {

    /// Creates a mock ViewModel with sample data for previews
    static var preview: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview, .previewPaid, .previewOverdue]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        viewModel.financeSummary = .preview
        return viewModel
    }

    /// Creates a mock ViewModel in loading state for previews
    static var previewLoading: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.isLoading = true
        return viewModel
    }

    /// Creates a mock ViewModel with error state for previews
    static var previewError: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.error = APIError.serverError(statusCode: 500, message: "Internal Server Error")
        viewModel.showError = true
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with empty state for previews
    static var previewEmpty: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = []
        viewModel.totalCount = 0
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel with search active for previews
    static var previewSearching: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview, .previewPaid, .previewOverdue]
        viewModel.searchText = "Tremblay"
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with filter active for previews
    static var previewFiltered: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview]
        viewModel.statusFilter = .pending
        viewModel.totalCount = 1
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel filtered by overdue status for previews
    static var previewOverdueFilter: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.previewOverdue]
        viewModel.statusFilter = .overdue
        viewModel.totalCount = 1
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with many invoices for testing pagination
    static var previewManyInvoices: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        var invoicesList: [Invoice] = []
        let statuses: [InvoiceStatus] = [.pending, .paid, .overdue, .paid, .pending]
        let families = ["Tremblay Family", "Gagnon Family", "Lavoie Family", "Bouchard Family"]

        for i in 1...50 {
            let date = Calendar.current.date(byAdding: .day, value: -i * 3, to: Date()) ?? Date()
            let dueDate = Calendar.current.date(byAdding: .day, value: 30, to: date) ?? Date()
            let status = statuses[i % statuses.count]
            let subtotal = Double(500 + i * 25)
            let taxAmount = subtotal * 0.14975
            let totalAmount = subtotal + taxAmount
            let amountPaid = status == .paid ? totalAmount : (status == .overdue ? totalAmount * 0.3 : 0)

            invoicesList.append(Invoice(
                id: "invoice-\(i)",
                number: "INV-2026-\(String(format: "%04d", i))",
                familyId: "family-\(i % 4 + 1)",
                familyName: families[i % families.count],
                childId: "child-\(i)",
                childName: "Child \(i)",
                date: date,
                dueDate: dueDate,
                status: status,
                subtotal: subtotal,
                taxAmount: taxAmount,
                totalAmount: totalAmount,
                amountPaid: amountPaid,
                items: [InvoiceItem.preview],
                periodStartDate: Calendar.current.date(byAdding: .month, value: -1, to: date),
                periodEndDate: date,
                pdfUrl: nil,
                notes: nil,
                createdAt: date,
                updatedAt: date
            ))
        }
        viewModel.invoices = invoicesList
        viewModel.totalCount = 100
        viewModel.hasLoaded = true
        viewModel.hasMoreData = true
        return viewModel
    }

    /// Creates a mock ViewModel with selections for previews
    static var previewWithSelections: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview, .previewPaid, .previewOverdue]
        viewModel.selectedInvoiceIds = Set([Invoice.preview.id, Invoice.previewOverdue.id])
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        return viewModel
    }

    /// Creates a mock ViewModel with finance summary for previews
    static var previewWithSummary: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview, .previewPaid, .previewOverdue]
        viewModel.totalCount = 3
        viewModel.hasLoaded = true
        viewModel.financeSummary = FinanceSummary(
            monthlyRevenue: 45250.00,
            totalOutstanding: 8750.00,
            overdueCount: 3,
            overdueAmount: 2875.00,
            pendingCount: 15,
            pendingAmount: 5875.00,
            collectionRate: 0.92
        )
        return viewModel
    }

    /// Creates a mock ViewModel in offline state for previews
    static var previewOffline: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.invoices = [.preview, .previewPaid]
        viewModel.isOffline = true
        viewModel.pendingSyncCount = 4
        viewModel.totalCount = 2
        viewModel.hasLoaded = true
        viewModel.hasMoreData = false
        return viewModel
    }

    /// Creates a mock ViewModel loading from cache for previews
    static var previewLoadingFromCache: InvoiceListViewModel {
        let viewModel = InvoiceListViewModel()
        viewModel.isOffline = true
        viewModel.isLoadingFromCache = true
        return viewModel
    }
}
#endif

// MARK: - Notification Names

extension Notification.Name {

    /// Posted when the invoice list is refreshed
    static let invoiceListRefreshed = Notification.Name("invoiceListRefreshed")

    /// Posted when an invoice is created
    static let invoiceCreated = Notification.Name("invoiceCreated")

    /// Posted when an invoice is updated
    static let invoiceUpdated = Notification.Name("invoiceUpdated")

    /// Posted when an invoice is deleted
    static let invoiceDeleted = Notification.Name("invoiceDeleted")

    /// Posted when a payment is recorded
    static let paymentRecorded = Notification.Name("paymentRecorded")
}
