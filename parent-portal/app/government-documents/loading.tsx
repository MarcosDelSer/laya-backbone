export default function Loading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 animate-pulse">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="h-8 w-64 bg-gray-200 rounded" />
            <div className="mt-2 h-4 w-80 bg-gray-200 rounded" />
          </div>
          <div className="h-10 w-36 bg-gray-200 rounded" />
        </div>
      </div>

      {/* Compliance Progress skeleton */}
      <div className="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div className="h-6 w-40 bg-gray-200 rounded" />
          <div className="h-8 w-16 bg-gray-200 rounded" />
        </div>
        <div className="h-3 w-full bg-gray-200 rounded-full" />
        <div className="mt-2 h-4 w-48 bg-gray-200 rounded" />
      </div>

      {/* Summary Cards skeleton */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div
            key={i}
            className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
          >
            <div className="flex items-center space-x-3">
              <div className="h-10 w-10 bg-gray-200 rounded-full" />
              <div>
                <div className="h-4 w-24 bg-gray-200 rounded" />
                <div className="mt-2 h-6 w-8 bg-gray-200 rounded" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Filter tabs skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-5 w-32 bg-gray-200 rounded" />
        <div className="flex items-center space-x-2">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-9 w-20 bg-gray-200 rounded" />
          ))}
        </div>
      </div>

      {/* Document list skeleton */}
      <div className="space-y-4">
        {[1, 2, 3, 4].map((i) => (
          <div
            key={i}
            className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
          >
            <div className="flex items-start justify-between">
              <div className="flex items-start space-x-4">
                <div className="h-12 w-12 bg-gray-200 rounded-full" />
                <div className="flex-1">
                  <div className="h-5 w-48 bg-gray-200 rounded" />
                  <div className="mt-2 h-4 w-32 bg-gray-200 rounded" />
                  <div className="mt-2 h-3 w-24 bg-gray-200 rounded" />
                </div>
              </div>
              <div className="h-6 w-20 bg-gray-200 rounded-full" />
            </div>
            <div className="mt-4 flex gap-2 border-t border-gray-100 pt-4">
              <div className="h-9 w-28 bg-gray-200 rounded" />
              <div className="h-9 w-36 bg-gray-200 rounded" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
