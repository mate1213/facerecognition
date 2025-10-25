<?php
/**
 * @copyright Copyright (c) 2020, Matias De lellis <mati86dl@gmail.com>
 *
 * @author Matias De lellis <mati86dl@gmail.com>
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
namespace OCA\FaceRecognition\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCP\App\IAppManager;

use OCP\IUser;
use OCP\IUserManager;

use OCA\FaceRecognition\Helper\PhotoAlbums;
use OCA\FaceRecognition\Db\ClusterMapper;

use OCA\FaceRecognition\Service\SettingsService;
use OCA\FaceRecognition\Traits\LoggerTrait;
use Psr\Log\LoggerInterface;

class SyncAlbumsCommand extends Command {

	use LoggerTrait;
	/** @var IUserManager */
	protected $userManager;

        /** @var ClusterMapper Person mapper*/
	private $clusterMapper;

	/** @var IAppManager */
	private $appManager;

	/** @var PhotoAlbums */
	protected $photoAlbums;

	/** @var SettingsService */
	private $settingsService;

	/**
	 * @param IUserManager $userManager
	 * @param ClusterMapper $clusterMapper
	 * @param PhotoAlbums $photoAlbums
	 * @param SettingsService $settingsService
	 * @param IAppManager $appManager
	 */
	public function __construct(IUserManager    $userManager,
	                            ClusterMapper    $clusterMapper,
	                            IAppManager     $appManager,
	                            PhotoAlbums     $photoAlbums,
	                            SettingsService $settingsService,
								LoggerInterface $logger)
	{
		parent::__construct();

		$this->setLogger($logger);
		$this->appManager      = $appManager;
		$this->clusterMapper    = $clusterMapper;
		$this->userManager     = $userManager;
		$this->photoAlbums     = $photoAlbums;
		$this->settingsService = $settingsService;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('face:sync-albums')
			->setDescription('Synchronize the people found with the photo albums')
			->addOption(
				'user_id',
				'u',
				InputOption::VALUE_REQUIRED,
				'Sync albums for a given user only. If not given, sync albums for all users.',
				null
			)->addOption(
				'list_person',
				'l',
				InputOption::VALUE_NONE,
				'List all persons defined for the given user_id.',
				null
			)->addOption(
				'person_name',
				'p',
				InputOption::VALUE_REQUIRED,
				'Sync albums for a given user and person name(s) (separate using comma). If not used, sync albums for all persons defined by the user.',
				null
			)->addOption(
				'mode',
				'm',
				InputOption::VALUE_REQUIRED,
				'Album creation mode. Use "album-per-person" to create one album for each given person via person_name parameter. Use "album-combined" to create one album for all person names given via person_name parameter.',
				'album-per-person'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->setOutput($output);
		if (!$this->appManager->isEnabledForUser('photos')) {
			$this->logError('The photos app is disabled.');
			return 1;
		}

		$users = array();
		$userId = $input->getOption('user_id');
		$person_name = $input->getOption('person_name');
		$mode = $input->getOption('mode');

		if (!is_null($userId)) {
			if ($this->userManager->get($userId) === null) {
				$this->logError("User with id <$userId> in unknown.");
				return 1;
			}
			else {
				$users[] = $userId;
			}
		}
		else {
			$this->userManager->callForAllUsers(function (IUser $iUser) use (&$users) {
				$users[] = $iUser->getUID();
			});
		}

		if ($input->getOption('list_person')) {
			if (is_null($userId)) {
				$this->logError("List option requires option user_id!");
				return 1;
			} else {
				$this->logInfo("List of defined persons for the user <$userId> :");
				$modelId = $this->settingsService->getCurrentFaceModel();
				$distintNames = $this->clusterMapper->findDistinctNames($userId, $modelId);
				
				// Build the names list
				$namesList = [];
				foreach ($distintNames as $distintName) {
					$namesList[] = $distintName->getName();
				}
				$this->logInfo(implode(", ", $namesList));
				$this->logInfo("Done.");
			}
			return 0;
		}

		foreach ($users as $userId) {
			if (!is_null($person_name)) {
				if (is_null($userId)) {
					$this->logError("Person_name option requires option user_id!");
					return 1;
				}
				$this->logInfo("Synchronizing albums for the user <$userId> and person_name <$person_name> using mode <$mode>... ");
				if ($mode === "album-per-person") {
					$personList = explode(",", $person_name);
					foreach ($personList as $person) {
						$this->photoAlbums->syncUserPersonNamesSelected($userId, $person, $output);
					}
				}
				else if ($mode === "album-combined") {
					$personList = explode(",", $person_name); 
					if (count($personList) < 2) {
						$this->logError("Note parameter mode <$mode> requires at least two persons separated using coma.");
						return 1;
					}
					$this->photoAlbums->syncUserPersonNamesCombinedAlbum($userId, $personList, $output);
				}
				else {
					$this->logError("Error: invalid value for parameter mode <$mode>. ");
					return 1;
				}
				$this->logInfo("Done.");
			} else {
				$this->logInfo("Synchronizing albums for the user <$userId>... ");
				$this->photoAlbums->syncUser($userId);
				$this->logInfo("Done.");
			}
		}

		return 0;
	}

}
