"""Coaching guidance prompt templates for LAYA AI Service.

Provides specialized prompt templates for generating coaching guidance
and professional development support for early childhood educators,
focusing on best practices, child development strategies, and
pedagogical approaches.
"""

from typing import Any, Optional

from app.llm.prompts.base import SystemUserPromptTemplate


# System prompt for coaching guidance
COACHING_GUIDANCE_SYSTEM_PROMPT = """You are LAYA, an expert early childhood education AI assistant specializing in coaching and professional development for childcare educators in Quebec.

Your role is to provide thoughtful, evidence-based coaching guidance that:
- Supports educators in their professional growth and practice
- Aligns with Quebec's educational program framework (Programme éducatif)
- Promotes reflective practice and continuous improvement
- Offers practical strategies grounded in child development research
- Respects educators' expertise while expanding their toolkit

When providing coaching guidance, always consider:
1. The developmental appropriateness of suggested strategies
2. The educator's current experience level and context
3. Evidence-based practices in early childhood education
4. Cultural sensitivity and inclusive practices
5. Practical implementation within childcare settings

Respond in a supportive, non-judgmental manner that empowers educators to make informed decisions about their practice."""

# User prompt template for coaching guidance
COACHING_GUIDANCE_USER_TEMPLATE = """Please provide coaching guidance for the following situation:

Educator Context: {educator_context}
Child/Children Age Group: {age_group}
Situation/Challenge: {situation}
{goal_section}
{approaches_section}
{constraints_section}

Please provide thoughtful coaching guidance with practical strategies and explanations."""


class CoachingGuidancePrompt(SystemUserPromptTemplate):
    """Prompt template for generating coaching guidance for educators.

    Provides a structured way to request coaching guidance and
    professional development support for early childhood educators.

    Attributes:
        system_prompt: The system message defining the AI's role
        user_template: Template for the user message with placeholders

    Example:
        >>> prompt = CoachingGuidancePrompt()
        >>> system, user = prompt.render(
        ...     educator_context="New educator, 6 months experience",
        ...     age_group="2-3 years",
        ...     situation="Managing transitions between activities"
        ... )
    """

    def __init__(
        self,
        *,
        system_template: Optional[str] = None,
        user_template: Optional[str] = None,
        name: str = "coaching_guidance",
    ) -> None:
        """Initialize the coaching guidance prompt.

        Args:
            system_template: Custom system template (uses default if None)
            user_template: Custom user template (uses default if None)
            name: Identifier for this prompt template
        """
        self._system_prompt = system_template or COACHING_GUIDANCE_SYSTEM_PROMPT

        super().__init__(
            system_template=self._system_prompt,
            user_template=user_template or COACHING_GUIDANCE_USER_TEMPLATE,
            name=name,
        )

    @property
    def system_prompt(self) -> str:
        """Get the system prompt for coaching guidance.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._system_prompt

    def render(
        self,
        educator_context: str,
        age_group: str,
        situation: str,
        goal: Optional[str] = None,
        approaches_tried: Optional[list[str]] = None,
        constraints: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the coaching guidance prompt.

        Args:
            educator_context: Context about the educator (experience, role)
            age_group: Age group of children involved
            situation: Description of the situation or challenge
            goal: Optional specific goal or outcome desired
            approaches_tried: Optional list of approaches already attempted
            constraints: Optional constraints or limitations to consider

        Returns:
            Tuple of (system_message, user_message)

        Example:
            >>> prompt = CoachingGuidancePrompt()
            >>> system, user = prompt.render(
            ...     educator_context="Experienced educator, lead teacher",
            ...     age_group="4-5 years",
            ...     situation="Supporting a child with separation anxiety",
            ...     goal="Help the child feel secure during drop-off",
            ...     approaches_tried=["Comfort objects", "Gradual separation"]
            ... )
        """
        # Build goal section
        goal_section = (
            f"Desired Outcome: {goal}"
            if goal
            else "Desired Outcome: General improvement and best practices"
        )

        # Build approaches tried section
        if approaches_tried:
            approaches_list = ", ".join(approaches_tried)
            approaches_section = f"Approaches Already Tried: {approaches_list}"
        else:
            approaches_section = "Approaches Already Tried: None specified"

        # Build constraints section
        constraints_section = (
            f"Constraints/Considerations: {constraints}"
            if constraints
            else "Constraints/Considerations: None specified"
        )

        # Render using parent's render method with all variables
        return super().render(
            educator_context=educator_context,
            age_group=age_group,
            situation=situation,
            goal_section=goal_section,
            approaches_section=approaches_section,
            constraints_section=constraints_section,
            **kwargs,
        )

    def render_for_api(
        self,
        educator_context: str,
        age_group: str,
        situation: str,
        goal: Optional[str] = None,
        approaches_tried: Optional[list[str]] = None,
        constraints: Optional[str] = None,
    ) -> list[dict[str, str]]:
        """Render the prompt as a list of messages for LLM API calls.

        Formats the prompt as a list of message dictionaries compatible
        with OpenAI and Anthropic API formats.

        Args:
            educator_context: Context about the educator
            age_group: Age group of children involved
            situation: Description of the situation or challenge
            goal: Optional specific goal or outcome desired
            approaches_tried: Optional list of approaches already attempted
            constraints: Optional constraints or limitations

        Returns:
            List of message dictionaries with 'role' and 'content' keys

        Example:
            >>> prompt = CoachingGuidancePrompt()
            >>> messages = prompt.render_for_api(
            ...     educator_context="New educator",
            ...     age_group="3-4 years",
            ...     situation="Encouraging cooperative play"
            ... )
            >>> # [{'role': 'system', 'content': '...'}, {'role': 'user', 'content': '...'}]
        """
        system_msg, user_msg = self.render(
            educator_context=educator_context,
            age_group=age_group,
            situation=situation,
            goal=goal,
            approaches_tried=approaches_tried,
            constraints=constraints,
        )

        return [
            {"role": "system", "content": system_msg},
            {"role": "user", "content": user_msg},
        ]


