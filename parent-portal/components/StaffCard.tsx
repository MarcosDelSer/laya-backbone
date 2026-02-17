import type {
  StaffProfile,
  QualificationLevel,
  StaffStatus,
  CertificationStatus,
  StaffCertification,
} from '../lib/types';

export interface StaffCardProps {
  staff: StaffProfile;
  showCertifications?: boolean;
  showBio?: boolean;
  compact?: boolean;
}

/**
 * Formats a qualification level for display.
 */
function formatQualificationLevel(level: QualificationLevel): string {
  const labels: Record<QualificationLevel, string> = {
    unqualified: 'Unqualified',
    in_training: 'In Training',
    qualified_assistant: 'Qualified Assistant',
    qualified_educator: 'Qualified Educator',
    specialized_educator: 'Specialized Educator',
    director: 'Director',
  };
  return labels[level] || level;
}

/**
 * Gets the CSS classes for a qualification badge.
 */
function getQualificationBadgeClass(level: QualificationLevel): string {
  switch (level) {
    case 'director':
      return 'badge badge-info';
    case 'specialized_educator':
    case 'qualified_educator':
      return 'badge badge-success';
    case 'qualified_assistant':
      return 'badge badge-warning';
    case 'in_training':
      return 'badge bg-purple-100 text-purple-800';
    case 'unqualified':
    default:
      return 'badge bg-gray-100 text-gray-600';
  }
}

/**
 * Formats a staff status for display.
 */
function formatStaffStatus(status: StaffStatus): string {
  const labels: Record<StaffStatus, string> = {
    active: 'Active',
    on_leave: 'On Leave',
    terminated: 'Terminated',
    suspended: 'Suspended',
  };
  return labels[status] || status;
}

/**
 * Gets the CSS classes for a status badge.
 */
function getStatusBadgeClass(status: StaffStatus): string {
  switch (status) {
    case 'active':
      return 'badge badge-success';
    case 'on_leave':
      return 'badge badge-warning';
    case 'terminated':
    case 'suspended':
      return 'badge badge-error';
    default:
      return 'badge bg-gray-100 text-gray-600';
  }
}

/**
 * Formats a certification status for display.
 */
function formatCertificationStatus(status: CertificationStatus): string {
  const labels: Record<CertificationStatus, string> = {
    valid: 'Valid',
    pending: 'Pending',
    expired: 'Expired',
    expiring_soon: 'Expiring Soon',
  };
  return labels[status] || status;
}

/**
 * Gets the CSS classes for a certification status badge.
 */
function getCertificationStatusClass(status: CertificationStatus): string {
  switch (status) {
    case 'valid':
      return 'text-green-600';
    case 'pending':
      return 'text-yellow-600';
    case 'expiring_soon':
      return 'text-orange-600';
    case 'expired':
      return 'text-red-600';
    default:
      return 'text-gray-600';
  }
}

/**
 * Gets the display name for a staff member.
 */
function getDisplayName(staff: StaffProfile): string {
  if (staff.preferredName) {
    return `${staff.preferredName} ${staff.lastName}`;
  }
  return `${staff.firstName} ${staff.lastName}`;
}

/**
 * Gets initials for avatar fallback.
 */
function getInitials(staff: StaffProfile): string {
  const firstName = staff.preferredName || staff.firstName;
  return `${firstName.charAt(0)}${staff.lastName.charAt(0)}`.toUpperCase();
}

/**
 * Certification entry component.
 */
