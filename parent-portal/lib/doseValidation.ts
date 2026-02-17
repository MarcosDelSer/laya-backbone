/**
 * Dose Validation for Quebec FO-0647 Acetaminophen Protocol
 *
 * Provides client-side dose validation for acetaminophen administration
 * per Quebec childcare regulations (FO-0647). Mirrors backend validation
 * logic to provide immediate safety feedback to parents.
 *
 * Quebec FO-0647 Requirements:
 * - Weight range: 4.3kg - 35kg
 * - Dosing: 10-15 mg/kg per dose
 * - Concentrations: 80mg/mL (drops), 160mg/5mL (suspension), 325mg/500mg (tablets)
 * - Maximum 5 doses per 24 hours
 * - Minimum 4-6 hour interval between doses
 */

// ============================================================================
// Constants
// ============================================================================

/**
 * Minimum weight for acetaminophen protocol (kg).
 */
export const MIN_WEIGHT_KG = 4.3;

/**
 * Maximum weight for acetaminophen protocol (kg).
 */
export const MAX_WEIGHT_KG = 35.0;

/**
 * Minimum recommended dose per kilogram (mg/kg).
 */
export const MIN_MG_PER_KG = 10.0;

/**
 * Maximum recommended dose per kilogram (mg/kg).
 */
export const MAX_MG_PER_KG = 15.0;

/**
 * Maximum number of doses allowed per 24 hours.
 */
export const MAX_DAILY_DOSES = 5;

/**
 * Minimum interval between doses (hours).
 */
export const MIN_INTERVAL_HOURS = 4;

// ============================================================================
// Types
// ============================================================================

/**
 * Acetaminophen concentration types per Quebec FO-0647.
 */
export type Concentration = '80mg/mL' | '160mg/5mL' | '325mg' | '500mg';

/**
 * Validation severity levels.
 */
export type ValidationSeverity = 'safe' | 'warning' | 'error';

/**
 * Dose information from dosing table.
 */
export interface DoseInfo {
  weightMinKg: number;
  weightMaxKg: number;
  concentration: Concentration;
  amount: string;
  mg: number;
  mgPerKg: number;
}

/**
 * Dose range for a given weight.
 */
export interface DoseRange {
  minMg: number;
  maxMg: number;
  weightKg: number;
}

/**
 * Dose validation result.
 */
export interface DoseValidationResult {
  valid: boolean;
  severity: ValidationSeverity;
  errors: string[];
  warnings: string[];
  recommendedRange?: DoseRange;
}

/**
 * Dosing table entry for a weight range.
 */
interface DosingTableEntry {
  weightMinKg: number;
  weightMaxKg: number;
  doses: {
    [key in Concentration]?: {
      amount: string;
      mg: number;
    };
  };
}

// ============================================================================
// Quebec FO-0647 Dosing Table
// ============================================================================

/**
 * Official Quebec FO-0647 dosing table for acetaminophen.
 *
 * Defines weight-based dose recommendations for each concentration type.
 * Based on official Quebec childcare medication protocol.
 */
const DOSING_TABLE: DosingTableEntry[] = [
  {
    weightMinKg: 4.3,
    weightMaxKg: 5.4,
    doses: {
      '80mg/mL': { amount: '0.6 mL', mg: 48 },
      '160mg/5mL': { amount: '1.5 mL', mg: 48 },
    },
  },
  {
    weightMinKg: 5.5,
    weightMaxKg: 7.9,
    doses: {
      '80mg/mL': { amount: '0.8 mL', mg: 64 },
      '160mg/5mL': { amount: '2 mL', mg: 64 },
    },
  },
  {
    weightMinKg: 8.0,
    weightMaxKg: 10.9,
    doses: {
      '80mg/mL': { amount: '1.2 mL', mg: 96 },
      '160mg/5mL': { amount: '3 mL', mg: 96 },
    },
  },
  {
    weightMinKg: 11.0,
    weightMaxKg: 15.9,
    doses: {
      '80mg/mL': { amount: '1.6 mL', mg: 128 },
      '160mg/5mL': { amount: '4 mL', mg: 128 },
    },
  },
  {
    weightMinKg: 16.0,
    weightMaxKg: 21.9,
    doses: {
      '80mg/mL': { amount: '2.4 mL', mg: 192 },
      '160mg/5mL': { amount: '6 mL', mg: 192 },
      '325mg': { amount: '½ tablet', mg: 162.5 },
    },
  },
  {
    weightMinKg: 22.0,
    weightMaxKg: 26.9,
    doses: {
      '160mg/5mL': { amount: '8 mL', mg: 256 },
      '325mg': { amount: '1 tablet', mg: 325 },
    },
  },
  {
    weightMinKg: 27.0,
    weightMaxKg: 31.9,
    doses: {
      '160mg/5mL': { amount: '10 mL', mg: 320 },
      '325mg': { amount: '1 tablet', mg: 325 },
    },
  },
  {
    weightMinKg: 32.0,
    weightMaxKg: 35.0,
    doses: {
      '160mg/5mL': { amount: '12 mL', mg: 384 },
      '325mg': { amount: '1 tablet', mg: 325 },
      '500mg': { amount: '1 tablet', mg: 500 },
    },
  },
];

