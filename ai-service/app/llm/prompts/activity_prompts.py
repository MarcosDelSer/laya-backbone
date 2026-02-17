"""Activity recommendation prompt templates for LAYA AI Service.

Provides specialized prompt templates for generating educational activity
recommendations for childcare settings, considering child development stages,
learning objectives, and regulatory compliance.
"""

from typing import Any, Optional

from app.llm.prompts.base import PromptTemplate, SystemUserPromptTemplate


# System prompt for activity recommendations
ACTIVITY_RECOMMENDATION_SYSTEM_PROMPT = """You are LAYA, an expert early childhood education AI assistant specializing in activity recommendations for childcare centers in Quebec.

Your role is to recommend age-appropriate, engaging, and educational activities that:
- Align with Quebec's educational program framework (Programme éducatif)
- Support holistic child development across all domains
- Are practical and feasible for childcare settings
- Consider safety and regulatory requirements
- Promote inclusive learning for all children

When recommending activities, always consider:
1. Developmental appropriateness for the specified age group
2. Required materials and preparation time
3. Learning objectives and developmental domains addressed
4. Adaptations for children with different needs
5. Indoor/outdoor suitability based on weather and setting

Respond in a clear, professional manner suitable for early childhood educators."""

# User prompt template for activity recommendations
ACTIVITY_RECOMMENDATION_USER_TEMPLATE = """Please recommend activities based on the following criteria:

Age Group: {age_group}
Number of Children: {num_children}
Available Time: {duration} minutes
Setting: {setting}
{theme_section}
{materials_section}
{objectives_section}

Please provide {num_activities} activity recommendation(s) that are appropriate for this group."""


