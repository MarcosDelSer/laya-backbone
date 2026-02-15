/**
 * Sample component tests to verify Vitest setup works
 * This file demonstrates that React + jsdom + testing-library are properly configured
 */

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { PaymentStatusBadge } from '@/components/PaymentStatusBadge'

describe('Vitest Setup Verification', () => {
  it('should run basic assertions', () => {
    expect(true).toBe(true)
    expect(1 + 1).toBe(2)
    expect('vitest').toContain('vi')
  })

  it('should have access to jest-dom matchers', () => {
    const div = document.createElement('div')
    div.textContent = 'Hello'
    expect(div).toHaveTextContent('Hello')
  })
})

describe('PaymentStatusBadge Component', () => {
  it('renders paid status correctly', () => {
    render(<PaymentStatusBadge status="paid" />)
    expect(screen.getByText('Paid')).toBeInTheDocument()
  })

  it('renders pending status correctly', () => {
    render(<PaymentStatusBadge status="pending" />)
    expect(screen.getByText('Pending')).toBeInTheDocument()
  })

  it('renders overdue status correctly', () => {
    render(<PaymentStatusBadge status="overdue" />)
    expect(screen.getByText('Overdue')).toBeInTheDocument()
  })

  it('applies correct CSS class for paid status', () => {
    const { container } = render(<PaymentStatusBadge status="paid" />)
    const badge = container.querySelector('span')
    expect(badge).toHaveClass('badge-success')
  })

  it('applies correct CSS class for pending status', () => {
    const { container } = render(<PaymentStatusBadge status="pending" />)
    const badge = container.querySelector('span')
    expect(badge).toHaveClass('badge-warning')
  })

  it('applies correct CSS class for overdue status', () => {
    const { container } = render(<PaymentStatusBadge status="overdue" />)
    const badge = container.querySelector('span')
    expect(badge).toHaveClass('badge-error')
  })

  it('defaults to md size', () => {
    const { container } = render(<PaymentStatusBadge status="paid" />)
    const badge = container.querySelector('span')
    expect(badge).toHaveClass('text-sm')
  })

  it('applies sm size when specified', () => {
    const { container } = render(<PaymentStatusBadge status="paid" size="sm" />)
    const badge = container.querySelector('span')
    expect(badge).toHaveClass('text-xs')
  })

  it('includes an icon svg element', () => {
    const { container } = render(<PaymentStatusBadge status="paid" />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })
})
