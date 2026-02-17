export default function MedicalLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
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

      {/* Status badges skeleton */}
      <div className="mb-6 flex flex-wrap items-center gap-2">
        <div className="h-7 w-36 skeleton rounded-full" />
        <div className="h-7 w-32 skeleton rounded-full" />
        <div className="h-7 w-48 skeleton rounded-full" />
      </div>

      {/* Active Alerts skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
          <div className="h-6 w-32 skeleton" />
          <div className="h-4 w-16 skeleton" />
        </div>
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <div key={i} className="rounded-lg border-2 border-gray-200 p-4">
              <div className="flex items-start space-x-3">
                <div className="h-10 w-10 rounded-full skeleton" />
                <div className="flex-1">
                  <div className="flex items-center justify-between mb-2">
                    <div className="h-5 w-40 skeleton" />
                    <div className="flex space-x-1">
                      <div className="h-5 w-16 skeleton rounded-full" />
                      <div className="h-5 w-14 skeleton rounded-full" />
                    </div>
                  </div>
                  <div className="h-4 w-full skeleton mb-2" />
                  <div className="h-4 w-3/4 skeleton" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Allergies card skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="h-8 w-8 rounded-full skeleton" />
              <div className="h-6 w-24 skeleton" />
            </div>
            <div className="h-4 w-20 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4 divide-y divide-gray-100">
            {[1, 2, 3].map((i) => (
              <div key={i} className={i > 1 ? 'pt-4' : ''}>
                <div className="flex items-start space-x-3">
                  <div className="h-10 w-10 rounded-full skeleton" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-2">
                      <div className="h-5 w-28 skeleton" />
                      <div className="h-4 w-16 skeleton" />
                    </div>
                    <div className="h-4 w-48 skeleton mb-2" />
                    <div className="h-4 w-40 skeleton mb-2" />
                    <div className="flex gap-1">
                      <div className="h-5 w-16 skeleton rounded-full" />
                      <div className="h-5 w-28 skeleton rounded-full" />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Medications card skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="h-8 w-8 rounded-full skeleton" />
              <div className="h-6 w-28 skeleton" />
            </div>
            <div className="h-4 w-16 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4 divide-y divide-gray-100">
            {[1, 2].map((i) => (
              <div key={i} className={i > 1 ? 'pt-4' : ''}>
                <div className="flex items-start space-x-3">
                  <div className="h-10 w-10 rounded-full skeleton" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-2">
                      <div className="h-5 w-32 skeleton" />
                      <div className="h-4 w-24 skeleton" />
                    </div>
                    <div className="h-4 w-56 skeleton mb-2" />
                    <div className="h-4 w-36 skeleton mb-2" />
                    <div className="h-4 w-44 skeleton mb-2" />
                    <div className="flex gap-1">
                      <div className="h-5 w-32 skeleton rounded-full" />
                      <div className="h-5 w-24 skeleton rounded-full" />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Accommodation Plans card skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="h-8 w-8 rounded-full skeleton" />
              <div className="h-6 w-44 skeleton" />
            </div>
            <div className="h-4 w-16 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4 divide-y divide-gray-100">
            {[1, 2].map((i) => (
              <div key={i} className={i > 1 ? 'pt-4' : ''}>
                <div className="flex items-start space-x-3">
                  <div className="h-10 w-10 rounded-full skeleton" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-2">
                      <div className="h-5 w-48 skeleton" />
                      <div className="h-4 w-20 skeleton" />
                    </div>
                    <div className="h-4 w-full skeleton mb-2" />
                    <div className="h-4 w-3/4 skeleton mb-2" />
                    <div className="flex gap-1">
                      <div className="h-5 w-20 skeleton rounded-full" />
                      <div className="h-5 w-28 skeleton rounded-full" />
                      <div className="h-5 w-24 skeleton rounded-full" />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Footer skeleton */}
      <div className="text-center">
        <div className="h-4 w-48 skeleton mx-auto mb-2" />
        <div className="h-4 w-64 skeleton mx-auto" />
      </div>
    </div>
  );
}
