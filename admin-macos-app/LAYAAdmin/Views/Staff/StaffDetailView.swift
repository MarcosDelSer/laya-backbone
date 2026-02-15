//
//  StaffDetailView.swift
//  LAYAAdmin
//
//  Detail view for displaying comprehensive staff member information.
//  Shows personal details, employment info, certifications, emergency contacts,
//  and schedule information in organized sections.
//

import SwiftUI

// MARK: - Staff Detail View

/// A comprehensive detail view displaying all information about a staff member.
///
/// Features:
/// - Personal information header with avatar
/// - Employment status and role
/// - Certifications with expiry tracking
/// - Emergency contact information
/// - Schedule overview
/// - Edit functionality
/// - Delete with confirmation
struct StaffDetailView: View {

    // MARK: - Properties

    /// The staff member to display
    let staff: Staff

    /// Callback when edit is requested
    var onEdit: ((Staff) -> Void)?

    /// Callback when delete is requested
    var onDelete: ((Staff) -> Void)?

    /// Environment to dismiss the view
    @Environment(\.dismiss) private var dismiss

    /// Whether to show delete confirmation dialog
    @State private var showDeleteConfirmation = false

    // MARK: - Body

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                // Header section with avatar and basic info
                headerSection

                Divider()

                // Main content sections
                personalInfoSection

                employmentSection

                if let certifications = staff.certifications, !certifications.isEmpty {
                    certificationsSection(certifications)
                }

                emergencyContactSection

                if let notes = staff.notes, !notes.isEmpty {
                    notesSection(notes)
                }

