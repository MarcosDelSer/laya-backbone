"""Alert Manager for LAYA AI Service.

Provides centralized alerting functionality for health check failures and
critical system events. Supports multiple alert channels including email,
webhooks, and Slack.
"""

import asyncio
import json
import logging
import os
import smtplib
from dataclasses import dataclass
from datetime import datetime
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from enum import Enum
from typing import Any, Dict, List, Optional

try:
    import aiohttp
except ImportError:
    aiohttp = None

logger = logging.getLogger(__name__)


class AlertSeverity(str, Enum):
    """Alert severity levels."""

    INFO = "info"
    WARNING = "warning"
    ERROR = "error"
    CRITICAL = "critical"


class AlertChannel(str, Enum):
    """Available alert channels."""

    EMAIL = "email"
    WEBHOOK = "webhook"
    SLACK = "slack"


@dataclass
class AlertConfig:
    """Configuration for alert channels.

    Attributes:
        enabled: Whether alerting is enabled
        channels: List of enabled alert channels
        email_smtp_host: SMTP server host for email alerts
        email_smtp_port: SMTP server port for email alerts
        email_smtp_user: SMTP username for authentication
        email_smtp_password: SMTP password for authentication
        email_from: From address for email alerts
        email_to: Recipient addresses for email alerts
        webhook_url: Webhook URL for HTTP POST alerts
        slack_webhook_url: Slack webhook URL for Slack alerts
        min_severity: Minimum severity level to trigger alerts
    """

    enabled: bool = False
    channels: List[AlertChannel] = None
    email_smtp_host: str = "localhost"
    email_smtp_port: int = 587
    email_smtp_user: str = ""
    email_smtp_password: str = ""
    email_from: str = "alerts@laya.local"
    email_to: List[str] = None
    webhook_url: str = ""
    slack_webhook_url: str = ""
    min_severity: AlertSeverity = AlertSeverity.WARNING

    def __post_init__(self):
        """Initialize default values."""
        if self.channels is None:
            self.channels = []
        if self.email_to is None:
            self.email_to = []


