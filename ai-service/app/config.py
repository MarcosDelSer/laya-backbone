"""Configuration settings for LAYA AI Service.

Loads environment variables with sensible defaults for local development.
"""

from typing import Literal, Optional

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
        storage_backend: Storage backend type ('local' or 's3')
        s3_bucket_name: S3 bucket name for file storage
        s3_region: AWS region for S3 bucket
        s3_access_key_id: AWS access key ID for S3
        s3_secret_access_key: AWS secret access key for S3
        s3_endpoint_url: Custom S3 endpoint URL (for S3-compatible services)
        local_storage_path: Local filesystem path for file storage
        max_file_size_mb: Maximum file size in megabytes
        allowed_file_types: Comma-separated list of allowed MIME types
        thumbnail_sizes: Comma-separated thumbnail sizes (e.g., '64,128,256')
        storage_quota_mb: Default storage quota per user in megabytes
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

    # Storage configuration
    storage_backend: Literal["local", "s3"] = "local"
    s3_bucket_name: str = ""
    s3_region: str = "us-east-1"
    s3_access_key_id: str = ""
    s3_secret_access_key: str = ""
    s3_endpoint_url: Optional[str] = None
    local_storage_path: str = "./storage"
    max_file_size_mb: int = 50
    allowed_file_types: str = (
        "image/jpeg,image/png,image/gif,image/webp,"
        "application/pdf,"
        "video/mp4,video/webm,"
        "audio/mpeg,audio/wav"
    )
    thumbnail_sizes: str = "64,128,256"
    storage_quota_mb: int = 1024

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
