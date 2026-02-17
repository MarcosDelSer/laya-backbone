"""Unit tests for LLM prompt templates.

Tests for base prompt templates, activity prompts, report prompts,
coaching prompts, variable extraction, validation, and rendering.
"""

from __future__ import annotations

import pytest

from app.llm.prompts import (
    ActivityAdaptationPrompt,
    ActivityRecommendationPrompt,
    BehaviorGuidancePrompt,
    CoachingGuidancePrompt,
    DailyPlanningPrompt,
    DailyReportPrompt,
    InvalidVariableError,
    MissingVariableError,
    ParentCommunicationPrompt,
    PromptTemplate,
    PromptTemplateError,
    SystemUserPromptTemplate,
)


# ============================================================================
# PromptTemplate Base Class Tests
# ============================================================================


class TestPromptTemplate:
    """Test suite for base PromptTemplate class."""

    def test_create_template_with_valid_string(self) -> None:
        """Test creating a template with a valid string.

        Verifies that a PromptTemplate can be created with a non-empty
        template string containing variables.
        """
        template = PromptTemplate("Hello {name}!")
        assert template.template == "Hello {name}!"

    def test_create_template_raises_error_for_empty_string(self) -> None:
        """Test that empty template string raises ValueError.

        Verifies that attempting to create a PromptTemplate with an
        empty string raises a ValueError.
        """
        with pytest.raises(ValueError) as exc_info:
            PromptTemplate("")

        assert "empty" in str(exc_info.value).lower()

    def test_create_template_raises_error_for_none(self) -> None:
        """Test that None template raises ValueError.

        Verifies that attempting to create a PromptTemplate with None
        raises a ValueError.
        """
        with pytest.raises(ValueError):
            PromptTemplate(None)

    def test_extract_single_variable(self) -> None:
        """Test extracting a single variable from template.

        Verifies that variables property correctly identifies
        a single variable placeholder.
        """
        template = PromptTemplate("Hello {name}!")
        assert template.variables == {"name"}

    def test_extract_multiple_variables(self) -> None:
        """Test extracting multiple variables from template.

        Verifies that variables property correctly identifies
        all variable placeholders in a template.
        """
        template = PromptTemplate("Hello {name}, you are {age} years old from {city}.")
        assert template.variables == {"name", "age", "city"}

    def test_extract_no_variables(self) -> None:
        """Test template with no variables.

        Verifies that variables property returns empty set
        when no placeholders exist.
        """
        template = PromptTemplate("Hello World!")
        assert template.variables == set()

    def test_extract_repeated_variables(self) -> None:
        """Test that repeated variables are counted once.

        Verifies that variables appearing multiple times
        are only counted once in the variables set.
        """
        template = PromptTemplate("Hello {name}! Nice to meet you, {name}.")
        assert template.variables == {"name"}

    def test_render_with_all_variables(self) -> None:
        """Test rendering with all required variables.

        Verifies that render() correctly substitutes all
        provided variable values.
        """
        template = PromptTemplate("Hello {name}, you are {age} years old.")
        result = template.render(name="Alice", age=30)

        assert result == "Hello Alice, you are 30 years old."

    def test_render_raises_missing_variable_error(self) -> None:
        """Test that missing variables raise MissingVariableError.

        Verifies that attempting to render without providing
        all required variables raises MissingVariableError.
        """
        template = PromptTemplate("Hello {name} from {city}!")

        with pytest.raises(MissingVariableError) as exc_info:
            template.render(name="Alice")

        assert "city" in exc_info.value.missing_vars
        assert exc_info.value.template == "Hello {name} from {city}!"

    def test_render_strict_mode_raises_invalid_variable_error(self) -> None:
        """Test that strict mode raises error for extra variables.

        Verifies that in strict mode, providing variables that
        don't exist in the template raises InvalidVariableError.
        """
        template = PromptTemplate("Hello {name}!", strict=True)

        with pytest.raises(InvalidVariableError) as exc_info:
            template.render(name="Alice", extra_var="unused")

        assert "extra_var" in exc_info.value.invalid_vars

    def test_render_non_strict_mode_ignores_extra_variables(self) -> None:
        """Test that non-strict mode ignores extra variables.

        Verifies that extra variables are silently ignored
        when strict mode is disabled (default).
        """
        template = PromptTemplate("Hello {name}!", strict=False)
        result = template.render(name="Alice", extra_var="ignored")

        assert result == "Hello Alice!"

    def test_partial_render_with_some_variables(self) -> None:
        """Test partial rendering with subset of variables.

        Verifies that partial() substitutes provided variables
        while leaving others intact.
        """
        template = PromptTemplate("Hello {name} from {city}!")
        result = template.partial(name="Alice")

        assert result == "Hello Alice from {city}!"

    def test_partial_render_with_all_variables(self) -> None:
        """Test partial rendering with all variables.

        Verifies that partial() works correctly when all
        variables are provided.
        """
        template = PromptTemplate("Hello {name} from {city}!")
        result = template.partial(name="Alice", city="Paris")

        assert result == "Hello Alice from Paris!"

    def test_safe_render_with_missing_variables(self) -> None:
        """Test safe_render uses default for missing variables.

        Verifies that safe_render() substitutes default value
        for missing variables instead of raising an error.
        """
        template = PromptTemplate("Hello {name} from {city}!")
        result = template.safe_render(default="UNKNOWN", name="Alice")

        assert result == "Hello Alice from UNKNOWN!"

    def test_safe_render_with_empty_default(self) -> None:
        """Test safe_render with empty string default.

        Verifies that safe_render() uses empty string as default
        when no default is specified.
        """
        template = PromptTemplate("Hello {name}!")
        result = template.safe_render()

        assert result == "Hello !"

    def test_validate_returns_true_when_all_provided(self) -> None:
        """Test validate returns True when all variables provided.

        Verifies that validate() correctly identifies when all
        required variables are present.
        """
        template = PromptTemplate("Hello {name} from {city}!")
        is_valid, missing = template.validate(name="Alice", city="Paris")

        assert is_valid is True
        assert missing == set()

    def test_validate_returns_false_with_missing_variables(self) -> None:
        """Test validate returns False when variables missing.

        Verifies that validate() correctly identifies missing
        variables and returns them in the set.
        """
        template = PromptTemplate("Hello {name} from {city}!")
        is_valid, missing = template.validate(name="Alice")

        assert is_valid is False
        assert missing == {"city"}

    def test_str_returns_template(self) -> None:
        """Test __str__ returns the template string.

        Verifies that converting template to string returns
        the raw template.
        """
        template = PromptTemplate("Hello {name}!")
        assert str(template) == "Hello {name}!"

    def test_repr_format(self) -> None:
        """Test __repr__ returns proper format.

        Verifies that repr includes PromptTemplate class name
        and template preview.
        """
        template = PromptTemplate("Hello {name}!")
        assert "PromptTemplate" in repr(template)
        assert "Hello {name}!" in repr(template)

    def test_repr_truncates_long_templates(self) -> None:
        """Test __repr__ truncates long templates.

        Verifies that very long templates are truncated
        in the repr output.
        """
        long_template = "x" * 100
        template = PromptTemplate(long_template)
        repr_str = repr(template)

        assert "..." in repr_str
        assert len(repr_str) < len(long_template) + 50

    def test_equality_same_template(self) -> None:
        """Test equality for templates with same string.

        Verifies that two templates with identical strings
        are considered equal.
        """
        template1 = PromptTemplate("Hello {name}!")
        template2 = PromptTemplate("Hello {name}!")

        assert template1 == template2

    def test_equality_different_template(self) -> None:
        """Test inequality for templates with different strings.

        Verifies that templates with different strings
        are not considered equal.
        """
        template1 = PromptTemplate("Hello {name}!")
        template2 = PromptTemplate("Hi {name}!")

        assert template1 != template2

    def test_hash_consistent(self) -> None:
        """Test that hash is consistent for same template.

        Verifies that templates with same string have
        the same hash value.
        """
        template1 = PromptTemplate("Hello {name}!")
        template2 = PromptTemplate("Hello {name}!")

        assert hash(template1) == hash(template2)


