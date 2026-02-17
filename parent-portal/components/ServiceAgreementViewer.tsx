'use client';

import { useState } from 'react';
import type {
  ServiceAgreement,
  ServiceAgreementStatus,
  ServiceAgreementAnnex,
  DayOfWeek,
  ContributionType,
} from '../lib/types';

interface ServiceAgreementViewerProps {
  agreement: ServiceAgreement;
  onClose?: () => void;
  onSign?: () => void;
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
  }).format(amount);
}

function formatTime(timeString: string): string {
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function formatDayOfWeek(day: DayOfWeek): string {
  const dayMap: Record<DayOfWeek, string> = {
    monday: 'Monday',
    tuesday: 'Tuesday',
    wednesday: 'Wednesday',
    thursday: 'Thursday',
    friday: 'Friday',
    saturday: 'Saturday',
    sunday: 'Sunday',
  };
  return dayMap[day] || day;
}

function formatDays(days: DayOfWeek[]): string {
  if (days.length === 0) return 'None';
  if (days.length === 5 && !days.includes('saturday') && !days.includes('sunday')) {
    return 'Monday - Friday';
  }
  if (days.length === 7) {
    return 'Every day';
  }
  return days.map(formatDayOfWeek).join(', ');
}

function formatContributionType(type: ContributionType): string {
  const typeMap: Record<ContributionType, string> = {
    reduced: 'Quebec Reduced Contribution ($9.35/day)',
    full_rate: 'Full Market Rate',
    mixed: 'Mixed (Reduced + Additional)',
  };
  return typeMap[type] || type;
}

function getStatusBadgeClasses(status: ServiceAgreementStatus): string {
  switch (status) {
    case 'active':
      return 'badge badge-success';
    case 'pending_signature':
      return 'badge badge-warning';
    case 'draft':
      return 'badge badge-info';
    case 'expired':
      return 'badge badge-error';
    case 'terminated':
      return 'badge badge-error';
    case 'cancelled':
      return 'badge badge-neutral';
    default:
      return 'badge badge-neutral';
  }
}

function getStatusLabel(status: ServiceAgreementStatus): string {
  switch (status) {
    case 'active':
      return 'Active';
    case 'pending_signature':
      return 'Pending Signature';
    case 'draft':
      return 'Draft';
    case 'expired':
      return 'Expired';
    case 'terminated':
      return 'Terminated';
    case 'cancelled':
      return 'Cancelled';
    default:
      return status;
  }
}

function getAnnexTitle(annex: ServiceAgreementAnnex): string {
  switch (annex.type) {
    case 'A':
      return 'Annex A - Field Trips Authorization';
    case 'B':
      return 'Annex B - Hygiene Items';
    case 'C':
      return 'Annex C - Supplementary Meals';
    case 'D':
      return 'Annex D - Extended Hours';
    default:
      return `Annex ${annex.type}`;
  }
}

// ============================================================================
// Sub-Components
// ============================================================================

interface ArticleSectionProps {
  number: number;
  title: string;
  children: React.ReactNode;
  defaultExpanded?: boolean;
}

function ArticleSection({
  number,
  title,
  children,
  defaultExpanded = false,
}: ArticleSectionProps) {
  const [isExpanded, setIsExpanded] = useState(defaultExpanded);

  return (
    <div className="border border-gray-200 rounded-lg overflow-hidden">
      <button
        type="button"
        onClick={() => setIsExpanded(!isExpanded)}
        className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors"
      >
        <div className="flex items-center space-x-3">
          <span className="flex items-center justify-center h-7 w-7 rounded-full bg-purple-100 text-purple-700 text-sm font-semibold">
            {number}
          </span>
          <span className="font-medium text-gray-900">{title}</span>
        </div>
        <svg
          className={`h-5 w-5 text-gray-500 transform transition-transform ${
            isExpanded ? 'rotate-180' : ''
          }`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </button>
      {isExpanded && (
        <div className="px-4 py-4 bg-white">
          {children}
        </div>
      )}
    </div>
  );
}

interface InfoRowProps {
  label: string;
  value: React.ReactNode;
}

function InfoRow({ label, value }: InfoRowProps) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-start py-2 border-b border-gray-100 last:border-b-0">
      <span className="text-sm font-medium text-gray-500 sm:w-1/3">{label}</span>
      <span className="text-sm text-gray-900 sm:w-2/3 mt-1 sm:mt-0">{value}</span>
    </div>
  );
}

