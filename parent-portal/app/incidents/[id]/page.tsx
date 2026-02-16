'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { IncidentAcknowledge } from '@/components/IncidentAcknowledge';
import { PhotoGallery } from '@/components/PhotoGallery';
import {
  Incident,
  IncidentCategory,
  IncidentSeverity,
  IncidentStatus,
} from '@/lib/types';

// Mock data for incidents - will be replaced with API calls
const mockIncidents: Incident[] = [
  {
    id: 'incident-1',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date().toISOString().split('T')[0],
    time: '10:30:00',
    severity: 'minor',
    category: 'bump',
    status: 'pending',
    description:
      'Emma bumped her head on the edge of a table while playing in the block area. She was reaching for a toy when she lost her balance and hit the corner of the table.',
    actionTaken:
      'Ice pack was applied immediately for 10 minutes. Teacher stayed with Emma to comfort her and monitored for any signs of concussion. No visible swelling or bruising was observed.',
    location: 'Block Play Area',
    witnesses: ['Ms. Sarah Thompson', 'Ms. Jennifer Davis'],
    reportedBy: 'teacher-1',
    reportedByName: 'Ms. Sarah Thompson',
    requiresFollowUp: false,
    attachments: [],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  },
  {
    id: 'incident-2',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
    time: '14:15:00',
    severity: 'moderate',
    category: 'fall',
    status: 'acknowledged',
    description:
      'Emma fell while running on the playground during outdoor play time. She tripped over an uneven surface near the slide and scraped her right knee.',
    actionTaken:
      'The knee was cleaned with soap and water, antiseptic was applied, and the wound was covered with a bandage. Emma was comforted and given a few minutes to rest before returning to play.',
    location: 'Outdoor Playground',
    witnesses: ['Ms. Jennifer Davis', 'Mr. Robert Wilson'],
    reportedBy: 'teacher-2',
    reportedByName: 'Ms. Jennifer Davis',
    acknowledgedAt: new Date(Date.now() - 72000000).toISOString(),
    acknowledgedBy: 'parent-1',
    parentNotifiedAt: new Date(Date.now() - 82800000).toISOString(),
    requiresFollowUp: true,
    followUpNotes:
      'Please monitor the knee for signs of infection. Keep the bandage clean and dry. Contact us if you notice increased redness, swelling, or if Emma develops a fever.',
    attachments: [
      { id: 'photo-1', url: '', caption: 'Photo of scraped knee after cleaning', taggedChildren: ['child-1'] },
    ],
    createdAt: new Date(Date.now() - 86400000).toISOString(),
    updatedAt: new Date(Date.now() - 72000000).toISOString(),
  },
  {
    id: 'incident-3',
    childId: 'child-2',
    childName: 'Liam Johnson',
    date: new Date(Date.now() - 172800000).toISOString().split('T')[0],
    time: '11:00:00',
    severity: 'minor',
    category: 'bite',
    status: 'resolved',
    description:
      'Liam was bitten by another child during a dispute over a toy truck in the dramatic play area. The bite did not break the skin but left a visible mark on his left forearm.',
    actionTaken:
      'The area was cleaned with soap and water and ice was applied. Both children were separated and spoken to about appropriate ways to resolve conflicts. Parents of both children were notified.',
    location: 'Dramatic Play Area',
    witnesses: ['Ms. Sarah Thompson'],
    reportedBy: 'teacher-1',
    reportedByName: 'Ms. Sarah Thompson',
    acknowledgedAt: new Date(Date.now() - 158400000).toISOString(),
    acknowledgedBy: 'parent-1',
    parentNotifiedAt: new Date(Date.now() - 169200000).toISOString(),
    requiresFollowUp: false,
    attachments: [],
    createdAt: new Date(Date.now() - 172800000).toISOString(),
    updatedAt: new Date(Date.now() - 158400000).toISOString(),
  },
  {
    id: 'incident-4',
    childId: 'child-1',
    childName: 'Emma Johnson',
    date: new Date(Date.now() - 259200000).toISOString().split('T')[0],
    time: '09:45:00',
    severity: 'serious',
    category: 'allergic_reaction',
    status: 'resolved',
    description:
      'Emma showed signs of mild allergic reaction approximately 20 minutes after morning snack time. Symptoms included small hives appearing on both arms and mild itching. No respiratory distress was observed.',
    actionTaken:
      'Emma was immediately removed from the snack area. Her condition was monitored closely. Parents were called immediately. EpiPen was not required as symptoms remained mild. Emma was picked up early for medical evaluation.',
    location: 'Snack Area',
    witnesses: ['Ms. Sarah Thompson', 'Ms. Jennifer Davis', 'Director Mary Williams'],
    reportedBy: 'teacher-1',
    reportedByName: 'Ms. Sarah Thompson',
    acknowledgedAt: new Date(Date.now() - 252000000).toISOString(),
    acknowledgedBy: 'parent-1',
    parentNotifiedAt: new Date(Date.now() - 255600000).toISOString(),
    requiresFollowUp: true,
    followUpNotes:
      'Please consult with your pediatrician about allergy testing. Bring documentation of any new allergies to update her file. The snack served was apple slices with peanut butter crackers.',
    attachments: [
      { id: 'photo-2', url: '', caption: 'Photo of hives on left arm', taggedChildren: ['child-1'] },
      { id: 'photo-3', url: '', caption: 'Photo of hives on right arm', taggedChildren: ['child-1'] },
    ],
    createdAt: new Date(Date.now() - 259200000).toISOString(),
    updatedAt: new Date(Date.now() - 252000000).toISOString(),
  },
  {
    id: 'incident-5',
    childId: 'child-2',
    childName: 'Liam Johnson',
    date: new Date(Date.now() - 345600000).toISOString().split('T')[0],
    time: '15:30:00',
    severity: 'minor',
    category: 'scratch',
    status: 'acknowledged',
    description:
      'Liam got a small scratch on his right arm while playing near the bushes in the garden area during nature exploration activity.',
    actionTaken:
      'The wound was cleaned with soap and water and a small bandage was applied. Liam was reminded about staying on the designated paths in the garden.',
    location: 'Garden Area',
    witnesses: ['Mr. Robert Wilson'],
    reportedBy: 'teacher-3',
    reportedByName: 'Mr. Robert Wilson',
    acknowledgedAt: new Date(Date.now() - 331200000).toISOString(),
    acknowledgedBy: 'parent-1',
    parentNotifiedAt: new Date(Date.now() - 342000000).toISOString(),
    requiresFollowUp: false,
    attachments: [],
    createdAt: new Date(Date.now() - 345600000).toISOString(),
    updatedAt: new Date(Date.now() - 331200000).toISOString(),
  },
];

