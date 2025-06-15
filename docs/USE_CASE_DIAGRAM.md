graph TD
    subgraph Sistem Akudihatinya
        actor Admin
        actor "Pengguna Puskesmas" as PuskesmasUser

        %% Use Cases
        ucLogin("Login & Logout")
        ucManageProfile("Kelola Profil Sendiri")

        subgraph "Fungsionalitas Admin"
            direction LR
            ucAdminDashboard("Melihat Dashboard Admin")
            ucManageUsers("Mengelola Pengguna (CRUD)")
            ucManageTargets("Mengelola Target Tahunan (CRUD)")
            ucViewGlobalStats("Melihat Statistik Global")
            ucExportGlobalReports("Mengekspor Laporan Global (PDF/Excel)")
        end

        subgraph "Fungsionalitas Puskesmas"
            direction LR
            ucPuskesmasDashboard("Melihat Dashboard Puskesmas")
            ucManagePatients("Mengelola Pasien (CRUD)")
            ucExportPatientList("Mengekspor Daftar Pasien")
            ucManageExaminations("Mengelola Pemeriksaan (CRUD)")
            ucViewPuskesmasStats("Melihat Statistik Puskesmas")
            ucExportPuskesmasReports("Mengekspor Laporan Puskesmas")
        end

        %% Relasi Aktor ke Use Case
        Admin --> ucLogin
        Admin --> ucManageProfile
        Admin --> ucAdminDashboard
        Admin --> ucManageUsers
        Admin --> ucManageTargets
        Admin --> ucViewGlobalStats
        Admin --> ucExportGlobalReports

        PuskesmasUser --> ucLogin
        PuskesmasUser --> ucManageProfile
        PuskesmasUser --> ucPuskesmasDashboard
        PuskesmasUser --> ucManagePatients
        PuskesmasUser --> ucExportPatientList
        PuskesmasUser --> ucManageExaminations
        PuskesmasUser --> ucViewPuskesmasStats
        PuskesmasUser --> ucExportPuskesmasReports
    end

    style Admin fill:#f9f,stroke:#333,stroke-width:2px
    style PuskesmasUser fill:#bbf,stroke:#333,stroke-width:2px
