'use client';

import { useEffect, useState, useCallback } from 'react';
import Link from 'next/link';
import type {
  DirectorDashboard,
  GroupOccupancy,
  AlertItem,
  OccupancySummary,
  AlertPriority,
} from '../../lib/types';
import { OccupancyCard } from '../../components/OccupancyCard';
import { GroupOccupancyList } from '../../components/GroupOccupancyRow';
import { AlertList, AlertSummaryBadge } from '../../components/AlertList';

// Mock data for dashboard - will be replaced with API calls
const mockOccupancySummary: OccupancySummary = {
  facilityId: 'facility-1',
  totalChildren: 67,
  totalCapacity: 80,
  overallOccupancyPercentage: 84,
  groupsAtCapacity: 1,
  groupsNearCapacity: 2,
  totalGroups: 5,
  averageStaffRatio: '1:6',
  snapshotTime: new Date().toISOString(),
};

const mockGroups: GroupOccupancy[] = [
  {
    groupId: 'group-1',
    groupName: 'Les Petits Explorateurs',
    ageGroup: 'poupon',
    currentCount: 8,
    capacity: 10,
    occupancyPercentage: 80,
    status: 'near_capacity',
    staffCount: 3,
    staffRatio: '1:3',
    roomNumber: '101',
    lastUpdated: new Date().toISOString(),
  },
  {
    groupId: 'group-2',
    groupName: 'Les Papillons',
    ageGroup: 'bambin',
    currentCount: 15,
    capacity: 15,
    occupancyPercentage: 100,
    status: 'at_capacity',
    staffCount: 3,
    staffRatio: '1:5',
    roomNumber: '102',
    lastUpdated: new Date().toISOString(),
  },
  {
    groupId: 'group-3',
    groupName: 'Les Coccinelles',
    ageGroup: 'prescolaire',
    currentCount: 16,
    capacity: 20,
    occupancyPercentage: 80,
    status: 'normal',
    staffCount: 2,
    staffRatio: '1:8',
    roomNumber: '103',
    lastUpdated: new Date().toISOString(),
  },
  {
    groupId: 'group-4',
    groupName: 'Les Tournesols',
    ageGroup: 'prescolaire',
    currentCount: 18,
    capacity: 20,
    occupancyPercentage: 90,
    status: 'near_capacity',
    staffCount: 2,
    staffRatio: '1:9',
    roomNumber: '104',
    lastUpdated: new Date().toISOString(),
  },
  {
    groupId: 'group-5',
    groupName: 'Les Arc-en-Ciel',
    ageGroup: 'scolaire',
    currentCount: 10,
    capacity: 15,
    occupancyPercentage: 67,
    status: 'normal',
    staffCount: 1,
    staffRatio: '1:10',
    roomNumber: '105',
    lastUpdated: new Date().toISOString(),
  },
];

const mockAlerts: AlertItem[] = [
  {
    alertId: 'alert-1',
    alertType: 'occupancy',
    priority: 'high',
    title: 'Group At Capacity',
    message: 'Les Papillons has reached maximum capacity. No additional children can be admitted.',
    groupId: 'group-2',
    groupName: 'Les Papillons',
    createdAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(), // 15 mins ago
    isAcknowledged: false,
  },
  {
    alertId: 'alert-2',
    alertType: 'staffing',
    priority: 'medium',
    title: 'Staff Ratio Warning',
    message: 'Les Tournesols is approaching the maximum staff-to-child ratio for this age group.',
    groupId: 'group-4',
    groupName: 'Les Tournesols',
    createdAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(), // 30 mins ago
    isAcknowledged: false,
  },
  {
    alertId: 'alert-3',
    alertType: 'attendance',
    priority: 'low',
    title: 'Late Arrival Expected',
    message: 'Parent notified: Emma will arrive 30 minutes late today due to medical appointment.',
    createdAt: new Date(Date.now() - 45 * 60 * 1000).toISOString(), // 45 mins ago
    isAcknowledged: true,
  },
];

const mockDashboard: DirectorDashboard = {
  summary: mockOccupancySummary,
  groups: mockGroups,
  alerts: mockAlerts,
  alertCountByPriority: {
    critical: 0,
    high: 1,
    medium: 1,
    low: 1,
  },
  generatedAt: new Date().toISOString(),
};

