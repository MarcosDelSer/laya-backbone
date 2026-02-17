'use client';

import { useState, useCallback, useEffect } from 'react';
import Image from 'next/image';
import { useEscapeKey, useFocusTrap } from '../hooks';

interface Photo {
  id: string;
  url: string;
  caption: string;
  taggedChildren: string[];
}

interface PhotoGalleryProps {
  photos: Photo[];
  maxDisplay?: number;
}

export function PhotoGallery({ photos, maxDisplay = 4 }: PhotoGalleryProps) {
  const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
  const modalRef = useFocusTrap<HTMLDivElement>(selectedPhoto !== null);

  const closeModal = useCallback(() => {
    setSelectedPhoto(null);
  }, []);

  const navigatePhoto = useCallback(
    (direction: 'prev' | 'next') => {
      if (!selectedPhoto) return;

      const currentIndex = photos.findIndex((p) => p.id === selectedPhoto.id);
      let newIndex: number;

      if (direction === 'next') {
        newIndex = currentIndex + 1 >= photos.length ? 0 : currentIndex + 1;
      } else {
        newIndex = currentIndex - 1 < 0 ? photos.length - 1 : currentIndex - 1;
      }

      setSelectedPhoto(photos[newIndex]);
    },
    [selectedPhoto, photos]
  );

  // Escape key to close modal
  useEscapeKey(closeModal, selectedPhoto !== null);

  // Arrow key navigation between photos
  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (!selectedPhoto) return;

      if (e.key === 'ArrowRight') {
        e.preventDefault();
        navigatePhoto('next');
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        navigatePhoto('prev');
      }
    },
    [selectedPhoto, navigatePhoto]
  );

  // Add keyboard event listener for arrow navigation
  useEffect(() => {
    if (selectedPhoto) {
      window.addEventListener('keydown', handleKeyDown);
      return () => window.removeEventListener('keydown', handleKeyDown);
    }
  }, [selectedPhoto, handleKeyDown]);

  if (photos.length === 0) {
    return (
      <div className="flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 p-8" role="status" aria-label="No photos available">
        <div className="text-center">
          <svg
            className="mx-auto h-12 w-12 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p className="mt-2 text-sm text-gray-500">No photos for this day</p>
        </div>
      </div>
    );
  }

  const displayPhotos = photos.slice(0, maxDisplay);
  const remainingCount = photos.length - maxDisplay;

  return (
    <>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4" role="list" aria-label="Photo gallery">
        {displayPhotos.map((photo, index) => (
          <button
            key={photo.id}
            onClick={() => setSelectedPhoto(photo)}
            aria-label={`View ${photo.caption || `photo ${index + 1}`} in full size`}
            role="listitem"
            className="group relative aspect-square overflow-hidden rounded-lg bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          >
            {photo.url ? (
              <Image
                src={photo.url}
                alt={photo.caption || `Photo ${index + 1}`}
                fill
                className="object-cover transition-transform group-hover:scale-105"
                sizes="(max-width: 640px) 50vw, (max-width: 768px) 33vw, 25vw"
              />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-gray-400" aria-label="No image available">
                <svg
                  className="h-8 w-8"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
              </div>
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100" aria-hidden="true">
              {photo.caption && (
                <div className="absolute bottom-2 left-2 right-2 text-white">
                  <p className="truncate text-xs font-medium">{photo.caption}</p>
                </div>
              )}
            </div>
            {/* Show remaining count on last visible photo */}
            {index === maxDisplay - 1 && remainingCount > 0 && (
              <div className="absolute inset-0 flex items-center justify-center bg-black/50" aria-label={`${remainingCount} more photos`}>
                <span className="text-2xl font-bold text-white" aria-hidden="true">
                  +{remainingCount}
                </span>
              </div>
            )}
          </button>
        ))}
      </div>

      {/* Lightbox Modal */}
      {selectedPhoto && (
        <div
          ref={modalRef}
          role="dialog"
          aria-modal="true"
          aria-label="Photo viewer"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          onClick={closeModal}
        >
          <div
            className="relative max-h-[90vh] max-w-4xl overflow-hidden rounded-lg bg-white"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              onClick={closeModal}
              aria-label="Close photo viewer (Escape key)"
              className="absolute right-2 top-2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
            >
              <svg
                className="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
            <div className="relative aspect-[4/3] w-full max-w-4xl">
              {selectedPhoto.url ? (
                <Image
                  src={selectedPhoto.url}
                  alt={selectedPhoto.caption || 'Photo'}
                  fill
                  className="object-contain"
                  sizes="(max-width: 1024px) 100vw, 1024px"
                  priority
                />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400" aria-label="No image available">
                  <svg
                    className="h-24 w-24"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                </div>
              )}
            </div>
            {selectedPhoto.caption && (
              <div className="bg-white p-4">
                <p className="text-gray-700">{selectedPhoto.caption}</p>
              </div>
            )}
          </div>

          {/* Navigation buttons for gallery */}
          <div className="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-4">
            <button
              onClick={(e) => {
                e.stopPropagation();
                navigatePhoto('prev');
              }}
              aria-label="Previous photo (Left arrow key)"
              className="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
            >
              <svg
                className="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 19l-7-7 7-7"
                />
              </svg>
            </button>
            <nav className="flex gap-2" aria-label="Photo navigation">
              {photos.map((photo, index) => (
                <button
                  key={photo.id}
                  onClick={(e) => {
                    e.stopPropagation();
                    setSelectedPhoto(photo);
                  }}
                  aria-label={`View photo ${index + 1}${photo.caption ? `: ${photo.caption}` : ''}`}
                  aria-current={selectedPhoto.id === photo.id ? 'true' : undefined}
                  className={`h-2 w-2 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-white ${
                    selectedPhoto.id === photo.id
                      ? 'bg-white'
                      : 'bg-white/50 hover:bg-white/75'
                  }`}
                />
              ))}
            </nav>
            <button
              onClick={(e) => {
                e.stopPropagation();
                navigatePhoto('next');
              }}
              aria-label="Next photo (Right arrow key)"
              className="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70 focus:outline-none focus:ring-2 focus:ring-white"
            >
              <svg
                className="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </button>
          </div>
          </nav>
        </div>
      )}
    </>
  );
}
