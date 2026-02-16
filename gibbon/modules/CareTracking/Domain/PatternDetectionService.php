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

/**
 * Pattern Detection Service
 *
 * Service for detecting incident patterns in children's care records.
 * Identifies at-risk children based on frequency, severity, and type patterns.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PatternDetectionService
{
    /**
     * @var Connection Database connection
     */
    protected $db;

    /**
     * @var IncidentGateway Incident gateway for data access
     */
    protected $incidentGateway;

    /**
     * @var int Default threshold for pattern detection
     */
    protected $defaultThreshold = 3;

    /**
     * @var int Default period in days for pattern detection
     */
    protected $defaultPeriodDays = 30;

    /**
     * Pattern type constants
     */
    const PATTERN_FREQUENCY = 'Frequency';
    const PATTERN_SEVERITY = 'Severity';
    const PATTERN_CATEGORY = 'Category';
    const PATTERN_LOCATION = 'Location';
    const PATTERN_TIME = 'Time';
    const PATTERN_BEHAVIORAL = 'Behavioral';

    /**
     * Constructor.
     *
     * @param Connection $db Database connection
     * @param IncidentGateway $incidentGateway Incident gateway
     */
    public function __construct(Connection $db, IncidentGateway $incidentGateway)
    {
        $this->db = $db;
        $this->incidentGateway = $incidentGateway;
    }

    /**
     * Run pattern detection for all children in a school year.
     *
     * Analyzes incident data to identify patterns that may indicate
     * at-risk children requiring intervention or review.
     *
     * @param int $gibbonSchoolYearID School year to analyze
     * @param int|null $threshold Minimum incidents to trigger pattern (default from settings)
     * @param int|null $periodDays Days to look back (default from settings)
     * @return array Array of detected patterns with details
     */
    public function runPatternDetection($gibbonSchoolYearID, $threshold = null, $periodDays = null)
    {
        $threshold = $threshold ?? $this->getSettingValue('patternDetectionThreshold', $this->defaultThreshold);
        $periodDays = $periodDays ?? $this->getSettingValue('patternDetectionPeriodDays', $this->defaultPeriodDays);

        $dateEnd = date('Y-m-d');
        $dateStart = date('Y-m-d', strtotime("-{$periodDays} days"));

        $detectedPatterns = [];

        // Detect frequency patterns (children with many incidents)
        $frequencyPatterns = $this->detectFrequencyPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $threshold);
        $detectedPatterns = array_merge($detectedPatterns, $frequencyPatterns);

        // Detect severity patterns (children with repeated severe incidents)
        $severityPatterns = $this->detectSeverityPatterns($gibbonSchoolYearID, $dateStart, $dateEnd);
        $detectedPatterns = array_merge($detectedPatterns, $severityPatterns);

        // Detect category patterns (same type of incident repeatedly)
        $categoryPatterns = $this->detectCategoryPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $threshold);
        $detectedPatterns = array_merge($detectedPatterns, $categoryPatterns);

        // Detect behavioral patterns (repeated behavioral incidents)
        $behavioralPatterns = $this->detectBehavioralPatterns($gibbonSchoolYearID, $dateStart, $dateEnd);
        $detectedPatterns = array_merge($detectedPatterns, $behavioralPatterns);

        return $detectedPatterns;
    }

    /**
     * Identify children who may be at risk based on incident patterns.
     *
     * Returns children who have concerning patterns that warrant review
     * by staff or director.
     *
     * @param int $gibbonSchoolYearID School year to analyze
     * @param int|null $totalThreshold Total incident threshold (default 5)
     * @param int|null $severeThreshold Severe incident threshold (default 2)
     * @return array Array of at-risk children with pattern details
     */
    public function identifyAtRiskChildren($gibbonSchoolYearID, $totalThreshold = 5, $severeThreshold = 2)
    {
        $periodDays = $this->getSettingValue('patternDetectionPeriodDays', $this->defaultPeriodDays);
        $dateEnd = date('Y-m-d');
        $dateStart = date('Y-m-d', strtotime("-{$periodDays} days"));

        // Use the gateway method to get children needing review
        $result = $this->incidentGateway->selectChildrenNeedingReview(
            $gibbonSchoolYearID,
            $dateStart,
            $dateEnd,
            $totalThreshold,
            $severeThreshold
        );

        $atRiskChildren = [];

        foreach ($result as $child) {
            $riskFactors = [];

            // Determine risk factors
            if ((int) $child['totalIncidents'] >= $totalThreshold) {
                $riskFactors[] = 'High incident frequency (' . $child['totalIncidents'] . ' incidents)';
            }

            if ((int) $child['severeCount'] >= $severeThreshold) {
                $riskFactors[] = 'Multiple severe incidents (' . $child['severeCount'] . ' severe)';
            }

            if ((int) $child['criticalCount'] > 0) {
                $riskFactors[] = 'Critical incident(s) recorded (' . $child['criticalCount'] . ' critical)';
            }

            if ((int) $child['behavioralCount'] >= 3) {
                $riskFactors[] = 'Recurring behavioral issues (' . $child['behavioralCount'] . ' behavioral)';
            }

            $atRiskChildren[] = [
                'gibbonPersonID' => $child['gibbonPersonID'],
                'preferredName' => $child['preferredName'],
                'surname' => $child['surname'],
                'image_240' => $child['image_240'] ?? null,
                'dob' => $child['dob'] ?? null,
                'totalIncidents' => (int) $child['totalIncidents'],
                'severeCount' => (int) $child['severeCount'],
                'criticalCount' => (int) $child['criticalCount'],
                'highCount' => (int) $child['highCount'],
                'firstIncidentDate' => $child['firstIncidentDate'],
                'lastIncidentDate' => $child['lastIncidentDate'],
                'riskFactors' => $riskFactors,
                'riskLevel' => $this->calculateRiskLevel($child),
            ];
        }

        // Sort by risk level (highest first)
        usort($atRiskChildren, function ($a, $b) {
            return $b['riskLevel'] - $a['riskLevel'];
        });

        return $atRiskChildren;
    }

    /**
     * Create a pattern alert record in the database.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonSchoolYearID School year ID
     * @param string $patternType Type of pattern detected
     * @param int $incidentCount Number of incidents in pattern
     * @param int $periodDays Period the pattern spans
     * @param array $incidentIDs Array of incident IDs involved
     * @param string|null $description Description of the pattern
     * @return int|false The new pattern alert ID or false on failure
     */
    public function createPatternAlert($gibbonPersonID, $gibbonSchoolYearID, $patternType, $incidentCount, $periodDays, array $incidentIDs, $description = null)
    {
        // Check if a similar pending pattern already exists
        $existingPattern = $this->getExistingPendingPattern($gibbonPersonID, $patternType, $gibbonSchoolYearID);
        if ($existingPattern) {
            // Update existing pattern instead of creating duplicate
            return $this->updatePatternAlert($existingPattern['gibbonCareIncidentPatternID'], $incidentCount, $incidentIDs, $description);
        }

        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'patternType' => $patternType,
            'detectedAt' => date('Y-m-d H:i:s'),
            'incidentCount' => $incidentCount,
            'periodDays' => $periodDays,
            'incidentIDs' => json_encode($incidentIDs),
            'patternDescription' => $description,
            'reviewStatus' => 'Pending',
        ];

        $sql = "INSERT INTO gibbonCareIncidentPattern
                (gibbonPersonID, gibbonSchoolYearID, patternType, detectedAt, incidentCount,
                 periodDays, incidentIDs, patternDescription, reviewStatus)
                VALUES (:gibbonPersonID, :gibbonSchoolYearID, :patternType, :detectedAt, :incidentCount,
                        :periodDays, :incidentIDs, :patternDescription, :reviewStatus)";

        $this->db->statement($sql, $data);
        return $this->db->getConnection()->lastInsertID();
    }

    /**
     * Get pending pattern alerts for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getPendingPatternAlerts($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT p.*,
                       person.preferredName,
                       person.surname,
                       person.image_240,
                       person.dob
                FROM gibbonCareIncidentPattern p
                INNER JOIN gibbonPerson AS person ON person.gibbonPersonID = p.gibbonPersonID
                WHERE p.gibbonSchoolYearID = :gibbonSchoolYearID
                AND p.reviewStatus = 'Pending'
                ORDER BY p.detectedAt DESC";

        return $this->db->select($sql, $data)->fetchAll();
    }

    /**
     * Get pattern alerts for a specific child.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getPatternAlertsByPerson($gibbonPersonID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];
        $sql = "SELECT p.*,
                       reviewer.preferredName AS reviewerPreferredName,
                       reviewer.surname AS reviewerSurname
                FROM gibbonCareIncidentPattern p
                LEFT JOIN gibbonPerson AS reviewer ON reviewer.gibbonPersonID = p.reviewedByID
                WHERE p.gibbonPersonID = :gibbonPersonID
                AND p.gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY p.detectedAt DESC";

        return $this->db->select($sql, $data)->fetchAll();
    }

    /**
     * Mark a pattern alert as reviewed.
     *
     * @param int $gibbonCareIncidentPatternID
     * @param string $status New status (Reviewed, Dismissed, ActionTaken)
     * @param int $reviewedByID Staff who reviewed
     * @param string|null $notes Review notes
     * @return bool
     */
    public function markPatternReviewed($gibbonCareIncidentPatternID, $status, $reviewedByID, $notes = null)
    {
        $validStatuses = ['Reviewed', 'Dismissed', 'ActionTaken'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $data = [
            'gibbonCareIncidentPatternID' => $gibbonCareIncidentPatternID,
            'reviewStatus' => $status,
            'reviewedByID' => $reviewedByID,
            'reviewedAt' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ];

        $sql = "UPDATE gibbonCareIncidentPattern
                SET reviewStatus = :reviewStatus,
                    reviewedByID = :reviewedByID,
                    reviewedAt = :reviewedAt,
                    notes = :notes
                WHERE gibbonCareIncidentPatternID = :gibbonCareIncidentPatternID";

        return $this->db->statement($sql, $data);
    }

    /**
     * Get pattern statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getPatternStatistics($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalPatterns,
                    SUM(CASE WHEN reviewStatus = 'Pending' THEN 1 ELSE 0 END) as pendingPatterns,
                    SUM(CASE WHEN reviewStatus = 'Reviewed' THEN 1 ELSE 0 END) as reviewedPatterns,
                    SUM(CASE WHEN reviewStatus = 'Dismissed' THEN 1 ELSE 0 END) as dismissedPatterns,
                    SUM(CASE WHEN reviewStatus = 'ActionTaken' THEN 1 ELSE 0 END) as actionTakenPatterns,
                    COUNT(DISTINCT gibbonPersonID) as childrenWithPatterns,
                    SUM(CASE WHEN patternType = 'Frequency' THEN 1 ELSE 0 END) as frequencyPatterns,
                    SUM(CASE WHEN patternType = 'Severity' THEN 1 ELSE 0 END) as severityPatterns,
                    SUM(CASE WHEN patternType = 'Category' THEN 1 ELSE 0 END) as categoryPatterns,
                    SUM(CASE WHEN patternType = 'Behavioral' THEN 1 ELSE 0 END) as behavioralPatterns
                FROM gibbonCareIncidentPattern
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID";

        return $this->db->selectOne($sql, $data) ?: [
            'totalPatterns' => 0,
            'pendingPatterns' => 0,
            'reviewedPatterns' => 0,
            'dismissedPatterns' => 0,
            'actionTakenPatterns' => 0,
            'childrenWithPatterns' => 0,
            'frequencyPatterns' => 0,
            'severityPatterns' => 0,
            'categoryPatterns' => 0,
            'behavioralPatterns' => 0,
        ];
    }

    // =========================================================================
    // PROTECTED HELPER METHODS
    // =========================================================================

    /**
     * Detect frequency patterns (high number of total incidents).
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $threshold
     * @return array
     */
    protected function detectFrequencyPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $threshold)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'threshold' => $threshold,
        ];

        $sql = "SELECT
                    gibbonPersonID,
                    COUNT(*) as incidentCount,
                    GROUP_CONCAT(gibbonCareIncidentID) as incidentIDs,
                    MIN(date) as firstDate,
                    MAX(date) as lastDate
                FROM gibbonCareIncident
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND date >= :dateStart
                AND date <= :dateEnd
                GROUP BY gibbonPersonID
                HAVING COUNT(*) >= :threshold";

        $results = $this->db->select($sql, $data)->fetchAll();
        $patterns = [];

        $periodDays = (int) ((strtotime($dateEnd) - strtotime($dateStart)) / 86400);

        foreach ($results as $row) {
            $incidentIDs = array_map('intval', explode(',', $row['incidentIDs']));
            $patterns[] = [
                'gibbonPersonID' => $row['gibbonPersonID'],
                'patternType' => self::PATTERN_FREQUENCY,
                'incidentCount' => (int) $row['incidentCount'],
                'periodDays' => $periodDays,
                'incidentIDs' => $incidentIDs,
                'description' => sprintf(
                    '%d incidents recorded in %d days (threshold: %d)',
                    $row['incidentCount'],
                    $periodDays,
                    $threshold
                ),
            ];
        }

        return $patterns;
    }

    /**
     * Detect severity patterns (multiple severe incidents).
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    protected function detectSeverityPatterns($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $sql = "SELECT
                    gibbonPersonID,
                    COUNT(*) as severeCount,
                    SUM(CASE WHEN severity = 'Critical' THEN 1 ELSE 0 END) as criticalCount,
                    SUM(CASE WHEN severity = 'High' THEN 1 ELSE 0 END) as highCount,
                    GROUP_CONCAT(gibbonCareIncidentID) as incidentIDs
                FROM gibbonCareIncident
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND date >= :dateStart
                AND date <= :dateEnd
                AND severity IN ('Critical', 'High')
                GROUP BY gibbonPersonID
                HAVING COUNT(*) >= 2";

        $results = $this->db->select($sql, $data)->fetchAll();
        $patterns = [];

        $periodDays = (int) ((strtotime($dateEnd) - strtotime($dateStart)) / 86400);

        foreach ($results as $row) {
            $incidentIDs = array_map('intval', explode(',', $row['incidentIDs']));
            $patterns[] = [
                'gibbonPersonID' => $row['gibbonPersonID'],
                'patternType' => self::PATTERN_SEVERITY,
                'incidentCount' => (int) $row['severeCount'],
                'periodDays' => $periodDays,
                'incidentIDs' => $incidentIDs,
                'description' => sprintf(
                    '%d severe incidents (%d critical, %d high) in %d days',
                    $row['severeCount'],
                    $row['criticalCount'],
                    $row['highCount'],
                    $periodDays
                ),
            ];
        }

        return $patterns;
    }

    /**
     * Detect category patterns (same type of incident repeatedly).
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @param int $threshold
     * @return array
     */
    protected function detectCategoryPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $threshold)
    {
        // Use the gateway's detectPatterns method
        $patterns = $this->incidentGateway->detectPatterns($gibbonSchoolYearID, $dateStart, $dateEnd, $threshold);

        $result = [];
        $periodDays = (int) ((strtotime($dateEnd) - strtotime($dateStart)) / 86400);

        foreach ($patterns as $pattern) {
            $result[] = [
                'gibbonPersonID' => $pattern['gibbonPersonID'],
                'patternType' => self::PATTERN_CATEGORY,
                'incidentCount' => (int) $pattern['incidentCount'],
                'periodDays' => $periodDays,
                'incidentIDs' => [], // Would need additional query to get IDs
                'description' => sprintf(
                    '%d %s incidents in %d days (%d critical, %d high)',
                    $pattern['incidentCount'],
                    $pattern['type'],
                    $periodDays,
                    $pattern['criticalCount'],
                    $pattern['highCount']
                ),
                'incidentType' => $pattern['type'],
            ];
        }

        return $result;
    }

    /**
     * Detect behavioral patterns (recurring behavioral incidents).
     *
     * @param int $gibbonSchoolYearID
     * @param string $dateStart
     * @param string $dateEnd
     * @return array
     */
    protected function detectBehavioralPatterns($gibbonSchoolYearID, $dateStart, $dateEnd)
    {
        $data = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];

        $sql = "SELECT
                    gibbonPersonID,
                    COUNT(*) as behavioralCount,
                    GROUP_CONCAT(gibbonCareIncidentID) as incidentIDs,
                    GROUP_CONCAT(DISTINCT incidentCategory) as categories
                FROM gibbonCareIncident
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                AND date >= :dateStart
                AND date <= :dateEnd
                AND (type = 'Behavioral' OR incidentCategory = 'Behavioral')
                GROUP BY gibbonPersonID
                HAVING COUNT(*) >= 2";

        $results = $this->db->select($sql, $data)->fetchAll();
        $patterns = [];

        $periodDays = (int) ((strtotime($dateEnd) - strtotime($dateStart)) / 86400);

        foreach ($results as $row) {
            $incidentIDs = array_map('intval', explode(',', $row['incidentIDs']));
            $patterns[] = [
                'gibbonPersonID' => $row['gibbonPersonID'],
                'patternType' => self::PATTERN_BEHAVIORAL,
                'incidentCount' => (int) $row['behavioralCount'],
                'periodDays' => $periodDays,
                'incidentIDs' => $incidentIDs,
                'description' => sprintf(
                    '%d behavioral incidents in %d days',
                    $row['behavioralCount'],
                    $periodDays
                ),
            ];
        }

        return $patterns;
    }

    /**
     * Calculate risk level for a child based on incident data.
     *
     * @param array $childData Child incident data
     * @return int Risk level (0-10)
     */
    protected function calculateRiskLevel($childData)
    {
        $riskLevel = 0;

        // Base risk from total incidents (max 3 points)
        $totalIncidents = (int) ($childData['totalIncidents'] ?? 0);
        $riskLevel += min(3, floor($totalIncidents / 2));

        // Risk from critical incidents (max 3 points)
        $criticalCount = (int) ($childData['criticalCount'] ?? 0);
        $riskLevel += min(3, $criticalCount * 2);

        // Risk from high severity incidents (max 2 points)
        $highCount = (int) ($childData['highCount'] ?? 0);
        $riskLevel += min(2, $highCount);

        // Risk from behavioral incidents (max 2 points)
        $behavioralCount = (int) ($childData['behavioralCount'] ?? 0);
        if ($behavioralCount >= 3) {
            $riskLevel += 2;
        } elseif ($behavioralCount >= 2) {
            $riskLevel += 1;
        }

        return min(10, $riskLevel);
    }

    /**
     * Get an existing pending pattern for a child.
     *
     * @param int $gibbonPersonID
     * @param string $patternType
     * @param int $gibbonSchoolYearID
     * @return array|false
     */
    protected function getExistingPendingPattern($gibbonPersonID, $patternType, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'patternType' => $patternType,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
        ];

        $sql = "SELECT * FROM gibbonCareIncidentPattern
                WHERE gibbonPersonID = :gibbonPersonID
                AND patternType = :patternType
                AND gibbonSchoolYearID = :gibbonSchoolYearID
                AND reviewStatus = 'Pending'
                ORDER BY detectedAt DESC
                LIMIT 1";

        return $this->db->selectOne($sql, $data);
    }

    /**
     * Update an existing pattern alert.
     *
     * @param int $gibbonCareIncidentPatternID
     * @param int $incidentCount
     * @param array $incidentIDs
     * @param string|null $description
     * @return int|false The pattern ID or false
     */
    protected function updatePatternAlert($gibbonCareIncidentPatternID, $incidentCount, array $incidentIDs, $description = null)
    {
        $data = [
            'gibbonCareIncidentPatternID' => $gibbonCareIncidentPatternID,
            'incidentCount' => $incidentCount,
            'incidentIDs' => json_encode($incidentIDs),
            'patternDescription' => $description,
            'detectedAt' => date('Y-m-d H:i:s'),
        ];

        $sql = "UPDATE gibbonCareIncidentPattern
                SET incidentCount = :incidentCount,
                    incidentIDs = :incidentIDs,
                    patternDescription = :patternDescription,
                    detectedAt = :detectedAt
                WHERE gibbonCareIncidentPatternID = :gibbonCareIncidentPatternID";

        $this->db->statement($sql, $data);

        return $gibbonCareIncidentPatternID;
    }

    /**
     * Get a setting value from the database.
     *
     * @param string $settingName
     * @param mixed $default
     * @return mixed
     */
    protected function getSettingValue($settingName, $default = null)
    {
        $data = ['settingName' => $settingName];
        $sql = "SELECT value FROM gibbonSetting
                WHERE scope = 'Care Tracking'
                AND name = :settingName";

        $result = $this->db->selectOne($sql, $data);

        return $result ? $result['value'] : $default;
    }
}
