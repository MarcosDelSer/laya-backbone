'use client';

import { useState } from 'react';
import Image from 'next/image';
import { PortfolioItem } from '../lib/types';

interface PortfolioMediaGalleryProps {
  items: PortfolioItem[];
  maxDisplay?: number;
  onItemClick?: (item: PortfolioItem) => void;
}

export function PortfolioMediaGallery({
  items,
  maxDisplay = 6,
  onItemClick,
}: PortfolioMediaGalleryProps) {
  const [selectedItem, setSelectedItem] = useState<PortfolioItem | null>(null);

  // Filter to only photo and video items
  const mediaItems = items.filter(
    (item) => item.type === 'photo' || item.type === 'video'
  );

  if (mediaItems.length === 0) {
    return (
      <div className="flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 p-8">
        <div className="text-center">
          <svg
            className="mx-auto h-12 w-12 text-gray-400"
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
          <p className="mt-2 text-sm text-gray-500">No media items</p>
        </div>
      </div>
    );
  }

  const displayItems = mediaItems.slice(0, maxDisplay);
  const remainingCount = mediaItems.length - maxDisplay;

  const handleItemClick = (item: PortfolioItem) => {
    if (onItemClick) {
      onItemClick(item);
    } else {
      setSelectedItem(item);
    }
  };

  const handlePrevious = () => {
    if (!selectedItem) return;
    const currentIndex = mediaItems.findIndex((i) => i.id === selectedItem.id);
    const prevIndex = currentIndex > 0 ? currentIndex - 1 : mediaItems.length - 1;
    setSelectedItem(mediaItems[prevIndex]);
  };

  const handleNext = () => {
    if (!selectedItem) return;
    const currentIndex = mediaItems.findIndex((i) => i.id === selectedItem.id);
    const nextIndex = currentIndex < mediaItems.length - 1 ? currentIndex + 1 : 0;
    setSelectedItem(mediaItems[nextIndex]);
  };

  return (
    <>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
        {displayItems.map((item, index) => (
          <button
            key={item.id}
            onClick={() => handleItemClick(item)}
            className="group relative aspect-square overflow-hidden rounded-lg bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          >
            {item.type === 'photo' && item.mediaUrl ? (
              <Image
                src={item.thumbnailUrl || item.mediaUrl}
                alt={item.title || `Photo ${index + 1}`}
                fill
                className="object-cover transition-transform group-hover:scale-105"
                sizes="(max-width: 640px) 50vw, (max-width: 768px) 33vw, 25vw"
              />
            ) : item.type === 'video' ? (
              <div className="flex h-full w-full items-center justify-center bg-gray-200 text-gray-500">
                {item.thumbnailUrl ? (
                  <>
                    <Image
                      src={item.thumbnailUrl}
                      alt={item.title || `Video ${index + 1}`}
                      fill
                      className="object-cover transition-transform group-hover:scale-105"
                      sizes="(max-width: 640px) 50vw, (max-width: 768px) 33vw, 25vw"
                    />
                    <div className="absolute inset-0 flex items-center justify-center">
                      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-black/50">
                        <svg
                          className="h-6 w-6 text-white"
                          fill="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path d="M8 5v14l11-7z" />
                        </svg>
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="flex flex-col items-center">
                    <svg
                      className="h-8 w-8"
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
                    <span className="mt-1 text-xs">Video</span>
                  </div>
                )}
              </div>
            ) : (
              <div className="flex h-full w-full items-center justify-center text-gray-400">
                <svg
                  className="h-8 w-8"
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
              </div>
            )}
            {/* Hover overlay with caption */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100">
              {item.title && (
                <div className="absolute bottom-2 left-2 right-2 text-white">
                  <p className="truncate text-xs font-medium">{item.title}</p>
                  {item.type === 'video' && (
                    <span className="text-xs text-gray-300">Video</span>
                  )}
                </div>
              )}
            </div>
            {/* Media type indicator */}
            <div className="absolute right-1 top-1">
              {item.type === 'video' && (
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-white">
                  <svg
                    className="h-3 w-3"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </span>
              )}
            </div>
            {/* Privacy indicator */}
            {item.isPrivate && (
              <div className="absolute left-1 top-1">
                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-gray-900/75 text-white">
                  <svg
                    className="h-3 w-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                    />
                  </svg>
                </span>
              </div>
            )}
            {/* Show remaining count on last visible item */}
            {index === maxDisplay - 1 && remainingCount > 0 && (
              <div className="absolute inset-0 flex items-center justify-center bg-black/50">
                <span className="text-2xl font-bold text-white">
                  +{remainingCount}
                </span>
              </div>
            )}
          </button>
        ))}
      </div>

      {/* Lightbox Modal */}
      {selectedItem && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          onClick={() => setSelectedItem(null)}
        >
          <div
            className="relative max-h-[90vh] max-w-4xl overflow-hidden rounded-lg bg-white"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Close button */}
            <button
              onClick={() => setSelectedItem(null)}
              className="absolute right-2 top-2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            >
              <svg
                className="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>

            {/* Media content */}
            <div className="relative aspect-[4/3] w-full max-w-4xl">
              {selectedItem.type === 'photo' && selectedItem.mediaUrl ? (
                <Image
                  src={selectedItem.mediaUrl}
                  alt={selectedItem.title || 'Photo'}
                  fill
                  className="object-contain"
                  sizes="(max-width: 1024px) 100vw, 1024px"
                  priority
                />
              ) : selectedItem.type === 'video' && selectedItem.mediaUrl ? (
                <video
                  src={selectedItem.mediaUrl}
                  controls
                  autoPlay
                  className="h-full w-full object-contain"
                  poster={selectedItem.thumbnailUrl}
                >
                  Your browser does not support the video tag.
                </video>
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-gray-200 text-gray-400">
                  <svg
                    className="h-24 w-24"
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
                </div>
              )}
            </div>

            {/* Caption and metadata */}
            {(selectedItem.title || selectedItem.caption) && (
              <div className="bg-white p-4">
                {selectedItem.title && (
                  <h3 className="font-semibold text-gray-900">
                    {selectedItem.title}
                  </h3>
                )}
                {selectedItem.caption && (
                  <p className="mt-1 text-gray-700">{selectedItem.caption}</p>
                )}
                {selectedItem.tags && selectedItem.tags.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1">
                    {selectedItem.tags.map((tag, index) => (
                      <span
                        key={index}
                        className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                      >
                        #{tag}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Navigation arrows */}
          {mediaItems.length > 1 && (
            <>
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  handlePrevious();
                }}
                className="absolute left-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
                aria-label="Previous"
              >
                <svg
                  className="h-6 w-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M15 19l-7-7 7-7"
                  />
                </svg>
              </button>
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  handleNext();
                }}
                className="absolute right-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
                aria-label="Next"
              >
                <svg
                  className="h-6 w-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </button>
            </>
          )}

          {/* Thumbnail navigation dots */}
          <div className="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
            {mediaItems.map((item, index) => (
              <button
                key={item.id}
                onClick={(e) => {
                  e.stopPropagation();
                  setSelectedItem(item);
                }}
                className={`h-2 w-2 rounded-full transition-colors ${
                  selectedItem.id === item.id
                    ? 'bg-white'
                    : 'bg-white/50 hover:bg-white/75'
                }`}
                aria-label={`View ${item.type} ${index + 1}`}
              />
            ))}
          </div>
        </div>
      )}
    </>
  );
}
