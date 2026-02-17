import type { MedicationInfo, MedicationType, MedicationRoute, AdministeredBy } from '../lib/types';

interface MedicationCardProps {
  medication: MedicationInfo;
}

const medicationTypeLabels: Record<MedicationType, string> = {
  prescription: 'Prescription',
  over_the_counter: 'Over the Counter',
  supplement: 'Supplement',
};

const routeLabels: Record<MedicationRoute, string> = {
  oral: 'Oral',
  topical: 'Topical',
  inhalation: 'Inhalation',
  injection: 'Injection',
  drops: 'Drops',
  other: 'Other',
};

const administeredByConfig: Record<AdministeredBy, { label: string; badgeClass: string }> = {
  staff: { label: 'Staff Administered', badgeClass: 'badge-warning' },
  nurse: { label: 'Nurse Administered', badgeClass: 'badge-info' },
  self: { label: 'Self Administered', badgeClass: 'badge-neutral' },
};

/**
 * Check if medication is expired or expiring soon.
 */
function getExpirationStatus(expirationDate?: string): {
  isExpired: boolean;
  isExpiringSoon: boolean;
  daysUntilExpiration: number | null;
} {
  if (!expirationDate) {
    return { isExpired: false, isExpiringSoon: false, daysUntilExpiration: null };
  }

  const expDate = new Date(expirationDate);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  expDate.setHours(0, 0, 0, 0);

  const diffTime = expDate.getTime() - today.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  return {
    isExpired: diffDays < 0,
    isExpiringSoon: diffDays >= 0 && diffDays <= 30,
    daysUntilExpiration: diffDays,
  };
}

export function MedicationCard({ medication }: MedicationCardProps) {
  const administeredByInfo = administeredByConfig[medication.administeredBy];
  const expirationStatus = getExpirationStatus(medication.expirationDate);

  return (
    <div className="flex items-start space-x-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
        <svg
          className="h-5 w-5 text-purple-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
          />
        </svg>
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="font-medium text-gray-900">{medication.medicationName}</p>
          <span className="text-sm text-gray-500">
            {medicationTypeLabels[medication.medicationType]}
          </span>
        </div>
        <div className="text-sm text-gray-600 mt-0.5">
          <span className="font-medium">Dosage:</span> {medication.dosage}
          {medication.frequency && (
            <span className="ml-2">
              <span className="font-medium">Frequency:</span> {medication.frequency}
            </span>
          )}
        </div>
        <p className="text-sm text-gray-600 mt-0.5">
          <span className="font-medium">Route:</span> {routeLabels[medication.route]}
        </p>
        {medication.purpose && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Purpose:</span> {medication.purpose}
          </p>
        )}
        {medication.sideEffects && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Side Effects:</span> {medication.sideEffects}
          </p>
        )}
        {medication.prescribedBy && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Prescribed by:</span> {medication.prescribedBy}
          </p>
        )}
        {medication.storageLocation && (
          <p className="text-sm text-gray-600 mt-0.5">
            <span className="font-medium">Storage:</span> {medication.storageLocation}
          </p>
        )}
        <div className="flex flex-wrap items-center gap-1 mt-1">
          <span className={`badge ${administeredByInfo.badgeClass}`}>
            {administeredByInfo.label}
          </span>
          {expirationStatus.isExpired && (
            <span className="badge badge-error">Expired</span>
          )}
          {expirationStatus.isExpiringSoon && !expirationStatus.isExpired && (
            <span className="badge badge-warning">
              Expires in {expirationStatus.daysUntilExpiration} day
              {expirationStatus.daysUntilExpiration !== 1 ? 's' : ''}
            </span>
          )}
          {!medication.parentConsent && (
            <span className="badge badge-error">Consent Required</span>
          )}
          {!medication.isVerified && (
            <span className="badge badge-neutral">Unverified</span>
          )}
        </div>
        {medication.notes && (
          <p className="text-sm text-gray-500 mt-1 italic">{medication.notes}</p>
        )}
      </div>
    </div>
  );
}
