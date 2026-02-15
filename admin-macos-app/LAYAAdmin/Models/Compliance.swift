//
//  Compliance.swift
//  LAYAAdmin
//
//  Compliance domain models for the LAYA Admin application.
//  Supports Quebec regulatory compliance monitoring including
//  staff-to-child ratios, certifications, and safety inspections.
//

import Foundation

// MARK: - Compliance Status

/// Status levels for compliance checks.
enum ComplianceStatus: String, Codable, CaseIterable {
    /// Fully compliant with regulations
    case compliant = "compliant"

    /// Minor issues requiring attention
    case warning = "warning"

    /// Serious compliance violation
    case violation = "violation"

    /// Status cannot be determined
    case unknown = "unknown"

    var displayName: String {
        switch self {
        case .compliant:
            return String(localized: "Compliant")
        case .warning:
            return String(localized: "Warning")
        case .violation:
            return String(localized: "Violation")
        case .unknown:
            return String(localized: "Unknown")
        }
    }

    /// Color name for this status
    var color: String {
        switch self {
        case .compliant:
            return "green"
        case .warning:
            return "orange"
        case .violation:
            return "red"
        case .unknown:
            return "gray"
        }
    }

    /// SF Symbol name for this status
    var iconName: String {
        switch self {
        case .compliant:
            return "checkmark.circle.fill"
        case .warning:
            return "exclamationmark.triangle.fill"
        case .violation:
            return "xmark.octagon.fill"
        case .unknown:
            return "questionmark.circle.fill"
        }
    }

    /// Sort order for displaying (violations first)
    var sortOrder: Int {
        switch self {
        case .violation:
            return 0
        case .warning:
            return 1
        case .unknown:
            return 2
        case .compliant:
            return 3
        }
    }
}

// MARK: - Compliance Check Type

/// Types of compliance checks performed.
enum ComplianceCheckType: String, Codable, CaseIterable {
    /// Staff-to-child ratio compliance
    case staffRatio = "staff_ratio"

    /// Staff certification requirements
    case certification = "certification"

    /// Facility capacity limits
    case capacity = "capacity"

    /// Safety inspection status
    case safety = "safety"

    var displayName: String {
        switch self {
        case .staffRatio:
            return String(localized: "Staff Ratio")
        case .certification:
            return String(localized: "Certifications")
        case .capacity:
            return String(localized: "Capacity")
        case .safety:
            return String(localized: "Safety")
        }
    }

    /// Detailed description of what this check covers
    var description: String {
        switch self {
        case .staffRatio:
            return String(localized: "Verifies staff-to-child ratios meet Quebec ministry requirements for each age group")
        case .certification:
            return String(localized: "Checks that all required staff certifications are valid and up-to-date")
        case .capacity:
            return String(localized: "Ensures enrollment does not exceed licensed facility capacity")
        case .safety:
            return String(localized: "Monitors safety inspection status and required safety equipment")
        }
    }

    /// SF Symbol name for this check type
    var iconName: String {
        switch self {
        case .staffRatio:
            return "person.3.fill"
        case .certification:
            return "checkmark.seal.fill"
        case .capacity:
            return "building.2.fill"
        case .safety:
            return "shield.checkered"
        }
    }

    /// Regulatory reference (Quebec ministry)
    var regulatoryReference: String {
        switch self {
        case .staffRatio:
            return "Règlement sur les services de garde éducatifs à l'enfance, Art. 20-23"
        case .certification:
            return "Règlement sur les services de garde éducatifs à l'enfance, Art. 24-27"
        case .capacity:
            return "Loi sur les services de garde éducatifs à l'enfance, Art. 11"
        case .safety:
            return "Règlement sur les services de garde éducatifs à l'enfance, Art. 40-45"
        }
    }
}

// MARK: - Compliance Check Response

/// Response schema for a compliance check result.
/// Represents the status of a single compliance area with details
/// and recommendations.
struct ComplianceCheckResponse: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the check (using check type + facility)
    var id: String {
        "\(checkType.rawValue)-\(facilityId ?? "all")"
    }

    /// Type of compliance check performed
    let checkType: ComplianceCheckType

    /// Current compliance status
    let status: ComplianceStatus

    /// Detailed information about the compliance check
    let details: [String: String]?

    /// When the check was performed
    let checkedAt: Date

    /// When the next check is due
    let nextCheckDue: Date?

    /// Optional facility identifier
    let facilityId: String?

    /// Recommended action if not compliant
    let recommendation: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case checkType = "check_type"
        case status
        case details
        case checkedAt = "checked_at"
        case nextCheckDue = "next_check_due"
        case facilityId = "facility_id"
        case recommendation
    }

    // MARK: - Computed Properties

    /// Display name for the check type
    var checkTypeDisplayName: String {
        checkType.displayName
    }

    /// Display name for the status
    var statusDisplayName: String {
        status.displayName
    }

    /// Color name for the status
    var statusColor: String {
        status.color
    }

    /// Icon name for the status
    var statusIcon: String {
        status.iconName
    }

    /// Formatted checked date string
    var formattedCheckedAt: String {
        checkedAt.displayDate
    }

    /// Formatted next check due date string
    var formattedNextCheckDue: String? {
        nextCheckDue?.displayDate
    }

    /// Whether the check requires attention
    var requiresAttention: Bool {
        status == .warning || status == .violation
    }

    /// Days until next check due (nil if no due date)
    var daysUntilNextCheck: Int? {
        guard let nextDue = nextCheckDue else { return nil }
        return Calendar.current.dateComponents([.day], from: Date(), to: nextDue).day
    }

    /// Whether next check is overdue
    var isOverdue: Bool {
        guard let days = daysUntilNextCheck else { return false }
        return days < 0
    }
}

