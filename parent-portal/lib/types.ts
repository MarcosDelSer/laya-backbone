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
// Service Agreement Types (Quebec FO-0659)
// ============================================================================

/**
 * Service agreement status.
 */
export type ServiceAgreementStatus =
  | 'draft'
  | 'pending_signature'
  | 'active'
  | 'expired'
  | 'terminated'
  | 'cancelled';

/**
 * Annex status for optional service agreement annexes.
 */
export type AnnexStatus =
  | 'pending'
  | 'signed'
  | 'declined'
  | 'not_applicable';

/**
 * Annex type identifier for Quebec FO-0659 form.
 */
export type AnnexType = 'A' | 'B' | 'C' | 'D';

/**
 * Contribution type for Quebec childcare subsidies.
 */
export type ContributionType =
  | 'reduced'       // $9.35/day Quebec reduced contribution
  | 'full_rate'     // Full market rate
  | 'mixed';        // Combination

/**
 * Signer role in the agreement.
 */
export type SignerRole = 'parent' | 'provider' | 'witness';

/**
 * Signature verification status.
 */
export type SignatureVerificationStatus =
  | 'pending'
  | 'verified'
  | 'failed';

/**
 * Operating days of the week.
 */
export type DayOfWeek =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday';

/**
 * Payment terms for service agreement (Article 5).
 */
export interface ServiceAgreementPaymentTerms {
  contributionType: ContributionType;
  dailyRate: number;
  monthlyAmount: number;
  paymentDueDay: number;
  paymentMethod: string;
  lateFeePercentage?: number;
  lateFeeGraceDays?: number;
  nsfFee?: number;
  depositAmount?: number;
  depositRefundable?: boolean;
}

/**
 * Operating hours for service agreement (Article 3).
 */
export interface ServiceAgreementOperatingHours {
  openTime: string;
  closeTime: string;
  operatingDays: DayOfWeek[];
  maxDailyHours: number;
}

/**
 * Attendance pattern for service agreement (Article 4).
 */
export interface ServiceAgreementAttendancePattern {
  scheduledDays: DayOfWeek[];
  arrivalTime: string;
  departureTime: string;
  isFullTime: boolean;
  daysPerWeek: number;
}

/**
 * Late pickup fee configuration (Article 6).
 */
export interface LatePickupFeeConfig {
  gracePeriodMinutes: number;
  feePerInterval: number;
  intervalMinutes: number;
  maxDailyFee: number;
}

/**
 * Termination conditions (Article 10).
 */
export interface TerminationConditions {
  noticePeriodDays: number;
  immediateTerminationReasons: string[];
  refundPolicy: string;
}

/**
 * Annex A - Field trips authorization (optional).
 */
export interface ServiceAgreementAnnexA {
  id: string;
  agreementId: string;
  type: 'A';
  status: AnnexStatus;
  authorizeFieldTrips: boolean;
  fieldTripConditions?: string;
  transportationAuthorized?: boolean;
  walkingDistanceAuthorized?: boolean;
  signedAt?: string;
  signedBy?: string;
}

/**
 * Annex B - Hygiene items (optional).
 */
export interface ServiceAgreementAnnexB {
  id: string;
  agreementId: string;
  type: 'B';
  status: AnnexStatus;
  hygieneItemsIncluded: boolean;
  itemsList?: string[];
  monthlyFee?: number;
  parentProvides?: string[];
  signedAt?: string;
  signedBy?: string;
}

/**
 * Annex C - Supplementary meals (optional).
 */
export interface ServiceAgreementAnnexC {
  id: string;
  agreementId: string;
  type: 'C';
  status: AnnexStatus;
  supplementaryMealsIncluded: boolean;
  mealsIncluded?: string[];
  dietaryRestrictions?: string;
  allergyInfo?: string;
  monthlyFee?: number;
  signedAt?: string;
  signedBy?: string;
}

/**
 * Annex D - Extended hours beyond 10h/day (optional).
 */
export interface ServiceAgreementAnnexD {
  id: string;
  agreementId: string;
  type: 'D';
  status: AnnexStatus;
  extendedHoursRequired: boolean;
  requestedStartTime?: string;
  requestedEndTime?: string;
  additionalHoursPerDay?: number;
  hourlyRate?: number;
  monthlyEstimate?: number;
  reason?: string;
  signedAt?: string;
  signedBy?: string;
}

/**
 * Union type for all annex types.
 */
export type ServiceAgreementAnnex =
  | ServiceAgreementAnnexA
  | ServiceAgreementAnnexB
  | ServiceAgreementAnnexC
  | ServiceAgreementAnnexD;

