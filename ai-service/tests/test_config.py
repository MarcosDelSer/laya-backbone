"""Unit tests for configuration settings and caching.

Tests for settings caching with lru_cache, environment variable loading,
property methods, and settings instance consistency.
"""

from __future__ import annotations

import os
from functools import lru_cache
from unittest.mock import patch

import pytest

from app.config import Settings, get_settings


class TestSettingsCaching:
    """Test settings caching behavior with lru_cache decorator."""

    def test_get_settings_returns_cached_instance(self) -> None:
        """Test that multiple calls to get_settings return the same instance.

        The @lru_cache decorator should ensure settings are loaded only once,
        avoiding repeated .env file reads and improving performance.
        """
        # Clear the cache before testing
        get_settings.cache_clear()

        # Get settings multiple times
        settings_1 = get_settings()
        settings_2 = get_settings()
        settings_3 = get_settings()

        # All calls should return the exact same instance (not just equal values)
        assert settings_1 is settings_2
        assert settings_2 is settings_3
        assert settings_1 is settings_3

    def test_get_settings_cache_info(self) -> None:
        """Test that lru_cache tracks cache hits correctly."""
        # Clear the cache before testing
        get_settings.cache_clear()

        # Check initial cache state
        cache_info_initial = get_settings.cache_info()
        assert cache_info_initial.hits == 0
        assert cache_info_initial.misses == 0
        assert cache_info_initial.currsize == 0

        # First call should be a cache miss
        get_settings()
        cache_info_first = get_settings.cache_info()
        assert cache_info_first.hits == 0
        assert cache_info_first.misses == 1
        assert cache_info_first.currsize == 1

        # Second call should be a cache hit
        get_settings()
        cache_info_second = get_settings.cache_info()
        assert cache_info_second.hits == 1
        assert cache_info_second.misses == 1
        assert cache_info_second.currsize == 1

        # Third call should also be a cache hit
        get_settings()
        cache_info_third = get_settings.cache_info()
        assert cache_info_third.hits == 2
        assert cache_info_third.misses == 1
        assert cache_info_third.currsize == 1

    def test_cache_clear_creates_new_instance(self) -> None:
        """Test that clearing the cache creates a new settings instance."""
        # Clear the cache before testing
        get_settings.cache_clear()

        # Get first instance
        settings_1 = get_settings()

        # Clear cache
        get_settings.cache_clear()

        # Get second instance after clearing cache
        settings_2 = get_settings()

        # Should be different instances after cache clear
        assert settings_1 is not settings_2

    def test_direct_settings_instantiation_bypasses_cache(self) -> None:
        """Test that creating Settings() directly bypasses the cache."""
        # Clear the cache before testing
        get_settings.cache_clear()

        # Get cached instance
        cached_settings = get_settings()

        # Create new instance directly
        direct_settings = Settings()

        # Should be different instances
        assert cached_settings is not direct_settings

    def test_cached_settings_persist_across_module_imports(self) -> None:
        """Test that cached settings are consistent across imports."""
        # Clear the cache before testing
        get_settings.cache_clear()

        # Import and get settings
        from app.config import get_settings as imported_get_settings

        settings_1 = get_settings()
        settings_2 = imported_get_settings()

        # Should be the same instance
        assert settings_1 is settings_2


