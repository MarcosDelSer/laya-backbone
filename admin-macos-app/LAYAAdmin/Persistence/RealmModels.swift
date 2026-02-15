//
//  RealmModels.swift
//  LAYAAdmin
//
//  Realm database models for offline data persistence.
//  These models mirror the API models and support offline sync.
//

import Foundation
import RealmSwift

// MARK: - Child Realm Object

/// Realm object representing an enrolled child.
/// Maps to the Child Swift struct for API operations.
class ChildObject: Object, ObjectKeyIdentifiable {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = ""

    // MARK: - Properties

    @Persisted var firstName: String = ""
    @Persisted var lastName: String = ""
    @Persisted var dateOfBirth: Date = Date()
    @Persisted var enrollmentStatusRaw: String = EnrollmentStatus.pending.rawValue
    @Persisted var classroomName: String?
    @Persisted var classroomId: String?
    @Persisted var primaryGuardianId: String = ""
    @Persisted var primaryGuardianName: String = ""
    @Persisted var primaryGuardianEmail: String?
    @Persisted var primaryGuardianPhone: String?
    @Persisted var secondaryGuardianId: String?
    @Persisted var secondaryGuardianName: String?
    @Persisted var allergies: String?
    @Persisted var medicalNotes: String?
    @Persisted var dietaryRequirements: String?
    @Persisted var profilePhotoURL: String?
    @Persisted var enrollmentDate: Date?
    @Persisted var expectedGraduationDate: Date?
    @Persisted var notes: String?
    @Persisted var createdAt: Date?
    @Persisted var updatedAt: Date?

    // MARK: - Sync Metadata

    /// When this record was last synced from the server
    @Persisted var lastSyncedAt: Date?

    /// Whether this record has local changes pending sync
    @Persisted var hasPendingChanges: Bool = false

    /// Whether this is a locally created record not yet on server
    @Persisted var isLocalOnly: Bool = false

    // MARK: - Computed Properties

    var enrollmentStatus: EnrollmentStatus {
        get { EnrollmentStatus(rawValue: enrollmentStatusRaw) ?? .pending }
        set { enrollmentStatusRaw = newValue.rawValue }
    }

    var fullName: String {
        "\(firstName) \(lastName)"
    }

    var initials: String {
        let firstInitial = firstName.first.map { String($0) } ?? ""
        let lastInitial = lastName.first.map { String($0) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }

    // MARK: - Conversion Methods

    /// Converts this Realm object to a Child struct
    func toModel() -> Child {
        Child(
            id: id,
            firstName: firstName,
            lastName: lastName,
            dateOfBirth: dateOfBirth,
            enrollmentStatus: enrollmentStatus,
            classroomName: classroomName,
            classroomId: classroomId,
            primaryGuardianId: primaryGuardianId,
            primaryGuardianName: primaryGuardianName,
            primaryGuardianEmail: primaryGuardianEmail,
            primaryGuardianPhone: primaryGuardianPhone,
            secondaryGuardianId: secondaryGuardianId,
            secondaryGuardianName: secondaryGuardianName,
            allergies: allergies,
            medicalNotes: medicalNotes,
            dietaryRequirements: dietaryRequirements,
            profilePhotoURL: profilePhotoURL,
            enrollmentDate: enrollmentDate,
            expectedGraduationDate: expectedGraduationDate,
            notes: notes,
            createdAt: createdAt,
            updatedAt: updatedAt
        )
    }

    /// Updates this Realm object from a Child struct
    func update(from model: Child) {
        firstName = model.firstName
        lastName = model.lastName
        dateOfBirth = model.dateOfBirth
        enrollmentStatus = model.enrollmentStatus
        classroomName = model.classroomName
        classroomId = model.classroomId
        primaryGuardianId = model.primaryGuardianId
        primaryGuardianName = model.primaryGuardianName
        primaryGuardianEmail = model.primaryGuardianEmail
        primaryGuardianPhone = model.primaryGuardianPhone
        secondaryGuardianId = model.secondaryGuardianId
        secondaryGuardianName = model.secondaryGuardianName
        allergies = model.allergies
        medicalNotes = model.medicalNotes
        dietaryRequirements = model.dietaryRequirements
        profilePhotoURL = model.profilePhotoURL
        enrollmentDate = model.enrollmentDate
        expectedGraduationDate = model.expectedGraduationDate
        notes = model.notes
        createdAt = model.createdAt
        updatedAt = model.updatedAt
    }

    /// Creates a ChildObject from a Child struct
    static func from(_ model: Child) -> ChildObject {
        let object = ChildObject()
        object.id = model.id
        object.update(from: model)
        object.lastSyncedAt = Date()
        return object
    }
}

// MARK: - Staff Realm Object

/// Realm object representing a staff member.
/// Maps to the Staff Swift struct for API operations.
class StaffObject: Object, ObjectKeyIdentifiable {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = ""

