# Setup Wizard Resume Capability

## Overview

The LAYA setup wizard includes comprehensive resume capability that allows users to interrupt the wizard at any point and resume from where they left off when they return.

## How It Works

### Architecture

The resume capability is built on three key components:

1. **InstallationDetector** - Tracks wizard progress in the `gibbonSetupWizard` table
2. **Individual Step Classes** - Each step implements `prepareData()` to restore saved data
3. **SetupWizardManager** - Orchestrates the wizard flow and determines resume point

### Data Persistence

Each wizard step saves its progress in two ways:

1. **Step-specific settings** - Saved to `gibbonSetting` table with scope `SetupWizard`
2. **Wizard progress** - Saved to `gibbonSetupWizard` table with step data as JSON

### Resume Flow

When a user returns to an interrupted wizard:

1. `InstallationDetector.getWizardProgress()` retrieves the last saved progress
2. `SetupWizardManager.getCurrentStep()` determines the first incomplete step
3. Each step's `prepareData()` method restores previously entered data
4. User can continue from where they left off or navigate back to review/edit

## Usage Example

```php
// Initialize the wizard manager
$manager = new SetupWizardManager($settingGateway, $pdo, $installationDetector);

// Get the current step (resumes from last incomplete step)
$currentStep = $manager->getCurrentStep();

if ($currentStep) {
    echo "Resume from step: " . $currentStep['name'];

    // Get previously saved data for this step
    $savedData = $currentStep['data'];

    // User can edit and save
    // ...

    // Move to next step
    $nextStep = $manager->getNextStep($currentStep['id']);
}
```

## Step Navigation

Users can navigate through the wizard:

- **Forward**: `getNextStep($currentStepId)` - Move to next step
- **Backward**: `getPreviousStep($currentStepId)` - Return to previous step
- **Jump**: `canAccessStep($stepId)` - Check if a step can be accessed (requires previous required steps completed)

## Completion Tracking

The wizard tracks completion at two levels:

1. **Individual steps**: Each step has a `_completed` flag in settings
2. **Overall wizard**: The `setupWizardCompleted` setting prevents re-running

## Step Completion Flags

Each step sets a completion flag when saved:

- `organization_info_completed`
- `admin_account_completed`
- `operating_hours_completed`
- `groups_rooms_completed`
- `finance_settings_completed`
- `service_connectivity_completed`
- `sample_data_completed` (optional)

## Progress Percentage

Track overall progress:

```php
$percentage = $manager->getCompletionPercentage();
echo "Wizard is $percentage% complete";
```

## Wizard States

- **Not Started**: No progress data exists
- **In Progress**: Some steps completed, can resume
- **Completed**: All required steps done, wizard marked complete
- **Reset**: Can be reset for testing (use with caution)

## Database Schema

### gibbonSetupWizard Table

```sql
CREATE TABLE gibbonSetupWizard (
    gibbonSetupWizardID INT AUTO_INCREMENT PRIMARY KEY,
    stepCompleted VARCHAR(50),        -- Last completed step ID
    stepData TEXT,                    -- JSON data for all steps
    wizardCompleted ENUM('Y', 'N'),   -- Overall completion flag
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### gibbonSetting Table

Settings with scope `SetupWizard`:
- `{step_id}_completed` - Step completion flags
- Step-specific data (varies by step)

## Testing

The resume capability is thoroughly tested in `SetupWizardManagerTest.php`:

- Resume from each step in the sequence
- Data persistence across interruptions
- Step navigation and access control
- Completion percentage calculation
- Wizard completion validation

## Security Considerations

1. **Data Validation**: All saved data is validated on resume
2. **Access Control**: Users cannot skip required steps
3. **Completion Lock**: Completed wizards cannot be re-run without explicit reset
4. **Data Sanitization**: All input is sanitized before saving

## Best Practices

1. **Save Early, Save Often**: Each step saves data on form submission
2. **Validate on Resume**: Always re-validate data when resuming
3. **Clear Error Messages**: Show clear messages if data is invalid
4. **Allow Navigation**: Let users go back to edit previous steps
5. **Progress Indicator**: Show completion percentage to users

## Limitations

1. Optional steps (like sample data import) don't block wizard completion
2. Wizard can only be reset by administrators or during testing
3. Progress data is not automatically cleaned up after completion
4. Concurrent wizard sessions (multiple users) are not explicitly handled

## Future Enhancements

- Add wizard timeout/expiration
- Support for wizard branching based on choices
- Audit trail of all wizard steps
- Multi-language support for step names
- Wizard templates for different installation types
