'use client';

import { useState, useCallback } from 'react';
import type {
  ServiceAgreementAnnex,
  ServiceAgreementAnnexA,
  ServiceAgreementAnnexB,
  ServiceAgreementAnnexC,
  ServiceAgreementAnnexD,
  AnnexStatus,
  AnnexType,
} from '../lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Form data for Annex selections.
 */
export interface AnnexFormData {
  annexA: {
    authorizeFieldTrips: boolean;
    transportationAuthorized: boolean;
    walkingDistanceAuthorized: boolean;
    fieldTripConditions: string;
  };
  annexB: {
    hygieneItemsIncluded: boolean;
    itemsList: string[];
    parentProvides: string[];
  };
  annexC: {
    supplementaryMealsIncluded: boolean;
    mealsIncluded: string[];
    dietaryRestrictions: string;
    allergyInfo: string;
  };
  annexD: {
    extendedHoursRequired: boolean;
    requestedStartTime: string;
    requestedEndTime: string;
    additionalHoursPerDay: number;
    reason: string;
  };
}

interface AnnexFormProps {
  annexes: ServiceAgreementAnnex[];
  onSubmit: (formData: AnnexFormData) => void;
  onCancel?: () => void;
  isSubmitting?: boolean;
  readOnly?: boolean;
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
  }).format(amount);
}

function formatTime(timeString: string): string {
  if (!timeString) return '';
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours, 10);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 || 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

function getAnnexStatusBadge(status: AnnexStatus): { label: string; classes: string } {
  switch (status) {
    case 'signed':
      return { label: 'Signed', classes: 'badge badge-success' };
    case 'declined':
      return { label: 'Declined', classes: 'badge badge-error' };
    case 'not_applicable':
      return { label: 'N/A', classes: 'badge badge-neutral' };
    case 'pending':
    default:
      return { label: 'Pending', classes: 'badge badge-warning' };
  }
}

function getAnnexByType<T extends ServiceAgreementAnnex>(
  annexes: ServiceAgreementAnnex[],
  type: AnnexType
): T | undefined {
  return annexes.find((a) => a.type === type) as T | undefined;
}

// ============================================================================
// Constants
// ============================================================================

const HYGIENE_ITEMS = [
  'Diapers',
  'Wipes',
  'Diaper cream',
  'Sunscreen',
  'Hand sanitizer',
  'Tissues',
  'Extra clothes',
];

const MEAL_OPTIONS = [
  'Breakfast',
  'Morning snack',
  'Lunch',
  'Afternoon snack',
  'Dinner',
];

// ============================================================================
// Sub-Components
// ============================================================================

interface AnnexSectionProps {
  title: string;
  annexType: AnnexType;
  status?: AnnexStatus;
  description: string;
  monthlyFee?: number;
  children: React.ReactNode;
}

