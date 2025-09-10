<?php

/**
 * @copyright Copyright (c) 2017-2020, Matias De lellis <mati86dl@gmail.com>
 * @copyright Copyright (c) 2018-2019, Branko Kokanovic <branko@kokanovic.org>
 *
 * @author Branko Kokanovic <branko@kokanovic.org>
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

namespace OCA\FaceRecognition\Db;

use OCP\IDBConnection;
use OCP\IUser;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IFunctionBuilder;

class ImageMapper extends QBMapper
{
	/** @var FaceMapper Face mapper*/
	private $faceMapper;

	public function __construct(IDBConnection $db, FaceMapper $faceMapper){
		parent::__construct($db, 'facerecog_images', '\OCA\FaceRecognition\Db\Image');
		$this->faceMapper = $faceMapper;
	}

	/**
	 * @param string $userId Id of user
	 * @param int $imageId Id of Image to get
	 *
	 */
	public function find(string $userId, int $imageId): ?Image{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('ui.image_id', $qb->createNamedParameter($imageId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @param string $userId Id of user
	 * @param int $modelId Id of model to get
	 *
	 */
	public function findAll(string $userId, int $modelId): array{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($modelId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId Id of user
	 * @param int $modelId Id of model
	 * @param int $fileId Id of file to get Image
	 *
	 */
	public function findFromFile(string $userId, int $modelId, int $fileId): ?Image{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andwhere($qb->expr()->eq('i.model', $qb->createNamedParameter($modelId)))
			->andWhere($qb->expr()->eq('i.nc_file_id', $qb->createNamedParameter($fileId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @param int $imageId Id of Image Entry
	 */
	public function otherUserStilHasConnection(int $imageId): bool{
		$qb = $this->db->getQueryBuilder();
		$resultStatement = $qb
			->select($qb->func()->count('*'))
			->from('facerecog_user_images')
			->where($qb->expr()->eq('image_id', $qb->createNamedParameter($imageId)))
			->executeQuery();

		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();

		return (int)$data[0] > 1;
	}

	#[\Override]
	public function insert(Entity $image): Entity{
		$qb = $this->db->getQueryBuilder();
		$queryExec = $qb
			->select(['id'])
			->from($this->getTableName(), 'i')
			->Where($qb->expr()->eq('i.nc_file_id', $qb->createParameter('file')))
			->andWhere($qb->expr()->eq('i.model', $qb->createParameter('model')))
			->setParameter('file', $image->getFile())
			->setParameter('model', $image->getModel())
			->executeQuery();
		$row = $queryExec->fetch();
		$queryExec->closeCursor();

		$imageID = $row ? (int)$row['id'] : null;
		if ($imageID === null) {
			$insertImage = $this->db->getQueryBuilder();

			$insertImage
				->insert($this->getTableName())
				->values([
					'nc_file_id' => $insertImage->createNamedParameter($image->getFile()),
					'model' => $insertImage->createNamedParameter($image->getModel()),
				])->executeStatement();
			$imageID = $insertImage->getLastInsertId();
		}
		$insertUserImages = $this->db->getQueryBuilder();
		$insertUserImages->insert('facerecog_user_images')
			->values([
				'user' => $insertUserImages->createNamedParameter($image->getUser()),
				'image_id' => $insertUserImages->createNamedParameter($imageID)
			])->executeStatement();

		$image->setId((int) $imageID);
		return $image;
	}

	#[\Override]
	public function update(Entity $entity): Entity{
		// if entity wasn't changed it makes no sense to run a db query
		$properties = $entity->getUpdatedFields();
		if (count($properties) === 0)
			return $entity;
		// entity needs an id
		$id = $entity->getId();
		if ($id === null) {
			throw new \InvalidArgumentException(
				'Entity which should be updated has no id'
			);
		}

		// get updated fields to save, fields have to be set using a setter to
		// be saved
		// do not update the id field
		// do not update the user field
		unset($properties['id']);
		unset($properties['user']);

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName);

		// build the fields
		foreach ($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			if ($column === "file") {
				$column = "nc_file_id";
			}
			$getter = 'get' . ucfirst($property);
			$value = $entity->$getter();

			$type = $this->getParameterTypeForProperty($entity, $property);
			$qb->set($column, $qb->createNamedParameter($value, $type));
		}

		$idType = $this->getParameterTypeForProperty($entity, 'id');

		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($id, $idType))
		);
		$qb->executeStatement();

		return $entity;
	}

	#[\Override]
	public function delete(Entity $entity): Entity{
		return parent::delete($entity);
	}

	/**
	 * @param Entity $entity image entity
	 * @param string $userName name of user
	 */
	public function removeUserImageConnection(Entity $entity){
		$qb = $this->db->getQueryBuilder();

		$qb->delete('facerecog_user_images')
			->where(
				$qb->expr()->eq('image_id', $qb->createNamedParameter($entity->getId()))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($entity->getUser()))
			);
		$qb->executeStatement();
	}

	public function imageExists(Image $image): ?int{
		$qb = $this->db->getQueryBuilder();
		$query = $qb
			->select(['id'])
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createParameter('user')))
			->andWhere($qb->expr()->eq('i.nc_file_id', $qb->createParameter('file')))
			->andWhere($qb->expr()->eq('i.model', $qb->createParameter('model')))
			->setParameter('user', $image->getUser())
			->setParameter('file', $image->getFile())
			->setParameter('model', $image->getModel());
		$resultStatement = $query->executeQuery();
		$row = $resultStatement->fetch();
		$resultStatement->closeCursor();
		return $row ? (int)$row['id'] : null;
	}

	public function countImages(int $model): int{
		$qb = $this->db->getQueryBuilder();
		$query = $qb
			->select($qb->createFunction('COUNT(' . $qb->getColumnName('id') . ')'))
			->from($this->getTableName())
			->where($qb->expr()->eq('model', $qb->createParameter('model')))
			->setParameter('model', $model);
		$resultStatement = $query->executeQuery();
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();

		return (int)$data[0];
	}

	public function countProcessedImages(int $model): int{
		$qb = $this->db->getQueryBuilder();
		$query = $qb
			->select($qb->createFunction('COUNT(' . $qb->getColumnName('id') . ')'))
			->from($this->getTableName())
			->where($qb->expr()->eq('model', $qb->createParameter('model')))
			->andWhere($qb->expr()->eq('is_processed', $qb->createParameter('is_processed')))
			->setParameter('model', $model)
			->setParameter('is_processed', TRUE);
		$resultStatement = $query->executeQuery();
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();

		return (int)$data[0];
	}

	public function avgProcessingDuration(int $model): int{
		$sql = "SELECT AVG(`processing_duration`) FROM (select `processing_duration` FROM `*PREFIX*facerecog_images` WHERE (`model` = :model) AND (`is_processed` = :is_processed) ORDER BY `last_processed_time` DESC LIMIT 50) as t";
		$params = [
			'model' => $model,
			'is_processed' => true
		];
		$resultStatement = $this->db->executeQuery($sql, $params);
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();

		return (int)$data[0];
	}

	public function countUserImages(string $userId, int $model, bool $processed = false): int{
		$qb = $this->db->getQueryBuilder();
		$query = $qb
			->select($qb->createFunction('COUNT(' . $qb->getColumnName('id') . ')'))
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createParameter('user')))
			->andWhere($qb->expr()->eq('i.model', $qb->createParameter('model')))
			->setParameter('user', $userId)
			->setParameter('model', $model);

		if ($processed) {
			$query->andWhere($qb->expr()->eq('i.is_processed', $qb->createParameter('is_processed')))
				->setParameter('is_processed', true);
		}

		$resultStatement = $query->executeQuery();
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();

		return (int)$data[0];
	}

	/**
	 * @param IUser|null $user User for which to get images for. If not given, all images from instance are returned.
	 * @param int $modelId Model Id to get images for.
	 */
	public function findImagesWithoutFaces(?string $user, int $modelId): array{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('i.is_processed',  $qb->createParameter('is_processed')))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($modelId)))
			->setParameter('is_processed', false, IQueryBuilder::PARAM_BOOL);
		if (!is_null($user)) {
			$qb->andWhere($qb->expr()->eq('ui.user', $qb->createNamedParameter($user)));
		}
		return $this->findEntities($qb);
	}

	public function findImages(string $userId, int $model): array{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($model)));

		$images = $this->findEntities($qb);
		return $images;
	}

	//MTODO: NEVER called
	public function findFromPersonLike(string $userId, int $model, string $name, $offset = null, $limit = null): array{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_faces', 'f', $qb->expr()->eq('f.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_cluster_faces', 'cf', $qb->expr()->eq('cf.face_id', 'f.id'))
			->innerJoin('i', 'facerecog_person_clusters', 'pc', $qb->expr()->eq('pc.cluster_id', 'cf.cluster_id'))
			->innerJoin('i', 'facerecog_persons', 'p', $qb->expr()->eq('pc.person_id', 'p.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($model)))
			->andWhere($qb->expr()->eq('i.is_processed', $qb->createNamedParameter(True)))
			->andWhere($qb->expr()->like($qb->func()->lower('p.name'), $qb->createParameter('query')));

		$query = '%' . $this->db->escapeLikeParameter(strtolower($name)) . '%';
		$qb->setParameter('query', $query);

		$qb->setFirstResult($offset);
		$qb->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function findFromPerson(string $userId, int $modelId, string $name, $offset = null, $limit = null): array{
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'ui.user', 'i.model', 'i.nc_file_id as file', 'i.is_processed', 'i.error', 'i.last_processed_time', 'i.processing_duration')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_faces', 'f', $qb->expr()->eq('f.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_cluster_faces', 'cf', $qb->expr()->eq('cf.face_id', 'f.id'))
			->innerJoin('i', 'facerecog_person_clusters', 'pc', $qb->expr()->eq('pc.cluster_id', 'cf.cluster_id'))
			->innerJoin('i', 'facerecog_persons', 'p', $qb->expr()->eq('pc.person_id', 'p.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($modelId)))
			->andWhere($qb->expr()->eq('i.is_processed', $qb->createNamedParameter(True)))
			->andWhere($qb->expr()->eq('p.name', $qb->createNamedParameter($name)))
			->orderBy('i.nc_file_id', 'DESC');

		$qb->setFirstResult($offset);
		$qb->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function countFromPerson(string $userId, int $modelId, string $name): int{
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*'))
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $qb->expr()->eq('ui.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_faces', 'f', $qb->expr()->eq('f.image_id', 'i.id'))
			->innerJoin('i', 'facerecog_cluster_faces', 'cf', $qb->expr()->eq('cf.face_id', 'f.id'))
			->innerJoin('i', 'facerecog_person_clusters', 'pc', $qb->expr()->eq('pc.cluster_id', 'cf.cluster_id'))
			->innerJoin('i', 'facerecog_persons', 'p', $qb->expr()->eq('pc.person_id', 'p.id'))
			->where($qb->expr()->eq('ui.user', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('i.model', $qb->createNamedParameter($modelId)))
			->andWhere($qb->expr()->eq('i.is_processed', $qb->createNamedParameter(True)))
			->andWhere($qb->expr()->eq('p.name', $qb->createNamedParameter($name)));

		$result = $qb->executeQuery();
		$column = (int)$result->fetchOne();
		$result->closeCursor();

		return $column;
	}

	/**
	 * Writes to DB that image has been processed. Previously found faces are deleted and new ones are inserted.
	 * If there is exception, its stack trace is also updated.
	 *
	 * @param Image $image Image to be updated
	 * @param Face[] $faces Faces to insert
	 * @param int $duration Processing time, in milliseconds
	 * @param \Exception|null $e Any exception that happened during image processing
	 *
	 * @return void
	 */
	public function imageProcessed(int $imageId, array $faces, int $duration, \Exception $e = null): void{
		$this->db->beginTransaction();
		try {
			// Update image itself
			//
			$error = null;
			if ($e !== null) {
				$error = substr($e->getMessage(), 0, 1024);
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set("is_processed", $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
				->set("error", $qb->createNamedParameter($error))
				->set("last_processed_time", $qb->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATE))
				->set("processing_duration", $qb->createNamedParameter($duration))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($imageId)))
				->executeStatement();

			// Delete all previous faces
			//
			$this->faceMapper->removeFromImage($imageId, $this->db);

			// Insert all faces
			//
			foreach ($faces as $face) {
				$this->faceMapper->insertFace($face, $this->db);
			}

			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Resets image by deleting all associated faces and prepares it to be processed again
	 *
	 * @param Image $image Image to reset
	 *
	 * @return void
	 */
	public function resetImage(Image $image): void{
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set("is_processed", $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->set("error", $qb->createNamedParameter(null))
			->set("last_processed_time", $qb->createNamedParameter(null))
			->Where($qb->expr()->eq('nc_file_id', $qb->createNamedParameter($image->getFile())))
			->andWhere($qb->expr()->eq('model', $qb->createNamedParameter($image->getModel())))
			->executeStatement();
	}

	/**
	 * Resets all image with error from that user and prepares it to be processed again
	 *
	 * @param string $userId User to reset errors
	 *
	 * @return void
	 */
	public function resetErrors(string $userId): void{
		//Collect all imageId whitch has error and belongs to that user
		$sub = $this->db->getQueryBuilder();
		$sub->select('ui.image_id')
			->from($this->getTableName(), 'i')
			->innerJoin('i', 'facerecog_user_images', 'ui', $sub->expr()->eq('ui.image_id', 'i.id'))
			->where($sub->expr()->eq('ui.user', $sub->createParameter('userId')))
			->andWhere($sub->expr()->isNotNull('i.error'));

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set("is_processed", $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->set("error", $qb->createParameter('error'))
			->set("last_processed_time", $qb->createParameter("last_processed_time"))
			->Where('id in (' . $sub->getSQL() . ')')
			->setParameter('userId', $userId, IQueryBuilder::PARAM_STR)
			->setParameter('error', null)
			->setParameter('last_processed_time', null)
			->executeStatement();
	}

	/**
	 * Deletes all images from that user.
	 *
	 * @param string $userId User to drop images from table.
	 *
	 * @return void
	 */
	public function deleteUserImages(string $userId): void{
		//Delete User-ImageConnection
		$qb = $this->db->getQueryBuilder();
		$qb->delete('facerecog_user_images')
			->where($qb->expr()->eq('user', $qb->createNamedParameter($userId)))
			->executeStatement();

		//Collect all imageId whitch has no more references by other Users
		$sub = $this->db->getQueryBuilder();
		$sub->select('i.id')
			->from($this->getTableName(), 'i')
			->leftJoin('i', 'facerecog_user_images', 'ui', $sub->expr()->eq('ui.image_id', 'i.id'))
			->where($sub->expr()->isNull('ui.image_id'))
			->groupBy('i.id');

		//Delete image where the connection table has no reference
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->Where('id in (' . $sub->getSQL() . ')')
			->executeStatement();
	}

	/**
	 * Deletes all images from that user and Model
	 *
	 * @param string $userId User to drop images from table.
	 * @param int $modelId model to drop images from table.
	 *
	 * @return void
	 */
	public function deleteUserModel(string $userId, int $modelId): void{
		//Collect all imageId where user has connection and it's the required model
		$sub = $this->db->getQueryBuilder();
		$sub->select('i.id')
			->from($this->getTableName(), 'i')
			->leftJoin('i', 'facerecog_user_images', 'ui', $sub->expr()->eq('ui.image_id', 'i.id'))
			->where($sub->expr()->eq('ui.user', $sub->createParameter('userId')))
			->andWhere($sub->expr()->eq('i.model', $sub->createParameter('modelId')))
			->groupBy('i.id');
		//Delete User-ImageConnection
		$qb = $this->db->getQueryBuilder();
		$qb->delete('facerecog_user_images')
			->where($qb->expr()->eq('user', $qb->createParameter('userId')))
			->AndWhere('image_id in (' . $sub->getSQL() . ')')
			->setParameter('userId', $userId, IQueryBuilder::PARAM_STR)
			->setParameter('modelId', $modelId, IQueryBuilder::PARAM_INT)
			->executeStatement();

		//Collect all imageId whitch has no more references by other Users
		$sub = $this->db->getQueryBuilder();
		$sub->select('i.id')
			->from($this->getTableName(), 'i')
			->leftJoin('i', 'facerecog_user_images', 'ui', $sub->expr()->eq('ui.image_id', 'i.id'))
			->where($sub->expr()->isNull('ui.image_id'))
			->groupBy('i.id');
		//Delete image where the connection table has no reference
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->Where('id in (' . $sub->getSQL() . ')')
			->executeStatement();
	}
}
