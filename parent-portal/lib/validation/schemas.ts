/**
 * Zod validation schemas for API data in LAYA Parent Portal.
 *
 * Provides runtime validation and type inference for all API request/response types.
 * These schemas ensure data integrity and prevent security vulnerabilities by
 * validating all data against strict type definitions.
 *
 * Security features:
 * - Runtime type validation to prevent type confusion attacks
 * - String length limits to prevent DoS attacks
 * - URL validation to prevent malicious redirects
 * - Enum validation to prevent invalid values
 * - Required field validation to prevent missing data exploits
 *
 * @module lib/validation/schemas
 */

import { z } from 'zod';

// ============================================================================
// Common Validation Helpers
// ============================================================================

/**
 * UUID v4 pattern regex.
 */
const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/**
 * ISO 8601 date pattern regex (YYYY-MM-DD).
 */
const DATE_REGEX = /^\d{4}-\d{2}-\d{2}$/;

/**
 * ISO 8601 datetime pattern regex.
 */
const DATETIME_REGEX = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?(Z|[+-]\d{2}:\d{2})?$/;

/**
 * Validates a string as a UUID.
 */
export const uuidSchema = z.string().regex(UUID_REGEX, 'Invalid UUID format');

/**
 * Validates a string as an ISO 8601 date.
 */
export const dateSchema = z.string().regex(DATE_REGEX, 'Invalid date format (expected YYYY-MM-DD)');

/**
 * Validates a string as an ISO 8601 datetime.
 */
export const datetimeSchema = z.string().regex(DATETIME_REGEX, 'Invalid datetime format');

/**
 * Validates a URL string.
 */
export const urlSchema = z.string().url('Invalid URL format').max(2048, 'URL too long');

/**
 * Validates an email address.
 */
export const emailSchema = z.string().email('Invalid email format').max(255, 'Email too long');

/**
 * Validates a non-empty string with reasonable length limit.
 */
export const nonEmptyStringSchema = z.string().min(1, 'String cannot be empty').max(10000, 'String too long');

// ============================================================================
// Common Types
// ============================================================================

/**
 * Pagination parameters for list requests.
 */
export const paginationParamsSchema = z.object({
  skip: z.number().int().min(0).optional(),
  limit: z.number().int().min(1).max(1000).optional(),
});

export type PaginationParams = z.infer<typeof paginationParamsSchema>;

/**
 * Paginated response wrapper.
 */
export const paginatedResponseSchema = <T extends z.ZodTypeAny>(itemSchema: T) =>
  z.object({
    items: z.array(itemSchema),
    total: z.number().int().min(0),
    skip: z.number().int().min(0),
    limit: z.number().int().min(1),
  });

/**
 * Base response with common fields.
 */
export const baseResponseSchema = z.object({
  id: uuidSchema,
  createdAt: datetimeSchema.optional(),
  updatedAt: datetimeSchema.optional(),
});

// ============================================================================
// Daily Report Types
// ============================================================================

/**
 * Meal consumption amount levels.
 */
export const mealAmountSchema = z.enum(['all', 'most', 'some', 'none']);

export type MealAmount = z.infer<typeof mealAmountSchema>;

/**
 * Types of meals served.
 */
export const mealTypeSchema = z.enum(['breakfast', 'lunch', 'snack']);

export type MealType = z.infer<typeof mealTypeSchema>;

/**
 * Nap quality levels.
 */
export const napQualitySchema = z.enum(['good', 'fair', 'poor']);

export type NapQuality = z.infer<typeof napQualitySchema>;

/**
 * Individual meal entry in a daily report.
 */
export const mealEntrySchema = z.object({
  id: uuidSchema,
  type: mealTypeSchema,
  time: z.string().max(100),
  notes: z.string().max(1000),
  amount: mealAmountSchema,
});

export type MealEntry = z.infer<typeof mealEntrySchema>;

/**
 * Individual nap entry in a daily report.
 */
export const napEntrySchema = z.object({
  id: uuidSchema,
  startTime: z.string().max(100),
  endTime: z.string().max(100),
  quality: napQualitySchema,
});

export type NapEntry = z.infer<typeof napEntrySchema>;

/**
 * Individual activity entry in a daily report.
 */
export const activityEntrySchema = z.object({
  id: uuidSchema,
  name: z.string().max(200),
  description: z.string().max(2000),
  time: z.string().max(100),
});

export type ActivityEntry = z.infer<typeof activityEntrySchema>;

/**
 * Photo attached to a daily report.
 */
export const photoSchema = z.object({
  id: uuidSchema,
  url: urlSchema,
  caption: z.string().max(500),
  taggedChildren: z.array(uuidSchema),
});

export type Photo = z.infer<typeof photoSchema>;

