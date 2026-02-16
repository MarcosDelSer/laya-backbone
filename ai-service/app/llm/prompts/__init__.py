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
"""

from app.llm.prompts.base import (
    InvalidVariableError,
    MissingVariableError,
    PromptTemplate,
    PromptTemplateError,
    SystemUserPromptTemplate,
)

__all__ = [
    "InvalidVariableError",
    "MissingVariableError",
    "PromptTemplate",
    "PromptTemplateError",
    "SystemUserPromptTemplate",
]