    // MARK: - Properties

    @Persisted var firstName: String = ""
    @Persisted var lastName: String = ""
    @Persisted var email: String = ""
    @Persisted var phone: String?
    @Persisted var roleRaw: String = StaffRole.educator.rawValue
    @Persisted var statusRaw: String = StaffStatus.active.rawValue
    @Persisted var hireDate: Date = Date()
    @Persisted var terminationDate: Date?
    @Persisted var assignedClassroomId: String?
    @Persisted var assignedClassroomName: String?
    @Persisted var employeeNumber: String?
    @Persisted var profilePhotoURL: String?
    @Persisted var emergencyContactName: String?
    @Persisted var emergencyContactPhone: String?
    @Persisted var certifications: List<StaffCertificationObject>
    @Persisted var hourlyRate: Double?
    @Persisted var contractedHours: Double?
    @Persisted var notes: String?
    @Persisted var createdAt: Date?
    @Persisted var updatedAt: Date?

    // MARK: - Sync Metadata

    @Persisted var lastSyncedAt: Date?
    @Persisted var hasPendingChanges: Bool = false
    @Persisted var isLocalOnly: Bool = false

    // MARK: - Computed Properties

    var role: StaffRole {
        get { StaffRole(rawValue: roleRaw) ?? .educator }
        set { roleRaw = newValue.rawValue }
    }

    var status: StaffStatus {
        get { StaffStatus(rawValue: statusRaw) ?? .active }
        set { statusRaw = newValue.rawValue }
    }

    var fullName: String {
        "\(firstName) \(lastName)"
    }

    var initials: String {
        let firstInitial = firstName.first.map { String($0) } ?? ""
        let lastInitial = lastName.first.map { String($0) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }

    // MARK: - Conversion Methods

    func toModel() -> Staff {
        Staff(
            id: id,
            firstName: firstName,
            lastName: lastName,
            email: email,
            phone: phone,
            role: role,
            status: status,
            hireDate: hireDate,
            terminationDate: terminationDate,
            assignedClassroomId: assignedClassroomId,
            assignedClassroomName: assignedClassroomName,
            employeeNumber: employeeNumber,
            profilePhotoURL: profilePhotoURL,
            emergencyContactName: emergencyContactName,
            emergencyContactPhone: emergencyContactPhone,
            certifications: Array(certifications).map { $0.toModel() },
            hourlyRate: hourlyRate,
            contractedHours: contractedHours,
            notes: notes,
            createdAt: createdAt,
            updatedAt: updatedAt
        )
    }

    func update(from model: Staff) {
        firstName = model.firstName
        lastName = model.lastName
        email = model.email
        phone = model.phone
        role = model.role
        status = model.status
        hireDate = model.hireDate
        terminationDate = model.terminationDate
        assignedClassroomId = model.assignedClassroomId
        assignedClassroomName = model.assignedClassroomName
        employeeNumber = model.employeeNumber
        profilePhotoURL = model.profilePhotoURL
        emergencyContactName = model.emergencyContactName
        emergencyContactPhone = model.emergencyContactPhone
        hourlyRate = model.hourlyRate
        contractedHours = model.contractedHours
        notes = model.notes
        createdAt = model.createdAt
        updatedAt = model.updatedAt

        // Update certifications
        certifications.removeAll()
        if let modelCerts = model.certifications {
            for cert in modelCerts {
                certifications.append(StaffCertificationObject.from(cert))
            }
        }
    }

    static func from(_ model: Staff) -> StaffObject {
        let object = StaffObject()
        object.id = model.id
        object.update(from: model)
        object.lastSyncedAt = Date()
        return object
    }
}

// MARK: - Staff Certification Realm Object

/// Realm object representing a staff certification.
class StaffCertificationObject: EmbeddedObject {

    @Persisted var id: String = ""
    @Persisted var name: String = ""
    @Persisted var issuingBody: String = ""
    @Persisted var issueDate: Date = Date()
    @Persisted var expiryDate: Date?
    @Persisted var certificateNumber: String?

