'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter, useParams } from 'next/navigation';
import { EnrollmentPdfPreview } from '@/components/enrollment/EnrollmentPdfPreview';
import type {
  EnrollmentForm,
  EnrollmentParent,
  AuthorizedPickup,
  EmergencyContact,
  HealthInfo,
  NutritionInfo,
  AttendancePattern,
  EnrollmentSignature,
  EnrollmentFormStatus,
  AllergyInfo,
  MedicationInfo,
} from '@/lib/types';

// ============================================================================
// Mock Data - will be replaced with API calls
// ============================================================================

const mockEnrollmentForm: EnrollmentForm = {
  id: 'enroll-1',
  personId: 'person-1',
  familyId: 'family-1',
  schoolYearId: 'sy-2024',
  formNumber: 'ENR-2024-001',
  status: 'Approved',
  version: 1,
  admissionDate: '2024-09-01',
  childFirstName: 'Sophie',
  childLastName: 'Martin',
  childDateOfBirth: '2021-03-15',
  childAddress: '123 Rue Principale',
  childCity: 'Montreal',
  childPostalCode: 'H2X 1Y6',
  languagesSpoken: 'French, English',
  notes: 'Sophie is a friendly child who enjoys arts and crafts.',
  submittedAt: '2024-01-12T10:00:00Z',
  approvedAt: '2024-01-15T14:30:00Z',
  approvedById: 'admin-1',
  createdById: 'parent-1',
  createdAt: '2024-01-10T09:00:00Z',
  updatedAt: '2024-01-15T14:30:00Z',
  parents: [
    {
      id: 'parent-1',
      formId: 'enroll-1',
      parentNumber: '1',
      name: 'Marie Martin',
      relationship: 'Mother',
      address: '123 Rue Principale',
      city: 'Montreal',
      postalCode: 'H2X 1Y6',
      homePhone: '514-555-0101',
      cellPhone: '514-555-0102',
      workPhone: '514-555-0103',
      email: 'marie.martin@email.com',
      employer: 'Tech Corp',
      workAddress: '456 Business St, Montreal',
      workHours: '9:00 AM - 5:00 PM',
      isPrimaryContact: true,
    },
    {
      id: 'parent-2',
      formId: 'enroll-1',
      parentNumber: '2',
      name: 'Jean Martin',
      relationship: 'Father',
      address: '123 Rue Principale',
      city: 'Montreal',
      postalCode: 'H2X 1Y6',
      homePhone: '514-555-0101',
      cellPhone: '514-555-0201',
      email: 'jean.martin@email.com',
      employer: 'Finance Inc',
      workAddress: '789 Corporate Ave, Montreal',
      workHours: '8:00 AM - 4:00 PM',
      isPrimaryContact: false,
    },
  ],
  authorizedPickups: [
    {
      id: 'pickup-1',
      formId: 'enroll-1',
      name: 'Grandma Louise',
      relationship: 'Grandmother',
      phone: '514-555-0301',
      priority: 1,
      notes: 'Primary backup pickup',
    },
    {
      id: 'pickup-2',
      formId: 'enroll-1',
      name: 'Uncle Pierre',
      relationship: 'Uncle',
      phone: '514-555-0302',
      priority: 2,
    },
  ],
  emergencyContacts: [
    {
      id: 'emergency-1',
      formId: 'enroll-1',
      name: 'Grandma Louise',
      relationship: 'Grandmother',
      phone: '514-555-0301',
      alternatePhone: '514-555-0311',
      priority: 1,
      notes: 'Lives nearby, can arrive quickly',
    },
    {
      id: 'emergency-2',
      formId: 'enroll-1',
      name: 'Aunt Claire',
      relationship: 'Aunt',
      phone: '514-555-0401',
      priority: 2,
    },
  ],
  healthInfo: {
    id: 'health-1',
    formId: 'enroll-1',
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
    epiPenInstructions:
      'Administer in outer thigh, call 911 immediately, notify parents',
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
  },
  nutritionInfo: {
    id: 'nutrition-1',
    formId: 'enroll-1',
    dietaryRestrictions: 'Peanut-free diet',
    foodAllergies: 'Peanuts (severe)',
    feedingInstructions: '',
    isBottleFeeding: false,
    foodPreferences: 'Loves fruits, especially berries and apples',
    foodDislikes: 'Does not like broccoli',
    mealPlanNotes: 'Please ensure all snacks and meals are peanut-free',
  },
  attendancePattern: {
    id: 'attendance-1',
    formId: 'enroll-1',
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
  },
  signatures: [
    {
      id: 'sig-1',
      formId: 'enroll-1',
      signatureType: 'Parent1',
      signatureData: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      signerName: 'Marie Martin',
      signedAt: '2024-01-12T10:00:00Z',
    },
    {
      id: 'sig-2',
      formId: 'enroll-1',
      signatureType: 'Parent2',
      signatureData: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      signerName: 'Jean Martin',
      signedAt: '2024-01-12T10:15:00Z',
    },
  ],
};

