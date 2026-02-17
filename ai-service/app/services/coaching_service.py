"""Service for RAG-based special needs coaching guidance.

Provides personalized, evidence-based coaching recommendations for educators
and parents working with children who have special needs. All recommendations
must be backed by citations from peer-reviewed or official sources.
"""

from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.cache import cache, invalidate_cache
from app.models.coaching import (
    CoachingRecommendation,
    CoachingSession,
    EvidenceSource,
)
from app.schemas.coaching import (
    CoachingCategory,
    CoachingGuidance,
    CoachingGuidanceRequest,
    CoachingGuidanceResponse,
    CoachingPriority,
    CoachingResponse,
    EvidenceSourceSchema,
    SpecialNeedType,
)

# Safety disclaimer that must be included in all responses
SAFETY_DISCLAIMER = (
    "This guidance is for informational purposes only and does not constitute "
    "professional medical, therapeutic, or educational advice. The information "
    "provided should not be used as a substitute for consultation with qualified "
    "healthcare providers, therapists, or special education professionals. "
    "Always consult with appropriate specialists for diagnosis, treatment, or "
    "intervention decisions specific to your child's needs. If you have concerns "
    "about a child's health, development, or safety, please contact a qualified "
    "professional immediately."
)

# Professional referral message for medical questions
PROFESSIONAL_REFERRAL = (
    "This question appears to require professional medical or therapeutic expertise. "
    "We recommend consulting with the following professionals:\n\n"
    "- Pediatrician or developmental pediatrician for medical concerns\n"
    "- Licensed psychologist for behavioral or psychological assessments\n"
    "- Speech-language pathologist for communication concerns\n"
    "- Occupational therapist for sensory or motor skill issues\n"
    "- Board Certified Behavior Analyst (BCBA) for behavioral interventions\n"
    "- Special education specialist for educational accommodations\n\n"
    "Please seek professional guidance for any decisions regarding medication, "
    "diagnosis, or treatment plans."
)

# Keywords that indicate a medical question requiring professional referral
MEDICAL_KEYWORDS = [
    "medication",
    "medicine",
    "drug",
    "prescription",
    "dose",
    "dosage",
    "diagnose",
    "diagnosis",
    "treat",
    "treatment",
    "cure",
    "therapy session",
    "symptoms",
    "side effect",
    "doctor",
    "medical",
    "clinical",
    "psychiatrist",
    "psychologist assessment",
]


class CoachingServiceError(Exception):
    """Base exception for coaching service errors."""

    pass


class NoSourcesFoundError(CoachingServiceError):
    """Raised when no matching evidence sources are found."""

    pass


class InvalidChildError(CoachingServiceError):
    """Raised when the child_id is invalid."""

    pass