function AnnexSection({
  title,
  annexType,
  status,
  description,
  monthlyFee,
  children,
}: AnnexSectionProps) {
  const statusBadge = status ? getAnnexStatusBadge(status) : null;

  return (
    <div className="border border-gray-200 rounded-lg overflow-hidden">
      {/* Header */}
      <div className="bg-gray-50 px-4 py-3 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <span className="flex items-center justify-center h-8 w-8 rounded-full bg-purple-100 text-purple-700 text-sm font-bold">
              {annexType}
            </span>
            <div>
              <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
              <p className="text-xs text-gray-500">{description}</p>
            </div>
          </div>
          <div className="flex items-center space-x-2">
            {monthlyFee !== undefined && monthlyFee > 0 && (
              <span className="text-sm font-medium text-gray-700">
                {formatCurrency(monthlyFee)}/month
              </span>
            )}
            {statusBadge && (
              <span className={statusBadge.classes}>{statusBadge.label}</span>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="px-4 py-4 bg-white">{children}</div>
    </div>
  );
}

interface CheckboxFieldProps {
  id: string;
  label: string;
  description?: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}

function CheckboxField({
  id,
  label,
  description,
  checked,
  onChange,
  disabled,
}: CheckboxFieldProps) {
  return (
    <label
      htmlFor={id}
      className={`flex items-start space-x-3 ${disabled ? 'opacity-60' : 'cursor-pointer'}`}
    >
      <input
        type="checkbox"
        id={id}
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        disabled={disabled}
        className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
      />
      <div>
        <span className="text-sm font-medium text-gray-900">{label}</span>
        {description && (
          <p className="text-xs text-gray-500 mt-0.5">{description}</p>
        )}
      </div>
    </label>
  );
}

interface MultiSelectFieldProps {
  label: string;
  options: string[];
  selected: string[];
  onChange: (selected: string[]) => void;
  disabled?: boolean;
}

function MultiSelectField({
  label,
  options,
  selected,
  onChange,
  disabled,
}: MultiSelectFieldProps) {
  const toggleOption = (option: string) => {
    if (selected.includes(option)) {
      onChange(selected.filter((s) => s !== option));
    } else {
      onChange([...selected, option]);
    }
  };

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-2">{label}</label>
      <div className="space-y-2">
        {options.map((option) => (
          <label
            key={option}
            className={`flex items-center space-x-2 ${disabled ? 'opacity-60' : 'cursor-pointer'}`}
          >
            <input
              type="checkbox"
              checked={selected.includes(option)}
              onChange={() => toggleOption(option)}
              disabled={disabled}
              className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <span className="text-sm text-gray-700">{option}</span>
          </label>
        ))}
      </div>
    </div>
  );
}

interface TextAreaFieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  rows?: number;
}

function TextAreaField({
  id,
  label,
  value,
  onChange,
  placeholder,
  disabled,
  rows = 3,
}: TextAreaFieldProps) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
        {label}
      </label>
      <textarea
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        className="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm disabled:bg-gray-100 disabled:opacity-60"
      />
    </div>
  );
}

interface TimeInputFieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

function TimeInputField({
  id,
  label,
  value,
  onChange,
  disabled,
}: TimeInputFieldProps) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
        {label}
      </label>
      <input
        type="time"
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        className="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm disabled:bg-gray-100 disabled:opacity-60"
      />
    </div>
  );
}

interface NumberInputFieldProps {
  id: string;
  label: string;
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  step?: number;
  disabled?: boolean;
  suffix?: string;
}

function NumberInputField({
  id,
  label,
  value,
  onChange,
  min = 0,
  max,
  step = 0.5,
  disabled,
  suffix,
}: NumberInputFieldProps) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
        {label}
      </label>
      <div className="flex items-center space-x-2">
        <input
          type="number"
          id={id}
          value={value}
          onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
          min={min}
          max={max}
          step={step}
          disabled={disabled}
          className="w-24 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm disabled:bg-gray-100 disabled:opacity-60"
        />
        {suffix && <span className="text-sm text-gray-500">{suffix}</span>}
      </div>
    </div>
  );
}

// ============================================================================
// Main Component
// ============================================================================

