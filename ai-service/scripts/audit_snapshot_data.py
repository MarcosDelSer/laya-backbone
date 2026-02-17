#!/usr/bin/env python3
"""Audit intervention plan snapshot data integrity.

This script audits intervention plan version snapshots to identify:
- Versions with null snapshot_data
- Snapshots missing critical fields (created_by, parent_version_id)
- Incomplete snapshots (missing 8-part structure sections)
- Data completeness statistics

This is a read-only audit script that reports issues without modifying data.
Use the backfill script to repair corrupted snapshots.

Usage:
    python scripts/audit_snapshot_data.py                # Full audit
    python scripts/audit_snapshot_data.py --dry-run      # Same as above (no writes)
    python scripts/audit_snapshot_data.py --verbose      # Detailed output
    python scripts/audit_snapshot_data.py --plan-id UUID # Audit specific plan
"""

import argparse
import asyncio
import sys
from datetime import datetime
from typing import Dict, List, Optional, Set
from uuid import UUID

from sqlalchemy import select, func, and_
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import AsyncSessionLocal, engine
from app.models.intervention_plan import InterventionPlan, InterventionVersion


# ANSI color codes for terminal output
class Colors:
    """ANSI color codes for colorized terminal output."""
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'


class SnapshotAuditResult:
    """Result of a snapshot audit operation."""

    def __init__(self):
        """Initialize audit result."""
        self.total_versions = 0
        self.versions_with_null_data = 0
        self.versions_missing_created_by = 0
        self.versions_missing_parent_version_id = 0
        self.versions_missing_sections = 0
        self.initial_versions_null = 0
        self.issues: List[str] = []
        self.warnings: List[str] = []
        self.section_stats: Dict[str, int] = {}

    def add_issue(self, message: str) -> None:
        """Add an issue to the audit result.

        Args:
            message: Issue description
        """
        self.issues.append(message)

    def add_warning(self, message: str) -> None:
        """Add a warning to the audit result.

        Args:
            message: Warning description
        """
        self.warnings.append(message)

    def get_completeness_percentage(self) -> float:
        """Calculate overall snapshot completeness percentage.

        Returns:
            Percentage of complete snapshots (0-100)
        """
        if self.total_versions == 0:
            return 100.0

        complete_snapshots = (
            self.total_versions
            - self.versions_with_null_data
            - self.versions_missing_created_by
            - self.versions_missing_parent_version_id
            - self.versions_missing_sections
        )

        return (complete_snapshots / self.total_versions) * 100.0


