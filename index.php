<?php
session_start();
require 'db.php';

// Prepare data: Calculate standings
$stmt = $pdo->query("SELECT id, name, skill_tier FROM players");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$standings = [];
foreach ($players as $p) {
    $standings[$p['id']] = [
        'name' => $p['name'],
        'tier' => $p['skill_tier'],
        'matches_played' => 0,
        'wins' => 0,
        'losses' => 0,
        'points' => 0,
        'point_diff' => 0
    ];
}

$stmt = $pdo->query("SELECT type, t1p1, t1p2, t2p1, t2p2, team1_score, team2_score FROM matches WHERE status='Completed'");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($matches as $m) {
    $t1_score = (int)$m['team1_score'];
    $t2_score = (int)$m['team2_score'];
    
    $t1_won = $t1_score > $t2_score;
    $t2_won = $t2_score > $t1_score;
    
    $t1_players = array_filter([$m['t1p1'], $m['t1p2']]);
    $t2_players = array_filter([$m['t2p1'], $m['t2p2']]);
    
    foreach ($t1_players as $pid) {
        if (!isset($standings[$pid])) continue;
        $standings[$pid]['matches_played']++;
        if ($t1_won) {
            $standings[$pid]['wins']++;
            $standings[$pid]['points'] += 3;
        } else if (!$t2_won && $t1_score == $t2_score) {
            $standings[$pid]['points'] += 1; 
        } else {
            $standings[$pid]['losses']++;
        }
        $standings[$pid]['point_diff'] += ($t1_score - $t2_score);
    }
    
    foreach ($t2_players as $pid) {
        if (!isset($standings[$pid])) continue;
        $standings[$pid]['matches_played']++;
        if ($t2_won) {
            $standings[$pid]['wins']++;
            $standings[$pid]['points'] += 3;
        } else if (!$t1_won && $t1_score == $t2_score) {
            $standings[$pid]['points'] += 1; 
        } else {
            $standings[$pid]['losses']++;
        }
        $standings[$pid]['point_diff'] += ($t2_score - $t1_score);
    }
}

usort($standings, function($a, $b) {
    if ($a['points'] != $b['points']) return $b['points'] <=> $a['points'];
    if ($a['point_diff'] != $b['point_diff']) return $b['point_diff'] <=> $a['point_diff'];
    return $a['matches_played'] <=> $b['matches_played'];
});
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badminton Leaderboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-cream-50 text-stone-800 min-h-screen">

    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-4">
            <div>
                <h1 class="text-4xl md:text-5xl font-semibold text-brown-900 mb-2 tracking-tight">
                    Smart Court
                </h1>
                <p class="text-brown-600 text-sm md:text-base font-light">ตารางอันดับนักกีฬาแบดมินตัน</p>
            </div>
            <div class="flex gap-3">
                <a href="tournament.php" class="px-6 py-2.5 rounded-full bg-brown-800 text-white hover:bg-brown-900 transition text-sm font-medium shadow-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                    ชมทัวร์นาเมนต์
                </a>
                <?php if(isset($_SESSION['admin_auth']) && $_SESSION['admin_auth']): ?>
                <a href="admin.php" class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    เมนูแอดมิน
                </a>
                <?php else: ?>
                <a href="login.php" class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    เข้าสู่ระบบแอดมิน
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leaderboard Card -->
        <div class="bg-white rounded-3xl overflow-hidden shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-cream-200">
            <div class="p-6 md:p-8 bg-cream-100 border-b border-cream-200 flex justify-between items-center">
                <h2 class="text-xl md:text-2xl font-semibold text-brown-900 flex items-center gap-2">
                    <span class="tracking-wide">Leaderboard</span>
                </h2>
                <div class="text-xs md:text-sm text-brown-600 bg-white px-3 py-1 rounded-full border border-cream-200 shadow-sm">อัปเดตแบบเรียลไทม์</div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white text-brown-400 text-xs md:text-sm uppercase tracking-wider border-b border-cream-200">
                            <th class="px-6 py-5 font-medium text-center w-16">อันดับ</th>
                            <th class="px-6 py-5 font-medium">นักกีฬา</th>
                            <th class="px-6 py-5 font-medium text-center hidden md:table-cell">แมตช์ที่เล่น</th>
                            <th class="px-6 py-5 font-medium text-center">ชนะ</th>
                            <th class="px-6 py-5 font-medium text-center">แพ้</th>
                            <th class="px-6 py-5 font-medium text-center">+/-</th>
                            <th class="px-6 py-5 font-semibold text-center text-brown-800">คะแนน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cream-100">
                        <?php $rank = 1; foreach($standings as $pid => $p): ?>
                        <tr class="hover:bg-cream-50 transition duration-200 group">
                            <td class="px-6 py-5 text-center">
                                <?php if($rank == 1): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-700 font-semibold border border-amber-200">1</span>
                                <?php elseif($rank == 2): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-stone-100 text-stone-600 font-semibold border border-stone-200">2</span>
                                <?php elseif($rank == 3): ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-50 text-orange-700 font-semibold border border-orange-100">3</span>
                                <?php else: ?>
                                    <span class="text-brown-400 font-medium"><?= $rank ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-cream-100 text-brown-800 flex items-center justify-center font-semibold text-lg border border-cream-200">
                                        <?= mb_substr($p['name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-brown-900 text-base md:text-lg"><?= htmlspecialchars($p['name']) ?></div>
                                        <div class="text-xs text-brown-500 mt-0.5">มือระดับ <?= htmlspecialchars($p['tier']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center hidden md:table-cell text-stone-600 font-light">
                                <?= $p['matches_played'] ?>
                            </td>
                            <td class="px-6 py-5 text-center text-stone-600 font-medium">
                                <?= $p['wins'] ?>
                            </td>
                            <td class="px-6 py-5 text-center text-stone-400 font-medium">
                                <?= $p['losses'] ?>
                            </td>
                            <td class="px-6 py-5 text-center font-medium <?= $p['point_diff'] > 0 ? 'text-green-600' : ($p['point_diff'] < 0 ? 'text-red-400' : 'text-stone-400') ?>">
                                <?= ($p['point_diff'] > 0 ? '+' : '') . $p['point_diff'] ?>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="bg-brown-800 text-white px-4 py-1.5 rounded-full font-semibold text-sm inline-block shadow-sm">
                                    <?= $p['points'] ?> pts
                                </span>
                            </td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                        
                        <?php if(empty($standings)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center text-brown-400">
                                🏸 ยังไม่มีข้อมูลตารางคะแนน เริ่มจัดการแข่งขันเพื่อดูอันดับ
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-12 text-center text-sm text-brown-400 font-light">
            &copy; <?= date('Y') ?> Badminton Court Management. All rights reserved.
        </div>
    </div>

</body>
</html>
