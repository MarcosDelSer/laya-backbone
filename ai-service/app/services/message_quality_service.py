"""Service for AI-powered message quality analysis.

Provides message quality analysis based on Quebec 'Bonne Message' communication
standards for positive parent-educator communication in daycare settings.
All analysis results and suggestions are available in both English and French
for Quebec bilingual compliance.
"""

import re
from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy.ext.asyncio import AsyncSession

from app.models.message_quality import MessageAnalysis
from app.schemas.message_quality import (
    IssueSeverity,
    Language,
    MessageAnalysisRequest,
    MessageAnalysisResponse,
    MessageContext,
    QualityIssue,
    QualityIssueDetail,
    RewriteSuggestion,
)


# =============================================================================
# Bilingual Quality Issue Templates
# =============================================================================

QUALITY_ISSUE_TEMPLATES = {
    Language.EN: {
        QualityIssue.ACCUSATORY_YOU: {
            "description": (
                "Accusatory 'you' language detected. This phrasing may make "
                "the parent feel blamed or attacked. Consider using 'I' language instead."
            ),
            "suggestion": "Try using 'I noticed...' or 'I observed...' instead of 'You...'",
        },
        QualityIssue.JUDGMENTAL_LABEL: {
            "description": (
                "Judgmental label detected. Labeling a child or parent can be "
                "hurtful and unproductive. Focus on specific behaviors instead."
            ),
            "suggestion": "Describe the specific behavior rather than using labels",
        },
        QualityIssue.BLAME_SHAME: {
            "description": (
                "Blame or shame pattern detected. This language may make the "
                "parent feel guilty rather than supported. Focus on solutions."
            ),
            "suggestion": "Focus on what can be done together rather than assigning blame",
        },
        QualityIssue.EXAGGERATION: {
            "description": (
                "Exaggeration detected ('always', 'never', 'every time'). These "
                "absolute terms are rarely accurate and can make parents defensive."
            ),
            "suggestion": "Use specific examples with dates or frequencies instead",
        },
        QualityIssue.ALARMIST: {
            "description": (
                "Alarmist language detected. This may cause unnecessary worry "
                "or panic. Present concerns calmly and factually."
            ),
            "suggestion": "Present the observation calmly with context and next steps",
        },
        QualityIssue.COMPARISON: {
            "description": (
                "Comparison to other children detected. Comparing children can "
                "be hurtful and unhelpful. Focus on the individual child's progress."
            ),
            "suggestion": "Focus on this child's individual growth and development",
        },
        QualityIssue.NEGATIVE_TONE: {
            "description": (
                "Overall negative tone detected. Messages should maintain a "
                "constructive and supportive tone even when addressing concerns."
            ),
            "suggestion": "Balance concerns with positives and focus on solutions",
        },
        QualityIssue.MISSING_POSITIVE: {
            "description": (
                "Missing positive opening. Starting with something positive helps "
                "parents feel appreciated and receptive to any concerns."
            ),
            "suggestion": "Begin with a genuine positive observation about the child",
        },
        QualityIssue.MISSING_SOLUTION: {
            "description": (
                "Missing solution-oriented closing. Messages should end with "
                "actionable next steps or an invitation to collaborate."
            ),
            "suggestion": "End with a specific suggestion or invitation to discuss",
        },
        QualityIssue.MULTIPLE_OBJECTIVES: {
            "description": (
                "Multiple topics or objectives detected. Messages with too many "
                "topics can overwhelm parents. Focus on one main point per message."
            ),
            "suggestion": "Focus on one main topic and save others for separate messages",
        },
    },
    Language.FR: {
        QualityIssue.ACCUSATORY_YOU: {
            "description": (
                "Langage accusateur 'vous' détecté. Cette formulation peut faire "
                "sentir le parent blâmé ou attaqué. Utilisez plutôt le langage 'Je'."
            ),
            "suggestion": "Essayez d'utiliser 'J'ai remarqué...' ou 'J'ai observé...' au lieu de 'Vous...'",
        },
        QualityIssue.JUDGMENTAL_LABEL: {
            "description": (
                "Étiquette jugeante détectée. Étiqueter un enfant ou un parent peut "
                "être blessant et improductif. Concentrez-vous sur des comportements spécifiques."
            ),
            "suggestion": "Décrivez le comportement spécifique plutôt que d'utiliser des étiquettes",
        },
        QualityIssue.BLAME_SHAME: {
            "description": (
                "Schéma de blâme ou de honte détecté. Ce langage peut faire "
                "culpabiliser le parent plutôt que de le soutenir. Concentrez-vous sur les solutions."
            ),
            "suggestion": "Concentrez-vous sur ce qui peut être fait ensemble plutôt que d'attribuer des blâmes",
        },
        QualityIssue.EXAGGERATION: {
            "description": (
                "Exagération détectée ('toujours', 'jamais', 'chaque fois'). Ces "
                "termes absolus sont rarement exacts et peuvent rendre les parents défensifs."
            ),
            "suggestion": "Utilisez des exemples spécifiques avec des dates ou des fréquences",
        },
        QualityIssue.ALARMIST: {
            "description": (
                "Langage alarmiste détecté. Cela peut causer des inquiétudes ou "
                "de la panique inutiles. Présentez les préoccupations calmement et factuellement."
            ),
            "suggestion": "Présentez l'observation calmement avec le contexte et les prochaines étapes",
        },
        QualityIssue.COMPARISON: {
            "description": (
                "Comparaison avec d'autres enfants détectée. Comparer les enfants peut "
                "être blessant et inutile. Concentrez-vous sur les progrès individuels de l'enfant."
            ),
            "suggestion": "Concentrez-vous sur la croissance et le développement individuel de cet enfant",
        },
        QualityIssue.NEGATIVE_TONE: {
            "description": (
                "Ton général négatif détecté. Les messages doivent maintenir un "
                "ton constructif et encourageant même lorsqu'ils abordent des préoccupations."
            ),
            "suggestion": "Équilibrez les préoccupations avec des points positifs et concentrez-vous sur les solutions",
        },
        QualityIssue.MISSING_POSITIVE: {
            "description": (
                "Ouverture positive manquante. Commencer par quelque chose de positif "
                "aide les parents à se sentir appréciés et réceptifs aux préoccupations."
            ),
            "suggestion": "Commencez par une observation positive sincère sur l'enfant",
        },
        QualityIssue.MISSING_SOLUTION: {
            "description": (
                "Conclusion orientée solution manquante. Les messages doivent se terminer "
                "par des prochaines étapes concrètes ou une invitation à collaborer."
            ),
            "suggestion": "Terminez par une suggestion spécifique ou une invitation à discuter",
        },
        QualityIssue.MULTIPLE_OBJECTIVES: {
            "description": (
                "Plusieurs sujets ou objectifs détectés. Les messages avec trop de "
                "sujets peuvent submerger les parents. Concentrez-vous sur un point principal."
            ),
            "suggestion": "Concentrez-vous sur un sujet principal et gardez les autres pour des messages séparés",
        },
    },
}

