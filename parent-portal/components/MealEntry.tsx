interface MealEntryProps {
  meal: {
    id: string;
    type: 'breakfast' | 'lunch' | 'snack';
    time: string;
    notes: string;
    amount: 'all' | 'most' | 'some' | 'none';
  };
}

const mealTypeLabels: Record<MealEntryProps['meal']['type'], string> = {
  breakfast: 'Breakfast',
  lunch: 'Lunch',
  snack: 'Snack',
};

const amountConfig: Record<MealEntryProps['meal']['amount'], { label: string; badgeClass: string }> = {
  all: { label: 'Ate all', badgeClass: 'badge-success' },
  most: { label: 'Ate most', badgeClass: 'badge-info' },
  some: { label: 'Ate some', badgeClass: 'badge-warning' },
  none: { label: 'Did not eat', badgeClass: 'badge-neutral' },
};

export function MealEntry({ meal }: MealEntryProps) {
  const amountInfo = amountConfig[meal.amount];

  return (
    <div className="flex items-start space-x-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
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
          <span className="text-sm text-gray-500">{meal.time}</span>
        </div>
        {meal.notes && (
          <p className="text-sm text-gray-600 mt-0.5">{meal.notes}</p>
        )}
        <span className={`badge mt-1 ${amountInfo.badgeClass}`}>
          {amountInfo.label}
        </span>
      </div>
    </div>
  );
}
