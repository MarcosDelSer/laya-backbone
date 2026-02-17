#!/usr/bin/env python3
"""Backfill intervention plan snapshot data.

This script backfills missing or incomplete snapshot data for intervention plan versions:
- Reconstructs snapshots for versions with null snapshot_data
- Adds missing fields (created_by, parent_version_id) to existing snapshots
- Validates and repairs incomplete snapshots

WARNING: This script modifies data. Always run with --dry-run first to preview changes.

Usage:
    python scripts/backfill_snapshot_data.py --dry-run                # Preview changes
    python scripts/backfill_snapshot_data.py --dry-run --limit 10     # Preview first 10
    python scripts/backfill_snapshot_data.py --limit 10               # Backfill first 10
    python scripts/backfill_snapshot_data.py                          # Backfill all
    python scripts/backfill_snapshot_data.py --plan-id UUID           # Backfill specific plan
"""

import argparse
import asyncio
import sys
from datetime import datetime
from typing import Dict, List, Optional
from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

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


class BackfillResult:
    """Result of a backfill operation."""

    def __init__(self):
        """Initialize backfill result."""
        self.total_versions = 0
        self.versions_backfilled = 0
        self.versions_updated = 0
        self.versions_skipped = 0
        self.errors: List[str] = []
        self.backfilled_versions: List[Dict] = []

    def add_backfilled(self, plan_id: UUID, version_number: int, reason: str) -> None:
        """Record a backfilled version.

        Args:
            plan_id: Plan ID
            version_number: Version number
            reason: Reason for backfill
        """
        self.versions_backfilled += 1
        self.backfilled_versions.append({
            "plan_id": str(plan_id),
            "version_number": version_number,
            "reason": reason,
        })

    def add_updated(self) -> None:
        """Record an updated version."""
        self.versions_updated += 1

    def add_skipped(self) -> None:
        """Record a skipped version."""
        self.versions_skipped += 1

    def add_error(self, message: str) -> None:
        """Add an error to the result.

        Args:
            message: Error description
        """
        self.errors.append(message)


