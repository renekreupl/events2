<?php

namespace JWeiland\Events2\Tests\Unit\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Stefan Froemken <projects@jweiland.net>, jweiland.net
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use JWeiland\Events2\Domain\Model\Event;
use JWeiland\Events2\Domain\Repository\CategoryRepository;
use JWeiland\Events2\Domain\Repository\DayRepository;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * Test case.
 *
 * @author Stefan Froemken <projects@jweiland.net>
 */
class CategoryRepositoryTest extends UnitTestCase
{
    /**
     * @var \JWeiland\Events2\Domain\Repository\CategoryRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $subject;

    /**
     * set up.
     */
    public function setUp()
    {
        $this->subject = $this->getMock(CategoryRepository::class, array('createQuery'), array(), '', false);
    }

    /**
     * tear down.
     */
    public function tearDown()
    {
        unset($this->subject);
    }

    /**
     * @test
     */
    public function getSelectedCategoriesConvertsWrongCategoriesToInteger()
    {
        /** @var Query|\PHPUnit_Framework_MockObject_MockObject $query */
        $query = $this->getMock(Query::class, array(), array(), '', false);
        $query->expects($this->once())->method('matching')->willReturn($query);
        $query->expects($this->once())->method('in')->with(
            $this->equalTo('uid'),
            $this->equalTo(array(1,2,4))
        );

        $this->subject->expects($this->once())->method('createQuery')->willReturn($query);

        $this->subject->getSelectedCategories('1,2test,drei,4');
    }

    /**
     * @test
     */
    public function getSelectedCategoriesWithNonParentWillNotCallEquals()
    {
        /** @var Query|\PHPUnit_Framework_MockObject_MockObject $query */
        $query = $this->getMock(Query::class, array(), array(), '', false);
        $query->expects($this->never())->method('equals');
        $query->expects($this->once())->method('matching')->willReturn($query);

        $this->subject->expects($this->once())->method('createQuery')->willReturn($query);

        $this->subject->getSelectedCategories('1,2,3,4');
    }

    /**
     * @test
     */
    public function getSelectedCategoriesWithGivenParentWillCallEquals()
    {
        /** @var Query|\PHPUnit_Framework_MockObject_MockObject $query */
        $query = $this->getMock(Query::class, array(), array(), '', false);
        $query->expects($this->once())->method('equals')->with(
            $this->equalTo('parent'),
            $this->equalTo(5)
        );
        $query->expects($this->once())->method('matching')->willReturn($query);

        $this->subject->expects($this->once())->method('createQuery')->willReturn($query);

        // parent (5) should be casted to integer
        $this->subject->getSelectedCategories('1,2,3,4', '5');
    }
}
