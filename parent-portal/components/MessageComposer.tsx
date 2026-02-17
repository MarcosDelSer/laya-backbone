'use client';

import { useState, useRef, useEffect, useCallback } from 'react';

// ============================================================================
// Types
// ============================================================================

/**
 * Represents a pending attachment before upload.
 * Contains the file object and preview information.
 */
export interface PendingAttachment {
  /** Unique identifier for tracking in the UI */
  id: string;
  /** The actual file object */
  file: File;
  /** Preview URL (blob URL for images, undefined otherwise) */
  previewUrl?: string;
  /** Whether the file is an image */
  isImage: boolean;
}

/**
 * Props for the MessageComposer component.
 */
interface MessageComposerProps {
  /**
   * Callback when a message is sent.
   * @param content - The text content of the message
   * @param attachments - Array of files to be attached (if any)
   */
  onSendMessage: (content: string, attachments?: File[]) => void;
  /** Whether the composer is disabled (e.g., while sending) */
  disabled?: boolean;
  /** Placeholder text for the input */
  placeholder?: string;
  /** Maximum number of attachments allowed */
  maxAttachments?: number;
  /** Maximum file size in bytes (default 10MB) */
  maxFileSize?: number;
  /** Allowed file types (MIME types or extensions) */
  allowedFileTypes?: string[];
}

// ============================================================================
// Constants
// ============================================================================

/** Default maximum file size: 10MB */
const DEFAULT_MAX_FILE_SIZE = 10 * 1024 * 1024;

/** Default maximum number of attachments */
const DEFAULT_MAX_ATTACHMENTS = 5;

