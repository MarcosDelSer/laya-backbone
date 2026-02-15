//
//  GibbonClient.swift
//  LAYAAdmin
//
//  Type-safe Gibbon CMS API client for LAYA Admin.
//  Provides methods for interacting with the Gibbon CMS backend
//  for children, staff, invoices, payments, and RL-24 tax slips.
//

import Foundation

// MARK: - Gibbon Client

/// Type-safe client for the Gibbon CMS API.
///
/// Provides high-level methods for CRUD operations on:
/// - Children (students)
/// - Staff members
/// - Invoices and payments
/// - RL-24 tax slips
///
/// Uses `APIService.shared` for all network requests.
@MainActor
final class GibbonClient {

    // MARK: - Singleton

    /// Shared instance of the Gibbon client
    static let shared = GibbonClient()

    // MARK: - Properties

    /// The API service used for network requests
    private let apiService: APIService

    // MARK: - Initialization

    /// Creates a new Gibbon client instance
    /// - Parameter apiService: The API service to use (defaults to shared instance)
    init(apiService: APIService = .shared) {
        self.apiService = apiService
    }

    // MARK: - Children API

    /// Fetches all children with optional filters
    /// - Parameters:
    ///   - status: Filter by enrollment status
    ///   - classroomId: Filter by classroom
    ///   - skip: Number of items to skip (pagination)
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of children
    func fetchChildren(
        status: EnrollmentStatus? = nil,
        classroomId: String? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<Child> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit
        ]

        if let status = status {
            params["status"] = status.rawValue
        }
        if let classroomId = classroomId {
            params["classroom_id"] = classroomId
        }

