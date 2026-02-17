/**
 * XSS (Cross-Site Scripting) sanitization utilities for LAYA Parent Portal.
 *
 * This module provides comprehensive XSS protection through input sanitization
 * and validation. It uses DOMPurify for HTML sanitization and provides
 * utilities for different contexts (HTML, attributes, URLs, etc.).
 *
 * @example
 * ```typescript
 * import { sanitizeHTML, sanitizeURL } from '@/lib/security/sanitize';
 *
 * const safeHTML = sanitizeHTML(userInput);
 * const safeURL = sanitizeURL(userURL);
 * ```
 */

import DOMPurify from 'isomorphic-dompurify';

/**
 * Sanitization configuration options
 */
export interface SanitizeConfig {
  /**
   * Allowed HTML tags (default: safe subset)
   */
  allowedTags?: string[];
  /**
   * Allowed HTML attributes (default: safe subset)
   */
  allowedAttributes?: string[];
  /**
   * Whether to allow data URIs (default: false for security)
   */
  allowDataURI?: boolean;
  /**
   * Whether to keep safe HTML tags (default: false strips all HTML)
   */
  keepSafeHTML?: boolean;
  /**
   * Custom DOMPurify configuration
   */
  dompurifyConfig?: DOMPurify.Config;
}

/**
 * Default safe HTML tags (minimal set for basic formatting)
 */
const DEFAULT_ALLOWED_TAGS = [
  'p',
  'br',
  'strong',
  'em',
  'u',
  'a',
  'ul',
  'ol',
  'li',
  'blockquote',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
];

/**
 * Default safe HTML attributes
 */
const DEFAULT_ALLOWED_ATTRIBUTES = ['href', 'title', 'class', 'id'];

/**
 * Dangerous URL protocols that should always be blocked
 */
const DANGEROUS_PROTOCOLS = [
  'javascript:',
  'data:',
  'vbscript:',
  'file:',
  'about:',
];

/**
 * Safe URL protocols that are allowed
 */
const SAFE_PROTOCOLS = ['http:', 'https:', 'mailto:', 'tel:'];

/**
 * Sanitize HTML content to prevent XSS attacks
 *
 * This is the main function for sanitizing user-generated HTML content.
 * It removes dangerous tags, attributes, and scripts while optionally
 * preserving safe formatting.
 *
 * @param html - The HTML string to sanitize
 * @param config - Optional sanitization configuration
 * @returns Sanitized HTML string safe for rendering
 *
 * @example
 * ```typescript
 * const userInput = '<script>alert("XSS")</script><p>Hello</p>';
 * const safe = sanitizeHTML(userInput); // Returns: '<p>Hello</p>'
 * ```
 */
export function sanitizeHTML(
  html: string,
  config: SanitizeConfig = {}
): string {
  if (!html || typeof html !== 'string') {
    return '';
  }

  const {
    allowedTags = DEFAULT_ALLOWED_TAGS,
    allowedAttributes = DEFAULT_ALLOWED_ATTRIBUTES,
    allowDataURI = false,
    dompurifyConfig = {},
  } = config;

  // Configure DOMPurify
  const purifyConfig: DOMPurify.Config = {
    ALLOWED_TAGS: allowedTags,
    ALLOWED_ATTR: allowedAttributes,
    ALLOW_DATA_ATTR: false, // Never allow data-* attributes by default
    ALLOW_UNKNOWN_PROTOCOLS: false,
    SAFE_FOR_TEMPLATES: true, // Escape template strings
    WHOLE_DOCUMENT: false,
    RETURN_DOM: false,
    RETURN_DOM_FRAGMENT: false,
    FORCE_BODY: false,
    SANITIZE_DOM: true, // Enable DOM clobbering protection
    KEEP_CONTENT: true, // Keep text content from removed tags
    ...dompurifyConfig,
  };

  // Add URI filtering if data URIs are not allowed
  if (!allowDataURI) {
    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
      // Remove data URIs from href and src attributes
      if (node.hasAttribute('href')) {
        const href = node.getAttribute('href') || '';
        if (href.toLowerCase().startsWith('data:')) {
          node.removeAttribute('href');
        }
      }
      if (node.hasAttribute('src')) {
        const src = node.getAttribute('src') || '';
        if (src.toLowerCase().startsWith('data:')) {
          node.removeAttribute('src');
        }
      }
    });
  }

  try {
    const sanitized = DOMPurify.sanitize(html, purifyConfig);
    return sanitized;
  } catch (error) {
    // If sanitization fails, return empty string (fail secure)
    console.error('HTML sanitization error:', error);
    return '';
  } finally {
    // Clean up hooks to prevent memory leaks
    DOMPurify.removeAllHooks();
  }
}