class SnapshotDataBackfiller:
    """Backfill intervention plan snapshot data."""

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
        "created_by",
        "title",
        "status",
        "version",
        "child_name",
        "parent_version_id",
    }

    def __init__(
        self,
        session: AsyncSession,
        dry_run: bool = True,
        verbose: bool = False,
        limit: Optional[int] = None,
        plan_id: Optional[UUID] = None,
    ):
        """Initialize snapshot backfiller.

        Args:
            session: Database session
            dry_run: If True, preview changes without modifying data
            verbose: Enable verbose output
            limit: Maximum number of versions to process
            plan_id: Optional specific plan ID to backfill
        """
        self.session = session
        self.dry_run = dry_run
        self.verbose = verbose
        self.limit = limit
        self.plan_id = plan_id
        self.result = BackfillResult()

    async def run_backfill(self) -> BackfillResult:
        """Run complete snapshot backfill.

        Returns:
            Backfill result with statistics
        """
        mode = "DRY RUN" if self.dry_run else "LIVE MODE"
        print(f"\n{Colors.HEADER}{Colors.BOLD}Intervention Plan Snapshot Backfill - {mode}{Colors.ENDC}")
        print(f"{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}\n")

        if self.dry_run:
            print(f"{Colors.WARNING}Running in DRY RUN mode - no data will be modified{Colors.ENDC}\n")
        else:
            print(f"{Colors.FAIL}Running in LIVE MODE - data WILL be modified{Colors.ENDC}\n")

        if self.plan_id:
            print(f"{Colors.OKBLUE}Backfilling specific plan: {self.plan_id}{Colors.ENDC}\n")
        elif self.limit:
            print(f"{Colors.OKBLUE}Processing up to {self.limit} versions{Colors.ENDC}\n")
        else:
            print(f"{Colors.OKBLUE}Processing all versions{Colors.ENDC}\n")

        # Get all versions that need backfilling
        await self._backfill_versions()

        # Print backfill report
        self._print_report()

        # Commit changes if not dry run
        if not self.dry_run and self.versions_backfilled > 0:
            await self.session.commit()
            print(f"\n{Colors.OKGREEN}✓ Changes committed to database{Colors.ENDC}")
        elif not self.dry_run:
            print(f"\n{Colors.WARNING}No changes to commit{Colors.ENDC}")

        return self.result

    async def _backfill_versions(self) -> None:
        """Backfill all intervention plan versions that need it."""
        # Build query for versions needing backfill
        stmt = select(InterventionVersion)
        if self.plan_id:
            stmt = stmt.where(InterventionVersion.plan_id == self.plan_id)

        # Apply limit
        if self.limit:
            stmt = stmt.limit(self.limit)

        # Execute query
        db_result = await self.session.execute(stmt)
        versions = db_result.scalars().all()

        self.result.total_versions = len(versions)

        if self.verbose:
            print(f"{Colors.OKCYAN}Checking {len(versions)} version records...{Colors.ENDC}\n")

        # Process each version
        for version in versions:
            await self._process_single_version(version)

    async def _process_single_version(self, version: InterventionVersion) -> None:
        """Process a single version record.

        Args:
            version: The version record to process
        """
        needs_backfill = False
        needs_update = False
        reason_parts = []

        # Check 1: Null snapshot data
        if version.snapshot_data is None:
            needs_backfill = True
            reason_parts.append("NULL snapshot_data")

        # Check 2: Missing required fields
        if version.snapshot_data is not None:
            missing_fields = self.REQUIRED_FIELDS - set(version.snapshot_data.keys())
            if missing_fields:
                needs_update = True
                reason_parts.append(f"missing fields: {', '.join(missing_fields)}")

        # Skip if no action needed
        if not needs_backfill and not needs_update:
            self.result.add_skipped()
            if self.verbose:
                print(
                    f"{Colors.OKGREEN}✓ Version {version.version_number} of plan "
                    f"{version.plan_id}: Already complete{Colors.ENDC}"
                )
            return

        reason = "; ".join(reason_parts)

        # Log what we're about to do
        if self.verbose:
            action = "Would backfill" if self.dry_run else "Backfilling"
            print(
                f"{Colors.WARNING}{action} version {version.version_number} of plan "
                f"{version.plan_id}: {reason}{Colors.ENDC}"
            )

        # Reconstruct snapshot
        try:
            snapshot = await self._reconstruct_snapshot(version)

            if snapshot:
                # Update version with new snapshot
                if not self.dry_run:
                    version.snapshot_data = snapshot
                    await self.session.flush()

                if needs_backfill:
                    self.result.add_backfilled(version.plan_id, version.version_number, reason)
                else:
                    self.result.add_updated()

                if self.verbose:
                    status = "Would be backfilled" if self.dry_run else "Backfilled"
                    print(
                        f"{Colors.OKGREEN}✓ {status}: Version {version.version_number} "
                        f"of plan {version.plan_id}{Colors.ENDC}"
                    )
            else:
                error = (
                    f"Failed to reconstruct snapshot for version {version.version_number} "
                    f"of plan {version.plan_id}: Plan not found"
                )
                self.result.add_error(error)
                if self.verbose:
                    print(f"{Colors.FAIL}✗ {error}{Colors.ENDC}")

        except Exception as e:
            error = (
                f"Error processing version {version.version_number} "
                f"of plan {version.plan_id}: {str(e)}"
            )
            self.result.add_error(error)
            if self.verbose:
                print(f"{Colors.FAIL}✗ {error}{Colors.ENDC}")

    async def _reconstruct_snapshot(self, version: InterventionVersion) -> Optional[dict]:
        """Reconstruct snapshot data for a version.

        This is a best-effort reconstruction. For versions with null snapshot_data,
        we reconstruct from the current plan state. This may not reflect the exact
        state at the time the version was created, but provides a reasonable snapshot.

        For versions with existing snapshot_data, we add missing fields.

        Args:
            version: The version record to reconstruct snapshot for

        Returns:
            Reconstructed snapshot dict, or None if plan not found
        """
        # Load the plan with all relationships
        stmt = (
            select(InterventionPlan)
            .where(InterventionPlan.id == version.plan_id)
            .options(
                selectinload(InterventionPlan.strengths),
                selectinload(InterventionPlan.needs),
                selectinload(InterventionPlan.goals),
                selectinload(InterventionPlan.strategies),
                selectinload(InterventionPlan.monitoring),
                selectinload(InterventionPlan.parent_involvements),
                selectinload(InterventionPlan.consultations),
            )
        )
        db_result = await self.session.execute(stmt)
        plan = db_result.scalar_one_or_none()

        if not plan:
            return None

        # If we have existing snapshot data, merge with it
        if version.snapshot_data is not None:
            snapshot = version.snapshot_data.copy()
        else:
            # Create new snapshot from current plan state
            snapshot = {}

        # Get parent version ID for lineage tracking
        parent_version_id = None
        if version.version_number > 1:
            # Find the previous version
            parent_query = (
                select(InterventionVersion)
                .where(InterventionVersion.plan_id == version.plan_id)
                .where(InterventionVersion.version_number == version.version_number - 1)
            )
            parent_result = await self.session.execute(parent_query)
            parent_version = parent_result.scalar_one_or_none()
            if parent_version:
                parent_version_id = str(parent_version.id)

        # Update or add required top-level fields
        snapshot.update({
            "id": str(plan.id),
            "child_id": str(plan.child_id),
            "created_by": str(plan.created_by) if plan.created_by else None,
            "parent_version_id": parent_version_id,
            "title": plan.title,
            "status": plan.status,
            "version": plan.version,
            "child_name": plan.child_name,
            "date_of_birth": plan.date_of_birth.isoformat() if plan.date_of_birth else None,
            "diagnosis": plan.diagnosis,
            "medical_history": plan.medical_history,
            "educational_history": plan.educational_history,
            "family_context": plan.family_context,
            "review_schedule": plan.review_schedule,
            "next_review_date": plan.next_review_date.isoformat() if plan.next_review_date else None,
            "effective_date": plan.effective_date.isoformat() if plan.effective_date else None,
            "end_date": plan.end_date.isoformat() if plan.end_date else None,
            "parent_signed": plan.parent_signed,
            "created_at": plan.created_at.isoformat() if plan.created_at else None,
            "updated_at": plan.updated_at.isoformat() if plan.updated_at else None,
        })

        # Only add 8-part structure if snapshot_data was null
        # (otherwise we preserve the historical data)
        if version.snapshot_data is None:
            snapshot["strengths"] = [
                {
                    "id": str(s.id),
                    "category": s.category,
                    "description": s.description,
                    "examples": s.examples,
                    "order": s.order,
                }
                for s in plan.strengths
            ]
            snapshot["needs"] = [
                {
                    "id": str(n.id),
                    "category": n.category,
                    "description": n.description,
                    "priority": n.priority,
                    "baseline": n.baseline,
                    "order": n.order,
                }
                for n in plan.needs
            ]
            snapshot["goals"] = [
                {
                    "id": str(g.id),
                    "need_id": str(g.need_id) if g.need_id else None,
                    "title": g.title,
                    "description": g.description,
                    "measurement_criteria": g.measurement_criteria,
                    "measurement_baseline": g.measurement_baseline,
                    "measurement_target": g.measurement_target,
                    "achievability_notes": g.achievability_notes,
                    "relevance_notes": g.relevance_notes,
                    "target_date": g.target_date.isoformat() if g.target_date else None,
                    "status": g.status,
                    "progress_percentage": g.progress_percentage,
                    "order": g.order,
                }
                for g in plan.goals
            ]
            snapshot["strategies"] = [
                {
                    "id": str(s.id),
                    "goal_id": str(s.goal_id) if s.goal_id else None,
                    "title": s.title,
                    "description": s.description,
                    "responsible_party": s.responsible_party,
                    "frequency": s.frequency,
                    "materials_needed": s.materials_needed,
                    "accommodations": s.accommodations,
                    "order": s.order,
                }
                for s in plan.strategies
            ]
            snapshot["monitoring"] = [
                {
                    "id": str(m.id),
                    "goal_id": str(m.goal_id) if m.goal_id else None,
                    "method": m.method,
                    "description": m.description,
                    "frequency": m.frequency,
                    "responsible_party": m.responsible_party,
                    "data_collection_tools": m.data_collection_tools,
                    "success_indicators": m.success_indicators,
                    "order": m.order,
                }
                for m in plan.monitoring
            ]
            snapshot["parent_involvements"] = [
                {
                    "id": str(p.id),
                    "activity_type": p.activity_type,
                    "title": p.title,
                    "description": p.description,
                    "frequency": p.frequency,
                    "resources_provided": p.resources_provided,
                    "communication_method": p.communication_method,
                    "order": p.order,
                }
                for p in plan.parent_involvements
            ]
            snapshot["consultations"] = [
                {
                    "id": str(c.id),
                    "specialist_type": c.specialist_type,
                    "specialist_name": c.specialist_name,
                    "organization": c.organization,
                    "purpose": c.purpose,
                    "recommendations": c.recommendations,
                    "consultation_date": c.consultation_date.isoformat() if c.consultation_date else None,
                    "next_consultation_date": c.next_consultation_date.isoformat() if c.next_consultation_date else None,
                    "notes": c.notes,
                    "order": c.order,
                }
                for c in plan.consultations
            ]

        return snapshot

    def _print_report(self) -> None:
        """Print comprehensive backfill report."""
        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")
        print(f"{Colors.HEADER}{Colors.BOLD}Backfill Report{Colors.ENDC}\n")

        # Overall statistics
        print(f"{Colors.BOLD}Overall Statistics:{Colors.ENDC}")
        print(f"  Total versions processed: {self.result.total_versions}")
        print(f"  Versions backfilled (NULL data): {self.result.versions_backfilled}")
        print(f"  Versions updated (missing fields): {self.result.versions_updated}")
        print(f"  Versions skipped (already complete): {self.result.versions_skipped}")

        if self.result.errors:
            print(f"  {Colors.FAIL}Errors encountered: {len(self.result.errors)}{Colors.ENDC}")

        # Backfilled versions details
        if self.result.backfilled_versions and self.verbose:
            print(f"\n{Colors.BOLD}Backfilled Versions:{Colors.ENDC}")
            for item in self.result.backfilled_versions[:20]:  # Limit to first 20
                print(
                    f"  - Plan {item['plan_id']}, Version {item['version_number']}: "
                    f"{item['reason']}"
                )
            if len(self.result.backfilled_versions) > 20:
                print(f"  ... and {len(self.result.backfilled_versions) - 20} more versions")

        # Errors
        if self.result.errors:
            print(f"\n{Colors.FAIL}{Colors.BOLD}Errors:{Colors.ENDC}")
            for error in self.result.errors[:10]:  # Limit to first 10
                print(f"  - {error}")
            if len(self.result.errors) > 10:
                print(f"  ... and {len(self.result.errors) - 10} more errors")

        # Recommendations
        print(f"\n{Colors.BOLD}Next Steps:{Colors.ENDC}")
        if self.dry_run and self.result.versions_backfilled > 0:
            print(
                f"  {Colors.WARNING}• Run without --dry-run to apply backfill changes{Colors.ENDC}"
            )
        if self.result.versions_backfilled > 0 or self.result.versions_updated > 0:
            print(
                f"  {Colors.OKGREEN}• Run audit script to verify snapshot data integrity{Colors.ENDC}"
            )

        print(f"\n{Colors.OKCYAN}{'=' * 80}{Colors.ENDC}")

        # Final summary
        total_modified = self.result.versions_backfilled + self.result.versions_updated
        if total_modified == 0:
            print(f"{Colors.OKGREEN}{Colors.BOLD}✓ No backfill needed - all snapshots are complete!{Colors.ENDC}\n")
        elif self.dry_run:
            print(
                f"{Colors.WARNING}{Colors.BOLD}Preview: {total_modified} versions would be "
                f"backfilled/updated{Colors.ENDC}\n"
            )
        else:
            print(
                f"{Colors.OKGREEN}{Colors.BOLD}✓ Successfully backfilled/updated {total_modified} "
                f"versions{Colors.ENDC}\n"
            )


