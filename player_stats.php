<?php
session_start();
require 'db.php';

// If DB hasn't been migrated yet, do so automatically
try {
    $pdo->exec("ALTER TABLE players ADD COLUMN total_matches INT DEFAULT 0, ADD COLUMN total_wins INT DEFAULT 0, ADD COLUMN total_losses INT DEFAULT 0, ADD COLUMN total_points INT DEFAULT 0");
} catch (Exception $e) {
    // Columns already exist
}

// Retrieve stats
$stmt = $pdo->query("SELECT * FROM players ORDER BY total_points DESC, total_wins DESC, name ASC");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถิติผู้เล่น (Player Stats)</title>
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
    <style>body { font-family: 'Kanit', sans-serif; }</style>
</head>
<body class="bg-cream-50 text-stone-800 min-h-screen">

    <div class="max-w-5xl mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-4">
            <div>
                <h1 class="text-3xl md:text-5xl font-semibold text-brown-900 mb-2 tracking-tight flex items-center gap-3">
                    <span class="text-brown-500">📊</span> สถิตินักกีฬา
                </h1>
                <p class="text-brown-600 text-sm md:text-base font-light">ข้อมูลสะสมตลอดการใช้งาน (ไม่ล้างหาย)</p>
            </div>
            <div class="flex gap-3">
                <a href="index.php" class="px-6 py-2.5 rounded-full bg-white text-brown-800 hover:bg-cream-100 transition text-sm font-medium border border-cream-200 shadow-sm">
                    กลับหน้าทัวร์นาเมนต์
                </a>
                <?php if(isset($_SESSION['admin_auth']) && $_SESSION['admin_auth']): ?>
                <a href="admin.php" class="px-6 py-2.5 rounded-full bg-brown-800 text-white hover:bg-brown-900 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                    เมนูแอดมิน
                </a>
                <?php else: ?>
                <a href="login.php" class="px-6 py-2.5 rounded-full bg-brown-800 text-white hover:bg-brown-900 transition text-sm font-medium border border-cream-200 shadow-sm flex items-center gap-2">
                    เข้าสู่ระบบ
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="bg-white rounded-3xl overflow-hidden shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-cream-200 mb-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-cream-100 text-brown-600 text-xs md:text-sm uppercase tracking-wider border-b border-cream-200">
                            <th class="px-6 py-5 font-semibold text-center w-16">#</th>
                            <th class="px-6 py-5 font-semibold">นักกีฬา</th>
                            <th class="px-6 py-5 font-semibold text-center">ระดับมือ</th>
                            <th class="px-6 py-5 font-semibold text-center">รวมแมตช์</th>
                            <th class="px-6 py-5 font-semibold text-center text-green-600">ชนะ</th>
                            <th class="px-6 py-5 font-semibold text-center text-red-500">แพ้</th>
                            <th class="px-6 py-5 font-semibold text-center text-blue-600">อัตราชนะ</th>
                            <th class="px-6 py-5 font-bold text-center text-brown-800">คะแนนสะสมสะสม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cream-100">
                        <?php 
                        $rank = 1;
                        foreach($players as $p): 
                            $win_rate = $p['total_matches'] > 0 ? round(($p['total_wins'] / $p['total_matches']) * 100) : 0;
                        ?>
                        <tr class="hover:bg-cream-50 transition duration-200 group">
                            <td class="px-6 py-5 text-center font-medium text-brown-400"><?= $rank++ ?></td>
                            <td class="px-6 py-5">
                                <div class="font-medium text-brown-900 text-base"><?= htmlspecialchars($p['name']) ?></div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="bg-stone-100 border border-stone-200 text-stone-600 text-xs px-2 py-1 rounded inline-block font-medium">
                                    <?= htmlspecialchars($p['skill_tier']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-center text-stone-600 font-medium"><?= $p['total_matches'] ?></td>
                            <td class="px-6 py-5 text-center text-green-600 font-medium"><?= $p['total_wins'] ?></td>
                            <td class="px-6 py-5 text-center text-red-500 font-medium"><?= $p['total_losses'] ?></td>
                            <td class="px-6 py-5 text-center text-blue-600 font-semibold"><?= $win_rate ?>%</td>
                            <td class="px-6 py-5 text-center">
                                <span class="bg-brown-100 text-brown-800 px-4 py-1.5 rounded-full font-bold text-sm inline-block shadow-sm">
                                    <?= $p['total_points'] ?> 
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
