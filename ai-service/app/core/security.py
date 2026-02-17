"""Security utilities for LAYA AI Service.

Provides password hashing and verification using bcrypt,
and token hashing using SHA-256.
"""

import hashlib

from passlib.context import CryptContext

# Bcrypt password hashing context
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def hash_password(password: str) -> str:
    """Hash a plain text password using bcrypt.

    Args:
        password: Plain text password to hash

    Returns:
        str: Hashed password suitable for storage in database

    Example:
        >>> hashed = hash_password("my_secure_password")
        >>> hashed.startswith("$2b$")
        True
    """
    return pwd_context.hash(password)


def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verify a plain text password against a hashed password.

    Args:
        plain_password: Plain text password to verify
        hashed_password: Hashed password from database

    Returns:
        bool: True if password matches, False otherwise

    Example:
        >>> hashed = hash_password("my_password")
        >>> verify_password("my_password", hashed)
        True
        >>> verify_password("wrong_password", hashed)
        False
    """
    return pwd_context.verify(plain_password, hashed_password)


def hash_token(token: str) -> str:
    """Hash a token using SHA-256 for secure storage.

    Unlike passwords, tokens use deterministic hashing (SHA-256) to allow
    direct database lookups by the hashed value.

    Args:
        token: Plain text token to hash

    Returns:
        str: SHA-256 hash of the token (hex encoded)

    Example:
        >>> hashed = hash_token("my_reset_token")
        >>> len(hashed) == 64  # SHA-256 produces 64 hex characters
        True
    """
    return hashlib.sha256(token.encode("utf-8")).hexdigest()