// ============================================================================
// Main Component
// ============================================================================

export function ServiceAgreementViewer({
  agreement,
  onClose,
  onSign,
}: ServiceAgreementViewerProps) {
  const requiresSignature =
    agreement.status === 'pending_signature' &&
    !agreement.signatures.some((s) => s.signerRole === 'parent');

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Service Agreement
            </h1>
            <p className="mt-1 text-sm text-gray-500">
              Agreement #{agreement.agreementNumber}
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <span className={getStatusBadgeClasses(agreement.status)}>
              {getStatusLabel(agreement.status)}
            </span>
            {onClose && (
              <button
                type="button"
                onClick={onClose}
                className="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              >
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
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            )}
          </div>
        </div>

        {/* Quick info bar */}
        <div className="mt-4 flex flex-wrap gap-4 text-sm">
          <div className="flex items-center text-gray-600">
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
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
            {agreement.childName}
          </div>
          <div className="flex items-center text-gray-600">
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
            {formatDate(agreement.startDate)} - {formatDate(agreement.endDate)}
          </div>
        </div>
      </div>

      {/* Signature required alert */}
      {requiresSignature && (
        <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-amber-400 flex-shrink-0"
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
            <div className="ml-3">
              <h3 className="text-sm font-medium text-amber-800">
                Signature Required
              </h3>
              <p className="mt-1 text-sm text-amber-700">
                Please review this agreement carefully and sign to confirm your acceptance.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Articles */}
      <div className="space-y-4">
        {/* Article 1: Identification of Parties */}
        <ArticleSection number={1} title="Identification of Parties" defaultExpanded>
          <div className="space-y-4">
            {/* Child Information */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-2">Child Information</h4>
              <div className="bg-gray-50 rounded-lg p-3">
                <InfoRow label="Name" value={agreement.childName} />
                <InfoRow label="Date of Birth" value={formatDate(agreement.childDateOfBirth)} />
              </div>
            </div>

            {/* Parent/Guardian Information */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-2">Parent/Guardian</h4>
              <div className="bg-gray-50 rounded-lg p-3">
                <InfoRow label="Name" value={agreement.parentName} />
                <InfoRow label="Address" value={agreement.parentAddress} />
                <InfoRow label="Phone" value={agreement.parentPhone} />
                <InfoRow label="Email" value={agreement.parentEmail} />
              </div>
            </div>

            {/* Provider Information */}
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-2">Service Provider</h4>
              <div className="bg-gray-50 rounded-lg p-3">
                <InfoRow label="Name" value={agreement.providerName} />
                <InfoRow label="Address" value={agreement.providerAddress} />
                <InfoRow label="Phone" value={agreement.providerPhone} />
                {agreement.providerPermitNumber && (
                  <InfoRow label="Permit Number" value={agreement.providerPermitNumber} />
                )}
              </div>
            </div>
          </div>
        </ArticleSection>

        {/* Article 2: Description of Services */}
        <ArticleSection number={2} title="Description of Services">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow label="Service Description" value={agreement.serviceDescription} />
            <InfoRow label="Program Type" value={agreement.programType} />
            <InfoRow label="Age Group" value={agreement.ageGroup} />
            {agreement.classroomName && (
              <InfoRow label="Classroom" value={agreement.classroomName} />
            )}
          </div>
        </ArticleSection>

        {/* Article 3: Operating Hours */}
        <ArticleSection number={3} title="Operating Hours">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Hours"
              value={`${formatTime(agreement.operatingHours.openTime)} - ${formatTime(agreement.operatingHours.closeTime)}`}
            />
            <InfoRow
              label="Operating Days"
              value={formatDays(agreement.operatingHours.operatingDays)}
            />
            <InfoRow
              label="Maximum Daily Hours"
              value={`${agreement.operatingHours.maxDailyHours} hours`}
            />
          </div>
        </ArticleSection>

        {/* Article 4: Attendance Pattern */}
        <ArticleSection number={4} title="Attendance Pattern">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Scheduled Days"
              value={formatDays(agreement.attendancePattern.scheduledDays)}
            />
            <InfoRow
              label="Arrival Time"
              value={formatTime(agreement.attendancePattern.arrivalTime)}
            />
            <InfoRow
              label="Departure Time"
              value={formatTime(agreement.attendancePattern.departureTime)}
            />
            <InfoRow
              label="Schedule Type"
              value={agreement.attendancePattern.isFullTime ? 'Full-Time' : 'Part-Time'}
            />
            <InfoRow
              label="Days per Week"
              value={`${agreement.attendancePattern.daysPerWeek} days`}
            />
          </div>
        </ArticleSection>

        {/* Article 5: Payment Terms */}
        <ArticleSection number={5} title="Payment Terms">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Contribution Type"
              value={formatContributionType(agreement.paymentTerms.contributionType)}
            />
            <InfoRow
              label="Daily Rate"
              value={formatCurrency(agreement.paymentTerms.dailyRate)}
            />
            <InfoRow
              label="Monthly Amount"
              value={formatCurrency(agreement.paymentTerms.monthlyAmount)}
            />
            <InfoRow
              label="Payment Due"
              value={`Day ${agreement.paymentTerms.paymentDueDay} of each month`}
            />
            <InfoRow label="Payment Method" value={agreement.paymentTerms.paymentMethod} />
            {agreement.paymentTerms.depositAmount && (
              <InfoRow
                label="Deposit"
                value={`${formatCurrency(agreement.paymentTerms.depositAmount)} ${
                  agreement.paymentTerms.depositRefundable ? '(Refundable)' : '(Non-refundable)'
                }`}
              />
            )}
            {agreement.paymentTerms.lateFeePercentage && (
              <InfoRow
                label="Late Fee"
                value={`${agreement.paymentTerms.lateFeePercentage}% after ${agreement.paymentTerms.lateFeeGraceDays || 0} grace days`}
              />
            )}
            {agreement.paymentTerms.nsfFee && (
              <InfoRow label="NSF Fee" value={formatCurrency(agreement.paymentTerms.nsfFee)} />
            )}
          </div>
        </ArticleSection>

        {/* Article 6: Late Pickup Fees */}
        <ArticleSection number={6} title="Late Pickup Fees">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Grace Period"
              value={`${agreement.latePickupFees.gracePeriodMinutes} minutes`}
            />
            <InfoRow
              label="Fee per Interval"
              value={`${formatCurrency(agreement.latePickupFees.feePerInterval)} per ${agreement.latePickupFees.intervalMinutes} minutes`}
            />
            <InfoRow
              label="Maximum Daily Fee"
              value={formatCurrency(agreement.latePickupFees.maxDailyFee)}
            />
          </div>
        </ArticleSection>

        {/* Article 7: Closure Days */}
        <ArticleSection number={7} title="Closure Days">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Statutory Holidays"
              value={
                agreement.closureDays.length > 0
                  ? agreement.closureDays.join(', ')
                  : 'Standard Quebec statutory holidays'
              }
            />
            {agreement.holidaySchedule && (
              <InfoRow label="Holiday Schedule" value={agreement.holidaySchedule} />
            )}
            {agreement.vacationWeeks !== undefined && (
              <InfoRow label="Annual Vacation Weeks" value={`${agreement.vacationWeeks} weeks`} />
            )}
          </div>
        </ArticleSection>

        {/* Article 8: Absence Policy */}
        <ArticleSection number={8} title="Absence Policy">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow label="Absence Policy" value={agreement.absencePolicy} />
            <InfoRow
              label="Notification Required"
              value={agreement.absenceNotificationRequired ? 'Yes' : 'No'}
            />
            {agreement.absenceNotificationMethod && (
              <InfoRow
                label="Notification Method"
                value={agreement.absenceNotificationMethod}
              />
            )}
            {agreement.sickDayPolicy && (
              <InfoRow label="Sick Day Policy" value={agreement.sickDayPolicy} />
            )}
          </div>
        </ArticleSection>

        {/* Article 9: Agreement Duration */}
        <ArticleSection number={9} title="Agreement Duration">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow label="Start Date" value={formatDate(agreement.startDate)} />
            <InfoRow label="End Date" value={formatDate(agreement.endDate)} />
            <InfoRow label="Auto-Renewal" value={agreement.autoRenewal ? 'Yes' : 'No'} />
            {agreement.renewalNoticeRequired && (
              <InfoRow
                label="Renewal Notice"
                value={`Required ${agreement.renewalNoticeDays || 30} days before end date`}
              />
            )}
          </div>
        </ArticleSection>

        {/* Article 10: Termination Conditions */}
        <ArticleSection number={10} title="Termination Conditions">
          <div className="bg-gray-50 rounded-lg p-3">
            <InfoRow
              label="Notice Period"
              value={`${agreement.terminationConditions.noticePeriodDays} days`}
            />
            {agreement.terminationConditions.immediateTerminationReasons.length > 0 && (
              <InfoRow
                label="Immediate Termination Reasons"
                value={
                  <ul className="list-disc list-inside">
                    {agreement.terminationConditions.immediateTerminationReasons.map(
                      (reason, idx) => (
                        <li key={idx} className="text-sm">{reason}</li>
                      )
                    )}
                  </ul>
                }
              />
            )}
            <InfoRow label="Refund Policy" value={agreement.terminationConditions.refundPolicy} />
          </div>
        </ArticleSection>

        {/* Article 11: Special Conditions */}
        <ArticleSection number={11} title="Special Conditions">
          <div className="bg-gray-50 rounded-lg p-3">
            {agreement.specialConditions ? (
              <InfoRow label="Special Conditions" value={agreement.specialConditions} />
            ) : (
              <p className="text-sm text-gray-500 italic">No special conditions specified.</p>
            )}
            {agreement.specialNeedsAccommodations && (
              <InfoRow
                label="Special Needs Accommodations"
                value={agreement.specialNeedsAccommodations}
              />
            )}
            {agreement.medicalConditions && (
              <InfoRow label="Medical Conditions" value={agreement.medicalConditions} />
            )}
            {agreement.allergies && (
              <InfoRow label="Allergies" value={agreement.allergies} />
            )}
            {agreement.emergencyContacts && (
              <InfoRow label="Emergency Contacts" value={agreement.emergencyContacts} />
            )}
          </div>
        </ArticleSection>

        {/* Article 12: Consumer Protection Act Notice */}
        <ArticleSection number={12} title="Consumer Protection Act Notice">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-start">
              <svg
                className="h-5 w-5 text-blue-500 flex-shrink-0 mt-0.5"
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
              <div className="ml-3">
                <h4 className="text-sm font-semibold text-blue-900">
                  Quebec Consumer Protection Act
                </h4>
                <p className="mt-2 text-sm text-blue-800">
                  Under the Quebec Consumer Protection Act, you have a <strong>10-day cooling-off period</strong> during
                  which you may cancel this agreement without penalty. This period begins from the date you receive
                  a copy of the signed agreement.
                </p>
                <p className="mt-2 text-sm text-blue-800">
                  To cancel, you must notify the service provider in writing within this period.
                </p>
              </div>
            </div>

            <div className="mt-4 pt-4 border-t border-blue-200">
              <InfoRow
                label="Acknowledgment Status"
                value={
                  agreement.consumerProtectionAcknowledgment.acknowledged ? (
                    <span className="flex items-center text-green-600">
                      <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                      </svg>
                      Acknowledged
                      {agreement.consumerProtectionAcknowledgment.acknowledgedAt && (
                        <span className="ml-2 text-gray-500">
                          on {formatDateTime(agreement.consumerProtectionAcknowledgment.acknowledgedAt)}
                        </span>
                      )}
                    </span>
                  ) : (
                    <span className="text-amber-600">Pending acknowledgment</span>
                  )
                }
              />
              {agreement.consumerProtectionAcknowledgment.coolingOffPeriodEndDate && (
                <InfoRow
                  label="Cooling-Off Period Ends"
                  value={formatDate(agreement.consumerProtectionAcknowledgment.coolingOffPeriodEndDate)}
                />
              )}
              {agreement.consumerProtectionAcknowledgment.coolingOffDaysRemaining !== undefined &&
                agreement.consumerProtectionAcknowledgment.coolingOffDaysRemaining > 0 && (
                <InfoRow
                  label="Days Remaining"
                  value={`${agreement.consumerProtectionAcknowledgment.coolingOffDaysRemaining} days`}
                />
              )}
            </div>
          </div>
        </ArticleSection>

        {/* Article 13: Signatures */}
        <ArticleSection number={13} title="Signatures" defaultExpanded>
          <div className="space-y-4">
            {agreement.signatures.length > 0 ? (
              agreement.signatures.map((signature) => (
                <div
                  key={signature.id}
                  className="bg-gray-50 rounded-lg p-4 border border-gray-200"
                >
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
                        <svg
                          className="h-5 w-5 text-green-600"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                          />
                        </svg>
                      </div>
                      <div className="ml-3">
                        <p className="text-sm font-medium text-gray-900">
                          {signature.signerName}
                        </p>
                        <p className="text-xs text-gray-500 capitalize">
                          {signature.signerRole}
                        </p>
                      </div>
                    </div>
                    <span
                      className={`badge ${
                        signature.verificationStatus === 'verified'
                          ? 'badge-success'
                          : signature.verificationStatus === 'failed'
                          ? 'badge-error'
                          : 'badge-warning'
                      }`}
                    >
                      {signature.verificationStatus === 'verified'
                        ? 'Verified'
                        : signature.verificationStatus === 'failed'
                        ? 'Failed'
                        : 'Pending'}
                    </span>
                  </div>
                  <div className="text-xs text-gray-500 space-y-1">
                    <p>Signed: {formatDateTime(signature.signedAt)}</p>
                    <p>Type: {signature.signatureType === 'drawn' ? 'Hand-drawn' : 'Typed'}</p>
                    {signature.ipAddress && <p>IP: {signature.ipAddress}</p>}
                  </div>
                </div>
              ))
            ) : (
              <div className="text-center py-6 bg-gray-50 rounded-lg">
                <svg
                  className="mx-auto h-12 w-12 text-gray-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                  />
                </svg>
                <p className="mt-2 text-sm text-gray-500">
                  No signatures collected yet.
                </p>
              </div>
            )}

            {/* Signature status summary */}
            <div className="mt-4 p-3 bg-gray-100 rounded-lg">
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Parent Signature:</span>
                {agreement.parentSignedAt ? (
                  <span className="flex items-center text-green-600">
                    <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    Signed
                  </span>
                ) : (
                  <span className="text-amber-600">Pending</span>
                )}
              </div>
              <div className="flex items-center justify-between text-sm mt-2">
                <span className="text-gray-600">Provider Signature:</span>
                {agreement.providerSignedAt ? (
                  <span className="flex items-center text-green-600">
                    <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    Signed
                  </span>
                ) : (
                  <span className="text-amber-600">Pending</span>
                )}
              </div>
            </div>
          </div>
        </ArticleSection>

        {/* Annexes Section */}
        {agreement.annexes.length > 0 && (
          <div className="mt-8">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Optional Annexes</h2>
            <div className="space-y-4">
              {agreement.annexes.map((annex) => (
                <div key={annex.id} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-medium text-gray-900">
                      {getAnnexTitle(annex)}
                    </h3>
                    <span
                      className={`badge ${
                        annex.status === 'signed'
                          ? 'badge-success'
                          : annex.status === 'declined'
                          ? 'badge-error'
                          : annex.status === 'not_applicable'
                          ? 'badge-neutral'
                          : 'badge-warning'
                      }`}
                    >
                      {annex.status === 'signed'
                        ? 'Signed'
                        : annex.status === 'declined'
                        ? 'Declined'
                        : annex.status === 'not_applicable'
                        ? 'N/A'
                        : 'Pending'}
                    </span>
                  </div>

                  {/* Annex-specific content */}
                  <div className="bg-gray-50 rounded-lg p-3 text-sm">
                    {annex.type === 'A' && (
                      <>
                        <InfoRow
                          label="Field Trips Authorized"
                          value={annex.authorizeFieldTrips ? 'Yes' : 'No'}
                        />
                        {annex.transportationAuthorized !== undefined && (
                          <InfoRow
                            label="Transportation Authorized"
                            value={annex.transportationAuthorized ? 'Yes' : 'No'}
                          />
                        )}
                        {annex.walkingDistanceAuthorized !== undefined && (
                          <InfoRow
                            label="Walking Distance Authorized"
                            value={annex.walkingDistanceAuthorized ? 'Yes' : 'No'}
                          />
                        )}
                        {annex.fieldTripConditions && (
                          <InfoRow label="Conditions" value={annex.fieldTripConditions} />
                        )}
                      </>
                    )}

                    {annex.type === 'B' && (
                      <>
                        <InfoRow
                          label="Hygiene Items Included"
                          value={annex.hygieneItemsIncluded ? 'Yes' : 'No'}
                        />
                        {annex.itemsList && annex.itemsList.length > 0 && (
                          <InfoRow label="Items" value={annex.itemsList.join(', ')} />
                        )}
                        {annex.monthlyFee !== undefined && (
                          <InfoRow label="Monthly Fee" value={formatCurrency(annex.monthlyFee)} />
                        )}
                        {annex.parentProvides && annex.parentProvides.length > 0 && (
                          <InfoRow label="Parent Provides" value={annex.parentProvides.join(', ')} />
                        )}
                      </>
                    )}

                    {annex.type === 'C' && (
                      <>
                        <InfoRow
                          label="Supplementary Meals"
                          value={annex.supplementaryMealsIncluded ? 'Yes' : 'No'}
                        />
                        {annex.mealsIncluded && annex.mealsIncluded.length > 0 && (
                          <InfoRow label="Meals Included" value={annex.mealsIncluded.join(', ')} />
                        )}
                        {annex.dietaryRestrictions && (
                          <InfoRow label="Dietary Restrictions" value={annex.dietaryRestrictions} />
                        )}
                        {annex.allergyInfo && (
                          <InfoRow label="Allergy Information" value={annex.allergyInfo} />
                        )}
                        {annex.monthlyFee !== undefined && (
                          <InfoRow label="Monthly Fee" value={formatCurrency(annex.monthlyFee)} />
                        )}
                      </>
                    )}

                    {annex.type === 'D' && (
                      <>
                        <InfoRow
                          label="Extended Hours Required"
                          value={annex.extendedHoursRequired ? 'Yes' : 'No'}
                        />
                        {annex.requestedStartTime && (
                          <InfoRow
                            label="Requested Hours"
                            value={`${formatTime(annex.requestedStartTime)} - ${formatTime(annex.requestedEndTime || '')}`}
                          />
                        )}
                        {annex.additionalHoursPerDay !== undefined && (
                          <InfoRow
                            label="Additional Hours/Day"
                            value={`${annex.additionalHoursPerDay} hours`}
                          />
                        )}
                        {annex.hourlyRate !== undefined && (
                          <InfoRow label="Hourly Rate" value={formatCurrency(annex.hourlyRate)} />
                        )}
                        {annex.monthlyEstimate !== undefined && (
                          <InfoRow
                            label="Monthly Estimate"
                            value={formatCurrency(annex.monthlyEstimate)}
                          />
                        )}
                        {annex.reason && <InfoRow label="Reason" value={annex.reason} />}
                      </>
                    )}
                  </div>

                  {annex.signedAt && (
                    <p className="mt-2 text-xs text-gray-500">
                      Signed on {formatDateTime(annex.signedAt)}
                      {annex.signedBy && ` by ${annex.signedBy}`}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Action buttons */}
      <div className="mt-8 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
        {agreement.pdfUrl && (
          <a
            href={agreement.pdfUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="btn btn-outline"
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
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
            Download PDF
          </a>
        )}

        {requiresSignature && onSign && (
          <button type="button" onClick={onSign} className="btn btn-primary">
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
                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
              />
            </svg>
            Sign Agreement
          </button>
        )}

        {onClose && (
          <button type="button" onClick={onClose} className="btn btn-outline">
            Close
          </button>
        )}
      </div>

      {/* Metadata footer */}
      <div className="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-400">
        <p>
          Created: {formatDateTime(agreement.createdAt)} | Last updated:{' '}
          {formatDateTime(agreement.updatedAt)}
        </p>
        {agreement.notes && <p className="mt-1">Notes: {agreement.notes}</p>}
      </div>
    </div>
  );
}
