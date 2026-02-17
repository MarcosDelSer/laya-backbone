#!/usr/bin/env python3
"""Test script to verify SettingsConfigDict migration."""

import sys
sys.path.insert(0, './ai-service')

from app.config import Settings

# Test instantiation
config = Settings()

# Verify model_config exists
if hasattr(Settings, 'model_config'):
    print("✓ model_config attribute found")
else:
    print("✗ model_config attribute NOT found")
    sys.exit(1)

# Verify Config class doesn't exist anymore
if hasattr(Settings, 'Config'):
    print("✗ Old Config class still exists (should be removed)")
    sys.exit(1)
else:
    print("✓ Old Config class removed")

# Verify settings can be instantiated
print(f"✓ Settings instantiated successfully")
print(f"✓ Environment: {config.environment}")

print("\nSettingsConfigDict migration successful")
