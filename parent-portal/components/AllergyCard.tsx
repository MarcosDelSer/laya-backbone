import type { AllergyInfo, AllergenType, AllergySeverity } from '../lib/types';

interface AllergyCardProps {
  allergy: AllergyInfo;
}

const allergenTypeLabels: Record<AllergenType, string> = {
  food: 'Food',
  medication: 'Medication',
  environmental: 'Environmental',
  insect: 'Insect',
  contact: 'Contact',
  other: 'Other',
};

const severityConfig: Record<AllergySeverity, { label: string; badgeClass: string }> = {
  mild: { label: 'Mild', badgeClass: 'badge-info' },
  moderate: { label: 'Moderate', badgeClass: 'badge-warning' },
  severe: { label: 'Severe', badgeClass: 'badge-error' },
  life_threatening: { label: 'Life-Threatening', badgeClass: 'badge-error' },
};

export function AllergyCard({ allergy }: AllergyCardProps) {
  const severityInfo = severityConfig[allergy.severity];

  return (
    <div className="flex items-start space-x-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
        <svg
          className="h-5 w-5 text-red-600"
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
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="font-medium text-gray-900">{allergy.allergenName}</p>
          <span className="text-sm text-gray-500">
            {allergenTypeLabels[allergy.allergenType]}
          </span>
        </div>
        {allergy.reaction && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Reaction:</span> {allergy.reaction}
          </p>
        )}
        {allergy.treatment && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Treatment:</span> {allergy.treatment}
          </p>
        )}
        <div className="flex flex-wrap items-center gap-1 mt-1">
          <span className={`badge ${severityInfo.badgeClass}`}>
            {severityInfo.label}
          </span>
          {allergy.epiPenRequired && (
            <span className="badge badge-error">
              EpiPen Required
              {allergy.epiPenLocation && ` (${allergy.epiPenLocation})`}
            </span>
          )}
          {!allergy.isVerified && (
            <span className="badge badge-neutral">Unverified</span>
          )}
        </div>
        {allergy.notes && (
          <p className="text-sm text-gray-500 mt-1 italic">{allergy.notes}</p>
        )}
      </div>
    </div>
  );
}
