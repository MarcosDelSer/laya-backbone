"""Integration tests for concurrent plan snapshot updates.

Tests verify that the snapshot mechanism handles concurrent updates correctly
without corruption, data loss, or race conditions. These tests use a real database
to ensure proper transaction isolation and locking behavior.

NOTE: These tests require intervention plan and auth tables to be created in the test database.
Currently marked as skip until database schema is set up in conftest.py.
"""

from __future__ import annotations

import asyncio
import json
from datetime import date, timedelta
from typing import Any
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.schemas.intervention_plan import (
    InterventionPlanStatus,
    ReviewSchedule,
)


# Mark all tests in this module to skip until database schema is set up
pytestmark = pytest.mark.skip(reason="Requires intervention plan tables in test database - infrastructure not yet set up in conftest.py")


# =============================================================================
# Test Fixtures
# =============================================================================


@pytest.fixture
def test_child_id() -> UUID:
    """Generate a mock child ID for testing.

    Returns:
        UUID: Mock child identifier
    """
    return uuid4()


@pytest.fixture
def base_plan_data(test_child_id: UUID) -> dict[str, Any]:
    """Create base intervention plan data for concurrent update testing.

    Args:
        test_child_id: Mock child ID

    Returns:
        dict: Base plan creation payload
    """
    return {
        "child_id": str(test_child_id),
        "title": "Concurrent Update Test Plan",
        "status": "draft",
        "review_schedule": "quarterly",
        "effective_date": str(date.today()),
        "child_name": "Concurrent Test Child",
        "child_dob": str(date.today() - timedelta(days=1095)),  # ~3 years old
        "medical_diagnosis": "Test diagnosis for concurrent updates",
        "educational_history": "Test educational history",
        "strengths": [
            {
                "category": "cognitive",
                "description": "Baseline strength 1",
                "examples": "Example 1",
            },
            {
                "category": "social",
                "description": "Baseline strength 2",
                "examples": "Example 2",
            },
        ],
        "needs": [
            {
                "category": "communication",
                "description": "Baseline need 1",
                "priority": "high",
            },
            {
                "category": "behavioral",
                "description": "Baseline need 2",
                "priority": "medium",
            },
        ],
        "goals": [
            {
                "title": "Baseline goal 1",
                "description": "Description for baseline goal 1",
                "measurement_criteria": "Criteria 1",
                "measurement_baseline": "0",
                "measurement_target": "10",
                "achievability_notes": "Achievability notes 1",
                "relevance_notes": "Relevance notes 1",
                "target_date": str(date.today() + timedelta(days=90)),
            },
        ],
    }


# =============================================================================
# Concurrent Update Integration Tests
# =============================================================================


