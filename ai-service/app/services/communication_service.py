"""Service for parent communication and report generation.

Provides personalized, bilingual (English/French) daily reports and home activity
suggestions for parents. All content can be generated in either language to comply
with Quebec bilingual requirements.
"""

from datetime import date, datetime
from typing import Optional
from uuid import UUID, uuid4

from sqlalchemy import and_, cast, select, String
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.activity import Activity, ActivityParticipation
from app.models.communication import (
    CommunicationPreference,
    HomeActivity,
    ParentReport,
)
from app.schemas.communication import (
    DevelopmentalArea,
    GenerateReportRequest,
    HomeActivitiesListResponse,
    HomeActivityResponse,
    Language,
    ParentReportResponse,
)


# =============================================================================
# Bilingual Content Templates
# =============================================================================

# Templates for "no activities" message
NO_ACTIVITIES_TEMPLATES = {
    Language.EN: "No activities were recorded for this child on {date}.",
    Language.FR: "Aucune activité n'a été enregistrée pour cet enfant le {date}.",
}

# Templates for default summary when generating reports
DEFAULT_SUMMARY_TEMPLATES = {
    Language.EN: (
        "Today was a wonderful day! Your child engaged in {activity_count} activities "
        "and showed great progress throughout the day."
    ),
    Language.FR: (
        "Aujourd'hui a été une journée merveilleuse ! Votre enfant a participé à "
        "{activity_count} activités et a montré de grands progrès tout au long de la journée."
    ),
}

# Templates for mood summaries
MOOD_TEMPLATES = {
    Language.EN: {
        "positive": "Your child was in great spirits throughout the day, showing enthusiasm and joy.",
        "neutral": "Your child had a balanced day with steady mood and good engagement.",
        "mixed": "Your child experienced a mix of moods today but adapted well to different situations.",
    },
    Language.FR: {
        "positive": "Votre enfant était de bonne humeur tout au long de la journée, faisant preuve d'enthousiasme et de joie.",
        "neutral": "Votre enfant a passé une journée équilibrée avec une humeur stable et un bon engagement.",
        "mixed": "Votre enfant a connu des humeurs variées aujourd'hui mais s'est bien adapté aux différentes situations.",
    },
}

# Templates for meals summary
MEALS_TEMPLATES = {
    Language.EN: "Your child had a good appetite today, enjoying all planned meals and snacks.",
    Language.FR: "Votre enfant a eu un bon appétit aujourd'hui, appréciant tous les repas et collations prévus.",
}

