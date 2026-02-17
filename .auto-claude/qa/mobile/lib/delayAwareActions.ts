/**
 * LAYA Mobile QA - Delay-Aware Action Wrappers
 *
 * Provides retry logic and state-based waits for flaky iOS simulator interactions.
 * Wraps WebDriverIO/Appium actions with explicit wait conditions, bounded retries,
 * and iOS-specific quirk handling for LLM-driven exploratory testing.
 *
 * Configuration values are loaded from appium.ios-sim.yaml delayAwareActions section.
 */

// ============================================================================
// Types and Interfaces
// ============================================================================

/**
 * Element locator strategies supported by Appium
 */
export type LocatorStrategy =
  | 'id'
  | 'accessibilityId'
  | 'name'
  | 'className'
  | 'xpath'
  | 'css'
  | '-ios predicate string'
  | '-ios class chain';

/**
 * Element locator definition
 */
export interface ElementLocator {
  strategy: LocatorStrategy;
  selector: string;
  description?: string;
}

/**
 * Wait condition types for element states
 */
export type WaitCondition =
  | 'visible'
  | 'clickable'
  | 'present'
  | 'enabled'
  | 'selected'
  | 'stale'
  | 'textPresent'
  | 'textContains'
  | 'attributeEquals'
  | 'attributeContains';

/**
 * Configuration for wait conditions before actions
 */
export interface WaitConditionsConfig {
  beforeTap: number;
  beforeType: number;
  afterNavigation: number;
  afterAnimation: number;
}

/**
 * Configuration for explicit wait timeouts
 */
export interface ExplicitWaitsConfig {
  elementVisible: number;
  elementClickable: number;
  textPresent: number;
  alertPresent: number;
  pageReady: number;
}

/**
 * Configuration for state-based polling
 */
export interface PollingConfig {
  interval: number;
  timeout: number;
  stableIterations: number;
}

/**
 * iOS simulator specific quirks configuration
 */
export interface IOSQuirksConfig {
  tapDelay: number;
  scrollSettleTime: number;
  keyboardDelay: number;
  alertHandlingDelay: number;
}

/**
 * Retry configuration with exponential backoff
 */
export interface RetryConfig {
  maxAttempts: number;
  delayMs: number;
  exponentialBackoff: boolean;
  backoffMultiplier: number;
  maxDelayMs: number;
}

/**
 * Complete delay-aware actions configuration
 */
export interface DelayAwareConfig {
  waitConditions: WaitConditionsConfig;
  explicitWaits: ExplicitWaitsConfig;
  polling: PollingConfig;
  iosQuirks: IOSQuirksConfig;
  retry: RetryConfig;
}

/**
 * Action result with success status and optional error
 */
export interface ActionResult<T = void> {
  success: boolean;
  data?: T;
  error?: ActionError;
  attempts: number;
  duration: number;
}

/**
 * Error codes for action failures
 */
export type ActionErrorCode =
  | 'ELEMENT_NOT_FOUND'
  | 'ELEMENT_NOT_VISIBLE'
  | 'ELEMENT_NOT_CLICKABLE'
  | 'ELEMENT_STALE'
  | 'TIMEOUT'
  | 'INTERACTION_FAILED'
  | 'KEYBOARD_ERROR'
  | 'SCROLL_ERROR'
  | 'ALERT_ERROR'
  | 'STABILITY_CHECK_FAILED'
  | 'MAX_RETRIES_EXCEEDED'
  | 'UNKNOWN_ERROR';

/**
 * Structured error for action failures
 */
export interface ActionError {
  code: ActionErrorCode;
  message: string;
  locator?: ElementLocator;
  originalError?: Error;
}

/**
 * Scroll direction options
 */
export type ScrollDirection = 'up' | 'down' | 'left' | 'right';

/**
 * Screenshot evidence for failed actions
 */
export interface ActionEvidence {
  screenshot?: string;
  elementHierarchy?: string;
  timestamp: string;
  action: string;
}

/**
 * Callback type for evidence collection
 */
export type EvidenceCollector = (action: string) => Promise<ActionEvidence>;

