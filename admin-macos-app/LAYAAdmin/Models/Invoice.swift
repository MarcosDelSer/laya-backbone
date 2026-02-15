//
//  Invoice.swift
//  LAYAAdmin
//
//  Invoice and payment domain models for the LAYA Admin application.
//

import Foundation

// MARK: - Invoice Model

/// Represents an invoice in the LAYA finance system.
/// Contains billing information, line items, and payment status for
/// childcare services rendered to families.
struct Invoice: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the invoice
    let id: String

    /// Invoice number (e.g., "INV-2026-0001")
    let number: String

    /// Family ID this invoice belongs to
    let familyId: String

    /// Family name for display purposes
    let familyName: String

    /// Child ID associated with this invoice (optional, for child-specific invoices)
    let childId: String?

    /// Child name for display purposes (optional)
    let childName: String?

    /// Invoice date (when the invoice was generated)
    let date: Date

    /// Due date for payment
    let dueDate: Date

    /// Current payment status
    var status: InvoiceStatus

    /// Total amount before tax
    let subtotal: Double

    /// Tax amount (QST + GST)
    let taxAmount: Double

    /// Total amount including tax
    let totalAmount: Double

    /// Amount already paid
    var amountPaid: Double

    /// Outstanding balance (totalAmount - amountPaid)
    var balanceDue: Double {
        max(0, totalAmount - amountPaid)
    }

    /// Invoice line items
    let items: [InvoiceItem]

    /// Billing period start date
    let periodStartDate: Date?

    /// Billing period end date
    let periodEndDate: Date?

    /// URL to the invoice PDF (optional)
    let pdfUrl: String?

    /// Additional notes on the invoice
    let notes: String?

    /// Date when the record was created
    let createdAt: Date?

    /// Date when the record was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case number
        case familyId = "family_id"
        case familyName = "family_name"
        case childId = "child_id"
        case childName = "child_name"
        case date
        case dueDate = "due_date"
        case status
        case subtotal
        case taxAmount = "tax_amount"
        case totalAmount = "total_amount"
        case amountPaid = "amount_paid"
        case items
        case periodStartDate = "period_start_date"
        case periodEndDate = "period_end_date"
        case pdfUrl = "pdf_url"
        case notes
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Formatted total amount string
    var formattedTotal: String {
        totalAmount.asCurrency
    }

    /// Formatted balance due string
    var formattedBalanceDue: String {
        balanceDue.asCurrency
    }

    /// Formatted subtotal string
    var formattedSubtotal: String {
        subtotal.asCurrency
    }

    /// Formatted tax amount string
    var formattedTax: String {
        taxAmount.asCurrency
    }

    /// Formatted amount paid string
    var formattedAmountPaid: String {
        amountPaid.asCurrency
    }

    /// Whether the invoice is fully paid
    var isFullyPaid: Bool {
        balanceDue <= 0
    }

    /// Whether the invoice is past due
    var isPastDue: Bool {
        Date() > dueDate && !isFullyPaid
    }

    /// Days until due (negative if past due)
    var daysUntilDue: Int {
        Calendar.current.dateComponents([.day], from: Date(), to: dueDate).day ?? 0
    }

    /// Display string for invoice status
    var statusDisplayName: String {
        status.displayName
    }

    /// Formatted billing period string
    var formattedBillingPeriod: String? {
        guard let start = periodStartDate, let end = periodEndDate else { return nil }
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        return "\(formatter.string(from: start)) - \(formatter.string(from: end))"
    }
}

// MARK: - Invoice Extensions

extension Invoice {

