# Invoice & Payment Flow E2E Documentation

## Overview

This document describes the end-to-end flow for invoice generation, payment processing, receipt delivery, and Quebec Relevé 24 (RL-24) tax document generation in the LAYA childcare management system. The workflow covers the complete billing lifecycle from invoice creation through year-end tax document compliance.

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
                        └─────────────────┘
```

### Services Involved

| Service | Tech Stack | Port | Role |
|---------|------------|------|------|
| gibbon | PHP 8.1+ / MySQL | 80/8080 | Backend, EnhancedFinance module, invoice generation |
| parent-portal | Next.js 14 | 3000 | Parent-facing invoice view and payment UI |
| ai-service | FastAPI / PostgreSQL | 8000 | Optional analytics and notifications |

### Key Modules

| Module | Purpose |
|--------|---------|
| `EnhancedFinance` | Invoice, payment, contract, and RL-24 management |
| `NotificationEngine` | Payment reminders and receipt delivery |
| `AISync` | Webhook sync for payment events |

---

## Step 1: Invoice Generation

### User Action
Administrator creates an invoice for a child's childcare services from the Gibbon admin interface.

### Flow Diagram

```
Admin selects child/family → Creates invoice → Invoice issued
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Admin UI                                                  │
│ Modules > Enhanced Finance > Manage Invoices > Add Invoice       │
│                                                                  │
│ 1. Select child and family                                       │
│ 2. Choose billing period (monthly, weekly)                       │
│ 3. Add line items (tuition, meals, activities, etc.)             │
│ 4. Apply GST/QST tax rates                                       │
│ 5. Set due date                                                  │
│ 6. Click "Create Invoice"                                        │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ EnhancedFinance Module (Gibbon)                                  │
│ 1. Validate invoice data and amounts                             │
│ 2. Generate unique invoice number (INV-YYYYMMDD-NNNNNN)          │
│ 3. Calculate subtotal, GST (5%), QST (9.975%)                    │
│ 4. Insert into gibbonEnhancedFinanceInvoice table                │
│ 5. Set status to 'Issued'                                        │
│ 6. Trigger notification via NotificationEngine                   │
│ 7. Optional: AISync webhook for analytics                        │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ NotificationEngine Module                                        │
│ 1. Create notification for parent                                │
│ 2. Queue email with invoice PDF attachment                       │
│ 3. Send push notification to Parent Portal                       │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Create Invoice (Admin → Gibbon)

```http
POST /modules/EnhancedFinance/finance_invoice_addProcess.php
Content-Type: application/x-www-form-urlencoded
Authorization: Session cookie

gibbonPersonID=12345
gibbonFamilyID=100
gibbonSchoolYearID=2026
invoiceDate=2026-02-01
dueDate=2026-02-15
items[0][description]=Monthly Tuition - February 2026
items[0][quantity]=1
items[0][unitPrice]=1100.00
items[1][description]=Lunch Program
items[1][quantity]=1
items[1][unitPrice]=100.00
items[2][description]=Activity Fee
items[2][quantity]=1
items[2][unitPrice]=50.00
notes=Monthly childcare invoice
```

**Response (redirect on success):**
```
Location: /modules/EnhancedFinance/finance_invoice_view.php?gibbonEnhancedFinanceInvoiceID=1001
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonEnhancedFinanceInvoice` | INSERT | invoiceNumber, gibbonPersonID, gibbonFamilyID, invoiceDate, dueDate, subtotal, taxAmount, totalAmount, status='Issued' |
| `gibbonNotification` | INSERT | type='invoice_issued', recipient, invoiceID |

### Invoice Number Format

| Component | Example | Description |
|-----------|---------|-------------|
| Prefix | `INV-` | Configurable in settings |
| Year | `2026` | Current school year |
| Sequence | `000001` | Zero-padded sequential number |
| Full | `INV-2026-000001` | Complete invoice number |

### Tax Calculation (Quebec)

