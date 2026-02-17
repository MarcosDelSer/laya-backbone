/**
 * Tests for Zod validation schemas.
 *
 * Verifies that all validation schemas correctly validate valid data,
 * reject invalid data, and provide helpful error messages.
 */

import { describe, it, expect } from 'vitest';
import {
  // Common schemas
  uuidSchema,
  dateSchema,
  datetimeSchema,
  urlSchema,
  emailSchema,
  nonEmptyStringSchema,
  paginationParamsSchema,
  paginatedResponseSchema,
  baseResponseSchema,
  // Daily Report schemas
  mealAmountSchema,
  mealTypeSchema,
  napQualitySchema,
  mealEntrySchema,
  napEntrySchema,
  activityEntrySchema,
  photoSchema,
  dailyReportSchema,
  // Invoice schemas
  invoiceStatusSchema,
  invoiceItemSchema,
  invoiceSchema,
  // Message schemas
  messageSchema,
  messageThreadSchema,
  sendMessageRequestSchema,
  createThreadRequestSchema,
  // Document schemas
  documentStatusSchema,
  documentSchema,
  signDocumentRequestSchema,
  // Child schemas
  childSchema,
  // AI Service schemas
  activityTypeSchema,
  activityDifficultySchema,
  ageRangeSchema,
  activitySchema,
  activityRecommendationSchema,
  activityRecommendationRequestSchema,
  activityRecommendationResponseSchema,
  specialNeedTypeSchema,
  coachingCategorySchema,
  coachingPrioritySchema,
  coachingSchema,
  coachingGuidanceSchema,
  coachingGuidanceRequestSchema,
  coachingGuidanceResponseSchema,
  // API Response schemas
  healthCheckResponseSchema,
  apiErrorResponseSchema,
  csrfTokenResponseSchema,
  // User and Auth schemas
  userRoleSchema,
  userSchema,
  loginRequestSchema,
  loginResponseSchema,
  tokenRefreshRequestSchema,
  tokenRefreshResponseSchema,
  // Utilities
  validate,
  safeParse,
  validateArray,
  validatePaginatedResponse,
} from '../schemas';

// ============================================================================
// Test Helpers
// ============================================================================

const validUuid = '550e8400-e29b-41d4-a716-446655440000';
const validDate = '2024-02-17';
const validDatetime = '2024-02-17T10:30:00.000Z';
const validUrl = 'https://example.com/test';
const validEmail = 'test@example.com';

// ============================================================================
// Common Schemas Tests
// ============================================================================

