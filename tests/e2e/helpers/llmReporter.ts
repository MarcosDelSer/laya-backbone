/**
 * LLM Reporter Helper for Playwright E2E Tests
 *
 * Provides report aggregation, evidence linking, and markdown QA report
 * generation for LLM-guided exploratory frontend testing.
 *
 * @module llmReporter
 */

import * as fs from 'fs';
import * as path from 'path';
import type { Page } from '@playwright/test';
import type {
  NetworkCaptureSummary,
  FailedRequest,
  CapturedConsoleMessage,
  Severity,
} from './networkCapture';

// ============================================================================
// Types
// ============================================================================

/**
 * Test finding categories.
 */
export type FindingCategory =
  | 'authentication'
  | 'navigation'
  | 'validation'
  | 'ui'
  | 'performance'
  | 'security'
  | 'accessibility'
  | 'data'
  | 'error-handling'
  | 'other';

/**
 * Evidence types that can be attached to findings.
 */
export type EvidenceType = 'screenshot' | 'network' | 'console' | 'trace' | 'video';

/**
 * Evidence attachment for a finding.
 */
export interface Evidence {
  /** Type of evidence */
  type: EvidenceType;
  /** Relative path to evidence file */
  path: string;
  /** Description of the evidence */
  description: string;
  /** Timestamp when captured */
  timestamp: Date;
}

/**
 * Reproduction step for a finding.
 */
export interface ReproStep {
  /** Step number */
  step: number;
  /** Action description */
  action: string;
  /** Expected result */
  expected?: string;
  /** Actual result */
  actual?: string;
}

/**
 * Test finding from LLM-guided exploratory testing.
 */
export interface Finding {
  /** Unique finding ID */
  id: string;
  /** Finding title */
  title: string;
  /** Detailed description */
  description: string;
  /** Finding category */
  category: FindingCategory;
  /** Severity level */
  severity: Severity;
  /** Associated scenario ID */
  scenarioId?: string;
  /** Affected URL */
  url?: string;
  /** Reproduction steps */
  reproSteps: ReproStep[];
  /** Attached evidence */
  evidence: Evidence[];
  /** LLM evaluation notes */
  llmNotes?: string;
  /** Whether this is a potential security issue */
  securityRelated: boolean;
  /** Timestamp when recorded */
  timestamp: Date;
  /** Tags for categorization */
  tags: string[];
}

/**
 * Test scenario result.
 */
export interface ScenarioResult {
  /** Scenario ID */
  scenarioId: string;
  /** Scenario name */
  name: string;
  /** Whether the scenario passed */
  passed: boolean;
  /** Duration in milliseconds */
  durationMs: number;
  /** Findings from this scenario */
  findings: Finding[];
  /** Network capture summary */
  networkSummary?: NetworkCaptureSummary;
  /** Screenshot paths */
  screenshots: string[];
  /** Start timestamp */
  startTime: Date;
  /** End timestamp */
  endTime: Date;
  /** Execution notes */
  notes?: string;
}

/**
 * Full test run report.
 */
export interface TestReport {
  /** Report ID */
  id: string;
  /** Report title */
  title: string;
  /** Target application */
  targetApp: string;
  /** Base URL tested */
  baseUrl: string;
  /** Report generation timestamp */
  generatedAt: Date;
  /** Test run duration in milliseconds */
  totalDurationMs: number;
  /** Scenario results */
  scenarios: ScenarioResult[];
  /** All findings across scenarios */
  findings: Finding[];
  /** Summary statistics */
  summary: ReportSummary;
  /** Environment information */
  environment: EnvironmentInfo;
}

/**
 * Report summary statistics.
 */
export interface ReportSummary {
  /** Total scenarios run */
  totalScenarios: number;
  /** Passed scenarios */
  passedScenarios: number;
  /** Failed scenarios */
  failedScenarios: number;
  /** Total findings */
  totalFindings: number;
  /** Findings by severity */
  findingsBySeverity: Record<Severity, number>;
  /** Findings by category */
  findingsByCategory: Record<FindingCategory, number>;
  /** Critical issues count */
  criticalIssues: number;
  /** Security-related findings count */
  securityIssues: number;
}

