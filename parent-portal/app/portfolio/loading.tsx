export default function PortfolioLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-32 skeleton mb-2" />
            <div className="h-4 w-72 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Child selector skeleton */}
      <div className="mb-6">
        <div className="h-4 w-24 skeleton mb-2" />
        <div className="h-10 w-full skeleton rounded-lg" />
      </div>

      {/* Summary stats skeleton */}
      <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card p-4 text-center">
            <div className="h-12 w-12 mx-auto rounded-full skeleton" />
            <div className="h-8 w-12 mx-auto skeleton mt-2" />
            <div className="h-4 w-20 mx-auto skeleton mt-1" />
          </div>
        ))}
      </div>

      {/* Quick actions skeleton */}
      <div className="mb-8">
        <div className="h-6 w-32 skeleton mb-4" />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="card p-4 text-center">
              <div className="h-10 w-10 mx-auto rounded-full skeleton" />
              <div className="h-4 w-24 mx-auto skeleton mt-2" />
            </div>
          ))}
        </div>
      </div>

      {/* Recent items header skeleton */}
      <div className="mb-4 flex items-center justify-between">
        <div className="h-6 w-32 skeleton" />
        <div className="h-4 w-16 skeleton" />
      </div>

      {/* Portfolio cards skeleton */}
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card">
            {/* Media preview skeleton */}
            <div className="aspect-video w-full skeleton rounded-t-lg" />

            <div className="card-body">
              {/* Title and date skeleton */}
              <div className="mb-2">
                <div className="h-5 w-48 skeleton mb-2" />
                <div className="h-4 w-24 skeleton" />
              </div>

              {/* Caption skeleton */}
              <div className="mb-4">
                <div className="h-3 w-full skeleton mb-1" />
                <div className="h-3 w-3/4 skeleton" />
              </div>

              {/* Tags skeleton */}
              <div className="mb-4 flex gap-1">
                <div className="h-5 w-16 skeleton rounded-full" />
                <div className="h-5 w-20 skeleton rounded-full" />
              </div>

              {/* Metadata skeleton */}
              <div className="mb-4 border-t border-gray-100 pt-3">
                <div className="h-3 w-32 skeleton" />
              </div>

              {/* Actions skeleton */}
              <div className="flex gap-2 border-t border-gray-100 pt-4">
                <div className="h-8 w-16 skeleton rounded-lg" />
                <div className="h-8 w-16 skeleton rounded-lg" />
                <div className="h-8 w-16 skeleton rounded-lg" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Child info card skeleton */}
      <div className="card p-6">
        <div className="flex items-center space-x-4">
          <div className="h-16 w-16 rounded-full skeleton" />
          <div>
            <div className="h-5 w-36 skeleton mb-2" />
            <div className="h-4 w-28 skeleton mb-1" />
            <div className="h-4 w-20 skeleton" />
          </div>
        </div>
      </div>
    </div>
  );
}
