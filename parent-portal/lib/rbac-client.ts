/**
 * RBAC API client for LAYA Parent Portal.
 *
 * Provides type-safe methods for Role-Based Access Control operations
 * including role management, permission checking, and audit trail access.
 */

import { aiServiceClient, ApiError } from './api';
import type {
  AssignRoleRequest,
  AuditLog,
  AuditLogFilter,
  PaginatedResponse,
  PaginationParams,
  Permission,
  PermissionCheckRequest,
  PermissionCheckResponse,
  RevokeRoleRequest,
  Role,
  RoleType,
  UserPermissionsResponse,
  UserRole,
} from './types';

// ============================================================================
// Response Types
// ============================================================================

/**
 * Paginated response for roles.
 */
export type RoleListResponse = PaginatedResponse<Role>;

/**
 * Paginated response for user roles.
 */
export type UserRoleListResponse = PaginatedResponse<UserRole>;

/**
 * Paginated response for permissions.
 */
export type PermissionListResponse = PaginatedResponse<Permission>;

/**
 * Paginated response for audit logs.
 */
export type AuditLogListResponse = PaginatedResponse<AuditLog>;

// ============================================================================
// Role Management
// ============================================================================

/**
 * Get all roles in the system.
 *
 * @param params - Pagination parameters
 * @returns Promise resolving to paginated list of roles
 * @throws ApiError if the request fails
 *
 * @example
 * ```typescript
 * const roles = await getRoles({ limit: 10 });
 * console.log(roles.items); // Array of Role objects
 * ```
 */
export async function getRoles(params?: PaginationParams): Promise<RoleListResponse> {
  return aiServiceClient.get<RoleListResponse>('/api/v1/rbac/roles', { params });
}

/**
 * Get a specific role by name.
 *
 * @param roleName - Name of the role to retrieve (e.g., 'director', 'teacher')
 * @returns Promise resolving to the role details
 * @throws ApiError if the role is not found or request fails
 *
 * @example
 * ```typescript
 * const teacherRole = await getRoleByName('teacher');
 * console.log(teacherRole.displayName); // "Teacher"
 * ```
 */
export async function getRoleByName(roleName: RoleType | string): Promise<Role> {
  return aiServiceClient.get<Role>(`/api/v1/rbac/roles/${encodeURIComponent(roleName)}`);
}

/**
 * Get all roles assigned to a specific user.
 *
 * @param userId - ID of the user to get roles for
 * @param params - Pagination parameters
 * @returns Promise resolving to paginated list of user role assignments
 * @throws ApiError if the request fails
 *
 * @example
 * ```typescript
 * const userRoles = await getUserRoles('user-123');
 * console.log(userRoles.items[0].role?.name); // "teacher"
 * ```
 */
export async function getUserRoles(
  userId: string,
  params?: PaginationParams
): Promise<UserRoleListResponse> {
  return aiServiceClient.get<UserRoleListResponse>(`/api/v1/rbac/users/${encodeURIComponent(userId)}/roles`, {
    params,
  });
}

/**
 * Assign a role to a user.
 *
 * Requires Director role to perform this action.
 *
 * @param request - Role assignment details
 * @returns Promise resolving to the created user role assignment
 * @throws ApiError if assignment fails or user lacks permission
 *
 * @example
 * ```typescript
 * const assignment = await assignRole({
 *   userId: 'user-123',
 *   roleId: 'role-456',
 *   groupId: 'group-789', // Optional: scope to specific group
 * });
 * ```
 */
export async function assignRole(request: AssignRoleRequest): Promise<UserRole> {
  return aiServiceClient.post<UserRole>('/api/v1/rbac/roles/assign', request);
}

/**
 * Revoke a role from a user.
 *
 * Requires Director role to perform this action.
 *
 * @param request - Role revocation details
 * @returns Promise resolving when the role is revoked
 * @throws ApiError if revocation fails or user lacks permission
 *
 * @example
 * ```typescript
 * await revokeRole({
 *   userId: 'user-123',
 *   roleId: 'role-456',
 * });
 * ```
 */
export async function revokeRole(request: RevokeRoleRequest): Promise<void> {
  return aiServiceClient.post<void>('/api/v1/rbac/roles/revoke', request);
}

// ============================================================================
// Permission Management
// ============================================================================

/**
 * Get all permissions in the system.
 *
 * @param params - Query parameters including pagination and optional filters
 * @returns Promise resolving to paginated list of permissions
 * @throws ApiError if the request fails
 *
 * @example
 * ```typescript
 * const permissions = await getPermissions({ roleId: 'role-123' });
 * ```
 */
export async function getPermissions(
  params?: PaginationParams & { roleId?: string; includeInactive?: boolean }
): Promise<PermissionListResponse> {
  return aiServiceClient.get<PermissionListResponse>('/api/v1/rbac/permissions', { params });
}

/**
 * Check if a user has a specific permission.
 *
 * @param request - Permission check details
 * @returns Promise resolving to the permission check result
 * @throws ApiError if the request fails
 *
 * @example
 * ```typescript
 * const result = await checkPermission({
 *   userId: 'user-123',
 *   resource: 'children',
 *   action: 'read',
 * });
 * if (result.allowed) {
 *   console.log('Access granted via', result.matchedRole);
 * }
 * ```
 */
