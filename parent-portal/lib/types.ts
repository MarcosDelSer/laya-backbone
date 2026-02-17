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
// Intervention Plan Types
// ============================================================================

/**
 * Status of an intervention plan.
 */
export type InterventionPlanStatus =
  | 'draft'
  | 'active'
  | 'under_review'
  | 'completed'
  | 'archived';

/**
 * Review schedule frequency for intervention plans.
 */
export type ReviewSchedule =
  | 'monthly'
  | 'quarterly'
  | 'semi_annually'
  | 'annually';

/**
 * Status of a SMART goal.
 */
export type GoalStatus =
  | 'not_started'
  | 'in_progress'
  | 'achieved'
  | 'modified'
  | 'discontinued';

/**
 * Progress level for intervention plan tracking.
 */
export type ProgressLevel =
  | 'no_progress'
  | 'minimal'
  | 'moderate'
  | 'significant'
  | 'achieved';

/**
 * Categories for child's strengths.
 */
export type StrengthCategory =
  | 'cognitive'
  | 'social'
  | 'physical'
  | 'emotional'
  | 'communication'
  | 'creative'
  | 'academic'
  | 'adaptive'
  | 'other';

/**
 * Categories for child's needs.
 */
export type NeedCategory =
  | 'communication'
  | 'behavior'
  | 'academic'
  | 'sensory'
  | 'motor'
  | 'social'
  | 'emotional'
  | 'self_care'
  | 'cognitive'
  | 'other';

/**
 * Priority levels for needs.
 */
export type NeedPriority = 'low' | 'medium' | 'high' | 'critical';

/**
 * Who is responsible for a strategy or monitoring task.
 */
export type ResponsibleParty =
  | 'educator'
  | 'parent'
  | 'therapist'
  | 'team'
  | 'child';

/**
 * Methods for monitoring progress.
 */
export type MonitoringMethod =
  | 'observation'
  | 'assessment'
  | 'data_collection'
  | 'interview'
  | 'portfolio'
  | 'checklist'
  | 'other';

/**
 * Types of parent involvement activities.
 */
export type ParentActivityType =
  | 'home_activity'
  | 'communication'
  | 'training'
  | 'meeting'
  | 'observation'
  | 'documentation'
  | 'other';

/**
 * Types of specialists for consultations.
 */
export type SpecialistType =
  | 'speech_therapist'
  | 'occupational_therapist'
  | 'physical_therapist'
  | 'psychologist'
  | 'behavioral_specialist'
  | 'special_educator'
  | 'social_worker'
  | 'pediatrician'
  | 'neurologist'
  | 'psychiatrist'
  | 'other';

/**
 * Child's strength entry (Part 2).
 */
export interface InterventionStrength {
  id: string;
  planId: string;
  category: StrengthCategory;
  description: string;
  examples?: string;
  order: number;
  createdAt: string;
}

/**
 * Child's need entry (Part 3).
 */
export interface InterventionNeed {
  id: string;
  planId: string;
  category: NeedCategory;
  description: string;
  priority: NeedPriority;
  baseline?: string;
  order: number;
  createdAt: string;
}

/**
 * SMART goal entry (Part 4).
 */
export interface InterventionGoal {
  id: string;
  planId: string;
  needId?: string;
  title: string;
  description: string;
  measurementCriteria: string;
  measurementBaseline?: string;
  measurementTarget?: string;
  achievabilityNotes?: string;
  relevanceNotes?: string;
  targetDate?: string;
  status: GoalStatus;
  progressPercentage: number;
  order: number;
  createdAt: string;
  updatedAt?: string;
}

/**
 * Intervention strategy entry (Part 5).
 */
export interface InterventionStrategy {
  id: string;
  planId: string;
  goalId?: string;
  title: string;
  description: string;
  responsibleParty: ResponsibleParty;
  frequency?: string;
  materialsNeeded?: string;
  accommodations?: string;
  order: number;
  createdAt: string;
}

/**
 * Monitoring approach entry (Part 6).
 */
