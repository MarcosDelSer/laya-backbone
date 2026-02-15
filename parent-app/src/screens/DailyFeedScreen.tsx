/**
 * LAYA Parent App - Daily Feed Screen
 *
 * Displays daily reports for child(ren) with meals, naps, activities, and photos.
 * Supports pull-to-refresh for real-time updates.
 *
 * This is a placeholder that will be expanded in subsequent subtasks.
 */

import React from 'react';
import {SafeAreaView, Text, StyleSheet, View} from 'react-native';

import type {DailyFeedScreenProps} from '../types/navigation';

/**
 * Daily Feed Screen - displays daily reports for children.
 */
function DailyFeedScreen(_props: DailyFeedScreenProps): React.JSX.Element {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.icon}>📋</Text>
        <Text style={styles.title}>Daily Feed</Text>
        <Text style={styles.subtitle}>
          View your child's daily activities, meals, and naps
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

export default DailyFeedScreen;
