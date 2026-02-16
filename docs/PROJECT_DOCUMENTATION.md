# LAYA - Kindergarten & Childcare Management Platform

## Project Documentation

---

## 1. Executive Summary

LAYA is a comprehensive, AI-powered kindergarten and childcare management platform purpose-built for the Quebec childcare market. It combines **Gibbon CMS** (an open-source school management system) as an operational backbone with custom-built modules, an AI-powered FastAPI backend, and multi-platform client applications for parents, educators, and administrators.

**Key Differentiators:**
- AI-powered activity recommendations, coaching guidance, and automated daily reports
- Full Quebec regulatory compliance (staff ratios, bilingual EN/FR, Relev 24 tax slips, 5-year photo retention)
- Evidence-based special needs coaching with mandatory peer-reviewed citations
- Multi-platform delivery: web portal, iOS/Android mobile apps, Windows desktop, macOS admin

**Target:** 3-pilot daycares for MVP validation, with a freemium revenue model (basic tier free, AI features premium).

---

## 2. Architecture Overview

```
                    +-----------------------+
                    |   Admin macOS App      |
                    |   (SwiftUI native)     |
                    +-----------+-----------+
                                |
+------------------+    +-------v--------+    +------------------+
| Parent Portal    |    |  Gibbon CMS    |    | Teacher App      |
| (Next.js 14)     |    |  (PHP 8.3)     |    | (React Native)   |
| Port 3000        |    |  Port 8080     |    | iOS + Android    |
+--------+---------+    +-------+--------+    +--------+---------+
         |                      |                       |
         |              +-------v--------+              |
         +------------->|    Nginx       |<-------------+
                        |  (Reverse Proxy)|
                        +-------+--------+
                                |
         +----------------------+----------------------+
         |                                             |
+--------v---------+                          +--------v---------+
|   AI Service     |    AISync Webhooks       |   MySQL 8.0      |
|   (FastAPI)      |<------------------------>|   (Gibbon DB)    |
|   Port 8000      |                          |   Port 3306      |
+--------+---------+                          +------------------+
         |
+--------v---------+    +------------------+
|  PostgreSQL 12   |    |   Redis 7        |
|  (AI Service DB) |    |   (Cache/Session) |
|  Port 5433       |    |   Port 6379      |
+------------------+    +------------------+
```

---

## 3. Services & Technology Stack

### 3.1 Gibbon CMS (Core Backend)
- **Language:** PHP 8.3
- **Database:** MySQL 8.0 (charset: utf8mb4)
- **Web Server:** Nginx + PHP-FPM 8.3
- **Version:** Gibbon v30.0.01
- **Purpose:** Student/family management, staff management, attendance, forms, core admin operations
- **Custom Modules:**
  - `CareTracking` - Daily care logging (meals, naps, diapers, incidents, activities)
  - `EnhancedFinance` - Invoicing, payments, contracts, Quebec RL-24 tax slips
  - `PhotoManagement` - Photo uploads, child tagging, 5-year soft-delete retention
  - `NotificationEngine` - Multi-channel notifications (email + FCM push)
  - `AISync` - Bidirectional data sync between Gibbon and AI Service

### 3.2 AI Service (Intelligence Backend)
- **Language:** Python 3.11+
- **Framework:** FastAPI (async)
- **Database:** PostgreSQL 12 via async SQLAlchemy + asyncpg
- **Auth:** JWT (PyJWT) with HTTPBearer
- **Migrations:** Alembic
- **Port:** 8000
- **Endpoints:**
  - `/api/v1/activities/` - Activity Intelligence (personalized recommendations)
  - `/api/v1/coaching/` - Special Needs Coach (evidence-based guidance)
  - `/api/v1/communication/` - Parent Communication (daily reports, home activities)
  - `/api/v1/analytics/` - Business Intelligence (KPIs, forecasting, compliance)
  - `/api/v1/webhook/` - Gibbon event ingestion

### 3.3 Parent Portal (Web Frontend)
- **Framework:** Next.js 14 (App Router)
- **Language:** TypeScript
- **Styling:** Tailwind CSS 3.4
- **Testing:** Vitest + React Testing Library
- **Port:** 3000
- **Pages:** Dashboard, Daily Reports, Messages, Invoices, Documents, Photo Gallery

### 3.4 Teacher App (Mobile)
- **Framework:** React Native 0.78
- **Language:** TypeScript
- **Navigation:** React Navigation (native-stack + bottom-tabs)
- **Push:** Firebase Cloud Messaging
- **Platforms:** iOS + Android
- **Android setup:** See [ANDROID_APP_SETUP.md](ANDROID_APP_SETUP.md) for environment, Gradle/RN 0.78 fixes, emulator, and troubleshooting.

### 3.5 Parent App (Mobile)
- **Framework:** React Native 0.78
- **Language:** TypeScript
- **Navigation:** React Navigation (native-stack + bottom-tabs)
- **Push:** Firebase Cloud Messaging
- **Features:** Photo viewing, daily feed, messaging, invoices

