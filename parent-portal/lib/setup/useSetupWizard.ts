/**
 * useSetupWizard Hook
 *
 * React hook for managing setup wizard state and API communication.
 * Handles step navigation, data persistence, and integration with Gibbon backend.
 *
 * @module useSetupWizard
 */

import { useState, useEffect, useCallback } from 'react';
import { gibbonClient, ApiError } from '../api';

// ============================================================================
// API Endpoints for Setup Wizard
// ============================================================================

/**
 * Setup wizard API endpoints following Gibbon API conventions.
 * Uses snake_case field naming to match PHP backend expectations.
 */
const SETUP_ENDPOINTS = {
  CURRENT_STEP: '/api/v1/setup/current-step',
  STEP: (stepId: string) => `/api/v1/setup/steps/${stepId}`,
  STEP_PREVIOUS: (stepId: string) => `/api/v1/setup/steps/${stepId}/previous`,
  PROGRESS: '/api/v1/setup/progress',
} as const;

/**
 * Transform camelCase keys to snake_case for API compatibility.
 * The Gibbon backend expects snake_case field names.
 */
function toSnakeCase(data: Record<string, any>): Record<string, any> {
  const result: Record<string, any> = {};
  for (const key of Object.keys(data)) {
    const snakeKey = key.replace(/[A-Z]/g, letter => `_${letter.toLowerCase()}`);
    result[snakeKey] = data[key];
  }
  return result;
}

/**
 * Transform snake_case keys to camelCase for frontend compatibility.
 */
function toCamelCase(data: Record<string, any>): Record<string, any> {
  const result: Record<string, any> = {};
  for (const key of Object.keys(data)) {
    const camelKey = key.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
    result[camelKey] = data[key];
  }
  return result;
}

// ============================================================================
// Type Definitions
// ============================================================================

/**
 * Wizard step definition
 */
export interface WizardStep {
  id: string;
  name: string;
  class: string;
  required: boolean;
  isCompleted: boolean;
  canAccess: boolean;
  data: Record<string, any>;
}

/**
 * Wizard state
 */
export interface WizardState {
  currentStep: WizardStep | null;
  data: Record<string, any>;
  isLoading: boolean;
  error: string | null;
  completionPercentage: number;
}

/**
 * Wizard hook return type
 */
export interface UseSetupWizardReturn {
  currentStep: WizardStep | null;
  data: Record<string, any>;
  isLoading: boolean;
  error: string | null;
  completionPercentage: number;
  updateData: (stepData: Record<string, any>) => void;
  next: () => Promise<void>;
  previous: () => Promise<void>;
  saveAndResume: () => Promise<void>;
  refresh: () => Promise<void>;
}

/**
 * Custom hook for setup wizard functionality
 *
 * @returns Wizard state and control functions
 *
 * @example
 * ```tsx
 * const { currentStep, data, next, previous, updateData } = useSetupWizard();
 *
 * return (
 *   <div>
 *     <h1>{currentStep?.name}</h1>
 *     <input
 *       value={data.fieldName}
 *       onChange={(e) => updateData({ fieldName: e.target.value })}
 *     />
 *     <button onClick={previous}>Previous</button>
 *     <button onClick={next}>Next</button>
 *   </div>
 * );
 * ```
 */
