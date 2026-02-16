"""Seed script for LAYA AI Service development database.

This script generates sample data for development and testing:
- 20 children across 3 age groups (0-2, 2-4, 4-6 years)
- 15 families with parent information
- 50+ activities across different types and difficulty levels
- Activity participation records
- Coaching sessions and recommendations
- Parent reports and home activities
- Communication preferences

The script is idempotent - it checks for existing data before inserting.
"""

import asyncio
import random
from datetime import datetime, timedelta
from uuid import UUID, uuid4

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import AsyncSessionLocal, engine
from app.models import (
    Activity,
    ActivityDifficulty,
    ActivityParticipation,
    ActivityRecommendation,
    ActivityType,
    CoachingRecommendation,
    CoachingSession,
    CommunicationPreference,
    EvidenceSource,
    HomeActivity,
    ParentReport,
)
from app.models.base import Base


# Seed data configuration
NUM_CHILDREN = 20
NUM_FAMILIES = 15
NUM_ACTIVITIES = 55
NUM_PARTICIPATIONS_PER_CHILD = 5
NUM_COACHING_SESSIONS = 10


# Sample data for seed generation
ACTIVITY_NAMES = {
    ActivityType.COGNITIVE: [
        "Puzzle Time",
        "Shape Sorting",
        "Memory Match Game",
        "Number Recognition",
        "Pattern Building",
        "Story Sequencing",
        "Counting Bears",
        "Color Matching",
    ],
    ActivityType.MOTOR: [
        "Ball Tossing",
        "Obstacle Course",
        "Dance Party",
        "Climbing Wall",
        "Balance Beam",
        "Jump Rope",
        "Bike Riding",
        "Fine Motor Beads",
    ],
    ActivityType.SOCIAL: [
        "Group Circle Time",
        "Share and Tell",
        "Cooperative Building",
        "Role Play Kitchen",
        "Friendship Game",
        "Team Clean-up",
        "Partner Art",
        "Community Helpers",
    ],
    ActivityType.LANGUAGE: [
        "Alphabet Songs",
        "Story Time",
        "Letter Recognition",
        "Rhyme Time",
        "Show and Tell",
        "Picture Description",
        "Word Building",
        "Bilingual Songs",
    ],
    ActivityType.CREATIVE: [
        "Finger Painting",
        "Playdough Creations",
        "Collage Making",
        "Music with Instruments",
        "Dress-up Theater",
        "Clay Sculpting",
        "Drawing Station",
        "Craft Corner",
    ],
    ActivityType.SENSORY: [
        "Sensory Bin Exploration",
        "Water Play",
        "Sand Table",
        "Texture Touch Board",
        "Scent Bottles",
        "Sound Matching",
        "Light Table Play",
        "Bubble Time",
    ],
}


COACHING_QUESTIONS = [
    "How can I help my child with autism transition between activities?",
    "What strategies work for a child with ADHD during circle time?",
    "How to support a child with speech delay in group settings?",
    "Best practices for sensory-sensitive children during meal times?",
    "How to encourage social interaction for a shy child?",
]


AGE_GROUPS = {
    "infant": (0, 24),  # 0-2 years in months
    "toddler": (24, 48),  # 2-4 years in months
    "preschool": (48, 72),  # 4-6 years in months
}


async def generate_children_and_families() -> tuple[list[UUID], list[UUID]]:
    """Generate UUIDs for children and families.

    In production, these would come from Gibbon. For development,
    we generate UUIDs that can be referenced by AI service data.

    Returns:
        Tuple of (child_ids, family_ids, parent_ids)
    """
    # Generate family IDs
    family_ids = [uuid4() for _ in range(NUM_FAMILIES)]

    # Generate child IDs distributed across families
    child_ids = []
    for i in range(NUM_CHILDREN):
        child_ids.append(uuid4())

    # Generate parent IDs (1-2 parents per family)
    parent_ids = []
    for _ in range(NUM_FAMILIES):
        num_parents = random.randint(1, 2)
        for _ in range(num_parents):
            parent_ids.append(uuid4())

    return child_ids, family_ids, parent_ids


