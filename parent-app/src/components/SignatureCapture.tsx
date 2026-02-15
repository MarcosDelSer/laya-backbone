/**
 * LAYA Parent App - SignatureCapture Component
 *
 * A modal component for capturing electronic signatures with a canvas
 * and legal agreement checkbox. Based on web portal SignatureCanvas pattern.
 */

import React, {useState, useCallback, useRef, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  Modal,
  TouchableOpacity,
  ActivityIndicator,
  PanResponder,
  Dimensions,
} from 'react-native';
import type {SignatureRequest} from '../types';
import {formatDocumentDate} from '../api/documentsApi';

interface SignatureCaptureProps {
  /** The document being signed */
  document: SignatureRequest | null;
  /** Whether the modal is visible */
  isVisible: boolean;
  /** Called when modal should close */
  onClose: () => void;
  /** Called when signature is submitted */
  onSubmit: (documentId: string, signatureDataUrl: string) => void;
  /** Whether submission is in progress */
  isSubmitting?: boolean;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#FFFFFF',
  overlay: 'rgba(0, 0, 0, 0.5)',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  success: '#16A34A',
  error: '#DC2626',
  canvasBackground: '#FAFAFA',
  canvasBorder: '#D1D5DB',
};

const CANVAS_WIDTH = Dimensions.get('window').width - 80;
const CANVAS_HEIGHT = 150;

/**
 * Point interface for tracking touch positions
 */
interface Point {
  x: number;
  y: number;
}

/**
 * SignatureCapture provides a modal interface for capturing signatures.
 */
