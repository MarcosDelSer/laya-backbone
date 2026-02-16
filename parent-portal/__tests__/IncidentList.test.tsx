/**
 * IncidentList component tests
 * Tests the incident list display with filtering, sorting, and summary badges.
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent, within } from '@testing-library/react'
import { IncidentList } from '@/components/IncidentList'
import { IncidentListItem } from '@/lib/types'

/**
 * Create a mock incident for testing.
 */
function createMockIncident(overrides: Partial<IncidentListItem> = {}): IncidentListItem {
  return {
    id: 'inc-001',
    childId: 'child-001',
    childName: 'Emma Smith',
    date: '2026-02-15',
    time: '10:30:00',
    severity: 'minor',
    category: 'bump',
    status: 'pending',
    description: 'Minor bump on the playground while running.',
    requiresFollowUp: false,
    createdAt: '2026-02-15T10:30:00Z',
    ...overrides,
  }
}

/**
 * Create multiple mock incidents for list testing.
 */
function createMockIncidents(): IncidentListItem[] {
  return [
    createMockIncident({
      id: 'inc-001',
      date: '2026-02-15',
      time: '10:30:00',
      severity: 'minor',
      status: 'pending',
      category: 'bump',
      childName: 'Emma Smith',
    }),
    createMockIncident({
      id: 'inc-002',
      date: '2026-02-14',
      time: '14:00:00',
      severity: 'moderate',
      status: 'acknowledged',
      category: 'fall',
      childName: 'James Wilson',
    }),
    createMockIncident({
      id: 'inc-003',
      date: '2026-02-13',
      time: '09:15:00',
      severity: 'serious',
      status: 'resolved',
      category: 'bite',
      childName: 'Sophie Brown',
    }),
  ]
}

