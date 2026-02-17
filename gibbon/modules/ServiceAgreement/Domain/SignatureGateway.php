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

namespace Gibbon\Module\ServiceAgreement\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Service Agreement Signature Gateway
 *
 * Handles storage and verification of electronic signatures for Quebec FO-0659
 * Service Agreements. Captures signatures with timestamps, IP addresses, and
 * full audit trail for legal compliance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SignatureGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceAgreementSignature';
    private static $primaryKey = 'gibbonServiceAgreementSignatureID';

    private static $searchableColumns = [
        'gibbonServiceAgreementSignature.signerName',
        'gibbonServiceAgreementSignature.signerEmail',
        'gibbonServiceAgreementSignature.ipAddress',
    ];

    /**
     * Query signatures for a service agreement.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonServiceAgreementID
     * @return DataSet
     */
    public function querySignaturesByAgreement(QueryCriteria $criteria, $gibbonServiceAgreementID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementSignature.gibbonServiceAgreementSignatureID',
                'gibbonServiceAgreementSignature.gibbonServiceAgreementID',
                'gibbonServiceAgreementSignature.signerType',
                'gibbonServiceAgreementSignature.gibbonPersonID',
                'gibbonServiceAgreementSignature.signerName',
                'gibbonServiceAgreementSignature.signerEmail',
                'gibbonServiceAgreementSignature.signatureType',
                'gibbonServiceAgreementSignature.legalAcknowledgment',
                'gibbonServiceAgreementSignature.consumerProtectionAcknowledged',
                'gibbonServiceAgreementSignature.termsAccepted',
                'gibbonServiceAgreementSignature.signedDate',
                'gibbonServiceAgreementSignature.ipAddress',
                'gibbonServiceAgreementSignature.userAgent',
                'gibbonServiceAgreementSignature.verified',
                'gibbonServiceAgreementSignature.verifiedDate',
                'gibbonServiceAgreementSignature.timestampCreated',
                'signer.preferredName as signerPreferredName',
                'signer.surname as signerSurname',
                'signer.image_240 as signerImage',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as signer', 'gibbonServiceAgreementSignature.gibbonPersonID=signer.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonServiceAgreementSignature.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonServiceAgreementSignature.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID);

        $criteria->addFilterRules([
            'signerType' => function ($query, $signerType) {
                return $query
                    ->where('gibbonServiceAgreementSignature.signerType=:signerType')
                    ->bindValue('signerType', $signerType);
            },
            'verified' => function ($query, $verified) {
                return $query
                    ->where('gibbonServiceAgreementSignature.verified=:verified')
                    ->bindValue('verified', $verified);
            },
            'signedDate' => function ($query, $signedDate) {
                return $query
                    ->where('DATE(gibbonServiceAgreementSignature.signedDate)=:signedDate')
                    ->bindValue('signedDate', $signedDate);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get all signatures for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return \Gibbon\Database\Result
     */
    public function selectSignaturesByAgreement($gibbonServiceAgreementID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementSignature.*',
                'signer.preferredName as signerPreferredName',
                'signer.surname as signerSurname',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
            ])
            ->leftJoin('gibbonPerson as signer', 'gibbonServiceAgreementSignature.gibbonPersonID=signer.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonServiceAgreementSignature.verifiedByID=verifiedBy.gibbonPersonID')
            ->where('gibbonServiceAgreementSignature.gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->orderBy(['gibbonServiceAgreementSignature.signedDate ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get a signature by signer type for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @param string $signerType Parent, Provider, or Witness
     * @return array|false
     */
    public function getSignatureByType($gibbonServiceAgreementID, $signerType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonServiceAgreementID=:gibbonServiceAgreementID')
            ->bindValue('gibbonServiceAgreementID', $gibbonServiceAgreementID)
            ->where('signerType=:signerType')
            ->bindValue('signerType', $signerType);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Get signature with full audit details.
     *
     * @param int $gibbonServiceAgreementSignatureID
     * @return array|false
     */
    public function getSignatureWithAuditDetails($gibbonServiceAgreementSignatureID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementSignature.*',
                'signer.preferredName as signerPreferredName',
                'signer.surname as signerSurname',
                'signer.email as signerPersonEmail',
                'verifiedBy.preferredName as verifiedByName',
                'verifiedBy.surname as verifiedBySurname',
                'agreement.agreementNumber',
                'agreement.childName',
                'agreement.parentName',
                'agreement.status as agreementStatus',
            ])
            ->leftJoin('gibbonPerson as signer', 'gibbonServiceAgreementSignature.gibbonPersonID=signer.gibbonPersonID')
            ->leftJoin('gibbonPerson as verifiedBy', 'gibbonServiceAgreementSignature.verifiedByID=verifiedBy.gibbonPersonID')
            ->innerJoin('gibbonServiceAgreement as agreement', 'gibbonServiceAgreementSignature.gibbonServiceAgreementID=agreement.gibbonServiceAgreementID')
            ->where('gibbonServiceAgreementSignature.gibbonServiceAgreementSignatureID=:gibbonServiceAgreementSignatureID')
            ->bindValue('gibbonServiceAgreementSignatureID', $gibbonServiceAgreementSignatureID);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : false;
    }

    /**
     * Store a new electronic signature with audit trail.
     *
     * @param int $gibbonServiceAgreementID
     * @param string $signerType Parent, Provider, or Witness
     * @param string $signerName Legal name of signer
     * @param string $signatureData Base64 encoded signature data
     * @param string $signatureType Drawn, Typed, or Image
     * @param string $ipAddress IP address of signer
     * @param int|null $gibbonPersonID Person ID if in system
     * @param string|null $signerEmail Signer's email
     * @param string|null $userAgent Browser/device info
     * @param string|null $sessionID Session identifier
     * @param string|null $geoLocation Geographic location if available
     * @param string|null $deviceFingerprint Device identification
     * @param bool $legalAcknowledgment Acknowledged legal binding
     * @param bool $consumerProtectionAcknowledged Acknowledged Consumer Protection Act
     * @param bool $termsAccepted Accepted terms and conditions
     * @param string|null $verificationHash SHA-256 hash of agreement
     * @return int|false
     */
    public function storeSignature(
        $gibbonServiceAgreementID,
        $signerType,
        $signerName,
        $signatureData,
        $signatureType,
        $ipAddress,
        $gibbonPersonID = null,
        $signerEmail = null,
        $userAgent = null,
        $sessionID = null,
        $geoLocation = null,
        $deviceFingerprint = null,
        $legalAcknowledgment = false,
        $consumerProtectionAcknowledged = false,
        $termsAccepted = false,
        $verificationHash = null
    ) {
        return $this->insert([
            'gibbonServiceAgreementID' => $gibbonServiceAgreementID,
            'signerType' => $signerType,
            'gibbonPersonID' => $gibbonPersonID,
            'signerName' => $signerName,
            'signerEmail' => $signerEmail,
            'signatureData' => $signatureData,
            'signatureType' => $signatureType,
            'legalAcknowledgment' => $legalAcknowledgment ? 'Y' : 'N',
            'consumerProtectionAcknowledged' => $consumerProtectionAcknowledged ? 'Y' : 'N',
            'termsAccepted' => $termsAccepted ? 'Y' : 'N',
            'signedDate' => date('Y-m-d H:i:s'),
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'sessionID' => $sessionID,
            'geoLocation' => $geoLocation,
            'deviceFingerprint' => $deviceFingerprint,
            'verificationHash' => $verificationHash,
            'verified' => 'N',
        ]);
    }

    /**
     * Verify a signature.
     *
     * @param int $gibbonServiceAgreementSignatureID
     * @param int $verifiedByID Person ID who verified
     * @return bool
     */
    public function verifySignature($gibbonServiceAgreementSignatureID, $verifiedByID)
    {
        return $this->update($gibbonServiceAgreementSignatureID, [
            'verified' => 'Y',
            'verifiedDate' => date('Y-m-d H:i:s'),
            'verifiedByID' => $verifiedByID,
        ]);
    }

    /**
     * Check if a signature's verification hash matches.
     *
     * @param int $gibbonServiceAgreementSignatureID
     * @param string $hashToVerify Hash to compare against stored hash
     * @return bool
     */
    public function verifySignatureHash($gibbonServiceAgreementSignatureID, $hashToVerify)
    {
        $signature = $this->getByID($gibbonServiceAgreementSignatureID);
        if (!$signature || empty($signature['verificationHash'])) {
            return false;
        }
        return hash_equals($signature['verificationHash'], $hashToVerify);
    }

    /**
     * Check if all required signatures are complete for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @param bool $requireWitness Whether witness signature is required
     * @return bool
     */
    public function areAllSignaturesComplete($gibbonServiceAgreementID, $requireWitness = false)
    {
        $signatures = $this->selectSignaturesByAgreement($gibbonServiceAgreementID)->fetchAll();

        $hasParent = false;
        $hasProvider = false;
        $hasWitness = false;

        foreach ($signatures as $signature) {
            if ($signature['signerType'] === 'Parent') {
                $hasParent = true;
            } elseif ($signature['signerType'] === 'Provider') {
                $hasProvider = true;
            } elseif ($signature['signerType'] === 'Witness') {
                $hasWitness = true;
            }
        }

        if ($requireWitness) {
            return $hasParent && $hasProvider && $hasWitness;
        }

        return $hasParent && $hasProvider;
    }

    /**
     * Get signature count by type for an agreement.
     *
     * @param int $gibbonServiceAgreementID
     * @return array
     */
    public function getSignatureCountsByAgreement($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT
                    signerType,
                    COUNT(*) as count,
                    SUM(CASE WHEN verified='Y' THEN 1 ELSE 0 END) as verifiedCount
                FROM gibbonServiceAgreementSignature
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID
                GROUP BY signerType
                ORDER BY FIELD(signerType, 'Parent', 'Provider', 'Witness')";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Get signatures by person across all agreements.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectSignaturesByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementSignature.*',
                'agreement.agreementNumber',
                'agreement.childName',
                'agreement.status as agreementStatus',
            ])
            ->innerJoin('gibbonServiceAgreement as agreement', 'gibbonServiceAgreementSignature.gibbonServiceAgreementID=agreement.gibbonServiceAgreementID')
            ->where('gibbonServiceAgreementSignature.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->orderBy(['gibbonServiceAgreementSignature.signedDate DESC']);

        return $this->runSelect($query);
    }

    /**
     * Select signatures from a specific IP address for fraud detection.
     *
     * @param string $ipAddress
     * @param string|null $startDate Optional start date for range
     * @param string|null $endDate Optional end date for range
     * @return \Gibbon\Database\Result
     */
    public function selectSignaturesByIPAddress($ipAddress, $startDate = null, $endDate = null)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceAgreementSignature.*',
                'agreement.agreementNumber',
                'agreement.childName',
                'agreement.parentName',
            ])
            ->innerJoin('gibbonServiceAgreement as agreement', 'gibbonServiceAgreementSignature.gibbonServiceAgreementID=agreement.gibbonServiceAgreementID')
            ->where('gibbonServiceAgreementSignature.ipAddress=:ipAddress')
            ->bindValue('ipAddress', $ipAddress);

        if ($startDate !== null) {
            $query
                ->where('gibbonServiceAgreementSignature.signedDate >= :startDate')
                ->bindValue('startDate', $startDate);
        }

        if ($endDate !== null) {
            $query
                ->where('gibbonServiceAgreementSignature.signedDate <= :endDate')
                ->bindValue('endDate', $endDate);
        }

        $query->orderBy(['gibbonServiceAgreementSignature.signedDate DESC']);

        return $this->runSelect($query);
    }

    /**
     * Get signature audit log for compliance reporting.
     *
     * @param int $gibbonServiceAgreementID
     * @return array
     */
    public function getSignatureAuditLog($gibbonServiceAgreementID)
    {
        $data = ['gibbonServiceAgreementID' => $gibbonServiceAgreementID];
        $sql = "SELECT
                    gibbonServiceAgreementSignatureID,
                    signerType,
                    signerName,
                    signerEmail,
                    signatureType,
                    signedDate,
                    ipAddress,
                    userAgent,
                    sessionID,
                    geoLocation,
                    deviceFingerprint,
                    legalAcknowledgment,
                    consumerProtectionAcknowledged,
                    termsAccepted,
                    verificationHash,
                    verified,
                    verifiedDate,
                    timestampCreated
                FROM gibbonServiceAgreementSignature
                WHERE gibbonServiceAgreementID=:gibbonServiceAgreementID
                ORDER BY signedDate ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Generate SHA-256 verification hash for agreement content.
     *
     * @param array $agreementData Agreement data to hash
     * @return string SHA-256 hash
     */
    public function generateVerificationHash(array $agreementData)
    {
        // Sort keys for consistent hashing
        ksort($agreementData);
        $content = json_encode($agreementData, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $content);
    }

    /**
     * Check if signature already exists for agreement and signer type.
     *
     * @param int $gibbonServiceAgreementID
     * @param string $signerType
     * @return bool
     */
    public function hasSignature($gibbonServiceAgreementID, $signerType)
    {
        $signature = $this->getSignatureByType($gibbonServiceAgreementID, $signerType);
        return $signature !== false;
    }

    /**
     * Get pending signatures (not yet verified).
     *
     * @param int $gibbonSchoolYearID
     * @return \Gibbon\Database\Result
     */
    public function selectPendingVerificationSignatures($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    sig.gibbonServiceAgreementSignatureID,
                    sig.signerType,
                    sig.signerName,
                    sig.signerEmail,
                    sig.signedDate,
                    sig.ipAddress,
                    agreement.gibbonServiceAgreementID,
                    agreement.agreementNumber,
                    agreement.childName,
                    agreement.parentName
                FROM gibbonServiceAgreementSignature sig
                INNER JOIN gibbonServiceAgreement agreement
                    ON sig.gibbonServiceAgreementID=agreement.gibbonServiceAgreementID
                WHERE agreement.gibbonSchoolYearID=:gibbonSchoolYearID
                AND sig.verified='N'
                ORDER BY sig.signedDate ASC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get signature statistics for reporting.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    public function getSignatureStatsBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT
                    COUNT(*) as totalSignatures,
                    SUM(CASE WHEN sig.signerType='Parent' THEN 1 ELSE 0 END) as parentSignatures,
                    SUM(CASE WHEN sig.signerType='Provider' THEN 1 ELSE 0 END) as providerSignatures,
                    SUM(CASE WHEN sig.signerType='Witness' THEN 1 ELSE 0 END) as witnessSignatures,
                    SUM(CASE WHEN sig.verified='Y' THEN 1 ELSE 0 END) as verifiedSignatures,
                    SUM(CASE WHEN sig.signatureType='Drawn' THEN 1 ELSE 0 END) as drawnSignatures,
                    SUM(CASE WHEN sig.signatureType='Typed' THEN 1 ELSE 0 END) as typedSignatures,
                    SUM(CASE WHEN sig.signatureType='Image' THEN 1 ELSE 0 END) as imageSignatures
                FROM gibbonServiceAgreementSignature sig
                INNER JOIN gibbonServiceAgreement agreement
                    ON sig.gibbonServiceAgreementID=agreement.gibbonServiceAgreementID
                WHERE agreement.gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->db()->selectOne($sql, $data) ?: [
            'totalSignatures' => 0,
            'parentSignatures' => 0,
            'providerSignatures' => 0,
            'witnessSignatures' => 0,
            'verifiedSignatures' => 0,
            'drawnSignatures' => 0,
            'typedSignatures' => 0,
            'imageSignatures' => 0,
        ];
    }

    /**
     * Delete a signature (use with caution - for draft agreements only).
     *
     * @param int $gibbonServiceAgreementSignatureID
     * @return bool
     */
    public function deleteSignature($gibbonServiceAgreementSignatureID)
    {
        return $this->delete($gibbonServiceAgreementSignatureID);
    }
}
