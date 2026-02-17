/**
 * Dose Validation Unit Tests
 * Tests for Quebec FO-0647 acetaminophen dose validation utilities
 */

import { describe, it, expect } from 'vitest'
import {
  MIN_WEIGHT_KG,
  MAX_WEIGHT_KG,
  MIN_MG_PER_KG,
  MAX_MG_PER_KG,
  MAX_DAILY_DOSES,
  MIN_INTERVAL_HOURS,
  calculateRecommendedDose,
  validateDose,
  isOverdoseRisk,
  getDoseRange,
  calculateMgPerKg,
  isValidConcentration,
  getValidConcentrations,
  getAvailableDoses,
  getDosingTable,
  type Concentration,
  type DoseInfo,
  type DoseRange,
  type DoseValidationResult,
} from '@/lib/doseValidation'

// ============================================================================
// Constants Tests
// ============================================================================

describe('Dose Validation Constants', () => {
  it('exports minimum weight constant', () => {
    expect(MIN_WEIGHT_KG).toBe(4.3)
  })

  it('exports maximum weight constant', () => {
    expect(MAX_WEIGHT_KG).toBe(35.0)
  })

  it('exports minimum mg per kg constant', () => {
    expect(MIN_MG_PER_KG).toBe(10.0)
  })

  it('exports maximum mg per kg constant', () => {
    expect(MAX_MG_PER_KG).toBe(15.0)
  })

  it('exports maximum daily doses constant', () => {
    expect(MAX_DAILY_DOSES).toBe(5)
  })

  it('exports minimum interval hours constant', () => {
    expect(MIN_INTERVAL_HOURS).toBe(4)
  })
})

// ============================================================================
// calculateRecommendedDose Tests
// ============================================================================

describe('calculateRecommendedDose', () => {
  it('returns correct dose for minimum weight range with 80mg/mL', () => {
    const dose = calculateRecommendedDose(4.5, '80mg/mL')
    expect(dose).not.toBeNull()
    expect(dose?.concentration).toBe('80mg/mL')
    expect(dose?.mg).toBe(48)
    expect(dose?.amount).toBe('0.6 mL')
    expect(dose?.weightMinKg).toBe(4.3)
    expect(dose?.weightMaxKg).toBe(5.4)
  })

  it('returns correct dose for mid-range weight with 160mg/5mL', () => {
    const dose = calculateRecommendedDose(12, '160mg/5mL')
    expect(dose).not.toBeNull()
    expect(dose?.concentration).toBe('160mg/5mL')
    expect(dose?.mg).toBe(128)
    expect(dose?.amount).toBe('4 mL')
    expect(dose?.weightMinKg).toBe(11.0)
    expect(dose?.weightMaxKg).toBe(15.9)
  })

  it('returns correct dose for upper weight range with 325mg tablet', () => {
    const dose = calculateRecommendedDose(28, '325mg')
    expect(dose).not.toBeNull()
    expect(dose?.concentration).toBe('325mg')
    expect(dose?.mg).toBe(325)
    expect(dose?.amount).toBe('1 tablet')
    expect(dose?.weightMinKg).toBe(27.0)
    expect(dose?.weightMaxKg).toBe(31.9)
  })

  it('returns correct dose for maximum weight with 500mg tablet', () => {
    const dose = calculateRecommendedDose(33, '500mg')
    expect(dose).not.toBeNull()
    expect(dose?.concentration).toBe('500mg')
    expect(dose?.mg).toBe(500)
    expect(dose?.amount).toBe('1 tablet')
  })

  it('calculates correct mg per kg ratio', () => {
    const dose = calculateRecommendedDose(12, '160mg/5mL')
    expect(dose?.mgPerKg).toBeCloseTo(10.67, 2)
  })

  it('returns null for weight below minimum', () => {
    const dose = calculateRecommendedDose(3, '80mg/mL')
    expect(dose).toBeNull()
  })

  it('returns null for weight above maximum', () => {
    const dose = calculateRecommendedDose(40, '160mg/5mL')
    expect(dose).toBeNull()
  })

  it('returns null for unavailable concentration at weight range', () => {
    const dose = calculateRecommendedDose(5, '325mg')
    expect(dose).toBeNull()
  })

  it('handles exact weight range boundaries', () => {
    const doseMin = calculateRecommendedDose(11.0, '160mg/5mL')
    const doseMax = calculateRecommendedDose(15.9, '160mg/5mL')
    expect(doseMin?.mg).toBe(128)
    expect(doseMax?.mg).toBe(128)
  })
})