/**
 * Sanitize HTML and strip all tags, leaving only plain text
 *
 * Use this when you want to accept user input but display it as plain text,
 * removing all HTML formatting.
 *
 * @param html - The HTML string to sanitize
 * @returns Plain text with all HTML removed
 *
 * @example
 * ```typescript
 * const input = '<p>Hello <strong>World</strong></p>';
 * const text = sanitizeText(input); // Returns: 'Hello World'
 * ```
 */
export function sanitizeText(html: string): string {
  if (!html || typeof html !== 'string') {
    return '';
  }

  try {
    // Sanitize and strip all tags
    const sanitized = DOMPurify.sanitize(html, {
      ALLOWED_TAGS: [], // No tags allowed
      KEEP_CONTENT: true, // Keep text content
    });
    return sanitized.trim();
  } catch (error) {
    console.error('Text sanitization error:', error);
    return '';
  }
}

/**
 * Sanitize a URL to prevent XSS attacks via javascript: or data: URIs
 *
 * Only allows safe protocols (http, https, mailto, tel) and blocks
 * dangerous protocols that could execute JavaScript.
 *
 * @param url - The URL to sanitize
 * @returns Sanitized URL or '#' if URL is dangerous
 *
 * @example
 * ```typescript
 * sanitizeURL('javascript:alert(1)'); // Returns: '#'
 * sanitizeURL('https://example.com'); // Returns: 'https://example.com'
 * ```
 */
export function sanitizeURL(url: string): string {
  if (!url || typeof url !== 'string') {
    return '#';
  }

  const trimmedURL = url.trim();

  // Empty URL
  if (!trimmedURL) {
    return '#';
  }

  // Check for dangerous protocols
  const lowerURL = trimmedURL.toLowerCase();
  for (const protocol of DANGEROUS_PROTOCOLS) {
    if (lowerURL.startsWith(protocol)) {
      console.warn('Blocked dangerous URL protocol:', protocol);
      return '#';
    }
  }

  // Check if URL has a protocol
  try {
    const urlObj = new URL(trimmedURL);
    const protocol = urlObj.protocol;

    // Only allow safe protocols
    if (!SAFE_PROTOCOLS.includes(protocol)) {
      console.warn('Blocked unsafe URL protocol:', protocol);
      return '#';
    }

    return trimmedURL;
  } catch {
    // Relative URL or malformed URL
    // Allow relative URLs (they start with / or ./  or ../)
    if (
      trimmedURL.startsWith('/') ||
      trimmedURL.startsWith('./') ||
      trimmedURL.startsWith('../')
    ) {
      return trimmedURL;
    }

    // Check for protocol-less dangerous patterns
    if (
      lowerURL.includes('javascript:') ||
      lowerURL.includes('data:') ||
      lowerURL.includes('vbscript:')
    ) {
      console.warn('Blocked URL with embedded dangerous protocol');
      return '#';
    }

    // For other cases (fragment identifiers, query strings), allow them
    if (trimmedURL.startsWith('#') || trimmedURL.startsWith('?')) {
      return trimmedURL;
    }

    // If we can't determine safety, block it
    return '#';
  }
}

