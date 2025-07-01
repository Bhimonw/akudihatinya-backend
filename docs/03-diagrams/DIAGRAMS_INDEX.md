# Diagrams Index - Akudihatinya System

## Overview
This directory contains comprehensive system design diagrams for the Akudihatinya health data management system. All diagrams are created in SVG format for scalability and easy viewing.

## Available Diagrams

### 1. Entity Relationship Diagram (ERD)
**File:** `ERD_COMPREHENSIVE.svg`

**Description:** Complete database schema showing all entities, attributes, relationships, and constraints.

**Entities Covered:**
- Users (Admin, Puskesmas Officers)
- Puskesmas (Health Centers)
- Patients
- HT_Examinations (Hypertension)
- DM_Examinations (Diabetes Mellitus)
- Yearly_Targets
- User_Refresh_Tokens
- Monthly_Statistics_Cache

**Key Features:**
- Primary and foreign key relationships
- Data types and constraints
- Indexing strategies
- Referential integrity

---

### 2. Use Case Diagram
**File:** `USECASE_DIAGRAM_COMPREHENSIVE.svg`

**Description:** Complete system functionality overview showing all actors and their interactions with the system.

**Actors:**
- **Admin (Dinas Kesehatan):** System administration, user management, target setting
- **Petugas Puskesmas:** Patient management, examination recording, local reporting

**Modules Covered:**
1. **Authentication & Authorization** (4 use cases)
   - Login, Logout, Refresh Token, Change Password

2. **User Management - Admin Only** (5 use cases)
   - Create, View, Update, Delete User, Reset Password

3. **Yearly Targets - Admin Only** (5 use cases)
   - Set, View, Update, Delete Target, Monitor Achievement

4. **Patient Management** (7 use cases)
   - Register, View, Update, Delete Patient, Add/Remove Exam Year, Export Data

5. **HT Examinations** (5 use cases)
   - Record, View, Update, Delete Exam, Calculate BP Control

6. **DM Examinations** (5 use cases)
   - Record, View, Update, Delete Exam, Calculate DM Control

7. **Dashboard & Statistics** (7 use cases)
   - Admin/Puskesmas Dashboard, Statistics, Real-time Data, Reports, Export, Performance Monitoring

8. **Export & Reporting** (7 use cases)
   - PDF/Excel Export, Monthly/Quarterly/Annual Reports, Custom Reports, Archive Management

9. **Profile Management** (2 use cases)
   - Update Profile, Upload Photo

**Total:** 35 use cases across 7 modules

---

### 3. Activity Diagrams
**File:** `ACTIVITY_DIAGRAMS_COMPREHENSIVE.svg`

**Description:** Detailed workflow processes showing the flow of activities within the system.

**Processes Covered:**

#### 3.1 User Authentication Process
- **Actors:** User, System, Database
- **Flow:** Credential validation, database verification, token generation
- **Features:** Input validation, error handling, security checks

#### 3.2 Patient Registration Process
- **Actors:** Petugas Puskesmas, System, Database
- **Flow:** Form submission, data validation, patient ID generation
- **Features:** Data validation, duplicate checking, success confirmation

#### 3.3 HT Examination Recording
- **Actors:** Petugas Puskesmas, System, Database
- **Flow:** Patient selection, BP recording, control calculation
- **Features:** Blood pressure validation, automatic control determination

#### 3.4 Statistics Generation Process
- **Actors:** User, System, Cache, Database
- **Flow:** Request handling, cache checking, data aggregation
- **Features:** Caching strategy, performance optimization, real-time updates

#### 3.5 Report Export Process
- **Actors:** User, System, File System
- **Flow:** Export request, data processing, file generation
- **Features:** Multiple formats (PDF, Excel), file management, download links

#### 3.6 DM Examination Recording
- **Actors:** Petugas Puskesmas, System, Database
- **Flow:** Patient selection, glucose/HbA1c recording, control calculation
- **Features:** Multiple glucose types, HbA1c validation, control logic

#### 3.7 Admin Dashboard Data Loading
- **Actors:** Admin, System, Database, Cache
- **Flow:** Dashboard access, cache validation, data aggregation
- **Features:** Performance optimization, real-time statistics, caching strategy

---

### 4. Sequence Diagrams
**File:** `SEQUENCE_DIAGRAMS_COMPREHENSIVE.svg`

**Description:** Detailed interaction flows between system components showing message exchanges over time.

**Sequences Covered:**

#### 4.1 User Login Sequence
- **Components:** User, Frontend, AuthController, Database
- **Flow:** Credential submission, validation, token generation, response
- **Features:** Laravel Sanctum integration, security validation

