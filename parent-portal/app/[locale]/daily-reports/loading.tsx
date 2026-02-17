/**
 * Loading skeleton for the Daily Reports page.
 *
 * Displays a skeleton UI while the page content is loading.
 * This provides visual feedback to users during page transitions.
 */
export default function DailyReportsLoading() {
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
        <div className="h-4 w-40 skeleton" />
        <div className="h-8 w-20 skeleton rounded-lg" />
      </div>

      {/* Report cards skeleton */}
      <div className="space-y-6">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card">
            {/* Card header skeleton */}
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="h-12 w-12 rounded-full skeleton" />
                  <div>
                    <div className="h-5 w-32 skeleton mb-2" />
                    <div className="h-4 w-24 skeleton" />
                  </div>
                </div>
                <div className="hidden sm:flex items-center space-x-2">
                  <div className="h-6 w-16 skeleton rounded-full" />
                  <div className="h-6 w-14 skeleton rounded-full" />
                  <div className="h-6 w-20 skeleton rounded-full" />
                </div>
              </div>
            </div>

            <div className="card-body">
              {/* Photos skeleton */}
              <div className="mb-6">
                <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                  <div className="h-5 w-20 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                  {[1, 2, 3, 4].map((j) => (
                    <div key={j} className="aspect-square skeleton rounded-lg" />
                  ))}
                </div>
              </div>

              {/* Meals and Naps skeleton */}
              <div className="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
                {/* Meals skeleton */}
                <div>
                  <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                    <div className="h-5 w-16 skeleton" />
                    <div className="h-4 w-16 skeleton" />
                  </div>
                  <div className="space-y-4">
                    {[1, 2, 3].map((j) => (
                      <div key={j} className="flex items-start space-x-3">
                        <div className="h-10 w-10 rounded-full skeleton" />
                        <div className="flex-1">
                          <div className="h-4 w-24 skeleton mb-2" />
                          <div className="h-3 w-48 skeleton mb-2" />
                          <div className="h-5 w-16 skeleton rounded-full" />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Naps skeleton */}
                <div>
                  <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                    <div className="h-5 w-20 skeleton" />
                    <div className="h-4 w-12 skeleton" />
                  </div>
                  <div className="space-y-4">
                    <div className="flex items-start space-x-3">
                      <div className="h-10 w-10 rounded-full skeleton" />
                      <div className="flex-1">
                        <div className="h-4 w-20 skeleton mb-2" />
                        <div className="h-3 w-32 skeleton mb-2" />
                        <div className="h-5 w-20 skeleton rounded-full" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Activities skeleton */}
              <div>
                <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                  <div className="h-5 w-24 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {[1, 2, 3, 4].map((j) => (
                    <div key={j} className="flex items-start space-x-3">
                      <div className="h-10 w-10 rounded-full skeleton" />
                      <div className="flex-1">
                        <div className="h-4 w-28 skeleton mb-2" />
                        <div className="h-3 w-40 skeleton" />
                      </div>
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
