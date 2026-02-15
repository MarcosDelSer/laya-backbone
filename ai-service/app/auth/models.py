"""Authentication SQLAlchemy models for LAYA AI Service.

Defines database models for user authentication and authorization.
"""

from datetime import datetime
from enum import Enum as PyEnum
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import (
    Boolean,
    DateTime,
    Enum,
    String,
    func,
)
from sqlalchemy.dialects.postgresql import UUID as PGUUID
from sqlalchemy.orm import Mapped, mapped_column

from app.models.base import Base


class UserRole(str, PyEnum):
    """User roles for role-based access control.

    Attributes:
        ADMIN: System administrator with full access
        TEACHER: Teacher with access to classroom features
        PARENT: Parent with access to their child's information
        ACCOUNTANT: Accountant with access to financial features
        STAFF: Staff member with limited access
    """

    ADMIN = "admin"
    TEACHER = "teacher"
    PARENT = "parent"
    ACCOUNTANT = "accountant"
    STAFF = "staff"


class User(Base):
    """SQLAlchemy model for user authentication and authorization.

    Represents a user in the LAYA system with authentication credentials
    and role-based access control.

    Attributes:
        id: Unique identifier for the user
        email: User's email address (used for login)
        password_hash: Hashed password using bcrypt
        first_name: User's first name
        last_name: User's last name
        role: User's role for access control
        is_active: Whether the user account is active
        created_at: Timestamp when the user was created
        updated_at: Timestamp when the user was last updated
    """

    __tablename__ = "users"

    id: Mapped[UUID] = mapped_column(
        PGUUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    email: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
        unique=True,
        index=True,
    )
    password_hash: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    first_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    last_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    role: Mapped[UserRole] = mapped_column(
        Enum(UserRole, name="user_role_enum", create_constraint=True),
        nullable=False,
        index=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
        index=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        nullable=False,
        server_default=func.now(),
        onupdate=func.now(),
    )

    def __repr__(self) -> str:
        """Return string representation of the User."""
        return f"<User(id={self.id}, email='{self.email}', role={self.role.value})>"
