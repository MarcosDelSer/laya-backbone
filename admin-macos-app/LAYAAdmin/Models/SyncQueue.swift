//
//  SyncQueue.swift
//  LAYAAdmin
//
//  Models for sync queue management and offline operations.
//  Defines the state and configuration for sync operations.
//

import Foundation

// MARK: - Sync State

/// Represents the current state of the sync service
enum SyncState: Equatable {
    /// Sync service is idle and ready
    case idle

    /// Sync is currently in progress
    case syncing(progress: SyncProgress)

    /// Sync completed successfully
    case completed(SyncResult)

    /// Sync failed with an error
    case failed(SyncError)

    /// Sync is paused (waiting for network)
    case paused(reason: PauseReason)

    /// Whether sync is currently active
    var isSyncing: Bool {
        if case .syncing = self { return true }
        return false
    }

    /// Whether the service is paused
    var isPaused: Bool {
        if case .paused = self { return true }
        return false
    }
}

// MARK: - Pause Reason

/// Reason why sync is paused
enum PauseReason: Equatable {
    case noNetwork
    case notAuthenticated
    case userPaused
    case backgroundMode

    var displayName: String {
        switch self {
        case .noNetwork:
            return String(localized: "No network connection")
        case .notAuthenticated:
            return String(localized: "Not authenticated")
        case .userPaused:
            return String(localized: "Sync paused by user")
        case .backgroundMode:
            return String(localized: "App in background")
        }
    }
}

// MARK: - Sync Progress

/// Progress information for ongoing sync operations
struct SyncProgress: Equatable {
    /// Total number of operations to sync
    let total: Int

    /// Number of completed operations
    let completed: Int

    /// Currently syncing entity type
    let currentEntity: SyncEntityType?

    /// Current operation being processed
    let currentOperation: String?

    /// Progress percentage (0.0 - 1.0)
    var percentage: Double {
        guard total > 0 else { return 0 }
        return Double(completed) / Double(total)
    }

    /// Progress description for display
    var displayText: String {
        if let entity = currentEntity {
            return String(localized: "Syncing \(entity.displayName)... (\(completed)/\(total))")
        }
        return String(localized: "Syncing... (\(completed)/\(total))")
    }

    static let zero = SyncProgress(total: 0, completed: 0, currentEntity: nil, currentOperation: nil)
}

// MARK: - Sync Result

/// Result of a sync operation
struct SyncResult: Equatable {
    /// Number of operations successfully synced
    let successCount: Int

    /// Number of operations that failed
    let failureCount: Int

    /// Number of conflicts resolved
    let conflictCount: Int

    /// When the sync completed
    let completedAt: Date

    /// Duration of the sync operation
    let duration: TimeInterval

    /// Whether sync was fully successful
    var isFullySuccessful: Bool {
        failureCount == 0
    }

    /// Summary message for display
    var displayText: String {
        if isFullySuccessful {
            return String(localized: "Synced \(successCount) items successfully")
        } else if successCount == 0 {
            return String(localized: "Sync failed for all \(failureCount) items")
        } else {
            return String(localized: "Synced \(successCount) items, \(failureCount) failed")
        }
    }
}

// MARK: - Sync Error

/// Errors that can occur during sync operations
enum SyncError: LocalizedError, Equatable {
    case networkUnavailable
    case authenticationRequired
    case serverError(message: String)
    case conflictResolutionFailed(entityId: String)
    case operationFailed(entityType: String, entityId: String, message: String)
    case timeout
    case cancelled
    case unknown(message: String)

    var errorDescription: String? {
        switch self {
        case .networkUnavailable:
            return String(localized: "Network connection unavailable")
        case .authenticationRequired:
            return String(localized: "Authentication required")
        case .serverError(let message):
            return String(localized: "Server error: \(message)")
        case .conflictResolutionFailed(let entityId):
            return String(localized: "Failed to resolve conflict for item \(entityId)")
        case .operationFailed(let entityType, let entityId, let message):
            return String(localized: "Failed to sync \(entityType) \(entityId): \(message)")
        case .timeout:
            return String(localized: "Sync operation timed out")
        case .cancelled:
            return String(localized: "Sync was cancelled")
        case .unknown(let message):
            return String(localized: "Unknown error: \(message)")
        }
    }
}

// MARK: - Sync Configuration

/// Configuration options for the sync service
struct SyncConfiguration {
    /// Automatic sync interval in seconds
    var syncInterval: TimeInterval = 300 // 5 minutes

    /// Maximum retry attempts per operation
    var maxRetryAttempts: Int = 3

    /// Timeout for individual sync operations
    var operationTimeout: TimeInterval = 30

    /// Whether to sync automatically when network becomes available
    var autoSyncOnReconnect: Bool = true

    /// Whether to sync in background
    var enableBackgroundSync: Bool = true

