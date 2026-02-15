//
//  RealmManager.swift
//  LAYAAdmin
//
//  Manages Realm database operations for offline data persistence.
//  Provides CRUD operations, sync support, and cache management.
//

import Foundation
import RealmSwift
import Combine

// MARK: - Realm Manager

/// Singleton manager for Realm database operations.
/// Handles local data persistence and sync queue management.
@MainActor
final class RealmManager: ObservableObject {

    // MARK: - Singleton

    /// Shared instance for app-wide database access
    static let shared = RealmManager()

    // MARK: - Published Properties

    /// Number of pending sync operations
    @Published private(set) var pendingSyncCount: Int = 0

    /// Whether the database is ready
    @Published private(set) var isReady: Bool = false

    /// Last sync timestamp
    @Published private(set) var lastSyncAt: Date?

    // MARK: - Private Properties

    private var realm: Realm?
    private var notificationTokens: [NotificationToken] = []
    private var cancellables = Set<AnyCancellable>()

    // MARK: - Configuration

    /// Realm configuration with migration support
    private static var realmConfig: Realm.Configuration {
        var config = Realm.Configuration(
            schemaVersion: 1,
            migrationBlock: { migration, oldSchemaVersion in
                // Handle migrations between schema versions
                if oldSchemaVersion < 1 {
                    // Initial schema - no migration needed
                }
            }
        )

        // Set the file URL to the app's documents directory
        let fileURL = FileManager.default
            .urls(for: .documentDirectory, in: .userDomainMask)
            .first?
            .appendingPathComponent("LAYAAdmin.realm")

        config.fileURL = fileURL

        return config
    }

    // MARK: - Initialization

    private init() {
        Task {
            await initialize()
        }
    }

    /// Initializes the Realm database
    func initialize() async {
        do {
            Realm.Configuration.defaultConfiguration = Self.realmConfig

            realm = try await Realm(configuration: Self.realmConfig, actor: MainActor.shared)
            isReady = true

            // Load initial state
            await loadSyncMetadata()
            await updatePendingSyncCount()

            // Set up observers
            setupObservers()

        } catch {
            isReady = false
        }
    }

    // MARK: - Database Access

    /// Returns the Realm instance for direct access (advanced use only)
    var database: Realm? {
        return realm
    }

