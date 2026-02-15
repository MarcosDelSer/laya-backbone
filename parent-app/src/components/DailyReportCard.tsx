/**
 * LAYA Parent App - Daily Report Card Component
 *
 * Aggregates meals, naps, and activities into a comprehensive daily
 * report card with date header and summary badges.
 *
 * Adapted from parent-portal/components/DailyReportCard.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {DailyReport} from '../types';
import MealCard from './MealCard';
import NapCard from './NapCard';
import ActivityCard from './ActivityCard';

// ============================================================================
// Props Interface
// ============================================================================

interface DailyReportCardProps {
  report: DailyReport;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Formats the date string to a user-friendly display format.
 * Returns "Today", "Yesterday", or a formatted date string.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  // Check if it's today
  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  }

  // Check if it's yesterday
  if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  }

  // Otherwise return formatted date
  const options: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  };

  // Include year if not the current year
  if (date.getFullYear() !== today.getFullYear()) {
    options.year = 'numeric';
  }

  return date.toLocaleDateString('en-US', options);
}

/**
 * Pluralize a word based on count.
 */
function pluralize(count: number, singular: string, plural: string): string {
  return count === 1 ? singular : plural;
}

// ============================================================================
// Sub-components
// ============================================================================

interface SectionHeaderProps {
  title: string;
  count?: number;
}

/**
 * SectionHeader - displays section title with optional entry count.
 */
function SectionHeader({title, count}: SectionHeaderProps): React.JSX.Element {
  return (
    <View style={sectionStyles.header}>
      <Text style={sectionStyles.title}>{title}</Text>
      {count !== undefined && count > 0 && (
        <Text style={sectionStyles.count}>
          {count} {pluralize(count, 'entry', 'entries')}
        </Text>
      )}
    </View>
  );
}

const sectionStyles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
    paddingBottom: 8,
    marginBottom: 12,
    marginHorizontal: 16,
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  count: {
    fontSize: 14,
    color: '#6B7280',
  },
});

interface EmptyStateProps {
  message: string;
}

/**
 * EmptyState - displays a message when a section has no entries.
 */
function EmptyState({message}: EmptyStateProps): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <Text style={emptyStyles.message}>{message}</Text>
    </View>
  );
}

const emptyStyles = StyleSheet.create({
  container: {
    paddingVertical: 16,
    paddingHorizontal: 16,
    alignItems: 'center',
  },
  message: {
    fontSize: 14,
    color: '#6B7280',
    fontStyle: 'italic',
  },
});

interface SummaryBadgeProps {
  count: number;
  label: string;
  backgroundColor: string;
  textColor: string;
}

/**
 * SummaryBadge - displays a count badge for report sections.
 */
function SummaryBadge({
  count,
  label,
  backgroundColor,
  textColor,
}: SummaryBadgeProps): React.JSX.Element | null {
  if (count === 0) {
    return null;
  }

  return (
    <View style={[badgeStyles.badge, {backgroundColor}]}>
      <Text style={[badgeStyles.text, {color: textColor}]}>
        {count} {label}
      </Text>
    </View>
  );
}

const badgeStyles = StyleSheet.create({
  badge: {
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 12,
    marginLeft: 6,
  },
  text: {
    fontSize: 12,
    fontWeight: '500',
  },
});

// ============================================================================
// Main Component
// ============================================================================

/**
 * DailyReportCard - aggregates meals, naps, and activities into a
 * comprehensive daily report with date header and summary.
 */
function DailyReportCard({report}: DailyReportCardProps): React.JSX.Element {
  const formattedDate = formatDate(report.date);

  return (
    <View style={styles.container}>
      {/* Report Header */}
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <View style={styles.iconContainer}>
            <Text style={styles.icon}>📅</Text>
          </View>
          <View style={styles.headerText}>
            <Text style={styles.headerTitle}>Daily Report</Text>
            <Text style={styles.headerDate}>{formattedDate}</Text>
          </View>
        </View>

        {/* Summary badges */}
        <View style={styles.badgeRow}>
          <SummaryBadge
            count={report.meals.length}
            label={pluralize(report.meals.length, 'Meal', 'Meals')}
            backgroundColor="#DEF7EC"
            textColor="#03543F"
          />
          <SummaryBadge
            count={report.naps.length}
            label={pluralize(report.naps.length, 'Nap', 'Naps')}
            backgroundColor="#DBEAFE"
            textColor="#1E429F"
          />
          <SummaryBadge
            count={report.activities.length}
            label={pluralize(report.activities.length, 'Activity', 'Activities')}
            backgroundColor="#FEF3C7"
            textColor="#92400E"
          />
        </View>
      </View>

      {/* Divider */}
      <View style={styles.divider} />

      {/* Meals Section */}
      <View style={styles.section}>
        <SectionHeader title="Meals" count={report.meals.length} />
        {report.meals.length > 0 ? (
          <View style={styles.entriesContainer}>
            {report.meals.map((meal) => (
              <View key={meal.id} style={styles.entryWrapper}>
                <MealCard meal={meal} />
              </View>
            ))}
          </View>
        ) : (
          <EmptyState message="No meals recorded" />
        )}
      </View>

      {/* Naps Section */}
      <View style={styles.section}>
        <SectionHeader title="Nap Time" count={report.naps.length} />
        {report.naps.length > 0 ? (
          <View style={styles.entriesContainer}>
            {report.naps.map((nap) => (
              <View key={nap.id} style={styles.entryWrapper}>
                <NapCard nap={nap} />
              </View>
            ))}
          </View>
        ) : (
          <EmptyState message="No naps recorded" />
        )}
      </View>

      {/* Activities Section */}
      <View style={styles.section}>
        <SectionHeader title="Activities" count={report.activities.length} />
        {report.activities.length > 0 ? (
          <View style={styles.entriesContainer}>
            {report.activities.map((activity) => (
              <View key={activity.id} style={styles.entryWrapper}>
                <ActivityCard activity={activity} />
              </View>
            ))}
          </View>
        ) : (
          <EmptyState message="No activities recorded" />
        )}
      </View>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    shadowColor: '#000000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    overflow: 'hidden',
  },
  header: {
    padding: 16,
    backgroundColor: '#FFFFFF',
  },
  headerContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#EDE9FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  icon: {
    fontSize: 24,
  },
  headerText: {
    flex: 1,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  headerDate: {
    fontSize: 14,
    color: '#6B7280',
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 12,
  },
  divider: {
    height: 1,
    backgroundColor: '#E5E7EB',
  },
  section: {
    paddingTop: 16,
    paddingBottom: 8,
  },
  entriesContainer: {
    paddingBottom: 8,
  },
  entryWrapper: {
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
});

export default DailyReportCard;
