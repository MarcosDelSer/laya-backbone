//
//  ChildFormView.swift
//  LAYAAdmin
//
//  Form view for creating and editing child records.
//  Supports both add and edit modes with comprehensive validation.
//

import SwiftUI

// MARK: - Child Form View

/// A form view for adding or editing child records.
///
/// Features:
/// - Personal information fields (name, date of birth)
/// - Enrollment status and classroom assignment
/// - Guardian contact information
/// - Medical information (allergies, notes, dietary requirements)
/// - Form validation with inline error display
/// - Support for both create and edit modes
struct ChildFormView: View {

    // MARK: - Properties

    /// The edit mode (add or edit)
    let mode: FormMode

    /// Callback when save is successful
    var onSave: ((Child) -> Void)?

    /// Callback when cancel is tapped
    var onCancel: (() -> Void)?

    /// The child list view model for performing operations
    @ObservedObject var viewModel: ChildListViewModel

    /// Environment to dismiss the view
    @Environment(\.dismiss) private var dismiss

    // MARK: - Form State

    @State private var firstName: String = ""
    @State private var lastName: String = ""
    @State private var dateOfBirth: Date = Calendar.current.date(byAdding: .year, value: -3, to: Date()) ?? Date()
    @State private var enrollmentStatus: EnrollmentStatus = .pending
    @State private var classroomId: String = ""
    @State private var classroomName: String = ""
    @State private var primaryGuardianId: String = ""
    @State private var primaryGuardianName: String = ""
    @State private var primaryGuardianEmail: String = ""
    @State private var primaryGuardianPhone: String = ""
    @State private var secondaryGuardianId: String = ""
    @State private var secondaryGuardianName: String = ""
    @State private var allergies: String = ""
    @State private var medicalNotes: String = ""
    @State private var dietaryRequirements: String = ""
    @State private var enrollmentDate: Date = Date()
    @State private var expectedGraduationDate: Date = Calendar.current.date(byAdding: .year, value: 5, to: Date()) ?? Date()
    @State private var hasGraduationDate: Bool = false
    @State private var notes: String = ""

    // MARK: - Validation State

    @State private var firstNameTouched = false
    @State private var lastNameTouched = false
    @State private var primaryGuardianNameTouched = false
    @State private var primaryGuardianEmailTouched = false

    // MARK: - UI State

    @State private var showDeleteConfirmation = false
    @State private var isExpanded: [String: Bool] = [
        "personal": true,
        "enrollment": true,
        "guardians": true,
        "medical": false,
        "notes": false
    ]

    // MARK: - Form Mode

    enum FormMode: Equatable {
        case add
        case edit(Child)

        var title: String {
            switch self {
            case .add:
                return String(localized: "Add Child")
            case .edit:
                return String(localized: "Edit Child")
            }
        }

        var saveButtonTitle: String {
            switch self {
            case .add:
                return String(localized: "Add Child")
            case .edit:
                return String(localized: "Save Changes")
            }
        }

        var childId: String? {
            if case .edit(let child) = self {
                return child.id
            }
            return nil
        }
    }

    // MARK: - Initialization

    init(
        mode: FormMode,
        viewModel: ChildListViewModel,
        onSave: ((Child) -> Void)? = nil,
        onCancel: (() -> Void)? = nil
    ) {
        self.mode = mode
        self.viewModel = viewModel
        self.onSave = onSave
        self.onCancel = onCancel

        // Pre-populate form for edit mode
        if case .edit(let child) = mode {
            _firstName = State(initialValue: child.firstName)
            _lastName = State(initialValue: child.lastName)
            _dateOfBirth = State(initialValue: child.dateOfBirth)
            _enrollmentStatus = State(initialValue: child.enrollmentStatus)
            _classroomId = State(initialValue: child.classroomId ?? "")
            _classroomName = State(initialValue: child.classroomName ?? "")
            _primaryGuardianId = State(initialValue: child.primaryGuardianId)
            _primaryGuardianName = State(initialValue: child.primaryGuardianName)
            _primaryGuardianEmail = State(initialValue: child.primaryGuardianEmail ?? "")
            _primaryGuardianPhone = State(initialValue: child.primaryGuardianPhone ?? "")
            _secondaryGuardianId = State(initialValue: child.secondaryGuardianId ?? "")
            _secondaryGuardianName = State(initialValue: child.secondaryGuardianName ?? "")
            _allergies = State(initialValue: child.allergies ?? "")
            _medicalNotes = State(initialValue: child.medicalNotes ?? "")
            _dietaryRequirements = State(initialValue: child.dietaryRequirements ?? "")
            _enrollmentDate = State(initialValue: child.enrollmentDate ?? Date())
            _expectedGraduationDate = State(initialValue: child.expectedGraduationDate ?? Calendar.current.date(byAdding: .year, value: 5, to: Date()) ?? Date())
            _hasGraduationDate = State(initialValue: child.expectedGraduationDate != nil)
            _notes = State(initialValue: child.notes ?? "")
        }
    }

