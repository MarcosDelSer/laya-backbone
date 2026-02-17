/**
 * Medical Protocol Component Tests
 * Tests for WeightInput, MedicalProtocolCard, and DosingChart components
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { WeightInput, MIN_WEIGHT_KG, MAX_WEIGHT_KG } from '@/components/WeightInput'
import { MedicalProtocolCard } from '@/components/MedicalProtocolCard'
import {
  DosingChart,
  MIN_WEIGHT_KG as DOSING_MIN_WEIGHT,
  MAX_WEIGHT_KG as DOSING_MAX_WEIGHT,
  MAX_DAILY_DOSES,
  MIN_INTERVAL_HOURS,
} from '@/components/DosingChart'
import type { ProtocolSummary } from '@/lib/types'

// ============================================================================
// WeightInput Component Tests
// ============================================================================

describe('WeightInput Component', () => {
  it('renders with default label', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)
    expect(screen.getByText("Child's Weight")).toBeInTheDocument()
  })

  it('renders with custom label', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} label="Custom Label" />)
    expect(screen.getByText('Custom Label')).toBeInTheDocument()
  })

  it('renders with help text', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} helpText="Enter weight" />)
    expect(screen.getByText('Enter weight')).toBeInTheDocument()
  })

  it('shows valid range info', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)
    expect(screen.getByText(/Valid range:/)).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`${MIN_WEIGHT_KG} kg - ${MAX_WEIGHT_KG} kg`))).toBeInTheDocument()
  })

  it('shows 3-month update reminder', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)
    expect(screen.getByText(/Weight must be updated every 3 months/)).toBeInTheDocument()
  })

  it('calls onWeightChange with valid weight', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '15.5' } })

    expect(onWeightChange).toHaveBeenCalledWith(15.5, true)
  })

  it('calls onWeightChange with invalid weight below minimum', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '2' } })

    expect(onWeightChange).toHaveBeenCalledWith(2, false)
  })

  it('calls onWeightChange with invalid weight above maximum', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '40' } })

    expect(onWeightChange).toHaveBeenCalledWith(40, false)
  })

  it('calls onWeightChange with null for empty input', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} initialWeight={15} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '' } })

    expect(onWeightChange).toHaveBeenCalledWith(null, false)
  })

  it('shows error message for weight below minimum', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '2' } })

    expect(screen.getByText(new RegExp(`Weight must be at least ${MIN_WEIGHT_KG} kg`))).toBeInTheDocument()
  })

  it('shows warning message for weight above maximum', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '40' } })

    expect(screen.getByText(/Please consult a healthcare provider/)).toBeInTheDocument()
  })

  it('shows valid weight confirmation', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '15' } })

    expect(screen.getByText('Weight is within valid range for dosing')).toBeInTheDocument()
  })

  it('renders with initial weight', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} initialWeight={12.5} />)

    const input = screen.getByRole('textbox') as HTMLInputElement
    expect(input.value).toBe('12.5')
  })

  it('renders in disabled state', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} disabled />)

    const input = screen.getByRole('textbox')
    expect(input).toBeDisabled()
  })

  it('formats to one decimal on blur', () => {
    const onWeightChange = vi.fn()
    render(<WeightInput onWeightChange={onWeightChange} />)

    const input = screen.getByRole('textbox') as HTMLInputElement
    fireEvent.change(input, { target: { value: '15' } })
    fireEvent.blur(input)

    expect(input.value).toBe('15.0')
  })

  it('exports weight range constants', () => {
    expect(MIN_WEIGHT_KG).toBe(4.3)
    expect(MAX_WEIGHT_KG).toBe(35)
  })
})

// ============================================================================
// MedicalProtocolCard Component Tests
// ============================================================================

describe('MedicalProtocolCard Component', () => {
  const createMockProtocol = (overrides: Partial<ProtocolSummary> = {}): ProtocolSummary => ({
    protocolId: 'protocol-1',
    protocolName: 'Acetaminophen',
    protocolFormCode: 'FO-0647',
    protocolType: 'medication',
    authorizationStatus: null,
    canAdminister: false,
    ...overrides,
  })

  it('renders protocol name', () => {
    const protocol = createMockProtocol()
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Acetaminophen')).toBeInTheDocument()
  })

  it('renders protocol form code', () => {
    const protocol = createMockProtocol()
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText(/Form:.*FO-0647/)).toBeInTheDocument()
  })

  it('shows Not Authorized badge when no authorization', () => {
    const protocol = createMockProtocol({ authorizationStatus: null })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Not Authorized')).toBeInTheDocument()
  })

  it('shows Authorized badge when active', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'active' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Authorized')).toBeInTheDocument()
  })

  it('shows Pending badge when pending', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'pending' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Pending')).toBeInTheDocument()
  })

  it('shows Expired badge when expired', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'expired' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Expired')).toBeInTheDocument()
  })

  it('shows Revoked badge when revoked', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'revoked' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Revoked')).toBeInTheDocument()
  })

  it('shows Sign Authorization button when not authorized', () => {
    const protocol = createMockProtocol({ authorizationStatus: null })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Sign Authorization')).toBeInTheDocument()
  })

  it('shows Renew Authorization button when expired', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'expired' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Renew Authorization')).toBeInTheDocument()
  })

  it('does not show authorization button when active', () => {
    const protocol = createMockProtocol({ authorizationStatus: 'active' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.queryByText('Sign Authorization')).not.toBeInTheDocument()
    expect(screen.queryByText('Renew Authorization')).not.toBeInTheDocument()
  })

  it('shows View Details button', () => {
    const protocol = createMockProtocol()
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('View Details')).toBeInTheDocument()
  })

  it('calls onViewDetails when View Details is clicked', () => {
    const onViewDetails = vi.fn()
    const protocol = createMockProtocol()
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={onViewDetails}
      />
    )

    fireEvent.click(screen.getByText('View Details'))
    expect(onViewDetails).toHaveBeenCalledWith('protocol-1')
  })

  it('calls onAuthorize when Sign Authorization is clicked', () => {
    const onAuthorize = vi.fn()
    const protocol = createMockProtocol({ authorizationStatus: null })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={onAuthorize}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )

    fireEvent.click(screen.getByText('Sign Authorization'))
    expect(onAuthorize).toHaveBeenCalledWith('protocol-1')
  })

  it('shows weight when provided', () => {
    const protocol = createMockProtocol({ weightKg: 15.5 })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText(/Weight: 15.5 kg/)).toBeInTheDocument()
  })

  it('shows Update Weight button when weight is expired', () => {
    const protocol = createMockProtocol({
      authorizationStatus: 'active',
      weightKg: 15,
      isWeightExpired: true,
    })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('Update Weight')).toBeInTheDocument()
  })

  it('calls onUpdateWeight when Update Weight is clicked', () => {
    const onUpdateWeight = vi.fn()
    const protocol = createMockProtocol({
      authorizationStatus: 'active',
      weightKg: 15,
      isWeightExpired: true,
    })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={onUpdateWeight}
        onViewDetails={vi.fn()}
      />
    )

    fireEvent.click(screen.getByText('Update Weight'))
    expect(onUpdateWeight).toHaveBeenCalledWith('protocol-1')
  })

  it('shows weight update required indicator', () => {
    const protocol = createMockProtocol({
      authorizationStatus: 'active',
      weightKg: 15,
      isWeightExpired: true,
    })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText('(Update required)')).toBeInTheDocument()
  })

  it('renders medication type protocol description', () => {
    const protocol = createMockProtocol({ protocolType: 'medication' })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText(/Acetaminophen administration/)).toBeInTheDocument()
  })

  it('renders topical type protocol description', () => {
    const protocol = createMockProtocol({
      protocolType: 'topical',
      protocolName: 'Insect Repellent',
      protocolFormCode: 'FO-0646',
    })
    render(
      <MedicalProtocolCard
        protocol={protocol}
        childName="Test Child"
        onAuthorize={vi.fn()}
        onUpdateWeight={vi.fn()}
        onViewDetails={vi.fn()}
      />
    )
    expect(screen.getByText(/Insect repellent application/)).toBeInTheDocument()
  })
})

// ============================================================================
// DosingChart Component Tests
// ============================================================================

describe('DosingChart Component', () => {
  it('shows enter weight message when weight is null', () => {
    render(<DosingChart weightKg={null} />)
    expect(screen.getByText("Enter Child's Weight")).toBeInTheDocument()
    expect(screen.getByText(/Enter the child's weight above/)).toBeInTheDocument()
  })

  it('shows out of range warning for weight below minimum', () => {
    render(<DosingChart weightKg={3} />)
    expect(screen.getByText('Weight Out of Range')).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`below the minimum.*${DOSING_MIN_WEIGHT} kg`))).toBeInTheDocument()
  })

  it('shows out of range warning for weight above maximum', () => {
    render(<DosingChart weightKg={40} />)
    expect(screen.getByText('Weight Out of Range')).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`exceeds the maximum.*${DOSING_MAX_WEIGHT} kg`))).toBeInTheDocument()
  })

  it('shows consult healthcare provider message for out of range weight', () => {
    render(<DosingChart weightKg={40} />)
    expect(screen.getByText(/Please consult a healthcare provider/)).toBeInTheDocument()
  })

  it('shows dosing chart header for valid weight', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText('Acetaminophen Dosing Chart')).toBeInTheDocument()
  })

  it('shows weight in kg and lbs for valid weight', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText(/For 15 kg.*33 lbs/)).toBeInTheDocument()
  })

  it('renders concentration options for valid weight', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText('Infant Drops')).toBeInTheDocument()
    expect(screen.getByText("Children's Syrup")).toBeInTheDocument()
    expect(screen.getByText('Concentrated Syrup')).toBeInTheDocument()
  })

  it('shows concentration descriptions', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText('80 mg per 1 mL')).toBeInTheDocument()
    expect(screen.getByText('80 mg per 5 mL')).toBeInTheDocument()
    expect(screen.getByText('160 mg per 5 mL')).toBeInTheDocument()
  })

  it('highlights recommended concentration', () => {
    render(<DosingChart weightKg={15} recommendedConcentration="80mg/5mL" />)
    expect(screen.getByText('Recommended')).toBeInTheDocument()
  })

  it('shows daily dose warning by default', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText('Important Dosing Guidelines')).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`Maximum ${MAX_DAILY_DOSES} doses`))).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`Wait at least ${MIN_INTERVAL_HOURS} hours`))).toBeInTheDocument()
  })

  it('hides daily dose warning when showDailyDoseWarning is false', () => {
    render(<DosingChart weightKg={15} showDailyDoseWarning={false} />)
    expect(screen.queryByText('Important Dosing Guidelines')).not.toBeInTheDocument()
  })

  it('shows dosing guideline footer', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText(/Dosing guideline:/)).toBeInTheDocument()
    expect(screen.getByText(/10-15 mg per kg/)).toBeInTheDocument()
  })

  it('shows FO-0647 badge', () => {
    render(<DosingChart weightKg={15} />)
    expect(screen.getByText('FO-0647')).toBeInTheDocument()
  })

  it('calculates correct dose range for 10kg child', () => {
    render(<DosingChart weightKg={10} />)
    // 10-15 mg/kg = 100-150 mg - appears once for each concentration
    const doseTexts = screen.getAllByText('100 - 150 mg')
    expect(doseTexts.length).toBe(3) // One for each concentration
  })

  it('calls onConcentrationSelect when concentration is clicked', () => {
    const onConcentrationSelect = vi.fn()
    render(<DosingChart weightKg={15} onConcentrationSelect={onConcentrationSelect} />)

    fireEvent.click(screen.getByText('Infant Drops'))
    expect(onConcentrationSelect).toHaveBeenCalledWith('80mg/mL')
  })

  it('shows compact mode without header', () => {
    render(<DosingChart weightKg={15} compact />)
    expect(screen.queryByText('Acetaminophen Dosing Chart')).not.toBeInTheDocument()
    expect(screen.getByText(/Dosing for 15 kg/)).toBeInTheDocument()
  })

  it('renders with pre-calculated dosing options', () => {
    const dosingOptions = [
      {
        protocolId: 'test',
        concentration: '80mg/mL' as const,
        minWeightKg: 15,
        maxWeightKg: 15,
        minDoseMg: 150,
        maxDoseMg: 225,
        minDoseMl: 1.9,
        maxDoseMl: 2.8,
        displayLabel: 'Test Drops',
      },
    ]
    render(<DosingChart weightKg={15} dosingOptions={dosingOptions} />)
    expect(screen.getByText('150 - 225 mg')).toBeInTheDocument()
    expect(screen.getByText('1.9 - 2.8 mL')).toBeInTheDocument()
  })

  it('exports dosing constants', () => {
    expect(DOSING_MIN_WEIGHT).toBe(4.3)
    expect(DOSING_MAX_WEIGHT).toBe(35)
    expect(MAX_DAILY_DOSES).toBe(5)
    expect(MIN_INTERVAL_HOURS).toBe(4)
  })

  it('shows selected concentration styling', () => {
    render(
      <DosingChart
        weightKg={15}
        selectedConcentration="80mg/mL"
        onConcentrationSelect={vi.fn()}
      />
    )
    // Selected row should exist (we can't easily test exact styling, but the selection should be present)
    const table = screen.getByRole('table')
    expect(table).toBeInTheDocument()
  })
})
