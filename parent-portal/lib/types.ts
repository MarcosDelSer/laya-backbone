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
// Enrollment Form Types
// ============================================================================

/**
 * Enrollment form status.
 */
export type EnrollmentFormStatus =
  | 'Draft'
  | 'Submitted'
  | 'Approved'
  | 'Rejected'
  | 'Expired';

/**
 * Parent/Guardian number identifier.
 */
export type ParentNumber = '1' | '2';

/**
 * E-signature type identifier.
 */
export type SignatureType = 'Parent1' | 'Parent2' | 'Director';

/**
 * Parent/Guardian information for enrollment form.
 */
export interface EnrollmentParent {
  id?: string;
  formId: string;
  parentNumber: ParentNumber;
  name: string;
  relationship: string;
  address?: string;
  city?: string;
  postalCode?: string;
  homePhone?: string;
  cellPhone?: string;
  workPhone?: string;
  email?: string;
  employer?: string;
  workAddress?: string;
  workHours?: string;
  isPrimaryContact: boolean;
}

/**
 * Authorized pickup person for enrollment form.
 */
export interface AuthorizedPickup {
  id?: string;
  formId: string;
  name: string;
  relationship: string;
  phone: string;
  photoPath?: string;
  photoUrl?: string;
  priority: number;
  notes?: string;
}

/**
 * Emergency contact for enrollment form.
 */
export interface EmergencyContact {
  id?: string;
  formId: string;
  name: string;
  relationship: string;
  phone: string;
  alternatePhone?: string;
  priority: number;
  notes?: string;
}

/**
 * Allergy information with details.
 */
export interface AllergyInfo {
  allergen: string;
  severity?: 'mild' | 'moderate' | 'severe';
  reaction?: string;
  treatment?: string;
}

/**
 * Medication information with dosage and schedule.
 */
export interface MedicationInfo {
  name: string;
  dosage: string;
  schedule: string;
  instructions?: string;
}

/**
 * Health information for enrollment form.
 */
export interface HealthInfo {
  id?: string;
  formId: string;
  allergies?: AllergyInfo[];
  medicalConditions?: string;
  hasEpiPen: boolean;
  epiPenInstructions?: string;
  medications?: MedicationInfo[];
  doctorName?: string;
  doctorPhone?: string;
  doctorAddress?: string;
  healthInsuranceNumber?: string;
  healthInsuranceExpiry?: string;
  specialNeeds?: string;
  developmentalNotes?: string;
}

/**
 * Nutrition and dietary information for enrollment form.
 */
export interface NutritionInfo {
  id?: string;
  formId: string;
  dietaryRestrictions?: string;
  foodAllergies?: string;
  feedingInstructions?: string;
  isBottleFeeding: boolean;
  bottleFeedingInfo?: string;
  foodPreferences?: string;
  foodDislikes?: string;
  mealPlanNotes?: string;
}

/**
 * Weekly attendance pattern for enrollment form.
 */
export interface AttendancePattern {
  id?: string;
  formId: string;
  mondayAm: boolean;
  mondayPm: boolean;
  tuesdayAm: boolean;
  tuesdayPm: boolean;
  wednesdayAm: boolean;
  wednesdayPm: boolean;
  thursdayAm: boolean;
  thursdayPm: boolean;
  fridayAm: boolean;
  fridayPm: boolean;
  saturdayAm: boolean;
  saturdayPm: boolean;
  sundayAm: boolean;
  sundayPm: boolean;
  expectedHoursPerWeek?: number;
  expectedArrivalTime?: string;
  expectedDepartureTime?: string;
  notes?: string;
}

/**
 * E-signature for enrollment form.
 */
export interface EnrollmentSignature {
  id?: string;
  formId: string;
  signatureType: SignatureType;
  signatureData: string;
  signerName: string;
  signedAt: string;
  ipAddress?: string;
  userAgent?: string;
}

