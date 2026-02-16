# LAYA - Implementation Plan for Missing Requirements

## Overview

This plan defines the implementation of all missing functionality identified in the gap analysis. Tasks are designed for maximum parallelism with explicit dependency chains.

---

## Dependency Graph

```
Layer 0 (No Dependencies - Start Immediately)
=============================================
[020] Finance Module Gibbon Install     [022] Auth: AI Service Login & JWT
[024] AISync Webhook Wiring             [029] LLM Integration Service
[030] i18n Framework (Parent Portal)    [031] File Upload & Storage Service
[036] Error Handling & Logging          [038] CI/CD Pipeline Setup
[039] API Pagination & Search           [040] Redis Caching Layer

Layer 1 (Depends on Layer 0)
============================
[021] RL-24 PDF Generation          -> depends on [020]
[023] Auth: Gibbon-AI Token Bridge  -> depends on [022]
[025] Parent Portal Auth Flow       -> depends on [022]
[026] Parent Portal API Integration -> depends on [022, 030]
[027] Teacher App Auth & API        -> depends on [022]
[028] Parent App Auth & API         -> depends on [022]
[032] Push Notification Wiring      -> depends on [024]
[033] Invoice PDF Generation        -> depends on [020]
[035] Security Hardening            -> depends on [022]
[037] Database Seeding              -> depends on [020, 024]

Layer 2 (Depends on Layer 1)
============================
[034] Document E-Signature Backend  -> depends on [031, 025]
[041] Health Checks & Monitoring    -> depends on [036]
[042] Backup & Recovery             -> (standalone)
[043] Performance Optimization      -> depends on [039, 040]
[044] Accessibility Audit           -> depends on [026]
[045] User Onboarding Wizard        -> depends on [020, 022, 037]
```

---

## Task Definitions

### LAYER 0 - Parallelizable Foundation Tasks

#### Task 020: Enhanced Finance Module - Gibbon Installation
- **Service:** gibbon
- **Scope:** Install EnhancedFinance module from /modules/EnhancedFinance/ into gibbon/modules/, create missing entry pages (contracts, payments, settings, RL-24 management), register module in Gibbon
- **Effort:** Medium
- **Tests:** PHPUnit for all gateways + Playwright browser tests for each page

#### Task 022: Authentication - AI Service Login & JWT Generation
- **Service:** ai-service
- **Scope:** Login endpoint, JWT token generation/refresh, password hashing, role-based decorators, user model
- **Effort:** Medium
- **Tests:** pytest for auth endpoints + integration tests

#### Task 024: AISync Webhook Wiring
- **Service:** gibbon (CareTracking, PhotoManagement modules)
- **Scope:** Add webhook fire calls to all CRUD operations in CareTracking and PhotoManagement, implement cron-based retry queue processor
- **Effort:** Medium
- **Tests:** PHPUnit mocking webhook calls + integration test with AI service

#### Task 029: LLM Integration Service
- **Service:** ai-service
- **Scope:** Abstract LLM provider client, prompt templates for activity recommendations/daily reports/coaching, response parsing, token tracking, caching, fallback
- **Effort:** High
- **Tests:** pytest with mocked LLM responses + integration tests

#### Task 030: i18n Framework - Parent Portal
- **Service:** parent-portal
- **Scope:** Install next-intl, create EN/FR translation files, language switcher component, locale-aware formatting
- **Effort:** Medium
- **Tests:** Vitest for translation rendering + Playwright for language switching

#### Task 031: File Upload & Storage Service
- **Service:** ai-service
- **Scope:** Multipart upload endpoint, local/S3 storage backend, thumbnail generation, secure URL generation
- **Effort:** Medium
- **Tests:** pytest for upload/download endpoints + file cleanup tests

#### Task 036: Error Handling & Structured Logging
- **Service:** ai-service, parent-portal
- **Scope:** Error middleware, structured JSON logging, request ID propagation, error response standardization
- **Effort:** Low-Medium
- **Tests:** pytest for error middleware + log output verification

#### Task 038: CI/CD Pipeline Setup
- **Service:** .github/workflows
- **Scope:** GitHub Actions for lint, test, build across all services; PR check requirements
- **Effort:** Medium
- **Tests:** Pipeline validation via test commits

