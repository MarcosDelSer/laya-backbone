/**
 * LAYA Teacher App - MealSelector Component
 *
 * A component for selecting meal types (breakfast, lunch, snack) during meal logging.
 * Provides visual indicators for meal types with time-based suggestions and
 * indicates which meals have already been logged.
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
import type {MealType} from '../types';

interface MealSelectorProps {
  /** Currently selected meal type */
  selectedMealType: MealType | null;
  /** Callback when a meal type is selected */
  onSelectMealType: (mealType: MealType) => void;
  /** Meal types that have already been logged */
  loggedMealTypes?: MealType[];
  /** Suggested meal type based on time of day */
  suggestedMealType?: MealType;
  /** Whether the selector is disabled */
  disabled?: boolean;
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

interface MealOption {
  value: MealType;
  label: string;
  icon: string;
  timeHint: string;
}

const MEAL_OPTIONS: MealOption[] = [
  {
    value: 'breakfast',
    label: 'Breakfast',
    icon: '',
    timeHint: 'Before 10am',
  },
  {
    value: 'lunch',
    label: 'Lunch',
    icon: '',
    timeHint: '10am - 2pm',
  },
  {
    value: 'snack',
    label: 'Snack',
    icon: '',
    timeHint: 'After 2pm',
  },
];

interface MealButtonConfig {
  backgroundColor: string;
  borderColor: string;
  textColor: string;
  iconColor: string;
}

const MEAL_CONFIGS: Record<MealType, MealButtonConfig> = {
  breakfast: {
    backgroundColor: '#FFF8E1',
    borderColor: '#FFB300',
    textColor: '#E65100',
    iconColor: '#FFB300',
  },
  lunch: {
    backgroundColor: '#E3F2FD',
    borderColor: '#42A5F5',
    textColor: '#1565C0',
    iconColor: '#42A5F5',
  },
  snack: {
    backgroundColor: '#F3E5F5',
    borderColor: '#AB47BC',
    textColor: '#7B1FA2',
    iconColor: '#AB47BC',
  },
};

const UNSELECTED_CONFIG: MealButtonConfig = {
  backgroundColor: '#FFFFFF',
  borderColor: '#E0E0E0',
  textColor: '#666666',
  iconColor: '#999999',
};

const LOGGED_CONFIG: MealButtonConfig = {
  backgroundColor: '#F5F5F5',
  borderColor: '#BDBDBD',
  textColor: '#9E9E9E',
  iconColor: '#BDBDBD',
};

/**
 * MealSelector displays meal type options as large, tappable buttons.
 * Shows visual feedback for selected, suggested, and already-logged meals.
 */
function MealSelector({
  selectedMealType,
  onSelectMealType,
  loggedMealTypes = [],
  suggestedMealType,
  disabled = false,
  style,
}: MealSelectorProps): React.JSX.Element {
  const handleSelect = (mealType: MealType) => {
    if (disabled) {
      return;
    }
    onSelectMealType(mealType);
    AccessibilityInfo.announceForAccessibility(
      `Selected ${MEAL_OPTIONS.find(m => m.value === mealType)?.label || mealType}`,
    );
  };

  return (
    <View style={[styles.container, style]}>
      <Text style={styles.label}>Meal Type</Text>
      <View style={styles.optionsContainer}>
        {MEAL_OPTIONS.map(option => {
          const isSelected = selectedMealType === option.value;
          const isLogged = loggedMealTypes.includes(option.value);
          const isSuggested = suggestedMealType === option.value && !isSelected && !isLogged;

          let config: MealButtonConfig;
          if (isLogged && !isSelected) {
            config = LOGGED_CONFIG;
          } else if (isSelected) {
            config = MEAL_CONFIGS[option.value];
          } else {
            config = UNSELECTED_CONFIG;
          }

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
                isSuggested && styles.optionButtonSuggested,
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
              accessibilityLabel={`${option.label}${isLogged ? ', already logged' : ''}${isSuggested ? ', suggested' : ''}`}
              accessibilityHint={option.timeHint}>
              <Text style={[styles.optionIcon, {color: config.iconColor}]}>
                {option.icon}
              </Text>
              <Text style={[styles.optionLabel, {color: config.textColor}]}>
                {option.label}
              </Text>
              <Text style={[styles.optionTimeHint, {color: config.textColor}]}>
                {option.timeHint}
              </Text>

              {isSuggested && (
                <View style={styles.suggestedBadge}>
                  <Text style={styles.suggestedText}>Suggested</Text>
                </View>
              )}

              {isLogged && !isSelected && (
                <View style={styles.loggedBadge}>
                  <Text style={styles.loggedText}>Logged</Text>
                </View>
              )}
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
    minHeight: 100,
    position: 'relative',
  },
  optionButtonSelected: {
    // Shadow for selected state
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  optionButtonSuggested: {
    borderStyle: 'dashed',
  },
  optionButtonDisabled: {
    opacity: 0.5,
  },
  optionIcon: {
    fontSize: 32,
    marginBottom: 4,
  },
  optionLabel: {
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 2,
  },
  optionTimeHint: {
    fontSize: 10,
    opacity: 0.7,
  },
  suggestedBadge: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: '#4CAF50',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  suggestedText: {
    color: '#FFFFFF',
    fontSize: 9,
    fontWeight: '600',
  },
  loggedBadge: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: '#9E9E9E',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  loggedText: {
    color: '#FFFFFF',
    fontSize: 9,
    fontWeight: '600',
  },
});

export default MealSelector;