class TestSettingsProperties:
    """Test settings property methods and computed values."""

    def test_database_url_construction(self) -> None:
        """Test that database URL is properly constructed from settings."""
        with patch.dict(
            os.environ,
            {
                "POSTGRES_HOST": "test-host",
                "POSTGRES_PORT": "5433",
                "POSTGRES_DB": "test-db",
                "POSTGRES_USER": "test-user",
                "POSTGRES_PASSWORD": "test-pass",
            },
        ):
            settings = Settings()
            expected_url = (
                "postgresql+asyncpg://test-user:test-pass"
                "@test-host:5433/test-db"
            )
            assert settings.database_url == expected_url

    def test_database_url_default_values(self) -> None:
        """Test database URL with default values."""
        with patch.dict(os.environ, {}, clear=True):
            settings = Settings()
            expected_url = (
                "postgresql+asyncpg://laya:laya_password"
                "@localhost:5432/laya_ai"
            )
            assert settings.database_url == expected_url

    def test_redis_url_construction(self) -> None:
        """Test that Redis URL is properly constructed from settings."""
        with patch.dict(
            os.environ,
            {
                "REDIS_HOST": "test-redis",
                "REDIS_PORT": "6380",
                "REDIS_DB": "2",
            },
        ):
            settings = Settings()
            expected_url = "redis://test-redis:6380/2"
            assert settings.redis_url == expected_url

    def test_redis_url_default_values(self) -> None:
        """Test Redis URL with default values."""
        with patch.dict(os.environ, {}, clear=True):
            settings = Settings()
            expected_url = "redis://localhost:6379/0"
            assert settings.redis_url == expected_url

    def test_is_production_true(self) -> None:
        """Test is_production returns True when environment is production."""
        with patch.dict(os.environ, {"ENVIRONMENT": "production"}):
            settings = Settings()
            assert settings.is_production is True
            assert settings.is_development is False

    def test_is_production_false(self) -> None:
        """Test is_production returns False when environment is not production."""
        with patch.dict(os.environ, {"ENVIRONMENT": "development"}):
            settings = Settings()
            assert settings.is_production is False
            assert settings.is_development is True

    def test_is_development_staging(self) -> None:
        """Test is_development returns False for staging environment."""
        with patch.dict(os.environ, {"ENVIRONMENT": "staging"}):
            settings = Settings()
            assert settings.is_development is False
            assert settings.is_production is False

    def test_cors_origins_list_empty(self) -> None:
        """Test CORS origins list when no origins are configured."""
        with patch.dict(os.environ, {"CORS_ORIGINS": ""}):
            settings = Settings()
            assert settings.cors_origins_list == []

    def test_cors_origins_list_single_origin(self) -> None:
        """Test CORS origins list with a single origin."""
        with patch.dict(os.environ, {"CORS_ORIGINS": "https://example.com"}):
            settings = Settings()
            assert settings.cors_origins_list == ["https://example.com"]

    def test_cors_origins_list_multiple_origins(self) -> None:
        """Test CORS origins list with multiple comma-separated origins."""
        origins = "https://example.com,https://api.example.com,https://admin.example.com"
        with patch.dict(os.environ, {"CORS_ORIGINS": origins}):
            settings = Settings()
            expected = [
                "https://example.com",
                "https://api.example.com",
                "https://admin.example.com",
            ]
            assert settings.cors_origins_list == expected

    def test_cors_origins_list_with_whitespace(self) -> None:
        """Test CORS origins list handles whitespace correctly."""
        origins = "https://example.com , https://api.example.com ,  https://admin.example.com  "
        with patch.dict(os.environ, {"CORS_ORIGINS": origins}):
            settings = Settings()
            expected = [
                "https://example.com",
                "https://api.example.com",
                "https://admin.example.com",
            ]
            assert settings.cors_origins_list == expected

    def test_cors_origins_list_filters_empty_strings(self) -> None:
        """Test CORS origins list filters out empty strings from trailing commas."""
        origins = "https://example.com,,"
        with patch.dict(os.environ, {"CORS_ORIGINS": origins}):
            settings = Settings()
            assert settings.cors_origins_list == ["https://example.com"]


