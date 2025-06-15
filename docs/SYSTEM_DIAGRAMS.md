# System Diagrams

Dokumen ini berisi berbagai diagram sistem untuk Akudihatinya Backend meliputi Activity Diagram, Sequence Diagram, Class Diagram, dan Architecture Diagram.

## Activity Diagram

### 1. Activity Diagram - Login Process

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

### 2. Activity Diagram - Record Examination Process

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

### 3. Activity Diagram - Generate Report Process

```mermaid
flowchart TD
    Start([Start]) --> SelectReportType[Select Report Type]
    SelectReportType --> SetParameters[Set Report Parameters]
    SetParameters --> ValidateParams{Validate Parameters}
    ValidateParams -->|Invalid| ShowParamError[Show Parameter Error]
    ShowParamError --> SetParameters
    ValidateParams -->|Valid| CheckPermission{Check User Permission}
    CheckPermission -->|Denied| ShowPermError[Show Permission Error]
    ShowPermError --> End([End])
    CheckPermission -->|Allowed| FetchData[Fetch Data from Database]
    FetchData --> CheckDataExists{Data Exists?}
    CheckDataExists -->|No| ShowNoData[Show No Data Message]
    ShowNoData --> End
    CheckDataExists -->|Yes| ProcessData[Process and Format Data]
    ProcessData --> GenerateFile[Generate PDF/Excel File]
    GenerateFile --> SaveFile[Save File to Storage]
    SaveFile --> ProvideDownload[Provide Download Link]
    ProvideDownload --> End
```

## Sequence Diagram

### 1. Sequence Diagram - Authentication Flow

```mermaid
sequenceDiagram
    participant User
    participant Frontend
    participant AuthController
    participant UserModel
    participant Database
    participant TokenService

    User->>Frontend: Enter credentials
    Frontend->>AuthController: POST /api/auth/login
    AuthController->>UserModel: findByUsername()
    UserModel->>Database: SELECT user WHERE username
    Database-->>UserModel: User data
    UserModel-->>AuthController: User object
    AuthController->>AuthController: validatePassword()
    alt Password valid
        AuthController->>TokenService: generateTokens()
        TokenService-->>AuthController: Access & Refresh tokens
        AuthController->>Database: Store refresh token
        AuthController-->>Frontend: 200 OK + tokens
        Frontend-->>User: Redirect to dashboard
    else Password invalid
        AuthController-->>Frontend: 401 Unauthorized
        Frontend-->>User: Show error message
    end
```

### 2. Sequence Diagram - Record Examination Flow

```mermaid
sequenceDiagram
    participant User
    participant Frontend
    participant ExamController
    participant PatientModel
    participant ExaminationModel
    participant StatisticsService
    participant Database

    User->>Frontend: Select patient and enter examination data
    Frontend->>ExamController: POST /api/examinations
    ExamController->>PatientModel: findById()
    PatientModel->>Database: SELECT patient
    Database-->>PatientModel: Patient data
    PatientModel-->>ExamController: Patient object
    ExamController->>ExamController: validateExaminationData()
    alt Data valid
        ExamController->>ExaminationModel: create()
        ExaminationModel->>Database: INSERT examination
        Database-->>ExaminationModel: Examination ID
        ExaminationModel-->>ExamController: Examination object
        ExamController->>StatisticsService: updateCache()
        StatisticsService->>Database: UPDATE statistics cache
        ExamController-->>Frontend: 201 Created
        Frontend-->>User: Show success message
    else Data invalid
        ExamController-->>Frontend: 422 Validation Error
        Frontend-->>User: Show validation errors
    end
```

### 3. Sequence Diagram - Generate Statistics Report

```mermaid
sequenceDiagram
    participant User
    participant Frontend
    participant ReportController
    participant StatisticsService
    participant PDFService
    participant Database
    participant FileStorage

    User->>Frontend: Request statistics report
    Frontend->>ReportController: GET /api/statistics/export
    ReportController->>StatisticsService: getStatistics(params)
    StatisticsService->>Database: Query examinations data
    Database-->>StatisticsService: Raw data
    StatisticsService->>StatisticsService: calculateStatistics()
    StatisticsService-->>ReportController: Processed statistics
    ReportController->>PDFService: generatePDF(data)
    PDFService->>PDFService: createPDFDocument()
    PDFService-->>ReportController: PDF buffer
    ReportController->>FileStorage: saveFile()
    FileStorage-->>ReportController: File path
    ReportController-->>Frontend: Download URL
    Frontend-->>User: Provide download link
```

## Class Diagram

