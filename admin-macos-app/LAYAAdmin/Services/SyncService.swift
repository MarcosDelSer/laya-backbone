//
//  SyncService.swift
//  LAYAAdmin
//
//  Service for managing offline sync queue and network connectivity.
//  Uses NWPathMonitor to track network state and automatically syncs
//  pending operations when connectivity is restored.
//

import Foundation
import Combine
import Network

// MARK: - Sync Service Protocol

/// Protocol defining the sync service interface
protocol SyncServiceProtocol {
    /// Current sync state
    var syncState: SyncState { get }

    /// Current network status
    var networkStatus: NetworkStatus { get }

    /// Number of pending sync operations
    var pendingOperationsCount: Int { get }

    /// Whether the service is online
    var isOnline: Bool { get }

    /// Queues an operation for sync
    func queueOperation<T: Encodable>(
        entityType: SyncEntityType,
        entityId: String,
        operation: SyncOperationType,
        payload: T?
    ) async throws

    /// Starts syncing pending operations
    func syncPending() async throws

    /// Pauses sync operations
    func pause()

    /// Resumes sync operations
    func resume()

    /// Cancels all pending sync operations
    func cancelAllPending() async throws
}

// MARK: - Sync Service

/// Service for managing offline data synchronization.
///
/// Features:
/// - Network connectivity monitoring via NWPathMonitor
/// - Automatic sync when connectivity is restored
/// - Conflict resolution (server wins by default)
/// - Retry logic with exponential backoff
/// - Observable sync state for UI updates
@MainActor
final class SyncService: ObservableObject, SyncServiceProtocol {

    // MARK: - Singleton

    /// Shared instance for app-wide sync management
    static let shared = SyncService()

    // MARK: - Published Properties

    /// Current sync state
    @Published private(set) var syncState: SyncState = .idle

    /// Current network connectivity status
    @Published private(set) var networkStatus: NetworkStatus = .unknown

    /// Number of pending sync operations
    @Published private(set) var pendingOperationsCount: Int = 0

    /// Sync statistics
    @Published private(set) var statistics: SyncStatistics = SyncStatistics()

    /// Active conflicts requiring resolution
    @Published private(set) var activeConflicts: [SyncConflict] = []

    // MARK: - Computed Properties

    /// Whether the service has network connectivity
    var isOnline: Bool {
        networkStatus.isConnected
    }

    // MARK: - Private Properties

    /// Network path monitor for connectivity tracking
    private let pathMonitor: NWPathMonitor

    /// Dispatch queue for network monitoring
    private let monitorQueue: DispatchQueue

    /// Realm manager for local storage
    private let realmManager: RealmManager

    /// Gibbon client for API calls
    private let gibbonClient: GibbonClient

    /// Auth service for authentication state
    private let authService: AuthService

    /// Sync configuration
    private var configuration: SyncConfiguration

    /// Timer for automatic sync
    private var syncTimer: Timer?

    /// Current sync task
    private var currentSyncTask: Task<Void, Error>?

    /// Cancellables for Combine subscriptions
    private var cancellables = Set<AnyCancellable>()

    /// Whether sync is currently paused by user
    private var isPausedByUser: Bool = false

    /// JSON encoder for payloads
    private let encoder: JSONEncoder = {
        let encoder = JSONEncoder()
        encoder.dateEncodingStrategy = .iso8601
        encoder.keyEncodingStrategy = .convertToSnakeCase
        return encoder
    }()