function SignatureCapture({
  document,
  isVisible,
  onClose,
  onSubmit,
  isSubmitting = false,
}: SignatureCaptureProps): React.JSX.Element {
  const [hasSignature, setHasSignature] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [paths, setPaths] = useState<Point[][]>([]);
  const [currentPath, setCurrentPath] = useState<Point[]>([]);
  const canvasRef = useRef<View>(null);
  const layoutRef = useRef<{x: number; y: number; width: number; height: number} | null>(null);

  // Reset state when modal closes or document changes
  useEffect(() => {
    if (!isVisible) {
      setHasSignature(false);
      setAgreedToTerms(false);
      setPaths([]);
      setCurrentPath([]);
    }
  }, [isVisible]);

  /**
   * Handle canvas layout to get position
   */
  const handleCanvasLayout = useCallback((event: {nativeEvent: {layout: {x: number; y: number; width: number; height: number}}}) => {
    layoutRef.current = event.nativeEvent.layout;
  }, []);

  /**
   * Convert touch position to canvas coordinates
   */
  const getTouchPosition = useCallback((pageX: number, pageY: number): Point | null => {
    if (!layoutRef.current) {
      return null;
    }

    // Since we measure from the canvas view, coordinates are relative
    const x = pageX - layoutRef.current.x;
    const y = pageY - layoutRef.current.y;

    // Clamp to canvas bounds
    return {
      x: Math.max(0, Math.min(CANVAS_WIDTH, x)),
      y: Math.max(0, Math.min(CANVAS_HEIGHT, y)),
    };
  }, []);

  /**
   * Pan responder for touch handling
   */
  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: (event) => {
        const {pageX, pageY} = event.nativeEvent;
        // Get canvas position relative to screen
        canvasRef.current?.measure((_x, _y, _width, _height, pageXOffset, pageYOffset) => {
          const x = pageX - pageXOffset;
          const y = pageY - pageYOffset;
          if (x >= 0 && x <= CANVAS_WIDTH && y >= 0 && y <= CANVAS_HEIGHT) {
            setCurrentPath([{x, y}]);
          }
        });
      },
      onPanResponderMove: (event) => {
        const {pageX, pageY} = event.nativeEvent;
        canvasRef.current?.measure((_x, _y, _width, _height, pageXOffset, pageYOffset) => {
          const x = pageX - pageXOffset;
          const y = pageY - pageYOffset;
          if (x >= 0 && x <= CANVAS_WIDTH && y >= 0 && y <= CANVAS_HEIGHT) {
            setCurrentPath(prev => [...prev, {x, y}]);
            if (!hasSignature) {
              setHasSignature(true);
            }
          }
        });
      },
      onPanResponderRelease: () => {
        if (currentPath.length > 0) {
          setPaths(prev => [...prev, currentPath]);
          setCurrentPath([]);
        }
      },
    })
  ).current;

  /**
   * Clear the signature canvas
   */
  const handleClear = useCallback(() => {
    setPaths([]);
    setCurrentPath([]);
    setHasSignature(false);
  }, []);

  /**
   * Generate SVG path data from points
   */
  const generatePathData = useCallback((points: Point[]): string => {
    if (points.length === 0) {
      return '';
    }

    let pathData = `M ${points[0].x} ${points[0].y}`;
    for (let i = 1; i < points.length; i++) {
      pathData += ` L ${points[i].x} ${points[i].y}`;
    }
    return pathData;
  }, []);

  /**
   * Generate signature data URL (simplified - returns SVG as data URL)
   */
  const generateSignatureDataUrl = useCallback((): string => {
    const allPaths = [...paths];
    if (currentPath.length > 0) {
      allPaths.push(currentPath);
    }

    const pathElements = allPaths
      .map(path => `<path d="${generatePathData(path)}" stroke="#1f2937" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`)
      .join('');

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${CANVAS_WIDTH}" height="${CANVAS_HEIGHT}" viewBox="0 0 ${CANVAS_WIDTH} ${CANVAS_HEIGHT}">${pathElements}</svg>`;

    return `data:image/svg+xml;base64,${btoa(svg)}`;
  }, [paths, currentPath, generatePathData]);

  /**
   * Handle form submission
   */
  const handleSubmit = useCallback(async () => {
    if (!document || !hasSignature || !agreedToTerms) {
      return;
    }

    const signatureDataUrl = generateSignatureDataUrl();
    onSubmit(document.id, signatureDataUrl);
  }, [document, hasSignature, agreedToTerms, generateSignatureDataUrl, onSubmit]);

  const canSubmit = hasSignature && agreedToTerms && !isSubmitting;

  if (!document) {
    return <></>;
  }

  /**
   * Render the SVG paths for the signature
   */
  const renderPaths = (): React.JSX.Element[] => {
    const allPaths = [...paths];
    if (currentPath.length > 0) {
      allPaths.push(currentPath);
    }

    return allPaths.map((path, index) => {
      if (path.length < 2) {
        return <View key={index} />;
      }

      // Draw path segments as absolute positioned views with small circles
      return (
        <View key={index} style={StyleSheet.absoluteFill}>
          {path.map((point, pointIndex) => (
            <View
              key={pointIndex}
              style={[
                styles.signaturePoint,
                {
                  left: point.x - 1,
                  top: point.y - 1,
                },
              ]}
            />
          ))}
        </View>
      );
    });
  };

  return (
    <Modal
      visible={isVisible}
      animationType="slide"
      transparent
      onRequestClose={isSubmitting ? undefined : onClose}>
      <View style={styles.overlay}>
        <View style={styles.modal}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.headerContent}>
              <Text style={styles.headerTitle}>Sign Document</Text>
              <Text style={styles.headerSubtitle} numberOfLines={1}>
                {document.documentTitle}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={onClose}
              disabled={isSubmitting}
              accessibilityRole="button"
              accessibilityLabel="Close">
              <Text style={styles.closeButtonText}>{'\u2715'}</Text>
            </TouchableOpacity>
          </View>

          {/* Document Info */}
          <View style={styles.documentInfo}>
            <View style={styles.documentIconContainer}>
              <Text style={styles.documentIcon}>{'\u{1F4C4}'}</Text>
            </View>
            <View style={styles.documentDetails}>
              <Text style={styles.documentTitle} numberOfLines={1}>
                {document.documentTitle}
              </Text>
              <Text style={styles.documentMeta}>
                Requested: {formatDocumentDate(document.requestedAt)}
              </Text>
            </View>
            <TouchableOpacity
              style={styles.viewPdfLink}
              accessibilityRole="link"
              accessibilityLabel="View PDF">
              <Text style={styles.viewPdfText}>View PDF</Text>
            </TouchableOpacity>
          </View>

          {/* Signature Canvas */}
          <View style={styles.canvasSection}>
            <Text style={styles.canvasLabel}>Your Signature</Text>
            <View
              ref={canvasRef}
              style={styles.canvas}
              onLayout={handleCanvasLayout}
              {...panResponder.panHandlers}>
              {/* Signature line */}
              <View style={styles.signatureLine} />

              {/* X mark */}
              <Text style={styles.signatureX}>{'\u2715'}</Text>

              {/* Placeholder text */}
              {!hasSignature && (
                <View style={styles.placeholderContainer}>
                  <Text style={styles.placeholderText}>Sign here</Text>
                </View>
              )}

              {/* Rendered paths */}
              {renderPaths()}
            </View>

            {/* Canvas controls */}
            <View style={styles.canvasControls}>
              <Text style={styles.canvasHint}>
                {hasSignature ? 'Signature captured' : 'Draw your signature above'}
              </Text>
              <TouchableOpacity
                onPress={handleClear}
                disabled={!hasSignature}
                accessibilityRole="button"
                accessibilityLabel="Clear signature">
                <Text style={[styles.clearButton, !hasSignature && styles.clearButtonDisabled]}>
                  Clear
                </Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* Agreement Checkbox */}
          <TouchableOpacity
            style={styles.agreementRow}
            onPress={() => setAgreedToTerms(!agreedToTerms)}
            disabled={isSubmitting}
            accessibilityRole="checkbox"
            accessibilityState={{checked: agreedToTerms}}>
            <View style={[styles.checkbox, agreedToTerms && styles.checkboxChecked]}>
              {agreedToTerms && <Text style={styles.checkmark}>{'\u2713'}</Text>}
            </View>
            <Text style={styles.agreementText}>
              I acknowledge that this electronic signature is legally binding and represents my consent to the terms in this document.
            </Text>
          </TouchableOpacity>

          {/* Actions */}
          <View style={styles.actions}>
            <TouchableOpacity
              style={styles.cancelButton}
              onPress={onClose}
              disabled={isSubmitting}
              accessibilityRole="button"
              accessibilityLabel="Cancel">
              <Text style={styles.cancelButtonText}>Cancel</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.submitButton, !canSubmit && styles.submitButtonDisabled]}
              onPress={handleSubmit}
              disabled={!canSubmit}
              accessibilityRole="button"
              accessibilityLabel="Submit signature">
              {isSubmitting ? (
                <ActivityIndicator size="small" color="#FFFFFF" />
              ) : (
                <Text style={styles.submitButtonText}>Sign Document</Text>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: COLORS.overlay,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modal: {
    width: '100%',
    maxWidth: 500,
    backgroundColor: COLORS.background,
    borderRadius: 16,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.2,
    shadowRadius: 8,
    // Android elevation
    elevation: 8,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerContent: {
    flex: 1,
    marginRight: 16,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 4,
  },
  headerSubtitle: {
    fontSize: 14,
    color: COLORS.textSecondary,
  },
  closeButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  closeButtonText: {
    fontSize: 16,
    color: COLORS.textSecondary,
  },
  documentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    marginHorizontal: 20,
    marginTop: 16,
    backgroundColor: '#F9FAFB',
    borderRadius: 12,
  },
  documentIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#FEE2E2',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  documentIcon: {
    fontSize: 18,
  },
  documentDetails: {
    flex: 1,
  },
  documentTitle: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.text,
    marginBottom: 2,
  },
  documentMeta: {
    fontSize: 12,
    color: COLORS.textLight,
  },
  viewPdfLink: {
    paddingHorizontal: 8,
  },
  viewPdfText: {
    fontSize: 13,
    fontWeight: '500',
    color: COLORS.primary,
  },
  canvasSection: {
    paddingHorizontal: 20,
    marginTop: 20,
  },
  canvasLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.text,
    marginBottom: 12,
  },
  canvas: {
    width: CANVAS_WIDTH,
    height: CANVAS_HEIGHT,
    backgroundColor: COLORS.canvasBackground,
    borderWidth: 2,
    borderStyle: 'dashed',
    borderColor: COLORS.canvasBorder,
    borderRadius: 8,
    overflow: 'hidden',
    position: 'relative',
  },
  signatureLine: {
    position: 'absolute',
    bottom: 32,
    left: 16,
    right: 16,
    height: 1,
    backgroundColor: '#D1D5DB',
  },
  signatureX: {
    position: 'absolute',
    bottom: 36,
    left: 16,
    fontSize: 10,
    color: '#9CA3AF',
  },
  placeholderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
  },
  placeholderText: {
    fontSize: 14,
    color: '#9CA3AF',
  },
  signaturePoint: {
    position: 'absolute',
    width: 2,
    height: 2,
    borderRadius: 1,
    backgroundColor: '#1f2937',
  },
  canvasControls: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
  },
  canvasHint: {
    fontSize: 12,
    color: COLORS.textLight,
  },
  clearButton: {
    fontSize: 13,
    color: COLORS.textSecondary,
    textDecorationLine: 'underline',
  },
  clearButtonDisabled: {
    color: COLORS.textLight,
  },
  agreementRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 20,
    marginTop: 20,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: COLORS.border,
    marginRight: 12,
    marginTop: 2,
    justifyContent: 'center',
    alignItems: 'center',
  },
  checkboxChecked: {
    backgroundColor: COLORS.primary,
    borderColor: COLORS.primary,
  },
  checkmark: {
    fontSize: 12,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  agreementText: {
    flex: 1,
    fontSize: 13,
    color: COLORS.textSecondary,
    lineHeight: 18,
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 12,
    padding: 20,
  },
  cancelButton: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.background,
  },
  cancelButtonText: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.textSecondary,
  },
  submitButton: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: COLORS.primary,
    minWidth: 120,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    backgroundColor: '#93C5FD',
  },
  submitButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#FFFFFF',
  },
});

export default SignatureCapture;
