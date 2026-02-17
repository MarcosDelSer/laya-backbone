/**
 * Network Capture Helper for LLM-Driven Playwright E2E Tests
 *
 * Provides normalized network request/response capture, console error
 * collection, and evidence aggregation for LLM-guided exploratory testing.
 *
 * @module networkCapture
 */

import type { Page, Request, Response, ConsoleMessage } from '@playwright/test';

// ============================================================================
// Types
// ============================================================================

/**
 * Severity levels for captured events.
 */
export type Severity = 'critical' | 'high' | 'medium' | 'low' | 'info';

/**
 * Captured network request information.
 */
export interface CapturedRequest {
  /** Unique identifier for the request */
  id: string;
  /** Request URL */
  url: string;
  /** HTTP method */
  method: string;
  /** Request headers */
  headers: Record<string, string>;
  /** POST body if present */
  postData?: string;
  /** Resource type */
  resourceType: string;
  /** Timestamp when captured */
  timestamp: Date;
}

/**
 * Captured network response information.
 */
export interface CapturedResponse {
  /** Associated request ID */
  requestId: string;
  /** Response URL */
  url: string;
  /** HTTP status code */
  status: number;
  /** Status text */
  statusText: string;
  /** Response headers */
  headers: Record<string, string>;
  /** Whether the request failed */
  failed: boolean;
  /** Failure reason if failed */
  failureReason?: string;
  /** Response timing in ms */
  timing?: number;
  /** Timestamp when captured */
  timestamp: Date;
}

/**
 * Captured console message information.
 */
export interface CapturedConsoleMessage {
  /** Message type (log, error, warning, etc.) */
  type: string;
  /** Message text */
  text: string;
  /** Source location if available */
  location?: {
    url: string;
    lineNumber: number;
    columnNumber: number;
  };
  /** Timestamp when captured */
  timestamp: Date;
  /** Assigned severity */
  severity: Severity;
}

/**
 * Failed network request summary.
 */
export interface FailedRequest {
  /** Request URL */
  url: string;
  /** HTTP method */
  method: string;
  /** HTTP status code */
  status: number;
  /** Status text */
  statusText: string;
  /** Failure reason */
  reason: string;
  /** Request timestamp */
  timestamp: Date;
  /** Assigned severity based on status */
  severity: Severity;
}

/**
 * Network capture session summary.
 */
export interface NetworkCaptureSummary {
  /** Total requests captured */
  totalRequests: number;
  /** Failed requests count */
  failedRequests: number;
  /** Console errors count */
  consoleErrors: number;
  /** Console warnings count */
  consoleWarnings: number;
  /** List of failed request details */
  failures: FailedRequest[];
  /** Start time of capture */
  startTime: Date;
  /** End time of capture */
  endTime?: Date;
  /** Duration in milliseconds */
  durationMs?: number;
}

// ============================================================================
// Network Capture Class
// ============================================================================

/**
 * Network capture utility for Playwright pages.
 *
 * Captures and normalizes network requests, responses, and console messages
 * for LLM analysis and report generation.
 *
 * @example
 * ```typescript
 * const capture = new NetworkCapture(page);
 * await capture.start();
 *
 * // Perform test actions...
 *
 * const summary = capture.getSummary();
 * const failures = capture.getFailedRequests();
 * ```
 */
export class NetworkCapture {
  private readonly page: Page;
  private requests: Map<string, CapturedRequest> = new Map();
  private responses: Map<string, CapturedResponse> = new Map();
  private consoleMessages: CapturedConsoleMessage[] = [];
  private startTime?: Date;
  private endTime?: Date;
  private isCapturing = false;

  // Filter patterns for requests to ignore
  private readonly ignorePatterns: RegExp[] = [
    /\.(png|jpg|jpeg|gif|webp|ico|svg)$/i,
    /\.(woff|woff2|ttf|eot)$/i,
    /google-analytics\.com/i,
    /googletagmanager\.com/i,
    /facebook\.com\/tr/i,
  ];

  constructor(page: Page) {
    this.page = page;
  }

  /**
   * Start capturing network activity.
   */
  async start(): Promise<void> {
    if (this.isCapturing) {
      return;
    }

    this.reset();
    this.startTime = new Date();
    this.isCapturing = true;

    // Capture requests
    this.page.on('request', this.handleRequest.bind(this));

    // Capture responses
    this.page.on('response', this.handleResponse.bind(this));

    // Capture failed requests
    this.page.on('requestfailed', this.handleRequestFailed.bind(this));

    // Capture console messages
    this.page.on('console', this.handleConsoleMessage.bind(this));
  }

