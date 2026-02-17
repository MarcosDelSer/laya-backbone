"""Unit tests for structured JSON logging.

Tests for logger configuration, log levels, JSON output,
and request ID correlation.
"""

from __future__ import annotations

import json
import logging
from io import StringIO
from typing import Any
from unittest.mock import patch

import pytest
import structlog

from app.core.logging import (
    add_log_level,
    add_request_id,
    bind_request_id,
    configure_logging,
    get_logger,
)


@pytest.fixture(autouse=True)
def reset_logging() -> None:
    """Reset logging configuration before each test.

    This ensures tests don't interfere with each other.
    """
    # Clear all handlers
    logging.root.handlers = []
    # Reset structlog configuration
    structlog.reset_defaults()


def test_configure_logging_json_format() -> None:
    """Test that JSON logging format is configured correctly.

    Verifies that logs are output in JSON format with all required fields.
    """
    # Capture log output to StringIO
    output = StringIO()

    # Configure logging with JSON output to our StringIO
    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__)
    logger.info("test message", key="value")

    # Get the log output
    log_line = output.getvalue().strip()

    # Should have output
    assert log_line, "Expected log output but got empty string"

    # Parse as JSON
    log_data = json.loads(log_line)

    # Verify required fields
    assert "event" in log_data
    assert log_data["event"] == "test message"
    assert "timestamp" in log_data
    assert "level" in log_data
    assert log_data["level"] == "info"
    assert "key" in log_data
    assert log_data["key"] == "value"


def test_configure_logging_human_readable_format() -> None:
    """Test that human-readable logging format is configured correctly.

    Verifies that logs are output in a human-readable format for development.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=False, stream=output)

    logger = get_logger(__name__)
    logger.info("test message", key="value")

    # Get the log output
    log_line = output.getvalue().strip()

    # Should have output
    assert log_line, "Expected log output but got empty string"

    # Should contain the message (not strict JSON)
    assert "test message" in log_line


def test_log_levels() -> None:
    """Test that all log levels work correctly.

    Verifies DEBUG, INFO, WARNING, ERROR, and CRITICAL levels.
    """
    output = StringIO()

    configure_logging(log_level="DEBUG", json_logs=True, stream=output)

    logger = get_logger(__name__)

    # Test all log levels
    logger.debug("debug message")
    logger.info("info message")
    logger.warning("warning message")
    logger.error("error message")
    logger.critical("critical message")

    # Get all log lines
    log_output = output.getvalue().strip()
    log_lines = [line for line in log_output.split("\n") if line.strip()]

    assert len(log_lines) == 5, f"Expected 5 log lines, got {len(log_lines)}"

    # Verify each log level
    levels = []
    for line in log_lines:
        log_data = json.loads(line)
        levels.append(log_data["level"])

    assert "debug" in levels
    assert "info" in levels
    assert "warning" in levels
    assert "error" in levels
    assert "critical" in levels


def test_log_level_filtering() -> None:
    """Test that log level filtering works correctly.

    Verifies that logs below the configured level are not output.
    """
    output = StringIO()

    configure_logging(log_level="WARNING", json_logs=True, stream=output)

    logger = get_logger(__name__)

    # Log at different levels
    logger.debug("debug message")  # Should not appear
    logger.info("info message")  # Should not appear
    logger.warning("warning message")  # Should appear
    logger.error("error message")  # Should appear

    # Get all log lines
    log_output = output.getvalue().strip()

    # Only WARNING and ERROR should be logged
    assert "debug message" not in log_output
    assert "info message" not in log_output
    assert "warning message" in log_output
    assert "error message" in log_output


def test_get_logger_with_name() -> None:
    """Test getting a logger with a specific name.

    Verifies that logger name is included in log output.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger("test.module")
    logger.info("test message")

    # Parse log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify logger name is included
    assert "logger" in log_data
    assert log_data["logger"] == "test.module"


