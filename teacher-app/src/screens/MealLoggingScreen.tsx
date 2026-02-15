/**
 * LAYA Teacher App - MealLoggingScreen
 *
 * Main screen for logging meals with meal type selection, allergy alerts,
 * and portion tracking. Displays a list of children with their meal status
 * and allows quick meal logging with tap interactions.
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
import MealSelector from '../components/MealSelector';
import AllergyAlert from '../components/AllergyAlert';
import PortionSelector from '../components/PortionSelector';
import {
  fetchTodayMeals,
  logMeal,
  getSuggestedMealType,
  hasMealLogged,
  getMealTypeLabel,
  getPortionLabel,
  type ChildWithMeals,
  type MealsSummary,
} from '../api/mealApi';
import type {
  RootStackParamList,
  Child,
  MealRecord,
  MealType,
  PortionSize,
} from '../types';

type Props = NativeStackScreenProps<RootStackParamList, 'MealLogging'>;

/**
 * Local state for a child with meals
 */
interface ChildMealState {
  child: Child;
  meals: MealRecord[];
  isLoading: boolean;
}

/**
 * State for the meal logging modal
 */
interface MealLogModalState {
  visible: boolean;
  child: Child | null;
  existingMeals: MealRecord[];
  selectedMealType: MealType | null;
  selectedPortion: PortionSize | null;
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
 * MealLoggingScreen displays all children with tap-to-log-meal functionality
 */
function MealLoggingScreen({route}: Props): React.JSX.Element {
  const [childrenState, setChildrenState] = useState<ChildMealState[]>([]);
  const [summary, setSummary] = useState<MealsSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [modalState, setModalState] = useState<MealLogModalState>({
    visible: false,
    child: null,
    existingMeals: [],
    selectedMealType: null,
    selectedPortion: null,
    notes: '',
    isSubmitting: false,
  });

  const suggestedMealType = getSuggestedMealType();

  /**
   * Load meal data from API
   */
  const loadMeals = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchTodayMeals();

      if (response.success && response.data) {
        const childStates: ChildMealState[] = response.data.children.map(
          (item: ChildWithMeals) => ({
            child: item.child,
            meals: item.meals,
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
    loadMeals();
  }, [loadMeals]);

  /**
   * Open the meal logging modal for a child
   */
  const openMealModal = useCallback((child: Child, existingMeals: MealRecord[]) => {
    setModalState({
      visible: true,
      child,
      existingMeals,
      selectedMealType: suggestedMealType,
      selectedPortion: null,
      notes: '',
      isSubmitting: false,
    });
  }, [suggestedMealType]);

  /**
   * Close the meal logging modal
   */
  const closeMealModal = useCallback(() => {
    setModalState(prev => ({
      ...prev,
      visible: false,
      child: null,
      selectedMealType: null,
      selectedPortion: null,
      notes: '',
    }));
  }, []);

  /**
   * Handle meal type selection in modal
   */
  const handleSelectMealType = useCallback((mealType: MealType) => {
    setModalState(prev => ({...prev, selectedMealType: mealType}));
  }, []);

  /**
   * Handle portion selection in modal
   */
  const handleSelectPortion = useCallback((portion: PortionSize) => {
    setModalState(prev => ({...prev, selectedPortion: portion}));
  }, []);

  /**
   * Handle notes change in modal
   */
  const handleNotesChange = useCallback((text: string) => {
    setModalState(prev => ({...prev, notes: text}));
  }, []);

  /**
   * Submit the meal log
   */
  const handleSubmitMeal = useCallback(async () => {
    const {child, selectedMealType, selectedPortion, notes} = modalState;

    if (!child || !selectedMealType || !selectedPortion) {
      Alert.alert('Missing Information', 'Please select a meal type and portion size.');
      return;
    }

    setModalState(prev => ({...prev, isSubmitting: true}));

    try {
      const response = await logMeal(
        child.id,
        selectedMealType,
        selectedPortion,
        {notes: notes || undefined},
      );

      if (response.success && response.data) {
        // Update local state with new meal record
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === child.id) {
              return {
                ...item,
                meals: [...item.meals, response.data!.mealRecord],
              };
            }
            return item;
          }),
        );

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            mealsLogged: summary.mealsLogged + 1,
            breakfastLogged:
              selectedMealType === 'breakfast'
                ? summary.breakfastLogged + 1
                : summary.breakfastLogged,
            lunchLogged:
              selectedMealType === 'lunch'
                ? summary.lunchLogged + 1
                : summary.lunchLogged,
            snacksLogged:
              selectedMealType === 'snack'
                ? summary.snacksLogged + 1
                : summary.snacksLogged,
          });
        }

