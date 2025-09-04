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
namespace OCA\FaceRecognition\Tests\Unit\Mappers;

use DateTime;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

use OCA\FaceRecognition\Tests\Unit\UnitBaseTestCase;
use OCA\FaceRecognition\Db\PersonMapper;
use OCA\FaceRecognition\Db\Person;
use OC;
use OCP\IDBConnection;

#[CoversClass(PersonMapper::class)]
#[UsesClass(Person::class)]
class PersonMapperTest extends UnitBaseTestCase {
    /** @var PersonMapper test instance*/
	private $personMapper;

    /**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
        parent::setUp();
		$this->personMapper = new PersonMapper($this->dbConnection);
	}

    public function test_Find_nameExists() : void {
		//Act
        $person = $this->personMapper->find('user1', 1);

		//Assert
        $this->assertNotNull($person);
		$this->assertInstanceOf(Person::class, $person);
        $this->assertEquals(1, $person->getId());
		$this->assertEquals('user1', $person->getUser());
		$this->assertEquals('Alice', $person->getName());
		$this->assertEquals(null, $person->getLinkedUser());
		$this->assertEquals(true, $person->getIsVisible());
		$this->assertEquals(true, $person->getIsValid());
        $this->assertEquals(DateTime::createFromFormat('Y-m-d H:i:s', '2025-08-26 10:00:00'), $person->getLastGenerationTime());
	}

    public function test_Find_noNameExists() : void {
		//Act
        $person = $this->personMapper->find('user1', 3);

		//Assert
        $this->assertNotNull($person);
		$this->assertInstanceOf(Person::class, $person);
        $this->assertEquals(3, $person->getId());
		$this->assertEquals('user1', $person->getUser());
		$this->assertEquals(null, $person->getName());
		$this->assertEquals(null, $person->getLinkedUser());
		$this->assertEquals(true, $person->getIsVisible());
		$this->assertEquals(true, $person->getIsValid());
        $this->assertEquals(DateTime::createFromFormat('Y-m-d H:i:s', '2025-08-26 11:00:00'), $person->getLastGenerationTime());
	}

    public function test_Find_noneExisting() : void {
        $this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
        $this->expectExceptionMessage('Did expect one result but found none when executing: query "SELECT `c`.`id`, `user`, `p`.`name`, `is_visible`, `is_valid`, `last_generation_time`, `linked_user` FROM `*PREFIX*facerecog_clusters` `c` LEFT JOIN `*PREFIX*facerecog_person_clusters` `pc` ON `pc`.`cluster_id` = `c`.`id` LEFT JOIN `*PREFIX*facerecog_persons` `p` ON (`pc`.`person_id` = `p`.`id`) AND (`pc`.`cluster_id` IS NOT NULL) WHERE (`c`.`id` = :dcValue1) AND (`c`.`user` = :dcValue2)"');

		//Act
        $person = $this->personMapper->find('user1', 8);

		//Assert
        $this->assertNull($person);
	}
    

    #[DataProviderExternal(PersonDataProvider::class, 'findByName_Provider')]
    public function test_FindByName(string $userId, int $modelId, string $personName, int $expectedCount) : void {        
		//Act
        $people = $this->personMapper->findByName($userId, $modelId, $personName);

		//Assert
        $this->assertNotNull($people);
		$this->assertIsArray($people);
		$this->assertContainsOnlyInstancesOf(Person::class, $people);
		$this->assertCount($expectedCount, $people);
        if  ($expectedCount > 0)
        {
            foreach ($people as $person)
            {
                $this->assertEquals($userId, $person->getUser());
                $this->assertEquals($personName, $person->getName());
            }
        }
	}

    /**
	 * {@inheritDoc}
	 */
    public function tearDown(): void {
		parent::tearDown();
	}
}

class PersonDataProvider{
	public static function findByName_Provider(): array {
        return [
            ['user1',1,'Alice',1],
            ['user1', 3, 'Alice',0],
            ['user3', 1, 'Alice',0],
            ['user1',1,'Dummy',0],
            ['user2', 1, 'Alice',0],
            ['user2', 2, 'Bob',1],
    ];
    }
}
