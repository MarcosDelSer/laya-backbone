<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\System\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\System\Domain\SetupWizardManager;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * SetupWizardManager Test Suite
 *
 * Tests for the setup wizard manager including resume capability,
 * step navigation, and wizard completion.
 */
class SetupWizardManagerTest extends TestCase
{
    protected $manager;
    protected $settingGateway;
    protected $pdo;
    protected $installationDetector;

    protected function setUp(): void
    {
        // Create mocks
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->pdo = $this->createMock(\PDO::class);
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create manager instance
        $this->manager = new SetupWizardManager(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    public function testGetStepsReturnsAllSteps()
    {
        $steps = $this->manager->getSteps();

        $this->assertIsArray($steps);
        $this->assertGreaterThan(0, count($steps));
        $this->assertArrayHasKey('organization_info', $steps);
        $this->assertArrayHasKey('admin_account', $steps);
        $this->assertArrayHasKey('operating_hours', $steps);
        $this->assertArrayHasKey('groups_rooms', $steps);
        $this->assertArrayHasKey('finance_settings', $steps);
        $this->assertArrayHasKey('service_connectivity', $steps);
        $this->assertArrayHasKey('sample_data', $steps);
        $this->assertArrayHasKey('completion', $steps);
    }

    public function testGetStepReturnsCorrectStep()
    {
        $step = $this->manager->getStep('organization_info');

        $this->assertIsArray($step);
        $this->assertEquals('organization_info', $step['id']);
        $this->assertEquals('Organization Information', $step['name']);
        $this->assertEquals('OrganizationInfoStep', $step['class']);
        $this->assertTrue($step['required']);
    }

    public function testGetStepReturnsNullForInvalidStep()
    {
        $step = $this->manager->getStep('invalid_step');
        $this->assertNull($step);
    }

    public function testGetCurrentStepReturnsFirstStepWhenNoneCompleted()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock no wizard progress
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        // Mock no steps completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $currentStep = $this->manager->getCurrentStep();

        $this->assertIsArray($currentStep);
        $this->assertEquals('organization_info', $currentStep['id']);
        $this->assertFalse($currentStep['isCompleted']);
        $this->assertTrue($currentStep['canAccess']);
    }

    public function testGetCurrentStepReturnsNullWhenWizardCompleted()
    {
        // Mock wizard completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        $currentStep = $this->manager->getCurrentStep();
        $this->assertNull($currentStep);
    }

    public function testGetCurrentStepReturnsSecondStepWhenFirstCompleted()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock first step completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard' && $name === 'organization_info_completed') {
                    return 'Y';
                }
                return 'N';
            });

        $currentStep = $this->manager->getCurrentStep();

        $this->assertIsArray($currentStep);
        $this->assertEquals('admin_account', $currentStep['id']);
    }

    public function testResumeCapabilityFromMiddleStep()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock first three steps completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard') {
                    if (in_array($name, [
                        'organization_info_completed',
                        'admin_account_completed',
                        'operating_hours_completed'
                    ])) {
                        return 'Y';
                    }
                }
                return 'N';
            });

        $currentStep = $this->manager->getCurrentStep();

        // Should resume from groups_rooms step
        $this->assertIsArray($currentStep);
        $this->assertEquals('groups_rooms', $currentStep['id']);
        $this->assertFalse($currentStep['isCompleted']);
    }

    public function testResumeCapabilityFromLastStep()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock all steps except completion completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard') {
                    if (in_array($name, [
                        'organization_info_completed',
                        'admin_account_completed',
                        'operating_hours_completed',
                        'groups_rooms_completed',
                        'finance_settings_completed',
                        'service_connectivity_completed',
                        'sample_data_completed'
                    ])) {
                        return 'Y';
                    }
                }
                return 'N';
            });

        $currentStep = $this->manager->getCurrentStep();

        // Should resume from completion step
        $this->assertIsArray($currentStep);
        $this->assertEquals('completion', $currentStep['id']);
    }

    public function testGetNextStepReturnsCorrectStep()
    {
        $nextStep = $this->manager->getNextStep('organization_info');

        $this->assertIsArray($nextStep);
        $this->assertEquals('admin_account', $nextStep['id']);
    }

    public function testGetNextStepReturnsNullAtEnd()
    {
        $nextStep = $this->manager->getNextStep('completion');
        $this->assertNull($nextStep);
    }

    public function testGetNextStepReturnsNullForInvalidStep()
    {
        $nextStep = $this->manager->getNextStep('invalid_step');
        $this->assertNull($nextStep);
    }

    public function testGetPreviousStepReturnsCorrectStep()
    {
        $prevStep = $this->manager->getPreviousStep('admin_account');

        $this->assertIsArray($prevStep);
        $this->assertEquals('organization_info', $prevStep['id']);
    }

    public function testGetPreviousStepReturnsNullAtStart()
    {
        $prevStep = $this->manager->getPreviousStep('organization_info');
        $this->assertNull($prevStep);
    }

    public function testGetPreviousStepReturnsNullForInvalidStep()
    {
        $prevStep = $this->manager->getPreviousStep('invalid_step');
        $this->assertNull($prevStep);
    }

    public function testIsStepCompletedReturnsTrueForCompletedStep()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard' && $name === 'organization_info_completed') {
                    return 'Y';
                }
                return 'N';
            });

        $isCompleted = $this->manager->isStepCompleted('organization_info');
        $this->assertTrue($isCompleted);
    }

    public function testIsStepCompletedReturnsFalseForIncompleteStep()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $isCompleted = $this->manager->isStepCompleted('organization_info');
        $this->assertFalse($isCompleted);
    }

    public function testCanAccessStepReturnsTrueForFirstStep()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $canAccess = $this->manager->canAccessStep('organization_info');
        $this->assertTrue($canAccess);
    }

    public function testCanAccessStepReturnsFalseWhenPreviousStepNotCompleted()
    {
        // Mock first step not completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $canAccess = $this->manager->canAccessStep('admin_account');
        $this->assertFalse($canAccess);
    }

    public function testCanAccessStepReturnsTrueWhenPreviousStepCompleted()
    {
        // Mock first step completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard' && $name === 'organization_info_completed') {
                    return 'Y';
                }
                return 'N';
            });

        $canAccess = $this->manager->canAccessStep('admin_account');
        $this->assertTrue($canAccess);
    }

    public function testCanAccessStepReturnsFalseForInvalidStep()
    {
        $canAccess = $this->manager->canAccessStep('invalid_step');
        $this->assertFalse($canAccess);
    }

    public function testCanAccessOptionalStepWhenRequiredStepsCompleted()
    {
        // Mock required steps before sample_data completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard') {
                    if (in_array($name, [
                        'organization_info_completed',
                        'admin_account_completed',
                        'operating_hours_completed',
                        'groups_rooms_completed',
                        'finance_settings_completed',
                        'service_connectivity_completed'
                    ])) {
                        return 'Y';
                    }
                }
                return 'N';
            });

        $canAccess = $this->manager->canAccessStep('sample_data');
        $this->assertTrue($canAccess);
    }

    public function testGetCompletedStepsReturnsEmptyArrayWhenNoneCompleted()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $completed = $this->manager->getCompletedSteps();
        $this->assertIsArray($completed);
        $this->assertEmpty($completed);
    }

    public function testGetCompletedStepsReturnsAllCompletedSteps()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard') {
                    if (in_array($name, [
                        'organization_info_completed',
                        'admin_account_completed'
                    ])) {
                        return 'Y';
                    }
                }
                return 'N';
            });

        $completed = $this->manager->getCompletedSteps();
        $this->assertIsArray($completed);
        $this->assertContains('organization_info', $completed);
        $this->assertContains('admin_account', $completed);
        $this->assertCount(2, $completed);
    }

    public function testGetStepDataReturnsEmptyArrayWhenNoData()
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn(null);

        $data = $this->manager->getStepData('organization_info');
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function testGetStepDataReturnsDataFromWizardProgress()
    {
        $expectedData = ['name' => 'Test Daycare'];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => $expectedData
                ]
            ]);

        $data = $this->manager->getStepData('organization_info');
        $this->assertEquals($expectedData, $data);
    }

    public function testGetStepDataReturnsDataFromSettings()
    {
        $expectedData = ['name' => 'Test Daycare'];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn(json_encode($expectedData));

        $data = $this->manager->getStepData('organization_info');
        $this->assertEquals($expectedData, $data);
    }

    public function testSaveStepDataSavesCorrectly()
    {
        $stepData = ['name' => 'Test Daycare'];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => []
            ]);

        $this->installationDetector
            ->expects($this->once())
            ->method('saveWizardProgress')
            ->with(
                $this->equalTo('organization_info'),
                $this->equalTo(['organization_info' => $stepData])
            )
            ->willReturn(true);

        $result = $this->manager->saveStepData('organization_info', $stepData);
        $this->assertTrue($result);
    }

    public function testSaveStepDataMergesWithExistingData()
    {
        $existingData = ['admin_account' => ['email' => 'admin@test.com']];
        $newData = ['name' => 'Test Daycare'];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => $existingData
            ]);

        $this->installationDetector
            ->expects($this->once())
            ->method('saveWizardProgress')
            ->with(
                $this->equalTo('organization_info'),
                $this->equalTo([
                    'admin_account' => ['email' => 'admin@test.com'],
                    'organization_info' => $newData
                ])
            )
            ->willReturn(true);

        $result = $this->manager->saveStepData('organization_info', $newData);
        $this->assertTrue($result);
    }

    public function testGetCompletionPercentageReturnsZeroWhenNoneCompleted()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $percentage = $this->manager->getCompletionPercentage();
        $this->assertEquals(0, $percentage);
    }

    public function testGetCompletionPercentageReturnsCorrectValue()
    {
        // Mock 3 out of 7 required steps completed (sample_data is optional)
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard') {
                    if (in_array($name, [
                        'organization_info_completed',
                        'admin_account_completed',
                        'operating_hours_completed'
                    ])) {
                        return 'Y';
                    }
                }
                return 'N';
            });

        $percentage = $this->manager->getCompletionPercentage();
        // 3 out of 7 required steps = ~43%
        $this->assertGreaterThanOrEqual(40, $percentage);
        $this->assertLessThanOrEqual(45, $percentage);
    }

    public function testGetCompletionPercentageReturns100WhenAllCompleted()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('Y');

        $percentage = $this->manager->getCompletionPercentage();
        $this->assertEquals(100, $percentage);
    }

    public function testAreAllRequiredStepsCompletedReturnsFalseWhenIncomplete()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $result = $this->manager->areAllRequiredStepsCompleted();
        $this->assertFalse($result);
    }

    public function testAreAllRequiredStepsCompletedReturnsTrueWhenAllCompleted()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('Y');

        $result = $this->manager->areAllRequiredStepsCompleted();
        $this->assertTrue($result);
    }

    public function testAreAllRequiredStepsCompletedIgnoresOptionalSteps()
    {
        // Mock all required steps completed, but sample_data (optional) not completed
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnCallback(function ($scope, $name) {
                if ($scope === 'SetupWizard' && $name === 'sample_data_completed') {
                    return 'N';
                }
                return 'Y';
            });

        $result = $this->manager->areAllRequiredStepsCompleted();
        $this->assertTrue($result);
    }

    public function testCompleteWizardFailsWhenStepsIncomplete()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('N');

        $result = $this->manager->completeWizard();
        $this->assertFalse($result);
    }

    public function testCompleteWizardSucceedsWhenAllStepsComplete()
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturn('Y');

        $this->installationDetector
            ->expects($this->once())
            ->method('markWizardCompleted')
            ->willReturn(true);

        $result = $this->manager->completeWizard();
        $this->assertTrue($result);
    }

    public function testResetWizardCallsInstallationDetector()
    {
        $this->installationDetector
            ->expects($this->once())
            ->method('resetWizard')
            ->willReturn(true);

        $result = $this->manager->resetWizard();
        $this->assertTrue($result);
    }

    public function testWizardFlowSequence()
    {
        // Test a complete wizard flow sequence
        $expectedSequence = [
            'organization_info',
            'admin_account',
            'operating_hours',
            'groups_rooms',
            'finance_settings',
            'service_connectivity',
            'sample_data',
            'completion'
        ];

        $steps = $this->manager->getSteps();
        $actualSequence = array_keys($steps);

        $this->assertEquals($expectedSequence, $actualSequence);
    }

    public function testNavigationThroughAllSteps()
    {
        $steps = $this->manager->getSteps();
        $stepIds = array_keys($steps);

        // Test forward navigation
        for ($i = 0; $i < count($stepIds) - 1; $i++) {
            $nextStep = $this->manager->getNextStep($stepIds[$i]);
            $this->assertNotNull($nextStep);
            $this->assertEquals($stepIds[$i + 1], $nextStep['id']);
        }

        // Test backward navigation
        for ($i = count($stepIds) - 1; $i > 0; $i--) {
            $prevStep = $this->manager->getPreviousStep($stepIds[$i]);
            $this->assertNotNull($prevStep);
            $this->assertEquals($stepIds[$i - 1], $prevStep['id']);
        }
    }

    public function testResumeAfterInterruptionAtEachStep()
    {
        // Test resume capability at each step
        $stepIds = [
            'organization_info',
            'admin_account',
            'operating_hours',
            'groups_rooms',
            'finance_settings',
            'service_connectivity',
            'sample_data'
        ];

        foreach ($stepIds as $index => $expectedNextStep) {
            // Mock wizard not completed
            $this->installationDetector = $this->createMock(InstallationDetector::class);
            $this->installationDetector
                ->method('isWizardCompleted')
                ->willReturn(false);

            // Create new manager instance with updated mock
            $manager = new SetupWizardManager(
                $this->settingGateway,
                $this->pdo,
                $this->installationDetector
            );

            // Mock steps completed up to but not including this one
            $this->settingGateway = $this->createMock(SettingGateway::class);
            $completedUpTo = $index;
            $this->settingGateway
                ->method('getSettingByScope')
                ->willReturnCallback(function ($scope, $name) use ($stepIds, $completedUpTo) {
                    if ($scope === 'SetupWizard') {
                        for ($i = 0; $i < $completedUpTo; $i++) {
                            if ($name === $stepIds[$i] . '_completed') {
                                return 'Y';
                            }
                        }
                    }
                    return 'N';
                });

            // Re-create manager with new mocks
            $manager = new SetupWizardManager(
                $this->settingGateway,
                $this->pdo,
                $this->installationDetector
            );

            $currentStep = $manager->getCurrentStep();
            $this->assertIsArray($currentStep);
            $this->assertEquals($expectedNextStep, $currentStep['id'],
                "Expected to resume at step: $expectedNextStep");
        }
    }
}
