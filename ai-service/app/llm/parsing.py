"""Response parsing utilities for LLM output extraction in LAYA AI Service.

Provides tools for extracting structured data from LLM responses, including
JSON extraction from markdown code blocks, list parsing, and validation
against Pydantic models. Handles common LLM output formats and edge cases.
"""

import json
import re
from enum import Enum
from typing import Any, Optional, Type, TypeVar

from pydantic import BaseModel, ConfigDict, Field, ValidationError


class ParseError(Exception):
    """Exception raised when parsing fails.

    Attributes:
        message: Human-readable error message
        raw_content: The original content that failed to parse
        parse_type: The type of parsing that was attempted
    """

    def __init__(
        self,
        message: str,
        raw_content: Optional[str] = None,
        parse_type: Optional[str] = None,
    ) -> None:
        """Initialize the parse error.

        Args:
            message: Human-readable error message
            raw_content: The original content that failed to parse
            parse_type: The type of parsing that was attempted
        """
        self.message = message
        self.raw_content = raw_content
        self.parse_type = parse_type
        super().__init__(message)


class JsonExtractionError(ParseError):
    """Exception raised when JSON extraction fails."""

    def __init__(
        self,
        message: str,
        raw_content: Optional[str] = None,
    ) -> None:
        """Initialize JSON extraction error.

        Args:
            message: Human-readable error message
            raw_content: The original content that failed to parse
        """
        super().__init__(message, raw_content, "json")


class ValidationParseError(ParseError):
    """Exception raised when Pydantic validation fails during parsing.

    Attributes:
        validation_errors: The Pydantic validation errors
    """

    def __init__(
        self,
        message: str,
        raw_content: Optional[str] = None,
        validation_errors: Optional[list[dict[str, Any]]] = None,
    ) -> None:
        """Initialize validation parse error.

        Args:
            message: Human-readable error message
            raw_content: The original content that failed to parse
            validation_errors: The Pydantic validation errors
        """
        super().__init__(message, raw_content, "validation")
        self.validation_errors = validation_errors or []


class OutputFormat(str, Enum):
    """Supported output formats for LLM responses.

    Attributes:
        JSON: JSON object format
        JSON_ARRAY: JSON array format
        TEXT: Plain text format
        MARKDOWN: Markdown formatted text
        LIST: Bulleted or numbered list
        KEY_VALUE: Key-value pair format
    """

    JSON = "json"
    JSON_ARRAY = "json_array"
    TEXT = "text"
    MARKDOWN = "markdown"
    LIST = "list"
    KEY_VALUE = "key_value"


class ParsedResponse(BaseModel):
    """Container for a parsed LLM response.

    Holds the parsed data along with metadata about the parsing process.

    Attributes:
        data: The extracted/parsed data
        format_detected: The format that was detected in the response
        raw_content: The original raw content from the LLM
        parsing_notes: Optional notes about the parsing process
    """

    model_config = ConfigDict(
        from_attributes=True,
    )

    data: Any = Field(
        ...,
        description="The extracted/parsed data",
    )
    format_detected: OutputFormat = Field(
        default=OutputFormat.TEXT,
        description="The format that was detected in the response",
    )
    raw_content: str = Field(
        ...,
        description="The original raw content from the LLM",
    )
    parsing_notes: Optional[str] = Field(
        default=None,
        description="Optional notes about the parsing process",
    )


# Type variable for generic Pydantic model parsing
T = TypeVar("T", bound=BaseModel)


