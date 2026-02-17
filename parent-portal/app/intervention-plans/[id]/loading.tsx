export default function InterventionPlanDetailLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center space-x-3 mb-2">
              <div className="h-8 w-64 skeleton" />
              <div className="h-6 w-16 skeleton rounded-full" />
            </div>
            <div className="h-4 w-40 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Plan Overview Card skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-32 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4">
            {[1, 2, 3, 4].map((j) => (
              <div key={j} className="text-center p-3 bg-gray-50 rounded-lg">
                <div className="h-8 w-12 skeleton mx-auto mb-1" />
                <div className="h-3 w-16 skeleton mx-auto" />
              </div>
            ))}
          </div>
          <div className="p-3 bg-gray-50 rounded-lg">
            <div className="flex items-center space-x-2">
              <div className="h-5 w-5 skeleton rounded" />
              <div className="h-4 w-40 skeleton" />
            </div>
          </div>
          <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            {[1, 2, 3, 4].map((j) => (
              <div key={j} className="flex items-center space-x-2">
                <div className="h-4 w-24 skeleton" />
                <div className="h-4 w-32 skeleton" />
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Child Identification skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-40 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {[1, 2, 3, 4].map((j) => (
              <div key={j}>
                <div className="h-3 w-16 skeleton mb-1" />
                <div className="h-5 w-full skeleton" />
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Strengths skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-24 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4">
            {[1, 2, 3].map((j) => (
              <div key={j} className="p-4 bg-gray-50 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="h-6 w-20 skeleton rounded-full" />
                </div>
                <div className="h-5 w-full skeleton mb-2" />
                <div className="h-4 w-3/4 skeleton" />
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Goals skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-28 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4">
            {[1, 2, 3, 4].map((j) => (
              <div key={j} className="p-4 border border-gray-200 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="h-5 w-40 skeleton" />
                  <div className="h-6 w-24 skeleton rounded-full" />
                </div>
                <div className="h-4 w-full skeleton mb-3" />
                <div className="mb-3">
                  <div className="flex items-center justify-between mb-1">
                    <div className="h-4 w-16 skeleton" />
                    <div className="h-4 w-10 skeleton" />
                  </div>
                  <div className="h-2 w-full skeleton rounded-full" />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {[1, 2, 3, 4].map((k) => (
                    <div key={k} className="flex items-center space-x-2">
                      <div className="h-4 w-20 skeleton" />
                      <div className="h-4 w-24 skeleton" />
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Strategies skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-44 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4">
            {[1, 2, 3].map((j) => (
              <div key={j} className="p-4 bg-gray-50 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="h-5 w-36 skeleton" />
                  <div className="h-6 w-20 skeleton rounded-full" />
                </div>
                <div className="h-4 w-full skeleton mb-3" />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {[1, 2].map((k) => (
                    <div key={k} className="flex items-center space-x-2">
                      <div className="h-4 w-20 skeleton" />
                      <div className="h-4 w-28 skeleton" />
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Progress History skeleton */}
      <div className="card mb-6">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-36 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-4">
            {[1, 2, 3].map((j) => (
              <div key={j} className="p-4 border border-gray-200 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center space-x-3">
                    <div className="h-6 w-32 skeleton rounded-full" />
                    <div className="h-4 w-28 skeleton" />
                  </div>
                  <div className="h-4 w-20 skeleton" />
                </div>
                <div className="h-4 w-full skeleton mb-2" />
                <div className="flex items-center justify-between">
                  <div className="h-3 w-32 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Version History skeleton */}
      <div className="card">
        <div className="card-header">
          <div className="flex items-center space-x-3">
            <div className="h-10 w-10 skeleton rounded-lg" />
            <div className="h-6 w-32 skeleton" />
          </div>
        </div>
        <div className="card-body">
          <div className="space-y-3">
            {[1, 2].map((j) => (
              <div
                key={j}
                className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div className="flex items-center space-x-3">
                  <div className="h-6 w-8 skeleton rounded-full" />
                  <div>
                    <div className="h-4 w-40 skeleton mb-1" />
                    <div className="h-3 w-24 skeleton" />
                  </div>
                </div>
                <div className="h-4 w-20 skeleton" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
