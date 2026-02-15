//
//  Extensions.swift
//  LAYAAdmin
//
//  Common Swift extensions used throughout the application
//

import Foundation
import SwiftUI

// MARK: - String Extensions

extension String {

    /// Returns a localized version of the string
    var localized: String {
        return NSLocalizedString(self, comment: "")
    }

    /// Returns a localized string with arguments
    func localized(with arguments: CVarArg...) -> String {
        return String(format: self.localized, arguments: arguments)
    }

    /// Checks if the string is a valid email address
    var isValidEmail: Bool {
        let emailRegex = "[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,64}"
        let emailPredicate = NSPredicate(format: "SELF MATCHES %@", emailRegex)
        return emailPredicate.evaluate(with: self)
    }

    /// Checks if the string is empty or contains only whitespace
    var isBlank: Bool {
        return self.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    /// Returns nil if the string is blank, otherwise returns the trimmed string
    var nilIfBlank: String? {
        let trimmed = self.trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.isEmpty ? nil : trimmed
    }

    /// Truncates the string to a maximum length with ellipsis
    func truncated(to maxLength: Int, trailing: String = "...") -> String {
        if self.count <= maxLength {
            return self
        }
        return String(self.prefix(maxLength - trailing.count)) + trailing
    }

    /// Formats a phone number for display
    var formattedPhoneNumber: String {
        let digits = self.filter { $0.isNumber }
        guard digits.count == 10 else { return self }

        let areaCode = String(digits.prefix(3))
        let prefix = String(digits.dropFirst(3).prefix(3))
        let suffix = String(digits.dropFirst(6))

        return "(\(areaCode)) \(prefix)-\(suffix)"
    }
}

// MARK: - Date Extensions

extension Date {

    /// Returns a formatted string for display purposes
    func formatted(as format: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = format
        formatter.locale = Locale.current
        return formatter.string(from: self)
    }

    /// Returns the date formatted for display (e.g., "Jan 15, 2024")
    var displayDate: String {
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        formatter.timeStyle = .none
        formatter.locale = Locale.current
        return formatter.string(from: self)
    }

    /// Returns the date and time formatted for display
    var displayDateTime: String {
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        formatter.locale = Locale.current
        return formatter.string(from: self)
    }

    /// Returns the time formatted for display (e.g., "2:30 PM")
    var displayTime: String {
        let formatter = DateFormatter()
        formatter.dateStyle = .none
        formatter.timeStyle = .short
        formatter.locale = Locale.current
        return formatter.string(from: self)
    }

    /// Returns the ISO 8601 string representation
    var iso8601String: String {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.string(from: self)
    }

    /// Creates a Date from an ISO 8601 string
    static func from(iso8601String: String) -> Date? {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: iso8601String)
    }

    /// Returns relative time description (e.g., "2 hours ago", "Yesterday")
    var relativeTimeString: String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .full
        formatter.locale = Locale.current
        return formatter.localizedString(for: self, relativeTo: Date())
    }

    /// Returns true if the date is today
    var isToday: Bool {
        return Calendar.current.isDateInToday(self)
    }

    /// Returns true if the date is yesterday
    var isYesterday: Bool {
        return Calendar.current.isDateInYesterday(self)
    }

    /// Returns the start of the day
    var startOfDay: Date {
        return Calendar.current.startOfDay(for: self)
    }

    /// Returns the end of the day
    var endOfDay: Date {
        var components = DateComponents()
        components.day = 1
        components.second = -1
        return Calendar.current.date(byAdding: components, to: startOfDay) ?? self
    }

    /// Returns the year component
    var year: Int {
        return Calendar.current.component(.year, from: self)
    }

    /// Returns the age in years from the date to now
    var ageInYears: Int {
        return Calendar.current.dateComponents([.year], from: self, to: Date()).year ?? 0
    }
}

// MARK: - Optional Extensions

extension Optional where Wrapped == String {

    /// Returns true if the optional string is nil or empty
    var isNilOrEmpty: Bool {
        return self?.isEmpty ?? true
    }

    /// Returns the string or a default value if nil or empty
    func orDefault(_ defaultValue: String) -> String {
        guard let value = self, !value.isEmpty else {
            return defaultValue
        }
        return value
    }
}

// MARK: - Double Extensions

extension Double {

    /// Formats the number as currency (CAD)
    var asCurrency: String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.locale = Locale(identifier: "en_CA")
        return formatter.string(from: NSNumber(value: self)) ?? "$0.00"
    }

    /// Formats the number as a percentage
    var asPercentage: String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .percent
        formatter.maximumFractionDigits = 1
        return formatter.string(from: NSNumber(value: self)) ?? "0%"
    }

    /// Formats the number with a specified number of decimal places
    func formatted(decimals: Int) -> String {
        return String(format: "%.\(decimals)f", self)
    }
}

// MARK: - Int Extensions

