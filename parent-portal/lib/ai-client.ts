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
  CreateDevelopmentProfileRequest,
  CreateMonthlySnapshotRequest,
  CreateObservationRequest,
  CreateSkillAssessmentRequest,
  DevelopmentalDomain,
  DevelopmentProfile,
  DevelopmentProfileSummary,
  GrowthTrajectory,
  HealthCheckResponse,
  MonthlySnapshot,
  Observation,
  ObserverType,
  OverallProgress,
  PaginatedResponse,
  PaginationParams,
  SkillAssessment,
  SkillStatus,
  SpecialNeedType,
  UpdateMonthlySnapshotRequest,
  UpdateObservationRequest,
  UpdateSkillAssessmentRequest,
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

  // Development Profiles
  DEVELOPMENT_PROFILES: '/api/v1/development-profiles',
  DEVELOPMENT_PROFILE: (profileId: string) => `/api/v1/development-profiles/${profileId}`,
  DEVELOPMENT_PROFILE_BY_CHILD: (childId: string) => `/api/v1/development-profiles/child/${childId}`,
  SKILL_ASSESSMENTS: (profileId: string) => `/api/v1/development-profiles/${profileId}/assessments`,
  SKILL_ASSESSMENT: (profileId: string, assessmentId: string) =>
    `/api/v1/development-profiles/${profileId}/assessments/${assessmentId}`,
  OBSERVATIONS: (profileId: string) => `/api/v1/development-profiles/${profileId}/observations`,
  OBSERVATION: (profileId: string, observationId: string) =>
    `/api/v1/development-profiles/${profileId}/observations/${observationId}`,
  MONTHLY_SNAPSHOTS: (profileId: string) => `/api/v1/development-profiles/${profileId}/snapshots`,
  MONTHLY_SNAPSHOT_GENERATE: (profileId: string) =>
    `/api/v1/development-profiles/${profileId}/snapshots/generate`,
  MONTHLY_SNAPSHOT: (profileId: string, snapshotId: string) =>
    `/api/v1/development-profiles/${profileId}/snapshots/${snapshotId}`,
  GROWTH_TRAJECTORY: (profileId: string) => `/api/v1/development-profiles/${profileId}/trajectory`,
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

// ============================================================================
// Development Profile API
// ============================================================================

/**
 * Parameters for fetching development profiles.
 */
export interface DevelopmentProfileParams extends PaginationParams {
  isActive?: boolean;
  educatorId?: string;
}

/**
 * Create a new development profile for a child.
 */
export async function createDevelopmentProfile(
  request: CreateDevelopmentProfileRequest
): Promise<DevelopmentProfile> {
  return aiServiceClient.post<DevelopmentProfile>(ENDPOINTS.DEVELOPMENT_PROFILES, {
    child_id: request.childId,
    educator_id: request.educatorId,
    birth_date: request.birthDate,
    notes: request.notes,
  });
}

/**
 * Fetch development profiles with optional filters.
 */
export async function getDevelopmentProfiles(
  params?: DevelopmentProfileParams
): Promise<PaginatedResponse<DevelopmentProfileSummary>> {
  return aiServiceClient.get<PaginatedResponse<DevelopmentProfileSummary>>(
    ENDPOINTS.DEVELOPMENT_PROFILES,
    {
      params: {
        skip: params?.skip,
        limit: params?.limit,
        is_active: params?.isActive,
        educator_id: params?.educatorId,
      },
    }
  );
}

/**
 * Fetch a development profile by child ID.
 */
export async function getDevelopmentProfileByChild(
  childId: string
): Promise<DevelopmentProfile> {
  return aiServiceClient.get<DevelopmentProfile>(
    ENDPOINTS.DEVELOPMENT_PROFILE_BY_CHILD(childId)
  );
}

/**
 * Fetch a specific development profile by ID.
 */
export async function getDevelopmentProfile(profileId: string): Promise<DevelopmentProfile> {
  return aiServiceClient.get<DevelopmentProfile>(ENDPOINTS.DEVELOPMENT_PROFILE(profileId));
}

