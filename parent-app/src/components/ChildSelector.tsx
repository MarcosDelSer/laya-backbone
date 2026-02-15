/**
 * LAYA Parent App - ChildSelector Component
 *
 * A dropdown selector component for multi-child families to switch
 * between children. Displays the selected child with avatar and
 * classroom info, with a modal dropdown for selection.
 */

import React, {useState} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  Modal,
  Pressable,
  ScrollView,
  Image,
} from 'react-native';
import type {Child} from '../types';

interface ChildSelectorProps {
  /** List of available children to select from */
  children: Child[];
  /** Currently selected child */
  selectedChild: Child | null;
  /** Callback when a child is selected */
  onSelectChild: (child: Child) => void;
  /** Whether the selector is disabled */
  disabled?: boolean;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  primaryLight: '#E8F1FA',
  primaryDark: '#2A6DB8',
  background: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  overlay: 'rgba(0, 0, 0, 0.5)',
};

/**
 * Get initials from a child's name
 */
function getChildInitials(child: Child): string {
  return `${child.firstName.charAt(0)}${child.lastName.charAt(0)}`;
}

/**
 * Get full name from a child
 */
function getChildFullName(child: Child): string {
  return `${child.firstName} ${child.lastName}`;
}

/**
 * ChildSelector provides a dropdown for selecting between multiple children.
 * Shows the currently selected child with avatar and classroom, with a
 * modal dropdown for switching between children.
 */
