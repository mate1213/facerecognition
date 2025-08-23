<?php

declare(strict_types=1);

namespace OCA\FaceRecognition\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

use OCP\IDBConnection;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

/**
 * MIIGRATE TO V0.9.8
 * - Step1: Create new tables and duplicate data into those
 * Step2: Remove duplications from images, and faces -> Remove original columns
 */
class Version001000Date20250611141100 extends SimpleMigrationStep {

	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		//Start with table and column renaming
		if ($schema->hasTable('facerecog_faces')) {
			$table = $schema->getTable('facerecog_faces');
		    $table->renameColumn('image', 'image_id');
        }
		
		if ($schema->hasTable('facerecog_persons')) {
			$schema->renameTable('facerecog_persons', 'facerecog_clusters');
		}
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	//MTODO: Start with table and column renaming
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$output->warning("DO NOT STOP the migration process!");
		$output->warning("This might take a while depending on how many images you have");
		$schema = $schemaClosure();



		$faceTable = $schema->getTable('facerecog_faces');
		$faceIdOptions = $faceTable->getColumn('id')->toArray();
		unset($faceIdOptions['name']);
		unset($faceIdOptions['autoincrement']);

		$personsTable = $schema->getTable('facerecog_clusters');
		$personsIdOptions = $personsTable->getColumn('id')->toArray();
		unset($personsIdOptions['name']);
		unset($personsIdOptions['autoincrement']);

		$imageTable = $schema->getTable('facerecog_images');
		$imageIdOptions = $imageTable->getColumn('id')->toArray();
		unset($imageIdOptions['name']);
		unset($imageIdOptions['autoincrement']);

		if (!$schema->hasTable('facerecog_user_images')) {
			$table = $schema->createTable('facerecog_user_images');
			$table->addColumn('image_id', $imageIdOptions['type']->getName(), $imageIdOptions);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['image_id', 'user']);
			
