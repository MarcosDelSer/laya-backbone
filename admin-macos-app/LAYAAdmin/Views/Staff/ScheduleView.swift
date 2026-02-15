//
//  ScheduleView.swift
//  LAYAAdmin
//
//  Calendar view for managing staff schedules.
//  Displays shifts in day, week, or month view with the ability
//  to create, edit, and manage staff assignments.
//

import SwiftUI

// MARK: - Schedule View

/// A comprehensive calendar view for staff scheduling management.
///
/// Features:
/// - Day, week, and month view modes
/// - DatePicker for navigation
/// - Shift display with staff and classroom info
/// - Create new shifts
/// - Filter by staff, classroom, or shift type
/// - Summary statistics
struct ScheduleView: View {

    // MARK: - Properties

    /// Authentication view model from environment
    @EnvironmentObject var authViewModel: AuthViewModel

    /// Selected date for the calendar
    @State private var selectedDate = Date()

    /// Current view mode (day, week, month)
    @State private var viewMode: ScheduleViewMode = .week

    /// Currently loaded shifts
    @State private var shifts: [Shift] = []

    /// Classrooms for assignment
    @State private var classrooms: [Classroom] = Classroom.previewList

    /// Staff list for assignment
    @State private var staffList: [Staff] = [.preview, .previewSubstitute, .previewOnLeave]

    /// Whether data is loading
    @State private var isLoading = false

    /// Error message if any
    @State private var error: Error?

    /// Whether to show error alert
    @State private var showError = false

    /// Whether to show add shift sheet
    @State private var showAddShift = false

    /// Selected shift for editing
    @State private var selectedShift: Shift?

    /// Current filter settings
    @State private var filter: ScheduleFilter = .default

    /// Whether to show filter sheet
    @State private var showFilterSheet = false

    // MARK: - Body

