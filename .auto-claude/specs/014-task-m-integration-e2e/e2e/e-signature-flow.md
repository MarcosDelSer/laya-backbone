# E-Signature Flow E2E Documentation

## Overview

This document describes the end-to-end flow for electronic document signing in the LAYA childcare management system. The workflow covers four major interactions: document upload, parent signature capture via canvas UI, cryptographic timestamp storage, and signature verification/validation.

## System Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Admin UI      │     │     Gibbon      │     │   AI Service    │
│  (Gibbon Web)   │────▶│   (PHP/MySQL)   │────▶│    (FastAPI)    │
└─────────────────┘     └────────┬────────┘     └─────────────────┘
                                │
                                │ API Calls
                                ▼
                       ┌─────────────────┐
                       │  Parent Portal  │
                       │   (Next.js)     │
                       │ SignatureCanvas │
                       └─────────────────┘
```

### Services Involved

| Service | Tech Stack | Port | Role |
|---------|------------|------|------|
| gibbon | PHP 8.1+ / MySQL | 80/8080 | Backend, document storage, signature records |
| parent-portal | Next.js 14 | 3000 | Parent-facing document view and signature UI |
| ai-service | FastAPI / PostgreSQL | 8000 | Optional webhooks for audit trail |

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `SignatureCanvas` | `parent-portal/components/SignatureCanvas.tsx` | Canvas-based signature capture |
| `DocumentSignature` | `parent-portal/components/DocumentSignature.tsx` | Modal for document signing workflow |
| `DocumentCard` | `parent-portal/components/DocumentCard.tsx` | Document display with status badges |
| `gibbon-client.ts` | `parent-portal/lib/gibbon-client.ts` | API client for document operations |

---

## Step 1: Document Upload

### User Action
Administrator uploads a document requiring parent signature from the Gibbon admin interface.

### Flow Diagram

```
Admin selects "Add Document" → Uploads PDF → Assigns to family → Document created
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Admin UI                                                  │
│ Modules > Document Management > Add Document                     │
│                                                                  │
│ 1. Upload PDF file                                               │
│ 2. Enter document title and type                                 │
│ 3. Select target families/children                               │
│ 4. Set signature requirement (required/optional)                 │
│ 5. Set expiry date (if applicable)                               │
│ 6. Click "Create Document"                                       │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Document Management Module (Gibbon)                              │
│ 1. Validate PDF file (type, size, security scan)                │
│ 2. Generate unique document ID (DOC-YYYYMMDD-NNNNNN)             │
│ 3. Store PDF in secure file system                               │
│ 4. Insert into gibbonDocument table                              │
│ 5. Create gibbonDocumentSignature record (status: pending)       │
│ 6. Trigger notification via NotificationEngine                   │
│ 7. Optional: AISync webhook for audit trail                      │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ NotificationEngine Module                                        │
│ 1. Create notification for parent                                │
│ 2. Queue email with document link                                │
│ 3. Send push notification to Parent Portal                       │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Upload Document (Admin → Gibbon)

```http
POST /modules/DocumentManagement/document_addProcess.php
Content-Type: multipart/form-data
Authorization: Session cookie

------WebKitFormBoundary
Content-Disposition: form-data; name="documentFile"; filename="enrollment-agreement.pdf"
Content-Type: application/pdf

[PDF binary data]
------WebKitFormBoundary
Content-Disposition: form-data; name="title"

Enrollment Agreement 2025-2026
------WebKitFormBoundary
Content-Disposition: form-data; name="type"

Enrollment
------WebKitFormBoundary
Content-Disposition: form-data; name="gibbonFamilyID"

100
------WebKitFormBoundary
Content-Disposition: form-data; name="signatureRequired"

Y
------WebKitFormBoundary
Content-Disposition: form-data; name="expiryDate"

2026-08-31
------WebKitFormBoundary--
```

**Response (redirect on success):**
```
Location: /modules/DocumentManagement/document_view.php?gibbonDocumentID=1001
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonDocument` | INSERT | documentID, title, type, filePath, gibbonFamilyID, uploadedBy, uploadDate, signatureRequired |
| `gibbonDocumentSignature` | INSERT | gibbonDocumentID, gibbonPersonID (parent), status='pending', createdAt |
| `gibbonNotification` | INSERT | type='document_uploaded', recipient, documentID |

### Document Types

| Type | Description | Common Documents |
|------|-------------|------------------|
| `Enrollment` | Enrollment and registration | Enrollment Agreement, Registration Form |
| `Consent Form` | Permission and consent | Photo Release, Field Trip Permission |
| `Medical` | Health-related | Emergency Medical Authorization, Allergy Form |
| `Policy` | Policy acknowledgments | Parent Handbook, Code of Conduct |
| `Health` | Health information | Dietary Information, Immunization Records |
| `Legal` | Legal documents | Custody Agreements, Authorization Forms |