// MARK: - Compliance Check Response Extensions

extension ComplianceCheckResponse {

    /// Creates a sample compliant check for previews
    static var previewCompliant: ComplianceCheckResponse {
        ComplianceCheckResponse(
            checkType: .staffRatio,
            status: .compliant,
            details: [
                "current_ratio": "1:5",
                "required_ratio": "1:6",
                "age_group": "3-4 years"
            ],
            checkedAt: Date(),
            nextCheckDue: Calendar.current.date(byAdding: .month, value: 1, to: Date()),
            facilityId: nil,
            recommendation: nil
        )
    }

    /// Creates a sample warning check for previews
    static var previewWarning: ComplianceCheckResponse {
        ComplianceCheckResponse(
            checkType: .certification,
            status: .warning,
            details: [
                "expiring_count": "2",
                "expiring_soon": "Marie Dupont (First Aid), Jean Tremblay (CPR)"
            ],
            checkedAt: Date(),
            nextCheckDue: Calendar.current.date(byAdding: .day, value: 14, to: Date()),
            facilityId: nil,
            recommendation: "Schedule certification renewal for staff members with expiring credentials"
        )
    }

    /// Creates a sample violation check for previews
    static var previewViolation: ComplianceCheckResponse {
        ComplianceCheckResponse(
            checkType: .capacity,
            status: .violation,
            details: [
                "current_enrollment": "55",
                "licensed_capacity": "52",
                "over_capacity_by": "3"
            ],
            checkedAt: Date(),
            nextCheckDue: nil,
            facilityId: nil,
            recommendation: "Immediately reduce enrollment to licensed capacity of 52 children"
        )
    }

    /// Creates a sample safety check for previews
    static var previewSafety: ComplianceCheckResponse {
        ComplianceCheckResponse(
            checkType: .safety,
            status: .compliant,
            details: [
                "last_inspection": "2025-12-15",
                "fire_extinguisher_check": "Valid",
                "first_aid_kit": "Complete"
            ],
            checkedAt: Date(),
            nextCheckDue: Calendar.current.date(byAdding: .month, value: 6, to: Date()),
            facilityId: nil,
            recommendation: nil
        )
    }
}

// MARK: - Compliance Check With ID

/// Compliance check response with database ID and timestamps.
/// Includes all compliance check fields plus database record metadata.
struct ComplianceCheckWithID: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Database ID
    let id: String

    /// Type of compliance check performed
    let checkType: ComplianceCheckType

    /// Current compliance status
    let status: ComplianceStatus

    /// Detailed information about the compliance check
    let details: [String: String]?

    /// When the check was performed
    let checkedAt: Date

    /// When the next check is due
    let nextCheckDue: Date?

    /// Optional facility identifier
    let facilityId: String?

    /// Recommended action if not compliant
    let recommendation: String?

    /// Date when the record was created
    let createdAt: Date?

    /// Date when the record was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case checkType = "check_type"
        case status
        case details
        case checkedAt = "checked_at"
        case nextCheckDue = "next_check_due"
        case facilityId = "facility_id"
        case recommendation
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Display name for the check type
    var checkTypeDisplayName: String {
        checkType.displayName
    }

    /// Display name for the status
    var statusDisplayName: String {
        status.displayName
    }

    /// Whether the check requires attention
    var requiresAttention: Bool {
        status == .warning || status == .violation
    }
}

// MARK: - Compliance List Response

/// Response schema for a list of compliance checks.
struct ComplianceListResponse: Codable, Equatable {

    // MARK: - Properties

    /// List of compliance check results
    let checks: [ComplianceCheckResponse]

    /// Overall compliance status across all checks
    let overallStatus: ComplianceStatus

