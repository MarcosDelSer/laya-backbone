"""Child profile schemas for LAYA AI Service.

Defines Pydantic schemas for child profile data received from Gibbon.
These schemas are used when fetching and caching child profile information.
"""

from datetime import date, datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseResponse, BaseSchema


class Gender(str, Enum):
    """Child gender options.

    Attributes:
        MALE: Male
        FEMALE: Female
        OTHER: Other/prefer not to say
    """

    MALE = "male"
    FEMALE = "female"
    OTHER = "other"


class SpecialNeedInfo(BaseModel):
    """Information about a child's special needs.

    Attributes:
        need_type: Type of special need
        description: Description of the special need
        accommodations: Required accommodations
        severity: Severity level (low, medium, high)
    """

    need_type: str = Field(
        ...,
        max_length=100,
        description="Type of special need"
    )
    description: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Description of the special need"
    )
    accommodations: Optional[str] = Field(
        default=None,
        max_length=1000,
        description="Required accommodations"
    )
    severity: Optional[str] = Field(
        default="medium",
        max_length=20,
        description="Severity level (low, medium, high)"
    )


class EmergencyContact(BaseModel):
    """Emergency contact information.

    Attributes:
        name: Contact name
        relationship: Relationship to child
        phone: Phone number
        email: Email address (optional)
    """

    name: str = Field(
        ...,
        max_length=200,
        description="Contact name"
    )
    relationship: str = Field(
        ...,
        max_length=100,
        description="Relationship to child"
    )
    phone: str = Field(
        ...,
        max_length=20,
        description="Phone number"
    )
    email: Optional[str] = Field(
        default=None,
        max_length=255,
        description="Email address"
    )


class ChildProfileSchema(BaseSchema):
    """Child profile schema.

    Contains comprehensive information about a child including
    basic info, special needs, allergies, and emergency contacts.

    Attributes:
        id: Unique identifier of the child
        first_name: Child's first name
        last_name: Child's last name
        date_of_birth: Child's date of birth
        gender: Child's gender
        enrollment_date: Date of enrollment
        classroom_id: Current classroom ID
        parent_ids: List of parent/guardian IDs
        special_needs: List of special needs
        allergies: List of allergies
        medications: List of current medications
        dietary_restrictions: Dietary restrictions
        emergency_contacts: Emergency contact information
        notes: Additional notes about the child
        is_active: Whether the child is currently active
        updated_at: Last update timestamp
    """

    id: UUID = Field(
        ...,
        description="Unique identifier of the child"
    )
    first_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Child's first name"
    )
    last_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Child's last name"
    )
    date_of_birth: date = Field(
        ...,
        description="Child's date of birth"
    )
    gender: Optional[Gender] = Field(
        default=None,
        description="Child's gender"
    )
    enrollment_date: Optional[date] = Field(
        default=None,
        description="Date of enrollment"
    )
    classroom_id: Optional[UUID] = Field(
        default=None,
        description="Current classroom ID"
    )
    parent_ids: list[UUID] = Field(
        default_factory=list,
        description="List of parent/guardian IDs"
    )
    special_needs: list[SpecialNeedInfo] = Field(
        default_factory=list,
        description="List of special needs"
    )
    allergies: list[str] = Field(
        default_factory=list,
        description="List of allergies"
    )
    medications: list[str] = Field(
        default_factory=list,
        description="List of current medications"
    )
    dietary_restrictions: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Dietary restrictions"
    )
    emergency_contacts: list[EmergencyContact] = Field(
        default_factory=list,
        description="Emergency contact information"
    )
    notes: Optional[str] = Field(
        default=None,
        max_length=2000,
        description="Additional notes about the child"
    )
    is_active: bool = Field(
        default=True,
        description="Whether the child is currently active"
    )
    updated_at: datetime = Field(
        default_factory=datetime.now,
        description="Last update timestamp"
    )
