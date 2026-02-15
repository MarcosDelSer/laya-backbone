/**
 * LAYA Parent App - DocumentCard Component
 *
 * A card component for displaying individual documents with
 * title, status, expiration info, and sign/view actions.
 */

import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
} from 'react-native';
import type {SignatureRequest, SignatureStatus} from '../types';
import {
  formatDocumentDate,
  formatDocumentDateTime,
  getDaysUntilExpiration,
} from '../api/documentsApi';

interface DocumentCardProps {
  /** The document data to display */
  document: SignatureRequest;
  /** Callback when sign button is pressed */
  onSign?: (document: SignatureRequest) => void;
  /** Callback when view button is pressed */
  onView?: (document: SignatureRequest) => void;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  success: '#16A34A',
  warning: '#D97706',
  error: '#DC2626',
  purple: '#9C27B0',
};

/**
 * Status badge colors
 */
const STATUS_COLORS: Record<SignatureStatus, {bg: string; text: string}> = {
  pending: {bg: '#FEF3C7', text: '#D97706'},
  signed: {bg: '#DCFCE7', text: '#16A34A'},
  expired: {bg: '#FEE2E2', text: '#DC2626'},
};

/**
 * DocumentCard displays a single document with its status and actions.
 */
function DocumentCard({
  document,
  onSign,
  onView,
}: DocumentCardProps): React.JSX.Element {
  const daysUntilExpiration = getDaysUntilExpiration(document.expiresAt);
  const isExpiringSoon = daysUntilExpiration !== null && daysUntilExpiration <= 7 && daysUntilExpiration > 0;
  const isPending = document.status === 'pending';
  const isSigned = document.status === 'signed';
  const isExpired = document.status === 'expired';

  const handleSign = () => {
    if (onSign && isPending) {
      onSign(document);
    }
  };

  const handleView = () => {
    if (onView) {
      onView(document);
    }
  };

  /**
   * Render the status badge
   */
  const renderStatusBadge = (): React.JSX.Element => {
    const colors = STATUS_COLORS[document.status];
    let label: string;

    if (isPending) {
      label = 'Action Required';
    } else if (isSigned) {
      label = 'Signed';
    } else {
      label = 'Expired';
    }

    return (
      <View style={[styles.statusBadge, {backgroundColor: colors.bg}]}>
        <Text style={[styles.statusText, {color: colors.text}]}>{label}</Text>
      </View>
    );
  };

  /**
   * Render the expiration status
   */
  const renderExpirationStatus = (): React.JSX.Element | null => {
    if (document.status === 'signed') {
      return (
        <Text style={styles.signedDate}>
          Signed on {formatDocumentDateTime(document.signedAt!)}
        </Text>
      );
    }

    if (document.status === 'expired') {
      return (
        <Text style={[styles.expirationText, {color: COLORS.error}]}>
          Expired on {formatDocumentDate(document.expiresAt!)}
        </Text>
      );
    }

    if (daysUntilExpiration !== null) {
      let statusText: string;
      let statusColor = COLORS.textLight;

      if (daysUntilExpiration <= 0) {
        statusText = 'Expires today';
        statusColor = COLORS.error;
      } else if (daysUntilExpiration === 1) {
        statusText = 'Expires tomorrow';
        statusColor = COLORS.warning;
      } else if (isExpiringSoon) {
        statusText = `Expires in ${daysUntilExpiration} days`;
        statusColor = COLORS.warning;
      } else {
        statusText = `Expires on ${formatDocumentDate(document.expiresAt!)}`;
      }

      return (
        <Text style={[styles.expirationText, {color: statusColor}]}>
          {statusText}
        </Text>
      );
    }

    return null;
  };

  return (
    <View style={styles.card}>
      {/* Document Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={[
            styles.iconContainer,
            isPending && styles.iconContainerPending,
            isSigned && styles.iconContainerSigned,
            isExpired && styles.iconContainerExpired,
          ]}>
            <Text style={styles.iconText}>
              {isSigned ? '\u2713' : isPending ? '\u270D' : '\u2717'}
            </Text>
          </View>
          <View style={styles.headerInfo}>
            <Text style={styles.documentTitle} numberOfLines={2}>
              {document.documentTitle}
            </Text>
            <Text style={styles.requestDate}>
              Requested: {formatDocumentDate(document.requestedAt)}
            </Text>
          </View>
        </View>
        {renderStatusBadge()}
      </View>

      {/* Description */}
      {document.description && (
        <Text style={styles.description} numberOfLines={2}>
          {document.description}
        </Text>
      )}

      {/* Expiration/Signed Info */}
      <View style={styles.statusRow}>
        {renderExpirationStatus()}
      </View>

      {/* Actions */}
      <View style={styles.actions}>
        {isPending && (
          <TouchableOpacity
            style={styles.signButton}
            onPress={handleSign}
            accessibilityRole="button"
            accessibilityLabel={`Sign ${document.documentTitle}`}>
            <Text style={styles.signButtonIcon}>{'\u270D'}</Text>
            <Text style={styles.signButtonText}>Sign Now</Text>
          </TouchableOpacity>
        )}

        <TouchableOpacity
          style={[styles.viewButton, !isPending && styles.viewButtonFull]}
          onPress={handleView}
          accessibilityRole="button"
          accessibilityLabel={`View ${document.documentTitle}`}>
          <Text style={styles.viewButtonIcon}>{'\u{1F4C4}'}</Text>
          <Text style={styles.viewButtonText}>View PDF</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.background,
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 8,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
    // Android elevation
    elevation: 3,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    flex: 1,
    marginRight: 8,
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#F3E8FF',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  iconContainerPending: {
    backgroundColor: '#FEF3C7',
  },
  iconContainerSigned: {
    backgroundColor: '#DCFCE7',
  },
  iconContainerExpired: {
    backgroundColor: '#FEE2E2',
  },
  iconText: {
    fontSize: 20,
    fontWeight: '600',
  },
  headerInfo: {
    flex: 1,
  },
  documentTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 4,
  },
  requestDate: {
    fontSize: 13,
    color: COLORS.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  description: {
    fontSize: 14,
    color: COLORS.textSecondary,
    lineHeight: 20,
    marginBottom: 12,
  },
  statusRow: {
    marginBottom: 16,
    minHeight: 18,
  },
  signedDate: {
    fontSize: 13,
    color: COLORS.success,
  },
  expirationText: {
    fontSize: 13,
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  signButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    backgroundColor: COLORS.primary,
  },
  signButtonIcon: {
    fontSize: 14,
    marginRight: 6,
    color: '#FFFFFF',
  },
  signButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  viewButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.background,
  },
  viewButtonFull: {
    flex: 1,
  },
  viewButtonIcon: {
    fontSize: 14,
    marginRight: 6,
  },
  viewButtonText: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.textSecondary,
  },
});

export default DocumentCard;
