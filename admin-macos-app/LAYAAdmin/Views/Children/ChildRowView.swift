//
//  ChildRowView.swift
//  LAYAAdmin
//
//  Row view for displaying a child in the child list.
//  Shows child name, age, enrollment status, classroom, and guardian info.
//

import SwiftUI

// MARK: - Child Row View

/// A row view displaying a child's information in the list.
///
/// Features:
/// - Avatar with initials or profile photo
/// - Child name with age
/// - Enrollment status badge
/// - Classroom assignment
/// - Primary guardian info
/// - Medical alert indicator
/// - Selection checkbox for bulk operations
struct ChildRowView: View {

    // MARK: - Properties

    /// The child to display
    let child: Child

    /// Whether this child is selected
    let isSelected: Bool

    /// Callback for toggling selection
    let onToggleSelect: (() -> Void)?

    // MARK: - Initialization

    /// Creates a new ChildRowView
    /// - Parameters:
    ///   - child: The child to display
    ///   - isSelected: Whether this child is selected (default: false)
    ///   - onToggleSelect: Optional callback for selection toggle
    init(
        child: Child,
        isSelected: Bool = false,
        onToggleSelect: (() -> Void)? = nil
    ) {
        self.child = child
        self.isSelected = isSelected
        self.onToggleSelect = onToggleSelect
    }

    // MARK: - Body

    var body: some View {
        HStack(spacing: 12) {
            // Selection checkbox (if selection is enabled)
            if onToggleSelect != nil {
                Button(action: {
                    onToggleSelect?()
                }) {
                    Image(systemName: isSelected ? "checkmark.circle.fill" : "circle")
                        .font(.title3)
                        .foregroundColor(isSelected ? .accentColor : .secondary)
                }
                .buttonStyle(.plain)
            }

            // Avatar
            childAvatar

            // Main content
            VStack(alignment: .leading, spacing: 4) {
                // Top row: Name and status
                HStack(spacing: 8) {
                    Text(child.fullName)
                        .font(.headline)
                        .lineLimit(1)

                    EnrollmentStatusBadge(status: child.enrollmentStatus)

                    if child.hasMedicalConcerns {
                        MedicalAlertBadge()
                    }
                }

                // Bottom row: Details
                HStack(spacing: 16) {
                    // Age
                    Label(child.ageDisplayString, systemImage: "calendar")
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    // Classroom
                    if let classroom = child.classroomName {
                        Label(classroom, systemImage: "building")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                    }

                    // Guardian
                    Label(child.primaryGuardianName, systemImage: "person.2")
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                        .lineLimit(1)
                }
            }

            Spacer()

            // Chevron indicator
            Image(systemName: "chevron.right")
                .font(.caption)
                .foregroundColor(.tertiary)
        }
        .padding(.vertical, 8)
        .contentShape(Rectangle())
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
                            .frame(width: 44, height: 44)
                    @unknown default:
                        avatarPlaceholder
                    }
                }
            } else {
                avatarPlaceholder
            }
        }
        .frame(width: 44, height: 44)
        .clipShape(Circle())
    }

    private var avatarPlaceholder: some View {
        Circle()
            .fill(avatarBackgroundColor)
            .overlay {
                Text(child.initials)
                    .font(.system(size: 16, weight: .semibold))
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
            return Color.gray.opacity(0.15)
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
            return .gray
        case .withdrawn:
            return .gray
        }
    }
}

// MARK: - Enrollment Status Badge

/// A badge displaying the enrollment status with appropriate styling.
struct EnrollmentStatusBadge: View {

    let status: EnrollmentStatus

    var body: some View {
        Text(status.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(statusBackgroundColor)
            .foregroundColor(statusTextColor)
            .cornerRadius(4)
    }

    private var statusBackgroundColor: Color {
        switch status {
        case .active:
            return Color.green.opacity(0.15)
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

    private var statusTextColor: Color {
        switch status {
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
}

// MARK: - Medical Alert Badge

/// A badge indicating the child has medical concerns documented.
struct MedicalAlertBadge: View {

    var body: some View {
        HStack(spacing: 2) {
            Image(systemName: "cross.case.fill")
                .font(.caption2)

            Text(String(localized: "Medical"))
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 4)
        .padding(.vertical, 2)
        .background(Color.red.opacity(0.15))
        .foregroundColor(.red)
        .cornerRadius(4)
    }
}

// MARK: - Compact Child Row View

/// A more compact version of the child row for use in tight spaces.
struct CompactChildRowView: View {

    let child: Child

    var body: some View {
        HStack(spacing: 10) {
            // Avatar
            Circle()
                .fill(Color.accentColor.opacity(0.15))
                .frame(width: 32, height: 32)
                .overlay {
                    Text(child.initials)
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundColor(.accentColor)
                }

            // Name and age
            VStack(alignment: .leading, spacing: 2) {
                Text(child.fullName)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Text(child.ageDisplayString)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }

            Spacer()

            // Status indicator
            Circle()
                .fill(statusColor)
                .frame(width: 8, height: 8)
        }
        .contentShape(Rectangle())
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
}

// MARK: - Preview

#Preview("Child Row View") {
    VStack(spacing: 0) {
        ChildRowView(child: .preview, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        ChildRowView(child: .previewInfant, isSelected: true, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        ChildRowView(child: .previewWaitlist, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)
    }
    .frame(width: 600)
}

#Preview("Child Row View - Without Selection") {
    VStack(spacing: 0) {
        ChildRowView(child: .preview)
            .padding(.horizontal)

        Divider()

        ChildRowView(child: .previewInfant)
            .padding(.horizontal)
    }
    .frame(width: 600)
}

#Preview("Enrollment Status Badge") {
    HStack(spacing: 12) {
        EnrollmentStatusBadge(status: .active)
        EnrollmentStatusBadge(status: .pending)
        EnrollmentStatusBadge(status: .waitlist)
        EnrollmentStatusBadge(status: .graduated)
        EnrollmentStatusBadge(status: .withdrawn)
    }
    .padding()
}

#Preview("Medical Alert Badge") {
    MedicalAlertBadge()
        .padding()
}

#Preview("Compact Child Row View") {
    VStack(spacing: 8) {
        CompactChildRowView(child: .preview)
        CompactChildRowView(child: .previewInfant)
        CompactChildRowView(child: .previewWaitlist)
    }
    .frame(width: 300)
    .padding()
}
