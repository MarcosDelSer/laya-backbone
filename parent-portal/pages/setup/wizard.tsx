/**
 * Setup Wizard Page
 *
 * Next.js page component for the setup wizard.
 * Provides a step-by-step interface for configuring the daycare system.
 *
 * @module pages/setup/wizard
 */

import { useState } from 'react';
import { useSetupWizard } from '@/lib/setup/useSetupWizard';

/**
 * Wizard Layout Component
 *
 * Provides consistent layout for wizard steps with progress indicator,
 * navigation buttons, and error handling.
 */
function WizardLayout({
  currentStepName,
  completionPercentage,
  children,
  onNext,
  onPrevious,
  onSaveResume,
  isLoading,
  error,
  canGoPrevious,
  isLastStep,
}: {
  currentStepName: string;
  completionPercentage: number;
  children: React.ReactNode;
  onNext: () => void;
  onPrevious: () => void;
  onSaveResume: () => void;
  isLoading: boolean;
  error: string | null;
  canGoPrevious: boolean;
  isLastStep: boolean;
}) {
  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">Setup Wizard</h1>

          {/* Progress Bar */}
          <div className="mb-4">
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm font-medium text-gray-700">Progress</span>
              <span className="text-sm font-medium text-gray-700">
                {Math.round(completionPercentage)}%
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
              <div
                className="bg-blue-600 h-full transition-all duration-300 ease-in-out"
                style={{ width: `${completionPercentage}%` }}
              />
            </div>
          </div>

          {/* Current Step */}
          <h2 className="text-xl font-semibold text-gray-800">{currentStepName}</h2>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div className="flex items-start">
              <div className="flex-shrink-0">
                <svg
                  className="h-5 w-5 text-red-400"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                    clipRule="evenodd"
                  />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-red-800">Error</h3>
                <p className="text-sm text-red-700 mt-1">{error}</p>
              </div>
            </div>
          </div>
        )}

        {/* Step Content */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          {children}
        </div>

        {/* Navigation Buttons */}
        <div className="flex justify-between items-center">
          <button
            onClick={onPrevious}
            disabled={!canGoPrevious || isLoading}
            className={`px-6 py-2 rounded-md font-medium transition-colors ${
              !canGoPrevious || isLoading
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-gray-300 text-gray-700 hover:bg-gray-400'
            }`}
          >
            ← Previous
          </button>

          <button
            onClick={onSaveResume}
            disabled={isLoading}
            className={`px-6 py-2 rounded-md font-medium transition-colors ${
              isLoading
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-yellow-500 text-white hover:bg-yellow-600'
            }`}
          >
            Save & Resume Later
          </button>

          <button
            onClick={onNext}
            disabled={isLoading}
            className={`px-6 py-2 rounded-md font-medium transition-colors ${
              isLoading
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-blue-600 text-white hover:bg-blue-700'
            }`}
          >
            {isLoading ? (
              <span className="flex items-center">
                <svg
                  className="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
                Processing...
              </span>
            ) : isLastStep ? (
              'Complete Wizard'
            ) : (
              'Next →'
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

/**
 * Setup Wizard Page Component
 *
 * Main wizard page that orchestrates the setup process.
 * Uses the useSetupWizard hook for state management.
 */
export default function SetupWizardPage() {
  const {
    currentStep,
    data,
    updateData,
    next,
    previous,
    saveAndResume,
    isLoading,
    error,
    completionPercentage,
  } = useSetupWizard();

  // Show loading state while fetching initial data
  if (!currentStep && isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4" />
          <p className="text-gray-600">Loading wizard...</p>
        </div>
      </div>
    );
  }

  // Show completion message if wizard is done
  if (!currentStep && !isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-md p-8 max-w-md text-center">
          <div className="text-green-500 text-6xl mb-4">✓</div>
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            Setup Complete!
          </h1>
          <p className="text-gray-600 mb-6">
            Your daycare system is now configured and ready to use.
          </p>
          <a
            href="/"
            className="inline-block px-6 py-3 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 transition-colors"
          >
            Go to Dashboard
          </a>
        </div>
      </div>
    );
  }

  const canGoPrevious = currentStep?.id !== 'organization_info';
  const isLastStep = currentStep?.id === 'completion';

  return (
    <WizardLayout
      currentStepName={currentStep?.name || 'Setup Wizard'}
      completionPercentage={completionPercentage}
      onNext={next}
      onPrevious={previous}
      onSaveResume={saveAndResume}
      isLoading={isLoading}
      error={error}
      canGoPrevious={canGoPrevious}
      isLastStep={isLastStep}
    >
      {/* Render step-specific form based on current step */}
      {currentStep && renderStepForm(currentStep, data, updateData)}
    </WizardLayout>
  );
}

/**
 * Render step-specific form fields
 *
 * @param step - Current wizard step
 * @param data - Step data
 * @param updateData - Function to update step data
 */
function renderStepForm(
  step: any,
  data: Record<string, any>,
  updateData: (data: Record<string, any>) => void
) {
  const handleChange = (field: string) => (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    updateData({ [field]: e.target.value });
  };

  const inputClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
  const labelClass = 'block text-sm font-medium text-gray-700 mb-1';

  switch (step.id) {
    case 'organization_info':
      return (
        <div className="space-y-6">
          <div>
            <label htmlFor="name" className={labelClass}>
              Organization Name *
            </label>
            <input
              id="name"
              type="text"
              value={data.name || ''}
              onChange={handleChange('name')}
              className={inputClass}
              placeholder="Sunshine Daycare"
              required
            />
            <p className="mt-1 text-sm text-gray-500">
              Full legal name of your daycare
            </p>
          </div>

          <div>
            <label htmlFor="address" className={labelClass}>
              Address *
            </label>
            <textarea
              id="address"
              value={data.address || ''}
              onChange={handleChange('address')}
              className={inputClass}
              rows={3}
              placeholder="123 Main Street, City, State, ZIP"
              required
            />
          </div>

          <div>
            <label htmlFor="phone" className={labelClass}>
              Phone Number *
            </label>
            <input
              id="phone"
              type="tel"
              value={data.phone || ''}
              onChange={handleChange('phone')}
              className={inputClass}
              placeholder="+1 (555) 123-4567"
              required
            />
          </div>

          <div>
            <label htmlFor="license_number" className={labelClass}>
              License Number *
            </label>
            <input
              id="license_number"
              type="text"
              value={data.license_number || ''}
              onChange={handleChange('license_number')}
              className={inputClass}
              placeholder="DC-12345"
              required
            />
          </div>

          <div>
            <label htmlFor="email" className={labelClass}>
              Email
            </label>
            <input
              id="email"
              type="email"
              value={data.email || ''}
              onChange={handleChange('email')}
              className={inputClass}
              placeholder="info@daycare.com"
            />
          </div>

          <div>
            <label htmlFor="website" className={labelClass}>
              Website
            </label>
            <input
              id="website"
              type="url"
              value={data.website || ''}
              onChange={handleChange('website')}
              className={inputClass}
              placeholder="https://daycare.com"
            />
          </div>
        </div>
      );

    case 'admin_account':
      return (
        <div className="space-y-6">
          <p className="text-gray-600">
            Create the administrator account for managing the system.
          </p>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="first_name" className={labelClass}>
                First Name *
              </label>
              <input
                id="first_name"
                type="text"
                value={data.first_name || ''}
                onChange={handleChange('first_name')}
                className={inputClass}
                required
              />
            </div>

            <div>
              <label htmlFor="last_name" className={labelClass}>
                Last Name *
              </label>
              <input
                id="last_name"
                type="text"
                value={data.last_name || ''}
                onChange={handleChange('last_name')}
                className={inputClass}
                required
              />
            </div>
          </div>

          <div>
            <label htmlFor="email" className={labelClass}>
              Email *
            </label>
            <input
              id="email"
              type="email"
              value={data.email || ''}
              onChange={handleChange('email')}
              className={inputClass}
              required
            />
          </div>

          <div>
            <label htmlFor="username" className={labelClass}>
              Username
            </label>
            <input
              id="username"
              type="text"
              value={data.username || ''}
              onChange={handleChange('username')}
              className={inputClass}
              placeholder="Leave empty to auto-generate"
            />
          </div>

          <div>
            <label htmlFor="password" className={labelClass}>
              Password *
            </label>
            <input
              id="password"
              type="password"
              value={data.password || ''}
              onChange={handleChange('password')}
              className={inputClass}
              required
            />
            <p className="mt-1 text-sm text-gray-500">
              Minimum 8 characters, include uppercase, lowercase, number, and special
              character
            </p>
          </div>

          <div>
            <label htmlFor="password_confirm" className={labelClass}>
              Confirm Password *
            </label>
            <input
              id="password_confirm"
              type="password"
              value={data.password_confirm || ''}
              onChange={handleChange('password_confirm')}
              className={inputClass}
              required
            />
          </div>
        </div>
      );

    case 'completion':
      return (
        <div className="text-center py-8">
          <div className="text-green-500 text-6xl mb-4">🎉</div>
          <h3 className="text-2xl font-bold text-gray-900 mb-4">
            Congratulations!
          </h3>
          <p className="text-gray-600 mb-6">
            You have completed all required setup steps. Your daycare system is
            ready to use.
          </p>
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p className="text-sm text-blue-800">
              Click "Complete Wizard" to finalize your setup and start using the
              system.
            </p>
          </div>
        </div>
      );

    default:
      return (
        <div className="text-center py-8">
          <p className="text-gray-600">
            Step configuration for "{step.name}" is not yet implemented.
          </p>
        </div>
      );
  }
}
