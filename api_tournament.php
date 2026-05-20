<?php
session_start();
if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$action = $_POST['action'] ?? '';

if ($action === 'quick_add_player') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $tier = $_POST['tier'] ?? 'Beginner';
    
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้เล่น']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO players (name, skill_tier, status) VALUES (?, ?, 'Ready')");
        $stmt->execute([$name, $tier]);
        $playerId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'player' => [
                'id' => (int)$playerId,
                'name' => $name,
                'skill_tier' => $tier,
                'status' => 'Ready'
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'create') {
    $t_name = $_POST['tournament_name'];
    $teams = $_POST['teams'] ?? [];

    if (count($teams) < 2) {
        die("Need at least 2 teams.");
    }

    try {
        $pdo->beginTransaction();

        // Ensure we cancel any existing draft/ongoing tournament to satisfy "only 1 active" / "no history" requirement
        $pdo->exec("DELETE FROM tournaments"); // This cascades to teams and matches because we setup ON DELETE CASCADE
        $pdo->exec("UPDATE courts SET status = 'Available'");

        // Create new tournament
        $stmt = $pdo->prepare("INSERT INTO tournaments (name, status) VALUES (?, 'Ongoing')");
        $stmt->execute([$t_name]);
        $tourney_id = $pdo->lastInsertId();

        $teamIds = [];
        $i = 1;
        $stmtTeam = $pdo->prepare("INSERT INTO tournament_teams (tournament_id, team_name, p1, p2) VALUES (?, ?, ?, ?)");
        foreach ($teams as $team) {
            $name = "ทีม " . $i;
            $p1 = $team['p1'];
            $p2 = $team['p2'];
            $stmtTeam->execute([$tourney_id, $name, $p1, $p2]);
            $teamIds[] = $pdo->lastInsertId();
            $i++;
        }

        // Generate Round-Robin Matches (Circle Method for interleaved schedule)
        $stmtMatch = $pdo->prepare("INSERT INTO tournament_matches (tournament_id, team1_id, team2_id) VALUES (?, ?, ?)");
        
        $teamsForRR = $teamIds;
        if (count($teamsForRR) % 2 !== 0) {
            $teamsForRR[] = null; // Add a dummy "bye" team
        }
        
        $n = count($teamsForRR);
        $rounds = $n - 1;
        
        for ($round = 0; $round < $rounds; $round++) {
            for ($i = 0; $i < $n / 2; $i++) {
                $t1 = $teamsForRR[$i];
                $t2 = $teamsForRR[$n - 1 - $i];
                
                if ($t1 !== null && $t2 !== null) {
                    $stmtMatch->execute([$tourney_id, $t1, $t2]);
                }
            }
            
            // Rotate the teams array (keep the first element fixed, shift others)
            $teamsForRR = array_merge(
                [$teamsForRR[0]],
                [$teamsForRR[$n - 1]],
                array_slice($teamsForRR, 1, $n - 2)
            );
        }

        $pdo->commit();
        header("Location: admin_tournament.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

if ($action === 'deploy') {
    $match_id = $_POST['match_id'];
    $court_id = $_POST['court_id'];

    if (!$court_id) {
        die("Missing court.");
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM tournament_matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch();

        if ($match['status'] !== 'Pending') {
            throw new Exception("Match not pending.");
        }

        // Assign to court
        $stmt = $pdo->prepare("UPDATE tournament_matches SET status = 'Ongoing', court_id = ?, start_time = NOW() WHERE id = ?");
        $stmt->execute([$court_id, $match_id]);

        $stmt = $pdo->prepare("UPDATE courts SET status = 'Occupied' WHERE id = ?");
        $stmt->execute([$court_id]);

        $pdo->commit();
        header("Location: admin_tournament.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

if ($action === 'submit_score') {
    $match_id = $_POST['match_id'];
    $t1_score = $_POST['t1_score'];
    $t2_score = $_POST['t2_score'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM tournament_matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch();

        if ($match['status'] !== 'Ongoing') {
            throw new Exception("Match not ongoing.");
        }

        $stmt = $pdo->prepare("UPDATE tournament_matches SET status = 'Completed', t1_score = ?, t2_score = ?, end_time = NOW() WHERE id = ?");
        $stmt->execute([$t1_score, $t2_score, $match_id]);

        $stmt = $pdo->prepare("UPDATE courts SET status = 'Available' WHERE id = ?");
        $stmt->execute([$match['court_id']]);

        // Check if tournament is completely done
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tournament_matches WHERE tournament_id = ? AND status != 'Completed'");
        $stmt->execute([$match['tournament_id']]);
        $remaining = $stmt->fetchColumn();

        if ($remaining == 0) {
            $stmt = $pdo->prepare("UPDATE tournaments SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$match['tournament_id']]);
        }

        // --- Persistent Stats Logic ---
        // Fetch teams to get players
        $stmt = $pdo->prepare("SELECT * FROM tournament_teams WHERE id IN (?, ?)");
        $stmt->execute([$match['team1_id'], $match['team2_id']]);
        $teamsData = $stmt->fetchAll();
        $team1Players = [];
        $team2Players = [];
        foreach ($teamsData as $td) {
            if ($td['id'] == $match['team1_id']) $team1Players = [$td['p1'], $td['p2']];
            if ($td['id'] == $match['team2_id']) $team2Players = [$td['p1'], $td['p2']];
        }

        $allPlayersInMatch = array_merge($team1Players, $team2Players);
        if (!empty($allPlayersInMatch)) {
            $inList = implode(',', array_fill(0, count($allPlayersInMatch), '?'));
            $pdo->prepare("UPDATE players SET total_matches = total_matches + 1 WHERE id IN ($inList)")->execute($allPlayersInMatch);
        }

        if ($t1_score > $t2_score) {
            if (!empty($team1Players)) {
                $in = implode(',', array_fill(0, count($team1Players), '?'));
                $pdo->prepare("UPDATE players SET total_wins = total_wins + 1, total_points = total_points + 3 WHERE id IN ($in)")->execute($team1Players);
            }
            if (!empty($team2Players)) {
                $in = implode(',', array_fill(0, count($team2Players), '?'));
                $pdo->prepare("UPDATE players SET total_losses = total_losses + 1 WHERE id IN ($in)")->execute($team2Players);
            }
        } elseif ($t2_score > $t1_score) {
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

        $pdo->commit();
        header("Location: admin_tournament.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

if ($action === 'cancel') {
    $tourney_id = $_POST['tournament_id'];
    try {
        $pdo->beginTransaction();
        
        // Free occupied courts
        $pdo->exec("UPDATE courts SET status = 'Available' WHERE id IN (SELECT court_id FROM tournament_matches WHERE tournament_id = $tourney_id AND status = 'Ongoing')");

        $stmt = $pdo->prepare("DELETE FROM tournaments WHERE id = ?");
        $stmt->execute([$tourney_id]);

        $pdo->commit();
        header("Location: admin_tournament.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

if ($action === 'edit_team') {
    $team_id = $_POST['team_id'];
    $p1 = $_POST['p1'];
    $p2 = $_POST['p2'];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE tournament_teams SET p1 = ?, p2 = ? WHERE id = ?");
        $stmt->execute([$p1, $p2, $team_id]);
        $pdo->commit();
        header("Location: admin_tournament.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