  /**
   * Stop capturing network activity.
   */
  stop(): void {
    if (!this.isCapturing) {
      return;
    }

    this.endTime = new Date();
    this.isCapturing = false;

    // Note: Playwright doesn't support removing event listeners easily,
    // so we rely on the isCapturing flag to ignore events after stop.
  }

  /**
   * Reset all captured data.
   */
  reset(): void {
    this.requests.clear();
    this.responses.clear();
    this.consoleMessages = [];
    this.startTime = undefined;
    this.endTime = undefined;
  }

  /**
   * Handle captured request.
   */
  private handleRequest(request: Request): void {
    if (!this.isCapturing) return;

    const url = request.url();

    // Skip ignored patterns
    if (this.ignorePatterns.some(pattern => pattern.test(url))) {
      return;
    }

    const captured: CapturedRequest = {
      id: this.generateRequestId(),
      url,
      method: request.method(),
      headers: request.headers(),
      postData: request.postData() ?? undefined,
      resourceType: request.resourceType(),
      timestamp: new Date(),
    };

    this.requests.set(url + request.method(), captured);
  }

  /**
   * Handle captured response.
   */
  private handleResponse(response: Response): void {
    if (!this.isCapturing) return;

    const request = response.request();
    const url = request.url();
    const key = url + request.method();

    const capturedRequest = this.requests.get(key);
    if (!capturedRequest) return;

    const timing = capturedRequest.timestamp
      ? new Date().getTime() - capturedRequest.timestamp.getTime()
      : undefined;

    const captured: CapturedResponse = {
      requestId: capturedRequest.id,
      url,
      status: response.status(),
      statusText: response.statusText(),
      headers: response.headers(),
      failed: !response.ok(),
      timing,
      timestamp: new Date(),
    };

    this.responses.set(capturedRequest.id, captured);
  }

  /**
   * Handle failed request.
   */
  private handleRequestFailed(request: Request): void {
    if (!this.isCapturing) return;

    const url = request.url();
    const key = url + request.method();

    const capturedRequest = this.requests.get(key);
    if (!capturedRequest) return;

    const failure = request.failure();
    const captured: CapturedResponse = {
      requestId: capturedRequest.id,
      url,
      status: 0,
      statusText: 'Failed',
      headers: {},
      failed: true,
      failureReason: failure?.errorText ?? 'Unknown failure',
      timestamp: new Date(),
    };

    this.responses.set(capturedRequest.id, captured);
  }

  /**
   * Handle console message.
   */
  private handleConsoleMessage(message: ConsoleMessage): void {
    if (!this.isCapturing) return;

    const type = message.type();
    const location = message.location();

    const captured: CapturedConsoleMessage = {
      type,
      text: message.text(),
      location: location.url ? {
        url: location.url,
        lineNumber: location.lineNumber,
        columnNumber: location.columnNumber,
      } : undefined,
      timestamp: new Date(),
      severity: this.getConsoleSeverity(type),
    };

    this.consoleMessages.push(captured);
  }

  /**
   * Get severity for console message type.
   */
  private getConsoleSeverity(type: string): Severity {
    switch (type) {
      case 'error':
        return 'high';
      case 'warning':
        return 'medium';
      case 'assert':
        return 'high';
      default:
        return 'info';
    }
  }

  /**
   * Get severity for HTTP status code.
   */
  private getStatusSeverity(status: number): Severity {
    if (status === 0) return 'critical'; // Network failure
    if (status === 401 || status === 403) return 'critical'; // Auth failures
    if (status >= 500) return 'high'; // Server errors
    if (status >= 400) return 'medium'; // Client errors
    return 'low';
  }

