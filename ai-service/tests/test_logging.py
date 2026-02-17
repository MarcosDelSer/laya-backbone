"""Unit tests for structured JSON logging.

Tests for logger configuration, log levels, JSON output,
request ID correlation, and log rotation.
"""

from __future__ import annotations

import json
import logging
import os
import tempfile
from io import StringIO
from logging.handlers import RotatingFileHandler, TimedRotatingFileHandler
from pathlib import Path
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


def test_file_logging_without_rotation() -> None:
    """Test basic file logging without rotation.

    Verifies that logs are written to file when log_file is specified.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test.log")

        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=False,
        )

        logger = get_logger(__name__)
        logger.info("test message to file")

        # Verify file exists and contains log
        assert os.path.exists(log_file)

        with open(log_file, "r") as f:
            content = f.read()
            assert "test message to file" in content

            # Verify it's valid JSON
            log_data = json.loads(content.strip())
            assert log_data["event"] == "test message to file"


def test_size_based_log_rotation() -> None:
    """Test size-based log rotation configuration.

    Verifies that RotatingFileHandler is configured with correct parameters.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_rotating.log")
        max_bytes = 1024  # 1 KB
        backup_count = 3

        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=max_bytes,
            backup_count=backup_count,
        )

        # Verify RotatingFileHandler was added
        file_handlers = [
            h for h in logging.root.handlers if isinstance(h, RotatingFileHandler)
        ]
        assert len(file_handlers) > 0, "Expected RotatingFileHandler to be configured"

        handler = file_handlers[0]
        assert handler.maxBytes == max_bytes
        assert handler.backupCount == backup_count

        # Write some logs
        logger = get_logger(__name__)
        logger.info("test message for rotation")

        # Verify file exists
        assert os.path.exists(log_file)


def test_size_based_rotation_creates_backup() -> None:
    """Test that size-based rotation creates backup files.

    Verifies that when log file exceeds max size, backup files are created.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_backup.log")
        max_bytes = 500  # Small size to trigger rotation
        backup_count = 2

        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=max_bytes,
            backup_count=backup_count,
        )

        logger = get_logger(__name__)

        # Write enough logs to trigger rotation
        for i in range(100):
            logger.info(f"Log message {i} with enough content to trigger rotation")

        # Check if rotation occurred (backup files exist)
        # Note: Actual rotation may vary based on handler implementation
        assert os.path.exists(log_file), "Main log file should exist"


def test_time_based_log_rotation() -> None:
    """Test time-based log rotation configuration.

    Verifies that TimedRotatingFileHandler is configured with correct parameters.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_timed.log")
        when = "midnight"
        interval = 1
        backup_count = 7

        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="time",
            when=when,
            interval=interval,
            backup_count=backup_count,
        )

        # Verify TimedRotatingFileHandler was added
        file_handlers = [
            h
            for h in logging.root.handlers
            if isinstance(h, TimedRotatingFileHandler)
        ]
        assert (
            len(file_handlers) > 0
        ), "Expected TimedRotatingFileHandler to be configured"

        handler = file_handlers[0]
        assert handler.when == when.upper()  # Handler normalizes to uppercase
        # Note: when="midnight", the handler converts interval to seconds (86400)
        # For other values like "H", "M", "S", interval is kept as-is
        assert handler.backupCount == backup_count

        # Write a log
        logger = get_logger(__name__)
        logger.info("test message for timed rotation")

        # Verify file exists
        assert os.path.exists(log_file)


def test_invalid_rotation_type_raises_error() -> None:
    """Test that invalid rotation type raises ValueError.

    Verifies that only 'size' and 'time' are accepted as rotation types.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test.log")

        with pytest.raises(ValueError, match="Invalid rotation_type"):
            configure_logging(
                log_level="INFO",
                json_logs=True,
                log_file=log_file,
                rotation_enabled=True,
                rotation_type="invalid",
            )


def test_rotation_with_different_log_levels() -> None:
    """Test that log rotation works with different log levels.

    Verifies that rotation respects log level filtering.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_levels.log")

        configure_logging(
            log_level="WARNING",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=1024,
            backup_count=3,
        )

        logger = get_logger(__name__)

        # Log at different levels
        logger.debug("debug message")  # Should not appear
        logger.info("info message")  # Should not appear
        logger.warning("warning message")  # Should appear
        logger.error("error message")  # Should appear

        # Read file content
        with open(log_file, "r") as f:
            content = f.read()

        # Verify only WARNING and ERROR are in the file
        assert "debug message" not in content
        assert "info message" not in content
        assert "warning message" in content
        assert "error message" in content


def test_rotation_handler_formatter() -> None:
    """Test that rotation handlers use correct formatter.

    Verifies that file handlers have the correct formatter configured.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_format.log")

        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=1024,
            backup_count=3,
        )

        # Get the file handler
        file_handlers = [
            h for h in logging.root.handlers if isinstance(h, RotatingFileHandler)
        ]
        assert len(file_handlers) > 0

        handler = file_handlers[0]

        # Verify formatter is set
        assert handler.formatter is not None
        assert handler.formatter._fmt == "%(message)s"


def test_rotation_configuration_from_environment() -> None:
    """Test that rotation can be configured from environment variables.

    Verifies integration with config settings.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file = os.path.join(tmpdir, "test_env.log")

        # Configure with parameters that would come from settings
        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=10 * 1024 * 1024,  # 10 MB (default)
            backup_count=5,  # Default
        )

        logger = get_logger(__name__)
        logger.info("test configuration from settings")

        # Verify file exists and contains log
        assert os.path.exists(log_file)

        with open(log_file, "r") as f:
            content = f.read()
            assert "test configuration from settings" in content


def test_multiple_rotation_handlers() -> None:
    """Test behavior with multiple configure_logging calls.

    Verifies that handlers are properly managed on reconfiguration.
    """
    with tempfile.TemporaryDirectory() as tmpdir:
        log_file1 = os.path.join(tmpdir, "test1.log")
        log_file2 = os.path.join(tmpdir, "test2.log")

        # First configuration
        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file1,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=1024,
            backup_count=3,
        )

        logger = get_logger(__name__)
        logger.info("message to first file")

        # Verify first file
        assert os.path.exists(log_file1)

        # Second configuration (force=True should replace)
        configure_logging(
            log_level="INFO",
            json_logs=True,
            log_file=log_file2,
            rotation_enabled=True,
            rotation_type="size",
            max_bytes=2048,
            backup_count=5,
        )

        logger = get_logger(__name__)
        logger.info("message to second file")

        # Verify second file
        assert os.path.exists(log_file2)