// ============================================================================
// Core Validation Functions
// ============================================================================

/**
 * Calculate recommended dose for a given weight and concentration.
 *
 * Looks up the Quebec FO-0647 dosing table to find the appropriate dose
 * for the child's weight and specified medication concentration.
 *
 * @param weightKg - Child's weight in kilograms
 * @param concentration - Medication concentration type
 * @returns Dose information or null if not found
 *
 * @example
 * const dose = calculateRecommendedDose(12, '160mg/5mL');
 * // Returns: { weightMinKg: 11.0, weightMaxKg: 15.9, concentration: '160mg/5mL', amount: '4 mL', mg: 128, mgPerKg: 10.67 }
 */
export function calculateRecommendedDose(
  weightKg: number,
  concentration: Concentration
): DoseInfo | null {
  if (weightKg < MIN_WEIGHT_KG || weightKg > MAX_WEIGHT_KG) {
    return null;
  }

  for (const range of DOSING_TABLE) {
    if (weightKg >= range.weightMinKg && weightKg <= range.weightMaxKg) {
      const dose = range.doses[concentration];
      if (dose) {
        return {
          weightMinKg: range.weightMinKg,
          weightMaxKg: range.weightMaxKg,
          concentration,
          amount: dose.amount,
          mg: dose.mg,
          mgPerKg: parseFloat((dose.mg / weightKg).toFixed(2)),
        };
      }
    }
  }

  return null;
}

/**
 * Validate a proposed dose against Quebec FO-0647 requirements.
 *
 * Performs comprehensive safety validation including:
 * - Weight range verification
 * - Concentration validation
 * - Dose range compliance (10-15 mg/kg)
 * - Overdose risk detection
 * - Dosing table compliance
 *
 * @param weightKg - Child's weight in kilograms
 * @param doseMg - Proposed dose in milligrams
 * @param concentration - Medication concentration type
 * @returns Validation result with errors, warnings, and recommended range
 *
 * @example
 * const result = validateDose(12, 150, '160mg/5mL');
 * if (!result.valid) {
 *   console.error(result.errors);
 * }
 */
export function validateDose(
  weightKg: number,
  doseMg: number,
  concentration: Concentration
): DoseValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Validate weight range
  if (weightKg < MIN_WEIGHT_KG) {
    errors.push(
      `Weight ${weightKg.toFixed(1)}kg is below minimum ${MIN_WEIGHT_KG}kg for acetaminophen protocol`
    );
  }

  if (weightKg > MAX_WEIGHT_KG) {
    errors.push(
      `Weight ${weightKg.toFixed(1)}kg exceeds maximum ${MAX_WEIGHT_KG}kg for acetaminophen protocol`
    );
  }

  // Validate concentration
  if (!isValidConcentration(concentration)) {
    errors.push(
      `Invalid concentration "${concentration}". Must be one of: ${getValidConcentrations().join(', ')}`
    );
  }

  // Calculate recommended dose range
  const minRecommendedMg = weightKg * MIN_MG_PER_KG;
  const maxRecommendedMg = weightKg * MAX_MG_PER_KG;
  const mgPerKg = calculateMgPerKg(weightKg, doseMg);

  // Check if dose is too low (allow 10% tolerance)
  if (doseMg < minRecommendedMg * 0.9) {
    errors.push(
      `Dose ${doseMg.toFixed(1)}mg is too low for weight ${weightKg.toFixed(1)}kg. Recommended range: ${minRecommendedMg.toFixed(1)}-${maxRecommendedMg.toFixed(1)}mg`
    );
  }

  // Check for overdose risk
  if (doseMg > maxRecommendedMg) {
    errors.push(
      `OVERDOSE RISK: Dose ${doseMg.toFixed(1)}mg exceeds maximum ${maxRecommendedMg.toFixed(1)}mg for weight ${weightKg.toFixed(1)}kg (${mgPerKg.toFixed(1)} mg/kg)`
    );
  }

  // Validate against dosing table
  const tableValidation = validateAgainstDosingTable(weightKg, doseMg, concentration);
  errors.push(...tableValidation.errors);

  // Generate warnings for borderline doses
  if (mgPerKg >= 14.0 && mgPerKg <= MAX_MG_PER_KG) {
    warnings.push(
      `Dose ${doseMg.toFixed(1)}mg (${mgPerKg.toFixed(1)} mg/kg) is at upper limit. Monitor closely.`
    );
  }

  if (mgPerKg >= MIN_MG_PER_KG && mgPerKg <= 11.0) {
    warnings.push(
      `Dose ${doseMg.toFixed(1)}mg (${mgPerKg.toFixed(1)} mg/kg) is at lower limit. May be less effective.`
    );
  }

  // Determine severity
  let severity: ValidationSeverity = 'safe';
  if (errors.length > 0) {
    severity = 'error';
  } else if (warnings.length > 0) {
    severity = 'warning';
  }

  return {
    valid: errors.length === 0,
    severity,
    errors,
    warnings,
    recommendedRange: {
      minMg: parseFloat(minRecommendedMg.toFixed(1)),
      maxMg: parseFloat(maxRecommendedMg.toFixed(1)),
      weightKg,
    },
  };
}

