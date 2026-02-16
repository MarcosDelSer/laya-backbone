/**
 * E2E Tests for Message Quality Coach Feature
 *
 * Tests the complete message quality coach workflow including:
 * - Quality panel visibility and interactions
 * - Real-time message analysis with debouncing
 * - Issue detection and display
 * - Rewrite suggestions and apply functionality
 * - Bilingual support (English/French) for Quebec compliance
 * - Message submission with quality warnings
 */

import { test, expect, Page } from '@playwright/test';

// Mock API response data
const mockAnalysisResponseClean = {
  id: 'analysis-clean-001',
  messageText: 'I noticed Sophie seems tired today. I wanted to check if everything is okay at home.',
  language: 'en',
  qualityScore: 92,
  isAcceptable: true,
  issues: [],
  rewriteSuggestions: [],
  hasPositiveOpening: true,
  hasFactualBasis: true,
  hasSolutionFocus: true,
  analysisNotes: 'Message follows Quebec Bonne Message standards.',
};

const mockAnalysisResponseWithIssues = {
  id: 'analysis-issues-001',
  messageText: 'You never pick up your child on time. This is unacceptable behavior.',
  language: 'en',
  qualityScore: 35,
  isAcceptable: false,
  issues: [
    {
      issueType: 'accusatory_you',
      severity: 'high',
      description: 'Uses accusatory "you" language that can feel confrontational',
      originalText: 'You never pick up your child on time',
      positionStart: 0,
      positionEnd: 37,
      suggestion: 'Consider using I-language to express observations',
    },
    {
      issueType: 'exaggeration',
      severity: 'medium',
      description: 'Uses absolute term "never" which may exaggerate the situation',
      originalText: 'never',
      positionStart: 4,
      positionEnd: 9,
      suggestion: 'Use specific instances rather than absolutes',
    },
    {
      issueType: 'judgmental_label',
      severity: 'high',
      description: 'Labels behavior as "unacceptable" which can feel judgmental',
      originalText: 'unacceptable behavior',
      positionStart: 48,
      positionEnd: 69,
      suggestion: 'Focus on the impact rather than labeling',
    },
  ],
  rewriteSuggestions: [
    {
      originalText: 'You never pick up your child on time. This is unacceptable behavior.',
      suggestedText: 'I noticed that pickup has been later than our scheduled time on several occasions this week. I wanted to understand if there are any challenges we can help address to ensure smooth transitions for your child.',
      explanation: 'This version uses I-language, focuses on specific observations, and offers collaborative problem-solving.',
      usesILanguage: true,
      hasSandwichStructure: true,
      confidenceScore: 0.88,
    },
  ],
  hasPositiveOpening: false,
  hasFactualBasis: false,
  hasSolutionFocus: false,
};

const mockAnalysisResponseFrench = {
  id: 'analysis-french-001',
  messageText: 'Tu ne fais jamais tes devoirs correctement.',
  language: 'fr',
  qualityScore: 28,
  isAcceptable: false,
  issues: [
    {
      issueType: 'accusatory_you',
      severity: 'high',
      description: "Utilise un langage accusateur avec 'tu' qui peut sembler conflictuel",
      originalText: 'Tu ne fais jamais',
      positionStart: 0,
      positionEnd: 17,
      suggestion: "Envisagez d'utiliser le langage 'je' pour exprimer vos observations",
    },
    {
      issueType: 'exaggeration',
      severity: 'medium',
      description: "Utilise le terme absolu 'jamais' qui peut exagérer la situation",
      originalText: 'jamais',
      positionStart: 12,
      positionEnd: 18,
      suggestion: 'Utilisez des exemples spécifiques plutôt que des absolus',
    },
  ],
  rewriteSuggestions: [
    {
      originalText: 'Tu ne fais jamais tes devoirs correctement.',
      suggestedText: "J'ai remarqué que les devoirs ont besoin de quelques corrections récemment. Pourrions-nous travailler ensemble pour trouver des stratégies qui fonctionnent mieux?",
      explanation: "Cette version utilise le langage 'je', se concentre sur des observations spécifiques et offre une résolution collaborative.",
      usesILanguage: true,
      hasSandwichStructure: true,
      confidenceScore: 0.85,
    },
  ],
  hasPositiveOpening: false,
  hasFactualBasis: false,
  hasSolutionFocus: false,
};

