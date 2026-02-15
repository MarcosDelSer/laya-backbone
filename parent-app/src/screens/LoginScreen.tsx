/**
 * LAYA Parent App - LoginScreen
 *
 * Authentication screen with login form and biometric support.
 * Supports email/password login and fingerprint/face authentication
 * on supported devices.
 *
 * Follows pattern from:
 * - DailyFeedScreen.tsx for screen structure
 * - spec.md requirements for authentication features
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Keyboard,
  TouchableWithoutFeedback,
  Image,
} from 'react-native';
import {useAuth} from '../hooks/useAuth';

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  primaryDark: '#3A7BC8',
  background: '#F5F5F5',
  cardBackground: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  error: '#D32F2F',
  errorBackground: '#FFEBEE',
  success: '#4CAF50',
  inputBackground: '#FAFAFA',
};

/**
 * Biometric icon based on platform and type
 */
function BiometricIcon({type}: {type: 'fingerprint' | 'face' | 'iris' | 'none'}): React.JSX.Element {
  const icons: Record<string, string> = {
    fingerprint: '???',
    face: '????',
    iris: '????',
    none: '????',
  };

  return (
    <Text style={styles.biometricIcon}>{icons[type] || icons.fingerprint}</Text>
  );
}

/**
 * LoginScreen provides authentication UI with form and biometric options
 */
