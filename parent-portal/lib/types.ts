/**
 * Domain types for LAYA Parent Portal.
 *
 * Provides TypeScript interfaces for all data models used across
 * the parent portal application.
 */

// ============================================================================
// Common Types
// ============================================================================

/**
 * Pagination parameters for list requests.
 */
export interface PaginationParams {
  skip?: number;
  limit?: number;
}

/**
 * Paginated response wrapper.
 */
export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  skip: number;
  limit: number;
}

/**
 * Base response with common fields.
 */
export interface BaseResponse {
  id: string;
  createdAt?: string;
  updatedAt?: string;
}

// ============================================================================
// Daily Report Types
// ============================================================================

/**
 * Meal consumption amount levels.
 */
export type MealAmount = 'all' | 'most' | 'some' | 'none';

/**
 * Types of meals served.
 */
export type MealType = 'breakfast' | 'lunch' | 'snack';

/**
 * Nap quality levels.
 */
export type NapQuality = 'good' | 'fair' | 'poor';

/**
 * Individual meal entry in a daily report.
 */
export interface MealEntry {
  id: string;
  type: MealType;
  time: string;
  notes: string;
  amount: MealAmount;
}

/**
 * Individual nap entry in a daily report.
 */
export interface NapEntry {
  id: string;
  startTime: string;
  endTime: string;
  quality: NapQuality;
}

/**
 * Individual activity entry in a daily report.
 */
export interface ActivityEntry {
  id: string;
  name: string;
  description: string;
  time: string;
}

/**
 * Photo attached to a daily report.
 */
export interface Photo {
  id: string;
  url: string;
  caption: string;
  taggedChildren: string[];
}

/**
 * Complete daily report for a child.
 */
export interface DailyReport {
  id: string;
  date: string;
  childId: string;
  meals: MealEntry[];
  naps: NapEntry[];
  activities: ActivityEntry[];
  photos: Photo[];
}

// ============================================================================
// Invoice Types
// ============================================================================

/**
 * Invoice payment status.
 */
export type InvoiceStatus = 'paid' | 'pending' | 'overdue';

/**
 * Individual line item in an invoice.
 */
