"""Tests for the alert manager service.

Tests alert configuration, sending alerts through multiple channels,
and integration with health check system.
"""

import json
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.services.alert_manager import (
    AlertChannel,
    AlertConfig,
    AlertManager,
    AlertSeverity,
)


class TestAlertConfig:
    """Tests for AlertConfig class."""

    def test_default_config(self):
        """Test default alert configuration."""
        config = AlertConfig()

        assert config.enabled is False
        assert config.channels == []
        assert config.email_smtp_host == "localhost"
        assert config.email_smtp_port == 587
        assert config.email_to == []
        assert config.min_severity == AlertSeverity.WARNING

    def test_custom_config(self):
        """Test custom alert configuration."""
        config = AlertConfig(
            enabled=True,
            channels=[AlertChannel.EMAIL, AlertChannel.SLACK],
            email_to=["admin@example.com"],
            min_severity=AlertSeverity.ERROR,
        )

        assert config.enabled is True
        assert AlertChannel.EMAIL in config.channels
        assert AlertChannel.SLACK in config.channels
        assert "admin@example.com" in config.email_to
        assert config.min_severity == AlertSeverity.ERROR


class TestAlertManager:
    """Tests for AlertManager class."""

    def test_init_with_config(self):
        """Test alert manager initialization with config."""
        config = AlertConfig(enabled=True)
        manager = AlertManager(config)

        assert manager.config.enabled is True
        assert manager._alert_history == []

    @patch.dict(
        "os.environ",
        {
            "ALERTS_ENABLED": "true",
            "ALERT_CHANNELS": "email,slack",
            "ALERT_EMAIL_TO": "admin@example.com,ops@example.com",
            "ALERT_MIN_SEVERITY": "critical",
        },
    )
    def test_load_config_from_env(self):
        """Test loading configuration from environment variables."""
        config = AlertManager._load_config_from_env()

        assert config.enabled is True
        assert AlertChannel.EMAIL in config.channels
        assert AlertChannel.SLACK in config.channels
        assert "admin@example.com" in config.email_to
        assert "ops@example.com" in config.email_to
        assert config.min_severity == AlertSeverity.CRITICAL

    @pytest.mark.asyncio
    async def test_send_alert_disabled(self):
        """Test sending alert when alerting is disabled."""
        config = AlertConfig(enabled=False)
        manager = AlertManager(config)

        result = await manager.send_alert(
            title="Test Alert",
            message="This is a test",
            severity=AlertSeverity.ERROR,
        )

        assert result["sent"] is False
        assert result["reason"] == "alerting_disabled"

    @pytest.mark.asyncio
    async def test_send_alert_below_min_severity(self):
        """Test sending alert below minimum severity threshold."""
        config = AlertConfig(enabled=True, min_severity=AlertSeverity.ERROR)
        manager = AlertManager(config)

        result = await manager.send_alert(
            title="Test Alert",
            message="This is a test",
            severity=AlertSeverity.WARNING,
        )

        assert result["sent"] is False
        assert result["reason"] == "below_min_severity"

    @pytest.mark.asyncio
    async def test_send_email_alert_no_recipients(self):
        """Test sending email alert with no recipients configured."""
        config = AlertConfig(
            enabled=True,
            channels=[AlertChannel.EMAIL],
            email_to=[],
        )
        manager = AlertManager(config)

        alert_data = {
            "title": "Test",
            "message": "Test",
            "severity": "error",
            "timestamp": "2024-01-01T00:00:00Z",
            "service": "test",
            "context": {},
        }

        result = await manager._send_email_alert(alert_data)

        assert result["success"] is False
        assert "No email recipients" in result["error"]

    @pytest.mark.asyncio
    async def test_send_webhook_alert_no_url(self):
        """Test sending webhook alert with no URL configured."""
        config = AlertConfig(
            enabled=True,
            channels=[AlertChannel.WEBHOOK],
            webhook_url="",
        )
        manager = AlertManager(config)

        alert_data = {
            "title": "Test",
            "message": "Test",
            "severity": "error",
            "timestamp": "2024-01-01T00:00:00Z",
            "service": "test",
            "context": {},
        }

        result = await manager._send_webhook_alert(alert_data)

        assert result["success"] is False
        assert "No webhook URL" in result["error"]

    @pytest.mark.asyncio
    async def test_send_slack_alert_no_url(self):
        """Test sending Slack alert with no URL configured."""
        config = AlertConfig(
            enabled=True,
            channels=[AlertChannel.SLACK],
            slack_webhook_url="",
        )
        manager = AlertManager(config)

        alert_data = {
            "title": "Test",
            "message": "Test",
            "severity": "error",
            "timestamp": "2024-01-01T00:00:00Z",
            "service": "test",
            "context": {},
        }

        result = await manager._send_slack_alert(alert_data)

        assert result["success"] is False
        assert "No Slack webhook" in result["error"]

    @pytest.mark.asyncio
    @patch("app.services.alert_manager.smtplib.SMTP")
    async def test_send_email_alert_success(self, mock_smtp):
        """Test successful email alert sending."""
        config = AlertConfig(
            enabled=True,
            channels=[AlertChannel.EMAIL],
            email_to=["admin@example.com"],
            email_smtp_user="user",
            email_smtp_password="pass",
        )
        manager = AlertManager(config)

        # Mock SMTP server
        mock_server = MagicMock()
        mock_smtp.return_value.__enter__.return_value = mock_server

        alert_data = {
            "title": "Test Alert",
            "message": "This is a test",
            "severity": "error",
            "timestamp": "2024-01-01T00:00:00Z",
            "service": "laya-ai-service",
            "context": {"test": True},
        }

        result = await manager._send_email_alert(alert_data)

        assert result["success"] is True
        assert result["recipients"] == ["admin@example.com"]
        mock_server.starttls.assert_called_once()
        mock_server.login.assert_called_once_with("user", "pass")
        mock_server.send_message.assert_called_once()

    @pytest.mark.asyncio
    async def test_get_alert_history(self):
        """Test retrieving alert history."""
        config = AlertConfig(enabled=True)
        manager = AlertManager(config)

        # Add some alerts to history
        for i in range(5):
            manager._alert_history.append({"id": i})

        # Get last 3 alerts
        history = manager.get_alert_history(limit=3)

        assert len(history) == 3
        assert history[0]["id"] == 2
        assert history[-1]["id"] == 4
