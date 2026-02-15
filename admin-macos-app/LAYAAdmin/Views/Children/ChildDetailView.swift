//
//  ChildDetailView.swift
//  LAYAAdmin
//
//  Detail view for displaying comprehensive child information.
//  Shows personal details, enrollment info, guardians, medical info,
//  and emergency contacts in organized sections.
//

import SwiftUI

// MARK: - Child Detail View

/// A comprehensive detail view displaying all information about an enrolled child.
///
/// Features:
/// - Personal information header with avatar
/// - Enrollment status and dates
/// - Guardian contact information
/// - Medical and dietary information
/// - Emergency contacts
/// - Edit functionality
/// - Delete with confirmation
struct ChildDetailView: View {

    // MARK: - Properties

    /// The child to display
    let child: Child

    /// Callback when edit is requested
    var onEdit: ((Child) -> Void)?

    /// Callback when delete is requested
    var onDelete: ((Child) -> Void)?

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

                enrollmentSection

                guardiansSection

                if child.hasMedicalConcerns {
                    medicalSection
                }

                notesSection

                // Metadata section
                metadataSection
            }
            .padding(24)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(child.fullName)
        .toolbar {
            detailToolbar
        }
        .confirmationDialog(
            String(localized: "Delete Child"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                onDelete?(child)
                dismiss()
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        } message: {
            Text(String(localized: "Are you sure you want to delete \(child.fullName)? This action cannot be undone."))
        }
    }

    // MARK: - Header Section

    private var headerSection: some View {
        HStack(spacing: 20) {
            // Large avatar
            childAvatar

            VStack(alignment: .leading, spacing: 8) {
                // Name and status
                HStack(spacing: 12) {
                    Text(child.fullName)
                        .font(.title)
                        .fontWeight(.bold)

                    EnrollmentStatusBadge(status: child.enrollmentStatus)

                    if child.hasMedicalConcerns {
                        MedicalAlertBadge()
                    }
                }

                // Quick info row
                HStack(spacing: 20) {
                    Label(child.ageDisplayString, systemImage: "calendar")
                        .font(.headline)
                        .foregroundColor(.secondary)

                    if let classroom = child.classroomName {
                        Label(classroom, systemImage: "building")
                            .font(.headline)
                            .foregroundColor(.secondary)
                    }

                    Label(child.dateOfBirth.displayDate, systemImage: "birthday.cake")
                        .font(.headline)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()
        }
    }

    // MARK: - Child Avatar

    private var childAvatar: some View {
        Group {
            if let photoURL = child.profilePhotoURL, let url = URL(string: photoURL) {
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
                Text(child.initials)
                    .font(.system(size: 28, weight: .semibold))
                    .foregroundColor(avatarTextColor)
            }
    }

    private var avatarBackgroundColor: Color {
        switch child.enrollmentStatus {
        case .active:
            return Color.accentColor.opacity(0.15)
        case .pending:
            return Color.orange.opacity(0.15)
        case .waitlist:
            return Color.purple.opacity(0.15)
        case .graduated:
            return Color.blue.opacity(0.15)
        case .withdrawn:
            return Color.gray.opacity(0.15)
        }
    }

    private var avatarTextColor: Color {
        switch child.enrollmentStatus {
        case .active:
            return .accentColor
        case .pending:
            return .orange
        case .waitlist:
            return .purple
        case .graduated:
            return .blue
        case .withdrawn:
            return .gray
        }
    }

    private var avatarBorderColor: Color {
        switch child.enrollmentStatus {
        case .active:
            return .green
        case .pending:
            return .orange
        case .waitlist:
            return .purple
        case .graduated:
            return .blue
        case .withdrawn:
            return .gray
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
                    value: child.firstName,
                    icon: "person"
                )

                DetailField(
                    label: String(localized: "Last Name"),
                    value: child.lastName,
                    icon: "person"
                )

                DetailField(
                    label: String(localized: "Date of Birth"),
                    value: child.dateOfBirth.displayDate,
                    icon: "calendar"
                )

                DetailField(
                    label: String(localized: "Age"),
                    value: child.ageDisplayString,
                    icon: "figure.child"
                )
            }
        }
    }

    // MARK: - Enrollment Section

    private var enrollmentSection: some View {
        DetailSection(title: String(localized: "Enrollment Details")) {
            LazyVGrid(columns: [
                GridItem(.flexible(), spacing: 16),
                GridItem(.flexible(), spacing: 16)
            ], alignment: .leading, spacing: 16) {
                DetailField(
                    label: String(localized: "Status"),
                    value: child.enrollmentStatus.displayName,
                    icon: "checkmark.circle",
                    valueColor: statusColor
                )

                if let classroom = child.classroomName {
                    DetailField(
                        label: String(localized: "Classroom"),
                        value: classroom,
                        icon: "building"
                    )
                }

                if let enrollmentDate = child.enrollmentDate {
                    DetailField(
                        label: String(localized: "Enrollment Date"),
                        value: enrollmentDate.displayDate,
                        icon: "calendar.badge.plus"
                    )
                }

                if let graduationDate = child.expectedGraduationDate {
                    DetailField(
                        label: String(localized: "Expected Graduation"),
                        value: graduationDate.displayDate,
                        icon: "graduationcap"
                    )
                }
            }
        }
    }

    private var statusColor: Color {
        switch child.enrollmentStatus {
        case .active:
            return .green
        case .pending:
            return .orange
        case .waitlist:
            return .purple
        case .graduated:
            return .blue
        case .withdrawn:
            return .gray
        }
    }

    // MARK: - Guardians Section

    private var guardiansSection: some View {
        DetailSection(title: String(localized: "Guardians")) {
            VStack(alignment: .leading, spacing: 16) {
                // Primary Guardian
                GuardianCard(
                    title: String(localized: "Primary Guardian"),
                    name: child.primaryGuardianName,
                    email: child.primaryGuardianEmail,
                    phone: child.primaryGuardianPhone,
                    isPrimary: true
                )

                // Secondary Guardian (if exists)
                if let secondaryName = child.secondaryGuardianName {
                    GuardianCard(
                        title: String(localized: "Secondary Guardian"),
                        name: secondaryName,
                        email: nil,
                        phone: nil,
                        isPrimary: false
                    )
                }
            }
        }
    }

    // MARK: - Medical Section

    private var medicalSection: some View {
        DetailSection(title: String(localized: "Medical Information")) {
            VStack(alignment: .leading, spacing: 16) {
                if let allergies = child.allergies, !allergies.isEmpty {
                    MedicalInfoCard(
                        title: String(localized: "Allergies"),
                        content: allergies,
                        icon: "allergens",
                        severity: .high
                    )
                }

                if let medicalNotes = child.medicalNotes, !medicalNotes.isEmpty {
                    MedicalInfoCard(
                        title: String(localized: "Medical Notes"),
                        content: medicalNotes,
                        icon: "cross.case",
                        severity: .medium
                    )
                }

                if let dietary = child.dietaryRequirements, !dietary.isEmpty {
                    MedicalInfoCard(
                        title: String(localized: "Dietary Requirements"),
                        content: dietary,
                        icon: "fork.knife",
                        severity: .low
                    )
                }
            }
        }
    }

    // MARK: - Notes Section

    @ViewBuilder
    private var notesSection: some View {
        if let notes = child.notes, !notes.isEmpty {
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
    }

    // MARK: - Metadata Section

    private var metadataSection: some View {
        HStack(spacing: 24) {
            if let createdAt = child.createdAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Created:"))
                        .foregroundColor(.secondary)
                    Text(createdAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            if let updatedAt = child.updatedAt {
                HStack(spacing: 4) {
                    Text(String(localized: "Updated:"))
                        .foregroundColor(.secondary)
                    Text(updatedAt.displayDateTime)
                        .foregroundColor(.secondary)
                }
                .font(.caption)
            }

            Spacer()

            Text(String(localized: "ID: \(child.id)"))
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
                onEdit?(child)
            }) {
                Label(String(localized: "Edit"), systemImage: "pencil")
            }
            .keyboardShortcut("e", modifiers: [.command])
            .help(String(localized: "Edit child information (Cmd+E)"))
        }

        ToolbarItem(placement: .destructiveAction) {
            Button(role: .destructive, action: {
                showDeleteConfirmation = true
            }) {
                Label(String(localized: "Delete"), systemImage: "trash")
            }
            .help(String(localized: "Delete child"))
        }
    }
}