# =============================================================================
# Detection Pattern Definitions
# =============================================================================

# Accusatory 'you' patterns (English)
ACCUSATORY_YOU_PATTERNS_EN = [
    r"\byou\s+(?:always|never|don\'?t|didn\'?t|won\'?t|can\'?t|shouldn\'?t|failed|forgot|neglected)\b",
    r"\byou\s+(?:need\s+to|have\s+to|must|should)\b",
    r"\byour\s+(?:child|kid|son|daughter)\s+(?:always|never|can\'?t|won\'?t)\b",
    r"\byou\s+are\s+(?:not|never)\b",
    r"\bwhy\s+(?:didn\'?t|don\'?t|won\'?t|can\'?t)\s+you\b",
]

# Accusatory 'vous' patterns (French)
ACCUSATORY_YOU_PATTERNS_FR = [
    r"\bvous\s+(?:devez|devriez|auriez\s+dû|n\'?avez\s+pas|n\'?avez\s+jamais)\b",
    r"\bvous\s+(?:ne\s+(?:faites|pouvez|devez)\s+(?:pas|jamais))\b",
    r"\bvotre\s+(?:enfant|fils|fille)\s+(?:ne\s+(?:fait|peut|veut)\s+(?:pas|jamais))\b",
    r"\bpourquoi\s+(?:n\'?avez-vous\s+pas|vous\s+n\'?avez\s+pas)\b",
]

