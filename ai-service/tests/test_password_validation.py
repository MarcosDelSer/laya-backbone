"""Tests for password complexity validation."""

import pytest

from app.security.password import (
    PasswordValidationError,
    get_password_requirements,
    get_password_strength,
    validate_password_complexity,
)


class TestPasswordComplexityValidation:
    """Test password complexity validation function."""

    def test_valid_password_passes_validation(self):
        """Test that a valid password passes all checks."""
        valid_passwords = [
            "Password1",
            "MyP@ssw0rd",
            "Secure123",
            "Admin2024!",
            "Test1234Pass",
            "Aa1bcdefgh",
        ]

        for password in valid_passwords:
            assert validate_password_complexity(password) is True

    def test_empty_password_raises_error(self):
        """Test that empty password raises error."""
        with pytest.raises(PasswordValidationError) as exc_info:
            validate_password_complexity("")

        assert "required" in str(exc_info.value).lower()
        assert exc_info.value.field == "password"

    def test_none_password_raises_error(self):
        """Test that None password raises error."""
        with pytest.raises(PasswordValidationError) as exc_info:
            validate_password_complexity(None)

        assert "required" in str(exc_info.value).lower()

    def test_short_password_raises_error(self):
        """Test that password shorter than 8 characters raises error."""
        short_passwords = ["Pass1", "Ab1", "Short1A"]

        for password in short_passwords:
            with pytest.raises(PasswordValidationError) as exc_info:
                validate_password_complexity(password)

            assert "8 characters" in str(exc_info.value)
            assert exc_info.value.field == "password"

    def test_password_without_uppercase_raises_error(self):
        """Test that password without uppercase letter raises error."""
        no_uppercase = [
            "password123",
            "lowercase1",
            "test12345",
        ]

        for password in no_uppercase:
            with pytest.raises(PasswordValidationError) as exc_info:
                validate_password_complexity(password)

            assert "uppercase" in str(exc_info.value).lower()
            assert exc_info.value.field == "password"

    def test_password_without_lowercase_raises_error(self):
        """Test that password without lowercase letter raises error."""
        no_lowercase = [
            "PASSWORD123",
            "UPPERCASE1",
            "TEST12345",
        ]

        for password in no_lowercase:
            with pytest.raises(PasswordValidationError) as exc_info:
                validate_password_complexity(password)

            assert "lowercase" in str(exc_info.value).lower()
            assert exc_info.value.field == "password"

    def test_password_without_number_raises_error(self):
        """Test that password without number raises error."""
        no_number = [
            "Password",
            "NoNumbers",
            "TestPassword",
        ]

        for password in no_number:
            with pytest.raises(PasswordValidationError) as exc_info:
                validate_password_complexity(password)

            assert "number" in str(exc_info.value).lower()
            assert exc_info.value.field == "password"

    def test_custom_field_name_in_error_message(self):
        """Test that custom field name appears in error messages."""
        with pytest.raises(PasswordValidationError) as exc_info:
            validate_password_complexity("short", field_name="new_password")

        assert "new_password" in str(exc_info.value).lower()
        assert exc_info.value.field == "new_password"

    def test_password_with_special_characters_is_valid(self):
        """Test that passwords with special characters are valid."""
        passwords_with_special = [
            "P@ssw0rd",
            "Test123!",
            "Secure#2024",
            "My$ecure1Pass",
        ]

        for password in passwords_with_special:
            assert validate_password_complexity(password) is True

    def test_exactly_8_characters_is_valid(self):
        """Test that password with exactly 8 characters is valid."""
        assert validate_password_complexity("Password1") is True
        assert validate_password_complexity("Abcdef12") is True

    def test_very_long_password_is_valid(self):
        """Test that very long passwords are valid."""
        long_password = "ThisIsAVeryLongPassword123WithManyCharacters"
        assert validate_password_complexity(long_password) is True

    def test_password_with_spaces_is_valid(self):
        """Test that passwords with spaces are valid if they meet requirements."""
        assert validate_password_complexity("Pass word 123") is True
        assert validate_password_complexity("My Secure 1 Password") is True

    def test_unicode_characters_with_valid_password(self):
        """Test that Unicode characters don't interfere with validation."""
        assert validate_password_complexity("Pässw0rd") is True
        assert validate_password_complexity("Test123Ñ") is True


