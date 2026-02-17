'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { HealthStatusCard } from '@/components/HealthStatusCard';

/**
 * Aggregated Health Dashboard
 *
 * Monitors and displays the health status of all LAYA services:
 * - AI Service
 * - Gibbon CMS
 * - Parent Portal (overall)
 *
 * Auto-refreshes every 60 seconds to provide real-time monitoring.
 */

interface HealthCheck {
  status: 'healthy' | 'degraded' | 'unhealthy';
  connected?: boolean;
  error?: string;
  details?: any;
  apiUrl?: string;
  gibbonUrl?: string;
  responseTime?: number;
}

interface HealthData {
  status: 'healthy' | 'degraded' | 'unhealthy';
  timestamp: string;
  service: string;
  version: string;
  checks: {
    aiService: HealthCheck;
    gibbon: HealthCheck;
  };
}

type LoadingState = 'loading' | 'loaded' | 'error';

export default function HealthDashboardPage() {
  const [healthData, setHealthData] = useState<HealthData | null>(null);
  const [loadingState, setLoadingState] = useState<LoadingState>('loading');
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const [autoRefresh, setAutoRefresh] = useState(true);

  // Fetch health data
  const fetchHealthData = async () => {
    try {
      const response = await fetch('/api/health');
      const data = await response.json();
      setHealthData(data);
      setLoadingState('loaded');
      setLastUpdate(new Date());
    } catch (error) {
      console.error('Failed to fetch health data:', error);
      setLoadingState('error');
    }
  };

  // Initial fetch
  useEffect(() => {
    fetchHealthData();
  }, []);

  // Auto-refresh every 60 seconds
  useEffect(() => {
    if (!autoRefresh) return;

    const interval = setInterval(() => {
      fetchHealthData();
    }, 60000); // 60 seconds

    return () => clearInterval(interval);
  }, [autoRefresh]);

  // Manual refresh handler
  const handleRefresh = () => {
    setLoadingState('loading');
    fetchHealthData();
  };

  // Get overall status badge configuration
  const getOverallStatusBadge = (status?: 'healthy' | 'degraded' | 'unhealthy') => {
    const config = {
      healthy: {
        label: 'All Systems Operational',
        className: 'badge-success',
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
        label: 'Partial System Outage',
        className: 'badge-warning',
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
        label: 'System Outage',
        className: 'badge-error',
        icon: (
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        ),
      },
    };

    return config[status || 'unhealthy'];
  };

  const overallBadge = getOverallStatusBadge(healthData?.status);

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="flex items-center space-x-2">
              <Link href="/" className="text-gray-500 hover:text-gray-700">
                <svg
                  className="h-5 w-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                  />
                </svg>
              </Link>
              <h1 className="text-2xl font-bold text-gray-900">
                System Health Dashboard
              </h1>
            </div>
            <p className="mt-1 text-gray-600">
              Real-time monitoring of all LAYA services
            </p>
          </div>
          <div className="mt-4 sm:mt-0 flex items-center space-x-3">
            {/* Auto-refresh toggle */}
            <label className="flex items-center space-x-2 text-sm text-gray-600">
              <input
                type="checkbox"
                checked={autoRefresh}
                onChange={(e) => setAutoRefresh(e.target.checked)}
                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <span>Auto-refresh (60s)</span>
            </label>
            {/* Refresh button */}
            <button
              onClick={handleRefresh}
              disabled={loadingState === 'loading'}
              className="btn btn-outline btn-sm"
            >
              <svg
                className={`h-4 w-4 mr-2 ${loadingState === 'loading' ? 'animate-spin' : ''}`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                />
              </svg>
              Refresh
            </button>
          </div>
        </div>
      </div>

      {/* Overall Status Card */}
      <div className="mb-8">
        <div className={`card border-2 ${
          healthData?.status === 'healthy' ? 'border-green-200' :
          healthData?.status === 'degraded' ? 'border-yellow-200' :
          'border-red-200'
        }`}>
          <div className={`card-header ${
            healthData?.status === 'healthy' ? 'bg-green-50' :
            healthData?.status === 'degraded' ? 'bg-yellow-50' :
            'bg-red-50'
          }`}>
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <div className={`flex h-12 w-12 items-center justify-center rounded-full ${
                  healthData?.status === 'healthy' ? 'bg-green-100 text-green-600' :
                  healthData?.status === 'degraded' ? 'bg-yellow-100 text-yellow-600' :
                  'bg-red-100 text-red-600'
                }`}>
                  <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {overallBadge.icon}
                  </svg>
                </div>
                <div>
                  <h2 className="text-xl font-bold text-gray-900">Overall Status</h2>
                  <p className={`text-sm font-semibold ${
                    healthData?.status === 'healthy' ? 'text-green-700' :
                    healthData?.status === 'degraded' ? 'text-yellow-700' :
                    'text-red-700'
                  }`}>
                    {loadingState === 'loading' ? 'Checking...' : overallBadge.label}
                  </p>
                </div>
              </div>
              {lastUpdate && (
                <div className="text-right text-sm text-gray-500">
                  <div>Last updated:</div>
                  <div className="font-mono text-gray-900">
                    {lastUpdate.toLocaleTimeString()}
                  </div>
                </div>
              )}
            </div>
          </div>
          <div className="card-body">
            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
              <div>
                <dt className="font-medium text-gray-500">Service</dt>
                <dd className="mt-1 text-lg font-semibold text-gray-900">
                  {healthData?.service || 'Loading...'}
                </dd>
              </div>
              <div>
                <dt className="font-medium text-gray-500">Version</dt>
                <dd className="mt-1 text-lg font-semibold text-gray-900 font-mono">
                  {healthData?.version || 'Loading...'}
                </dd>
              </div>
              <div>
                <dt className="font-medium text-gray-500">Timestamp</dt>
                <dd className="mt-1 text-lg font-semibold text-gray-900 font-mono">
                  {healthData?.timestamp
                    ? new Date(healthData.timestamp).toLocaleString()
                    : 'Loading...'}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      {/* Service Health Cards */}
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Service Status</h2>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {/* Parent Portal Health */}
        <HealthStatusCard
          serviceName="Parent Portal"
          status={loadingState === 'loading' ? 'loading' : loadingState === 'error' ? 'error' : healthData?.status || 'unhealthy'}
          connected={loadingState === 'loaded'}
          url={typeof window !== 'undefined' ? window.location.origin : 'N/A'}
          details={healthData && {
            status: healthData.status,
            timestamp: healthData.timestamp,
            version: healthData.version,
            service: healthData.service,
          }}
        />

        {/* AI Service Health */}
        <HealthStatusCard
          serviceName="AI Service"
          status={
            loadingState === 'loading'
              ? 'loading'
              : loadingState === 'error'
              ? 'error'
              : healthData?.checks.aiService.status || 'unhealthy'
          }
          connected={healthData?.checks.aiService.connected}
          error={healthData?.checks.aiService.error}
          url={healthData?.checks.aiService.apiUrl}
          responseTime={healthData?.checks.aiService.responseTime}
          details={healthData?.checks.aiService.details}
        />

        {/* Gibbon CMS Health */}
        <HealthStatusCard
          serviceName="Gibbon CMS"
          status={
            loadingState === 'loading'
              ? 'loading'
              : loadingState === 'error'
              ? 'error'
              : healthData?.checks.gibbon.status || 'unhealthy'
          }
          connected={healthData?.checks.gibbon.connected}
          error={healthData?.checks.gibbon.error}
          url={healthData?.checks.gibbon.gibbonUrl}
          responseTime={healthData?.checks.gibbon.responseTime}
          details={healthData?.checks.gibbon.details}
        />
      </div>

      {/* Information Section */}
      <div className="mt-8 card">
        <div className="card-header">
          <h3 className="section-title">About Health Monitoring</h3>
        </div>
        <div className="card-body">
          <div className="prose prose-sm max-w-none text-gray-600">
            <p>
              This dashboard provides real-time monitoring of all LAYA services. Health checks are
              performed automatically every 60 seconds to ensure system reliability.
            </p>
            <div className="mt-4 space-y-2">
              <div className="flex items-start space-x-2">
                <svg
                  className="h-5 w-5 text-green-600 mt-0.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <div>
                  <span className="font-semibold text-green-700">Healthy:</span> All systems operating normally
                </div>
              </div>
              <div className="flex items-start space-x-2">
                <svg
                  className="h-5 w-5 text-yellow-600 mt-0.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </svg>
                <div>
                  <span className="font-semibold text-yellow-700">Degraded:</span> Service is running but experiencing issues
                </div>
              </div>
              <div className="flex items-start space-x-2">
                <svg
                  className="h-5 w-5 text-red-600 mt-0.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <div>
                  <span className="font-semibold text-red-700">Unhealthy:</span> Service is down or unreachable
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
