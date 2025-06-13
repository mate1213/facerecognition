<?php

declare(strict_types=1);

namespace OCA\FaceRecognition\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

use OCP\IDBConnection;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version0980Date20250611141102 extends SimpleMigrationStep {

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
		//Get images which are duplicated
		$resultDuplicatedImages = getDuplicatedFiles($this->connection->getQueryBuilder());

		while ($row = $resultDuplicatedImages->fetch()) {
			$ncFileId = $row['file'];
			$modelNumber = $row['model'];
			$flaggedForKEEP = -1;
			//Get Images by nextcloud FileID and model <- these are shared files
			$resultImageEntries = getDuplicatedImages($this->connection->getQueryBuilder(), $ncFileId, $modelNumber);
			while ($duplicatedRow = $resultImageEntries->fetch()) {
				if	($flaggedForKEEP < 0)
				{
					$flaggedForKEEP = $duplicatedRow['id'];
				}
				else
				{
					$currentImage = $duplicatedRow['id'];
					updateUsersImages($this->connection->getQueryBuilder(), $currentImage, $flaggedForKEEP);

				}
			}
			$resultImageEntries->closeCursor();
		}
		$resultDuplicatedImages->closeCursor();
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	protected function getDuplicatedFiles(IQueryBuilder $builder): IResult {
		return $builder
			->select('file', 'model')
			->from('facerecog_images')
			->groupBy('file','model')
			->having('COUNT(*) > 1')
			->executeQuery();
	}

	protected function getDuplicatedImages(IQueryBuilder $builder, $ncFileId, $modelNumber): IResult {
		$and = $builder->expr()->andx(
    		$builder->expr()->eq('file', $ncFileId),
    		$builder->expr()->eq('model', $modelNumber),
		);
		return $builder
			->select('*')
			->from('facerecog_images')
			->Where($and)
			->executeQuery();
	}
	
	protected function updateUsersImages(IQueryBuilder $builder, $imageId, $newImageId): void {
		$builder
			->update('facerecog_user_images')
			->set('image', $newImageId)
			->Where($query->expr()->eq('file',$imageId))
			->executeStatement();
	}
	protected function HandleFaces(IQueryBuilder $builder, $imageId, $newImageId): void {
		$resultImageEntries=getFacesFromImage($builder, $imageId);
		while ($duplicatedRow = $resultImageEntries->fetch()) {
		}
	}
	
	protected function getFacesFromImage(IQueryBuilder $builder, $imageId): IResult {
		$builder
			->update('facerecog_faces')
			->set('image', $newImageId)
			->Where($query->expr()->eq('file',$imageId))
			->executeStatement();
	}
}
