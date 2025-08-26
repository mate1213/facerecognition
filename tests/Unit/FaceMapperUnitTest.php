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

    	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
        parent::setUp();
        $dbConnection = OC::$server->getDatabaseConnection();
		$dbConnection->beginTransaction();

		$this->faceMapper = new FaceMapper($dbConnection);
	}
	public function testFindById() {
        $face = $this->faceMapper->find(1);
		$this->assertInstanceOf(Face::class, $face);
        $this->assertNotNull($face);
        $this->assertEquals(1, $face->getId());
		$this->assertNotNull($face->getImage());
    }

    public function tearDown(): void
	{
        if ($this->dbConnection != null) {
		    $dbConnection->rollBack();
            return;
        }
		parent::tearDown();
	}
}