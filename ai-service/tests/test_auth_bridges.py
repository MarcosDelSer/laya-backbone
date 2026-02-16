"""Unit tests for role synchronization bridges.

Tests the role mapping functionality between Gibbon and AI Service,
including role conversion, validation, and access control helpers.
"""

from __future__ import annotations

import pytest

from app.auth.bridges import (
    AIServiceRole,
    GibbonRoleID,
    RoleMapping,
    can_access_role,
    get_ai_role_from_gibbon,
    get_gibbon_role_from_ai,
    get_role_hierarchy_level,
    has_admin_access,
    has_educator_access,
    has_staff_access,
    validate_role_mapping,
)


class TestGibbonToAIRoleMapping:
    """Tests for Gibbon to AI service role mapping."""

    def test_administrator_maps_to_admin(self):
        """Test that Gibbon Administrator (001) maps to admin."""
        assert get_ai_role_from_gibbon(GibbonRoleID.ADMINISTRATOR) == AIServiceRole.ADMIN
        assert RoleMapping.get_ai_role("001") == "admin"

    def test_teacher_maps_to_teacher(self):
        """Test that Gibbon Teacher (002) maps to teacher."""
        assert get_ai_role_from_gibbon(GibbonRoleID.TEACHER) == AIServiceRole.TEACHER
        assert RoleMapping.get_ai_role("002") == "teacher"

    def test_student_maps_to_student(self):
        """Test that Gibbon Student (003) maps to student."""
        assert get_ai_role_from_gibbon(GibbonRoleID.STUDENT) == AIServiceRole.STUDENT
        assert RoleMapping.get_ai_role("003") == "student"

    def test_parent_maps_to_parent(self):
        """Test that Gibbon Parent (004) maps to parent."""
        assert get_ai_role_from_gibbon(GibbonRoleID.PARENT) == AIServiceRole.PARENT
        assert RoleMapping.get_ai_role("004") == "parent"

    def test_support_staff_maps_to_staff(self):
        """Test that Gibbon Support Staff (006) maps to staff."""
        assert get_ai_role_from_gibbon(GibbonRoleID.SUPPORT_STAFF) == AIServiceRole.STAFF
        assert RoleMapping.get_ai_role("006") == "staff"

    def test_unknown_role_maps_to_user(self):
        """Test that unknown Gibbon roles map to default 'user' role."""
        assert RoleMapping.get_ai_role("999") == AIServiceRole.USER
        assert get_ai_role_from_gibbon("999") == "user"
        assert get_ai_role_from_gibbon("005") == "user"
        assert get_ai_role_from_gibbon("abc") == "user"

    def test_empty_role_maps_to_user(self):
        """Test that empty role ID maps to default 'user' role."""
        assert RoleMapping.get_ai_role("") == AIServiceRole.USER


class TestAIToGibbonRoleMapping:
    """Tests for AI service to Gibbon role mapping (reverse mapping)."""

    def test_admin_maps_to_administrator(self):
        """Test that AI admin role maps to Gibbon Administrator."""
        assert get_gibbon_role_from_ai(AIServiceRole.ADMIN) == GibbonRoleID.ADMINISTRATOR
        assert RoleMapping.get_gibbon_role("admin") == "001"

    def test_teacher_maps_to_teacher(self):
        """Test that AI teacher role maps to Gibbon Teacher."""
        assert get_gibbon_role_from_ai(AIServiceRole.TEACHER) == GibbonRoleID.TEACHER
        assert RoleMapping.get_gibbon_role("teacher") == "002"

    def test_student_maps_to_student(self):
        """Test that AI student role maps to Gibbon Student."""
        assert get_gibbon_role_from_ai(AIServiceRole.STUDENT) == GibbonRoleID.STUDENT
        assert RoleMapping.get_gibbon_role("student") == "003"

    def test_parent_maps_to_parent(self):
        """Test that AI parent role maps to Gibbon Parent."""
        assert get_gibbon_role_from_ai(AIServiceRole.PARENT) == GibbonRoleID.PARENT
        assert RoleMapping.get_gibbon_role("parent") == "004"

    def test_staff_maps_to_support_staff(self):
        """Test that AI staff role maps to Gibbon Support Staff."""
        assert get_gibbon_role_from_ai(AIServiceRole.STAFF) == GibbonRoleID.SUPPORT_STAFF
        assert RoleMapping.get_gibbon_role("staff") == "006"

    def test_user_role_has_no_gibbon_mapping(self):
        """Test that default 'user' role has no Gibbon equivalent."""
        assert RoleMapping.get_gibbon_role(AIServiceRole.USER) is None
        assert get_gibbon_role_from_ai("user") is None

    def test_unknown_ai_role_returns_none(self):
        """Test that unknown AI roles return None."""
        assert RoleMapping.get_gibbon_role("superadmin") is None
        assert get_gibbon_role_from_ai("unknown") is None