class ResponseParser:
    """Parser for extracting structured data from LLM responses.

    Provides methods for extracting JSON, lists, key-value pairs, and
    other structured data from raw LLM text responses. Handles common
    patterns like markdown code blocks and escaped characters.

    Example:
        parser = ResponseParser()

        # Extract JSON from a response
        json_data = parser.extract_json('{"name": "John", "age": 30}')

        # Parse into a Pydantic model
        user = parser.parse_to_model(response_text, UserModel)

        # Extract a list of items
        items = parser.extract_list("- Item 1\\n- Item 2\\n- Item 3")
    """

    # Regex patterns for content extraction
    _JSON_BLOCK_PATTERN = re.compile(
        r"```(?:json)?\s*\n?([\s\S]*?)\n?```",
        re.IGNORECASE,
    )
    _JSON_OBJECT_PATTERN = re.compile(
        r"\{[\s\S]*\}",
    )
    _JSON_ARRAY_PATTERN = re.compile(
        r"\[[\s\S]*\]",
    )
    _LIST_ITEM_PATTERN = re.compile(
        r"^[\s]*[-*•]\s+(.+)$|^[\s]*\d+[\.\)]\s+(.+)$",
        re.MULTILINE,
    )
    _KEY_VALUE_PATTERN = re.compile(
        r"^[\s]*([^:]+):\s*(.+)$",
        re.MULTILINE,
    )

    def __init__(
        self,
        strict_json: bool = False,
        strip_markdown: bool = True,
    ) -> None:
        """Initialize the response parser.

        Args:
            strict_json: If True, raise errors on JSON parse failures
            strip_markdown: If True, attempt to strip markdown formatting
        """
        self.strict_json = strict_json
        self.strip_markdown = strip_markdown

    def extract_json(
        self,
        content: str,
        default: Optional[dict[str, Any]] = None,
    ) -> dict[str, Any]:
        """Extract JSON object from LLM response content.

        Handles common patterns like markdown code blocks, escaped quotes,
        and trailing commas. Returns the first valid JSON object found.

        Args:
            content: The raw content to extract JSON from
            default: Default value to return if extraction fails (when not strict)

        Returns:
            The extracted JSON as a dictionary

        Raises:
            JsonExtractionError: If extraction fails and strict_json is True
        """
        if not content or not content.strip():
            if self.strict_json:
                raise JsonExtractionError("Empty content provided", content)
            return default or {}

        # Try to extract from markdown code block first
        code_block_match = self._JSON_BLOCK_PATTERN.search(content)
        if code_block_match:
            json_str = code_block_match.group(1).strip()
            try:
                return json.loads(json_str)
            except json.JSONDecodeError:
                # Continue to try other methods
                pass

        # Try to find raw JSON object
        json_match = self._JSON_OBJECT_PATTERN.search(content)
        if json_match:
            json_str = json_match.group(0)
            try:
                return json.loads(json_str)
            except json.JSONDecodeError:
                # Try to fix common issues
                fixed_json = self._fix_json(json_str)
                try:
                    return json.loads(fixed_json)
                except json.JSONDecodeError:
                    pass

        # Try parsing the entire content as JSON
        try:
            return json.loads(content.strip())
        except json.JSONDecodeError:
            pass

        if self.strict_json:
            raise JsonExtractionError(
                "Could not extract valid JSON from content",
                content,
            )
        return default or {}

    def extract_json_array(
        self,
        content: str,
        default: Optional[list[Any]] = None,
    ) -> list[Any]:
        """Extract JSON array from LLM response content.

        Similar to extract_json but specifically looks for array structures.

        Args:
            content: The raw content to extract JSON array from
            default: Default value to return if extraction fails (when not strict)

        Returns:
            The extracted JSON array as a list

        Raises:
            JsonExtractionError: If extraction fails and strict_json is True
        """
        if not content or not content.strip():
            if self.strict_json:
                raise JsonExtractionError("Empty content provided", content)
            return default or []

        # Try to extract from markdown code block first
        code_block_match = self._JSON_BLOCK_PATTERN.search(content)
        if code_block_match:
            json_str = code_block_match.group(1).strip()
            try:
                result = json.loads(json_str)
                if isinstance(result, list):
                    return result
            except json.JSONDecodeError:
                pass

        # Try to find raw JSON array
        array_match = self._JSON_ARRAY_PATTERN.search(content)
        if array_match:
            json_str = array_match.group(0)
            try:
                result = json.loads(json_str)
                if isinstance(result, list):
                    return result
            except json.JSONDecodeError:
                # Try to fix common issues
                fixed_json = self._fix_json(json_str)
                try:
                    result = json.loads(fixed_json)
                    if isinstance(result, list):
                        return result
                except json.JSONDecodeError:
                    pass

        # Try parsing the entire content as JSON
        try:
            result = json.loads(content.strip())
            if isinstance(result, list):
                return result
        except json.JSONDecodeError:
            pass

        if self.strict_json:
            raise JsonExtractionError(
                "Could not extract valid JSON array from content",
                content,
            )
        return default or []

    def extract_list(
        self,
        content: str,
        strip_items: bool = True,
    ) -> list[str]:
        """Extract list items from LLM response content.

        Recognizes bulleted lists (-, *, •) and numbered lists (1., 1)).

        Args:
            content: The raw content to extract list from
            strip_items: If True, strip whitespace from each item

        Returns:
            List of extracted items as strings
        """
        if not content or not content.strip():
            return []

        items = []
        for match in self._LIST_ITEM_PATTERN.finditer(content):
            # Either group 1 (bullet) or group 2 (numbered) will have content
            item = match.group(1) or match.group(2)
            if item:
                if strip_items:
                    item = item.strip()
                items.append(item)

        return items

    def extract_key_value_pairs(
        self,
        content: str,
        strip_values: bool = True,
    ) -> dict[str, str]:
        """Extract key-value pairs from LLM response content.

        Recognizes "key: value" patterns in the content.

        Args:
            content: The raw content to extract key-value pairs from
            strip_values: If True, strip whitespace from keys and values

        Returns:
            Dictionary of extracted key-value pairs
        """
        if not content or not content.strip():
            return {}

        pairs = {}
        for match in self._KEY_VALUE_PATTERN.finditer(content):
            key = match.group(1)
            value = match.group(2)
            if strip_values:
                key = key.strip()
                value = value.strip()
            pairs[key] = value

        return pairs

    def parse_to_model(
        self,
        content: str,
        model_class: Type[T],
        extract_json_first: bool = True,
    ) -> T:
        """Parse LLM response content into a Pydantic model.

        Attempts to extract JSON from the content and validate it against
        the provided Pydantic model class.

        Args:
            content: The raw content to parse
            model_class: The Pydantic model class to validate against
            extract_json_first: If True, attempt JSON extraction before parsing

        Returns:
            An instance of the model class populated with extracted data

        Raises:
            ValidationParseError: If parsing or validation fails
        """
        if not content or not content.strip():
            raise ValidationParseError(
                f"Cannot parse empty content to {model_class.__name__}",
                content,
            )

        try:
            if extract_json_first:
                data = self.extract_json(content)
            else:
                data = json.loads(content.strip())

            return model_class.model_validate(data)

        except JsonExtractionError as e:
            raise ValidationParseError(
                f"Failed to extract JSON for {model_class.__name__}: {e.message}",
                content,
            ) from e

        except json.JSONDecodeError as e:
            raise ValidationParseError(
                f"Invalid JSON for {model_class.__name__}: {str(e)}",
                content,
            ) from e

        except ValidationError as e:
            raise ValidationParseError(
                f"Validation failed for {model_class.__name__}: {str(e)}",
                content,
                validation_errors=[err for err in e.errors()],
            ) from e

    def parse_to_model_list(
        self,
        content: str,
        model_class: Type[T],
    ) -> list[T]:
        """Parse LLM response content into a list of Pydantic models.

        Attempts to extract a JSON array from the content and validate each
        item against the provided Pydantic model class.

        Args:
            content: The raw content to parse
            model_class: The Pydantic model class for list items

        Returns:
            A list of model class instances

        Raises:
            ValidationParseError: If parsing or validation fails
        """
        if not content or not content.strip():
            raise ValidationParseError(
                f"Cannot parse empty content to list of {model_class.__name__}",
                content,
            )

        try:
            data = self.extract_json_array(content)

            return [model_class.model_validate(item) for item in data]

        except JsonExtractionError as e:
            raise ValidationParseError(
                f"Failed to extract JSON array for {model_class.__name__}: {e.message}",
                content,
            ) from e

        except ValidationError as e:
            raise ValidationParseError(
                f"Validation failed for {model_class.__name__} list: {str(e)}",
                content,
                validation_errors=[err for err in e.errors()],
            ) from e

    def parse(
        self,
        content: str,
        expected_format: Optional[OutputFormat] = None,
    ) -> ParsedResponse:
        """Parse LLM response content with automatic format detection.

        Attempts to detect the format of the content and parse accordingly.
        If expected_format is provided, it will attempt to parse as that format.

        Args:
            content: The raw content to parse
            expected_format: Optional expected format hint

        Returns:
            ParsedResponse with extracted data and metadata
        """
        if not content or not content.strip():
            return ParsedResponse(
                data="",
                format_detected=OutputFormat.TEXT,
                raw_content=content or "",
                parsing_notes="Empty content",
            )

        # If expected format is provided, try that first
        if expected_format == OutputFormat.JSON:
            try:
                data = self.extract_json(content)
                return ParsedResponse(
                    data=data,
                    format_detected=OutputFormat.JSON,
                    raw_content=content,
                )
            except JsonExtractionError:
                pass

        if expected_format == OutputFormat.JSON_ARRAY:
            try:
                data = self.extract_json_array(content)
                return ParsedResponse(
                    data=data,
                    format_detected=OutputFormat.JSON_ARRAY,
                    raw_content=content,
                )
            except JsonExtractionError:
                pass

        # Auto-detect format
        # Try JSON object
        try:
            data = self.extract_json(content)
            if data:  # Non-empty dict found
                return ParsedResponse(
                    data=data,
                    format_detected=OutputFormat.JSON,
                    raw_content=content,
                )
        except JsonExtractionError:
            pass

        # Try JSON array
        try:
            data = self.extract_json_array(content)
            if data:  # Non-empty list found
                return ParsedResponse(
                    data=data,
                    format_detected=OutputFormat.JSON_ARRAY,
                    raw_content=content,
                )
        except JsonExtractionError:
            pass

        # Try list format
        list_items = self.extract_list(content)
        if list_items:
            return ParsedResponse(
                data=list_items,
                format_detected=OutputFormat.LIST,
                raw_content=content,
            )

        # Try key-value format
        kv_pairs = self.extract_key_value_pairs(content)
        if len(kv_pairs) >= 2:  # At least 2 pairs to be considered key-value
            return ParsedResponse(
                data=kv_pairs,
                format_detected=OutputFormat.KEY_VALUE,
                raw_content=content,
            )

        # Check for markdown
        if self._looks_like_markdown(content):
            return ParsedResponse(
                data=content,
                format_detected=OutputFormat.MARKDOWN,
                raw_content=content,
            )

        # Default to plain text
        return ParsedResponse(
            data=content,
            format_detected=OutputFormat.TEXT,
            raw_content=content,
        )

    def _fix_json(self, json_str: str) -> str:
        """Attempt to fix common JSON formatting issues.

        Args:
            json_str: The JSON string to fix

        Returns:
            The fixed JSON string
        """
        # Remove trailing commas before closing brackets
        fixed = re.sub(r",\s*([}\]])", r"\1", json_str)

        # Fix unescaped newlines in strings
        fixed = re.sub(r'(?<!\\)\n(?=.*")', r"\\n", fixed)

        return fixed

    def _looks_like_markdown(self, content: str) -> bool:
        """Check if content appears to be markdown formatted.

        Args:
            content: The content to check

        Returns:
            True if content appears to be markdown
        """
        markdown_indicators = [
            r"^#{1,6}\s+",  # Headers
            r"^\*\*[^*]+\*\*",  # Bold
            r"^```",  # Code blocks
            r"^\|.+\|",  # Tables
            r"^\[.+\]\(.+\)",  # Links
        ]

        for pattern in markdown_indicators:
            if re.search(pattern, content, re.MULTILINE):
                return True

        return False

    def extract_code_blocks(
        self,
        content: str,
        language: Optional[str] = None,
    ) -> list[str]:
        """Extract code blocks from markdown-formatted content.

        Args:
            content: The content to extract code blocks from
            language: Optional language filter (e.g., "python", "json")

        Returns:
            List of code block contents
        """
        if not content:
            return []

        if language:
            # Pattern for specific language
            pattern = re.compile(
                rf"```{language}\s*\n?([\s\S]*?)\n?```",
                re.IGNORECASE,
            )
        else:
            # Pattern for any code block
            pattern = re.compile(
                r"```(?:\w+)?\s*\n?([\s\S]*?)\n?```",
                re.IGNORECASE,
            )

        blocks = []
        for match in pattern.finditer(content):
            block = match.group(1).strip()
            if block:
                blocks.append(block)

        return blocks

    def strip_markdown_formatting(self, content: str) -> str:
        """Remove common markdown formatting from content.

        Useful for extracting plain text from markdown-formatted responses.

        Args:
            content: The markdown content to strip

        Returns:
            The content with markdown formatting removed
        """
        if not content:
            return ""

        result = content

        # Remove code blocks
        result = re.sub(r"```[\s\S]*?```", "", result)

        # Remove inline code
        result = re.sub(r"`[^`]+`", lambda m: m.group(0)[1:-1], result)

        # Remove bold/italic
        result = re.sub(r"\*\*([^*]+)\*\*", r"\1", result)
        result = re.sub(r"\*([^*]+)\*", r"\1", result)
        result = re.sub(r"__([^_]+)__", r"\1", result)
        result = re.sub(r"_([^_]+)_", r"\1", result)

        # Remove headers
        result = re.sub(r"^#{1,6}\s+", "", result, flags=re.MULTILINE)

        # Remove links, keep text
        result = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", result)

        # Clean up extra whitespace
        result = re.sub(r"\n{3,}", "\n\n", result)

        return result.strip()
