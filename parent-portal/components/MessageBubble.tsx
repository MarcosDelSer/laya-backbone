'use client';

import { useState, useCallback } from 'react';
import type { SenderType, MessageContentType, MessageAttachment } from '../lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Extended message interface for MessageBubble with rich content support.
 */
interface MessageBubbleMessage {
  id: string;
  senderId: string;
  senderName: string;
  senderType?: SenderType;
  content: string;
  contentType?: MessageContentType;
  timestamp: string;
  read: boolean;
  attachments?: MessageAttachment[];
}

/**
 * Props for the MessageBubble component.
 */
interface MessageBubbleProps {
  message: MessageBubbleMessage;
  isCurrentUser: boolean;
  /** Optional callback when an image attachment is clicked */
  onImageClick?: (attachment: MessageAttachment) => void;
  /** Optional callback when a file attachment is clicked */
  onFileClick?: (attachment: MessageAttachment) => void;
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Sender type badge configuration.
 */
const SENDER_TYPE_CONFIG: Record<
  SenderType,
  { label: string; bgColor: string; textColor: string }
> = {
  parent: {
    label: 'Parent',
    bgColor: 'bg-blue-100',
    textColor: 'text-blue-700',
  },
  educator: {
    label: 'Educator',
    bgColor: 'bg-green-100',
    textColor: 'text-green-700',
  },
  director: {
    label: 'Director',
    bgColor: 'bg-purple-100',
    textColor: 'text-purple-700',
  },
  admin: {
    label: 'Admin',
    bgColor: 'bg-orange-100',
    textColor: 'text-orange-700',
  },
};

/**
 * File type icon mappings for common file types.
 */
const FILE_TYPE_ICONS: Record<string, { icon: string; color: string }> = {
  'application/pdf': { icon: 'pdf', color: 'text-red-500' },
  'application/msword': { icon: 'doc', color: 'text-blue-500' },
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': {
    icon: 'doc',
    color: 'text-blue-500',
  },
  'application/vnd.ms-excel': { icon: 'xls', color: 'text-green-500' },
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': {
    icon: 'xls',
    color: 'text-green-500',
  },
  'text/plain': { icon: 'txt', color: 'text-gray-500' },
  'video/mp4': { icon: 'video', color: 'text-purple-500' },
  'video/webm': { icon: 'video', color: 'text-purple-500' },
  'video/quicktime': { icon: 'video', color: 'text-purple-500' },
  'audio/mpeg': { icon: 'audio', color: 'text-pink-500' },
  'audio/wav': { icon: 'audio', color: 'text-pink-500' },
};

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Format timestamp to display time.
 */
function formatTime(timestamp: string): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format timestamp to display date.
 */
function formatDate(timestamp: string): string {
  const date = new Date(timestamp);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  } else if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    });
  }
}

/**
 * Check if a file type is an image.
 */
function isImageAttachment(fileType: string): boolean {
  return fileType.startsWith('image/');
}

/**
 * Check if a file type is a video.
 */
function isVideoAttachment(fileType: string): boolean {
  return fileType.startsWith('video/');
}

/**
 * Check if a file type is an audio file.
 */
function isAudioAttachment(fileType: string): boolean {
  return fileType.startsWith('audio/');
}

/**
 * Format file size for display.
 */
