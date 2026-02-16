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
 * Child Enrollment Signature Gateway
 *
 * Handles e-signature capture and management for child enrollment forms.
 * Supports parent signatures (Parent1, Parent2) and director signatures.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EnrollmentSignatureGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonChildEnrollmentSignature';
    private static $primaryKey = 'gibbonChildEnrollmentSignatureID';

    private static $searchableColumns = ['gibbonChildEnrollmentSignature.signerName'];

    /**
     * Valid signature types.
     */
    const SIGNATURE_PARENT1 = 'Parent1';
    const SIGNATURE_PARENT2 = 'Parent2';
    const SIGNATURE_DIRECTOR = 'Director';

    /**
     * Query signatures with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int|null $gibbonSchoolYearID
     * @return DataSet
     */
    public function querySignatures(QueryCriteria $criteria, $gibbonSchoolYearID = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentSignature.gibbonChildEnrollmentSignatureID',
                'gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentSignature.signatureType',
                'gibbonChildEnrollmentSignature.signerName',
                'gibbonChildEnrollmentSignature.signedAt',
                'gibbonChildEnrollmentSignature.ipAddress',
                'gibbonChildEnrollmentSignature.timestampCreated',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status as formStatus',
            ])
            ->innerJoin('gibbonChildEnrollmentForm', 'gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID');

        if ($gibbonSchoolYearID !== null) {
            $query
                ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        $criteria->addFilterRules([
            'form' => function ($query, $gibbonChildEnrollmentFormID) {
                return $query
                    ->where('gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
                    ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID);
            },
            'signatureType' => function ($query, $signatureType) {
                return $query
                    ->where('gibbonChildEnrollmentSignature.signatureType=:signatureType')
                    ->bindValue('signatureType', $signatureType);
            },
            'signedDateFrom' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonChildEnrollmentSignature.signedAt)>=:signedDateFrom')
                    ->bindValue('signedDateFrom', $date);
            },
            'signedDateTo' => function ($query, $date) {
                return $query
                    ->where('DATE(gibbonChildEnrollmentSignature.signedAt)<=:signedDateTo')
                    ->bindValue('signedDateTo', $date);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query signatures for a specific form.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonChildEnrollmentFormID
     * @return DataSet
     */
    public function querySignaturesByForm(QueryCriteria $criteria, $gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentSignature.gibbonChildEnrollmentSignatureID',
                'gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentSignature.signatureType',
                'gibbonChildEnrollmentSignature.signerName',
                'gibbonChildEnrollmentSignature.signedAt',
                'gibbonChildEnrollmentSignature.ipAddress',
                'gibbonChildEnrollmentSignature.userAgent',
                'gibbonChildEnrollmentSignature.timestampCreated',
            ])
            ->where('gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(["FIELD(gibbonChildEnrollmentSignature.signatureType, 'Parent1', 'Parent2', 'Director')"]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all signatures for a specific form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return \Gibbon\Database\Result
     */
    public function selectSignaturesByForm($gibbonChildEnrollmentFormID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonChildEnrollmentSignature.*',
            ])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->orderBy(["FIELD(signatureType, 'Parent1', 'Parent2', 'Director')"]);

        return $this->runSelect($query);
    }

    /**
     * Get a signature by ID.
     *
     * @param int $gibbonChildEnrollmentSignatureID
     * @return array|false
     */
    public function getSignatureByID($gibbonChildEnrollmentSignatureID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentSignatureID=:gibbonChildEnrollmentSignatureID')
            ->bindValue('gibbonChildEnrollmentSignatureID', $gibbonChildEnrollmentSignatureID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get a signature by form and type.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $signatureType
     * @return array|false
     */
    public function getSignatureByFormAndType($gibbonChildEnrollmentFormID, $signatureType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID')
            ->bindValue('gibbonChildEnrollmentFormID', $gibbonChildEnrollmentFormID)
            ->where('signatureType=:signatureType')
            ->bindValue('signatureType', $signatureType);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Check if a signature exists for a form and type.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $signatureType
     * @return bool
     */
    public function hasSignature($gibbonChildEnrollmentFormID, $signatureType)
    {
        return $this->getSignatureByFormAndType($gibbonChildEnrollmentFormID, $signatureType) !== false;
    }

    /**
     * Add a new signature to a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param string $signatureType
     * @param string $signatureData Base64-encoded signature image
     * @param string $signerName
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return int|false
     */
    public function addSignature($gibbonChildEnrollmentFormID, $signatureType, $signatureData, $signerName, $ipAddress = null, $userAgent = null)
    {
        // Validate signature type
        if (!$this->isValidSignatureType($signatureType)) {
            return false;
        }

        // Check if this signature type already exists for this form
        $existing = $this->getSignatureByFormAndType($gibbonChildEnrollmentFormID, $signatureType);
        if ($existing) {
            // Update existing signature
            return $this->updateSignature(
                $existing['gibbonChildEnrollmentSignatureID'],
                $signatureData,
                $signerName,
                $ipAddress,
                $userAgent
            ) ? $existing['gibbonChildEnrollmentSignatureID'] : false;
        }

        $data = [
            'gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID,
            'signatureType' => $signatureType,
            'signatureData' => $signatureData,
            'signerName' => $signerName,
            'signedAt' => date('Y-m-d H:i:s'),
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];

        return $this->insert($data);
    }

    /**
     * Update an existing signature.
     *
     * @param int $gibbonChildEnrollmentSignatureID
     * @param string $signatureData
     * @param string $signerName
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return bool
     */
    public function updateSignature($gibbonChildEnrollmentSignatureID, $signatureData, $signerName, $ipAddress = null, $userAgent = null)
    {
        $data = [
            'signatureData' => $signatureData,
            'signerName' => $signerName,
            'signedAt' => date('Y-m-d H:i:s'),
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];

        return $this->update($gibbonChildEnrollmentSignatureID, $data);
    }

    /**
     * Delete a signature.
     *
     * @param int $gibbonChildEnrollmentSignatureID
     * @return bool
     */
    public function deleteSignature($gibbonChildEnrollmentSignatureID)
    {
        return $this->delete($gibbonChildEnrollmentSignatureID);
    }

    /**
     * Delete all signatures for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return bool
     */
    public function deleteSignaturesByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "DELETE FROM {$this->getTableName()}
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        return $this->db()->statement($sql, $data) !== false;
    }

    /**
     * Count signatures for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return int
     */
    public function countSignaturesByForm($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT COUNT(*) as count FROM {$this->getTableName()}
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID";

        $result = $this->db()->selectOne($sql, $data);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get signature status for a form (which signatures are present).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array
     */
    public function getSignatureStatus($gibbonChildEnrollmentFormID)
    {
        $signatures = $this->selectSignaturesByForm($gibbonChildEnrollmentFormID)->fetchAll();

        $status = [
            'Parent1' => null,
            'Parent2' => null,
            'Director' => null,
        ];

        foreach ($signatures as $sig) {
            $status[$sig['signatureType']] = [
                'signatureID' => $sig['gibbonChildEnrollmentSignatureID'],
                'signerName' => $sig['signerName'],
                'signedAt' => $sig['signedAt'],
            ];
        }

        return $status;
    }

    /**
     * Check if a form has all required signatures.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param bool $requireBothParents
     * @param bool $requireDirector
     * @return bool
     */
    public function hasRequiredSignatures($gibbonChildEnrollmentFormID, $requireBothParents = false, $requireDirector = true)
    {
        $status = $this->getSignatureStatus($gibbonChildEnrollmentFormID);

        // Must have at least Parent1 signature
        if ($status['Parent1'] === null) {
            return false;
        }

        // Check if both parents required
        if ($requireBothParents && $status['Parent2'] === null) {
            return false;
        }

        // Check if director required
        if ($requireDirector && $status['Director'] === null) {
            return false;
        }

        return true;
    }

    /**
     * Get missing signatures for a form.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @param bool $requireBothParents
     * @param bool $requireDirector
     * @return array
     */
    public function getMissingSignatures($gibbonChildEnrollmentFormID, $requireBothParents = false, $requireDirector = true)
    {
        $status = $this->getSignatureStatus($gibbonChildEnrollmentFormID);
        $missing = [];

        // Parent1 is always required
        if ($status['Parent1'] === null) {
            $missing[] = 'Parent1';
        }

        // Check if both parents required
        if ($requireBothParents && $status['Parent2'] === null) {
            $missing[] = 'Parent2';
        }

        // Check if director required
        if ($requireDirector && $status['Director'] === null) {
            $missing[] = 'Director';
        }

        return $missing;
    }

    /**
     * Get signature data for PDF export (without base64 for listing).
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array
     */
    public function getSignaturesForDisplay($gibbonChildEnrollmentFormID)
    {
        $data = ['gibbonChildEnrollmentFormID' => $gibbonChildEnrollmentFormID];
        $sql = "SELECT
                    gibbonChildEnrollmentSignatureID,
                    signatureType,
                    signerName,
                    signedAt,
                    ipAddress
                FROM {$this->getTableName()}
                WHERE gibbonChildEnrollmentFormID=:gibbonChildEnrollmentFormID
                ORDER BY FIELD(signatureType, 'Parent1', 'Parent2', 'Director')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get signature data including base64 data for PDF rendering.
     *
     * @param int $gibbonChildEnrollmentFormID
     * @return array
     */
    public function getSignaturesForPdf($gibbonChildEnrollmentFormID)
    {
        $signatures = $this->selectSignaturesByForm($gibbonChildEnrollmentFormID)->fetchAll();

        $result = [];
        foreach ($signatures as $sig) {
            $result[$sig['signatureType']] = [
                'signatureData' => $sig['signatureData'],
                'signerName' => $sig['signerName'],
                'signedAt' => $sig['signedAt'],
            ];
        }

        return $result;
    }

    /**
     * Query forms with signature statistics.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryFormsWithSignatureStatus(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonChildEnrollmentForm')
            ->cols([
                'gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID',
                'gibbonChildEnrollmentForm.formNumber',
                'gibbonChildEnrollmentForm.childFirstName',
                'gibbonChildEnrollmentForm.childLastName',
                'gibbonChildEnrollmentForm.status',
                'COUNT(gibbonChildEnrollmentSignature.gibbonChildEnrollmentSignatureID) as signatureCount',
                "SUM(CASE WHEN gibbonChildEnrollmentSignature.signatureType='Parent1' THEN 1 ELSE 0 END) as hasParent1",
                "SUM(CASE WHEN gibbonChildEnrollmentSignature.signatureType='Parent2' THEN 1 ELSE 0 END) as hasParent2",
                "SUM(CASE WHEN gibbonChildEnrollmentSignature.signatureType='Director' THEN 1 ELSE 0 END) as hasDirector",
            ])
            ->leftJoin('gibbonChildEnrollmentSignature', 'gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID=gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID')
            ->where('gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID']);

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonChildEnrollmentForm.status=:status')
                    ->bindValue('status', $status);
            },
            'missingSignatures' => function ($query, $value) {
                if ($value === 'Y') {
                    return $query
                        ->having('signatureCount < 1');
                }
                return $query;
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get signature statistics for a school year.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getSignatureStatistics($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(DISTINCT gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID) as totalForms,
                    SUM(CASE WHEN sig.signatureCount > 0 THEN 1 ELSE 0 END) as formsWithSignatures,
                    SUM(CASE WHEN sig.hasParent1 > 0 THEN 1 ELSE 0 END) as formsWithParent1,
                    SUM(CASE WHEN sig.hasParent2 > 0 THEN 1 ELSE 0 END) as formsWithParent2,
                    SUM(CASE WHEN sig.hasDirector > 0 THEN 1 ELSE 0 END) as formsWithDirector,
                    SUM(CASE WHEN sig.signatureCount >= 2 THEN 1 ELSE 0 END) as formsFullySigned
                FROM gibbonChildEnrollmentForm
                LEFT JOIN (
                    SELECT
                        gibbonChildEnrollmentFormID,
                        COUNT(*) as signatureCount,
                        SUM(CASE WHEN signatureType='Parent1' THEN 1 ELSE 0 END) as hasParent1,
                        SUM(CASE WHEN signatureType='Parent2' THEN 1 ELSE 0 END) as hasParent2,
                        SUM(CASE WHEN signatureType='Director' THEN 1 ELSE 0 END) as hasDirector
                    FROM gibbonChildEnrollmentSignature
                    GROUP BY gibbonChildEnrollmentFormID
                ) sig ON gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID = sig.gibbonChildEnrollmentFormID
                WHERE gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalForms' => 0,
            'formsWithSignatures' => 0,
            'formsWithParent1' => 0,
            'formsWithParent2' => 0,
            'formsWithDirector' => 0,
            'formsFullySigned' => 0,
        ];
    }

    /**
     * Get recent signatures across all forms.
     *
     * @param int $gibbonSchoolYearID
     * @param int $limit
     * @return array
     */
    public function getRecentSignatures($gibbonSchoolYearID, $limit = 10)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'limit' => $limit];
        $sql = "SELECT
                    gibbonChildEnrollmentSignature.gibbonChildEnrollmentSignatureID,
                    gibbonChildEnrollmentSignature.signatureType,
                    gibbonChildEnrollmentSignature.signerName,
                    gibbonChildEnrollmentSignature.signedAt,
                    gibbonChildEnrollmentForm.formNumber,
                    gibbonChildEnrollmentForm.childFirstName,
                    gibbonChildEnrollmentForm.childLastName
                FROM gibbonChildEnrollmentSignature
                INNER JOIN gibbonChildEnrollmentForm
                    ON gibbonChildEnrollmentSignature.gibbonChildEnrollmentFormID=gibbonChildEnrollmentForm.gibbonChildEnrollmentFormID
                WHERE gibbonChildEnrollmentForm.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY gibbonChildEnrollmentSignature.signedAt DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Validate signature data (check if base64 is valid).
     *
     * @param string $signatureData
     * @return bool
     */
    public function validateSignatureData($signatureData)
    {
        if (empty($signatureData)) {
            return false;
        }

        // Check for data URI prefix
        if (strpos($signatureData, 'data:image/') === 0) {
            // Extract base64 part
            $parts = explode(',', $signatureData, 2);
            if (count($parts) !== 2) {
                return false;
            }
            $signatureData = $parts[1];
        }

        // Try to decode
        $decoded = base64_decode($signatureData, true);
        return $decoded !== false && strlen($decoded) > 100; // Minimum size for a valid image
    }

    /**
     * Check if a signature type is valid.
     *
     * @param string $signatureType
     * @return bool
     */
    public function isValidSignatureType($signatureType)
    {
        return in_array($signatureType, [
            self::SIGNATURE_PARENT1,
            self::SIGNATURE_PARENT2,
            self::SIGNATURE_DIRECTOR,
        ], true);
    }

    /**
     * Get a human-readable label for a signature type.
     *
     * @param string $signatureType
     * @return string
     */
    public function getSignatureTypeLabel($signatureType)
    {
        $labels = [
            self::SIGNATURE_PARENT1 => __('Parent/Guardian 1'),
            self::SIGNATURE_PARENT2 => __('Parent/Guardian 2'),
            self::SIGNATURE_DIRECTOR => __('Director'),
        ];

        return $labels[$signatureType] ?? $signatureType;
    }

    /**
     * Get all valid signature types.
     *
     * @return array
     */
    public function getSignatureTypes()
    {
        return [
            self::SIGNATURE_PARENT1 => $this->getSignatureTypeLabel(self::SIGNATURE_PARENT1),
            self::SIGNATURE_PARENT2 => $this->getSignatureTypeLabel(self::SIGNATURE_PARENT2),
            self::SIGNATURE_DIRECTOR => $this->getSignatureTypeLabel(self::SIGNATURE_DIRECTOR),
        ];
    }
}