    /// Creates a sample invoice for previews and testing
    static var preview: Invoice {
        Invoice(
            id: "preview-invoice-1",
            number: "INV-2026-0042",
            familyId: "family-1",
            familyName: "Tremblay Family",
            childId: "child-1",
            childName: "Emma Tremblay",
            date: Date(),
            dueDate: Calendar.current.date(byAdding: .day, value: 30, to: Date()) ?? Date(),
            status: .pending,
            subtotal: 850.00,
            taxAmount: 127.07,
            totalAmount: 977.07,
            amountPaid: 0.0,
            items: [InvoiceItem.preview],
            periodStartDate: Calendar.current.date(byAdding: .month, value: -1, to: Date()),
            periodEndDate: Date(),
            pdfUrl: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample paid invoice for previews
    static var previewPaid: Invoice {
        Invoice(
            id: "preview-invoice-2",
            number: "INV-2026-0041",
            familyId: "family-1",
            familyName: "Tremblay Family",
            childId: "child-1",
            childName: "Emma Tremblay",
            date: Calendar.current.date(byAdding: .month, value: -1, to: Date()) ?? Date(),
            dueDate: Date(),
            status: .paid,
            subtotal: 850.00,
            taxAmount: 127.07,
            totalAmount: 977.07,
            amountPaid: 977.07,
            items: [InvoiceItem.preview],
            periodStartDate: Calendar.current.date(byAdding: .month, value: -2, to: Date()),
            periodEndDate: Calendar.current.date(byAdding: .month, value: -1, to: Date()),
            pdfUrl: "https://example.com/invoice.pdf",
            notes: "Thank you for your payment!",
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample overdue invoice for previews
    static var previewOverdue: Invoice {
        Invoice(
            id: "preview-invoice-3",
            number: "INV-2026-0040",
            familyId: "family-2",
            familyName: "Gagnon Family",
            childId: "child-2",
            childName: "Lucas Gagnon",
            date: Calendar.current.date(byAdding: .month, value: -2, to: Date()) ?? Date(),
            dueDate: Calendar.current.date(byAdding: .day, value: -15, to: Date()) ?? Date(),
            status: .overdue,
            subtotal: 1200.00,
            taxAmount: 179.40,
            totalAmount: 1379.40,
            amountPaid: 500.00,
            items: [InvoiceItem.preview, InvoiceItem.previewMealPlan],
            periodStartDate: Calendar.current.date(byAdding: .month, value: -3, to: Date()),
            periodEndDate: Calendar.current.date(byAdding: .month, value: -2, to: Date()),
            pdfUrl: nil,
            notes: "Partial payment received",
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - Invoice Summary

/// A lightweight representation of an invoice for list views and references.
struct InvoiceSummary: Identifiable, Codable, Equatable {

    /// Unique identifier for the invoice
    let id: String

    /// Invoice number
    let number: String

    /// Family name for display
    let familyName: String

    /// Child name (optional)
    let childName: String?

    /// Invoice date
    let date: Date

    /// Due date
    let dueDate: Date

    /// Payment status
    let status: InvoiceStatus

    /// Total amount
    let totalAmount: Double

    /// Balance due
    let balanceDue: Double

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case number
        case familyName = "family_name"
        case childName = "child_name"
        case date
        case dueDate = "due_date"
        case status
        case totalAmount = "total_amount"
        case balanceDue = "balance_due"
    }

    // MARK: - Computed Properties

    /// Formatted total amount string
    var formattedTotal: String {
        totalAmount.asCurrency
    }

    /// Formatted balance due string
    var formattedBalanceDue: String {
        balanceDue.asCurrency
    }

    /// Whether the invoice is past due
    var isPastDue: Bool {
        Date() > dueDate && balanceDue > 0
    }
}

// MARK: - Invoice Item

/// A line item on an invoice.
struct InvoiceItem: Identifiable, Codable, Equatable {

    /// Unique identifier for the line item
    let id: String

    /// Description of the item/service
    let description: String

    /// Quantity (e.g., number of days)
    let quantity: Double

    /// Unit price
    let unitPrice: Double

    /// Total for this line item (quantity × unitPrice)
    let total: Double

    /// Item category for tax purposes
    let category: InvoiceItemCategory

    /// Whether this item qualifies for childcare expense deduction (RL-24)
    let isQualifyingExpense: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case description
        case quantity
        case unitPrice = "unit_price"
        case total
        case category
        case isQualifyingExpense = "is_qualifying_expense"
    }

    // MARK: - Computed Properties

    /// Formatted unit price string
    var formattedUnitPrice: String {
        unitPrice.asCurrency
    }

    /// Formatted total string
    var formattedTotal: String {
        total.asCurrency
    }
}

// MARK: - Invoice Item Extensions

extension InvoiceItem {

    /// Creates a sample invoice item for previews
    static var preview: InvoiceItem {
        InvoiceItem(
            id: "item-1",
            description: "Childcare - Full Day",
            quantity: 20,
            unitPrice: 42.50,
            total: 850.00,
            category: .childcare,
            isQualifyingExpense: true
        )
    }

    /// Creates a sample meal plan item for previews
    static var previewMealPlan: InvoiceItem {
        InvoiceItem(
            id: "item-2",
            description: "Meal Plan",
            quantity: 20,
            unitPrice: 17.50,
            total: 350.00,
            category: .meals,
            isQualifyingExpense: true
        )
    }

    /// Creates a sample registration fee item for previews
    static var previewRegistration: InvoiceItem {
        InvoiceItem(
            id: "item-3",
            description: "Annual Registration Fee",
            quantity: 1,
            unitPrice: 75.00,
            total: 75.00,
            category: .registration,
            isQualifyingExpense: false
        )
    }
}

// MARK: - Invoice Item Category

/// Categories for invoice line items.
/// Used for financial reporting and tax classification.
enum InvoiceItemCategory: String, Codable, CaseIterable {
    case childcare = "childcare"
    case meals = "meals"
    case registration = "registration"
    case fieldTrip = "field_trip"
    case supplies = "supplies"
    case lateFee = "late_fee"
    case transportation = "transportation"
    case other = "other"

    var displayName: String {
        switch self {
        case .childcare:
            return String(localized: "Childcare")
        case .meals:
            return String(localized: "Meals")
        case .registration:
            return String(localized: "Registration")
        case .fieldTrip:
            return String(localized: "Field Trip")
        case .supplies:
            return String(localized: "Supplies")
        case .lateFee:
            return String(localized: "Late Fee")
        case .transportation:
            return String(localized: "Transportation")
        case .other:
            return String(localized: "Other")
        }
    }

    /// Whether this category qualifies for RL-24 childcare expense deduction
    var qualifiesForRL24: Bool {
        switch self {
        case .childcare, .meals:
            return true
        case .registration, .fieldTrip, .supplies, .lateFee, .transportation, .other:
            return false
        }
    }
}

// MARK: - Payment Model

/// Represents a payment made against an invoice.
struct Payment: Identifiable, Codable, Equatable {

    /// Unique identifier for the payment
    let id: String

    /// Invoice ID this payment is applied to
    let invoiceId: String

    /// Invoice number for reference
    let invoiceNumber: String?

    /// Payment amount
    let amount: Double

    /// Date payment was received
    let paymentDate: Date

    /// Payment method used
    let paymentMethod: PaymentMethod

    /// Reference number (check number, transaction ID, etc.)
    let referenceNumber: String?

    /// Additional notes about the payment
    let notes: String?

    /// Staff member who recorded the payment
    let recordedById: String?

    /// Staff member name for display
    let recordedByName: String?

    /// Date when the record was created
    let createdAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case invoiceId = "invoice_id"
        case invoiceNumber = "invoice_number"
        case amount
        case paymentDate = "payment_date"
        case paymentMethod = "payment_method"
        case referenceNumber = "reference_number"
        case notes
        case recordedById = "recorded_by_id"
        case recordedByName = "recorded_by_name"
        case createdAt = "created_at"
    }

    // MARK: - Computed Properties

    /// Formatted payment amount string
    var formattedAmount: String {
        amount.asCurrency
    }

    /// Display name for the payment method
    var paymentMethodDisplayName: String {
        paymentMethod.displayName
    }
}

// MARK: - Payment Extensions

extension Payment {

    /// Creates a sample payment for previews
    static var preview: Payment {
        Payment(
            id: "payment-1",
            invoiceId: "invoice-1",
            invoiceNumber: "INV-2026-0041",
            amount: 977.07,
            paymentDate: Date(),
            paymentMethod: .creditCard,
            referenceNumber: "TXN-123456",
            notes: nil,
            recordedById: "staff-1",
            recordedByName: "Marie Dupont",
            createdAt: Date()
        )
    }
}

// MARK: - Payment Method

/// Methods of payment accepted.
enum PaymentMethod: String, Codable, CaseIterable {
    case cash = "cash"
    case cheque = "cheque"
    case creditCard = "credit_card"
    case debitCard = "debit_card"
    case bankTransfer = "bank_transfer"
    case interac = "interac"
    case other = "other"

    var displayName: String {
        switch self {
        case .cash:
            return String(localized: "Cash")
        case .cheque:
            return String(localized: "Cheque")
        case .creditCard:
            return String(localized: "Credit Card")
        case .debitCard:
            return String(localized: "Debit Card")
        case .bankTransfer:
            return String(localized: "Bank Transfer")
        case .interac:
            return String(localized: "Interac e-Transfer")
        case .other:
            return String(localized: "Other")
        }
    }
}

// MARK: - Payment Request

/// Request payload for recording a payment.
struct PaymentRequest: Codable {

    /// Invoice ID to apply the payment to
    let invoiceId: String

    /// Payment amount
    let amount: Double

    /// Date payment was received
    let paymentDate: Date

    /// Payment method
    let paymentMethod: PaymentMethod

    /// Reference number (optional)
    let referenceNumber: String?

    /// Additional notes (optional)
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case invoiceId = "invoice_id"
        case amount
        case paymentDate = "payment_date"
        case paymentMethod = "payment_method"
        case referenceNumber = "reference_number"
        case notes
    }
}

// MARK: - Invoice Request

/// Request payload for creating or updating an invoice.
struct InvoiceRequest: Codable {

    /// Family ID
    let familyId: String

    /// Child ID (optional)
    let childId: String?

    /// Invoice date
    let date: Date

    /// Due date
    let dueDate: Date

    /// Invoice line items
    let items: [InvoiceItemRequest]

    /// Billing period start (optional)
    let periodStartDate: Date?

    /// Billing period end (optional)
    let periodEndDate: Date?

    /// Notes (optional)
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case familyId = "family_id"
        case childId = "child_id"
        case date
        case dueDate = "due_date"
        case items
        case periodStartDate = "period_start_date"
        case periodEndDate = "period_end_date"
        case notes
    }
}

// MARK: - Invoice Item Request

/// Request payload for invoice line items.
struct InvoiceItemRequest: Codable {

    /// Description of the item/service
    let description: String

    /// Quantity
    let quantity: Double

    /// Unit price
    let unitPrice: Double

    /// Category
    let category: InvoiceItemCategory

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case description
        case quantity
        case unitPrice = "unit_price"
        case category
    }
}

// MARK: - Finance Summary

/// Summary of financial status for dashboard display.
struct FinanceSummary: Codable, Equatable {