class TestRoleValidation:
    """Tests for role validation functions."""

    def test_valid_gibbon_roles(self):
        """Test that valid Gibbon role IDs are recognized."""
        assert RoleMapping.is_valid_gibbon_role("001") is True
        assert RoleMapping.is_valid_gibbon_role("002") is True
        assert RoleMapping.is_valid_gibbon_role("003") is True
        assert RoleMapping.is_valid_gibbon_role("004") is True
        assert RoleMapping.is_valid_gibbon_role("006") is True

    def test_invalid_gibbon_roles(self):
        """Test that invalid Gibbon role IDs are not recognized."""
        assert RoleMapping.is_valid_gibbon_role("999") is False
        assert RoleMapping.is_valid_gibbon_role("005") is False
        assert RoleMapping.is_valid_gibbon_role("") is False
        assert RoleMapping.is_valid_gibbon_role("abc") is False

    def test_valid_ai_roles(self):
        """Test that valid AI service roles are recognized."""
        assert RoleMapping.is_valid_ai_role("admin") is True
        assert RoleMapping.is_valid_ai_role("teacher") is True
        assert RoleMapping.is_valid_ai_role("student") is True
        assert RoleMapping.is_valid_ai_role("parent") is True
        assert RoleMapping.is_valid_ai_role("staff") is True
        assert RoleMapping.is_valid_ai_role("user") is True

    def test_invalid_ai_roles(self):
        """Test that invalid AI service roles are not recognized."""
        assert RoleMapping.is_valid_ai_role("superadmin") is False
        assert RoleMapping.is_valid_ai_role("moderator") is False
        assert RoleMapping.is_valid_ai_role("") is False
        assert RoleMapping.is_valid_ai_role("unknown") is False

    def test_validate_correct_mapping(self):
        """Test validation of correct role mappings."""
        assert validate_role_mapping("001", "admin") is True
        assert validate_role_mapping("002", "teacher") is True
        assert validate_role_mapping("003", "student") is True
        assert validate_role_mapping("004", "parent") is True
        assert validate_role_mapping("006", "staff") is True

    def test_validate_incorrect_mapping(self):
        """Test validation rejects incorrect role mappings."""
        assert validate_role_mapping("001", "teacher") is False
        assert validate_role_mapping("002", "admin") is False
        assert validate_role_mapping("003", "parent") is False
        assert validate_role_mapping("004", "student") is False

    def test_validate_unknown_gibbon_role_maps_to_user(self):
        """Test that unknown Gibbon roles validate as mapping to 'user'."""
        assert validate_role_mapping("999", "user") is True
        assert validate_role_mapping("005", "user") is True
        assert validate_role_mapping("abc", "user") is True


