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

use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OC\DB\Exceptions\DbalException;

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
    public function test_countPersons(string $userId, int $modelId, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->countPersons($userId, $modelId);

        //Assert
        $this->assertNotNull($people);
        $this->assertEquals($expectedCount, $people);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'countClusters_Provider')]
    public function test_countClusters(string $userId, int $modelId, bool $onlyInvalid, int $expectedCount): void
    {
        //Act
        $people = $this->personMapper->countClusters($userId, $modelId, $onlyInvalid);

        //Assert
        $this->assertNotNull($people);
        $this->assertEquals($expectedCount, $people);
    }

    //MTODO: extend with userID; 
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'invalidatePersons_Provider')]
    public function test_invalidatePersons(int $imageId): void
    {
        //Act
        $this->personMapper->invalidatePersons($imageId);

        //Assert
        $sub = $this->dbConnection->getQueryBuilder();
        $query = $sub->select('c.id')
            ->from('facerecog_clusters', 'c')
            ->innerJoin('c', 'facerecog_cluster_faces', 'cf', $sub->expr()->eq('cf.cluster_id', 'c.id'))
            ->innerJoin('c', 'facerecog_faces', 'f', $sub->expr()->eq('cf.face_id', 'f.id'))
            ->innerJoin('c', 'facerecog_images', 'i', $sub->expr()->eq('f.image_id', 'i.id'))
            ->Where($sub->expr()->eq('f.image_id', $sub->createParameter('image_id')))
            ->andWhere($sub->expr()->eq('c.is_valid', $sub->createParameter('is_valid')))
            ->setParameter('image_id', $imageId)
            ->setParameter('is_valid', true, IQueryBuilder::PARAM_BOOL);
        $sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetchAll();
        $sqlResult->closeCursor();
        $this->assertCount(0, $modifiedValidClusters);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'deleteUserPersons_Provider')]
    public function test_deleteUserPersons(string $userId): void
    {
        //Act
        $this->personMapper->deleteUserPersons($userId);

        //Assert
        $sub = $this->dbConnection->getQueryBuilder();
        $query = $sub->select('c.id')
            ->from('facerecog_clusters', 'c')
            ->Where($sub->expr()->eq('c.user', $sub->createParameter('user')))
            ->setParameter('user', $userId, IQueryBuilder::PARAM_STR);
        $sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetchAll();
        $sqlResult->closeCursor();
        $this->assertCount(0, $modifiedValidClusters);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'deleteUserModel_Provider')]
    public function test_deleteUserModel(string $userId, int $modelId): void
    {
        //Act
        $this->personMapper->deleteUserModel($userId, $modelId);

        //Assert
        $sub = $this->dbConnection->getQueryBuilder();
        $query = $sub->select('c.id')
            ->from('facerecog_clusters', 'c')
            ->innerJoin('c', 'facerecog_cluster_faces', 'cf', $sub->expr()->eq('cf.cluster_id', 'c.id'))
            ->innerJoin('c', 'facerecog_faces', 'f', $sub->expr()->eq('cf.face_id', 'f.id'))
            ->innerJoin('c', 'facerecog_images', 'i', $sub->expr()->eq('f.image_id', 'i.id'))
            ->Where($sub->expr()->eq('c.user', $sub->createParameter('user')))
            ->andWhere($sub->expr()->eq('i.model', $sub->createParameter('model')))
            ->setParameter('user', $userId, IQueryBuilder::PARAM_STR)
            ->setParameter('model', $modelId, IQueryBuilder::PARAM_INT);
        $sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetchAll();
        $sqlResult->closeCursor();
        $this->assertCount(0, $modifiedValidClusters);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'removeIfEmpty_Provider')]
    public function test_removeIfEmpty(int $clusterId, bool $isDeleted): void
    {
        //Act
        $this->personMapper->removeIfEmpty($clusterId);

        //Assert
        $sub = $this->dbConnection->getQueryBuilder();
        $query = $sub->select('c.id')
            ->from('facerecog_clusters', 'c')
            ->Where($sub->expr()->eq('c.id', $sub->createParameter('id')))
            ->setParameter('id', $clusterId, IQueryBuilder::PARAM_INT);
        $sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetchAll();
        $sqlResult->closeCursor();
        if ($isDeleted) {
            $this->assertCount(0, $modifiedValidClusters);
        } else {
            $this->assertCount(1, $modifiedValidClusters);
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'deleteOrphaned_Provider')]
    public function test_deleteOrphaned(string $userId, int $expected): void
    {
        //Act
        $deletedEntries = $this->personMapper->deleteOrphaned($userId);

        //Assert
        $this->assertEquals($expected, $deletedEntries);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'deleteOrphaned_Provider')]
    public function test_deleteOrphaned_withDB(string $userId, int $expected): void
    {
        //Act
        $deletedEntries = $this->personMapper->deleteOrphaned($userId, $this->dbConnection);

        //Assert
        $this->assertEquals($expected, $deletedEntries);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'setVisibility_Provider')]
    public function test_setVisibility(int $clusterId, bool $visible): void
    {
        //Act
        $this->personMapper->setVisibility($clusterId, $visible);

        //Assert
        $sub = $this->dbConnection->getQueryBuilder();
        $query = $sub->select('c.id', 'p.name', 'is_visible')
            ->from('facerecog_clusters', 'c')
			->leftJoin('c', 'facerecog_person_clusters' ,'pc', $sub->expr()->eq('pc.cluster_id', 'c.id'))
			->leftJoin('c', 'facerecog_persons', 'p', $sub->expr()->eq('pc.person_id', 'p.id'))
            ->Where($sub->expr()->eq('c.id', $sub->createParameter('id')))
            ->setParameter('id', $clusterId, IQueryBuilder::PARAM_INT);
        $sqlResult = $query->executeQuery();
        $modifiedValidClusters = $sqlResult->fetch();
        $sqlResult->closeCursor();
        if ($visible)
        {
            $this->assertEquals($clusterId, $modifiedValidClusters['id']);
            $this->assertEquals(true, $modifiedValidClusters['is_visible']);
        } else {
            $this->assertEquals($clusterId, $modifiedValidClusters['id']);
            $this->assertEquals(null, $modifiedValidClusters['name']);
            $this->assertEquals(false, $modifiedValidClusters['is_visible']);

        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'detachFace_Provider')]
    public function test_detachFace(int $clusterId, int $faceId, ?string $name): void
    {
        //Act
        $person = $this->personMapper->detachFace($clusterId, $faceId, $name);

        //Assert
        $this->assertNotNull($person);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'insertPersonIfNotExists_Provider')]
    public function test_insertPersonIfNotExists(string $personName, int $expectedId): void
    {
        //Act
        $personId = $this->personMapper->insertPersonIfNotExists($personName);

        //Assert
        
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('facerecog_persons')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($personId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        $this->assertNotNull($personId);
        $this->assertGreaterThanOrEqual($expectedId, $personId);
        $this->assertNotFalse($data);
        $this->assertCount(1, $data);
        $this->assertGreaterThanOrEqual( $expectedId, $data[0]['id']);
        $this->assertEquals($personName, $data[0]['name']);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'insertPersonIfNotExists_Provider')]
    public function test_insertPersonIfNotExists_withDb(string $personName, int $expectedId): void
    {
        //Act
        $personId = $this->personMapper->insertPersonIfNotExists($personName, $this->dbConnection);

        //Assert
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'name')
			->from('facerecog_persons')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($personId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        $this->assertNotNull($personId);
        $this->assertGreaterThanOrEqual($expectedId, $personId);
        $this->assertNotFalse($data);
        $this->assertCount(1, $data);
        $this->assertGreaterThanOrEqual( $expectedId, $data[0]['id']);
        $this->assertEquals($personName, $data[0]['name']);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'updateClusterPersonConnection_Provider')]
    public function test_updateClusterPersonConnections(int $clusterId, ?string $personName, int $expectedId): void
    {
        //Act
        $this->personMapper->updateClusterPersonConnection($clusterId, $personName);

        //Assert
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('cluster_id', 'person_id')
			->from('facerecog_person_clusters')
			->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        if ($personName !== null)
        {
            $this->assertNotFalse($data);
            $this->assertCount(1, $data);
            $this->assertGreaterThanOrEqual($expectedId, $data[0]['person_id']);
            $this->assertEquals($clusterId, $data[0]['cluster_id']);
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('id', 'name')
                ->from('facerecog_persons')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data[0]['person_id'])));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();
            $this->assertEquals($personName, $data[0]['name']);
        } else {
            $this->assertEmpty($data);
        }

    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'updateClusterPersonConnection_Provider')]
    public function test_updateClusterPersonConnections_withDb(int $clusterId, ?string $personName, int $expectedId): void
    {
        //Act
        $this->personMapper->updateClusterPersonConnection($clusterId, $personName, $this->dbConnection);

        //Assert
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('cluster_id', 'person_id')
			->from('facerecog_person_clusters')
			->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        if ($personName !== null)
        {
            $this->assertNotFalse($data);
            $this->assertCount(1, $data);
            $this->assertGreaterThanOrEqual($expectedId, $data[0]['person_id']);
            $this->assertEquals($clusterId, $data[0]['cluster_id']);
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('id', 'name')
                ->from('facerecog_persons')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data[0]['person_id'])));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();
            $this->assertEquals($personName, $data[0]['name']);
        } else {
            $this->assertEmpty($data);
        }
    }
    
    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'updateClusterPersonConnection_error_Provider')]
    public function test_updateClusterPersonConnections_error(int $clusterId, ?string $personName, int $expectedId): void
    {
		$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('facerecog_person_clusters')
			->values(
				[
					'cluster_id' => $qb->createNamedParameter($clusterId),
					'person_id' =>  $qb->createNamedParameter(2)
				])
			->executeStatement();

        $this->expectException(MultipleObjectsReturnedException::class);
        $this->expectExceptionMessageMatches("/^Did not expect more than one result when executing: query/");

        //Act
        $this->personMapper->updateClusterPersonConnection($clusterId, $personName);

        //Assert
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('cluster_id', 'person_id')
			->from('facerecog_person_clusters')
			->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        if ($personName !== null)
        {
            $this->assertNotFalse($data);
            $this->assertCount(1, $data);
            $this->assertGreaterThanOrEqual($expectedId, $data[0]['person_id']);
            $this->assertEquals($clusterId, $data[0]['cluster_id']);
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('id', 'name')
                ->from('facerecog_persons')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data[0]['person_id'])));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();
            $this->assertEquals($personName, $data[0]['name']);
        } else {
            $this->assertEmpty($data);
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'updateClusterPersonConnection_error_Provider')]
    public function test_updateClusterPersonConnections_withDb_error(int $clusterId, ?string $personName, int $expectedId): void
    {
		$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('facerecog_person_clusters')
			->values(
				[
					'cluster_id' => $qb->createNamedParameter($clusterId),
					'person_id' =>  $qb->createNamedParameter(2)
				])
			->executeStatement();

        $this->expectException(MultipleObjectsReturnedException::class);
        $this->expectExceptionMessageMatches("/^Did not expect more than one result when executing: query/");

        //Act
        $this->personMapper->updateClusterPersonConnection($clusterId, $personName, $this->dbConnection);

        //Assert
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('cluster_id', 'person_id')
			->from('facerecog_person_clusters')
			->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)));
		$result = $qb->executeQuery();
		$data = $result->fetchAll();
		$result->closeCursor();

        if ($personName !== null)
        {
            $this->assertNotFalse($data);
            $this->assertCount(1, $data);
            $this->assertGreaterThanOrEqual($expectedId, $data[0]['person_id']);
            $this->assertEquals($clusterId, $data[0]['cluster_id']);
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('id', 'name')
                ->from('facerecog_persons')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($data[0]['person_id'])));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();
            $this->assertEquals($personName, $data[0]['name']);
        } else {
            $this->assertEmpty($data);
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'countClusterFaces_Provider')]
    public function test_countClusterFaces(int $clusterId, int $expectedcount): void
    {
        //Act
        $faceCount = $this->personMapper->countClusterFaces($clusterId);

        //Assert
        $this->assertNotNull($faceCount);
        $this->assertEquals($expectedcount, $faceCount);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'updateFace_Provider')]
    public function test_updateFace(int $faceId, ?int $oldClusterId, ?int $clusterId, bool $expectedError): void
    {
        if ($expectedError)
        {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches("/^No clusterId was given to face Id:[0-9]+/");
        }
        //Act
        $this->personMapper->updateFace($faceId, $oldClusterId, $clusterId);

        //Assert
        if ($clusterId !== null)
        {
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('cluster_id', 'face_id')
                ->from('facerecog_cluster_faces')
                ->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)))
                ->andwhere($qb->expr()->eq('face_id', $qb->createNamedParameter($faceId)));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();

            $this->assertNotFalse($data);
            $this->assertCount(1, $data);
        }
        if ($oldClusterId !== null)
        {
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('cluster_id', 'face_id')
                ->from('facerecog_cluster_faces')
                ->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($oldClusterId)))
                ->andwhere($qb->expr()->eq('face_id', $qb->createNamedParameter($faceId)));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();

            $this->assertEmpty($data);
        }
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'removeAllFacesFromPerson_Provider')]
    public function test_removeAllFacesFromPersonint(int $clusterId): void
    {
        //Act
        $this->personMapper->removeAllFacesFromPerson($clusterId);

        //Assert
            $qb = $this->dbConnection->getQueryBuilder();
            $qb->select('cluster_id', 'face_id')
                ->from('facerecog_cluster_faces')
                ->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)));
            $result = $qb->executeQuery();
            $data = $result->fetchAll();
            $result->closeCursor();
            $this->assertEmpty($data);
    }

    #[DataProviderExternal(className: PersonDataProvider::class, methodName: 'attachFaceToPerson_Provider')]
    public function test_attachFaceToPerson(int $clusterId, int $faceId, bool $expectError, ?string $message): void
    {
        if ($expectError)
        {
            $this->expectException(\OC\DB\Exceptions\DbalException::class);
            $this->expectExceptionMessage($message);
        }
        //Act
        $this->personMapper->attachFaceToPerson($clusterId, $faceId);

        //Assert
        $qb = $this->dbConnection->getQueryBuilder();
        $qb->select('cluster_id', 'face_id')
            ->from('facerecog_cluster_faces')
            ->where($qb->expr()->eq('cluster_id', $qb->createNamedParameter($clusterId)))
            ->where($qb->expr()->eq('face_id', $qb->createNamedParameter($faceId)));
        $result = $qb->executeQuery();
        $data = $result->fetchAll();
        $result->closeCursor();
        $this->assertIsArray($data);
        $this->greaterThanOrEqual(1, $data);
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
    public static function findByName_Provider(): array
    {
        return [
            ['user1', 1, 'Alice', 2],
            ['user1', 3, 'Alice', 0],
            ['user3', 1, 'Alice', 0],
            ['user1', 1, 'Dummy', 0],
            ['user2', 1, 'Alice', 0],
            ['user2', 2, 'Bob', 3],
        ];
    }

    public static function getPersonsByFlagsWithoutName_Provider(): array
    {
        return [
            //Existing user and model
            ['user1', 1, false, false, 0],
            ['user1', 1, false, true,  0],
            ['user1', 1, true, false,  4],
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
            ['user2', 2, false, true,  1],
            ['user2', 2, true, false,  0],
            ['user2', 2, true, true,   0],
        ];
    }

    public static function findIgnored_Provider(): array
    {
        return [
            //Existing user and model
            ['user1', 1, 4],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 0],
            ['user2', 2, 0],
        ];
    }

    public static function findUnassigned_Provider(): array
    {
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

    public static function findAll_Provider(): array
    {
        return [
            //Existing user and model
            ['user1', 1, 6],
            //nonexisting model
            ['user1', 3, 0],
            //nonexisting user
            ['user3', 1, 0],
            //User has mixed models
            ['user2', 1, 3],
            ['user2', 2, 3],
        ];
    }

    public static function findDistinctNames_Provider(): array
    {
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

    public static function findDistinctNamesSelected_Provider(): array
    {
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

    public static function findPersonsLike_Provider(): array
    {
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

    public static function countPersons_Provider(): array
    {
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

    public static function countClusters_Provider(): array
    {
        return [
            //Existing user and model
            ['user1', 1, false, 2],
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
            ['user2', 2, false, 2],
            ['user2', 2, true, 2],
        ];
    }

    public static function invalidatePersons_Provider(): array
    {
        return [
            //Single File
            [1],
            //SharedFile
            [10],
            //NonexistingFile
            [100],
        ];
    }

    public static function deleteUserPersons_Provider(): array
    {
        return [
            //User with single model
            ['user1'],
            //User with multiple model
            ['user2'],
            //Nonexisting User
            ['user3'],
        ];
    }

    public static function deleteUserModel_Provider(): array
    {
        return [
            //User with single model
            ['user1', 1],
            //User with not attached model
            ['user1', 2],
            //User with multiple model
            ['user2', 1],
            ['user2', 2],
            //Nonexisting user
            ['user3', 1],
            //Nonexisting model
            ['user2', 3],
            //Nonexisting user and model
            ['user3', 3],
        ];
    }

    public static function removeIfEmpty_Provider(): array
    {
        return [
            //Multiple face
            [1, false],
            //Single face
            [3, false],
            //No face
            [7, true],
            //Invalid Id
            [100, true],
        ];
    }

    public static function deleteOrphaned_Provider(): array
    {
        return [
            //User with single model
            ['user1',1],
            //User with multiple model
            ['user2',1],
            //Nonexisting User
            ['user3',0],
        ];
    }

    public static function setVisibility_Provider(): array
    {
        return [
            [1, false],
            [1, true],
            [3, false],
            [3, true],
            [10, false],
            [10, true]
        ];
    }

    public static function detachFace_Provider(): array
    {
        return [
            //multi face cluster has default name
            [2, 100, null],
            [2, 100, 'Dummy'],
            //Singe face cluster no default name
            [4, 4, null],
            [4, 4, 'Dummy1'],
            //Single face cluster has default name
            [1, 1, null],
            [1, 1, 'Dummy2'],
        ];
    }

    public static function insertPersonIfNotExists_Provider(): array
    {
        return [
            //Existing name
            ['Alice', 1],
            //NonExisting name
            ['Dummy', 3],
        ];
    }

    public static function updateClusterPersonConnection_Provider(): array
    {
        return [
            //remove Existing connection
            [1, null, 0],
            //rename Existing connection
            [1, 'Dummy', 3],
            //NonExisting connection
            [3, 'Dummy', 3],
            //No connection created
            [3, null, 0],
        ];
    }

    public static function updateClusterPersonConnection_error_Provider(): array
    {
        return [
            //remove Existing connection
            [1, null, 0],
            //rename Existing connection
            [1, 'Dummy', 3],
        ];
    }

    public static function countClusterFaces_Provider(): array
    {
        return [
            [1, 2],
            [2, 2],
            [3, 1],
            [10, 2]
        ];
    }

    public static function updateFace_Provider(): array
    {
        return [
            //invalid reuest
            [1, null, null, true],
            //Non attached face
            [9, null, 4, false],
            //migrate face
            [1, 1, 4, false],
            //detach face
            [1, 1, null, false],
        ];
    }

    public static function removeAllFacesFromPerson_Provider(): array
    {
        return [
            //multiple faces
            [1],
            //Single face
            [3],
            //no face
            [7],
        ];
    }
    
    public static function attachFaceToPerson_Provider(): array
    {
        return [
            //invalid connection
            [1, 1, true, "An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1' for key 'PRIMARY'"],
            //Single face
            [3, 5, false, null],
            //to empty cluster
            [7, 100, false, null],
            //nonexisting cluster
            [100, 100, true, "An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`nextcloud_db`.`oc_facerecog_cluster_faces`, CONSTRAINT `FK_CF21BF85C36A3328` FOREIGN KEY (`cluster_id`) REFERENCES `oc_facerecog_clusters` (`id`) ON DELETE CASCADE)"],
            //nonexisting face
            [1, 1000, true, "An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`nextcloud_db`.`oc_facerecog_cluster_faces`, CONSTRAINT `FK_CF21BF85FDC86CD0` FOREIGN KEY (`face_id`) REFERENCES `oc_facerecog_faces` (`id`) ON DELETE CASCADE)"],
            //nonexisting cluster and face
            [100, 1000, true, "An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`nextcloud_db`.`oc_facerecog_cluster_faces`, CONSTRAINT `FK_CF21BF85C36A3328` FOREIGN KEY (`cluster_id`) REFERENCES `oc_facerecog_clusters` (`id`) ON DELETE CASCADE)"],
        ];
    }
}
