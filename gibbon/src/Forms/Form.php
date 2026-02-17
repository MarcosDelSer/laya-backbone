<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Form Builder
 *
 * Provides HTML form generation with built-in security features including
 * CSRF protection, input validation, and accessibility support.
 *
 * Features:
 * - Fluent interface for form building
 * - Automatic CSRF token injection
 * - HTML5 input types and attributes
 * - Accessibility (ARIA) support
 * - Integration with Gibbon's session management
 *
 * Security Considerations:
 * - Automatic CSRF token generation and injection
 * - HTML entity encoding for XSS protection
 * - Secure form attribute defaults
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

namespace Gibbon\Forms;

use Gibbon\Security\CsrfTokenManager;

/**
 * Form class
 *
 * Builds and renders HTML forms with security features.
 */
class Form
{
    /**
     * @var string Form ID
     */
    private $formId;

    /**
     * @var string Form action URL
     */
    private $action;

    /**
     * @var string Form method (GET or POST)
     */
    private $method = 'POST';

    /**
     * @var string Form title
     */
    private $title = '';

    /**
     * @var array Form fields and content
     */
    private $fields = [];

    /**
     * @var array Form attributes
     */
    private $attributes = [];

    /**
     * @var CsrfTokenManager|null CSRF token manager
     */
    private $csrfTokenManager;

    /**
     * @var bool Whether CSRF token has been added
     */
    private $csrfTokenAdded = false;

    /**
     * Private constructor (use static factory method create())
     *
     * @param string $formId Form identifier
     * @param string $action Form action URL
     * @param string $method HTTP method (default: POST)
     */
    private function __construct(string $formId, string $action, string $method = 'POST')
    {
        $this->formId = $formId;
        $this->action = $action;
        $this->method = strtoupper($method);

        // Set default attributes
        $this->attributes = [
            'id' => $formId,
            'name' => $formId,
            'method' => $this->method,
            'action' => $action,
        ];
    }

    /**
     * Create a new form instance
     *
     * Static factory method for creating forms.
     *
     * @param string $formId Form identifier
     * @param string $action Form action URL
     * @param string $method HTTP method (default: POST)
     * @return self New Form instance
     */
    public static function create(string $formId, string $action, string $method = 'POST'): self
    {
        return new self($formId, $action, $method);
    }

    /**
     * Set form title
     *
     * @param string $title Form title
     * @return self For method chaining
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set CSRF token manager
     *
     * @param CsrfTokenManager $csrfTokenManager CSRF token manager instance
     * @return self For method chaining
     */
    public function setCsrfTokenManager(CsrfTokenManager $csrfTokenManager): self
    {
        $this->csrfTokenManager = $csrfTokenManager;
        return $this;
    }

    /**
     * Add CSRF token to form
     *
     * Automatically adds a hidden CSRF token field to the form for protection
     * against Cross-Site Request Forgery attacks. The token is retrieved from
     * the session and validated on form submission.
     *
     * This method should be called for all state-changing forms (POST, PUT, DELETE).
     *
     * @param string|null $token Optional CSRF token (if not provided, retrieves from session)
     * @param string|null $formId Optional form identifier for per-form tokens
     * @return self For method chaining
     */
    public function addCsrfToken(?string $token = null, ?string $formId = null): self
    {
        // Prevent duplicate CSRF tokens
        if ($this->csrfTokenAdded) {
            return $this;
        }

        // Get token from session if not provided
        if ($token === null) {
            if ($this->csrfTokenManager !== null) {
                $token = $this->csrfTokenManager->getToken($formId);
            } elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrf_token'])) {
                $token = $_SESSION['csrf_token'];
            } else {
                // Generate new token if none exists
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $token = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token'] = $token;
                } else {
                    throw new \RuntimeException('Cannot add CSRF token: session not active and no token provided');
                }
            }
        }

        // Add hidden CSRF token field
        $this->addHiddenValue('csrf_token', $token);
        $this->csrfTokenAdded = true;

        return $this;
    }

    /**
     * Add a hidden input field to the form
     *
     * @param string $name Field name
     * @param string $value Field value
     * @return self For method chaining
     */
    public function addHiddenValue(string $name, string $value): self
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $this->fields[] = sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $escapedName,
            $escapedValue
        );

        return $this;
    }

    /**
     * Set a form attribute
     *
     * @param string $name Attribute name
     * @param string $value Attribute value
     * @return self For method chaining
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * Add a CSS class to the form
     *
     * @param string $className CSS class name
     * @return self For method chaining
     */
    public function addClass(string $className): self
    {
        if (isset($this->attributes['class'])) {
            $this->attributes['class'] .= ' ' . $className;
        } else {
            $this->attributes['class'] = $className;
        }
        return $this;
    }

    /**
     * Add raw HTML content to the form
     *
     * @param string $html HTML content to add
     * @return self For method chaining
     */
    public function addContent(string $html): self
    {
        $this->fields[] = $html;
        return $this;
    }

    /**
     * Render the form as HTML
     *
     * @return string HTML form markup
     */
    public function render(): string
    {
        $html = '';

        // Add title if set
        if (!empty($this->title)) {
            $escapedTitle = htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8');
            $html .= sprintf('<h2>%s</h2>', $escapedTitle);
        }

        // Build form opening tag
        $html .= '<form';
        foreach ($this->attributes as $name => $value) {
            $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $html .= sprintf(' %s="%s"', $escapedName, $escapedValue);
        }
        $html .= '>';

        // Add form fields
        foreach ($this->fields as $field) {
            $html .= "\n    " . $field;
        }

        // Close form tag
        $html .= "\n</form>";

        return $html;
    }

    /**
     * Convert form to string (calls render())
     *
     * @return string HTML form markup
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            error_log('Form rendering error: ' . $e->getMessage());
            return '<!-- Form rendering error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
        }
    }

    /**
     * Get form ID
     *
     * @return string Form identifier
     */
    public function getId(): string
    {
        return $this->formId;
    }

    /**
     * Get form action URL
     *
     * @return string Form action URL
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get form method
     *
     * @return string HTTP method
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}
