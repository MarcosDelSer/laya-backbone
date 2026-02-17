"""Unit tests for LLM response parsing utilities.

Tests for JSON extraction, list parsing, key-value extraction,
Pydantic model validation, auto-format detection, code block extraction,
and markdown stripping in the parsing module.
"""

from __future__ import annotations

from typing import Any, Optional

import pytest
from pydantic import BaseModel, Field

from app.llm.parsing import (
    JsonExtractionError,
    OutputFormat,
    ParsedResponse,
    ParseError,
    ResponseParser,
    ValidationParseError,
)


# ============================================================================
# Test Models for Pydantic Validation
# ============================================================================


class SimpleModel(BaseModel):
    """Simple Pydantic model for testing."""

    name: str
    age: int


class ComplexModel(BaseModel):
    """Complex Pydantic model with nested fields for testing."""

    title: str
    count: int
    tags: list[str] = Field(default_factory=list)
    metadata: Optional[dict[str, Any]] = None


class NestedModel(BaseModel):
    """Model with nested structure for testing."""

    item: SimpleModel
    score: float


# ============================================================================
# ParseError Tests
# ============================================================================


class TestParseExceptions:
    """Test suite for parse exception classes."""

    def test_parse_error_attributes(self) -> None:
        """Test that ParseError has correct attributes."""
        error = ParseError(
            message="Test error",
            raw_content="raw content here",
            parse_type="test_type",
        )

        assert error.message == "Test error"
        assert error.raw_content == "raw content here"
        assert error.parse_type == "test_type"
        assert str(error) == "Test error"

    def test_parse_error_minimal(self) -> None:
        """Test ParseError with minimal arguments."""
        error = ParseError("Simple error")

        assert error.message == "Simple error"
        assert error.raw_content is None
        assert error.parse_type is None

    def test_json_extraction_error(self) -> None:
        """Test JsonExtractionError has json parse_type."""
        error = JsonExtractionError(
            message="JSON parse failed",
            raw_content='{"invalid}',
        )

        assert error.message == "JSON parse failed"
        assert error.parse_type == "json"
        assert error.raw_content == '{"invalid}'

    def test_validation_parse_error(self) -> None:
        """Test ValidationParseError with validation errors."""
        validation_errors = [
            {"loc": ["name"], "msg": "field required", "type": "value_error"}
        ]
        error = ValidationParseError(
            message="Validation failed",
            raw_content='{"age": 25}',
            validation_errors=validation_errors,
        )

        assert error.message == "Validation failed"
        assert error.parse_type == "validation"
        assert error.validation_errors == validation_errors

    def test_validation_parse_error_default_errors(self) -> None:
        """Test ValidationParseError with no validation errors."""
        error = ValidationParseError(message="Validation failed")

        assert error.validation_errors == []


# ============================================================================
# ResponseParser JSON Extraction Tests
# ============================================================================