```mermaid
classDiagram
    class User {
        +Long id
        +String username
        +String password
        +String name
        +String profilePicture
        +Role role
        +Long puskesmasId
        +DateTime createdAt
        +DateTime updatedAt
        +validatePassword(String password) Boolean
        +hasRole(Role role) Boolean
        +belongsToPuskesmas(Long puskesmasId) Boolean
    }

    class Puskesmas {
        +Long id
        +String name
        +String address
        +String phone
        +String email
        +Boolean isActive
        +DateTime createdAt
        +DateTime updatedAt
        +activate() void
        +deactivate() void
        +getActiveUsers() List~User~
    }

    class Patient {
        +Long id
        +Long puskesmasId
        +String nik
        +String bpjsNumber
        +String medicalRecordNumber
        +String name
        +String address
        +String phoneNumber
        +Gender gender
        +Date birthDate
        +Integer age
        +List~Integer~ htYears
        +List~Integer~ dmYears
        +DateTime createdAt
        +DateTime updatedAt
        +calculateAge() Integer
        +hasHT() Boolean
        +hasDM() Boolean
        +getLatestHTExamination() HTExamination
        +getLatestDMExamination() DMExamination
    }

    class HTExamination {
        +Long id
        +Long patientId
        +Long puskesmasId
        +Date examinationDate
        +Integer systolic
        +Integer diastolic
        +Integer year
        +Integer month
        +Boolean isArchived
        +DateTime createdAt
        +DateTime updatedAt
        +isControlled() Boolean
        +getBloodPressureCategory() String
        +archive() void
    }

    class DMExamination {
        +Long id
        +Long patientId
        +Long puskesmasId
        +Date examinationDate
        +ExaminationType examinationType
        +Decimal result
        +Integer year
        +Integer month
        +Boolean isArchived
        +DateTime createdAt
        +DateTime updatedAt
        +isControlled() Boolean
        +getControlStatus() String
        +archive() void
    }

    class YearlyTarget {
        +Long id
        +Long puskesmasId
        +DiseaseType diseaseType
        +Integer year
        +Integer targetCount
        +DateTime createdAt
        +DateTime updatedAt
        +calculateAchievement() Double
        +isAchieved() Boolean
    }

    class StatisticsService {
        +calculateHTStatistics(Long puskesmasId, Integer year, Integer month) HTStatistics
        +calculateDMStatistics(Long puskesmasId, Integer year, Integer month) DMStatistics
        +getGlobalStatistics(Integer year, Integer month) GlobalStatistics
        +updateCache(Long puskesmasId, DiseaseType type, Integer year, Integer month) void
        +clearCache(Long puskesmasId) void
    }

    class ReportService {
        +generateHTReport(ReportParams params) Report
        +generateDMReport(ReportParams params) Report
        +generateGlobalReport(ReportParams params) Report
        +exportToPDF(Report report) File
        +exportToExcel(Report report) File
    }

    class AuthService {
        +login(String username, String password) AuthResult
        +logout(String token) void
        +refreshToken(String refreshToken) AuthResult
        +validateToken(String token) Boolean
        +generateTokens(User user) TokenPair
    }

    %% Relationships
    User ||--o{ Puskesmas : belongs_to
    Puskesmas ||--o{ Patient : has_many
    Puskesmas ||--o{ HTExamination : has_many
    Puskesmas ||--o{ DMExamination : has_many
    Puskesmas ||--o{ YearlyTarget : has_many
    Patient ||--o{ HTExamination : has_many
    Patient ||--o{ DMExamination : has_many

    %% Enums
    class Role {
        <<enumeration>>
        ADMIN
        PUSKESMAS
    }

    class Gender {
        <<enumeration>>
        MALE
        FEMALE
    }

    class ExaminationType {
        <<enumeration>>
        HBA1C
        GDP
        GD2JPP
        GDSP
    }

    class DiseaseType {
        <<enumeration>>
        HT
        DM
    }
```

## Architecture Diagram

### 1. System Architecture Overview

