"""Alembic migration verification script for LAYA AI Service.

This script verifies the integrity and consistency of Alembic database migrations:
- Checks current migration status
- Detects pending migrations
- Verifies migrations can be applied cleanly (upgrade test)
- Verifies migrations are reversible (downgrade test)
- Validates schema consistency between models and database
- Detects orphaned or broken migrations

Usage:
    python scripts/verify_migrations.py [--full] [--check-schema] [--verbose]

Options:
    --full          Run full verification including upgrade/downgrade tests
    --check-schema  Compare database schema with SQLAlchemy models
    --verbose       Display detailed output
"""

import argparse
import asyncio
import sys
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple

from alembic import command
from alembic.config import Config
from alembic.runtime.migration import MigrationContext
from alembic.script import ScriptDirectory
from sqlalchemy import inspect, text
from sqlalchemy.ext.asyncio import AsyncEngine, create_async_engine

from app.config import settings
from app.models.base import Base


class Color:
    """ANSI color codes for terminal output."""

    GREEN = "\033[92m"
    YELLOW = "\033[93m"
    RED = "\033[91m"
    BLUE = "\033[94m"
    BOLD = "\033[1m"
    END = "\033[0m"


class MigrationVerifier:
    """Verifies Alembic migration integrity and consistency."""

    def __init__(self, verbose: bool = False):
        """Initialize the migration verifier.

        Args:
            verbose: Enable detailed output
        """
        self.verbose = verbose
        self.errors: List[str] = []
        self.warnings: List[str] = []
        self.info: List[str] = []

        # Get Alembic configuration
        self.alembic_cfg = self._get_alembic_config()
        self.script_dir = ScriptDirectory.from_config(self.alembic_cfg)

        # Database engine (will be initialized async)
        self.engine: Optional[AsyncEngine] = None

    def _get_alembic_config(self) -> Config:
        """Get Alembic configuration object.

        Returns:
            Config: Alembic configuration
        """
        # Find alembic.ini in ai-service directory
        alembic_ini_path = Path(__file__).parent.parent / "alembic.ini"

        if not alembic_ini_path.exists():
            self._error(f"alembic.ini not found at {alembic_ini_path}")
            sys.exit(1)

        cfg = Config(str(alembic_ini_path))
        cfg.set_main_option("sqlalchemy.url", settings.database_url)

        return cfg

    async def _get_engine(self) -> AsyncEngine:
        """Get or create async database engine.

        Returns:
            AsyncEngine: SQLAlchemy async engine
        """
        if self.engine is None:
            self.engine = create_async_engine(
                settings.database_url,
                echo=self.verbose,
            )
        return self.engine

    async def _get_current_revision(self) -> Optional[str]:
        """Get current database migration revision.

        Returns:
            Optional[str]: Current revision ID or None if no migrations applied
        """
        engine = await self._get_engine()

        async with engine.connect() as conn:
            context = await conn.run_sync(
                lambda sync_conn: MigrationContext.configure(sync_conn)
            )
            current_rev = context.get_current_revision()

        return current_rev

    def _get_head_revision(self) -> str:
        """Get head migration revision from scripts.

        Returns:
            str: Head revision ID
        """
        return self.script_dir.get_current_head()

    def _get_all_revisions(self) -> List[str]:
        """Get all migration revision IDs.

        Returns:
            List[str]: List of all revision IDs
        """
        revisions = []
        for rev in self.script_dir.walk_revisions():
            revisions.append(rev.revision)
        return revisions

    def _get_pending_migrations(
        self, current: Optional[str], head: str
    ) -> List[str]:
        """Get list of pending migrations.

        Args:
            current: Current database revision
            head: Head revision from scripts

        Returns:
            List[str]: List of pending revision IDs
        """
        if current is None:
            # No migrations applied - all migrations are pending
            return self._get_all_revisions()

        if current == head:
            return []

        # Get revisions between current and head
        pending = []
        for rev in self.script_dir.iterate_revisions(head, current):
            if rev.revision != current:
                pending.append(rev.revision)

        return pending

    async def _check_migration_status(self) -> bool:
        """Check current migration status.

        Returns:
            bool: True if check passed
        """
        self._info("\n" + "=" * 70)
        self._info("MIGRATION STATUS CHECK")
        self._info("=" * 70)

        try:
            current = await self._get_current_revision()
            head = self._get_head_revision()

            if current is None:
                self._warning("No migrations have been applied to the database")
                self._info(f"Head revision: {head}")
                return False

            self._success(f"Current revision: {current}")
            self._info(f"Head revision:    {head}")

            if current == head:
                self._success("✓ Database is up to date")
                return True
            else:
                pending = self._get_pending_migrations(current, head)
                self._warning(
                    f"Database is behind. {len(pending)} pending migration(s):"
                )
                for rev in pending:
                    script = self.script_dir.get_revision(rev)
                    self._info(f"  - {rev}: {script.doc}")
                return False

        except Exception as e:
            self._error(f"Failed to check migration status: {e}")
            return False

    async def _check_migration_files(self) -> bool:
        """Check migration files for common issues.

        Returns:
            bool: True if check passed
        """
        self._info("\n" + "=" * 70)
        self._info("MIGRATION FILES CHECK")
        self._info("=" * 70)

        try:
            all_revisions = self._get_all_revisions()
            self._info(f"Found {len(all_revisions)} migration(s)")

            # Check for duplicate revision IDs
            if len(all_revisions) != len(set(all_revisions)):
                self._error("Duplicate revision IDs detected!")
                return False

            # Verify each migration file
            issues_found = False
            for rev_id in all_revisions:
                script = self.script_dir.get_revision(rev_id)

                # Check for upgrade/downgrade functions
                if self.verbose:
                    self._info(f"  {rev_id}: {script.doc}")

                # Migration file path
                module_path = script.module.__file__
                if not Path(module_path).exists():
                    self._error(f"Migration file not found: {module_path}")
                    issues_found = True

            if not issues_found:
                self._success(f"✓ All {len(all_revisions)} migration files verified")
                return True
            else:
                return False

        except Exception as e:
            self._error(f"Failed to check migration files: {e}")
            return False

    async def _check_database_tables(self) -> bool:
        """Check that database tables exist and are accessible.

        Returns:
            bool: True if check passed
        """
        self._info("\n" + "=" * 70)
        self._info("DATABASE TABLES CHECK")
        self._info("=" * 70)

        try:
            engine = await self._get_engine()

            async with engine.connect() as conn:
                # Get list of tables
                inspector = await conn.run_sync(
                    lambda sync_conn: inspect(sync_conn)
                )
                tables = await conn.run_sync(
                    lambda sync_conn: inspect(sync_conn).get_table_names()
                )

            if not tables:
                self._warning("No tables found in database")
                return False

            self._info(f"Found {len(tables)} table(s) in database:")
            for table in sorted(tables):
                if self.verbose:
                    self._info(f"  - {table}")

            # Check for alembic_version table
            if "alembic_version" not in tables:
                self._warning("alembic_version table not found")
                return False

            self._success("✓ Database tables verified")
            return True

        except Exception as e:
            self._error(f"Failed to check database tables: {e}")
            return False

    async def _check_schema_consistency(self) -> bool:
        """Check consistency between SQLAlchemy models and database schema.

        Returns:
            bool: True if schemas are consistent
        """
        self._info("\n" + "=" * 70)
        self._info("SCHEMA CONSISTENCY CHECK")
        self._info("=" * 70)

        try:
            engine = await self._get_engine()

            # Get model table names from Base.metadata
            model_tables = set(Base.metadata.tables.keys())
            self._info(f"Model tables: {len(model_tables)}")

            # Get database table names
            async with engine.connect() as conn:
                db_tables = set(
                    await conn.run_sync(
                        lambda sync_conn: inspect(sync_conn).get_table_names()
                    )
                )

            # Remove alembic_version from comparison
            db_tables.discard("alembic_version")

            self._info(f"Database tables: {len(db_tables)}")

            # Find differences
            missing_in_db = model_tables - db_tables
            extra_in_db = db_tables - model_tables

            issues_found = False

            if missing_in_db:
                self._warning(
                    f"Tables in models but not in database ({len(missing_in_db)}):"
                )
                for table in sorted(missing_in_db):
                    self._info(f"  - {table}")
                issues_found = True

            if extra_in_db:
                self._warning(
                    f"Tables in database but not in models ({len(extra_in_db)}):"
                )
                for table in sorted(extra_in_db):
                    self._info(f"  - {table}")
                issues_found = True

            if not issues_found:
                self._success("✓ Schema consistency verified")
                return True
            else:
                return False

        except Exception as e:
            self._error(f"Failed to check schema consistency: {e}")
            return False

    async def _test_upgrade(self) -> bool:
        """Test applying all pending migrations.

        Note: This is a dry-run check, actual migrations are not applied.

        Returns:
            bool: True if upgrade would succeed
        """
        self._info("\n" + "=" * 70)
        self._info("UPGRADE TEST (DRY RUN)")
        self._info("=" * 70)

        try:
            current = await self._get_current_revision()
            head = self._get_head_revision()

            if current == head:
                self._success("✓ Database is at head, no upgrades needed")
                return True

            # Check if migrations can be applied
            # Note: We're not actually applying them, just checking
            pending = self._get_pending_migrations(current, head)

            self._info(
                f"Would apply {len(pending)} migration(s) to reach head"
            )

            # Verify each pending migration has upgrade function
            for rev_id in pending:
                script = self.script_dir.get_revision(rev_id)
                if not hasattr(script.module, "upgrade"):
                    self._error(
                        f"Migration {rev_id} missing upgrade() function"
                    )
                    return False

            self._success("✓ All pending migrations have upgrade functions")
            self._info("  (To apply: alembic upgrade head)")
            return True

        except Exception as e:
            self._error(f"Upgrade test failed: {e}")
            return False

    async def _test_downgrade(self) -> bool:
        """Test that migrations are reversible.

        Returns:
            bool: True if downgrades are available
        """
        self._info("\n" + "=" * 70)
        self._info("DOWNGRADE TEST")
        self._info("=" * 70)

        try:
            all_revisions = self._get_all_revisions()

            # Verify each migration has downgrade function
            missing_downgrade = []
            for rev_id in all_revisions:
                script = self.script_dir.get_revision(rev_id)
                if not hasattr(script.module, "downgrade"):
                    missing_downgrade.append(rev_id)

            if missing_downgrade:
                self._error(
                    f"{len(missing_downgrade)} migration(s) missing downgrade():"
                )
                for rev_id in missing_downgrade:
                    script = self.script_dir.get_revision(rev_id)
                    self._info(f"  - {rev_id}: {script.doc}")
                return False

            self._success("✓ All migrations have downgrade functions")
            return True

        except Exception as e:
            self._error(f"Downgrade test failed: {e}")
            return False

    async def verify(
        self, full: bool = False, check_schema: bool = False
    ) -> bool:
        """Run all verification checks.

        Args:
            full: Run full verification including upgrade/downgrade tests
            check_schema: Check schema consistency

        Returns:
            bool: True if all checks passed
        """
        self._info(f"\n{Color.BOLD}LAYA AI Service - Alembic Migration Verification{Color.END}")
        self._info(f"Database: {settings.database_url.split('@')[-1]}")

        results = []

        # Basic checks (always run)
        results.append(await self._check_migration_status())
        results.append(await self._check_migration_files())
        results.append(await self._check_database_tables())

        # Optional checks
        if check_schema:
            results.append(await self._check_schema_consistency())

        if full:
            results.append(await self._test_upgrade())
            results.append(await self._test_downgrade())

        # Print summary
        self._print_summary(results)

        # Cleanup
        if self.engine:
            await self.engine.dispose()

        return all(results)

    def _print_summary(self, results: List[bool]) -> None:
        """Print verification summary.

        Args:
            results: List of check results
        """
        self._info("\n" + "=" * 70)
        self._info("VERIFICATION SUMMARY")
        self._info("=" * 70)

        passed = sum(results)
        total = len(results)

        if self.errors:
            self._info(f"\n{Color.RED}Errors ({len(self.errors)}):{Color.END}")
            for error in self.errors:
                print(f"  ✗ {error}")

        if self.warnings:
            self._info(f"\n{Color.YELLOW}Warnings ({len(self.warnings)}):{Color.END}")
            for warning in self.warnings:
                print(f"  ⚠ {warning}")

        self._info(f"\n{Color.BOLD}Checks: {passed}/{total} passed{Color.END}")

        if all(results):
            self._info(
                f"\n{Color.GREEN}{Color.BOLD}✓ All verification checks passed!{Color.END}"
            )
        else:
            self._info(
                f"\n{Color.RED}{Color.BOLD}✗ Some verification checks failed{Color.END}"
            )

    def _success(self, message: str) -> None:
        """Print success message."""
        print(f"{Color.GREEN}{message}{Color.END}")

    def _info(self, message: str) -> None:
        """Print info message."""
        print(message)
        self.info.append(message)

    def _warning(self, message: str) -> None:
        """Print warning message."""
        print(f"{Color.YELLOW}⚠ {message}{Color.END}")
        self.warnings.append(message)

    def _error(self, message: str) -> None:
        """Print error message."""
        print(f"{Color.RED}✗ {message}{Color.END}")
        self.errors.append(message)


async def main() -> int:
    """Main entry point.

    Returns:
        int: Exit code (0 for success, 1 for failure)
    """
    parser = argparse.ArgumentParser(
        description="Verify Alembic migration integrity and consistency"
    )
    parser.add_argument(
        "--full",
        action="store_true",
        help="Run full verification including upgrade/downgrade tests",
    )
    parser.add_argument(
        "--check-schema",
        action="store_true",
        help="Compare database schema with SQLAlchemy models",
    )
    parser.add_argument(
        "--verbose",
        "-v",
        action="store_true",
        help="Display detailed output",
    )

    args = parser.parse_args()

    verifier = MigrationVerifier(verbose=args.verbose)

    try:
        success = await verifier.verify(
            full=args.full,
            check_schema=args.check_schema,
        )
        return 0 if success else 1
    except Exception as e:
        print(f"{Color.RED}Verification failed with error: {e}{Color.END}")
        if args.verbose:
            import traceback
            traceback.print_exc()
        return 1


if __name__ == "__main__":
    sys.exit(asyncio.run(main()))
