<?php
/**
 * @copyright Copyright (c) 2017, Matias De lellis <mati86dl@gmail.com>
 * @copyright Copyright (c) 2018, Branko Kokanovic <branko@kokanovic.org>
 *
 * @author Branko Kokanovic <branko@kokanovic.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\FaceRecognition\Tests\Integration;

use OC\Files\View;

use OCP\IUser;

use OCA\FaceRecognition\Db\Image;
use OCA\FaceRecognition\BackgroundJob\Tasks\AddMissingImagesTask;
use OCA\FaceRecognition\BackgroundJob\Tasks\StaleImagesRemovalTask;
use OCA\FaceRecognition\Model\ModelManager;
use OCA\FaceRecognition\Service\SettingsService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(StaleImagesRemovalTask::class)]
#[UsesClass(\OCA\FaceRecognition\Db\FaceMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Db\Image::class)]
#[UsesClass(\OCA\FaceRecognition\Db\ImageMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Db\PersonMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Service\FileService::class)]
#[UsesClass(\OCA\FaceRecognition\BackgroundJob\FaceRecognitionContext::class)]
#[UsesClass(\OCA\FaceRecognition\BackgroundJob\FaceRecognitionLogger::class)]
#[UsesClass(\OCA\FaceRecognition\BackgroundJob\Tasks\AddMissingImagesTask::class)]
#[UsesClass(\OCA\FaceRecognition\Listener\UserDeletedListener::class)]
#[UsesClass(\OCA\FaceRecognition\Service\FaceManagementService::class)]
#[UsesClass(\OCA\FaceRecognition\Service\SettingsService::class)]
class StaleImagesRemovalTaskTest extends IntegrationTestCase {

	/**
	 * Test that StaleImagesRemovalTask is not active, even though there should be some removals.
	 */
	public function testNotNeededScan() {
		$imageMapper = $this->container->get('OCA\FaceRecognition\Db\ImageMapper');
		$image = new Image();
		$image->setUser($this->user->getUid());
		$image->setFile(1);
		$image->setModel(ModelManager::DEFAULT_FACE_MODEL_ID);
		$imageMapper->insert($image);

		$staleImagesRemovalTask = $this->createStaleImagesRemovalTask();
		$generator = $staleImagesRemovalTask->execute($this->context);
		foreach ($generator as $_) {
		}
		$this->assertEquals(true, $generator->getReturn());

		$this->assertEquals(0, $this->context->propertyBag['StaleImagesRemovalTask_staleRemovedImages']);
		$imageMapper->delete($image);
	}

	/**
	 * Test that image which exists only in database is removed when StaleImagesRemovalTask is run.
	 */
	public function testMissingImageRemoval() {
		$imageMapper = $this->container->get('OCA\FaceRecognition\Db\ImageMapper');
		$image = new Image();
		$image->setUser($this->user->getUid());
		$image->setFile(2);
		$image->setModel(ModelManager::DEFAULT_FACE_MODEL_ID);
		$imageMapper->insert($image);

		$this->doStaleImagesRemoval();
		$this->assertEquals(1, $this->context->propertyBag['StaleImagesRemovalTask_staleRemovedImages']);
	}

	/**
	 * Test that image under .nomedia directory is removed
	 */
	public function testNoMediaImageRemoval() {
		// Create foo1.jpg in root and foo2.jpg in child directory
		$view = new View('/' . $this->user->getUID() . '/files');
		$view->file_put_contents("foo1.jpg", "content");
		$view->mkdir('dir_nomedia');
		$view->file_put_contents("dir_nomedia/foo2.jpg", "content");
		// Create these two images in database by calling add missing images task
		$this->config->setUserValue($this->user->getUID(), 'facerecognition', AddMissingImagesTask::FULL_IMAGE_SCAN_DONE_KEY, 'false');
		$imageMapper = $this->container->get('OCA\FaceRecognition\Db\ImageMapper');
		$fileService = $this->container->get('OCA\FaceRecognition\Service\FileService');
		$settingsService = $this->container->get('OCA\FaceRecognition\Service\SettingsService');
		$addMissingImagesTask = new AddMissingImagesTask($imageMapper, $fileService, $settingsService);
		$this->context->user = $this->user;
		$generator = $addMissingImagesTask->execute($this->context);
		foreach ($generator as $_) {
		}
		// TODO: add faces and person for those images, so we can exercise person
		// invalidation and face removal when image is removed.

		// We should find 2 images now - foo1.jpg, foo2.png
		$this->assertEquals(2, count($imageMapper->findImagesWithoutFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID)));

		// We should not delete anything this time
		$this->doStaleImagesRemoval();
		$this->assertEquals(0, $this->context->propertyBag['StaleImagesRemovalTask_staleRemovedImages']);

		// Now add .nomedia file in subdirectory and one image (foo2.jpg) should be gone now
		$view->file_put_contents("dir_nomedia/.nomedia", "content");
		$this->doStaleImagesRemoval();
		$this->assertEquals(1, $this->context->propertyBag['StaleImagesRemovalTask_staleRemovedImages']);
		$this->assertEquals(1, count($imageMapper->findImagesWithoutFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID)));
	}

	/**
	 * Helper method to set up and do scanning
	 *
	 * @param IUser|null $contextUser Optional user to scan for.
	 * If not given, stale images for all users will be renived.
	 */
	private function doStaleImagesRemoval($contextUser = null) {
		// Set config that stale image removal is needed
		$this->config->setUserValue($this->user->getUID(), 'facerecognition', SettingsService::STALE_IMAGES_REMOVAL_NEEDED_KEY, 'true');

		$staleImagesRemovalTask = $this->createStaleImagesRemovalTask();
		$this->assertNotEquals("", $staleImagesRemovalTask->description());

		// Set user for which to do scanning, if any
		$this->context->user = $contextUser;

		// Since this task returns generator, iterate until it is done
		$generator = $staleImagesRemovalTask->execute($this->context);
		foreach ($generator as $_) {
		}

		$this->assertEquals(true, $generator->getReturn());
	}

	private function createStaleImagesRemovalTask() {
		$imageMapper = $this->container->get('OCA\FaceRecognition\Db\ImageMapper');
		$faceMapper = $this->container->get('OCA\FaceRecognition\Db\FaceMapper');
		$personMapper = $this->container->get('OCA\FaceRecognition\Db\PersonMapper');
		$fileService = $this->container->get('OCA\FaceRecognition\Service\FileService');
		$settingsService = $this->container->get('OCA\FaceRecognition\Service\SettingsService');
		return new StaleImagesRemovalTask($imageMapper, $faceMapper, $personMapper, $fileService, $settingsService);
	}
}