#### Task 039: API Pagination & Search
- **Service:** ai-service
- **Scope:** Pagination middleware (cursor + offset), search endpoints, filter/sort parameters, response metadata
- **Effort:** Medium
- **Tests:** pytest for pagination edge cases, search relevance

#### Task 040: Redis Caching Layer
- **Service:** ai-service
- **Scope:** Redis client integration, cache decorator, invalidation on writes, TTL configuration
- **Effort:** Low-Medium
- **Tests:** pytest with redis mock, cache hit/miss verification

---

### LAYER 1 - Dependent Tasks

#### Task 021: RL-24 PDF Generation & Export
- **Depends on:** [020]
- **Service:** gibbon (EnhancedFinance module)
- **Scope:** TCPDF/mPDF integration, official RL-24 form layout, batch generation, download/email delivery
- **Effort:** High
- **Tests:** PHPUnit for PDF content + visual regression tests

#### Task 023: Authentication - Gibbon-AI Token Bridge
- **Depends on:** [022]
- **Service:** gibbon, ai-service
- **Scope:** Gibbon session -> JWT token exchange, shared auth middleware, role synchronization
- **Effort:** Medium
- **Tests:** Integration tests for cross-service auth

#### Task 025: Parent Portal - Auth Flow
- **Depends on:** [022]
- **Service:** parent-portal
- **Scope:** Login page, registration, token storage, protected routes middleware, logout, password reset UI
- **Effort:** Medium
- **Tests:** Vitest for auth components + Playwright for login flow

#### Task 026: Parent Portal - Real API Integration
- **Depends on:** [022, 030]
- **Service:** parent-portal
- **Scope:** Replace mock data with real API calls to ai-service and Gibbon, error handling, loading states, real-time updates
- **Effort:** High
- **Tests:** Vitest with MSW mocks + Playwright E2E

#### Task 027: Teacher App - Auth & Real API Integration
- **Depends on:** [022]
- **Service:** teacher-app
- **Scope:** Login screen, secure token storage (Keychain/Keystore), real API calls for attendance/meals/photos, offline caching
- **Effort:** High
- **Tests:** Jest for API integration + Detox E2E (optional)

#### Task 028: Parent App - Auth & Real API Integration
- **Depends on:** [022]
- **Service:** parent-app
- **Scope:** Login screen, secure token storage, real API calls for daily feed/photos/messages/invoices, pull-to-refresh
- **Effort:** High
- **Tests:** Jest for API integration + Detox E2E (optional)

#### Task 032: Push Notification End-to-End Wiring
- **Depends on:** [024]
- **Service:** gibbon (NotificationEngine), teacher-app, parent-app
- **Scope:** FCM server SDK integration, event-to-notification mapping, preference enforcement, mobile token registration, notification inbox
- **Effort:** High
- **Tests:** PHPUnit for notification dispatch + mobile integration tests

#### Task 033: Invoice PDF Generation
- **Depends on:** [020]
- **Service:** gibbon (EnhancedFinance)
- **Scope:** Invoice PDF template, generation endpoint, print CSS, batch generation, email delivery
- **Effort:** Medium
- **Tests:** PHPUnit for PDF content + visual tests

#### Task 035: Security Hardening
- **Depends on:** [022]
- **Service:** ai-service, parent-portal, gibbon
- **Scope:** CORS lockdown, rate limiting, input validation middleware, XSS/CSRF protection, secrets management, HTTPS config
- **Effort:** Medium
- **Tests:** pytest security tests + OWASP ZAP scan

#### Task 037: Database Seeding & Migration Verification
- **Depends on:** [020, 024]
- **Service:** ai-service, gibbon
- **Scope:** Seed data scripts, Alembic migration verification, CHANGEDB.php execution, pilot onboarding scripts
- **Effort:** Medium
- **Tests:** Seed script idempotency tests

---

### LAYER 2 - Final Integration Tasks

#### Task 034: Document E-Signature Backend
- **Depends on:** [031, 025]
- **Service:** ai-service, parent-portal
- **Scope:** Document model, signature storage, status tracking, audit trail, template management
- **Effort:** Medium
- **Tests:** pytest for document lifecycle + Playwright for signing flow

