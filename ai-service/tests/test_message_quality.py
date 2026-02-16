"""Unit tests for message quality analysis functionality.

Tests for MessageQualityService analyzing various message patterns including
accusatory language, judgmental labels, positive communication, and
bilingual (English/French) support per Quebec requirements.

Tests cover:
- Detection of accusatory 'you' language patterns
- Detection of judgmental labels and blame/shame patterns
- Recognition of positive, well-structured messages
- Quality score calculation and validation
- Rewrite suggestion generation with 'I' language
- Sandwich method structure validation
- Bilingual support (English/French)
- Error handling for invalid inputs
"""

from __future__ import annotations

from typing import Any
from unittest.mock import AsyncMock, MagicMock
from uuid import UUID, uuid4

import pytest
import pytest_asyncio

from app.schemas.message_quality import (
    IssueSeverity,
    Language,
    MessageAnalysisRequest,
    MessageContext,
    QualityIssue,
    QualityIssueDetail,
)
from app.services.message_quality_service import (
    InvalidMessageError,
    MessageQualityService,
)


# =============================================================================
# Fixtures
# =============================================================================


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
    session.refresh = AsyncMock()
    return session


@pytest.fixture
def mock_user() -> dict[str, Any]:
    """Create a mock user payload.

    Returns:
        dict: Mock user data with sub (user_id) field
    """
    return {
        "sub": str(uuid4()),
        "email": "educator@example.com",
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
def message_quality_service(mock_db_session: AsyncMock) -> MessageQualityService:
    """Create a MessageQualityService instance with mock database.

    Args:
        mock_db_session: Mock database session fixture

    Returns:
        MessageQualityService: Service instance for testing
    """
    return MessageQualityService(mock_db_session)


# =============================================================================
# Sample Message Fixtures - Accusatory Patterns
# =============================================================================


@pytest.fixture
def accusatory_message_en() -> str:
    """Sample English message with accusatory 'you' language."""
    return "You never send the snacks on time. You need to be more responsible."


@pytest.fixture
def accusatory_message_fr() -> str:
    """Sample French message with accusatory 'vous' language."""
    return "Vous n'avez jamais envoyé les collations à temps. Vous devez être plus responsable."


@pytest.fixture
def judgmental_message_en() -> str:
    """Sample English message with judgmental labels."""
    return "Your child is a difficult kid. He is always disruptive during circle time."


@pytest.fixture
def judgmental_message_fr() -> str:
    """Sample French message with judgmental labels."""
    return "Votre enfant est un enfant difficile. Il est toujours perturbateur pendant le cercle."


@pytest.fixture
def blame_shame_message_en() -> str:
    """Sample English message with blame/shame patterns."""
    return "It's your fault that your child acts this way. If only you had taught better manners at home."


@pytest.fixture
def blame_shame_message_fr() -> str:
    """Sample French message with blame/shame patterns."""
    return "C'est votre faute si votre enfant agit ainsi. Si seulement vous aviez enseigné de meilleures manières."


@pytest.fixture
def exaggeration_message_en() -> str:
    """Sample English message with exaggeration patterns."""
    return "Your child always disrupts the class and never listens to instructions every single time."


@pytest.fixture
def exaggeration_message_fr() -> str:
    """Sample French message with exaggeration patterns."""
    return "Votre enfant perturbe toujours la classe et n'écoute jamais les instructions chaque fois."


@pytest.fixture
def alarmist_message_en() -> str:
    """Sample English message with alarmist language."""
    return "This is urgent! I am very worried about your child. This is a serious concern that needs immediate attention."


@pytest.fixture
def alarmist_message_fr() -> str:
    """Sample French message with alarmist language."""
    return "C'est urgent! Je suis très inquiet au sujet de votre enfant. C'est une grave préoccupation qui nécessite une attention immédiate."


@pytest.fixture
def comparison_message_en() -> str:
    """Sample English message with inappropriate comparisons."""
    return "Unlike other children, your child can't follow simple instructions. Most children at this age can do this easily."


@pytest.fixture
def comparison_message_fr() -> str:
    """Sample French message with inappropriate comparisons."""
    return "Contrairement aux autres enfants, votre enfant ne peut pas suivre des instructions simples. La plupart des enfants de son âge peuvent le faire facilement."


# =============================================================================
# Sample Message Fixtures - Positive Patterns
# =============================================================================


@pytest.fixture
def positive_message_en() -> str:
    """Sample English message following 'Bonne Message' standards."""
    return (
        "I wanted to share how much we've enjoyed having Emma in our class this week. "
        "I noticed that she sometimes needs extra time transitioning between activities. "
        "I'd love to discuss strategies we could try together. "
        "Please feel free to reach out so we can talk more about this."
    )


@pytest.fixture
def positive_message_fr() -> str:
    """Sample French message following 'Bonne Message' standards."""
    return (
        "Je voulais partager combien nous avons apprécié avoir Emma dans notre classe cette semaine. "
        "J'ai remarqué qu'elle a parfois besoin de plus de temps pour les transitions entre les activités. "
        "J'aimerais en discuter des stratégies que nous pourrions essayer ensemble. "
        "N'hésitez pas à me contacter pour en parler davantage."
    )


@pytest.fixture
def short_positive_message_en() -> str:
    """Short positive message in English."""
    return "Thank you for the lovely snacks today! Emma had a great day."


@pytest.fixture
def short_positive_message_fr() -> str:
    """Short positive message in French."""
    return "Merci pour les délicieuses collations aujourd'hui! Emma a passé une excellente journée."


# =============================================================================
# Tests - Accusatory Language Detection
# =============================================================================


class TestAccusatoryLanguageDetection:
    """Tests for detection of accusatory 'you' language patterns."""

    @pytest.mark.asyncio
    async def test_detects_accusatory_you_in_english(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that accusatory 'you' language is detected in English messages.

        Verifies that phrases like 'You never', 'You need to' trigger
        the ACCUSATORY_YOU quality issue detection.
        """
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect accusatory language
        accusatory_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.ACCUSATORY_YOU
        ]
        assert len(accusatory_issues) > 0, (
            "Should detect accusatory 'you' language in English"
        )

        # Verify severity is HIGH for accusatory language
        for issue in accusatory_issues:
            assert issue.severity == IssueSeverity.HIGH, (
                "Accusatory language should have HIGH severity"
            )

    @pytest.mark.asyncio
    async def test_detects_accusatory_vous_in_french(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_fr: str,
    ) -> None:
        """Test that accusatory 'vous' language is detected in French messages.

        Verifies Quebec French compliance by detecting accusatory patterns
        like 'Vous devez', 'Vous n'avez jamais'.
        """
        request = MessageAnalysisRequest(
            message_text=accusatory_message_fr,
            language=Language.FR,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect accusatory language
        accusatory_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.ACCUSATORY_YOU
        ]
        assert len(accusatory_issues) > 0, (
            "Should detect accusatory 'vous' language in French"
        )

    @pytest.mark.asyncio
    async def test_accusatory_message_has_low_quality_score(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that accusatory messages receive lower quality scores.

        Verifies that messages with accusatory language are penalized
        in the overall quality score calculation.
        """
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Quality score should be below acceptable threshold (70)
        assert response.quality_score < 70, (
            f"Accusatory message should have score below 70, got {response.quality_score}"
        )
        assert not response.is_acceptable, (
            "Accusatory message should not be marked as acceptable"
        )


# =============================================================================
# Tests - Judgmental Label Detection
# =============================================================================


class TestJudgmentalLabelDetection:
    """Tests for detection of judgmental labels in messages."""

    @pytest.mark.asyncio
    async def test_detects_judgmental_labels_in_english(
        self,
        message_quality_service: MessageQualityService,
        judgmental_message_en: str,
    ) -> None:
        """Test that judgmental labels are detected in English messages.

        Verifies that labels like 'difficult kid', 'always disruptive'
        trigger JUDGMENTAL_LABEL quality issue detection.
        """
        request = MessageAnalysisRequest(
            message_text=judgmental_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect judgmental labels
        judgmental_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.JUDGMENTAL_LABEL
        ]
        assert len(judgmental_issues) > 0, (
            "Should detect judgmental labels in English"
        )

        # Judgmental labels should have CRITICAL severity
        for issue in judgmental_issues:
            assert issue.severity == IssueSeverity.CRITICAL, (
                "Judgmental labels should have CRITICAL severity"
            )

    @pytest.mark.asyncio
    async def test_detects_judgmental_labels_in_french(
        self,
        message_quality_service: MessageQualityService,
        judgmental_message_fr: str,
    ) -> None:
        """Test that judgmental labels are detected in French messages.

        Verifies Quebec French compliance for detecting labels like
        'enfant difficile', 'toujours perturbateur'.
        """
        request = MessageAnalysisRequest(
            message_text=judgmental_message_fr,
            language=Language.FR,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect judgmental labels
        judgmental_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.JUDGMENTAL_LABEL
        ]
        assert len(judgmental_issues) > 0, (
            "Should detect judgmental labels in French"
        )

    @pytest.mark.asyncio
    async def test_judgmental_message_not_acceptable(
        self,
        message_quality_service: MessageQualityService,
        judgmental_message_en: str,
    ) -> None:
        """Test that messages with judgmental labels are marked unacceptable.

        Messages with CRITICAL severity issues should never be marked
        as acceptable, regardless of overall score.
        """
        request = MessageAnalysisRequest(
            message_text=judgmental_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        assert not response.is_acceptable, (
            "Message with judgmental labels should not be acceptable"
        )


# =============================================================================
# Tests - Blame and Shame Detection
# =============================================================================


class TestBlameShameDetection:
    """Tests for detection of blame/shame patterns in messages."""

    @pytest.mark.asyncio
    async def test_detects_blame_patterns_in_english(
        self,
        message_quality_service: MessageQualityService,
        blame_shame_message_en: str,
    ) -> None:
        """Test that blame/shame patterns are detected in English messages.

        Verifies detection of patterns like 'your fault', 'if only you had'.
        """
        request = MessageAnalysisRequest(
            message_text=blame_shame_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect blame/shame patterns
        blame_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.BLAME_SHAME
        ]
        assert len(blame_issues) > 0, (
            "Should detect blame/shame patterns in English"
        )

        # Blame patterns should have HIGH severity
        for issue in blame_issues:
            assert issue.severity == IssueSeverity.HIGH, (
                "Blame/shame patterns should have HIGH severity"
            )

    @pytest.mark.asyncio
    async def test_detects_blame_patterns_in_french(
        self,
        message_quality_service: MessageQualityService,
        blame_shame_message_fr: str,
    ) -> None:
        """Test that blame/shame patterns are detected in French messages.

        Verifies Quebec French compliance for detecting blame patterns
        like 'c'est votre faute', 'si seulement vous aviez'.
        """
        request = MessageAnalysisRequest(
            message_text=blame_shame_message_fr,
            language=Language.FR,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect blame/shame patterns
        blame_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.BLAME_SHAME
        ]
        assert len(blame_issues) > 0, (
            "Should detect blame/shame patterns in French"
        )


# =============================================================================
# Tests - Exaggeration Detection
# =============================================================================


class TestExaggerationDetection:
    """Tests for detection of exaggeration patterns in messages."""

    @pytest.mark.asyncio
    async def test_detects_exaggeration_in_english(
        self,
        message_quality_service: MessageQualityService,
        exaggeration_message_en: str,
    ) -> None:
        """Test that exaggeration patterns are detected in English.

        Verifies detection of words like 'always', 'never', 'every single time'.
        """
        request = MessageAnalysisRequest(
            message_text=exaggeration_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect exaggeration patterns
        exaggeration_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.EXAGGERATION
        ]
        assert len(exaggeration_issues) > 0, (
            "Should detect exaggeration patterns in English"
        )

        # Exaggerations should have MEDIUM severity
        for issue in exaggeration_issues:
            assert issue.severity == IssueSeverity.MEDIUM, (
                "Exaggeration patterns should have MEDIUM severity"
            )

    @pytest.mark.asyncio
    async def test_detects_exaggeration_in_french(
        self,
        message_quality_service: MessageQualityService,
        exaggeration_message_fr: str,
    ) -> None:
        """Test that exaggeration patterns are detected in French.

        Verifies Quebec French compliance for detecting words like
        'toujours', 'jamais', 'chaque fois'.
        """
        request = MessageAnalysisRequest(
            message_text=exaggeration_message_fr,
            language=Language.FR,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect exaggeration patterns
        exaggeration_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.EXAGGERATION
        ]
        assert len(exaggeration_issues) > 0, (
            "Should detect exaggeration patterns in French"
        )


# =============================================================================
# Tests - Alarmist Language Detection
# =============================================================================


class TestAlarmistLanguageDetection:
    """Tests for detection of alarmist language patterns."""

    @pytest.mark.asyncio
    async def test_detects_alarmist_language_in_english(
        self,
        message_quality_service: MessageQualityService,
        alarmist_message_en: str,
    ) -> None:
        """Test that alarmist language is detected in English.

        Verifies detection of words like 'urgent', 'very worried',
        'serious concern', 'immediate attention'.
        """
        request = MessageAnalysisRequest(
            message_text=alarmist_message_en,
            language=Language.EN,
            context=MessageContext.INCIDENT_REPORT,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect alarmist language
        alarmist_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.ALARMIST
        ]
        assert len(alarmist_issues) > 0, (
            "Should detect alarmist language in English"
        )

    @pytest.mark.asyncio
    async def test_detects_alarmist_language_in_french(
        self,
        message_quality_service: MessageQualityService,
        alarmist_message_fr: str,
    ) -> None:
        """Test that alarmist language is detected in French.

        Verifies Quebec French compliance for detecting words like
        'urgent', 'très inquiet', 'grave préoccupation'.
        """
        request = MessageAnalysisRequest(
            message_text=alarmist_message_fr,
            language=Language.FR,
            context=MessageContext.INCIDENT_REPORT,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect alarmist language
        alarmist_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.ALARMIST
        ]
        assert len(alarmist_issues) > 0, (
            "Should detect alarmist language in French"
        )


# =============================================================================
# Tests - Comparison Detection
# =============================================================================


class TestComparisonDetection:
    """Tests for detection of inappropriate comparisons to other children."""

    @pytest.mark.asyncio
    async def test_detects_comparison_in_english(
        self,
        message_quality_service: MessageQualityService,
        comparison_message_en: str,
    ) -> None:
        """Test that comparisons to other children are detected in English.

        Verifies detection of phrases like 'unlike other children',
        'most children at this age'.
        """
        request = MessageAnalysisRequest(
            message_text=comparison_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect comparison patterns
        comparison_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.COMPARISON
        ]
        assert len(comparison_issues) > 0, (
            "Should detect comparison patterns in English"
        )

        # Comparisons should have HIGH severity
        for issue in comparison_issues:
            assert issue.severity == IssueSeverity.HIGH, (
                "Comparison patterns should have HIGH severity"
            )

    @pytest.mark.asyncio
    async def test_detects_comparison_in_french(
        self,
        message_quality_service: MessageQualityService,
        comparison_message_fr: str,
    ) -> None:
        """Test that comparisons to other children are detected in French.

        Verifies Quebec French compliance for detecting phrases like
        'contrairement aux autres enfants', 'la plupart des enfants de son âge'.
        """
        request = MessageAnalysisRequest(
            message_text=comparison_message_fr,
            language=Language.FR,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should detect comparison patterns
        comparison_issues = [
            issue for issue in response.issues
            if issue.issue_type == QualityIssue.COMPARISON
        ]
        assert len(comparison_issues) > 0, (
            "Should detect comparison patterns in French"
        )


# =============================================================================
# Tests - Positive Message Recognition
# =============================================================================


class TestPositiveMessageRecognition:
    """Tests for recognition of positive, well-structured messages."""

    @pytest.mark.asyncio
    async def test_positive_message_has_high_quality_score_en(
        self,
        message_quality_service: MessageQualityService,
        positive_message_en: str,
    ) -> None:
        """Test that positive English messages receive high quality scores.

        Verifies that messages following 'Bonne Message' standards
        with positive opening, factual content, and solution focus
        receive scores above the acceptable threshold.
        """
        request = MessageAnalysisRequest(
            message_text=positive_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Quality score should be acceptable (>= 70)
        assert response.quality_score >= 70, (
            f"Positive message should have score >= 70, got {response.quality_score}"
        )
        assert response.is_acceptable, (
            "Positive message should be marked as acceptable"
        )

    @pytest.mark.asyncio
    async def test_positive_message_has_high_quality_score_fr(
        self,
        message_quality_service: MessageQualityService,
        positive_message_fr: str,
    ) -> None:
        """Test that positive French messages receive high quality scores.

        Verifies Quebec French compliance for well-structured messages
        following 'Bonne Message' standards.
        """
        request = MessageAnalysisRequest(
            message_text=positive_message_fr,
            language=Language.FR,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Quality score should be acceptable (>= 70)
        assert response.quality_score >= 70, (
            f"Positive French message should have score >= 70, got {response.quality_score}"
        )
        assert response.is_acceptable, (
            "Positive French message should be marked as acceptable"
        )

    @pytest.mark.asyncio
    async def test_positive_message_has_structural_elements(
        self,
        message_quality_service: MessageQualityService,
        positive_message_en: str,
    ) -> None:
        """Test that positive messages are recognized for structural elements.

        Verifies detection of positive opening, factual basis, and
        solution-oriented closing.
        """
        request = MessageAnalysisRequest(
            message_text=positive_message_en,
            language=Language.EN,
            context=MessageContext.BEHAVIOR_CONCERN,
        )

        response = await message_quality_service.analyze_message(request)

        # Should have positive structural elements
        assert response.has_positive_opening, (
            "Should detect positive opening in well-structured message"
        )
        assert response.has_solution_focus, (
            "Should detect solution focus in well-structured message"
        )

    @pytest.mark.asyncio
    async def test_short_positive_message_acceptable(
        self,
        message_quality_service: MessageQualityService,
        short_positive_message_en: str,
    ) -> None:
        """Test that short positive messages are still acceptable.

        Short messages without solution focus should still be acceptable
        if they have no quality issues and start positively.
        """
        request = MessageAnalysisRequest(
            message_text=short_positive_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Short positive messages should generally be acceptable
        assert response.quality_score >= 60, (
            f"Short positive message should have score >= 60, got {response.quality_score}"
        )


# =============================================================================
# Tests - Quality Score Calculation
# =============================================================================


class TestQualityScoreCalculation:
    """Tests for quality score calculation logic."""

    @pytest.mark.asyncio
    async def test_quality_score_public_method(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test the public calculate_quality_score method.

        Verifies that the public method returns comprehensive
        validation results including all structural checks.
        """
        message = (
            "I wanted to share an observation about Emma's progress. "
            "She has been working hard on her transitions between activities. "
            "Let's work together to continue supporting her development."
        )

        result = message_quality_service.calculate_quality_score(
            message_text=message,
            language=Language.EN,
        )

        # Should return dictionary with required fields
        assert "quality_score" in result, "Should include quality_score"
        assert "has_single_objective" in result, "Should include has_single_objective"
        assert "has_factual_basis" in result, "Should include has_factual_basis"
        assert "has_neutral_tone" in result, "Should include has_neutral_tone"
        assert "has_collaborative_approach" in result, "Should include has_collaborative_approach"
        assert "issues" in result, "Should include issues list"
        assert "validation_details" in result, "Should include validation_details"

    @pytest.mark.asyncio
    async def test_quality_score_range(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that quality scores stay within valid range (0-100)."""
        messages = [
            "You are the worst parent. You never do anything right!",  # Very negative
            "Thank you for everything!",  # Very positive
        ]

        for message in messages:
            result = message_quality_service.calculate_quality_score(
                message_text=message,
                language=Language.EN,
            )

            assert 0 <= result["quality_score"] <= 100, (
                f"Quality score should be 0-100, got {result['quality_score']}"
            )

    @pytest.mark.asyncio
    async def test_empty_message_raises_error(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that empty messages raise InvalidMessageError."""
        with pytest.raises(InvalidMessageError):
            message_quality_service.calculate_quality_score(
                message_text="",
                language=Language.EN,
            )

        with pytest.raises(InvalidMessageError):
            message_quality_service.calculate_quality_score(
                message_text="   ",
                language=Language.EN,
            )


# =============================================================================
# Tests - Rewrite Suggestions
# =============================================================================


class TestRewriteSuggestions:
    """Tests for rewrite suggestion generation."""

    @pytest.mark.asyncio
    async def test_rewrite_suggestion_generated_for_issues(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that rewrite suggestions are generated for problematic messages."""
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
            include_rewrites=True,
        )

        response = await message_quality_service.analyze_message(request)

        # Should include rewrite suggestions when issues detected
        assert len(response.rewrite_suggestions) > 0, (
            "Should generate rewrite suggestions for problematic messages"
        )

        # Verify rewrite suggestion structure
        suggestion = response.rewrite_suggestions[0]
        assert suggestion.original_text is not None, "Should include original text"
        assert suggestion.suggested_text is not None, "Should include suggested text"
        assert suggestion.explanation is not None, "Should include explanation"
        assert suggestion.uses_i_language, "Should use 'I' language transformation"
        assert suggestion.has_sandwich_structure, "Should use sandwich method"

    @pytest.mark.asyncio
    async def test_rewrite_uses_i_language(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that rewrites transform accusatory 'you' to 'I' language."""
        issues = [
            QualityIssueDetail(
                issue_type=QualityIssue.ACCUSATORY_YOU,
                severity=IssueSeverity.HIGH,
                description="Accusatory language detected",
                original_text="You never",
                position_start=0,
                position_end=9,
                suggestion="Use 'I' language",
            )
        ]

        suggestion = message_quality_service.suggest_rewrite(
            message_text="You never bring the snacks on time.",
            issues=issues,
            language=Language.EN,
        )

        assert suggestion.uses_i_language, (
            "Rewrite should be marked as using 'I' language"
        )
        # Check that the suggested text contains 'I' language markers
        suggested_lower = suggestion.suggested_text.lower()
        assert any(
            marker in suggested_lower
            for marker in ["i ", "i'", "i'd", "i've", "we "]
        ), "Suggested text should contain 'I' or 'we' language"

    @pytest.mark.asyncio
    async def test_rewrite_has_sandwich_structure(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that rewrites follow sandwich method structure."""
        issues = [
            QualityIssueDetail(
                issue_type=QualityIssue.MISSING_POSITIVE,
                severity=IssueSeverity.LOW,
                description="Missing positive opening",
                original_text="[Entire message]",
                position_start=0,
                position_end=50,
                suggestion="Add positive opening",
            )
        ]

        suggestion = message_quality_service.suggest_rewrite(
            message_text="Your child had trouble focusing today.",
            issues=issues,
            language=Language.EN,
        )

        assert suggestion.has_sandwich_structure, (
            "Rewrite should follow sandwich method structure"
        )

    @pytest.mark.asyncio
    async def test_no_rewrite_when_disabled(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that rewrites are not generated when include_rewrites is False."""
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
            include_rewrites=False,
        )

        response = await message_quality_service.analyze_message(request)

        # Should not include rewrite suggestions when disabled
        assert len(response.rewrite_suggestions) == 0, (
            "Should not generate rewrite suggestions when disabled"
        )


# =============================================================================
# Tests - Bilingual Support
# =============================================================================


class TestBilingualSupport:
    """Tests for bilingual (English/French) support per Quebec requirements."""

    @pytest.mark.asyncio
    async def test_english_analysis_returns_english_content(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that English analysis returns English descriptions and suggestions."""
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Verify response language
        assert response.language == Language.EN, (
            "Response language should be English"
        )

        # Verify issue descriptions are in English
        for issue in response.issues:
            assert issue.description is not None, "Issue should have description"
            # English descriptions should contain English words
            assert not any(
                french_word in issue.description.lower()
                for french_word in ["détecté", "langage", "votre"]
            ), "English analysis should have English descriptions"

    @pytest.mark.asyncio
    async def test_french_analysis_returns_french_content(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_fr: str,
    ) -> None:
        """Test that French analysis returns French descriptions and suggestions.

        Verifies Quebec French compliance by ensuring all user-facing
        content is properly translated.
        """
        request = MessageAnalysisRequest(
            message_text=accusatory_message_fr,
            language=Language.FR,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        # Verify response language
        assert response.language == Language.FR, (
            "Response language should be French"
        )

        # Verify issue descriptions are in French
        for issue in response.issues:
            assert issue.description is not None, "Issue should have description"
            # French descriptions should contain French words
            assert any(
                french_indicator in issue.description.lower()
                for french_indicator in ["détecté", "langage", "peut", "être", "les", "que"]
            ), "French analysis should have French descriptions"

    @pytest.mark.asyncio
    async def test_language_enum_values(self) -> None:
        """Test that Language enum has both English and French values."""
        assert Language.EN.value == "en", "English language code should be 'en'"
        assert Language.FR.value == "fr", "French language code should be 'fr'"
        assert len(list(Language)) == 2, "Should have exactly 2 languages"


# =============================================================================
# Tests - Error Handling
# =============================================================================


class TestErrorHandling:
    """Tests for error handling in message quality analysis."""

    @pytest.mark.asyncio
    async def test_empty_message_raises_invalid_message_error(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that empty message text raises InvalidMessageError."""
        request = MessageAnalysisRequest(
            message_text="",
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        with pytest.raises(InvalidMessageError) as exc_info:
            await message_quality_service.analyze_message(request)

        assert "empty" in str(exc_info.value).lower(), (
            "Error message should mention empty message"
        )

    @pytest.mark.asyncio
    async def test_whitespace_only_message_raises_error(
        self,
        message_quality_service: MessageQualityService,
    ) -> None:
        """Test that whitespace-only message raises InvalidMessageError."""
        request = MessageAnalysisRequest(
            message_text="   \t\n  ",
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        with pytest.raises(InvalidMessageError):
            await message_quality_service.analyze_message(request)


# =============================================================================
# Tests - Issue Detail Structure
# =============================================================================


class TestIssueDetailStructure:
    """Tests for quality issue detail structure and content."""

    @pytest.mark.asyncio
    async def test_issue_contains_all_required_fields(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that each detected issue contains all required fields."""
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        assert len(response.issues) > 0, "Should detect issues"

        for issue in response.issues:
            assert issue.issue_type is not None, "Issue should have type"
            assert issue.severity is not None, "Issue should have severity"
            assert issue.description is not None, "Issue should have description"
            assert len(issue.description) > 0, "Description should not be empty"
            assert issue.original_text is not None, "Issue should have original_text"
            assert issue.position_start >= 0, "Position start should be non-negative"
            assert issue.position_end >= issue.position_start, (
                "Position end should be >= start"
            )
            assert issue.suggestion is not None, "Issue should have suggestion"

    @pytest.mark.asyncio
    async def test_issue_positions_are_valid(
        self,
        message_quality_service: MessageQualityService,
        accusatory_message_en: str,
    ) -> None:
        """Test that issue positions correctly identify the problematic text."""
        request = MessageAnalysisRequest(
            message_text=accusatory_message_en,
            language=Language.EN,
            context=MessageContext.GENERAL_UPDATE,
        )

        response = await message_quality_service.analyze_message(request)

        for issue in response.issues:
            if issue.original_text != "[Entire message]":
                # Position should be within message bounds
                assert issue.position_end <= len(accusatory_message_en), (
                    "Position end should not exceed message length"
                )


# =============================================================================
# Tests - All Quality Issue Types
# =============================================================================


class TestAllQualityIssueTypes:
    """Tests to verify all quality issue types are properly defined."""

    @pytest.mark.asyncio
    async def test_quality_issue_enum_completeness(self) -> None:
        """Test that QualityIssue enum has all expected values."""
        expected_issues = {
            "accusatory_you",
            "judgmental_label",
            "blame_shame",
            "exaggeration",
            "alarmist",
            "comparison",
            "negative_tone",
            "missing_positive",
            "missing_solution",
            "multiple_objectives",
        }

        actual_issues = {issue.value for issue in QualityIssue}

        assert actual_issues == expected_issues, (
            f"QualityIssue enum should have all expected values. "
            f"Missing: {expected_issues - actual_issues}, "
            f"Extra: {actual_issues - expected_issues}"
        )

    @pytest.mark.asyncio
    async def test_issue_severity_enum_completeness(self) -> None:
        """Test that IssueSeverity enum has all expected values."""
        expected_severities = {"low", "medium", "high", "critical"}
        actual_severities = {severity.value for severity in IssueSeverity}

        assert actual_severities == expected_severities, (
            "IssueSeverity enum should have all expected values"
        )

    @pytest.mark.asyncio
    async def test_message_context_enum_completeness(self) -> None:
        """Test that MessageContext enum has all expected values."""
        expected_contexts = {
            "daily_report",
            "incident_report",
            "milestone_update",
            "general_update",
            "behavior_concern",
            "health_update",
        }
        actual_contexts = {context.value for context in MessageContext}

        assert actual_contexts == expected_contexts, (
            "MessageContext enum should have all expected values"
        )
