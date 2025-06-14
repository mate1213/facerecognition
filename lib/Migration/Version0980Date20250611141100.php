<?php

declare(strict_types=1);

namespace OCA\FaceRecognition\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

use OCP\IDBConnection;

/**
 * MIIGRATE TO V0.9.8
 * - Step1: Create new tables and duplicate data into those
 * Step2: Deduplicate the images, and faces -> Remove original columns
 */
class Version0980Date20250611141100 extends SimpleMigrationStep {

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
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('facerecog_user_images')) {
			$table = $schema->createTable('facerecog_user_images');
			$table->addColumn('image', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
        }

        if (!$schema->hasTable('facerecog_person_faces')) {
			$table = $schema->createTable('facerecog_person_faces');
			$table->addColumn('person', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('face', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
        }
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		//Migrate to personFace N2N connection
		//Needs to support the shared files, record but different person groups
		$insertUserImages = $this->connection->getQueryBuilder();
		$insertUserImages
			->insert('facerecog_user_images')
			->values([
				'image' => '?',
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


		//Migrate to userImages N2N connection
		//Needs to support the shared files, record but different person groups
		$insertPersonFace = $this->connection->getQueryBuilder();
		$insertPersonFace
			->insert('facerecog_person_faces')
			->values([
				'face' => '?',
				'person' => '?'
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
}
