/**
 * LAYA Teacher App - Diagnostics Service
 *
 * Collects and exports iOS real-device diagnostics for QA sessions.
 * Handles log collection, network error tracking, PII redaction,
 * and diagnostic bundle generation.
 *
 * @see docs/DIAGNOSTICS_PAYLOAD.md for payload contract
 */

import {Platform} from 'react-native';

// ============================================================================
// Types
// ============================================================================

export type LogLevel = 'debug' | 'info' | 'warning' | 'error' | 'fatal';
export type NetworkErrorType =
  | 'timeout'
  | 'network_unreachable'
  | 'ssl_error'
  | 'dns_failure'
  | 'server_error'
  | 'client_error'
  | 'unknown';
export type BatteryState = 'unplugged' | 'charging' | 'full';
export type AppEnvironment = 'development' | 'staging' | 'production';

export interface LogEntry {
  timestamp: string;
  level: LogLevel;
  tag: string;
  message: string;
  metadata?: Record<string, unknown>;
}

export interface NetworkErrorDigest {
  timestamp: string;
  request_url: string;
  request_method: string;
  status_code: number | null;
  error_type: NetworkErrorType;
  error_message: string;
  request_duration_ms: number;
  retry_count: number;
}

export interface CrashReport {
  timestamp: string;
  exception_type: string;
  exception_message: string;
  stack_trace: string[];
  thread_info: ThreadInfo[];
}

export interface ThreadInfo {
  thread_id: number;
  name: string;
  is_crashed: boolean;
}

export interface ScreenshotRef {
  id: string;
  timestamp: string;
  screen_name: string;
  file_size_bytes: number;
}

export interface AppMetadata {
  app_name: string;
  app_version: string;
  build_number: string;
  bundle_id: string;
  environment: AppEnvironment;
}

export interface DeviceMetadata {
  device_model: string;
  device_identifier: string;
  ios_version: string;
  locale: string;
  timezone: string;
  is_simulator: boolean;
  available_storage_mb: number;
  total_memory_mb: number;
  battery_level?: number;
  battery_state?: BatteryState;
}

export interface DiagnosticsPayload {
  test_run_id: string;
  app_metadata: AppMetadata;
  device_metadata: DeviceMetadata;
  timestamp_collected: string;
  logs?: LogEntry[];
  network_errors?: NetworkErrorDigest[];
  crash_reports?: CrashReport[];
  screenshots?: ScreenshotRef[];
  custom_data?: Record<string, unknown>;
}

export interface DiagnosticsUploadResponse {
  diagnostics_id: string;
  test_run_id: string;
  received_at: string;
  status: 'accepted' | 'rejected';
}

// ============================================================================
// Constants
// ============================================================================

const MAX_LOG_ENTRIES = 500;
const MAX_LOG_MESSAGE_LENGTH = 1000;
const MAX_NETWORK_ERRORS = 100;
const MAX_CRASH_REPORTS = 5;
const MAX_SCREENSHOTS = 20;
const MAX_STACK_TRACE_DEPTH = 50;
const MAX_CUSTOM_DATA_SIZE = 100 * 1024; // 100 KB
const MAX_PAYLOAD_SIZE = 5 * 1024 * 1024; // 5 MB

// ============================================================================
// PII Redaction Patterns
// ============================================================================

const REDACTION_PATTERNS: Array<{
  pattern: RegExp;
  replacement: string;
}> = [
  // Email addresses
  {pattern: /[\w.-]+@[\w.-]+\.[a-z]{2,}/gi, replacement: '[EMAIL_REDACTED]'},
  // Password fields
  {pattern: /password[:=]\s*\S+/gi, replacement: 'password=[REDACTED]'},
  // Bearer tokens
  {pattern: /Bearer\s+[\w.-]+/gi, replacement: 'Bearer [TOKEN_REDACTED]'},
  // Authorization headers
  {pattern: /Authorization[:=]\s*[\w.-]+/gi, replacement: 'Authorization=[REDACTED]'},
  // Long tokens (32+ chars)
  {pattern: /\b[A-Za-z0-9_-]{32,}\b/g, replacement: '[TOKEN_REDACTED]'},
  // Phone numbers (various formats)
  {pattern: /\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/g, replacement: '[PHONE_REDACTED]'},
  // IP addresses
  {pattern: /\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/g, replacement: '[IP_REDACTED]'},
  // FCM tokens
  {pattern: /fcm[_-]?token[:=]\s*[\w:_-]+/gi, replacement: 'fcm_token=[REDACTED]'},
  // Session IDs
  {pattern: /session[_-]?id[:=]\s*[\w-]+/gi, replacement: 'session_id=[REDACTED]'},
  // API keys
  {pattern: /api[_-]?key[:=]\s*[\w-]+/gi, replacement: 'api_key=[REDACTED]'},
];

