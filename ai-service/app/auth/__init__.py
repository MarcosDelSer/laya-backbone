"""Authentication bridge module for cross-service authentication.

This module provides role mapping and synchronization between Gibbon
and the AI service authentication systems.
"""

from app.auth.bridges import (
    AIServiceRole,
    GibbonRoleID,
    RoleMapping,
    get_ai_role_from_gibbon,
    get_gibbon_role_from_ai,
    validate_role_mapping,
)

__all__ = [
    "AIServiceRole",
    "GibbonRoleID",
    "RoleMapping",
    "get_ai_role_from_gibbon",
    "get_gibbon_role_from_ai",
    "validate_role_mapping",
]
