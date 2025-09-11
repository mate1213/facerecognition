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

use OC;
use OC\Files\View;

use OCP\IConfig;
use OCP\IUser;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

use OCA\FaceRecognition\BackgroundJob\FaceRecognitionContext;
use OCA\FaceRecognition\BackgroundJob\FaceRecognitionLogger;
use OCA\FaceRecognition\BackgroundJob\Tasks\AddMissingImagesTask;
use OCA\FaceRecognition\Model\ModelManager;
use OCP\IDBConnection;

use \phpunit\Framework\TestCase;

/**
 * Main class that all integration tests should inherit from.
 */
abstract class IntegrationTestCase extends TestCase {

	/** @var int*/
	protected $originalModel = 0;

	/** @var SettingsService*/
	protected $settingsService;

	/** @var IAppContainer */
	protected $container;

	/** @var FaceRecognitionContext Context */
	protected $context;

	/** @var IUser User */
	protected $user;

	/** @var IConfig Config */
	protected $config;

	/** @var IDBConnection test instance*/
	protected $dbConnection;
	
	public function setUp(): void {
		parent::setUp();
		// Better safe than sorry. Warn user that database will be changed in chaotic manner:)
		if (false === getenv('TRAVIS')) {
			$this->fail("This test touches database. Add \"TRAVIS\" env variable if you want to run these test on your local instance.");
		}

		$this->dbConnection = OC::$server->getDatabaseConnection();
		
		$sql = file_get_contents("tests/DatabaseInserts/00_emptyDatabase.sql");
		$this->dbConnection->executeStatement($sql);

		// Create user on which we will upload images and do testing
		$userManager = OC::$server->getUserManager();
		$username = 'testuser' . rand(0, PHP_INT_MAX);
		$this->user = $userManager->createUser($username, 'YVvV4huLVUNR#UgJC*bBGXzHR4uW24$kB#dRTX*9');
		$this->user->updateLastLoginTimestamp();
		// Get container to get classes using DI
		$app = new App('facerecognition');
		$this->container = $app->getContainer();

		// Insantiate our context, that all tasks need
		$userManager = $this->container->get('OCP\IUserManager');
		$this->config = $this->container->get('OCP\IConfig');
		$this->context = new FaceRecognitionContext($userManager, $this->config);
		$logger = $this->container->get('Psr\Log\LoggerInterface');
		$this->context->logger = new FaceRecognitionLogger($logger);

		// The tests, by default, are with the analysis activated.
		$this->config->setUserValue($this->user->getUID(), 'facerecognition', 'enabled', 'true');

		
		$this->settingsService = $this->container->get('OCA\FaceRecognition\Service\SettingsService');
		$this->originalModel = $this->settingsService->getCurrentFaceModel();
		$this->settingsService->setCurrentFaceModel(ModelManager::DEFAULT_FACE_MODEL_ID);
	}

	public function tearDown(): void {
		$this->settingsService->setCurrentFaceModel($this->originalModel);

		// $faceMgmtService = $this->container->get('OCA\FaceRecognition\Service\FaceManagementService');
		// $faceMgmtService->resetAllForUser($this->user->getUID());

		// $this->user->delete();

		parent::tearDown();
	}
}