async def seed_activities(session: AsyncSession) -> list[UUID]:
    """Seed activity data.

    Creates 50+ activities across different types and difficulty levels.

    Args:
        session: Async database session

    Returns:
        List of created activity IDs
    """
    # Check if activities already exist
    result = await session.execute(select(Activity).limit(1))
    if result.scalar_one_or_none():
        print("✓ Activities already exist, skipping...")
        result = await session.execute(select(Activity.id))
        return [row[0] for row in result.all()]

    print(f"Creating {NUM_ACTIVITIES} activities...")
    activity_ids = []
    activities_created = 0

    for activity_type in ActivityType:
        names = ACTIVITY_NAMES[activity_type]
        activities_per_type = NUM_ACTIVITIES // len(ActivityType)

        for i in range(activities_per_type):
            if activities_created >= NUM_ACTIVITIES:
                break

            name = names[i % len(names)]
            if i >= len(names):
                name = f"{name} {i // len(names) + 1}"

            # Determine age range based on difficulty
            difficulty = random.choice(list(ActivityDifficulty))
            if difficulty == ActivityDifficulty.EASY:
                min_age, max_age = 12, 36
            elif difficulty == ActivityDifficulty.MEDIUM:
                min_age, max_age = 24, 60
            else:
                min_age, max_age = 48, 72

            activity = Activity(
                id=uuid4(),
                name=name,
                description=f"A {difficulty.value} {activity_type.value} activity for children aged {min_age//12}-{max_age//12} years.",
                activity_type=activity_type,
                difficulty=difficulty,
                duration_minutes=random.randint(15, 45),
                materials_needed=[
                    f"Material {j+1}" for j in range(random.randint(2, 5))
                ],
                min_age_months=min_age,
                max_age_months=max_age,
                special_needs_adaptations="Can be adapted for various special needs with appropriate support.",
                is_active=True,
            )

            session.add(activity)
            activity_ids.append(activity.id)
            activities_created += 1

    await session.commit()
    print(f"✓ Created {activities_created} activities")
    return activity_ids


async def seed_activity_participations(
    session: AsyncSession,
    child_ids: list[UUID],
    activity_ids: list[UUID],
) -> None:
    """Seed activity participation records.

    Creates participation records for children in various activities.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        activity_ids: List of activity UUIDs
    """
    # Check if participations already exist
    result = await session.execute(select(ActivityParticipation).limit(1))
    if result.scalar_one_or_none():
        print("✓ Activity participations already exist, skipping...")
        return

    print("Creating activity participation records...")
    participations_created = 0

    for child_id in child_ids:
        # Each child participates in 5 random activities
        selected_activities = random.sample(activity_ids, NUM_PARTICIPATIONS_PER_CHILD)

        for activity_id in selected_activities:
            started_at = datetime.utcnow() - timedelta(
                days=random.randint(1, 30),
                hours=random.randint(0, 23),
            )

            # 80% completion rate
            is_completed = random.random() < 0.8
            completed_at = started_at + timedelta(
                minutes=random.randint(15, 45)
            ) if is_completed else None

            participation = ActivityParticipation(
                id=uuid4(),
                child_id=child_id,
                activity_id=activity_id,
                started_at=started_at,
                completed_at=completed_at,
                duration_minutes=random.randint(15, 45) if is_completed else None,
                completion_status="completed" if is_completed else "started",
                engagement_score=random.uniform(0.5, 1.0) if is_completed else None,
                notes=f"Child {'enjoyed' if is_completed else 'participated in'} the activity.",
            )

            session.add(participation)
            participations_created += 1

    await session.commit()
    print(f"✓ Created {participations_created} activity participation records")


async def seed_activity_recommendations(
    session: AsyncSession,
    child_ids: list[UUID],
    activity_ids: list[UUID],
) -> None:
    """Seed activity recommendations.

    Creates personalized activity recommendations for children.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        activity_ids: List of activity UUIDs
    """
    # Check if recommendations already exist
    result = await session.execute(select(ActivityRecommendation).limit(1))
    if result.scalar_one_or_none():
        print("✓ Activity recommendations already exist, skipping...")
        return

    print("Creating activity recommendations...")
    recommendations_created = 0

    for child_id in child_ids:
        # Each child gets 3-5 recommendations
        num_recommendations = random.randint(3, 5)
        selected_activities = random.sample(activity_ids, num_recommendations)

        for activity_id in selected_activities:
            recommendation = ActivityRecommendation(
                id=uuid4(),
                child_id=child_id,
                activity_id=activity_id,
                relevance_score=random.uniform(0.6, 1.0),
                reasoning="Recommended based on child's developmental needs and interests.",
                is_dismissed=False,
                generated_at=datetime.utcnow() - timedelta(days=random.randint(0, 7)),
            )

            session.add(recommendation)
            recommendations_created += 1

    await session.commit()
    print(f"✓ Created {recommendations_created} activity recommendations")