    var body: some View {
        VStack(spacing: 0) {
            // Header with navigation and controls
            scheduleHeader

            Divider()

            // Main content based on view mode
            Group {
                if isLoading && shifts.isEmpty {
                    loadingView
                } else {
                    scheduleContent
                }
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(NSColor.windowBackgroundColor))
        .navigationTitle(String(localized: "Staff Schedule"))
        .toolbar {
            scheduleToolbar
        }
        .task {
            await loadSchedule()
        }
        .onChange(of: selectedDate) { _, _ in
            Task {
                await loadSchedule()
            }
        }
        .onChange(of: viewMode) { _, _ in
            Task {
                await loadSchedule()
            }
        }
        .alert(
            String(localized: "Error Loading Schedule"),
            isPresented: $showError,
            presenting: error
        ) { _ in
            Button(String(localized: "Retry")) {
                Task {
                    await loadSchedule()
                }
            }
            Button(String(localized: "Dismiss"), role: .cancel) {}
        } message: { error in
            Text(error.localizedDescription)
        }
        .sheet(isPresented: $showAddShift) {
            ShiftFormSheet(
                date: selectedDate,
                staffList: staffList,
                classrooms: classrooms,
                onSave: { newShift in
                    shifts.append(newShift)
                }
            )
        }
        .sheet(item: $selectedShift) { shift in
            ShiftFormSheet(
                shift: shift,
                date: shift.date,
                staffList: staffList,
                classrooms: classrooms,
                onSave: { updatedShift in
                    if let index = shifts.firstIndex(where: { $0.id == updatedShift.id }) {
                        shifts[index] = updatedShift
                    }
                },
                onDelete: { shiftToDelete in
                    shifts.removeAll { $0.id == shiftToDelete.id }
                }
            )
        }
        .sheet(isPresented: $showFilterSheet) {
            ScheduleFilterSheet(
                filter: $filter,
                staffList: staffList,
                classrooms: classrooms
            )
        }
    }

    // MARK: - Schedule Header

    private var scheduleHeader: some View {
        HStack(spacing: 16) {
            // Date navigation
            HStack(spacing: 8) {
                Button(action: navigatePrevious) {
                    Image(systemName: "chevron.left")
                        .font(.title3)
                }
                .buttonStyle(.plain)
                .help(String(localized: "Previous"))

                DatePicker(
                    "",
                    selection: $selectedDate,
                    displayedComponents: [.date]
                )
                .datePickerStyle(.compact)
                .labelsHidden()
                .frame(width: 140)

                Button(action: navigateNext) {
                    Image(systemName: "chevron.right")
                        .font(.title3)
                }
                .buttonStyle(.plain)
                .help(String(localized: "Next"))

                Button(action: goToToday) {
                    Text(String(localized: "Today"))
                        .font(.subheadline)
                }
                .buttonStyle(.bordered)
            }

            Spacer()

            // Summary stats
            scheduleSummaryStats

            Spacer()

            // View mode picker
            Picker(String(localized: "View"), selection: $viewMode) {
                ForEach(ScheduleViewMode.allCases) { mode in
                    Label(mode.displayName, systemImage: mode.icon)
                        .tag(mode)
                }
            }
            .pickerStyle(.segmented)
            .frame(width: 200)

            // Filter button
            Button(action: { showFilterSheet = true }) {
                Label(
                    String(localized: "Filter"),
                    systemImage: filter.isActive ? "line.3.horizontal.decrease.circle.fill" : "line.3.horizontal.decrease.circle"
                )
            }
            .buttonStyle(.bordered)
            .tint(filter.isActive ? .accentColor : nil)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(Color(NSColor.controlBackgroundColor))
    }

    // MARK: - Summary Stats

    private var scheduleSummaryStats: some View {
        HStack(spacing: 20) {
            VStack(alignment: .leading, spacing: 2) {
                Text(String(localized: "Shifts"))
                    .font(.caption)
                    .foregroundColor(.secondary)
                Text("\(filteredShifts.count)")
                    .font(.headline)
            }

            VStack(alignment: .leading, spacing: 2) {
                Text(String(localized: "Staff"))
                    .font(.caption)
                    .foregroundColor(.secondary)
                Text("\(uniqueStaffCount)")
                    .font(.headline)
            }

            VStack(alignment: .leading, spacing: 2) {
                Text(String(localized: "Hours"))
                    .font(.caption)
                    .foregroundColor(.secondary)
                Text(String(format: "%.1f", totalScheduledHours))
                    .font(.headline)
            }

            if substituteShiftCount > 0 {
                VStack(alignment: .leading, spacing: 2) {
                    Text(String(localized: "Subs"))
                        .font(.caption)
                        .foregroundColor(.orange)
                    Text("\(substituteShiftCount)")
                        .font(.headline)
                        .foregroundColor(.orange)
                }
            }
        }
    }

    // MARK: - Schedule Content

    @ViewBuilder
    private var scheduleContent: some View {
        switch viewMode {
        case .day:
            dayView
        case .week:
            weekView
        case .month:
            monthView
        }
    }

    // MARK: - Day View

    private var dayView: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                // Day header
                HStack {
                    Text(selectedDate.formatted(as: "EEEE, MMMM d, yyyy"))
                        .font(.title2)
                        .fontWeight(.semibold)

                    Spacer()

                    if selectedDate.isToday {
                        Text(String(localized: "Today"))
                            .font(.caption)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 4)
                            .background(Color.accentColor)
                            .foregroundColor(.white)
                            .cornerRadius(4)
                    }
                }
                .padding(.horizontal, 16)
                .padding(.top, 16)

                if shiftsForSelectedDate.isEmpty {
                    emptyDayView
                } else {
                    // Timeline view of shifts
                    DayTimelineView(
                        shifts: shiftsForSelectedDate,
                        onShiftTap: { shift in
                            selectedShift = shift
                        }
                    )
                }
            }
        }
    }

    // MARK: - Week View

    private var weekView: some View {
        ScrollView(.horizontal, showsIndicators: true) {
            WeekCalendarView(
                weekStartDate: weekStartDate,
                shifts: filteredShifts,
                classrooms: classrooms,
                onDayTap: { date in
                    selectedDate = date
                    viewMode = .day
                },
                onShiftTap: { shift in
                    selectedShift = shift
                },
                onAddShift: { date in
                    selectedDate = date
                    showAddShift = true
                }
            )
        }
    }

    // MARK: - Month View

    private var monthView: some View {
        MonthCalendarView(
            selectedDate: $selectedDate,
            shifts: filteredShifts,
            onDayTap: { date in
                selectedDate = date
                viewMode = .day
            }
        )
    }

    // MARK: - Empty Day View

    private var emptyDayView: some View {
        VStack(spacing: 16) {
            Image(systemName: "calendar.badge.plus")
                .font(.system(size: 48))
                .foregroundColor(.secondary)

            Text(String(localized: "No shifts scheduled"))
                .font(.headline)
                .foregroundColor(.secondary)

            Text(String(localized: "Add a shift to get started"))
                .font(.subheadline)
                .foregroundColor(.tertiary)

            Button(action: { showAddShift = true }) {
                Label(String(localized: "Add Shift"), systemImage: "plus")
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding(40)
    }

    // MARK: - Loading View

    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .progressViewStyle(.circular)
                .controlSize(.large)

            Text(String(localized: "Loading schedule..."))
                .font(.headline)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var scheduleToolbar: some ToolbarContent {
        ToolbarItem(placement: .primaryAction) {
            Button(action: {
                Task {
                    await loadSchedule()
                }
            }) {
                Label(String(localized: "Refresh"), systemImage: "arrow.clockwise")
            }
            .keyboardShortcut("r", modifiers: [.command])
            .disabled(isLoading)
            .help(String(localized: "Refresh schedule (Cmd+R)"))
        }

        ToolbarItem(placement: .primaryAction) {
            Button(action: { showAddShift = true }) {
                Label(String(localized: "Add Shift"), systemImage: "plus")
            }
            .keyboardShortcut("n", modifiers: [.command])
            .help(String(localized: "Add new shift (Cmd+N)"))
        }
    }

    // MARK: - Navigation

    private func navigatePrevious() {
        switch viewMode {
        case .day:
            selectedDate = Calendar.current.date(byAdding: .day, value: -1, to: selectedDate) ?? selectedDate
        case .week:
            selectedDate = Calendar.current.date(byAdding: .weekOfYear, value: -1, to: selectedDate) ?? selectedDate
        case .month:
            selectedDate = Calendar.current.date(byAdding: .month, value: -1, to: selectedDate) ?? selectedDate
        }
    }

    private func navigateNext() {
        switch viewMode {
        case .day:
            selectedDate = Calendar.current.date(byAdding: .day, value: 1, to: selectedDate) ?? selectedDate
        case .week:
            selectedDate = Calendar.current.date(byAdding: .weekOfYear, value: 1, to: selectedDate) ?? selectedDate
        case .month:
            selectedDate = Calendar.current.date(byAdding: .month, value: 1, to: selectedDate) ?? selectedDate
        }
    }

    private func goToToday() {
        selectedDate = Date()
    }

    // MARK: - Data Loading

    private func loadSchedule() async {
        isLoading = true
        defer { isLoading = false }

        // For now, use sample data
        // In production, this would call the API
        await MainActor.run {
            let today = Calendar.current.startOfDay(for: selectedDate)
            shifts = generateSampleShifts(around: today)
        }
    }

    private func generateSampleShifts(around date: Date) -> [Shift] {
        var sampleShifts: [Shift] = []
        let calendar = Calendar.current

        // Generate shifts for 7 days around the selected date
        for dayOffset in -3...3 {
            guard let shiftDate = calendar.date(byAdding: .day, value: dayOffset, to: date) else { continue }

            // Morning shift
            if let startTime = calendar.date(bySettingHour: 7, minute: 0, second: 0, of: shiftDate),
               let endTime = calendar.date(bySettingHour: 15, minute: 0, second: 0, of: shiftDate) {
                sampleShifts.append(Shift(
                    id: "shift-\(dayOffset)-morning",
                    staffId: "staff-1",
                    staffName: "Isabelle Bouchard",
                    date: shiftDate,
                    startTime: startTime,
                    endTime: endTime,
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
                ))
            }

            // Afternoon shift
            if let startTime = calendar.date(bySettingHour: 12, minute: 0, second: 0, of: shiftDate),
               let endTime = calendar.date(bySettingHour: 18, minute: 0, second: 0, of: shiftDate) {
                sampleShifts.append(Shift(
                    id: "shift-\(dayOffset)-afternoon",
                    staffId: "staff-2",
                    staffName: "Sophie Tremblay",
                    date: shiftDate,
                    startTime: startTime,
                    endTime: endTime,
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
                ))
            }

            // Substitute shift on alternate days
            if dayOffset % 2 == 0 {
                if let startTime = calendar.date(bySettingHour: 7, minute: 0, second: 0, of: shiftDate),
                   let endTime = calendar.date(bySettingHour: 12, minute: 0, second: 0, of: shiftDate) {
                    sampleShifts.append(Shift(
                        id: "shift-\(dayOffset)-sub",
                        staffId: "staff-sub-1",
                        staffName: "Marc Lefebvre",
                        date: shiftDate,
                        startTime: startTime,
                        endTime: endTime,
                        shiftType: .morning,
                        status: .scheduled,
                        classroomId: "classroom-2",
                        classroomName: "Little Stars",
                        isSubstituteCoverage: true,
                        coveringForStaffId: "staff-3",
                        notes: "Covering for Julie",
                        breakDurationMinutes: 15,
                        createdAt: Date(),
                        updatedAt: Date()
                    ))
                }
            }
        }

        return sampleShifts
    }

    // MARK: - Computed Properties

    private var filteredShifts: [Shift] {
        shifts.filter { shift in
            // Apply staff filter
            if let staffIds = filter.staffIds, !staffIds.isEmpty {
                guard staffIds.contains(shift.staffId) else { return false }
            }

            // Apply classroom filter
            if let classroomIds = filter.classroomIds, !classroomIds.isEmpty {
                guard let classroomId = shift.classroomId, classroomIds.contains(classroomId) else { return false }
            }

            // Apply shift type filter
            if let shiftTypes = filter.shiftTypes, !shiftTypes.isEmpty {
                guard shiftTypes.contains(shift.shiftType) else { return false }
            }

            // Apply status filter
            if let statuses = filter.statuses, !statuses.isEmpty {
                guard statuses.contains(shift.status) else { return false }
            }

            // Apply substitute filter
            if filter.onlySubstitutes {
                guard shift.isSubstituteCoverage else { return false }
            }

            return true
        }
    }

    private var shiftsForSelectedDate: [Shift] {
        filteredShifts.filter { shift in
            Calendar.current.isDate(shift.date, inSameDayAs: selectedDate)
        }.sorted { $0.startTime < $1.startTime }
    }

    private var weekStartDate: Date {
        let calendar = Calendar.current
        let weekday = calendar.component(.weekday, from: selectedDate)
        let daysToSubtract = (weekday - calendar.firstWeekday + 7) % 7
        return calendar.date(byAdding: .day, value: -daysToSubtract, to: selectedDate) ?? selectedDate
    }

    private var uniqueStaffCount: Int {
        Set(filteredShifts.map { $0.staffId }).count
    }

    private var totalScheduledHours: Double {
        filteredShifts.reduce(0) { $0 + $1.durationHours }
    }

    private var substituteShiftCount: Int {
        filteredShifts.filter { $0.isSubstituteCoverage }.count
    }
}

// MARK: - Day Timeline View

/// Displays shifts in a timeline format for a single day
struct DayTimelineView: View {

    let shifts: [Shift]
    let onShiftTap: (Shift) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            ForEach(shifts) { shift in
                ShiftCard(shift: shift)
                    .onTapGesture {
                        onShiftTap(shift)
                    }
            }
        }
        .padding(.horizontal, 16)
        .padding(.bottom, 16)
    }
}