// ============================================================================
// validateDose Tests
// ============================================================================

describe('validateDose', () => {
  it('validates safe dose within recommended range', () => {
    const result = validateDose(12, 140, '160mg/5mL')
    expect(result.valid).toBe(true)
    expect(result.severity).toBe('safe')
    expect(result.errors).toHaveLength(0)
  })

  it('provides recommended range in validation result', () => {
    const result = validateDose(12, 140, '160mg/5mL')
    expect(result.recommendedRange).toEqual({
      minMg: 120.0,
      maxMg: 180.0,
      weightKg: 12,
    })
  })

  it('detects weight below minimum', () => {
    const result = validateDose(3, 40, '80mg/mL')
    expect(result.valid).toBe(false)
    expect(result.severity).toBe('error')
    expect(result.errors).toContain(
      'Weight 3.0kg is below minimum 4.3kg for acetaminophen protocol'
    )
  })

  it('detects weight above maximum', () => {
    const result = validateDose(40, 400, '160mg/5mL')
    expect(result.valid).toBe(false)
    expect(result.severity).toBe('error')
    expect(result.errors).toContain(
      'Weight 40.0kg exceeds maximum 35kg for acetaminophen protocol'
    )
  })

  it('detects invalid concentration', () => {
    const result = validateDose(12, 140, 'invalid' as Concentration)
    expect(result.valid).toBe(false)
    expect(result.severity).toBe('error')
    expect(result.errors.some((e) => e.includes('Invalid concentration'))).toBe(true)
  })

  it('detects dose too low for weight', () => {
    const result = validateDose(12, 50, '160mg/5mL')
    expect(result.valid).toBe(false)
    expect(result.severity).toBe('error')
    expect(result.errors.some((e) => e.includes('too low'))).toBe(true)
  })

  it('detects overdose risk', () => {
    const result = validateDose(12, 200, '160mg/5mL')
    expect(result.valid).toBe(false)
    expect(result.severity).toBe('error')
    expect(result.errors.some((e) => e.includes('OVERDOSE RISK'))).toBe(true)
  })

  it('warns for dose at upper limit', () => {
    const result = validateDose(12, 175, '160mg/5mL')
    expect(result.valid).toBe(true)
    expect(result.severity).toBe('warning')
    expect(result.warnings.some((w) => w.includes('upper limit'))).toBe(true)
  })

  it('warns for dose at lower limit', () => {
    const result = validateDose(12, 125, '160mg/5mL')
    expect(result.valid).toBe(true)
    expect(result.severity).toBe('warning')
    expect(result.warnings.some((w) => w.includes('lower limit'))).toBe(true)
  })

  it('accepts dose with 10% tolerance below minimum', () => {
    const result = validateDose(12, 110, '160mg/5mL')
    expect(result.valid).toBe(true)
  })

  it('rejects dose below 10% tolerance', () => {
    const result = validateDose(12, 100, '160mg/5mL')
    expect(result.valid).toBe(false)
    expect(result.errors.some((e) => e.includes('too low'))).toBe(true)
  })

  it('detects concentration not available for weight range', () => {
    const result = validateDose(5, 50, '325mg')
    expect(result.valid).toBe(false)
    expect(result.errors.some((e) => e.includes('not recommended for weight'))).toBe(true)
  })

  it('handles multiple validation errors', () => {
    const result = validateDose(3, 300, 'invalid' as Concentration)
    expect(result.valid).toBe(false)
    expect(result.errors.length).toBeGreaterThan(1)
  })
})

