"""Unit tests for authentication SQLAlchemy models.

Tests UserRole enum, User model, TokenBlacklist model, and PasswordResetToken model.
"""

from datetime import datetime, timedelta, timezone
from uuid import UUID, uuid4

import pytest

from app.auth.models import (
    PasswordResetToken,
    TokenBlacklist,
    User,
    UserRole,
)


class TestUserRole:
    """Tests for UserRole enum."""

    def test_user_role_values(self):
        """Test UserRole enum has all expected values."""
        assert UserRole.ADMIN.value == "admin"
        assert UserRole.TEACHER.value == "teacher"
        assert UserRole.PARENT.value == "parent"
        assert UserRole.ACCOUNTANT.value == "accountant"
        assert UserRole.STAFF.value == "staff"

    def test_user_role_count(self):
        """Test UserRole enum has exactly 5 roles."""
        assert len(UserRole) == 5

    def test_user_role_inherits_from_str(self):
        """Test UserRole is a string enum."""
        assert isinstance(UserRole.ADMIN, str)
        assert isinstance(UserRole.TEACHER, str)

    def test_user_role_string_comparison(self):
        """Test UserRole can be compared with strings."""
        assert UserRole.ADMIN == "admin"
        assert UserRole.TEACHER == "teacher"
        assert UserRole.PARENT == "parent"
        assert UserRole.ACCOUNTANT == "accountant"
        assert UserRole.STAFF == "staff"

    def test_user_role_from_string(self):
        """Test UserRole can be created from string."""
        assert UserRole("admin") == UserRole.ADMIN
        assert UserRole("teacher") == UserRole.TEACHER
        assert UserRole("parent") == UserRole.PARENT
        assert UserRole("accountant") == UserRole.ACCOUNTANT
        assert UserRole("staff") == UserRole.STAFF

    def test_user_role_invalid_value(self):
        """Test UserRole raises ValueError for invalid value."""
        with pytest.raises(ValueError):
            UserRole("invalid_role")

    def test_user_role_iteration(self):
        """Test UserRole can be iterated."""
        roles = list(UserRole)
        assert len(roles) == 5
        assert UserRole.ADMIN in roles
        assert UserRole.TEACHER in roles
        assert UserRole.PARENT in roles
        assert UserRole.ACCOUNTANT in roles
        assert UserRole.STAFF in roles


class TestUserModel:
    """Tests for User SQLAlchemy model structure."""

    def test_user_tablename(self):
        """Test User model has correct table name."""
        assert User.__tablename__ == "users"

    def test_user_has_required_columns(self):
        """Test User model has all required columns."""
        columns = [col.name for col in User.__table__.columns]
        required_columns = [
            "id",
            "email",
            "password_hash",
            "first_name",
            "last_name",
            "role",
            "is_active",
            "created_at",
            "updated_at",
        ]
        for col in required_columns:
            assert col in columns, f"Missing column: {col}"

    def test_user_id_column(self):
        """Test User id column properties."""
        id_col = User.__table__.c.id
        assert id_col.primary_key

    def test_user_email_column(self):
        """Test User email column properties."""
        email_col = User.__table__.c.email
        assert not email_col.nullable
        assert email_col.unique
        assert email_col.index

    def test_user_password_hash_column(self):
        """Test User password_hash column properties."""
        password_hash_col = User.__table__.c.password_hash
        assert not password_hash_col.nullable

    def test_user_first_name_column(self):
        """Test User first_name column properties."""
        first_name_col = User.__table__.c.first_name
        assert not first_name_col.nullable

    def test_user_last_name_column(self):
        """Test User last_name column properties."""
        last_name_col = User.__table__.c.last_name
        assert not last_name_col.nullable

    def test_user_role_column(self):
        """Test User role column properties."""
        role_col = User.__table__.c.role
        assert not role_col.nullable
        assert role_col.index

    def test_user_is_active_column(self):
        """Test User is_active column properties."""
        is_active_col = User.__table__.c.is_active
        assert not is_active_col.nullable
        assert is_active_col.index

    def test_user_repr(self):
        """Test User __repr__ method."""
        user_id = uuid4()
        user = User(
            id=user_id,
            email="test@example.com",
            password_hash="hashed",
            first_name="Test",
            last_name="User",
            role=UserRole.TEACHER,
            is_active=True,
        )
        repr_str = repr(user)
        assert "User" in repr_str
        assert str(user_id) in repr_str
        assert "test@example.com" in repr_str
        assert "teacher" in repr_str


