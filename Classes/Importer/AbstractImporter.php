<?php

namespace JWeiland\Events2\Importer;

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

use JWeiland\Events2\Domain\Model\Event;
use JWeiland\Events2\Domain\Repository\CategoryRepository;
use JWeiland\Events2\Domain\Repository\LocationRepository;
use JWeiland\Events2\Domain\Repository\OrganizerRepository;
use JWeiland\Events2\Utility\DateTimeUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Abstract Importer which will keep most methods for all importer scripts
 */
abstract class AbstractImporter implements ImporterInterface
{
    /**
     * @var FileInterface
     */
    protected $file;

    /**
     * @var string
     */
    protected $logFileName = 'Messages.txt';

    /**
     * @var array
     */
    protected $allowedMimeType = [];

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var OrganizerRepository
     */
    protected $organizerRepository;

    /**
     * @var LocationRepository
     */
    protected $locationRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var DateTimeUtility
     */
    protected $dateTimeUtility;

    /**
     * @var \DateTime
     */
    protected $today;

    /**
     * XmlImporter constructor.
     *
     * @param FileInterface $file
     */
    public function __construct(FileInterface $file)
    {
        $this->file = $file;
    }

    /**
     * Initialize this object
     *
     * @return void
     */
    public function initialize()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->organizerRepository = $this->objectManager->get(OrganizerRepository::class);
        $this->locationRepository = $this->objectManager->get(LocationRepository::class);
        $this->categoryRepository = $this->objectManager->get(CategoryRepository::class);
        $this->dateTimeUtility = $this->objectManager->get(DateTimeUtility::class);
        $this->today = new \DateTime('now');
    }

    /**
     * Check, if File is valid for this importer
     *
     * @param FileInterface $file
     * @return bool
     * @throws \Exception
     */
    public function isValid(FileInterface $file)
    {
        $isValid = true;

        if (!in_array($file->getMimeType(), $this->allowedMimeType)) {
            $isValid = false;
            $this->addMessage('MimeType of file is not allowed', FlashMessage::ERROR);
        }

        return $isValid;
    }

    /**
     * Check, if we have invalid events in our array
     *
     * @param array $events
     * @return bool
     * @throws \Exception
     */
    protected function hasInvalidEvents(array $events)
    {
        $hasInvalidEvents = false;
        foreach ($events as $event) {
            if (!$this->isValidEvent($event)) {
                $hasInvalidEvents = true;
                break;
            }
        }

        return $hasInvalidEvents;
    }

    /**
     * Is valid event
     * It also checks, if given relations exists in DB
     *
     * @param array $event
     * @return bool
     * @throws \Exception
     */
    protected function isValidEvent(array $event)
    {
        // is future event?
        $eventBegin = \DateTime::createFromFormat('Y-m-d', $event['event_begin']);
        if ($eventBegin < $this->today) {
            $this->addMessage(
                sprintf(
                    'Event: %s - Date: %s - Error: %s',
                    $event['title'],
                    $eventBegin->format('d.m.Y'),
                    'event_begin can not be in past'
                ),
                FlashMessage::ERROR
            );
            return false;
        }

        // specified organizer exists?
        $organizer = $this->getOrganizer($event['organizer']);
        if (empty($organizer)) {
            $this->addMessage(
                sprintf(
                    'Event: %s - Date: %s - Error: %s',
                    $event['title'],
                    $eventBegin->format('d.m.Y'),
                    'Given organizer "' . $event['organizer'] . '" does not exist in our database'
                ),
                FlashMessage::ERROR
            );
            return false;
        }

        // specified location exists?
        $location = $this->getLocation($event['location']);
        if (empty($location)) {
            $this->addMessage(
                sprintf(
                    'Event: %s - Date: %s - Error: %s',
                    $event['title'],
                    $eventBegin->format('d.m.Y'),
                    'Given location "' . $event['location'] . '" does not exist in our database'
                ),
                FlashMessage::ERROR
            );
            return false;
        }

        // specified categories exists?
        foreach ($event['categories'] as $title) {
            $category = $this->getCategory($title);
            if (empty($category)) {
                $this->addMessage(
                    sprintf(
                        'Event: %s - Date: %s - Error: %s',
                        $event['title'],
                        $eventBegin->format('d.m.Y'),
                        'Given category "' . $title . '" does not exist in our database'
                    ),
                    FlashMessage::ERROR
                );
                return false;
            }
        }

        // check for valid image paths
        if (isset($event['images']) && is_array($event['images'])) {
            foreach ($event['images'] as $image) {
                if (!is_array($image)) {
                    $this->addMessage(
                        sprintf(
                            'Event: %s - Date: %s - Error: %s',
                            $event['title'],
                            $eventBegin->format('d.m.Y'),
                            'Image must be of type array'
                        ),
                        FlashMessage::ERROR
                    );
                    return false;
                }
                if (!isset($image['url']) || empty(trim($image['url']))) {
                    $this->addMessage(
                        sprintf(
                            'Event: %s - Date: %s - Error: %s',
                            $event['title'],
                            $eventBegin->format('d.m.Y'),
                            'Array key "url" of image must be set and can not be empty'
                        ),
                        FlashMessage::ERROR
                    );
                    return false;
                }
                if (!filter_var($image['url'], FILTER_VALIDATE_URL)) {
                    $this->addMessage(
                        sprintf(
                            'Event: %s - Date: %s - Error: %s',
                            $event['title'],
                            $eventBegin->format('d.m.Y'),
                            'Image path has to be a valid URL'
                        ),
                        FlashMessage::ERROR
                    );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get organizer from DB
     *
     * @param string $title
     * @return array|false|null
     */
    protected function getOrganizer($title)
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_events2_domain_model_organizer');
        return $queryBuilder
            ->select('uid')
            ->from('tx_events2_domain_model_organizer')
            ->where(
                $queryBuilder->expr()->eq(
                    'organizer',
                    $queryBuilder->createNamedParameter($title, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetch();
    }

    /**
     * Get location from DB
     *
     * @param $title
     * @return array|null
     */
    protected function getLocation($title)
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_events2_domain_model_location');

        // I don't have the TypoScript or Plugin storage PID. That's why I don't use the repository directly
        return $queryBuilder
            ->select('uid')
            ->from('tx_events2_domain_model_location')
            ->where(
                $queryBuilder->expr()->eq(
                    'location',
                    $queryBuilder->createNamedParameter($title, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetch();
    }

    /**
     * Get category from DB
     *
     * @param string $title
     * @return array|false|null
     */
    protected function getCategory($title)
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_category');
        return $queryBuilder
            ->select('uid')
            ->from('sys_category')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter($title, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetch();
    }

    /**
     * This method is used to add a message to the internal queue
     *
     * @param string $message The message itself
     * @param int $severity Message level (according to \TYPO3\CMS\Core\Messaging\FlashMessage class constants)
     * @return void
     * @throws \Exception
     */
    protected function addMessage($message, $severity = FlashMessage::OK)
    {
        static $firstMessage = true;
        /** @var AbstractFile $logFile */
        static $logFile = null;

        try {
            $content = '';
            if ($firstMessage) {
                // truncate LogFile
                $logFile = $this->getLogFile();
                $logFile->setContents($content);
                $firstMessage = false;
            } else {
                $content = $logFile->getContents();
            }

            $logFile->setContents($content . $message . LF);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $severity = FlashMessage::ERROR;
        }

        // show messages in TYPO3 BE when started manually
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $message, '', $severity);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        /** @var FlashMessageQueue $defaultFlashMessageQueue */
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Get LogFile
     * If it does not exists, we create a new one in same directory of import file
     *
     * @return AbstractFile
     * @throws \Exception
     */
    protected function getLogFile()
    {
        try {
            /** @var Folder $folder */
            $folder = $this->file->getParentFolder();
            if (!$folder->hasFile($this->logFileName)) {
                $logFile = $folder->createFile($this->logFileName);
            } else {
                $logFile = ResourceFactory::getInstance()->retrieveFileOrFolderObject(
                    $folder->getCombinedIdentifier() . $this->logFileName
                );
            }
        } catch (\Exception $e) {
            throw new \Exception('Error while retrieving the LogFile. FAL error: ' . $e->getMessage(), 1525416333);
        }

        return $logFile;
    }

    /**
     * Set property of event object
     *
     * @param Event $event
     * @param string $column
     * @param mixed $value
     *
     * @return void
     */
    protected function setEventProperty(Event $event, $column, $value)
    {
        $setter = 'set' . GeneralUtility::underscoredToUpperCamelCase($column);
        if (method_exists($event, $setter)) {
            $event->{$setter}($value);
        }
    }

    /**
     * Get persistence manager
     *
     * @return PersistenceManagerInterface
     */
    protected function getPersistenceManager()
    {
        if ($this->persistenceManager === null) {
            /** @var ObjectManager $objectManager */
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            /** @var PersistenceManagerInterface $persistenceManager */
            $this->persistenceManager = $objectManager->get(PersistenceManager::class);
        }
        return $this->persistenceManager;
    }

    /**
     * Get TYPO3s Connection Pool
     *
     * @return ConnectionPool
     */
    protected function getConnectionPool()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