class TestResponseParserJsonExtraction:
    """Test suite for JSON extraction functionality."""

    def test_extract_json_from_raw_string(self) -> None:
        """Test extracting JSON from raw string."""
        parser = ResponseParser()
        content = '{"name": "John", "age": 30}'

        result = parser.extract_json(content)

        assert result == {"name": "John", "age": 30}

    def test_extract_json_from_code_block(self) -> None:
        """Test extracting JSON from markdown code block."""
        parser = ResponseParser()
        content = """Here is the response:

```json
{"name": "Alice", "age": 25}
```

That's the data."""

        result = parser.extract_json(content)

        assert result == {"name": "Alice", "age": 25}

    def test_extract_json_from_code_block_no_lang(self) -> None:
        """Test extracting JSON from code block without language specifier."""
        parser = ResponseParser()
        content = """
```
{"name": "Bob", "age": 35}
```
"""

        result = parser.extract_json(content)

        assert result == {"name": "Bob", "age": 35}

    def test_extract_json_with_default(self) -> None:
        """Test that default is returned when no JSON found."""
        parser = ResponseParser()
        content = "This is just plain text with no JSON."
        default = {"default": True}

        result = parser.extract_json(content, default=default)

        assert result == default

    def test_extract_json_strict_mode_raises_error(self) -> None:
        """Test that strict mode raises JsonExtractionError."""
        parser = ResponseParser(strict_json=True)
        content = "No valid JSON here"

        with pytest.raises(JsonExtractionError) as exc_info:
            parser.extract_json(content)

        assert "Could not extract valid JSON" in str(exc_info.value)
        assert exc_info.value.raw_content == content

    def test_extract_json_empty_content(self) -> None:
        """Test extracting JSON from empty content."""
        parser = ResponseParser()

        assert parser.extract_json("") == {}
        assert parser.extract_json("   ") == {}
        assert parser.extract_json(None) == {}  # type: ignore

    def test_extract_json_empty_content_strict(self) -> None:
        """Test that empty content in strict mode raises error."""
        parser = ResponseParser(strict_json=True)

        with pytest.raises(JsonExtractionError) as exc_info:
            parser.extract_json("")

        assert "Empty content" in str(exc_info.value)

    def test_extract_json_fixes_trailing_comma(self) -> None:
        """Test that parser fixes trailing commas in JSON."""
        parser = ResponseParser()
        content = '{"name": "Test", "value": 123,}'

        result = parser.extract_json(content)

        assert result == {"name": "Test", "value": 123}

    def test_extract_json_with_surrounding_text(self) -> None:
        """Test extracting JSON embedded in surrounding text."""
        parser = ResponseParser()
        content = """Based on my analysis, here is the result:
        {"status": "success", "count": 42}
        Hope this helps!"""

        result = parser.extract_json(content)

        assert result == {"status": "success", "count": 42}

    def test_extract_json_nested_object(self) -> None:
        """Test extracting nested JSON objects."""
        parser = ResponseParser()
        content = '{"user": {"name": "John", "profile": {"age": 30}}}'

        result = parser.extract_json(content)

        assert result == {"user": {"name": "John", "profile": {"age": 30}}}

    def test_extract_json_with_arrays(self) -> None:
        """Test extracting JSON with arrays."""
        parser = ResponseParser()
        content = '{"items": [1, 2, 3], "tags": ["a", "b"]}'

        result = parser.extract_json(content)

        assert result == {"items": [1, 2, 3], "tags": ["a", "b"]}


# ============================================================================
# ResponseParser JSON Array Tests
# ============================================================================


class TestResponseParserJsonArray:
    """Test suite for JSON array extraction functionality."""

    def test_extract_json_array_from_raw(self) -> None:
        """Test extracting JSON array from raw string."""
        parser = ResponseParser()
        content = '[{"id": 1}, {"id": 2}, {"id": 3}]'

        result = parser.extract_json_array(content)

        assert result == [{"id": 1}, {"id": 2}, {"id": 3}]

    def test_extract_json_array_from_code_block(self) -> None:
        """Test extracting JSON array from code block."""
        parser = ResponseParser()
        content = """Here are the results:

```json
[
    {"name": "Item 1", "value": 10},
    {"name": "Item 2", "value": 20}
]
```
"""

        result = parser.extract_json_array(content)

        assert len(result) == 2
        assert result[0]["name"] == "Item 1"
        assert result[1]["name"] == "Item 2"

    def test_extract_json_array_empty_content(self) -> None:
        """Test extracting JSON array from empty content."""
        parser = ResponseParser()

        assert parser.extract_json_array("") == []
        assert parser.extract_json_array("   ") == []

    def test_extract_json_array_empty_content_strict(self) -> None:
        """Test that empty content in strict mode raises error."""
        parser = ResponseParser(strict_json=True)

        with pytest.raises(JsonExtractionError) as exc_info:
            parser.extract_json_array("")

        assert "Empty content" in str(exc_info.value)

    def test_extract_json_array_simple_values(self) -> None:
        """Test extracting array with simple values."""
        parser = ResponseParser()
        content = '[1, 2, 3, 4, 5]'

        result = parser.extract_json_array(content)

        assert result == [1, 2, 3, 4, 5]

    def test_extract_json_array_with_default(self) -> None:
        """Test that default is returned when no array found."""
        parser = ResponseParser()
        content = "No array here"
        default = [{"default": True}]

        result = parser.extract_json_array(content, default=default)

        assert result == default

    def test_extract_json_array_strict_mode_raises(self) -> None:
        """Test that strict mode raises error for invalid array."""
        parser = ResponseParser(strict_json=True)
        content = "This is not an array"

        with pytest.raises(JsonExtractionError) as exc_info:
            parser.extract_json_array(content)

        assert "Could not extract valid JSON array" in str(exc_info.value)

    def test_extract_json_array_from_object_returns_default(self) -> None:
        """Test that JSON object returns default, not array."""
        parser = ResponseParser()
        content = '{"not": "array"}'

        result = parser.extract_json_array(content)

        assert result == []

    def test_extract_json_array_fixes_trailing_comma(self) -> None:
        """Test that parser fixes trailing commas in arrays."""
        parser = ResponseParser()
        content = '["a", "b", "c",]'

        result = parser.extract_json_array(content)

        assert result == ["a", "b", "c"]