    func toModel() -> StaffCertification {
        StaffCertification(
            id: id,
            name: name,
            issuingBody: issuingBody,
            issueDate: issueDate,
            expiryDate: expiryDate,
            certificateNumber: certificateNumber
        )
    }

    static func from(_ model: StaffCertification) -> StaffCertificationObject {
        let object = StaffCertificationObject()
        object.id = model.id
        object.name = model.name
        object.issuingBody = model.issuingBody
        object.issueDate = model.issueDate
        object.expiryDate = model.expiryDate
        object.certificateNumber = model.certificateNumber
        return object
    }
}

// MARK: - Invoice Realm Object

/// Realm object representing an invoice.
/// Maps to the Invoice Swift struct for API operations.
class InvoiceObject: Object, ObjectKeyIdentifiable {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = ""

    // MARK: - Properties

    @Persisted var number: String = ""
    @Persisted var familyId: String = ""
    @Persisted var familyName: String = ""
    @Persisted var childId: String?
    @Persisted var childName: String?
    @Persisted var date: Date = Date()
    @Persisted var dueDate: Date = Date()
    @Persisted var statusRaw: String = InvoiceStatus.pending.rawValue
    @Persisted var subtotal: Double = 0.0
    @Persisted var taxAmount: Double = 0.0
    @Persisted var totalAmount: Double = 0.0
    @Persisted var amountPaid: Double = 0.0
    @Persisted var items: List<InvoiceItemObject>
    @Persisted var periodStartDate: Date?
    @Persisted var periodEndDate: Date?
    @Persisted var pdfUrl: String?
    @Persisted var notes: String?
    @Persisted var createdAt: Date?
    @Persisted var updatedAt: Date?

    // MARK: - Sync Metadata

    @Persisted var lastSyncedAt: Date?
    @Persisted var hasPendingChanges: Bool = false
    @Persisted var isLocalOnly: Bool = false

    // MARK: - Computed Properties

    var status: InvoiceStatus {
        get { InvoiceStatus(rawValue: statusRaw) ?? .pending }
        set { statusRaw = newValue.rawValue }
    }

    var balanceDue: Double {
        max(0, totalAmount - amountPaid)
    }

    // MARK: - Conversion Methods

    func toModel() -> Invoice {
        Invoice(
            id: id,
            number: number,
            familyId: familyId,
            familyName: familyName,
            childId: childId,
            childName: childName,
            date: date,
            dueDate: dueDate,
            status: status,
            subtotal: subtotal,
            taxAmount: taxAmount,
            totalAmount: totalAmount,
            amountPaid: amountPaid,
            items: Array(items).map { $0.toModel() },
            periodStartDate: periodStartDate,
            periodEndDate: periodEndDate,
            pdfUrl: pdfUrl,
            notes: notes,
            createdAt: createdAt,
            updatedAt: updatedAt
        )
    }

    func update(from model: Invoice) {
        number = model.number
        familyId = model.familyId
        familyName = model.familyName
        childId = model.childId
        childName = model.childName
        date = model.date
        dueDate = model.dueDate
        status = model.status
        subtotal = model.subtotal
        taxAmount = model.taxAmount
        totalAmount = model.totalAmount
        amountPaid = model.amountPaid
        periodStartDate = model.periodStartDate
        periodEndDate = model.periodEndDate
        pdfUrl = model.pdfUrl
        notes = model.notes
        createdAt = model.createdAt
        updatedAt = model.updatedAt

        // Update items
        items.removeAll()
        for item in model.items {
            items.append(InvoiceItemObject.from(item))
        }
    }

    static func from(_ model: Invoice) -> InvoiceObject {
        let object = InvoiceObject()
        object.id = model.id
        object.update(from: model)
        object.lastSyncedAt = Date()
        return object
    }
}

// MARK: - Invoice Item Realm Object

/// Realm object representing an invoice line item.
class InvoiceItemObject: EmbeddedObject {

    @Persisted var id: String = ""
    @Persisted var itemDescription: String = ""
    @Persisted var quantity: Double = 0.0
    @Persisted var unitPrice: Double = 0.0
    @Persisted var total: Double = 0.0
    @Persisted var categoryRaw: String = InvoiceItemCategory.childcare.rawValue
    @Persisted var isQualifyingExpense: Bool = true

    var category: InvoiceItemCategory {
        get { InvoiceItemCategory(rawValue: categoryRaw) ?? .childcare }
        set { categoryRaw = newValue.rawValue }
    }

