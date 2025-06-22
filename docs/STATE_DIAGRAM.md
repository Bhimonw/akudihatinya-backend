# 🔀 State Diagram

Dokumen ini berisi State Diagram untuk sistem Akudihatinya Backend yang menunjukkan berbagai state dan transisi dalam sistem.

## User Authentication State Diagram

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

## Patient Management State Diagram

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
    
    Active --> Archived : archive_patient
    Inactive --> Archived : archive_patient
    
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
        
        Controlled --> Uncontrolled : condition_worsened
        Uncontrolled --> Controlled : condition_improved
        PartiallyControlled --> FullyControlled : improvement
        PartiallyControlled --> Uncontrolled : deterioration
        FullyControlled --> PartiallyControlled : partial_relapse
    }
```

## Examination Process State Diagram

```mermaid
stateDiagram-v2
    [*] --> ExaminationNotStarted
    
    ExaminationNotStarted --> PatientSelection : select_patient
    PatientSelection --> ExaminationTypeSelection : patient_selected
    PatientSelection --> ExaminationNotStarted : cancel_selection
    
    ExaminationTypeSelection --> HTExamination : select_ht
    ExaminationTypeSelection --> DMExamination : select_dm
    ExaminationTypeSelection --> PatientSelection : back_to_patient
    
    HTExamination --> DataEntry : start_ht_exam
    DMExamination --> DataEntry : start_dm_exam
    
    DataEntry --> Validation : submit_data
    Validation --> DataEntry : validation_failed
    Validation --> Processing : validation_passed
    
    Processing --> Completed : processing_success
    Processing --> Error : processing_failed
    
    Error --> DataEntry : retry
    Error --> ExaminationNotStarted : cancel
    
    Completed --> ExaminationNotStarted : new_examination
    Completed --> [*] : finish
    
    state DataEntry {
        [*] --> EnteringBasicInfo
        EnteringBasicInfo --> EnteringMeasurements : basic_info_complete
        EnteringMeasurements --> ReviewingData : measurements_complete
        ReviewingData --> EnteringBasicInfo : edit_basic_info
        ReviewingData --> EnteringMeasurements : edit_measurements
        ReviewingData --> [*] : data_confirmed
    }
    
    state Processing {
        [*] --> CalculatingStatus
        CalculatingStatus --> UpdatingStatistics : status_calculated
        UpdatingStatistics --> SavingData : statistics_updated
        SavingData --> [*] : data_saved
    }
```

## Report Generation State Diagram

```mermaid
stateDiagram-v2
    [*] --> ReportNotRequested
    
    ReportNotRequested --> ParameterSelection : request_report
    ParameterSelection --> ParameterValidation : submit_parameters
    ParameterValidation --> ParameterSelection : validation_failed
    ParameterValidation --> DataFetching : validation_passed
    
    DataFetching --> DataProcessing : data_fetched
    DataFetching --> Error : fetch_failed
    
    DataProcessing --> ReportGeneration : data_processed
    DataProcessing --> Error : processing_failed
    
    ReportGeneration --> FileStorage : report_generated
    ReportGeneration --> Error : generation_failed
    
    FileStorage --> Ready : file_stored
    FileStorage --> Error : storage_failed
    
    Ready --> Downloaded : download_started
    Ready --> Expired : expiry_time_reached
    
    Downloaded --> [*] : download_complete
    Expired --> [*] : file_cleaned
    Error --> ReportNotRequested : reset
    
    state ReportGeneration {
        [*] --> FormatSelection
        FormatSelection --> PDFGeneration : pdf_selected
        FormatSelection --> ExcelGeneration : excel_selected
        PDFGeneration --> [*] : pdf_complete
        ExcelGeneration --> [*] : excel_complete
    }
```

## System Cache State Diagram

```mermaid
stateDiagram-v2
    [*] --> Empty
    
    Empty --> Loading : cache_miss
    Loading --> Populated : data_loaded
    Loading --> Error : load_failed
    
    Populated --> Serving : cache_hit
    Serving --> Populated : data_served
    
    Populated --> Updating : data_changed
    Updating --> Populated : update_complete
    Updating --> Error : update_failed
    
    Populated --> Expiring : ttl_reached
    Expiring --> Empty : cache_cleared
    
    Populated --> Invalidating : invalidation_triggered
    Invalidating --> Empty : invalidation_complete
    
    Error --> Empty : error_cleared
    Error --> Loading : retry_load
    
    state Populated {
        [*] --> Fresh
        Fresh --> Stale : aging_threshold
        Stale --> Fresh : refresh_triggered
        Stale --> [*] : eviction_candidate
    }
```

## Database Connection State Diagram

```mermaid
stateDiagram-v2
    [*] --> Disconnected
    
    Disconnected --> Connecting : connection_attempt
    Connecting --> Connected : connection_success
    Connecting --> ConnectionFailed : connection_timeout
    Connecting --> ConnectionFailed : authentication_failed
    
    Connected --> Active : ready_for_queries
    Active --> Busy : query_executing
    Busy --> Active : query_complete
    Busy --> Error : query_failed
    
    Active --> Idle : no_activity
    Idle --> Active : new_query
    Idle --> Disconnected : idle_timeout
    
    Connected --> Reconnecting : connection_lost
    Active --> Reconnecting : connection_lost
    Busy --> Reconnecting : connection_lost
    
    Reconnecting --> Connected : reconnection_success
    Reconnecting --> ConnectionFailed : reconnection_failed
    
    ConnectionFailed --> Disconnected : give_up
    ConnectionFailed --> Connecting : retry_connection
    
    Error --> Active : error_handled
    Error --> Disconnected : fatal_error
    
    state Connected {
        [*] --> HealthCheck
        HealthCheck --> Healthy : check_passed
        HealthCheck --> Unhealthy : check_failed
        Healthy --> HealthCheck : periodic_check
        Unhealthy --> HealthCheck : retry_check
        Unhealthy --> [*] : force_disconnect
    }