class TestGetPasswordRequirements:
    """Test password requirements retrieval function."""

    def test_get_password_requirements_returns_dict(self):
        """Test that function returns a dictionary."""
        requirements = get_password_requirements()
        assert isinstance(requirements, dict)

    def test_get_password_requirements_includes_all_requirements(self):
        """Test that all requirements are included."""
        requirements = get_password_requirements()

        assert "min_length" in requirements
        assert "uppercase" in requirements
        assert "lowercase" in requirements
        assert "number" in requirements

    def test_get_password_requirements_has_descriptions(self):
        """Test that requirements have human-readable descriptions."""
        requirements = get_password_requirements()

        # All values should be non-empty strings
        for key, value in requirements.items():
            assert isinstance(value, str)
            assert len(value) > 0

    def test_get_password_requirements_min_length_description(self):
        """Test that min_length has correct description."""
        requirements = get_password_requirements()
        assert "8" in requirements["min_length"]
        assert "characters" in requirements["min_length"].lower()

    def test_get_password_requirements_uppercase_description(self):
        """Test that uppercase has correct description."""
        requirements = get_password_requirements()
        assert "uppercase" in requirements["uppercase"].lower()

    def test_get_password_requirements_lowercase_description(self):
        """Test that lowercase has correct description."""
        requirements = get_password_requirements()
        assert "lowercase" in requirements["lowercase"].lower()

    def test_get_password_requirements_number_description(self):
        """Test that number has correct description."""
        requirements = get_password_requirements()
        assert "number" in requirements["number"].lower()


class TestGetPasswordStrength:
    """Test password strength evaluation function."""

    def test_valid_password_shows_all_requirements_met(self):
        """Test that valid password meets all requirements."""
        strength = get_password_strength("Password123")

        assert strength["min_length"] is True
        assert strength["has_uppercase"] is True
        assert strength["has_lowercase"] is True
        assert strength["has_number"] is True
        assert strength["is_valid"] is True

    def test_short_password_fails_min_length(self):
        """Test that short password fails minimum length check."""
        strength = get_password_strength("Pass1")

        assert strength["min_length"] is False
        assert strength["has_uppercase"] is True
        assert strength["has_lowercase"] is True
        assert strength["has_number"] is True
        assert strength["is_valid"] is False

    def test_password_without_uppercase_shows_missing_uppercase(self):
        """Test that password without uppercase shows missing requirement."""
        strength = get_password_strength("password123")

        assert strength["min_length"] is True
        assert strength["has_uppercase"] is False
        assert strength["has_lowercase"] is True
        assert strength["has_number"] is True
        assert strength["is_valid"] is False

    def test_password_without_lowercase_shows_missing_lowercase(self):
        """Test that password without lowercase shows missing requirement."""
        strength = get_password_strength("PASSWORD123")

        assert strength["min_length"] is True
        assert strength["has_uppercase"] is True
        assert strength["has_lowercase"] is False
        assert strength["has_number"] is True
        assert strength["is_valid"] is False

    def test_password_without_number_shows_missing_number(self):
        """Test that password without number shows missing requirement."""
        strength = get_password_strength("PasswordTest")

        assert strength["min_length"] is True
        assert strength["has_uppercase"] is True
        assert strength["has_lowercase"] is True
        assert strength["has_number"] is False
        assert strength["is_valid"] is False

    def test_empty_password_fails_all_checks(self):
        """Test that empty password fails all checks."""
        strength = get_password_strength("")

        assert strength["min_length"] is False
        assert strength["has_uppercase"] is False
        assert strength["has_lowercase"] is False
        assert strength["has_number"] is False
        assert strength["is_valid"] is False

    def test_none_password_fails_all_checks(self):
        """Test that None password fails all checks."""
        strength = get_password_strength(None)

        assert strength["min_length"] is False
        assert strength["has_uppercase"] is False
        assert strength["has_lowercase"] is False
        assert strength["has_number"] is False
        assert strength["is_valid"] is False

    def test_password_with_multiple_missing_requirements(self):
        """Test password missing multiple requirements."""
        strength = get_password_strength("test")

        assert strength["min_length"] is False
        assert strength["has_uppercase"] is False
        assert strength["has_lowercase"] is True
        assert strength["has_number"] is False
        assert strength["is_valid"] is False

    def test_get_password_strength_returns_all_keys(self):
        """Test that function returns all expected keys."""
        strength = get_password_strength("Test123")

        expected_keys = [
            "min_length",
            "has_uppercase",
            "has_lowercase",
            "has_number",
            "is_valid",
        ]

        for key in expected_keys:
            assert key in strength


