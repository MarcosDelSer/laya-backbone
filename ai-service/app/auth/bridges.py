"""Role synchronization bridge between Gibbon and AI Service.

This module provides role mapping functionality to synchronize user roles
between Gibbon (PHP-based school management system) and the LAYA AI Service.

The role mapping ensures that user permissions from Gibbon are correctly
translated to AI service roles, maintaining consistent access control
across both systems.

Gibbon Role IDs:
    001: Administrator - Full system access
    002: Teacher - Educator with class management
    003: Student - Learner with limited access
    004: Parent - Guardian with child-related access
    006: Support Staff - Administrative support role

AI Service Roles:
    admin: Full administrative access
    teacher: Educator role with AI features
    student: Student role with learning features
    parent: Parent role with child monitoring
    staff: Support staff with limited admin
    user: Default role with basic access
"""

from __future__ import annotations

from enum import Enum
from typing import Optional


class GibbonRoleID(str, Enum):
    """Gibbon system role identifiers.

    These IDs match the gibbonRole table primary keys in a standard
    Gibbon installation. They represent the core user types in the
    school management system.
    """

    ADMINISTRATOR = "001"
    TEACHER = "002"
    STUDENT = "003"
    PARENT = "004"
    SUPPORT_STAFF = "006"


class AIServiceRole(str, Enum):
    """AI Service role identifiers.

    These roles define the permission levels and feature access
    within the LAYA AI Service ecosystem.
    """

    ADMIN = "admin"
    TEACHER = "teacher"
    STUDENT = "student"
    PARENT = "parent"
    STAFF = "staff"
    USER = "user"


class RoleMapping:
    """Role mapping configuration between Gibbon and AI Service.

    This class encapsulates the bidirectional mapping between Gibbon
    role IDs and AI service roles, ensuring consistent role translation
    across the authentication bridge.

    The mapping is designed to be extensible, allowing for additional
    custom roles to be added through configuration.
    """

    # Primary role mapping: Gibbon ID -> AI Service role
    _GIBBON_TO_AI: dict[str, str] = {
        GibbonRoleID.ADMINISTRATOR: AIServiceRole.ADMIN,
        GibbonRoleID.TEACHER: AIServiceRole.TEACHER,
        GibbonRoleID.STUDENT: AIServiceRole.STUDENT,
        GibbonRoleID.PARENT: AIServiceRole.PARENT,
        GibbonRoleID.SUPPORT_STAFF: AIServiceRole.STAFF,
    }

    # Reverse mapping: AI Service role -> Gibbon ID
    # Note: This is used for validation and reporting, not for
    # creating Gibbon users from AI service tokens
    _AI_TO_GIBBON: dict[str, str] = {
        AIServiceRole.ADMIN: GibbonRoleID.ADMINISTRATOR,
        AIServiceRole.TEACHER: GibbonRoleID.TEACHER,
        AIServiceRole.STUDENT: GibbonRoleID.STUDENT,
        AIServiceRole.PARENT: GibbonRoleID.PARENT,
        AIServiceRole.STAFF: GibbonRoleID.SUPPORT_STAFF,
    }

    # Default role for unmapped Gibbon roles
    _DEFAULT_AI_ROLE = AIServiceRole.USER

    @classmethod
    def get_ai_role(cls, gibbon_role_id: str) -> str:
        """Map a Gibbon role ID to an AI service role.

        Args:
            gibbon_role_id: Gibbon role identifier (e.g., "001" for Administrator)

        Returns:
            str: Corresponding AI service role name

        Examples:
            >>> RoleMapping.get_ai_role("001")
            'admin'
            >>> RoleMapping.get_ai_role("002")
            'teacher'
            >>> RoleMapping.get_ai_role("999")  # Unknown role
            'user'
        """
        return cls._GIBBON_TO_AI.get(gibbon_role_id, cls._DEFAULT_AI_ROLE)

    @classmethod
    def get_gibbon_role(cls, ai_role: str) -> Optional[str]:
        """Map an AI service role to a Gibbon role ID.

        This reverse mapping is primarily used for validation and
        reporting purposes, not for creating Gibbon users.

        Args:
            ai_role: AI service role name (e.g., "admin", "teacher")

        Returns:
            Optional[str]: Corresponding Gibbon role ID or None if unmapped

        Examples:
            >>> RoleMapping.get_gibbon_role("admin")
            '001'
            >>> RoleMapping.get_gibbon_role("teacher")
            '002'
            >>> RoleMapping.get_gibbon_role("user")
            None
        """
        return cls._AI_TO_GIBBON.get(ai_role)

    @classmethod
    def is_valid_gibbon_role(cls, gibbon_role_id: str) -> bool:
        """Check if a Gibbon role ID is recognized.

        Args:
            gibbon_role_id: Gibbon role identifier to validate

        Returns:
            bool: True if the role ID is mapped, False otherwise

        Examples:
            >>> RoleMapping.is_valid_gibbon_role("001")
            True
            >>> RoleMapping.is_valid_gibbon_role("999")
            False
        """
        return gibbon_role_id in cls._GIBBON_TO_AI

    @classmethod
    def is_valid_ai_role(cls, ai_role: str) -> bool:
        """Check if an AI service role is recognized.

        Args:
            ai_role: AI service role name to validate

        Returns:
            bool: True if the role is valid, False otherwise

        Examples:
            >>> RoleMapping.is_valid_ai_role("admin")
            True
            >>> RoleMapping.is_valid_ai_role("superadmin")
            False
        """
        try:
            AIServiceRole(ai_role)
            return True
        except ValueError:
            return False

    @classmethod
    def get_all_mappings(cls) -> dict[str, str]:
        """Get all Gibbon to AI role mappings.

        Returns:
            dict[str, str]: Complete mapping of Gibbon role IDs to AI roles

        Examples:
            >>> mappings = RoleMapping.get_all_mappings()
            >>> mappings["001"]
            'admin'
        """
        return cls._GIBBON_TO_AI.copy()