/**
 * Complete daily report for a child.
 */
export const dailyReportSchema = z.object({
  id: uuidSchema,
  date: dateSchema,
  childId: uuidSchema,
  meals: z.array(mealEntrySchema),
  naps: z.array(napEntrySchema),
  activities: z.array(activityEntrySchema),
  photos: z.array(photoSchema),
});

export type DailyReport = z.infer<typeof dailyReportSchema>;

// ============================================================================
// Invoice Types
// ============================================================================

/**
 * Invoice payment status.
 */
export const invoiceStatusSchema = z.enum(['paid', 'pending', 'overdue']);

export type InvoiceStatus = z.infer<typeof invoiceStatusSchema>;

/**
 * Individual line item in an invoice.
 */
export const invoiceItemSchema = z.object({
  description: z.string().max(500),
  quantity: z.number().min(0),
  unitPrice: z.number().min(0),
  total: z.number().min(0),
});

export type InvoiceItem = z.infer<typeof invoiceItemSchema>;

/**
 * Complete invoice record.
 */
export const invoiceSchema = z.object({
  id: uuidSchema,
  number: z.string().max(50),
  date: dateSchema,
  dueDate: dateSchema,
  amount: z.number().min(0),
  status: invoiceStatusSchema,
  pdfUrl: urlSchema,
  items: z.array(invoiceItemSchema),
});

export type Invoice = z.infer<typeof invoiceSchema>;

// ============================================================================
// Message Types
// ============================================================================

/**
 * Individual message in a conversation thread.
 */
export const messageSchema = z.object({
  id: uuidSchema,
  threadId: uuidSchema,
  senderId: uuidSchema,
  senderName: z.string().max(200),
  content: z.string().max(10000),
  timestamp: datetimeSchema,
  read: z.boolean(),
});

export type Message = z.infer<typeof messageSchema>;

/**
 * Conversation thread containing messages.
 */
export const messageThreadSchema = z.object({
  id: uuidSchema,
  subject: z.string().max(500),
  participants: z.array(uuidSchema),
  lastMessage: messageSchema,
  unreadCount: z.number().int().min(0),
});

export type MessageThread = z.infer<typeof messageThreadSchema>;

/**
 * Request payload for sending a message.
 */
export const sendMessageRequestSchema = z.object({
  threadId: uuidSchema,
  content: z.string().min(1).max(10000),
});

export type SendMessageRequest = z.infer<typeof sendMessageRequestSchema>;

/**
 * Request payload for creating a new thread.
 */
export const createThreadRequestSchema = z.object({
  subject: z.string().min(1).max(500),
  recipientIds: z.array(uuidSchema).min(1),
  initialMessage: z.string().min(1).max(10000),
});

export type CreateThreadRequest = z.infer<typeof createThreadRequestSchema>;

// ============================================================================
// Document Types
// ============================================================================

/**
 * Document signature status.
 */
export const documentStatusSchema = z.enum(['pending', 'signed']);

export type DocumentStatus = z.infer<typeof documentStatusSchema>;

/**
 * Document requiring signature.
 */
export const documentSchema = z.object({
  id: uuidSchema,
  title: z.string().max(500),
  type: z.string().max(100),
  uploadDate: dateSchema,
  status: documentStatusSchema,
  signedAt: datetimeSchema.optional(),
  signatureUrl: urlSchema.optional(),
  pdfUrl: urlSchema,
});

export type Document = z.infer<typeof documentSchema>;

/**
 * Request payload for signing a document.
 */
export const signDocumentRequestSchema = z.object({
  documentId: uuidSchema,
  signatureData: z.string().min(1).max(100000), // Base64 encoded signature
});

export type SignDocumentRequest = z.infer<typeof signDocumentRequestSchema>;

// ============================================================================
// Child Types
// ============================================================================

/**
 * Child profile information.
 */
export const childSchema = z.object({
  id: uuidSchema,
  firstName: z.string().max(100),
  lastName: z.string().max(100),
  dateOfBirth: dateSchema,
  profilePhotoUrl: urlSchema.optional(),
  classroomId: uuidSchema,
  classroomName: z.string().max(200),
});

export type Child = z.infer<typeof childSchema>;

// ============================================================================
// AI Service Types
// ============================================================================

/**
 * Activity type categories.
 */
export const activityTypeSchema = z.enum([
  'cognitive',
  'motor',
  'social',
  'language',
  'creative',
  'sensory',
]);

export type ActivityType = z.infer<typeof activityTypeSchema>;

/**
 * Activity difficulty levels.
 */
export const activityDifficultySchema = z.enum(['easy', 'medium', 'hard']);

export type ActivityDifficulty = z.infer<typeof activityDifficultySchema>;

