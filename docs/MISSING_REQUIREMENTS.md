# LAYA - Gap Analysis: Missing Requirements

## Summary

This document compares the LAYA Implementation Plan requirements against the current codebase state to identify all missing functionality. Items are categorized by priority and service.

---

## Critical Gaps (Blocks pilot launch)

### GAP-01: Enhanced Finance Module - Gibbon Integration
- **Status:** Module code exists in `/modules/EnhancedFinance/` but is NOT installed in `gibbon/modules/`
- **Missing:**
  - Module installation into Gibbon (copy to gibbon/modules/EnhancedFinance/)
  - Database table creation via CHANGEDB.php
  - Module registration in Gibbon's module system
  - Missing pages: `finance_contracts.php` (contract CRUD), `finance_payment_add.php` (standalone payment recording), `finance_settings.php` (provider config), `finance_releve24.php` (RL-24 management), `finance_releve24_batch.php` (batch generation)
  - Finance CSS styling (`css/module.css`)
- **Impact:** Cannot manage invoices, payments, contracts, or generate RL-24 tax slips

### GAP-02: RL-24 PDF Generation & Export
- **Status:** Calculation logic exists in `Releve24.php` but no PDF rendering
- **Missing:**
  - PDF generation using TCPDF or mPDF library
  - Official RL-24 form layout matching Quebec government format
  - Batch PDF generation for all children in a tax year
  - Download/print functionality
  - Batch email delivery to parents
- **Impact:** Cannot produce legally required tax documents

### GAP-03: Authentication & Authorization System
- **Status:** JWT verification scaffolded in ai-service (`auth.py`), but no token generation, login, or role management
- **Missing:**
  - Login endpoint (username/password -> JWT token)
  - Token refresh mechanism
  - Role-based access control (admin, teacher, parent roles)
  - Session management across services
  - Gibbon auth integration (SSO or token bridge)
  - Parent portal login/registration flow
  - Mobile app authentication flow
  - Password reset functionality
- **Impact:** No secure access to any service; all endpoints effectively unprotected

### GAP-04: Gibbon <-> AI Service Data Sync (AISync Wiring)
- **Status:** AISync module created with database tables and settings, but webhook triggers NOT wired into source modules
- **Missing:**
  - CareTracking module: fire webhooks on activity/meal/nap/attendance events
  - PhotoManagement module: fire webhooks on photo upload/tag events
  - NotificationEngine: fire webhooks on notification delivery events
  - AI Service webhook receiver: process incoming events and update local models
  - Retry queue processing (cron job or background worker)
  - Health monitoring and alerting for sync failures
- **Impact:** AI Service has no real data - all recommendations/reports based on nothing

### GAP-05: Parent Portal Real API Integration
- **Status:** Portal has complete UI but uses placeholder/mock data patterns
- **Missing:**
  - Authentication flow (login page, token management, protected routes)
  - Real API calls to ai-service for daily reports, activities, coaching
  - Real API calls to Gibbon for child profiles, attendance, meals, photos
  - Error handling for API failures
  - Loading states and offline fallbacks
  - Real-time updates (WebSocket or polling)
- **Impact:** Parent portal shows static/fake content

### GAP-06: Mobile Apps Real API Integration
- **Status:** Both teacher-app and parent-app have UI screens but limited real API connectivity
- **Missing:**
  - Auth flow implementation (login screens, secure token storage)
  - API client configuration pointing to real endpoints
  - Real data fetching for all screens (attendance, meals, photos, reports)
  - Offline data caching and sync
  - Push notification token registration with backend
  - Image upload from camera/gallery to PhotoManagement API
  - Pull-to-refresh with real data invalidation
- **Impact:** Mobile apps are UI shells without backend connectivity

### GAP-07: AI/LLM Integration for Intelligence Features
- **Status:** AI service has business logic services but no actual LLM API calls
- **Missing:**
  - LLM provider integration (OpenAI/Claude API) for:
    - Activity recommendation reasoning and personalization
    - Daily report narrative generation (bilingual EN/FR)
    - Home activity suggestion generation
    - Special needs coaching guidance synthesis
  - Prompt engineering templates for each use case
  - Token usage tracking and cost management
  - Fallback logic when LLM is unavailable
  - Response caching to reduce API costs
  - Content safety filtering for generated text
