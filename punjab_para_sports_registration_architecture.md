# Complete Registration System Audit, Data Flow, Admin Intake & Punjab Para Sports Association Blueprint

## Executive Summary
This document provides a comprehensive technical audit, data flow pipeline, and architectural blueprint of the Athlete and Official Registration system. It details the existing architecture (from OTP verification to admin panel intake, cards, approvals, and email dispatch) and outlines a custom-tailored implementation blueprint for the **Punjab Para Sports Association (PPSA)** based on the Para State Games event structure.

---

## 1. End-to-End System Architecture & Data Flow Pipeline

```
 [APPLICANT / USER]
        │
        ▼
 ┌─────────────────────────────────────────────────────────────┐
 │ STEP 0: Email Verification (OTP Gate)                       │
 │  • hCaptcha & Honeypot Validation                           │
 │  • CSRF Session Token Check                                 │
 │  • Rate Limiting (IP: 5/15m, Email: 3/15m, 60s Cooldown)   │
 │  • HMAC-SHA256 Hashed 6-Digit OTP Generation                │
 │  • Resend API Dispatch ──> OTP Email Sent                   │
 └──────────────────────────────┬──────────────────────────────┘
                                │ (Session Verified)
                                ▼
 ┌─────────────────────────────────────────────────────────────┐
 │ STEP 1 to N: Multi-Step Registration Form                   │
 │  • Client-Side Validation & Auto-Draft (localStorage)       │
 │  • Dynamic Form Fields (Athlete vs Official Categories)    │
 │  • File Upload Client Compression & Validation              │
 └──────────────────────────────┬──────────────────────────────┘
                                │ (AJAX POST)
                                ▼
 ┌─────────────────────────────────────────────────────────────┐
 │ BACKEND INTAKE (api/player-registration.php & official)     │
 │  • Verify Session OTP State & CSRF                          │
 │  • High-Scoring Duplicate Detection Algorithm (Aadhaar,     │
 │    Phone, Email, Normalized Levenshtein Name & DOB Proximity│
 │  • UUID File Storage (Photos, Identity Docs, Passport Copy) │
 │  • MySQL Transaction: Insert application with status=pending│
 │  • Confirmation Email Sent via Mailer Engine (Resend API)   │
 └──────────────────────────────┬──────────────────────────────┘
                                │
                                ▼
 ┌─────────────────────────────────────────────────────────────┐
 │ ADMIN INTAKE PANEL (admin/registrations.php)                │
 │  • Real-time Queue & Verification Filter                    │
 │  • Full Player / Official Cards & Profile Drawer             │
 │  • Action: Approve, Reject (with reason), Reopen, Duplicate │
 └──────────────────────────────┬──────────────────────────────┘
                                │ (Admin Decision)
                                ▼
 ┌─────────────────────────────────────────────────────────────┐
 │ DECISION & NOTIFICATION                                     │
 │  • Approval: Promote to `athletes` / `officials` main table │
 │  • Issue Permanent Registration No (e.g. PPSA-ATH-2026-001) │
 │  • Send HTML Email Notification (Approval / Rejection)      │
 └─────────────────────────────────────────────────────────────┘
```

---

## 2. OTP Verification Mechanics

The OTP system acts as an initial security and verification gate prior to unlocking registration form entry.

### A. Security Protocols & Rate Limits
1. **CSRF Protection**: Every OTP request requires a session-bound `csrf_token`.
2. **Bot & Spam Prevention**:
   - **hCaptcha Integration**: Verifies user response against hCaptcha API (`https://api.hcaptcha.com/siteverify`).
   - **Honeypot Trap**: Invisible field `website_url`. If populated, execution terminates with a dummy success response.
3. **Multi-Tier Rate Limiting** (`otp_rate_limits` table with `FOR UPDATE` lock):
   - **60-Second Cooldown**: Minimum 60 seconds required between consecutive OTP requests for the same email.
   - **IP Window**: Maximum 5 requests per 15 minutes, 30 requests per 24 hours per IP hash.
   - **Email Window**: Maximum 3 requests per 15 minutes, 10 requests per 24 hours per email hash.
4. **Data Hashing & Zero-Knowledge**:
   - OTP codes are generated via `random_int(100000, 999999)`.
   - Raw OTPs and recipient emails are never saved in plain text in OTP logs; stored as `hash_hmac('sha256', $data, OTP_SECRET)`.
