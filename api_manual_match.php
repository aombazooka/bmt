<?php
session_start();

if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $court_id = $_POST['court_id'] ?? 0;
    
    // Fetch Court
    $stmt = $pdo->prepare("SELECT id, status FROM courts WHERE id = ?");
    $stmt->execute([$court_id]);
    $court = $stmt->fetch();
    
    if (!$court || $court['status'] !== 'Available') {
        echo "<script>alert('สนามนี้ไม่ว่าง หรือ ไม่มีอยู่ในระบบ'); window.location.href='admin.php';</script>";
        exit;
    }

    $t1p1 = $_POST['t1p1'] ?? 0;
    $t1p2 = $_POST['t1p2'] ?? 0;
    $t2p1 = $_POST['t2p1'] ?? 0;
    $t2p2 = $_POST['t2p2'] ?? 0;

    $player_ids = [$t1p1, $t1p2, $t2p1, $t2p2];
    
    // Check duplicates
    if (count(array_unique($player_ids)) !== 4) {
        echo "<script>alert('กรุณาเลือกนักกีฬาให้ไม่ซ้ำกันทั้ง 4 คน'); window.location.href='admin.php';</script>";
        exit;
    }

    // Begin Transaction
    try {
        $pdo->beginTransaction();

        // Check if players are actually ready (to prevent race conditions)
        $in  = str_repeat('?,', count($player_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id FROM players WHERE id IN ($in) AND status = 'Ready'");
        $stmt->execute($player_ids);
        $ready_count = $stmt->rowCount();
        
        if ($ready_count !== 4) {
            $pdo->rollBack();
            echo "<script>alert('มีนักกีฬาบางคนไม่ได้อยู่ในสถานะ พร้อมเล่น กรุณาตรวจสอบอีกครั้ง'); window.location.href='admin.php';</script>";
            exit;
        }

        // Insert Match
        $stmt = $pdo->prepare("INSERT INTO matches (court_id, type, t1p1, t1p2, t2p1, t2p2, status, start_time) VALUES (?, 'Doubles', ?, ?, ?, ?, 'Ongoing', NOW())");
        $stmt->execute([$court_id, $t1p1, $t1p2, $t2p1, $t2p2]);

        // Update Court
        $stmt = $pdo->prepare("UPDATE courts SET status = 'Occupied' WHERE id = ?");
        $stmt->execute([$court_id]);

        // Update Players => Playing
        $stmt = $pdo->prepare("UPDATE players SET status = 'Playing' WHERE id IN ($in)");
        $stmt->execute($player_ids);

        $pdo->commit();
        header("Location: admin.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.location.href='admin.php';</script>";
        exit;
    }
} else {
    header("Location: admin.php");
    exit;
}
?>