async def seed_coaching_sessions(
    session: AsyncSession,
    child_ids: list[UUID],
    parent_ids: list[UUID],
) -> list[UUID]:
    """Seed coaching sessions and recommendations.

    Creates coaching sessions with evidence-based recommendations.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        parent_ids: List of parent UUIDs

    Returns:
        List of created session IDs
    """
    # Check if coaching sessions already exist
    result = await session.execute(select(CoachingSession).limit(1))
    if result.scalar_one_or_none():
        print("✓ Coaching sessions already exist, skipping...")
        result = await session.execute(select(CoachingSession.id))
        return [row[0] for row in result.all()]

    print(f"Creating {NUM_COACHING_SESSIONS} coaching sessions...")
    session_ids = []

    for i in range(NUM_COACHING_SESSIONS):
        coaching_session = CoachingSession(
            id=uuid4(),
            child_id=random.choice(child_ids),
            user_id=random.choice(parent_ids),
            question=COACHING_QUESTIONS[i % len(COACHING_QUESTIONS)],
            context="Child has been showing these behaviors during daycare hours.",
            special_need_types=["autism", "adhd", "speech_delay"][i % 3:i % 3 + 1],
            category="behavior_management" if i % 2 == 0 else "developmental_support",
            created_at=datetime.utcnow() - timedelta(days=random.randint(0, 30)),
        )

        session.add(coaching_session)
        session_ids.append(coaching_session.id)

        # Create 2-3 recommendations per session
        num_recommendations = random.randint(2, 3)
        for j in range(num_recommendations):
            recommendation = CoachingRecommendation(
                id=uuid4(),
                session_id=coaching_session.id,
                title=f"Strategy {j+1}: Evidence-Based Approach",
                content=f"Detailed recommendation based on peer-reviewed research and clinical guidelines for handling this specific situation.",
                category=coaching_session.category,
                priority=["medium", "high", "urgent"][j % 3],
                relevance_score=random.uniform(0.7, 1.0),
                target_audience="educator" if j % 2 == 0 else "parent",
                prerequisites="Understanding of child's individual needs and triggers.",
            )

            session.add(recommendation)

            # Add 1-2 evidence sources per recommendation
            num_sources = random.randint(1, 2)
            for k in range(num_sources):
                source = EvidenceSource(
                    id=uuid4(),
                    recommendation_id=recommendation.id,
                    source_type=["peer_reviewed", "official_guide", "clinical"][k % 3],
                    title=f"Research Study on {coaching_session.category.replace('_', ' ').title()}",
                    authors="Smith, J., Johnson, M., et al.",
                    publication="Journal of Child Development" if k % 2 == 0 else "Clinical Psychology Review",
                    year=random.randint(2018, 2024),
                    doi=f"10.1000/example.{random.randint(1000, 9999)}",
                    url=f"https://example.com/research/{uuid4()}",
                    accessed_at=datetime.utcnow(),
                )

                session.add(source)

    await session.commit()
    print(f"✓ Created {NUM_COACHING_SESSIONS} coaching sessions with recommendations and evidence sources")
    return session_ids


async def seed_parent_reports(
    session: AsyncSession,
    child_ids: list[UUID],
    parent_ids: list[UUID],
) -> None:
    """Seed parent daily reports.

    Creates bilingual daily reports for children.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        parent_ids: List of parent UUIDs
    """
    # Check if parent reports already exist
    result = await session.execute(select(ParentReport).limit(1))
    if result.scalar_one_or_none():
        print("✓ Parent reports already exist, skipping...")
        return

    print("Creating parent daily reports...")
    reports_created = 0

    for child_id in child_ids:
        # Create reports for the last 7 days
        for days_ago in range(7):
            report_date = datetime.utcnow() - timedelta(days=days_ago)

            # Create both English and French reports
            for language in ["en", "fr"]:
                if language == "en":
                    summary = f"Today was a wonderful day! The child engaged in various activities and showed great progress."
                    activities = "The child participated in puzzle time, outdoor play, and art activities."
                    mood = "Happy and engaged throughout the day."
                    meals = "Ate well during lunch and snack time."
                    milestones = "Showed improvement in sharing toys with peers."
                else:
                    summary = f"Aujourd'hui était une merveilleuse journée! L'enfant a participé à diverses activités et a montré de grands progrès."
                    activities = "L'enfant a participé au temps de puzzle, au jeu en plein air et aux activités artistiques."
                    mood = "Heureux et engagé tout au long de la journée."
                    meals = "A bien mangé pendant le déjeuner et la collation."
                    milestones = "A montré une amélioration dans le partage de jouets avec ses pairs."

                report = ParentReport(
                    id=uuid4(),
                    child_id=child_id,
                    report_date=report_date.date(),
                    language=language,
                    summary=summary,
                    activities_summary=activities,
                    mood_summary=mood,
                    meals_summary=meals,
                    milestones=milestones,
                    educator_notes="Great day overall!",
                    generated_by=random.choice(parent_ids),
                )

                session.add(report)
                reports_created += 1

    await session.commit()
    print(f"✓ Created {reports_created} parent daily reports")


