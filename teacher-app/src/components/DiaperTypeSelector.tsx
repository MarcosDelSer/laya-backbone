/**
 * LAYA Teacher App - DiaperTypeSelector Component
 *
 * A component for selecting diaper change types (wet, soiled, dry).
 * Provides visual indicators with large touch targets for quick teacher
 * interaction during diaper change logging.
 */

import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ViewStyle,
  AccessibilityInfo,
} from 'react-native';
import type {DiaperType} from '../types';

interface DiaperTypeSelectorProps {
  /** Currently selected diaper type */
  selectedType: DiaperType | null;
  /** Callback when a diaper type is selected */
  onSelectType: (type: DiaperType) => void;
  /** Whether the selector is disabled */
  disabled?: boolean;
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

interface DiaperOption {
  value: DiaperType;
  label: string;
  icon: string;
  description: string;
}

const DIAPER_OPTIONS: DiaperOption[] = [
  {
    value: 'wet',
    label: 'Wet',
    icon: 'W',
    description: 'Wet diaper',
  },
  {
    value: 'soiled',
    label: 'Soiled',
    icon: 'S',
    description: 'Bowel movement',
  },
  {
    value: 'dry',
    label: 'Dry',
    icon: 'D',
    description: 'Dry check',
  },
];

interface DiaperButtonConfig {
  backgroundColor: string;
  borderColor: string;
  textColor: string;
  iconBackgroundColor: string;
}

const DIAPER_CONFIGS: Record<DiaperType, DiaperButtonConfig> = {
  wet: {
    backgroundColor: '#E3F2FD',
    borderColor: '#42A5F5',
    textColor: '#1565C0',
    iconBackgroundColor: '#42A5F5',
  },
  soiled: {
    backgroundColor: '#FFF3E0',
    borderColor: '#FFA726',
    textColor: '#E65100',
    iconBackgroundColor: '#FFA726',
  },
  dry: {
    backgroundColor: '#E8F5E9',
    borderColor: '#66BB6A',
    textColor: '#2E7D32',
    iconBackgroundColor: '#66BB6A',
  },
};

const UNSELECTED_CONFIG: DiaperButtonConfig = {
  backgroundColor: '#FFFFFF',
  borderColor: '#E0E0E0',
  textColor: '#666666',
  iconBackgroundColor: '#E0E0E0',
};

/**
 * DiaperTypeSelector displays diaper type options as large, tappable buttons.
 * Designed for quick teacher interaction during diaper change logging.
 */
function DiaperTypeSelector({
  selectedType,
  onSelectType,
  disabled = false,
  style,
}: DiaperTypeSelectorProps): React.JSX.Element {
  const handleSelect = (type: DiaperType) => {
    if (disabled) {
      return;
    }
    onSelectType(type);
    AccessibilityInfo.announceForAccessibility(
      `Selected ${DIAPER_OPTIONS.find(d => d.value === type)?.label || type} diaper`,
    );
  };

  return (
    <View style={[styles.container, style]}>
      <Text style={styles.label}>Diaper Type</Text>
      <View style={styles.optionsContainer}>
        {DIAPER_OPTIONS.map(option => {
          const isSelected = selectedType === option.value;
          const config = isSelected
            ? DIAPER_CONFIGS[option.value]
            : UNSELECTED_CONFIG;

          return (
            <TouchableOpacity
              key={option.value}
              style={[
                styles.optionButton,
                {
                  backgroundColor: config.backgroundColor,
                  borderColor: config.borderColor,
                },
                isSelected && styles.optionButtonSelected,
                disabled && styles.optionButtonDisabled,
              ]}
              onPress={() => handleSelect(option.value)}
              disabled={disabled}
              activeOpacity={0.7}
              accessibilityRole="radio"
              accessibilityState={{
                checked: isSelected,
                disabled,
              }}
              accessibilityLabel={`${option.label} - ${option.description}`}>
              <View
                style={[
                  styles.iconContainer,
                  {backgroundColor: config.iconBackgroundColor},
                ]}>
                <Text style={styles.iconText}>{option.icon}</Text>
              </View>
              <Text style={[styles.optionLabel, {color: config.textColor}]}>
                {option.label}
              </Text>
              <Text style={[styles.optionDescription, {color: config.textColor}]}>
                {option.description}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 12,
  },
  optionsContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  optionButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 20,
    paddingHorizontal: 8,
    borderRadius: 12,
    borderWidth: 2,
    // Minimum touch target size (44x44pt)
    minHeight: 110,
  },
  optionButtonSelected: {
    // Shadow for selected state
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  optionButtonDisabled: {
    opacity: 0.5,
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  iconText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '700',
  },
  optionLabel: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 2,
  },
  optionDescription: {
    fontSize: 11,
    opacity: 0.8,
    textAlign: 'center',
  },
});

export default DiaperTypeSelector;
