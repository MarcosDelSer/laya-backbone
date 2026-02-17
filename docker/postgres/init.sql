-- LAYA Kindergarten & Childcare Management Platform
-- PostgreSQL 12 Initialization Script for AI Service
--
-- This script runs on first container startup to configure the database.
-- It is mounted at /docker-entrypoint-initdb.d/init.sql
--
-- Requirements:
-- - PostgreSQL 12+ for AI Service (asyncpg compatibility)
-- - UTF8 encoding for full Unicode support
-- - Proper collation for multi-language (EN/FR) Quebec compliance

-- Enable required extensions for AI Service functionality
-- UUID generation for primary keys
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Enable pg_trgm for fuzzy text search (activity recommendations, etc.)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Enable btree_gin for combined indexes (performance optimization)
CREATE EXTENSION IF NOT EXISTS btree_gin;

-- Create schema for LAYA AI Service tables
-- Keeps AI tables organized and separate from system tables
CREATE SCHEMA IF NOT EXISTS laya;

-- Set default search path to include laya schema
ALTER DATABASE laya_ai SET search_path TO laya, public;

-- Grant all privileges on laya schema to laya user
GRANT ALL PRIVILEGES ON SCHEMA laya TO laya;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA laya TO laya;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA laya TO laya;

-- Set default privileges for future objects
ALTER DEFAULT PRIVILEGES IN SCHEMA laya
    GRANT ALL PRIVILEGES ON TABLES TO laya;
ALTER DEFAULT PRIVILEGES IN SCHEMA laya
    GRANT ALL PRIVILEGES ON SEQUENCES TO laya;

-- Configure timezone support (required for proper datetime handling)
SET timezone = 'UTC';

-- Create enum types for AI Service
-- Activity status enum
DO $$ BEGIN
    CREATE TYPE laya.activity_status AS ENUM ('pending', 'active', 'completed', 'archived');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Coaching session status enum
DO $$ BEGIN
    CREATE TYPE laya.session_status AS ENUM ('scheduled', 'in_progress', 'completed', 'cancelled');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Analytics report type enum
DO $$ BEGIN
    CREATE TYPE laya.report_type AS ENUM ('daily', 'weekly', 'monthly', 'quarterly', 'annual');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Log successful initialization
DO $$
BEGIN
    RAISE NOTICE 'LAYA PostgreSQL initialization complete for AI Service';
END $$;