class BehaviorGuidancePrompt(SystemUserPromptTemplate):
    """Prompt template for guidance on child behavior support.

    Provides strategies for understanding and supporting children's
    behavior using positive, developmentally appropriate approaches.

    Example:
        >>> prompt = BehaviorGuidancePrompt()
        >>> system, user = prompt.render(
        ...     child_age="3 years",
        ...     behavior_description="Difficulty sharing toys with peers",
        ...     context="During free play time",
        ...     frequency="Daily occurrence"
        ... )
    """

    _SYSTEM_TEMPLATE = """You are LAYA, an expert early childhood education AI assistant specializing in positive behavior guidance for childcare centers in Quebec.

Your role is to help educators understand and support children's behavior using:
- Positive guidance techniques that respect children's dignity
- Developmentally appropriate expectations and strategies
- Understanding of the underlying needs driving behavior
- Proactive environmental and relational approaches
- Evidence-based practices from child development research

When providing behavior guidance:
1. Help educators understand the developmental context of the behavior
2. Suggest proactive strategies to prevent challenging situations
3. Offer in-the-moment response strategies
4. Include follow-up and relationship-building approaches
5. Consider the child's perspective and emotional needs

Always promote positive, supportive approaches that build children's self-regulation skills and maintain strong educator-child relationships."""

    _USER_TEMPLATE = """Please provide guidance for supporting the following behavior:

Child Age: {child_age}
Behavior Observed: {behavior_description}
Context/Setting: {context}
Frequency: {frequency}
{triggers_section}
{strategies_section}
{additional_info_section}

Please provide developmentally appropriate guidance with practical strategies."""

    def __init__(
        self,
        *,
        name: str = "behavior_guidance",
    ) -> None:
        """Initialize the behavior guidance prompt.

        Args:
            name: Identifier for this prompt template
        """
        super().__init__(
            system_template=self._SYSTEM_TEMPLATE,
            user_template=self._USER_TEMPLATE,
            name=name,
        )

    @property
    def system_prompt(self) -> str:
        """Get the system prompt for behavior guidance.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._SYSTEM_TEMPLATE

    def render(
        self,
        child_age: str,
        behavior_description: str,
        context: str,
        frequency: str,
        known_triggers: Optional[list[str]] = None,
        strategies_tried: Optional[list[str]] = None,
        additional_info: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the behavior guidance prompt.

        Args:
            child_age: Age of the child
            behavior_description: Description of the behavior observed
            context: Setting or situation where behavior occurs
            frequency: How often the behavior occurs
            known_triggers: Optional list of known triggers
            strategies_tried: Optional list of strategies already attempted
            additional_info: Optional additional relevant information

        Returns:
            Tuple of (system_message, user_message)
        """
        # Build triggers section
        if known_triggers:
            triggers_list = ", ".join(known_triggers)
            triggers_section = f"Known Triggers: {triggers_list}"
        else:
            triggers_section = "Known Triggers: Not yet identified"

        # Build strategies section
        if strategies_tried:
            strategies_list = ", ".join(strategies_tried)
            strategies_section = f"Strategies Already Tried: {strategies_list}"
        else:
            strategies_section = "Strategies Already Tried: None specified"

        # Build additional info section
        additional_info_section = (
            f"Additional Information: {additional_info}"
            if additional_info
            else "Additional Information: None provided"
        )

        return super().render(
            child_age=child_age,
            behavior_description=behavior_description,
            context=context,
            frequency=frequency,
            triggers_section=triggers_section,
            strategies_section=strategies_section,
            additional_info_section=additional_info_section,
            **kwargs,
        )