// MARK: - Supporting Views

/// A section container with a title and content
struct DetailSection<Content: View>: View {
    let title: String
    let content: Content

    init(title: String, @ViewBuilder content: () -> Content) {
        self.title = title
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(title)
                .font(.headline)
                .foregroundColor(.primary)

            content
        }
    }
}

/// A labeled field with an icon
struct DetailField: View {
    let label: String
    let value: String
    var icon: String? = nil
    var valueColor: Color = .primary

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(label)
                .font(.caption)
                .foregroundColor(.secondary)

            HStack(spacing: 6) {
                if let icon = icon {
                    Image(systemName: icon)
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }

                Text(value)
                    .font(.body)
                    .fontWeight(.medium)
                    .foregroundColor(valueColor)
            }
        }
        .padding(.vertical, 4)
    }
}

/// A card displaying guardian information with contact actions
struct GuardianCard: View {
    let title: String
    let name: String
    let email: String?
    let phone: String?
    let isPrimary: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                HStack(spacing: 8) {
                    Image(systemName: isPrimary ? "person.crop.circle.fill" : "person.crop.circle")
                        .font(.title2)
                        .foregroundColor(isPrimary ? .accentColor : .secondary)

                    VStack(alignment: .leading, spacing: 2) {
                        Text(title)
                            .font(.caption)
                            .foregroundColor(.secondary)

                        Text(name)
                            .font(.headline)
                    }
                }

