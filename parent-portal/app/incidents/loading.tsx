export default function IncidentsLoading() {
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

      {/* Summary banner skeleton */}
      <div className="mb-6 h-14 skeleton rounded-lg" />

      {/* Filter bar skeleton */}
      <div className="mb-6 flex items-center justify-between">
        <div className="h-4 w-32 skeleton" />
        <div className="h-8 w-20 skeleton rounded-lg" />
      </div>

      {/* Incident cards skeleton */}
      <div className="space-y-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="card relative overflow-hidden">
            {/* Severity indicator bar skeleton */}
            <div className="absolute left-0 top-0 bottom-0 w-1 skeleton" />

            <div className="card-body pl-5">
              <div className="flex items-start justify-between">
                {/* Incident info skeleton */}
                <div className="flex items-start space-x-4">
                  {/* Category icon skeleton */}
                  <div className="flex-shrink-0">
                    <div className="h-12 w-12 rounded-full skeleton" />
                  </div>

                  {/* Incident details skeleton */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center space-x-2">
                      <div className="h-5 w-32 skeleton mb-1" />
                      <div className="h-5 w-16 skeleton rounded-full" />
                    </div>

                    <div className="h-4 w-24 skeleton mt-2" />

                    <div className="mt-2 flex items-center space-x-2">
                      <div className="h-4 w-4 skeleton rounded-full" />
                      <div className="h-4 w-36 skeleton" />
                    </div>

                    {/* Description skeleton */}
                    <div className="mt-3 space-y-2">
                      <div className="h-3 w-full skeleton" />
                      <div className="h-3 w-3/4 skeleton" />
                    </div>
                  </div>
                </div>

                {/* Status badge skeleton */}
                <div className="flex-shrink-0 hidden sm:block">
                  <div className="h-6 w-24 skeleton rounded-full" />
                </div>
              </div>

              {/* Actions skeleton */}
              <div className="mt-4 flex gap-2 border-t border-gray-100 pt-4">
                <div className="h-9 w-28 skeleton rounded-lg" />
                <div className="h-9 w-28 skeleton rounded-lg" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Load more skeleton */}
      <div className="mt-8 flex justify-center">
        <div className="h-10 w-40 skeleton rounded-lg" />
      </div>
    </div>
  );
}
