//
//  OfflineStatusBanner.swift
//  LAYAAdmin
//
//  Banner component displaying offline status and sync information.
//  Shows when the app is offline, when data is from cache, and when
//  there are pending sync operations.
//

import SwiftUI

// MARK: - Offline Status Banner

/// Banner displaying the current offline status and sync state.
///
/// Shows different states:
/// - Offline: No network connection
/// - Pending sync: Changes waiting to be synced
/// - Syncing: Currently syncing changes
/// - Sync completed: Recently completed sync
///
/// Usage:
/// ```swift
/// VStack {
///     OfflineStatusBanner(
///         isOffline: viewModel.isOffline,
///         pendingSyncCount: viewModel.pendingSyncCount,
///         syncState: syncService.syncState,
///         isLoadingFromCache: viewModel.isLoadingFromCache
///     )
///     // ... rest of your view
/// }
/// ```
struct OfflineStatusBanner: View {

    // MARK: - Properties

    /// Whether the app is currently offline
    let isOffline: Bool

    /// Number of pending sync operations
    let pendingSyncCount: Int

    /// Current sync state (optional, for detailed sync progress)
    let syncState: SyncState?

    /// Whether data is being loaded from local cache
    let isLoadingFromCache: Bool

    /// Whether to show the banner even when online with no pending syncs
    var alwaysShow: Bool = false

    // MARK: - Initialization

    init(
        isOffline: Bool,
        pendingSyncCount: Int = 0,
        syncState: SyncState? = nil,
        isLoadingFromCache: Bool = false,
        alwaysShow: Bool = false
    ) {
        self.isOffline = isOffline
        self.pendingSyncCount = pendingSyncCount
        self.syncState = syncState
        self.isLoadingFromCache = isLoadingFromCache
        self.alwaysShow = alwaysShow
    }

    // MARK: - Computed Properties

    /// Whether the banner should be visible
    private var shouldShow: Bool {
        if alwaysShow { return true }
        return isOffline || pendingSyncCount > 0 || isLoadingFromCache || isSyncing
    }

    /// Whether sync is currently in progress
    private var isSyncing: Bool {
        guard let state = syncState else { return false }
        if case .syncing = state { return true }
        return false
    }

    /// Sync progress percentage (0.0 - 1.0)
    private var syncProgress: Double {
        guard case .syncing(let progress) = syncState else { return 0 }
        return progress.percentage
    }

    /// Current banner color based on state
    private var bannerColor: Color {
        if isOffline {
            return .orange
        } else if isSyncing {
            return .blue
        } else if pendingSyncCount > 0 {
            return .yellow
        } else if isLoadingFromCache {
            return .purple
        } else {
            return .green
        }
    }

    /// Current banner icon based on state
    private var bannerIcon: String {
        if isOffline {
            return "wifi.slash"
        } else if isSyncing {
            return "arrow.triangle.2.circlepath"
        } else if pendingSyncCount > 0 {
            return "clock.arrow.circlepath"
        } else if isLoadingFromCache {
            return "internaldrive"
        } else {
            return "checkmark.circle"
        }
    }

    /// Current banner message
    private var bannerMessage: String {
        if isOffline {
            if pendingSyncCount > 0 {
                return String(localized: "Offline - \(pendingSyncCount) changes pending sync")
            }
            return String(localized: "Offline - Using cached data")
        } else if isSyncing {
            if case .syncing(let progress) = syncState {
                return String(localized: "Syncing... \(progress.completed)/\(progress.total)")
            }
            return String(localized: "Syncing changes...")
        } else if pendingSyncCount > 0 {
            return String(localized: "\(pendingSyncCount) changes waiting to sync")
        } else if isLoadingFromCache {
            return String(localized: "Loading from local cache...")
        } else {
            return String(localized: "All changes synced")
        }
    }

    // MARK: - Body

