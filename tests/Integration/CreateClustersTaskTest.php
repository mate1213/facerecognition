<?php
/**
 * @copyright Copyright (c) 2017, Matias De lellis <mati86dl@gmail.com>
 * @copyright Copyright (c) 2019, Branko Kokanovic <branko@kokanovic.org>
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

use Exception;
use OC;

use OCP\IConfig;
use OCP\IUser;
use OCP\AppFramework\App;

use OCA\FaceRecognition\BackgroundJob\FaceRecognitionContext;
use OCA\FaceRecognition\BackgroundJob\FaceRecognitionLogger;
use OCA\FaceRecognition\BackgroundJob\Tasks\CreateClustersTask;
use OCA\FaceRecognition\Db\Face;
use OCA\FaceRecognition\Db\Person;
use OCA\FaceRecognition\Db\Image;
use OCA\FaceRecognition\Db\ImageMapper;
use OCA\FaceRecognition\Db\FaceMapper;
use OCA\FaceRecognition\Db\PersonMapper;
use OCA\FaceRecognition\Model\ModelManager;
use OCA\FaceRecognition\Service\SettingsService;
use OCA\FaceRecognition\Service\FaceManagementService;

use Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CreateClustersTask::class)]
#[CoversClass(SettingsService::class)]
#[UsesClass(\OCA\FaceRecognition\Helper\Requirements::class)]
#[UsesClass(\OCA\FaceRecognition\Listener\UserDeletedListener::class)]
#[UsesClass(FaceRecognitionContext::class)]
#[UsesClass(FaceRecognitionLogger::class)]
#[UsesClass(FaceManagementService::class)]
#[UsesClass(FaceMapper::class)]
#[UsesClass(PersonMapper::class)]
#[UsesClass(ImageMapper::class)]
#[UsesClass(ModelManager::class)]
#[UsesClass(Face::class)]
#[UsesClass(Image::class)]
#[UsesClass(Person::class)]
class CreateClustersTaskTest extends IntegrationTestCase {


	/** @var Image*/
	private $image;

	/** @var Face*/
	private $face;

	public function setUp(): void {
		parent::setUp();

		$this->image = new Image();
		$this->image->setUser(self::$user->getUid());
		$this->image->setFile(1);
		$this->image->setModel(ModelManager::DEFAULT_FACE_MODEL_ID);
		self::$imageMapper->insert($this->image);

		$this->face = Face::fromModel($this->image->getId(), array("left"=>0, "right"=>100, "top"=>0, "bottom"=>100, "detection_confidence"=>1.0));
		self::$faceMapper->insertFace($this->face);

		/* Check inserted face */
		$min_face_size = self::$settingsService->getMinimumFaceSize();
		$min_confidence = self::$settingsService->getMinimumConfidence();

		$groupablefaces = count(self::$faceMapper->getGroupableFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID, $min_face_size, $min_confidence));
		$this->assertEquals(1, $groupablefaces);
		$nonGroupablefaces = count(self::$faceMapper->getNonGroupableFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID, $min_face_size, $min_confidence));
		$this->assertEquals(0, $nonGroupablefaces);
	}
	
	/**
	 * Test that one face by defauld should be not clustered
	 */
	public function test_singleFaceShouldNeverCreateClusters() {
		// With a single face should never create clusters.
		$this->doCreateClustersTask(self::$personMapper, self::$imageMapper, self::$faceMapper, self::$settingsService, self::$user);

		$personCount = self::$personMapper->countPersons(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $personCount);
		$persons = self::$personMapper->findAll(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, count($persons));

		$faceCount = self::$faceMapper->countFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $faceCount);
		$faces = self::$faceMapper->getFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($faces));
		$this->assertNull($faces[0]->getPerson());
	}

	/**
	 * Test that one face that was not in any cluster will be assigned new person
	 */
	public function testCreateSingleFaceCluster() {
		// Force clustering the sigle face.
		self::$settingsService->_setForceCreateClusters(true, self::$user->getUID());

		$this->doCreateClustersTask(self::$personMapper, self::$imageMapper, self::$faceMapper, self::$settingsService, self::$user);

		$clusterCount = self::$personMapper->countClusters(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $clusterCount);

		$persons = self::$personMapper->findAll(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($persons));
		$personId = $persons[0]->getId();

		$faceCount = self::$faceMapper->countFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $faceCount);

		$faces = self::$faceMapper->getFaces(self::$user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($faces));
		$this->assertNotNull($faces[0]->getPerson());

		$faces = self::$faceMapper->findFromCluster(self::$user->getUID(), $personId, ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($faces));
	}

	/**
	 * Helper method to set up and do create clusters task
	 *
	 * @param IUser|null $contextUser Optional user to create clusters for.
	 * If not given, clusters for all users will be processed.
	 */
	private function doCreateClustersTask($personMapper, $imageMapper, $faceMapper, $settingsService, $contextUser = null) {
		$createClustersTask = new CreateClustersTask($personMapper, $imageMapper, $faceMapper, $settingsService);
		$this->assertNotEquals("", $createClustersTask->description());

		// Set user for which to do processing, if any
		self::$context->user = $contextUser;

		// Since this task returns generator, iterate until it is done
		$generator = $createClustersTask->execute(self::$context);
		foreach ($generator as $_) {
		}

		$this->assertEquals(true, $generator->getReturn());
	}
}