describe('Common Schemas', () => {
  describe('uuidSchema', () => {
    it('should accept valid UUIDs', () => {
      expect(() => uuidSchema.parse(validUuid)).not.toThrow();
      expect(() => uuidSchema.parse('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')).not.toThrow();
    });

    it('should reject invalid UUIDs', () => {
      expect(() => uuidSchema.parse('not-a-uuid')).toThrow();
      expect(() => uuidSchema.parse('12345')).toThrow();
      expect(() => uuidSchema.parse('')).toThrow();
    });
  });

  describe('dateSchema', () => {
    it('should accept valid dates', () => {
      expect(() => dateSchema.parse('2024-02-17')).not.toThrow();
      expect(() => dateSchema.parse('2024-12-31')).not.toThrow();
    });

    it('should reject invalid dates', () => {
      expect(() => dateSchema.parse('2024-13-01')).toThrow(); // Invalid month
      expect(() => dateSchema.parse('17-02-2024')).toThrow(); // Wrong format
      expect(() => dateSchema.parse('2024/02/17')).toThrow(); // Wrong separator
    });
  });

  describe('datetimeSchema', () => {
    it('should accept valid datetimes', () => {
      expect(() => datetimeSchema.parse('2024-02-17T10:30:00.000Z')).not.toThrow();
      expect(() => datetimeSchema.parse('2024-02-17T10:30:00Z')).not.toThrow();
      expect(() => datetimeSchema.parse('2024-02-17T10:30:00+05:30')).not.toThrow();
    });

    it('should reject invalid datetimes', () => {
      expect(() => datetimeSchema.parse('2024-02-17')).toThrow();
      expect(() => datetimeSchema.parse('not-a-datetime')).toThrow();
    });
  });

  describe('urlSchema', () => {
    it('should accept valid URLs', () => {
      expect(() => urlSchema.parse('https://example.com')).not.toThrow();
      expect(() => urlSchema.parse('http://localhost:3000/api/test')).not.toThrow();
    });

    it('should reject invalid URLs', () => {
      expect(() => urlSchema.parse('not-a-url')).toThrow();
      expect(() => urlSchema.parse('//example.com')).toThrow();
      expect(() => urlSchema.parse('a'.repeat(2050))).toThrow(); // Too long
    });
  });

  describe('emailSchema', () => {
    it('should accept valid emails', () => {
      expect(() => emailSchema.parse('test@example.com')).not.toThrow();
      expect(() => emailSchema.parse('user.name+tag@example.co.uk')).not.toThrow();
    });

    it('should reject invalid emails', () => {
      expect(() => emailSchema.parse('not-an-email')).toThrow();
      expect(() => emailSchema.parse('@example.com')).toThrow();
      expect(() => emailSchema.parse('test@')).toThrow();
    });
  });

  describe('nonEmptyStringSchema', () => {
    it('should accept non-empty strings', () => {
      expect(() => nonEmptyStringSchema.parse('hello')).not.toThrow();
      expect(() => nonEmptyStringSchema.parse('a')).not.toThrow();
    });

    it('should reject empty strings', () => {
      expect(() => nonEmptyStringSchema.parse('')).toThrow();
    });

    it('should reject strings that are too long', () => {
      expect(() => nonEmptyStringSchema.parse('a'.repeat(10001))).toThrow();
    });
  });

  describe('paginationParamsSchema', () => {
    it('should accept valid pagination params', () => {
      expect(() => paginationParamsSchema.parse({ skip: 0, limit: 10 })).not.toThrow();
      expect(() => paginationParamsSchema.parse({ skip: 20 })).not.toThrow();
      expect(() => paginationParamsSchema.parse({ limit: 50 })).not.toThrow();
      expect(() => paginationParamsSchema.parse({})).not.toThrow();
    });

    it('should reject invalid pagination params', () => {
      expect(() => paginationParamsSchema.parse({ skip: -1 })).toThrow();
      expect(() => paginationParamsSchema.parse({ limit: 0 })).toThrow();
      expect(() => paginationParamsSchema.parse({ limit: 1001 })).toThrow();
    });
  });

  describe('paginatedResponseSchema', () => {
    it('should accept valid paginated responses', () => {
      const schema = paginatedResponseSchema(uuidSchema);
      const validResponse = {
        items: [validUuid],
        total: 1,
        skip: 0,
        limit: 10,
      };
      expect(() => schema.parse(validResponse)).not.toThrow();
    });

    it('should reject invalid paginated responses', () => {
      const schema = paginatedResponseSchema(uuidSchema);
      expect(() => schema.parse({ items: ['invalid-uuid'], total: 1, skip: 0, limit: 10 })).toThrow();
      expect(() => schema.parse({ items: [], total: -1, skip: 0, limit: 10 })).toThrow();
    });
  });

  describe('baseResponseSchema', () => {
    it('should accept valid base responses', () => {
      const validResponse = {
        id: validUuid,
        createdAt: validDatetime,
        updatedAt: validDatetime,
      };
      expect(() => baseResponseSchema.parse(validResponse)).not.toThrow();
    });

    it('should accept base responses without optional fields', () => {
      const validResponse = { id: validUuid };
      expect(() => baseResponseSchema.parse(validResponse)).not.toThrow();
    });
  });
});

// ============================================================================
// Daily Report Schemas Tests
// ============================================================================

