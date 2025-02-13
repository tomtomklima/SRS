<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\SkautIs\SkautIsCourseRepository;
use App\Model\Structure\SubeventRepository;
use App\Model\User\User;
use Doctrine\Common\Collections\Collection;
use Skautis\Skautis;
use Skautis\Wsdl\WsdlException;
use Tracy\Debugger;
use Tracy\ILogger;
use function array_key_exists;

/**
 * Služba pro správu vzdělávací skautIS akce.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class SkautIsEventEducationService extends SkautIsEventService
{
    /** @var SkautIsCourseRepository */
    private $skautIsCourseRepository;

    /** @var SubeventRepository */
    private $subeventRepository;


    public function __construct(
        Skautis $skautIs,
        SkautIsCourseRepository $skautIsCourseRepository,
        SubeventRepository $subeventRepository
    ) {
        parent::__construct($skautIs);

        $this->skautIsCourseRepository = $skautIsCourseRepository;
        $this->subeventRepository      = $subeventRepository;
    }

    public function isEventDraft(int $eventId) : bool
    {
        return true;
//        return $this->getEventDetail($eventId)->ID_EventEducationState === 'draft';
    }

    /**
     * @param Collection|User[] $users
     */
    public function insertParticipants(int $eventId, Collection $users, bool $accept = false) : bool
    {
        try {
            $participants = [];

            foreach ($this->skautIsCourseRepository->findAll() as $course) {
                $courseId                = $course->getSkautIsCourseId();
                $participants[$courseId] = [];

                foreach ($this->getAllParticipants($eventId, $courseId) as $participant) {
                    $participants[$courseId][$participant->ID_Person] = true;
                }
            }

            foreach ($users as $user) {
                $personId = $user->getSkautISPersonId();

                foreach ($user->getSubevents() as $subevent) {
                    foreach ($subevent->getSkautIsCourses() as $course) {
                        $courseId = $course->getSkautIsCourseId();

                        if (array_key_exists($personId, $participants[$courseId])) {
                            continue;
                        }

                        $this->insertParticipant($eventId, $course->getSkautIsCourseId(), $personId, $accept);
                        $participants[$courseId][$personId] = true;
                    }
                }
            }
        } catch (WsdlException $ex) {
            Debugger::log($ex, ILogger::WARNING);
            return false;
        }

        return true;
    }

    protected function getEventDetail(int $eventId) : \stdClass
    {
        return $this->skautIs->event->EventEducationDetail([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
            'ID' => $eventId,
        ]);
    }

    /**
     * @return \stdClass[]
     */
    protected function getDraftEvents() : array
    {
        $events = $this->skautIs->event->EventEducationAllMyActions([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
        ]);

        if ($events instanceof \stdClass) {
            return [];
        }

        return $events;

        //TODO: vracet jen akce, kam je možné přidávat účastníky
        //        return $this->skautIs->event->EventEducationAllMyActions([
        //            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
        //            'ID_EventEducationState' => 'draft'
        //        ]);
    }

    /**
     * Vrací kurzy vzdělávací akce.
     * @return \stdClass[]
     */
    public function getEventCourses(int $eventId) : array
    {
        $courses = $this->skautIs->event->EventEducationCourseAll([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
            'ID_EventEducation' => $eventId,
        ]);

        if ($courses instanceof \stdClass) {
            return [];
        }

        return $courses;
    }

    /**
     * Je nastaveno propojení alespoň jedné podakce se skautIS kurzem?
     */
    public function isSubeventConnected() : bool
    {
        foreach ($this->subeventRepository->findAll() as $subevent) {
            if (! $subevent->getSkautIsCourses()->isEmpty()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vrací přihlášené účastníky kurzu.
     * @return \stdClass[]
     */
    private function getAllParticipants(int $eventId, int $courseId) : array
    {
        $participants = $this->skautIs->event->ParticipantEducationAll([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
            'ID_EventEducation' => $eventId,
            'ID_EventEducationCourse' => [$courseId, $courseId],
            'IsActive' => true,
        ]);

        if ($participants instanceof \stdClass) {
            return [];
        }

        return $participants;
    }

    /**
     * Přidá účastníka kurzu.
     */
    private function insertParticipant(int $eventId, int $courseId, int $personId, bool $accept) : void
    {
        $response = $this->skautIs->event->ParticipantEducationInsert([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
            'ID_EventEducation' => $eventId,
            'ID_EventEducationCourse' => $courseId,
            'ID_Person' => $personId,
        ]);

        if (! $accept) {
            return;
        }

        $this->skautIs->event->ParticipantEducationUpdate([
            'ID_Login' => $this->skautIs->getUser()->getLoginId(),
            'ID' => $response->ID,
            'IsAccepted' => $accept,
        ], 'participantEducation');
    }
}
