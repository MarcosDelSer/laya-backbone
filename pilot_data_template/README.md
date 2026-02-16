# Pilot Data Template Files

This directory contains template files showing the expected format for pilot daycare onboarding data.

## Files

- **organization.json** - Daycare organization information
- **staff.json** - Staff members to be onboarded
- **families.csv** - Family and parent information
- **children.csv** - Children to be enrolled

## Usage

1. Copy this template directory to create your pilot data:
   ```bash
   cp -r pilot_data_template my_daycare_data
   ```

2. Edit the files with your actual pilot daycare data

3. Run the onboarding scripts:
   ```bash
   # Validate data first (dry run)
   php gibbon/modules/onboard_pilot.php --data-dir my_daycare_data --dry-run
   python ai-service/scripts/onboard_pilot.py --data-dir my_daycare_data --dry-run

   # If validation passes, import data
   php gibbon/modules/onboard_pilot.php --data-dir my_daycare_data
   python ai-service/scripts/onboard_pilot.py --data-dir my_daycare_data
   ```

## Data Format Guidelines

### organization.json

- All fields are required unless marked optional
- Email and phone must be valid formats
- Address must include all components

### staff.json

- Email must be unique (used for login)
- Role should match existing Gibbon roles
- Dates in YYYY-MM-DD format

### families.csv

- parent1_* fields are required
- parent2_* fields are optional (single parent families)
- Email used for parent portal access

### children.csv

- date_of_birth in YYYY-MM-DD format
- family_email must match a family from families.csv
- age_group: Infants, Toddlers, or Preschool
- allergies and medical_notes are optional but important

## Security Notes

- Keep this data secure and confidential
- Delete after successful onboarding
- Never commit real pilot data to version control
- Use encrypted connections for file transfer

## Support

For assistance with pilot onboarding, see PILOT_ONBOARDING_GUIDE.md or contact support.
