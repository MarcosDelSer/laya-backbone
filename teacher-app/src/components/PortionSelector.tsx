/**
 * LAYA Teacher App - PortionSelector Component
 *
 * A component for selecting portion sizes during meal logging.
 * Provides visual indicators for none, half, and full portions with
 * large touch targets for easy teacher interaction.
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
import type {PortionSize} from '../types';

interface PortionSelectorProps {
  /** Currently selected portion size */
  selectedPortion: PortionSize | null;
  /** Callback when a portion is selected */
  onSelectPortion: (portion: PortionSize) => void;
  /** Whether the selector is disabled */
  disabled?: boolean;
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

interface PortionOption {
  value: PortionSize;
  label: string;
  icon: string;
  description: string;
}

const PORTION_OPTIONS: PortionOption[] = [
  {
    value: 'none',
    label: 'None',
    icon: '',
    description: 'Did not eat',
  },
  {
    value: 'half',
    label: 'Half',
    icon: '',
    description: 'Ate some',
  },
  {
    value: 'full',
    label: 'Full',
    icon: '',
    description: 'Ate all',
  },
];

interface PortionButtonConfig {
  backgroundColor: string;
  borderColor: string;
  textColor: string;
  iconColor: string;
}

const PORTION_CONFIGS: Record<PortionSize, PortionButtonConfig> = {
  none: {
    backgroundColor: '#FFEBEE',
    borderColor: '#EF5350',
    textColor: '#C62828',
    iconColor: '#EF5350',
  },
  half: {
    backgroundColor: '#FFF3E0',
    borderColor: '#FFA726',
    textColor: '#E65100',
    iconColor: '#FFA726',
  },
  full: {
    backgroundColor: '#E8F5E9',
    borderColor: '#66BB6A',
    textColor: '#2E7D32',
    iconColor: '#66BB6A',
  },
};

const UNSELECTED_CONFIG: PortionButtonConfig = {
  backgroundColor: '#FFFFFF',
  borderColor: '#E0E0E0',
  textColor: '#666666',
  iconColor: '#999999',
};

/**
 * PortionSelector displays portion size options as large, tappable buttons.
 * Designed for quick teacher interaction during meal logging.
 */
function PortionSelector({
  selectedPortion,
  onSelectPortion,
  disabled = false,
  style,
}: PortionSelectorProps): React.JSX.Element {
  const handleSelect = (portion: PortionSize) => {
    if (disabled) {
      return;
    }
    onSelectPortion(portion);
    AccessibilityInfo.announceForAccessibility(
      `Selected ${PORTION_OPTIONS.find(p => p.value === portion)?.label || portion} portion`,
    );
  };

  return (
    <View style={[styles.container, style]}>
      <Text style={styles.label}>Portion Size</Text>
      <View style={styles.optionsContainer}>
        {PORTION_OPTIONS.map(option => {
          const isSelected = selectedPortion === option.value;
          const config = isSelected ? PORTION_CONFIGS[option.value] : UNSELECTED_CONFIG;

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
              accessibilityLabel={`${option.label} portion - ${option.description}`}>
              <Text style={[styles.optionIcon, {color: config.iconColor}]}>
                {option.icon}
              </Text>
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
    marginBottom: 8,
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
    paddingVertical: 16,
    paddingHorizontal: 8,
    borderRadius: 12,
    borderWidth: 2,
    // Minimum touch target size (44x44pt)
    minHeight: 88,
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
  optionIcon: {
    fontSize: 28,
    marginBottom: 4,
  },
  optionLabel: {
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 2,
  },
  optionDescription: {
    fontSize: 11,
    opacity: 0.8,
  },
});

export default PortionSelector;