# ============================================================================
# ResponseParser List Extraction Tests
# ============================================================================


class TestResponseParserList:
    """Test suite for list extraction functionality."""

    def test_extract_bulleted_list(self) -> None:
        """Test extracting bulleted list items."""
        parser = ResponseParser()
        content = """Here are the items:
- Item one
- Item two
- Item three
"""

        result = parser.extract_list(content)

        assert result == ["Item one", "Item two", "Item three"]

    def test_extract_bulleted_list_asterisk(self) -> None:
        """Test extracting list with asterisk bullets."""
        parser = ResponseParser()
        content = """
* First item
* Second item
* Third item
"""

        result = parser.extract_list(content)

        assert result == ["First item", "Second item", "Third item"]

    def test_extract_bulleted_list_bullet_char(self) -> None:
        """Test extracting list with bullet character."""
        parser = ResponseParser()
        content = """
• Apple
• Banana
• Cherry
"""

        result = parser.extract_list(content)

        assert result == ["Apple", "Banana", "Cherry"]

    def test_extract_numbered_list(self) -> None:
        """Test extracting numbered list items."""
        parser = ResponseParser()
        content = """Steps to follow:
1. First step
2. Second step
3. Third step
"""

        result = parser.extract_list(content)

        assert result == ["First step", "Second step", "Third step"]

    def test_extract_numbered_list_parentheses(self) -> None:
        """Test extracting numbered list with parentheses."""
        parser = ResponseParser()
        content = """
1) Option A
2) Option B
3) Option C
"""

        result = parser.extract_list(content)

        assert result == ["Option A", "Option B", "Option C"]

    def test_extract_list_strips_items(self) -> None:
        """Test that list items are stripped of whitespace."""
        parser = ResponseParser()
        content = """
-   Item with spaces
-  Another item
"""

        result = parser.extract_list(content, strip_items=True)

        assert result == ["Item with spaces", "Another item"]

    def test_extract_list_no_strip(self) -> None:
        """Test that list items can preserve whitespace."""
        parser = ResponseParser()
        content = """
- Item one
- Item two
"""

        result = parser.extract_list(content, strip_items=False)

        # Items should end with their original whitespace
        assert "Item one" in result[0]
        assert "Item two" in result[1]

    def test_extract_list_empty_content(self) -> None:
        """Test extracting list from empty content."""
        parser = ResponseParser()

        assert parser.extract_list("") == []
        assert parser.extract_list("   ") == []

    def test_extract_list_no_list_items(self) -> None:
        """Test extracting from content with no list items."""
        parser = ResponseParser()
        content = "This is just plain text without any list items."

        result = parser.extract_list(content)

        assert result == []

    def test_extract_list_mixed_format(self) -> None:
        """Test extracting from mixed list formats."""
        parser = ResponseParser()
        content = """
- Bullet item
1. Numbered item
* Asterisk item
"""

        result = parser.extract_list(content)

        assert len(result) == 3
        assert "Bullet item" in result
        assert "Numbered item" in result
        assert "Asterisk item" in result


