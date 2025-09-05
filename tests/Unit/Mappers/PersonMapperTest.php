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
use OCP\DB\QueryBuilder\IQueryBuilder;

#[CoversClass(PersonMapper::class)]
#[UsesClass(Person::class)]
class PersonMapperTest extends UnitBaseTestCase
{
    /** @var PersonMapper test instance*/
    private $personMapper;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->personMapper = new PersonMapper($this->dbConnection);
    }

    public function test_Find_nameExists(): void
    {
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

    public function test_Find_noNameExists(): void
    {
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

    public function test_Find_noneExisting(): void
    {
        $this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
        $this->expectExceptionMessage('Did expect one result but found none when executing: query "SELECT `c`.`id`, `user`, `p`.`name`, `is_visible`, `is_valid`, `last_generation_time`, `linked_user` FROM `*PREFIX*facerecog_clusters` `c` LEFT JOIN `*PREFIX*facerecog_person_clusters` `pc` ON `pc`.`cluster_id` = `c`.`id` LEFT JOIN `*PREFIX*facerecog_persons` `p` ON (`pc`.`person_id` = `p`.`id`) AND (`pc`.`cluster_id` IS NOT NULL) WHERE (`c`.`id` = :dcValue1) AND (`c`.`user` = :dcValue2)"');

        //Act
        $person = $this->personMapper->find('user1', 8);

        //Assert
        $this->assertNull($person);
    }

    #[DataProviderExternal(PersonDataProvider::class, 'findByName_Provider')]
    public function test_FindByName(string $userId, int $modelId, string $personName, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findByName($userId, $modelId, $personName);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertEquals($userId, $person->getUser());
                $this->assertEquals($personName, $person->getName());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'getPersonsByFlagsWithoutName_Provider')]
    public function test_getPersonsByFlagsWithoutName(string $userId, int $modelId, bool $isValid, bool $isVisible, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->getPersonsByFlagsWithoutName($userId, $modelId, $isValid, $isVisible);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertEquals($userId, $person->getUser());
                $this->assertEquals($isValid, $person->getIsValid());
                $this->assertEquals($isVisible, $person->getIsVisible());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findIgnored_Provider')]
    public function test_findIgnored(string $userId, int $modelId, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findIgnored($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertEquals($userId, $person->getUser());
                $this->assertEquals(true, $person->getIsValid());
                $this->assertEquals(false, $person->getIsVisible());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findUnassigned_Provider')]
    public function test_findUnassigned(string $userId, int $modelId, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findUnassigned($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertEquals($userId, $person->getUser());
                $this->assertEquals(true, $person->getIsValid());
                $this->assertEquals(true, $person->getIsVisible());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findAll_Provider')]
    public function test_findAll(string $userId, int $modelId, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findAll($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertEquals($userId, $person->getUser());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findDistinctNames_Provider')]
    public function test_findDistinctNames(string $userId, int $modelId, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findDistinctNames($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertNotNull($person->getName());
            }
        }
    }

    //MTODO: Undestand this function
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findDistinctNamesSelected_Provider')]
    public function test_findDistinctNamesSelected(string $userId, int $modelId, string $faceName, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findDistinctNamesSelected($userId, $modelId, $faceName);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertNotNull($person->getName());
            }
        }
    }

    //MTODO: Undestand this function
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'findPersonsLike_Provider')]
    public function test_findPersonsLike(string $userId, int $modelId, string $faceName, ?int $offset, ?int $limit, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->findPersonsLike($userId, $modelId, $faceName, $offset, $limit);

        //Assert
        $this->assertNotNull($people);
        $this->assertIsArray($people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertCount($expectedCount, $people);
        if ($expectedCount > 0) {
            foreach ($people as $person) {
                $this->assertNotNull($person->getName());
            }
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'countPersons_Provider')]
    public function test_countPersons(string $userId, int $modelId, int $expectedCount): void {
        //Act
        $people = $this->personMapper->countPersons($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertEquals($expectedCount, $people);
    }
    
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'countClusters_Provider')]
    public function test_countClusters(string $userId, int $modelId, bool $onlyInvalid, int $expectedCount): void {
        //Act
        $people = $this->personMapper->countClusters($userId, $modelId, $onlyInvalid);

        //Assert
        $this->assertNotNull($people);
        $this->assertEquals($expectedCount, $people);
    }
    
	//MTODO: extend with userID; 
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'invalidatePersons_Provider')]
    public function test_invalidatePersons(int $imageId): void {
        $sub = $this->dbConnection->getQueryBuilder();
		$query = $sub->select('c.id')
			->from('facerecog_clusters', 'c')
			->innerJoin('c', 'facerecog_cluster_faces' ,'cf', $sub->expr()->eq('cf.cluster_id', 'c.id'))
			->innerJoin('c', 'facerecog_faces' ,'f', $sub->expr()->eq('cf.face_id', 'f.id'))
			->innerJoin('c', 'facerecog_images' ,'i', $sub->expr()->eq('f.image_id', 'i.id'))
			->Where($sub->expr()->eq('f.image_id', $sub->createParameter('image_id')))
			->andWhere($sub->expr()->eq('c.is_valid', $sub->createParameter('is_valid')))
			->setParameter('image_id', $imageId)
			->setParameter('is_valid', true, IQueryBuilder::PARAM_BOOL);
        //Act
        $this->personMapper->invalidatePersons($imageId);

        //Assert
		$sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetchAll();
        $sqlResult->closeCursor();
        $this->assertCount(0, $modifiedValidClusters);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }
}