export interface InterventionMonitoring {
  id: string;
  planId: string;
  goalId?: string;
  method: MonitoringMethod;
  description: string;
  frequency: string;
  responsibleParty: ResponsibleParty;
  dataCollectionTools?: string;
  successIndicators?: string;
  order: number;
  createdAt: string;
}

/**
 * Parent involvement activity entry (Part 7).
 */
export interface InterventionParentInvolvement {
  id: string;
  planId: string;
  activityType: ParentActivityType;
  title: string;
  description: string;
  frequency?: string;
  resourcesProvided?: string;
  communicationMethod?: string;
  order: number;
  createdAt: string;
}

/**
 * Specialist consultation entry (Part 8).
 */
export interface InterventionConsultation {
  id: string;
  planId: string;
  specialistType: SpecialistType;
  specialistName?: string;
  organization?: string;
  purpose: string;
  recommendations?: string;
  consultationDate?: string;
  nextConsultationDate?: string;
  notes?: string;
  order: number;
  createdAt: string;
}

/**
 * Progress tracking record.
 */
export interface InterventionProgress {
  id: string;
  planId: string;
  goalId?: string;
  recordedBy: string;
  recordDate: string;
  progressNotes: string;
  progressLevel: ProgressLevel;
  measurementValue?: string;
  barriers?: string;
  nextSteps?: string;
  createdAt: string;
}

/**
 * Version history record.
 */
export interface InterventionVersion {
  id: string;
  planId: string;
  versionNumber: number;
  changeSummary?: string;
  createdBy: string;
  createdAt: string;
}

/**
 * Complete intervention plan with all 8 sections.
 */
export interface InterventionPlan {
  id: string;
  childId: string;
  createdBy: string;
  title: string;
  status: InterventionPlanStatus;
  version: number;
  parentVersionId?: string;

  // Part 1 - Identification & History
  childName: string;
  dateOfBirth?: string;
  diagnosis?: string[];
  medicalHistory?: string;
  educationalHistory?: string;
  familyContext?: string;

  // Review scheduling
  reviewSchedule: ReviewSchedule;
  nextReviewDate?: string;

  // Dates
  effectiveDate?: string;
  endDate?: string;

  // Parent signature
  parentSigned: boolean;
  parentSignatureDate?: string;

  // All 8 sections
  strengths: InterventionStrength[];
  needs: InterventionNeed[];
  goals: InterventionGoal[];
  strategies: InterventionStrategy[];
  monitoring: InterventionMonitoring[];
  parentInvolvements: InterventionParentInvolvement[];
  consultations: InterventionConsultation[];

  // Progress and version history
  progressRecords: InterventionProgress[];
  versions: InterventionVersion[];

  // Timestamps
  createdAt: string;
  updatedAt?: string;
}

/**
 * Summary of an intervention plan for list views.
 */
export interface InterventionPlanSummary {
  id: string;
  childId: string;
  childName: string;
  title: string;
  status: InterventionPlanStatus;
  version: number;
  reviewSchedule: ReviewSchedule;
  nextReviewDate?: string;
  parentSigned: boolean;
  goalsCount: number;
  progressCount: number;
  createdAt: string;
  updatedAt?: string;
}

/**
 * Request payload for parent signature on an intervention plan.
 */
export interface SignInterventionPlanRequest {
  signatureData: string;
  agreedToTerms: boolean;
}

/**
 * Response payload for parent signature confirmation.
 */
export interface SignInterventionPlanResponse {
  planId: string;
  parentSigned: boolean;
  parentSignatureDate: string;
  message: string;
}

/**
 * Plan review reminder for notifications.
 */