/**
 * Complete enrollment form with all related data.
 */
export interface EnrollmentForm {
  id: string;
  personId: string;
  familyId: string;
  schoolYearId: string;
  formNumber: string;
  status: EnrollmentFormStatus;
  version: number;
  admissionDate?: string;
  childFirstName: string;
  childLastName: string;
  childDateOfBirth: string;
  childAddress?: string;
  childCity?: string;
  childPostalCode?: string;
  languagesSpoken?: string;
  notes?: string;
  submittedAt?: string;
  approvedAt?: string;
  approvedById?: string;
  rejectedAt?: string;
  rejectedById?: string;
  rejectionReason?: string;
  createdById: string;
  createdAt?: string;
  updatedAt?: string;
  // Related data (populated when fetched with relations)
  parents?: EnrollmentParent[];
  authorizedPickups?: AuthorizedPickup[];
  emergencyContacts?: EmergencyContact[];
  healthInfo?: HealthInfo;
  nutritionInfo?: NutritionInfo;
  attendancePattern?: AttendancePattern;
  signatures?: EnrollmentSignature[];
}

/**
 * Enrollment form summary for list display.
 */
export interface EnrollmentFormSummary {
  id: string;
  formNumber: string;
  status: EnrollmentFormStatus;
  version: number;
  admissionDate?: string;
  childFirstName: string;
  childLastName: string;
  childDateOfBirth: string;
  createdAt?: string;
  updatedAt?: string;
  createdByName?: string;
}

/**
 * Request payload for creating an enrollment form.
 */
export interface CreateEnrollmentFormRequest {
  personId: string;
  familyId: string;
  admissionDate?: string;
  childFirstName: string;
  childLastName: string;
  childDateOfBirth: string;
  childAddress?: string;
  childCity?: string;
  childPostalCode?: string;
  languagesSpoken?: string;
  notes?: string;
  parents: Omit<EnrollmentParent, 'id' | 'formId'>[];
  authorizedPickups?: Omit<AuthorizedPickup, 'id' | 'formId'>[];
  emergencyContacts: Omit<EmergencyContact, 'id' | 'formId'>[];
  healthInfo?: Omit<HealthInfo, 'id' | 'formId'>;
  nutritionInfo?: Omit<NutritionInfo, 'id' | 'formId'>;
  attendancePattern?: Omit<AttendancePattern, 'id' | 'formId'>;
}

/**
 * Request payload for updating an enrollment form.
 */
export interface UpdateEnrollmentFormRequest {
  admissionDate?: string;
  childFirstName?: string;
  childLastName?: string;
  childDateOfBirth?: string;
  childAddress?: string;
  childCity?: string;
  childPostalCode?: string;
  languagesSpoken?: string;
  notes?: string;
  parents?: Omit<EnrollmentParent, 'formId'>[];
  authorizedPickups?: Omit<AuthorizedPickup, 'formId'>[];
  emergencyContacts?: Omit<EmergencyContact, 'formId'>[];
  healthInfo?: Omit<HealthInfo, 'formId'>;
  nutritionInfo?: Omit<NutritionInfo, 'formId'>;
  attendancePattern?: Omit<AttendancePattern, 'formId'>;
}

/**
 * Request payload for signing an enrollment form.
 */
export interface SignEnrollmentFormRequest {
  formId: string;
  signatureType: SignatureType;
  signatureData: string;
  signerName: string;
}

/**
 * Request payload for submitting an enrollment form for approval.
 */
export interface SubmitEnrollmentFormRequest {
  formId: string;
}

/**
 * Request payload for approving or rejecting an enrollment form.
 */
