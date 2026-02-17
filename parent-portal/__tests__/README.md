# Image Optimization Tests

## Test Files

- `OptimizedImage.test.tsx` - Tests for the OptimizedImage component
- `AvatarImage.test.tsx` - Tests for the AvatarImage component

## Running Tests

### Run all tests:
```bash
npm run test
```

### Run specific test file:
```bash
npm run test OptimizedImage.test.tsx
npm run test AvatarImage.test.tsx
```

### Run with coverage:
```bash
npm run test:coverage
```

## Test Coverage

The test suite covers:

### OptimizedImage Component
- ✅ Image rendering with correct props
- ✅ Lazy loading behavior (default)
- ✅ Priority loading (above-fold)
- ✅ Loading skeleton visibility
- ✅ Error handling with fallback images
- ✅ Custom className application
- ✅ Error message display
- ✅ Fill prop handling
- ✅ Pass-through of additional props

### AvatarImage Component
- ✅ Image rendering with correct dimensions
- ✅ Size variants (xs, sm, md, lg, xl, 2xl)
- ✅ Variant shapes (circle, rounded)
- ✅ Lazy loading by default
- ✅ Priority loading option
- ✅ Initials fallback when no image
- ✅ Initials extraction from names
- ✅ Error handling (fallback to initials)
- ✅ Consistent color generation
- ✅ Accessibility attributes
- ✅ Custom className application

## Expected Coverage

- **Statements:** >80%
- **Branches:** >80%
- **Functions:** >80%
- **Lines:** >80%

## Manual Testing Checklist

After running automated tests, manually verify:

- [ ] Images lazy load as you scroll down the page
- [ ] Loading skeletons appear during image load
- [ ] Above-fold images with `priority={true}` load immediately
- [ ] Failed images show fallback image
- [ ] Avatar images show initials when image fails
- [ ] No Cumulative Layout Shift (images reserve space)
- [ ] Images are responsive at different viewport sizes
- [ ] WebP/AVIF images served on supported browsers (check Network tab)
- [ ] Browser DevTools shows loading="lazy" on images
- [ ] Lighthouse score improves (LCP, CLS metrics)

## Troubleshooting

### Tests fail with "Cannot find module 'next/image'"

Make sure Next.js is installed:
```bash
npm install next@14.2.20
```

### Tests timeout

Increase timeout in vitest.config.ts:
```typescript
export default defineConfig({
  test: {
    testTimeout: 10000,
  },
});
```

### Mock not working

Ensure the mock is at the top of the test file:
```typescript
vi.mock('next/image', () => ({
  default: (props: any) => <img {...props} />,
}));
```