/**
 * Abstract WebDriver element interface for type safety
 * Implementations should wrap actual WebDriverIO elements
 */
export interface WebDriverElement {
  click(): Promise<void>;
  setValue(value: string): Promise<void>;
  clearValue(): Promise<void>;
  getText(): Promise<string>;
  getAttribute(name: string): Promise<string | null>;
  isDisplayed(): Promise<boolean>;
  isEnabled(): Promise<boolean>;
  isSelected(): Promise<boolean>;
  getLocation(): Promise<{ x: number; y: number }>;
  getSize(): Promise<{ width: number; height: number }>;
}

/**
 * Abstract WebDriver browser interface for type safety
 * Implementations should wrap actual WebDriverIO browser instance
 */
export interface WebDriverBrowser {
  $(selector: string): Promise<WebDriverElement>;
  $$(selector: string): Promise<WebDriverElement[]>;
  pause(ms: number): Promise<void>;
  execute<T>(script: string | ((...args: unknown[]) => T), ...args: unknown[]): Promise<T>;
  touchAction(action: unknown): Promise<void>;
  takeScreenshot(): Promise<string>;
  getPageSource(): Promise<string>;
}

// ============================================================================
// Default Configuration
// ============================================================================

/**
 * Default configuration matching appium.ios-sim.yaml delayAwareActions section
 */
export const DEFAULT_CONFIG: DelayAwareConfig = {
  waitConditions: {
    beforeTap: 500,
    beforeType: 500,
    afterNavigation: 2000,
    afterAnimation: 1000,
  },
  explicitWaits: {
    elementVisible: 30000,
    elementClickable: 30000,
    textPresent: 20000,
    alertPresent: 15000,
    pageReady: 45000,
  },
  polling: {
    interval: 500,
    timeout: 60000,
    stableIterations: 3,
  },
  iosQuirks: {
    tapDelay: 300,
    scrollSettleTime: 800,
    keyboardDelay: 1500,
    alertHandlingDelay: 500,
  },
  retry: {
    maxAttempts: 5,
    delayMs: 2000,
    exponentialBackoff: true,
    backoffMultiplier: 2.0,
    maxDelayMs: 30000,
  },
};

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Sleep for specified milliseconds
 */
function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Calculate exponential backoff delay with jitter
 */
function calculateBackoffDelay(
  attempt: number,
  baseDelay: number,
  multiplier: number,
  maxDelay: number
): number {
  const delay = baseDelay * Math.pow(multiplier, attempt);
  // Add 10% jitter to prevent thundering herd
  const jitter = Math.random() * delay * 0.1;
  return Math.min(delay + jitter, maxDelay);
}

/**
 * Build selector string from locator
 */
function buildSelector(locator: ElementLocator): string {
  switch (locator.strategy) {
    case 'id':
      return `#${locator.selector}`;
    case 'accessibilityId':
      return `~${locator.selector}`;
    case 'className':
      return locator.selector;
    case 'xpath':
      return locator.selector;
    case '-ios predicate string':
      return `-ios predicate string:${locator.selector}`;
    case '-ios class chain':
      return `-ios class chain:${locator.selector}`;
    case 'css':
      return locator.selector;
    case 'name':
      return `[name="${locator.selector}"]`;
    default:
      return locator.selector;
  }
}

/**
 * Create an ActionError from various error types
 */
function createActionError(
  code: ActionErrorCode,
  message: string,
  locator?: ElementLocator,
  originalError?: unknown
): ActionError {
  return {
    code,
    message,
    locator,
    originalError: originalError instanceof Error ? originalError : undefined,
  };
}

// ============================================================================
// Main Class: DelayAwareActions
// ============================================================================

