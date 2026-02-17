"""Configuration settings for LAYA AI Service.

Loads environment variables with sensible defaults for local development.
"""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings loaded from environment variables.

    Attributes:
        postgres_host: PostgreSQL database host
        postgres_port: PostgreSQL database port
        postgres_db: PostgreSQL database name
        postgres_user: PostgreSQL database user
        postgres_password: PostgreSQL database password
        jwt_secret_key: Secret key for JWT token signing
        jwt_algorithm: Algorithm for JWT token signing
        cors_origins: Comma-separated list of allowed CORS origins
    """

    # Database configuration
    postgres_host: str = "localhost"
    postgres_port: int = 5432
    postgres_db: str = "laya_ai"
    postgres_user: str = "laya"
    postgres_password: str = "laya_password"

    # JWT configuration
    jwt_secret_key: str = "your_jwt_secret_key_change_in_production"
    jwt_algorithm: str = "HS256"

    # CORS configuration - comma-separated origins for production security
    cors_origins: str = ""

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

    class Config:
        """Pydantic settings configuration."""

        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False


# Global settings instance
settings = Settings()