// MARK: - Shift Card

/// A card displaying shift information
struct ShiftCard: View {

    let shift: Shift

    var body: some View {
        HStack(spacing: 12) {
            // Time column
            VStack(alignment: .leading, spacing: 4) {
                Text(shift.startTime.displayTime)
                    .font(.headline)
                Text(shift.endTime.displayTime)
                    .font(.subheadline)
                    .foregroundColor(.secondary)
            }
            .frame(width: 70, alignment: .leading)

            // Color indicator
            Rectangle()
                .fill(shiftColor)
                .frame(width: 4)
                .cornerRadius(2)

            // Shift details
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(shift.staffName ?? String(localized: "Unknown"))
                        .font(.headline)

                    if shift.isSubstituteCoverage {
                        Text(String(localized: "SUB"))
                            .font(.caption2)
                            .fontWeight(.bold)
                            .padding(.horizontal, 4)
                            .padding(.vertical, 2)
                            .background(Color.orange)
                            .foregroundColor(.white)
                            .cornerRadius(3)
                    }

                    Spacer()

                    ShiftStatusBadge(status: shift.status)
                }

                HStack(spacing: 12) {
                    if let classroom = shift.classroomName {
                        Label(classroom, systemImage: "building")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                    }

                    Label(shift.shiftType.displayName, systemImage: shift.shiftType.icon)
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    Label(String(format: "%.1f hrs", shift.durationHours), systemImage: "clock")
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }

                if let notes = shift.notes, !notes.isEmpty {
                    Text(notes)
                        .font(.caption)
                        .foregroundColor(.secondary)
                        .lineLimit(1)
                }
            }
        }
        .padding(12)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .stroke(shiftColor.opacity(0.3), lineWidth: 1)
        )
    }

    private var shiftColor: Color {
        switch shift.status {
        case .scheduled:
            return .blue
        case .confirmed:
            return .green
        case .inProgress:
            return .orange
        case .completed:
            return .gray
        case .cancelled:
            return .red
        case .noShow:
            return .purple
        }
    }
}

