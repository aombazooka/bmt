<?php
session_start();
if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Handle Add Player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = $_POST['name'] ?? '';
    $tier = $_POST['tier'] ?? 'Beginner';
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO players (name, skill_tier) VALUES (?, ?)");
        $stmt->execute([$name, $tier]);
    }
    header("Location: players.php");
    exit;
}

// Handle Delete Player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $pid = $_POST['player_id'];
    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$pid]);
    header("Location: players.php");
    exit;
}

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'status') {
    $pid = $_POST['player_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE players SET status = ? WHERE id = ?");
    $stmt->execute([$status, $pid]);
    header("Location: players.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM players ORDER BY skill_tier, name");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    if ($status === 'Ready') return '<span class="px-3 py-1 rounded-full bg-green-50 text-green-700 border border-green-200 text-xs font-semibold w-full inline-block text-center shadow-sm">พร้อมเล่น</span>';
    if ($status === 'Playing') return '<span class="px-3 py-1 rounded-full bg-orange-50 text-orange-700 border border-orange-200 text-xs font-semibold w-full inline-block text-center shadow-sm">กำลังเล่น</span>';
    if ($status === 'Break') return '<span class="px-3 py-1 rounded-full bg-stone-100 text-stone-600 border border-stone-200 text-xs font-semibold w-full inline-block text-center shadow-sm">พัก/กลับแล้ว</span>';
    return '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้เล่น | Admin Panel</title>
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
<body class="bg-cream-50 text-stone-800">

    <nav class="bg-white border-b border-cream-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-4">
            <h1 class="text-brown-900 text-lg md:text-xl font-semibold tracking-wide flex items-center gap-2">
                <span class="text-brown-500">🏸</span> Smart Court Admin
            </h1>
            <div class="flex gap-1 md:gap-4 items-center">
                <a href="admin.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-3 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">จัดการสนาม</a>
                <a href="players.php" class="text-brown-900 bg-cream-100 px-3 md:px-4 py-2 rounded-full font-medium text-xs md:text-sm border border-cream-200 shadow-sm transition">ผู้เล่น</a>
                <a href="player_stats.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm text-green-700">🏆 สถิติ</a>
                <a href="index.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ดูคะแนนปกติ</a>
                <a href="tournament.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ดูคะแนนทัวร์</a>
                <div class="h-6 w-px bg-cream-200 mx-2"></div>
                <a href="logout.php" class="text-red-400 hover:text-red-600 font-medium text-xs md:text-sm py-2 px-2 transition">ออก</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8 md:py-12">
        
        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-8 gap-4 border-b border-cream-200 pb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-semibold text-brown-900 mb-1 leading-tight">ระบบจัดการนักกีฬา</h2>
                <p class="text-stone-500 text-sm font-light">เพิ่มผู้เล่นและอัปเดตสถานะความพร้อม</p>
            </div>
            
            <!-- Add Player Form -->
            <form method="POST" class="flex flex-col sm:flex-row gap-2 w-full xl:w-auto">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="ชื่อผู้เล่น..." required class="border border-cream-200 rounded-xl px-4 py-2.5 text-sm focus:ring-1 focus:ring-brown-400 outline-none w-full sm:w-64 bg-white shadow-sm">
                <select name="tier" class="border border-cream-200 rounded-xl px-4 py-2.5 text-sm focus:ring-1 focus:ring-brown-400 outline-none sm:w-32 bg-white shadow-sm cursor-pointer text-stone-600">
                    <option value="S">มือ S</option>
                    <option value="A">มือ A</option>
                    <option value="B">มือ B</option>
                    <option value="C">มือ C</option>
                    <option value="Beginner" selected>Beginner</option>
                </select>
                <button type="submit" class="bg-brown-800 hover:bg-brown-900 text-white font-medium py-2.5 px-5 rounded-xl text-sm shadow-sm transition whitespace-nowrap">
                    + เพิ่มรายชื่อ
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach($players as $p): ?>
                <div class="bg-white rounded-2xl shadow-sm border <?= $p['status'] == 'Ready' ? 'border-green-100 border-l-4 border-l-green-400' : ($p['status'] == 'Playing' ? 'border-orange-100 border-l-4 border-l-orange-400' : 'border-stone-100 border-l-4 border-l-stone-300') ?> p-5 flex flex-col justify-between hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="font-semibold text-lg text-brown-900"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-brown-400 uppercase tracking-widest mt-0.5 font-medium">มือระดับ <?= htmlspecialchars($p['skill_tier']) ?></div>
                        </div>
                        <div class="w-24 shrink-0">
                            <?= getStatusBadge($p['status']) ?>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-auto pt-4 border-t border-cream-100 flex gap-2">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="flex-1 text-sm bg-cream-50 border border-cream-200 rounded-xl px-3 py-2 outline-none focus:border-brown-400 cursor-pointer text-stone-600 font-medium <?= $p['status'] == 'Playing' ? 'opacity-50 cursor-not-allowed bg-stone-50' : '' ?>" <?= $p['status'] == 'Playing' ? 'disabled' : '' ?>>
                            <option value="Ready" <?= $p['status'] == 'Ready' ? 'selected' : '' ?>>สถานะ: พร้อมลงเล่น</option>
                            <option value="Break" <?= $p['status'] == 'Break' ? 'selected' : '' ?>>สถานะ: พัก/กลับแล้ว</option>
                        </select>
                        <button type="button" onclick="if(confirm('คุณแน่ใจหรือไม่ที่จะลบผู้เล่น <?= htmlspecialchars(addslashes($p['name'])) ?> ออกจากระบบ?')) { const f = document.createElement('form'); f.method='POST'; const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete'; const id = document.createElement('input'); id.type='hidden'; id.name='player_id'; id.value='<?= $p['id'] ?>'; f.appendChild(a); f.appendChild(id); document.body.appendChild(f); f.submit(); }" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl px-3 py-2 transition" title="ลบผู้เล่น" <?= $p['status'] == 'Playing' ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        
    </div>

</body>
</html>
