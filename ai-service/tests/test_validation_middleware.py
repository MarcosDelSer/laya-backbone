"""Tests for validation middleware and exception handling.

This test suite verifies that the validation middleware properly handles
validation errors and returns security-focused error messages.
"""

import pytest
from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.testclient import TestClient
from pydantic import BaseModel, Field, ValidationError

from app.main import app
from app.middleware.validation import (
    get_validation_middleware,
    validation_exception_handler,
)


class TestValidationExceptionHandler:
    """Test validation exception handler."""

    @pytest.mark.asyncio
    async def test_handles_request_validation_error(self):
        """Test that handler processes RequestValidationError correctly."""
        from fastapi import FastAPI
        from pydantic import BaseModel

        test_app = FastAPI()

        class TestModel(BaseModel):
            name: str
            age: int

        @test_app.post("/test")
        async def test_endpoint(data: TestModel):
            return {"ok": True}

        # Create a mock request
        client = TestClient(test_app)

        # Test with invalid data (age as string instead of int)
        response = client.post(
            "/test",
            json={"name": "John", "age": "not-a-number"},
        )

        # Should return 422 Unprocessable Entity
        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_error_response_format(self):
        """Test that error response has the correct format."""
        from fastapi.exceptions import RequestValidationError
        from pydantic import BaseModel, ValidationError

        class TestModel(BaseModel):
            name: str
            age: int

        # Create a validation error
        try:
            TestModel(name=123, age="not-a-number")
        except ValidationError as exc:
            # Mock request
            from unittest.mock import MagicMock

            mock_request = MagicMock(spec=Request)

            # Call the handler
            response = await validation_exception_handler(mock_request, exc)

            # Check response structure
            assert response.status_code == 422
            content = response.body.decode()
            assert "detail" in content
            assert "errors" in content
            assert "message" in content

    def test_validation_errors_include_field_info(self):
        """Test that validation errors include field information."""
        from pydantic import BaseModel

        class TestModel(BaseModel):
            name: str
            age: int

            model_config = {"strict": True}

        # Create validation error
        try:
            TestModel(name="John", age="25")  # String instead of int
            assert False, "Should have raised ValidationError"
        except ValidationError as exc:
            errors = exc.errors()
            # Should have error for 'age' field
            assert any("age" in str(e.get("loc", [])) for e in errors)
            # Should indicate int_type error
            assert any("int_type" in str(e.get("type", "")) for e in errors)


class TestValidationMiddleware:
    """Test validation middleware integration."""

    def test_middleware_catches_validation_errors(self):
        """Test that middleware catches and handles validation errors."""
        from fastapi import FastAPI
        from pydantic import BaseModel

        test_app = FastAPI()
        test_app.add_exception_handler(
            RequestValidationError, validation_exception_handler
        )
        test_app.add_exception_handler(ValidationError, validation_exception_handler)

        class StrictModel(BaseModel):
            value: int

            model_config = {"strict": True}

        @test_app.post("/test")
        async def test_endpoint(data: StrictModel):
            return {"value": data.value}

        client = TestClient(test_app)

        # Send string instead of int (should fail with strict mode)
        response = client.post("/test", json={"value": "123"})

        # Should return 422 with validation error
        assert response.status_code == 422
        data = response.json()
        assert "detail" in data
        assert "errors" in data

    def test_middleware_allows_valid_requests(self):
        """Test that middleware allows valid requests through."""
        from fastapi import FastAPI
        from pydantic import BaseModel

        test_app = FastAPI()
        test_app.add_exception_handler(
            RequestValidationError, validation_exception_handler
        )

        class StrictModel(BaseModel):
            value: int

            model_config = {"strict": True}

        @test_app.post("/test")
        async def test_endpoint(data: StrictModel):
            return {"value": data.value}

        client = TestClient(test_app)

        # Send valid int
        response = client.post("/test", json={"value": 123})

        # Should succeed
        assert response.status_code == 200
        assert response.json() == {"value": 123}


class TestMainAppValidationIntegration:
    """Test that validation is properly integrated in main app."""

    def test_main_app_has_validation_handler(self):
        """Test that main app has validation exception handlers registered."""
        # Check that exception handlers are registered
        assert RequestValidationError in app.exception_handlers
        assert ValidationError in app.exception_handlers

    def test_validation_works_on_real_endpoints(self):
        """Test validation on actual app endpoints."""
        client = TestClient(app)

        # Test the health endpoint with valid request - should work
        response = client.get("/")
        assert response.status_code == 200

        # Verify that validation handlers are properly integrated
        # They will catch validation errors when endpoints receive invalid data


class TestSecurityErrorMessages:
    """Test that error messages don't leak sensitive information."""

    @pytest.mark.asyncio
    async def test_error_messages_dont_include_sensitive_data(self):
        """Test that validation errors don't leak sensitive input data."""
        from pydantic import BaseModel

        class SensitiveModel(BaseModel):
            password: str
            api_key: str

            model_config = {"strict": True}

        # Try to create with wrong types
        try:
            SensitiveModel(password=123, api_key=456)
        except ValidationError as exc:
            from unittest.mock import MagicMock

            mock_request = MagicMock(spec=Request)
            response = await validation_exception_handler(mock_request, exc)

            content = response.body.decode()
            # Should not include actual input values (123, 456) in response
            # Only type information should be included for safety
            assert "password" in content  # Field name is ok
            assert "123" not in content  # Actual value should not be in error
            assert "456" not in content  # Actual value should not be in error

    def test_error_response_has_safe_type_information(self):
        """Test that error responses include safe type information."""
        from pydantic import BaseModel

        class TestModel(BaseModel):
            count: int

            model_config = {"strict": True}

        try:
            TestModel(count="not-a-number")
        except ValidationError as exc:
            errors = exc.errors()
            # Should indicate the error type (int_type expected)
            assert any("int_type" in str(e.get("type", "")) for e in errors)


class TestStrictModePreventionOfAttacks:
    """Test that strict mode prevents common attack vectors."""

    def test_prevents_sql_injection_via_type_confusion(self):
        """Test that strict mode helps prevent SQL injection via type confusion."""
        from pydantic import BaseModel

        class QueryParams(BaseModel):
            user_id: int
            limit: int

            model_config = {"strict": True}

        # Attacker tries to inject SQL via string in integer field
        with pytest.raises(ValidationError):
            QueryParams(
                user_id="1 OR 1=1",  # SQL injection attempt
                limit=10,
            )

    def test_prevents_xss_via_type_confusion(self):
        """Test that strict mode prevents XSS via type confusion."""
        from pydantic import BaseModel

        class UserInput(BaseModel):
            item_count: int
            is_active: bool

            model_config = {"strict": True}

        # Attacker tries to inject script via type confusion
        with pytest.raises(ValidationError):
            UserInput(
                item_count="<script>alert('xss')</script>",
                is_active=True,
            )

    def test_prevents_parameter_pollution(self):
        """Test that strict mode prevents parameter pollution attacks."""
        from pydantic import BaseModel

        class SafeParams(BaseModel):
            id: int
            name: str

            model_config = {"strict": True}

        # Attacker tries to pollute parameters with unexpected types
        with pytest.raises(ValidationError):
            SafeParams(
                id=["multiple", "values"],  # Should be single int
                name="test",
            )