/**
 * Update an existing development profile.
 */
export async function updateDevelopmentProfile(
  profileId: string,
  request: CreateDevelopmentProfileRequest
): Promise<DevelopmentProfile> {
  return aiServiceClient.put<DevelopmentProfile>(ENDPOINTS.DEVELOPMENT_PROFILE(profileId), {
    child_id: request.childId,
    educator_id: request.educatorId,
    birth_date: request.birthDate,
    notes: request.notes,
  });
}

/**
 * Delete a development profile.
 */
export async function deleteDevelopmentProfile(profileId: string): Promise<void> {
  return aiServiceClient.delete(ENDPOINTS.DEVELOPMENT_PROFILE(profileId));
}

// ============================================================================
// Skill Assessment API
// ============================================================================

/**
 * Parameters for fetching skill assessments.
 */
export interface SkillAssessmentParams extends PaginationParams {
  domain?: DevelopmentalDomain;
  status?: SkillStatus;
}

/**
 * Create a new skill assessment for a development profile.
 */
export async function createSkillAssessment(
  profileId: string,
  request: CreateSkillAssessmentRequest
): Promise<SkillAssessment> {
  return aiServiceClient.post<SkillAssessment>(ENDPOINTS.SKILL_ASSESSMENTS(profileId), {
    profile_id: request.profileId,
    domain: request.domain,
    skill_name: request.skillName,
    skill_name_fr: request.skillNameFr,
    status: request.status,
    evidence: request.evidence,
    assessed_by_id: request.assessedById,
  });
}

/**
 * Fetch skill assessments for a profile with optional filters.
 */
export async function getSkillAssessments(
  profileId: string,
  params?: SkillAssessmentParams
): Promise<PaginatedResponse<SkillAssessment>> {
  return aiServiceClient.get<PaginatedResponse<SkillAssessment>>(
    ENDPOINTS.SKILL_ASSESSMENTS(profileId),
    {
      params: {
        skip: params?.skip,
        limit: params?.limit,
        domain: params?.domain,
        status: params?.status,
      },
    }
  );
}

/**
 * Fetch a specific skill assessment by ID.
 */
export async function getSkillAssessment(
  profileId: string,
  assessmentId: string
): Promise<SkillAssessment> {
  return aiServiceClient.get<SkillAssessment>(
    ENDPOINTS.SKILL_ASSESSMENT(profileId, assessmentId)
  );
}

/**
 * Update an existing skill assessment.
 */
export async function updateSkillAssessment(
  profileId: string,
  assessmentId: string,
  request: UpdateSkillAssessmentRequest
): Promise<SkillAssessment> {
  return aiServiceClient.patch<SkillAssessment>(
    ENDPOINTS.SKILL_ASSESSMENT(profileId, assessmentId),
    {
      status: request.status,
      evidence: request.evidence,
      assessed_by_id: request.assessedById,
    }
  );
}

/**
 * Delete a skill assessment.
 */
export async function deleteSkillAssessment(
  profileId: string,
  assessmentId: string
): Promise<void> {
  return aiServiceClient.delete(ENDPOINTS.SKILL_ASSESSMENT(profileId, assessmentId));
}

// ============================================================================
// Observation API
// ============================================================================

/**
 * Parameters for fetching observations.
 */
export interface ObservationParams extends PaginationParams {
  domain?: DevelopmentalDomain;
  isMilestone?: boolean;
  isConcern?: boolean;
  observerType?: ObserverType;
}

/**
 * Create a new observation for a development profile.
 */
export async function createObservation(
  profileId: string,
  request: CreateObservationRequest
): Promise<Observation> {
  return aiServiceClient.post<Observation>(ENDPOINTS.OBSERVATIONS(profileId), {
    profile_id: request.profileId,
    domain: request.domain,
    behavior_description: request.behaviorDescription,
    context: request.context,
    is_milestone: request.isMilestone ?? false,
    is_concern: request.isConcern ?? false,
    observed_at: request.observedAt,
    observer_id: request.observerId,
    observer_type: request.observerType ?? 'parent',
    attachments: request.attachments,
  });
}