- **Impact:** AI features return hardcoded/templated responses instead of intelligent ones

---

## High Priority Gaps (Required for pilot quality)

### GAP-08: Bilingual (EN/FR) Interface Framework
- **Status:** Backend supports bilingual daily reports; frontend has no i18n setup
- **Missing:**
  - i18n framework for parent-portal (next-intl or similar)
  - French translation files for all UI strings
  - Language switcher component
  - Locale-aware date/currency formatting (Quebec uses DD/MM/YYYY, $X,XXX.XX)
  - French translations for mobile apps
- **Impact:** Fails Quebec bilingual compliance for parent-facing interfaces

### GAP-09: File Upload & Storage Service
- **Status:** PhotoManagement module has DB schema but no actual file handling
- **Missing:**
  - File upload endpoint (multipart/form-data)
  - Storage backend (local disk for dev, S3-compatible for production)
  - Image resizing/thumbnail generation
  - Secure URL generation for photo access
  - Storage quota management
  - Cleanup job for orphaned files
- **Impact:** Cannot upload or view photos

### GAP-10: Push Notification End-to-End Wiring
- **Status:** NotificationEngine module exists with FCM support; FCM not connected to events
- **Missing:**
  - FCM server-side SDK integration (kreait/firebase-php)
  - Event-to-notification mapping (attendance check-in -> parent notification)
  - Notification preference enforcement (respect user channel choices)
  - Mobile app FCM token registration with backend
  - Notification history/inbox in parent portal and mobile apps
  - Batch notification processing (cron-based queue worker)
- **Impact:** No push notifications reach parents or teachers

### GAP-11: Invoice PDF Generation & Printing
- **Status:** Invoice data and UI exist but no PDF/print output
- **Missing:**
  - Invoice PDF template with daycare branding
  - PDF generation endpoint
  - Print-friendly CSS for browser printing
  - Batch invoice generation (monthly for all families)
  - Email delivery of invoice PDFs
- **Impact:** Cannot provide professional invoices to parents

### GAP-12: Document E-Signature Backend
- **Status:** SignatureCanvas component exists in parent-portal; no backend persistence
- **Missing:**
  - Document model in ai-service or Gibbon
  - Signature image storage
  - Document status tracking (pending, signed, expired)
  - Signature verification/audit trail
  - Document template management for common forms
- **Impact:** Cannot collect legally binding electronic signatures

### GAP-13: Security Hardening
- **Status:** Basic CORS (allow all origins), no rate limiting, minimal input validation
- **Missing:**
  - CORS restriction to known origins
  - Rate limiting on API endpoints (especially auth)
  - Input sanitization and validation middleware
  - SQL injection prevention audit
  - XSS protection headers
  - CSRF tokens for Gibbon forms
  - Secrets management (no hardcoded keys)
  - HTTPS enforcement configuration
- **Impact:** Platform vulnerable to common web attacks

---

## Medium Priority Gaps (Polish for pilot)

### GAP-14: Error Handling & Structured Logging
- **Missing:**
  - Centralized error handling middleware (ai-service)
  - Structured JSON logging across all services
  - Error tracking integration (Sentry or similar)
  - Request ID propagation for distributed tracing
  - Log rotation and retention policies
- **Impact:** Debugging production issues will be extremely difficult

### GAP-15: Database Seeding & Migration Management
- **Missing:**
  - Seed data script for development (sample children, families, activities, invoices)
  - Alembic migration for all AI service models (verify current state)
  - Gibbon CHANGEDB.php execution verification
  - Data migration scripts for pilot daycare onboarding
- **Impact:** Fresh installs have empty databases; developers can't test realistic scenarios

