"""Token verification audit logging for AI Service.

This module provides audit logging for JWT token verification events
in the AI service. It complements the Gibbon-side logging by tracking
token usage and validation on the AI service side.

This enables:
- Cross-service audit trail correlation
- Token usage analytics
- Security monitoring
- Compliance reporting
"""

from __future__ import annotations

import logging
from datetime import datetime
from typing import Any, Literal, Optional

from pydantic import BaseModel

# Configure structured logging
logger = logging.getLogger("ai_service.auth.audit")


class TokenVerificationEvent(BaseModel):
    """Represents a token verification event for audit logging.

    Attributes:
        timestamp: Event timestamp (ISO format)
        event_type: Type of event (verify_success, verify_failed, etc.)
        user_id: User identifier from token (sub claim)
        username: Username from token
        role: User role from token
        source: Token source (ai-service or gibbon)
        ip_address: Client IP address
        user_agent: Client user agent
        endpoint: API endpoint being accessed
        session_id: Session ID (for Gibbon tokens)
        error_message: Error details if verification failed
        token_expired: Whether token was expired
        token_claims: Additional token claims for analysis
    """

    timestamp: str
    event_type: Literal[
        "verify_success",
        "verify_failed",
        "token_expired",
        "token_invalid",
        "missing_claims",
    ]
    user_id: Optional[str] = None
    username: Optional[str] = None
    role: Optional[str] = None
    source: Optional[str] = None
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None
    endpoint: Optional[str] = None
    session_id: Optional[str] = None
    error_message: Optional[str] = None
    token_expired: bool = False
    token_claims: Optional[dict[str, Any]] = None

    class Config:
        """Pydantic config."""

        json_encoders = {datetime: lambda v: v.isoformat()}


class TokenAuditLogger:
    """Audit logger for token verification events.

    This class provides structured logging for authentication and
    authorization events in the AI service.
    """

    def __init__(self, logger_name: str = "ai_service.auth.audit") -> None:
        """Initialize the audit logger.

        Args:
            logger_name: Logger name for configuration
        """
        self.logger = logging.getLogger(logger_name)

    def log_verification_success(
        self,
        token_payload: dict[str, Any],
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
    ) -> None:
        """Log successful token verification.

        Args:
            token_payload: Decoded token payload
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="verify_success",
            user_id=token_payload.get("sub"),
            username=token_payload.get("username"),
            role=token_payload.get("role"),
            source=token_payload.get("source", "ai-service"),
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            session_id=token_payload.get("session_id"),
            token_claims=self._extract_relevant_claims(token_payload),
        )

        self.logger.info(
            "Token verification successful",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "verify_success",
                "user_id": event.user_id,
                "source": event.source,
            },
        )

    def log_verification_failed(
        self,
        error_message: str,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
        token_payload: Optional[dict[str, Any]] = None,
    ) -> None:
        """Log failed token verification.

        Args:
            error_message: Error description
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
            token_payload: Partial token payload (if available)
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="verify_failed",
            user_id=token_payload.get("sub") if token_payload else None,
            username=token_payload.get("username") if token_payload else None,
            source=token_payload.get("source") if token_payload else None,
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            error_message=error_message,
        )

        self.logger.warning(
            f"Token verification failed: {error_message}",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "verify_failed",
                "ip_address": ip_address,
            },
        )

    def log_token_expired(
        self,
        token_payload: dict[str, Any],
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
    ) -> None:
        """Log expired token attempt.

        Args:
            token_payload: Decoded (but expired) token payload
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="token_expired",
            user_id=token_payload.get("sub"),
            username=token_payload.get("username"),
            role=token_payload.get("role"),
            source=token_payload.get("source", "ai-service"),
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            session_id=token_payload.get("session_id"),
            error_message="Token has expired",
            token_expired=True,
            token_claims=self._extract_relevant_claims(token_payload),
        )

        self.logger.warning(
            "Expired token attempt",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "token_expired",
                "user_id": event.user_id,
                "source": event.source,
            },
        )

    def log_invalid_token(
        self,
        error_message: str,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
    ) -> None:
        """Log invalid token attempt.

        Args:
            error_message: Error description
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="token_invalid",
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            error_message=error_message,
        )

        self.logger.warning(
            f"Invalid token: {error_message}",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "token_invalid",
                "ip_address": ip_address,
            },
        )

    def log_missing_claims(
        self,
        missing_claims: list[str],
        token_payload: dict[str, Any],
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
    ) -> None:
        """Log token with missing required claims.

        Args:
            missing_claims: List of missing claim names
            token_payload: Token payload
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="missing_claims",
            user_id=token_payload.get("sub"),
            source=token_payload.get("source"),
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            error_message=f"Missing required claims: {', '.join(missing_claims)}",
            token_claims=self._extract_relevant_claims(token_payload),
        )

        self.logger.warning(
            f"Token missing required claims: {missing_claims}",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "missing_claims",
                "missing_claims": missing_claims,
            },
        )

    def _extract_relevant_claims(
        self, token_payload: dict[str, Any]
    ) -> dict[str, Any]:
        """Extract relevant claims for audit logging.

        Args:
            token_payload: Full token payload

        Returns:
            dict[str, Any]: Subset of claims relevant for auditing
        """
        relevant_keys = [
            "iat",
            "exp",
            "source",
            "gibbon_role_id",
            "session_id",
            "email",
        ]

        return {
            key: token_payload.get(key)
            for key in relevant_keys
            if key in token_payload
        }


# Global audit logger instance
audit_logger = TokenAuditLogger()


def get_client_ip(request: Any) -> Optional[str]:
    """Extract client IP address from request.

    Handles various proxy headers in order of reliability.

    Args:
        request: FastAPI Request object

    Returns:
        Optional[str]: Client IP address or None
    """
    # Check headers in order of preference
    headers_to_check = [
        "cf-connecting-ip",  # Cloudflare
        "x-real-ip",  # Nginx proxy
        "x-forwarded-for",  # Standard proxy
    ]

    for header in headers_to_check:
        if value := request.headers.get(header):
            # Handle comma-separated list (X-Forwarded-For)
            if "," in value:
                return value.split(",")[0].strip()
            return value

    # Fallback to direct client
    return request.client.host if hasattr(request, "client") else None


def get_user_agent(request: Any) -> Optional[str]:
    """Extract user agent from request.

    Args:
        request: FastAPI Request object

    Returns:
        Optional[str]: User agent string or None
    """
    return request.headers.get("user-agent")


def get_endpoint(request: Any) -> str:
    """Extract endpoint path from request.

    Args:
        request: FastAPI Request object

    Returns:
        str: Endpoint path
    """
    return f"{request.method} {request.url.path}" if hasattr(request, "url") else ""