class ParentCommunicationPrompt(SystemUserPromptTemplate):
    """Prompt template for parent communication guidance.

    Helps educators craft professional, empathetic communications
    with parents about various topics including development,
    behavior, and daily updates.

    Example:
        >>> prompt = ParentCommunicationPrompt()
        >>> system, user = prompt.render(
        ...     communication_type="Developmental milestone update",
        ...     child_age="18 months",
        ...     topic="First words and language development",
        ...     tone="Celebratory and informative"
        ... )
    """

    _SYSTEM_TEMPLATE = """You are LAYA, an expert early childhood education AI assistant specializing in parent-educator communication for childcare centers in Quebec.

Your role is to help educators communicate effectively with families by:
- Crafting clear, professional, and empathetic messages
- Balancing honesty with sensitivity and tact
- Celebrating children's growth and achievements
- Addressing concerns constructively and collaboratively
- Building strong home-school partnerships

When helping with parent communication:
1. Use warm, professional language accessible to all families
2. Focus on specific observations and facts
3. Frame challenges as opportunities for collaboration
4. Respect family privacy and cultural differences
5. Offer concrete examples and actionable suggestions

Always maintain a partnership mindset that positions parents and educators as a team working together for the child's benefit."""

    _USER_TEMPLATE = """Please help craft a parent communication for the following:

Communication Type: {communication_type}
Child Age: {child_age}
Topic: {topic}
Desired Tone: {tone}
{key_points_section}
{context_section}

Please provide a draft communication that is professional, empathetic, and effective."""

    def __init__(
        self,
        *,
        name: str = "parent_communication",
    ) -> None:
        """Initialize the parent communication prompt.

        Args:
            name: Identifier for this prompt template
        """
        super().__init__(
            system_template=self._SYSTEM_TEMPLATE,
            user_template=self._USER_TEMPLATE,
            name=name,
        )

    @property
    def system_prompt(self) -> str:
        """Get the system prompt for parent communication.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._SYSTEM_TEMPLATE

    def render(
        self,
        communication_type: str,
        child_age: str,
        topic: str,
        tone: str,
        key_points: Optional[list[str]] = None,
        context: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the parent communication prompt.

        Args:
            communication_type: Type of communication (e.g., "update", "concern")
            child_age: Age of the child
            topic: Main topic of the communication
            tone: Desired tone (e.g., "warm", "professional", "celebratory")
            key_points: Optional list of key points to include
            context: Optional additional context

        Returns:
            Tuple of (system_message, user_message)
        """
        # Build key points section
        if key_points:
            points_list = "\n".join(f"- {point}" for point in key_points)
            key_points_section = f"Key Points to Include:\n{points_list}"
        else:
            key_points_section = "Key Points to Include: None specified"

        # Build context section
        context_section = (
            f"Additional Context: {context}"
            if context
            else "Additional Context: None provided"
        )

        return super().render(
            communication_type=communication_type,
            child_age=child_age,
            topic=topic,
            tone=tone,
            key_points_section=key_points_section,
            context_section=context_section,
            **kwargs,
        )
