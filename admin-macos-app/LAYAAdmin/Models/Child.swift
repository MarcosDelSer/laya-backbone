//
//  Child.swift
//  LAYAAdmin
//
//  Child domain models for the LAYA Admin application.
//

import Foundation

// MARK: - Child Model

/// Represents an enrolled child in the LAYA system.
/// Contains personal information, enrollment details, medical information,
/// and emergency contacts for childcare management.
struct Child: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the child
    let id: String

    /// Child's first name
    let firstName: String

    /// Child's last name
    let lastName: String

    /// Child's date of birth
    let dateOfBirth: Date

    /// Current enrollment status
    var enrollmentStatus: EnrollmentStatus

    /// Assigned classroom or group name
    let classroomName: String?

    /// Classroom identifier for API operations
    let classroomId: String?

    /// Primary parent/guardian ID
    let primaryGuardianId: String

    /// Primary parent/guardian name for display
    let primaryGuardianName: String

    /// Primary parent/guardian email
    let primaryGuardianEmail: String?

    /// Primary parent/guardian phone
    let primaryGuardianPhone: String?

    /// Secondary parent/guardian ID (optional)
    let secondaryGuardianId: String?

    /// Secondary parent/guardian name for display (optional)
    let secondaryGuardianName: String?

    /// Known allergies (optional)
    let allergies: String?

    /// Medical conditions or notes (optional)
    let medicalNotes: String?

    /// Special dietary requirements (optional)
    let dietaryRequirements: String?

    /// URL to the child's profile photo (optional)
    let profilePhotoURL: String?

    /// Date when the child was enrolled
    let enrollmentDate: Date?

    /// Expected graduation date (optional)
    let expectedGraduationDate: Date?

    /// Additional notes about the child (optional)
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
        case dateOfBirth = "date_of_birth"
        case enrollmentStatus = "enrollment_status"
        case classroomName = "classroom_name"
        case classroomId = "classroom_id"
        case primaryGuardianId = "primary_guardian_id"
        case primaryGuardianName = "primary_guardian_name"
        case primaryGuardianEmail = "primary_guardian_email"
        case primaryGuardianPhone = "primary_guardian_phone"
        case secondaryGuardianId = "secondary_guardian_id"
        case secondaryGuardianName = "secondary_guardian_name"
        case allergies
        case medicalNotes = "medical_notes"
        case dietaryRequirements = "dietary_requirements"
        case profilePhotoURL = "profile_photo_url"
        case enrollmentDate = "enrollment_date"
        case expectedGraduationDate = "expected_graduation_date"
        case notes
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Full name of the child
    var fullName: String {
        "\(firstName) \(lastName)"
    }

    /// Initials for avatar display
    var initials: String {
        let firstInitial = firstName.first.map { String($0) } ?? ""
        let lastInitial = lastName.first.map { String($0) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }

    /// Age of the child in years
    var age: Int {
        Calendar.current.dateComponents([.year], from: dateOfBirth, to: Date()).year ?? 0
    }

    /// Age of the child in months (for infants)
    var ageInMonths: Int {
        Calendar.current.dateComponents([.month], from: dateOfBirth, to: Date()).month ?? 0
    }

    /// Formatted age string (e.g., "3 years" or "18 months")
    var ageDisplayString: String {
        let months = ageInMonths
        if months < 24 {
            return String(localized: "\(months) months")
        } else {
            let years = age
            return String(localized: "\(years) years")
        }
    }

    /// Display string for enrollment status
    var enrollmentStatusDisplayName: String {
        enrollmentStatus.displayName
    }

    /// Whether the child has any medical concerns documented
    var hasMedicalConcerns: Bool {
        let hasAllergies = !(allergies?.isEmpty ?? true)
        let hasMedicalNotes = !(medicalNotes?.isEmpty ?? true)
        let hasDietaryReqs = !(dietaryRequirements?.isEmpty ?? true)
        return hasAllergies || hasMedicalNotes || hasDietaryReqs
    }

    /// Whether the child is currently active (not graduated or withdrawn)
    var isActive: Bool {
        enrollmentStatus == .active
    }
}

// MARK: - Child Extensions

extension Child {

