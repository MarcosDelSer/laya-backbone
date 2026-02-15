//
//  Schedule.swift
//  LAYAAdmin
//
//  Schedule domain models for staff scheduling functionality.
//  Includes shift types, weekly schedules, and schedule management.
//

import Foundation

// MARK: - Shift Type

/// Types of shifts available for scheduling
enum ShiftType: String, Codable, CaseIterable, Identifiable {
    case morning = "morning"
    case afternoon = "afternoon"
    case fullDay = "full_day"
    case split = "split"
    case custom = "custom"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .morning:
            return String(localized: "Morning")
        case .afternoon:
            return String(localized: "Afternoon")
        case .fullDay:
            return String(localized: "Full Day")
        case .split:
            return String(localized: "Split Shift")
        case .custom:
            return String(localized: "Custom")
        }
    }

    var icon: String {
        switch self {
        case .morning:
            return "sunrise"
        case .afternoon:
            return "sun.max"
        case .fullDay:
            return "sun.and.horizon"
        case .split:
            return "arrow.left.arrow.right"
        case .custom:
            return "clock"
        }
    }

    /// Default start time for this shift type
    var defaultStartHour: Int {
        switch self {
        case .morning, .fullDay:
            return 7
        case .afternoon:
            return 12
        case .split:
            return 7
        case .custom:
            return 9
        }
    }

    /// Default end time for this shift type
    var defaultEndHour: Int {
        switch self {
        case .morning:
            return 12
        case .afternoon, .fullDay:
            return 18
        case .split:
            return 18
        case .custom:
            return 17
        }
    }
}

// MARK: - Schedule Status

/// Status of a schedule entry
enum ScheduleStatus: String, Codable, CaseIterable {
    case scheduled = "scheduled"
    case confirmed = "confirmed"
    case inProgress = "in_progress"
    case completed = "completed"
    case cancelled = "cancelled"
    case noShow = "no_show"

    var displayName: String {
        switch self {
        case .scheduled:
            return String(localized: "Scheduled")
        case .confirmed:
            return String(localized: "Confirmed")
        case .inProgress:
            return String(localized: "In Progress")
        case .completed:
            return String(localized: "Completed")
        case .cancelled:
            return String(localized: "Cancelled")
        case .noShow:
            return String(localized: "No Show")
        }
    }

    var color: String {
        switch self {
        case .scheduled:
            return "blue"
        case .confirmed:
            return "green"
        case .inProgress:
            return "orange"
        case .completed:
            return "gray"
        case .cancelled:
            return "red"
        case .noShow:
            return "purple"
        }
    }
}

// MARK: - Shift

/// Represents a single shift in the schedule
struct Shift: Identifiable, Codable, Equatable {

    // MARK: - Properties

    /// Unique identifier for the shift
    let id: String

    /// Staff member ID assigned to this shift
    let staffId: String

    /// Staff member name for display
    let staffName: String?

    /// Date of the shift
    let date: Date

    /// Start time of the shift
    let startTime: Date

    /// End time of the shift
    let endTime: Date

    /// Type of shift
    let shiftType: ShiftType

    /// Status of the shift
    var status: ScheduleStatus

    /// Assigned classroom ID (optional)
    let classroomId: String?

    /// Assigned classroom name for display
    let classroomName: String?

    /// Whether this is a substitute coverage entry
    let isSubstituteCoverage: Bool

    /// ID of the staff member being covered (for substitutes)
    let coveringForStaffId: String?

    /// Notes about this shift
    let notes: String?

    /// Break duration in minutes
    let breakDurationMinutes: Int?

    /// Date when the shift was created
    let createdAt: Date?

    /// Date when the shift was last updated
    let updatedAt: Date?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case staffId = "staff_id"
        case staffName = "staff_name"
        case date
        case startTime = "start_time"
        case endTime = "end_time"
        case shiftType = "shift_type"
        case status
        case classroomId = "classroom_id"
        case classroomName = "classroom_name"
        case isSubstituteCoverage = "is_substitute_coverage"
        case coveringForStaffId = "covering_for_staff_id"
        case notes
        case breakDurationMinutes = "break_duration_minutes"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }

    // MARK: - Computed Properties

    /// Duration of the shift in hours (excluding breaks)
    var durationHours: Double {
        let totalMinutes = endTime.timeIntervalSince(startTime) / 60.0
        let breakMinutes = Double(breakDurationMinutes ?? 0)
        return (totalMinutes - breakMinutes) / 60.0
    }

    /// Formatted time range string (e.g., "7:00 AM - 3:00 PM")
    var timeRangeString: String {
        return "\(startTime.displayTime) - \(endTime.displayTime)"
    }

    /// Whether the shift is in the past
    var isPast: Bool {
        return endTime < Date()
    }

    /// Whether the shift is currently active
    var isActive: Bool {
        let now = Date()
        return startTime <= now && endTime >= now
    }

    /// Whether the shift is in the future
    var isFuture: Bool {
        return startTime > Date()
    }
}