# ============================================================================
# SystemUserPromptTemplate Tests
# ============================================================================


class TestSystemUserPromptTemplate:
    """Test suite for SystemUserPromptTemplate class."""

    def test_create_system_user_template(self) -> None:
        """Test creating a system/user template.

        Verifies that SystemUserPromptTemplate can be created
        with both system and user templates.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are a helpful assistant.",
            user_template="User says: {input}",
        )

        assert prompt.system_template == "You are a helpful assistant."
        assert prompt.user_template == "User says: {input}"

    def test_create_with_name(self) -> None:
        """Test creating template with optional name.

        Verifies that the name attribute is properly set.
        """
        prompt = SystemUserPromptTemplate(
            system_template="System",
            user_template="User",
            name="test_prompt",
        )

        assert prompt.name == "test_prompt"

    def test_raises_error_for_empty_system_template(self) -> None:
        """Test that empty system template raises ValueError.

        Verifies that an empty system template string
        raises a ValueError.
        """
        with pytest.raises(ValueError):
            SystemUserPromptTemplate(
                system_template="",
                user_template="User",
            )

    def test_raises_error_for_empty_user_template(self) -> None:
        """Test that empty user template raises ValueError.

        Verifies that an empty user template string
        raises a ValueError.
        """
        with pytest.raises(ValueError):
            SystemUserPromptTemplate(
                system_template="System",
                user_template="",
            )

    def test_variables_from_both_templates(self) -> None:
        """Test variables property includes both templates.

        Verifies that variables property returns combined
        variables from both system and user templates.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User {name} says: {input}",
        )

        assert prompt.variables == {"role", "name", "input"}

    def test_system_variables_property(self) -> None:
        """Test system_variables returns only system template vars.

        Verifies that system_variables property returns only
        variables from the system template.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User {name} says: {input}",
        )

        assert prompt.system_variables == {"role"}

    def test_user_variables_property(self) -> None:
        """Test user_variables returns only user template vars.

        Verifies that user_variables property returns only
        variables from the user template.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User {name} says: {input}",
        )

        assert prompt.user_variables == {"name", "input"}

    def test_render_returns_both_messages(self) -> None:
        """Test render returns tuple of system and user messages.

        Verifies that render() returns correctly substituted
        system and user messages as a tuple.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User says: {input}",
        )

        system_msg, user_msg = prompt.render(role="assistant", input="Hello!")

        assert system_msg == "You are assistant."
        assert user_msg == "User says: Hello!"

    def test_render_raises_missing_variable_error(self) -> None:
        """Test render raises error for missing variables.

        Verifies that render() raises MissingVariableError
        when required variables are not provided.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User says: {input}",
        )

        with pytest.raises(MissingVariableError) as exc_info:
            prompt.render(role="assistant")

        assert "input" in exc_info.value.missing_vars

    def test_render_system_only(self) -> None:
        """Test render_system renders only system template.

        Verifies that render_system() only renders the
        system template portion.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User says: {input}",
        )

        result = prompt.render_system(role="assistant")
        assert result == "You are assistant."

    def test_render_user_only(self) -> None:
        """Test render_user renders only user template.

        Verifies that render_user() only renders the
        user template portion.
        """
        prompt = SystemUserPromptTemplate(
            system_template="You are {role}.",
            user_template="User says: {input}",
        )

        result = prompt.render_user(input="Hello!")
        assert result == "User says: Hello!"

    def test_repr_format(self) -> None:
        """Test __repr__ includes class name and variables.

        Verifies that repr includes SystemUserPromptTemplate
        and the list of variables.
        """
        prompt = SystemUserPromptTemplate(
            system_template="System {a}",
            user_template="User {b}",
            name="test",
        )
        repr_str = repr(prompt)

        assert "SystemUserPromptTemplate" in repr_str
        assert "test" in repr_str


# ============================================================================
# Exception Tests
# ============================================================================


class TestPromptTemplateExceptions:
    """Test suite for prompt template exceptions."""

    def test_prompt_template_error_attributes(self) -> None:
        """Test PromptTemplateError has correct attributes.

        Verifies that the base exception stores message
        and template correctly.
        """
        error = PromptTemplateError("Test error", template="Hello {name}!")

        assert error.message == "Test error"
        assert error.template == "Hello {name}!"
        assert str(error) == "Test error"

    def test_missing_variable_error_attributes(self) -> None:
        """Test MissingVariableError has correct attributes.

        Verifies that the exception stores missing variables
        and generates appropriate message.
        """
        error = MissingVariableError({"name", "city"}, template="Template")

        assert error.missing_vars == {"name", "city"}
        assert error.template == "Template"
        assert "name" in str(error) or "city" in str(error)

    def test_invalid_variable_error_attributes(self) -> None:
        """Test InvalidVariableError has correct attributes.

        Verifies that the exception stores invalid variables
        and generates appropriate message.
        """
        error = InvalidVariableError({"extra", "unused"}, template="Template")

        assert error.invalid_vars == {"extra", "unused"}
        assert error.template == "Template"
        assert "extra" in str(error) or "unused" in str(error)


# ============================================================================
# ActivityRecommendationPrompt Tests
# ============================================================================


class TestActivityRecommendationPrompt:
    """Test suite for ActivityRecommendationPrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that ActivityRecommendationPrompt can be created
        with default system and user templates.
        """
        prompt = ActivityRecommendationPrompt()

        assert prompt.name == "activity_recommendation"
        assert "LAYA" in prompt.system_prompt
        assert "early childhood" in prompt.system_prompt.lower()

    def test_create_with_custom_templates(self) -> None:
        """Test creating prompt with custom templates.

        Verifies that custom templates can be provided.
        """
        prompt = ActivityRecommendationPrompt(
            system_template="Custom system",
            user_template="Custom user {age_group}",
            name="custom_activity",
        )

        assert prompt.system_template == "Custom system"
        assert prompt.name == "custom_activity"

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters only.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = ActivityRecommendationPrompt()
        system_msg, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            duration=30,
            setting="indoor",
            num_activities=3,
        )

        assert "LAYA" in system_msg
        assert "3-4 years" in user_msg
        assert "12" in user_msg
        assert "30" in user_msg
        assert "indoor" in user_msg
        assert "3" in user_msg

    def test_render_with_optional_theme(self) -> None:
        """Test rendering with optional theme parameter.

        Verifies that theme is properly included when provided.
        """
        prompt = ActivityRecommendationPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            duration=30,
            setting="indoor",
            theme="nature",
        )

        assert "nature" in user_msg

    def test_render_with_optional_materials(self) -> None:
        """Test rendering with optional materials parameter.

        Verifies that materials list is properly included.
        """
        prompt = ActivityRecommendationPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            duration=30,
            setting="indoor",
            available_materials=["blocks", "crayons", "paper"],
        )

        assert "blocks" in user_msg
        assert "crayons" in user_msg

    def test_render_with_learning_objectives(self) -> None:
        """Test rendering with optional learning objectives.

        Verifies that learning objectives are properly included.
        """
        prompt = ActivityRecommendationPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            duration=30,
            setting="indoor",
            learning_objectives=["motor skills", "social interaction"],
        )

        assert "motor skills" in user_msg

    def test_render_for_api_format(self) -> None:
        """Test render_for_api returns correct message format.

        Verifies that render_for_api() returns a list of
        message dictionaries with role and content keys.
        """
        prompt = ActivityRecommendationPrompt()
        messages = prompt.render_for_api(
            age_group="4-5 years",
            num_children=15,
            duration=45,
            setting="outdoor",
        )

        assert isinstance(messages, list)
        assert len(messages) == 2
        assert messages[0]["role"] == "system"
        assert messages[1]["role"] == "user"
        assert "LAYA" in messages[0]["content"]
        assert "4-5 years" in messages[1]["content"]


# ============================================================================
# ActivityAdaptationPrompt Tests
# ============================================================================


class TestActivityAdaptationPrompt:
    """Test suite for ActivityAdaptationPrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that ActivityAdaptationPrompt has correct
        default system prompt.
        """
        prompt = ActivityAdaptationPrompt()

        assert prompt.name == "activity_adaptation"
        assert "LAYA" in prompt.system_prompt
        assert "inclusive" in prompt.system_prompt.lower()

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = ActivityAdaptationPrompt()
        system_msg, user_msg = prompt.render(
            activity_name="Finger painting",
            activity_description="Children create artwork using finger paints",
            adaptation_need="visual impairment",
            age_group="3-4 years",
        )

        assert "LAYA" in system_msg
        assert "Finger painting" in user_msg
        assert "visual impairment" in user_msg
        assert "3-4 years" in user_msg

    def test_render_with_additional_context(self) -> None:
        """Test rendering with additional context.

        Verifies that additional context is properly included.
        """
        prompt = ActivityAdaptationPrompt()
        _, user_msg = prompt.render(
            activity_name="Block building",
            activity_description="Stack blocks to create towers",
            adaptation_need="motor challenges",
            age_group="2-3 years",
            additional_context="Child uses a wheelchair",
        )

        assert "wheelchair" in user_msg


# ============================================================================
# DailyPlanningPrompt Tests
# ============================================================================


class TestDailyPlanningPrompt:
    """Test suite for DailyPlanningPrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that DailyPlanningPrompt has correct
        default system prompt.
        """
        prompt = DailyPlanningPrompt()

        assert prompt.name == "daily_planning"
        assert "LAYA" in prompt.system_prompt
        assert "daily" in prompt.system_prompt.lower()

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = DailyPlanningPrompt()
        system_msg, user_msg = prompt.render(
            age_group="4-5 years",
            num_children=16,
            day_duration=8,
        )

        assert "LAYA" in system_msg
        assert "4-5 years" in user_msg
        assert "16" in user_msg
        assert "8" in user_msg

    def test_render_with_special_events(self) -> None:
        """Test rendering with special events.

        Verifies that special events are properly included.
        """
        prompt = DailyPlanningPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            day_duration=6,
            special_events="Field trip to fire station",
        )

        assert "fire station" in user_msg

    def test_render_with_focus_areas(self) -> None:
        """Test rendering with focus areas.

        Verifies that focus areas list is properly included.
        """
        prompt = DailyPlanningPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            day_duration=6,
            focus_areas=["motor skills", "language development"],
        )

        assert "motor skills" in user_msg

    def test_render_with_constraints(self) -> None:
        """Test rendering with constraints.

        Verifies that constraints are properly included.
        """
        prompt = DailyPlanningPrompt()
        _, user_msg = prompt.render(
            age_group="3-4 years",
            num_children=12,
            day_duration=6,
            constraints="Limited outdoor space due to construction",
        )

        assert "construction" in user_msg


# ============================================================================
# DailyReportPrompt Tests
# ============================================================================


class TestDailyReportPrompt:
    """Test suite for DailyReportPrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that DailyReportPrompt has correct
        default system prompt.
        """
        prompt = DailyReportPrompt()

        assert prompt.name == "daily_report"
        assert "LAYA" in prompt.system_prompt
        assert "report" in prompt.system_prompt.lower()

    def test_create_with_custom_templates(self) -> None:
        """Test creating prompt with custom templates.

        Verifies that custom templates can be provided.
        """
        prompt = DailyReportPrompt(
            system_template="Custom report system",
            user_template="Report for {report_date}",
            name="custom_report",
        )

        assert prompt.system_template == "Custom report system"
        assert prompt.name == "custom_report"

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = DailyReportPrompt()
        system_msg, user_msg = prompt.render(
            report_date="2024-01-15",
            classroom_name="Butterflies Room",
            age_group="3-4 years",
            num_children=12,
        )

        assert "LAYA" in system_msg
        assert "2024-01-15" in user_msg
        assert "Butterflies Room" in user_msg
        assert "3-4 years" in user_msg
        assert "12" in user_msg

    def test_render_with_activities(self) -> None:
        """Test rendering with activities list.

        Verifies that activities are properly formatted.
        """
        prompt = DailyReportPrompt()
        _, user_msg = prompt.render(
            report_date="2024-01-15",
            classroom_name="Sunshine Room",
            age_group="2-3 years",
            num_children=8,
            activities=["Morning circle time", "Art project", "Outdoor play"],
        )

        assert "Morning circle time" in user_msg
        assert "Art project" in user_msg
        assert "Outdoor play" in user_msg

    def test_render_with_observations(self) -> None:
        """Test rendering with observations list.

        Verifies that observations are properly formatted.
        """
        prompt = DailyReportPrompt()
        _, user_msg = prompt.render(
            report_date="2024-01-15",
            classroom_name="Rainbow Room",
            age_group="4-5 years",
            num_children=15,
            observations=["Great group participation", "New words learned"],
        )

        assert "Great group participation" in user_msg
        assert "New words learned" in user_msg

    def test_render_with_meals_info(self) -> None:
        """Test rendering with meals information.

        Verifies that meals info is properly included.
        """
        prompt = DailyReportPrompt()
        _, user_msg = prompt.render(
            report_date="2024-01-15",
            classroom_name="Stars Room",
            age_group="3-4 years",
            num_children=10,
            meals_info="Ate well at lunch, had fruit for snack",
        )

        assert "fruit" in user_msg

    def test_render_with_additional_notes(self) -> None:
        """Test rendering with additional notes.

        Verifies that additional notes are properly included.
        """
        prompt = DailyReportPrompt()
        _, user_msg = prompt.render(
            report_date="2024-01-15",
            classroom_name="Moon Room",
            age_group="2-3 years",
            num_children=8,
            additional_notes="Please bring extra clothes tomorrow",
        )

        assert "extra clothes" in user_msg

    def test_render_for_api_format(self) -> None:
        """Test render_for_api returns correct message format.

        Verifies that render_for_api() returns a list of
        message dictionaries.
        """
        prompt = DailyReportPrompt()
        messages = prompt.render_for_api(
            report_date="2024-01-15",
            classroom_name="Test Room",
            age_group="3-4 years",
            num_children=12,
        )

        assert isinstance(messages, list)
        assert len(messages) == 2
        assert messages[0]["role"] == "system"
        assert messages[1]["role"] == "user"


# ============================================================================
# CoachingGuidancePrompt Tests
# ============================================================================


class TestCoachingGuidancePrompt:
    """Test suite for CoachingGuidancePrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that CoachingGuidancePrompt has correct
        default system prompt.
        """
        prompt = CoachingGuidancePrompt()

        assert prompt.name == "coaching_guidance"
        assert "LAYA" in prompt.system_prompt
        assert "coaching" in prompt.system_prompt.lower()

    def test_create_with_custom_templates(self) -> None:
        """Test creating prompt with custom templates.

        Verifies that custom templates can be provided.
        """
        prompt = CoachingGuidancePrompt(
            system_template="Custom coaching system",
            user_template="Situation: {situation}",
            name="custom_coaching",
        )

        assert prompt.system_template == "Custom coaching system"
        assert prompt.name == "custom_coaching"

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = CoachingGuidancePrompt()
        system_msg, user_msg = prompt.render(
            educator_context="New educator, 6 months experience",
            age_group="2-3 years",
            situation="Managing transitions between activities",
        )

        assert "LAYA" in system_msg
        assert "New educator" in user_msg
        assert "2-3 years" in user_msg
        assert "transitions" in user_msg

    def test_render_with_goal(self) -> None:
        """Test rendering with optional goal.

        Verifies that goal is properly included when provided.
        """
        prompt = CoachingGuidancePrompt()
        _, user_msg = prompt.render(
            educator_context="Experienced teacher",
            age_group="4-5 years",
            situation="Supporting separation anxiety",
            goal="Help child feel secure during drop-off",
        )

        assert "secure" in user_msg

    def test_render_with_approaches_tried(self) -> None:
        """Test rendering with approaches already tried.

        Verifies that previous approaches are properly included.
        """
        prompt = CoachingGuidancePrompt()
        _, user_msg = prompt.render(
            educator_context="Lead teacher",
            age_group="3-4 years",
            situation="Encouraging cooperative play",
            approaches_tried=["Modeling sharing", "Positive reinforcement"],
        )

        assert "Modeling sharing" in user_msg
        assert "Positive reinforcement" in user_msg

    def test_render_with_constraints(self) -> None:
        """Test rendering with constraints.

        Verifies that constraints are properly included.
        """
        prompt = CoachingGuidancePrompt()
        _, user_msg = prompt.render(
            educator_context="Assistant teacher",
            age_group="2-3 years",
            situation="Managing biting behavior",
            constraints="High adult-child ratio",
        )

        assert "ratio" in user_msg

    def test_render_for_api_format(self) -> None:
        """Test render_for_api returns correct message format.

        Verifies that render_for_api() returns a list of
        message dictionaries.
        """
        prompt = CoachingGuidancePrompt()
        messages = prompt.render_for_api(
            educator_context="New educator",
            age_group="3-4 years",
            situation="Test situation",
        )

        assert isinstance(messages, list)
        assert len(messages) == 2
        assert messages[0]["role"] == "system"
        assert messages[1]["role"] == "user"


# ============================================================================
# BehaviorGuidancePrompt Tests
# ============================================================================


class TestBehaviorGuidancePrompt:
    """Test suite for BehaviorGuidancePrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that BehaviorGuidancePrompt has correct
        default system prompt.
        """
        prompt = BehaviorGuidancePrompt()

        assert prompt.name == "behavior_guidance"
        assert "LAYA" in prompt.system_prompt
        assert "behavior" in prompt.system_prompt.lower()

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = BehaviorGuidancePrompt()
        system_msg, user_msg = prompt.render(
            child_age="3 years",
            behavior_description="Difficulty sharing toys with peers",
            context="During free play time",
            frequency="Daily occurrence",
        )

        assert "LAYA" in system_msg
        assert "3 years" in user_msg
        assert "sharing toys" in user_msg
        assert "free play" in user_msg
        assert "Daily" in user_msg

    def test_render_with_known_triggers(self) -> None:
        """Test rendering with known triggers.

        Verifies that known triggers are properly included.
        """
        prompt = BehaviorGuidancePrompt()
        _, user_msg = prompt.render(
            child_age="4 years",
            behavior_description="Hitting when frustrated",
            context="During transitions",
            frequency="2-3 times daily",
            known_triggers=["Change of activity", "Waiting for turn"],
        )

        assert "Change of activity" in user_msg

    def test_render_with_strategies_tried(self) -> None:
        """Test rendering with strategies already tried.

        Verifies that previous strategies are properly included.
        """
        prompt = BehaviorGuidancePrompt()
        _, user_msg = prompt.render(
            child_age="2 years",
            behavior_description="Biting other children",
            context="During group activities",
            frequency="Weekly",
            strategies_tried=["Redirection", "Time-out"],
        )

        assert "Redirection" in user_msg

    def test_render_with_additional_info(self) -> None:
        """Test rendering with additional information.

        Verifies that additional info is properly included.
        """
        prompt = BehaviorGuidancePrompt()
        _, user_msg = prompt.render(
            child_age="3 years",
            behavior_description="Throwing objects",
            context="Morning arrival",
            frequency="Most mornings",
            additional_info="Parents report similar behavior at home",
        )

        assert "home" in user_msg


# ============================================================================
# ParentCommunicationPrompt Tests
# ============================================================================


class TestParentCommunicationPrompt:
    """Test suite for ParentCommunicationPrompt class."""

    def test_create_with_defaults(self) -> None:
        """Test creating prompt with default templates.

        Verifies that ParentCommunicationPrompt has correct
        default system prompt.
        """
        prompt = ParentCommunicationPrompt()

        assert prompt.name == "parent_communication"
        assert "LAYA" in prompt.system_prompt
        assert "parent" in prompt.system_prompt.lower()

    def test_render_with_required_params(self) -> None:
        """Test rendering with required parameters.

        Verifies that render() works with minimal required
        parameters.
        """
        prompt = ParentCommunicationPrompt()
        system_msg, user_msg = prompt.render(
            communication_type="Developmental milestone update",
            child_age="18 months",
            topic="First words and language development",
            tone="Celebratory and informative",
        )

        assert "LAYA" in system_msg
        assert "milestone" in user_msg
        assert "18 months" in user_msg
        assert "language" in user_msg
        assert "Celebratory" in user_msg

    def test_render_with_key_points(self) -> None:
        """Test rendering with key points.

        Verifies that key points are properly formatted.
        """
        prompt = ParentCommunicationPrompt()
        _, user_msg = prompt.render(
            communication_type="Progress update",
            child_age="3 years",
            topic="Social skills improvement",
            tone="Encouraging",
            key_points=["Making friends", "Sharing toys", "Using words"],
        )

        assert "Making friends" in user_msg
        assert "Sharing toys" in user_msg

    def test_render_with_context(self) -> None:
        """Test rendering with additional context.

        Verifies that context is properly included.
        """
        prompt = ParentCommunicationPrompt()
        _, user_msg = prompt.render(
            communication_type="Concern discussion",
            child_age="4 years",
            topic="Difficulty with fine motor tasks",
            tone="Supportive and collaborative",
            context="Parent has mentioned similar concerns at home",
        )

        assert "concerns at home" in user_msg


# ============================================================================
# Integration Tests
# ============================================================================


class TestPromptTemplateIntegration:
    """Integration tests for prompt template system."""

    def test_all_prompts_have_laya_in_system(self) -> None:
        """Test that all prompts mention LAYA in system message.

        Verifies that all specialized prompt templates include
        the LAYA assistant identity in their system prompts.
        """
        prompts = [
            ActivityRecommendationPrompt(),
            ActivityAdaptationPrompt(),
            DailyPlanningPrompt(),
            DailyReportPrompt(),
            CoachingGuidancePrompt(),
            BehaviorGuidancePrompt(),
            ParentCommunicationPrompt(),
        ]

        for prompt in prompts:
            assert "LAYA" in prompt.system_prompt, (
                f"{prompt.name} should mention LAYA in system prompt"
            )

    def test_all_prompts_have_unique_names(self) -> None:
        """Test that all prompts have unique default names.

        Verifies that each specialized prompt template has
        a distinct name for identification.
        """
        prompts = [
            ActivityRecommendationPrompt(),
            ActivityAdaptationPrompt(),
            DailyPlanningPrompt(),
            DailyReportPrompt(),
            CoachingGuidancePrompt(),
            BehaviorGuidancePrompt(),
            ParentCommunicationPrompt(),
        ]

        names = [p.name for p in prompts]
        assert len(names) == len(set(names)), "All prompt names should be unique"

    def test_quebec_context_in_relevant_prompts(self) -> None:
        """Test that Quebec context is included where relevant.

        Verifies that prompts for childcare-related tasks include
        Quebec educational program references.
        """
        quebec_prompts = [
            ActivityRecommendationPrompt(),
            DailyPlanningPrompt(),
            CoachingGuidancePrompt(),
        ]

        for prompt in quebec_prompts:
            assert "quebec" in prompt.system_prompt.lower(), (
                f"{prompt.name} should reference Quebec"
            )

    def test_child_development_focus(self) -> None:
        """Test that prompts focus on child development.

        Verifies that all prompts include child development
        or early childhood education concepts.
        """
        prompts = [
            ActivityRecommendationPrompt(),
            ActivityAdaptationPrompt(),
            DailyPlanningPrompt(),
            CoachingGuidancePrompt(),
            BehaviorGuidancePrompt(),
        ]

        for prompt in prompts:
            system_lower = prompt.system_prompt.lower()
            has_dev_focus = (
                "development" in system_lower
                or "child" in system_lower
                or "early childhood" in system_lower
            )
            assert has_dev_focus, (
                f"{prompt.name} should focus on child development"
            )