async def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="Backfill Intervention Plan Snapshot Data",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python scripts/backfill_snapshot_data.py --dry-run                  # Preview changes
  python scripts/backfill_snapshot_data.py --dry-run --limit 10       # Preview first 10
  python scripts/backfill_snapshot_data.py --limit 10                 # Backfill first 10
  python scripts/backfill_snapshot_data.py                            # Backfill all
  python scripts/backfill_snapshot_data.py --plan-id <UUID>           # Backfill specific plan

WARNING: Always run with --dry-run first to preview changes before modifying data.
        """
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Preview changes without modifying data (RECOMMENDED)"
    )
    parser.add_argument(
        "--verbose",
        action="store_true",
        help="Enable verbose output with detailed information"
    )
    parser.add_argument(
        "--limit",
        type=int,
        help="Maximum number of versions to process"
    )
    parser.add_argument(
        "--plan-id",
        type=str,
        help="Backfill specific plan by UUID"
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

    # Warn if not in dry-run mode
    if not args.dry_run:
        print(f"\n{Colors.FAIL}{Colors.BOLD}WARNING: Running in LIVE MODE - data WILL be modified!{Colors.ENDC}")
        print(f"{Colors.WARNING}Press Ctrl+C within 3 seconds to cancel...{Colors.ENDC}\n")
        await asyncio.sleep(3)

    # Create database session
    async with AsyncSessionLocal() as session:
        backfiller = SnapshotDataBackfiller(
            session,
            dry_run=args.dry_run,
            verbose=args.verbose,
            limit=args.limit,
            plan_id=plan_id,
        )
        result = await backfiller.run_backfill()

    # Exit with appropriate code
    # Exit 0 if successful or nothing to do, 1 if errors occurred
    sys.exit(1 if result.errors else 0)


if __name__ == "__main__":
    asyncio.run(main())