const URL_SENSITIVE_PARAMS = [
  'token',
  'auth',
  'key',
  'password',
  'secret',
  'access_token',
  'refresh_token',
  'api_key',
  'session',
];

// ============================================================================
// Internal State
// ============================================================================

let logBuffer: LogEntry[] = [];
let networkErrorBuffer: NetworkErrorDigest[] = [];
let crashReportBuffer: CrashReport[] = [];
let screenshotBuffer: ScreenshotRef[] = [];
let customData: Record<string, unknown> = {};
let screensVisited: string[] = [];
let testRunId: string | null = null;
let isCollecting = false;

// ============================================================================
// Redaction Utilities
// ============================================================================

/**
 * Redact sensitive data from a string
 */
export function redactSensitiveData(text: string): string {
  let redacted = text;
  for (const {pattern, replacement} of REDACTION_PATTERNS) {
    redacted = redacted.replace(pattern, replacement);
  }
  return redacted;
}

/**
 * Redact sensitive query parameters from a URL
 */
export function redactUrl(url: string): string {
  try {
    const urlObj = new URL(url);

    // Redact sensitive query parameters
    for (const param of URL_SENSITIVE_PARAMS) {
      if (urlObj.searchParams.has(param)) {
        urlObj.searchParams.set(param, '[REDACTED]');
      }
    }

    // Redact numeric IDs in path segments
    const pathParts = urlObj.pathname.split('/');
    const redactedPath = pathParts
      .map(part => (/^\d+$/.test(part) ? '[ID]' : part))
      .join('/');
    urlObj.pathname = redactedPath;

    return urlObj.toString();
  } catch {
    // If URL parsing fails, apply basic redaction
    return redactSensitiveData(url);
  }
}

/**
 * Truncate string to max length
 */
function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) {
    return text;
  }
  return text.substring(0, maxLength - 3) + '...';
}

/**
 * Hash a device identifier for privacy
 */
function hashDeviceId(deviceId: string): string {
  // Simple hash for device ID - first 8 chars of base64 encoded
  // In production, use a proper SHA-256 implementation
  let hash = 0;
  for (let i = 0; i < deviceId.length; i++) {
    const char = deviceId.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash;
  }
  return Math.abs(hash).toString(16).substring(0, 8);
}

// ============================================================================
// Session Management
// ============================================================================

/**
 * Start a new diagnostics collection session
 */
export function startDiagnosticsSession(runId: string): void {
  testRunId = runId;
  isCollecting = true;
  logBuffer = [];
  networkErrorBuffer = [];
  crashReportBuffer = [];
  screenshotBuffer = [];
  customData = {};
  screensVisited = [];

  logDiagnostic('info', 'DiagnosticsService', 'Started diagnostics collection', {
    test_run_id: runId,
  });
}

/**
 * Stop diagnostics collection
 */
export function stopDiagnosticsSession(): void {
  if (isCollecting && testRunId) {
    logDiagnostic('info', 'DiagnosticsService', 'Stopped diagnostics collection', {
      test_run_id: testRunId,
      log_count: logBuffer.length,
      network_error_count: networkErrorBuffer.length,
    });
  }
  isCollecting = false;
}

/**
 * Check if diagnostics collection is active
 */
export function isDiagnosticsActive(): boolean {
  return isCollecting && testRunId !== null;
}

/**
 * Get current test run ID
 */
export function getTestRunId(): string | null {
  return testRunId;
}

// ============================================================================
// Log Collection
// ============================================================================

/**
 * Log a diagnostic entry
 */
export function logDiagnostic(
  level: LogLevel,
  tag: string,
  message: string,
  metadata?: Record<string, unknown>,
): void {
  if (!isCollecting) {
    return;
  }

  const entry: LogEntry = {
    timestamp: new Date().toISOString(),
    level,
    tag,
    message: truncate(redactSensitiveData(message), MAX_LOG_MESSAGE_LENGTH),
    metadata: metadata ? redactMetadata(metadata) : undefined,
  };

  logBuffer.push(entry);

  // Keep only most recent logs
  if (logBuffer.length > MAX_LOG_ENTRIES) {
    logBuffer = logBuffer.slice(-MAX_LOG_ENTRIES);
  }
}

