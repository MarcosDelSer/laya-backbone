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

from app.models.message_quality import MessageAnalysis, MessageTemplate, TrainingExample
from app.schemas.message_quality import (
    IssueSeverity,
    Language,
    MessageAnalysisRequest,
    MessageAnalysisResponse,
    MessageContext,
    MessageTemplateListResponse,
    MessageTemplateRequest,
    MessageTemplateResponse,
    QualityIssue,
    QualityIssueDetail,
    RewriteSuggestion,
    TemplateCategory,
    TrainingExampleListResponse,
    TrainingExampleResponse,
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


class TemplateNotFoundError(MessageQualityServiceError):
    """Raised when a requested template is not found."""

    pass


class InvalidTemplateError(MessageQualityServiceError):
    """Raised when template data is invalid."""

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

    def _check_single_objective(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message focuses on a single main objective.

        Messages with too many topics can overwhelm parents. This method
        detects if a message tries to address multiple unrelated concerns.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message has a single main objective
        """
        text_lower = message_text.lower()

        # Patterns that indicate multiple topics being addressed
        if language == Language.FR:
            multiple_topic_indicators = [
                r"\bégalement\b.*\bje\s+(?:voulais|voudrais|dois)\b",
                r"\baussi\b.*\bun\s+autre\s+(?:point|sujet|chose)\b",
                r"\bde\s+plus\b.*\bje\s+(?:voulais|dois)\s+(?:mentionner|signaler)\b",
                r"\bpremièrement\b.*\bdeuxièmement\b",
                r"\bd\'?abord\b.*\bensuite\b.*\bfinalement\b",
                r"\bplusieurs\s+(?:choses|points|sujets)\s+à\s+(?:aborder|discuter)\b",
                r"\bje\s+(?:voulais|voudrais)\s+(?:aussi|également)\s+(?:mentionner|signaler|parler)\b",
            ]
            # Count distinct topic markers
            topic_markers = [
                r"\b(?:concernant|au\s+sujet\s+de|à\s+propos\s+de)\b",
                r"\b(?:un\s+autre\s+(?:point|sujet))\b",
            ]
        else:
            multiple_topic_indicators = [
                r"\balso\b.*\bi\s+(?:wanted|need|would\s+like)\s+to\b",
                r"\badditionally\b.*\bi\s+(?:wanted|need)\s+to\s+(?:mention|discuss)\b",
                r"\bfirst(?:ly)?\b.*\bsecond(?:ly)?\b",
                r"\bfirst\b.*\bthen\b.*\bfinally\b",
                r"\bseveral\s+(?:things|points|topics)\s+to\s+(?:discuss|address)\b",
                r"\bi\s+(?:also|additionally)\s+(?:wanted|need)\s+to\s+(?:mention|discuss|bring\s+up)\b",
                r"\bon\s+(?:another|a\s+different)\s+(?:note|topic|subject)\b",
            ]
            # Count distinct topic markers
            topic_markers = [
                r"\b(?:regarding|concerning|about|on\s+the\s+topic\s+of)\b",
                r"\b(?:another\s+(?:point|topic|matter))\b",
            ]

        # Check for multiple topic indicator phrases
        for pattern in multiple_topic_indicators:
            if re.search(pattern, text_lower, re.IGNORECASE):
                return False

        # Count topic markers
        topic_count = 0
        for pattern in topic_markers:
            matches = re.findall(pattern, text_lower, re.IGNORECASE)
            topic_count += len(matches)

        # If more than 2 topic markers, likely multiple objectives
        if topic_count > 2:
            return False

        # Count sentence count as an indicator (very long messages often have multiple topics)
        sentences = re.split(r'[.!?]+', message_text)
        sentences = [s.strip() for s in sentences if s.strip()]

        # If message is very long with many sentences, check for topic diversity
        if len(sentences) > 8:
            # Heuristic: long messages are more likely to have multiple topics
            # unless they're narratively connected
            return False

        return True

    def _check_neutral_tone(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message maintains a neutral, non-accusatory tone.

        A neutral tone avoids accusatory language, emotional escalation,
        and judgmental statements. It focuses on facts and observations.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message maintains a neutral tone
        """
        text_lower = message_text.lower()

        # Patterns indicating non-neutral (accusatory, judgmental, emotional) tone
        if language == Language.FR:
            non_neutral_patterns = [
                # Accusatory patterns
                r"\bvous\s+(?:devez|devriez|auriez\s+dû)\b",
                r"\bc\'?est\s+(?:votre|de\s+votre)\s+(?:faute|responsabilité)\b",
                r"\bpourquoi\s+(?:vous|n\'?avez-vous)\b",
                # Emotional escalation
                r"\b(?:inacceptable|inadmissible|scandaleux|honteux)\b",
                r"\b(?:je\s+suis\s+(?:très\s+)?(?:déçu|frustré|fâché|en\s+colère))\b",
                r"\b(?:franchement|sincèrement)\s+(?:déçu|frustré)\b",
                # Judgmental patterns
                r"\b(?:votre\s+enfant\s+est\s+(?:toujours|jamais))\b",
                r"\b(?:il|elle)\s+(?:est|semble)\s+(?:être\s+)?(?:paresseux|difficile|méchant)\b",
                # Threatening or ultimatum language
                r"\b(?:si\s+(?:cela|ça)\s+continue|sinon|autrement)\b",
                r"\b(?:nous\s+(?:devrons|serons\s+obligés\s+de))\b",
            ]
        else:
            non_neutral_patterns = [
                # Accusatory patterns
                r"\byou\s+(?:need\s+to|have\s+to|must|should\s+have)\b",
                r"\b(?:it\'?s|this\s+is)\s+(?:your|the\s+parent\'?s?)\s+(?:fault|responsibility)\b",
                r"\bwhy\s+(?:didn\'?t|don\'?t|can\'?t|won\'?t)\s+you\b",
                # Emotional escalation
                r"\b(?:unacceptable|inexcusable|outrageous|shocking|appalling)\b",
                r"\b(?:i\'?m\s+(?:very\s+)?(?:disappointed|frustrated|upset|angry))\b",
                r"\b(?:frankly|honestly)\s+(?:disappointed|frustrated)\b",
                # Judgmental patterns
                r"\byour\s+child\s+(?:is\s+)?(?:always|never)\b",
                r"\b(?:he|she)\s+(?:is|seems)\s+(?:to\s+be\s+)?(?:lazy|difficult|bad|naughty)\b",
                # Threatening or ultimatum language
                r"\b(?:if\s+this\s+continues|otherwise|or\s+else)\b",
                r"\b(?:we\s+will\s+(?:have\s+to|be\s+forced\s+to))\b",
            ]

        # Count non-neutral patterns found
        non_neutral_count = 0
        for pattern in non_neutral_patterns:
            if re.search(pattern, text_lower, re.IGNORECASE):
                non_neutral_count += 1

        # Consider neutral if no more than 1 minor non-neutral indicator
        return non_neutral_count == 0

    def _check_collaborative_approach(
        self,
        message_text: str,
        language: Language,
    ) -> bool:
        """Check if message uses a collaborative, partnership-focused approach.

        A collaborative approach invites dialogue, focuses on working together,
        and emphasizes partnership between educators and parents.

        Args:
            message_text: The message text to check
            language: Language of the message

        Returns:
            True if the message uses a collaborative approach
        """
        text_lower = message_text.lower()

        # Patterns indicating collaborative language
        if language == Language.FR:
            collaborative_patterns = [
                # Partnership language
                r"\b(?:ensemble|travailler\s+ensemble|collaborer)\b",
                r"\b(?:partenariat|équipe|collaboration)\b",
                # Inviting dialogue
                r"\b(?:n\'?hésitez\s+pas|je\s+serais\s+(?:ravi|heureux)\s+(?:de|d\'?))\b",
                r"\b(?:discutons|parlons|échangeons)\b",
                r"\b(?:qu\'?en\s+pensez-vous|votre\s+avis)\b",
                # Solution-focused
                r"\b(?:nous\s+pouvons|nous\s+pourrions)\b",
                r"\b(?:essayons|trouvons\s+(?:une\s+)?solution)\b",
                # Supportive language
                r"\b(?:je\s+(?:vous\s+)?soutiens|nous\s+(?:vous\s+)?soutenons)\b",
                r"\b(?:comment\s+(?:puis-je|pouvons-nous)\s+(?:vous\s+)?aider)\b",
            ]
        else:
            collaborative_patterns = [
                # Partnership language
                r"\b(?:together|work\s+together|collaborate|partnership)\b",
                r"\b(?:as\s+a\s+team|team\s+effort)\b",
                # Inviting dialogue
                r"\b(?:please\s+(?:feel\s+free|don\'?t\s+hesitate)\s+to)\b",
                r"\b(?:i\'?d\s+(?:love|like)\s+to\s+(?:discuss|talk|hear))\b",
                r"\b(?:let\'?s\s+(?:discuss|talk|chat))\b",
                r"\b(?:what\s+do\s+you\s+think|your\s+(?:thoughts|input|feedback))\b",
                # Solution-focused
                r"\b(?:we\s+(?:can|could)|let\'?s\s+(?:try|find))\b",
                r"\b(?:working\s+(?:together|with\s+you))\b",
                # Supportive language
                r"\b(?:i\s+(?:support|am\s+here\s+to\s+(?:help|support)))\b",
                r"\b(?:how\s+can\s+(?:i|we)\s+(?:help|support))\b",
            ]

        # Check for collaborative patterns
        collaborative_count = 0
        for pattern in collaborative_patterns:
            if re.search(pattern, text_lower, re.IGNORECASE):
                collaborative_count += 1

        # Message is collaborative if it has at least one collaborative indicator
        # For shorter messages (< 100 chars), we're more lenient
        if len(message_text) < 100:
            return collaborative_count >= 0  # Always pass for very short messages
        elif len(message_text) < 300:
            return collaborative_count >= 1
        else:
            return collaborative_count >= 1

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

    def calculate_quality_score(
        self,
        message_text: str,
        language: Language = Language.EN,
    ) -> dict:
        """Calculate quality score with comprehensive message structure validation.

        Public method that performs complete message analysis including:
        - Single objective validation (message focuses on one main point)
        - Factual basis validation (objective, non-emotional content)
        - Neutral tone validation (avoids accusatory or judgmental language)
        - Collaborative approach validation (solution-oriented, partnership-focused)

        Args:
            message_text: The message text to analyze
            language: Language of the message (default: English)

        Returns:
            Dictionary containing:
                - quality_score: Overall quality score (0-100)
                - has_single_objective: Whether message has one main focus
                - has_factual_basis: Whether message is factual
                - has_neutral_tone: Whether message has neutral tone
                - has_collaborative_approach: Whether message invites collaboration
                - issues: List of detected quality issues
                - validation_details: Detailed validation results

        Raises:
            InvalidMessageError: When the message text is invalid or empty
        """
        # Validate message text
        if not message_text or not message_text.strip():
            raise InvalidMessageError("Message text cannot be empty")

        message_text = message_text.strip()

        # Detect all quality issues
        issues = self._detect_issues(message_text, language)

        # Perform structure validations
        has_single_objective = self._check_single_objective(message_text, language)
        has_factual_basis = self._check_factual_basis(message_text, language)
        has_neutral_tone = self._check_neutral_tone(message_text, language)
        has_collaborative_approach = self._check_collaborative_approach(message_text, language)
        has_positive_opening = self._check_positive_opening(message_text, language)
        has_solution_focus = self._check_solution_closing(message_text, language)

        # Add structural issues if validation fails
        if not has_single_objective:
            issues.append(self._create_structural_issue(
                QualityIssue.MULTIPLE_OBJECTIVES,
                language,
                message_text,
            ))

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

        if not has_neutral_tone:
            issues.append(self._create_structural_issue(
                QualityIssue.NEGATIVE_TONE,
                language,
                message_text,
            ))

        # Calculate the quality score
        quality_score = self._calculate_quality_score(
            issues=issues,
            has_positive_opening=has_positive_opening,
            has_solution_focus=has_solution_focus,
            has_factual_basis=has_factual_basis,
            has_neutral_tone=has_neutral_tone,
            has_collaborative_approach=has_collaborative_approach,
            has_single_objective=has_single_objective,
        )

        return {
            "quality_score": quality_score,
            "has_single_objective": has_single_objective,
            "has_factual_basis": has_factual_basis,
            "has_neutral_tone": has_neutral_tone,
            "has_collaborative_approach": has_collaborative_approach,
            "has_positive_opening": has_positive_opening,
            "has_solution_focus": has_solution_focus,
            "issues": issues,
            "validation_details": {
                "single_objective": {
                    "passed": has_single_objective,
                    "description": (
                        "Message focuses on one main topic" if language == Language.EN
                        else "Le message se concentre sur un sujet principal"
                    ) if has_single_objective else (
                        "Message contains multiple topics - consider splitting"
                        if language == Language.EN
                        else "Le message contient plusieurs sujets - envisagez de le diviser"
                    ),
                },
                "factual_basis": {
                    "passed": has_factual_basis,
                    "description": (
                        "Message is based on factual observations" if language == Language.EN
                        else "Le message est basé sur des observations factuelles"
                    ) if has_factual_basis else (
                        "Message contains emotional or subjective language"
                        if language == Language.EN
                        else "Le message contient un langage émotionnel ou subjectif"
                    ),
                },
                "neutral_tone": {
                    "passed": has_neutral_tone,
                    "description": (
                        "Message maintains a neutral, non-accusatory tone" if language == Language.EN
                        else "Le message maintient un ton neutre et non accusateur"
                    ) if has_neutral_tone else (
                        "Message tone could be perceived as accusatory or negative"
                        if language == Language.EN
                        else "Le ton du message pourrait être perçu comme accusateur ou négatif"
                    ),
                },
                "collaborative_approach": {
                    "passed": has_collaborative_approach,
                    "description": (
                        "Message invites partnership and collaboration" if language == Language.EN
                        else "Le message invite au partenariat et à la collaboration"
                    ) if has_collaborative_approach else (
                        "Message could be more collaborative and solution-focused"
                        if language == Language.EN
                        else "Le message pourrait être plus collaboratif et axé sur les solutions"
                    ),
                },
            },
        }

    def _calculate_quality_score(
        self,
        issues: list[QualityIssueDetail],
        has_positive_opening: bool,
        has_solution_focus: bool,
        has_factual_basis: bool,
        has_neutral_tone: bool = True,
        has_collaborative_approach: bool = True,
        has_single_objective: bool = True,
    ) -> int:
        """Calculate overall quality score for the message.

        The score starts at 100 and is reduced based on detected issues
        and missing structural elements. Bonus points are awarded for
        good message structure.

        Args:
            issues: List of detected quality issues
            has_positive_opening: Whether message has positive opening
            has_solution_focus: Whether message has solution-oriented closing
            has_factual_basis: Whether message is factual
            has_neutral_tone: Whether message has neutral tone
            has_collaborative_approach: Whether message invites collaboration
            has_single_objective: Whether message has single focus

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
        if has_neutral_tone:
            score = min(100, score + 3)
        if has_collaborative_approach:
            score = min(100, score + 3)
        if has_single_objective:
            score = min(100, score + 2)

        # Ensure score stays within bounds
        return max(0, min(100, score))

    def suggest_rewrite(
        self,
        message_text: str,
        issues: list[QualityIssueDetail],
        language: Language,
        child_name: Optional[str] = None,
    ) -> RewriteSuggestion:
        """Generate a rewrite suggestion using 'I' language and sandwich method.

        Transforms problematic messages into positive, constructive communication
        using two key techniques:
        1. 'I' language transformation - replaces accusatory 'you' statements
           with 'I' observations (e.g., "You never..." becomes "I noticed...")
        2. Sandwich method - structures message with:
           - Positive opening (genuine acknowledgment)
           - Factual concern (specific observation without judgment)
           - Solution-oriented closing (collaborative next steps)

        Args:
            message_text: The original message text to rewrite
            issues: List of detected quality issues to address
            language: Language for the rewritten message
            child_name: Optional child's name for personalization

        Returns:
            RewriteSuggestion with the improved message and explanation
        """
        if not message_text or not message_text.strip():
            # Return minimal suggestion for empty input
            return RewriteSuggestion(
                original_text=message_text or "",
                suggested_text="",
                explanation="No message text provided to rewrite.",
                uses_i_language=False,
                has_sandwich_structure=False,
                confidence_score=0.0,
            )

        # Extract the core concern from the original message
        core_concern = self._extract_core_concern(message_text, language)

        # Build sandwich structure components
        positive_opening = self._generate_positive_opening(
            message_text=message_text,
            language=language,
            child_name=child_name,
        )

        factual_observation = self._transform_to_i_language(
            text=core_concern,
            issues=issues,
            language=language,
        )

        solution_closing = self._generate_solution_closing(
            issues=issues,
            language=language,
        )

        # Combine into sandwich structure
        suggested_text = self._build_sandwich_message(
            positive_opening=positive_opening,
            factual_observation=factual_observation,
            solution_closing=solution_closing,
            language=language,
        )

        # Generate explanation based on issues addressed
        explanation = self._generate_rewrite_explanation(
            issues=issues,
            language=language,
        )

        # Calculate confidence based on number and severity of issues
        confidence_score = self._calculate_rewrite_confidence(issues)

        return RewriteSuggestion(
            original_text=message_text,
            suggested_text=suggested_text,
            explanation=explanation,
            uses_i_language=True,
            has_sandwich_structure=True,
            confidence_score=confidence_score,
        )

    def _extract_core_concern(
        self,
        message_text: str,
        language: Language,
    ) -> str:
        """Extract the core concern or observation from a message.

        Strips away accusatory language and emotional content to find
        the underlying factual observation.

        Args:
            message_text: The original message text
            language: Language of the message

        Returns:
            The core concern extracted from the message
        """
        text = message_text.strip()

        # Remove common accusatory openings
        if language == Language.FR:
            accusatory_starts = [
                r"^vous\s+(?:devez|devriez|n'avez\s+pas|avez\s+oublié)",
                r"^votre\s+enfant\s+(?:ne\s+)?(?:fait|peut|veut)",
                r"^il\s+faut\s+que\s+vous",
                r"^pourquoi\s+(?:vous|votre)",
            ]
        else:
            accusatory_starts = [
                r"^you\s+(?:need\s+to|have\s+to|should|must|never|always|don't|didn't|can't)",
                r"^your\s+child\s+(?:is\s+)?(?:always|never|can't|won't)",
                r"^why\s+(?:don't|didn't|can't|won't)\s+you",
            ]

        result = text
        for pattern in accusatory_starts:
            result = re.sub(pattern, "", result, flags=re.IGNORECASE).strip()

        # If we stripped too much, return original with first char lowered
        if len(result) < 10 and len(text) > 10:
            result = text[0].lower() + text[1:] if text else text

        return result

    def _transform_to_i_language(
        self,
        text: str,
        issues: list[QualityIssueDetail],
        language: Language,
    ) -> str:
        """Transform text to use 'I' language instead of accusatory 'you'.

        Applies 'I' language transformation patterns to convert accusatory
        statements into observations from the speaker's perspective.

        Args:
            text: The text to transform
            issues: Detected issues that may guide transformation
            language: Language for the transformation

        Returns:
            Text transformed to use 'I' language
        """
        result = text

        if language == Language.FR:
            # French 'I' language transformations
            transformations = [
                # "Vous devez/devriez" -> "J'aimerais"
                (r"\bvous\s+devez\b", "j'aimerais que nous"),
                (r"\bvous\s+devriez\b", "je suggère que vous"),
                # "Vous n'avez pas" -> "J'ai remarqué que"
                (r"\bvous\s+n'avez\s+pas\b", "j'ai remarqué que vous n'avez pas"),
                # "Votre enfant est/fait" -> "J'ai observé que"
                (r"\bvotre\s+enfant\s+(?:est|fait)\b", "j'ai observé que votre enfant"),
                # "Il faut que vous" -> "Je vous invite à"
                (r"\bil\s+faut\s+que\s+vous\b", "je vous invite à"),
                # Exaggerations
                (r"\btoujours\b", "souvent"),
                (r"\bjamais\b", "rarement"),
            ]
        else:
            # English 'I' language transformations
            transformations = [
                # "You need to/have to" -> "I would like"
                (r"\byou\s+need\s+to\b", "I would like us to"),
                (r"\byou\s+have\s+to\b", "I hope we can"),
                (r"\byou\s+should\b", "I suggest"),
                (r"\byou\s+must\b", "I believe it would help if we"),
                # "You don't/didn't/can't" -> "I noticed"
                (r"\byou\s+don't\b", "I noticed that you don't"),
                (r"\byou\s+didn't\b", "I noticed that you didn't"),
                (r"\byou\s+can't\b", "I understand that you may find it difficult to"),
                (r"\byou\s+won't\b", "I've observed that you haven't"),
                # "Your child is/does" -> "I observed"
                (r"\byour\s+child\s+(?:is|does)\b", "I observed that your child"),
                (r"\byour\s+child\s+always\b", "I've noticed your child often"),
                (r"\byour\s+child\s+never\b", "I've noticed your child rarely"),
                # Exaggerations
                (r"\balways\b", "often"),
                (r"\bnever\b", "rarely"),
                (r"\bevery\s+time\b", "frequently"),
            ]

        for pattern, replacement in transformations:
            result = re.sub(pattern, replacement, result, flags=re.IGNORECASE)

        # Ensure first character is properly capitalized
        if result:
            result = result[0].upper() + result[1:] if len(result) > 1 else result.upper()

        return result

    def _generate_positive_opening(
        self,
        message_text: str,
        language: Language,
        child_name: Optional[str] = None,
    ) -> str:
        """Generate a positive opening for the sandwich method.

        Creates a genuine positive acknowledgment to start the message
        on a supportive note.

        Args:
            message_text: The original message for context
            language: Language for the opening
            child_name: Optional child's name for personalization

        Returns:
            A positive opening statement
        """
        # Use child name if provided, otherwise generic
        name_ref = child_name or (
            "votre enfant" if language == Language.FR else "your child"
        )

        if language == Language.FR:
            openings = [
                f"J'apprécie vraiment votre engagement envers {name_ref}.",
                f"Merci de prendre le temps de lire ce message concernant {name_ref}.",
                f"Je suis reconnaissant(e) de pouvoir travailler avec vous pour soutenir {name_ref}.",
                f"Nous apprécions toujours votre collaboration pour le bien-être de {name_ref}.",
            ]
        else:
            openings = [
                f"I truly appreciate your dedication to {name_ref}.",
                f"Thank you for taking the time to read this message about {name_ref}.",
                f"I'm grateful for the opportunity to work with you to support {name_ref}.",
                f"We always appreciate your partnership in {name_ref}'s well-being.",
            ]

        # Select opening based on message content hash for consistency
        opening_index = hash(message_text) % len(openings)
        return openings[opening_index]

    def _generate_solution_closing(
        self,
        issues: list[QualityIssueDetail],
        language: Language,
    ) -> str:
        """Generate a solution-oriented closing for the sandwich method.

        Creates a collaborative, forward-looking closing that invites
        partnership and focuses on solutions.

        Args:
            issues: Detected issues that may inform the solution approach
            language: Language for the closing

        Returns:
            A solution-oriented closing statement
        """
        # Determine if this is a behavior concern based on issues
        is_behavior_concern = any(
            issue.issue_type in [
                QualityIssue.BLAME_SHAME,
                QualityIssue.JUDGMENTAL_LABEL,
            ]
            for issue in issues
        )

        if language == Language.FR:
            if is_behavior_concern:
                closing = (
                    "Je serais ravi(e) de discuter ensemble de stratégies "
                    "que nous pourrions essayer. N'hésitez pas à me contacter "
                    "pour en parler davantage."
                )
            else:
                closing = (
                    "Travaillons ensemble pour trouver la meilleure approche. "
                    "Je suis disponible pour en discuter quand cela vous convient."
                )
        else:
            if is_behavior_concern:
                closing = (
                    "I would love to discuss strategies we could try together. "
                    "Please feel free to reach out so we can talk more about this."
                )
            else:
                closing = (
                    "Let's work together to find the best approach. "
                    "I'm available to discuss this whenever it's convenient for you."
                )

        return closing

    def _build_sandwich_message(
        self,
        positive_opening: str,
        factual_observation: str,
        solution_closing: str,
        language: Language,
    ) -> str:
        """Build the final message using sandwich structure.

        Combines the three parts of the sandwich method into a cohesive,
        well-structured message.

        Args:
            positive_opening: The positive opening statement
            factual_observation: The factual observation (core concern)
            solution_closing: The solution-oriented closing
            language: Language for connectors and formatting

        Returns:
            The complete sandwich-structured message
        """
        if language == Language.FR:
            connector = "J'aimerais partager une observation : "
        else:
            connector = "I wanted to share an observation: "

        # Ensure observation starts with lowercase after connector
        if factual_observation:
            observation = factual_observation[0].lower() + factual_observation[1:]
        else:
            observation = factual_observation

        # Build the sandwich structure with proper spacing
        parts = [
            positive_opening,
            connector + observation,
            solution_closing,
        ]

        return "\n\n".join(parts)

    def _generate_rewrite_explanation(
        self,
        issues: list[QualityIssueDetail],
        language: Language,
    ) -> str:
        """Generate an explanation of the rewrite changes.

        Explains what improvements were made and why, helping educators
        learn to write better messages.

        Args:
            issues: The issues that were addressed
            language: Language for the explanation

        Returns:
            Explanation of the rewrite improvements
        """
        if not issues:
            if language == Language.FR:
                return "Le message a été restructuré selon la méthode sandwich pour une communication plus positive."
            else:
                return "The message has been restructured using the sandwich method for more positive communication."

        # Get unique issue types
        issue_types = list(set(issue.issue_type for issue in issues))

        if language == Language.FR:
            improvements = []
            for issue_type in issue_types[:3]:  # Limit to top 3 for readability
                if issue_type == QualityIssue.ACCUSATORY_YOU:
                    improvements.append("le langage accusateur 'vous' a été transformé en langage 'Je'")
                elif issue_type == QualityIssue.JUDGMENTAL_LABEL:
                    improvements.append("les étiquettes jugeantes ont été remplacées par des observations factuelles")
                elif issue_type == QualityIssue.BLAME_SHAME:
                    improvements.append("le blâme a été remplacé par une approche collaborative")
                elif issue_type == QualityIssue.EXAGGERATION:
                    improvements.append("les exagérations ont été atténuées avec des termes plus précis")
                elif issue_type == QualityIssue.MISSING_POSITIVE:
                    improvements.append("une ouverture positive a été ajoutée")
                elif issue_type == QualityIssue.MISSING_SOLUTION:
                    improvements.append("une conclusion orientée solution a été ajoutée")
                else:
                    improvements.append(f"le problème '{issue_type.value}' a été corrigé")

            return (
                "Cette réécriture améliore le message en utilisant la méthode sandwich "
                f"(ouverture positive, observation factuelle, conclusion collaborative). "
                f"Améliorations spécifiques : {'; '.join(improvements)}."
            )
        else:
            improvements = []
            for issue_type in issue_types[:3]:  # Limit to top 3 for readability
                if issue_type == QualityIssue.ACCUSATORY_YOU:
                    improvements.append("accusatory 'you' language transformed to 'I' language")
                elif issue_type == QualityIssue.JUDGMENTAL_LABEL:
                    improvements.append("judgmental labels replaced with factual observations")
                elif issue_type == QualityIssue.BLAME_SHAME:
                    improvements.append("blame replaced with collaborative approach")
                elif issue_type == QualityIssue.EXAGGERATION:
                    improvements.append("exaggerations softened with more precise terms")
                elif issue_type == QualityIssue.MISSING_POSITIVE:
                    improvements.append("positive opening added")
                elif issue_type == QualityIssue.MISSING_SOLUTION:
                    improvements.append("solution-oriented closing added")
                else:
                    improvements.append(f"'{issue_type.value}' issue addressed")

            return (
                "This rewrite improves the message using the sandwich method "
                f"(positive opening, factual observation, collaborative closing). "
                f"Specific improvements: {'; '.join(improvements)}."
            )

    def _calculate_rewrite_confidence(
        self,
        issues: list[QualityIssueDetail],
    ) -> float:
        """Calculate confidence score for the rewrite suggestion.

        Higher confidence for simpler messages with fewer issues,
        lower confidence for complex messages with many issues.

        Args:
            issues: List of detected issues

        Returns:
            Confidence score between 0.0 and 1.0
        """
        if not issues:
            return 0.95

        # Base confidence starts high and decreases with issue count
        base_confidence = 0.9
        issue_penalty = len(issues) * 0.05

        # Higher severity issues reduce confidence more
        severity_penalty = 0.0
        for issue in issues:
            if issue.severity == IssueSeverity.CRITICAL:
                severity_penalty += 0.1
            elif issue.severity == IssueSeverity.HIGH:
                severity_penalty += 0.05
            elif issue.severity == IssueSeverity.MEDIUM:
                severity_penalty += 0.02

        confidence = base_confidence - issue_penalty - severity_penalty

        # Ensure confidence stays in valid range
        return max(0.5, min(1.0, confidence))

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
            suggestion = self.suggest_rewrite(
                message_text=message_text,
                issues=issues,
                language=language,
            )
            if suggestion and suggestion.suggested_text:
                suggestions.append(suggestion)

        return suggestions

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

    async def get_templates(
        self,
        language: Optional[Language] = None,
        category: Optional[TemplateCategory] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> MessageTemplateListResponse:
        """Get message templates with optional filtering.

        Retrieves message templates that educators can use as starting points
        for positive parent communication. Templates follow Quebec 'Bonne Message'
        standards and are available in both English and French.

        Args:
            language: Optional filter by language (en or fr)
            category: Optional filter by template category
            limit: Maximum number of templates to return (default: 20)
            offset: Number of templates to skip for pagination (default: 0)

        Returns:
            MessageTemplateListResponse with paginated list of templates
        """
        from sqlalchemy import select, func

        # Build base query
        query = select(MessageTemplate).where(MessageTemplate.is_active == True)

        # Apply language filter
        if language:
            query = query.where(MessageTemplate.language == language.value)

        # Apply category filter
        if category:
            query = query.where(MessageTemplate.category == category.value)

        # Get total count for pagination
        count_query = select(func.count()).select_from(
            query.subquery()
        )
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply ordering, limit and offset
        query = query.order_by(
            MessageTemplate.is_system.desc(),
            MessageTemplate.usage_count.desc(),
            MessageTemplate.created_at.desc(),
        ).offset(offset).limit(limit)

        result = await self.db.execute(query)
        templates = result.scalars().all()

        # Convert to response models
        items = [
            MessageTemplateResponse(
                id=template.id,
                title=template.title,
                content=template.content,
                category=TemplateCategory(template.category),
                language=Language(template.language),
                description=template.description,
                is_system=template.is_system,
                usage_count=template.usage_count,
                created_at=template.created_at,
                updated_at=template.updated_at,
            )
            for template in templates
        ]

        return MessageTemplateListResponse(
            items=items,
            total=total,
            page=offset // limit + 1 if limit > 0 else 1,
            page_size=limit,
            has_more=offset + len(items) < total,
        )

    async def create_template(
        self,
        request: MessageTemplateRequest,
        user: dict,
    ) -> MessageTemplateResponse:
        """Create a new custom message template.

        Creates a custom message template that educators can use as a starting
        point for positive parent communication. The template will be validated
        against Quebec 'Bonne Message' standards before creation.

        Args:
            request: The template creation request containing:
                - title: Title of the template
                - content: Template content with optional placeholders
                - category: Category of the template
                - language: Language of the template
                - description: Optional description of when to use this template
            user: Authenticated user creating the template

        Returns:
            MessageTemplateResponse with the created template

        Raises:
            InvalidTemplateError: When template data is invalid
        """
        # Validate template content
        if not request.title or not request.title.strip():
            raise InvalidTemplateError("Template title cannot be empty")

        if not request.content or not request.content.strip():
            raise InvalidTemplateError("Template content cannot be empty")

        # Get user ID from JWT token
        user_id = UUID(user.get("sub", user.get("user_id")))

        # Create the template
        template = MessageTemplate(
            title=request.title.strip(),
            content=request.content.strip(),
            category=request.category.value,
            language=request.language.value,
            description=request.description,
            is_system=False,
            is_active=True,
            usage_count=0,
            created_by=user_id,
        )

        self.db.add(template)
        await self.db.commit()
        await self.db.refresh(template)

        return MessageTemplateResponse(
            id=template.id,
            title=template.title,
            content=template.content,
            category=TemplateCategory(template.category),
            language=Language(template.language),
            description=template.description,
            is_system=template.is_system,
            usage_count=template.usage_count,
            created_at=template.created_at,
            updated_at=template.updated_at,
        )

    async def get_training_examples(
        self,
        language: Optional[Language] = None,
        issue_type: Optional[QualityIssue] = None,
        difficulty_level: Optional[str] = None,
        limit: int = 20,
        offset: int = 0,
    ) -> TrainingExampleListResponse:
        """Get training examples with optional filtering.

        Retrieves training examples that help educators learn to write better
        messages following Quebec 'Bonne Message' standards. Each example shows
        an original message with quality issues alongside an improved version
        with explanations.

        Args:
            language: Optional filter by language (en or fr)
            issue_type: Optional filter by quality issue type demonstrated
            difficulty_level: Optional filter by difficulty level
            limit: Maximum number of examples to return (default: 20)
            offset: Number of examples to skip for pagination (default: 0)

        Returns:
            TrainingExampleListResponse with paginated list of training examples
        """
        from sqlalchemy import select, func

        # Build base query
        query = select(TrainingExample).where(TrainingExample.is_active == True)

        # Apply language filter
        if language:
            query = query.where(TrainingExample.language == language.value)

        # Apply issue type filter (check if the issue is in the array)
        if issue_type:
            query = query.where(
                TrainingExample.issues_demonstrated.contains([issue_type.value])
            )

        # Apply difficulty level filter
        if difficulty_level:
            query = query.where(TrainingExample.difficulty_level == difficulty_level)

        # Get total count for pagination
        count_query = select(func.count()).select_from(
            query.subquery()
        )
        count_result = await self.db.execute(count_query)
        total = count_result.scalar() or 0

        # Apply ordering, limit and offset
        query = query.order_by(
            TrainingExample.view_count.desc(),
            TrainingExample.helpfulness_score.desc().nulls_last(),
            TrainingExample.created_at.desc(),
        ).offset(offset).limit(limit)

        result = await self.db.execute(query)
        examples = result.scalars().all()

        # Convert to response models
        items = [
            TrainingExampleResponse(
                id=example.id,
                original_message=example.original_message,
                improved_message=example.improved_message,
                issues_demonstrated=[
                    QualityIssue(issue) for issue in example.issues_demonstrated
                ],
                explanation=example.explanation,
                language=Language(example.language),
                difficulty_level=example.difficulty_level,
                created_at=example.created_at,
                updated_at=example.updated_at,
            )
            for example in examples
        ]

        return TrainingExampleListResponse(
            items=items,
            total=total,
            page=offset // limit + 1 if limit > 0 else 1,
            page_size=limit,
            has_more=offset + len(items) < total,
        )