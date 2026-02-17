export default function MedicalProtocolsLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="h-8 w-44 skeleton mb-2" />
            <div className="h-4 w-72 skeleton" />
          </div>
          {/* Child selector skeleton */}
          <div className="h-10 w-48 skeleton rounded-lg" />
        </div>
      </div>

      {/* Summary Cards skeleton */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {[1, 2].map((i) => (
          <div key={i} className="card p-4">
            <div className="flex items-center space-x-3">
              <div className="h-10 w-10 rounded-full skeleton" />
              <div>
                <div className="h-4 w-32 skeleton mb-2" />
                <div className="h-6 w-12 skeleton" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Filter tabs skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-6 w-32 skeleton" />
        <div className="flex items-center space-x-2">
          <div className="h-9 w-16 skeleton rounded-lg" />
          <div className="h-9 w-24 skeleton rounded-lg" />
          <div className="h-9 w-20 skeleton rounded-lg" />
        </div>
      </div>

      {/* Action notice skeleton */}
      <div className="mb-6 rounded-lg bg-gray-100 p-4">
        <div className="flex">
          <div className="h-5 w-5 skeleton rounded-full flex-shrink-0" />
          <div className="ml-3 flex-1">
            <div className="h-4 w-36 skeleton mb-2" />
            <div className="h-4 w-full skeleton" />
          </div>
        </div>
      </div>

      {/* Protocol cards skeleton */}
      <div className="space-y-4">
        {[1, 2].map((i) => (
          <div key={i} className="card">
            <div className="card-body">
              <div className="flex items-start justify-between">
                {/* Protocol info skeleton */}
                <div className="flex items-start space-x-4">
                  {/* Icon skeleton */}
                  <div className="flex-shrink-0">
                    <div className="h-12 w-12 rounded-full skeleton" />
                  </div>
                  {/* Details skeleton */}
                  <div className="flex-1">
                    <div className="h-5 w-40 skeleton mb-2" />
                    <div className="h-4 w-28 skeleton mb-2" />
                    <div className="h-3 w-48 skeleton" />
                  </div>
                </div>
                {/* Status badge skeleton */}
                <div className="h-6 w-24 skeleton rounded-full" />
              </div>

              {/* Weight and dosing info skeleton */}
              <div className="mt-4 border-t border-gray-100 pt-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <div className="h-3 w-16 skeleton mb-1" />
                    <div className="h-5 w-20 skeleton" />
                  </div>
                  <div>
                    <div className="h-3 w-24 skeleton mb-1" />
                    <div className="h-5 w-28 skeleton" />
                  </div>
                </div>
              </div>

              {/* Actions skeleton */}
              <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <div className="h-9 w-28 skeleton rounded-lg" />
                <div className="h-9 w-36 skeleton rounded-lg" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