describe('IncidentList Component', () => {
  describe('Basic Rendering', () => {
    it('renders list of incidents', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
      expect(screen.getByText('James Wilson')).toBeInTheDocument()
      expect(screen.getByText('Sophie Brown')).toBeInTheDocument()
    })

    it('renders incident cards for each incident', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      expect(screen.getByText('Bump Incident')).toBeInTheDocument()
      expect(screen.getByText('Fall Incident')).toBeInTheDocument()
      expect(screen.getByText('Bite Incident')).toBeInTheDocument()
    })

    it('renders correct number of incident cards', () => {
      const incidents = createMockIncidents()
      const { container } = render(<IncidentList incidents={incidents} />)
      const cards = container.querySelectorAll('.card')
      expect(cards.length).toBe(3)
    })
  })

  describe('Empty State', () => {
    it('shows empty state when no incidents', () => {
      render(<IncidentList incidents={[]} />)
      expect(screen.getByText('No incidents')).toBeInTheDocument()
    })

    it('shows default empty message', () => {
      render(<IncidentList incidents={[]} />)
      expect(
        screen.getByText('No incidents found matching your filters.')
      ).toBeInTheDocument()
    })

    it('shows custom empty message', () => {
      render(
        <IncidentList
          incidents={[]}
          emptyMessage="No incidents recorded for your children."
        />
      )
      expect(
        screen.getByText('No incidents recorded for your children.')
      ).toBeInTheDocument()
    })

    it('shows empty state icon', () => {
      const { container } = render(<IncidentList incidents={[]} />)
      const svg = container.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Summary Badges', () => {
    it('shows pending badge with correct count', () => {
      const incidents = [
        createMockIncident({ id: 'inc-1', status: 'pending' }),
        createMockIncident({ id: 'inc-2', status: 'pending' }),
      ]
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('2 Pending')).toBeInTheDocument()
    })

    it('shows acknowledged badge with correct count', () => {
      const incidents = [
        createMockIncident({ id: 'inc-1', status: 'acknowledged' }),
        createMockIncident({ id: 'inc-2', status: 'acknowledged' }),
        createMockIncident({ id: 'inc-3', status: 'acknowledged' }),
      ]
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('3 Acknowledged')).toBeInTheDocument()
    })

    it('shows resolved badge with correct count', () => {
      const incidents = [createMockIncident({ id: 'inc-1', status: 'resolved' })]
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('1 Resolved')).toBeInTheDocument()
    })

    it('shows multiple status badges', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      expect(screen.getByText('1 Pending')).toBeInTheDocument()
      expect(screen.getByText('1 Acknowledged')).toBeInTheDocument()
      expect(screen.getByText('1 Resolved')).toBeInTheDocument()
    })

    it('does not show badge for zero count', () => {
      const incidents = [createMockIncident({ status: 'pending' })]
      render(<IncidentList incidents={incidents} />)

      expect(screen.queryByText(/Acknowledged/)).not.toBeInTheDocument()
      expect(screen.queryByText(/Resolved/)).not.toBeInTheDocument()
    })
  })

  describe('Filter Bar', () => {
    it('shows filter bar by default', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('Filter Incidents')).toBeInTheDocument()
    })

    it('hides filter bar when showFilters is false', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} showFilters={false} />)
      expect(screen.queryByText('Filter Incidents')).not.toBeInTheDocument()
    })

    it('shows clear filters button', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('Clear Filters')).toBeInTheDocument()
    })

    it('shows date from input', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByLabelText('From Date')).toBeInTheDocument()
    })

    it('shows date to input', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByLabelText('To Date')).toBeInTheDocument()
    })

    it('shows severity filter dropdown', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByLabelText('Severity')).toBeInTheDocument()
    })

    it('shows status filter dropdown', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByLabelText('Status')).toBeInTheDocument()
    })

    it('shows all severity options in dropdown', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      const severitySelect = screen.getByLabelText('Severity')

      expect(within(severitySelect).getByText('All Severities')).toBeInTheDocument()
      expect(within(severitySelect).getByText('Minor')).toBeInTheDocument()
      expect(within(severitySelect).getByText('Moderate')).toBeInTheDocument()
      expect(within(severitySelect).getByText('Serious')).toBeInTheDocument()
      expect(within(severitySelect).getByText('Severe')).toBeInTheDocument()
    })

    it('shows all status options in dropdown', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)
      const statusSelect = screen.getByLabelText('Status')

      expect(within(statusSelect).getByText('All Status')).toBeInTheDocument()
      expect(within(statusSelect).getByText('Pending')).toBeInTheDocument()
      expect(within(statusSelect).getByText('Acknowledged')).toBeInTheDocument()
      expect(within(statusSelect).getByText('Resolved')).toBeInTheDocument()
    })
  })

  describe('Filtering Functionality', () => {
    it('filters by severity', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const severitySelect = screen.getByLabelText('Severity')
      fireEvent.change(severitySelect, { target: { value: 'minor' } })

      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
      expect(screen.queryByText('James Wilson')).not.toBeInTheDocument()
      expect(screen.queryByText('Sophie Brown')).not.toBeInTheDocument()
    })

    it('filters by status', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const statusSelect = screen.getByLabelText('Status')
      fireEvent.change(statusSelect, { target: { value: 'acknowledged' } })

      expect(screen.queryByText('Emma Smith')).not.toBeInTheDocument()
      expect(screen.getByText('James Wilson')).toBeInTheDocument()
      expect(screen.queryByText('Sophie Brown')).not.toBeInTheDocument()
    })

    it('filters by date from', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const dateFromInput = screen.getByLabelText('From Date')
      fireEvent.change(dateFromInput, { target: { value: '2026-02-15' } })

      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
      expect(screen.queryByText('James Wilson')).not.toBeInTheDocument()
      expect(screen.queryByText('Sophie Brown')).not.toBeInTheDocument()
    })

    it('filters by date to', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const dateToInput = screen.getByLabelText('To Date')
      fireEvent.change(dateToInput, { target: { value: '2026-02-13' } })

      expect(screen.queryByText('Emma Smith')).not.toBeInTheDocument()
      expect(screen.queryByText('James Wilson')).not.toBeInTheDocument()
      expect(screen.getByText('Sophie Brown')).toBeInTheDocument()
    })

    it('clears filters when clear button clicked', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      // Apply a filter first
      const severitySelect = screen.getByLabelText('Severity')
      fireEvent.change(severitySelect, { target: { value: 'minor' } })

      // Only one incident should be visible
      expect(screen.queryByText('James Wilson')).not.toBeInTheDocument()

      // Clear filters
      fireEvent.click(screen.getByText('Clear Filters'))

      // All incidents should be visible again
      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
      expect(screen.getByText('James Wilson')).toBeInTheDocument()
      expect(screen.getByText('Sophie Brown')).toBeInTheDocument()
    })

    it('shows empty state when filters match no incidents', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const severitySelect = screen.getByLabelText('Severity')
      fireEvent.change(severitySelect, { target: { value: 'severe' } })

      expect(screen.getByText('No incidents')).toBeInTheDocument()
    })
  })

  describe('Results Count', () => {
    it('shows results count when filters visible', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} showFilters={true} />)
      expect(screen.getByText('Showing 3 of 3 incidents')).toBeInTheDocument()
    })

    it('does not show results count when filters hidden', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} showFilters={false} />)
      expect(screen.queryByText(/Showing/)).not.toBeInTheDocument()
    })

    it('updates results count when filtering', () => {
      const incidents = createMockIncidents()
      render(<IncidentList incidents={incidents} />)

      const severitySelect = screen.getByLabelText('Severity')
      fireEvent.change(severitySelect, { target: { value: 'minor' } })

      expect(screen.getByText('Showing 1 of 3 incidents')).toBeInTheDocument()
    })

    it('shows singular form for one incident', () => {
      const incidents = [createMockIncident()]
      render(<IncidentList incidents={incidents} />)
      expect(screen.getByText('Showing 1 of 1 incident')).toBeInTheDocument()
    })
  })

  describe('Sorting', () => {
    it('sorts incidents by date (most recent first)', () => {
      const incidents = [
        createMockIncident({
          id: 'inc-old',
          date: '2026-02-01',
          time: '10:00:00',
          childName: 'Old Incident',
        }),
        createMockIncident({
          id: 'inc-new',
          date: '2026-02-15',
          time: '10:00:00',
          childName: 'New Incident',
        }),
        createMockIncident({
          id: 'inc-mid',
          date: '2026-02-10',
          time: '10:00:00',
          childName: 'Mid Incident',
        }),
      ]

      const { container } = render(<IncidentList incidents={incidents} />)
      const cards = container.querySelectorAll('.card')

      // First card should be the most recent
      expect(within(cards[0] as HTMLElement).getByText('New Incident')).toBeInTheDocument()
      // Middle card should be the middle date
      expect(within(cards[1] as HTMLElement).getByText('Mid Incident')).toBeInTheDocument()
      // Last card should be the oldest
      expect(within(cards[2] as HTMLElement).getByText('Old Incident')).toBeInTheDocument()
    })

    it('sorts by time when dates are equal', () => {
      const incidents = [
        createMockIncident({
          id: 'inc-morning',
          date: '2026-02-15',
          time: '08:00:00',
          childName: 'Morning Incident',
        }),
        createMockIncident({
          id: 'inc-afternoon',
          date: '2026-02-15',
          time: '14:00:00',
          childName: 'Afternoon Incident',
        }),
      ]

      const { container } = render(<IncidentList incidents={incidents} />)
      const cards = container.querySelectorAll('.card')

      // Afternoon (later time) should be first
      expect(within(cards[0] as HTMLElement).getByText('Afternoon Incident')).toBeInTheDocument()
      expect(within(cards[1] as HTMLElement).getByText('Morning Incident')).toBeInTheDocument()
    })
  })

  describe('Callback Functions', () => {
    it('passes onAcknowledge to incident cards', () => {
      const incidents = [createMockIncident({ id: 'inc-test', status: 'pending' })]
      const onAcknowledge = vi.fn()

      render(<IncidentList incidents={incidents} onAcknowledge={onAcknowledge} />)
      fireEvent.click(screen.getByText('Acknowledge'))

      expect(onAcknowledge).toHaveBeenCalledWith('inc-test')
    })

    it('passes onViewDetails to incident cards', () => {
      const incidents = [createMockIncident({ id: 'inc-test' })]
      const onViewDetails = vi.fn()

      render(<IncidentList incidents={incidents} onViewDetails={onViewDetails} />)
      fireEvent.click(screen.getByText('View Details'))

      expect(onViewDetails).toHaveBeenCalledWith('inc-test')
    })
  })

  describe('Combined Filters', () => {
    it('applies multiple filters simultaneously', () => {
      const incidents = [
        createMockIncident({
          id: 'inc-1',
          date: '2026-02-15',
          severity: 'minor',
          status: 'pending',
          childName: 'Match All',
        }),
        createMockIncident({
          id: 'inc-2',
          date: '2026-02-15',
          severity: 'moderate',
          status: 'pending',
          childName: 'Wrong Severity',
        }),
        createMockIncident({
          id: 'inc-3',
          date: '2026-02-14',
          severity: 'minor',
          status: 'pending',
          childName: 'Wrong Date',
        }),
        createMockIncident({
          id: 'inc-4',
          date: '2026-02-15',
          severity: 'minor',
          status: 'acknowledged',
          childName: 'Wrong Status',
        }),
      ]

      render(<IncidentList incidents={incidents} />)

      // Apply multiple filters
      fireEvent.change(screen.getByLabelText('From Date'), { target: { value: '2026-02-15' } })
      fireEvent.change(screen.getByLabelText('Severity'), { target: { value: 'minor' } })
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'pending' } })

      // Only the incident matching all filters should be visible
      expect(screen.getByText('Match All')).toBeInTheDocument()
      expect(screen.queryByText('Wrong Severity')).not.toBeInTheDocument()
      expect(screen.queryByText('Wrong Date')).not.toBeInTheDocument()
      expect(screen.queryByText('Wrong Status')).not.toBeInTheDocument()
    })
  })
})
