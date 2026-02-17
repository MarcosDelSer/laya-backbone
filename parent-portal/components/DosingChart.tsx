'use client';

import type { DosingInfo, AcetaminophenConcentration } from '@/lib/types';
import {
  getDoseRange,
  calculateMgPerKg,
  isOverdoseRisk,
  MIN_MG_PER_KG,
  MAX_MG_PER_KG,
} from '@/lib/doseValidation';

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
  { label: string; description: string; icon: 'drops' | 'syrup' | 'concentrated' | 'tablet' }
> = {
  '80mg/mL': {
    label: 'Infant Drops',
    description: '80 mg per 1 mL',
    icon: 'drops',
  },
  '160mg/5mL': {
    label: 'Children\'s Suspension',
    description: '160 mg per 5 mL',
    icon: 'concentrated',
  },
  '325mg': {
    label: '325mg Tablet',
    description: '325 mg per tablet',
    icon: 'tablet',
  },
  '500mg': {
    label: '500mg Tablet',
    description: '500 mg per tablet',
    icon: 'tablet',
  },
};

interface DosingChartProps {
  /** Child's weight in kilograms */
  weightKg: number | null;
  /** Child's age in months (optional, for age-based warnings) */
  ageMonths?: number;
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

  const minDoseMg = Math.round(weightKg * MIN_MG_PER_KG); // 10 mg/kg
  const maxDoseMg = Math.round(weightKg * MAX_MG_PER_KG); // 15 mg/kg

  const concentrations: AcetaminophenConcentration[] = ['80mg/mL', '160mg/5mL', '325mg', '500mg'];

  return concentrations.map((concentration) => {
    let minDoseMl: number;
    let maxDoseMl: number;

    switch (concentration) {
      case '80mg/mL':
        // Infant drops: 80 mg per 1 mL
        minDoseMl = minDoseMg / 80;
        maxDoseMl = maxDoseMg / 80;
        break;
      case '160mg/5mL':
        // Children's suspension: 160 mg per 5 mL = 32 mg per 1 mL
        minDoseMl = (minDoseMg / 160) * 5;
        maxDoseMl = (maxDoseMg / 160) * 5;
        break;
      case '325mg':
        // 325mg tablet: show as fraction of tablet
        minDoseMl = minDoseMg / 325;
        maxDoseMl = maxDoseMg / 325;
        break;
      case '500mg':
        // 500mg tablet: show as fraction of tablet
        minDoseMl = minDoseMg / 500;
        maxDoseMl = maxDoseMg / 500;
        break;
    }

    return {
      protocolId: 'acetaminophen',
      concentration,
      minWeightKg: weightKg,
      maxWeightKg: weightKg,
      minDoseMg,
      maxDoseMg,
      minDoseMl: Math.round(minDoseMl * 10) / 10, // Round to 1 decimal place
      maxDoseMl: Math.round(maxDoseMl * 10) / 10,
      displayLabel: CONCENTRATION_INFO[concentration].label,
    };
  });
}

/**
 * Get icon for concentration type.
 */