5. **Expiration & Attempt Limits**:
   - Code expires after **10 minutes**.
   - Maximum **5 failed attempts** allowed. Exceeding this invalidates the OTP record.
6. **Session-Locked Verification Context**:
   - Upon successful verification, `$_SESSION['verified_email_' . $action]` is set to prevent cross-action session hijacking.

---

## 3. Registration Forms & Step Division

### A. Athlete Registration Form (3-Step Wizard + OTP Gate)

| Step | Section Name | Fields & Inputs | Validation & Rules |
| :--- | :--- | :--- | :--- |
| **Step 0** | **Email Verification** | • Email Address<br>• hCaptcha Widget<br>• 6-digit OTP Input | Mandatory verification before accessing Step 1. |
| **Step 1** | **Personal Details** | • Full Name<br>• Gender (Male / Female / Other)<br>• Date of Birth<br>• Father's Name<br>• Mother's Name<br>• Phone / Mobile Number | All fields mandatory. Full Name & DOB used for duplicate checking. |
| **Step 2** | **Sports & Apparel Sizes** | • Age Category (Sub-Junior U14 / Junior U17 / Open 17+)<br>• State / Union Territory<br>• Impairment Type (e.g. Hypertonia, Ataxia, Limb Deficiency)<br>• Wheelchair Status (Yes / No)<br>• T-Shirt Size (XS to 4XL)<br>• Track Suit Size (XS to 4XL)<br>• Shoe Size (UK 5 to 12) | Required for uniform allocation and event categorization. |
| **Step 3** | **Identity & Uploads** | • 12-Digit Aadhaar Card Number<br>• Permanent Address & Pin Code<br>• Passport Photo (JPG/PNG, auto WebP compression)<br>• Government ID Proof (Aadhaar / Passport Scan PDF/JPG)<br>• Medical / Disability Certificate (PDF/JPG, max 2MB) | Strict file extension & size validation. |
| **Step 4** | **Submission Success** | • Generates unique Reference Tracking ID (e.g., `PPSA-ATH-2026-000101`)<br>• Status Tracking Link | Auto-saves progress to `localStorage` until submitted. |

---

### B. Official Registration Form (5-Step Wizard + OTP Gate)

#### Official Types & Roles:
1. **Coach**
2. **Referee / Technical Official**
3. **Volunteer**
4. **Classifier** (Sub-types: Physio, Doctor, Coach, Other)
5. **Ramp Operator / Sports Assistant**
6. **Escort**

#### Step Division:

```
[Step 0: Email OTP Verification]
             │
[Step 1: Personal Info] (Full Name, Gender, DOB, Father's Name, Mobile)
             │
[Step 2: Category & Profile] 
 ├── Select Official Category (Coach, Referee, Volunteer, Classifier, Ramp Operator, Escort)
 ├── Educational / Professional Qualification (Required for Coach, Referee, Classifier)
 ├── Classifier Type Dropdown (If Classifier: Physio / Doctor / Coach / Other)
 ├── Para Sports Experience Description
 └── Uniform Sizes (T-Shirt, Track Suit, Shoe Size)
             │
[Step 3: Address & Identity] (State/UT, Full Permanent Address, Pincode, 12-digit Aadhaar)
             │
[Step 4: Document Uploads]
 ├── Passport Size Photograph (JPG/PNG)
 ├── Govt ID Proof (Aadhaar / ID Card scan PDF/JPG)
 └── Mandatory Passport Copy / Booklet Document
             │
[Step 5: Review & Declaration] (Summary preview box, Legal Declaration checkbox, Submit)
```

---

## 4. Admin Intake Panel, Player Cards & Approval Pipeline

### A. Intake Panel Structure (`admin/registrations.php`)
- **Queue Views**: Filter by Pending Review, Approved, Rejected, and Re-opened applications.
- **Role-Based Scopes**:
  - **Admin**: Full review, approval, rejection, deletion, state reassignment.
  - **Classifier**: Authorized specifically to review impairment details, sports classification, and medical certificates for Athletes.
  - **State Admin**: Scoped to view and verify applicants originating from their designated state.

### B. Inside the Player Card & Profile Drawer (`admin/athlete-details.php`)

