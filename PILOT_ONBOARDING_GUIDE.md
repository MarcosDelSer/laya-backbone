# Pilot Daycare Onboarding Guide

## Overview

This guide helps you onboard a real pilot daycare center to the LAYA system. Unlike the development seed scripts (which create fake test data), these onboarding scripts help you set up a production daycare with real data.

## Onboarding Process

### Phase 1: Data Collection (Week 1)
Use the data collection templates to gather information from the pilot daycare.

### Phase 2: System Setup (Week 2)
Configure the LAYA system with the collected data.

### Phase 3: Testing & Training (Week 3-4)
Verify the setup and train staff on system usage.

### Phase 4: Go Live (Week 5)
Launch the system for daily operations.

## Prerequisites

Before starting the onboarding process:

- [ ] LAYA system deployed and accessible
- [ ] PostgreSQL database configured (ai-service)
- [ ] MySQL/MariaDB database configured (Gibbon)
- [ ] All migrations applied successfully
- [ ] Admin credentials created
- [ ] SSL certificates configured (for production)

## Files in This Onboarding Package

| File | Purpose |
|------|---------|
| `PILOT_ONBOARDING_GUIDE.md` | This guide (onboarding process overview) |
| `PILOT_DATA_COLLECTION_TEMPLATE.md` | Data collection checklist and forms |
| `ai-service/scripts/onboard_pilot.py` | Python script to import pilot data |
| `gibbon/modules/onboard_pilot.php` | PHP script to set up Gibbon for pilot |
| `ai-service/tests/test_pilot_onboarding.py` | Tests for onboarding scripts |

## Quick Start

### Step 1: Collect Data

```bash
# Review and fill out the data collection template
cat PILOT_DATA_COLLECTION_TEMPLATE.md
```

### Step 2: Prepare Data Files

Create JSON/CSV files with the collected data:
- `pilot_data/organization.json` - Daycare information
- `pilot_data/staff.json` - Staff members
- `pilot_data/families.csv` - Family information
- `pilot_data/children.csv` - Children information

### Step 3: Run Onboarding Scripts

```bash
# Configure Gibbon first (organization, staff, families, children)
php gibbon/modules/onboard_pilot.php --data-dir pilot_data

# Then configure AI service (sync data from Gibbon)
cd ai-service
python scripts/onboard_pilot.py --data-dir ../pilot_data
```

### Step 4: Verify Setup

```bash
# Verify the onboarding was successful
cd ai-service
python scripts/onboard_pilot.py --verify

# Run data integrity checks
python scripts/onboard_pilot.py --check-integrity
```

### Step 5: Train Staff

- Schedule training sessions for daycare staff
- Provide access credentials
- Demonstrate key workflows
- Answer questions and address concerns

## Data Collection

### Organization Information

Collect the following from the daycare center:

- **Basic Information**
  - Legal name
  - Operating name (DBA)
  - Address (street, city, state/province, postal code, country)
  - Phone number(s)
  - Email address(es)
  - Website
  - License/Registration number

- **Operating Details**
  - Hours of operation
  - Days of operation
  - Age groups served
  - Total capacity
  - Capacity by age group

- **Contact Persons**
  - Director/Owner name and contact
  - Administrative contact
  - Emergency contact

### Staff Information

For each staff member:
- Full name (first, middle, last)
- Email address (for login)
- Phone number(s)
- Role/Position (Director, Lead Educator, Educator, Assistant, etc.)
- Qualifications/Certifications
- Start date
- Emergency contact information

### Family Information

For each family:
- **Parents/Guardians** (at least one, up to two)
  - Full name
  - Relationship to child
  - Email address (for portal access)
  - Phone number(s)
  - Address (if different from child)
  - Preferred language
  - Communication preferences (email, SMS, phone, app)

### Children Information

For each child:
- Full name
- Date of birth
- Gender
- Home address
- Allergies/Medical conditions
- Special needs/Accommodations
- Emergency contacts
- Authorized pickup persons
- Start date
- Assigned age group/classroom

### Existing Records (Optional)

If the daycare has existing records to import:
- Historical attendance records
- Past incident reports
- Previous assessments
- Medical/Allergy information
- Parent communications

## Data File Formats

### organization.json

```json
{
  "legal_name": "ABC Daycare Center Inc.",
  "operating_name": "Little Stars Daycare",
  "address": {
    "street": "123 Main Street",
    "city": "Toronto",
    "province": "ON",
    "postal_code": "M5V 1A1",
    "country": "Canada"
  },
  "contact": {
    "phone": "416-555-0100",
    "email": "info@littlestars.ca",
    "website": "https://www.littlestars.ca"
  },
  "license_number": "ON-DC-12345",
  "hours": {
    "weekday": "7:00 AM - 6:00 PM",
    "weekend": "Closed"
  },
  "capacity": {
    "total": 60,
    "infants": 15,
    "toddlers": 20,
    "preschool": 25
  }
}
```

