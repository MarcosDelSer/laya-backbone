/**
 * Type-safe Medical API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the AI Service backend
 * for allergies, medications, accommodation plans, and medical alerts.
 */

import { aiServiceClient, ApiError } from './api';
import type {
  AllergyInfo,
  MedicationInfo,
  AccommodationPlan,
  MedicalAlert,
  ChildMedicalSummary,
  AllergenType,
  AllergySeverity,
  MedicationType,
  AdministeredBy,
  AccommodationPlanType,
  AccommodationPlanStatus,
  AlertType,
  AlertLevel,
  PaginatedResponse,
  PaginationParams,
} from './types';

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  // Allergies
  ALLERGIES: '/api/v1/medical/allergies',
  ALLERGY: (id: string) => `/api/v1/medical/allergies/${id}`,
  CHILD_ALLERGIES: (childId: string) => `/api/v1/medical/children/${childId}/allergies`,
  VERIFY_ALLERGY: (id: string) => `/api/v1/medical/allergies/${id}/verify`,
  DEACTIVATE_ALLERGY: (id: string) => `/api/v1/medical/allergies/${id}/deactivate`,

  // Medications
  MEDICATIONS: '/api/v1/medical/medications',
  MEDICATION: (id: string) => `/api/v1/medical/medications/${id}`,
  CHILD_MEDICATIONS: (childId: string) => `/api/v1/medical/children/${childId}/medications`,
  EXPIRING_MEDICATIONS: '/api/v1/medical/medications/expiring',

  // Accommodation Plans
  ACCOMMODATION_PLANS: '/api/v1/medical/accommodation-plans',
  ACCOMMODATION_PLAN: (id: string) => `/api/v1/medical/accommodation-plans/${id}`,
  CHILD_ACCOMMODATION_PLANS: (childId: string) => `/api/v1/medical/children/${childId}/accommodation-plans`,

  // Medical Alerts
  ALERTS: '/api/v1/medical/alerts',
  ALERT: (id: string) => `/api/v1/medical/alerts/${id}`,
  CHILD_ALERTS: (childId: string) => `/api/v1/medical/children/${childId}/alerts`,

  // Allergen Detection
  DETECT_ALLERGENS: '/api/v1/medical/detect-allergens',

  // Child Medical Summary
  CHILD_SUMMARY: (childId: string) => `/api/v1/medical/children/${childId}/summary`,
} as const;

// ============================================================================
// Allergy API
// ============================================================================

/**
 * Parameters for fetching allergies.
 */
export interface AllergyParams extends PaginationParams {
  childId?: string;
  allergenType?: AllergenType;
  severity?: AllergySeverity;
  isActive?: boolean;
  epiPenRequired?: boolean;
}

/**
 * Fetch allergies with optional filters.
 */
export async function getAllergies(
  params?: AllergyParams
): Promise<PaginatedResponse<AllergyInfo>> {
  return aiServiceClient.get<PaginatedResponse<AllergyInfo>>(ENDPOINTS.ALLERGIES, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      allergen_type: params?.allergenType,
      severity: params?.severity,
      is_active: params?.isActive,
      epi_pen_required: params?.epiPenRequired,
    },
  });
}

/**
 * Fetch a specific allergy by ID.
 */
export async function getAllergy(allergyId: string): Promise<AllergyInfo> {
  return aiServiceClient.get<AllergyInfo>(ENDPOINTS.ALLERGY(allergyId));
}

/**
 * Fetch all allergies for a specific child.
 */
export async function getChildAllergies(childId: string): Promise<AllergyInfo[]> {
  return aiServiceClient.get<AllergyInfo[]>(ENDPOINTS.CHILD_ALLERGIES(childId));
}

/**
 * Get active allergies for a child.
 */
export async function getActiveAllergies(childId: string): Promise<AllergyInfo[]> {
  const allergies = await getChildAllergies(childId);
  return allergies.filter(allergy => allergy.isActive);
}

/**
 * Get severe allergies for a child.
 */
export async function getSevereAllergies(childId: string): Promise<AllergyInfo[]> {
  const allergies = await getChildAllergies(childId);
  return allergies.filter(
    allergy => allergy.isActive &&
    (allergy.severity === 'severe' || allergy.severity === 'life_threatening')
  );
}

/**
 * Check if a child requires an EpiPen.
 */
export async function childRequiresEpiPen(childId: string): Promise<boolean> {
  const allergies = await getChildAllergies(childId);
  return allergies.some(allergy => allergy.isActive && allergy.epiPenRequired);
}

// ============================================================================
// Medication API
// ============================================================================

