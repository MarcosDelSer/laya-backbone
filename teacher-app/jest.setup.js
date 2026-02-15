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

// Silence the warning: Animated: `useNativeDriver` is not supported
jest.mock('react-native/Libraries/Animated/NativeAnimatedHelper');