    var body: some View {
        if shouldShow {
            HStack(spacing: 12) {
                // Icon
                Image(systemName: bannerIcon)
                    .font(.system(size: 14, weight: .medium))
                    .foregroundColor(bannerColor)
                    .symbolEffect(.pulse, isActive: isSyncing || isOffline)

                // Message
                Text(bannerMessage)
                    .font(.subheadline)
                    .foregroundColor(.primary)

                Spacer()

                // Progress indicator for syncing
                if isSyncing {
                    ProgressView(value: syncProgress)
                        .progressViewStyle(.linear)
                        .frame(width: 80)
                }

                // Sync button when online with pending changes
                if !isOffline && pendingSyncCount > 0 && !isSyncing {
                    Button(action: {
                        NotificationCenter.default.post(name: .syncNow, object: nil)
                    }) {
                        Text(String(localized: "Sync Now"))
                            .font(.subheadline.weight(.medium))
                    }
                    .buttonStyle(.bordered)
                    .controlSize(.small)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(bannerColor.opacity(0.1))
            .overlay(
                Rectangle()
                    .fill(bannerColor)
                    .frame(width: 4),
                alignment: .leading
            )
        }
    }
}

// MARK: - Compact Offline Indicator

/// A compact offline indicator for use in toolbars or headers.
struct CompactOfflineIndicator: View {

    let isOffline: Bool
    let pendingSyncCount: Int

    var body: some View {
        if isOffline || pendingSyncCount > 0 {
            HStack(spacing: 4) {
                Image(systemName: isOffline ? "wifi.slash" : "clock.arrow.circlepath")
                    .font(.system(size: 12))

                if pendingSyncCount > 0 {
                    Text("\(pendingSyncCount)")
                        .font(.caption.monospacedDigit())
                }
            }
            .foregroundColor(isOffline ? .orange : .yellow)
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(
                Capsule()
                    .fill((isOffline ? Color.orange : Color.yellow).opacity(0.15))
            )
            .help(isOffline
                ? String(localized: "Offline - \(pendingSyncCount) changes pending")
                : String(localized: "\(pendingSyncCount) changes pending sync")
            )
        }
    }
}

// MARK: - Sync Status View

/// A detailed sync status view for settings or status pages.
struct SyncStatusView: View {

    @ObservedObject var syncService: SyncService

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            // Network status
            HStack {
                Image(systemName: syncService.isOnline ? "wifi" : "wifi.slash")
                    .foregroundColor(syncService.isOnline ? .green : .orange)

                Text(syncService.networkStatus.displayName)
                    .font(.subheadline)

                Spacer()
            }

            // Sync state
            HStack {
                Image(systemName: syncStateIcon)
                    .foregroundColor(syncStateColor)

                Text(syncStateMessage)
                    .font(.subheadline)

                Spacer()

                if case .syncing(let progress) = syncService.syncState {
                    Text("\(Int(progress.percentage * 100))%")
                        .font(.caption.monospacedDigit())
                        .foregroundColor(.secondary)
                }
            }

            // Pending operations
            if syncService.pendingOperationsCount > 0 {
                HStack {
                    Image(systemName: "clock.arrow.circlepath")
                        .foregroundColor(.yellow)

                    Text(String(localized: "\(syncService.pendingOperationsCount) pending operations"))
                        .font(.subheadline)

                    Spacer()

                    if syncService.isOnline && !syncService.syncState.isSyncing {
                        Button(String(localized: "Sync Now")) {
                            Task {
                                try? await syncService.syncPending()
                            }
                        }
                        .buttonStyle(.bordered)
                        .controlSize(.small)
                    }
                }
            }

            // Last sync time
            if let lastSync = syncService.statistics.lastSuccessfulSync {
                HStack {
                    Image(systemName: "checkmark.circle")
                        .foregroundColor(.green)

                    Text(String(localized: "Last synced \(lastSync.formatted(.relative(presentation: .named)))"))
                        .font(.caption)
                        .foregroundColor(.secondary)

                    Spacer()
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }

    private var syncStateIcon: String {
        switch syncService.syncState {
        case .idle:
            return "checkmark.circle"
        case .syncing:
            return "arrow.triangle.2.circlepath"
        case .completed:
            return "checkmark.circle.fill"
        case .failed:
            return "exclamationmark.triangle"
        case .paused:
            return "pause.circle"
        }
    }

    private var syncStateColor: Color {
        switch syncService.syncState {
        case .idle, .completed:
            return .green
        case .syncing:
            return .blue
        case .failed:
            return .red
        case .paused:
            return .orange
        }
    }

    private var syncStateMessage: String {
        switch syncService.syncState {
        case .idle:
            return String(localized: "Ready to sync")
        case .syncing(let progress):
            return progress.displayText
        case .completed(let result):
            return result.displayText
        case .failed(let error):
            return error.localizedDescription
        case .paused(let reason):
            return reason.displayName
        }
    }
}

// MARK: - Notification Names

extension Notification.Name {
    /// Posted to trigger an immediate sync
    static let syncNow = Notification.Name("syncNow")
}

// MARK: - Preview

#Preview("Offline Status Banner - Offline") {
    VStack(spacing: 0) {
        OfflineStatusBanner(
            isOffline: true,
            pendingSyncCount: 3,
            syncState: nil,
            isLoadingFromCache: false
        )

        Spacer()
    }
    .frame(width: 500, height: 200)
}

#Preview("Offline Status Banner - Syncing") {
    VStack(spacing: 0) {
        OfflineStatusBanner(
            isOffline: false,
            pendingSyncCount: 5,
            syncState: .syncing(progress: SyncProgress(
                total: 5,
                completed: 2,
                currentEntity: .child,
                currentOperation: "Updating child records"
            )),
            isLoadingFromCache: false
        )

        Spacer()
    }
    .frame(width: 500, height: 200)
}

#Preview("Offline Status Banner - Pending Sync") {
    VStack(spacing: 0) {
        OfflineStatusBanner(
            isOffline: false,
            pendingSyncCount: 7,
            syncState: nil,
            isLoadingFromCache: false
        )

        Spacer()
    }
    .frame(width: 500, height: 200)
}

#Preview("Offline Status Banner - Loading from Cache") {
    VStack(spacing: 0) {
        OfflineStatusBanner(
            isOffline: true,
            pendingSyncCount: 0,
            syncState: nil,
            isLoadingFromCache: true
        )

        Spacer()
    }
    .frame(width: 500, height: 200)
}

#Preview("Compact Offline Indicator") {
    HStack(spacing: 20) {
        CompactOfflineIndicator(isOffline: true, pendingSyncCount: 5)
        CompactOfflineIndicator(isOffline: false, pendingSyncCount: 3)
        CompactOfflineIndicator(isOffline: false, pendingSyncCount: 0)
    }
    .padding()
}