// MARK: - Shift Status Badge

/// Badge showing the status of a shift
struct ShiftStatusBadge: View {

    let status: ScheduleStatus

    var body: some View {
        Text(status.displayName)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(backgroundColor)
            .foregroundColor(textColor)
            .cornerRadius(4)
    }

    private var backgroundColor: Color {
        switch status {
        case .scheduled:
            return Color.blue.opacity(0.15)
        case .confirmed:
            return Color.green.opacity(0.15)
        case .inProgress:
            return Color.orange.opacity(0.15)
        case .completed:
            return Color.gray.opacity(0.15)
        case .cancelled:
            return Color.red.opacity(0.15)
        case .noShow:
            return Color.purple.opacity(0.15)
        }
    }

    private var textColor: Color {
        switch status {
        case .scheduled:
            return .blue
        case .confirmed:
            return .green
        case .inProgress:
            return .orange
        case .completed:
            return .gray
        case .cancelled:
            return .red
        case .noShow:
            return .purple
        }
    }
}

// MARK: - Week Calendar View

/// Displays a week view of the schedule
struct WeekCalendarView: View {

    let weekStartDate: Date
    let shifts: [Shift]
    let classrooms: [Classroom]
    let onDayTap: (Date) -> Void
    let onShiftTap: (Shift) -> Void
    let onAddShift: (Date) -> Void

