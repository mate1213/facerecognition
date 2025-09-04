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
namespace OCA\FaceRecognition\Tests\Unit\DbOjects;

use DateTime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

use OCA\FaceRecognition\Tests\Unit\UnitBaseTestCase;
use OCA\FaceRecognition\Db\Face;
use OCP\AppFramework\Db\Entity;
use JsonSerializable;

#[CoversClass(Face::class)]
#[UsesClass(JsonSerializable::class)]
#[UsesClass(Entity::class)]
class FaceTest extends UnitBaseTestCase {
    /** @var Face test instance*/
	private $face;

    /**
	* {@inheritDoc}
	*/
	public function setUp(): void {
        $this->face = new Face();
        $this->face->setId(1);
        $this->face->setImage(1);
        $this->face->setPerson(2);
        $this->face->setX(10);
        $this->face->setY(20);
        $this->face->setWidth(100);
        $this->face->setHeight(200);
        $this->face->setConfidence(0.95);
        $this->face->setIsGroupable(true);
        $this->face->setLandmarks('[{"x":30,"y":40},{"x":50,"y":60},{"x":70,"y":80}]');
        $this->face->setDescriptor('[0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1.0]');
        $this->face->setCreationTime(new DateTime('2024-01-01 10:00:00'));
        $this->face->resetUpdatedFields();
        parent::setUp();
	}
    public function test_jsonSerialize() : void {
        //Act
        $jsonObject = $this->face->jsonSerialize();
        //Assert
        $this->assertNotNull($jsonObject);
        $this->assertIsArray($jsonObject);
        $this->assertCount(12,$jsonObject);
        $this->assertArrayHasKey('id',$jsonObject);
        $this->assertArrayHasKey('image',$jsonObject);
        $this->assertArrayHasKey('person',$jsonObject);
        $this->assertArrayHasKey('x',$jsonObject);
        $this->assertArrayHasKey('y',$jsonObject);
        $this->assertArrayHasKey('width',$jsonObject);
        $this->assertArrayHasKey('height',$jsonObject);
        $this->assertArrayHasKey('confidence',$jsonObject);
        $this->assertArrayHasKey('is_groupable',$jsonObject);
        $this->assertArrayHasKey('landmarks',$jsonObject);
        $this->assertArrayHasKey('descriptor',$jsonObject);
        $this->assertArrayHasKey('creation_time',$jsonObject);
    }

    
    public function test_getLandmarks() : void {
        //Act
        $jsonString = $this->face->getLandmarks();
        //Assert
        $this->assertNotNull($jsonString);
        $this->assertIsString($jsonString);
        $this->assertJson($jsonString);
        $this->assertEquals('[{"x":30,"y":40},{"x":50,"y":60},{"x":70,"y":80}]',$jsonString);
    }

    
    public function test_getDescriptor() : void {
        //Act
        $jsonString = $this->face->getDescriptor();
        //Assert
        $this->assertNotNull($jsonString);
        $this->assertIsString($jsonString);
        $this->assertJson($jsonString);
        $this->assertEquals('[0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1]',$jsonString);
    }

    public function test_fromModel_filledproperly() : void {
        $face = [];
		$face['left'] = 10;
		$face['right'] = 40;
		$face['top'] = 20;
		$face['bottom'] = 30;
		$face['detection_confidence'] = 0.85;
		$face['landmarks'] = [["x"=>30,"y"=>40],["x"=>50,"y"=>60],["x"=>70,"y"=>80]];
		$face['descriptor'] = [0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1.0];
        //Act
        $faceObject = $this->face->fromModel(1, $face);
        //Assert
        $this->assertNotNull($faceObject);
        $this->assertInstanceOf(Face::class,$faceObject);
        $this->assertEquals(1,$faceObject->getImage());
        $this->assertEquals(10,$faceObject->getX());
        $this->assertEquals(20,$faceObject->getY());
        $this->assertEquals(30,$faceObject->getWidth());
        $this->assertEquals(10,$faceObject->getHeight());
        $this->assertEquals(0.85,$faceObject->getConfidence());
        $this->assertEquals('[{"x":30,"y":40},{"x":50,"y":60},{"x":70,"y":80}]',$faceObject->getLandmarks());
        $this->assertEquals('[0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1]',$faceObject->getDescriptor());
        
    }
    public function test_fromModel_filledempty() : void {
        $face = [];
		$face['left'] = 10;
		$face['right'] = 40;
		$face['top'] = 20;
		$face['bottom'] = 30;
		$face['detection_confidence'] = 0.85;
		$face['landmarks'] = null;
		$face['descriptor'] = null;
        //Act
        $faceObject = $this->face->fromModel(1, $face);
        //Assert
        $this->assertNotNull($faceObject);
        $this->assertInstanceOf(Face::class,$faceObject);
        $this->assertEquals(1,$faceObject->getImage());
        $this->assertEquals(10,$faceObject->getX());
        $this->assertEquals(20,$faceObject->getY());
        $this->assertEquals(30,$faceObject->getWidth());
        $this->assertEquals(10,$faceObject->getHeight());
        $this->assertEquals(0.85,$faceObject->getConfidence());
        $this->assertEquals('[]',$faceObject->getLandmarks());
        $this->assertEquals('[]',$faceObject->getDescriptor());
    }
    /**
	* {@inheritDoc}
	*/
    public function tearDown(): void {
		parent::tearDown();
	}
}