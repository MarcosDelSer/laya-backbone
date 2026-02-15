//
//  Staff.swift
//  LAYAAdmin
//
//  Staff domain models for the LAYA Admin application.
//

import Foundation

// MARK: - Staff Role

/// Roles for staff members in the childcare facility
enum StaffRole: String, Codable, CaseIterable {
    case educator = "educator"
    case leadEducator = "lead_educator"
    case director = "director"
    case assistantDirector = "assistant_director"
    case cook = "cook"
    case administrative = "administrative"
    case maintenance = "maintenance"
    case substitute = "substitute"

    var displayName: String {
        switch self {
        case .educator:
            return String(localized: "Educator")
        case .leadEducator:
            return String(localized: "Lead Educator")
        case .director:
            return String(localized: "Director")
        case .assistantDirector:
            return String(localized: "Assistant Director")
        case .cook:
            return String(localized: "Cook")
        case .administrative:
            return String(localized: "Administrative")
        case .maintenance:
            return String(localized: "Maintenance")
        case .substitute:
            return String(localized: "Substitute")
        }
    }

    /// Whether this role is a primary childcare role
    var isChildcareRole: Bool {
        switch self {
        case .educator, .leadEducator, .substitute:
            return true
        default:
            return false
        }
    }
}

// MARK: - Staff Status

/// Employment status for staff members
enum StaffStatus: String, Codable, CaseIterable {
    case active = "active"
    case onLeave = "on_leave"
    case terminated = "terminated"
    case suspended = "suspended"

    var displayName: String {
        switch self {
        case .active:
            return String(localized: "Active")
        case .onLeave:
            return String(localized: "On Leave")
        case .terminated:
            return String(localized: "Terminated")
        case .suspended:
            return String(localized: "Suspended")
        }
    }

    var color: String {
        switch self {
        case .active:
            return "green"
        case .onLeave:
            return "orange"
        case .terminated:
            return "gray"
        case .suspended:
            return "red"
        }
    }
}

// MARK: - Staff Model

/// Represents a staff member in the LAYA system.
/// Contains personal information, employment details, certifications,
/// and schedule information for workforce management.
struct Staff: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the staff member
    let id: String

    /// Staff member's first name
    let firstName: String

    /// Staff member's last name
    let lastName: String

    /// Email address
    let email: String

    /// Phone number
    let phone: String?

    /// Role/position in the facility
    let role: StaffRole

    /// Employment status
    var status: StaffStatus

    /// Date of hire
    let hireDate: Date

    /// Termination date (if applicable)
    let terminationDate: Date?

    /// Assigned classroom or group ID (for educators)
    let assignedClassroomId: String?

    /// Assigned classroom name for display
    let assignedClassroomName: String?

    /// Employee number/ID for HR purposes
    let employeeNumber: String?

    /// URL to the staff member's profile photo (optional)
    let profilePhotoURL: String?

    /// Emergency contact name
    let emergencyContactName: String?

    /// Emergency contact phone
    let emergencyContactPhone: String?

    /// Certifications held (e.g., First Aid, ECE)
    let certifications: [StaffCertification]?

    /// Hourly rate (for scheduling calculations)
    let hourlyRate: Double?

    /// Weekly contracted hours
    let contractedHours: Double?

    /// Additional notes about the staff member
    let notes: String?

    /// Date when the record was created
    let createdAt: Date?

    /// Date when the record was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case firstName = "first_name"
        case lastName = "last_name"
        case email
        case phone
        case role
        case status
        case hireDate = "hire_date"
        case terminationDate = "termination_date"
        case assignedClassroomId = "assigned_classroom_id"
        case assignedClassroomName = "assigned_classroom_name"
        case employeeNumber = "employee_number"
        case profilePhotoURL = "profile_photo_url"
        case emergencyContactName = "emergency_contact_name"
        case emergencyContactPhone = "emergency_contact_phone"
        case certifications
        case hourlyRate = "hourly_rate"
        case contractedHours = "contracted_hours"
        case notes
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Full name of the staff member
    var fullName: String {
        "\(firstName) \(lastName)"
    }

    /// Initials for avatar display
    var initials: String {
        let firstInitial = firstName.first.map { String($0) } ?? ""
        let lastInitial = lastName.first.map { String($0) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }

    /// Display string for role
    var roleDisplayName: String {
        role.displayName
    }

    /// Display string for status
    var statusDisplayName: String {
        status.displayName
    }

    /// Years of employment
    var yearsEmployed: Int {
        let endDate = terminationDate ?? Date()
        return Calendar.current.dateComponents([.year], from: hireDate, to: endDate).year ?? 0
    }

    /// Whether the staff member is currently active
    var isActive: Bool {
        status == .active
    }

    /// Whether the staff member works directly with children
    var isChildcareStaff: Bool {
        role.isChildcareRole
    }

    /// Whether any certifications are expired or expiring soon
    var hasCertificationConcerns: Bool {
        guard let certifications = certifications else { return false }
        let thirtyDaysFromNow = Calendar.current.date(byAdding: .day, value: 30, to: Date()) ?? Date()
        return certifications.contains { cert in
            guard let expiryDate = cert.expiryDate else { return false }
            return expiryDate < thirtyDaysFromNow
        }
    }
}

