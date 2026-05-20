<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_auth'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require 'db.php';

// 1. Find at least 4 ready players
$stmt = $pdo->query("SELECT id, name, skill_tier FROM players WHERE status = 'Ready' ORDER BY RAND() LIMIT 4");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($players) < 4) {
    echo json_encode(['success' => false, 'message' => 'มีผู้เล่นสถานะ พร้อมเล่น ไม่ถึง 4 คน']);
    exit;
}

// 2. Smart Balancing Logic (S=5, A=4, B=3, C=2, Beginner=1)
$tierValue = ['S' => 5, 'A' => 4, 'B' => 3, 'C' => 2, 'Beginner' => 1];

// Sort players by skill value (Highest first)
usort($players, function($a, $b) use ($tierValue) {
    return $tierValue[$b['skill_tier']] <=> $tierValue[$a['skill_tier']];
});

// Balance Teams: P1+P4 vs P2+P3
$t1p1 = $players[0]; // Highest
$t1p2 = $players[3]; // Lowest

$t2p1 = $players[1]; // 2nd Highest
$t2p2 = $players[2]; // 3rd Highest

// Return drafted payload
echo json_encode([
    'success' => true,
    'draft' => [
        't1p1' => $t1p1,
        't1p2' => $t1p2,
        't2p1' => $t2p1,
        't2p2' => $t2p2
    ]
]);
?>