/**
 * Environment information for the test run.
 */
export interface EnvironmentInfo {
  /** Browser name */
  browser: string;
  /** Browser version */
  browserVersion?: string;
  /** Operating system */
  os: string;
  /** Viewport size */
  viewport: { width: number; height: number };
  /** User agent string */
  userAgent?: string;
  /** Test execution date */
  executionDate: string;
}

/**
 * Reporter configuration options.
 */
export interface LlmReporterOptions {
  /** Output directory for reports and evidence */
  outputDir: string;
  /** Report file name (without extension) */
  reportName?: string;
  /** Whether to generate HTML report */
  generateHtml?: boolean;
  /** Whether to include screenshot thumbnails in report */
  includeScreenshots?: boolean;
  /** Maximum findings to include in summary */
  maxSummaryFindings?: number;
}

// ============================================================================
// LLM Reporter Class
// ============================================================================

/**
 * Reporter for LLM-guided Playwright exploratory testing.
 *
 * Aggregates findings, manages evidence, and generates markdown QA reports.
 *
 * @example
 * ```typescript
 * const reporter = new LlmReporter({
 *   outputDir: './test-results/llm-review',
 *   reportName: 'qa-review'
 * });
 *
 * await reporter.startScenario('core-001', 'First-Time Admin Login');
 * // ... run test actions ...
 * await reporter.captureScreenshot(page, 'login-page');
 * reporter.addFinding({
 *   title: 'Login button not visible',
 *   severity: 'high',
 *   // ...
 * });
 * await reporter.endScenario(true);
 *
 * await reporter.generateReport();
 * ```
 */
export class LlmReporter {
  private readonly options: Required<LlmReporterOptions>;
  private findings: Finding[] = [];
  private scenarios: ScenarioResult[] = [];
  private currentScenario?: {
    id: string;
    name: string;
    startTime: Date;
    findings: Finding[];
    screenshots: string[];
    networkSummary?: NetworkCaptureSummary;
  };
  private startTime?: Date;
  private environment?: EnvironmentInfo;
  private findingCounter = 0;

  constructor(options: LlmReporterOptions) {
    this.options = {
      reportName: 'llm-qa-report',
      generateHtml: false,
      includeScreenshots: true,
      maxSummaryFindings: 10,
      ...options,
    };

    // Ensure output directory exists
    this.ensureOutputDir();
  }

  /**
   * Ensure the output directory exists.
   */
  private ensureOutputDir(): void {
    if (!fs.existsSync(this.options.outputDir)) {
      fs.mkdirSync(this.options.outputDir, { recursive: true });
    }
  }

  /**
   * Initialize the reporter with environment info.
   */
  async initialize(page: Page): Promise<void> {
    this.startTime = new Date();

    const viewport = page.viewportSize() ?? { width: 1280, height: 720 };

    this.environment = {
      browser: 'chromium', // Playwright default
      os: process.platform,
      viewport,
      userAgent: await page.evaluate(() => navigator.userAgent),
      executionDate: new Date().toISOString(),
    };
  }

  /**
   * Start a new scenario.
   */
  startScenario(scenarioId: string, name: string): void {
    if (this.currentScenario) {
      throw new Error(`Cannot start scenario ${scenarioId}: scenario ${this.currentScenario.id} is still in progress`);
    }

    this.currentScenario = {
      id: scenarioId,
      name,
      startTime: new Date(),
      findings: [],
      screenshots: [],
    };
  }

