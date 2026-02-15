# AI Activity Suggestion Flow E2E Documentation

## Overview

This document describes the end-to-end flow for AI-powered activity recommendations in the LAYA childcare management system. The workflow covers five major steps: viewing a child's profile, requesting AI-generated activity suggestions, age-appropriate filtering verification, activity selection, and recording participation.

## System Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Teacher App   │     │     Gibbon      │     │   AI Service    │
│  (React Native) │────▶│   (PHP/MySQL)   │────▶│    (FastAPI)    │
└─────────────────┘     └────────┬────────┘     └─────────────────┘
                                 │                      │
                                 │ AISync Webhooks      │ Activity
                                 │                      │ Recommendations
                                 ▼                      ▼
                        ┌─────────────────┐     ┌─────────────────┐
                        │  Parent Portal  │     │ Activity Scoring│
                        │   (Next.js)     │     │    Algorithm    │
                        └─────────────────┘     └─────────────────┘
```

### Services Involved

| Service | Tech Stack | Port | Role |
|---------|------------|------|------|
| teacher-app | React Native | N/A (mobile) | Educator UI for requesting suggestions and logging activities |
| gibbon | PHP 8.1+ / MySQL | 80/8080 | Backend, child profiles, activity tracking via CareTracking module |
| ai-service | FastAPI / PostgreSQL | 8000 | AI recommendations API, age filtering, relevance scoring |
| parent-portal | Next.js 14 | 3000 | Parent-facing view of child activities |

---

## Step 1: View Child Profile

### User Action
Educator opens the Teacher App and views a child's profile to understand their developmental stage, age, and activity history.

### Flow Diagram

```
Educator selects child from classroom list
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ GET /api/child/{gibbonPersonID}/profile                         │
│ Headers: Authorization: Bearer <session_token>                   │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend                                                   │
│ 1. Validate educator session/permissions                        │
│ 2. Fetch child profile from gibbonPerson table                  │
│ 3. Calculate age in months from date of birth                   │
│ 4. Retrieve recent activity history                              │
│ 5. Return profile data with developmental indicators            │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App displays:                                            │
│ - Child name, photo, age                                        │
│ - Recent activities participated in                              │
│ - Activity type distribution                                     │
│ - "Get AI Suggestions" button                                    │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → Gibbon (Get Child Profile)

```http
GET /modules/CareTracking/careTracking_childProfile.php?gibbonPersonID=12345
Authorization: Bearer <session_token>
```

**Response:**
```json
{
  "success": true,
  "child": {
    "gibbonPersonID": 12345,
    "preferredName": "Sophie",
    "surname": "Martin",
    "dateOfBirth": "2022-08-15",
    "ageMonths": 42,
    "ageDisplay": "3 years 6 months",
    "image_240": "uploads/photos/12345_240.jpg",
    "classroomID": 5,
    "classroomName": "Butterflies Room",
    "specialNeeds": null,
    "allergies": ["Peanuts"],
    "primaryLanguage": "French"
  },
  "activitySummary": {
    "totalActivitiesThisWeek": 15,
    "favoriteType": "Art",
    "averageDurationMinutes": 25,
    "participationLevel": "Leading",
    "aiSuggestedActivitiesUsed": 3
  },
  "recentActivities": [
    {
      "activityName": "Finger Painting",
      "activityType": "Art",
      "date": "2026-02-14",
      "participation": "Leading"
    },
    {
      "activityName": "Building Blocks",
      "activityType": "Math",
      "date": "2026-02-14",
      "participation": "Participating"
    }
  ]
}
```

### Age Calculation

The child's age in months is critical for age-appropriate filtering:

```php
// In Gibbon CareTracking module
function calculateAgeInMonths($dateOfBirth, $referenceDate = null) {
    $dob = new DateTime($dateOfBirth);
    $now = $referenceDate ? new DateTime($referenceDate) : new DateTime();

    $interval = $dob->diff($now);
    $months = ($interval->y * 12) + $interval->m;

    // Add partial month if more than 15 days
    if ($interval->d >= 15) {
        $months++;
    }

    return $months;
}
```

