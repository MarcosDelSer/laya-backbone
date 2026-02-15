/**
 * LAYA Teacher App - AllergyAlert Component
 *
 * A component for displaying allergy warnings prominently during meal logging.
 * Shows severity-based visual indicators to help teachers avoid allergens.
 */

import React from 'react';
import {StyleSheet, Text, View, ViewStyle} from 'react-native';
import type {Allergy} from '../types';

interface AllergyAlertProps {
  /** List of allergies to display */
  allergies: Allergy[];
  /** Whether to show in compact mode */
  compact?: boolean;
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

interface SeverityConfig {
  backgroundColor: string;
  borderColor: string;
  textColor: string;
  icon: string;
}

const SEVERITY_CONFIGS: Record<Allergy['severity'], SeverityConfig> = {
  severe: {
    backgroundColor: '#FFEBEE',
    borderColor: '#C62828',
    textColor: '#C62828',
    icon: '!',
  },
  moderate: {
    backgroundColor: '#FFF3E0',
    borderColor: '#EF6C00',
    textColor: '#E65100',
    icon: '!',
  },
  mild: {
    backgroundColor: '#FFF8E1',
    borderColor: '#FFA000',
    textColor: '#F57C00',
    icon: '',
  },
};

/**
 * Get label for severity level
 */
function getSeverityLabel(severity: Allergy['severity']): string {
  const labels: Record<Allergy['severity'], string> = {
    severe: 'SEVERE',
    moderate: 'Moderate',
    mild: 'Mild',
  };
  return labels[severity];
}

/**
 * AllergyAlert displays prominent allergy warnings for a child.
 * Shows different visual styles based on allergy severity.
 */
function AllergyAlert({
  allergies,
  compact = false,
  style,
}: AllergyAlertProps): React.JSX.Element | null {
  if (allergies.length === 0) {
    return null;
  }

  // Sort allergies by severity (severe first)
  const sortedAllergies = [...allergies].sort((a, b) => {
    const severityOrder: Record<Allergy['severity'], number> = {
      severe: 0,
      moderate: 1,
      mild: 2,
    };
    return severityOrder[a.severity] - severityOrder[b.severity];
  });

  // Get the most severe allergy to determine container styling
  const mostSevere = sortedAllergies[0];
  const containerConfig = SEVERITY_CONFIGS[mostSevere.severity];

  if (compact) {
    return (
      <View
        style={[
          styles.compactContainer,
          {
            backgroundColor: containerConfig.backgroundColor,
            borderColor: containerConfig.borderColor,
          },
          style,
        ]}
        accessibilityRole="alert"
        accessibilityLabel={`Allergy warning: ${allergies.map(a => a.allergen).join(', ')}`}>
        <Text style={[styles.compactIcon, {color: containerConfig.textColor}]}>
          {containerConfig.icon}
        </Text>
        <Text
          style={[styles.compactText, {color: containerConfig.textColor}]}
          numberOfLines={1}>
          {allergies.map(a => a.allergen).join(', ')}
        </Text>
      </View>
    );
  }

  return (
    <View
      style={[
        styles.container,
        {
          backgroundColor: containerConfig.backgroundColor,
          borderColor: containerConfig.borderColor,
        },
        style,
      ]}
      accessibilityRole="alert"
      accessibilityLabel={`Allergy warning: ${allergies.length} ${
        allergies.length === 1 ? 'allergy' : 'allergies'
      }`}>
      <View style={styles.headerRow}>
        <View style={[styles.iconContainer, {backgroundColor: containerConfig.borderColor}]}>
          <Text style={styles.iconText}>!</Text>
        </View>
        <Text style={[styles.headerText, {color: containerConfig.textColor}]}>
          Allergy Alert
        </Text>
      </View>

      <View style={styles.allergyList}>
        {sortedAllergies.map(allergy => {
          const config = SEVERITY_CONFIGS[allergy.severity];
          return (
            <View key={allergy.id} style={styles.allergyItem}>
              <View
                style={[
                  styles.severityBadge,
                  {backgroundColor: config.borderColor},
                ]}>
                <Text style={styles.severityText}>
                  {getSeverityLabel(allergy.severity)}
                </Text>
              </View>
              <Text style={[styles.allergenText, {color: config.textColor}]}>
                {allergy.allergen}
              </Text>
              {allergy.notes && (
                <Text style={styles.notesText}>{allergy.notes}</Text>
              )}
            </View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    borderWidth: 2,
    borderRadius: 12,
    padding: 12,
    marginVertical: 8,
  },
  compactContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  compactIcon: {
    fontSize: 14,
    fontWeight: '700',
    marginRight: 6,
  },
  compactText: {
    fontSize: 12,
    fontWeight: '600',
    flex: 1,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  iconContainer: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
  },
  iconText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '700',
  },
  headerText: {
    fontSize: 16,
    fontWeight: '700',
  },
  allergyList: {
    marginTop: 4,
  },
  allergyItem: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    marginVertical: 4,
  },
  severityBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginRight: 8,
  },
  severityText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: '700',
  },
  allergenText: {
    fontSize: 14,
    fontWeight: '600',
  },
  notesText: {
    fontSize: 12,
    color: '#666666',
    width: '100%',
    marginTop: 2,
    marginLeft: 58,
  },
});

export default AllergyAlert;