/**
 * Sanitize an HTML attribute value
 *
 * Use this for sanitizing values that will be placed in HTML attributes.
 * It encodes special characters to prevent attribute-based XSS.
 *
 * @param value - The attribute value to sanitize
 * @returns Sanitized attribute value
 *
 * @example
 * ```typescript
 * const value = 'user"onload="alert(1)';
 * const safe = sanitizeAttribute(value); // Encodes quotes and special chars
 * ```
 */
export function sanitizeAttribute(value: string): string {
  if (!value || typeof value !== 'string') {
    return '';
  }

  // Use DOMPurify to sanitize the attribute
  // Wrap in a div to use as attribute, then extract
  const html = `<div data-value="${value}"></div>`;
  const sanitized = DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['div'],
    ALLOWED_ATTR: ['data-value'],
  });

  // Extract the sanitized value
  const match = sanitized.match(/data-value="([^"]*)"/);
  return match?.[1] || '';
}

/**
 * Sanitize user input for safe display in React components
 *
 * This function prepares user input for rendering in React by sanitizing
 * HTML and returning an object suitable for dangerouslySetInnerHTML.
 *
 * @param html - The HTML string to sanitize
 * @param config - Optional sanitization configuration
 * @returns Object with __html property for React
 *
 * @example
 * ```tsx
 * const userBio = '<p>Hello <script>alert(1)</script></p>';
 * const sanitized = sanitizeForReact(userBio);
 * return <div dangerouslySetInnerHTML={sanitized} />;
 * ```
 */
export function sanitizeForReact(
  html: string,
  config: SanitizeConfig = {}
): { __html: string } {
  const sanitized = sanitizeHTML(html, config);
  return { __html: sanitized };
}

/**
 * Check if a string contains potentially dangerous content
 *
 * This is a quick check for obvious XSS attempts before sanitization.
 * Use this for logging/monitoring suspicious input.
 *
 * @param input - The input to check
 * @returns True if input contains suspicious patterns
 *
 * @example
 * ```typescript
 * if (containsXSSPatterns(userInput)) {
 *   logSecurityWarning('Possible XSS attempt detected');
 * }
 * ```
 */
export function containsXSSPatterns(input: string): boolean {
  if (!input || typeof input !== 'string') {
    return false;
  }

  const lowerInput = input.toLowerCase();

  // Check for common XSS patterns
  const xssPatterns = [
    '<script',
    'javascript:',
    'onerror=',
    'onload=',
    'onclick=',
    'onmouseover=',
    'onfocus=',
    'onblur=',
    '<iframe',
    '<object',
    '<embed',
    '<applet',
    'data:text/html',
    'vbscript:',
    'expression(',
    'import(',
    'eval(',
  ];

  return xssPatterns.some((pattern) => lowerInput.includes(pattern));
}

/**
 * Sanitize a filename to prevent path traversal attacks
 *
 * Removes directory traversal characters and dangerous characters
 * from filenames to prevent path traversal vulnerabilities.
 *
 * @param filename - The filename to sanitize
 * @returns Sanitized filename safe for file operations
 *
 * @example
 * ```typescript
 * sanitizeFilename('../../etc/passwd'); // Returns: 'etcpasswd'
 * sanitizeFilename('file<script>.txt'); // Returns: 'filescript.txt'
 * ```
 */