class PersonDataProvider
{
    public static function findByName_Provider(): array {
        return [
            ['user1', 1, 'Alice', 1],
            ['user1', 3, 'Alice', 0],
            ['user3', 1, 'Alice', 0],
            ['user1', 1, 'Dummy', 0],
            ['user2', 1, 'Alice', 0],
            ['user2', 2, 'Bob', 2],
        ];
    }

    public static function getPersonsByFlagsWithoutName_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, false, false, 0],
            ['user1', 1, false, true,  0],
            ['user1', 1, true, false,  5],
            ['user1', 1, true, true,   1],
            //nonexisting model
            ['user1', 3, false, false, 0],
            ['user1', 3, false, true,  0],
            ['user1', 3, true, false,  0],
            ['user1', 3, true, true,   0],
            //nonexisting user
            ['user3', 1, false, false, 0],
            ['user3', 1, false, true,  0],
            ['user3', 1, true, false,  0],
            ['user3', 1, true, true,   0],
            //User has mixed models
            ['user2', 1, false, false, 0],
            ['user2', 1, false, true,  0],
            ['user2', 1, true, false,  0],
            ['user2', 1, true, true,   2],

            ['user2', 2, false, false, 0],
            ['user2', 2, false, true,  2],
            ['user2', 2, true, false,  0],
            ['user2', 2, true, true,   0],
        ];
    }

    public static function findIgnored_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 5],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 0],
            ['user2', 2, 0],
        ];
    }

    public static function findUnassigned_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 1],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 2],
            ['user2', 2, 0],
        ];
    }

    public static function findAll_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 7],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 3],
            ['user2', 2, 4],
        ];
    }

    public static function findDistinctNames_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 1],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 1],
            ['user2', 2, 1],
        ];
    }

    public static function findDistinctNamesSelected_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 'Alice', 1],
            ['user1', 1, 'Bob', 0],
            //nonexisting model
            ['user1', 3, 'Alice', 0],
            //nonexisting user
            ['user3', 1, 'Dummy', 0],
            //User has mixed models
            ['user2', 1, 'Alice', 0],
            ['user2', 2, 'Bob', 1],
        ];
    }

    public static function findPersonsLike_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 'Alice', null, null, 1],
            ['user1', 1, 'Alice', 0, 1, 1],
            ['user1', 1, 'Bob', null, null, 0],
            ['user1', 1, 'Alice', 1, 1, 0],
            ['user1', 1, 'Bob', 1, 1, 0],
            ['user1', 1, 'Al', 1, 1, 0],
            ['user1', 1, 'Al', 0, 1, 1],
            ['user1', 1, 'Bo', 1, 1, 0],
            //nonexisting model
            ['user1', 3, 'Alice', null, null, 0],
            ['user1', 3, 'Alice', 1, 1, 0],
            //nonexisting user
            ['user3', 1, 'Dummy', null, null,  0],
            ['user3', 1, 'Dummy', 1, 1,  0],
            //User has mixed models
            ['user2', 1, 'Alice', null, null,  0],
            ['user2', 1, 'Alice', 1, 1,  0],
            ['user2', 2, 'Bob', null, null,  1],
            ['user2', 2, 'Bob', 1, 1,  0],
            ['user2', 2, 'Bob', 0, 1,  1],
            ['user2', 2, 'Bo', null, null,  1],
            ['user2', 2, 'Bo', 0, 1,  1],
            ['user2', 2, 'Bo', 1, 1,  0],
        ];
    }

    public static function countPersons_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, 1],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 1],
            ['user2', 2, 1],
        ];
    }

    public static function countClusters_Provider(): array {
        return [
            //Existing user and model
            ['user1', 1, false, 1],
            ['user1', 1, true, 0],
            //nonexisting model
            ['user1', 3, false, 0],
            ['user1', 3, true, 0],
            //nonexisting user
            ['user3', 1, false, 0],
            ['user3', 1, true, 0],
            //User has mixed models
            ['user2', 1, false, 4],
            ['user2', 1, true, 0],
            ['user2', 2, false, 1],
            ['user2', 2, true, 1],
        ];
    }

    public static function invalidatePersons_Provider(): array {
        return [
            //Single File
            [1],
            //SharedFile
            [10],
            //NonexistingFile
            [100],
        ];
    }
}