                // Metadata section
                metadataSection
            }
            .padding(24)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(staff.fullName)
        .toolbar {
            detailToolbar
        }
        .confirmationDialog(
            String(localized: "Delete Staff Member"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                onDelete?(staff)
                dismiss()
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        } message: {
            Text(String(localized: "Are you sure you want to delete \(staff.fullName)? This action cannot be undone."))
        }
    }

    // MARK: - Header Section

    private var headerSection: some View {
        HStack(spacing: 20) {
            // Large avatar
            staffAvatar

            VStack(alignment: .leading, spacing: 8) {
                // Name and status
                HStack(spacing: 12) {
                    Text(staff.fullName)
                        .font(.title)
                        .fontWeight(.bold)

                    StaffStatusBadge(status: staff.status)

                    StaffRoleBadge(role: staff.role)

                    if staff.hasCertificationConcerns {
                        CertificationAlertBadge()
                    }
                }

                // Quick info row
                HStack(spacing: 20) {
                    Label(staff.roleDisplayName, systemImage: "person.badge.shield.checkmark")
                        .font(.headline)
                        .foregroundColor(.secondary)

                    if let classroom = staff.assignedClassroomName {
                        Label(classroom, systemImage: "building")
                            .font(.headline)
                            .foregroundColor(.secondary)
                    }

                    if staff.yearsEmployed > 0 {
                        Label(
                            String(localized: "\(staff.yearsEmployed) years employed"),
                            systemImage: "calendar.badge.clock"
                        )
                        .font(.headline)
                        .foregroundColor(.secondary)
                    }
                }
            }

            Spacer()
        }
    }

    // MARK: - Staff Avatar

    private var staffAvatar: some View {
        Group {
            if let photoURL = staff.profilePhotoURL, let url = URL(string: photoURL) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .aspectRatio(contentMode: .fill)
                    case .failure:
                        avatarPlaceholder
                    case .empty:
                        ProgressView()
                            .frame(width: 80, height: 80)
                    @unknown default:
                        avatarPlaceholder
                    }
                }
            } else {
                avatarPlaceholder
            }
        }
        .frame(width: 80, height: 80)
        .clipShape(Circle())
        .overlay(
            Circle()
                .stroke(avatarBorderColor, lineWidth: 3)
        )
        .shadow(color: Color.black.opacity(0.1), radius: 4, x: 0, y: 2)
    }

    private var avatarPlaceholder: some View {
        Circle()
            .fill(avatarBackgroundColor)
            .overlay {
                Text(staff.initials)
                    .font(.system(size: 28, weight: .semibold))
                    .foregroundColor(avatarTextColor)
            }
    }

    private var avatarBackgroundColor: Color {
        switch staff.status {
        case .active:
            return Color.accentColor.opacity(0.15)
        case .onLeave:
            return Color.orange.opacity(0.15)
        case .terminated:
            return Color.gray.opacity(0.15)
        case .suspended:
            return Color.red.opacity(0.15)
        }
    }

    private var avatarTextColor: Color {
        switch staff.status {
        case .active:
            return .accentColor
        case .onLeave:
            return .orange
        case .terminated:
            return .gray
        case .suspended:
            return .red
        }
    }

    private var avatarBorderColor: Color {
        switch staff.status {
        case .active:
            return .green
        case .onLeave:
            return .orange
        case .terminated:
            return .gray
        case .suspended:
            return .red
        }
    }

    // MARK: - Personal Info Section

    private var personalInfoSection: some View {
        DetailSection(title: String(localized: "Personal Information")) {
            LazyVGrid(columns: [
                GridItem(.flexible(), spacing: 16),
                GridItem(.flexible(), spacing: 16)
            ], alignment: .leading, spacing: 16) {
                DetailField(
                    label: String(localized: "First Name"),
                    value: staff.firstName,
                    icon: "person"
                )

                DetailField(
                    label: String(localized: "Last Name"),
                    value: staff.lastName,
                    icon: "person"
                )

                DetailField(
                    label: String(localized: "Email"),
                    value: staff.email,
                    icon: "envelope"
                )

                if let phone = staff.phone {
                    DetailField(
                        label: String(localized: "Phone"),
                        value: phone.formattedPhoneNumber,
                        icon: "phone"
                    )
                }

                if let employeeNumber = staff.employeeNumber {
                    DetailField(
                        label: String(localized: "Employee ID"),
                        value: employeeNumber,
                        icon: "number"
                    )
                }
            }

            // Contact action buttons
            HStack(spacing: 12) {
                Button(action: {
                    if let url = URL(string: "mailto:\(staff.email)") {
                        NSWorkspace.shared.open(url)
                    }
                }) {
                    Label(String(localized: "Send Email"), systemImage: "envelope.fill")
                }
                .buttonStyle(.bordered)

                if let phone = staff.phone, !phone.isEmpty {
                    Button(action: {
                        if let url = URL(string: "tel:\(phone.filter { $0.isNumber })") {
                            NSWorkspace.shared.open(url)
                        }
                    }) {
                        Label(String(localized: "Call"), systemImage: "phone.fill")
                    }
                    .buttonStyle(.bordered)
                }
            }
            .padding(.top, 8)
        }
    }

    // MARK: - Employment Section

    private var employmentSection: some View {
        DetailSection(title: String(localized: "Employment Details")) {
            LazyVGrid(columns: [
                GridItem(.flexible(), spacing: 16),
                GridItem(.flexible(), spacing: 16)
            ], alignment: .leading, spacing: 16) {
                DetailField(
                    label: String(localized: "Status"),
                    value: staff.status.displayName,
                    icon: "checkmark.circle",
                    valueColor: statusColor
                )

                DetailField(
                    label: String(localized: "Role"),
                    value: staff.role.displayName,
                    icon: "person.badge.shield.checkmark"
                )

                DetailField(
                    label: String(localized: "Hire Date"),
                    value: staff.hireDate.displayDate,
                    icon: "calendar.badge.plus"
                )

                if let classroom = staff.assignedClassroomName {
                    DetailField(
                        label: String(localized: "Assigned Classroom"),
                        value: classroom,
                        icon: "building"
                    )
                }

                if let hourlyRate = staff.hourlyRate {
                    DetailField(
                        label: String(localized: "Hourly Rate"),
                        value: hourlyRate.asCurrency,
                        icon: "dollarsign.circle"
                    )
                }

                if let contractedHours = staff.contractedHours {
                    DetailField(
                        label: String(localized: "Contracted Hours"),
                        value: String(format: "%.1f hrs/week", contractedHours),
                        icon: "clock"
                    )
                }

                if let terminationDate = staff.terminationDate {
                    DetailField(
                        label: String(localized: "Termination Date"),
                        value: terminationDate.displayDate,
                        icon: "calendar.badge.minus",
                        valueColor: .red
                    )
                }
            }
        }
    }

    private var statusColor: Color {
        switch staff.status {
        case .active:
            return .green
        case .onLeave:
            return .orange
        case .terminated:
            return .gray
        case .suspended:
            return .red
        }
    }

    // MARK: - Certifications Section

    private func certificationsSection(_ certifications: [StaffCertification]) -> some View {
        DetailSection(title: String(localized: "Certifications & Qualifications")) {
            VStack(alignment: .leading, spacing: 12) {
                ForEach(certifications) { certification in
                    CertificationCard(certification: certification)
                }
            }
        }
    }

    // MARK: - Emergency Contact Section

    private var emergencyContactSection: some View {
        DetailSection(title: String(localized: "Emergency Contact")) {
            if let contactName = staff.emergencyContactName,
               let contactPhone = staff.emergencyContactPhone {
                EmergencyContactCard(
                    name: contactName,
                    phone: contactPhone
                )
            } else {
                HStack {
                    Image(systemName: "exclamationmark.triangle")
                        .foregroundColor(.orange)
                    Text(String(localized: "No emergency contact on file"))
                        .foregroundColor(.secondary)
                }
                .padding()
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.orange.opacity(0.1))
                .cornerRadius(8)
            }
        }
    }

    // MARK: - Notes Section

    private func notesSection(_ notes: String) -> some View {
        DetailSection(title: String(localized: "Additional Notes")) {
            Text(notes)
                .font(.body)
                .foregroundColor(.primary)
                .padding()
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color(NSColor.controlBackgroundColor))
                .cornerRadius(8)
        }
    }

    // MARK: - Metadata Section

    private var metadataSection: some View {
        HStack(spacing: 24) {
            if let createdAt = staff.createdAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Created:"))
                        .foregroundColor(.secondary)
                    Text(createdAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            if let updatedAt = staff.updatedAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Updated:"))
                        .foregroundColor(.secondary)
                    Text(updatedAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            Spacer()

            Text(String(localized: "ID: \(staff.id)"))
                .font(.caption)
                .foregroundColor(.tertiary)
        }
        .padding(.top, 8)
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var detailToolbar: some ToolbarContent {
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                onEdit?(staff)
            }) {
                Label(String(localized: "Edit"), systemImage: "pencil")
            }
            .keyboardShortcut("e", modifiers: [.command])
            .help(String(localized: "Edit staff information (Cmd+E)"))
        }

        ToolbarItem(placement: .destructiveAction) {
            Button(role: .destructive, action: {
                showDeleteConfirmation = true
            }) {
                Label(String(localized: "Delete"), systemImage: "trash")
            }
            .help(String(localized: "Delete staff member"))
        }
    }
}

