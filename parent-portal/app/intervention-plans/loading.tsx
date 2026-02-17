export default function InterventionPlansLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-48 skeleton mb-2" />
            <div className="h-4 w-72 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Filter bar skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-4 w-32 skeleton" />
        <div className="h-8 w-20 skeleton rounded-lg" />
      </div>

      {/* Plan cards skeleton */}
      <div className="space-y-6">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card">
            {/* Card header skeleton */}
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="h-12 w-12 rounded-full skeleton" />
                  <div>
                    <div className="h-5 w-48 skeleton mb-2" />
                    <div className="h-4 w-32 skeleton" />
                  </div>
                </div>
                <div className="hidden sm:flex items-center space-x-2">
                  <div className="h-6 w-16 skeleton rounded-full" />
                  <div className="h-6 w-28 skeleton rounded-full" />
                </div>
              </div>
            </div>

            <div className="card-body">
              {/* Mobile badges skeleton */}
              <div className="flex sm:hidden items-center space-x-2 mb-4">
                <div className="h-6 w-16 skeleton rounded-full" />
                <div className="h-6 w-28 skeleton rounded-full" />
              </div>

              {/* Statistics grid skeleton */}
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4">
                {[1, 2, 3, 4].map((j) => (
                  <div key={j} className="text-center p-3 bg-gray-50 rounded-lg">
                    <div className="h-8 w-12 skeleton mx-auto mb-1" />
                    <div className="h-3 w-16 skeleton mx-auto" />
                  </div>
                ))}
              </div>

              {/* Review date skeleton */}
              <div className="p-3 bg-gray-50 rounded-lg">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <div className="h-5 w-5 skeleton rounded" />
                    <div className="h-4 w-40 skeleton" />
                  </div>
                </div>
              </div>

              {/* Footer skeleton */}
              <div className="mt-4 flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <div className="h-5 w-5 skeleton rounded-full" />
                  <div className="h-4 w-28 skeleton" />
                </div>
                <div className="h-4 w-24 skeleton" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
