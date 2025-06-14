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
 * MIIGRATE TO V0.9.8
 * Step1: Create new tables and duplicate data into those
 * Step2: Remove original columns
 * - Step3: Deduplicate the images, and faces
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
			$resultImageEntries = getImagesByModelAndNcFileId($this->connection->getQueryBuilder(), $ncFileId, $modelNumber);
			while ($duplicatedRow = $resultImageEntries->fetch()) {
				if	($flaggedForKEEP < 0)
				{
					//Keep the first of all duplicates
					$flaggedForKEEP = $duplicatedRow['id'];
				}
				else
				{
					//Handle other duplicates
					$currentImage = $duplicatedRow['id'];
					updateUsersImages($this->connection->getQueryBuilder(), $currentImage, $flaggedForKEEP);
					handleFaces($this->connection->getQueryBuilder(), $currentImage, $flaggedForKEEP);
					removeImage($this->connection->getQueryBuilder(), $currentImage);
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

	/**
	 * Update old image ID to the one we keep
	 * @param IQueryBuilder $builder
	 * @param $imageId image ID what is removed
	 * @param $flaggedForKEEP image ID what is replaced to
	 */
	protected function updateUsersImages(IQueryBuilder $builder, $imageId,  $flaggedForKEEP): void {
		$builder
			->update('facerecog_user_images')
			->set('image', $flaggedForKEEP)
			->Where($query->expr()->eq('file',$imageId))
			->executeStatement();
	}

	/**
	 * Update faces of the image to be connected to the image what we keep after the deduplication
	 * Or if faces are duplicate remove the one which is connected to the deletable picture
	 * @param IQueryBuilder $builder
	 * @param $imageId the old image ID
	 * @param $flaggedForKEEP new image ID what is replaced to
	 */
	protected function handleFaces(IQueryBuilder $builder, $imageId, $flaggedForKEEP): void {
		$resultOldFaceEntries=getFacesFromImage($builder, $imageId);
		$resultNewFaceEntries=getFacesFromImage($builder, $flaggedForKEEP);
		while ($oldFace = $resultOldFaceEntries->fetch()) {
			$isDeleted = false;
			while ($newFace = $resultNewFaceEntries->fetch()){
				if (isSameFace($oldFace, $newFace)){
					updatePersonAndDeleteFace( $builder, $oldFace['id'], $newFace['id']);
					$isDeleted = true;
					break;
				}
			}
			if(!$isDeleted){
				updateFaceToNewImage($builder, $oldFace['id'], $flaggedForKEEP);
			}
		}
	}

	/**
	 * Get Nextcloud internal file ids which are duplicated by the same model
	 * @return IResult Columns: file, model
	 */
	protected function getDuplicatedFiles(IQueryBuilder $builder): IResult {
		return $builder
			->select('file', 'model')
			->from('facerecog_images')
			->groupBy('file','model')
			->having('COUNT(*) > 1')
			->executeQuery();
	}

	/**
	 * Get Image based on NC file ID and the model ID
	 * @return IResult
	 */
	protected function getImagesByModelAndNcFileId(IQueryBuilder $builder, $ncFileId, $modelNumber): IResult {
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
	
	/**
	 * Get Face based on image ID
	 * @return IResult
	 */
	protected function getFacesFromImage(IQueryBuilder $builder, $imageId): IResult {
		$builder
			->select('*')
			->from('facerecog_faces')
			->Where($builder->expr()->eq('image',$imageId))
			->executeQuery();
	}

	/**
	 * Update faces of the image to be connected to the image what we keep after the deduplication
	 * @param IQueryBuilder $builder
	 * @param $imageId the old image ID
	 * @param $flaggedForKEEP new image ID what is replaced to
	 */
	protected function updateFaceToNewImage(IQueryBuilder $builder, $faceId, $newImageId): void {
		$builder
			->update('facerecog_faces')
			->set('image', $newImageId)
			->Where($query->expr()->eq('id',$faceId))
			->executeStatement();
	}

	/**
	 * If the face is duplicate, update the person to be connected to the face we keep and delete the old face
	 * @param IQueryBuilder $builder
	 * @param $oldFaceId the old face ID
	 * @param $newFaceId new face ID what is replaced to
	 */
	protected function updatePersonAndDeleteFace(IQueryBuilder $builder, $oldFaceId, $newFaceId): void {
		$builder
			->update('facerecog_person_faces')
			->set('face', $newFaceId)
			->Where($query->expr()->eq('face',$oldFaceId))
			->executeStatement();
		$builder
			->delete('facerecog_faces')
			->where($query->expr()->eq('id',$oldFaceId))
			->executeStatement();
	}
	
	/**
	 * Check if 2 face are the same.
	 * Checked fields:
	 * - x
	 * - y
	 * - width
	 * - height
	 * - confidence
	 * - landmarks
	 * - descriptor
	 * @param IQueryBuilder $builder
	 * @param $oldFace the old face complete database row
	 * @param $newFace new face complete database row
	 * @return bool True if the are equal otherwise false
	 */
	protected function isSameFace($oldFace, $newFace): bool {
		return
			$oldFace['x']==$newFace['x'] &&
			$oldFace['y']==$newFace['y'] &&
			$oldFace['width']==$newFace['width'] &&
			$oldFace['height']==$newFace['height'] &&
			$oldFace['confidence']==$newFace['confidence'] &&
			$oldFace['landmarks']==$newFace['landmarks']  &&
			$oldFace['descriptor']==$newFace['descriptor'];
	}

	/**
	 * Remove image by image ID
	 * @param IQueryBuilder $builder
	 * @param $imageId image ID what is removed
	 */
	protected function removeImage(IQueryBuilder $builder, $imageId): void {
		$builder
			->delete('facerecog_images')
			->where($query->expr()->eq('id',$imageId))
			->executeStatement();
	}
}