extension Int {

    /// Formats the number with thousands separators
    var formatted: String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .decimal
        return formatter.string(from: NSNumber(value: self)) ?? "\(self)"
    }
}

// MARK: - Array Extensions

extension Array {

    /// Safely access an element at the given index
    subscript(safe index: Index) -> Element? {
        return indices.contains(index) ? self[index] : nil
    }
}

extension Array where Element: Identifiable {

    /// Finds the index of an element by its ID
    func index(of element: Element) -> Int? {
        return firstIndex(where: { $0.id == element.id })
    }
}

// MARK: - View Extensions

extension View {

    /// Applies a condition-based modifier
    @ViewBuilder
    func `if`<Content: View>(_ condition: Bool, transform: (Self) -> Content) -> some View {
        if condition {
            transform(self)
        } else {
            self
        }
    }

    /// Applies a condition-based modifier with else clause
    @ViewBuilder
    func `if`<TrueContent: View, FalseContent: View>(
        _ condition: Bool,
        then trueTransform: (Self) -> TrueContent,
        else falseTransform: (Self) -> FalseContent
    ) -> some View {
        if condition {
            trueTransform(self)
        } else {
            falseTransform(self)
        }
    }

    /// Hides the view based on a condition
    @ViewBuilder
    func hidden(_ shouldHide: Bool) -> some View {
        if shouldHide {
            self.hidden()
        } else {
            self
        }
    }

    /// Applies a card-like background style
    func cardStyle() -> some View {
        self
            .padding()
            .background(Color(NSColor.controlBackgroundColor))
            .cornerRadius(8)
            .shadow(color: Color.black.opacity(0.1), radius: 2, x: 0, y: 1)
    }

    /// Applies a standard section header style
    func sectionHeaderStyle() -> some View {
        self
            .font(.headline)
            .foregroundColor(.secondary)
    }
}

// MARK: - Color Extensions

extension Color {

    /// Creates a color from a hex string
    init(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0
        Scanner(string: hex).scanHexInt64(&int)

        let a, r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (a, r, g, b) = (255, (int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (a, r, g, b) = (255, int >> 16, int >> 8 & 0xFF, int & 0xFF)
        case 8: // ARGB (32-bit)
            (a, r, g, b) = (int >> 24, int >> 16 & 0xFF, int >> 8 & 0xFF, int & 0xFF)
        default:
            (a, r, g, b) = (255, 0, 0, 0)
        }

        self.init(
            .sRGB,
            red: Double(r) / 255,
            green: Double(g) / 255,
            blue: Double(b) / 255,
            opacity: Double(a) / 255
        )
    }

    // MARK: - App Colors

    /// Primary brand color
    static let layaPrimary = Color("AccentColor")

    /// Success color (green)
    static let success = Color.green

    /// Warning color (orange)
    static let warning = Color.orange

    /// Error/danger color (red)
    static let danger = Color.red

    /// Info color (blue)
    static let info = Color.blue
}

// MARK: - URL Extensions

extension URL {

    /// Appends query parameters to the URL
    func appendingQueryParameters(_ parameters: [String: String]) -> URL {
        var components = URLComponents(url: self, resolvingAgainstBaseURL: true)
        var queryItems = components?.queryItems ?? []

        for (key, value) in parameters {
            queryItems.append(URLQueryItem(name: key, value: value))
        }

        components?.queryItems = queryItems
        return components?.url ?? self
    }
}

// MARK: - Data Extensions

extension Data {

    /// Returns a pretty-printed JSON string for debugging
    var prettyPrintedJSONString: String? {
        guard let object = try? JSONSerialization.jsonObject(with: self, options: []),
              let data = try? JSONSerialization.data(withJSONObject: object, options: [.prettyPrinted]),
              let prettyPrintedString = String(data: data, encoding: .utf8) else {
            return nil
        }
        return prettyPrintedString
    }
}

// MARK: - Bundle Extensions

extension Bundle {

    /// Returns the app version string
    var appVersion: String {
        return infoDictionary?["CFBundleShortVersionString"] as? String ?? "Unknown"
    }

    /// Returns the build number
    var buildNumber: String {
        return infoDictionary?["CFBundleVersion"] as? String ?? "Unknown"
    }

    /// Returns the full version string (e.g., "1.0.0 (123)")
    var fullVersionString: String {
        return "\(appVersion) (\(buildNumber))"
    }
}

// MARK: - Encodable Extensions

extension Encodable {

    /// Converts the object to a dictionary
    var dictionary: [String: Any]? {
        guard let data = try? JSONEncoder().encode(self) else { return nil }
        return (try? JSONSerialization.jsonObject(with: data, options: .allowFragments)).flatMap { $0 as? [String: Any] }
    }

    /// Converts the object to JSON data
    func toJSONData() -> Data? {
        return try? JSONEncoder().encode(self)
    }
}