# Home activity suggestion templates by developmental area
HOME_ACTIVITY_DATABASE = {
    Language.EN: {
        DevelopmentalArea.COGNITIVE: [
            {
                "name": "Puzzle Time at Home",
                "description": (
                    "Continue building cognitive skills with age-appropriate puzzles. "
                    "Start with simple puzzles and gradually increase difficulty. "
                    "Talk about colors, shapes, and patterns as you work together."
                ),
                "materials": ["puzzles", "flat surface"],
                "duration": 20,
            },
            {
                "name": "Counting Games",
                "description": (
                    "Practice counting with everyday objects around the house. "
                    "Count toys, fruits, or steps as you walk. Make it fun by singing "
                    "counting songs together."
                ),
                "materials": ["household items for counting"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.MOTOR: [
            {
                "name": "Building Blocks at Home",
                "description": (
                    "Continue developing fine motor skills by building with blocks at home. "
                    "Challenge your child to stack blocks higher or create simple patterns. "
                    "This activity strengthens hand-eye coordination and spatial awareness."
                ),
                "materials": ["wooden blocks", "flat surface"],
                "duration": 20,
            },
            {
                "name": "Playdough Creations",
                "description": (
                    "Strengthen fine motor muscles by playing with playdough. "
                    "Roll, squeeze, and shape the dough into different forms. "
                    "Use cookie cutters for added fun."
                ),
                "materials": ["playdough", "cookie cutters", "rolling pin"],
                "duration": 25,
            },
        ],
        DevelopmentalArea.LANGUAGE: [
            {
                "name": "Story Time",
                "description": (
                    "Read together for 15-20 minutes, pointing to pictures and asking questions "
                    "about the story. Encourage your child to predict what happens next and "
                    "retell favorite parts."
                ),
                "materials": ["children's books"],
                "duration": 20,
            },
            {
                "name": "Name That Object",
                "description": (
                    "Walk around the house and name objects together. "
                    "Ask 'What is this?' and encourage full sentences. "
                    "Describe objects using colors, sizes, and textures."
                ),
                "materials": ["household items"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.SOCIAL: [
            {
                "name": "Pretend Play",
                "description": (
                    "Engage in pretend play scenarios like playing house, doctor, or restaurant. "
                    "This helps develop social skills, empathy, and creative thinking."
                ),
                "materials": ["dress-up clothes", "toy kitchen items", "dolls or action figures"],
                "duration": 30,
            },
            {
                "name": "Turn-Taking Games",
                "description": (
                    "Play simple board games or card games that require taking turns. "
                    "This teaches patience, following rules, and graceful winning and losing."
                ),
                "materials": ["simple board game or card game"],
                "duration": 20,
            },
        ],
        DevelopmentalArea.SENSORY: [
            {
                "name": "Sensory Bin Exploration",
                "description": (
                    "Create a sensory bin with rice, beans, or sand. Hide small toys inside "
                    "for your child to discover. This activity promotes sensory exploration "
                    "and fine motor skills."
                ),
                "materials": ["container", "rice or beans", "small toys", "scoops"],
                "duration": 25,
            },
            {
                "name": "Texture Hunt",
                "description": (
                    "Go on a texture hunt around the house. Find things that are soft, rough, "
                    "smooth, and bumpy. Talk about how each texture feels."
                ),
                "materials": ["various textured items"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.CREATIVE: [
            {
                "name": "Art Project",
                "description": (
                    "Create art using crayons, paint, or collage materials. "
                    "Focus on the process, not the product. Ask your child to describe "
                    "their artwork when finished."
                ),
                "materials": ["paper", "crayons or paint", "brushes"],
                "duration": 30,
            },
            {
                "name": "Music and Movement",
                "description": (
                    "Put on music and dance together. Use scarves, ribbons, or instruments. "
                    "Try freeze dance or follow-the-leader to add fun variations."
                ),
                "materials": ["music player", "scarves or ribbons (optional)"],
                "duration": 20,
            },
        ],
    },
    Language.FR: {
        DevelopmentalArea.COGNITIVE: [
            {
                "name": "Temps des puzzles à la maison",
                "description": (
                    "Continuez à développer les compétences cognitives avec des puzzles adaptés à l'âge. "
                    "Commencez par des puzzles simples et augmentez progressivement la difficulté. "
                    "Parlez des couleurs, des formes et des motifs pendant que vous travaillez ensemble."
                ),
                "materials": ["puzzles", "surface plane"],
                "duration": 20,
            },
            {
                "name": "Jeux de comptage",
                "description": (
                    "Pratiquez le comptage avec des objets du quotidien dans la maison. "
                    "Comptez les jouets, les fruits ou les marches en marchant. "
                    "Rendez-le amusant en chantant des comptines ensemble."
                ),
                "materials": ["objets ménagers pour compter"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.MOTOR: [
            {
                "name": "Blocs de construction à la maison",
                "description": (
                    "Continuez à développer la motricité fine en construisant avec des blocs à la maison. "
                    "Défiez votre enfant d'empiler les blocs plus haut ou de créer des motifs simples. "
                    "Cette activité renforce la coordination œil-main et la conscience spatiale."
                ),
                "materials": ["blocs en bois", "surface plane"],
                "duration": 20,
            },
            {
                "name": "Créations en pâte à modeler",
                "description": (
                    "Renforcez les muscles de la motricité fine en jouant avec de la pâte à modeler. "
                    "Roulez, pressez et façonnez la pâte en différentes formes. "
                    "Utilisez des emporte-pièces pour plus de plaisir."
                ),
                "materials": ["pâte à modeler", "emporte-pièces", "rouleau à pâtisserie"],
                "duration": 25,
            },
        ],
        DevelopmentalArea.LANGUAGE: [
            {
                "name": "Heure du conte",
                "description": (
                    "Lisez ensemble pendant 15-20 minutes, en montrant les images et en posant des questions "
                    "sur l'histoire. Encouragez votre enfant à prédire ce qui va se passer ensuite et "
                    "à raconter ses passages préférés."
                ),
                "materials": ["livres pour enfants"],
                "duration": 20,
            },
            {
                "name": "Nommer les objets",
                "description": (
                    "Promenez-vous dans la maison et nommez les objets ensemble. "
                    "Demandez « Qu'est-ce que c'est ? » et encouragez les phrases complètes. "
                    "Décrivez les objets en utilisant les couleurs, les tailles et les textures."
                ),
                "materials": ["objets ménagers"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.SOCIAL: [
            {
                "name": "Jeu de rôle",
                "description": (
                    "Participez à des scénarios de jeu de rôle comme jouer à la maison, au docteur ou au restaurant. "
                    "Cela aide à développer les compétences sociales, l'empathie et la pensée créative."
                ),
                "materials": ["vêtements de déguisement", "articles de cuisine jouets", "poupées ou figurines"],
                "duration": 30,
            },
            {
                "name": "Jeux de tour de rôle",
                "description": (
                    "Jouez à des jeux de société simples ou à des jeux de cartes qui nécessitent de jouer à tour de rôle. "
                    "Cela enseigne la patience, le respect des règles et la bonne attitude en cas de victoire ou de défaite."
                ),
                "materials": ["jeu de société simple ou jeu de cartes"],
                "duration": 20,
            },
        ],
        DevelopmentalArea.SENSORY: [
            {
                "name": "Exploration de bac sensoriel",
                "description": (
                    "Créez un bac sensoriel avec du riz, des haricots ou du sable. Cachez de petits jouets à l'intérieur "
                    "pour que votre enfant les découvre. Cette activité favorise l'exploration sensorielle "
                    "et la motricité fine."
                ),
                "materials": ["contenant", "riz ou haricots", "petits jouets", "pelles"],
                "duration": 25,
            },
            {
                "name": "Chasse aux textures",
                "description": (
                    "Partez à la chasse aux textures dans la maison. Trouvez des choses douces, rugueuses, "
                    "lisses et bosselées. Parlez de la sensation de chaque texture."
                ),
                "materials": ["divers objets texturés"],
                "duration": 15,
            },
        ],
        DevelopmentalArea.CREATIVE: [
            {
                "name": "Projet artistique",
                "description": (
                    "Créez de l'art en utilisant des crayons, de la peinture ou des matériaux de collage. "
                    "Concentrez-vous sur le processus, pas sur le produit. Demandez à votre enfant de décrire "
                    "son œuvre une fois terminée."
                ),
                "materials": ["papier", "crayons ou peinture", "pinceaux"],
                "duration": 30,
            },
            {
                "name": "Musique et mouvement",
                "description": (
                    "Mettez de la musique et dansez ensemble. Utilisez des foulards, des rubans ou des instruments. "
                    "Essayez la danse figée ou suivez-le-chef pour ajouter des variations amusantes."
                ),
                "materials": ["lecteur de musique", "foulards ou rubans (optionnel)"],
                "duration": 20,
            },
        ],
    },
}


# =============================================================================
# Exception Classes
# =============================================================================


class CommunicationServiceError(Exception):
    """Base exception for communication service errors."""

    pass


class ChildNotFoundError(CommunicationServiceError):
    """Raised when the specified child is not found."""

    pass


class InvalidDateError(CommunicationServiceError):
    """Raised when an invalid date is provided."""

    pass


class NoActivitiesError(CommunicationServiceError):
    """Raised when no activities are found for the specified date."""

    pass


class UnauthorizedAccessError(CommunicationServiceError):
    """Raised when the user does not have permission to access a resource."""

    pass


# =============================================================================
# Communication Service
# =============================================================================


class CommunicationService:
    """Service for generating parent communications and home activity suggestions.

    This service provides personalized, bilingual communication capabilities
    for daycare centers to share daily reports and home activity suggestions
    with parents. All content is available in both English and French for
    Quebec bilingual compliance.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the communication service.

        Args:
            db: Async database session
        """
        self.db = db

    async def generate_report(
        self,
        request: GenerateReportRequest,
        user: dict,
    ) -> ParentReportResponse:
        """Generate a personalized daily report for parents.

        Creates an AI-powered daily summary of a child's activities, mood,
        meals, and milestones for the specified date. The report is generated
        in the requested language (English or French).

        Args:
            request: The report generation request containing child_id, date, and language
            user: The authenticated user making the request

        Returns:
            ParentReportResponse with the complete generated report

        Raises:
            InvalidDateError: When the date is in the future
        """
        # Validate date is not in the future
        if request.report_date > date.today():
            raise InvalidDateError(
                f"Cannot generate report for future date: {request.report_date}"
            )

        # Get child activities for the specified date
        activities = await self._get_child_activities(
            child_id=request.child_id,
            report_date=request.report_date,
        )

        # Generate summary content based on activities and language
        summary_content = await self._generate_summary_content(
            activities=activities,
            language=request.language,
            report_date=request.report_date,
        )

        # Get user ID from authenticated user
        user_id = UUID(user.get("sub", user.get("user_id")))

        # Persist the report to the database
        report = await self._persist_report(
            child_id=request.child_id,
            report_date=request.report_date,
            language=request.language,
            summary_content=summary_content,
            educator_notes=request.educator_notes,
            generated_by=user_id,
        )

        return ParentReportResponse(
            id=report.id,
            child_id=report.child_id,
            report_date=report.report_date,
            language=Language(report.language),
            summary=report.summary,
            activities_summary=report.activities_summary,
            mood_summary=report.mood_summary,
            meals_summary=report.meals_summary,
            milestones=report.milestones,
            educator_notes=report.educator_notes,
            generated_by=report.generated_by,
            created_at=report.created_at,
            updated_at=report.updated_at,
        )

    async def get_home_activities(
        self,
        child_id: UUID,
        language: Language = Language.EN,
        limit: int = 5,
    ) -> HomeActivitiesListResponse:
        """Get suggested home activities for a child.

        Generates personalized home activity suggestions based on the child's
        recent daycare activities. Suggestions help parents continue their
        child's developmental activities at home.

        Args:
            child_id: Unique identifier of the child
            language: Language for the activity suggestions (default: English)
            limit: Maximum number of suggestions to return (default: 5)

        Returns:
            HomeActivitiesListResponse with activity suggestions
        """
        # Get recent activities for the child
        recent_activities = await self._get_recent_activities(child_id=child_id)

        # Generate home activity suggestions based on recent activities
        suggestions = await self._generate_home_suggestions(
            activities=recent_activities,
            language=language,
            limit=limit,
        )

        # Persist the generated suggestions
        saved_activities = await self._persist_home_activities(
            child_id=child_id,
            suggestions=suggestions,
            language=language,
        )

        # Convert to response schema
        activity_responses: list[HomeActivityResponse] = []
        for activity in saved_activities:
            materials_list = (
                activity.materials_needed.split(", ")
                if activity.materials_needed
                else None
            )
            activity_responses.append(
                HomeActivityResponse(
                    id=activity.id,
                    child_id=activity.child_id,
                    activity_name=activity.activity_name,
                    activity_description=activity.activity_description,
                    materials_needed=materials_list,
                    estimated_duration_minutes=activity.estimated_duration_minutes,
                    developmental_area=(
                        DevelopmentalArea(activity.developmental_area)
                        if activity.developmental_area
                        else None
                    ),
                    language=Language(activity.language),
                    based_on_activity_id=activity.based_on_activity_id,
                    is_completed=activity.is_completed,
                    based_on=None,  # Will be populated in future with activity name lookup
                    created_at=activity.created_at,
                    updated_at=activity.updated_at,
                )
            )

        return HomeActivitiesListResponse(
            child_id=child_id,
            activities=activity_responses,
            generated_at=datetime.utcnow(),
        )

    async def get_or_create_preference(
        self,
        parent_id: UUID,
        child_id: UUID,
    ) -> CommunicationPreference:
        """Get or create communication preferences for a parent.

        Retrieves existing communication preferences for a parent, or creates
        a new preference record with default values if none exists.

        Args:
            parent_id: Unique identifier of the parent user
            child_id: Unique identifier of the child

        Returns:
            CommunicationPreference record
        """
        # Try to find existing preference
        query = select(CommunicationPreference).where(
            CommunicationPreference.parent_id == parent_id
        )
        result = await self.db.execute(query)
        preference = result.scalar_one_or_none()

        if preference:
            return preference

        # Create new preference with defaults
        preference = CommunicationPreference(
            parent_id=parent_id,
            child_id=child_id,
            preferred_language="en",
            report_frequency="daily",
        )
        self.db.add(preference)
        await self.db.commit()
        await self.db.refresh(preference)

        return preference

    async def update_preference(
        self,
        parent_id: UUID,
        child_id: UUID,
        preferred_language: Optional[Language] = None,
        report_frequency: Optional[str] = None,
    ) -> CommunicationPreference:
        """Update communication preferences for a parent.

        Updates existing preferences or creates new ones if they don't exist.

        Args:
            parent_id: Unique identifier of the parent user
            child_id: Unique identifier of the child
            preferred_language: Preferred language for communications
            report_frequency: How often to generate reports

        Returns:
            Updated CommunicationPreference record
        """
        preference = await self.get_or_create_preference(
            parent_id=parent_id,
            child_id=child_id,
        )

        if preferred_language:
            preference.preferred_language = preferred_language.value
        if report_frequency:
            preference.report_frequency = report_frequency

        await self.db.commit()
        await self.db.refresh(preference)

        return preference

    async def _get_child_activities(
        self,
        child_id: UUID,
        report_date: date,
    ) -> list[ActivityParticipation]:
        """Retrieve child's activities for the specified date.

        Fetches all activity participations for a child on the given date
        from the Phase 8 Activity Intelligence infrastructure.

        Args:
            child_id: Unique identifier of the child
            report_date: Date to get activities for

        Returns:
            List of ActivityParticipation records for the date
        """
        # Query activity participations for the child on the specified date
        # Use cast for SQLite compatibility (TEXT storage) while maintaining PostgreSQL compatibility
        query = (
            select(ActivityParticipation)
            .where(
                and_(
                    cast(ActivityParticipation.child_id, String) == str(child_id),
                    ActivityParticipation.started_at >= datetime.combine(
                        report_date, datetime.min.time()
                    ),
                    ActivityParticipation.started_at < datetime.combine(
                        report_date, datetime.max.time()
                    ),
                )
            )
            .order_by(ActivityParticipation.started_at)
        )

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def _get_recent_activities(
        self,
        child_id: UUID,
        days: int = 7,
    ) -> list[ActivityParticipation]:
        """Retrieve child's recent activities.

        Fetches activity participations from the last N days for generating
        home activity suggestions.

        Args:
            child_id: Unique identifier of the child
            days: Number of days to look back (default: 7)

        Returns:
            List of recent ActivityParticipation records
        """
        from datetime import timedelta

        cutoff_date = datetime.utcnow() - timedelta(days=days)

        # Use cast for SQLite compatibility
        query = (
            select(ActivityParticipation)
            .where(
                and_(
                    cast(ActivityParticipation.child_id, String) == str(child_id),
                    ActivityParticipation.started_at >= cutoff_date,
                )
            )
            .order_by(ActivityParticipation.started_at.desc())
            .limit(20)
        )

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def _generate_summary_content(
        self,
        activities: list[ActivityParticipation],
        language: Language,
        report_date: date,
    ) -> dict:
        """Generate AI-powered summary content sections.

        Creates personalized summary content for each section of the report
        based on the child's activities and the requested language.

        Args:
            activities: List of activity participations for the day
            language: Language for the generated content
            report_date: Date of the report

        Returns:
            Dictionary with summary, activities_summary, mood_summary,
            meals_summary, and milestones
        """
        activity_count = len(activities)

        # Handle no activities case
        if activity_count == 0:
            no_activities_msg = NO_ACTIVITIES_TEMPLATES[language].format(
                date=report_date.strftime("%B %d, %Y" if language == Language.EN else "%d %B %Y")
            )
            return {
                "summary": no_activities_msg,
                "activities_summary": None,
                "mood_summary": None,
                "meals_summary": None,
                "milestones": None,
            }

        # Generate main summary
        summary = DEFAULT_SUMMARY_TEMPLATES[language].format(
            activity_count=activity_count
        )

        # Generate activities summary
        activities_summary = await self._generate_activities_summary(
            activities=activities,
            language=language,
        )

        # Generate mood summary (default to positive)
        mood_summary = MOOD_TEMPLATES[language]["positive"]

        # Generate meals summary
        meals_summary = MEALS_TEMPLATES[language]

        # Generate milestones (placeholder for future AI integration)
        milestones = None

        return {
            "summary": summary,
            "activities_summary": activities_summary,
            "mood_summary": mood_summary,
            "meals_summary": meals_summary,
            "milestones": milestones,
        }

    async def _generate_activities_summary(
        self,
        activities: list[ActivityParticipation],
        language: Language,
    ) -> str:
        """Generate a summary of the day's activities.

        Args:
            activities: List of activity participations
            language: Language for the summary

        Returns:
            Formatted activities summary string
        """
        if not activities:
            return ""

        # Fetch activity details for each participation
        activity_names: list[str] = []
        for participation in activities:
            # Use cast for SQLite compatibility
            activity_query = select(Activity).where(
                cast(Activity.id, String) == str(participation.activity_id)
            )
            result = await self.db.execute(activity_query)
            activity = result.scalar_one_or_none()
            if activity:
                activity_names.append(activity.name)

        if not activity_names:
            return ""

        # Generate summary based on language
        if language == Language.EN:
            if len(activity_names) == 1:
                return f"Today, your child participated in {activity_names[0]}."
            elif len(activity_names) == 2:
                return f"Today, your child participated in {activity_names[0]} and {activity_names[1]}."
            else:
                activities_str = ", ".join(activity_names[:-1]) + f", and {activity_names[-1]}"
                return f"Today, your child participated in {activities_str}."
        else:  # French
            if len(activity_names) == 1:
                return f"Aujourd'hui, votre enfant a participé à {activity_names[0]}."
            elif len(activity_names) == 2:
                return f"Aujourd'hui, votre enfant a participé à {activity_names[0]} et {activity_names[1]}."
            else:
                activities_str = ", ".join(activity_names[:-1]) + f" et {activity_names[-1]}"
                return f"Aujourd'hui, votre enfant a participé à {activities_str}."

    async def _generate_home_suggestions(
        self,
        activities: list[ActivityParticipation],
        language: Language,
        limit: int = 5,
    ) -> list[dict]:
        """Generate home activity suggestions based on daycare activities.

        Creates personalized home activity recommendations that complement
        the child's recent daycare activities.

        Args:
            activities: List of recent activity participations
            language: Language for the suggestions
            limit: Maximum number of suggestions

        Returns:
            List of suggestion dictionaries with activity details
        """
        suggestions: list[dict] = []
        used_areas: set[DevelopmentalArea] = set()

        # Map activities to developmental areas
        activity_areas: list[DevelopmentalArea] = []
        for participation in activities:
            # Fetch activity to get its type
            activity_query = select(Activity).where(
                cast(Activity.id, String) == str(participation.activity_id)
            )
            result = await self.db.execute(activity_query)
            activity = result.scalar_one_or_none()

            if activity and activity.activity_type:
                # Map activity type to developmental area
                try:
                    area = DevelopmentalArea(activity.activity_type.value)
                    activity_areas.append(area)
                except ValueError:
                    # If activity type doesn't match, try to use it anyway
                    pass

        # Generate suggestions based on activity areas
        activity_database = HOME_ACTIVITY_DATABASE.get(language, HOME_ACTIVITY_DATABASE[Language.EN])

        # First, add suggestions based on recent activities
        for area in activity_areas:
            if len(suggestions) >= limit:
                break
            if area in used_areas:
                continue

            area_activities = activity_database.get(area, [])
            if area_activities:
                # Pick the first available activity for this area
                activity_data = area_activities[0]
                suggestions.append({
                    "area": area,
                    "based_on_activity_id": None,
                    **activity_data,
                })
                used_areas.add(area)

        # Fill remaining slots with activities from other areas
        for area, area_activities in activity_database.items():
            if len(suggestions) >= limit:
                break
            if area in used_areas:
                continue

            if area_activities:
                activity_data = area_activities[0]
                suggestions.append({
                    "area": area,
                    "based_on_activity_id": None,
                    **activity_data,
                })
                used_areas.add(area)

        return suggestions[:limit]

    async def _persist_report(
        self,
        child_id: UUID,
        report_date: date,
        language: Language,
        summary_content: dict,
        educator_notes: Optional[str],
        generated_by: UUID,
    ) -> ParentReport:
        """Persist a generated report to the database.

        Args:
            child_id: Unique identifier of the child
            report_date: Date the report covers
            language: Language of the report
            summary_content: Dictionary with summary sections
            educator_notes: Optional educator notes
            generated_by: ID of the user generating the report

        Returns:
            The persisted ParentReport record
        """
        report = ParentReport(
            child_id=child_id,
            report_date=report_date,
            language=language.value,
            summary=summary_content["summary"],
            activities_summary=summary_content.get("activities_summary"),
            mood_summary=summary_content.get("mood_summary"),
            meals_summary=summary_content.get("meals_summary"),
            milestones=summary_content.get("milestones"),
            educator_notes=educator_notes,
            generated_by=generated_by,
        )

        self.db.add(report)
        await self.db.commit()
        await self.db.refresh(report)

        return report

    async def _persist_home_activities(
        self,
        child_id: UUID,
        suggestions: list[dict],
        language: Language,
    ) -> list[HomeActivity]:
        """Persist home activity suggestions to the database.

        Args:
            child_id: Unique identifier of the child
            suggestions: List of suggestion dictionaries
            language: Language of the suggestions

        Returns:
            List of persisted HomeActivity records
        """
        saved_activities: list[HomeActivity] = []

        for suggestion in suggestions:
            # Convert materials list to comma-separated string for storage
            materials_str = (
                ", ".join(suggestion.get("materials", []))
                if suggestion.get("materials")
                else None
            )

            activity = HomeActivity(
                child_id=child_id,
                activity_name=suggestion["name"],
                activity_description=suggestion["description"],
                materials_needed=materials_str,
                estimated_duration_minutes=suggestion.get("duration"),
                developmental_area=suggestion.get("area").value if suggestion.get("area") else None,
                language=language.value,
                based_on_activity_id=suggestion.get("based_on_activity_id"),
                is_completed=False,
            )

            self.db.add(activity)
            saved_activities.append(activity)

        await self.db.commit()

        # Refresh all activities to get their IDs
        for activity in saved_activities:
            await self.db.refresh(activity)

        return saved_activities

    async def get_preference_by_parent(
        self,
        parent_id: UUID,
    ) -> Optional[CommunicationPreference]:
        """Get communication preferences for a parent.

        Args:
            parent_id: Unique identifier of the parent user

        Returns:
            CommunicationPreference if found, None otherwise
        """
        query = select(CommunicationPreference).where(
            cast(CommunicationPreference.parent_id, String) == str(parent_id)
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def _verify_child_access(
        self,
        child_id: UUID,
        user_id: UUID,
        user_role: str,
        allow_educators: bool = True,
    ) -> bool:
        """Verify user has permission to access a child's data.

        This method verifies that a user (parent, educator, or admin) has
        permission to access data associated with a specific child. Access rules:
        - Admins: Always have access
        - Educators/Teachers: Have access if allow_educators=True
        - Parents: Have access only to their own children (verified through relationships)

        Args:
            child_id: UUID of the child
            user_id: UUID of the user requesting access
            user_role: Role of the user (from JWT token)
            allow_educators: Whether educators/teachers should have access (default: True)

        Returns:
            bool: True if user has access

        Raises:
            UnauthorizedAccessError: When user lacks permission to access the child
        """
        from app.auth.dependencies import verify_child_access

        try:
            return await verify_child_access(
                db=self.db,
                child_id=child_id,
                user_id=user_id,
                user_role=user_role,
                allow_educators=allow_educators,
            )
        except Exception as e:
            # Re-raise as our service's UnauthorizedAccessError
            if "does not have permission" in str(e):
                raise UnauthorizedAccessError(str(e))
            raise
