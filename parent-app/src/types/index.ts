/**
 * LAYA Parent App - Type Definitions
 *
 * Core type definitions for the parent app including navigation,
 * data models, and API types.
 */

// Navigation Types
export type RootStackParamList = {
  DailyFeed: undefined;
  PhotoGallery: {childId?: string};
  PhotoDetail: {photoId: string};
  Invoices: undefined;
  InvoiceDetail: {invoiceId: string};
  Messages: undefined;
  Conversation: {conversationId: string};
  Signatures: undefined;
  SignDocument: {signatureId: string};
};

// Child Types
export interface Child {
  id: string;
  firstName: string;
  lastName: string;
  photoUrl: string | null;
  dateOfBirth: string;
  classroomId: string;
  classroomName: string;
}

// Daily Feed Types
export type FeedEventType =
  | 'check_in'
  | 'check_out'
  | 'meal'
  | 'nap'
  | 'diaper'
  | 'activity'
  | 'photo'
  | 'incident'
  | 'note';

export interface FeedEvent {
  id: string;
  childId: string;
  type: FeedEventType;
  title: string;
  description: string | null;
  timestamp: string;
  photoUrl: string | null;
  metadata: Record<string, unknown> | null;
}

export interface DailyFeed {
  date: string;
  childId: string;
  events: FeedEvent[];
  summary: DailySummary | null;
}

export interface DailySummary {
  mealsCount: number;
  napMinutes: number;
  activitiesCount: number;
  photosCount: number;
}

// Photo Gallery Types
export interface Photo {
  id: string;
  uri: string;
  thumbnailUri: string;
  childIds: string[];
  caption: string | null;
  takenAt: string;
  takenBy: string;
  downloadUrl: string;
}

// Invoice Types
export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';

export interface Invoice {
  id: string;
  invoiceNumber: string;
  issueDate: string;
  dueDate: string;
  status: InvoiceStatus;
  amount: number;
  currency: string;
  items: InvoiceItem[];
  pdfUrl: string | null;
}

export interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

// Messaging Types
export interface Conversation {
  id: string;
  participants: ConversationParticipant[];
  lastMessage: Message | null;
  unreadCount: number;
  updatedAt: string;
}

export interface ConversationParticipant {
  id: string;
  name: string;
  role: 'parent' | 'teacher' | 'admin';
  photoUrl: string | null;
}

export interface Message {
  id: string;
  conversationId: string;
  senderId: string;
  senderName: string;
  content: string;
  sentAt: string;
  readAt: string | null;
  attachments: MessageAttachment[];
}

export interface MessageAttachment {
  id: string;
  type: 'image' | 'document';
  url: string;
  name: string;
  size: number;
}

// E-Signature Types
export type SignatureStatus = 'pending' | 'signed' | 'expired';

export interface SignatureRequest {
  id: string;
  documentTitle: string;
  description: string | null;
  requestedAt: string;
  expiresAt: string | null;
  status: SignatureStatus;
  signedAt: string | null;
  documentUrl: string;
}

// Push Notification Types
export interface NotificationPreferences {
  dailyReport: boolean;
  incidents: boolean;
  messages: boolean;
  invoices: boolean;
  photos: boolean;
}

// User/Parent Types
export interface Parent {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string | null;
  childIds: string[];
}

// API Response Types
export interface ApiResponse<T> {
  success: boolean;
  data: T | null;
  error: ApiError | null;
}

export interface ApiError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  pageSize: number;
  hasMore: boolean;
}
