import Link from 'next/link';
import { AllergyCard } from '@/components/AllergyCard';
import { MedicationCard } from '@/components/MedicationCard';
import { MedicalAlert } from '@/components/MedicalAlert';
import type {
  AllergyInfo,
  MedicationInfo,
  AccommodationPlan,
  MedicalAlert as MedicalAlertType,
  ChildMedicalSummary,
} from '@/lib/types';

// Mock data for medical information - will be replaced with API calls
const mockMedicalSummary: ChildMedicalSummary = {
  childId: 'child-1',
  allergies: [
    {
      id: 'allergy-1',
      childId: 'child-1',
      allergenName: 'Peanuts',
      allergenType: 'food',
      severity: 'life_threatening',
      reaction: 'Anaphylaxis, difficulty breathing, swelling',
      treatment: 'Administer EpiPen immediately, call 911',
      epiPenRequired: true,
      epiPenLocation: 'Front office and classroom',
      diagnosedDate: '2023-03-15',
      diagnosedBy: 'Dr. Sarah Johnson',
      notes: 'Avoid all tree nuts as precaution',
      isVerified: true,
      verifiedById: 'staff-1',
      verifiedDate: '2023-03-20',
      isActive: true,
    },
    {
      id: 'allergy-2',
      childId: 'child-1',
      allergenName: 'Dairy',
      allergenType: 'food',
      severity: 'moderate',
      reaction: 'Stomach upset, skin rash',
      treatment: 'Antihistamine as needed',
      epiPenRequired: false,
      notes: 'Can tolerate small amounts of baked dairy',
      isVerified: true,
      verifiedById: 'staff-1',
      verifiedDate: '2023-04-01',
      isActive: true,
    },
    {
      id: 'allergy-3',
      childId: 'child-1',
      allergenName: 'Bee Stings',
      allergenType: 'insect',
      severity: 'severe',
      reaction: 'Severe swelling, hives',
      treatment: 'Benadryl, ice pack, monitor closely',
      epiPenRequired: false,
      isVerified: false,
      isActive: true,
    },
  ],
  medications: [
    {
      id: 'med-1',
      childId: 'child-1',
      medicationName: 'Children\'s Zyrtec',
      medicationType: 'over_the_counter',
      dosage: '5mg',
      frequency: 'Once daily in the morning',
      route: 'oral',
      purpose: 'Seasonal allergies',
      sideEffects: 'May cause drowsiness',
      storageLocation: 'Classroom medicine cabinet',
      administeredBy: 'staff',
      parentConsent: true,
      parentConsentDate: '2024-01-15',
      isVerified: true,
      verifiedById: 'staff-1',
      verifiedDate: '2024-01-16',
      isActive: true,
    },
    {
      id: 'med-2',
      childId: 'child-1',
      medicationName: 'EpiPen Jr.',
      medicationType: 'prescription',
      dosage: '0.15mg',
      frequency: 'As needed for anaphylaxis',
      route: 'injection',
      prescribedBy: 'Dr. Sarah Johnson',
      prescriptionDate: '2024-01-10',
      expirationDate: '2025-01-10',
      purpose: 'Emergency treatment for severe allergic reaction',
      storageLocation: 'Front office emergency kit',
      administeredBy: 'staff',
      notes: 'Check expiration monthly',
      parentConsent: true,
      parentConsentDate: '2024-01-15',
      isVerified: true,
      verifiedById: 'nurse-1',
      verifiedDate: '2024-01-16',
      isActive: true,
    },
  ],
  accommodationPlans: [
    {
      id: 'plan-1',
      childId: 'child-1',
      planType: 'health_plan',
      planName: 'Peanut Allergy Management Plan',
      description: 'Comprehensive plan for managing severe peanut allergy in the classroom and during activities.',
      accommodations: 'Peanut-free table at lunch, separate snacks provided, staff trained in EpiPen administration',
      emergencyProcedures: '1. Administer EpiPen 2. Call 911 3. Contact parents 4. Monitor until help arrives',
      triggersSigns: 'Exposure to peanuts or tree nuts, symptoms include hives, swelling, difficulty breathing',
      staffNotifications: 'All classroom staff and cafeteria workers notified',
      effectiveDate: '2024-01-01',
      expirationDate: '2024-12-31',
      reviewDate: '2024-06-01',
      status: 'approved',
      approvedById: 'admin-1',
      approvedDate: '2024-01-05',
    },
    {
      id: 'plan-2',
      childId: 'child-1',
      planType: 'dietary_plan',
      planName: 'Dairy-Free Diet Accommodation',
      description: 'Dietary accommodations for dairy intolerance.',
      accommodations: 'Dairy-free alternatives provided for meals and snacks, parents notified of menu changes',
      effectiveDate: '2024-01-01',
      status: 'approved',
      approvedById: 'admin-1',
      approvedDate: '2024-01-05',
    },
  ],
  activeAlerts: [
    {
      id: 'alert-1',
      childId: 'child-1',
      alertType: 'allergy',
      alertLevel: 'critical',
      title: 'Severe Peanut Allergy',
      description: 'Life-threatening peanut allergy. EpiPen required on site at all times.',
      actionRequired: 'Verify EpiPen location and expiration at start of each day',
      displayOnDashboard: true,
      displayOnAttendance: true,
      displayOnReports: true,
      notifyOnCheckIn: true,
      relatedAllergyId: 'allergy-1',
      isActive: true,
    },
    {
      id: 'alert-2',
      childId: 'child-1',
      alertType: 'medication',
      alertLevel: 'warning',
      title: 'Daily Allergy Medication',
      description: 'Zyrtec to be administered each morning before 10 AM.',
      actionRequired: 'Log administration in medication tracker',
      displayOnDashboard: true,
      displayOnAttendance: false,
      displayOnReports: true,
      notifyOnCheckIn: true,
      relatedMedicationId: 'med-1',
      isActive: true,
    },
  ],
  hasSevereAllergies: true,
  hasEpiPen: true,
  hasStaffAdministeredMedications: true,
  generatedAt: new Date().toISOString(),
};

