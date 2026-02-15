//
//  AppDelegate.swift
//  LAYAAdmin
//
//  AppDelegate for handling macOS-specific functionality including
//  notifications, menu bar integration, and lifecycle events.
//

import SwiftUI
import UserNotifications
import AppKit

// MARK: - App Delegate

/// AppDelegate for handling macOS-specific functionality
/// including notifications and menu bar integration.
///
/// Features:
/// - Menu bar status item with NSStatusItem
/// - Native notification setup and permissions
/// - App lifecycle management
/// - Keeps app running when main window is closed
class AppDelegate: NSObject, NSApplicationDelegate {

    // MARK: - Properties

    /// Menu bar status item
    private var statusItem: NSStatusItem?

    /// Key for tracking first launch notification permission
    private let hasRequestedNotificationPermissionKey = "hasRequestedNotificationPermission"

    /// Menu bar popover (alternative to Menu for richer UI)
    private var popover: NSPopover?

    /// Event monitor for clicking outside popover
    private var eventMonitor: Any?

    // MARK: - NSApplicationDelegate

    func applicationDidFinishLaunching(_ notification: Notification) {
        // Configure app on launch
        configureAppearance()

        // Setup menu bar status item
        setupStatusItem()

        // Setup notification service
        setupNotifications()

        // Register for notification action handlers
        registerNotificationHandlers()
    }

