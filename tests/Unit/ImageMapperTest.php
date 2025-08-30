<?php
/**
 * @copyright Copyright (c) 2024, Mate Zsolya <zsolyamate@gmail.com>
 *
 * @author Mate Zsolya <zsolyamate@gmail.com>
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
namespace OCA\FaceRecognition\Tests\Unit;

use DateTime;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use OCA\FaceRecognition\Db\FaceMapper;
use OCA\FaceRecognition\Db\ImageMapper;
use OCA\FaceRecognition\Db\Image;
use OC;
use OCP\IDBConnection;

#[CoversClass(ImageMapper::class)]
#[UsesClass(FaceMapper::class)]
#[UsesClass(Image::class)]
class ImageMapperTest extends TestCase {
    /** @var ImageMapper test instance*/
	private $imageMapper;
    /** @var IDBConnection test instance*/
    private $dbConnection;
	private $isSetupComplete = true;
    /**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
        parent::setUp();
        $this->dbConnection = OC::$server->getDatabaseConnection();
		$this->dbConnection->beginTransaction();

		$this->imageMapper = new ImageMapper($this->dbConnection, new FaceMapper($this->dbConnection));
		if ($this->isSetupComplete === false) {
			$this->isSetupComplete = true;
			$sql = file_get_contents("tests/DatabaseInserts/10_imageInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/20_userImagesInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/30_facesInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/40_clustersInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/50_clusterFacesInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/60_personInsert.sql");
			$this->dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/70_personClustersInsert.sql");
			$this->dbConnection->executeStatement($sql);
		}
	}

    public function test_Find() : void {
		//Act
        $image = $this->imageMapper->find('user1', 1);

		//Assert
        $this->assertNotNull($image);
		$this->assertInstanceOf(Image::class, $image);
        $this->assertEquals(1, $image->getId());
        $this->assertEquals(1, $image->getModel());
        $this->assertEquals(120, $image->getProcessingDuration());
		$this->assertEquals('user1', $image->getUser());
		$this->assertEquals(101, $image->getFile());
		$this->assertEquals(true, $image->getIsProcessed());
		$this->assertEquals(null, $image->getError());
        $this->assertEquals(DateTime::createFromFormat('Y-m-d H:i:s', '2025-08-26 10:05:00'), $image->getLastProcessedTime());
	}

    #[DataProviderExternal(ImageDataProvider::class, 'findAll_Provider')]
    public function test_FindAll(string $user, int $model, int $expectedCount) : void {
		//Act
        $images = $this->imageMapper->findAll($user, $model);

		//Assert
        $this->assertNotNull($images);
		$this->assertIsArray($images);
		$this->assertContainsOnlyInstancesOf(Image::class, $images);
		$this->assertCount($expectedCount, $images);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'findFromFile_Provider')]
    public function test_findFromFile(string $user, int $model, int $nc_file_id, ?int $expectedId) : void {
		//Act
        $image = $this->imageMapper->findFromFile($user, $model, $nc_file_id);

		//Assert
		if ($expectedId === null)
		{
			$this->assertNull($image);
		}
		else
		{
			$this->assertNotNull($image);
			$this->assertInstanceOf(Image::class, $image);
			$this->assertEquals($expectedId, $image->getId());
			$this->assertEquals($user, $image->getUser());
			$this->assertEquals($model, $image->getModel());
		}
	}

    #[DataProviderExternal(ImageDataProvider::class, 'otherUserStilHasConnection_Provider')]
    public function test_otherUserStilHasConnection(int $imageId, bool $expectedResult) : void {
		//Act
        $hasConnection = $this->imageMapper->otherUserStilHasConnection($imageId);

		//Assert
		$this->assertEquals($expectedResult, $hasConnection);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'insert_Provider')]
    public function test_removeUserImageConnection_singleImage() : void {
		//Assert initial state
        $imageCountQuery = $this->dbConnection->getQueryBuilder();
		$imageCountQuery->select($imageCountQuery->createFunction('COUNT(id) as count'))->from('facerecog_images');
        $userImageCountQuery = $this->dbConnection->getQueryBuilder();
		$userImageCountQuery->select($userImageCountQuery->createFunction('COUNT(*) as count'))->from('facerecog_user_images');

		$row  = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);

		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);

		$image = new Image();
		$image->id = 1;
		$image->user = "user1";

		//Act
        $this->imageMapper->removeUserImageConnection($image);

		//Assert
		$row = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
	}

    public function test_removeUserImageConnection_sharedImage() : void {
		//Assert initial state
        $imageCountQuery = $this->dbConnection->getQueryBuilder();
		$imageCountQuery->select($imageCountQuery->createFunction('COUNT(id) as count'))->from('facerecog_images');
        $userImageCountQuery = $this->dbConnection->getQueryBuilder();
		$userImageCountQuery->select($userImageCountQuery->createFunction('COUNT(*) as count'))->from('facerecog_user_images');

		$row  = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);

		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);

		$image = new Image();
		$image->id = 10;
		$image->user = "user1";

		//Act
        $this->imageMapper->removeUserImageConnection($image);

		//Assert
		$row = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
	}

	public function test_removeUserImageConnection_NonExistingImage() : void {
		//Assert initial state
        $imageCountQuery = $this->dbConnection->getQueryBuilder();
		$imageCountQuery->select($imageCountQuery->createFunction('COUNT(id) as count'))->from('facerecog_images');
        $userImageCountQuery = $this->dbConnection->getQueryBuilder();
		$userImageCountQuery->select($userImageCountQuery->createFunction('COUNT(*) as count'))->from('facerecog_user_images');

		$row  = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);

		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);

		$image = new Image();
		$image->id = 100;
		$image->user = "user1";

		//Act
        $this->imageMapper->removeUserImageConnection($image);

		//Assert
		$row = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);
	}
	
	public function test_removeUserImageConnection_nonExistingUser() : void {
		//Assert initial state
        $imageCountQuery = $this->dbConnection->getQueryBuilder();
		$imageCountQuery->select($imageCountQuery->createFunction('COUNT(id) as count'))->from('facerecog_images');
        $userImageCountQuery = $this->dbConnection->getQueryBuilder();
		$userImageCountQuery->select($userImageCountQuery->createFunction('COUNT(*) as count'))->from('facerecog_user_images');

		$row  = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);

		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);

		$image = new Image();
		$image->id = 10;
		$image->user = "user3";

		//Act
        $this->imageMapper->removeUserImageConnection($image);

		//Assert
		$row = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);
		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'insert_Provider')]
	public function test_insert(string $user, int $model, int $nc_file_id, int $expectedFileCount, int $expectedConnections) : void {
		//Assert initial state
        $imageCountQuery = $this->dbConnection->getQueryBuilder();
		$imageCountQuery->select($imageCountQuery->createFunction('COUNT(id) as count'))->from('facerecog_images');
        $userImageCountQuery = $this->dbConnection->getQueryBuilder();
		$userImageCountQuery->select($userImageCountQuery->createFunction('COUNT(*) as count'))->from('facerecog_user_images');

		$row  = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(9, (int)$row['count']);

		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals(10, (int)$row['count']);

		$image = new Image();
		$image->user = $user;
		$image->setModel($model);
		$image->file = $nc_file_id;

		//Act
        $this->imageMapper->insert($image);

		//Assert
		$row = $imageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals($expectedFileCount, (int)$row['count']);
		$row = $userImageCountQuery->executeQuery()->fetch();
		$this->assertNotFalse($row);
		$this->assertEquals($expectedConnections, (int)$row['count']);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'findFromFile_Provider')]
	public function test_imageExists(string $user, int $model, int $nc_file_id, ?int $expectedId) : void {
		$image = new Image();
		$image->user = $user;
		$image->file = $nc_file_id;
		$image->setModel($model);

		//Act
        $resultId = $this->imageMapper->imageExists($image);

		//Assert
		if ($expectedId === null)
		{
			$this->assertNull($resultId);
		}
		else
		{
			$this->assertNotNull($resultId);
			$this->assertEquals($expectedId, $resultId);
		}
	}

    #[DataProviderExternal(ImageDataProvider::class, 'countImages_Provider')]
	public function test_countImages(int $model, int $expectedCount) : void {
		//Act
        $resultCount = $this->imageMapper->countImages($model);

		//Assert
		$this->assertEquals($expectedCount, $resultCount);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'countProcessedImages_Provider')]
	public function test_countProcessedImages(int $model, int $expectedCount) : void {
		//Act
        $resultCount = $this->imageMapper->countProcessedImages($model);

		//Assert
		$this->assertEquals($expectedCount, $resultCount);
	}

    #[DataProviderExternal(ImageDataProvider::class, 'avgProcessingDuration_Provider')]
	public function test_avgProcessingDuration(int $model, int $expectedCount) : void {
		//Act
        $resultCount = $this->imageMapper->avgProcessingDuration($model);

		//Assert
		$this->assertEquals($expectedCount, $resultCount);
	}

    public function tearDown(): void {
        if ($this->dbConnection != null) {
			$this->dbConnection->rollBack();
			return;
        }
		parent::tearDown();
	}
}
class ImageDataProvider{
    public static function findAll_Provider(): array {
        return [
            ["user1",1,5],
            ["user2",1,1],
            ["user2",2,4],
            ["user3",1,0], //not existing user
            ["user1",4,0], //not existing model
            ["user3",4,0], //not existing user and model
        ];
    }
	
    public static function findFromFile_Provider(): array {
        return [
            ["user1",1,101,1], //
            ["user1",2,101,null],
            ["user1",1,201,10], //
            ["user1",2,201,null],
            ["user2",1,102,null],
            ["user2",2,102,2], //
            ["user3",1,101,null], //not existing user
            ["user1",4,101,null], //not existing model
            ["user1",1,301,null], //not existing nc_file_Id
            ["user3",4,101,null], //not existing user and model
            ["user3",1,301,null], //not existing user and file
            ["user1",4,301,null], //not existing model and file
            ["user3",4,301,null], //not existing user and model and file
        ];
    }
	
    public static function insert_Provider(): array {
        return [
			//New FILE
            ["user1", 1, 150, 10, 11], //existing user
            ["user3", 1, 150, 10, 11], //not existing user
            [null, 1, 150, 10, 10], // not validuser
			//Existing file
            ["user1",1,101,9,10], // Existing user
            ["user3",1,101,9,11], // new user
            [null,1,101,9,10], // not valid user
			//New FILE, NEW model
            ["user1", 4, 150, 10, 11], //existing user
            ["user3", 4, 150, 10, 11], //not existing user
            [null, 4, 150, 10, 10], // not validuser
			//Existing file, new MODEL
            ["user1",4,101,10,11], // Existing user
            ["user3",4,101,10,11], // new user
            [null,4,101,10,10], // not valid user
        ];
    }
	
    public static function otherUserStilHasConnection_Provider(): array {
        return [
            [1, false],
            [10, true],
            [11, false] // non existing image
        ];
    }

    public static function countImages_Provider(): array {
        return [
            [1, 5],
            [2, 4],
            [3, 0] // non existing model
        ];
    }

    public static function countProcessedImages_Provider(): array {
        return [
            [1, 4],
            [2, 2],
            [3, 0] // non existing model
        ];
    }

    public static function avgProcessingDuration_Provider(): array {
        return [
            [1, 137],
            [2, 205],
            [3, 0] // non existing model
        ];
    }
}