        return try await apiService.get(GibbonEndpoints.students, parameters: params)
    }

    /// Fetches a specific child by ID
    /// - Parameter childId: The child's unique identifier
    /// - Returns: The child details
    func fetchChild(childId: String) async throws -> Child {
        return try await apiService.get("\(GibbonEndpoints.students)/\(childId)")
    }

    /// Creates a new child record
    /// - Parameter request: The child creation request
    /// - Returns: The created child
    func createChild(_ request: ChildRequest) async throws -> Child {
        return try await apiService.post(GibbonEndpoints.students, body: request)
    }

    /// Updates an existing child record
    /// - Parameters:
    ///   - childId: The child's unique identifier
    ///   - request: The child update request
    /// - Returns: The updated child
    func updateChild(childId: String, request: ChildRequest) async throws -> Child {
        return try await apiService.put("\(GibbonEndpoints.students)/\(childId)", body: request)
    }

    /// Deletes a child record
    /// - Parameter childId: The child's unique identifier
    func deleteChild(childId: String) async throws {
        try await apiService.delete("\(GibbonEndpoints.students)/\(childId)")
    }

    /// Fetches all active children
    /// - Returns: Array of active children
    func fetchActiveChildren() async throws -> [Child] {
        let response: PaginatedResponse<Child> = try await fetchChildren(
            status: .active,
            limit: AppConstants.maxPageSize
        )
        return response.items
    }

    /// Fetches child summaries for list views
    /// - Parameters:
    ///   - skip: Number of items to skip
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of child summaries
    func fetchChildSummaries(
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<ChildSummary> {
        let params: [String: Any] = [
            "skip": skip,
            "limit": limit,
            "summary": true
        ]
        return try await apiService.get(GibbonEndpoints.students, parameters: params)
    }

    // MARK: - Staff API

    /// Fetches all staff members with optional filters
    /// - Parameters:
    ///   - status: Filter by employment status
    ///   - role: Filter by staff role
    ///   - classroomId: Filter by assigned classroom
    ///   - skip: Number of items to skip (pagination)
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of staff members
    func fetchStaff(
        status: StaffStatus? = nil,
        role: StaffRole? = nil,
        classroomId: String? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<Staff> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit
        ]

        if let status = status {
            params["status"] = status.rawValue
        }
        if let role = role {
            params["role"] = role.rawValue
        }
        if let classroomId = classroomId {
            params["classroom_id"] = classroomId
        }

        return try await apiService.get(GibbonEndpoints.staff, parameters: params)
    }

    /// Fetches a specific staff member by ID
    /// - Parameter staffId: The staff member's unique identifier
    /// - Returns: The staff member details
    func fetchStaffMember(staffId: String) async throws -> Staff {
        return try await apiService.get("\(GibbonEndpoints.staff)/\(staffId)")
    }

    /// Creates a new staff member record
    /// - Parameter request: The staff creation request
    /// - Returns: The created staff member
    func createStaffMember(_ request: StaffRequest) async throws -> Staff {
        return try await apiService.post(GibbonEndpoints.staff, body: request)
    }

    /// Updates an existing staff member record
    /// - Parameters:
    ///   - staffId: The staff member's unique identifier
    ///   - request: The staff update request
    /// - Returns: The updated staff member
    func updateStaffMember(staffId: String, request: StaffRequest) async throws -> Staff {
        return try await apiService.put("\(GibbonEndpoints.staff)/\(staffId)", body: request)
    }

    /// Deletes a staff member record
    /// - Parameter staffId: The staff member's unique identifier
    func deleteStaffMember(staffId: String) async throws {
        try await apiService.delete("\(GibbonEndpoints.staff)/\(staffId)")
    }

    /// Fetches all active staff members
    /// - Returns: Array of active staff members
    func fetchActiveStaff() async throws -> [Staff] {
        let response: PaginatedResponse<Staff> = try await fetchStaff(
            status: .active,
            limit: AppConstants.maxPageSize
        )
        return response.items
    }

    /// Fetches staff summaries for list views
    /// - Parameters:
    ///   - skip: Number of items to skip
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of staff summaries
    func fetchStaffSummaries(
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<StaffSummary> {
        let params: [String: Any] = [
            "skip": skip,
            "limit": limit,
            "summary": true
        ]
        return try await apiService.get(GibbonEndpoints.staff, parameters: params)
    }

    // MARK: - Invoice API

    /// Fetches all invoices with optional filters
    /// - Parameters:
    ///   - status: Filter by invoice status
    ///   - familyId: Filter by family
    ///   - childId: Filter by child
    ///   - startDate: Filter by date range (start)
    ///   - endDate: Filter by date range (end)
    ///   - skip: Number of items to skip (pagination)
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of invoices
    func fetchInvoices(
        status: InvoiceStatus? = nil,
        familyId: String? = nil,
        childId: String? = nil,
        startDate: Date? = nil,
        endDate: Date? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<Invoice> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit
        ]

        if let status = status {
            params["status"] = status.rawValue
        }
        if let familyId = familyId {
            params["family_id"] = familyId
        }
        if let childId = childId {
            params["child_id"] = childId
        }
        if let startDate = startDate {
            params["start_date"] = startDate.iso8601String
        }
        if let endDate = endDate {
            params["end_date"] = endDate.iso8601String
        }

        return try await apiService.get(GibbonEndpoints.invoices, parameters: params)
    }

    /// Fetches a specific invoice by ID
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: The invoice details
    func fetchInvoice(invoiceId: String) async throws -> Invoice {
        return try await apiService.get("\(GibbonEndpoints.invoices)/\(invoiceId)")
    }

    /// Creates a new invoice
    /// - Parameter request: The invoice creation request
    /// - Returns: The created invoice
    func createInvoice(_ request: InvoiceRequest) async throws -> Invoice {
        return try await apiService.post(GibbonEndpoints.invoices, body: request)
    }

    /// Updates an existing invoice
    /// - Parameters:
    ///   - invoiceId: The invoice's unique identifier
    ///   - request: The invoice update request
    /// - Returns: The updated invoice
    func updateInvoice(invoiceId: String, request: InvoiceRequest) async throws -> Invoice {
        return try await apiService.put("\(GibbonEndpoints.invoices)/\(invoiceId)", body: request)
    }

    /// Deletes an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    func deleteInvoice(invoiceId: String) async throws {
        try await apiService.delete("\(GibbonEndpoints.invoices)/\(invoiceId)")
    }

    /// Fetches invoice summaries for list views
    /// - Parameters:
    ///   - status: Filter by invoice status
    ///   - skip: Number of items to skip
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of invoice summaries
    func fetchInvoiceSummaries(
        status: InvoiceStatus? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<InvoiceSummary> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit,
            "summary": true
        ]

        if let status = status {
            params["status"] = status.rawValue
        }

        return try await apiService.get(GibbonEndpoints.invoices, parameters: params)
    }

    /// Gets the PDF download URL for an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: The URL to download the invoice PDF
    func getInvoicePdfUrl(invoiceId: String) -> URL? {
        let path = "\(GibbonEndpoints.invoices)/\(invoiceId)/pdf"
        return URL(string: "\(apiService.baseURL)\(path)")
    }

    /// Fetches invoice summary statistics
    /// - Returns: Finance summary with aggregated invoice data
    func fetchFinanceSummary() async throws -> FinanceSummary {
        return try await apiService.get("\(GibbonEndpoints.invoices)/summary")
    }

    /// Fetches overdue invoices
    /// - Parameter limit: Maximum number of items to return
    /// - Returns: Array of overdue invoices
    func fetchOverdueInvoices(limit: Int = 10) async throws -> [Invoice] {
        let response: PaginatedResponse<Invoice> = try await fetchInvoices(
            status: .overdue,
            limit: limit
        )
        return response.items
    }

    // MARK: - Payment API

    /// Fetches payments for an invoice
    /// - Parameter invoiceId: The invoice's unique identifier
    /// - Returns: Array of payments for the invoice
    func fetchPayments(invoiceId: String) async throws -> [Payment] {
        let params: [String: Any] = ["invoice_id": invoiceId]
        let response: PaginatedResponse<Payment> = try await apiService.get(
            GibbonEndpoints.payments,
            parameters: params
        )
        return response.items
    }

    /// Records a new payment
    /// - Parameter request: The payment request
    /// - Returns: The recorded payment
    func recordPayment(_ request: PaymentRequest) async throws -> Payment {
        return try await apiService.post(GibbonEndpoints.payments, body: request)
    }

    /// Deletes a payment
    /// - Parameter paymentId: The payment's unique identifier
    func deletePayment(paymentId: String) async throws {
        try await apiService.delete("\(GibbonEndpoints.payments)/\(paymentId)")
    }

    // MARK: - RL-24 API

    /// Fetches all RL-24 slips with optional filters
    /// - Parameters:
    ///   - taxYear: Filter by tax year
    ///   - familyId: Filter by family
    ///   - childId: Filter by child
    ///   - status: Filter by status
    ///   - skip: Number of items to skip (pagination)
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of RL-24 slips
    func fetchReleve24s(
        taxYear: Int? = nil,
        familyId: String? = nil,
        childId: String? = nil,
        status: Releve24Status? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<Releve24> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit
        ]

        if let taxYear = taxYear {
            params["tax_year"] = taxYear
        }
        if let familyId = familyId {
            params["family_id"] = familyId
        }
        if let childId = childId {
            params["child_id"] = childId
        }
        if let status = status {
            params["status"] = status.rawValue
        }

        return try await apiService.get(GibbonEndpoints.releve24, parameters: params)
    }

    /// Fetches a specific RL-24 by ID
    /// - Parameter releve24Id: The RL-24's unique identifier
    /// - Returns: The RL-24 details
    func fetchReleve24(releve24Id: String) async throws -> Releve24 {
        return try await apiService.get("\(GibbonEndpoints.releve24)/\(releve24Id)")
    }

    /// Calculates RL-24 values for a child and tax year
    /// - Parameters:
    ///   - childId: The child's unique identifier
    ///   - familyId: The family's unique identifier
    ///   - taxYear: The tax year to calculate
    /// - Returns: The calculated RL-24 values
    func calculateReleve24(
        childId: String,
        familyId: String,
        taxYear: Int
    ) async throws -> Releve24Calculation {
        let params: [String: Any] = [
            "child_id": childId,
            "family_id": familyId,
            "tax_year": taxYear
        ]
        return try await apiService.get("\(GibbonEndpoints.releve24)/calculate", parameters: params)
    }

    /// Generates a new RL-24 slip
    /// - Parameter request: The RL-24 generation request
    /// - Returns: The generated RL-24
    func generateReleve24(_ request: Releve24Request) async throws -> Releve24 {
        return try await apiService.post(GibbonEndpoints.releve24, body: request)
    }

    /// Exports an RL-24 to PDF
    /// - Parameter request: The export request
    /// - Returns: The updated RL-24 with PDF URL
    func exportReleve24(_ request: Releve24ExportRequest) async throws -> Releve24 {
        return try await apiService.post(GibbonEndpoints.releve24Export, body: request)
    }

    /// Batch exports multiple RL-24 slips
    /// - Parameter request: The batch export request
    /// - Returns: Array of exported RL-24 summaries
    func batchExportReleve24(_ request: Releve24BatchExportRequest) async throws -> [Releve24Summary] {
        return try await apiService.post("\(GibbonEndpoints.releve24Export)/batch", body: request)
    }

    /// Gets the PDF download URL for an RL-24
    /// - Parameter releve24Id: The RL-24's unique identifier
    /// - Returns: The URL to download the RL-24 PDF
    func getReleve24PdfUrl(releve24Id: String) -> URL? {
        let path = "\(GibbonEndpoints.releve24)/\(releve24Id)/pdf"
        return URL(string: "\(apiService.baseURL)\(path)")
    }

    /// Fetches RL-24 summaries for list views
    /// - Parameters:
    ///   - taxYear: Filter by tax year
    ///   - skip: Number of items to skip
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of RL-24 summaries
    func fetchReleve24Summaries(
        taxYear: Int? = nil,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<Releve24Summary> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit,
            "summary": true
        ]

        if let taxYear = taxYear {
            params["tax_year"] = taxYear
        }

        return try await apiService.get(GibbonEndpoints.releve24, parameters: params)
    }

    // MARK: - Dashboard API

    /// Fetches dashboard data
    /// - Returns: Dashboard summary information
    func fetchDashboard() async throws -> DashboardData {
        return try await apiService.get(GibbonEndpoints.dashboard)
    }

    // MARK: - Notifications API

    /// Fetches notifications
    /// - Parameters:
    ///   - unreadOnly: Whether to fetch only unread notifications
    ///   - skip: Number of items to skip
    ///   - limit: Maximum number of items to return
    /// - Returns: Paginated list of notifications
    func fetchNotifications(
        unreadOnly: Bool = false,
        skip: Int = 0,
        limit: Int = AppConstants.defaultPageSize
    ) async throws -> PaginatedResponse<NotificationItem> {
        var params: [String: Any] = [
            "skip": skip,
            "limit": limit
        ]

        if unreadOnly {
            params["unread_only"] = true
        }

        return try await apiService.get(GibbonEndpoints.notifications, parameters: params)
    }

    /// Marks a notification as read
    /// - Parameter notificationId: The notification's unique identifier
    func markNotificationAsRead(notificationId: String) async throws {
        try await apiService.post("\(GibbonEndpoints.notifications)/\(notificationId)/read")
    }

    /// Gets the count of unread notifications
    /// - Returns: Number of unread notifications
    func fetchUnreadNotificationCount() async throws -> Int {
        let response: NotificationCountResponse = try await apiService.get(
            "\(GibbonEndpoints.notifications)/count"
        )
        return response.count
    }
}