// MARK: - Staff Extensions

extension Staff {

    /// Creates a sample staff member for previews and testing
    static var preview: Staff {
        Staff(
            id: "preview-staff-1",
            firstName: "Isabelle",
            lastName: "Bouchard",
            email: "isabelle.bouchard@laya.ca",
            phone: "(514) 555-2345",
            role: .leadEducator,
            status: .active,
            hireDate: Calendar.current.date(byAdding: .year, value: -3, to: Date()) ?? Date(),
            terminationDate: nil,
            assignedClassroomId: "classroom-1",
            assignedClassroomName: "Sunflowers",
            employeeNumber: "EMP-001",
            profilePhotoURL: nil,
            emergencyContactName: "Pierre Bouchard",
            emergencyContactPhone: "(514) 555-6789",
            certifications: [
                StaffCertification(
                    id: "cert-1",
                    name: "Early Childhood Education",
                    issuingBody: "Quebec Ministry of Education",
                    issueDate: Calendar.current.date(byAdding: .year, value: -5, to: Date()) ?? Date(),
                    expiryDate: nil,
                    certificateNumber: "ECE-12345"
                ),
                StaffCertification(
                    id: "cert-2",
                    name: "First Aid & CPR",
                    issuingBody: "Red Cross",
                    issueDate: Calendar.current.date(byAdding: .month, value: -6, to: Date()) ?? Date(),
                    expiryDate: Calendar.current.date(byAdding: .month, value: 18, to: Date()),
                    certificateNumber: "FA-67890"
                )
            ],
            hourlyRate: 22.50,
            contractedHours: 37.5,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample substitute staff for previews
    static var previewSubstitute: Staff {
        Staff(
            id: "preview-staff-2",
            firstName: "Marc",
            lastName: "Lefebvre",
            email: "marc.lefebvre@laya.ca",
            phone: "(514) 555-3456",
            role: .substitute,
            status: .active,
            hireDate: Calendar.current.date(byAdding: .month, value: -6, to: Date()) ?? Date(),
            terminationDate: nil,
            assignedClassroomId: nil,
            assignedClassroomName: nil,
            employeeNumber: "EMP-SUB-001",
            profilePhotoURL: nil,
            emergencyContactName: "Claire Lefebvre",
            emergencyContactPhone: "(514) 555-7890",
            certifications: [
                StaffCertification(
                    id: "cert-3",
                    name: "First Aid & CPR",
                    issuingBody: "Red Cross",
                    issueDate: Calendar.current.date(byAdding: .month, value: -3, to: Date()) ?? Date(),
                    expiryDate: Calendar.current.date(byAdding: .month, value: 21, to: Date()),
                    certificateNumber: "FA-11111"
                )
            ],
            hourlyRate: 18.00,
            contractedHours: nil,
            notes: "Available Mon-Wed",
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample staff member on leave for previews
    static var previewOnLeave: Staff {
        Staff(
            id: "preview-staff-3",
            firstName: "Julie",
            lastName: "Martin",
            email: "julie.martin@laya.ca",
            phone: "(514) 555-4567",
            role: .educator,
            status: .onLeave,
            hireDate: Calendar.current.date(byAdding: .year, value: -5, to: Date()) ?? Date(),
            terminationDate: nil,
            assignedClassroomId: "classroom-2",
            assignedClassroomName: "Little Stars",
            employeeNumber: "EMP-002",
            profilePhotoURL: nil,
            emergencyContactName: "Robert Martin",
            emergencyContactPhone: "(514) 555-8901",
            certifications: nil,
            hourlyRate: 20.00,
            contractedHours: 37.5,
            notes: "Maternity leave until September 2026",
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - Staff Summary

/// A lightweight representation of a staff member for list views and references.
struct StaffSummary: Identifiable, Codable, Equatable {

    /// Unique identifier for the staff member
    let id: String

    /// Staff member's full name
    let fullName: String

    /// Role/position
    let role: StaffRole

    /// Employment status
    let status: StaffStatus

    /// Assigned classroom name (if applicable)
    let assignedClassroomName: String?

    /// URL to profile photo (optional)
    let profilePhotoURL: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case fullName = "full_name"
        case role
        case status
        case assignedClassroomName = "assigned_classroom_name"
        case profilePhotoURL = "profile_photo_url"
    }

    // MARK: - Computed Properties

    /// Initials for avatar display
    var initials: String {
        let components = fullName.split(separator: " ")
        let firstInitial = components.first.map { String($0.first ?? Character(" ")) } ?? ""
        let lastInitial = components.last.map { String($0.first ?? Character(" ")) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }
}

// MARK: - Staff Certification

/// Certification or qualification held by a staff member.
struct StaffCertification: Identifiable, Codable, Equatable {

    /// Unique identifier for the certification
    let id: String

    /// Name of the certification (e.g., "First Aid & CPR")
    let name: String

    /// Organization that issued the certification
    let issuingBody: String

    /// Date when the certification was issued
    let issueDate: Date

    /// Expiry date (optional, some certifications don't expire)
    let expiryDate: Date?

    /// Certificate number or reference
    let certificateNumber: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case name
        case issuingBody = "issuing_body"
        case issueDate = "issue_date"
        case expiryDate = "expiry_date"
        case certificateNumber = "certificate_number"
    }

    // MARK: - Computed Properties

    /// Whether the certification is currently valid
    var isValid: Bool {
        guard let expiryDate = expiryDate else { return true }
        return expiryDate > Date()
    }

    /// Whether the certification is expiring within 30 days
    var isExpiringSoon: Bool {
        guard let expiryDate = expiryDate else { return false }
        let thirtyDaysFromNow = Calendar.current.date(byAdding: .day, value: 30, to: Date()) ?? Date()
        return expiryDate < thirtyDaysFromNow && expiryDate > Date()
    }

    /// Status display string
    var statusDisplayString: String {
        if !isValid {
            return String(localized: "Expired")
        } else if isExpiringSoon {
            return String(localized: "Expiring Soon")
        } else {
            return String(localized: "Valid")
        }
    }
}

// MARK: - Staff Schedule Entry

/// A single shift or schedule entry for a staff member.
struct ScheduleEntry: Identifiable, Codable, Equatable {

    /// Unique identifier for the schedule entry
    let id: String

    /// Staff member ID
    let staffId: String

    /// Date of the shift
    let date: Date

    /// Start time of the shift
    let startTime: Date

    /// End time of the shift
    let endTime: Date

    /// Assigned classroom ID (optional)
    let classroomId: String?

    /// Assigned classroom name for display
    let classroomName: String?

    /// Whether this is a substitute coverage entry
    let isSubstituteCoverage: Bool

    /// Notes about this schedule entry
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case staffId = "staff_id"
        case date
        case startTime = "start_time"
        case endTime = "end_time"
        case classroomId = "classroom_id"
        case classroomName = "classroom_name"
        case isSubstituteCoverage = "is_substitute_coverage"
        case notes
    }

    // MARK: - Computed Properties

    /// Duration of the shift in hours
    var durationHours: Double {
        let interval = endTime.timeIntervalSince(startTime)
        return interval / 3600.0
    }
}

// MARK: - Create/Update Staff Request

/// Request payload for creating or updating a staff record.
struct StaffRequest: Codable {

    /// Staff member's first name
    let firstName: String

    /// Staff member's last name
    let lastName: String

    /// Email address
    let email: String

    /// Phone number (optional)
    let phone: String?

    /// Role/position
    let role: StaffRole

    /// Employment status
    let status: StaffStatus

    /// Date of hire
    let hireDate: Date

    /// Assigned classroom ID (optional)
    let assignedClassroomId: String?

    /// Employee number (optional)
    let employeeNumber: String?

    /// Emergency contact name (optional)
    let emergencyContactName: String?

    /// Emergency contact phone (optional)
    let emergencyContactPhone: String?

    /// Hourly rate (optional)
    let hourlyRate: Double?

    /// Contracted hours per week (optional)
    let contractedHours: Double?

    /// Additional notes (optional)
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case firstName = "first_name"
        case lastName = "last_name"
        case email
        case phone
        case role
        case status
        case hireDate = "hire_date"
        case assignedClassroomId = "assigned_classroom_id"
        case employeeNumber = "employee_number"
        case emergencyContactName = "emergency_contact_name"
        case emergencyContactPhone = "emergency_contact_phone"
        case hourlyRate = "hourly_rate"
        case contractedHours = "contracted_hours"
        case notes
    }
}
