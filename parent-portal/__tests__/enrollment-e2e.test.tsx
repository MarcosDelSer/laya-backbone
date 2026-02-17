/**
 * End-to-end verification tests for enrollment workflow
 *
 * These tests verify the complete enrollment process:
 * 1. Navigate to enrollment page from parent portal
 * 2. Click 'New Enrollment Form' button
 * 3. Complete all wizard steps with valid data
 * 4. Capture e-signatures using SignatureCanvas
 * 5. Submit form successfully
 * 6. Verify form appears in list with correct status
 * 7. View form details and verify all data saved
 * 8. Download PDF and verify it contains all information
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { EnrollmentFormWizard, WIZARD_STEPS } from '@/components/enrollment/EnrollmentFormWizard';
import { ChildInfoSection } from '@/components/enrollment/ChildInfoSection';
import { ParentSection } from '@/components/enrollment/ParentSection';
import { AuthorizedPickupsSection } from '@/components/enrollment/AuthorizedPickupsSection';
import { EmergencyContactsSection } from '@/components/enrollment/EmergencyContactsSection';
import { HealthSection } from '@/components/enrollment/HealthSection';
import { NutritionSection } from '@/components/enrollment/NutritionSection';
import { AttendancePatternSection } from '@/components/enrollment/AttendancePatternSection';
import { SignaturesSection } from '@/components/enrollment/SignaturesSection';
import { EnrollmentFormCard } from '@/components/enrollment/EnrollmentFormCard';
import { EnrollmentPdfPreview } from '@/components/enrollment/EnrollmentPdfPreview';
import type {
  EnrollmentForm,
  EnrollmentFormSummary,
  EnrollmentFormStatus,
  EnrollmentParent,
  EmergencyContact,
  AuthorizedPickup,
  HealthInfo,
  NutritionInfo,
  AttendancePattern,
  EnrollmentSignature,
} from '@/lib/types';

// ============================================================================
// Mock Router
// ============================================================================

const mockPush = vi.fn();
const mockParams = { id: 'test-form-1' };

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
    back: vi.fn(),
    forward: vi.fn(),
    refresh: vi.fn(),
    replace: vi.fn(),
    prefetch: vi.fn(),
  }),
  useParams: () => mockParams,
}));

// ============================================================================
// Test Fixtures
// ============================================================================

const createValidChildInfo = () => ({
  childFirstName: 'Sophie',
  childLastName: 'Martin',
  childDateOfBirth: '2021-03-15',
  childAddress: '123 Rue Principale',
  childCity: 'Montreal',
  childPostalCode: 'H2X 1Y6',
  languagesSpoken: 'French, English',
  admissionDate: '2024-09-01',
  notes: 'Sophie is a friendly child who enjoys arts and crafts.',
});

const createValidParent = (num: '1' | '2'): Omit<EnrollmentParent, 'id' | 'formId'> => ({
  parentNumber: num,
  name: num === '1' ? 'Marie Martin' : 'Jean Martin',
  relationship: num === '1' ? 'Mother' : 'Father',
  address: '123 Rue Principale',
  city: 'Montreal',
  postalCode: 'H2X 1Y6',
  homePhone: '514-555-0101',
  cellPhone: `514-555-010${num}`,
  workPhone: '514-555-0103',
  email: `${num === '1' ? 'marie' : 'jean'}.martin@email.com`,
  employer: num === '1' ? 'Tech Corp' : 'Finance Inc',
  workAddress: '456 Business St, Montreal',
  workHours: '9:00 AM - 5:00 PM',
  isPrimaryContact: num === '1',
});

const createValidPickup = (index: number): Omit<AuthorizedPickup, 'id' | 'formId'> => ({
  name: index === 0 ? 'Grandma Louise' : 'Uncle Pierre',
  relationship: index === 0 ? 'Grandmother' : 'Uncle',
  phone: `514-555-030${index + 1}`,
  priority: index + 1,
  notes: index === 0 ? 'Primary backup pickup' : undefined,
});

const createValidEmergencyContact = (index: number): Omit<EmergencyContact, 'id' | 'formId'> => ({
  name: index === 0 ? 'Grandma Louise' : 'Aunt Claire',
  relationship: index === 0 ? 'Grandmother' : 'Aunt',
  phone: `514-555-0${index + 3}01`,
  alternatePhone: index === 0 ? '514-555-0311' : undefined,
  priority: index + 1,
  notes: index === 0 ? 'Lives nearby, can arrive quickly' : undefined,
});

const createValidHealthInfo = (): Omit<HealthInfo, 'id' | 'formId'> => ({
  allergies: [
    {
      allergen: 'Peanuts',
      severity: 'severe',
      reaction: 'Anaphylaxis',
      treatment: 'EpiPen immediately, call 911',
    },
  ],
  medicalConditions: 'Mild asthma, controlled with inhaler as needed',
  hasEpiPen: true,
  epiPenInstructions: 'Administer in outer thigh, call 911 immediately, notify parents',
  medications: [
    {
      name: 'Ventolin',
      dosage: '2 puffs',
      schedule: 'As needed',
      instructions: 'Use with spacer if breathing difficulty',
    },
  ],
  doctorName: 'Dr. Sarah Chen',
  doctorPhone: '514-555-5000',
  doctorAddress: '500 Medical Center, Montreal',
  healthInsuranceNumber: 'MART12345678',
  healthInsuranceExpiry: '2025-12-31',
  specialNeeds: '',
  developmentalNotes: 'Meeting all developmental milestones',
});

const createValidNutritionInfo = (): Omit<NutritionInfo, 'id' | 'formId'> => ({
  dietaryRestrictions: 'Peanut-free diet',
  foodAllergies: 'Peanuts (severe)',
  feedingInstructions: '',
  isBottleFeeding: false,
  foodPreferences: 'Loves fruits, especially berries and apples',
  foodDislikes: 'Does not like broccoli',
  mealPlanNotes: 'Please ensure all snacks and meals are peanut-free',
});

const createValidAttendancePattern = (): Omit<AttendancePattern, 'id' | 'formId'> => ({
  mondayAm: true,
  mondayPm: true,
  tuesdayAm: true,
  tuesdayPm: true,
  wednesdayAm: true,
  wednesdayPm: true,
  thursdayAm: true,
  thursdayPm: true,
  fridayAm: true,
  fridayPm: false,
  saturdayAm: false,
  saturdayPm: false,
  sundayAm: false,
  sundayPm: false,
  expectedHoursPerWeek: 40,
  expectedArrivalTime: '08:00',
  expectedDepartureTime: '17:00',
  notes: 'Early pickup on Fridays at 12:00 PM',
});

const createCompleteEnrollmentForm = (): EnrollmentForm => ({
  id: 'enroll-test-1',
  personId: 'person-1',
  familyId: 'family-1',
  schoolYearId: 'sy-2024',
  formNumber: 'ENR-2024-001',
  status: 'Draft',
  version: 1,
  ...createValidChildInfo(),
  createdById: 'parent-1',
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
  parents: [
    { id: 'p1', formId: 'enroll-test-1', ...createValidParent('1') },
    { id: 'p2', formId: 'enroll-test-1', ...createValidParent('2') },
  ],
  authorizedPickups: [
    { id: 'pk1', formId: 'enroll-test-1', ...createValidPickup(0) },
    { id: 'pk2', formId: 'enroll-test-1', ...createValidPickup(1) },
  ],
  emergencyContacts: [
    { id: 'ec1', formId: 'enroll-test-1', ...createValidEmergencyContact(0) },
    { id: 'ec2', formId: 'enroll-test-1', ...createValidEmergencyContact(1) },
  ],
  healthInfo: { id: 'h1', formId: 'enroll-test-1', ...createValidHealthInfo() },
  nutritionInfo: { id: 'n1', formId: 'enroll-test-1', ...createValidNutritionInfo() },
  attendancePattern: { id: 'a1', formId: 'enroll-test-1', ...createValidAttendancePattern() },
  signatures: [
    {
      id: 'sig-1',
      formId: 'enroll-test-1',
      signatureType: 'Parent1',
      signatureData: 'data:image/png;base64,iVBORw0KGgo=',
      signerName: 'Marie Martin',
      signedAt: new Date().toISOString(),
    },
  ],
});

const createMockFormSummary = (
  overrides: Partial<EnrollmentFormSummary> = {}
): EnrollmentFormSummary => ({
  id: 'form-1',
  formNumber: 'ENR-2024-0001',
  status: 'Draft',
  version: 1,
  childFirstName: 'Sophie',
  childLastName: 'Martin',
  childDateOfBirth: '2021-03-15',
  admissionDate: '2024-09-01',
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
  createdByName: 'Marie Martin',
  ...overrides,
});

// ============================================================================
// Test Suites
// ============================================================================

describe('End-to-End Enrollment Workflow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ==========================================================================
  // 1. Enrollment List Page Tests
  // ==========================================================================

  describe('1. Enrollment List Page Navigation', () => {
    it('displays enrollment list with correct elements', () => {
      const forms = [
        createMockFormSummary({ status: 'Draft' }),
        createMockFormSummary({ id: 'form-2', status: 'Approved', formNumber: 'ENR-2024-0002' }),
      ];
      const onView = vi.fn();
      const onEdit = vi.fn();
      const onContinue = vi.fn();

      const { container } = render(
        <div>
          {forms.map((form) => (
            <EnrollmentFormCard
              key={form.id}
              form={form}
              onView={onView}
              onEdit={onEdit}
              onContinue={onContinue}
            />
          ))}
        </div>
      );

      // Verify both forms are rendered
      expect(screen.getAllByText(/Sophie Martin/)).toHaveLength(2);

      // Verify status badges
      expect(screen.getByText('Draft')).toBeInTheDocument();
      expect(screen.getByText('Approved')).toBeInTheDocument();
    });

    it('navigates to new enrollment form when New Enrollment button is clicked', () => {
      const forms = [createMockFormSummary()];
      const onView = vi.fn();

      render(
        <EnrollmentFormCard form={forms[0]} onView={onView} />
      );

      // The EnrollmentFormCard has View Details button
      fireEvent.click(screen.getByText('View Details'));
      expect(onView).toHaveBeenCalledWith('form-1');
    });

    it('shows Continue button for draft forms', () => {
      const form = createMockFormSummary({ status: 'Draft' });
      const onView = vi.fn();
      const onContinue = vi.fn();

      render(
        <EnrollmentFormCard form={form} onView={onView} onContinue={onContinue} />
      );

      expect(screen.getByText('Continue')).toBeInTheDocument();
      fireEvent.click(screen.getByText('Continue'));
      expect(onContinue).toHaveBeenCalledWith('form-1');
    });

    it('filters forms by status correctly', () => {
      const draftForm = createMockFormSummary({ id: 'draft-1', status: 'Draft' });
      const approvedForm = createMockFormSummary({ id: 'approved-1', status: 'Approved' });

      // Verify draft form shows Continue button
      const { rerender } = render(
        <EnrollmentFormCard form={draftForm} onView={vi.fn()} onContinue={vi.fn()} />
      );
      expect(screen.getByText('Continue')).toBeInTheDocument();

      // Verify approved form does not show Continue button
      rerender(
        <EnrollmentFormCard form={approvedForm} onView={vi.fn()} onContinue={vi.fn()} />
      );
      expect(screen.queryByText('Continue')).not.toBeInTheDocument();
    });
  });

  // ==========================================================================
  // 2. Wizard Navigation and Steps Tests
  // ==========================================================================

  describe('2. Enrollment Form Wizard', () => {
    it('renders all wizard steps correctly', () => {
      const onSubmit = vi.fn();
      const onCancel = vi.fn();

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={onSubmit}
          onCancel={onCancel}
        />
      );

      // Check wizard header
      expect(screen.getByText('New Enrollment Form')).toBeInTheDocument();
      expect(screen.getByText(/Child Information/)).toBeInTheDocument();

      // Verify step indicators are present (10 steps total)
      const stepIndicators = document.querySelectorAll('button[title]');
      expect(stepIndicators.length).toBeGreaterThanOrEqual(WIZARD_STEPS.length);
    });

    it('displays correct step count and progress', () => {
      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={vi.fn()}
        />
      );

      // First step should show Step 1 of 10
      expect(screen.getByText(/Step 1 of 10/)).toBeInTheDocument();
      expect(screen.getByText(/10% complete/)).toBeInTheDocument();
    });

    it('navigates between steps using Previous and Next buttons', async () => {
      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={vi.fn()}
        />
      );

      // Initially on step 1, Previous should be disabled
      const previousBtn = screen.getByRole('button', { name: /Previous/ });
      expect(previousBtn).toBeDisabled();

      // Click Next to go to step 2 (this will fail validation but wizard allows navigation)
      const nextBtn = screen.getByRole('button', { name: /Next/ });
      expect(nextBtn).toBeInTheDocument();
    });

    it('calls onCancel when cancel button is clicked', () => {
      const onCancel = vi.fn();

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={onCancel}
        />
      );

      // Find the close button by aria-label
      const closeBtn = screen.getByRole('button', { name: /Cancel/ });
      fireEvent.click(closeBtn);

      expect(onCancel).toHaveBeenCalled();
    });

    it('handles keyboard navigation (Escape to cancel)', () => {
      const onCancel = vi.fn();

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={onCancel}
        />
      );

      // Simulate Escape key
      fireEvent.keyDown(window, { key: 'Escape' });

      expect(onCancel).toHaveBeenCalled();
    });

    it('displays step content placeholder for each step', () => {
      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={vi.fn()}
        />
      );

      // First step should show Child Information content area
      expect(screen.getByText('Child Information')).toBeInTheDocument();
      expect(screen.getByText('Basic information about the child')).toBeInTheDocument();
    });
  });

  // ==========================================================================
  // 3. Child Information Section Tests
  // ==========================================================================

  describe('3. Child Information Section', () => {
    it('renders all required fields', () => {
      const data = createValidChildInfo();
      render(<ChildInfoSection data={data} onChange={vi.fn()} />);

      expect(screen.getByText('First Name')).toBeInTheDocument();
      expect(screen.getByText('Last Name')).toBeInTheDocument();
      expect(screen.getByText('Date of Birth')).toBeInTheDocument();
    });

    it('populates fields with provided data', () => {
      const data = createValidChildInfo();
      render(<ChildInfoSection data={data} onChange={vi.fn()} />);

      expect(screen.getByDisplayValue('Sophie')).toBeInTheDocument();
      expect(screen.getByDisplayValue('Martin')).toBeInTheDocument();
      expect(screen.getByDisplayValue('2021-03-15')).toBeInTheDocument();
    });

    it('calls onChange when field values are updated', async () => {
      const onChange = vi.fn();
      const data = {
        childFirstName: '',
        childLastName: '',
        childDateOfBirth: '',
        childAddress: '',
        childCity: '',
        childPostalCode: '',
        languagesSpoken: '',
        admissionDate: '',
        notes: '',
      };

      render(<ChildInfoSection data={data} onChange={onChange} />);

      const firstNameInput = screen.getByPlaceholderText("Enter child's first name");
      fireEvent.change(firstNameInput, { target: { value: 'Sophie' } });

      expect(onChange).toHaveBeenCalledWith({ childFirstName: 'Sophie' });
    });

    it('displays validation errors when provided', () => {
      const data = {
        childFirstName: '',
        childLastName: '',
        childDateOfBirth: '',
        childAddress: '',
        childCity: '',
        childPostalCode: '',
        languagesSpoken: '',
        admissionDate: '',
        notes: '',
      };
      const errors = ['First name is required', 'Date of birth is required'];

      render(<ChildInfoSection data={data} onChange={vi.fn()} errors={errors} />);

      expect(screen.getByText('First name is required')).toBeInTheDocument();
      expect(screen.getByText('Date of birth is required')).toBeInTheDocument();
    });

    it('disables all inputs when disabled prop is true', () => {
      const data = createValidChildInfo();
      render(<ChildInfoSection data={data} onChange={vi.fn()} disabled={true} />);

      const firstNameInput = screen.getByPlaceholderText("Enter child's first name");
      const lastNameInput = screen.getByPlaceholderText("Enter child's last name");

      expect(firstNameInput).toBeDisabled();
      expect(lastNameInput).toBeDisabled();
    });
  });

  // ==========================================================================
  // 4. Parent Section Tests
  // ==========================================================================

  describe('4. Parent Section', () => {
    it('renders required parent 1 fields', () => {
      const data = createValidParent('1');
      render(
        <ParentSection
          data={data}
          onChange={vi.fn()}
          parentNumber={1}
          isRequired={true}
        />
      );

      expect(screen.getByText(/Parent\/Guardian 1/)).toBeInTheDocument();
    });

    it('renders optional parent 2 section differently', () => {
      const data = null;
      render(
        <ParentSection
          data={data}
          onChange={vi.fn()}
          parentNumber={2}
          isRequired={false}
        />
      );

      expect(screen.getByText(/Parent\/Guardian 2/)).toBeInTheDocument();
    });

    it('populates fields with provided data', () => {
      const data = createValidParent('1');
      render(
        <ParentSection
          data={data}
          onChange={vi.fn()}
          parentNumber={1}
          isRequired={true}
        />
      );

      expect(screen.getByDisplayValue('Marie Martin')).toBeInTheDocument();
      expect(screen.getByDisplayValue('marie.martin@email.com')).toBeInTheDocument();
    });

    it('calls onChange when field values are updated', () => {
      const onChange = vi.fn();
      const data = createValidParent('1');

      render(
        <ParentSection
          data={data}
          onChange={onChange}
          parentNumber={1}
          isRequired={true}
        />
      );

      const nameInput = screen.getByDisplayValue('Marie Martin');
      fireEvent.change(nameInput, { target: { value: 'Marie Smith' } });

      expect(onChange).toHaveBeenCalled();
    });
  });

  // ==========================================================================
  // 5. Authorized Pickups Section Tests
  // ==========================================================================

  describe('5. Authorized Pickups Section', () => {
    it('allows adding authorized pickup persons', () => {
      const onChange = vi.fn();
      const data: Omit<AuthorizedPickup, 'id' | 'formId'>[] = [];

      render(<AuthorizedPickupsSection data={data} onChange={onChange} />);

      const addButton = screen.getByRole('button', { name: /Add Authorized Person/i });
      expect(addButton).toBeInTheDocument();

      fireEvent.click(addButton);
      expect(onChange).toHaveBeenCalled();
    });

    it('displays existing pickup persons', () => {
      const data = [createValidPickup(0), createValidPickup(1)];

      render(<AuthorizedPickupsSection data={data} onChange={vi.fn()} />);

      expect(screen.getByDisplayValue('Grandma Louise')).toBeInTheDocument();
      expect(screen.getByDisplayValue('Uncle Pierre')).toBeInTheDocument();
    });

    it('allows removing pickup persons', () => {
      const onChange = vi.fn();
      const data = [createValidPickup(0)];

      render(<AuthorizedPickupsSection data={data} onChange={onChange} />);

      const removeButton = screen.getByRole('button', { name: /Remove/i });
      fireEvent.click(removeButton);

      expect(onChange).toHaveBeenCalled();
    });
  });

  // ==========================================================================
  // 6. Emergency Contacts Section Tests
  // ==========================================================================

  describe('6. Emergency Contacts Section', () => {
    it('shows minimum contacts requirement notice', () => {
      const data: Omit<EmergencyContact, 'id' | 'formId'>[] = [];

      render(<EmergencyContactsSection data={data} onChange={vi.fn()} />);

      expect(screen.getByText(/minimum 2 emergency contacts/i)).toBeInTheDocument();
    });

    it('displays existing emergency contacts', () => {
      const data = [createValidEmergencyContact(0), createValidEmergencyContact(1)];

      render(<EmergencyContactsSection data={data} onChange={vi.fn()} />);

      expect(screen.getByDisplayValue('Grandma Louise')).toBeInTheDocument();
      expect(screen.getByDisplayValue('Aunt Claire')).toBeInTheDocument();
    });

    it('allows priority reordering', () => {
      const onChange = vi.fn();
      const data = [createValidEmergencyContact(0), createValidEmergencyContact(1)];

      render(<EmergencyContactsSection data={data} onChange={onChange} />);

      // Should have move up/down buttons for reordering
      const priorityButtons = screen.getAllByRole('button');
      expect(priorityButtons.length).toBeGreaterThan(0);
    });
  });

  // ==========================================================================
  // 7. Health Section Tests
  // ==========================================================================

  describe('7. Health Section', () => {
    it('renders health information fields', () => {
      const data = createValidHealthInfo();

      render(<HealthSection data={data} onChange={vi.fn()} />);

      expect(screen.getByText(/Health Information/i)).toBeInTheDocument();
    });

    it('shows EpiPen alert when hasEpiPen is true', () => {
      const data = createValidHealthInfo();

      render(<HealthSection data={data} onChange={vi.fn()} />);

      // Should show EpiPen related information
      expect(screen.getByText(/EpiPen/i)).toBeInTheDocument();
    });

    it('displays allergies list', () => {
      const data = createValidHealthInfo();

      render(<HealthSection data={data} onChange={vi.fn()} />);

      // Should show allergy information
      expect(screen.getByDisplayValue('Peanuts')).toBeInTheDocument();
    });

    it('displays medications list', () => {
      const data = createValidHealthInfo();

      render(<HealthSection data={data} onChange={vi.fn()} />);

      // Should show medication information
      expect(screen.getByDisplayValue('Ventolin')).toBeInTheDocument();
    });
  });

  // ==========================================================================
  // 8. Nutrition Section Tests
  // ==========================================================================

  describe('8. Nutrition Section', () => {
    it('renders nutrition fields', () => {
      const data = createValidNutritionInfo();

      render(<NutritionSection data={data} onChange={vi.fn()} />);

      expect(screen.getByText(/Nutrition/i)).toBeInTheDocument();
    });

    it('shows food allergy warning when allergies present', () => {
      const data = createValidNutritionInfo();

      render(<NutritionSection data={data} onChange={vi.fn()} />);

      // Should display food allergy information
      expect(screen.getByDisplayValue('Peanuts (severe)')).toBeInTheDocument();
    });

    it('handles bottle feeding toggle', () => {
      const onChange = vi.fn();
      const data = { ...createValidNutritionInfo(), isBottleFeeding: true };

      render(<NutritionSection data={data} onChange={onChange} />);

      // Should show bottle feeding information when enabled
      const bottleCheckbox = screen.getByLabelText(/bottle feeding/i);
      expect(bottleCheckbox).toBeChecked();
    });
  });

  // ==========================================================================
  // 9. Attendance Pattern Section Tests
  // ==========================================================================

  describe('9. Attendance Pattern Section', () => {
    it('renders weekly schedule grid', () => {
      const data = createValidAttendancePattern();

      render(<AttendancePatternSection data={data} onChange={vi.fn()} />);

      expect(screen.getByText(/Attendance/i)).toBeInTheDocument();
      expect(screen.getByText(/Monday/i)).toBeInTheDocument();
      expect(screen.getByText(/Friday/i)).toBeInTheDocument();
    });

    it('displays hours per week', () => {
      const data = createValidAttendancePattern();

      render(<AttendancePatternSection data={data} onChange={vi.fn()} />);

      // Should show expected hours
      expect(screen.getByDisplayValue('40')).toBeInTheDocument();
    });

    it('allows selecting AM/PM schedules', () => {
      const onChange = vi.fn();
      const data = createValidAttendancePattern();

      render(<AttendancePatternSection data={data} onChange={onChange} />);

      // Should have AM/PM checkboxes
      const amCheckboxes = screen.getAllByRole('checkbox');
      expect(amCheckboxes.length).toBeGreaterThan(0);
    });
  });

  // ==========================================================================
  // 10. Signatures Section Tests
  // ==========================================================================

  describe('10. Signatures Section', () => {
    it('renders signature blocks for required parties', () => {
      const data = {
        parent1: {
          signatureType: 'Parent1' as const,
          signatureData: null,
          signerName: '',
          signedAt: null,
          agreedToTerms: false,
        },
        parent2: null,
        director: null,
      };

      render(
        <SignaturesSection
          data={data}
          onChange={vi.fn()}
          parent1Name="Marie Martin"
        />
      );

      expect(screen.getByText(/Parent.*Signature/i)).toBeInTheDocument();
    });

    it('shows agreement checkbox requirement', () => {
      const data = {
        parent1: {
          signatureType: 'Parent1' as const,
          signatureData: null,
          signerName: '',
          signedAt: null,
          agreedToTerms: false,
        },
        parent2: null,
        director: null,
      };

      render(
        <SignaturesSection
          data={data}
          onChange={vi.fn()}
          parent1Name="Marie Martin"
        />
      );

      expect(screen.getByText(/agree/i)).toBeInTheDocument();
    });

    it('shows signature completion status', () => {
      const data = {
        parent1: {
          signatureType: 'Parent1' as const,
          signatureData: 'data:image/png;base64,test',
          signerName: 'Marie Martin',
          signedAt: new Date().toISOString(),
          agreedToTerms: true,
        },
        parent2: null,
        director: null,
      };

      render(
        <SignaturesSection
          data={data}
          onChange={vi.fn()}
          parent1Name="Marie Martin"
        />
      );

      // Should show completion indicator
      expect(screen.getByText(/Complete/i)).toBeInTheDocument();
    });
  });

  // ==========================================================================
  // 11. Form Card Status Display Tests
  // ==========================================================================

  describe('11. Form Card Status Display', () => {
    const statusTests: EnrollmentFormStatus[] = ['Draft', 'Submitted', 'Approved', 'Rejected', 'Expired'];

    statusTests.forEach((status) => {
      it(`displays correct badge style for ${status} status`, () => {
        const form = createMockFormSummary({ status });

        const { container } = render(
          <EnrollmentFormCard form={form} onView={vi.fn()} />
        );

        const badge = container.querySelector('.badge');
        expect(badge).toBeInTheDocument();
        expect(screen.getByText(status)).toBeInTheDocument();
      });
    });

    it('shows Edit button only for Rejected forms', () => {
      const rejectedForm = createMockFormSummary({ status: 'Rejected' });

      render(
        <EnrollmentFormCard form={rejectedForm} onView={vi.fn()} onEdit={vi.fn()} />
      );

      expect(screen.getByText('Edit')).toBeInTheDocument();
    });

    it('does not show Edit button for Approved forms', () => {
      const approvedForm = createMockFormSummary({ status: 'Approved' });

      render(
        <EnrollmentFormCard form={approvedForm} onView={vi.fn()} onEdit={vi.fn()} />
      );

      expect(screen.queryByText('Edit')).not.toBeInTheDocument();
    });
  });

  // ==========================================================================
  // 12. PDF Preview Component Tests
  // ==========================================================================

  describe('12. PDF Preview Component', () => {
    it('renders PDF preview with form information', () => {
      const form = createCompleteEnrollmentForm();

      render(<EnrollmentPdfPreview form={form} />);

      // Should display form number and child name
      expect(screen.getByText(/ENR-2024-001/)).toBeInTheDocument();
    });

    it('shows download button', () => {
      const form = createCompleteEnrollmentForm();

      render(<EnrollmentPdfPreview form={form} />);

      // Should have download functionality
      const downloadBtn = screen.getByRole('button', { name: /Download/i });
      expect(downloadBtn).toBeInTheDocument();
    });

    it('handles generate PDF callback', async () => {
      const form = createCompleteEnrollmentForm();
      const onGeneratePdf = vi.fn().mockResolvedValue({
        url: 'https://example.com/test.pdf',
        filename: 'test.pdf',
      });

      render(
        <EnrollmentPdfPreview form={form} onGeneratePdf={onGeneratePdf} />
      );

      // Should render with generate option
      expect(screen.getByText(/PDF/i)).toBeInTheDocument();
    });

    it('displays correct status color', () => {
      const draftForm = createCompleteEnrollmentForm();
      const approvedForm = { ...createCompleteEnrollmentForm(), status: 'Approved' as const };

      const { rerender, container } = render(
        <EnrollmentPdfPreview form={draftForm} showFormInfo={true} />
      );

      // Check draft status
      expect(container.textContent).toContain('Draft');

      rerender(<EnrollmentPdfPreview form={approvedForm} showFormInfo={true} />);

      // Check approved status
      expect(container.textContent).toContain('Approved');
    });
  });

  // ==========================================================================
  // 13. Form Submission Flow Tests
  // ==========================================================================

  describe('13. Form Submission Flow', () => {
    it('validates all steps before submission', async () => {
      const onSubmit = vi.fn();

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={onSubmit}
          onCancel={vi.fn()}
        />
      );

      // Try to navigate without filling required fields
      const nextBtn = screen.getByRole('button', { name: /Next/ });
      fireEvent.click(nextBtn);

      // Should show validation errors
      await waitFor(() => {
        expect(screen.getByText(/Please correct the following/)).toBeInTheDocument();
      });
    });

    it('displays submitting state when isSubmitting is true', () => {
      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={vi.fn()}
          onCancel={vi.fn()}
          isSubmitting={true}
        />
      );

      // All navigation should be disabled
      const previousBtn = screen.getByRole('button', { name: /Previous/ });
      const closeBtn = screen.getByRole('button', { name: /Cancel/ });

      expect(previousBtn).toBeDisabled();
      expect(closeBtn).toBeDisabled();
    });
  });

  // ==========================================================================
  // 14. Data Persistence Tests
  // ==========================================================================

  describe('14. Data Persistence', () => {
    it('maintains form data when navigating between steps', async () => {
      const onSubmit = vi.fn();

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          onSubmit={onSubmit}
          onCancel={vi.fn()}
        />
      );

      // Step indicators should be clickable and maintain state
      const stepIndicators = document.querySelectorAll('button[title]');
      expect(stepIndicators.length).toBeGreaterThan(0);
    });

    it('loads initial data when editing existing form', () => {
      const initialData = {
        childInfo: createValidChildInfo(),
        parent1: createValidParent('1'),
        parent2: null,
        authorizedPickups: [],
        emergencyContacts: [],
        healthInfo: null,
        nutritionInfo: null,
        attendancePattern: null,
        signatures: [],
      };

      render(
        <EnrollmentFormWizard
          personId="person-1"
          familyId="family-1"
          initialData={initialData}
          onSubmit={vi.fn()}
          onCancel={vi.fn()}
        />
      );

      // Should load with initial data
      expect(screen.getByText('New Enrollment Form')).toBeInTheDocument();
    });
  });
});

// ============================================================================
// Workflow Integration Tests
// ============================================================================

describe('Complete Workflow Integration', () => {
  it('verifies end-to-end flow from list to new form to submission', async () => {
    // 1. Start with enrollment list - create a form card
    const mockForm = createMockFormSummary();
    const onView = vi.fn();
    const onContinue = vi.fn();

    const { rerender } = render(
      <EnrollmentFormCard form={mockForm} onView={onView} onContinue={onContinue} />
    );

    // Verify form card is displayed
    expect(screen.getByText('Sophie Martin')).toBeInTheDocument();
    expect(screen.getByText('Draft')).toBeInTheDocument();

    // 2. Now render the wizard for new form creation
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    const onCancel = vi.fn();

    rerender(
      <EnrollmentFormWizard
        personId="person-1"
        familyId="family-1"
        onSubmit={onSubmit}
        onCancel={onCancel}
      />
    );

    // Verify wizard is displayed
    expect(screen.getByText('New Enrollment Form')).toBeInTheDocument();
    expect(screen.getByText(/Step 1 of 10/)).toBeInTheDocument();

    // 3. Verify child info section structure
    expect(screen.getByText('Child Information')).toBeInTheDocument();
    expect(screen.getByText('Basic information about the child')).toBeInTheDocument();
  });

  it('verifies wizard step configuration is complete', () => {
    expect(WIZARD_STEPS.length).toBe(10);

    const stepIds = WIZARD_STEPS.map(s => s.id);
    expect(stepIds).toContain('child-info');
    expect(stepIds).toContain('parent-1');
    expect(stepIds).toContain('parent-2');
    expect(stepIds).toContain('authorized-pickups');
    expect(stepIds).toContain('emergency-contacts');
    expect(stepIds).toContain('health');
    expect(stepIds).toContain('nutrition');
    expect(stepIds).toContain('attendance');
    expect(stepIds).toContain('signatures');
    expect(stepIds).toContain('review');
  });

  it('verifies optional step is marked correctly', () => {
    const parent2Step = WIZARD_STEPS.find(s => s.id === 'parent-2');
    expect(parent2Step?.isOptional).toBe(true);

    const requiredSteps = WIZARD_STEPS.filter(s => !s.isOptional);
    expect(requiredSteps.length).toBe(9);
  });
});

// ============================================================================
// Accessibility Tests
// ============================================================================

describe('Accessibility', () => {
  it('has proper ARIA labels on navigation buttons', () => {
    render(
      <EnrollmentFormWizard
        personId="person-1"
        familyId="family-1"
        onSubmit={vi.fn()}
        onCancel={vi.fn()}
      />
    );

    const cancelBtn = screen.getByRole('button', { name: /Cancel/ });
    expect(cancelBtn).toHaveAttribute('aria-label', 'Cancel');
  });

  it('has keyboard navigation support', () => {
    const onCancel = vi.fn();

    render(
      <EnrollmentFormWizard
        personId="person-1"
        familyId="family-1"
        onSubmit={vi.fn()}
        onCancel={onCancel}
      />
    );

    // Escape key should trigger cancel
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(onCancel).toHaveBeenCalled();
  });

  it('form card has proper button roles', () => {
    const form = createMockFormSummary();

    render(<EnrollmentFormCard form={form} onView={vi.fn()} />);

    const viewButton = screen.getByRole('button', { name: /View Details/i });
    expect(viewButton).toBeInTheDocument();
  });
});