/**
 * Format a date string for display.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Format a time string for display.
 */
function formatTime(timeString: string): string {
  const date = timeString.includes('T')
    ? new Date(timeString)
    : new Date(`1970-01-01T${timeString}`);

  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format a datetime string for timeline display.
 */
function formatDateTime(dateTimeString: string): string {
  const date = new Date(dateTimeString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Get severity configuration for styling.
 */
function getSeverityConfig(severity: IncidentSeverity): {
  label: string;
  badgeClass: string;
  bgClass: string;
  borderClass: string;
} {
  switch (severity) {
    case 'minor':
      return {
        label: 'Minor',
        badgeClass: 'badge badge-info',
        bgClass: 'bg-blue-50',
        borderClass: 'border-blue-200',
      };
    case 'moderate':
      return {
        label: 'Moderate',
        badgeClass: 'badge badge-warning',
        bgClass: 'bg-yellow-50',
        borderClass: 'border-yellow-200',
      };
    case 'serious':
      return {
        label: 'Serious',
        badgeClass: 'badge badge-error',
        bgClass: 'bg-orange-50',
        borderClass: 'border-orange-200',
      };
    case 'severe':
      return {
        label: 'Severe',
        badgeClass: 'badge bg-red-700 text-white',
        bgClass: 'bg-red-50',
        borderClass: 'border-red-200',
      };
    default:
      return {
        label: 'Unknown',
        badgeClass: 'badge badge-neutral',
        bgClass: 'bg-gray-50',
        borderClass: 'border-gray-200',
      };
  }
}

/**
 * Get status configuration for styling.
 */
function getStatusConfig(status: IncidentStatus): {
  label: string;
  badgeClass: string;
} {
  switch (status) {
    case 'pending':
      return {
        label: 'Pending Acknowledgment',
        badgeClass: 'badge badge-warning',
      };
    case 'acknowledged':
      return {
        label: 'Acknowledged',
        badgeClass: 'badge badge-success',
      };
    case 'resolved':
      return {
        label: 'Resolved',
        badgeClass: 'badge badge-neutral',
      };
    default:
      return {
        label: 'Unknown',
        badgeClass: 'badge badge-neutral',
      };
  }
}

/**
 * Get formatted category label.
 */
function getCategoryLabel(category: IncidentCategory): string {
  const labels: Record<IncidentCategory, string> = {
    bump: 'Bump',
    fall: 'Fall',
    bite: 'Bite',
    scratch: 'Scratch',
    behavioral: 'Behavioral',
    medical: 'Medical',
    allergic_reaction: 'Allergic Reaction',
    other: 'Other',
  };
  return labels[category] || 'Unknown';
}

/**
 * Get icon component for incident category.
 */
function getCategoryIcon(category: IncidentCategory): React.ReactNode {
  switch (category) {
    case 'bump':
      return (
        <svg
          className="h-8 w-8 text-orange-600"
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
      );
    case 'fall':
      return (
        <svg
          className="h-8 w-8 text-red-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 14l-7 7m0 0l-7-7m7 7V3"
          />
        </svg>
      );
    case 'bite':
      return (
        <svg
          className="h-8 w-8 text-purple-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'scratch':
      return (
        <svg
          className="h-8 w-8 text-yellow-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
          />
        </svg>
      );
    case 'behavioral':
      return (
        <svg
          className="h-8 w-8 text-indigo-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
          />
        </svg>
      );
    case 'medical':
      return (
        <svg
          className="h-8 w-8 text-red-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      );
    case 'allergic_reaction':
      return (
        <svg
          className="h-8 w-8 text-pink-600"
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
      );
    case 'other':
    default:
      return (
        <svg
          className="h-8 w-8 text-gray-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
  }
}

/**
 * Timeline event type for incident history.
 */
interface TimelineEvent {
  id: string;
  type: 'created' | 'notified' | 'acknowledged' | 'resolved' | 'followup';
  timestamp: string;
  title: string;
  description?: string;
}

/**
 * Build timeline events from incident data.
 */
function buildTimeline(incident: Incident): TimelineEvent[] {
  const events: TimelineEvent[] = [];

  // Incident created/reported
  events.push({
    id: 'created',
    type: 'created',
    timestamp: incident.createdAt,
    title: 'Incident Reported',
    description: `Reported by ${incident.reportedByName}`,
  });

  // Parent notified
  if (incident.parentNotifiedAt) {
    events.push({
      id: 'notified',
      type: 'notified',
      timestamp: incident.parentNotifiedAt,
      title: 'Parent Notified',
      description: 'You were notified about this incident',
    });
  }

  // Acknowledged
  if (incident.acknowledgedAt) {
    events.push({
      id: 'acknowledged',
      type: 'acknowledged',
      timestamp: incident.acknowledgedAt,
      title: 'Acknowledged by Parent',
      description: 'You acknowledged this incident report',
    });
  }

  // Follow-up required
  if (incident.requiresFollowUp && incident.followUpNotes) {
    events.push({
      id: 'followup',
      type: 'followup',
      timestamp: incident.updatedAt,
      title: 'Follow-up Required',
      description: 'Additional follow-up care is recommended',
    });
  }

  // Sort by timestamp (oldest first)
  events.sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());

  return events;
}

/**
 * Get icon for timeline event type.
 */
function getTimelineIcon(type: TimelineEvent['type']): React.ReactNode {
  switch (type) {
    case 'created':
      return (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
      );
    case 'notified':
      return (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
          />
        </svg>
      );
    case 'acknowledged':
      return (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M5 13l4 4L19 7"
          />
        </svg>
      );
    case 'resolved':
      return (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'followup':
      return (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    default:
      return null;
  }
}

/**
 * Get color classes for timeline event type.
 */
function getTimelineColor(type: TimelineEvent['type']): string {
  switch (type) {
    case 'created':
      return 'bg-red-100 text-red-600';
    case 'notified':
      return 'bg-blue-100 text-blue-600';
    case 'acknowledged':
      return 'bg-green-100 text-green-600';
    case 'resolved':
      return 'bg-gray-100 text-gray-600';
    case 'followup':
      return 'bg-amber-100 text-amber-600';
    default:
      return 'bg-gray-100 text-gray-600';
  }
}

export default function IncidentDetailPage() {
  const params = useParams();
  const router = useRouter();
  const incidentId = params.id as string;

  const [incident, setIncident] = useState<Incident | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAcknowledgeModalOpen, setIsAcknowledgeModalOpen] = useState(false);

  // Fetch incident data
  useEffect(() => {
    const fetchIncident = async () => {
      setIsLoading(true);
      setError(null);

      try {
        // Simulate API call delay
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Find incident in mock data
        const foundIncident = mockIncidents.find((i) => i.id === incidentId);

        if (!foundIncident) {
          setError('Incident not found');
        } else {
          setIncident(foundIncident);
        }
      } catch {
        setError('Failed to load incident details');
      } finally {
        setIsLoading(false);
      }
    };

    if (incidentId) {
      fetchIncident();
    }
  }, [incidentId]);

  // Handle acknowledge submission
  const handleAcknowledgeSubmit = useCallback(
    async (id: string, signatureDataUrl: string, notes?: string) => {
      // TODO: Implement API call to acknowledge incident
      // For now, update local state
      if (incident) {
        setIncident({
          ...incident,
          status: 'acknowledged',
          acknowledgedAt: new Date().toISOString(),
          acknowledgedBy: 'parent-1',
        });
      }
      setIsAcknowledgeModalOpen(false);
    },
    [incident]
  );

  // Loading state
  if (isLoading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="animate-pulse">
          <div className="mb-8 flex items-center justify-between">
            <div className="h-8 w-48 rounded bg-gray-200" />
            <div className="h-10 w-24 rounded bg-gray-200" />
          </div>
          <div className="card p-6">
            <div className="flex items-start space-x-4">
              <div className="h-16 w-16 rounded-full bg-gray-200" />
              <div className="flex-1 space-y-3">
                <div className="h-6 w-1/3 rounded bg-gray-200" />
                <div className="h-4 w-1/4 rounded bg-gray-200" />
                <div className="h-4 w-1/2 rounded bg-gray-200" />
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Error state
  if (error || !incident) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="mb-8">
          <Link href="/incidents" className="btn btn-outline">
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
            Back to Incidents
          </Link>
        </div>
        <div className="card p-12 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
            <svg
              className="h-8 w-8 text-red-600"
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
          </div>
          <h3 className="text-lg font-medium text-gray-900">
            {error || 'Incident not found'}
          </h3>
          <p className="mt-2 text-gray-500">
            The incident you&apos;re looking for could not be found.
          </p>
          <div className="mt-6">
            <Link href="/incidents" className="btn btn-primary">
              View All Incidents
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const severityConfig = getSeverityConfig(incident.severity);
  const statusConfig = getStatusConfig(incident.status);
  const timeline = buildTimeline(incident);
  const isPending = incident.status === 'pending';

  // Convert attachments to photo format for PhotoGallery
  const photos = (incident.attachments || []).map((attachment) => {
    if (typeof attachment === 'string') {
      return { id: attachment, url: attachment, caption: '', taggedChildren: [] };
    }
    return attachment;
  });

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <Link
            href="/incidents"
            className="flex items-center text-gray-600 hover:text-gray-900"
          >
            <svg
              className="mr-2 h-5 w-5"
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
            Back to Incidents
          </Link>
          <span className={statusConfig.badgeClass}>{statusConfig.label}</span>
        </div>
      </div>

      {/* Pending Acknowledgment Banner */}
      {isPending && (
        <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
          <div className="flex items-center justify-between">
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
              <div>
                <p className="text-sm font-medium text-amber-800">
                  This incident requires your acknowledgment
                </p>
                <p className="text-sm text-amber-700">
                  Please review the details and acknowledge to confirm you&apos;ve been
                  informed.
                </p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setIsAcknowledgeModalOpen(true)}
              className="btn btn-primary ml-4"
            >
              Acknowledge Now
            </button>
          </div>
        </div>
      )}

      {/* Incident Header Card */}
      <div
        className={`card mb-6 ${severityConfig.bgClass} border ${severityConfig.borderClass}`}
      >
        <div className="card-body">
          <div className="flex items-start space-x-4">
            {/* Category Icon */}
            <div className="flex-shrink-0">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-sm">
                {getCategoryIcon(incident.category)}
              </div>
            </div>

            {/* Incident Info */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center space-x-3 flex-wrap">
                <h1 className="text-xl font-bold text-gray-900">
                  {getCategoryLabel(incident.category)} Incident
                </h1>
                <span className={severityConfig.badgeClass}>
                  {severityConfig.label}
                </span>
              </div>

              <p className="mt-1 text-lg text-gray-700">{incident.childName}</p>

              <div className="mt-3 flex flex-wrap gap-4 text-sm text-gray-600">
                <div className="flex items-center">
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
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  {formatDate(incident.date)}
                </div>
                <div className="flex items-center">
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
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  {formatTime(incident.time)}
                </div>
                <div className="flex items-center">
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
                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                    />
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                  </svg>
                  {incident.location}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="grid gap-6 lg:grid-cols-3">
        {/* Left Column - Details */}
        <div className="lg:col-span-2 space-y-6">
          {/* Description */}
          <div className="card">
            <div className="card-body">
              <h2 className="text-lg font-semibold text-gray-900 mb-3">
                What Happened
              </h2>
              <p className="text-gray-700 leading-relaxed">{incident.description}</p>
            </div>
          </div>

          {/* Action Taken */}
          <div className="card">
            <div className="card-body">
              <h2 className="text-lg font-semibold text-gray-900 mb-3">
                Action Taken
              </h2>
              <p className="text-gray-700 leading-relaxed">{incident.actionTaken}</p>
            </div>
          </div>

          {/* Follow-up Notes */}
          {incident.requiresFollowUp && (
            <div className="card border-amber-200 bg-amber-50">
              <div className="card-body">
                <div className="flex items-center mb-3">
                  <svg
                    className="h-5 w-5 text-amber-600 mr-2"
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
                  <h2 className="text-lg font-semibold text-amber-800">
                    Follow-up Required
                  </h2>
                </div>
                <p className="text-amber-800 leading-relaxed">
                  {incident.followUpNotes ||
                    'Please contact the daycare for follow-up instructions.'}
                </p>
              </div>
            </div>
          )}

          {/* Photos/Attachments */}
          {photos.length > 0 && (
            <div className="card">
              <div className="card-body">
                <h2 className="text-lg font-semibold text-gray-900 mb-3">
                  Photos & Documentation
                </h2>
                <PhotoGallery photos={photos} maxDisplay={4} />
              </div>
            </div>
          )}

          {/* Witnesses */}
          {incident.witnesses && incident.witnesses.length > 0 && (
            <div className="card">
              <div className="card-body">
                <h2 className="text-lg font-semibold text-gray-900 mb-3">
                  Witnesses
                </h2>
                <ul className="space-y-2">
                  {incident.witnesses.map((witness, index) => (
                    <li key={index} className="flex items-center text-gray-700">
                      <svg
                        className="mr-2 h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        />
                      </svg>
                      {witness}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </div>

        {/* Right Column - Timeline & Info */}
        <div className="space-y-6">
          {/* Timeline */}
          <div className="card">
            <div className="card-body">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Timeline</h2>
              <div className="space-y-4">
                {timeline.map((event, index) => (
                  <div key={event.id} className="relative flex items-start">
                    {/* Connector line */}
                    {index < timeline.length - 1 && (
                      <div className="absolute left-5 top-10 h-full w-px bg-gray-200" />
                    )}

                    {/* Icon */}
                    <div
                      className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ${getTimelineColor(
                        event.type
                      )}`}
                    >
                      {getTimelineIcon(event.type)}
                    </div>

                    {/* Content */}
                    <div className="ml-4 min-w-0 flex-1">
                      <p className="text-sm font-medium text-gray-900">
                        {event.title}
                      </p>
                      {event.description && (
                        <p className="text-sm text-gray-500">{event.description}</p>
                      )}
                      <p className="mt-1 text-xs text-gray-400">
                        {formatDateTime(event.timestamp)}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Reported By */}
          <div className="card">
            <div className="card-body">
              <h2 className="text-lg font-semibold text-gray-900 mb-3">
                Reported By
              </h2>
              <div className="flex items-center">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
                  <svg
                    className="h-5 w-5 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                  </svg>
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-900">
                    {incident.reportedByName}
                  </p>
                  <p className="text-xs text-gray-500">Staff Member</p>
                </div>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="card">
            <div className="card-body space-y-3">
              {isPending && (
                <button
                  type="button"
                  onClick={() => setIsAcknowledgeModalOpen(true)}
                  className="btn btn-primary w-full"
                >
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
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  Acknowledge Incident
                </button>
              )}

              <Link href="/messages" className="btn btn-outline w-full">
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
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                  />
                </svg>
                Contact Teacher
              </Link>

              <button type="button" className="btn btn-outline w-full" disabled>
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
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                  />
                </svg>
                Print Report
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Acknowledge Modal */}
      <IncidentAcknowledge
        incident={incident}
        isOpen={isAcknowledgeModalOpen}
        onClose={() => setIsAcknowledgeModalOpen(false)}
        onSubmit={handleAcknowledgeSubmit}
      />
    </div>
  );
}
