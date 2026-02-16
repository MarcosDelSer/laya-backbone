<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

namespace Gibbon\Module\CareTracking\Domain;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

/**
 * Incident Notification Service
 *
 * Service for sending notifications related to care incidents.
 * Handles parent notifications, director escalations, and queued notifications.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class IncidentNotificationService
{
    /**
     * @var Connection Database connection
     */
    protected $db;

    /**
     * @var NotificationGateway Notification gateway for queue operations
     */
    protected $notificationGateway;

    /**
     * @var IncidentGateway Incident gateway for data access
     */
    protected $incidentGateway;

    /**
     * Notification type constants
     */
    const TYPE_INCIDENT_PARENT = 'incident_parent_notification';
    const TYPE_INCIDENT_DIRECTOR = 'incident_director_escalation';
    const TYPE_INCIDENT_ESCALATION = 'incident_escalation';
    const TYPE_PATTERN_ALERT = 'incident_pattern_alert';

    /**
     * Severity levels requiring immediate director notification
     */
    const IMMEDIATE_ESCALATION_SEVERITIES = ['Critical', 'High'];

    /**
     * Constructor.
     *
     * @param Connection $db Database connection
     * @param NotificationGateway $notificationGateway Notification gateway
     * @param IncidentGateway $incidentGateway Incident gateway
     */
    public function __construct(Connection $db, NotificationGateway $notificationGateway, IncidentGateway $incidentGateway)
    {
        $this->db = $db;
        $this->notificationGateway = $notificationGateway;
        $this->incidentGateway = $incidentGateway;
    }

    /**
     * Notify parent(s) of an incident.
     *
     * Sends notifications to all parents/guardians of the child involved in the incident.
     * Uses both email and push channels for immediate delivery.
     *
     * @param int $gibbonCareIncidentID The incident ID
     * @param string $channel Notification channel (email, push, both)
     * @return array Array with 'success' boolean and 'notified' count or 'error' message
     */
    public function notifyParent($gibbonCareIncidentID, $channel = 'both')
    {
        // Get the incident details
        $incident = $this->incidentGateway->getByID($gibbonCareIncidentID);
        if (!$incident) {
            return [
                'success' => false,
                'error' => 'Incident not found',
            ];
        }

        // Get the child's details
        $child = $this->getChildDetails($incident['gibbonPersonID']);
        if (!$child) {
            return [
                'success' => false,
                'error' => 'Child not found',
            ];
        }

        // Get parent/guardian IDs for this child
        $parentIDs = $this->getParentIDsForChild($incident['gibbonPersonID']);
        if (empty($parentIDs)) {
            return [
                'success' => false,
                'error' => 'No parents/guardians found for child',
            ];
        }

        // Build notification content
        $title = $this->buildParentNotificationTitle($incident, $child);
        $body = $this->buildParentNotificationBody($incident, $child);

        // Prepare payload data
        $payloadData = [
            'incidentID' => $gibbonCareIncidentID,
            'childID' => $incident['gibbonPersonID'],
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'incidentType' => $incident['type'],
            'severity' => $incident['severity'],
            'date' => $incident['date'],
            'time' => $incident['time'],
        ];

        // Queue notifications for all parents
        $notifiedCount = $this->notificationGateway->queueBulkNotification(
            $parentIDs,
            self::TYPE_INCIDENT_PARENT,
            $title,
            $body,
            $payloadData,
            $channel
        );

        // Update incident to mark parent as notified
        if ($notifiedCount > 0) {
            $this->markIncidentParentNotified($gibbonCareIncidentID);
        }

        return [
            'success' => $notifiedCount > 0,
            'notified' => $notifiedCount,
            'parentIDs' => $parentIDs,
        ];
    }

    /**
     * Notify director of an incident requiring escalation.
     *
     * Sends notification to the director when an incident requires
     * immediate attention due to severity or pattern detection.
     *
     * @param int $gibbonCareIncidentID The incident ID
     * @param string $reason Reason for escalation
     * @param int|null $directorID Specific director to notify (null for all directors)
     * @param string $channel Notification channel (email, push, both)
     * @return array Array with 'success' boolean and details
     */
    public function notifyDirector($gibbonCareIncidentID, $reason = 'Severity escalation', $directorID = null, $channel = 'both')
    {
        // Get the incident details
        $incident = $this->incidentGateway->getByID($gibbonCareIncidentID);
        if (!$incident) {
            return [
                'success' => false,
                'error' => 'Incident not found',
            ];
        }

        // Get the child's details
        $child = $this->getChildDetails($incident['gibbonPersonID']);
        if (!$child) {
            return [
                'success' => false,
                'error' => 'Child not found',
            ];
        }

        // Get director(s) to notify
        $directorIDs = $directorID ? [$directorID] : $this->getDirectorIDs();
        if (empty($directorIDs)) {
            return [
                'success' => false,
                'error' => 'No directors found to notify',
            ];
        }

        // Build notification content
        $title = $this->buildDirectorNotificationTitle($incident, $child, $reason);
        $body = $this->buildDirectorNotificationBody($incident, $child, $reason);

        // Prepare payload data
        $payloadData = [
            'incidentID' => $gibbonCareIncidentID,
            'childID' => $incident['gibbonPersonID'],
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'incidentType' => $incident['type'],
            'severity' => $incident['severity'],
            'date' => $incident['date'],
            'time' => $incident['time'],
            'escalationReason' => $reason,
        ];

        // Queue notifications for director(s)
        $notifiedCount = $this->notificationGateway->queueBulkNotification(
            $directorIDs,
            self::TYPE_INCIDENT_DIRECTOR,
            $title,
            $body,
            $payloadData,
            $channel
        );

        // Log the escalation
        if ($notifiedCount > 0) {
            $this->logEscalation($gibbonCareIncidentID, $reason, $directorIDs);
        }

        return [
            'success' => $notifiedCount > 0,
            'notified' => $notifiedCount,
            'directorIDs' => $directorIDs,
        ];
    }

    /**
     * Queue an escalation notification for later processing.
     *
     * Creates a delayed escalation notification that will be sent
     * if the incident is not resolved within a specified timeframe.
     *
     * @param int $gibbonCareIncidentID The incident ID
     * @param string $escalationType Type of escalation (severity, pattern, unacknowledged)
     * @param int $delayMinutes Minutes to delay before sending (0 for immediate)
     * @param array $additionalData Additional data to include in notification
     * @return array Array with 'success' boolean and 'escalationID' or 'error'
     */
    public function queueEscalation($gibbonCareIncidentID, $escalationType = 'severity', $delayMinutes = 0, array $additionalData = [])
    {
        // Get the incident details
        $incident = $this->incidentGateway->getByID($gibbonCareIncidentID);
        if (!$incident) {
            return [
                'success' => false,
                'error' => 'Incident not found',
            ];
        }

        // Check if escalation is already queued
        $existingEscalation = $this->getExistingEscalation($gibbonCareIncidentID, $escalationType);
        if ($existingEscalation) {
            return [
                'success' => true,
                'escalationID' => $existingEscalation['gibbonCareIncidentEscalationID'],
                'message' => 'Escalation already queued',
            ];
        }

        // Get the child's details
        $child = $this->getChildDetails($incident['gibbonPersonID']);

        // Calculate scheduled time
        $scheduledAt = $delayMinutes > 0
            ? date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"))
            : date('Y-m-d H:i:s');

        // Insert escalation record
        $escalationData = [
            'gibbonCareIncidentID' => $gibbonCareIncidentID,
            'escalationType' => $escalationType,
            'status' => 'Pending',
            'scheduledAt' => $scheduledAt,
            'additionalData' => json_encode($additionalData),
            'timestampCreated' => date('Y-m-d H:i:s'),
        ];

        $escalationID = $this->insertEscalation($escalationData);

        if (!$escalationID) {
            return [
                'success' => false,
                'error' => 'Failed to create escalation record',
            ];
        }

        // If immediate escalation, notify directors now
        if ($delayMinutes === 0) {
            $reason = $this->getEscalationReason($escalationType, $incident, $additionalData);
            $this->notifyDirector($gibbonCareIncidentID, $reason);
            $this->markEscalationSent($escalationID);
        }

        return [
            'success' => true,
            'escalationID' => $escalationID,
            'scheduledAt' => $scheduledAt,
        ];
    }

    /**
     * Process pending escalations that are due.
     *
     * Checks for escalations that are scheduled and due for processing,
     * then sends the appropriate notifications.
     *
     * @return array Array with 'processed' count and details
     */
    public function processPendingEscalations()
    {
        $pendingEscalations = $this->getPendingEscalations();
        $processedCount = 0;
        $results = [];

        foreach ($pendingEscalations as $escalation) {
            // Get incident details
            $incident = $this->incidentGateway->getByID($escalation['gibbonCareIncidentID']);
            if (!$incident) {
                $this->markEscalationFailed($escalation['gibbonCareIncidentEscalationID'], 'Incident not found');
                continue;
            }

            // Check if already acknowledged (no need to escalate)
            if ($escalation['escalationType'] === 'unacknowledged' && $incident['parentAcknowledged'] === 'Y') {
                $this->markEscalationCancelled($escalation['gibbonCareIncidentEscalationID'], 'Parent already acknowledged');
                continue;
            }

            // Build escalation reason
            $additionalData = json_decode($escalation['additionalData'], true) ?: [];
            $reason = $this->getEscalationReason($escalation['escalationType'], $incident, $additionalData);

            // Send notification
            $notifyResult = $this->notifyDirector($escalation['gibbonCareIncidentID'], $reason);

            if ($notifyResult['success']) {
                $this->markEscalationSent($escalation['gibbonCareIncidentEscalationID']);
                $processedCount++;
                $results[] = [
                    'escalationID' => $escalation['gibbonCareIncidentEscalationID'],
                    'incidentID' => $escalation['gibbonCareIncidentID'],
                    'status' => 'sent',
                ];
            } else {
                $this->markEscalationFailed($escalation['gibbonCareIncidentEscalationID'], $notifyResult['error'] ?? 'Unknown error');
                $results[] = [
                    'escalationID' => $escalation['gibbonCareIncidentEscalationID'],
                    'incidentID' => $escalation['gibbonCareIncidentID'],
                    'status' => 'failed',
                    'error' => $notifyResult['error'] ?? 'Unknown error',
                ];
            }
        }

        return [
            'processed' => $processedCount,
            'total' => count($pendingEscalations),
            'results' => $results,
        ];
    }

    /**
     * Notify about a detected pattern.
     *
     * Sends notifications to directors about detected incident patterns
     * that may indicate at-risk children.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param string $patternType Type of pattern detected
     * @param array $patternDetails Details about the pattern
     * @return array Array with 'success' boolean and details
     */
    public function notifyPatternDetected($gibbonPersonID, $patternType, array $patternDetails)
    {
        // Get the child's details
        $child = $this->getChildDetails($gibbonPersonID);
        if (!$child) {
            return [
                'success' => false,
                'error' => 'Child not found',
            ];
        }

        // Get directors to notify
        $directorIDs = $this->getDirectorIDs();
        if (empty($directorIDs)) {
            return [
                'success' => false,
                'error' => 'No directors found to notify',
            ];
        }

        // Build notification content
        $title = sprintf(
            'Pattern Alert: %s %s',
            $child['preferredName'],
            $child['surname']
        );

        $body = sprintf(
            'A %s pattern has been detected for %s %s. %s',
            $patternType,
            $child['preferredName'],
            $child['surname'],
            $patternDetails['description'] ?? ''
        );

        // Prepare payload data
        $payloadData = [
            'childID' => $gibbonPersonID,
            'childName' => $child['preferredName'] . ' ' . $child['surname'],
            'patternType' => $patternType,
            'incidentCount' => $patternDetails['incidentCount'] ?? 0,
            'periodDays' => $patternDetails['periodDays'] ?? 30,
        ];

        // Queue notifications for directors
        $notifiedCount = $this->notificationGateway->queueBulkNotification(
            $directorIDs,
            self::TYPE_PATTERN_ALERT,
            $title,
            $body,
            $payloadData,
            'both'
        );

        return [
            'success' => $notifiedCount > 0,
            'notified' => $notifiedCount,
            'directorIDs' => $directorIDs,
        ];
    }

    /**
     * Check if an incident requires immediate escalation based on severity.
     *
     * @param array $incident Incident data array
     * @return bool
     */
    public function requiresImmediateEscalation(array $incident)
    {
        return in_array($incident['severity'] ?? '', self::IMMEDIATE_ESCALATION_SEVERITIES, true);
    }

    // =========================================================================
    // PROTECTED HELPER METHODS
    // =========================================================================

    /**
     * Get child details by person ID.
     *
     * @param int $gibbonPersonID
     * @return array|false
     */
    protected function getChildDetails($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonPersonID, preferredName, surname, image_240, dob
                FROM gibbonPerson
                WHERE gibbonPersonID = :gibbonPersonID";

        return $this->db->selectOne($sql, $data);
    }

    /**
     * Get parent/guardian IDs for a child.
     *
     * @param int $childPersonID
     * @return array Array of gibbonPersonID values
     */
    protected function getParentIDsForChild($childPersonID)
    {
        $data = ['gibbonPersonID' => $childPersonID];
        $sql = "SELECT DISTINCT adult.gibbonPersonID
                FROM gibbonFamilyAdult AS adult
                INNER JOIN gibbonFamilyChild AS child ON child.gibbonFamilyID = adult.gibbonFamilyID
                INNER JOIN gibbonPerson AS person ON person.gibbonPersonID = adult.gibbonPersonID
                WHERE child.gibbonPersonID = :gibbonPersonID
                AND adult.contactPriority <= 2
                AND person.status = 'Full'
                ORDER BY adult.contactPriority";

        $results = $this->db->select($sql, $data)->fetchAll();

        return array_column($results, 'gibbonPersonID');
    }

    /**
     * Get director IDs for the school.
     *
     * @return array Array of gibbonPersonID values
     */
    protected function getDirectorIDs()
    {
        $sql = "SELECT DISTINCT p.gibbonPersonID
                FROM gibbonPerson p
                INNER JOIN gibbonStaff s ON s.gibbonPersonID = p.gibbonPersonID
                INNER JOIN gibbonRole r ON FIND_IN_SET(r.gibbonRoleID, p.gibbonRoleIDAll)
                WHERE p.status = 'Full'
                AND (
                    s.type = 'Support' AND s.jobTitle LIKE '%Director%'
                    OR r.name = 'Administrator'
                    OR r.category = 'Administrator'
                )
                LIMIT 10";

        $results = $this->db->select($sql)->fetchAll();

        return array_column($results, 'gibbonPersonID');
    }

    /**
     * Build the parent notification title.
     *
     * @param array $incident
     * @param array $child
     * @return string
     */
    protected function buildParentNotificationTitle(array $incident, array $child)
    {
        $severityPrefix = in_array($incident['severity'], ['Critical', 'High'], true)
            ? '[URGENT] '
            : '';

        return sprintf(
            '%sIncident Report: %s %s',
            $severityPrefix,
            $child['preferredName'],
            $child['surname']
        );
    }

    /**
     * Build the parent notification body.
     *
     * @param array $incident
     * @param array $child
     * @return string
     */
    protected function buildParentNotificationBody(array $incident, array $child)
    {
        $formattedDate = date('F j, Y', strtotime($incident['date']));
        $formattedTime = date('g:i A', strtotime($incident['time']));

        $body = sprintf(
            "An incident involving %s has been reported.\n\n" .
            "Date: %s\n" .
            "Time: %s\n" .
            "Type: %s\n" .
            "Severity: %s\n\n" .
            "Description:\n%s\n\n" .
            "Action Taken:\n%s\n\n" .
            "Please log in to the parent portal to acknowledge this report.",
            $child['preferredName'],
            $formattedDate,
            $formattedTime,
            $incident['type'],
            $incident['severity'],
            $incident['description'] ?? 'No description provided.',
            $incident['actionTaken'] ?? 'No action details provided.'
        );

        return $body;
    }

    /**
     * Build the director notification title.
     *
     * @param array $incident
     * @param array $child
     * @param string $reason
     * @return string
     */
    protected function buildDirectorNotificationTitle(array $incident, array $child, $reason)
    {
        return sprintf(
            '[ESCALATION] %s - %s %s',
            $incident['severity'],
            $child['preferredName'],
            $child['surname']
        );
    }

    /**
     * Build the director notification body.
     *
     * @param array $incident
     * @param array $child
     * @param string $reason
     * @return string
     */
    protected function buildDirectorNotificationBody(array $incident, array $child, $reason)
    {
        $formattedDate = date('F j, Y', strtotime($incident['date']));
        $formattedTime = date('g:i A', strtotime($incident['time']));

        $body = sprintf(
            "An incident has been escalated requiring your attention.\n\n" .
            "ESCALATION REASON: %s\n\n" .
            "Child: %s %s\n" .
            "Date: %s\n" .
            "Time: %s\n" .
            "Type: %s\n" .
            "Severity: %s\n\n" .
            "Description:\n%s\n\n" .
            "Action Taken:\n%s\n\n" .
            "Parent Notified: %s\n" .
            "Parent Acknowledged: %s\n\n" .
            "Please review this incident in the Care Tracking module.",
            $reason,
            $child['preferredName'],
            $child['surname'],
            $formattedDate,
            $formattedTime,
            $incident['type'],
            $incident['severity'],
            $incident['description'] ?? 'No description provided.',
            $incident['actionTaken'] ?? 'No action details provided.',
            $incident['parentNotified'] === 'Y' ? 'Yes' : 'No',
            $incident['parentAcknowledged'] === 'Y' ? 'Yes' : 'No'
        );

        return $body;
    }

    /**
     * Mark an incident as parent notified.
     *
     * @param int $gibbonCareIncidentID
     * @return bool
     */
    protected function markIncidentParentNotified($gibbonCareIncidentID)
    {
        $data = [
            'gibbonCareIncidentID' => $gibbonCareIncidentID,
            'parentNotifiedTime' => date('Y-m-d H:i:s'),
        ];

        $sql = "UPDATE gibbonCareIncident
                SET parentNotified = 'Y',
                    parentNotifiedTime = :parentNotifiedTime
                WHERE gibbonCareIncidentID = :gibbonCareIncidentID";

        return $this->db->statement($sql, $data);
    }

    /**
     * Log an escalation event.
     *
     * @param int $gibbonCareIncidentID
     * @param string $reason
     * @param array $notifiedIDs
     * @return void
     */
    protected function logEscalation($gibbonCareIncidentID, $reason, array $notifiedIDs)
    {
        $data = [
            'gibbonCareIncidentID' => $gibbonCareIncidentID,
            'escalationType' => 'director_notification',
            'status' => 'Sent',
            'scheduledAt' => date('Y-m-d H:i:s'),
            'sentAt' => date('Y-m-d H:i:s'),
            'additionalData' => json_encode([
                'reason' => $reason,
                'notifiedIDs' => $notifiedIDs,
            ]),
            'timestampCreated' => date('Y-m-d H:i:s'),
        ];

        $sql = "INSERT INTO gibbonCareIncidentEscalation
                (gibbonCareIncidentID, escalationType, status, scheduledAt, sentAt, additionalData, timestampCreated)
                VALUES (:gibbonCareIncidentID, :escalationType, :status, :scheduledAt, :sentAt, :additionalData, :timestampCreated)";

        $this->db->statement($sql, $data);
    }

    /**
     * Get an existing pending escalation.
     *
     * @param int $gibbonCareIncidentID
     * @param string $escalationType
     * @return array|false
     */
    protected function getExistingEscalation($gibbonCareIncidentID, $escalationType)
    {
        $data = [
            'gibbonCareIncidentID' => $gibbonCareIncidentID,
            'escalationType' => $escalationType,
        ];

        $sql = "SELECT * FROM gibbonCareIncidentEscalation
                WHERE gibbonCareIncidentID = :gibbonCareIncidentID
                AND escalationType = :escalationType
                AND status = 'Pending'
                ORDER BY timestampCreated DESC
                LIMIT 1";

        return $this->db->selectOne($sql, $data);
    }

    /**
     * Insert a new escalation record.
     *
     * @param array $data
     * @return int|false
     */
    protected function insertEscalation(array $data)
    {
        $sql = "INSERT INTO gibbonCareIncidentEscalation
                (gibbonCareIncidentID, escalationType, status, scheduledAt, additionalData, timestampCreated)
                VALUES (:gibbonCareIncidentID, :escalationType, :status, :scheduledAt, :additionalData, :timestampCreated)";

        $this->db->statement($sql, $data);
        return $this->db->getConnection()->lastInsertID();
    }

    /**
     * Get pending escalations that are due for processing.
     *
     * @return array
     */
    protected function getPendingEscalations()
    {
        $sql = "SELECT * FROM gibbonCareIncidentEscalation
                WHERE status = 'Pending'
                AND scheduledAt <= NOW()
                ORDER BY scheduledAt ASC
                LIMIT 50";

        return $this->db->select($sql)->fetchAll();
    }

    /**
     * Mark an escalation as sent.
     *
     * @param int $gibbonCareIncidentEscalationID
     * @return bool
     */
    protected function markEscalationSent($gibbonCareIncidentEscalationID)
    {
        $data = [
            'gibbonCareIncidentEscalationID' => $gibbonCareIncidentEscalationID,
            'sentAt' => date('Y-m-d H:i:s'),
        ];

        $sql = "UPDATE gibbonCareIncidentEscalation
                SET status = 'Sent',
                    sentAt = :sentAt
                WHERE gibbonCareIncidentEscalationID = :gibbonCareIncidentEscalationID";

        return $this->db->statement($sql, $data);
    }

    /**
     * Mark an escalation as failed.
     *
     * @param int $gibbonCareIncidentEscalationID
     * @param string $errorMessage
     * @return bool
     */
    protected function markEscalationFailed($gibbonCareIncidentEscalationID, $errorMessage)
    {
        $data = [
            'gibbonCareIncidentEscalationID' => $gibbonCareIncidentEscalationID,
            'additionalData' => json_encode(['error' => $errorMessage]),
        ];

        $sql = "UPDATE gibbonCareIncidentEscalation
                SET status = 'Failed',
                    additionalData = :additionalData
                WHERE gibbonCareIncidentEscalationID = :gibbonCareIncidentEscalationID";

        return $this->db->statement($sql, $data);
    }

    /**
     * Mark an escalation as cancelled.
     *
     * @param int $gibbonCareIncidentEscalationID
     * @param string $reason
     * @return bool
     */
    protected function markEscalationCancelled($gibbonCareIncidentEscalationID, $reason)
    {
        $data = [
            'gibbonCareIncidentEscalationID' => $gibbonCareIncidentEscalationID,
            'additionalData' => json_encode(['cancelReason' => $reason]),
        ];

        $sql = "UPDATE gibbonCareIncidentEscalation
                SET status = 'Cancelled',
                    additionalData = :additionalData
                WHERE gibbonCareIncidentEscalationID = :gibbonCareIncidentEscalationID";

        return $this->db->statement($sql, $data);
    }

    /**
     * Get escalation reason text based on type.
     *
     * @param string $escalationType
     * @param array $incident
     * @param array $additionalData
     * @return string
     */
    protected function getEscalationReason($escalationType, array $incident, array $additionalData = [])
    {
        switch ($escalationType) {
            case 'severity':
                return sprintf('%s severity incident requires immediate attention', $incident['severity']);

            case 'pattern':
                $patternType = $additionalData['patternType'] ?? 'unknown';
                return sprintf('Pattern detected: %s pattern identified for this child', $patternType);

            case 'unacknowledged':
                $hours = $additionalData['hoursElapsed'] ?? 'N/A';
                return sprintf('Parent has not acknowledged incident after %s hours', $hours);

            case 'medical':
                return 'Medical attention may be required';

            case 'regulatory':
                return 'Incident may require regulatory reporting';

            default:
                return $additionalData['reason'] ?? 'Manual escalation';
        }
    }
}