| Tax | Rate | Calculation |
|-----|------|-------------|
| GST | 5% | subtotal × 0.05 |
| QST | 9.975% | subtotal × 0.09975 |
| Total Tax | 14.975% | GST + QST |
| Grand Total | - | subtotal + GST + QST |

**Example:**
```
Subtotal:   $1,250.00
GST (5%):   $   62.50
QST (9.975%): $ 124.69
─────────────────────
Total:      $1,437.19
```

### Invoice Status Lifecycle

```
┌──────────┐    Issue    ┌──────────┐   Partial   ┌──────────┐
│ Pending  │ ──────────▶ │  Issued  │ ──────────▶ │ Partial  │
└──────────┘             └──────────┘             └──────────┘
                              │                        │
                              │ Full Payment           │ Full Payment
                              ▼                        ▼
                         ┌──────────┐             ┌──────────┐
                         │   Paid   │             │   Paid   │
                         └──────────┘             └──────────┘
```

| Status | Description |
|--------|-------------|
| `Pending` | Invoice created but not yet issued |
| `Issued` | Invoice sent to parent, payment expected |
| `Partial` | Some payment received, balance remaining |
| `Paid` | Full payment received |
| `Cancelled` | Invoice voided (no payment expected) |
| `Refunded` | Payment returned to parent |

### Expected Outcome
- Invoice created with unique number
- Parent notified via email and push notification
- Invoice visible in Parent Portal
- Dashboard shows updated financial summary

---

## Step 2: Parent Views Invoice in Portal

### User Action
Parent logs into the Parent Portal and views their invoice details.

### Flow Diagram

```
Parent logs into Parent Portal
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal (Next.js)                                          │
│ GET /invoices                                                    │
│ 1. Authenticate via JWT                                          │
│ 2. Fetch invoices from Gibbon API                                │
│ 3. Display invoice list with status badges                       │
│ 4. Show summary (pending, overdue, paid totals)                  │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal → Gibbon API                                       │
│ GET /api/v1/invoices                                             │
│ Authorization: Bearer <parent_jwt_token>                         │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend                                                   │
│ 1. Validate JWT token                                            │
│ 2. Identify parent's linked children/families                    │
│ 3. Query gibbonEnhancedFinanceInvoice for family                │
│ 4. Return paginated invoice list                                 │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Parent Portal → Gibbon (Fetch Invoices)

```http
GET /api/v1/invoices?status=pending&limit=20
Authorization: Bearer <parent_jwt_token>
```

**Response:**
```json
{
  "items": [
    {
      "id": "inv-001",
      "number": "INV-2026-000001",
      "date": "2026-02-01",
      "dueDate": "2026-02-15",
      "amount": 1437.19,
      "status": "pending",
      "pdfUrl": "/api/v1/invoices/inv-001/pdf",
      "items": [
        {
          "description": "Monthly Tuition - February 2026",
          "quantity": 1,
          "unitPrice": 1100.00,
          "total": 1100.00
        },
        {
          "description": "Lunch Program",
          "quantity": 1,
          "unitPrice": 100.00,
          "total": 100.00
        },
        {
          "description": "Activity Fee",
          "quantity": 1,
          "unitPrice": 50.00,
          "total": 50.00
        }
      ],
      "subtotal": 1250.00,
      "taxAmount": 187.19,
      "paidAmount": 0.00,
      "balanceRemaining": 1437.19,
      "childName": "Sophie Martin"
    }
  ],
  "total": 1,
  "skip": 0,
  "limit": 20
}
```

#### 2. Download Invoice PDF

```http
GET /api/v1/invoices/inv-001/pdf
Authorization: Bearer <parent_jwt_token>
```

**Response:**
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="INV-2026-000001.pdf"

[PDF binary data]
```

### Parent Portal UI Components

#### Invoice List Page (`/invoices/page.tsx`)

