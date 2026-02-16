/**
 * IncidentCard component tests
 * Tests the incident card display with severity indicators, category icons,
 * status badges, and action buttons.
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { IncidentCard } from '@/components/IncidentCard'
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

describe('IncidentCard Component', () => {
  describe('Basic Rendering', () => {
    it('renders incident category label correctly', () => {
      const incident = createMockIncident({ category: 'bump' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Bump Incident')).toBeInTheDocument()
    })

    it('renders child name correctly', () => {
      const incident = createMockIncident({ childName: 'Emma Smith' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
    })

    it('renders description correctly', () => {
      const incident = createMockIncident({
        description: 'Minor bump on the playground while running.',
      })
      render(<IncidentCard incident={incident} />)
      expect(
        screen.getByText('Minor bump on the playground while running.')
      ).toBeInTheDocument()
    })

    it('includes category icon svg element', () => {
      const incident = createMockIncident()
      const { container } = render(<IncidentCard incident={incident} />)
      const svg = container.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Severity Levels', () => {
    it('renders minor severity correctly', () => {
      const incident = createMockIncident({ severity: 'minor' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Minor')).toBeInTheDocument()
    })

    it('renders moderate severity correctly', () => {
      const incident = createMockIncident({ severity: 'moderate' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Moderate')).toBeInTheDocument()
    })

    it('renders serious severity correctly', () => {
      const incident = createMockIncident({ severity: 'serious' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Serious')).toBeInTheDocument()
    })

    it('renders severe severity correctly', () => {
      const incident = createMockIncident({ severity: 'severe' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Severe')).toBeInTheDocument()
    })

    it('applies correct CSS class for minor severity badge', () => {
      const incident = createMockIncident({ severity: 'minor' })
      const { container } = render(<IncidentCard incident={incident} />)
      const badge = container.querySelector('.badge-info')
      expect(badge).toBeInTheDocument()
      expect(badge).toHaveTextContent('Minor')
    })

    it('applies correct CSS class for moderate severity badge', () => {
      const incident = createMockIncident({ severity: 'moderate' })
      const { container } = render(<IncidentCard incident={incident} />)
      const badge = container.querySelector('.badge-warning')
      expect(badge).toBeInTheDocument()
    })

    it('applies correct CSS class for serious severity badge', () => {
      const incident = createMockIncident({ severity: 'serious' })
      const { container } = render(<IncidentCard incident={incident} />)
      const badge = container.querySelector('.badge-error')
      expect(badge).toBeInTheDocument()
    })

    it('applies severity indicator bar', () => {
      const incident = createMockIncident({ severity: 'minor' })
      const { container } = render(<IncidentCard incident={incident} />)
      const indicator = container.querySelector('.bg-blue-500')
      expect(indicator).toBeInTheDocument()
    })
  })

  describe('Category Types', () => {
    it('renders bump category correctly', () => {
      const incident = createMockIncident({ category: 'bump' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Bump Incident')).toBeInTheDocument()
    })

    it('renders fall category correctly', () => {
      const incident = createMockIncident({ category: 'fall' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Fall Incident')).toBeInTheDocument()
    })

    it('renders bite category correctly', () => {
      const incident = createMockIncident({ category: 'bite' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Bite Incident')).toBeInTheDocument()
    })

    it('renders scratch category correctly', () => {
      const incident = createMockIncident({ category: 'scratch' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Scratch Incident')).toBeInTheDocument()
    })

    it('renders behavioral category correctly', () => {
      const incident = createMockIncident({ category: 'behavioral' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Behavioral Incident')).toBeInTheDocument()
    })

    it('renders medical category correctly', () => {
      const incident = createMockIncident({ category: 'medical' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Medical Incident')).toBeInTheDocument()
    })

    it('renders allergic_reaction category correctly', () => {
      const incident = createMockIncident({ category: 'allergic_reaction' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Allergic Reaction Incident')).toBeInTheDocument()
    })

    it('renders other category correctly', () => {
      const incident = createMockIncident({ category: 'other' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Other Incident')).toBeInTheDocument()
    })
  })

  describe('Status Display', () => {
    it('renders pending status correctly', () => {
      const incident = createMockIncident({ status: 'pending' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Pending Review')).toBeInTheDocument()
    })

    it('renders acknowledged status correctly', () => {
      const incident = createMockIncident({ status: 'acknowledged' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Acknowledged')).toBeInTheDocument()
    })

    it('renders resolved status correctly', () => {
      const incident = createMockIncident({ status: 'resolved' })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Resolved')).toBeInTheDocument()
    })
  })

  describe('Follow-up Indicator', () => {
    it('shows follow-up required indicator when true', () => {
      const incident = createMockIncident({ requiresFollowUp: true })
      render(<IncidentCard incident={incident} />)
      expect(screen.getByText('Follow-up required')).toBeInTheDocument()
    })

    it('does not show follow-up indicator when false', () => {
      const incident = createMockIncident({ requiresFollowUp: false })
      render(<IncidentCard incident={incident} />)
      expect(screen.queryByText('Follow-up required')).not.toBeInTheDocument()
    })
  })

  describe('Action Buttons', () => {
    it('shows acknowledge button for pending incidents when callback provided', () => {
      const incident = createMockIncident({ status: 'pending' })
      const onAcknowledge = vi.fn()
      render(<IncidentCard incident={incident} onAcknowledge={onAcknowledge} />)
      expect(screen.getByText('Acknowledge')).toBeInTheDocument()
    })

    it('does not show acknowledge button for acknowledged incidents', () => {
      const incident = createMockIncident({ status: 'acknowledged' })
      const onAcknowledge = vi.fn()
      render(<IncidentCard incident={incident} onAcknowledge={onAcknowledge} />)
      expect(screen.queryByRole('button', { name: /acknowledge/i })).not.toBeInTheDocument()
    })

    it('does not show acknowledge button for resolved incidents', () => {
      const incident = createMockIncident({ status: 'resolved' })
      const onAcknowledge = vi.fn()
      render(<IncidentCard incident={incident} onAcknowledge={onAcknowledge} />)
      expect(screen.queryByRole('button', { name: /acknowledge/i })).not.toBeInTheDocument()
    })

    it('does not show acknowledge button when callback not provided', () => {
      const incident = createMockIncident({ status: 'pending' })
      render(<IncidentCard incident={incident} />)
      expect(screen.queryByRole('button', { name: /acknowledge/i })).not.toBeInTheDocument()
    })

    it('shows view details button when callback provided', () => {
      const incident = createMockIncident()
      const onViewDetails = vi.fn()
      render(<IncidentCard incident={incident} onViewDetails={onViewDetails} />)
      expect(screen.getByText('View Details')).toBeInTheDocument()
    })

    it('does not show view details button when callback not provided', () => {
      const incident = createMockIncident()
      render(<IncidentCard incident={incident} />)
      expect(screen.queryByText('View Details')).not.toBeInTheDocument()
    })

    it('calls onAcknowledge with incident id when acknowledge button clicked', () => {
      const incident = createMockIncident({ id: 'inc-123', status: 'pending' })
      const onAcknowledge = vi.fn()
      render(<IncidentCard incident={incident} onAcknowledge={onAcknowledge} />)

      fireEvent.click(screen.getByText('Acknowledge'))
      expect(onAcknowledge).toHaveBeenCalledWith('inc-123')
    })

    it('calls onViewDetails with incident id when view details button clicked', () => {
      const incident = createMockIncident({ id: 'inc-456' })
      const onViewDetails = vi.fn()
      render(<IncidentCard incident={incident} onViewDetails={onViewDetails} />)

      fireEvent.click(screen.getByText('View Details'))
      expect(onViewDetails).toHaveBeenCalledWith('inc-456')
    })
  })

  describe('Acknowledged Indicator', () => {
    it('shows acknowledged indicator for acknowledged status', () => {
      const incident = createMockIncident({ status: 'acknowledged' })
      render(<IncidentCard incident={incident} />)
      // Check for the green checkmark indicator in the actions area
      const { container } = render(<IncidentCard incident={incident} />)
      const greenText = container.querySelector('.text-green-600')
      expect(greenText).toBeInTheDocument()
    })
  })

  describe('Card Structure', () => {
    it('renders card container with proper class', () => {
      const incident = createMockIncident()
      const { container } = render(<IncidentCard incident={incident} />)
      expect(container.querySelector('.card')).toBeInTheDocument()
    })

    it('renders card body with proper class', () => {
      const incident = createMockIncident()
      const { container } = render(<IncidentCard incident={incident} />)
      expect(container.querySelector('.card-body')).toBeInTheDocument()
    })

    it('has proper overflow handling for severity indicator', () => {
      const incident = createMockIncident()
      const { container } = render(<IncidentCard incident={incident} />)
      const card = container.querySelector('.card')
      expect(card).toHaveClass('overflow-hidden')
    })
  })
})