    /// When the compliance report was generated
    let generatedAt: Date

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case checks
        case overallStatus = "overall_status"
        case generatedAt = "generated_at"
    }

    // MARK: - Computed Properties

    /// Number of checks with compliant status
    var compliantCount: Int {
        checks.filter { $0.status == .compliant }.count
    }

    /// Number of checks with warning status
    var warningCount: Int {
        checks.filter { $0.status == .warning }.count
    }

    /// Number of checks with violation status
    var violationCount: Int {
        checks.filter { $0.status == .violation }.count
    }

    /// Total number of checks
    var totalChecks: Int {
        checks.count
    }

    /// Compliance percentage (compliant checks / total checks)
    var compliancePercentage: Double {
        guard totalChecks > 0 else { return 0 }
        return (Double(compliantCount) / Double(totalChecks)) * 100
    }

    /// Formatted compliance percentage string
    var formattedCompliancePercentage: String {
        compliancePercentage.asPercentage
    }

    /// Checks sorted by status (violations first)
    var sortedChecks: [ComplianceCheckResponse] {
        checks.sorted { $0.status.sortOrder < $1.status.sortOrder }
    }

    /// Checks that require attention (warnings and violations)
    var checksRequiringAttention: [ComplianceCheckResponse] {
        checks.filter { $0.requiresAttention }
    }

    /// Whether any checks require attention
    var hasIssues: Bool {
        !checksRequiringAttention.isEmpty
    }
}

// MARK: - Compliance List Response Extensions

extension ComplianceListResponse {

    /// Creates a sample compliance list for previews
    static var preview: ComplianceListResponse {
        ComplianceListResponse(
            checks: [
                .previewCompliant,
                .previewWarning,
                .previewSafety
            ],
            overallStatus: .warning,
            generatedAt: Date()
        )
    }

    /// Creates a sample compliance list with all compliant for previews
    static var previewAllCompliant: ComplianceListResponse {
        ComplianceListResponse(
            checks: [
                .previewCompliant,
                .previewSafety
            ],
            overallStatus: .compliant,
            generatedAt: Date()
        )
    }

    /// Creates a sample compliance list with warnings for previews
    static var previewWithWarnings: ComplianceListResponse {
        ComplianceListResponse(
            checks: [
                .previewCompliant,
                .previewWarning,
                .previewViolation,
                .previewSafety
            ],
            overallStatus: .violation,
            generatedAt: Date()
        )
    }
}

// MARK: - Staff Ratio Details

/// Detailed information about staff-to-child ratio compliance.
struct StaffRatioDetails: Codable, Equatable {

    // MARK: - Properties

    /// Age group for this ratio requirement
    let ageGroup: AgeGroup

    /// Current number of staff for this age group
    let currentStaffCount: Int

    /// Current number of children in this age group
    let currentChildCount: Int

    /// Required ratio (e.g., 1:5 means 1 staff per 5 children)
    let requiredRatio: Double

    /// Current actual ratio
    var currentRatio: Double {
        guard currentStaffCount > 0 else { return 0 }
        return Double(currentChildCount) / Double(currentStaffCount)
    }

    /// Whether the current ratio meets requirements
    var meetsRequirement: Bool {
        currentRatio <= requiredRatio
    }

    /// Number of additional staff needed (0 if compliant)
    var additionalStaffNeeded: Int {
        guard !meetsRequirement else { return 0 }
        let requiredStaff = Int(ceil(Double(currentChildCount) / requiredRatio))
        return max(0, requiredStaff - currentStaffCount)
    }

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case ageGroup = "age_group"
        case currentStaffCount = "current_staff_count"
        case currentChildCount = "current_child_count"
        case requiredRatio = "required_ratio"
    }

    // MARK: - Computed Properties

    /// Formatted current ratio string (e.g., "1:4")
    var formattedCurrentRatio: String {
        "1:\(String(format: "%.1f", currentRatio))"
    }

    /// Formatted required ratio string (e.g., "1:5")
    var formattedRequiredRatio: String {
        "1:\(Int(requiredRatio))"
    }

    /// Compliance status for this ratio
    var status: ComplianceStatus {
        if meetsRequirement {
            return .compliant
        } else if currentRatio <= requiredRatio * 1.2 {
            return .warning
        } else {
            return .violation
        }
    }
}

// MARK: - Age Group

/// Age groups for Quebec childcare ratio requirements.
enum AgeGroup: String, Codable, CaseIterable {
    /// 0-18 months (poupons)
    case infant = "infant"

    /// 18 months - 4 years (trottineurs/préscolaire)
    case toddler = "toddler"

    /// 4-5 years (préscolaire)
    case preschool = "preschool"

    /// School age (5+ years)
    case schoolAge = "school_age"

    var displayName: String {
        switch self {
        case .infant:
            return String(localized: "Infant (0-18 months)")
        case .toddler:
            return String(localized: "Toddler (18 months - 4 years)")
        case .preschool:
            return String(localized: "Preschool (4-5 years)")
        case .schoolAge:
            return String(localized: "School Age (5+ years)")
        }
    }

