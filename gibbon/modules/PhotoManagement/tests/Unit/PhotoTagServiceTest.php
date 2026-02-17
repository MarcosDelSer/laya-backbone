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
use Gibbon\Module\PhotoManagement\Service\PhotoTagService;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;

/**
 * Unit tests for PhotoTagService.
 *
 * Tests photo tagging business logic including single/bulk tagging operations,
 * tag validation, tag removal, and tag statistics.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PhotoTagServiceTest extends TestCase
{
    /**
     * @var PhotoTagService
     */
    protected $service;

    /**
     * @var PhotoTagGateway|MockObject
     */
    protected $photoTagGateway;

    /**
     * @var PhotoGateway|MockObject
     */
    protected $photoGateway;

    /**
     * Sample photo data for testing.
     *
     * @var array
     */
    protected $samplePhoto;

    /**
     * Sample tag data for testing.
     *
     * @var array
     */
    protected $sampleTag;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock gateways
        $this->photoTagGateway = $this->createMock(PhotoTagGateway::class);
        $this->photoGateway = $this->createMock(PhotoGateway::class);

        // Create service instance with mocked dependencies
        $this->service = new PhotoTagService(
            $this->photoTagGateway,
            $this->photoGateway
        );

        // Sample photo data
        $this->samplePhoto = [
            'gibbonPhotoUploadID' => 1,
            'uploadedByID' => 100,
            'sharedWithParent' => 'Y',
            'gibbonSchoolYearID' => 2025,
            'fileName' => 'photo.jpg',
        ];

        // Sample tag data
        $this->sampleTag = [
            'gibbonPhotoTagID' => 1,
            'gibbonPhotoUploadID' => 1,
            'gibbonPersonID' => 201,
            'taggedByID' => 100,
            'timestampCreated' => '2025-01-15 10:00:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
        $this->photoTagGateway = null;
        $this->photoGateway = null;
    }

    // =========================================================================
    // SINGLE CHILD TAGGING TESTS
    // =========================================================================

    /**
     * Test tagChild successfully tags a child in a photo.
     */
    public function testTagChildSuccessfullyTagsChild(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->tagChild(1, 201, 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['gibbonPhotoTagID']);
    }

    /**
     * Test tagChild fails when photo does not exist.
     */
    public function testTagChildFailsWhenPhotoNotFound(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->tagChild(999, 201, 100);

        $this->assertFalse($result['success']);
        $this->assertEquals('Photo not found', $result['error']);
    }

    /**
     * Test tagChild fails when child already tagged.
     */
    public function testTagChildFailsWhenChildAlreadyTagged(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $result = $this->service->tagChild(1, 201, 100);

        $this->assertFalse($result['success']);
        $this->assertEquals('Child already tagged in this photo', $result['error']);
    }

    /**
     * Test tagChild fails when insert operation fails.
     */
    public function testTagChildFailsWhenInsertFails(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(false);

        $result = $this->service->tagChild(1, 201, 100);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to tag child', $result['error']);
    }

    // =========================================================================
    // MULTIPLE CHILDREN TAGGING TESTS
    // =========================================================================

    /**
     * Test tagChildren successfully tags multiple children.
     */
    public function testTagChildrenSuccessfullyTagsMultipleChildren(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->tagChildren(1, [201, 202, 203], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['tagged']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(3, $result['total']);
    }

    /**
     * Test tagChildren skips already tagged children.
     */
    public function testTagChildrenSkipsAlreadyTaggedChildren(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->willReturnCallback(function($photoID, $childID) {
                return $childID === 201; // First child already tagged
            });

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->tagChildren(1, [201, 202, 203], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['tagged']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(3, $result['total']);
    }

    /**
     * Test tagChildren fails when photo does not exist.
     */
    public function testTagChildrenFailsWhenPhotoNotFound(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->tagChildren(999, [201, 202], 100);

        $this->assertFalse($result['success']);
        $this->assertEquals('Photo not found', $result['error']);
        $this->assertEquals(0, $result['tagged']);
    }

    /**
     * Test tagChildren handles empty child array.
     */
    public function testTagChildrenHandlesEmptyChildArray(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->tagChildren(1, [], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['tagged']);
        $this->assertEquals(0, $result['total']);
    }

    // =========================================================================
    // UNTAG CHILD TESTS
    // =========================================================================

    /**
     * Test untagChild successfully removes tag.
     */
    public function testUntagChildSuccessfullyRemovesTag(): void
    {
        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $this->photoTagGateway->method('untagChild')
            ->with(1, 201)
            ->willReturn(true);

        $result = $this->service->untagChild(1, 201);

        $this->assertTrue($result['success']);
    }

    /**
     * Test untagChild fails when child not tagged.
     */
    public function testUntagChildFailsWhenChildNotTagged(): void
    {
        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(false);

        $result = $this->service->untagChild(1, 201);

        $this->assertFalse($result['success']);
        $this->assertEquals('Child not tagged in this photo', $result['error']);
    }

    /**
     * Test untagChild fails when removal operation fails.
     */
    public function testUntagChildFailsWhenRemovalFails(): void
    {
        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $this->photoTagGateway->method('untagChild')
            ->with(1, 201)
            ->willReturn(false);

        $result = $this->service->untagChild(1, 201);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to untag child', $result['error']);
    }

    // =========================================================================
    // REMOVE ALL TAGS TESTS
    // =========================================================================

    /**
     * Test removeAllTags successfully removes all tags.
     */
    public function testRemoveAllTagsSuccessfullyRemovesAllTags(): void
    {
        $this->photoTagGateway->method('deleteAllTagsForPhoto')
            ->with(1)
            ->willReturn(5);

        $result = $this->service->removeAllTags(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['removed']);
    }

    /**
     * Test removeAllTags returns zero when no tags exist.
     */
    public function testRemoveAllTagsReturnsZeroWhenNoTags(): void
    {
        $this->photoTagGateway->method('deleteAllTagsForPhoto')
            ->with(1)
            ->willReturn(0);

        $result = $this->service->removeAllTags(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['removed']);
    }

    // =========================================================================
    // BULK TAGGING TESTS
    // =========================================================================

    /**
     * Test bulkTagPhotos successfully tags multiple photos.
     */
    public function testBulkTagPhotosSuccessfullyTagsMultiplePhotos(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->bulkTagPhotos([1, 2, 3], [201, 202], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['photosProcessed']);
        $this->assertEquals(6, $result['totalTagged']); // 3 photos × 2 children
        $this->assertEquals(0, $result['totalSkipped']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test bulkTagPhotos handles photo not found errors.
     */
    public function testBulkTagPhotosHandlesPhotoNotFoundErrors(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->willReturnCallback(function($id) {
                return $id === 999 ? null : $this->samplePhoto;
            });

        $this->photoTagGateway->method('isChildTagged')
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->bulkTagPhotos([1, 999, 2], [201], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['photosProcessed']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(999, $result['errors'][0]['photoID']);
    }

    /**
     * Test bulkTagPhotos with empty photo array.
     */
    public function testBulkTagPhotosWithEmptyPhotoArray(): void
    {
        $result = $this->service->bulkTagPhotos([], [201, 202], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['photosProcessed']);
        $this->assertEquals(0, $result['totalTagged']);
    }

    // =========================================================================
    // REPLACE TAGS TESTS
    // =========================================================================

    /**
     * Test replaceTags successfully replaces all tags.
     */
    public function testReplaceTagsSuccessfullyReplacesAllTags(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('deleteAllTagsForPhoto')
            ->with(1)
            ->willReturn(3);

        $this->photoTagGateway->method('isChildTagged')
            ->willReturn(false);

        $this->photoTagGateway->method('insertTag')
            ->willReturn(1);

        $result = $this->service->replaceTags(1, [201, 202], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals('all', $result['removed']);
        $this->assertEquals(2, $result['tagged']);
    }

    /**
     * Test replaceTags fails when photo does not exist.
     */
    public function testReplaceTagsFailsWhenPhotoNotFound(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->replaceTags(999, [201, 202], 100);

        $this->assertFalse($result['success']);
        $this->assertEquals('Photo not found', $result['error']);
    }

    /**
     * Test replaceTags with empty child array removes all tags.
     */
    public function testReplaceTagsWithEmptyChildArrayRemovesAllTags(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('deleteAllTagsForPhoto')
            ->with(1)
            ->willReturn(5);

        $result = $this->service->replaceTags(1, [], 100);

        $this->assertTrue($result['success']);
        $this->assertEquals('all', $result['removed']);
        $this->assertEquals(0, $result['tagged']);
    }

    // =========================================================================
    // QUERY METHODS TESTS
    // =========================================================================

    /**
     * Test getTagsForPhoto returns tags.
     */
    public function testGetTagsForPhotoReturnsTags(): void
    {
        $expectedTags = [$this->sampleTag];

        $this->photoTagGateway->method('selectTagsByPhoto')
            ->with(1)
            ->willReturn($expectedTags);

        $result = $this->service->getTagsForPhoto(1);

        $this->assertEquals($expectedTags, $result);
    }

    /**
     * Test getPhotosByChild returns photos.
     */
    public function testGetPhotosByChildReturnsPhotos(): void
    {
        $expectedPhotos = [$this->samplePhoto];

        $this->photoTagGateway->method('selectPhotosByChild')
            ->with(201)
            ->willReturn($expectedPhotos);

        $result = $this->service->getPhotosByChild(201);

        $this->assertEquals($expectedPhotos, $result);
    }

    /**
     * Test isChildTagged returns true when child is tagged.
     */
    public function testIsChildTaggedReturnsTrueWhenTagged(): void
    {
        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $result = $this->service->isChildTagged(1, 201);

        $this->assertTrue($result);
    }

    /**
     * Test isChildTagged returns false when child is not tagged.
     */
    public function testIsChildTaggedReturnsFalseWhenNotTagged(): void
    {
        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(false);

        $result = $this->service->isChildTagged(1, 201);

        $this->assertFalse($result);
    }

    /**
     * Test countTags returns tag count.
     */
    public function testCountTagsReturnsTagCount(): void
    {
        $this->photoTagGateway->method('countTagsByPhoto')
            ->with(1)
            ->willReturn(5);

        $result = $this->service->countTags(1);

        $this->assertEquals(5, $result);
    }

    /**
     * Test getTagsByTagger returns tags created by tagger.
     */
    public function testGetTagsByTaggerReturnsTagsByTagger(): void
    {
        $expectedTags = [$this->sampleTag];

        $this->photoTagGateway->method('selectTagsByTagger')
            ->with(100)
            ->willReturn($expectedTags);

        $result = $this->service->getTagsByTagger(100);

        $this->assertEquals($expectedTags, $result);
    }

    /**
     * Test getTagStatistics returns statistics.
     */
    public function testGetTagStatisticsReturnsStatistics(): void
    {
        $expectedStats = [
            ['gibbonPersonID' => 201, 'photoCount' => 10],
            ['gibbonPersonID' => 202, 'photoCount' => 5],
        ];

        $this->photoTagGateway->method('getTagStatistics')
            ->with(2025)
            ->willReturn($expectedStats);

        $result = $this->service->getTagStatistics(2025);

        $this->assertEquals($expectedStats, $result);
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    /**
     * Test validateTag returns valid for valid tag data.
     */
    public function testValidateTagReturnsValidForValidData(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(false);

        $result = $this->service->validateTag(1, 201, 100);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test validateTag detects photo not found.
     */
    public function testValidateTagDetectsPhotoNotFound(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->validateTag(999, 201, 100);

        $this->assertFalse($result['valid']);
        $this->assertContains('Photo not found', $result['errors']);
    }

    /**
     * Test validateTag detects child already tagged.
     */
    public function testValidateTagDetectsChildAlreadyTagged(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $this->photoTagGateway->method('isChildTagged')
            ->with(1, 201)
            ->willReturn(true);

        $result = $this->service->validateTag(1, 201, 100);

        $this->assertFalse($result['valid']);
        $this->assertContains('Child already tagged in this photo', $result['errors']);
    }

    /**
     * Test validateTag detects invalid photo ID.
     */
    public function testValidateTagDetectsInvalidPhotoID(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->willReturn(null);

        $result = $this->service->validateTag(0, 201, 100);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid photo ID', $result['errors']);
    }

    /**
     * Test validateTag detects invalid child ID.
     */
    public function testValidateTagDetectsInvalidChildID(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->validateTag(1, 0, 100);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid child ID', $result['errors']);
    }

    /**
     * Test validateTag detects invalid tagger ID.
     */
    public function testValidateTagDetectsInvalidTaggerID(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->with(1)
            ->willReturn($this->samplePhoto);

        $result = $this->service->validateTag(1, 201, 0);

        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid tagger ID', $result['errors']);
    }

    /**
     * Test validateTag detects multiple errors.
     */
    public function testValidateTagDetectsMultipleErrors(): void
    {
        $this->photoGateway->method('getPhotoByID')
            ->willReturn(null);

        $result = $this->service->validateTag(0, 0, 0);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(3, count($result['errors']), 'Should detect multiple validation errors');
    }
}
