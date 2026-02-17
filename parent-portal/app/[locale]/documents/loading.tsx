export default function DocumentsLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header Skeleton */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="h-8 w-40 bg-gray-200 rounded animate-pulse" />
            <div className="mt-2 h-4 w-64 bg-gray-200 rounded animate-pulse" />
          </div>
          <div className="h-10 w-32 bg-gray-200 rounded animate-pulse" />
        </div>
      </div>

      {/* Summary Cards Skeleton */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card p-4">
            <div className="flex items-center space-x-3">
              <div className="h-10 w-10 bg-gray-200 rounded-full animate-pulse" />
              <div>
                <div className="h-4 w-24 bg-gray-200 rounded animate-pulse" />
                <div className="mt-2 h-6 w-8 bg-gray-200 rounded animate-pulse" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Filter Tabs Skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-6 w-32 bg-gray-200 rounded animate-pulse" />
        <div className="flex items-center space-x-2">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-8 w-20 bg-gray-200 rounded animate-pulse"
            />
          ))}
        </div>
      </div>

      {/* Document List Skeleton */}
      <div className="space-y-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <div className="h-12 w-12 bg-gray-200 rounded animate-pulse" />
                <div>
                  <div className="h-5 w-48 bg-gray-200 rounded animate-pulse" />
                  <div className="mt-2 h-4 w-32 bg-gray-200 rounded animate-pulse" />
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <div className="h-6 w-16 bg-gray-200 rounded animate-pulse" />
                <div className="h-8 w-24 bg-gray-200 rounded animate-pulse" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