class SnapshotDataAuditor:
    """Audit intervention plan snapshot data integrity."""

    # Expected sections in snapshot 8-part structure
    EXPECTED_SECTIONS = {
        "strengths",
        "needs",
        "goals",
        "strategies",
        "monitoring",
        "parent_involvements",
        "consultations",
    }

    # Required top-level fields in snapshot
    REQUIRED_FIELDS = {
        "id",
        "child_id",
        "created_by",  # Bug #1: Currently missing
        "title",
        "status",
        "version",
        "child_name",
        "parent_version_id",  # Bug #1: Currently missing
    }

    def __init__(
        self,
        session: AsyncSession,
        verbose: bool = False,
        plan_id: Optional[UUID] = None,
    ):
        """Initialize snapshot auditor.

        Args:
            session: Database session
            verbose: Enable verbose output
            plan_id: Optional specific plan ID to audit
        """
        self.session = session
        self.verbose = verbose
        self.plan_id = plan_id
        self.result = SnapshotAuditResult()

    async def run_audit(self) -> SnapshotAuditResult:
        """Run complete snapshot audit.

        Returns:
            Audit result with statistics and issues
        """
        print(f"\n{Colors.HEADER}{Colors.BOLD}Intervention Plan Snapshot Audit{Colors.ENDC}")
        print(f"{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}\n")

        if self.plan_id:
            print(f"{Colors.OKBLUE}Auditing specific plan: {self.plan_id}{Colors.ENDC}\n")
        else:
            print(f"{Colors.OKBLUE}Auditing all intervention plan snapshots{Colors.ENDC}\n")

        # Get all versions to audit
        await self._audit_versions()

        # Print audit report
        self._print_report()

        return self.result

    async def _audit_versions(self) -> None:
        """Audit all intervention plan versions."""
        # Build query
        stmt = select(InterventionVersion)
        if self.plan_id:
            stmt = stmt.where(InterventionVersion.plan_id == self.plan_id)

        # Execute query
        db_result = await self.session.execute(stmt)
        versions = db_result.scalars().all()

        self.result.total_versions = len(versions)

        if self.verbose:
            print(f"{Colors.OKCYAN}Checking {len(versions)} version records...{Colors.ENDC}\n")

        # Audit each version
        for version in versions:
            await self._audit_single_version(version)

    async def _audit_single_version(self, version: InterventionVersion) -> None:
        """Audit a single version record.

        Args:
            version: The version record to audit
        """
        # Check 1: Null snapshot data (Bug #2)
        if version.snapshot_data is None:
            self.result.versions_with_null_data += 1
            issue = (
                f"Version {version.version_number} of plan {version.plan_id}: "
                f"snapshot_data is NULL"
            )
            self.result.add_issue(issue)

            if self.verbose:
                print(f"{Colors.FAIL}✗ {issue}{Colors.ENDC}")

            # Track initial versions specifically (Bug #2: Initial version has null data)
            if version.version_number == 1:
                self.result.initial_versions_null += 1
                self.result.add_warning(
                    f"Initial version (v1) of plan {version.plan_id} has NULL snapshot_data"
                )

            return  # Cannot check further if snapshot is null

        snapshot = version.snapshot_data

        # Check 2: Missing created_by field (Bug #1)
        if "created_by" not in snapshot:
            self.result.versions_missing_created_by += 1
            issue = (
                f"Version {version.version_number} of plan {version.plan_id}: "
                f"snapshot missing 'created_by' field"
            )
            self.result.add_issue(issue)

            if self.verbose:
                print(f"{Colors.FAIL}✗ {issue}{Colors.ENDC}")

        # Check 3: Missing parent_version_id field (Bug #1)
        if "parent_version_id" not in snapshot:
            self.result.versions_missing_parent_version_id += 1
            issue = (
                f"Version {version.version_number} of plan {version.plan_id}: "
                f"snapshot missing 'parent_version_id' field"
            )
            self.result.add_issue(issue)

            if self.verbose:
                print(f"{Colors.FAIL}✗ {issue}{Colors.ENDC}")

        # Check 4: Missing sections from 8-part structure
        missing_sections = self.EXPECTED_SECTIONS - set(snapshot.keys())
        if missing_sections:
            self.result.versions_missing_sections += 1
            issue = (
                f"Version {version.version_number} of plan {version.plan_id}: "
                f"snapshot missing sections: {', '.join(missing_sections)}"
            )
            self.result.add_issue(issue)

            if self.verbose:
                print(f"{Colors.FAIL}✗ {issue}{Colors.ENDC}")

        # Check 5: Empty sections (Bug #3: Relationship loading issue)
        for section in self.EXPECTED_SECTIONS:
            if section in snapshot:
                section_data = snapshot[section]
                if isinstance(section_data, list) and len(section_data) == 0:
                    # Track empty sections for statistics
                    self.result.section_stats[f"{section}_empty"] = (
                        self.result.section_stats.get(f"{section}_empty", 0) + 1
                    )
                else:
                    # Track non-empty sections
                    self.result.section_stats[f"{section}_populated"] = (
                        self.result.section_stats.get(f"{section}_populated", 0) + 1
                    )

        # Success case
        if self.verbose:
            all_checks_passed = (
                version.snapshot_data is not None
                and "created_by" in snapshot
                and "parent_version_id" in snapshot
                and len(missing_sections) == 0
            )
            if all_checks_passed:
                print(
                    f"{Colors.OKGREEN}✓ Version {version.version_number} of plan "
                    f"{version.plan_id}: Complete snapshot{Colors.ENDC}"
                )

    def _print_report(self) -> None:
        """Print comprehensive audit report."""
        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")
        print(f"{Colors.HEADER}{Colors.BOLD}Audit Report{Colors.ENDC}\n")

        # Overall statistics
        print(f"{Colors.BOLD}Overall Statistics:{Colors.ENDC}")
        print(f"  Total versions audited: {self.result.total_versions}")

        completeness = self.result.get_completeness_percentage()
        color = Colors.OKGREEN if completeness == 100.0 else Colors.WARNING if completeness >= 80.0 else Colors.FAIL
        print(f"  Snapshot completeness: {color}{completeness:.1f}%{Colors.ENDC}\n")

        # Issue breakdown
        print(f"{Colors.BOLD}Issue Breakdown:{Colors.ENDC}")

        if self.result.versions_with_null_data > 0:
            print(
                f"  {Colors.FAIL}Versions with NULL snapshot_data: "
                f"{self.result.versions_with_null_data}{Colors.ENDC}"
            )
            if self.result.initial_versions_null > 0:
                print(
                    f"    {Colors.WARNING}└─ Initial versions (v1) with NULL data: "
                    f"{self.result.initial_versions_null}{Colors.ENDC}"
                )
        else:
            print(f"  {Colors.OKGREEN}✓ No NULL snapshot_data found{Colors.ENDC}")

        if self.result.versions_missing_created_by > 0:
            print(
                f"  {Colors.FAIL}Snapshots missing 'created_by' field: "
                f"{self.result.versions_missing_created_by}{Colors.ENDC}"
            )
        else:
            print(f"  {Colors.OKGREEN}✓ All snapshots have 'created_by' field{Colors.ENDC}")

        if self.result.versions_missing_parent_version_id > 0:
            print(
                f"  {Colors.FAIL}Snapshots missing 'parent_version_id' field: "
                f"{self.result.versions_missing_parent_version_id}{Colors.ENDC}"
            )
        else:
            print(f"  {Colors.OKGREEN}✓ All snapshots have 'parent_version_id' field{Colors.ENDC}")

        if self.result.versions_missing_sections > 0:
            print(
                f"  {Colors.FAIL}Snapshots with missing sections: "
                f"{self.result.versions_missing_sections}{Colors.ENDC}"
            )
        else:
            print(f"  {Colors.OKGREEN}✓ All snapshots have complete 8-part structure{Colors.ENDC}")

        # Section statistics
        if self.result.section_stats and self.verbose:
            print(f"\n{Colors.BOLD}Section Population Statistics:{Colors.ENDC}")
            for section in self.EXPECTED_SECTIONS:
                populated = self.result.section_stats.get(f"{section}_populated", 0)
                empty = self.result.section_stats.get(f"{section}_empty", 0)
                total = populated + empty
                if total > 0:
                    pct = (populated / total) * 100
                    print(f"  {section}: {populated}/{total} populated ({pct:.1f}%)")

        # Warnings
        if self.result.warnings and self.verbose:
            print(f"\n{Colors.WARNING}{Colors.BOLD}Warnings:{Colors.ENDC}")
            for warning in self.result.warnings[:10]:  # Limit to first 10
                print(f"  - {warning}")
            if len(self.result.warnings) > 10:
                print(f"  ... and {len(self.result.warnings) - 10} more warnings")

        # Critical issues
        if self.result.issues:
            print(f"\n{Colors.FAIL}{Colors.BOLD}Critical Issues Found:{Colors.ENDC}")
            print(f"  Total issues: {len(self.result.issues)}")

            if self.verbose:
                print(f"\n{Colors.FAIL}Issue Details:{Colors.ENDC}")
                for issue in self.result.issues[:20]:  # Limit to first 20
                    print(f"  - {issue}")
                if len(self.result.issues) > 20:
                    print(f"  ... and {len(self.result.issues) - 20} more issues")

        # Recommendations
        print(f"\n{Colors.BOLD}Recommendations:{Colors.ENDC}")
        if self.result.versions_with_null_data > 0:
            print(f"  {Colors.WARNING}• Run backfill script to populate NULL snapshots{Colors.ENDC}")

        if self.result.versions_missing_created_by > 0 or self.result.versions_missing_parent_version_id > 0:
            print(
                f"  {Colors.WARNING}• Update snapshot creation logic to include missing fields{Colors.ENDC}"
            )
            print(
                f"  {Colors.WARNING}• Run migration script to add missing fields to existing snapshots{Colors.ENDC}"
            )

        if self.result.versions_missing_sections > 0:
            print(
                f"  {Colors.WARNING}• Investigate relationship loading issues (Bug #3){Colors.ENDC}"
            )

        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")

        # Final summary
        if completeness == 100.0:
            print(f"{Colors.OKGREEN}{Colors.BOLD}✓ All snapshots are complete!{Colors.ENDC}\n")
        else:
            print(
                f"{Colors.FAIL}{Colors.BOLD}✗ Snapshot data has integrity issues. "
                f"Review recommendations above.{Colors.ENDC}\n"
            )