### Database Tables Accessed

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonPerson` | SELECT | gibbonPersonID, preferredName, surname, dob, image_240 |
| `gibbonCareActivity` | SELECT | Activity history for the child |
| `gibbonStudentEnrolment` | SELECT | Classroom/form group information |

### Expected Outcome
- Child profile displayed with photo, name, and age
- Recent activity history visible
- "Get AI Suggestions" button available for educator

---

## Step 2: Request AI Activity Suggestions

### User Action
Educator taps "Get AI Suggestions" button to request personalized activity recommendations for the child.

### Flow Diagram

```
Educator taps "Get AI Suggestions"
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ 1. Gather child profile data (age, preferences)                 │
│ 2. Detect current context (weather, time of day, group size)    │
│ 3. POST request to Gibbon proxy or direct to AI Service         │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend (Optional Proxy)                                  │
│ 1. Validate educator permissions                                │
│ 2. Enrich request with child data from database                 │
│ 3. Forward to AI Service with JWT token                         │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service (FastAPI)                                             │
│ 1. Validate JWT token via get_current_user dependency           │
│ 2. Parse request parameters (child_id, age_months, filters)     │
│ 3. Query activity database with age-appropriate filtering       │
│ 4. Score activities using multi-factor relevance algorithm      │
│ 5. Return ranked recommendations                                │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → AI Service (Request Recommendations)

```http
GET /api/v1/activities/recommendations/123e4567-e89b-12d3-a456-426614174000
    ?max_recommendations=5
    &child_age_months=42
    &weather=sunny
    &group_size=8
    &include_special_needs=true
Content-Type: application/json
Authorization: Bearer <jwt_token>
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `max_recommendations` | integer | No | Maximum recommendations (1-20, default: 5) |
| `child_age_months` | integer | No | Child's age in months for filtering (0-144) |
| `activity_types` | array | No | Filter by specific types (cognitive, motor, etc.) |
| `weather` | string | No | Current weather for indoor/outdoor filtering |
| `group_size` | integer | No | Current group size for compatibility |
| `include_special_needs` | boolean | No | Include special needs adaptations (default: true) |

**Response:**
```json
{
  "child_id": "123e4567-e89b-12d3-a456-426614174000",
  "recommendations": [
    {
      "activity": {
        "id": "550e8400-e29b-41d4-a716-446655440001",
        "name": "Shape Sorting Challenge",
        "description": "Children sort colorful shapes into matching containers, developing pattern recognition and fine motor skills.",
        "activity_type": "cognitive",
        "difficulty": "medium",
        "duration_minutes": 20,
        "materials_needed": ["shape sorter", "colored shapes", "sorting containers"],
        "age_range": {
          "min_months": 36,
          "max_months": 60
        },
        "special_needs_adaptations": "Use larger shapes for children with motor difficulties. Provide verbal cues for visually impaired.",
        "is_active": true,
        "created_at": "2026-01-10T10:00:00Z",
        "updated_at": "2026-01-10T10:00:00Z"
      },
      "relevance_score": 0.92,
      "reasoning": "Excellent match for 42-month-old child. Cognitive activities align with developmental stage. Child has shown interest in shape-based activities."
    },
    {
      "activity": {
        "id": "550e8400-e29b-41d4-a716-446655440002",
        "name": "Musical Movement Circle",
        "description": "Group activity where children move to music, learning to follow rhythms and express themselves through dance.",
        "activity_type": "motor",
        "difficulty": "easy",
        "duration_minutes": 15,
        "materials_needed": ["music player", "scarves or ribbons"],
        "age_range": {
          "min_months": 24,
          "max_months": 72
        },
        "special_needs_adaptations": "Allow seated participation. Provide tactile cues for hearing impaired.",
        "is_active": true,
        "created_at": "2026-01-08T14:30:00Z",
        "updated_at": "2026-01-08T14:30:00Z"
      },
      "relevance_score": 0.88,
      "reasoning": "Good for group of 8 children. Sunny weather allows outdoor movement. Balances recent art activities with motor development."
    },
    {
      "activity": {
        "id": "550e8400-e29b-41d4-a716-446655440003",
        "name": "Story Time with Puppets",
        "description": "Interactive storytelling using hand puppets to engage children in narrative comprehension and language development.",
        "activity_type": "language",
        "difficulty": "easy",
        "duration_minutes": 25,
        "materials_needed": ["hand puppets", "storybook", "comfortable seating area"],
        "age_range": {
          "min_months": 30,
          "max_months": 60
        },
        "special_needs_adaptations": "Use large puppets with distinct features. Add sign language for key story elements.",
        "is_active": true,
        "created_at": "2026-01-05T09:15:00Z",
        "updated_at": "2026-01-05T09:15:00Z"
      },
      "relevance_score": 0.85,
      "reasoning": "Supports bilingual development (French primary language). Low recent language activity participation suggests this area needs attention."
    }
  ],
  "generated_at": "2026-02-15T10:30:00.123Z"
}
```

### AI Service Implementation

#### Activity Service (app/services/activity_service.py)

The activity service implements the recommendation algorithm:

```python
class ActivityService:
    """Service for activity recommendations and management."""

    async def get_recommendations(
        self,
        child_id: UUID,
        max_recommendations: int = 5,
        activity_types: Optional[list[str]] = None,
        child_age_months: Optional[int] = None,
        weather: Optional[str] = None,
        group_size: Optional[int] = None,
        include_special_needs: bool = True,
    ) -> ActivityRecommendationResponse:
        """Generate personalized activity recommendations.

        Scoring factors:
        1. Age appropriateness (0-1): How well activity fits child's age
        2. Type preference (0-1): Based on historical participation
        3. Variety bonus (0-0.2): Promotes trying new activity types
        4. Weather compatibility (0-1): Indoor/outdoor suitability
        5. Group size fit (0-1): Activity works with current group
        6. Recency penalty (-0.3-0): Avoids recently done activities
        """
        # Query activities with age filtering
        activities = await self._query_age_appropriate_activities(
            child_age_months=child_age_months,
            activity_types=activity_types,
        )

        # Score each activity
        scored_activities = []
        for activity in activities:
            score = self._calculate_relevance_score(
                activity=activity,
                child_age_months=child_age_months,
                weather=weather,
                group_size=group_size,
            )
            reasoning = self._generate_reasoning(activity, score)
            scored_activities.append((activity, score, reasoning))

        # Sort by score and return top recommendations
        scored_activities.sort(key=lambda x: x[1], reverse=True)

        recommendations = [
            ActivityRecommendation(
                activity=self._activity_to_response(activity),
                relevance_score=score,
                reasoning=reasoning,
            )
            for activity, score, reasoning in scored_activities[:max_recommendations]
        ]

        return ActivityRecommendationResponse(
            child_id=child_id,
            recommendations=recommendations,
            generated_at=datetime.utcnow(),
        )
