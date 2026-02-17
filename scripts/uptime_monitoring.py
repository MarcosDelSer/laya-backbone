#!/usr/bin/env python3
"""LAYA Uptime Monitoring Script.

Continuously monitors all LAYA service health endpoints every 60 seconds.
Alerts on failures and tracks uptime statistics.

Usage:
    python scripts/uptime_monitoring.py

Environment Variables:
    AI_SERVICE_URL: AI Service URL (default: http://localhost:8000)
    GIBBON_URL: Gibbon CMS URL (default: http://localhost:80)
    PARENT_PORTAL_URL: Parent Portal URL (default: http://localhost:3000)
    CHECK_INTERVAL: Check interval in seconds (default: 60)
    ALERT_EMAIL: Email address for alerts (optional)
    ALERT_WEBHOOK: Webhook URL for alerts (optional)
    LOG_FILE: Log file path (default: uptime_monitoring.log)
"""

import argparse
import json
import logging
import os
import smtplib
import sys
import time
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from email.mime.text import MIMEText
from typing import Dict, List, Optional
from urllib.parse import urljoin

try:
    import requests
except ImportError:
    print("Error: requests library is required. Install with: pip install requests")
    sys.exit(1)


# Configuration from environment variables
AI_SERVICE_URL = os.getenv("AI_SERVICE_URL", "http://localhost:8000")
GIBBON_URL = os.getenv("GIBBON_URL", "http://localhost:80")
PARENT_PORTAL_URL = os.getenv("PARENT_PORTAL_URL", "http://localhost:3000")
CHECK_INTERVAL = int(os.getenv("CHECK_INTERVAL", "60"))
ALERT_EMAIL = os.getenv("ALERT_EMAIL", "")
ALERT_WEBHOOK = os.getenv("ALERT_WEBHOOK", "")
LOG_FILE = os.getenv("LOG_FILE", "uptime_monitoring.log")
SMTP_SERVER = os.getenv("SMTP_SERVER", "localhost")
SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
SMTP_USER = os.getenv("SMTP_USER", "")
SMTP_PASSWORD = os.getenv("SMTP_PASSWORD", "")
ALERT_FROM_EMAIL = os.getenv("ALERT_FROM_EMAIL", "monitoring@laya.local")

# HTTP request timeout
REQUEST_TIMEOUT = 10


@dataclass
class ServiceCheck:
    """Service health check configuration."""

    name: str
    url: str
    endpoint: str
    timeout: int = REQUEST_TIMEOUT
    last_status: Optional[str] = None
    last_check: Optional[datetime] = None
    consecutive_failures: int = 0
    total_checks: int = 0
    total_failures: int = 0
    downtime_start: Optional[datetime] = None
    response_times: List[float] = field(default_factory=list)

    @property
    def full_url(self) -> str:
        """Get the full URL for the health check."""
        return urljoin(self.url, self.endpoint)

    @property
    def uptime_percentage(self) -> float:
        """Calculate uptime percentage."""
        if self.total_checks == 0:
            return 100.0
        return ((self.total_checks - self.total_failures) / self.total_checks) * 100

    @property
    def avg_response_time(self) -> float:
        """Calculate average response time in milliseconds."""
        if not self.response_times:
            return 0.0
        return sum(self.response_times) / len(self.response_times)


