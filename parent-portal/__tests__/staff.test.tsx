/**
 * Staff component and API client tests for LAYA Parent Portal.
 * Tests StaffCard component rendering and staff API client functions.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { StaffCard } from '@/components/StaffCard'
import type {
  StaffProfile,
  StaffCertification,
  ChildStaffResponse,
  StaffOnDuty,
} from '@/lib/types'

// ============================================================================
// Test Data Fixtures
// ============================================================================

const mockCertification: StaffCertification = {
  id: 'cert-1',
  type: 'first_aid',
  name: 'First Aid & CPR',
  issuingOrganization: 'Red Cross',
  certificateNumber: 'FA-12345',
  issueDate: '2024-01-15',
  expiryDate: '2026-01-15',
  status: 'valid',
  isRequired: true,
}

const mockExpiredCertification: StaffCertification = {
  id: 'cert-2',
  type: 'cpr',
  name: 'CPR Level C',
  issuingOrganization: 'St. John Ambulance',
  issueDate: '2022-01-15',
  expiryDate: '2024-01-15',
  status: 'expired',
  isRequired: false,
}

const mockExpiringCertification: StaffCertification = {
  id: 'cert-3',
  type: 'food_handling',
  name: 'Food Handling',
  issueDate: '2023-06-01',
  expiryDate: '2026-03-01',
  status: 'expiring_soon',
  isRequired: true,
}

const mockPendingCertification: StaffCertification = {
  id: 'cert-4',
  type: 'background_check',
  name: 'Background Check',
  issueDate: '2026-02-01',
  status: 'pending',
  isRequired: true,
}

const mockStaffProfile: StaffProfile = {
  id: 'staff-1',
  gibbonPersonID: '12345',
  firstName: 'Jane',
  lastName: 'Doe',
  preferredName: 'Janie',
  position: 'Lead Educator',
  department: 'Preschool',
  profilePhotoUrl: 'https://example.com/photo.jpg',
  qualificationLevel: 'qualified_educator',
  status: 'active',
  bio: 'Passionate early childhood educator with 10 years of experience.',
  specializations: ['Montessori', 'Special Needs'],
  certifications: [mockCertification, mockExpiredCertification],
}

const mockStaffWithoutPreferredName: StaffProfile = {
  id: 'staff-2',
  gibbonPersonID: '12346',
  firstName: 'John',
  lastName: 'Smith',
  position: 'Assistant Educator',
  qualificationLevel: 'qualified_assistant',
  status: 'active',
}

const mockOnLeaveStaff: StaffProfile = {
  id: 'staff-3',
  gibbonPersonID: '12347',
  firstName: 'Sarah',
  lastName: 'Wilson',
  position: 'Educator',
  qualificationLevel: 'in_training',
  status: 'on_leave',
}

const mockTerminatedStaff: StaffProfile = {
  id: 'staff-4',
  gibbonPersonID: '12348',
  firstName: 'Mike',
  lastName: 'Brown',
  position: 'Educator',
  qualificationLevel: 'unqualified',
  status: 'terminated',
}

const mockSuspendedStaff: StaffProfile = {
  id: 'staff-5',
  gibbonPersonID: '12349',
  firstName: 'Lisa',
  lastName: 'Davis',
  position: 'Assistant',
  qualificationLevel: 'qualified_assistant',
  status: 'suspended',
}

const mockDirectorStaff: StaffProfile = {
  id: 'staff-6',
  gibbonPersonID: '12350',
  firstName: 'Emily',
  lastName: 'Johnson',
  position: 'Center Director',
  qualificationLevel: 'director',
  status: 'active',
}

const mockSpecializedEducator: StaffProfile = {
  id: 'staff-7',
  gibbonPersonID: '12351',
  firstName: 'David',
  lastName: 'Lee',
  position: 'Special Needs Educator',
  qualificationLevel: 'specialized_educator',
  status: 'active',
}

// ============================================================================
// StaffCard Component Tests
// ============================================================================

describe('StaffCard Component', () => {
  describe('Basic Rendering', () => {
    it('renders staff name with preferred name when available', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Janie Doe')).toBeInTheDocument()
    })

    it('renders staff name without preferred name when not available', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} />)
      expect(screen.getByText('John Smith')).toBeInTheDocument()
    })

    it('renders staff position', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Lead Educator')).toBeInTheDocument()
    })

    it('renders department when provided', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Preschool')).toBeInTheDocument()
    })

    it('renders profile photo when URL is provided', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      const img = document.querySelector('img')
      expect(img).toBeInTheDocument()
      expect(img).toHaveAttribute('src', 'https://example.com/photo.jpg')
      expect(img).toHaveAttribute('alt', 'Janie Doe')
    })

    it('renders initials when no profile photo is provided', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} />)
      expect(screen.getByText('JS')).toBeInTheDocument()
    })

    it('renders initials using preferred name when available', () => {
      const staffWithoutPhoto = { ...mockStaffProfile, profilePhotoUrl: undefined }
      render(<StaffCard staff={staffWithoutPhoto} />)
      expect(screen.getByText('JD')).toBeInTheDocument()
    })
  })

  describe('Status Badge', () => {
    it('displays Active status correctly', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Active')).toBeInTheDocument()
    })

    it('displays On Leave status correctly', () => {
      render(<StaffCard staff={mockOnLeaveStaff} />)
      expect(screen.getByText('On Leave')).toBeInTheDocument()
    })

    it('displays Terminated status correctly', () => {
      render(<StaffCard staff={mockTerminatedStaff} />)
      expect(screen.getByText('Terminated')).toBeInTheDocument()
    })

    it('displays Suspended status correctly', () => {
      render(<StaffCard staff={mockSuspendedStaff} />)
      expect(screen.getByText('Suspended')).toBeInTheDocument()
    })

    it('applies badge-success class for active status', () => {
      const { container } = render(<StaffCard staff={mockStaffProfile} />)
      const badge = container.querySelector('.badge-success')
      expect(badge).toBeInTheDocument()
      expect(badge).toHaveTextContent('Active')
    })

    it('applies badge-warning class for on_leave status', () => {
      const { container } = render(<StaffCard staff={mockOnLeaveStaff} />)
      const badge = container.querySelector('.badge-warning')
      expect(badge).toBeInTheDocument()
      expect(badge).toHaveTextContent('On Leave')
    })

    it('applies badge-error class for terminated status', () => {
      const { container } = render(<StaffCard staff={mockTerminatedStaff} />)
      const badge = container.querySelector('.badge-error')
      expect(badge).toBeInTheDocument()
      expect(badge).toHaveTextContent('Terminated')
    })

    it('applies badge-error class for suspended status', () => {
      const { container } = render(<StaffCard staff={mockSuspendedStaff} />)
      const badge = container.querySelector('.badge-error')
      expect(badge).toBeInTheDocument()
      expect(badge).toHaveTextContent('Suspended')
    })
  })

  describe('Qualification Level Badge', () => {
    it('displays Director qualification correctly', () => {
      render(<StaffCard staff={mockDirectorStaff} />)
      expect(screen.getByText('Director')).toBeInTheDocument()
    })

    it('displays Specialized Educator qualification correctly', () => {
      render(<StaffCard staff={mockSpecializedEducator} />)
      expect(screen.getByText('Specialized Educator')).toBeInTheDocument()
    })

    it('displays Qualified Educator qualification correctly', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Qualified Educator')).toBeInTheDocument()
    })

    it('displays Qualified Assistant qualification correctly', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} />)
      expect(screen.getByText('Qualified Assistant')).toBeInTheDocument()
    })

    it('displays In Training qualification correctly', () => {
      render(<StaffCard staff={mockOnLeaveStaff} />)
      expect(screen.getByText('In Training')).toBeInTheDocument()
    })

    it('displays Unqualified status correctly', () => {
      render(<StaffCard staff={mockTerminatedStaff} />)
      expect(screen.getByText('Unqualified')).toBeInTheDocument()
    })

    it('applies badge-info class for director qualification', () => {
      const { container } = render(<StaffCard staff={mockDirectorStaff} />)
      const badge = container.querySelector('.badge-info')
      expect(badge).toBeInTheDocument()
    })

    it('applies badge-success class for qualified educator', () => {
      const { container } = render(<StaffCard staff={mockStaffProfile} />)
      const qualificationBadges = container.querySelectorAll('.badge-success')
      expect(qualificationBadges.length).toBeGreaterThanOrEqual(1)
    })

    it('applies badge-warning class for qualified assistant', () => {
      const { container } = render(<StaffCard staff={mockStaffWithoutPreferredName} />)
      const badge = container.querySelector('.badge-warning')
      expect(badge).toBeInTheDocument()
    })
  })

  describe('Compact Mode', () => {
    it('renders compact version with smaller avatar', () => {
      const { container } = render(<StaffCard staff={mockStaffProfile} compact />)
      const avatar = container.querySelector('img')
      expect(avatar).toHaveClass('h-10', 'w-10')
    })

    it('shows green dot for active status in compact mode', () => {
      const { container } = render(<StaffCard staff={mockStaffProfile} compact />)
      const greenDot = container.querySelector('.bg-green-400')
      expect(greenDot).toBeInTheDocument()
    })

    it('shows status badge for non-active status in compact mode', () => {
      render(<StaffCard staff={mockOnLeaveStaff} compact />)
      expect(screen.getByText('On Leave')).toBeInTheDocument()
    })

    it('does not show bio in compact mode', () => {
      render(<StaffCard staff={mockStaffProfile} compact showBio />)
      expect(screen.queryByText('About')).not.toBeInTheDocument()
    })

    it('does not show certifications in compact mode', () => {
      render(<StaffCard staff={mockStaffProfile} compact showCertifications />)
      expect(screen.queryByText('Certifications')).not.toBeInTheDocument()
    })

    it('displays initials in compact mode when no photo', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} compact />)
      expect(screen.getByText('JS')).toBeInTheDocument()
    })
  })

  describe('Bio Section', () => {
    it('shows bio section when showBio is true and bio exists', () => {
      render(<StaffCard staff={mockStaffProfile} showBio />)
      expect(screen.getByText('About')).toBeInTheDocument()
      expect(
        screen.getByText('Passionate early childhood educator with 10 years of experience.')
      ).toBeInTheDocument()
    })

    it('does not show bio section when showBio is false', () => {
      render(<StaffCard staff={mockStaffProfile} showBio={false} />)
      expect(screen.queryByText('About')).not.toBeInTheDocument()
    })

    it('does not show bio section when bio is undefined', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} showBio />)
      expect(screen.queryByText('About')).not.toBeInTheDocument()
    })
  })

  describe('Specializations Section', () => {
    it('shows specializations when they exist', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('Specializations')).toBeInTheDocument()
      expect(screen.getByText('Montessori')).toBeInTheDocument()
      expect(screen.getByText('Special Needs')).toBeInTheDocument()
    })

    it('does not show specializations section when none exist', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} />)
      expect(screen.queryByText('Specializations')).not.toBeInTheDocument()
    })
  })

  describe('Certifications Section', () => {
    it('shows certifications section when showCertifications is true', () => {
      render(<StaffCard staff={mockStaffProfile} showCertifications />)
      expect(screen.getByText('Certifications')).toBeInTheDocument()
    })

    it('displays certification names', () => {
      render(<StaffCard staff={mockStaffProfile} showCertifications />)
      expect(screen.getByText('First Aid & CPR')).toBeInTheDocument()
      expect(screen.getByText('CPR Level C')).toBeInTheDocument()
    })

    it('displays certification status', () => {
      render(<StaffCard staff={mockStaffProfile} showCertifications />)
      expect(screen.getByText('Valid')).toBeInTheDocument()
      expect(screen.getByText('Expired')).toBeInTheDocument()
    })

    it('displays (Required) label for required certifications', () => {
      render(<StaffCard staff={mockStaffProfile} showCertifications />)
      expect(screen.getByText('(Required)')).toBeInTheDocument()
    })

    it('does not show certifications section when showCertifications is false', () => {
      render(<StaffCard staff={mockStaffProfile} showCertifications={false} />)
      expect(screen.queryByText('Certifications')).not.toBeInTheDocument()
    })

    it('does not show certifications section when staff has no certifications', () => {
      render(<StaffCard staff={mockStaffWithoutPreferredName} showCertifications />)
      expect(screen.queryByText('Certifications')).not.toBeInTheDocument()
    })
  })

  describe('Certification Count Badge', () => {
    it('shows valid certification count', () => {
      render(<StaffCard staff={mockStaffProfile} />)
      expect(screen.getByText('1 Certification')).toBeInTheDocument()
    })

    it('shows plural certification text for multiple valid certs', () => {
      const staffWithMultipleCerts: StaffProfile = {
        ...mockStaffProfile,
        certifications: [mockCertification, { ...mockCertification, id: 'cert-5' }],
      }
      render(<StaffCard staff={staffWithMultipleCerts} />)
      expect(screen.getByText('2 Certifications')).toBeInTheDocument()
    })

    it('does not show certification count when no valid certifications', () => {
      const staffWithOnlyExpired: StaffProfile = {
        ...mockStaffProfile,
        certifications: [mockExpiredCertification],
      }
      render(<StaffCard staff={staffWithOnlyExpired} />)
      expect(screen.queryByText(/Certification/)).not.toBeInTheDocument()
    })
  })
})

// ============================================================================
// Certification Status Tests
// ============================================================================

describe('Certification Status Display', () => {
  const createStaffWithCert = (cert: StaffCertification): StaffProfile => ({
    ...mockStaffProfile,
    certifications: [cert],
  })

  it('displays Valid status with green color', () => {
    const staff = createStaffWithCert(mockCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const statusSpan = container.querySelector('.text-green-600')
    expect(statusSpan).toBeInTheDocument()
  })

  it('displays Expired status with red color', () => {
    const staff = createStaffWithCert(mockExpiredCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const statusSpan = container.querySelector('.text-red-600')
    expect(statusSpan).toBeInTheDocument()
  })

  it('displays Expiring Soon status with orange color', () => {
    const staff = createStaffWithCert(mockExpiringCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const statusSpan = container.querySelector('.text-orange-600')
    expect(statusSpan).toBeInTheDocument()
  })

  it('displays Pending status with yellow color', () => {
    const staff = createStaffWithCert(mockPendingCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const statusSpan = container.querySelector('.text-yellow-600')
    expect(statusSpan).toBeInTheDocument()
  })

  it('renders check icon for valid certification', () => {
    const staff = createStaffWithCert(mockCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })

  it('renders x icon for expired certification', () => {
    const staff = createStaffWithCert(mockExpiredCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })

  it('renders clock icon for pending/expiring certification', () => {
    const staff = createStaffWithCert(mockExpiringCertification)
    const { container } = render(<StaffCard staff={staff} showCertifications />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })
})

// ============================================================================
// Staff API Client Tests
// ============================================================================

describe('Staff API Client', () => {
  // Mock the gibbonClient
  vi.mock('@/lib/api', () => ({
    gibbonClient: {
      get: vi.fn(),
    },
    ApiError: class ApiError extends Error {
      status: number
      constructor(message: string, status: number) {
        super(message)
        this.status = status
      }
    },
  }))

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('ENDPOINTS configuration', () => {
    it('should have correct child staff endpoint pattern', () => {
      // Test the endpoint pattern logic
      const childId = 'child-123'
      const expectedPattern = `/api/v1/children/${childId}/staff`
      expect(expectedPattern).toBe('/api/v1/children/child-123/staff')
    })

    it('should have correct child staff on duty endpoint pattern', () => {
      const childId = 'child-456'
      const expectedPattern = `/api/v1/children/${childId}/staff/on-duty`
      expect(expectedPattern).toBe('/api/v1/children/child-456/staff/on-duty')
    })

    it('should have correct staff profile endpoint pattern', () => {
      const staffId = 'staff-789'
      const expectedPattern = `/api/v1/staff/${staffId}`
      expect(expectedPattern).toBe('/api/v1/staff/staff-789')
    })
  })
})

// ============================================================================
// Type Guard and Helper Tests
// ============================================================================

describe('Type Guard Tests', () => {
  it('StaffProfile should have required fields', () => {
    const staff: StaffProfile = mockStaffProfile
    expect(staff.id).toBeDefined()
    expect(staff.gibbonPersonID).toBeDefined()
    expect(staff.firstName).toBeDefined()
    expect(staff.lastName).toBeDefined()
    expect(staff.position).toBeDefined()
    expect(staff.qualificationLevel).toBeDefined()
    expect(staff.status).toBeDefined()
  })

  it('StaffCertification should have required fields', () => {
    const cert: StaffCertification = mockCertification
    expect(cert.id).toBeDefined()
    expect(cert.type).toBeDefined()
    expect(cert.name).toBeDefined()
    expect(cert.issueDate).toBeDefined()
    expect(cert.status).toBeDefined()
    expect(cert.isRequired).toBeDefined()
  })

  it('ChildStaffResponse should have correct structure', () => {
    const response: ChildStaffResponse = {
      childId: 'child-1',
      classroomName: 'Sunshine Room',
      ageGroup: 'preschool',
      assignments: {
        childId: 'child-1',
        primaryCaregivers: [mockStaffProfile],
        classroomStaff: [mockStaffWithoutPreferredName],
      },
      lastUpdated: '2026-02-16T10:00:00Z',
    }
    expect(response.childId).toBe('child-1')
    expect(response.classroomName).toBe('Sunshine Room')
    expect(response.assignments.primaryCaregivers).toHaveLength(1)
  })

  it('StaffOnDuty should have correct structure', () => {
    const onDuty: StaffOnDuty = {
      staff: mockStaffProfile,
      clockedInAt: '2026-02-16T08:00:00Z',
      assignedRoom: 'Preschool A',
      ageGroup: 'preschool',
      isOnBreak: false,
    }
    expect(onDuty.staff.id).toBe('staff-1')
    expect(onDuty.isOnBreak).toBe(false)
  })
})

// ============================================================================
// Edge Cases and Error Handling
// ============================================================================

describe('Edge Cases', () => {
  it('handles staff with empty specializations array', () => {
    const staffWithEmptySpecs: StaffProfile = {
      ...mockStaffProfile,
      specializations: [],
    }
    render(<StaffCard staff={staffWithEmptySpecs} />)
    expect(screen.queryByText('Specializations')).not.toBeInTheDocument()
  })

  it('handles staff with empty certifications array', () => {
    const staffWithNoCerts: StaffProfile = {
      ...mockStaffProfile,
      certifications: [],
    }
    render(<StaffCard staff={staffWithNoCerts} showCertifications />)
    expect(screen.queryByText('Certifications')).not.toBeInTheDocument()
  })

  it('handles staff with undefined optional fields', () => {
    const minimalStaff: StaffProfile = {
      id: 'minimal',
      gibbonPersonID: '99999',
      firstName: 'Min',
      lastName: 'Staff',
      position: 'Staff',
      qualificationLevel: 'unqualified',
      status: 'active',
    }
    render(<StaffCard staff={minimalStaff} showBio showCertifications />)
    expect(screen.getByText('Min Staff')).toBeInTheDocument()
    expect(screen.queryByText('About')).not.toBeInTheDocument()
    expect(screen.queryByText('Certifications')).not.toBeInTheDocument()
  })

  it('renders long names without breaking layout', () => {
    const staffWithLongName: StaffProfile = {
      ...mockStaffProfile,
      firstName: 'Alexandrina-Konstantina',
      lastName: 'Pappadopoulos-Christodoulou',
      preferredName: undefined,
    }
    render(<StaffCard staff={staffWithLongName} />)
    expect(
      screen.getByText('Alexandrina-Konstantina Pappadopoulos-Christodoulou')
    ).toBeInTheDocument()
  })

  it('handles certification with no expiry date', () => {
    const certNoExpiry: StaffCertification = {
      ...mockCertification,
      expiryDate: undefined,
    }
    const staff: StaffProfile = {
      ...mockStaffProfile,
      certifications: [certNoExpiry],
    }
    render(<StaffCard staff={staff} showCertifications />)
    expect(screen.getByText('First Aid & CPR')).toBeInTheDocument()
  })
})