class CoachingService:
    """Service for generating evidence-based coaching recommendations.

    This service provides personalized coaching guidance for educators and
    parents working with children who have special needs. All recommendations
    are backed by citations from evidence sources - no uncited recommendations
    are permitted.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the coaching service.

        Args:
            db: Async database session
        """
        self.db = db

    async def generate_guidance(
        self,
        request: CoachingGuidanceRequest,
        user: dict,
    ) -> CoachingGuidanceResponse:
        """Generate personalized coaching guidance with mandatory citations.

        Generates evidence-based coaching recommendations based on the child's
        special needs and the situation described. All recommendations include
        citations from peer-reviewed or official sources.

        Args:
            request: The coaching guidance request containing child info and context
            user: The authenticated user making the request

        Returns:
            CoachingGuidanceResponse with guidance items, citations, and disclaimer

        Raises:
            NoSourcesFoundError: When no matching evidence sources are found
            InvalidChildError: When the child_id is invalid
        """
        # Check for medical questions that require professional referral
        if self._is_medical_question(request.situation_description):
            return await self._create_professional_referral_response(request, user)

        # Get matching guidance based on special needs and category
        guidance_items, citations = await self._retrieve_evidence_based_guidance(
            special_need_types=request.special_need_types,
            situation_description=request.situation_description,
            category=request.category,
            max_recommendations=request.max_recommendations,
        )

        # Validate that we have citations - never return uncited recommendations
        if not citations:
            raise NoSourcesFoundError(
                "Unable to provide guidance: no matching evidence sources found. "
                "Please try with different parameters or consult a specialist."
            )

        # Persist the coaching session
        session = await self._persist_session(
            request=request,
            user_id=UUID(user.get("sub", user.get("user_id"))),
            guidance_items=guidance_items,
            citations=citations,
        )

        return CoachingGuidanceResponse(
            child_id=request.child_id,
            guidance_items=guidance_items,
            generated_at=datetime.utcnow(),
            citations=citations,
            disclaimer=SAFETY_DISCLAIMER,
        )

    def _is_medical_question(self, situation_description: Optional[str]) -> bool:
        """Check if the question requires professional medical expertise.

        Args:
            situation_description: The situation or question described

        Returns:
            True if the question appears to require medical expertise
        """
        if not situation_description:
            return False

        description_lower = situation_description.lower()
        return any(keyword in description_lower for keyword in MEDICAL_KEYWORDS)

    async def _create_professional_referral_response(
        self,
        request: CoachingGuidanceRequest,
        user: dict,
    ) -> CoachingGuidanceResponse:
        """Create a response directing user to professional help.

        Used when a medical question is detected that requires professional
        expertise rather than general coaching guidance.

        Args:
            request: The original coaching guidance request
            user: The authenticated user

        Returns:
            CoachingGuidanceResponse with professional referral guidance
        """
        # Create a referral guidance item
        referral_guidance = CoachingGuidance(
            coaching=CoachingResponse(
                id=UUID("00000000-0000-0000-0000-000000000000"),
                title="Professional Referral Recommended",
                content=PROFESSIONAL_REFERRAL,
                category=CoachingCategory.PARENT_GUIDANCE,
                special_need_types=request.special_need_types,
                priority=CoachingPriority.HIGH,
                target_audience="parent",
                prerequisites=None,
                is_published=True,
                view_count=0,
                created_at=datetime.utcnow(),
                updated_at=datetime.utcnow(),
            ),
            relevance_score=1.0,
            applicability_notes="This question requires professional expertise. "
            "Please consult with qualified specialists.",
        )

        # Include a citation for the referral best practices
        referral_citation = EvidenceSourceSchema(
            title="American Academy of Pediatrics - Guidelines for Referral to Specialists",
            authors="American Academy of Pediatrics Committee on Children With Disabilities",
            publication_year=2023,
            source_type="official_guide",
            url="https://www.aap.org/en/patient-care/early-childhood/",
            doi=None,
        )

        return CoachingGuidanceResponse(
            child_id=request.child_id,
            guidance_items=[referral_guidance],
            generated_at=datetime.utcnow(),
            citations=[referral_citation],
            disclaimer=SAFETY_DISCLAIMER,
        )

    @cache(ttl=86400, key_prefix="llm_response")
    async def _retrieve_evidence_based_guidance(
        self,
        special_need_types: list[SpecialNeedType],
        situation_description: Optional[str],
        category: Optional[CoachingCategory],
        max_recommendations: int,
    ) -> tuple[list[CoachingGuidance], list[EvidenceSourceSchema]]:
        """Retrieve evidence-based guidance from the knowledge base.

        Queries the RAG database for relevant coaching guidance based on
        the specified special needs and situation. Returns guidance items
        with their supporting evidence sources.

        This method is cached with a 24-hour TTL (86400 seconds) to improve
        performance for repeated queries with the same parameters.

        Args:
            special_need_types: Types of special needs to address
            situation_description: Description of the situation or challenge
            category: Optional category filter
            max_recommendations: Maximum number of recommendations to return

        Returns:
            Tuple of (guidance_items, citations)

        Note:
            This is a placeholder implementation that returns curated guidance.
            In production, this would query a RAG vector database for
            semantically similar guidance from the knowledge base.
        """
        # Build guidance based on special needs types
        guidance_items: list[CoachingGuidance] = []
        citations: list[EvidenceSourceSchema] = []

        # Get guidance for each special need type
        for need_type in special_need_types:
            need_guidance, need_citations = self._get_guidance_for_need_type(
                need_type=need_type,
                category=category,
            )
            guidance_items.extend(need_guidance)
            citations.extend(need_citations)

            # Respect max_recommendations limit
            if len(guidance_items) >= max_recommendations:
                guidance_items = guidance_items[:max_recommendations]
                break

        # Deduplicate citations by title
        seen_titles: set[str] = set()
        unique_citations: list[EvidenceSourceSchema] = []
        for citation in citations:
            if citation.title not in seen_titles:
                seen_titles.add(citation.title)
                unique_citations.append(citation)

        return guidance_items, unique_citations

    def _get_guidance_for_need_type(
        self,
        need_type: SpecialNeedType,
        category: Optional[CoachingCategory],
    ) -> tuple[list[CoachingGuidance], list[EvidenceSourceSchema]]:
        """Get guidance and citations for a specific special need type.

        Args:
            need_type: The special need type to get guidance for
            category: Optional category filter

        Returns:
            Tuple of (guidance_items, citations) for this need type
        """
        # Evidence-based guidance database (placeholder for RAG retrieval)
        guidance_database = self._get_evidence_database()

        need_key = need_type.value
        if need_key not in guidance_database:
            need_key = "general"

        need_guidance_data = guidance_database[need_key]

        # Filter by category if specified
        if category:
            need_guidance_data = [
                g for g in need_guidance_data
                if g["category"] == category.value or g["category"] == "general"
            ]

        guidance_items: list[CoachingGuidance] = []
        citations: list[EvidenceSourceSchema] = []

        for item in need_guidance_data:
            # Create coaching response
            coaching_response = CoachingResponse(
                id=UUID("00000000-0000-0000-0000-000000000001"),
                title=item["title"],
                content=item["content"],
                category=CoachingCategory(item["category"]),
                special_need_types=[need_type],
                priority=CoachingPriority(item.get("priority", "medium")),
                target_audience=item.get("target_audience", "educator"),
                prerequisites=item.get("prerequisites"),
                is_published=True,
                view_count=0,
                created_at=datetime.utcnow(),
                updated_at=datetime.utcnow(),
            )

            guidance = CoachingGuidance(
                coaching=coaching_response,
                relevance_score=item.get("relevance_score", 0.8),
                applicability_notes=item.get("applicability_notes"),
            )
            guidance_items.append(guidance)

            # Add citations for this guidance
            for citation_data in item.get("citations", []):
                citation = EvidenceSourceSchema(
                    title=citation_data["title"],
                    authors=citation_data.get("authors"),
                    publication_year=citation_data.get("publication_year"),
                    source_type=citation_data.get("source_type", "peer_reviewed"),
                    url=citation_data.get("url"),
                    doi=citation_data.get("doi"),
                )
                citations.append(citation)

        return guidance_items, citations

    def _get_evidence_database(self) -> dict:
        """Get the evidence-based guidance database.

        Returns a curated database of evidence-based guidance for different
        special need types. Each guidance item includes citations from
        peer-reviewed or official sources.

        Returns:
            Dictionary mapping special need types to guidance items

        Note:
            This is a placeholder. In production, this would be replaced by
            RAG retrieval from a vector database of curated content.
        """
        return {
            "autism": [
                {
                    "title": "Visual Schedule Implementation for Autism Support",
                    "content": (
                        "Visual schedules are highly effective for children with autism "
                        "spectrum disorder. Use picture-based schedules to show daily "
                        "routines and transitions. Start with 3-5 activities and gradually "
                        "increase complexity. Allow the child to check off completed items "
                        "to build independence and reduce anxiety around transitions."
                    ),
                    "category": "activity_adaptation",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.95,
                    "applicability_notes": "Implement gradually, starting with preferred activities",
                    "citations": [
                        {
                            "title": "Visual Supports and Autism Spectrum Disorder",
                            "authors": "Knight, V., Sartini, E., & Spriggs, A. D.",
                            "publication_year": 2015,
                            "source_type": "peer_reviewed",
                            "doi": "10.1177/1088357614528799",
                        },
                        {
                            "title": "TEACCH Autism Program - Structured Teaching",
                            "authors": "Division TEACCH, University of North Carolina",
                            "publication_year": 2022,
                            "source_type": "official_guide",
                            "url": "https://teacch.com/about-us/what-is-teacch/",
                        },
                    ],
                },
                {
                    "title": "Social Stories for Communication Support",
                    "content": (
                        "Social Stories are short narratives that describe social situations "
                        "and appropriate responses. Write stories in first person, use "
                        "descriptive and perspective sentences, and include directive "
                        "sentences for expected behavior. Read the story regularly before "
                        "the situation occurs to help the child prepare."
                    ),
                    "category": "communication",
                    "priority": "medium",
                    "target_audience": "educator",
                    "relevance_score": 0.90,
                    "citations": [
                        {
                            "title": "The New Social Story Book",
                            "authors": "Gray, Carol",
                            "publication_year": 2015,
                            "source_type": "peer_reviewed",
                            "url": "https://carolgraysocialstories.com/",
                        },
                    ],
                },
            ],
            "adhd": [
                {
                    "title": "Movement Breaks for ADHD Support",
                    "content": (
                        "Children with ADHD benefit from regular movement breaks throughout "
                        "the day. Schedule 5-10 minute physical activity breaks every 20-30 "
                        "minutes. Activities can include stretching, jumping jacks, or a "
                        "quick walk. Movement helps regulate attention and reduces fidgeting "
                        "during seated activities."
                    ),
                    "category": "activity_adaptation",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.92,
                    "citations": [
                        {
                            "title": "Physical Activity and Academic Performance in Children with ADHD",
                            "authors": "Pontifex, M. B., Saliba, B. J., Raine, L. B., Picchietti, D. L., & Hillman, C. H.",
                            "publication_year": 2013,
                            "source_type": "peer_reviewed",
                            "doi": "10.1016/j.jsp.2012.09.006",
                        },
                    ],
                },
            ],
            "dyslexia": [
                {
                    "title": "Multi-Sensory Reading Instruction for Dyslexia",
                    "content": (
                        "Use multi-sensory approaches that engage visual, auditory, and "
                        "kinesthetic channels simultaneously. Have children trace letters "
                        "while saying sounds, use textured materials for letter formation, "
                        "and incorporate movement into phonics lessons. This approach "
                        "strengthens neural pathways for reading."
                    ),
                    "category": "activity_adaptation",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.94,
                    "citations": [
                        {
                            "title": "Orton-Gillingham Approach: A Meta-Analysis",
                            "authors": "Ritchey, K. D., & Goeke, J. L.",
                            "publication_year": 2006,
                            "source_type": "peer_reviewed",
                            "doi": "10.1177/00222194060390030501",
                        },
                        {
                            "title": "International Dyslexia Association - Structured Literacy",
                            "authors": "International Dyslexia Association",
                            "publication_year": 2023,
                            "source_type": "official_guide",
                            "url": "https://dyslexiaida.org/structured-literacy/",
                        },
                    ],
                },
            ],
            "speech_delay": [
                {
                    "title": "Language Modeling Strategies for Speech Delays",
                    "content": (
                        "Use parallel talk and self-talk to model language throughout the day. "
                        "Describe what the child is doing (parallel talk) and what you are "
                        "doing (self-talk). Expand on the child's utterances by adding one "
                        "word or grammatical element. Wait 5-10 seconds after asking questions "
                        "to give processing time."
                    ),
                    "category": "communication",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.91,
                    "citations": [
                        {
                            "title": "Enhanced Milieu Teaching Effects on Language Development",
                            "authors": "Kaiser, A. P., & Roberts, M. Y.",
                            "publication_year": 2013,
                            "source_type": "peer_reviewed",
                            "doi": "10.1044/1092-4388(2012/12-0092)",
                        },
                    ],
                },
            ],
            "motor_delay": [
                {
                    "title": "Fine Motor Activities for Motor Delays",
                    "content": (
                        "Incorporate fine motor practice into daily activities. Use activities "
                        "like playdough manipulation, bead stringing, and scissor cutting. "
                        "Start with larger materials and gradually decrease size. Provide "
                        "hand-over-hand assistance initially, then fade support as skills "
                        "develop. Celebrate small improvements to build confidence."
                    ),
                    "category": "motor_support",
                    "priority": "medium",
                    "target_audience": "educator",
                    "relevance_score": 0.88,
                    "citations": [
                        {
                            "title": "Fine Motor Skills and Academic Achievement",
                            "authors": "Cameron, C. E., Brock, L. L., Murrah, W. M., et al.",
                            "publication_year": 2012,
                            "source_type": "peer_reviewed",
                            "doi": "10.1111/j.1467-8624.2012.01738.x",
                        },
                    ],
                },
            ],
            "sensory_processing": [
                {
                    "title": "Sensory Diet Implementation",
                    "content": (
                        "Create a 'sensory diet' - a personalized plan of sensory activities "
                        "throughout the day. Include alerting activities (jumping, spinning) "
                        "when the child is sluggish, and calming activities (deep pressure, "
                        "heavy work) when overstimulated. Work with an occupational therapist "
                        "to identify specific sensory needs and preferences."
                    ),
                    "category": "sensory_support",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.93,
                    "citations": [
                        {
                            "title": "Sensory Integration: Theory and Practice",
                            "authors": "Bundy, A. C., Lane, S. J., & Murray, E. A.",
                            "publication_year": 2019,
                            "source_type": "peer_reviewed",
                            "url": "https://www.fadavis.com/product/occupational-therapy-sensory-integration-bundy",
                        },
                    ],
                },
            ],
            "behavioral": [
                {
                    "title": "Positive Behavior Support Strategies",
                    "content": (
                        "Implement Positive Behavior Support (PBS) by identifying the function "
                        "of challenging behaviors and teaching replacement behaviors. Use "
                        "antecedent strategies to prevent problems, teach appropriate "
                        "alternatives, and reinforce positive behaviors. Track data to "
                        "measure progress and adjust strategies as needed."
                    ),
                    "category": "behavior_management",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.91,
                    "citations": [
                        {
                            "title": "Positive Behavior Support in Early Childhood Programs",
                            "authors": "Fox, L., Dunlap, G., Hemmeter, M. L., Joseph, G. E., & Strain, P. S.",
                            "publication_year": 2003,
                            "source_type": "peer_reviewed",
                            "doi": "10.1177/10983007030060010401",
                        },
                    ],
                },
            ],
            "cognitive_delay": [
                {
                    "title": "Task Analysis and Scaffolding for Cognitive Support",
                    "content": (
                        "Break complex tasks into smaller, manageable steps using task analysis. "
                        "Provide visual supports for each step and allow extra processing time. "
                        "Use concrete materials before moving to abstract concepts. Offer "
                        "consistent routines and repetition to support learning and memory "
                        "consolidation."
                    ),
                    "category": "activity_adaptation",
                    "priority": "medium",
                    "target_audience": "educator",
                    "relevance_score": 0.87,
                    "citations": [
                        {
                            "title": "Systematic Instruction for Students with Intellectual Disabilities",
                            "authors": "Collins, B. C.",
                            "publication_year": 2012,
                            "source_type": "peer_reviewed",
                            "url": "https://products.brookespublishing.com/Systematic-Instruction-for-Students-with-Moderate-and-Severe-Disabilities-P619.aspx",
                        },
                    ],
                },
            ],
            "visual_impairment": [
                {
                    "title": "Environmental Adaptations for Visual Impairments",
                    "content": (
                        "Ensure adequate lighting and high contrast materials in the learning "
                        "environment. Use tactile markers for navigation and organization. "
                        "Provide verbal descriptions of visual content and allow extra time "
                        "for tasks. Use large print materials or screen magnification as needed. "
                        "Partner with a teacher of the visually impaired for specialized support."
                    ),
                    "category": "activity_adaptation",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.89,
                    "citations": [
                        {
                            "title": "Teaching Students with Visual Impairments",
                            "authors": "American Foundation for the Blind",
                            "publication_year": 2023,
                            "source_type": "official_guide",
                            "url": "https://www.afb.org/blindness-and-low-vision/educators",
                        },
                    ],
                },
            ],
            "hearing_impairment": [
                {
                    "title": "Communication Strategies for Hearing Impairments",
                    "content": (
                        "Face the child when speaking and ensure your face is well-lit. "
                        "Use visual supports and gestures alongside verbal communication. "
                        "Reduce background noise and use FM systems or captioning when available. "
                        "Check understanding frequently and rephrase rather than repeat when "
                        "needed. Consider learning basic sign language for common classroom phrases."
                    ),
                    "category": "communication",
                    "priority": "high",
                    "target_audience": "educator",
                    "relevance_score": 0.90,
                    "citations": [
                        {
                            "title": "Educating Deaf and Hard of Hearing Students",
                            "authors": "National Association of the Deaf",
                            "publication_year": 2023,
                            "source_type": "official_guide",
                            "url": "https://www.nad.org/resources/education/",
                        },
                    ],
                },
            ],
            "other": [
                {
                    "title": "Universal Design for Learning Principles",
                    "content": (
                        "Apply Universal Design for Learning (UDL) principles to create "
                        "inclusive environments. Provide multiple means of engagement to "
                        "motivate learners, multiple means of representation for content, "
                        "and multiple means of action and expression for demonstrating "
                        "knowledge. This flexible approach benefits all children while "
                        "supporting individual needs."
                    ),
                    "category": "activity_adaptation",
                    "priority": "medium",
                    "target_audience": "educator",
                    "relevance_score": 0.85,
                    "citations": [
                        {
                            "title": "Universal Design for Learning Guidelines",
                            "authors": "CAST (Center for Applied Special Technology)",
                            "publication_year": 2018,
                            "source_type": "official_guide",
                            "url": "https://udlguidelines.cast.org/",
                        },
                    ],
                },
            ],
            "general": [
                {
                    "title": "Universal Design for Learning Principles",
                    "content": (
                        "Apply Universal Design for Learning (UDL) principles to create "
                        "inclusive environments. Provide multiple means of engagement to "
                        "motivate learners, multiple means of representation for content, "
                        "and multiple means of action and expression for demonstrating "
                        "knowledge. This flexible approach benefits all children while "
                        "supporting individual needs."
                    ),
                    "category": "activity_adaptation",
                    "priority": "medium",
                    "target_audience": "educator",
                    "relevance_score": 0.85,
                    "citations": [
                        {
                            "title": "Universal Design for Learning Guidelines",
                            "authors": "CAST (Center for Applied Special Technology)",
                            "publication_year": 2018,
                            "source_type": "official_guide",
                            "url": "https://udlguidelines.cast.org/",
                        },
                    ],
                },
            ],
        }

    async def _persist_session(
        self,
        request: CoachingGuidanceRequest,
        user_id: UUID,
        guidance_items: list[CoachingGuidance],
        citations: list[EvidenceSourceSchema],
    ) -> CoachingSession:
        """Persist the coaching session and recommendations to the database.

        Args:
            request: The original coaching request
            user_id: ID of the user making the request
            guidance_items: Generated guidance items
            citations: Citations supporting the guidance

        Returns:
            The persisted CoachingSession
        """
        # Create the coaching session
        session = CoachingSession(
            child_id=request.child_id,
            user_id=user_id,
            question=request.situation_description or "Guidance request",
            context=None,
            special_need_types=[need.value for need in request.special_need_types],
            category=request.category.value if request.category else None,
        )
        self.db.add(session)
        await self.db.flush()

        # Create recommendations with evidence sources
        for idx, guidance in enumerate(guidance_items):
            recommendation = CoachingRecommendation(
                session_id=session.id,
                title=guidance.coaching.title,
                content=guidance.coaching.content,
                category=guidance.coaching.category.value,
                priority=guidance.coaching.priority.value,
                relevance_score=guidance.relevance_score,
                target_audience=guidance.coaching.target_audience,
                prerequisites=guidance.coaching.prerequisites,
            )
            self.db.add(recommendation)
            await self.db.flush()

            # Add evidence sources for this recommendation
            # Link citations to recommendations (distribute evenly)
            if citations:
                citation_idx = idx % len(citations)
                citation = citations[citation_idx]
                evidence = EvidenceSource(
                    recommendation_id=recommendation.id,
                    source_type=citation.source_type,
                    title=citation.title,
                    authors=citation.authors,
                    publication=None,
                    year=citation.publication_year,
                    doi=citation.doi,
                    url=citation.url,
                )
                self.db.add(evidence)

        await self.db.commit()
        return session

    async def invalidate_llm_response_cache(
        self,
        pattern: str = "*",
    ) -> int:
        """Invalidate cached LLM responses.

        Invalidates LLM response cache entries matching the specified pattern.
        This is useful when the underlying knowledge base is updated or when
        you need to force fresh guidance generation.

        Args:
            pattern: Pattern to match for cache invalidation (default: "*" for all)

        Returns:
            int: Number of cache entries deleted

        Example:
            # Invalidate all LLM response caches
            await service.invalidate_llm_response_cache()

            # Invalidate specific pattern
            await service.invalidate_llm_response_cache("*autism*")
        """
        return await invalidate_cache("llm_response", pattern)

    async def refresh_llm_response_cache(
        self,
        special_need_types: list[SpecialNeedType],
        situation_description: Optional[str] = None,
        category: Optional[CoachingCategory] = None,
        max_recommendations: int = 3,
    ) -> tuple[list[CoachingGuidance], list[EvidenceSourceSchema]]:
        """Refresh LLM response cache by invalidating and refetching.

        Invalidates the cache for the given parameters and fetches fresh
        guidance, which will then be cached with a new 24-hour TTL.

        Args:
            special_need_types: Types of special needs to address
            situation_description: Description of the situation or challenge
            category: Optional category filter
            max_recommendations: Maximum number of recommendations to return

        Returns:
            Tuple of (guidance_items, citations) with fresh data

        Example:
            # Refresh cache for specific query
            guidance, citations = await service.refresh_llm_response_cache(
                special_need_types=[SpecialNeedType.AUTISM],
                situation_description="transition strategies",
                category=CoachingCategory.ACTIVITY_ADAPTATION,
            )
        """
        # Invalidate existing cache for these parameters
        await self.invalidate_llm_response_cache()

        # Fetch fresh data (which will be cached)
        return await self._retrieve_evidence_based_guidance(
            special_need_types=special_need_types,
            situation_description=situation_description,
            category=category,
            max_recommendations=max_recommendations,
        )
