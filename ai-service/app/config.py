"""Configuration settings for LAYA AI Service.

Loads environment variables with sensible defaults for local development.
All configuration values can be overridden via environment variables or .env file.
"""

from typing import Literal

from pydantic_settings import BaseSettings


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

    class Config:
        """Pydantic settings configuration."""

        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False


# Global settings instance
settings = Settings()
