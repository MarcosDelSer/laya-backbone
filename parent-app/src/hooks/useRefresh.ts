/**
 * LAYA Parent App - useRefresh Hook
 *
 * Custom hook for managing pull-to-refresh state in FlatList components.
 * Provides a clean interface for triggering refresh actions with loading state
 * and error handling support.
 */

import {useState, useCallback} from 'react';

// ============================================================================
// Types
// ============================================================================

/**
 * Configuration options for the useRefresh hook.
 */
export interface UseRefreshOptions {
  /**
   * Callback invoked when an error occurs during refresh.
   * @param error - The error that occurred
   */
  onError?: (error: Error) => void;
  /**
   * Callback invoked after a successful refresh.
   */
  onSuccess?: () => void;
}

/**
 * Return type for the useRefresh hook.
 */
export interface UseRefreshResult<T> {
  /**
   * Whether a refresh is currently in progress.
   */
  refreshing: boolean;
  /**
   * The data returned from the last successful refresh.
   */
  data: T | null;
  /**
   * The error from the last failed refresh, if any.
   */
  error: Error | null;
  /**
   * Triggers a refresh by calling the provided fetch function.
   * Safe to pass directly to RefreshControl's onRefresh prop.
   */
  onRefresh: () => Promise<void>;
  /**
   * Manually set the data without triggering a refresh.
   * Useful for optimistic updates or initial data loading.
   */
  setData: React.Dispatch<React.SetStateAction<T | null>>;
  /**
   * Clear any existing error state.
   */
  clearError: () => void;
}

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Custom hook for managing pull-to-refresh functionality.
 *
 * @param fetchFn - Async function that fetches the data
 * @param options - Optional configuration for error/success callbacks
 * @returns Object containing refresh state and handlers
 *
 * @example
 * ```tsx
 * const { refreshing, data, error, onRefresh } = useRefresh(
 *   () => api.fetchDailyReports(),
 *   { onError: (err) => Alert.alert('Error', err.message) }
 * );
 *
 * return (
 *   <FlatList
 *     data={data}
 *     refreshControl={
 *       <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
 *     }
 *     // ...
 *   />
 * );
 * ```
 */
export function useRefresh<T>(
  fetchFn: () => Promise<T>,
  options?: UseRefreshOptions,
): UseRefreshResult<T> {
  const [refreshing, setRefreshing] = useState(false);
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<Error | null>(null);

  const onRefresh = useCallback(async (): Promise<void> => {
    setRefreshing(true);
    setError(null);

    try {
      const result = await fetchFn();
      setData(result);
      options?.onSuccess?.();
    } catch (err) {
      const errorObj = err instanceof Error ? err : new Error(String(err));
      setError(errorObj);
      options?.onError?.(errorObj);
    } finally {
      setRefreshing(false);
    }
  }, [fetchFn, options]);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    refreshing,
    data,
    error,
    onRefresh,
    setData,
    clearError,
  };
}

// ============================================================================
// Additional Utility Hooks
// ============================================================================

/**
 * Hook for managing initial load + pull-to-refresh pattern.
 * Automatically triggers a refresh on mount.
 *
 * @param fetchFn - Async function that fetches the data
 * @param options - Optional configuration for error/success callbacks
 * @returns Object containing refresh state, handlers, and loading state
 *
 * @example
 * ```tsx
 * const { refreshing, data, loading, onRefresh } = useAutoRefresh(
 *   () => api.fetchDailyReports()
 * );
 *
 * if (loading) {
 *   return <LoadingSpinner />;
 * }
 * ```
 */
export interface UseAutoRefreshResult<T> extends UseRefreshResult<T> {
  /**
   * True during the initial load (before first data is available).
   */
  loading: boolean;
}

export function useAutoRefresh<T>(
  fetchFn: () => Promise<T>,
  options?: UseRefreshOptions,
): UseAutoRefreshResult<T> {
  const [hasLoaded, setHasLoaded] = useState(false);
  const refreshResult = useRefresh(fetchFn, {
    ...options,
    onSuccess: () => {
      setHasLoaded(true);
      options?.onSuccess?.();
    },
  });

  // Calculate loading state: true if we haven't loaded yet and are refreshing
  const loading = !hasLoaded && refreshResult.refreshing;

  return {
    ...refreshResult,
    loading,
  };
}

export default useRefresh;