// Helper to format plan status
const planStatusConfig: Record<string, { label: string; badgeClass: string }> = {
  draft: { label: 'Draft', badgeClass: 'badge-neutral' },
  pending_approval: { label: 'Pending Approval', badgeClass: 'badge-warning' },
  approved: { label: 'Approved', badgeClass: 'badge-success' },
  expired: { label: 'Expired', badgeClass: 'badge-error' },
};

const planTypeLabels: Record<string, string> = {
  health_plan: 'Health Plan',
  emergency_plan: 'Emergency Plan',
  dietary_plan: 'Dietary Plan',
  behavioral_plan: 'Behavioral Plan',
  other: 'Other',
};

export default function MedicalPage() {
  const summary = mockMedicalSummary;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Medical Information</h1>
            <p className="mt-1 text-gray-600">
              View and manage your child&apos;s allergies, medications, and health plans
            </p>
          </div>
          <Link href="/" className="btn btn-outline">
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
            Back
          </Link>
        </div>
      </div>

      {/* Quick Status Badges */}
      {(summary.hasSevereAllergies || summary.hasEpiPen || summary.hasStaffAdministeredMedications) && (
        <div className="mb-6 flex flex-wrap items-center gap-2">
          {summary.hasSevereAllergies && (
            <span className="badge badge-error badge-lg">
              <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
              Severe Allergies
            </span>
          )}
          {summary.hasEpiPen && (
            <span className="badge badge-warning badge-lg">
              <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                />
              </svg>
              EpiPen Required
            </span>
          )}
          {summary.hasStaffAdministeredMedications && (
            <span className="badge badge-info badge-lg">
              <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              Staff-Administered Medications
            </span>
          )}
        </div>
      )}

      {/* Active Alerts Section */}
      {summary.activeAlerts.length > 0 && (
        <div className="mb-8">
          <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
            <h2 className="text-lg font-semibold text-gray-900">Active Alerts</h2>
            <span className="text-sm text-gray-500">{summary.activeAlerts.length} alert(s)</span>
          </div>
          <div className="space-y-4">
            {summary.activeAlerts.map((alert) => (
              <MedicalAlert key={alert.id} alert={alert} />
            ))}
          </div>
        </div>
      )}

      {/* Allergies Section */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                <svg className="h-4 w-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>
              </div>
              <h2 className="text-lg font-semibold text-gray-900">Allergies</h2>
            </div>
            <span className="text-sm text-gray-500">{summary.allergies.length} recorded</span>
          </div>
        </div>
        <div className="card-body">
          {summary.allergies.length > 0 ? (
            <div className="space-y-4 divide-y divide-gray-100">
              {summary.allergies.map((allergy, index) => (
                <div key={allergy.id} className={index > 0 ? 'pt-4' : ''}>
                  <AllergyCard allergy={allergy} />
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No allergies recorded</p>
          )}
        </div>
      </div>

      {/* Medications Section */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100">
                <svg className="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                  />
                </svg>
              </div>
              <h2 className="text-lg font-semibold text-gray-900">Medications</h2>
            </div>
            <span className="text-sm text-gray-500">{summary.medications.length} active</span>
          </div>
        </div>
        <div className="card-body">
          {summary.medications.length > 0 ? (
            <div className="space-y-4 divide-y divide-gray-100">
              {summary.medications.map((medication, index) => (
                <div key={medication.id} className={index > 0 ? 'pt-4' : ''}>
                  <MedicationCard medication={medication} />
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No medications recorded</p>
          )}
        </div>
      </div>

      {/* Accommodation Plans Section */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                <svg className="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
              </div>
              <h2 className="text-lg font-semibold text-gray-900">Accommodation Plans</h2>
            </div>
            <span className="text-sm text-gray-500">{summary.accommodationPlans.length} plan(s)</span>
          </div>
        </div>
        <div className="card-body">
          {summary.accommodationPlans.length > 0 ? (
            <div className="space-y-4 divide-y divide-gray-100">
              {summary.accommodationPlans.map((plan, index) => (
                <div key={plan.id} className={index > 0 ? 'pt-4' : ''}>
                  <div className="flex items-start space-x-3">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                      <svg className="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                      </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between">
                        <p className="font-medium text-gray-900">{plan.planName}</p>
                        <span className="text-sm text-gray-500">
                          {planTypeLabels[plan.planType] || plan.planType}
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{plan.description}</p>
                      {plan.accommodations && (
                        <p className="text-sm text-gray-600 mt-1">
                          <span className="font-medium">Accommodations:</span> {plan.accommodations}
                        </p>
                      )}
                      {plan.emergencyProcedures && (
                        <p className="text-sm text-gray-600 mt-1">
                          <span className="font-medium">Emergency Procedures:</span> {plan.emergencyProcedures}
                        </p>
                      )}
                      <div className="flex flex-wrap items-center gap-1 mt-2">
                        <span className={`badge ${planStatusConfig[plan.status]?.badgeClass || 'badge-neutral'}`}>
                          {planStatusConfig[plan.status]?.label || plan.status}
                        </span>
                        {plan.effectiveDate && (
                          <span className="badge badge-outline badge-sm">
                            Effective: {new Date(plan.effectiveDate).toLocaleDateString()}
                          </span>
                        )}
                        {plan.expirationDate && (
                          <span className="badge badge-outline badge-sm">
                            Expires: {new Date(plan.expirationDate).toLocaleDateString()}
                          </span>
                        )}
                        {plan.reviewDate && (
                          <span className="badge badge-ghost badge-sm">
                            Review: {new Date(plan.reviewDate).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No accommodation plans recorded</p>
          )}
        </div>
      </div>

      {/* Last Updated Info */}
      <div className="text-center text-sm text-gray-500">
        <p>
          Last updated: {new Date(summary.generatedAt).toLocaleString()}
        </p>
        <p className="mt-1">
          Contact your childcare provider to update medical information.
        </p>
      </div>
    </div>
  );
}
