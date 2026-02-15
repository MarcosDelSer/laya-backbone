/**
 * LAYA Parent App - Nap Card Component
 *
 * Displays nap entry information including start/end time,
 * calculated duration, and sleep quality.
 *
 * Adapted from parent-portal/components/NapEntry.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {NapEntry, NapQuality} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface NapCardProps {
  nap: NapEntry;
}

// ============================================================================
// Configuration
// ============================================================================

interface QualityConfig {
  label: string;
  badgeBackgroundColor: string;
  badgeTextColor: string;
}

const qualityConfig: Record<NapQuality, QualityConfig> = {
  good: {
    label: 'Good sleep',
    badgeBackgroundColor: '#DEF7EC',
    badgeTextColor: '#03543F',
  },
  fair: {
    label: 'Fair sleep',
    badgeBackgroundColor: '#FDF6B2',
    badgeTextColor: '#723B13',
  },
  poor: {
    label: 'Poor sleep',
    badgeBackgroundColor: '#F3F4F6',
    badgeTextColor: '#4B5563',
  },
};

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Calculate the duration between start and end times.
 * Handles both HH:MM format and AM/PM format.
 */
function calculateDuration(startTime: string, endTime: string): string {
  const parseTime = (time: string): number => {
    const normalized = time.toLowerCase();
    const isPM = normalized.includes('pm');
    const isAM = normalized.includes('am');
    const timePart = normalized.replace(/[ap]m/i, '').trim();

    const [hours, minutes] = timePart.split(':').map(Number);
    let hour24 = hours;

    if (isPM && hours !== 12) {
      hour24 = hours + 12;
    } else if (isAM && hours === 12) {
      hour24 = 0;
    }

    return hour24 * 60 + minutes;
  };

  const startMinutes = parseTime(startTime);
  const endMinutes = parseTime(endTime);
  let durationMinutes = endMinutes - startMinutes;

  // Handle overnight naps (edge case)
  if (durationMinutes < 0) {
    durationMinutes += 24 * 60;
  }

  const hours = Math.floor(durationMinutes / 60);
  const minutes = durationMinutes % 60;

  if (hours === 0) {
    return `${minutes}m`;
  } else if (minutes === 0) {
    return `${hours}h`;
  }
  return `${hours}h ${minutes}m`;
}

// ============================================================================
// Component
// ============================================================================

/**
 * NapCard - displays a single nap entry with duration and quality.
 */
function NapCard({nap}: NapCardProps): React.JSX.Element {
  const qualityInfo = qualityConfig[nap.quality];
  const duration = calculateDuration(nap.startTime, nap.endTime);

  return (
    <View style={styles.container}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>😴</Text>
      </View>
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.title}>{duration}</Text>
          <Text style={styles.time}>
            {nap.startTime} - {nap.endTime}
          </Text>
        </View>
        <View
          style={[
            styles.badge,
            {backgroundColor: qualityInfo.badgeBackgroundColor},
          ]}>
          <Text style={[styles.badgeText, {color: qualityInfo.badgeTextColor}]}>
            {qualityInfo.label}
          </Text>
        </View>
      </View>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 12,
    paddingHorizontal: 16,
    backgroundColor: '#FFFFFF',
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#DBEAFE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  icon: {
    fontSize: 20,
  },
  content: {
    flex: 1,
    minWidth: 0,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  time: {
    fontSize: 14,
    color: '#6B7280',
  },
  badge: {
    alignSelf: 'flex-start',
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 4,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '500',
  },
});

export default NapCard;