// ============================================================================
// Helper Functions
// ============================================================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function formatTime(timeString: string): string {
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function calculateAge(dateOfBirth: string): string {
  const today = new Date();
  const birth = new Date(dateOfBirth);
  let years = today.getFullYear() - birth.getFullYear();
  let months = today.getMonth() - birth.getMonth();

  if (months < 0) {
    years--;
    months += 12;
  }

  if (years === 0) {
    return `${months} month${months !== 1 ? 's' : ''} old`;
  }
  if (months === 0) {
    return `${years} year${years !== 1 ? 's' : ''} old`;
  }
  return `${years}y ${months}m old`;
}

function getStatusBadgeClasses(status: EnrollmentFormStatus): string {
  switch (status) {
    case 'Draft':
      return 'badge badge-default';
    case 'Submitted':
      return 'badge badge-info';
    case 'Approved':
      return 'badge badge-success';
    case 'Rejected':
      return 'badge badge-error';
    case 'Expired':
      return 'badge badge-warning';
    default:
      return 'badge badge-default';
  }
}

// ============================================================================
// Section Components
// ============================================================================

function SectionCard({
  title,
  icon,
  children,
  className = '',
}: {
  title: string;
  icon: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={`card ${className}`}>
      <div className="card-body">
        <div className="flex items-center gap-3 mb-4">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
            {icon}
          </div>
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
        </div>
        {children}
      </div>
    </div>
  );
}

function InfoRow({
  label,
  value,
  highlight = false,
}: {
  label: string;
  value: React.ReactNode;
  highlight?: boolean;
}) {
  if (!value) return null;
  return (
    <div className="py-2">
      <dt className="text-sm font-medium text-gray-500">{label}</dt>
      <dd
        className={`mt-1 text-sm ${highlight ? 'font-semibold text-gray-900' : 'text-gray-900'}`}
      >
        {value}
      </dd>
    </div>
  );
}

function ChildInfoSection({ form }: { form: EnrollmentForm }) {
  const childFullName = `${form.childFirstName} ${form.childLastName}`;

  return (
    <SectionCard
      title="Child Information"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
          />
        </svg>
      }
    >
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
        <InfoRow label="Full Name" value={childFullName} highlight />
        <InfoRow
          label="Date of Birth"
          value={`${formatDate(form.childDateOfBirth)} (${calculateAge(form.childDateOfBirth)})`}
        />
        {form.admissionDate && (
          <InfoRow label="Admission Date" value={formatDate(form.admissionDate)} />
        )}
        <InfoRow label="Languages Spoken" value={form.languagesSpoken} />
        {form.childAddress && (
          <InfoRow
            label="Address"
            value={`${form.childAddress}, ${form.childCity} ${form.childPostalCode}`}
          />
        )}
        {form.notes && <InfoRow label="Notes" value={form.notes} />}
      </dl>
    </SectionCard>
  );
}

