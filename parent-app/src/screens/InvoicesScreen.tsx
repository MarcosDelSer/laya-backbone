/**
 * LAYA Parent App - Invoices Screen
 *
 * Displays invoice listing with status badges and summary statistics.
 * Parents can view invoices, see payment status, and access PDF downloads.
 *
 * This is a placeholder that will be expanded in subsequent subtasks.
 */

import React from 'react';
import {SafeAreaView, Text, StyleSheet, View} from 'react-native';

import type {InvoicesScreenProps} from '../types/navigation';

/**
 * Invoices Screen - displays invoice management interface.
 */
function InvoicesScreen(_props: InvoicesScreenProps): React.JSX.Element {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.icon}>📄</Text>
        <Text style={styles.title}>Invoices</Text>
        <Text style={styles.subtitle}>
          View and manage your invoices and payments
        </Text>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  icon: {
    fontSize: 48,
    marginBottom: 16,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
});

export default InvoicesScreen;