class ActivityRecommendationPrompt(SystemUserPromptTemplate):
    """Prompt template for generating activity recommendations.

    Provides a structured way to request age-appropriate educational
    activity recommendations for childcare settings.

    Attributes:
        system_prompt: The system message defining the AI's role
        user_template: Template for the user message with placeholders

    Example:
        >>> prompt = ActivityRecommendationPrompt()
        >>> system, user = prompt.render(
        ...     age_group="3-4 years",
        ...     num_children=12,
        ...     duration=30,
        ...     setting="indoor",
        ...     num_activities=3
        ... )
    """

    def __init__(
        self,
        *,
        system_template: Optional[str] = None,
        user_template: Optional[str] = None,
        name: str = "activity_recommendation",
    ) -> None:
        """Initialize the activity recommendation prompt.

        Args:
            system_template: Custom system template (uses default if None)
            user_template: Custom user template (uses default if None)
            name: Identifier for this prompt template
        """
        self._system_prompt = system_template or ACTIVITY_RECOMMENDATION_SYSTEM_PROMPT

        super().__init__(
            system_template=self._system_prompt,
            user_template=user_template or ACTIVITY_RECOMMENDATION_USER_TEMPLATE,
            name=name,
        )

    @property
    def system_prompt(self) -> str:
        """Get the system prompt for activity recommendations.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._system_prompt

    def render(
        self,
        age_group: str,
        num_children: int,
        duration: int,
        setting: str,
        num_activities: int = 3,
        theme: Optional[str] = None,
        available_materials: Optional[list[str]] = None,
        learning_objectives: Optional[list[str]] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the activity recommendation prompt.

        Args:
            age_group: Target age group (e.g., "3-4 years", "toddlers")
            num_children: Number of children in the group
            duration: Available time in minutes
            setting: Activity setting ("indoor", "outdoor", or "both")
            num_activities: Number of activities to recommend
            theme: Optional theme for activities (e.g., "nature", "seasons")
            available_materials: Optional list of available materials
            learning_objectives: Optional specific learning objectives

        Returns:
            Tuple of (system_message, user_message)

        Example:
            >>> prompt = ActivityRecommendationPrompt()
            >>> system, user = prompt.render(
            ...     age_group="2-3 years",
            ...     num_children=8,
            ...     duration=20,
            ...     setting="indoor",
            ...     theme="colors",
            ...     num_activities=2
            ... )
        """
        # Build optional sections
        theme_section = f"Theme/Topic: {theme}" if theme else "Theme/Topic: Any appropriate theme"

        if available_materials:
            materials_list = ", ".join(available_materials)
            materials_section = f"Available Materials: {materials_list}"
        else:
            materials_section = "Available Materials: Standard childcare supplies"

        if learning_objectives:
            objectives_list = ", ".join(learning_objectives)
            objectives_section = f"Learning Objectives: {objectives_list}"
        else:
            objectives_section = "Learning Objectives: General development"

        # Render using parent's render method with all variables
        return super().render(
            age_group=age_group,
            num_children=num_children,
            duration=duration,
            setting=setting,
            num_activities=num_activities,
            theme_section=theme_section,
            materials_section=materials_section,
            objectives_section=objectives_section,
            **kwargs,
        )

    def render_for_api(
        self,
        age_group: str,
        num_children: int,
        duration: int,
        setting: str,
        num_activities: int = 3,
        theme: Optional[str] = None,
        available_materials: Optional[list[str]] = None,
        learning_objectives: Optional[list[str]] = None,
    ) -> list[dict[str, str]]:
        """Render the prompt as a list of messages for LLM API calls.

        Formats the prompt as a list of message dictionaries compatible
        with OpenAI and Anthropic API formats.

        Args:
            age_group: Target age group
            num_children: Number of children in the group
            duration: Available time in minutes
            setting: Activity setting
            num_activities: Number of activities to recommend
            theme: Optional theme for activities
            available_materials: Optional list of available materials
            learning_objectives: Optional specific learning objectives

        Returns:
            List of message dictionaries with 'role' and 'content' keys

        Example:
            >>> prompt = ActivityRecommendationPrompt()
            >>> messages = prompt.render_for_api(
            ...     age_group="4-5 years",
            ...     num_children=15,
            ...     duration=45,
            ...     setting="outdoor"
            ... )
            >>> # [{'role': 'system', 'content': '...'}, {'role': 'user', 'content': '...'}]
        """
        system_msg, user_msg = self.render(
            age_group=age_group,
            num_children=num_children,
            duration=duration,
            setting=setting,
            num_activities=num_activities,
            theme=theme,
            available_materials=available_materials,
            learning_objectives=learning_objectives,
        )

        return [
            {"role": "system", "content": system_msg},
            {"role": "user", "content": user_msg},
        ]