class TestRoleMappingComplete:
    """Tests for complete role mapping retrieval."""

    def test_get_all_mappings_returns_dict(self):
        """Test that get_all_mappings returns a dictionary."""
        mappings = RoleMapping.get_all_mappings()
        assert isinstance(mappings, dict)

    def test_get_all_mappings_contains_all_roles(self):
        """Test that get_all_mappings contains all defined roles."""
        mappings = RoleMapping.get_all_mappings()
        assert mappings["001"] == "admin"
        assert mappings["002"] == "teacher"
        assert mappings["003"] == "student"
        assert mappings["004"] == "parent"
        assert mappings["006"] == "staff"

    def test_get_all_mappings_returns_copy(self):
        """Test that get_all_mappings returns a copy (not modifiable)."""
        mappings1 = RoleMapping.get_all_mappings()
        mappings2 = RoleMapping.get_all_mappings()
        assert mappings1 is not mappings2
        assert mappings1 == mappings2


class TestAccessControlHelpers:
    """Tests for access control helper functions."""

    def test_has_admin_access(self):
        """Test admin access detection."""
        assert has_admin_access("admin") is True
        assert has_admin_access("teacher") is False
        assert has_admin_access("staff") is False
        assert has_admin_access("student") is False
        assert has_admin_access("parent") is False
        assert has_admin_access("user") is False

    def test_has_educator_access(self):
        """Test educator access detection (admin and teacher)."""
        assert has_educator_access("admin") is True
        assert has_educator_access("teacher") is True
        assert has_educator_access("staff") is False
        assert has_educator_access("student") is False
        assert has_educator_access("parent") is False
        assert has_educator_access("user") is False

    def test_has_staff_access(self):
        """Test staff-level access detection (admin, teacher, staff)."""
        assert has_staff_access("admin") is True
        assert has_staff_access("teacher") is True
        assert has_staff_access("staff") is True
        assert has_staff_access("student") is False
        assert has_staff_access("parent") is False
        assert has_staff_access("user") is False


class TestRoleHierarchy:
    """Tests for role hierarchy and access control."""

    def test_role_hierarchy_levels(self):
        """Test that roles have correct hierarchy levels."""
        assert get_role_hierarchy_level("admin") == 4
        assert get_role_hierarchy_level("teacher") == 3
        assert get_role_hierarchy_level("staff") == 2
        assert get_role_hierarchy_level("parent") == 1
        assert get_role_hierarchy_level("student") == 1
        assert get_role_hierarchy_level("user") == 0

    def test_unknown_role_hierarchy(self):
        """Test that unknown roles have hierarchy level 0."""
        assert get_role_hierarchy_level("unknown") == 0
        assert get_role_hierarchy_level("superadmin") == 0

    def test_admin_can_access_all_roles(self):
        """Test that admin can access resources for all roles."""
        assert can_access_role("admin", "teacher") is True
        assert can_access_role("admin", "staff") is True
        assert can_access_role("admin", "student") is True
        assert can_access_role("admin", "parent") is True
        assert can_access_role("admin", "user") is True

    def test_teacher_can_access_lower_roles(self):
        """Test that teacher can access student/parent/user but not admin."""
        assert can_access_role("teacher", "admin") is False
        assert can_access_role("teacher", "teacher") is True
        assert can_access_role("teacher", "staff") is True
        assert can_access_role("teacher", "student") is True
        assert can_access_role("teacher", "parent") is True
        assert can_access_role("teacher", "user") is True

    def test_staff_access_permissions(self):
        """Test staff role access permissions."""
        assert can_access_role("staff", "admin") is False
        assert can_access_role("staff", "teacher") is False
        assert can_access_role("staff", "staff") is True
        assert can_access_role("staff", "student") is True
        assert can_access_role("staff", "parent") is True
        assert can_access_role("staff", "user") is True

    def test_student_limited_access(self):
        """Test that students have limited access."""
        assert can_access_role("student", "admin") is False
        assert can_access_role("student", "teacher") is False
        assert can_access_role("student", "staff") is False
        assert can_access_role("student", "student") is True
        assert can_access_role("student", "parent") is True
        assert can_access_role("student", "user") is True

    def test_parent_limited_access(self):
        """Test that parents have limited access."""
        assert can_access_role("parent", "admin") is False
        assert can_access_role("parent", "teacher") is False
        assert can_access_role("parent", "staff") is False
        assert can_access_role("parent", "student") is True
        assert can_access_role("parent", "parent") is True
        assert can_access_role("parent", "user") is True

    def test_user_minimal_access(self):
        """Test that basic users have minimal access."""
        assert can_access_role("user", "admin") is False
        assert can_access_role("user", "teacher") is False
        assert can_access_role("user", "staff") is False
        assert can_access_role("user", "student") is False
        assert can_access_role("user", "parent") is False
        assert can_access_role("user", "user") is True