/**
 * Delay-aware action wrapper for flaky iOS simulator interactions
 *
 * Provides:
 * - Explicit wait conditions before actions
 * - Bounded retry logic with exponential backoff
 * - State-based polling for element stability
 * - iOS quirk handling (tap delays, keyboard timing, etc.)
 * - Evidence collection for failures
 *
 * @example
 * ```typescript
 * const actions = new DelayAwareActions(browser);
 *
 * // Tap with automatic retry and wait
 * const result = await actions.tap({ strategy: 'accessibilityId', selector: 'loginButton' });
 *
 * // Type text with keyboard delay handling
 * await actions.typeText({ strategy: 'accessibilityId', selector: 'emailField' }, 'user@example.com');
 *
 * // Wait for element with custom timeout
 * await actions.waitForElement(
 *   { strategy: 'xpath', selector: '//XCUIElementTypeStaticText[@name="Welcome"]' },
 *   { condition: 'visible', timeout: 10000 }
 * );
 * ```
 */
export class DelayAwareActions {
  private readonly browser: WebDriverBrowser;
  private readonly config: DelayAwareConfig;
  private readonly evidenceCollector?: EvidenceCollector;

  constructor(
    browser: WebDriverBrowser,
    config: Partial<DelayAwareConfig> = {},
    evidenceCollector?: EvidenceCollector
  ) {
    this.browser = browser;
    this.config = this.mergeConfig(DEFAULT_CONFIG, config);
    this.evidenceCollector = evidenceCollector;
  }

  /**
   * Deep merge configuration with defaults
   */
  private mergeConfig(
    defaults: DelayAwareConfig,
    overrides: Partial<DelayAwareConfig>
  ): DelayAwareConfig {
    return {
      waitConditions: { ...defaults.waitConditions, ...overrides.waitConditions },
      explicitWaits: { ...defaults.explicitWaits, ...overrides.explicitWaits },
      polling: { ...defaults.polling, ...overrides.polling },
      iosQuirks: { ...defaults.iosQuirks, ...overrides.iosQuirks },
      retry: { ...defaults.retry, ...overrides.retry },
    };
  }

  // ==========================================================================
  // Core Wait Methods
  // ==========================================================================

  /**
   * Wait for an element to match a specific condition
   *
   * Uses polling with configurable interval and stability checks.
   * Element must remain in desired state for stableIterations to pass.
   */
  async waitForElement(
    locator: ElementLocator,
    options: {
      condition?: WaitCondition;
      timeout?: number;
      expectedText?: string;
      expectedAttribute?: { name: string; value: string };
    } = {}
  ): Promise<ActionResult<WebDriverElement>> {
    const startTime = Date.now();
    const {
      condition = 'visible',
      timeout = this.config.explicitWaits.elementVisible,
      expectedText,
      expectedAttribute,
    } = options;

    const selector = buildSelector(locator);
    let stableCount = 0;
    let lastElement: WebDriverElement | null = null;
    let attempts = 0;

    while (Date.now() - startTime < timeout) {
      attempts++;

      try {
        const element = await this.browser.$(selector);
        const conditionMet = await this.checkElementCondition(
          element,
          condition,
          expectedText,
          expectedAttribute
        );

        if (conditionMet) {
          stableCount++;
          lastElement = element;

          // Element must be stable for configured iterations
          if (stableCount >= this.config.polling.stableIterations) {
            return {
              success: true,
              data: element,
              attempts,
              duration: Date.now() - startTime,
            };
          }
        } else {
          stableCount = 0;
        }
      } catch {
        stableCount = 0;
      }

      await sleep(this.config.polling.interval);
    }

    // Timeout - collect evidence
    if (this.evidenceCollector) {
      await this.evidenceCollector(`waitForElement:${condition}:${locator.selector}`);
    }

    return {
      success: false,
      error: createActionError(
        'TIMEOUT',
        `Element did not meet condition '${condition}' within ${timeout}ms`,
        locator
      ),
      attempts,
      duration: Date.now() - startTime,
    };
  }