// KPI data for the summary cards
interface KPIStat {
  label: string;
  value: string;
  subValue?: string;
  icon: 'children' | 'capacity' | 'staff' | 'alerts';
  color: 'blue' | 'green' | 'purple' | 'orange';
}

function KPIStatIcon({ icon, color }: { icon: KPIStat['icon']; color: KPIStat['color'] }) {
  const colorClasses = {
    blue: 'bg-blue-100 text-blue-600',
    green: 'bg-green-100 text-green-600',
    purple: 'bg-purple-100 text-purple-600',
    orange: 'bg-orange-100 text-orange-600',
  };

  const iconPaths: Record<KPIStat['icon'], JSX.Element> = {
    children: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
      />
    ),
    capacity: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
      />
    ),
    staff: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
      />
    ),
    alerts: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
      />
    ),
  };

  return (
    <div
      className={`flex h-12 w-12 items-center justify-center rounded-xl ${colorClasses[color]}`}
    >
      <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {iconPaths[icon]}
      </svg>
    </div>
  );
}

function KPICard({ stat }: { stat: KPIStat }) {
  return (
    <div className="card p-5">
      <div className="flex items-center space-x-4">
        <KPIStatIcon icon={stat.icon} color={stat.color} />
        <div>
          <p className="text-sm font-medium text-gray-500">{stat.label}</p>
          <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
          {stat.subValue && (
            <p className="text-xs text-gray-400">{stat.subValue}</p>
          )}
        </div>
      </div>
    </div>
  );
}