function ParentInfoSection({ parents }: { parents: EnrollmentParent[] }) {
  if (!parents || parents.length === 0) return null;

  return (
    <SectionCard
      title="Parents / Guardians"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
          />
        </svg>
      }
    >
      <div className="space-y-6">
        {parents.map((parent, index) => (
          <div
            key={parent.id}
            className={index > 0 ? 'border-t border-gray-200 pt-6' : ''}
          >
            <div className="flex items-center gap-2 mb-3">
              <h4 className="text-sm font-semibold text-gray-900">
                Parent {parent.parentNumber}: {parent.name}
              </h4>
              {parent.isPrimaryContact && (
                <span className="badge badge-info text-xs">Primary Contact</span>
              )}
            </div>
            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
              <InfoRow label="Relationship" value={parent.relationship} />
              <InfoRow label="Email" value={parent.email} />
              <InfoRow label="Cell Phone" value={parent.cellPhone} />
              <InfoRow label="Home Phone" value={parent.homePhone} />
              <InfoRow label="Work Phone" value={parent.workPhone} />
              {parent.address && (
                <InfoRow
                  label="Address"
                  value={`${parent.address}, ${parent.city} ${parent.postalCode}`}
                />
              )}
              <InfoRow label="Employer" value={parent.employer} />
              <InfoRow label="Work Address" value={parent.workAddress} />
              <InfoRow label="Work Hours" value={parent.workHours} />
            </dl>
          </div>
        ))}
      </div>
    </SectionCard>
  );
}

