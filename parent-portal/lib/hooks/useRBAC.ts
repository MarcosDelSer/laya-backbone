/**
 * RBAC React Hook for permission checking in LAYA Parent Portal.
 *
 * Provides a declarative way to check user permissions, roles, and access
 * rights within React components with caching and loading state management.
 */

'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  canAccess,
  checkPermission,
  getUserPermissions,
  getUserRoles,
  hasAnyRole,
  isDirector,
  isEducator,
  isParent,
} from '../rbac-client';
import type {
  Permission,
  PermissionAction,
  PermissionCheckResponse,
  Role,
  RoleType,
  UserPermissionsResponse,
  UserRole,
} from '../types';

// ============================================================================
// Types
// ============================================================================

/**
 * State for RBAC data loading and caching.
 */
interface RBACState {
  /** User's assigned roles */
  roles: Role[];
  /** User's aggregated permissions */
  permissions: Permission[];
  /** User's role assignments with group/org info */
  userRoles: UserRole[];
  /** Loading state for initial data fetch */
  isLoading: boolean;
  /** Error from data fetch */
  error: Error | null;
  /** Timestamp of last data refresh */
  lastRefreshed: Date | null;
}

/**
 * Options for permission checking.
 */
interface PermissionCheckOptions {
  /** Organization ID context for permission check */
  organizationId?: string;
  /** Group ID context for permission check */
  groupId?: string;
}

/**
 * Result of a permission check with detailed information.
 */
interface PermissionCheckResult {
  /** Whether the permission is granted */
  allowed: boolean;
  /** Role that granted the permission (if allowed) */
  matchedRole?: string;
  /** Reason for denial (if not allowed) */
  reason?: string;
  /** Whether the check is in progress */
  isChecking: boolean;
}

/**
 * Return type for the useRBAC hook.
 */
export interface UseRBACReturn {
  // ---- State ----
  /** User's assigned roles */
  roles: Role[];
  /** User's aggregated permissions */
  permissions: Permission[];
  /** User's role assignments */
  userRoles: UserRole[];
  /** Whether RBAC data is loading */
  isLoading: boolean;
  /** Error from loading RBAC data */
  error: Error | null;

  // ---- Permission Checking ----
  /**
   * Check if user can perform an action on a resource.
   *
   * @param resource - Resource identifier (e.g., 'children', 'invoices')
   * @param action - Action to check (default: 'read')
   * @param options - Additional options for group/org context
   * @returns Promise resolving to true if allowed
   *
   * @example
   * ```typescript
   * const canViewChildren = await checkAccess('children', 'read');
   * ```
   */
  checkAccess: (
    resource: string,
    action?: PermissionAction | string,
    options?: PermissionCheckOptions
  ) => Promise<boolean>;

  /**
   * Check permission with full response details.
   *
   * @param resource - Resource identifier
   * @param action - Action to check
   * @param options - Additional options
   * @returns Promise resolving to detailed permission check response
   */
  checkPermissionDetails: (
    resource: string,
    action: PermissionAction | string,
    options?: PermissionCheckOptions
  ) => Promise<PermissionCheckResponse>;

  /**
   * Check if user has a specific permission locally (from cached data).
   * This is a synchronous check using cached permissions.
   *
   * @param resource - Resource identifier
   * @param action - Action to check
   * @returns True if permission exists in cached data
   */
  hasPermission: (resource: string, action: PermissionAction | string) => boolean;

  // ---- Role Checking ----
  /**
   * Check if user has any of the specified roles.
   *
   * @param roleTypes - Array of role types to check
   * @returns Promise resolving to true if user has any role
   */
  hasRole: (roleTypes: RoleType[]) => Promise<boolean>;

  /**
   * Check if user has a specific role (synchronous, from cached data).
   *
   * @param roleType - Role type to check
   * @returns True if user has the role in cached data
   */
  hasRoleCached: (roleType: RoleType) => boolean;

  /**
   * Check if user is a director (full admin access).
   */
  checkIsDirector: () => Promise<boolean>;

  /**
   * Check if user is an educator (teacher or assistant).
   */
  checkIsEducator: () => Promise<boolean>;

  /**
   * Check if user is a parent.
   */
  checkIsParent: () => Promise<boolean>;

  // ---- Cached Role Checks (synchronous) ----
  /** Whether user is a director (from cached data) */
  isDirectorCached: boolean;
  /** Whether user is an educator (from cached data) */
  isEducatorCached: boolean;
  /** Whether user is a parent (from cached data) */
  isParentCached: boolean;

  // ---- Utilities ----
  /**
   * Refresh RBAC data from the server.
   */
  refresh: () => Promise<void>;

  /**
   * Get group IDs the user has access to.
   * Directors return undefined (all access), others return specific group IDs.
   */
  getAccessibleGroupIds: () => string[] | undefined;
}

// ============================================================================
// Cache Configuration
// ============================================================================

/**
 * Cache duration for RBAC data in milliseconds (5 minutes).
 */