Each player card and detail view contains 6 structured modules:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        ATHLETE PROFILE CARD                            │
├────────────────────────────────────────────────────────────────────────┤
│ 1. HEADER & IDENTITY BADGE                                            │
│    • Photo Preview, Full Name, Gender, DOB, Age Category              │
│    • System Registration No (PPSA-ATH-2026-XXXX) & NSRS ID             │
│    • Status Badge: Approved (Green) / Pending (Yellow) / Rejected (Red)│
│    • Profile Health Bar (Flags missing photo/contact/medical/id)       │
├────────────────────────────────────────────────────────────────────────┤
│ 2. PERSONAL & CONTACT DETAILS                                          │
│    • Father's Name & Mother's Name                                    │
│    • Primary Mobile Number & Email Address                            │
│    • Full Address, Pin Code, State Association Scope                  │
├────────────────────────────────────────────────────────────────────────┤
│ 3. SPORTS, CLASSIFICATION & DISABILITY                                │
│    • Designated Sport / Game & Event Discipline                       │
│    • Sports Classification Code (e.g., F-55, T-54, WH-1)              │
│    • Primary Impairment Type & Wheelchair User Status                 │
├────────────────────────────────────────────────────────────────────────┤
│ 4. APPAREL & EQUIPMENT SIZES                                           │
│    • Kit T-Shirt Size, Track Suit Size, Shoe Size (UK)                │
├────────────────────────────────────────────────────────────────────────┤
│ 5. VERIFIED DOCUMENTS & ATTACHMENTS                                    │
│    • Passport Photograph (High-res WebP)                              │
│    • Government ID / Aadhaar Copy Viewer / Download                   │
│    • Medical / Disability Certificate Document Link                   │
├────────────────────────────────────────────────────────────────────────┤
│ 6. SYSTEM AUDIT & HISTORY LOG                                          │
│    • Duplicate Risk Score Indicator (0-100 pts match warning)          │
│    • Event Participation History & Tournament Entry Records           │
│    • Timeline Log: Date submitted, status changes, changed by whom    │
└────────────────────────────────────────────────────────────────────────┘
```

---

### C. Intake Approval & Duplicate Detection Workflow

1. **Intake Queue Arrival**: Application enters `athlete_applications` or `official_applications` with status `'pending'`.
2. **Duplicate Prevention Algorithm**:
   - Calculates a fuzzy match score against existing approved records.
   - **Aadhaar Match**: +100 pts (Instant duplicate flag).
   - **Phone Match**: +40 pts.
   - **Email Match**: +30 pts.
   - **DOB Proximity**: Up to +20 pts.
   - **Levenshtein & Metaphone Name Match**: Up to +30 pts.
   - *Total Score $\ge$ 50 pts*: Admin receives an interactive warning modal: *"Potential Duplicate Detected. Merge or Link to Existing Record?"*
3. **Approval Action**:
   - Admin clicks **Approve**.
   - Application is migrated into the master `athletes` or `officials` directory table.
   - Permanent Registration ID generated.
   - Automatic email notification triggered to applicant.
4. **Rejection Action**:
   - Admin clicks **Reject**.
   - Mandatory modal prompts for **Rejection Reason / Comments**.
   - Application status updated to `'rejected'`.
   - Rejection email automatically dispatched containing the exact admin feedback notes.

---

## 5. Automated Email Dispatch System

All outgoing email dispatch is centralized in `includes/mailer.php` leveraging the **Resend API** (`api.resend.com`) with automated fallback logging in `email_logs`.

```
                  ┌──────────────────────────────┐
                  │      triggering Action       │
                  └──────────────┬───────────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         ▼                       ▼                       ▼
 ┌───────────────┐       ┌───────────────┐       ┌───────────────┐
 │   OTP Code    │       │ Application   │       │ Admin Review  │
 │  Dispatch     │       │ Submission    │       │ Decision      │
 └───────┬───────┘       └───────┬───────┘       └───────┬───────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ Subject:        │     │ Subject:        │     │ Approved:       │
│ Your OTP        │     │ Application     │     │ Registration    │
│ Verification    │     │ Received -      │     │ Approved -      │
│ Code            │     │ Tracking ID     │     │ Reg No Issued   │
│                 │     │                 │     │                 │
│ Content:        │     │ Content:        │     │ Rejected:       │
│ 6-digit code    │     │ Category,       │     │ Rejection       │
│ (10 min expiry) │     │ Reference ID,   │     │ Reason &        │
│                 │     │ Tracking URL    │     │ Action Needed   │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

---

## 6. Customization Blueprint for Punjab Para Sports Association (PPSA)

Based on the official affiliation and event notification details, here is the architecture for **Punjab Para Sports Association**.