    /// Quebec ministry required staff-to-child ratio for this age group
    var requiredRatio: Double {
        switch self {
        case .infant:
            return 5.0  // 1 staff per 5 infants
        case .toddler:
            return 8.0  // 1 staff per 8 toddlers
        case .preschool:
            return 10.0 // 1 staff per 10 preschoolers
        case .schoolAge:
            return 20.0 // 1 staff per 20 school-age children
        }
    }

    /// Age range description in French (for Quebec context)
    var frenchDescription: String {
        switch self {
        case .infant:
            return "Poupons (0-18 mois)"
        case .toddler:
            return "Trottineurs (18 mois - 4 ans)"
        case .preschool:
            return "Préscolaire (4-5 ans)"
        case .schoolAge:
            return "Âge scolaire (5 ans et plus)"
        }
    }
}

// MARK: - Certification Status

/// Status of a staff member's certification.
struct CertificationStatus: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier
    let id: String

    /// Staff member ID
    let staffId: String

    /// Staff member name
    let staffName: String

    /// Type of certification
    let certificationType: CertificationType

    /// Current certification status
    let status: CertificationValidity

    /// Expiration date
    let expirationDate: Date?

    /// Days until expiration (negative if expired)
    var daysUntilExpiration: Int? {
        guard let expDate = expirationDate else { return nil }
        return Calendar.current.dateComponents([.day], from: Date(), to: expDate).day
    }

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case staffId = "staff_id"
        case staffName = "staff_name"
        case certificationType = "certification_type"
        case status
        case expirationDate = "expiration_date"
    }

    // MARK: - Computed Properties

    /// Whether the certification is expiring soon (within 30 days)
    var isExpiringSoon: Bool {
        guard let days = daysUntilExpiration else { return false }
        return days > 0 && days <= 30
    }

    /// Whether the certification has expired
    var isExpired: Bool {
        guard let days = daysUntilExpiration else { return false }
        return days < 0
    }

    /// Formatted expiration date string
    var formattedExpirationDate: String? {
        expirationDate?.displayDate
    }
}

// MARK: - Certification Type

/// Types of certifications required for childcare staff.
enum CertificationType: String, Codable, CaseIterable {
    case firstAid = "first_aid"
    case cpr = "cpr"
    case earlyChildhoodEducation = "ece"
    case foodHandler = "food_handler"
    case criminalBackgroundCheck = "criminal_check"

    var displayName: String {
        switch self {
        case .firstAid:
            return String(localized: "First Aid")
        case .cpr:
            return String(localized: "CPR")
        case .earlyChildhoodEducation:
            return String(localized: "Early Childhood Education")
        case .foodHandler:
            return String(localized: "Food Handler")
        case .criminalBackgroundCheck:
            return String(localized: "Criminal Background Check")
        }
    }

    /// Whether this certification expires
    var expires: Bool {
        switch self {
        case .firstAid, .cpr, .foodHandler:
            return true
        case .earlyChildhoodEducation, .criminalBackgroundCheck:
            return false
        }
    }

    /// Typical validity period in years (if applicable)
    var validityYears: Int? {
        switch self {
        case .firstAid:
            return 3
        case .cpr:
            return 1
        case .foodHandler:
            return 5
        case .earlyChildhoodEducation, .criminalBackgroundCheck:
            return nil
        }
    }
}

// MARK: - Certification Validity

/// Validity status for a certification.
enum CertificationValidity: String, Codable, CaseIterable {
    case valid = "valid"
    case expiringSoon = "expiring_soon"
    case expired = "expired"
    case missing = "missing"

    var displayName: String {
        switch self {
        case .valid:
            return String(localized: "Valid")
        case .expiringSoon:
            return String(localized: "Expiring Soon")
        case .expired:
            return String(localized: "Expired")
        case .missing:
            return String(localized: "Missing")
        }
    }

    /// Color name for this validity status
    var color: String {
        switch self {
        case .valid:
            return "green"
        case .expiringSoon:
            return "orange"
        case .expired, .missing:
            return "red"
        }
    }

    /// Corresponding compliance status
    var complianceStatus: ComplianceStatus {
        switch self {
        case .valid:
            return .compliant
        case .expiringSoon:
            return .warning
        case .expired, .missing:
            return .violation
        }
    }
}

// MARK: - Compliance Request

/// Request parameters for fetching compliance data.
struct ComplianceRequest: Codable {

    /// Facility ID for facility-specific compliance
    let facilityId: String?

    /// Check types to include (nil for all)
    let checkTypes: [ComplianceCheckType]?

    /// Whether to include detailed breakdown
    let includeDetails: Bool

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case facilityId = "facility_id"
        case checkTypes = "check_types"
        case includeDetails = "include_details"
    }
}