// ============================================================================
// isOverdoseRisk Tests
// ============================================================================

describe('isOverdoseRisk', () => {
  it('returns false for safe dose', () => {
    const isRisk = isOverdoseRisk(12, 140)
    expect(isRisk).toBe(false)
  })

  it('returns false for dose at maximum safe limit', () => {
    const isRisk = isOverdoseRisk(12, 180)
    expect(isRisk).toBe(false)
  })

  it('returns true for dose exceeding maximum', () => {
    const isRisk = isOverdoseRisk(12, 181)
    expect(isRisk).toBe(true)
  })

  it('returns true for significantly high dose', () => {
    const isRisk = isOverdoseRisk(10, 200)
    expect(isRisk).toBe(true)
  })

  it('handles minimum weight boundary', () => {
    const isRisk = isOverdoseRisk(4.3, 65)
    expect(isRisk).toBe(false)
    const isRisk2 = isOverdoseRisk(4.3, 66)
    expect(isRisk2).toBe(true)
  })

  it('handles maximum weight boundary', () => {
    const isRisk = isOverdoseRisk(35, 525)
    expect(isRisk).toBe(false)
    const isRisk2 = isOverdoseRisk(35, 526)
    expect(isRisk2).toBe(true)
  })
})

// ============================================================================
// getDoseRange Tests
// ============================================================================

describe('getDoseRange', () => {
  it('calculates correct range for 12kg child', () => {
    const range = getDoseRange(12)
    expect(range.minMg).toBe(120.0)
    expect(range.maxMg).toBe(180.0)
    expect(range.weightKg).toBe(12)
  })

  it('calculates correct range for minimum weight', () => {
    const range = getDoseRange(4.3)
    expect(range.minMg).toBe(43.0)
    expect(range.maxMg).toBe(64.5)
    expect(range.weightKg).toBe(4.3)
  })

  it('calculates correct range for maximum weight', () => {
    const range = getDoseRange(35)
    expect(range.minMg).toBe(350.0)
    expect(range.maxMg).toBe(525.0)
    expect(range.weightKg).toBe(35)
  })

  it('handles decimal weights correctly', () => {
    const range = getDoseRange(12.5)
    expect(range.minMg).toBe(125.0)
    expect(range.maxMg).toBe(187.5)
  })

  it('rounds to one decimal place', () => {
    const range = getDoseRange(12.33)
    expect(range.minMg).toBe(123.3)
    expect(range.maxMg).toBe(185.0)
  })
})

// ============================================================================
// calculateMgPerKg Tests
// ============================================================================

describe('calculateMgPerKg', () => {
  it('calculates correct mg/kg ratio', () => {
    const mgPerKg = calculateMgPerKg(12, 140)
    expect(mgPerKg).toBeCloseTo(11.67, 2)
  })

  it('handles minimum dose calculation', () => {
    const mgPerKg = calculateMgPerKg(12, 120)
    expect(mgPerKg).toBe(10.0)
  })

  it('handles maximum dose calculation', () => {
    const mgPerKg = calculateMgPerKg(12, 180)
    expect(mgPerKg).toBe(15.0)
  })

  it('returns 0 for zero weight', () => {
    const mgPerKg = calculateMgPerKg(0, 100)
    expect(mgPerKg).toBe(0)
  })

  it('returns 0 for negative weight', () => {
    const mgPerKg = calculateMgPerKg(-5, 100)
    expect(mgPerKg).toBe(0)
  })

  it('rounds to two decimal places', () => {
    const mgPerKg = calculateMgPerKg(12.3, 128)
    expect(mgPerKg).toBeCloseTo(10.41, 2)
  })

  it('handles large doses correctly', () => {
    const mgPerKg = calculateMgPerKg(35, 500)
    expect(mgPerKg).toBeCloseTo(14.29, 2)
  })
})