/**
 * Check if a dose poses an overdose risk.
 *
 * A dose is considered an overdose risk if it exceeds 15 mg/kg,
 * which is the maximum safe dose per Quebec FO-0647 protocol.
 *
 * @param weightKg - Child's weight in kilograms
 * @param doseMg - Proposed dose in milligrams
 * @returns True if overdose risk detected
 *
 * @example
 * const isRisk = isOverdoseRisk(10, 200); // true (20 mg/kg > 15 mg/kg)
 */
export function isOverdoseRisk(weightKg: number, doseMg: number): boolean {
  const maxSafeDose = weightKg * MAX_MG_PER_KG;
  return doseMg > maxSafeDose;
}

/**
 * Get the recommended dose range for a given weight.
 *
 * Calculates the minimum and maximum safe dose based on the
 * Quebec FO-0647 protocol (10-15 mg/kg).
 *
 * @param weightKg - Child's weight in kilograms
 * @returns Dose range with min/max in milligrams
 *
 * @example
 * const range = getDoseRange(12);
 * // Returns: { minMg: 120, maxMg: 180, weightKg: 12 }
 */
export function getDoseRange(weightKg: number): DoseRange {
  const minMg = parseFloat((weightKg * MIN_MG_PER_KG).toFixed(1));
  const maxMg = parseFloat((weightKg * MAX_MG_PER_KG).toFixed(1));

  return {
    minMg,
    maxMg,
    weightKg,
  };
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Calculate mg/kg for a given dose and weight.
 *
 * @param weightKg - Child's weight in kilograms
 * @param doseMg - Dose in milligrams
 * @returns Dose in mg/kg
 */
export function calculateMgPerKg(weightKg: number, doseMg: number): number {
  if (weightKg <= 0) {
    return 0;
  }
  return parseFloat((doseMg / weightKg).toFixed(2));
}

/**
 * Check if a concentration is valid per Quebec FO-0647.
 *
 * @param concentration - Concentration to validate
 * @returns True if valid
 */
export function isValidConcentration(concentration: string): concentration is Concentration {
  return getValidConcentrations().includes(concentration as Concentration);
}

/**
 * Get list of all valid concentrations.
 *
 * @returns Array of valid concentration types
 */
export function getValidConcentrations(): Concentration[] {
  return ['80mg/mL', '160mg/5mL', '325mg', '500mg'];
}

/**
 * Get all available doses for a given weight.
 *
 * Returns dose information for all concentrations available
 * at the child's weight per the Quebec FO-0647 dosing table.
 *
 * @param weightKg - Child's weight in kilograms
 * @returns Array of dose options for each available concentration
 */
export function getAvailableDoses(weightKg: number): DoseInfo[] {
  const doses: DoseInfo[] = [];

  for (const range of DOSING_TABLE) {
    if (weightKg >= range.weightMinKg && weightKg <= range.weightMaxKg) {
      for (const [concentration, dose] of Object.entries(range.doses)) {
        doses.push({
          concentration: concentration as Concentration,
          amount: dose.amount,
          mg: dose.mg,
          mgPerKg: parseFloat((dose.mg / weightKg).toFixed(2)),
          weightMinKg: range.weightMinKg,
          weightMaxKg: range.weightMaxKg,
        });
      }
      break;
    }
  }

  return doses;
}

/**
 * Get the complete Quebec FO-0647 dosing table.
 *
 * @returns Complete dosing table
 */
export function getDosingTable(): DosingTableEntry[] {
  return DOSING_TABLE;
}

// ============================================================================
// Private Helper Functions
// ============================================================================

/**
 * Validate dose against Quebec FO-0647 dosing table.
 *
 * @param weightKg - Child's weight in kilograms
 * @param doseMg - Proposed dose in milligrams
 * @param concentration - Medication concentration
 * @returns Validation result with errors
 */
function validateAgainstDosingTable(
  weightKg: number,
  doseMg: number,
  concentration: Concentration
): { errors: string[] } {
  const errors: string[] = [];
  let tableEntry: DosingTableEntry | null = null;

  for (const range of DOSING_TABLE) {
    if (weightKg >= range.weightMinKg && weightKg <= range.weightMaxKg) {
      tableEntry = range;
      break;
    }
  }

  if (!tableEntry) {
    errors.push(`No dosing table entry found for weight ${weightKg.toFixed(1)}kg`);
    return { errors };
  }

  // Check if concentration is available for this weight range
  if (!tableEntry.doses[concentration]) {
    const availableConcentrations = Object.keys(tableEntry.doses);
    errors.push(
      `Concentration ${concentration} not recommended for weight ${weightKg.toFixed(1)}kg. Available: ${availableConcentrations.join(', ')}`
    );
  }

  return { errors };
}
