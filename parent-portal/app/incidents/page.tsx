'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { IncidentCard } from '@/components/IncidentCard';
import { IncidentListItem } from '@/lib/types';

// Mock data for incidents - will be replaced with API calls
const mockIncidents: IncidentListItem[] = [
  {
    id: 'incident-1',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date().toISOString().split('T')[0], // Today
    time: '10:30:00',
    severity: 'minor',
    category: 'bump',
    status: 'pending',
    description:
      'Emma bumped her head on the edge of a table while playing. Ice pack was applied immediately. No visible swelling or bruising.',
    requiresFollowUp: false,
    createdAt: new Date().toISOString(),
  },
  {
    id: 'incident-2',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date(Date.now() - 86400000).toISOString().split('T')[0], // Yesterday
    time: '14:15:00',
    severity: 'moderate',
    category: 'fall',
    status: 'acknowledged',
    description:
      'Emma fell while running on the playground. Scraped knee was cleaned and bandaged. She was comforted and returned to play after a few minutes.',
    requiresFollowUp: true,
    createdAt: new Date(Date.now() - 86400000).toISOString(),
  },
  {
    id: 'incident-3',
    childId: 'child-2',
    childName: 'Liam Johnson',
    date: new Date(Date.now() - 172800000).toISOString().split('T')[0], // 2 days ago
    time: '11:00:00',
    severity: 'minor',
    category: 'bite',
    status: 'resolved',
    description:
      'Liam was bitten by another child during a dispute over a toy. The area was cleaned and ice was applied. Parents of both children were notified.',
    requiresFollowUp: false,
    createdAt: new Date(Date.now() - 172800000).toISOString(),
  },
  {
    id: 'incident-4',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date(Date.now() - 259200000).toISOString().split('T')[0], // 3 days ago
    time: '09:45:00',
    severity: 'serious',
    category: 'allergic_reaction',
    status: 'resolved',
    description:
      'Emma showed signs of mild allergic reaction (hives on arms) after snack time. EpiPen was not required. Parents were called immediately and Emma was picked up early.',
    requiresFollowUp: true,
    createdAt: new Date(Date.now() - 259200000).toISOString(),
  },
  {
    id: 'incident-5',
    childId: 'child-2',
    childName: 'Liam Johnson',
    date: new Date(Date.now() - 345600000).toISOString().split('T')[0], // 4 days ago
    time: '15:30:00',
    severity: 'minor',
    category: 'scratch',
    status: 'acknowledged',
    description:
      'Liam got a small scratch on his arm while playing near the bushes in the garden area. The wound was cleaned and a bandage was applied.',
    requiresFollowUp: false,
    createdAt: new Date(Date.now() - 345600000).toISOString(),
  },
];

export default function IncidentsPage() {
  const router = useRouter();

  const handleViewDetails = (incidentId: string) => {
    router.push(`/incidents/${incidentId}`);
  };

  const handleAcknowledge = (incidentId: string) => {
    // TODO: Implement acknowledge API call
    // For now, just show an alert
    alert(`Acknowledging incident ${incidentId} - API integration coming soon`);
  };

  // Count pending incidents
  const pendingCount = mockIncidents.filter(
    (incident) => incident.status === 'pending'
  ).length;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Incident Reports</h1>
            <p className="mt-1 text-gray-600">
              View and acknowledge incident reports for your children
            </p>
          </div>
          <Link href="/" className="btn btn-outline">
            <svg
              className="mr-2 h-4 w-4"
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
            Back
          </Link>
        </div>
      </div>

      {/* Summary Banner */}
      {pendingCount > 0 && (
        <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
          <div className="flex items-center">
            <svg
              className="h-5 w-5 text-amber-600 mr-3"
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
            <p className="text-sm text-amber-800">
              <span className="font-medium">
                {pendingCount} incident{pendingCount !== 1 ? 's' : ''} pending
                acknowledgment.
              </span>{' '}
              Please review and acknowledge to confirm you&apos;ve been informed.
            </p>
          </div>
        </div>
      )}

      {/* Filter/Status Navigation */}
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-500">
            Showing {mockIncidents.length} incident
            {mockIncidents.length !== 1 ? 's' : ''}
          </span>
        </div>
        <div className="flex items-center space-x-2">
          <button type="button" className="btn btn-outline btn-sm" disabled>
            <svg
              className="mr-1 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
              />
            </svg>
            Filter
          </button>
        </div>
      </div>

      {/* Incidents Feed */}
      {mockIncidents.length > 0 ? (
        <div className="space-y-4">
          {mockIncidents.map((incident) => (
            <IncidentCard
              key={incident.id}
              incident={incident}
              onViewDetails={handleViewDetails}
              onAcknowledge={handleAcknowledge}
            />
          ))}
        </div>
      ) : (
        <div className="card p-12 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
            <svg
              className="h-8 w-8 text-gray-400"
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
          </div>
          <h3 className="text-lg font-medium text-gray-900">
            No incidents reported
          </h3>
          <p className="mt-2 text-gray-500">
            There are currently no incident reports for your children. This is
            good news!
          </p>
        </div>
      )}

      {/* Load More - placeholder for pagination */}
      {mockIncidents.length > 0 && (
        <div className="mt-8 text-center">
          <button type="button" className="btn btn-outline" disabled>
            Load More Incidents
          </button>
        </div>
      )}
    </div>
  );
}