async def seed_home_activities(
    session: AsyncSession,
    child_ids: list[UUID],
    activity_ids: list[UUID],
) -> None:
    """Seed home activity suggestions.

    Creates personalized home activities for parents.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        activity_ids: List of activity UUIDs
    """
    # Check if home activities already exist
    result = await session.execute(select(HomeActivity).limit(1))
    if result.scalar_one_or_none():
        print("✓ Home activities already exist, skipping...")
        return

    print("Creating home activity suggestions...")
    home_activities_created = 0

    for child_id in child_ids:
        # Create 3-5 home activities per child
        num_activities = random.randint(3, 5)

        for i in range(num_activities):
            language = "en" if i % 2 == 0 else "fr"

            if language == "en":
                name = f"Home Activity {i+1}"
                description = "A fun activity you can do at home to continue your child's learning and development."
                materials = "Common household items"
            else:
                name = f"Activité à la maison {i+1}"
                description = "Une activité amusante que vous pouvez faire à la maison pour continuer l'apprentissage et le développement de votre enfant."
                materials = "Articles ménagers courants"

            home_activity = HomeActivity(
                id=uuid4(),
                child_id=child_id,
                activity_name=name,
                activity_description=description,
                materials_needed=materials,
                estimated_duration_minutes=random.randint(15, 30),
                developmental_area=random.choice([
                    "cognitive", "motor", "social", "language", "creative"
                ]),
                language=language,
                based_on_activity_id=random.choice(activity_ids) if random.random() < 0.7 else None,
                is_completed=random.random() < 0.3,
            )

            session.add(home_activity)
            home_activities_created += 1

    await session.commit()
    print(f"✓ Created {home_activities_created} home activity suggestions")


async def seed_communication_preferences(
    session: AsyncSession,
    child_ids: list[UUID],
    parent_ids: list[UUID],
) -> None:
    """Seed communication preferences.

    Creates communication preferences for parents.

    Args:
        session: Async database session
        child_ids: List of child UUIDs
        parent_ids: List of parent UUIDs
    """
    # Check if communication preferences already exist
    result = await session.execute(select(CommunicationPreference).limit(1))
    if result.scalar_one_or_none():
        print("✓ Communication preferences already exist, skipping...")
        return

    print("Creating communication preferences...")
    prefs_created = 0

    # Create one preference per parent
    for i, parent_id in enumerate(parent_ids):
        child_id = child_ids[i % len(child_ids)]

        preference = CommunicationPreference(
            id=uuid4(),
            parent_id=parent_id,
            child_id=child_id,
            preferred_language="fr" if i % 3 == 0 else "en",  # 1/3 prefer French
            report_frequency="daily" if i % 2 == 0 else "weekly",
        )

        session.add(preference)
        prefs_created += 1

    await session.commit()
    print(f"✓ Created {prefs_created} communication preferences")


async def create_tables() -> None:
    """Create all database tables if they don't exist."""
    print("Creating database tables...")
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    print("✓ Database tables created")


async def main() -> None:
    """Main seed function."""
    print("=" * 60)
    print("LAYA AI Service - Database Seeding")
    print("=" * 60)
    print(f"Database: {settings.postgres_db}")
    print(f"Host: {settings.postgres_host}:{settings.postgres_port}")
    print("=" * 60)
    print()

    # Create tables
    await create_tables()
    print()

    # Generate child and family IDs
    print("Generating child and family IDs...")
    child_ids, family_ids, parent_ids = await generate_children_and_families()
    print(f"✓ Generated {len(child_ids)} children, {len(family_ids)} families, {len(parent_ids)} parents")
    print()

    # Seed all data
    async with AsyncSessionLocal() as session:
        # Seed activities
        activity_ids = await seed_activities(session)
        print()

        # Seed activity participations
        await seed_activity_participations(session, child_ids, activity_ids)
        print()

        # Seed activity recommendations
        await seed_activity_recommendations(session, child_ids, activity_ids)
        print()

        # Seed coaching sessions
        await seed_coaching_sessions(session, child_ids, parent_ids)
        print()

        # Seed parent reports
        await seed_parent_reports(session, child_ids, parent_ids)
        print()

        # Seed home activities
        await seed_home_activities(session, child_ids, activity_ids)
        print()

        # Seed communication preferences
        await seed_communication_preferences(session, child_ids, parent_ids)
        print()

    print("=" * 60)
    print("✓ Seeding completed successfully!")
    print("=" * 60)
    print()
    print("Summary:")
    print(f"  - {len(child_ids)} children")
    print(f"  - {len(family_ids)} families")
    print(f"  - {len(parent_ids)} parents")
    print(f"  - {len(activity_ids)} activities")
    print(f"  - {len(child_ids) * NUM_PARTICIPATIONS_PER_CHILD} activity participations")
    print(f"  - {NUM_COACHING_SESSIONS} coaching sessions")
    print(f"  - Parent reports (bilingual)")
    print(f"  - Home activities (bilingual)")
    print(f"  - Communication preferences")
    print()


if __name__ == "__main__":
    asyncio.run(main())