/**
 * Fetch observations for a profile with optional filters.
 */
export async function getObservations(
  profileId: string,
  params?: ObservationParams
): Promise<PaginatedResponse<Observation>> {
  return aiServiceClient.get<PaginatedResponse<Observation>>(ENDPOINTS.OBSERVATIONS(profileId), {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      domain: params?.domain,
      is_milestone: params?.isMilestone,
      is_concern: params?.isConcern,
      observer_type: params?.observerType,
    },
  });
}

/**
 * Fetch a specific observation by ID.
 */
export async function getObservation(
  profileId: string,
  observationId: string
): Promise<Observation> {
  return aiServiceClient.get<Observation>(ENDPOINTS.OBSERVATION(profileId, observationId));
}

/**
 * Update an existing observation.
 */
export async function updateObservation(
  profileId: string,
  observationId: string,
  request: UpdateObservationRequest
): Promise<Observation> {
  return aiServiceClient.patch<Observation>(ENDPOINTS.OBSERVATION(profileId, observationId), {
    behavior_description: request.behaviorDescription,
    context: request.context,
    is_milestone: request.isMilestone,
    is_concern: request.isConcern,
    attachments: request.attachments,
  });
}

/**
 * Delete an observation.
 */
export async function deleteObservation(profileId: string, observationId: string): Promise<void> {
  return aiServiceClient.delete(ENDPOINTS.OBSERVATION(profileId, observationId));
}

// ============================================================================
// Monthly Snapshot API
// ============================================================================

/**
 * Parameters for fetching monthly snapshots.
 */
export interface MonthlySnapshotParams extends PaginationParams {
  startMonth?: string;
  endMonth?: string;
}

/**
 * Create a new monthly snapshot for a development profile.
 */
export async function createMonthlySnapshot(
  profileId: string,
  request: CreateMonthlySnapshotRequest
): Promise<MonthlySnapshot> {
  return aiServiceClient.post<MonthlySnapshot>(ENDPOINTS.MONTHLY_SNAPSHOTS(profileId), {
    profile_id: request.profileId,
    snapshot_month: request.snapshotMonth,
    age_months: request.ageMonths,
    overall_progress: request.overallProgress ?? 'on_track',
    domain_summaries: request.domainSummaries,
    strengths: request.strengths,
    growth_areas: request.growthAreas,
    recommendations: request.recommendations,
    generated_by_id: request.generatedById,
  });
}

/**
 * Automatically generate a monthly snapshot from current assessments and observations.
 */
export async function generateMonthlySnapshot(
  profileId: string,
  snapshotMonth: string,
  generatedById?: string
): Promise<MonthlySnapshot> {
  return aiServiceClient.post<MonthlySnapshot>(ENDPOINTS.MONTHLY_SNAPSHOT_GENERATE(profileId), {}, {
    params: {
      snapshot_month: snapshotMonth,
      generated_by_id: generatedById,
    },
  });
}

/**
 * Fetch monthly snapshots for a profile with optional date filtering.
 */
export async function getMonthlySnapshots(
  profileId: string,
  params?: MonthlySnapshotParams
): Promise<PaginatedResponse<MonthlySnapshot>> {
  return aiServiceClient.get<PaginatedResponse<MonthlySnapshot>>(
    ENDPOINTS.MONTHLY_SNAPSHOTS(profileId),
    {
      params: {
        skip: params?.skip,
        limit: params?.limit,
        start_month: params?.startMonth,
        end_month: params?.endMonth,
      },
    }
  );
}

/**
 * Fetch a specific monthly snapshot by ID.
 */
export async function getMonthlySnapshot(
  profileId: string,
  snapshotId: string
): Promise<MonthlySnapshot> {
  return aiServiceClient.get<MonthlySnapshot>(ENDPOINTS.MONTHLY_SNAPSHOT(profileId, snapshotId));
}

/**
 * Update an existing monthly snapshot.
 */