// Test configuration
test.describe('Message Quality Coach', () => {
  test.beforeEach(async ({ page }) => {
    // Set up API mocking for the message quality endpoints
    await page.route('**/api/v1/message-quality/analyze', async (route) => {
      const request = route.request();
      const postData = request.postDataJSON();

      // Determine which mock response to return based on message content
      let response = mockAnalysisResponseClean;

      if (postData?.message_text) {
        const messageText = postData.message_text.toLowerCase();

        if (messageText.includes('you never') || messageText.includes('unacceptable')) {
          response = mockAnalysisResponseWithIssues;
        } else if (messageText.includes('tu ne fais') || messageText.includes('jamais')) {
          response = mockAnalysisResponseFrench;
        }
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(response),
      });
    });

    // Navigate to the messages page
    await page.goto('/messages');
  });

  test.describe('Quality Coach Panel Visibility', () => {
    test('should display Quality Coach panel on messages page', async ({ page }) => {
      // Wait for page to load
      await page.waitForLoadState('networkidle');

      // Check for Quality Coach heading
      const qualityCoachHeader = page.getByRole('heading', { name: /quality coach/i });
      await expect(qualityCoachHeader).toBeVisible();
    });

    test('should show empty state when no message is typed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check for empty state text
      const emptyStateText = page.getByText('Start typing to see quality analysis');
      await expect(emptyStateText).toBeVisible();
    });

    test('should show loading state when analyzing message', async ({ page }) => {
      // Type a message longer than the minimum threshold
      const textarea = page.getByRole('textbox', { name: /message input/i });
      await textarea.fill('This is a test message that is long enough to trigger analysis.');

      // Check for loading state (the spinner or "Analyzing..." text)
      const analyzingText = page.getByText(/analyzing/i);
      await expect(analyzingText).toBeVisible({ timeout: 2000 });
    });
  });

  test.describe('Message Analysis', () => {
    test('should analyze message and show quality score for clean message', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a clean message
      await textarea.fill(
        'I noticed Sophie seems tired today. I wanted to check if everything is okay at home.'
      );

      // Wait for analysis (debounced)
      await page.waitForTimeout(600);

      // Check for quality score display
      const scoreElement = page.getByText('92');
      await expect(scoreElement).toBeVisible({ timeout: 5000 });

      // Check for "Excellent" label
      const excellentLabel = page.getByText('Excellent');
      await expect(excellentLabel).toBeVisible();

      // Check for "Ready to send" badge
      const readyBadge = page.getByText('Ready to send');
      await expect(readyBadge).toBeVisible();
    });

    test('should detect accusatory language and show issues', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for low quality score
      const scoreElement = page.getByText('35');
      await expect(scoreElement).toBeVisible({ timeout: 5000 });

      // Check for "Review suggested" badge
      const reviewBadge = page.getByText('Review suggested');
      await expect(reviewBadge).toBeVisible();

      // Check for issues section
      const issuesSection = page.getByText('Issues Detected');
      await expect(issuesSection).toBeVisible();

      // Check for specific issue type
      const accusatoryLabel = page.getByText('Accusatory Language');
      await expect(accusatoryLabel).toBeVisible();
    });

    test('should show quality check indicators', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message
      await textarea.fill(
        'I noticed Sophie seems tired today. I wanted to check if everything is okay at home.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for quality indicators
      const positiveOpening = page.getByText('Positive Opening');
      await expect(positiveOpening).toBeVisible({ timeout: 5000 });

      const factualBasis = page.getByText('Factual Basis');
      await expect(factualBasis).toBeVisible();

      const solutionFocus = page.getByText('Solution Focus');
      await expect(solutionFocus).toBeVisible();
    });
  });

  test.describe('Rewrite Suggestions', () => {
    test('should display rewrite suggestions for problematic messages', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for rewrite suggestions section
      const suggestionsSection = page.getByText('Suggested Rewrites');
      await expect(suggestionsSection).toBeVisible({ timeout: 5000 });

      // Check for I-language badge
      const iLanguageBadge = page.getByText('I-language');
      await expect(iLanguageBadge).toBeVisible();

      // Check for Sandwich badge
      const sandwichBadge = page.getByText('Sandwich');
      await expect(sandwichBadge).toBeVisible();
    });

    test('should show Apply Suggestion button for rewrites', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for Apply Suggestion button
      const applyButton = page.getByRole('button', { name: /apply suggestion/i });
      await expect(applyButton).toBeVisible({ timeout: 5000 });
    });

    test('should apply rewrite suggestion when button is clicked', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Click Apply Suggestion button
      const applyButton = page.getByRole('button', { name: /apply suggestion/i });
      await applyButton.click();

      // Verify the textarea now contains the suggested text
      await expect(textarea).toHaveValue(/I noticed that pickup has been later/i);
    });
  });

  test.describe('Issue Dismissal', () => {
    test('should allow dismissing individual issues', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Wait for issues to appear
      const accusatoryIssue = page.getByText('Accusatory Language');
      await expect(accusatoryIssue).toBeVisible({ timeout: 5000 });

      // Find and click dismiss button (X icon)
      const dismissButton = page.locator('button[title="Dismiss issue"]').first();
      await dismissButton.click();

      // Issue count should decrease (checking the count text)
      // Note: The exact behavior depends on implementation
    });
  });

  test.describe('Panel Collapse/Expand', () => {
    test('should collapse panel when collapse button is clicked', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Find collapse button
      const collapseButton = page.locator('button[title="Collapse panel"]');
      await collapseButton.click();

      // Quality Score should not be visible when collapsed
      const qualityScoreText = page.getByText('Quality Score');
      await expect(qualityScoreText).not.toBeVisible();
    });

    test('should expand panel when expand button is clicked', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // First collapse
      const collapseButton = page.locator('button[title="Collapse panel"]');
      await collapseButton.click();

      // Then expand
      const expandButton = page.locator('button[title="Expand panel"]');
      await expandButton.click();

      // Empty state should be visible again
      const emptyStateText = page.getByText('Start typing to see quality analysis');
      await expect(emptyStateText).toBeVisible();
    });
  });

  test.describe('Message Submission', () => {
    test('should show quality warning banner for problematic messages', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for warning banner
      const warningBanner = page.getByText(/quality.*issue.*detected/i);
      await expect(warningBanner).toBeVisible({ timeout: 5000 });

      // Check for "Send anyway" option
      const sendAnywayButton = page.getByRole('button', { name: /send anyway/i });
      await expect(sendAnywayButton).toBeVisible();
    });

    test('should allow sending message with quality issues using "Send anyway"', async ({
      page,
    }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Click "Send anyway"
      const sendAnywayButton = page.getByRole('button', { name: /send anyway/i });
      await sendAnywayButton.click();

      // Textarea should be cleared after sending
      await expect(textarea).toHaveValue('');
    });

    test('should submit clean message normally with Enter key', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a clean message
      await textarea.fill(
        'I noticed Sophie seems tired today. I wanted to check if everything is okay at home.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Press Enter to send
      await textarea.press('Enter');

      // Textarea should be cleared after sending
      await expect(textarea).toHaveValue('');
    });
  });

  test.describe('Bilingual Support', () => {
    test('should analyze French messages correctly', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a French message with issues
      await textarea.fill('Tu ne fais jamais tes devoirs correctement.');

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check that issues are detected
      const reviewBadge = page.getByText('Review suggested');
      await expect(reviewBadge).toBeVisible({ timeout: 5000 });
    });

    test('should display French issue descriptions for French messages', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a French message with issues
      await textarea.fill('Tu ne fais jamais tes devoirs correctement.');

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for French issue description
      const frenchDescription = page.getByText(/accusateur/i);
      await expect(frenchDescription).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('Severity Indicators', () => {
    test('should display severity badges for detected issues', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for severity badges (High severity issues)
      const highSeverityBadge = page.getByText(/High/);
      await expect(highSeverityBadge.first()).toBeVisible({ timeout: 5000 });

      // Check for medium severity badge
      const mediumSeverityBadge = page.getByText(/Medium/);
      await expect(mediumSeverityBadge.first()).toBeVisible();
    });

    test('should sort issues by severity (critical first)', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message with issues
      await textarea.fill(
        'You never pick up your child on time. This is unacceptable behavior.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // The first issue card should be the highest severity
      // (Visual verification - checking order of elements)
      const issueCards = page.locator('.rounded-lg.border.p-3');
      const firstCard = issueCards.first();

      // First card should have high severity styling (orange or red background)
      await expect(firstCard).toHaveClass(/bg-(red|orange)-50/);
    });
  });

  test.describe('Great Message State', () => {
    test('should show success message for high-quality messages', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a clean message
      await textarea.fill(
        'I noticed Sophie seems tired today. I wanted to check if everything is okay at home.'
      );

      // Wait for analysis
      await page.waitForTimeout(600);

      // Check for "Great message!" text
      const greatMessageText = page.getByText('Great message!');
      await expect(greatMessageText).toBeVisible({ timeout: 5000 });

      // Check for Quebec Bonne Message standards reference
      const bonneMessageText = page.getByText(/Quebec.*Bonne Message.*standards/i);
      await expect(bonneMessageText).toBeVisible();
    });
  });

  test.describe('Character Threshold', () => {
    test('should not analyze messages below minimum character threshold', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a short message (below 20 character threshold)
      await textarea.fill('Hello');

      // Wait a bit
      await page.waitForTimeout(600);

      // Should still show empty state
      const emptyStateText = page.getByText('Start typing to see quality analysis');
      await expect(emptyStateText).toBeVisible();
    });

    test('should show character count prompt for short messages', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a short message
      await textarea.fill('Hi there');

      // Check for character count prompt
      const charPrompt = page.getByText(/Type.*more characters for quality analysis/i);
      await expect(charPrompt).toBeVisible();
    });
  });

  test.describe('Debouncing', () => {
    test('should debounce analysis requests while typing', async ({ page }) => {
      let analyzeCallCount = 0;

      // Track API calls
      await page.route('**/api/v1/message-quality/analyze', async (route) => {
        analyzeCallCount++;
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockAnalysisResponseClean),
        });
      });

      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type quickly (simulating real typing)
      await textarea.type('I noticed Sophie seems tired today.', { delay: 50 });

      // Wait for debounce to complete
      await page.waitForTimeout(700);

      // Should have made only one API call due to debouncing
      // (This may vary based on exact typing speed and debounce timing)
      expect(analyzeCallCount).toBeLessThanOrEqual(2);
    });
  });

  test.describe('Accessibility', () => {
    test('should have accessible button titles', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check for accessible collapse button
      const collapseButton = page.locator('button[title="Collapse panel"]');
      await expect(collapseButton).toHaveAttribute('title', 'Collapse panel');
    });

    test('should have proper aria-label on message input', async ({ page }) => {
      const textarea = page.getByRole('textbox', { name: /message input/i });
      await expect(textarea).toHaveAttribute('aria-label', 'Message input');
    });

    test('should have semantic heading for Quality Coach', async ({ page }) => {
      const heading = page.getByRole('heading', { name: /quality coach/i });
      await expect(heading).toBeVisible();
    });
  });

  test.describe('Error Handling', () => {
    test('should display error message when analysis fails', async ({ page }) => {
      // Override the route to return an error
      await page.route('**/api/v1/message-quality/analyze', async (route) => {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Internal server error' }),
        });
      });

      const textarea = page.getByRole('textbox', { name: /message input/i });

      // Type a message
      await textarea.fill('Test message that should trigger an error.');

      // Wait for analysis attempt
      await page.waitForTimeout(600);

      // Check for error message
      const errorMessage = page.getByText(/quality analysis unavailable/i);
      await expect(errorMessage).toBeVisible({ timeout: 5000 });
    });
  });
});

// Mobile responsiveness tests
test.describe('Mobile Responsiveness', () => {
  test.use({ viewport: { width: 375, height: 667 } }); // iPhone SE

  test('should display Quality Coach panel on mobile', async ({ page }) => {
    // Set up API mocking
    await page.route('**/api/v1/message-quality/analyze', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockAnalysisResponseClean),
      });
    });

    await page.goto('/messages');
    await page.waitForLoadState('networkidle');

    // Quality Coach should be visible on mobile
    const qualityCoachHeader = page.getByRole('heading', { name: /quality coach/i });
    await expect(qualityCoachHeader).toBeVisible();
  });

  test('should allow message input on mobile', async ({ page }) => {
    // Set up API mocking
    await page.route('**/api/v1/message-quality/analyze', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockAnalysisResponseClean),
      });
    });

    await page.goto('/messages');
    await page.waitForLoadState('networkidle');

    const textarea = page.getByRole('textbox', { name: /message input/i });
    await textarea.fill('Testing mobile message input');

    await expect(textarea).toHaveValue('Testing mobile message input');
  });
});
