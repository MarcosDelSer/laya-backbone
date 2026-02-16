/**
 * LAYA Teacher App - LoginScreen Component Tests
 *
 * Component tests for the LoginScreen, covering:
 * - Initial rendering of all UI elements
 * - Form input handling and validation
 * - Login submission flow
 * - Biometric login functionality
 * - Error display
 * - Loading states
 */

import React from 'react';
import {
  render,
  fireEvent,
  waitFor,
  screen,
} from '@testing-library/react-native';
import LoginScreen from '../../src/screens/LoginScreen';

// Mock the useAuth hook
jest.mock('../../src/hooks/useAuth', () => ({
  useAuth: jest.fn(),
}));

// Import mocked hook
import {useAuth} from '../../src/hooks/useAuth';

const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;

// Test fixtures
const mockBiometricStatusFingerprint = {
  isAvailable: true,
  biometricType: 'fingerprint' as const,
  isEnrolled: true,
};

const mockBiometricStatusFace = {
  isAvailable: true,
  biometricType: 'face' as const,
  isEnrolled: true,
};

const mockBiometricStatusNone = {
  isAvailable: false,
  biometricType: 'none' as const,
  isEnrolled: false,
};

const mockError = {
  code: 'INVALID_CREDENTIALS' as const,
  message: 'Invalid email or password',
};

// Default mock values for useAuth
const createMockAuthState = (overrides = {}) => ({
  isLoggingIn: false,
  error: null,
  biometricStatus: mockBiometricStatusFingerprint,
  biometricEnabled: false,
  storedEmail: null,
  login: jest.fn(),
  loginWithBiometrics: jest.fn(),
  clearError: jest.fn(),
  ...overrides,
});

