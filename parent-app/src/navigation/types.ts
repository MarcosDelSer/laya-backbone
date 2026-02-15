/**
 * LAYA Parent App - Navigation Type Definitions
 *
 * Type definitions for React Navigation used throughout the app.
 * Defines parameter lists for tab and stack navigators.
 */

import type {BottomTabScreenProps} from '@react-navigation/bottom-tabs';
import type {
  CompositeScreenProps,
  NavigatorScreenParams,
} from '@react-navigation/native';
import type {NativeStackScreenProps} from '@react-navigation/native-stack';

/**
 * Root Tab Navigator Param List
 * Defines the screens available in the bottom tab navigator
 */
export type RootTabParamList = {
  DailyFeed: undefined;
  PhotoGallery: {childId?: string} | undefined;
  Invoices: undefined;
  Messages: undefined;
  Signatures: undefined;
};

/**
 * Root Stack Navigator Param List
 * Defines all screens including those accessed via navigation (not tabs)
 */
export type RootStackParamList = {
  MainTabs: NavigatorScreenParams<RootTabParamList>;
  PhotoDetail: {photoId: string};
  InvoiceDetail: {invoiceId: string};
  Conversation: {conversationId: string};
  SignDocument: {signatureId: string};
  Login: undefined;
};

/**
 * Screen props for tab screens
 */
export type RootTabScreenProps<T extends keyof RootTabParamList> =
  CompositeScreenProps<
    BottomTabScreenProps<RootTabParamList, T>,
    NativeStackScreenProps<RootStackParamList>
  >;

/**
 * Screen props for stack screens
 */
export type RootStackScreenProps<T extends keyof RootStackParamList> =
  NativeStackScreenProps<RootStackParamList, T>;

/**
 * Declare global navigation types for useNavigation hook
 */
declare global {
  namespace ReactNavigation {
    interface RootParamList extends RootStackParamList {}
  }
}