// MARK: - Dashboard Data

/// Dashboard data from Gibbon CMS
struct DashboardData: Codable, Equatable {

    /// Total number of enrolled children
    let totalChildren: Int

    /// Number of active children
    let activeChildren: Int

    /// Total number of staff members
    let totalStaff: Int

    /// Number of active staff members
    let activeStaff: Int

    /// Finance summary
    let financeSummary: FinanceSummary?

    /// Recent alerts
    let alerts: [DashboardAlert]?

    /// Today's attendance count
    let todayAttendance: Int?

    /// Current capacity utilization
    let capacityUtilization: Double?

    enum CodingKeys: String, CodingKey {
        case totalChildren = "total_children"
        case activeChildren = "active_children"
        case totalStaff = "total_staff"
        case activeStaff = "active_staff"
        case financeSummary = "finance_summary"
        case alerts
        case todayAttendance = "today_attendance"
        case capacityUtilization = "capacity_utilization"
    }
}

// MARK: - Notification Item

/// A notification item from the system
struct NotificationItem: Identifiable, Codable, Equatable {

    /// Unique identifier
    let id: String

    /// Notification title
    let title: String

    /// Notification message
    let message: String

    /// Notification type
    let type: NotificationType

    /// Whether the notification has been read
    var isRead: Bool

