'use client';

import { useTranslations } from 'next-intl';
import { useFormatting } from '@/lib/hooks/useFormatting';

interface MessageBubbleProps {
  message: {
    id: string;
    senderId: string;
    senderName: string;
    content: string;
    timestamp: string;
    read: boolean;
  };
  isCurrentUser: boolean;
}

export function MessageBubble({ message, isCurrentUser }: MessageBubbleProps) {
  const t = useTranslations();
  const { formatTime } = useFormatting();

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
        {!isCurrentUser && (
          <p className="text-xs font-medium text-gray-600 mb-1">
            {message.senderName}
          </p>
        )}
        <p className={`text-sm ${isCurrentUser ? 'text-white' : 'text-gray-900'}`}>
          {message.content}
        </p>
        <div className={`flex items-center justify-end mt-1 space-x-1`}>
          <span
            className={`text-xs ${
              isCurrentUser ? 'text-white/70' : 'text-gray-500'
            }`}
          >
            {formatTime(message.timestamp, 'short')}
          </span>
          {isCurrentUser && (
            <span className="text-xs">
              {message.read ? (
                <svg
                  className="h-4 w-4 text-white/70"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth={2}
                >
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
