export default function ServiceAgreementDetailLoading() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Back link skeleton */}
      <div className="mb-6">
        <div className="h-5 w-48 skeleton" />
      </div>

      {/* Header skeleton */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <div className="h-8 w-48 skeleton mb-2" />
            <div className="h-4 w-32 skeleton" />
          </div>
          <div className="flex items-center space-x-3">
            <div className="h-6 w-28 skeleton rounded-full" />
            <div className="h-8 w-8 rounded-full skeleton" />
          </div>
        </div>

        {/* Quick info bar skeleton */}
        <div className="mt-4 flex flex-wrap gap-4">
          <div className="h-5 w-32 skeleton" />
          <div className="h-5 w-48 skeleton" />
        </div>
      </div>

      {/* Alert skeleton */}
      <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
        <div className="flex">
          <div className="h-5 w-5 skeleton rounded-full flex-shrink-0" />
          <div className="ml-3 flex-1">
            <div className="h-4 w-36 skeleton mb-2" />
            <div className="h-4 w-full skeleton" />
          </div>
        </div>
      </div>

      {/* Articles skeleton */}
      <div className="space-y-4">
        {/* Article 1 - expanded */}
        <div className="border border-gray-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 bg-gray-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <div className="h-7 w-7 rounded-full skeleton" />
                <div className="h-5 w-40 skeleton" />
              </div>
              <div className="h-5 w-5 skeleton" />
            </div>
          </div>
          <div className="px-4 py-4 bg-white">
            <div className="space-y-4">
              {/* Child info skeleton */}
              <div>
                <div className="h-4 w-28 skeleton mb-2" />
                <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                  <div className="flex justify-between">
                    <div className="h-4 w-16 skeleton" />
                    <div className="h-4 w-32 skeleton" />
                  </div>
                  <div className="flex justify-between">
                    <div className="h-4 w-24 skeleton" />
                    <div className="h-4 w-28 skeleton" />
                  </div>
                </div>
              </div>
              {/* Parent info skeleton */}
              <div>
                <div className="h-4 w-32 skeleton mb-2" />
                <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                  <div className="flex justify-between">
                    <div className="h-4 w-16 skeleton" />
                    <div className="h-4 w-32 skeleton" />
                  </div>
                  <div className="flex justify-between">
                    <div className="h-4 w-20 skeleton" />
                    <div className="h-4 w-48 skeleton" />
                  </div>
                  <div className="flex justify-between">
                    <div className="h-4 w-16 skeleton" />
                    <div className="h-4 w-28 skeleton" />
                  </div>
                </div>
              </div>
              {/* Provider info skeleton */}
              <div>
                <div className="h-4 w-32 skeleton mb-2" />
                <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                  <div className="flex justify-between">
                    <div className="h-4 w-16 skeleton" />
                    <div className="h-4 w-36 skeleton" />
                  </div>
                  <div className="flex justify-between">
                    <div className="h-4 w-20 skeleton" />
                    <div className="h-4 w-48 skeleton" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Collapsed articles skeleton */}
        {[2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((i) => (
          <div key={i} className="border border-gray-200 rounded-lg overflow-hidden">
            <div className="px-4 py-3 bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <div className="h-7 w-7 rounded-full skeleton" />
                  <div className="h-5 w-40 skeleton" />
                </div>
                <div className="h-5 w-5 skeleton" />
              </div>
            </div>
          </div>
        ))}

        {/* Article 13 - Signatures (expanded) */}
        <div className="border border-gray-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 bg-gray-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <div className="h-7 w-7 rounded-full skeleton" />
                <div className="h-5 w-24 skeleton" />
              </div>
              <div className="h-5 w-5 skeleton" />
            </div>
          </div>
          <div className="px-4 py-4 bg-white">
            <div className="space-y-4">
              {/* Signature card skeleton */}
              <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center">
                    <div className="h-10 w-10 rounded-full skeleton" />
                    <div className="ml-3">
                      <div className="h-4 w-36 skeleton mb-1" />
                      <div className="h-3 w-16 skeleton" />
                    </div>
                  </div>
                  <div className="h-5 w-16 skeleton rounded-full" />
                </div>
                <div className="space-y-1">
                  <div className="h-3 w-40 skeleton" />
                  <div className="h-3 w-28 skeleton" />
                </div>
              </div>

              {/* Signature status skeleton */}
              <div className="p-3 bg-gray-100 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="h-4 w-32 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
                <div className="flex items-center justify-between">
                  <div className="h-4 w-36 skeleton" />
                  <div className="h-4 w-16 skeleton" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Annexes skeleton */}
      <div className="mt-8">
        <div className="h-6 w-32 skeleton mb-4" />
        <div className="space-y-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="border border-gray-200 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <div className="h-4 w-48 skeleton" />
                <div className="h-5 w-16 skeleton rounded-full" />
              </div>
              <div className="bg-gray-50 rounded-lg p-3 space-y-2">
                <div className="flex justify-between">
                  <div className="h-4 w-36 skeleton" />
                  <div className="h-4 w-12 skeleton" />
                </div>
                <div className="flex justify-between">
                  <div className="h-4 w-40 skeleton" />
                  <div className="h-4 w-12 skeleton" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Action buttons skeleton */}
      <div className="mt-8 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
        <div className="h-10 w-36 skeleton rounded-lg" />
        <div className="h-10 w-36 skeleton rounded-lg" />
        <div className="h-10 w-20 skeleton rounded-lg" />
      </div>

      {/* Footer skeleton */}
      <div className="mt-6 pt-4 border-t border-gray-100">
        <div className="h-3 w-64 skeleton" />
      </div>
    </div>
  );
}
