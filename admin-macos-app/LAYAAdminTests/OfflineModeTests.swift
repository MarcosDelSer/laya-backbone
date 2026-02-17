//
//  OfflineModeTests.swift
//  LAYAAdminTests
//
//  Unit tests for offline mode functionality including sync queue,
//  local caching, and sync service operations.
//

import XCTest
@testable import LAYAAdmin

// MARK: - Sync State Tests

final class SyncStateTests: XCTestCase {

    func testSyncStateIsSyncing() {
        // Given
        let idleState = SyncState.idle
        let syncingState = SyncState.syncing(progress: .zero)
        let completedState = SyncState.completed(SyncResult(
            successCount: 5,
            failureCount: 0,
            conflictCount: 0,
            completedAt: Date(),
            duration: 1.5
        ))
        let failedState = SyncState.failed(.networkUnavailable)
        let pausedState = SyncState.paused(reason: .noNetwork)

        // Then
        XCTAssertFalse(idleState.isSyncing)
        XCTAssertTrue(syncingState.isSyncing)
        XCTAssertFalse(completedState.isSyncing)
        XCTAssertFalse(failedState.isSyncing)
        XCTAssertFalse(pausedState.isSyncing)
    }

    func testSyncStateIsPaused() {
        // Given
        let idleState = SyncState.idle
        let syncingState = SyncState.syncing(progress: .zero)
        let pausedNetworkState = SyncState.paused(reason: .noNetwork)
        let pausedAuthState = SyncState.paused(reason: .notAuthenticated)
        let pausedUserState = SyncState.paused(reason: .userPaused)

        // Then
        XCTAssertFalse(idleState.isPaused)
        XCTAssertFalse(syncingState.isPaused)
        XCTAssertTrue(pausedNetworkState.isPaused)
        XCTAssertTrue(pausedAuthState.isPaused)
        XCTAssertTrue(pausedUserState.isPaused)
    }
}

// MARK: - Sync Progress Tests

final class SyncProgressTests: XCTestCase {

    func testSyncProgressPercentage() {
        // Given
        let zeroProgress = SyncProgress.zero
        let halfProgress = SyncProgress(total: 10, completed: 5, currentEntity: nil, currentOperation: nil)
        let fullProgress = SyncProgress(total: 10, completed: 10, currentEntity: nil, currentOperation: nil)

        // Then
        XCTAssertEqual(zeroProgress.percentage, 0.0, accuracy: 0.001)
        XCTAssertEqual(halfProgress.percentage, 0.5, accuracy: 0.001)
        XCTAssertEqual(fullProgress.percentage, 1.0, accuracy: 0.001)
    }

    func testSyncProgressDisplayText() {
        // Given
        let progressWithEntity = SyncProgress(
            total: 10,
            completed: 3,
            currentEntity: .child,
            currentOperation: "Updating"
        )
        let progressWithoutEntity = SyncProgress(
            total: 10,
            completed: 3,
            currentEntity: nil,
            currentOperation: nil
        )

        // Then
        XCTAssertTrue(progressWithEntity.displayText.contains("Children"))
        XCTAssertTrue(progressWithEntity.displayText.contains("3/10"))
        XCTAssertTrue(progressWithoutEntity.displayText.contains("3/10"))
    }
}

// MARK: - Sync Result Tests

final class SyncResultTests: XCTestCase {

    func testSyncResultIsFullySuccessful() {
        // Given
        let successResult = SyncResult(
            successCount: 10,
            failureCount: 0,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )
        let partialResult = SyncResult(
            successCount: 8,
            failureCount: 2,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )
        let failedResult = SyncResult(
            successCount: 0,
            failureCount: 10,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )

        // Then
        XCTAssertTrue(successResult.isFullySuccessful)
        XCTAssertFalse(partialResult.isFullySuccessful)
        XCTAssertFalse(failedResult.isFullySuccessful)
    }

    func testSyncResultDisplayText() {
        // Given
        let successResult = SyncResult(
            successCount: 10,
            failureCount: 0,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )
        let partialResult = SyncResult(
            successCount: 8,
            failureCount: 2,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )
        let failedResult = SyncResult(
            successCount: 0,
            failureCount: 10,
            conflictCount: 0,
            completedAt: Date(),
            duration: 2.0
        )

        // Then
        XCTAssertTrue(successResult.displayText.contains("10"))
        XCTAssertTrue(successResult.displayText.contains("successfully"))
        XCTAssertTrue(partialResult.displayText.contains("8"))
        XCTAssertTrue(partialResult.displayText.contains("2"))
        XCTAssertTrue(failedResult.displayText.contains("failed"))
    }
}

