/**
 * RBAC component tests for LAYA Parent Portal.
 *
 * Tests the PermissionGate component and its specialized variants
 * for proper permission-based and role-based access control rendering.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import {
  PermissionGate,
  DirectorOnly,
  EducatorOnly,
  StaffOnly,
  CanAccessChildren,
  CanAccessInvoices,
  CanAccessReports,
  CanAccessDocuments,
} from '@/components/PermissionGate'

// Mock the RBAC hooks
vi.mock('@/lib/hooks/useRBAC', () => ({
  usePermissionCheck: vi.fn(),
  useRoleCheck: vi.fn(),
}))

import { usePermissionCheck, useRoleCheck } from '@/lib/hooks/useRBAC'

// Type the mocked functions
const mockUsePermissionCheck = usePermissionCheck as ReturnType<typeof vi.fn>
const mockUseRoleCheck = useRoleCheck as ReturnType<typeof vi.fn>

// Test constants
const TEST_USER_ID = 'user-123'
const TEST_CHILDREN_CONTENT = 'Protected Children Content'
const TEST_DENIED_MESSAGE = "You don't have permission to view this content."

describe('PermissionGate Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Loading State', () => {
    it('shows default loading spinner while checking permissions', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: true,
      })

      const { container } = render(
        <PermissionGate userId={TEST_USER_ID} resource="children" action="read">
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      // Should show loading spinner
      const spinner = container.querySelector('.animate-spin')
      expect(spinner).toBeInTheDocument()
      expect(screen.queryByText(TEST_CHILDREN_CONTENT)).not.toBeInTheDocument()
    })

    it('shows custom loading fallback when provided', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: true,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          resource="children"
          action="read"
          loadingFallback={<div>Custom Loading...</div>}
        >
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      expect(screen.getByText('Custom Loading...')).toBeInTheDocument()
      expect(screen.queryByText(TEST_CHILDREN_CONTENT)).not.toBeInTheDocument()
    })
  })

  describe('Permission-Based Gating', () => {
    it('renders children when permission is granted', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <PermissionGate userId={TEST_USER_ID} resource="children" action="read">
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      expect(screen.getByText(TEST_CHILDREN_CONTENT)).toBeInTheDocument()
    })

    it('renders default access denied when permission is denied', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      render(
        <PermissionGate userId={TEST_USER_ID} resource="children" action="read">
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      expect(screen.getByText(TEST_DENIED_MESSAGE)).toBeInTheDocument()
      expect(screen.queryByText(TEST_CHILDREN_CONTENT)).not.toBeInTheDocument()
    })

    it('renders custom denied fallback when provided', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          resource="children"
          action="read"
          deniedFallback={<div>Custom Access Denied</div>}
        >
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      expect(screen.getByText('Custom Access Denied')).toBeInTheDocument()
      expect(screen.queryByText(TEST_DENIED_MESSAGE)).not.toBeInTheDocument()
    })

    it('renders nothing when hideWhenDenied is true and permission denied', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      const { container } = render(
        <PermissionGate
          userId={TEST_USER_ID}
          resource="children"
          action="read"
          hideWhenDenied
        >
          <div>{TEST_CHILDREN_CONTENT}</div>
        </PermissionGate>
      )

      expect(container).toBeEmptyDOMElement()
    })

    it('passes correct parameters to usePermissionCheck', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          resource="invoices"
          action="write"
          organizationId="org-456"
          groupId="group-789"
        >
          <div>Content</div>
        </PermissionGate>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'invoices',
        'write',
        { organizationId: 'org-456', groupId: 'group-789' }
      )
    })

    it('uses default action of read when not specified', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'children',
        'read',
        { organizationId: undefined, groupId: undefined }
      )
    })
  })

  describe('Role-Based Gating', () => {
    it('renders children when user has allowed role', () => {
      mockUseRoleCheck.mockReturnValue({
        hasRole: true,
        isChecking: false,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          allowedRoles={['director', 'teacher']}
        >
          <div>Admin Content</div>
        </PermissionGate>
      )

      expect(screen.getByText('Admin Content')).toBeInTheDocument()
    })

    it('renders access denied when user lacks required role', () => {
      mockUseRoleCheck.mockReturnValue({
        hasRole: false,
        isChecking: false,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          allowedRoles={['director']}
        >
          <div>Admin Content</div>
        </PermissionGate>
      )

      expect(screen.getByText(TEST_DENIED_MESSAGE)).toBeInTheDocument()
      expect(screen.queryByText('Admin Content')).not.toBeInTheDocument()
    })

    it('passes correct parameters to useRoleCheck', () => {
      mockUseRoleCheck.mockReturnValue({
        hasRole: true,
        isChecking: false,
      })

      render(
        <PermissionGate
          userId={TEST_USER_ID}
          allowedRoles={['director', 'teacher', 'assistant']}
        >
          <div>Content</div>
        </PermissionGate>
      )

      expect(mockUseRoleCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        ['director', 'teacher', 'assistant']
      )
    })

    it('shows loading state while checking roles', () => {
      mockUseRoleCheck.mockReturnValue({
        hasRole: false,
        isChecking: true,
      })

      const { container } = render(
        <PermissionGate
          userId={TEST_USER_ID}
          allowedRoles={['director']}
        >
          <div>Content</div>
        </PermissionGate>
      )

      const spinner = container.querySelector('.animate-spin')
      expect(spinner).toBeInTheDocument()
    })
  })

  describe('Invalid Usage', () => {
    it('renders null when neither resource nor allowedRoles provided', () => {
      const { container } = render(
        // @ts-expect-error - Testing invalid props
        <PermissionGate userId={TEST_USER_ID}>
          <div>Content</div>
        </PermissionGate>
      )

      expect(container).toBeEmptyDOMElement()
    })
  })
})

describe('DirectorOnly Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders children for director role', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: true,
      isChecking: false,
    })

    render(
      <DirectorOnly userId={TEST_USER_ID}>
        <div>Director Settings</div>
      </DirectorOnly>
    )

    expect(screen.getByText('Director Settings')).toBeInTheDocument()
    expect(mockUseRoleCheck).toHaveBeenCalledWith(TEST_USER_ID, ['director'])
  })

  it('hides content for non-directors by default', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: false,
      isChecking: false,
    })

    const { container } = render(
      <DirectorOnly userId={TEST_USER_ID}>
        <div>Director Settings</div>
      </DirectorOnly>
    )

    // hideWhenDenied defaults to true for DirectorOnly
    expect(container).toBeEmptyDOMElement()
  })

  it('shows denied fallback when hideWhenDenied is false', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: false,
      isChecking: false,
    })

    render(
      <DirectorOnly userId={TEST_USER_ID} hideWhenDenied={false}>
        <div>Director Settings</div>
      </DirectorOnly>
    )

    expect(screen.getByText(TEST_DENIED_MESSAGE)).toBeInTheDocument()
  })

  it('shows custom denied fallback when provided', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: false,
      isChecking: false,
    })

    render(
      <DirectorOnly
        userId={TEST_USER_ID}
        hideWhenDenied={false}
        deniedFallback={<div>Directors only!</div>}
      >
        <div>Director Settings</div>
      </DirectorOnly>
    )

    expect(screen.getByText('Directors only!')).toBeInTheDocument()
  })
})

describe('EducatorOnly Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders children for educator roles', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: true,
      isChecking: false,
    })

    render(
      <EducatorOnly userId={TEST_USER_ID}>
        <div>Classroom Management</div>
      </EducatorOnly>
    )

    expect(screen.getByText('Classroom Management')).toBeInTheDocument()
    expect(mockUseRoleCheck).toHaveBeenCalledWith(TEST_USER_ID, ['director', 'teacher', 'assistant'])
  })

  it('hides content for non-educators by default', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: false,
      isChecking: false,
    })

    const { container } = render(
      <EducatorOnly userId={TEST_USER_ID}>
        <div>Classroom Management</div>
      </EducatorOnly>
    )

    expect(container).toBeEmptyDOMElement()
  })
})

describe('StaffOnly Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders children for staff roles', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: true,
      isChecking: false,
    })

    render(
      <StaffOnly userId={TEST_USER_ID}>
        <div>Staff Dashboard</div>
      </StaffOnly>
    )

    expect(screen.getByText('Staff Dashboard')).toBeInTheDocument()
    expect(mockUseRoleCheck).toHaveBeenCalledWith(TEST_USER_ID, ['director', 'teacher', 'assistant', 'staff'])
  })

  it('hides content for non-staff by default', () => {
    mockUseRoleCheck.mockReturnValue({
      hasRole: false,
      isChecking: false,
    })

    const { container } = render(
      <StaffOnly userId={TEST_USER_ID}>
        <div>Staff Dashboard</div>
      </StaffOnly>
    )

    expect(container).toBeEmptyDOMElement()
  })
})

describe('Resource-Specific Gate Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('CanAccessChildren', () => {
    it('renders children when permission is granted', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessChildren userId={TEST_USER_ID}>
          <div>Child Profile</div>
        </CanAccessChildren>
      )

      expect(screen.getByText('Child Profile')).toBeInTheDocument()
      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'children',
        'read',
        { organizationId: undefined, groupId: undefined }
      )
    })

    it('passes action parameter correctly', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessChildren userId={TEST_USER_ID} action="write">
          <div>Edit Child</div>
        </CanAccessChildren>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'children',
        'write',
        { organizationId: undefined, groupId: undefined }
      )
    })

    it('passes groupId parameter correctly', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessChildren userId={TEST_USER_ID} groupId="classroom-123">
          <div>Classroom Children</div>
        </CanAccessChildren>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'children',
        'read',
        { organizationId: undefined, groupId: 'classroom-123' }
      )
    })

    it('shows access denied by default (hideWhenDenied false)', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      render(
        <CanAccessChildren userId={TEST_USER_ID}>
          <div>Child Profile</div>
        </CanAccessChildren>
      )

      expect(screen.getByText(TEST_DENIED_MESSAGE)).toBeInTheDocument()
    })
  })

  describe('CanAccessInvoices', () => {
    it('renders children when permission is granted', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessInvoices userId={TEST_USER_ID}>
          <div>Invoice List</div>
        </CanAccessInvoices>
      )

      expect(screen.getByText('Invoice List')).toBeInTheDocument()
      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'invoices',
        'read',
        { organizationId: undefined, groupId: undefined }
      )
    })

    it('passes organizationId parameter correctly', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessInvoices userId={TEST_USER_ID} organizationId="org-456">
          <div>Organization Invoices</div>
        </CanAccessInvoices>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'invoices',
        'read',
        { organizationId: 'org-456', groupId: undefined }
      )
    })
  })

  describe('CanAccessReports', () => {
    it('renders children when permission is granted', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessReports userId={TEST_USER_ID}>
          <div>Daily Reports</div>
        </CanAccessReports>
      )

      expect(screen.getByText('Daily Reports')).toBeInTheDocument()
      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'reports',
        'read',
        { organizationId: undefined, groupId: undefined }
      )
    })

    it('passes groupId and organizationId parameters', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessReports
          userId={TEST_USER_ID}
          action="write"
          groupId="classroom-123"
          organizationId="org-456"
        >
          <div>Write Reports</div>
        </CanAccessReports>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'reports',
        'write',
        { organizationId: 'org-456', groupId: 'classroom-123' }
      )
    })
  })

  describe('CanAccessDocuments', () => {
    it('renders children when permission is granted', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessDocuments userId={TEST_USER_ID}>
          <div>Document List</div>
        </CanAccessDocuments>
      )

      expect(screen.getByText('Document List')).toBeInTheDocument()
      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'documents',
        'read',
        { organizationId: undefined, groupId: undefined }
      )
    })

    it('supports write action for document signing', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: true,
        isChecking: false,
      })

      render(
        <CanAccessDocuments userId={TEST_USER_ID} action="write">
          <div>Sign Document</div>
        </CanAccessDocuments>
      )

      expect(mockUsePermissionCheck).toHaveBeenCalledWith(
        TEST_USER_ID,
        'documents',
        'write',
        { organizationId: undefined, groupId: undefined }
      )
    })
  })
})

describe('Default UI Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('DefaultLoadingSpinner', () => {
    it('renders spinner element with correct classes', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: true,
      })

      const { container } = render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      const spinner = container.querySelector('.animate-spin')
      expect(spinner).toBeInTheDocument()
      expect(spinner).toHaveClass('rounded-full')
      expect(spinner).toHaveClass('border-2')
    })

    it('is contained in a flex container', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: true,
      })

      const { container } = render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      const flexContainer = container.querySelector('.flex.items-center.justify-center')
      expect(flexContainer).toBeInTheDocument()
    })
  })

  describe('DefaultAccessDenied', () => {
    it('renders access denied message', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      expect(screen.getByText(TEST_DENIED_MESSAGE)).toBeInTheDocument()
    })

    it('renders with error styling', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      const { container } = render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      const errorContainer = container.querySelector('.border-red-200.bg-red-50')
      expect(errorContainer).toBeInTheDocument()
    })

    it('includes an icon', () => {
      mockUsePermissionCheck.mockReturnValue({
        allowed: false,
        isChecking: false,
      })

      const { container } = render(
        <PermissionGate userId={TEST_USER_ID} resource="children">
          <div>Content</div>
        </PermissionGate>
      )

      const icon = container.querySelector('svg')
      expect(icon).toBeInTheDocument()
      expect(icon).toHaveClass('text-red-500')
    })
  })
})
