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

namespace Gibbon\Module\RL24Submission\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * RL-24 Slip Gateway
 *
 * Handles individual RL-24 slip management for childcare expense tax forms.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24SlipGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonRL24Slip';
    private static $primaryKey = 'gibbonRL24SlipID';

    private static $searchableColumns = ['gibbonRL24Slip.parentFirstName', 'gibbonRL24Slip.parentLastName', 'gibbonRL24Slip.childFirstName', 'gibbonRL24Slip.childLastName', 'gibbonRL24Slip.notes'];

    /**
     * Query slips with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonRL24TransmissionID
     * @return DataSet
     */
    public function querySlips(QueryCriteria $criteria, $gibbonRL24TransmissionID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.gibbonRL24SlipID',
                'gibbonRL24Slip.gibbonRL24TransmissionID',
                'gibbonRL24Slip.gibbonPersonIDChild',
                'gibbonRL24Slip.gibbonPersonIDParent',
                'gibbonRL24Slip.slipNumber',
                'gibbonRL24Slip.taxYear',
                'gibbonRL24Slip.parentFirstName',
                'gibbonRL24Slip.parentLastName',
                'gibbonRL24Slip.childFirstName',
                'gibbonRL24Slip.childLastName',
                'gibbonRL24Slip.childDateOfBirth',
                'gibbonRL24Slip.servicePeriodStart',
                'gibbonRL24Slip.servicePeriodEnd',
                'gibbonRL24Slip.totalDays',
                'gibbonRL24Slip.case11Amount',
                'gibbonRL24Slip.case12Amount',
                'gibbonRL24Slip.case13Amount',
                'gibbonRL24Slip.case14Amount',
                'gibbonRL24Slip.caseACode',
                'gibbonRL24Slip.status',
                'gibbonRL24Slip.timestampCreated',
                'gibbonRL24Slip.timestampModified',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
            ])
            ->leftJoin('gibbonPerson as child', 'gibbonRL24Slip.gibbonPersonIDChild=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as parent', 'gibbonRL24Slip.gibbonPersonIDParent=parent.gibbonPersonID')
            ->where('gibbonRL24Slip.gibbonRL24TransmissionID=:gibbonRL24TransmissionID')
            ->bindValue('gibbonRL24TransmissionID', $gibbonRL24TransmissionID);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonRL24Slip.status=:status')
                    ->bindValue('status', $status);
            },
            'child' => function ($query, $gibbonPersonIDChild) {
                return $query
                    ->where('gibbonRL24Slip.gibbonPersonIDChild=:gibbonPersonIDChild')
                    ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild);
            },
            'parent' => function ($query, $gibbonPersonIDParent) {
                return $query
                    ->where('gibbonRL24Slip.gibbonPersonIDParent=:gibbonPersonIDParent')
                    ->bindValue('gibbonPersonIDParent', $gibbonPersonIDParent);
            },
            'caseACode' => function ($query, $caseACode) {
                return $query
                    ->where('gibbonRL24Slip.caseACode=:caseACode')
                    ->bindValue('caseACode', $caseACode);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query slips for a specific tax year.
     *
     * @param QueryCriteria $criteria
     * @param int $taxYear
     * @return DataSet
     */
    public function querySlipsByTaxYear(QueryCriteria $criteria, $taxYear)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.gibbonRL24SlipID',
                'gibbonRL24Slip.gibbonRL24TransmissionID',
                'gibbonRL24Slip.slipNumber',
                'gibbonRL24Slip.taxYear',
                'gibbonRL24Slip.parentFirstName',
                'gibbonRL24Slip.parentLastName',
                'gibbonRL24Slip.childFirstName',
                'gibbonRL24Slip.childLastName',
                'gibbonRL24Slip.totalDays',
                'gibbonRL24Slip.case11Amount',
                'gibbonRL24Slip.case12Amount',
                'gibbonRL24Slip.caseACode',
                'gibbonRL24Slip.status',
                'gibbonRL24Slip.timestampCreated',
                'transmission.fileName as transmissionFileName',
                'transmission.status as transmissionStatus',
            ])
            ->leftJoin('gibbonRL24Transmission as transmission', 'gibbonRL24Slip.gibbonRL24TransmissionID=transmission.gibbonRL24TransmissionID')
            ->where('gibbonRL24Slip.taxYear=:taxYear')
            ->bindValue('taxYear', $taxYear);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonRL24Slip.status=:status')
                    ->bindValue('status', $status);
            },
            'transmission' => function ($query, $gibbonRL24TransmissionID) {
                return $query
                    ->where('gibbonRL24Slip.gibbonRL24TransmissionID=:gibbonRL24TransmissionID')
                    ->bindValue('gibbonRL24TransmissionID', $gibbonRL24TransmissionID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query slips for a specific child.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonIDChild
     * @return DataSet
     */
    public function querySlipsByChild(QueryCriteria $criteria, $gibbonPersonIDChild)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.gibbonRL24SlipID',
                'gibbonRL24Slip.gibbonRL24TransmissionID',
                'gibbonRL24Slip.slipNumber',
                'gibbonRL24Slip.taxYear',
                'gibbonRL24Slip.parentFirstName',
                'gibbonRL24Slip.parentLastName',
                'gibbonRL24Slip.servicePeriodStart',
                'gibbonRL24Slip.servicePeriodEnd',
                'gibbonRL24Slip.totalDays',
                'gibbonRL24Slip.case11Amount',
                'gibbonRL24Slip.case12Amount',
                'gibbonRL24Slip.case13Amount',
                'gibbonRL24Slip.case14Amount',
                'gibbonRL24Slip.caseACode',
                'gibbonRL24Slip.status',
                'gibbonRL24Slip.timestampCreated',
                'transmission.fileName as transmissionFileName',
                'transmission.status as transmissionStatus',
            ])
            ->leftJoin('gibbonRL24Transmission as transmission', 'gibbonRL24Slip.gibbonRL24TransmissionID=transmission.gibbonRL24TransmissionID')
            ->where('gibbonRL24Slip.gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild);

        $criteria->addFilterRules([
            'taxYear' => function ($query, $taxYear) {
                return $query
                    ->where('gibbonRL24Slip.taxYear=:taxYear')
                    ->bindValue('taxYear', $taxYear);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonRL24Slip.status=:status')
                    ->bindValue('status', $status);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select slips by transmission ID.
     *
     * @param int $gibbonRL24TransmissionID
     * @return \Gibbon\Database\Result
     */
    public function selectSlipsByTransmission($gibbonRL24TransmissionID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.*',
            ])
            ->where('gibbonRL24Slip.gibbonRL24TransmissionID=:gibbonRL24TransmissionID')
            ->bindValue('gibbonRL24TransmissionID', $gibbonRL24TransmissionID)
            ->orderBy(['gibbonRL24Slip.slipNumber ASC']);

        return $this->runSelect($query);
    }

    /**
     * Select slips by status within a transmission.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $status
     * @return \Gibbon\Database\Result
     */
    public function selectSlipsByTransmissionAndStatus($gibbonRL24TransmissionID, $status)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.*',
            ])
            ->where('gibbonRL24Slip.gibbonRL24TransmissionID=:gibbonRL24TransmissionID')
            ->bindValue('gibbonRL24TransmissionID', $gibbonRL24TransmissionID)
            ->where('gibbonRL24Slip.status=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonRL24Slip.slipNumber ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get slip by ID with full details.
     *
     * @param int $gibbonRL24SlipID
     * @return array
     */
    public function getSlipByID($gibbonRL24SlipID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.*',
                'child.preferredName as childPreferredName',
                'child.surname as childSurname',
                'child.image_240 as childImage',
                'parent.preferredName as parentPreferredName',
                'parent.surname as parentSurname',
                'transmission.taxYear as transmissionTaxYear',
                'transmission.fileName as transmissionFileName',
                'transmission.status as transmissionStatus',
            ])
            ->leftJoin('gibbonPerson as child', 'gibbonRL24Slip.gibbonPersonIDChild=child.gibbonPersonID')
            ->leftJoin('gibbonPerson as parent', 'gibbonRL24Slip.gibbonPersonIDParent=parent.gibbonPersonID')
            ->leftJoin('gibbonRL24Transmission as transmission', 'gibbonRL24Slip.gibbonRL24TransmissionID=transmission.gibbonRL24TransmissionID')
            ->where('gibbonRL24Slip.gibbonRL24SlipID=:gibbonRL24SlipID')
            ->bindValue('gibbonRL24SlipID', $gibbonRL24SlipID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get the next slip number for a transmission.
     *
     * @param int $gibbonRL24TransmissionID
     * @return int
     */
    public function getNextSlipNumber($gibbonRL24TransmissionID)
    {
        $data = ['gibbonRL24TransmissionID' => $gibbonRL24TransmissionID];
        $sql = "SELECT COALESCE(MAX(slipNumber), 0) + 1 as nextSlipNumber
                FROM gibbonRL24Slip
                WHERE gibbonRL24TransmissionID=:gibbonRL24TransmissionID";

        $result = $this->db()->selectOne($sql, $data);
        return (int) ($result['nextSlipNumber'] ?? 1);
    }

    /**
     * Update slip status.
     *
     * @param int $gibbonRL24SlipID
     * @param string $status
     * @param array $additionalData Optional additional fields to update
     * @return bool
     */
    public function updateSlipStatus($gibbonRL24SlipID, $status, $additionalData = [])
    {
        $data = array_merge(['status' => $status], $additionalData);
        return $this->update($gibbonRL24SlipID, $data);
    }

    /**
     * Update slip amounts.
     *
     * @param int $gibbonRL24SlipID
     * @param int $totalDays
     * @param float $case11Amount
     * @param float $case12Amount
     * @param float $case13Amount
     * @param float $case14Amount
     * @return bool
     */
    public function updateSlipAmounts($gibbonRL24SlipID, $totalDays, $case11Amount, $case12Amount, $case13Amount, $case14Amount)
    {
        return $this->update($gibbonRL24SlipID, [
            'totalDays' => $totalDays,
            'case11Amount' => $case11Amount,
            'case12Amount' => $case12Amount,
            'case13Amount' => $case13Amount,
            'case14Amount' => $case14Amount,
        ]);
    }

    /**
     * Create a new slip record.
     *
     * @param int $gibbonRL24TransmissionID
     * @param int $gibbonPersonIDChild
     * @param int|null $gibbonPersonIDParent
     * @param array $slipData Slip data including names, addresses, amounts, etc.
     * @return int|false
     */
    public function createSlip($gibbonRL24TransmissionID, $gibbonPersonIDChild, $gibbonPersonIDParent, $slipData = [])
    {
        $slipNumber = $this->getNextSlipNumber($gibbonRL24TransmissionID);

        return $this->insert(array_merge([
            'gibbonRL24TransmissionID' => $gibbonRL24TransmissionID,
            'gibbonPersonIDChild' => $gibbonPersonIDChild,
            'gibbonPersonIDParent' => $gibbonPersonIDParent,
            'slipNumber' => $slipNumber,
            'status' => 'Draft',
            'caseACode' => 'O', // Original slip by default
        ], $slipData));
    }

    /**
     * Create an amended slip referencing an original slip.
     *
     * @param int $originalSlipID
     * @param array $amendedData Updated data for the amended slip
     * @return int|false
     */
    public function createAmendedSlip($originalSlipID, $amendedData = [])
    {
        $originalSlip = $this->getSlipByID($originalSlipID);
        if (empty($originalSlip)) {
            return false;
        }

        // Mark original slip as amended
        $this->updateSlipStatus($originalSlipID, 'Amended');

        $slipNumber = $this->getNextSlipNumber($originalSlip['gibbonRL24TransmissionID']);

        return $this->insert(array_merge([
            'gibbonRL24TransmissionID' => $originalSlip['gibbonRL24TransmissionID'],
            'gibbonPersonIDChild' => $originalSlip['gibbonPersonIDChild'],
            'gibbonPersonIDParent' => $originalSlip['gibbonPersonIDParent'],
            'slipNumber' => $slipNumber,
            'taxYear' => $originalSlip['taxYear'],
            'parentFirstName' => $originalSlip['parentFirstName'],
            'parentLastName' => $originalSlip['parentLastName'],
            'parentSIN' => $originalSlip['parentSIN'],
            'parentAddressLine1' => $originalSlip['parentAddressLine1'],
            'parentAddressLine2' => $originalSlip['parentAddressLine2'],
            'parentCity' => $originalSlip['parentCity'],
            'parentProvince' => $originalSlip['parentProvince'],
            'parentPostalCode' => $originalSlip['parentPostalCode'],
            'childFirstName' => $originalSlip['childFirstName'],
            'childLastName' => $originalSlip['childLastName'],
            'childDateOfBirth' => $originalSlip['childDateOfBirth'],
            'servicePeriodStart' => $originalSlip['servicePeriodStart'],
            'servicePeriodEnd' => $originalSlip['servicePeriodEnd'],
            'totalDays' => $originalSlip['totalDays'],
            'case11Amount' => $originalSlip['case11Amount'],
            'case12Amount' => $originalSlip['case12Amount'],
            'case13Amount' => $originalSlip['case13Amount'],
            'case14Amount' => $originalSlip['case14Amount'],
            'status' => 'Draft',
            'caseACode' => 'A', // Amended slip
            'amendedSlipID' => $originalSlipID,
        ], $amendedData));
    }

    /**
     * Create a cancelled slip referencing an original slip.
     *
     * @param int $originalSlipID
     * @param string|null $notes
     * @return int|false
     */
    public function createCancelledSlip($originalSlipID, $notes = null)
    {
        $originalSlip = $this->getSlipByID($originalSlipID);
        if (empty($originalSlip)) {
            return false;
        }

        // Mark original slip as cancelled
        $this->updateSlipStatus($originalSlipID, 'Cancelled', ['notes' => $notes]);

        $slipNumber = $this->getNextSlipNumber($originalSlip['gibbonRL24TransmissionID']);

        return $this->insert([
            'gibbonRL24TransmissionID' => $originalSlip['gibbonRL24TransmissionID'],
            'gibbonPersonIDChild' => $originalSlip['gibbonPersonIDChild'],
            'gibbonPersonIDParent' => $originalSlip['gibbonPersonIDParent'],
            'slipNumber' => $slipNumber,
            'taxYear' => $originalSlip['taxYear'],
            'parentFirstName' => $originalSlip['parentFirstName'],
            'parentLastName' => $originalSlip['parentLastName'],
            'parentSIN' => $originalSlip['parentSIN'],
            'childFirstName' => $originalSlip['childFirstName'],
            'childLastName' => $originalSlip['childLastName'],
            'totalDays' => 0,
            'case11Amount' => 0,
            'case12Amount' => 0,
            'case13Amount' => 0,
            'case14Amount' => 0,
            'status' => 'Draft',
            'caseACode' => 'D', // Cancelled slip
            'amendedSlipID' => $originalSlipID,
            'notes' => $notes,
        ]);
    }

    /**
     * Get slip summary totals for a transmission.
     *
     * @param int $gibbonRL24TransmissionID
     * @return array
     */
    public function getSlipSummaryByTransmission($gibbonRL24TransmissionID)
    {
        $data = ['gibbonRL24TransmissionID' => $gibbonRL24TransmissionID];
        $sql = "SELECT
                    COUNT(*) as totalSlips,
                    SUM(CASE WHEN status='Draft' THEN 1 ELSE 0 END) as draftCount,
                    SUM(CASE WHEN status='Included' THEN 1 ELSE 0 END) as includedCount,
                    SUM(CASE WHEN status='Amended' THEN 1 ELSE 0 END) as amendedCount,
                    SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) as cancelledCount,
                    SUM(totalDays) as totalDays,
                    SUM(case11Amount) as totalCase11,
                    SUM(case12Amount) as totalCase12,
                    SUM(case13Amount) as totalCase13,
                    SUM(case14Amount) as totalCase14
                FROM gibbonRL24Slip
                WHERE gibbonRL24TransmissionID=:gibbonRL24TransmissionID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSlips' => 0,
            'draftCount' => 0,
            'includedCount' => 0,
            'amendedCount' => 0,
            'cancelledCount' => 0,
            'totalDays' => 0,
            'totalCase11' => 0,
            'totalCase12' => 0,
            'totalCase13' => 0,
            'totalCase14' => 0,
        ];
    }

    /**
     * Get included slips totals for a transmission (for XML generation).
     *
     * @param int $gibbonRL24TransmissionID
     * @return array
     */
    public function getIncludedSlipTotals($gibbonRL24TransmissionID)
    {
        $data = ['gibbonRL24TransmissionID' => $gibbonRL24TransmissionID];
        $sql = "SELECT
                    COUNT(*) as totalSlips,
                    SUM(totalDays) as totalDays,
                    SUM(case11Amount) as totalCase11,
                    SUM(case12Amount) as totalCase12
                FROM gibbonRL24Slip
                WHERE gibbonRL24TransmissionID=:gibbonRL24TransmissionID
                AND status='Included'";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSlips' => 0,
            'totalDays' => 0,
            'totalCase11' => 0,
            'totalCase12' => 0,
        ];
    }

    /**
     * Mark all draft slips as included for a transmission.
     *
     * @param int $gibbonRL24TransmissionID
     * @return int Number of slips updated
     */
    public function includeDraftSlips($gibbonRL24TransmissionID)
    {
        $data = [
            'gibbonRL24TransmissionID' => $gibbonRL24TransmissionID,
            'oldStatus' => 'Draft',
            'newStatus' => 'Included',
        ];
        $sql = "UPDATE gibbonRL24Slip
                SET status=:newStatus
                WHERE gibbonRL24TransmissionID=:gibbonRL24TransmissionID
                AND status=:oldStatus";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Check if a slip exists for a child in a specific tax year.
     *
     * @param int $gibbonPersonIDChild
     * @param int $taxYear
     * @param int|null $excludeSlipID Optional slip ID to exclude (for updates)
     * @return bool
     */
    public function slipExistsForChildAndYear($gibbonPersonIDChild, $taxYear, $excludeSlipID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonRL24SlipID'])
            ->where('gibbonPersonIDChild=:gibbonPersonIDChild')
            ->bindValue('gibbonPersonIDChild', $gibbonPersonIDChild)
            ->where('taxYear=:taxYear')
            ->bindValue('taxYear', $taxYear)
            ->where("status NOT IN ('Cancelled', 'Amended')");

        if ($excludeSlipID !== null) {
            $query
                ->where('gibbonRL24SlipID!=:excludeSlipID')
                ->bindValue('excludeSlipID', $excludeSlipID);
        }

        $result = $this->runSelect($query);
        return $result->isNotEmpty();
    }

    /**
     * Get amended slip history for an original slip.
     *
     * @param int $originalSlipID
     * @return \Gibbon\Database\Result
     */
    public function selectAmendmentHistory($originalSlipID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Slip.*',
            ])
            ->where('gibbonRL24Slip.amendedSlipID=:amendedSlipID')
            ->bindValue('amendedSlipID', $originalSlipID)
            ->orderBy(['gibbonRL24Slip.timestampCreated ASC']);

        return $this->runSelect($query);
    }

    /**
     * Delete a draft slip.
     *
     * @param int $gibbonRL24SlipID
     * @return bool
     */
    public function deleteDraftSlip($gibbonRL24SlipID)
    {
        // Only delete if it's a draft
        $slip = $this->getByID($gibbonRL24SlipID);
        if (empty($slip) || $slip['status'] !== 'Draft') {
            return false;
        }

        return $this->delete($gibbonRL24SlipID);
    }
}