```

### Expected Outcome
- AI Service returns ranked activity recommendations
- Each recommendation includes relevance score and reasoning
- Activities are filtered by age appropriateness

---

## Step 3: Age-Appropriate Filtering Verification

### Core Concept

Age filtering is the most critical component of the AI activity suggestion flow. It ensures children are never recommended activities outside their developmental capabilities.

### Age Range Model

```python
class AgeRange(BaseModel):
    """Age range specification for activity targeting.

    Attributes:
        min_months: Minimum age in months (0-144)
        max_months: Maximum age in months (0-144)
    """
    min_months: int = Field(..., ge=0, le=144)
    max_months: int = Field(..., ge=0, le=144)
```

### Filtering Algorithm

```python
def is_age_appropriate(activity: Activity, child_age_months: int) -> bool:
    """Check if an activity is appropriate for the child's age.

    Args:
        activity: The activity to check
        child_age_months: Child's age in months

    Returns:
        True if activity is appropriate for the child's age
    """
    if activity.age_range is None:
        # Activity is suitable for all ages
        return True

    return (
        activity.age_range.min_months <= child_age_months <= activity.age_range.max_months
    )
```

### Edge Cases at Age Boundaries

The system must handle edge cases where a child is exactly at the boundary of an activity's age range.

| Scenario | Child Age | Activity Range | Result | Rationale |
|----------|-----------|----------------|--------|-----------|
| At minimum | 36 months | 36-60 months | ✅ Include | Child meets minimum |
| At maximum | 60 months | 36-60 months | ✅ Include | Child meets maximum (inclusive) |
| Below minimum | 35 months | 36-60 months | ❌ Exclude | Below developmental stage |
| Above maximum | 61 months | 36-60 months | ❌ Exclude | Activity too simple |
| No range set | 42 months | null | ✅ Include | Universal activity |

### Age Boundary Test Cases

```python
# test_activities.py - Age boundary verification

