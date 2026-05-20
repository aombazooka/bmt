<?php
session_start();

if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_system'])) {
    try {
        $pdo->beginTransaction();

        // 1. Delete all matches to start score over
        $pdo->exec("DELETE FROM matches");

        // 2. Reset all courts to Available
        $pdo->exec("UPDATE courts SET status = 'Available'");

        // 3. Reset all players to Ready
        $pdo->exec("UPDATE players SET status = 'Ready'");

        $pdo->commit();
        
        // Return JSON if called via fetch, or redirect if form
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        header("Location: admin.php?msg=reset_success");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.location.href='admin.php';</script>";
        exit;
    }
}
?>