const CACHE_DURATION_MS = 5 * 60 * 1000;

/**
 * In-memory cache for permission checks.
 */
const permissionCache = new Map<string, { result: boolean; timestamp: number }>();

/**
 * Generate a cache key for permission checks.
 */
function getPermissionCacheKey(
  userId: string,
  resource: string,
  action: string,
  options?: PermissionCheckOptions
): string {
  return `${userId}:${resource}:${action}:${options?.organizationId || ''}:${options?.groupId || ''}`;
}

/**
 * Check if a cached permission is still valid.
 */
function isCacheValid(timestamp: number): boolean {
  return Date.now() - timestamp < CACHE_DURATION_MS;
}

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * React hook for RBAC permission checking in components.
 *
 * Provides methods to check user permissions, roles, and access rights
 * with automatic caching and loading state management.
 *
 * @param userId - ID of the user to check permissions for
 * @returns Object containing RBAC state and checking methods
 *
 * @example
 * ```typescript
 * function ChildrenList({ userId }: { userId: string }) {
 *   const { isLoading, checkAccess, isDirectorCached } = useRBAC(userId);
 *
 *   useEffect(() => {
 *     async function checkPermissions() {
 *       const canView = await checkAccess('children', 'read');
 *       const canEdit = await checkAccess('children', 'write');
 *       // Handle permissions...
 *     }
 *     checkPermissions();
 *   }, [checkAccess]);
 *
 *   if (isLoading) return <Loading />;
 *
 *   return (
 *     <div>
 *       {isDirectorCached && <AdminControls />}
 *       <ChildList />
 *     </div>
 *   );
 * }
 * ```
 */
export function useRBAC(userId: string): UseRBACReturn {
  // ---- State ----
  const [state, setState] = useState<RBACState>({
    roles: [],
    permissions: [],
    userRoles: [],
    isLoading: true,
    error: null,
    lastRefreshed: null,
  });

  // ---- Data Fetching ----
  const fetchRBACData = useCallback(async () => {
    if (!userId) {
      setState((prev) => ({
        ...prev,
        isLoading: false,
        error: new Error('User ID is required'),
      }));
      return;
    }

    setState((prev) => ({ ...prev, isLoading: true, error: null }));

    try {
      const [permissionsResponse, rolesResponse] = await Promise.all([
        getUserPermissions(userId),
        getUserRoles(userId),
      ]);

      setState({
        roles: permissionsResponse.roles,
        permissions: permissionsResponse.permissions,
        userRoles: rolesResponse.items,
        isLoading: false,
        error: null,
        lastRefreshed: new Date(),
      });
    } catch (err) {
      setState((prev) => ({
        ...prev,
        isLoading: false,
        error: err instanceof Error ? err : new Error('Failed to load RBAC data'),
      }));
    }
  }, [userId]);

  // Initial data fetch
  useEffect(() => {
    fetchRBACData();
  }, [fetchRBACData]);

  // ---- Cached Role Checks ----
  const isDirectorCached = useMemo(() => {
    return state.roles.some((role) => role.name === 'director');
  }, [state.roles]);

  const isEducatorCached = useMemo(() => {
    return state.roles.some((role) => role.name === 'teacher' || role.name === 'assistant');
  }, [state.roles]);

  const isParentCached = useMemo(() => {
    return state.roles.some((role) => role.name === 'parent');
  }, [state.roles]);

  // ---- Permission Checking Methods ----
  const checkAccess = useCallback(
    async (
      resource: string,
      action: PermissionAction | string = 'read',
      options?: PermissionCheckOptions
    ): Promise<boolean> => {
      if (!userId) return false;

      // Check cache first
      const cacheKey = getPermissionCacheKey(userId, resource, action, options);
      const cached = permissionCache.get(cacheKey);
      if (cached && isCacheValid(cached.timestamp)) {
        return cached.result;
      }

      try {
        const result = await canAccess(userId, resource, action);

        // Cache the result
        permissionCache.set(cacheKey, {
          result,
          timestamp: Date.now(),
        });

        return result;
      } catch {
        return false;
      }
    },
    [userId]
  );

  const checkPermissionDetails = useCallback(
    async (
      resource: string,
      action: PermissionAction | string,
      options?: PermissionCheckOptions
    ): Promise<PermissionCheckResponse> => {
      return checkPermission({
        userId,
        resource,
        action,
        organizationId: options?.organizationId,
        groupId: options?.groupId,
      });
    },
    [userId]
  );

  const hasPermission = useCallback(
    (resource: string, action: PermissionAction | string): boolean => {
      return state.permissions.some(
        (perm) =>
          perm.resource === resource &&
          (perm.action === action || perm.action === 'manage') &&
          perm.isActive
      );
    },
    [state.permissions]
  );

  // ---- Role Checking Methods ----
  const hasRole = useCallback(
    async (roleTypes: RoleType[]): Promise<boolean> => {
      if (!userId) return false;
      return hasAnyRole(userId, roleTypes);
    },
    [userId]
  );

  const hasRoleCached = useCallback(
    (roleType: RoleType): boolean => {
      return state.roles.some((role) => role.name === roleType);
    },
    [state.roles]
  );

  const checkIsDirector = useCallback(async (): Promise<boolean> => {
    if (!userId) return false;
    return isDirector(userId);
  }, [userId]);

  const checkIsEducator = useCallback(async (): Promise<boolean> => {
    if (!userId) return false;
    return isEducator(userId);
  }, [userId]);

  const checkIsParent = useCallback(async (): Promise<boolean> => {
    if (!userId) return false;
    return isParent(userId);
  }, [userId]);

  // ---- Utilities ----
  const refresh = useCallback(async (): Promise<void> => {
    // Clear permission cache
    permissionCache.clear();
    await fetchRBACData();
  }, [fetchRBACData]);

  const getAccessibleGroupIds = useCallback((): string[] | undefined => {
    // Directors have access to all groups (undefined means all)
    if (isDirectorCached) {
      return undefined;
    }

    // Get unique group IDs from user role assignments
    const groupIds = state.userRoles
      .filter((ur) => ur.isActive && ur.groupId)
      .map((ur) => ur.groupId as string);

    return [...new Set(groupIds)];
  }, [isDirectorCached, state.userRoles]);

  // ---- Return Value ----
  return {
    // State
    roles: state.roles,
    permissions: state.permissions,
    userRoles: state.userRoles,
    isLoading: state.isLoading,
    error: state.error,

    // Permission checking
    checkAccess,
    checkPermissionDetails,
    hasPermission,

    // Role checking
    hasRole,
    hasRoleCached,
    checkIsDirector,
    checkIsEducator,
    checkIsParent,

    // Cached role checks
    isDirectorCached,
    isEducatorCached,
    isParentCached,

    // Utilities
    refresh,
    getAccessibleGroupIds,
  };
}