### 3.6 Desktop App
- **Framework:** Electron 40.x
- **Target:** Windows (NSIS installer)
- **Approach:** Loads parent-portal from localhost:3000
- **Security:** nodeIntegration disabled, contextIsolation enabled, CSP headers
- **Updates:** electron-updater for auto-updates

### 3.7 Admin macOS App
- **Framework:** SwiftUI (native macOS)
- **Architecture:** MVVM with Combine
- **Target:** macOS 13+
- **Purpose:** Administrative dashboard for daycare operators
- **API:** Connects to Gibbon REST API

---

## 4. Database Schema

### 4.1 Gibbon MySQL (Primary)

**Core Gibbon Tables:** gibbonPerson, gibbonFamily, gibbonSchoolYear, gibbonFormGroup, gibbonStudentEnrolment, gibbonStaff, gibbonAction, gibbonModule, gibbonPermission, gibbonRole

**Care Tracking Tables:**
- `gibbonCareAttendance` - Check-in/check-out records
- `gibbonCareMeal` - Meal logging (portions, types)
- `gibbonCareNap` - Nap duration and quality
- `gibbonCareDiaper` - Diaper change records
- `gibbonCareIncident` - Incident reports
- `gibbonCareActivity` - Activity participation

**Enhanced Finance Tables:**
- `gibbonEnhancedFinanceInvoice` - Invoice records with tax calculations (GST 5% + QST 9.975%)
- `gibbonEnhancedFinancePayment` - Payment receipts (Cash, Cheque, E-Transfer, Credit/Debit)
- `gibbonEnhancedFinanceReleve24` - Quebec RL-24 tax slip data
- `gibbonEnhancedFinanceContract` - Care contracts (weekly rate, days/week, terms)

**Photo Management Tables:**
- `gibbonPhotoUpload` - Photo metadata with soft-delete (5-year retention)
- `gibbonPhotoTag` - Child-photo associations
- `gibbonPhotoRetention` - Retention policy tracking

**Notification Engine Tables:**
- `gibbonNotificationQueue` - Notification delivery queue
- `gibbonNotificationTemplate` - Message templates by event type
- `gibbonNotificationPreference` - User channel preferences
- `gibbonFCMToken` - Firebase device tokens

**AISync Tables:**
- `gibbonAISyncLog` - Webhook delivery logs with retry tracking

### 4.2 AI Service PostgreSQL (Secondary)

**Activity Intelligence:**
- `activities` - Educational activity catalog (type, difficulty, duration, materials, age range)
- `activity_recommendations` - Personalized recommendations with relevance scores
- `activity_participation` - Engagement tracking (started, completed, abandoned)

**Coaching:**
- `coaching_sessions` - Coaching interaction records
- `coaching_recommendations` - Evidence-based guidance with priority levels
- `evidence_sources` - Peer-reviewed citations (DOI, URL, ISBN)

**Communication:**
- `parent_reports` - AI-generated daily summaries (bilingual EN/FR)
- `home_activities` - At-home activity suggestions
- `communication_preferences` - Language and frequency preferences

**Analytics:**
- `analytics_metrics` - KPI tracking (enrollment, attendance, revenue, staffing)
- `enrollment_forecasts` - Time-series predictions with confidence intervals
- `compliance_checks` - Quebec regulatory compliance status

---

## 5. Quebec Compliance Requirements

### 5.1 Staff-to-Child Ratios (Ministere de la Famille)
| Age Group | Required Ratio |
|-----------|---------------|
| 0-18 months | 1:5 |
| 18-36 months | 1:8 |
| 36-48 months | 1:10 |
| 48-60 months | 1:10 |
| 60+ months (school-age) | 1:20 |

### 5.2 Releve 24 (RL-24) Tax Slips
- **Box A:** Slip type (R=original, A=amended, D=cancelled)
- **Box B:** Days of care (from PAID invoices only, not invoiced)
- **Box C:** Total amounts PAID (sum of payments, not invoice totals)
- **Box D:** Non-qualifying expenses (medical, transport, teaching, field trips, registration, late fees)
- **Box E:** Qualifying expenses (C - D, minimum 0)
- **Box H:** Provider SIN (XXX-XXX-XXX format, Luhn-validated)
- **Filing Deadline:** Last day of February following tax year
- **Amendment:** Type A slip when additional payments received after original filing

### 5.3 Bilingual Support
- All parent-facing communications must be available in English and French
- Language preference stored per parent in communication_preferences
- Template-based translations (not machine translation)

### 5.4 Photo Retention
- 5-year soft-delete retention period (Quebec privacy compliance)
- Hard-delete only via scheduled CLI for records 5+ years old
- All queries filter `WHERE deletedAt IS NULL`

---

## 6. Development Environment

### 6.1 Prerequisites
- Docker & Docker Compose
- Node.js 18+ (parent-portal)
- Python 3.11+ (ai-service)
- Xcode 15+ (macOS admin app, teacher/parent iOS)
- Android Studio (teacher/parent Android)

