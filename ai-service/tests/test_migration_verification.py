"""Tests for Alembic migration verification script.

This test suite verifies that the migration verification script works correctly
and can detect various migration issues.
"""

import asyncio
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, Mock, patch

import pytest
from alembic.config import Config
from alembic.script import ScriptDirectory

from scripts.verify_migrations import MigrationVerifier


@pytest.fixture
def verifier():
    """Create a MigrationVerifier instance for testing."""
    with patch("scripts.verify_migrations.settings") as mock_settings:
        mock_settings.database_url = "postgresql+asyncpg://test:test@localhost/test"
        return MigrationVerifier(verbose=False)


@pytest.fixture
def mock_alembic_config(tmp_path):
    """Create a mock Alembic configuration."""
    # Create temporary alembic.ini
    alembic_ini = tmp_path / "alembic.ini"
    alembic_ini.write_text(
        """
[alembic]
script_location = alembic
sqlalchemy.url = postgresql://test:test@localhost/test
"""
    )

    return Config(str(alembic_ini))


class TestMigrationVerifier:
    """Test suite for MigrationVerifier class."""

    def test_initialization(self, verifier):
        """Test verifier initializes correctly."""
        assert verifier.verbose is False
        assert verifier.errors == []
        assert verifier.warnings == []
        assert verifier.info == []
        assert verifier.engine is None

    def test_verbose_mode(self):
        """Test verifier can be initialized in verbose mode."""
        with patch("scripts.verify_migrations.settings") as mock_settings:
            mock_settings.database_url = (
                "postgresql+asyncpg://test:test@localhost/test"
            )
            verifier = MigrationVerifier(verbose=True)
            assert verifier.verbose is True

    @pytest.mark.asyncio
    async def test_get_engine(self, verifier):
        """Test async engine creation."""
        with patch(
            "scripts.verify_migrations.create_async_engine"
        ) as mock_create:
            mock_engine = AsyncMock()
            mock_create.return_value = mock_engine

            engine = await verifier._get_engine()

            assert engine is mock_engine
            assert verifier.engine is mock_engine
            mock_create.assert_called_once()

            # Second call should return same engine
            engine2 = await verifier._get_engine()
            assert engine2 is mock_engine
            assert mock_create.call_count == 1

    def test_get_head_revision(self, verifier):
        """Test getting head revision from scripts."""
        mock_script_dir = Mock()
        mock_script_dir.get_current_head.return_value = "abc123"
        verifier.script_dir = mock_script_dir

        head = verifier._get_head_revision()

        assert head == "abc123"
        mock_script_dir.get_current_head.assert_called_once()

    def test_get_all_revisions(self, verifier):
        """Test getting all revision IDs."""
        mock_rev1 = Mock()
        mock_rev1.revision = "rev1"
        mock_rev2 = Mock()
        mock_rev2.revision = "rev2"
        mock_rev3 = Mock()
        mock_rev3.revision = "rev3"

        mock_script_dir = Mock()
        mock_script_dir.walk_revisions.return_value = [
            mock_rev1,
            mock_rev2,
            mock_rev3,
        ]
        verifier.script_dir = mock_script_dir

        revisions = verifier._get_all_revisions()

        assert revisions == ["rev1", "rev2", "rev3"]
        mock_script_dir.walk_revisions.assert_called_once()

    def test_get_pending_migrations_none_applied(self, verifier):
        """Test getting pending migrations when none are applied."""
        mock_script_dir = Mock()
        mock_rev1 = Mock()
        mock_rev1.revision = "rev1"
        mock_rev2 = Mock()
        mock_rev2.revision = "rev2"
        mock_script_dir.walk_revisions.return_value = [mock_rev1, mock_rev2]
        verifier.script_dir = mock_script_dir

        pending = verifier._get_pending_migrations(None, "rev2")

        # When current is None, all revisions are pending
        assert "rev1" in pending
        assert "rev2" in pending

    def test_get_pending_migrations_up_to_date(self, verifier):
        """Test getting pending migrations when database is up to date."""
        pending = verifier._get_pending_migrations("rev2", "rev2")
        assert pending == []

    def test_get_pending_migrations_behind(self, verifier):
        """Test getting pending migrations when database is behind."""
        mock_rev1 = Mock()
        mock_rev1.revision = "rev1"
        mock_rev2 = Mock()
        mock_rev2.revision = "rev2"
        mock_rev3 = Mock()
        mock_rev3.revision = "rev3"

        mock_script_dir = Mock()
        mock_script_dir.iterate_revisions.return_value = [mock_rev3, mock_rev2]
        verifier.script_dir = mock_script_dir

        pending = verifier._get_pending_migrations("rev1", "rev3")

        assert "rev2" in pending
        assert "rev3" in pending
        assert "rev1" not in pending

    @pytest.mark.asyncio
    async def test_check_migration_status_up_to_date(self, verifier):
        """Test migration status check when database is up to date."""
        verifier._get_current_revision = AsyncMock(return_value="rev1")
        verifier._get_head_revision = Mock(return_value="rev1")

        result = await verifier._check_migration_status()

        assert result is True
        assert len(verifier.errors) == 0

    @pytest.mark.asyncio
    async def test_check_migration_status_behind(self, verifier):
        """Test migration status check when database is behind."""
        verifier._get_current_revision = AsyncMock(return_value="rev1")
        verifier._get_head_revision = Mock(return_value="rev2")
        verifier._get_pending_migrations = Mock(return_value=["rev2"])

        mock_script = Mock()
        mock_script.doc = "Add new table"
        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_script
        verifier.script_dir = mock_script_dir

        result = await verifier._check_migration_status()

        assert result is False
        assert len(verifier.warnings) > 0

    @pytest.mark.asyncio
    async def test_check_migration_status_no_migrations(self, verifier):
        """Test migration status check when no migrations applied."""
        verifier._get_current_revision = AsyncMock(return_value=None)
        verifier._get_head_revision = Mock(return_value="rev1")

        result = await verifier._check_migration_status()

        assert result is False
        assert len(verifier.warnings) > 0

    @pytest.mark.asyncio
    async def test_check_migration_files_valid(self, verifier):
        """Test migration files check with valid files."""
        mock_rev1 = Mock()
        mock_rev1.revision = "rev1"
        mock_rev1.doc = "Add table"
        mock_rev1.module = Mock()
        mock_rev1.module.__file__ = __file__  # Use this file as test

        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_rev1
        verifier.script_dir = mock_script_dir
        verifier._get_all_revisions = Mock(return_value=["rev1"])

        result = await verifier._check_migration_files()

        assert result is True
        assert len(verifier.errors) == 0

    @pytest.mark.asyncio
    async def test_check_migration_files_duplicate_revisions(self, verifier):
        """Test migration files check detects duplicate revisions."""
        verifier._get_all_revisions = Mock(
            return_value=["rev1", "rev2", "rev1"]
        )

        result = await verifier._check_migration_files()

        assert result is False
        assert len(verifier.errors) > 0

    @pytest.mark.asyncio
    async def test_check_database_tables(self, verifier):
        """Test database tables check."""
        from unittest.mock import MagicMock

        mock_engine = AsyncMock()
        mock_conn = MagicMock()
        mock_conn.run_sync = AsyncMock(
            return_value=["table1", "table2", "alembic_version"]
        )

        # Properly mock async context manager
        mock_cm = MagicMock()
        mock_cm.__aenter__ = AsyncMock(return_value=mock_conn)
        mock_cm.__aexit__ = AsyncMock(return_value=None)
        mock_engine.connect = MagicMock(return_value=mock_cm)

        verifier._get_engine = AsyncMock(return_value=mock_engine)

        result = await verifier._check_database_tables()

        assert result is True
        assert len(verifier.errors) == 0

    @pytest.mark.asyncio
    async def test_check_database_tables_no_alembic_version(self, verifier):
        """Test database tables check when alembic_version is missing."""
        from unittest.mock import MagicMock

        mock_engine = AsyncMock()
        mock_conn = MagicMock()
        mock_conn.run_sync = AsyncMock(return_value=["table1", "table2"])

        # Properly mock async context manager
        mock_cm = MagicMock()
        mock_cm.__aenter__ = AsyncMock(return_value=mock_conn)
        mock_cm.__aexit__ = AsyncMock(return_value=None)
        mock_engine.connect = MagicMock(return_value=mock_cm)

        verifier._get_engine = AsyncMock(return_value=mock_engine)

        result = await verifier._check_database_tables()

        assert result is False
        assert len(verifier.warnings) > 0

    @pytest.mark.asyncio
    async def test_check_schema_consistency_match(self, verifier):
        """Test schema consistency check when schemas match."""
        from unittest.mock import MagicMock

        mock_engine = AsyncMock()
        mock_conn = MagicMock()
        mock_conn.run_sync = AsyncMock(
            return_value=["table1", "table2", "alembic_version"]
        )

        # Properly mock async context manager
        mock_cm = MagicMock()
        mock_cm.__aenter__ = AsyncMock(return_value=mock_conn)
        mock_cm.__aexit__ = AsyncMock(return_value=None)
        mock_engine.connect = MagicMock(return_value=mock_cm)

        verifier._get_engine = AsyncMock(return_value=mock_engine)

        # Mock Base.metadata.tables
        with patch("scripts.verify_migrations.Base") as mock_base:
            mock_base.metadata.tables.keys.return_value = ["table1", "table2"]

            result = await verifier._check_schema_consistency()

            assert result is True
            assert len(verifier.errors) == 0

    @pytest.mark.asyncio
    async def test_check_schema_consistency_mismatch(self, verifier):
        """Test schema consistency check when schemas don't match."""
        from unittest.mock import MagicMock

        mock_engine = AsyncMock()
        mock_conn = MagicMock()
        mock_conn.run_sync = AsyncMock(
            return_value=["table1", "alembic_version"]
        )

        # Properly mock async context manager
        mock_cm = MagicMock()
        mock_cm.__aenter__ = AsyncMock(return_value=mock_conn)
        mock_cm.__aexit__ = AsyncMock(return_value=None)
        mock_engine.connect = MagicMock(return_value=mock_cm)

        verifier._get_engine = AsyncMock(return_value=mock_engine)

        # Mock Base.metadata.tables
        with patch("scripts.verify_migrations.Base") as mock_base:
            mock_base.metadata.tables.keys.return_value = [
                "table1",
                "table2",
                "table3",
            ]

            result = await verifier._check_schema_consistency()

            assert result is False
            assert len(verifier.warnings) > 0

    @pytest.mark.asyncio
    async def test_test_upgrade_up_to_date(self, verifier):
        """Test upgrade test when database is up to date."""
        verifier._get_current_revision = AsyncMock(return_value="rev1")
        verifier._get_head_revision = Mock(return_value="rev1")

        result = await verifier._test_upgrade()

        assert result is True

    @pytest.mark.asyncio
    async def test_test_upgrade_pending(self, verifier):
        """Test upgrade test when there are pending migrations."""
        verifier._get_current_revision = AsyncMock(return_value="rev1")
        verifier._get_head_revision = Mock(return_value="rev2")
        verifier._get_pending_migrations = Mock(return_value=["rev2"])

        mock_script = Mock()
        mock_script.module = Mock()
        mock_script.module.upgrade = Mock()
        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_script
        verifier.script_dir = mock_script_dir

        result = await verifier._test_upgrade()

        assert result is True

    @pytest.mark.asyncio
    async def test_test_upgrade_missing_upgrade_function(self, verifier):
        """Test upgrade test detects missing upgrade function."""
        verifier._get_current_revision = AsyncMock(return_value="rev1")
        verifier._get_head_revision = Mock(return_value="rev2")
        verifier._get_pending_migrations = Mock(return_value=["rev2"])

        mock_script = Mock()
        mock_script.module = Mock(spec=[])  # No upgrade function
        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_script
        verifier.script_dir = mock_script_dir

        result = await verifier._test_upgrade()

        assert result is False
        assert len(verifier.errors) > 0

    @pytest.mark.asyncio
    async def test_test_downgrade_all_have_downgrade(self, verifier):
        """Test downgrade test when all migrations have downgrade."""
        verifier._get_all_revisions = Mock(return_value=["rev1", "rev2"])

        mock_script = Mock()
        mock_script.module = Mock()
        mock_script.module.downgrade = Mock()
        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_script
        verifier.script_dir = mock_script_dir

        result = await verifier._test_downgrade()

        assert result is True

    @pytest.mark.asyncio
    async def test_test_downgrade_missing_downgrade(self, verifier):
        """Test downgrade test detects missing downgrade function."""
        verifier._get_all_revisions = Mock(return_value=["rev1", "rev2"])

        mock_script = Mock()
        mock_script.module = Mock(spec=[])  # No downgrade function
        mock_script.doc = "Add table"
        mock_script_dir = Mock()
        mock_script_dir.get_revision.return_value = mock_script
        verifier.script_dir = mock_script_dir

        result = await verifier._test_downgrade()

        assert result is False
        assert len(verifier.errors) > 0

    @pytest.mark.asyncio
    async def test_verify_basic(self, verifier):
        """Test basic verification (no full or schema checks)."""
        verifier._check_migration_status = AsyncMock(return_value=True)
        verifier._check_migration_files = AsyncMock(return_value=True)
        verifier._check_database_tables = AsyncMock(return_value=True)
        verifier._get_engine = AsyncMock()
        verifier.engine = AsyncMock()

        result = await verifier.verify(full=False, check_schema=False)

        assert result is True
        verifier._check_migration_status.assert_called_once()
        verifier._check_migration_files.assert_called_once()
        verifier._check_database_tables.assert_called_once()

    @pytest.mark.asyncio
    async def test_verify_full(self, verifier):
        """Test full verification including upgrade/downgrade tests."""
        verifier._check_migration_status = AsyncMock(return_value=True)
        verifier._check_migration_files = AsyncMock(return_value=True)
        verifier._check_database_tables = AsyncMock(return_value=True)
        verifier._test_upgrade = AsyncMock(return_value=True)
        verifier._test_downgrade = AsyncMock(return_value=True)
        verifier._get_engine = AsyncMock()
        verifier.engine = AsyncMock()

        result = await verifier.verify(full=True, check_schema=False)

        assert result is True
        verifier._test_upgrade.assert_called_once()
        verifier._test_downgrade.assert_called_once()

    @pytest.mark.asyncio
    async def test_verify_with_schema(self, verifier):
        """Test verification with schema consistency check."""
        verifier._check_migration_status = AsyncMock(return_value=True)
        verifier._check_migration_files = AsyncMock(return_value=True)
        verifier._check_database_tables = AsyncMock(return_value=True)
        verifier._check_schema_consistency = AsyncMock(return_value=True)
        verifier._get_engine = AsyncMock()
        verifier.engine = AsyncMock()

        result = await verifier.verify(full=False, check_schema=True)

        assert result is True
        verifier._check_schema_consistency.assert_called_once()

    @pytest.mark.asyncio
    async def test_verify_with_failures(self, verifier):
        """Test verification when some checks fail."""
        verifier._check_migration_status = AsyncMock(return_value=True)
        verifier._check_migration_files = AsyncMock(return_value=False)
        verifier._check_database_tables = AsyncMock(return_value=True)
        verifier._get_engine = AsyncMock()
        verifier.engine = AsyncMock()

        result = await verifier.verify(full=False, check_schema=False)

        assert result is False

    def test_message_methods(self, verifier):
        """Test message logging methods."""
        verifier._success("Success message")
        verifier._info("Info message")
        verifier._warning("Warning message")
        verifier._error("Error message")

        assert len(verifier.info) > 0
        assert len(verifier.warnings) == 1
        assert len(verifier.errors) == 1
        assert verifier.warnings[0] == "Warning message"
        assert verifier.errors[0] == "Error message"