function LoginScreen(): React.JSX.Element {
  const {
    isLoggingIn,
    error,
    biometricStatus,
    biometricEnabled,
    storedEmail,
    login,
    loginWithBiometrics,
    clearError,
  } = useAuth();

  // Form state
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(true);
  const [showPassword, setShowPassword] = useState(false);

  // Validation state
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  /**
   * Pre-fill email if biometric login is available
   */
  useEffect(() => {
    if (storedEmail && biometricEnabled) {
      setEmail(storedEmail);
    }
  }, [storedEmail, biometricEnabled]);

  /**
   * Validate email format
   */
  const validateEmail = useCallback((value: string): boolean => {
    if (!value.trim()) {
      setEmailError('Email is required');
      return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
      setEmailError('Please enter a valid email address');
      return false;
    }

    setEmailError(null);
    return true;
  }, []);

  /**
   * Validate password
   */
  const validatePassword = useCallback((value: string): boolean => {
    if (!value) {
      setPasswordError('Password is required');
      return false;
    }

    setPasswordError(null);
    return true;
  }, []);

  /**
   * Handle email change
   */
  const handleEmailChange = useCallback(
    (value: string) => {
      setEmail(value);
      if (emailError) {
        validateEmail(value);
      }
      if (error) {
        clearError();
      }
    },
    [emailError, error, validateEmail, clearError],
  );

  /**
   * Handle password change
   */
  const handlePasswordChange = useCallback(
    (value: string) => {
      setPassword(value);
      if (passwordError) {
        validatePassword(value);
      }
      if (error) {
        clearError();
      }
    },
    [passwordError, error, validatePassword, clearError],
  );

  /**
   * Handle login form submission
   */
  const handleLogin = useCallback(async () => {
    Keyboard.dismiss();

    const isEmailValid = validateEmail(email);
    const isPasswordValid = validatePassword(password);

    if (!isEmailValid || !isPasswordValid) {
      return;
    }

    await login({
      email: email.trim(),
      password,
      rememberMe,
    });
  }, [email, password, rememberMe, validateEmail, validatePassword, login]);

  /**
   * Handle biometric login
   */
  const handleBiometricLogin = useCallback(async () => {
    await loginWithBiometrics();
  }, [loginWithBiometrics]);

  /**
   * Toggle remember me checkbox
   */
  const toggleRememberMe = useCallback(() => {
    setRememberMe(prev => !prev);
  }, []);

  /**
   * Toggle password visibility
   */
  const toggleShowPassword = useCallback(() => {
    setShowPassword(prev => !prev);
  }, []);

  /**
   * Get biometric button label
   */
  const getBiometricLabel = (): string => {
    if (!biometricStatus) return 'Login with Biometrics';

    switch (biometricStatus.biometricType) {
      case 'fingerprint':
        return 'Login with Fingerprint';
      case 'face':
        return Platform.OS === 'ios' ? 'Login with Face ID' : 'Login with Face Unlock';
      case 'iris':
        return 'Login with Iris Scan';
      default:
        return 'Login with Biometrics';
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 20}>
      <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}>
          {/* Logo and Header */}
          <View style={styles.header}>
            <View style={styles.logoContainer}>
              <Text style={styles.logoText}>LAYA</Text>
            </View>
            <Text style={styles.title}>Welcome Back</Text>
            <Text style={styles.subtitle}>Sign in to continue to LAYA Parent</Text>
          </View>

          {/* Biometric Login Button */}
          {biometricEnabled && biometricStatus?.isAvailable && (
            <View style={styles.biometricSection}>
              <TouchableOpacity
                style={styles.biometricButton}
                onPress={handleBiometricLogin}
                disabled={isLoggingIn}
                activeOpacity={0.7}>
                {isLoggingIn ? (
                  <ActivityIndicator size="small" color={COLORS.primary} />
                ) : (
                  <>
                    <BiometricIcon type={biometricStatus.biometricType} />
                    <Text style={styles.biometricButtonText}>{getBiometricLabel()}</Text>
                  </>
                )}
              </TouchableOpacity>
              {storedEmail && (
                <Text style={styles.storedEmailText}>
                  Signed in as {storedEmail}
                </Text>
              )}
              <View style={styles.dividerContainer}>
                <View style={styles.divider} />
                <Text style={styles.dividerText}>or</Text>
                <View style={styles.divider} />
              </View>
            </View>
          )}

          {/* Login Form */}
          <View style={styles.form}>
            {/* Email Input */}
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>Email</Text>
              <View
                style={[
                  styles.inputWrapper,
                  emailError && styles.inputWrapperError,
                ]}>
                <TextInput
                  style={styles.input}
                  placeholder="Enter your email"
                  placeholderTextColor={COLORS.textLight}
                  value={email}
                  onChangeText={handleEmailChange}
                  onBlur={() => validateEmail(email)}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoComplete="email"
                  editable={!isLoggingIn}
                  returnKeyType="next"
                />
              </View>
              {emailError && <Text style={styles.inputError}>{emailError}</Text>}
            </View>

            {/* Password Input */}
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>Password</Text>
              <View
                style={[
                  styles.inputWrapper,
                  passwordError && styles.inputWrapperError,
                ]}>
                <TextInput
                  style={[styles.input, styles.passwordInput]}
                  placeholder="Enter your password"
                  placeholderTextColor={COLORS.textLight}
                  value={password}
                  onChangeText={handlePasswordChange}
                  onBlur={() => validatePassword(password)}
                  secureTextEntry={!showPassword}
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoComplete="password"
                  editable={!isLoggingIn}
                  returnKeyType="done"
                  onSubmitEditing={handleLogin}
                />
                <TouchableOpacity
                  style={styles.showPasswordButton}
                  onPress={toggleShowPassword}
                  hitSlop={{top: 10, bottom: 10, left: 10, right: 10}}>
                  <Text style={styles.showPasswordText}>
                    {showPassword ? 'Hide' : 'Show'}
                  </Text>
                </TouchableOpacity>
              </View>
              {passwordError && <Text style={styles.inputError}>{passwordError}</Text>}
            </View>

            {/* Remember Me & Biometric Checkbox */}
            {biometricStatus?.isAvailable && (
              <TouchableOpacity
                style={styles.checkboxContainer}
                onPress={toggleRememberMe}
                activeOpacity={0.7}>
                <View style={[styles.checkbox, rememberMe && styles.checkboxChecked]}>
                  {rememberMe && <Text style={styles.checkmark}>???</Text>}
                </View>
                <Text style={styles.checkboxLabel}>
                  Remember me & enable {biometricStatus.biometricType === 'face' ? 'Face ID' : 'fingerprint'} login
                </Text>
              </TouchableOpacity>
            )}

            {/* Error Message */}
            {error && (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error.message}</Text>
              </View>
            )}

            {/* Login Button */}
            <TouchableOpacity
              style={[styles.loginButton, isLoggingIn && styles.loginButtonDisabled]}
              onPress={handleLogin}
              disabled={isLoggingIn}
              activeOpacity={0.8}>
              {isLoggingIn ? (
                <ActivityIndicator size="small" color={COLORS.cardBackground} />
              ) : (
                <Text style={styles.loginButtonText}>Sign In</Text>
              )}
            </TouchableOpacity>

            {/* Forgot Password Link */}
            <TouchableOpacity
              style={styles.forgotPasswordContainer}
              disabled={isLoggingIn}
              activeOpacity={0.7}>
              <Text style={styles.forgotPasswordText}>Forgot your password?</Text>
            </TouchableOpacity>
          </View>

          {/* Footer */}
          <View style={styles.footer}>
            <Text style={styles.footerText}>
              By signing in, you agree to our{' '}
              <Text style={styles.footerLink}>Terms of Service</Text>
              {' '}and{' '}
              <Text style={styles.footerLink}>Privacy Policy</Text>
            </Text>
          </View>
        </ScrollView>
      </TouchableWithoutFeedback>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingTop: Platform.OS === 'ios' ? 60 : 40,
    paddingBottom: 40,
  },
  header: {
    alignItems: 'center',
    marginBottom: 32,
  },
  logoContainer: {
    width: 80,
    height: 80,
    borderRadius: 20,
    backgroundColor: COLORS.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
    ...Platform.select({
      ios: {
        shadowColor: COLORS.primary,
        shadowOffset: {width: 0, height: 4},
        shadowOpacity: 0.3,
        shadowRadius: 8,
      },
      android: {
        elevation: 8,
      },
    }),
  },
  logoText: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.cardBackground,
    letterSpacing: 2,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: COLORS.textSecondary,
  },
  biometricSection: {
    marginBottom: 24,
  },
  biometricButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.cardBackground,
    borderRadius: 12,
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderWidth: 1,
    borderColor: COLORS.primary,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: {width: 0, height: 2},
        shadowOpacity: 0.1,
        shadowRadius: 4,
      },
      android: {
        elevation: 2,
      },
    }),
  },
  biometricIcon: {
    fontSize: 24,
    marginRight: 12,
  },
  biometricButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.primary,
  },
  storedEmailText: {
    fontSize: 13,
    color: COLORS.textSecondary,
    textAlign: 'center',
    marginTop: 8,
  },
  dividerContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 24,
  },
  divider: {
    flex: 1,
    height: 1,
    backgroundColor: COLORS.border,
  },
  dividerText: {
    paddingHorizontal: 16,
    fontSize: 14,
    color: COLORS.textLight,
  },
  form: {
    marginBottom: 24,
  },
  inputContainer: {
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.inputBackground,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    overflow: 'hidden',
  },
  inputWrapperError: {
    borderColor: COLORS.error,
    backgroundColor: COLORS.errorBackground,
  },
  input: {
    flex: 1,
    paddingVertical: Platform.OS === 'ios' ? 16 : 12,
    paddingHorizontal: 16,
    fontSize: 16,
    color: COLORS.text,
  },
  passwordInput: {
    paddingRight: 60,
  },
  showPasswordButton: {
    position: 'absolute',
    right: 16,
    paddingVertical: 8,
  },
  showPasswordText: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.primary,
  },
  inputError: {
    fontSize: 12,
    color: COLORS.error,
    marginTop: 6,
    marginLeft: 4,
  },
  checkboxContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 24,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: COLORS.border,
    marginRight: 12,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.cardBackground,
  },
  checkboxChecked: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  checkmark: {
    fontSize: 14,
    color: COLORS.cardBackground,
    fontWeight: '700',
  },
  checkboxLabel: {
    fontSize: 14,
    color: COLORS.textSecondary,
    flex: 1,
  },
  errorContainer: {
    backgroundColor: COLORS.errorBackground,
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 16,
    marginBottom: 20,
    borderLeftWidth: 4,
    borderLeftColor: COLORS.error,
  },
  errorText: {
    fontSize: 14,
    color: COLORS.error,
    lineHeight: 20,
  },
  loginButton: {
    backgroundColor: COLORS.primary,
    borderRadius: 12,
    paddingVertical: 16,
    alignItems: 'center',
    justifyContent: 'center',
    ...Platform.select({
      ios: {
        shadowColor: COLORS.primary,
        shadowOffset: {width: 0, height: 4},
        shadowOpacity: 0.3,
        shadowRadius: 8,
      },
      android: {
        elevation: 4,
      },
    }),
  },
  loginButtonDisabled: {
    backgroundColor: COLORS.textLight,
    ...Platform.select({
      ios: {
        shadowOpacity: 0,
      },
      android: {
        elevation: 0,
      },
    }),
  },
  loginButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.cardBackground,
  },
  forgotPasswordContainer: {
    alignItems: 'center',
    marginTop: 20,
  },
  forgotPasswordText: {
    fontSize: 14,
    color: COLORS.primary,
    fontWeight: '500',
  },
  footer: {
    marginTop: 'auto',
    paddingTop: 24,
  },
  footerText: {
    fontSize: 12,
    color: COLORS.textLight,
    textAlign: 'center',
    lineHeight: 18,
  },
  footerLink: {
    color: COLORS.primary,
    fontWeight: '500',
  },
});

export default LoginScreen;
