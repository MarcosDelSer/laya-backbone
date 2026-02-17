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

namespace Gibbon\Module\PhotoManagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\PhotoManagement\Service\PhotoAccessService;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Contracts\Database\Result;

/**
 * Unit tests for PhotoAccessService.
 *
 * Tests photo access control business logic including role-based permissions,
 * parent access validation, retention period calculations, and file validation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoAccessServiceTest extends TestCase
{
    /**
     * @var PhotoAccessService
     */
    protected $service;

    /**
     * @var PhotoGateway|MockObject
     */
    protected $photoGateway;

    /**
     * @var PhotoTagGateway|MockObject
     */
    protected $photoTagGateway;

    /**
     * @var FamilyGateway|MockObject
     */
    protected $familyGateway;

    /**
     * @var SettingGateway|MockObject
     */
    protected $settingGateway;

    /**
     * Sample photo data for testing.
     *
     * @var array
     */
    protected $samplePhoto;

    /**
     * Sample child data for testing.
     *
     * @var array
     */
    protected $sampleChildren;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock gateways
        $this->photoGateway = $this->createMock(PhotoGateway::class);
        $this->photoTagGateway = $this->createMock(PhotoTagGateway::class);
        $this->familyGateway = $this->createMock(FamilyGateway::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create service instance with mocked dependencies
        $this->service = new PhotoAccessService(
            $this->photoGateway,
            $this->photoTagGateway,
            $this->familyGateway,
            $this->settingGateway
        );

        // Sample photo data
        $this->samplePhoto = [
            'gibbonPhotoUploadID' => 1,
            'uploadedByID' => 100,
            'sharedWithParent' => 'Y',
            'gibbonSchoolYearID' => 2025,
            'status' => 'Active',
            'fileName' => 'photo.jpg',
            'uploadDate' => '2025-01-15',
        ];

        // Sample children data
        $this->sampleChildren = [
            [
                'gibbonPersonID' => 201,
                'preferredName' => 'John',
                'surname' => 'Smith',
                'status' => 'Full',
                'gibbonSchoolYearID' => 2025,
                'image_240' => '/uploads/john.jpg',
            ],
            [
                'gibbonPersonID' => 202,
                'preferredName' => 'Jane',
                'surname' => 'Smith',
                'status' => 'Full',
                'gibbonSchoolYearID' => 2025,
                'image_240' => '/uploads/jane.jpg',
            ],
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
        $this->photoGateway = null;
        $this->photoTagGateway = null;
        $this->familyGateway = null;
        $this->settingGateway = null;
    }

    // =========================================================================
    // ACCESS CONTROL TESTS - canViewPhoto
    // =========================================================================

    /**
     * Test that staff can view all photos.
     */
    public function testStaffCanViewAllPhotos(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->canViewPhoto(1, 100, 'Staff', 2025);

        $this->assertTrue($result, 'Staff should be able to view all photos');
    }

    /**
     * Test that admin can view all photos.
     */
    public function testAdminCanViewAllPhotos(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->canViewPhoto(1, 100, 'Admin', 2025);

        $this->assertTrue($result, 'Admin should be able to view all photos');
    }

    /**
     * Test that canViewPhoto returns false for non-existent photo.
     */
    public function testCannotViewNonExistentPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->canViewPhoto(999, 100, 'Staff', 2025);

        $this->assertFalse($result, 'Should not be able to view non-existent photo');
    }

    /**
     * Test that parent cannot view photo not shared with parents.
     */
    public function testParentCannotViewUnsharedPhoto(): void
    {
        $photo = $this->samplePhoto;
        $photo['sharedWithParent'] = 'N';

        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($photo);

        $result = $this->service->canViewPhoto(1, 100, 'Parent', 2025);

        $this->assertFalse($result, 'Parent should not be able to view unshared photo');
    }

    /**
     * Test that parent can view photo shared with their tagged child.
     */
    public function testParentCanViewSharedPhotoWithTaggedChild(): void
    {
        // Mock family gateway to return children
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($this->sampleChildren);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $result = $this->service->canViewPhoto(1, 100, 'Parent', 2025);

        $this->assertTrue($result, 'Parent should be able to view shared photo with their tagged child');
    }

    /**
     * Test that parent cannot view photo if no children are tagged.
     */
    public function testParentCannotViewPhotoWithNoTaggedChildren(): void
    {
        // Mock family gateway to return children
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($this->sampleChildren);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->willReturn(false);

        $result = $this->service->canViewPhoto(1, 100, 'Parent', 2025);

        $this->assertFalse($result, 'Parent should not be able to view photo without tagged children');
    }

    // =========================================================================
    // ACCESS CONTROL TESTS - canEditPhoto
    // =========================================================================

    /**
     * Test that admin can edit any photo.
     */
    public function testAdminCanEditAnyPhoto(): void
    {
        $result = $this->service->canEditPhoto(1, 100, 'Admin');

        $this->assertTrue($result, 'Admin should be able to edit any photo');
    }

    /**
     * Test that staff can edit their own uploaded photos.
     */
    public function testStaffCanEditOwnPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->canEditPhoto(1, 100, 'Staff');

        $this->assertTrue($result, 'Staff should be able to edit their own photo');
    }

    /**
     * Test that staff cannot edit photos uploaded by others.
     */
    public function testStaffCannotEditOthersPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->canEditPhoto(1, 999, 'Staff');

        $this->assertFalse($result, 'Staff should not be able to edit photos uploaded by others');
    }

    /**
     * Test that parent cannot edit photos.
     */
    public function testParentCannotEditPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->canEditPhoto(1, 100, 'Parent');

        $this->assertFalse($result, 'Parent should not be able to edit photos');
    }

    /**
     * Test that canEditPhoto returns false for non-existent photo.
     */
    public function testCannotEditNonExistentPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->canEditPhoto(999, 100, 'Staff');

        $this->assertFalse($result, 'Should not be able to edit non-existent photo');
    }

    // =========================================================================
    // ACCESS CONTROL TESTS - Upload and Tag Permissions
    // =========================================================================

    /**
     * Test that staff can upload photos.
     */
    public function testStaffCanUploadPhotos(): void
    {
        $result = $this->service->canUploadPhotos('Staff');

        $this->assertTrue($result, 'Staff should be able to upload photos');
    }

    /**
     * Test that admin can upload photos.
     */
    public function testAdminCanUploadPhotos(): void
    {
        $result = $this->service->canUploadPhotos('Admin');

        $this->assertTrue($result, 'Admin should be able to upload photos');
    }

    /**
     * Test that parent cannot upload photos.
     */
    public function testParentCannotUploadPhotos(): void
    {
        $result = $this->service->canUploadPhotos('Parent');

        $this->assertFalse($result, 'Parent should not be able to upload photos');
    }

    /**
     * Test that staff can tag children.
     */
    public function testStaffCanTagChildren(): void
    {
        $result = $this->service->canTagChildren('Staff');

        $this->assertTrue($result, 'Staff should be able to tag children');
    }

    /**
     * Test that admin can tag children.
     */
    public function testAdminCanTagChildren(): void
    {
        $result = $this->service->canTagChildren('Admin');

        $this->assertTrue($result, 'Admin should be able to tag children');
    }

    /**
     * Test that parent cannot tag children.
     */
    public function testParentCannotTagChildren(): void
    {
        $result = $this->service->canTagChildren('Parent');

        $this->assertFalse($result, 'Parent should not be able to tag children');
    }

    /**
     * Test canDeletePhoto uses same logic as canEditPhoto.
     */
    public function testCanDeletePhotoUsesEditPermissions(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $editResult = $this->service->canEditPhoto(1, 100, 'Staff');
        $deleteResult = $this->service->canDeletePhoto(1, 100, 'Staff');

        $this->assertEquals($editResult, $deleteResult, 'Delete permissions should match edit permissions');
    }

    // =========================================================================
    // ACCESSIBLE CHILDREN TESTS
    // =========================================================================

    /**
     * Test getAccessibleChildren for parent returns their children.
     */
    public function testParentGetAccessibleChildrenReturnsTheirChildren(): void
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($this->sampleChildren);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $result = $this->service->getAccessibleChildren(100, 'Parent', 2025);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('childIDs', $result);
        $this->assertArrayHasKey('childInfo', $result);
        $this->assertCount(2, $result['childIDs']);
        $this->assertContains(201, $result['childIDs']);
        $this->assertContains(202, $result['childIDs']);
    }

    /**
     * Test getAccessibleChildren filters by school year.
     */
    public function testGetAccessibleChildrenFiltersBySchoolYear(): void
    {
        $children = $this->sampleChildren;
        $children[0]['gibbonSchoolYearID'] = 2024; // Different year

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($children);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $result = $this->service->getAccessibleChildren(100, 'Parent', 2025);

        $this->assertCount(1, $result['childIDs'], 'Should only return children in current school year');
        $this->assertContains(202, $result['childIDs']);
        $this->assertNotContains(201, $result['childIDs']);
    }

    /**
     * Test getAccessibleChildren filters by enrollment status.
     */
    public function testGetAccessibleChildrenFiltersByEnrollmentStatus(): void
    {
        $children = $this->sampleChildren;
        $children[0]['status'] = 'Left'; // Not enrolled

        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($children);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $result = $this->service->getAccessibleChildren(100, 'Parent', 2025);

        $this->assertCount(1, $result['childIDs'], 'Should only return fully enrolled children');
        $this->assertContains(202, $result['childIDs']);
        $this->assertNotContains(201, $result['childIDs']);
    }

    /**
     * Test getAccessibleChildren includes child info.
     */
    public function testGetAccessibleChildrenIncludesChildInfo(): void
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($this->sampleChildren);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $result = $this->service->getAccessibleChildren(100, 'Parent', 2025);

        $this->assertArrayHasKey(201, $result['childInfo']);
        $this->assertEquals('John Smith', $result['childInfo'][201]['name']);
        $this->assertEquals('John', $result['childInfo'][201]['preferredName']);
        $this->assertEquals('Smith', $result['childInfo'][201]['surname']);
    }

    // =========================================================================
    // PHOTO SHARING TESTS
    // =========================================================================

    /**
     * Test isSharedWithParents returns true for shared photo.
     */
    public function testIsSharedWithParentsReturnsTrueForSharedPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->isSharedWithParents(1);

        $this->assertTrue($result, 'Should return true for photo shared with parents');
    }

    /**
     * Test isSharedWithParents returns false for unshared photo.
     */
    public function testIsSharedWithParentsReturnsFalseForUnsharedPhoto(): void
    {
        $photo = $this->samplePhoto;
        $photo['sharedWithParent'] = 'N';

        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($photo);

        $result = $this->service->isSharedWithParents(1);

        $this->assertFalse($result, 'Should return false for photo not shared with parents');
    }

    /**
     * Test isSharedWithParents returns false for non-existent photo.
     */
    public function testIsSharedWithParentsReturnsFalseForNonExistentPhoto(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->isSharedWithParents(999);

        $this->assertFalse($result, 'Should return false for non-existent photo');
    }

    /**
     * Test isPhotoOwner returns true for owner.
     */
    public function testIsPhotoOwnerReturnsTrueForOwner(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->isPhotoOwner(1, 100);

        $this->assertTrue($result, 'Should return true for photo owner');
    }

    /**
     * Test isPhotoOwner returns false for non-owner.
     */
    public function testIsPhotoOwnerReturnsFalseForNonOwner(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->isPhotoOwner(1, 999);

        $this->assertFalse($result, 'Should return false for non-owner');
    }

    // =========================================================================
    // RETENTION PERIOD TESTS
    // =========================================================================

    /**
     * Test getRetentionExpiry calculates correct expiry date.
     */
    public function testGetRetentionExpiryCalculatesCorrectDate(): void
    {
        $deletedAt = '2020-01-15 10:00:00';
        $result = $this->service->getRetentionExpiry($deletedAt, 5);

        $expected = '2025-01-15 10:00:00';
        $this->assertEquals($expected, $result, 'Should calculate 5 years from deletion date');
    }

    /**
     * Test isRetentionExpired returns false for recent deletion.
     */
    public function testIsRetentionExpiredReturnsFalseForRecentDeletion(): void
    {
        $deletedAt = date('Y-m-d H:i:s', strtotime('-1 year'));
        $result = $this->service->isRetentionExpired($deletedAt, 5);

        $this->assertFalse($result, 'Should not be expired for deletion within retention period');
    }

    /**
     * Test isRetentionExpired returns true for old deletion.
     */
    public function testIsRetentionExpiredReturnsTrueForOldDeletion(): void
    {
        $deletedAt = date('Y-m-d H:i:s', strtotime('-6 years'));
        $result = $this->service->isRetentionExpired($deletedAt, 5);

        $this->assertTrue($result, 'Should be expired for deletion past retention period');
    }

    /**
     * Test getRetentionDaysRemaining returns positive number for active retention.
     */
    public function testGetRetentionDaysRemainingReturnsPositiveForActiveRetention(): void
    {
        $deletedAt = date('Y-m-d H:i:s', strtotime('-1 year'));
        $result = $this->service->getRetentionDaysRemaining($deletedAt, 5);

        $this->assertGreaterThan(0, $result, 'Should return positive days remaining');
        $this->assertLessThan(365 * 5, $result, 'Should be less than 5 years');
    }

    /**
     * Test getRetentionDaysRemaining returns negative number for expired retention.
     */
    public function testGetRetentionDaysRemainingReturnsNegativeForExpiredRetention(): void
    {
        $deletedAt = date('Y-m-d H:i:s', strtotime('-6 years'));
        $result = $this->service->getRetentionDaysRemaining($deletedAt, 5);

        $this->assertLessThan(0, $result, 'Should return negative days for expired retention');
    }

    // =========================================================================
    // FILE VALIDATION TESTS
    // =========================================================================

    /**
     * Test isValidPhotoType accepts JPEG images.
     */
    public function testIsValidPhotoTypeAcceptsJPEG(): void
    {
        $result = $this->service->isValidPhotoType('image/jpeg');

        $this->assertTrue($result, 'Should accept JPEG images');
    }

    /**
     * Test isValidPhotoType accepts PNG images.
     */
    public function testIsValidPhotoTypeAcceptsPNG(): void
    {
        $result = $this->service->isValidPhotoType('image/png');

        $this->assertTrue($result, 'Should accept PNG images');
    }

    /**
     * Test isValidPhotoType accepts GIF images.
     */
    public function testIsValidPhotoTypeAcceptsGIF(): void
    {
        $result = $this->service->isValidPhotoType('image/gif');

        $this->assertTrue($result, 'Should accept GIF images');
    }

    /**
     * Test isValidPhotoType accepts WebP images.
     */
    public function testIsValidPhotoTypeAcceptsWebP(): void
    {
        $result = $this->service->isValidPhotoType('image/webp');

        $this->assertTrue($result, 'Should accept WebP images');
    }

    /**
     * Test isValidPhotoType rejects non-image types.
     */
    public function testIsValidPhotoTypeRejectsNonImageTypes(): void
    {
        $result = $this->service->isValidPhotoType('application/pdf');

        $this->assertFalse($result, 'Should reject non-image types');
    }

    /**
     * Test isValidPhotoType respects custom allowed types.
     */
    public function testIsValidPhotoTypeRespectsCustomAllowedTypes(): void
    {
        $result = $this->service->isValidPhotoType('image/gif', 'jpg,png');

        $this->assertFalse($result, 'Should reject GIF when only JPG and PNG allowed');
    }

    /**
     * Test isValidFileSize accepts valid file sizes.
     */
    public function testIsValidFileSizeAcceptsValidSize(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('PhotoManagement', 'maxUploadSize')
            ->willReturn(5242880); // 5MB

        $result = $this->service->isValidFileSize(1048576); // 1MB

        $this->assertTrue($result, 'Should accept file within size limit');
    }

    /**
     * Test isValidFileSize rejects oversized files.
     */
    public function testIsValidFileSizeRejectsOversizedFile(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('PhotoManagement', 'maxUploadSize')
            ->willReturn(5242880); // 5MB

        $result = $this->service->isValidFileSize(10485760); // 10MB

        $this->assertFalse($result, 'Should reject file exceeding size limit');
    }

    /**
     * Test isValidFileSize rejects zero-byte files.
     */
    public function testIsValidFileSizeRejectsZeroByteFile(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('PhotoManagement', 'maxUploadSize')
            ->willReturn(5242880);

        $result = $this->service->isValidFileSize(0);

        $this->assertFalse($result, 'Should reject zero-byte files');
    }

    /**
     * Test getMaxUploadSize returns configured value.
     */
    public function testGetMaxUploadSizeReturnsConfiguredValue(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('PhotoManagement', 'maxUploadSize')
            ->willReturn(10485760);

        $result = $this->service->getMaxUploadSize();

        $this->assertEquals(10485760, $result, 'Should return configured max upload size');
    }

    /**
     * Test getMaxUploadSize returns default when not configured.
     */
    public function testGetMaxUploadSizeReturnsDefaultWhenNotConfigured(): void
    {
        $this->settingGateway->method('getSettingByScope')
            ->with('PhotoManagement', 'maxUploadSize')
            ->willReturn(null);

        $result = $this->service->getMaxUploadSize();

        $this->assertEquals(5242880, $result, 'Should return default 5MB when not configured');
    }

    // =========================================================================
    // PARENT PHOTO ACCESS TESTS
    // =========================================================================

    /**
     * Test getPhotosForParentByChild validates parent access to child.
     */
    public function testGetPhotosForParentByChildValidatesParentAccess(): void
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn([]);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $result = $this->service->getPhotosForParentByChild(100, 999, 2025);

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Should return empty array when parent has no access to child');
    }

    /**
     * Test getPhotosForParentByChild returns photos for accessible child.
     */
    public function testGetPhotosForParentByChildReturnsPhotosForAccessibleChild(): void
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAll')
            ->willReturn($this->sampleChildren);

        $this->familyGateway->method('selectFamilyChildrenByAdult')
            ->with(100)
            ->willReturn($resultMock);

        $expectedPhotos = [$this->samplePhoto];
        $this->photoTagGateway->method('selectPhotosForParentByChild')
            ->with(201, 2025)
            ->willReturn($expectedPhotos);

        $result = $this->service->getPhotosForParentByChild(100, 201, 2025);

        $this->assertEquals($expectedPhotos, $result, 'Should return photos for accessible child');
    }
}
