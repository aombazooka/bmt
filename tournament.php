<?php
session_start();
require 'db.php';

// Get active/latest tournament
$stmt = $pdo->query("SELECT * FROM tournaments ORDER BY id DESC LIMIT 1");
$tourney = $stmt->fetch();

$teams = [];
$matches = [];
$standings = [];

if ($tourney) {
    $tid = $tourney['id'];

    // Load teams
    $stmt = $pdo->prepare("
        SELECT t.*, 
        p1.name as p1_name, p1.skill_tier as p1_tier,
        p2.name as p2_name, p2.skill_tier as p2_tier
        FROM tournament_teams t
        JOIN players p1 ON t.p1 = p1.id
        JOIN players p2 ON t.p2 = p2.id
        WHERE t.tournament_id = ?
    ");
    $stmt->execute([$tid]);
    $teamsList = $stmt->fetchAll();

    foreach ($teamsList as $t) {
        $standings[$t['id']] = [
            'team' => $t,
            'matches_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'points' => 0,
            'point_diff' => 0
        ];
    }

    // Load matches
    $stmt = $pdo->prepare("
        SELECT m.*, 
        t1.team_name as t1_name, t2.team_name as t2_name,
        c.name as court_name
        FROM tournament_matches m
        JOIN tournament_teams t1 ON m.team1_id = t1.id
        JOIN tournament_teams t2 ON m.team2_id = t2.id
        LEFT JOIN courts c ON m.court_id = c.id
        WHERE m.tournament_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$tid]);
    $matches = $stmt->fetchAll();

    // Compute standings from Completed matches
    foreach ($matches as $m) {
        if ($m['status'] === 'Completed') {
            $t1 = $m['team1_id'];
            $t2 = $m['team2_id'];
            $s1 = (int) $m['t1_score'];
            $s2 = (int) $m['t2_score'];

            $standings[$t1]['matches_played']++;
            $standings[$t2]['matches_played']++;

            $standings[$t1]['point_diff'] += ($s1 - $s2);
            $standings[$t2]['point_diff'] += ($s2 - $s1);

            if ($s1 > $s2) {
                $standings[$t1]['wins']++;
                $standings[$t1]['points'] += 3;
                $standings[$t2]['losses']++;
            } elseif ($s2 > $s1) {
                $standings[$t2]['wins']++;
                $standings[$t2]['points'] += 3;
                $standings[$t1]['losses']++;
            } else {
                $standings[$t1]['draws']++;
                $standings[$t2]['draws']++;
                $standings[$t1]['points'] += 1;
                $standings[$t2]['points'] += 1;
            }
        }
    }

    // Sort standings
    usort($standings, function ($a, $b) {
        if ($a['points'] != $b['points'])
            return $b['points'] <=> $a['points'];
        if ($a['point_diff'] != $b['point_diff'])
            return $b['point_diff'] <=> $a['point_diff'];
        return $a['matches_played'] <=> $b['matches_played'];
    });
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament Leaderboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: { 50: '#FDFBF7', 100: '#F8F5F0', 200: '#EBE5D9' },
                        brown: { 400: '#C4A484', 500: '#A98E71', 600: '#8C7A6B', 800: '#5C4A3D', 900: '#4A3B32' }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
        }
    </style>
</head>