@pytest.mark.asyncio
async def test_age_at_exact_minimum_boundary(client, auth_headers):
    """Child exactly at min age should see activity."""
    response = await client.get(
        f"/api/v1/activities/recommendations/{child_id}"
        f"?child_age_months=36",  # Exact minimum
        headers=auth_headers,
    )
    assert response.status_code == 200
    data = response.json()

    # Activity with range 36-60 should be included
    activity_ids = [r["activity"]["id"] for r in data["recommendations"]]
    assert "activity-36-60" in activity_ids


@pytest.mark.asyncio
async def test_age_at_exact_maximum_boundary(client, auth_headers):
    """Child exactly at max age should see activity."""
    response = await client.get(
        f"/api/v1/activities/recommendations/{child_id}"
        f"?child_age_months=60",  # Exact maximum
        headers=auth_headers,
    )
    assert response.status_code == 200
    data = response.json()

    # Activity with range 36-60 should be included
    activity_ids = [r["activity"]["id"] for r in data["recommendations"]]
    assert "activity-36-60" in activity_ids


@pytest.mark.asyncio
async def test_age_one_month_below_minimum(client, auth_headers):
    """Child one month below min should NOT see activity."""
    response = await client.get(
        f"/api/v1/activities/recommendations/{child_id}"
        f"?child_age_months=35",  # One below minimum
        headers=auth_headers,
    )
    assert response.status_code == 200
    data = response.json()

    # Activity with range 36-60 should NOT be included
    activity_ids = [r["activity"]["id"] for r in data["recommendations"]]
    assert "activity-36-60" not in activity_ids


@pytest.mark.asyncio
async def test_age_one_month_above_maximum(client, auth_headers):
    """Child one month above max should NOT see activity."""
    response = await client.get(
        f"/api/v1/activities/recommendations/{child_id}"
        f"?child_age_months=61",  # One above maximum
        headers=auth_headers,
    )
    assert response.status_code == 200
    data = response.json()

    # Activity with range 36-60 should NOT be included
    activity_ids = [r["activity"]["id"] for r in data["recommendations"]]
    assert "activity-36-60" not in activity_ids
```

### Developmental Age Categories

| Age Range | Category | Typical Activity Types |
|-----------|----------|----------------------|
| 0-12 months | Infant | Sensory exploration, tummy time, simple cause-effect |
| 12-24 months | Toddler | Object permanence, stacking, push toys |
| 24-36 months | Early Preschool | Parallel play, simple puzzles, art basics |
| 36-48 months | Preschool | Cooperative play, early literacy, counting |
| 48-60 months | Pre-K | Complex puzzles, social games, writing readiness |
| 60-72 months | Kindergarten | Reading readiness, advanced motor, team activities |
| 72+ months | School Age | Academic support, complex social dynamics |

### SQL Query for Age Filtering

```sql
-- Query activities appropriate for a 42-month-old child
SELECT
    a.id,
    a.name,
    a.description,
    a.activity_type,
    a.age_range_min_months,
    a.age_range_max_months
FROM activities a
WHERE a.is_active = TRUE
AND (
    -- No age range means suitable for all
    (a.age_range_min_months IS NULL AND a.age_range_max_months IS NULL)
    OR
    -- Child's age falls within range (inclusive)
    (
        a.age_range_min_months <= 42
        AND a.age_range_max_months >= 42
    )
)
ORDER BY
    -- Prioritize activities where child is in the middle of the range
    ABS(((a.age_range_min_months + a.age_range_max_months) / 2) - 42) ASC;
```

### Expected Outcome
- Only age-appropriate activities returned
- Age boundaries are inclusive
- Activities without age ranges are included for all children

---

## Step 4: Select Activity

### User Action
Educator reviews the AI suggestions and selects an activity to do with the child or group.

### Flow Diagram

```
Educator reviews AI suggestions
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App displays recommendations:                           │
│ - Activity name, description, materials                         │
│ - Relevance score and reasoning                                  │
│ - Duration estimate                                              │
│ - Special needs adaptations (if applicable)                      │
│ - "Select" button for each                                       │
└─────────────────────────────────────────────────────────────────┘
         │
         │ Educator taps "Select" on chosen activity
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ 1. Cache selected activity details                              │
│ 2. Navigate to "Log Activity" form                               │
│ 3. Pre-fill activity name, type, duration from AI suggestion    │
│ 4. Optionally select additional children for group activity      │
└─────────────────────────────────────────────────────────────────┘
```

### UI Components

#### Activity Suggestion Card

```typescript
// Teacher App - ActivitySuggestionCard.tsx
interface ActivitySuggestion {
  activity: {
    id: string;
    name: string;
    description: string;
    activity_type: ActivityType;
    difficulty: 'easy' | 'medium' | 'hard';
    duration_minutes: number;
    materials_needed: string[];
    age_range: { min_months: number; max_months: number } | null;
    special_needs_adaptations: string | null;
  };
  relevance_score: number;
  reasoning: string;
}