/**
 * Age range specification.
 */
export const ageRangeSchema = z.object({
  minMonths: z.number().int().min(0).max(216), // 0-18 years
  maxMonths: z.number().int().min(0).max(216),
});

export type AgeRange = z.infer<typeof ageRangeSchema>;

/**
 * Educational activity from AI service.
 */
export const activitySchema = z.object({
  id: uuidSchema,
  name: z.string().max(200),
  description: z.string().max(5000),
  activityType: activityTypeSchema,
  difficulty: activityDifficultySchema,
  durationMinutes: z.number().int().min(1).max(480), // 1 minute to 8 hours
  materialsNeeded: z.array(z.string().max(200)),
  ageRange: ageRangeSchema.optional(),
  specialNeedsAdaptations: z.string().max(5000).optional(),
  isActive: z.boolean(),
  createdAt: datetimeSchema.optional(),
  updatedAt: datetimeSchema.optional(),
});

export type Activity = z.infer<typeof activitySchema>;

/**
 * Activity recommendation with relevance score.
 */
export const activityRecommendationSchema = z.object({
  activity: activitySchema,
  relevanceScore: z.number().min(0).max(1),
  reasoning: z.string().max(2000).optional(),
});

export type ActivityRecommendation = z.infer<typeof activityRecommendationSchema>;

/**
 * Request payload for activity recommendations.
 */
export const activityRecommendationRequestSchema = z.object({
  childId: uuidSchema,
  activityTypes: z.array(activityTypeSchema).optional(),
  maxRecommendations: z.number().int().min(1).max(50).optional(),
  includeSpecialNeeds: z.boolean().optional(),
});

export type ActivityRecommendationRequest = z.infer<typeof activityRecommendationRequestSchema>;

/**
 * Response payload for activity recommendations.
 */
export const activityRecommendationResponseSchema = z.object({
  childId: uuidSchema,
  recommendations: z.array(activityRecommendationSchema),
  generatedAt: datetimeSchema,
});

export type ActivityRecommendationResponse = z.infer<typeof activityRecommendationResponseSchema>;

/**
 * Special need type categories.
 */
export const specialNeedTypeSchema = z.enum([
  'autism',
  'adhd',
  'dyslexia',
  'speech_delay',
  'motor_delay',
  'sensory_processing',
  'behavioral',
  'cognitive_delay',
  'visual_impairment',
  'hearing_impairment',
  'other',
]);

export type SpecialNeedType = z.infer<typeof specialNeedTypeSchema>;

/**
 * Coaching guidance category.
 */
export const coachingCategorySchema = z.enum([
  'activity_adaptation',
  'communication',
  'behavior_management',
  'sensory_support',
  'motor_support',
  'social_skills',
  'parent_guidance',
  'educator_training',
]);

export type CoachingCategory = z.infer<typeof coachingCategorySchema>;

/**
 * Coaching priority levels.
 */
export const coachingPrioritySchema = z.enum(['low', 'medium', 'high', 'urgent']);

export type CoachingPriority = z.infer<typeof coachingPrioritySchema>;

/**
 * Coaching guidance item.
 */
export const coachingSchema = z.object({
  id: uuidSchema,
  title: z.string().max(500),
  content: z.string().max(50000),
  category: coachingCategorySchema,
  specialNeedTypes: z.array(specialNeedTypeSchema),
  priority: coachingPrioritySchema,
  targetAudience: z.string().max(500),
  prerequisites: z.string().max(2000).optional(),
  isPublished: z.boolean(),
  viewCount: z.number().int().min(0),
  createdAt: datetimeSchema.optional(),
  updatedAt: datetimeSchema.optional(),
});

export type Coaching = z.infer<typeof coachingSchema>;

/**
 * Coaching guidance with relevance information.
 */
export const coachingGuidanceSchema = z.object({
  coaching: coachingSchema,
  relevanceScore: z.number().min(0).max(1),
  applicabilityNotes: z.string().max(2000).optional(),
});

export type CoachingGuidance = z.infer<typeof coachingGuidanceSchema>;

/**
 * Request payload for coaching guidance.
 */
export const coachingGuidanceRequestSchema = z.object({
  childId: uuidSchema,
  specialNeedTypes: z.array(specialNeedTypeSchema).min(1),
  situationDescription: z.string().max(5000).optional(),
  category: coachingCategorySchema.optional(),
  maxRecommendations: z.number().int().min(1).max(50).optional(),
});

export type CoachingGuidanceRequest = z.infer<typeof coachingGuidanceRequestSchema>;

/**
 * Response payload for coaching guidance.
 */
export const coachingGuidanceResponseSchema = z.object({
  childId: uuidSchema,
  guidanceItems: z.array(coachingGuidanceSchema),
  generatedAt: datetimeSchema,
});

