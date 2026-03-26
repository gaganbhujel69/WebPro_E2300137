-- ============================================================
--  EduSkill Marketplace System (EMS) — Database Schema
--  Engine : InnoDB (enforces FK constraints)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS ems_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ems_db;

-- ============================================================
-- 1. USERS
--    Single table for all roles; role column drives access.
-- ============================================================
CREATE TABLE users (
    user_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(150)      NOT NULL,
    email         VARCHAR(255)      NOT NULL UNIQUE,
    password_hash VARCHAR(255)      NOT NULL,
    role          ENUM(
                      'learner',
                      'training_provider',
                      'ministry_officer'
                  )                 NOT NULL DEFAULT 'learner',
    is_first_login TINYINT(1)        NOT NULL DEFAULT 1,
    is_active     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. PROVIDERS
--    Extended profile for training_provider users.
--    status is set to 'pending' on registration;
--    a Ministry Officer changes it to 'approved' or 'rejected'.
-- ============================================================
CREATE TABLE providers (
    provider_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED      NOT NULL UNIQUE,   -- 1-to-1 with users
    organisation_name VARCHAR(200)      NOT NULL,
    registration_no   VARCHAR(100)      NOT NULL,
    address           TEXT              NOT NULL,
    phone             VARCHAR(20)       NOT NULL,
    website           VARCHAR(255)          NULL,
    document_path     VARCHAR(255)          NULL,
    status            ENUM(
                          'pending',
                          'approved',
                          'rejected'
                      )                 NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED          NULL,          -- FK → users (officer)
    reviewed_at       DATETIME              NULL,
    rejection_reason  TEXT                  NULL,
    created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id),
    CONSTRAINT fk_providers_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_providers_reviewer
        FOREIGN KEY (reviewed_by)
        REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_providers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. COURSE CATEGORIES
--    Lookup table for organising courses.
-- ============================================================
CREATE TABLE course_categories (
    category_id   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)      NOT NULL UNIQUE,
    description   TEXT                  NULL,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. COURSES
--    Short courses listed by approved providers.
-- ============================================================
CREATE TABLE courses (
    course_id     INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    provider_id   INT UNSIGNED      NOT NULL,
    category_id   INT UNSIGNED          NULL,
    title         VARCHAR(255)      NOT NULL,
    description   TEXT              NOT NULL,
    duration_days SMALLINT UNSIGNED NOT NULL COMMENT 'Length of course in days',
    fee           DECIMAL(10, 2)    NOT NULL DEFAULT 0.00,
    seats_total   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    seats_taken   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    start_date    DATE              NOT NULL,
    end_date      DATE              NOT NULL,
    location      VARCHAR(255)      NOT NULL,
    mode          ENUM(
                      'in_person',
                      'online',
                      'hybrid'
                  )                 NOT NULL DEFAULT 'in_person',
    status        ENUM(
                      'draft',
                      'published',
                      'cancelled',
                      'completed'
                  )                 NOT NULL DEFAULT 'draft',
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id),
    CONSTRAINT fk_courses_provider
        FOREIGN KEY (provider_id)
        REFERENCES providers (provider_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_courses_category
        FOREIGN KEY (category_id)
        REFERENCES course_categories (category_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_courses_provider  (provider_id),
    INDEX idx_courses_status    (status),
    INDEX idx_courses_start     (start_date),
    CONSTRAINT chk_courses_dates CHECK (end_date >= start_date),
    CONSTRAINT chk_courses_seats CHECK (seats_taken <= seats_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. ENROLMENTS
--    Records a learner's enrolment in a course.
--    payment_status tracks the payment lifecycle.
-- ============================================================
CREATE TABLE enrolments (
    enrolment_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    learner_id        INT UNSIGNED      NOT NULL,   -- FK → users
    course_id         INT UNSIGNED      NOT NULL,
    enrolment_date    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_status    ENUM(
                          'pending',
                          'paid',
                          'failed',
                          'refunded'
                      )                 NOT NULL DEFAULT 'pending',
    amount_paid       DECIMAL(10, 2)        NULL,
    completion_status ENUM(
                          'enrolled',
                          'completed',
                          'withdrawn'
                      )                 NOT NULL DEFAULT 'enrolled',
    PRIMARY KEY (enrolment_id),
    UNIQUE KEY uq_enrolment_learner_course (learner_id, course_id),
    CONSTRAINT fk_enrolments_learner
        FOREIGN KEY (learner_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_enrolments_course
        FOREIGN KEY (course_id)
        REFERENCES courses (course_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_enrolments_learner  (learner_id),
    INDEX idx_enrolments_course   (course_id),
    INDEX idx_enrolments_payment  (payment_status),
    INDEX idx_enrolments_date     (enrolment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. PAYMENTS
--    Detailed payment record for each enrolment.
--    Kept separate from enrolments for audit purposes.
-- ============================================================
CREATE TABLE payments (
    payment_id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    enrolment_id      INT UNSIGNED      NOT NULL UNIQUE,   -- 1-to-1
    transaction_ref   VARCHAR(100)      NOT NULL UNIQUE    COMMENT 'System-generated reference',
    amount            DECIMAL(10, 2)    NOT NULL,
    payment_method    ENUM(
                          'credit_card',
                          'debit_card',
                          'online_transfer',
                          'others'
                      )                 NOT NULL,
    payment_status    ENUM(
                          'success',
                          'failed',
                          'refunded'
                      )                 NOT NULL,
    paid_at           DATETIME              NULL,
    notes             VARCHAR(255)          NULL,
    created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    CONSTRAINT fk_payments_enrolment
        FOREIGN KEY (enrolment_id)
        REFERENCES enrolments (enrolment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_payments_status (payment_status),
    INDEX idx_payments_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. RECEIPTS
--    Official enrolment receipt issued after successful payment.
-- ============================================================
CREATE TABLE receipts (
    receipt_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    payment_id      INT UNSIGNED      NOT NULL UNIQUE,    -- 1-to-1
    receipt_no      VARCHAR(50)       NOT NULL UNIQUE     COMMENT 'Human-readable; e.g. RCP-2025-000001',
    issued_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_id),
    CONSTRAINT fk_receipts_payment
        FOREIGN KEY (payment_id)
        REFERENCES payments (payment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. REVIEWS
--    A learner may review a course only after completing it.
--    One review per enrolment (enforced via UNIQUE on enrolment_id).
-- ============================================================
CREATE TABLE reviews (
    review_id     INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    enrolment_id  INT UNSIGNED      NOT NULL UNIQUE,   -- 1 review per enrolment
    course_id     INT UNSIGNED      NOT NULL,          -- denormalised for query ease
    learner_id    INT UNSIGNED      NOT NULL,          -- denormalised for query ease
    rating        TINYINT UNSIGNED  NOT NULL,
    feedback      TEXT                  NULL,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    CONSTRAINT fk_reviews_enrolment
        FOREIGN KEY (enrolment_id)
        REFERENCES enrolments (enrolment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_course
        FOREIGN KEY (course_id)
        REFERENCES courses (course_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_learner
        FOREIGN KEY (learner_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    INDEX idx_reviews_course  (course_id),
    INDEX idx_reviews_learner (learner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA — Default Ministry Officer account
--    password: Officer@123  (bcrypt hash provided below)
--    Replace hash before going live.
-- ============================================================
INSERT INTO users (full_name, email, password_hash, role) VALUES
(
    'Ministry Officer',
    'officer@ems.gov',
    '$2y$12$placeholder_replace_with_real_bcrypt_hash_here_xxx',
    'ministry_officer'
);

-- ============================================================
-- SEED DATA — Course Categories
-- ============================================================
INSERT INTO course_categories (name, description) VALUES
('Information Technology',  'Software, networking, cybersecurity, and data skills'),
('Business & Management',   'Leadership, accounting, project management'),
('Creative Arts & Design',  'Graphic design, UI/UX, photography'),
('Health & Wellness',       'First aid, nutrition, mental health awareness'),
('Language & Communication','English proficiency, public speaking, writing'),
('Skilled Trades',          'Electrical, plumbing, welding, automotive');