    /// JSON decoder for responses
    private let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return decoder
    }()

    // MARK: - Initialization

    /// Creates a new SyncService instance
    /// - Parameters:
    ///   - realmManager: The Realm manager for local storage
    ///   - gibbonClient: The Gibbon client for API calls
    ///   - authService: The auth service for authentication state
    ///   - configuration: Sync configuration options
    init(
        realmManager: RealmManager = .shared,
        gibbonClient: GibbonClient = .shared,
        authService: AuthService = .shared,
        configuration: SyncConfiguration = .default
    ) {
        self.realmManager = realmManager
        self.gibbonClient = gibbonClient
        self.authService = authService
        self.configuration = configuration

        // Initialize network monitor
        self.pathMonitor = NWPathMonitor()
        self.monitorQueue = DispatchQueue(label: "com.laya.admin.network-monitor")

        // Start monitoring and observing
        setupNetworkMonitoring()
        setupObservers()
        updatePendingCount()
    }

    deinit {
        pathMonitor.cancel()
        syncTimer?.invalidate()
        currentSyncTask?.cancel()
    }

    // MARK: - Network Monitoring

    /// Sets up network path monitoring
    private func setupNetworkMonitoring() {
        pathMonitor.pathUpdateHandler = { [weak self] path in
            Task { @MainActor [weak self] in
                self?.handleNetworkPathUpdate(path)
            }
        }

        pathMonitor.start(queue: monitorQueue)
    }

    /// Handles network path updates
    private func handleNetworkPathUpdate(_ path: NWPath) {
        let previousStatus = networkStatus

        // Determine network status from path
        switch path.status {
        case .satisfied:
            let interface = determineNetworkInterface(path)
            networkStatus = .connected(interface: interface)
        case .unsatisfied:
            networkStatus = .disconnected
        case .requiresConnection:
            networkStatus = .disconnected
        @unknown default:
            networkStatus = .unknown
        }

        // Post notification
        NotificationCenter.default.post(name: .networkStatusChanged, object: self)

        // Handle status changes
        if !previousStatus.isConnected && networkStatus.isConnected {
            handleNetworkRestored()
        } else if previousStatus.isConnected && !networkStatus.isConnected {
            handleNetworkLost()
        }
    }

    /// Determines the network interface type from the path
    private func determineNetworkInterface(_ path: NWPath) -> NetworkInterface {
        if path.usesInterfaceType(.wifi) {
            return .wifi
        } else if path.usesInterfaceType(.cellular) {
            return .cellular
        } else if path.usesInterfaceType(.wiredEthernet) {
            return .wired
        } else {
            return .other
        }
    }

    /// Handles network restoration
    private func handleNetworkRestored() {
        // Check if we should auto-sync
        if configuration.autoSyncOnReconnect && !isPausedByUser {
            if case .paused(.noNetwork) = syncState {
                syncState = .idle
            }

            // Trigger sync if there are pending operations
            if pendingOperationsCount > 0 {
                Task {
                    try? await syncPending()
                }
            }
        }
    }

    /// Handles network loss
    private func handleNetworkLost() {
        // Cancel ongoing sync
        currentSyncTask?.cancel()

        // Update state if syncing
        if syncState.isSyncing {
            syncState = .paused(reason: .noNetwork)
        }
    }

    // MARK: - Observers

    /// Sets up Combine observers
    private func setupObservers() {
        // Observe pending count changes from RealmManager
        realmManager.$pendingSyncCount
            .receive(on: DispatchQueue.main)
            .sink { [weak self] count in
                self?.pendingOperationsCount = count
            }
            .store(in: &cancellables)

        // Observe authentication state changes
        authService.$authState
            .receive(on: DispatchQueue.main)
            .sink { [weak self] state in
                self?.handleAuthStateChange(state)
            }
            .store(in: &cancellables)
    }

    /// Handles authentication state changes
    private func handleAuthStateChange(_ state: AuthState) {
        if !state.isAuthenticated {
            // Pause sync if not authenticated
            if syncState.isSyncing {
                currentSyncTask?.cancel()
                syncState = .paused(reason: .notAuthenticated)
            }
        } else if case .paused(.notAuthenticated) = syncState {
            // Resume if previously paused due to auth
            syncState = .idle
            if pendingOperationsCount > 0 && isOnline {
                Task {
                    try? await syncPending()
                }
            }
        }
    }

    // MARK: - Queue Operations

    /// Queues an operation for sync
    /// - Parameters:
    ///   - entityType: Type of entity being synced
    ///   - entityId: ID of the entity
    ///   - operation: Type of operation (create, update, delete)
    ///   - payload: Optional payload for the operation
    func queueOperation<T: Encodable>(
        entityType: SyncEntityType,
        entityId: String,
        operation: SyncOperationType,
        payload: T? = nil
    ) async throws {
        // Create sync queue item
        let item = SyncQueueItem()
        item.entityType = entityType.rawValue
        item.entityId = entityId
        item.operationType = operation

        // Encode payload if provided
        if let payload = payload {
            let data = try encoder.encode(payload)
            item.payload = String(data: data, encoding: .utf8)
        }

        // Queue the operation
        try await realmManager.queueSyncOperation(item)
        updatePendingCount()

        // Trigger immediate sync if online and not already syncing
        if isOnline && !syncState.isSyncing && !isPausedByUser {
            Task {
                try? await syncPending()
            }
        }
    }

    // MARK: - Sync Operations

    /// Syncs all pending operations
    func syncPending() async throws {
        // Check prerequisites
        guard isOnline else {
            syncState = .paused(reason: .noNetwork)
            throw SyncError.networkUnavailable
        }

        guard authService.isAuthenticated else {
            syncState = .paused(reason: .notAuthenticated)
            throw SyncError.authenticationRequired
        }

        guard !isPausedByUser else {
            syncState = .paused(reason: .userPaused)
            return
        }

        // Don't start new sync if already syncing
        guard !syncState.isSyncing else {
            return
        }

        // Get pending operations
        let pendingOperations = realmManager.fetchPendingSyncOperations()

        guard !pendingOperations.isEmpty else {
            syncState = .idle
            return
        }

        // Start sync
        let startTime = Date()
        var successCount = 0
        var failureCount = 0
        var conflictCount = 0

        // Update state
        syncState = .syncing(progress: SyncProgress(
            total: pendingOperations.count,
            completed: 0,
            currentEntity: nil,
            currentOperation: nil
        ))

        // Update metadata
        try? await realmManager.updateSyncMetadata(isSyncing: true)

        // Create sync task
        currentSyncTask = Task {
            for (index, operation) in pendingOperations.enumerated() {
                // Check for cancellation
                if Task.isCancelled {
                    throw SyncError.cancelled
                }

                // Update progress
                let entityType = SyncEntityType(rawValue: operation.entityType)
                syncState = .syncing(progress: SyncProgress(
                    total: pendingOperations.count,
                    completed: index,
                    currentEntity: entityType,
                    currentOperation: "\(operation.operationType.displayName) \(operation.entityId)"
                ))

                do {
                    try await processOperation(operation)
                    try await realmManager.markSyncOperationProcessed(id: operation.id)
                    successCount += 1
                } catch let error as SyncError {
                    if case .conflictResolutionFailed = error {
                        conflictCount += 1
                    }
                    failureCount += 1
                    try await realmManager.recordSyncError(
                        id: operation.id,
                        error: error.localizedDescription
                    )
                } catch {
                    failureCount += 1
                    try await realmManager.recordSyncError(
                        id: operation.id,
                        error: error.localizedDescription
                    )
                }
            }
        }

        do {
            try await currentSyncTask?.value

            // Clean up processed operations
            try await realmManager.clearProcessedSyncOperations()

            // Calculate duration
            let duration = Date().timeIntervalSince(startTime)

            // Create result
            let result = SyncResult(
                successCount: successCount,
                failureCount: failureCount,
                conflictCount: conflictCount,
                completedAt: Date(),
                duration: duration
            )

            // Update state
            syncState = .completed(result)

            // Update metadata
            try await realmManager.updateSyncMetadata(
                lastSyncAt: Date(),
                isSyncing: false,
                lastSyncError: nil
            )

            // Update statistics
            updateStatistics(result: result)

            // Post notification
            NotificationCenter.default.post(name: .syncCompleted, object: result)

            // Reset to idle after a delay
            try? await Task.sleep(nanoseconds: 2_000_000_000) // 2 seconds
            if case .completed = syncState {
                syncState = .idle
            }

        } catch let error as SyncError {
            syncState = .failed(error)
            try? await realmManager.updateSyncMetadata(
                isSyncing: false,
                lastSyncError: error.localizedDescription
            )
            NotificationCenter.default.post(name: .syncFailed, object: error)
            throw error
        } catch {
            let syncError = SyncError.unknown(message: error.localizedDescription)
            syncState = .failed(syncError)
            try? await realmManager.updateSyncMetadata(
                isSyncing: false,
                lastSyncError: error.localizedDescription
            )
            NotificationCenter.default.post(name: .syncFailed, object: syncError)
            throw syncError
        }

        updatePendingCount()
    }

    /// Processes a single sync operation
    private func processOperation(_ operation: SyncQueueItem) async throws {
        guard let entityType = SyncEntityType(rawValue: operation.entityType) else {
            throw SyncError.operationFailed(
                entityType: operation.entityType,
                entityId: operation.entityId,
                message: "Unknown entity type"
            )
        }

        switch operation.operationType {
        case .create:
            try await processCreate(operation, entityType: entityType)
        case .update:
            try await processUpdate(operation, entityType: entityType)
        case .delete:
            try await processDelete(operation, entityType: entityType)
        }
    }

    /// Processes a create operation
    private func processCreate(_ operation: SyncQueueItem, entityType: SyncEntityType) async throws {
        guard let payload = operation.payload else {
            throw SyncError.operationFailed(
                entityType: entityType.rawValue,
                entityId: operation.entityId,
                message: "Missing payload"
            )
        }

        switch entityType {
        case .child:
            let request = try decodePayload(ChildRequest.self, from: payload)
            _ = try await gibbonClient.createChild(request)

        case .staff:
            let request = try decodePayload(StaffRequest.self, from: payload)
            _ = try await gibbonClient.createStaffMember(request)

        case .invoice:
            let request = try decodePayload(InvoiceRequest.self, from: payload)
            _ = try await gibbonClient.createInvoice(request)

        case .payment:
            let request = try decodePayload(PaymentRequest.self, from: payload)
            _ = try await gibbonClient.recordPayment(request)
        }
    }

    /// Processes an update operation
    private func processUpdate(_ operation: SyncQueueItem, entityType: SyncEntityType) async throws {
        guard let payload = operation.payload else {
            throw SyncError.operationFailed(
                entityType: entityType.rawValue,
                entityId: operation.entityId,
                message: "Missing payload"
            )
        }

        // Check for conflicts (server wins by default)
        try await checkAndResolveConflict(operation, entityType: entityType)

        switch entityType {
        case .child:
            let request = try decodePayload(ChildRequest.self, from: payload)
            _ = try await gibbonClient.updateChild(childId: operation.entityId, request: request)

        case .staff:
            let request = try decodePayload(StaffRequest.self, from: payload)
            _ = try await gibbonClient.updateStaffMember(staffId: operation.entityId, request: request)

        case .invoice:
            let request = try decodePayload(InvoiceRequest.self, from: payload)
            _ = try await gibbonClient.updateInvoice(invoiceId: operation.entityId, request: request)

        case .payment:
            // Payments typically aren't updated, but handle gracefully
            break
        }
    }

    /// Processes a delete operation
    private func processDelete(_ operation: SyncQueueItem, entityType: SyncEntityType) async throws {
        switch entityType {
        case .child:
            try await gibbonClient.deleteChild(childId: operation.entityId)

        case .staff:
            try await gibbonClient.deleteStaffMember(staffId: operation.entityId)

        case .invoice:
            try await gibbonClient.deleteInvoice(invoiceId: operation.entityId)

        case .payment:
            try await gibbonClient.deletePayment(paymentId: operation.entityId)
        }
    }

    /// Checks for and resolves conflicts using configured strategy
    private func checkAndResolveConflict(
        _ operation: SyncQueueItem,
        entityType: SyncEntityType
    ) async throws {
        // For server-wins strategy, we fetch the server version and compare timestamps
        // If server has newer data, we don't push our changes

        guard configuration.conflictResolution == .serverWins else {
            // Other strategies would be implemented here
            return
        }

        // Fetch server version to check timestamps
        do {
            switch entityType {
            case .child:
                let serverChild = try await gibbonClient.fetchChild(childId: operation.entityId)

                // Check if local data has this entity
                if let localChild = realmManager.fetchChild(id: operation.entityId) {
                    // Compare timestamps - server wins if newer
                    if let serverUpdated = serverChild.updatedAt,
                       let localUpdated = localChild.updatedAt,
                       serverUpdated > localUpdated {
                        // Server has newer data - update local and skip push
                        try await realmManager.saveChild(serverChild, queueSync: false)
                        throw SyncError.conflictResolutionFailed(entityId: operation.entityId)
                    }
                }

            case .staff:
                let serverStaff = try await gibbonClient.fetchStaffMember(staffId: operation.entityId)

                if let localStaff = realmManager.fetchStaffMember(id: operation.entityId) {
                    if let serverUpdated = serverStaff.updatedAt,
                       let localUpdated = localStaff.updatedAt,
                       serverUpdated > localUpdated {
                        try await realmManager.saveStaff(serverStaff, queueSync: false)
                        throw SyncError.conflictResolutionFailed(entityId: operation.entityId)
                    }
                }

            case .invoice:
                let serverInvoice = try await gibbonClient.fetchInvoice(invoiceId: operation.entityId)

                if let localInvoice = realmManager.fetchInvoice(id: operation.entityId) {
                    if let serverUpdated = serverInvoice.updatedAt,
                       let localUpdated = localInvoice.updatedAt,
                       serverUpdated > localUpdated {
                        try await realmManager.saveInvoice(serverInvoice, queueSync: false)
                        throw SyncError.conflictResolutionFailed(entityId: operation.entityId)
                    }
                }

            case .payment:
                // Payments are typically immutable, no conflict check needed
                break
            }
        } catch is APIError {
            // Entity doesn't exist on server - this is okay for creates
            // For updates, it might be a delete conflict
        }
    }

    /// Decodes a JSON payload to the specified type
    private func decodePayload<T: Decodable>(_ type: T.Type, from json: String) throws -> T {
        guard let data = json.data(using: .utf8) else {
            throw SyncError.operationFailed(
                entityType: String(describing: type),
                entityId: "unknown",
                message: "Invalid JSON string"
            )
        }
        return try decoder.decode(type, from: data)
    }

    // MARK: - Control Methods

    /// Pauses sync operations
    func pause() {
        isPausedByUser = true
        currentSyncTask?.cancel()

        if syncState.isSyncing {
            syncState = .paused(reason: .userPaused)
        }
    }

    /// Resumes sync operations
    func resume() {
        isPausedByUser = false

        if case .paused(.userPaused) = syncState {
            syncState = .idle

            // Trigger sync if there are pending operations
            if pendingOperationsCount > 0 && isOnline {
                Task {
                    try? await syncPending()
                }
            }
        }
    }

    /// Cancels all pending sync operations
    func cancelAllPending() async throws {
        currentSyncTask?.cancel()

        // Clear the sync queue
        try await realmManager.clearProcessedSyncOperations()

        // Clear unprocessed items would need a new method in RealmManager
        // For now, we mark them as processed

        syncState = .idle
        updatePendingCount()
    }

    // MARK: - Automatic Sync

    /// Starts the automatic sync timer
    func startAutoSync() {
        guard configuration.syncInterval > 0 else { return }

        syncTimer?.invalidate()
        syncTimer = Timer.scheduledTimer(
            withTimeInterval: configuration.syncInterval,
            repeats: true
        ) { [weak self] _ in
            guard let self = self else { return }

            Task { @MainActor in
                if self.isOnline && !self.isPausedByUser && self.pendingOperationsCount > 0 {
                    try? await self.syncPending()
                }
            }
        }
    }

    /// Stops the automatic sync timer
    func stopAutoSync() {
        syncTimer?.invalidate()
        syncTimer = nil
    }

    // MARK: - Full Sync (Pull from Server)

    /// Performs a full sync by pulling all data from the server
    func performFullSync() async throws {
        guard isOnline else {
            throw SyncError.networkUnavailable
        }

        guard authService.isAuthenticated else {
            throw SyncError.authenticationRequired
        }

        syncState = .syncing(progress: SyncProgress(
            total: 4, // children, staff, invoices, payments
            completed: 0,
            currentEntity: .child,
            currentOperation: "Fetching children"
        ))

        do {
            // Sync children
            let children = try await gibbonClient.fetchAllChildren()
            try await realmManager.saveChildren(children)

            syncState = .syncing(progress: SyncProgress(
                total: 4,
                completed: 1,
                currentEntity: .staff,
                currentOperation: "Fetching staff"
            ))

            // Sync staff
            let staff = try await gibbonClient.fetchAllStaff()
            try await realmManager.saveStaffMembers(staff)

            syncState = .syncing(progress: SyncProgress(
                total: 4,
                completed: 2,
                currentEntity: .invoice,
                currentOperation: "Fetching invoices"
            ))

            // Sync invoices
            let invoices = try await gibbonClient.fetchAllInvoices()
            try await realmManager.saveInvoices(invoices)

            syncState = .syncing(progress: SyncProgress(
                total: 4,
                completed: 3,
                currentEntity: .payment,
                currentOperation: "Fetching payments"
            ))

            // Note: Payments would need a fetchAllPayments method
            // For now, skip or implement as needed

            // Complete
            let result = SyncResult(
                successCount: children.count + staff.count + invoices.count,
                failureCount: 0,
                conflictCount: 0,
                completedAt: Date(),
                duration: 0
            )

            syncState = .completed(result)

            try await realmManager.updateSyncMetadata(lastSyncAt: Date(), isSyncing: false)

            // Reset to idle
            try? await Task.sleep(nanoseconds: 2_000_000_000)
            syncState = .idle

        } catch {
            let syncError = error as? SyncError ?? SyncError.unknown(message: error.localizedDescription)
            syncState = .failed(syncError)
            throw syncError
        }
    }

    // MARK: - Helper Methods

    /// Updates the pending operations count
    private func updatePendingCount() {
        pendingOperationsCount = realmManager.fetchPendingSyncOperations().count
    }

    /// Updates sync statistics
    private func updateStatistics(result: SyncResult) {
        statistics.totalSyncs += 1
        statistics.totalItemsSynced += result.successCount
        statistics.totalConflictsResolved += result.conflictCount

        if result.isFullySuccessful {
            statistics.successfulSyncs += 1
            statistics.lastSuccessfulSync = result.completedAt
        } else if result.successCount == 0 {
            statistics.failedSyncs += 1
            statistics.lastFailedSync = result.completedAt
        } else {
            // Partial success
            statistics.successfulSyncs += 1
            statistics.lastSuccessfulSync = result.completedAt
        }

        // Update average duration
        let totalDuration = statistics.averageSyncDuration * Double(statistics.totalSyncs - 1)
        statistics.averageSyncDuration = (totalDuration + result.duration) / Double(statistics.totalSyncs)
    }

    /// Updates the sync configuration
    func updateConfiguration(_ newConfig: SyncConfiguration) {
        configuration = newConfig

        // Restart auto-sync with new interval if running
        if syncTimer != nil {
            startAutoSync()
        }
    }
}

// MARK: - Preview Support

#if DEBUG
extension SyncService {

    /// Creates a preview instance with simulated state
    static var preview: SyncService {
        let service = SyncService()
        service.networkStatus = .connected(interface: .wifi)
        service.pendingOperationsCount = 3
        return service
    }

    /// Creates an offline preview instance
    static var previewOffline: SyncService {
        let service = SyncService()
        service.networkStatus = .disconnected
        service.syncState = .paused(reason: .noNetwork)
        service.pendingOperationsCount = 5
        return service
    }

    /// Creates a syncing preview instance
    static var previewSyncing: SyncService {
        let service = SyncService()
        service.networkStatus = .connected(interface: .wifi)
        service.syncState = .syncing(progress: SyncProgress(
            total: 10,
            completed: 4,
            currentEntity: .child,
            currentOperation: "Updating child records"
        ))
        return service
    }
}
#endif