describe('Daily Report Schemas', () => {
  describe('mealAmountSchema', () => {
    it('should accept valid meal amounts', () => {
      ['all', 'most', 'some', 'none'].forEach(amount => {
        expect(() => mealAmountSchema.parse(amount)).not.toThrow();
      });
    });

    it('should reject invalid meal amounts', () => {
      expect(() => mealAmountSchema.parse('invalid')).toThrow();
    });
  });

  describe('mealTypeSchema', () => {
    it('should accept valid meal types', () => {
      ['breakfast', 'lunch', 'snack'].forEach(type => {
        expect(() => mealTypeSchema.parse(type)).not.toThrow();
      });
    });
  });

  describe('mealEntrySchema', () => {
    it('should accept valid meal entries', () => {
      const validEntry = {
        id: validUuid,
        type: 'breakfast',
        time: '08:00',
        notes: 'Ate well',
        amount: 'most',
      };
      expect(() => mealEntrySchema.parse(validEntry)).not.toThrow();
    });

    it('should reject meal entries with invalid types', () => {
      const invalidEntry = {
        id: validUuid,
        type: 'dinner', // invalid
        time: '08:00',
        notes: 'Ate well',
        amount: 'most',
      };
      expect(() => mealEntrySchema.parse(invalidEntry)).toThrow();
    });
  });

  describe('napEntrySchema', () => {
    it('should accept valid nap entries', () => {
      const validEntry = {
        id: validUuid,
        startTime: '13:00',
        endTime: '15:00',
        quality: 'good',
      };
      expect(() => napEntrySchema.parse(validEntry)).not.toThrow();
    });
  });

  describe('activityEntrySchema', () => {
    it('should accept valid activity entries', () => {
      const validEntry = {
        id: validUuid,
        name: 'Art time',
        description: 'Drawing with crayons',
        time: '10:00',
      };
      expect(() => activityEntrySchema.parse(validEntry)).not.toThrow();
    });
  });

  describe('photoSchema', () => {
    it('should accept valid photos', () => {
      const validPhoto = {
        id: validUuid,
        url: validUrl,
        caption: 'Playing outside',
        taggedChildren: [validUuid],
      };
      expect(() => photoSchema.parse(validPhoto)).not.toThrow();
    });
  });

  describe('dailyReportSchema', () => {
    it('should accept valid daily reports', () => {
      const validReport = {
        id: validUuid,
        date: validDate,
        childId: validUuid,
        meals: [],
        naps: [],
        activities: [],
        photos: [],
      };
      expect(() => dailyReportSchema.parse(validReport)).not.toThrow();
    });

    it('should accept daily reports with data', () => {
      const validReport = {
        id: validUuid,
        date: validDate,
        childId: validUuid,
        meals: [
          {
            id: validUuid,
            type: 'breakfast',
            time: '08:00',
            notes: 'Ate well',
            amount: 'most',
          },
        ],
        naps: [
          {
            id: validUuid,
            startTime: '13:00',
            endTime: '15:00',
            quality: 'good',
          },
        ],
        activities: [
          {
            id: validUuid,
            name: 'Art time',
            description: 'Drawing',
            time: '10:00',
          },
        ],
        photos: [
          {
            id: validUuid,
            url: validUrl,
            caption: 'Playing',
            taggedChildren: [validUuid],
          },
        ],
      };
      expect(() => dailyReportSchema.parse(validReport)).not.toThrow();
    });
  });
});

// ============================================================================
// Invoice Schemas Tests
// ============================================================================

describe('Invoice Schemas', () => {
  describe('invoiceStatusSchema', () => {
    it('should accept valid statuses', () => {
      ['paid', 'pending', 'overdue'].forEach(status => {
        expect(() => invoiceStatusSchema.parse(status)).not.toThrow();
      });
    });
  });

  describe('invoiceItemSchema', () => {
    it('should accept valid invoice items', () => {
      const validItem = {
        description: 'Tuition for February',
        quantity: 1,
        unitPrice: 500,
        total: 500,
      };
      expect(() => invoiceItemSchema.parse(validItem)).not.toThrow();
    });

    it('should reject negative values', () => {
      const invalidItem = {
        description: 'Tuition',
        quantity: -1,
        unitPrice: 500,
        total: 500,
      };
      expect(() => invoiceItemSchema.parse(invalidItem)).toThrow();
    });
  });

  describe('invoiceSchema', () => {
    it('should accept valid invoices', () => {
      const validInvoice = {
        id: validUuid,
        number: 'INV-001',
        date: validDate,
        dueDate: validDate,
        amount: 500,
        status: 'pending',
        pdfUrl: validUrl,
        items: [],
      };
      expect(() => invoiceSchema.parse(validInvoice)).not.toThrow();
    });
  });
});

// ============================================================================
// Message Schemas Tests
// ============================================================================

