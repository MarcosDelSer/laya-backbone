"""Configuration settings for LAYA AI Service.

Loads environment variables with sensible defaults for local development.
All configuration values can be overridden via environment variables or .env file.
"""

from functools import lru_cache
from typing import Literal, Optional

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Application settings loaded from environment variables.

    Attributes:
        environment: Application environment (development, staging, production)
        debug: Enable debug mode (should be False in production)
        log_level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
        postgres_host: PostgreSQL database host
        postgres_port: PostgreSQL database port
        postgres_db: PostgreSQL database name
        postgres_user: PostgreSQL database user
        postgres_password: PostgreSQL database password
        jwt_secret_key: Secret key for JWT token signing (MUST change in production)
        jwt_algorithm: Algorithm for JWT token signing
        jwt_access_token_expire_minutes: JWT token expiration in minutes
        csrf_token_expire_minutes: CSRF token expiration in minutes
        cors_origins: Comma-separated list of allowed CORS origins
        rate_limit_storage_uri: Storage backend for rate limiting (memory:// or redis://)
        rate_limit_general: Rate limit for general endpoints (requests per minute)
        rate_limit_auth: Rate limit for auth endpoints (requests per minute)
        enforce_https: Enforce HTTPS in production (redirect HTTP to HTTPS)
    """

    # Application settings
    environment: Literal["development", "staging", "production"] = "development"
    debug: bool = True
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"

    # Database configuration
    postgres_host: str = "localhost"
    postgres_port: int = 5432
    postgres_db: str = "laya_ai"
    postgres_user: str = "laya"
    postgres_password: str = "laya_password"

    # JWT configuration
    jwt_secret_key: str = "your_jwt_secret_key_change_in_production"
    jwt_algorithm: str = "HS256"
    jwt_access_token_expire_minutes: int = 60

    # CSRF configuration
    csrf_token_expire_minutes: int = 60

    # CORS configuration - comma-separated origins for production security
    cors_origins: str = ""

    # Rate limiting configuration
    rate_limit_storage_uri: str = "memory://"
    rate_limit_general: int = 100  # requests per minute
    rate_limit_auth: int = 10  # requests per minute

    # HTTPS configuration
    enforce_https: bool = False  # Set to True in production to enforce HTTPS

    # Redis configuration
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0

    # Logging configuration
    log_level: str = "INFO"
    json_logs: bool = True
    log_file: Optional[str] = None

    # Log rotation configuration
    log_rotation_enabled: bool = True
    log_rotation_type: str = "size"  # "size" or "time"
    log_max_bytes: int = 10 * 1024 * 1024  # 10 MB
    log_backup_count: int = 5
    log_rotation_when: str = "midnight"  # For time-based: "S", "M", "H", "D", "midnight"
    log_rotation_interval: int = 1  # For time-based rotation interval

    @property
    def database_url(self) -> str:
        """Construct the async database URL.

        Returns:
            str: Async PostgreSQL connection URL using asyncpg driver
        """
        return (
            f"postgresql+asyncpg://{self.postgres_user}:{self.postgres_password}"
            f"@{self.postgres_host}:{self.postgres_port}/{self.postgres_db}"
        )

    @property
    def is_production(self) -> bool:
        """Check if running in production environment.

        Returns:
            bool: True if environment is production
        """
        return self.environment == "production"

    @property
    def is_development(self) -> bool:
        """Check if running in development environment.

        Returns:
            bool: True if environment is development
        """
        return self.environment == "development"

    @property
    def cors_origins_list(self) -> list[str]:
        """Parse CORS origins string into a list.

        Returns:
            list[str]: List of allowed CORS origins, empty list if not configured
        """
        if not self.cors_origins:
            return []
        return [origin.strip() for origin in self.cors_origins.split(",") if origin.strip()]

    @property
    def redis_url(self) -> str:
        """Construct Redis connection URL.

        Returns:
            str: Redis connection URL
        """
        return f"redis://{self.redis_host}:{self.redis_port}/{self.redis_db}"

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )


@lru_cache()
def get_settings() -> Settings:
    """Get cached settings instance.

    Uses lru_cache to ensure settings are loaded only once,
    avoiding repeated .env file reads.

    Returns:
        Settings: Cached settings instance
    """
    return Settings()


# Global settings instance for backward compatibility
settings = get_settings()
