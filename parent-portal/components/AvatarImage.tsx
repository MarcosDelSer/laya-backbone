'use client';

import Image from 'next/image';
import { useState } from 'react';

/**
 * AvatarImage Component
 *
 * Optimized image component specifically for user avatars and profile pictures.
 * Includes fallback to initials when image fails to load.
 *
 * Features:
 * - Lazy loading by default
 * - Circular/rounded variants
 * - Fallback to initials
 * - Multiple sizes
 * - Error handling
 *
 * @example
 * ```tsx
 * <AvatarImage
 *   src="/avatar.jpg"
 *   alt="Emma Johnson"
 *   name="Emma Johnson"
 *   size="md"
 * />
 * ```
 */

interface AvatarImageProps {
  /** Image source URL */
  src?: string;
  /** Alt text for accessibility */
  alt: string;
  /** Name for initials fallback */
  name: string;
  /** Avatar size preset */
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  /** Shape of avatar */
  variant?: 'circle' | 'rounded';
  /** Priority loading (above-the-fold) */
  priority?: boolean;
  /** Custom className */
  className?: string;
}

const sizeMap = {
  xs: { dimension: 24, text: 'text-xs' },
  sm: { dimension: 32, text: 'text-sm' },
  md: { dimension: 48, text: 'text-base' },
  lg: { dimension: 64, text: 'text-lg' },
  xl: { dimension: 96, text: 'text-2xl' },
  '2xl': { dimension: 128, text: 'text-4xl' },
};

const variantMap = {
  circle: 'rounded-full',
  rounded: 'rounded-lg',
};

/**
 * Extract initials from a name
 */
function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) {
    return parts[0].substring(0, 2).toUpperCase();
  }
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

/**
 * Generate a consistent color from a string
 */
function getColorFromName(name: string): string {
  const colors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-yellow-500',
    'bg-red-500',
    'bg-purple-500',
    'bg-pink-500',
    'bg-indigo-500',
    'bg-teal-500',
  ];
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
}

export function AvatarImage({
  src,
  alt,
  name,
  size = 'md',
  variant = 'circle',
  priority = false,
  className = '',
}: AvatarImageProps) {
  const [hasError, setHasError] = useState(false);
  const { dimension, text } = sizeMap[size];
  const variantClass = variantMap[variant];
  const showImage = src && !hasError;

  const initials = getInitials(name);
  const bgColor = getColorFromName(name);

  const containerClass = `relative flex items-center justify-center ${variantClass} overflow-hidden ${className}`;

  if (!showImage) {
    // Fallback to initials
    return (
      <div
        className={`${containerClass} ${bgColor} text-white font-semibold ${text}`}
        style={{ width: dimension, height: dimension }}
        aria-label={alt}
      >
        {initials}
      </div>
    );
  }

  return (
    <div
      className={containerClass}
      style={{ width: dimension, height: dimension }}
    >
      <Image
        src={src}
        alt={alt}
        width={dimension}
        height={dimension}
        className={`object-cover ${variantClass}`}
        priority={priority}
        loading={priority ? undefined : 'lazy'}
        onError={() => setHasError(true)}
        sizes={`${dimension}px`}
      />
    </div>
  );
}