    var body: some View {
        HStack(alignment: .top, spacing: 1) {
            ForEach(0..<7, id: \.self) { dayOffset in
                let date = Calendar.current.date(byAdding: .day, value: dayOffset, to: weekStartDate) ?? weekStartDate
                let dayShifts = shifts.filter { Calendar.current.isDate($0.date, inSameDayAs: date) }

                WeekDayColumn(
                    date: date,
                    shifts: dayShifts,
                    onDayTap: { onDayTap(date) },
                    onShiftTap: onShiftTap,
                    onAddShift: { onAddShift(date) }
                )
            }
        }
        .padding(16)
    }
}

// MARK: - Week Day Column

/// A single day column in the week view
struct WeekDayColumn: View {

    let date: Date
    let shifts: [Shift]
    let onDayTap: () -> Void
    let onShiftTap: (Shift) -> Void
    let onAddShift: () -> Void

    var body: some View {
        VStack(spacing: 8) {
            // Day header
            Button(action: onDayTap) {
                VStack(spacing: 4) {
                    Text(date.formatted(as: "EEE"))
                        .font(.caption)
                        .foregroundColor(.secondary)

                    Text(date.formatted(as: "d"))
                        .font(.title2)
                        .fontWeight(date.isToday ? .bold : .regular)
                        .foregroundColor(date.isToday ? .accentColor : .primary)
                }
                .frame(width: 140)
                .padding(.vertical, 8)
                .background(date.isToday ? Color.accentColor.opacity(0.1) : Color.clear)
                .cornerRadius(8)
            }
            .buttonStyle(.plain)

            // Shifts for this day
            ScrollView {
                VStack(spacing: 4) {
                    ForEach(shifts.sorted { $0.startTime < $1.startTime }) { shift in
                        CompactShiftCard(shift: shift)
                            .onTapGesture {
                                onShiftTap(shift)
                            }
                    }

                    // Add shift button
                    Button(action: onAddShift) {
                        HStack {
                            Image(systemName: "plus.circle")
                            Text(String(localized: "Add"))
                        }
                        .font(.caption)
                        .foregroundColor(.accentColor)
                        .padding(8)
                        .frame(maxWidth: .infinity)
                        .background(Color.accentColor.opacity(0.1))
                        .cornerRadius(6)
                    }
                    .buttonStyle(.plain)
                }
            }
            .frame(width: 140, height: 300)
        }
        .frame(width: 150)
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

// MARK: - Compact Shift Card

/// A compact card for displaying shifts in week view
struct CompactShiftCard: View {

    let shift: Shift

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(shift.staffName?.components(separatedBy: " ").first ?? "")
                    .font(.caption)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Spacer()

                if shift.isSubstituteCoverage {
                    Image(systemName: "arrow.left.arrow.right")
                        .font(.caption2)
                        .foregroundColor(.orange)
                }
            }

            Text(shift.timeRangeString)
                .font(.caption2)
                .foregroundColor(.secondary)

            if let classroom = shift.classroomName {
                Text(classroom)
                    .font(.caption2)
                    .foregroundColor(.secondary)
                    .lineLimit(1)
            }
        }
        .padding(8)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(shiftBackgroundColor)
        .cornerRadius(6)
        .overlay(
            RoundedRectangle(cornerRadius: 6)
                .stroke(shiftBorderColor, lineWidth: 1)
        )
    }

    private var shiftBackgroundColor: Color {
        switch shift.status {
        case .confirmed:
            return Color.green.opacity(0.1)
        case .scheduled:
            return Color.blue.opacity(0.1)
        case .inProgress:
            return Color.orange.opacity(0.1)
        default:
            return Color.gray.opacity(0.1)
        }
    }

    private var shiftBorderColor: Color {
        switch shift.status {
        case .confirmed:
            return Color.green.opacity(0.3)
        case .scheduled:
            return Color.blue.opacity(0.3)
        case .inProgress:
            return Color.orange.opacity(0.3)
        default:
            return Color.gray.opacity(0.3)
        }
    }
}

// MARK: - Month Calendar View

/// Displays a month view with shift indicators
struct MonthCalendarView: View {

