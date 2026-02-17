import Link from 'next/link';

// Mock data for dashboard - will be replaced with API calls
const childData = {
  name: 'Emma Johnson',
  classroom: 'Butterfly Room',
  status: 'checked-in',
  checkedInAt: '8:30 AM',
  teacher: 'Ms. Sarah',
};

const todaysSummary = {
  date: new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }),
  meals: [
    { type: 'Breakfast', time: '8:45 AM', notes: 'Ate all of their oatmeal and fruit', amount: 'all' },
    { type: 'Snack', time: '10:30 AM', notes: 'Apple slices and crackers', amount: 'most' },
  ],
  naps: [
    { startTime: '12:30 PM', endTime: '2:00 PM', quality: 'good', duration: '1h 30m' },
  ],
  activities: [
    { name: 'Art Time', time: '9:00 AM', description: 'Finger painting with watercolors' },
    { name: 'Story Circle', time: '11:00 AM', description: 'Read "The Very Hungry Caterpillar"' },
    { name: 'Outdoor Play', time: '3:00 PM', description: 'Playing on the playground' },
  ],
};

const recentPhotos = [
  { id: '1', url: '/placeholder-1.jpg', caption: 'Art project', date: 'Today' },
  { id: '2', url: '/placeholder-2.jpg', caption: 'Playing outside', date: 'Today' },
  { id: '3', url: '/placeholder-3.jpg', caption: 'Story time', date: 'Yesterday' },
];

const quickStats = [
  { label: 'Meals Today', value: '2 of 3', icon: 'meal', color: 'green' },
  { label: 'Nap Time', value: '1h 30m', icon: 'nap', color: 'blue' },
  { label: 'Activities', value: '3', icon: 'activity', color: 'purple' },
  { label: 'Photos', value: '3 new', icon: 'photo', color: 'pink' },
];

function StatusBadge({ status }: { status: string }) {
  const statusConfig = {
    'checked-in': { label: 'Checked In', className: 'badge-success' },
    'checked-out': { label: 'Checked Out', className: 'badge-neutral' },
    'absent': { label: 'Absent', className: 'badge-warning' },
  };

  const config = statusConfig[status as keyof typeof statusConfig] || statusConfig['checked-out'];

  return <span className={`badge ${config.className}`} role="status" aria-label={`Child status: ${config.label}`}>{config.label}</span>;
}

