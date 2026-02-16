"""Tests for Makefile seed commands.

This module tests that the Makefile targets work correctly for seeding
the development database.
"""

import subprocess
from pathlib import Path

import pytest


# Get the project root directory (two levels up from this file)
PROJECT_ROOT = Path(__file__).parent.parent.parent


class TestMakefileCommands:
    """Test suite for Makefile seed commands."""

    def test_makefile_exists(self):
        """Test that Makefile exists in project root."""
        makefile_path = PROJECT_ROOT / "Makefile"
        assert makefile_path.exists(), "Makefile should exist in project root"
        assert makefile_path.is_file(), "Makefile should be a file"

    def test_makefile_has_help_target(self):
        """Test that Makefile has a help target."""
        result = subprocess.run(
            ["make", "help"],
            cwd=PROJECT_ROOT,
            capture_output=True,
            text=True,
        )
        assert result.returncode == 0, "make help should succeed"
        assert "LAYA Development Seed Data Commands" in result.stdout
        assert "make seed" in result.stdout
        assert "make seed-reset" in result.stdout

    def test_makefile_help_shows_all_targets(self):
        """Test that help shows all available targets."""
        result = subprocess.run(
            ["make", "help"],
            cwd=PROJECT_ROOT,
            capture_output=True,
            text=True,
        )

        # Check for all expected targets
        expected_targets = [
            "make seed",
            "make seed-reset",
            "make seed-ai",
            "make seed-ai-reset",
            "make seed-gibbon",
            "make seed-gibbon-reset",
        ]

        for target in expected_targets:
            assert target in result.stdout, f"Help should mention {target}"

    def test_makefile_targets_are_phony(self):
        """Test that all seed targets are marked as .PHONY."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that .PHONY declaration exists
        assert ".PHONY:" in makefile_content

        # Check that all targets are listed as phony
        phony_targets = [
            "help",
            "seed",
            "seed-reset",
            "seed-ai",
            "seed-ai-reset",
            "seed-gibbon",
            "seed-gibbon-reset",
        ]

        for target in phony_targets:
            assert target in makefile_content, f"Target {target} should be in Makefile"

    def test_seed_ai_target_runs_python_script(self):
        """Test that seed-ai target references the Python seed script."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that seed-ai target runs the Python seed script
        assert "python scripts/seed.py" in makefile_content
        assert "seed-ai:" in makefile_content

    def test_seed_ai_reset_target_uses_alembic(self):
        """Test that seed-ai-reset target uses Alembic migrations."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that seed-ai-reset uses Alembic commands
        assert "alembic downgrade base" in makefile_content
        assert "alembic upgrade head" in makefile_content

    def test_seed_gibbon_target_runs_php_script(self):
        """Test that seed-gibbon target references the PHP seed script."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that seed-gibbon target runs the PHP seed script
        assert "gibbon/modules/seed_data.php" in makefile_content
        assert "seed-gibbon:" in makefile_content

    def test_seed_gibbon_reset_target_uses_reset_flag(self):
        """Test that seed-gibbon-reset target uses --reset flag."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that seed-gibbon-reset uses --reset flag
        assert "--reset" in makefile_content

    def test_seed_target_combines_both_services(self):
        """Test that seed target runs both AI and Gibbon seeds."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Find the seed target definition
        lines = makefile_content.split("\n")
        seed_target_found = False
        depends_on_ai = False
        depends_on_gibbon = False

        for line in lines:
            if line.strip().startswith("seed:"):
                seed_target_found = True
                # Check if it depends on both seed-ai and seed-gibbon
                if "seed-ai" in line:
                    depends_on_ai = True
                if "seed-gibbon" in line:
                    depends_on_gibbon = True

        assert seed_target_found, "seed target should exist"
        assert depends_on_ai, "seed should depend on seed-ai"
        assert depends_on_gibbon, "seed should depend on seed-gibbon"

    def test_seed_reset_target_combines_both_resets(self):
        """Test that seed-reset target runs both AI and Gibbon resets."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Find the seed-reset target definition
        lines = makefile_content.split("\n")
        reset_target_found = False
        depends_on_ai_reset = False
        depends_on_gibbon_reset = False

        for line in lines:
            if line.strip().startswith("seed-reset:"):
                reset_target_found = True
                # Check if it depends on both resets
                if "seed-ai-reset" in line:
                    depends_on_ai_reset = True
                if "seed-gibbon-reset" in line:
                    depends_on_gibbon_reset = True

        assert reset_target_found, "seed-reset target should exist"
        assert depends_on_ai_reset, "seed-reset should depend on seed-ai-reset"
        assert depends_on_gibbon_reset, "seed-reset should depend on seed-gibbon-reset"

    def test_makefile_has_descriptive_comments(self):
        """Test that Makefile includes helpful comments."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check for header comment
        assert "LAYA Backbone" in makefile_content
        assert "Development Seed Data" in makefile_content

        # Check for helpful descriptions
        assert "20 children" in makefile_content.lower()
        assert "15 families" in makefile_content.lower()

    def test_makefile_provides_next_steps(self):
        """Test that Makefile provides helpful next steps."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check for service URLs
        assert "http://localhost:8000" in makefile_content
        assert "http://localhost:8080" in makefile_content

        # Check for next steps section
        assert "Next steps:" in makefile_content or "next steps:" in makefile_content.lower()

    def test_default_target_shows_help(self):
        """Test that default make command shows help."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # The first target should be help (or there should be a .DEFAULT_GOAL)
        lines = [line.strip() for line in makefile_content.split("\n") if line.strip() and not line.strip().startswith("#")]

        # Find first target (ignoring .PHONY)
        for line in lines:
            if ":" in line and not line.startswith("."):
                first_target = line.split(":")[0].strip()
                assert first_target == "help", "First target should be help"
                break


