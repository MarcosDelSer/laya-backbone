"""Structured JSON logging configuration for LAYA AI Service.

This module provides structured JSON logging with request ID correlation,
log levels, and both development and production configurations.
"""

import logging
import sys
from typing import Any, Optional

import structlog
from structlog.types import EventDict, Processor


def add_request_id(
    logger: logging.Logger, method_name: str, event_dict: EventDict
) -> EventDict:
    """Add request ID to log event if available in context.

    Args:
        logger: The logger instance
        method_name: The logging method name
        event_dict: The event dictionary to process

    Returns:
        EventDict: Updated event dictionary with request_id if available
    """
    # Request ID will be bound to logger context by middleware
    # This is just a placeholder to ensure the field exists
    if "request_id" not in event_dict:
        event_dict["request_id"] = None
    return event_dict


def add_log_level(
    logger: logging.Logger, method_name: str, event_dict: EventDict
) -> EventDict:
    """Add log level to event dictionary.

    Args:
        logger: The logger instance
        method_name: The logging method name
        event_dict: The event dictionary to process

    Returns:
        EventDict: Updated event dictionary with log level
    """
    if method_name == "warn":
        # Normalize 'warn' to 'warning'
        event_dict["level"] = "warning"
    else:
        event_dict["level"] = method_name
    return event_dict


def configure_logging(
    log_level: str = "INFO",
    json_logs: bool = True,
    log_file: Optional[str] = None,
    stream: Optional[Any] = None,
) -> None:
    """Configure structured logging for the application.

    This sets up structlog with appropriate processors for either
    JSON output (production) or human-readable output (development).

    Args:
        log_level: The minimum log level to capture (DEBUG/INFO/WARNING/ERROR/CRITICAL)
        json_logs: Whether to output logs in JSON format (True for production)
        log_file: Optional path to log file for file-based logging
        stream: Optional stream to write logs to (defaults to sys.stdout)
    """
    # Convert log level string to logging constant
    log_level_value = getattr(logging, log_level.upper(), logging.INFO)

    # Use provided stream or default to stdout
    output_stream = stream if stream is not None else sys.stdout

    # Configure standard library logging
    logging.basicConfig(
        format="%(message)s",
        stream=output_stream,
        level=log_level_value,
        force=True,  # Force reconfiguration
    )

    # Define processors for structlog
    processors: list[Processor] = [
        # Add log level
        structlog.stdlib.add_log_level,
        # Add logger name
        structlog.stdlib.add_logger_name,
        # Add timestamp
        structlog.processors.TimeStamper(fmt="iso"),
        # Add stack info for exceptions
        structlog.processors.StackInfoRenderer(),
        # Format exceptions
        structlog.processors.format_exc_info,
        # Add request ID if available
        add_request_id,
        # Add custom log level processor
        add_log_level,
    ]

    if json_logs:
        # Production: JSON output
        processors.append(structlog.processors.JSONRenderer())
    else:
        # Development: Human-readable output with colors
        processors.extend([
            structlog.dev.ConsoleRenderer(colors=True),
        ])

    # Configure structlog
    structlog.configure(
        processors=processors,
        wrapper_class=structlog.stdlib.BoundLogger,
        context_class=dict,
        logger_factory=structlog.stdlib.LoggerFactory(),
        cache_logger_on_first_use=True,
    )

    # Add file handler if log_file is specified
    if log_file:
        file_handler = logging.FileHandler(log_file)
        file_handler.setLevel(log_level_value)
        file_handler.setFormatter(
            logging.Formatter("%(message)s")
        )
        logging.root.addHandler(file_handler)


def get_logger(name: Optional[str] = None, **initial_values: Any) -> structlog.BoundLogger:
    """Get a structured logger instance.

    Args:
        name: Optional logger name (typically __name__ of the calling module)
        **initial_values: Initial context values to bind to the logger

    Returns:
        structlog.BoundLogger: A bound logger instance with initial context

    Example:
        >>> logger = get_logger(__name__, service="coaching")
        >>> logger.info("Processing request", child_id=child_id)
    """
    if name:
        logger = structlog.get_logger(name)
    else:
        logger = structlog.get_logger()

    # Bind initial values if provided
    if initial_values:
        logger = logger.bind(**initial_values)

    return logger


def bind_request_id(logger: structlog.BoundLogger, request_id: str) -> structlog.BoundLogger:
    """Bind a request ID to a logger for correlation.

    Args:
        logger: The logger instance to bind to
        request_id: The request ID to bind

    Returns:
        structlog.BoundLogger: Logger with bound request_id

    Example:
        >>> logger = get_logger(__name__)
        >>> logger = bind_request_id(logger, request.state.request_id)
        >>> logger.info("Processing request")  # Will include request_id
    """
    return logger.bind(request_id=request_id)
