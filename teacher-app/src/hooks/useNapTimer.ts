/**
 * LAYA Teacher App - useNapTimer Hook
 *
 * A custom hook for managing nap timer state and updates.
 * Handles elapsed time calculation, timer updates, and
 * duration formatting for nap tracking.
 */

import {useState, useEffect, useCallback, useRef} from 'react';
import {
  calculateElapsedTime,
  formatElapsedTime,
} from '../api/napApi';

/**
 * Timer state returned by the hook
 */
export interface NapTimerState {
  /** Whether the timer is currently running */
  isRunning: boolean;
  /** Elapsed time in milliseconds */
  elapsedMs: number;
  /** Formatted elapsed time string (MM:SS or HH:MM:SS) */
  formattedTime: string;
  /** Elapsed time in minutes (for display) */
  elapsedMinutes: number;
}

/**
 * Timer actions returned by the hook
 */
export interface NapTimerActions {
  /** Start the timer with an optional start time */
  start: (startTime?: string) => void;
  /** Stop the timer */
  stop: () => void;
  /** Reset the timer */
  reset: () => void;
}

/**
 * Return type for the useNapTimer hook
 */
export type UseNapTimerReturn = [NapTimerState, NapTimerActions];

/**
 * Options for the useNapTimer hook
 */
export interface UseNapTimerOptions {
  /** Initial start time (ISO string) for resuming an active nap */
  initialStartTime?: string | null;
  /** Update interval in milliseconds (default: 1000) */
  updateInterval?: number;
  /** Callback when timer ticks */
  onTick?: (elapsedMs: number) => void;
}

/**
 * Custom hook for managing nap timer state
 *
 * Provides timer state and actions for starting, stopping, and tracking
 * elapsed time during naps. Automatically calculates elapsed time from
 * a start time and updates every second.
 *
 * @param options - Timer configuration options
 * @returns [state, actions] - Timer state and control actions
 *
 * @example
 * ```tsx
 * const [timer, actions] = useNapTimer({
 *   initialStartTime: activeNap?.startTime,
 * });
 *
 * // Display timer
 * <Text>{timer.formattedTime}</Text>
 *
 * // Control timer
 * <Button onPress={() => actions.start()} title="Start" />
 * <Button onPress={() => actions.stop()} title="Stop" />
 * ```
 */
export function useNapTimer(options: UseNapTimerOptions = {}): UseNapTimerReturn {
  const {
    initialStartTime = null,
    updateInterval = 1000,
    onTick,
  } = options;

  const [isRunning, setIsRunning] = useState(!!initialStartTime);
  const [startTime, setStartTime] = useState<string | null>(initialStartTime);
  const [elapsedMs, setElapsedMs] = useState(0);

  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const onTickRef = useRef(onTick);

  // Keep onTick ref updated
  useEffect(() => {
    onTickRef.current = onTick;
  }, [onTick]);

  /**
   * Calculate and update elapsed time
   */
  const updateElapsedTime = useCallback(() => {
    if (startTime) {
      const elapsed = calculateElapsedTime(startTime);
      setElapsedMs(elapsed);
      onTickRef.current?.(elapsed);
    }
  }, [startTime]);

  /**
   * Start the timer interval
   */
  const startInterval = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
    }

    // Update immediately
    updateElapsedTime();

    // Then update at interval
    intervalRef.current = setInterval(updateElapsedTime, updateInterval);
  }, [updateElapsedTime, updateInterval]);

  /**
   * Stop the timer interval
   */
  const stopInterval = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
  }, []);

  /**
   * Start the timer
   */
  const start = useCallback((newStartTime?: string) => {
    const time = newStartTime || new Date().toISOString();
    setStartTime(time);
    setIsRunning(true);
  }, []);

  /**
   * Stop the timer
   */
  const stop = useCallback(() => {
    setIsRunning(false);
    stopInterval();
  }, [stopInterval]);

  /**
   * Reset the timer
   */
  const reset = useCallback(() => {
    setIsRunning(false);
    setStartTime(null);
    setElapsedMs(0);
    stopInterval();
  }, [stopInterval]);

  // Effect to handle timer updates
  useEffect(() => {
    if (isRunning && startTime) {
      startInterval();
    } else {
      stopInterval();
    }

    return () => {
      stopInterval();
    };
  }, [isRunning, startTime, startInterval, stopInterval]);

  // Effect to handle initial start time changes
  useEffect(() => {
    if (initialStartTime && !isRunning) {
      setStartTime(initialStartTime);
      setIsRunning(true);
    } else if (!initialStartTime && isRunning) {
      reset();
    }
  }, [initialStartTime]);

  // Calculate derived values
  const formattedTime = formatElapsedTime(elapsedMs);
  const elapsedMinutes = Math.floor(elapsedMs / 60000);

  const state: NapTimerState = {
    isRunning,
    elapsedMs,
    formattedTime,
    elapsedMinutes,
  };

  const actions: NapTimerActions = {
    start,
    stop,
    reset,
  };

  return [state, actions];
}

export default useNapTimer;