    func toModel() -> InvoiceItem {
        InvoiceItem(
            id: id,
            description: itemDescription,
            quantity: quantity,
            unitPrice: unitPrice,
            total: total,
            category: category,
            isQualifyingExpense: isQualifyingExpense
        )
    }

    static func from(_ model: InvoiceItem) -> InvoiceItemObject {
        let object = InvoiceItemObject()
        object.id = model.id
        object.itemDescription = model.description
        object.quantity = model.quantity
        object.unitPrice = model.unitPrice
        object.total = model.total
        object.category = model.category
        object.isQualifyingExpense = model.isQualifyingExpense
        return object
    }
}

// MARK: - Payment Realm Object

/// Realm object representing a payment.
/// Maps to the Payment Swift struct for API operations.
class PaymentObject: Object, ObjectKeyIdentifiable {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = ""

    // MARK: - Properties

    @Persisted var invoiceId: String = ""
    @Persisted var invoiceNumber: String?
    @Persisted var amount: Double = 0.0
    @Persisted var paymentDate: Date = Date()
    @Persisted var paymentMethodRaw: String = PaymentMethod.cash.rawValue
    @Persisted var referenceNumber: String?
    @Persisted var notes: String?
    @Persisted var recordedById: String?
    @Persisted var recordedByName: String?
    @Persisted var createdAt: Date?

    // MARK: - Sync Metadata

    @Persisted var lastSyncedAt: Date?
    @Persisted var hasPendingChanges: Bool = false
    @Persisted var isLocalOnly: Bool = false

    // MARK: - Computed Properties

    var paymentMethod: PaymentMethod {
        get { PaymentMethod(rawValue: paymentMethodRaw) ?? .cash }
        set { paymentMethodRaw = newValue.rawValue }
    }

    // MARK: - Conversion Methods

    func toModel() -> Payment {
        Payment(
            id: id,
            invoiceId: invoiceId,
            invoiceNumber: invoiceNumber,
            amount: amount,
            paymentDate: paymentDate,
            paymentMethod: paymentMethod,
            referenceNumber: referenceNumber,
            notes: notes,
            recordedById: recordedById,
            recordedByName: recordedByName,
            createdAt: createdAt
        )
    }

    func update(from model: Payment) {
        invoiceId = model.invoiceId
        invoiceNumber = model.invoiceNumber
        amount = model.amount
        paymentDate = model.paymentDate
        paymentMethod = model.paymentMethod
        referenceNumber = model.referenceNumber
        notes = model.notes
        recordedById = model.recordedById
        recordedByName = model.recordedByName
        createdAt = model.createdAt
    }

    static func from(_ model: Payment) -> PaymentObject {
        let object = PaymentObject()
        object.id = model.id
        object.update(from: model)
        object.lastSyncedAt = Date()
        return object
    }
}

// MARK: - Sync Queue Item

/// Represents a pending sync operation.
/// Used to track changes made while offline.
class SyncQueueItem: Object, ObjectKeyIdentifiable {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = UUID().uuidString

    // MARK: - Properties

    /// Type of entity (child, staff, invoice, payment)
    @Persisted var entityType: String = ""

    /// ID of the entity being modified
    @Persisted var entityId: String = ""

    /// Type of operation (create, update, delete)
    @Persisted var operationTypeRaw: String = SyncOperationType.create.rawValue

    /// JSON payload for the operation
    @Persisted var payload: String?

    /// When this operation was queued
    @Persisted var createdAt: Date = Date()

    /// Number of sync attempts
    @Persisted var attemptCount: Int = 0

    /// Last error message if sync failed
    @Persisted var lastError: String?

    /// Whether this operation has been processed
    @Persisted var isProcessed: Bool = false

    // MARK: - Computed Properties

    var operationType: SyncOperationType {
        get { SyncOperationType(rawValue: operationTypeRaw) ?? .create }
        set { operationTypeRaw = newValue.rawValue }
    }

    // MARK: - Factory Methods

    /// Creates a sync queue item for a child operation
    static func forChild(_ child: Child, operation: SyncOperationType) -> SyncQueueItem {
        let item = SyncQueueItem()
        item.entityType = SyncEntityType.child.rawValue
        item.entityId = child.id
        item.operationType = operation

        if operation != .delete {
            let encoder = JSONEncoder()
            encoder.dateEncodingStrategy = .iso8601
            if let data = try? encoder.encode(child) {
                item.payload = String(data: data, encoding: .utf8)
            }
        }

        return item
    }

