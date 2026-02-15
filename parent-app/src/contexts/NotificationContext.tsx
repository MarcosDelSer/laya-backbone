/**
 * LAYA Parent App - Notification Context
 *
 * Provides app-wide notification state and actions through React Context.
 * This context wraps the useNotifications hook to provide consistent
 * notification state across all components.
 *
 * Usage:
 * 1. Wrap your app with NotificationProvider in App.tsx
 * 2. Use useNotificationContext() in any component to access notification state
 */

import React, {createContext, useContext, useMemo} from 'react';

import {NotificationPayload} from '../services/pushNotifications';
import {
  useNotifications,
  NotificationState,
  NotificationActions,
  UseNotificationsOptions,
} from '../hooks/useNotifications';

/**
 * Context value type combining state and actions
 */
export interface NotificationContextValue {
  /** Current notification state */
  state: NotificationState;
  /** Notification control actions */
  actions: NotificationActions;
}

/**
 * Props for the NotificationProvider component
 */
export interface NotificationProviderProps {
  /** Child components */
  children: React.ReactNode;
  /** Whether to automatically register on mount */
  registerOnMount?: boolean;
  /** Callback when a notification is received */
  onNotificationReceived?: (payload: NotificationPayload) => void;
}

/**
 * Create the notification context with undefined default value
 * to enforce usage within provider
 */
const NotificationContext = createContext<NotificationContextValue | undefined>(
  undefined,
);

/**
 * Display name for debugging
 */
NotificationContext.displayName = 'NotificationContext';

/**
 * NotificationProvider component
 *
 * Wraps the application with notification state management.
 * Should be placed near the root of the component tree.
 *
 * @example
 * ```tsx
 * function App() {
 *   return (
 *     <NotificationProvider
 *       onNotificationReceived={(payload) => {
 *         // Handle notification (e.g., navigate to relevant screen)
 *       }}
 *     >
 *       <AppNavigator />
 *     </NotificationProvider>
 *   );
 * }
 * ```
 */
export function NotificationProvider({
  children,
  registerOnMount = false,
  onNotificationReceived,
}: NotificationProviderProps): React.JSX.Element {
  // Configure notification hook options
  const options: UseNotificationsOptions = useMemo(
    () => ({
      registerOnMount,
      checkOnMount: true,
      onNotificationReceived,
    }),
    [registerOnMount, onNotificationReceived],
  );

  // Use the notifications hook
  const [state, actions] = useNotifications(options);

  // Memoize context value to prevent unnecessary re-renders
  const contextValue: NotificationContextValue = useMemo(
    () => ({
      state,
      actions,
    }),
    [state, actions],
  );

  return (
    <NotificationContext.Provider value={contextValue}>
      {children}
    </NotificationContext.Provider>
  );
}

/**
 * Hook to access the notification context
 *
 * Must be used within a NotificationProvider.
 * Throws an error if used outside the provider.
 *
 * @returns NotificationContextValue - The notification state and actions
 *
 * @example
 * ```tsx
 * function SettingsScreen() {
 *   const { state, actions } = useNotificationContext();
 *
 *   const handleEnableNotifications = async () => {
 *     const success = await actions.register();
 *     if (success) {
 *       // Notifications enabled
 *     }
 *   };
 *
 *   return (
 *     <View>
 *       <Text>
 *         Notifications: {state.isEnabled ? 'Enabled' : 'Disabled'}
 *       </Text>
 *       {!state.isRegistered && (
 *         <Button
 *           title="Enable Notifications"
 *           onPress={handleEnableNotifications}
 *           disabled={state.isLoading}
 *         />
 *       )}
 *     </View>
 *   );
 * }
 * ```
 */
export function useNotificationContext(): NotificationContextValue {
  const context = useContext(NotificationContext);

  if (context === undefined) {
    throw new Error(
      'useNotificationContext must be used within a NotificationProvider. ' +
        'Ensure your component is wrapped with <NotificationProvider>.',
    );
  }

  return context;
}

/**
 * Re-export types for convenience
 */
export type {NotificationState, NotificationActions} from '../hooks/useNotifications';
export type {NotificationPayload} from '../services/pushNotifications';

export default NotificationContext;
