<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_auth'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matchId = $_POST['match_id'] ?? 0;
    $team1Score = (int)($_POST['team1_score'] ?? 0);
    $team2Score = (int)($_POST['team2_score'] ?? 0);

    if (!$matchId) {
        echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Fetch match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ? AND status = 'Ongoing'");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();

        if (!$match) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการแข่งขันที่กำลังดำเนินอยู่']);
            exit;
        }

        // 2. Update Match
        $stmt = $pdo->prepare("UPDATE matches SET team1_score = ?, team2_score = ?, status = 'Completed', end_time = NOW() WHERE id = ?");
        $stmt->execute([$team1Score, $team2Score, $matchId]);

        // 3. Free Court
        $stmt = $pdo->prepare("UPDATE courts SET status = 'Available' WHERE id = ?");
        $stmt->execute([$match['court_id']]);

        // 4. Update Players status back to Ready
        $pids = array_filter([$match['t1p1'], $match['t1p2'], $match['t2p1'], $match['t2p2']]);
        if (!empty($pids)) {
            $in = str_repeat('?,', count($pids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE players SET status = 'Ready' WHERE id IN ($in)");
            $stmt->execute(array_values($pids));
        }

        // --- Persistent Stats Logic ---
        $team1Players = array_filter([$match['t1p1'], $match['t1p2']]);
        $team2Players = array_filter([$match['t2p1'], $match['t2p2']]);
        $allPlayersInMatch = array_merge($team1Players, $team2Players);

        if (!empty($allPlayersInMatch)) {
            $inList = implode(',', array_fill(0, count($allPlayersInMatch), '?'));
            $pdo->prepare("UPDATE players SET total_matches = total_matches + 1 WHERE id IN ($inList)")->execute($allPlayersInMatch);
        }

        if ($team1Score > $team2Score) {
            if (!empty($team1Players)) {
                $in = implode(',', array_fill(0, count($team1Players), '?'));
                $pdo->prepare("UPDATE players SET total_wins = total_wins + 1, total_points = total_points + 3 WHERE id IN ($in)")->execute($team1Players);
            }
            if (!empty($team2Players)) {
                $in = implode(',', array_fill(0, count($team2Players), '?'));
                $pdo->prepare("UPDATE players SET total_losses = total_losses + 1 WHERE id IN ($in)")->execute($team2Players);
            }
        } elseif ($team2Score > $team1Score) {
            if (!empty($team2Players)) {
                $in = implode(',', array_fill(0, count($team2Players), '?'));
                $pdo->prepare("UPDATE players SET total_wins = total_wins + 1, total_points = total_points + 3 WHERE id IN ($in)")->execute($team2Players);
            }
            if (!empty($team1Players)) {
                $in = implode(',', array_fill(0, count($team1Players), '?'));
                $pdo->prepare("UPDATE players SET total_losses = total_losses + 1 WHERE id IN ($in)")->execute($team1Players);
            }
        } else {
            // Draw
            if (!empty($allPlayersInMatch)) {
                $inList = implode(',', array_fill(0, count($allPlayersInMatch), '?'));
                $pdo->prepare("UPDATE players SET total_points = total_points + 1 WHERE id IN ($inList)")->execute($allPlayersInMatch);
            }
        }
        // ------------------------------

        // Optional: Update Event ranking/stats here if necessary, but we calculate it dynamically in index.php

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'บันทึกคะแนนเรียบร้อย']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}
?>