  /**
   * End the current scenario.
   */
  endScenario(passed: boolean, notes?: string): void {
    if (!this.currentScenario) {
      throw new Error('No scenario in progress');
    }

    const endTime = new Date();
    const result: ScenarioResult = {
      scenarioId: this.currentScenario.id,
      name: this.currentScenario.name,
      passed,
      durationMs: endTime.getTime() - this.currentScenario.startTime.getTime(),
      findings: this.currentScenario.findings,
      networkSummary: this.currentScenario.networkSummary,
      screenshots: this.currentScenario.screenshots,
      startTime: this.currentScenario.startTime,
      endTime,
      notes,
    };

    this.scenarios.push(result);
    this.findings.push(...this.currentScenario.findings);
    this.currentScenario = undefined;
  }

  /**
   * Attach network capture summary to current scenario.
   */
  attachNetworkSummary(summary: NetworkCaptureSummary): void {
    if (this.currentScenario) {
      this.currentScenario.networkSummary = summary;
    }

    // Auto-create findings for critical network failures
    for (const failure of summary.failures) {
      if (failure.severity === 'critical' || failure.severity === 'high') {
        this.addFinding({
          title: `Network Failure: ${failure.method} ${this.truncateUrl(failure.url)}`,
          description: `Request failed with status ${failure.status}: ${failure.reason}`,
          category: 'error-handling',
          severity: failure.severity,
          url: failure.url,
          tags: ['network', 'api-failure'],
        });
      }
    }
  }

  /**
   * Add a finding to the current scenario.
   */
  addFinding(finding: Omit<Finding, 'id' | 'timestamp' | 'evidence' | 'reproSteps' | 'securityRelated'> & {
    evidence?: Evidence[];
    reproSteps?: ReproStep[];
    securityRelated?: boolean;
  }): Finding {
    this.findingCounter++;
    const id = `F-${String(this.findingCounter).padStart(4, '0')}`;

    const fullFinding: Finding = {
      id,
      title: finding.title,
      description: finding.description,
      category: finding.category,
      severity: finding.severity,
      scenarioId: this.currentScenario?.id,
      url: finding.url,
      reproSteps: finding.reproSteps ?? [],
      evidence: finding.evidence ?? [],
      llmNotes: finding.llmNotes,
      securityRelated: finding.securityRelated ?? this.isSecurityRelated(finding),
      timestamp: new Date(),
      tags: finding.tags ?? [],
    };

    if (this.currentScenario) {
      this.currentScenario.findings.push(fullFinding);
    } else {
      this.findings.push(fullFinding);
    }

    return fullFinding;
  }

  /**
   * Check if finding is security-related based on tags/category.
   */
  private isSecurityRelated(finding: { category: FindingCategory; tags?: string[] }): boolean {
    if (finding.category === 'security' || finding.category === 'authentication') {
      return true;
    }
    const securityTags = ['xss', 'sql-injection', 'auth', 'csrf', 'security', 'sensitive'];
    return (finding.tags ?? []).some(tag =>
      securityTags.includes(tag.toLowerCase())
    );
  }

  /**
   * Capture and save a screenshot.
   */
  async captureScreenshot(
    page: Page,
    name: string,
    description?: string
  ): Promise<Evidence> {
    const fileName = `${this.currentScenario?.id ?? 'general'}-${name}-${Date.now()}.png`;
    const filePath = path.join(this.options.outputDir, 'screenshots', fileName);

    // Ensure screenshots directory exists
    const screenshotsDir = path.join(this.options.outputDir, 'screenshots');
    if (!fs.existsSync(screenshotsDir)) {
      fs.mkdirSync(screenshotsDir, { recursive: true });
    }

    await page.screenshot({ path: filePath, fullPage: false });

    const relativePath = path.relative(this.options.outputDir, filePath);

    const evidence: Evidence = {
      type: 'screenshot',
      path: relativePath,
      description: description ?? `Screenshot: ${name}`,
      timestamp: new Date(),
    };

    if (this.currentScenario) {
      this.currentScenario.screenshots.push(relativePath);
    }

    return evidence;
  }

  /**
   * Add reproduction steps to a finding.
   */
  addReproSteps(findingId: string, steps: Omit<ReproStep, 'step'>[]): void {
    const finding = this.findFinding(findingId);
    if (finding) {
      finding.reproSteps = steps.map((step, index) => ({
        ...step,
        step: index + 1,
      }));
    }
  }