    func applicationWillTerminate(_ notification: Notification) {
        // Clean up event monitor
        if let monitor = eventMonitor {
            NSEvent.removeMonitor(monitor)
            eventMonitor = nil
        }
    }

    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool {
        // Keep app running in menu bar even if main window is closed
        return false
    }

    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        // When dock icon is clicked, bring main window to front
        if !flag {
            // No visible windows, open main window
            for window in NSApp.windows {
                if !(window is NSPanel) {
                    window.makeKeyAndOrderFront(self)
                    return true
                }
            }
        }
        return true
    }

    // MARK: - Status Item Setup

    /// Sets up the menu bar status item with icon and menu
    private func setupStatusItem() {
        // Create status item
        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)

        // Configure button appearance
        if let button = statusItem?.button {
            // Use SF Symbol for the icon
            let config = NSImage.SymbolConfiguration(pointSize: 14, weight: .medium)
            if let image = NSImage(systemSymbolName: "building.2.fill", accessibilityDescription: "LAYA Admin") {
                let configuredImage = image.withSymbolConfiguration(config)
                button.image = configuredImage
            }

            // Add action for menu display
            button.action = #selector(statusItemClicked(_:))
            button.target = self
            button.sendAction(on: [.leftMouseUp, .rightMouseUp])
        }

        // Build the status menu
        updateStatusMenu()
    }

    /// Called when the status item is clicked
    @objc private func statusItemClicked(_ sender: NSStatusBarButton) {
        // Update menu items based on current state before showing
        updateStatusMenu()
    }

    /// Updates the status item menu with current state
    private func updateStatusMenu() {
        let menu = NSMenu()
        menu.autoenablesItems = false

        // Header
        let headerItem = NSMenuItem(title: "LAYA Admin", action: nil, keyEquivalent: "")
        headerItem.isEnabled = false
        if let font = NSFont.boldSystemFont(ofSize: 13) as NSFont? {
            headerItem.attributedTitle = NSAttributedString(
                string: "LAYA Admin",
                attributes: [.font: font]
            )
        }
        menu.addItem(headerItem)

        // Connection status
        let statusItem = NSMenuItem(
            title: "Status: Connected",
            action: nil,
            keyEquivalent: ""
        )
        statusItem.isEnabled = false
        menu.addItem(statusItem)

        menu.addItem(NSMenuItem.separator())

        // Check if authenticated
        let isAuthenticated = AuthService.shared.isAuthenticated

        if isAuthenticated {
            // Navigation section
            let dashboardItem = NSMenuItem(
                title: "Dashboard",
                action: #selector(showDashboard),
                keyEquivalent: "1"
            )
            dashboardItem.keyEquivalentModifierMask = .command
            dashboardItem.target = self
            menu.addItem(dashboardItem)

            let childrenItem = NSMenuItem(
                title: "Children",
                action: #selector(showChildren),
                keyEquivalent: "2"
            )
            childrenItem.keyEquivalentModifierMask = .command
            childrenItem.target = self
            menu.addItem(childrenItem)

            let staffItem = NSMenuItem(
                title: "Staff",
                action: #selector(showStaff),
                keyEquivalent: "3"
            )
            staffItem.keyEquivalentModifierMask = .command
            staffItem.target = self
            menu.addItem(staffItem)

            let financeItem = NSMenuItem(
                title: "Finance",
                action: #selector(showFinance),
                keyEquivalent: "4"
            )
            financeItem.keyEquivalentModifierMask = .command
            financeItem.target = self
            menu.addItem(financeItem)

            menu.addItem(NSMenuItem.separator())

            // Quick actions section
            let newChildItem = NSMenuItem(
                title: "New Child",
                action: #selector(newChild),
                keyEquivalent: "n"
            )
            newChildItem.keyEquivalentModifierMask = .command
            newChildItem.target = self
            menu.addItem(newChildItem)

            let newStaffItem = NSMenuItem(
                title: "New Staff Member",
                action: #selector(newStaffMember),
                keyEquivalent: "n"
            )
            newStaffItem.keyEquivalentModifierMask = [.command, .shift]
            newStaffItem.target = self
            menu.addItem(newStaffItem)

            menu.addItem(NSMenuItem.separator())
        }

        // Open main window
        let openItem = NSMenuItem(
            title: "Open LAYA Admin",
            action: #selector(openMainWindow),
            keyEquivalent: "o"
        )
        openItem.keyEquivalentModifierMask = .command
        openItem.target = self
        menu.addItem(openItem)

        if isAuthenticated {
            // Settings
            let settingsItem = NSMenuItem(
                title: "Settings...",
                action: #selector(openSettings),
                keyEquivalent: ","
            )
            settingsItem.keyEquivalentModifierMask = .command
            settingsItem.target = self
            menu.addItem(settingsItem)

            // Sign out
            let signOutItem = NSMenuItem(
                title: "Sign Out",
                action: #selector(signOut),
                keyEquivalent: ""
            )
            signOutItem.target = self
            menu.addItem(signOutItem)
        }

        menu.addItem(NSMenuItem.separator())

        // Quit
        let quitItem = NSMenuItem(
            title: "Quit LAYA Admin",
            action: #selector(quitApp),
            keyEquivalent: "q"
        )
        quitItem.keyEquivalentModifierMask = .command
        quitItem.target = self
        menu.addItem(quitItem)

        // Assign menu to status item
        self.statusItem?.menu = menu
    }

    // MARK: - Menu Actions

    @objc private func showDashboard() {
        openMainWindow()
        NotificationCenter.default.post(name: .showDashboard, object: nil)
    }

    @objc private func showChildren() {
        openMainWindow()
        NotificationCenter.default.post(name: .showChildren, object: nil)
    }

    @objc private func showStaff() {
        openMainWindow()
        NotificationCenter.default.post(name: .showStaff, object: nil)
    }

    @objc private func showFinance() {
        openMainWindow()
        NotificationCenter.default.post(name: .showFinance, object: nil)
    }

    @objc private func newChild() {
        openMainWindow()
        NotificationCenter.default.post(name: .newChild, object: nil)
    }

    @objc private func newStaffMember() {
        openMainWindow()
        NotificationCenter.default.post(name: .newStaff, object: nil)
    }

    @objc private func openMainWindow() {
        NSApp.activate(ignoringOtherApps: true)

        // Find and show the main window
        for window in NSApp.windows {
            if !(window is NSPanel) {
                window.makeKeyAndOrderFront(self)
                return
            }
        }

        // If no window exists, the WindowGroup will create one
        NSApp.setActivationPolicy(.regular)
    }

    @objc private func openSettings() {
        NSApp.activate(ignoringOtherApps: true)
        // Use the appropriate selector based on macOS version
        if #available(macOS 14, *) {
            NSApp.sendAction(Selector(("showSettingsWindow:")), to: nil, from: nil)
        } else {
            NSApp.sendAction(Selector(("showPreferencesWindow:")), to: nil, from: nil)
        }
    }

    @objc private func signOut() {
        Task { @MainActor in
            await AuthService.shared.logout()
            // Update menu after sign out
            updateStatusMenu()
        }
    }

    @objc private func quitApp() {
        NSApplication.shared.terminate(nil)
    }

    // MARK: - Appearance Configuration

    private func configureAppearance() {
        // Allow the app to follow system appearance (Dark/Light mode)
    }

    // MARK: - Notification Setup

    /// Sets up the notification service and requests permission if needed
    private func setupNotifications() {
        // Request notification permission on first launch
        let hasRequestedPermission = UserDefaults.standard.bool(forKey: hasRequestedNotificationPermissionKey)

        if !hasRequestedPermission {
            Task { @MainActor in
                do {
                    let granted = try await NotificationService.shared.requestAuthorization()
                    UserDefaults.standard.set(true, forKey: hasRequestedNotificationPermissionKey)

                    if granted {
                        // Notification permission granted on first launch
                    }
                } catch {
                    // Handle permission error silently
                    UserDefaults.standard.set(true, forKey: hasRequestedNotificationPermissionKey)
                }
            }
        }
    }

    /// Registers handlers for notification actions
    private func registerNotificationHandlers() {
        // Handle navigation to alert
        NotificationCenter.default.addObserver(
            forName: .navigateToAlert,
            object: nil,
            queue: .main
        ) { [weak self] notification in
            guard let alertId = notification.userInfo?["alertId"] as? String else { return }
            // Post a notification to show the alert in the UI
            self?.openMainWindow()
            NotificationCenter.default.post(name: .showDashboard, object: nil)
            // Additional navigation logic would go here
            _ = alertId // Use alertId for navigation
        }

        // Handle alert acknowledgment
        NotificationCenter.default.addObserver(
            forName: .acknowledgeAlert,
            object: nil,
            queue: .main
        ) { notification in
            guard let alertId = notification.userInfo?["alertId"] as? String else { return }
            // Handle alert acknowledgment
            // This would typically call an API to mark the alert as acknowledged
            _ = alertId // Use alertId for acknowledgment
        }

        // Handle auth state changes to update menu
        NotificationCenter.default.addObserver(
            forName: .authStateChanged,
            object: nil,
            queue: .main
        ) { [weak self] _ in
            self?.updateStatusMenu()
        }
    }

    // MARK: - Status Item Badge Updates

    /// Updates the status item icon to show alert state
    /// - Parameters:
    ///   - hasAlerts: Whether there are pending alerts
    ///   - isConnected: Whether the app is connected to the server
    func updateStatusItemIcon(hasAlerts: Bool, isConnected: Bool) {
        guard let button = statusItem?.button else { return }

        let symbolName: String
        if !isConnected {
            symbolName = "exclamationmark.icloud.fill"
        } else if hasAlerts {
            symbolName = "bell.badge.fill"
        } else {
            symbolName = "building.2.fill"
        }

        let config = NSImage.SymbolConfiguration(pointSize: 14, weight: .medium)
        if let image = NSImage(systemSymbolName: symbolName, accessibilityDescription: "LAYA Admin") {
            button.image = image.withSymbolConfiguration(config)
        }
    }
}

// MARK: - Notification Name Extension

extension Notification.Name {
    /// Posted when authentication state changes
    static let authStateChanged = Notification.Name("authStateChanged")
}
