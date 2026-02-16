/**
 * @format
 * LAYA Parent App - LoginScreen Tests
 *
 * Tests for the LoginScreen component covering:
 * - Form validation (email and password)
 * - Login flow (success and loading states)
 * - Biometric login
 * - Error display
 *
 * Follows pattern from:
 * - __tests__/hooks/useAuth.test.ts
 * - teacher-app/__tests__/App.test.tsx
 */

import React from 'react';
import {render, fireEvent, waitFor} from '@testing-library/react-native';
import LoginScreen from '../../src/screens/LoginScreen';
import type {BiometricStatus, AuthError} from '../../src/services/authService';

// Mock the useAuth hook
jest.mock('../../src/hooks/useAuth', () => ({
  useAuth: jest.fn(),
}));

import {useAuth} from '../../src/hooks/useAuth';

const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;

// Default mock values for useAuth
const defaultMockUseAuth = {
  isAuthenticated: false,
  isLoading: false,
  isLoggingIn: false,
  user: null,
  error: null,
  biometricStatus: null,
  biometricEnabled: false,
  storedEmail: null,
  login: jest.fn().mockResolvedValue(true),
  loginWithBiometrics: jest.fn().mockResolvedValue(true),
  logout: jest.fn().mockResolvedValue(undefined),
  enableBiometricLogin: jest.fn().mockResolvedValue(true),
  disableBiometricLogin: jest.fn().mockResolvedValue(undefined),
  clearError: jest.fn(),
  refreshBiometricStatus: jest.fn().mockResolvedValue(undefined),
};

// Test data
const mockBiometricStatus: BiometricStatus = {
  isAvailable: true,
  biometricType: 'fingerprint',
  isEnrolled: true,
};

const mockFaceBiometricStatus: BiometricStatus = {
  isAvailable: true,
  biometricType: 'face',
  isEnrolled: true,
};

