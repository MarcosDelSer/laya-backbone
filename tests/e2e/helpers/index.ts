/**
 * E2E Test Helpers for LLM-Driven Playwright Testing
 *
 * This module exports helpers for network capture, report generation,
 * and evidence collection during LLM-guided exploratory frontend testing.
 *
 * @module e2e/helpers
 */

// Network capture utilities
export {
  NetworkCapture,
  createNetworkCapture,
  waitForNetworkIdle,
  matchesApiPattern,
} from './networkCapture';

export type {
  Severity,
  CapturedRequest,
  CapturedResponse,
  CapturedConsoleMessage,
  FailedRequest,
  NetworkCaptureSummary,
} from './networkCapture';

// LLM reporter utilities
export {
  LlmReporter,
  createLlmReporter,
  createFindingFromConsoleErrors,
  createFindingFromNetworkFailures,
} from './llmReporter';

export type {
  FindingCategory,
  EvidenceType,
  Evidence,
  ReproStep,
  Finding,
  ScenarioResult,
  TestReport,
  ReportSummary,
  EnvironmentInfo,
  LlmReporterOptions,
} from './llmReporter';