### Document Status Lifecycle

```
┌──────────┐   Upload   ┌──────────┐   Parent Signs   ┌──────────┐
│ Pending  │ ─────────▶ │ Uploaded │ ───────────────▶ │  Signed  │
└──────────┘            └──────────┘                  └──────────┘
                              │                            │
                              │ Expired                    │ Revoked
                              ▼                            ▼
                        ┌──────────┐                ┌──────────┐
                        │ Expired  │                │ Revoked  │
                        └──────────┘                └──────────┘
```

| Status | Description |
|--------|-------------|
| `pending` | Document uploaded, awaiting parent signature |
| `signed` | Parent has signed the document |
| `expired` | Document passed expiry date without signature |
| `revoked` | Document/signature revoked by administrator |

### Expected Outcome
- Document created with unique ID
- Parent notified via email and push notification
- Document visible in Parent Portal "Documents" section
- Pending signature count updated

---

## Step 2: Parent Signs Document

### User Action
Parent logs into Parent Portal, views the document, and signs using the signature canvas.

### Flow Diagram

```
Parent navigates to Documents → Clicks "Sign Document" → Draws signature → Submits
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal (Next.js)                                          │
│ /documents page                                                  │
│ 1. Fetch documents from Gibbon API                               │
│ 2. Display DocumentCard with status badges                       │
│ 3. Parent clicks "Sign Document" on pending document             │
│ 4. DocumentSignature modal opens                                 │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ DocumentSignature Modal                                          │
│ 1. Display document preview link (View PDF)                      │
│ 2. Show SignatureCanvas component                                │
│ 3. Parent draws signature using mouse/touch                      │
│ 4. Parent agrees to terms checkbox                               │
│ 5. Parent clicks "Submit Signature"                              │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ SignatureCanvas Component                                        │
│ 1. Canvas initialized with high DPI support                      │
│ 2. Captures mouse/touch drawing events                           │
│ 3. Converts signature to PNG data URL (base64)                   │
│ 4. Returns signature data to parent component                    │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal → Gibbon API                                       │
│ POST /api/v1/documents/{id}/sign                                 │
│ Body: { signature_data: "<base64 PNG data>" }                    │
│ Authorization: Bearer <parent_jwt_token>                         │
└─────────────────────────────────────────────────────────────────┘
```

### SignatureCanvas Technical Details

The `SignatureCanvas` component (`parent-portal/components/SignatureCanvas.tsx`) provides:

**Features:**
- High DPI display support (`window.devicePixelRatio`)
- Mouse and touch event handling
- Smooth stroke rendering with `lineCap: 'round'` and `lineJoin: 'round'`
- Clear functionality to reset signature
- PNG data URL export via `canvas.toDataURL('image/png')`

**Props:**
```typescript
interface SignatureCanvasProps {
  onSignatureChange: (hasSignature: boolean, dataUrl: string | null) => void;
  width?: number;      // Default: 400
  height?: number;     // Default: 200
  penColor?: string;   // Default: '#1f2937' (dark gray)
  penWidth?: number;   // Default: 2
}
```

**Signature Data Format:**
```
data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...
```

### API Calls

#### 1. Parent Portal → Gibbon (Fetch Documents)

```http
GET /api/v1/documents?status=pending
Authorization: Bearer <parent_jwt_token>
```

**Response:**
```json
{
  "items": [
    {
      "id": "doc-001",
      "title": "Enrollment Agreement 2025-2026",
      "type": "Enrollment",
      "uploadDate": "2026-02-01T10:00:00Z",
      "status": "pending",
      "pdfUrl": "/api/v1/documents/doc-001/pdf",
      "signatureRequired": true,
      "expiryDate": "2026-08-31"
    },
    {
      "id": "doc-002",
      "title": "Photo & Video Release Consent",
      "type": "Consent Form",
      "uploadDate": "2026-02-01T10:00:00Z",
      "status": "pending",
      "pdfUrl": "/api/v1/documents/doc-002/pdf",
      "signatureRequired": true
    }
  ],
  "total": 2,
  "skip": 0,
  "limit": 20
}
```

#### 2. Parent Portal → Gibbon (Submit Signature)

```http
POST /api/v1/documents/doc-001/sign
Content-Type: application/json
Authorization: Bearer <parent_jwt_token>

{
  "signature_data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAA..."
}
```