describe('Message Schemas', () => {
  describe('messageSchema', () => {
    it('should accept valid messages', () => {
      const validMessage = {
        id: validUuid,
        threadId: validUuid,
        senderId: validUuid,
        senderName: 'John Doe',
        content: 'Hello, this is a test message.',
        timestamp: validDatetime,
        read: false,
      };
      expect(() => messageSchema.parse(validMessage)).not.toThrow();
    });
  });

  describe('messageThreadSchema', () => {
    it('should accept valid message threads', () => {
      const validThread = {
        id: validUuid,
        subject: 'Test subject',
        participants: [validUuid],
        lastMessage: {
          id: validUuid,
          threadId: validUuid,
          senderId: validUuid,
          senderName: 'John Doe',
          content: 'Last message',
          timestamp: validDatetime,
          read: true,
        },
        unreadCount: 0,
      };
      expect(() => messageThreadSchema.parse(validThread)).not.toThrow();
    });
  });

  describe('sendMessageRequestSchema', () => {
    it('should accept valid send message requests', () => {
      const validRequest = {
        threadId: validUuid,
        content: 'This is my message',
      };
      expect(() => sendMessageRequestSchema.parse(validRequest)).not.toThrow();
    });

    it('should reject empty content', () => {
      const invalidRequest = {
        threadId: validUuid,
        content: '',
      };
      expect(() => sendMessageRequestSchema.parse(invalidRequest)).toThrow();
    });
  });

  describe('createThreadRequestSchema', () => {
    it('should accept valid create thread requests', () => {
      const validRequest = {
        subject: 'New conversation',
        recipientIds: [validUuid],
        initialMessage: 'Hello!',
      };
      expect(() => createThreadRequestSchema.parse(validRequest)).not.toThrow();
    });

    it('should reject requests with no recipients', () => {
      const invalidRequest = {
        subject: 'New conversation',
        recipientIds: [],
        initialMessage: 'Hello!',
      };
      expect(() => createThreadRequestSchema.parse(invalidRequest)).toThrow();
    });
  });
});

// ============================================================================
// Document Schemas Tests
// ============================================================================

describe('Document Schemas', () => {
  describe('documentSchema', () => {
    it('should accept valid documents', () => {
      const validDocument = {
        id: validUuid,
        title: 'Enrollment Agreement',
        type: 'contract',
        uploadDate: validDate,
        status: 'pending',
        pdfUrl: validUrl,
      };
      expect(() => documentSchema.parse(validDocument)).not.toThrow();
    });

    it('should accept signed documents', () => {
      const signedDocument = {
        id: validUuid,
        title: 'Enrollment Agreement',
        type: 'contract',
        uploadDate: validDate,
        status: 'signed',
        signedAt: validDatetime,
        signatureUrl: validUrl,
        pdfUrl: validUrl,
      };
      expect(() => documentSchema.parse(signedDocument)).not.toThrow();
    });
  });

  describe('signDocumentRequestSchema', () => {
    it('should accept valid sign document requests', () => {
      const validRequest = {
        documentId: validUuid,
        signatureData: 'base64-encoded-signature-data',
      };
      expect(() => signDocumentRequestSchema.parse(validRequest)).not.toThrow();
    });
  });
});

// ============================================================================
// Child Schemas Tests
// ============================================================================

describe('Child Schemas', () => {
  describe('childSchema', () => {
    it('should accept valid child profiles', () => {
      const validChild = {
        id: validUuid,
        firstName: 'Emma',
        lastName: 'Johnson',
        dateOfBirth: validDate,
        classroomId: validUuid,
        classroomName: 'Toddler Room A',
      };
      expect(() => childSchema.parse(validChild)).not.toThrow();
    });

    it('should accept child profiles with optional photo', () => {
      const validChild = {
        id: validUuid,
        firstName: 'Emma',
        lastName: 'Johnson',
        dateOfBirth: validDate,
        profilePhotoUrl: validUrl,
        classroomId: validUuid,
        classroomName: 'Toddler Room A',
      };
      expect(() => childSchema.parse(validChild)).not.toThrow();
    });
  });
});

// ============================================================================
// AI Service Schemas Tests
// ============================================================================