function ChildSelector({
  children,
  selectedChild,
  onSelectChild,
  disabled = false,
}: ChildSelectorProps): React.JSX.Element | null {
  const [isOpen, setIsOpen] = useState(false);

  // Don't render if no children or only one child
  if (children.length <= 1) {
    return null;
  }

  const handleSelect = (child: Child): void => {
    onSelectChild(child);
    setIsOpen(false);
  };

  const toggleDropdown = (): void => {
    if (!disabled) {
      setIsOpen(!isOpen);
    }
  };

  const closeDropdown = (): void => {
    setIsOpen(false);
  };

  return (
    <>
      {/* Selector Button */}
      <TouchableOpacity
        style={[styles.selectorButton, disabled && styles.selectorButtonDisabled]}
        onPress={toggleDropdown}
        disabled={disabled}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={
          selectedChild
            ? `Selected: ${getChildFullName(selectedChild)}. Tap to change child.`
            : 'Select a child'
        }
        accessibilityState={{expanded: isOpen, disabled}}>
        {selectedChild ? (
          <>
            {/* Avatar */}
            {selectedChild.photoUrl ? (
              <Image
                source={{uri: selectedChild.photoUrl}}
                style={styles.avatar}
                accessibilityIgnoresInvertColors
              />
            ) : (
              <View style={styles.avatarPlaceholder}>
                <Text style={styles.avatarText}>
                  {getChildInitials(selectedChild)}
                </Text>
              </View>
            )}

            {/* Name and Classroom */}
            <View style={styles.childInfo}>
              <Text style={styles.childName} numberOfLines={1}>
                {getChildFullName(selectedChild)}
              </Text>
              <Text style={styles.classroomName} numberOfLines={1}>
                {selectedChild.classroomName}
              </Text>
            </View>

            {/* Chevron Icon */}
            <View
              style={[
                styles.chevronContainer,
                isOpen && styles.chevronContainerOpen,
              ]}>
              <Text style={styles.chevron}>▼</Text>
            </View>
          </>
        ) : (
          <Text style={styles.placeholderText}>Select a child</Text>
        )}
      </TouchableOpacity>

      {/* Dropdown Modal */}
      <Modal
        visible={isOpen}
        transparent
        animationType="fade"
        onRequestClose={closeDropdown}>
        <Pressable style={styles.modalOverlay} onPress={closeDropdown}>
          <View style={styles.dropdownContainer}>
            <View style={styles.dropdownHeader}>
              <Text style={styles.dropdownTitle}>Select Child</Text>
            </View>
            <ScrollView
              style={styles.dropdownList}
              showsVerticalScrollIndicator={false}>
              {children.map((child) => {
                const isSelected = selectedChild?.id === child.id;
                return (
                  <TouchableOpacity
                    key={child.id}
                    style={[
                      styles.dropdownItem,
                      isSelected && styles.dropdownItemSelected,
                    ]}
                    onPress={() => handleSelect(child)}
                    activeOpacity={0.7}
                    accessibilityRole="menuitem"
                    accessibilityLabel={`${getChildFullName(child)}, ${child.classroomName}`}
                    accessibilityState={{selected: isSelected}}>
                    {/* Avatar */}
                    {child.photoUrl ? (
                      <Image
                        source={{uri: child.photoUrl}}
                        style={styles.dropdownAvatar}
                        accessibilityIgnoresInvertColors
                      />
                    ) : (
                      <View
                        style={[
                          styles.dropdownAvatarPlaceholder,
                          isSelected && styles.dropdownAvatarPlaceholderSelected,
                        ]}>
                        <Text
                          style={[
                            styles.dropdownAvatarText,
                            isSelected && styles.dropdownAvatarTextSelected,
                          ]}>
                          {getChildInitials(child)}
                        </Text>
                      </View>
                    )}

                    {/* Name and Classroom */}
                    <View style={styles.dropdownItemInfo}>
                      <Text
                        style={[
                          styles.dropdownItemName,
                          isSelected && styles.dropdownItemNameSelected,
                        ]}
                        numberOfLines={1}>
                        {getChildFullName(child)}
                      </Text>
                      <Text style={styles.dropdownItemClassroom} numberOfLines={1}>
                        {child.classroomName}
                      </Text>
                    </View>

                    {/* Checkmark for selected */}
                    {isSelected && (
                      <View style={styles.checkmarkContainer}>
                        <Text style={styles.checkmark}>✓</Text>
                      </View>
                    )}
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  // Selector Button Styles
  selectorButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingHorizontal: 12,
    paddingVertical: 8,
    minWidth: 180,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    // Android elevation
    elevation: 1,
  },
  selectorButtonDisabled: {
    opacity: 0.5,
  },
  avatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  avatarPlaceholder: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: COLORS.primaryLight,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.primary,
  },
  childInfo: {
    flex: 1,
    marginLeft: 10,
  },
  childName: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.text,
  },
  classroomName: {
    fontSize: 12,
    color: COLORS.textLight,
    marginTop: 1,
  },
  chevronContainer: {
    marginLeft: 8,
    width: 20,
    height: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  chevronContainerOpen: {
    transform: [{rotate: '180deg'}],
  },
  chevron: {
    fontSize: 10,
    color: COLORS.textLight,
  },
  placeholderText: {
    fontSize: 14,
    color: COLORS.textLight,
    flex: 1,
  },

  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: COLORS.overlay,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
  },
  dropdownContainer: {
    backgroundColor: COLORS.background,
    borderRadius: 16,
    width: '100%',
    maxWidth: 320,
    maxHeight: '60%',
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.15,
    shadowRadius: 12,
    // Android elevation
    elevation: 8,
  },
  dropdownHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  dropdownTitle: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textLight,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  dropdownList: {
    padding: 8,
  },
  dropdownItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 10,
    marginVertical: 2,
  },
  dropdownItemSelected: {
    backgroundColor: COLORS.primaryLight,
  },
  dropdownAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  dropdownAvatarPlaceholder: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F0F0F0',
    justifyContent: 'center',
    alignItems: 'center',
  },
  dropdownAvatarPlaceholderSelected: {
    backgroundColor: COLORS.primary,
  },
  dropdownAvatarText: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  dropdownAvatarTextSelected: {
    color: COLORS.background,
  },
  dropdownItemInfo: {
    flex: 1,
    marginLeft: 12,
  },
  dropdownItemName: {
    fontSize: 15,
    fontWeight: '500',
    color: COLORS.text,
  },
  dropdownItemNameSelected: {
    color: COLORS.primaryDark,
    fontWeight: '600',
  },
  dropdownItemClassroom: {
    fontSize: 13,
    color: COLORS.textLight,
    marginTop: 2,
  },
  checkmarkContainer: {
    width: 24,
    height: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 8,
  },
  checkmark: {
    fontSize: 16,
    fontWeight: '700',
    color: COLORS.primary,
  },
});

export default ChildSelector;
