/**
 * LAYA Parent App - Profile Screen
 *
 * Displays parent profile information, child profiles, and settings access.
 * Provides access to notification settings, account management, and logout.
 *
 * This is a placeholder that will be expanded in subsequent subtasks.
 */

import React from 'react';
import {SafeAreaView, Text, StyleSheet, View} from 'react-native';

import type {ProfileScreenProps} from '../types/navigation';

/**
 * Profile Screen - displays parent profile and settings.
 */
function ProfileScreen(_props: ProfileScreenProps): React.JSX.Element {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.icon}>👤</Text>
        <Text style={styles.title}>Profile</Text>
        <Text style={styles.subtitle}>
          Manage your account and settings
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

export default ProfileScreen;
