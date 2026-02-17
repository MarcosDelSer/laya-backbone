"""HTTP client utilities with automatic correlation ID propagation.

This module provides HTTP client helpers that automatically propagate
request and correlation IDs to downstream services for distributed tracing.
"""

from typing import Any, Dict, Optional, Union

import httpx

from app.core.context import get_correlation_id, get_request_id
from app.core.logging import get_logger

logger = get_logger(__name__)


def get_trace_headers() -> Dict[str, str]:
    """Get headers for distributed tracing.

    This function retrieves the current request and correlation IDs
    from the context and returns them as headers that can be added
    to outbound HTTP requests.

    Returns:
        Dict[str, str]: Headers containing X-Request-ID and X-Correlation-ID
    """
    headers: Dict[str, str] = {}

    request_id = get_request_id()
    if request_id:
        headers["X-Request-ID"] = request_id

    correlation_id = get_correlation_id()
    if correlation_id:
        headers["X-Correlation-ID"] = correlation_id

    return headers


class TracedAsyncClient(httpx.AsyncClient):
    """HTTP client that automatically propagates correlation IDs.

    This client extends httpx.AsyncClient to automatically add
    request and correlation ID headers to all outbound requests.

    Example:
        >>> async with TracedAsyncClient() as client:
        ...     response = await client.get("http://api.example.com/endpoint")
        ...     # Request automatically includes X-Request-ID and X-Correlation-ID
    """

    async def request(
        self,
        method: str,
        url: Union[httpx.URL, str],
        *,
        headers: Optional[Dict[str, str]] = None,
        **kwargs: Any,
    ) -> httpx.Response:
        """Make an HTTP request with automatic trace header propagation.

        Args:
            method: HTTP method (GET, POST, etc.)
            url: Target URL
            headers: Optional headers dict (trace headers will be added)
            **kwargs: Additional arguments passed to httpx.AsyncClient.request

        Returns:
            httpx.Response: The HTTP response
        """
        # Get trace headers
        trace_headers = get_trace_headers()

        # Merge with provided headers (provided headers take precedence)
        if headers:
            merged_headers = {**trace_headers, **headers}
        else:
            merged_headers = trace_headers

        # Log outbound request
        request_logger = logger.bind(**trace_headers)
        request_logger.info(
            "Outbound request",
            method=method,
            url=str(url),
        )

        # Make the request with trace headers
        response = await super().request(
            method,
            url,
            headers=merged_headers,
            **kwargs,
        )

        # Log response
        request_logger.info(
            "Outbound request completed",
            status_code=response.status_code,
        )

        return response


async def make_traced_request(
    method: str,
    url: str,
    **kwargs: Any,
) -> httpx.Response:
    """Make a traced HTTP request using a context manager.

    This is a convenience function for making single HTTP requests
    with automatic trace header propagation.

    Args:
        method: HTTP method (GET, POST, etc.)
        url: Target URL
        **kwargs: Additional arguments passed to httpx.AsyncClient.request

    Returns:
        httpx.Response: The HTTP response

    Example:
        >>> response = await make_traced_request(
        ...     "GET",
        ...     "http://api.example.com/endpoint",
        ...     timeout=10.0
        ... )
    """
    async with TracedAsyncClient() as client:
        return await client.request(method, url, **kwargs)
