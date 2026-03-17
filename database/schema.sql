CREATE DATABASE IF NOT EXISTS floss_gloss_dental;
USE floss_gloss_dental;

-- =========================================================
-- 1) USERS
-- =========================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('patient', 'staff', 'system_admin', 'dentist') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    account_status ENUM('active', 'inactive', 'deactivated') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================================
-- 2) PATIENTS
-- =========================================================
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    sex ENUM('male', 'female', 'other') NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    emergency_contact_name VARCHAR(100) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patients_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================================================
-- 3) STAFF / RECEPTIONISTS / SYSTEM ADMIN
-- =========================================================
CREATE TABLE staff_profiles (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL DEFAULT 'Receptionist',
    hired_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================================================
-- 4) DENTISTS
-- =========================================================
CREATE TABLE dentists (
    dentist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    specialization VARCHAR(100) NULL,
    license_number VARCHAR(50) UNIQUE NULL,
    bio TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dentists_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================================================
-- 5) SERVICES
-- =========================================================
CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    estimated_duration_minutes INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    service_status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================================
-- 6) DENTIST AVAILABILITY / SCHEDULE
-- One row = one available slot or blocked slot
-- =========================================================
CREATE TABLE dentist_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    dentist_id INT NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_status ENUM('available', 'booked', 'blocked') NOT NULL DEFAULT 'available',
    block_reason VARCHAR(255) NULL,
    created_by_staff_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_dentist
        FOREIGN KEY (dentist_id) REFERENCES dentists(dentist_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_staff
        FOREIGN KEY (created_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT chk_schedule_time
        CHECK (start_time < end_time),
    UNIQUE KEY uq_dentist_slot (dentist_id, schedule_date, start_time, end_time)
);

-- =========================================================
-- 7) APPOINTMENTS
-- Core booking table
-- =========================================================
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_code VARCHAR(30) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    dentist_id INT NOT NULL,
    service_id INT NOT NULL,
    schedule_id INT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'completed',
        'cancelled',
        'reschedule_pending'
    ) NOT NULL DEFAULT 'pending',
    payment_status ENUM('not_required', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    cancellation_reason VARCHAR(255) NULL,
    patient_notes TEXT NULL,
    admin_notes TEXT NULL,
    approved_by_staff_id INT NULL,
    approved_at DATETIME NULL,
    completed_by_staff_id INT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointments_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_dentist
        FOREIGN KEY (dentist_id) REFERENCES dentists(dentist_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_service
        FOREIGN KEY (service_id) REFERENCES services(service_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_schedule
        FOREIGN KEY (schedule_id) REFERENCES dentist_schedules(schedule_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_approved_staff
        FOREIGN KEY (approved_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_completed_staff
        FOREIGN KEY (completed_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_appointment_time
        CHECK (start_time < end_time),

    INDEX idx_appointments_patient (patient_id),
    INDEX idx_appointments_dentist_date (dentist_id, appointment_date),
    INDEX idx_appointments_status (status),
    INDEX idx_appointments_date (appointment_date)
);

-- =========================================================
-- 8) RESCHEDULE REQUESTS
-- Patient or staff requests a new schedule; admin approves/rejects
-- =========================================================
CREATE TABLE appointment_reschedule_requests (
    reschedule_request_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    requested_by_user_id INT NOT NULL,
    old_appointment_date DATE NOT NULL,
    old_start_time TIME NOT NULL,
    old_end_time TIME NOT NULL,
    requested_date DATE NOT NULL,
    requested_start_time TIME NOT NULL,
    requested_end_time TIME NOT NULL,
    requested_dentist_id INT NOT NULL,
    request_reason VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    reviewed_by_staff_id INT NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reschedule_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_reschedule_requested_by
        FOREIGN KEY (requested_by_user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_reschedule_dentist
        FOREIGN KEY (requested_dentist_id) REFERENCES dentists(dentist_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reschedule_staff
        FOREIGN KEY (reviewed_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_reschedule_time
        CHECK (requested_start_time < requested_end_time),

    INDEX idx_reschedule_appointment (appointment_id),
    INDEX idx_reschedule_status (status)
);

-- =========================================================
-- 9) APPOINTMENT ACTION LOGS
-- Approval history / rejection / reschedule / completion / cancel logs
-- =========================================================
CREATE TABLE appointment_action_logs (
    action_log_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    action_type ENUM(
        'created',
        'approved',
        'rejected',
        'reschedule_requested',
        'reschedule_approved',
        'reschedule_rejected',
        'cancelled',
        'completed',
        'payment_verified',
        'payment_rejected',
        'note_added',
        'note_updated'
    ) NOT NULL,
    acted_by_user_id INT NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_logs_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_logs_user
        FOREIGN KEY (acted_by_user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_logs_appointment (appointment_id),
    INDEX idx_logs_action_type (action_type)
);

-- =========================================================
-- 10) PATIENT MEDICAL HISTORY
-- General medical info of patient
-- =========================================================
CREATE TABLE patient_medical_histories (
    medical_history_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    blood_type VARCHAR(5) NULL,
    allergies TEXT NULL,
    current_medications TEXT NULL,
    existing_conditions TEXT NULL,
    previous_dental_procedures TEXT NULL,
    notes TEXT NULL,
    last_updated_by_staff_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_medhist_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_medhist_staff
        FOREIGN KEY (last_updated_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    UNIQUE KEY uq_patient_medhist (patient_id)
);

-- =========================================================
-- 11) APPOINTMENT NOTES
-- Notes tied to specific appointments
-- Staff can create/manage; dentists view-only
-- =========================================================
CREATE TABLE appointment_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    written_by_staff_id INT NOT NULL,
    note_text TEXT NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_notes_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notes_staff
        FOREIGN KEY (written_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_notes_appointment (appointment_id)
);

-- =========================================================
-- 12) PAYMENTS
-- Manual verification only
-- =========================================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL UNIQUE,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer', 'other') NULL,
    proof_of_payment_path VARCHAR(255) NULL,
    payment_reference VARCHAR(100) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by_staff_id INT NULL,
    verified_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payments_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_payments_staff
        FOREIGN KEY (verified_by_staff_id) REFERENCES staff_profiles(staff_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================================================
-- 13) NOTIFICATION LOGS
-- Optional but useful for email notification tracking
-- =========================================================
CREATE TABLE notification_logs (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appointment_id INT NULL,
    notification_type ENUM(
        'appointment_approved',
        'appointment_rejected',
        'appointment_rescheduled',
        'appointment_cancelled',
        'payment_verified',
        'payment_rejected'
    ) NOT NULL,
    channel ENUM('email', 'system') NOT NULL DEFAULT 'email',
    subject VARCHAR(150) NULL,
    message TEXT NULL,
    send_status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notifications_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================================================
-- 14) SYSTEM SETTINGS
-- For app-wide settings if needed
-- =========================================================
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);