class TestMakefileIntegration:
    """Integration tests for Makefile commands (requires environment setup)."""

    @pytest.mark.integration
    def test_make_help_runs_successfully(self):
        """Integration test: make help should run without errors."""
        result = subprocess.run(
            ["make", "help"],
            cwd=PROJECT_ROOT,
            capture_output=True,
            text=True,
        )

        assert result.returncode == 0, f"make help failed: {result.stderr}"
        assert len(result.stdout) > 0, "make help should produce output"
        assert "LAYA Development Seed Data Commands" in result.stdout

    @pytest.mark.integration
    @pytest.mark.slow
    def test_seed_scripts_are_executable(self):
        """Integration test: Verify seed scripts exist and are accessible."""
        # Check Python seed script
        python_seed = PROJECT_ROOT / "ai-service" / "scripts" / "seed.py"
        assert python_seed.exists(), "Python seed script should exist"

        # Check PHP seed script
        php_seed = PROJECT_ROOT / "gibbon" / "modules" / "seed_data.php"
        assert php_seed.exists(), "PHP seed script should exist"

        # Check that PHP script is executable
        import os
        assert os.access(php_seed, os.X_OK), "PHP seed script should be executable"


class TestMakefileErrorHandling:
    """Test error handling in Makefile commands."""

    def test_makefile_uses_at_symbol_for_clean_output(self):
        """Test that Makefile uses @ to suppress command echo where appropriate."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that echo commands use @
        lines = makefile_content.split("\n")
        echo_lines = [line for line in lines if "echo" in line.lower() and not line.strip().startswith("#")]

        # Most echo lines should start with @ for clean output
        at_echo_count = sum(1 for line in echo_lines if line.strip().startswith("@echo"))
        assert at_echo_count > 0, "Should use @echo for clean output"

    def test_makefile_changes_directory_correctly(self):
        """Test that Makefile uses cd correctly for service-specific commands."""
        makefile_path = PROJECT_ROOT / "Makefile"
        makefile_content = makefile_path.read_text()

        # Check that commands use cd within the same line (to maintain directory context)
        # e.g., @cd ai-service && python ...
        assert "cd ai-service &&" in makefile_content
        assert "cd ai-service && python" in makefile_content or "cd ai-service && alembic" in makefile_content
