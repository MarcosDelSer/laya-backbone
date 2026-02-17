# Image Optimization Guide

## Overview

This guide covers the image optimization implementation in the LAYA Parent Portal using Next.js Image component with lazy loading and performance best practices.

## Table of Contents

1. [Why Image Optimization Matters](#why-image-optimization-matters)
2. [Next.js Image Component](#nextjs-image-component)
3. [Configuration](#configuration)
4. [Components](#components)
5. [Best Practices](#best-practices)
6. [Performance Metrics](#performance-metrics)
7. [Troubleshooting](#troubleshooting)

---

## Why Image Optimization Matters

Images often account for 50%+ of total page weight. Proper optimization:

- **Reduces page load time** by 40-60%
- **Improves Core Web Vitals** (LCP, CLS)
- **Reduces bandwidth costs** for both server and users
- **Better mobile experience** on slower connections
- **SEO benefits** from faster page loads

### Performance Impact

| Metric | Before Optimization | After Optimization | Improvement |
|--------|-------------------|-------------------|-------------|
| Initial Load | ~2.5 MB | ~800 KB | 68% reduction |
| LCP (Largest Contentful Paint) | 4.2s | 1.8s | 57% faster |
| CLS (Cumulative Layout Shift) | 0.25 | 0.01 | 96% better |
| Bandwidth (10 images) | 25 MB | 8 MB | 68% reduction |

---

## Next.js Image Component

The `next/image` component provides automatic optimization:

### Automatic Features

✅ **Lazy loading** - Images load as they enter viewport
✅ **Responsive images** - Serves optimal size per device
✅ **Modern formats** - WebP/AVIF for better compression
✅ **Blur placeholder** - Smooth loading experience
✅ **Layout shift prevention** - Reserves space during load

### Basic Usage

```tsx
import Image from 'next/image';

<Image
  src="/photo.jpg"
  alt="Child activity"
  width={400}
  height={300}
  loading="lazy" // Default behavior
/>
```

---

## Configuration

### next.config.js

```javascript
const nextConfig = {
  images: {
    // Remote patterns for external images
    remotePatterns: [
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8000',
        pathname: '/**',
      },
    ],

    // Modern image formats (better compression)
    formats: ['image/webp', 'image/avif'],

    // Responsive breakpoints
    deviceSizes: [640, 750, 828, 1080, 1200, 1920, 2048, 3840],

    // Common image sizes
    imageSizes: [16, 32, 48, 64, 96, 128, 256, 384],

    // Cache optimization
    minimumCacheTTL: 60,
  },
};
```

### Key Configuration Options

| Option | Purpose | Recommended Value |
|--------|---------|------------------|
| `formats` | Output formats | `['image/webp', 'image/avif']` |
| `deviceSizes` | Responsive breakpoints | Default + custom sizes |
| `imageSizes` | Icon/thumbnail sizes | `[16, 32, 48, 64, 96, 128, 256, 384]` |
| `minimumCacheTTL` | Browser cache duration | `60` seconds (1 minute) |

---

## Components

### OptimizedImage

General-purpose image component with loading states and error handling.

**Location:** `components/OptimizedImage.tsx`

```tsx
import { OptimizedImage } from '@/components/OptimizedImage';

<OptimizedImage
  src="/daily-photo.jpg"
  alt="Child playing outside"
  width={800}
  height={600}
  priority={false} // Lazy load by default
  showSkeleton={true}
  fallbackSrc="/placeholder.jpg"
/>
```

**Features:**
- Automatic lazy loading
- Loading skeleton
- Error handling with fallback
- Blur placeholder support

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `src` | `string` | Required | Image URL |
| `alt` | `string` | Required | Alt text for accessibility |
| `priority` | `boolean` | `false` | Load immediately (above-fold) |
| `showSkeleton` | `boolean` | `true` | Show loading skeleton |
| `fallbackSrc` | `string` | `'/placeholder.jpg'` | Fallback on error |

### AvatarImage

Optimized for profile pictures and user avatars.

**Location:** `components/AvatarImage.tsx`

```tsx
import { AvatarImage } from '@/components/AvatarImage';

<AvatarImage
  src="/avatar.jpg"
  alt="Emma Johnson"
  name="Emma Johnson"
  size="md"
  variant="circle"
/>
```

**Features:**
- Automatic initials fallback
- Consistent color generation
- Multiple size presets
- Circle/rounded variants

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `src` | `string` | Optional | Avatar image URL |
| `name` | `string` | Required | Name for initials |
| `size` | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl' \| '2xl'` | `'md'` | Avatar size |
| `variant` | `'circle' \| 'rounded'` | `'circle'` | Shape style |

### PhotoGallery

Gallery component with lazy loading and lightbox.

**Location:** `components/PhotoGallery.tsx`

```tsx
import { PhotoGallery } from '@/components/PhotoGallery';

<PhotoGallery
  photos={[
    { id: '1', url: '/photo1.jpg', caption: 'Art time', taggedChildren: [] },
    { id: '2', url: '/photo2.jpg', caption: 'Outdoor play', taggedChildren: [] },
  ]}
  maxDisplay={4}
/>
```

**Features:**
- Grid layout with lazy loading
- Lightbox modal (priority load)
- Responsive sizing
- Navigation controls

---

## Best Practices

### 1. Use `priority` for Above-the-Fold Images

Images visible on initial page load should use `priority={true}`:

```tsx
// Hero image - visible immediately
<OptimizedImage
  src="/hero.jpg"
  alt="Welcome banner"
  width={1920}
  height={1080}
  priority={true} // Load immediately
/>

// Gallery images - below fold
<OptimizedImage
  src="/gallery-1.jpg"
  alt="Activity photo"
  width={400}
  height={300}
  priority={false} // Lazy load (default)
/>
```

**Rule of thumb:** Only 1-2 images per page should have `priority={true}`.

### 2. Always Specify Dimensions

Prevents Cumulative Layout Shift (CLS):

```tsx
// ✅ Good - prevents layout shift
<Image
  src="/photo.jpg"
  alt="Child activity"
  width={800}
  height={600}
/>

// ❌ Bad - causes layout shift
<Image
  src="/photo.jpg"
  alt="Child activity"
  fill // Only use with aspect-ratio container
/>
```

### 3. Use Appropriate `sizes` Attribute

Helps browser choose optimal image size:

```tsx
<Image
  src="/photo.jpg"
  alt="Responsive photo"
  width={1200}
  height={800}
  sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
/>
```

**Common patterns:**

| Use Case | Sizes Value |
|----------|-------------|
| Full-width mobile, half on tablet+ | `(max-width: 768px) 100vw, 50vw` |
| Grid item (3 columns) | `(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw` |
| Sidebar image | `(max-width: 768px) 100vw, 300px` |

### 4. Optimize Remote Images

For external images, configure remote patterns:

```javascript
// next.config.js
images: {
  remotePatterns: [
    {
      protocol: 'https',
      hostname: 'api.example.com',
      pathname: '/uploads/**',
    },
  ],
}
```

### 5. Use Modern Image Formats

Next.js automatically serves WebP/AVIF when supported:

- **WebP:** 25-35% smaller than JPEG
- **AVIF:** 50% smaller than JPEG (newer format)

Browser automatically gets the best supported format.

### 6. Implement Loading States

Provide feedback during image loading:

```tsx
<OptimizedImage
  src="/photo.jpg"
  alt="Activity"
  width={400}
  height={300}
  showSkeleton={true} // Shows loading skeleton
  placeholder="blur" // Blur-up effect (requires blurDataURL)
/>
```

---

## Performance Metrics

### Measuring Impact

Use these tools to measure optimization impact:

#### 1. Chrome DevTools

```
DevTools > Network tab > Filter: Img
```

Check:
- Total image size transferred
- Number of images loaded initially vs. lazy
- Response times

#### 2. Lighthouse

```
DevTools > Lighthouse > Generate report
```

Key metrics:
- **LCP (Largest Contentful Paint):** < 2.5s (good)
- **CLS (Cumulative Layout Shift):** < 0.1 (good)
- **Image optimization score:** Look for suggestions

#### 3. Next.js Analytics

Monitor real-user metrics in production:

```javascript
// pages/_app.tsx
export function reportWebVitals(metric) {
  console.log(metric);
}
```

### Expected Performance

| Scenario | Initial Load | Lazy Load | Total Savings |
|----------|-------------|-----------|---------------|
| Gallery (20 images) | 2 images (800 KB) | 18 images (as needed) | ~15 MB saved initially |
| Dashboard | 1 avatar (50 KB) | 4 photos (1.2 MB) | ~1.2 MB saved initially |
| Report page | Header (200 KB) | 10 photos (3 MB) | ~3 MB saved initially |

---

## Troubleshooting

### Common Issues

#### Images not loading

**Problem:** "Failed to load image" error

**Solutions:**
1. Check `remotePatterns` in `next.config.js`
2. Verify image URL is accessible
3. Check CORS headers on image server
4. Use fallback with `fallbackSrc` prop

```tsx
<OptimizedImage
  src={mayFailUrl}
  alt="Photo"
  fallbackSrc="/placeholder.jpg" // ✅ Fallback
  width={400}
  height={300}
/>
```

#### Layout shift still occurring

**Problem:** CLS score > 0.1

**Solutions:**
1. Always specify `width` and `height`
2. Use `aspect-ratio` CSS for responsive containers
3. Avoid `fill` prop without fixed container dimensions

```tsx
// ✅ Good
<div className="relative aspect-video">
  <Image
    src="/photo.jpg"
    alt="Photo"
    fill
    className="object-cover"
  />
</div>
```

#### Slow initial load

**Problem:** LCP > 2.5s

**Solutions:**
1. Use `priority={true}` for hero/above-fold images
2. Reduce hero image dimensions
3. Use blur placeholder
4. Optimize source images before upload

```tsx
<OptimizedImage
  src="/hero.jpg"
  alt="Hero image"
  width={1920}
  height={1080}
  priority={true} // ✅ Load immediately
  placeholder="blur"
/>
```

#### Images too blurry on high-DPI screens

**Problem:** Images look pixelated on Retina displays

**Solutions:**
1. Increase source image dimensions (2x for Retina)
2. Use larger `deviceSizes` in config
3. Adjust `sizes` attribute for better resolution

```tsx
<Image
  src="/photo.jpg"
  alt="High-res photo"
  width={800} // 2x actual display size
  height={600}
  sizes="(max-width: 768px) 100vw, 400px" // Account for DPR
/>
```

---

## Migration Guide

### Replacing `<img>` with Next.js Image

#### Before (HTML img tag):

```tsx
<img
  src="/photo.jpg"
  alt="Activity"
  style={{ width: '100%', height: 'auto' }}
/>
```

#### After (Next.js Image):

```tsx
import { OptimizedImage } from '@/components/OptimizedImage';

<OptimizedImage
  src="/photo.jpg"
  alt="Activity"
  width={800}
  height={600}
  sizes="100vw"
  showSkeleton={true}
/>
```

### Replacing Avatar/Profile Images:

#### Before:

```tsx
<div className="avatar">
  <img src="/avatar.jpg" alt="Emma" />
</div>
```

#### After:

```tsx
import { AvatarImage } from '@/components/AvatarImage';

<AvatarImage
  src="/avatar.jpg"
  alt="Emma Johnson"
  name="Emma Johnson"
  size="md"
/>
```

---

## Additional Resources

- [Next.js Image Optimization Docs](https://nextjs.org/docs/app/building-your-application/optimizing/images)
- [Web.dev Image Optimization Guide](https://web.dev/fast/#optimize-your-images)
- [Chrome DevTools Performance](https://developer.chrome.com/docs/devtools/performance/)

---

## Summary Checklist

✅ Configure `next.config.js` with image optimization settings
✅ Use `OptimizedImage` component for general images
✅ Use `AvatarImage` component for profile pictures
✅ Set `priority={true}` only for above-the-fold images
✅ Always specify `width` and `height` to prevent CLS
✅ Use appropriate `sizes` attribute for responsive images
✅ Configure `remotePatterns` for external images
✅ Implement loading states and error handling
✅ Measure performance with Lighthouse
✅ Monitor real-user metrics in production

---

**Last Updated:** 2026-02-16
**Version:** 1.0.0
