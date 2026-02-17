export default function DietaryLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="h-8 w-48 skeleton mb-2" />
            <div className="h-4 w-72 skeleton" />
          </div>
          <div className="h-10 w-40 skeleton rounded-lg" />
        </div>
      </div>

      {/* Child selector skeleton */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div className="h-4 w-24 skeleton" />
          <div className="h-12 w-48 skeleton rounded-lg" />
        </div>
      </div>

      {/* Summary Cards skeleton */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card p-4">
            <div className="flex items-center space-x-3">
              <div className="h-10 w-10 rounded-full skeleton" />
              <div>
                <div className="h-4 w-24 skeleton mb-2" />
                <div className="h-6 w-16 skeleton" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Tab navigation skeleton */}
      <div className="mb-6 flex items-center justify-between border-b border-gray-200 pb-3">
        <div className="flex space-x-4">
          <div className="h-5 w-40 skeleton" />
          <div className="h-5 w-36 skeleton" />
        </div>
      </div>

      {/* Form skeleton */}
      <div className="card">
        <div className="card-body space-y-6">
          {/* Child name header skeleton */}
          <div className="border-b border-gray-200 pb-4">
            <div className="h-6 w-56 skeleton mb-2" />
            <div className="h-4 w-80 skeleton" />
          </div>

          {/* Dietary type field skeleton */}
          <div>
            <div className="h-4 w-24 skeleton mb-2" />
            <div className="h-10 w-full skeleton rounded-lg" />
          </div>

          {/* Allergies section skeleton */}
          <div>
            <div className="h-4 w-20 skeleton mb-4" />
            <div className="rounded-lg bg-gray-50 p-4 mb-4">
              <div className="flex flex-wrap gap-2">
                {[1, 2].map((i) => (
                  <div
                    key={i}
                    className="flex items-center gap-2 rounded-lg bg-white border border-gray-200 px-3 py-2"
                  >
                    <div className="h-6 w-24 skeleton rounded-full" />
                    <div className="h-6 w-20 skeleton rounded" />
                    <div className="h-4 w-4 skeleton rounded" />
                  </div>
                ))}
              </div>
            </div>
            <div className="h-4 w-48 skeleton mb-3" />
            <div className="flex flex-wrap gap-2">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <div key={i} className="h-7 w-20 skeleton rounded-full" />
              ))}
            </div>
          </div>

          {/* Restrictions field skeleton */}
          <div>
            <div className="h-4 w-32 skeleton mb-2" />
            <div className="h-20 w-full skeleton rounded-lg" />
            <div className="h-3 w-64 skeleton mt-2" />
          </div>

          {/* Notes field skeleton */}
          <div>
            <div className="h-4 w-28 skeleton mb-2" />
            <div className="h-20 w-full skeleton rounded-lg" />
          </div>

          {/* Info notice skeleton */}
          <div className="rounded-lg bg-gray-100 p-4">
            <div className="flex">
              <div className="h-5 w-5 skeleton rounded-full flex-shrink-0" />
              <div className="ml-3 flex-1">
                <div className="h-4 w-full skeleton" />
              </div>
            </div>
          </div>

          {/* Actions skeleton */}
          <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
            <div className="h-10 w-36 skeleton rounded-lg" />
          </div>
        </div>
      </div>

      {/* Last updated skeleton */}
      <div className="mt-6 text-center">
        <div className="h-3 w-48 skeleton mx-auto" />
      </div>
    </div>
  );
}
