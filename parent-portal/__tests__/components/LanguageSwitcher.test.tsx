/**
 * Unit tests for LanguageSwitcher component
 * Tests language selection dropdown functionality and accessibility
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { LanguageSwitcher } from '@/components/LanguageSwitcher'

// Mock next-intl
const mockUseLocale = vi.fn()
vi.mock('next-intl', () => ({
  useLocale: () => mockUseLocale(),
}))

// Mock next/navigation
const mockPush = vi.fn()
const mockPathname = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
  usePathname: () => mockPathname(),
}))

describe('LanguageSwitcher Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockUseLocale.mockReturnValue('en')
    mockPathname.mockReturnValue('/en/dashboard')
  })

  describe('Rendering', () => {
    it('renders the language switcher button', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      expect(button).toBeInTheDocument()
    })

    it('displays the current language flag', () => {
      render(<LanguageSwitcher />)
      expect(screen.getByText('🇬🇧')).toBeInTheDocument()
    })

    it('displays French flag when locale is fr', () => {
      mockUseLocale.mockReturnValue('fr')
      render(<LanguageSwitcher />)
      expect(screen.getByText('🇫🇷')).toBeInTheDocument()
    })

    it('displays the current language label on larger screens', () => {
      render(<LanguageSwitcher />)
      expect(screen.getByText('English')).toBeInTheDocument()
    })

    it('displays Français label when locale is fr', () => {
      mockUseLocale.mockReturnValue('fr')
      render(<LanguageSwitcher />)
      expect(screen.getByText('Français')).toBeInTheDocument()
    })

    it('includes dropdown chevron icon', () => {
      const { container } = render(<LanguageSwitcher />)
      const svg = container.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Dropdown Behavior', () => {
    it('dropdown is closed by default', () => {
      render(<LanguageSwitcher />)
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    })

    it('opens dropdown when button is clicked', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('listbox')).toBeInTheDocument()
    })

    it('closes dropdown when button is clicked again', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('listbox')).toBeInTheDocument()
      fireEvent.click(button)
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    })

    it('shows all language options when dropdown is open', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('option', { name: /english/i })).toBeInTheDocument()
      expect(screen.getByRole('option', { name: /français/i })).toBeInTheDocument()
    })

    it('closes dropdown when Escape key is pressed', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('listbox')).toBeInTheDocument()
      fireEvent.keyDown(document, { key: 'Escape' })
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    })

    it('closes dropdown when clicking outside', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('listbox')).toBeInTheDocument()
      fireEvent.mouseDown(document.body)
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    })
  })

  describe('Language Selection', () => {
    it('navigates to new locale when different language is selected', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const frOption = screen.getByRole('option', { name: /français/i })
      fireEvent.click(frOption)
      expect(mockPush).toHaveBeenCalledWith('/fr/dashboard')
    })

    it('does not navigate when same language is selected', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const enOption = screen.getByRole('option', { name: /english/i })
      fireEvent.click(enOption)
      expect(mockPush).not.toHaveBeenCalled()
    })

    it('closes dropdown after selecting a language', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const frOption = screen.getByRole('option', { name: /français/i })
      fireEvent.click(frOption)
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    })

    it('navigates to French locale from English', () => {
      mockUseLocale.mockReturnValue('en')
      mockPathname.mockReturnValue('/en/payments')
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const frOption = screen.getByRole('option', { name: /français/i })
      fireEvent.click(frOption)
      expect(mockPush).toHaveBeenCalledWith('/fr/payments')
    })

    it('navigates to English locale from French', () => {
      mockUseLocale.mockReturnValue('fr')
      mockPathname.mockReturnValue('/fr/dashboard')
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const enOption = screen.getByRole('option', { name: /english/i })
      fireEvent.click(enOption)
      expect(mockPush).toHaveBeenCalledWith('/en/dashboard')
    })

    it('handles root path correctly', () => {
      mockPathname.mockReturnValue('/en')
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const frOption = screen.getByRole('option', { name: /français/i })
      fireEvent.click(frOption)
      expect(mockPush).toHaveBeenCalledWith('/fr/')
    })
  })

  describe('Accessibility', () => {
    it('has correct aria-label on main button', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      expect(button).toHaveAttribute('aria-label', 'Select language')
    })

    it('has aria-expanded attribute on button', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      expect(button).toHaveAttribute('aria-expanded', 'false')
      fireEvent.click(button)
      expect(button).toHaveAttribute('aria-expanded', 'true')
    })

    it('has aria-haspopup attribute on button', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      expect(button).toHaveAttribute('aria-haspopup', 'listbox')
    })

    it('dropdown has listbox role', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      expect(screen.getByRole('listbox')).toBeInTheDocument()
    })

    it('language options have option role', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const options = screen.getAllByRole('option')
      expect(options).toHaveLength(2)
    })

    it('current language option has aria-selected true', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const enOption = screen.getByRole('option', { name: /english/i })
      expect(enOption).toHaveAttribute('aria-selected', 'true')
    })

    it('non-current language option has aria-selected false', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const frOption = screen.getByRole('option', { name: /français/i })
      expect(frOption).toHaveAttribute('aria-selected', 'false')
    })
  })

  describe('Visual State', () => {
    it('shows checkmark for selected language', () => {
      const { container } = render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      // The checkmark SVG is rendered inside the selected language option
      const checkmarks = container.querySelectorAll('svg')
      // There should be 2 SVGs: chevron in button + checkmark in selected option
      expect(checkmarks.length).toBeGreaterThan(1)
    })

    it('applies selected styling to current language', () => {
      render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      fireEvent.click(button)
      const enOption = screen.getByRole('option', { name: /english/i })
      expect(enOption).toHaveClass('bg-primary-50')
    })

    it('rotates chevron when dropdown is open', () => {
      const { container } = render(<LanguageSwitcher />)
      const button = screen.getByRole('button', { name: /select language/i })
      const chevron = container.querySelector('button svg')
      expect(chevron).not.toHaveClass('rotate-180')
      fireEvent.click(button)
      expect(chevron).toHaveClass('rotate-180')
    })
  })
})