export interface InterventionPlanReviewReminder {
  planId: string;
  childId: string;
  childName: string;
  title: string;
  nextReviewDate: string;
  daysUntilReview: number;
  status: InterventionPlanStatus;
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
// Medical Protocol Types
// ============================================================================

/**
 * Types of medical protocols supported.
 * - medication: Acetaminophen protocol (FO-0647)
 * - topical: Insect repellent protocol (FO-0646)
 */
export type ProtocolType = 'medication' | 'topical';

/**
 * Authorization status for a medical protocol.
 */
export type ProtocolAuthorizationStatus = 'active' | 'pending' | 'expired' | 'revoked';

/**
 * Acetaminophen concentration options per FO-0647.
 */
export type AcetaminophenConcentration = '80mg/mL' | '80mg/5mL' | '160mg/5mL';

/**
 * Medical protocol definition (Acetaminophen FO-0647, Insect Repellent FO-0646).
 */
export interface MedicalProtocol {
  id: string;
  name: string;
  formCode: string;
  type: ProtocolType;
  description: string;
  minimumAgeMonths?: number;
  requiresWeight: boolean;
  requiresTemperature: boolean;
  minimumIntervalHours?: number;
  maxDailyDoses?: number;
  isActive: boolean;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Dosing information for a specific weight and concentration.
 */
export interface DosingInfo {
  protocolId: string;
  concentration: AcetaminophenConcentration;
  minWeightKg: number;
  maxWeightKg: number;
  minDoseMg: number;
  maxDoseMg: number;
  minDoseMl: number;
  maxDoseMl: number;
  displayLabel: string;
}

/**
 * Weight record for a child (used for dosing calculations).
 */
export interface WeightRecord {
  weightKg: number;
  recordedAt: string;
  expiresAt: string;
  isExpired: boolean;
}

/**
 * Parent authorization for a medical protocol.
 */
export interface ProtocolAuthorization {
  id: string;
  childId: string;
  childName: string;
  protocolId: string;
  protocolName: string;
  protocolFormCode: string;
  protocolType: ProtocolType;
  status: ProtocolAuthorizationStatus;
  weightKg: number;
  weightDate: string;
  weightExpiryDate: string;
  isWeightExpired: boolean;
  signatureDate: string;
  signatureData?: string;
  agreementText: string;
  expiryDate?: string;
  revokedAt?: string;
  revokedReason?: string;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Administration record for a medical protocol.
 */
export interface ProtocolAdministration {
  id: string;
  childId: string;
  childName: string;
  protocolId: string;
  protocolName: string;
  protocolFormCode: string;
  administeredAt: string;
  administeredById: string;
  administeredByName: string;
  doseMg?: number;
  doseMl?: number;
  concentration?: AcetaminophenConcentration;
  weightKg: number;
  temperatureCelsius?: number;
  temperatureMethod?: string;
  notes?: string;
  witnessId?: string;
  witnessName?: string;
  followUpTime?: string;
  followUpCompleted?: boolean;
  followUpCompletedAt?: string;
  parentNotified?: boolean;
  parentNotifiedAt?: string;
  parentAcknowledged?: boolean;
  parentAcknowledgedAt?: string;
  createdAt?: string;
}

/**
 * Request payload for creating a protocol authorization.
 */
export interface CreateProtocolAuthorizationRequest {
  childId: string;
  protocolId: string;
  weightKg: number;
  signatureData: string;
  agreementText: string;
}

/**
 * Request payload for updating a child's weight.
 */
export interface UpdateWeightRequest {
  childId: string;
  protocolId: string;
  weightKg: number;
}

/**
 * Dosing calculation request for acetaminophen.
 */
export interface DosingCalculationRequest {
  protocolId: string;
  weightKg: number;
  concentration?: AcetaminophenConcentration;
}

/**
 * Dosing calculation response with all available concentrations.
 */
export interface DosingCalculationResponse {
  weightKg: number;
  isInRange: boolean;
  dosingOptions: DosingInfo[];
  recommendedConcentration?: AcetaminophenConcentration;
  warningMessage?: string;
}

/**
 * Medical protocol summary for dashboard display.
 */
export interface ProtocolSummary {
  protocolId: string;
  protocolName: string;
  protocolFormCode: string;
  protocolType: ProtocolType;
  authorizationStatus: ProtocolAuthorizationStatus | null;
  lastAuthorizedAt?: string;
  weightKg?: number;
  isWeightExpired?: boolean;
  lastAdministeredAt?: string;
  canAdminister: boolean;
  nextAllowedAdministrationAt?: string;
}

/**
 * Child's medical protocol overview.
 */
export interface ChildProtocolOverview {
  childId: string;
  childName: string;
  protocols: ProtocolSummary[];
  pendingActions: number;
  activeAuthorizations: number;
}