// MARK: - Sync Error Tests

final class SyncErrorTests: XCTestCase {

    func testSyncErrorDescriptions() {
        // Given
        let errors: [SyncError] = [
            .networkUnavailable,
            .authenticationRequired,
            .serverError(message: "Internal error"),
            .conflictResolutionFailed(entityId: "child-123"),
            .operationFailed(entityType: "Child", entityId: "123", message: "Update failed"),
            .timeout,
            .cancelled,
            .unknown(message: "Something went wrong")
        ]

        // Then
        for error in errors {
            XCTAssertNotNil(error.errorDescription)
            XCTAssertFalse(error.errorDescription?.isEmpty ?? true)
        }
    }

    func testSyncErrorEquatable() {
        // Given
        let error1 = SyncError.networkUnavailable
        let error2 = SyncError.networkUnavailable
        let error3 = SyncError.authenticationRequired

        // Then
        XCTAssertEqual(error1, error2)
        XCTAssertNotEqual(error1, error3)
    }
}

// MARK: - Network Status Tests

final class NetworkStatusTests: XCTestCase {

    func testNetworkStatusIsConnected() {
        // Given
        let wifiConnected = NetworkStatus.connected(interface: .wifi)
        let cellularConnected = NetworkStatus.connected(interface: .cellular)
        let disconnected = NetworkStatus.disconnected
        let unknown = NetworkStatus.unknown

        // Then
        XCTAssertTrue(wifiConnected.isConnected)
        XCTAssertTrue(cellularConnected.isConnected)
        XCTAssertFalse(disconnected.isConnected)
        XCTAssertFalse(unknown.isConnected)
    }

    func testNetworkStatusDisplayName() {
        // Given
        let wifiConnected = NetworkStatus.connected(interface: .wifi)
        let disconnected = NetworkStatus.disconnected
        let unknown = NetworkStatus.unknown

        // Then
        XCTAssertTrue(wifiConnected.displayName.contains("Wi-Fi"))
        XCTAssertEqual(disconnected.displayName, "Disconnected")
        XCTAssertEqual(unknown.displayName, "Unknown")
    }
}

// MARK: - Sync Configuration Tests

final class SyncConfigurationTests: XCTestCase {

    func testDefaultConfiguration() {
        // Given
        let config = SyncConfiguration.default

        // Then
        XCTAssertEqual(config.syncInterval, 300) // 5 minutes
        XCTAssertEqual(config.maxRetryAttempts, 3)
        XCTAssertEqual(config.operationTimeout, 30)
        XCTAssertTrue(config.autoSyncOnReconnect)
        XCTAssertTrue(config.enableBackgroundSync)
        XCTAssertEqual(config.batchSize, 50)
        XCTAssertEqual(config.conflictResolution, .serverWins)
    }
}

// MARK: - Conflict Resolution Strategy Tests

final class ConflictResolutionStrategyTests: XCTestCase {

    func testConflictResolutionDisplayNames() {
        // Given/Then
        XCTAssertEqual(ConflictResolutionStrategy.serverWins.displayName, "Server Wins")
        XCTAssertEqual(ConflictResolutionStrategy.localWins.displayName, "Local Wins")
        XCTAssertEqual(ConflictResolutionStrategy.merge.displayName, "Merge Changes")
        XCTAssertEqual(ConflictResolutionStrategy.askUser.displayName, "Ask User")
    }

    func testAllCases() {
        // Given
        let allCases = ConflictResolutionStrategy.allCases

        // Then
        XCTAssertEqual(allCases.count, 4)
        XCTAssertTrue(allCases.contains(.serverWins))
        XCTAssertTrue(allCases.contains(.localWins))
        XCTAssertTrue(allCases.contains(.merge))
        XCTAssertTrue(allCases.contains(.askUser))
    }
}

// MARK: - Sync Entity Type Tests

final class SyncEntityTypeTests: XCTestCase {

    func testSyncEntityTypeDisplayNames() {
        // Given/Then
        XCTAssertEqual(SyncEntityType.child.displayName, "Children")
        XCTAssertEqual(SyncEntityType.staff.displayName, "Staff")
        XCTAssertEqual(SyncEntityType.invoice.displayName, "Invoices")
        XCTAssertEqual(SyncEntityType.payment.displayName, "Payments")
    }