    // MARK: - Computed Properties

    private var isFormValid: Bool {
        !firstName.isBlank &&
        !lastName.isBlank &&
        !primaryGuardianName.isBlank &&
        (primaryGuardianEmail.isEmpty || primaryGuardianEmail.isValidEmail)
    }

    private var firstNameError: String? {
        guard firstNameTouched else { return nil }
        if firstName.isBlank {
            return String(localized: "First name is required")
        }
        return nil
    }

    private var lastNameError: String? {
        guard lastNameTouched else { return nil }
        if lastName.isBlank {
            return String(localized: "Last name is required")
        }
        return nil
    }

    private var primaryGuardianNameError: String? {
        guard primaryGuardianNameTouched else { return nil }
        if primaryGuardianName.isBlank {
            return String(localized: "Primary guardian name is required")
        }
        return nil
    }

    private var primaryGuardianEmailError: String? {
        guard primaryGuardianEmailTouched else { return nil }
        if !primaryGuardianEmail.isEmpty && !primaryGuardianEmail.isValidEmail {
            return String(localized: "Please enter a valid email address")
        }
        return nil
    }

    // MARK: - Body

    var body: some View {
        VStack(spacing: 0) {
            // Header
            formHeader

            Divider()

            // Form content
            ScrollView {
                VStack(spacing: 20) {
                    personalInfoSection
                    enrollmentSection
                    guardiansSection
                    medicalSection
                    notesSection
                }
                .padding(24)
            }

            Divider()

            // Footer with buttons
            formFooter
        }
        .frame(minWidth: 500, idealWidth: 600, maxWidth: 700)
        .frame(minHeight: 600, idealHeight: 700, maxHeight: 800)
        .background(Color(NSColor.windowBackgroundColor))
        .confirmationDialog(
            String(localized: "Delete Child"),
            isPresented: $showDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Delete"), role: .destructive) {
                deleteChild()
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        } message: {
            Text(String(localized: "Are you sure you want to delete this child? This action cannot be undone."))
        }
        .alert(
            String(localized: "Error"),
            isPresented: $viewModel.showError,
            presenting: viewModel.error
        ) { _ in
            Button(String(localized: "OK")) {
                viewModel.clearError()
            }
        } message: { error in
            Text(error.localizedDescription)
        }
    }

    // MARK: - Header

    private var formHeader: some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                Text(mode.title)
                    .font(.title2)
                    .fontWeight(.bold)

