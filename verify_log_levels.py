#!/usr/bin/env python3
"""Verification script for log levels implementation.

This script demonstrates that all log levels (DEBUG/INFO/WARNING/ERROR/CRITICAL)
are properly implemented and working.
"""

import sys
import os
from io import StringIO
import json

# Add ai-service to path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'ai-service'))

from app.core.logging import configure_logging, get_logger


def verify_log_levels():
    """Verify all log levels are implemented and working."""
    print("Verifying log levels implementation...")
    print("=" * 60)

    # Test 1: Verify all log levels can be logged
    print("\n1. Testing all log levels (DEBUG/INFO/WARNING/ERROR/CRITICAL):")
    output = StringIO()
    configure_logging(log_level="DEBUG", json_logs=True, stream=output)

    logger = get_logger("test_logger")

    # Log at all levels
    logger.debug("This is a DEBUG message", level_num=10)
    logger.info("This is an INFO message", level_num=20)
    logger.warning("This is a WARNING message", level_num=30)
    logger.error("This is an ERROR message", level_num=40)
    logger.critical("This is a CRITICAL message", level_num=50)

    # Parse output
    log_lines = [line for line in output.getvalue().strip().split('\n') if line]

    if len(log_lines) == 5:
        print("   ✓ All 5 log levels generated output")
    else:
        print(f"   ✗ Expected 5 log lines, got {len(log_lines)}")
        return False

    # Verify each level
    levels_found = set()
    for line in log_lines:
        try:
            log_data = json.loads(line)
            level = log_data.get('level')
            levels_found.add(level)
            print(f"   ✓ Found level: {level.upper()}")
        except json.JSONDecodeError:
            print(f"   ✗ Failed to parse JSON log line")
            return False

    expected_levels = {'debug', 'info', 'warning', 'error', 'critical'}
    if levels_found == expected_levels:
        print("   ✓ All expected log levels present")
    else:
        print(f"   ✗ Missing levels: {expected_levels - levels_found}")
        return False

    # Test 2: Verify log level filtering
    print("\n2. Testing log level filtering (WARNING and above):")
    output2 = StringIO()
    configure_logging(log_level="WARNING", json_logs=True, stream=output2)

    logger2 = get_logger("test_logger2")
    logger2.debug("DEBUG - should not appear")
    logger2.info("INFO - should not appear")
    logger2.warning("WARNING - should appear")
    logger2.error("ERROR - should appear")
    logger2.critical("CRITICAL - should appear")

    log_output = output2.getvalue()
    log_lines2 = [line for line in log_output.strip().split('\n') if line]

    if len(log_lines2) == 3:
        print("   ✓ Only WARNING, ERROR, CRITICAL logged (3 lines)")
    else:
        print(f"   ✗ Expected 3 log lines, got {len(log_lines2)}")
        return False

    if "DEBUG" not in log_output and "INFO" not in log_output:
        print("   ✓ DEBUG and INFO correctly filtered out")
    else:
        print("   ✗ DEBUG or INFO incorrectly included")
        return False

    # Test 3: Verify configure_logging accepts all standard levels
    print("\n3. Testing configure_logging accepts all log level strings:")
    test_levels = ["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"]
    for level in test_levels:
        try:
            output_test = StringIO()
            configure_logging(log_level=level, json_logs=True, stream=output_test)
            print(f"   ✓ Level '{level}' accepted")
        except Exception as e:
            print(f"   ✗ Level '{level}' failed: {e}")
            return False

    print("\n" + "=" * 60)
    print("✓ ALL VERIFICATIONS PASSED")
    print("\nLog levels (DEBUG/INFO/WARNING/ERROR/CRITICAL) are fully")
    print("implemented and working correctly!")
    print("=" * 60)
    return True


if __name__ == "__main__":
    try:
        success = verify_log_levels()
        sys.exit(0 if success else 1)
    except Exception as e:
        print(f"\n✗ Verification failed with error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