export interface ReviewEnrollmentFormRequest {
  formId: string;
  action: 'approve' | 'reject';
  reason?: string;
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
// Portfolio Types
// ============================================================================

/**
 * Portfolio item type categories.
 */
export type PortfolioItemType = 'photo' | 'video' | 'document' | 'artwork';

/**
 * Observation type categories.
 */
export type ObservationType =
  | 'anecdotal'
  | 'running_record'
  | 'learning_story'
  | 'checklist'
  | 'time_sample';

/**
 * Milestone status.
 */
export type MilestoneStatus = 'not_started' | 'in_progress' | 'achieved';

/**
 * Developmental domain categories.
 */
export type DevelopmentalDomain =
  | 'cognitive'
  | 'physical'
  | 'social_emotional'
  | 'language'
  | 'creative';

/**
 * Work sample type categories.
 */
export type WorkSampleType =
  | 'drawing'
  | 'writing'
  | 'craft'
  | 'photo'
  | 'recording'
  | 'other';

/**
 * Individual portfolio item (photo/video/document).
 */
export interface PortfolioItem {
  id: string;
  childId: string;
  type: PortfolioItemType;
  title: string;
  caption: string;
  mediaUrl: string;
  thumbnailUrl?: string;
  date: string;
  uploadedBy: string;
  tags: string[];
  isPrivate: boolean;
  createdAt: string;
  updatedAt?: string;
}

/**
 * Observation note for a child.
 */
export interface Observation {
  id: string;
  childId: string;
  type: ObservationType;
  title: string;
  content: string;
  date: string;
  observedBy: string;
  domains: DevelopmentalDomain[];
  linkedMilestones: string[];
  linkedWorkSamples: string[];
  isPrivate: boolean;
  createdAt: string;
  updatedAt?: string;
}

/**
 * Developmental milestone for tracking progress.
 */
export interface Milestone {
  id: string;
  childId: string;
  domain: DevelopmentalDomain;
  title: string;
  description: string;
  expectedAgeMonths?: number;
  status: MilestoneStatus;
  achievedDate?: string;
  notes?: string;
  evidenceIds: string[];
  createdAt: string;
  updatedAt?: string;
}

/**
 * Work sample documentation.
 */
export interface WorkSample {
  id: string;
  childId: string;
  type: WorkSampleType;
  title: string;
  description: string;
  mediaUrl: string;
  thumbnailUrl?: string;
  date: string;
  domains: DevelopmentalDomain[];
  teacherNotes?: string;
  familyContribution?: string;
  isPrivate: boolean;
  createdAt: string;
  updatedAt?: string;
}

/**
 * Request payload for creating a portfolio item.
 */
export interface CreatePortfolioItemRequest {
  childId: string;
  type: PortfolioItemType;
  title: string;
  caption: string;
  mediaUrl: string;
  thumbnailUrl?: string;
  date: string;
  tags?: string[];
  isPrivate?: boolean;
}

/**
 * Request payload for creating an observation.
 */
export interface CreateObservationRequest {
  childId: string;
  type: ObservationType;
  title: string;
  content: string;
  date: string;
  domains?: DevelopmentalDomain[];
  linkedMilestones?: string[];
  linkedWorkSamples?: string[];
  isPrivate?: boolean;
}

/**
 * Request payload for creating a milestone.
 */
export interface CreateMilestoneRequest {
  childId: string;
  domain: DevelopmentalDomain;
  title: string;
  description: string;
  expectedAgeMonths?: number;
  status?: MilestoneStatus;
  notes?: string;
}

/**
 * Request payload for creating a work sample.
 */
export interface CreateWorkSampleRequest {
  childId: string;
  type: WorkSampleType;
  title: string;
  description: string;
  mediaUrl: string;
  thumbnailUrl?: string;
  date: string;
  domains?: DevelopmentalDomain[];
  teacherNotes?: string;
  isPrivate?: boolean;
}

/**
 * Portfolio summary for a child.
 */
export interface PortfolioSummary {
  childId: string;
  totalItems: number;
  totalObservations: number;
  totalMilestones: number;
  milestonesAchieved: number;
  totalWorkSamples: number;
  recentActivity: string;
}
