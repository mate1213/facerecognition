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

use \OC;
use \OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

abstract class UnitBaseTestCase extends TestCase
{
	/** @var IDBConnection test instance*/
	protected static $dbConnection;
	/** @var bool*/
	private $isSetupComplete = false;
	/** @var bool */
	protected $runLargeTests = true;


	public static function setUpBeforeClass(): void {
		self::$dbConnection = OC::$server->getDatabaseConnection();
	}

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void{
		parent::setUp();
		self::$dbConnection->beginTransaction();

		if (!$this->isSetupComplete) {
			$this->isSetupComplete = true;
			$sql = file_get_contents("tests/DatabaseInserts/00_emptyDatabase.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/10_imageInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/20_userImagesInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/30_facesInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/40_clustersInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/50_clusterFacesInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/60_personInsert.sql");
			self::$dbConnection->executeStatement($sql);
			$sql = file_get_contents("tests/DatabaseInserts/70_personClustersInsert.sql");
			self::$dbConnection->executeStatement($sql);
			//self::$dbConnection->commit();
		}
	}

	public function tearDown(): void{
		if (self::$dbConnection != null) {
			self::$dbConnection->rollBack();
			return;
		}
		parent::tearDown();
	}
}
