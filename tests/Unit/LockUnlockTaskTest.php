<?php
/**
 * @copyright Copyright (c) 2017, Matias De lellis <mati86dl@gmail.com>
 * @copyright Copyright (c) 2018, Branko Kokanovic <branko@kokanovic.org>
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
namespace OCA\FaceRecognition\Tests\Unit;

use OCA\FaceRecognition\Helper\CommandLock;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommandLock::class)]
class LockUnlockTaskTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
	}

	public function testLockUnlock() {
		$lock = CommandLock::Lock("testLockUnlock");
		$this->assertNotNull($lock);
		$this->assertEquals("testLockUnlock", CommandLock::IsLockedBy());
		CommandLock::Unlock($lock);
	}

	public function testDoubleLock() {
		$lock = CommandLock::Lock("testDoubleLock");
		$this->assertNotNull($lock);
		$lock2 = CommandLock::Lock("testDoubleLockt2");
		$this->assertNull($lock2);
		$this->assertEquals("testDoubleLock", CommandLock::IsLockedBy());
		CommandLock::Unlock($lock);
	}
}