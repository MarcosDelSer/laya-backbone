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

namespace Gibbon\Module\ChildEnrollment\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Enrollment Health Gateway
 *
 * Handles health information for child enrollment forms.
 * Each enrollment form has one health record (one-to-one relationship).
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentHealthGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentHealth';
    private static $primaryKey = 'gibbonChildEnrollmentHealthID';

    private static $searchableColumns = [
        'gibbonChildEnrollmentHealth.allergies',
        'gibbonChildEnrollmentHealth.medicalConditions',
        'gibbonChildEnrollmentHealth.medications',
        'gibbonChildEnrollmentHealth.doctorName',
        'gibbonChildEnrollmentHealth.specialNeeds',
    ];

    /**
     * Query health records with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryHealthRecords(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentHealthID',
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentHealth.allergies',
                'gibbonChildEnrollmentHealth.medicalConditions',
                'gibbonChildEnrollmentHealth.hasEpiPen',
                'gibbonChildEnrollmentHealth.epiPenInstructions',
                'gibbonChildEnrollmentHealth.medications',
                'gibbonChildEnrollmentHealth.doctorName',
                'gibbonChildEnrollmentHealth.doctorPhone',
                'gibbonChildEnrollmentHealth.doctorAddress',
                'gibbonChildEnrollmentHealth.healthInsuranceNumber',
                'gibbonChildEnrollmentHealth.healthInsuranceExpiry',
                'gibbonChildEnrollmentHealth.specialNeeds',
                'gibbonChildEnrollmentHealth.developmentalNotes',
                'gibbonChildEnrollmentHealth.timestampCreated',
                'gibbonChildEnrollmentHealth.timestampModified',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID');

        $criteria->addFilterRules([
            'hasEpiPen' => function ($query, $hasEpiPen) {
                return $query
                    ->where('gibbonChildEnrollmentHealth.hasEpiPen=:hasEpiPen')
                    ->bindValue('hasEpiPen', $hasEpiPen);
            },
            'hasAllergies' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentHealth.allergies IS NOT NULL AND gibbonChildEnrollmentHealth.allergies != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentHealth.allergies IS NULL OR gibbonChildEnrollmentHealth.allergies = \'\')');
            },
            'hasMedications' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentHealth.medications IS NOT NULL AND gibbonChildEnrollmentHealth.medications != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentHealth.medications IS NULL OR gibbonChildEnrollmentHealth.medications = \'\')');
            },
            'hasSpecialNeeds' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->where('gibbonChildEnrollmentHealth.specialNeeds IS NOT NULL AND gibbonChildEnrollmentHealth.specialNeeds != \'\'');
                }
                return $query
                    ->where('(gibbonChildEnrollmentHealth.specialNeeds IS NULL OR gibbonChildEnrollmentHealth.specialNeeds = \'\')');
            },
            'insuranceExpiring' => function ($query, $days) {
                return $query
                    ->where('gibbonChildEnrollmentHealth.healthInsuranceExpiry IS NOT NULL')
                    ->where('gibbonChildEnrollmentHealth.healthInsuranceExpiry <= DATE_ADD(CURRENT_DATE, INTERVAL :days DAY)')
                    ->bindValue('days', (int) $days);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get health information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|false
     */
    public function getHealthByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get a specific health record by ID.
     *
     * @param int $gibbonChildEnrollmentHealthID
     * @return array|false
     */
    public function getHealthByID($gibbonChildEnrollmentHealthID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentHealthID=:gibbonChildEnrollmentHealthID')
            ->bindValue('gibbonChildEnrollmentHealthID', $gibbonChildEnrollmentHealthID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Check if a health record exists for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function healthRecordExists($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonChildEnrollmentHealthID'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Insert or update health information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param array $data
     * @return int|false Returns the health record ID on success
     */
    public function saveHealth($gibbonChildEnrollmentFormID, array $data)
    {
        $existing = $this->getHealthByForm($gibbonChildEnrollmentFormID);

        if ($existing) {
            // Update existing record
            return $this->update($existing['gibbonChildEnrollmentHealthID'], $data)
                ? $existing['gibbonChildEnrollmentHealthID']
                : false;
        }

        // Create new record
        $data['gibbonChildEnrollmentFormID'] = $gibbonChildEnrollmentFormID;
        return $this->insert($data);
    }

    /**
     * Update health information.
     *
     * @param int $gibbonChildEnrollmentHealthID
     * @param array $data
     * @return bool
     */
    public function updateHealth($gibbonChildEnrollmentHealthID, array $data)
    {
        return $this->update($gibbonChildEnrollmentHealthID, $data);
    }

    /**
     * Delete health information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function deleteHealthByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM gibbonChildEnrollmentHealth
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data) !== false;
    }

    /**
     * Get allergy information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null Decoded JSON array of allergies
     */
    public function getAllergies($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        if (!$health || empty($health['allergies'])) {
            return null;
        }

        $allergies = json_decode($health['allergies'], true);
        return is_array($allergies) ? $allergies : null;
    }

    /**
     * Get medication information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null Decoded JSON array of medications
     */
    public function getMedications($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        if (!$health || empty($health['medications'])) {
            return null;
        }

        $medications = json_decode($health['medications'], true);
        return is_array($medications) ? $medications : null;
    }

    /**
     * Check if child has an EpiPen requirement.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function hasEpiPen($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        return $health && $health['hasEpiPen'] === 'Y';
    }

    /**
     * Get doctor information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getDoctorInfo($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        if (!$health) {
            return null;
        }

        return [
            'name' => $health['doctorName'],
            'phone' => $health['doctorPhone'],
            'address' => $health['doctorAddress'],
        ];
    }

    /**
     * Get health insurance information for an enrollment form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null
     */
    public function getHealthInsuranceInfo($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        if (!$health) {
            return null;
        }

        return [
            'number' => $health['healthInsuranceNumber'],
            'expiry' => $health['healthInsuranceExpiry'],
            'isExpired' => $health['healthInsuranceExpiry'] && $health['healthInsuranceExpiry'] < date('Y-m-d'),
        ];
    }

    /**
     * Query children with expiring health insurance.
     *
     * @param QueryCriteria $criteria
     * @param int $daysUntilExpiry
     * @return DataSet
     */
    public function queryExpiringInsurance(QueryCriteria $criteria, $daysUntilExpiry = 30)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentHealthID',
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentHealth.healthInsuranceNumber',
                'gibbonChildEnrollmentHealth.healthInsuranceExpiry',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentHealth.healthInsuranceExpiry IS NOT NULL')
            ->where('gibbonChildEnrollmentHealth.healthInsuranceExpiry <= DATE_ADD(CURRENT_DATE, INTERVAL :days DAY)')
            ->where('gibbonChildEnrollmentHealth.healthInsuranceExpiry >= CURRENT_DATE')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')')
            ->bindValue('days', (int) $daysUntilExpiry)
            ->orderBy(['gibbonChildEnrollmentHealth.healthInsuranceExpiry ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query children with EpiPen requirements.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithEpiPen(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentHealthID',
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentHealth.allergies',
                'gibbonChildEnrollmentHealth.epiPenInstructions',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentHealth.hasEpiPen=:hasEpiPen')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')')
            ->bindValue('hasEpiPen', 'Y');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query children with special needs.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryChildrenWithSpecialNeeds(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentHealthID',
                'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentHealth.specialNeeds',
                'gibbonChildEnrollmentHealth.developmentalNotes',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentHealth.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentHealth.specialNeeds IS NOT NULL')
            ->where('gibbonChildEnrollmentHealth.specialNeeds != \'\'')
            ->where('gibbonChildEnrollmentForm.status IN (\'Submitted\', \'Approved\')');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get health information for display purposes (formatted).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array|null Formatted health information
     */
    public function getHealthInfo($gibbonChildEnrollmentFormID)
    {
        $health = $this->getHealthByForm($gibbonChildEnrollmentFormID);
        if (!$health) {
            return null;
        }

        return [
            'allergies' => $this->getAllergies($gibbonChildEnrollmentFormID),
            'medicalConditions' => $health['medicalConditions'],
            'hasEpiPen' => $health['hasEpiPen'] === 'Y',
            'epiPenInstructions' => $health['epiPenInstructions'],
            'medications' => $this->getMedications($gibbonChildEnrollmentFormID),
            'doctor' => $this->getDoctorInfo($gibbonChildEnrollmentFormID),
            'healthInsurance' => $this->getHealthInsuranceInfo($gibbonChildEnrollmentFormID),
            'specialNeeds' => $health['specialNeeds'],
            'developmentalNotes' => $health['developmentalNotes'],
        ];
    }

    /**
     * Validate health data before insert/update.
     *
     * @param array $data
     * @return array Array of validation errors (empty if valid)
     */
    public function validateHealthData(array $data)
    {
        $errors = [];

        // Validate EpiPen requires instructions
        if (isset($data['hasEpiPen']) && $data['hasEpiPen'] === 'Y') {
            if (empty($data['epiPenInstructions'])) {
                $errors[] = 'EpiPen instructions are required when EpiPen is indicated.';
            }
        }

        // Validate allergies JSON format if provided
        if (!empty($data['allergies'])) {
            $decoded = json_decode($data['allergies'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Allergies must be valid JSON format.';
            }
        }

        // Validate medications JSON format if provided
        if (!empty($data['medications'])) {
            $decoded = json_decode($data['medications'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Medications must be valid JSON format.';
            }
        }

        // Validate health insurance expiry date format if provided
        if (!empty($data['healthInsuranceExpiry'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['healthInsuranceExpiry']);
            if (!$date || $date->format('Y-m-d') !== $data['healthInsuranceExpiry']) {
                $errors[] = 'Health insurance expiry date must be in YYYY-MM-DD format.';
            }
        }

        return $errors;
    }

    /**
     * Get health summary statistics for reporting.
     *
     * @return array
     */
    public function getHealthStatistics()
    {
        $sql = "SELECT
                    COUNT(*) as totalRecords,
                    SUM(CASE WHEN hasEpiPen='Y' THEN 1 ELSE 0 END) as withEpiPen,
                    SUM(CASE WHEN allergies IS NOT NULL AND allergies != '' THEN 1 ELSE 0 END) as withAllergies,
                    SUM(CASE WHEN medications IS NOT NULL AND medications != '' THEN 1 ELSE 0 END) as withMedications,
                    SUM(CASE WHEN specialNeeds IS NOT NULL AND specialNeeds != '' THEN 1 ELSE 0 END) as withSpecialNeeds,
                    SUM(CASE WHEN healthInsuranceExpiry < CURRENT_DATE THEN 1 ELSE 0 END) as expiredInsurance
                FROM gibbonChildEnrollmentHealth h
                INNER JOIN gibbonChildEnrollmentForm f
                    ON h.gibbonChildEnrollmentFormID = f.gibbonChildEnrollmentFormID
                WHERE f.status IN ('Submitted', 'Approved')";

        return $this->db()->selectOne($sql) ?: [
            'totalRecords' => 0,
            'withEpiPen' => 0,
            'withAllergies' => 0,
            'withMedications' => 0,
            'withSpecialNeeds' => 0,
            'expiredInsurance' => 0,
        ];
    }
}