// Render each suggestion card
<ActivitySuggestionCard
  suggestion={suggestion}
  onSelect={(activity) => {
    navigation.navigate('LogActivity', {
      prefill: {
        activityName: activity.name,
        activityType: mapToGibbonType(activity.activity_type),
        duration: activity.duration_minutes,
        aiSuggested: true,
        aiActivityID: activity.id,
      },
      childID: selectedChildID,
    });
  }}
/>
```

#### Activity Type Mapping

The AI Service uses different activity type names than Gibbon's CareTracking module:

| AI Service Type | Gibbon CareTracking Type |
|-----------------|-------------------------|
| `cognitive` | `Math`, `Science` |
| `motor` | `Physical` |
| `social` | `Social` |
| `language` | `Language` |
| `creative` | `Art`, `Music` |
| `sensory` | `Free Play` |

```typescript
// Type mapping function
function mapToGibbonType(aiType: ActivityType): string {
  const mapping: Record<ActivityType, string> = {
    cognitive: 'Math',
    motor: 'Physical',
    social: 'Social',
    language: 'Language',
    creative: 'Art',
    sensory: 'Free Play',
  };
  return mapping[aiType] || 'Other';
}
```

### Selection Tracking

When an educator selects an AI suggestion, we track this for analytics:

```http
POST /api/v1/analytics/activity-selection
Content-Type: application/json
Authorization: Bearer <jwt_token>