    /// Timestamp when the notification was created
    let createdAt: Date

    /// Related entity ID (if applicable)
    let relatedEntityId: String?

    /// Related entity type (if applicable)
    let relatedEntityType: String?

    enum CodingKeys: String, CodingKey {
        case id
        case title
        case message
        case type
        case isRead = "is_read"
        case createdAt = "created_at"
        case relatedEntityId = "related_entity_id"
        case relatedEntityType = "related_entity_type"
    }
}

// MARK: - Notification Type

/// Types of notifications
enum NotificationType: String, Codable, CaseIterable {
    case info = "info"
    case warning = "warning"
    case error = "error"
    case success = "success"
    case reminder = "reminder"

    var displayName: String {
        switch self {
        case .info:
            return String(localized: "Information")
        case .warning:
            return String(localized: "Warning")
        case .error:
            return String(localized: "Error")
        case .success:
            return String(localized: "Success")
        case .reminder:
            return String(localized: "Reminder")
        }
    }
}

// MARK: - Notification Count Response

/// Response for notification count endpoint
struct NotificationCountResponse: Codable {
    let count: Int
}

// MARK: - Gibbon Client Extensions

extension GibbonClient {

    /// Fetches all pages of children
    /// - Parameter status: Filter by enrollment status
    /// - Returns: All children matching the filter
    func fetchAllChildren(status: EnrollmentStatus? = nil) async throws -> [Child] {
        var allChildren: [Child] = []
        var skip = 0
        var hasMore = true

        while hasMore {
            let response = try await fetchChildren(
                status: status,
                skip: skip,
                limit: AppConstants.defaultPageSize
            )
            allChildren.append(contentsOf: response.items)
            hasMore = response.hasMore
            skip += AppConstants.defaultPageSize
        }

        return allChildren
    }

