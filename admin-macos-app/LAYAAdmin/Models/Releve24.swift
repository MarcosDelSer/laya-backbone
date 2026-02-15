//
//  Releve24.swift
//  LAYAAdmin
//
//  Quebec Relevé 24 (RL-24) tax slip models for the LAYA Admin application.
//
//  RL-24 is required by Revenu Québec for childcare expense deductions.
//  Critical Business Rule: RL-24 amounts must reflect PAID amounts at filing time,
//  NOT invoiced amounts. If additional payments are received after initial RL-24 filing,
//  an amended RL-24 (type A) must be issued.
//

import Foundation

// MARK: - Releve24 Model

/// Represents a Quebec RL-24 tax slip for childcare expenses.
/// The RL-24 (Relevé 24) is required by Revenu Québec for parents to claim
/// childcare expense deductions on their provincial tax return.
///
/// Box Definitions:
/// - Box A: Slip Type (R=original, A=amended, D=cancelled)
/// - Box B: Days of Care (actual paid days, not calendar or invoiced days)
/// - Box C: Total Amounts Paid (all payments received)
/// - Box D: Non-Qualifying Expenses (medical, transport, teaching, etc.)
/// - Box E: Qualifying Expenses (Box C - Box D)
/// - Box H: Provider SIN (XXX-XXX-XXX format)
struct Releve24: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the RL-24 record
    let id: String

    /// Reference number for the RL-24 slip
    let referenceNumber: String?

    /// Child's person ID in the system
    let childId: String

    /// Child's full name for display
    let childName: String

    /// Child's social insurance number (for the slip)
    let childSIN: String?

    /// Family ID
    let familyId: String

    /// Family name for display
    let familyName: String

    /// Recipient (parent/guardian) ID
    let recipientId: String

    /// Recipient's full name
    let recipientName: String

    /// Recipient's SIN (XXX-XXX-XXX format)
    let recipientSIN: String?

    /// Recipient's address
    let recipientAddress: Address?

    /// Tax year for this slip (YYYY format)
    let taxYear: Int

    /// Slip type (R=original, A=amended, D=cancelled)
    let slipType: Releve24SlipType

    /// Box B: Number of days of care
    let daysOfCare: Int

    /// Box C: Total amounts paid
    let totalAmountsPaid: Double

    /// Box D: Non-qualifying expenses
    let nonQualifyingExpenses: Double

    /// Box E: Qualifying expenses (totalAmountsPaid - nonQualifyingExpenses)
    var qualifyingExpenses: Double {
        max(0, totalAmountsPaid - nonQualifyingExpenses)
    }

    /// Provider's social insurance number
    let providerSIN: String?

    /// Provider's name
    let providerName: String?

    /// Provider's address
    let providerAddress: Address?

    /// Current status of the RL-24
    var status: Releve24Status

    /// Date the slip was generated
    let generatedAt: Date?

    /// Date the slip was sent to the recipient
    let sentAt: Date?

    /// Date the slip was filed with Revenu Québec
    let filedAt: Date?

    /// URL to the PDF file
    let pdfUrl: String?

    /// Staff member who created this slip
    let createdById: String?

    /// Staff member name for display
    let createdByName: String?

    /// Additional notes
    let notes: String?

    /// Date when the record was created
    let createdAt: Date?

    /// Date when the record was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case referenceNumber = "reference_number"
        case childId = "child_id"
        case childName = "child_name"
        case childSIN = "child_sin"
        case familyId = "family_id"
        case familyName = "family_name"
        case recipientId = "recipient_id"
        case recipientName = "recipient_name"
        case recipientSIN = "recipient_sin"
        case recipientAddress = "recipient_address"
        case taxYear = "tax_year"
        case slipType = "slip_type"
        case daysOfCare = "days_of_care"
        case totalAmountsPaid = "total_amounts_paid"
        case nonQualifyingExpenses = "non_qualifying_expenses"
        case providerSIN = "provider_sin"
        case providerName = "provider_name"
        case providerAddress = "provider_address"
        case status
        case generatedAt = "generated_at"
        case sentAt = "sent_at"
        case filedAt = "filed_at"
        case pdfUrl = "pdf_url"
        case createdById = "created_by_id"
        case createdByName = "created_by_name"
        case notes
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Formatted total amounts paid string
    var formattedTotalAmountsPaid: String {
        totalAmountsPaid.asCurrency
    }

    /// Formatted non-qualifying expenses string
    var formattedNonQualifyingExpenses: String {
        nonQualifyingExpenses.asCurrency
    }

    /// Formatted qualifying expenses string (Box E)
    var formattedQualifyingExpenses: String {
        qualifyingExpenses.asCurrency
    }

    /// Display name for status
    var statusDisplayName: String {
        status.displayName
    }

    /// Display name for slip type
    var slipTypeDisplayName: String {
        slipType.displayName
    }

    /// Whether this is an amended slip
    var isAmended: Bool {
        slipType == .amended
    }

    /// Whether this slip can be edited
    var isEditable: Bool {
        status == .draft || status == .generated
    }

    /// Formatted tax year string
    var taxYearString: String {
        String(taxYear)
    }

    /// Title for display (e.g., "RL-24 2025 - Emma Tremblay")
    var displayTitle: String {
        "RL-24 \(taxYear) - \(childName)"
    }
}