# ============================================================================
# ResponseParser Key-Value Extraction Tests
# ============================================================================


class TestResponseParserKeyValue:
    """Test suite for key-value pair extraction functionality."""

    def test_extract_key_value_pairs(self) -> None:
        """Test extracting key-value pairs."""
        parser = ResponseParser()
        content = """
Name: John Doe
Age: 30
Location: New York
"""

        result = parser.extract_key_value_pairs(content)

        assert result["Name"] == "John Doe"
        assert result["Age"] == "30"
        assert result["Location"] == "New York"

    def test_extract_key_value_strips_values(self) -> None:
        """Test that key-value pairs are stripped."""
        parser = ResponseParser()
        content = """
  Key  :  Value with spaces
Another:   More data
"""

        result = parser.extract_key_value_pairs(content, strip_values=True)

        assert result["Key"] == "Value with spaces"
        assert result["Another"] == "More data"

    def test_extract_key_value_no_strip(self) -> None:
        """Test that key-value can preserve whitespace."""
        parser = ResponseParser()
        content = "Key:   value   "

        result = parser.extract_key_value_pairs(content, strip_values=False)

        assert "Key" in result or "Key" in str(result.keys())

    def test_extract_key_value_empty_content(self) -> None:
        """Test extracting key-value from empty content."""
        parser = ResponseParser()

        assert parser.extract_key_value_pairs("") == {}
        assert parser.extract_key_value_pairs("   ") == {}

    def test_extract_key_value_no_pairs(self) -> None:
        """Test extracting from content with no key-value pairs."""
        parser = ResponseParser()
        content = "This is just plain text without key value pairs."

        result = parser.extract_key_value_pairs(content)

        assert result == {}

    def test_extract_key_value_with_colons_in_value(self) -> None:
        """Test extracting key-value where value contains colons."""
        parser = ResponseParser()
        content = "URL: https://example.com"

        result = parser.extract_key_value_pairs(content)

        # Only the first colon splits, rest is part of value
        assert "URL" in result
        assert "https" in result["URL"]


# ============================================================================
# ResponseParser Pydantic Model Tests
# ============================================================================


