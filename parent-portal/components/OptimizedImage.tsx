'use client';

import Image, { ImageProps } from 'next/image';
import { useState } from 'react';

/**
 * OptimizedImage Component
 *
 * A wrapper around next/image that enforces lazy loading best practices
 * and provides loading states for better UX.
 *
 * Features:
 * - Automatic lazy loading (default)
 * - Loading skeleton/placeholder
 * - Error handling with fallback
 * - Blur placeholder support
 * - Responsive sizing
 *
 * @example
 * ```tsx
 * <OptimizedImage
 *   src="/photo.jpg"
 *   alt="Child activity"
 *   width={400}
 *   height={300}
 *   priority={false} // Lazy load by default
 * />
 * ```
 */

interface OptimizedImageProps extends Omit<ImageProps, 'onLoadingComplete' | 'onError'> {
  /** Fallback image URL when loading fails */
  fallbackSrc?: string;
  /** Show loading skeleton */
  showSkeleton?: boolean;
  /** Custom skeleton className */
  skeletonClassName?: string;
  /** Custom error message */
  errorMessage?: string;
}

export function OptimizedImage({
  src,
  alt,
  fallbackSrc = '/placeholder-image.jpg',
  showSkeleton = true,
  skeletonClassName = 'bg-gray-200 animate-pulse',
  errorMessage,
  className,
  priority = false, // Default to lazy loading
  loading = 'lazy', // Explicit lazy loading
  ...props
}: OptimizedImageProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);
  const [imageSrc, setImageSrc] = useState(src);

  const handleLoadingComplete = () => {
    setIsLoading(false);
    setHasError(false);
  };

  const handleError = () => {
    setIsLoading(false);
    setHasError(true);
    if (fallbackSrc && imageSrc !== fallbackSrc) {
      setImageSrc(fallbackSrc);
    }
  };

  return (
    <div className="relative overflow-hidden">
      {/* Loading skeleton */}
      {isLoading && showSkeleton && (
        <div
          className={`absolute inset-0 ${skeletonClassName}`}
          aria-label="Loading image"
        />
      )}

      {/* Error state */}
      {hasError && errorMessage && (
        <div className="absolute inset-0 flex items-center justify-center bg-gray-100 text-gray-500 text-sm p-2">
          {errorMessage}
        </div>
      )}

      {/* Optimized image */}
      <Image
        src={imageSrc}
        alt={alt}
        className={className}
        priority={priority}
        loading={priority ? undefined : loading}
        onLoadingComplete={handleLoadingComplete}
        onError={handleError}
        {...props}
      />
    </div>
  );
}
