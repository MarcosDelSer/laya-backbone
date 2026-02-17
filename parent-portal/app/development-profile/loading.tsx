export default function DevelopmentProfileLoading() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-56 skeleton mb-2" />
            <div className="h-4 w-80 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Tab navigation skeleton */}
      <div className="mb-6 flex flex-wrap gap-2">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="h-10 w-36 skeleton rounded-lg" />
        ))}
      </div>

      {/* Quick stats skeleton */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card p-4 text-center">
            <div className="h-9 w-12 skeleton mx-auto mb-2" />
            <div className="h-4 w-24 skeleton mx-auto" />
          </div>
        ))}
      </div>

      {/* Section header skeleton */}
      <div className="flex items-center justify-between mb-4">
        <div className="h-6 w-48 skeleton" />
        <div className="h-4 w-20 skeleton" />
      </div>

      {/* Domain cards skeleton */}
      <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="card">
            {/* Card header skeleton */}
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="h-12 w-12 rounded-full skeleton" />
                  <div>
                    <div className="h-5 w-36 skeleton mb-2" />
                    <div className="h-4 w-48 skeleton" />
                  </div>
                </div>
              </div>
            </div>

            <div className="card-body">
              {/* Progress bar skeleton */}
              <div className="mb-4">
                <div className="flex items-center justify-between mb-2">
                  <div className="h-4 w-16 skeleton" />
                  <div className="h-4 w-10 skeleton" />
                </div>
                <div className="h-2.5 w-full skeleton rounded-full" />
              </div>

              {/* Stats grid skeleton */}
              <div className="grid grid-cols-4 gap-2 mb-4">
                {[1, 2, 3, 4].map((j) => (
                  <div key={j} className="text-center p-2 bg-gray-50 rounded-lg">
                    <div className="h-6 w-6 skeleton mx-auto mb-1" />
                    <div className="h-3 w-12 skeleton mx-auto" />
                  </div>
                ))}
              </div>

              {/* Skills list skeleton */}
              <div>
                <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-2">
                  <div className="h-4 w-12 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
                <div className="space-y-2">
                  {[1, 2, 3].map((j) => (
                    <div
                      key={j}
                      className="flex items-center justify-between py-2 border-b border-gray-100"
                    >
                      <div className="flex-1">
                        <div className="h-4 w-32 skeleton mb-1" />
                        <div className="h-3 w-24 skeleton" />
                      </div>
                      <div className="h-6 w-20 skeleton rounded-full" />
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