// ============================================================================
// Concentration Validation Tests
// ============================================================================

describe('isValidConcentration', () => {
  it('returns true for 80mg/mL', () => {
    expect(isValidConcentration('80mg/mL')).toBe(true)
  })

  it('returns true for 160mg/5mL', () => {
    expect(isValidConcentration('160mg/5mL')).toBe(true)
  })

  it('returns true for 325mg', () => {
    expect(isValidConcentration('325mg')).toBe(true)
  })

  it('returns true for 500mg', () => {
    expect(isValidConcentration('500mg')).toBe(true)
  })

  it('returns false for invalid concentration', () => {
    expect(isValidConcentration('invalid')).toBe(false)
  })

  it('returns false for empty string', () => {
    expect(isValidConcentration('')).toBe(false)
  })

  it('returns false for similar but incorrect format', () => {
    expect(isValidConcentration('80 mg/mL')).toBe(false)
  })
})

describe('getValidConcentrations', () => {
  it('returns all valid concentrations', () => {
    const concentrations = getValidConcentrations()
    expect(concentrations).toEqual(['80mg/mL', '160mg/5mL', '325mg', '500mg'])
  })

  it('returns array with correct length', () => {
    const concentrations = getValidConcentrations()
    expect(concentrations).toHaveLength(4)
  })
})

// ============================================================================
// getAvailableDoses Tests
// ============================================================================

describe('getAvailableDoses', () => {
  it('returns available doses for minimum weight range', () => {
    const doses = getAvailableDoses(4.5)
    expect(doses).toHaveLength(2)
    expect(doses.some((d) => d.concentration === '80mg/mL')).toBe(true)
    expect(doses.some((d) => d.concentration === '160mg/5mL')).toBe(true)
  })

  it('returns available doses for mid-range weight', () => {
    const doses = getAvailableDoses(12)
    expect(doses).toHaveLength(2)
    expect(doses.some((d) => d.concentration === '80mg/mL')).toBe(true)
    expect(doses.some((d) => d.concentration === '160mg/5mL')).toBe(true)
  })

  it('returns available doses for upper weight range', () => {
    const doses = getAvailableDoses(33)
    expect(doses).toHaveLength(3)
    expect(doses.some((d) => d.concentration === '160mg/5mL')).toBe(true)
    expect(doses.some((d) => d.concentration === '325mg')).toBe(true)
    expect(doses.some((d) => d.concentration === '500mg')).toBe(true)
  })

  it('returns empty array for weight below minimum', () => {
    const doses = getAvailableDoses(3)
    expect(doses).toHaveLength(0)
  })

  it('returns empty array for weight above maximum', () => {
    const doses = getAvailableDoses(40)
    expect(doses).toHaveLength(0)
  })

  it('includes correct dose information', () => {
    const doses = getAvailableDoses(12)
    const infantDrops = doses.find((d) => d.concentration === '80mg/mL')
    expect(infantDrops).toBeDefined()
    expect(infantDrops?.mg).toBe(128)
    expect(infantDrops?.amount).toBe('1.6 mL')
    expect(infantDrops?.weightMinKg).toBe(11.0)
    expect(infantDrops?.weightMaxKg).toBe(15.9)
  })

  it('calculates mg per kg for each dose', () => {
    const doses = getAvailableDoses(12)
    doses.forEach((dose) => {
      expect(dose.mgPerKg).toBeGreaterThan(0)
      expect(dose.mgPerKg).toBeCloseTo(dose.mg / 12, 2)
    })
  })

  it('handles weight at exact range boundary', () => {
    const doses = getAvailableDoses(16.0)
    expect(doses).toHaveLength(3)
    expect(doses.some((d) => d.concentration === '325mg')).toBe(true)
  })
})

// ============================================================================
// getDosingTable Tests
// ============================================================================