```mermaid
graph TB
    subgraph "Client Layer"
        WebApp[Web Application]
        MobileApp[Mobile Application]
        AdminPanel[Admin Panel]
    end

    subgraph "API Gateway"
        Gateway[Laravel API Gateway]
        Auth[Authentication Middleware]
        RateLimit[Rate Limiting]
        CORS[CORS Middleware]
    end

    subgraph "Application Layer"
        Controllers[Controllers]
        Services[Business Services]
        Repositories[Data Repositories]
        Validators[Data Validators]
    end

    subgraph "Core Services"
        AuthService[Authentication Service]
        StatService[Statistics Service]
        ReportService[Report Generation Service]
        NotificationService[Notification Service]
        CacheService[Cache Service]
    end

    subgraph "Data Layer"
        MySQL[(MySQL Database)]
        Redis[(Redis Cache)]
        FileStorage[File Storage]
    end

    subgraph "External Services"
        EmailService[Email Service]
        PDFGenerator[PDF Generator]
        ExcelGenerator[Excel Generator]
    end

    %% Connections
    WebApp --> Gateway
    MobileApp --> Gateway
    AdminPanel --> Gateway

    Gateway --> Auth
    Auth --> RateLimit
    RateLimit --> CORS
    CORS --> Controllers

    Controllers --> Services
    Services --> Repositories
    Services --> Validators

    Services --> AuthService
    Services --> StatService
    Services --> ReportService
    Services --> NotificationService
    Services --> CacheService

    Repositories --> MySQL
    CacheService --> Redis
    ReportService --> FileStorage

    ReportService --> PDFGenerator
    ReportService --> ExcelGenerator
    NotificationService --> EmailService
```

### 2. Database Architecture

```mermaid
graph TB
    subgraph "Application Servers"
        App1[Laravel App 1]
        App2[Laravel App 2]
        App3[Laravel App 3]
    end

    subgraph "Load Balancer"
        LB[Nginx Load Balancer]
    end

    subgraph "Database Cluster"
        Master[(MySQL Master)]
        Slave1[(MySQL Slave 1)]
        Slave2[(MySQL Slave 2)]
    end

    subgraph "Cache Layer"
        RedisCluster[(Redis Cluster)]
    end

    subgraph "Storage"
        FileSystem[File System Storage]
        Backup[Backup Storage]
    end

    LB --> App1
    LB --> App2
    LB --> App3

    App1 --> Master
    App2 --> Master
    App3 --> Master

    App1 --> Slave1
    App2 --> Slave1
    App3 --> Slave2

    Master --> Slave1
    Master --> Slave2

    App1 --> RedisCluster
    App2 --> RedisCluster
    App3 --> RedisCluster

    App1 --> FileSystem
    App2 --> FileSystem
    App3 --> FileSystem

    Master --> Backup
    Slave1 --> Backup
    Slave2 --> Backup
```

## Deployment Diagram

```mermaid
graph TB
    subgraph "Production Environment"
        subgraph "Web Tier"
            LB[Load Balancer]
            Web1[Web Server 1]
            Web2[Web Server 2]
        end

        subgraph "Application Tier"
            App1[Laravel App 1]
            App2[Laravel App 2]
            Queue[Queue Worker]
            Scheduler[Task Scheduler]
        end

        subgraph "Database Tier"
            DBMaster[(Database Master)]
            DBSlave[(Database Slave)]
            Cache[(Redis Cache)]
        end

        subgraph "Storage Tier"
            Files[File Storage]
            Logs[Log Storage]
            Backup[Backup Storage]
        end
    end

    subgraph "Monitoring"
        Monitor[Application Monitoring]
        Alerts[Alert System]
    end

    Internet --> LB
    LB --> Web1
    LB --> Web2
    Web1 --> App1
    Web2 --> App2
    App1 --> DBMaster
    App2 --> DBMaster
    App1 --> DBSlave
    App2 --> DBSlave
    App1 --> Cache
    App2 --> Cache
    App1 --> Files
    App2 --> Files
    Queue --> DBMaster
    Scheduler --> DBMaster
    DBMaster --> Backup
    App1 --> Monitor
    App2 --> Monitor
    Monitor --> Alerts
```

## Component Interaction Flow

```mermaid
flowchart LR
    subgraph "Frontend"
        UI[User Interface]
        State[State Management]
        HTTP[HTTP Client]
    end

    subgraph "Backend API"
        Route[Routes]
        Middleware[Middleware Stack]
        Controller[Controllers]
        Service[Business Logic]
        Repository[Data Access]
    end

    subgraph "Data Storage"
        DB[(Database)]
        Cache[(Cache)]
        Files[File System]
    end

    UI --> State
    State --> HTTP
    HTTP --> Route
    Route --> Middleware
    Middleware --> Controller
    Controller --> Service
    Service --> Repository
    Repository --> DB
    Repository --> Cache
    Service --> Files

    %% Response flow
    DB --> Repository
    Cache --> Repository
    Files --> Service
    Repository --> Service
    Service --> Controller
    Controller --> Middleware
    Middleware --> Route
    Route --> HTTP
    HTTP --> State
    State --> UI
```