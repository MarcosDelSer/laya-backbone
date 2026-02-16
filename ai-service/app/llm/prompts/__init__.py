"""Prompt template system for LAYA AI Service.

Provides a flexible template system for constructing LLM prompts
with variable substitution, validation, and formatting.

Usage:
    from app.llm.prompts import PromptTemplate, SystemUserPromptTemplate

    # Simple template
    template = PromptTemplate("Hello {name}!")
    message = template.render(name="World")

    # System/User template for LLM conversations
    prompt = SystemUserPromptTemplate(
        system_template="You are a helpful assistant.",
        user_template="User says: {input}",
    )
    system_msg, user_msg = prompt.render(input="Hello!")

    # Activity recommendation prompts
    from app.llm.prompts import ActivityRecommendationPrompt
    prompt = ActivityRecommendationPrompt()
    system, user = prompt.render(
        age_group="3-4 years",
        num_children=12,
        duration=30,
        setting="indoor"
    )
"""

from app.llm.prompts.activity_prompts import (
    ActivityAdaptationPrompt,
    ActivityRecommendationPrompt,
    DailyPlanningPrompt,
)
from app.llm.prompts.base import (
    InvalidVariableError,
    MissingVariableError,
    PromptTemplate,
    PromptTemplateError,
    SystemUserPromptTemplate,
)
from app.llm.prompts.report_prompts import DailyReportPrompt

__all__ = [
    # Base classes
    "InvalidVariableError",
    "MissingVariableError",
    "PromptTemplate",
    "PromptTemplateError",
    "SystemUserPromptTemplate",
    # Activity prompts
    "ActivityAdaptationPrompt",
    "ActivityRecommendationPrompt",
    "DailyPlanningPrompt",
    # Report prompts
    "DailyReportPrompt",
]
