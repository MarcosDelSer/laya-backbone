'use client';

import { useRef, useEffect, useState, useCallback } from 'react';
import { useTranslations } from 'next-intl';

interface SignatureCanvasProps {
  onSignatureChange: (hasSignature: boolean, dataUrl: string | null) => void;
  width?: number;
  height?: number;
  penColor?: string;
  penWidth?: number;
}

export function SignatureCanvas({
  onSignatureChange,
  width = 400,
  height = 200,
  penColor = '#1f2937',
  penWidth = 2,
}: SignatureCanvasProps) {
  const t = useTranslations();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [hasSignature, setHasSignature] = useState(false);
  const [context, setContext] = useState<CanvasRenderingContext2D | null>(null);

  // Initialize canvas context
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Set canvas resolution for high DPI displays
    const dpr = window.devicePixelRatio || 1;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.scale(dpr, dpr);

    // Configure drawing style
    ctx.strokeStyle = penColor;
    ctx.lineWidth = penWidth;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    setContext(ctx);
  }, [width, height, penColor, penWidth]);

  // Get position from mouse or touch event
  const getPosition = useCallback(
    (e: MouseEvent | TouchEvent): { x: number; y: number } | null => {
      const canvas = canvasRef.current;
      if (!canvas) return null;

      const rect = canvas.getBoundingClientRect();

      if ('touches' in e) {
        if (e.touches.length === 0) return null;
        const touch = e.touches[0];
        return {
          x: touch.clientX - rect.left,
          y: touch.clientY - rect.top,
        };
      } else {
        return {
          x: e.clientX - rect.left,
          y: e.clientY - rect.top,
        };
      }
    },
    []
  );

  // Start drawing
  const startDrawing = useCallback(
    (e: React.MouseEvent | React.TouchEvent) => {
      if (!context) return;
      e.preventDefault();

      const pos = getPosition(e.nativeEvent);
      if (!pos) return;

      setIsDrawing(true);
      context.beginPath();
      context.moveTo(pos.x, pos.y);
    },
    [context, getPosition]
  );

  // Continue drawing
  const draw = useCallback(
    (e: React.MouseEvent | React.TouchEvent) => {
      if (!isDrawing || !context) return;
      e.preventDefault();

      const pos = getPosition(e.nativeEvent);
      if (!pos) return;

      context.lineTo(pos.x, pos.y);
      context.stroke();

      if (!hasSignature) {
        setHasSignature(true);
      }
    },
    [isDrawing, context, getPosition, hasSignature]
  );

  // Stop drawing
  const stopDrawing = useCallback(() => {
    if (!context || !isDrawing) return;

    setIsDrawing(false);
    context.closePath();

    // Notify parent of signature change
    const canvas = canvasRef.current;
    if (canvas && hasSignature) {
      const dataUrl = canvas.toDataURL('image/png');
      onSignatureChange(true, dataUrl);
    }
  }, [context, isDrawing, hasSignature, onSignatureChange]);

  // Effect to update parent when hasSignature changes
  useEffect(() => {
    if (hasSignature) {
      const canvas = canvasRef.current;
      if (canvas) {
        const dataUrl = canvas.toDataURL('image/png');
        onSignatureChange(true, dataUrl);
      }
    }
  }, [hasSignature, onSignatureChange]);

  // Clear the canvas
  const clearCanvas = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas || !context) return;

    const dpr = window.devicePixelRatio || 1;
    context.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
    setHasSignature(false);
    onSignatureChange(false, null);
  }, [context, onSignatureChange]);

  // Get signature data URL
  const getSignatureDataUrl = useCallback((): string | null => {
    const canvas = canvasRef.current;
    if (!canvas || !hasSignature) return null;
    return canvas.toDataURL('image/png');
  }, [hasSignature]);

  return (
    <div className="signature-canvas-container">
      {/* Canvas */}
      <div className="relative border-2 border-dashed border-gray-300 rounded-lg bg-white overflow-hidden" role="application" aria-label="Signature drawing canvas">
        <canvas
          ref={canvasRef}
          className="touch-none cursor-crosshair"
          onMouseDown={startDrawing}
          onMouseMove={draw}
          onMouseUp={stopDrawing}
          onMouseLeave={stopDrawing}
          onTouchStart={startDrawing}
          onTouchMove={draw}
          onTouchEnd={stopDrawing}
          onTouchCancel={stopDrawing}
          aria-label="Draw your signature with mouse or touch"
        />

        {/* Signature line indicator */}
        <div className="absolute bottom-8 left-4 right-4 border-b border-gray-300" aria-hidden="true" />

        {/* Placeholder text */}
        {!hasSignature && (
          <div className="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden="true">
            <p className="text-gray-400 text-sm">Sign here</p>
          </div>
        )}

        {/* X mark for signature start point */}
        <div className="absolute bottom-9 left-4 text-gray-400 text-xs pointer-events-none" aria-hidden="true">
          ×
        </div>
      </div>

      {/* Controls */}
      <div className="mt-3 flex items-center justify-between">
        <p className="text-xs text-gray-500" role="status" aria-live="polite">
          {hasSignature ? 'Signature captured' : 'Draw your signature above'}
        </p>
        <button
          type="button"
          onClick={clearCanvas}
          className="text-sm text-gray-600 hover:text-gray-800 underline"
          disabled={!hasSignature}
          aria-label="Clear signature"
        >
          {t('documents.signature.clear')}
        </button>
      </div>
    </div>
  );
}

// Export utility function for external access
export function getSignatureCanvas(ref: React.RefObject<HTMLCanvasElement>): string | null {
  const canvas = ref.current;
  if (!canvas) return null;
  return canvas.toDataURL('image/png');
}
