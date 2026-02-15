/**
 * LAYA Teacher App - ChildTagging Component
 *
 * Multi-select component for tagging children in photos.
 * Follows the pattern from photos_tag.php:
 * - Display list of enrolled children
 * - Allow multi-selection
 * - Show currently tagged children
 *
 * Designed with large touch targets for quick teacher interactions.
 */

import React, {useCallback, useMemo} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  FlatList,
  Image,
  AccessibilityInfo,
} from 'react-native';
import type {Child} from '../types';

interface ChildTaggingProps {
  /** List of children available for tagging */
  children: Child[];
  /** Currently selected child IDs */
  selectedIds: string[];
  /** Callback when selection changes */
  onSelectionChange: (selectedIds: string[]) => void;
  /** Maximum number of children that can be tagged (optional) */
  maxSelection?: number;
  /** Whether the component is disabled */
  disabled?: boolean;
  /** Title to display above the list */
  title?: string;
  /** Description text */
  description?: string;
}

/**
 * Get initials from child name for placeholder avatar
 */
function getInitials(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

/**
 * Format child name for display
 */
function formatChildName(child: Child): string {
  return `${child.firstName} ${child.lastName}`;
}

/**
 * Individual child item in the tagging list
 */
interface ChildTagItemProps {
  child: Child;
  isSelected: boolean;
  onToggle: (childId: string) => void;
  disabled: boolean;
}

function ChildTagItem({
  child,
  isSelected,
  onToggle,
  disabled,
}: ChildTagItemProps): React.JSX.Element {
  const handlePress = useCallback(() => {
    if (!disabled) {
      onToggle(child.id);
      AccessibilityInfo.announceForAccessibility(
        isSelected
          ? `Removed ${formatChildName(child)} from photo`
          : `Tagged ${formatChildName(child)} in photo`,
      );
    }
  }, [child, isSelected, disabled, onToggle]);

  return (
    <TouchableOpacity
      style={[
        styles.childItem,
        isSelected && styles.childItemSelected,
        disabled && styles.childItemDisabled,
      ]}
      onPress={handlePress}
      disabled={disabled}
      activeOpacity={0.7}
      accessibilityRole="checkbox"
      accessibilityState={{checked: isSelected}}
      accessibilityLabel={`${formatChildName(child)}`}
      accessibilityHint={
        isSelected ? 'Double tap to remove from photo' : 'Double tap to tag in photo'
      }>
      {/* Avatar */}
      <View style={styles.avatarContainer}>
        {child.photoUrl ? (
          <Image
            source={{uri: child.photoUrl}}
            style={styles.avatar}
            accessibilityIgnoresInvertColors
          />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Text style={styles.avatarInitials}>
              {getInitials(child.firstName, child.lastName)}
            </Text>
          </View>
        )}
      </View>

      {/* Name */}
      <Text style={[styles.childName, isSelected && styles.childNameSelected]} numberOfLines={1}>
        {formatChildName(child)}
      </Text>

      {/* Selection indicator */}
      <View
        style={[
          styles.checkbox,
          isSelected && styles.checkboxSelected,
        ]}>
        {isSelected && <Text style={styles.checkmark}>{'\u2713'}</Text>}
      </View>
    </TouchableOpacity>
  );
}

/**
 * ChildTagging allows teachers to tag multiple children in a photo.
 * Displays a scrollable list of children with multi-select functionality.
 */
function ChildTagging({
  children,
  selectedIds,
  onSelectionChange,
  maxSelection,
  disabled = false,
  title = 'Tag Children',
  description = 'Select the children who appear in this photo',
}: ChildTaggingProps): React.JSX.Element {
  /**
   * Toggle child selection
   */
  const handleToggle = useCallback(
    (childId: string) => {
      const isCurrentlySelected = selectedIds.includes(childId);

      if (isCurrentlySelected) {
        // Remove from selection
        onSelectionChange(selectedIds.filter(id => id !== childId));
      } else {
        // Add to selection (if under max limit)
        if (maxSelection && selectedIds.length >= maxSelection) {
          AccessibilityInfo.announceForAccessibility(
            `Maximum of ${maxSelection} children can be tagged`,
          );
          return;
        }
        onSelectionChange([...selectedIds, childId]);
      }
    },
    [selectedIds, onSelectionChange, maxSelection],
  );

  /**
   * Select all children
   */
  const handleSelectAll = useCallback(() => {
    const allIds = children.map(child => child.id);
    const limitedIds = maxSelection ? allIds.slice(0, maxSelection) : allIds;
    onSelectionChange(limitedIds);
    AccessibilityInfo.announceForAccessibility(
      `Selected ${limitedIds.length} children`,
    );
  }, [children, maxSelection, onSelectionChange]);

  /**
   * Clear all selections
   */
  const handleClearAll = useCallback(() => {
    onSelectionChange([]);
    AccessibilityInfo.announceForAccessibility('Cleared all selections');
  }, [onSelectionChange]);

  /**
   * Render a child item
   */
  const renderItem = useCallback(
    ({item}: {item: Child}) => (
      <ChildTagItem
        child={item}
        isSelected={selectedIds.includes(item.id)}
        onToggle={handleToggle}
        disabled={disabled}
      />
    ),
    [selectedIds, handleToggle, disabled],
  );

  /**
   * Key extractor
   */
  const keyExtractor = useCallback((item: Child) => item.id, []);

  /**
   * Calculate selected count text
   */
  const selectionText = useMemo(() => {
    const count = selectedIds.length;
    if (count === 0) {
      return 'No children selected';
    }
    if (count === 1) {
      return '1 child selected';
    }
    return `${count} children selected`;
  }, [selectedIds.length]);

  if (children.length === 0) {
    return (
      <View style={styles.container}>
        <View style={styles.emptyState}>
          <Text style={styles.emptyStateText}>
            No children available for tagging.
          </Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.description}>{description}</Text>
      </View>

      {/* Selection summary and actions */}
      <View style={styles.selectionBar}>
        <Text style={styles.selectionText}>{selectionText}</Text>
        <View style={styles.selectionActions}>
          <TouchableOpacity
            onPress={handleSelectAll}
            disabled={disabled}
            style={styles.actionButton}
            accessibilityRole="button"
            accessibilityLabel="Select all children">
            <Text style={[styles.actionText, disabled && styles.actionTextDisabled]}>
              Select All
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            onPress={handleClearAll}
            disabled={disabled || selectedIds.length === 0}
            style={styles.actionButton}
            accessibilityRole="button"
            accessibilityLabel="Clear selection">
            <Text
              style={[
                styles.actionText,
                (disabled || selectedIds.length === 0) && styles.actionTextDisabled,
              ]}>
              Clear
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Child list */}
      <FlatList
        data={children}
        renderItem={renderItem}
        keyExtractor={keyExtractor}
        style={styles.list}
        contentContainerStyle={styles.listContent}
        showsVerticalScrollIndicator={false}
        numColumns={2}
        columnWrapperStyle={styles.columnWrapper}
      />

      {/* Warning for untagged */}
      {selectedIds.length === 0 && (
        <View style={styles.warningBanner}>
          <Text style={styles.warningText}>
            {'\u26A0'} Photos without tagged children won't be visible to parents
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  header: {
    padding: 16,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 4,
  },
  description: {
    fontSize: 14,
    color: '#666666',
    lineHeight: 20,
  },
  selectionBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  selectionText: {
    fontSize: 14,
    color: '#4A90D9',
    fontWeight: '500',
  },
  selectionActions: {
    flexDirection: 'row',
    gap: 16,
  },
  actionButton: {
    paddingVertical: 4,
    paddingHorizontal: 8,
  },
  actionText: {
    fontSize: 14,
    color: '#4A90D9',
    fontWeight: '500',
  },
  actionTextDisabled: {
    color: '#CCCCCC',
  },
  list: {
    flex: 1,
  },
  listContent: {
    padding: 12,
  },
  columnWrapper: {
    gap: 12,
  },
  childItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.08,
    shadowRadius: 2,
    // Android elevation
    elevation: 2,
    // Minimum touch target
    minHeight: 56,
  },
  childItemSelected: {
    backgroundColor: '#E3F2FD',
    borderWidth: 2,
    borderColor: '#4A90D9',
    padding: 10, // Adjust for border
  },
  childItemDisabled: {
    opacity: 0.5,
  },
  avatarContainer: {
    marginRight: 10,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  avatarPlaceholder: {
    backgroundColor: '#4A90D9',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarInitials: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '600',
  },
  childName: {
    flex: 1,
    fontSize: 14,
    color: '#333333',
    fontWeight: '500',
  },
  childNameSelected: {
    color: '#1565C0',
    fontWeight: '600',
  },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#CCCCCC',
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
  },
  checkboxSelected: {
    backgroundColor: '#4A90D9',
    borderColor: '#4A90D9',
  },
  checkmark: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '700',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  emptyStateText: {
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
  },
  warningBanner: {
    backgroundColor: '#FFF8E1',
    padding: 12,
    borderTopWidth: 1,
    borderTopColor: '#FFE082',
  },
  warningText: {
    fontSize: 13,
    color: '#F57C00',
    textAlign: 'center',
    fontWeight: '500',
  },
});

export default ChildTagging;
