<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DB_HOST = "127.0.0.1";
const DB_NAME = "database_barangaySystem";
const DB_USER = "root";
const DB_PASS = "";
const DB_PORT = 3307;

// All allowed roles and their display names
const ROLES = [
    'captain'   => 'Barangay Captain',
    'secretary' => 'Secretary',
    'treasurer' => 'Treasurer',
    'assistant' => 'Asst. Secretary',
];

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, "", DB_PORT);
    $conn->set_charset("utf8mb4");
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);
    ensureSchema($conn);
    return $conn;
}

function ensureSchema(mysqli $conn): void
{
    // Users table (for login/authentication)
    // PK renamed: id -> users_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS users (
            users_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'secretary',
            email VARCHAR(180) NOT NULL DEFAULT '',
            contact VARCHAR(60) NOT NULL DEFAULT '',
            profile_photo LONGBLOB NULL,
            profile_photo_mime VARCHAR(50) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_online TINYINT(1) NOT NULL DEFAULT 0,
            session_token VARCHAR(128) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // residents table
    // PK: residents_code -> residents_id (VARCHAR, format RES-YYYY-000001), remove old INT id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS residents (
            residents_id VARCHAR(20) NOT NULL PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            birthday DATE NULL,
            gender VARCHAR(20) NOT NULL DEFAULT '',
            civil_status VARCHAR(30) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL,
            contact VARCHAR(60) NOT NULL DEFAULT '',
            household_id VARCHAR(20) NULL,
            household VARCHAR(100) NOT NULL DEFAULT '',
            zone_name VARCHAR(50) NOT NULL DEFAULT 'Zone 1',
            status VARCHAR(30) NOT NULL DEFAULT 'Active',
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // households table
    // PK: household_code -> household_id (VARCHAR, format HOU-YYYY-000001), remove old INT id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS households (
            household_id VARCHAR(20) NOT NULL PRIMARY KEY,
            head_name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            members INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // documents table
    // PK: request_code -> request_id (VARCHAR, format DOC-YYYY-000001), remove old INT id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS documents (
            request_id VARCHAR(20) NOT NULL PRIMARY KEY,
            resident_name VARCHAR(150) NOT NULL,
            document_type VARCHAR(120) NOT NULL,
            date_requested DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            notes TEXT NULL,
            rejection_reason TEXT NULL,
            released_at DATETIME NULL,
            released_by INT UNSIGNED NULL,
            processed_by INT UNSIGNED NULL,
            proof_photo LONGBLOB NULL,
            proof_photo_mime VARCHAR(50) NULL,
            recipient_name VARCHAR(150) NULL,
            relationship_to_requester VARCHAR(100) NULL,
            archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // barangay table — PK renamed: id -> barangay_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS barangay (
            barangay_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL DEFAULT '',
            city VARCHAR(100) NOT NULL DEFAULT '',
            province VARCHAR(100) NOT NULL DEFAULT '',
            zip VARCHAR(20) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            contact VARCHAR(60) NOT NULL DEFAULT '',
            email VARCHAR(180) NOT NULL DEFAULT '',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // document_settings table — PK renamed: id -> document_settings_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS document_settings (
            document_settings_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            header VARCHAR(255) NOT NULL DEFAULT '',
            signatory VARCHAR(150) NOT NULL DEFAULT '',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // system_settings table — PK renamed: id -> system_settings_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS system_settings (
            system_settings_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            language VARCHAR(60) NOT NULL DEFAULT 'Filipino',
            date_format VARCHAR(30) NOT NULL DEFAULT 'MM/DD/YYYY',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // archived_residents table — PK renamed: id -> archived_residents_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS archived_residents (
            archived_residents_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            original_resident_id VARCHAR(20) NULL,
            resident_code VARCHAR(32) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            birthday DATE NULL,
            gender VARCHAR(20) NOT NULL DEFAULT '',
            civil_status VARCHAR(30) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL,
            contact VARCHAR(60) NOT NULL DEFAULT '',
            household VARCHAR(100) NOT NULL,
            zone_name VARCHAR(50) NOT NULL DEFAULT 'Zone 1',
            status VARCHAR(30) NOT NULL DEFAULT 'Archived',
            archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // archived_documents table — PK renamed: id -> archived_documents_id
    $conn->query(
        "CREATE TABLE IF NOT EXISTS archived_documents (
            archived_documents_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            original_document_id VARCHAR(20) NULL,
            request_code VARCHAR(32) NOT NULL,
            resident_name VARCHAR(150) NOT NULL,
            document_type VARCHAR(120) NOT NULL,
            date_requested DATE NOT NULL,
            status VARCHAR(30) NOT NULL,
            notes TEXT NULL,
            rejection_reason TEXT NULL,
            released_at DATETIME NULL,
            released_by_name VARCHAR(150) NULL,
            processed_by_name VARCHAR(150) NULL,
            recipient_name VARCHAR(150) NULL,
            relationship_to_requester VARCHAR(100) NULL,
            proof_photo LONGBLOB NULL,
            proof_photo_mime VARCHAR(50) NULL,
            archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // ── MIGRATION: handle existing databases with old column names ──────────────
    migrateOldSchema($conn);

    // ── COLUMN ADDITIONS for upgrades ──────────────────────────────────────────
    $migrateArchResCols = [
        'birthday'     => "ALTER TABLE archived_residents ADD COLUMN birthday DATE NULL AFTER full_name",
        'gender'       => "ALTER TABLE archived_residents ADD COLUMN gender VARCHAR(20) NOT NULL DEFAULT '' AFTER birthday",
        'civil_status' => "ALTER TABLE archived_residents ADD COLUMN civil_status VARCHAR(30) NOT NULL DEFAULT '' AFTER gender",
        'contact'      => "ALTER TABLE archived_residents ADD COLUMN contact VARCHAR(60) NOT NULL DEFAULT '' AFTER address",
    ];
    foreach ($migrateArchResCols as $col => $sql) {
        if ($conn->query("SHOW COLUMNS FROM archived_residents LIKE '$col'")->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $migrateArchDocCols = [
        'notes'                    => "ALTER TABLE archived_documents ADD COLUMN notes TEXT NULL AFTER status",
        'rejection_reason'         => "ALTER TABLE archived_documents ADD COLUMN rejection_reason TEXT NULL AFTER notes",
        'released_at'              => "ALTER TABLE archived_documents ADD COLUMN released_at DATETIME NULL AFTER rejection_reason",
        'released_by_name'         => "ALTER TABLE archived_documents ADD COLUMN released_by_name VARCHAR(150) NULL AFTER released_at",
        'processed_by_name'        => "ALTER TABLE archived_documents ADD COLUMN processed_by_name VARCHAR(150) NULL AFTER released_by_name",
        'recipient_name'           => "ALTER TABLE archived_documents ADD COLUMN recipient_name VARCHAR(150) NULL AFTER processed_by_name",
        'relationship_to_requester'=> "ALTER TABLE archived_documents ADD COLUMN relationship_to_requester VARCHAR(100) NULL AFTER recipient_name",
        'proof_photo'              => "ALTER TABLE archived_documents ADD COLUMN proof_photo LONGBLOB NULL AFTER relationship_to_requester",
        'proof_photo_mime'         => "ALTER TABLE archived_documents ADD COLUMN proof_photo_mime VARCHAR(50) NULL AFTER proof_photo",
    ];
    foreach ($migrateArchDocCols as $col => $sql) {
        if ($conn->query("SHOW COLUMNS FROM archived_documents LIKE '$col'")->num_rows === 0) {
            $conn->query($sql);
        }
    }

    // Add new document columns if upgrading
    $migrateDocCols = [
        'notes'            => "ALTER TABLE documents ADD COLUMN notes TEXT NULL AFTER status",
        'rejection_reason' => "ALTER TABLE documents ADD COLUMN rejection_reason TEXT NULL AFTER notes",
        'released_at'      => "ALTER TABLE documents ADD COLUMN released_at DATETIME NULL AFTER rejection_reason",
        'released_by'      => "ALTER TABLE documents ADD COLUMN released_by INT UNSIGNED NULL AFTER released_at",
        'processed_by'     => "ALTER TABLE documents ADD COLUMN processed_by INT UNSIGNED NULL AFTER released_by",
        'proof_photo'                => "ALTER TABLE documents ADD COLUMN proof_photo LONGBLOB NULL AFTER processed_by",
        'proof_photo_mime'           => "ALTER TABLE documents ADD COLUMN proof_photo_mime VARCHAR(50) NULL AFTER proof_photo",
        'recipient_name'             => "ALTER TABLE documents ADD COLUMN recipient_name VARCHAR(150) NULL AFTER proof_photo_mime",
        'relationship_to_requester'  => "ALTER TABLE documents ADD COLUMN relationship_to_requester VARCHAR(100) NULL AFTER recipient_name",
    ];
    foreach ($migrateDocCols as $col => $sql) {
        if ($conn->query("SHOW COLUMNS FROM documents LIKE '$col'")->num_rows === 0) {
            $conn->query($sql);
        }
    }

    migrateLegacyArchivedRows($conn);
    seedUsers($conn);
    seedResidents($conn);
    seedHouseholds($conn);
    seedDocuments($conn);
    seedSettings($conn);
}

/**
 * Migrate old schema (old column names) to new schema if the database was created
 * with the previous version of this code.
 */
function migrateOldSchema(mysqli $conn): void
{
    // ── users: id -> users_id ──────────────────────────────────────────────────
    $usersCols = $conn->query("SHOW COLUMNS FROM users LIKE 'id'");
    if ($usersCols->num_rows > 0) {
        $conn->query("ALTER TABLE users CHANGE COLUMN `id` `users_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    // ── barangay: id -> barangay_id ────────────────────────────────────────────
    $barangayCols = $conn->query("SHOW COLUMNS FROM barangay LIKE 'id'");
    if ($barangayCols->num_rows > 0) {
        $conn->query("ALTER TABLE barangay CHANGE COLUMN `id` `barangay_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    // ── document_settings: id -> document_settings_id ─────────────────────────
    $docSettingsCols = $conn->query("SHOW COLUMNS FROM document_settings LIKE 'id'");
    if ($docSettingsCols->num_rows > 0) {
        $conn->query("ALTER TABLE document_settings CHANGE COLUMN `id` `document_settings_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    // ── system_settings: id -> system_settings_id ─────────────────────────────
    $sysSettingsCols = $conn->query("SHOW COLUMNS FROM system_settings LIKE 'id'");
    if ($sysSettingsCols->num_rows > 0) {
        $conn->query("ALTER TABLE system_settings CHANGE COLUMN `id` `system_settings_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    // ── archived_residents: id -> archived_residents_id ───────────────────────
    $archResCols = $conn->query("SHOW COLUMNS FROM archived_residents LIKE 'id'");
    if ($archResCols->num_rows > 0) {
        $conn->query("ALTER TABLE archived_residents CHANGE COLUMN `id` `archived_residents_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }
    // Also update original_resident_id column type to VARCHAR if still INT
    $origResCol = $conn->query("SHOW COLUMNS FROM archived_residents LIKE 'original_resident_id'");
    if ($origResCol->num_rows > 0) {
        $colInfo = $origResCol->fetch_assoc();
        if (stripos((string)($colInfo['Type'] ?? ''), 'int') !== false) {
            $conn->query("ALTER TABLE archived_residents MODIFY COLUMN original_resident_id VARCHAR(20) NULL");
        }
    }

    // ── archived_documents: id -> archived_documents_id ───────────────────────
    $archDocCols = $conn->query("SHOW COLUMNS FROM archived_documents LIKE 'id'");
    if ($archDocCols->num_rows > 0) {
        $conn->query("ALTER TABLE archived_documents CHANGE COLUMN `id` `archived_documents_id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }
    // Also update original_document_id column type to VARCHAR if still INT
    $origDocCol = $conn->query("SHOW COLUMNS FROM archived_documents LIKE 'original_document_id'");
    if ($origDocCol->num_rows > 0) {
        $colInfo = $origDocCol->fetch_assoc();
        if (stripos((string)($colInfo['Type'] ?? ''), 'int') !== false) {
            $conn->query("ALTER TABLE archived_documents MODIFY COLUMN original_document_id VARCHAR(20) NULL");
        }
    }

    // ── residents: old schema with INT id + resident_code -> new VARCHAR PK residents_id ──
    $resIdCol = $conn->query("SHOW COLUMNS FROM residents LIKE 'id'");
    if ($resIdCol->num_rows > 0) {
        // Old schema still exists: migrate data
        // Step 1: add residents_id column if not already there
        $hasNewPk = $conn->query("SHOW COLUMNS FROM residents LIKE 'residents_id'")->num_rows;
        if ($hasNewPk === 0) {
            $conn->query("ALTER TABLE residents ADD COLUMN residents_id VARCHAR(20) NULL FIRST");
        }
        // Step 2: populate residents_id from resident_code
        $conn->query("UPDATE residents SET residents_id = resident_code WHERE residents_id IS NULL OR residents_id = ''");
        // Ensure no NULLs remain (fallback)
        $conn->query("UPDATE residents SET residents_id = CONCAT('RES-', DATE_FORMAT(created_at, '%Y'), '-', LPAD(id, 6, '0')) WHERE residents_id IS NULL OR residents_id = ''");
        // Step 3: drop old PK
        $conn->query("ALTER TABLE residents DROP PRIMARY KEY");
        // Step 4: make residents_id NOT NULL and set as PK
        $conn->query("ALTER TABLE residents MODIFY COLUMN residents_id VARCHAR(20) NOT NULL");
        $conn->query("ALTER TABLE residents ADD PRIMARY KEY (residents_id)");
        // Step 5: drop old columns
        $conn->query("ALTER TABLE residents DROP COLUMN id");
        if ($conn->query("SHOW COLUMNS FROM residents LIKE 'resident_code'")->num_rows > 0) {
            $conn->query("ALTER TABLE residents DROP COLUMN resident_code");
        }
        // Step 6: fix household_id column to VARCHAR if needed
        $hhIdCol = $conn->query("SHOW COLUMNS FROM residents LIKE 'household_id'");
        if ($hhIdCol->num_rows > 0) {
            $hhInfo = $hhIdCol->fetch_assoc();
            if (stripos((string)($hhInfo['Type'] ?? ''), 'int') !== false) {
                // Clear int FK references first, will be re-linked via household code
                $conn->query("ALTER TABLE residents MODIFY COLUMN household_id VARCHAR(20) NULL");
            }
        }
    }
    // Also handle resident_code column leftover if schema partially migrated
    if ($conn->query("SHOW COLUMNS FROM residents LIKE 'resident_code'")->num_rows > 0) {
        $conn->query("ALTER TABLE residents DROP COLUMN resident_code");
    }

    // ── households: old schema with INT id + household_code -> new VARCHAR PK household_id ──
    $hhIdCol = $conn->query("SHOW COLUMNS FROM households LIKE 'id'");
    if ($hhIdCol->num_rows > 0) {
        $hasNewPk = $conn->query("SHOW COLUMNS FROM households LIKE 'household_id'")->num_rows;
        if ($hasNewPk === 0) {
            $conn->query("ALTER TABLE households ADD COLUMN household_id VARCHAR(20) NULL FIRST");
        }
        $conn->query("UPDATE households SET household_id = household_code WHERE household_id IS NULL OR household_id = ''");
        $conn->query("UPDATE households SET household_id = CONCAT('HOU-', DATE_FORMAT(created_at, '%Y'), '-', LPAD(id, 6, '0')) WHERE household_id IS NULL OR household_id = ''");
        $conn->query("ALTER TABLE households DROP PRIMARY KEY");
        $conn->query("ALTER TABLE households MODIFY COLUMN household_id VARCHAR(20) NOT NULL");
        $conn->query("ALTER TABLE households ADD PRIMARY KEY (household_id)");
        $conn->query("ALTER TABLE households DROP COLUMN id");
        if ($conn->query("SHOW COLUMNS FROM households LIKE 'household_code'")->num_rows > 0) {
            $conn->query("ALTER TABLE households DROP COLUMN household_code");
        }
        // Update residents.household_id foreign references (they were INT, now should be the household_id string)
        // We do best-effort: since households used household_code as the display value, household field in residents has it
        $conn->query("UPDATE residents r JOIN households h ON r.household = h.household_id SET r.household_id = h.household_id WHERE r.household_id IS NULL OR r.household_id = ''");
    }
    // Also handle household_code column leftover
    if ($conn->query("SHOW COLUMNS FROM households LIKE 'household_code'")->num_rows > 0) {
        $conn->query("ALTER TABLE households DROP COLUMN household_code");
    }

    // ── documents: old schema with INT id + request_code -> new VARCHAR PK request_id ──
    $docIdCol = $conn->query("SHOW COLUMNS FROM documents LIKE 'id'");
    if ($docIdCol->num_rows > 0) {
        $hasNewPk = $conn->query("SHOW COLUMNS FROM documents LIKE 'request_id'")->num_rows;
        if ($hasNewPk === 0) {
            $conn->query("ALTER TABLE documents ADD COLUMN request_id VARCHAR(20) NULL FIRST");
        }
        $conn->query("UPDATE documents SET request_id = request_code WHERE request_id IS NULL OR request_id = ''");
        $conn->query("UPDATE documents SET request_id = CONCAT('DOC-', DATE_FORMAT(date_requested, '%Y'), '-', LPAD(id, 6, '0')) WHERE request_id IS NULL OR request_id = ''");
        $conn->query("ALTER TABLE documents DROP PRIMARY KEY");
        $conn->query("ALTER TABLE documents MODIFY COLUMN request_id VARCHAR(20) NOT NULL");
        $conn->query("ALTER TABLE documents ADD PRIMARY KEY (request_id)");
        $conn->query("ALTER TABLE documents DROP COLUMN id");
        if ($conn->query("SHOW COLUMNS FROM documents LIKE 'request_code'")->num_rows > 0) {
            $conn->query("ALTER TABLE documents DROP COLUMN request_code");
        }
        // Fix released_by and processed_by references: they reference users.id (now users_id)
        // Column types remain INT UNSIGNED, they still reference users_id value which hasn't changed
    }
    // Also handle request_code leftover
    if ($conn->query("SHOW COLUMNS FROM documents LIKE 'request_code'")->num_rows > 0) {
        $conn->query("ALTER TABLE documents DROP COLUMN request_code");
    }
}

function seedUsers(mysqli $conn): void
{
    if (tableCount($conn, "users") > 0) return;

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password_hash, full_name, role, email, contact, is_active, is_online)
         VALUES (?, ?, ?, ?, ?, ?, 1, 0)"
    );

    $rows = [
        ["CellyG",    "captain123",    "Celina G.",   "captain",   "celina@barangay.gov.ph",  "+63 998 111 0001"],
        ["Yunisss_a", "barangay123",   "Eunice A.",   "secretary", "eunice@barangay.gov.ph",  "+63 912 345 6789"],
        ["Kzyn013",   "treasurer123",  "Kizeyn L.",   "treasurer", "kizeyn@barangay.gov.ph",  "+63 917 222 3333"]
    ];

    foreach ($rows as [$username, $password, $fullName, $role, $email, $contact]) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt->bind_param("ssssss", $username, $hash, $fullName, $role, $email, $contact);
        $stmt->execute();
    }
}

function tableCount(mysqli $conn, string $table): int
{
    $result = $conn->query("SELECT COUNT(*) AS total FROM {$table}");
    return (int) ($result->fetch_assoc()["total"] ?? 0);
}

function seedResidents(mysqli $conn): void
{
    if (tableCount($conn, "residents") > 0) return;

    $stmt = $conn->prepare(
        "INSERT INTO residents (residents_id, full_name, address, household, zone_name, status, archived, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $rows = [
        ["RES-2023-000001", "Eunice P.",      "123 Mabini St.",     "HH #042", "Zone 2", "Active", 0, "2026-04-08 08:00:00"],
        ["RES-2023-000002", "Kizeyn L.",      "45 Rizal Ave.",      "HH #017", "Zone 3", "Active", 0, "2026-04-08 09:00:00"],
        ["RES-2023-000003", "Viene S.",       "78 Luna St.",        "HH #091", "Zone 4", "Active", 0, "2026-04-07 10:00:00"],
        ["RES-2023-000004", "Ghincel L.",     "12 Bonifacio Blvd.", "HH #055", "Zone 1", "Active", 0, "2026-04-06 11:00:00"],
        ["RES-2023-000005", "Juan dela Cruz", "89 Aguinaldo St.",   "HH #033", "Zone 5", "Active", 0, "2026-04-05 12:00:00"],
    ];

    foreach ($rows as [$resId, $fullName, $address, $household, $zone, $status, $archived, $createdAt]) {
        $stmt->bind_param("ssssssis", $resId, $fullName, $address, $household, $zone, $status, $archived, $createdAt);
        $stmt->execute();
    }
}

function seedHouseholds(mysqli $conn): void
{
    if (tableCount($conn, "households") > 0) return;

    $stmt = $conn->prepare(
        "INSERT INTO households (household_id, head_name, address, members) VALUES (?, ?, ?, ?)"
    );

    $rows = [
        ["HOU-2023-000001", "Eunice P.",  "Zone 2, Disneyland", 4],
        ["HOU-2023-000002", "Kizeyn L.",  "Zone 3, Disneyland", 5],
        ["HOU-2023-000003", "Viene S.",   "Zone 4, Disneyland", 2],
        ["HOU-2023-000004", "Ghincel L.", "Zone 1, Disneyland", 4],
    ];

    foreach ($rows as [$hhId, $headName, $address, $members]) {
        $stmt->bind_param("sssi", $hhId, $headName, $address, $members);
        $stmt->execute();
    }
}

function seedDocuments(mysqli $conn): void
{
    if (tableCount($conn, "documents") > 0) return;

    $stmt = $conn->prepare(
        "INSERT INTO documents (request_id, resident_name, document_type, date_requested, status, archived)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $rows = [
        ["DOC-2023-000001", "Eunice P.",    "Barangay Clearance",      "2026-04-20", "Pending",  0],
        ["DOC-2023-000002", "Kizeyn L.",    "Business Permit",         "2026-05-25", "Approved", 0],
        ["DOC-2023-000003", "Viene S.",     "Barangay Clearance",      "2026-04-27", "Rejected", 0],
        ["DOC-2023-000004", "Ghincel L.",   "Business Permit",         "2026-06-17", "Approved", 0],
        ["DOC-2023-000005", "Maria Santos", "Certificate of Residency","2026-04-08", "Pending",  0],
        ["DOC-2023-000006", "Pedro Reyes",  "Barangay Indigency",      "2026-04-07", "Pending",  0],
    ];

    foreach ($rows as [$reqId, $residentName, $type, $dateRequested, $status, $archived]) {
        $stmt->bind_param("sssssi", $reqId, $residentName, $type, $dateRequested, $status, $archived);
        $stmt->execute();
    }
}

function migrateLegacyArchivedRows(mysqli $conn): void
{
    $residentRows = $conn->query(
        "SELECT residents_id, full_name, birthday, gender, civil_status,
                address, contact, household, zone_name, status FROM residents WHERE archived = 1"
    );

    if ($residentRows && $residentRows->num_rows > 0) {
        $insertResident = $conn->prepare(
            "INSERT INTO archived_residents
                (original_resident_id, resident_code, full_name, birthday, gender, civil_status,
                 address, contact, household, zone_name, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        while ($row = $residentRows->fetch_assoc()) {
            $check = $conn->prepare("SELECT archived_residents_id FROM archived_residents WHERE resident_code = ?");
            $check->bind_param("s", $row["residents_id"]);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $insertResident->bind_param("sssssssssss",
                    $row["residents_id"],
                    $row["residents_id"],
                    $row["full_name"],
                    $row["birthday"],
                    $row["gender"],
                    $row["civil_status"],
                    $row["address"],
                    $row["contact"],
                    $row["household"],
                    $row["zone_name"],
                    $row["status"]
                );
                $insertResident->execute();
            }
        }
        $conn->query("DELETE FROM residents WHERE archived = 1");
    }

    $documentRows = $conn->query(
        "SELECT d.request_id, d.resident_name, d.document_type, d.date_requested, d.status,
                d.notes, d.rejection_reason, d.released_at,
                d.recipient_name, d.relationship_to_requester, d.proof_photo, d.proof_photo_mime,
                rb.full_name AS released_by_name,
                pb.full_name AS processed_by_name
         FROM documents d
         LEFT JOIN users rb ON d.released_by = rb.users_id
         LEFT JOIN users pb ON d.processed_by = pb.users_id
         WHERE d.archived = 1"
    );

    if ($documentRows && $documentRows->num_rows > 0) {
        $insertDocument = $conn->prepare(
            "INSERT INTO archived_documents
                (original_document_id, request_code, resident_name, document_type, date_requested, status,
                 notes, rejection_reason, released_at, released_by_name, processed_by_name,
                 recipient_name, relationship_to_requester, proof_photo, proof_photo_mime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        while ($row = $documentRows->fetch_assoc()) {
            $check = $conn->prepare("SELECT archived_documents_id FROM archived_documents WHERE request_code = ?");
            $check->bind_param("s", $row["request_id"]);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $nullPhoto = null;
                $insertDocument->bind_param("sssssssssssssbs",
                    $row["request_id"],
                    $row["request_id"],
                    $row["resident_name"],
                    $row["document_type"],
                    $row["date_requested"],
                    $row["status"],
                    $row["notes"],
                    $row["rejection_reason"],
                    $row["released_at"],
                    $row["released_by_name"],
                    $row["processed_by_name"],
                    $row["recipient_name"],
                    $row["relationship_to_requester"],
                    $nullPhoto,
                    $row["proof_photo_mime"]
                );
                if ($row["proof_photo"]) {
                    $insertDocument->send_long_data(13, $row["proof_photo"]);
                }
                $insertDocument->execute();
            }
        }
        $conn->query("DELETE FROM documents WHERE archived = 1");
    }
}

function seedSettings(mysqli $conn): void
{
    // Seed barangay table if empty
    if ((int)($conn->query("SELECT COUNT(*) AS t FROM barangay")->fetch_assoc()["t"] ?? 0) === 0) {
        $conn->query(
            "INSERT INTO barangay (name, city, province, zip, address, contact, email)
             VALUES ('Barangay Maligaya','Quezon City','Metro Manila','1105',
                     'Barangay Hall, Mabini Street, Zone 2','+63 998 000 1111',
                     'maligaya@barangay.gov.ph')"
        );
    }
    // Seed document_settings table if empty
    if ((int)($conn->query("SELECT COUNT(*) AS t FROM document_settings")->fetch_assoc()["t"] ?? 0) === 0) {
        $conn->query(
            "INSERT INTO document_settings (header, signatory)
             VALUES ('Republic of the Philippines | Barangay Maligaya','Celina G.')"
        );
    }
    // Seed system_settings table if empty
    if ((int)($conn->query("SELECT COUNT(*) AS t FROM system_settings")->fetch_assoc()["t"] ?? 0) === 0) {
        $conn->query(
            "INSERT INTO system_settings (language, date_format)
             VALUES ('Filipino','MM/DD/YYYY')"
        );
    }

    // Migrate legacy app_settings if the table still exists
    $tableExists = $conn->query(
        "SELECT COUNT(*) AS t FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'app_settings'"
    )->fetch_assoc()["t"] ?? 0;

    if ((int)$tableExists > 0) {
        $legacyRows = $conn->query("SELECT setting_key, setting_value FROM app_settings");
        while ($row = $legacyRows->fetch_assoc()) {
            $data = json_decode((string)$row["setting_value"], true);
            if (!is_array($data)) continue;

            if ($row["setting_key"] === "barangay") {
                $b = $conn->query("SELECT barangay_id FROM barangay LIMIT 1")->fetch_assoc();
                if ($b) {
                    $name     = $conn->real_escape_string($data["name"] ?? "");
                    $city     = $conn->real_escape_string($data["city"] ?? "");
                    $province = $conn->real_escape_string($data["province"] ?? "");
                    $zip      = $conn->real_escape_string($data["zip"] ?? "");
                    $address  = $conn->real_escape_string($data["address"] ?? "");
                    $contact  = $conn->real_escape_string($data["contact"] ?? "");
                    $email    = $conn->real_escape_string($data["email"] ?? "");
                    $conn->query("UPDATE barangay SET name='$name',city='$city',province='$province',zip='$zip',address='$address',contact='$contact',email='$email' WHERE barangay_id={$b["barangay_id"]}");
                }
            } elseif ($row["setting_key"] === "document") {
                $d = $conn->query("SELECT document_settings_id FROM document_settings LIMIT 1")->fetch_assoc();
                if ($d) {
                    $header    = $conn->real_escape_string($data["header"] ?? "");
                    $signatory = $conn->real_escape_string($data["signatory"] ?? "");
                    $conn->query("UPDATE document_settings SET header='$header',signatory='$signatory' WHERE document_settings_id={$d["document_settings_id"]}");
                }
            } elseif ($row["setting_key"] === "system") {
                $s = $conn->query("SELECT system_settings_id FROM system_settings LIMIT 1")->fetch_assoc();
                if ($s) {
                    $language    = $conn->real_escape_string($data["language"] ?? "Filipino");
                    $date_format = $conn->real_escape_string($data["dateFormat"] ?? "MM/DD/YYYY");
                    $conn->query("UPDATE system_settings SET language='$language',date_format='$date_format' WHERE system_settings_id={$s["system_settings_id"]}");
                }
            }
        }
        // Drop legacy table after migration
        $conn->query("DROP TABLE IF EXISTS app_settings");
    }
}

function defaultSettings(): array
{
    return [
        "barangay" => [
            "name"     => "Barangay Maligaya",
            "city"     => "Quezon City",
            "province" => "Metro Manila",
            "zip"      => "1105",
            "address"  => "Barangay Hall, Mabini Street, Zone 2",
            "contact"  => "+63 998 000 1111",
            "email"    => "maligaya@barangay.gov.ph",
        ],
        "document" => [
            "header"    => "Republic of the Philippines | Barangay Maligaya",
            "signatory" => "Celina G.",
        ],
        "system" => [
            "language"   => "Filipino",
            "dateFormat" => "MM/DD/YYYY",
        ],
    ];
}

/**
 * Read a settings section from the normalized tables.
 * Supported keys: "barangay", "document", "system"
 */
function getSetting(mysqli $conn, string $key, mixed $fallback = null): mixed
{
    if ($key === "barangay") {
        $row = $conn->query("SELECT name,city,province,zip,address,contact,email FROM barangay LIMIT 1")->fetch_assoc();
        if (!$row) return $fallback;
        return [
            "name"     => $row["name"],
            "city"     => $row["city"],
            "province" => $row["province"],
            "zip"      => $row["zip"],
            "address"  => $row["address"],
            "contact"  => $row["contact"],
            "email"    => $row["email"],
        ];
    }
    if ($key === "document") {
        $row = $conn->query("SELECT header, signatory FROM document_settings LIMIT 1")->fetch_assoc();
        if (!$row) return $fallback;
        return [
            "header"    => $row["header"],
            "signatory" => $row["signatory"],
        ];
    }
    if ($key === "system") {
        $row = $conn->query("SELECT language, date_format FROM system_settings LIMIT 1")->fetch_assoc();
        if (!$row) return $fallback;
        return [
            "language"   => $row["language"],
            "dateFormat" => $row["date_format"],
        ];
    }
    return $fallback;
}

function getAllSettings(mysqli $conn): array
{
    $defaults = defaultSettings();
    return [
        "barangay" => getSetting($conn, "barangay", $defaults["barangay"]),
        "document"  => getSetting($conn, "document",  $defaults["document"]),
        "system"    => getSetting($conn, "system",    $defaults["system"]),
    ];
}

/**
 * Write a settings section to the normalized tables.
 * Supported keys: "barangay", "document", "system"
 */
function setSetting(mysqli $conn, string $key, mixed $value): void
{
    if (!is_array($value)) return;

    if ($key === "barangay") {
        $name     = $conn->real_escape_string((string)($value["name"]     ?? ""));
        $city     = $conn->real_escape_string((string)($value["city"]     ?? ""));
        $province = $conn->real_escape_string((string)($value["province"] ?? ""));
        $zip      = $conn->real_escape_string((string)($value["zip"]      ?? ""));
        $address  = $conn->real_escape_string((string)($value["address"]  ?? ""));
        $contact  = $conn->real_escape_string((string)($value["contact"]  ?? ""));
        $email    = $conn->real_escape_string((string)($value["email"]    ?? ""));
        $existing = $conn->query("SELECT barangay_id FROM barangay LIMIT 1")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE barangay SET name='$name',city='$city',province='$province',zip='$zip',address='$address',contact='$contact',email='$email' WHERE barangay_id={$existing["barangay_id"]}");
        } else {
            $conn->query("INSERT INTO barangay (name,city,province,zip,address,contact,email) VALUES ('$name','$city','$province','$zip','$address','$contact','$email')");
        }
        return;
    }

    if ($key === "document") {
        $header    = $conn->real_escape_string((string)($value["header"]    ?? ""));
        $signatory = $conn->real_escape_string((string)($value["signatory"] ?? ""));
        $existing  = $conn->query("SELECT document_settings_id FROM document_settings LIMIT 1")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE document_settings SET header='$header',signatory='$signatory' WHERE document_settings_id={$existing["document_settings_id"]}");
        } else {
            $conn->query("INSERT INTO document_settings (header,signatory) VALUES ('$header','$signatory')");
        }
        return;
    }

    if ($key === "system") {
        $language    = $conn->real_escape_string((string)($value["language"]   ?? "Filipino"));
        $date_format = $conn->real_escape_string((string)($value["dateFormat"] ?? "MM/DD/YYYY"));
        $existing    = $conn->query("SELECT system_settings_id FROM system_settings LIMIT 1")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE system_settings SET language='$language',date_format='$date_format' WHERE system_settings_id={$existing["system_settings_id"]}");
        } else {
            $conn->query("INSERT INTO system_settings (language,date_format) VALUES ('$language','$date_format')");
        }
        return;
    }
}

/**
 * Generate the next ID code in the format PREFIX-YEAR-000001
 * Uses the actual current year for new records.
 */
function nextCode(mysqli $conn, string $table, string $column, string $prefix): string
{
    $year = date("Y");
    $yearPrefix = $prefix . $year . "-";

    $escaped = $conn->real_escape_string($yearPrefix);
    $result = $conn->query(
        "SELECT {$column} AS code FROM {$table}
         WHERE {$column} LIKE '{$escaped}%'"
    );

    $max = 0;
    while ($row = $result->fetch_assoc()) {
        if (preg_match('/(\d+)$/', (string) ($row["code"] ?? ""), $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return $yearPrefix . str_pad((string) ($max + 1), 6, "0", STR_PAD_LEFT);
}

function fetchResidents(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT r.residents_id, r.full_name, r.birthday, r.gender, r.civil_status,
                r.address, r.contact, r.household_id, r.household, r.zone_name, r.status,
                r.archived, r.created_at,
                h.household_id AS h_household_id, h.head_name
         FROM residents r
         LEFT JOIN households h ON r.household_id = h.household_id
         WHERE r.archived = 0
         ORDER BY r.created_at DESC, r.residents_id DESC"
    );

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $householdDisplay = $row["h_household_id"] ? $row["h_household_id"] : $row["household"];
        $rows[] = [
            "dbId"        => $row["residents_id"],
            "id"          => $row["residents_id"],
            "fullName"    => $row["full_name"],
            "birthday"    => $row["birthday"] ?? "",
            "gender"      => $row["gender"] ?? "",
            "civilStatus" => $row["civil_status"] ?? "",
            "address"     => $row["address"],
            "contact"     => $row["contact"] ?? "",
            "householdId" => $row["household_id"] ?? null,
            "household"   => $householdDisplay,
            "zone"        => $row["zone_name"],
            "status"      => $row["status"],
            "createdAt"   => substr((string) $row["created_at"], 0, 10),
            "archived"    => false,
        ];
    }

    $archived = $conn->query(
        "SELECT archived_residents_id, resident_code, full_name, birthday, gender, civil_status,
                address, contact, household, zone_name, archived_at
         FROM archived_residents ORDER BY archived_at DESC, archived_residents_id DESC"
    );
    while ($row = $archived->fetch_assoc()) {
        $rows[] = [
            "dbId"        => (int) $row["archived_residents_id"],
            "id"          => $row["resident_code"],
            "fullName"    => $row["full_name"],
            "birthday"    => $row["birthday"] ?? "",
            "gender"      => $row["gender"] ?? "",
            "civilStatus" => $row["civil_status"] ?? "",
            "address"     => $row["address"],
            "contact"     => $row["contact"] ?? "",
            "householdId" => null,
            "household"   => $row["household"],
            "zone"        => $row["zone_name"],
            "status"      => "Archived",
            "createdAt"   => substr((string) $row["archived_at"], 0, 10),
            "archived"    => true,
            "archivedAt"  => $row["archived_at"] ?? "",
        ];
    }

    return $rows;
}

function fetchHouseholds(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT h.household_id, h.head_name, h.address,
                COUNT(r.residents_id) AS resident_count
         FROM households h
         LEFT JOIN residents r ON r.household_id = h.household_id AND r.archived = 0
         GROUP BY h.household_id
         ORDER BY h.household_id DESC"
    );

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            "dbId"    => $row["household_id"],
            "id"      => $row["household_id"],
            "head"    => $row["head_name"],
            "address" => $row["address"],
            "members" => (int) $row["resident_count"],
        ];
    }
    return $rows;
}

function fetchDocuments(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT d.request_id, d.resident_name, d.document_type, d.date_requested,
                d.status, d.notes, d.rejection_reason, d.released_at, d.released_by,
                d.processed_by, d.archived,
                (d.proof_photo IS NOT NULL) AS has_proof,
                d.recipient_name, d.relationship_to_requester,
                rb.full_name AS released_by_name,
                pb.full_name AS processed_by_name
         FROM documents d
         LEFT JOIN users rb ON d.released_by = rb.users_id
         LEFT JOIN users pb ON d.processed_by = pb.users_id
         WHERE d.archived = 0
         ORDER BY d.date_requested DESC, d.request_id DESC"
    );

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            "dbId"            => $row["request_id"],
            "id"              => $row["request_id"],
            "name"            => $row["resident_name"],
            "type"            => $row["document_type"],
            "dateRequested"   => $row["date_requested"],
            "status"          => $row["status"],
            "notes"           => $row["notes"] ?? "",
            "rejectionReason" => $row["rejection_reason"] ?? "",
            "releasedAt"              => $row["released_at"] ?? "",
            "releasedBy"              => $row["released_by_name"] ?? "",
            "processedBy"             => $row["processed_by_name"] ?? "",
            "hasProof"                => (bool) $row["has_proof"],
            "recipientName"           => $row["recipient_name"] ?? "",
            "relationshipToRequester" => $row["relationship_to_requester"] ?? "",
            "archived"                => false,
        ];
    }

    $archived = $conn->query(
        "SELECT archived_documents_id, request_code, resident_name, document_type, date_requested, status,
                notes, rejection_reason, released_at, released_by_name, processed_by_name,
                recipient_name, relationship_to_requester,
                (proof_photo IS NOT NULL) AS has_proof,
                archived_at
         FROM archived_documents ORDER BY archived_at DESC, archived_documents_id DESC"
    );
    while ($row = $archived->fetch_assoc()) {
        $rows[] = [
            "dbId"            => (int) $row["archived_documents_id"],
            "id"              => $row["request_code"],
            "name"            => $row["resident_name"],
            "type"            => $row["document_type"],
            "dateRequested"   => $row["date_requested"],
            "status"          => $row["status"],
            "notes"           => $row["notes"] ?? "",
            "rejectionReason" => $row["rejection_reason"] ?? "",
            "releasedAt"              => $row["released_at"] ?? "",
            "releasedBy"              => $row["released_by_name"] ?? "",
            "processedBy"             => $row["processed_by_name"] ?? "",
            "hasProof"                => (bool) $row["has_proof"],
            "recipientName"           => $row["recipient_name"] ?? "",
            "relationshipToRequester" => $row["relationship_to_requester"] ?? "",
            "archived"                => true,
            "archivedAt"              => $row["archived_at"] ?? "",
        ];
    }

    return $rows;
}

function fetchUsers(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT users_id, username, full_name, role, email, contact, is_online, is_active,
                (profile_photo IS NOT NULL) AS has_photo
         FROM users ORDER BY users_id ASC"
    );

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            "dbId"      => (int) $row["users_id"],
            "id"        => "U-" . str_pad((string) $row["users_id"], 3, "0", STR_PAD_LEFT),
            "username"  => $row["username"],
            "fullName"  => $row["full_name"],
            "role"      => $row["role"],
            "email"     => $row["email"],
            "contact"   => $row["contact"],
            "isOnline"  => (bool) $row["is_online"],
            "isActive"  => (bool) $row["is_active"],
            "hasPhoto"  => (bool) $row["has_photo"],
            "position"  => ROLES[$row["role"]] ?? ucfirst($row["role"]),
            "status"    => $row["is_online"] ? "Active Now" : "Offline",
        ];
    }
    return $rows;
}

function buildNotifications(mysqli $conn): array
{
    $pendingDocs  = (int) ($conn->query("SELECT COUNT(*) AS t FROM documents WHERE archived = 0 AND status = 'Pending'")->fetch_assoc()["t"] ?? 0);
    $newResidents = (int) ($conn->query("SELECT COUNT(*) AS t FROM residents WHERE archived = 0 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()["t"] ?? 0);

    return [
        $pendingDocs . " document request(s) need review.",
        $newResidents . " new resident(s) were added this week.",
        "Backup reminder: export a fresh backup before large updates.",
    ];
}

function bootstrapPayload(mysqli $conn, int $userId): array
{
    $user = $conn->prepare(
        "SELECT users_id, username, full_name, role, email, contact, (profile_photo IS NOT NULL) AS has_photo
         FROM users WHERE users_id = ?"
    );
    $user->bind_param("i", $userId);
    $user->execute();
    $me = $user->get_result()->fetch_assoc();

    return [
        "residents"     => fetchResidents($conn),
        "households"    => fetchHouseholds($conn),
        "documents"     => fetchDocuments($conn),
        "users"         => fetchUsers($conn),
        "settings"      => getAllSettings($conn),
        "notifications" => buildNotifications($conn),
        "me"            => $me ? [
            "dbId"     => (int) $me["users_id"],
            "username" => $me["username"],
            "fullName" => $me["full_name"],
            "role"     => $me["role"],
            "position" => ROLES[$me["role"]] ?? ucfirst($me["role"]),
            "email"    => $me["email"],
            "contact"  => $me["contact"],
            "hasPhoto" => (bool) $me["has_photo"],
        ] : null,
    ];
}

function replaceTableFromBackup(mysqli $conn, string $table, array $columns, array $rows): void
{
    $conn->query("DELETE FROM {$table}");
    if ($rows === []) return;

    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    $sql  = "INSERT INTO {$table} (" . implode(", ", $columns) . ") VALUES ({$placeholders})";
    $stmt = $conn->prepare($sql);

    foreach ($rows as $row) {
        $values = [];
        $types  = "";
        foreach ($columns as $col) {
            $v = $row[$col] ?? null;
            $types  .= is_int($v) ? "i" : "s";
            $values[] = is_int($v) ? $v : (string) ($v ?? "");
        }
        $refs = [$types];
        foreach ($values as $i => $_) $refs[] = &$values[$i];
        call_user_func_array([$stmt, "bind_param"], $refs);
        $stmt->execute();
    }
}