<body class="bg-cream-50 text-stone-800 min-h-screen">

    <div class="max-w-5xl mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-4">
            <div>
                <h1
                    class="text-3xl md:text-5xl font-semibold text-brown-900 mb-2 tracking-tight flex items-center gap-3">
                    <span class="text-brown-500">🏆</span> Tournament Mode
                </h1>
                <p class="text-brown-600 text-sm md:text-base font-light">
                    <?= $tourney ? htmlspecialchars($tourney['name']) . ($tourney['status'] == 'Completed' ? ' (เสร็จสิ้น)' : ' (กำลังแข่งขัน)') : 'ยังไม่มีทัวร์นาเมนต์' ?>
                </p>
            </div>
            <div class="flex gap-3">
                <a href="index.php"
                    class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm">
                    กลับหน้าปกติ
                </a>
                <?php if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth']): ?>
                    <a href="admin.php"
                        class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                        เมนูแอดมิน
                    </a>
                <?php else: ?>
                    <a href="login.php"
                        class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                        เข้าสู่ระบบ
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($tourney): ?>
            <!-- Leaderboard Card -->
            <div
                class="bg-white rounded-3xl overflow-hidden shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-cream-200 mb-10">
                <div class="p-6 md:p-8 bg-brown-900/5 border-b border-cream-200 flex justify-between items-center">
                    <h2 class="text-xl md:text-2xl font-semibold text-brown-900 flex items-center gap-2">
                        <span class="tracking-wide">ตารางคะแนนทีม</span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-white text-brown-400 text-xs md:text-sm uppercase tracking-wider border-b border-cream-200">
                                <th class="px-6 py-5 font-medium text-center w-16">อันดับ</th>
                                <th class="px-6 py-5 font-medium">ทีม</th>
                                <th class="px-6 py-5 font-medium text-center">แข่ง</th>
                                <th class="px-6 py-5 font-medium text-center">ชนะ</th>
                                <th class="px-6 py-5 font-medium text-center">เสมอ</th>
                                <th class="px-6 py-5 font-medium text-center">แพ้</th>
                                <th class="px-6 py-5 font-medium text-center">ได้-เสีย</th>
                                <th class="px-6 py-5 font-semibold text-center text-brown-800">แต้มรวม</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-cream-100">
                            <?php $rank = 1;
                            foreach ($standings as $s):
                                $t = $s['team'];
                                ?>
                                <tr class="hover:bg-cream-50 transition duration-200 group">
                                    <td class="px-6 py-5 text-center">
                                        <?php if ($rank == 1): ?>
                                            <span
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-700 font-semibold border border-amber-200">1</span>
                                        <?php elseif ($rank == 2): ?>
                                            <span
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-stone-100 text-stone-600 font-semibold border border-stone-200">2</span>
                                        <?php elseif ($rank == 3): ?>
                                            <span
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-50 text-orange-700 font-semibold border border-orange-100">3</span>
                                        <?php else: ?>
                                            <span class="text-brown-400 font-medium"><?= $rank ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="font-medium text-brown-900 text-base">
                                            <?= htmlspecialchars($t['team_name']) ?>
                                        </div>
                                        <div class="text-xs text-brown-500 mt-1 flex flex-col gap-0.5">
                                            <span>• <?= htmlspecialchars($t['p1_name']) ?></span>
                                            <span>• <?= htmlspecialchars($t['p2_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center text-stone-600 font-medium"><?= $s['matches_played'] ?>
                                    </td>
                                    <td class="px-6 py-5 text-center text-green-600 font-medium"><?= $s['wins'] ?></td>
                                    <td class="px-6 py-5 text-center text-stone-500 font-medium"><?= $s['draws'] ?></td>
                                    <td class="px-6 py-5 text-center text-red-500 font-medium"><?= $s['losses'] ?></td>
                                    <td
                                        class="px-6 py-5 text-center font-medium <?= $s['point_diff'] > 0 ? 'text-green-600' : ($s['point_diff'] < 0 ? 'text-red-400' : 'text-stone-400') ?> ">
                                        <?= ($s['point_diff'] > 0 ? '+' : '') . $s['point_diff'] ?>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span
                                            class="bg-brown-800 text-white px-4 py-1.5 rounded-full font-semibold text-sm inline-block shadow-sm">
                                            <?= $s['points'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-8 border-t border-cream-200 pt-8">
                <h3 class="text-2xl font-semibold text-brown-900 mb-6">ตารางคู่แข่งขันทั้งหมด</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($matches as $m): ?>
                        <div
                            class="bg-white border <?= $m['status'] == 'Ongoing' ? 'border-orange-300 shadow-md' : ($m['status'] == 'Completed' ? 'border-green-200 bg-green-50/20' : 'border-cream-200 shadow-sm') ?> rounded-2xl p-5 overflow-hidden transition relative">
                            <!-- Status Badge -->
                            <div
                                class="absolute top-0 right-0 rounded-bl-xl px-3 py-1 text-[10px] font-bold uppercase tracking-wider
                                <?= $m['status'] == 'Ongoing' ? 'bg-orange-100 text-orange-700' : ($m['status'] == 'Completed' ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500') ?>">
                                <?= $m['status'] == 'Ongoing' ? 'กำลังแข่ง' : ($m['status'] == 'Completed' ? 'จบเกม' : 'รอดำเนินการ') ?>
                            </div>

                            <div class="mt-2 mb-4 text-center">
                                <?php if ($m['status'] == 'Ongoing'): ?>
                                    <span
                                        class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded inline-block">⚡
                                        ที่ <?= htmlspecialchars($m['court_name']) ?></span>
                                <?php elseif ($m['status'] == 'Completed'): ?>
                                    <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded inline-block">✓
                                        ผลการเเข่งขัน</span>
                                <?php else: ?>
                                    <span class="text-xs font-medium text-stone-400">--- รอเรียกตัว ---</span>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-between items-center gap-2">
                                <div class="text-center flex-1">
                                    <div class="font-medium text-brown-900 mb-1"><?= htmlspecialchars($m['t1_name']) ?></div>
                                    <?php if ($m['status'] == 'Completed'): ?>
                                        <div
                                            class="text-3xl font-bold <?= $m['t1_score'] > $m['t2_score'] ? 'text-brown-800' : 'text-stone-400' ?>">
                                            <?= $m['t1_score'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[10px] font-bold text-stone-300 tracking-widest px-1">VS</div>
                                <div class="text-center flex-1">
                                    <div class="font-medium text-brown-900 mb-1"><?= htmlspecialchars($m['t2_name']) ?></div>
                                    <?php if ($m['status'] == 'Completed'): ?>
                                        <div
                                            class="text-3xl font-bold <?= $m['t2_score'] > $m['t1_score'] ? 'text-brown-800' : 'text-stone-400' ?>">
                                            <?= $m['t2_score'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="text-center bg-white rounded-3xl py-20 px-4 border border-cream-200">
                <span class="text-6xl mb-4 block">🏸</span>
                <h3 class="text-2xl font-semibold text-brown-700 mb-2">ยังไม่มีทัวร์นาเมนต์ขณะนี้</h3>
                <p class="text-stone-500">แอดมินยังไม่ได้เริ่มระบบทัวร์นาเมนต์แบบพบกันหมด</p>
            </div>
        <?php endif; ?>

        <div class="mt-12 text-center text-sm text-brown-400 font-light">
            &copy; <?= date('Y') ?> Badminton Court Management. All rights reserved.
        </div>
    </div>
</body>

</html>