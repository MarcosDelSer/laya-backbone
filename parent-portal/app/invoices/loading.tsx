export default function InvoicesLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-32 skeleton mb-2" />
            <div className="h-4 w-64 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Summary Cards skeleton */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card p-4">
            <div className="h-4 w-20 skeleton mb-2" />
            <div className="h-8 w-28 skeleton mb-1" />
            <div className="h-3 w-16 skeleton" />
          </div>
        ))}
      </div>

      {/* Payment History Header skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-6 w-40 skeleton" />
        <div className="flex items-center space-x-2">
          <div className="h-8 w-20 skeleton rounded-lg" />
          <div className="h-8 w-20 skeleton rounded-lg" />
        </div>
      </div>

      {/* Invoice cards skeleton */}
      <div className="space-y-6">
        {[1, 2, 3].map((i) => (
          <div key={i} className="card">
            {/* Card header skeleton */}
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="h-12 w-12 rounded-full skeleton" />
                  <div>
                    <div className="h-5 w-36 skeleton mb-2" />
                    <div className="h-4 w-28 skeleton" />
                  </div>
                </div>
                <div className="h-6 w-20 skeleton rounded-full" />
              </div>
            </div>

            <div className="card-body">
              {/* Amount and Due Date skeleton */}
              <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <div className="h-4 w-24 skeleton mb-2" />
                  <div className="h-8 w-32 skeleton" />
                </div>
                <div>
                  <div className="h-4 w-20 skeleton mb-2" />
                  <div className="h-5 w-28 skeleton mb-1" />
                  <div className="h-3 w-24 skeleton" />
                </div>
              </div>

              {/* Invoice Items skeleton */}
              <div className="mb-6">
                <div className="h-5 w-28 skeleton mb-3" />
                <div className="space-y-2">
                  <div className="h-8 w-full skeleton" />
                  <div className="h-10 w-full skeleton" />
                  <div className="h-10 w-full skeleton" />
                  <div className="h-8 w-full skeleton" />
                </div>
              </div>

              {/* Actions skeleton */}
              <div className="flex flex-col sm:flex-row gap-3">
                <div className="h-10 w-36 skeleton rounded-lg" />
                <div className="h-10 w-28 skeleton rounded-lg" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