/**
 * Redact metadata object
 */
function redactMetadata(metadata: Record<string, unknown>): Record<string, unknown> {
  const redacted: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(metadata)) {
    if (typeof value === 'string') {
      redacted[key] = redactSensitiveData(value);
    } else if (typeof value === 'object' && value !== null) {
      redacted[key] = redactMetadata(value as Record<string, unknown>);
    } else {
      redacted[key] = value;
    }
  }
  return redacted;
}

// ============================================================================
// Network Error Collection
// ============================================================================

/**
 * Record a network error
 */
export function recordNetworkError(
  url: string,
  method: string,
  statusCode: number | null,
  errorType: NetworkErrorType,
  errorMessage: string,
  durationMs: number,
  retryCount: number = 0,
): void {
  if (!isCollecting) {
    return;
  }

  const error: NetworkErrorDigest = {
    timestamp: new Date().toISOString(),
    request_url: redactUrl(url),
    request_method: method,
    status_code: statusCode,
    error_type: errorType,
    error_message: redactSensitiveData(errorMessage),
    request_duration_ms: durationMs,
    retry_count: retryCount,
  };

  networkErrorBuffer.push(error);

  // Keep only most recent errors
  if (networkErrorBuffer.length > MAX_NETWORK_ERRORS) {
    networkErrorBuffer = networkErrorBuffer.slice(-MAX_NETWORK_ERRORS);
  }
}

/**
 * Classify HTTP status code as error type
 */
export function classifyHttpError(statusCode: number): NetworkErrorType {
  if (statusCode >= 500) {
    return 'server_error';
  }
  if (statusCode >= 400) {
    return 'client_error';
  }
  return 'unknown';
}

// ============================================================================
// Crash Report Collection
// ============================================================================

/**
 * Record a crash or exception
 */
export function recordCrash(
  exceptionType: string,
  exceptionMessage: string,
  stackTrace: string[],
  threadInfo: ThreadInfo[] = [],
): void {
  const crash: CrashReport = {
    timestamp: new Date().toISOString(),
    exception_type: exceptionType,
    exception_message: redactSensitiveData(exceptionMessage),
    stack_trace: stackTrace
      .slice(0, MAX_STACK_TRACE_DEPTH)
      .map(frame => redactSensitiveData(frame)),
    thread_info: threadInfo,
  };

  crashReportBuffer.push(crash);

  // Keep only most recent crashes
  if (crashReportBuffer.length > MAX_CRASH_REPORTS) {
    crashReportBuffer = crashReportBuffer.slice(-MAX_CRASH_REPORTS);
  }
}

// ============================================================================
// Screenshot Reference Collection
// ============================================================================

/**
 * Add a screenshot reference
 */
export function addScreenshotRef(
  id: string,
  screenName: string,
  fileSizeBytes: number,
): void {
  if (!isCollecting) {
    return;
  }

  const ref: ScreenshotRef = {
    id,
    timestamp: new Date().toISOString(),
    screen_name: screenName,
    file_size_bytes: fileSizeBytes,
  };

  screenshotBuffer.push(ref);

  // Keep only most recent screenshots
  if (screenshotBuffer.length > MAX_SCREENSHOTS) {
    screenshotBuffer = screenshotBuffer.slice(-MAX_SCREENSHOTS);
  }
}

// ============================================================================
// Custom Data & Screen Tracking
// ============================================================================

/**
 * Set custom diagnostic data
 */
export function setCustomData(key: string, value: unknown): void {
  if (!isCollecting) {
    return;
  }
  customData[key] = value;
}

/**
 * Record a screen visit
 */
export function recordScreenVisit(screenName: string): void {
  if (!isCollecting) {
    return;
  }

  // Avoid duplicate consecutive visits
  if (screensVisited[screensVisited.length - 1] !== screenName) {
    screensVisited.push(screenName);
  }
}

// ============================================================================
// Device Metadata Collection
// ============================================================================

/**
 * Get current device metadata
 */