class UptimeMonitor:
    """Uptime monitoring service for LAYA services."""

    def __init__(
        self,
        check_interval: int = CHECK_INTERVAL,
        alert_email: str = ALERT_EMAIL,
        alert_webhook: str = ALERT_WEBHOOK,
        log_file: str = LOG_FILE,
    ):
        """Initialize the uptime monitor.

        Args:
            check_interval: Interval between checks in seconds
            alert_email: Email address for alerts
            alert_webhook: Webhook URL for alerts
            log_file: Path to log file
        """
        self.check_interval = check_interval
        self.alert_email = alert_email
        self.alert_webhook = alert_webhook
        self.start_time = datetime.now()

        # Setup logging
        self.setup_logging(log_file)

        # Initialize service checks
        self.services = [
            ServiceCheck(
                name="AI Service",
                url=AI_SERVICE_URL,
                endpoint="/api/v1/health/liveness",
            ),
            ServiceCheck(
                name="Gibbon CMS",
                url=GIBBON_URL,
                endpoint="/modules/System/health.php",
            ),
            ServiceCheck(
                name="Parent Portal",
                url=PARENT_PORTAL_URL,
                endpoint="/api/health",
            ),
        ]

        self.logger.info("=" * 80)
        self.logger.info("LAYA Uptime Monitoring Started")
        self.logger.info("=" * 80)
        self.logger.info(f"Check Interval: {self.check_interval}s")
        self.logger.info(f"Services to monitor: {len(self.services)}")
        for service in self.services:
            self.logger.info(f"  - {service.name}: {service.full_url}")
        if self.alert_email:
            self.logger.info(f"Email Alerts: Enabled ({self.alert_email})")
        if self.alert_webhook:
            self.logger.info(f"Webhook Alerts: Enabled")
        self.logger.info("=" * 80)

    def setup_logging(self, log_file: str) -> None:
        """Setup logging configuration.

        Args:
            log_file: Path to log file
        """
        # Create logger
        self.logger = logging.getLogger("UptimeMonitor")
        self.logger.setLevel(logging.INFO)

        # Console handler
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.INFO)
        console_formatter = logging.Formatter(
            "%(asctime)s - %(levelname)s - %(message)s", datefmt="%Y-%m-%d %H:%M:%S"
        )
        console_handler.setFormatter(console_formatter)
        self.logger.addHandler(console_handler)

        # File handler
        try:
            file_handler = logging.FileHandler(log_file)
            file_handler.setLevel(logging.INFO)
            file_formatter = logging.Formatter(
                "%(asctime)s - %(levelname)s - %(message)s",
                datefmt="%Y-%m-%d %H:%M:%S",
            )
            file_handler.setFormatter(file_formatter)
            self.logger.addHandler(file_handler)
        except Exception as e:
            self.logger.warning(f"Could not create log file {log_file}: {e}")

    def check_service(self, service: ServiceCheck) -> bool:
        """Check if a service is healthy.

        Args:
            service: Service check configuration

        Returns:
            True if service is healthy, False otherwise
        """
        service.total_checks += 1
        service.last_check = datetime.now()

        try:
            start_time = time.time()
            response = requests.get(
                service.full_url, timeout=service.timeout, allow_redirects=True
            )
            response_time = (time.time() - start_time) * 1000  # Convert to ms

            # Keep last 100 response times for averaging
            service.response_times.append(response_time)
            if len(service.response_times) > 100:
                service.response_times.pop(0)

            # Check if response is successful (200 or 503 can be acceptable for degraded state)
            is_healthy = response.status_code in [200, 503]

            if is_healthy:
                # Parse response to check actual health status
                try:
                    data = response.json()
                    status = data.get("status", "unknown").lower()

                    # Log based on actual health status
                    if status == "healthy":
                        self.logger.info(
                            f"✓ {service.name}: healthy ({response.status_code}) - "
                            f"{response_time:.0f}ms"
                        )
                    elif status in ["degraded", "unhealthy"]:
                        self.logger.warning(
                            f"⚠ {service.name}: {status} ({response.status_code}) - "
                            f"{response_time:.0f}ms"
                        )
                    else:
                        self.logger.info(
                            f"✓ {service.name}: {status} ({response.status_code}) - "
                            f"{response_time:.0f}ms"
                        )

                    # Update service status
                    previous_status = service.last_status
                    service.last_status = status

                    # If recovering from downtime
                    if service.consecutive_failures > 0:
                        downtime = (
                            datetime.now() - service.downtime_start
                            if service.downtime_start
                            else timedelta(0)
                        )
                        self.logger.info(
                            f"🔄 {service.name}: RECOVERED after "
                            f"{service.consecutive_failures} failures "
                            f"(downtime: {downtime})"
                        )
                        self.send_alert(
                            service,
                            "recovered",
                            f"Service recovered after {downtime}",
                        )
                        service.consecutive_failures = 0
                        service.downtime_start = None

                    # Alert on status changes
                    elif previous_status and previous_status != status:
                        if status in ["degraded", "unhealthy"]:
                            self.send_alert(
                                service,
                                status,
                                f"Service status changed from {previous_status} to {status}",
                            )

                    return True

                except (json.JSONDecodeError, KeyError) as e:
                    self.logger.warning(
                        f"⚠ {service.name}: Unable to parse response: {e}"
                    )
                    return True  # Still consider it up if we got a response

            else:
                self.logger.error(
                    f"✗ {service.name}: HTTP {response.status_code} - "
                    f"{response_time:.0f}ms"
                )
                return False

        except requests.exceptions.ConnectionError as e:
            self.logger.error(f"✗ {service.name}: Connection failed - {e}")
            return False
        except requests.exceptions.Timeout:
            self.logger.error(
                f"✗ {service.name}: Request timeout (>{service.timeout}s)"
            )
            return False
        except requests.exceptions.RequestException as e:
            self.logger.error(f"✗ {service.name}: Request failed - {e}")
            return False
        except Exception as e:
            self.logger.error(f"✗ {service.name}: Unexpected error - {e}")
            return False

    def handle_failure(self, service: ServiceCheck) -> None:
        """Handle service failure.

        Args:
            service: Service check configuration
        """
        service.total_failures += 1
        service.consecutive_failures += 1

        # Mark downtime start
        if service.consecutive_failures == 1:
            service.downtime_start = datetime.now()

        # Alert on first failure, then every 5 failures
        if service.consecutive_failures == 1 or service.consecutive_failures % 5 == 0:
            self.logger.error(
                f"🚨 {service.name}: ALERT - {service.consecutive_failures} "
                f"consecutive failures"
            )
            self.send_alert(
                service,
                "failure",
                f"Service is down ({service.consecutive_failures} consecutive failures)",
            )

    def send_alert(self, service: ServiceCheck, alert_type: str, message: str) -> None:
        """Send alert via configured channels.

        Args:
            service: Service check configuration
            alert_type: Type of alert (failure, recovered, degraded, etc.)
            message: Alert message
        """
        # Email alert
        if self.alert_email:
            self.send_email_alert(service, alert_type, message)

        # Webhook alert
        if self.alert_webhook:
            self.send_webhook_alert(service, alert_type, message)

    def send_email_alert(
        self, service: ServiceCheck, alert_type: str, message: str
    ) -> None:
        """Send email alert.

        Args:
            service: Service check configuration
            alert_type: Type of alert
            message: Alert message
        """
        try:
            subject = f"[LAYA] {service.name} - {alert_type.upper()}"
            body = f"""
LAYA Service Alert

Service: {service.name}
Status: {alert_type.upper()}
URL: {service.full_url}
Time: {datetime.now().isoformat()}

Details: {message}

Statistics:
- Total Checks: {service.total_checks}
- Total Failures: {service.total_failures}
- Consecutive Failures: {service.consecutive_failures}
- Uptime: {service.uptime_percentage:.2f}%
- Avg Response Time: {service.avg_response_time:.0f}ms

---
LAYA Uptime Monitoring
            """.strip()

            msg = MIMEText(body)
            msg["Subject"] = subject
            msg["From"] = ALERT_FROM_EMAIL
            msg["To"] = self.alert_email

            if SMTP_USER and SMTP_PASSWORD:
                with smtplib.SMTP(SMTP_SERVER, SMTP_PORT) as server:
                    server.starttls()
                    server.login(SMTP_USER, SMTP_PASSWORD)
                    server.send_message(msg)
                self.logger.info(f"📧 Email alert sent to {self.alert_email}")
            else:
                self.logger.warning("Email alerts configured but SMTP credentials missing")

        except Exception as e:
            self.logger.error(f"Failed to send email alert: {e}")

    def send_webhook_alert(
        self, service: ServiceCheck, alert_type: str, message: str
    ) -> None:
        """Send webhook alert.

        Args:
            service: Service check configuration
            alert_type: Type of alert
            message: Alert message
        """
        try:
            payload = {
                "service": service.name,
                "status": alert_type,
                "url": service.full_url,
                "message": message,
                "timestamp": datetime.now().isoformat(),
                "statistics": {
                    "total_checks": service.total_checks,
                    "total_failures": service.total_failures,
                    "consecutive_failures": service.consecutive_failures,
                    "uptime_percentage": round(service.uptime_percentage, 2),
                    "avg_response_time_ms": round(service.avg_response_time, 0),
                },
            }

            response = requests.post(
                self.alert_webhook, json=payload, timeout=10, allow_redirects=True
            )
            response.raise_for_status()
            self.logger.info("📡 Webhook alert sent successfully")

        except Exception as e:
            self.logger.error(f"Failed to send webhook alert: {e}")

    def print_status_report(self) -> None:
        """Print current status report."""
        uptime = datetime.now() - self.start_time
        self.logger.info("")
        self.logger.info("=" * 80)
        self.logger.info(f"Status Report - Uptime: {uptime}")
        self.logger.info("=" * 80)

        for service in self.services:
            status_emoji = "✓" if service.consecutive_failures == 0 else "✗"
            self.logger.info(
                f"{status_emoji} {service.name:20s} | "
                f"Status: {(service.last_status or 'unknown'):10s} | "
                f"Uptime: {service.uptime_percentage:6.2f}% | "
                f"Failures: {service.total_failures:3d}/{service.total_checks:3d} | "
                f"Avg RT: {service.avg_response_time:6.0f}ms"
            )

        self.logger.info("=" * 80)
        self.logger.info("")

    def run(self) -> None:
        """Run the monitoring loop."""
        try:
            iteration = 0
            while True:
                iteration += 1

                # Check all services
                for service in self.services:
                    is_healthy = self.check_service(service)
                    if not is_healthy:
                        self.handle_failure(service)

                # Print status report every 10 iterations (10 minutes)
                if iteration % 10 == 0:
                    self.print_status_report()

                # Wait for next check
                time.sleep(self.check_interval)

        except KeyboardInterrupt:
            self.logger.info("")
            self.logger.info("=" * 80)
            self.logger.info("Monitoring stopped by user")
            self.print_status_report()
            self.logger.info("=" * 80)
            sys.exit(0)
        except Exception as e:
            self.logger.error(f"Fatal error: {e}")
            sys.exit(1)


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="LAYA Uptime Monitoring - Check health endpoints every 60s"
    )
    parser.add_argument(
        "--interval",
        type=int,
        default=CHECK_INTERVAL,
        help=f"Check interval in seconds (default: {CHECK_INTERVAL})",
    )
    parser.add_argument(
        "--email",
        type=str,
        default=ALERT_EMAIL,
        help="Email address for alerts",
    )
    parser.add_argument(
        "--webhook",
        type=str,
        default=ALERT_WEBHOOK,
        help="Webhook URL for alerts",
    )
    parser.add_argument(
        "--log-file",
        type=str,
        default=LOG_FILE,
        help=f"Log file path (default: {LOG_FILE})",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Run a single check and exit",
    )

    args = parser.parse_args()

    # Create monitor
    monitor = UptimeMonitor(
        check_interval=args.interval,
        alert_email=args.email,
        alert_webhook=args.webhook,
        log_file=args.log_file,
    )

    # Dry run mode - check once and exit
    if args.dry_run:
        monitor.logger.info("Running in dry-run mode (single check)")
        for service in monitor.services:
            monitor.check_service(service)
        monitor.print_status_report()
        sys.exit(0)

    # Run monitoring loop
    monitor.run()


if __name__ == "__main__":
    main()