### 6.2 Running Services
```bash
# Start all infrastructure
docker-compose up -d

# Gibbon CMS: http://localhost:8080
# AI Service: http://localhost:8000/docs
# Parent Portal: http://localhost:3000

# AI Service (development)
cd ai-service && source .venv/bin/activate
uvicorn app.main:app --reload --port 8000

# Parent Portal (development)
cd parent-portal && npm run dev

# Run AI Service tests
cd ai-service && pytest tests/ -v

# Run Parent Portal tests
cd parent-portal && npx vitest
```

### 6.3 Environment Variables
- `DATABASE_URL` - PostgreSQL connection (ai-service)
- `MYSQL_*` - MySQL credentials (Gibbon)
- `JWT_SECRET_KEY` - JWT signing key
- `REDIS_URL` - Redis connection string
- `AI_SERVICE_URL` - Internal service URL (default: http://ai-service:8000)
- `GIBBON_URL` - Gibbon CMS URL (default: http://localhost:8080)

### 6.4 Teacher App (Android)
- **Full guide:** [ANDROID_APP_SETUP.md](ANDROID_APP_SETUP.md) — SDK, emulator, Gradle/React Native 0.78 config, SoLoader/BootReceiver fixes, Metro CLI version, and run script.
- **Quick run:** Emulator + `cd teacher-app && npx react-native run-android` (or `./run-android-emulator.sh` after building).

---

## 7. Project Structure

```
laya-backbone/
+-- .auto-claude/           # Auto Claude task management system
|   +-- specs/              # 18 task specifications (002-019)
|   +-- worktrees/          # Git worktree working directories
|   +-- project_index.json  # Service registry
+-- .claude/                # Claude Code configuration
+-- .github/workflows/      # CI/CD pipelines
+-- admin-macos-app/        # SwiftUI macOS admin (Xcode project)
+-- ai-service/             # FastAPI Python backend
|   +-- app/                # Application code
|   |   +-- models/         # SQLAlchemy models
|   |   +-- routers/        # API endpoints
|   |   +-- services/       # Business logic
|   |   +-- schemas/        # Pydantic validation
|   +-- alembic/            # Database migrations
|   +-- tests/              # pytest test suite
+-- desktop-app/            # Electron Windows app
+-- docker/                 # Docker configuration files
+-- docs/                   # Project documentation
+-- gibbon/                 # Gibbon CMS v30.0.01
|   +-- modules/            # Custom LAYA modules
|   |   +-- AISync/
|   |   +-- CareTracking/
|   |   +-- NotificationEngine/
|   |   +-- PhotoManagement/
|   +-- src/                # Gibbon core framework
+-- modules/                # Standalone module development
|   +-- EnhancedFinance/    # Finance module (pre-integration)
+-- parent-app/             # React Native parent mobile app
+-- parent-portal/          # Next.js parent web portal
+-- teacher-app/            # React Native teacher mobile app
+-- docker-compose.yml      # Docker orchestration
```

---

## 8. Auto Claude Task System

Tasks are managed via the `.auto-claude/specs/` directory. Each task follows a standardized structure:

```
specs/NNN-task-name/
+-- spec.md                    # Full specification (requirements, patterns, criteria)
+-- task_metadata.json         # Model config, thinking levels, base branch
+-- requirements.json          # Task description, workflow type
+-- implementation_plan.json   # Phases, subtasks, dependencies, verification
+-- context.json               # Files to create/modify/reference, patterns
+-- complexity_assessment.json # Complexity metrics and risk assessment
+-- review_state.json          # QA approval status
+-- project_index.json         # Service catalog context
```

**Task Lifecycle:** Spec Creation -> Planning -> Implementation -> Testing -> QA -> Review -> Merge

**Completed Tasks:** 002-014, 017 (15 tasks)
**In Progress:** 015, 016, 018, 019 (4 tasks)

---

## 9. Testing Strategy

| Service | Framework | Command |
|---------|-----------|---------|
| AI Service | pytest + pytest-asyncio | `cd ai-service && pytest tests/ -v` |
| Parent Portal | Vitest + React Testing Library | `cd parent-portal && npx vitest` |
| Gibbon Modules | PHPUnit | `cd gibbon && vendor/bin/phpunit` |
| Teacher App | Jest | `cd teacher-app && npm test` |
| Parent App | Jest | `cd parent-app && npm test` |
| Enhanced Finance | PHPUnit (standalone) | `cd modules/EnhancedFinance && phpunit tests/` |

---

## 10. Deployment

The platform runs via Docker Compose with 7 containerized services. Production deployment targets:
- **Web:** Nginx serving Gibbon + reverse proxy to AI Service and Parent Portal
- **Database:** MySQL 8.0 (Gibbon) + PostgreSQL 12 (AI Service) with persistent volumes
- **Cache:** Redis 7 for session management and caching
- **Mobile:** App Store (iOS) + Google Play (Android) distribution
- **Desktop:** Windows installer via electron-builder + NSIS