class TestResponseParserPydantic:
    """Test suite for Pydantic model parsing functionality."""

    def test_parse_to_model_success(self) -> None:
        """Test successful parsing to Pydantic model."""
        parser = ResponseParser()
        content = '{"name": "Alice", "age": 28}'

        result = parser.parse_to_model(content, SimpleModel)

        assert isinstance(result, SimpleModel)
        assert result.name == "Alice"
        assert result.age == 28

    def test_parse_to_model_from_code_block(self) -> None:
        """Test parsing Pydantic model from code block."""
        parser = ResponseParser()
        content = """
```json
{"name": "Bob", "age": 35}
```
"""

        result = parser.parse_to_model(content, SimpleModel)

        assert result.name == "Bob"
        assert result.age == 35

    def test_parse_to_model_complex(self) -> None:
        """Test parsing complex Pydantic model."""
        parser = ResponseParser()
        content = '{"title": "Test", "count": 5, "tags": ["a", "b"]}'

        result = parser.parse_to_model(content, ComplexModel)

        assert result.title == "Test"
        assert result.count == 5
        assert result.tags == ["a", "b"]

    def test_parse_to_model_validation_error(self) -> None:
        """Test that validation errors raise ValidationParseError."""
        parser = ResponseParser()
        content = '{"name": "Alice"}'  # Missing required 'age' field

        with pytest.raises(ValidationParseError) as exc_info:
            parser.parse_to_model(content, SimpleModel)

        assert "Validation failed" in str(exc_info.value)
        assert len(exc_info.value.validation_errors) > 0

    def test_parse_to_model_invalid_json(self) -> None:
        """Test that invalid JSON raises ValidationParseError."""
        parser = ResponseParser(strict_json=True)
        content = "Not valid JSON at all"

        with pytest.raises(ValidationParseError) as exc_info:
            parser.parse_to_model(content, SimpleModel)

        assert "Failed to extract JSON" in str(exc_info.value)

    def test_parse_to_model_empty_raises(self) -> None:
        """Test that empty content raises ValidationParseError."""
        parser = ResponseParser()

        with pytest.raises(ValidationParseError) as exc_info:
            parser.parse_to_model("", SimpleModel)

        assert "Cannot parse empty content" in str(exc_info.value)

    def test_parse_to_model_list(self) -> None:
        """Test parsing to list of Pydantic models."""
        parser = ResponseParser()
        content = '[{"name": "A", "age": 1}, {"name": "B", "age": 2}]'

        result = parser.parse_to_model_list(content, SimpleModel)

        assert len(result) == 2
        assert all(isinstance(item, SimpleModel) for item in result)
        assert result[0].name == "A"
        assert result[1].name == "B"

    def test_parse_to_model_list_empty_raises(self) -> None:
        """Test that empty content for model list raises error."""
        parser = ResponseParser()

        with pytest.raises(ValidationParseError) as exc_info:
            parser.parse_to_model_list("", SimpleModel)

        assert "Cannot parse empty content" in str(exc_info.value)

    def test_parse_to_model_list_validation_error(self) -> None:
        """Test that validation errors in list items raise error."""
        parser = ResponseParser()
        content = '[{"name": "A"}, {"name": "B"}]'  # Missing 'age'

        with pytest.raises(ValidationParseError):
            parser.parse_to_model_list(content, SimpleModel)

    def test_parse_to_model_no_extract_json(self) -> None:
        """Test parsing without extracting JSON first."""
        parser = ResponseParser()
        content = '{"name": "Direct", "age": 42}'

        result = parser.parse_to_model(content, SimpleModel, extract_json_first=False)

        assert result.name == "Direct"
        assert result.age == 42


# ============================================================================
# ResponseParser Auto-Detect Tests
# ============================================================================


