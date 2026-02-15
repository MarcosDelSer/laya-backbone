"""Base SQLAlchemy model for LAYA AI Service.

Provides the declarative base class for all database models.
"""

from sqlalchemy.orm import DeclarativeBase


class Base(DeclarativeBase):
    """Base class for all SQLAlchemy ORM models.

    All database models in the AI service should inherit from this class
    to ensure consistent metadata and configuration across the application.

    Example:
        class User(Base):
            __tablename__ = "users"

            id: Mapped[uuid.UUID] = mapped_column(primary_key=True)
            name: Mapped[str] = mapped_column(String(100))
    """

    pass
