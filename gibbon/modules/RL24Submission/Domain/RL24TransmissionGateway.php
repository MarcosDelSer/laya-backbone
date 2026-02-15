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
 * RL-24 Transmission Gateway
 *
 * Handles batch transmission management for RL-24 government submissions to Revenu Quebec.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RL24TransmissionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonRL24Transmission';
    private static $primaryKey = 'gibbonRL24TransmissionID';

    private static $searchableColumns = ['gibbonRL24Transmission.fileName', 'gibbonRL24Transmission.providerName', 'gibbonRL24Transmission.confirmationNumber', 'gibbonRL24Transmission.notes'];

    /**
     * Query transmissions with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryTransmissions(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Transmission.gibbonRL24TransmissionID',
                'gibbonRL24Transmission.gibbonSchoolYearID',
                'gibbonRL24Transmission.taxYear',
                'gibbonRL24Transmission.sequenceNumber',
                'gibbonRL24Transmission.fileName',
                'gibbonRL24Transmission.status',
                'gibbonRL24Transmission.preparerNumber',
                'gibbonRL24Transmission.providerName',
                'gibbonRL24Transmission.providerNEQ',
                'gibbonRL24Transmission.totalSlips',
                'gibbonRL24Transmission.totalAmountCase11',
                'gibbonRL24Transmission.totalAmountCase12',
                'gibbonRL24Transmission.totalDays',
                'gibbonRL24Transmission.xmlValidated',
                'gibbonRL24Transmission.submissionDate',
                'gibbonRL24Transmission.confirmationNumber',
                'gibbonRL24Transmission.timestampCreated',
                'gibbonRL24Transmission.timestampModified',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
                'submittedBy.preferredName as submittedByName',
                'submittedBy.surname as submittedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonRL24Transmission.generatedByID=generatedBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as submittedBy', 'gibbonRL24Transmission.submittedByID=submittedBy.gibbonPersonID')
            ->where('gibbonRL24Transmission.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        $criteria->addFilterRules([
            'taxYear' => function ($query, $taxYear) {
                return $query
                    ->where('gibbonRL24Transmission.taxYear=:taxYear')
                    ->bindValue('taxYear', $taxYear);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonRL24Transmission.status=:status')
                    ->bindValue('status', $status);
            },
            'xmlValidated' => function ($query, $value) {
                return $query
                    ->where('gibbonRL24Transmission.xmlValidated=:xmlValidated')
                    ->bindValue('xmlValidated', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query transmissions for a specific tax year.
     *
     * @param QueryCriteria $criteria
     * @param int $taxYear
     * @return DataSet
     */
    public function queryTransmissionsByTaxYear(QueryCriteria $criteria, $taxYear)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Transmission.gibbonRL24TransmissionID',
                'gibbonRL24Transmission.gibbonSchoolYearID',
                'gibbonRL24Transmission.taxYear',
                'gibbonRL24Transmission.sequenceNumber',
                'gibbonRL24Transmission.fileName',
                'gibbonRL24Transmission.status',
                'gibbonRL24Transmission.providerName',
                'gibbonRL24Transmission.totalSlips',
                'gibbonRL24Transmission.totalAmountCase11',
                'gibbonRL24Transmission.totalAmountCase12',
                'gibbonRL24Transmission.totalDays',
                'gibbonRL24Transmission.xmlValidated',
                'gibbonRL24Transmission.submissionDate',
                'gibbonRL24Transmission.confirmationNumber',
                'gibbonRL24Transmission.timestampCreated',
            ])
            ->where('gibbonRL24Transmission.taxYear=:taxYear')
            ->bindValue('taxYear', $taxYear);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonRL24Transmission.status=:status')
                    ->bindValue('status', $status);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select transmissions by status.
     *
     * @param string $status
     * @param int|null $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectTransmissionsByStatus($status, $gibbonSchoolYearID = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Transmission.gibbonRL24TransmissionID',
                'gibbonRL24Transmission.taxYear',
                'gibbonRL24Transmission.sequenceNumber',
                'gibbonRL24Transmission.fileName',
                'gibbonRL24Transmission.status',
                'gibbonRL24Transmission.totalSlips',
                'gibbonRL24Transmission.timestampCreated',
            ])
            ->where('gibbonRL24Transmission.status=:status')
            ->bindValue('status', $status)
            ->orderBy(['gibbonRL24Transmission.taxYear DESC', 'gibbonRL24Transmission.sequenceNumber DESC']);

        if ($gibbonSchoolYearID !== null) {
            $query
                ->where('gibbonRL24Transmission.gibbonSchoolYearID=:gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        return $this->runSelect($query);
    }

    /**
     * Get transmission by ID with full details.
     *
     * @param int $gibbonRL24TransmissionID
     * @return array
     */
    public function getTransmissionByID($gibbonRL24TransmissionID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonRL24Transmission.*',
                'generatedBy.preferredName as generatedByName',
                'generatedBy.surname as generatedBySurname',
                'submittedBy.preferredName as submittedByName',
                'submittedBy.surname as submittedBySurname',
            ])
            ->leftJoin('gibbonPerson as generatedBy', 'gibbonRL24Transmission.generatedByID=generatedBy.gibbonPersonID')
            ->leftJoin('gibbonPerson as submittedBy', 'gibbonRL24Transmission.submittedByID=submittedBy.gibbonPersonID')
            ->where('gibbonRL24Transmission.gibbonRL24TransmissionID=:gibbonRL24TransmissionID')
            ->bindValue('gibbonRL24TransmissionID', $gibbonRL24TransmissionID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get the next sequence number for a tax year.
     *
     * @param int $taxYear
     * @return int
     */
    public function getNextSequenceNumber($taxYear)
    {
        $data = ['taxYear' => $taxYear];
        $sql = "SELECT COALESCE(MAX(sequenceNumber), 0) + 1 as nextSequence
                FROM gibbonRL24Transmission
                WHERE taxYear=:taxYear";

        $result = $this->db()->selectOne($sql, $data);
        return (int) ($result['nextSequence'] ?? 1);
    }

    /**
     * Get transmission by tax year and sequence number.
     *
     * @param int $taxYear
     * @param int $sequenceNumber
     * @return array
     */
    public function getTransmissionByYearAndSequence($taxYear, $sequenceNumber)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('taxYear=:taxYear')
            ->bindValue('taxYear', $taxYear)
            ->where('sequenceNumber=:sequenceNumber')
            ->bindValue('sequenceNumber', $sequenceNumber);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Update transmission status.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $status
     * @param array $additionalData Optional additional fields to update
     * @return bool
     */
    public function updateTransmissionStatus($gibbonRL24TransmissionID, $status, $additionalData = [])
    {
        $data = array_merge(['status' => $status], $additionalData);
        return $this->update($gibbonRL24TransmissionID, $data);
    }

    /**
     * Update summary totals after batch processing.
     *
     * @param int $gibbonRL24TransmissionID
     * @param int $totalSlips
     * @param float $totalAmountCase11
     * @param float $totalAmountCase12
     * @param int $totalDays
     * @return bool
     */
    public function updateSummaryTotals($gibbonRL24TransmissionID, $totalSlips, $totalAmountCase11, $totalAmountCase12, $totalDays)
    {
        return $this->update($gibbonRL24TransmissionID, [
            'totalSlips' => $totalSlips,
            'totalAmountCase11' => $totalAmountCase11,
            'totalAmountCase12' => $totalAmountCase12,
            'totalDays' => $totalDays,
        ]);
    }

    /**
     * Update XML file path and validation status.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $xmlFilePath
     * @param bool $xmlValidated
     * @param string|null $validationErrors
     * @return bool
     */
    public function updateXmlFile($gibbonRL24TransmissionID, $xmlFilePath, $xmlValidated, $validationErrors = null)
    {
        return $this->update($gibbonRL24TransmissionID, [
            'xmlFilePath' => $xmlFilePath,
            'xmlValidated' => $xmlValidated ? 'Y' : 'N',
            'xmlValidationErrors' => $validationErrors,
        ]);
    }

    /**
     * Record transmission submission.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $submissionDate
     * @param int $submittedByID
     * @param string|null $confirmationNumber
     * @return bool
     */
    public function recordSubmission($gibbonRL24TransmissionID, $submissionDate, $submittedByID, $confirmationNumber = null)
    {
        return $this->update($gibbonRL24TransmissionID, [
            'status' => 'Submitted',
            'submissionDate' => $submissionDate,
            'submittedByID' => $submittedByID,
            'confirmationNumber' => $confirmationNumber,
        ]);
    }

    /**
     * Record transmission acceptance.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $confirmationNumber
     * @return bool
     */
    public function recordAcceptance($gibbonRL24TransmissionID, $confirmationNumber)
    {
        return $this->update($gibbonRL24TransmissionID, [
            'status' => 'Accepted',
            'confirmationNumber' => $confirmationNumber,
        ]);
    }

    /**
     * Record transmission rejection.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string $rejectionReason
     * @return bool
     */
    public function recordRejection($gibbonRL24TransmissionID, $rejectionReason)
    {
        return $this->update($gibbonRL24TransmissionID, [
            'status' => 'Rejected',
            'rejectionReason' => $rejectionReason,
        ]);
    }

    /**
     * Create a new transmission record.
     *
     * @param int $gibbonSchoolYearID
     * @param int $taxYear
     * @param int $generatedByID
     * @param array $providerInfo Provider information (name, NEQ, address, preparer number)
     * @return int|false
     */
    public function createTransmission($gibbonSchoolYearID, $taxYear, $generatedByID, $providerInfo = [])
    {
        $sequenceNumber = $this->getNextSequenceNumber($taxYear);

        // Generate filename: AAPPPPPPSSS.xml (AA=year last 2 digits, PPPPPP=preparer number, SSS=sequence)
        $fileName = null;
        if (!empty($providerInfo['preparerNumber'])) {
            $yearShort = substr((string) $taxYear, -2);
            $preparerNumber = str_pad(substr($providerInfo['preparerNumber'], 0, 6), 6, '0', STR_PAD_LEFT);
            $sequenceStr = str_pad((string) $sequenceNumber, 3, '0', STR_PAD_LEFT);
            $fileName = $yearShort . $preparerNumber . $sequenceStr . '.xml';
        }

        return $this->insert([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'taxYear' => $taxYear,
            'sequenceNumber' => $sequenceNumber,
            'fileName' => $fileName,
            'status' => 'Draft',
            'preparerNumber' => $providerInfo['preparerNumber'] ?? null,
            'providerName' => $providerInfo['providerName'] ?? null,
            'providerNEQ' => $providerInfo['providerNEQ'] ?? null,
            'providerAddress' => $providerInfo['providerAddress'] ?? null,
            'generatedByID' => $generatedByID,
        ]);
    }

    /**
     * Get transmission summary statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getTransmissionSummaryBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalTransmissions,
                    SUM(CASE WHEN status='Draft' THEN 1 ELSE 0 END) as draftCount,
                    SUM(CASE WHEN status='Generated' THEN 1 ELSE 0 END) as generatedCount,
                    SUM(CASE WHEN status='Validated' THEN 1 ELSE 0 END) as validatedCount,
                    SUM(CASE WHEN status='Submitted' THEN 1 ELSE 0 END) as submittedCount,
                    SUM(CASE WHEN status='Accepted' THEN 1 ELSE 0 END) as acceptedCount,
                    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(totalSlips) as totalSlips,
                    SUM(totalAmountCase11) as totalAmountCase11,
                    SUM(totalAmountCase12) as totalAmountCase12,
                    SUM(totalDays) as totalDays
                FROM gibbonRL24Transmission
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalTransmissions' => 0,
            'draftCount' => 0,
            'generatedCount' => 0,
            'validatedCount' => 0,
            'submittedCount' => 0,
            'acceptedCount' => 0,
            'rejectedCount' => 0,
            'totalSlips' => 0,
            'totalAmountCase11' => 0,
            'totalAmountCase12' => 0,
            'totalDays' => 0,
        ];
    }

    /**
     * Get list of distinct tax years with transmissions.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectDistinctTaxYears()
    {
        $sql = "SELECT DISTINCT taxYear
                FROM gibbonRL24Transmission
                ORDER BY taxYear DESC";

        return $this->db()->select($sql);
    }

    /**
     * Cancel a transmission and update its status.
     *
     * @param int $gibbonRL24TransmissionID
     * @param string|null $notes
     * @return bool
     */
    public function cancelTransmission($gibbonRL24TransmissionID, $notes = null)
    {
        $data = ['status' => 'Cancelled'];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        return $this->update($gibbonRL24TransmissionID, $data);
    }
}