```

## File Upload State Diagram

```mermaid
stateDiagram-v2
    [*] --> NotSelected
    
    NotSelected --> FileSelected : file_chosen
    FileSelected --> Validating : start_validation
    FileSelected --> NotSelected : cancel_selection
    
    Validating --> ValidationPassed : file_valid
    Validating --> ValidationFailed : file_invalid
    
    ValidationFailed --> FileSelected : retry_selection
    ValidationFailed --> NotSelected : cancel_upload
    
    ValidationPassed --> Uploading : start_upload
    Uploading --> UploadProgress : upload_started
    
    UploadProgress --> UploadComplete : upload_finished
    UploadProgress --> UploadFailed : upload_error
    UploadProgress --> UploadCancelled : user_cancelled
    
    UploadComplete --> Processing : process_file
    Processing --> ProcessingComplete : processing_success
    Processing --> ProcessingFailed : processing_error
    
    ProcessingComplete --> [*] : file_ready
    ProcessingFailed --> NotSelected : reset
    UploadFailed --> FileSelected : retry_upload
    UploadCancelled --> FileSelected : restart
    
    state Uploading {
        [*] --> ChunkUploading
        ChunkUploading --> ChunkComplete : chunk_uploaded
        ChunkComplete --> ChunkUploading : next_chunk
        ChunkComplete --> [*] : all_chunks_complete
    }
```

## Notification State Diagram

```mermaid
stateDiagram-v2
    [*] --> NotTriggered
    
    NotTriggered --> Triggered : event_occurred
    Triggered --> Queued : notification_created
    
    Queued --> Processing : worker_picked_up
    Processing --> Sending : notification_prepared
    
    Sending --> Sent : send_success
    Sending --> Failed : send_failed
    
    Failed --> Retrying : retry_attempt
    Retrying --> Sending : retry_ready
    Retrying --> Abandoned : max_retries_reached
    
    Sent --> Delivered : delivery_confirmed
    Sent --> DeliveryFailed : delivery_failed
    
    Delivered --> Read : user_read
    Delivered --> Expired : expiry_reached
    
    Read --> [*] : notification_processed
    Expired --> [*] : notification_cleaned
    Abandoned --> [*] : notification_discarded
    DeliveryFailed --> [*] : marked_as_failed
    
    state Processing {
        [*] --> TemplateLoading
        TemplateLoading --> DataBinding : template_loaded
        DataBinding --> ContentGeneration : data_bound
        ContentGeneration --> [*] : content_ready
    }
```

## Backup Process State Diagram

```mermaid
stateDiagram-v2
    [*] --> Idle
    
    Idle --> Scheduled : backup_scheduled
    Scheduled --> Preparing : backup_time_reached
    Scheduled --> Idle : schedule_cancelled
    
    Preparing --> InProgress : preparation_complete
    Preparing --> Failed : preparation_failed
    
    InProgress --> Compressing : data_collected
    Compressing --> Encrypting : compression_complete
    Encrypting --> Storing : encryption_complete
    
    Storing --> Verifying : storage_complete
    Verifying --> Complete : verification_passed
    Verifying --> Failed : verification_failed
    
    Complete --> Idle : backup_finished
    Failed --> Idle : error_handled
    
    InProgress --> Failed : backup_interrupted
    Compressing --> Failed : compression_failed
    Encrypting --> Failed : encryption_failed
    Storing --> Failed : storage_failed
    
    state InProgress {
        [*] --> DatabaseDump
        DatabaseDump --> FileCollection : dump_complete
        FileCollection --> MetadataGeneration : files_collected
        MetadataGeneration --> [*] : metadata_ready
    }
```

## State Transition Rules

### Authentication States
- **Unauthenticated → Authenticating**: User submits login credentials
- **Authenticating → Authenticated**: Credentials validated successfully
- **Authenticated → Unauthenticated**: User logs out or session expires
- **Authenticated → TokenRefreshing**: Access token expires but refresh token is valid

### Patient Management States
- **NotRegistered → Registering**: User starts patient registration process
- **Registered → Active**: Patient record is activated for treatment
- **Active → UnderTreatment**: Patient begins medical treatment
- **UnderTreatment → Controlled/Uncontrolled**: Based on examination results

### Examination Process States
- **ExaminationNotStarted → PatientSelection**: User initiates examination process
- **DataEntry → Validation**: User submits examination data
- **Validation → Processing**: Data passes validation checks
- **Processing → Completed**: Examination data is successfully saved

### Report Generation States
- **ReportNotRequested → ParameterSelection**: User requests a report
- **ParameterValidation → DataFetching**: Parameters are valid
- **ReportGeneration → Ready**: Report file is successfully generated
- **Ready → Downloaded**: User downloads the report

### System Cache States
- **Empty → Loading**: Cache miss triggers data loading
- **Loading → Populated**: Data is successfully loaded into cache
- **Populated → Updating**: Underlying data changes trigger cache update
- **Populated → Expiring**: Cache TTL (Time To Live) is reached

### Error Handling
- Most states have error transitions that lead to appropriate error states
- Error states typically allow for retry operations or reset to initial states
- Critical errors may force the system to disconnect or restart processes

### Concurrency Considerations
- Multiple users can be in different authentication states simultaneously
- Patient states are independent and can change concurrently
- Cache states are shared but managed with proper locking mechanisms
- Database connections are pooled and managed independently