describe('getDosingTable', () => {
  it('returns dosing table array', () => {
    const table = getDosingTable()
    expect(Array.isArray(table)).toBe(true)
  })

  it('returns correct number of weight ranges', () => {
    const table = getDosingTable()
    expect(table).toHaveLength(8)
  })

  it('contains minimum weight range entry', () => {
    const table = getDosingTable()
    const minEntry = table[0]
    expect(minEntry.weightMinKg).toBe(4.3)
    expect(minEntry.weightMaxKg).toBe(5.4)
  })

  it('contains maximum weight range entry', () => {
    const table = getDosingTable()
    const maxEntry = table[table.length - 1]
    expect(maxEntry.weightMinKg).toBe(32.0)
    expect(maxEntry.weightMaxKg).toBe(35.0)
  })

  it('all entries have weight ranges', () => {
    const table = getDosingTable()
    table.forEach((entry) => {
      expect(entry.weightMinKg).toBeDefined()
      expect(entry.weightMaxKg).toBeDefined()
      expect(entry.weightMinKg).toBeLessThan(entry.weightMaxKg)
    })
  })

  it('all entries have dose information', () => {
    const table = getDosingTable()
    table.forEach((entry) => {
      expect(entry.doses).toBeDefined()
      expect(Object.keys(entry.doses).length).toBeGreaterThan(0)
    })
  })

  it('dose entries contain amount and mg', () => {
    const table = getDosingTable()
    table.forEach((entry) => {
      Object.values(entry.doses).forEach((dose) => {
        expect(dose?.amount).toBeDefined()
        expect(dose?.mg).toBeDefined()
        expect(dose?.mg).toBeGreaterThan(0)
      })
    })
  })

  it('weight ranges are continuous', () => {
    const table = getDosingTable()
    for (let i = 0; i < table.length - 1; i++) {
      const currentMax = table[i].weightMaxKg
      const nextMin = table[i + 1].weightMinKg
      expect(nextMin).toBeCloseTo(currentMax + 0.1, 1)
    }
  })
})

// ============================================================================
// Integration Tests
// ============================================================================

describe('Dose Validation Integration', () => {
  it('validates complete workflow for valid dose', () => {
    const weightKg = 12
    const concentration: Concentration = '160mg/5mL'

    // Get recommended dose
    const recommended = calculateRecommendedDose(weightKg, concentration)
    expect(recommended).not.toBeNull()

    // Validate the recommended dose
    const validation = validateDose(weightKg, recommended!.mg, concentration)
    expect(validation.valid).toBe(true)
    expect(validation.severity).toBe('safe')

    // Check it's not an overdose risk
    const isRisk = isOverdoseRisk(weightKg, recommended!.mg)
    expect(isRisk).toBe(false)
  })

  it('validates complete workflow for all weight ranges', () => {
    const testWeights = [5, 8, 10, 12, 18, 24, 30, 33]

    testWeights.forEach((weightKg) => {
      const doses = getAvailableDoses(weightKg)
      expect(doses.length).toBeGreaterThan(0)

      doses.forEach((dose) => {
        const validation = validateDose(weightKg, dose.mg, dose.concentration)
        expect(validation.valid).toBe(true)
      })
    })
  })

  it('calculates dose range matching recommended doses', () => {
    const weightKg = 12
    const range = getDoseRange(weightKg)
    const recommended = calculateRecommendedDose(weightKg, '160mg/5mL')

    expect(recommended).not.toBeNull()
    expect(recommended!.mg).toBeGreaterThanOrEqual(range.minMg)
    expect(recommended!.mg).toBeLessThanOrEqual(range.maxMg)
  })

  it('validates mg per kg calculation matches validation', () => {
    const weightKg = 12
    const doseMg = 140
    const mgPerKg = calculateMgPerKg(weightKg, doseMg)

    expect(mgPerKg).toBeGreaterThanOrEqual(MIN_MG_PER_KG)
    expect(mgPerKg).toBeLessThanOrEqual(MAX_MG_PER_KG)

    const validation = validateDose(weightKg, doseMg, '160mg/5mL')
    expect(validation.valid).toBe(true)
  })
})
