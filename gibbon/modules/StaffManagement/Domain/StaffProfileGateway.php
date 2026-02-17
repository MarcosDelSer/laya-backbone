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

namespace Gibbon\Module\StaffManagement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Staff Profile Gateway
 *
 * Handles staff profile CRUD operations for HR management.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class StaffProfileGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStaffProfile';
    private static $primaryKey = 'gibbonStaffProfileID';

    private static $searchableColumns = ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffProfile.employeeNumber', 'gibbonStaffProfile.position', 'gibbonStaffProfile.department'];

    /**
     * Query staff profiles with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryStaffProfiles(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.employeeNumber',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.department',
                'gibbonStaffProfile.employmentType',
                'gibbonStaffProfile.hireDate',
                'gibbonStaffProfile.status',
                'gibbonStaffProfile.qualificationLevel',
                'gibbonStaffProfile.timestampModified',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
                'gibbonPerson.phone1',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID');

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStaffProfile.status=:status')
                    ->bindValue('status', $status);
            },
            'employmentType' => function ($query, $employmentType) {
                return $query
                    ->where('gibbonStaffProfile.employmentType=:employmentType')
                    ->bindValue('employmentType', $employmentType);
            },
            'position' => function ($query, $position) {
                return $query
                    ->where('gibbonStaffProfile.position=:position')
                    ->bindValue('position', $position);
            },
            'department' => function ($query, $department) {
                return $query
                    ->where('gibbonStaffProfile.department=:department')
                    ->bindValue('department', $department);
            },
            'qualificationLevel' => function ($query, $qualificationLevel) {
                return $query
                    ->where('gibbonStaffProfile.qualificationLevel=:qualificationLevel')
                    ->bindValue('qualificationLevel', $qualificationLevel);
            },
            'hiredAfter' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffProfile.hireDate >= :hiredAfter')
                    ->bindValue('hiredAfter', $date);
            },
            'hiredBefore' => function ($query, $date) {
                return $query
                    ->where('gibbonStaffProfile.hireDate <= :hiredBefore')
                    ->bindValue('hiredBefore', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active staff profiles for scheduling and time tracking.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveStaff(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.employeeNumber',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.department',
                'gibbonStaffProfile.employmentType',
                'gibbonStaffProfile.qualificationLevel',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where("gibbonPerson.status='Full'");

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get staff profile by ID with full details.
     *
     * @param int $gibbonStaffProfileID
     * @return array
     */
    public function getStaffProfileByID($gibbonStaffProfileID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.firstName',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
                'gibbonPerson.phone1',
                'gibbonPerson.phone2',
                'gibbonPerson.dob',
                'gibbonPerson.gender',
                'createdBy.preferredName as createdByName',
                'createdBy.surname as createdBySurname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as createdBy', 'gibbonStaffProfile.createdByID=createdBy.gibbonPersonID')
            ->where('gibbonStaffProfile.gibbonStaffProfileID=:gibbonStaffProfileID')
            ->bindValue('gibbonStaffProfileID', $gibbonStaffProfileID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get staff profile by person ID.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getStaffProfileByPersonID($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.*',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.firstName',
                'gibbonPerson.image_240',
                'gibbonPerson.email',
                'gibbonPerson.phone1',
                'gibbonPerson.phone2',
                'gibbonPerson.dob',
                'gibbonPerson.gender',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffProfile.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Select all active staff members for dropdown lists.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectActiveStaffList()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonPersonID as value',
                "CONCAT(gibbonPerson.surname, ', ', gibbonPerson.preferredName) as name",
                'gibbonStaffProfile.position',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where("gibbonPerson.status='Full'")
            ->orderBy(['gibbonPerson.surname', 'gibbonPerson.preferredName']);

        return $this->runSelect($query);
    }

    /**
     * Select staff by qualification level for ratio compliance.
     *
     * @param string $qualificationLevel
     * @return \Gibbon\Database\Result
     */
    public function selectStaffByQualificationLevel($qualificationLevel)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.qualificationLevel',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where('gibbonStaffProfile.qualificationLevel=:qualificationLevel')
            ->bindValue('qualificationLevel', $qualificationLevel)
            ->orderBy(['gibbonPerson.surname', 'gibbonPerson.preferredName']);

        return $this->runSelect($query);
    }

    /**
     * Select staff members on probation whose probation is ending soon.
     *
     * @param string $endDate
     * @return \Gibbon\Database\Result
     */
    public function selectStaffWithProbationEnding($endDate)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.probationEndDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.email',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Active'")
            ->where('gibbonStaffProfile.probationEndDate IS NOT NULL')
            ->where('gibbonStaffProfile.probationEndDate <= :endDate')
            ->bindValue('endDate', $endDate)
            ->orderBy(['gibbonStaffProfile.probationEndDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get staff profile summary statistics.
     *
     * @return array
     */
    public function getStaffSummaryStatistics()
    {
        $data = [];
        $sql = "SELECT
                    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as totalActive,
                    SUM(CASE WHEN status='Inactive' THEN 1 ELSE 0 END) as totalInactive,
                    SUM(CASE WHEN status='On Leave' THEN 1 ELSE 0 END) as totalOnLeave,
                    SUM(CASE WHEN status='Terminated' THEN 1 ELSE 0 END) as totalTerminated,
                    SUM(CASE WHEN employmentType='Full-Time' AND status='Active' THEN 1 ELSE 0 END) as totalFullTime,
                    SUM(CASE WHEN employmentType='Part-Time' AND status='Active' THEN 1 ELSE 0 END) as totalPartTime,
                    SUM(CASE WHEN employmentType='Casual' AND status='Active' THEN 1 ELSE 0 END) as totalCasual,
                    SUM(CASE WHEN qualificationLevel='Director' AND status='Active' THEN 1 ELSE 0 END) as totalDirectors,
                    SUM(CASE WHEN qualificationLevel IN ('Level 1','Level 2','Level 3') AND status='Active' THEN 1 ELSE 0 END) as totalQualified,
                    SUM(CASE WHEN (qualificationLevel='Unqualified' OR qualificationLevel IS NULL) AND status='Active' THEN 1 ELSE 0 END) as totalUnqualified
                FROM gibbonStaffProfile";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalActive' => 0,
            'totalInactive' => 0,
            'totalOnLeave' => 0,
            'totalTerminated' => 0,
            'totalFullTime' => 0,
            'totalPartTime' => 0,
            'totalCasual' => 0,
            'totalDirectors' => 0,
            'totalQualified' => 0,
            'totalUnqualified' => 0,
        ];
    }

    /**
     * Get staff count by position for reporting.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectStaffCountByPosition()
    {
        $data = [];
        $sql = "SELECT
                    position,
                    COUNT(*) as staffCount
                FROM gibbonStaffProfile
                WHERE status='Active'
                GROUP BY position
                ORDER BY staffCount DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get staff count by department for reporting.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectStaffCountByDepartment()
    {
        $data = [];
        $sql = "SELECT
                    COALESCE(department, 'Unassigned') as department,
                    COUNT(*) as staffCount
                FROM gibbonStaffProfile
                WHERE status='Active'
                GROUP BY department
                ORDER BY staffCount DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a person already has a staff profile.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function hasStaffProfile($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*) FROM gibbonStaffProfile WHERE gibbonPersonID=:gibbonPersonID";

        return $this->db()->selectOne($sql, $data) > 0;
    }

    /**
     * Get staff members hired within a date range.
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectStaffHiredInDateRange($dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.department',
                'gibbonStaffProfile.hireDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonStaffProfile.hireDate >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonStaffProfile.hireDate <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonStaffProfile.hireDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get staff members terminated within a date range.
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @return \Gibbon\Database\Result
     */
    public function selectStaffTerminatedInDateRange($dateStart, $dateEnd)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStaffProfile.gibbonStaffProfileID',
                'gibbonStaffProfile.gibbonPersonID',
                'gibbonStaffProfile.position',
                'gibbonStaffProfile.terminationDate',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
            ])
            ->innerJoin('gibbonPerson', 'gibbonStaffProfile.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where("gibbonStaffProfile.status='Terminated'")
            ->where('gibbonStaffProfile.terminationDate >= :dateStart')
            ->bindValue('dateStart', $dateStart)
            ->where('gibbonStaffProfile.terminationDate <= :dateEnd')
            ->bindValue('dateEnd', $dateEnd)
            ->orderBy(['gibbonStaffProfile.terminationDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get all unique positions for dropdown.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectUniquePositions()
    {
        $data = [];
        $sql = "SELECT DISTINCT position FROM gibbonStaffProfile WHERE position IS NOT NULL AND position <> '' ORDER BY position";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get all unique departments for dropdown.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectUniqueDepartments()
    {
        $data = [];
        $sql = "SELECT DISTINCT department FROM gibbonStaffProfile WHERE department IS NOT NULL AND department <> '' ORDER BY department";

        return $this->db()->select($sql, $data);
    }
}
