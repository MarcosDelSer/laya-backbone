/**
 * LAYA Teacher App - useApiRequest Hook
 *
 * A custom hook for managing API request state including loading,
 * error handling, and data management. Provides consistent API
 * call patterns across the app.
 */

import {useState, useCallback, useRef, useEffect} from 'react';
import type {ApiResponse, ApiError} from '../types';

/**
 * State returned by the useApiRequest hook
 */
export interface ApiRequestState<T> {
  /** The data returned from the API */
  data: T | null;
  /** Whether a request is in progress */
  isLoading: boolean;
  /** Whether this is the first load (no data yet) */
  isInitialLoading: boolean;
  /** Whether a refresh is in progress */
  isRefreshing: boolean;
  /** Error message if the request failed */
  error: string | null;
  /** Detailed error object if available */
  errorDetails: ApiError | null;
  /** Whether data has been fetched at least once */
  hasFetched: boolean;
}

/**
 * Actions returned by the useApiRequest hook
 */
export interface ApiRequestActions<T, P extends unknown[]> {
  /** Execute the API request */
  execute: (...args: P) => Promise<ApiResponse<T>>;
  /** Execute as a refresh (shows refresh indicator instead of loading) */
  refresh: (...args: P) => Promise<ApiResponse<T>>;
  /** Set data manually */
  setData: (data: T | null) => void;
  /** Reset the state */
  reset: () => void;
  /** Clear error */
  clearError: () => void;
}

/**
 * Return type for the useApiRequest hook
 */
export type UseApiRequestReturn<T, P extends unknown[]> = [
  ApiRequestState<T>,
  ApiRequestActions<T, P>,
];

/**
 * Options for the useApiRequest hook
 */
export interface UseApiRequestOptions<T> {
  /** Initial data value */
  initialData?: T | null;
  /** Callback when request succeeds */
  onSuccess?: (data: T) => void;
  /** Callback when request fails */
  onError?: (error: ApiError | null, message: string) => void;
  /** Whether to reset error on new request */
  resetErrorOnRequest?: boolean;
}

/**
 * Custom hook for managing API request state
 *
 * Provides a standardized way to handle API requests with loading states,
 * error handling, and data management.
 *
 * @param apiFunction - The API function to call
 * @param options - Configuration options
 * @returns [state, actions] - Request state and control actions
 *
 * @example
 * ```tsx
 * const [state, actions] = useApiRequest(fetchTodayAttendance, {
 *   onSuccess: (data) => console.log('Fetched', data.children.length, 'children'),
 *   onError: (error) => Alert.alert('Error', error?.message || 'Failed to fetch'),
 * });
 *
 * // Execute request
 * useEffect(() => {
 *   actions.execute();
 * }, []);
 *
 * // Show loading state
 * if (state.isInitialLoading) {
 *   return <LoadingSpinner message="Loading..." />;
 * }
 *
 * // Show error state
 * if (state.error) {
 *   return <ErrorMessage message={state.error} onRetry={() => actions.execute()} />;
 * }
 * ```
 */
export function useApiRequest<T, P extends unknown[] = []>(
  apiFunction: (...args: P) => Promise<ApiResponse<T>>,
  options: UseApiRequestOptions<T> = {},
): UseApiRequestReturn<T, P> {
  const {
    initialData = null,
    onSuccess,
    onError,
    resetErrorOnRequest = true,
  } = options;

  const [data, setData] = useState<T | null>(initialData);
  const [isLoading, setIsLoading] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [errorDetails, setErrorDetails] = useState<ApiError | null>(null);
  const [hasFetched, setHasFetched] = useState(false);

  // Keep callbacks in refs to avoid stale closures
  const onSuccessRef = useRef(onSuccess);
  const onErrorRef = useRef(onError);

  useEffect(() => {
    onSuccessRef.current = onSuccess;
    onErrorRef.current = onError;
  }, [onSuccess, onError]);

  /**
   * Execute the API request
   */
  const execute = useCallback(
    async (...args: P): Promise<ApiResponse<T>> => {
      setIsLoading(true);
      if (resetErrorOnRequest) {
        setError(null);
        setErrorDetails(null);
      }

      try {
        const response = await apiFunction(...args);

        if (response.success && response.data !== null) {
          setData(response.data);
          setError(null);
          setErrorDetails(null);
          onSuccessRef.current?.(response.data);
        } else if (response.error) {
          setError(response.error.message);
          setErrorDetails(response.error);
          onErrorRef.current?.(response.error, response.error.message);
        }

        setHasFetched(true);
        return response;
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : 'An unexpected error occurred';
        setError(errorMessage);
        setErrorDetails(null);
        onErrorRef.current?.(null, errorMessage);

        setHasFetched(true);
        return {
          success: false,
          data: null,
          error: {
            code: 'UNKNOWN_ERROR',
            message: errorMessage,
          },
        };
      } finally {
        setIsLoading(false);
        setIsRefreshing(false);
      }
    },
    [apiFunction, resetErrorOnRequest],
  );

  /**
   * Execute as a refresh (shows refresh indicator instead of loading)
   */
  const refresh = useCallback(
    async (...args: P): Promise<ApiResponse<T>> => {
      setIsRefreshing(true);
      return execute(...args);
    },
    [execute],
  );

  /**
   * Reset the state
   */
  const reset = useCallback(() => {
    setData(initialData);
    setIsLoading(false);
    setIsRefreshing(false);
    setError(null);
    setErrorDetails(null);
    setHasFetched(false);
  }, [initialData]);

  /**
   * Clear error
   */
  const clearError = useCallback(() => {
    setError(null);
    setErrorDetails(null);
  }, []);

  const isInitialLoading = isLoading && !hasFetched && !isRefreshing;

  const state: ApiRequestState<T> = {
    data,
    isLoading,
    isInitialLoading,
    isRefreshing,
    error,
    errorDetails,
    hasFetched,
  };

  const actions: ApiRequestActions<T, P> = {
    execute,
    refresh,
    setData,
    reset,
    clearError,
  };

  return [state, actions];
}

export default useApiRequest;