**Response:**
```json
{
  "id": "doc-001",
  "title": "Enrollment Agreement 2025-2026",
  "type": "Enrollment",
  "uploadDate": "2026-02-01T10:00:00Z",
  "status": "signed",
  "signedAt": "2026-02-15T14:30:00Z",
  "signatureUrl": "/api/v1/documents/doc-001/signature",
  "pdfUrl": "/api/v1/documents/doc-001/pdf",
  "signatureTimestamp": {
    "timestamp": "2026-02-15T14:30:00.123Z",
    "hash": "sha256:a1b2c3d4e5f6...",
    "ipAddress": "192.168.1.100",
    "userAgent": "Mozilla/5.0..."
  }
}
```

### Parent Portal UI Components

#### Documents Page (`/documents/page.tsx`)

```
┌──────────────────────────────────────────────────────────────┐
│ Documents                                                     │
│ Review and sign required documents for your child            │
├──────────────────────────────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐ ┌────────────┐                │
│ │   Total    │ │  Pending   │ │   Signed   │                │
│ │     6      │ │     3      │ │     3      │                │
│ │ documents  │ │ signatures │ │ documents  │                │
│ └────────────┘ └────────────┘ └────────────┘                │
├──────────────────────────────────────────────────────────────┤
│ ⚠️ Action Required                                           │
│ You have 3 documents requiring your signature.               │
├──────────────────────────────────────────────────────────────┤
│ Document Library                     [All] [Pending] [Signed]│
│                                                              │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ 📋 Enrollment Agreement 2025-2026             [Pending]  ││
│ │ Type: Enrollment                                          ││
│ │ Uploaded: Feb 1, 2026                                     ││
│ │                                                          ││
│ │ [View Document]  [Sign Document]                          ││
│ └──────────────────────────────────────────────────────────┘│
│                                                              │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ ✅ Parent Handbook Acknowledgment             [Signed]   ││
│ │ Type: Policy                                              ││
│ │ Uploaded: Jan 10, 2026                                    ││
│ │ ✓ Signed on Jan 12, 2026 at 4:45 PM                      ││
│ │                                                          ││
│ │ [View Document]  [View Signature]                         ││
│ └──────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

#### Signature Modal (`DocumentSignature.tsx`)

```
┌──────────────────────────────────────────────────────────────┐
│ Sign Document                                            [×] │
│ Enrollment Agreement 2025-2026                               │
├──────────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────────────────────┐│
│ │ 📄 Enrollment Agreement 2025-2026                        ││
│ │    Enrollment                        [View PDF →]        ││
│ └──────────────────────────────────────────────────────────┘│
│                                                              │
│ Your Signature                                               │
│ ┌────────────────────────────────────────────────────────┐  │
│ │                                                        │  │
│ │                    Sign here                           │  │
│ │                                                        │  │
│ │ ×______________________________________________________│  │
│ └────────────────────────────────────────────────────────┘  │
│                    Draw your signature above       [Clear]   │
│                                                              │
│ ☑ I acknowledge that I have read and understand this        │
│   document. By signing below, I agree to be legally         │
│   bound by its terms and conditions.                        │
│                                                              │
│ Your signature will be timestamped with the current date    │
│ and time for verification purposes.                         │
├──────────────────────────────────────────────────────────────┤
│                              [Cancel]  [✓ Submit Signature]  │
└──────────────────────────────────────────────────────────────┘
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonDocumentSignature` | UPDATE | status='signed', signedAt, signatureData (base64), signatureHash, signatureTimestamp |
| `gibbonAISyncLog` | INSERT | eventType='document_signed', entityType='signature' |
| `gibbonNotification` | INSERT | type='document_signed', recipient (admin) |

### Expected Outcome
- Signature captured and stored securely
- Document status changed to "signed"
- Cryptographic timestamp recorded
- Signed document available with signature overlay
- Administrator notified of signature completion

---

## Step 3: Cryptographic Timestamp Storage

### Overview

When a parent signs a document, the system creates a cryptographic timestamp record that cannot be tampered with. This ensures legal validity and non-repudiation of the signature.

### Timestamp Data Structure

```json
{
  "signatureTimestamp": {
    "timestamp": "2026-02-15T14:30:00.123Z",
    "timestampISO": "2026-02-15T14:30:00.123+00:00",
    "timestampUnix": 1771080600123,
    "hash": "sha256:a1b2c3d4e5f6g7h8i9j0...",
    "signatureHash": "sha256:k1l2m3n4o5p6q7r8s9t0...",
    "documentHash": "sha256:u1v2w3x4y5z6a7b8c9d0...",
    "ipAddress": "192.168.1.100",
    "userAgent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)...",
    "parentID": 12345,
    "parentName": "Jean-Pierre Martin",
    "parentEmail": "jp.martin@email.com",
    "sessionID": "sess_abc123xyz...",
    "serverTimestamp": "2026-02-15T14:30:00.456Z",
    "timezone": "America/Montreal"
  }
}
```

### Hash Generation Process

```
┌─────────────────────────────────────────────────────────────────┐
│ Signature Submission                                             │
│ 1. Receive signature PNG data (base64)                          │
│ 2. Generate signature hash: SHA-256(signature_data)             │
│ 3. Generate document hash: SHA-256(original_pdf_content)        │
│ 4. Generate combined hash: SHA-256(signature_hash + doc_hash)   │
│ 5. Create timestamp record with all metadata                     │
│ 6. Store hash in database                                        │
│ 7. Optional: Submit to RFC 3161 Time Stamping Authority (TSA)   │
└─────────────────────────────────────────────────────────────────┘
```

### Database Schema

```sql
CREATE TABLE `gibbonDocumentSignature` (
    `gibbonDocumentSignatureID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonDocumentID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Parent who signed',
    `status` ENUM('pending','signed','revoked') NOT NULL DEFAULT 'pending',

    -- Signature Data
    `signatureData` LONGTEXT NULL COMMENT 'Base64 PNG signature image',
    `signatureHash` VARCHAR(128) NULL COMMENT 'SHA-256 hash of signature data',

    -- Document Integrity
    `documentHash` VARCHAR(128) NULL COMMENT 'SHA-256 hash of original PDF',
    `combinedHash` VARCHAR(128) NULL COMMENT 'SHA-256(signature_hash + doc_hash)',

    -- Timestamp Data
    `signedAt` DATETIME NULL COMMENT 'Timestamp when signature was captured',
    `serverTimestamp` DATETIME(3) NULL COMMENT 'Server-side timestamp with milliseconds',
    `timestampUnix` BIGINT NULL COMMENT 'Unix timestamp in milliseconds',
    `timezone` VARCHAR(50) NULL COMMENT 'Parent timezone',

    -- Signer Information
    `ipAddress` VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
    `userAgent` TEXT NULL COMMENT 'Browser user agent string',
    `sessionID` VARCHAR(255) NULL COMMENT 'Session identifier',

    -- Verification
    `tsaResponse` LONGTEXT NULL COMMENT 'RFC 3161 TSA response (optional)',
    `verificationStatus` ENUM('pending','verified','failed') DEFAULT 'pending',
    `lastVerifiedAt` DATETIME NULL,

    -- Audit
    `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`gibbonDocumentID`) REFERENCES `gibbonDocument`(`gibbonDocumentID`),
    FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson`(`gibbonPersonID`),
    INDEX `idx_document` (`gibbonDocumentID`),
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_status` (`status`),
    INDEX `idx_signed_at` (`signedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Gibbon Backend Processing

```php
// From SignatureService.php (conceptual implementation)
class SignatureService
{
    /**
     * Process and store a signature with cryptographic timestamp.
     */
    public function signDocument(int $documentID, int $parentID, string $signatureData): array
    {
        // 1. Validate signature data
        if (!$this->isValidBase64Png($signatureData)) {
            throw new InvalidArgumentException('Invalid signature format');
        }

        // 2. Get original document
        $document = $this->documentGateway->getByID($documentID);
        $pdfContent = file_get_contents($document['filePath']);

        // 3. Generate hashes
        $signatureHash = hash('sha256', $signatureData);
        $documentHash = hash('sha256', $pdfContent);
        $combinedHash = hash('sha256', $signatureHash . $documentHash);

        // 4. Create timestamp record
        $timestamp = [
            'signedAt' => date('Y-m-d H:i:s'),
            'serverTimestamp' => microtime(true),
            'timestampUnix' => (int)(microtime(true) * 1000),
            'timezone' => date_default_timezone_get(),
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'sessionID' => session_id(),
        ];

        // 5. Store signature record
        $signatureID = $this->signatureGateway->insert([
            'gibbonDocumentID' => $documentID,
            'gibbonPersonID' => $parentID,
            'status' => 'signed',
            'signatureData' => $signatureData,
            'signatureHash' => $signatureHash,
            'documentHash' => $documentHash,
            'combinedHash' => $combinedHash,
            ...$timestamp,
        ]);

        // 6. Optional: Submit to TSA for RFC 3161 timestamp
        if ($this->tsaEnabled) {
            $tsaResponse = $this->submitToTSA($combinedHash);
            $this->signatureGateway->update($signatureID, [
                'tsaResponse' => $tsaResponse,
                'verificationStatus' => 'verified',
            ]);
        }

        // 7. Trigger AISync webhook
        $this->aiSyncService->syncSignatureEvent($signatureID, $documentID);

        // 8. Send notifications
        $this->notificationService->notifyDocumentSigned($documentID, $parentID);

        return [
            'signatureID' => $signatureID,
            'signedAt' => $timestamp['signedAt'],
            'combinedHash' => $combinedHash,
        ];
    }
}
```

### RFC 3161 Time Stamping Authority (Optional)

For legal compliance, signatures can be submitted to a trusted Time Stamping Authority (TSA):

```php
/**
 * Submit hash to RFC 3161 TSA for cryptographic timestamp.
 */
public function submitToTSA(string $hash): string
{
    $tsaUrl = getenv('TSA_URL') ?: 'https://freetsa.org/tsr';

    // Create timestamp request
    $request = $this->createTimestampRequest($hash);

    // Submit to TSA
    $client = new Client();
    $response = $client->post($tsaUrl, [
        'headers' => ['Content-Type' => 'application/timestamp-query'],
        'body' => $request,
    ]);

    // Validate response
    $tsaResponse = $response->getBody()->getContents();
    if (!$this->validateTSAResponse($tsaResponse)) {
        throw new TSAException('Invalid TSA response');
    }

    return base64_encode($tsaResponse);
}
```

### AISync Webhook (Document Signed Event)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: document_signed

{
  "event_type": "document_signed",
  "entity_type": "signature",
  "entity_id": "1001",
  "payload": {
    "document_id": "doc-001",
    "document_title": "Enrollment Agreement 2025-2026",
    "parent_id": 12345,
    "parent_name": "Jean-Pierre Martin",
    "signed_at": "2026-02-15T14:30:00Z",
    "signature_hash": "sha256:a1b2c3d4e5f6...",
    "combined_hash": "sha256:u1v2w3x4y5z6...",
    "ip_address": "192.168.1.100",
    "verification_status": "verified"
  },
  "timestamp": "2026-02-15T14:30:05Z"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Document signed event for doc-001 recorded",
  "event_type": "document_signed",
  "entity_id": "1001",
  "received_at": "2026-02-15T19:30:05.123Z",
  "processing_time_ms": 12.34
}
```

### Expected Outcome
- Signature hash computed and stored
- Document hash computed and stored
- Combined hash created for tamper detection
- Server timestamp recorded with millisecond precision
- Client metadata captured (IP, user agent)
- Optional TSA timestamp obtained for legal validity

---

## Step 4: Signature Verification

### Overview

Signature verification ensures that:
1. The signature has not been tampered with
2. The document has not been modified since signing
3. The timestamp is authentic and cannot be forged
4. The signer's identity can be confirmed

### Verification Flow

```
Verification request received
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Step 1: Retrieve Signature Record                                │
│ SELECT * FROM gibbonDocumentSignature WHERE id = ?               │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Step 2: Recompute Hashes                                         │
│ 1. Hash current signature data: SHA-256(signature_data)         │
│ 2. Hash current document: SHA-256(pdf_content)                  │
│ 3. Compute combined: SHA-256(sig_hash + doc_hash)               │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Step 3: Compare Hashes                                           │
│ 1. Compare signature hash with stored value                     │
│ 2. Compare document hash with stored value                      │
│ 3. Compare combined hash with stored value                      │
│ 4. If any mismatch → VERIFICATION FAILED (tampering detected)  │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Step 4: Verify TSA Timestamp (if available)                      │
│ 1. Parse TSA response                                            │
│ 2. Verify TSA certificate chain                                  │
│ 3. Compare embedded hash with stored combined hash              │
│ 4. Verify timestamp is within acceptable range                   │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Step 5: Return Verification Result                               │
│ { status: 'verified' | 'failed', details: {...} }               │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Verify Signature (Admin/API → Gibbon)

```http
GET /api/v1/documents/doc-001/verify
Authorization: Bearer <jwt_token>
```

**Response (Success):**
```json
{
  "verified": true,
  "status": "verified",
  "document": {
    "id": "doc-001",
    "title": "Enrollment Agreement 2025-2026",
    "type": "Enrollment"
  },
  "signature": {
    "signedAt": "2026-02-15T14:30:00Z",
    "signedBy": {
      "id": 12345,
      "name": "Jean-Pierre Martin",
      "email": "jp.martin@email.com"
    }
  },
  "verification": {
    "signatureIntegrity": "valid",
    "documentIntegrity": "valid",
    "timestampValid": true,
    "tsaVerified": true,
    "verifiedAt": "2026-02-15T16:00:00Z"
  },
  "hashes": {
    "signatureHashMatch": true,
    "documentHashMatch": true,
    "combinedHashMatch": true
  },
  "metadata": {
    "ipAddress": "192.168.1.100",
    "userAgent": "Mozilla/5.0...",
    "timezone": "America/Montreal"
  }
}
```

**Response (Failure - Document Modified):**
```json
{
  "verified": false,
  "status": "failed",
  "error": "DOCUMENT_TAMPERED",
  "message": "Document has been modified since signing",
  "verification": {
    "signatureIntegrity": "valid",
    "documentIntegrity": "invalid",
    "timestampValid": true,
    "tsaVerified": true,
    "verifiedAt": "2026-02-15T16:00:00Z"
  },
  "hashes": {
    "signatureHashMatch": true,
    "documentHashMatch": false,
    "combinedHashMatch": false,
    "expectedDocumentHash": "sha256:u1v2w3x4...",
    "actualDocumentHash": "sha256:z9y8x7w6..."
  }
}
```

### Verification Status Codes

| Status | Code | Description |
|--------|------|-------------|
| Valid | `VERIFIED` | All hashes match, signature is authentic |
| Signature Tampered | `SIGNATURE_TAMPERED` | Signature image has been modified |
| Document Tampered | `DOCUMENT_TAMPERED` | PDF document has been modified |
| Hash Mismatch | `HASH_MISMATCH` | Combined hash does not match |
| TSA Invalid | `TSA_INVALID` | TSA timestamp verification failed |
| Expired | `SIGNATURE_EXPIRED` | Signature has expired (if expiry set) |
| Revoked | `SIGNATURE_REVOKED` | Signature was revoked by admin |

### Gibbon Backend Verification

```php
// From VerificationService.php (conceptual implementation)
class VerificationService
{
    /**
     * Verify document signature integrity.
     */
    public function verifySignature(int $signatureID): array
    {
        // 1. Get signature record
        $signature = $this->signatureGateway->getByID($signatureID);
        if (!$signature) {
            return ['verified' => false, 'error' => 'SIGNATURE_NOT_FOUND'];
        }

        // 2. Get original document
        $document = $this->documentGateway->getByID($signature['gibbonDocumentID']);
        $pdfContent = file_get_contents($document['filePath']);

        // 3. Recompute hashes
        $currentSignatureHash = hash('sha256', $signature['signatureData']);
        $currentDocumentHash = hash('sha256', $pdfContent);
        $currentCombinedHash = hash('sha256', $currentSignatureHash . $currentDocumentHash);

        // 4. Compare hashes
        $signatureValid = hash_equals($signature['signatureHash'], $currentSignatureHash);
        $documentValid = hash_equals($signature['documentHash'], $currentDocumentHash);
        $combinedValid = hash_equals($signature['combinedHash'], $currentCombinedHash);

        // 5. Determine verification status
        $verified = $signatureValid && $documentValid && $combinedValid;
        $error = null;

        if (!$signatureValid) {
            $error = 'SIGNATURE_TAMPERED';
        } elseif (!$documentValid) {
            $error = 'DOCUMENT_TAMPERED';
        } elseif (!$combinedValid) {
            $error = 'HASH_MISMATCH';
        }

        // 6. Verify TSA timestamp if present
        $tsaVerified = null;
        if ($signature['tsaResponse']) {
            $tsaVerified = $this->verifyTSAResponse(
                $signature['tsaResponse'],
                $signature['combinedHash']
            );
        }

        // 7. Update verification status
        $this->signatureGateway->update($signatureID, [
            'verificationStatus' => $verified ? 'verified' : 'failed',
            'lastVerifiedAt' => date('Y-m-d H:i:s'),
        ]);

        return [
            'verified' => $verified,
            'error' => $error,
            'signatureIntegrity' => $signatureValid ? 'valid' : 'invalid',
            'documentIntegrity' => $documentValid ? 'valid' : 'invalid',
            'tsaVerified' => $tsaVerified,
            'verifiedAt' => date('c'),
        ];
    }
}
```

### Certificate of Signature (PDF Export)

Parents and administrators can download a certificate of signature:

```
┌─────────────────────────────────────────────────────────────────┐
│                    CERTIFICATE OF SIGNATURE                      │
│                                                                 │
│ This certificate confirms that the following document was       │
│ electronically signed using the LAYA E-Signature system.       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ DOCUMENT INFORMATION                                            │
│ ─────────────────────────────────────────────────────────────── │
│ Document ID:     DOC-2026-000001                                │
│ Title:           Enrollment Agreement 2025-2026                 │
│ Type:            Enrollment                                     │
│ Upload Date:     February 1, 2026                               │
│                                                                 │
│ SIGNATURE INFORMATION                                           │
│ ─────────────────────────────────────────────────────────────── │
│ Signed By:       Jean-Pierre Martin                             │
│ Email:           jp.martin@email.com                            │
│ Signed At:       February 15, 2026 at 2:30:00 PM EST            │
│ IP Address:      192.168.1.100                                  │
│                                                                 │
│ SIGNATURE IMAGE                                                 │
│ ┌─────────────────────────────────────────────────────────────┐│
│ │                                                             ││
│ │    [Signature Image]                                        ││
│ │                                                             ││
│ └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│ VERIFICATION HASHES                                             │
│ ─────────────────────────────────────────────────────────────── │
│ Signature Hash:  sha256:a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8   │
│ Document Hash:   sha256:s9t0u1v2w3x4y5z6a7b8c9d0e1f2g3h4i5j6   │
│ Combined Hash:   sha256:k7l8m9n0o1p2q3r4s5t6u7v8w9x0y1z2a3b4   │
│                                                                 │
│ TSA TIMESTAMP (RFC 3161)                                        │
│ ─────────────────────────────────────────────────────────────── │
│ Timestamp:       2026-02-15T14:30:00.123Z                       │
│ TSA Authority:   FreeTSA.org                                    │
│ TSA Certificate: Valid until 2030-12-31                         │
│                                                                 │
│ VERIFICATION STATUS                                             │
│ ─────────────────────────────────────────────────────────────── │
│ Status:          ✓ VERIFIED                                     │
│ Last Verified:   February 15, 2026 at 4:00:00 PM EST            │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ This certificate was generated by LAYA Childcare System.       │
│ For verification, visit: https://laya.ca/verify/DOC-2026-000001│
└─────────────────────────────────────────────────────────────────┘
```

### Expected Outcome
- Signature integrity verified via hash comparison
- Document integrity verified via hash comparison
- TSA timestamp verified (if enabled)
- Verification status recorded in database
- Certificate of signature available for download

---

## Error Handling

### Common Error Scenarios

| Scenario | Error Code | Handling |
|----------|------------|----------|
| Invalid signature format | `INVALID_SIGNATURE_FORMAT` | Reject with clear error message |
| Document not found | `DOCUMENT_NOT_FOUND` | Return 404 with document ID |
| Signature already exists | `ALREADY_SIGNED` | Reject unless revoked |
| Document expired | `DOCUMENT_EXPIRED` | Reject with expiry date |
| Unauthorized signer | `UNAUTHORIZED` | Return 403 |
| File corruption | `FILE_CORRUPTED` | Log error, alert admin |
| TSA service unavailable | `TSA_UNAVAILABLE` | Continue without TSA, log warning |
| Hash verification failed | `VERIFICATION_FAILED` | Return detailed failure reason |

### Error Response Format

```json
{
  "error": {
    "code": "ALREADY_SIGNED",
    "message": "This document has already been signed",
    "details": {
      "documentId": "doc-001",
      "signedAt": "2026-02-10T14:30:00Z",
      "signedBy": "Jean-Pierre Martin"
    },
    "timestamp": "2026-02-15T16:00:00Z"
  }
}
```

### Monitoring Queries

```sql
-- Pending signatures older than 7 days
SELECT d.title, s.gibbonPersonID, s.createdAt,
       DATEDIFF(NOW(), s.createdAt) AS days_pending
FROM gibbonDocumentSignature s
JOIN gibbonDocument d ON s.gibbonDocumentID = d.gibbonDocumentID
WHERE s.status = 'pending'
  AND s.createdAt < NOW() - INTERVAL 7 DAY
ORDER BY s.createdAt;

-- Signature verification failures
SELECT d.title, s.gibbonPersonID, s.lastVerifiedAt, s.verificationStatus
FROM gibbonDocumentSignature s
JOIN gibbonDocument d ON s.gibbonDocumentID = d.gibbonDocumentID
WHERE s.verificationStatus = 'failed'
ORDER BY s.lastVerifiedAt DESC;

-- Signature statistics by document type
SELECT
    d.type,
    COUNT(*) AS total_documents,
    SUM(CASE WHEN s.status = 'signed' THEN 1 ELSE 0 END) AS signed_count,
    SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
    AVG(TIMESTAMPDIFF(DAY, s.createdAt, s.signedAt)) AS avg_days_to_sign
FROM gibbonDocument d
JOIN gibbonDocumentSignature s ON d.gibbonDocumentID = s.gibbonDocumentID
GROUP BY d.type;
```

---

## Security Considerations

### Signature Data Protection

1. **At Rest Encryption**: Signature data stored in database should be encrypted
2. **In Transit**: All API calls use HTTPS with TLS 1.3
3. **Access Control**: Only authorized parents can sign their assigned documents
4. **Audit Trail**: All signature events logged with full metadata

### Hash Algorithm Selection

| Algorithm | Use Case | Strength |
|-----------|----------|----------|
| SHA-256 | Signature and document hashing | Recommended |
| SHA-384 | High-security environments | Enhanced |
| SHA-512 | Maximum security | Maximum |

### Timestamp Trust

1. **Server Time**: NTP-synchronized server clock
2. **TSA Integration**: RFC 3161 compliant timestamping
3. **Client Metadata**: IP address, user agent for audit
4. **Session Binding**: Signature tied to authenticated session

### Legal Compliance

| Jurisdiction | Regulation | Compliance Notes |
|--------------|------------|------------------|
| Canada | PIPEDA, Quebec Bill 64 | Electronic signatures legally valid |
| Quebec | Civil Code Art. 2837 | Document integrity must be assured |
| Federal | UECA | Technology-neutral signature validity |

---

## Testing Checklist

### Manual Verification Steps

- [ ] **Document Upload**
  - [ ] Upload PDF document from admin interface
  - [ ] Verify document appears in parent's portal
  - [ ] Confirm notification sent to parent
  - [ ] Check document status is "pending"

- [ ] **Signature Capture**
  - [ ] Open signature modal from DocumentCard
  - [ ] Draw signature on canvas (mouse and touch)
  - [ ] Clear signature and redraw
  - [ ] View PDF document link works
  - [ ] Agree to terms checkbox required

- [ ] **Signature Submission**
  - [ ] Submit signature successfully
  - [ ] Verify document status changes to "signed"
  - [ ] Confirm signedAt timestamp recorded
  - [ ] Check notification sent to admin

- [ ] **Timestamp Verification**
  - [ ] Verify signatureHash computed correctly
  - [ ] Verify documentHash computed correctly
  - [ ] Verify combinedHash computed correctly
  - [ ] Check server timestamp recorded with milliseconds
  - [ ] Confirm IP address and user agent captured

- [ ] **Signature Verification**
  - [ ] Call verification API endpoint
  - [ ] Confirm all hashes match (integrity valid)
  - [ ] Modify document and verify detection
  - [ ] Check verification status recorded

- [ ] **Certificate Export**
  - [ ] Download certificate of signature
  - [ ] Verify certificate contains all required fields
  - [ ] Confirm signature image embedded

### Integration Test Commands

```bash
# Run parent-portal document tests
cd parent-portal && npm test -- --run documents

# Run AI service webhook tests (signature events)
cd ai-service && pytest tests/test_webhooks.py -k "signature" -v

# Test signature canvas component
cd parent-portal && npm test -- --run SignatureCanvas
```

### SignatureCanvas Unit Tests

```typescript
// Expected test coverage for SignatureCanvas
describe('SignatureCanvas', () => {
  it('renders with default props');
  it('captures mouse drawing events');
  it('captures touch drawing events');
  it('clears signature when clear button clicked');
  it('exports PNG data URL on signature change');
  it('supports high DPI displays');
  it('shows placeholder when no signature');
  it('hides placeholder when signature drawn');
});
```

---

## Appendix

### Environment Variables

| Variable | Service | Description |
|----------|---------|-------------|
| `DOCUMENT_STORAGE_PATH` | gibbon | Path to store uploaded documents |
| `SIGNATURE_STORAGE_PATH` | gibbon | Path to store signature images |
| `TSA_URL` | gibbon | RFC 3161 TSA endpoint (optional) |
| `TSA_ENABLED` | gibbon | Enable/disable TSA integration |
| `JWT_SECRET_KEY` | gibbon, parent-portal | Shared JWT secret |
| `NEXT_PUBLIC_GIBBON_URL` | parent-portal | Gibbon API base URL |

### Related Documentation

- [SignatureCanvas Component](../../../parent-portal/components/SignatureCanvas.tsx)
- [DocumentSignature Component](../../../parent-portal/components/DocumentSignature.tsx)
- [Gibbon Client API](../../../parent-portal/lib/gibbon-client.ts)
- [RFC 3161 Time-Stamp Protocol](https://datatracker.ietf.org/doc/html/rfc3161)

### Compliance Notes

#### Quebec Civil Code (Art. 2837-2840)
- Electronic documents have same legal value as paper
- Document integrity must be ensured from creation
- Link between document and signature must be maintained
- Source and destination must be ascertainable

#### UECA (Uniform Electronic Commerce Act)
- Electronic signatures functionally equivalent to handwritten
- Signature must identify the person
- Signature must indicate person's approval
- Reliability appropriate to the purpose

### Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2026-02-15 | 1.0 | auto-claude | Initial E2E documentation |
