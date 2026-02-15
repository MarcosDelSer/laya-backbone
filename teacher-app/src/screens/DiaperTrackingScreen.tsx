/**
 * LAYA Teacher App - DiaperTrackingScreen
 *
 * Main screen for logging diaper changes with quick-log interface.
 * Displays a list of children with their diaper status and allows
 * teachers to quickly log diaper changes with tap interactions.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  TouchableOpacity,
  Modal,
  ScrollView,
  TextInput,
} from 'react-native';
import type {NativeStackScreenProps} from '@react-navigation/native-stack';
import DiaperTypeSelector from '../components/DiaperTypeSelector';
import {
  fetchTodayDiapers,
  logDiaper,
  formatDiaperTime,
  getTimeSinceLastChange,
  getDiaperTypeLabel,
  type ChildWithDiapers,
  type DiapersSummary,
} from '../api/diaperApi';
import type {RootStackParamList, Child, DiaperRecord, DiaperType} from '../types';

type Props = NativeStackScreenProps<RootStackParamList, 'DiaperTracking'>;

/**
 * Local state for a child with diapers
 */
interface ChildDiaperState {
  child: Child;
  diapers: DiaperRecord[];
  lastChange: DiaperRecord | null;
  isLoading: boolean;
}

/**
 * State for the diaper logging modal
 */
interface DiaperModalState {
  visible: boolean;
  child: Child | null;
  existingDiapers: DiaperRecord[];
  selectedType: DiaperType | null;
  notes: string;
  isSubmitting: boolean;
}

/**
 * Format date for header display
 */
