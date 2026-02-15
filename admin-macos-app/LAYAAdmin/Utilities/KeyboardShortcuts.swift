//
//  KeyboardShortcuts.swift
//  LAYAAdmin
//
//  Centralized keyboard shortcut definitions and command groups for the macOS app.
//  Provides organized, discoverable shortcuts for navigation, actions, and editing.
//

import SwiftUI

// MARK: - Keyboard Shortcuts

/// Centralized keyboard shortcut definitions for the LAYA Admin app.
///
/// Usage:
/// - Access shortcuts via static properties: `KeyboardShortcuts.Navigation.dashboard`
/// - Apply to views: `.keyboardShortcut(KeyboardShortcuts.Navigation.dashboard)`
/// - Use command groups: `AppCommands.navigationCommands()`
enum KeyboardShortcuts {

    // MARK: - Navigation Shortcuts

    /// Shortcuts for navigating between app sections
    enum Navigation {
        /// Show Dashboard (Cmd+1)
        static let dashboard = KeyboardShortcut("1", modifiers: .command)

        /// Show Children list (Cmd+2)
        static let children = KeyboardShortcut("2", modifiers: .command)

        /// Show Staff list (Cmd+3)
        static let staff = KeyboardShortcut("3", modifiers: .command)

        /// Show Finance view (Cmd+4)
        static let finance = KeyboardShortcut("4", modifiers: .command)

        /// Show Analytics view (Cmd+5)
        static let analytics = KeyboardShortcut("5", modifiers: .command)

        /// Show Settings (Cmd+,)
        static let settings = KeyboardShortcut(",", modifiers: .command)

        /// Toggle sidebar visibility (Cmd+Ctrl+S)
        static let toggleSidebar = KeyboardShortcut("s", modifiers: [.command, .control])

        /// Go back in navigation (Cmd+[)
        static let goBack = KeyboardShortcut("[", modifiers: .command)

        /// Go forward in navigation (Cmd+])
        static let goForward = KeyboardShortcut("]", modifiers: .command)
    }

    // MARK: - Creation Shortcuts

    /// Shortcuts for creating new items
    enum Creation {
        /// New Child (Cmd+N)
        static let newChild = KeyboardShortcut("n", modifiers: .command)

        /// New Staff Member (Cmd+Shift+N)
        static let newStaff = KeyboardShortcut("n", modifiers: [.command, .shift])

        /// New Invoice (Cmd+Shift+I)
        static let newInvoice = KeyboardShortcut("i", modifiers: [.command, .shift])
    }

    // MARK: - Action Shortcuts

    /// Shortcuts for common actions
    enum Actions {
        /// Refresh current view (Cmd+R)
        static let refresh = KeyboardShortcut("r", modifiers: .command)

        /// Save current item (Cmd+S)
        static let save = KeyboardShortcut("s", modifiers: .command)

        /// Delete selected item (Cmd+Backspace)
        static let delete = KeyboardShortcut(.delete, modifiers: .command)

        /// Print current view (Cmd+P)
        static let print = KeyboardShortcut("p", modifiers: .command)

        /// Export data (Cmd+E)
        static let export = KeyboardShortcut("e", modifiers: .command)

        /// Search/Filter (Cmd+F)
        static let search = KeyboardShortcut("f", modifiers: .command)

        /// Show quick actions (Cmd+K)
        static let quickActions = KeyboardShortcut("k", modifiers: .command)

        /// Sign out (Cmd+Shift+Q)
        static let signOut = KeyboardShortcut("q", modifiers: [.command, .shift])
    }

    // MARK: - Editing Shortcuts

    /// Shortcuts for editing operations
    enum Editing {
        /// Edit selected item (Cmd+E or Enter)
        static let edit = KeyboardShortcut(.return, modifiers: .command)

        /// Cancel editing (Escape)
        static let cancel = KeyboardShortcut(.escape, modifiers: [])

        /// Select all (Cmd+A)
        static let selectAll = KeyboardShortcut("a", modifiers: .command)

        /// Duplicate item (Cmd+D)
        static let duplicate = KeyboardShortcut("d", modifiers: .command)
    }