class TestSettingsEnvironmentLoading:
    """Test settings loading from environment variables."""

    def test_settings_loads_from_environment(self) -> None:
        """Test that settings correctly load from environment variables."""
        with patch.dict(
            os.environ,
            {
                "ENVIRONMENT": "staging",
                "DEBUG": "false",
                "LOG_LEVEL": "WARNING",
                "JWT_SECRET_KEY": "test-secret-key",
                "JWT_ALGORITHM": "HS512",
                "JWT_ACCESS_TOKEN_EXPIRE_MINUTES": "120",
            },
        ):
            settings = Settings()
            assert settings.environment == "staging"
            assert settings.debug is False
            assert settings.log_level == "WARNING"
            assert settings.jwt_secret_key == "test-secret-key"
            assert settings.jwt_algorithm == "HS512"
            assert settings.jwt_access_token_expire_minutes == 120

    def test_settings_uses_defaults_when_env_not_set(self) -> None:
        """Test that settings use default values when environment variables are not set."""
        with patch.dict(os.environ, {}, clear=True):
            settings = Settings()
            assert settings.environment == "development"
            assert settings.debug is True
            assert settings.log_level == "INFO"
            assert settings.postgres_host == "localhost"
            assert settings.postgres_port == 5432
            assert settings.rate_limit_general == 100
            assert settings.rate_limit_auth == 10

    def test_settings_case_insensitive_env_vars(self) -> None:
        """Test that settings support case-insensitive environment variables."""
        with patch.dict(
            os.environ,
            {
                "environment": "production",  # lowercase
                "POSTGRES_HOST": "db-host",  # uppercase
                "Redis_Host": "cache-host",  # mixed case
            },
        ):
            settings = Settings()
            # Pydantic BaseSettings should handle case-insensitive loading
            assert settings.environment == "production"
            assert settings.postgres_host == "db-host"
            # Note: Redis_Host might not work due to case sensitivity in some scenarios

    def test_settings_rate_limit_configuration(self) -> None:
        """Test rate limit configuration settings."""
        with patch.dict(
            os.environ,
            {
                "RATE_LIMIT_STORAGE_URI": "redis://localhost:6379",
                "RATE_LIMIT_GENERAL": "200",
                "RATE_LIMIT_AUTH": "5",
            },
        ):
            settings = Settings()
            assert settings.rate_limit_storage_uri == "redis://localhost:6379"
            assert settings.rate_limit_general == 200
            assert settings.rate_limit_auth == 5

    def test_settings_https_enforcement(self) -> None:
        """Test HTTPS enforcement configuration."""
        with patch.dict(os.environ, {"ENFORCE_HTTPS": "true"}):
            settings = Settings()
            assert settings.enforce_https is True

        with patch.dict(os.environ, {"ENFORCE_HTTPS": "false"}):
            settings = Settings()
            assert settings.enforce_https is False

    def test_settings_logging_configuration(self) -> None:
        """Test logging configuration settings."""
        with patch.dict(
            os.environ,
            {
                "LOG_LEVEL": "DEBUG",
                "JSON_LOGS": "false",
                "LOG_FILE": "/var/log/app.log",
                "LOG_ROTATION_ENABLED": "true",
                "LOG_ROTATION_TYPE": "time",
                "LOG_MAX_BYTES": "20971520",  # 20 MB
                "LOG_BACKUP_COUNT": "10",
            },
        ):
            settings = Settings()
            assert settings.log_level == "DEBUG"
            assert settings.json_logs is False
            assert settings.log_file == "/var/log/app.log"
            assert settings.log_rotation_enabled is True
            assert settings.log_rotation_type == "time"
            assert settings.log_max_bytes == 20971520
            assert settings.log_backup_count == 10


class TestSettingsCachingPerformance:
    """Test that settings caching improves performance."""

    def test_cached_settings_no_repeated_env_parsing(self) -> None:
        """Test that cached settings avoid repeated environment parsing.

        The lru_cache ensures that Settings() is only instantiated once,
        which means environment variables and .env files are only parsed once.
        """
        # Clear the cache before testing
        get_settings.cache_clear()

        with patch.dict(
            os.environ,
            {"ENVIRONMENT": "production", "DEBUG": "false"},
        ):
            # First call - should parse environment
            settings_1 = get_settings()
            assert settings_1.environment == "production"

            # Change environment variables
            os.environ["ENVIRONMENT"] = "development"
            os.environ["DEBUG"] = "true"

            # Second call - should return cached instance (unchanged)
            settings_2 = get_settings()

            # Cached settings should not reflect the change
            assert settings_2 is settings_1
            assert settings_2.environment == "production"  # Still the original value
            assert settings_2.debug is False  # Still the original value

    def test_cache_clear_picks_up_new_environment_values(self) -> None:
        """Test that clearing cache allows settings to pick up new environment values."""
        # Clear the cache before testing
        get_settings.cache_clear()

        with patch.dict(os.environ, {"ENVIRONMENT": "development"}):
            settings_1 = get_settings()
            assert settings_1.environment == "development"

            # Clear cache
            get_settings.cache_clear()

            # Change environment
            os.environ["ENVIRONMENT"] = "production"

            # Get new settings - should reflect the change
            settings_2 = get_settings()
            assert settings_2.environment == "production"
            assert settings_2 is not settings_1
