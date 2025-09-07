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
	protected $dbConnection;
	private $isSetupComplete = false;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void
	{
		parent::setUp();
		$this->dbConnection = OC::$server->getDatabaseConnection();
		$this->dbConnection->beginTransaction();

		if (!$this->isSetupComplete) {
			$this->isSetupComplete = true;
			$sql = file_get_contents("tests/DatabaseInserts/00_emptyDatabase.sql");
			$this->dbConnection->executeStatement($sql);
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
			//$this->dbConnection->commit();
		}
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