    /// Creates a sample child for previews and testing
    static var preview: Child {
        Child(
            id: "preview-child-1",
            firstName: "Emma",
            lastName: "Tremblay",
            dateOfBirth: Calendar.current.date(byAdding: .year, value: -4, to: Date()) ?? Date(),
            enrollmentStatus: .active,
            classroomName: "Sunflowers",
            classroomId: "classroom-1",
            primaryGuardianId: "guardian-1",
            primaryGuardianName: "Marie Tremblay",
            primaryGuardianEmail: "marie.tremblay@email.com",
            primaryGuardianPhone: "(514) 555-1234",
            secondaryGuardianId: "guardian-2",
            secondaryGuardianName: "Jean Tremblay",
            allergies: "Peanuts",
            medicalNotes: nil,
            dietaryRequirements: nil,
            profilePhotoURL: nil,
            enrollmentDate: Calendar.current.date(byAdding: .year, value: -1, to: Date()),
            expectedGraduationDate: Calendar.current.date(byAdding: .year, value: 1, to: Date()),
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample infant child for previews and testing
    static var previewInfant: Child {
        Child(
            id: "preview-child-2",
            firstName: "Lucas",
            lastName: "Gagnon",
            dateOfBirth: Calendar.current.date(byAdding: .month, value: -14, to: Date()) ?? Date(),
            enrollmentStatus: .active,
            classroomName: "Little Stars",
            classroomId: "classroom-2",
            primaryGuardianId: "guardian-3",
            primaryGuardianName: "Sophie Gagnon",
            primaryGuardianEmail: "sophie.gagnon@email.com",
            primaryGuardianPhone: "(514) 555-5678",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: nil,
            medicalNotes: "Eczema - apply prescribed cream as needed",
            dietaryRequirements: "Lactose intolerant - uses soy formula",
            profilePhotoURL: nil,
            enrollmentDate: Calendar.current.date(byAdding: .month, value: -6, to: Date()),
            expectedGraduationDate: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample child on waitlist for previews
    static var previewWaitlist: Child {
        Child(
            id: "preview-child-3",
            firstName: "Olivier",
            lastName: "Roy",
            dateOfBirth: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
            enrollmentStatus: .waitlist,
            classroomName: nil,
            classroomId: nil,
            primaryGuardianId: "guardian-4",
            primaryGuardianName: "Isabelle Roy",
            primaryGuardianEmail: "isabelle.roy@email.com",
            primaryGuardianPhone: "(514) 555-9012",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: nil,
            medicalNotes: nil,
            dietaryRequirements: nil,
            profilePhotoURL: nil,
            enrollmentDate: nil,
            expectedGraduationDate: nil,
            notes: "Preferred start date: September 2026",
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - Child Summary

/// A lightweight representation of a child for list views and references.
struct ChildSummary: Identifiable, Codable, Equatable {

    /// Unique identifier for the child
    let id: String

    /// Child's full name
    let fullName: String

    /// Child's date of birth
    let dateOfBirth: Date

    /// Current enrollment status
    let enrollmentStatus: EnrollmentStatus

    /// Assigned classroom name
    let classroomName: String?

    /// URL to the child's profile photo (optional)
    let profilePhotoURL: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case fullName = "full_name"
        case dateOfBirth = "date_of_birth"
        case enrollmentStatus = "enrollment_status"
        case classroomName = "classroom_name"
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

    /// Age of the child in years
    var age: Int {
        Calendar.current.dateComponents([.year], from: dateOfBirth, to: Date()).year ?? 0
    }
}

// MARK: - Emergency Contact

/// Emergency contact information for a child.
struct EmergencyContact: Identifiable, Codable, Equatable {

    /// Unique identifier for the contact
    let id: String

    /// Contact's full name
    let name: String

    /// Relationship to the child (e.g., "Grandmother", "Uncle")
    let relationship: String

    /// Primary phone number
    let phone: String

    /// Secondary phone number (optional)
    let alternatePhone: String?

    /// Whether this contact is authorized for pickup
    let authorizedForPickup: Bool

    /// Priority order for contacting (1 = first)
    let priority: Int

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case name
        case relationship
        case phone
        case alternatePhone = "alternate_phone"
        case authorizedForPickup = "authorized_for_pickup"
        case priority
    }
}

// MARK: - Create/Update Child Request

/// Request payload for creating or updating a child record.
struct ChildRequest: Codable {

    /// Child's first name
    let firstName: String

    /// Child's last name
    let lastName: String

    /// Child's date of birth
    let dateOfBirth: Date

    /// Enrollment status (optional for updates)
    var enrollmentStatus: EnrollmentStatus?

    /// Assigned classroom ID (optional)
    let classroomId: String?

    /// Primary guardian ID (optional - generated if not provided)
    var primaryGuardianId: String?

    /// Primary guardian name (optional - for offline mode)
    var primaryGuardianName: String?

    /// Primary guardian email (optional)
    var primaryGuardianEmail: String?

    /// Primary guardian phone (optional)
    var primaryGuardianPhone: String?

    /// Secondary guardian ID (optional)
    let secondaryGuardianId: String?

    /// Secondary guardian name (optional)
    var secondaryGuardianName: String?

    /// Known allergies (optional)
    let allergies: String?

    /// Medical notes (optional)
    let medicalNotes: String?

    /// Dietary requirements (optional)
    let dietaryRequirements: String?

    /// Enrollment date (optional)
    let enrollmentDate: Date?

    /// Expected graduation date (optional)
    let expectedGraduationDate: Date?

    /// Additional notes (optional)
    let notes: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case firstName = "first_name"
        case lastName = "last_name"
        case dateOfBirth = "date_of_birth"
        case enrollmentStatus = "enrollment_status"
        case classroomId = "classroom_id"
        case primaryGuardianId = "primary_guardian_id"
        case primaryGuardianName = "primary_guardian_name"
        case primaryGuardianEmail = "primary_guardian_email"
        case primaryGuardianPhone = "primary_guardian_phone"
        case secondaryGuardianId = "secondary_guardian_id"
        case secondaryGuardianName = "secondary_guardian_name"
        case allergies
        case medicalNotes = "medical_notes"
        case dietaryRequirements = "dietary_requirements"
        case enrollmentDate = "enrollment_date"
        case expectedGraduationDate = "expected_graduation_date"
        case notes
    }
}
