/**
 * LAYA Teacher App - Meal API
 *
 * API functions for managing child meal logging including meal types,
 * portion tracking, and allergy alerts. Follows patterns from
 * Gibbon CareTracking MealGateway.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Child,
  MealRecord,
  MealType,
  PortionSize,
} from '../types';

/**
 * Response type for meals list endpoint
 */
interface MealsListResponse {
  children: ChildWithMeals[];
  summary: MealsSummary;
}

/**
 * Child data combined with their meal records for the day
 */
export interface ChildWithMeals {
  child: Child;
  meals: MealRecord[];
}

/**
 * Daily meals summary statistics
 */
export interface MealsSummary {
  totalChildren: number;
  mealsLogged: number;
  breakfastLogged: number;
  lunchLogged: number;
  snacksLogged: number;
}

/**
 * Request payload for logging a meal
 */
interface LogMealRequest {
  childId: string;
  mealType: MealType;
  foodItems: string[];
  portion: PortionSize;
  notes?: string;
  time?: string;
}

/**
 * Response from meal logging operation
 */
interface MealLogResponse {
  mealRecord: MealRecord;
  message: string;
}

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Get the current time in ISO format
 */
function getCurrentTimeISO(): string {
  return new Date().toISOString();
}

/**
 * Fetch all children with their meal records for the current day
 */
export async function fetchTodayMeals(): Promise<ApiResponse<MealsListResponse>> {
  const date = getCurrentDate();
  return api.get<MealsListResponse>(
    API_CONFIG.endpoints.meals.list,
    {date},
  );
}

/**
 * Fetch meals for a specific date
 */
export async function fetchMealsByDate(
  date: string,
): Promise<ApiResponse<MealsListResponse>> {
  return api.get<MealsListResponse>(
    API_CONFIG.endpoints.meals.list,
    {date},
  );
}

/**
 * Log a meal for a child
 *
 * Creates a new meal record with the specified meal type, food items, and portion.
 * Follows the pattern from MealGateway::insertMeal()
 */
export async function logMeal(
  childId: string,
  mealType: MealType,
  portion: PortionSize,
  options?: {
    foodItems?: string[];
    notes?: string;
    time?: string;
  },
): Promise<ApiResponse<MealLogResponse>> {
  const request: LogMealRequest = {
    childId,
    mealType,
    foodItems: options?.foodItems || [],
    portion,
    notes: options?.notes,
    time: options?.time || getCurrentTimeISO(),
  };

  return api.post<MealLogResponse>(
    API_CONFIG.endpoints.meals.log,
    request,
  );
}

/**
 * Check if a child has a specific meal logged for the day
 */
export function hasMealLogged(
  meals: MealRecord[],
  mealType: MealType,
): boolean {
  return meals.some(meal => meal.mealType === mealType);
}

/**
 * Get meal record for a specific meal type
 */
export function getMealByType(
  meals: MealRecord[],
  mealType: MealType,
): MealRecord | undefined {
  return meals.find(meal => meal.mealType === mealType);
}

/**
 * Get display label for meal type
 */
export function getMealTypeLabel(mealType: MealType): string {
  const labels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    snack: 'Snack',
  };
  return labels[mealType] || mealType;
}

/**
 * Get display label for portion size
 */
export function getPortionLabel(portion: PortionSize): string {
  const labels: Record<PortionSize, string> = {
    none: 'None',
    half: 'Half',
    full: 'Full',
  };
  return labels[portion] || portion;
}

/**
 * Format time from ISO string for display
 */
export function formatMealTime(timeString: string): string {
  try {
    const date = new Date(timeString);
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  } catch {
    return '';
  }
}

/**
 * Get the suggested meal type based on current time
 */
export function getSuggestedMealType(): MealType {
  const now = new Date();
  const hour = now.getHours();

  if (hour < 10) {
    return 'breakfast';
  } else if (hour < 14) {
    return 'lunch';
  } else {
    return 'snack';
  }
}

/**
 * Get all available meal types
 */
export function getMealTypes(): MealType[] {
  return ['breakfast', 'lunch', 'snack'];
}

/**
 * Get all available portion sizes
 */
export function getPortionSizes(): PortionSize[] {
  return ['none', 'half', 'full'];
}