async def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="Audit Intervention Plan Snapshot Data Integrity",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python scripts/audit_snapshot_data.py                      # Full audit
  python scripts/audit_snapshot_data.py --dry-run            # Same as above (read-only)
  python scripts/audit_snapshot_data.py --verbose            # Detailed output
  python scripts/audit_snapshot_data.py --plan-id <UUID>     # Audit specific plan
        """
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Dry run mode (read-only, same as default behavior)"
    )
    parser.add_argument(
        "--verbose",
        action="store_true",
        help="Enable verbose output with detailed information"
    )
    parser.add_argument(
        "--plan-id",
        type=str,
        help="Audit specific plan by UUID"
    )

    args = parser.parse_args()

    # Parse plan_id if provided
    plan_id = None
    if args.plan_id:
        try:
            plan_id = UUID(args.plan_id)
        except ValueError:
            print(f"{Colors.FAIL}Error: Invalid UUID format for --plan-id{Colors.ENDC}")
            sys.exit(1)

    # Create database session
    async with AsyncSessionLocal() as session:
        auditor = SnapshotDataAuditor(
            session,
            verbose=args.verbose,
            plan_id=plan_id,
        )
        result = await auditor.run_audit()

    # Exit with appropriate code
    # Exit 0 if all complete, 1 if issues found
    completeness = result.get_completeness_percentage()
    sys.exit(0 if completeness == 100.0 else 1)


if __name__ == "__main__":
    asyncio.run(main())
