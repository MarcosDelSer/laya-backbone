/**
 * LAYA Teacher App - Main Entry Point
 *
 * React Native iOS application for teachers/educators to perform
 * daily childcare tracking activities including attendance, meals,
 * naps, diapers, and photo capture.
 */

import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {StyleSheet, Text, View} from 'react-native';

import type {RootStackParamList} from './src/types';

// Import real screens
import AttendanceScreen from './src/screens/AttendanceScreen';
import MealLoggingScreen from './src/screens/MealLoggingScreen';
import NapTrackingScreen from './src/screens/NapTrackingScreen';
import DiaperTrackingScreen from './src/screens/DiaperTrackingScreen';

// Placeholder screens - will be replaced in subsequent subtasks

function PhotoCaptureScreen(): React.JSX.Element {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Photo Capture</Text>
      <Text style={styles.subtitle}>Capture and tag photos</Text>
    </View>
  );
}

const Stack = createNativeStackNavigator<RootStackParamList>();

function App(): React.JSX.Element {
  return (
    <SafeAreaProvider>
      <NavigationContainer>
        <Stack.Navigator
          initialRouteName="Attendance"
          screenOptions={{
            headerStyle: {
              backgroundColor: '#4A90D9',
            },
            headerTintColor: '#FFFFFF',
            headerTitleStyle: {
              fontWeight: '600',
            },
          }}>
          <Stack.Screen
            name="Attendance"
            component={AttendanceScreen}
            options={{title: 'Attendance'}}
          />
          <Stack.Screen
            name="MealLogging"
            component={MealLoggingScreen}
            options={{title: 'Meal Logging'}}
          />
          <Stack.Screen
            name="NapTracking"
            component={NapTrackingScreen}
            options={{title: 'Nap Tracking'}}
          />
          <Stack.Screen
            name="DiaperTracking"
            component={DiaperTrackingScreen}
            options={{title: 'Diaper Tracking'}}
          />
          <Stack.Screen
            name="PhotoCapture"
            component={PhotoCaptureScreen}
            options={{title: 'Photo Capture'}}
          />
        </Stack.Navigator>
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#666666',
  },
});

export default App;
