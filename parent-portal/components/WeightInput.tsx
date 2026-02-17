'use client';

import { useState, useCallback, useEffect } from 'react';

/**
 * Weight range constants for medical protocol dosing.
 * Based on Quebec FO-0647 dosing table (4.3kg - 35kg range).
 */
const MIN_WEIGHT_KG = 4.3;
const MAX_WEIGHT_KG = 35;

interface WeightInputProps {
  /** Callback when weight value changes */
  onWeightChange: (weightKg: number | null, isValid: boolean) => void;
  /** Initial weight value in kg */
  initialWeight?: number;
  /** Whether the input is disabled */
  disabled?: boolean;
  /** Optional label text override */
  label?: string;
  /** Optional help text */
  helpText?: string;
  /** Whether to show validation warnings */
  showValidation?: boolean;
}

interface ValidationState {
  isValid: boolean;
  message: string | null;
  type: 'error' | 'warning' | 'info' | null;
}

export function WeightInput({
  onWeightChange,
  initialWeight,
  disabled = false,
  label = "Child's Weight",
  helpText = 'Enter the current weight of your child in kilograms',
  showValidation = true,
}: WeightInputProps) {
  const [weightValue, setWeightValue] = useState<string>(
    initialWeight ? initialWeight.toFixed(1) : ''
  );
  const [validation, setValidation] = useState<ValidationState>({
    isValid: initialWeight ? validateWeight(initialWeight).isValid : false,
    message: null,
    type: null,
  });

  // Validate weight and return validation state
  function validateWeight(weight: number): ValidationState {
    if (isNaN(weight) || weight <= 0) {
      return {
        isValid: false,
        message: 'Please enter a valid weight',
        type: 'error',
      };
    }

    if (weight < MIN_WEIGHT_KG) {
      return {
        isValid: false,
        message: `Weight must be at least ${MIN_WEIGHT_KG} kg for dosing calculations`,
        type: 'error',
      };
    }

    if (weight > MAX_WEIGHT_KG) {
      return {
        isValid: false,
        message: `Weight exceeds ${MAX_WEIGHT_KG} kg. Please consult a healthcare provider for dosing.`,
        type: 'warning',
      };
    }

    return {
      isValid: true,
      message: null,
      type: null,
    };
  }

  // Handle input change
  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const value = e.target.value;

      // Allow empty input or valid decimal numbers
      if (value === '' || /^\d*\.?\d{0,1}$/.test(value)) {
        setWeightValue(value);

        if (value === '') {
          setValidation({
            isValid: false,
            message: null,
            type: null,
          });
          onWeightChange(null, false);
          return;
        }

        const numValue = parseFloat(value);
        if (!isNaN(numValue)) {
          const validationResult = validateWeight(numValue);
          setValidation(validationResult);
          onWeightChange(numValue, validationResult.isValid);
        }
      }
    },
    [onWeightChange]
  );

  // Handle input blur - format to one decimal place
  const handleBlur = useCallback(() => {
    if (weightValue === '') return;

    const numValue = parseFloat(weightValue);
    if (!isNaN(numValue) && numValue > 0) {
      setWeightValue(numValue.toFixed(1));
    }
  }, [weightValue]);

  // Update validation state when initial weight changes
  useEffect(() => {
    if (initialWeight !== undefined) {
      setWeightValue(initialWeight.toFixed(1));
      const validationResult = validateWeight(initialWeight);
      setValidation(validationResult);
    }
  }, [initialWeight]);

  // Get input border color based on validation state
  const getBorderClass = (): string => {
    if (disabled) return 'border-gray-200 bg-gray-50';
    if (!showValidation || weightValue === '') return 'border-gray-300';
    if (!validation.isValid) {
      return validation.type === 'warning'
        ? 'border-orange-500 focus:border-orange-500 focus:ring-orange-500'
        : 'border-red-500 focus:border-red-500 focus:ring-red-500';
    }
    return 'border-green-500 focus:border-green-500 focus:ring-green-500';
  };

  // Get validation message color
  const getMessageClass = (): string => {
    switch (validation.type) {
      case 'error':
        return 'text-red-600';
      case 'warning':
        return 'text-orange-600';
      case 'info':
        return 'text-blue-600';
      default:
        return 'text-gray-500';
    }
  };

  return (
    <div className="weight-input-container">
      {/* Label */}
      <label
        htmlFor="weight-input"
        className="block text-sm font-medium text-gray-700 mb-1"
      >
        {label}
        <span className="text-red-500 ml-1">*</span>
      </label>

      {/* Help text */}
      {helpText && (
        <p className="text-xs text-gray-500 mb-2">{helpText}</p>
      )}

      {/* Input container */}
      <div className="relative">
        {/* Scale icon */}
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg
            className={`h-5 w-5 ${disabled ? 'text-gray-300' : 'text-gray-400'}`}
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

        {/* Weight input */}
        <input
          id="weight-input"
          type="text"
          inputMode="decimal"
          value={weightValue}
          onChange={handleChange}
          onBlur={handleBlur}
          disabled={disabled}
          placeholder="0.0"
          className={`
            block w-full pl-10 pr-12 py-3
            rounded-lg border-2
            text-gray-900 placeholder-gray-400
            focus:outline-none focus:ring-2 focus:ring-offset-0
            transition-colors duration-200
            ${getBorderClass()}
            ${disabled ? 'cursor-not-allowed opacity-75' : ''}
          `}
          aria-describedby="weight-unit weight-validation"
          aria-invalid={!validation.isValid && weightValue !== ''}
        />

        {/* Unit label */}
        <div
          id="weight-unit"
          className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"
        >
          <span className={`text-sm font-medium ${disabled ? 'text-gray-300' : 'text-gray-500'}`}>
            kg
          </span>
        </div>
      </div>

      {/* Validation message */}
      {showValidation && validation.message && weightValue !== '' && (
        <div
          id="weight-validation"
          className={`mt-2 flex items-start text-xs ${getMessageClass()}`}
          role="alert"
        >
          {validation.type === 'error' ? (
            <svg
              className="h-4 w-4 mr-1 flex-shrink-0"
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
          ) : (
            <svg
              className="h-4 w-4 mr-1 flex-shrink-0"
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
          )}
          <span>{validation.message}</span>
        </div>
      )}

      {/* Valid weight confirmation */}
      {showValidation && validation.isValid && weightValue !== '' && (
        <div className="mt-2 flex items-center text-xs text-green-600">
          <svg
            className="h-4 w-4 mr-1"
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
          <span>Weight is within valid range for dosing</span>
        </div>
      )}

      {/* Weight range info */}
      <div className="mt-3 p-3 bg-gray-50 rounded-lg">
        <p className="text-xs text-gray-600">
          <span className="font-medium">Valid range:</span> {MIN_WEIGHT_KG} kg - {MAX_WEIGHT_KG} kg
        </p>
        <p className="text-xs text-gray-500 mt-1">
          Weight must be updated every 3 months for accurate dosing calculations.
        </p>
      </div>
    </div>
  );
}

// Export constants for use in other components
export { MIN_WEIGHT_KG, MAX_WEIGHT_KG };