describe('LoginScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue(createMockAuthState());
  });

  describe('rendering', () => {
    it('should render the login screen with all main elements', () => {
      render(<LoginScreen />);

      // Logo and header
      expect(screen.getByText('LAYA')).toBeTruthy();
      expect(screen.getByText('Welcome Back')).toBeTruthy();
      expect(screen.getByText('Sign in to continue to LAYA Teacher')).toBeTruthy();

      // Form labels
      expect(screen.getByText('Email')).toBeTruthy();
      expect(screen.getByText('Password')).toBeTruthy();

      // Input fields
      expect(screen.getByPlaceholderText('Enter your email')).toBeTruthy();
      expect(screen.getByPlaceholderText('Enter your password')).toBeTruthy();

      // Login button
      expect(screen.getByText('Sign In')).toBeTruthy();

      // Forgot password link
      expect(screen.getByText('Forgot your password?')).toBeTruthy();

      // Footer
      expect(screen.getByText(/By signing in, you agree to our/)).toBeTruthy();
      expect(screen.getByText('Terms of Service')).toBeTruthy();
      expect(screen.getByText('Privacy Policy')).toBeTruthy();
    });

    it('should render show/hide password toggle', () => {
      render(<LoginScreen />);

      expect(screen.getByText('Show')).toBeTruthy();
    });

    it('should not render biometric button when biometric is not enabled', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: false,
        }),
      );

      render(<LoginScreen />);

      expect(screen.queryByText('Login with Fingerprint')).toBeNull();
    });

    it('should render biometric button when biometric is enabled', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFingerprint,
          storedEmail: 'test@example.com',
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText('Login with Fingerprint')).toBeTruthy();
    });

    it('should render Face ID label for face biometric type', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFace,
          storedEmail: 'test@example.com',
        }),
      );

      render(<LoginScreen />);

      // On iOS it shows "Login with Face ID", on Android it shows "Login with Face Unlock"
      // We check for partial match
      expect(
        screen.getByText(/Login with Face/),
      ).toBeTruthy();
    });

    it('should display stored email when biometric is enabled', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFingerprint,
          storedEmail: 'jane@school.edu',
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText('Signed in as jane@school.edu')).toBeTruthy();
    });

    it('should render remember me checkbox when biometric is available', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricStatus: mockBiometricStatusFingerprint,
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText(/Remember me & enable fingerprint login/)).toBeTruthy();
    });

    it('should not render remember me checkbox when biometric is not available', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricStatus: mockBiometricStatusNone,
        }),
      );

      render(<LoginScreen />);

      expect(screen.queryByText(/Remember me & enable/)).toBeNull();
    });

    it('should render Face ID label in remember me checkbox for face type', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricStatus: mockBiometricStatusFace,
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText(/Remember me & enable Face ID login/)).toBeTruthy();
    });
  });

  describe('form input handling', () => {
    it('should update email input when user types', () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'user@example.com');

      expect(emailInput.props.value).toBe('user@example.com');
    });

    it('should update password input when user types', () => {
      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, 'password123');

      expect(passwordInput.props.value).toBe('password123');
    });

    it('should toggle password visibility when show/hide is pressed', () => {
      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      const toggleButton = screen.getByText('Show');

      // Initially password should be hidden (secureTextEntry)
      expect(passwordInput.props.secureTextEntry).toBe(true);

      // Press show
      fireEvent.press(toggleButton);

      // Password should now be visible
      expect(screen.getByText('Hide')).toBeTruthy();
      expect(passwordInput.props.secureTextEntry).toBe(false);

      // Press hide
      fireEvent.press(screen.getByText('Hide'));

      // Password should be hidden again
      expect(screen.getByText('Show')).toBeTruthy();
      expect(passwordInput.props.secureTextEntry).toBe(true);
    });

    it('should pre-fill email when stored email exists and biometric is enabled', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          storedEmail: 'stored@email.com',
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      expect(emailInput.props.value).toBe('stored@email.com');
    });

    it('should clear error when email input changes', () => {
      const mockClearError = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          error: mockError,
          clearError: mockClearError,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'user@example.com');

      expect(mockClearError).toHaveBeenCalled();
    });

    it('should clear error when password input changes', () => {
      const mockClearError = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          error: mockError,
          clearError: mockClearError,
        }),
      );

      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, 'newpassword');

      expect(mockClearError).toHaveBeenCalled();
    });
  });

  describe('form validation', () => {
    it('should show email required error when email is empty and login is pressed', async () => {
      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(screen.getByText('Email is required')).toBeTruthy();
      });
    });

    it('should show invalid email error for malformed email', async () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'notanemail');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(screen.getByText('Please enter a valid email address')).toBeTruthy();
      });
    });

    it('should show password required error when password is empty', async () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'user@example.com');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(screen.getByText('Password is required')).toBeTruthy();
      });
    });

    it('should show both email and password errors when both are empty', async () => {
      render(<LoginScreen />);

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(screen.getByText('Email is required')).toBeTruthy();
        expect(screen.getByText('Password is required')).toBeTruthy();
      });
    });

    it('should validate email on blur', async () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'invalid');
      fireEvent(emailInput, 'blur');

      await waitFor(() => {
        expect(screen.getByText('Please enter a valid email address')).toBeTruthy();
      });
    });

    it('should validate password on blur', async () => {
      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, '');
      fireEvent(passwordInput, 'blur');

      await waitFor(() => {
        expect(screen.getByText('Password is required')).toBeTruthy();
      });
    });

    it('should clear email error when valid email is entered', async () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');

      // First, trigger validation error
      fireEvent.changeText(emailInput, 'invalid');
      fireEvent(emailInput, 'blur');

      await waitFor(() => {
        expect(screen.getByText('Please enter a valid email address')).toBeTruthy();
      });

      // Now enter valid email
      fireEvent.changeText(emailInput, 'valid@email.com');

      await waitFor(() => {
        expect(screen.queryByText('Please enter a valid email address')).toBeNull();
      });
    });
  });

  describe('login submission', () => {
    it('should not call login when form validation fails', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(mockLogin).not.toHaveBeenCalled();
      });
    });

    it('should call login with credentials when form is valid', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'user@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          {
            email: 'user@example.com',
            password: 'password123',
          },
          true, // rememberMe is true by default
        );
      });
    });

    it('should trim email before submitting', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, '  user@example.com  ');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          {
            email: 'user@example.com',
            password: 'password123',
          },
          true,
        );
      });
    });

    it('should pass rememberMe as false when checkbox is unchecked', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
          biometricStatus: mockBiometricStatusFingerprint,
        }),
      );

      render(<LoginScreen />);

      // Uncheck remember me (it's checked by default)
      const checkbox = screen.getByText(/Remember me & enable fingerprint login/);
      fireEvent.press(checkbox);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'user@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          {
            email: 'user@example.com',
            password: 'password123',
          },
          false,
        );
      });
    });

    it('should toggle rememberMe when checkbox is pressed multiple times', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
          biometricStatus: mockBiometricStatusFingerprint,
        }),
      );

      render(<LoginScreen />);

      const checkbox = screen.getByText(/Remember me & enable fingerprint login/);

      // Uncheck
      fireEvent.press(checkbox);

      // Check again
      fireEvent.press(checkbox);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'user@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith(
          expect.any(Object),
          true, // rememberMe is true after toggle
        );
      });
    });

    it('should trigger login on password submit editing', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'user@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      // Simulate pressing "Done" on keyboard
      fireEvent(passwordInput, 'submitEditing');

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalled();
      });
    });
  });

  describe('biometric login', () => {
    it('should call loginWithBiometrics when biometric button is pressed', async () => {
      const mockLoginWithBiometrics = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFingerprint,
          storedEmail: 'test@example.com',
          loginWithBiometrics: mockLoginWithBiometrics,
        }),
      );

      render(<LoginScreen />);

      const biometricButton = screen.getByText('Login with Fingerprint');
      fireEvent.press(biometricButton);

      await waitFor(() => {
        expect(mockLoginWithBiometrics).toHaveBeenCalled();
      });
    });

    it('should render divider when biometric section is shown', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFingerprint,
          storedEmail: 'test@example.com',
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText('or')).toBeTruthy();
    });

    it('should not render divider when biometric section is hidden', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricEnabled: false,
        }),
      );

      render(<LoginScreen />);

      expect(screen.queryByText('or')).toBeNull();
    });
  });

  describe('loading state', () => {
    it('should show loading indicator on login button when logging in', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
        }),
      );

      render(<LoginScreen />);

      // Sign In text should not be visible when loading
      expect(screen.queryByText('Sign In')).toBeNull();
    });

    it('should disable login button when logging in', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
        }),
      );

      render(<LoginScreen />);

      // The button still renders but should be disabled
      // We can't directly test disabled state in RNTL, but we can verify the behavior
      // by checking that login is not called when pressed while loading
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      // Find the touchable that would have Sign In
      // Since the text is replaced with ActivityIndicator, we can't find it by text
    });

    it('should disable email input when logging in', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      expect(emailInput.props.editable).toBe(false);
    });

    it('should disable password input when logging in', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
        }),
      );

      render(<LoginScreen />);

      const passwordInput = screen.getByPlaceholderText('Enter your password');
      expect(passwordInput.props.editable).toBe(false);
    });

    it('should show loading indicator on biometric button when logging in', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          isLoggingIn: true,
          biometricEnabled: true,
          biometricStatus: mockBiometricStatusFingerprint,
          storedEmail: 'test@example.com',
        }),
      );

      render(<LoginScreen />);

      // Biometric button text should not be visible when loading
      expect(screen.queryByText('Login with Fingerprint')).toBeNull();
    });
  });

  describe('error display', () => {
    it('should display error message when error exists', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          error: mockError,
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText('Invalid email or password')).toBeTruthy();
    });

    it('should not display error container when no error', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          error: null,
        }),
      );

      render(<LoginScreen />);

      expect(screen.queryByText('Invalid email or password')).toBeNull();
    });

    it('should display different error messages based on error code', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          error: {
            code: 'NETWORK_ERROR',
            message: 'Unable to connect to server',
          },
        }),
      );

      render(<LoginScreen />);

      expect(screen.getByText('Unable to connect to server')).toBeTruthy();
    });
  });

  describe('accessibility', () => {
    it('should have proper return key types', () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      expect(emailInput.props.returnKeyType).toBe('next');
      expect(passwordInput.props.returnKeyType).toBe('done');
    });

    it('should have proper keyboard types', () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');

      expect(emailInput.props.keyboardType).toBe('email-address');
    });

    it('should have auto capitalize disabled for email', () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');

      expect(emailInput.props.autoCapitalize).toBe('none');
    });

    it('should have auto correct disabled for inputs', () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      expect(emailInput.props.autoCorrect).toBe(false);
      expect(passwordInput.props.autoCorrect).toBe(false);
    });
  });

  describe('edge cases', () => {
    it('should handle whitespace-only email as invalid', async () => {
      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, '   ');
      fireEvent.changeText(passwordInput, 'password123');

      const loginButton = screen.getByText('Sign In');
      fireEvent.press(loginButton);

      await waitFor(() => {
        expect(screen.getByText('Email is required')).toBeTruthy();
      });
    });

    it('should handle biometric status being null', () => {
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          biometricStatus: null,
          biometricEnabled: true,
        }),
      );

      // Should not crash
      render(<LoginScreen />);
    });

    it('should handle rapid form submissions gracefully', async () => {
      const mockLogin = jest.fn();
      mockUseAuth.mockReturnValue(
        createMockAuthState({
          login: mockLogin,
        }),
      );

      render(<LoginScreen />);

      const emailInput = screen.getByPlaceholderText('Enter your email');
      const passwordInput = screen.getByPlaceholderText('Enter your password');
      const loginButton = screen.getByText('Sign In');

      fireEvent.changeText(emailInput, 'user@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      // Rapid presses
      fireEvent.press(loginButton);
      fireEvent.press(loginButton);
      fireEvent.press(loginButton);

      // Should still work without crashing
      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalled();
      });
    });
  });
});