export function getDeviceMetadata(): DeviceMetadata {
  // Platform-specific device info
  // In production, use react-native-device-info for accurate values
  const deviceModel = Platform.OS === 'ios' ? 'iPhone' : 'Android Device';
  const osVersion = Platform.Version?.toString() || 'unknown';

  return {
    device_model: deviceModel,
    device_identifier: hashDeviceId(
      `${Platform.OS}-${Platform.Version}-device`,
    ),
    ios_version: Platform.OS === 'ios' ? osVersion : 'N/A',
    locale: 'en_US', // Would use Localization.locale in production
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    is_simulator: __DEV__ || false,
    available_storage_mb: 0, // Would use react-native-device-info
    total_memory_mb: 0, // Would use react-native-device-info
    battery_level: undefined,
    battery_state: undefined,
  };
}

/**
 * Get app metadata
 */
export function getAppMetadata(): AppMetadata {
  // In production, these would come from app.json or react-native-device-info
  return {
    app_name: 'TeacherApp',
    app_version: '1.0.0',
    build_number: '1',
    bundle_id: 'com.laya.teacherapp',
    environment: __DEV__ ? 'development' : 'production',
  };
}

// ============================================================================
// Payload Generation
// ============================================================================

/**
 * Generate the complete diagnostics payload
 */
export function generateDiagnosticsPayload(): DiagnosticsPayload | null {
  if (!testRunId) {
    return null;
  }

  const payload: DiagnosticsPayload = {
    test_run_id: testRunId,
    app_metadata: getAppMetadata(),
    device_metadata: getDeviceMetadata(),
    timestamp_collected: new Date().toISOString(),
  };

  // Add optional fields only if they have data
  if (logBuffer.length > 0) {
    payload.logs = [...logBuffer];
  }

  if (networkErrorBuffer.length > 0) {
    payload.network_errors = [...networkErrorBuffer];
  }

  if (crashReportBuffer.length > 0) {
    payload.crash_reports = [...crashReportBuffer];
  }

  if (screenshotBuffer.length > 0) {
    payload.screenshots = [...screenshotBuffer];
  }

  // Add custom data with screen visits
  const allCustomData: Record<string, unknown> = {
    ...customData,
  };

  if (screensVisited.length > 0) {
    allCustomData.screens_visited = screensVisited;
  }

  if (Object.keys(allCustomData).length > 0) {
    // Enforce custom data size limit
    const customDataString = JSON.stringify(allCustomData);
    if (customDataString.length <= MAX_CUSTOM_DATA_SIZE) {
      payload.custom_data = allCustomData;
    }
  }

  return payload;
}

/**
 * Check if payload is within size limits
 */
export function isPayloadWithinLimits(payload: DiagnosticsPayload): boolean {
  const jsonString = JSON.stringify(payload);
  return jsonString.length <= MAX_PAYLOAD_SIZE;
}

/**
 * Get current diagnostics summary (for UI display)
 */
export function getDiagnosticsSummary(): {
  isActive: boolean;
  testRunId: string | null;
  logCount: number;
  networkErrorCount: number;
  crashCount: number;
  screenshotCount: number;
  screensVisited: number;
} {
  return {
    isActive: isCollecting,
    testRunId,
    logCount: logBuffer.length,
    networkErrorCount: networkErrorBuffer.length,
    crashCount: crashReportBuffer.length,
    screenshotCount: screenshotBuffer.length,
    screensVisited: screensVisited.length,
  };
}

/**
 * Clear all diagnostic data (use after successful upload)
 */
export function clearDiagnosticsData(): void {
  logBuffer = [];
  networkErrorBuffer = [];
  crashReportBuffer = [];
  screenshotBuffer = [];
  customData = {};
  screensVisited = [];
}

// ============================================================================
// Export Service Object
// ============================================================================

export default {
  // Session Management
  startDiagnosticsSession,
  stopDiagnosticsSession,
  isDiagnosticsActive,
  getTestRunId,

  // Logging
  logDiagnostic,

  // Network Errors
  recordNetworkError,
  classifyHttpError,

  // Crash Reports
  recordCrash,

  // Screenshots
  addScreenshotRef,

  // Custom Data
  setCustomData,
  recordScreenVisit,

  // Metadata
  getDeviceMetadata,
  getAppMetadata,

  // Payload
  generateDiagnosticsPayload,
  isPayloadWithinLimits,
  getDiagnosticsSummary,
  clearDiagnosticsData,

  // Redaction
  redactSensitiveData,
  redactUrl,
};