/**
 * Parameters for fetching medications.
 */
export interface MedicationParams extends PaginationParams {
  childId?: string;
  medicationType?: MedicationType;
  administeredBy?: AdministeredBy;
  isActive?: boolean;
  expiringWithinDays?: number;
}

/**
 * Fetch medications with optional filters.
 */
export async function getMedications(
  params?: MedicationParams
): Promise<PaginatedResponse<MedicationInfo>> {
  return aiServiceClient.get<PaginatedResponse<MedicationInfo>>(ENDPOINTS.MEDICATIONS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      medication_type: params?.medicationType,
      administered_by: params?.administeredBy,
      is_active: params?.isActive,
      expiring_within_days: params?.expiringWithinDays,
    },
  });
}

/**
 * Fetch a specific medication by ID.
 */
export async function getMedication(medicationId: string): Promise<MedicationInfo> {
  return aiServiceClient.get<MedicationInfo>(ENDPOINTS.MEDICATION(medicationId));
}

/**
 * Fetch all medications for a specific child.
 */
export async function getChildMedications(childId: string): Promise<MedicationInfo[]> {
  return aiServiceClient.get<MedicationInfo[]>(ENDPOINTS.CHILD_MEDICATIONS(childId));
}

/**
 * Fetch medications that are expiring soon.
 */
export async function getExpiringMedications(
  daysAhead: number = 30
): Promise<MedicationInfo[]> {
  return aiServiceClient.get<MedicationInfo[]>(ENDPOINTS.EXPIRING_MEDICATIONS, {
    params: {
      days_ahead: daysAhead,
    },
  });
}

/**
 * Get active medications for a child.
 */
export async function getActiveMedications(childId: string): Promise<MedicationInfo[]> {
  const medications = await getChildMedications(childId);
  return medications.filter(med => med.isActive);
}

/**
 * Get staff-administered medications for a child.
 */
export async function getStaffAdministeredMedications(childId: string): Promise<MedicationInfo[]> {
  const medications = await getChildMedications(childId);
  return medications.filter(
    med => med.isActive && (med.administeredBy === 'staff' || med.administeredBy === 'nurse')
  );
}

// ============================================================================
// Accommodation Plan API
// ============================================================================

/**
 * Parameters for fetching accommodation plans.
 */
export interface AccommodationPlanParams extends PaginationParams {
  childId?: string;
  planType?: AccommodationPlanType;
  status?: AccommodationPlanStatus;
}

/**
 * Fetch accommodation plans with optional filters.
 */
export async function getAccommodationPlans(
  params?: AccommodationPlanParams
): Promise<PaginatedResponse<AccommodationPlan>> {
  return aiServiceClient.get<PaginatedResponse<AccommodationPlan>>(ENDPOINTS.ACCOMMODATION_PLANS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      plan_type: params?.planType,
      status: params?.status,
    },
  });
}

/**
 * Fetch a specific accommodation plan by ID.
 */
export async function getAccommodationPlan(planId: string): Promise<AccommodationPlan> {
  return aiServiceClient.get<AccommodationPlan>(ENDPOINTS.ACCOMMODATION_PLAN(planId));
}

/**
 * Fetch all accommodation plans for a specific child.
 */
export async function getChildAccommodationPlans(childId: string): Promise<AccommodationPlan[]> {
  return aiServiceClient.get<AccommodationPlan[]>(ENDPOINTS.CHILD_ACCOMMODATION_PLANS(childId));
}

/**
 * Get approved accommodation plans for a child.
 */
export async function getApprovedAccommodationPlans(childId: string): Promise<AccommodationPlan[]> {
  const plans = await getChildAccommodationPlans(childId);
  return plans.filter(plan => plan.status === 'approved');
}

/**
 * Get emergency plans for a child.
 */
export async function getEmergencyPlans(childId: string): Promise<AccommodationPlan[]> {
  const plans = await getChildAccommodationPlans(childId);
  return plans.filter(
    plan => plan.status === 'approved' && plan.planType === 'emergency_plan'
  );
}

/**
 * Get dietary plans for a child.
 */
export async function getDietaryPlans(childId: string): Promise<AccommodationPlan[]> {
  const plans = await getChildAccommodationPlans(childId);
  return plans.filter(
    plan => plan.status === 'approved' && plan.planType === 'dietary_plan'
  );
}

// ============================================================================
// Medical Alert API
// ============================================================================

/**
 * Parameters for fetching medical alerts.
 */
export interface MedicalAlertParams extends PaginationParams {
  childId?: string;
  alertType?: AlertType;
  alertLevel?: AlertLevel;
  isActive?: boolean;
}