			//Set Foreign key
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_images', // Remote Table
				['image_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_user_images_image_id'
				]
			);
        }

        if (!$schema->hasTable('facerecog_cluster_faces')) {
			$table = $schema->createTable('facerecog_cluster_faces');
			$table->addColumn('cluster_id', $personsIdOptions['type']->getName(), $personsIdOptions);
			$table->addColumn('face_id', $faceIdOptions['type']->getName(), $faceIdOptions);
			$table->setPrimaryKey(['cluster_id', 'face_id']);

			//Set Foreign keys
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_faces', // Remote Table
				['face_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_person_faces_face_id'
				]
			);
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_clusters', // Remote Table
				['person_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_person_faces_person_id'
				]
			);
        }
		if (!$schema->hasTable('facerecog_personNames')) {
			$table = $schema->createTable('facerecog_personNames');
			$table->addColumn('id', 'integer', [
				'autoincrement'=>true,
				'unsigned'=>true
			]);
			$table->addColumn('personName', 'string', [
				'length'=> 128
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['personName'], 'UNIQ_personName');
		}

		$personNamesTable = $schema->getTable('facerecog_personNames');
		$personNamesOptions = $personNamesTable->getColumn('id')->toArray();
		unset($personNamesOptions['name']);
		unset($personNamesOptions['autoincrement']);

		if (!$schema->hasTable('facerecog_name_persons')) {

			$table = $schema->createTable('facerecog_name_persons');
			$table->addColumn('cluster_id', $personsIdOptions['type']->getName(), $personsIdOptions);
			$table->addColumn('personName_id', $personNamesOptions['type']->getName(), $personNamesOptions);
			$table->setPrimaryKey(['person_id', 'personName_id']);

			//Set Foreign keys
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_personNames', // Remote Table
				['personName_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_name_persons_personName_id'
				]
			);
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_clusters', // Remote Table
				['cluster_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_name_persons_person_id'
				]
			);
        }

		if ($schema->hasTable('facerecog_faces')) {
			$table = $schema->getTable('facerecog_faces');

			//Set Foreign keys
			$table->addForeignKeyConstraint(
				'*PREFIX*facerecog_images', // Remote Table
				['image_id'], // Local Columns
				['id'], // Remote Columns
				[
					'onDelete' => 'CASCADE',
					'name' => 'fk_faces_image_id'
				]
			);
        }
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->warning("Connecting images with users, persons with faces and persons with personNames");
		$this->migratePersonNames();
		$this->connectImagesWithUsers();
		$this->connectPersonWithFaces();
		$this->connectPersonWithPersonNames();
	}

	private function connectPersonWithPersonNames() {
		//Migrate personNames - Person connection
		//Needs to support the shared persons
		$insertPersonNames = $this->connection->getQueryBuilder();
		$insertPersonNames
			->insert('facerecog_name_persons')
			->values([
				'person_id' => '?',
				'personName_id' => '?',
			]);

		//GetPersons with names
		$queryPersons = $this->connection->getQueryBuilder();
		$queryPersons->select('id', 'name')
			->from('facerecog_clusters', 'c')
			->Where($queryPersons->expr()->isNotNull('c.name'));

		$resultQueryPersons = $queryPersons->executeQuery();

		while ($row = $resultQueryPersons->fetch()) {
			//Get NameId for Person
			$queryPersonNames = $this->connection->getQueryBuilder();
			$queryPersonNames->select('id', 'personName')
				->from('facerecog_personNames', 'p')
				->Where($queryPersonNames->expr()->eq('personName', $queryPersonNames->createNamedParameter($row['name'])));

			$resultPersonNames = $queryPersonNames->executeQuery();
			while ($PersonNamesRow = $resultPersonNames->fetch()) {
				$insertPersonNames->setParameters([
					$row['id'],
					$PersonNamesRow['id']
				]);
				$insertPersonNames->executeStatement();
			}
			$resultPersonNames->closeCursor();
		}

		$resultQueryPersons->closeCursor();
	}
	
	private function migratePersonNames() {
		
		//Migrate to personNames to dedicated tabloe connection
		//Needs to support the shared persons
		$insertPersonNames = $this->connection->getQueryBuilder();
		$insertPersonNames
			->insert('facerecog_personNames')
			->values([
				'personName' => '?'
			]);

		$queryPersonNames = $this->connection->getQueryBuilder();
		$queryPersonNames->selectDistinct('name')
			->from('facerecog_clusters', 'c')
			->Where($queryPersonNames->expr()->isNotNull('c.name'));

		$resultPersonNames = $queryPersonNames->executeQuery();
		while ($row = $resultPersonNames->fetch()) {
			$insertPersonNames->setParameters([
				$row['name']
			]);
			$insertPersonNames->executeStatement();
		}
		$resultPersonNames->closeCursor();
	}

	private function connectPersonWithFaces(){
		//Migrate to personsFaces N2N connection
		//Needs to support the shared files, record but different person groups
		$insertPersonFace = $this->connection->getQueryBuilder();
		$insertPersonFace
			->insert('facerecog_cluster_faces')
			->values([
				'face_id' => '?',
				'person_id' => '?'
			]);

		$queryFaces = $this->connection->getQueryBuilder();
		$queryFaces->select('person','id')->from('facerecog_faces');

		$resultFaces = $queryFaces->executeQuery();
		while ($row = $resultFaces->fetch()) {
			$insertPersonFace->setParameters([
				$row['id'],
				$row['person']
			]);
			$insertPersonFace->executeStatement();
		}
		$resultFaces->closeCursor();
	}

	private function connectImagesWithUsers(){
		//Migrate to userImages N2N connection
		//Needs to support the shared files, record but different person groups
		$insertUserImages = $this->connection->getQueryBuilder();
		$insertUserImages
			->insert('facerecog_user_images')
			->values([
				'image_id' => '?',
				'user' => '?'
			]);

		$queryImages = $this->connection->getQueryBuilder();
		$queryImages->select('user','id')->from('facerecog_images');

		$resultImages = $queryImages->executeQuery();
		while ($row = $resultImages->fetch()) {
			$insertUserImages->setParameters([
				$row['id'],
				$row['user']
			]);
			$insertUserImages->executeStatement();
		}
		$resultImages->closeCursor();
	}
}