class AlertManager:
    """Centralized alert manager for system health monitoring.

    Sends alerts through multiple channels (email, webhook, Slack) when
    critical system events occur such as health check failures, resource
    exhaustion, or service degradation.
    """

    def __init__(self, config: Optional[AlertConfig] = None):
        """Initialize the alert manager.

        Args:
            config: Alert configuration. If None, loads from environment variables.
        """
        self.config = config or self._load_config_from_env()
        self._alert_history: List[Dict[str, Any]] = []
        self._max_history = 100

    @staticmethod
    def _load_config_from_env() -> AlertConfig:
        """Load alert configuration from environment variables.

        Returns:
            AlertConfig instance populated from environment variables
        """
        enabled = os.getenv("ALERTS_ENABLED", "false").lower() == "true"

        # Parse enabled channels from comma-separated list
        channels_str = os.getenv("ALERT_CHANNELS", "")
        channels = []
        if channels_str:
            for channel in channels_str.split(","):
                channel = channel.strip().lower()
                if channel in [c.value for c in AlertChannel]:
                    channels.append(AlertChannel(channel))

        # Parse email recipients
        email_to_str = os.getenv("ALERT_EMAIL_TO", "")
        email_to = [e.strip() for e in email_to_str.split(",") if e.strip()]

        # Parse minimum severity
        min_severity_str = os.getenv("ALERT_MIN_SEVERITY", "warning").lower()
        min_severity = AlertSeverity.WARNING
        if min_severity_str in [s.value for s in AlertSeverity]:
            min_severity = AlertSeverity(min_severity_str)

        return AlertConfig(
            enabled=enabled,
            channels=channels,
            email_smtp_host=os.getenv("ALERT_SMTP_HOST", "localhost"),
            email_smtp_port=int(os.getenv("ALERT_SMTP_PORT", "587")),
            email_smtp_user=os.getenv("ALERT_SMTP_USER", ""),
            email_smtp_password=os.getenv("ALERT_SMTP_PASSWORD", ""),
            email_from=os.getenv("ALERT_EMAIL_FROM", "alerts@laya.local"),
            email_to=email_to,
            webhook_url=os.getenv("ALERT_WEBHOOK_URL", ""),
            slack_webhook_url=os.getenv("ALERT_SLACK_WEBHOOK_URL", ""),
            min_severity=min_severity,
        )

    async def send_alert(
        self,
        title: str,
        message: str,
        severity: AlertSeverity = AlertSeverity.WARNING,
        context: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """Send an alert through all configured channels.

        Args:
            title: Alert title/subject
            message: Alert message body
            severity: Alert severity level
            context: Additional context data to include with alert

        Returns:
            Dict containing alert results for each channel
        """
        if not self.config.enabled:
            logger.debug("Alerting is disabled, skipping alert: %s", title)
            return {"sent": False, "reason": "alerting_disabled"}

        # Check if severity meets minimum threshold
        severity_order = {
            AlertSeverity.INFO: 0,
            AlertSeverity.WARNING: 1,
            AlertSeverity.ERROR: 2,
            AlertSeverity.CRITICAL: 3,
        }

        if severity_order[severity] < severity_order[self.config.min_severity]:
            logger.debug(
                "Alert severity %s below minimum %s, skipping: %s",
                severity,
                self.config.min_severity,
                title,
            )
            return {"sent": False, "reason": "below_min_severity"}

        # Prepare alert data
        alert_data = {
            "title": title,
            "message": message,
            "severity": severity.value,
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "service": "laya-ai-service",
            "context": context or {},
        }

        # Store in history
        self._alert_history.append(alert_data)
        if len(self._alert_history) > self._max_history:
            self._alert_history.pop(0)

        # Send through all configured channels
        results = {}
        tasks = []

        for channel in self.config.channels:
            if channel == AlertChannel.EMAIL:
                tasks.append(self._send_email_alert(alert_data))
            elif channel == AlertChannel.WEBHOOK:
                tasks.append(self._send_webhook_alert(alert_data))
            elif channel == AlertChannel.SLACK:
                tasks.append(self._send_slack_alert(alert_data))

        # Execute all alert tasks concurrently
        if tasks:
            channel_results = await asyncio.gather(*tasks, return_exceptions=True)
            for i, channel in enumerate(self.config.channels):
                result = channel_results[i]
                if isinstance(result, Exception):
                    results[channel.value] = {
                        "success": False,
                        "error": str(result),
                    }
                else:
                    results[channel.value] = result

        logger.info(
            "Alert sent: %s (severity=%s, channels=%s)",
            title,
            severity.value,
            list(results.keys()),
        )

        return {
            "sent": True,
            "timestamp": alert_data["timestamp"],
            "channels": results,
        }

    async def _send_email_alert(self, alert_data: Dict[str, Any]) -> Dict[str, Any]:
        """Send alert via email.

        Args:
            alert_data: Alert data dictionary

        Returns:
            Result dictionary with success status
        """
        if not self.config.email_to:
            return {"success": False, "error": "No email recipients configured"}

        try:
            # Create email message
            msg = MIMEMultipart("alternative")
            msg["Subject"] = f"[LAYA {alert_data['severity'].upper()}] {alert_data['title']}"
            msg["From"] = self.config.email_from
            msg["To"] = ", ".join(self.config.email_to)

            # Create plain text and HTML versions
            text_body = f"""
LAYA AI Service Alert

Severity: {alert_data['severity'].upper()}
Title: {alert_data['title']}
Time: {alert_data['timestamp']}
Service: {alert_data['service']}

Message:
{alert_data['message']}

Context:
{json.dumps(alert_data['context'], indent=2)}

---
This is an automated alert from LAYA AI Service monitoring.
            """.strip()

            html_body = f"""
<html>
<head>
    <style>
        body {{ font-family: Arial, sans-serif; }}
        .alert {{ padding: 20px; border-radius: 5px; }}
        .critical {{ background-color: #fee; border-left: 4px solid #c00; }}
        .error {{ background-color: #fef0f0; border-left: 4px solid #f44; }}
        .warning {{ background-color: #fff8e1; border-left: 4px solid #fb8c00; }}
        .info {{ background-color: #e3f2fd; border-left: 4px solid #2196f3; }}
        h1 {{ margin: 0 0 10px 0; color: #333; }}
        .meta {{ color: #666; font-size: 0.9em; }}
        pre {{ background: #f5f5f5; padding: 10px; border-radius: 3px; }}
    </style>
</head>
<body>
    <div class="alert {alert_data['severity']}">
        <h1>{alert_data['title']}</h1>
        <div class="meta">
            <strong>Severity:</strong> {alert_data['severity'].upper()}<br>
            <strong>Time:</strong> {alert_data['timestamp']}<br>
            <strong>Service:</strong> {alert_data['service']}
        </div>
        <h2>Message</h2>
        <p>{alert_data['message']}</p>
        <h2>Context</h2>
        <pre>{json.dumps(alert_data['context'], indent=2)}</pre>
    </div>
    <p style="color: #999; font-size: 0.8em; margin-top: 20px;">
        This is an automated alert from LAYA AI Service monitoring.
    </p>
</body>
</html>
            """.strip()

            # Attach both versions
            msg.attach(MIMEText(text_body, "plain"))
            msg.attach(MIMEText(html_body, "html"))

            # Send email via SMTP
            # Run SMTP operations in executor to avoid blocking
            await asyncio.get_event_loop().run_in_executor(
                None, self._send_smtp_email, msg
            )

            return {"success": True, "recipients": self.config.email_to}

        except Exception as e:
            logger.error("Failed to send email alert: %s", e)
            return {"success": False, "error": str(e)}

    def _send_smtp_email(self, msg: MIMEMultipart) -> None:
        """Send email via SMTP (blocking operation).

        Args:
            msg: Email message to send
        """
        with smtplib.SMTP(self.config.email_smtp_host, self.config.email_smtp_port) as server:
            server.starttls()
            if self.config.email_smtp_user and self.config.email_smtp_password:
                server.login(self.config.email_smtp_user, self.config.email_smtp_password)
            server.send_message(msg)

    async def _send_webhook_alert(self, alert_data: Dict[str, Any]) -> Dict[str, Any]:
        """Send alert via generic webhook.

        Args:
            alert_data: Alert data dictionary

        Returns:
            Result dictionary with success status
        """
        if not self.config.webhook_url:
            return {"success": False, "error": "No webhook URL configured"}

        if aiohttp is None:
            return {"success": False, "error": "aiohttp not installed"}

        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    self.config.webhook_url,
                    json=alert_data,
                    timeout=aiohttp.ClientTimeout(total=10),
                ) as response:
                    response.raise_for_status()
                    return {
                        "success": True,
                        "url": self.config.webhook_url,
                        "status_code": response.status,
                    }

        except Exception as e:
            logger.error("Failed to send webhook alert: %s", e)
            return {"success": False, "error": str(e)}

    async def _send_slack_alert(self, alert_data: Dict[str, Any]) -> Dict[str, Any]:
        """Send alert via Slack webhook.

        Args:
            alert_data: Alert data dictionary

        Returns:
            Result dictionary with success status
        """
        if not self.config.slack_webhook_url:
            return {"success": False, "error": "No Slack webhook URL configured"}

        if aiohttp is None:
            return {"success": False, "error": "aiohttp not installed"}

        try:
            # Map severity to Slack colors
            severity_colors = {
                "critical": "#c00000",  # Dark red
                "error": "#ff4444",  # Red
                "warning": "#fb8c00",  # Orange
                "info": "#2196f3",  # Blue
            }

            # Format context for Slack
            context_text = "\n".join(
                [f"*{k}:* {v}" for k, v in alert_data["context"].items()]
            )

            # Create Slack message
            slack_payload = {
                "attachments": [
                    {
                        "color": severity_colors.get(
                            alert_data["severity"], "#999999"
                        ),
                        "title": alert_data["title"],
                        "text": alert_data["message"],
                        "fields": [
                            {
                                "title": "Severity",
                                "value": alert_data["severity"].upper(),
                                "short": True,
                            },
                            {
                                "title": "Service",
                                "value": alert_data["service"],
                                "short": True,
                            },
                            {
                                "title": "Time",
                                "value": alert_data["timestamp"],
                                "short": False,
                            },
                        ],
                        "footer": "LAYA AI Service Monitoring",
                        "footer_icon": "https://platform.slack-edge.com/img/default_application_icon.png",
                        "ts": int(datetime.utcnow().timestamp()),
                    }
                ]
            }

            # Add context if present
            if context_text:
                slack_payload["attachments"][0]["fields"].append(
                    {
                        "title": "Context",
                        "value": context_text,
                        "short": False,
                    }
                )

            async with aiohttp.ClientSession() as session:
                async with session.post(
                    self.config.slack_webhook_url,
                    json=slack_payload,
                    timeout=aiohttp.ClientTimeout(total=10),
                ) as response:
                    response.raise_for_status()
                    return {
                        "success": True,
                        "url": self.config.slack_webhook_url,
                        "status_code": response.status,
                    }

        except Exception as e:
            logger.error("Failed to send Slack alert: %s", e)
            return {"success": False, "error": str(e)}

    def get_alert_history(self, limit: int = 10) -> List[Dict[str, Any]]:
        """Get recent alert history.

        Args:
            limit: Maximum number of alerts to return

        Returns:
            List of recent alert data dictionaries
        """
        return self._alert_history[-limit:]


# Global alert manager instance
_alert_manager: Optional[AlertManager] = None


def get_alert_manager() -> AlertManager:
    """Get the global alert manager instance.

    Returns:
        AlertManager instance
    """
    global _alert_manager
    if _alert_manager is None:
        _alert_manager = AlertManager()
    return _alert_manager