#### Task 041: Health Checks & Monitoring
- **Depends on:** [036]
- **Service:** All services
- **Scope:** Health endpoints, uptime monitoring, connection pool monitoring, queue depth monitoring, alerting
- **Effort:** Low
- **Tests:** pytest/Vitest for health endpoints

#### Task 042: Backup & Recovery Scripts
- **Depends on:** None (standalone)
- **Service:** Infrastructure
- **Scope:** MySQL/PostgreSQL backup scripts, cron scheduling, restore verification, retention policy
- **Effort:** Low
- **Tests:** Backup/restore round-trip test

#### Task 043: Performance Optimization
- **Depends on:** [039, 040]
- **Service:** ai-service, parent-portal
- **Scope:** Index optimization, N+1 detection, image lazy loading, bundle size reduction, gzip compression
- **Effort:** Medium
- **Tests:** Load testing with locust/k6, Lighthouse scores

#### Task 044: Accessibility Audit & Fixes
- **Depends on:** [026]
- **Service:** parent-portal
- **Scope:** Semantic HTML, ARIA labels, keyboard navigation, screen reader testing, color contrast
- **Effort:** Medium
- **Tests:** axe-core automated testing + Playwright accessibility checks

#### Task 045: User Onboarding Wizard
- **Depends on:** [020, 022, 037]
- **Service:** gibbon, parent-portal
- **Scope:** First-run wizard, guided config, sample data import, admin creation, service verification
- **Effort:** Medium
- **Tests:** Playwright E2E for wizard flow

---

---

### LAYER 3+ - Drive Requirements (Quebec Functional Spec)

