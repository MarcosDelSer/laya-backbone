# Development Seed Data Script

## Overview

The `seed_data.php` script creates comprehensive sample data for development and testing of the LAYA daycare management system.

## What It Creates

### Organization Structure
- **3 Form Groups (Roll Groups)**:
  - Infants (0-2 years)
  - Toddlers (2-4 years)
  - Preschool (4-6 years)

### People
- **5 Staff Members**: Director, Lead Educator, Educators, and Assistant Educator
- **15 Families**: With both mother and father
- **20 Children**: Distributed across the three age groups
  - 7 Infants (0-2 years)
  - 6 Toddlers (2-4 years)
  - 7 Preschool (4-6 years)

### Care Records (10 days of data per child)
- **Attendance**: Check-in/check-out times
- **Meals**: 2-3 meals per day (breakfast, snacks, lunch, etc.)
- **Naps**: Duration and quality
- **Diaper Changes**: For younger children (2-4 changes per day)
- **Incidents**: Minor injuries, illness, behavioral (10% occurrence rate)
- **Activities**: 2-3 activities per day across various types:
  - Art (Finger Painting, Playdough, Collage, Drawing)
  - Music (Instruments, Singing, Dance, Rhythm)
  - Physical (Obstacle Course, Ball Games, Climbing)
  - Language (Story Time, Show and Tell, Letter Recognition)
  - Math (Counting, Shape Sorting, Pattern Building)
  - Science (Nature Exploration, Experiments, Sensory Play)
  - Social (Circle Time, Cooperative Games, Role Play)
  - Free Play (Block Building, Dramatic Play, Puzzles)

## Usage

### Basic Usage

```bash
# From the gibbon/modules directory
php seed_data.php

# Or from anywhere with full path
php /path/to/gibbon/modules/seed_data.php
```

### Options

```bash
# Show help
php seed_data.php --help

# Run with verbose output
php seed_data.php --verbose

# Reset existing seed data before creating new
php seed_data.php --reset
```

## Prerequisites

1. **Gibbon Installation**: Must have a working Gibbon installation
2. **Database Connection**: Gibbon must be configured with database access
3. **Current School Year**: At least one school year must exist with 'Current' status
4. **CareTracking Module**: The CareTracking module tables must be installed

## Idempotency

The script is **idempotent** - it checks for existing records before inserting:
- Uses email addresses to identify existing people
- Checks for existing form groups by name
- Checks for existing care records by date and person
- Safe to run multiple times without creating duplicates

## Sample Data Identifiers

### Email Patterns
- **Staff**: `firstname.lastname@laya.test`
- **Parents**: `firstname.lastname@example.com`
- **Children**: `firstname.lastname@example.com`

### Test Data Markers
All seed data can be identified by:
- Email domains: `@laya.test` for staff, `@example.com` for families
- Family names: Contain "Test Family"
- Phone numbers: Start with 555-

## Reset/Cleanup

To remove all seed data:

```bash
php seed_data.php --reset
```

This will:
1. Delete all care records for test users
2. Delete student enrollments
3. Delete family relationships
4. Delete staff records
5. Delete person records for test users

## Integration with AI Service

After running this script, you should also run the AI service seed script:

```bash
cd ai-service
python scripts/seed.py
```

This creates complementary data in the AI service database:
- 20 children records
- 15 families
- 50+ activities
- Activity participations
- Coaching sessions
- Parent reports
- Communication preferences

## Verification

After running the script, verify in Gibbon UI:
1. Navigate to **People > Manage Families**
2. Check **Students > Student Enrolment**
3. View **Care Tracking > Dashboard**
4. Check attendance, meals, naps, and activities

## Troubleshooting

### "No current school year found"
- Go to **School Admin > Manage School Years**
- Ensure at least one school year has status 'Current'

### Database connection errors
- Verify Gibbon config.php has correct database credentials
- Ensure database server is running

### Permission errors
- Make sure the script is executable: `chmod +x seed_data.php`
- Run from a user with database write permissions

## Technical Details

### Database Tables Used

**Core Gibbon Tables:**
- `gibbonPerson`
- `gibbonFamily`
- `gibbonFamilyAdult`
- `gibbonFamilyChild`
- `gibbonRollGroup`
- `gibbonStudentEnrolment`
- `gibbonStaff`
- `gibbonSchoolYear`

**CareTracking Module Tables:**
- `gibbonCareAttendance`
- `gibbonCareMeal`
- `gibbonCareNap`
- `gibbonCareDiaper`
- `gibbonCareIncident`
- `gibbonCareActivity`

### Transaction Safety

The script uses database transactions:
- All inserts are wrapped in a transaction
- Automatic rollback on any error
- Ensures data consistency

### Performance

Typical execution time:
- Initial run: 2-5 seconds
- With --reset: 3-7 seconds
- Creates ~1000+ database records

## Support

For issues or questions:
1. Check Gibbon logs
2. Run with `--verbose` for detailed output
3. Verify prerequisites are met
4. Check database connectivity
