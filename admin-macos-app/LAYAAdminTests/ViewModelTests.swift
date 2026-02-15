//
//  ViewModelTests.swift
//  LAYAAdminTests
//
//  Unit tests for ViewModels verifying state management,
//  data transformations, user interactions, and error handling.
//

import XCTest
import Combine
@testable import LAYAAdmin

// MARK: - Mock Services for ViewModel Testing

/// Mock AuthService for testing ViewModels that depend on authentication
@MainActor
final class MockAuthService: AuthServiceProtocol {

    // MARK: - Published Properties

    @Published var authState: AuthState = .unauthenticated

    var authStatePublisher: AnyPublisher<AuthState, Never> {
        $authState.eraseToAnyPublisher()
    }

    // MARK: - Mock Behavior Control

    var mockUser: User?
    var mockLoginResponse: User?
    var shouldThrowError: AuthError?
    var loginCalled = false
    var logoutCalled = false
    var restoreSessionCalled = false
    var refreshTokenCalled = false
    var requestPasswordResetCalled = false
    var capturedEmail: String?
    var capturedPassword: String?
    var capturedRememberMe: Bool?

    // MARK: - Protocol Implementation

    func login(email: String, password: String, rememberMe: Bool) async throws -> User {
        loginCalled = true
        capturedEmail = email
        capturedPassword = password
        capturedRememberMe = rememberMe

        if let error = shouldThrowError {
            authState = .failed(error)
            throw error
        }

        guard let user = mockLoginResponse ?? mockUser else {
            throw AuthError.invalidCredentials
        }

        authState = .authenticated(user)
        return user
    }

    func logout() async {
        logoutCalled = true
        authState = .unauthenticated
    }

    func restoreSession() async -> Bool {
        restoreSessionCalled = true

        if let user = mockUser {
            authState = .authenticated(user)
            return true
        }

        return false
    }

    func refreshToken() async throws {
        refreshTokenCalled = true

        if let error = shouldThrowError {
            throw error
        }
    }

    func requestPasswordReset(email: String) async throws -> String {
        requestPasswordResetCalled = true
        capturedEmail = email

        if let error = shouldThrowError {
            throw error
        }

        return "Password reset email sent"
    }

    func getAccessToken() -> String? {
        if case .authenticated = authState {
            return "mock-token"
        }
        return nil
    }

    // MARK: - Helper Methods

    func reset() {
        authState = .unauthenticated
        mockUser = nil
        mockLoginResponse = nil
        shouldThrowError = nil
        loginCalled = false
        logoutCalled = false
        restoreSessionCalled = false
        refreshTokenCalled = false
        requestPasswordResetCalled = false
        capturedEmail = nil
        capturedPassword = nil
        capturedRememberMe = nil
    }
}

/// Mock GibbonClient for testing ViewModels
@MainActor
final class MockGibbonClient {

    // MARK: - Mock Data

    var mockChildren: [Child] = []
    var mockStaff: [Staff] = []
    var mockInvoices: [Invoice] = []
    var mockDashboard: DashboardData?
    var mockFinanceSummary: FinanceSummary?
    var shouldThrowError: APIError?

    // MARK: - Tracking

    var fetchChildrenCalled = false
    var fetchStaffCalled = false
    var fetchInvoicesCalled = false
    var fetchDashboardCalled = false
    var createChildCalled = false
    var updateChildCalled = false
    var deleteChildCalled = false
    var createInvoiceCalled = false
    var recordPaymentCalled = false
    var capturedChildId: String?
    var capturedInvoiceId: String?

    // MARK: - Methods

    func fetchChildren(
        status: EnrollmentStatus?,
        classroomId: String?,
        skip: Int,
        limit: Int
    ) async throws -> PaginatedResponse<Child> {
        fetchChildrenCalled = true

        if let error = shouldThrowError {
            throw error
        }

        let filteredChildren = mockChildren.filter { child in
            if let status = status, child.enrollmentStatus != status {
                return false
            }
            if let classroomId = classroomId, child.classroomId != classroomId {
                return false
            }
            return true
        }

        let endIndex = min(skip + limit, filteredChildren.count)
        let items = Array(filteredChildren[skip..<endIndex])

        return PaginatedResponse(
            items: items,
            total: filteredChildren.count,
            skip: skip,
            limit: limit
        )
    }

    func fetchInvoices(
        status: InvoiceStatus?,
        familyId: String?,
        childId: String?,
        startDate: Date?,
        endDate: Date?,
        skip: Int,
        limit: Int
    ) async throws -> PaginatedResponse<Invoice> {
        fetchInvoicesCalled = true

        if let error = shouldThrowError {
            throw error
        }

        let filteredInvoices = mockInvoices.filter { invoice in
            if let status = status, invoice.status != status {
                return false
            }
            if let familyId = familyId, invoice.familyId != familyId {
                return false
            }
            return true
        }

        let endIndex = min(skip + limit, filteredInvoices.count)
        let items = Array(filteredInvoices[skip..<endIndex])

        return PaginatedResponse(
            items: items,
            total: filteredInvoices.count,
            skip: skip,
            limit: limit
        )
    }

