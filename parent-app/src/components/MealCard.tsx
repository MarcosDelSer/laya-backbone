/**
 * LAYA Parent App - Meal Card Component
 *
 * Displays meal entry information including meal type, time,
 * consumption amount, and optional notes.
 *
 * Adapted from parent-portal/components/MealEntry.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {MealEntry, MealAmount, MealType} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface MealCardProps {
  meal: MealEntry;
}

// ============================================================================
// Configuration
// ============================================================================

const mealTypeLabels: Record<MealType, string> = {
  breakfast: 'Breakfast',
  lunch: 'Lunch',
  snack: 'Snack',
};

interface AmountConfig {
  label: string;
  badgeBackgroundColor: string;
  badgeTextColor: string;
}

const amountConfig: Record<MealAmount, AmountConfig> = {
  all: {
    label: 'Ate all',
    badgeBackgroundColor: '#DEF7EC',
    badgeTextColor: '#03543F',
  },
  most: {
    label: 'Ate most',
    badgeBackgroundColor: '#E1EFFE',
    badgeTextColor: '#1E429F',
  },
  some: {
    label: 'Ate some',
    badgeBackgroundColor: '#FDF6B2',
    badgeTextColor: '#723B13',
  },
  none: {
    label: 'Did not eat',
    badgeBackgroundColor: '#F3F4F6',
    badgeTextColor: '#4B5563',
  },
};

// ============================================================================
// Component
// ============================================================================

/**
 * MealCard - displays a single meal entry with consumption details.
 */
function MealCard({meal}: MealCardProps): React.JSX.Element {
  const amountInfo = amountConfig[meal.amount];

  return (
    <View style={styles.container}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>🍽️</Text>
      </View>
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.title}>{mealTypeLabels[meal.type]}</Text>
          <Text style={styles.time}>{meal.time}</Text>
        </View>
        {meal.notes ? (
          <Text style={styles.notes} numberOfLines={2}>
            {meal.notes}
          </Text>
        ) : null}
        <View
          style={[
            styles.badge,
            {backgroundColor: amountInfo.badgeBackgroundColor},
          ]}>
          <Text style={[styles.badgeText, {color: amountInfo.badgeTextColor}]}>
            {amountInfo.label}
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
    backgroundColor: '#DEF7EC',
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
    marginBottom: 4,
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
  notes: {
    fontSize: 14,
    color: '#4B5563',
    marginBottom: 8,
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

export default MealCard;