                if case .edit(let child) = mode {
                    Text(child.fullName)
                        .font(.subheadline)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()

            if case .edit = mode {
                Button(role: .destructive, action: {
                    showDeleteConfirmation = true
                }) {
                    Label(String(localized: "Delete"), systemImage: "trash")
                }
                .buttonStyle(.borderless)
                .foregroundColor(.red)
            }
        }
        .padding(.horizontal, 24)
        .padding(.vertical, 16)
    }

    // MARK: - Personal Info Section

    private var personalInfoSection: some View {
        FormSection(
            title: String(localized: "Personal Information"),
            icon: "person.fill",
            isExpanded: Binding(
                get: { isExpanded["personal"] ?? true },
                set: { isExpanded["personal"] = $0 }
            )
        ) {
            VStack(spacing: 16) {
                HStack(spacing: 16) {
                    // First name
                    FormField(
                        label: String(localized: "First Name"),
                        isRequired: true,
                        error: firstNameError
                    ) {
                        TextField(String(localized: "Enter first name"), text: $firstName)
                            .textFieldStyle(.roundedBorder)
                            .onChange(of: firstName) { _, _ in
                                firstNameTouched = true
                            }
                    }

                    // Last name
                    FormField(
                        label: String(localized: "Last Name"),
                        isRequired: true,
                        error: lastNameError
                    ) {
                        TextField(String(localized: "Enter last name"), text: $lastName)
                            .textFieldStyle(.roundedBorder)
                            .onChange(of: lastName) { _, _ in
                                lastNameTouched = true
                            }
                    }
                }

                // Date of birth
                FormField(
                    label: String(localized: "Date of Birth"),
                    isRequired: true
                ) {
                    DatePicker(
                        "",
                        selection: $dateOfBirth,
                        in: ...Date(),
                        displayedComponents: .date
                    )
                    .datePickerStyle(.field)
                    .labelsHidden()
                }
            }
        }
    }

    // MARK: - Enrollment Section

    private var enrollmentSection: some View {
        FormSection(
            title: String(localized: "Enrollment Details"),
            icon: "checkmark.seal.fill",
            isExpanded: Binding(
                get: { isExpanded["enrollment"] ?? true },
                set: { isExpanded["enrollment"] = $0 }
            )
        ) {
            VStack(spacing: 16) {
                HStack(spacing: 16) {
                    // Enrollment status
                    FormField(label: String(localized: "Status")) {
                        Picker("", selection: $enrollmentStatus) {
                            ForEach(EnrollmentStatus.allCases, id: \.self) { status in
                                Text(status.displayName)
                                    .tag(status)
                            }
                        }
                        .pickerStyle(.menu)
                        .labelsHidden()
                    }

                    // Classroom
                    FormField(label: String(localized: "Classroom")) {
                        TextField(String(localized: "Enter classroom name"), text: $classroomName)
                            .textFieldStyle(.roundedBorder)
                    }
                }

                HStack(spacing: 16) {
                    // Enrollment date
                    FormField(label: String(localized: "Enrollment Date")) {
                        DatePicker(
                            "",
                            selection: $enrollmentDate,
                            displayedComponents: .date
                        )
                        .datePickerStyle(.field)
                        .labelsHidden()
                    }

                    // Expected graduation
                    FormField(label: String(localized: "Expected Graduation")) {
                        HStack {
                            Toggle("", isOn: $hasGraduationDate)
                                .toggleStyle(.checkbox)
                                .labelsHidden()

                            if hasGraduationDate {
                                DatePicker(
                                    "",
                                    selection: $expectedGraduationDate,
                                    in: Date()...,
                                    displayedComponents: .date
                                )
                                .datePickerStyle(.field)
                                .labelsHidden()
                            } else {
                                Text(String(localized: "Not set"))
                                    .foregroundColor(.secondary)
                            }

                            Spacer()
                        }
                    }
                }
            }
        }
    }

    // MARK: - Guardians Section

    private var guardiansSection: some View {
        FormSection(
            title: String(localized: "Guardian Information"),
            icon: "person.2.fill",
            isExpanded: Binding(
                get: { isExpanded["guardians"] ?? true },
                set: { isExpanded["guardians"] = $0 }
            )
        ) {
            VStack(spacing: 20) {
                // Primary Guardian
                VStack(alignment: .leading, spacing: 12) {
                    HStack {
                        Image(systemName: "person.crop.circle.fill")
                            .foregroundColor(.accentColor)
                        Text(String(localized: "Primary Guardian"))
                            .font(.subheadline)
                            .fontWeight(.semibold)
                    }

                    VStack(spacing: 12) {
                        FormField(
                            label: String(localized: "Name"),
                            isRequired: true,
                            error: primaryGuardianNameError
                        ) {
                            TextField(String(localized: "Enter guardian name"), text: $primaryGuardianName)
                                .textFieldStyle(.roundedBorder)
                                .onChange(of: primaryGuardianName) { _, _ in
                                    primaryGuardianNameTouched = true
                                }
                        }

                        HStack(spacing: 16) {
                            FormField(
                                label: String(localized: "Email"),
                                error: primaryGuardianEmailError
                            ) {
                                TextField(String(localized: "Enter email"), text: $primaryGuardianEmail)
                                    .textFieldStyle(.roundedBorder)
                                    .textContentType(.emailAddress)
                                    .onChange(of: primaryGuardianEmail) { _, _ in
                                        primaryGuardianEmailTouched = true
                                    }
                            }

                            FormField(label: String(localized: "Phone")) {
                                TextField(String(localized: "Enter phone number"), text: $primaryGuardianPhone)
                                    .textFieldStyle(.roundedBorder)
                                    .textContentType(.telephoneNumber)
                            }
                        }
                    }
                    .padding(.leading, 24)
                }

                Divider()

                // Secondary Guardian
                VStack(alignment: .leading, spacing: 12) {
                    HStack {
                        Image(systemName: "person.crop.circle")
                            .foregroundColor(.secondary)
                        Text(String(localized: "Secondary Guardian (Optional)"))
                            .font(.subheadline)
                            .fontWeight(.semibold)
                    }

                    FormField(label: String(localized: "Name")) {
                        TextField(String(localized: "Enter guardian name"), text: $secondaryGuardianName)
                            .textFieldStyle(.roundedBorder)
                    }
                    .padding(.leading, 24)
                }
            }
        }
    }

    // MARK: - Medical Section

    private var medicalSection: some View {
        FormSection(
            title: String(localized: "Medical Information"),
            icon: "cross.case.fill",
            isExpanded: Binding(
                get: { isExpanded["medical"] ?? false },
                set: { isExpanded["medical"] = $0 }
            )
        ) {
            VStack(spacing: 16) {
                // Allergies
                FormField(label: String(localized: "Allergies")) {
                    TextEditor(text: $allergies)
                        .font(.body)
                        .frame(minHeight: 60, maxHeight: 100)
                        .padding(4)
                        .background(Color(NSColor.textBackgroundColor))
                        .cornerRadius(6)
                        .overlay(
                            RoundedRectangle(cornerRadius: 6)
                                .stroke(Color(NSColor.separatorColor), lineWidth: 1)
                        )
                }

                // Medical notes
                FormField(label: String(localized: "Medical Notes")) {
                    TextEditor(text: $medicalNotes)
                        .font(.body)
                        .frame(minHeight: 60, maxHeight: 100)
                        .padding(4)
                        .background(Color(NSColor.textBackgroundColor))
                        .cornerRadius(6)
                        .overlay(
                            RoundedRectangle(cornerRadius: 6)
                                .stroke(Color(NSColor.separatorColor), lineWidth: 1)
                        )
                }

                // Dietary requirements
                FormField(label: String(localized: "Dietary Requirements")) {
                    TextEditor(text: $dietaryRequirements)
                        .font(.body)
                        .frame(minHeight: 60, maxHeight: 100)
                        .padding(4)
                        .background(Color(NSColor.textBackgroundColor))
                        .cornerRadius(6)
                        .overlay(
                            RoundedRectangle(cornerRadius: 6)
                                .stroke(Color(NSColor.separatorColor), lineWidth: 1)
                        )
                }
            }
        }
    }

    // MARK: - Notes Section

    private var notesSection: some View {
        FormSection(
            title: String(localized: "Additional Notes"),
            icon: "note.text",
            isExpanded: Binding(
                get: { isExpanded["notes"] ?? false },
                set: { isExpanded["notes"] = $0 }
            )
        ) {
            TextEditor(text: $notes)
                .font(.body)
                .frame(minHeight: 80, maxHeight: 150)
                .padding(4)
                .background(Color(NSColor.textBackgroundColor))
                .cornerRadius(6)
                .overlay(
                    RoundedRectangle(cornerRadius: 6)
                        .stroke(Color(NSColor.separatorColor), lineWidth: 1)
                )
        }
    }

    // MARK: - Footer

    private var formFooter: some View {
        HStack(spacing: 12) {
            // Validation indicator
            if !isFormValid {
                HStack(spacing: 4) {
                    Image(systemName: "exclamationmark.circle")
                        .foregroundColor(.orange)
                    Text(String(localized: "Please fill in all required fields"))
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
            }

            Spacer()

            // Cancel button
            Button(action: {
                onCancel?()
                dismiss()
            }) {
                Text(String(localized: "Cancel"))
                    .frame(minWidth: 80)
            }
            .buttonStyle(.bordered)
            .keyboardShortcut(.escape, modifiers: [])

            // Save button
            Button(action: {
                saveChild()
            }) {
                HStack(spacing: 6) {
                    if viewModel.isSaving {
                        ProgressView()
                            .progressViewStyle(.circular)
                            .controlSize(.small)
                    }
                    Text(mode.saveButtonTitle)
                }
                .frame(minWidth: 100)
            }
            .buttonStyle(.borderedProminent)
            .disabled(!isFormValid || viewModel.isSaving)
            .keyboardShortcut(.return, modifiers: [.command])
        }
        .padding(.horizontal, 24)
        .padding(.vertical, 16)
    }

    // MARK: - Actions

    private func saveChild() {
        // Mark all fields as touched for validation
        firstNameTouched = true
        lastNameTouched = true
        primaryGuardianNameTouched = true
        primaryGuardianEmailTouched = true

        guard isFormValid else { return }

        let request = ChildRequest(
            firstName: firstName.trimmingCharacters(in: .whitespaces),
            lastName: lastName.trimmingCharacters(in: .whitespaces),
            dateOfBirth: dateOfBirth,
            enrollmentStatus: enrollmentStatus,
            classroomId: classroomId.nilIfBlank,
            primaryGuardianId: primaryGuardianId.nilIfBlank ?? UUID().uuidString,
            secondaryGuardianId: secondaryGuardianId.nilIfBlank,
            allergies: allergies.nilIfBlank,
            medicalNotes: medicalNotes.nilIfBlank,
            dietaryRequirements: dietaryRequirements.nilIfBlank,
            enrollmentDate: enrollmentDate,
            expectedGraduationDate: hasGraduationDate ? expectedGraduationDate : nil,
            notes: notes.nilIfBlank
        )

        Task {
            var savedChild: Child?

            switch mode {
            case .add:
                savedChild = await viewModel.createChild(request)
            case .edit(let child):
                savedChild = await viewModel.updateChild(childId: child.id, request: request)
            }

            if let child = savedChild {
                onSave?(child)
                dismiss()
            }
        }
    }

    private func deleteChild() {
        guard case .edit(let child) = mode else { return }

        Task {
            let success = await viewModel.deleteChild(childId: child.id)
            if success {
                dismiss()
            }
        }
    }
}

