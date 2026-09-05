<?php
/**
 * ============================================================
 * ONE-TIME SETUP SCRIPT — run once to add sample medicines.
 * DELETE this file after running it.
 * Usage: visit http://localhost/clinic-system/sql/seed_medicines.php
 * ============================================================
 */
require_once __DIR__ . '/../backend/config.php';
$pdo = getDBConnection();

$medicines = [
    // name, category, is_proprietary, unit_price, stock_qty, expiry_date
    ['Panadol 500mg',        'Painkiller',    0, 5.00,  500, '2027-06-30'],
    ['Amoxicillin 500mg',    'Antibiotic',    0, 12.00, 200, '2027-01-15'],
    ['Piriton Tablets',      'Antihistamine', 0, 8.00,  300, '2026-12-01'],
    ['Vitamin C 1000mg',     'Supplement',    0, 15.00, 150, '2027-03-20'],
    ['Omeprazole 20mg',      'Antacid',       0, 20.00, 100, '2027-02-10'],
    // 6 proprietary clinic products
    ['ClinicCare Cough Syrup',    'Proprietary', 1, 350.00, 80, '2027-05-01'],
    ['ClinicCare Herbal Balm',    'Proprietary', 1, 450.00, 60, '2027-05-01'],
    ['ClinicCare Multivitamin',   'Proprietary', 1, 900.00, 100, '2027-08-01'],
    ['ClinicCare Skin Ointment',  'Proprietary', 1, 550.00, 70, '2027-06-15'],
    ['ClinicCare Digestive Tonic','Proprietary', 1, 650.00, 90, '2027-07-01'],
    ['ClinicCare Immune Booster', 'Proprietary', 1, 1200.00, 50, '2027-09-01'],
];

$stmt = $pdo->prepare(
    'INSERT INTO medicines (name, category, is_proprietary, unit_price, stock_qty, expiry_date)
     VALUES (?, ?, ?, ?, ?, ?)'
);

foreach ($medicines as $m) {
    $stmt->execute($m);
    echo "Added medicine: {$m[0]}\n";
}

echo "\nDone. Please delete seed_medicines.php now.\n";
