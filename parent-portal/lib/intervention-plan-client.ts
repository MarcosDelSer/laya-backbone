/**
 * Type-safe Intervention Plan API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the AI service backend
 * for intervention plan management, progress tracking, and parent signatures.
 */

import { aiServiceClient, ApiError } from './api';
import type {
  InterventionPlan,
  InterventionPlanReviewReminder,
  InterventionPlanSummary,
  InterventionPlanStatus,
  InterventionProgress,
  PaginatedResponse,
  PaginationParams,
  ReviewSchedule,
  SignInterventionPlanRequest,
  SignInterventionPlanResponse,
} from './types';

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  // Intervention Plans
  PLANS: '/api/v1/intervention-plans',
  PLAN: (id: string) => `/api/v1/intervention-plans/${id}`,
  PLAN_HISTORY: (id: string) => `/api/v1/intervention-plans/${id}/history`,

  // Progress
  PLAN_PROGRESS: (id: string) => `/api/v1/intervention-plans/${id}/progress`,

  // Parent Signature
  PLAN_SIGN: (id: string) => `/api/v1/intervention-plans/${id}/sign`,

  // Review Reminders
  PENDING_REVIEW: '/api/v1/intervention-plans/pending-review',

  // Health Check
  HEALTH: '/api/v1/intervention-plans/health',
} as const;

// ============================================================================
// Health Check
// ============================================================================

/**
 * Health check response for intervention plan service.
 */
export interface InterventionPlanHealthResponse {
  status: string;
  service: string;
}

/**
 * Check if the intervention plan service is healthy and accessible.
 */
export async function checkInterventionPlanHealth(): Promise<InterventionPlanHealthResponse> {
  return aiServiceClient.get<InterventionPlanHealthResponse>(ENDPOINTS.HEALTH);
}

/**
 * Check if the intervention plan service is available.
 * Returns true if healthy, false otherwise.
 */
export async function isInterventionPlanServiceAvailable(): Promise<boolean> {
  try {
    const response = await checkInterventionPlanHealth();
    return response.status === 'healthy';
  } catch {
    return false;
  }
}

// ============================================================================
// Intervention Plan API
// ============================================================================

/**
 * Parameters for fetching intervention plans.
 */
export interface InterventionPlanParams extends PaginationParams {
  childId?: string;
  status?: InterventionPlanStatus;
  reviewSchedule?: ReviewSchedule;
  parentSigned?: boolean;
  needsReview?: boolean;
}

/**
 * Fetch intervention plans with optional filters.
 * For parents, this returns plans for their children.
 */
export async function getInterventionPlans(
  params?: InterventionPlanParams
): Promise<PaginatedResponse<InterventionPlanSummary>> {
  return aiServiceClient.get<PaginatedResponse<InterventionPlanSummary>>(
    ENDPOINTS.PLANS,
    {
      params: {
        skip: params?.skip,
        limit: params?.limit,
        child_id: params?.childId,
        status: params?.status,
        review_schedule: params?.reviewSchedule,
        parent_signed: params?.parentSigned,
        needs_review: params?.needsReview,
      },
    }
  );
}

/**
 * Fetch intervention plans for a specific child.
 */
export async function getInterventionPlansForChild(
  childId: string,
  params?: Omit<InterventionPlanParams, 'childId'>
): Promise<PaginatedResponse<InterventionPlanSummary>> {
  return getInterventionPlans({ ...params, childId });
}

/**
 * Fetch active intervention plans for a child.
 */
export async function getActiveInterventionPlans(
  childId: string
): Promise<PaginatedResponse<InterventionPlanSummary>> {
  return getInterventionPlans({ childId, status: 'active' });
}

/**
 * Fetch a specific intervention plan by ID.
 * Returns the complete plan with all 8 sections.
 */
export async function getInterventionPlan(planId: string): Promise<InterventionPlan> {
  return aiServiceClient.get<InterventionPlan>(ENDPOINTS.PLAN(planId));
}

/**
 * Fetch intervention plan version history.
 */
export async function getInterventionPlanHistory(
  planId: string
): Promise<InterventionPlan[]> {
  return aiServiceClient.get<InterventionPlan[]>(ENDPOINTS.PLAN_HISTORY(planId));
}

// ============================================================================
// Progress API
// ============================================================================

/**
 * Fetch progress records for an intervention plan.
 */
export async function getInterventionPlanProgress(
  planId: string,
  params?: PaginationParams
): Promise<PaginatedResponse<InterventionProgress>> {
  return aiServiceClient.get<PaginatedResponse<InterventionProgress>>(
    ENDPOINTS.PLAN_PROGRESS(planId),
    {
      params: {
        skip: params?.skip,
        limit: params?.limit,
      },
    }
  );
}

