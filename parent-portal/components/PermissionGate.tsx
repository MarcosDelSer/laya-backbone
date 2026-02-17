'use client';

import { usePermissionCheck, useRoleCheck } from '../lib/hooks/useRBAC';
import type { PermissionAction, RoleType } from '../lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Base props shared by all PermissionGate variants.
 */
interface BaseGateProps {
  /** User ID to check permissions for */
  userId: string;
  /** Content to render when permission is granted */
  children: React.ReactNode;
  /** Content to render while checking permissions */
  loadingFallback?: React.ReactNode;
  /** Content to render when permission is denied */
  deniedFallback?: React.ReactNode;
  /** If true, renders nothing when denied instead of deniedFallback */
  hideWhenDenied?: boolean;
}

/**
 * Props for permission-based gating.
 */
interface PermissionGateProps extends BaseGateProps {
  /** Resource to check permission for (e.g., 'children', 'invoices') */
  resource: string;
  /** Action to check (default: 'read') */
  action?: PermissionAction | string;
  /** Organization ID context for permission check */
  organizationId?: string;
  /** Group ID context for permission check */
  groupId?: string;
}

/**
 * Props for role-based gating.
 */
interface RoleGateProps extends BaseGateProps {
  /** Role types that are allowed access */
  allowedRoles: RoleType[];
}

/**
 * Combined props for the main PermissionGate component.
 * Either permission-based (resource) or role-based (allowedRoles).
 */
type PermissionGatePropsUnion =
  | (PermissionGateProps & { allowedRoles?: never })
  | (RoleGateProps & { resource?: never; action?: never; organizationId?: never; groupId?: never });

// ============================================================================
// Helper Components
// ============================================================================

/**
 * Default loading spinner component.
 */
function DefaultLoadingSpinner(): React.ReactElement {
  return (
    <div className="flex items-center justify-center p-4">
      <div className="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600" />
    </div>
  );
}

/**
 * Default access denied component.
 */
function DefaultAccessDenied(): React.ReactElement {
  return (
    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
      <div className="flex items-center">
        <svg
          className="mr-3 h-5 w-5 text-red-500"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
          />
        </svg>
        <span className="text-sm font-medium text-red-800">
          You don't have permission to view this content.
        </span>
      </div>
    </div>
  );
}

// ============================================================================
// Permission-Based Gate Component
// ============================================================================

/**
 * Conditionally renders children based on permission check.
 *
 * @internal Use the main PermissionGate component instead.
 */