                Spacer()

                // Contact actions
                HStack(spacing: 8) {
                    if let phone = phone, !phone.isEmpty {
                        Button(action: {
                            if let url = URL(string: "tel:\(phone.filter { $0.isNumber })") {
                                NSWorkspace.shared.open(url)
                            }
                        }) {
                            Image(systemName: "phone.circle.fill")
                                .font(.title2)
                                .foregroundColor(.green)
                        }
                        .buttonStyle(.plain)
                        .help(String(localized: "Call \(phone)"))
                    }

                    if let email = email, !email.isEmpty {
                        Button(action: {
                            if let url = URL(string: "mailto:\(email)") {
                                NSWorkspace.shared.open(url)
                            }
                        }) {
                            Image(systemName: "envelope.circle.fill")
                                .font(.title2)
                                .foregroundColor(.accentColor)
                        }
                        .buttonStyle(.plain)
                        .help(String(localized: "Email \(email)"))
                    }
                }
            }

            // Contact details
            HStack(spacing: 24) {
                if let email = email, !email.isEmpty {
                    Label(email, systemImage: "envelope")
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }

                if let phone = phone, !phone.isEmpty {
                    Label(phone.formattedPhoneNumber, systemImage: "phone")
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }
            }
        }
        .padding()
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

/// A card displaying medical information with severity indication
struct MedicalInfoCard: View {
    let title: String
    let content: String
    let icon: String
    let severity: MedicalSeverity

    enum MedicalSeverity {
        case high
        case medium
        case low

        var color: Color {
            switch self {
            case .high: return .red
            case .medium: return .orange
            case .low: return .yellow
            }
        }

        var backgroundColor: Color {
            switch self {
            case .high: return Color.red.opacity(0.1)
            case .medium: return Color.orange.opacity(0.1)
            case .low: return Color.yellow.opacity(0.1)
            }
        }
    }

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: icon)
                .font(.title2)
                .foregroundColor(severity.color)
                .frame(width: 32)

            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.subheadline)
                    .fontWeight(.semibold)
                    .foregroundColor(severity.color)

                Text(content)
                    .font(.body)
                    .foregroundColor(.primary)
            }

            Spacer()
        }
        .padding()
        .background(severity.backgroundColor)
        .cornerRadius(8)
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .stroke(severity.color.opacity(0.3), lineWidth: 1)
        )
    }
}

// MARK: - Preview

#Preview("Child Detail View - Active") {
    NavigationStack {
        ChildDetailView(
            child: .preview,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 800)
}

#Preview("Child Detail View - Infant with Medical") {
    NavigationStack {
        ChildDetailView(
            child: .previewInfant,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 800)
}

#Preview("Child Detail View - Waitlist") {
    NavigationStack {
        ChildDetailView(
            child: .previewWaitlist,
            onEdit: { _ in },
            onDelete: { _ in }
        )
    }
    .frame(width: 700, height: 800)
}

#Preview("Detail Section") {
    DetailSection(title: "Sample Section") {
        Text("Section content goes here")
    }
    .padding()
}

#Preview("Detail Field") {
    VStack(alignment: .leading, spacing: 16) {
        DetailField(label: "Name", value: "Emma Tremblay", icon: "person")
        DetailField(label: "Status", value: "Active", icon: "checkmark.circle", valueColor: .green)
    }
    .padding()
}

#Preview("Guardian Card") {
    VStack(spacing: 16) {
        GuardianCard(
            title: "Primary Guardian",
            name: "Marie Tremblay",
            email: "marie@email.com",
            phone: "(514) 555-1234",
            isPrimary: true
        )

        GuardianCard(
            title: "Secondary Guardian",
            name: "Jean Tremblay",
            email: nil,
            phone: nil,
            isPrimary: false
        )
    }
    .padding()
    .frame(width: 500)
}

#Preview("Medical Info Card") {
    VStack(spacing: 16) {
        MedicalInfoCard(
            title: "Allergies",
            content: "Peanuts - severe allergic reaction, requires EpiPen",
            icon: "allergens",
            severity: .high
        )

        MedicalInfoCard(
            title: "Medical Notes",
            content: "Asthma - uses inhaler as needed",
            icon: "cross.case",
            severity: .medium
        )

        MedicalInfoCard(
            title: "Dietary Requirements",
            content: "Vegetarian",
            icon: "fork.knife",
            severity: .low
        )
    }
    .padding()
    .frame(width: 500)
}
