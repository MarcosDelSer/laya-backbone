"""Unit tests for coaching functionality.

Tests for citation validation, disclaimer presence, special need type coverage,
medical question detection, session persistence, and error handling.
"""

from __future__ import annotations

from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import UUID, uuid4

import pytest
import pytest_asyncio

from app.schemas.coaching import (
    CoachingGuidanceRequest,
    EvidenceSourceSchema,
    SpecialNeedType,
)
from app.services.coaching_service import (
    SAFETY_DISCLAIMER,
    CoachingService,
    NoSourcesFoundError,
)


@pytest.fixture
def mock_db_session() -> AsyncMock:
    """Create a mock async database session.

    Returns:
        AsyncMock: Mock database session with async methods
    """
    session = AsyncMock()
    session.add = MagicMock()
    session.flush = AsyncMock()
    session.commit = AsyncMock()
    session.rollback = AsyncMock()
    return session


@pytest.fixture
def mock_user() -> dict[str, Any]:
    """Create a mock user payload.

    Returns:
        dict: Mock user data with sub (user_id) field
    """
    return {
        "sub": str(uuid4()),
        "email": "test@example.com",
        "role": "educator",
    }


@pytest.fixture
def mock_child_id() -> UUID:
    """Generate a mock child ID.

    Returns:
        UUID: Mock child identifier
    """
    return uuid4()


@pytest.fixture
def coaching_service(mock_db_session: AsyncMock) -> CoachingService:
    """Create a CoachingService instance with mock database.

    Args:
        mock_db_session: Mock database session fixture

    Returns:
        CoachingService: Service instance for testing
    """
    return CoachingService(mock_db_session)


@pytest_asyncio.fixture
async def guidance_request(mock_child_id: UUID) -> CoachingGuidanceRequest:
    """Create a standard guidance request for testing.

    Args:
        mock_child_id: Mock child ID fixture

    Returns:
        CoachingGuidanceRequest: Valid guidance request
    """
    return CoachingGuidanceRequest(
        child_id=mock_child_id,
        special_need_types=[SpecialNeedType.AUTISM],
        situation_description="Child has difficulty with transitions",
        max_recommendations=3,
    )


@pytest.mark.asyncio
async def test_guidance_includes_citations(
    coaching_service: CoachingService,
    guidance_request: CoachingGuidanceRequest,
    mock_user: dict[str, Any],
) -> None:
    """Test that every guidance response includes non-empty citations array.

    Verifies that the coaching service always returns evidence-based
    recommendations with supporting citations from peer-reviewed or
    official sources.
    """
    response = await coaching_service.generate_guidance(guidance_request, mock_user)

    assert response.citations is not None, "Citations should not be None"
    assert len(response.citations) > 0, "Citations array should not be empty"
    assert isinstance(response.citations, list), "Citations should be a list"

    # Verify each citation is properly structured
    for citation in response.citations:
        assert isinstance(
            citation, EvidenceSourceSchema
        ), "Each citation should be EvidenceSourceSchema"


@pytest.mark.asyncio
async def test_guidance_includes_disclaimer(
    coaching_service: CoachingService,
    guidance_request: CoachingGuidanceRequest,
    mock_user: dict[str, Any],
) -> None:
    """Test that every guidance response includes a safety disclaimer.

    Verifies that all responses contain appropriate safety disclaimers
    directing users to consult qualified professionals.
    """
    response = await coaching_service.generate_guidance(guidance_request, mock_user)

    assert response.disclaimer is not None, "Disclaimer should not be None"
    assert len(response.disclaimer) > 0, "Disclaimer should not be empty"
    assert response.disclaimer == SAFETY_DISCLAIMER, (
        "Disclaimer should match SAFETY_DISCLAIMER constant"
    )

    # Verify disclaimer contains key safety elements
    assert "professional" in response.disclaimer.lower(), (
        "Disclaimer should mention professionals"
    )
    assert "medical" in response.disclaimer.lower() or "advice" in response.disclaimer.lower(), (
        "Disclaimer should address medical/advice limitations"
    )


@pytest.mark.asyncio
async def test_all_special_need_types(
    mock_db_session: AsyncMock,
    mock_user: dict[str, Any],
) -> None:
    """Test that valid responses are generated for each SpecialNeedType enum value.

    Verifies that the coaching system can provide guidance for all
    11 supported special need types: AUTISM, ADHD, DYSLEXIA, SPEECH_DELAY,
    MOTOR_DELAY, SENSORY_PROCESSING, BEHAVIORAL, COGNITIVE_DELAY,
    VISUAL_IMPAIRMENT, HEARING_IMPAIRMENT, and OTHER.
    """
    service = CoachingService(mock_db_session)
    child_id = uuid4()

    # Test each special need type
    for need_type in SpecialNeedType:
        request = CoachingGuidanceRequest(
            child_id=child_id,
            special_need_types=[need_type],
            situation_description=f"General support for {need_type.value}",
            max_recommendations=2,
        )

        response = await service.generate_guidance(request, mock_user)

        # Verify valid response for this need type
        assert response is not None, f"Response for {need_type.value} should not be None"
        assert response.child_id == child_id, (
            f"Child ID should match for {need_type.value}"
        )
        assert len(response.guidance_items) > 0, (
            f"Should return guidance for {need_type.value}"
        )
        assert len(response.citations) > 0, (
            f"Should include citations for {need_type.value}"
        )
        assert response.disclaimer is not None, (
            f"Should include disclaimer for {need_type.value}"
        )