        closeMealModal();
      } else {
        // For development: simulate successful meal log
        simulateMealLog(child.id, selectedMealType, selectedPortion, notes);
      }
    } catch (_err) {
      // For development: simulate successful meal log
      simulateMealLog(
        child.id,
        selectedMealType,
        selectedPortion,
        notes,
      );
    }
  }, [modalState, summary, closeMealModal]);

  /**
   * Simulate meal log for development when API is not available
   */
  const simulateMealLog = (
    childId: string,
    mealType: MealType,
    portion: PortionSize,
    notes: string,
  ) => {
    const now = new Date();
    const newMeal: MealRecord = {
      id: `meal-${childId}-${now.getTime()}`,
      childId,
      date: now.toISOString().split('T')[0],
      mealType,
      foodItems: [],
      portion,
      notes: notes || null,
      loggedBy: 'teacher-1',
      loggedAt: now.toISOString(),
    };

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId) {
          return {
            ...item,
            meals: [...item.meals, newMeal],
          };
        }
        return item;
      }),
    );

    if (summary) {
      setSummary({
        ...summary,
        mealsLogged: summary.mealsLogged + 1,
        breakfastLogged:
          mealType === 'breakfast'
            ? summary.breakfastLogged + 1
            : summary.breakfastLogged,
        lunchLogged:
          mealType === 'lunch' ? summary.lunchLogged + 1 : summary.lunchLogged,
        snacksLogged:
          mealType === 'snack' ? summary.snacksLogged + 1 : summary.snacksLogged,
      });
    }

    closeMealModal();
  };

  /**
   * Get logged meal types for a child
   */
  const getLoggedMealTypes = (meals: MealRecord[]): MealType[] => {
    return meals.map(meal => meal.mealType);
  };

  /**
   * Render a child card for meal logging
   */
  const renderChildCard = useCallback(
    ({item}: {item: ChildMealState}) => {
      const loggedMealTypes = getLoggedMealTypes(item.meals);
      const allMealsLogged = loggedMealTypes.length >= 3;

      return (
        <TouchableOpacity
          style={[
            styles.childCard,
            allMealsLogged && styles.childCardComplete,
          ]}
          onPress={() => openMealModal(item.child, item.meals)}
          disabled={item.isLoading}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel={`${item.child.firstName} ${item.child.lastName}`}
          accessibilityHint="Tap to log a meal">
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

            {/* Meal status indicators */}
            <View style={styles.mealStatusContainer}>
              {(['breakfast', 'lunch', 'snack'] as MealType[]).map(mealType => {
                const isLogged = hasMealLogged(item.meals, mealType);
                return (
                  <View
                    key={mealType}
                    style={[
                      styles.mealIndicator,
                      isLogged
                        ? styles.mealIndicatorLogged
                        : styles.mealIndicatorPending,
                    ]}>
                    <Text
                      style={[
                        styles.mealIndicatorText,
                        isLogged
                          ? styles.mealIndicatorTextLogged
                          : styles.mealIndicatorTextPending,
                      ]}>
                      {getMealTypeLabel(mealType).charAt(0)}
                    </Text>
                  </View>
                );
              })}
            </View>

            {/* Allergy indicator */}
            {item.child.allergies.length > 0 && (
              <AllergyAlert allergies={item.child.allergies} compact />
            )}
          </View>

          {/* Status Section */}
          <View style={styles.statusContainer}>
            <Text style={styles.mealsLoggedText}>
              {item.meals.length}/3
            </Text>
            <Text style={styles.statusHint}>
              {allMealsLogged ? 'Complete' : 'Tap to log'}
            </Text>
          </View>
        </TouchableOpacity>
      );
    },
    [openMealModal],
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
            <Text style={styles.summaryNumber}>{summary.breakfastLogged}</Text>
            <Text style={styles.summaryLabel}>Breakfast</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.lunchLogged}</Text>
            <Text style={styles.summaryLabel}>Lunch</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.snacksLogged}</Text>
            <Text style={styles.summaryLabel}>Snack</Text>
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
   * Render the meal logging modal
   */
  const renderMealModal = () => {
    const {
      visible,
      child,
      existingMeals,
      selectedMealType,
      selectedPortion,
      notes,
      isSubmitting,
    } = modalState;

    if (!child) {
      return null;
    }

    const loggedMealTypes = getLoggedMealTypes(existingMeals);

    return (
      <Modal
        visible={visible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={closeMealModal}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity
              onPress={closeMealModal}
              style={styles.modalCloseButton}
              accessibilityLabel="Close">
              <Text style={styles.modalCloseText}>Cancel</Text>
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Log Meal</Text>
            <TouchableOpacity
              onPress={handleSubmitMeal}
              style={[
                styles.modalSaveButton,
                (!selectedMealType || !selectedPortion || isSubmitting) &&
                  styles.modalSaveButtonDisabled,
              ]}
              disabled={!selectedMealType || !selectedPortion || isSubmitting}
              accessibilityLabel="Save meal">
              <Text
                style={[
                  styles.modalSaveText,
                  (!selectedMealType || !selectedPortion || isSubmitting) &&
                    styles.modalSaveTextDisabled,
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

            {/* Allergy alert */}
            {child.allergies.length > 0 && (
              <AllergyAlert allergies={child.allergies} />
            )}

            {/* Meal type selector */}
            <MealSelector
              selectedMealType={selectedMealType}
              onSelectMealType={handleSelectMealType}
              loggedMealTypes={loggedMealTypes}
              suggestedMealType={suggestedMealType}
              disabled={isSubmitting}
            />

            {/* Portion selector */}
            <PortionSelector
              selectedPortion={selectedPortion}
              onSelectPortion={handleSelectPortion}
              disabled={isSubmitting}
            />

            {/* Notes input */}
            <View style={styles.notesContainer}>
              <Text style={styles.notesLabel}>Notes (optional)</Text>
              <TextInput
                style={styles.notesInput}
                placeholder="Add any notes about this meal..."
                placeholderTextColor="#999999"
                value={notes}
                onChangeText={handleNotesChange}
                multiline
                numberOfLines={3}
                editable={!isSubmitting}
              />
            </View>

            {/* Already logged meals */}
            {existingMeals.length > 0 && (
              <View style={styles.existingMealsContainer}>
                <Text style={styles.existingMealsTitle}>Meals Logged Today</Text>
                {existingMeals.map(meal => (
                  <View key={meal.id} style={styles.existingMealItem}>
                    <Text style={styles.existingMealType}>
                      {getMealTypeLabel(meal.mealType)}
                    </Text>
                    <Text style={styles.existingMealPortion}>
                      {getPortionLabel(meal.portion)}
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
    (item: ChildMealState) => item.child.id,
    [],
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Loading meals...</Text>
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
            onRefresh={() => loadMeals(true)}
            tintColor="#4A90D9"
          />
        }
      />
      {renderMealModal()}
    </View>
  );
}

/**
 * Get mock children state for development
 */
function getMockChildrenState(): ChildMealState[] {
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
      allergies: [{id: 'allergy-1', allergen: 'Peanuts', severity: 'severe', notes: null}],
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
      allergies: [
        {id: 'allergy-2', allergen: 'Dairy', severity: 'moderate', notes: null},
        {id: 'allergy-3', allergen: 'Eggs', severity: 'mild', notes: null},
      ],
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
    meals: [],
    isLoading: false,
  }));
}

/**
 * Get mock summary for development
 */
function getMockSummary(): MealsSummary {
  return {
    totalChildren: 5,
    mealsLogged: 0,
    breakfastLogged: 0,
    lunchLogged: 0,
    snacksLogged: 0,
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
  childCardComplete: {
    backgroundColor: '#F5F5F5',
    opacity: 0.9,
    borderLeftWidth: 4,
    borderLeftColor: '#4CAF50',
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
  mealStatusContainer: {
    flexDirection: 'row',
    gap: 6,
    marginBottom: 4,
  },
  mealIndicator: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  mealIndicatorLogged: {
    backgroundColor: '#E8F5E9',
  },
  mealIndicatorPending: {
    backgroundColor: '#F5F5F5',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  mealIndicatorText: {
    fontSize: 11,
    fontWeight: '600',
  },
  mealIndicatorTextLogged: {
    color: '#2E7D32',
  },
  mealIndicatorTextPending: {
    color: '#999999',
  },
  statusContainer: {
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  mealsLoggedText: {
    fontSize: 18,
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
    marginBottom: 16,
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
  existingMealsContainer: {
    marginTop: 24,
    padding: 12,
    backgroundColor: '#F5F5F5',
    borderRadius: 8,
  },
  existingMealsTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666666',
    marginBottom: 8,
  },
  existingMealItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 4,
  },
  existingMealType: {
    fontSize: 14,
    color: '#333333',
  },
  existingMealPortion: {
    fontSize: 14,
    color: '#666666',
  },
});

export default MealLoggingScreen;
