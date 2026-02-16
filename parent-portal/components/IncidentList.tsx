'use client';

import { useState, useMemo } from 'react';
import { IncidentCard } from './IncidentCard';
import {
  IncidentListItem,
  IncidentSeverity,
  IncidentStatus,
} from '../lib/types';

export interface IncidentFilters {
  dateFrom?: string;
  dateTo?: string;
  severity?: IncidentSeverity | 'all';
  status?: IncidentStatus | 'all';
}

export interface IncidentListProps {
  incidents: IncidentListItem[];
  onAcknowledge?: (incidentId: string) => void;
  onViewDetails?: (incidentId: string) => void;
  showFilters?: boolean;
  emptyMessage?: string;
}

/**
 * Severity options for filter dropdown.
 */
const SEVERITY_OPTIONS: { value: IncidentSeverity | 'all'; label: string }[] = [
  { value: 'all', label: 'All Severities' },
  { value: 'minor', label: 'Minor' },
  { value: 'moderate', label: 'Moderate' },
  { value: 'serious', label: 'Serious' },
  { value: 'severe', label: 'Severe' },
];

/**
 * Status options for filter dropdown.
 */
const STATUS_OPTIONS: { value: IncidentStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'All Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'acknowledged', label: 'Acknowledged' },
  { value: 'resolved', label: 'Resolved' },
];

/**
 * Filter bar component for incident filtering.
 */
function FilterBar({
  filters,
  onFilterChange,
}: {
  filters: IncidentFilters;
  onFilterChange: (filters: IncidentFilters) => void;
}) {
  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 mb-6">
      <div className="flex items-center justify-between mb-3">
        <h4 className="text-sm font-medium text-gray-700">Filter Incidents</h4>
        <button
          type="button"
          onClick={() =>
            onFilterChange({
              dateFrom: undefined,
              dateTo: undefined,
              severity: 'all',
              status: 'all',
            })
          }
          className="text-sm text-primary-600 hover:text-primary-700"
        >
          Clear Filters
        </button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Date From */}
        <div>
          <label
            htmlFor="dateFrom"
            className="block text-xs font-medium text-gray-600 mb-1"
          >
            From Date
          </label>
          <input
            type="date"
            id="dateFrom"
            value={filters.dateFrom || ''}
            onChange={(e) =>
              onFilterChange({ ...filters, dateFrom: e.target.value || undefined })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
        </div>

        {/* Date To */}
        <div>
          <label
            htmlFor="dateTo"
            className="block text-xs font-medium text-gray-600 mb-1"
          >
            To Date
          </label>
          <input
            type="date"
            id="dateTo"
            value={filters.dateTo || ''}
            onChange={(e) =>
              onFilterChange({ ...filters, dateTo: e.target.value || undefined })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
        </div>

        {/* Severity Filter */}
        <div>
          <label
            htmlFor="severity"
            className="block text-xs font-medium text-gray-600 mb-1"
          >
            Severity
          </label>
          <select
            id="severity"
            value={filters.severity || 'all'}
            onChange={(e) =>
              onFilterChange({
                ...filters,
                severity: e.target.value as IncidentSeverity | 'all',
              })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          >
            {SEVERITY_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        {/* Status Filter */}
        <div>
          <label
            htmlFor="status"
            className="block text-xs font-medium text-gray-600 mb-1"
          >
            Status
          </label>
          <select
            id="status"
            value={filters.status || 'all'}
            onChange={(e) =>
              onFilterChange({
                ...filters,
                status: e.target.value as IncidentStatus | 'all',
              })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          >
            {STATUS_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
}

/**
 * Empty state component when no incidents match filters.
 */
function EmptyState({ message }: { message: string }) {
  return (
    <div className="text-center py-12">
      <svg
        className="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      <h3 className="mt-2 text-sm font-medium text-gray-900">No incidents</h3>
      <p className="mt-1 text-sm text-gray-500">{message}</p>
    </div>
  );
}

/**
 * Summary badge showing count of incidents by status.
 */
function SummaryBadges({ incidents }: { incidents: IncidentListItem[] }) {
  const pending = incidents.filter((i) => i.status === 'pending').length;
  const acknowledged = incidents.filter((i) => i.status === 'acknowledged').length;
  const resolved = incidents.filter((i) => i.status === 'resolved').length;

  return (
    <div className="flex items-center space-x-3 mb-4">
      {pending > 0 && (
        <span className="badge badge-warning">
          {pending} Pending
        </span>
      )}
      {acknowledged > 0 && (
        <span className="badge badge-success">
          {acknowledged} Acknowledged
        </span>
      )}
      {resolved > 0 && (
        <span className="badge badge-neutral">
          {resolved} Resolved
        </span>
      )}
    </div>
  );
}

/**
 * IncidentList component displays a list of incidents with optional filtering.
 * Supports filtering by date range, severity level, and acknowledgement status.
 */
export function IncidentList({
  incidents,
  onAcknowledge,
  onViewDetails,
  showFilters = true,
  emptyMessage = 'No incidents found matching your filters.',
}: IncidentListProps) {
  const [filters, setFilters] = useState<IncidentFilters>({
    severity: 'all',
    status: 'all',
  });

  // Filter incidents based on current filter state
  const filteredIncidents = useMemo(() => {
    return incidents.filter((incident) => {
      // Filter by date range
      if (filters.dateFrom) {
        const incidentDate = new Date(incident.date);
        const fromDate = new Date(filters.dateFrom);
        if (incidentDate < fromDate) {
          return false;
        }
      }

      if (filters.dateTo) {
        const incidentDate = new Date(incident.date);
        const toDate = new Date(filters.dateTo);
        if (incidentDate > toDate) {
          return false;
        }
      }

      // Filter by severity
      if (filters.severity && filters.severity !== 'all') {
        if (incident.severity !== filters.severity) {
          return false;
        }
      }

      // Filter by status (acknowledged status)
      if (filters.status && filters.status !== 'all') {
        if (incident.status !== filters.status) {
          return false;
        }
      }

      return true;
    });
  }, [incidents, filters]);

  // Sort incidents by date (most recent first)
  const sortedIncidents = useMemo(() => {
    return [...filteredIncidents].sort((a, b) => {
      const dateA = new Date(`${a.date}T${a.time}`);
      const dateB = new Date(`${b.date}T${b.time}`);
      return dateB.getTime() - dateA.getTime();
    });
  }, [filteredIncidents]);

  return (
    <div>
      {/* Filter bar */}
      {showFilters && (
        <FilterBar filters={filters} onFilterChange={setFilters} />
      )}

      {/* Summary badges */}
      {sortedIncidents.length > 0 && (
        <SummaryBadges incidents={sortedIncidents} />
      )}

      {/* Incident list */}
      {sortedIncidents.length > 0 ? (
        <div className="space-y-4">
          {sortedIncidents.map((incident) => (
            <IncidentCard
              key={incident.id}
              incident={incident}
              onAcknowledge={onAcknowledge}
              onViewDetails={onViewDetails}
            />
          ))}
        </div>
      ) : (
        <EmptyState message={emptyMessage} />
      )}

      {/* Results count */}
      {showFilters && incidents.length > 0 && (
        <div className="mt-4 text-sm text-gray-500 text-center">
          Showing {sortedIncidents.length} of {incidents.length} incident
          {incidents.length !== 1 ? 's' : ''}
        </div>
      )}
    </div>
  );
}
