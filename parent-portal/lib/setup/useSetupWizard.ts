/**
 * useSetupWizard Hook
 *
 * React hook for managing setup wizard state and API communication.
 * Handles step navigation, data persistence, and integration with Gibbon backend.
 *
 * @module useSetupWizard
 */

import { useState, useEffect, useCallback } from 'react';

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
   * Fetch current step from backend
   */
  const fetchCurrentStep = useCallback(async () => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch('/api/setup/current-step', {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Failed to fetch current step: ${response.statusText}`);
      }

      const result = await response.json();

      setState(prev => ({
        ...prev,
        currentStep: result.step,
        data: result.step?.data || {},
        completionPercentage: result.completionPercentage || 0,
        isLoading: false,
      }));
    } catch (error) {
      setState(prev => ({
        ...prev,
        error: error instanceof Error ? error.message : 'Unknown error',
        isLoading: false,
      }));
    }
  }, []);

  /**
   * Save step data to backend
   *
   * @param stepId - Step identifier
   * @param data - Step data to save
   * @returns Promise that resolves when save is complete
   */
  const saveStepData = useCallback(async (stepId: string, data: Record<string, any>): Promise<void> => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch(`/api/setup/steps/${stepId}`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `Failed to save step: ${response.statusText}`);
      }

      const result = await response.json();

      // Check for validation errors
      if (result.errors && Object.keys(result.errors).length > 0) {
        const errorMessage = Object.values(result.errors).join(', ');
        throw new Error(errorMessage);
      }

      setState(prev => ({ ...prev, isLoading: false }));
    } catch (error) {
      setState(prev => ({
        ...prev,
        error: error instanceof Error ? error.message : 'Unknown error',
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
   * Move to next step
   * Validates and saves current step data before advancing
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
      console.error('Failed to move to next step:', error);
    }
  }, [state.currentStep, state.data, saveStepData, fetchCurrentStep]);

  /**
   * Move to previous step
   */
  const previous = useCallback(async () => {
    if (!state.currentStep) {
      return;
    }

    setState(prev => ({ ...prev, isLoading: true, error: null }));

    try {
      const response = await fetch(`/api/setup/steps/${state.currentStep.id}/previous`, {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Failed to go to previous step: ${response.statusText}`);
      }

      await fetchCurrentStep();
    } catch (error) {
      setState(prev => ({
        ...prev,
        error: error instanceof Error ? error.message : 'Unknown error',
        isLoading: false,
      }));
    }
  }, [state.currentStep, fetchCurrentStep]);

  /**
   * Save current progress and allow resuming later
   */
  const saveAndResume = useCallback(async () => {
    if (!state.currentStep) {
      return;
    }

    try {
      await saveStepData(state.currentStep.id, state.data);
      // Optionally redirect to a "resume later" page or show a message
    } catch (error) {
      console.error('Failed to save and resume:', error);
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