function AuthorizedPickupsSection({ pickups }: { pickups: AuthorizedPickup[] }) {
  if (!pickups || pickups.length === 0) return null;

  return (
    <SectionCard
      title="Authorized Pickups"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
          />
        </svg>
      }
    >
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Priority
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Name
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Relationship
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Phone
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Notes
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {pickups
              .sort((a, b) => a.priority - b.priority)
              .map((pickup) => (
                <tr key={pickup.id}>
                  <td className="px-3 py-2 text-sm text-gray-900">
                    #{pickup.priority}
                  </td>
                  <td className="px-3 py-2 text-sm font-medium text-gray-900">
                    {pickup.name}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-600">
                    {pickup.relationship}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-600">
                    {pickup.phone}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-500">
                    {pickup.notes || '-'}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>
    </SectionCard>
  );
}

function EmergencyContactsSection({
  contacts,
}: {
  contacts: EmergencyContact[];
}) {
  if (!contacts || contacts.length === 0) return null;

  return (
    <SectionCard
      title="Emergency Contacts"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
          />
        </svg>
      }
    >
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Priority
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Name
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Relationship
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Phone
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Alternate Phone
              </th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Notes
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {contacts
              .sort((a, b) => a.priority - b.priority)
              .map((contact) => (
                <tr key={contact.id}>
                  <td className="px-3 py-2 text-sm text-gray-900">
                    #{contact.priority}
                  </td>
                  <td className="px-3 py-2 text-sm font-medium text-gray-900">
                    {contact.name}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-600">
                    {contact.relationship}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-600">
                    {contact.phone}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-600">
                    {contact.alternatePhone || '-'}
                  </td>
                  <td className="px-3 py-2 text-sm text-gray-500">
                    {contact.notes || '-'}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>
    </SectionCard>
  );
}

function HealthInfoSection({ health }: { health: HealthInfo }) {
  if (!health) return null;

  return (
    <SectionCard
      title="Health Information"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      }
    >
      {/* EpiPen Alert */}
      {health.hasEpiPen && (
        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
          <div className="flex items-start">
            <svg
              className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <div className="ml-3">
              <h4 className="text-sm font-semibold text-red-800">
                EpiPen Required
              </h4>
              {health.epiPenInstructions && (
                <p className="mt-1 text-sm text-red-700">
                  {health.epiPenInstructions}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      <div className="space-y-6">
        {/* Allergies */}
        {health.allergies && health.allergies.length > 0 && (
          <div>
            <h4 className="text-sm font-semibold text-gray-900 mb-2">
              Allergies
            </h4>
            <div className="space-y-2">
              {health.allergies.map((allergy, index) => (
                <div
                  key={index}
                  className="rounded-lg bg-amber-50 border border-amber-200 p-3"
                >
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-amber-900">
                      {allergy.allergen}
                    </span>
                    {allergy.severity && (
                      <span
                        className={`badge text-xs ${
                          allergy.severity === 'severe'
                            ? 'badge-error'
                            : allergy.severity === 'moderate'
                              ? 'badge-warning'
                              : 'badge-default'
                        }`}
                      >
                        {allergy.severity}
                      </span>
                    )}
                  </div>
                  {allergy.reaction && (
                    <p className="mt-1 text-sm text-amber-800">
                      Reaction: {allergy.reaction}
                    </p>
                  )}
                  {allergy.treatment && (
                    <p className="text-sm text-amber-700">
                      Treatment: {allergy.treatment}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Medical Conditions */}
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
          <InfoRow
            label="Medical Conditions"
            value={health.medicalConditions}
          />
          <InfoRow label="Special Needs" value={health.specialNeeds} />
          <InfoRow
            label="Developmental Notes"
            value={health.developmentalNotes}
          />
        </dl>

        {/* Medications */}
        {health.medications && health.medications.length > 0 && (
          <div>
            <h4 className="text-sm font-semibold text-gray-900 mb-2">
              Medications
            </h4>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr>
                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                      Medication
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                      Dosage
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                      Schedule
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                      Instructions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {health.medications.map((med, index) => (
                    <tr key={index}>
                      <td className="px-3 py-2 text-sm font-medium text-gray-900">
                        {med.name}
                      </td>
                      <td className="px-3 py-2 text-sm text-gray-600">
                        {med.dosage}
                      </td>
                      <td className="px-3 py-2 text-sm text-gray-600">
                        {med.schedule}
                      </td>
                      <td className="px-3 py-2 text-sm text-gray-500">
                        {med.instructions || '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Doctor & Insurance */}
        <div className="border-t border-gray-200 pt-4">
          <h4 className="text-sm font-semibold text-gray-900 mb-3">
            Doctor & Insurance
          </h4>
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
            <InfoRow label="Doctor Name" value={health.doctorName} />
            <InfoRow label="Doctor Phone" value={health.doctorPhone} />
            <InfoRow label="Doctor Address" value={health.doctorAddress} />
            <InfoRow
              label="Health Insurance (RAMQ)"
              value={health.healthInsuranceNumber}
            />
            {health.healthInsuranceExpiry && (
              <InfoRow
                label="Insurance Expiry"
                value={formatDate(health.healthInsuranceExpiry)}
              />
            )}
          </dl>
        </div>
      </div>
    </SectionCard>
  );
}

function NutritionInfoSection({ nutrition }: { nutrition: NutritionInfo }) {
  if (!nutrition) return null;

  return (
    <SectionCard
      title="Nutrition & Dietary Information"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          />
        </svg>
      }
    >
      {/* Bottle Feeding Alert */}
      {nutrition.isBottleFeeding && (
        <div className="mb-4 rounded-lg bg-blue-50 border border-blue-200 p-4">
          <div className="flex items-start">
            <svg
              className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div className="ml-3">
              <h4 className="text-sm font-semibold text-blue-800">
                Bottle Feeding
              </h4>
              {nutrition.bottleFeedingInfo && (
                <p className="mt-1 text-sm text-blue-700">
                  {nutrition.bottleFeedingInfo}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Food Allergies Warning */}
      {nutrition.foodAllergies && (
        <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-4">
          <div className="flex items-start">
            <svg
              className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <div className="ml-3">
              <h4 className="text-sm font-semibold text-amber-800">
                Food Allergies
              </h4>
              <p className="mt-1 text-sm text-amber-700">
                {nutrition.foodAllergies}
              </p>
            </div>
          </div>
        </div>
      )}

      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
        <InfoRow
          label="Dietary Restrictions"
          value={nutrition.dietaryRestrictions}
        />
        <InfoRow
          label="Feeding Instructions"
          value={nutrition.feedingInstructions}
        />
        <InfoRow label="Food Preferences" value={nutrition.foodPreferences} />
        <InfoRow label="Food Dislikes" value={nutrition.foodDislikes} />
        <InfoRow label="Meal Plan Notes" value={nutrition.mealPlanNotes} />
      </dl>
    </SectionCard>
  );
}

function AttendancePatternSection({
  attendance,
}: {
  attendance: AttendancePattern;
}) {
  if (!attendance) return null;

  const days = [
    {
      name: 'Monday',
      am: attendance.mondayAm,
      pm: attendance.mondayPm,
    },
    {
      name: 'Tuesday',
      am: attendance.tuesdayAm,
      pm: attendance.tuesdayPm,
    },
    {
      name: 'Wednesday',
      am: attendance.wednesdayAm,
      pm: attendance.wednesdayPm,
    },
    {
      name: 'Thursday',
      am: attendance.thursdayAm,
      pm: attendance.thursdayPm,
    },
    {
      name: 'Friday',
      am: attendance.fridayAm,
      pm: attendance.fridayPm,
    },
    {
      name: 'Saturday',
      am: attendance.saturdayAm,
      pm: attendance.saturdayPm,
    },
    {
      name: 'Sunday',
      am: attendance.sundayAm,
      pm: attendance.sundayPm,
    },
  ];

  const scheduledDays = days.filter((d) => d.am || d.pm).length;
  const totalPeriods = days.reduce(
    (acc, d) => acc + (d.am ? 1 : 0) + (d.pm ? 1 : 0),
    0
  );
  const hasWeekendCare = attendance.saturdayAm || attendance.saturdayPm || attendance.sundayAm || attendance.sundayPm;

  return (
    <SectionCard
      title="Weekly Attendance Schedule"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      }
    >
      {/* Schedule Grid */}
      <div className="overflow-x-auto mb-4">
        <table className="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                Day
              </th>
              <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                AM
              </th>
              <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                PM
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {days.map((day) => (
              <tr key={day.name} className={day.am || day.pm ? '' : 'opacity-50'}>
                <td className="px-3 py-2 text-sm font-medium text-gray-900">
                  {day.name}
                </td>
                <td className="px-3 py-2 text-center">
                  {day.am ? (
                    <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-green-100">
                      <svg
                        className="h-4 w-4 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                    </span>
                  ) : (
                    <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-gray-100">
                      <svg
                        className="h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </span>
                  )}
                </td>
                <td className="px-3 py-2 text-center">
                  {day.pm ? (
                    <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-green-100">
                      <svg
                        className="h-4 w-4 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                    </span>
                  ) : (
                    <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-gray-100">
                      <svg
                        className="h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Schedule Summary */}
      <div className="flex flex-wrap gap-4 mb-4">
        <div className="flex items-center gap-2 text-sm text-gray-600">
          <span className="font-medium">{scheduledDays}</span> days/week
        </div>
        <div className="flex items-center gap-2 text-sm text-gray-600">
          <span className="font-medium">{totalPeriods}</span> periods/week
        </div>
        {attendance.expectedHoursPerWeek && (
          <div className="flex items-center gap-2 text-sm text-gray-600">
            <span className="font-medium">{attendance.expectedHoursPerWeek}</span>{' '}
            hours/week
          </div>
        )}
        {totalPeriods >= 10 && (
          <span className="badge badge-success text-xs">Full-time</span>
        )}
        {hasWeekendCare && (
          <span className="badge badge-info text-xs">Weekend Care</span>
        )}
      </div>

      {/* Times */}
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
        {attendance.expectedArrivalTime && (
          <InfoRow
            label="Expected Arrival"
            value={formatTime(attendance.expectedArrivalTime)}
          />
        )}
        {attendance.expectedDepartureTime && (
          <InfoRow
            label="Expected Departure"
            value={formatTime(attendance.expectedDepartureTime)}
          />
        )}
        <InfoRow label="Schedule Notes" value={attendance.notes} />
      </dl>
    </SectionCard>
  );
}

function SignaturesSection({
  signatures,
}: {
  signatures: EnrollmentSignature[];
}) {
  if (!signatures || signatures.length === 0) return null;

  return (
    <SectionCard
      title="E-Signatures"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
          />
        </svg>
      }
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {signatures.map((sig) => (
          <div
            key={sig.id}
            className="border border-gray-200 rounded-lg p-4 bg-gray-50"
          >
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-semibold text-gray-900">
                {sig.signatureType === 'Parent1'
                  ? 'Parent 1'
                  : sig.signatureType === 'Parent2'
                    ? 'Parent 2'
                    : 'Director'}
              </h4>
              <span className="badge badge-success text-xs">Signed</span>
            </div>
            <p className="text-sm text-gray-600">{sig.signerName}</p>
            <p className="text-xs text-gray-400 mt-1">
              Signed: {formatDateTime(sig.signedAt)}
            </p>
            {sig.signatureData && (
              <div className="mt-2 p-2 bg-white border border-gray-200 rounded">
                <img
                  src={sig.signatureData}
                  alt={`${sig.signerName}'s signature`}
                  className="max-h-16 mx-auto"
                />
              </div>
            )}
          </div>
        ))}
      </div>
    </SectionCard>
  );
}

function FormMetadataSection({ form }: { form: EnrollmentForm }) {
  return (
    <SectionCard
      title="Form Information"
      icon={
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      }
    >
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
        <InfoRow label="Form Number" value={form.formNumber} highlight />
        <InfoRow label="Version" value={`v${form.version}`} />
        <InfoRow
          label="Status"
          value={
            <span className={getStatusBadgeClasses(form.status)}>
              {form.status}
            </span>
          }
        />
        {form.createdAt && (
          <InfoRow label="Created" value={formatDateTime(form.createdAt)} />
        )}
        {form.updatedAt && (
          <InfoRow label="Last Updated" value={formatDateTime(form.updatedAt)} />
        )}
        {form.submittedAt && (
          <InfoRow label="Submitted" value={formatDateTime(form.submittedAt)} />
        )}
        {form.approvedAt && (
          <InfoRow label="Approved" value={formatDateTime(form.approvedAt)} />
        )}
        {form.rejectedAt && (
          <InfoRow label="Rejected" value={formatDateTime(form.rejectedAt)} />
        )}
        {form.rejectionReason && (
          <InfoRow
            label="Rejection Reason"
            value={
              <span className="text-red-600">{form.rejectionReason}</span>
            }
          />
        )}
      </dl>
    </SectionCard>
  );
}

// ============================================================================
// Main Page Component
// ============================================================================

export default function EnrollmentDetailPage() {
  const router = useRouter();
  const params = useParams();
  const formId = params.id as string;

  const [form, setForm] = useState<EnrollmentForm | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Load form data
  useEffect(() => {
    const loadForm = async () => {
      setIsLoading(true);
      setError(null);

      try {
        // Simulate API call
        await new Promise((resolve) => setTimeout(resolve, 500));

        // For now, use mock data
        // In production, this would be: const data = await gibbonClient.getEnrollmentForm(formId);
        setForm(mockEnrollmentForm);
      } catch (err) {
        setError(
          err instanceof Error ? err.message : 'Failed to load enrollment form'
        );
      } finally {
        setIsLoading(false);
      }
    };

    loadForm();
  }, [formId]);

  const handleEdit = () => {
    router.push(`/enrollment/${formId}/edit`);
  };

  const handleBack = () => {
    router.push('/enrollment');
  };

  const canEdit = form?.status === 'Draft' || form?.status === 'Rejected';

  if (isLoading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="animate-pulse space-y-6">
          <div className="h-8 w-64 bg-gray-200 rounded" />
          <div className="h-4 w-48 bg-gray-200 rounded" />
          <div className="space-y-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="card">
                <div className="card-body">
                  <div className="h-6 w-48 bg-gray-200 rounded mb-4" />
                  <div className="space-y-2">
                    <div className="h-4 w-full bg-gray-100 rounded" />
                    <div className="h-4 w-3/4 bg-gray-100 rounded" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (error || !form) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="rounded-lg bg-red-50 border border-red-200 p-8 text-center">
          <svg
            className="mx-auto h-12 w-12 text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          <h3 className="mt-4 text-lg font-semibold text-red-800">
            {error || 'Enrollment form not found'}
          </h3>
          <p className="mt-2 text-sm text-red-600">
            Unable to load the enrollment form. Please try again.
          </p>
          <div className="mt-4 flex justify-center gap-3">
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="btn btn-outline"
            >
              Try Again
            </button>
            <button type="button" onClick={handleBack} className="btn btn-primary">
              Back to Enrollment List
            </button>
          </div>
        </div>
      </div>
    );
  }

  const childFullName = `${form.childFirstName} ${form.childLastName}`;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <h1 className="text-2xl font-bold text-gray-900">{childFullName}</h1>
              <span className={getStatusBadgeClasses(form.status)}>
                {form.status}
              </span>
            </div>
            <p className="text-sm text-gray-600">
              Enrollment Form #{form.formNumber}
              {form.version > 1 && (
                <span className="ml-2 text-gray-400">(v{form.version})</span>
              )}
            </p>
          </div>
          <div className="flex items-center gap-3 flex-wrap">
            <Link href="/enrollment" className="btn btn-outline">
              <svg
                className="mr-2 h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M10 19l-7-7m0 0l7-7m-7 7h18"
                />
              </svg>
              Back to List
            </Link>
            {canEdit && (
              <button type="button" onClick={handleEdit} className="btn btn-primary">
                <svg
                  className="mr-2 h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                  />
                </svg>
                Edit Form
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Rejection Notice */}
      {form.status === 'Rejected' && form.rejectionReason && (
        <div className="mb-6 rounded-lg bg-red-50 border border-red-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-red-400 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <div className="ml-3">
              <h3 className="text-sm font-semibold text-red-800">
                Form Rejected
              </h3>
              <p className="mt-1 text-sm text-red-700">{form.rejectionReason}</p>
              <p className="mt-2 text-sm text-red-600">
                Please review and edit the form to address the issues, then
                resubmit.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* PDF Preview */}
      <div className="mb-6">
        <EnrollmentPdfPreview form={form} variant="inline" showFormInfo={false} />
      </div>

      {/* Form Sections */}
      <div className="space-y-6">
        <ChildInfoSection form={form} />

        {form.parents && <ParentInfoSection parents={form.parents} />}

        {form.authorizedPickups && (
          <AuthorizedPickupsSection pickups={form.authorizedPickups} />
        )}

        {form.emergencyContacts && (
          <EmergencyContactsSection contacts={form.emergencyContacts} />
        )}

        {form.healthInfo && <HealthInfoSection health={form.healthInfo} />}

        {form.nutritionInfo && (
          <NutritionInfoSection nutrition={form.nutritionInfo} />
        )}

        {form.attendancePattern && (
          <AttendancePatternSection attendance={form.attendancePattern} />
        )}

        {form.signatures && <SignaturesSection signatures={form.signatures} />}

        <FormMetadataSection form={form} />
      </div>

      {/* Bottom Actions */}
      <div className="mt-8 flex flex-wrap justify-between gap-4 pt-6 border-t border-gray-200">
        <Link href="/enrollment" className="btn btn-outline">
          <svg
            className="mr-2 h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M10 19l-7-7m0 0l7-7m-7 7h18"
            />
          </svg>
          Back to Enrollment List
        </Link>
        <div className="flex gap-3">
          {canEdit && (
            <button type="button" onClick={handleEdit} className="btn btn-primary">
              <svg
                className="mr-2 h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              Edit Form
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
