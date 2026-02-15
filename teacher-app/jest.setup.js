/**
 * Jest setup file for React Native testing
 */

// Mock react-native-safe-area-context
jest.mock('react-native-safe-area-context', () => {
  const inset = {top: 0, right: 0, bottom: 0, left: 0};
  return {
    SafeAreaProvider: ({children}) => children,
    SafeAreaConsumer: ({children}) => children(inset),
    SafeAreaView: ({children}) => children,
    useSafeAreaInsets: () => inset,
    useSafeAreaFrame: () => ({x: 0, y: 0, width: 390, height: 844}),
  };
});

// Mock @react-navigation
jest.mock('@react-navigation/native', () => {
  const actualNav = jest.requireActual('@react-navigation/native');
  return {
    ...actualNav,
    useNavigation: () => ({
      navigate: jest.fn(),
      goBack: jest.fn(),
    }),
    useRoute: () => ({
      params: {},
    }),
  };
});

// Mock react-native-screens
jest.mock('react-native-screens', () => ({
  enableScreens: jest.fn(),
}));

// Mock @react-navigation/bottom-tabs
jest.mock('@react-navigation/bottom-tabs', () => {
  const React = require('react');
  return {
    createBottomTabNavigator: () => ({
      Navigator: ({children}) => React.createElement(React.Fragment, null, children),
      Screen: () => null,
    }),
  };
});

// Mock the useNotifications hook
jest.mock('./src/hooks/useNotifications', () => ({
  useNotifications: () => [
    {
      isSupported: true,
      isRegistered: false,
      isLoading: false,
      token: null,
      error: null,
    },
    {
      register: jest.fn(),
      unregister: jest.fn(),
      requestPermission: jest.fn(),
      sendNotification: jest.fn(),
    },
  ],
}));

// Mock the push notifications service
jest.mock('./src/services/pushNotifications', () => ({
  PushNotificationService: {
    requestPermission: jest.fn(),
    getToken: jest.fn(),
    unregister: jest.fn(),
    onNotificationReceived: jest.fn(),
    sendLocalNotification: jest.fn(),
  },
}));

// Silence the warning: Animated: `useNativeDriver` is not supported
// Note: In React Native 0.78+, NativeAnimatedHelper is handled internally
// and doesn't require mocking. The warning is suppressed by default.
