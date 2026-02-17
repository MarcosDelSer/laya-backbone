'use client';

import { useState, useEffect, useRef } from 'react';
import type {
  ThreadType,
  MessageThread,
  ThreadParticipant,
} from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Recipient option for the dropdown.
 */
export interface RecipientOption {
  id: string;
  name: string;
  type: 'educator' | 'director' | 'admin';
  role?: string;
}

/**
 * Child option for the dropdown.
 */
export interface ChildOption {
  id: string;
  name: string;
}

/**
 * Props for the NewThreadModal component.
 */
interface NewThreadModalProps {
  /** Whether the modal is open */
  isOpen: boolean;
  /** Callback when modal is closed */
  onClose: () => void;
  /** Callback when thread is successfully created */
  onThreadCreated: (thread: MessageThread) => void;
  /** Available recipients to message */
  recipients: RecipientOption[];
  /** Available children to associate with the thread */
  children: ChildOption[];
  /** Whether the form is in a loading state */
  isLoading?: boolean;
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Thread type options for the dropdown.
 */
const THREAD_TYPE_OPTIONS: { value: ThreadType; label: string }[] = [
  { value: 'daily_log', label: 'Daily Log' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'serious', label: 'Serious' },
  { value: 'admin', label: 'Administrative' },
];

// ============================================================================
// Component
// ============================================================================

export function NewThreadModal({
  isOpen,
  onClose,
  onThreadCreated,
  recipients,
  children,
  isLoading = false,
}: NewThreadModalProps) {
  // Form state
  const [selectedRecipientId, setSelectedRecipientId] = useState<string>('');
  const [selectedChildId, setSelectedChildId] = useState<string>('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [threadType, setThreadType] = useState<ThreadType>('daily_log');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Refs
  const modalRef = useRef<HTMLDivElement>(null);
  const subjectInputRef = useRef<HTMLInputElement>(null);

  // Derived state
  const selectedRecipient = recipients.find((r) => r.id === selectedRecipientId);
  const canSubmit =
    selectedRecipientId && subject.trim() && message.trim() && !isSubmitting && !isLoading;

  // ============================================================================
  // Effects
  // ============================================================================

  // Focus subject input when modal opens
  useEffect(() => {
    if (isOpen && subjectInputRef.current) {
      // Small delay to ensure modal is rendered
      const timer = setTimeout(() => {
        subjectInputRef.current?.focus();
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [isOpen]);

  // Reset form when modal closes
  useEffect(() => {
    if (!isOpen) {
      setSelectedRecipientId('');
      setSelectedChildId('');
      setSubject('');
      setMessage('');
      setThreadType('daily_log');
      setError(null);
    }
  }, [isOpen]);

  // Handle escape key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen && !isSubmitting) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, isSubmitting, onClose]);

  // Handle click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (
        modalRef.current &&
        !modalRef.current.contains(e.target as Node) &&
        !isSubmitting
      ) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen, isSubmitting, onClose]);

  // ============================================================================
  // Handlers
  // ============================================================================

  /**
   * Handle form submission.
   */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!canSubmit || !selectedRecipient) return;

    setIsSubmitting(true);
    setError(null);

    try {
      // Build participant list
      const participants: ThreadParticipant[] = [
        {
          userId: selectedRecipient.id,
          userType: selectedRecipient.type,
          displayName: selectedRecipient.name,
        },
      ];

      // Import and call the API
      const { createThread } = await import('@/lib/messaging-client');

      const thread = await createThread({
        subject: subject.trim(),
        threadType,
        childId: selectedChildId || undefined,
        participants,
        initialMessage: message.trim(),
      });

      onThreadCreated(thread);
      onClose();
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to create conversation';
      setError(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  // ============================================================================
  // Render
  // ============================================================================

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black bg-opacity-50 transition-opacity" />

      {/* Modal container */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div
          ref={modalRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby="new-thread-title"
          className="relative w-full max-w-lg transform rounded-xl bg-white shadow-xl transition-all"
        >
          {/* Header */}
          <div className="border-b border-gray-200 px-6 py-4">
            <div className="flex items-center justify-between">
              <h2
                id="new-thread-title"
                className="text-lg font-semibold text-gray-900"
              >
                New Message
              </h2>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50"
              >
                <span className="sr-only">Close</span>
                <svg
                  className="h-6 w-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit}>
            <div className="space-y-4 px-6 py-4">
              {/* Error message */}
              {error && (
                <div className="rounded-lg bg-red-50 p-3">
                  <div className="flex items-center space-x-2">
                    <svg
                      className="h-5 w-5 text-red-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                    <span className="text-sm text-red-700">{error}</span>
                  </div>
                </div>
              )}

              {/* Recipient selector */}
              <div>
                <label
                  htmlFor="recipient"
                  className="block text-sm font-medium text-gray-700"
                >
                  To <span className="text-red-500">*</span>
                </label>
                <select
                  id="recipient"
                  value={selectedRecipientId}
                  onChange={(e) => setSelectedRecipientId(e.target.value)}
                  disabled={isSubmitting || isLoading}
                  className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100"
                  required
                >
                  <option value="">Select a recipient...</option>
                  {recipients.map((recipient) => (
                    <option key={recipient.id} value={recipient.id}>
                      {recipient.name}
                      {recipient.role ? ` (${recipient.role})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Child selector (optional) */}
              {children.length > 0 && (
                <div>
                  <label
                    htmlFor="child"
                    className="block text-sm font-medium text-gray-700"
                  >
                    Regarding Child
                  </label>
                  <select
                    id="child"
                    value={selectedChildId}
                    onChange={(e) => setSelectedChildId(e.target.value)}
                    disabled={isSubmitting || isLoading}
                    className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100"
                  >
                    <option value="">Select a child (optional)...</option>
                    {children.map((child) => (
                      <option key={child.id} value={child.id}>
                        {child.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Thread type selector */}
              <div>
                <label
                  htmlFor="threadType"
                  className="block text-sm font-medium text-gray-700"
                >
                  Category
                </label>
                <select
                  id="threadType"
                  value={threadType}
                  onChange={(e) => setThreadType(e.target.value as ThreadType)}
                  disabled={isSubmitting || isLoading}
                  className="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100"
                >
                  {THREAD_TYPE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* Subject input */}
              <div>
                <label
                  htmlFor="subject"
                  className="block text-sm font-medium text-gray-700"
                >
                  Subject <span className="text-red-500">*</span>
                </label>
                <input
                  ref={subjectInputRef}
                  type="text"
                  id="subject"
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  disabled={isSubmitting || isLoading}
                  placeholder="Enter a subject..."
                  maxLength={100}
                  className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100"
                  required
                />
                <p className="mt-1 text-xs text-gray-500">
                  {subject.length}/100 characters
                </p>
              </div>

              {/* Message textarea */}
              <div>
                <label
                  htmlFor="message"
                  className="block text-sm font-medium text-gray-700"
                >
                  Message <span className="text-red-500">*</span>
                </label>
                <textarea
                  id="message"
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  disabled={isSubmitting || isLoading}
                  placeholder="Type your message..."
                  rows={4}
                  maxLength={2000}
                  className="mt-1 block w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100"
                  required
                />
                <p className="mt-1 text-xs text-gray-500">
                  {message.length}/2000 characters
                </p>
              </div>
            </div>

            {/* Footer with actions */}
            <div className="border-t border-gray-200 bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:gap-3">
              <button
                type="submit"
                disabled={!canSubmit}
                className="inline-flex w-full justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 sm:w-auto"
              >
                {isSubmitting ? (
                  <>
                    <svg
                      className="mr-2 h-4 w-4 animate-spin text-white"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
                      <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                      />
                      <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                    Sending...
                  </>
                ) : (
                  <>
                    <svg
                      className="mr-2 h-4 w-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                      />
                    </svg>
                    Send Message
                  </>
                )}
              </button>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="mt-3 inline-flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 sm:mt-0 sm:w-auto"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