# Judgmental label patterns
JUDGMENTAL_LABELS_EN = [
    r"\b(?:is\s+)?(?:a\s+)?(?:bad|lazy|difficult|stubborn|mean|naughty|spoiled|rude|selfish|aggressive|hyperactive|problematic)\s+(?:child|kid|boy|girl)\b",
    r"\b(?:he|she)\s+is\s+(?:always\s+)?(?:disruptive|trouble|a\s+problem|impossible|uncontrollable|wild)\b",
    r"\b(?:your\s+)?(?:child|kid|son|daughter)\s+is\s+(?:being\s+)?(?:bad|difficult|impossible|unmanageable)\b",
]

JUDGMENTAL_LABELS_FR = [
    r"\b(?:est\s+)?(?:un|une)\s+(?:enfant|garçon|fille)\s+(?:difficile|méchant|paresseux|têtu|impoli|agressif|hyperactif|problématique)\b",
    r"\b(?:il|elle)\s+est\s+(?:toujours\s+)?(?:perturbateur|un\s+problème|impossible|incontrôlable)\b",
    r"\bvotre\s+(?:enfant|fils|fille)\s+est\s+(?:vraiment\s+)?(?:difficile|impossible|ingérable)\b",
]

# Blame/shame patterns
BLAME_SHAME_PATTERNS_EN = [
    r"\b(?:it\'?s\s+)?(?:your|the\s+parent\'?s?)\s+(?:fault|responsibility|problem)\b",
    r"\b(?:you|parents)\s+(?:are|should\s+be)\s+(?:to\s+blame|responsible\s+for)\b",
    r"\b(?:if\s+only\s+you|you\s+should\s+have)\b",
    r"\b(?:this\s+wouldn\'?t\s+have\s+happened\s+if)\b",
    r"\b(?:because\s+of\s+(?:you|your|the\s+parents?))\b",
]

BLAME_SHAME_PATTERNS_FR = [
    r"\bc\'?est\s+(?:votre|la)\s+(?:faute|responsabilité)\b",
    r"\b(?:vous|les\s+parents)\s+(?:êtes|devriez\s+être)\s+(?:responsables?|à\s+blâmer)\b",
    r"\bsi\s+seulement\s+vous\s+aviez\b",
    r"\bcela\s+ne\s+serait\s+pas\s+arrivé\s+si\b",
    r"\bà\s+cause\s+de\s+(?:vous|votre)\b",
]

# Exaggeration patterns
EXAGGERATION_PATTERNS_EN = [
    r"\b(?:always|never|every\s+(?:time|single\s+time|day)|constantly|forever|all\s+the\s+time)\b",
    r"\b(?:absolutely|completely|totally|utterly)\s+(?:impossible|unacceptable|unbelievable)\b",
    r"\b(?:the\s+worst|the\s+only\s+one|no\s+one\s+else)\b",
]

EXAGGERATION_PATTERNS_FR = [
    r"\b(?:toujours|jamais|chaque\s+(?:fois|jour)|constamment|tout\s+le\s+temps)\b",
    r"\b(?:absolument|complètement|totalement)\s+(?:impossible|inacceptable|incroyable)\b",
    r"\b(?:le\s+pire|le\s+seul|la\s+seule|personne\s+d\'?autre)\b",
]

# Alarmist patterns
ALARMIST_PATTERNS_EN = [
    r"\b(?:urgent|emergency|immediately|crisis|dangerous|serious\s+concern|very\s+worried|alarming)\b",
    r"\b(?:if\s+this\s+continues|we\s+are\s+very\s+concerned|this\s+is\s+(?:very\s+)?serious)\b",
    r"\b(?:i\s+am\s+(?:very\s+)?(?:worried|concerned|alarmed))\b",
    r"\b(?:needs\s+immediate\s+attention|cannot\s+be\s+ignored)\b",
]

ALARMIST_PATTERNS_FR = [
    r"\b(?:urgent|urgence|immédiatement|crise|dangereux|grave\s+préoccupation|très\s+inquiet|alarmant)\b",
    r"\b(?:si\s+cela\s+continue|nous\s+sommes\s+très\s+(?:préoccupés|inquiets)|c\'?est\s+(?:très\s+)?grave)\b",
    r"\b(?:je\s+suis\s+(?:très\s+)?(?:inquiet|préoccupé|alarmé))\b",
    r"\b(?:nécessite\s+une\s+attention\s+immédiate|ne\s+peut\s+pas\s+être\s+ignoré)\b",
]