/**
 * Fetch all progress records for a plan (no pagination).
 */
export async function getAllProgressForPlan(
  planId: string
): Promise<InterventionProgress[]> {
  const response = await getInterventionPlanProgress(planId, { limit: 1000 });
  return response.items;
}

// ============================================================================
// Parent Signature API
// ============================================================================

/**
 * Sign an intervention plan as a parent.
 */
export async function signInterventionPlan(
  planId: string,
  request: SignInterventionPlanRequest
): Promise<SignInterventionPlanResponse> {
  return aiServiceClient.post<SignInterventionPlanResponse>(
    ENDPOINTS.PLAN_SIGN(planId),
    {
      signature_data: request.signatureData,
      agreed_to_terms: request.agreedToTerms,
    }
  );
}

// ============================================================================
// Review Reminders API
// ============================================================================

/**
 * Parameters for fetching plans pending review.
 */
export interface PendingReviewParams {
  daysAhead?: number;
  includeOverdue?: boolean;
}

/**
 * Fetch intervention plans that are pending review.
 * Useful for showing parents which plans are due for review.
 */
export async function getPlansForReview(
  params?: PendingReviewParams
): Promise<InterventionPlanReviewReminder[]> {
  return aiServiceClient.get<InterventionPlanReviewReminder[]>(
    ENDPOINTS.PENDING_REVIEW,
    {
      params: {
        days_ahead: params?.daysAhead ?? 30,
        include_overdue: params?.includeOverdue ?? true,
      },
    }
  );
}

/**
 * Get review reminders for a specific child.
 */
export async function getReviewRemindersForChild(
  childId: string
): Promise<InterventionPlanReviewReminder[]> {
  const reminders = await getPlansForReview();
  return reminders.filter((reminder) => reminder.childId === childId);
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get plans requiring parent signature.
 */
export async function getPlansAwaitingSignature(
  childId?: string
): Promise<InterventionPlanSummary[]> {
  const response = await getInterventionPlans({
    childId,
    parentSigned: false,
    status: 'active',
  });
  return response.items;
}

/**
 * Get count of unsigned plans for badge display.
 */
export async function getUnsignedPlanCount(childId?: string): Promise<number> {
  const plans = await getPlansAwaitingSignature(childId);
  return plans.length;
}

/**
 * Get plans with upcoming or overdue reviews for badge display.
 */
export async function getPendingReviewCount(): Promise<number> {
  const reminders = await getPlansForReview({ daysAhead: 7 });
  return reminders.length;
}

// ============================================================================
// Batch Operations
// ============================================================================

/**
 * Summary of intervention plans for a child.
 */
export interface ChildInterventionPlanSummary {
  childId: string;
  activePlans: InterventionPlanSummary[];
  pendingSignature: InterventionPlanSummary[];
  upcomingReviews: InterventionPlanReviewReminder[];
  totalPlans: number;
}

/**
 * Fetch comprehensive intervention plan summary for a child.
 * Combines multiple API calls for efficient data loading.
 */
export async function getChildInterventionPlanSummary(
  childId: string
): Promise<ChildInterventionPlanSummary> {
  const [allPlans, pendingSignature, reviewReminders] = await Promise.allSettled([
    getInterventionPlansForChild(childId),
    getPlansAwaitingSignature(childId),
    getReviewRemindersForChild(childId),
  ]);

  return {
    childId,
    activePlans:
      allPlans.status === 'fulfilled'
        ? allPlans.value.items.filter((p) => p.status === 'active')
        : [],
    pendingSignature:
      pendingSignature.status === 'fulfilled' ? pendingSignature.value : [],
    upcomingReviews:
      reviewReminders.status === 'fulfilled' ? reviewReminders.value : [],
    totalPlans:
      allPlans.status === 'fulfilled' ? allPlans.value.total : 0,
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
 * Get user-friendly error message for intervention plan operations.
 */
export function getErrorMessage(error: unknown): string {
  if (isApiError(error)) {
    if (error.isNotFound) {
      return 'The intervention plan was not found.';
    }
    if (error.isForbidden) {
      return 'You do not have permission to access this intervention plan.';
    }
    if (error.isValidationError) {
      return error.userMessage;
    }
    return error.userMessage;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred while accessing intervention plan data.';
}

/**
 * Wrap an intervention plan operation with fallback behavior.
 */
export async function withFallback<T>(
  operation: () => Promise<T>,
  fallback: T
): Promise<T> {
  try {
    return await operation();
  } catch (error) {
    if (isApiError(error) && error.isServerError) {
      return fallback;
    }
    throw error;
  }
}
