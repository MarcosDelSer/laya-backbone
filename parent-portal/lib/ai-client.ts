/**
 * Type-safe AI Service API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the AI service backend
 * for activity recommendations, coaching guidance, and analytics.
 */

import { aiServiceClient, ApiError } from './api';
import type {
  Activity,
  ActivityRecommendationRequest,
  ActivityRecommendationResponse,
  ActivityType,
  Coaching,
  CoachingCategory,
  CoachingGuidanceRequest,
  CoachingGuidanceResponse,
  HealthCheckResponse,
  PaginatedResponse,
  PaginationParams,
  SpecialNeedType,
} from './types';

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  // Health Check
  HEALTH: '/',

  // Activities
  ACTIVITIES: '/api/v1/activities',
  ACTIVITY: (id: string) => `/api/v1/activities/${id}`,
  ACTIVITY_RECOMMENDATIONS: '/api/v1/activities/recommendations',

  // Coaching
  COACHING: '/api/v1/coaching',
  COACHING_ITEM: (id: string) => `/api/v1/coaching/${id}`,
  COACHING_GUIDANCE: '/api/v1/coaching/guidance',

  // Analytics
  CHILD_ANALYTICS: (childId: string) => `/api/v1/analytics/children/${childId}`,
  PROGRESS_REPORT: (childId: string) => `/api/v1/analytics/children/${childId}/progress`,
} as const;

// ============================================================================
// Health Check
// ============================================================================

/**
 * Check if the AI service is healthy and accessible.
 */
export async function checkHealth(): Promise<HealthCheckResponse> {
  return aiServiceClient.get<HealthCheckResponse>(ENDPOINTS.HEALTH);
}

/**
 * Check if the AI service is available.
 * Returns true if healthy, false otherwise.
 */
export async function isServiceAvailable(): Promise<boolean> {
  try {
    const response = await checkHealth();
    return response.status === 'healthy';
  } catch {
    return false;
  }
}

// ============================================================================
// Activity API
// ============================================================================

/**
 * Parameters for fetching activities.
 */
export interface ActivityParams extends PaginationParams {
  activityType?: ActivityType;
  difficulty?: 'easy' | 'medium' | 'hard';
  isActive?: boolean;
}

/**
 * Fetch activities with optional filters.
 */
export async function getActivities(
  params?: ActivityParams
): Promise<PaginatedResponse<Activity>> {
  return aiServiceClient.get<PaginatedResponse<Activity>>(ENDPOINTS.ACTIVITIES, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      activity_type: params?.activityType,
      difficulty: params?.difficulty,
      is_active: params?.isActive,
    },
  });
}

/**
 * Fetch a specific activity by ID.
 */
export async function getActivity(activityId: string): Promise<Activity> {
  return aiServiceClient.get<Activity>(ENDPOINTS.ACTIVITY(activityId));
}

/**
 * Get personalized activity recommendations for a child.
 */
export async function getActivityRecommendations(
  request: ActivityRecommendationRequest
): Promise<ActivityRecommendationResponse> {
  return aiServiceClient.post<ActivityRecommendationResponse>(
    ENDPOINTS.ACTIVITY_RECOMMENDATIONS,
    {
      child_id: request.childId,
      activity_types: request.activityTypes,
      max_recommendations: request.maxRecommendations ?? 5,
      include_special_needs: request.includeSpecialNeeds ?? true,
    }
  );
}

/**
 * Get activity recommendations for multiple activity types.
 */
export async function getMultiTypeRecommendations(
  childId: string,
  types: ActivityType[],
  maxPerType: number = 3
): Promise<Map<ActivityType, ActivityRecommendationResponse>> {
  const results = new Map<ActivityType, ActivityRecommendationResponse>();

  const promises = types.map(async (type) => {
    const response = await getActivityRecommendations({
      childId,
      activityTypes: [type],
      maxRecommendations: maxPerType,
    });
    return { type, response };
  });

  const responses = await Promise.all(promises);

  for (const { type, response } of responses) {
    results.set(type, response);
  }

  return results;
}

// ============================================================================
// Coaching API
// ============================================================================

/**
 * Parameters for fetching coaching items.
 */
export interface CoachingParams extends PaginationParams {
  category?: CoachingCategory;
  specialNeedTypes?: SpecialNeedType[];
  isPublished?: boolean;
}

/**
 * Fetch coaching items with optional filters.
 */