    // MARK: - View Shortcuts

    /// Shortcuts for view adjustments
    enum View {
        /// Zoom in (Cmd++)
        static let zoomIn = KeyboardShortcut("+", modifiers: .command)

        /// Zoom out (Cmd+-)
        static let zoomOut = KeyboardShortcut("-", modifiers: .command)

        /// Actual size (Cmd+0)
        static let actualSize = KeyboardShortcut("0", modifiers: .command)

        /// Toggle full screen (Cmd+Ctrl+F)
        static let fullScreen = KeyboardShortcut("f", modifiers: [.command, .control])
    }
}

// MARK: - App Commands

/// Command groups for the app menu bar.
///
/// Usage in App struct:
/// ```swift
/// .commands {
///     AppCommands.navigationCommands()
///     AppCommands.creationCommands()
/// }
/// ```
enum AppCommands {

    /// Creates navigation command group for the View menu
    @CommandsBuilder
    static func navigationCommands() -> some Commands {
        CommandGroup(after: .sidebar) {
            Button("Show Dashboard") {
                NotificationCenter.default.post(name: .showDashboard, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.dashboard)

            Button("Show Children") {
                NotificationCenter.default.post(name: .showChildren, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.children)

            Button("Show Staff") {
                NotificationCenter.default.post(name: .showStaff, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.staff)

            Button("Show Finance") {
                NotificationCenter.default.post(name: .showFinance, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.finance)

            Button("Show Analytics") {
                NotificationCenter.default.post(name: .showAnalytics, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.analytics)

            Divider()

            Button("Toggle Sidebar") {
                toggleSidebar()
            }
            .keyboardShortcut(KeyboardShortcuts.Navigation.toggleSidebar)
        }
    }

    /// Creates new item command group for the File menu
    @CommandsBuilder
    static func creationCommands() -> some Commands {
        CommandGroup(replacing: .newItem) {
            Button("New Child") {
                NotificationCenter.default.post(name: .newChild, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Creation.newChild)

            Button("New Staff Member") {
                NotificationCenter.default.post(name: .newStaff, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Creation.newStaff)

            Button("New Invoice") {
                NotificationCenter.default.post(name: .newInvoice, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Creation.newInvoice)
        }
    }

    /// Creates action command group
    @CommandsBuilder
    static func actionCommands() -> some Commands {
        CommandGroup(after: .pasteboard) {
            Button("Refresh") {
                NotificationCenter.default.post(name: .dataRefreshNeeded, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Actions.refresh)

            Button("Export...") {
                NotificationCenter.default.post(name: .exportRequested, object: nil)
            }
            .keyboardShortcut(KeyboardShortcuts.Actions.export)
        }
    }

    /// Toggles the sidebar visibility
    private static func toggleSidebar() {
        NSApp.keyWindow?.firstResponder?.tryToPerform(
            #selector(NSSplitViewController.toggleSidebar(_:)),
            with: nil
        )
    }
}

// MARK: - Notification Names Extension

/// Additional notification names for keyboard shortcut actions
extension Notification.Name {
    /// Posted when analytics view should be shown
    static let showAnalytics = Notification.Name("showAnalytics")

    /// Posted when new invoice creation is requested
    static let newInvoice = Notification.Name("newInvoice")

    /// Posted when export action is triggered
    static let exportRequested = Notification.Name("exportRequested")

    /// Posted when search/filter should be focused
    static let focusSearch = Notification.Name("focusSearch")

    /// Posted when quick actions menu should be shown
    static let showQuickActions = Notification.Name("showQuickActions")
}

// MARK: - View Modifiers

/// A view modifier that applies standard keyboard shortcuts to a view
struct KeyboardShortcutsModifier: ViewModifier {

    /// Binding to the selected navigation section
    @Binding var selectedSection: NavigationSection?

    /// Whether search field should be focused
    var onSearch: (() -> Void)?

    /// Action for refresh
    var onRefresh: (() -> Void)?

    func body(content: Content) -> some View {
        content
            .onReceive(NotificationCenter.default.publisher(for: .showAnalytics)) { _ in
                selectedSection = .analytics
            }
            .onReceive(NotificationCenter.default.publisher(for: .focusSearch)) { _ in
                onSearch?()
            }
            .onReceive(NotificationCenter.default.publisher(for: .dataRefreshNeeded)) { _ in
                onRefresh?()
            }
    }
}

/// View extension for applying keyboard shortcuts
extension View {

    /// Applies standard keyboard shortcut handlers to a view
    /// - Parameters:
    ///   - selectedSection: Binding to the current navigation section
    ///   - onSearch: Optional callback when search is triggered
    ///   - onRefresh: Optional callback when refresh is triggered
    /// - Returns: Modified view with keyboard shortcut handlers
    func withKeyboardShortcuts(
        selectedSection: Binding<NavigationSection?>,
        onSearch: (() -> Void)? = nil,
        onRefresh: (() -> Void)? = nil
    ) -> some View {
        modifier(KeyboardShortcutsModifier(
            selectedSection: selectedSection,
            onSearch: onSearch,
            onRefresh: onRefresh
        ))
    }

    /// Adds a keyboard shortcut with a tooltip hint
    /// - Parameters:
    ///   - shortcut: The keyboard shortcut to apply
    ///   - hint: Optional hint text to show
    /// - Returns: Modified view with shortcut and help
    func keyboardShortcut(_ shortcut: KeyboardShortcut, hint: String) -> some View {
        self
            .keyboardShortcut(shortcut)
            .help(hint)
    }
}

// MARK: - Shortcut Description Helper

/// Extension to provide human-readable descriptions of keyboard shortcuts
extension KeyboardShortcut {

    /// Returns a human-readable description of the shortcut (e.g., "⌘1")
    var displayString: String {
        var result = ""

        // Add modifier symbols
        if modifiers.contains(.command) {
            result += "⌘"
        }
        if modifiers.contains(.shift) {
            result += "⇧"
        }
        if modifiers.contains(.option) {
            result += "⌥"
        }
        if modifiers.contains(.control) {
            result += "⌃"
        }

        // Add key character
        // Note: This is a simplified representation
        // Real implementation would need to handle KeyEquivalent properly
        result += String(describing: key)

        return result
    }
}

// MARK: - Keyboard Shortcut Button Style

/// A button style that displays the keyboard shortcut hint
struct KeyboardShortcutButtonStyle: ButtonStyle {
    let shortcutHint: String

    func makeBody(configuration: Configuration) -> some View {
        HStack {
            configuration.label

            Spacer()

            Text(shortcutHint)
                .font(.caption)
                .foregroundColor(.secondary)
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(
            RoundedRectangle(cornerRadius: 4)
                .fill(configuration.isPressed ? Color.accentColor.opacity(0.2) : Color.clear)
        )
    }
}

// MARK: - Preview

#Preview("Keyboard Shortcut Documentation") {
    VStack(alignment: .leading, spacing: 16) {
        Text("Navigation Shortcuts")
            .font(.headline)

        Group {
            HStack {
                Text("Dashboard")
                Spacer()
                Text("⌘1")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Children")
                Spacer()
                Text("⌘2")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Staff")
                Spacer()
                Text("⌘3")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Finance")
                Spacer()
                Text("⌘4")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Analytics")
                Spacer()
                Text("⌘5")
                    .foregroundColor(.secondary)
            }
        }

        Divider()

        Text("Creation Shortcuts")
            .font(.headline)

        Group {
            HStack {
                Text("New Child")
                Spacer()
                Text("⌘N")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("New Staff")
                Spacer()
                Text("⇧⌘N")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("New Invoice")
                Spacer()
                Text("⇧⌘I")
                    .foregroundColor(.secondary)
            }
        }

        Divider()

        Text("Action Shortcuts")
            .font(.headline)

        Group {
            HStack {
                Text("Refresh")
                Spacer()
                Text("⌘R")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Search")
                Spacer()
                Text("⌘F")
                    .foregroundColor(.secondary)
            }
            HStack {
                Text("Export")
                Spacer()
                Text("⌘E")
                    .foregroundColor(.secondary)
            }
        }
    }
    .padding()
    .frame(width: 300)
}