### GAP-16: CI/CD Pipeline
- **Status:** `.github/workflows/` directory exists but may be incomplete
- **Missing:**
  - AI service: lint (ruff/flake8), test (pytest), type check (mypy)
  - Parent portal: lint (eslint), test (vitest), build verification
  - Gibbon modules: PHPUnit test runner
  - Docker build verification
  - PR checks (require passing tests)
  - Deployment automation (staging/production)
- **Impact:** No automated quality gates; broken code can be merged

### GAP-17: API Pagination & Search
- **Missing:**
  - Consistent pagination across all API endpoints (cursor or offset-based)
  - Full-text search for children, families, activities
  - Filter parameters on list endpoints
  - Sort options on list endpoints
  - Response metadata (total count, page info)
- **Impact:** Performance degrades as data grows; users can't find records efficiently

### GAP-18: Caching Strategy
- **Missing:**
  - Redis caching for frequently accessed data (child profiles, activity catalog)
  - Cache invalidation on data updates
  - API response caching headers (ETag, Cache-Control)
  - Session caching optimization
- **Impact:** Unnecessary database load; slower response times

---

## Low Priority Gaps (Post-pilot)

### GAP-19: Monitoring & Health Checks
- **Missing:**
  - Health check endpoints for all services
  - Uptime monitoring dashboard
  - Database connection pool monitoring
  - Queue depth monitoring (notifications, sync)
  - Alerting for service failures

### GAP-20: Backup & Recovery
- **Missing:**
  - Automated MySQL backup script (daily)
  - Automated PostgreSQL backup script (daily)
  - Backup verification and restore testing
  - Point-in-time recovery configuration

### GAP-21: Performance Optimization
- **Missing:**
  - Database query optimization (indexes, query plans)
  - N+1 query detection and resolution
  - Image lazy loading and CDN configuration
  - Bundle size optimization (parent-portal)
  - API response compression (gzip)

### GAP-22: Accessibility (WCAG 2.1)
- **Missing:**
  - Semantic HTML audit for parent-portal
  - ARIA labels and roles
  - Keyboard navigation support
  - Screen reader compatibility testing
  - Color contrast verification
  - Focus management

### GAP-23: User Onboarding & Setup Wizard
- **Missing:**
  - First-run setup wizard for new daycare installations
  - Guided configuration for organization settings
  - Sample data import option
  - Admin user creation flow
  - Service connectivity verification

---

## Critical Gaps from Drive Requirements (Quebec Functional Spec)

The following gaps were identified from the "Document de cadrage fonctionnel" and skeleton forms in the LAYA Drive folder. These represent core Quebec childcare regulatory requirements that are **not addressed** in the current codebase.

### GAP-24: Child Registration Form (Fiche d'Inscription)
- **Status:** No digital enrollment form exists
- **Missing:**
  - Complete child identification (name, DOB, address, languages spoken/understood)
  - Parent 1 & 2 detailed info (name, address, phone, email, relationship)
  - Multiple authorized pickups with ID verification status
  - Multiple emergency contacts
  - Health section: allergies, medical history, EpiPen instructions, specialist contacts, medication list
  - Nutrition section: dietary restrictions, feeding instructions (breastfeeding/bottle/solid)
  - Admission date, attendance pattern (Mon-Sun AM/PM)
  - Special conditions for outings
  - E-signature capture (parent 1, parent 2, director)
  - Audit trail (creation date, modification date, history, status)
  - PDF export of completed form
- **Impact:** Cannot legally enroll children; missing mandatory Quebec documentation
- **Task:** 046

### GAP-25: Service Agreement Digitization (Entente de Services FO-0659)
- **Status:** No digital service agreement system
- **Missing:**
  - Full 13-article Quebec service agreement template
  - Payment terms (reduced contribution $9.35/day, frequency, method)
  - Operating hours, closure days (max 13/year), pickup lateness fees
  - Provider/parent termination clauses with Consumer Protection Act notice
  - Annex A (field trips), Annex B (hygiene items), Annex C (supplementary meals), Annex D (extended hours)
  - E-signature workflow with duplicate delivery
  - PDF generation of signed agreement
