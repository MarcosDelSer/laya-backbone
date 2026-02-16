/**
 * Director Dashboard component tests
 * Tests for OccupancyCard, GroupOccupancyRow, AlertList, and AlertSummaryBadge components
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { OccupancyCard } from '@/components/OccupancyCard'
import {
  GroupOccupancyRow,
  GroupOccupancyRowCompact,
  GroupOccupancyList,
} from '@/components/GroupOccupancyRow'
import {
  AlertList,
  AlertItemRow,
  AlertItemCompact,
  AlertSummaryBadge,
} from '@/components/AlertList'
import type { GroupOccupancy, AlertItem } from '@/lib/types'

// ============================================================================
// Mock Data
// ============================================================================

const mockOccupancy = {
  facilityName: 'LAYA Childcare Center',
  totalCurrent: 67,
  totalCapacity: 80,
  groups: [
    { id: 'group-1', name: 'Explorers', currentCount: 8, capacity: 10 },
    { id: 'group-2', name: 'Butterflies', currentCount: 15, capacity: 15 },
    { id: 'group-3', name: 'Ladybugs', currentCount: 5, capacity: 20 },
  ],
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockGroup: GroupOccupancy = {
  groupId: 'group-1',
  groupName: 'Les Petits Explorateurs',
  ageGroup: 'poupon',
  currentCount: 8,
  capacity: 10,
  occupancyPercentage: 80,
  status: 'near_capacity',
  staffCount: 3,
  staffRatio: '1:3',
  roomNumber: '101',
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockGroupAtCapacity: GroupOccupancy = {
  groupId: 'group-2',
  groupName: 'Les Papillons',
  ageGroup: 'bambin',
  currentCount: 15,
  capacity: 15,
  occupancyPercentage: 100,
  status: 'at_capacity',
  staffCount: 3,
  staffRatio: '1:5',
  roomNumber: '102',
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockGroupOverCapacity: GroupOccupancy = {
  groupId: 'group-3',
  groupName: 'Les Coccinelles',
  ageGroup: 'prescolaire',
  currentCount: 22,
  capacity: 20,
  occupancyPercentage: 110,
  status: 'over_capacity',
  staffCount: 2,
  staffRatio: '1:11',
  roomNumber: '103',
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockGroupNormal: GroupOccupancy = {
  groupId: 'group-4',
  groupName: 'Les Tournesols',
  ageGroup: 'scolaire',
  currentCount: 6,
  capacity: 15,
  occupancyPercentage: 40,
  status: 'normal',
  staffCount: 1,
  staffRatio: '1:6',
  roomNumber: '104',
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockGroupEmpty: GroupOccupancy = {
  groupId: 'group-5',
  groupName: 'Les Arc-en-Ciel',
  ageGroup: 'mixed',
  currentCount: 0,
  capacity: 10,
  occupancyPercentage: 0,
  status: 'empty',
  lastUpdated: '2026-02-16T10:30:00Z',
}

const mockAlert: AlertItem = {
  alertId: 'alert-1',
  alertType: 'occupancy',
  priority: 'high',
  title: 'Group At Capacity',
  message: 'Les Papillons has reached maximum capacity.',
  groupId: 'group-2',
  groupName: 'Les Papillons',
  createdAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
  isAcknowledged: false,
}

const mockAlertAcknowledged: AlertItem = {
  alertId: 'alert-2',
  alertType: 'staffing',
  priority: 'medium',
  title: 'Staff Ratio Warning',
  message: 'Approaching maximum staff-to-child ratio.',
  groupId: 'group-4',
  groupName: 'Les Tournesols',
  createdAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
  isAcknowledged: true,
}

const mockAlertCritical: AlertItem = {
  alertId: 'alert-3',
  alertType: 'compliance',
  priority: 'critical',
  title: 'Compliance Alert',
  message: 'Immediate attention required for compliance issue.',
  createdAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
  isAcknowledged: false,
}

const mockAlertLow: AlertItem = {
  alertId: 'alert-4',
  alertType: 'attendance',
  priority: 'low',
  title: 'Late Arrival Notice',
  message: 'A child will arrive 30 minutes late today.',
  createdAt: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
  isAcknowledged: false,
}

// ============================================================================
// OccupancyCard Tests
// ============================================================================

describe('OccupancyCard Component', () => {
  it('renders facility name correctly', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('LAYA Childcare Center')).toBeInTheDocument()
  })

  it('renders current occupancy heading', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('Current Occupancy')).toBeInTheDocument()
  })

  it('displays total children count', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('67')).toBeInTheDocument()
  })

  it('displays total capacity', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('of 80')).toBeInTheDocument()
  })

  it('displays occupancy percentage', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('84% Full')).toBeInTheDocument()
  })

  it('renders group breakdown section', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('Occupancy by Group')).toBeInTheDocument()
  })

  it('renders all groups in the list', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('Explorers')).toBeInTheDocument()
    expect(screen.getByText('Butterflies')).toBeInTheDocument()
    expect(screen.getByText('Ladybugs')).toBeInTheDocument()
  })

  it('displays group occupancy counts', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('8/10')).toBeInTheDocument()
    expect(screen.getByText('15/15')).toBeInTheDocument()
    expect(screen.getByText('5/20')).toBeInTheDocument()
  })

  it('shows Live indicator', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText('Live')).toBeInTheDocument()
  })

  it('shows Last updated text', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    expect(screen.getByText(/Last updated:/)).toBeInTheDocument()
  })

  it('displays Nearly Full status for high occupancy', () => {
    const highOccupancy = {
      ...mockOccupancy,
      totalCurrent: 75,
      totalCapacity: 80,
    }
    render(<OccupancyCard occupancy={highOccupancy} />)
    expect(screen.getByText('Nearly Full')).toBeInTheDocument()
  })

  it('displays At Capacity status for full occupancy', () => {
    const fullOccupancy = {
      ...mockOccupancy,
      totalCurrent: 80,
      totalCapacity: 80,
    }
    render(<OccupancyCard occupancy={fullOccupancy} />)
    expect(screen.getByText('At Capacity')).toBeInTheDocument()
  })

  it('displays Available status for low occupancy', () => {
    const lowOccupancy = {
      ...mockOccupancy,
      totalCurrent: 30,
      totalCapacity: 80,
    }
    render(<OccupancyCard occupancy={lowOccupancy} />)
    expect(screen.getByText('Available')).toBeInTheDocument()
  })

  it('handles empty groups array', () => {
    const noGroups = {
      ...mockOccupancy,
      groups: [],
    }
    render(<OccupancyCard occupancy={noGroups} />)
    expect(screen.queryByText('Occupancy by Group')).not.toBeInTheDocument()
  })

  it('handles zero capacity without crashing', () => {
    const zeroCapacity = {
      ...mockOccupancy,
      totalCurrent: 0,
      totalCapacity: 0,
    }
    render(<OccupancyCard occupancy={zeroCapacity} />)
    expect(screen.getByText('0% Full')).toBeInTheDocument()
  })
})

// ============================================================================
// GroupOccupancyRow Tests
// ============================================================================

describe('GroupOccupancyRow Component', () => {
  it('renders group name', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByText('Les Petits Explorateurs')).toBeInTheDocument()
  })

  it('renders age group label for poupon', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByText('Poupon (0-18m)')).toBeInTheDocument()
  })

  it('renders age group label for bambin', () => {
    render(<GroupOccupancyRow group={mockGroupAtCapacity} />)
    expect(screen.getByText('Bambin (18-36m)')).toBeInTheDocument()
  })

  it('renders age group label for prescolaire', () => {
    render(<GroupOccupancyRow group={mockGroupOverCapacity} />)
    expect(screen.getByText(/Pr.*scolaire/)).toBeInTheDocument()
  })

  it('renders age group label for scolaire', () => {
    render(<GroupOccupancyRow group={mockGroupNormal} />)
    expect(screen.getByText('Scolaire (5+y)')).toBeInTheDocument()
  })

  it('renders age group label for mixed', () => {
    render(<GroupOccupancyRow group={mockGroupEmpty} />)
    expect(screen.getByText('Mixed Ages')).toBeInTheDocument()
  })

  it('displays current count and capacity', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByText('8')).toBeInTheDocument()
    expect(screen.getByText('10')).toBeInTheDocument()
  })

  it('displays occupancy percentage', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByText('80%')).toBeInTheDocument()
  })

  it('displays room number when showRoomNumber is true', () => {
    render(<GroupOccupancyRow group={mockGroup} showRoomNumber={true} />)
    expect(screen.getByText('Room 101')).toBeInTheDocument()
  })

  it('hides room number when showRoomNumber is false', () => {
    render(<GroupOccupancyRow group={mockGroup} showRoomNumber={false} />)
    expect(screen.queryByText('Room 101')).not.toBeInTheDocument()
  })

  it('displays staff info when showStaffInfo is true', () => {
    render(<GroupOccupancyRow group={mockGroup} showStaffInfo={true} />)
    // The staff info shows "3 staff (1:3)" in a single span
    expect(screen.getByText(/3 staff \(1:3\)/)).toBeInTheDocument()
  })

  it('hides staff info when showStaffInfo is false', () => {
    render(<GroupOccupancyRow group={mockGroup} showStaffInfo={false} />)
    expect(screen.queryByText(/3 staff/)).not.toBeInTheDocument()
  })

  it('displays Near Capacity status badge', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByText('Near Capacity')).toBeInTheDocument()
  })

  it('displays At Capacity status badge', () => {
    render(<GroupOccupancyRow group={mockGroupAtCapacity} />)
    expect(screen.getByText('At Capacity')).toBeInTheDocument()
  })

  it('displays Over Capacity status badge', () => {
    render(<GroupOccupancyRow group={mockGroupOverCapacity} />)
    expect(screen.getByText('Over Capacity')).toBeInTheDocument()
  })

  it('displays Available status badge for normal', () => {
    render(<GroupOccupancyRow group={mockGroupNormal} />)
    expect(screen.getByText('Available')).toBeInTheDocument()
  })

  it('displays Empty status badge', () => {
    render(<GroupOccupancyRow group={mockGroupEmpty} />)
    expect(screen.getByText('Empty')).toBeInTheDocument()
  })

  it('contains progress bar with role', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    expect(screen.getByRole('progressbar')).toBeInTheDocument()
  })

  it('sets correct aria attributes on progress bar', () => {
    render(<GroupOccupancyRow group={mockGroup} />)
    const progressBar = screen.getByRole('progressbar')
    expect(progressBar).toHaveAttribute('aria-valuenow', '8')
    expect(progressBar).toHaveAttribute('aria-valuemin', '0')
    expect(progressBar).toHaveAttribute('aria-valuemax', '10')
  })
})

// ============================================================================
// GroupOccupancyRowCompact Tests
// ============================================================================

describe('GroupOccupancyRowCompact Component', () => {
  it('renders group name', () => {
    render(<GroupOccupancyRowCompact group={mockGroup} />)
    expect(screen.getByText('Les Petits Explorateurs')).toBeInTheDocument()
  })

  it('displays count/capacity', () => {
    render(<GroupOccupancyRowCompact group={mockGroup} />)
    expect(screen.getByText('8/10')).toBeInTheDocument()
  })
})

// ============================================================================
// GroupOccupancyList Tests
// ============================================================================

describe('GroupOccupancyList Component', () => {
  const mockGroups = [mockGroup, mockGroupAtCapacity, mockGroupNormal]

  it('renders all groups', () => {
    render(<GroupOccupancyList groups={mockGroups} />)
    expect(screen.getByText('Les Petits Explorateurs')).toBeInTheDocument()
    expect(screen.getByText('Les Papillons')).toBeInTheDocument()
    expect(screen.getByText('Les Tournesols')).toBeInTheDocument()
  })

  it('displays empty message when no groups', () => {
    render(<GroupOccupancyList groups={[]} />)
    expect(screen.getByText('No groups available')).toBeInTheDocument()
  })

  it('displays custom empty message', () => {
    render(<GroupOccupancyList groups={[]} emptyMessage="No classrooms" />)
    expect(screen.getByText('No classrooms')).toBeInTheDocument()
  })

  it('passes showStaffInfo to children', () => {
    render(<GroupOccupancyList groups={mockGroups} showStaffInfo={true} />)
    // Multiple groups may display staff info, use getAllByText to find at least one
    const staffElements = screen.getAllByText(/\d+ staff/)
    expect(staffElements.length).toBeGreaterThan(0)
  })

  it('passes showRoomNumber to children', () => {
    render(<GroupOccupancyList groups={mockGroups} showRoomNumber={true} />)
    expect(screen.getByText('Room 101')).toBeInTheDocument()
    expect(screen.getByText('Room 102')).toBeInTheDocument()
  })
})

// ============================================================================
// AlertItemRow Tests
// ============================================================================

describe('AlertItemRow Component', () => {
  it('renders alert title', () => {
    render(<AlertItemRow alert={mockAlert} />)
    expect(screen.getByText('Group At Capacity')).toBeInTheDocument()
  })

  it('renders alert message', () => {
    render(<AlertItemRow alert={mockAlert} />)
    expect(screen.getByText('Les Papillons has reached maximum capacity.')).toBeInTheDocument()
  })

  it('renders group name', () => {
    render(<AlertItemRow alert={mockAlert} />)
    expect(screen.getByText('Group: Les Papillons')).toBeInTheDocument()
  })

  it('displays High priority badge', () => {
    render(<AlertItemRow alert={mockAlert} />)
    expect(screen.getByText('High')).toBeInTheDocument()
  })

  it('displays Critical priority badge', () => {
    render(<AlertItemRow alert={mockAlertCritical} />)
    expect(screen.getByText('Critical')).toBeInTheDocument()
  })

  it('displays Medium priority badge', () => {
    render(<AlertItemRow alert={mockAlertAcknowledged} />)
    expect(screen.getByText('Medium')).toBeInTheDocument()
  })

  it('displays Low priority badge', () => {
    render(<AlertItemRow alert={mockAlertLow} />)
    expect(screen.getByText('Low')).toBeInTheDocument()
  })

  it('shows Acknowledge button for unacknowledged alerts', () => {
    const onAcknowledge = vi.fn()
    render(
      <AlertItemRow
        alert={mockAlert}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={true}
      />
    )
    expect(screen.getByText('Acknowledge')).toBeInTheDocument()
  })

  it('hides Acknowledge button for acknowledged alerts', () => {
    const onAcknowledge = vi.fn()
    render(
      <AlertItemRow
        alert={mockAlertAcknowledged}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={true}
      />
    )
    expect(screen.queryByText('Acknowledge')).not.toBeInTheDocument()
  })

  it('shows Acknowledged badge for acknowledged alerts', () => {
    render(<AlertItemRow alert={mockAlertAcknowledged} />)
    expect(screen.getByText('Acknowledged')).toBeInTheDocument()
  })

  it('calls onAcknowledge when button is clicked', () => {
    const onAcknowledge = vi.fn()
    render(
      <AlertItemRow
        alert={mockAlert}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={true}
      />
    )
    fireEvent.click(screen.getByText('Acknowledge'))
    expect(onAcknowledge).toHaveBeenCalledWith('alert-1')
  })

  it('hides Acknowledge button when showAcknowledgeButton is false', () => {
    const onAcknowledge = vi.fn()
    render(
      <AlertItemRow
        alert={mockAlert}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={false}
      />
    )
    expect(screen.queryByText('Acknowledge')).not.toBeInTheDocument()
  })

  it('displays relative timestamp', () => {
    render(<AlertItemRow alert={mockAlert} />)
    expect(screen.getByText(/\d+m ago/)).toBeInTheDocument()
  })
})

// ============================================================================
// AlertItemCompact Tests
// ============================================================================

describe('AlertItemCompact Component', () => {
  it('renders alert title', () => {
    render(<AlertItemCompact alert={mockAlert} />)
    expect(screen.getByText('Group At Capacity')).toBeInTheDocument()
  })

  it('displays relative timestamp', () => {
    render(<AlertItemCompact alert={mockAlert} />)
    expect(screen.getByText(/\d+m ago/)).toBeInTheDocument()
  })
})

// ============================================================================
// AlertList Tests
// ============================================================================

describe('AlertList Component', () => {
  const mockAlerts = [mockAlert, mockAlertAcknowledged, mockAlertLow]

  it('renders all alerts', () => {
    render(<AlertList alerts={mockAlerts} />)
    expect(screen.getByText('Group At Capacity')).toBeInTheDocument()
    expect(screen.getByText('Staff Ratio Warning')).toBeInTheDocument()
    expect(screen.getByText('Late Arrival Notice')).toBeInTheDocument()
  })

  it('displays empty message when no alerts', () => {
    render(<AlertList alerts={[]} />)
    expect(screen.getByText('No alerts at this time')).toBeInTheDocument()
  })

  it('displays custom empty message', () => {
    render(<AlertList alerts={[]} emptyMessage="All clear!" />)
    expect(screen.getByText('All clear!')).toBeInTheDocument()
  })

  it('limits displayed alerts with maxItems', () => {
    render(<AlertList alerts={mockAlerts} maxItems={2} />)
    expect(screen.getByText('Group At Capacity')).toBeInTheDocument()
    expect(screen.getByText('Staff Ratio Warning')).toBeInTheDocument()
    expect(screen.queryByText('Late Arrival Notice')).not.toBeInTheDocument()
  })

  it('shows more alerts count when maxItems is set', () => {
    render(<AlertList alerts={mockAlerts} maxItems={2} />)
    expect(screen.getByText('+1 more alert')).toBeInTheDocument()
  })

  it('shows plural "alerts" when more than one hidden', () => {
    const manyAlerts = [mockAlert, mockAlertAcknowledged, mockAlertLow, mockAlertCritical]
    render(<AlertList alerts={manyAlerts} maxItems={2} />)
    expect(screen.getByText('+2 more alerts')).toBeInTheDocument()
  })

  it('passes onAcknowledge to children', () => {
    const onAcknowledge = vi.fn()
    render(
      <AlertList
        alerts={[mockAlert]}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={true}
      />
    )
    fireEvent.click(screen.getByText('Acknowledge'))
    expect(onAcknowledge).toHaveBeenCalledWith('alert-1')
  })
})

// ============================================================================
// AlertSummaryBadge Tests
// ============================================================================

describe('AlertSummaryBadge Component', () => {
  it('renders count and priority label for critical', () => {
    render(<AlertSummaryBadge count={3} priority="critical" />)
    expect(screen.getByText('3 Critical')).toBeInTheDocument()
  })

  it('renders count and priority label for high', () => {
    render(<AlertSummaryBadge count={2} priority="high" />)
    expect(screen.getByText('2 High')).toBeInTheDocument()
  })

  it('renders count and priority label for medium', () => {
    render(<AlertSummaryBadge count={5} priority="medium" />)
    expect(screen.getByText('5 Medium')).toBeInTheDocument()
  })

  it('renders count and priority label for low', () => {
    render(<AlertSummaryBadge count={1} priority="low" />)
    expect(screen.getByText('1 Low')).toBeInTheDocument()
  })

  it('returns null when count is zero', () => {
    const { container } = render(<AlertSummaryBadge count={0} priority="high" />)
    expect(container.firstChild).toBeNull()
  })
})

// ============================================================================
// Integration Tests
// ============================================================================

describe('Director Dashboard Integration', () => {
  it('renders full occupancy card with groups', () => {
    render(<OccupancyCard occupancy={mockOccupancy} />)
    // Check header
    expect(screen.getByText('Current Occupancy')).toBeInTheDocument()
    expect(screen.getByText('LAYA Childcare Center')).toBeInTheDocument()
    // Check stats
    expect(screen.getByText('67')).toBeInTheDocument()
    expect(screen.getByText('of 80')).toBeInTheDocument()
    // Check groups
    expect(screen.getByText('Explorers')).toBeInTheDocument()
    expect(screen.getByText('Butterflies')).toBeInTheDocument()
    expect(screen.getByText('Ladybugs')).toBeInTheDocument()
  })

  it('renders alert list with acknowledge functionality', () => {
    const onAcknowledge = vi.fn()
    const alerts = [mockAlert, mockAlertCritical]

    render(
      <AlertList
        alerts={alerts}
        onAcknowledge={onAcknowledge}
        showAcknowledgeButton={true}
      />
    )

    // Both alerts should render
    expect(screen.getByText('Group At Capacity')).toBeInTheDocument()
    expect(screen.getByText('Compliance Alert')).toBeInTheDocument()

    // Click acknowledge on first alert
    const acknowledgeButtons = screen.getAllByText('Acknowledge')
    fireEvent.click(acknowledgeButtons[0])
    expect(onAcknowledge).toHaveBeenCalled()
  })

  it('renders group list with all status types', () => {
    const groups = [
      mockGroupNormal,
      mockGroup,
      mockGroupAtCapacity,
      mockGroupOverCapacity,
      mockGroupEmpty,
    ]

    render(<GroupOccupancyList groups={groups} />)

    expect(screen.getByText('Available')).toBeInTheDocument()
    expect(screen.getByText('Near Capacity')).toBeInTheDocument()
    expect(screen.getByText('At Capacity')).toBeInTheDocument()
    expect(screen.getByText('Over Capacity')).toBeInTheDocument()
    expect(screen.getByText('Empty')).toBeInTheDocument()
  })
})