# Comparison patterns
COMPARISON_PATTERNS_EN = [
    r"\b(?:unlike|compared\s+to|other\s+(?:children|kids)|the\s+other\s+(?:children|kids))\b",
    r"\b(?:(?:his|her|their)\s+(?:peers|classmates)\s+(?:can|are\s+able\s+to))\b",
    r"\b(?:most\s+(?:children|kids)\s+(?:at\s+this\s+age|his\s+age|her\s+age))\b",
    r"\b(?:why\s+can\'?t\s+(?:he|she)\s+be\s+(?:like|more\s+like))\b",
]

COMPARISON_PATTERNS_FR = [
    r"\b(?:contrairement\s+à|comparé\s+(?:à|aux)|les\s+autres\s+enfants)\b",
    r"\b(?:(?:ses)\s+(?:pairs|camarades)\s+(?:peuvent|sont\s+capables\s+de))\b",
    r"\b(?:la\s+plupart\s+des\s+enfants\s+(?:à\s+cet\s+âge|de\s+son\s+âge))\b",
    r"\b(?:pourquoi\s+(?:il|elle)\s+ne\s+peut\s+pas\s+être\s+(?:comme|plus\s+comme))\b",
]

# Positive opening indicators
POSITIVE_OPENINGS_EN = [
    r"^(?:i\s+(?:wanted\s+to|would\s+like\s+to)\s+(?:share|let\s+you\s+know))",
    r"^(?:thank\s+you|thanks\s+for)",
    r"^(?:we\s+(?:enjoyed|loved|appreciated|noticed\s+(?:how\s+well|that)))",
    r"^(?:(?:your\s+child|he|she)\s+(?:did\s+(?:a\s+)?great|had\s+(?:a\s+)?wonderful|showed|demonstrated))",
    r"^(?:it\s+was\s+(?:lovely|wonderful|great)\s+to\s+see)",
    r"^(?:i\'?m\s+(?:happy|pleased|delighted)\s+to)",
]

POSITIVE_OPENINGS_FR = [
    r"^(?:je\s+(?:voulais|voudrais)\s+(?:partager|vous\s+informer))",
    r"^(?:merci\s+(?:de|pour))",
    r"^(?:nous\s+avons\s+(?:apprécié|aimé|remarqué))",
    r"^(?:(?:votre\s+enfant|il|elle)\s+a\s+(?:fait|passé|montré|démontré))",
    r"^(?:c\'?était\s+(?:agréable|merveilleux|super)\s+de\s+voir)",
    r"^(?:je\s+suis\s+(?:heureux|content|ravi)\s+de)",
]

# Solution-oriented closing indicators
SOLUTION_CLOSINGS_EN = [
    r"(?:let\'?s\s+(?:work\s+together|discuss|find\s+a\s+way))\s*$",
    r"(?:please\s+(?:let\s+me|feel\s+free\s+to)\s+know)\s*$",
    r"(?:i\'?d\s+(?:love|like)\s+to\s+(?:discuss|talk|hear))\s*$",
    r"(?:we\s+can\s+(?:try|work\s+on|focus\s+on))\s*$",
    r"(?:if\s+you\s+have\s+any\s+questions)\s*$",
    r"(?:looking\s+forward\s+to\s+(?:working|hearing))\s*$",
]

SOLUTION_CLOSINGS_FR = [
    r"(?:travaillons\s+ensemble|discutons|trouvons\s+une\s+solution)\s*$",
    r"(?:n\'?hésitez\s+pas\s+à\s+(?:me\s+contacter|nous\s+contacter))\s*$",
    r"(?:j\'?aimerais\s+(?:en\s+discuter|en\s+parler))\s*$",
    r"(?:nous\s+pouvons\s+(?:essayer|travailler\s+sur|nous\s+concentrer\s+sur))\s*$",
    r"(?:si\s+vous\s+avez\s+des\s+questions)\s*$",
    r"(?:au\s+plaisir\s+de\s+(?:travailler|vous\s+entendre))\s*$",
]


# =============================================================================
# Exception Classes
# =============================================================================


class MessageQualityServiceError(Exception):
    """Base exception for message quality service errors."""

    pass


class InvalidMessageError(MessageQualityServiceError):
    """Raised when the message text is invalid or empty."""

    pass


class AnalysisError(MessageQualityServiceError):
    """Raised when an error occurs during message analysis."""

    pass