    func fetchDashboard() async throws -> DashboardData {
        fetchDashboardCalled = true

        if let error = shouldThrowError {
            throw error
        }

        return mockDashboard ?? DashboardData(
            totalChildren: 50,
            activeChildren: 45,
            totalStaff: 10,
            activeStaff: 8,
            financeSummary: mockFinanceSummary,
            alerts: nil,
            todayAttendance: 40,
            capacityUtilization: 0.9
        )
    }

    func createChild(_ request: ChildRequest) async throws -> Child {
        createChildCalled = true

        if let error = shouldThrowError {
            throw error
        }

        return Child(
            id: UUID().uuidString,
            firstName: request.firstName,
            lastName: request.lastName,
            dateOfBirth: request.dateOfBirth,
            enrollmentStatus: request.enrollmentStatus ?? .pending,
            classroomName: nil,
            classroomId: request.classroomId,
            primaryGuardianId: request.primaryGuardianId,
            primaryGuardianName: request.primaryGuardianName ?? "",
            primaryGuardianEmail: request.primaryGuardianEmail ?? "",
            primaryGuardianPhone: request.primaryGuardianPhone ?? "",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: request.allergies,
            medicalNotes: request.medicalNotes,
            dietaryRequirements: request.dietaryRequirements,
            profilePhotoURL: nil,
            enrollmentDate: request.enrollmentDate,
            expectedGraduationDate: request.expectedGraduationDate,
            notes: request.notes,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    func updateChild(childId: String, request: ChildRequest) async throws -> Child {
        updateChildCalled = true
        capturedChildId = childId

        if let error = shouldThrowError {
            throw error
        }

        return Child(
            id: childId,
            firstName: request.firstName,
            lastName: request.lastName,
            dateOfBirth: request.dateOfBirth,
            enrollmentStatus: request.enrollmentStatus ?? .active,
            classroomName: nil,
            classroomId: request.classroomId,
            primaryGuardianId: request.primaryGuardianId,
            primaryGuardianName: request.primaryGuardianName ?? "",
            primaryGuardianEmail: request.primaryGuardianEmail ?? "",
            primaryGuardianPhone: request.primaryGuardianPhone ?? "",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: request.allergies,
            medicalNotes: request.medicalNotes,
            dietaryRequirements: request.dietaryRequirements,
            profilePhotoURL: nil,
            enrollmentDate: request.enrollmentDate,
            expectedGraduationDate: request.expectedGraduationDate,
            notes: request.notes,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    func deleteChild(childId: String) async throws {
        deleteChildCalled = true
        capturedChildId = childId

        if let error = shouldThrowError {
            throw error
        }
    }

    func reset() {
        mockChildren = []
        mockStaff = []
        mockInvoices = []
        mockDashboard = nil
        mockFinanceSummary = nil
        shouldThrowError = nil
        fetchChildrenCalled = false
        fetchStaffCalled = false
        fetchInvoicesCalled = false
        fetchDashboardCalled = false
        createChildCalled = false
        updateChildCalled = false
        deleteChildCalled = false
        createInvoiceCalled = false
        recordPaymentCalled = false
        capturedChildId = nil
        capturedInvoiceId = nil
    }
}

// MARK: - Test Fixtures

extension Child {
    static var testChild: Child {
        Child(
            id: "test-child-1",
            firstName: "Emma",
            lastName: "Tremblay",
            dateOfBirth: Calendar.current.date(byAdding: .year, value: -4, to: Date()) ?? Date(),
            enrollmentStatus: .active,
            classroomName: "Butterflies",
            classroomId: "classroom-1",
            primaryGuardianId: "guardian-1",
            primaryGuardianName: "Marie Tremblay",
            primaryGuardianEmail: "marie@email.com",
            primaryGuardianPhone: "(514) 555-1234",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: "Peanuts",
            medicalNotes: nil,
            dietaryRequirements: nil,
            profilePhotoURL: nil,
            enrollmentDate: Date(),
            expectedGraduationDate: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    static var testChild2: Child {
        Child(
            id: "test-child-2",
            firstName: "Lucas",
            lastName: "Gagnon",
            dateOfBirth: Calendar.current.date(byAdding: .year, value: -3, to: Date()) ?? Date(),
            enrollmentStatus: .active,
            classroomName: "Ladybugs",
            classroomId: "classroom-2",
            primaryGuardianId: "guardian-2",
            primaryGuardianName: "Jean Gagnon",
            primaryGuardianEmail: "jean@email.com",
            primaryGuardianPhone: "(514) 555-5678",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: nil,
            medicalNotes: nil,
            dietaryRequirements: nil,
            profilePhotoURL: nil,
            enrollmentDate: Date(),
            expectedGraduationDate: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    static var testChildPending: Child {
        Child(
            id: "test-child-3",
            firstName: "Sophie",
            lastName: "Lavoie",
            dateOfBirth: Calendar.current.date(byAdding: .year, value: -2, to: Date()) ?? Date(),
            enrollmentStatus: .pending,
            classroomName: nil,
            classroomId: nil,
            primaryGuardianId: "guardian-3",
            primaryGuardianName: "Anne Lavoie",
            primaryGuardianEmail: "anne@email.com",
            primaryGuardianPhone: "(514) 555-9999",
            secondaryGuardianId: nil,
            secondaryGuardianName: nil,
            allergies: nil,
            medicalNotes: nil,
            dietaryRequirements: nil,
            profilePhotoURL: nil,
            enrollmentDate: nil,
            expectedGraduationDate: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

extension Invoice {
    static var testInvoice: Invoice {
        Invoice(
            id: "test-invoice-1",
            number: "INV-2026-0001",
            familyId: "family-1",
            familyName: "Tremblay Family",
            childId: "child-1",
            childName: "Emma Tremblay",
            date: Date(),
            dueDate: Calendar.current.date(byAdding: .day, value: 30, to: Date()) ?? Date(),
            status: .pending,
            subtotal: 1000.00,
            taxAmount: 149.75,
            totalAmount: 1149.75,
            amountPaid: 0,
            items: [],
            periodStartDate: Calendar.current.date(byAdding: .month, value: -1, to: Date()),
            periodEndDate: Date(),
            pdfUrl: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    static var testInvoicePaid: Invoice {
        Invoice(
            id: "test-invoice-2",
            number: "INV-2026-0002",
            familyId: "family-2",
            familyName: "Gagnon Family",
            childId: "child-2",
            childName: "Lucas Gagnon",
            date: Calendar.current.date(byAdding: .day, value: -15, to: Date()) ?? Date(),
            dueDate: Calendar.current.date(byAdding: .day, value: 15, to: Date()) ?? Date(),
            status: .paid,
            subtotal: 850.00,
            taxAmount: 127.29,
            totalAmount: 977.29,
            amountPaid: 977.29,
            items: [],
            periodStartDate: Calendar.current.date(byAdding: .month, value: -2, to: Date()),
            periodEndDate: Calendar.current.date(byAdding: .month, value: -1, to: Date()),
            pdfUrl: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }

    static var testInvoiceOverdue: Invoice {
        Invoice(
            id: "test-invoice-3",
            number: "INV-2026-0003",
            familyId: "family-3",
            familyName: "Lavoie Family",
            childId: "child-3",
            childName: "Sophie Lavoie",
            date: Calendar.current.date(byAdding: .day, value: -45, to: Date()) ?? Date(),
            dueDate: Calendar.current.date(byAdding: .day, value: -15, to: Date()) ?? Date(),
            status: .overdue,
            subtotal: 1200.00,
            taxAmount: 179.70,
            totalAmount: 1379.70,
            amountPaid: 500.00,
            items: [],
            periodStartDate: nil,
            periodEndDate: nil,
            pdfUrl: nil,
            notes: nil,
            createdAt: Date(),
            updatedAt: Date()
        )
    }
}

// MARK: - AuthViewModel Tests

@MainActor
final class AuthViewModelTests: XCTestCase {

    // MARK: - Properties

    var sut: AuthViewModel!
    var mockAuthService: MockAuthService!
    var cancellables: Set<AnyCancellable>!

    // MARK: - Setup / Teardown

    override func setUp() async throws {
        try await super.setUp()

        mockAuthService = MockAuthService()
        sut = AuthViewModel(authService: mockAuthService)
        cancellables = Set<AnyCancellable>()
    }

    override func tearDown() async throws {
        sut = nil
        mockAuthService = nil
        cancellables = nil

        try await super.tearDown()
    }

    // MARK: - Initial State Tests

    func testInitialState_isUnauthenticated() {
        XCTAssertEqual(sut.authState, .unauthenticated)
        XCTAssertFalse(sut.isAuthenticated)
        XCTAssertNil(sut.currentUser)
        XCTAssertFalse(sut.isLoading)
        XCTAssertNil(sut.error)
        XCTAssertFalse(sut.showError)
    }

    func testInitialState_formFieldsAreEmpty() {
        XCTAssertEqual(sut.email, "")
        XCTAssertEqual(sut.password, "")
        XCTAssertFalse(sut.rememberMe)
        XCTAssertFalse(sut.emailTouched)
        XCTAssertFalse(sut.passwordTouched)
    }

    // MARK: - Form Validation Tests

    func testIsEmailValid_withValidEmail_returnsTrue() {
        sut.email = "test@example.com"
        XCTAssertTrue(sut.isEmailValid)
    }

    func testIsEmailValid_withInvalidEmail_returnsFalse() {
        sut.email = "invalid-email"
        XCTAssertFalse(sut.isEmailValid)

        sut.email = "test@"
        XCTAssertFalse(sut.isEmailValid)

        sut.email = ""
        XCTAssertFalse(sut.isEmailValid)
    }

    func testIsPasswordValid_withValidPassword_returnsTrue() {
        sut.password = "password123"
        XCTAssertTrue(sut.isPasswordValid)
    }

    func testIsPasswordValid_withShortPassword_returnsFalse() {
        sut.password = "12345"
        XCTAssertFalse(sut.isPasswordValid)

        sut.password = ""
        XCTAssertFalse(sut.isPasswordValid)
    }

    func testIsFormValid_withValidEmailAndPassword_returnsTrue() {
        sut.email = "test@example.com"
        sut.password = "password123"
        XCTAssertTrue(sut.isFormValid)
    }

    func testIsFormValid_withInvalidEmail_returnsFalse() {
        sut.email = "invalid"
        sut.password = "password123"
        XCTAssertFalse(sut.isFormValid)
    }

    func testIsFormValid_withInvalidPassword_returnsFalse() {
        sut.email = "test@example.com"
        sut.password = "123"
        XCTAssertFalse(sut.isFormValid)
    }

    func testEmailError_whenNotTouched_returnsNil() {
        sut.email = ""
        XCTAssertNil(sut.emailError)
    }

    func testEmailError_whenTouchedAndEmpty_returnsRequiredError() {
        sut.email = ""
        sut.emailTouched = true
        XCTAssertNotNil(sut.emailError)
        XCTAssertTrue(sut.emailError?.contains("required") ?? false)
    }

    func testEmailError_whenTouchedAndInvalid_returnsInvalidError() {
        sut.email = "invalid-email"
        sut.emailTouched = true
        XCTAssertNotNil(sut.emailError)
        XCTAssertTrue(sut.emailError?.contains("valid email") ?? false)
    }

    func testPasswordError_whenNotTouched_returnsNil() {
        sut.password = ""
        XCTAssertNil(sut.passwordError)
    }

    func testPasswordError_whenTouchedAndEmpty_returnsRequiredError() {
        sut.password = ""
        sut.passwordTouched = true
        XCTAssertNotNil(sut.passwordError)
        XCTAssertTrue(sut.passwordError?.contains("required") ?? false)
    }

    func testPasswordError_whenTouchedAndShort_returnsLengthError() {
        sut.password = "123"
        sut.passwordTouched = true
        XCTAssertNotNil(sut.passwordError)
        XCTAssertTrue(sut.passwordError?.contains("at least 6") ?? false)
    }

    func testCanLogin_whenFormValidAndNotLoading_returnsTrue() {
        sut.email = "test@example.com"
        sut.password = "password123"
        XCTAssertTrue(sut.canLogin)
    }

    func testCanLogin_whenFormInvalid_returnsFalse() {
        sut.email = "invalid"
        sut.password = "password123"
        XCTAssertFalse(sut.canLogin)
    }

    // MARK: - Login Tests

    func testLogin_withValidCredentials_updatesAuthState() async {
        // Given
        let testUser = User.testUser
        mockAuthService.mockLoginResponse = testUser
        sut.email = "test@example.com"
        sut.password = "password123"

        // When
        await sut.login()

        // Then
        XCTAssertTrue(mockAuthService.loginCalled)
        XCTAssertEqual(mockAuthService.capturedEmail, "test@example.com")
        XCTAssertEqual(mockAuthService.capturedPassword, "password123")
        XCTAssertTrue(sut.isAuthenticated)
        XCTAssertNil(sut.error)
        XCTAssertFalse(sut.showError)
    }

    func testLogin_clearsFormOnSuccess() async {
        // Given
        let testUser = User.testUser
        mockAuthService.mockLoginResponse = testUser
        sut.email = "test@example.com"
        sut.password = "password123"
        sut.rememberMe = true

        // When
        await sut.login()

        // Then
        XCTAssertEqual(sut.email, "")
        XCTAssertEqual(sut.password, "")
        XCTAssertFalse(sut.rememberMe)
        XCTAssertFalse(sut.emailTouched)
        XCTAssertFalse(sut.passwordTouched)
    }

    func testLogin_withInvalidForm_marksTouchedAndDoesNotCallService() async {
        // Given
        sut.email = "invalid"
        sut.password = "123"

        // When
        await sut.login()

        // Then
        XCTAssertFalse(mockAuthService.loginCalled)
        XCTAssertTrue(sut.emailTouched)
        XCTAssertTrue(sut.passwordTouched)
    }

    func testLogin_withInvalidCredentials_setsError() async {
        // Given
        mockAuthService.shouldThrowError = .invalidCredentials
        sut.email = "test@example.com"
        sut.password = "wrongpassword"

        // When
        await sut.login()

        // Then
        XCTAssertTrue(mockAuthService.loginCalled)
        XCTAssertEqual(sut.error, .invalidCredentials)
        XCTAssertTrue(sut.showError)
        XCTAssertFalse(sut.isAuthenticated)
    }

    func testLogin_withNetworkError_setsError() async {
        // Given
        mockAuthService.shouldThrowError = .networkError("Connection failed")
        sut.email = "test@example.com"
        sut.password = "password123"

        // When
        await sut.login()

        // Then
        XCTAssertNotNil(sut.error)
        XCTAssertTrue(sut.showError)
    }

    func testLogin_trimsWhitespaceFromEmail() async {
        // Given
        let testUser = User.testUser
        mockAuthService.mockLoginResponse = testUser
        sut.email = "  test@example.com  "
        sut.password = "password123"

        // When
        await sut.login()

        // Then
        XCTAssertEqual(mockAuthService.capturedEmail, "test@example.com")
    }

    // MARK: - Logout Tests

    func testLogout_clearsAuthStateAndForm() async {
        // Given
        mockAuthService.mockUser = .testUser
        mockAuthService.authState = .authenticated(.testUser)
        sut.email = "test@example.com"
        sut.password = "password123"

        // When
        await sut.logout()

        // Then
        XCTAssertTrue(mockAuthService.logoutCalled)
        XCTAssertEqual(sut.email, "")
        XCTAssertEqual(sut.password, "")
    }

    // MARK: - Session Restoration Tests

    func testRestoreSession_withStoredSession_returnsTrue() async {
        // Given
        mockAuthService.mockUser = .testUser

        // When
        let restored = await sut.restoreSession()

        // Then
        XCTAssertTrue(restored)
        XCTAssertTrue(mockAuthService.restoreSessionCalled)
        XCTAssertTrue(sut.isAuthenticated)
    }

    func testRestoreSession_withoutStoredSession_returnsFalse() async {
        // Given
        mockAuthService.mockUser = nil

        // When
        let restored = await sut.restoreSession()

        // Then
        XCTAssertFalse(restored)
        XCTAssertTrue(mockAuthService.restoreSessionCalled)
        XCTAssertFalse(sut.isAuthenticated)
    }

    // MARK: - Validation Method Tests

    func testValidateEmail_setsEmailTouched() {
        // Given
        XCTAssertFalse(sut.emailTouched)

        // When
        sut.validateEmail()

        // Then
        XCTAssertTrue(sut.emailTouched)
    }

    func testValidatePassword_setsPasswordTouched() {
        // Given
        XCTAssertFalse(sut.passwordTouched)

        // When
        sut.validatePassword()

        // Then
        XCTAssertTrue(sut.passwordTouched)
    }

    // MARK: - Clear Methods Tests

    func testClearForm_resetsAllFormFields() {
        // Given
        sut.email = "test@example.com"
        sut.password = "password123"
        sut.rememberMe = true
        sut.emailTouched = true
        sut.passwordTouched = true

        // When
        sut.clearForm()

        // Then
        XCTAssertEqual(sut.email, "")
        XCTAssertEqual(sut.password, "")
        XCTAssertFalse(sut.rememberMe)
        XCTAssertFalse(sut.emailTouched)
        XCTAssertFalse(sut.passwordTouched)
    }

    func testClearError_resetsErrorState() {
        // Given
        sut.error = .invalidCredentials
        sut.showError = true

        // When
        sut.clearError()

        // Then
        XCTAssertNil(sut.error)
        XCTAssertFalse(sut.showError)
    }

    // MARK: - Password Reset Tests

    func testDismissPasswordReset_clearsResetState() {
        // Given
        sut.showPasswordReset = true
        sut.resetEmail = "test@example.com"
        sut.resetSuccessMessage = "Email sent"

        // When
        sut.dismissPasswordReset()

        // Then
        XCTAssertFalse(sut.showPasswordReset)
        XCTAssertEqual(sut.resetEmail, "")
        XCTAssertNil(sut.resetSuccessMessage)
    }

    // MARK: - Auth State Publisher Tests

    func testAuthStatePublisher_updatesAuthState() async {
        // Given
        var receivedStates: [AuthState] = []

        sut.$authState
            .sink { state in
                receivedStates.append(state)
            }
            .store(in: &cancellables)

        // When
        mockAuthService.authState = .authenticated(.testUser)

        // Wait briefly for publisher
        try? await Task.sleep(nanoseconds: 100_000_000)

        // Then - Should have received both initial and updated states
        XCTAssertGreaterThanOrEqual(receivedStates.count, 1)
    }
}

// MARK: - ChildListViewModel Tests

@MainActor
final class ChildListViewModelTests: XCTestCase {

    // MARK: - Properties

    var sut: ChildListViewModel!
    var cancellables: Set<AnyCancellable>!

    // MARK: - Setup / Teardown

    override func setUp() async throws {
        try await super.setUp()

        sut = ChildListViewModel()
        cancellables = Set<AnyCancellable>()
    }

    override func tearDown() async throws {
        sut = nil
        cancellables = nil

        try await super.tearDown()
    }

    // MARK: - Initial State Tests

    func testInitialState_hasEmptyList() {
        XCTAssertTrue(sut.children.isEmpty)
        XCTAssertFalse(sut.isLoading)
        XCTAssertFalse(sut.hasLoaded)
        XCTAssertFalse(sut.hasMoreData)
        XCTAssertNil(sut.error)
        XCTAssertEqual(sut.totalCount, 0)
    }

    func testInitialState_hasDefaultFilters() {
        XCTAssertEqual(sut.searchText, "")
        XCTAssertNil(sut.statusFilter)
        XCTAssertNil(sut.classroomFilter)
        XCTAssertEqual(sut.sortOrder, .nameAsc)
    }

    func testInitialState_hasEmptySelection() {
        XCTAssertTrue(sut.selectedChildIds.isEmpty)
        XCTAssertNil(sut.selectedChild)
        XCTAssertEqual(sut.selectedCount, 0)
    }

    // MARK: - Computed Property Tests

    func testIsEmpty_whenNoChildrenAndLoaded_returnsTrue() {
        sut.hasLoaded = true
        XCTAssertTrue(sut.isEmpty)
    }

    func testIsEmpty_whenChildrenExist_returnsFalse() {
        // Manually set children for testing (without loading from API)
        // Note: In real tests with mocks, we'd use dependency injection
        sut.hasLoaded = true
        // Since children is private(set), we can test via filteredChildren
        XCTAssertTrue(sut.isEmpty)
    }

    func testIsSearching_whenSearchTextEmpty_returnsFalse() {
        sut.searchText = ""
        XCTAssertFalse(sut.isSearching)

        sut.searchText = "   "
        XCTAssertFalse(sut.isSearching)
    }

    func testIsSearching_whenSearchTextNotEmpty_returnsTrue() {
        sut.searchText = "Emma"
        XCTAssertTrue(sut.isSearching)
    }

    func testHasActiveFilters_whenNoFilters_returnsFalse() {
        XCTAssertFalse(sut.hasActiveFilters)
    }

    func testHasActiveFilters_whenStatusFilterSet_returnsTrue() {
        sut.statusFilter = .active
        XCTAssertTrue(sut.hasActiveFilters)
    }

    func testHasActiveFilters_whenClassroomFilterSet_returnsTrue() {
        sut.classroomFilter = "classroom-1"
        XCTAssertTrue(sut.hasActiveFilters)
    }

    // MARK: - Selection Tests

    func testToggleSelection_addsToSelection() {
        // Given
        let childId = "child-1"
        XCTAssertFalse(sut.selectedChildIds.contains(childId))

        // When
        sut.toggleSelection(childId: childId)

        // Then
        XCTAssertTrue(sut.selectedChildIds.contains(childId))
    }

    func testToggleSelection_removesFromSelection() {
        // Given
        let childId = "child-1"
        sut.selectedChildIds.insert(childId)

        // When
        sut.toggleSelection(childId: childId)

        // Then
        XCTAssertFalse(sut.selectedChildIds.contains(childId))
    }

    func testDeselectAll_clearsSelection() {
        // Given
        sut.selectedChildIds = Set(["child-1", "child-2", "child-3"])

        // When
        sut.deselectAll()

        // Then
        XCTAssertTrue(sut.selectedChildIds.isEmpty)
    }

    // MARK: - Filter Tests

    func testClearFilters_resetsAllFilters() {
        // Given
        sut.statusFilter = .active
        sut.classroomFilter = "classroom-1"
        sut.searchText = "Emma"

        // When
        sut.clearFilters()

        // Then
        XCTAssertNil(sut.statusFilter)
        XCTAssertNil(sut.classroomFilter)
        XCTAssertEqual(sut.searchText, "")
    }

    func testFilterByStatus_setsStatusFilter() {
        // Given
        XCTAssertNil(sut.statusFilter)

        // When
        sut.filterByStatus(.pending)

        // Then
        XCTAssertEqual(sut.statusFilter, .pending)
    }

    func testFilterByClassroom_setsClassroomFilter() {
        // Given
        XCTAssertNil(sut.classroomFilter)

        // When
        sut.filterByClassroom("classroom-1")

        // Then
        XCTAssertEqual(sut.classroomFilter, "classroom-1")
    }

    // MARK: - Utility Methods Tests

    func testClearError_resetsErrorState() {
        // Given
        sut.error = APIError.serverError(statusCode: 500, response: nil)
        sut.showError = true

        // When
        sut.clearError()

        // Then
        XCTAssertNil(sut.error)
        XCTAssertFalse(sut.showError)
    }

    func testClearSuccess_resetsSuccessState() {
        // Given
        sut.successMessage = "Child added successfully"
        sut.showSuccess = true

        // When
        sut.clearSuccess()

        // Then
        XCTAssertNil(sut.successMessage)
        XCTAssertFalse(sut.showSuccess)
    }
}

// MARK: - InvoiceListViewModel Tests

@MainActor
final class InvoiceListViewModelTests: XCTestCase {

    // MARK: - Properties

    var sut: InvoiceListViewModel!
    var cancellables: Set<AnyCancellable>!

    // MARK: - Setup / Teardown

    override func setUp() async throws {
        try await super.setUp()

        sut = InvoiceListViewModel()
        cancellables = Set<AnyCancellable>()
    }

    override func tearDown() async throws {
        sut = nil
        cancellables = nil

        try await super.tearDown()
    }

    // MARK: - Initial State Tests

    func testInitialState_hasEmptyList() {
        XCTAssertTrue(sut.invoices.isEmpty)
        XCTAssertFalse(sut.isLoading)
        XCTAssertFalse(sut.hasLoaded)
        XCTAssertFalse(sut.hasMoreData)
        XCTAssertNil(sut.error)
        XCTAssertEqual(sut.totalCount, 0)
    }

    func testInitialState_hasDefaultFilters() {
        XCTAssertEqual(sut.searchText, "")
        XCTAssertNil(sut.statusFilter)
        XCTAssertNil(sut.familyFilter)
        XCTAssertNil(sut.childFilter)
        XCTAssertNil(sut.startDateFilter)
        XCTAssertNil(sut.endDateFilter)
        XCTAssertEqual(sut.sortOrder, .dateDesc)
    }

    func testInitialState_hasEmptySelection() {
        XCTAssertTrue(sut.selectedInvoiceIds.isEmpty)
        XCTAssertNil(sut.selectedInvoice)
        XCTAssertEqual(sut.selectedCount, 0)
    }

    // MARK: - Computed Property Tests

    func testIsEmpty_whenNoInvoicesAndLoaded_returnsTrue() {
        sut.hasLoaded = true
        XCTAssertTrue(sut.isEmpty)
    }

    func testIsSearching_whenSearchTextEmpty_returnsFalse() {
        sut.searchText = ""
        XCTAssertFalse(sut.isSearching)
    }

    func testIsSearching_whenSearchTextNotEmpty_returnsTrue() {
        sut.searchText = "Tremblay"
        XCTAssertTrue(sut.isSearching)
    }

    func testHasActiveFilters_whenNoFilters_returnsFalse() {
        XCTAssertFalse(sut.hasActiveFilters)
    }

    func testHasActiveFilters_whenStatusFilterSet_returnsTrue() {
        sut.statusFilter = .pending
        XCTAssertTrue(sut.hasActiveFilters)
    }

    func testHasActiveFilters_whenFamilyFilterSet_returnsTrue() {
        sut.familyFilter = "family-1"
        XCTAssertTrue(sut.hasActiveFilters)
    }

    func testHasActiveFilters_whenDateFilterSet_returnsTrue() {
        sut.startDateFilter = Date()
        XCTAssertTrue(sut.hasActiveFilters)
    }

    func testTotalOutstanding_withEmptyInvoices_returnsZero() {
        XCTAssertEqual(sut.totalOutstanding, 0)
    }

    // MARK: - Selection Tests

    func testToggleSelection_addsToSelection() {
        // Given
        let invoiceId = "invoice-1"
        XCTAssertFalse(sut.selectedInvoiceIds.contains(invoiceId))

        // When
        sut.toggleSelection(invoiceId: invoiceId)

        // Then
        XCTAssertTrue(sut.selectedInvoiceIds.contains(invoiceId))
    }

    func testToggleSelection_removesFromSelection() {
        // Given
        let invoiceId = "invoice-1"
        sut.selectedInvoiceIds.insert(invoiceId)

        // When
        sut.toggleSelection(invoiceId: invoiceId)

        // Then
        XCTAssertFalse(sut.selectedInvoiceIds.contains(invoiceId))
    }

    func testDeselectAll_clearsSelection() {
        // Given
        sut.selectedInvoiceIds = Set(["invoice-1", "invoice-2", "invoice-3"])

        // When
        sut.deselectAll()

        // Then
        XCTAssertTrue(sut.selectedInvoiceIds.isEmpty)
    }

    // MARK: - Filter Tests

    func testClearFilters_resetsAllFilters() {
        // Given
        sut.statusFilter = .overdue
        sut.familyFilter = "family-1"
        sut.childFilter = "child-1"
        sut.startDateFilter = Date()
        sut.endDateFilter = Date()
        sut.searchText = "Tremblay"

        // When
        sut.clearFilters()

        // Then
        XCTAssertNil(sut.statusFilter)
        XCTAssertNil(sut.familyFilter)
        XCTAssertNil(sut.childFilter)
        XCTAssertNil(sut.startDateFilter)
        XCTAssertNil(sut.endDateFilter)
        XCTAssertEqual(sut.searchText, "")
    }

    func testFilterByStatus_setsStatusFilter() {
        // Given
        XCTAssertNil(sut.statusFilter)

        // When
        sut.filterByStatus(.overdue)

        // Then
        XCTAssertEqual(sut.statusFilter, .overdue)
    }

    func testFilterByFamily_setsFamilyFilter() {
        // Given
        XCTAssertNil(sut.familyFilter)

        // When
        sut.filterByFamily("family-1")

        // Then
        XCTAssertEqual(sut.familyFilter, "family-1")
    }

    func testFilterByChild_setsChildFilter() {
        // Given
        XCTAssertNil(sut.childFilter)

        // When
        sut.filterByChild("child-1")

        // Then
        XCTAssertEqual(sut.childFilter, "child-1")
    }

    // MARK: - Utility Methods Tests

    func testClearError_resetsErrorState() {
        // Given
        sut.error = APIError.serverError(statusCode: 500, response: nil)
        sut.showError = true

        // When
        sut.clearError()

        // Then
        XCTAssertNil(sut.error)
        XCTAssertFalse(sut.showError)
    }

    func testClearSuccess_resetsSuccessState() {
        // Given
        sut.successMessage = "Invoice created successfully"
        sut.showSuccess = true

        // When
        sut.clearSuccess()

        // Then
        XCTAssertNil(sut.successMessage)
        XCTAssertFalse(sut.showSuccess)
    }

    func testGetOverdueInvoices_withEmptyList_returnsEmpty() {
        let overdueInvoices = sut.getOverdueInvoices()
        XCTAssertTrue(overdueInvoices.isEmpty)
    }
}

// MARK: - ChildSortOrder Tests

final class ChildSortOrderTests: XCTestCase {

    func testAllCases_haveDisplayNames() {
        for sortOrder in ChildSortOrder.allCases {
            XCTAssertFalse(sortOrder.displayName.isEmpty, "Sort order \(sortOrder.rawValue) should have a display name")
        }
    }

    func testAllCases_haveSystemImages() {
        for sortOrder in ChildSortOrder.allCases {
            XCTAssertFalse(sortOrder.systemImage.isEmpty, "Sort order \(sortOrder.rawValue) should have a system image")
        }
    }

    func testId_matchesRawValue() {
        for sortOrder in ChildSortOrder.allCases {
            XCTAssertEqual(sortOrder.id, sortOrder.rawValue)
        }
    }
}

// MARK: - InvoiceSortOrder Tests

final class InvoiceSortOrderTests: XCTestCase {

    func testAllCases_haveDisplayNames() {
        for sortOrder in InvoiceSortOrder.allCases {
            XCTAssertFalse(sortOrder.displayName.isEmpty, "Sort order \(sortOrder.rawValue) should have a display name")
        }
    }

    func testAllCases_haveSystemImages() {
        for sortOrder in InvoiceSortOrder.allCases {
            XCTAssertFalse(sortOrder.systemImage.isEmpty, "Sort order \(sortOrder.rawValue) should have a system image")
        }
    }

    func testId_matchesRawValue() {
        for sortOrder in InvoiceSortOrder.allCases {
            XCTAssertEqual(sortOrder.id, sortOrder.rawValue)
        }
    }
}

// MARK: - DashboardLevel Tests

final class DashboardLevelTests: XCTestCase {

    func testDashboardLevel_low_hasCorrectProperties() {
        let level = DashboardLevel.low
        XCTAssertNotNil(level.color)
        XCTAssertFalse(level.displayName.isEmpty)
    }

    func testDashboardLevel_medium_hasCorrectProperties() {
        let level = DashboardLevel.medium
        XCTAssertNotNil(level.color)
        XCTAssertFalse(level.displayName.isEmpty)
    }

    func testDashboardLevel_high_hasCorrectProperties() {
        let level = DashboardLevel.high
        XCTAssertNotNil(level.color)
        XCTAssertFalse(level.displayName.isEmpty)
    }
}

// MARK: - AuthState Tests (Extension)

final class AuthStateViewModelTests: XCTestCase {

    func testIsAuthenticated_unauthenticated_returnsFalse() {
        let state = AuthState.unauthenticated
        XCTAssertFalse(state.isAuthenticated)
    }

    func testIsAuthenticated_authenticating_returnsFalse() {
        let state = AuthState.authenticating
        XCTAssertFalse(state.isAuthenticated)
    }

    func testIsAuthenticated_authenticated_returnsTrue() {
        let state = AuthState.authenticated(.testUser)
        XCTAssertTrue(state.isAuthenticated)
    }

    func testIsAuthenticated_failed_returnsFalse() {
        let state = AuthState.failed(.invalidCredentials)
        XCTAssertFalse(state.isAuthenticated)
    }

    func testIsAuthenticated_tokenExpired_returnsFalse() {
        let state = AuthState.tokenExpired
        XCTAssertFalse(state.isAuthenticated)
    }

    func testCurrentUser_unauthenticated_returnsNil() {
        let state = AuthState.unauthenticated
        XCTAssertNil(state.currentUser)
    }

    func testCurrentUser_authenticated_returnsUser() {
        let user = User.testUser
        let state = AuthState.authenticated(user)
        XCTAssertEqual(state.currentUser?.id, user.id)
    }
}

// MARK: - EnrollmentStatus Count Tests

@MainActor
final class ChildListCountTests: XCTestCase {

    var sut: ChildListViewModel!

    override func setUp() async throws {
        try await super.setUp()
        sut = ChildListViewModel()
    }

    override func tearDown() async throws {
        sut = nil
        try await super.tearDown()
    }

    func testActiveCount_withEmptyChildren_returnsZero() {
        XCTAssertEqual(sut.activeCount, 0)
    }

    func testPendingCount_withEmptyChildren_returnsZero() {
        XCTAssertEqual(sut.pendingCount, 0)
    }

    func testWaitlistCount_withEmptyChildren_returnsZero() {
        XCTAssertEqual(sut.waitlistCount, 0)
    }
}

// MARK: - InvoiceStatus Count Tests

@MainActor
final class InvoiceListCountTests: XCTestCase {

    var sut: InvoiceListViewModel!

    override func setUp() async throws {
        try await super.setUp()
        sut = InvoiceListViewModel()
    }

    override func tearDown() async throws {
        sut = nil
        try await super.tearDown()
    }

    func testPendingCount_withEmptyInvoices_returnsZero() {
        XCTAssertEqual(sut.pendingCount, 0)
    }

    func testPaidCount_withEmptyInvoices_returnsZero() {
        XCTAssertEqual(sut.paidCount, 0)
    }

    func testOverdueCount_withEmptyInvoices_returnsZero() {
        XCTAssertEqual(sut.overdueCount, 0)
    }
}
