import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { OptimizedImage } from '../components/OptimizedImage';

// Mock next/image
vi.mock('next/image', () => ({
  default: ({ src, alt, onLoadingComplete, onError, ...props }: any) => {
    // Simulate image load after mount
    setTimeout(() => {
      if (src.includes('error')) {
        onError?.();
      } else {
        onLoadingComplete?.();
      }
    }, 100);

    return (
      <img
        src={src}
        alt={alt}
        data-testid="next-image"
        {...props}
      />
    );
  },
}));

describe('OptimizedImage', () => {
  it('should render image with correct props', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toBeInTheDocument();
    expect(image).toHaveAttribute('src', '/test-image.jpg');
    expect(image).toHaveAttribute('alt', 'Test image');
    expect(image).toHaveAttribute('width', '400');
    expect(image).toHaveAttribute('height', '300');
  });

  it('should default to lazy loading when priority is false', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        priority={false}
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toHaveAttribute('loading', 'lazy');
  });

  it('should not have loading attribute when priority is true', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        priority={true}
      />
    );

    const image = screen.getByTestId('next-image');
    // When priority is true, loading attribute should be undefined
    expect(image).not.toHaveAttribute('loading', 'lazy');
  });

  it('should show loading skeleton initially when showSkeleton is true', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        showSkeleton={true}
      />
    );

    const skeleton = screen.getByLabelText('Loading image');
    expect(skeleton).toBeInTheDocument();
    expect(skeleton).toHaveClass('bg-gray-200', 'animate-pulse');
  });

  it('should hide skeleton after image loads', async () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        showSkeleton={true}
      />
    );

    const skeleton = screen.getByLabelText('Loading image');
    expect(skeleton).toBeInTheDocument();

    // Wait for image to load
    await waitFor(
      () => {
        expect(screen.queryByLabelText('Loading image')).not.toBeInTheDocument();
      },
      { timeout: 200 }
    );
  });

  it('should not show skeleton when showSkeleton is false', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        showSkeleton={false}
      />
    );

    expect(screen.queryByLabelText('Loading image')).not.toBeInTheDocument();
  });

  it('should use fallback image on error', async () => {
    render(
      <OptimizedImage
        src="/error-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        fallbackSrc="/fallback.jpg"
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toHaveAttribute('src', '/error-image.jpg');

    // Wait for error and fallback
    await waitFor(
      () => {
        expect(image).toHaveAttribute('src', '/fallback.jpg');
      },
      { timeout: 200 }
    );
  });

  it('should show error message when image fails and errorMessage is provided', async () => {
    render(
      <OptimizedImage
        src="/error-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        errorMessage="Failed to load image"
      />
    );

    // Wait for error
    await waitFor(
      () => {
        expect(screen.getByText('Failed to load image')).toBeInTheDocument();
      },
      { timeout: 200 }
    );
  });

  it('should apply custom className to image', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        className="custom-class rounded-lg"
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toHaveClass('custom-class', 'rounded-lg');
  });

  it('should apply custom skeleton className', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        showSkeleton={true}
        skeletonClassName="custom-skeleton"
      />
    );

    const skeleton = screen.getByLabelText('Loading image');
    expect(skeleton).toHaveClass('custom-skeleton');
  });

  it('should handle fill prop correctly', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        fill={true}
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toHaveAttribute('fill');
  });

  it('should pass through additional props to Image component', () => {
    render(
      <OptimizedImage
        src="/test-image.jpg"
        alt="Test image"
        width={400}
        height={300}
        sizes="(max-width: 768px) 100vw, 50vw"
        quality={90}
      />
    );

    const image = screen.getByTestId('next-image');
    expect(image).toHaveAttribute('sizes', '(max-width: 768px) 100vw, 50vw');
    expect(image).toHaveAttribute('quality', '90');
  });
});
