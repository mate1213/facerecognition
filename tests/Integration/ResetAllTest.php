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

use OCA\FaceRecognition\Service\FaceManagementService;
use OCA\FaceRecognition\Db\Face;
use OCA\FaceRecognition\Db\Image;
use OCA\FaceRecognition\Db\Person;
use OCA\FaceRecognition\Model\ModelManager;


use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(FaceManagementService::class)]
#[UsesClass(\OCA\FaceRecognition\BackgroundJob\FaceRecognitionContext::class)]
#[UsesClass(\OCA\FaceRecognition\BackgroundJob\FaceRecognitionLogger::class)]
#[UsesClass(\OCA\FaceRecognition\Db\Face::class)]
#[UsesClass(\OCA\FaceRecognition\Db\FaceMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Db\Image::class)]
#[UsesClass(\OCA\FaceRecognition\Db\ImageMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Db\Person::class)]
#[UsesClass(\OCA\FaceRecognition\Db\PersonMapper::class)]
#[UsesClass(\OCA\FaceRecognition\Service\SettingsService::class)]
class ResetAllTest extends IntegrationTestCase {

	/**
	 * Test that AddMissingImagesTask is updating app config that it finished full scan.
	 * Note that, in this test, we cannot check number of newly found images,
	 * as this instance might be in use and can lead to wrong results
	 */
	public function testResetAll() {
		// Add one image to DB
		$imageMapper = $this->container->get('OCA\FaceRecognition\Db\ImageMapper');
		$image = new Image();
		$image->setUser($this->user->getUid());
		$image->setFile(1);
		$image->setModel(ModelManager::DEFAULT_FACE_MODEL_ID);
		$image = $imageMapper->insert($image);
		$imageCount = $imageMapper->countUserImages($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $imageCount);

		// Add one person to DB
		$personMapper = $this->container->get('OCA\FaceRecognition\Db\PersonMapper');
		$person = new Person();
		$person->setUser($this->user->getUID());
		$person->setIsValid(true);
		$person->setName('foo');
		$person = $personMapper->insert($person);
		$personCount = $personMapper->countPersons($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $personCount); // Still 0 due it has no associated faces

		// Add one face to DB
		$faceMapper = $this->container->get('OCA\FaceRecognition\Db\FaceMapper');
		$face = Face::fromModel($image->getId(), array("left"=>0, "right"=>100, "top"=>0, "bottom"=>100, "detection_confidence"=>1.0));
		$face->setPerson($person->getId());
		$face = $faceMapper->insertFace($face);
		$faceCount = $faceMapper->countFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $faceCount);

		// Check faces with all correct relationships
		$personCount = $personMapper->countPersons($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $personCount);

		// Execute reset all
		$userManager = $this->container->get('OCP\IUserManager');
		$settingsService = $this->container->get('OCA\FaceRecognition\Service\SettingsService');
		$faceMgmtService = new FaceManagementService($userManager, $faceMapper, $imageMapper, $personMapper, $settingsService);
		$faceMgmtService->resetAllForUser($this->user->getUID());

		// Check that everything is gone
		$imageCount = $imageMapper->countUserImages($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $imageCount);
		$faceCount = $faceMapper->countFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $faceCount);
		$personCount = $personMapper->countPersons($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $personCount);
	}
}