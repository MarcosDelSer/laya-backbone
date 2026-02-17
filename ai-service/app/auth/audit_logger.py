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
        "message_quality_access",
        "message_quality_denied",
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
    resource_type: Optional[str] = None
    action: Optional[str] = None

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

    def log_message_quality_access(
        self,
        action: str,
        current_user: dict[str, Any],
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
        resource_type: str = "message_quality",
    ) -> None:
        """Log message quality resource access.

        Args:
            action: Action performed (analyze, rewrite, history, etc.)
            current_user: Current user from JWT token
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
            resource_type: Type of resource accessed (default: message_quality)
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="message_quality_access",
            user_id=current_user.get("sub"),
            username=current_user.get("username"),
            role=current_user.get("role"),
            source=current_user.get("source", "ai-service"),
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            session_id=current_user.get("session_id"),
            resource_type=resource_type,
            action=action,
        )

        self.logger.info(
            f"Message quality access: {action}",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "message_quality_access",
                "user_id": event.user_id,
                "action": action,
                "resource_type": resource_type,
            },
        )

    def log_message_quality_denied(
        self,
        action: str,
        reason: str,
        current_user: Optional[dict[str, Any]] = None,
        ip_address: Optional[str] = None,
        user_agent: Optional[str] = None,
        endpoint: Optional[str] = None,
    ) -> None:
        """Log denied message quality access attempt.

        Args:
            action: Action attempted (analyze, rewrite, history, etc.)
            reason: Reason for denial
            current_user: Current user from JWT token (if available)
            ip_address: Client IP address
            user_agent: Client user agent
            endpoint: API endpoint being accessed
        """
        event = TokenVerificationEvent(
            timestamp=datetime.utcnow().isoformat(),
            event_type="message_quality_denied",
            user_id=current_user.get("sub") if current_user else None,
            username=current_user.get("username") if current_user else None,
            role=current_user.get("role") if current_user else None,
            source=current_user.get("source") if current_user else None,
            ip_address=ip_address,
            user_agent=user_agent,
            endpoint=endpoint,
            session_id=current_user.get("session_id") if current_user else None,
            error_message=reason,
            action=action,
            resource_type="message_quality",
        )

        self.logger.warning(
            f"Message quality access denied: {action} - {reason}",
            extra={
                "event": event.dict(exclude_none=True),
                "event_type": "message_quality_denied",
                "user_id": event.user_id,
                "action": action,
                "reason": reason,
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
    Robust to None request or missing attributes.

    Args:
        request: FastAPI Request object

    Returns:
        Optional[str]: Client IP address or None
    """
    if request is None:
        return None

    # Check headers in order of preference
    headers_to_check = [
        "cf-connecting-ip",  # Cloudflare
        "x-real-ip",  # Nginx proxy
        "x-forwarded-for",  # Standard proxy
    ]

    if hasattr(request, "headers") and request.headers is not None:
        for header in headers_to_check:
            if value := request.headers.get(header):
                # Handle comma-separated list (X-Forwarded-For)
                if "," in value:
                    return value.split(",")[0].strip()
                return value

    # Fallback to direct client
    # Handle cases where request.client may be None (e.g., test environments, certain proxies)
    if hasattr(request, "client") and request.client is not None:
        return getattr(request.client, "host", None)
    return None


def get_user_agent(request: Any) -> Optional[str]:
    """Extract user agent from request.

    Handles cases where request or headers may be None/unavailable.

    Args:
        request: FastAPI Request object

    Returns:
        Optional[str]: User agent string or None
    """
    if request is None:
        return None
    if not hasattr(request, "headers") or request.headers is None:
        return None
    return request.headers.get("user-agent")


def get_endpoint(request: Any) -> str:
    """Extract endpoint path from request.

    Handles cases where request, method, or url may be None/unavailable.

    Args:
        request: FastAPI Request object

    Returns:
        str: Endpoint path or empty string if unavailable
    """
    if request is None:
        return ""
    method = getattr(request, "method", None) or ""
    url = getattr(request, "url", None)
    path = getattr(url, "path", "") if url is not None else ""
    if method and path:
        return f"{method} {path}"
    return path or method or ""