def get_ai_role_from_gibbon(gibbon_role_id: str) -> str:
    """Convert a Gibbon role ID to an AI service role.

    This is a convenience function that wraps RoleMapping.get_ai_role()
    for use in token processing and middleware.

    Args:
        gibbon_role_id: Gibbon role identifier

    Returns:
        str: Corresponding AI service role name

    Examples:
        >>> get_ai_role_from_gibbon("001")
        'admin'
        >>> get_ai_role_from_gibbon("004")
        'parent'
    """
    return RoleMapping.get_ai_role(gibbon_role_id)


def get_gibbon_role_from_ai(ai_role: str) -> Optional[str]:
    """Convert an AI service role to a Gibbon role ID.

    This is a convenience function that wraps RoleMapping.get_gibbon_role()
    for reverse lookups and validation.

    Args:
        ai_role: AI service role name

    Returns:
        Optional[str]: Corresponding Gibbon role ID or None if unmapped

    Examples:
        >>> get_gibbon_role_from_ai("admin")
        '001'
        >>> get_gibbon_role_from_ai("user")
        None
    """
    return RoleMapping.get_gibbon_role(ai_role)


def validate_role_mapping(gibbon_role_id: str, ai_role: str) -> bool:
    """Validate that a Gibbon role ID correctly maps to an AI service role.

    This function verifies that the role mapping is consistent and correct,
    useful for validating tokens and debugging authentication issues.

    Args:
        gibbon_role_id: Gibbon role identifier to check
        ai_role: AI service role that should correspond to the Gibbon role

    Returns:
        bool: True if the mapping is correct, False otherwise

    Examples:
        >>> validate_role_mapping("001", "admin")
        True
        >>> validate_role_mapping("001", "teacher")
        False
        >>> validate_role_mapping("999", "user")
        True  # Unknown roles map to 'user'
    """
    expected_role = RoleMapping.get_ai_role(gibbon_role_id)
    return expected_role == ai_role


def has_admin_access(role: str) -> bool:
    """Check if a role has administrative access.

    Args:
        role: AI service role to check

    Returns:
        bool: True if the role has admin privileges

    Examples:
        >>> has_admin_access("admin")
        True
        >>> has_admin_access("teacher")
        False
    """
    return role == AIServiceRole.ADMIN


def has_educator_access(role: str) -> bool:
    """Check if a role has educator privileges.

    Educator access includes both teachers and administrators.

    Args:
        role: AI service role to check

    Returns:
        bool: True if the role has educator privileges

    Examples:
        >>> has_educator_access("teacher")
        True
        >>> has_educator_access("admin")
        True
        >>> has_educator_access("student")
        False
    """
    return role in (AIServiceRole.ADMIN, AIServiceRole.TEACHER)


def has_staff_access(role: str) -> bool:
    """Check if a role has staff-level access.

    Staff access includes administrators, teachers, and support staff.

    Args:
        role: AI service role to check

    Returns:
        bool: True if the role has staff privileges

    Examples:
        >>> has_staff_access("staff")
        True
        >>> has_staff_access("admin")
        True
        >>> has_staff_access("parent")
        False
    """
    return role in (AIServiceRole.ADMIN, AIServiceRole.TEACHER, AIServiceRole.STAFF)


def get_role_hierarchy_level(role: str) -> int:
    """Get the hierarchy level of a role (higher number = more privileges).

    Args:
        role: AI service role to evaluate

    Returns:
        int: Hierarchy level (0-4, where 4 is highest)

    Examples:
        >>> get_role_hierarchy_level("admin")
        4
        >>> get_role_hierarchy_level("teacher")
        3
        >>> get_role_hierarchy_level("student")
        1
    """
    hierarchy = {
        AIServiceRole.ADMIN: 4,
        AIServiceRole.TEACHER: 3,
        AIServiceRole.STAFF: 2,
        AIServiceRole.PARENT: 1,
        AIServiceRole.STUDENT: 1,
        AIServiceRole.USER: 0,
    }
    return hierarchy.get(role, 0)


def can_access_role(user_role: str, target_role: str) -> bool:
    """Check if a user role can access resources meant for a target role.

    This implements a simple hierarchical access control where higher-level
    roles can access resources of lower-level roles.

    Args:
        user_role: Role of the user attempting access
        target_role: Role level of the resource being accessed

    Returns:
        bool: True if access should be granted

    Examples:
        >>> can_access_role("admin", "teacher")
        True
        >>> can_access_role("teacher", "admin")
        False
        >>> can_access_role("teacher", "student")
        True
    """
    user_level = get_role_hierarchy_level(user_role)
    target_level = get_role_hierarchy_level(target_role)
    return user_level >= target_level