  /**
   * Check if element meets the specified condition
   */
  private async checkElementCondition(
    element: WebDriverElement,
    condition: WaitCondition,
    expectedText?: string,
    expectedAttribute?: { name: string; value: string }
  ): Promise<boolean> {
    try {
      switch (condition) {
        case 'visible':
          return await element.isDisplayed();

        case 'clickable':
          return (await element.isDisplayed()) && (await element.isEnabled());

        case 'present':
          return true; // Element was found

        case 'enabled':
          return await element.isEnabled();

        case 'selected':
          return await element.isSelected();

        case 'textPresent':
          if (!expectedText) return false;
          const text = await element.getText();
          return text === expectedText;

        case 'textContains':
          if (!expectedText) return false;
          const elementText = await element.getText();
          return elementText.includes(expectedText);

        case 'attributeEquals':
          if (!expectedAttribute) return false;
          const attrValue = await element.getAttribute(expectedAttribute.name);
          return attrValue === expectedAttribute.value;

        case 'attributeContains':
          if (!expectedAttribute) return false;
          const attr = await element.getAttribute(expectedAttribute.name);
          return attr ? attr.includes(expectedAttribute.value) : false;

        case 'stale':
          try {
            await element.isDisplayed();
            return false; // Element is still valid
          } catch {
            return true; // Element is stale
          }

        default:
          return false;
      }
    } catch {
      return false;
    }
  }

  /**
   * Wait for page to be fully loaded/stable
   *
   * Uses multiple checks to determine page readiness:
   * - No pending animations
   * - UI hierarchy stable
   * - Network idle (if applicable)
   */
  async waitForPageReady(timeout?: number): Promise<ActionResult> {
    const startTime = Date.now();
    const maxTimeout = timeout ?? this.config.explicitWaits.pageReady;
    let stableCount = 0;
    let lastSourceHash = '';
    let attempts = 0;

    while (Date.now() - startTime < maxTimeout) {
      attempts++;

      try {
        // Get page source and compute simple hash
        const source = await this.browser.getPageSource();
        const currentHash = this.simpleHash(source);

        if (currentHash === lastSourceHash) {
          stableCount++;
          if (stableCount >= this.config.polling.stableIterations) {
            // Page is stable, wait for any remaining animations
            await sleep(this.config.waitConditions.afterAnimation);
            return {
              success: true,
              attempts,
              duration: Date.now() - startTime,
            };
          }
        } else {
          stableCount = 0;
          lastSourceHash = currentHash;
        }
      } catch {
        stableCount = 0;
      }

      await sleep(this.config.polling.interval);
    }

    return {
      success: false,
      error: createActionError('TIMEOUT', `Page did not stabilize within ${maxTimeout}ms`),
      attempts,
      duration: Date.now() - startTime,
    };
  }

  /**
   * Simple string hash for page source comparison
   */
  private simpleHash(str: string): string {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return hash.toString(16);
  }

  // ==========================================================================
  // Action Methods with Retry Logic
  // ==========================================================================

  /**
   * Tap on an element with delay-aware retry logic
   *
   * Handles iOS simulator tap flakiness by:
   * - Waiting for element to be clickable
   * - Adding pre-tap delay
   * - Retrying with exponential backoff
   * - Adding post-tap delay for consecutive taps
   */
  async tap(
    locator: ElementLocator,
    options: { waitForClickable?: boolean; retries?: number } = {}
  ): Promise<ActionResult> {
    const { waitForClickable = true, retries = this.config.retry.maxAttempts } = options;

    return this.withRetry(
      async () => {
        // Wait for element to be clickable if requested
        if (waitForClickable) {
          const waitResult = await this.waitForElement(locator, {
            condition: 'clickable',
            timeout: this.config.explicitWaits.elementClickable,
          });

          if (!waitResult.success) {
            throw new Error(waitResult.error?.message ?? 'Element not clickable');
          }
        }

        // Pre-tap delay for iOS simulator
        await sleep(this.config.waitConditions.beforeTap);

        // Find and tap element
        const selector = buildSelector(locator);
        const element = await this.browser.$(selector);
        await element.click();

        // Post-tap delay for iOS quirks (consecutive taps)
        await sleep(this.config.iosQuirks.tapDelay);
      },
      {
        maxAttempts: retries,
        errorCode: 'INTERACTION_FAILED',
        actionName: `tap:${locator.selector}`,
        locator,
      }
    );
  }

