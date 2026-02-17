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
        redis_host: Redis server host
        redis_port: Redis server port
        redis_db: Redis database number
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
    jwt_audience: str = "laya-ai-service"
    jwt_issuer: str = "laya-ai-service"

    # Redis configuration
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0

    # Database connection pool configuration
    db_pool_size: int = 10
    db_max_overflow: int = 20
    db_pool_timeout: int = 30
    db_pool_recycle: int = 3600
    db_pool_pre_ping: bool = True
    db_echo: bool = False

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
    def redis_url(self) -> str:
        """Construct the Redis connection URL.

        Returns:
            str: Redis connection URL
        """
        return f"redis://{self.redis_host}:{self.redis_port}/{self.redis_db}"

    class Config:
        """Pydantic settings configuration."""

        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False


# Global settings instance
settings = Settings()