def test_get_logger_with_initial_values() -> None:
    """Test getting a logger with initial context values.

    Verifies that initial values are included in all log entries.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__, service="coaching", version="1.0")
    logger.info("test message")

    # Parse log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify initial values are included
    assert log_data["service"] == "coaching"
    assert log_data["version"] == "1.0"


def test_bind_request_id() -> None:
    """Test binding request ID to logger.

    Verifies that request ID is included in all subsequent log entries.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__)
    test_request_id = "12345678-1234-5678-1234-567812345678"

    # Bind request ID
    logger = bind_request_id(logger, test_request_id)
    logger.info("test message")

    # Parse log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify request ID is included
    assert "request_id" in log_data
    assert log_data["request_id"] == test_request_id


def test_bind_multiple_context_values() -> None:
    """Test binding multiple context values to logger.

    Verifies that multiple context values can be bound and are included in logs.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__)

    # Bind multiple values
    logger = logger.bind(
        request_id="12345678-1234-5678-1234-567812345678",
        user_id="user-123",
        child_id="child-456",
    )
    logger.info("test message")

    # Parse log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify all bound values are included
    assert log_data["request_id"] == "12345678-1234-5678-1234-567812345678"
    assert log_data["user_id"] == "user-123"
    assert log_data["child_id"] == "child-456"


def test_exception_logging() -> None:
    """Test that exceptions are logged with stack traces.

    Verifies that exception information is included in log output.
    """
    output = StringIO()

    configure_logging(log_level="ERROR", json_logs=True, stream=output)

    logger = get_logger(__name__)

    try:
        raise ValueError("Test exception")
    except ValueError:
        logger.error("An error occurred", exc_info=True)

    # Get log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify exception info is included
    assert "exception" in log_data
    assert "ValueError" in log_data["exception"]
    assert "Test exception" in log_data["exception"]


def test_add_request_id_processor() -> None:
    """Test the add_request_id processor function.

    Verifies that the processor adds request_id field if not present.
    """
    event_dict: dict[str, Any] = {"event": "test"}
    logger = logging.getLogger("test")

    # Process event dict
    result = add_request_id(logger, "info", event_dict)

    # Should add request_id field with None if not present
    assert "request_id" in result
    assert result["request_id"] is None

    # Should not overwrite existing request_id
    event_dict_with_id: dict[str, Any] = {
        "event": "test",
        "request_id": "existing-id",
    }
    result = add_request_id(logger, "info", event_dict_with_id)
    assert result["request_id"] == "existing-id"


def test_add_log_level_processor() -> None:
    """Test the add_log_level processor function.

    Verifies that the processor adds level field correctly.
    """
    event_dict: dict[str, Any] = {"event": "test"}
    logger = logging.getLogger("test")

    # Test various log levels
    result = add_log_level(logger, "info", event_dict.copy())
    assert result["level"] == "info"

    result = add_log_level(logger, "error", event_dict.copy())
    assert result["level"] == "error"

    result = add_log_level(logger, "debug", event_dict.copy())
    assert result["level"] == "debug"

    # Test 'warn' normalization to 'warning'
    result = add_log_level(logger, "warn", event_dict.copy())
    assert result["level"] == "warning"


def test_structured_context_preservation() -> None:
    """Test that structured context is preserved across log calls.

    Verifies that bound context values persist for multiple log calls.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__)
    logger = logger.bind(session_id="session-123")

    # Log multiple messages
    logger.info("first message", extra="data1")
    logger.info("second message", extra="data2")

    # Get all log lines
    log_output = output.getvalue().strip()
    log_lines = [line for line in log_output.split("\n") if line.strip()]

    # Both should have session_id
    log1 = json.loads(log_lines[0])
    log2 = json.loads(log_lines[1])

    assert log1["session_id"] == "session-123"
    assert log2["session_id"] == "session-123"

    # But different extra values
    assert log1["extra"] == "data1"
    assert log2["extra"] == "data2"


def test_timestamp_format() -> None:
    """Test that timestamps are in ISO format.

    Verifies that log entries include ISO-formatted timestamps.
    """
    output = StringIO()

    configure_logging(log_level="INFO", json_logs=True, stream=output)

    logger = get_logger(__name__)
    logger.info("test message")

    # Parse log output
    log_line = output.getvalue().strip()
    log_data = json.loads(log_line)

    # Verify timestamp exists and is in ISO format
    assert "timestamp" in log_data
    # ISO format includes 'T' separator and timezone info
    assert "T" in log_data["timestamp"]