function formatFileSize(bytes: number | undefined): string {
  if (bytes === undefined || bytes === 0) return '';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

/**
 * Get file type icon configuration.
 */
function getFileTypeConfig(
  fileType: string
): { icon: string; color: string } {
  if (isImageAttachment(fileType)) {
    return { icon: 'image', color: 'text-blue-500' };
  }
  if (isVideoAttachment(fileType)) {
    return { icon: 'video', color: 'text-purple-500' };
  }
  if (isAudioAttachment(fileType)) {
    return { icon: 'audio', color: 'text-pink-500' };
  }
  return FILE_TYPE_ICONS[fileType] || { icon: 'file', color: 'text-gray-500' };
}

/**
 * Parse and sanitize rich text content.
 * Converts basic markdown-like syntax to HTML.
 */
function parseRichText(content: string): string {
  // Escape HTML entities first for security
  let html = content
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  // Convert markdown-like syntax to HTML
  // Bold: **text** or __text__
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');

  // Italic: *text* or _text_
  html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

  // Links: [text](url)
  html = html.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer" class="underline hover:opacity-80">$1</a>'
  );

  // Line breaks
  html = html.replace(/\n/g, '<br />');

  // @mentions: @[Name] or @username - highlight them
  html = html.replace(
    /@\[([^\]]+)\]/g,
    '<span class="bg-primary/10 text-primary font-medium px-1 rounded">@$1</span>'
  );
  html = html.replace(
    /@([a-zA-Z0-9_]+)/g,
    '<span class="bg-primary/10 text-primary font-medium px-1 rounded">@$1</span>'
  );

  return html;
}

// ============================================================================
// Sub-components
// ============================================================================

/**
 * Sender type badge component.
 */
