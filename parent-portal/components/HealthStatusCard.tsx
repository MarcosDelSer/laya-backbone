/**
 * Health Status Card Component
 *
 * Displays the health status of a service with visual indicators
 * and detailed information.
 */

interface HealthStatusCardProps {
  serviceName: string;
  status: 'healthy' | 'degraded' | 'unhealthy' | 'loading' | 'error';
  connected?: boolean;
  error?: string;
  details?: any;
  url?: string;
  responseTime?: number;
}

export function HealthStatusCard({
  serviceName,
  status,
  connected,
  error,
  details,
  url,
  responseTime,
}: HealthStatusCardProps) {
  // Status configuration
  const statusConfig = {
    healthy: {
      label: 'Healthy',
      bgColor: 'bg-green-50',
      borderColor: 'border-green-200',
      textColor: 'text-green-700',
      iconColor: 'bg-green-100 text-green-600',
      icon: (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      ),
    },
    degraded: {
      label: 'Degraded',
      bgColor: 'bg-yellow-50',
      borderColor: 'border-yellow-200',
      textColor: 'text-yellow-700',
      iconColor: 'bg-yellow-100 text-yellow-600',
      icon: (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
        />
      ),
    },
    unhealthy: {
      label: 'Unhealthy',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200',
      textColor: 'text-red-700',
      iconColor: 'bg-red-100 text-red-600',
      icon: (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      ),
    },
    loading: {
      label: 'Loading',
      bgColor: 'bg-gray-50',
      borderColor: 'border-gray-200',
      textColor: 'text-gray-700',
      iconColor: 'bg-gray-100 text-gray-600',
      icon: (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
        />
      ),
    },
    error: {
      label: 'Error',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200',
      textColor: 'text-red-700',
      iconColor: 'bg-red-100 text-red-600',
      icon: (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      ),
    },
  };

  const config = statusConfig[status];

  return (
    <div className={`card border-2 ${config.borderColor}`}>
      <div className={`card-header ${config.bgColor}`}>
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900">{serviceName}</h3>
          <div className="flex items-center space-x-2">
            <div className={`flex h-8 w-8 items-center justify-center rounded-full ${config.iconColor}`}>
              <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {config.icon}
              </svg>
            </div>
            <span className={`text-sm font-medium ${config.textColor}`}>
              {config.label}
            </span>
          </div>
        </div>
      </div>
      <div className="card-body">
        <dl className="grid grid-cols-1 gap-3 text-sm">
          {/* Connection Status */}
          {connected !== undefined && (
            <div className="flex items-center justify-between">
              <dt className="font-medium text-gray-500">Connection:</dt>
              <dd className={`font-semibold ${connected ? 'text-green-600' : 'text-red-600'}`}>
                {connected ? 'Connected' : 'Disconnected'}
              </dd>
            </div>
          )}

          {/* Service URL */}
          {url && (
            <div className="flex items-start justify-between">
              <dt className="font-medium text-gray-500">URL:</dt>
              <dd className="text-gray-900 text-right break-all max-w-xs font-mono text-xs">
                {url}
              </dd>
            </div>
          )}

          {/* Response Time */}
          {responseTime !== undefined && (
            <div className="flex items-center justify-between">
              <dt className="font-medium text-gray-500">Response Time:</dt>
              <dd className="text-gray-900 font-semibold">
                &lt; 5s
              </dd>
            </div>
          )}

          {/* Error Message */}
          {error && (
            <div className="mt-2 rounded-md bg-red-50 p-3 border border-red-200">
              <div className="flex">
                <div className="flex-shrink-0">
                  <svg
                    className="h-5 w-5 text-red-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </div>
                <div className="ml-3">
                  <h4 className="text-sm font-medium text-red-800">Error Details</h4>
                  <p className="mt-1 text-sm text-red-700 font-mono break-all">{error}</p>
                </div>
              </div>
            </div>
          )}

          {/* Service Details */}
          {details && details.status && (
            <div className="mt-2 rounded-md bg-gray-50 p-3 border border-gray-200">
              <h4 className="text-sm font-medium text-gray-700 mb-2">Service Details</h4>
              <dl className="space-y-1 text-xs">
                {details.status && (
                  <div className="flex justify-between">
                    <dt className="text-gray-500">Status:</dt>
                    <dd className="font-semibold text-gray-900">{details.status}</dd>
                  </div>
                )}
                {details.timestamp && (
                  <div className="flex justify-between">
                    <dt className="text-gray-500">Last Check:</dt>
                    <dd className="text-gray-900 font-mono">
                      {new Date(details.timestamp).toLocaleTimeString()}
                    </dd>
                  </div>
                )}
                {details.version && (
                  <div className="flex justify-between">
                    <dt className="text-gray-500">Version:</dt>
                    <dd className="text-gray-900 font-mono">{details.version}</dd>
                  </div>
                )}
                {details.service && (
                  <div className="flex justify-between">
                    <dt className="text-gray-500">Service:</dt>
                    <dd className="text-gray-900">{details.service}</dd>
                  </div>
                )}
              </dl>
            </div>
          )}
        </dl>
      </div>
    </div>
  );
}