export type CoachingGuidanceResponse = z.infer<typeof coachingGuidanceResponseSchema>;

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Health check response.
 */
export const healthCheckResponseSchema = z.object({
  status: z.string().max(50),
  service: z.string().max(100),
  version: z.string().max(50),
});

export type HealthCheckResponse = z.infer<typeof healthCheckResponseSchema>;

/**
 * Error response from API.
 */
export const apiErrorResponseSchema = z.object({
  detail: z.string().max(5000),
  statusCode: z.number().int().optional(),
});

export type ApiErrorResponse = z.infer<typeof apiErrorResponseSchema>;

/**
 * CSRF token response.
 */
export const csrfTokenResponseSchema = z.object({
  csrf_token: z.string().min(1).max(500),
});

export type CsrfTokenResponse = z.infer<typeof csrfTokenResponseSchema>;

// ============================================================================
// User and Authentication Types
// ============================================================================

/**
 * User role types.
 */
export const userRoleSchema = z.enum(['parent', 'teacher', 'admin', 'director']);

export type UserRole = z.infer<typeof userRoleSchema>;

/**
 * User information.
 */
export const userSchema = z.object({
  id: uuidSchema,
  email: emailSchema,
  role: userRoleSchema,
  firstName: z.string().max(100).optional(),
  lastName: z.string().max(100).optional(),
});

export type User = z.infer<typeof userSchema>;

/**
 * Login request payload.
 */
export const loginRequestSchema = z.object({
  email: emailSchema,
  password: z.string().min(8).max(100),
});

export type LoginRequest = z.infer<typeof loginRequestSchema>;

/**
 * Login response payload.
 */
export const loginResponseSchema = z.object({
  access_token: z.string().min(1),
  token_type: z.string(),
  expires_in: z.number().int().positive().optional(),
  user: userSchema,
});

export type LoginResponse = z.infer<typeof loginResponseSchema>;

/**
 * Token refresh request payload.
 */
export const tokenRefreshRequestSchema = z.object({
  refresh_token: z.string().min(1),
});

export type TokenRefreshRequest = z.infer<typeof tokenRefreshRequestSchema>;

/**
 * Token refresh response payload.
 */
export const tokenRefreshResponseSchema = z.object({
  access_token: z.string().min(1),
  token_type: z.string(),
  expires_in: z.number().int().positive().optional(),
});

export type TokenRefreshResponse = z.infer<typeof tokenRefreshResponseSchema>;

// ============================================================================
// Validation Utilities
// ============================================================================

/**
 * Validates data against a schema and returns typed result.
 * Throws ZodError if validation fails.
 *
 * @param schema - Zod schema to validate against
 * @param data - Data to validate
 * @returns Validated and typed data
 *
 * @example
 * ```ts
 * const validatedUser = validate(userSchema, rawUserData);
 * ```
 */
export function validate<T>(schema: z.ZodSchema<T>, data: unknown): T {
  return schema.parse(data);
}

/**
 * Validates data against a schema and returns result with success flag.
 * Does not throw on validation failure.
 *
 * @param schema - Zod schema to validate against
 * @param data - Data to validate
 * @returns Validation result with success flag and data or error
 *
 * @example
 * ```ts
 * const result = safeParse(userSchema, rawUserData);
 * if (result.success) {
 *   console.log('Valid user:', result.data);
 * } else {
 *   console.error('Validation errors:', result.error);
 * }
 * ```
 */
export function safeParse<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): { success: true; data: T } | { success: false; error: z.ZodError } {
  const result = schema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data };
  }
  return { success: false, error: result.error };
}

/**
 * Validates an array of items against a schema.
 * Throws ZodError if any item fails validation.
 *
 * @param schema - Zod schema for individual items
 * @param items - Array of items to validate
 * @returns Array of validated and typed items
 *
 * @example
 * ```ts
 * const validatedChildren = validateArray(childSchema, rawChildren);
 * ```
 */
export function validateArray<T>(schema: z.ZodSchema<T>, items: unknown[]): T[] {
  return z.array(schema).parse(items);
}

/**
 * Validates a paginated response.
 *
 * @param itemSchema - Zod schema for individual items
 * @param data - Paginated response data to validate
 * @returns Validated paginated response
 *
 * @example
 * ```ts
 * const validatedPage = validatePaginatedResponse(childSchema, rawPageData);
 * ```
 */
export function validatePaginatedResponse<T>(
  itemSchema: z.ZodSchema<T>,
  data: unknown
): z.infer<ReturnType<typeof paginatedResponseSchema>> {
  return paginatedResponseSchema(itemSchema).parse(data);
}