    @Binding var selectedDate: Date
    let shifts: [Shift]
    let onDayTap: (Date) -> Void

    private let calendar = Calendar.current

    var body: some View {
        VStack(spacing: 8) {
            // Month header
            HStack {
                Text(selectedDate.formatted(as: "MMMM yyyy"))
                    .font(.title2)
                    .fontWeight(.semibold)
                Spacer()
            }
            .padding(.horizontal, 16)
            .padding(.top, 16)

            // Day of week headers
            HStack(spacing: 0) {
                ForEach(calendar.shortWeekdaySymbols, id: \.self) { symbol in
                    Text(symbol)
                        .font(.caption)
                        .fontWeight(.medium)
                        .foregroundColor(.secondary)
                        .frame(maxWidth: .infinity)
                }
            }
            .padding(.horizontal, 16)

            // Calendar grid
            LazyVGrid(columns: Array(repeating: GridItem(.flexible()), count: 7), spacing: 4) {
                ForEach(daysInMonth, id: \.self) { date in
                    if let date = date {
                        MonthDayCell(
                            date: date,
                            shiftCount: shiftsForDate(date).count,
                            isSelected: calendar.isDate(date, inSameDayAs: selectedDate),
                            isToday: date.isToday
                        )
                        .onTapGesture {
                            onDayTap(date)
                        }
                    } else {
                        Color.clear
                            .frame(height: 60)
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 16)

            Spacer()
        }
    }

    private var daysInMonth: [Date?] {
        var days: [Date?] = []

        let startOfMonth = calendar.date(from: calendar.dateComponents([.year, .month], from: selectedDate))!
        let firstWeekday = calendar.component(.weekday, from: startOfMonth)
        let daysInMonth = calendar.range(of: .day, in: .month, for: selectedDate)!.count

        // Add empty cells for days before the first of the month
        for _ in 1..<firstWeekday {
            days.append(nil)
        }

        // Add days of the month
        for day in 1...daysInMonth {
            if let date = calendar.date(byAdding: .day, value: day - 1, to: startOfMonth) {
                days.append(date)
            }
        }

        return days
    }

    private func shiftsForDate(_ date: Date) -> [Shift] {
        shifts.filter { calendar.isDate($0.date, inSameDayAs: date) }
    }
}

// MARK: - Month Day Cell

/// A single day cell in the month view
struct MonthDayCell: View {

    let date: Date
    let shiftCount: Int
    let isSelected: Bool
    let isToday: Bool

    var body: some View {
        VStack(spacing: 4) {
            Text(date.formatted(as: "d"))
                .font(.headline)
                .foregroundColor(isToday ? .accentColor : .primary)
                .fontWeight(isToday ? .bold : .regular)

            if shiftCount > 0 {
                HStack(spacing: 2) {
                    ForEach(0..<min(shiftCount, 3), id: \.self) { _ in
                        Circle()
                            .fill(Color.accentColor)
                            .frame(width: 6, height: 6)
                    }
                    if shiftCount > 3 {
                        Text("+\(shiftCount - 3)")
                            .font(.caption2)
                            .foregroundColor(.secondary)
                    }
                }
            }
        }
        .frame(height: 60)
        .frame(maxWidth: .infinity)
        .background(isSelected ? Color.accentColor.opacity(0.15) : Color.clear)
        .cornerRadius(8)
        .overlay(
            RoundedRectangle(cornerRadius: 8)
                .stroke(isToday ? Color.accentColor : Color.clear, lineWidth: 2)
        )
    }
}

// MARK: - Shift Form Sheet

/// Sheet for creating or editing a shift
struct ShiftFormSheet: View {

    let shift: Shift?
    let date: Date
    let staffList: [Staff]
    let classrooms: [Classroom]
    let onSave: (Shift) -> Void
    let onDelete: ((Shift) -> Void)?

    @Environment(\.dismiss) private var dismiss

    @State private var selectedStaffId: String = ""
    @State private var selectedDate: Date
    @State private var startTime: Date
    @State private var endTime: Date
    @State private var shiftType: ShiftType = .fullDay
    @State private var status: ScheduleStatus = .scheduled
    @State private var selectedClassroomId: String?
    @State private var isSubstitute: Bool = false
    @State private var coveringForStaffId: String?
    @State private var notes: String = ""
    @State private var breakMinutes: Int = 30
    @State private var showDeleteConfirmation = false

    init(
        shift: Shift? = nil,
        date: Date,
        staffList: [Staff],
        classrooms: [Classroom],
        onSave: @escaping (Shift) -> Void,
        onDelete: ((Shift) -> Void)? = nil
    ) {
        self.shift = shift
        self.date = date
        self.staffList = staffList
        self.classrooms = classrooms
        self.onSave = onSave
        self.onDelete = onDelete

        let calendar = Calendar.current
        let defaultStart = calendar.date(bySettingHour: 7, minute: 0, second: 0, of: date) ?? date
        let defaultEnd = calendar.date(bySettingHour: 15, minute: 0, second: 0, of: date) ?? date

        _selectedDate = State(initialValue: shift?.date ?? date)
        _startTime = State(initialValue: shift?.startTime ?? defaultStart)
        _endTime = State(initialValue: shift?.endTime ?? defaultEnd)
        _selectedStaffId = State(initialValue: shift?.staffId ?? "")
        _shiftType = State(initialValue: shift?.shiftType ?? .fullDay)
        _status = State(initialValue: shift?.status ?? .scheduled)
        _selectedClassroomId = State(initialValue: shift?.classroomId)
        _isSubstitute = State(initialValue: shift?.isSubstituteCoverage ?? false)
        _coveringForStaffId = State(initialValue: shift?.coveringForStaffId)
        _notes = State(initialValue: shift?.notes ?? "")
        _breakMinutes = State(initialValue: shift?.breakDurationMinutes ?? 30)
    }

    var body: some View {
        NavigationStack {
            Form {
                Section(String(localized: "Staff")) {
                    Picker(String(localized: "Staff Member"), selection: $selectedStaffId) {
                        Text(String(localized: "Select staff...")).tag("")
                        ForEach(staffList.filter { $0.isActive }) { staff in
                            Text(staff.fullName).tag(staff.id)
                        }
                    }
                }

                Section(String(localized: "Schedule")) {
                    DatePicker(
                        String(localized: "Date"),
                        selection: $selectedDate,
                        displayedComponents: [.date]
                    )

                    DatePicker(
                        String(localized: "Start Time"),
                        selection: $startTime,
                        displayedComponents: [.hourAndMinute]
                    )

                    DatePicker(
                        String(localized: "End Time"),
                        selection: $endTime,
                        displayedComponents: [.hourAndMinute]
                    )

                    Picker(String(localized: "Shift Type"), selection: $shiftType) {
                        ForEach(ShiftType.allCases) { type in
                            Label(type.displayName, systemImage: type.icon).tag(type)
                        }
                    }

                    Stepper(
                        String(localized: "Break: \(breakMinutes) min"),
                        value: $breakMinutes,
                        in: 0...120,
                        step: 15
                    )
                }

                Section(String(localized: "Assignment")) {
                    Picker(String(localized: "Classroom"), selection: $selectedClassroomId) {
                        Text(String(localized: "None")).tag(nil as String?)
                        ForEach(classrooms) { classroom in
                            Text(classroom.name).tag(classroom.id as String?)
                        }
                    }

                    Picker(String(localized: "Status"), selection: $status) {
                        ForEach(ScheduleStatus.allCases, id: \.self) { status in
                            Text(status.displayName).tag(status)
                        }
                    }
                }

                Section(String(localized: "Substitute Coverage")) {
                    Toggle(String(localized: "This is a substitute shift"), isOn: $isSubstitute)

                    if isSubstitute {
                        Picker(String(localized: "Covering for"), selection: $coveringForStaffId) {
                            Text(String(localized: "Select...")).tag(nil as String?)
                            ForEach(staffList.filter { $0.status == .onLeave }) { staff in
                                Text(staff.fullName).tag(staff.id as String?)
                            }
                        }
                    }
                }

                Section(String(localized: "Notes")) {
                    TextField(String(localized: "Add notes..."), text: $notes, axis: .vertical)
                        .lineLimit(3...6)
                }

                if shift != nil {
                    Section {
                        Button(role: .destructive, action: { showDeleteConfirmation = true }) {
                            Label(String(localized: "Delete Shift"), systemImage: "trash")
                                .foregroundColor(.red)
                        }
                    }
                }
            }
            .formStyle(.grouped)
            .navigationTitle(shift != nil ? String(localized: "Edit Shift") : String(localized: "New Shift"))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(String(localized: "Cancel")) {
                        dismiss()
                    }
                }

                ToolbarItem(placement: .confirmationAction) {
                    Button(String(localized: "Save")) {
                        saveShift()
                    }
                    .disabled(selectedStaffId.isEmpty)
                }
            }
            .confirmationDialog(
                String(localized: "Delete Shift"),
                isPresented: $showDeleteConfirmation,
                titleVisibility: .visible
            ) {
                Button(String(localized: "Delete"), role: .destructive) {
                    if let shift = shift {
                        onDelete?(shift)
                    }
                    dismiss()
                }
                Button(String(localized: "Cancel"), role: .cancel) {}
            } message: {
                Text(String(localized: "Are you sure you want to delete this shift?"))
            }
        }
        .frame(width: 500, height: 650)
    }

