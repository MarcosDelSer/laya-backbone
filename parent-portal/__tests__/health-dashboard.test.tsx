/**
 * Health Dashboard Component Tests
 *
 * Comprehensive tests for the aggregated health dashboard page
 * and health status card component.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { HealthStatusCard } from '@/components/HealthStatusCard';
import HealthDashboardPage from '@/app/health/page';

// Mock next/link
vi.mock('next/link', () => ({
  default: ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

// Mock fetch
global.fetch = vi.fn();

describe('HealthStatusCard Component', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it('renders healthy status correctly', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="healthy"
        connected={true}
        url="http://localhost:8000"
      />
    );

    expect(screen.getByText('Test Service')).toBeInTheDocument();
    expect(screen.getByText('Healthy')).toBeInTheDocument();
    expect(screen.getByText('Connected')).toBeInTheDocument();
  });

  it('renders degraded status correctly', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="degraded"
        connected={true}
        error="Service running slowly"
      />
    );

    expect(screen.getByText('Degraded')).toBeInTheDocument();
    expect(screen.getByText('Service running slowly')).toBeInTheDocument();
  });

  it('renders unhealthy status correctly', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="unhealthy"
        connected={false}
        error="Connection refused"
      />
    );

    expect(screen.getByText('Unhealthy')).toBeInTheDocument();
    expect(screen.getByText('Disconnected')).toBeInTheDocument();
    expect(screen.getByText('Connection refused')).toBeInTheDocument();
  });

  it('renders loading status correctly', () => {
    render(<HealthStatusCard serviceName="Test Service" status="loading" />);

    expect(screen.getByText('Loading')).toBeInTheDocument();
  });

  it('renders error status correctly', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="error"
        error="Failed to fetch"
      />
    );

    expect(screen.getByText('Error')).toBeInTheDocument();
    expect(screen.getByText('Failed to fetch')).toBeInTheDocument();
  });

  it('displays service URL when provided', () => {
    const { container } = render(
      <HealthStatusCard
        serviceName="Test Service"
        status="healthy"
        url="http://localhost:8000"
      />
    );

    expect(screen.getByText('http://localhost:8000')).toBeInTheDocument();
  });

  it('displays response time when provided', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="healthy"
        responseTime={123456}
      />
    );

    expect(screen.getByText('Response Time:')).toBeInTheDocument();
  });

  it('displays service details when provided', () => {
    const details = {
      status: 'healthy',
      timestamp: '2024-02-15T10:30:00.000Z',
      version: '1.0.0',
      service: 'test-service',
    };

    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="healthy"
        details={details}
      />
    );

    expect(screen.getByText('Service Details')).toBeInTheDocument();
    expect(screen.getByText('1.0.0')).toBeInTheDocument();
    expect(screen.getByText('test-service')).toBeInTheDocument();
  });

  it('applies correct CSS classes for healthy status', () => {
    const { container } = render(
      <HealthStatusCard serviceName="Test Service" status="healthy" />
    );

    const card = container.querySelector('.card');
    expect(card).toHaveClass('border-green-200');
  });

  it('applies correct CSS classes for degraded status', () => {
    const { container } = render(
      <HealthStatusCard serviceName="Test Service" status="degraded" />
    );

    const card = container.querySelector('.card');
    expect(card).toHaveClass('border-yellow-200');
  });

  it('applies correct CSS classes for unhealthy status', () => {
    const { container } = render(
      <HealthStatusCard serviceName="Test Service" status="unhealthy" />
    );

    const card = container.querySelector('.card');
    expect(card).toHaveClass('border-red-200');
  });

  it('renders error details section when error is present', () => {
    render(
      <HealthStatusCard
        serviceName="Test Service"
        status="unhealthy"
        error="Connection timeout"
      />
    );

    expect(screen.getByText('Error Details')).toBeInTheDocument();
    expect(screen.getByText('Connection timeout')).toBeInTheDocument();
  });
});

describe('HealthDashboardPage Component', () => {
  const mockHealthData = {
    status: 'healthy' as const,
    timestamp: '2024-02-15T10:30:00.000Z',
    service: 'parent-portal',
    version: '0.1.0',
    checks: {
      aiService: {
        status: 'healthy' as const,
        connected: true,
        responseTime: 123,
        apiUrl: 'http://localhost:8000',
        details: {
          status: 'healthy',
          timestamp: '2024-02-15T10:30:00.000Z',
        },
      },
      gibbon: {
        status: 'healthy' as const,
        connected: true,
        responseTime: 456,
        gibbonUrl: 'http://localhost:8080/gibbon',
        details: {
          status: 'healthy',
          timestamp: '2024-02-15T10:30:00.000Z',
        },
      },
    },
  };

  beforeEach(() => {
    vi.useFakeTimers();
    (global.fetch as any).mockResolvedValue({
      ok: true,
      json: async () => mockHealthData,
    });
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
  });

  it('renders the health dashboard page', async () => {
    render(<HealthDashboardPage />);

    expect(screen.getByText('System Health Dashboard')).toBeInTheDocument();
    expect(screen.getByText('Real-time monitoring of all LAYA services')).toBeInTheDocument();
  });

  it('fetches health data on mount', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith('/api/health');
    });
  });

  it('displays overall status when data is loaded', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('All Systems Operational')).toBeInTheDocument();
    });

    expect(screen.getByText('parent-portal')).toBeInTheDocument();
    expect(screen.getByText('0.1.0')).toBeInTheDocument();
  });

  it('displays all service health cards', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('Parent Portal')).toBeInTheDocument();
      expect(screen.getByText('AI Service')).toBeInTheDocument();
      expect(screen.getByText('Gibbon CMS')).toBeInTheDocument();
    });
  });

  it('shows degraded status when a service is degraded', async () => {
    const degradedData = {
      ...mockHealthData,
      status: 'degraded' as const,
      checks: {
        ...mockHealthData.checks,
        aiService: {
          ...mockHealthData.checks.aiService,
          status: 'degraded' as const,
        },
      },
    };

    (global.fetch as any).mockResolvedValue({
      ok: true,
      json: async () => degradedData,
    });

    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('Partial System Outage')).toBeInTheDocument();
    });
  });

  it('shows unhealthy status when a service is down', async () => {
    const unhealthyData = {
      ...mockHealthData,
      status: 'unhealthy' as const,
      checks: {
        ...mockHealthData.checks,
        aiService: {
          status: 'unhealthy' as const,
          connected: false,
          error: 'Connection refused',
          apiUrl: 'http://localhost:8000',
        },
        gibbon: {
          status: 'unhealthy' as const,
          connected: false,
          error: 'Connection refused',
          gibbonUrl: 'http://localhost:8080/gibbon',
        },
      },
    };

    (global.fetch as any).mockResolvedValue({
      ok: true,
      json: async () => unhealthyData,
    });

    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('System Outage')).toBeInTheDocument();
    });
  });

  it('handles fetch errors gracefully', async () => {
    (global.fetch as any).mockRejectedValue(new Error('Network error'));

    render(<HealthDashboardPage />);

    await waitFor(() => {
      // Should still render the page structure
      expect(screen.getByText('System Health Dashboard')).toBeInTheDocument();
    });
  });

  it('manual refresh button triggers data fetch', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    const refreshButton = screen.getByText('Refresh');
    fireEvent.click(refreshButton);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
    });
  });

  it('auto-refresh checkbox controls automatic refresh', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    // Find and uncheck the auto-refresh checkbox
    const checkbox = screen.getByRole('checkbox');
    fireEvent.click(checkbox);

    // Advance timers by 60 seconds
    vi.advanceTimersByTime(60000);

    // Should not have called fetch again
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  it('auto-refresh fetches data every 60 seconds', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    // Advance timers by 60 seconds
    vi.advanceTimersByTime(60000);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(2);
    });

    // Advance another 60 seconds
    vi.advanceTimersByTime(60000);

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(3);
    });
  });

  it('displays last update timestamp', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('Last updated:')).toBeInTheDocument();
    });
  });

  it('displays information about health monitoring', async () => {
    render(<HealthDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText('About Health Monitoring')).toBeInTheDocument();
      expect(
        screen.getByText(
          /This dashboard provides real-time monitoring of all LAYA services/
        )
      ).toBeInTheDocument();
    });
  });

  it('back button navigates to home', async () => {
    render(<HealthDashboardPage />);

    const backButton = screen.getByRole('link');
    expect(backButton).toHaveAttribute('href', '/');
  });

  it('disables refresh button while loading', async () => {
    render(<HealthDashboardPage />);

    const refreshButton = screen.getByText('Refresh');
    fireEvent.click(refreshButton);

    // Button should be disabled during loading
    expect(refreshButton).toBeDisabled();

    await waitFor(() => {
      expect(refreshButton).not.toBeDisabled();
    });
  });

  it('shows loading state initially', () => {
    render(<HealthDashboardPage />);

    expect(screen.getByText('Checking...')).toBeInTheDocument();
  });
});
