/**
 * LAYA Teacher App - AttendanceScreen
 *
 * Main screen for managing daily attendance with tap-to-check-in/check-out
 * functionality. Displays a list of children in the classroom with their
 * current attendance status.
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
} from 'react-native';
import ChildCard from '../components/ChildCard';
import StatusBadge from '../components/StatusBadge';
import {
  fetchTodayAttendance,
  checkInChild,
  checkOutChild,
  calculateAttendanceStatus,
  isLateArrival,
  isEarlyDeparture,
  type ChildWithAttendance,
  type AttendanceSummary,
} from '../api/attendanceApi';
import type {Child, AttendanceRecord, AttendanceStatus} from '../types';

/**
 * Local state for a child with attendance
 */
interface ChildAttendanceState {
  child: Child;
  attendance: AttendanceRecord | null;
  status: AttendanceStatus;
  isLoading: boolean;
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
 * AttendanceScreen displays all children with tap-to-check-in/out functionality
 */
function AttendanceScreen(): React.JSX.Element {
  const [childrenState, setChildrenState] = useState<ChildAttendanceState[]>([]);
  const [summary, setSummary] = useState<AttendanceSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Load attendance data from API
   */
  const loadAttendance = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchTodayAttendance();

      if (response.success && response.data) {
        const childStates: ChildAttendanceState[] = response.data.children.map(
          (item: ChildWithAttendance) => ({
            child: item.child,
            attendance: item.attendance,
            status: calculateAttendanceStatus(item.attendance),
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
    } catch (err) {
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
    loadAttendance();
  }, [loadAttendance]);

  /**
   * Handle check-in for a child
   */
  const handleCheckIn = useCallback(async (childId: string) => {
    // Set loading state for this child
    setChildrenState(prev =>
      prev.map(item =>
        item.child.id === childId ? {...item, isLoading: true} : item,
      ),
    );

    const isLate = isLateArrival();

    if (isLate) {
      Alert.alert(
        'Late Arrival',
        'This child is arriving after the standard check-in time. Continue with check-in?',
        [
          {
            text: 'Cancel',
            style: 'cancel',
            onPress: () => {
              setChildrenState(prev =>
                prev.map(item =>
                  item.child.id === childId ? {...item, isLoading: false} : item,
                ),
              );
            },
          },
          {
            text: 'Check In',
            onPress: () => performCheckIn(childId, true),
          },
        ],
      );
    } else {
      await performCheckIn(childId, false);
    }
  }, []);

  /**
   * Perform the actual check-in API call
   */
  const performCheckIn = async (childId: string, lateArrival: boolean) => {
    try {
      const response = await checkInChild(childId, {lateArrival});

      if (response.success && response.data) {
        // Update local state with new attendance record
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === childId) {
              return {
                ...item,
                attendance: response.data!.attendanceRecord,
                status: calculateAttendanceStatus(response.data!.attendanceRecord),
                isLoading: false,
              };
            }
            return item;
          }),
        );

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            totalCheckedIn: summary.totalCheckedIn + 1,
            currentlyPresent: summary.currentlyPresent + 1,
            totalAbsent: Math.max(0, summary.totalAbsent - 1),
            totalLateArrivals: lateArrival
              ? summary.totalLateArrivals + 1
              : summary.totalLateArrivals,
          });
        }
      } else {
        // For development: simulate successful check-in
        simulateCheckIn(childId, lateArrival);
      }
    } catch (err) {
      // For development: simulate successful check-in
      simulateCheckIn(childId, lateArrival);
    }
  };

  /**
   * Simulate check-in for development when API is not available
   */
  const simulateCheckIn = (childId: string, lateArrival: boolean) => {
    const now = new Date();
    const newAttendance: AttendanceRecord = {
      id: `attendance-${childId}-${now.getTime()}`,
      childId,
      date: now.toISOString().split('T')[0],
      checkInTime: now.toISOString(),
      checkOutTime: null,
      status: lateArrival ? 'late' : 'present',
      checkedInBy: 'teacher-1',
      checkedOutBy: null,
      notes: null,
    };

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId) {
          return {
            ...item,
            attendance: newAttendance,
            status: lateArrival ? 'late' : 'present',
            isLoading: false,
          };
        }
        return item;
      }),
    );

    if (summary) {
      setSummary({
        ...summary,
        totalCheckedIn: summary.totalCheckedIn + 1,
        currentlyPresent: summary.currentlyPresent + 1,
        totalAbsent: Math.max(0, summary.totalAbsent - 1),
        totalLateArrivals: lateArrival
          ? summary.totalLateArrivals + 1
          : summary.totalLateArrivals,
      });
    }
  };

  /**
   * Handle check-out for a child
   */
  const handleCheckOut = useCallback(async (childId: string) => {
    const childState = childrenState.find(item => item.child.id === childId);
    if (!childState?.attendance) {
      return;
    }

    // Set loading state for this child
    setChildrenState(prev =>
      prev.map(item =>
        item.child.id === childId ? {...item, isLoading: true} : item,
      ),
    );

    const isEarly = isEarlyDeparture();

    if (isEarly) {
      Alert.alert(
        'Early Departure',
        'This child is leaving before the standard checkout time. Continue with checkout?',
        [
          {
            text: 'Cancel',
            style: 'cancel',
            onPress: () => {
              setChildrenState(prev =>
                prev.map(item =>
                  item.child.id === childId ? {...item, isLoading: false} : item,
                ),
              );
            },
          },
          {
            text: 'Check Out',
            onPress: () => performCheckOut(childId, childState.attendance!.id, true),
          },
        ],
      );
    } else {
      await performCheckOut(childId, childState.attendance.id, false);
    }
  }, [childrenState, summary]);

  /**
   * Perform the actual check-out API call
   */
  const performCheckOut = async (
    childId: string,
    attendanceId: string,
    earlyDeparture: boolean,
  ) => {
    try {
      const response = await checkOutChild(childId, attendanceId, {earlyDeparture});

      if (response.success && response.data) {
        // Update local state with new attendance record
        setChildrenState(prev =>
          prev.map(item => {
            if (item.child.id === childId) {
              return {
                ...item,
                attendance: response.data!.attendanceRecord,
                status: earlyDeparture ? 'early_pickup' : item.status,
                isLoading: false,
              };
            }
            return item;
          }),
        );

        // Update summary
        if (summary) {
          setSummary({
            ...summary,
            totalCheckedOut: summary.totalCheckedOut + 1,
            currentlyPresent: Math.max(0, summary.currentlyPresent - 1),
          });
        }
      } else {
        // For development: simulate successful check-out
        simulateCheckOut(childId, earlyDeparture);
      }
    } catch (err) {
      // For development: simulate successful check-out
      simulateCheckOut(childId, earlyDeparture);
    }
  };

  /**
   * Simulate check-out for development when API is not available
   */
  const simulateCheckOut = (childId: string, earlyDeparture: boolean) => {
    const now = new Date();

    setChildrenState(prev =>
      prev.map(item => {
        if (item.child.id === childId && item.attendance) {
          const updatedAttendance: AttendanceRecord = {
            ...item.attendance,
            checkOutTime: now.toISOString(),
            status: earlyDeparture ? 'early_pickup' : item.attendance.status,
            checkedOutBy: 'teacher-1',
          };
          return {
            ...item,
            attendance: updatedAttendance,
            status: earlyDeparture ? 'early_pickup' : item.status,
            isLoading: false,
          };
        }
        return item;
      }),
    );

    if (summary) {
      setSummary({
        ...summary,
        totalCheckedOut: summary.totalCheckedOut + 1,
        currentlyPresent: Math.max(0, summary.currentlyPresent - 1),
      });
    }
  };

  /**
   * Render a child card item
   */
  const renderChildCard = useCallback(
    ({item}: {item: ChildAttendanceState}) => (
      <ChildCard
        child={item.child}
        status={item.status}
        checkInTime={item.attendance?.checkInTime}
        checkOutTime={item.attendance?.checkOutTime}
        onCheckIn={handleCheckIn}
        onCheckOut={handleCheckOut}
        loading={item.isLoading}
      />
    ),
    [handleCheckIn, handleCheckOut],
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
            <Text style={styles.summaryNumber}>{summary.currentlyPresent}</Text>
            <Text style={styles.summaryLabel}>Present</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.totalAbsent}</Text>
            <Text style={styles.summaryLabel}>Absent</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNumber}>{summary.totalCheckedOut}</Text>
            <Text style={styles.summaryLabel}>Left</Text>
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
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback(
    (item: ChildAttendanceState) => item.child.id,
    [],
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Loading attendance...</Text>
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
            onRefresh={() => loadAttendance(true)}
            tintColor="#4A90D9"
          />
        }
      />
    </View>
  );
}

/**
 * Get mock children state for development
 */
function getMockChildrenState(): ChildAttendanceState[] {
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
    attendance: null,
    status: 'absent' as AttendanceStatus,
    isLoading: false,
  }));
}

/**
 * Get mock summary for development
 */
function getMockSummary(): AttendanceSummary {
  return {
    totalChildren: 5,
    totalCheckedIn: 0,
    totalCheckedOut: 0,
    currentlyPresent: 0,
    totalAbsent: 5,
    totalLateArrivals: 0,
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
});

export default AttendanceScreen;
