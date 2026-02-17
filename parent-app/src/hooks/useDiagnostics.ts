/**
 * LAYA Parent App - Diagnostics Hook
 *
 * React hook for integrating iOS real-device diagnostics
 * into React components. Provides easy access to diagnostics
 * collection and export functionality.
 *
 * @see docs/DIAGNOSTICS_PAYLOAD.md for payload contract
 */

import {useCallback, useEffect, useState} from 'react';
import {useNavigation} from '@react-navigation/native';
import diagnosticsService, {
  LogLevel,
  NetworkErrorType,
} from '../services/diagnosticsService';
import {uploadDiagnostics} from '../api/diagnosticsApi';

// ============================================================================
// Types
// ============================================================================

export interface DiagnosticsState {
  isActive: boolean;
  testRunId: string | null;
  logCount: number;
  networkErrorCount: number;
  crashCount: number;
  screenshotCount: number;
  screensVisited: number;
}

export interface UseDiagnosticsReturn {
  /** Current diagnostics state */
  state: DiagnosticsState;

  /** Start a new diagnostics collection session */
  startSession: (testRunId: string) => void;

  /** Stop the current diagnostics session */
  stopSession: () => void;

  /** Log a diagnostic message */
  log: (
    level: LogLevel,
    tag: string,
    message: string,
    metadata?: Record<string, unknown>,
  ) => void;

  /** Record a network error */
  recordError: (
    url: string,
    method: string,
    statusCode: number | null,
    errorType: NetworkErrorType,
    errorMessage: string,
    durationMs: number,
    retryCount?: number,
  ) => void;

  /** Set custom diagnostic data */
  setCustomData: (key: string, value: unknown) => void;

  /** Upload diagnostics to backend */
  exportDiagnostics: () => Promise<{
    success: boolean;
    diagnosticsId?: string;
    error?: string;
  }>;

  /** Check if diagnostics is available (iOS only) */
  isAvailable: boolean;
}

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Hook for integrating diagnostics into React components
 *
 * @example
 * ```tsx
 * function MyScreen() {
 *   const diagnostics = useDiagnostics();
 *
 *   useEffect(() => {
 *     diagnostics.log('info', 'MyScreen', 'Screen loaded');
 *   }, []);
 *
 *   const handleError = (error: Error) => {
 *     diagnostics.log('error', 'MyScreen', error.message);
 *   };
 *
 *   return <View>...</View>;
 * }
 * ```
 */
export function useDiagnostics(): UseDiagnosticsReturn {
  const [state, setState] = useState<DiagnosticsState>(() =>
    diagnosticsService.getDiagnosticsSummary(),
  );

  const navigation = useNavigation();

  // Track screen visits when navigation state changes
  useEffect(() => {
    const unsubscribe = navigation.addListener('state', () => {
      const currentRoute = navigation.getCurrentRoute?.();
      if (currentRoute?.name) {
        diagnosticsService.recordScreenVisit(currentRoute.name);
        // Update state
        setState(diagnosticsService.getDiagnosticsSummary());
      }
    });

    return unsubscribe;
  }, [navigation]);

  // Start diagnostics session
  const startSession = useCallback((testRunId: string) => {
    diagnosticsService.startDiagnosticsSession(testRunId);
    setState(diagnosticsService.getDiagnosticsSummary());
  }, []);

  // Stop diagnostics session
  const stopSession = useCallback(() => {
    diagnosticsService.stopDiagnosticsSession();
    setState(diagnosticsService.getDiagnosticsSummary());
  }, []);

  // Log diagnostic message
  const log = useCallback(
    (
      level: LogLevel,
      tag: string,
      message: string,
      metadata?: Record<string, unknown>,
    ) => {
      diagnosticsService.logDiagnostic(level, tag, message, metadata);
      setState(diagnosticsService.getDiagnosticsSummary());
    },
    [],
  );

  // Record network error
  const recordError = useCallback(
    (
      url: string,
      method: string,
      statusCode: number | null,
      errorType: NetworkErrorType,
      errorMessage: string,
      durationMs: number,
      retryCount: number = 0,
    ) => {
      diagnosticsService.recordNetworkError(
        url,
        method,
        statusCode,
        errorType,
        errorMessage,
        durationMs,
        retryCount,
      );
      setState(diagnosticsService.getDiagnosticsSummary());
    },
    [],
  );

  // Set custom data
  const setCustomData = useCallback((key: string, value: unknown) => {
    diagnosticsService.setCustomData(key, value);
  }, []);

  // Export diagnostics
  const exportDiagnostics = useCallback(async () => {
    const result = await uploadDiagnostics();
    setState(diagnosticsService.getDiagnosticsSummary());

    return {
      success: result.success,
      diagnosticsId: result.diagnosticsId,
      error: result.error?.message,
    };
  }, []);

  return {
    state,
    startSession,
    stopSession,
    log,
    recordError,
    setCustomData,
    exportDiagnostics,
    isAvailable: true, // iOS diagnostics are always available
  };
}

/**
 * Simplified hook for just logging diagnostics
 *
 * @example
 * ```tsx
 * function MyComponent() {
 *   const log = useDiagnosticsLog('MyComponent');
 *
 *   log('info', 'Component mounted');
 *   log('error', 'Something went wrong', { errorCode: 123 });
 * }
 * ```
 */
export function useDiagnosticsLog(tag: string) {
  return useCallback(
    (
      level: LogLevel,
      message: string,
      metadata?: Record<string, unknown>,
    ) => {
      diagnosticsService.logDiagnostic(level, tag, message, metadata);
    },
    [tag],
  );
}

export default useDiagnostics;