export async function checkPermission(
  request: PermissionCheckRequest
): Promise<PermissionCheckResponse> {
  return aiServiceClient.post<PermissionCheckResponse>('/api/v1/rbac/permissions/check', request);
}

/**
 * Get all permissions for a specific user.
 *
 * Returns aggregated permissions from all assigned roles.
 *
 * @param userId - ID of the user to get permissions for
 * @returns Promise resolving to user's roles and permissions
 * @throws ApiError if the request fails
 *
 * @example
 * ```typescript
 * const userPermissions = await getUserPermissions('user-123');
 * console.log(userPermissions.roles); // Array of roles
 * console.log(userPermissions.permissions); // Aggregated permissions
 * ```
 */
export async function getUserPermissions(userId: string): Promise<UserPermissionsResponse> {
  return aiServiceClient.get<UserPermissionsResponse>(
    `/api/v1/rbac/users/${encodeURIComponent(userId)}/permissions`
  );
}

// ============================================================================
// Audit Trail
// ============================================================================

/**
 * Get audit log entries with optional filtering.
 *
 * Requires Director role to access audit logs.
 *
 * @param filter - Filter parameters for audit logs
 * @param params - Pagination parameters
 * @returns Promise resolving to paginated list of audit log entries
 * @throws ApiError if the request fails or user lacks permission
 *
 * @example
 * ```typescript
 * const auditLogs = await getAuditLogs(
 *   { action: 'access_denied', userId: 'user-123' },
 *   { limit: 50 }
 * );
 * ```
 */
export async function getAuditLogs(
  filter?: AuditLogFilter,
  params?: PaginationParams
): Promise<AuditLogListResponse> {
  const queryParams: Record<string, string | number | boolean | undefined> = {
    ...params,
  };

  if (filter?.userId) {
    queryParams.user_id = filter.userId;
  }
  if (filter?.action) {
    queryParams.action = filter.action;
  }
  if (filter?.resourceType) {
    queryParams.resource_type = filter.resourceType;
  }
  if (filter?.startDate) {
    queryParams.start_date = filter.startDate;
  }
  if (filter?.endDate) {
    queryParams.end_date = filter.endDate;
  }

  return aiServiceClient.get<AuditLogListResponse>('/api/v1/rbac/audit', {
    params: queryParams,
  });
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Check if a user has any of the specified roles.
 *
 * @param userId - ID of the user to check
 * @param roleTypes - Array of role types to check for
 * @returns Promise resolving to true if user has any of the roles
 *
 * @example
 * ```typescript
 * const isAdmin = await hasAnyRole('user-123', ['director']);
 * const isEducator = await hasAnyRole('user-123', ['director', 'teacher', 'assistant']);
 * ```
 */
export async function hasAnyRole(userId: string, roleTypes: RoleType[]): Promise<boolean> {
  try {
    const userRoles = await getUserRoles(userId);
    return userRoles.items.some(
      (ur) => ur.isActive && ur.role && roleTypes.includes(ur.role.name as RoleType)
    );
  } catch (error) {
    if (error instanceof ApiError && error.isNotFound) {
      return false;
    }
    throw error;
  }
}

/**
 * Check if a user is a director (has full administrative access).
 *
 * @param userId - ID of the user to check
 * @returns Promise resolving to true if user is a director
 *
 * @example
 * ```typescript
 * if (await isDirector('user-123')) {
 *   // Show admin controls
 * }
 * ```
 */
export async function isDirector(userId: string): Promise<boolean> {
  return hasAnyRole(userId, ['director']);
}

/**
 * Check if a user is an educator (teacher or assistant).
 *
 * @param userId - ID of the user to check
 * @returns Promise resolving to true if user is an educator
 *
 * @example
 * ```typescript
 * if (await isEducator('user-123')) {
 *   // Show educator-specific features
 * }
 * ```
 */
export async function isEducator(userId: string): Promise<boolean> {
  return hasAnyRole(userId, ['teacher', 'assistant']);
}

/**
 * Check if a user is a parent.
 *
 * @param userId - ID of the user to check
 * @returns Promise resolving to true if user is a parent
 *
 * @example
 * ```typescript
 * if (await isParent('user-123')) {
 *   // Show parent-specific features
 * }
 * ```
 */
export async function isParent(userId: string): Promise<boolean> {
  return hasAnyRole(userId, ['parent']);
}

/**
 * Check if a user can access a specific resource.
 *
 * Convenience wrapper around checkPermission.
 *
 * @param userId - ID of the user to check
 * @param resource - Resource to check access for
 * @param action - Action to check (default: 'read')
 * @returns Promise resolving to true if access is allowed
 *
 * @example
 * ```typescript
 * if (await canAccess('user-123', 'children', 'read')) {
 *   // Fetch and display children data
 * }
 * ```
 */
export async function canAccess(
  userId: string,
  resource: string,
  action: string = 'read'
): Promise<boolean> {
  try {
    const result = await checkPermission({ userId, resource, action });
    return result.allowed;
  } catch (error) {
    if (error instanceof ApiError && error.isForbidden) {
      return false;
    }
    throw error;
  }
}
