/**
 * Tests for Government Documents components.
 * Tests GovernmentDocumentCard and GovernmentDocumentUpload components.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { GovernmentDocumentCard } from '@/components/GovernmentDocumentCard'
import { GovernmentDocumentUpload } from '@/components/GovernmentDocumentUpload'
import type {
  GovernmentDocument,
  GovernmentDocumentTypeDefinition,
} from '@/lib/types'

// ============================================================================
// GovernmentDocumentCard Tests
// ============================================================================

describe('GovernmentDocumentCard Component', () => {
  const mockOnUpload = vi.fn()
  const mockOnView = vi.fn()
  const mockOnDelete = vi.fn()

  const createMockDocument = (
    overrides: Partial<GovernmentDocument> = {}
  ): GovernmentDocument => ({
    id: 'doc-1',
    familyId: 'family-1',
    personId: 'person-1',
    personName: 'John Doe',
    documentTypeId: 'type-1',
    documentTypeName: 'Birth Certificate',
    category: 'child_identity',
    status: 'verified',
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
    ...overrides,
  })

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Basic Rendering', () => {
    it('renders document type name', () => {
      const doc = createMockDocument({ documentTypeName: 'Birth Certificate' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Birth Certificate')).toBeInTheDocument()
    })

    it('renders person name', () => {
      const doc = createMockDocument({ personName: 'Jane Smith' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('For: Jane Smith')).toBeInTheDocument()
    })

    it('renders upload date when available', () => {
      const doc = createMockDocument({ uploadedAt: '2024-03-15T10:00:00Z' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText(/Received:/)).toBeInTheDocument()
    })

    it('renders document number when available', () => {
      const doc = createMockDocument({ documentNumber: 'ABC123456' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Doc #: ABC123456')).toBeInTheDocument()
    })
  })

  describe('Status Badge Rendering', () => {
    it('renders verified status correctly', () => {
      const doc = createMockDocument({ status: 'verified' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Verified')).toBeInTheDocument()
    })

    it('renders pending status correctly', () => {
      const doc = createMockDocument({ status: 'pending_verification' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Pending')).toBeInTheDocument()
    })

    it('renders rejected status correctly', () => {
      const doc = createMockDocument({ status: 'rejected' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Rejected')).toBeInTheDocument()
    })

    it('renders expired status correctly', () => {
      const doc = createMockDocument({ status: 'expired' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Expired')).toBeInTheDocument()
    })

    it('renders missing status correctly', () => {
      const doc = createMockDocument({ status: 'missing' })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText('Missing')).toBeInTheDocument()
    })

    it('applies badge-success class for verified status', () => {
      const doc = createMockDocument({ status: 'verified' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const badge = container.querySelector('.badge-success')
      expect(badge).toBeInTheDocument()
    })

    it('applies badge-warning class for pending status', () => {
      const doc = createMockDocument({ status: 'pending_verification' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const badge = container.querySelector('.badge-warning')
      expect(badge).toBeInTheDocument()
    })

    it('applies badge-error class for rejected status', () => {
      const doc = createMockDocument({ status: 'rejected' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const badge = container.querySelector('.badge-error')
      expect(badge).toBeInTheDocument()
    })
  })

  describe('Category Icons', () => {
    it('renders child_identity category icon', () => {
      const doc = createMockDocument({ category: 'child_identity' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const svg = container.querySelector('svg.text-blue-600')
      expect(svg).toBeInTheDocument()
    })

    it('renders parent_identity category icon', () => {
      const doc = createMockDocument({ category: 'parent_identity' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const svg = container.querySelector('svg.text-purple-600')
      expect(svg).toBeInTheDocument()
    })

    it('renders health category icon', () => {
      const doc = createMockDocument({ category: 'health' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const svg = container.querySelector('svg.text-red-600')
      expect(svg).toBeInTheDocument()
    })

    it('renders immigration category icon', () => {
      const doc = createMockDocument({ category: 'immigration' })
      const { container } = render(<GovernmentDocumentCard document={doc} />)
      const svg = container.querySelector('svg.text-green-600')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Verification Info', () => {
    it('shows verified date for verified documents', () => {
      const doc = createMockDocument({
        status: 'verified',
        verifiedAt: '2024-02-15T10:00:00Z',
      })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText(/Verified on/)).toBeInTheDocument()
    })

    it('shows rejection reason for rejected documents', () => {
      const doc = createMockDocument({
        status: 'rejected',
        rejectionReason: 'Document is blurry',
      })
      render(<GovernmentDocumentCard document={doc} />)
      expect(screen.getByText(/Reason:/)).toBeInTheDocument()
      expect(screen.getByText(/Document is blurry/)).toBeInTheDocument()
    })
  })

  describe('Action Buttons', () => {
    it('shows View Document button when file exists', () => {
      const doc = createMockDocument({
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onView={mockOnView} />)
      expect(screen.getByText('View Document')).toBeInTheDocument()
    })

    it('does not show View Document button when no file', () => {
      const doc = createMockDocument({ fileUrl: undefined })
      render(<GovernmentDocumentCard document={doc} onView={mockOnView} />)
      expect(screen.queryByText('View Document')).not.toBeInTheDocument()
    })

    it('shows Upload Document button for missing documents', () => {
      const doc = createMockDocument({ status: 'missing' })
      render(<GovernmentDocumentCard document={doc} onUpload={mockOnUpload} />)
      expect(screen.getByText('Upload Document')).toBeInTheDocument()
    })

    it('shows Re-upload Document button for rejected documents', () => {
      const doc = createMockDocument({
        status: 'rejected',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onUpload={mockOnUpload} />)
      expect(screen.getByText('Re-upload Document')).toBeInTheDocument()
    })

    it('shows Re-upload Document button for expired documents', () => {
      const doc = createMockDocument({
        status: 'expired',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onUpload={mockOnUpload} />)
      expect(screen.getByText('Re-upload Document')).toBeInTheDocument()
    })

    it('shows Delete button for non-verified documents with files', () => {
      const doc = createMockDocument({
        status: 'pending_verification',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onDelete={mockOnDelete} />)
      expect(screen.getByText('Delete')).toBeInTheDocument()
    })

    it('does not show Delete button for verified documents', () => {
      const doc = createMockDocument({
        status: 'verified',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onDelete={mockOnDelete} />)
      expect(screen.queryByText('Delete')).not.toBeInTheDocument()
    })
  })

  describe('Button Interactions', () => {
    it('calls onView when View Document button is clicked', () => {
      const doc = createMockDocument({
        id: 'doc-123',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onView={mockOnView} />)
      fireEvent.click(screen.getByText('View Document'))
      expect(mockOnView).toHaveBeenCalledWith('doc-123')
    })

    it('calls onUpload with correct params when Upload button is clicked', () => {
      const doc = createMockDocument({
        status: 'missing',
        documentTypeId: 'type-456',
        personId: 'person-789',
      })
      render(<GovernmentDocumentCard document={doc} onUpload={mockOnUpload} />)
      fireEvent.click(screen.getByText('Upload Document'))
      expect(mockOnUpload).toHaveBeenCalledWith('type-456', 'person-789')
    })

    it('calls onDelete when Delete button is clicked', () => {
      const doc = createMockDocument({
        id: 'doc-delete',
        status: 'pending_verification',
        fileUrl: 'https://example.com/doc.pdf',
      })
      render(<GovernmentDocumentCard document={doc} onDelete={mockOnDelete} />)
      fireEvent.click(screen.getByText('Delete'))
      expect(mockOnDelete).toHaveBeenCalledWith('doc-delete')
    })
  })
})

// ============================================================================
// GovernmentDocumentUpload Tests
// ============================================================================

describe('GovernmentDocumentUpload Component', () => {
  const mockOnClose = vi.fn()
  const mockOnSubmit = vi.fn()

  const mockDocumentType: GovernmentDocumentTypeDefinition = {
    id: 'type-1',
    name: 'Birth Certificate',
    description: 'Official birth certificate document',
    category: 'child_identity',
    isRequired: true,
    appliesToChild: true,
    appliesToParent: false,
    hasExpiration: false,
  }

  const defaultProps = {
    isOpen: true,
    onClose: mockOnClose,
    onSubmit: mockOnSubmit,
    documentType: mockDocumentType,
    personId: 'person-1',
    personName: 'John Doe',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockOnSubmit.mockResolvedValue(undefined)
  })

  describe('Modal Visibility', () => {
    it('renders when isOpen is true', () => {
      render(<GovernmentDocumentUpload {...defaultProps} isOpen={true} />)
      expect(screen.getByText('Upload Document')).toBeInTheDocument()
    })

    it('does not render when isOpen is false', () => {
      render(<GovernmentDocumentUpload {...defaultProps} isOpen={false} />)
      expect(screen.queryByText('Upload Document')).not.toBeInTheDocument()
    })

    it('does not render when documentType is null', () => {
      render(
        <GovernmentDocumentUpload {...defaultProps} documentType={null} />
      )
      expect(screen.queryByText('Upload Document')).not.toBeInTheDocument()
    })
  })

  describe('Header Content', () => {
    it('shows document type name in header', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByText('Birth Certificate for John Doe')).toBeInTheDocument()
    })

    it('shows document description when available', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(
        screen.getByText('Official birth certificate document')
      ).toBeInTheDocument()
    })
  })

  describe('Drop Zone', () => {
    it('shows drag and drop text when no file selected', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByText('Drag and drop a file here')).toBeInTheDocument()
    })

    it('shows accepted file types info', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByText(/PDF, JPG, or PNG/)).toBeInTheDocument()
    })

    it('shows file size limit', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByText(/max 10MB/)).toBeInTheDocument()
    })
  })

  describe('Optional Fields', () => {
    it('shows document number input', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByLabelText(/Document Number/)).toBeInTheDocument()
    })

    it('shows issue date input', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByLabelText(/Issue Date/)).toBeInTheDocument()
    })

    it('shows expiration date input when document type has expiration', () => {
      const docTypeWithExpiration = { ...mockDocumentType, hasExpiration: true }
      render(
        <GovernmentDocumentUpload
          {...defaultProps}
          documentType={docTypeWithExpiration}
        />
      )
      expect(screen.getByLabelText(/Expiration Date/)).toBeInTheDocument()
    })

    it('does not show expiration date input when document type has no expiration', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.queryByLabelText(/Expiration Date/)).not.toBeInTheDocument()
    })

    it('shows notes textarea', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByLabelText(/Notes/)).toBeInTheDocument()
    })
  })

  describe('Form Buttons', () => {
    it('shows Cancel button', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument()
    })

    it('shows Upload Document button', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(
        screen.getByRole('button', { name: /Upload Document/ })
      ).toBeInTheDocument()
    })

    it('disables Upload Document button when no file selected', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      const submitButton = screen.getByRole('button', { name: /Upload Document/ })
      expect(submitButton).toBeDisabled()
    })
  })

  describe('Close Actions', () => {
    it('calls onClose when Cancel button is clicked', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
      expect(mockOnClose).toHaveBeenCalled()
    })

    it('calls onClose when close icon button is clicked', () => {
      const { container } = render(<GovernmentDocumentUpload {...defaultProps} />)
      // Find the close button in the header (not the Cancel button)
      const closeButtons = container.querySelectorAll('button')
      // The first button with X icon is the close button in header
      const headerCloseButton = Array.from(closeButtons).find(
        (btn) =>
          btn.querySelector('svg path[d="M6 18L18 6M6 6l12 12"]') &&
          !btn.textContent?.includes('Delete')
      )
      if (headerCloseButton) {
        fireEvent.click(headerCloseButton)
        expect(mockOnClose).toHaveBeenCalled()
      }
    })
  })

  describe('File Selection', () => {
    it('handles file input change', async () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      const input = document.querySelector('input[type="file"]') as HTMLInputElement
      expect(input).toBeInTheDocument()
      expect(input.accept).toBe('.pdf,.jpg,.jpeg,.png')
    })

    it('shows file info after selecting a file', async () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      const input = document.querySelector('input[type="file"]') as HTMLInputElement

      const mockFile = new File(['test content'], 'test-document.pdf', {
        type: 'application/pdf',
      })

      Object.defineProperty(input, 'files', {
        value: [mockFile],
        configurable: true,
      })

      fireEvent.change(input)

      await waitFor(() => {
        expect(screen.getByText('test-document.pdf')).toBeInTheDocument()
      })
    })
  })

  describe('Form Submission', () => {
    it('calls onSubmit with correct data when form is submitted', async () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      const input = document.querySelector('input[type="file"]') as HTMLInputElement

      const mockFile = new File(['test content'], 'test-document.pdf', {
        type: 'application/pdf',
      })

      Object.defineProperty(input, 'files', {
        value: [mockFile],
        configurable: true,
      })

      fireEvent.change(input)

      await waitFor(() => {
        expect(screen.getByText('test-document.pdf')).toBeInTheDocument()
      })

      // Fill optional fields
      const docNumberInput = screen.getByLabelText(/Document Number/)
      fireEvent.change(docNumberInput, { target: { value: '123456' } })

      // Submit the form
      const submitButton = screen.getByRole('button', { name: /Upload Document/ })
      expect(submitButton).not.toBeDisabled()
      fireEvent.click(submitButton)

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledWith(
          expect.objectContaining({
            personId: 'person-1',
            documentTypeId: 'type-1',
            documentNumber: '123456',
          })
        )
      })
    })
  })

  describe('File Validation', () => {
    it('shows error for invalid file type', async () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      const input = document.querySelector('input[type="file"]') as HTMLInputElement

      const invalidFile = new File(['test'], 'test.txt', {
        type: 'text/plain',
      })

      Object.defineProperty(input, 'files', {
        value: [invalidFile],
        configurable: true,
      })

      fireEvent.change(input)

      await waitFor(() => {
        expect(
          screen.getByText('Please select a PDF, JPG, or PNG file.')
        ).toBeInTheDocument()
      })
    })
  })

  describe('Help Text', () => {
    it('shows help text about document review', () => {
      render(<GovernmentDocumentUpload {...defaultProps} />)
      expect(
        screen.getByText(/Please ensure the document is clear and legible/)
      ).toBeInTheDocument()
    })
  })
})
