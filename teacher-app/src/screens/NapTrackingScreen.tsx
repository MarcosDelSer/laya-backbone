/**
 * LAYA Teacher App - NapTrackingScreen
 *
 * Main screen for tracking naps with start/stop timer functionality.
 * Displays a list of children with their nap status and allows
 * teachers to start and stop naps with duration recording.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  Alert,
  TouchableOpacity,
  Modal,
  ScrollView,
  TextInput,
} from 'react-native';
import type {NativeStackScreenProps} from '@react-navigation/native-stack';
import NapTimer from '../components/NapTimer';
import {useNapTimer} from '../hooks/useNapTimer';
import {
  fetchTodayNaps,
  startNap,
  stopNap,
  formatDuration,
  formatNapTime,
  getNapQualityOptions,
  getNapQualityLabel,
  type ChildWithNaps,
  type NapsSummary,
  type NapQuality,
} from '../api/napApi';
import type {RootStackParamList, Child, NapRecord} from '../types';

type Props = NativeStackScreenProps<RootStackParamList, 'NapTracking'>;

/**
 * Local state for a child with naps
 */
interface ChildNapState {
  child: Child;
  naps: NapRecord[];
  activeNap: NapRecord | null;
  isLoading: boolean;
}

/**
 * State for the nap tracking modal
 */