@pytest.mark.asyncio
async def test_main_success():
    """Test main function with successful verification."""
    with patch("scripts.verify_migrations.MigrationVerifier") as mock_verifier_class:
        mock_verifier = Mock()
        mock_verifier.verify = AsyncMock(return_value=True)
        mock_verifier_class.return_value = mock_verifier

        with patch("sys.argv", ["verify_migrations.py"]):
            from scripts.verify_migrations import main

            result = await main()

            assert result == 0


@pytest.mark.asyncio
async def test_main_failure():
    """Test main function with failed verification."""
    with patch("scripts.verify_migrations.MigrationVerifier") as mock_verifier_class:
        mock_verifier = Mock()
        mock_verifier.verify = AsyncMock(return_value=False)
        mock_verifier_class.return_value = mock_verifier

        with patch("sys.argv", ["verify_migrations.py"]):
            from scripts.verify_migrations import main

            result = await main()

            assert result == 1


@pytest.mark.asyncio
async def test_main_exception():
    """Test main function handles exceptions."""
    with patch("scripts.verify_migrations.MigrationVerifier") as mock_verifier_class:
        mock_verifier = Mock()
        mock_verifier.verify = AsyncMock(
            side_effect=Exception("Test error")
        )
        mock_verifier_class.return_value = mock_verifier

        with patch("sys.argv", ["verify_migrations.py"]):
            from scripts.verify_migrations import main

            result = await main()

            assert result == 1