// MARK: - Certification Card

/// A card displaying certification information with status indication
struct CertificationCard: View {
    let certification: StaffCertification

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            // Status icon
            Image(systemName: statusIcon)
                .font(.title2)
                .foregroundColor(statusColor)
                .frame(width: 32)

            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(certification.name)
                        .font(.headline)

                    Spacer()

                    Text(certification.statusDisplayString)
                        .font(.caption)
                        .fontWeight(.medium)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(statusBackgroundColor)
                        .foregroundColor(statusColor)
                        .cornerRadius(4)
                }

                Text(certification.issuingBody)
                    .font(.subheadline)
                    .foregroundColor(.secondary)

                HStack(spacing: 16) {
                    Label(
                        String(localized: "Issued: \(certification.issueDate.displayDate)"),
                        systemImage: "calendar"
                    )
                    .font(.caption)
                    .foregroundColor(.secondary)

                    if let expiryDate = certification.expiryDate {
                        Label(
                            String(localized: "Expires: \(expiryDate.displayDate)"),
                            systemImage: "calendar.badge.exclamationmark"
                        )
                        .font(.caption)
                        .foregroundColor(certification.isExpiringSoon || !certification.isValid ? .orange : .secondary)
                    }
                }

                if let certNumber = certification.certificateNumber {
                    Text(String(localized: "Certificate #: \(certNumber)"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .stroke(statusColor.opacity(0.3), lineWidth: certification.isExpiringSoon || !certification.isValid ? 2 : 0)
        )
    }

    private var statusIcon: String {
        if !certification.isValid {
            return "xmark.circle.fill"
        } else if certification.isExpiringSoon {
            return "exclamationmark.triangle.fill"
        } else {
            return "checkmark.seal.fill"
        }
    }

    private var statusColor: Color {
        if !certification.isValid {
            return .red
        } else if certification.isExpiringSoon {
            return .orange
        } else {
            return .green
        }
    }

    private var statusBackgroundColor: Color {
        if !certification.isValid {
            return Color.red.opacity(0.15)
        } else if certification.isExpiringSoon {
            return Color.orange.opacity(0.15)
        } else {
            return Color.green.opacity(0.15)
        }
    }
}