  /**
   * Add evidence to a finding.
   */
  addEvidence(findingId: string, evidence: Evidence): void {
    const finding = this.findFinding(findingId);
    if (finding) {
      finding.evidence.push(evidence);
    }
  }

  /**
   * Add LLM evaluation notes to a finding.
   */
  addLlmNotes(findingId: string, notes: string): void {
    const finding = this.findFinding(findingId);
    if (finding) {
      finding.llmNotes = notes;
    }
  }

  /**
   * Find a finding by ID.
   */
  private findFinding(findingId: string): Finding | undefined {
    // Check current scenario first
    if (this.currentScenario) {
      const finding = this.currentScenario.findings.find(f => f.id === findingId);
      if (finding) return finding;
    }
    // Then check all findings
    return this.findings.find(f => f.id === findingId);
  }

  /**
   * Get summary statistics.
   */
  private getSummary(): ReportSummary {
    const allFindings = this.findings;

    const findingsBySeverity: Record<Severity, number> = {
      critical: 0,
      high: 0,
      medium: 0,
      low: 0,
      info: 0,
    };

    const findingsByCategory: Record<FindingCategory, number> = {
      authentication: 0,
      navigation: 0,
      validation: 0,
      ui: 0,
      performance: 0,
      security: 0,
      accessibility: 0,
      data: 0,
      'error-handling': 0,
      other: 0,
    };

    for (const finding of allFindings) {
      findingsBySeverity[finding.severity]++;
      findingsByCategory[finding.category]++;
    }

    return {
      totalScenarios: this.scenarios.length,
      passedScenarios: this.scenarios.filter(s => s.passed).length,
      failedScenarios: this.scenarios.filter(s => !s.passed).length,
      totalFindings: allFindings.length,
      findingsBySeverity,
      findingsByCategory,
      criticalIssues: findingsBySeverity.critical + findingsBySeverity.high,
      securityIssues: allFindings.filter(f => f.securityRelated).length,
    };
  }

