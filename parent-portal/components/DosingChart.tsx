'use client';

import type { DosingInfo, AcetaminophenConcentration } from '@/lib/types';

/**
 * Weight range constants for medical protocol dosing.
 * Based on Quebec FO-0647 dosing table (4.3kg - 35kg range).
 */
const MIN_WEIGHT_KG = 4.3;
const MAX_WEIGHT_KG = 35;

/**
 * Maximum daily doses per FO-0647 guidelines.
 */
const MAX_DAILY_DOSES = 5;

/**
 * Minimum interval between doses in hours per FO-0647.
 */
const MIN_INTERVAL_HOURS = 4;

/**
 * Concentration descriptions for display.
 */
const CONCENTRATION_INFO: Record<
  AcetaminophenConcentration,
  { label: string; description: string; icon: 'drops' | 'syrup' | 'concentrated' }
> = {
  '80mg/mL': {
    label: 'Infant Drops',
    description: '80 mg per 1 mL',
    icon: 'drops',
  },
  '80mg/5mL': {
    label: "Children's Syrup",
    description: '80 mg per 5 mL',
    icon: 'syrup',
  },
  '160mg/5mL': {
    label: 'Concentrated Syrup',
    description: '160 mg per 5 mL',
    icon: 'concentrated',
  },
};

interface DosingChartProps {
  /** Child's weight in kilograms */
  weightKg: number | null;
  /** Optional pre-calculated dosing options from API */
  dosingOptions?: DosingInfo[];
  /** Recommended concentration to highlight */
  recommendedConcentration?: AcetaminophenConcentration;
  /** Whether to show the max daily dose warning */
  showDailyDoseWarning?: boolean;
  /** Callback when a concentration is selected */
  onConcentrationSelect?: (concentration: AcetaminophenConcentration) => void;
  /** Currently selected concentration */
  selectedConcentration?: AcetaminophenConcentration;
  /** Whether the chart is compact (for embedding in forms) */
  compact?: boolean;
}

/**
 * Calculate dosing information for a given weight.
 * Based on 10-15 mg/kg guideline per FO-0647.
 */
function calculateDosing(weightKg: number): DosingInfo[] {
  if (weightKg < MIN_WEIGHT_KG || weightKg > MAX_WEIGHT_KG) {
    return [];
  }

  const minDoseMg = Math.round(weightKg * 10);
  const maxDoseMg = Math.round(weightKg * 15);

  const concentrations: AcetaminophenConcentration[] = ['80mg/mL', '80mg/5mL', '160mg/5mL'];

  return concentrations.map((concentration) => {
    let minDoseMl: number;
    let maxDoseMl: number;

    switch (concentration) {
      case '80mg/mL':
        // 80 mg per 1 mL
        minDoseMl = minDoseMg / 80;
        maxDoseMl = maxDoseMg / 80;
        break;
      case '80mg/5mL':
        // 80 mg per 5 mL = 16 mg per 1 mL
        minDoseMl = (minDoseMg / 80) * 5;
        maxDoseMl = (maxDoseMg / 80) * 5;
        break;
      case '160mg/5mL':
        // 160 mg per 5 mL = 32 mg per 1 mL
        minDoseMl = (minDoseMg / 160) * 5;
        maxDoseMl = (maxDoseMg / 160) * 5;
        break;
    }

    return {
      protocolId: 'acetaminophen',
      concentration,
      minWeightKg: weightKg,
      maxWeightKg: weightKg,
      minDoseMg,
      maxDoseMg,
      minDoseMl: Math.round(minDoseMl * 10) / 10,
      maxDoseMl: Math.round(maxDoseMl * 10) / 10,
      displayLabel: CONCENTRATION_INFO[concentration].label,
    };
  });
}

/**
 * Get icon for concentration type.
 */
function getConcentrationIcon(type: 'drops' | 'syrup' | 'concentrated'): React.ReactNode {
  switch (type) {
    case 'drops':
      return (
        <svg
          className="h-5 w-5 text-blue-600"
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
      );
    case 'syrup':
      return (
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
            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
          />
        </svg>
      );
    case 'concentrated':
      return (
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
            d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"
          />
        </svg>
      );
  }
}

/**
 * Format mL dose for display.
 */
function formatDoseMl(minMl: number, maxMl: number): string {
  if (minMl === maxMl) {
    return `${minMl} mL`;
  }
  return `${minMl} - ${maxMl} mL`;
}

/**
 * Format mg dose for display.
 */
function formatDoseMg(minMg: number, maxMg: number): string {
  if (minMg === maxMg) {
    return `${minMg} mg`;
  }
  return `${minMg} - ${maxMg} mg`;
}

