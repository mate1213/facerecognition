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
	private $originalModel = 0;
	private $settingsService;
	private $personMapper;
	private $faceMapper;
	private $imageMapper;
	private $image;
	private $face;

	
	public function setUp(): void {
		parent::setUp();
		$this->personMapper = new PersonMapper($this->dbConnection);
		$this->faceMapper = new FaceMapper($this->dbConnection);
		$this->imageMapper = new ImageMapper($this->dbConnection, $this->faceMapper);
		$this->settingsService = new SettingsService($this->config, $this->user->getUID());
		$this->originalModel = $this->settingsService->getCurrentFaceModel();
		$this->settingsService->setCurrentFaceModel(ModelManager::DEFAULT_FACE_MODEL_ID);

		$this->image = new Image();
		$this->image->setUser($this->user->getUid());
		$this->image->setFile(1);
		$this->image->setModel(ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->imageMapper->insert($this->image);

		$this->face = Face::fromModel($this->image->getId(), array("left"=>0, "right"=>100, "top"=>0, "bottom"=>100, "detection_confidence"=>1.0));
		$this->faceMapper->insertFace($this->face);

		/* Check inserted face */
		$min_face_size = $this->settingsService->getMinimumFaceSize();
		$min_confidence = $this->settingsService->getMinimumConfidence();

		$groupablefaces = count($this->faceMapper->getGroupableFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID, $min_face_size, $min_confidence));
		$this->assertEquals(1, $groupablefaces);
		$nonGroupablefaces = count($this->faceMapper->getNonGroupableFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID, $min_face_size, $min_confidence));
		$this->assertEquals(0, $nonGroupablefaces);
	}
	
	/**
	 * Test that one face by defauld should be not clustered
	 */
	public function test_singleFaceShouldNeverCreateClusters() {
		// With a single face should never create clusters.
		$this->doCreateClustersTask($this->personMapper, $this->imageMapper, $this->faceMapper, $this->settingsService, $this->user);

		$personCount = $this->personMapper->countPersons($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, $personCount);
		$persons = $this->personMapper->findAll($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(0, count($persons));

		$faceCount = $this->faceMapper->countFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $faceCount);
		$faces = $this->faceMapper->getFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($faces));
		$this->assertNull($faces[0]->getPerson());
	}

	/**
	 * Test that one face that was not in any cluster will be assigned new person
	 */
	public function testCreateSingleFaceCluster() {
		// Force clustering the sigle face.
		$this->settingsService->_setForceCreateClusters(true, $this->user->getUID());

		$this->doCreateClustersTask($this->personMapper, $this->imageMapper, $this->faceMapper, $this->settingsService, $this->user);

		$clusterCount = $this->personMapper->countClusters($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $clusterCount);

		$persons = $this->personMapper->findAll($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($persons));
		$personId = $persons[0]->getId();

		$faceCount = $this->faceMapper->countFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, $faceCount);

		$faces = $this->faceMapper->getFaces($this->user->getUID(), ModelManager::DEFAULT_FACE_MODEL_ID);
		$this->assertEquals(1, count($faces));
		$this->assertNotNull($faces[0]->getPerson());

		$faces = $this->faceMapper->findFromCluster($this->user->getUID(), $personId, ModelManager::DEFAULT_FACE_MODEL_ID);
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
		$this->context->user = $contextUser;

		// Since this task returns generator, iterate until it is done
		$generator = $createClustersTask->execute($this->context);
		foreach ($generator as $_) {
		}

		$this->assertEquals(true, $generator->getReturn());
	}

	public function tearDown(): void {
		$this->settingsService->setCurrentFaceModel($this->originalModel);

		parent::tearDown();
	}
}