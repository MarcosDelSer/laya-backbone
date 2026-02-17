"""Password complexity validation for LAYA AI Service.

Provides password strength validation to enforce security requirements:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
"""

import re
from typing import Optional


class PasswordValidationError(Exception):
    """Exception raised when password validation fails.

    Attributes:
        message: Description of the validation failure
        field: Field name that failed validation (optional)
    """

    def __init__(self, message: str, field: Optional[str] = None):
        """Initialize password validation error.

        Args:
            message: Description of the validation failure
            field: Field name that failed validation
        """
        self.message = message
        self.field = field
        super().__init__(self.message)


def validate_password_complexity(password: str, field_name: str = "password") -> bool:
    """Validate password meets complexity requirements.

    Password must meet all of the following requirements:
    - At least 8 characters long
    - Contains at least one uppercase letter (A-Z)
    - Contains at least one lowercase letter (a-z)
    - Contains at least one number (0-9)

    Args:
        password: Password string to validate
        field_name: Name of the field for error messages (default: "password")

    Returns:
        bool: True if password meets all requirements

    Raises:
        PasswordValidationError: If password fails any validation check

    Examples:
        >>> validate_password_complexity("Password1")
        True

        >>> validate_password_complexity("short")
        PasswordValidationError: Password must be at least 8 characters long

        >>> validate_password_complexity("lowercase123")
        PasswordValidationError: Password must contain at least one uppercase letter
    """
    if not password:
        raise PasswordValidationError(
            f"{field_name.capitalize()} is required",
            field=field_name,
        )

    # Check minimum length (8 characters)
    if len(password) < 8:
        raise PasswordValidationError(
            f"{field_name.capitalize()} must be at least 8 characters long",
            field=field_name,
        )

    # Check for at least one uppercase letter
    if not re.search(r"[A-Z]", password):
        raise PasswordValidationError(
            f"{field_name.capitalize()} must contain at least one uppercase letter",
            field=field_name,
        )

    # Check for at least one lowercase letter
    if not re.search(r"[a-z]", password):
        raise PasswordValidationError(
            f"{field_name.capitalize()} must contain at least one lowercase letter",
            field=field_name,
        )

    # Check for at least one number
    if not re.search(r"[0-9]", password):
        raise PasswordValidationError(
            f"{field_name.capitalize()} must contain at least one number",
            field=field_name,
        )

    return True


def get_password_requirements() -> dict[str, str]:
    """Get password complexity requirements.

    Returns:
        dict[str, str]: Dictionary of requirement names and descriptions

    Example:
        >>> requirements = get_password_requirements()
        >>> print(requirements["min_length"])
        "At least 8 characters"
    """
    return {
        "min_length": "At least 8 characters",
        "uppercase": "At least one uppercase letter (A-Z)",
        "lowercase": "At least one lowercase letter (a-z)",
        "number": "At least one number (0-9)",
    }


def get_password_strength(password: str) -> dict[str, bool]:
    """Evaluate password strength against each requirement.

    This function checks each requirement individually without raising
    exceptions, useful for providing feedback to users.

    Args:
        password: Password string to evaluate

    Returns:
        dict[str, bool]: Dictionary indicating which requirements are met

    Example:
        >>> strength = get_password_strength("Pass123")
        >>> print(strength)
        {
            "min_length": True,
            "has_uppercase": True,
            "has_lowercase": True,
            "has_number": True,
            "is_valid": True
        }
    """
    strength = {
        "min_length": len(password) >= 8 if password else False,
        "has_uppercase": bool(re.search(r"[A-Z]", password)) if password else False,
        "has_lowercase": bool(re.search(r"[a-z]", password)) if password else False,
        "has_number": bool(re.search(r"[0-9]", password)) if password else False,
    }

    # Overall validity requires all checks to pass
    strength["is_valid"] = all(strength.values())

    return strength