class TestEnumDefinitions:
    """Tests for enum type definitions."""

    def test_gibbon_role_id_enum_values(self):
        """Test that GibbonRoleID enum has correct values."""
        assert GibbonRoleID.ADMINISTRATOR.value == "001"
        assert GibbonRoleID.TEACHER.value == "002"
        assert GibbonRoleID.STUDENT.value == "003"
        assert GibbonRoleID.PARENT.value == "004"
        assert GibbonRoleID.SUPPORT_STAFF.value == "006"

    def test_ai_service_role_enum_values(self):
        """Test that AIServiceRole enum has correct values."""
        assert AIServiceRole.ADMIN.value == "admin"
        assert AIServiceRole.TEACHER.value == "teacher"
        assert AIServiceRole.STUDENT.value == "student"
        assert AIServiceRole.PARENT.value == "parent"
        assert AIServiceRole.STAFF.value == "staff"
        assert AIServiceRole.USER.value == "user"

    def test_gibbon_role_id_is_string_enum(self):
        """Test that GibbonRoleID values can be used as strings."""
        role_id = GibbonRoleID.TEACHER
        assert role_id == "002"
        assert role_id.value == "002"

    def test_ai_service_role_is_string_enum(self):
        """Test that AIServiceRole values can be used as strings."""
        role = AIServiceRole.TEACHER
        assert role == "teacher"
        assert role.value == "teacher"


class TestBidirectionalMapping:
    """Tests for bidirectional role mapping consistency."""

    def test_mapping_is_bidirectional_for_admin(self):
        """Test admin role mapping is consistent in both directions."""
        ai_role = get_ai_role_from_gibbon("001")
        gibbon_role = get_gibbon_role_from_ai(ai_role)
        assert gibbon_role == "001"

    def test_mapping_is_bidirectional_for_teacher(self):
        """Test teacher role mapping is consistent in both directions."""
        ai_role = get_ai_role_from_gibbon("002")
        gibbon_role = get_gibbon_role_from_ai(ai_role)
        assert gibbon_role == "002"

    def test_mapping_is_bidirectional_for_student(self):
        """Test student role mapping is consistent in both directions."""
        ai_role = get_ai_role_from_gibbon("003")
        gibbon_role = get_gibbon_role_from_ai(ai_role)
        assert gibbon_role == "003"

    def test_mapping_is_bidirectional_for_parent(self):
        """Test parent role mapping is consistent in both directions."""
        ai_role = get_ai_role_from_gibbon("004")
        gibbon_role = get_gibbon_role_from_ai(ai_role)
        assert gibbon_role == "004"

    def test_mapping_is_bidirectional_for_staff(self):
        """Test staff role mapping is consistent in both directions."""
        ai_role = get_ai_role_from_gibbon("006")
        gibbon_role = get_gibbon_role_from_ai(ai_role)
        assert gibbon_role == "006"

    def test_user_role_has_no_reverse_mapping(self):
        """Test that default 'user' role cannot be mapped back to Gibbon."""
        gibbon_role = get_gibbon_role_from_ai("user")
        assert gibbon_role is None