describe('AI Service Schemas', () => {
  describe('activityTypeSchema', () => {
    it('should accept valid activity types', () => {
      ['cognitive', 'motor', 'social', 'language', 'creative', 'sensory'].forEach(type => {
        expect(() => activityTypeSchema.parse(type)).not.toThrow();
      });
    });
  });

  describe('ageRangeSchema', () => {
    it('should accept valid age ranges', () => {
      const validRange = { minMonths: 12, maxMonths: 24 };
      expect(() => ageRangeSchema.parse(validRange)).not.toThrow();
    });

    it('should reject age ranges outside valid bounds', () => {
      expect(() => ageRangeSchema.parse({ minMonths: -1, maxMonths: 24 })).toThrow();
      expect(() => ageRangeSchema.parse({ minMonths: 12, maxMonths: 300 })).toThrow();
    });
  });

  describe('activitySchema', () => {
    it('should accept valid activities', () => {
      const validActivity = {
        id: validUuid,
        name: 'Block Building',
        description: 'Building towers with blocks',
        activityType: 'cognitive',
        difficulty: 'easy',
        durationMinutes: 30,
        materialsNeeded: ['blocks', 'mat'],
        isActive: true,
      };
      expect(() => activitySchema.parse(validActivity)).not.toThrow();
    });

    it('should reject activities with invalid duration', () => {
      const invalidActivity = {
        id: validUuid,
        name: 'Test',
        description: 'Test activity',
        activityType: 'cognitive',
        difficulty: 'easy',
        durationMinutes: 0,
        materialsNeeded: [],
        isActive: true,
      };
      expect(() => activitySchema.parse(invalidActivity)).toThrow();
    });
  });

  describe('activityRecommendationSchema', () => {
    it('should accept valid activity recommendations', () => {
      const validRecommendation = {
        activity: {
          id: validUuid,
          name: 'Block Building',
          description: 'Building towers',
          activityType: 'cognitive',
          difficulty: 'easy',
          durationMinutes: 30,
          materialsNeeded: ['blocks'],
          isActive: true,
        },
        relevanceScore: 0.85,
        reasoning: 'Good for cognitive development',
      };
      expect(() => activityRecommendationSchema.parse(validRecommendation)).not.toThrow();
    });

    it('should reject recommendations with invalid relevance scores', () => {
      const invalidRecommendation = {
        activity: {
          id: validUuid,
          name: 'Test',
          description: 'Test',
          activityType: 'cognitive',
          difficulty: 'easy',
          durationMinutes: 30,
          materialsNeeded: [],
          isActive: true,
        },
        relevanceScore: 1.5, // Invalid: > 1
      };
      expect(() => activityRecommendationSchema.parse(invalidRecommendation)).toThrow();
    });
  });

  describe('specialNeedTypeSchema', () => {
    it('should accept valid special need types', () => {
      [
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
      ].forEach(type => {
        expect(() => specialNeedTypeSchema.parse(type)).not.toThrow();
      });
    });
  });

  describe('coachingSchema', () => {
    it('should accept valid coaching guidance', () => {
      const validCoaching = {
        id: validUuid,
        title: 'Managing Sensory Overload',
        content: 'Detailed guidance...',
        category: 'sensory_support',
        specialNeedTypes: ['autism', 'sensory_processing'],
        priority: 'high',
        targetAudience: 'Parents and Teachers',
        isPublished: true,
        viewCount: 42,
      };
      expect(() => coachingSchema.parse(validCoaching)).not.toThrow();
    });
  });
});

// ============================================================================
// API Response Schemas Tests
// ============================================================================

describe('API Response Schemas', () => {
  describe('healthCheckResponseSchema', () => {
    it('should accept valid health check responses', () => {
      const validResponse = {
        status: 'healthy',
        service: 'parent-portal',
        version: '1.0.0',
      };
      expect(() => healthCheckResponseSchema.parse(validResponse)).not.toThrow();
    });
  });

  describe('apiErrorResponseSchema', () => {
    it('should accept valid error responses', () => {
      const validError = {
        detail: 'Resource not found',
        statusCode: 404,
      };
      expect(() => apiErrorResponseSchema.parse(validError)).not.toThrow();
    });
  });

  describe('csrfTokenResponseSchema', () => {
    it('should accept valid CSRF token responses', () => {
      const validResponse = {
        csrf_token: 'random-token-string-here',
      };
      expect(() => csrfTokenResponseSchema.parse(validResponse)).not.toThrow();
    });

    it('should reject empty CSRF tokens', () => {
      expect(() => csrfTokenResponseSchema.parse({ csrf_token: '' })).toThrow();
    });
  });
});