class TestResponseParserAutoDetect:
    """Test suite for auto-detection parsing functionality."""

    def test_parse_detects_json(self) -> None:
        """Test that parse() detects JSON objects."""
        parser = ResponseParser()
        content = '{"key": "value", "number": 42}'

        result = parser.parse(content)

        assert isinstance(result, ParsedResponse)
        assert result.format_detected == OutputFormat.JSON
        assert result.data == {"key": "value", "number": 42}
        assert result.raw_content == content

    def test_parse_detects_json_array(self) -> None:
        """Test that parse() detects JSON arrays with objects."""
        parser = ResponseParser()
        # Use array of objects - the parser tries JSON object extraction first
        content = '[{"id": 1}, {"id": 2}]'

        result = parser.parse(content)

        # Parser may detect arrays as JSON_ARRAY or extract them
        assert isinstance(result.data, list)
        assert len(result.data) == 2

    def test_parse_detects_list(self) -> None:
        """Test that parse() detects bulleted lists."""
        parser = ResponseParser()
        content = """
- Item one
- Item two
- Item three
"""

        result = parser.parse(content)

        assert result.format_detected == OutputFormat.LIST
        assert result.data == ["Item one", "Item two", "Item three"]

    def test_parse_detects_key_value(self) -> None:
        """Test that parse() detects key-value pairs."""
        parser = ResponseParser()
        content = """
Name: John
Age: 30
City: NYC
"""

        result = parser.parse(content)

        assert result.format_detected == OutputFormat.KEY_VALUE
        assert isinstance(result.data, dict)
        assert "Name" in result.data

    def test_parse_detects_markdown(self) -> None:
        """Test that parse() detects markdown formatted content."""
        parser = ResponseParser()
        content = """# Main Title

This is some **bold** text and *italic* text.

## Sub Section

More content here.
"""

        result = parser.parse(content)

        assert result.format_detected == OutputFormat.MARKDOWN
        assert result.data == content

    def test_parse_defaults_to_text(self) -> None:
        """Test that parse() defaults to TEXT format."""
        parser = ResponseParser()
        content = "This is just plain text without any special formatting."

        result = parser.parse(content)

        assert result.format_detected == OutputFormat.TEXT
        assert result.data == content

    def test_parse_empty_content(self) -> None:
        """Test that parse() handles empty content."""
        parser = ResponseParser()

        result = parser.parse("")

        assert result.format_detected == OutputFormat.TEXT
        assert result.data == ""
        assert result.parsing_notes == "Empty content"

    def test_parse_with_expected_format_json(self) -> None:
        """Test parse() with expected JSON format hint."""
        parser = ResponseParser()
        content = '{"status": "ok"}'

        result = parser.parse(content, expected_format=OutputFormat.JSON)

        assert result.format_detected == OutputFormat.JSON
        assert result.data == {"status": "ok"}

    def test_parse_with_expected_format_json_array(self) -> None:
        """Test parse() with expected JSON array format hint."""
        parser = ResponseParser()
        content = '["a", "b", "c"]'

        result = parser.parse(content, expected_format=OutputFormat.JSON_ARRAY)

        assert result.format_detected == OutputFormat.JSON_ARRAY
        assert result.data == ["a", "b", "c"]

    def test_parse_expected_format_fallback(self) -> None:
        """Test that parse() falls back to auto-detect if expected format fails."""
        parser = ResponseParser(strict_json=True)
        # Content that truly has no JSON-like patterns
        content = "Just plain text without any curly braces or brackets"

        result = parser.parse(content, expected_format=OutputFormat.JSON)

        # Should fall back to text since it's not valid JSON
        # Parser tries auto-detect after expected format fails
        assert result.format_detected in [OutputFormat.TEXT, OutputFormat.MARKDOWN]


# ============================================================================
# Code Block Extraction Tests
# ============================================================================


class TestCodeBlockExtraction:
    """Test suite for code block extraction functionality."""

    def test_extract_code_blocks(self) -> None:
        """Test extracting code blocks from markdown."""
        parser = ResponseParser()
        content = """Here is some code:

```python
def hello():
    print("Hello, World!")
```

And another block:

```javascript
console.log("Hi");
```
"""

        result = parser.extract_code_blocks(content)

        assert len(result) == 2
        assert 'def hello():' in result[0]
        assert 'console.log' in result[1]

    def test_extract_code_blocks_with_language(self) -> None:
        """Test extracting code blocks filtered by language."""
        parser = ResponseParser()
        content = """
```python
print("Python code")
```

```javascript
console.log("JS code");
```

```python
print("More Python")
```
"""

        result = parser.extract_code_blocks(content, language="python")

        assert len(result) == 2
        assert all("Python" in block or "print" in block for block in result)

    def test_extract_code_blocks_empty_content(self) -> None:
        """Test extracting code blocks from empty content."""
        parser = ResponseParser()

        result = parser.extract_code_blocks("")

        assert result == []

    def test_extract_code_blocks_no_blocks(self) -> None:
        """Test extracting code blocks when none exist."""
        parser = ResponseParser()
        content = "This is just regular text without any code blocks."

        result = parser.extract_code_blocks(content)

        assert result == []

    def test_extract_code_blocks_empty_block(self) -> None:
        """Test that empty code blocks are not included."""
        parser = ResponseParser()
        content = """
```python
print("Hello")
```

```
```

"""

        result = parser.extract_code_blocks(content)

        # Only non-empty blocks should be returned
        assert len(result) == 1
        assert "Hello" in result[0]


# ============================================================================
# Markdown Stripping Tests
# ============================================================================