/** Default allowed file types */
const DEFAULT_ALLOWED_FILE_TYPES = [
  // Images
  'image/jpeg',
  'image/png',
  'image/gif',
  'image/webp',
  // Documents
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'text/plain',
  // Spreadsheets
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

/** Human-readable file type names for error messages */
const FILE_TYPE_NAMES: Record<string, string> = {
  'image/jpeg': 'JPEG image',
  'image/png': 'PNG image',
  'image/gif': 'GIF image',
  'image/webp': 'WebP image',
  'application/pdf': 'PDF document',
  'application/msword': 'Word document',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word document',
  'text/plain': 'Text file',
  'application/vnd.ms-excel': 'Excel spreadsheet',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel spreadsheet',
};

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Generate a unique ID for tracking attachments.
 */
function generateAttachmentId(): string {
  return `attachment-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
}

/**
 * Check if a file is an image based on MIME type.
 */
function isImageFile(file: File): boolean {
  return file.type.startsWith('image/');
}

/**
 * Format file size for display.
 */
function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

/**
 * Get a display name for a file type.
 */
function getFileTypeName(mimeType: string): string {
  return FILE_TYPE_NAMES[mimeType] || 'File';
}

/**
 * Get an icon for a file type.
 */
function getFileIcon(mimeType: string): string {
  if (mimeType.startsWith('image/')) return 'image';
  if (mimeType === 'application/pdf') return 'pdf';
  if (mimeType.includes('word')) return 'doc';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'xls';
  if (mimeType === 'text/plain') return 'txt';
  return 'file';
}

// ============================================================================
// Component
// ============================================================================

export function MessageComposer({
  onSendMessage,
  disabled = false,
  placeholder = 'Type a message...',
  maxAttachments = DEFAULT_MAX_ATTACHMENTS,
  maxFileSize = DEFAULT_MAX_FILE_SIZE,
  allowedFileTypes = DEFAULT_ALLOWED_FILE_TYPES,
}: MessageComposerProps) {
  // State
  const [message, setMessage] = useState('');
  const [attachments, setAttachments] = useState<PendingAttachment[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isDragOver, setIsDragOver] = useState(false);

  // Refs
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // ============================================================================
  // Effects
  // ============================================================================

  // Auto-resize textarea based on content
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${Math.min(
        textareaRef.current.scrollHeight,
        150
      )}px`;
    }
  }, [message]);

  // Cleanup preview URLs on unmount
  useEffect(() => {
    return () => {
      attachments.forEach((attachment) => {
        if (attachment.previewUrl) {
          URL.revokeObjectURL(attachment.previewUrl);
        }
      });
    };
  }, [attachments]);

  // Auto-dismiss error after 5 seconds
  useEffect(() => {
    if (error) {
      const timer = setTimeout(() => setError(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [error]);

  // ============================================================================
  // File Handling
  // ============================================================================

  /**
   * Validate and process selected files.
   */
  const processFiles = useCallback(
    (files: FileList | File[]) => {
      const fileArray = Array.from(files);
      const newAttachments: PendingAttachment[] = [];
      const errors: string[] = [];

      // Check total attachment limit
      const totalAfterAdd = attachments.length + fileArray.length;
      if (totalAfterAdd > maxAttachments) {
        setError(`You can only attach up to ${maxAttachments} files.`);
        return;
      }

      for (const file of fileArray) {
        // Check file size
        if (file.size > maxFileSize) {
          errors.push(`"${file.name}" exceeds the ${formatFileSize(maxFileSize)} limit.`);
          continue;
        }

        // Check file type
        if (!allowedFileTypes.includes(file.type)) {
          errors.push(`"${file.name}" is not a supported file type.`);
          continue;
        }

        // Check for duplicates
        const isDuplicate = attachments.some(
          (a) => a.file.name === file.name && a.file.size === file.size
        );
        if (isDuplicate) {
          errors.push(`"${file.name}" is already attached.`);
          continue;
        }

        // Create preview URL for images
        const isImage = isImageFile(file);
        const previewUrl = isImage ? URL.createObjectURL(file) : undefined;

        newAttachments.push({
          id: generateAttachmentId(),
          file,
          previewUrl,
          isImage,
        });
      }

      if (errors.length > 0) {
        setError(errors.join(' '));
      }

      if (newAttachments.length > 0) {
        setAttachments((prev) => [...prev, ...newAttachments]);
      }
    },
    [attachments, maxAttachments, maxFileSize, allowedFileTypes]
  );

  /**
   * Remove an attachment by ID.
   */
  const removeAttachment = useCallback((id: string) => {
    setAttachments((prev) => {
      const toRemove = prev.find((a) => a.id === id);
      if (toRemove?.previewUrl) {
        URL.revokeObjectURL(toRemove.previewUrl);
      }
      return prev.filter((a) => a.id !== id);
    });
  }, []);

  /**
   * Clear all attachments.
   */
  const clearAttachments = useCallback(() => {
    attachments.forEach((attachment) => {
      if (attachment.previewUrl) {
        URL.revokeObjectURL(attachment.previewUrl);
      }
    });
    setAttachments([]);
  }, [attachments]);

  // ============================================================================
  // Event Handlers
  // ============================================================================

  const handleAttachClick = () => {
    fileInputRef.current?.click();
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      processFiles(e.target.files);
      // Reset input value so the same file can be selected again
      e.target.value = '';
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (!disabled) {
      setIsDragOver(true);
    }
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);

    if (disabled) return;

    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      processFiles(files);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmedMessage = message.trim();
    const hasContent = trimmedMessage || attachments.length > 0;

    if (hasContent && !disabled) {
      const files = attachments.map((a) => a.file);
      onSendMessage(trimmedMessage, files.length > 0 ? files : undefined);
      setMessage('');
      clearAttachments();
      // Reset textarea height
      if (textareaRef.current) {
        textareaRef.current.style.height = 'auto';
      }
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Submit on Enter (without Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  const handleDismissError = () => {
    setError(null);
  };

  // ============================================================================
  // Computed Values
  // ============================================================================

  const canSend =
    !disabled && (message.trim().length > 0 || attachments.length > 0);
  const canAddMoreAttachments = attachments.length < maxAttachments;

  // ============================================================================
  // Render
  // ============================================================================

  return (
    <form
      onSubmit={handleSubmit}
      className={`bg-white border-t border-gray-200 p-4 ${
        isDragOver ? 'bg-blue-50 ring-2 ring-primary ring-inset' : ''
      }`}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
    >
      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        className="hidden"
        multiple
        accept={allowedFileTypes.join(',')}
        onChange={handleFileSelect}
        disabled={disabled || !canAddMoreAttachments}
      />

      {/* Error message */}
      {error && (
        <div className="mb-3 flex items-start justify-between rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          <div className="flex items-start space-x-2">
            <svg
              className="mt-0.5 h-4 w-4 flex-shrink-0 text-red-400"
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
            <span>{error}</span>
          </div>
          <button
            type="button"
            onClick={handleDismissError}
            className="ml-2 flex-shrink-0 text-red-400 hover:text-red-600"
          >
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      )}

      {/* Attachment previews */}
      {attachments.length > 0 && (
        <div className="mb-3 flex flex-wrap gap-2">
          {attachments.map((attachment) => (
            <AttachmentPreview
              key={attachment.id}
              attachment={attachment}
              onRemove={() => removeAttachment(attachment.id)}
              disabled={disabled}
            />
          ))}
        </div>
      )}

      {/* Drag and drop indicator */}
      {isDragOver && (
        <div className="mb-3 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-blue-50 py-4">
          <div className="flex items-center space-x-2 text-primary">
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
              />
            </svg>
            <span className="font-medium">Drop files here to attach</span>
          </div>
        </div>
      )}

      <div className="flex items-end space-x-3">
        {/* Attachment button */}
        <button
          type="button"
          onClick={handleAttachClick}
          className={`flex-shrink-0 p-2 rounded-full focus:outline-none focus:ring-2 focus:ring-primary ${
            disabled || !canAddMoreAttachments
              ? 'text-gray-300 cursor-not-allowed'
              : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
          }`}
          disabled={disabled || !canAddMoreAttachments}
          title={
            !canAddMoreAttachments
              ? `Maximum ${maxAttachments} attachments reached`
              : 'Attach file'
          }
        >
          <svg
            className="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
            />
          </svg>
        </button>

        {/* Message input */}
        <div className="relative flex-1">
          <textarea
            ref={textareaRef}
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={placeholder}
            disabled={disabled}
            rows={1}
            className="w-full resize-none rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 pr-12 text-sm focus:border-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100 disabled:text-gray-500"
          />
          {/* Character count (optional) */}
          {message.length > 200 && (
            <span
              className={`absolute bottom-2 right-14 text-xs ${
                message.length > 500 ? 'text-red-500' : 'text-gray-400'
              }`}
            >
              {message.length}/500
            </span>
          )}
        </div>

        {/* Send button */}
        <button
          type="submit"
          disabled={!canSend}
          className="flex-shrink-0 rounded-full bg-primary p-3 text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          <svg
            className="h-5 w-5"
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
        </button>
      </div>

      {/* Helper text */}
      <div className="mt-2 flex items-center justify-between text-xs text-gray-400">
        <span>
          Press Enter to send, Shift+Enter for new line
        </span>
        {attachments.length > 0 && (
          <span>
            {attachments.length}/{maxAttachments} attachments
          </span>
        )}
      </div>
    </form>
  );
}

// ============================================================================
// Attachment Preview Sub-component
// ============================================================================

interface AttachmentPreviewProps {
  attachment: PendingAttachment;
  onRemove: () => void;
  disabled: boolean;
}

function AttachmentPreview({
  attachment,
  onRemove,
  disabled,
}: AttachmentPreviewProps) {
  const { file, previewUrl, isImage } = attachment;
  const iconType = getFileIcon(file.type);

  return (
    <div className="group relative flex items-center space-x-2 rounded-lg border border-gray-200 bg-gray-50 p-2 pr-8">
      {/* Preview or icon */}
      {isImage && previewUrl ? (
        <img
          src={previewUrl}
          alt={file.name}
          className="h-10 w-10 rounded object-cover"
        />
      ) : (
        <div className="flex h-10 w-10 items-center justify-center rounded bg-gray-200">
          <FileIcon type={iconType} />
        </div>
      )}

      {/* File info */}
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-gray-700" title={file.name}>
          {file.name}
        </p>
        <p className="text-xs text-gray-500">
          {formatFileSize(file.size)} &middot; {getFileTypeName(file.type)}
        </p>
      </div>

      {/* Remove button */}
      <button
        type="button"
        onClick={onRemove}
        disabled={disabled}
        className={`absolute right-1 top-1 rounded-full p-1 ${
          disabled
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-gray-400 hover:bg-gray-200 hover:text-gray-600'
        }`}
        title="Remove attachment"
      >
        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  );
}

// ============================================================================
// File Icon Sub-component
// ============================================================================

interface FileIconProps {
  type: string;
}

function FileIcon({ type }: FileIconProps) {
  switch (type) {
    case 'image':
      return (
        <svg className="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      );
    case 'pdf':
      return (
        <svg className="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
          />
        </svg>
      );
    case 'doc':
      return (
        <svg className="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      );
    case 'xls':
      return (
        <svg className="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      );
    case 'txt':
      return (
        <svg className="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      );
    default:
      return (
        <svg className="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
          />
        </svg>
      );
  }
}
