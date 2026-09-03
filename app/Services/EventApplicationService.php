<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Entities\Event;
use App\Model\Repositories\EventRepository;
use App\Model\Repositories\IEventAudienceRepository;
use Nette\Database\Connection;
use Throwable;

final class EventApplicationService
{
    public function __construct(
        private readonly Connection $db,
        private readonly EventRepository $eventRepository,
        private readonly IEventAudienceRepository $eventAudienceRepository,
    ) {
    }

    public function saveEvent(Event $event): Event
    {
        $this->db->query('START TRANSACTION');

        try {
            $stored = $this->eventRepository->save($event);
            $this->eventRepository->syncTagAssignments($stored);
            $this->eventAudienceRepository->replaceRolesForEvent($stored->id, $stored->getAudienceRoles());
            $this->db->query('COMMIT');

            return $stored;
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    public function setStatus(int $eventId, string $status): ?Event
    {
        $event = $this->eventRepository->findById($eventId);
        if ($event === null) {
            return null;
        }

        $event->status = $status;

        return $this->saveEvent($event);
    }

    public function deleteEvent(int $eventId): void
    {
        $this->db->query('START TRANSACTION');

        try {
            $this->eventAudienceRepository->deleteForEvent($eventId);
            $this->eventRepository->delete($eventId);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }
}
