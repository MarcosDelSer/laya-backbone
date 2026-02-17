"""SQLAlchemy models for LLM integration.

Defines database models for LLM usage tracking and response caching.
These models support monitoring, cost tracking, and intelligent caching
of LLM completions across different providers.
"""

from datetime import datetime
from typing import Optional
from uuid import uuid4

from sqlalchemy import Boolean, DateTime, Float, Index, Integer, String, Text
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column

from app.models.base import Base


class LLMUsageLog(Base):
    """Track LLM API usage for monitoring and cost analysis.

    Stores detailed information about each LLM completion request,
    including token usage, costs, latency, and success/failure status.
    This data supports usage analytics, cost tracking, and debugging.

    Attributes:
        id: Unique identifier for the usage log entry
        user_id: ID of the user who made the request (optional)
        session_id: ID of the session/conversation this request belongs to
        provider: LLM provider name (openai, anthropic, etc.)
        model: The model used for the completion
        prompt_tokens: Number of tokens in the prompt
        completion_tokens: Number of tokens in the completion
        total_tokens: Total tokens used (prompt + completion)
        cost_usd: Estimated cost in USD (optional)
        request_type: Type of request (completion, chat, etc.)
        success: Whether the request succeeded
        error_message: Error message if the request failed
        latency_ms: Response time in milliseconds
        cached: Whether the response was served from cache
        created_at: Timestamp when the request was made
    """

    __tablename__ = "llm_usage_logs"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    user_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    session_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    provider: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        index=True,
    )
    model: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    prompt_tokens: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    completion_tokens: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    total_tokens: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    cost_usd: Mapped[Optional[float]] = mapped_column(
        Float,
        nullable=True,
    )
    request_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="completion",
    )
    success: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    error_message: Mapped[Optional[str]] = mapped_column(
        Text,
        nullable=True,
    )
    latency_ms: Mapped[Optional[int]] = mapped_column(
        Integer,
        nullable=True,
    )
    cached: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_llm_usage_logs_provider_model", "provider", "model"),
        Index("ix_llm_usage_logs_user_created", "user_id", "created_at"),
        Index("ix_llm_usage_logs_created_at", "created_at"),
        Index("ix_llm_usage_logs_success", "success"),
    )


class LLMCacheEntry(Base):
    """Cache LLM responses for improved performance and cost reduction.

    Stores cached LLM responses with their associated prompts and metadata.
    Supports TTL-based expiration and tracks cache hit statistics for
    monitoring cache effectiveness.

    Attributes:
        id: Unique identifier for the cache entry
        cache_key: Unique hash of the prompt/messages for lookup
        provider: LLM provider name (openai, anthropic, etc.)
        model: The model used for the completion
        prompt_hash: Hash of the prompt messages for verification
        response_content: The cached response content
        prompt_tokens: Number of tokens in the original prompt
        completion_tokens: Number of tokens in the cached response
        hit_count: Number of times this cache entry was used
        expires_at: Timestamp when the cache entry expires
        created_at: Timestamp when the cache entry was created
        last_accessed_at: Timestamp when the cache was last accessed
    """

    __tablename__ = "llm_cache_entries"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    cache_key: Mapped[str] = mapped_column(
        String(64),
        nullable=False,
        unique=True,
        index=True,
    )
    provider: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        index=True,
    )
    model: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
        index=True,
    )
    prompt_hash: Mapped[str] = mapped_column(
        String(64),
        nullable=False,
    )
    response_content: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    prompt_tokens: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    completion_tokens: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    hit_count: Mapped[int] = mapped_column(
        Integer,
        nullable=False,
        default=0,
    )
    expires_at: Mapped[datetime] = mapped_column(
        DateTime,
        nullable=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    last_accessed_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_llm_cache_entries_provider_model", "provider", "model"),
        Index("ix_llm_cache_entries_expires_at", "expires_at"),
        Index("ix_llm_cache_entries_created_at", "created_at"),
    )