    /// Fetches all pages of staff members
    /// - Parameter status: Filter by employment status
    /// - Returns: All staff members matching the filter
    func fetchAllStaff(status: StaffStatus? = nil) async throws -> [Staff] {
        var allStaff: [Staff] = []
        var skip = 0
        var hasMore = true

        while hasMore {
            let response = try await fetchStaff(
                status: status,
                skip: skip,
                limit: AppConstants.defaultPageSize
            )
            allStaff.append(contentsOf: response.items)
            hasMore = response.hasMore
            skip += AppConstants.defaultPageSize
        }

        return allStaff
    }

    /// Fetches all pages of invoices
    /// - Parameters:
    ///   - status: Filter by invoice status
    ///   - familyId: Filter by family
    /// - Returns: All invoices matching the filters
    func fetchAllInvoices(
        status: InvoiceStatus? = nil,
        familyId: String? = nil
    ) async throws -> [Invoice] {
        var allInvoices: [Invoice] = []
        var skip = 0
        var hasMore = true

        while hasMore {
            let response = try await fetchInvoices(
                status: status,
                familyId: familyId,
                skip: skip,
                limit: AppConstants.defaultPageSize
            )
            allInvoices.append(contentsOf: response.items)
            hasMore = response.hasMore
            skip += AppConstants.defaultPageSize
        }

        return allInvoices
    }
}