    /// Total revenue for the current month
    let monthlyRevenue: Double

    /// Total outstanding balance across all families
    let totalOutstanding: Double

    /// Number of overdue invoices
    let overdueCount: Int

    /// Total amount overdue
    let overdueAmount: Double

    /// Number of invoices pending payment
    let pendingCount: Int

    /// Total amount pending
    let pendingAmount: Double

    /// Collection rate (percentage of invoiced amount collected)
    let collectionRate: Double

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case monthlyRevenue = "monthly_revenue"
        case totalOutstanding = "total_outstanding"
        case overdueCount = "overdue_count"
        case overdueAmount = "overdue_amount"
        case pendingCount = "pending_count"
        case pendingAmount = "pending_amount"
        case collectionRate = "collection_rate"
    }

    // MARK: - Computed Properties

    /// Formatted monthly revenue string
    var formattedMonthlyRevenue: String {
        monthlyRevenue.asCurrency
    }

    /// Formatted total outstanding string
    var formattedTotalOutstanding: String {
        totalOutstanding.asCurrency
    }

    /// Formatted overdue amount string
    var formattedOverdueAmount: String {
        overdueAmount.asCurrency
    }

    /// Formatted collection rate string
    var formattedCollectionRate: String {
        collectionRate.asPercentage
    }
}

// MARK: - Finance Summary Extensions

extension FinanceSummary {

    /// Creates a sample finance summary for previews
    static var preview: FinanceSummary {
        FinanceSummary(
            monthlyRevenue: 45250.00,
            totalOutstanding: 8750.00,
            overdueCount: 3,
            overdueAmount: 2875.00,
            pendingCount: 15,
            pendingAmount: 5875.00,
            collectionRate: 0.92
        )
    }
}