function CertificationEntry({ certification }: { certification: StaffCertification }) {
  return (
    <div className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
      <div className="flex items-center space-x-2">
        <svg
          className={`h-4 w-4 ${getCertificationStatusClass(certification.status)}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          {certification.status === 'valid' ? (
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          ) : certification.status === 'expired' ? (
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          ) : (
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          )}
        </svg>
        <span className="text-sm text-gray-700">{certification.name}</span>
        {certification.isRequired && (
          <span className="text-xs text-gray-400">(Required)</span>
        )}
      </div>
      <span className={`text-xs ${getCertificationStatusClass(certification.status)}`}>
        {formatCertificationStatus(certification.status)}
      </span>
    </div>
  );
}

/**
 * StaffCard displays information about a staff member to parents.
 * Shows profile photo, name, position, qualification level, and optionally
 * certifications and bio.
 */
export function StaffCard({
  staff,
  showCertifications = false,
  showBio = false,
  compact = false,
}: StaffCardProps) {
  const displayName = getDisplayName(staff);
  const initials = getInitials(staff);
  const validCertifications = staff.certifications?.filter(
    (cert) => cert.status === 'valid'
  );

  if (compact) {
    // Compact version for lists or grids
    return (
      <div className="flex items-center space-x-3 p-3 bg-white rounded-lg border border-gray-200 hover:border-primary-300 transition-colors">
        {/* Avatar */}
        <div className="flex-shrink-0">
          {staff.profilePhotoUrl ? (
            <img
              src={staff.profilePhotoUrl}
              alt={displayName}
              className="h-10 w-10 rounded-full object-cover"
            />
          ) : (
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100">
              <span className="text-sm font-medium text-primary-700">{initials}</span>
            </div>
          )}
        </div>

        {/* Info */}
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-gray-900 truncate">{displayName}</p>
          <p className="text-xs text-gray-500 truncate">{staff.position}</p>
        </div>

        {/* Status indicator */}
        {staff.status === 'active' ? (
          <div className="flex-shrink-0">
            <span className="inline-flex h-2 w-2 rounded-full bg-green-400" />
          </div>
        ) : (
          <span className={`flex-shrink-0 ${getStatusBadgeClass(staff.status)} text-xs`}>
            {formatStaffStatus(staff.status)}
          </span>
        )}
      </div>
    );
  }

  // Full card version
  return (
    <div className="card">
      <div className="card-body">
        {/* Header with avatar and basic info */}
        <div className="flex items-start space-x-4">
          {/* Profile Photo */}
          <div className="flex-shrink-0">
            {staff.profilePhotoUrl ? (
              <img
                src={staff.profilePhotoUrl}
                alt={displayName}
                className="h-16 w-16 rounded-full object-cover ring-2 ring-white shadow-sm"
              />
            ) : (
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 ring-2 ring-white shadow-sm">
                <span className="text-xl font-semibold text-primary-700">{initials}</span>
              </div>
            )}
          </div>

          {/* Name, Position, Badges */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold text-gray-900">{displayName}</h3>
              <span className={getStatusBadgeClass(staff.status)}>
                {formatStaffStatus(staff.status)}
              </span>
            </div>
            <p className="text-sm text-gray-600">{staff.position}</p>
            {staff.department && (
              <p className="text-xs text-gray-400">{staff.department}</p>
            )}
            <div className="mt-2 flex flex-wrap gap-2">
              <span className={getQualificationBadgeClass(staff.qualificationLevel)}>
                {formatQualificationLevel(staff.qualificationLevel)}
              </span>
              {validCertifications && validCertifications.length > 0 && (
                <span className="badge bg-green-100 text-green-700">
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
                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                  </svg>
                  {validCertifications.length} Certification
                  {validCertifications.length !== 1 ? 's' : ''}
                </span>
              )}
            </div>
          </div>
        </div>

        {/* Bio section */}
        {showBio && staff.bio && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <h4 className="text-sm font-medium text-gray-900 mb-2">About</h4>
            <p className="text-sm text-gray-600">{staff.bio}</p>
          </div>
        )}

        {/* Specializations */}
        {staff.specializations && staff.specializations.length > 0 && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <h4 className="text-sm font-medium text-gray-900 mb-2">Specializations</h4>
            <div className="flex flex-wrap gap-2">
              {staff.specializations.map((spec, index) => (
                <span
                  key={index}
                  className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                >
                  {spec}
                </span>
              ))}
            </div>
          </div>
        )}

        {/* Certifications section */}
        {showCertifications && staff.certifications && staff.certifications.length > 0 && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <h4 className="text-sm font-medium text-gray-900 mb-2">Certifications</h4>
            <div className="space-y-0">
              {staff.certifications.map((cert) => (
                <CertificationEntry key={cert.id} certification={cert} />
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