export async function getCoachingItems(
  params?: CoachingParams
): Promise<PaginatedResponse<Coaching>> {
  return aiServiceClient.get<PaginatedResponse<Coaching>>(ENDPOINTS.COACHING, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      category: params?.category,
      special_need_types: params?.specialNeedTypes?.join(','),
      is_published: params?.isPublished,
    },
  });
}

/**
 * Fetch a specific coaching item by ID.
 */
export async function getCoachingItem(coachingId: string): Promise<Coaching> {
  return aiServiceClient.get<Coaching>(ENDPOINTS.COACHING_ITEM(coachingId));
}

/**
 * Get personalized coaching guidance for a child.
 */
export async function getCoachingGuidance(
  request: CoachingGuidanceRequest
): Promise<CoachingGuidanceResponse> {
  return aiServiceClient.post<CoachingGuidanceResponse>(
    ENDPOINTS.COACHING_GUIDANCE,
    {
      child_id: request.childId,
      special_need_types: request.specialNeedTypes,
      situation_description: request.situationDescription,
      category: request.category,
      max_recommendations: request.maxRecommendations ?? 5,
    }
  );
}

/**
 * Get coaching guidance for a specific category.
 */
export async function getCategoryGuidance(
  childId: string,
  specialNeedTypes: SpecialNeedType[],
  category: CoachingCategory,
  maxItems: number = 5
): Promise<CoachingGuidanceResponse> {
  return getCoachingGuidance({
    childId,
    specialNeedTypes,
    category,
    maxRecommendations: maxItems,
  });
}

// ============================================================================
// Analytics API
// ============================================================================

/**
 * Child analytics summary.
 */
export interface ChildAnalytics {
  childId: string;
  periodStart: string;
  periodEnd: string;
  activitiesCompleted: number;
  averageEngagementScore: number;
  skillProgressByType: Record<ActivityType, number>;
  recommendedFocusAreas: ActivityType[];
  generatedAt: string;
}

/**
 * Progress report for a child.
 */
export interface ProgressReport {
  childId: string;
  reportPeriod: string;
  overallProgress: number;
  strengthAreas: string[];
  improvementAreas: string[];
  recommendations: string[];
  milestones: {
    name: string;
    achieved: boolean;
    achievedAt?: string;
  }[];
  generatedAt: string;
}

/**
 * Fetch analytics for a child.
 */
export async function getChildAnalytics(
  childId: string,
  periodDays: number = 30
): Promise<ChildAnalytics> {
  return aiServiceClient.get<ChildAnalytics>(ENDPOINTS.CHILD_ANALYTICS(childId), {
    params: {
      period_days: periodDays,
    },
  });
}

/**
 * Generate a progress report for a child.
 */
export async function getProgressReport(
  childId: string,
  reportPeriod: 'weekly' | 'monthly' | 'quarterly' = 'monthly'
): Promise<ProgressReport> {
  return aiServiceClient.get<ProgressReport>(ENDPOINTS.PROGRESS_REPORT(childId), {
    params: {
      report_period: reportPeriod,
    },
  });
}

// ============================================================================
// Batch Operations
// ============================================================================

/**
 * Fetch recommendations and guidance for a child in one call.
 */
export interface ChildInsights {
  recommendations: ActivityRecommendationResponse;
  guidance: CoachingGuidanceResponse | null;
  analytics: ChildAnalytics | null;
}

export async function getChildInsights(
  childId: string,
  specialNeedTypes?: SpecialNeedType[]
): Promise<ChildInsights> {
  const [recommendations, guidance, analytics] = await Promise.allSettled([
    getActivityRecommendations({
      childId,
      maxRecommendations: 5,
    }),
    specialNeedTypes && specialNeedTypes.length > 0
      ? getCoachingGuidance({
          childId,
          specialNeedTypes,
          maxRecommendations: 3,
        })
      : Promise.resolve(null),
    getChildAnalytics(childId).catch(() => null),
  ]);

  return {
    recommendations:
      recommendations.status === 'fulfilled'
        ? recommendations.value
        : {
            childId,
            recommendations: [],
            generatedAt: new Date().toISOString(),
          },
    guidance:
      guidance.status === 'fulfilled' ? guidance.value : null,
    analytics:
      analytics.status === 'fulfilled' ? analytics.value : null,
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
  return 'An unexpected error occurred while communicating with the AI service.';
}

/**
 * Wrap an AI service call with fallback behavior.
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
