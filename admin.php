<?php
session_start();
if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$stmt = $pdo->query("SELECT id, name, skill_tier FROM players WHERE status = 'Ready' ORDER BY skill_tier, name");
$readyPlayers = $stmt->fetchAll();

$courts = $pdo->query("SELECT * FROM courts ORDER BY id")->fetchAll();
$activeMatches = [];
$stmt = $pdo->query("
    SELECT m.*, 
    p1.name as t1p1_name, p2.name as t1p2_name, 
    p3.name as t2p1_name, p4.name as t2p2_name
    FROM matches m
    LEFT JOIN players p1 ON m.t1p1 = p1.id
    LEFT JOIN players p2 ON m.t1p2 = p2.id
    LEFT JOIN players p3 ON m.t2p1 = p3.id
    LEFT JOIN players p4 ON m.t2p2 = p4.id
    WHERE m.status = 'Ongoing'
");
foreach ($stmt->fetchAll() as $row) {
    $activeMatches[$row['court_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสนาม | Admin Panel</title>
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
    <script>
        function updateTimer() {
            document.querySelectorAll('.timer').forEach($el => {
                let start = new Date($el.dataset.start).getTime();
                let now = new Date().getTime();
                let diff = Math.floor((now - start)/1000);
                let m = Math.floor(diff/60);
                let s = diff % 60;
                $el.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
            });
        }
        setInterval(updateTimer, 1000);

        async function openSmartMatchModal() {
            try {
                let res = await fetch('api_matchmaking.php', { method: 'POST' });
                let data = await res.json();
                
                if(!data.success) {
                    alert(data.message);
                    return;
                }

                // Fill Modal
                let draft = data.draft;
                document.getElementById('sm_t1p1_name').innerText = draft.t1p1.name + ' (' + draft.t1p1.skill_tier + ')';
                document.getElementById('sm_t1p2_name').innerText = draft.t1p2.name + ' (' + draft.t1p2.skill_tier + ')';
                document.getElementById('sm_t2p1_name').innerText = draft.t2p1.name + ' (' + draft.t2p1.skill_tier + ')';
                document.getElementById('sm_t2p2_name').innerText = draft.t2p2.name + ' (' + draft.t2p2.skill_tier + ')';
                
                // Form hidden inputs
                document.getElementById('frm_t1p1').value = draft.t1p1.id;
                document.getElementById('frm_t1p2').value = draft.t1p2.id;
                document.getElementById('frm_t2p1').value = draft.t2p1.id;
                document.getElementById('frm_t2p2').value = draft.t2p2.id;

                // Show modal
                document.getElementById('smartMatchModal').classList.remove('hidden');
                document.getElementById('smartMatchModal').classList.add('flex');

            } catch (e) {
                alert('เกิดข้อผิดพลาดในการสุ่มคิว: ' + e);
            }
        }

        function closeSmartMatchModal() {
            document.getElementById('smartMatchModal').classList.add('hidden');
            document.getElementById('smartMatchModal').classList.remove('flex');
        }

        // Score Entry
        function openScoreModal(matchId, t1Name, t2Name) {
            document.getElementById('score_match_id').value = matchId;
            document.getElementById('score_t1_name').innerText = t1Name;
            document.getElementById('score_t2_name').innerText = t2Name;
            
            document.getElementById('score_t1_input').value = '';
            document.getElementById('score_t2_input').value = '';
            
            document.getElementById('scoreModal').classList.remove('hidden');
            document.getElementById('scoreModal').classList.add('flex');
        }

        function closeScoreModal() {
            document.getElementById('scoreModal').classList.add('hidden');
            document.getElementById('scoreModal').classList.remove('flex');
        }

        async function submitScore(e) {
            e.preventDefault();
            let fd = new FormData(document.getElementById('scoreForm'));
            try {
                let res = await fetch('api_matches.php', { method: 'POST', body: fd });
                let data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดในการส่งคะแนน');
            }
        }

        function openResetModal() {
            document.getElementById('resetConfirmModal').classList.remove('hidden');
            document.getElementById('resetConfirmModal').classList.add('flex');
        }

        function closeResetModal() {
            document.getElementById('resetConfirmModal').classList.add('hidden');
            document.getElementById('resetConfirmModal').classList.remove('flex');
        }

        async function executeReset() {
            let btn = document.getElementById('btnConfirmReset');
            btn.innerText = 'กำลังล้างข้อมูล...';
            btn.disabled = true;
            
            let fd = new FormData();
            fd.append('reset_system', '1');
            
            try {
                let res = await fetch('api_reset.php', { method: 'POST', body: fd, headers: {'Accept': 'application/json'} });
                let data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert('ล้มเหลว: ' + data.message);
                    closeResetModal();
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดระบบขัดข้อง');
                closeResetModal();
            }
        }
    </script>
</head>
<body class="bg-cream-50 text-stone-800">

    <!-- Nav -->
    <nav class="bg-white border-b border-cream-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-4">
            <h1 class="text-brown-900 text-lg md:text-xl font-semibold tracking-wide flex items-center gap-2">
                <span class="text-brown-500">🏸</span> Smart Court Admin
            </h1>
            <div class="flex gap-1 md:gap-4 items-center">
                <a href="admin.php" class="text-brown-900 bg-cream-100 px-3 md:px-4 py-2 rounded-full font-medium text-xs md:text-sm border border-cream-200 shadow-sm transition">จัดการสนาม</a>
                <a href="admin_tournament.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-3 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ทัวร์นาเมนต์</a>
                <a href="players.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-3 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ผู้เล่น</a>
                <a href="player_stats.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm text-green-700">🏆 สถิติ</a>
                <a href="index.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ดูคะแนนปกติ</a>
                <a href="tournament.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-2 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">ดูคะแนนทัวร์</a>
                <div class="h-6 w-px bg-cream-200 mx-2"></div>
                <a href="logout.php" class="text-red-400 hover:text-red-600 font-medium text-xs md:text-sm py-2 px-2 transition">ออก</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8 md:py-12 relative">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-cream-200 pb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-semibold text-brown-900 mb-1">สถานะสนามปัจจุบัน</h2>
                <p class="text-stone-500 text-sm font-light">ข้อมูลอัปเดตแบบเรียลไทม์</p>
            </div>
            <div class="flex gap-3 w-full md:w-auto mt-2 md:mt-0">
                <button onclick="openResetModal()" class="bg-white border border-red-200 hover:bg-red-50 text-red-500 font-medium py-2.5 px-4 rounded-full shadow-sm hover:shadow-md transition flex items-center gap-2 text-sm justify-center w-1/2 md:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    เริ่มตารางใหม่
                </button>
                <button onclick="openSmartMatchModal()" class="bg-brown-800 hover:bg-brown-900 text-white font-medium py-2.5 px-6 rounded-full shadow-sm hover:shadow-md transition flex items-center gap-2 text-sm justify-center w-1/2 md:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m20.2 7.8-8.2 8.2-4-4"/></svg>
                    สุ่มจับคู่ (Smart Match)
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach($courts as $court): 
                $cid = $court['id'];
                $isOccupied = $court['status'] === 'Occupied' && isset($activeMatches[$cid]);
                $match = $isOccupied ? $activeMatches[$cid] : null;
            ?>
                <!-- Court Card -->
                <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border <?= $isOccupied ? 'border-orange-200' : 'border-stone-100' ?> overflow-hidden transition-all duration-300">
                    <div class="p-5 md:p-6 flex justify-between items-center <?= $isOccupied ? 'bg-orange-50/50' : 'bg-transparent' ?> border-b border-cream-100">
                        <h3 class="text-lg md:text-xl font-medium text-brown-900 flex items-center gap-2.5">
                            <?= htmlspecialchars($court['name']) ?>
                            <span class="w-2.5 h-2.5 rounded-full <?= $isOccupied ? 'bg-orange-400' : 'bg-green-400' ?>"></span>
                        </h3>
                        <div class="text-xs font-semibold px-3 py-1 rounded-full <?= $isOccupied ? 'bg-orange-100 text-orange-700' : 'bg-green-50 text-green-700 border border-green-100' ?>">
                            <?= $isOccupied ? 'กำลังแข่งขัน' : 'ว่างเว้น' ?>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">
                        <?php if($isOccupied): 
                            $t1Name = $match['t1p1_name'] . ($match['t1p2_name'] ? " & " . $match['t1p2_name'] : "");
                            $t2Name = $match['t2p1_name'] . ($match['t2p2_name'] ? " & " . $match['t2p2_name'] : "");
                        ?>
                            <!-- Match Details -->
                            <div class="text-center mb-6">
                                <div class="text-brown-800 font-medium text-sm md:text-base p-3 bg-cream-50 rounded-xl border border-cream-100"><?= htmlspecialchars($t1Name) ?></div>
                                <div class="my-3 text-xs text-stone-400 font-medium tracking-widest">VS</div>
                                <div class="text-brown-800 font-medium text-sm md:text-base p-3 bg-cream-50 rounded-xl border border-cream-100"><?= htmlspecialchars($t2Name) ?></div>
                            </div>
                            
                            <div class="flex justify-between items-center bg-brown-900 text-white rounded-2xl p-4 shadow-sm my-6">
                                <div class="text-xs text-brown-200 font-light tracking-wide">เวลาที่เล่นไปแล้ว</div>
                                <div class="text-2xl md:text-3xl font-mono font-medium timer" data-start="<?= date('Y-m-d\TH:i:s', strtotime($match['start_time'])) ?>">00:00</div>
                            </div>

                            <button onclick="openScoreModal(<?= $match['id'] ?>, '<?= addslashes($t1Name) ?>', '<?= addslashes($t2Name) ?>')" class="w-full bg-white border border-brown-200 text-brown-800 hover:bg-cream-100 font-medium py-3 rounded-xl shadow-sm transition">
                                ใส่คะแนนจบเกม
                            </button>
                        <?php else: ?>
                            <!-- Empty Court state -->
                            <div class="flex flex-col border border-dashed border-cream-200 rounded-2xl bg-cream-50/50 p-4">
                                <div class="flex flex-col items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-stone-300 mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                                    <p class="text-brown-500 text-xs font-medium">สนามพร้อมใช้งาน (Smart Match / Manual)</p>
                                </div>
                                <form method="POST" action="api_manual_match.php" class="border-t border-cream-200 pt-3">
                                    <div class="text-[11px] text-brown-600 mb-2 font-medium text-center">จัดแบบแมนนวล (ทีม 1 ปะทะ ทีม 2)</div>
                                    <input type="hidden" name="court_id" value="<?= $court['id'] ?>">
                                    <div class="grid grid-cols-2 gap-2 mb-1">
                                        <select name="t1p1" required class="w-full text-[11px] bg-white border border-cream-200 rounded-lg p-1.5 outline-none focus:border-brown-400 text-stone-700">
                                            <option value="">ทีม 1 (คนที่ 1)</option>
                                            <?php foreach($readyPlayers as $rp): ?>
                                                <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name']) ?> (<?= $rp['skill_tier'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="t1p2" required class="w-full text-[11px] bg-white border border-cream-200 rounded-lg p-1.5 outline-none focus:border-brown-400 text-stone-700">
                                            <option value="">ทีม 1 (คนที่ 2)</option>
                                            <?php foreach($readyPlayers as $rp): ?>
                                                <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name']) ?> (<?= $rp['skill_tier'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="text-center text-[10px] text-stone-400 font-bold my-1 tracking-widest">VS</div>
                                    <div class="grid grid-cols-2 gap-2 mb-3">
                                        <select name="t2p1" required class="w-full text-[11px] bg-white border border-cream-200 rounded-lg p-1.5 outline-none focus:border-brown-400 text-stone-700">
                                            <option value="">ทีม 2 (คนที่ 1)</option>
                                            <?php foreach($readyPlayers as $rp): ?>
                                                <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name']) ?> (<?= $rp['skill_tier'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="t2p2" required class="w-full text-[11px] bg-white border border-cream-200 rounded-lg p-1.5 outline-none focus:border-brown-400 text-stone-700">
                                            <option value="">ทีม 2 (คนที่ 2)</option>
                                            <?php foreach($readyPlayers as $rp): ?>
                                                <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name']) ?> (<?= $rp['skill_tier'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="w-full bg-white border border-brown-200 text-brown-800 hover:bg-cream-100 font-medium py-1.5 rounded-lg shadow-sm transition text-[11px]">
                                        นำผู้เล่นลงสนาม
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Smart Match Modal -->
    <div id="smartMatchModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-md overflow-hidden">
            <div class="p-6 border-b border-cream-100 flex justify-between items-center bg-cream-50">
                <h3 class="text-xl font-semibold text-brown-900">พรีวิวการจับคู่ (Smart Match)</h3>
                <button onclick="closeSmartMatchModal()" class="text-brown-400 hover:text-brown-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form action="api_manual_match.php" method="POST" class="p-6">
                <!-- Data Holders -->
                <input type="hidden" id="frm_t1p1" name="t1p1" value="">
                <input type="hidden" id="frm_t1p2" name="t1p2" value="">
                <input type="hidden" id="frm_t2p1" name="t2p1" value="">
                <input type="hidden" id="frm_t2p2" name="t2p2" value="">

                <div class="bg-cream-100/50 rounded-2xl p-5 mb-6 border border-cream-200">
                    <div class="text-center mb-2 text-sm text-brown-600 font-medium">ทีมที่ 1</div>
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-cream-200 shadow-sm mb-2">
                        <span id="sm_t1p1_name" class="font-medium text-brown-800"></span>
                    </div>
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-cream-200 shadow-sm">
                        <span id="sm_t1p2_name" class="font-medium text-brown-800"></span>
                    </div>

                    <div class="text-center my-4 text-xs font-bold text-stone-400 tracking-widest">VS</div>

                    <div class="text-center mb-2 text-sm text-brown-600 font-medium">ทีมที่ 2</div>
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-cream-200 shadow-sm mb-2">
                        <span id="sm_t2p1_name" class="font-medium text-brown-800"></span>
                    </div>
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-cream-200 shadow-sm">
                        <span id="sm_t2p2_name" class="font-medium text-brown-800"></span>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-brown-800 text-sm font-medium mb-2">เลือกสนามที่ต้องการลง:</label>
                    <select name="court_id" required class="w-full bg-white border border-brown-200 rounded-xl px-4 py-3 text-stone-700 outline-none focus:border-brown-400 focus:ring-1 focus:ring-brown-400">
                        <?php 
                        $hasAvailable = false;
                        foreach($courts as $c): 
                            if($c['status'] === 'Available'): 
                                $hasAvailable = true;
                        ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (ว่าง)</option>
                        <?php endif; endforeach; 
                        if(!$hasAvailable): ?>
                            <option value="">-- ไม่มีสนามว่าง --</option>
                        <?php endif; ?>
                    </select>
                </div> <!-- /field -->

                <div class="flex gap-3">
                    <button type="button" onclick="openSmartMatchModal()" class="w-1/2 bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-3 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v6h6"/></svg>
                        สุ่มใหม่ (Re-roll)
                    </button>
                    <button type="submit" class="w-1/2 bg-brown-800 hover:bg-brown-900 text-white font-medium py-3 rounded-xl shadow-sm transition">
                        ยืนยันลงสนาม
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Score Entry Modal -->
    <div id="scoreModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-sm overflow-hidden">
            <div class="p-5 border-b border-cream-100 flex justify-between items-center bg-cream-50">
                <h3 class="text-xl font-semibold text-brown-900">สรุปคะแนนจบเกม</h3>
                <button onclick="closeScoreModal()" class="text-brown-400 hover:text-brown-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form id="scoreForm" onsubmit="submitScore(event)" class="p-6">
                <input type="hidden" id="score_match_id" name="match_id" value="">

                <div class="mb-4 text-center">
                    <div class="text-sm text-brown-600 mb-1 font-medium">คะแนนทีมของ</div>
                    <div id="score_t1_name" class="font-medium text-lg text-brown-900 truncate bg-cream-100 rounded-lg p-2"></div>
                    <div class="mt-3">
                        <input type="number" id="score_t1_input" name="team1_score" required min="0" max="99" class="text-center w-24 text-3xl font-bold bg-white border border-brown-300 rounded-xl py-3 text-stone-800 outline-none focus:border-brown-500 focus:ring-2 focus:ring-brown-200">
                    </div>
                </div>

                <div class="my-4 border-t border-dashed border-cream-200 w-1/2 mx-auto"></div>

                <div class="mb-8 text-center">
                    <div class="text-sm text-brown-600 mb-1 font-medium">คะแนนทีมของ</div>
                    <div id="score_t2_name" class="font-medium text-lg text-brown-900 truncate bg-cream-100 rounded-lg p-2"></div>
                    <div class="mt-3">
                        <input type="number" id="score_t2_input" name="team2_score" required min="0" max="99" class="text-center w-24 text-3xl font-bold bg-white border border-brown-300 rounded-xl py-3 text-stone-800 outline-none focus:border-brown-500 focus:ring-2 focus:ring-brown-200">
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeScoreModal()" class="w-1/3 bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-3 rounded-xl shadow-sm transition text-sm">
                        ยกเลิก
                    </button>
                    <button type="submit" class="w-2/3 bg-brown-800 hover:bg-brown-900 text-white font-medium py-3 rounded-xl shadow-sm transition">
                        บันทึกคะแนน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div id="resetConfirmModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-sm overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h3 class="text-xl font-semibold text-brown-900 mb-2">ยืนยันการล้างตาราง?</h3>
                <p class="text-sm text-stone-500 font-light mb-6">
                    การกระทำนี้จะลบผลการแข่งขันออกจาก Leaderboard ทั้งหมด และรีเซ็ตสถานะนักกีฬากลับเป็น <span class="text-green-600 font-medium">"พร้อมเล่น"</span>
                </p>
                
                <div class="flex gap-3">
                    <button onclick="closeResetModal()" class="w-1/2 bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-3 rounded-xl shadow-sm transition text-sm">
                        ยกเลิก
                    </button>
                    <button id="btnConfirmReset" onclick="executeReset()" class="w-1/2 bg-red-500 hover:bg-red-600 border border-red-600 text-white font-medium py-3 rounded-xl shadow-sm transition">
                        ยืนยันล้างข้อมูล
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