function PermissionBasedGate({
  userId,
  resource,
  action = 'read',
  organizationId,
  groupId,
  children,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: PermissionGateProps): React.ReactElement | null {
  const { allowed, isChecking } = usePermissionCheck(userId, resource, action, {
    organizationId,
    groupId,
  });

  if (isChecking) {
    return <>{loadingFallback ?? <DefaultLoadingSpinner />}</>;
  }

  if (!allowed) {
    if (hideWhenDenied) {
      return null;
    }
    return <>{deniedFallback ?? <DefaultAccessDenied />}</>;
  }

  return <>{children}</>;
}

// ============================================================================
// Role-Based Gate Component
// ============================================================================

/**
 * Conditionally renders children based on role membership.
 *
 * @internal Use the main PermissionGate component instead.
 */
function RoleBasedGate({
  userId,
  allowedRoles,
  children,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: RoleGateProps): React.ReactElement | null {
  const { hasRole, isChecking } = useRoleCheck(userId, allowedRoles);

  if (isChecking) {
    return <>{loadingFallback ?? <DefaultLoadingSpinner />}</>;
  }

  if (!hasRole) {
    if (hideWhenDenied) {
      return null;
    }
    return <>{deniedFallback ?? <DefaultAccessDenied />}</>;
  }

  return <>{children}</>;
}

// ============================================================================
// Main Component
// ============================================================================

/**
 * Conditionally renders content based on user permissions or roles.
 *
 * This component provides a declarative way to control access to UI elements
 * based on the current user's RBAC permissions. It supports both permission-based
 * and role-based access control.
 *
 * @example Permission-based access
 * ```tsx
 * // Only show invoice editor if user can write to invoices
 * <PermissionGate userId={userId} resource="invoices" action="write">
 *   <InvoiceEditor />
 * </PermissionGate>
 * ```
 *
 * @example Role-based access
 * ```tsx
 * // Only show admin panel for directors and teachers
 * <PermissionGate userId={userId} allowedRoles={['director', 'teacher']}>
 *   <AdminPanel />
 * </PermissionGate>
 * ```
 *
 * @example Custom loading and denied states
 * ```tsx
 * <PermissionGate
 *   userId={userId}
 *   resource="children"
 *   action="read"
 *   loadingFallback={<Skeleton />}
 *   deniedFallback={<PleaseContactAdmin />}
 * >
 *   <ChildrenList />
 * </PermissionGate>
 * ```
 *
 * @example Hidden when denied (no fallback shown)
 * ```tsx
 * <PermissionGate
 *   userId={userId}
 *   resource="reports"
 *   action="delete"
 *   hideWhenDenied
 * >
 *   <DeleteButton />
 * </PermissionGate>
 * ```
 */
export function PermissionGate(props: PermissionGatePropsUnion): React.ReactElement | null {
  // Role-based gating
  if ('allowedRoles' in props && props.allowedRoles) {
    return (
      <RoleBasedGate
        userId={props.userId}
        allowedRoles={props.allowedRoles}
        loadingFallback={props.loadingFallback}
        deniedFallback={props.deniedFallback}
        hideWhenDenied={props.hideWhenDenied}
      >
        {props.children}
      </RoleBasedGate>
    );
  }

  // Permission-based gating (default)
  if ('resource' in props && props.resource) {
    return (
      <PermissionBasedGate
        userId={props.userId}
        resource={props.resource}
        action={props.action}
        organizationId={props.organizationId}
        groupId={props.groupId}
        loadingFallback={props.loadingFallback}
        deniedFallback={props.deniedFallback}
        hideWhenDenied={props.hideWhenDenied}
      >
        {props.children}
      </PermissionBasedGate>
    );
  }

  // Invalid usage - neither allowedRoles nor resource provided
  return null;
}

// ============================================================================
// Specialized Gate Components
// ============================================================================

/**
 * Props for DirectorOnly component.
 */
interface DirectorOnlyProps {
  userId: string;
  children: React.ReactNode;
  loadingFallback?: React.ReactNode;
  deniedFallback?: React.ReactNode;
  hideWhenDenied?: boolean;
}

/**
 * Only renders children for users with the director role.
 *
 * @example
 * ```tsx
 * <DirectorOnly userId={userId}>
 *   <AdminSettings />
 * </DirectorOnly>
 * ```
 */
export function DirectorOnly({
  userId,
  children,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = true,
}: DirectorOnlyProps): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      allowedRoles={['director']}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

/**
 * Props for EducatorOnly component.
 */
interface EducatorOnlyProps {
  userId: string;
  children: React.ReactNode;
  loadingFallback?: React.ReactNode;
  deniedFallback?: React.ReactNode;
  hideWhenDenied?: boolean;
}

/**
 * Only renders children for users with teacher or assistant roles.
 *
 * @example
 * ```tsx
 * <EducatorOnly userId={userId}>
 *   <ClassroomManagement />
 * </EducatorOnly>
 * ```
 */
export function EducatorOnly({
  userId,
  children,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = true,
}: EducatorOnlyProps): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      allowedRoles={['director', 'teacher', 'assistant']}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

/**
 * Props for StaffOnly component.
 */
interface StaffOnlyProps {
  userId: string;
  children: React.ReactNode;
  loadingFallback?: React.ReactNode;
  deniedFallback?: React.ReactNode;
  hideWhenDenied?: boolean;
}

/**
 * Only renders children for non-parent staff users.
 *
 * @example
 * ```tsx
 * <StaffOnly userId={userId}>
 *   <StaffDashboard />
 * </StaffOnly>
 * ```
 */
export function StaffOnly({
  userId,
  children,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = true,
}: StaffOnlyProps): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      allowedRoles={['director', 'teacher', 'assistant', 'staff']}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

// ============================================================================
// Resource-Specific Gate Components
// ============================================================================

/**
 * Props for resource-specific gate components.
 */
interface ResourceGateProps {
  userId: string;
  children: React.ReactNode;
  action?: PermissionAction | string;
  groupId?: string;
  organizationId?: string;
  loadingFallback?: React.ReactNode;
  deniedFallback?: React.ReactNode;
  hideWhenDenied?: boolean;
}

/**
 * Gate for child-related content access.
 *
 * @example
 * ```tsx
 * <CanAccessChildren userId={userId} action="write">
 *   <EditChildProfile />
 * </CanAccessChildren>
 * ```
 */
export function CanAccessChildren({
  userId,
  children,
  action = 'read',
  groupId,
  organizationId,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: ResourceGateProps): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      resource="children"
      action={action}
      groupId={groupId}
      organizationId={organizationId}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

/**
 * Gate for invoice/financial content access.
 *
 * @example
 * ```tsx
 * <CanAccessInvoices userId={userId}>
 *   <InvoiceList />
 * </CanAccessInvoices>
 * ```
 */
export function CanAccessInvoices({
  userId,
  children,
  action = 'read',
  organizationId,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: Omit<ResourceGateProps, 'groupId'>): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      resource="invoices"
      action={action}
      organizationId={organizationId}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

/**
 * Gate for daily report content access.
 *
 * @example
 * ```tsx
 * <CanAccessReports userId={userId} groupId={classroomId}>
 *   <DailyReportView />
 * </CanAccessReports>
 * ```
 */
export function CanAccessReports({
  userId,
  children,
  action = 'read',
  groupId,
  organizationId,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: ResourceGateProps): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      resource="reports"
      action={action}
      groupId={groupId}
      organizationId={organizationId}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

/**
 * Gate for document/signing content access.
 *
 * @example
 * ```tsx
 * <CanAccessDocuments userId={userId} action="write">
 *   <DocumentSigning />
 * </CanAccessDocuments>
 * ```
 */
export function CanAccessDocuments({
  userId,
  children,
  action = 'read',
  organizationId,
  loadingFallback,
  deniedFallback,
  hideWhenDenied = false,
}: Omit<ResourceGateProps, 'groupId'>): React.ReactElement | null {
  return (
    <PermissionGate
      userId={userId}
      resource="documents"
      action={action}
      organizationId={organizationId}
      loadingFallback={loadingFallback}
      deniedFallback={deniedFallback}
      hideWhenDenied={hideWhenDenied}
    >
      {children}
    </PermissionGate>
  );
}

// ============================================================================
// Export Default
// ============================================================================

export default PermissionGate;
