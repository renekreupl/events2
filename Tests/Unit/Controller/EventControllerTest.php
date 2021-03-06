<?php

namespace JWeiland\Events2\Tests\Unit\Controller;

/*
 * This file is part of the events2 project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use JWeiland\Events2\Controller\EventController;
use JWeiland\Events2\Domain\Model\Event;
use JWeiland\Events2\Domain\Model\Search;
use JWeiland\Events2\Domain\Repository\CategoryRepository;
use JWeiland\Events2\Domain\Repository\DayRepository;
use JWeiland\Events2\Domain\Repository\EventRepository;
use JWeiland\Events2\Domain\Repository\LocationRepository;
use Nimut\TestingFramework\MockObject\AccessibleMockObjectInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Fluid\View\TemplateView;

/**
 * Test case.
 */
class EventControllerTest extends UnitTestCase
{
    /**
     * @var EventController|\PHPUnit_Framework_MockObject_MockObject|AccessibleMockObjectInterface
     */
    protected $subject;

    /**
     * @var DayRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $dayRepository;

    /**
     * @var EventRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $eventRepository;

    /**
     * @var LocationRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $locationRepository;

    /**
     * @var CategoryRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $categoryRepository;

    /**
     * @var Request|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $request;

    /**
     * @var ControllerContext|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $controllerContext;

    /**
     * @var ObjectManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $objectManager;

    /**
     * @var TemplateView|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $view;

    /**
     * set up.
     */
    public function setUp()
    {
        $this->request = $this->getMockBuilder(Request::class)->getMock();

        $this->controllerContext = $this->getMockBuilder(ControllerContext::class)->setMethods(['dummy'])->getMock();
        $this->controllerContext->setRequest($this->request);

        $this->dayRepository = $this
            ->getMockBuilder(DayRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->eventRepository = $this
            ->getMockBuilder(EventRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->locationRepository = $this
            ->getMockBuilder(LocationRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->categoryRepository = $this
            ->getMockBuilder(CategoryRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->view = $this
            ->getMockBuilder(TemplateView::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subject = $this->getAccessibleMock(EventController::class, ['dummy']);
        $this->subject->injectDayRepository($this->dayRepository);
        $this->subject->injectEventRepository($this->eventRepository);
        $this->subject->injectLocationRepository($this->locationRepository);
        $this->subject->injectCategoryRepository($this->categoryRepository);
        $this->subject->injectObjectManager($this->objectManager);
        $this->subject->_set('controllerContext', $this->controllerContext);
        $this->subject->_set('view', $this->view);
    }

    /**
     * taer down.
     */
    public function tearDown()
    {
        unset($this->subject);
        unset($this->dayRepository);
        unset($this->eventRepository);
        unset($this->view);
    }

    /**
     * @test
     */
    public function injectConfigurationManagerOverridesTypoScriptWithFlexFormSettings()
    {
        $typoScript = [
            'settings' => [
                'pidOfDetailPage' => '217',
                'pidOfListPage' => '217',
                'pidOfSearchPage' => '217',
                'pidOfLocationPage' => '217',
                'rootCategory' => '12'
            ]
        ];
        $flexFormSettings = [
            'pidOfDetailPage' => '0',
            'pidOfListPage' => '363',
            'pidOfSearchPage' => '217',
            'pidOfLocationPage' => '217',
            'rootCategory' => '12'
        ];

        $expectedResult = [
            'pidOfDetailPage' => '217',
            'pidOfListPage' => '363',
            'pidOfSearchPage' => '217',
            'pidOfLocationPage' => '217',
            'rootCategory' => '12'
        ];

        $configurationManager = $this->getMockBuilder(ConfigurationManager::class)->getMock();
        $configurationManager
            ->expects($this->at(0))
            ->method('getConfiguration')
            ->with(
                $this->identicalTo(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK),
                $this->identicalTo('events2'),
                $this->identicalTo('events2_event')
            )
            ->willReturn($typoScript);
        $configurationManager
            ->expects($this->at(1))
            ->method('getConfiguration')
            ->with(
                $this->identicalTo(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS)
            )
            ->willReturn($flexFormSettings);

        $this->subject->injectConfigurationManager($configurationManager);

        $this->assertSame(
            $expectedResult,
            $this->subject->_get('settings')
        );
    }

    /**
     * @test
     */
    public function listSearchResultsActionSearchesForEventsAndAssignsThemToView()
    {
        $search = new Search();
        $queryResultProphecy = $this->prophesize(QueryResult::class);

        $this->dayRepository
            ->expects($this->once())
            ->method('searchEvents')
            ->with($this->equalTo($search))
            ->willReturn($queryResultProphecy->reveal());

        $this->view
            ->expects($this->once())
            ->method('assign')
            ->with(
                $this->equalTo('days'),
                $this->equalTo($queryResultProphecy->reveal())
            );

        $this->subject->injectDayRepository($this->dayRepository);
        $this->subject->_set('view', $this->view);

        $this->subject->listSearchResultsAction($search);
    }

    /**
     * @test
     */
    public function listMyEventsActionFindEventsAndAssignsThemToView()
    {
        $queryResultProphecy = $this->prophesize(QueryResult::class);
        $tsfeBackup = $GLOBALS['TSFE'];
        $feUser = new \stdClass();
        $feUser->user = $user = [
            'uid' => 123,
        ];
        $GLOBALS['TSFE'] = new \stdClass();
        $GLOBALS['TSFE']->fe_user = $feUser;

        $this->eventRepository
            ->expects($this->once())
            ->method('findMyEvents')
            ->willReturn($queryResultProphecy->reveal());

        $this->view
            ->expects($this->once())
            ->method('assign')
            ->with(
                $this->equalTo('events'),
                $this->equalTo($queryResultProphecy->reveal())
            );

        $this->subject->injectEventRepository($this->eventRepository);
        $this->subject->_set('view', $this->view);

        $this->subject->listMyEventsAction();
        $GLOBALS['TSFE'] = $tsfeBackup;
    }

    /**
     * @test
     *
     * @expectedException \Exception
     * @expectedExceptionMessage You have forgotten to define some allowed categories in plugin configuration
     */
    public function newActionThrowsExceptionWhenCategoriesAreEmpty()
    {
        $event = new Event();
        $categories = new \ArrayObject();

        $this->request
            ->expects($this->once())
            ->method('hasArgument')
            ->with('event');
        $this->request
            ->expects($this->never())
            ->method('getArgument');

        $this->objectManager
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo(Event::class))
            ->willReturn($event);

        $this->categoryRepository
            ->expects($this->once())
            ->method('getCategories')
            ->with(
                $this->equalTo('23,24')
            )
            ->willReturn($categories);

        $this->subject->_set('settings', [
            'selectableCategoriesForNewEvents' => '23,24'
        ]);

        $this->subject->newAction();
    }

    /**
     * @test
     */
    public function newActionFillsTemplateVariables()
    {
        $event = new Event();
        $categories = new \ArrayObject();
        $categories->append('TestValue');

        $this->request
            ->expects($this->once())
            ->method('hasArgument')
            ->with('event');
        $this->request
            ->expects($this->never())
            ->method('getArgument');

        $this->objectManager
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo(Event::class))
            ->willReturn($event);

        $this->locationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->categoryRepository
            ->expects($this->once())
            ->method('getCategories')
            ->with(
                $this->equalTo('23,24')
            )
            ->willReturn($categories);

        $this->view
            ->expects($this->at(0))
            ->method('assign')
            ->with(
                $this->equalTo('event'),
                $this->equalTo($event)
            );
        $this->view
            ->expects($this->at(1))
            ->method('assign')
            ->with(
                $this->equalTo('locations'),
                $this->equalTo([])
            );
        $this->view
            ->expects($this->at(2))
            ->method('assign')
            ->with(
                $this->equalTo('selectableCategories'),
                $this->equalTo($categories)
            );

        $this->subject->_set('settings', [
            'selectableCategoriesForNewEvents' => '23,24'
        ]);

        $this->subject->newAction();
    }

    /**
     * @test
     */
    public function newActionWithoutImagesCallsDeleteUploadedFiles()
    {
        $categories = new \ArrayObject();
        $categories->append('TestValue');

        /** @var Event|\PHPUnit_Framework_MockObject_MockObject $event */
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event
            ->expects($this->once())
            ->method('getImages')
            ->willReturn([]);

        $this->categoryRepository
            ->expects($this->once())
            ->method('getCategories')
            ->willReturn($categories);

        $this->request
            ->expects($this->once())
            ->method('hasArgument')
            ->with('event')
            ->willReturn(true);
        $this->request
            ->expects($this->once())
            ->method('getArgument')
            ->with('event')
            ->willReturn($event);

        $this->subject->newAction();
    }

    /**
     * @test
     */
    public function newActionWithImagesCallsDeleteUploadedFiles()
    {
        $categories = new \ArrayObject();
        $categories->append('TestValue');

        /** @var FileReference|\PHPUnit_Framework_MockObject_MockObject $originalResource */
        $originalResource = $this
            ->getMockBuilder(FileReference::class)
            ->disableOriginalConstructor()
            ->getMock();
        $originalResource
            ->expects($this->once())
            ->method('delete');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FileReference|\PHPUnit_Framework_MockObject_MockObject $uploadedFile */
        $uploadedFile = $this->getMockBuilder(\TYPO3\CMS\Extbase\Domain\Model\FileReference::class)->getMock();
        $uploadedFile
            ->expects($this->once())
            ->method('getOriginalResource')
            ->willReturn($originalResource);

        $images = new ObjectStorage();
        $images->attach($uploadedFile);

        $event = new Event();
        $event->setImages($images);

        $this->categoryRepository
            ->expects($this->once())
            ->method('getCategories')
            ->willReturn($categories);

        $this->request
            ->expects($this->once())
            ->method('hasArgument')
            ->with('event')
            ->willReturn(true);
        $this->request
            ->expects($this->once())
            ->method('getArgument')
            ->with('event')
            ->willReturn($event);

        $this->subject->newAction();
    }
}