export function sanitizeFilename(filename: string): string {
  if (!filename || typeof filename !== 'string') {
    return '';
  }

  // Remove path separators and parent directory references
  let sanitized = filename
    .replace(/\.\./g, '') // Remove parent directory references
    .replace(/[/\\]/g, '') // Remove path separators
    .replace(/[<>:"|?*]/g, '') // Remove Windows forbidden characters
    .replace(/[\x00-\x1f\x80-\x9f]/g, '') // Remove control characters
    .trim();

  // Ensure filename is not empty after sanitization
  if (!sanitized) {
    sanitized = 'file';
  }

  // Limit length
  if (sanitized.length > 255) {
    sanitized = sanitized.substring(0, 255);
  }

  return sanitized;
}

/**
 * Sanitize CSS class names to prevent CSS injection
 *
 * Ensures class names only contain safe characters.
 *
 * @param className - The class name to sanitize
 * @returns Sanitized class name
 *
 * @example
 * ```typescript
 * sanitizeClassName('my-class'); // Returns: 'my-class'
 * sanitizeClassName('my-class<script>'); // Returns: 'my-class'
 * ```
 */
export function sanitizeClassName(className: string): string {
  if (!className || typeof className !== 'string') {
    return '';
  }

  // Only allow alphanumeric, hyphens, underscores
  return className.replace(/[^a-zA-Z0-9_-]/g, '').trim();
}

/**
 * Sanitize JSON data to prevent XSS in JSON contexts
 *
 * Escapes characters that could break out of JSON context.
 * Use this when embedding JSON in HTML.
 *
 * @param data - The data to sanitize
 * @returns Sanitized JSON string
 *
 * @example
 * ```typescript
 * const data = { name: '</script><script>alert(1)</script>' };
 * const safe = sanitizeJSON(data);
 * ```
 */
export function sanitizeJSON(data: any): string {
  try {
    const json = JSON.stringify(data);

    // Escape characters that could break out of script context
    return json
      .replace(/</g, '\\u003c')
      .replace(/>/g, '\\u003e')
      .replace(/&/g, '\\u0026')
      .replace(/'/g, '\\u0027');
  } catch (error) {
    console.error('JSON sanitization error:', error);
    return '{}';
  }
}

/**
 * Configuration presets for common use cases
 */
export const SanitizePresets = {
  /**
   * Strict preset: Strips all HTML tags
   */
  STRICT: {
    keepSafeHTML: false,
    allowedTags: [],
    allowedAttributes: [],
    allowDataURI: false,
  } as SanitizeConfig,

  /**
   * Basic preset: Allows basic text formatting only
   */
  BASIC: {
    keepSafeHTML: true,
    allowedTags: ['p', 'br', 'strong', 'em', 'u'],
    allowedAttributes: [],
    allowDataURI: false,
  } as SanitizeConfig,

  /**
   * Rich text preset: Allows common rich text tags
   */
  RICH_TEXT: {
    keepSafeHTML: true,
    allowedTags: DEFAULT_ALLOWED_TAGS,
    allowedAttributes: ['href', 'title', 'class'],
    allowDataURI: false,
  } as SanitizeConfig,

  /**
   * Markdown preset: Suitable for sanitized Markdown output
   */
  MARKDOWN: {
    keepSafeHTML: true,
    allowedTags: [
      ...DEFAULT_ALLOWED_TAGS,
      'code',
      'pre',
      'img',
      'table',
      'thead',
      'tbody',
      'tr',
      'th',
      'td',
    ],
    allowedAttributes: ['href', 'title', 'class', 'src', 'alt'],
    allowDataURI: false,
  } as SanitizeConfig,
};

/**
 * React hook for sanitization utilities
 *
 * Provides access to all sanitization functions in a React component.
 *
 * @returns Object with sanitization utilities
 *
 * @example
 * ```tsx
 * function MyComponent() {
 *   const { sanitizeHTML, sanitizeURL } = useSanitization();
 *   const safeContent = sanitizeHTML(userInput);
 *   return <div dangerouslySetInnerHTML={{ __html: safeContent }} />;
 * }
 * ```
 */
export function useSanitization() {
  return {
    sanitizeHTML,
    sanitizeText,
    sanitizeURL,
    sanitizeAttribute,
    sanitizeForReact,
    containsXSSPatterns,
    sanitizeFilename,
    sanitizeClassName,
    sanitizeJSON,
    presets: SanitizePresets,
  };
}

/**
 * Export DOMPurify instance for advanced use cases
 * Use with caution - prefer the sanitization functions above
 */
export { DOMPurify };