/**
 * Fetch medical alerts with optional filters.
 */
export async function getMedicalAlerts(
  params?: MedicalAlertParams
): Promise<PaginatedResponse<MedicalAlert>> {
  return aiServiceClient.get<PaginatedResponse<MedicalAlert>>(ENDPOINTS.ALERTS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      alert_type: params?.alertType,
      alert_level: params?.alertLevel,
      is_active: params?.isActive,
    },
  });
}

/**
 * Fetch a specific medical alert by ID.
 */
export async function getMedicalAlert(alertId: string): Promise<MedicalAlert> {
  return aiServiceClient.get<MedicalAlert>(ENDPOINTS.ALERT(alertId));
}

/**
 * Fetch all medical alerts for a specific child.
 */
export async function getChildMedicalAlerts(childId: string): Promise<MedicalAlert[]> {
  return aiServiceClient.get<MedicalAlert[]>(ENDPOINTS.CHILD_ALERTS(childId));
}

/**
 * Get active alerts for a child.
 */
export async function getActiveAlerts(childId: string): Promise<MedicalAlert[]> {
  const alerts = await getChildMedicalAlerts(childId);
  return alerts.filter(alert => alert.isActive);
}

/**
 * Get critical alerts for a child.
 */
export async function getCriticalAlerts(childId: string): Promise<MedicalAlert[]> {
  const alerts = await getChildMedicalAlerts(childId);
  return alerts.filter(
    alert => alert.isActive && alert.alertLevel === 'critical'
  );
}

// ============================================================================
// Allergen Detection API
// ============================================================================

/**
 * Request for detecting allergens in meal items.
 */
export interface AllergenDetectionRequest {
  childId: string;
  mealItems: string[];
}

/**
 * Detected allergen in a meal item.
 */
export interface DetectedAllergen {
  allergenName: string;
  mealItem: string;
  severity: AllergySeverity;
  allergyId: string;
}

/**
 * Response from allergen detection.
 */
export interface AllergenDetectionResponse {
  childId: string;
  allergensDetected: DetectedAllergen[];
  hasSevereAllergen: boolean;
  recommendedAction?: string;
}

/**
 * Detect potential allergens in meal items for a child.
 */
export async function detectAllergens(
  request: AllergenDetectionRequest
): Promise<AllergenDetectionResponse> {
  return aiServiceClient.post<AllergenDetectionResponse>(ENDPOINTS.DETECT_ALLERGENS, {
    child_id: request.childId,
    meal_items: request.mealItems,
  });
}

// ============================================================================
// Child Medical Summary API
// ============================================================================

/**
 * Fetch complete medical summary for a child.
 */
export async function getChildMedicalSummary(childId: string): Promise<ChildMedicalSummary> {
  return aiServiceClient.get<ChildMedicalSummary>(ENDPOINTS.CHILD_SUMMARY(childId));
}

/**
 * Check if a child has any medical conditions requiring attention.
 */
export async function childHasMedicalNeeds(childId: string): Promise<boolean> {
  const summary = await getChildMedicalSummary(childId);
  return (
    summary.allergies.length > 0 ||
    summary.medications.length > 0 ||
    summary.accommodationPlans.length > 0 ||
    summary.activeAlerts.length > 0
  );
}

/**
 * Get a quick medical status for a child.
 */
export interface MedicalStatus {
  childId: string;
  hasSevereAllergies: boolean;
  hasEpiPen: boolean;
  hasStaffAdministeredMedications: boolean;
  activeAlertCount: number;
  requiresSpecialAttention: boolean;
}

export async function getChildMedicalStatus(childId: string): Promise<MedicalStatus> {
  const summary = await getChildMedicalSummary(childId);
  return {
    childId: summary.childId,
    hasSevereAllergies: summary.hasSevereAllergies,
    hasEpiPen: summary.hasEpiPen,
    hasStaffAdministeredMedications: summary.hasStaffAdministeredMedications,
    activeAlertCount: summary.activeAlerts.length,
    requiresSpecialAttention:
      summary.hasSevereAllergies ||
      summary.hasEpiPen ||
      summary.hasStaffAdministeredMedications ||
      summary.activeAlerts.some(alert => alert.alertLevel === 'critical'),
  };
}

// ============================================================================
// Error Handling Helpers
// ============================================================================

/**
 * Check if an error is an API error.
 */
export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/**
 * Get user-friendly error message.
 */
export function getErrorMessage(error: unknown): string {
  if (isApiError(error)) {
    return error.userMessage;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred.';
}
