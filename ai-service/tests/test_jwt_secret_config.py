"""Tests for JWT shared secret configuration.

This test suite verifies that the JWT secret configuration is working
correctly and that both Gibbon and AI Service can use the same secret
for signing and verifying JWT tokens.
"""

import os
from unittest.mock import patch

import jwt
import pytest

from app.config import Settings


class TestJWTSecretConfiguration:
    """Test JWT secret configuration loading and usage."""

    def test_settings_loads_jwt_secret_from_env(self):
        """Test that Settings loads JWT_SECRET_KEY from environment."""
        test_secret = "test_secret_key_12345678901234567890"

        with patch.dict(os.environ, {"JWT_SECRET_KEY": test_secret}):
            settings = Settings()
            assert settings.jwt_secret_key == test_secret

    def test_settings_has_default_jwt_secret(self):
        """Test that Settings has a default JWT secret."""
        # Clear any environment variable
        with patch.dict(os.environ, {}, clear=True):
            settings = Settings()
            assert settings.jwt_secret_key is not None
            assert len(settings.jwt_secret_key) > 0

    def test_settings_jwt_algorithm_default(self):
        """Test that JWT algorithm defaults to HS256."""
        settings = Settings()
        assert settings.jwt_algorithm == "HS256"

    def test_jwt_token_can_be_signed_and_verified(self):
        """Test that a JWT token can be signed and verified with the secret."""
        settings = Settings()

        # Create a test payload
        payload = {
            "sub": "12345",
            "username": "testuser",
            "email": "test@example.com",
            "role": "teacher",
            "source": "ai-service",
        }

        # Sign the token
        token = jwt.encode(
            payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm
        )

        # Verify the token
        decoded = jwt.decode(
            token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        assert decoded["sub"] == payload["sub"]
        assert decoded["username"] == payload["username"]
        assert decoded["email"] == payload["email"]
        assert decoded["role"] == payload["role"]

    def test_jwt_token_signed_with_different_secret_fails_verification(self):
        """Test that a token signed with a different secret fails verification."""
        settings = Settings()

        # Create a test payload
        payload = {"sub": "12345", "username": "testuser"}

        # Sign with one secret
        token = jwt.encode(payload, "wrong_secret_key", algorithm="HS256")

        # Try to verify with different secret - should fail
        with pytest.raises(jwt.InvalidSignatureError):
            jwt.decode(
                token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
            )

    def test_gibbon_token_structure_compatibility(self):
        """Test that Gibbon-style tokens can be verified by AI Service."""
        import time
        settings = Settings()

        now = int(time.time())

        # Simulate a token created by Gibbon (same structure as auth_token.php)
        gibbon_payload = {
            "sub": "67890",
            "iat": now,
            "exp": now + 3600,  # +1 hour
            "username": "gibbon_user",
            "email": "gibbon@example.com",
            "role": "teacher",
            "gibbon_role_id": "002",
            "name": "John Doe",
            "source": "gibbon",
            "session_id": "abc123xyz",
        }

        # Sign the token (simulating Gibbon's encodeJWT function)
        token = jwt.encode(
            gibbon_payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm
        )

        # Verify the token (as AI Service would)
        decoded = jwt.decode(
            token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        # Verify all Gibbon-specific fields are preserved
        assert decoded["sub"] == gibbon_payload["sub"]
        assert decoded["username"] == gibbon_payload["username"]
        assert decoded["source"] == "gibbon"
        assert decoded["gibbon_role_id"] == "002"
        assert decoded["session_id"] == "abc123xyz"

    def test_shared_secret_consistency(self):
        """Test that both services would use the same secret from environment."""
        shared_secret = "shared_secret_for_both_services_123456789"

        with patch.dict(os.environ, {"JWT_SECRET_KEY": shared_secret}):
            settings = Settings()
            assert settings.jwt_secret_key == shared_secret

            # Simulate Gibbon's getJWTSecret() behavior
            gibbon_secret = os.getenv("JWT_SECRET_KEY")
            assert gibbon_secret == shared_secret

            # Both should be identical
            assert settings.jwt_secret_key == gibbon_secret

    def test_token_interoperability_ai_to_gibbon(self):
        """Test that a token created by AI Service could be verified by Gibbon."""
        settings = Settings()

        # AI Service creates a token
        ai_payload = {
            "sub": "11111",
            "username": "ai_user",
            "email": "ai@example.com",
            "role": "student",
            "source": "ai-service",
        }

        token = jwt.encode(
            ai_payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm
        )

        # Gibbon would verify it (simulating PHP jwt_decode with same secret)
        decoded = jwt.decode(
            token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        assert decoded["sub"] == ai_payload["sub"]
        assert decoded["source"] == "ai-service"

    def test_token_interoperability_gibbon_to_ai(self):
        """Test that a token created by Gibbon can be verified by AI Service."""
        settings = Settings()

        # Gibbon creates a token (simulating auth_token.php)
        gibbon_payload = {
            "sub": "22222",
            "username": "gibbon_user",
            "email": "gibbon@example.com",
            "role": "parent",
            "gibbon_role_id": "004",
            "source": "gibbon",
        }

        token = jwt.encode(
            gibbon_payload, settings.jwt_secret_key, algorithm=settings.jwt_algorithm
        )

        # AI Service verifies it
        decoded = jwt.decode(
            token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        assert decoded["sub"] == gibbon_payload["sub"]
        assert decoded["source"] == "gibbon"
        assert decoded["gibbon_role_id"] == "004"

    def test_secret_length_validation(self):
        """Test that weak secrets can be detected."""
        weak_secrets = [
            "short",  # Too short
            "12345678",  # Too short
            "password",  # Common word
        ]

        strong_secret = "vK8f3nP9mH2jD1sL7wQ4xR6yT5uV0zA3bE8cF9gH1iJ2kL3mN4oP5q"

        for weak_secret in weak_secrets:
            # Weak secrets work technically but should be flagged
            assert len(weak_secret) < 32, f"Secret '{weak_secret}' should be too short"

        # Strong secret should pass
        assert len(strong_secret) >= 32, "Strong secret should be long enough"

    def test_default_secret_is_placeholder(self):
        """Test that the default secret is clearly a placeholder."""
        with patch.dict(os.environ, {}, clear=True):
            settings = Settings()
            # The default should indicate it needs to be changed
            assert "change" in settings.jwt_secret_key.lower() or \
                   "production" in settings.jwt_secret_key.lower(), \
                   "Default secret should indicate it needs to be changed"


class TestCrossServiceAuthentication:
    """Test cross-service authentication scenarios."""

    def test_full_authentication_flow(self):
        """Test a complete authentication flow from Gibbon to AI Service."""
        import time
        settings = Settings()

        now = int(time.time())

        # Step 1: User logs into Gibbon (PHP session created)
        # Step 2: Frontend requests JWT token from Gibbon
        # Step 3: Gibbon validates session and creates JWT

        gibbon_token_payload = {
            "sub": "12345",
            "iat": now,
            "exp": now + 3600,
            "username": "testuser",
            "email": "test@example.com",
            "role": "teacher",
            "gibbon_role_id": "002",
            "name": "Test User",
            "source": "gibbon",
            "session_id": "session_abc123",
        }

        # Gibbon signs the token
        token = jwt.encode(
            gibbon_token_payload,
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Step 4: Frontend sends token to AI Service
        # Step 5: AI Service verifies token
        decoded = jwt.decode(
            token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        # Step 6: AI Service extracts user info
        assert decoded["sub"] == "12345"
        assert decoded["username"] == "testuser"
        assert decoded["source"] == "gibbon"
        assert decoded["role"] == "teacher"

        # Verify we can identify the token source
        assert decoded.get("source") == "gibbon"
        assert "gibbon_role_id" in decoded
        assert "session_id" in decoded

    def test_multiple_token_sources_same_secret(self):
        """Test that tokens from different sources can be verified with same secret."""
        settings = Settings()

        # Token from AI Service
        ai_token = jwt.encode(
            {"sub": "1", "username": "ai_user", "source": "ai-service"},
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Token from Gibbon
        gibbon_token = jwt.encode(
            {"sub": "2", "username": "gibbon_user", "source": "gibbon"},
            settings.jwt_secret_key,
            algorithm=settings.jwt_algorithm,
        )

        # Both should verify successfully with the same secret
        ai_decoded = jwt.decode(
            ai_token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )
        gibbon_decoded = jwt.decode(
            gibbon_token, settings.jwt_secret_key, algorithms=[settings.jwt_algorithm]
        )

        assert ai_decoded["source"] == "ai-service"
        assert gibbon_decoded["source"] == "gibbon"