function formatDateHeader(date: Date): string {
  return date.toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Get initials from child name for placeholder avatar
 */
function getInitials(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

/**
 * DiaperTrackingScreen displays all children with quick diaper change logging
 */
function DiaperTrackingScreen({route}: Props): React.JSX.Element {
  const [childrenState, setChildrenState] = useState<ChildDiaperState[]>([]);
  const [summary, setSummary] = useState<DiapersSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [modalState, setModalState] = useState<DiaperModalState>({
    visible: false,
    child: null,
    existingDiapers: [],
    selectedType: null,
    notes: '',
    isSubmitting: false,
  });

  /**
   * Load diaper data from API
   */
  const loadDiapers = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchTodayDiapers();

      if (response.success && response.data) {
        const childStates: ChildDiaperState[] = response.data.children.map(
          (item: ChildWithDiapers) => ({
            child: item.child,
            diapers: item.diapers,
            lastChange: item.lastChange,
            isLoading: false,
          }),
        );

        setChildrenState(childStates);
        setSummary(response.data.summary);
      } else {
        // If API fails, use mock data for development
        setChildrenState(getMockChildrenState());
        setSummary(getMockSummary());
      }
    } catch (_err) {
      // Use mock data for development when API is not available
      setChildrenState(getMockChildrenState());
      setSummary(getMockSummary());
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /**
   * Initial load
   */
  useEffect(() => {
    loadDiapers();
  }, [loadDiapers]);

  /**
   * Open the diaper modal for a child
   */
  const openDiaperModal = useCallback(
    (child: Child, existingDiapers: DiaperRecord[]) => {
      setModalState({
        visible: true,
        child,
        existingDiapers,
        selectedType: null,
        notes: '',
        isSubmitting: false,
      });
    },
    [],
  );

  /**
   * Close the diaper modal
   */
  const closeDiaperModal = useCallback(() => {
    setModalState(prev => ({
      ...prev,
      visible: false,
      child: null,
      selectedType: null,
      notes: '',
    }));
  }, []);

  /**
   * Handle diaper type selection in modal
   */
  const handleSelectType = useCallback((type: DiaperType) => {
    setModalState(prev => ({...prev, selectedType: type}));
  }, []);

  /**
   * Handle notes change in modal
   */
  const handleNotesChange = useCallback((text: string) => {
    setModalState(prev => ({...prev, notes: text}));
  }, []);

  /**
   * Submit the diaper log
   */
  const handleSubmitDiaper = useCallback(async () => {
    const {child, selectedType, notes} = modalState;

    if (!child || !selectedType) {
      return;
    }

    setModalState(prev => ({...prev, isSubmitting: true}));

    try {
      const response = await logDiaper(child.id, selectedType, {
        notes: notes || undefined,
      });

      if (response.success && response.data) {
        // Update local state with new diaper record
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === child.id) {
              const newDiapers = [...item.diapers, response.data!.diaperRecord];
              return {
                ...item,
                diapers: newDiapers,
                lastChange: response.data!.diaperRecord,
              };
            }
            return item;
          }),
        );

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            totalChanges: summary.totalChanges + 1,
            wetChanges:
              selectedType === 'wet'
                ? summary.wetChanges + 1
                : summary.wetChanges,
            soiledChanges:
              selectedType === 'soiled'
                ? summary.soiledChanges + 1
                : summary.soiledChanges,
            dryChanges:
              selectedType === 'dry'
                ? summary.dryChanges + 1
                : summary.dryChanges,
          });
        }

        closeDiaperModal();
      } else {
        // For development: simulate successful diaper log
        simulateDiaperLog(child.id, selectedType, notes);
      }
    } catch (_err) {
      // For development: simulate successful diaper log
      simulateDiaperLog(child.id, selectedType, notes);
    }
  }, [modalState, summary, closeDiaperModal]);

  /**
   * Simulate diaper log for development when API is not available
   */
  const simulateDiaperLog = (
    childId: string,
    type: DiaperType,
    notes: string,
  ) => {
    const now = new Date();
    const newDiaper: DiaperRecord = {
      id: `diaper-${childId}-${now.getTime()}`,
      childId,
      date: now.toISOString().split('T')[0],
      time: now.toISOString(),
      type,
      notes: notes || null,
      loggedBy: 'teacher-1',
    };

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId) {
          return {
            ...item,
            diapers: [...item.diapers, newDiaper],
            lastChange: newDiaper,
          };
        }
        return item;
      }),
    );

    if (summary) {
      setSummary({
        ...summary,
        totalChanges: summary.totalChanges + 1,
        wetChanges: type === 'wet' ? summary.wetChanges + 1 : summary.wetChanges,
        soiledChanges:
          type === 'soiled' ? summary.soiledChanges + 1 : summary.soiledChanges,
        dryChanges: type === 'dry' ? summary.dryChanges + 1 : summary.dryChanges,
      });
    }

    closeDiaperModal();
  };

  /**
   * Render a child card for diaper tracking
   */
  const renderChildCard = useCallback(
    ({item}: {item: ChildDiaperState}) => {
      const changeCount = item.diapers.length;
      const lastChange = item.lastChange;

      return (
        <TouchableOpacity
          style={styles.childCard}
          onPress={() => openDiaperModal(item.child, item.diapers)}
          disabled={item.isLoading}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel={`${item.child.firstName} ${item.child.lastName}`}
          accessibilityHint="Tap to log diaper change">
          {/* Avatar Section */}
          <View style={styles.avatarContainer}>
            <View style={[styles.avatar, styles.avatarPlaceholder]}>
              <Text style={styles.avatarInitials}>
                {getInitials(item.child.firstName, item.child.lastName)}
              </Text>
            </View>
          </View>

          {/* Info Section */}
          <View style={styles.infoContainer}>
            <Text style={styles.childName} numberOfLines={1}>
              {item.child.firstName} {item.child.lastName}
            </Text>

            {lastChange ? (
              <View style={styles.lastChangeContainer}>
                <View
                  style={[
                    styles.typeIndicator,
                    lastChange.type === 'wet' && styles.typeIndicatorwet,
                    lastChange.type === 'soiled' && styles.typeIndicatorsoiled,
                    lastChange.type === 'dry' && styles.typeIndicatordry,
                  ]}>
                  <Text style={styles.typeIndicatorText}>
                    {getDiaperTypeLabel(lastChange.type)}
                  </Text>
                </View>
                <Text style={styles.lastChangeTime}>
                  {getTimeSinceLastChange(lastChange.time)}
                </Text>
              </View>
            ) : (
              <Text style={styles.noChangesText}>No changes yet</Text>
            )}
          </View>

          {/* Status Section */}
          <View style={styles.statusContainer}>
            <Text style={styles.changeCountText}>{changeCount}</Text>
            <Text style={styles.statusHint}>
              {changeCount === 1 ? 'change' : 'changes'}
            </Text>
          </View>
        </TouchableOpacity>
      );
    },
    [openDiaperModal],
  );

  /**
   * Render the list header with date and summary
   */
  const renderHeader = () => (
    <View style={styles.header}>
      <Text style={styles.dateText}>{formatDateHeader(new Date())}</Text>
      {summary && (
        <View style={styles.summaryContainer}>
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.wetChanges}</Text>
            <Text style={styles.summaryLabel}>Wet</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.soiledChanges}</Text>
            <Text style={styles.summaryLabel}>Soiled</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.dryChanges}</Text>
            <Text style={styles.summaryLabel}>Dry</Text>
          </View>
        </View>
      )}
    </View>
  );

  /**
   * Render empty state
   */
  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateTitle}>No Children Found</Text>
      <Text style={styles.emptyStateText}>
        There are no children assigned to your classroom.
      </Text>
    </View>
  );

  /**
   * Render the diaper logging modal
   */
  const renderDiaperModal = () => {
    const {visible, child, existingDiapers, selectedType, notes, isSubmitting} =
      modalState;

    if (!child) {
      return null;
    }

    return (
      <Modal
        visible={visible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={closeDiaperModal}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity
              onPress={closeDiaperModal}
              style={styles.modalCloseButton}
              accessibilityLabel="Close">
              <Text style={styles.modalCloseText}>Cancel</Text>
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Log Diaper</Text>
            <TouchableOpacity
              onPress={handleSubmitDiaper}
              style={[
                styles.modalSaveButton,
                (!selectedType || isSubmitting) && styles.modalSaveButtonDisabled,
              ]}
              disabled={!selectedType || isSubmitting}
              accessibilityLabel="Save diaper change">
              <Text
                style={[
                  styles.modalSaveText,
                  (!selectedType || isSubmitting) && styles.modalSaveTextDisabled,
                ]}>
                {isSubmitting ? 'Saving...' : 'Save'}
              </Text>
            </TouchableOpacity>
          </View>

          <ScrollView
            style={styles.modalContent}
            contentContainerStyle={styles.modalContentContainer}
            keyboardShouldPersistTaps="handled">
            {/* Child info */}
            <View style={styles.modalChildInfo}>
              <View style={[styles.avatar, styles.avatarPlaceholder]}>
                <Text style={styles.avatarInitials}>
                  {getInitials(child.firstName, child.lastName)}
                </Text>
              </View>
              <Text style={styles.modalChildName}>
                {child.firstName} {child.lastName}
              </Text>
            </View>

            {/* Diaper type selector */}
            <DiaperTypeSelector
              selectedType={selectedType}
              onSelectType={handleSelectType}
              disabled={isSubmitting}
            />

            {/* Notes input */}
            <View style={styles.notesContainer}>
              <Text style={styles.notesLabel}>Notes (optional)</Text>
              <TextInput
                style={styles.notesInput}
                placeholder="Add any notes about this diaper change..."
                placeholderTextColor="#999999"
                value={notes}
                onChangeText={handleNotesChange}
                multiline
                numberOfLines={3}
                editable={!isSubmitting}
              />
            </View>

            {/* Previous diaper changes */}
            {existingDiapers.length > 0 && (
              <View style={styles.previousChangesContainer}>
                <Text style={styles.previousChangesTitle}>
                  Changes Today ({existingDiapers.length})
                </Text>
                {existingDiapers.slice(-5).reverse().map(diaper => (
                  <View key={diaper.id} style={styles.previousChangeItem}>
                    <View
                      style={[
                        styles.previousChangeType,
                        diaper.type === 'wet' && styles.previousChangeTypewet,
                        diaper.type === 'soiled' && styles.previousChangeTypesoiled,
                        diaper.type === 'dry' && styles.previousChangeTypedry,
                      ]}>
                      <Text style={styles.previousChangeTypeText}>
                        {getDiaperTypeLabel(diaper.type)}
                      </Text>
                    </View>
                    <Text style={styles.previousChangeTime}>
                      {formatDiaperTime(diaper.time)}
                    </Text>
                  </View>
                ))}
              </View>
            )}
          </ScrollView>
        </View>
      </Modal>
    );
  };

  /**
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback(
    (item: ChildDiaperState) => item.child.id,
    [],
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Loading diapers...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={childrenState}
        renderItem={renderChildCard}
        keyExtractor={keyExtractor}
        ListHeaderComponent={renderHeader}
        ListEmptyComponent={renderEmptyState}
        contentContainerStyle={styles.listContent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={() => loadDiapers(true)}
            tintColor="#4A90D9"
          />
        }
      />
      {renderDiaperModal()}
    </View>
  );
}

/**
 * Get mock children state for development
 */
function getMockChildrenState(): ChildDiaperState[] {
  const mockChildren: Child[] = [
    {
      id: 'child-1',
      firstName: 'Emma',
      lastName: 'Johnson',
      photoUrl: null,
      dateOfBirth: '2020-03-15',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-1'],
    },
    {
      id: 'child-2',
      firstName: 'Liam',
      lastName: 'Williams',
      photoUrl: null,
      dateOfBirth: '2019-11-22',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-2'],
    },
    {
      id: 'child-3',
      firstName: 'Olivia',
      lastName: 'Brown',
      photoUrl: null,
      dateOfBirth: '2020-07-08',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-3'],
    },
    {
      id: 'child-4',
      firstName: 'Noah',
      lastName: 'Davis',
      photoUrl: null,
      dateOfBirth: '2020-01-30',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-4'],
    },
    {
      id: 'child-5',
      firstName: 'Ava',
      lastName: 'Miller',
      photoUrl: null,
      dateOfBirth: '2019-09-12',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-5'],
    },
  ];

  return mockChildren.map(child => ({
    child,
    diapers: [],
    lastChange: null,
    isLoading: false,
  }));
}

/**
 * Get mock summary for development
 */
function getMockSummary(): DiapersSummary {
  return {
    totalChanges: 0,
    wetChanges: 0,
    soiledChanges: 0,
    dryChanges: 0,
    childrenChanged: 0,
  };
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666666',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 20,
  },
  errorText: {
    fontSize: 16,
    color: '#C62828',
    textAlign: 'center',
  },
  listContent: {
    paddingBottom: 20,
  },
  header: {
    padding: 16,
    paddingBottom: 8,
  },
  dateText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 12,
  },
  summaryContainer: {
    flexDirection: 'row',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  summaryItem: {
    flex: 1,
    alignItems: 'center',
  },
  summaryNumber: {
    fontSize: 24,
    fontWeight: '700',
    color: '#4A90D9',
  },
  summaryLabel: {
    fontSize: 12,
    color: '#666666',
    marginTop: 4,
  },
  summaryDivider: {
    width: 1,
    backgroundColor: '#E0E0E0',
    marginVertical: 4,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyStateTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 8,
  },
  emptyStateText: {
    fontSize: 14,
    color: '#666666',
    textAlign: 'center',
  },
  childCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 6,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    minHeight: 80,
  },
  avatarContainer: {
    position: 'relative',
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
  },
  avatarPlaceholder: {
    backgroundColor: '#4A90D9',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarInitials: {
    color: '#FFFFFF',
    fontSize: 20,
    fontWeight: '600',
  },
  infoContainer: {
    flex: 1,
    marginLeft: 12,
    justifyContent: 'center',
  },
  childName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 4,
  },
  lastChangeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  typeIndicator: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  typeIndicatorwet: {
    backgroundColor: '#E3F2FD',
  },
  typeIndicatorsoiled: {
    backgroundColor: '#FFF3E0',
  },
  typeIndicatordry: {
    backgroundColor: '#E8F5E9',
  },
  typeIndicatorText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#666666',
  },
  lastChangeTime: {
    fontSize: 12,
    color: '#999999',
  },
  noChangesText: {
    fontSize: 12,
    color: '#999999',
  },
  statusContainer: {
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  changeCountText: {
    fontSize: 24,
    fontWeight: '700',
    color: '#4A90D9',
  },
  statusHint: {
    fontSize: 10,
    color: '#999999',
    marginTop: 2,
  },
  // Modal styles
  modalContainer: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  modalCloseButton: {
    padding: 8,
  },
  modalCloseText: {
    fontSize: 16,
    color: '#4A90D9',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333333',
  },
  modalSaveButton: {
    padding: 8,
  },
  modalSaveButtonDisabled: {
    opacity: 0.5,
  },
  modalSaveText: {
    fontSize: 16,
    color: '#4A90D9',
    fontWeight: '600',
  },
  modalSaveTextDisabled: {
    color: '#999999',
  },
  modalContent: {
    flex: 1,
  },
  modalContentContainer: {
    padding: 16,
  },
  modalChildInfo: {
    alignItems: 'center',
    marginBottom: 24,
  },
  modalChildName: {
    fontSize: 20,
    fontWeight: '600',
    color: '#333333',
    marginTop: 8,
  },
  notesContainer: {
    marginTop: 16,
  },
  notesLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 8,
  },
  notesInput: {
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: '#333333',
    minHeight: 80,
    textAlignVertical: 'top',
  },
  previousChangesContainer: {
    marginTop: 24,
    padding: 12,
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
  },
  previousChangesTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666666',
    marginBottom: 12,
  },
  previousChangeItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  previousChangeType: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 4,
  },
  previousChangeTypewet: {
    backgroundColor: '#E3F2FD',
  },
  previousChangeTypesoiled: {
    backgroundColor: '#FFF3E0',
  },
  previousChangeTypedry: {
    backgroundColor: '#E8F5E9',
  },
  previousChangeTypeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#666666',
  },
  previousChangeTime: {
    fontSize: 14,
    color: '#666666',
  },
});

export default DiaperTrackingScreen;
