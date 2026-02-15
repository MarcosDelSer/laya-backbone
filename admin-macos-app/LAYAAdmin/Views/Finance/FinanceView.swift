//
//  FinanceView.swift
//  LAYAAdmin
//
//  Main Finance module view that organizes invoice management and RL-24 tax slips.
//  Provides tabbed navigation between Invoices and Releve 24 sections.
//

import SwiftUI

// MARK: - Finance View

/// Main view for the Finance module with tabbed navigation.
///
/// Features:
/// - Tab navigation between Invoices and RL-24 sections
/// - Preserves tab selection state
/// - Integrates with InvoiceListView and Releve24View
struct FinanceView: View {

    // MARK: - Properties

    /// Currently selected finance tab
    @State private var selectedTab: FinanceTab = .invoices

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    // MARK: - Body

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Tab selector
                tabSelector

                Divider()

                // Tab content
                tabContent
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .background(Color(NSColor.windowBackgroundColor))
        }
        .onReceive(NotificationCenter.default.publisher(for: .showInvoices)) { _ in
            selectedTab = .invoices
        }
        .onReceive(NotificationCenter.default.publisher(for: .showReleve24)) { _ in
            selectedTab = .releve24
        }
    }

    // MARK: - Tab Selector

    private var tabSelector: some View {
        HStack(spacing: 0) {
            ForEach(FinanceTab.allCases) { tab in
                Button(action: {
                    withAnimation(.easeInOut(duration: 0.2)) {
                        selectedTab = tab
                    }
                }) {
                    HStack(spacing: 8) {
                        Image(systemName: tab.icon)
                            .font(.system(size: 14, weight: .medium))

                        Text(tab.title)
                            .font(.subheadline)
                            .fontWeight(.medium)
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 10)
                    .background(
                        selectedTab == tab
                            ? Color.accentColor.opacity(0.1)
                            : Color.clear
                    )
                    .foregroundColor(selectedTab == tab ? .accentColor : .secondary)
                    .cornerRadius(8)
                }
                .buttonStyle(.plain)
            }

            Spacer()
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 8)
        .background(Color(NSColor.controlBackgroundColor))
    }

    // MARK: - Tab Content

    @ViewBuilder
    private var tabContent: some View {
        switch selectedTab {
        case .invoices:
            InvoiceListView()
        case .releve24:
            Releve24View()
        }
    }
}

// MARK: - Finance Tab

/// Available tabs in the Finance module
enum FinanceTab: String, CaseIterable, Identifiable {
    case invoices = "invoices"
    case releve24 = "releve24"

    var id: String { rawValue }

    var title: String {
        switch self {
        case .invoices:
            return String(localized: "Invoices")
        case .releve24:
            return String(localized: "RL-24 Tax Slips")
        }
    }

    var icon: String {
        switch self {
        case .invoices:
            return "doc.text"
        case .releve24:
            return "doc.badge.gearshape"
        }
    }
}

// MARK: - Notification Names

extension Notification.Name {
    /// Posted when the app should show the Invoices tab
    static let showInvoices = Notification.Name("showInvoices")

    /// Posted when the app should show the RL-24 tab
    static let showReleve24 = Notification.Name("showReleve24")
}

// MARK: - Preview

#Preview("Finance View - Invoices") {
    FinanceView()
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 900, height: 600)
}

#Preview("Finance View - RL-24") {
    FinanceView()
        .environmentObject(AuthViewModel.previewAuthenticated)
        .frame(width: 900, height: 600)
}
