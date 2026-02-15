interface NapEntryProps {
  nap: {
    id: string;
    startTime: string;
    endTime: string;
    quality: 'good' | 'fair' | 'poor';
  };
}

const qualityConfig: Record<NapEntryProps['nap']['quality'], { label: string; badgeClass: string }> = {
  good: { label: 'Good sleep', badgeClass: 'badge-success' },
  fair: { label: 'Fair sleep', badgeClass: 'badge-warning' },
  poor: { label: 'Poor sleep', badgeClass: 'badge-neutral' },
};

function calculateDuration(startTime: string, endTime: string): string {
  // Parse times in HH:MM format (or with AM/PM)
  const parseTime = (time: string): number => {
    const normalized = time.toLowerCase();
    const isPM = normalized.includes('pm');
    const isAM = normalized.includes('am');
    const timePart = normalized.replace(/[ap]m/i, '').trim();

    const [hours, minutes] = timePart.split(':').map(Number);
    let hour24 = hours;

    if (isPM && hours !== 12) {
      hour24 = hours + 12;
    } else if (isAM && hours === 12) {
      hour24 = 0;
    }

    return hour24 * 60 + minutes;
  };

  const startMinutes = parseTime(startTime);
  const endMinutes = parseTime(endTime);
  let durationMinutes = endMinutes - startMinutes;

  // Handle overnight naps
  if (durationMinutes < 0) {
    durationMinutes += 24 * 60;
  }

  const hours = Math.floor(durationMinutes / 60);
  const minutes = durationMinutes % 60;

  if (hours === 0) {
    return `${minutes}m`;
  } else if (minutes === 0) {
    return `${hours}h`;
  }
  return `${hours}h ${minutes}m`;
}

export function NapEntry({ nap }: NapEntryProps) {
  const qualityInfo = qualityConfig[nap.quality];
  const duration = calculateDuration(nap.startTime, nap.endTime);

  return (
    <div className="flex items-start space-x-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
        <svg
          className="h-5 w-5 text-blue-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
          />
        </svg>
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="font-medium text-gray-900">{duration}</p>
          <span className="text-sm text-gray-500">
            {nap.startTime} - {nap.endTime}
          </span>
        </div>
        <span className={`badge mt-1 ${qualityInfo.badgeClass}`}>
          {qualityInfo.label}
        </span>
      </div>
    </div>
  );
}
