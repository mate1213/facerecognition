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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use OCA\FaceRecognition\Db\FaceMapper;
use OCA\FaceRecognition\Db\Face;
use OC;
use OCP\IDBConnection;

#[CoversClass(FaceMapper::class)]
#[UsesClass(Face::class)]
class FaceMapperUnitTest extends TestCase {
    /** @var FaceMapper test instance*/
	private $faceMapper;
    /** @var IDBConnection test instance*/
    private $dbConnection;
	private $isSetupComplete = false;
    	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
        parent::setUp();
        $this->dbConnection = OC::$server->getDatabaseConnection();
		$this->dbConnection->beginTransaction();

		$this->faceMapper = new FaceMapper($this->dbConnection);
		// if ($this->isSetupComplete === false) {
		// 	$this->isSetupComplete = true;
		// 	$sql = file_get_contents("tests/DatabaseInserts/10_imageInsert.sql");
		// 	$this->dbConnection->executeStatement($sql);
		// 	$sql = file_get_contents("tests/DatabaseInserts/20_userImagesInsert.sql");
		// 	$this->dbConnection->executeStatement($sql);
		// 	$sql = file_get_contents("tests/DatabaseInserts/30_facesInsert.sql");
		// 	$this->dbConnection->executeStatement($sql);
		// }
	}

	public function testFindById() {
		//Act
        $face = $this->faceMapper->find(1);

		//Assert
        $this->assertNotNull($face);
		$this->assertInstanceOf(Face::class, $face);
        $this->assertEquals(1, $face->getId());
		$this->assertEquals(1, $face->getImage());
		$this->assertEquals('[0.23,-0.45,0.67,-0.12,0.89,-0.34,0.56,-0.78,0.9,-0.11,0.22,-0.33,0.44,-0.55,0.66,-0.77,0.88,-0.99,0.01,-0.02,0.03,-0.04,0.05,-0.06,0.07,-0.08,0.09,-0.1,0.11,-0.12,0.13,-0.14,0.15,-0.16,0.17,-0.18,0.19,-0.2,0.21,-0.22,0.23,-0.24,0.25,-0.26,0.27,-0.28,0.29,-0.3,0.31,-0.32,0.33,-0.34,0.35,-0.36,0.37,-0.38,0.39,-0.4,0.41,-0.42,0.43,-0.44,0.45,-0.46,0.47,-0.48,0.49,-0.5,0.51,-0.52,0.53,-0.54,0.55,-0.56,0.57,-0.58,0.59,-0.6,0.61,-0.62,0.63,-0.64,0.65,-0.66,0.67,-0.68,0.69,-0.7,0.71,-0.72,0.73,-0.74,0.75,-0.76,0.77,-0.78,0.79,-0.8,0.81,-0.82,0.83,-0.84,0.85,-0.86,0.87,-0.88,0.89,-0.9,0.91,-0.92,0.93,-0.94,0.95,-0.96,0.97,-0.98,0.99,-1,0.01,0.02,0.03,0.04,0.05,0.06,0.07,0.08,0.09,0.1]', $face->getDescriptor());
		$this->assertEquals(DateTime::createFromFormat('Y-m-d HH:MM:ss', '2025-08-26 10:06:00'), $face->getCreationTime());
		$this->assertEquals(0.98, $face->getConfidence());
		$this->assertEquals('"[\n    {\"x\": 12, \"y\": 34},\n    {\"x\": 45, \"y\": 67},\n    {\"x\": 23, \"y\": 56},\n    {\"x\": 78, \"y\": 12},\n    {\"x\": 34, \"y\": 89},\n    {\"x\": 56, \"y\": 23}\n  ]"', $face->getLandmarks());
		$this->assertEquals(10, $face->getX());
		$this->assertEquals(20, $face->getY());
		$this->assertEquals(30, $face->getWidth());
		$this->assertEquals(40, $face->getHeight());
	}

	public function testFindDescriptorsBathed() {
		//Act
        $descriptors = $this->faceMapper->findDescriptorsBathed([1,2]);

		//Assert
        $this->assertNotNull($descriptors);
		$this->assertIsArray($descriptors);
		$this->assertCount(2, $descriptors);

		$firstDescriptor = $descriptors[0];
		$secondDescriptor = $descriptors[1];

		$this->assertIsArray($firstDescriptor['descriptor']);
		$this->assertCount(128, $firstDescriptor['descriptor']);
		$this->assertEquals(1, $firstDescriptor['id']);
		$this->assertIsArray($secondDescriptor['descriptor']);
		$this->assertCount(128, $secondDescriptor['descriptor']);
		$this->assertEquals(2, $secondDescriptor['id']);
	}

	public function testFindFromFile() {
		//Act
        $faces = $this->faceMapper->findFromFile("user1", 1, 101);

		//Assert
        $this->assertNotNull($faces);
		$this->assertIsArray($faces);
		$this->assertContainsOnlyInstancesOf(Face::class, $faces);
		$this->assertCount(1, $faces);
		
	}

    public function tearDown(): void
	{
        if ($this->dbConnection != null) {
			$this->dbConnection->rollBack();
			return;
        }
		parent::tearDown();
	}
}