function SenderTypeBadge({ senderType }: { senderType: SenderType }) {
  const config = SENDER_TYPE_CONFIG[senderType];
  if (!config) return null;

  return (
    <span
      className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wide ${config.bgColor} ${config.textColor}`}
    >
      {config.label}
    </span>
  );
}

/**
 * File icon component for attachments.
 */
function FileIcon({
  type,
  className = 'h-5 w-5',
}: {
  type: string;
  className?: string;
}) {
  const config = getFileTypeConfig(type);

  switch (config.icon) {
    case 'image':
      return (
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      );
    case 'video':
      return (
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      );
    case 'audio':
      return (
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
          />
        </svg>
      );
    case 'pdf':
      return (
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
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
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
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
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
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
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
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
        <svg
          className={`${className} ${config.color}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
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

/**
 * Image attachment component with lightbox preview.
 */
function ImageAttachment({
  attachment,
  onClick,
  isCurrentUser,
}: {
  attachment: MessageAttachment;
  onClick?: () => void;
  isCurrentUser: boolean;
}) {
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  const handleLoad = useCallback(() => {
    setIsLoading(false);
  }, []);

  const handleError = useCallback(() => {
    setIsLoading(false);
    setHasError(true);
  }, []);

  if (hasError) {
    return (
      <div className="flex items-center space-x-2 rounded-lg bg-gray-100 p-3">
        <FileIcon type={attachment.fileType} />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium text-gray-700">
            {attachment.fileName}
          </p>
          <p className="text-xs text-red-500">Failed to load image</p>
        </div>
      </div>
    );
  }

  return (
    <div className="relative max-w-[280px] overflow-hidden rounded-lg">
      {isLoading && (
        <div className="absolute inset-0 flex items-center justify-center bg-gray-100">
          <svg
            className="h-6 w-6 animate-spin text-gray-400"
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
        </div>
      )}
      <button
        type="button"
        onClick={onClick}
        className="focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-lg"
        aria-label={`View ${attachment.fileName}`}
      >
        <img
          src={attachment.fileUrl}
          alt={attachment.fileName}
          className={`max-h-[200px] w-auto rounded-lg object-contain ${
            isLoading ? 'invisible' : 'visible'
          }`}
          onLoad={handleLoad}
          onError={handleError}
        />
      </button>
    </div>
  );
}

/**
 * Video attachment component with inline player.
 */
function VideoAttachment({
  attachment,
  isCurrentUser,
}: {
  attachment: MessageAttachment;
  isCurrentUser: boolean;
}) {
  const [hasError, setHasError] = useState(false);

  if (hasError) {
    return (
      <div className="flex items-center space-x-2 rounded-lg bg-gray-100 p-3">
        <FileIcon type={attachment.fileType} />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium text-gray-700">
            {attachment.fileName}
          </p>
          <p className="text-xs text-red-500">Failed to load video</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-[320px] overflow-hidden rounded-lg">
      <video
        src={attachment.fileUrl}
        controls
        preload="metadata"
        className="max-h-[240px] w-full rounded-lg"
        onError={() => setHasError(true)}
      >
        <track kind="captions" />
        Your browser does not support the video element.
      </video>
      <p
        className={`mt-1 truncate text-xs ${
          isCurrentUser ? 'text-white/70' : 'text-gray-500'
        }`}
        title={attachment.fileName}
      >
        {attachment.fileName}
        {attachment.fileSize && ` · ${formatFileSize(attachment.fileSize)}`}
      </p>
    </div>
  );
}

/**
 * Audio attachment component with inline player.
 */
function AudioAttachment({
  attachment,
  isCurrentUser,
}: {
  attachment: MessageAttachment;
  isCurrentUser: boolean;
}) {
  return (
    <div className="w-full max-w-[280px]">
      <div className="flex items-center space-x-2 mb-1">
        <FileIcon type={attachment.fileType} />
        <p
          className={`truncate text-sm font-medium ${
            isCurrentUser ? 'text-white' : 'text-gray-700'
          }`}
          title={attachment.fileName}
        >
          {attachment.fileName}
        </p>
      </div>
      <audio
        src={attachment.fileUrl}
        controls
        preload="metadata"
        className="w-full"
      >
        Your browser does not support the audio element.
      </audio>
    </div>
  );
}

/**
 * Generic file attachment component for non-media files.
 */
function FileAttachment({
  attachment,
  onClick,
  isCurrentUser,
}: {
  attachment: MessageAttachment;
  onClick?: () => void;
  isCurrentUser: boolean;
}) {
  const handleClick = () => {
    if (onClick) {
      onClick();
    } else {
      // Default behavior: open file in new tab
      window.open(attachment.fileUrl, '_blank', 'noopener,noreferrer');
    }
  };

  return (
    <button
      type="button"
      onClick={handleClick}
      className={`flex w-full items-center space-x-3 rounded-lg border p-3 text-left transition-colors ${
        isCurrentUser
          ? 'border-white/20 bg-white/10 hover:bg-white/20'
          : 'border-gray-200 bg-gray-50 hover:bg-gray-100'
      }`}
    >
      <div
        className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg ${
          isCurrentUser ? 'bg-white/20' : 'bg-white'
        }`}
      >
        <FileIcon type={attachment.fileType} />
      </div>
      <div className="min-w-0 flex-1">
        <p
          className={`truncate text-sm font-medium ${
            isCurrentUser ? 'text-white' : 'text-gray-700'
          }`}
          title={attachment.fileName}
        >
          {attachment.fileName}
        </p>
        <p
          className={`text-xs ${
            isCurrentUser ? 'text-white/70' : 'text-gray-500'
          }`}
        >
          {formatFileSize(attachment.fileSize)}
          {attachment.fileSize ? ' · ' : ''}
          Click to download
        </p>
      </div>
      <svg
        className={`h-5 w-5 flex-shrink-0 ${
          isCurrentUser ? 'text-white/70' : 'text-gray-400'
        }`}
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
        />
      </svg>
    </button>
  );
}

/**
 * Attachments container component.
 */
function AttachmentsSection({
  attachments,
  isCurrentUser,
  onImageClick,
  onFileClick,
}: {
  attachments: MessageAttachment[];
  isCurrentUser: boolean;
  onImageClick?: (attachment: MessageAttachment) => void;
  onFileClick?: (attachment: MessageAttachment) => void;
}) {
  if (!attachments || attachments.length === 0) return null;

  // Group attachments by type
  const imageAttachments = attachments.filter((a) =>
    isImageAttachment(a.fileType)
  );
  const videoAttachments = attachments.filter((a) =>
    isVideoAttachment(a.fileType)
  );
  const audioAttachments = attachments.filter((a) =>
    isAudioAttachment(a.fileType)
  );
  const fileAttachments = attachments.filter(
    (a) =>
      !isImageAttachment(a.fileType) &&
      !isVideoAttachment(a.fileType) &&
      !isAudioAttachment(a.fileType)
  );

  return (
    <div className="mt-2 space-y-2">
      {/* Images - show in a grid if multiple */}
      {imageAttachments.length > 0 && (
        <div
          className={`flex flex-wrap gap-2 ${
            imageAttachments.length > 1 ? 'grid grid-cols-2' : ''
          }`}
        >
          {imageAttachments.map((attachment) => (
            <ImageAttachment
              key={attachment.id}
              attachment={attachment}
              onClick={() => onImageClick?.(attachment)}
              isCurrentUser={isCurrentUser}
            />
          ))}
        </div>
      )}

      {/* Videos */}
      {videoAttachments.map((attachment) => (
        <VideoAttachment
          key={attachment.id}
          attachment={attachment}
          isCurrentUser={isCurrentUser}
        />
      ))}

      {/* Audio */}
      {audioAttachments.map((attachment) => (
        <AudioAttachment
          key={attachment.id}
          attachment={attachment}
          isCurrentUser={isCurrentUser}
        />
      ))}

      {/* Files */}
      {fileAttachments.map((attachment) => (
        <FileAttachment
          key={attachment.id}
          attachment={attachment}
          onClick={() => onFileClick?.(attachment)}
          isCurrentUser={isCurrentUser}
        />
      ))}
    </div>
  );
}

// ============================================================================
// Main Component
// ============================================================================

/**
 * MessageBubble component for displaying individual messages.
 * Supports rich text content, attachments (images, videos, files),
 * and sender type badges.
 */
export function MessageBubble({
  message,
  isCurrentUser,
  onImageClick,
  onFileClick,
}: MessageBubbleProps) {
  const {
    senderName,
    senderType,
    content,
    contentType = 'text',
    timestamp,
    read,
    attachments,
  } = message;

  // Determine if we should render rich text
  const isRichText = contentType === 'rich_text';

  return (
    <div
      className={`flex ${isCurrentUser ? 'justify-end' : 'justify-start'} mb-4`}
    >
      <div
        className={`max-w-[75%] ${
          isCurrentUser
            ? 'bg-primary text-white rounded-l-2xl rounded-tr-2xl'
            : 'bg-gray-100 text-gray-900 rounded-r-2xl rounded-tl-2xl'
        } px-4 py-3`}
      >
        {/* Sender info with type badge */}
        {!isCurrentUser && (
          <div className="flex items-center space-x-2 mb-1">
            <p className="text-xs font-medium text-gray-600">{senderName}</p>
            {senderType && <SenderTypeBadge senderType={senderType} />}
          </div>
        )}

        {/* Message content */}
        {content && (
          <>
            {isRichText ? (
              <div
                className={`text-sm prose prose-sm max-w-none ${
                  isCurrentUser
                    ? 'text-white prose-invert prose-a:text-white prose-strong:text-white prose-em:text-white'
                    : 'text-gray-900'
                }`}
                dangerouslySetInnerHTML={{ __html: parseRichText(content) }}
              />
            ) : (
              <p
                className={`text-sm whitespace-pre-wrap ${
                  isCurrentUser ? 'text-white' : 'text-gray-900'
                }`}
              >
                {content}
              </p>
            )}
          </>
        )}

        {/* Attachments */}
        <AttachmentsSection
          attachments={attachments || []}
          isCurrentUser={isCurrentUser}
          onImageClick={onImageClick}
          onFileClick={onFileClick}
        />

        {/* Timestamp and read status */}
        <div className="flex items-center justify-end mt-1 space-x-1">
          <span
            className={`text-xs ${
              isCurrentUser ? 'text-white/70' : 'text-gray-500'
            }`}
          >
            {formatTime(timestamp)}
          </span>
          {isCurrentUser && (
            <span className="text-xs">
              {read ? (
                <svg
                  className="h-4 w-4 text-white/70"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth={2}
                >
                  {/* Double check mark for read */}
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 13l4 4L19 7"
                  />
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M1 13l4 4L15 7"
                    className="translate-x-3"
                  />
                </svg>
              ) : (
                <svg
                  className="h-4 w-4 text-white/50"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth={2}
                >
                  {/* Single check mark for sent */}
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
              )}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

// Export date formatting helpers for use in thread headers
export { formatDate, formatTime };

// Export types for external use
export type { MessageBubbleMessage, MessageBubbleProps };