function formatDate(): string {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function getGreeting(): string {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

export default function DirectorDashboardPage() {
  const [dashboard, setDashboard] = useState<DirectorDashboard>(mockDashboard);
  const [isLoading, setIsLoading] = useState(false);
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());

  // Calculate KPI stats from dashboard data
  const kpiStats: KPIStat[] = [
    {
      label: 'Total Children',
      value: dashboard.summary.totalChildren.toString(),
      subValue: `of ${dashboard.summary.totalCapacity} capacity`,
      icon: 'children',
      color: 'blue',
    },
    {
      label: 'Occupancy Rate',
      value: `${dashboard.summary.overallOccupancyPercentage}%`,
      subValue: `${dashboard.summary.totalGroups} active groups`,
      icon: 'capacity',
      color: 'green',
    },
    {
      label: 'Staff Ratio',
      value: dashboard.summary.averageStaffRatio || 'N/A',
      subValue: 'Average across facility',
      icon: 'staff',
      color: 'purple',
    },
    {
      label: 'Active Alerts',
      value: dashboard.alerts.filter((a) => !a.isAcknowledged).length.toString(),
      subValue: `${dashboard.summary.groupsAtCapacity} groups at capacity`,
      icon: 'alerts',
      color: 'orange',
    },
  ];

  // Handler for acknowledging alerts
  const handleAcknowledgeAlert = useCallback((alertId: string) => {
    setDashboard((prev) => ({
      ...prev,
      alerts: prev.alerts.map((alert) =>
        alert.alertId === alertId
          ? { ...alert, isAcknowledged: true }
          : alert
      ),
    }));
  }, []);

  // Refresh handler - will be connected to API later
  const handleRefresh = useCallback(() => {
    setIsLoading(true);
    // Simulate API call
    setTimeout(() => {
      setLastUpdated(new Date());
      setIsLoading(false);
    }, 500);
  }, []);

  // Transform groups for OccupancyCard format
  const occupancyCardData = {
    facilityName: 'LAYA Childcare Center',
    totalCurrent: dashboard.summary.totalChildren,
    totalCapacity: dashboard.summary.totalCapacity,
    groups: dashboard.groups.map((g) => ({
      id: g.groupId,
      name: g.groupName,
      currentCount: g.currentCount,
      capacity: g.capacity,
    })),
    lastUpdated: dashboard.summary.snapshotTime,
  };

  // Count alerts by priority for badges
  const criticalAlerts = dashboard.alerts.filter(
    (a) => a.priority === 'critical' && !a.isAcknowledged
  ).length;
  const highAlerts = dashboard.alerts.filter(
    (a) => a.priority === 'high' && !a.isAcknowledged
  ).length;

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {getGreeting()}, Director
            </h1>
            <p className="mt-1 text-gray-600">{formatDate()}</p>
          </div>
          <div className="mt-4 flex items-center space-x-3 sm:mt-0">
            <button
              onClick={handleRefresh}
              disabled={isLoading}
              className="btn btn-secondary inline-flex items-center"
            >
              <svg
                className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`}
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
            <Link href="/settings" className="btn btn-primary">
              Settings
            </Link>
          </div>
        </div>
      </div>

      {/* KPI Summary Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        {kpiStats.map((stat) => (
          <KPICard key={stat.label} stat={stat} />
        ))}
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {/* Left Column - Occupancy Overview */}
        <div className="lg:col-span-2 space-y-8">
          {/* Facility Occupancy Card */}
          <OccupancyCard occupancy={occupancyCardData} />

          {/* Groups Detail Section */}
          <div className="card">
            <div className="card-header flex items-center justify-between">
              <div>
                <h3 className="section-title">Group Occupancy Details</h3>
                <p className="text-sm text-gray-500">
                  Real-time occupancy by classroom
                </p>
              </div>
              <div className="flex items-center space-x-2">
                {dashboard.summary.groupsAtCapacity > 0 && (
                  <span className="badge badge-error">
                    {dashboard.summary.groupsAtCapacity} at capacity
                  </span>
                )}
                {dashboard.summary.groupsNearCapacity > 0 && (
                  <span className="badge badge-warning">
                    {dashboard.summary.groupsNearCapacity} near capacity
                  </span>
                )}
              </div>
            </div>
            <div className="card-body">
              <GroupOccupancyList
                groups={dashboard.groups}
                showStaffInfo={true}
                showRoomNumber={true}
              />
            </div>
          </div>
        </div>

        {/* Right Column - Alerts */}
        <div className="space-y-6">
          {/* Alerts Section */}
          <div className="card">
            <div className="card-header">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="section-title">Active Alerts</h3>
                  <p className="text-sm text-gray-500">
                    Requires attention
                  </p>
                </div>
                <div className="flex items-center space-x-2">
                  {criticalAlerts > 0 && (
                    <AlertSummaryBadge count={criticalAlerts} priority="critical" />
                  )}
                  {highAlerts > 0 && (
                    <AlertSummaryBadge count={highAlerts} priority="high" />
                  )}
                </div>
              </div>
            </div>
            <div className="card-body">
              <AlertList
                alerts={dashboard.alerts}
                onAcknowledge={handleAcknowledgeAlert}
                showAcknowledgeButton={true}
                maxItems={5}
                emptyMessage="No active alerts - all systems normal"
              />
            </div>
          </div>

          {/* Quick Stats Card */}
          <div className="card">
            <div className="card-header">
              <h3 className="section-title">Capacity Overview</h3>
            </div>
            <div className="card-body">
              <div className="space-y-4">
                {/* Available Spots */}
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Available Spots</span>
                  <span className="text-lg font-semibold text-green-600">
                    {dashboard.summary.totalCapacity - dashboard.summary.totalChildren}
                  </span>
                </div>

                {/* Groups Status */}
                <div className="border-t border-gray-100 pt-4">
                  <div className="grid grid-cols-2 gap-4 text-center">
                    <div className="p-3 bg-green-50 rounded-lg">
                      <p className="text-2xl font-bold text-green-600">
                        {dashboard.summary.totalGroups -
                          dashboard.summary.groupsAtCapacity -
                          dashboard.summary.groupsNearCapacity}
                      </p>
                      <p className="text-xs text-gray-500">Normal</p>
                    </div>
                    <div className="p-3 bg-yellow-50 rounded-lg">
                      <p className="text-2xl font-bold text-yellow-600">
                        {dashboard.summary.groupsNearCapacity}
                      </p>
                      <p className="text-xs text-gray-500">Near Capacity</p>
                    </div>
                    <div className="p-3 bg-orange-50 rounded-lg">
                      <p className="text-2xl font-bold text-orange-600">
                        {dashboard.summary.groupsAtCapacity}
                      </p>
                      <p className="text-xs text-gray-500">At Capacity</p>
                    </div>
                    <div className="p-3 bg-blue-50 rounded-lg">
                      <p className="text-2xl font-bold text-blue-600">
                        {dashboard.summary.totalGroups}
                      </p>
                      <p className="text-xs text-gray-500">Total Groups</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Last Updated Info */}
          <div className="flex items-center justify-center text-sm text-gray-400">
            <svg
              className="mr-1.5 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            Last updated:{' '}
            {lastUpdated.toLocaleTimeString('en-US', {
              hour: 'numeric',
              minute: '2-digit',
              second: '2-digit',
              hour12: true,
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