### A. Organization & Identity Header Details
- **Organization Name**: Punjab Para Sports Association (PPSA)
- **Affiliations**:
  - Affiliated Member of: **Paralympic Committee of India (PCI), New Delhi**
  - Recognised by: **Punjab State Sports Council (Govt. of Punjab)**
- **Correspondence Office**: #126, Near Shivalik Public School, Muktsar Road, Jaito, Distt. Faridkot, Punjab 151202
- **Contact Info**: +91 98034-54949, +91 94645-00042 | **Email**: officeparapunjab@gmail.com
- **Key Leadership**:
  - **President**: Charanjeet Singh Brar
  - **Secretary General**: Jaspreet Singh
  - **Treasurer**: Shaminder Singh Dhillon

---

### B. Sports, Categories & Event Schema Matrix

The registration form step for **PPSA State Games Event Selection** will feature dynamic multi-level cascading selectors based on the official event catalog:

```
[ Select Game ] ──> [ Select Sport Classification / Weight ] ──> [ Select Specific Event ]
```

#### Detailed Event Breakdown Table:

| Sr No | Game | Classification / Category | Specific Event Options | Venue |
| :---: | :--- | :--- | :--- | :--- |
| **1** | **Para Athletics** | **F-51 (WC)** | Club Throw, Discus Throw | Ludhiana |
| | | **F-52 (WC)** | Discus Throw | Ludhiana |
| | | **F-53, 54, 55 (WC)** | Discus Throw, Shot Put, Javelin Throw | Ludhiana |
| | | **F-56, 57 (S)** | Discus Throw, Shot Put, Javelin Throw | Ludhiana |
| | | **F-35** | Shot Put | Ludhiana |
| | | **F-36** | Shot Put | Ludhiana |
| | | **F-43** | Javelin Throw | Ludhiana |
| | | **F-64** | Discus Throw, Shot Put, Javelin Throw | Ludhiana |
| | | **F-11, 12, 13, 20, 32, 33, 34, 35, 36, 37, 38, 44, 45, 46, 47** | Discus Throw, Shot Put, Javelin Throw | Ludhiana |
| | | **T-42** | High Jump, Long Jump, 100m | Ludhiana |
| | | **T-53** | 100m, 200m, 400m, 800m, 1500m, 5000m | Ludhiana |
| | | **T-54** | 100m, 400m, 800m, 1500m, 5000m | Ludhiana |
| | | **T-63** | 100m, Long Jump | Ludhiana |
| | | **T-64** | 100m, 200m, Long Jump | Ludhiana |
| | | **T-11, 12, 13, 20, 33, 34, 35, 36, 37, 38, 44, 46, 47** | 100m, 200m, 400m, 800m, 1500m, 5000m, Long Jump, High Jump | Ludhiana |
| **2** | **Power Lifting** | **Female Weight Categories**: 41kg, 45kg, 50kg, 55kg, 61kg, 67kg, 73kg, 79kg, 86kg, 86kg+ | Power Lifting Bench Press Entry | Ludhiana |
| | | **Male Weight Categories**: 49kg, 54kg, 59kg, 65kg, 72kg, 80kg, 88kg, 97kg, 107kg, 107kg+ | Power Lifting Bench Press Entry | Ludhiana |
| **3** | **Para Badminton** | **Classifications**: WH-1, WH-2, SL-3, SL-4, SU-5, SS-6 | Men Single, Men Double, Mix Double, Women Single, Women Double | Ludhiana |
| **4** | **Wheel-Chair Basketball** | **Gender Divisions**: Male, Female | Wheel-Chair Basketball Tournament Entry | Ludhiana |

---

### C. Implementation Steps for PPSA Project Creation
1. **Database Schema Adaptation**:
   - Update `athletes` & `official_applications` tables to include `game_name`, `classification_category`, and `event_discipline`.
   - Update registration number prefix to `PPSA-ATH-2026-XXXX` and `PPSA-OFF-2026-XXXX`.
2. **Form UI Styling**:
   - Apply PPSA Brand colors: Header Navy Blue (`#003366`), Gold/Yellow accents, and Punjab State emblem badge.
   - Add dynamic cascading select JS for Game $\rightarrow$ Classification $\rightarrow$ Event.
3. **Mailer Setup**:
   - Configure Resend domain / SMTP headers for `officeparapunjab@gmail.com`.
   - Customize email templates with PPSA letterhead banner and office bearer details.