    /// Batch size for bulk sync operations
    var batchSize: Int = 50

    /// Conflict resolution strategy
    var conflictResolution: ConflictResolutionStrategy = .serverWins

    /// Default configuration
    static let `default` = SyncConfiguration()
}

// MARK: - Conflict Resolution Strategy

/// Strategy for resolving sync conflicts
enum ConflictResolutionStrategy: String, Codable, CaseIterable {
    /// Server data always wins
    case serverWins = "server_wins"

    /// Local data always wins
    case localWins = "local_wins"

    /// Merge changes (most recent timestamp wins per field)
    case merge = "merge"

    /// Ask user to resolve manually
    case askUser = "ask_user"

    var displayName: String {
        switch self {
        case .serverWins:
            return String(localized: "Server Wins")
        case .localWins:
            return String(localized: "Local Wins")
        case .merge:
            return String(localized: "Merge Changes")
        case .askUser:
            return String(localized: "Ask User")
        }
    }
}

// MARK: - Sync Conflict

/// Represents a conflict between local and server data
struct SyncConflict: Identifiable, Equatable {
    /// Unique identifier for this conflict
    let id: String

    /// Entity type involved
    let entityType: SyncEntityType

    /// Entity ID involved
    let entityId: String

    /// Local version of the data (as JSON)
    let localData: String

    /// Server version of the data (as JSON)
    let serverData: String

    /// When the conflict was detected
    let detectedAt: Date

    /// Whether this conflict has been resolved
    var isResolved: Bool = false

    /// How the conflict was resolved
    var resolution: ConflictResolutionStrategy?

    init(
        entityType: SyncEntityType,
        entityId: String,
        localData: String,
        serverData: String
    ) {
        self.id = UUID().uuidString
        self.entityType = entityType
        self.entityId = entityId
        self.localData = localData
        self.serverData = serverData
        self.detectedAt = Date()
    }
}

// MARK: - Network Status

/// Current network connectivity status
enum NetworkStatus: Equatable {
    /// Network is available
    case connected(interface: NetworkInterface)

    /// No network connection
    case disconnected

    /// Network status unknown
    case unknown

    /// Whether network is available
    var isConnected: Bool {
        if case .connected = self { return true }
        return false
    }

    var displayName: String {
        switch self {
        case .connected(let interface):
            return String(localized: "Connected via \(interface.displayName)")
        case .disconnected:
            return String(localized: "Disconnected")
        case .unknown:
            return String(localized: "Unknown")
        }
    }
}

// MARK: - Network Interface

/// Type of network interface
enum NetworkInterface: String, Equatable {
    case wifi = "wifi"
    case cellular = "cellular"
    case wired = "wired"
    case other = "other"

    var displayName: String {
        switch self {
        case .wifi:
            return "Wi-Fi"
        case .cellular:
            return String(localized: "Cellular")
        case .wired:
            return String(localized: "Ethernet")
        case .other:
            return String(localized: "Other")
        }
    }
}

// MARK: - Sync Statistics

/// Statistics about sync operations
struct SyncStatistics: Equatable {
    /// Total number of sync operations performed
    var totalSyncs: Int = 0

    /// Number of successful syncs
    var successfulSyncs: Int = 0

    /// Number of failed syncs
    var failedSyncs: Int = 0

    /// Total items synced
    var totalItemsSynced: Int = 0

    /// Total conflicts resolved
    var totalConflictsResolved: Int = 0

    /// Last successful sync timestamp
    var lastSuccessfulSync: Date?

    /// Last failed sync timestamp
    var lastFailedSync: Date?

    /// Average sync duration in seconds
    var averageSyncDuration: TimeInterval = 0

    /// Success rate (0.0 - 1.0)
    var successRate: Double {
        guard totalSyncs > 0 else { return 0 }
        return Double(successfulSyncs) / Double(totalSyncs)
    }
}

// MARK: - Sync Entity Type Extension

extension SyncEntityType {
    /// Display name for the entity type
    var displayName: String {
        switch self {
        case .child:
            return String(localized: "Children")
        case .staff:
            return String(localized: "Staff")
        case .invoice:
            return String(localized: "Invoices")
        case .payment:
            return String(localized: "Payments")
        }
    }

    /// API endpoint path for the entity type
    var apiPath: String {
        switch self {
        case .child:
            return GibbonEndpoints.students
        case .staff:
            return GibbonEndpoints.staff
        case .invoice:
            return GibbonEndpoints.invoices
        case .payment:
            return GibbonEndpoints.payments
        }
    }
}

// MARK: - Sync Operation Type Extension

extension SyncOperationType {
    /// Display name for the operation type
    var displayName: String {
        switch self {
        case .create:
            return String(localized: "Create")
        case .update:
            return String(localized: "Update")
        case .delete:
            return String(localized: "Delete")
        }
    }
}