    private func saveShift() {
        let selectedStaff = staffList.first { $0.id == selectedStaffId }
        let newShift = Shift(
            id: shift?.id ?? UUID().uuidString,
            staffId: selectedStaffId,
            staffName: selectedStaff?.fullName,
            date: selectedDate,
            startTime: startTime,
            endTime: endTime,
            shiftType: shiftType,
            status: status,
            classroomId: selectedClassroomId,
            classroomName: classrooms.first { $0.id == selectedClassroomId }?.name,
            isSubstituteCoverage: isSubstitute,
            coveringForStaffId: isSubstitute ? coveringForStaffId : nil,
            notes: notes.nilIfBlank,
            breakDurationMinutes: breakMinutes,
            createdAt: shift?.createdAt ?? Date(),
            updatedAt: Date()
        )

        onSave(newShift)
        dismiss()
    }
}

// MARK: - Schedule Filter Sheet

/// Sheet for configuring schedule filters
struct ScheduleFilterSheet: View {

    @Binding var filter: ScheduleFilter
    let staffList: [Staff]
    let classrooms: [Classroom]

    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            Form {
                Section(String(localized: "Staff")) {
                    ForEach(staffList.filter { $0.isActive }) { staff in
                        Toggle(staff.fullName, isOn: Binding(
                            get: { filter.staffIds?.contains(staff.id) ?? false },
                            set: { isSelected in
                                var ids = filter.staffIds ?? []
                                if isSelected {
                                    ids.insert(staff.id)
                                } else {
                                    ids.remove(staff.id)
                                }
                                filter.staffIds = ids.isEmpty ? nil : ids
                            }
                        ))
                    }
                }

                Section(String(localized: "Classrooms")) {
                    ForEach(classrooms) { classroom in
                        Toggle(classroom.name, isOn: Binding(
                            get: { filter.classroomIds?.contains(classroom.id) ?? false },
                            set: { isSelected in
                                var ids = filter.classroomIds ?? []
                                if isSelected {
                                    ids.insert(classroom.id)
                                } else {
                                    ids.remove(classroom.id)
                                }
                                filter.classroomIds = ids.isEmpty ? nil : ids
                            }
                        ))
                    }
                }

                Section(String(localized: "Shift Types")) {
                    ForEach(ShiftType.allCases) { type in
                        Toggle(type.displayName, isOn: Binding(
                            get: { filter.shiftTypes?.contains(type) ?? false },
                            set: { isSelected in
                                var types = filter.shiftTypes ?? []
                                if isSelected {
                                    types.insert(type)
                                } else {
                                    types.remove(type)
                                }
                                filter.shiftTypes = types.isEmpty ? nil : types
                            }
                        ))
                    }
                }

                Section(String(localized: "Options")) {
                    Toggle(String(localized: "Show only substitutes"), isOn: $filter.onlySubstitutes)
                }
            }
            .formStyle(.grouped)
            .navigationTitle(String(localized: "Filter Schedule"))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(String(localized: "Reset")) {
                        filter = .default
                    }
                }

                ToolbarItem(placement: .confirmationAction) {
                    Button(String(localized: "Done")) {
                        dismiss()
                    }
                }
            }
        }
        .frame(width: 400, height: 500)
    }
}