// ============================================================================
// Utility Hooks
// ============================================================================

/**
 * Hook for checking a single permission with loading state.
 *
 * @param userId - User ID to check permission for
 * @param resource - Resource to check
 * @param action - Action to check
 * @param options - Additional options
 * @returns Permission check result with loading state
 *
 * @example
 * ```typescript
 * const { allowed, isChecking } = usePermissionCheck(userId, 'invoices', 'write');
 *
 * if (isChecking) return <Spinner />;
 * if (!allowed) return <AccessDenied />;
 * return <InvoiceEditor />;
 * ```
 */
export function usePermissionCheck(
  userId: string,
  resource: string,
  action: PermissionAction | string = 'read',
  options?: PermissionCheckOptions
): PermissionCheckResult {
  const [result, setResult] = useState<PermissionCheckResult>({
    allowed: false,
    isChecking: true,
  });

  useEffect(() => {
    let cancelled = false;

    async function check() {
      if (!userId) {
        setResult({ allowed: false, isChecking: false, reason: 'No user ID provided' });
        return;
      }

      try {
        const response = await checkPermission({
          userId,
          resource,
          action,
          organizationId: options?.organizationId,
          groupId: options?.groupId,
        });

        if (!cancelled) {
          setResult({
            allowed: response.allowed,
            matchedRole: response.matchedRole,
            reason: response.reason,
            isChecking: false,
          });
        }
      } catch (error) {
        if (!cancelled) {
          setResult({
            allowed: false,
            reason: error instanceof Error ? error.message : 'Permission check failed',
            isChecking: false,
          });
        }
      }
    }

    check();

    return () => {
      cancelled = true;
    };
  }, [userId, resource, action, options?.organizationId, options?.groupId]);

  return result;
}

/**
 * Hook for checking role membership with loading state.
 *
 * @param userId - User ID to check
 * @param roleTypes - Role types to check for
 * @returns Object with hasRole boolean and loading state
 *
 * @example
 * ```typescript
 * const { hasRole, isChecking } = useRoleCheck(userId, ['director', 'teacher']);
 *
 * if (isChecking) return <Spinner />;
 * if (!hasRole) return <AccessDenied message="Educators only" />;
 * return <EducatorDashboard />;
 * ```
 */
export function useRoleCheck(
  userId: string,
  roleTypes: RoleType[]
): { hasRole: boolean; isChecking: boolean } {
  const [state, setState] = useState({ hasRole: false, isChecking: true });

  useEffect(() => {
    let cancelled = false;

    async function check() {
      if (!userId || roleTypes.length === 0) {
        setState({ hasRole: false, isChecking: false });
        return;
      }

      try {
        const result = await hasAnyRole(userId, roleTypes);
        if (!cancelled) {
          setState({ hasRole: result, isChecking: false });
        }
      } catch {
        if (!cancelled) {
          setState({ hasRole: false, isChecking: false });
        }
      }
    }

    check();

    return () => {
      cancelled = true;
    };
  }, [userId, roleTypes]);

  return state;
}

// ============================================================================
// Export Default
// ============================================================================

export default useRBAC;