export async function updateMonthlySnapshot(
  profileId: string,
  snapshotId: string,
  request: UpdateMonthlySnapshotRequest
): Promise<MonthlySnapshot> {
  return aiServiceClient.patch<MonthlySnapshot>(
    ENDPOINTS.MONTHLY_SNAPSHOT(profileId, snapshotId),
    {
      overall_progress: request.overallProgress,
      recommendations: request.recommendations,
      strengths: request.strengths,
      growth_areas: request.growthAreas,
      is_parent_shared: request.isParentShared,
    }
  );
}

/**
 * Delete a monthly snapshot.
 */
export async function deleteMonthlySnapshot(
  profileId: string,
  snapshotId: string
): Promise<void> {
  return aiServiceClient.delete(ENDPOINTS.MONTHLY_SNAPSHOT(profileId, snapshotId));
}

// ============================================================================
// Growth Trajectory API
// ============================================================================

/**
 * Parameters for fetching growth trajectory.
 */
export interface GrowthTrajectoryParams {
  startMonth?: string;
  endMonth?: string;
  domains?: DevelopmentalDomain[];
}

/**
 * Fetch growth trajectory data for a development profile.
 * Returns data points over time with trend analysis and alerts.
 */
export async function getGrowthTrajectory(
  profileId: string,
  params?: GrowthTrajectoryParams
): Promise<GrowthTrajectory> {
  return aiServiceClient.get<GrowthTrajectory>(ENDPOINTS.GROWTH_TRAJECTORY(profileId), {
    params: {
      start_month: params?.startMonth,
      end_month: params?.endMonth,
      domains: params?.domains?.join(','),
    },
  });
}

// ============================================================================
// Development Profile Batch Operations
// ============================================================================

/**
 * Complete development profile data for a child including all related entities.
 */
export interface ChildDevelopmentInsights {
  profile: DevelopmentProfile | null;
  recentObservations: Observation[];
  latestSnapshot: MonthlySnapshot | null;
  trajectory: GrowthTrajectory | null;
}

/**
 * Fetch comprehensive development insights for a child.
 * Combines profile, observations, snapshots, and trajectory in one call.
 */
export async function getChildDevelopmentInsights(
  childId: string
): Promise<ChildDevelopmentInsights> {
  let profile: DevelopmentProfile | null = null;

  try {
    profile = await getDevelopmentProfileByChild(childId);
  } catch {
    // No profile exists for this child
    return {
      profile: null,
      recentObservations: [],
      latestSnapshot: null,
      trajectory: null,
    };
  }

  const [observationsResult, snapshotsResult, trajectoryResult] = await Promise.allSettled([
    getObservations(profile.id, { limit: 10 }),
    getMonthlySnapshots(profile.id, { limit: 1 }),
    getGrowthTrajectory(profile.id),
  ]);

  return {
    profile,
    recentObservations:
      observationsResult.status === 'fulfilled' ? observationsResult.value.items : [],
    latestSnapshot:
      snapshotsResult.status === 'fulfilled' && snapshotsResult.value.items.length > 0
        ? snapshotsResult.value.items[0]
        : null,
    trajectory: trajectoryResult.status === 'fulfilled' ? trajectoryResult.value : null,
  };
}

/**
 * Fetch skill assessments grouped by developmental domain.
 */
export async function getSkillAssessmentsByDomain(
  profileId: string
): Promise<Map<DevelopmentalDomain, SkillAssessment[]>> {
  const results = new Map<DevelopmentalDomain, SkillAssessment[]>();
  const domains: DevelopmentalDomain[] = [
    'affective',
    'social',
    'language',
    'cognitive',
    'gross_motor',
    'fine_motor',
  ];

  const promises = domains.map(async (domain) => {
    const response = await getSkillAssessments(profileId, { domain, limit: 100 });
    return { domain, assessments: response.items };
  });

  const responses = await Promise.all(promises);

  for (const { domain, assessments } of responses) {
    results.set(domain, assessments);
  }

  return results;
}
