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
 * Types of message senders.
 * Identifies the role of the user sending a message.
 */
export type SenderType = 'parent' | 'educator' | 'director' | 'admin';

/**
 * Types of message threads.
 * Categorizes conversation threads by their purpose.
 */
export type ThreadType = 'daily_log' | 'urgent' | 'serious' | 'admin';

/**
 * Content types for messages.
 * Defines the format of message content for proper rendering.
 */
export type MessageContentType = 'text' | 'rich_text';

/**
 * Types of notifications that can be sent to parents.
 */
export type NotificationType = 'message' | 'daily_log' | 'urgent' | 'admin';

/**
 * Channels through which notifications can be delivered.
 */
export type NotificationChannelType = 'email' | 'push' | 'sms';

/**
 * Frequency options for notification delivery.
 */
export type NotificationFrequency = 'immediate' | 'hourly' | 'daily' | 'weekly';

/**
 * Participant in a message thread.
 */
export interface ThreadParticipant {
  userId: string;
  userType: SenderType;
  displayName?: string;
}

/**
 * Attachment included with a message.
 */
export interface MessageAttachment {
  id: string;
  messageId: string;
  fileUrl: string;
  fileType: string;
  fileName: string;
  fileSize?: number;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Request payload for creating an attachment.
 */
export interface AttachmentCreate {
  fileUrl: string;
  fileType: string;
  fileName: string;
  fileSize?: number;
}

/**
 * Individual message in a conversation thread.
 */
export interface Message {
  id: string;
  threadId: string;
  senderId: string;
  senderType: SenderType;
  senderName?: string;
  content: string;
  contentType: MessageContentType;
  isRead: boolean;
  attachments: MessageAttachment[];
  createdAt?: string;
  updatedAt?: string;
  /** @deprecated Use isRead instead */
  read?: boolean;
  /** @deprecated Use createdAt instead */
  timestamp?: string;
}

/**
 * Conversation thread containing messages.
 */
export interface MessageThread {
  id: string;
  subject: string;
  threadType: ThreadType;
  childId?: string;
  createdBy: string;
  participants: ThreadParticipant[];
  isActive: boolean;
  unreadCount: number;
  lastMessage?: string;
  lastMessageAt?: string;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Thread with full list of messages.
 */
export interface ThreadWithMessages extends MessageThread {
  messages: Message[];
}

/**
 * Response for listing threads with pagination.
 */
export interface ThreadListResponse {
  threads: MessageThread[];
  total: number;
  skip: number;
  limit: number;
}

/**
 * Response for listing messages with pagination.
 */
export interface MessageListResponse {
  messages: Message[];
  total: number;
  skip: number;
  limit: number;
}

/**
 * Response for unread message count.
 */
export interface UnreadCountResponse {
  totalUnread: number;
  threadsWithUnread: number;
}

/**
 * Request payload for sending a message.
 */
export interface SendMessageRequest {
  content: string;
  contentType?: MessageContentType;
  attachments?: AttachmentCreate[];
}

/**
 * Request payload for creating a new thread.
 */
export interface CreateThreadRequest {
  subject: string;
  threadType?: ThreadType;
  childId?: string;
  participants: ThreadParticipant[];
  initialMessage?: string;
}

/**
 * Request payload for updating a thread.
 */
export interface UpdateThreadRequest {
  subject?: string;
  isActive?: boolean;
}

/**
 * Request payload for marking messages as read.
 */
export interface MarkAsReadRequest {
  messageIds: string[];
}

/**
 * Notification preference configuration.
 */
export interface NotificationPreference {
  id: string;
  parentId: string;
  notificationType: NotificationType;
  channel: NotificationChannelType;
  isEnabled: boolean;
  frequency: NotificationFrequency;
  quietHoursStart?: string;
  quietHoursEnd?: string;
  createdAt?: string;
  updatedAt?: string;
}

/**
 * Request payload for creating/updating notification preferences.
 */
export interface NotificationPreferenceRequest {
  parentId: string;
  notificationType: NotificationType;
  channel: NotificationChannelType;
  isEnabled?: boolean;
  frequency?: NotificationFrequency;
  quietHoursStart?: string;
  quietHoursEnd?: string;
}

/**
 * Response for listing notification preferences.
 */
export interface NotificationPreferenceListResponse {
  parentId: string;
  preferences: NotificationPreference[];
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
// Government Document Types
// ============================================================================

/**
 * Categories of government documents.
 */
export type GovernmentDocumentCategory =
  | 'child_identity'
  | 'parent_identity'
  | 'health'
  | 'immigration';

/**
 * Types of government documents for Quebec childcare compliance.
 */
export type GovernmentDocumentType =
  | 'birth_certificate'
  | 'citizenship_proof'
  | 'health_card'
  | 'immunization_record'
  | 'parent_id'
  | 'custody_agreement'
  | 'immigration_document'
  | 'work_permit'
  | 'study_permit';

/**
 * Verification status of a government document.
 */
export type GovernmentDocumentStatus =
  | 'missing'
  | 'pending_verification'
  | 'verified'
  | 'rejected'
  | 'expired';

/**
 * Government document type definition.
 */
export interface GovernmentDocumentTypeDefinition {
  id: string;
  name: string;
  description: string;
  category: GovernmentDocumentCategory;
  isRequired: boolean;
  appliesToChild: boolean;
  appliesToParent: boolean;
  hasExpiration: boolean;
}

/**
 * Government document record.
 */
export interface GovernmentDocument {
  id: string;
  familyId: string;
  personId: string;
  personName: string;
  documentTypeId: string;
  documentTypeName: string;
  category: GovernmentDocumentCategory;
  status: GovernmentDocumentStatus;
  documentNumber?: string;
  issueDate?: string;
  expirationDate?: string;
  fileUrl?: string;
  fileName?: string;
  uploadedAt?: string;
  verifiedAt?: string;
  verifiedBy?: string;
  rejectionReason?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

/**
 * Request payload for uploading a government document.
 */
export interface GovernmentDocumentUploadRequest {
  personId: string;
  documentTypeId: string;
  documentNumber?: string;
  issueDate?: string;
  expirationDate?: string;
  notes?: string;
  file: File;
}

/**
 * Family document checklist item.
 */
export interface GovernmentDocumentChecklistItem {
  documentType: GovernmentDocumentTypeDefinition;
  personId: string;
  personName: string;
  status: GovernmentDocumentStatus;
  document?: GovernmentDocument;
  daysUntilExpiration?: number;
}

/**
 * Family document checklist grouped by member.
 */
export interface GovernmentDocumentChecklist {
  familyId: string;
  children: {
    personId: string;
    personName: string;
    items: GovernmentDocumentChecklistItem[];
  }[];
  parents: {
    personId: string;
    personName: string;
    items: GovernmentDocumentChecklistItem[];
  }[];
  complianceRate: number;
  criticalDocumentsMissing: boolean;
  missingCriticalDocuments: string[];
}

/**
 * Summary statistics for government documents.
 */
export interface GovernmentDocumentStats {
  total: number;
  verified: number;
  pendingVerification: number;
  missing: number;
  expired: number;
  expiringWithin30Days: number;
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