    func testSyncEntityTypeApiPaths() {
        // Given/Then
        XCTAssertEqual(SyncEntityType.child.apiPath, GibbonEndpoints.students)
        XCTAssertEqual(SyncEntityType.staff.apiPath, GibbonEndpoints.staff)
        XCTAssertEqual(SyncEntityType.invoice.apiPath, GibbonEndpoints.invoices)
        XCTAssertEqual(SyncEntityType.payment.apiPath, GibbonEndpoints.payments)
    }
}

// MARK: - Sync Operation Type Tests

final class SyncOperationTypeTests: XCTestCase {

    func testSyncOperationTypeDisplayNames() {
        // Given/Then
        XCTAssertEqual(SyncOperationType.create.displayName, "Create")
        XCTAssertEqual(SyncOperationType.update.displayName, "Update")
        XCTAssertEqual(SyncOperationType.delete.displayName, "Delete")
    }

    func testSyncOperationTypeRawValues() {
        // Given/Then
        XCTAssertEqual(SyncOperationType.create.rawValue, "create")
        XCTAssertEqual(SyncOperationType.update.rawValue, "update")
        XCTAssertEqual(SyncOperationType.delete.rawValue, "delete")
    }
}

// MARK: - Sync Statistics Tests

final class SyncStatisticsTests: XCTestCase {

    func testSyncStatisticsDefaultValues() {
        // Given
        let stats = SyncStatistics()

        // Then
        XCTAssertEqual(stats.totalSyncs, 0)
        XCTAssertEqual(stats.successfulSyncs, 0)
        XCTAssertEqual(stats.failedSyncs, 0)
        XCTAssertEqual(stats.totalItemsSynced, 0)
        XCTAssertEqual(stats.totalConflictsResolved, 0)
        XCTAssertNil(stats.lastSuccessfulSync)
        XCTAssertNil(stats.lastFailedSync)
        XCTAssertEqual(stats.averageSyncDuration, 0)
    }

    func testSyncStatisticsSuccessRate() {
        // Given
        var stats = SyncStatistics()

        // When - no syncs
        XCTAssertEqual(stats.successRate, 0)

        // When - all successful
        stats.totalSyncs = 10
        stats.successfulSyncs = 10
        XCTAssertEqual(stats.successRate, 1.0, accuracy: 0.001)

        // When - partial success
        stats.totalSyncs = 10
        stats.successfulSyncs = 7
        XCTAssertEqual(stats.successRate, 0.7, accuracy: 0.001)
    }
}

// MARK: - Pause Reason Tests

final class PauseReasonTests: XCTestCase {

    func testPauseReasonDisplayNames() {
        // Given/Then
        XCTAssertEqual(PauseReason.noNetwork.displayName, "No network connection")
        XCTAssertEqual(PauseReason.notAuthenticated.displayName, "Not authenticated")
        XCTAssertEqual(PauseReason.userPaused.displayName, "Sync paused by user")
        XCTAssertEqual(PauseReason.backgroundMode.displayName, "App in background")
    }
}

// MARK: - Network Interface Tests

final class NetworkInterfaceTests: XCTestCase {

    func testNetworkInterfaceDisplayNames() {
        // Given/Then
        XCTAssertEqual(NetworkInterface.wifi.displayName, "Wi-Fi")
        XCTAssertEqual(NetworkInterface.cellular.displayName, "Cellular")
        XCTAssertEqual(NetworkInterface.wired.displayName, "Ethernet")
        XCTAssertEqual(NetworkInterface.other.displayName, "Other")
    }

    func testNetworkInterfaceRawValues() {
        // Given/Then
        XCTAssertEqual(NetworkInterface.wifi.rawValue, "wifi")
        XCTAssertEqual(NetworkInterface.cellular.rawValue, "cellular")
        XCTAssertEqual(NetworkInterface.wired.rawValue, "wired")
        XCTAssertEqual(NetworkInterface.other.rawValue, "other")
    }
}

// MARK: - Sync Conflict Tests

final class SyncConflictTests: XCTestCase {

    func testSyncConflictInitialization() {
        // Given
        let conflict = SyncConflict(
            entityType: .child,
            entityId: "child-123",
            localData: "{\"name\": \"Local\"}",
            serverData: "{\"name\": \"Server\"}"
        )

        // Then
        XCTAssertNotNil(conflict.id)
        XCTAssertEqual(conflict.entityType, .child)
        XCTAssertEqual(conflict.entityId, "child-123")
        XCTAssertEqual(conflict.localData, "{\"name\": \"Local\"}")
        XCTAssertEqual(conflict.serverData, "{\"name\": \"Server\"}")
        XCTAssertNotNil(conflict.detectedAt)
        XCTAssertFalse(conflict.isResolved)
        XCTAssertNil(conflict.resolution)
    }
}
