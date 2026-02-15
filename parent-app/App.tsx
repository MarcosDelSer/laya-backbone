/**
 * LAYA Parent App - Main Entry Point
 *
 * React Native iOS application for parents to view their children's
 * daily reports, photos, messages, and invoices.
 *
 * This is a placeholder that will be expanded in subsequent subtasks.
 */

import React from 'react';
import {SafeAreaView, Text, StyleSheet} from 'react-native';

/**
 * Main App component - placeholder for initial project setup
 *
 * This will be replaced with proper navigation and screens
 * in subsequent implementation phases.
 */
function App(): React.JSX.Element {
  return (
    <SafeAreaView style={styles.container}>
      <Text style={styles.title}>LAYA Parent App</Text>
      <Text style={styles.subtitle}>Loading...</Text>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  subtitle: {
    fontSize: 16,
    color: '#666',
    marginTop: 8,
  },
});

export default App;
