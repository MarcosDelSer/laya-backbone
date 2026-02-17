#!/usr/bin/env python3
"""
JWT Configuration Verification Script

This script verifies that Gibbon and AI Service are configured with the same
JWT secret key. It checks environment variables, .env files, and provides
recommendations for fixing configuration issues.

Usage:
    python3 scripts/verify-jwt-config.py
"""

import os
import sys
from pathlib import Path
from typing import Optional, Tuple


class Colors:
    """ANSI color codes for terminal output."""
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    BOLD = '\033[1m'
    END = '\033[0m'


def print_header(text: str) -> None:
    """Print a formatted header."""
    print(f"\n{Colors.BOLD}{Colors.BLUE}{'=' * 80}{Colors.END}")
    print(f"{Colors.BOLD}{Colors.BLUE} {text}{Colors.END}")
    print(f"{Colors.BOLD}{Colors.BLUE}{'=' * 80}{Colors.END}\n")


def print_success(text: str) -> None:
    """Print a success message."""
    print(f"{Colors.GREEN}✓ {text}{Colors.END}")


def print_error(text: str) -> None:
    """Print an error message."""
    print(f"{Colors.RED}✗ {text}{Colors.END}")


def print_warning(text: str) -> None:
    """Print a warning message."""
    print(f"{Colors.YELLOW}⚠ {text}{Colors.END}")


def print_info(text: str) -> None:
    """Print an info message."""
    print(f"  {text}")


def load_env_file(file_path: Path) -> Optional[str]:
    """Load JWT_SECRET_KEY from an .env file.

    Args:
        file_path: Path to the .env file

    Returns:
        The JWT_SECRET_KEY value if found, None otherwise
    """
    if not file_path.exists():
        return None

    try:
        with open(file_path, 'r') as f:
            for line in f:
                line = line.strip()
                if line.startswith('#') or not line:
                    continue
                if '=' in line:
                    key, value = line.split('=', 1)
                    if key.strip() == 'JWT_SECRET_KEY':
                        return value.strip().strip('"').strip("'")
    except Exception as e:
        print_error(f"Error reading {file_path}: {e}")

    return None


def get_ai_service_secret() -> Tuple[Optional[str], str]:
    """Get the AI Service JWT secret.

    Returns:
        Tuple of (secret_value, source_description)
    """
    # Try environment variable
    secret = os.getenv('JWT_SECRET_KEY')
    if secret:
        return secret, "Environment variable JWT_SECRET_KEY"

    # Try .env file
    env_path = Path('ai-service/.env')
    secret = load_env_file(env_path)
    if secret:
        return secret, f"{env_path}"

    # Try .env.example (should not be used in production!)
    env_example_path = Path('ai-service/.env.example')
    secret = load_env_file(env_example_path)
    if secret:
        return secret, f"{env_example_path} (DEFAULT - NOT FOR PRODUCTION)"

    return None, "Not configured"


def get_gibbon_secret() -> Tuple[Optional[str], str]:
    """Get the Gibbon JWT secret.

    Returns:
        Tuple of (secret_value, source_description)
    """
    # Try environment variable
    secret = os.getenv('JWT_SECRET_KEY')
    if secret:
        return secret, "Environment variable JWT_SECRET_KEY"

    # Try .env file
    env_path = Path('gibbon/.env')
    secret = load_env_file(env_path)
    if secret:
        return secret, f"{env_path}"

    # Try .env.example (should not be used in production!)
    env_example_path = Path('gibbon/.env.example')
    secret = load_env_file(env_example_path)
    if secret:
        return secret, f"{env_example_path} (DEFAULT - NOT FOR PRODUCTION)"

    return None, "Not configured"


def is_default_secret(secret: str) -> bool:
    """Check if a secret is the default insecure value."""
    return secret == 'your_jwt_secret_key_change_in_production'


def verify_secret_strength(secret: str) -> Tuple[bool, list[str]]:
    """Verify that a secret is strong enough.

    Args:
        secret: The secret to verify

    Returns:
        Tuple of (is_strong, list_of_issues)
    """
    issues = []

    if len(secret) < 32:
        issues.append(f"Too short ({len(secret)} chars, minimum 32 recommended)")

    if is_default_secret(secret):
        issues.append("Using default secret (NEVER use in production!)")

    # Check for common weak patterns
    if secret.lower() in ['secret', 'password', 'jwt', 'key']:
        issues.append("Using a common word (use cryptographically random string)")

    return len(issues) == 0, issues


