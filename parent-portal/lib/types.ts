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
// Message Quality Types
// ============================================================================

/**
 * Types of quality issues detected in messages.
 * Based on Quebec 'Bonne Message' communication standards.
 */
export type QualityIssue =
  | 'accusatory_you'
  | 'judgmental_label'
  | 'blame_shame'
  | 'exaggeration'
  | 'alarmist'
  | 'comparison'
  | 'negative_tone'
  | 'missing_positive'
  | 'missing_solution'
  | 'multiple_objectives';

/**
 * Severity levels for quality issues.
 */
export type IssueSeverity = 'low' | 'medium' | 'high' | 'critical';

/**
 * Context types for messages being analyzed.
 */
export type MessageContext =
  | 'daily_report'
  | 'incident_report'
  | 'milestone_update'
  | 'general_update'
  | 'behavior_concern'
  | 'health_update';

/**
 * Supported languages for message analysis.
 * Quebec compliance requires both English and French support.
 */
export type MessageLanguage = 'en' | 'fr';

/**
 * Categories for message templates.
 */
export type TemplateCategory =
  | 'positive_opening'
  | 'factual_observation'
  | 'solution_oriented'
  | 'full_message'
  | 'behavior_concern'
  | 'milestone_celebration';

/**
 * Detailed information about a detected quality issue.
 */
export interface QualityIssueDetail {
  issueType: QualityIssue;
  severity: IssueSeverity;
  description: string;
  originalText: string;
  positionStart: number;
  positionEnd: number;
  suggestion?: string;
}

/**
 * A suggested rewrite for improving message quality.
 * Implements 'I' language transformation and sandwich method.
 */
export interface RewriteSuggestion {
  originalText: string;
  suggestedText: string;
  explanation: string;
  usesILanguage: boolean;
  hasSandwichStructure: boolean;
  confidenceScore: number;
}

/**
 * Request payload for analyzing message quality.
 */
export interface MessageAnalysisRequest {
  messageText: string;
  language?: MessageLanguage;
  context?: MessageContext;
  childId?: string;
  includeRewrites?: boolean;
}

/**
 * Response payload for message quality analysis.
 */
export interface MessageAnalysisResponse {
  id: string;
  messageText: string;
  language: MessageLanguage;
  qualityScore: number;
  isAcceptable: boolean;
  issues: QualityIssueDetail[];
  rewriteSuggestions: RewriteSuggestion[];
  hasPositiveOpening: boolean;
  hasFactualBasis: boolean;
  hasSolutionFocus: boolean;
  analysisNotes?: string;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Request payload for creating a message template.
 */
export interface MessageTemplateRequest {
  title: string;
  content: string;
  category: TemplateCategory;
  language?: MessageLanguage;
  description?: string;
}

/**
 * Message template response.
 */
export interface MessageTemplateResponse {
  id: string;
  title: string;
  content: string;
  category: TemplateCategory;
  language: MessageLanguage;
  description?: string;
  isSystem: boolean;
  usageCount: number;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Request payload for creating a training example.
 */
export interface TrainingExampleRequest {
  originalMessage: string;
  improvedMessage: string;
  issuesDemonstrated: QualityIssue[];
  explanation: string;
  language?: MessageLanguage;
}

/**
 * Training example response.
 */
export interface TrainingExampleResponse {
  id: string;
  originalMessage: string;
  improvedMessage: string;
  issuesDemonstrated: QualityIssue[];
  explanation: string;
  language: MessageLanguage;
  difficultyLevel: string;
  createdAt?: string;
  updatedAt?: string;
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
