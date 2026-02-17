'use client';

import { useState } from 'react';
import type { MenuItem, MenuCategory } from '../lib/types';
import { AllergenBadge } from './AllergenBadge';

interface MenuItemCardProps {
  /** Menu item to display */
  menuItem: MenuItem;
  /** Whether to show in compact mode (less detail) */
  compact?: boolean;
  /** Callback when card is clicked */
  onClick?: (menuItem: MenuItem) => void;
}

const categoryConfig: Record<MenuCategory, { label: string; colorClass: string }> = {
  main: { label: 'Main', colorClass: 'bg-blue-100 text-blue-700' },
  side: { label: 'Side', colorClass: 'bg-green-100 text-green-700' },
  beverage: { label: 'Beverage', colorClass: 'bg-purple-100 text-purple-700' },
  snack: { label: 'Snack', colorClass: 'bg-yellow-100 text-yellow-700' },
  dessert: { label: 'Dessert', colorClass: 'bg-pink-100 text-pink-700' },
  fruit: { label: 'Fruit', colorClass: 'bg-orange-100 text-orange-700' },
  vegetable: { label: 'Vegetable', colorClass: 'bg-emerald-100 text-emerald-700' },
  dairy: { label: 'Dairy', colorClass: 'bg-sky-100 text-sky-700' },
  grain: { label: 'Grain', colorClass: 'bg-amber-100 text-amber-700' },
};

function getMealIcon(): React.ReactNode {
  return (
    <svg
      className="h-6 w-6 text-green-600"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
      />
    </svg>
  );
}

function NutritionRow({ label, value, unit }: { label: string; value: number; unit: string }) {
  return (
    <div className="flex justify-between text-sm">
      <span className="text-gray-500">{label}</span>
      <span className="font-medium text-gray-900">
        {value}
        {unit}
      </span>
    </div>
  );
}

/**
 * MenuItemCard displays an individual menu item with allergens and nutrition info.
 * Shows name, description, photo (optional), allergen badges, and expandable nutrition details.
 */
export function MenuItemCard({ menuItem, compact = false, onClick }: MenuItemCardProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const categoryInfo = categoryConfig[menuItem.category];
  const hasNutrition = menuItem.nutritionalInfo && (
    menuItem.nutritionalInfo.calories > 0 ||
    menuItem.nutritionalInfo.protein > 0
  );

  const handleClick = () => {
    if (onClick) {
      onClick(menuItem);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      handleClick();
    }
  };

  const toggleExpanded = (e: React.MouseEvent) => {
    e.stopPropagation();
    setIsExpanded(!isExpanded);
  };

  if (compact) {
    return (
      <div
        className="flex items-center space-x-2 rounded-lg bg-gray-50 px-3 py-2"
        role={onClick ? 'button' : undefined}
        tabIndex={onClick ? 0 : undefined}
        onClick={onClick ? handleClick : undefined}
        onKeyDown={onClick ? handleKeyDown : undefined}
      >
        {/* Photo or icon */}
        {menuItem.photoUrl ? (
          <img
            src={menuItem.photoUrl}
            alt={menuItem.name}
            className="h-8 w-8 rounded-full object-cover"
          />
        ) : (
          <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
            <svg className="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </div>
        )}
        <span className="flex-1 text-sm font-medium text-gray-900 truncate">
          {menuItem.name}
        </span>
        {/* Allergen indicators - compact shows dots for severe/moderate */}
        {menuItem.allergens.some((a) => a.severity === 'severe' || a.severity === 'moderate') && (
          <span className="flex h-2 w-2 rounded-full bg-red-500" title="Contains allergens" />
        )}
      </div>
    );
  }

  return (
    <div
      className="card"
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      onClick={onClick ? handleClick : undefined}
      onKeyDown={onClick ? handleKeyDown : undefined}
    >
      <div className="card-body">
        <div className="flex items-start space-x-4">
          {/* Photo or icon */}
          <div className="flex-shrink-0">
            {menuItem.photoUrl ? (
              <img
                src={menuItem.photoUrl}
                alt={menuItem.name}
                className="h-16 w-16 rounded-lg object-cover"
              />
            ) : (
              <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-green-100">
                {getMealIcon()}
              </div>
            )}
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0">
            {/* Header */}
            <div className="flex items-start justify-between">
              <div className="flex-1 min-w-0">
                <h3 className="text-base font-semibold text-gray-900 truncate">
                  {menuItem.name}
                </h3>
                {menuItem.description && (
                  <p className="mt-1 text-sm text-gray-600 line-clamp-2">
                    {menuItem.description}
                  </p>
                )}
              </div>
              {/* Category badge */}
              <span className={`ml-2 flex-shrink-0 rounded-full px-2 py-1 text-xs font-medium ${categoryInfo.colorClass}`}>
                {categoryInfo.label}
              </span>
            </div>

            {/* Allergens */}
            {menuItem.allergens.length > 0 && (
              <div className="mt-3 flex flex-wrap gap-1.5">
                {menuItem.allergens.map((allergen) => (
                  <AllergenBadge
                    key={allergen.id}
                    allergen={allergen.name}
                    severity={allergen.severity}
                    size="sm"
                  />
                ))}
              </div>
            )}

            {/* Nutritional summary - compact view */}
            {hasNutrition && !isExpanded && (
              <div className="mt-3 flex items-center space-x-4 text-sm text-gray-500">
                <span>{menuItem.nutritionalInfo!.calories} cal</span>
                <span>{menuItem.nutritionalInfo!.protein}g protein</span>
                <span>{menuItem.nutritionalInfo!.carbohydrates}g carbs</span>
                {hasNutrition && (
                  <button
                    type="button"
                    onClick={toggleExpanded}
                    className="text-primary-600 hover:text-primary-700 font-medium"
                  >
                    More
                  </button>
                )}
              </div>
            )}

            {/* Expanded nutrition details */}
            {hasNutrition && isExpanded && (
              <div className="mt-3 rounded-lg bg-gray-50 p-3">
                <div className="flex items-center justify-between mb-2">
                  <h4 className="text-sm font-semibold text-gray-900">Nutritional Information</h4>
                  <button
                    type="button"
                    onClick={toggleExpanded}
                    className="text-gray-400 hover:text-gray-600"
                    aria-label="Close nutrition details"
                  >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                <div className="space-y-1.5">
                  <NutritionRow label="Calories" value={menuItem.nutritionalInfo!.calories} unit=" kcal" />
                  <NutritionRow label="Protein" value={menuItem.nutritionalInfo!.protein} unit="g" />
                  <NutritionRow label="Carbohydrates" value={menuItem.nutritionalInfo!.carbohydrates} unit="g" />
                  <NutritionRow label="Fat" value={menuItem.nutritionalInfo!.fat} unit="g" />
                  {menuItem.nutritionalInfo!.fiber !== undefined && (
                    <NutritionRow label="Fiber" value={menuItem.nutritionalInfo!.fiber} unit="g" />
                  )}
                  {menuItem.nutritionalInfo!.servingSize && (
                    <div className="flex justify-between text-sm pt-2 border-t border-gray-200 mt-2">
                      <span className="text-gray-500">Serving Size</span>
                      <span className="font-medium text-gray-900">{menuItem.nutritionalInfo!.servingSize}</span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