export function useSetupWizard(): UseSetupWizardReturn {
  const [state, setState] = useState<WizardState>({
    currentStep: null,
    data: {},
    isLoading: false,
    error: null,
    completionPercentage: 0,
  });

  /**
   * Fetch current step from backend using gibbonClient.
   * The response data is transformed from snake_case to camelCase.
   */
  const fetchCurrentStep = useCallback(async () => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      const result = await gibbonClient.get<{
        step: WizardStep | null;
        completionPercentage?: number;
        completion_percentage?: number;
      }>(SETUP_ENDPOINTS.CURRENT_STEP);

      // Handle both camelCase and snake_case responses
      const completionPct = result.completionPercentage ?? result.completion_percentage ?? 0;
      const stepData = result.step?.data ? toCamelCase(result.step.data) : {};

      setState(prev => ({
        ...prev,
        currentStep: result.step,
        data: stepData,
        completionPercentage: completionPct,
        isLoading: false,
      }));
    } catch (error) {
      const errorMessage = error instanceof ApiError
        ? error.userMessage
        : error instanceof Error
          ? error.message
          : 'Unknown error';

      setState(prev => ({
        ...prev,
        error: errorMessage,
        isLoading: false,
      }));
    }
  }, []);

  /**
   * Save step data to backend using gibbonClient.
   * Data is transformed to snake_case before sending to match Gibbon conventions.
   *
   * @param stepId - Step identifier
   * @param data - Step data to save
   * @returns Promise that resolves when save is complete
   */
  const saveStepData = useCallback(async (stepId: string, data: Record<string, any>): Promise<void> => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      // Transform camelCase to snake_case for the backend
      const snakeCaseData = toSnakeCase(data);

      const result = await gibbonClient.post<{
        success?: boolean;
        errors?: Record<string, string>;
      }>(SETUP_ENDPOINTS.STEP(stepId), snakeCaseData);

      // Check for validation errors in response
      if (result.errors && Object.keys(result.errors).length > 0) {
        const errorMessage = Object.values(result.errors).join(', ');
        throw new Error(errorMessage);
      }

      setState(prev => ({ ...prev, isLoading: false }));
    } catch (error) {
      const errorMessage = error instanceof ApiError
        ? error.userMessage
        : error instanceof Error
          ? error.message
          : 'Unknown error';

      setState(prev => ({
        ...prev,
        error: errorMessage,
        isLoading: false,
      }));
      throw error;
    }
  }, []);

  /**
   * Update wizard data in local state
   *
   * @param stepData - Data to merge into current step data
   */
  const updateData = useCallback((stepData: Record<string, any>) => {
    setState(prev => ({
      ...prev,
      data: { ...prev.data, ...stepData },
    }));
  }, []);

  /**
   * Move to next step.
   * Validates and saves current step data before advancing.
   * Uses gibbonClient for API communication.
   */
  const next = useCallback(async () => {
    if (!state.currentStep) {
      return;
    }

    try {
      await saveStepData(state.currentStep.id, state.data);
      await fetchCurrentStep();
    } catch (error) {
      // Error is already set in state by saveStepData
      // Log for debugging but don't re-throw since state is already updated
      if (process.env.NODE_ENV === 'development') {
        console.error('Failed to move to next step:', error);
      }
    }
  }, [state.currentStep, state.data, saveStepData, fetchCurrentStep]);

  /**
   * Move to previous step using gibbonClient.
   */
  const previous = useCallback(async () => {
    if (!state.currentStep) {
      return;
    }

    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      await gibbonClient.get<{ success: boolean }>(
        SETUP_ENDPOINTS.STEP_PREVIOUS(state.currentStep.id)
      );

      await fetchCurrentStep();
    } catch (error) {
      const errorMessage = error instanceof ApiError
        ? error.userMessage
        : error instanceof Error
          ? error.message
          : 'Unknown error';

      setState(prev => ({
        ...prev,
        error: errorMessage,
        isLoading: false,
      }));
    }
  }, [state.currentStep, fetchCurrentStep]);

  /**
   * Save current progress and allow resuming later.
   * Uses gibbonClient for API communication.
   */
  const saveAndResume = useCallback(async () => {
    if (!state.currentStep) {
      return;
    }

    try {
      await saveStepData(state.currentStep.id, state.data);
      // Progress is saved - user can return later and resume
    } catch (error) {
      // Error is already set in state by saveStepData
      if (process.env.NODE_ENV === 'development') {
        console.error('Failed to save and resume:', error);
      }
    }
  }, [state.currentStep, state.data, saveStepData]);

  /**
   * Refresh wizard state from backend
   */
  const refresh = useCallback(async () => {
    await fetchCurrentStep();
  }, [fetchCurrentStep]);

  // Fetch current step on mount
  useEffect(() => {
    fetchCurrentStep();
  }, [fetchCurrentStep]);

  return {
    currentStep: state.currentStep,
    data: state.data,
    isLoading: state.isLoading,
    error: state.error,
    completionPercentage: state.completionPercentage,
    updateData,
    next,
    previous,
    saveAndResume,
    refresh,
  };
}
