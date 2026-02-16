# LAYA Backbone - Development Seed Data Makefile
# This Makefile provides convenient commands for seeding development databases
# with sample data for both the AI service (Python/PostgreSQL) and Gibbon (PHP/MySQL).

.PHONY: help seed seed-reset seed-ai seed-ai-reset seed-gibbon seed-gibbon-reset

# Default target - show help
help:
	@echo "LAYA Development Seed Data Commands"
	@echo "===================================="
	@echo ""
	@echo "Available targets:"
	@echo "  make seed              - Seed both AI service and Gibbon databases"
	@echo "  make seed-reset        - Reset and seed both databases"
	@echo "  make seed-ai           - Seed AI service database only"
	@echo "  make seed-ai-reset     - Reset and seed AI service database"
	@echo "  make seed-gibbon       - Seed Gibbon database only"
	@echo "  make seed-gibbon-reset - Reset and seed Gibbon database"
	@echo ""
	@echo "Details:"
	@echo "  AI Service creates: 20 children, 15 families, 50+ activities,"
	@echo "                      activity participations, coaching sessions,"
	@echo "                      parent reports, and communication preferences"
	@echo ""
	@echo "  Gibbon creates: Organization structure, form groups, staff,"
	@echo "                  families, student enrollments, care records"
	@echo "                  (attendance, meals, naps, diapers, incidents)"
	@echo ""

# Seed both databases
seed: seed-ai seed-gibbon
	@echo ""
	@echo "========================================="
	@echo "✓ All databases seeded successfully!"
	@echo "========================================="
	@echo ""
	@echo "Next steps:"
	@echo "  - AI Service API: http://localhost:8000"
	@echo "  - API Docs: http://localhost:8000/docs"
	@echo "  - Gibbon UI: http://localhost:8080"
	@echo ""

# Reset and seed both databases
seed-reset: seed-ai-reset seed-gibbon-reset
	@echo ""
	@echo "========================================="
	@echo "✓ All databases reset and seeded!"
	@echo "========================================="
	@echo ""
	@echo "Next steps:"
	@echo "  - AI Service API: http://localhost:8000"
	@echo "  - API Docs: http://localhost:8000/docs"
	@echo "  - Gibbon UI: http://localhost:8080"
	@echo ""

# Seed AI service database
seed-ai:
	@echo "========================================="
	@echo "Seeding AI Service Database"
	@echo "========================================="
	@echo ""
	@cd ai-service && python scripts/seed.py
	@echo ""
	@echo "✓ AI service database seeded"
	@echo ""

# Reset and seed AI service database
seed-ai-reset:
	@echo "========================================="
	@echo "Resetting AI Service Database"
	@echo "========================================="
	@echo ""
	@echo "Step 1: Downgrading database to base..."
	@cd ai-service && alembic downgrade base
	@echo ""
	@echo "Step 2: Upgrading database to head..."
	@cd ai-service && alembic upgrade head
	@echo ""
	@echo "Step 3: Seeding database with sample data..."
	@cd ai-service && python scripts/seed.py
	@echo ""
	@echo "✓ AI service database reset and seeded"
	@echo ""

# Seed Gibbon database
seed-gibbon:
	@echo "========================================="
	@echo "Seeding Gibbon Database"
	@echo "========================================="
	@echo ""
	@php gibbon/modules/seed_data.php
	@echo ""
	@echo "✓ Gibbon database seeded"
	@echo ""

# Reset and seed Gibbon database
seed-gibbon-reset:
	@echo "========================================="
	@echo "Resetting Gibbon Database"
	@echo "========================================="
	@echo ""
	@php gibbon/modules/seed_data.php --reset
	@echo ""
	@echo "✓ Gibbon database reset and seeded"
	@echo ""