    /// Creates a sync queue item for a staff operation
    static func forStaff(_ staff: Staff, operation: SyncOperationType) -> SyncQueueItem {
        let item = SyncQueueItem()
        item.entityType = SyncEntityType.staff.rawValue
        item.entityId = staff.id
        item.operationType = operation

        if operation != .delete {
            let encoder = JSONEncoder()
            encoder.dateEncodingStrategy = .iso8601
            if let data = try? encoder.encode(staff) {
                item.payload = String(data: data, encoding: .utf8)
            }
        }

        return item
    }

    /// Creates a sync queue item for an invoice operation
    static func forInvoice(_ invoice: Invoice, operation: SyncOperationType) -> SyncQueueItem {
        let item = SyncQueueItem()
        item.entityType = SyncEntityType.invoice.rawValue
        item.entityId = invoice.id
        item.operationType = operation

        if operation != .delete {
            let encoder = JSONEncoder()
            encoder.dateEncodingStrategy = .iso8601
            if let data = try? encoder.encode(invoice) {
                item.payload = String(data: data, encoding: .utf8)
            }
        }

        return item
    }

    /// Creates a sync queue item for a payment operation
    static func forPayment(_ payment: Payment, operation: SyncOperationType) -> SyncQueueItem {
        let item = SyncQueueItem()
        item.entityType = SyncEntityType.payment.rawValue
        item.entityId = payment.id
        item.operationType = operation

        if operation != .delete {
            let encoder = JSONEncoder()
            encoder.dateEncodingStrategy = .iso8601
            if let data = try? encoder.encode(payment) {
                item.payload = String(data: data, encoding: .utf8)
            }
        }

        return item
    }
}

// MARK: - Sync Operation Type

/// Type of sync operation
enum SyncOperationType: String, Codable {
    case create = "create"
    case update = "update"
    case delete = "delete"
}

// MARK: - Sync Entity Type

/// Type of entity being synced
enum SyncEntityType: String, Codable {
    case child = "child"
    case staff = "staff"
    case invoice = "invoice"
    case payment = "payment"
}

// MARK: - Sync Metadata

/// Metadata about sync status for the local database.
class SyncMetadata: Object {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = "sync_metadata"

    // MARK: - Properties

    /// Last successful sync timestamp
    @Persisted var lastSyncAt: Date?

    /// Last sync attempt timestamp
    @Persisted var lastSyncAttemptAt: Date?

    /// Whether a sync is currently in progress
    @Persisted var isSyncing: Bool = false

    /// Last sync error message
    @Persisted var lastSyncError: String?

    /// Number of pending sync operations
    @Persisted var pendingOperationsCount: Int = 0

    /// Last children sync timestamp
    @Persisted var childrenLastSyncAt: Date?

    /// Last staff sync timestamp
    @Persisted var staffLastSyncAt: Date?

    /// Last invoices sync timestamp
    @Persisted var invoicesLastSyncAt: Date?

    /// Last payments sync timestamp
    @Persisted var paymentsLastSyncAt: Date?
}

// MARK: - User Preferences Object

/// Stores user preferences in Realm for offline access.
class UserPreferencesObject: Object {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var userId: String = ""

    // MARK: - Properties

    @Persisted var notificationsEnabled: Bool = true
    @Persisted var autoSyncEnabled: Bool = true
    @Persisted var syncIntervalMinutes: Int = 15
    @Persisted var selectedLanguage: String = "en"
    @Persisted var lastViewedSection: String = "dashboard"

    // Notification preferences
    @Persisted var lowEnrollmentNotifications: Bool = true
    @Persisted var paymentDueNotifications: Bool = true
    @Persisted var certificationExpiryNotifications: Bool = true
    @Persisted var staffRatioNotifications: Bool = true
    @Persisted var syncCompletionNotifications: Bool = false
}

// MARK: - Cached Dashboard Data

/// Stores cached dashboard data for offline access.
class CachedDashboardObject: Object {

    // MARK: - Primary Key

    @Persisted(primaryKey: true) var id: String = "dashboard_cache"

    // MARK: - Properties

    /// JSON-encoded dashboard response
    @Persisted var dashboardJson: String?

    /// When this cache was created
    @Persisted var cachedAt: Date = Date()

    /// Cache expiry time
    @Persisted var expiresAt: Date = Date()

    // MARK: - Computed Properties

    var isExpired: Bool {
        Date() > expiresAt
    }
}
