"""UUID casting pattern checker for LAYA AI Service.

This script detects problematic UUID casting patterns in SQLAlchemy queries
that prevent database index usage and cause performance degradation.

Problematic patterns detected:
- cast(Model.id, String) == str(uuid_value)
- cast(Model.column_id, String) == str(uuid_value)
- Similar patterns that prevent index usage

Correct pattern:
- Model.id == uuid_value (direct UUID comparison)

Usage:
    python scripts/check_uuid_casting.py [--check-all] [--verbose]

Options:
    --check-all     Check all Python files in app/ directory
    --verbose       Display detailed output including correct patterns
    --strict        Exit with non-zero code if ANY issues found (default: only check fixed files)

Exit codes:
    0 - No issues found in critical files (activity_service.py, development_profile_service.py)
    1 - Issues found in critical files
    2 - Script execution error
"""

import argparse
import re
import sys
from pathlib import Path
from typing import Dict, List, Tuple


class Color:
    """ANSI color codes for terminal output."""

    GREEN = "\033[92m"
    YELLOW = "\033[93m"
    RED = "\033[91m"
    BLUE = "\033[94m"
    BOLD = "\033[1m"
    END = "\033[0m"


class UUIDCastingChecker:
    """Checks for problematic UUID casting patterns in Python code."""

    # Files that should have been fixed and must be clean
    CRITICAL_FILES = [
        "app/services/activity_service.py",
        "app/services/development_profile_service.py",
    ]

    # Problematic patterns to detect
    PATTERNS = [
        # cast(Model.id, String) == str(...)
        (r'cast\([^,]+\.id,\s*String\)\s*==\s*str\(',
         "UUID cast with String comparison"),

        # cast(Model.column_id, String) where column ends with _id
        (r'cast\([^,]+_id,\s*String\)\s*==\s*str\(',
         "Foreign key UUID cast with String comparison"),

        # cast(Model.id, String) without ==str but still problematic
        (r'cast\([^,]+\.id,\s*String\)',
         "UUID cast to String (may prevent index usage)"),

        # str(Model.id) in WHERE clauses (less common but still problematic)
        (r'\.where\([^)]*str\([^)]+\.id\)',
         "UUID string conversion in WHERE clause"),
    ]

    def __init__(self, verbose: bool = False, check_all: bool = False, strict: bool = False):
        """Initialize the UUID casting checker.

        Args:
            verbose: Enable detailed output
            check_all: Check all Python files, not just critical ones
            strict: Exit with error if ANY issues found
        """
        self.verbose = verbose
        self.check_all = check_all
        self.strict = strict
        self.issues: Dict[str, List[Tuple[int, str, str]]] = {}
        self.checked_files: List[str] = []

    def _print(self, message: str, color: str = "") -> None:
        """Print colored message.

        Args:
            message: Message to print
            color: ANSI color code
        """
        if color:
            print(f"{color}{message}{Color.END}")
        else:
            print(message)

    def _print_verbose(self, message: str) -> None:
        """Print message only in verbose mode.

        Args:
            message: Message to print
        """
        if self.verbose:
            print(message)

    def check_file(self, file_path: Path) -> List[Tuple[int, str, str]]:
        """Check a single file for UUID casting patterns.

        Args:
            file_path: Path to Python file

        Returns:
            List of (line_number, line_content, pattern_description) tuples
        """
        issues = []

        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                lines = f.readlines()

            # Track if we're in a SQLite-specific block
            in_sqlite_block = False
            sqlite_block_indent = 0

            for line_num, line in enumerate(lines, start=1):
                # Skip comments and blank lines
                stripped = line.strip()
                if not stripped or stripped.startswith('#'):
                    continue

                # Check for SQLite dialect conditional blocks
                if re.search(r'if\s+dialect_name\s*==\s*[\'"]sqlite[\'"]', line):
                    in_sqlite_block = True
                    sqlite_block_indent = len(line) - len(line.lstrip())
                    continue

                # Exit SQLite block when indentation returns to same or less
                if in_sqlite_block:
                    current_indent = len(line) - len(line.lstrip())
                    if stripped and current_indent <= sqlite_block_indent:
                        in_sqlite_block = False

                # Skip UUID casting inside SQLite compatibility blocks
                if in_sqlite_block:
                    self._print_verbose(f"  Skipping line {line_num} (SQLite compatibility block)")
                    continue

                # Check for comments indicating SQLite compatibility
                # Look at previous 3 lines for context
                context_lines = []
                for i in range(max(0, line_num - 4), line_num):
                    if i < len(lines):
                        context_lines.append(lines[i].lower())

                context_text = ''.join(context_lines)
                if 'sqlite' in context_text and ('compatibility' in context_text or 'test' in context_text):
                    self._print_verbose(f"  Skipping line {line_num} (SQLite compatibility context)")
                    continue

                # Check each pattern
                for pattern, description in self.PATTERNS:
                    if re.search(pattern, line):
                        issues.append((line_num, line.rstrip(), description))

        except Exception as e:
            self._print(f"Error reading {file_path}: {e}", Color.RED)

        return issues

    def check_critical_files(self) -> bool:
        """Check critical files that should be clean.

        Returns:
            True if all critical files are clean, False otherwise
        """
        base_path = Path(__file__).parent.parent
        all_clean = True

        self._print(f"\n{Color.BOLD}Checking critical files (must be clean):{Color.END}")

        for file_path_str in self.CRITICAL_FILES:
            file_path = base_path / file_path_str

            if not file_path.exists():
                self._print(f"  ⚠️  {file_path_str}: File not found", Color.YELLOW)
                continue

            issues = self.check_file(file_path)
            self.checked_files.append(file_path_str)

            if issues:
                self.issues[file_path_str] = issues
                all_clean = False
                self._print(f"  ❌ {file_path_str}: {len(issues)} issue(s) found", Color.RED)
            else:
                self._print(f"  ✅ {file_path_str}: Clean", Color.GREEN)

        return all_clean

    def check_all_files(self) -> None:
        """Check all Python files in app/ directory."""
        base_path = Path(__file__).parent.parent / "app"

        if not base_path.exists():
            self._print(f"Error: {base_path} not found", Color.RED)
            return

        self._print(f"\n{Color.BOLD}Checking all Python files in app/:{Color.END}")

        python_files = sorted(base_path.rglob("*.py"))

        for file_path in python_files:
            # Skip __init__.py and test files
            if file_path.name == "__init__.py" or "test" in str(file_path):
                continue

            relative_path = str(file_path.relative_to(base_path.parent))

            # Skip if already checked in critical files
            if relative_path in self.checked_files:
                continue

            issues = self.check_file(file_path)

            if issues:
                self.issues[relative_path] = issues
                self._print(f"  ⚠️  {relative_path}: {len(issues)} issue(s) found", Color.YELLOW)
            elif self.verbose:
                self._print(f"  ✅ {relative_path}: Clean", Color.GREEN)

    def print_detailed_report(self) -> None:
        """Print detailed report of all issues found."""
        if not self.issues:
            return

        self._print(f"\n{Color.BOLD}Detailed Issue Report:{Color.END}")
        self._print("=" * 80)

        for file_path, file_issues in sorted(self.issues.items()):
            self._print(f"\n{Color.BOLD}{file_path}:{Color.END}")

            for line_num, line_content, pattern_desc in file_issues:
                self._print(f"  Line {line_num}: {pattern_desc}", Color.YELLOW)
                self._print(f"    {line_content.strip()}", Color.RED)

                # Show suggested fix
                if "cast" in line_content:
                    self._print(f"    {Color.BLUE}💡 Tip: Remove cast() and use direct UUID comparison{Color.END}")

    def print_summary(self, critical_clean: bool) -> None:
        """Print summary of check results.

        Args:
            critical_clean: Whether critical files are clean
        """
        self._print("\n" + "=" * 80)
        self._print(f"{Color.BOLD}Summary:{Color.END}")

        total_files_checked = len(self.checked_files) + len([f for f in self.issues.keys() if f not in self.checked_files])
        total_issues = sum(len(issues) for issues in self.issues.values())
        files_with_issues = len(self.issues)

        self._print(f"  Files checked: {total_files_checked}")
        self._print(f"  Files with issues: {files_with_issues}")
        self._print(f"  Total issues: {total_issues}")

        if critical_clean:
            self._print(f"\n  {Color.GREEN}✅ All critical files are clean!{Color.END}")
        else:
            self._print(f"\n  {Color.RED}❌ Critical files have UUID casting issues!{Color.END}")

        if files_with_issues > 0 and not critical_clean:
            self._print(f"\n  {Color.YELLOW}⚠️  Please fix the issues in critical files.{Color.END}")
        elif files_with_issues > 0 and self.check_all:
            self._print(f"\n  {Color.YELLOW}ℹ️  Other files have issues (consider fixing in future).{Color.END}")

    def run(self) -> int:
        """Run the UUID casting check.

        Returns:
            Exit code (0 = success, 1 = issues found, 2 = error)
        """
        try:
            self._print(f"{Color.BOLD}UUID Casting Pattern Checker{Color.END}")
            self._print("Checking for problematic cast(*.id, String) patterns...")

            # Always check critical files
            critical_clean = self.check_critical_files()

            # Optionally check all files
            if self.check_all:
                self.check_all_files()

            # Print detailed report if issues found or verbose mode
            if self.issues and (self.verbose or not critical_clean):
                self.print_detailed_report()

            # Print summary
            self.print_summary(critical_clean)

            # Determine exit code
            if not critical_clean:
                # Critical files have issues - always fail
                return 1
            elif self.strict and self.issues:
                # Strict mode - fail if ANY issues found
                return 1
            else:
                # Success - critical files clean
                return 0

        except Exception as e:
            self._print(f"\n{Color.RED}Error: {e}{Color.END}", Color.RED)
            return 2


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="Check for problematic UUID casting patterns in Python code",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Check only critical files (default)
  python scripts/check_uuid_casting.py

  # Check all Python files
  python scripts/check_uuid_casting.py --check-all

  # Verbose output with all details
  python scripts/check_uuid_casting.py --check-all --verbose

  # Strict mode - fail if ANY issues found
  python scripts/check_uuid_casting.py --check-all --strict
        """
    )

    parser.add_argument(
        '--check-all',
        action='store_true',
        help='Check all Python files in app/ directory'
    )

    parser.add_argument(
        '--verbose', '-v',
        action='store_true',
        help='Display detailed output'
    )

    parser.add_argument(
        '--strict',
        action='store_true',
        help='Exit with error if ANY issues found (not just critical files)'
    )

    args = parser.parse_args()

    checker = UUIDCastingChecker(
        verbose=args.verbose,
        check_all=args.check_all,
        strict=args.strict
    )

    exit_code = checker.run()
    sys.exit(exit_code)


if __name__ == "__main__":
    main()