// MARK: - Emergency Contact Card

/// A card displaying emergency contact information with call action
struct EmergencyContactCard: View {
    let name: String
    let phone: String

    var body: some View {
        HStack(spacing: 16) {
            Image(systemName: "person.crop.circle.badge.exclamationmark")
                .font(.title)
                .foregroundColor(.red)

            VStack(alignment: .leading, spacing: 4) {
                Text(String(localized: "Emergency Contact"))
                    .font(.caption)
                    .foregroundColor(.secondary)

                Text(name)
                    .font(.headline)

                Text(phone.formattedPhoneNumber)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
            }

            Spacer()

            Button(action: {
                if let url = URL(string: "tel:\(phone.filter { $0.isNumber })") {
                    NSWorkspace.shared.open(url)
                }
            }) {
                Image(systemName: "phone.circle.fill")
                    .font(.largeTitle)
                    .foregroundColor(.green)
            }
            .buttonStyle(.plain)
            .help(String(localized: "Call \(phone)"))
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

// MARK: - Preview

#Preview("Staff Detail View - Active Lead Educator") {
    NavigationStack {
        StaffDetailView(
            staff: .preview,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 900)
}

#Preview("Staff Detail View - Substitute") {
    NavigationStack {
        StaffDetailView(
            staff: .previewSubstitute,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 800)
}

#Preview("Staff Detail View - On Leave") {
    NavigationStack {
        StaffDetailView(
            staff: .previewOnLeave,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 800)
}

#Preview("Certification Card - Valid") {
    CertificationCard(
        certification: StaffCertification(
            id: "cert-preview",
            name: "Early Childhood Education",
            issuingBody: "Quebec Ministry of Education",
            issueDate: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
            expiryDate: nil,
            certificateNumber: "ECE-12345"
        )
    )
    .padding()
    .frame(width: 500)
}

#Preview("Certification Card - Expiring Soon") {
    CertificationCard(
        certification: StaffCertification(
            id: "cert-expiring",
            name: "First Aid & CPR",
            issuingBody: "Red Cross",
            issueDate: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
            expiryDate: Calendar.current.date(byAdding: .day, value: 15, to: Date()),
            certificateNumber: "FA-67890"
        )
    )
    .padding()
    .frame(width: 500)
}

#Preview("Certification Card - Expired") {
    CertificationCard(
        certification: StaffCertification(
            id: "cert-expired",
            name: "First Aid & CPR",
            issuingBody: "Red Cross",
            issueDate: Calendar.current.date(byAdding: .year, value: -3, to: Date()) ?? Date(),
            expiryDate: Calendar.current.date(byAdding: .day, value: -30, to: Date()),
            certificateNumber: "FA-11111"
        )
    )
    .padding()
    .frame(width: 500)
}

#Preview("Emergency Contact Card") {
    EmergencyContactCard(
        name: "Pierre Bouchard",
        phone: "(514) 555-6789"
    )
    .padding()
    .frame(width: 500)
}
