# 🏗️ System Architecture & Design

<p align="center">
  <img src="https://img.shields.io/badge/Architecture-Complete-success?style=for-the-badge" alt="Architecture">
  <img src="https://img.shields.io/badge/Diagrams-UML-blue?style=for-the-badge" alt="UML">
  <img src="https://img.shields.io/badge/Design-System-orange?style=for-the-badge" alt="Design">
</p>

<h3>📊 Comprehensive System Design Documentation</h3>
<p><em>Complete architectural overview including ERD, use cases, state diagrams, data flow, and system diagrams</em></p>

---

## 📋 Table of Contents

1. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
2. [Use Case Diagrams](#use-case-diagrams)
3. [System Activity & Sequence Diagrams](#system-activity--sequence-diagrams)
4. [State Diagrams](#state-diagrams)
5. [Data Flow Diagrams](#data-flow-diagrams)
6. [Class & Architecture Diagrams](#class--architecture-diagrams)

---

## 🗄️ Entity Relationship Diagram (ERD)

### Database Schema Overview

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        enum role
        bigint puskesmas_id FK
        string remember_token
        timestamps created_at
        timestamps updated_at
    }
    
    puskesmas {
        bigint id PK
        string name UK
        string address
        string phone
        string email UK
        timestamps created_at
        timestamps updated_at
    }
    
    patients {
        bigint id PK
        string name
        string nik UK
        date birth_date
        enum gender
        string address
        string phone
        bigint puskesmas_id FK
        json ht_years
        json dm_years
        timestamps created_at
        timestamps updated_at
    }
    
    ht_examinations {
        bigint id PK
        bigint patient_id FK
        bigint puskesmas_id FK
        int systolic
        int diastolic
        enum control_status
        date examination_date
        timestamps created_at
        timestamps updated_at
    }
    
    dm_examinations {
        bigint id PK
        bigint patient_id FK
        bigint puskesmas_id FK
        enum examination_type
        decimal result
        enum control_status
        date examination_date
        timestamps created_at
        timestamps updated_at
    }
    
    yearly_targets {
        bigint id PK
        bigint puskesmas_id FK
        enum disease_type
        int year
        int target_count
        timestamps created_at
        timestamps updated_at
    }
    
    monthly_statistics {
        bigint id PK
        bigint puskesmas_id FK
        enum disease_type
        int year
        int month
        int male_patients
        int female_patients
        int total_patients
        int standard_patients
        int non_standard_patients
        decimal achievement_percentage
        timestamps created_at
        timestamps updated_at
    }
    
    archived_examinations {
        bigint id PK
        bigint original_id
        string examination_type
        bigint patient_id FK
        bigint puskesmas_id FK
        json examination_data
        date original_examination_date
        int archived_year
        timestamps created_at
        timestamps updated_at
    }

    %% Relationships
    users ||--o{ puskesmas : belongs_to
    puskesmas ||--o{ patients : has_many
    puskesmas ||--o{ ht_examinations : has_many
    puskesmas ||--o{ dm_examinations : has_many
    puskesmas ||--o{ yearly_targets : has_many
    puskesmas ||--o{ monthly_statistics : has_many
    puskesmas ||--o{ archived_examinations : has_many
    
    patients ||--o{ ht_examinations : has_many
    patients ||--o{ dm_examinations : has_many
    patients ||--o{ archived_examinations : has_many
```

---

## 👥 Use Case Diagrams

### Main System Use Case Diagram

```mermaid
graph TB
    %% Actors
    Admin[👤 Admin]
    PuskesmasUser[👤 User Puskesmas]
    System[🖥️ System]
    
    %% Admin Use Cases
    subgraph "Admin Use Cases"
        UC1[Login]
        UC2[Manage Puskesmas]
        UC3[View All Statistics]
        UC4[Export Global Reports]
        UC5[Manage Users]
        UC6[Set Yearly Targets]
        UC7[View System Analytics]
        UC8[Manage System Settings]
    end
    
    %% Puskesmas User Use Cases
    subgraph "Puskesmas User Use Cases"
        UC9[Login]
        UC10[Manage Patients]
        UC11[Record HT Examinations]
        UC12[Record DM Examinations]
        UC13[View Puskesmas Statistics]
        UC14[Export Puskesmas Reports]
        UC15[Update Profile]
        UC16[View Patient History]
        UC17[Archive Old Data]
    end
    
    %% System Use Cases
    subgraph "System Use Cases"
        UC18[Generate Monthly Statistics]
        UC19[Cache Statistics Data]
        UC20[Send Notifications]
        UC21[Backup Data]
        UC22[Clean Archived Data]
    end
    
    %% Relationships
    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC4
    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    
    PuskesmasUser --> UC9
    PuskesmasUser --> UC10
    PuskesmasUser --> UC11
    PuskesmasUser --> UC12
    PuskesmasUser --> UC13
    PuskesmasUser --> UC14
    PuskesmasUser --> UC15
    PuskesmasUser --> UC16
    PuskesmasUser --> UC17
    
    System --> UC18
    System --> UC19
    System --> UC20
    System --> UC21
    System --> UC22
```

### Account Management Use Case

```mermaid
graph TB
    User[👤 User]
    Admin[👤 Admin]
    
    subgraph "Account Management"
        UC1[Register Account]
        UC2[Login]
        UC3[Logout]
        UC4[Update Profile]
        UC5[Change Password]
        UC6[Reset Password]
        UC7[Manage User Accounts]
        UC8[Assign Roles]
    end
    
    User --> UC1
    User --> UC2
    User --> UC3
    User --> UC4
    User --> UC5
    User --> UC6
    
    Admin --> UC7
    Admin --> UC8
    Admin --> UC2
    Admin --> UC3
    Admin --> UC4
    Admin --> UC5
```

---

## 🔄 System Activity & Sequence Diagrams

### Login Process Activity Diagram

```mermaid
flowchart TD
    Start([Start]) --> InputCredentials[Input Username & Password]
    InputCredentials --> ValidateInput{Validate Input}
    ValidateInput -->|Invalid| ShowError[Show Error Message]
    ShowError --> InputCredentials
    ValidateInput -->|Valid| CheckCredentials{Check Credentials}
    CheckCredentials -->|Invalid| ShowAuthError[Show Authentication Error]
    ShowAuthError --> InputCredentials
    CheckCredentials -->|Valid| GenerateTokens[Generate Access & Refresh Tokens]
    GenerateTokens --> CheckRole{Check User Role}
    CheckRole -->|Admin| AdminDashboard[Redirect to Admin Dashboard]
    CheckRole -->|Puskesmas| PuskesmasDashboard[Redirect to Puskesmas Dashboard]
    AdminDashboard --> End([End])
    PuskesmasDashboard --> End
```

### Record Examination Process Activity Diagram

```mermaid
flowchart TD
    Start([Start]) --> SelectPatient[Select Patient]
    SelectPatient --> CheckPatientExists{Patient Exists?}
    CheckPatientExists -->|No| CreatePatient[Create New Patient]
    CreatePatient --> SelectExamType[Select Examination Type]
    CheckPatientExists -->|Yes| SelectExamType
    SelectExamType --> HTExam{HT Examination?}
    HTExam -->|Yes| InputBP[Input Blood Pressure]
    HTExam -->|No| InputDMType[Select DM Examination Type]
    InputDMType --> InputDMResult[Input DM Result]
    InputBP --> ValidateBP{Validate BP Values}
    InputDMResult --> ValidateDM{Validate DM Values}
    ValidateBP -->|Invalid| ShowBPError[Show BP Error]
    ValidateDM -->|Invalid| ShowDMError[Show DM Error]
    ShowBPError --> InputBP
    ShowDMError --> InputDMResult
    ValidateBP -->|Valid| CalculateHTStatus[Calculate HT Control Status]
    ValidateDM -->|Valid| CalculateDMStatus[Calculate DM Control Status]
    CalculateHTStatus --> SaveExamination[Save Examination]
    CalculateDMStatus --> SaveExamination
    SaveExamination --> UpdateStatistics[Update Statistics Cache]
    UpdateStatistics --> ShowSuccess[Show Success Message]
    ShowSuccess --> End([End])
```

### API Request Sequence Diagram

```mermaid
sequenceDiagram
    participant Client
    participant API
    participant Auth
    participant Controller
    participant Service
    participant Repository
    participant Database
    
    Client->>API: HTTP Request
    API->>Auth: Validate Token
    Auth-->>API: Token Valid
    API->>Controller: Route to Controller
    Controller->>Service: Business Logic
    Service->>Repository: Data Access
    Repository->>Database: Query
    Database-->>Repository: Result
    Repository-->>Service: Data
    Service-->>Controller: Processed Data
    Controller-->>API: Response
    API-->>Client: HTTP Response
```

---

## 🔀 State Diagrams

### User Authentication State Diagram

```mermaid
stateDiagram-v2
    [*] --> Unauthenticated
    
    Unauthenticated --> Authenticating : login_attempt
    Authenticating --> Authenticated : login_success
    Authenticating --> Unauthenticated : login_failed
    Authenticating --> Unauthenticated : invalid_credentials
    
    Authenticated --> TokenRefreshing : token_expired
    TokenRefreshing --> Authenticated : refresh_success
    TokenRefreshing --> Unauthenticated : refresh_failed
    
    Authenticated --> Unauthenticated : logout
    Authenticated --> Unauthenticated : token_revoked
    
    state Authenticated {
        [*] --> Active
        Active --> Idle : no_activity
        Idle --> Active : user_activity
        Active --> SessionExpiring : session_timeout_warning
        SessionExpiring --> Active : user_activity
        SessionExpiring --> Unauthenticated : session_expired
    }
```

### Patient Management State Diagram

```mermaid
stateDiagram-v2
    [*] --> NotRegistered
    
    NotRegistered --> Registering : start_registration
    Registering --> ValidationPending : submit_data
    ValidationPending --> Registered : validation_success
    ValidationPending --> Registering : validation_failed
    
    Registered --> Active : activate_patient
    Active --> UnderTreatment : start_treatment
    UnderTreatment --> Active : treatment_completed
    
    Active --> Inactive : deactivate_patient
    Inactive --> Active : reactivate_patient
    
    state UnderTreatment {
        [*] --> HTTreatment
        [*] --> DMTreatment
        [*] --> CombinedTreatment
        
        HTTreatment --> Controlled : ht_controlled
        HTTreatment --> Uncontrolled : ht_uncontrolled
        
        DMTreatment --> Controlled : dm_controlled
        DMTreatment --> Uncontrolled : dm_uncontrolled
        
        CombinedTreatment --> PartiallyControlled : partial_control
        CombinedTreatment --> FullyControlled : full_control
        CombinedTreatment --> Uncontrolled : no_control
    }
```

### Examination Process State Diagram

```mermaid
stateDiagram-v2
    [*] --> NotStarted
    
    NotStarted --> InProgress : start_examination
    InProgress --> DataEntry : enter_data
    DataEntry --> Validation : validate_data
    Validation --> DataEntry : validation_failed
    Validation --> Processing : validation_success
    Processing --> Completed : save_success
    Processing --> DataEntry : save_failed
    
    Completed --> [*]
    
    state DataEntry {
        [*] --> PatientSelection
        PatientSelection --> ExaminationType : patient_selected
        ExaminationType --> HTData : ht_selected
        ExaminationType --> DMData : dm_selected
        
        HTData --> BloodPressure
        DMData --> GlucoseLevel
        
        BloodPressure --> [*]
        GlucoseLevel --> [*]
    }
```

---

## 📊 Data Flow Diagrams

### Context Diagram (Level 0)

```mermaid
flowchart TD
    Admin[👤 Admin] 
    PuskesmasUser[👤 Puskesmas User]
    System[🖥️ Akudihatinya System]
    
    Admin -->|Login, Manage Users, View Reports| System
    System -->|Dashboard, Statistics, Reports| Admin
    
    PuskesmasUser -->|Login, Patient Data, Examinations| System
    System -->|Patient Records, Statistics, Reports| PuskesmasUser
```

### Level 1 Data Flow Diagram

```mermaid
flowchart TD
    Admin[👤 Admin]
    PuskesmasUser[👤 Puskesmas User]
    
    subgraph "Akudihatinya System"
        Auth[🔐 Authentication]
        PatientMgmt[👥 Patient Management]
        ExamMgmt[🩺 Examination Management]
        StatsMgmt[📊 Statistics Management]
        ReportMgmt[📋 Report Management]
    end
    
    UserDB[(👥 Users DB)]
    PatientDB[(👤 Patients DB)]
    ExamDB[(🩺 Examinations DB)]
    StatsDB[(📊 Statistics DB)]
    
    Admin --> Auth
    PuskesmasUser --> Auth
    Auth --> UserDB
    
    PuskesmasUser --> PatientMgmt
    PatientMgmt --> PatientDB
    
    PuskesmasUser --> ExamMgmt
    ExamMgmt --> ExamDB
    ExamMgmt --> PatientDB
    
    ExamMgmt --> StatsMgmt
    StatsMgmt --> StatsDB
    
    Admin --> ReportMgmt
    PuskesmasUser --> ReportMgmt
    ReportMgmt --> StatsDB
    ReportMgmt --> ExamDB
    ReportMgmt --> PatientDB
```

### Level 2 Data Flow Diagram - Examination Management

```mermaid
flowchart TD
    User[👤 User]
    
    subgraph "Examination Management"
        ValidateData[✅ Validate Data]
        ProcessHT[🩺 Process HT Exam]
        ProcessDM[💉 Process DM Exam]
        UpdateStats[📊 Update Statistics]
        GenerateReport[📋 Generate Report]
    end
    
    PatientDB[(👤 Patients)]
    HTExamDB[(🩺 HT Examinations)]
    DMExamDB[(💉 DM Examinations)]
    StatsDB[(📊 Statistics)]
    
    User -->|Examination Data| ValidateData
    ValidateData -->|Valid HT Data| ProcessHT
    ValidateData -->|Valid DM Data| ProcessDM
    
    ProcessHT --> HTExamDB
    ProcessDM --> DMExamDB
    
    ProcessHT -->|Patient Info| PatientDB
    ProcessDM -->|Patient Info| PatientDB
    
    ProcessHT --> UpdateStats
    ProcessDM --> UpdateStats
    UpdateStats --> StatsDB
    
    UpdateStats --> GenerateReport
    GenerateReport -->|Report| User
```

---

## 🏛️ Class & Architecture Diagrams

### System Architecture Overview

```mermaid
graph TB
    subgraph "Frontend Layer"
        WebApp[🌐 Web Application]
        MobileApp[📱 Mobile App]
        API_Client[🔌 API Client]
    end
    
    subgraph "API Gateway"
        Router[🚦 Router]
        Middleware[🛡️ Middleware]
        Auth[🔐 Authentication]
    end
    
    subgraph "Application Layer"
        Controllers[🎮 Controllers]
        Services[⚙️ Services]
        Repositories[📚 Repositories]
    end
    
    subgraph "Domain Layer"
        Models[📋 Models]
        Events[📡 Events]
        Jobs[⚡ Jobs]
    end
    
    subgraph "Infrastructure Layer"
        Database[🗄️ Database]
        Cache[⚡ Cache]
        Storage[💾 File Storage]
        Queue[📬 Queue System]
    end
    
    WebApp --> Router
    MobileApp --> Router
    API_Client --> Router
    
    Router --> Middleware
    Middleware --> Auth
    Auth --> Controllers
    
    Controllers --> Services
    Services --> Repositories
    Services --> Events
    Services --> Jobs
    
    Repositories --> Models
    Models --> Database
    
    Events --> Queue
    Jobs --> Queue
    
    Services --> Cache
    Controllers --> Storage
```

### Core Models Class Diagram

```mermaid
classDiagram
    class User {
        +id: bigint
        +name: string
        +email: string
        +role: enum
        +puskesmas_id: bigint
        +isAdmin(): bool
        +isPuskesmasUser(): bool
        +puskesmas(): BelongsTo
    }
    
    class Puskesmas {
        +id: bigint
        +name: string
        +address: string
        +phone: string
        +email: string
        +users(): HasMany
        +patients(): HasMany
        +htExaminations(): HasMany
        +dmExaminations(): HasMany
        +yearlyTargets(): HasMany
        +monthlyStatistics(): HasMany
    }
    
    class Patient {
        +id: bigint
        +name: string
        +nik: string
        +birth_date: date
        +gender: enum
        +address: string
        +phone: string
        +puskesmas_id: bigint
        +ht_years: json
        +dm_years: json
        +getAge(): int
        +puskesmas(): BelongsTo
        +htExaminations(): HasMany
        +dmExaminations(): HasMany
    }
    
    class HTExamination {
        +id: bigint
        +patient_id: bigint
        +puskesmas_id: bigint
        +systolic: int
        +diastolic: int
        +control_status: enum
        +examination_date: date
        +isControlled(): bool
        +patient(): BelongsTo
        +puskesmas(): BelongsTo
    }
    
    class DMExamination {
        +id: bigint
        +patient_id: bigint
        +puskesmas_id: bigint
        +examination_type: enum
        +result: decimal
        +control_status: enum
        +examination_date: date
        +isControlled(): bool
        +patient(): BelongsTo
        +puskesmas(): BelongsTo
    }
    
    class YearlyTarget {
        +id: bigint
        +puskesmas_id: bigint
        +disease_type: enum
        +year: int
        +target_count: int
        +puskesmas(): BelongsTo
    }
    
    class MonthlyStatistic {
        +id: bigint
        +puskesmas_id: bigint
        +disease_type: enum
        +year: int
        +month: int
        +male_patients: int
        +female_patients: int
        +total_patients: int
        +standard_patients: int
        +non_standard_patients: int
        +achievement_percentage: decimal
        +puskesmas(): BelongsTo
    }
    
    User ||--o{ Puskesmas : belongs_to
    Puskesmas ||--o{ Patient : has_many
    Puskesmas ||--o{ HTExamination : has_many
    Puskesmas ||--o{ DMExamination : has_many
    Puskesmas ||--o{ YearlyTarget : has_many
    Puskesmas ||--o{ MonthlyStatistic : has_many
    
    Patient ||--o{ HTExamination : has_many
    Patient ||--o{ DMExamination : has_many
```

---

## 📝 Architecture Notes

### Design Principles

1. **Separation of Concerns**: Clear separation between controllers, services, and repositories
2. **Single Responsibility**: Each class has a single, well-defined purpose
3. **Dependency Injection**: Services and repositories are injected for better testability
4. **Repository Pattern**: Data access is abstracted through repository interfaces
5. **Service Layer**: Business logic is encapsulated in service classes

### Key Architectural Decisions

- **Laravel Framework**: Provides robust foundation with built-in features
- **RESTful API Design**: Consistent and predictable API endpoints
- **Token-based Authentication**: Stateless authentication using Laravel Sanctum
- **Database Normalization**: Properly normalized database structure
- **Caching Strategy**: Strategic caching for performance optimization
- **Event-driven Architecture**: Events and listeners for decoupled operations

### Performance Considerations

- **Database Indexing**: Proper indexes on frequently queried columns
- **Query Optimization**: Efficient database queries with eager loading
- **Caching**: Redis caching for frequently accessed data
- **API Rate Limiting**: Protection against abuse and overload
- **Background Jobs**: Heavy operations processed asynchronously

---

*This document provides a comprehensive overview of the system architecture and design. For implementation details, refer to the specific feature documentation.*