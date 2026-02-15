//
//  ContentView.swift
//  LAYAAdmin
//
//  Main content view with navigation sidebar for the LAYA Admin application.
//

import SwiftUI

/// Main content view providing sidebar navigation and content area.
/// Implements the primary navigation structure for the admin application.
struct ContentView: View {

    // MARK: - State

    @State private var selectedSection: NavigationSection? = .dashboard
    @State private var columnVisibility: NavigationSplitViewVisibility = .all

    // MARK: - Body

    var body: some View {
        NavigationSplitView(columnVisibility: $columnVisibility) {
            SidebarView(selection: $selectedSection)
        } detail: {
            DetailView(section: selectedSection)
        }
        .navigationSplitViewStyle(.balanced)
        .frame(minWidth: 900, minHeight: 600)
        .onReceive(NotificationCenter.default.publisher(for: .showDashboard)) { _ in
            selectedSection = .dashboard
        }
        .onReceive(NotificationCenter.default.publisher(for: .showChildren)) { _ in
            selectedSection = .children
        }
        .onReceive(NotificationCenter.default.publisher(for: .showStaff)) { _ in
            selectedSection = .staff
        }
        .onReceive(NotificationCenter.default.publisher(for: .showFinance)) { _ in
            selectedSection = .finance
        }
    }
}

// MARK: - Navigation Section

/// Enumeration of available navigation sections in the app.
enum NavigationSection: String, CaseIterable, Identifiable {
    case dashboard = "Dashboard"
    case children = "Children"
    case staff = "Staff"
    case finance = "Finance"
    case analytics = "Analytics"
    case settings = "Settings"

    var id: String { rawValue }

    var icon: String {
        switch self {
        case .dashboard: return "square.grid.2x2"
        case .children: return "person.2"
        case .staff: return "person.badge.key"
        case .finance: return "dollarsign.circle"
        case .analytics: return "chart.bar"
        case .settings: return "gear"
        }
    }

    var localizedTitle: String {
        // Localization will be implemented in a later subtask
        return rawValue
    }
}

// MARK: - Sidebar View

/// Sidebar navigation view showing all available sections.
struct SidebarView: View {
    @Binding var selection: NavigationSection?

    var body: some View {
        List(selection: $selection) {
            Section("Main") {
                ForEach([NavigationSection.dashboard]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }

            Section("Management") {
                ForEach([NavigationSection.children, .staff]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }

            Section("Finance & Analytics") {
                ForEach([NavigationSection.finance, .analytics]) { section in
                    NavigationLink(value: section) {
                        Label(section.localizedTitle, systemImage: section.icon)
                    }
                }
            }
        }
        .listStyle(.sidebar)
        .frame(minWidth: 200)
        .toolbar {
            ToolbarItem {
                Button(action: toggleSidebar) {
                    Image(systemName: "sidebar.left")
                }
            }
        }
    }

    private func toggleSidebar() {
        NSApp.keyWindow?.firstResponder?.tryToPerform(
            #selector(NSSplitViewController.toggleSidebar(_:)),
            with: nil
        )
    }
}

// MARK: - Detail View

/// Detail view that displays content based on the selected navigation section.
struct DetailView: View {
    let section: NavigationSection?

    var body: some View {
        Group {
            switch section {
            case .dashboard:
                DashboardView()
            case .children:
                ChildListView()
            case .staff:
                StaffPlaceholderView()
            case .finance:
                FinancePlaceholderView()
            case .analytics:
                AnalyticsPlaceholderView()
            case .settings:
                SettingsPlaceholderView()
            case .none:
                WelcomeView()
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

// MARK: - Placeholder Views

// Note: DashboardPlaceholderView has been replaced with the full DashboardView implementation

/// Placeholder for the children management view.
struct ChildrenPlaceholderView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "person.2")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)

            Text("Child Management")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("Manage enrolled children and enrollment status")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

/// Placeholder for the staff management view.
struct StaffPlaceholderView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "person.badge.key")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)

            Text("Staff Management")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("Manage staff records and scheduling")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

/// Placeholder for the finance view.
struct FinancePlaceholderView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "dollarsign.circle")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)

            Text("Finance")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("Invoice management and Releve 24 export")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

/// Placeholder for the analytics view.
struct AnalyticsPlaceholderView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "chart.bar")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)

            Text("AI Analytics")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("Business intelligence and forecasting")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

/// Placeholder for the settings view.
struct SettingsPlaceholderView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "gear")
                .font(.system(size: 64))
                .foregroundColor(.accentColor)

            Text("Settings")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("App configuration and preferences")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

/// Welcome view shown when no section is selected.
struct WelcomeView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "building.2")
                .font(.system(size: 80))
                .foregroundColor(.accentColor)

            Text("Welcome to LAYA Admin")
                .font(.largeTitle)
                .fontWeight(.bold)

            Text("Select an item from the sidebar to get started")
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
    }
}

// MARK: - Preview

#Preview {
    ContentView()
}