- **Impact:** No legally binding care contracts; fails Quebec regulatory compliance
- **Task:** 047

### GAP-26: Development Profile (Portrait de Développement)
- **Status:** No developmental tracking module
- **Missing:**
  - 6-domain tracking: Affective, Social, Language/Communication, Cognitive, Gross Motor, Fine Motor
  - Per-skill assessment: Can/Is learning/Not yet/N/A with observable evidence
  - Monthly snapshots and growth trajectory
  - Age-appropriate milestone alerts
  - Parent contribution portal
  - PDF export as printed portfolio
- **Impact:** Cannot track child development; no educational progress reporting to parents
- **Task:** 048

### GAP-27: Intervention Plan Module
- **Status:** No intervention plan system
- **Missing:**
  - 8-part plan: Identification, Strengths, Needs, SMART Goals, Strategies, Monitoring, Parent Involvement, Specialist Consultation
  - Plan versioning and monthly progress documentation
  - Mandatory parent signature
  - Automated review reminders
  - Parent read-only access
  - Specialist referral tracking
- **Impact:** Cannot manage special needs children per Quebec requirements
- **Task:** 049

### GAP-28: Medical Protocol Module
- **Status:** No medication/protocol management
- **Missing:**
  - Acetaminophen protocol (FO-0647): weight-based dosing table (4.3-35kg, 3 concentrations), temperature measurement standards by age, 10-step administration procedure, 60min recheck, overdose response
  - Insect repellent protocol (FO-0646): DEET/Picaridin limits, application procedure, age restrictions
  - Parental consent forms with e-signature
  - Administration logging per child
  - Weight revalidation every 3 months
  - Expiration tracking
- **Impact:** Cannot administer medication legally; liability exposure
- **Task:** 051

### GAP-29: Allergy & Medical Tracking
- **Status:** Basic allergy field in child profile; no comprehensive tracking
- **Missing:**
  - Multi-type allergy profiles (food, medication, seasonal, environmental)
  - Severity levels and reaction types per allergen
  - Accommodation plans with dietary substitutions
  - EpiPen/inhaler location tracking
  - Auto-flag allergens in served meals
  - Emergency response checklists per allergy
  - Staff training records per child's needs
- **Impact:** Child safety at risk; cannot prevent allergen exposure systematically
- **Task:** 052

### GAP-30: Incident Report System
- **Status:** Basic incident field in CareTracking; no structured system
- **Missing:**
  - Typed incident reports (bump, fall, bite, allergy reaction)
  - Body part/severity tracking
  - First aid documentation
  - Automatic parent notification triggers
  - Pattern detection (3+ incidents -> director review)
  - Incident-to-intervention-plan linking
  - Photo documentation
  - PDF export
- **Impact:** No formal incident documentation; legal liability
- **Task:** 053

### GAP-31: AI Message Quality Coach
- **Status:** No message quality enforcement
- **Missing:**
  - Accusatory language detection ("You" language, judgmental labels)
  - "Bonne message" rewrite suggestions (sandwich method, "I" language)
  - Quality scoring per message
  - Communication channel recommendations by situation type
  - Template library for common educator-parent messages
- **Impact:** Risk of inappropriate parent communication; Quebec communication standards not met
- **Task:** 054

### GAP-32: HR & Staff Management
- **Status:** Basic Gibbon staff module; no childcare-specific features
- **Missing:**
  - Staff schedule editor with shift templates
  - Time tracking (clock-in/out, hours, overtime)
  - Certification management with expiration reminders
  - Criminal background check tracking
  - Real-time staff-to-child ratio monitoring (Quebec: 1:5 for 0-18mo, 1:8 for 18-36mo, 1:10 for 36-60mo)
  - Disciplinary records (director-only access)
- **Impact:** Cannot ensure Quebec staff ratio compliance; no schedule management
- **Task:** 055

### GAP-33: Government Document Verification
- **Status:** No document tracking system
- **Missing:**
  - Required document checklist per child (birth cert, SIN, proof of residence, vaccination, health card)
  - Status tracking (received, verified, expiring)
  - Auto-notifications for missing/expiring documents
  - Immigration status documentation for non-Canadian families
  - Block service agreement if critical docs missing
  - Secure document upload and storage
