//
//  User.swift
//  LAYAAdmin
//
//  User domain models for the LAYA Admin application.
//

import Foundation

// MARK: - User Model

/// Represents a user in the LAYA system.
/// Users can have different roles (director, accountant, admin) with
/// corresponding permissions for different parts of the application.
struct User: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the user
    let id: String

    /// User's email address (used for authentication)
    let email: String

    /// User's first name
    let firstName: String

    /// User's last name
    let lastName: String

    /// User's role in the system
    let role: UserRole

    /// Whether the user account is active
    let isActive: Bool

    /// URL to the user's profile photo (optional)
    let profilePhotoURL: String?

    /// User's phone number (optional)
    let phoneNumber: String?

    /// Date when the user account was created
    let createdAt: Date?

    /// Date when the user account was last updated
    let updatedAt: Date?

    /// Date of last login (optional)
    let lastLoginAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case email
        case firstName = "first_name"
        case lastName = "last_name"
        case role
        case isActive = "is_active"
        case profilePhotoURL = "profile_photo_url"
        case phoneNumber = "phone_number"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case lastLoginAt = "last_login_at"
    }

    // MARK: - Computed Properties

    /// Full name of the user
    var fullName: String {
        return "\(firstName) \(lastName)"
    }

    /// Initials for avatar display
    var initials: String {
        let firstInitial = firstName.first.map { String($0) } ?? ""
        let lastInitial = lastName.first.map { String($0) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }

    /// Display name for the role
    var roleDisplayName: String {
        return role.displayName
    }

    // MARK: - Permissions

    /// Whether the user can manage children
    var canManageChildren: Bool {
        return role.canManageChildren
    }

    /// Whether the user can manage staff
    var canManageStaff: Bool {
        return role.canManageStaff
    }

    /// Whether the user can manage finance
    var canManageFinance: Bool {
        return role.canManageFinance
    }

    /// Whether the user can view analytics
    var canViewAnalytics: Bool {
        return role.canViewAnalytics
    }
}

// MARK: - User Extensions

extension User {

    /// Creates a sample user for previews and testing
    static var preview: User {
        User(
            id: "preview-user-1",
            email: "director@laya.ca",
            firstName: "Marie",
            lastName: "Dupont",
            role: .director,
            isActive: true,
            profilePhotoURL: nil,
            phoneNumber: "(514) 555-1234",
            createdAt: Date(),
            updatedAt: Date(),
            lastLoginAt: Date()
        )
    }

    /// Creates a sample accountant user for previews and testing
    static var previewAccountant: User {
        User(
            id: "preview-user-2",
            email: "accountant@laya.ca",
            firstName: "Jean",
            lastName: "Tremblay",
            role: .accountant,
            isActive: true,
            profilePhotoURL: nil,
            phoneNumber: "(514) 555-5678",
            createdAt: Date(),
            updatedAt: Date(),
            lastLoginAt: Date()
        )
    }
}

// MARK: - User Summary

/// A lightweight representation of a user for list views and references.
struct UserSummary: Identifiable, Codable, Equatable {

    /// Unique identifier for the user
    let id: String

    /// User's full name
    let fullName: String

    /// User's email address
    let email: String

    /// User's role in the system
    let role: UserRole

    /// URL to the user's profile photo (optional)
    let profilePhotoURL: String?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case fullName = "full_name"
        case email
        case role
        case profilePhotoURL = "profile_photo_url"
    }

    /// Initials for avatar display
    var initials: String {
        let components = fullName.split(separator: " ")
        let firstInitial = components.first.map { String($0.first ?? Character(" ")) } ?? ""
        let lastInitial = components.last.map { String($0.first ?? Character(" ")) } ?? ""
        return "\(firstInitial)\(lastInitial)".uppercased()
    }
}

// MARK: - User Preferences

/// User-specific preferences stored locally.
struct UserPreferences: Codable, Equatable {

    /// Selected language (ISO 639-1 code: "en" or "fr")
    var language: String

    /// Whether to receive push notifications
    var notificationsEnabled: Bool

    /// Whether to enable automatic sync
    var autoSyncEnabled: Bool

    /// Sync interval in minutes
    var syncIntervalMinutes: Int

    /// Last viewed section in the app
    var lastViewedSection: String?

    // MARK: - Defaults

    /// Default preferences for new users
    static var defaults: UserPreferences {
        UserPreferences(
            language: Locale.current.language.languageCode?.identifier ?? "en",
            notificationsEnabled: true,
            autoSyncEnabled: true,
            syncIntervalMinutes: 15,
            lastViewedSection: nil
        )
    }
}