# =============================================================================
# Message Quality Service
# =============================================================================


class MessageQualityService:
    """Service for AI-powered message quality analysis.

    This service provides message quality analysis based on Quebec 'Bonne Message'
    communication standards for positive parent-educator communication in daycare
    settings. It detects problematic language patterns and provides suggestions
    for improvement.

    Analysis covers:
    - Accusatory 'you' language detection
    - Judgmental labels identification
    - Blame/shame patterns
    - Exaggerations ('always', 'never')
    - Alarmist language
    - Inappropriate comparisons to other children

    All analysis results and suggestions are available in both English and French
    for Quebec bilingual compliance.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the message quality service.

        Args:
            db: Async database session
        """
        self.db = db

    async def analyze_message(
        self,
        request: MessageAnalysisRequest,
        user: Optional[dict] = None,
    ) -> MessageAnalysisResponse:
        """Analyze message quality against Quebec 'Bonne Message' standards.

        Performs comprehensive analysis of educator messages to detect
        problematic language patterns and provide improvement suggestions.
        The analysis covers accusatory language, judgmental labels, blame/shame
        patterns, exaggerations, alarmist language, and comparisons.

        Args:
            request: The message analysis request containing text and options
            user: Optional authenticated user making the request

        Returns:
            MessageAnalysisResponse with quality score, detected issues,
            and rewrite suggestions

        Raises:
            InvalidMessageError: When the message text is invalid or empty
        """
        # Validate message text
        if not request.message_text or not request.message_text.strip():
            raise InvalidMessageError("Message text cannot be empty")

        message_text = request.message_text.strip()
        language = request.language

        # Detect quality issues
        issues = self._detect_issues(message_text, language)

        # Check message structure
        has_positive_opening = self._check_positive_opening(message_text, language)
        has_solution_focus = self._check_solution_closing(message_text, language)
        has_factual_basis = self._check_factual_basis(message_text, language)

        # Add structural issues if missing
        if not has_positive_opening:
            issues.append(self._create_structural_issue(
                QualityIssue.MISSING_POSITIVE,
                language,
                message_text,
            ))

        if not has_solution_focus and len(message_text) > 100:
            issues.append(self._create_structural_issue(
                QualityIssue.MISSING_SOLUTION,
                language,
                message_text,
            ))

        # Calculate quality score
        quality_score = self._calculate_quality_score(
            issues=issues,
            has_positive_opening=has_positive_opening,
            has_solution_focus=has_solution_focus,
            has_factual_basis=has_factual_basis,
        )

        # Determine if message is acceptable
        is_acceptable = quality_score >= 70 and not any(
            issue.severity == IssueSeverity.CRITICAL for issue in issues
        )

        # Generate rewrite suggestions if requested and issues exist
        rewrite_suggestions: list[RewriteSuggestion] = []
        if request.include_rewrites and issues:
            rewrite_suggestions = self._generate_rewrite_suggestions(
                message_text=message_text,
                issues=issues,
                language=language,
            )

        # Generate analysis notes
        analysis_notes = self._generate_analysis_notes(
            issues=issues,
            quality_score=quality_score,
            language=language,
        )

        # Persist analysis to database if user is authenticated
        if user:
            await self._persist_analysis(
                request=request,
                user_id=UUID(user.get("sub", user.get("user_id"))),
                quality_score=quality_score,
                is_acceptable=is_acceptable,
                issues=issues,
                has_positive_opening=has_positive_opening,
                has_factual_basis=has_factual_basis,
                has_solution_focus=has_solution_focus,
                rewrite_suggested=len(rewrite_suggestions) > 0,
                analysis_notes=analysis_notes,
            )

        return MessageAnalysisResponse(
            id=UUID("00000000-0000-0000-0000-000000000000"),
            message_text=message_text,
            language=language,
            quality_score=quality_score,
            is_acceptable=is_acceptable,
            issues=issues,
            rewrite_suggestions=rewrite_suggestions,
            has_positive_opening=has_positive_opening,
            has_factual_basis=has_factual_basis,
            has_solution_focus=has_solution_focus,
            analysis_notes=analysis_notes,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )

    def _detect_issues(
        self,
        message_text: str,
        language: Language,
    ) -> list[QualityIssueDetail]:
        """Detect all quality issues in the message.

        Scans the message for various problematic patterns including
        accusatory language, judgmental labels, blame/shame patterns,
        exaggerations, alarmist language, and comparisons.

        Args:
            message_text: The message text to analyze
            language: Language of the message

        Returns:
            List of detected quality issues with details
        """
        issues: list[QualityIssueDetail] = []
        text_lower = message_text.lower()

        # Select patterns based on language
        if language == Language.FR:
            accusatory_patterns = ACCUSATORY_YOU_PATTERNS_FR
            judgmental_patterns = JUDGMENTAL_LABELS_FR
            blame_patterns = BLAME_SHAME_PATTERNS_FR
            exaggeration_patterns = EXAGGERATION_PATTERNS_FR
            alarmist_patterns = ALARMIST_PATTERNS_FR
            comparison_patterns = COMPARISON_PATTERNS_FR
        else:
            accusatory_patterns = ACCUSATORY_YOU_PATTERNS_EN
            judgmental_patterns = JUDGMENTAL_LABELS_EN
            blame_patterns = BLAME_SHAME_PATTERNS_EN
            exaggeration_patterns = EXAGGERATION_PATTERNS_EN
            alarmist_patterns = ALARMIST_PATTERNS_EN
            comparison_patterns = COMPARISON_PATTERNS_EN

        # Detect accusatory 'you' language
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=accusatory_patterns,
            issue_type=QualityIssue.ACCUSATORY_YOU,
            severity=IssueSeverity.HIGH,
            language=language,
        ))

        # Detect judgmental labels
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=judgmental_patterns,
            issue_type=QualityIssue.JUDGMENTAL_LABEL,
            severity=IssueSeverity.CRITICAL,
            language=language,
        ))

        # Detect blame/shame patterns
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=blame_patterns,
            issue_type=QualityIssue.BLAME_SHAME,
            severity=IssueSeverity.HIGH,
            language=language,
        ))

        # Detect exaggerations
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=exaggeration_patterns,
            issue_type=QualityIssue.EXAGGERATION,
            severity=IssueSeverity.MEDIUM,
            language=language,
        ))

        # Detect alarmist language
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=alarmist_patterns,
            issue_type=QualityIssue.ALARMIST,
            severity=IssueSeverity.MEDIUM,
            language=language,
        ))

        # Detect comparisons
        issues.extend(self._detect_pattern_issues(
            text=text_lower,
            original_text=message_text,
            patterns=comparison_patterns,
            issue_type=QualityIssue.COMPARISON,
            severity=IssueSeverity.HIGH,
            language=language,
        ))

        return issues

    def _detect_pattern_issues(
        self,
        text: str,
        original_text: str,
        patterns: list[str],
        issue_type: QualityIssue,
        severity: IssueSeverity,
        language: Language,
    ) -> list[QualityIssueDetail]:
        """Detect issues matching specific patterns.

        Args:
            text: Lowercase text to search
            original_text: Original message text (for position tracking)
            patterns: List of regex patterns to match
            issue_type: Type of quality issue
            severity: Severity level for matched issues
            language: Language for templates

        Returns:
            List of detected issues
        """
        issues: list[QualityIssueDetail] = []
        template = QUALITY_ISSUE_TEMPLATES[language][issue_type]

        for pattern in patterns:
            try:
                for match in re.finditer(pattern, text, re.IGNORECASE):
                    # Get the matched text from original (preserves case)
                    start_pos = match.start()
                    end_pos = match.end()
                    matched_text = original_text[start_pos:end_pos]

                    issues.append(QualityIssueDetail(
                        issue_type=issue_type,
                        severity=severity,
                        description=template["description"],
                        original_text=matched_text,
                        position_start=start_pos,
                        position_end=end_pos,
                        suggestion=template["suggestion"],
                    ))
            except re.error:
                # Skip invalid patterns
                continue

        return issues

    def _check_positive_opening(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message has a positive opening.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message has a positive opening
        """
        text_lower = message_text.lower().strip()
        patterns = (
            POSITIVE_OPENINGS_FR if language == Language.FR
            else POSITIVE_OPENINGS_EN
        )

        for pattern in patterns:
            if re.search(pattern, text_lower, re.IGNORECASE):
                return True

        return False

    def _check_solution_closing(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message has a solution-oriented closing.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message has a solution-oriented closing
        """
        text_lower = message_text.lower().strip()
        patterns = (
            SOLUTION_CLOSINGS_FR if language == Language.FR
            else SOLUTION_CLOSINGS_EN
        )

        for pattern in patterns:
            if re.search(pattern, text_lower, re.IGNORECASE):
                return True

        return False

    def _check_factual_basis(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message appears to be factual rather than emotional.

        A message is considered factual if it doesn't contain excessive
        emotional language or subjective judgments.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message appears to be factual
        """
        text_lower = message_text.lower()

        # Emotional/subjective patterns that suggest non-factual content
        emotional_patterns_en = [
            r"\b(?:i\s+feel|i\s+think|i\s+believe|in\s+my\s+opinion)\b",
            r"\b(?:horrible|terrible|awful|amazing|perfect)\b",
            r"\b(?:love|hate|can\'?t\s+stand)\b",
        ]
        emotional_patterns_fr = [
            r"\b(?:je\s+sens|je\s+pense|je\s+crois|à\s+mon\s+avis)\b",
            r"\b(?:horrible|terrible|affreux|incroyable|parfait)\b",
            r"\b(?:adore|déteste|ne\s+supporte\s+pas)\b",
        ]

        patterns = (
            emotional_patterns_fr if language == Language.FR
            else emotional_patterns_en
        )

        emotional_count = 0
        for pattern in patterns:
            matches = re.findall(pattern, text_lower, re.IGNORECASE)
            emotional_count += len(matches)

        # Consider factual if less than 2 emotional indicators
        return emotional_count < 2

    def _create_structural_issue(
        self,
        issue_type: QualityIssue,
        language: Language,
        message_text: str,
    ) -> QualityIssueDetail:
        """Create a structural issue detail.

        Used for issues that apply to the whole message structure
        rather than specific text fragments.

        Args:
            issue_type: Type of quality issue
            language: Language for templates
            message_text: The full message text

        Returns:
            QualityIssueDetail for the structural issue
        """
        template = QUALITY_ISSUE_TEMPLATES[language][issue_type]

        return QualityIssueDetail(
            issue_type=issue_type,
            severity=IssueSeverity.LOW,
            description=template["description"],
            original_text="[Entire message]" if language == Language.EN else "[Message entier]",
            position_start=0,
            position_end=len(message_text),
            suggestion=template["suggestion"],
        )

    def _calculate_quality_score(
        self,
        issues: list[QualityIssueDetail],
        has_positive_opening: bool,
        has_solution_focus: bool,
        has_factual_basis: bool,
    ) -> int:
        """Calculate overall quality score for the message.

        The score starts at 100 and is reduced based on detected issues
        and missing structural elements.

        Args:
            issues: List of detected quality issues
            has_positive_opening: Whether message has positive opening
            has_solution_focus: Whether message has solution-oriented closing
            has_factual_basis: Whether message is factual

        Returns:
            Quality score from 0 to 100
        """
        score = 100

        # Deduct points for each issue based on severity
        severity_deductions = {
            IssueSeverity.LOW: 5,
            IssueSeverity.MEDIUM: 10,
            IssueSeverity.HIGH: 15,
            IssueSeverity.CRITICAL: 25,
        }

        for issue in issues:
            score -= severity_deductions.get(issue.severity, 10)

        # Bonus points for good structure
        if has_positive_opening:
            score = min(100, score + 5)
        if has_solution_focus:
            score = min(100, score + 5)
        if has_factual_basis:
            score = min(100, score + 5)

        # Ensure score stays within bounds
        return max(0, min(100, score))

    def _generate_rewrite_suggestions(
        self,
        message_text: str,
        issues: list[QualityIssueDetail],
        language: Language,
    ) -> list[RewriteSuggestion]:
        """Generate rewrite suggestions for problematic text.

        Creates specific rewrite suggestions that use 'I' language
        transformation and sandwich method principles.

        Args:
            message_text: The original message text
            issues: List of detected quality issues
            language: Language for suggestions

        Returns:
            List of rewrite suggestions
        """
        suggestions: list[RewriteSuggestion] = []

        # Generate suggestion for the overall message if there are issues
        if issues:
            suggestion = self._create_message_rewrite(
                message_text=message_text,
                issues=issues,
                language=language,
            )
            if suggestion:
                suggestions.append(suggestion)

        return suggestions

    def _create_message_rewrite(
        self,
        message_text: str,
        issues: list[QualityIssueDetail],
        language: Language,
    ) -> Optional[RewriteSuggestion]:
        """Create a rewrite suggestion for the message.

        This is a placeholder implementation. In production, this would
        use AI to generate contextually appropriate rewrites.

        Args:
            message_text: The original message text
            issues: List of detected quality issues
            language: Language for the suggestion

        Returns:
            RewriteSuggestion or None if no rewrite is needed
        """
        if not issues:
            return None

        # Get the most severe issue type for explanation
        most_severe = max(issues, key=lambda i: {
            IssueSeverity.CRITICAL: 4,
            IssueSeverity.HIGH: 3,
            IssueSeverity.MEDIUM: 2,
            IssueSeverity.LOW: 1,
        }[i.severity])

        if language == Language.FR:
            explanation = (
                f"Cette réécriture aborde le problème de '{most_severe.issue_type.value}' "
                f"en utilisant le langage 'Je' et une approche constructive."
            )
            suggested_prefix = "J'ai remarqué que "
        else:
            explanation = (
                f"This rewrite addresses the '{most_severe.issue_type.value}' issue "
                f"by using 'I' language and a constructive approach."
            )
            suggested_prefix = "I noticed that "

        # Create a simple rewrite suggestion
        # In production, this would be generated by AI
        suggested_text = suggested_prefix + message_text.lower()

        return RewriteSuggestion(
            original_text=message_text,
            suggested_text=suggested_text,
            explanation=explanation,
            uses_i_language=True,
            has_sandwich_structure=False,
            confidence_score=0.7,
        )

    def _generate_analysis_notes(
        self,
        issues: list[QualityIssueDetail],
        quality_score: int,
        language: Language,
    ) -> str:
        """Generate human-readable analysis notes.

        Args:
            issues: List of detected quality issues
            quality_score: The calculated quality score
            language: Language for the notes

        Returns:
            Analysis notes string
        """
        issue_count = len(issues)

        if language == Language.FR:
            if quality_score >= 90:
                return "Excellent! Ce message suit bien les standards 'Bonne Message'."
            elif quality_score >= 70:
                return f"Bon message avec {issue_count} point(s) d'amélioration."
            elif quality_score >= 50:
                return f"Message acceptable mais {issue_count} problème(s) à corriger."
            else:
                return f"Ce message nécessite des révisions. {issue_count} problème(s) détecté(s)."
        else:
            if quality_score >= 90:
                return "Excellent! This message follows 'Bonne Message' standards well."
            elif quality_score >= 70:
                return f"Good message with {issue_count} area(s) for improvement."
            elif quality_score >= 50:
                return f"Acceptable message but {issue_count} issue(s) to address."
            else:
                return f"This message needs revision. {issue_count} issue(s) detected."

    async def _persist_analysis(
        self,
        request: MessageAnalysisRequest,
        user_id: UUID,
        quality_score: int,
        is_acceptable: bool,
        issues: list[QualityIssueDetail],
        has_positive_opening: bool,
        has_factual_basis: bool,
        has_solution_focus: bool,
        rewrite_suggested: bool,
        analysis_notes: str,
    ) -> MessageAnalysis:
        """Persist the analysis results to the database.

        Args:
            request: The original analysis request
            user_id: ID of the user who requested the analysis
            quality_score: Calculated quality score
            is_acceptable: Whether the message is acceptable
            issues: List of detected issues
            has_positive_opening: Whether message has positive opening
            has_factual_basis: Whether message is factual
            has_solution_focus: Whether message has solution-oriented closing
            rewrite_suggested: Whether a rewrite was suggested
            analysis_notes: Analysis notes

        Returns:
            The persisted MessageAnalysis record
        """
        analysis = MessageAnalysis(
            user_id=user_id,
            child_id=request.child_id,
            message_text=request.message_text,
            language=request.language.value,
            context=request.context.value,
            quality_score=quality_score,
            is_acceptable=is_acceptable,
            issues_detected=[issue.issue_type.value for issue in issues],
            has_positive_opening=has_positive_opening,
            has_factual_basis=has_factual_basis,
            has_solution_focus=has_solution_focus,
            rewrite_suggested=rewrite_suggested,
            rewrite_accepted=None,
            analysis_notes=analysis_notes,
        )

        self.db.add(analysis)
        await self.db.commit()
        await self.db.refresh(analysis)

        return analysis