// MARK: - Releve24 Extensions

extension Releve24 {

    /// Creates a sample RL-24 for previews and testing
    static var preview: Releve24 {
        Releve24(
            id: "preview-releve24-1",
            referenceNumber: "RL24-2025-001",
            childId: "child-1",
            childName: "Emma Tremblay",
            childSIN: nil,
            familyId: "family-1",
            familyName: "Tremblay Family",
            recipientId: "guardian-1",
            recipientName: "Marie Tremblay",
            recipientSIN: "123-456-789",
            recipientAddress: Address.preview,
            taxYear: 2025,
            slipType: .original,
            daysOfCare: 220,
            totalAmountsPaid: 11725.00,
            nonQualifyingExpenses: 450.00,
            providerSIN: "987-654-321",
            providerName: "LAYA Childcare Inc.",
            providerAddress: Address.previewProvider,
            status: .generated,
            generatedAt: Date(),
            sentAt: nil,
            filedAt: nil,
            pdfUrl: nil,
            createdById: "staff-1",
            createdByName: "Marie Dupont",
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample amended RL-24 for previews
    static var previewAmended: Releve24 {
        Releve24(
            id: "preview-releve24-2",
            referenceNumber: "RL24-2025-001-A",
            childId: "child-1",
            childName: "Emma Tremblay",
            childSIN: nil,
            familyId: "family-1",
            familyName: "Tremblay Family",
            recipientId: "guardian-1",
            recipientName: "Marie Tremblay",
            recipientSIN: "123-456-789",
            recipientAddress: Address.preview,
            taxYear: 2025,
            slipType: .amended,
            daysOfCare: 225,
            totalAmountsPaid: 12150.00,
            nonQualifyingExpenses: 475.00,
            providerSIN: "987-654-321",
            providerName: "LAYA Childcare Inc.",
            providerAddress: Address.previewProvider,
            status: .sent,
            generatedAt: Calendar.current.date(byAdding: .day, value: -7, to: Date()),
            sentAt: Date(),
            filedAt: nil,
            pdfUrl: "https://example.com/releve24.pdf",
            createdById: "staff-1",
            createdByName: "Marie Dupont",
            notes: "Amended due to additional payment received after original filing",
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample filed RL-24 for previews
    static var previewFiled: Releve24 {
        Releve24(
            id: "preview-releve24-3",
            referenceNumber: "RL24-2024-042",
            childId: "child-2",
            childName: "Lucas Gagnon",
            childSIN: nil,
            familyId: "family-2",
            familyName: "Gagnon Family",
            recipientId: "guardian-3",
            recipientName: "Sophie Gagnon",
            recipientSIN: "234-567-890",
            recipientAddress: Address.preview,
            taxYear: 2024,
            slipType: .original,
            daysOfCare: 180,
            totalAmountsPaid: 9500.00,
            nonQualifyingExpenses: 325.00,
            providerSIN: "987-654-321",
            providerName: "LAYA Childcare Inc.",
            providerAddress: Address.previewProvider,
            status: .filed,
            generatedAt: Calendar.current.date(byAdding: .month, value: -2, to: Date()),
            sentAt: Calendar.current.date(byAdding: .month, value: -2, to: Date()),
            filedAt: Calendar.current.date(byAdding: .month, value: -1, to: Date()),
            pdfUrl: "https://example.com/releve24-2024.pdf",
            createdById: "staff-1",
            createdByName: "Marie Dupont",
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - Releve24 Summary

/// A lightweight representation of an RL-24 for list views.
struct Releve24Summary: Identifiable, Codable, Equatable {

    /// Unique identifier
    let id: String

    /// Reference number
    let referenceNumber: String?

    /// Child's name
    let childName: String

    /// Family name
    let familyName: String

    /// Tax year
    let taxYear: Int

    /// Slip type
    let slipType: Releve24SlipType

    /// Qualifying expenses (Box E)
    let qualifyingExpenses: Double

    /// Status
    let status: Releve24Status

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case referenceNumber = "reference_number"
        case childName = "child_name"
        case familyName = "family_name"
        case taxYear = "tax_year"
        case slipType = "slip_type"
        case qualifyingExpenses = "qualifying_expenses"
        case status
    }

    // MARK: - Computed Properties

    /// Formatted qualifying expenses string
    var formattedQualifyingExpenses: String {
        qualifyingExpenses.asCurrency
    }

    /// Display title
    var displayTitle: String {
        "RL-24 \(taxYear) - \(childName)"
    }
}

// MARK: - Releve24 Slip Type

/// RL-24 slip types as defined by Revenu Québec.
enum Releve24SlipType: String, Codable, CaseIterable {
    /// Original slip (R)
    case original = "R"

    /// Amended slip (A) - issued when corrections are needed
    case amended = "A"

    /// Cancelled slip (D) - issued to cancel a previously filed slip
    case cancelled = "D"

    var displayName: String {
        switch self {
        case .original:
            return String(localized: "Original")
        case .amended:
            return String(localized: "Amended")
        case .cancelled:
            return String(localized: "Cancelled")
        }
    }

    /// Box A code for the RL-24 form
    var boxACode: String {
        rawValue
    }
}

// MARK: - Releve24 Status

/// Status of an RL-24 slip in the workflow.
enum Releve24Status: String, Codable, CaseIterable {
    /// Draft - being prepared, not yet generated
    case draft = "draft"

    /// Generated - PDF created but not yet sent
    case generated = "generated"

    /// Sent - delivered to the recipient
    case sent = "sent"

    /// Filed - submitted to Revenu Québec
    case filed = "filed"

    /// Amended - original has been superseded by an amended version
    case amended = "amended"

    var displayName: String {
        switch self {
        case .draft:
            return String(localized: "Draft")
        case .generated:
            return String(localized: "Generated")
        case .sent:
            return String(localized: "Sent")
        case .filed:
            return String(localized: "Filed")
        case .amended:
            return String(localized: "Amended")
        }
    }

    var color: String {
        switch self {
        case .draft:
            return "gray"
        case .generated:
            return "blue"
        case .sent:
            return "orange"
        case .filed:
            return "green"
        case .amended:
            return "purple"
        }
    }
}

// MARK: - Non-Qualifying Expense Type

/// Types of expenses that do NOT qualify for RL-24 childcare deduction.
/// As defined by Revenu Québec guidelines.
enum NonQualifyingExpenseType: String, Codable, CaseIterable {
    /// Medical or hospital care
    case medical = "medical"

    /// Hospital care
    case hospital = "hospital"

    /// Transportation services
    case transportation = "transportation"

    /// Teaching services
    case teaching = "teaching"

    /// Educational services
    case education = "education"

    /// Field trips
    case fieldTrip = "field_trip"

    /// Registration fees
    case registration = "registration"

    /// Late payment penalties
    case lateFee = "late_fee"

    /// Administrative fees (non-care related)
    case adminFee = "admin_fee"

    /// Supply fees (non-care related)
    case supplyFee = "supply_fee"

    /// Additional meal supplements (above basic)
    case mealSupplement = "meal_supplement"

    var displayName: String {
        switch self {
        case .medical:
            return String(localized: "Medical Care")
        case .hospital:
            return String(localized: "Hospital Care")
        case .transportation:
            return String(localized: "Transportation")
        case .teaching:
            return String(localized: "Teaching Services")
        case .education:
            return String(localized: "Educational Services")
        case .fieldTrip:
            return String(localized: "Field Trips")
        case .registration:
            return String(localized: "Registration Fees")
        case .lateFee:
            return String(localized: "Late Payment Fees")
        case .adminFee:
            return String(localized: "Administrative Fees")
        case .supplyFee:
            return String(localized: "Supply Fees")
        case .mealSupplement:
            return String(localized: "Meal Supplements")
        }
    }
}

// MARK: - Non-Qualifying Expense

/// A non-qualifying expense entry for RL-24 calculation.
struct NonQualifyingExpense: Identifiable, Codable, Equatable {

    /// Unique identifier
    let id: String

    /// Type of non-qualifying expense
    let type: NonQualifyingExpenseType

    /// Description
    let description: String

    /// Amount
    let amount: Double

    // MARK: - Computed Properties

    /// Formatted amount string
    var formattedAmount: String {
        amount.asCurrency
    }
}

// MARK: - Address Model

/// Mailing address for RL-24 slips.
struct Address: Codable, Equatable {

    /// Street address line 1
    let street1: String

    /// Street address line 2 (optional)
    let street2: String?

    /// City
    let city: String

    /// Province (e.g., "QC")
    let province: String

    /// Postal code (e.g., "H2X 1Y4")
    let postalCode: String

    /// Country (default: Canada)
    let country: String

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case street1
        case street2
        case city
        case province
        case postalCode = "postal_code"
        case country
    }

    // MARK: - Computed Properties

    /// Formatted address for display (single line)
    var formattedSingleLine: String {
        var parts = [street1]
        if let street2, !street2.isEmpty {
            parts.append(street2)
        }
        parts.append("\(city), \(province) \(postalCode)")
        return parts.joined(separator: ", ")
    }

    /// Formatted address for display (multi-line)
    var formattedMultiLine: String {
        var lines = [street1]
        if let street2, !street2.isEmpty {
            lines.append(street2)
        }
        lines.append("\(city), \(province) \(postalCode)")
        lines.append(country)
        return lines.joined(separator: "\n")
    }
}

// MARK: - Address Extensions

extension Address {

    /// Creates a sample address for previews
    static var preview: Address {
        Address(
            street1: "123 Rue Principale",
            street2: "Apt 4B",
            city: "Montréal",
            province: "QC",
            postalCode: "H2X 1Y4",
            country: "Canada"
        )
    }

    /// Creates a sample provider address for previews
    static var previewProvider: Address {
        Address(
            street1: "456 Boulevard Saint-Laurent",
            street2: nil,
            city: "Montréal",
            province: "QC",
            postalCode: "H2Y 2Y7",
            country: "Canada"
        )
    }
}

// MARK: - Releve24 Request

/// Request payload for generating an RL-24.
struct Releve24Request: Codable {

    /// Child's person ID
    let childId: String

    /// Family ID
    let familyId: String

    /// Tax year (YYYY format)
    let taxYear: Int

    /// Recipient (parent) ID
    let recipientId: String?

    /// Notes (optional)
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case childId = "child_id"
        case familyId = "family_id"
        case taxYear = "tax_year"
        case recipientId = "recipient_id"
        case notes
    }
}

// MARK: - Releve24 Export Request

/// Request payload for exporting RL-24 to PDF.
struct Releve24ExportRequest: Codable {

    /// RL-24 ID to export
    let releve24Id: String

    /// Whether to mark as sent after export
    let markAsSent: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case releve24Id = "releve24_id"
        case markAsSent = "mark_as_sent"
    }
}

// MARK: - Releve24 Calculation

/// Calculated RL-24 values for a child/family/year combination.
/// Used to preview values before generating the official slip.
struct Releve24Calculation: Codable, Equatable {

    /// Child ID
    let childId: String

    /// Child name
    let childName: String

    /// Family ID
    let familyId: String

    /// Tax year
    let taxYear: Int

    /// Calculated days of care (Box B)
    let daysOfCare: Int

    /// Calculated total amounts paid (Box C)
    let totalAmountsPaid: Double

    /// Calculated non-qualifying expenses (Box D)
    let nonQualifyingExpenses: Double

    /// Calculated qualifying expenses (Box E)
    var qualifyingExpenses: Double {
        max(0, totalAmountsPaid - nonQualifyingExpenses)
    }

    /// Breakdown of non-qualifying expenses by type
    let nonQualifyingBreakdown: [NonQualifyingExpense]

    /// Whether an RL-24 already exists for this combination
    let existingReleve24Id: String?

    /// Whether the existing slip would need to be amended
    let requiresAmendment: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case childId = "child_id"
        case childName = "child_name"
        case familyId = "family_id"
        case taxYear = "tax_year"
        case daysOfCare = "days_of_care"
        case totalAmountsPaid = "total_amounts_paid"
        case nonQualifyingExpenses = "non_qualifying_expenses"
        case nonQualifyingBreakdown = "non_qualifying_breakdown"
        case existingReleve24Id = "existing_releve24_id"
        case requiresAmendment = "requires_amendment"
    }

    // MARK: - Computed Properties

    /// Formatted total amounts paid string
    var formattedTotalAmountsPaid: String {
        totalAmountsPaid.asCurrency
    }

    /// Formatted non-qualifying expenses string
    var formattedNonQualifyingExpenses: String {
        nonQualifyingExpenses.asCurrency
    }

    /// Formatted qualifying expenses string
    var formattedQualifyingExpenses: String {
        qualifyingExpenses.asCurrency
    }
}

// MARK: - Releve24 Calculation Extensions

extension Releve24Calculation {

    /// Creates a sample calculation for previews
    static var preview: Releve24Calculation {
        Releve24Calculation(
            childId: "child-1",
            childName: "Emma Tremblay",
            familyId: "family-1",
            taxYear: 2025,
            daysOfCare: 220,
            totalAmountsPaid: 11725.00,
            nonQualifyingExpenses: 450.00,
            nonQualifyingBreakdown: [
                NonQualifyingExpense(
                    id: "expense-1",
                    type: .fieldTrip,
                    description: "Zoo field trip",
                    amount: 75.00
                ),
                NonQualifyingExpense(
                    id: "expense-2",
                    type: .registration,
                    description: "Annual registration fee",
                    amount: 150.00
                ),
                NonQualifyingExpense(
                    id: "expense-3",
                    type: .lateFee,
                    description: "Late pickup fees",
                    amount: 225.00
                )
            ],
            existingReleve24Id: nil,
            requiresAmendment: false
        )
    }
}

// MARK: - Releve24 Batch Export

/// Request for batch exporting multiple RL-24 slips.
struct Releve24BatchExportRequest: Codable {

    /// Tax year to export
    let taxYear: Int

    /// Family IDs to include (nil for all families)
    let familyIds: [String]?

    /// Export format
    let format: Releve24ExportFormat

    /// Whether to mark slips as sent after export
    let markAsSent: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case taxYear = "tax_year"
        case familyIds = "family_ids"
        case format
        case markAsSent = "mark_as_sent"
    }
}

// MARK: - Releve24 Export Format

/// Export formats for RL-24 slips.
enum Releve24ExportFormat: String, Codable, CaseIterable {
    /// Individual PDF files
    case pdf = "pdf"

    /// Combined PDF with all slips
    case combinedPdf = "combined_pdf"

    /// XML format for electronic filing
    case xml = "xml"

    var displayName: String {
        switch self {
        case .pdf:
            return String(localized: "Individual PDFs")
        case .combinedPdf:
            return String(localized: "Combined PDF")
        case .xml:
            return String(localized: "XML (Electronic Filing)")
        }
    }
}
