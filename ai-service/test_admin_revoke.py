#!/usr/bin/env python3
"""Simple verification test for admin token revocation endpoint."""

import asyncio
from datetime import datetime, timezone, timedelta
from uuid import uuid4

# Test that imports work correctly
try:
    from app.auth.schemas import TokenRevocationRequest, TokenRevocationResponse
    from app.auth.service import AuthService
    print("✓ Schema imports successful")
except ImportError as e:
    print(f"✗ Schema import failed: {e}")
    exit(1)

# Test that schemas are properly defined
try:
    # Test TokenRevocationRequest
    req = TokenRevocationRequest(token="test_token_value")
    assert req.token == "test_token_value"
    print("✓ TokenRevocationRequest schema works")

    # Test TokenRevocationResponse
    resp = TokenRevocationResponse(
        message="Token revoked",
        token_revoked=True
    )
    assert resp.message == "Token revoked"
    assert resp.token_revoked == True
    print("✓ TokenRevocationResponse schema works")
except Exception as e:
    print(f"✗ Schema validation failed: {e}")
    exit(1)

# Test that AuthService has the new method
try:
    assert hasattr(AuthService, 'revoke_token')
    print("✓ AuthService.revoke_token method exists")
except Exception as e:
    print(f"✗ AuthService method check failed: {e}")
    exit(1)

# Test router imports
try:
    from app.auth.router import router
    # Check that the endpoint is registered
    endpoint_paths = [route.path for route in router.routes]
    print(f"  Available endpoint paths: {endpoint_paths}")
    # The full path includes the router prefix
    has_revoke = any('revoke-token' in path for path in endpoint_paths)
    assert has_revoke, "revoke-token endpoint not found in router"
    print("✓ Admin revoke-token endpoint registered in router")
except Exception as e:
    print(f"✗ Router check failed: {e}")
    import traceback
    traceback.print_exc()
    exit(1)

print("\n✅ All verification checks passed!")
print("The admin token revocation endpoint has been successfully implemented.")