@pytest.mark.asyncio
async def test_medical_question_detection(
    coaching_service: CoachingService,
    mock_child_id: UUID,
    mock_user: dict[str, Any],
) -> None:
    """Test that medical questions return professional referral instead of guidance.

    Verifies that questions requiring medical expertise (containing keywords
    like medication, diagnosis, treatment) receive professional referral
    responses directing users to appropriate specialists.
    """
    medical_questions = [
        "What medication should I give for ADHD symptoms?",
        "Can you help me diagnose my child's condition?",
        "What treatment options are available for autism?",
        "Should I give a higher dose of the medicine?",
        "What are the side effects of this drug?",
    ]

    for question in medical_questions:
        request = CoachingGuidanceRequest(
            child_id=mock_child_id,
            special_need_types=[SpecialNeedType.ADHD],
            situation_description=question,
            max_recommendations=3,
        )

        response = await coaching_service.generate_guidance(request, mock_user)

        # Verify professional referral response
        assert len(response.guidance_items) == 1, (
            f"Medical question should return single referral item for: {question}"
        )
        assert "Professional Referral" in response.guidance_items[0].coaching.title, (
            f"Should be professional referral for: {question}"
        )
        assert response.disclaimer is not None, (
            f"Should include disclaimer for medical question: {question}"
        )
        assert len(response.citations) > 0, (
            f"Professional referral should include citation for: {question}"
        )


@pytest.mark.asyncio
async def test_session_persistence(
    mock_db_session: AsyncMock,
    guidance_request: CoachingGuidanceRequest,
    mock_user: dict[str, Any],
) -> None:
    """Test that guidance requests create database records.

    Verifies that each coaching guidance request properly persists
    a coaching session record with linked recommendations to the database.
    """
    service = CoachingService(mock_db_session)

    await service.generate_guidance(guidance_request, mock_user)

    # Verify database operations were called
    assert mock_db_session.add.called, "Should add records to database session"
    assert mock_db_session.flush.called, "Should flush records to get IDs"
    assert mock_db_session.commit.called, "Should commit the transaction"

    # Verify multiple records were added (session + recommendations + evidence)
    add_call_count = mock_db_session.add.call_count
    assert add_call_count >= 2, (
        f"Should add at least session and recommendations, got {add_call_count} calls"
    )


@pytest.mark.asyncio
async def test_citation_validation(
    coaching_service: CoachingService,
    guidance_request: CoachingGuidanceRequest,
    mock_user: dict[str, Any],
) -> None:
    """Test that citations include required fields.

    Verifies that each citation includes source_type, title, and at least
    one identifier (DOI, URL, or ISBN) as per the citation quality requirements.
    """
    response = await coaching_service.generate_guidance(guidance_request, mock_user)

    assert len(response.citations) > 0, "Should have at least one citation"

    for citation in response.citations:
        # Required fields
        assert citation.title is not None, "Citation must have title"
        assert len(citation.title) > 0, "Citation title must not be empty"
        assert citation.source_type is not None, "Citation must have source_type"
        assert len(citation.source_type) > 0, "Citation source_type must not be empty"

        # At least one identifier required (DOI, URL, or publication info)
        has_identifier = (
            (citation.doi is not None and len(citation.doi) > 0)
            or (citation.url is not None and len(citation.url) > 0)
            or (citation.publication_year is not None)
        )
        assert has_identifier, (
            f"Citation '{citation.title}' must have at least one identifier "
            "(DOI, URL, or publication_year)"
        )


@pytest.mark.asyncio
async def test_empty_sources_error(
    mock_db_session: AsyncMock,
    mock_child_id: UUID,
    mock_user: dict[str, Any],
) -> None:
    """Test that error is returned when no matching sources are found.

    Verifies that the system raises NoSourcesFoundError when it cannot
    find any evidence sources to support guidance, ensuring no uncited
    recommendations are ever returned.
    """
    service = CoachingService(mock_db_session)

    # Patch the internal method to return empty citations
    with patch.object(
        service,
        "_retrieve_evidence_based_guidance",
        new_callable=AsyncMock,
        return_value=([], []),  # Empty guidance and citations
    ):
        request = CoachingGuidanceRequest(
            child_id=mock_child_id,
            special_need_types=[SpecialNeedType.AUTISM],
            situation_description="Some unusual situation with no matching sources",
            max_recommendations=3,
        )

        with pytest.raises(NoSourcesFoundError) as exc_info:
            await service.generate_guidance(request, mock_user)

        # Verify error message
        assert "no matching evidence sources" in str(exc_info.value).lower(), (
            "Error message should indicate no sources found"
        )