```
┌──────────────────────────────────────────────────────────────┐
│ Invoices                                                      │
│ View and manage your billing history                         │
├──────────────────────────────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐ ┌────────────┐                │
│ │  Pending   │ │  Overdue   │ │   Paid     │                │
│ │  $1,437.19 │ │     $0.00  │ │ $4,311.57  │                │
│ │ 1 invoice  │ │ 0 invoices │ │ 3 invoices │                │
│ └────────────┘ └────────────┘ └────────────┘                │
├──────────────────────────────────────────────────────────────┤
│ Payment History                                              │
│                                                              │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ 📄 Invoice #INV-2026-000001                    [Pending] ││
│ │ Issued: Feb 1, 2026                                      ││
│ │                                                          ││
│ │ Total Amount: $1,437.19     Due: Feb 15, 2026           ││
│ │                             14 days remaining            ││
│ │                                                          ││
│ │ ┌────────────────────────────────────────────────────┐  ││
│ │ │ Description          │ Qty │ Unit Price │  Total  │  ││
│ │ ├────────────────────────────────────────────────────┤  ││
│ │ │ Monthly Tuition      │  1  │  $1,100.00 │$1,100.00│  ││
│ │ │ Lunch Program        │  1  │    $100.00 │  $100.00│  ││
│ │ │ Activity Fee         │  1  │     $50.00 │   $50.00│  ││
│ │ │ GST (5%)             │     │            │   $62.50│  ││
│ │ │ QST (9.975%)         │     │            │  $124.69│  ││
│ │ ├────────────────────────────────────────────────────┤  ││
│ │ │                         Total │         │$1,437.19│  ││
│ │ └────────────────────────────────────────────────────┘  ││
│ │                                                          ││
│ │ [Download PDF]  [Pay Now]                                ││
│ └──────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

### Payment Status Badge Colors

| Status | Badge Class | Color |
|--------|-------------|-------|
| `paid` | `badge-success` | Green |
| `pending` | `badge-warning` | Yellow |
| `overdue` | `badge-error` | Red |

### Expected Outcome
- Parent can view all invoices for their children
- Status badges clearly indicate payment status
- Due dates show remaining days or overdue status
- PDF download available for records
- "Pay Now" button visible for unpaid invoices

---

## Step 3: Payment Processing

### User Action
Parent makes a payment through the Parent Portal or admin records a payment manually.

### Flow Diagram

```
Parent clicks "Pay Now" or Admin records payment
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Payment Options                                                  │
│ 1. Credit/Debit Card (online)                                   │
│ 2. E-Transfer (manually recorded)                                │
│ 3. Cheque (manually recorded)                                    │
│ 4. Cash (manually recorded)                                      │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend (Payment Recording)                               │
│ 1. Validate payment amount                                       │
│ 2. Insert into gibbonEnhancedFinancePayment table               │
│ 3. Update invoice paidAmount and status                         │
│ 4. Generate payment receipt                                      │
│ 5. Trigger notification to parent                                │
│ 6. Sync to AI service via webhook                                │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AISync Webhook                                                   │
│ POST /api/v1/webhook                                             │
│ event_type: invoice_paid                                         │
│ payload: { invoiceID, amount, paymentMethod, timestamp }        │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Record Payment (Admin → Gibbon)

```http
POST /modules/EnhancedFinance/finance_payment_addProcess.php
Content-Type: application/x-www-form-urlencoded
Authorization: Session cookie

gibbonEnhancedFinanceInvoiceID=1001
paymentDate=2026-02-10
amount=1437.19
method=ETransfer
reference=INTERAC-12345678
notes=Payment received via e-transfer
```

**Response (redirect on success):**
```
Location: /modules/EnhancedFinance/finance_invoice_view.php?gibbonEnhancedFinanceInvoiceID=1001&paymentRecorded=true
```

#### 2. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: invoice_paid

