"""Base prompt template classes for LAYA AI Service.

Provides a flexible template system for constructing LLM prompts
with variable substitution, validation, and formatting.
"""

import re
from string import Formatter
from typing import Any, Optional, Set


class PromptTemplateError(Exception):
    """Base exception for prompt template errors.

    Attributes:
        message: Human-readable error description
        template: The template string that caused the error
    """

    def __init__(self, message: str, template: Optional[str] = None) -> None:
        """Initialize the exception.

        Args:
            message: Human-readable error description
            template: Optional template string for context
        """
        self.message = message
        self.template = template
        super().__init__(message)


class MissingVariableError(PromptTemplateError):
    """Exception raised when required template variables are missing.

    Attributes:
        missing_vars: Set of variable names that were not provided
    """

    def __init__(
        self,
        missing_vars: Set[str],
        template: Optional[str] = None,
    ) -> None:
        """Initialize the exception.

        Args:
            missing_vars: Set of variable names that were not provided
            template: Optional template string for context
        """
        self.missing_vars = missing_vars
        message = f"Missing required variables: {', '.join(sorted(missing_vars))}"
        super().__init__(message, template)


class InvalidVariableError(PromptTemplateError):
    """Exception raised when invalid variable names are provided.

    Attributes:
        invalid_vars: Set of variable names that don't exist in template
    """

    def __init__(
        self,
        invalid_vars: Set[str],
        template: Optional[str] = None,
    ) -> None:
        """Initialize the exception.

        Args:
            invalid_vars: Set of variable names that don't exist in template
            template: Optional template string for context
        """
        self.invalid_vars = invalid_vars
        message = f"Invalid variable names: {', '.join(sorted(invalid_vars))}"
        super().__init__(message, template)


class PromptTemplate:
    """A prompt template with variable substitution support.

    Provides a flexible way to create prompt templates with named
    variables that can be substituted at render time. Supports
    Python string formatting syntax using {variable_name}.

    Attributes:
        template: The raw template string
        variables: Set of variable names found in the template

    Example:
        >>> template = PromptTemplate("Hello {name}, welcome to {place}!")
        >>> template.render(name="Alice", place="LAYA")
        'Hello Alice, welcome to LAYA!'

        >>> # Get required variables
        >>> template.variables
        {'name', 'place'}

        >>> # Partial rendering
        >>> template.partial(name="Bob")
        'Hello Bob, welcome to {place}!'
    """

    # Pattern to match format string variables like {name} or {name!r:10}
    _VARIABLE_PATTERN = re.compile(r"\{([a-zA-Z_][a-zA-Z0-9_]*)(?:![rsa])?(?::[^}]*)?\}")

    def __init__(
        self,
        template: str,
        *,
        strict: bool = False,
    ) -> None:
        """Initialize the prompt template.

        Args:
            template: The template string with {variable} placeholders
            strict: If True, raise error for invalid variable names in render

        Raises:
            ValueError: If template is empty or None
        """
        if not template:
            raise ValueError("Template cannot be empty")

        self.template = template
        self.strict = strict
        self._variables: Optional[Set[str]] = None

    @property
    def variables(self) -> Set[str]:
        """Get the set of variable names in this template.

        Returns:
            Set of variable names found in the template string

        Example:
            >>> PromptTemplate("Hello {name}!").variables
            {'name'}
        """
        if self._variables is None:
            self._variables = self._extract_variables(self.template)
        return self._variables.copy()

    def _extract_variables(self, template: str) -> Set[str]:
        """Extract variable names from a template string.

        Args:
            template: The template string to parse

        Returns:
            Set of variable names found in the template
        """
        variables: Set[str] = set()

        # Use the string Formatter to properly parse format strings
        formatter = Formatter()
        for _, field_name, _, _ in formatter.parse(template):
            if field_name is not None and field_name:
                # Handle complex field names like "person.name" -> just get "person"
                base_name = field_name.split(".")[0].split("[")[0]
                if base_name:
                    variables.add(base_name)

        return variables

    def render(self, **kwargs: Any) -> str:
        """Render the template with provided variable values.

        Substitutes all template variables with the provided values.
        All required variables must be provided.

        Args:
            **kwargs: Variable name-value pairs for substitution

        Returns:
            The rendered template string with all variables replaced

        Raises:
            MissingVariableError: If any required variables are missing
            InvalidVariableError: If strict mode and invalid vars provided

        Example:
            >>> t = PromptTemplate("Hello {name}!")
            >>> t.render(name="World")
            'Hello World!'
        """
        # Check for missing variables
        provided = set(kwargs.keys())
        required = self.variables
        missing = required - provided

        if missing:
            raise MissingVariableError(missing, self.template)

        # In strict mode, check for extra variables
        if self.strict:
            extra = provided - required
            if extra:
                raise InvalidVariableError(extra, self.template)

        # Render the template
        return self.template.format(**kwargs)

    def partial(self, **kwargs: Any) -> str:
        """Partially render the template with some variable values.

        Substitutes only the provided variables, leaving others
        intact for later substitution.

        Args:
            **kwargs: Variable name-value pairs for partial substitution

        Returns:
            The partially rendered template string

        Example:
            >>> t = PromptTemplate("Hello {name} from {place}!")
            >>> t.partial(name="World")
            'Hello World from {place}!'
        """
        result = self.template
        for key, value in kwargs.items():
            # Simple replacement for basic {var} patterns
            # This handles the common case without complex format specs
            pattern = re.compile(r"\{" + re.escape(key) + r"(?:![rsa])?(?::[^}]*)?\}")
            result = pattern.sub(str(value), result)
        return result

    def safe_render(self, default: str = "", **kwargs: Any) -> str:
        """Render the template, using default for missing variables.

        A safe version of render that never raises MissingVariableError.
        Missing variables are replaced with the default value.

        Args:
            default: Default value for missing variables
            **kwargs: Variable name-value pairs for substitution

        Returns:
            The rendered template string

        Example:
            >>> t = PromptTemplate("Hello {name}!")
            >>> t.safe_render()
            'Hello !'
            >>> t.safe_render(default="World")
            'Hello World!'
        """
        # Fill in missing variables with default
        required = self.variables
        provided = set(kwargs.keys())
        missing = required - provided

        all_vars = dict(kwargs)
        for var in missing:
            all_vars[var] = default

        return self.template.format(**all_vars)

    def validate(self, **kwargs: Any) -> tuple[bool, Set[str]]:
        """Validate that all required variables are provided.

        Checks if the provided kwargs contain all required variables
        without actually rendering the template.

        Args:
            **kwargs: Variable name-value pairs to validate

        Returns:
            Tuple of (is_valid, missing_variables)

        Example:
            >>> t = PromptTemplate("Hello {name} from {place}!")
            >>> t.validate(name="World")
            (False, {'place'})
            >>> t.validate(name="World", place="Earth")
            (True, set())
        """
        required = self.variables
        provided = set(kwargs.keys())
        missing = required - provided
        return (len(missing) == 0, missing)

    def __str__(self) -> str:
        """Return the raw template string.

        Returns:
            The template string
        """
        return self.template

    def __repr__(self) -> str:
        """Return a string representation of the template.

        Returns:
            String representation including template preview
        """
        preview = self.template[:50] + "..." if len(self.template) > 50 else self.template
        return f"PromptTemplate({preview!r})"

    def __eq__(self, other: object) -> bool:
        """Check equality with another PromptTemplate.

        Args:
            other: Object to compare with

        Returns:
            True if templates are equal
        """
        if not isinstance(other, PromptTemplate):
            return NotImplemented
        return self.template == other.template

    def __hash__(self) -> int:
        """Return hash of the template.

        Returns:
            Hash value based on template string
        """
        return hash(self.template)


