/**
 * LAYA Teacher App - Diagnostics API
 *
 * API module for uploading iOS real-device QA diagnostics bundles.
 * Handles payload validation, upload, and error handling.
 *
 * @see docs/DIAGNOSTICS_PAYLOAD.md for payload contract
 */

import {api, getSessionToken} from './client';
import type {ApiResponse} from '../types';
import type {
  DiagnosticsPayload,
  DiagnosticsUploadResponse,
} from '../services/diagnosticsService';
import {
  generateDiagnosticsPayload,
  isPayloadWithinLimits,
  getAppMetadata,
  clearDiagnosticsData,
} from '../services/diagnosticsService';

// ============================================================================
// Types
// ============================================================================

export interface DiagnosticsUploadResult {
  success: boolean;
  diagnosticsId?: string;
  error?: {
    code: string;
    message: string;
  };
}

export interface DiagnosticsStatusResponse {
  diagnostics_id: string;
  test_run_id: string;
  status: 'processing' | 'completed' | 'failed';
  created_at: string;
  processed_at?: string;
}

// ============================================================================
// API Endpoints
// ============================================================================

const DIAGNOSTICS_ENDPOINTS = {
  upload: '/api/v1/qa/diagnostics',
  status: (id: string) => `/api/v1/qa/diagnostics/${id}`,
};

// ============================================================================
// API Functions
// ============================================================================

/**
 * Upload diagnostics bundle to the backend
 *
 * This is the main export function for iOS QA diagnostics.
 * It collects the current diagnostics state and uploads it.
 */
export async function uploadDiagnostics(): Promise<DiagnosticsUploadResult> {
  // Generate the payload from current diagnostics state
  const payload = generateDiagnosticsPayload();

  if (!payload) {
    return {
      success: false,
      error: {
        code: 'NO_ACTIVE_SESSION',
        message: 'No active diagnostics session. Call startDiagnosticsSession first.',
      },
    };
  }

  return uploadDiagnosticsPayload(payload);
}

/**
 * Upload a specific diagnostics payload
 */
export async function uploadDiagnosticsPayload(
  payload: DiagnosticsPayload,
): Promise<DiagnosticsUploadResult> {
  // Validate payload size
  if (!isPayloadWithinLimits(payload)) {
    return {
      success: false,
      error: {
        code: 'PAYLOAD_TOO_LARGE',
        message: 'Diagnostics payload exceeds 5MB limit',
      },
    };
  }

  // Get app metadata for headers
  const appMetadata = getAppMetadata();

  try {
    const response = await api.post<DiagnosticsUploadResponse>(
      DIAGNOSTICS_ENDPOINTS.upload,
      payload,
      {
        'X-Test-Run-ID': payload.test_run_id,
        'X-App-Version': appMetadata.app_version,
      } as unknown as Record<string, string>,
    );

    if (response.success && response.data) {
      // Clear local diagnostics data after successful upload
      clearDiagnosticsData();

      return {
        success: true,
        diagnosticsId: response.data.diagnostics_id,
      };
    }

    return {
      success: false,
      error: {
        code: response.error?.code || 'UPLOAD_FAILED',
        message: response.error?.message || 'Failed to upload diagnostics',
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'NETWORK_ERROR',
        message: error instanceof Error ? error.message : 'Network error during upload',
      },
    };
  }
}

/**
 * Get the status of a previously uploaded diagnostics bundle
 */
export async function getDiagnosticsStatus(
  diagnosticsId: string,
): Promise<ApiResponse<DiagnosticsStatusResponse>> {
  return api.get<DiagnosticsStatusResponse>(
    DIAGNOSTICS_ENDPOINTS.status(diagnosticsId),
  );
}

/**
 * Export diagnostics on demand (manual trigger from debug menu)
 *
 * This function is designed to be called from a debug/QA menu
 * in the app for manual diagnostics export.
 */
export async function exportDiagnosticsOnDemand(
  testRunId: string,
): Promise<DiagnosticsUploadResult> {
  // Import these dynamically to avoid circular dependencies
  const {
    startDiagnosticsSession,
    stopDiagnosticsSession,
    logDiagnostic,
  } = await import('../services/diagnosticsService');

  // If there's already an active session, use it
  const existingPayload = generateDiagnosticsPayload();
  if (existingPayload) {
    logDiagnostic('info', 'DiagnosticsAPI', 'Manual diagnostics export triggered');
    return uploadDiagnosticsPayload(existingPayload);
  }

  // Otherwise, create a new session and immediately export
  startDiagnosticsSession(testRunId);
  logDiagnostic('info', 'DiagnosticsAPI', 'Created new session for manual export');

  const payload = generateDiagnosticsPayload();
  if (!payload) {
    stopDiagnosticsSession();
    return {
      success: false,
      error: {
        code: 'GENERATION_FAILED',
        message: 'Failed to generate diagnostics payload',
      },
    };
  }

  const result = await uploadDiagnosticsPayload(payload);
  stopDiagnosticsSession();
  return result;
}

// ============================================================================
// Export Module
// ============================================================================

export default {
  uploadDiagnostics,
  uploadDiagnosticsPayload,
  getDiagnosticsStatus,
  exportDiagnosticsOnDemand,
};
