"""Daily report generation prompt templates for LAYA AI Service.

Provides specialized prompt templates for generating daily reports
for childcare settings, summarizing activities, observations, and
child development progress.
"""

from typing import Any, Optional

from app.llm.prompts.base import SystemUserPromptTemplate


# System prompt for daily report generation
DAILY_REPORT_SYSTEM_PROMPT = """You are LAYA, an expert early childhood education AI assistant specializing in documentation and reporting for childcare centers in Quebec.

Your role is to help educators create comprehensive, professional daily reports that:
- Summarize the day's activities and learning experiences
- Document children's participation and engagement
- Highlight developmental observations and milestones
- Communicate effectively with parents/guardians
- Align with Quebec's educational program framework (Programme éducatif)

When generating reports, always:
1. Use clear, professional language accessible to parents
2. Focus on positive observations and growth
3. Include specific examples when possible
4. Maintain child confidentiality and privacy
5. Balance factual information with developmental insights

Reports should be warm, informative, and celebrate children's daily experiences."""

# User prompt template for daily report generation
DAILY_REPORT_USER_TEMPLATE = """Please generate a daily report based on the following information:

Date: {report_date}
Group/Classroom: {classroom_name}
Age Group: {age_group}
Number of Children Present: {num_children}

Activities Completed:
{activities_section}

{observations_section}
{meals_section}
{notes_section}

Please create a comprehensive daily report suitable for sharing with parents/guardians."""


class DailyReportPrompt(SystemUserPromptTemplate):
    """Prompt template for generating daily reports.

    Provides a structured way to request daily report generation
    for childcare settings, summarizing activities, observations,
    and child progress.

    Attributes:
        system_prompt: The system message defining the AI's role
        user_template: Template for the user message with placeholders

    Example:
        >>> prompt = DailyReportPrompt()
        >>> system, user = prompt.render(
        ...     report_date="2024-01-15",
        ...     classroom_name="Butterflies Room",
        ...     age_group="3-4 years",
        ...     num_children=12,
        ...     activities=["Morning circle time", "Art project", "Outdoor play"]
        ... )
    """

    def __init__(
        self,
        *,
        system_template: Optional[str] = None,
        user_template: Optional[str] = None,
        name: str = "daily_report",
    ) -> None:
        """Initialize the daily report prompt.

        Args:
            system_template: Custom system template (uses default if None)
            user_template: Custom user template (uses default if None)
            name: Identifier for this prompt template
        """
        self._system_prompt = system_template or DAILY_REPORT_SYSTEM_PROMPT

        super().__init__(
            system_template=self._system_prompt,
            user_template=user_template or DAILY_REPORT_USER_TEMPLATE,
            name=name,
        )

    @property
    def system_prompt(self) -> str:
        """Get the system prompt for daily report generation.

        Returns:
            The system prompt string defining the AI's role
        """
        return self._system_prompt

    def render(
        self,
        report_date: str,
        classroom_name: str,
        age_group: str,
        num_children: int,
        activities: Optional[list[str]] = None,
        observations: Optional[list[str]] = None,
        meals_info: Optional[str] = None,
        additional_notes: Optional[str] = None,
        **kwargs: Any,
    ) -> tuple[str, str]:
        """Render the daily report prompt.

        Args:
            report_date: Date of the report (e.g., "2024-01-15")
            classroom_name: Name of the classroom or group
            age_group: Target age group (e.g., "3-4 years")
            num_children: Number of children present
            activities: Optional list of activities completed
            observations: Optional list of notable observations
            meals_info: Optional information about meals/snacks
            additional_notes: Optional additional notes or comments

        Returns:
            Tuple of (system_message, user_message)

        Example:
            >>> prompt = DailyReportPrompt()
            >>> system, user = prompt.render(
            ...     report_date="2024-01-15",
            ...     classroom_name="Sunshine Room",
            ...     age_group="2-3 years",
            ...     num_children=8,
            ...     activities=["Sensory play", "Story time"],
            ...     observations=["Children showed great interest in the new sensory bin"]
            ... )
        """
        # Build activities section
        if activities:
            activities_list = "\n".join(f"- {activity}" for activity in activities)
            activities_section = activities_list
        else:
            activities_section = "- No specific activities recorded"

        # Build observations section
        if observations:
            observations_list = "\n".join(f"- {obs}" for obs in observations)
            observations_section = f"Notable Observations:\n{observations_list}"
        else:
            observations_section = "Notable Observations: General participation throughout the day"

        # Build meals section
        if meals_info:
            meals_section = f"Meals/Snacks: {meals_info}"
        else:
            meals_section = "Meals/Snacks: Regular meal schedule followed"

        # Build additional notes section
        if additional_notes:
            notes_section = f"Additional Notes: {additional_notes}"
        else:
            notes_section = "Additional Notes: None"

        # Render using parent's render method with all variables
        return super().render(
            report_date=report_date,
            classroom_name=classroom_name,
            age_group=age_group,
            num_children=num_children,
            activities_section=activities_section,
            observations_section=observations_section,
            meals_section=meals_section,
            notes_section=notes_section,
            **kwargs,
        )

    def render_for_api(
        self,
        report_date: str,
        classroom_name: str,
        age_group: str,
        num_children: int,
        activities: Optional[list[str]] = None,
        observations: Optional[list[str]] = None,
        meals_info: Optional[str] = None,
        additional_notes: Optional[str] = None,
    ) -> list[dict[str, str]]:
        """Render the prompt as a list of messages for LLM API calls.

        Formats the prompt as a list of message dictionaries compatible
        with OpenAI and Anthropic API formats.

        Args:
            report_date: Date of the report
            classroom_name: Name of the classroom or group
            age_group: Target age group
            num_children: Number of children present
            activities: Optional list of activities completed
            observations: Optional list of notable observations
            meals_info: Optional information about meals/snacks
            additional_notes: Optional additional notes or comments

        Returns:
            List of message dictionaries with 'role' and 'content' keys

        Example:
            >>> prompt = DailyReportPrompt()
            >>> messages = prompt.render_for_api(
            ...     report_date="2024-01-15",
            ...     classroom_name="Rainbow Room",
            ...     age_group="4-5 years",
            ...     num_children=15,
            ...     activities=["Science experiment", "Music and movement"]
            ... )
            >>> # [{'role': 'system', 'content': '...'}, {'role': 'user', 'content': '...'}]
        """
        system_msg, user_msg = self.render(
            report_date=report_date,
            classroom_name=classroom_name,
            age_group=age_group,
            num_children=num_children,
            activities=activities,
            observations=observations,
            meals_info=meals_info,
            additional_notes=additional_notes,
        )

        return [
            {"role": "system", "content": system_msg},
            {"role": "user", "content": user_msg},
        ]
