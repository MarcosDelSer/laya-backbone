/**
 * LAYA Parent App - Messages Screen
 *
 * Displays messaging interface with thread list and message detail view.
 * Parents can view message threads, read messages, and compose/send new messages.
 *
 * This is a placeholder that will be expanded in subsequent subtasks.
 */

import React from 'react';
import {SafeAreaView, Text, StyleSheet, View} from 'react-native';

import type {MessagesScreenProps} from '../types/navigation';

/**
 * Messages Screen - displays messaging interface.
 */
function MessagesScreen(_props: MessagesScreenProps): React.JSX.Element {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.icon}>💬</Text>
        <Text style={styles.title}>Messages</Text>
        <Text style={styles.subtitle}>
          Communicate with your child's teachers
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

export default MessagesScreen;