// MARK: - Preview

#Preview("Schedule View - Week") {
    NavigationStack {
        ScheduleView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 1200, height: 800)
}

#Preview("Schedule View - Day") {
    NavigationStack {
        ScheduleView()
    }
    .environmentObject(AuthViewModel.previewAuthenticated)
    .frame(width: 900, height: 700)
}

#Preview("Shift Card") {
    VStack(spacing: 16) {
        ShiftCard(shift: .preview)
        ShiftCard(shift: .previewSubstitute)
        ShiftCard(shift: .previewAfternoon)
    }
    .padding()
    .frame(width: 600)
}

#Preview("Compact Shift Card") {
    VStack(spacing: 8) {
        CompactShiftCard(shift: .preview)
        CompactShiftCard(shift: .previewSubstitute)
        CompactShiftCard(shift: .previewAfternoon)
    }
    .padding()
    .frame(width: 160)
}

#Preview("Shift Form - New") {
    ShiftFormSheet(
        date: Date(),
        staffList: [.preview, .previewSubstitute, .previewOnLeave],
        classrooms: Classroom.previewList,
        onSave: { _ in }
    )
}

#Preview("Shift Form - Edit") {
    ShiftFormSheet(
        shift: .preview,
        date: Date(),
        staffList: [.preview, .previewSubstitute, .previewOnLeave],
        classrooms: Classroom.previewList,
        onSave: { _ in },
        onDelete: { _ in }
    )
}