function StatIcon({ icon, color }: { icon: string; color: string }) {
  const colorClasses = {
    green: 'bg-green-100 text-green-600',
    blue: 'bg-blue-100 text-blue-600',
    purple: 'bg-purple-100 text-purple-600',
    pink: 'bg-pink-100 text-pink-600',
  };

  const iconPaths = {
    meal: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
      />
    ),
    nap: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
      />
    ),
    activity: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
      />
    ),
    photo: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
      />
    ),
  };

  return (
    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colorClasses[color as keyof typeof colorClasses]}`} aria-hidden="true">
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {iconPaths[icon as keyof typeof iconPaths]}
      </svg>
    </div>
  );
}

export default function DashboardPage() {
  return (
    <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Good morning!
            </h1>
            <p className="mt-1 text-gray-600">{todaysSummary.date}</p>
          </div>
          <div className="mt-4 sm:mt-0">
            <Link href="/daily-reports" className="btn btn-primary" aria-label="View full daily report">
              View Full Report
            </Link>
          </div>
        </div>
      </div>

      {/* Child Status Card */}
      <section className="card mb-8" aria-labelledby="child-status-heading">
        <div className="p-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-2xl font-semibold text-primary-700" aria-hidden="true">
                {childData.name.charAt(0)}
              </div>
              <div>
                <h2 id="child-status-heading" className="text-xl font-semibold text-gray-900">
                  {childData.name}
                </h2>
                <p className="text-gray-600">
                  {childData.classroom} • {childData.teacher}
                </p>
              </div>
            </div>
            <div className="text-right">
              <StatusBadge status={childData.status} />
              <p className="mt-1 text-sm text-gray-500">
                Since {childData.checkedInAt}
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Quick Stats */}
      <section className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-8" aria-label="Quick statistics">
        {quickStats.map((stat) => (
          <article key={stat.label} className="card p-4" aria-label={`${stat.label}: ${stat.value}`}>
            <div className="flex items-center space-x-3">
              <StatIcon icon={stat.icon} color={stat.color} />
              <div>
                <p className="text-sm text-gray-500">{stat.label}</p>
                <p className="text-lg font-semibold text-gray-900">{stat.value}</p>
              </div>
            </div>
          </article>
        ))}
      </section>

      {/* Main Content */}
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {/* Left Column - Daily Summary */}
        <div className="lg:col-span-2 space-y-6">
          {/* Today's Activities */}
          <article className="card">
            <header className="card-header flex items-center justify-between">
              <h3 className="section-title">Today&apos;s Activities</h3>
              <Link href="/daily-reports" className="text-sm text-primary-600 hover:text-primary-700">
                View all
              </Link>
            </header>
            <section className="card-body">
              <div className="space-y-4">
                {todaysSummary.activities.map((activity, index) => (
                  <div key={index} className="flex items-start space-x-4">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                      <svg className="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between">
                        <p className="font-medium text-gray-900">{activity.name}</p>
                        <span className="text-sm text-gray-500">{activity.time}</span>
                      </div>
                      <p className="text-sm text-gray-600">{activity.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          </article>

          {/* Meals & Naps */}
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {/* Meals */}
            <article className="card">
              <header className="card-header">
                <h3 className="section-title">Meals</h3>
              </header>
              <section className="card-body">
                <div className="space-y-3">
                  {todaysSummary.meals.map((meal, index) => (
                    <div key={index} className="flex items-start space-x-3">
                      <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
                        <svg className="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="font-medium text-gray-900">{meal.type}</p>
                          <span className="text-xs text-gray-500">{meal.time}</span>
                        </div>
                        <p className="text-sm text-gray-600">{meal.notes}</p>
                        <span className={`badge mt-1 ${meal.amount === 'all' ? 'badge-success' : 'badge-info'}`}>
                          Ate {meal.amount}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            </article>

            {/* Naps */}
            <article className="card">
              <header className="card-header">
                <h3 className="section-title">Nap Time</h3>
              </header>
              <section className="card-body">
                <div className="space-y-3">
                  {todaysSummary.naps.map((nap, index) => (
                    <div key={index} className="flex items-start space-x-3">
                      <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                        <svg className="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-gray-900">{nap.duration}</p>
                        <p className="text-sm text-gray-600">
                          {nap.startTime} - {nap.endTime}
                        </p>
                        <span className={`badge mt-1 ${nap.quality === 'good' ? 'badge-success' : 'badge-warning'}`}>
                          {nap.quality.charAt(0).toUpperCase() + nap.quality.slice(1)} sleep
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            </article>
          </div>
        </div>

        {/* Right Column - Photos & Quick Links */}
        <div className="space-y-6">
          {/* Recent Photos */}
          <article className="card">
            <header className="card-header flex items-center justify-between">
              <h3 className="section-title">Recent Photos</h3>
              <Link href="/daily-reports" className="text-sm text-primary-600 hover:text-primary-700">
                View all
              </Link>
            </header>
            <section className="card-body">
              <div className="grid grid-cols-2 gap-2">
                {recentPhotos.map((photo) => (
                  <div
                    key={photo.id}
                    className="group relative aspect-square overflow-hidden rounded-lg bg-gray-200"
                  >
                    {/* Placeholder for actual photo */}
                    <div className="flex h-full w-full items-center justify-center text-gray-400">
                      <svg className="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100">
                      <div className="absolute bottom-2 left-2 text-white">
                        <p className="text-xs font-medium">{photo.caption}</p>
                        <p className="text-xs opacity-75">{photo.date}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          </article>

          {/* Quick Links */}
          <article className="card">
            <header className="card-header">
              <h3 className="section-title">Quick Links</h3>
            </header>
            <nav className="card-body">
              <div className="space-y-2">
                <Link
                  href="/invoices"
                  className="flex items-center justify-between rounded-lg p-3 hover:bg-gray-50"
                >
                  <div className="flex items-center space-x-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-100">
                      <svg className="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">View Invoices</p>
                      <p className="text-sm text-gray-500">1 pending payment</p>
                    </div>
                  </div>
                  <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </Link>

                <Link
                  href="/messages"
                  className="flex items-center justify-between rounded-lg p-3 hover:bg-gray-50"
                >
                  <div className="flex items-center space-x-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                      <svg className="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Messages</p>
                      <p className="text-sm text-gray-500">2 unread messages</p>
                    </div>
                  </div>
                  <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </Link>

                <Link
                  href="/documents"
                  className="flex items-center justify-between rounded-lg p-3 hover:bg-gray-50"
                >
                  <div className="flex items-center space-x-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100">
                      <svg className="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Documents</p>
                      <p className="text-sm text-gray-500">1 awaiting signature</p>
                    </div>
                  </div>
                  <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </Link>
              </div>
            </nav>
          </article>
        </div>
      </div>
    </div>
  );
}