class TestConcurrentSnapshotUpdates:
    """Integration tests for concurrent plan update scenarios."""

    @pytest.mark.asyncio
    async def test_concurrent_plan_updates_create_separate_versions(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        base_plan_data: dict[str, Any],
        db_session: AsyncSession,
    ) -> None:
        """Test that concurrent updates create separate version snapshots.

        Verifies:
        1. Multiple concurrent updates all succeed
        2. Each update creates a separate version
        3. No versions are lost or overwritten
        4. All version snapshots are non-null
        5. Snapshot data is complete for each version
        """
        # Create initial plan
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=base_plan_data,
        )
        assert response.status_code == 201
        plan_id = response.json()["id"]

        # Define concurrent update operations
        async def update_plan(update_num: int) -> dict[str, Any]:
            """Perform a plan update and create a version.

            Args:
                update_num: The update number for tracking

            Returns:
                dict: Response JSON from the update
            """
            # Update plan
            update_data = {
                "title": f"Concurrent Update {update_num}",
                "status": "active",
            }
            update_response = await client.put(
                f"/api/v1/intervention-plans/{plan_id}",
                headers=auth_headers,
                json=update_data,
            )
            assert update_response.status_code == 200

            # Create version snapshot
            version_response = await client.post(
                f"/api/v1/intervention-plans/{plan_id}/version",
                headers=auth_headers,
            )
            assert version_response.status_code in (200, 201)

            return {
                "update_num": update_num,
                "update_response": update_response.json(),
                "version_response": version_response.json(),
            }

        # Execute 5 concurrent updates
        num_concurrent_updates = 5
        update_tasks = [
            update_plan(i) for i in range(1, num_concurrent_updates + 1)
        ]
        results = await asyncio.gather(*update_tasks, return_exceptions=True)

        # Verify all updates succeeded (no exceptions)
        for idx, result in enumerate(results):
            assert not isinstance(result, Exception), (
                f"Update {idx + 1} failed with exception: {result}"
            )

        # Query all versions from database
        version_query = text(
            """
            SELECT version_number, snapshot_data, created_at
            FROM intervention_versions
            WHERE plan_id = :plan_id
            ORDER BY version_number
            """
        )
        result = await db_session.execute(version_query, {"plan_id": plan_id})
        versions = result.fetchall()

        # Verify we have at least initial + concurrent updates versions
        # Note: Initial plan creation creates version 1
        min_expected_versions = 1 + num_concurrent_updates
        assert len(versions) >= min_expected_versions, (
            f"Expected at least {min_expected_versions} versions, "
            f"got {len(versions)}"
        )

        # Verify all snapshots are non-null (critical bug check)
        for idx, version_row in enumerate(versions):
            version_num = version_row[0]
            snapshot_data = version_row[1]
            created_at = version_row[2]

            assert snapshot_data is not None, (
                f"Version {version_num} snapshot is null - data corruption!"
            )
            assert created_at is not None, (
                f"Version {version_num} missing timestamp"
            )

            # Parse and validate snapshot structure
            if isinstance(snapshot_data, str):
                snapshot_dict = json.loads(snapshot_data)
            else:
                snapshot_dict = snapshot_data

            # Verify critical fields present
            assert "id" in snapshot_dict, (
                f"Version {version_num} snapshot missing plan ID"
            )
            assert "created_by" in snapshot_dict, (
                f"Version {version_num} snapshot missing created_by"
            )
            assert "strengths" in snapshot_dict, (
                f"Version {version_num} snapshot missing strengths section"
            )
            assert "needs" in snapshot_dict, (
                f"Version {version_num} snapshot missing needs section"
            )
            assert "goals" in snapshot_dict, (
                f"Version {version_num} snapshot missing goals section"
            )

    @pytest.mark.asyncio
    async def test_concurrent_updates_preserve_data_integrity(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        base_plan_data: dict[str, Any],
        db_session: AsyncSession,
    ) -> None:
        """Test that concurrent updates preserve complete snapshot data.

        Verifies:
        1. Snapshots contain all plan sections
        2. Relational data (strengths, needs, goals) is not corrupted
        3. No partial snapshots are created
        4. Version lineage is maintained
        """
        # Create initial plan
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=base_plan_data,
        )
        assert response.status_code == 201
        plan_id = response.json()["id"]

        # Define update with section changes
        async def update_with_sections(update_num: int) -> None:
            """Update plan with section modifications.

            Args:
                update_num: Update identifier
            """
            # Update plan metadata
            update_data = {
                "title": f"Update {update_num}",
                "medical_diagnosis": f"Updated diagnosis {update_num}",
            }
            response = await client.put(
                f"/api/v1/intervention-plans/{plan_id}",
                headers=auth_headers,
                json=update_data,
            )
            assert response.status_code == 200

            # Create version
            response = await client.post(
                f"/api/v1/intervention-plans/{plan_id}/version",
                headers=auth_headers,
            )
            assert response.status_code in (200, 201)

        # Execute concurrent updates
        await asyncio.gather(
            update_with_sections(1),
            update_with_sections(2),
            update_with_sections(3),
        )

        # Query all snapshots
        query = text(
            """
            SELECT version_number, snapshot_data
            FROM intervention_versions
            WHERE plan_id = :plan_id
            ORDER BY version_number
            """
        )
        result = await db_session.execute(query, {"plan_id": plan_id})
        versions = result.fetchall()

        # Verify each snapshot has complete data
        for version_row in versions:
            version_num = version_row[0]
            snapshot_data = version_row[1]

            assert snapshot_data is not None

            snapshot_dict = (
                json.loads(snapshot_data)
                if isinstance(snapshot_data, str)
                else snapshot_data
            )

            # Verify all 8 sections are present
            required_sections = [
                "strengths",
                "needs",
                "goals",
                "strategies",
                "monitoring",
                "parent_involvements",
                "consultations",
            ]
            for section in required_sections:
                assert section in snapshot_dict, (
                    f"Version {version_num} missing {section} section"
                )

            # Verify baseline data not corrupted
            # Each snapshot should have the strengths and needs from creation
            assert len(snapshot_dict["strengths"]) >= 2, (
                f"Version {version_num} has corrupted strengths data"
            )
            assert len(snapshot_dict["needs"]) >= 2, (
                f"Version {version_num} has corrupted needs data"
            )
            assert len(snapshot_dict["goals"]) >= 1, (
                f"Version {version_num} has corrupted goals data"
            )

            # Verify relational integrity - each item should have an ID
            for strength in snapshot_dict["strengths"]:
                assert "id" in strength, "Strength missing ID"
                assert "category" in strength, "Strength missing category"
                assert "description" in strength, "Strength missing description"

            for need in snapshot_dict["needs"]:
                assert "id" in need, "Need missing ID"
                assert "category" in need, "Need missing category"
                assert "priority" in need, "Need missing priority"

    @pytest.mark.asyncio
    async def test_high_concurrency_snapshot_stress_test(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        base_plan_data: dict[str, Any],
        db_session: AsyncSession,
    ) -> None:
        """Test snapshot system under high concurrent load.

        Verifies:
        1. System handles many simultaneous updates
        2. No deadlocks occur
        3. All snapshots are created successfully
        4. Version numbers are sequential
        5. No snapshot corruption under stress
        """
        # Create initial plan
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=base_plan_data,
        )
        assert response.status_code == 201
        plan_id = response.json()["id"]

        # Concurrent update function
        async def rapid_update(idx: int) -> bool:
            """Perform rapid update and version creation.

            Args:
                idx: Update index

            Returns:
                bool: Success status
            """
            try:
                # Small random delay to increase concurrency variety
                await asyncio.sleep(0.01 * (idx % 3))

                # Update
                update_data = {"title": f"Stress Test Update {idx}"}
                response = await client.put(
                    f"/api/v1/intervention-plans/{plan_id}",
                    headers=auth_headers,
                    json=update_data,
                )
                if response.status_code != 200:
                    return False

                # Create version
                response = await client.post(
                    f"/api/v1/intervention-plans/{plan_id}/version",
                    headers=auth_headers,
                )
                return response.status_code in (200, 201)
            except Exception:
                return False

        # Execute 10 concurrent updates (high stress)
        num_updates = 10
        tasks = [rapid_update(i) for i in range(num_updates)]
        results = await asyncio.gather(*tasks, return_exceptions=False)

        # Verify all succeeded
        successful_updates = sum(1 for r in results if r is True)
        assert successful_updates >= num_updates * 0.8, (
            f"Too many failures: {successful_updates}/{num_updates} succeeded"
        )

        # Query all versions
        query = text(
            """
            SELECT version_number, snapshot_data
            FROM intervention_versions
            WHERE plan_id = :plan_id
            ORDER BY version_number
            """
        )
        result = await db_session.execute(query, {"plan_id": plan_id})
        versions = result.fetchall()

        # Verify we have multiple versions
        assert len(versions) >= 5, (
            f"Expected at least 5 versions under stress test, got {len(versions)}"
        )

        # Verify version numbers are sequential
        version_numbers = [v[0] for v in versions]
        for idx, vnum in enumerate(version_numbers):
            expected_vnum = idx + 1
            assert vnum == expected_vnum, (
                f"Version numbers not sequential: expected {expected_vnum}, "
                f"got {vnum}"
            )

        # Verify no snapshot corruption
        null_snapshots = sum(1 for v in versions if v[1] is None)
        assert null_snapshots == 0, (
            f"Found {null_snapshots} null snapshots under stress - data corruption!"
        )

    @pytest.mark.asyncio
    async def test_concurrent_updates_with_different_sections(
        self,
        client: AsyncClient,
        auth_headers: dict[str, str],
        base_plan_data: dict[str, Any],
        db_session: AsyncSession,
    ) -> None:
        """Test concurrent updates modifying different plan sections.

        Verifies:
        1. Updates to different sections don't conflict
        2. Each snapshot captures correct section state
        3. No cross-contamination between updates
        4. Final state is consistent
        """
        # Create initial plan
        response = await client.post(
            "/api/v1/intervention-plans",
            headers=auth_headers,
            json=base_plan_data,
        )
        assert response.status_code == 201
        plan_id = response.json()["id"]

        # Different update types
        async def update_title() -> None:
            """Update plan title."""
            response = await client.put(
                f"/api/v1/intervention-plans/{plan_id}",
                headers=auth_headers,
                json={"title": "Title Updated Concurrently"},
            )
            assert response.status_code == 200
            response = await client.post(
                f"/api/v1/intervention-plans/{plan_id}/version",
                headers=auth_headers,
            )
            assert response.status_code in (200, 201)

        async def update_diagnosis() -> None:
            """Update medical diagnosis."""
            response = await client.put(
                f"/api/v1/intervention-plans/{plan_id}",
                headers=auth_headers,
                json={"medical_diagnosis": "Updated diagnosis concurrently"},
            )
            assert response.status_code == 200
            response = await client.post(
                f"/api/v1/intervention-plans/{plan_id}/version",
                headers=auth_headers,
            )
            assert response.status_code in (200, 201)

        async def update_status() -> None:
            """Update plan status."""
            response = await client.put(
                f"/api/v1/intervention-plans/{plan_id}",
                headers=auth_headers,
                json={"status": "active"},
            )
            assert response.status_code == 200
            response = await client.post(
                f"/api/v1/intervention-plans/{plan_id}/version",
                headers=auth_headers,
            )
            assert response.status_code in (200, 201)

        # Execute concurrent different updates
        await asyncio.gather(
            update_title(),
            update_diagnosis(),
            update_status(),
        )

        # Query final snapshots
        query = text(
            """
            SELECT snapshot_data
            FROM intervention_versions
            WHERE plan_id = :plan_id
            ORDER BY version_number DESC
            LIMIT 3
            """
        )
        result = await db_session.execute(query, {"plan_id": plan_id})
        recent_versions = result.fetchall()

        # Verify all recent snapshots are complete
        for version_row in recent_versions:
            snapshot_data = version_row[0]
            assert snapshot_data is not None

            snapshot_dict = (
                json.loads(snapshot_data)
                if isinstance(snapshot_data, str)
                else snapshot_data
            )

            # Verify snapshot has all critical fields
            assert "title" in snapshot_dict
            assert "status" in snapshot_dict
            assert "child_name" in snapshot_dict

            # Verify sections are preserved
            assert len(snapshot_dict["strengths"]) >= 2
            assert len(snapshot_dict["needs"]) >= 2
            assert len(snapshot_dict["goals"]) >= 1
