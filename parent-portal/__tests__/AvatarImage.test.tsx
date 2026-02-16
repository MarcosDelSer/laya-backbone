import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { AvatarImage } from '../components/AvatarImage';

// Mock next/image
vi.mock('next/image', () => ({
  default: ({ src, alt, onError, ...props }: any) => {
    // Simulate error for specific test URLs
    if (src === '/error.jpg') {
      setTimeout(() => onError?.(), 0);
    }

    return (
      <img
        src={src}
        alt={alt}
        data-testid="avatar-image"
        {...props}
      />
    );
  },
}));

describe('AvatarImage', () => {
  describe('Rendering with image', () => {
    it('should render image when src is provided', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toBeInTheDocument();
      expect(image).toHaveAttribute('src', '/avatar.jpg');
      expect(image).toHaveAttribute('alt', 'Emma Johnson');
    });

    it('should have correct dimensions for medium size', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
          size="md"
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toHaveAttribute('width', '48');
      expect(image).toHaveAttribute('height', '48');
    });

    it('should have correct dimensions for different sizes', () => {
      const sizes = {
        xs: 24,
        sm: 32,
        md: 48,
        lg: 64,
        xl: 96,
        '2xl': 128,
      };

      Object.entries(sizes).forEach(([size, dimension]) => {
        const { unmount } = render(
          <AvatarImage
            src="/avatar.jpg"
            alt="Test"
            name="Test User"
            size={size as any}
          />
        );

        const image = screen.getByTestId('avatar-image');
        expect(image).toHaveAttribute('width', dimension.toString());
        expect(image).toHaveAttribute('height', dimension.toString());

        unmount();
      });
    });

    it('should be circular by default', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toHaveClass('rounded-full');
    });

    it('should be rounded when variant is rounded', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
          variant="rounded"
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toHaveClass('rounded-lg');
    });

    it('should lazy load by default when priority is false', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
          priority={false}
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toHaveAttribute('loading', 'lazy');
    });

    it('should not have loading attribute when priority is true', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
          priority={true}
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).not.toHaveAttribute('loading', 'lazy');
    });
  });

  describe('Fallback to initials', () => {
    it('should show initials when no src is provided', () => {
      render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      // Should show initials, not image
      expect(screen.queryByTestId('avatar-image')).not.toBeInTheDocument();
      expect(screen.getByText('EJ')).toBeInTheDocument();
    });

    it('should extract correct initials from two-word name', () => {
      render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      expect(screen.getByText('EJ')).toBeInTheDocument();
    });

    it('should extract correct initials from single-word name', () => {
      render(
        <AvatarImage
          alt="Madonna"
          name="Madonna"
        />
      );

      expect(screen.getByText('MA')).toBeInTheDocument();
    });

    it('should extract correct initials from multi-word name', () => {
      render(
        <AvatarImage
          alt="Mary Jane Watson"
          name="Mary Jane Watson"
        />
      );

      // Should use first and last word
      expect(screen.getByText('MW')).toBeInTheDocument();
    });

    it('should handle names with extra spaces', () => {
      render(
        <AvatarImage
          alt="Emma  Johnson  "
          name="Emma  Johnson  "
        />
      );

      expect(screen.getByText('EJ')).toBeInTheDocument();
    });

    it('should uppercase initials', () => {
      render(
        <AvatarImage
          alt="emma johnson"
          name="emma johnson"
        />
      );

      expect(screen.getByText('EJ')).toBeInTheDocument();
    });

    it('should have consistent background color for same name', () => {
      const { container: container1 } = render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      const { container: container2 } = render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      const div1 = container1.querySelector('div');
      const div2 = container2.querySelector('div');

      // Both should have the same background color class
      expect(div1?.className).toContain('bg-');
      expect(div1?.className).toBe(div2?.className);
    });

    it('should show initials when image fails to load', async () => {
      render(
        <AvatarImage
          src="/error.jpg"
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      // Initially shows image
      expect(screen.queryByTestId('avatar-image')).toBeInTheDocument();

      // After error, should show initials
      await vi.waitFor(() => {
        expect(screen.queryByTestId('avatar-image')).not.toBeInTheDocument();
        expect(screen.getByText('EJ')).toBeInTheDocument();
      });
    });

    it('should apply correct text size for different avatar sizes', () => {
      const sizes = {
        xs: 'text-xs',
        sm: 'text-sm',
        md: 'text-base',
        lg: 'text-lg',
        xl: 'text-2xl',
        '2xl': 'text-4xl',
      };

      Object.entries(sizes).forEach(([size, textClass]) => {
        const { container, unmount } = render(
          <AvatarImage
            alt="Test User"
            name="Test User"
            size={size as any}
          />
        );

        const div = container.querySelector('div');
        expect(div).toHaveClass(textClass);

        unmount();
      });
    });
  });

  describe('Accessibility', () => {
    it('should have proper aria-label when showing initials', () => {
      render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
        />
      );

      const avatar = screen.getByLabelText('Emma Johnson');
      expect(avatar).toBeInTheDocument();
    });

    it('should have proper alt text on image', () => {
      render(
        <AvatarImage
          src="/avatar.jpg"
          alt="Profile picture of Emma Johnson"
          name="Emma Johnson"
        />
      );

      const image = screen.getByTestId('avatar-image');
      expect(image).toHaveAttribute('alt', 'Profile picture of Emma Johnson');
    });
  });

  describe('Custom styling', () => {
    it('should apply custom className', () => {
      const { container } = render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
          className="custom-class border-2"
        />
      );

      const div = container.querySelector('div');
      expect(div).toHaveClass('custom-class', 'border-2');
    });

    it('should maintain variant class with custom className', () => {
      const { container } = render(
        <AvatarImage
          alt="Emma Johnson"
          name="Emma Johnson"
          className="custom-class"
          variant="rounded"
        />
      );

      const div = container.querySelector('div');
      expect(div).toHaveClass('custom-class', 'rounded-lg');
    });
  });
});
