export default function Loading() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="h-8 w-56 skeleton mb-2" />
            <div className="h-5 w-40 skeleton" />
          </div>
          <div className="mt-4 flex items-center space-x-3 sm:mt-0">
            <div className="h-10 w-24 skeleton rounded-lg" />
            <div className="h-10 w-24 skeleton rounded-lg" />
          </div>
        </div>
      </div>

      {/* KPI Summary Cards skeleton */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card p-5">
            <div className="flex items-center space-x-4">
              <div className="h-12 w-12 rounded-xl skeleton" />
              <div>
                <div className="h-4 w-20 skeleton mb-2" />
                <div className="h-7 w-16 skeleton mb-1" />
                <div className="h-3 w-24 skeleton" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {/* Left Column - Occupancy Overview */}
        <div className="lg:col-span-2 space-y-8">
          {/* Facility Occupancy Card skeleton */}
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <div className="h-6 w-40 skeleton mb-2" />
                <div className="h-4 w-56 skeleton" />
              </div>
              <div className="h-6 w-16 skeleton rounded-full" />
            </div>
            {/* Progress bar skeleton */}
            <div className="h-3 w-full skeleton rounded-full mb-4" />
            {/* Occupancy breakdown */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="text-center">
                  <div className="h-4 w-16 skeleton mx-auto mb-2" />
                  <div className="h-6 w-12 skeleton mx-auto" />
                </div>
              ))}
            </div>
          </div>

          {/* Groups Detail Section skeleton */}
          <div className="card">
            <div className="card-header flex items-center justify-between">
              <div>
                <div className="h-6 w-48 skeleton mb-2" />
                <div className="h-4 w-40 skeleton" />
              </div>
              <div className="flex items-center space-x-2">
                <div className="h-6 w-24 skeleton rounded-full" />
                <div className="h-6 w-28 skeleton rounded-full" />
              </div>
            </div>
            <div className="card-body">
              <div className="space-y-4">
                {[1, 2, 3, 4, 5].map((i) => (
                  <div key={i} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div className="flex items-center space-x-4">
                      <div className="h-10 w-10 rounded-full skeleton" />
                      <div>
                        <div className="h-5 w-32 skeleton mb-2" />
                        <div className="h-3 w-24 skeleton" />
                      </div>
                    </div>
                    <div className="flex items-center space-x-4">
                      <div className="h-2 w-32 skeleton rounded-full" />
                      <div className="h-5 w-16 skeleton" />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Right Column */}
        <div className="space-y-6">
          {/* Alerts Section skeleton */}
          <div className="card">
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div>
                  <div className="h-6 w-28 skeleton mb-2" />
                  <div className="h-4 w-32 skeleton" />
                </div>
                <div className="flex items-center space-x-2">
                  <div className="h-6 w-6 skeleton rounded-full" />
                  <div className="h-6 w-6 skeleton rounded-full" />
                </div>
              </div>
            </div>
            <div className="card-body">
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="p-3 border border-gray-100 rounded-lg">
                    <div className="flex items-start space-x-3">
                      <div className="h-5 w-5 skeleton rounded" />
                      <div className="flex-1">
                        <div className="h-4 w-32 skeleton mb-2" />
                        <div className="h-3 w-full skeleton mb-1" />
                        <div className="h-3 w-2/3 skeleton" />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Quick Stats Card skeleton */}
          <div className="card">
            <div className="card-header">
              <div className="h-6 w-36 skeleton" />
            </div>
            <div className="card-body">
              <div className="space-y-4">
                {/* Available Spots */}
                <div className="flex items-center justify-between">
                  <div className="h-4 w-28 skeleton" />
                  <div className="h-6 w-8 skeleton" />
                </div>

                {/* Groups Status Grid */}
                <div className="border-t border-gray-100 pt-4">
                  <div className="grid grid-cols-2 gap-4">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="p-3 bg-gray-50 rounded-lg text-center">
                        <div className="h-8 w-8 skeleton mx-auto mb-2" />
                        <div className="h-3 w-16 skeleton mx-auto" />
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Last Updated skeleton */}
          <div className="flex items-center justify-center">
            <div className="h-4 w-40 skeleton" />
          </div>
        </div>
      </div>
    </div>
  );
}