- **Impact:** Cannot verify eligibility for reduced contribution; compliance risk
- **Task:** 056

### GAP-34: Attendance Sheet (Fiche d'Assiduité LOB 2024)
- **Status:** Basic attendance in CareTracking; no official format
- **Missing:**
  - LOB 2024 format template with weekly Mon-Fri AM/PM grid
  - Parent attestation and e-signature
  - Auto-calculation of days occupied vs present
  - Real-time dashboard (occupancy vs capacity, late pickups with fee accrual)
  - PDF printable format, Excel export
  - 7-year archive per Quebec requirements
- **Impact:** No official attendance records; cannot prove care days for RL-24
- **Task:** 057

### GAP-35: RL-24 XML Government Submission
- **Status:** RL-24 calculation exists; no XML submission format
- **Missing:**
  - XML file format per Revenu Québec spec (AAPPPPPPSSS.xml naming)
  - Government schema validation
  - Eligibility form FO-0601 digitization
  - Provider administration section
  - Batch generation for all children
  - Summary form auto-fill
- **Impact:** Cannot file tax receipts electronically with government
- **Task:** 058

### GAP-36: Role-Based Access Control (5 Roles)
- **Status:** No RBAC beyond basic Gibbon roles
- **Missing:**
  - Direction (Director): Full read, financial modify, user management
  - Éducateur (Educator): Group-specific child data, read/write observations
  - Comptable (Accountant): Financial module only
  - Parent: Own children only, read-only except signatures
  - Other Staff: Own schedule only
  - Group-level restrictions, approval workflows, audit trail
- **Impact:** No data isolation between roles; privacy violations possible
- **Task:** 060

### GAP-37: Meal Menu Management
- **Status:** Meal logging exists in CareTracking; no menu planning
- **Missing:**
  - Weekly menu editor (breakfast, snacks, lunch, dinner)
  - Per-child consumption tracking (full/half/taste/none)
  - Allergen cross-reference with child profiles
  - Aggregated nutrition reports
  - Menu sharing with parents
  - Dietary accommodation tracking
- **Impact:** No structured meal planning; allergen exposure risk
- **Task:** 063

### GAP-38: Real-Time Director Dashboard
- **Status:** Analytics dashboard exists in ai-service but not connected
- **Missing:**
  - Live occupancy by group/room
  - Staff ratio compliance monitoring
  - Late pickup tracking with fee calculation
  - Outstanding financial balances
  - Alert system (overdue payments, expiring certs, incident thresholds)
  - Weekly/monthly KPI trends
- **Impact:** Director has no operational visibility
- **Task:** 064

### GAP-39: Parent Portal Messaging System
- **Status:** Basic messaging UI exists; no backend
- **Missing:**
  - Educator-parent and director-parent messaging
  - Notification inbox with preferences (immediate/digest/weekly)
  - Integration with AI Message Quality Coach
  - Private notes vs shared notes
  - Photo/video attachment with authorization
  - @ mentions for notifications
- **Impact:** No communication channel between educators and parents
- **Task:** 065

---

## Gap-to-Task Mapping