export function DosingChart({
  weightKg,
  dosingOptions,
  recommendedConcentration = '80mg/5mL',
  showDailyDoseWarning = true,
  onConcentrationSelect,
  selectedConcentration,
  compact = false,
}: DosingChartProps) {
  // Calculate dosing if not provided
  const dosing = dosingOptions ?? (weightKg !== null ? calculateDosing(weightKg) : []);

  // Check if weight is out of range
  const isWeightOutOfRange =
    weightKg !== null && (weightKg < MIN_WEIGHT_KG || weightKg > MAX_WEIGHT_KG);

  // Render empty state if no weight
  if (weightKg === null) {
    return (
      <div className="card">
        <div className="card-body">
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 mb-4">
              <svg
                className="h-6 w-6 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"
                />
              </svg>
            </div>
            <h3 className="text-sm font-medium text-gray-900">Enter Child's Weight</h3>
            <p className="mt-1 text-sm text-gray-500">
              Enter the child's weight above to see dosing recommendations
            </p>
          </div>
        </div>
      </div>
    );
  }

  // Render out of range warning
  if (isWeightOutOfRange) {
    return (
      <div className="card border-orange-200 bg-orange-50">
        <div className="card-body">
          <div className="flex items-start space-x-3">
            <div className="flex-shrink-0">
              <svg
                className="h-6 w-6 text-orange-600"
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
            <div>
              <h3 className="text-sm font-semibold text-orange-800">Weight Out of Range</h3>
              <p className="mt-1 text-sm text-orange-700">
                {weightKg < MIN_WEIGHT_KG
                  ? `The weight (${weightKg} kg) is below the minimum (${MIN_WEIGHT_KG} kg) for standard dosing calculations.`
                  : `The weight (${weightKg} kg) exceeds the maximum (${MAX_WEIGHT_KG} kg) for standard dosing calculations.`}
              </p>
              <p className="mt-2 text-sm text-orange-600">
                Please consult a healthcare provider for appropriate dosing.
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={compact ? '' : 'card'}>
      <div className={compact ? '' : 'card-body'}>
        {/* Header */}
        {!compact && (
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
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
                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
                  />
                </svg>
              </div>
              <div>
                <h3 className="text-base font-semibold text-gray-900">
                  Acetaminophen Dosing Chart
                </h3>
                <p className="text-sm text-gray-500">
                  For {weightKg} kg ({Math.round(weightKg * 2.205)} lbs)
                </p>
              </div>
            </div>
            <span className="badge badge-info text-xs">FO-0647</span>
          </div>
        )}

        {/* Compact header */}
        {compact && (
          <div className="mb-3">
            <p className="text-sm font-medium text-gray-700">
              Dosing for {weightKg} kg ({Math.round(weightKg * 2.205)} lbs)
            </p>
          </div>
        )}

        {/* Dosing table */}
        <div className="overflow-hidden rounded-lg border border-gray-200">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th
                  scope="col"
                  className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                >
                  Concentration
                </th>
                <th
                  scope="col"
                  className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                >
                  Dose (mg)
                </th>
                <th
                  scope="col"
                  className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                >
                  Volume (mL)
                </th>
                {onConcentrationSelect && (
                  <th scope="col" className="relative px-4 py-3">
                    <span className="sr-only">Select</span>
                  </th>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
              {dosing.map((info) => {
                const concentrationData = CONCENTRATION_INFO[info.concentration];
                const isRecommended = info.concentration === recommendedConcentration;
                const isSelected = info.concentration === selectedConcentration;

                return (
                  <tr
                    key={info.concentration}
                    className={`
                      ${isRecommended ? 'bg-green-50' : ''}
                      ${isSelected ? 'bg-blue-50 ring-2 ring-inset ring-blue-500' : ''}
                      ${onConcentrationSelect ? 'cursor-pointer hover:bg-gray-50' : ''}
                    `}
                    onClick={
                      onConcentrationSelect
                        ? () => onConcentrationSelect(info.concentration)
                        : undefined
                    }
                  >
                    <td className="whitespace-nowrap px-4 py-3">
                      <div className="flex items-center space-x-3">
                        <div className="flex-shrink-0">
                          {getConcentrationIcon(concentrationData.icon)}
                        </div>
                        <div>
                          <div className="flex items-center space-x-2">
                            <span className="text-sm font-medium text-gray-900">
                              {concentrationData.label}
                            </span>
                            {isRecommended && (
                              <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                Recommended
                              </span>
                            )}
                          </div>
                          <p className="text-xs text-gray-500">{concentrationData.description}</p>
                        </div>
                      </div>
                    </td>
                    <td className="whitespace-nowrap px-4 py-3">
                      <span className="text-sm font-medium text-gray-900">
                        {formatDoseMg(info.minDoseMg, info.maxDoseMg)}
                      </span>
                    </td>
                    <td className="whitespace-nowrap px-4 py-3">
                      <span className="text-sm font-semibold text-blue-600">
                        {formatDoseMl(info.minDoseMl, info.maxDoseMl)}
                      </span>
                    </td>
                    {onConcentrationSelect && (
                      <td className="whitespace-nowrap px-4 py-3 text-right">
                        <div className="flex items-center justify-end">
                          {isSelected ? (
                            <svg
                              className="h-5 w-5 text-blue-600"
                              fill="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                          ) : (
                            <span className="text-xs text-gray-400">Select</span>
                          )}
                        </div>
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Daily dose warning */}
        {showDailyDoseWarning && (
          <div className="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-4">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg
                  className="h-5 w-5 text-amber-600"
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
              <div className="ml-3">
                <h4 className="text-sm font-medium text-amber-800">Important Dosing Guidelines</h4>
                <div className="mt-2 text-sm text-amber-700">
                  <ul className="list-disc space-y-1 pl-5">
                    <li>
                      <strong>Maximum {MAX_DAILY_DOSES} doses</strong> per 24 hours
                    </li>
                    <li>
                      <strong>Wait at least {MIN_INTERVAL_HOURS} hours</strong> between doses
                    </li>
                    <li>Use the measuring device that comes with the medicine</li>
                    <li>Do not exceed the recommended dose</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Guidelines footer */}
        {!compact && (
          <div className="mt-4 border-t border-gray-100 pt-4">
            <p className="text-xs text-gray-500">
              <span className="font-medium">Dosing guideline:</span> 10-15 mg per kg of body weight
              per dose, based on Quebec protocol FO-0647.
            </p>
            <p className="mt-1 text-xs text-gray-400">
              Always verify weight is current (within 3 months). When in doubt, consult a healthcare
              provider.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}

// Export constants for use in other components
export { MIN_WEIGHT_KG, MAX_WEIGHT_KG, MAX_DAILY_DOSES, MIN_INTERVAL_HOURS, CONCENTRATION_INFO };