  /**
   * Type text into an element with keyboard delay handling
   *
   * Handles iOS simulator keyboard issues by:
   * - Waiting for keyboard to appear
   * - Clearing existing text if requested
   * - Using slower typing frequency
   * - Waiting for keyboard to settle
   */
  async typeText(
    locator: ElementLocator,
    text: string,
    options: { clearFirst?: boolean; hideKeyboardAfter?: boolean; retries?: number } = {}
  ): Promise<ActionResult> {
    const {
      clearFirst = true,
      hideKeyboardAfter = false,
      retries = this.config.retry.maxAttempts,
    } = options;

    return this.withRetry(
      async () => {
        // Wait for element to be visible and enabled
        const waitResult = await this.waitForElement(locator, {
          condition: 'clickable',
          timeout: this.config.explicitWaits.elementClickable,
        });

        if (!waitResult.success || !waitResult.data) {
          throw new Error(waitResult.error?.message ?? 'Element not ready for input');
        }

        // Pre-type delay
        await sleep(this.config.waitConditions.beforeType);

        const element = waitResult.data;

        // Tap to focus and trigger keyboard
        await element.click();

        // Wait for keyboard to appear
        await sleep(this.config.iosQuirks.keyboardDelay);

        // Clear existing text if requested
        if (clearFirst) {
          await element.clearValue();
          await sleep(300); // Brief pause after clear
        }

        // Type text
        await element.setValue(text);

        // Wait for text entry to settle
        await sleep(500);

        // Optionally hide keyboard
        if (hideKeyboardAfter) {
          await this.hideKeyboard();
        }
      },
      {
        maxAttempts: retries,
        errorCode: 'KEYBOARD_ERROR',
        actionName: `typeText:${locator.selector}`,
        locator,
      }
    );
  }

  /**
   * Scroll in a direction until element is found or max scrolls reached
   *
   * Handles iOS simulator scroll settling by adding delays after each scroll.
   */
  async scrollToElement(
    locator: ElementLocator,
    options: {
      direction?: ScrollDirection;
      maxScrolls?: number;
      scrollDistance?: number;
      retries?: number;
    } = {}
  ): Promise<ActionResult> {
    const {
      direction = 'down',
      maxScrolls = 5,
      scrollDistance = 300,
      retries = this.config.retry.maxAttempts,
    } = options;

    return this.withRetry(
      async () => {
        const selector = buildSelector(locator);

        for (let i = 0; i < maxScrolls; i++) {
          // Check if element is already visible
          try {
            const element = await this.browser.$(selector);
            if (await element.isDisplayed()) {
              return; // Element found
            }
          } catch {
            // Element not found, continue scrolling
          }

          // Perform scroll
          await this.performScroll(direction, scrollDistance);

          // Wait for scroll to settle (iOS quirk)
          await sleep(this.config.iosQuirks.scrollSettleTime);
        }

        // Element not found after max scrolls
        throw new Error(`Element not found after ${maxScrolls} scrolls`);
      },
      {
        maxAttempts: retries,
        errorCode: 'SCROLL_ERROR',
        actionName: `scrollToElement:${locator.selector}`,
        locator,
      }
    );
  }

  /**
   * Perform a scroll action
   */
  private async performScroll(direction: ScrollDirection, distance: number): Promise<void> {
    // Calculate scroll coordinates based on direction
    const centerX = 200;
    const centerY = 400;

    let startX = centerX;
    let startY = centerY;
    let endX = centerX;
    let endY = centerY;

    switch (direction) {
      case 'down':
        startY = centerY + distance / 2;
        endY = centerY - distance / 2;
        break;
      case 'up':
        startY = centerY - distance / 2;
        endY = centerY + distance / 2;
        break;
      case 'left':
        startX = centerX + distance / 2;
        endX = centerX - distance / 2;
        break;
      case 'right':
        startX = centerX - distance / 2;
        endX = centerX + distance / 2;
        break;
    }

    // Use touch action for scroll
    await this.browser.touchAction([
      { action: 'press', x: startX, y: startY },
      { action: 'wait', ms: 100 },
      { action: 'moveTo', x: endX, y: endY },
      { action: 'release' },
    ]);
  }

