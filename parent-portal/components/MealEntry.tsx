'use client';

import { useTranslations } from 'next-intl';

interface MealEntryProps {
  meal: {
    id: string;
    type: 'breakfast' | 'lunch' | 'snack';
    time: string;
    notes: string;
    amount: 'all' | 'most' | 'some' | 'none';
  };
}

const amountBadgeClasses: Record<MealEntryProps['meal']['amount'], string> = {
  all: 'badge-success',
  most: 'badge-info',
  some: 'badge-warning',
  none: 'badge-neutral',
};

export function MealEntry({ meal }: MealEntryProps) {
  const t = useTranslations();

  const mealTypeLabel = t(`dailyReports.mealTypes.${meal.type}`);
  const amountLabel = t(`dashboard.mealAmount.${meal.amount}`);
  const badgeClass = amountBadgeClasses[meal.amount];

  return (
    <div className="flex items-start space-x-3" role="listitem" aria-label={`${mealTypeLabels[meal.type]} at ${meal.time}`}>
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100" aria-hidden="true">
        <svg
          className="h-5 w-5 text-green-600"
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
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="font-medium text-gray-900">{mealTypeLabels[meal.type]}</p>
          <time className="text-sm text-gray-500">{meal.time}</time>
        </div>
        {meal.notes && (
          <p className="text-sm text-gray-600 mt-0.5">{meal.notes}</p>
        )}
        <span className={`badge mt-1 ${amountInfo.badgeClass}`} role="status" aria-label={`Amount consumed: ${amountInfo.label}`}>
          {amountInfo.label}
        </span>
      </div>
    </div>
  );
}