{
  "activity_id": "550e8400-e29b-41d4-a716-446655440001",
  "child_id": "123e4567-e89b-12d3-a456-426614174000",
  "recommendation_session_id": "sess-abc123",
  "relevance_score": 0.92,
  "position_in_list": 1,
  "educator_id": 1001,
  "timestamp": "2026-02-15T10:35:00Z"
}
```

### Expected Outcome
- Educator can review multiple suggestions
- Selected activity pre-fills the logging form
- AI activity ID is retained for analytics

---

## Step 5: Record Participation

### User Action
Educator logs the child's participation in the selected activity.

### Flow Diagram

```
Educator completes activity with child
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App - Log Activity Form                                  │
│ Pre-filled fields:                                               │
│ - Activity Name: "Shape Sorting Challenge"                       │
│ - Type: "Math"                                                   │
│ - Duration: 20 minutes                                           │
│ - AI Suggested: Yes                                              │
│                                                                  │
│ Educator fills:                                                   │
│ - Participation Level: Leading / Participating / Observing       │
│ - Actual Duration: (optional override)                           │
│ - Notes: (observations about child's engagement)                 │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ POST /modules/CareTracking/careTracking_addActivity.php         │
│ Body: { gibbonPersonID, activityName, activityType, duration,   │
│         participation, aiSuggested: true, aiActivityID, notes } │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend (CareTracking Module)                            │
│ 1. Validate educator session/permissions                        │
│ 2. Insert into gibbonCareActivity with aiSuggested='Y'          │
│ 3. Store aiActivityID for analytics                              │
│ 4. Trigger AISync webhook via AISyncService::syncActivity()     │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AISync Module (sync.php)                                        │
│ 1. Create sync log entry (status: pending)                      │
│ 2. POST /api/v1/webhook (async)                                 │
│ 3. Payload includes aiSuggested flag and aiActivityID           │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service (webhooks.py)                                         │
│ 1. Process care_activity_created event                          │
│ 2. Update activity usage statistics                              │
│ 3. Track AI suggestion acceptance rate                           │
│ 4. Improve future recommendation model                           │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal receives notification                              │
│ "Sophie participated in Shape Sorting Challenge today!"         │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → Gibbon (Log Activity)

```http
POST /modules/CareTracking/careTracking_addActivity.php
Content-Type: application/json
Authorization: Bearer <session_token>

{
  "gibbonPersonID": 12345,
  "activityType": "Math",
  "activityName": "Shape Sorting Challenge",
  "duration": 20,
  "participation": "Leading",
  "aiSuggested": true,
  "aiActivityID": "550e8400-e29b-41d4-a716-446655440001",
  "notes": "Sophie really enjoyed sorting by color first, then by shape. Showed great concentration!",
  "gibbonPersonIDCreated": 1001
}
```

**Response:**
```json
{
  "success": true,
  "gibbonCareActivityID": 55015,
  "message": "Activity logged successfully"
}
```

#### 2. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: care_activity_created

{
  "event_type": "care_activity_created",
  "entity_type": "care_activity",
  "entity_id": "55015",
  "payload": {
    "child_id": 12345,
    "child_name": "Sophie Martin",
    "child_age_months": 42,
    "activity_name": "Shape Sorting Challenge",
    "activity_type": "Math",
    "duration_minutes": 20,
    "participation": "Leading",
    "ai_suggested": true,
    "ai_activity_id": "550e8400-e29b-41d4-a716-446655440001",
    "educator_id": 1001,
    "notes": "Sophie really enjoyed sorting by color first, then by shape. Showed great concentration!",
    "timestamp": "2026-02-15T10:55:00-05:00"
  },
  "timestamp": "2026-02-15T10:55:05-05:00",
  "gibbon_sync_log_id": 1234
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Care activity for child 12345 processed, AI suggestion tracked",
  "event_type": "care_activity_created",
  "entity_id": "55015",
  "received_at": "2026-02-15T15:55:05.234Z",
  "processing_time_ms": 15.67
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonCareActivity` | INSERT | gibbonPersonID, activityName, activityType, duration, participation, aiSuggested='Y', aiActivityID, notes, timestampCreated |
| `gibbonAISyncLog` | INSERT | eventType='care_activity_created', status='success' |

### Participation Level Codes

| Code | Description | UI Display |
|------|-------------|------------|
| `Leading` | Child actively leads the activity | 🌟 Leading |
| `Participating` | Child actively participates | ✅ Participating |
| `Observing` | Child watches but doesn't participate | 👀 Observing |
| `Not Interested` | Child showed no interest | ⭕ Not Interested |

### Gibbon Activity Gateway Implementation

```php
// gibbon/modules/CareTracking/Domain/ActivityGateway.php

/**
 * Log an activity for a child.
 *
 * @param int $gibbonPersonID Child's ID
 * @param int $gibbonSchoolYearID School year
 * @param string $date Activity date
 * @param string $activityName Name of activity
 * @param string $activityType Type category
 * @param int $recordedByID Educator who logged
 * @param int|null $duration Duration in minutes
 * @param string|null $participation Participation level
 * @param bool $aiSuggested Whether AI suggested this
 * @param string|null $aiActivityID UUID of AI activity
 * @param string|null $notes Educator observations
 * @return int|false New activity ID or false on failure
 */
public function logActivity(
    $gibbonPersonID,
    $gibbonSchoolYearID,
    $date,
    $activityName,
    $activityType,
    $recordedByID,
    $duration = null,
    $participation = null,
    $aiSuggested = false,
    $aiActivityID = null,
    $notes = null
) {
    $data = [
        'gibbonPersonID' => $gibbonPersonID,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'date' => $date,
        'activityName' => $activityName,
        'activityType' => $activityType,
        'recordedByID' => $recordedByID,
        'aiSuggested' => $aiSuggested ? 'Y' : 'N',
    ];

    if ($duration !== null) {
        $data['duration'] = $duration;
    }

    if ($participation !== null) {
        $data['participation'] = $participation;
    }

    if ($aiActivityID !== null) {
        $data['aiActivityID'] = $aiActivityID;
    }

    if ($notes !== null) {
        $data['notes'] = $notes;
    }

    return $this->insert($data);
}
```

### Expected Outcome
- Activity logged with AI suggestion attribution
- Participation level recorded
- AISync webhook notifies AI Service
- Parent receives activity notification
- AI model learns from selection pattern

---

## AI Suggestion Analytics

### Tracking Metrics

The system tracks these metrics to improve AI recommendations:

| Metric | Description | Formula |
|--------|-------------|---------|
| Acceptance Rate | % of suggestions that are selected | Selections / Suggestions Shown |
| Position Bias | Where in the list selected activities appear | Average Position of Selections |
| Type Preference | Which activity types are most selected | Count by Type |
| Age Appropriateness | How well age filtering works | Success Rate per Age Band |
| Engagement Score | Participation levels for AI-suggested activities | Leading/Participating % |

### SQL Query for AI Suggestion Analytics

```sql
-- AI Suggestion acceptance and engagement metrics
SELECT
    DATE(timestampCreated) as date,
    COUNT(*) as total_activities,
    SUM(CASE WHEN aiSuggested = 'Y' THEN 1 ELSE 0 END) as ai_suggested,
    ROUND(SUM(CASE WHEN aiSuggested = 'Y' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as ai_acceptance_rate,
    SUM(CASE WHEN aiSuggested = 'Y' AND participation IN ('Leading', 'Participating') THEN 1 ELSE 0 END) as ai_active_engagement,
    ROUND(AVG(CASE WHEN aiSuggested = 'Y' THEN duration ELSE NULL END), 1) as ai_avg_duration,
    ROUND(AVG(CASE WHEN aiSuggested = 'N' THEN duration ELSE NULL END), 1) as manual_avg_duration
FROM gibbonCareActivity
WHERE gibbonSchoolYearID = :schoolYearID
AND timestampCreated >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(timestampCreated)
ORDER BY date DESC;
```

### Feedback Loop

```
┌─────────────────────────────────────────────────────────────────┐
│                    AI Recommendation Feedback Loop               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐          │
│  │   Request   │───▶│   Generate  │───▶│   Display   │          │
│  │ Suggestions │    │   Ranked    │    │   to User   │          │
│  └─────────────┘    │   List      │    └──────┬──────┘          │
│                     └─────────────┘           │                  │
│                                               │                  │
│                                               ▼                  │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐          │
│  │   Update    │◀───│   Analyze   │◀───│   User      │          │
│  │   Model     │    │   Selection │    │   Selects   │          │
│  └─────────────┘    │   Patterns  │    └─────────────┘          │
│                     └─────────────┘                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Error Handling

### Common Error Scenarios

| Scenario | Error Code | Handling |
|----------|------------|----------|
| AI Service unavailable | `503 Service Unavailable` | Show cached/fallback suggestions |
| Invalid JWT | `401 Unauthorized` | Refresh token and retry |
| Invalid child age | `422 Validation Error` | Show error, prompt age verification |
| No activities match filters | `200 OK` (empty list) | Display "No matching activities" message |
| Rate limited | `429 Too Many Requests` | Wait and retry with backoff |
| Network timeout | `CONNECTION_ERROR` | Retry up to 3 times |

### Error Response Handling

```typescript
// Teacher App - Error handling for AI suggestions
async function getActivitySuggestions(childId: string, ageMonths: number) {
  try {
    const response = await aiService.getRecommendations(childId, {
      child_age_months: ageMonths
    });
    return response.recommendations;
  } catch (error) {
    if (error.status === 401) {
      // Token expired - refresh and retry
      await authService.refreshToken();
      return getActivitySuggestions(childId, ageMonths);
    }

    if (error.status === 503) {
      // AI service down - return cached suggestions
      return getCachedSuggestions(childId, ageMonths);
    }

    if (error.code === 'NETWORK_ERROR') {
      // Network issue - show offline suggestions
      return getOfflineFallbackActivities(ageMonths);
    }

    // Unknown error - log and show generic message
    logError('AI suggestion error', error);
    throw new Error('Unable to get activity suggestions. Please try again.');
  }
}
```

### Fallback Suggestions

When AI Service is unavailable, the system can provide static age-appropriate suggestions:

```typescript
const fallbackActivities: Record<string, Activity[]> = {
  'infant': [
    { name: 'Tummy Time', type: 'Physical', ageRange: { min: 0, max: 12 } },
    { name: 'Sensory Bottles', type: 'Sensory', ageRange: { min: 3, max: 12 } },
  ],
  'toddler': [
    { name: 'Block Stacking', type: 'Math', ageRange: { min: 12, max: 24 } },
    { name: 'Simple Puzzles', type: 'Cognitive', ageRange: { min: 18, max: 30 } },
  ],
  'preschool': [
    { name: 'Art Station', type: 'Art', ageRange: { min: 24, max: 60 } },
    { name: 'Story Circle', type: 'Language', ageRange: { min: 30, max: 60 } },
  ],
};

function getOfflineFallbackActivities(ageMonths: number): Activity[] {
  if (ageMonths < 12) return fallbackActivities.infant;
  if (ageMonths < 24) return fallbackActivities.toddler;
  return fallbackActivities.preschool;
}
```

---

## Testing Checklist

### Manual Verification Steps

- [ ] **Child Profile View**
  - [ ] Navigate to child profile in Teacher App
  - [ ] Verify age displays correctly
  - [ ] Verify recent activities show
  - [ ] Verify "Get AI Suggestions" button is visible

- [ ] **AI Suggestion Request**
  - [ ] Tap "Get AI Suggestions"
  - [ ] Verify loading state displays
  - [ ] Verify recommendations appear
  - [ ] Verify relevance scores shown
  - [ ] Verify reasoning is helpful

- [ ] **Age Filtering Verification**
  - [ ] Test with infant (0-12 months) - verify infant-appropriate activities
  - [ ] Test with toddler (12-24 months) - verify toddler activities
  - [ ] Test with preschooler (36-48 months) - verify age range matches
  - [ ] Test boundary case: child at exact min age
  - [ ] Test boundary case: child at exact max age
  - [ ] Verify activities outside age range are NOT shown

- [ ] **Activity Selection**
  - [ ] Tap "Select" on a suggestion
  - [ ] Verify form pre-fills with activity details
  - [ ] Verify AI suggested flag is set
  - [ ] Verify AI activity ID is captured

- [ ] **Participation Recording**
  - [ ] Select participation level
  - [ ] Add optional notes
  - [ ] Submit activity log
  - [ ] Verify success message
  - [ ] Verify activity appears in child's history with 🤖 icon

- [ ] **AISync Webhook**
  - [ ] Verify webhook fires after activity logged
  - [ ] Check gibbonAISyncLog for successful entry
  - [ ] Verify AI Service receives payload
  - [ ] Verify ai_suggested field is true in payload

- [ ] **Parent Notification**
  - [ ] Verify parent receives notification of activity
  - [ ] Verify notification includes activity details

### Automated Test Commands

```bash
# AI Service activity recommendation tests
cd ai-service && pytest tests/test_activities.py -v

# Age filtering specific tests
cd ai-service && pytest tests/test_activities.py -v -k "age"

# Webhook integration tests
cd ai-service && pytest tests/test_webhooks.py -v -k "care_activity"

# Full test suite
cd ai-service && pytest tests/ -v
```

### Edge Case Tests

```bash
# Test age boundary: child at exact minimum
curl -X GET "http://localhost:8000/api/v1/activities/recommendations/test-child?child_age_months=36" \
  -H "Authorization: Bearer $TOKEN"

# Test age boundary: child at exact maximum
curl -X GET "http://localhost:8000/api/v1/activities/recommendations/test-child?child_age_months=60" \
  -H "Authorization: Bearer $TOKEN"

# Test with missing age (should return all activities)
curl -X GET "http://localhost:8000/api/v1/activities/recommendations/test-child" \
  -H "Authorization: Bearer $TOKEN"

# Test with very young infant (should only return 0-12 month activities)
curl -X GET "http://localhost:8000/api/v1/activities/recommendations/test-child?child_age_months=6" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Appendix

### Environment Variables

| Variable | Service | Description |
|----------|---------|-------------|
| `AI_SERVICE_URL` | gibbon, teacher-app | URL to AI service (e.g., `http://ai-service:8000`) |
| `JWT_SECRET_KEY` | gibbon, ai-service | Shared secret for JWT tokens (min 32 chars) |
| `JWT_ALGORITHM` | gibbon, ai-service | JWT algorithm (default: `HS256`) |
| `RECOMMENDATION_CACHE_TTL` | ai-service | Cache duration for recommendations (default: 300s) |

### Activity Type Enum Reference

```python
class ActivityType(str, Enum):
    """Types of educational activities."""
    COGNITIVE = "cognitive"  # Pattern recognition, problem solving
    MOTOR = "motor"          # Gross/fine motor skills
    SOCIAL = "social"        # Cooperation, sharing, communication
    LANGUAGE = "language"    # Vocabulary, speech, literacy
    CREATIVE = "creative"    # Art, music, imagination
    SENSORY = "sensory"      # Touch, smell, taste exploration
```

### Related Documentation

- [AI Service Activities Router](../../../ai-service/app/routers/activities.py)
- [Activity Schemas](../../../ai-service/app/schemas/activity.py)
- [CareTracking Module](../../../gibbon/modules/CareTracking/)
- [AISync Module](../../../gibbon/modules/AISync/)

### Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2026-02-15 | 1.0 | auto-claude | Initial E2E documentation |
