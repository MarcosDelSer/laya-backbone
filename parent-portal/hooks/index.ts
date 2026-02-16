/**
 * LAYA Parent Portal - Hooks Index
 *
 * Re-exports all custom auth hooks for convenient importing.
 *
 * @example
 * ```tsx
 * import { useRequireAuth, useUser, useAuthStatus } from '@/hooks';
 * ```
 */

// Re-export useAuth from context for convenience
export { useAuth } from '@/contexts/AuthContext';

// Auth requirement and protection hooks
export { useRequireAuth } from './useRequireAuth';
export type { UseRequireAuthOptions } from './useRequireAuth';

// Simplified data access hooks
export { useUser } from './useUser';
export type { UseUserReturn } from './useUser';

export { useAuthStatus } from './useAuthStatus';
export type { UseAuthStatusReturn } from './useAuthStatus';

// Redirect handling hooks
export { useAuthRedirect } from './useAuthRedirect';
export type { UseAuthRedirectOptions } from './useAuthRedirect';
