export default function MenuLoading() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header skeleton */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-40 skeleton mb-2" />
            <div className="h-4 w-64 skeleton" />
          </div>
          <div className="h-10 w-24 skeleton rounded-lg" />
        </div>
      </div>

      {/* Quick stats skeleton */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {[1, 2, 3].map((i) => (
          <div
            key={i}
            className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200"
          >
            <div className="flex items-center">
              <div className="h-10 w-10 rounded-full skeleton" />
              <div className="ml-3">
                <div className="h-4 w-24 skeleton mb-2" />
                <div className="h-6 w-12 skeleton" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Weekly menu view skeleton */}
      <div className="card">
        {/* Card header skeleton */}
        <div className="card-header">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="h-12 w-12 rounded-full skeleton" />
              <div>
                <div className="h-5 w-32 skeleton mb-2" />
                <div className="h-4 w-40 skeleton" />
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <div className="h-10 w-10 skeleton rounded-md" />
              <div className="h-10 w-10 skeleton rounded-md" />
            </div>
          </div>
        </div>

        <div className="card-body p-0">
          {/* Desktop table skeleton */}
          <div className="hidden md:block overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr>
                  <th className="w-24 border-b border-r border-gray-200 bg-gray-50 p-2">
                    <div className="h-4 w-16 skeleton" />
                  </th>
                  {[1, 2, 3, 4, 5].map((day) => (
                    <th
                      key={day}
                      className="border-b border-r border-gray-200 bg-gray-50 p-3 text-center"
                    >
                      <div className="h-4 w-12 skeleton mx-auto mb-2" />
                      <div className="h-3 w-16 skeleton mx-auto" />
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[1, 2, 3].map((mealType) => (
                  <tr key={mealType}>
                    <td className="border-b border-r border-gray-200 bg-gray-50 p-3">
                      <div className="flex items-center space-x-2">
                        <div className="h-5 w-5 skeleton rounded" />
                        <div className="h-4 w-16 skeleton" />
                      </div>
                    </td>
                    {[1, 2, 3, 4, 5].map((day) => (
                      <td
                        key={day}
                        className="border-b border-r border-gray-200 align-top"
                      >
                        <div className="min-h-[120px] p-2">
                          <div className="space-y-2">
                            <div className="h-10 skeleton rounded-lg" />
                            <div className="h-10 skeleton rounded-lg" />
                          </div>
                        </div>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile stacked view skeleton */}
          <div className="md:hidden divide-y divide-gray-200">
            {[1, 2, 3].map((day) => (
              <div key={day} className="p-4">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center">
                    <div className="h-5 w-12 skeleton" />
                    <div className="ml-2 h-4 w-16 skeleton" />
                  </div>
                </div>

                <div className="space-y-4">
                  {[1, 2, 3].map((meal) => (
                    <div key={meal}>
                      <div className="flex items-center space-x-2 mb-2">
                        <div className="h-5 w-5 skeleton rounded" />
                        <div className="h-4 w-20 skeleton" />
                      </div>
                      <div className="space-y-2 pl-7">
                        <div className="h-10 skeleton rounded-lg" />
                        <div className="h-10 skeleton rounded-lg" />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {/* Legend skeleton */}
          <div className="border-t border-gray-200 p-4 bg-gray-50">
            <div className="h-5 w-40 skeleton mb-3" />
            <div className="flex flex-wrap gap-2">
              {[1, 2].map((i) => (
                <div key={i} className="h-6 w-24 skeleton rounded-full" />
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Help section skeleton */}
      <div className="mt-8 rounded-lg bg-blue-50 p-4">
        <div className="flex">
          <div className="h-5 w-5 skeleton rounded-full" />
          <div className="ml-3 flex-1">
            <div className="h-4 w-48 skeleton mb-2" />
            <div className="h-3 w-full skeleton" />
            <div className="h-3 w-3/4 skeleton mt-1" />
          </div>
        </div>
      </div>
    </div>
  );
}