// MARK: - Form Section

/// A collapsible section container for form fields.
struct FormSection<Content: View>: View {

    let title: String
    let icon: String
    @Binding var isExpanded: Bool
    let content: Content

    init(
        title: String,
        icon: String,
        isExpanded: Binding<Bool>,
        @ViewBuilder content: () -> Content
    ) {
        self.title = title
        self.icon = icon
        self._isExpanded = isExpanded
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            // Section header
            Button(action: {
                withAnimation(.easeInOut(duration: 0.2)) {
                    isExpanded.toggle()
                }
            }) {
                HStack {
                    Image(systemName: icon)
                        .font(.headline)
                        .foregroundColor(.accentColor)
                        .frame(width: 24)

                    Text(title)
                        .font(.headline)
                        .foregroundColor(.primary)

                    Spacer()

                    Image(systemName: isExpanded ? "chevron.up" : "chevron.down")
                        .font(.caption)
                        .foregroundColor(.secondary)
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
            }
            .buttonStyle(.plain)

            // Section content
            if isExpanded {
                VStack(alignment: .leading, spacing: 16) {
                    content
                }
                .padding(.horizontal, 16)
                .padding(.bottom, 16)
            }
        }
        .background(Color(NSColor.controlBackgroundColor))
        .cornerRadius(8)
    }
}