/**
 * Electronic signature with full audit trail.
 */
export interface ServiceAgreementSignature {
  id: string;
  agreementId: string;
  signerRole: SignerRole;
  signerName: string;
  signerPersonId: string;
  signatureData: string;
  signatureType: 'typed' | 'drawn';
  signedAt: string;
  ipAddress: string;
  userAgent?: string;
  geoLocation?: string;
  deviceFingerprint?: string;
  verificationHash: string;
  verificationStatus: SignatureVerificationStatus;
  consumerProtectionAcknowledged: boolean;
  termsAccepted: boolean;
  legalAcknowledged: boolean;
}

/**
 * Consumer Protection Act acknowledgment (Quebec law).
 */
export interface ConsumerProtectionAcknowledgment {
  acknowledged: boolean;
  acknowledgedAt?: string;
  acknowledgedBy?: string;
  coolingOffPeriodEndDate?: string;
  coolingOffDaysRemaining?: number;
}

/**
 * Complete Quebec FO-0659 Service Agreement.
 * Contains all 13 articles as per Quebec regulations.
 */
export interface ServiceAgreement {
  id: string;
  agreementNumber: string;
  status: ServiceAgreementStatus;
  schoolYearId: string;

  // Article 1: Identification of Parties
  childId: string;
  childName: string;
  childDateOfBirth: string;
  parentId: string;
  parentName: string;
  parentAddress: string;
  parentPhone: string;
  parentEmail: string;
  providerId: string;
  providerName: string;
  providerAddress: string;
  providerPhone: string;
  providerPermitNumber?: string;

  // Article 2: Description of Services
  serviceDescription: string;
  programType: string;
  ageGroup: string;
  classroomId?: string;
  classroomName?: string;

  // Article 3: Operating Hours
  operatingHours: ServiceAgreementOperatingHours;

  // Article 4: Attendance Pattern
  attendancePattern: ServiceAgreementAttendancePattern;

  // Article 5: Payment Terms
  paymentTerms: ServiceAgreementPaymentTerms;

  // Article 6: Late Pickup Fees
  latePickupFees: LatePickupFeeConfig;

  // Article 7: Closure Days
  closureDays: string[];
  holidaySchedule?: string;
  vacationWeeks?: number;

  // Article 8: Absence Policy
  absencePolicy: string;
  absenceNotificationRequired: boolean;
  absenceNotificationMethod?: string;
  sickDayPolicy?: string;

  // Article 9: Agreement Duration
  startDate: string;
  endDate: string;
  autoRenewal: boolean;
  renewalNoticeRequired?: boolean;
  renewalNoticeDays?: number;

  // Article 10: Termination Conditions
  terminationConditions: TerminationConditions;

  // Article 11: Special Conditions
  specialConditions?: string;
  specialNeedsAccommodations?: string;
  medicalConditions?: string;
  allergies?: string;
  emergencyContacts?: string;

  // Article 12: Consumer Protection Act Notice
  consumerProtectionAcknowledgment: ConsumerProtectionAcknowledgment;

  // Article 13: Signatures
  signatures: ServiceAgreementSignature[];
  parentSignedAt?: string;
  providerSignedAt?: string;
  allSignaturesComplete: boolean;

  // Annexes A-D (optional)
  annexes: ServiceAgreementAnnex[];

  // Metadata
  createdAt: string;
  updatedAt: string;
  createdBy: string;
  pdfUrl?: string;
  notes?: string;
}

/**
 * Summary view of service agreement for list display.
 */
export interface ServiceAgreementSummary {
  id: string;
  agreementNumber: string;
  status: ServiceAgreementStatus;
  childId: string;
  childName: string;
  parentName: string;
  startDate: string;
  endDate: string;
  allSignaturesComplete: boolean;
  parentSignedAt?: string;
  providerSignedAt?: string;
  createdAt: string;
  updatedAt: string;
}

/**
 * Request payload for signing a service agreement.
 */
export interface SignServiceAgreementRequest {
  agreementId: string;
  signatureData: string;
  signatureType: 'typed' | 'drawn';
  consumerProtectionAcknowledged: boolean;
  termsAccepted: boolean;
  legalAcknowledged: boolean;
  annexSignatures?: {
    annexId: string;
    signed: boolean;
  }[];
}

/**
 * Response from signing a service agreement.
 */
export interface SignServiceAgreementResponse {
  success: boolean;
  signatureId: string;
  agreementStatus: ServiceAgreementStatus;
  allSignaturesComplete: boolean;
  pdfUrl?: string;
  message?: string;
}