#### 4.2 Patient Registration Sequence
- **Components:** Petugas, Frontend, PatientController, PatientService, Database
- **Flow:** Form submission, service layer processing, database operations
- **Features:** Service layer architecture, data validation, ID generation

#### 4.3 HT Examination Recording
- **Components:** Petugas, Frontend, HTController, HTService, Database
- **Flow:** Examination data submission, BP control calculation, statistics update
- **Features:** Business logic separation, automatic calculations

#### 4.4 Dashboard Statistics Loading
- **Components:** Admin, Frontend, StatsController, Cache, Database
- **Flow:** Statistics request, cache checking, data aggregation, response formatting
- **Features:** Caching strategy, performance optimization

#### 4.5 Report Export Sequence
- **Components:** User, Frontend, ExportController, ExportService, Database, FileSystem
- **Flow:** Export request, data fetching, file generation, download link
- **Features:** Multiple export formats, file management

#### 4.6 DM Examination Recording
- **Components:** Petugas, Frontend, DMController, DMService, Database
- **Flow:** DM examination submission, glucose validation, control calculation
- **Features:** Multiple glucose types, HbA1c handling, control logic

---

## Technical Architecture

### Framework & Technologies
- **Backend:** Laravel 11
- **Database:** MySQL 8.0+
- **Authentication:** Laravel Sanctum
- **Caching:** Redis
- **Export:** PDF (DomPDF), Excel (PhpSpreadsheet)
- **Architecture:** MVC + Service Layer Pattern

### Key Design Patterns
- **Repository Pattern:** Data access abstraction
- **Service Layer:** Business logic separation
- **Factory Pattern:** Object creation
- **Observer Pattern:** Event handling
- **Strategy Pattern:** Export format handling

### Security Features
- Token-based authentication
- Role-based access control (RBAC)
- Input validation and sanitization
- SQL injection prevention
- CSRF protection
- Rate limiting

### Performance Optimizations
- Database indexing
- Query optimization
- Caching strategies
- Lazy loading
- Pagination
- Background job processing

## Business Rules

### HT (Hypertension) Control
- **Controlled:** Systolic < 140 mmHg AND Diastolic < 90 mmHg
- **Not Controlled:** Systolic ≥ 140 mmHg OR Diastolic ≥ 90 mmHg

### DM (Diabetes Mellitus) Control
- **Blood Sugar Control:**
  - Fasting: < 126 mg/dL
  - Random: < 200 mg/dL
  - Post-meal: < 200 mg/dL
- **HbA1c Control:** < 7%

### Visit Rules
- Minimum 3 visits per year for control determination
- Visit intervals: minimum 1 month apart
- Annual target tracking and achievement calculation

### Data Integrity
- Patient uniqueness per Puskesmas
- Examination date validation
- Target year constraints
- User role permissions

## File Organization

```
docs/03-diagrams/
├── DIAGRAMS_INDEX.md                    # This file
├── ERD_COMPREHENSIVE.svg                # Entity Relationship Diagram
├── USECASE_DIAGRAM_COMPREHENSIVE.svg    # Use Case Diagram
├── ACTIVITY_DIAGRAMS_COMPREHENSIVE.svg  # Activity Diagrams
└── SEQUENCE_DIAGRAMS_COMPREHENSIVE.svg  # Sequence Diagrams
```

## Viewing Instructions

### SVG Files
- **Web Browser:** Open directly in any modern browser
- **VS Code:** Install "SVG Viewer" extension
- **Image Viewers:** Most support SVG format
- **Print:** SVG files are vector-based and print at high quality

### Recommended Tools
- **Viewing:** Chrome, Firefox, Edge, Safari
- **Editing:** Inkscape, Adobe Illustrator, VS Code
- **Documentation:** Markdown viewers, GitHub, GitLab

## Maintenance

### Update Schedule
- **Major Updates:** When system architecture changes
- **Minor Updates:** When new features are added
- **Review:** Monthly during development phase

### Version Control
- All diagrams are version-controlled with the codebase
- Changes should be documented in commit messages
- Maintain consistency between code and diagrams

## Related Documentation

- **System Architecture:** `docs/02-architecture/COMPREHENSIVE_SYSTEM_DESIGN.md`
- **API Documentation:** `docs/01-api/`
- **Database Schema:** `database/migrations/`
- **Code Quality:** `docs/CODE_QUALITY_IMPROVEMENTS_COMPREHENSIVE.md`

---

**Last Updated:** December 2024  
**System Version:** Laravel 11  
**Documentation Version:** 1.0  
**Author:** System Architecture Team