// MARK: - Form Field

/// A labeled form field with optional required indicator and error message.
struct FormField<Content: View>: View {

    let label: String
    var isRequired: Bool = false
    var error: String? = nil
    let content: Content

    init(
        label: String,
        isRequired: Bool = false,
        error: String? = nil,
        @ViewBuilder content: () -> Content
    ) {
        self.label = label
        self.isRequired = isRequired
        self.error = error
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack(spacing: 4) {
                Text(label)
                    .font(.caption)
                    .foregroundColor(.secondary)

                if isRequired {
                    Text("*")
                        .font(.caption)
                        .foregroundColor(.red)
                }
            }

            content

            if let error = error {
                Text(error)
                    .font(.caption)
                    .foregroundColor(.red)
            }
        }
    }
}

// MARK: - Preview

#Preview("Add Child Form") {
    ChildFormView(
        mode: .add,
        viewModel: .preview
    )
}

#Preview("Edit Child Form") {
    ChildFormView(
        mode: .edit(.preview),
        viewModel: .preview
    )
}

#Preview("Form Section") {
    VStack(spacing: 16) {
        FormSection(
            title: "Personal Information",
            icon: "person.fill",
            isExpanded: .constant(true)
        ) {
            Text("Section content goes here")
        }

        FormSection(
            title: "Collapsed Section",
            icon: "folder.fill",
            isExpanded: .constant(false)
        ) {
            Text("Hidden content")
        }
    }
    .padding()
    .frame(width: 500)
}

#Preview("Form Field") {
    VStack(spacing: 16) {
        FormField(label: "Name", isRequired: true) {
            TextField("Enter name", text: .constant(""))
                .textFieldStyle(.roundedBorder)
        }

        FormField(label: "Email", error: "Please enter a valid email") {
            TextField("Enter email", text: .constant("invalid"))
                .textFieldStyle(.roundedBorder)
        }
    }
    .padding()
    .frame(width: 400)
}
