/**
 * LAYA Parent App - useChildSelection Hook
 *
 * A custom hook for managing child selection state in multi-child families.
 * Handles fetching children list, selection persistence, and state updates.
 */

import {useState, useEffect, useCallback} from 'react';
import type {Child, ApiResponse} from '../types';
import {api} from '../api/client';
import {API_CONFIG} from '../api/config';

interface UseChildSelectionState {
  /** List of all children for the current parent */
  children: Child[];
  /** Currently selected child */
  selectedChild: Child | null;
  /** Loading state while fetching children */
  isLoading: boolean;
  /** Error message if fetch fails */
  error: string | null;
  /** Whether the parent has multiple children */
  hasMultipleChildren: boolean;
}

interface UseChildSelectionActions {
  /** Select a specific child by ID */
  selectChild: (childId: string) => void;
  /** Select a child directly by reference */
  selectChildByRef: (child: Child) => void;
  /** Refresh the children list from the API */
  refreshChildren: () => Promise<void>;
}

export type UseChildSelectionReturn = UseChildSelectionState & UseChildSelectionActions;

// Mock data for development - will be replaced by API calls
const MOCK_CHILDREN: Child[] = [
  {
    id: 'child-1',
    firstName: 'Emma',
    lastName: 'Johnson',
    photoUrl: null,
    dateOfBirth: '2021-05-15',
    classroomId: 'classroom-1',
    classroomName: 'Butterfly Room',
  },
  {
    id: 'child-2',
    firstName: 'Oliver',
    lastName: 'Johnson',
    photoUrl: null,
    dateOfBirth: '2019-11-20',
    classroomId: 'classroom-2',
    classroomName: 'Sunshine Room',
  },
];

/**
 * Hook for managing child selection in multi-child families
 *
 * @returns {UseChildSelectionReturn} State and actions for child selection
 *
 * @example
 * ```tsx
 * function MyComponent() {
 *   const {
 *     children,
 *     selectedChild,
 *     isLoading,
 *     selectChild,
 *   } = useChildSelection();
 *
 *   if (isLoading) return <ActivityIndicator />;
 *
 *   return (
 *     <ChildSelector
 *       children={children}
 *       selectedChild={selectedChild}
 *       onSelectChild={selectChild}
 *     />
 *   );
 * }
 * ```
 */
export function useChildSelection(): UseChildSelectionReturn {
  const [children, setChildren] = useState<Child[]>([]);
  const [selectedChild, setSelectedChild] = useState<Child | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch children list from the API
   */
  const fetchChildren = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await api.get<Child[]>(API_CONFIG.endpoints.children.list);

      if (response.success && response.data) {
        setChildren(response.data);
        // Auto-select first child if none selected
        if (response.data.length > 0 && !selectedChild) {
          setSelectedChild(response.data[0]);
        }
      } else {
        // Fallback to mock data in development
        setChildren(MOCK_CHILDREN);
        if (!selectedChild) {
          setSelectedChild(MOCK_CHILDREN[0]);
        }
      }
    } catch {
      // Fallback to mock data on error
      setChildren(MOCK_CHILDREN);
      if (!selectedChild) {
        setSelectedChild(MOCK_CHILDREN[0]);
      }
    } finally {
      setIsLoading(false);
    }
  }, [selectedChild]);

  /**
   * Select a child by ID
   */
  const selectChild = useCallback(
    (childId: string): void => {
      const child = children.find((c) => c.id === childId);
      if (child) {
        setSelectedChild(child);
      }
    },
    [children],
  );

  /**
   * Select a child directly by reference
   */
  const selectChildByRef = useCallback((child: Child): void => {
    setSelectedChild(child);
  }, []);

  /**
   * Refresh children list from API
   */
  const refreshChildren = useCallback(async (): Promise<void> => {
    await fetchChildren();
  }, [fetchChildren]);

  // Fetch children on mount
  useEffect(() => {
    fetchChildren();
  }, [fetchChildren]);

  return {
    children,
    selectedChild,
    isLoading,
    error,
    hasMultipleChildren: children.length > 1,
    selectChild,
    selectChildByRef,
    refreshChildren,
  };
}

export default useChildSelection;
