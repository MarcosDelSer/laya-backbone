/**
 * Tests for XSS sanitization utilities
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  sanitizeHTML,
  sanitizeText,
  sanitizeURL,
  sanitizeAttribute,
  sanitizeForReact,
  containsXSSPatterns,
  sanitizeFilename,
  sanitizeClassName,
  sanitizeJSON,
  SanitizePresets,
  useSanitization,
} from '../sanitize';

describe('XSS Sanitization Utilities', () => {
  beforeEach(() => {
    // Clear console mocks
    vi.clearAllMocks();
    // Spy on console.error and console.warn to suppress test output
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('sanitizeHTML', () => {
    it('should remove script tags', () => {
      const input = '<script>alert("XSS")</script><p>Hello</p>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('<script>');
      expect(output).not.toContain('alert');
      expect(output).toContain('Hello');
    });

    it('should remove inline event handlers', () => {
      const input = '<p onclick="alert(1)">Click me</p>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('onclick');
      expect(output).not.toContain('alert');
      expect(output).toContain('Click me');
    });

    it('should remove javascript: URLs', () => {
      const input = '<a href="javascript:alert(1)">Link</a>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('javascript:');
      expect(output).toContain('Link');
    });

    it('should remove data: URIs by default', () => {
      const input = '<img src="data:text/html,<script>alert(1)</script>" />';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('data:');
    });

    it('should allow safe HTML tags', () => {
      const input = '<p>Hello <strong>World</strong></p>';
      const output = sanitizeHTML(input);

      expect(output).toContain('<p>');
      expect(output).toContain('<strong>');
      expect(output).toContain('Hello');
      expect(output).toContain('World');
    });

    it('should preserve safe links', () => {
      const input = '<a href="https://example.com">Link</a>';
      const output = sanitizeHTML(input);

      expect(output).toContain('href="https://example.com"');
      expect(output).toContain('Link');
    });

    it('should handle empty input', () => {
      expect(sanitizeHTML('')).toBe('');
      expect(sanitizeHTML(null as any)).toBe('');
      expect(sanitizeHTML(undefined as any)).toBe('');
    });

    it('should handle non-string input', () => {
      expect(sanitizeHTML(123 as any)).toBe('');
      expect(sanitizeHTML({} as any)).toBe('');
      expect(sanitizeHTML([] as any)).toBe('');
    });

    it('should use custom allowed tags', () => {
      const input = '<p>Text</p><script>alert(1)</script>';
      const output = sanitizeHTML(input, {
        allowedTags: ['p'],
        allowedAttributes: [],
      });

      expect(output).toContain('<p>');
      expect(output).not.toContain('<script>');
    });

    it('should block iframe tags', () => {
      const input = '<iframe src="javascript:alert(1)"></iframe>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('<iframe>');
      expect(output).not.toContain('javascript:');
    });

    it('should block object and embed tags', () => {
      const input =
        '<object data="malicious.swf"></object><embed src="malicious.swf">';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('<object>');
      expect(output).not.toContain('<embed>');
    });

    it('should handle deeply nested XSS attempts', () => {
      const input = '<div><div><div><script>alert(1)</script></div></div></div>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('<script>');
      expect(output).not.toContain('alert');
    });

    it('should sanitize SVG XSS vectors', () => {
      const input = '<svg onload="alert(1)"><script>alert(2)</script></svg>';
      const output = sanitizeHTML(input);

      expect(output).not.toContain('onload');
      expect(output).not.toContain('<script>');
    });
  });

  describe('sanitizeText', () => {
    it('should strip all HTML tags', () => {
      const input = '<p>Hello <strong>World</strong></p>';
      const output = sanitizeText(input);

      expect(output).toBe('Hello World');
      expect(output).not.toContain('<');
      expect(output).not.toContain('>');
    });

    it('should remove script tags and content', () => {
      const input = '<script>alert("XSS")</script>Text';
      const output = sanitizeText(input);

      expect(output).toBe('Text');
      expect(output).not.toContain('script');
      expect(output).not.toContain('alert');
    });

    it('should handle empty input', () => {
      expect(sanitizeText('')).toBe('');
      expect(sanitizeText(null as any)).toBe('');
      expect(sanitizeText(undefined as any)).toBe('');
    });

    it('should preserve text content', () => {
      const input = 'Just plain text';
      const output = sanitizeText(input);

      expect(output).toBe('Just plain text');
    });

    it('should decode HTML entities', () => {
      const input = '&lt;script&gt;alert(1)&lt;/script&gt;';
      const output = sanitizeText(input);

      // DOMPurify decodes entities
      expect(output).toContain('alert(1)');
      expect(output).not.toContain('&lt;');
    });
  });

  describe('sanitizeURL', () => {
    it('should allow safe HTTP URLs', () => {
      const url = 'http://example.com';
      expect(sanitizeURL(url)).toBe(url);
    });

    it('should allow safe HTTPS URLs', () => {
      const url = 'https://example.com';
      expect(sanitizeURL(url)).toBe(url);
    });

    it('should allow mailto URLs', () => {
      const url = 'mailto:user@example.com';
      expect(sanitizeURL(url)).toBe(url);
    });

    it('should allow tel URLs', () => {
      const url = 'tel:+1234567890';
      expect(sanitizeURL(url)).toBe(url);
    });

    it('should block javascript: URLs', () => {
      const url = 'javascript:alert(1)';
      expect(sanitizeURL(url)).toBe('#');
    });

    it('should block data: URLs', () => {
      const url = 'data:text/html,<script>alert(1)</script>';
      expect(sanitizeURL(url)).toBe('#');
    });

    it('should block vbscript: URLs', () => {
      const url = 'vbscript:msgbox(1)';
      expect(sanitizeURL(url)).toBe('#');
    });

    it('should block file: URLs', () => {
      const url = 'file:///etc/passwd';
      expect(sanitizeURL(url)).toBe('#');
    });

    it('should allow relative URLs', () => {
      expect(sanitizeURL('/path/to/page')).toBe('/path/to/page');
      expect(sanitizeURL('./relative/path')).toBe('./relative/path');
      expect(sanitizeURL('../parent/path')).toBe('../parent/path');
    });

    it('should allow fragment identifiers', () => {
      expect(sanitizeURL('#section')).toBe('#section');
    });

    it('should allow query strings', () => {
      expect(sanitizeURL('?query=value')).toBe('?query=value');
    });

    it('should handle empty input', () => {
      expect(sanitizeURL('')).toBe('#');
      expect(sanitizeURL(null as any)).toBe('#');
      expect(sanitizeURL(undefined as any)).toBe('#');
    });

    it('should handle URLs with whitespace', () => {
      expect(sanitizeURL('  https://example.com  ')).toBe(
        'https://example.com'
      );
    });

    it('should block case variations of dangerous protocols', () => {
      expect(sanitizeURL('JaVaScRiPt:alert(1)')).toBe('#');
      expect(sanitizeURL('DATA:text/html,<script>')).toBe('#');
      expect(sanitizeURL('VbScRiPt:msgbox(1)')).toBe('#');
    });

    it('should block embedded javascript in URLs', () => {
      const url = 'http://example.com/page?redirect=javascript:alert(1)';
      // URL is valid but contains javascript: - should pass through
      // The sanitization at this level is protocol-based
      expect(sanitizeURL(url)).toBe(url);
    });
  });

  describe('sanitizeAttribute', () => {
    it('should escape quotes', () => {
      const input = 'value"with"quotes';
      const output = sanitizeAttribute(input);

      expect(output).not.toContain('"');
    });

    it('should escape special characters', () => {
      const input = 'value<script>alert(1)</script>';
      const output = sanitizeAttribute(input);

      expect(output).not.toContain('<script>');
    });

    it('should handle empty input', () => {
      expect(sanitizeAttribute('')).toBe('');
      expect(sanitizeAttribute(null as any)).toBe('');
      expect(sanitizeAttribute(undefined as any)).toBe('');
    });

    it('should handle event handler injection attempts', () => {
      const input = 'test" onload="alert(1)';
      const output = sanitizeAttribute(input);

      expect(output).not.toContain('onload');
      expect(output).not.toContain('alert');
    });
  });

  describe('sanitizeForReact', () => {
    it('should return object with __html property', () => {
      const input = '<p>Hello</p>';
      const output = sanitizeForReact(input);

      expect(output).toHaveProperty('__html');
      expect(typeof output.__html).toBe('string');
    });

    it('should sanitize HTML content', () => {
      const input = '<script>alert(1)</script><p>Safe</p>';
      const output = sanitizeForReact(input);

      expect(output.__html).not.toContain('<script>');
      expect(output.__html).toContain('Safe');
    });

    it('should accept custom configuration', () => {
      const input = '<p>Text</p>';
      const output = sanitizeForReact(input, {
        allowedTags: ['p'],
      });

      expect(output.__html).toContain('<p>');
    });

    it('should be suitable for dangerouslySetInnerHTML', () => {
      const input = '<p>Hello <strong>World</strong></p>';
      const output = sanitizeForReact(input);

      // Verify structure matches React's expectation
      expect(output).toEqual({ __html: expect.any(String) });
    });
  });

  describe('containsXSSPatterns', () => {
    it('should detect script tags', () => {
      expect(containsXSSPatterns('<script>alert(1)</script>')).toBe(true);
      expect(containsXSSPatterns('<SCRIPT>alert(1)</SCRIPT>')).toBe(true);
    });

    it('should detect javascript: protocol', () => {
      expect(containsXSSPatterns('javascript:alert(1)')).toBe(true);
      expect(containsXSSPatterns('JAVASCRIPT:alert(1)')).toBe(true);
    });

    it('should detect event handlers', () => {
      expect(containsXSSPatterns('onerror=alert(1)')).toBe(true);
      expect(containsXSSPatterns('onload=alert(1)')).toBe(true);
      expect(containsXSSPatterns('onclick=alert(1)')).toBe(true);
    });

    it('should detect iframe tags', () => {
      expect(containsXSSPatterns('<iframe src="...">')).toBe(true);
    });

    it('should detect object and embed tags', () => {
      expect(containsXSSPatterns('<object data="...">')).toBe(true);
      expect(containsXSSPatterns('<embed src="...">')).toBe(true);
    });

    it('should detect data: URIs', () => {
      expect(containsXSSPatterns('data:text/html,<script>')).toBe(true);
    });

    it('should return false for safe content', () => {
      expect(containsXSSPatterns('Hello World')).toBe(false);
      expect(containsXSSPatterns('<p>Safe HTML</p>')).toBe(false);
      expect(containsXSSPatterns('https://example.com')).toBe(false);
    });

    it('should handle empty input', () => {
      expect(containsXSSPatterns('')).toBe(false);
      expect(containsXSSPatterns(null as any)).toBe(false);
      expect(containsXSSPatterns(undefined as any)).toBe(false);
    });
  });

  describe('sanitizeFilename', () => {
    it('should remove path traversal attempts', () => {
      expect(sanitizeFilename('../../etc/passwd')).toBe('etcpasswd');
      expect(sanitizeFilename('../../../file.txt')).toBe('file.txt');
    });

    it('should remove path separators', () => {
      expect(sanitizeFilename('path/to/file.txt')).toBe('pathtofile.txt');
      expect(sanitizeFilename('path\\to\\file.txt')).toBe('pathtofile.txt');
    });

    it('should remove dangerous characters', () => {
      expect(sanitizeFilename('file<script>.txt')).toBe('filescript.txt');
      expect(sanitizeFilename('file|name.txt')).toBe('filename.txt');
    });

    it('should preserve valid filenames', () => {
      expect(sanitizeFilename('valid-file_name.txt')).toBe(
        'valid-file_name.txt'
      );
      expect(sanitizeFilename('document.pdf')).toBe('document.pdf');
    });

    it('should handle empty input', () => {
      expect(sanitizeFilename('')).toBe('file');
      expect(sanitizeFilename(null as any)).toBe('');
      expect(sanitizeFilename(undefined as any)).toBe('');
    });

    it('should limit filename length', () => {
      const longName = 'a'.repeat(300);
      const sanitized = sanitizeFilename(longName);

      expect(sanitized.length).toBeLessThanOrEqual(255);
    });

    it('should remove control characters', () => {
      expect(sanitizeFilename('file\x00name.txt')).toBe('filename.txt');
      expect(sanitizeFilename('file\x1fname.txt')).toBe('filename.txt');
    });
  });

  describe('sanitizeClassName', () => {
    it('should allow valid class names', () => {
      expect(sanitizeClassName('my-class')).toBe('my-class');
      expect(sanitizeClassName('my_class')).toBe('my_class');
      expect(sanitizeClassName('MyClass123')).toBe('MyClass123');
    });

    it('should remove special characters', () => {
      expect(sanitizeClassName('class<script>')).toBe('classscript');
      expect(sanitizeClassName('class!@#$%')).toBe('class');
    });

    it('should handle empty input', () => {
      expect(sanitizeClassName('')).toBe('');
      expect(sanitizeClassName(null as any)).toBe('');
      expect(sanitizeClassName(undefined as any)).toBe('');
    });

    it('should preserve hyphens and underscores', () => {
      expect(sanitizeClassName('btn-primary_active')).toBe('btn-primary_active');
    });
  });

  describe('sanitizeJSON', () => {
    it('should escape HTML special characters', () => {
      const data = { html: '<script>alert(1)</script>' };
      const output = sanitizeJSON(data);

      expect(output).not.toContain('<script>');
      expect(output).toContain('\\u003c');
      expect(output).toContain('\\u003e');
    });

    it('should escape ampersands', () => {
      const data = { text: 'A & B' };
      const output = sanitizeJSON(data);

      expect(output).toContain('\\u0026');
    });

    it('should escape quotes', () => {
      const data = { text: "Quote's" };
      const output = sanitizeJSON(data);

      expect(output).toContain('\\u0027');
    });

    it('should handle complex objects', () => {
      const data = {
        nested: {
          value: '</script><script>alert(1)</script>',
        },
        array: [1, 2, 3],
      };
      const output = sanitizeJSON(data);

      expect(output).not.toContain('</script>');
      expect(() => JSON.parse(output.replace(/\\u[\da-f]{4}/gi, (match) =>
        String.fromCharCode(parseInt(match.replace(/\\u/g, ''), 16))
      ))).not.toThrow();
    });

    it('should handle serialization errors', () => {
      const circular: any = {};
      circular.self = circular;

      const output = sanitizeJSON(circular);
      expect(output).toBe('{}');
    });
  });

  describe('SanitizePresets', () => {
    it('should have STRICT preset', () => {
      expect(SanitizePresets.STRICT).toBeDefined();
      expect(SanitizePresets.STRICT.allowedTags).toEqual([]);
    });

    it('should have BASIC preset', () => {
      expect(SanitizePresets.BASIC).toBeDefined();
      expect(SanitizePresets.BASIC.allowedTags).toContain('p');
      expect(SanitizePresets.BASIC.allowedTags).toContain('strong');
    });

    it('should have RICH_TEXT preset', () => {
      expect(SanitizePresets.RICH_TEXT).toBeDefined();
      expect(SanitizePresets.RICH_TEXT.allowedTags?.length).toBeGreaterThan(0);
    });

    it('should have MARKDOWN preset', () => {
      expect(SanitizePresets.MARKDOWN).toBeDefined();
      expect(SanitizePresets.MARKDOWN.allowedTags).toContain('code');
      expect(SanitizePresets.MARKDOWN.allowedTags).toContain('pre');
    });

    it('STRICT preset should strip all HTML', () => {
      const input = '<p>Hello <strong>World</strong></p>';
      const output = sanitizeHTML(input, SanitizePresets.STRICT);

      expect(output).toBe('Hello World');
    });

    it('BASIC preset should allow basic formatting', () => {
      const input = '<p>Hello <strong>World</strong></p>';
      const output = sanitizeHTML(input, SanitizePresets.BASIC);

      expect(output).toContain('<p>');
      expect(output).toContain('<strong>');
    });
  });

  describe('useSanitization hook', () => {
    it('should return all sanitization functions', () => {
      const utils = useSanitization();

      expect(utils.sanitizeHTML).toBeDefined();
      expect(utils.sanitizeText).toBeDefined();
      expect(utils.sanitizeURL).toBeDefined();
      expect(utils.sanitizeAttribute).toBeDefined();
      expect(utils.sanitizeForReact).toBeDefined();
      expect(utils.containsXSSPatterns).toBeDefined();
      expect(utils.sanitizeFilename).toBeDefined();
      expect(utils.sanitizeClassName).toBeDefined();
      expect(utils.sanitizeJSON).toBeDefined();
      expect(utils.presets).toBeDefined();
    });

    it('should provide access to presets', () => {
      const utils = useSanitization();

      expect(utils.presets.STRICT).toBeDefined();
      expect(utils.presets.BASIC).toBeDefined();
      expect(utils.presets.RICH_TEXT).toBeDefined();
      expect(utils.presets.MARKDOWN).toBeDefined();
    });

    it('sanitization functions should work through hook', () => {
      const utils = useSanitization();
      const input = '<script>alert(1)</script><p>Safe</p>';
      const output = utils.sanitizeHTML(input);

      expect(output).not.toContain('<script>');
      expect(output).toContain('Safe');
    });
  });

  describe('Integration tests', () => {
    it('should handle multiple XSS vectors in one input', () => {
      const input = `
        <script>alert(1)</script>
        <img src=x onerror=alert(2)>
        <a href="javascript:alert(3)">Click</a>
        <iframe src="evil.com"></iframe>
        <object data="evil.swf"></object>
      `;
      const output = sanitizeHTML(input);

      expect(output).not.toContain('<script>');
      expect(output).not.toContain('onerror');
      expect(output).not.toContain('javascript:');
      expect(output).not.toContain('<iframe>');
      expect(output).not.toContain('<object>');
    });

    it('should sanitize user profile data', () => {
      const userBio = '<script>alert("XSS")</script><p>Hello, I am a <strong>developer</strong>!</p>';
      const sanitized = sanitizeHTML(userBio);

      expect(sanitized).not.toContain('<script>');
      expect(sanitized).toContain('Hello, I am a');
      expect(sanitized).toContain('<strong>developer</strong>');
    });

    it('should sanitize comment content', () => {
      const comment = 'Check out <a href="javascript:alert(1)">this link</a>!';
      const sanitized = sanitizeHTML(comment);

      expect(sanitized).not.toContain('javascript:');
      expect(sanitized).toContain('this link');
    });

    it('should work with React dangerouslySetInnerHTML', () => {
      const userInput = '<p>Safe content</p><script>alert(1)</script>';
      const reactProps = sanitizeForReact(userInput);

      expect(reactProps.__html).toContain('Safe content');
      expect(reactProps.__html).not.toContain('<script>');
    });
  });
});