function getConcentrationIcon(type: 'drops' | 'syrup' | 'concentrated' | 'tablet'): React.ReactNode {
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
    case 'tablet':
      return (
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
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
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

/**
 * Get age-based safety warnings per Quebec FO-0647.
 */
function getAgeWarnings(ageMonths: number | undefined): string[] {
  const warnings: string[] = [];

  if (ageMonths === undefined) {
    return warnings;
  }

  // Under 3 months (0-2 months) - require doctor approval
  if (ageMonths < 3) {
    warnings.push(
      '⚠️ CAUTION: Acetaminophen for infants under 3 months requires healthcare provider approval.'
    );
  }

  // Under 6 months - extra monitoring
  if (ageMonths >= 3 && ageMonths < 6) {
    warnings.push(
      'For infants under 6 months, closely monitor for effectiveness and side effects.'
    );
  }

  return warnings;
}

/**
 * Get dose validation severity based on mg/kg.
 */
function getDoseSeverity(mgPerKg: number): 'safe' | 'warning' | 'error' {
  if (mgPerKg > MAX_MG_PER_KG) {
    return 'error';
  }
  if (mgPerKg < MIN_MG_PER_KG) {
    return 'warning';
  }
  if (mgPerKg >= 14.0 && mgPerKg <= MAX_MG_PER_KG) {
    return 'warning'; // Upper limit warning
  }
  if (mgPerKg >= MIN_MG_PER_KG && mgPerKg <= 11.0) {
    return 'warning'; // Lower limit warning
  }
  return 'safe';
}

/**
 * Get severity badge classes.
 */
function getSeverityBadgeClass(severity: 'safe' | 'warning' | 'error'): string {
  switch (severity) {
    case 'safe':
      return 'bg-green-100 text-green-800 border-green-200';
    case 'warning':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'error':
      return 'bg-red-100 text-red-800 border-red-200';
  }
}

export function DosingChart({
  weightKg,
  ageMonths,
  dosingOptions,
  recommendedConcentration = '80mg/mL',
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

  // Get recommended dose range for validation
  const recommendedRange = weightKg !== null ? getDoseRange(weightKg) : null;

  // Get age-based warnings
  const ageWarnings = getAgeWarnings(ageMonths);

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

        {/* Recommended Dose Range - Prominent Display */}
        {recommendedRange && (
          <div className="mb-4 rounded-lg border-2 border-green-200 bg-green-50 p-4">
            <div className="flex items-start space-x-3">
              <div className="flex-shrink-0">
                <svg
                  className="h-6 w-6 text-green-600"
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
              <div className="flex-1">
                <h4 className="text-sm font-semibold text-green-900">
                  Recommended Dose Range
                </h4>
                <p className="mt-1 text-base font-bold text-green-800">
                  {recommendedRange.minMg} - {recommendedRange.maxMg} mg
                </p>
                <p className="mt-1 text-xs text-green-700">
                  For {weightKg} kg: {MIN_MG_PER_KG}-{MAX_MG_PER_KG} mg per kg of body weight
                </p>
                <div className="mt-2 flex items-center space-x-2">
                  <span className="inline-flex items-center rounded-full border bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 border-green-200">
                    ✓ Safe Range
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Age-Based Safety Warnings */}
        {ageWarnings.length > 0 && (
          <div className="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4">
            <div className="flex items-start space-x-3">
              <div className="flex-shrink-0">
                <svg
                  className="h-5 w-5 text-orange-600"
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
              <div className="flex-1">
                <h4 className="text-sm font-semibold text-orange-800">Age-Based Safety Notice</h4>
                <div className="mt-2 space-y-1">
                  {ageWarnings.map((warning, index) => (
                    <p key={index} className="text-sm text-orange-700">
                      {warning}
                    </p>
                  ))}
                </div>
              </div>
            </div>
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

                // Calculate mg/kg for min and max doses
                const minMgPerKg = weightKg !== null ? calculateMgPerKg(weightKg, info.minDoseMg) : 0;
                const maxMgPerKg = weightKg !== null ? calculateMgPerKg(weightKg, info.maxDoseMg) : 0;

                // Determine severity (use max dose for safety check)
                const severity = getDoseSeverity(maxMgPerKg);
                const isOverdose = weightKg !== null && isOverdoseRisk(weightKg, info.maxDoseMg);

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
                      <div className="space-y-1">
                        <span className="text-sm font-medium text-gray-900">
                          {formatDoseMg(info.minDoseMg, info.maxDoseMg)}
                        </span>
                        <div className="flex items-center space-x-1">
                          <span className={`inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-medium ${getSeverityBadgeClass(severity)}`}>
                            {minMgPerKg.toFixed(1)}-{maxMgPerKg.toFixed(1)} mg/kg
                          </span>
                          {isOverdose && (
                            <span className="inline-flex items-center rounded border bg-red-100 px-1.5 py-0.5 text-xs font-bold text-red-800 border-red-200">
                              ⚠️ Risk
                            </span>
                          )}
                        </div>
                      </div>
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

        {/* Dose Safety Legend */}
        <div className="mt-3 rounded-lg bg-gray-50 p-3">
          <h5 className="text-xs font-semibold text-gray-700 mb-2">Dose Safety Guide:</h5>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
            <div className="flex items-center space-x-2">
              <span className="inline-flex items-center rounded border bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 border-green-200">
                Safe
              </span>
              <span className="text-gray-600">{MIN_MG_PER_KG}-{MAX_MG_PER_KG} mg/kg</span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="inline-flex items-center rounded border bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 border-amber-200">
                Warning
              </span>
              <span className="text-gray-600">Borderline dose</span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="inline-flex items-center rounded border bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 border-red-200">
                Risk
              </span>
              <span className="text-gray-600">&gt;{MAX_MG_PER_KG} mg/kg overdose risk</span>
            </div>
          </div>
        </div>

        {/* Overdose Warning - Only show if any dose poses risk */}
        {weightKg !== null && dosing.some(info => isOverdoseRisk(weightKg, info.maxDoseMg)) && (
          <div className="mt-4 rounded-lg bg-red-50 border-2 border-red-200 p-4">
            <div className="flex items-start space-x-3">
              <div className="flex-shrink-0">
                <svg
                  className="h-6 w-6 text-red-600"
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
                <h4 className="text-sm font-semibold text-red-800">⚠️ OVERDOSE RISK DETECTED</h4>
                <p className="mt-1 text-sm text-red-700">
                  One or more doses exceed the maximum safe dose of {MAX_MG_PER_KG} mg/kg for this weight.
                </p>
                <p className="mt-2 text-sm font-medium text-red-800">
                  DO NOT administer doses marked with "Risk". Consult a healthcare provider immediately
                  if unsure about proper dosing.
                </p>
              </div>
            </div>
          </div>
        )}

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