class TestPasswordValidationError:
    """Test PasswordValidationError exception."""

    def test_password_validation_error_has_message(self):
        """Test that error has message attribute."""
        error = PasswordValidationError("Test error")
        assert error.message == "Test error"

    def test_password_validation_error_has_field(self):
        """Test that error has field attribute."""
        error = PasswordValidationError("Test error", field="password")
        assert error.field == "password"

    def test_password_validation_error_field_is_optional(self):
        """Test that field attribute is optional."""
        error = PasswordValidationError("Test error")
        assert error.field is None

    def test_password_validation_error_string_representation(self):
        """Test error string representation."""
        error = PasswordValidationError("Test error message")
        assert "Test error message" in str(error)

    def test_password_validation_error_is_exception(self):
        """Test that PasswordValidationError is an Exception."""
        error = PasswordValidationError("Test")
        assert isinstance(error, Exception)


class TestPasswordValidationIntegration:
    """Integration tests for password validation."""

    def test_realistic_user_registration_passwords(self):
        """Test realistic user registration password scenarios."""
        # Valid passwords users might choose
        valid_passwords = [
            "MySecure123",
            "Welcome2024!",
            "Admin@Pass1",
            "User12345Test",
        ]

        for password in valid_passwords:
            assert validate_password_complexity(password) is True
            strength = get_password_strength(password)
            assert strength["is_valid"] is True

    def test_common_weak_passwords_are_rejected(self):
        """Test that common weak passwords are rejected."""
        weak_passwords = [
            "password",  # No uppercase, no number
            "12345678",  # No letters
            "PASSWORD",  # No lowercase, no number
            "Pass1",  # Too short
            "password1",  # No uppercase
            "PASSWORD1",  # No lowercase
        ]

        for password in weak_passwords:
            with pytest.raises(PasswordValidationError):
                validate_password_complexity(password)

            strength = get_password_strength(password)
            assert strength["is_valid"] is False

    def test_password_strength_feedback_workflow(self):
        """Test workflow for providing password strength feedback to users."""
        # User enters weak password
        weak_password = "test"
        strength = get_password_strength(weak_password)

        # System can provide specific feedback
        feedback = []
        if not strength["min_length"]:
            feedback.append("Password must be at least 8 characters")
        if not strength["has_uppercase"]:
            feedback.append("Password must contain uppercase letter")
        if not strength["has_number"]:
            feedback.append("Password must contain number")

        assert len(feedback) > 0
        assert strength["is_valid"] is False

        # User improves password
        improved_password = "TestPassword1"
        strength = get_password_strength(improved_password)
        assert strength["is_valid"] is True

    def test_validation_and_strength_check_consistency(self):
        """Test that validate_password_complexity and get_password_strength agree."""
        test_passwords = [
            "ValidPass123",
            "weak",
            "NoNumber",
            "nocaps123",
            "NOLOWER1",
            "Short1A",
        ]

        for password in test_passwords:
            strength = get_password_strength(password)

            # If strength says it's valid, validation should pass
            if strength["is_valid"]:
                assert validate_password_complexity(password) is True
            else:
                # If strength says it's invalid, validation should fail
                with pytest.raises(PasswordValidationError):
                    validate_password_complexity(password)