    /// Performs a write transaction
    func write(_ block: (Realm) throws -> Void) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try realm.write {
            try block(realm)
        }
    }

    // MARK: - Child Operations

    /// Fetches all children from the local database
    func fetchChildren(
        status: EnrollmentStatus? = nil,
        classroomId: String? = nil,
        searchQuery: String? = nil
    ) -> [Child] {
        guard let realm = realm else { return [] }

        var results = realm.objects(ChildObject.self)

        if let status = status {
            results = results.filter("enrollmentStatusRaw == %@", status.rawValue)
        }

        if let classroomId = classroomId {
            results = results.filter("classroomId == %@", classroomId)
        }

        if let query = searchQuery, !query.isEmpty {
            results = results.filter(
                "firstName CONTAINS[c] %@ OR lastName CONTAINS[c] %@",
                query, query
            )
        }

        return results.map { $0.toModel() }
    }

    /// Fetches a single child by ID
    func fetchChild(id: String) -> Child? {
        guard let realm = realm else { return nil }
        return realm.object(ofType: ChildObject.self, forPrimaryKey: id)?.toModel()
    }

    /// Saves or updates a child in the local database
    func saveChild(_ child: Child, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let existing = realm.object(ofType: ChildObject.self, forPrimaryKey: child.id) {
                existing.update(from: child)
                existing.hasPendingChanges = queueSync
            } else {
                let object = ChildObject.from(child)
                object.isLocalOnly = queueSync
                object.hasPendingChanges = queueSync
                realm.add(object, update: .modified)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forChild(child, operation: .update))
        }
    }

    /// Saves multiple children (for bulk sync from server)
    func saveChildren(_ children: [Child]) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            for child in children {
                let object = ChildObject.from(child)
                realm.add(object, update: .modified)
            }
        }
    }

    /// Deletes a child from the local database
    func deleteChild(id: String, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        guard let child = fetchChild(id: id) else {
            throw RealmManagerError.notFound
        }

        try await write { realm in
            if let object = realm.object(ofType: ChildObject.self, forPrimaryKey: id) {
                realm.delete(object)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forChild(child, operation: .delete))
        }
    }

    // MARK: - Staff Operations

    /// Fetches all staff from the local database
    func fetchStaff(
        status: StaffStatus? = nil,
        role: StaffRole? = nil,
        classroomId: String? = nil,
        searchQuery: String? = nil
    ) -> [Staff] {
        guard let realm = realm else { return [] }

        var results = realm.objects(StaffObject.self)

        if let status = status {
            results = results.filter("statusRaw == %@", status.rawValue)
        }

        if let role = role {
            results = results.filter("roleRaw == %@", role.rawValue)
        }

        if let classroomId = classroomId {
            results = results.filter("assignedClassroomId == %@", classroomId)
        }

        if let query = searchQuery, !query.isEmpty {
            results = results.filter(
                "firstName CONTAINS[c] %@ OR lastName CONTAINS[c] %@ OR email CONTAINS[c] %@",
                query, query, query
            )
        }

        return results.map { $0.toModel() }
    }

    /// Fetches a single staff member by ID
    func fetchStaffMember(id: String) -> Staff? {
        guard let realm = realm else { return nil }
        return realm.object(ofType: StaffObject.self, forPrimaryKey: id)?.toModel()
    }

    /// Saves or updates a staff member in the local database
    func saveStaff(_ staff: Staff, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let existing = realm.object(ofType: StaffObject.self, forPrimaryKey: staff.id) {
                existing.update(from: staff)
                existing.hasPendingChanges = queueSync
            } else {
                let object = StaffObject.from(staff)
                object.isLocalOnly = queueSync
                object.hasPendingChanges = queueSync
                realm.add(object, update: .modified)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forStaff(staff, operation: .update))
        }
    }

    /// Saves multiple staff members (for bulk sync from server)
    func saveStaffMembers(_ staffMembers: [Staff]) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            for staff in staffMembers {
                let object = StaffObject.from(staff)
                realm.add(object, update: .modified)
            }
        }
    }

    /// Deletes a staff member from the local database
    func deleteStaff(id: String, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        guard let staff = fetchStaffMember(id: id) else {
            throw RealmManagerError.notFound
        }

        try await write { realm in
            if let object = realm.object(ofType: StaffObject.self, forPrimaryKey: id) {
                realm.delete(object)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forStaff(staff, operation: .delete))
        }
    }

    // MARK: - Invoice Operations

    /// Fetches all invoices from the local database
    func fetchInvoices(
        status: InvoiceStatus? = nil,
        familyId: String? = nil,
        childId: String? = nil,
        searchQuery: String? = nil
    ) -> [Invoice] {
        guard let realm = realm else { return [] }

        var results = realm.objects(InvoiceObject.self)

        if let status = status {
            results = results.filter("statusRaw == %@", status.rawValue)
        }

        if let familyId = familyId {
            results = results.filter("familyId == %@", familyId)
        }

        if let childId = childId {
            results = results.filter("childId == %@", childId)
        }

        if let query = searchQuery, !query.isEmpty {
            results = results.filter(
                "number CONTAINS[c] %@ OR familyName CONTAINS[c] %@ OR childName CONTAINS[c] %@",
                query, query, query
            )
        }

        return results.sorted(byKeyPath: "date", ascending: false).map { $0.toModel() }
    }

    /// Fetches a single invoice by ID
    func fetchInvoice(id: String) -> Invoice? {
        guard let realm = realm else { return nil }
        return realm.object(ofType: InvoiceObject.self, forPrimaryKey: id)?.toModel()
    }

    /// Saves or updates an invoice in the local database
    func saveInvoice(_ invoice: Invoice, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let existing = realm.object(ofType: InvoiceObject.self, forPrimaryKey: invoice.id) {
                existing.update(from: invoice)
                existing.hasPendingChanges = queueSync
            } else {
                let object = InvoiceObject.from(invoice)
                object.isLocalOnly = queueSync
                object.hasPendingChanges = queueSync
                realm.add(object, update: .modified)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forInvoice(invoice, operation: .update))
        }
    }

    /// Saves multiple invoices (for bulk sync from server)
    func saveInvoices(_ invoices: [Invoice]) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            for invoice in invoices {
                let object = InvoiceObject.from(invoice)
                realm.add(object, update: .modified)
            }
        }
    }

    /// Deletes an invoice from the local database
    func deleteInvoice(id: String, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        guard let invoice = fetchInvoice(id: id) else {
            throw RealmManagerError.notFound
        }

        try await write { realm in
            if let object = realm.object(ofType: InvoiceObject.self, forPrimaryKey: id) {
                realm.delete(object)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forInvoice(invoice, operation: .delete))
        }
    }

    // MARK: - Payment Operations

    /// Fetches payments for an invoice
    func fetchPayments(invoiceId: String) -> [Payment] {
        guard let realm = realm else { return [] }

        return realm.objects(PaymentObject.self)
            .filter("invoiceId == %@", invoiceId)
            .sorted(byKeyPath: "paymentDate", ascending: false)
            .map { $0.toModel() }
    }

    /// Fetches a single payment by ID
    func fetchPayment(id: String) -> Payment? {
        guard let realm = realm else { return nil }
        return realm.object(ofType: PaymentObject.self, forPrimaryKey: id)?.toModel()
    }

    /// Saves or updates a payment in the local database
    func savePayment(_ payment: Payment, queueSync: Bool = true) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let existing = realm.object(ofType: PaymentObject.self, forPrimaryKey: payment.id) {
                existing.update(from: payment)
                existing.hasPendingChanges = queueSync
            } else {
                let object = PaymentObject.from(payment)
                object.isLocalOnly = queueSync
                object.hasPendingChanges = queueSync
                realm.add(object, update: .modified)
            }
        }

        if queueSync {
            try await queueSyncOperation(.forPayment(payment, operation: .update))
        }
    }

    /// Saves multiple payments (for bulk sync from server)
    func savePayments(_ payments: [Payment]) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            for payment in payments {
                let object = PaymentObject.from(payment)
                realm.add(object, update: .modified)
            }
        }
    }

    // MARK: - Sync Queue Operations

    /// Adds an operation to the sync queue
    func queueSyncOperation(_ item: SyncQueueItem) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            realm.add(item)
        }

        await updatePendingSyncCount()
    }

    /// Fetches all pending sync operations
    func fetchPendingSyncOperations() -> [SyncQueueItem] {
        guard let realm = realm else { return [] }

        return Array(
            realm.objects(SyncQueueItem.self)
                .filter("isProcessed == false")
                .sorted(byKeyPath: "createdAt", ascending: true)
        )
    }

    /// Marks a sync operation as processed
    func markSyncOperationProcessed(id: String) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let item = realm.object(ofType: SyncQueueItem.self, forPrimaryKey: id) {
                item.isProcessed = true
            }
        }

        await updatePendingSyncCount()
    }

    /// Records a sync error for an operation
    func recordSyncError(id: String, error: String) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            if let item = realm.object(ofType: SyncQueueItem.self, forPrimaryKey: id) {
                item.attemptCount += 1
                item.lastError = error
            }
        }
    }

    /// Clears processed sync operations
    func clearProcessedSyncOperations() async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            let processed = realm.objects(SyncQueueItem.self)
                .filter("isProcessed == true")
            realm.delete(processed)
        }
    }

    // MARK: - Sync Metadata Operations

    /// Updates the sync metadata
    func updateSyncMetadata(
        lastSyncAt: Date? = nil,
        isSyncing: Bool? = nil,
        lastSyncError: String? = nil
    ) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            let metadata = realm.object(ofType: SyncMetadata.self, forPrimaryKey: "sync_metadata")
                ?? SyncMetadata()

            if let lastSyncAt = lastSyncAt {
                metadata.lastSyncAt = lastSyncAt
            }

            if let isSyncing = isSyncing {
                metadata.isSyncing = isSyncing
            }

            if let lastSyncError = lastSyncError {
                metadata.lastSyncError = lastSyncError
            }

            metadata.lastSyncAttemptAt = Date()
            metadata.pendingOperationsCount = fetchPendingSyncOperations().count

            realm.add(metadata, update: .modified)
        }

        await loadSyncMetadata()
    }

    /// Loads sync metadata into published properties
    private func loadSyncMetadata() async {
        guard let realm = realm else { return }

        if let metadata = realm.object(ofType: SyncMetadata.self, forPrimaryKey: "sync_metadata") {
            lastSyncAt = metadata.lastSyncAt
        }
    }

    /// Updates the pending sync count
    private func updatePendingSyncCount() async {
        pendingSyncCount = fetchPendingSyncOperations().count
    }

    // MARK: - Cache Operations

    /// Caches dashboard data
    func cacheDashboard(_ json: String, expiresIn: TimeInterval = AppConstants.cacheExpiryTime) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            let cache = realm.object(ofType: CachedDashboardObject.self, forPrimaryKey: "dashboard_cache")
                ?? CachedDashboardObject()

            cache.dashboardJson = json
            cache.cachedAt = Date()
            cache.expiresAt = Date().addingTimeInterval(expiresIn)

            realm.add(cache, update: .modified)
        }
    }

    /// Retrieves cached dashboard data if not expired
    func getCachedDashboard() -> String? {
        guard let realm = realm else { return nil }

        guard let cache = realm.object(ofType: CachedDashboardObject.self, forPrimaryKey: "dashboard_cache"),
              !cache.isExpired else {
            return nil
        }

        return cache.dashboardJson
    }

    /// Clears all cached data
    func clearCache() async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            let caches = realm.objects(CachedDashboardObject.self)
            realm.delete(caches)
        }
    }

    // MARK: - User Preferences

    /// Saves user preferences
    func saveUserPreferences(_ preferences: UserPreferencesObject) async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            realm.add(preferences, update: .modified)
        }
    }

    /// Fetches user preferences
    func getUserPreferences(userId: String) -> UserPreferencesObject? {
        guard let realm = realm else { return nil }
        return realm.object(ofType: UserPreferencesObject.self, forPrimaryKey: userId)
    }

    // MARK: - Data Management

    /// Clears all local data (for logout)
    func clearAllData() async throws {
        guard let realm = realm else {
            throw RealmManagerError.notInitialized
        }

        try await write { realm in
            realm.deleteAll()
        }

        pendingSyncCount = 0
        lastSyncAt = nil
    }

    /// Returns statistics about local data
    func getDataStatistics() -> DataStatistics {
        guard let realm = realm else {
            return DataStatistics()
        }

        return DataStatistics(
            childCount: realm.objects(ChildObject.self).count,
            staffCount: realm.objects(StaffObject.self).count,
            invoiceCount: realm.objects(InvoiceObject.self).count,
            paymentCount: realm.objects(PaymentObject.self).count,
            pendingSyncCount: fetchPendingSyncOperations().count,
            lastSyncAt: realm.object(ofType: SyncMetadata.self, forPrimaryKey: "sync_metadata")?.lastSyncAt
        )
    }

    // MARK: - Observers

    private func setupObservers() {
        guard let realm = realm else { return }

        // Observe sync queue changes
        let syncQueueToken = realm.objects(SyncQueueItem.self)
            .observe { [weak self] _ in
                Task { @MainActor in
                    await self?.updatePendingSyncCount()
                }
            }

        notificationTokens.append(syncQueueToken)
    }

    deinit {
        notificationTokens.forEach { $0.invalidate() }
    }
}

// MARK: - Realm Manager Error

/// Errors that can occur during Realm operations
enum RealmManagerError: LocalizedError {
    case notInitialized
    case notFound
    case writeFailed
    case migrationFailed

    var errorDescription: String? {
        switch self {
        case .notInitialized:
            return String(localized: "Database not initialized")
        case .notFound:
            return String(localized: "Record not found")
        case .writeFailed:
            return String(localized: "Failed to write to database")
        case .migrationFailed:
            return String(localized: "Database migration failed")
        }
    }
}

// MARK: - Data Statistics

/// Statistics about locally stored data
struct DataStatistics {
    var childCount: Int = 0
    var staffCount: Int = 0
    var invoiceCount: Int = 0
    var paymentCount: Int = 0
    var pendingSyncCount: Int = 0
    var lastSyncAt: Date?

    var totalRecordCount: Int {
        childCount + staffCount + invoiceCount + paymentCount
    }
}

// MARK: - Preview Support

#if DEBUG
extension RealmManager {

    /// Creates a preview instance with mock data
    static var preview: RealmManager {
        let manager = RealmManager.shared
        return manager
    }
}
#endif