// MARK: - Shift Extensions

extension Shift {

    /// Creates a sample shift for previews and testing
    static var preview: Shift {
        let today = Calendar.current.startOfDay(for: Date())
        return Shift(
            id: "shift-preview-1",
            staffId: "staff-1",
            staffName: "Isabelle Bouchard",
            date: today,
            startTime: Calendar.current.date(bySettingHour: 7, minute: 0, second: 0, of: today) ?? today,
            endTime: Calendar.current.date(bySettingHour: 15, minute: 0, second: 0, of: today) ?? today,
            shiftType: .fullDay,
            status: .confirmed,
            classroomId: "classroom-1",
            classroomName: "Sunflowers",
            isSubstituteCoverage: false,
            coveringForStaffId: nil,
            notes: nil,
            breakDurationMinutes: 30,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample substitute shift for previews
    static var previewSubstitute: Shift {
        let today = Calendar.current.startOfDay(for: Date())
        return Shift(
            id: "shift-preview-2",
            staffId: "staff-sub-1",
            staffName: "Marc Lefebvre",
            date: today,
            startTime: Calendar.current.date(bySettingHour: 7, minute: 0, second: 0, of: today) ?? today,
            endTime: Calendar.current.date(bySettingHour: 12, minute: 0, second: 0, of: today) ?? today,
            shiftType: .morning,
            status: .scheduled,
            classroomId: "classroom-2",
            classroomName: "Little Stars",
            isSubstituteCoverage: true,
            coveringForStaffId: "staff-3",
            notes: "Covering for Julie on maternity leave",
            breakDurationMinutes: 15,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    /// Creates a sample afternoon shift
    static var previewAfternoon: Shift {
        let today = Calendar.current.startOfDay(for: Date())
        return Shift(
            id: "shift-preview-3",
            staffId: "staff-2",
            staffName: "Sophie Tremblay",
            date: today,
            startTime: Calendar.current.date(bySettingHour: 12, minute: 0, second: 0, of: today) ?? today,
            endTime: Calendar.current.date(bySettingHour: 18, minute: 0, second: 0, of: today) ?? today,
            shiftType: .afternoon,
            status: .confirmed,
            classroomId: "classroom-1",
            classroomName: "Sunflowers",
            isSubstituteCoverage: false,
            coveringForStaffId: nil,
            notes: nil,
            breakDurationMinutes: 30,
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - Daily Schedule

/// Represents all shifts for a single day
struct DailySchedule: Identifiable, Equatable {

    var id: Date { date }

    /// The date for this schedule
    let date: Date

    /// All shifts for this day
    var shifts: [Shift]

    /// Total scheduled hours for the day
    var totalScheduledHours: Double {
        shifts.reduce(0) { $0 + $1.durationHours }
    }

    /// Number of staff scheduled
    var staffCount: Int {
        Set(shifts.map { $0.staffId }).count
    }

    /// Unique classrooms with staff scheduled
    var classroomsWithCoverage: Set<String> {
        Set(shifts.compactMap { $0.classroomId })
    }

    /// Whether there are any substitute shifts
    var hasSubstitutes: Bool {
        shifts.contains { $0.isSubstituteCoverage }
    }
}

// MARK: - Weekly Schedule

/// Represents a week's worth of schedules
struct WeeklySchedule: Identifiable, Equatable {

    var id: Date { weekStartDate }

    /// Start date of the week (typically Monday)
    let weekStartDate: Date

    /// End date of the week (typically Sunday)
    let weekEndDate: Date

    /// Daily schedules for each day of the week
    var dailySchedules: [DailySchedule]

    /// Total hours scheduled for the week
    var totalWeeklyHours: Double {
        dailySchedules.reduce(0) { $0 + $1.totalScheduledHours }
    }

    /// Gets the daily schedule for a specific date
    func schedule(for date: Date) -> DailySchedule? {
        dailySchedules.first { Calendar.current.isDate($0.date, inSameDayAs: date) }
    }
}

// MARK: - Schedule Summary

/// Summary statistics for schedule display
struct ScheduleSummary: Codable, Equatable {

    /// Total scheduled hours
    let totalHours: Double

    /// Total staff members scheduled
    let staffCount: Int

    /// Number of shifts
    let shiftCount: Int

    /// Number of substitute shifts
    let substituteShiftCount: Int

    /// Coverage percentage by classroom
    let coverageByClassroom: [String: Double]?

    /// Any scheduling conflicts or warnings
    let warnings: [String]?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case totalHours = "total_hours"
        case staffCount = "staff_count"
        case shiftCount = "shift_count"
        case substituteShiftCount = "substitute_shift_count"
        case coverageByClassroom = "coverage_by_classroom"
        case warnings
    }
}

// MARK: - Create/Update Shift Request

/// Request payload for creating or updating a shift
struct ShiftRequest: Codable {

    /// Staff member ID
    let staffId: String

    /// Date of the shift
    let date: Date

    /// Start time of the shift
    let startTime: Date

    /// End time of the shift
    let endTime: Date

    /// Type of shift
    let shiftType: ShiftType

    /// Status of the shift
    let status: ScheduleStatus

    /// Assigned classroom ID (optional)
    let classroomId: String?

    /// Whether this is a substitute coverage entry
    let isSubstituteCoverage: Bool

    /// ID of the staff member being covered (for substitutes)
    let coveringForStaffId: String?

    /// Notes about this shift
    let notes: String?

    /// Break duration in minutes
    let breakDurationMinutes: Int?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case staffId = "staff_id"
        case date
        case startTime = "start_time"
        case endTime = "end_time"
        case shiftType = "shift_type"
        case status
        case classroomId = "classroom_id"
        case isSubstituteCoverage = "is_substitute_coverage"
        case coveringForStaffId = "covering_for_staff_id"
        case notes
        case breakDurationMinutes = "break_duration_minutes"
    }
}

// MARK: - Classroom

/// Represents a classroom for scheduling purposes
struct Classroom: Identifiable, Codable, Equatable {

    /// Unique identifier
    let id: String

    /// Classroom name
    let name: String

    /// Maximum capacity
    let capacity: Int?

    /// Age group served
    let ageGroup: String?

    /// Required staff-to-child ratio
    let requiredRatio: Double?

    // MARK: - Coding Keys

    enum CodingKeys: String, CodingKey {
        case id
        case name
        case capacity
        case ageGroup = "age_group"
        case requiredRatio = "required_ratio"
    }
}

// MARK: - Classroom Extensions

extension Classroom {

    static var preview: Classroom {
        Classroom(
            id: "classroom-1",
            name: "Sunflowers",
            capacity: 20,
            ageGroup: "3-4 years",
            requiredRatio: 0.125
        )
    }

    static var previewList: [Classroom] {
        [
            Classroom(id: "classroom-1", name: "Sunflowers", capacity: 20, ageGroup: "3-4 years", requiredRatio: 0.125),
            Classroom(id: "classroom-2", name: "Little Stars", capacity: 16, ageGroup: "2-3 years", requiredRatio: 0.167),
            Classroom(id: "classroom-3", name: "Butterflies", capacity: 10, ageGroup: "1-2 years", requiredRatio: 0.2),
            Classroom(id: "classroom-4", name: "Teddy Bears", capacity: 8, ageGroup: "0-1 years", requiredRatio: 0.2)
        ]
    }
}

// MARK: - Schedule View Mode

/// View modes for the schedule calendar
enum ScheduleViewMode: String, CaseIterable, Identifiable {
    case day = "day"
    case week = "week"
    case month = "month"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .day:
            return String(localized: "Day")
        case .week:
            return String(localized: "Week")
        case .month:
            return String(localized: "Month")
        }
    }

    var icon: String {
        switch self {
        case .day:
            return "calendar.day.timeline.left"
        case .week:
            return "calendar"
        case .month:
            return "calendar.badge.clock"
        }
    }
}

// MARK: - Schedule Filter

/// Filter options for the schedule view
struct ScheduleFilter: Equatable {

    /// Filter by specific staff members
    var staffIds: Set<String>?

    /// Filter by classrooms
    var classroomIds: Set<String>?

    /// Filter by shift types
    var shiftTypes: Set<ShiftType>?

    /// Filter by status
    var statuses: Set<ScheduleStatus>?

    /// Show only substitute shifts
    var onlySubstitutes: Bool

    /// Default filter with no restrictions
    static var `default`: ScheduleFilter {
        ScheduleFilter(
            staffIds: nil,
            classroomIds: nil,
            shiftTypes: nil,
            statuses: nil,
            onlySubstitutes: false
        )
    }

    /// Whether any filters are active
    var isActive: Bool {
        staffIds != nil ||
        classroomIds != nil ||
        shiftTypes != nil ||
        statuses != nil ||
        onlySubstitutes
    }
}