  /**
   * Generate unique request ID.
   */
  private generateRequestId(): string {
    return `req-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
  }

  /**
   * Get all failed requests with normalized details.
   */
  getFailedRequests(): FailedRequest[] {
    const failures: FailedRequest[] = [];

    for (const [requestId, response] of this.responses) {
      if (response.failed || response.status >= 400) {
        const request = Array.from(this.requests.values())
          .find(r => r.id === requestId);

        if (request) {
          failures.push({
            url: response.url,
            method: request.method,
            status: response.status,
            statusText: response.statusText,
            reason: response.failureReason ?? `HTTP ${response.status}: ${response.statusText}`,
            timestamp: response.timestamp,
            severity: this.getStatusSeverity(response.status),
          });
        }
      }
    }

    return failures.sort((a, b) => {
      // Sort by severity (critical first), then by timestamp
      const severityOrder = { critical: 0, high: 1, medium: 2, low: 3, info: 4 };
      const severityDiff = severityOrder[a.severity] - severityOrder[b.severity];
      if (severityDiff !== 0) return severityDiff;
      return a.timestamp.getTime() - b.timestamp.getTime();
    });
  }

  /**
   * Get console errors and warnings.
   */
  getConsoleErrors(): CapturedConsoleMessage[] {
    return this.consoleMessages.filter(
      msg => msg.type === 'error' || msg.type === 'warning' || msg.type === 'assert'
    );
  }

  /**
   * Get all console messages.
   */
  getAllConsoleMessages(): CapturedConsoleMessage[] {
    return [...this.consoleMessages];
  }

  /**
   * Get all captured requests.
   */
  getAllRequests(): CapturedRequest[] {
    return Array.from(this.requests.values());
  }

  /**
   * Get all captured responses.
   */
  getAllResponses(): CapturedResponse[] {
    return Array.from(this.responses.values());
  }

  /**
   * Get capture session summary.
   */
  getSummary(): NetworkCaptureSummary {
    const failures = this.getFailedRequests();
    const consoleErrors = this.consoleMessages.filter(m => m.type === 'error' || m.type === 'assert');
    const consoleWarnings = this.consoleMessages.filter(m => m.type === 'warning');

    const summary: NetworkCaptureSummary = {
      totalRequests: this.requests.size,
      failedRequests: failures.length,
      consoleErrors: consoleErrors.length,
      consoleWarnings: consoleWarnings.length,
      failures,
      startTime: this.startTime ?? new Date(),
      endTime: this.endTime,
    };

    if (this.startTime && this.endTime) {
      summary.durationMs = this.endTime.getTime() - this.startTime.getTime();
    }

    return summary;
  }

  /**
   * Format failed requests as markdown for reports.
   */
  formatFailuresAsMarkdown(): string {
    const failures = this.getFailedRequests();
    if (failures.length === 0) {
      return '_No failed requests_';
    }

    const lines: string[] = [];
    for (const failure of failures) {
      lines.push(`- **[${failure.severity.toUpperCase()}]** \`${failure.method} ${failure.url}\``);
      lines.push(`  - Status: ${failure.status} ${failure.statusText}`);
      lines.push(`  - Reason: ${failure.reason}`);
      lines.push(`  - Time: ${failure.timestamp.toISOString()}`);
    }

    return lines.join('\n');
  }

  /**
   * Format console errors as markdown for reports.
   */
  formatConsoleErrorsAsMarkdown(): string {
    const errors = this.getConsoleErrors();
    if (errors.length === 0) {
      return '_No console errors or warnings_';
    }

    const lines: string[] = [];
    for (const error of errors) {
      const icon = error.type === 'error' ? 'X' : '!';
      lines.push(`- **[${icon}]** ${error.type.toUpperCase()}: ${error.text}`);
      if (error.location) {
        lines.push(`  - Source: ${error.location.url}:${error.location.lineNumber}`);
      }
    }

    return lines.join('\n');
  }
}

// ============================================================================
// Factory Function
// ============================================================================

/**
 * Create a new network capture instance for a page.
 *
 * @param page - Playwright page instance
 * @returns NetworkCapture instance
 *
 * @example
 * ```typescript
 * const capture = createNetworkCapture(page);
 * await capture.start();
 * ```
 */
export function createNetworkCapture(page: Page): NetworkCapture {
  return new NetworkCapture(page);
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Wait for network to be idle with custom timeout.
 *
 * @param page - Playwright page instance
 * @param options - Wait options
 */
export async function waitForNetworkIdle(
  page: Page,
  options: { timeout?: number; idleTime?: number } = {}
): Promise<void> {
  const { timeout = 10000, idleTime = 500 } = options;

  await page.waitForLoadState('networkidle', { timeout });

  // Additional wait for any delayed requests
  await page.waitForTimeout(idleTime);
}

/**
 * Check if a URL matches API patterns.
 *
 * @param url - URL to check
 * @param patterns - Array of patterns (string or RegExp)
 * @returns true if URL matches any pattern
 */
export function matchesApiPattern(
  url: string,
  patterns: (string | RegExp)[]
): boolean {
  return patterns.some(pattern => {
    if (typeof pattern === 'string') {
      return url.includes(pattern);
    }
    return pattern.test(url);
  });
}