  /**
   * Handle an alert dialog with delay-aware waiting
   */
  async handleAlert(
    action: 'accept' | 'dismiss',
    options: { waitTimeout?: number; retries?: number } = {}
  ): Promise<ActionResult> {
    const {
      waitTimeout = this.config.explicitWaits.alertPresent,
      retries = this.config.retry.maxAttempts,
    } = options;

    return this.withRetry(
      async () => {
        // Wait for alert handling delay
        await sleep(this.config.iosQuirks.alertHandlingDelay);

        // Alert handling depends on the specific Appium/WebDriverIO version
        // This is a simplified implementation
        const alertButtonSelector =
          action === 'accept'
            ? '~Accept' // Common accessibility IDs for iOS alerts
            : '~Cancel';

        const element = await this.browser.$(alertButtonSelector);
        await element.click();

        await sleep(this.config.iosQuirks.alertHandlingDelay);
      },
      {
        maxAttempts: retries,
        errorCode: 'ALERT_ERROR',
        actionName: `handleAlert:${action}`,
      }
    );
  }

  /**
   * Hide the keyboard if visible
   */
  async hideKeyboard(): Promise<ActionResult> {
    const startTime = Date.now();

    try {
      // Common methods to hide keyboard on iOS
      // Try tapping "Done" or "Return" key first
      const doneSelectors = ['~Done', '~Return', '~done', '~return'];

      for (const selector of doneSelectors) {
        try {
          const element = await this.browser.$(selector);
          if (await element.isDisplayed()) {
            await element.click();
            await sleep(this.config.iosQuirks.keyboardDelay);
            return {
              success: true,
              attempts: 1,
              duration: Date.now() - startTime,
            };
          }
        } catch {
          // Try next selector
        }
      }

      // Tap outside input area as fallback
      await this.browser.touchAction([
        { action: 'tap', x: 200, y: 100 },
      ]);
      await sleep(this.config.iosQuirks.keyboardDelay);

      return {
        success: true,
        attempts: 1,
        duration: Date.now() - startTime,
      };
    } catch (error) {
      return {
        success: false,
        error: createActionError('KEYBOARD_ERROR', 'Failed to hide keyboard', undefined, error),
        attempts: 1,
        duration: Date.now() - startTime,
      };
    }
  }

  /**
   * Get text from an element with retry
   */
  async getText(
    locator: ElementLocator,
    options: { retries?: number } = {}
  ): Promise<ActionResult<string>> {
    const { retries = this.config.retry.maxAttempts } = options;

    return this.withRetry(
      async () => {
        const waitResult = await this.waitForElement(locator, { condition: 'visible' });

        if (!waitResult.success || !waitResult.data) {
          throw new Error(waitResult.error?.message ?? 'Element not visible');
        }

        return await waitResult.data.getText();
      },
      {
        maxAttempts: retries,
        errorCode: 'ELEMENT_NOT_FOUND',
        actionName: `getText:${locator.selector}`,
        locator,
      }
    );
  }

  /**
   * Check if element is displayed with retry
   */
  async isDisplayed(
    locator: ElementLocator,
    options: { timeout?: number } = {}
  ): Promise<ActionResult<boolean>> {
    const startTime = Date.now();
    const { timeout = this.config.explicitWaits.elementVisible } = options;

    try {
      const selector = buildSelector(locator);
      const endTime = Date.now() + timeout;

      while (Date.now() < endTime) {
        try {
          const element = await this.browser.$(selector);
          const displayed = await element.isDisplayed();
          return {
            success: true,
            data: displayed,
            attempts: 1,
            duration: Date.now() - startTime,
          };
        } catch {
          await sleep(this.config.polling.interval);
        }
      }

      return {
        success: true,
        data: false,
        attempts: 1,
        duration: Date.now() - startTime,
      };
    } catch (error) {
      return {
        success: false,
        data: false,
        error: createActionError(
          'ELEMENT_NOT_FOUND',
          'Failed to check element visibility',
          locator,
          error
        ),
        attempts: 1,
        duration: Date.now() - startTime,
      };
    }
  }