interface NapModalState {
  visible: boolean;
  child: Child | null;
  activeNap: NapRecord | null;
  existingNaps: NapRecord[];
  selectedQuality: NapQuality | null;
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
 * NapTrackingScreen displays all children with nap tracking functionality
 */
function NapTrackingScreen({route}: Props): React.JSX.Element {
  const [childrenState, setChildrenState] = useState<ChildNapState[]>([]);
  const [summary, setSummary] = useState<NapsSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [modalState, setModalState] = useState<NapModalState>({
    visible: false,
    child: null,
    activeNap: null,
    existingNaps: [],
    selectedQuality: null,
    notes: '',
    isSubmitting: false,
  });

  // Timer hook for the modal
  const [timerState, timerActions] = useNapTimer({
    initialStartTime: modalState.activeNap?.startTime || null,
  });

  /**
   * Load nap data from API
   */
  const loadNaps = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchTodayNaps();

      if (response.success && response.data) {
        const childStates: ChildNapState[] = response.data.children.map(
          (item: ChildWithNaps) => ({
            child: item.child,
            naps: item.naps,
            activeNap: item.activeNap,
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
    loadNaps();
  }, [loadNaps]);

  /**
   * Open the nap modal for a child
   */
  const openNapModal = useCallback(
    (child: Child, activeNap: NapRecord | null, existingNaps: NapRecord[]) => {
      setModalState({
        visible: true,
        child,
        activeNap,
        existingNaps,
        selectedQuality: null,
        notes: '',
        isSubmitting: false,
      });

      // Start timer if there's an active nap
      if (activeNap?.startTime) {
        timerActions.start(activeNap.startTime);
      } else {
        timerActions.reset();
      }
    },
    [timerActions],
  );

  /**
   * Close the nap modal
   */
  const closeNapModal = useCallback(() => {
    setModalState(prev => ({
      ...prev,
      visible: false,
      child: null,
      activeNap: null,
      selectedQuality: null,
      notes: '',
    }));
    timerActions.reset();
  }, [timerActions]);

  /**
   * Handle starting a nap
   */
  const handleStartNap = useCallback(async () => {
    const {child} = modalState;
    if (!child) {
      return;
    }

    setModalState(prev => ({...prev, isSubmitting: true}));

    try {
      const response = await startNap(child.id);

      if (response.success && response.data) {
        const newNap = response.data.napRecord;

        // Update local state
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === child.id) {
              return {
                ...item,
                activeNap: newNap,
                naps: [...item.naps, newNap],
              };
            }
            return item;
          }),
        );

        // Update modal state
        setModalState(prev => ({
          ...prev,
          activeNap: newNap,
          isSubmitting: false,
        }));

        // Start the timer
        timerActions.start(newNap.startTime);

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            totalNaps: summary.totalNaps + 1,
            currentlySleeping: summary.currentlySleeping + 1,
          });
        }
      } else {
        // For development: simulate starting a nap
        simulateStartNap(child.id);
      }
    } catch (_err) {
      // For development: simulate starting a nap
      simulateStartNap(child.id);
    }
  }, [modalState, timerActions, summary]);

  /**
   * Simulate starting a nap for development
   */
  const simulateStartNap = (childId: string) => {
    const now = new Date();
    const newNap: NapRecord = {
      id: `nap-${childId}-${now.getTime()}`,
      childId,
      date: now.toISOString().split('T')[0],
      startTime: now.toISOString(),
      endTime: null,
      durationMinutes: null,
      notes: null,
      loggedBy: 'teacher-1',
    };

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId) {
          return {
            ...item,
            activeNap: newNap,
            naps: [...item.naps, newNap],
          };
        }
        return item;
      }),
    );

    setModalState(prev => ({
      ...prev,
      activeNap: newNap,
      isSubmitting: false,
    }));

    timerActions.start(newNap.startTime);

    if (summary) {
      setSummary({
        ...summary,
        totalNaps: summary.totalNaps + 1,
        currentlySleeping: summary.currentlySleeping + 1,
      });
    }
  };

  /**
   * Handle stopping a nap
   */
  const handleStopNap = useCallback(async () => {
    const {child, activeNap, selectedQuality, notes} = modalState;
    if (!child || !activeNap) {
      return;
    }

    // Require quality selection before stopping
    if (!selectedQuality) {
      Alert.alert(
        'Select Sleep Quality',
        'Please select how the child slept before stopping the nap.',
      );
      return;
    }

    setModalState(prev => ({...prev, isSubmitting: true}));

    try {
      const response = await stopNap(child.id, activeNap.id, {
        quality: selectedQuality,
        notes: notes || undefined,
      });

      if (response.success && response.data) {
        const updatedNap = response.data.napRecord;

        // Update local state
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === child.id) {
              return {
                ...item,
                activeNap: null,
                naps: item.naps.map(nap =>
                  nap.id === activeNap.id ? updatedNap : nap,
                ),
              };
            }
            return item;
          }),
        );

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            currentlySleeping: Math.max(0, summary.currentlySleeping - 1),
            completedNaps: summary.completedNaps + 1,
            childrenNapped: summary.childrenNapped + 1,
          });
        }

        closeNapModal();
      } else {
        // For development: simulate stopping a nap
        simulateStopNap(child.id, activeNap, selectedQuality, notes);
      }
    } catch (_err) {
      // For development: simulate stopping a nap
      simulateStopNap(child.id, activeNap, selectedQuality, notes);
    }
  }, [modalState, summary, closeNapModal]);

  /**
   * Simulate stopping a nap for development
   */
  const simulateStopNap = (
    childId: string,
    activeNap: NapRecord,
    quality: NapQuality,
    notes: string,
  ) => {
    const now = new Date();
    const startDate = new Date(activeNap.startTime);
    const durationMinutes = Math.round(
      (now.getTime() - startDate.getTime()) / 60000,
    );

    const updatedNap: NapRecord = {
      ...activeNap,
      endTime: now.toISOString(),
      durationMinutes,
      notes: notes || null,
    };

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId) {
          return {
            ...item,
            activeNap: null,
            naps: item.naps.map(nap =>
              nap.id === activeNap.id ? updatedNap : nap,
            ),
          };
        }
        return item;
      }),
    );

    if (summary) {
      setSummary({
        ...summary,
        currentlySleeping: Math.max(0, summary.currentlySleeping - 1),
        completedNaps: summary.completedNaps + 1,
        childrenNapped: summary.childrenNapped + 1,
      });
    }

    closeNapModal();
  };

  /**
   * Handle quality selection
   */
  const handleSelectQuality = useCallback((quality: NapQuality) => {
    setModalState(prev => ({...prev, selectedQuality: quality}));
  }, []);

  /**
   * Handle notes change
   */
  const handleNotesChange = useCallback((text: string) => {
    setModalState(prev => ({...prev, notes: text}));
  }, []);

  /**
   * Get total nap time for a child
   */
  const getTotalNapMinutes = (naps: NapRecord[]): number => {
    return naps.reduce((total, nap) => {
      if (nap.durationMinutes !== null) {
        return total + nap.durationMinutes;
      }
      return total;
    }, 0);
  };

  /**
   * Render a child card for nap tracking
   */
  const renderChildCard = useCallback(
    ({item}: {item: ChildNapState}) => {
      const isNapping = item.activeNap !== null;
      const completedNaps = item.naps.filter(nap => nap.endTime !== null);
      const totalMinutes = getTotalNapMinutes(completedNaps);

      return (
        <TouchableOpacity
          style={[styles.childCard, isNapping && styles.childCardNapping]}
          onPress={() => openNapModal(item.child, item.activeNap, item.naps)}
          disabled={item.isLoading}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel={`${item.child.firstName} ${item.child.lastName}`}
          accessibilityHint={
            isNapping ? 'Currently napping. Tap to stop.' : 'Tap to start nap'
          }>
          {/* Avatar Section */}
          <View style={styles.avatarContainer}>
            <View style={[styles.avatar, styles.avatarPlaceholder]}>
              <Text style={styles.avatarInitials}>
                {getInitials(item.child.firstName, item.child.lastName)}
              </Text>
            </View>
            {isNapping && (
              <View style={styles.nappingIndicator}>
                <Text style={styles.nappingIndicatorText}>Z</Text>
              </View>
            )}
          </View>

          {/* Info Section */}
          <View style={styles.infoContainer}>
            <Text style={styles.childName} numberOfLines={1}>
              {item.child.firstName} {item.child.lastName}
            </Text>

            {isNapping && item.activeNap && (
              <Text style={styles.nappingText}>
                Started {formatNapTime(item.activeNap.startTime)}
              </Text>
            )}

            {!isNapping && completedNaps.length > 0 && (
              <Text style={styles.napSummaryText}>
                {completedNaps.length} nap{completedNaps.length !== 1 ? 's' : ''}{' '}
                - {formatDuration(totalMinutes)}
              </Text>
            )}

            {!isNapping && completedNaps.length === 0 && (
              <Text style={styles.noNapsText}>No naps yet</Text>
            )}
          </View>

          {/* Status Section */}
          <View style={styles.statusContainer}>
            <View
              style={[
                styles.statusBadge,
                isNapping ? styles.statusBadgeNapping : styles.statusBadgeAwake,
              ]}>
              <Text
                style={[
                  styles.statusBadgeText,
                  isNapping
                    ? styles.statusBadgeTextNapping
                    : styles.statusBadgeTextAwake,
                ]}>
                {isNapping ? 'Napping' : 'Awake'}
              </Text>
            </View>
            <Text style={styles.statusHint}>
              {isNapping ? 'Tap to stop' : 'Tap to start'}
            </Text>
          </View>
        </TouchableOpacity>
      );
    },
    [openNapModal],
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
            <Text style={styles.summaryNumber}>{summary.currentlySleeping}</Text>
            <Text style={styles.summaryLabel}>Sleeping</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.completedNaps}</Text>
            <Text style={styles.summaryLabel}>Completed</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.childrenNapped}</Text>
            <Text style={styles.summaryLabel}>Napped</Text>
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
   * Render the nap tracking modal
   */
  const renderNapModal = () => {
    const {
      visible,
      child,
      activeNap,
      existingNaps,
      selectedQuality,
      notes,
      isSubmitting,
    } = modalState;

    if (!child) {
      return null;
    }

    const completedNaps = existingNaps.filter(nap => nap.endTime !== null);
    const isNapping = activeNap !== null;

    return (
      <Modal
        visible={visible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={closeNapModal}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity
              onPress={closeNapModal}
              style={styles.modalCloseButton}
              accessibilityLabel="Close">
              <Text style={styles.modalCloseText}>Close</Text>
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Nap Tracking</Text>
            <View style={styles.modalHeaderSpacer} />
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

            {/* Timer */}
            <View style={styles.timerContainer}>
              <NapTimer
                timerState={timerState}
                onStart={handleStartNap}
                onStop={handleStopNap}
                loading={isSubmitting}
                disabled={isNapping && !selectedQuality}
                childName={`${child.firstName} ${child.lastName}`}
              />
            </View>

            {/* Quality selector (only when napping) */}
            {isNapping && (
              <View style={styles.qualityContainer}>
                <Text style={styles.qualityLabel}>Sleep Quality</Text>
                <View style={styles.qualityOptions}>
                  {getNapQualityOptions().map(quality => (
                    <TouchableOpacity
                      key={quality}
                      style={[
                        styles.qualityOption,
                        selectedQuality === quality &&
                          styles.qualityOptionSelected,
                      ]}
                      onPress={() => handleSelectQuality(quality)}
                      disabled={isSubmitting}
                      accessibilityRole="button"
                      accessibilityState={{selected: selectedQuality === quality}}>
                      <Text
                        style={[
                          styles.qualityOptionText,
                          selectedQuality === quality &&
                            styles.qualityOptionTextSelected,
                        ]}>
                        {getNapQualityLabel(quality)}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>
            )}

            {/* Notes input (only when napping) */}
            {isNapping && (
              <View style={styles.notesContainer}>
                <Text style={styles.notesLabel}>Notes (optional)</Text>
                <TextInput
                  style={styles.notesInput}
                  placeholder="Add any notes about this nap..."
                  placeholderTextColor="#999999"
                  value={notes}
                  onChangeText={handleNotesChange}
                  multiline
                  numberOfLines={3}
                  editable={!isSubmitting}
                />
              </View>
            )}

            {/* Previous naps */}
            {completedNaps.length > 0 && (
              <View style={styles.previousNapsContainer}>
                <Text style={styles.previousNapsTitle}>Previous Naps Today</Text>
                {completedNaps.map(nap => (
                  <View key={nap.id} style={styles.previousNapItem}>
                    <View style={styles.previousNapTime}>
                      <Text style={styles.previousNapTimeText}>
                        {formatNapTime(nap.startTime)}
                        {nap.endTime && ` - ${formatNapTime(nap.endTime)}`}
                      </Text>
                    </View>
                    <Text style={styles.previousNapDuration}>
                      {nap.durationMinutes !== null
                        ? formatDuration(nap.durationMinutes)
                        : '--'}
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
  const keyExtractor = useCallback((item: ChildNapState) => item.child.id, []);

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Loading naps...</Text>
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
            onRefresh={() => loadNaps(true)}
            tintColor="#4A90D9"
          />
        }
      />
      {renderNapModal()}
    </View>
  );
}

/**
 * Get mock children state for development
 */
function getMockChildrenState(): ChildNapState[] {
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
    naps: [],
    activeNap: null,
    isLoading: false,
  }));
}

/**
 * Get mock summary for development
 */
function getMockSummary(): NapsSummary {
  return {
    totalNaps: 0,
    childrenNapped: 0,
    currentlySleeping: 0,
    completedNaps: 0,
    avgDurationMinutes: 0,
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
  childCardNapping: {
    borderLeftWidth: 4,
    borderLeftColor: '#9C27B0',
    backgroundColor: '#F3E5F5',
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
  nappingIndicator: {
    position: 'absolute',
    bottom: -2,
    right: -2,
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#9C27B0',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#FFFFFF',
  },
  nappingIndicatorText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
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
  nappingText: {
    fontSize: 12,
    color: '#7B1FA2',
    fontWeight: '500',
  },
  napSummaryText: {
    fontSize: 12,
    color: '#666666',
  },
  noNapsText: {
    fontSize: 12,
    color: '#999999',
  },
  statusContainer: {
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusBadgeNapping: {
    backgroundColor: '#CE93D8',
  },
  statusBadgeAwake: {
    backgroundColor: '#E0E0E0',
  },
  statusBadgeText: {
    fontSize: 12,
    fontWeight: '600',
  },
  statusBadgeTextNapping: {
    color: '#4A148C',
  },
  statusBadgeTextAwake: {
    color: '#666666',
  },
  statusHint: {
    fontSize: 10,
    color: '#999999',
    marginTop: 4,
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
  modalHeaderSpacer: {
    width: 60,
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
  timerContainer: {
    alignItems: 'center',
    marginBottom: 24,
  },
  qualityContainer: {
    marginBottom: 16,
  },
  qualityLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 12,
  },
  qualityOptions: {
    flexDirection: 'row',
    gap: 8,
  },
  qualityOption: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 8,
    borderRadius: 8,
    backgroundColor: '#F5F5F5',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  qualityOptionSelected: {
    backgroundColor: '#E3F2FD',
    borderColor: '#4A90D9',
  },
  qualityOptionText: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666666',
  },
  qualityOptionTextSelected: {
    color: '#1976D2',
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
  previousNapsContainer: {
    marginTop: 24,
    padding: 12,
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
  },
  previousNapsTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666666',
    marginBottom: 12,
  },
  previousNapItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  previousNapTime: {
    flex: 1,
  },
  previousNapTimeText: {
    fontSize: 14,
    color: '#333333',
  },
  previousNapDuration: {
    fontSize: 14,
    fontWeight: '600',
    color: '#4A90D9',
  },
});

export default NapTrackingScreen;
