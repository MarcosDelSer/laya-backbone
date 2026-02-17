'use client';

// ============================================================================
// Types
// ============================================================================

type NoticeVariant = 'default' | 'compact' | 'detailed';
type NoticeLanguage = 'en' | 'fr' | 'bilingual';

interface ConsumerProtectionNoticeProps {
  /** Variant of the notice display */
  variant?: NoticeVariant;
  /** Language for the notice content */
  language?: NoticeLanguage;
  /** Whether to show the acknowledgment checkbox */
  showAcknowledgment?: boolean;
  /** Whether the acknowledgment checkbox is checked */
  acknowledged?: boolean;
  /** Callback when acknowledgment checkbox changes */
  onAcknowledgmentChange?: (acknowledged: boolean) => void;
  /** Whether the checkbox is disabled (e.g., during form submission) */
  disabled?: boolean;
  /** Optional signed date to calculate cooling-off period remaining days */
  signedDate?: string;
  /** Optional className for additional styling */
  className?: string;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Calculate the number of days remaining in the cooling-off period
 * @param signedDate The date the agreement was signed
 * @returns Number of days remaining, or null if period has expired
 */
function getCoolingOffDaysRemaining(signedDate: string): number | null {
  const signed = new Date(signedDate);
  const now = new Date();
  const coolingOffEndDate = new Date(signed);
  coolingOffEndDate.setDate(coolingOffEndDate.getDate() + 10);

  const diffTime = coolingOffEndDate.getTime() - now.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  return diffDays > 0 ? diffDays : null;
}

/**
 * Format date for display
 * @param dateString ISO date string
 * @returns Formatted date string
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

// ============================================================================
// Content Constants
// ============================================================================

const NOTICE_CONTENT = {
  en: {
    title: 'Quebec Consumer Protection Act Notice',
    intro:
      'Under the Quebec Consumer Protection Act, you have a 10-day cooling-off period during which you may cancel this agreement without penalty.',
    details:
      'This period begins from the date you receive a copy of the signed agreement. To cancel, you must notify the service provider in writing within this period.',
    reference: 'Loi sur la protection du consommateur (L.R.Q., c. P-40.1)',
    acknowledgment:
      'I acknowledge that I have read and understand the Consumer Protection Act notice and my right to cancel within the 10-day cooling-off period.',
    coolingOffActive: (days: number) =>
      `You have ${days} day${days !== 1 ? 's' : ''} remaining in your cooling-off period.`,
    coolingOffExpired:
      'The 10-day cooling-off period has expired.',
    keyPoints: [
      '10-day cancellation period from date of receiving signed agreement',
      'Written notice required to cancel',
      'No penalty for cancellation within cooling-off period',
      'Full refund of any amounts paid in advance',
    ],
  },
  fr: {
    title: 'Avis de la Loi sur la protection du consommateur du Qu\u00e9bec',
    intro:
      'En vertu de la Loi sur la protection du consommateur du Qu\u00e9bec, vous disposez d\'une p\u00e9riode de r\u00e9flexion de 10 jours pendant laquelle vous pouvez annuler cette entente sans p\u00e9nalit\u00e9.',
    details:
      'Cette p\u00e9riode commence \u00e0 partir de la date \u00e0 laquelle vous recevez une copie de l\'entente sign\u00e9e. Pour annuler, vous devez aviser le prestataire de services par \u00e9crit dans ce d\u00e9lai.',
    reference: 'Loi sur la protection du consommateur (L.R.Q., c. P-40.1)',
    acknowledgment:
      'Je reconnais avoir lu et compris l\'avis relatif \u00e0 la Loi sur la protection du consommateur et mon droit d\'annuler dans le d\u00e9lai de r\u00e9flexion de 10 jours.',
    coolingOffActive: (days: number) =>
      `Il vous reste ${days} jour${days !== 1 ? 's' : ''} dans votre p\u00e9riode de r\u00e9flexion.`,
    coolingOffExpired:
      'La p\u00e9riode de r\u00e9flexion de 10 jours est expir\u00e9e.',
    keyPoints: [
      'P\u00e9riode d\'annulation de 10 jours \u00e0 compter de la r\u00e9ception de l\'entente sign\u00e9e',
      'Avis \u00e9crit requis pour annuler',
      'Aucune p\u00e9nalit\u00e9 pour annulation pendant la p\u00e9riode de r\u00e9flexion',
      'Remboursement int\u00e9gral des montants pay\u00e9s \u00e0 l\'avance',
    ],
  },
};

// ============================================================================
// Sub-Components
// ============================================================================

function InfoIcon() {
  return (
    <svg
      className="h-5 w-5 text-blue-500"
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

function ShieldIcon() {
  return (
    <svg
      className="h-6 w-6 text-blue-600"
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
  );
}

function CheckIcon() {
  return (
    <svg
      className="h-4 w-4 text-blue-600"
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
  );
}

// ============================================================================
// Main Component
// ============================================================================

export function ConsumerProtectionNotice({
  variant = 'default',
  language = 'en',
  showAcknowledgment = false,
  acknowledged = false,
  onAcknowledgmentChange,
  disabled = false,
  signedDate,
  className = '',
}: ConsumerProtectionNoticeProps) {
  const content = NOTICE_CONTENT[language === 'bilingual' ? 'en' : language];
  const frContent = language === 'bilingual' ? NOTICE_CONTENT.fr : null;

  // Calculate cooling-off period status if signed date provided
  const coolingOffDaysRemaining = signedDate
    ? getCoolingOffDaysRemaining(signedDate)
    : null;
  const isWithinCoolingOffPeriod = coolingOffDaysRemaining !== null;

  // Compact variant - minimal display for inline use
  if (variant === 'compact') {
    return (
      <div
        className={`flex items-center space-x-2 text-sm text-blue-700 ${className}`}
      >
        <InfoIcon />
        <span>
          <strong>Important:</strong> 10-day cooling-off period applies under
          Quebec Consumer Protection Act.
        </span>
      </div>
    );
  }

  // Detailed variant - full notice with key points
  if (variant === 'detailed') {
    return (
      <div
        className={`rounded-lg border border-blue-200 bg-blue-50 ${className}`}
      >
        {/* Header */}
        <div className="flex items-center space-x-3 border-b border-blue-200 bg-blue-100 px-4 py-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white">
            <ShieldIcon />
          </div>
          <div>
            <h3 className="text-base font-semibold text-blue-900">
              {content.title}
            </h3>
            {language === 'bilingual' && frContent && (
              <p className="text-sm text-blue-700">{frContent.title}</p>
            )}
          </div>
        </div>

        {/* Content */}
        <div className="p-4">
          {/* Main notice text */}
          <div className="space-y-3">
            <p className="text-sm text-blue-800">
              <strong>Important:</strong> {content.intro}
            </p>
            {language === 'bilingual' && frContent && (
              <p className="text-sm text-blue-700 italic">{frContent.intro}</p>
            )}

            <p className="text-sm text-blue-800">{content.details}</p>
            {language === 'bilingual' && frContent && (
              <p className="text-sm text-blue-700 italic">
                {frContent.details}
              </p>
            )}
          </div>

          {/* Key points */}
          <div className="mt-4">
            <h4 className="text-sm font-medium text-blue-900">Key Points:</h4>
            <ul className="mt-2 space-y-2">
              {content.keyPoints.map((point, index) => (
                <li
                  key={index}
                  className="flex items-start space-x-2 text-sm text-blue-800"
                >
                  <CheckIcon />
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Cooling-off period status */}
          {signedDate && (
            <div className="mt-4 rounded bg-white p-3">
              <p className="text-sm text-gray-600">
                <strong>Signed on:</strong> {formatDate(signedDate)}
              </p>
              <p
                className={`mt-1 text-sm font-medium ${
                  isWithinCoolingOffPeriod
                    ? 'text-green-700'
                    : 'text-gray-500'
                }`}
              >
                {isWithinCoolingOffPeriod
                  ? content.coolingOffActive(coolingOffDaysRemaining)
                  : content.coolingOffExpired}
              </p>
            </div>
          )}

          {/* Legal reference */}
          <p className="mt-4 text-xs text-blue-600">{content.reference}</p>

          {/* Acknowledgment checkbox */}
          {showAcknowledgment && (
            <div className="mt-4 border-t border-blue-200 pt-4">
              <label className="flex items-start space-x-3">
                <input
                  type="checkbox"
                  checked={acknowledged}
                  onChange={(e) => onAcknowledgmentChange?.(e.target.checked)}
                  disabled={disabled}
                  className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                />
                <span className="text-sm text-blue-900">
                  {content.acknowledgment}
                </span>
              </label>
              {language === 'bilingual' && frContent && (
                <p className="ml-7 mt-1 text-xs text-blue-700 italic">
                  {frContent.acknowledgment}
                </p>
              )}
            </div>
          )}
        </div>
      </div>
    );
  }

  // Default variant - standard notice box
  return (
    <div
      className={`rounded-lg border border-blue-200 bg-blue-50 p-4 ${className}`}
    >
      <div className="flex items-start">
        <div className="flex-shrink-0">
          <InfoIcon />
        </div>
        <div className="ml-3">
          <h4 className="text-sm font-semibold text-blue-900">
            {content.title}
          </h4>
          {language === 'bilingual' && frContent && (
            <p className="text-xs text-blue-700">{frContent.title}</p>
          )}

          <div className="mt-2 space-y-2 text-sm text-blue-800">
            <p>
              <strong>Important:</strong> {content.intro}
            </p>
            {language === 'bilingual' && frContent && (
              <p className="text-xs text-blue-700 italic">{frContent.intro}</p>
            )}

            <p>{content.details}</p>
            {language === 'bilingual' && frContent && (
              <p className="text-xs text-blue-700 italic">
                {frContent.details}
              </p>
            )}

            <p className="text-xs">{content.reference}</p>
          </div>

          {/* Cooling-off period status */}
          {signedDate && (
            <div className="mt-3 rounded bg-white px-3 py-2">
              <p
                className={`text-sm font-medium ${
                  isWithinCoolingOffPeriod
                    ? 'text-green-700'
                    : 'text-gray-500'
                }`}
              >
                {isWithinCoolingOffPeriod
                  ? content.coolingOffActive(coolingOffDaysRemaining)
                  : content.coolingOffExpired}
              </p>
            </div>
          )}

          {/* Acknowledgment checkbox */}
          {showAcknowledgment && (
            <div className="mt-4">
              <label className="flex items-start space-x-3">
                <input
                  type="checkbox"
                  checked={acknowledged}
                  onChange={(e) => onAcknowledgmentChange?.(e.target.checked)}
                  disabled={disabled}
                  className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                />
                <span className="text-sm text-blue-900">
                  {content.acknowledgment}
                </span>
              </label>
              {language === 'bilingual' && frContent && (
                <p className="ml-7 mt-1 text-xs text-blue-700 italic">
                  {frContent.acknowledgment}
                </p>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