The following tasks were identified from the Google Drive "Document de cadrage fonctionnel" and skeleton forms (Entente de Services, Fiche d'Inscription, RL-24, medical protocols, etc.):

#### Task 046: Child Registration Form (Fiche d'Inscription)
- **Depends on:** [025]
- **Service:** gibbon, parent-portal
- **Scope:** Complete digital enrollment form with Quebec-required fields: child ID, parent 1&2, authorized pickups, emergency contacts, health (allergies, medical history, EpiPen), nutrition (dietary restrictions), attendance pattern, e-signatures, audit trail, PDF export
- **Effort:** High

#### Task 047: Service Agreement Digitization (Entente de Services FO-0659)
- **Depends on:** [025, 046]
- **Service:** gibbon, parent-portal
- **Scope:** Full 13-article Quebec service agreement + Annexes A-D, payment terms, Consumer Protection Act notice, e-signature workflow, PDF generation
- **Effort:** High

#### Task 048: Development Profile Module (Portrait de Développement)
- **Depends on:** [024, 029]
- **Service:** ai-service, gibbon, parent-portal
- **Scope:** 6-domain developmental tracking (Affective, Social, Language, Cognitive, Gross Motor, Fine Motor), monthly snapshots, growth trajectory alerts, parent contribution, PDF export
- **Effort:** High

#### Task 049: Intervention Plan Module
- **Depends on:** [048]
- **Service:** ai-service, gibbon, parent-portal
- **Scope:** 8-part SMART intervention plans, versioning, progress documentation, parent signature, specialist referral tracking
- **Effort:** High

#### Task 050: Educational Portfolio
- **Depends on:** [031, 048]
- **Service:** ai-service, parent-portal
- **Scope:** Photo/video/work samples, chronological timeline, domain filtering, privacy controls, PDF portfolio export
- **Effort:** Medium

#### Task 051: Medical Protocol Module
- **Depends on:** [046]
- **Service:** gibbon, parent-portal
- **Scope:** Acetaminophen protocol (FO-0647) with weight-based dosing table and temperature standards; Insect repellent protocol (FO-0646); consent forms, administration logging, expiration tracking
- **Effort:** High

#### Task 052: Allergy & Medical Tracking
- **Depends on:** [046]
- **Service:** gibbon, ai-service, parent-portal
- **Scope:** Multi-type allergy profiles, accommodation plans, auto-flag allergens in meals, emergency response checklists, staff training tracking
- **Effort:** High

#### Task 053: Incident Report System
- **Depends on:** [046]
- **Service:** gibbon, parent-portal
- **Scope:** Structured incident reports, parent notification triggers, pattern detection, intervention plan linking, photo documentation, PDF export
- **Effort:** Medium

#### Task 054: AI Message Quality Coach
- **Depends on:** [029]
- **Service:** ai-service, parent-portal
- **Scope:** "Bonne message" standards enforcement, accusatory language detection, rewrite suggestions, quality scoring, message templates
- **Effort:** High

#### Task 055: HR & Staff Management
- **Depends on:** None
- **Service:** gibbon, parent-portal
- **Scope:** Staff files, schedule editor, time tracking, certification management, staff-to-child ratio monitoring (Quebec ratios)
- **Effort:** High

#### Task 056: Government Document Verification
- **Depends on:** [046]
- **Service:** gibbon, parent-portal
- **Scope:** Required document checklist, status tracking, auto-notifications, immigration documentation, service agreement blocking
- **Effort:** Medium

#### Task 057: Attendance Sheet Module (Fiche d'Assiduité LOB 2024)
- **Depends on:** [046]
- **Service:** gibbon, parent-portal
- **Scope:** LOB 2024 format, weekly grid, parent e-signature, real-time dashboard, PDF/Excel export, 7-year archive
- **Effort:** High

#### Task 058: RL-24 XML Government Submission
- **Depends on:** [021]
- **Service:** gibbon
- **Scope:** XML format per Revenu Québec spec, schema validation, eligibility form FO-0601, batch generation
- **Effort:** High

#### Task 059: Accounting Software Export
- **Depends on:** [020]
- **Service:** gibbon
- **Scope:** Sage 50/QuickBooks export, expense tracking, financial dashboard, aging reports
- **Effort:** Medium

#### Task 060: RBAC Roles & Permissions
- **Depends on:** [022, 023]
- **Service:** gibbon, ai-service, parent-portal
- **Scope:** 5-role system (Director, Educator, Accountant, Parent, Staff), group-level restrictions, approval workflows
- **Effort:** High

#### Task 061: MFA for Admin Accounts
- **Depends on:** [022]
- **Service:** gibbon, ai-service
- **Scope:** TOTP, backup codes, session timeout, IP whitelist
- **Effort:** Medium

#### Task 062: Audit Trail & Activity Logging
- **Depends on:** None
- **Service:** ai-service, gibbon
- **Scope:** Complete modification tracking, Quebec retention compliance, data access logging
- **Effort:** Medium

#### Task 063: Meal Menu Management
- **Depends on:** [046]
- **Service:** gibbon, parent-portal
- **Scope:** Weekly menu editor, per-child consumption tracking, allergen cross-reference, nutrition reports
- **Effort:** Medium

#### Task 064: Real-Time Director Dashboard
- **Depends on:** [024, 026]
- **Service:** parent-portal, ai-service
- **Scope:** Live occupancy, ratio compliance, late pickups, financial balances, alert system, KPI trends
- **Effort:** High

#### Task 065: Parent Portal Messaging
- **Depends on:** [026, 054]
- **Service:** parent-portal, ai-service
- **Scope:** Educator-parent messaging, notification inbox, AI quality coaching integration, private/shared notes
- **Effort:** High

---

## Execution Strategy

**Phase A (Weeks 1-2):** Execute all Layer 0 tasks in parallel (12 tasks: 020, 022, 024, 029, 030, 031, 036, 038, 039, 040, 055, 062)

**Phase B (Weeks 2-4):** Execute Layer 1 tasks as dependencies complete (12 tasks: 021, 023, 025, 027, 028, 032, 033, 035, 054, 059, 060, 061)

**Phase C (Weeks 4-6):** Execute Layer 2 tasks (7 tasks: 026, 037, 046, 058, 047 partial)

**Phase D (Weeks 6-8):** Execute Layer 3 tasks (12 tasks: 047, 048, 050, 051, 052, 053, 056, 057, 063, 064, 065)

**Phase E (Weeks 8-10):** Execute Layer 4 tasks (6 tasks: 034, 049, 041, 043, 044, 045)

**Total: 46 tasks (020-065) covering all identified gaps from the implementation plan AND the Quebec functional specification.**

All tasks include unit tests, integration tests, and AI QA specifications for Playwright (frontend) or pytest/PHPUnit (backend) validation. Every task spec includes a DEPENDENCIES section for Auto Claude execution ordering.
