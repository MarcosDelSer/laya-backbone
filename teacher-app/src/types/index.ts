/**
 * LAYA Teacher App - Type Definitions
 *
 * Core type definitions for the teacher app including navigation,
 * data models, and API types.
 */

// Navigation Types
export type RootStackParamList = {
  Attendance: undefined;
  MealLogging: {childId?: string};
  NapTracking: {childId?: string};
  DiaperTracking: {childId?: string};
  PhotoCapture: undefined;
};

// Child Types
export interface Child {
  id: string;
  firstName: string;
  lastName: string;
  photoUrl: string | null;
  dateOfBirth: string;
  allergies: Allergy[];
  classroomId: string;
  parentIds: string[];
}

export interface Allergy {
  id: string;
  allergen: string;
  severity: 'mild' | 'moderate' | 'severe';
  notes: string | null;
}

// Attendance Types
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'early_pickup';

export interface AttendanceRecord {
  id: string;
  childId: string;
  date: string;
  checkInTime: string | null;
  checkOutTime: string | null;
  status: AttendanceStatus;
  checkedInBy: string | null;
  checkedOutBy: string | null;
  notes: string | null;
}

// Meal Types
export type MealType = 'breakfast' | 'lunch' | 'snack';
export type PortionSize = 'none' | 'half' | 'full';

export interface MealRecord {
  id: string;
  childId: string;
  date: string;
  mealType: MealType;
  foodItems: string[];
  portion: PortionSize;
  notes: string | null;
  loggedBy: string;
  loggedAt: string;
}

// Nap Types
export interface NapRecord {
  id: string;
  childId: string;
  date: string;
  startTime: string;
  endTime: string | null;
  durationMinutes: number | null;
  notes: string | null;
  loggedBy: string;
}

// Diaper Types
export type DiaperType = 'wet' | 'soiled' | 'dry';

export interface DiaperRecord {
  id: string;
  childId: string;
  date: string;
  time: string;
  type: DiaperType;
  notes: string | null;
  loggedBy: string;
}

// Photo Types
export interface PhotoRecord {
  id: string;
  uri: string;
  childIds: string[];
  caption: string | null;
  takenAt: string;
  takenBy: string;
  uploadedAt: string | null;
  uploadStatus: 'pending' | 'uploading' | 'uploaded' | 'failed';
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

// User/Teacher Types
export interface Teacher {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  classroomIds: string[];
}

export interface Classroom {
  id: string;
  name: string;
  capacity: number;
  teacherIds: string[];
  childIds: string[];
}