class TestTokenBlacklistModel:
    """Tests for TokenBlacklist SQLAlchemy model structure."""

    def test_token_blacklist_tablename(self):
        """Test TokenBlacklist model has correct table name."""
        assert TokenBlacklist.__tablename__ == "token_blacklist"

    def test_token_blacklist_has_required_columns(self):
        """Test TokenBlacklist model has all required columns."""
        columns = [col.name for col in TokenBlacklist.__table__.columns]
        required_columns = [
            "id",
            "token",
            "user_id",
            "blacklisted_at",
            "expires_at",
        ]
        for col in required_columns:
            assert col in columns, f"Missing column: {col}"

    def test_token_blacklist_id_column(self):
        """Test TokenBlacklist id column properties."""
        id_col = TokenBlacklist.__table__.c.id
        assert id_col.primary_key

    def test_token_blacklist_token_column(self):
        """Test TokenBlacklist token column properties."""
        token_col = TokenBlacklist.__table__.c.token
        assert not token_col.nullable
        assert token_col.unique
        assert token_col.index

    def test_token_blacklist_user_id_column(self):
        """Test TokenBlacklist user_id column properties."""
        user_id_col = TokenBlacklist.__table__.c.user_id
        assert not user_id_col.nullable
        assert user_id_col.index

    def test_token_blacklist_expires_at_column(self):
        """Test TokenBlacklist expires_at column properties."""
        expires_at_col = TokenBlacklist.__table__.c.expires_at
        assert not expires_at_col.nullable
        assert expires_at_col.index

    def test_token_blacklist_repr(self):
        """Test TokenBlacklist __repr__ method."""
        blacklist_id = uuid4()
        user_id = uuid4()
        now = datetime.now(timezone.utc)
        token_blacklist = TokenBlacklist(
            id=blacklist_id,
            token="test_token",
            user_id=user_id,
            blacklisted_at=now,
            expires_at=now + timedelta(hours=1),
        )
        repr_str = repr(token_blacklist)
        assert "TokenBlacklist" in repr_str
        assert str(blacklist_id) in repr_str
        assert str(user_id) in repr_str


class TestPasswordResetTokenModel:
    """Tests for PasswordResetToken SQLAlchemy model structure."""

    def test_password_reset_token_tablename(self):
        """Test PasswordResetToken model has correct table name."""
        assert PasswordResetToken.__tablename__ == "password_reset_tokens"

    def test_password_reset_token_has_required_columns(self):
        """Test PasswordResetToken model has all required columns."""
        columns = [col.name for col in PasswordResetToken.__table__.columns]
        required_columns = [
            "id",
            "token",
            "user_id",
            "email",
            "is_used",
            "created_at",
            "expires_at",
        ]
        for col in required_columns:
            assert col in columns, f"Missing column: {col}"

    def test_password_reset_token_id_column(self):
        """Test PasswordResetToken id column properties."""
        id_col = PasswordResetToken.__table__.c.id
        assert id_col.primary_key

    def test_password_reset_token_token_column(self):
        """Test PasswordResetToken token column properties."""
        token_col = PasswordResetToken.__table__.c.token
        assert not token_col.nullable
        assert token_col.unique
        assert token_col.index

    def test_password_reset_token_user_id_column(self):
        """Test PasswordResetToken user_id column properties."""
        user_id_col = PasswordResetToken.__table__.c.user_id
        assert not user_id_col.nullable
        assert user_id_col.index

    def test_password_reset_token_email_column(self):
        """Test PasswordResetToken email column properties."""
        email_col = PasswordResetToken.__table__.c.email
        assert not email_col.nullable
        assert email_col.index

    def test_password_reset_token_is_used_column(self):
        """Test PasswordResetToken is_used column properties."""
        is_used_col = PasswordResetToken.__table__.c.is_used
        assert not is_used_col.nullable
        assert is_used_col.index

    def test_password_reset_token_expires_at_column(self):
        """Test PasswordResetToken expires_at column properties."""
        expires_at_col = PasswordResetToken.__table__.c.expires_at
        assert not expires_at_col.nullable
        assert expires_at_col.index

    def test_password_reset_token_repr(self):
        """Test PasswordResetToken __repr__ method."""
        reset_id = uuid4()
        user_id = uuid4()
        now = datetime.now(timezone.utc)
        reset_token = PasswordResetToken(
            id=reset_id,
            token="hashed_token",
            user_id=user_id,
            email="test@example.com",
            is_used=False,
            created_at=now,
            expires_at=now + timedelta(hours=1),
        )
        repr_str = repr(reset_token)
        assert "PasswordResetToken" in repr_str
        assert str(reset_id) in repr_str
        assert str(user_id) in repr_str
        assert "is_used=False" in repr_str

    def test_password_reset_token_repr_used(self):
        """Test PasswordResetToken __repr__ method when used."""
        reset_id = uuid4()
        user_id = uuid4()
        now = datetime.now(timezone.utc)
        reset_token = PasswordResetToken(
            id=reset_id,
            token="hashed_token",
            user_id=user_id,
            email="test@example.com",
            is_used=True,
            created_at=now,
            expires_at=now + timedelta(hours=1),
        )
        repr_str = repr(reset_token)
        assert "is_used=True" in repr_str
