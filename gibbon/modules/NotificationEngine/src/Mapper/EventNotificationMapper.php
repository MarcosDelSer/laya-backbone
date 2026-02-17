<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\NotificationEngine\Mapper;

use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Contracts\Database\Connection;

/**
 * EventNotificationMapper
 *
 * Maps domain events to notifications. Handles event-to-notification routing
 * for attendance check-ins, incidents, daily reports, and messages.
 * Determines recipients, notification types, and channels based on event context.
 *
 * Supported Event Types:
 * - attendance.checkIn: Child checked in -> notify parents
 * - attendance.checkOut: Child checked out -> notify parents
 * - incident.created: Incident reported -> notify parents
 * - incident.updated: Incident updated -> notify parents
 * - dailyReport.ready: Daily report available -> notify parents
 * - message.received: Message received -> notify recipient
 * - photo.uploaded: Photo uploaded -> notify parents
 * - meal.recorded: Meal recorded -> notify parents
 * - nap.recorded: Nap recorded -> notify parents
 * - diaper.recorded: Diaper change recorded -> notify parents
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EventNotificationMapper
{
    /**
     * @var NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var Connection
     */
    protected $pdo;

    /**
     * Constructor.
     *
     * @param NotificationGateway $notificationGateway
     * @param Connection $pdo
     */
    public function __construct(NotificationGateway $notificationGateway, Connection $pdo)
    {
        $this->notificationGateway = $notificationGateway;
        $this->pdo = $pdo;
    }

    /**
     * Map an event to notification(s) and queue them.
     *
     * @param string $eventType Event type (e.g., 'attendance.checkIn')
     * @param array $eventData Event payload data
     * @return array Result with notification IDs and count
     */
    public function mapEvent($eventType, array $eventData)
    {
        // Validate event type
        if (!$this->isValidEventType($eventType)) {
            return [
                'success' => false,
                'error' => 'Invalid event type',
                'eventType' => $eventType,
            ];
        }

        // Route to appropriate handler
        $handler = $this->getEventHandler($eventType);
        if (!$handler || !is_callable([$this, $handler])) {
            return [
                'success' => false,
                'error' => 'No handler found for event type',
                'eventType' => $eventType,
            ];
        }

        // Call the handler
        try {
            $result = $this->$handler($eventData);
            $result['success'] = true;
            $result['eventType'] = $eventType;
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'eventType' => $eventType,
            ];
        }
    }

    /**
     * Check if an event type is valid.
     *
     * @param string $eventType
     * @return bool
     */
    protected function isValidEventType($eventType)
    {
        $validTypes = [
            'attendance.checkIn',
            'attendance.checkOut',
            'incident.created',
            'incident.updated',
            'dailyReport.ready',
            'message.received',
            'photo.uploaded',
            'meal.recorded',
            'nap.recorded',
            'diaper.recorded',
        ];

        return in_array($eventType, $validTypes);
    }

    /**
     * Get the handler method name for an event type.
     *
     * @param string $eventType
     * @return string|null
     */
    protected function getEventHandler($eventType)
    {
        $handlers = [
            'attendance.checkIn' => 'handleAttendanceCheckIn',
            'attendance.checkOut' => 'handleAttendanceCheckOut',
            'incident.created' => 'handleIncidentCreated',
            'incident.updated' => 'handleIncidentUpdated',
            'dailyReport.ready' => 'handleDailyReportReady',
            'message.received' => 'handleMessageReceived',
            'photo.uploaded' => 'handlePhotoUploaded',
            'meal.recorded' => 'handleMealRecorded',
            'nap.recorded' => 'handleNapRecorded',
            'diaper.recorded' => 'handleDiaperRecorded',
        ];

        return $handlers[$eventType] ?? null;
    }

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    /**
     * Handle attendance check-in event.
     *
     * @param array $eventData Must contain: gibbonPersonID, checkInTime, checkedInBy
     * @return array
     */
    protected function handleAttendanceCheckIn(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $checkInTime = $eventData['checkInTime'] ?? date('Y-m-d H:i:s');
        $checkedInBy = $eventData['checkedInBy'] ?? 'Staff';

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        // Get child information
        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        // Get parents
        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        // Queue notifications for all parents
        $notificationType = 'attendance.checkIn';
        $title = sprintf('%s Checked In', $child['preferredName']);
        $body = sprintf(
            '%s %s was checked in at %s by %s.',
            $child['preferredName'],
            $child['surname'],
            date('g:i A', strtotime($checkInTime)),
            $checkedInBy
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'checkInTime' => $checkInTime,
            'checkedInBy' => $checkedInBy,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle attendance check-out event.
     *
     * @param array $eventData Must contain: gibbonPersonID, checkOutTime, checkedOutBy
     * @return array
     */
    protected function handleAttendanceCheckOut(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $checkOutTime = $eventData['checkOutTime'] ?? date('Y-m-d H:i:s');
        $checkedOutBy = $eventData['checkedOutBy'] ?? 'Staff';

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'attendance.checkOut';
        $title = sprintf('%s Checked Out', $child['preferredName']);
        $body = sprintf(
            '%s %s was checked out at %s by %s.',
            $child['preferredName'],
            $child['surname'],
            date('g:i A', strtotime($checkOutTime)),
            $checkedOutBy
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'checkOutTime' => $checkOutTime,
            'checkedOutBy' => $checkedOutBy,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle incident created event.
     *
     * @param array $eventData Must contain: gibbonPersonID, incidentType, description, severity
     * @return array
     */
    protected function handleIncidentCreated(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $incidentType = $eventData['incidentType'] ?? 'General';
        $description = $eventData['description'] ?? '';
        $severity = $eventData['severity'] ?? 'low';
        $incidentID = $eventData['incidentID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'incident.created';
        $title = sprintf('Incident Report: %s', $child['preferredName']);
        $body = sprintf(
            'An incident (%s) was reported for %s %s. %s',
            $incidentType,
            $child['preferredName'],
            $child['surname'],
            $description ? substr($description, 0, 100) : 'Please check the app for details.'
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'incidentType' => $incidentType,
            'description' => $description,
            'severity' => $severity,
            'incidentID' => $incidentID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle incident updated event.
     *
     * @param array $eventData Must contain: gibbonPersonID, incidentID, updateType
     * @return array
     */
    protected function handleIncidentUpdated(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $incidentID = $eventData['incidentID'] ?? null;
        $updateType = $eventData['updateType'] ?? 'updated';

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'incident.updated';
        $title = sprintf('Incident Update: %s', $child['preferredName']);
        $body = sprintf(
            'An incident report for %s %s has been %s.',
            $child['preferredName'],
            $child['surname'],
            $updateType
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'incidentID' => $incidentID,
            'updateType' => $updateType,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle daily report ready event.
     *
     * @param array $eventData Must contain: gibbonPersonID, date, reportID
     * @return array
     */
    protected function handleDailyReportReady(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $date = $eventData['date'] ?? date('Y-m-d');
        $reportID = $eventData['reportID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'dailyReport.ready';
        $title = sprintf('Daily Report Ready: %s', $child['preferredName']);
        $body = sprintf(
            'The daily report for %s %s is now available for %s.',
            $child['preferredName'],
            $child['surname'],
            date('F j, Y', strtotime($date))
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'date' => $date,
            'reportID' => $reportID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle message received event.
     *
     * @param array $eventData Must contain: recipientID, senderID, messageText
     * @return array
     */
    protected function handleMessageReceived(array $eventData)
    {
        $recipientID = $eventData['recipientID'] ?? null;
        $senderID = $eventData['senderID'] ?? null;
        $messageText = $eventData['messageText'] ?? '';
        $messageID = $eventData['messageID'] ?? null;

        if (!$recipientID) {
            throw new \InvalidArgumentException('recipientID is required');
        }

        $sender = $senderID ? $this->getPersonInfo($senderID) : null;
        $senderName = $sender ? ($sender['preferredName'] . ' ' . $sender['surname']) : 'Staff';

        $notificationType = 'message.received';
        $title = sprintf('New Message from %s', $senderName);
        $body = $messageText ? substr($messageText, 0, 100) : 'You have a new message.';
        $data = [
            'senderID' => $senderID,
            'senderName' => $senderName,
            'messageText' => $messageText,
            'messageID' => $messageID,
        ];

        // Queue notification for the recipient
        $notificationID = $this->notificationGateway->insertNotification([
            'gibbonPersonID' => $recipientID,
            'type' => $notificationType,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channel' => 'both',
        ]);

        return [
            'notificationIDs' => $notificationID ? [$notificationID] : [],
            'count' => $notificationID ? 1 : 0,
        ];
    }

    /**
     * Handle photo uploaded event.
     *
     * @param array $eventData Must contain: gibbonPersonID, photoURL, caption
     * @return array
     */
    protected function handlePhotoUploaded(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $photoURL = $eventData['photoURL'] ?? '';
        $caption = $eventData['caption'] ?? '';
        $photoID = $eventData['photoID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'photo.uploaded';
        $title = sprintf('New Photo: %s', $child['preferredName']);
        $body = sprintf(
            'A new photo of %s %s has been uploaded. %s',
            $child['preferredName'],
            $child['surname'],
            $caption ?: ''
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'photoURL' => $photoURL,
            'caption' => $caption,
            'photoID' => $photoID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle meal recorded event.
     *
     * @param array $eventData Must contain: gibbonPersonID, mealType, items
     * @return array
     */
    protected function handleMealRecorded(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $mealType = $eventData['mealType'] ?? 'Meal';
        $items = $eventData['items'] ?? '';
        $mealID = $eventData['mealID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'meal.recorded';
        $title = sprintf('%s Recorded: %s', $mealType, $child['preferredName']);
        $body = sprintf(
            '%s for %s %s has been recorded. %s',
            $mealType,
            $child['preferredName'],
            $child['surname'],
            $items ?: ''
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'mealType' => $mealType,
            'items' => $items,
            'mealID' => $mealID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle nap recorded event.
     *
     * @param array $eventData Must contain: gibbonPersonID, startTime, endTime
     * @return array
     */
    protected function handleNapRecorded(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $startTime = $eventData['startTime'] ?? '';
        $endTime = $eventData['endTime'] ?? '';
        $napID = $eventData['napID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'nap.recorded';
        $title = sprintf('Nap Recorded: %s', $child['preferredName']);
        $body = sprintf(
            '%s %s had a nap from %s to %s.',
            $child['preferredName'],
            $child['surname'],
            $startTime ? date('g:i A', strtotime($startTime)) : '',
            $endTime ? date('g:i A', strtotime($endTime)) : 'ongoing'
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'startTime' => $startTime,
            'endTime' => $endTime,
            'napID' => $napID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    /**
     * Handle diaper recorded event.
     *
     * @param array $eventData Must contain: gibbonPersonID, changeType, notes
     * @return array
     */
    protected function handleDiaperRecorded(array $eventData)
    {
        $gibbonPersonID = $eventData['gibbonPersonID'] ?? null;
        $changeType = $eventData['changeType'] ?? 'Diaper';
        $notes = $eventData['notes'] ?? '';
        $diaperID = $eventData['diaperID'] ?? null;

        if (!$gibbonPersonID) {
            throw new \InvalidArgumentException('gibbonPersonID is required');
        }

        $child = $this->getPersonInfo($gibbonPersonID);
        if (!$child) {
            throw new \InvalidArgumentException('Child not found');
        }

        $parents = $this->getParentsByChild($gibbonPersonID);
        if (empty($parents)) {
            return ['notificationIDs' => [], 'count' => 0];
        }

        $notificationType = 'diaper.recorded';
        $title = sprintf('Diaper Change: %s', $child['preferredName']);
        $body = sprintf(
            'A diaper change (%s) has been recorded for %s %s.',
            $changeType,
            $child['preferredName'],
            $child['surname']
        );
        $data = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'changeType' => $changeType,
            'notes' => $notes,
            'diaperID' => $diaperID,
        ];

        return $this->queueNotificationsForRecipients(
            $parents,
            $notificationType,
            $title,
            $body,
            $data
        );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Queue notifications for multiple recipients.
     *
     * @param array $recipients Array of person records
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional payload data
     * @param string $channel Channel (email, push, both)
     * @return array Result with notification IDs and count
     */
    protected function queueNotificationsForRecipients(
        array $recipients,
        $type,
        $title,
        $body,
        array $data = [],
        $channel = 'both'
    ) {
        $notificationIDs = [];

        foreach ($recipients as $recipient) {
            $recipientID = $recipient['gibbonPersonID'] ?? null;
            if (!$recipientID) {
                continue;
            }

            $notificationID = $this->notificationGateway->insertNotification([
                'gibbonPersonID' => $recipientID,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'channel' => $channel,
            ]);

            if ($notificationID) {
                $notificationIDs[] = $notificationID;
            }
        }

        return [
            'notificationIDs' => $notificationIDs,
            'count' => count($notificationIDs),
        ];
    }

    /**
     * Get person information by ID.
     *
     * @param int $gibbonPersonID
     * @return array|null
     */
    protected function getPersonInfo($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonPersonID, preferredName, surname, email
                FROM gibbonPerson
                WHERE gibbonPersonID = :gibbonPersonID";

        return $this->pdo->selectOne($sql, $data) ?: null;
    }

    /**
     * Get parents of a child.
     *
     * @param int $gibbonPersonID Child's person ID
     * @return array Array of parent records
     */
    protected function getParentsByChild($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT p.gibbonPersonID, p.preferredName, p.surname, p.email
                FROM gibbonPerson AS p
                INNER JOIN gibbonFamilyAdult AS fa ON fa.gibbonPersonID = p.gibbonPersonID
                INNER JOIN gibbonFamily AS f ON f.gibbonFamilyID = fa.gibbonFamilyID
                INNER JOIN gibbonFamilyChild AS fc ON fc.gibbonFamilyID = f.gibbonFamilyID
                WHERE fc.gibbonPersonID = :gibbonPersonID
                AND p.status = 'Full'
                AND fa.childDataAccess = 'Y'";

        return $this->pdo->select($sql, $data)->fetchAll() ?: [];
    }

    /**
     * Get notification type display name.
     *
     * @param string $type Notification type
     * @return string
     */
    public function getNotificationTypeDisplay($type)
    {
        $displays = [
            'attendance.checkIn' => 'Attendance Check-In',
            'attendance.checkOut' => 'Attendance Check-Out',
            'incident.created' => 'Incident Created',
            'incident.updated' => 'Incident Updated',
            'dailyReport.ready' => 'Daily Report Ready',
            'message.received' => 'Message Received',
            'photo.uploaded' => 'Photo Uploaded',
            'meal.recorded' => 'Meal Recorded',
            'nap.recorded' => 'Nap Recorded',
            'diaper.recorded' => 'Diaper Change Recorded',
        ];

        return $displays[$type] ?? ucwords(str_replace(['.', '_'], ' ', $type));
    }

    /**
     * Get all supported event types.
     *
     * @return array
     */
    public function getSupportedEventTypes()
    {
        return [
            'attendance.checkIn' => [
                'name' => 'Attendance Check-In',
                'description' => 'Child checked into facility',
                'recipients' => 'Parents',
            ],
            'attendance.checkOut' => [
                'name' => 'Attendance Check-Out',
                'description' => 'Child checked out of facility',
                'recipients' => 'Parents',
            ],
            'incident.created' => [
                'name' => 'Incident Created',
                'description' => 'New incident reported',
                'recipients' => 'Parents',
            ],
            'incident.updated' => [
                'name' => 'Incident Updated',
                'description' => 'Incident report updated',
                'recipients' => 'Parents',
            ],
            'dailyReport.ready' => [
                'name' => 'Daily Report Ready',
                'description' => 'Daily activity report available',
                'recipients' => 'Parents',
            ],
            'message.received' => [
                'name' => 'Message Received',
                'description' => 'New message received',
                'recipients' => 'Recipient',
            ],
            'photo.uploaded' => [
                'name' => 'Photo Uploaded',
                'description' => 'New photo of child uploaded',
                'recipients' => 'Parents',
            ],
            'meal.recorded' => [
                'name' => 'Meal Recorded',
                'description' => 'Meal or snack recorded',
                'recipients' => 'Parents',
            ],
            'nap.recorded' => [
                'name' => 'Nap Recorded',
                'description' => 'Nap time recorded',
                'recipients' => 'Parents',
            ],
            'diaper.recorded' => [
                'name' => 'Diaper Change Recorded',
                'description' => 'Diaper change recorded',
                'recipients' => 'Parents',
            ],
        ];
    }
}
