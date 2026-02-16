'use client';

import { useMemo } from 'react';
import type {
  GovernmentDocumentChecklist as ChecklistType,
  GovernmentDocumentChecklistItem,
  GovernmentDocumentCategory,
  GovernmentDocumentStatus,
} from '@/lib/types';

interface GovernmentDocumentChecklistProps {
  checklist: ChecklistType;
  onUpload?: (documentTypeId: string, personId: string) => void;
  onView?: (documentId: string) => void;
  showEmptySections?: boolean;
}

interface CategorySection {
  category: GovernmentDocumentCategory;
  title: string;
  description: string;
  icon: React.ReactNode;
  colorClass: string;
  bgColorClass: string;
  items: GovernmentDocumentChecklistItem[];
}

function getStatusIcon(status: GovernmentDocumentStatus): React.ReactNode {
  switch (status) {
    case 'verified':
      return (
        <svg
          className="h-5 w-5 text-green-500"
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
      );
    case 'pending_verification':
      return (
        <svg
          className="h-5 w-5 text-yellow-500"
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
      );
    case 'rejected':
      return (
        <svg
          className="h-5 w-5 text-red-500"
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
      );
    case 'expired':
      return (
        <svg
          className="h-5 w-5 text-orange-500"
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
    case 'missing':
    default:
      return (
        <svg
          className="h-5 w-5 text-gray-400"
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
  }
}

function getStatusLabel(status: GovernmentDocumentStatus): string {
  switch (status) {
    case 'verified':
      return 'Verified';
    case 'pending_verification':
      return 'Pending';
    case 'rejected':
      return 'Rejected';
    case 'expired':
      return 'Expired';
    case 'missing':
    default:
      return 'Not submitted';
  }
}

function getStatusColorClass(status: GovernmentDocumentStatus): string {
  switch (status) {
    case 'verified':
      return 'text-green-700 bg-green-50';
    case 'pending_verification':
      return 'text-yellow-700 bg-yellow-50';
    case 'rejected':
      return 'text-red-700 bg-red-50';
    case 'expired':
      return 'text-orange-700 bg-orange-50';
    case 'missing':
    default:
      return 'text-gray-700 bg-gray-50';
  }
}

function getCategoryIcon(category: GovernmentDocumentCategory): React.ReactNode {
  switch (category) {
    case 'child_identity':
      return (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
        />
      );
    case 'parent_identity':
      return (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
        />
      );
    case 'health':
      return (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
        />
      );
    case 'immigration':
      return (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      );
    default:
      return (
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
        />
      );
  }
}

function ChecklistItem({
  item,
  onUpload,
  onView,
}: {
  item: GovernmentDocumentChecklistItem;
  onUpload?: (documentTypeId: string, personId: string) => void;
  onView?: (documentId: string) => void;
}) {
  const canUpload = item.status === 'missing' || item.status === 'rejected' || item.status === 'expired';
  const canView = item.document?.fileUrl;

  const handleUpload = () => {
    onUpload?.(item.documentType.id, item.personId);
  };

  const handleView = () => {
    if (item.document?.fileUrl) {
      window.open(item.document.fileUrl, '_blank');
    }
    if (item.document) {
      onView?.(item.document.id);
    }
  };

  return (
    <div className="flex items-center justify-between py-3 px-4 hover:bg-gray-50 transition-colors rounded-lg">
      <div className="flex items-center space-x-3 min-w-0 flex-1">
        {getStatusIcon(item.status)}
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-gray-900 truncate">
            {item.documentType.name}
          </p>
          <p className="text-xs text-gray-500 truncate">
            {item.personName}
            {item.daysUntilExpiration !== undefined && item.daysUntilExpiration > 0 && item.daysUntilExpiration <= 30 && (
              <span className="ml-2 text-amber-600">
                • Expires in {item.daysUntilExpiration} days
              </span>
            )}
            {item.daysUntilExpiration !== undefined && item.daysUntilExpiration < 0 && (
              <span className="ml-2 text-red-600">
                • Expired {Math.abs(item.daysUntilExpiration)} days ago
              </span>
            )}
          </p>
        </div>
      </div>

      <div className="flex items-center space-x-2 ml-4">
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColorClass(item.status)}`}>
          {getStatusLabel(item.status)}
        </span>

        {canView && (
          <button
            type="button"
            onClick={handleView}
            className="p-1.5 text-gray-400 hover:text-gray-600 transition-colors"
            title="View document"
          >
            <svg
              className="h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
          </button>
        )}

        {canUpload && onUpload && (
          <button
            type="button"
            onClick={handleUpload}
            className="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-primary-600 hover:bg-primary-700 transition-colors"
          >
            <svg
              className="mr-1 h-3 w-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
              />
            </svg>
            Upload
          </button>
        )}
      </div>
    </div>
  );
}

function CategorySectionComponent({
  section,
  onUpload,
  onView,
}: {
  section: CategorySection;
  onUpload?: (documentTypeId: string, personId: string) => void;
  onView?: (documentId: string) => void;
}) {
  const verifiedCount = section.items.filter((item) => item.status === 'verified').length;
  const totalCount = section.items.length;
  const progressPercent = totalCount > 0 ? Math.round((verifiedCount / totalCount) * 100) : 0;

  return (
    <div className="card overflow-hidden">
      {/* Section Header */}
      <div className={`px-4 py-3 ${section.bgColorClass} border-b border-gray-200`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className={`flex h-8 w-8 items-center justify-center rounded-full bg-white ${section.colorClass}`}>
              <svg
                className="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                {section.icon}
              </svg>
            </div>
            <div>
              <h3 className="text-sm font-semibold text-gray-900">{section.title}</h3>
              <p className="text-xs text-gray-500">{section.description}</p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-sm font-medium text-gray-900">
              {verifiedCount}/{totalCount}
            </p>
            <p className="text-xs text-gray-500">verified</p>
          </div>
        </div>
        {/* Progress bar */}
        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
          <div
            className={`h-full rounded-full transition-all duration-300 ${
              progressPercent === 100
                ? 'bg-green-500'
                : progressPercent >= 50
                  ? 'bg-yellow-500'
                  : 'bg-red-500'
            }`}
            style={{ width: `${progressPercent}%` }}
          />
        </div>
      </div>

      {/* Section Items */}
      <div className="divide-y divide-gray-100">
        {section.items.map((item) => (
          <ChecklistItem
            key={`${item.documentType.id}-${item.personId}`}
            item={item}
            onUpload={onUpload}
            onView={onView}
          />
        ))}
      </div>
    </div>
  );
}

export function GovernmentDocumentChecklist({
  checklist,
  onUpload,
  onView,
  showEmptySections = false,
}: GovernmentDocumentChecklistProps) {
  // Organize items by category
  const sections = useMemo((): CategorySection[] => {
    // Collect all items from children and parents
    const allItems: GovernmentDocumentChecklistItem[] = [];

    checklist.children.forEach((child) => {
      child.items.forEach((item) => {
        allItems.push(item);
      });
    });

    checklist.parents.forEach((parent) => {
      parent.items.forEach((item) => {
        allItems.push(item);
      });
    });

    // Group by category
    const childIdentityItems = allItems.filter((item) => item.documentType.category === 'child_identity');
    const parentIdentityItems = allItems.filter((item) => item.documentType.category === 'parent_identity');
    const healthItems = allItems.filter((item) => item.documentType.category === 'health');
    const immigrationItems = allItems.filter((item) => item.documentType.category === 'immigration');

    const categorySections: CategorySection[] = [
      {
        category: 'child_identity',
        title: 'Child Identity Documents',
        description: 'Birth certificates and citizenship proof for children',
        icon: getCategoryIcon('child_identity'),
        colorClass: 'text-blue-600',
        bgColorClass: 'bg-blue-50',
        items: childIdentityItems,
      },
      {
        category: 'parent_identity',
        title: 'Parent Identity Documents',
        description: 'Government-issued ID for parents/guardians',
        icon: getCategoryIcon('parent_identity'),
        colorClass: 'text-purple-600',
        bgColorClass: 'bg-purple-50',
        items: parentIdentityItems,
      },
      {
        category: 'health',
        title: 'Health Documents',
        description: 'Health cards and immunization records',
        icon: getCategoryIcon('health'),
        colorClass: 'text-red-600',
        bgColorClass: 'bg-red-50',
        items: healthItems,
      },
      {
        category: 'immigration',
        title: 'Immigration Documents',
        description: 'Citizenship proof and immigration status (if applicable)',
        icon: getCategoryIcon('immigration'),
        colorClass: 'text-green-600',
        bgColorClass: 'bg-green-50',
        items: immigrationItems,
      },
    ];

    // Filter out empty sections if showEmptySections is false
    return showEmptySections
      ? categorySections
      : categorySections.filter((section) => section.items.length > 0);
  }, [checklist, showEmptySections]);

  // Calculate overall progress
  const totalItems = sections.reduce((acc, section) => acc + section.items.length, 0);
  const verifiedItems = sections.reduce(
    (acc, section) => acc + section.items.filter((item) => item.status === 'verified').length,
    0
  );
  const attentionItems = sections.reduce(
    (acc, section) =>
      acc +
      section.items.filter(
        (item) => item.status === 'missing' || item.status === 'expired' || item.status === 'rejected'
      ).length,
    0
  );

  if (sections.length === 0) {
    return (
      <div className="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center">
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
        <h3 className="mt-4 text-sm font-medium text-gray-900">No documents required</h3>
        <p className="mt-1 text-sm text-gray-500">
          There are no government documents required at this time.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Overall Summary */}
      <div className="card p-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-sm font-medium text-gray-900">Checklist Summary</h3>
            <p className="text-xs text-gray-500 mt-1">
              {verifiedItems} of {totalItems} documents verified
            </p>
          </div>
          <div className="flex items-center space-x-4">
            {attentionItems > 0 && (
              <div className="flex items-center text-sm">
                <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-red-100 text-red-800 text-xs font-medium mr-2">
                  {attentionItems}
                </span>
                <span className="text-red-700">needs attention</span>
              </div>
            )}
            <div className="text-right">
              <span className={`text-lg font-bold ${
                checklist.complianceRate >= 80
                  ? 'text-green-600'
                  : checklist.complianceRate >= 50
                    ? 'text-yellow-600'
                    : 'text-red-600'
              }`}>
                {checklist.complianceRate}%
              </span>
              <p className="text-xs text-gray-500">complete</p>
            </div>
          </div>
        </div>
      </div>

      {/* Category Sections */}
      {sections.map((section) => (
        <CategorySectionComponent
          key={section.category}
          section={section}
          onUpload={onUpload}
          onView={onView}
        />
      ))}

      {/* Legend */}
      <div className="rounded-lg bg-gray-50 p-4">
        <h4 className="text-xs font-medium text-gray-900 uppercase tracking-wider mb-3">
          Status Legend
        </h4>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
          <div className="flex items-center space-x-2">
            {getStatusIcon('verified')}
            <span className="text-xs text-gray-600">Verified</span>
          </div>
          <div className="flex items-center space-x-2">
            {getStatusIcon('pending_verification')}
            <span className="text-xs text-gray-600">Pending</span>
          </div>
          <div className="flex items-center space-x-2">
            {getStatusIcon('missing')}
            <span className="text-xs text-gray-600">Not submitted</span>
          </div>
          <div className="flex items-center space-x-2">
            {getStatusIcon('expired')}
            <span className="text-xs text-gray-600">Expired</span>
          </div>
          <div className="flex items-center space-x-2">
            {getStatusIcon('rejected')}
            <span className="text-xs text-gray-600">Rejected</span>
          </div>
        </div>
      </div>
    </div>
  );
}