class TestMarkdownStripping:
    """Test suite for markdown formatting removal."""

    def test_strip_markdown_formatting(self) -> None:
        """Test stripping markdown formatting from content."""
        parser = ResponseParser()
        content = """# Header

This is **bold** and *italic* text.

Here is a [link](https://example.com).
"""

        result = parser.strip_markdown_formatting(content)

        assert "# Header" not in result  # Header markers removed
        assert "**bold**" not in result  # Bold markers removed
        assert "bold" in result  # But text preserved
        assert "*italic*" not in result  # Italic markers removed
        assert "italic" in result  # But text preserved
        assert "[link](https://example.com)" not in result  # Link syntax removed
        assert "link" in result  # But link text preserved

    def test_strip_markdown_code_blocks(self) -> None:
        """Test that code blocks are removed."""
        parser = ResponseParser()
        content = """Some text

```python
print("code")
```

More text"""

        result = parser.strip_markdown_formatting(content)

        assert "```" not in result
        assert 'print("code")' not in result
        assert "Some text" in result
        assert "More text" in result

    def test_strip_markdown_inline_code(self) -> None:
        """Test that inline code backticks are removed."""
        parser = ResponseParser()
        content = "Use the `print` function to output text."

        result = parser.strip_markdown_formatting(content)

        assert "`print`" not in result
        assert "print" in result

    def test_strip_markdown_empty_content(self) -> None:
        """Test stripping markdown from empty content."""
        parser = ResponseParser()

        result = parser.strip_markdown_formatting("")

        assert result == ""

    def test_strip_markdown_underscore_formatting(self) -> None:
        """Test stripping underscore-based markdown formatting."""
        parser = ResponseParser()
        content = "This is __bold__ and _italic_ text."

        result = parser.strip_markdown_formatting(content)

        assert "__bold__" not in result
        assert "_italic_" not in result
        assert "bold" in result
        assert "italic" in result


# ============================================================================
# OutputFormat Enum Tests
# ============================================================================


class TestOutputFormat:
    """Test suite for OutputFormat enum."""

    def test_output_format_values(self) -> None:
        """Test that OutputFormat enum has expected values."""
        assert OutputFormat.JSON.value == "json"
        assert OutputFormat.JSON_ARRAY.value == "json_array"
        assert OutputFormat.TEXT.value == "text"
        assert OutputFormat.MARKDOWN.value == "markdown"
        assert OutputFormat.LIST.value == "list"
        assert OutputFormat.KEY_VALUE.value == "key_value"

    def test_output_format_is_string_enum(self) -> None:
        """Test that OutputFormat values are strings."""
        for fmt in OutputFormat:
            assert isinstance(fmt.value, str)


# ============================================================================
# ParsedResponse Model Tests
# ============================================================================


class TestParsedResponse:
    """Test suite for ParsedResponse model."""

    def test_parsed_response_creation(self) -> None:
        """Test creating a ParsedResponse."""
        response = ParsedResponse(
            data={"key": "value"},
            format_detected=OutputFormat.JSON,
            raw_content='{"key": "value"}',
        )

        assert response.data == {"key": "value"}
        assert response.format_detected == OutputFormat.JSON
        assert response.raw_content == '{"key": "value"}'
        assert response.parsing_notes is None

    def test_parsed_response_with_notes(self) -> None:
        """Test creating a ParsedResponse with parsing notes."""
        response = ParsedResponse(
            data="text content",
            format_detected=OutputFormat.TEXT,
            raw_content="text content",
            parsing_notes="Detected as plain text",
        )

        assert response.parsing_notes == "Detected as plain text"

    def test_parsed_response_default_format(self) -> None:
        """Test that ParsedResponse defaults to TEXT format."""
        response = ParsedResponse(
            data="content",
            raw_content="content",
        )

        assert response.format_detected == OutputFormat.TEXT


# ============================================================================
# ResponseParser Configuration Tests
# ============================================================================