export function AnnexForm({
  annexes,
  onSubmit,
  onCancel,
  isSubmitting = false,
  readOnly = false,
}: AnnexFormProps) {
  // Get existing annex data
  const annexA = getAnnexByType<ServiceAgreementAnnexA>(annexes, 'A');
  const annexB = getAnnexByType<ServiceAgreementAnnexB>(annexes, 'B');
  const annexC = getAnnexByType<ServiceAgreementAnnexC>(annexes, 'C');
  const annexD = getAnnexByType<ServiceAgreementAnnexD>(annexes, 'D');

  // Form state for Annex A - Field Trips
  const [authorizeFieldTrips, setAuthorizeFieldTrips] = useState(
    annexA?.authorizeFieldTrips ?? false
  );
  const [transportationAuthorized, setTransportationAuthorized] = useState(
    annexA?.transportationAuthorized ?? false
  );
  const [walkingDistanceAuthorized, setWalkingDistanceAuthorized] = useState(
    annexA?.walkingDistanceAuthorized ?? false
  );
  const [fieldTripConditions, setFieldTripConditions] = useState(
    annexA?.fieldTripConditions ?? ''
  );

  // Form state for Annex B - Hygiene Items
  const [hygieneItemsIncluded, setHygieneItemsIncluded] = useState(
    annexB?.hygieneItemsIncluded ?? false
  );
  const [selectedHygieneItems, setSelectedHygieneItems] = useState<string[]>(
    annexB?.itemsList ?? []
  );
  const [parentProvides, setParentProvides] = useState<string[]>(
    annexB?.parentProvides ?? []
  );

  // Form state for Annex C - Supplementary Meals
  const [supplementaryMealsIncluded, setSupplementaryMealsIncluded] = useState(
    annexC?.supplementaryMealsIncluded ?? false
  );
  const [mealsIncluded, setMealsIncluded] = useState<string[]>(
    annexC?.mealsIncluded ?? []
  );
  const [dietaryRestrictions, setDietaryRestrictions] = useState(
    annexC?.dietaryRestrictions ?? ''
  );
  const [allergyInfo, setAllergyInfo] = useState(annexC?.allergyInfo ?? '');

  // Form state for Annex D - Extended Hours
  const [extendedHoursRequired, setExtendedHoursRequired] = useState(
    annexD?.extendedHoursRequired ?? false
  );
  const [requestedStartTime, setRequestedStartTime] = useState(
    annexD?.requestedStartTime ?? '06:00'
  );
  const [requestedEndTime, setRequestedEndTime] = useState(
    annexD?.requestedEndTime ?? '18:00'
  );
  const [additionalHoursPerDay, setAdditionalHoursPerDay] = useState(
    annexD?.additionalHoursPerDay ?? 0
  );
  const [extendedHoursReason, setExtendedHoursReason] = useState(
    annexD?.reason ?? ''
  );

  // Handle form submission
  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();

      const formData: AnnexFormData = {
        annexA: {
          authorizeFieldTrips,
          transportationAuthorized,
          walkingDistanceAuthorized,
          fieldTripConditions,
        },
        annexB: {
          hygieneItemsIncluded,
          itemsList: selectedHygieneItems,
          parentProvides,
        },
        annexC: {
          supplementaryMealsIncluded,
          mealsIncluded,
          dietaryRestrictions,
          allergyInfo,
        },
        annexD: {
          extendedHoursRequired,
          requestedStartTime,
          requestedEndTime,
          additionalHoursPerDay,
          reason: extendedHoursReason,
        },
      };

      onSubmit(formData);
    },
    [
      authorizeFieldTrips,
      transportationAuthorized,
      walkingDistanceAuthorized,
      fieldTripConditions,
      hygieneItemsIncluded,
      selectedHygieneItems,
      parentProvides,
      supplementaryMealsIncluded,
      mealsIncluded,
      dietaryRestrictions,
      allergyInfo,
      extendedHoursRequired,
      requestedStartTime,
      requestedEndTime,
      additionalHoursPerDay,
      extendedHoursReason,
      onSubmit,
    ]
  );

  const disabled = readOnly || isSubmitting;

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Introduction */}
      <div className="rounded-lg bg-blue-50 border border-blue-200 p-4">
        <div className="flex">
          <svg
            className="h-5 w-5 text-blue-500 flex-shrink-0"
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
            <h3 className="text-sm font-semibold text-blue-900">Optional Annexes</h3>
            <p className="mt-1 text-sm text-blue-700">
              The following annexes are optional additions to your service agreement. Please review
              each section and select the options that apply to your needs. Additional fees may
              apply for some services.
            </p>
          </div>
        </div>
      </div>

      {/* Annex A - Field Trips */}
      <AnnexSection
        title="Field Trips Authorization"
        annexType="A"
        status={annexA?.status}
        description="Authorize participation in organized field trips and outings"
      >
        <div className="space-y-4">
          <CheckboxField
            id="authorizeFieldTrips"
            label="I authorize my child to participate in field trips"
            description="Your child may participate in organized educational outings with the daycare"
            checked={authorizeFieldTrips}
            onChange={setAuthorizeFieldTrips}
            disabled={disabled}
          />

          {authorizeFieldTrips && (
            <div className="ml-7 space-y-4 border-l-2 border-gray-200 pl-4">
              <CheckboxField
                id="transportationAuthorized"
                label="Transportation authorized"
                description="Allow transportation by vehicle for field trips"
                checked={transportationAuthorized}
                onChange={setTransportationAuthorized}
                disabled={disabled}
              />

              <CheckboxField
                id="walkingDistanceAuthorized"
                label="Walking distance authorized"
                description="Allow walking trips within the neighborhood"
                checked={walkingDistanceAuthorized}
                onChange={setWalkingDistanceAuthorized}
                disabled={disabled}
              />

              <TextAreaField
                id="fieldTripConditions"
                label="Special conditions or restrictions (optional)"
                value={fieldTripConditions}
                onChange={setFieldTripConditions}
                placeholder="e.g., Must notify parent 24 hours in advance, no water activities..."
                disabled={disabled}
                rows={2}
              />
            </div>
          )}
        </div>
      </AnnexSection>

      {/* Annex B - Hygiene Items */}
      <AnnexSection
        title="Hygiene Items"
        annexType="B"
        status={annexB?.status}
        description="Daycare-provided hygiene supplies and items"
        monthlyFee={annexB?.monthlyFee}
      >
        <div className="space-y-4">
          <CheckboxField
            id="hygieneItemsIncluded"
            label="Include daycare-provided hygiene items"
            description="The daycare will provide hygiene supplies for an additional monthly fee"
            checked={hygieneItemsIncluded}
            onChange={setHygieneItemsIncluded}
            disabled={disabled}
          />

          {hygieneItemsIncluded && (
            <div className="ml-7 space-y-4 border-l-2 border-gray-200 pl-4">
              <MultiSelectField
                label="Items provided by daycare"
                options={HYGIENE_ITEMS}
                selected={selectedHygieneItems}
                onChange={setSelectedHygieneItems}
                disabled={disabled}
              />

              <div className="pt-2 border-t border-gray-200">
                <MultiSelectField
                  label="Items I will provide myself"
                  options={HYGIENE_ITEMS.filter((item) => !selectedHygieneItems.includes(item))}
                  selected={parentProvides}
                  onChange={setParentProvides}
                  disabled={disabled}
                />
              </div>
            </div>
          )}

          {!hygieneItemsIncluded && (
            <p className="text-sm text-gray-500 italic ml-7">
              If not selected, you will be responsible for providing all hygiene items for your child.
            </p>
          )}
        </div>
      </AnnexSection>

      {/* Annex C - Supplementary Meals */}
      <AnnexSection
        title="Supplementary Meals"
        annexType="C"
        status={annexC?.status}
        description="Additional meal services beyond standard offerings"
        monthlyFee={annexC?.monthlyFee}
      >
        <div className="space-y-4">
          <CheckboxField
            id="supplementaryMealsIncluded"
            label="Include supplementary meal services"
            description="Additional meals beyond the standard meal plan"
            checked={supplementaryMealsIncluded}
            onChange={setSupplementaryMealsIncluded}
            disabled={disabled}
          />

          {supplementaryMealsIncluded && (
            <div className="ml-7 space-y-4 border-l-2 border-gray-200 pl-4">
              <MultiSelectField
                label="Select meals to include"
                options={MEAL_OPTIONS}
                selected={mealsIncluded}
                onChange={setMealsIncluded}
                disabled={disabled}
              />

              <TextAreaField
                id="dietaryRestrictions"
                label="Dietary restrictions"
                value={dietaryRestrictions}
                onChange={setDietaryRestrictions}
                placeholder="e.g., Vegetarian, no pork, halal, kosher..."
                disabled={disabled}
                rows={2}
              />

              <TextAreaField
                id="allergyInfo"
                label="Allergy information"
                value={allergyInfo}
                onChange={setAllergyInfo}
                placeholder="e.g., Peanut allergy (severe), lactose intolerant..."
                disabled={disabled}
                rows={2}
              />
            </div>
          )}
        </div>
      </AnnexSection>

      {/* Annex D - Extended Hours */}
      <AnnexSection
        title="Extended Hours"
        annexType="D"
        status={annexD?.status}
        description="Care hours beyond the standard 10 hours per day"
        monthlyFee={annexD?.monthlyEstimate}
      >
        <div className="space-y-4">
          <CheckboxField
            id="extendedHoursRequired"
            label="I require extended hours beyond 10 hours/day"
            description="Additional hourly fees will apply for extended care"
            checked={extendedHoursRequired}
            onChange={setExtendedHoursRequired}
            disabled={disabled}
          />

          {extendedHoursRequired && (
            <div className="ml-7 space-y-4 border-l-2 border-gray-200 pl-4">
              <div className="grid grid-cols-2 gap-4">
                <TimeInputField
                  id="requestedStartTime"
                  label="Requested drop-off time"
                  value={requestedStartTime}
                  onChange={setRequestedStartTime}
                  disabled={disabled}
                />

                <TimeInputField
                  id="requestedEndTime"
                  label="Requested pick-up time"
                  value={requestedEndTime}
                  onChange={setRequestedEndTime}
                  disabled={disabled}
                />
              </div>

              <NumberInputField
                id="additionalHoursPerDay"
                label="Additional hours per day"
                value={additionalHoursPerDay}
                onChange={setAdditionalHoursPerDay}
                min={0}
                max={4}
                step={0.5}
                suffix="hours"
                disabled={disabled}
              />

              {annexD?.hourlyRate !== undefined && (
                <div className="bg-gray-50 rounded-lg p-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Hourly rate:</span>
                    <span className="font-medium text-gray-900">
                      {formatCurrency(annexD.hourlyRate)}/hour
                    </span>
                  </div>
                  {additionalHoursPerDay > 0 && (
                    <div className="flex justify-between text-sm mt-1">
                      <span className="text-gray-600">Estimated monthly cost:</span>
                      <span className="font-medium text-gray-900">
                        {formatCurrency(annexD.hourlyRate * additionalHoursPerDay * 20)}
                      </span>
                    </div>
                  )}
                </div>
              )}

              <TextAreaField
                id="extendedHoursReason"
                label="Reason for extended hours"
                value={extendedHoursReason}
                onChange={setExtendedHoursReason}
                placeholder="e.g., Work schedule, commute time..."
                disabled={disabled}
                rows={2}
              />
            </div>
          )}

          {!extendedHoursRequired && (
            <p className="text-sm text-gray-500 italic ml-7">
              Standard care hours are up to 10 hours per day. Extended hours are subject to
              availability and additional fees.
            </p>
          )}
        </div>
      </AnnexSection>

      {/* Summary */}
      <div className="rounded-lg bg-gray-50 border border-gray-200 p-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Summary</h4>
        <div className="space-y-2 text-sm">
          <div className="flex items-center justify-between">
            <span className="text-gray-600">Annex A - Field Trips:</span>
            <span className={authorizeFieldTrips ? 'text-green-600 font-medium' : 'text-gray-500'}>
              {authorizeFieldTrips ? 'Authorized' : 'Not selected'}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-gray-600">Annex B - Hygiene Items:</span>
            <span className={hygieneItemsIncluded ? 'text-green-600 font-medium' : 'text-gray-500'}>
              {hygieneItemsIncluded ? 'Included' : 'Not selected'}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-gray-600">Annex C - Supplementary Meals:</span>
            <span
              className={supplementaryMealsIncluded ? 'text-green-600 font-medium' : 'text-gray-500'}
            >
              {supplementaryMealsIncluded ? 'Included' : 'Not selected'}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-gray-600">Annex D - Extended Hours:</span>
            <span className={extendedHoursRequired ? 'text-green-600 font-medium' : 'text-gray-500'}>
              {extendedHoursRequired ? `${additionalHoursPerDay}h extra/day` : 'Not selected'}
            </span>
          </div>
        </div>
      </div>

      {/* Action buttons */}
      {!readOnly && (
        <div className="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
          {onCancel && (
            <button
              type="button"
              onClick={onCancel}
              disabled={isSubmitting}
              className="btn btn-outline"
            >
              Cancel
            </button>
          )}
          <button type="submit" disabled={isSubmitting} className="btn btn-primary">
            {isSubmitting ? (
              <>
                <svg
                  className="mr-2 h-4 w-4 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  />
                  <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
                Saving...
              </>
            ) : (
              <>
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
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                Save Annex Selections
              </>
            )}
          </button>
        </div>
      )}
    </form>
  );
}