  /**
   * Wait after navigation completes
   */
  async waitAfterNavigation(): Promise<void> {
    await sleep(this.config.waitConditions.afterNavigation);
    await this.waitForPageReady();
  }

  // ==========================================================================
  // Retry Logic
  // ==========================================================================

  /**
   * Execute an action with retry logic and exponential backoff
   */
  private async withRetry<T>(
    action: () => Promise<T>,
    options: {
      maxAttempts: number;
      errorCode: ActionErrorCode;
      actionName: string;
      locator?: ElementLocator;
    }
  ): Promise<ActionResult<T>> {
    const startTime = Date.now();
    const { maxAttempts, errorCode, actionName, locator } = options;

    let lastError: Error | undefined;

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      try {
        const result = await action();
        return {
          success: true,
          data: result,
          attempts: attempt + 1,
          duration: Date.now() - startTime,
        };
      } catch (error) {
        lastError = error instanceof Error ? error : new Error(String(error));

        // Don't retry on the last attempt
        if (attempt < maxAttempts - 1) {
          const delay = this.config.retry.exponentialBackoff
            ? calculateBackoffDelay(
                attempt,
                this.config.retry.delayMs,
                this.config.retry.backoffMultiplier,
                this.config.retry.maxDelayMs
              )
            : this.config.retry.delayMs;

          await sleep(delay);
        }
      }
    }

    // All retries failed - collect evidence
    if (this.evidenceCollector) {
      await this.evidenceCollector(actionName);
    }

    return {
      success: false,
      error: createActionError(
        errorCode,
        `Action failed after ${maxAttempts} attempts: ${lastError?.message ?? 'Unknown error'}`,
        locator,
        lastError
      ),
      attempts: maxAttempts,
      duration: Date.now() - startTime,
    };
  }

  // ==========================================================================
  // Evidence Collection
  // ==========================================================================

  /**
   * Capture screenshot for evidence
   */
  async captureScreenshot(): Promise<string | null> {
    try {
      return await this.browser.takeScreenshot();
    } catch {
      return null;
    }
  }

  /**
   * Capture element hierarchy for debugging
   */
  async captureElementHierarchy(): Promise<string | null> {
    try {
      return await this.browser.getPageSource();
    } catch {
      return null;
    }
  }

  /**
   * Create evidence object for failed actions
   */
  async createEvidence(action: string): Promise<ActionEvidence> {
    return {
      screenshot: (await this.captureScreenshot()) ?? undefined,
      elementHierarchy: (await this.captureElementHierarchy()) ?? undefined,
      timestamp: new Date().toISOString(),
      action,
    };
  }

  // ==========================================================================
  // Configuration Access
  // ==========================================================================

  /**
   * Get current configuration
   */
  getConfig(): Readonly<DelayAwareConfig> {
    return { ...this.config };
  }

  /**
   * Update configuration (creates new instance internally)
   */
  withConfig(overrides: Partial<DelayAwareConfig>): DelayAwareActions {
    return new DelayAwareActions(this.browser, { ...this.config, ...overrides }, this.evidenceCollector);
  }
}

// ============================================================================
// Factory Functions
// ============================================================================

/**
 * Create a DelayAwareActions instance with default iOS simulator config
 */
export function createIOSSimulatorActions(
  browser: WebDriverBrowser,
  evidenceCollector?: EvidenceCollector
): DelayAwareActions {
  return new DelayAwareActions(browser, DEFAULT_CONFIG, evidenceCollector);
}

/**
 * Create a DelayAwareActions instance with custom config
 */
export function createDelayAwareActions(
  browser: WebDriverBrowser,
  config: Partial<DelayAwareConfig>,
  evidenceCollector?: EvidenceCollector
): DelayAwareActions {
  return new DelayAwareActions(browser, config, evidenceCollector);
}

// ============================================================================
// Exports
// ============================================================================

export default DelayAwareActions;