class SystemUserPromptTemplate:
    """A prompt template with separate system and user message components.

    Provides a structured way to define prompts with distinct system
    and user message templates, commonly used in LLM conversations.

    Attributes:
        system_template: Template for the system message
        user_template: Template for the user message
        name: Optional name identifier for this prompt

    Example:
        >>> prompt = SystemUserPromptTemplate(
        ...     system_template="You are a helpful assistant named {assistant_name}.",
        ...     user_template="User says: {user_input}",
        ...     name="basic_assistant"
        ... )
        >>> system, user = prompt.render(
        ...     assistant_name="LAYA",
        ...     user_input="Hello!"
        ... )
        >>> system
        'You are a helpful assistant named LAYA.'
        >>> user
        'User says: Hello!'
    """

    def __init__(
        self,
        system_template: str,
        user_template: str,
        *,
        name: Optional[str] = None,
    ) -> None:
        """Initialize the system/user prompt template.

        Args:
            system_template: Template string for system message
            user_template: Template string for user message
            name: Optional identifier for this prompt template

        Raises:
            ValueError: If either template is empty
        """
        self.system = PromptTemplate(system_template)
        self.user = PromptTemplate(user_template)
        self.name = name

    @property
    def system_template(self) -> str:
        """Get the raw system template string."""
        return self.system.template

    @property
    def user_template(self) -> str:
        """Get the raw user template string."""
        return self.user.template

    @property
    def variables(self) -> Set[str]:
        """Get all variables from both templates.

        Returns:
            Combined set of variables from system and user templates
        """
        return self.system.variables | self.user.variables

    @property
    def system_variables(self) -> Set[str]:
        """Get variables specific to system template.

        Returns:
            Set of variables in system template
        """
        return self.system.variables

    @property
    def user_variables(self) -> Set[str]:
        """Get variables specific to user template.

        Returns:
            Set of variables in user template
        """
        return self.user.variables

    def render(self, **kwargs: Any) -> tuple[str, str]:
        """Render both system and user templates.

        Args:
            **kwargs: Variable values for both templates

        Returns:
            Tuple of (system_message, user_message)

        Raises:
            MissingVariableError: If any required variables are missing
        """
        # Collect variables from both templates
        all_vars = self.variables
        provided = set(kwargs.keys())
        missing = all_vars - provided

        if missing:
            raise MissingVariableError(missing)

        # Filter kwargs for each template to avoid extra variable warnings
        system_kwargs = {k: v for k, v in kwargs.items() if k in self.system.variables}
        user_kwargs = {k: v for k, v in kwargs.items() if k in self.user.variables}

        system_msg = self.system.render(**system_kwargs)
        user_msg = self.user.render(**user_kwargs)

        return system_msg, user_msg

    def render_system(self, **kwargs: Any) -> str:
        """Render only the system template.

        Args:
            **kwargs: Variable values for system template

        Returns:
            Rendered system message
        """
        return self.system.render(**kwargs)

    def render_user(self, **kwargs: Any) -> str:
        """Render only the user template.

        Args:
            **kwargs: Variable values for user template

        Returns:
            Rendered user message
        """
        return self.user.render(**kwargs)

    def __repr__(self) -> str:
        """Return string representation of the template."""
        name_part = f"name={self.name!r}, " if self.name else ""
        return f"SystemUserPromptTemplate({name_part}variables={sorted(self.variables)})"