| Gap ID | Priority | Task(s) | Dependencies |
|--------|----------|---------|-------------|
| GAP-01 | Critical | 020 | None |
| GAP-02 | Critical | 021 | GAP-01 |
| GAP-03 | Critical | 022, 023 | None |
| GAP-04 | Critical | 024 | None |
| GAP-05 | Critical | 025, 026 | GAP-03 |
| GAP-06 | Critical | 027, 028 | GAP-03 |
| GAP-07 | Critical | 029 | None |
| GAP-08 | High | 030 | None |
| GAP-09 | High | 031 | None |
| GAP-10 | High | 032 | GAP-04 |
| GAP-11 | High | 033 | GAP-01 |
| GAP-12 | High | 034 | GAP-09 |
| GAP-13 | High | 035 | GAP-03 |
| GAP-14 | Medium | 036 | None |
| GAP-15 | Medium | 037 | GAP-01, GAP-04 |
| GAP-16 | Medium | 038 | None |
| GAP-17 | Medium | 039 | None |
| GAP-18 | Medium | 040 | None |
| GAP-19 | Low | 041 | None |
| GAP-20 | Low | 042 | None |
| GAP-21 | Low | 043 | None |
| GAP-22 | Low | 044 | None |
| GAP-23 | Low | 045 | None |
| GAP-24 | **Critical** | 046 | GAP-05 (auth) |
| GAP-25 | **Critical** | 047 | GAP-24 (registration), GAP-05 |
| GAP-26 | **Critical** | 048 | GAP-04, GAP-07 |
| GAP-27 | **Critical** | 049 | GAP-26 (dev profile) |
| GAP-28 | **Critical** | 051 | GAP-24 (child file) |
| GAP-29 | **Critical** | 052 | GAP-24 (child file) |
| GAP-30 | High | 053 | GAP-24 (child file) |
| GAP-31 | High | 054 | GAP-07 (LLM) |
| GAP-32 | **Critical** | 055 | None |
| GAP-33 | **Critical** | 056 | GAP-24 (child file) |
| GAP-34 | **Critical** | 057 | GAP-24 (child file) |
| GAP-35 | High | 058 | GAP-02 (RL-24 PDF) |
| GAP-36 | **Critical** | 060 | GAP-03 (auth) |
| GAP-37 | High | 063 | GAP-24 (child file) |
| GAP-38 | High | 064 | GAP-04, GAP-05 |
| GAP-39 | High | 065 | GAP-05, GAP-31 |

---

## Complete Task Dependency Graph

```
LAYER 0 - No Dependencies (Start Immediately)
===============================================
[020] Finance Module Install     [022] Auth: AI Service JWT
[024] AISync Webhook Wiring      [029] LLM Integration Service
[030] i18n Parent Portal         [031] File Upload & Storage
[036] Error Handling/Logging     [038] CI/CD Pipeline
[039] API Pagination             [040] Redis Caching
[055] HR & Staff Management      [062] Audit Trail & Logging

LAYER 1 - Single Dependency
============================
[021] RL-24 PDF               -> [020]
[023] Auth: Gibbon-AI Bridge  -> [022]
[025] Parent Portal Auth      -> [022]
[027] Teacher App Auth & API  -> [022]
[028] Parent App Auth & API   -> [022]
[032] Push Notifications      -> [024]
[033] Invoice PDF             -> [020]
[035] Security Hardening      -> [022]
[054] AI Message Quality      -> [029]
[059] Accounting Export        -> [020]
[060] RBAC Roles              -> [022, 023]
[061] MFA Admin               -> [022]

LAYER 2 - Multi-Dependency
===========================
[026] Portal API Integration  -> [022, 030]
[037] Database Seeding        -> [020, 024]
[046] Child Registration      -> [025]
[058] RL-24 XML Submission    -> [021]

LAYER 3 - Deep Dependencies
============================
[047] Service Agreements      -> [025, 046]
[048] Development Profile     -> [024, 029]
[050] Educational Portfolio   -> [031, 048]
[051] Medical Protocols       -> [046]
[052] Allergy Tracking        -> [046]
[053] Incident Reports        -> [046]
[056] Document Verification   -> [046]
[057] Attendance Sheets       -> [046]
[063] Meal Menu Management    -> [046]
[064] Real-Time Dashboard     -> [024, 026]
[065] Portal Messaging        -> [026, 054]

LAYER 4 - Deepest Dependencies
================================
[034] Document E-Signature    -> [031, 025]
[049] Intervention Plans      -> [048]
[041] Health Checks           -> [036]
[043] Performance             -> [039, 040]
[044] Accessibility           -> [026]
[045] Onboarding Wizard       -> [020, 022, 037]
```

**Total: 46 tasks (020-065) covering all identified gaps from GAP-01 through GAP-39**
