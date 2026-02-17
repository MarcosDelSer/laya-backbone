/**
 * Loading skeleton for the Messages page.
 *
 * Displays a skeleton UI while the page content is loading.
 * This provides visual feedback to users during page transitions.
 */
export default function MessagesLoading() {
  return (
    <div className="h-[calc(100vh-4rem)] flex flex-col">
      {/* Header skeleton */}
      <div className="border-b border-gray-200 bg-white px-4 py-4">
        <div className="mx-auto max-w-7xl">
          <div className="flex items-center justify-between">
            <div>
              <div className="h-7 w-32 skeleton mb-2" />
              <div className="h-4 w-48 skeleton" />
            </div>
            <div className="h-10 w-32 skeleton rounded-lg" />
          </div>
        </div>
      </div>

      {/* Main content area */}
      <div className="flex flex-1 overflow-hidden">
        {/* Thread list skeleton */}
        <div className="w-full md:w-80 lg:w-96 border-r border-gray-200 bg-white overflow-y-auto">
          {[1, 2, 3, 4, 5].map((i) => (
            <div
              key={i}
              className="px-4 py-3 border-b border-gray-100"
            >
              <div className="flex items-start space-x-3">
                {/* Avatar skeleton */}
                <div className="h-12 w-12 rounded-full skeleton flex-shrink-0" />

                {/* Content skeleton */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-2">
                    <div className="h-4 w-32 skeleton" />
                    <div className="h-3 w-16 skeleton" />
                  </div>
                  <div className="h-4 w-full skeleton mb-2" />
                  <div className="h-3 w-24 skeleton" />
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Message thread skeleton */}
        <div className="hidden md:flex flex-1 flex-col bg-gray-50">
          {/* Thread header skeleton */}
          <div className="border-b border-gray-200 bg-white px-4 py-3">
            <div className="flex items-center space-x-3">
              <div className="h-10 w-10 rounded-full skeleton flex-shrink-0" />
              <div className="flex-1">
                <div className="h-5 w-40 skeleton mb-2" />
                <div className="h-3 w-24 skeleton" />
              </div>
            </div>
          </div>

          {/* Messages skeleton */}
          <div className="flex-1 p-4 space-y-4">
            {/* Date divider skeleton */}
            <div className="flex items-center justify-center my-6">
              <div className="border-t border-gray-200 flex-1" />
              <div className="px-4 h-4 w-16 skeleton" />
              <div className="border-t border-gray-200 flex-1" />
            </div>

            {/* Message bubbles skeleton - alternating sides */}
            <div className="flex justify-start">
              <div className="max-w-[75%]">
                <div className="h-20 w-64 skeleton rounded-r-2xl rounded-tl-2xl" />
                <div className="h-3 w-16 skeleton mt-1" />
              </div>
            </div>

            <div className="flex justify-end">
              <div className="max-w-[75%]">
                <div className="h-14 w-48 skeleton rounded-l-2xl rounded-tr-2xl" />
                <div className="h-3 w-16 skeleton mt-1 ml-auto" />
              </div>
            </div>

            <div className="flex justify-start">
              <div className="max-w-[75%]">
                <div className="h-24 w-72 skeleton rounded-r-2xl rounded-tl-2xl" />
                <div className="h-3 w-16 skeleton mt-1" />
              </div>
            </div>

            <div className="flex justify-end">
              <div className="max-w-[75%]">
                <div className="h-10 w-32 skeleton rounded-l-2xl rounded-tr-2xl" />
                <div className="h-3 w-16 skeleton mt-1 ml-auto" />
              </div>
            </div>
          </div>

          {/* Composer skeleton */}
          <div className="bg-white border-t border-gray-200 p-4">
            <div className="flex items-end space-x-3">
              <div className="h-10 w-10 rounded-full skeleton flex-shrink-0" />
              <div className="flex-1 h-12 skeleton rounded-2xl" />
              <div className="h-10 w-10 rounded-full skeleton flex-shrink-0" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
