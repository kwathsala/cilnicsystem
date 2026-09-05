<?php
/**
 * ============================================================
 * ONE-TIME SETUP SCRIPT — run this once from the browser or CLI
 * to create initial login accounts. DELETE this file afterwards
 * for security.
 *
 * Usage: place in backend/ folder temporarily and visit it once,
 * or run: php seed_users.php
 * ============================================================
 */
require_once __DIR__ . '/../backend/config.php';

$pdo = getDBConnection();

// First insert a sample doctor (needed for the Doctor role link)
$pdo->exec("INSERT INTO doctors (name, specialization, default_fee) VALUES ('Dr. Perera', 'General Physician', 1000.00)");
$doctorId = $pdo->lastInsertId();

$users = [
    ['reception1', 'reception123', 'Reception', null],
    ['doctor1',    'doctor123',    'Doctor',    $doctorId],
    ['cashier1',   'cashier123',   'Cashier',   null],
    ['admin',      'admin123',     'Admin',     null],
];

$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, role, linked_doctor_id) VALUES (?, ?, ?, ?)'
);

foreach ($users as [$username, $plainPassword, $role, $linkedDoctorId]) {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt->execute([$username, $hash, $role, $linkedDoctorId]);
    echo "Created user: $username / $plainPassword ($role)\n";
}

echo "\nDone. IMPORTANT: delete seed_users.php now and change these passwords.\n";