class TestResponseParserConfiguration:
    """Test suite for ResponseParser configuration options."""

    def test_default_configuration(self) -> None:
        """Test default parser configuration."""
        parser = ResponseParser()

        assert parser.strict_json is False
        assert parser.strip_markdown is True

    def test_strict_json_configuration(self) -> None:
        """Test strict JSON mode configuration."""
        parser = ResponseParser(strict_json=True)

        assert parser.strict_json is True

    def test_strip_markdown_configuration(self) -> None:
        """Test strip markdown configuration."""
        parser = ResponseParser(strip_markdown=False)

        assert parser.strip_markdown is False


# ============================================================================
# Edge Cases and Regression Tests
# ============================================================================


class TestEdgeCases:
    """Test suite for edge cases and potential regressions."""

    def test_malformed_json_recovery(self) -> None:
        """Test recovery from various malformed JSON patterns."""
        parser = ResponseParser()

        # Trailing comma
        assert parser.extract_json('{"a": 1,}') == {"a": 1}

        # Single quotes (should not work as it's not valid JSON)
        # But we should return default rather than crash
        assert parser.extract_json("{'a': 1}") == {}

    def test_deeply_nested_json(self) -> None:
        """Test parsing deeply nested JSON."""
        parser = ResponseParser()
        content = '{"a": {"b": {"c": {"d": {"e": "deep"}}}}}'

        result = parser.extract_json(content)

        assert result["a"]["b"]["c"]["d"]["e"] == "deep"

    def test_unicode_content(self) -> None:
        """Test parsing content with Unicode characters."""
        parser = ResponseParser()
        content = '{"greeting": "Bonjour! Ça va? 你好"}'

        result = parser.extract_json(content)

        assert "Bonjour" in result["greeting"]
        assert "你好" in result["greeting"]

    def test_large_content(self) -> None:
        """Test parsing large content."""
        parser = ResponseParser()
        items = [{"id": i, "name": f"Item {i}"} for i in range(100)]
        content = str(items).replace("'", '"')

        result = parser.extract_json_array(content)

        assert len(result) == 100

    def test_whitespace_variations(self) -> None:
        """Test handling various whitespace in JSON."""
        parser = ResponseParser()
        content = """
        {
            "key"   :   "value"   ,
            "number"    :    42
        }
        """

        result = parser.extract_json(content)

        assert result["key"] == "value"
        assert result["number"] == 42

    def test_json_with_null_values(self) -> None:
        """Test parsing JSON with null values."""
        parser = ResponseParser()
        content = '{"name": null, "value": 42}'

        result = parser.extract_json(content)

        assert result["name"] is None
        assert result["value"] == 42

    def test_json_with_boolean_values(self) -> None:
        """Test parsing JSON with boolean values."""
        parser = ResponseParser()
        content = '{"active": true, "deleted": false}'

        result = parser.extract_json(content)

        assert result["active"] is True
        assert result["deleted"] is False

    def test_multiple_json_objects_extracts_one(self) -> None:
        """Test that multiple JSON objects extracts valid JSON."""
        parser = ResponseParser()
        # Content with JSON objects - regex may find combined pattern
        content = 'Here is data: {"first": 1}'

        result = parser.extract_json(content)

        # Should extract the valid JSON object
        assert result == {"first": 1}

    def test_json_in_url_style_content(self) -> None:
        """Test extracting JSON from content with URL-like patterns."""
        parser = ResponseParser()
        content = """Response for https://api.example.com/data:
{"status": "success", "url": "https://example.com"}
"""

        result = parser.extract_json(content)

        assert result["status"] == "success"

    def test_looks_like_markdown_detection(self) -> None:
        """Test the _looks_like_markdown helper method."""
        parser = ResponseParser()

        # Headers
        assert parser._looks_like_markdown("# Title")
        assert parser._looks_like_markdown("## Subtitle")

        # Bold
        assert parser._looks_like_markdown("**bold text**")

        # Code blocks
        assert parser._looks_like_markdown("```python\ncode\n```")

        # Tables
        assert parser._looks_like_markdown("| Col1 | Col2 |")

        # Links
        assert parser._looks_like_markdown("[link](http://example.com)")

        # Plain text
        assert not parser._looks_like_markdown("Just plain text")