def main() -> int:
    """Main verification logic.

    Returns:
        Exit code (0 = success, 1 = failure)
    """
    print_header("JWT Configuration Verification")

    # Get AI Service secret
    print(f"{Colors.BOLD}Checking AI Service configuration...{Colors.END}")
    ai_secret, ai_source = get_ai_service_secret()

    if ai_secret:
        print_success(f"Found JWT secret")
        print_info(f"Source: {ai_source}")
        print_info(f"Length: {len(ai_secret)} characters")

        is_strong, issues = verify_secret_strength(ai_secret)
        if is_strong:
            print_success("Secret appears strong")
        else:
            for issue in issues:
                print_warning(issue)
    else:
        print_error(f"No JWT secret found")
        print_info(f"Source checked: {ai_source}")

    # Get Gibbon secret
    print(f"\n{Colors.BOLD}Checking Gibbon configuration...{Colors.END}")
    gibbon_secret, gibbon_source = get_gibbon_secret()

    if gibbon_secret:
        print_success(f"Found JWT secret")
        print_info(f"Source: {gibbon_source}")
        print_info(f"Length: {len(gibbon_secret)} characters")

        is_strong, issues = verify_secret_strength(gibbon_secret)
        if is_strong:
            print_success("Secret appears strong")
        else:
            for issue in issues:
                print_warning(issue)
    else:
        print_error(f"No JWT secret found")
        print_info(f"Source checked: {gibbon_source}")

    # Compare secrets
    print(f"\n{Colors.BOLD}Comparing secrets...{Colors.END}")

    if not ai_secret or not gibbon_secret:
        print_error("Cannot compare - one or both secrets are missing")
        success = False
    elif ai_secret == gibbon_secret:
        print_success("Secrets match! Cross-service authentication will work.")
        success = True
    else:
        print_error("Secrets DO NOT match!")
        print_info("AI Service and Gibbon have different JWT secrets.")
        print_info("Cross-service authentication will FAIL.")
        success = False

    # Print recommendations
    print_header("Recommendations")

    if not ai_secret or not gibbon_secret:
        print("1. Generate a secure JWT secret:")
        print(f"   {Colors.BOLD}./scripts/generate-jwt-secret.sh{Colors.END}")
        print()
        print("2. Add the secret to both services:")
        print(f"   {Colors.BOLD}ai-service/.env{Colors.END}")
        print(f"   {Colors.BOLD}gibbon/.env{Colors.END}")
        print()

    if ai_secret and gibbon_secret and ai_secret != gibbon_secret:
        print("1. Ensure both services use the SAME secret:")
        print(f"   {Colors.BOLD}ai-service/.env:{Colors.END} JWT_SECRET_KEY=<same-secret>")
        print(f"   {Colors.BOLD}gibbon/.env:{Colors.END} JWT_SECRET_KEY=<same-secret>")
        print()
        print("2. Restart both services:")
        print(f"   {Colors.BOLD}docker-compose restart ai-service php-fpm{Colors.END}")
        print()

    if (ai_secret and is_default_secret(ai_secret)) or \
       (gibbon_secret and is_default_secret(gibbon_secret)):
        print(f"{Colors.RED}{Colors.BOLD}SECURITY WARNING:{Colors.END}")
        print("Using default secret is INSECURE and should NEVER be used in production!")
        print()
        print("Generate a new secure secret:")
        print(f"   {Colors.BOLD}./scripts/generate-jwt-secret.sh{Colors.END}")
        print()

    print("For detailed setup instructions, see:")
    print(f"   {Colors.BOLD}docs/JWT_SHARED_SECRET_SETUP.md{Colors.END}")
    print()

    # Summary
    print_header("Summary")

    if success and ai_secret and gibbon_secret and not is_default_secret(ai_secret):
        print_success("Configuration is correct! ✓")
        print_info("Both services are using the same strong JWT secret.")
        return 0
    else:
        print_error("Configuration needs attention! ✗")
        if not success:
            print_info("Secrets do not match or are missing.")
        if ai_secret and is_default_secret(ai_secret):
            print_info("Using default insecure secret.")
        return 1


if __name__ == '__main__':
    sys.exit(main())