class ActivityAdaptationPrompt(SystemUserPromptTemplate):
    """Prompt template for adapting activities for specific needs.

    Provides guidance on modifying activities to accommodate
    children with different abilities, needs, or preferences.

    Example:
        >>> prompt = ActivityAdaptationPrompt()
        >>> system, user = prompt.render(
        ...     activity_name="Finger painting",
        ...     activity_description="Children create artwork using finger paints",
        ...     adaptation_need="visual impairment",
        ...     age_group="3-4 years"
        ... )
    """

    _SYSTEM_TEMPLATE = """You are LAYA, an expert early childhood education AI assistant specializing in inclusive education and activity adaptation.

Your role is to help educators adapt activities to be accessible and engaging for all children, including those with:
- Physical disabilities or motor challenges
- Sensory processing differences
- Developmental delays
- Language or communication differences
- Behavioral or emotional needs

When suggesting adaptations:
1. Maintain the core learning objectives of the activity
2. Ensure adaptations are practical and dignified
3. Promote inclusion and participation
4. Consider safety requirements
5. Provide multiple adaptation options when possible

Always respond with practical, actionable suggestions that educators can implement immediately."""

    _USER_TEMPLATE = """Please suggest adaptations for the following activity:

Activity Name: {activity_name}
Activity Description: {activity_description}
Adaptation Need: {adaptation_need}
Age Group: {age_group}
{context_section}

Please provide specific, practical adaptations that maintain the activity's learning value while ensuring full participation."""

    def __init__(
        self,
        *,
        name: str = "activity_adaptation",
    ) -> None:
        """Initialize the activity adaptation prompt.

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
        """Get the system prompt for activity adaptation.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._SYSTEM_TEMPLATE

    def render(
        self,
        activity_name: str,
        activity_description: str,
        adaptation_need: str,
        age_group: str,
        additional_context: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the activity adaptation prompt.

        Args:
            activity_name: Name of the activity to adapt
            activity_description: Description of the original activity
            adaptation_need: Type of adaptation needed
            age_group: Target age group
            additional_context: Optional additional context

        Returns:
            Tuple of (system_message, user_message)
        """
        context_section = (
            f"Additional Context: {additional_context}"
            if additional_context
            else "Additional Context: None provided"
        )

        return super().render(
            activity_name=activity_name,
            activity_description=activity_description,
            adaptation_need=adaptation_need,
            age_group=age_group,
            context_section=context_section,
            **kwargs,
        )


class DailyPlanningPrompt(SystemUserPromptTemplate):
    """Prompt template for daily activity planning assistance.

    Helps educators plan a balanced day of activities considering
    energy levels, learning domains, and routine requirements.

    Example:
        >>> prompt = DailyPlanningPrompt()
        >>> system, user = prompt.render(
        ...     age_group="4-5 years",
        ...     num_children=16,
        ...     day_duration=8,
        ...     special_events="Field trip to fire station"
        ... )
    """

    _SYSTEM_TEMPLATE = """You are LAYA, an expert early childhood education AI assistant specializing in daily program planning for childcare centers in Quebec.

Your role is to help educators plan balanced, engaging daily schedules that:
- Follow Quebec's educational program framework (Programme éducatif)
- Balance active and quiet activities throughout the day
- Include all developmental domains (physical, cognitive, social-emotional, language)
- Respect children's natural rhythms and energy levels
- Allow for flexibility and child-led exploration
- Include appropriate transitions between activities
- Meet regulatory requirements for rest, meals, and outdoor time

Consider these daily routine elements:
- Morning arrival and free play
- Structured learning activities
- Outdoor play (minimum 60-90 minutes daily)
- Rest/nap time
- Meal and snack times
- Departure routines

Provide practical, well-organized daily plans that educators can follow and adapt."""

    _USER_TEMPLATE = """Please help plan activities for the following day:

Age Group: {age_group}
Number of Children: {num_children}
Day Duration: {day_duration} hours
{events_section}
{focus_section}
{constraints_section}

Please provide a balanced daily schedule with appropriate activities, transitions, and timing."""

    def __init__(
        self,
        *,
        name: str = "daily_planning",
    ) -> None:
        """Initialize the daily planning prompt.

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
        """Get the system prompt for daily planning.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._SYSTEM_TEMPLATE

    def render(
        self,
        age_group: str,
        num_children: int,
        day_duration: float,
        special_events: Optional[str] = None,
        focus_areas: Optional[list[str]] = None,
        constraints: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the daily planning prompt.

        Args:
            age_group: Target age group
            num_children: Number of children in the group
            day_duration: Length of the day in hours
            special_events: Any special events or visitors
            focus_areas: Learning areas to emphasize
            constraints: Any constraints or limitations

        Returns:
            Tuple of (system_message, user_message)
        """
        events_section = (
            f"Special Events: {special_events}"
            if special_events
            else "Special Events: None planned"
        )

        if focus_areas:
            focus_list = ", ".join(focus_areas)
            focus_section = f"Focus Areas: {focus_list}"
        else:
            focus_section = "Focus Areas: Balanced across all domains"

        constraints_section = (
            f"Constraints: {constraints}"
            if constraints
            else "Constraints: None specified"
        )

        return super().render(
            age_group=age_group,
            num_children=num_children,
            day_duration=day_duration,
            events_section=events_section,
            focus_section=focus_section,
            constraints_section=constraints_section,
            **kwargs,
        )