  /**
   * Generate the full test report.
   */
  async generateReport(
    title: string = 'LLM QA Review Report',
    targetApp: string = 'Application',
    baseUrl: string = ''
  ): Promise<TestReport> {
    const endTime = new Date();
    const reportId = `report-${Date.now()}`;

    const report: TestReport = {
      id: reportId,
      title,
      targetApp,
      baseUrl,
      generatedAt: endTime,
      totalDurationMs: this.startTime
        ? endTime.getTime() - this.startTime.getTime()
        : 0,
      scenarios: this.scenarios,
      findings: this.findings,
      summary: this.getSummary(),
      environment: this.environment ?? {
        browser: 'unknown',
        os: process.platform,
        viewport: { width: 1280, height: 720 },
        executionDate: new Date().toISOString(),
      },
    };

    // Save JSON report
    const jsonPath = path.join(
      this.options.outputDir,
      `${this.options.reportName}.json`
    );
    fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2));

    // Generate markdown report
    const markdownContent = this.generateMarkdownReport(report);
    const mdPath = path.join(
      this.options.outputDir,
      `${this.options.reportName}.md`
    );
    fs.writeFileSync(mdPath, markdownContent);

    return report;
  }

  /**
   * Generate markdown report content.
   */
  private generateMarkdownReport(report: TestReport): string {
    const lines: string[] = [];

    // Header
    lines.push(`# ${report.title}`);
    lines.push('');
    lines.push(`**Generated:** ${report.generatedAt.toISOString()}`);
    lines.push(`**Target:** ${report.targetApp}`);
    lines.push(`**Base URL:** ${report.baseUrl || 'N/A'}`);
    lines.push(`**Duration:** ${this.formatDuration(report.totalDurationMs)}`);
    lines.push('');

    // Executive Summary
    lines.push('## Executive Summary');
    lines.push('');
    lines.push('| Metric | Value |');
    lines.push('|--------|-------|');
    lines.push(`| Total Scenarios | ${report.summary.totalScenarios} |`);
    lines.push(`| Passed | ${report.summary.passedScenarios} |`);
    lines.push(`| Failed | ${report.summary.failedScenarios} |`);
    lines.push(`| Total Findings | ${report.summary.totalFindings} |`);
    lines.push(`| Critical/High Issues | ${report.summary.criticalIssues} |`);
    lines.push(`| Security Issues | ${report.summary.securityIssues} |`);
    lines.push('');

    // Severity breakdown
    lines.push('### Findings by Severity');
    lines.push('');
    lines.push('| Severity | Count |');
    lines.push('|----------|-------|');
    for (const [severity, count] of Object.entries(report.summary.findingsBySeverity)) {
      if (count > 0) {
        lines.push(`| ${severity.toUpperCase()} | ${count} |`);
      }
    }
    lines.push('');

    // Top Findings
    if (report.findings.length > 0) {
      lines.push('## Top Findings');
      lines.push('');

      const topFindings = this.getTopFindings(report.findings);
      for (const finding of topFindings) {
        lines.push(`### ${finding.id}: ${finding.title}`);
        lines.push('');
        lines.push(`**Severity:** ${finding.severity.toUpperCase()}`);
        lines.push(`**Category:** ${finding.category}`);
        if (finding.url) {
          lines.push(`**URL:** ${finding.url}`);
        }
        lines.push('');
        lines.push(finding.description);
        lines.push('');

        // Reproduction steps
        if (finding.reproSteps.length > 0) {
          lines.push('**Reproduction Steps:**');
          for (const step of finding.reproSteps) {
            lines.push(`${step.step}. ${step.action}`);
            if (step.expected) {
              lines.push(`   - Expected: ${step.expected}`);
            }
            if (step.actual) {
              lines.push(`   - Actual: ${step.actual}`);
            }
          }
          lines.push('');
        }

        // Evidence
        if (finding.evidence.length > 0) {
          lines.push('**Evidence:**');
          for (const evidence of finding.evidence) {
            lines.push(`- [${evidence.type}](${evidence.path}): ${evidence.description}`);
          }
          lines.push('');
        }

        // LLM notes
        if (finding.llmNotes) {
          lines.push('**LLM Analysis:**');
          lines.push(`> ${finding.llmNotes}`);
          lines.push('');
        }

        lines.push('---');
        lines.push('');
      }
    }

    // Scenario Results
    lines.push('## Scenario Results');
    lines.push('');

    for (const scenario of report.scenarios) {
      const status = scenario.passed ? 'PASS' : 'FAIL';
      const statusIcon = scenario.passed ? '[x]' : '[ ]';
      lines.push(`### ${statusIcon} ${scenario.name} (${scenario.scenarioId})`);
      lines.push('');
      lines.push(`- **Status:** ${status}`);
      lines.push(`- **Duration:** ${this.formatDuration(scenario.durationMs)}`);
      lines.push(`- **Findings:** ${scenario.findings.length}`);

      if (scenario.networkSummary) {
        const ns = scenario.networkSummary;
        lines.push(`- **Network:** ${ns.totalRequests} requests, ${ns.failedRequests} failed`);
        lines.push(`- **Console:** ${ns.consoleErrors} errors, ${ns.consoleWarnings} warnings`);
      }

      if (scenario.screenshots.length > 0) {
        lines.push(`- **Screenshots:** ${scenario.screenshots.length}`);
      }

      if (scenario.notes) {
        lines.push(`- **Notes:** ${scenario.notes}`);
      }

      lines.push('');
    }

    // Environment Info
    lines.push('## Environment');
    lines.push('');
    lines.push(`- **Browser:** ${report.environment.browser}`);
    lines.push(`- **OS:** ${report.environment.os}`);
    lines.push(`- **Viewport:** ${report.environment.viewport.width}x${report.environment.viewport.height}`);
    lines.push(`- **Executed:** ${report.environment.executionDate}`);
    lines.push('');

    // Footer
    lines.push('---');
    lines.push('');
    lines.push('*Generated by LLM QA Playwright Reporter*');

    return lines.join('\n');
  }

  /**
   * Get top findings sorted by severity.
   */
  private getTopFindings(findings: Finding[]): Finding[] {
    const severityOrder: Record<Severity, number> = {
      critical: 0,
      high: 1,
      medium: 2,
      low: 3,
      info: 4,
    };

    return [...findings]
      .sort((a, b) => severityOrder[a.severity] - severityOrder[b.severity])
      .slice(0, this.options.maxSummaryFindings);
  }

  /**
   * Format duration in human-readable format.
   */
  private formatDuration(ms: number): string {
    if (ms < 1000) {
      return `${ms}ms`;
    }
    const seconds = Math.floor(ms / 1000);
    if (seconds < 60) {
      return `${seconds}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
  }

  /**
   * Truncate URL for display.
   */
  private truncateUrl(url: string, maxLength: number = 60): string {
    if (url.length <= maxLength) {
      return url;
    }
    return url.substring(0, maxLength - 3) + '...';
  }

  /**
   * Get all findings.
   */
  getFindings(): Finding[] {
    return [...this.findings];
  }

  /**
   * Get findings by severity.
   */
  getFindingsBySeverity(severity: Severity): Finding[] {
    return this.findings.filter(f => f.severity === severity);
  }

  /**
   * Get security-related findings.
   */
  getSecurityFindings(): Finding[] {
    return this.findings.filter(f => f.securityRelated);
  }

  /**
   * Check if there are critical issues.
   */
  hasCriticalIssues(): boolean {
    return this.findings.some(f => f.severity === 'critical');
  }

  /**
   * Get the output directory path.
   */
  getOutputDir(): string {
    return this.options.outputDir;
  }
}

// ============================================================================
// Factory Function
// ============================================================================

/**
 * Create a new LLM reporter instance.
 *
 * @param options - Reporter configuration options
 * @returns LlmReporter instance
 *
 * @example
 * ```typescript
 * const reporter = createLlmReporter({
 *   outputDir: './test-results/llm-review'
 * });
 * ```
 */
export function createLlmReporter(options: LlmReporterOptions): LlmReporter {
  return new LlmReporter(options);
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Create finding from console errors.
 *
 * @param errors - Console error messages
 * @returns Finding object or undefined if no errors
 */
export function createFindingFromConsoleErrors(
  errors: CapturedConsoleMessage[]
): Omit<Finding, 'id' | 'timestamp' | 'evidence' | 'reproSteps' | 'securityRelated'> | undefined {
  if (errors.length === 0) {
    return undefined;
  }

  const errorCount = errors.filter(e => e.type === 'error').length;
  const warningCount = errors.length - errorCount;

  return {
    title: `Console Errors Detected (${errorCount} errors, ${warningCount} warnings)`,
    description: errors.map(e => `[${e.type.toUpperCase()}] ${e.text}`).join('\n'),
    category: 'error-handling',
    severity: errorCount > 0 ? 'medium' : 'low',
    tags: ['console', 'javascript'],
  };
}

/**
 * Create finding from failed network requests.
 *
 * @param failures - Failed request details
 * @returns Finding object or undefined if no failures
 */
export function createFindingFromNetworkFailures(
  failures: FailedRequest[]
): Omit<Finding, 'id' | 'timestamp' | 'evidence' | 'reproSteps' | 'securityRelated'> | undefined {
  if (failures.length === 0) {
    return undefined;
  }

  const critical = failures.filter(f => f.severity === 'critical').length;
  const high = failures.filter(f => f.severity === 'high').length;
  const severity: Severity = critical > 0 ? 'critical' : high > 0 ? 'high' : 'medium';

  return {
    title: `Network Failures Detected (${failures.length} requests)`,
    description: failures
      .map(f => `- ${f.method} ${f.url}: ${f.status} ${f.statusText}`)
      .join('\n'),
    category: 'error-handling',
    severity,
    tags: ['network', 'api'],
  };
}
