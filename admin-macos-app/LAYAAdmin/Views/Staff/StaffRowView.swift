//
//  StaffRowView.swift
//  LAYAAdmin
//
//  Row view for displaying a staff member in the staff list.
//  Shows staff name, role, status, classroom, and certification info.
//

import SwiftUI

// MARK: - Staff Row View

/// A row view displaying a staff member's information in the list.
///
/// Features:
/// - Avatar with initials or profile photo
/// - Staff name with role
/// - Employment status badge
/// - Classroom assignment
/// - Certification alert indicator
/// - Selection checkbox for bulk operations
struct StaffRowView: View {

    // MARK: - Properties

    /// The staff member to display
    let staff: Staff

    /// Whether this staff member is selected
    let isSelected: Bool

    /// Callback for toggling selection
    let onToggleSelect: (() -> Void)?

    // MARK: - Initialization

    /// Creates a new StaffRowView
    /// - Parameters:
    ///   - staff: The staff member to display
    ///   - isSelected: Whether this staff member is selected (default: false)
    ///   - onToggleSelect: Optional callback for selection toggle
    init(
        staff: Staff,
        isSelected: Bool = false,
        onToggleSelect: (() -> Void)? = nil
    ) {
        self.staff = staff
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
            staffAvatar

            // Main content
            VStack(alignment: .leading, spacing: 4) {
                // Top row: Name and status
                HStack(spacing: 8) {
                    Text(staff.fullName)
                        .font(.headline)
                        .lineLimit(1)

                    StaffStatusBadge(status: staff.status)

                    if staff.hasCertificationConcerns {
                        CertificationAlertBadge()
                    }
                }

                // Bottom row: Details
                HStack(spacing: 16) {
                    // Role
                    Label(staff.roleDisplayName, systemImage: "person.badge.shield.checkmark")
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    // Classroom
                    if let classroom = staff.assignedClassroomName {
                        Label(classroom, systemImage: "building")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                    }

                    // Years employed
                    if staff.yearsEmployed > 0 {
                        Label(
                            String(localized: "\(staff.yearsEmployed) years"),
                            systemImage: "calendar.badge.clock"
                        )
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                    }
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
                Text(staff.initials)
                    .font(.system(size: 16, weight: .semibold))
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
}

// MARK: - Staff Status Badge

/// A badge displaying the employment status with appropriate styling.
struct StaffStatusBadge: View {

    let status: StaffStatus

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
        case .onLeave:
            return Color.orange.opacity(0.15)
        case .terminated:
            return Color.gray.opacity(0.15)
        case .suspended:
            return Color.red.opacity(0.15)
        }
    }

    private var statusTextColor: Color {
        switch status {
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
}

// MARK: - Certification Alert Badge

/// A badge indicating the staff member has certification concerns.
struct CertificationAlertBadge: View {

    var body: some View {
        HStack(spacing: 2) {
            Image(systemName: "exclamationmark.triangle.fill")
                .font(.caption2)

            Text(String(localized: "Cert"))
                .font(.caption2)
                .fontWeight(.medium)
        }
        .padding(.horizontal, 4)
        .padding(.vertical, 2)
        .background(Color.orange.opacity(0.15))
        .foregroundColor(.orange)
        .cornerRadius(4)
    }
}

// MARK: - Staff Role Badge

/// A badge displaying the staff role with appropriate styling.
struct StaffRoleBadge: View {

    let role: StaffRole

    var body: some View {
        Text(role.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(roleBackgroundColor)
            .foregroundColor(roleTextColor)
            .cornerRadius(4)
    }

    private var roleBackgroundColor: Color {
        switch role {
        case .director, .assistantDirector:
            return Color.purple.opacity(0.15)
        case .leadEducator:
            return Color.blue.opacity(0.15)
        case .educator:
            return Color.accentColor.opacity(0.15)
        case .substitute:
            return Color.cyan.opacity(0.15)
        case .cook:
            return Color.orange.opacity(0.15)
        case .administrative:
            return Color.indigo.opacity(0.15)
        case .maintenance:
            return Color.brown.opacity(0.15)
        }
    }

    private var roleTextColor: Color {
        switch role {
        case .director, .assistantDirector:
            return .purple
        case .leadEducator:
            return .blue
        case .educator:
            return .accentColor
        case .substitute:
            return .cyan
        case .cook:
            return .orange
        case .administrative:
            return .indigo
        case .maintenance:
            return .brown
        }
    }
}

// MARK: - Compact Staff Row View

/// A more compact version of the staff row for use in tight spaces.
struct CompactStaffRowView: View {

    let staff: Staff

    var body: some View {
        HStack(spacing: 10) {
            // Avatar
            Circle()
                .fill(Color.accentColor.opacity(0.15))
                .frame(width: 32, height: 32)
                .overlay {
                    Text(staff.initials)
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundColor(.accentColor)
                }

            // Name and role
            VStack(alignment: .leading, spacing: 2) {
                Text(staff.fullName)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Text(staff.roleDisplayName)
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
}

// MARK: - Preview

#Preview("Staff Row View") {
    VStack(spacing: 0) {
        StaffRowView(staff: .preview, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        StaffRowView(staff: .previewSubstitute, isSelected: true, onToggleSelect: {})
            .padding(.horizontal)

        Divider()

        StaffRowView(staff: .previewOnLeave, isSelected: false, onToggleSelect: {})
            .padding(.horizontal)
    }
    .frame(width: 600)
}

#Preview("Staff Row View - Without Selection") {
    VStack(spacing: 0) {
        StaffRowView(staff: .preview)
            .padding(.horizontal)

        Divider()

        StaffRowView(staff: .previewSubstitute)
            .padding(.horizontal)
    }
    .frame(width: 600)
}

#Preview("Staff Status Badge") {
    HStack(spacing: 12) {
        StaffStatusBadge(status: .active)
        StaffStatusBadge(status: .onLeave)
        StaffStatusBadge(status: .terminated)
        StaffStatusBadge(status: .suspended)
    }
    .padding()
}

#Preview("Staff Role Badge") {
    VStack(spacing: 8) {
        HStack(spacing: 8) {
            StaffRoleBadge(role: .director)
            StaffRoleBadge(role: .assistantDirector)
            StaffRoleBadge(role: .leadEducator)
            StaffRoleBadge(role: .educator)
        }
        HStack(spacing: 8) {
            StaffRoleBadge(role: .substitute)
            StaffRoleBadge(role: .cook)
            StaffRoleBadge(role: .administrative)
            StaffRoleBadge(role: .maintenance)
        }
    }
    .padding()
}

#Preview("Certification Alert Badge") {
    CertificationAlertBadge()
        .padding()
}

#Preview("Compact Staff Row View") {
    VStack(spacing: 8) {
        CompactStaffRowView(staff: .preview)
        CompactStaffRowView(staff: .previewSubstitute)
        CompactStaffRowView(staff: .previewOnLeave)
    }
    .frame(width: 300)
    .padding()
}