### staff.json

```json
[
  {
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane.smith@littlestars.ca",
    "phone": "416-555-0101",
    "role": "Director",
    "qualifications": "Early Childhood Education Diploma",
    "start_date": "2020-01-15"
  },
  {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@littlestars.ca",
    "phone": "416-555-0102",
    "role": "Lead Educator",
    "qualifications": "ECE Certificate",
    "start_date": "2021-03-01"
  }
]
```

### families.csv

```csv
parent1_first,parent1_last,parent1_email,parent1_phone,parent2_first,parent2_last,parent2_email,parent2_phone,address,city,province,postal_code,preferred_language
Sarah,Johnson,sarah.j@email.com,416-555-1001,Michael,Johnson,michael.j@email.com,416-555-1002,456 Oak St,Toronto,ON,M4E 2K1,English
Marie,Dubois,marie.d@email.com,514-555-2001,Pierre,Dubois,pierre.d@email.com,514-555-2002,789 Rue Principale,Montreal,QC,H3B 1A1,French
```

### children.csv

```csv
first_name,last_name,date_of_birth,gender,family_email,age_group,allergies,medical_notes,start_date
Emma,Johnson,2022-03-15,Female,sarah.j@email.com,Toddlers,"Peanuts, Dairy",None,2024-09-01
Lucas,Dubois,2023-06-20,Male,marie.d@email.com,Infants,None,"Asthma - has inhaler",2024-09-01
```

## Security & Privacy

### Data Protection

- All pilot data must be treated as confidential
- Use encrypted connections (HTTPS/SSL) for all communications
- Store data files securely and delete after successful import
- Follow GDPR/PIPEDA privacy regulations
- Obtain consent for data processing and storage

### Access Control

- Create strong, unique passwords for all accounts
- Enable two-factor authentication where available
- Limit admin access to authorized personnel only
- Review and audit access logs regularly

### Compliance

- Ensure compliance with local childcare regulations
- Maintain required documentation and records
- Follow data retention policies
- Implement proper backup and disaster recovery procedures

## Troubleshooting

### Common Issues

#### "Database connection failed"
- Verify database credentials in configuration files
- Check that database servers are running
- Ensure network connectivity to database hosts

#### "Email already exists"
- Check if staff/parent already has an account
- Use the `--skip-existing` flag to skip duplicate emails
- Or use `--update-existing` to update existing records

#### "Invalid data format"
- Verify JSON/CSV files match the expected format
- Check for required fields
- Ensure dates are in ISO format (YYYY-MM-DD)

#### "Insufficient permissions"
- Run scripts with appropriate database permissions
- Verify user has INSERT/UPDATE rights on all tables

### Getting Help

If you encounter issues during onboarding:

1. Check the logs: `ai-service/logs/onboarding.log`
2. Review error messages carefully
3. Consult this guide and the API documentation
4. Contact LAYA support: support@laya.example.com

## Post-Onboarding

### Verification Checklist

After running the onboarding scripts:

- [ ] All staff members can log in
- [ ] All families can access the parent portal
- [ ] Children are assigned to correct age groups
- [ ] Organization information is correct
- [ ] Email notifications are working
- [ ] Mobile app access is configured
- [ ] Backup system is operational

### Training Resources

Provide staff with:
- User manuals and quick-start guides
- Video tutorials
- Contact information for support
- Feedback mechanism for issues/suggestions

### Ongoing Support

- Schedule regular check-ins during the first month
- Monitor system usage and adoption
- Collect feedback and address issues promptly
- Plan for system updates and enhancements

## Next Steps

After successful onboarding:

1. **Week 1**: Monitor daily operations closely
2. **Week 2-4**: Gather user feedback and make adjustments
3. **Month 2**: Review analytics and identify improvement areas
4. **Month 3**: Plan for full rollout or additional pilots

## Additional Resources

- [LAYA User Manual](./docs/user-manual.md)
- [Admin Guide](./docs/admin-guide.md)
- [API Documentation](http://localhost:8000/docs)
- [Support Portal](https://support.laya.example.com)

## Appendix

### Sample Pilot Timeline

| Week | Activities |
|------|------------|
| 1 | Data collection, stakeholder meetings |
| 2 | System configuration, data import |
| 3 | User testing, staff training |
| 4 | Final adjustments, go-live preparation |
| 5 | Go live, intensive support |
| 6-8 | Monitoring, feedback collection |
| 9-12 | Optimization, planning for expansion |

### Contact Information

- **Technical Support**: tech-support@laya.example.com
- **Training**: training@laya.example.com
- **General Inquiries**: info@laya.example.com

---

**Document Version**: 1.0
**Last Updated**: 2026-02-15
**Maintained By**: LAYA Development Team