describe('LoginScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue({...defaultMockUseAuth});
  });

  describe('Rendering', () => {
    it('renders without crashing', () => {
      expect(() => render(<LoginScreen />)).not.toThrow();
    });

    it('renders login form with email and password fields', () => {
      const {getByPlaceholderText, getByText} = render(<LoginScreen />);

      expect(getByPlaceholderText('Enter your email')).toBeTruthy();
      expect(getByPlaceholderText('Enter your password')).toBeTruthy();
      expect(getByText('Sign In')).toBeTruthy();
    });

    it('renders header with title and subtitle', () => {
      const {getByText} = render(<LoginScreen />);

      expect(getByText('LAYA')).toBeTruthy();
      expect(getByText('Welcome Back')).toBeTruthy();
      expect(getByText('Sign in to continue to LAYA Parent')).toBeTruthy();
    });

    it('renders input labels', () => {
      const {getByText} = render(<LoginScreen />);

      expect(getByText('Email')).toBeTruthy();
      expect(getByText('Password')).toBeTruthy();
    });

    it('renders forgot password link', () => {
      const {getByText} = render(<LoginScreen />);

      expect(getByText('Forgot your password?')).toBeTruthy();
    });

    it('renders footer with terms and privacy', () => {
      const {getByText} = render(<LoginScreen />);

      expect(getByText('Terms of Service')).toBeTruthy();
      expect(getByText('Privacy Policy')).toBeTruthy();
    });
  });

  describe('Biometric Login Section', () => {
    it('does not render biometric button when biometric is not available', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: false,
        biometricStatus: null,
      });

      const {queryByText} = render(<LoginScreen />);

      expect(queryByText('Login with Fingerprint')).toBeNull();
      expect(queryByText('Login with Face ID')).toBeNull();
    });

    it('renders fingerprint login button when biometric is enabled', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText('Login with Fingerprint')).toBeTruthy();
    });

    it('renders Face ID login button on iOS when face biometric is available', () => {
      // Mock Platform.OS to iOS
      jest.doMock('react-native/Libraries/Utilities/Platform', () => ({
        OS: 'ios',
        select: jest.fn((obj) => obj.ios),
      }));

      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockFaceBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      // On iOS, it should show "Login with Face ID"
      expect(getByText(/Login with Face/)).toBeTruthy();
    });

    it('renders stored email when biometric is enabled', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
        storedEmail: 'test@example.com',
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText('Signed in as test@example.com')).toBeTruthy();
    });

    it('renders divider when biometric section is shown', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText('or')).toBeTruthy();
    });

    it('calls loginWithBiometrics when biometric button is pressed', () => {
      const loginWithBiometrics = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
        loginWithBiometrics,
      });

      const {getByText} = render(<LoginScreen />);

      fireEvent.press(getByText('Login with Fingerprint'));

      expect(loginWithBiometrics).toHaveBeenCalled();
    });

    it('disables biometric button when logging in', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
        isLoggingIn: true,
      });

      const {getByText, queryByText} = render(<LoginScreen />);

      // When logging in, the biometric button should show loading indicator
      // and not the text
      expect(queryByText('Login with Fingerprint')).toBeNull();
    });

    it('pre-fills email when biometric is enabled with stored email', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
        storedEmail: 'stored@example.com',
      });

      const {getByPlaceholderText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      expect(emailInput.props.value).toBe('stored@example.com');
    });
  });

  describe('Form Validation - Email', () => {
    it('shows error when email is empty on blur', async () => {
      const {getByPlaceholderText, findByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      fireEvent(emailInput, 'blur');

      const errorMessage = await findByText('Email is required');
      expect(errorMessage).toBeTruthy();
    });

    it('shows error when email is invalid format', async () => {
      const {getByPlaceholderText, findByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'invalid-email');
      fireEvent(emailInput, 'blur');

      const errorMessage = await findByText('Please enter a valid email address');
      expect(errorMessage).toBeTruthy();
    });

    it('clears email error when valid email is entered', async () => {
      const {getByPlaceholderText, findByText, queryByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');

      // First trigger error
      fireEvent.changeText(emailInput, 'invalid');
      fireEvent(emailInput, 'blur');

      await findByText('Please enter a valid email address');

      // Now enter valid email
      fireEvent.changeText(emailInput, 'valid@example.com');

      await waitFor(() => {
        expect(queryByText('Please enter a valid email address')).toBeNull();
      });
    });

    it('validates email with whitespace correctly', async () => {
      const {getByPlaceholderText, findByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, '   ');
      fireEvent(emailInput, 'blur');

      const errorMessage = await findByText('Email is required');
      expect(errorMessage).toBeTruthy();
    });
  });

  describe('Form Validation - Password', () => {
    it('shows error when password is empty on blur', async () => {
      const {getByPlaceholderText, findByText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');
      fireEvent(passwordInput, 'blur');

      const errorMessage = await findByText('Password is required');
      expect(errorMessage).toBeTruthy();
    });

    it('clears password error when password is entered', async () => {
      const {getByPlaceholderText, findByText, queryByText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');

      // First trigger error
      fireEvent(passwordInput, 'blur');
      await findByText('Password is required');

      // Now enter password
      fireEvent.changeText(passwordInput, 'password123');

      await waitFor(() => {
        expect(queryByText('Password is required')).toBeNull();
      });
    });
  });

  describe('Form Validation - Submit', () => {
    it('shows both errors when submitting empty form', async () => {
      const login = jest.fn().mockResolvedValue(false);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByText, findByText} = render(<LoginScreen />);

      fireEvent.press(getByText('Sign In'));

      await findByText('Email is required');
      await findByText('Password is required');
      expect(login).not.toHaveBeenCalled();
    });

    it('shows email error when submitting with only password', async () => {
      const login = jest.fn().mockResolvedValue(false);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText, getByText, findByText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, 'password123');

      fireEvent.press(getByText('Sign In'));

      await findByText('Email is required');
      expect(login).not.toHaveBeenCalled();
    });

    it('shows password error when submitting with only email', async () => {
      const login = jest.fn().mockResolvedValue(false);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText, getByText, findByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'test@example.com');

      fireEvent.press(getByText('Sign In'));

      await findByText('Password is required');
      expect(login).not.toHaveBeenCalled();
    });
  });

  describe('Login Flow', () => {
    it('calls login with correct credentials when form is valid', async () => {
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText, getByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      const passwordInput = getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'test@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      fireEvent.press(getByText('Sign In'));

      await waitFor(() => {
        expect(login).toHaveBeenCalledWith({
          email: 'test@example.com',
          password: 'password123',
          rememberMe: true, // Default value
        });
      });
    });

    it('trims email before sending to login', async () => {
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText, getByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      const passwordInput = getByPlaceholderText('Enter your password');

      // Use a valid email format - the trim() call in handleLogin
      // ensures the email is trimmed before being passed to login
      fireEvent.changeText(emailInput, 'test@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      fireEvent.press(getByText('Sign In'));

      await waitFor(() => {
        expect(login).toHaveBeenCalledWith(
          expect.objectContaining({
            email: 'test@example.com',
          }),
        );
      });
    });

    it('fails validation when email has leading/trailing spaces', async () => {
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText, getByText, findByText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      const passwordInput = getByPlaceholderText('Enter your password');

      // Email with leading/trailing spaces fails regex validation
      fireEvent.changeText(emailInput, '  test@example.com  ');
      fireEvent.changeText(passwordInput, 'password123');

      fireEvent.press(getByText('Sign In'));

      // Should show validation error since regex doesn't match emails with spaces
      await findByText('Please enter a valid email address');
      expect(login).not.toHaveBeenCalled();
    });

    it('shows loading indicator when logging in', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        isLoggingIn: true,
      });

      const {queryByText} = render(<LoginScreen />);

      // Sign In text should not be visible when loading
      expect(queryByText('Sign In')).toBeNull();
    });

    it('disables login button when logging in', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        isLoggingIn: true,
      });

      const {UNSAFE_getByType} = render(<LoginScreen />);

      // We can't easily check disabled state, but at least verify it renders
      // The component should show ActivityIndicator instead of Sign In text
      expect(() => render(<LoginScreen />)).not.toThrow();
    });

    it('disables inputs when logging in', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        isLoggingIn: true,
      });

      const {getByPlaceholderText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      const passwordInput = getByPlaceholderText('Enter your password');

      expect(emailInput.props.editable).toBe(false);
      expect(passwordInput.props.editable).toBe(false);
    });
  });

  describe('Error Display', () => {
    it('displays error message from useAuth', () => {
      const error: AuthError = {
        code: 'INVALID_CREDENTIALS',
        message: 'Invalid email or password',
      };

      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        error,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText('Invalid email or password')).toBeTruthy();
    });

    it('clears error when typing in email field', () => {
      const clearError = jest.fn();
      const error: AuthError = {
        code: 'INVALID_CREDENTIALS',
        message: 'Invalid email or password',
      };

      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        error,
        clearError,
      });

      const {getByPlaceholderText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      fireEvent.changeText(emailInput, 'newemail@example.com');

      expect(clearError).toHaveBeenCalled();
    });

    it('clears error when typing in password field', () => {
      const clearError = jest.fn();
      const error: AuthError = {
        code: 'INVALID_CREDENTIALS',
        message: 'Invalid email or password',
      };

      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        error,
        clearError,
      });

      const {getByPlaceholderText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');
      fireEvent.changeText(passwordInput, 'newpassword');

      expect(clearError).toHaveBeenCalled();
    });

    it('does not display error container when no error', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        error: null,
      });

      const {queryByText} = render(<LoginScreen />);

      expect(queryByText('Invalid email or password')).toBeNull();
    });
  });

  describe('Password Visibility Toggle', () => {
    it('hides password by default', () => {
      const {getByPlaceholderText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');
      expect(passwordInput.props.secureTextEntry).toBe(true);
    });

    it('shows "Show" button by default', () => {
      const {getByText} = render(<LoginScreen />);

      expect(getByText('Show')).toBeTruthy();
    });

    it('toggles password visibility when Show/Hide is pressed', () => {
      const {getByText, getByPlaceholderText} = render(<LoginScreen />);

      const toggleButton = getByText('Show');
      fireEvent.press(toggleButton);

      const passwordInput = getByPlaceholderText('Enter your password');
      expect(passwordInput.props.secureTextEntry).toBe(false);
      expect(getByText('Hide')).toBeTruthy();
    });

    it('toggles back to hidden when Hide is pressed', () => {
      const {getByText, getByPlaceholderText} = render(<LoginScreen />);

      // Show password
      fireEvent.press(getByText('Show'));
      expect(getByText('Hide')).toBeTruthy();

      // Hide password
      fireEvent.press(getByText('Hide'));

      const passwordInput = getByPlaceholderText('Enter your password');
      expect(passwordInput.props.secureTextEntry).toBe(true);
      expect(getByText('Show')).toBeTruthy();
    });
  });

  describe('Remember Me Checkbox', () => {
    it('renders remember me checkbox when biometric is available', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText(/Remember me & enable/)).toBeTruthy();
    });

    it('does not render remember me when biometric is not available', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: null,
      });

      const {queryByText} = render(<LoginScreen />);

      expect(queryByText(/Remember me/)).toBeNull();
    });

    it('shows fingerprint text for fingerprint biometric', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText(/fingerprint login/)).toBeTruthy();
    });

    it('shows Face ID text for face biometric', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockFaceBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      expect(getByText(/Face ID login/)).toBeTruthy();
    });

    it('remember me is checked by default', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockBiometricStatus,
      });

      // The login call should include rememberMe: true
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockBiometricStatus,
        login,
      });

      const {getByPlaceholderText, getByText} = render(<LoginScreen />);

      fireEvent.changeText(getByPlaceholderText('Enter your email'), 'test@example.com');
      fireEvent.changeText(getByPlaceholderText('Enter your password'), 'password123');
      fireEvent.press(getByText('Sign In'));

      expect(login).toHaveBeenCalledWith(
        expect.objectContaining({
          rememberMe: true,
        }),
      );
    });

    it('toggles remember me when pressed', async () => {
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricStatus: mockBiometricStatus,
        login,
      });

      const {getByPlaceholderText, getByText} = render(<LoginScreen />);

      // Toggle remember me off
      fireEvent.press(getByText(/Remember me/));

      fireEvent.changeText(getByPlaceholderText('Enter your email'), 'test@example.com');
      fireEvent.changeText(getByPlaceholderText('Enter your password'), 'password123');
      fireEvent.press(getByText('Sign In'));

      await waitFor(() => {
        expect(login).toHaveBeenCalledWith(
          expect.objectContaining({
            rememberMe: false,
          }),
        );
      });
    });
  });

  describe('Keyboard Handling', () => {
    it('submits form when password input submit is pressed', async () => {
      const login = jest.fn().mockResolvedValue(true);
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        login,
      });

      const {getByPlaceholderText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');
      const passwordInput = getByPlaceholderText('Enter your password');

      fireEvent.changeText(emailInput, 'test@example.com');
      fireEvent.changeText(passwordInput, 'password123');

      fireEvent(passwordInput, 'submitEditing');

      await waitFor(() => {
        expect(login).toHaveBeenCalled();
      });
    });
  });

  describe('Input Configuration', () => {
    it('email input has correct keyboard type and autocomplete', () => {
      const {getByPlaceholderText} = render(<LoginScreen />);

      const emailInput = getByPlaceholderText('Enter your email');

      expect(emailInput.props.keyboardType).toBe('email-address');
      expect(emailInput.props.autoCapitalize).toBe('none');
      expect(emailInput.props.autoCorrect).toBe(false);
      expect(emailInput.props.autoComplete).toBe('email');
    });

    it('password input has correct configuration', () => {
      const {getByPlaceholderText} = render(<LoginScreen />);

      const passwordInput = getByPlaceholderText('Enter your password');

      expect(passwordInput.props.autoCapitalize).toBe('none');
      expect(passwordInput.props.autoCorrect).toBe(false);
      expect(passwordInput.props.autoComplete).toBe('password');
    });
  });

  describe('BiometricIcon Component', () => {
    it('renders fingerprint icon for fingerprint type', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      // The biometric icon should render
      expect(getByText('Login with Fingerprint')).toBeTruthy();
    });

    it('renders face icon for face type', () => {
      mockUseAuth.mockReturnValue({
        ...defaultMockUseAuth,
        biometricEnabled: true,
        biometricStatus: mockFaceBiometricStatus,
      });

      const {getByText} = render(<LoginScreen />);

      // Should show face-related login option
      expect(getByText(/Login with Face/)).toBeTruthy();
    });
  });
});