{
  "event_type": "invoice_paid",
  "entity_type": "payment",
  "entity_id": "5001",
  "payload": {
    "invoice_id": "1001",
    "invoice_number": "INV-2026-000001",
    "child_id": 12345,
    "family_id": 100,
    "amount": 1437.19,
    "method": "ETransfer",
    "reference": "INTERAC-12345678",
    "payment_date": "2026-02-10",
    "invoice_status": "Paid",
    "balance_remaining": 0.00
  },
  "timestamp": "2026-02-10T14:30:00-05:00"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Payment $1437.19 for invoice INV-2026-000001 recorded",
  "event_type": "invoice_paid",
  "entity_id": "5001",
  "received_at": "2026-02-10T19:30:00.123Z",
  "processing_time_ms": 8.45
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonEnhancedFinancePayment` | INSERT | gibbonEnhancedFinanceInvoiceID, paymentDate, amount, method, reference, notes, recordedByID |
| `gibbonEnhancedFinanceInvoice` | UPDATE | paidAmount, status='Paid' or 'Partial' |
| `gibbonAISyncLog` | INSERT | eventType='invoice_paid', status='success' |
| `gibbonNotification` | INSERT | type='payment_received', recipient |

### Payment Methods

| Method | Code | Description |
|--------|------|-------------|
| Cash | `Cash` | Physical cash payment |
| Cheque | `Cheque` | Personal or bank cheque |
| E-Transfer | `ETransfer` | Interac e-Transfer (Canada) |
| Credit Card | `CreditCard` | Visa, Mastercard, Amex |
| Debit Card | `DebitCard` | Bank debit card |
| Other | `Other` | Any other payment method |

### Partial Payment Handling

When payment amount < invoice total:

```php
// From InvoiceGateway::updatePaidAmount()
$newStatus = 'Issued';
if ($totalPaid >= $totalAmount) {
    $newStatus = 'Paid';
} elseif ($totalPaid > 0) {
    $newStatus = 'Partial';
}
```

**Example - Partial Payment:**
```
Invoice Total:     $1,437.19
First Payment:     $  500.00  → Status: Partial
Balance Remaining: $  937.19

Second Payment:    $  937.19  → Status: Paid
Balance Remaining: $    0.00
```

### Expected Outcome
- Payment recorded in database
- Invoice status updated (Partial or Paid)
- Parent receives payment confirmation notification
- Receipt generated and available for download
- Financial dashboard updated

---

## Step 4: Receipt Generation

### User Action
System generates a receipt after successful payment; parent can download it.

### Flow Diagram

```
Payment recorded successfully
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Receipt Generation                                               │
│ 1. Fetch payment details from database                          │
│ 2. Fetch invoice details (child, family, line items)            │
│ 3. Generate receipt PDF with tax breakdown                      │
│ 4. Store receipt reference in database                          │
│ 5. Send receipt notification to parent                          │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ NotificationEngine                                               │
│ 1. Queue email with receipt PDF attachment                       │
│ 2. Mark as sent in notification log                              │
└─────────────────────────────────────────────────────────────────┘
```

### Receipt PDF Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                        PAYMENT RECEIPT                          │
│                                                                 │
│ LAYA Childcare Center                                          │
│ 123 Maple Street, Montreal, QC H1A 1A1                         │
│ Tel: (514) 555-1234                                            │
│ NEQ: 1234567890                                                │
├─────────────────────────────────────────────────────────────────┤
│ Receipt #: REC-2026-000001                                      │
│ Date: February 10, 2026                                         │
│                                                                 │
│ Bill To:                                                        │
│   Martin Family                                                 │
│   456 Oak Avenue, Montreal, QC H2B 2B2                          │
│                                                                 │
│ For Child: Sophie Martin                                        │
├─────────────────────────────────────────────────────────────────┤
│ Payment for Invoice: INV-2026-000001                            │
│                                                                 │
│ Description                                          Amount     │
│ ─────────────────────────────────────────────────────────────── │
│ Monthly Tuition - February 2026                    $1,100.00    │
│ Lunch Program                                        $100.00    │
│ Activity Fee                                          $50.00    │
│ ─────────────────────────────────────────────────────────────── │
│ Subtotal                                           $1,250.00    │
│ GST (5%) - Registration #123456789                    $62.50    │
│ QST (9.975%) - Registration #1234567890              $124.69    │
│ ─────────────────────────────────────────────────────────────── │
│ TOTAL                                              $1,437.19    │
│                                                                 │
│ Payment Method: E-Transfer                                      │
│ Reference: INTERAC-12345678                                     │
│ Amount Paid: $1,437.19                                          │
│ Payment Status: PAID IN FULL                                    │
├─────────────────────────────────────────────────────────────────┤
│ This receipt is for tax purposes.                               │
│ Please keep for your records.                                   │
│                                                                 │
│ For Quebec residents: A Relevé 24 (RL-24) will be issued       │
│ annually for childcare expense deductions.                      │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Parent Portal → Gibbon (Download Receipt)

```http
GET /api/v1/payments/5001/receipt
Authorization: Bearer <parent_jwt_token>
```

**Response:**
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="REC-2026-000001.pdf"

[PDF binary data]
```

### Expected Outcome
- Receipt PDF generated with payment details
- Parent notified via email with receipt attachment
- Receipt available for download in Parent Portal
- Tax registration numbers included for compliance

---

## Step 5: Relevé 24 (RL-24) Generation

### Overview

The Relevé 24 (RL-24) is a Quebec tax document required for childcare expense deductions. It is issued annually to parents with the total qualifying childcare expenses paid during the tax year.

### Critical Business Rules

> **IMPORTANT**: RL-24 amounts must reflect PAID amounts at filing time, NOT invoiced amounts. If additional payments are received after initial RL-24 filing, an amended RL-24 (type A) must be issued.

### RL-24 Box Definitions

| Box | Name | Description |
|-----|------|-------------|
| A | Slip Type | R=Original, A=Amended, D=Cancelled |
| B | Days of Care | Actual paid days of childcare |
| C | Total Amounts Paid | All payments received for the tax year |
| D | Non-Qualifying Expenses | Medical, transport, teaching, field trips, fees |
| E | Qualifying Expenses | Box C minus Box D (claimable amount) |
| H | Provider SIN | Childcare provider's Social Insurance Number |

### Non-Qualifying Expense Types

These amounts are excluded from Box E (Qualifying Expenses):

| Expense Type | Description |
|--------------|-------------|
| `medical` | Medical or hospital care |
| `hospital` | Hospital care |
| `transportation` | Transportation services |
| `teaching` | Teaching/tutoring services |
| `fieldtrip` | Field trip costs |
| `registration` | Registration fees |
| `late_fee` | Late payment penalties |
| `admin_fee` | Administrative fees |
| `meal_supplement` | Additional meal supplements |

### Flow Diagram

```
End of Tax Year (December 31)
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Admin initiates RL-24 generation                                │
│ Modules > Enhanced Finance > Quebec Releve 24 (RL-24)           │
│ 1. Select tax year                                               │
│ 2. View eligible children (those with paid invoices)            │
│ 3. Click "Generate RL-24" for each child                        │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Releve24 Business Logic                                          │
│ 1. Validate provider configuration (SIN, name, address, NEQ)    │
│ 2. Calculate Box B - Days of Care (paid days only)              │
│ 3. Calculate Box C - Total Amounts Paid                         │
│ 4. Calculate Box D - Non-Qualifying Expenses                    │
│ 5. Calculate Box E - Qualifying Expenses (C - D)                │
│ 6. Determine slip type (R=original, A=amended)                  │
│ 7. Generate unique slip number (RL24-YYYY-NNNNNN)               │
│ 8. Insert into gibbonEnhancedFinanceReleve24 table              │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ RL-24 PDF Generation                                             │
│ 1. Render RL-24 form with calculated values                      │
│ 2. Include provider and recipient information                    │
│ 3. Store PDF for download                                        │
│ 4. Queue for distribution to parents                             │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Get Eligible Children for RL-24

```http
GET /modules/EnhancedFinance/api/releve24/eligible?taxYear=2025
Authorization: Session cookie
```

**Response:**
```json
{
  "taxYear": 2025,
  "eligibleChildren": [
    {
      "gibbonPersonID": 12345,
      "surname": "Martin",
      "preferredName": "Sophie",
      "dob": "2022-05-15",
      "gibbonFamilyID": 100,
      "familyName": "Martin Family",
      "totalPaid": 17246.28,
      "invoiceCount": 12,
      "paymentCount": 12,
      "hasReleve24": false
    }
  ],
  "totalAmountPaid": 17246.28,
  "generatedSlips": 0
}
```

#### 2. Generate RL-24 for Child

```http
POST /modules/EnhancedFinance/finance_releve24_generateProcess.php
Content-Type: application/x-www-form-urlencoded
Authorization: Session cookie

gibbonPersonID=12345
gibbonFamilyID=100
taxYear=2025
```

**Response (redirect on success):**
```
Location: /modules/EnhancedFinance/finance_releve24_view.php?gibbonEnhancedFinanceReleve24ID=1
```

#### 3. Get RL-24 Summary

```http
GET /modules/EnhancedFinance/api/releve24/summary?taxYear=2025
Authorization: Session cookie
```

**Response:**
```json
{
  "taxYear": 2025,
  "eligibleChildren": 45,
  "totalAmountPaid": 776083.00,
  "generatedSlips": 42,
  "draftCount": 3,
  "generatedCount": 39,
  "sentCount": 38,
  "filedCount": 0,
  "totalQualifyingExpenses": 745520.00,
  "totalDaysOfCare": 10350
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonEnhancedFinanceReleve24` | INSERT | gibbonPersonID, gibbonFamilyID, taxYear, slipType, slipNumber, daysOfCare, totalAmountsPaid, nonQualifyingExpenses, qualifyingExpenses, providerSIN, recipientSIN, status='Generated' |

### RL-24 Document Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                    RELEVÉ 24 (RL-24)                            │
│           Frais de garde d'enfants / Childcare Expenses         │
│                                                                 │
│ Année d'imposition / Tax Year: 2025                             │
│ No de relevé / Slip Number: RL24-2025-000001                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ CASE A - Type de relevé / Slip Type                            │
│ ┌───┐                                                           │
│ │ R │  R = Original / A = Modifié / D = Annulé                 │
│ └───┘                                                           │
│                                                                 │
│ CASE B - Nombre de jours de garde / Days of Care               │
│ ┌─────────┐                                                     │
│ │   230   │                                                     │
│ └─────────┘                                                     │
│                                                                 │
│ CASE C - Montants versés / Amounts Paid                        │
│ ┌─────────────┐                                                 │
│ │  17,246.28  │ $                                              │
│ └─────────────┘                                                 │
│                                                                 │
│ CASE D - Dépenses non admissibles / Non-Qualifying Expenses    │
│ ┌─────────────┐                                                 │
│ │     500.00  │ $                                              │
│ └─────────────┘                                                 │
│                                                                 │
│ CASE E - Dépenses admissibles / Qualifying Expenses            │
│ ┌─────────────┐                                                 │
│ │  16,746.28  │ $  (C - D)                                     │
│ └─────────────┘                                                 │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ CASE H - NAS du prestataire / Provider SIN                     │
│ ┌─────────────┐                                                 │
│ │ 123-456-789 │                                                │
│ └─────────────┘                                                 │
│                                                                 │
│ Prestataire / Provider:                                         │
│   LAYA Childcare Center                                         │
│   123 Maple Street, Montreal, QC H1A 1A1                        │
│   NEQ: 1234567890                                               │
│                                                                 │
│ Bénéficiaire / Recipient:                                       │
│   Martin, Jean-Pierre                                           │
│   NAS / SIN: 987-654-321                                        │
│                                                                 │
│ Enfant / Child:                                                 │
│   Martin, Sophie                                                │
│   Date de naissance / DOB: 2022-05-15                           │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Date de production / Issue Date: 2026-02-15                     │
│                                                                 │
│ Conservez ce relevé pour vos dossiers fiscaux.                 │
│ Keep this slip for your tax records.                            │
└─────────────────────────────────────────────────────────────────┘
```

### RL-24 Status Lifecycle

```
┌──────────┐  Generate  ┌───────────┐   Send    ┌──────────┐   File    ┌──────────┐
│  Draft   │ ─────────▶ │ Generated │ ────────▶ │   Sent   │ ────────▶ │  Filed   │
└──────────┘            └───────────┘           └──────────┘           └──────────┘
                              │
                              │ Amendment needed
                              ▼
                        ┌───────────┐
                        │ Amended   │
                        └───────────┘
```

| Status | Description |
|--------|-------------|
| `Draft` | RL-24 calculated but not finalized |
| `Generated` | RL-24 finalized with slip number |
| `Sent` | RL-24 distributed to recipient |
| `Filed` | RL-24 filed with Revenu Québec |
| `Amended` | Original RL-24 superseded by amendment |

### SIN Validation (Luhn Algorithm)

```php
// From Releve24::validateSIN()
public function validateSIN($sin)
{
    // Remove non-numeric characters
    $digits = preg_replace('/[^0-9]/', '', $sin);

    // SIN must be exactly 9 digits
    if (strlen($digits) !== 9) {
        return false;
    }

    // Luhn algorithm
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $digit = (int) $digits[$i];
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }

    return ($sum % 10) === 0;
}
```

### Provider Configuration Requirements

Before generating RL-24, these settings must be configured:

| Setting | Description | Required |
|---------|-------------|----------|
| `providerSIN` | Provider Social Insurance Number (XXX-XXX-XXX) | Yes |
| `providerName` | Legal name of childcare provider | Yes |
| `providerAddress` | Full mailing address | Yes |
| `providerNEQ` | Quebec Enterprise Number | Recommended |

### Expected Outcome
- RL-24 generated for each child with paid invoices
- Qualifying expenses correctly calculated (excluding non-qualifying items)
- Days of care based on paid days, not calendar days
- RL-24 available for download by parents
- Data ready for filing with Revenu Québec (by February 28 deadline)

---

## Error Handling

### Common Error Scenarios

| Scenario | Error Code | Handling |
|----------|------------|----------|
| Invalid payment amount | `VALIDATION_ERROR` | Reject payment, show error message |
| Payment exceeds balance | `OVERPAYMENT` | Allow but flag for review |
| Duplicate payment | `DUPLICATE_REFERENCE` | Reject if same reference number |
| Missing provider SIN | `CONFIG_ERROR` | Block RL-24 generation until configured |
| Invalid SIN format | `SIN_VALIDATION_ERROR` | Show format requirements |
| Invoice already cancelled | `INVOICE_CANCELLED` | Reject payment |

### Payment Webhook Retry Logic

```php
// AISync retry for failed webhooks
// Exponential backoff: 30s, 60s, 120s (max 3 retries)
if ($syncStatus === 'failed' && $retryCount < 3) {
    $delay = 30 * pow(2, $retryCount);
    scheduleRetry($delay);
}
```

### Monitoring Queries

```sql
-- Failed payment webhooks in last 24 hours
SELECT eventType, entityType, COUNT(*) as failed_count
FROM gibbonAISyncLog
WHERE status = 'failed'
  AND eventType IN ('invoice_paid', 'invoice_created')
  AND timestampCreated > NOW() - INTERVAL 1 DAY
GROUP BY eventType, entityType;

-- Outstanding invoices by family
SELECT
    f.name AS familyName,
    COUNT(*) AS overdueCount,
    SUM(i.totalAmount - i.paidAmount) AS totalOverdue
FROM gibbonEnhancedFinanceInvoice i
JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
WHERE i.status IN ('Issued', 'Partial')
  AND i.dueDate < CURDATE()
GROUP BY i.gibbonFamilyID
ORDER BY totalOverdue DESC;

-- RL-24 generation status by year
SELECT
    taxYear,
    status,
    COUNT(*) AS slipCount,
    SUM(qualifyingExpenses) AS totalQualifying
FROM gibbonEnhancedFinanceReleve24
GROUP BY taxYear, status
ORDER BY taxYear DESC, status;
```

---

## Testing Checklist

### Manual Verification Steps

- [ ] **Invoice Generation**
  - [ ] Create invoice with multiple line items
  - [ ] Verify tax calculations (GST 5%, QST 9.975%)
  - [ ] Confirm invoice number generated correctly
  - [ ] Check email notification sent to parent
  - [ ] Verify invoice visible in Parent Portal

- [ ] **Invoice Viewing (Parent Portal)**
  - [ ] Log in as parent
  - [ ] Navigate to Invoices page
  - [ ] Verify invoice list shows correct data
  - [ ] Check status badges display correctly
  - [ ] Download invoice PDF
  - [ ] Verify PDF contains all line items and tax breakdown

- [ ] **Payment Recording**
  - [ ] Record full payment against invoice
  - [ ] Verify invoice status changes to "Paid"
  - [ ] Record partial payment
  - [ ] Verify invoice status changes to "Partial"
  - [ ] Record remaining balance
  - [ ] Verify invoice status changes to "Paid"
  - [ ] Test all payment methods (Cash, Cheque, ETransfer, CreditCard)

- [ ] **Receipt Generation**
  - [ ] Verify receipt generated after payment
  - [ ] Download receipt PDF
  - [ ] Confirm receipt includes tax registration numbers
  - [ ] Verify email sent to parent with receipt

- [ ] **Relevé 24 (RL-24)**
  - [ ] Configure provider settings (SIN, name, address)
  - [ ] Generate RL-24 for child with payments
  - [ ] Verify Box B (Days of Care) calculated correctly
  - [ ] Verify Box C (Total Amounts Paid) matches payments
  - [ ] Verify Box D (Non-Qualifying Expenses) excludes correct items
  - [ ] Verify Box E (Qualifying Expenses) = Box C - Box D
  - [ ] Download RL-24 PDF
  - [ ] Test amended RL-24 generation

### Integration Test Commands

```bash
# Run Gibbon finance module tests
cd gibbon && vendor/bin/codecept run unit modules/EnhancedFinance/

# Run AI service webhook tests (payment events)
cd ai-service && pytest tests/test_webhooks.py -k "invoice" -v

# Run parent-portal invoice component tests
cd parent-portal && npm test -- --run invoice
```

---

## Appendix

### Environment Variables

| Variable | Service | Description |
|----------|---------|-------------|
| `AI_SERVICE_URL` | gibbon | URL to AI service for webhooks |
| `JWT_SECRET_KEY` | gibbon, ai-service | Shared secret for JWT tokens |
| `GST_RATE` | gibbon | GST tax rate (default: 0.05) |
| `QST_RATE` | gibbon | QST tax rate (default: 0.09975) |
| `INVOICE_PREFIX` | gibbon | Invoice number prefix (default: INV-) |

### Related Documentation

- [EnhancedFinance Module README](../../../modules/EnhancedFinance/README.md)
- [Releve24 Business Logic](../../../modules/EnhancedFinance/Releve24.php)
- [Parent Portal Invoice Components](../../../parent-portal/components/InvoiceCard.tsx)
- [Quebec RL-24 Official Guide](https://www.revenuquebec.ca/en/citizens/income-tax-return/completing-your-income-tax-return/relevé-24-childcare-expenses/)

### Compliance Notes

#### Quebec Relevé 24 Requirements
- **Filing Deadline**: Last day of February following the tax year
- **Recipient Copy**: Must be provided to parents by filing deadline
- **Government Copy**: Filed electronically with Revenu Québec
- **Record Retention**: Keep copies for 6 years
- **Amendments**: Issue type 'A' slip if corrections needed after filing
- **Cancellations**: Issue type 'D' slip to cancel a previously filed slip

### Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2026-02-15 | 1.0 | auto-claude | Initial E2E documentation |