export interface InvoiceItem {
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

/**
 * Complete invoice record.
 */
export interface Invoice {
  id: string;
  number: string;
  date: string;
  dueDate: string;
  amount: number;
  status: InvoiceStatus;
  pdfUrl: string;
  items: InvoiceItem[];
}

// ============================================================================
// Message Types
// ============================================================================

/**
 * Individual message in a conversation thread.
 */
export interface Message {
  id: string;
  threadId: string;
  senderId: string;
  senderName: string;
  content: string;
  timestamp: string;
  read: boolean;
}

/**
 * Conversation thread containing messages.
 */
export interface MessageThread {
  id: string;
  subject: string;
  participants: string[];
  lastMessage: Message;
  unreadCount: number;
}

/**
 * Request payload for sending a message.
 */
export interface SendMessageRequest {
  threadId: string;
  content: string;
}

/**
 * Request payload for creating a new thread.
 */
export interface CreateThreadRequest {
  subject: string;
  recipientIds: string[];
  initialMessage: string;
}

// ============================================================================
// Document Types
// ============================================================================

/**
 * Document signature status.
 */
export type DocumentStatus = 'pending' | 'signed';

/**
 * Document requiring signature.
 */
export interface Document {
  id: string;
  title: string;
  type: string;
  uploadDate: string;
  status: DocumentStatus;
  signedAt?: string;
  signatureUrl?: string;
  pdfUrl: string;
}

/**
 * Request payload for signing a document.
 */
export interface SignDocumentRequest {
  documentId: string;
  signatureData: string;
}

// ============================================================================
// Child Types
// ============================================================================

/**
 * Child profile information.
 */
export interface Child {
  id: string;
  firstName: string;
  lastName: string;
  dateOfBirth: string;
  profilePhotoUrl?: string;
  classroomId: string;
  classroomName: string;
}

// ============================================================================
// AI Service Types
// ============================================================================

/**
 * Activity type categories.
 */
export type ActivityType =
  | 'cognitive'
  | 'motor'
  | 'social'
  | 'language'
  | 'creative'
  | 'sensory';

/**
 * Activity difficulty levels.
 */
export type ActivityDifficulty = 'easy' | 'medium' | 'hard';

/**
 * Age range specification.
 */
export interface AgeRange {
  minMonths: number;
  maxMonths: number;
}

/**
 * Educational activity from AI service.
 */
export interface Activity {
  id: string;
  name: string;
  description: string;
  activityType: ActivityType;
  difficulty: ActivityDifficulty;
  durationMinutes: number;
  materialsNeeded: string[];
  ageRange?: AgeRange;
  specialNeedsAdaptations?: string;
  isActive: boolean;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Activity recommendation with relevance score.
 */
export interface ActivityRecommendation {
  activity: Activity;
  relevanceScore: number;
  reasoning?: string;
}

/**
 * Request payload for activity recommendations.
 */
export interface ActivityRecommendationRequest {
  childId: string;
  activityTypes?: ActivityType[];
  maxRecommendations?: number;
  includeSpecialNeeds?: boolean;
}

/**
 * Response payload for activity recommendations.
 */
export interface ActivityRecommendationResponse {
  childId: string;
  recommendations: ActivityRecommendation[];
  generatedAt: string;
}

/**
 * Special need type categories.
 */
export type SpecialNeedType =
  | 'autism'
  | 'adhd'
  | 'dyslexia'
  | 'speech_delay'
  | 'motor_delay'
  | 'sensory_processing'
  | 'behavioral'
  | 'cognitive_delay'
  | 'visual_impairment'
  | 'hearing_impairment'
  | 'other';

/**
 * Coaching guidance category.
 */
export type CoachingCategory =
  | 'activity_adaptation'
  | 'communication'
  | 'behavior_management'
  | 'sensory_support'
  | 'motor_support'
  | 'social_skills'
  | 'parent_guidance'
  | 'educator_training';

/**
 * Coaching priority levels.
 */
export type CoachingPriority = 'low' | 'medium' | 'high' | 'urgent';

/**
 * Coaching guidance item.
 */
export interface Coaching {
  id: string;
  title: string;
  content: string;
  category: CoachingCategory;
  specialNeedTypes: SpecialNeedType[];
  priority: CoachingPriority;
  targetAudience: string;
  prerequisites?: string;
  isPublished: boolean;
  viewCount: number;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Coaching guidance with relevance information.
 */
export interface CoachingGuidance {
  coaching: Coaching;
  relevanceScore: number;
  applicabilityNotes?: string;
}

/**
 * Request payload for coaching guidance.
 */
export interface CoachingGuidanceRequest {
  childId: string;
  specialNeedTypes: SpecialNeedType[];
  situationDescription?: string;
  category?: CoachingCategory;
  maxRecommendations?: number;
}

/**
 * Response payload for coaching guidance.
 */
export interface CoachingGuidanceResponse {
  childId: string;
  guidanceItems: CoachingGuidance[];
  generatedAt: string;
}

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Health check response.
 */
export interface HealthCheckResponse {
  status: string;
  service: string;
  version: string;
}

/**
 * Error response from API.
 */
export interface ApiErrorResponse {
  detail: string;
  statusCode?: number;
}

// ============================================================================
// Development Profile Types
// ============================================================================

/**
 * Quebec-aligned developmental domains for early childhood education.
 */
export type DevelopmentalDomain =
  | 'affective'
  | 'social'
  | 'language'
  | 'cognitive'
  | 'gross_motor'
  | 'fine_motor';

/**
 * Status levels for skill assessment tracking.
 */
export type SkillStatus = 'can' | 'learning' | 'not_yet' | 'na';

/**
 * Types of observers who can document child behavior.
 */
export type ObserverType = 'educator' | 'parent' | 'specialist';

/**
 * Overall developmental progress indicators.
 */
export type OverallProgress = 'on_track' | 'needs_support' | 'excelling';

/**
 * Summary of developmental progress for a single domain.
 */
export interface DomainSummary {
  domain: DevelopmentalDomain;
  skillsCan: number;
  skillsLearning: number;
  skillsNotYet: number;
  progressPercentage: number;
  keyObservations: string[];
}

/**
 * Development profile for a child with Quebec-aligned tracking.
 */
export interface DevelopmentProfile {
  id: string;
  childId: string;
  educatorId?: string;
  birthDate?: string;
  notes?: string;
  isActive: boolean;
  skillAssessments: SkillAssessment[];
  observations: Observation[];
  monthlySnapshots: MonthlySnapshot[];
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Summary response for development profile (without nested relations).
 */
export interface DevelopmentProfileSummary {
  id: string;
  childId: string;
  educatorId?: string;
  birthDate?: string;
  notes?: string;
  isActive: boolean;
  assessmentCount: number;
  observationCount: number;
  snapshotCount: number;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Individual skill assessment within a developmental domain.
 */
export interface SkillAssessment {
  id: string;
  profileId: string;
  domain: DevelopmentalDomain;
  skillName: string;
  skillNameFr?: string;
  status: SkillStatus;
  evidence?: string;
  assessedAt: string;
  assessedById?: string;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Observable behavior observation for a child.
 */
export interface Observation {
  id: string;
  profileId: string;
  domain: DevelopmentalDomain;
  behaviorDescription: string;
  context?: string;
  isMilestone: boolean;
  isConcern: boolean;
  observedAt: string;
  observerId?: string;
  observerType: ObserverType;
  attachments?: Record<string, unknown>;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Monthly developmental snapshot summarizing progress.
 */
export interface MonthlySnapshot {
  id: string;
  profileId: string;
  snapshotMonth: string;
  ageMonths?: number;
  overallProgress: OverallProgress;
  domainSummaries?: Record<string, DomainSummary>;
  strengths?: string[];
  growthAreas?: string[];
  recommendations?: string;
  generatedById?: string;
  isParentShared: boolean;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * A single data point in the growth trajectory.
 */
export interface GrowthDataPoint {
  month: string;
  ageMonths?: number;
  domainScores: Record<string, number>;
  overallScore: number;
}

/**
 * Growth trajectory analysis for a child's development over time.
 */
export interface GrowthTrajectory {
  profileId: string;
  childId: string;
  dataPoints: GrowthDataPoint[];
  trendAnalysis?: string;
  alerts: string[];
}

/**
 * Request payload for creating a development profile.
 */
export interface CreateDevelopmentProfileRequest {
  childId: string;
  educatorId?: string;
  birthDate?: string;
  notes?: string;
}

/**
 * Request payload for creating a skill assessment.
 */
export interface CreateSkillAssessmentRequest {
  profileId: string;
  domain: DevelopmentalDomain;
  skillName: string;
  skillNameFr?: string;
  status: SkillStatus;
  evidence?: string;
  assessedById?: string;
}

/**
 * Request payload for updating a skill assessment.
 */
export interface UpdateSkillAssessmentRequest {
  status?: SkillStatus;
  evidence?: string;
  assessedById?: string;
}

/**
 * Request payload for creating an observation.
 */
export interface CreateObservationRequest {
  profileId: string;
  domain: DevelopmentalDomain;
  behaviorDescription: string;
  context?: string;
  isMilestone?: boolean;
  isConcern?: boolean;
  observedAt?: string;
  observerId?: string;
  observerType?: ObserverType;
  attachments?: Record<string, unknown>;
}

/**
 * Request payload for updating an observation.
 */
export interface UpdateObservationRequest {
  behaviorDescription?: string;
  context?: string;
  isMilestone?: boolean;
  isConcern?: boolean;
  attachments?: Record<string, unknown>;
}

/**
 * Request payload for creating a monthly snapshot.
 */
export interface CreateMonthlySnapshotRequest {
  profileId: string;
  snapshotMonth: string;
  ageMonths?: number;
  overallProgress?: OverallProgress;
  domainSummaries?: Record<string, DomainSummary>;
  strengths?: string[];
  growthAreas?: string[];
  recommendations?: string;
  generatedById?: string;
}

/**
 * Request payload for updating a monthly snapshot.
 */
export interface UpdateMonthlySnapshotRequest {
  overallProgress?: OverallProgress;
  recommendations?: string;
  strengths?: string[];
  growthAreas?: string[];
  isParentShared?: boolean;
}