// ============================================================================
// User and Authentication Schemas Tests
// ============================================================================

describe('User and Authentication Schemas', () => {
  describe('userRoleSchema', () => {
    it('should accept valid user roles', () => {
      ['parent', 'teacher', 'admin', 'director'].forEach(role => {
        expect(() => userRoleSchema.parse(role)).not.toThrow();
      });
    });
  });

  describe('userSchema', () => {
    it('should accept valid users', () => {
      const validUser = {
        id: validUuid,
        email: validEmail,
        role: 'parent',
        firstName: 'John',
        lastName: 'Doe',
      };
      expect(() => userSchema.parse(validUser)).not.toThrow();
    });

    it('should accept users without optional fields', () => {
      const validUser = {
        id: validUuid,
        email: validEmail,
        role: 'parent',
      };
      expect(() => userSchema.parse(validUser)).not.toThrow();
    });
  });

  describe('loginRequestSchema', () => {
    it('should accept valid login requests', () => {
      const validRequest = {
        email: validEmail,
        password: 'SecurePassword123!',
      };
      expect(() => loginRequestSchema.parse(validRequest)).not.toThrow();
    });

    it('should reject login requests with short passwords', () => {
      const invalidRequest = {
        email: validEmail,
        password: 'short',
      };
      expect(() => loginRequestSchema.parse(invalidRequest)).toThrow();
    });
  });

  describe('loginResponseSchema', () => {
    it('should accept valid login responses', () => {
      const validResponse = {
        access_token: 'jwt-token-here',
        token_type: 'Bearer',
        expires_in: 3600,
        user: {
          id: validUuid,
          email: validEmail,
          role: 'parent',
        },
      };
      expect(() => loginResponseSchema.parse(validResponse)).not.toThrow();
    });
  });

  describe('tokenRefreshRequestSchema', () => {
    it('should accept valid token refresh requests', () => {
      const validRequest = {
        refresh_token: 'refresh-token-here',
      };
      expect(() => tokenRefreshRequestSchema.parse(validRequest)).not.toThrow();
    });
  });

  describe('tokenRefreshResponseSchema', () => {
    it('should accept valid token refresh responses', () => {
      const validResponse = {
        access_token: 'new-jwt-token',
        token_type: 'Bearer',
        expires_in: 3600,
      };
      expect(() => tokenRefreshResponseSchema.parse(validResponse)).not.toThrow();
    });
  });
});

// ============================================================================
// Validation Utilities Tests
// ============================================================================

describe('Validation Utilities', () => {
  describe('validate', () => {
    it('should validate and return typed data', () => {
      const data = { id: validUuid };
      const result = validate(baseResponseSchema, data);
      expect(result).toEqual(data);
    });

    it('should throw ZodError on validation failure', () => {
      const invalidData = { id: 'not-a-uuid' };
      expect(() => validate(baseResponseSchema, invalidData)).toThrow();
    });
  });

  describe('safeParse', () => {
    it('should return success result for valid data', () => {
      const data = { id: validUuid };
      const result = safeParse(baseResponseSchema, data);
      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toEqual(data);
      }
    });

    it('should return error result for invalid data', () => {
      const invalidData = { id: 'not-a-uuid' };
      const result = safeParse(baseResponseSchema, invalidData);
      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error).toBeDefined();
      }
    });
  });

  describe('validateArray', () => {
    it('should validate arrays of items', () => {
      const data = [validUuid, '550e8400-e29b-41d4-a716-446655440001'];
      const result = validateArray(uuidSchema, data);
      expect(result).toEqual(data);
    });

    it('should throw on invalid array items', () => {
      const invalidData = [validUuid, 'not-a-uuid'];
      expect(() => validateArray(uuidSchema, invalidData)).toThrow();
    });
  });

  describe('validatePaginatedResponse', () => {
    it('should validate paginated responses', () => {
      const data = {
        items: [validUuid],
        total: 1,
        skip: 0,
        limit: 10,
      };
      const result = validatePaginatedResponse(uuidSchema, data);
      expect(result).toEqual(data);
    });

    it('should throw on invalid paginated responses', () => {
      const invalidData = {
        items: ['not-a-uuid'],
        total: 1,
        skip: 0,
        limit: 10,
      };
      expect(() => validatePaginatedResponse(uuidSchema, invalidData)).toThrow();
    });
  });
});
