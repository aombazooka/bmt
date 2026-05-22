<?php
session_start();
if (!isset($_SESSION['admin_auth'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Check for active tournament
$stmt = $pdo->query("SELECT * FROM tournaments WHERE status IN ('Draft', 'Ongoing') LIMIT 1");
$activeTournament = $stmt->fetch();

$readyPlayers = [];
$courts = [];

if (!$activeTournament) {
    // Load all players for wheel draft and creation
    $stmt = $pdo->query("SELECT id, name, skill_tier, status FROM players ORDER BY skill_tier, name");
    $readyPlayers = $stmt->fetchAll();
} else {
    // Load all players for substitution dropdown
    $stmt = $pdo->query("SELECT id, name, skill_tier FROM players ORDER BY skill_tier, name");
    $allPlayers = $stmt->fetchAll();
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
    $stmt->execute([$activeTournament['id']]);
    $teams = $stmt->fetchAll();

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
    $stmt->execute([$activeTournament['id']]);
    $matches = $stmt->fetchAll();

    // Load available courts
    $courts = $pdo->query("SELECT * FROM courts WHERE status = 'Available' ORDER BY id")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการทัวร์นาเมนต์ | Admin Panel</title>
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
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body class="bg-cream-50 text-stone-800">

    <!-- Nav -->
    <nav class="bg-white border-b border-cream-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-4">
            <h1 class="text-brown-900 text-lg md:text-xl font-semibold tracking-wide flex items-center gap-2">
                <span class="text-brown-500">🏆</span> Tournament Admin
            </h1>
            <div class="flex gap-1 md:gap-4 items-center">
                <a href="admin.php" class="text-stone-500 hover:text-brown-800 hover:bg-cream-50 px-3 md:px-4 py-2 rounded-full font-medium transition text-xs md:text-sm">จัดการสนาม</a>
                <a href="admin_tournament.php" class="text-brown-900 bg-cream-100 px-3 md:px-4 py-2 rounded-full font-medium text-xs md:text-sm border border-cream-200 shadow-sm transition">ทัวร์นาเมนต์</a>
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
        <?php if (!$activeTournament): ?>
            <!-- Create Tournament Form -->
            <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-cream-200 p-8">
                <h2 class="text-2xl font-semibold text-brown-900 mb-6 border-b border-cream-100 pb-4">สร้างทัวร์นาเมนต์ใหม่</h2>
                
                <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                    <div class="w-full md:w-1/2">
                        <label class="block text-sm font-medium text-brown-800 mb-2">จำนวนแต่ละทีม (พบกันหมด):</label>
                        <select id="teamCount" class="w-full bg-white border border-cream-200 rounded-xl px-4 py-3 text-stone-700 outline-none focus:border-brown-400" onchange="generateTeamInputs()">
                            <option value="3">3 ทีม</option>
                            <option value="4">4 ทีม</option>
                            <option value="5">5 ทีม</option>
                            <option value="6">6 ทีม</option>
                            <option value="7">7 ทีม</option>
                            <option value="8">8 ทีม</option>
                        </select>
                    </div>
                    <button type="button" onclick="autoBalanceTeams()" class="w-full md:w-auto bg-brown-100 hover:bg-brown-200 text-brown-800 border border-brown-300 font-medium py-3 px-6 rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v6h6"/></svg>
                        สุ่มจัดทีมอัตโนมัติ (สมดุลมือ)
                    </button>
                </div>

                <form action="api_tournament.php" method="POST" id="createTournamentForm">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-brown-800 mb-2">ชื่อทัวร์นาเมนต์:</label>
                        <input type="text" name="tournament_name" required value="แบดมินตัน Tournament" class="w-full bg-white border border-cream-200 rounded-xl px-4 py-3 text-stone-700 outline-none focus:border-brown-400">
                    </div>

                    <div id="teamsContainer" class="space-y-4 mb-8">
                        <!-- Inputs generated by JS -->
                    </div>

                    <button type="submit" class="w-full bg-brown-800 hover:bg-brown-900 text-white font-medium py-3.5 rounded-xl shadow-sm transition h-14">
                        ยืนยันสร้างและจัดสายการแข่งขัน
                    </button>
                </form>
            </div>

            <!-- Wheel Modal -->
            <div id="wheelModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
                <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-4xl overflow-hidden flex flex-col md:flex-row h-[90vh] md:h-auto max-h-[90vh] relative">
                    
                    <!-- Global Close Button (Mobile Friendly) -->
                    <button type="button" onclick="closeWheelModal()" class="absolute top-3 right-3 md:top-4 md:right-4 z-50 p-2 bg-white/80 backdrop-blur-sm rounded-full text-stone-400 hover:text-stone-600 hover:bg-cream-100 shadow-sm border border-cream-200 transition">
                        <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>

                    <!-- Left Column: Wheel & Results -->
                    <div class="flex-1 p-4 pt-10 md:p-6 md:pt-6 flex flex-col items-center justify-start md:justify-center border-b md:border-b-0 md:border-r border-cream-100 bg-cream-50/30 overflow-y-auto">
                        <div class="text-center mb-4">
                            <h3 class="text-xl font-semibold text-brown-900" id="wheelModalTitle">สุ่มผู้เล่นสำหรับ ทีมที่ 1</h3>
                            <p class="text-xs text-stone-500 mt-1">หมุนสุ่มรายชื่อผู้เล่นทีละคนเพื่อจัดเป็นคู่</p>
                            <button type="button" onclick="document.getElementById('wheelRightColumn').classList.toggle('hidden'); document.getElementById('wheelRightColumn').classList.toggle('flex');" class="mt-3 text-[10px] bg-white hover:bg-cream-100 text-brown-800 border border-brown-200 px-3 py-1.5 rounded-full font-medium transition flex items-center justify-center gap-1 shadow-sm mx-auto">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                จัดการรายชื่อในวงล้อ
                            </button>
                        </div>
                        
                        <!-- Canvas Container -->
                        <div class="relative w-64 h-64 md:w-80 md:h-80 flex items-center justify-center mb-6">
                            <!-- Pointer -->
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 z-10 w-0 h-0 border-l-[12px] border-r-[12px] border-t-[20px] border-l-transparent border-r-transparent border-t-red-500 drop-shadow-md"></div>
                            <canvas id="wheelCanvas" width="320" height="320" class="w-full h-full rounded-full border-4 border-brown-800 shadow-md bg-white"></canvas>
                            <!-- Center Button -->
                            <button type="button" id="btnSpin" onclick="spinWheel()" class="absolute w-14 h-14 bg-brown-900 hover:bg-brown-800 text-white rounded-full flex items-center justify-center font-bold text-xs uppercase shadow-lg border-2 border-white tracking-wider active:scale-95 transition-all">SPIN</button>
                        </div>

                        <!-- Draft Status Section -->
                        <div class="w-full max-w-md bg-white border border-cream-200 rounded-2xl p-4 shadow-sm flex flex-col gap-3">
                            <div class="flex justify-between items-center text-sm font-medium border-b border-cream-100 pb-2">
                                <span class="text-brown-800">ผลสุ่มปัจจุบัน:</span>
                                <button type="button" onclick="resetDraft()" class="text-xs text-red-500 hover:underline">สุ่มใหม่ทั้งหมด</button>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Player 1 Slot -->
                                <div class="bg-cream-50 border border-cream-200 rounded-xl p-3 text-center flex flex-col justify-between min-h-[90px]">
                                    <span class="text-xs text-stone-400 font-medium">ผู้เล่นคนที่ 1</span>
                                    <div id="draft_p1_display" class="font-semibold text-brown-950 text-sm py-1 my-1 truncate">-</div>
                                    <div id="draft_p1_actions" class="hidden">
                                        <button type="button" onclick="removeDraftPlayer(1)" class="text-[10px] text-red-500 bg-red-50 hover:bg-red-100 border border-red-100 rounded px-1.5 py-0.5 transition font-medium">ลบออกจากวงล้อ</button>
                                    </div>
                                </div>
                                <!-- Player 2 Slot -->
                                <div class="bg-cream-50 border border-cream-200 rounded-xl p-3 text-center flex flex-col justify-between min-h-[90px]">
                                    <span class="text-xs text-stone-400 font-medium">ผู้เล่นคนที่ 2</span>
                                    <div id="draft_p2_display" class="font-semibold text-brown-950 text-sm py-1 my-1 truncate">-</div>
                                    <div id="draft_p2_actions" class="hidden">
                                        <button type="button" onclick="removeDraftPlayer(2)" class="text-[10px] text-red-500 bg-red-50 hover:bg-red-100 border border-red-100 rounded px-1.5 py-0.5 transition font-medium">ลบออกจากวงล้อ</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Action Button inside results -->
                            <button type="button" id="btnConfirmDraft" onclick="confirmDraft()" class="hidden w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 rounded-xl text-sm transition shadow-sm items-center justify-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                ยืนยันเลือกคู่นี้เข้า ทีมที่ X
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Player Checklist & Quick Add -->
                    <div id="wheelRightColumn" class="hidden w-full md:w-[350px] p-4 md:p-6 flex-col h-[50vh] md:h-auto overflow-y-auto">
                        <div class="flex justify-between items-center mb-3 mt-8 md:mt-0">
                            <h4 class="font-semibold text-brown-900 text-base">รายชื่อผู้เล่น</h4>
                            <button type="button" onclick="document.getElementById('quickAddContainer').classList.toggle('hidden')" class="text-[10px] font-medium text-brown-700 bg-brown-50 hover:bg-brown-100 px-2 py-1.5 rounded-lg border border-brown-200 transition flex items-center gap-1 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                เพิ่มด่วน
                            </button>
                        </div>

                        <!-- Quick Add Player Form -->
                        <div id="quickAddContainer" class="hidden mb-3 bg-cream-50 border border-cream-200 rounded-xl p-3 shadow-inner">
                            <div class="text-[10px] font-semibold text-brown-800 mb-2">เพิ่มผู้เล่นด่วน (เพิ่มปุ๊บ สุ่มได้เลย)</div>
                            <div class="flex flex-col gap-2">
                                <input type="text" id="quickAddName" placeholder="ชื่อผู้เล่น..." class="w-full text-xs border border-cream-200 rounded-lg p-2 outline-none focus:border-brown-400 bg-white shadow-sm">
                                <div class="flex gap-1.5">
                                    <select id="quickAddTier" class="flex-1 text-xs border border-cream-200 rounded-lg p-2 outline-none focus:border-brown-400 bg-white text-stone-600 cursor-pointer shadow-sm">
                                        <option value="S">มือ S</option>
                                        <option value="A">มือ A</option>
                                        <option value="B">มือ B</option>
                                        <option value="C">มือ C</option>
                                        <option value="Beginner" selected>Beginner</option>
                                    </select>
                                    <button type="button" onclick="submitQuickAddPlayer()" class="bg-brown-800 hover:bg-brown-900 text-white font-medium px-3 rounded-lg text-xs shadow-sm transition whitespace-nowrap">+ เพิ่มด่วน</button>
                                </div>
                            </div>
                        </div>

                        <!-- View Filter Tabs -->
                        <div class="flex gap-1 mb-2 bg-cream-50 p-1 rounded-lg border border-cream-200">
                            <button type="button" onclick="applyViewFilter('all')" id="viewBtn_all" class="flex-1 text-[10px] py-1 rounded font-medium transition text-stone-500 hover:text-stone-700">ทั้งหมด</button>
                            <button type="button" onclick="applyViewFilter('Ready')" id="viewBtn_Ready" class="flex-1 bg-white shadow-sm text-[10px] py-1 rounded font-medium transition text-green-700 border border-cream-200">พร้อมเล่น</button>
                            <button type="button" onclick="applyViewFilter('Break')" id="viewBtn_Break" class="flex-1 text-[10px] py-1 rounded font-medium transition text-stone-500 hover:text-stone-700">พัก</button>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <div class="flex gap-1.5 mb-3">
                            <button type="button" onclick="setWheelPoolFilter('all')" class="flex-1 bg-white border border-cream-200 hover:bg-cream-50 text-[10px] py-1 rounded font-medium text-stone-600 transition">เลือกทั้งหมดที่แสดง</button>
                            <button type="button" onclick="setWheelPoolFilter('none')" class="flex-1 bg-white border border-cream-200 hover:bg-cream-50 text-[10px] py-1 rounded font-medium text-red-500 transition">นำออกทั้งหมดที่แสดง</button>
                        </div>

                        <!-- Player List Checkbox Container -->
                        <div class="flex-1 border border-cream-100 rounded-xl overflow-y-auto min-h-0 p-3 space-y-2 bg-white" id="wheelPlayersList">
                            <!-- Checkboxes populated by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let players = <?= json_encode($readyPlayers) ?>;
                let rememberedWheelSelection = null;
                let currentViewFilter = 'Ready';
                function getPlayerOptions() {
                    let html = '<option value="">-- เลือกผู้เล่น --</option>';
                    players.forEach(p => {
                        html += `<option value="${p.id}">${p.name} (${p.skill_tier})</option>`;
                    });
                    return html;
                }

                function generateTeamInputs() {
                    let count = parseInt(document.getElementById('teamCount').value);
                    let container = document.getElementById('teamsContainer');
                    container.innerHTML = '';

                    for (let i = 1; i <= count; i++) {
                        container.innerHTML += `
                            <div class="bg-cream-50 border border-cream-200 rounded-2xl p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <div class="font-medium text-brown-800">ทีมที่ ${i}</div>
                                    <button type="button" onclick="openWheelModalForTeam(${i})" class="text-xs bg-brown-800 hover:bg-brown-900 text-white font-medium py-1 px-3 rounded-full shadow-sm transition flex items-center gap-1 active:scale-95">
                                        🎡 สุ่มด้วยวงล้อ
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <select name="teams[${i}][p1]" required class="w-full bg-white border border-cream-200 rounded-lg p-2.5 text-sm outline-none focus:border-brown-400">
                                        ${getPlayerOptions()}
                                    </select>
                                    <select name="teams[${i}][p2]" required class="w-full bg-white border border-cream-200 rounded-lg p-2.5 text-sm outline-none focus:border-brown-400">
                                        ${getPlayerOptions()}
                                    </select>
                                </div>
                            </div>
                        `;
                    }
                }

                // Initial run
                generateTeamInputs();

                function autoBalanceTeams() {
                    let count = parseInt(document.getElementById('teamCount').value);
                    let requiredPlayers = count * 2;
                    let availPlayers = [...players]; // Clone array
                    
                    if (availPlayers.length < requiredPlayers) {
                        alert(`มีนักกีฬาที่ตั้งสถานะ "พร้อมลงเล่น" เพียง ${availPlayers.length} คน แต่ต้องการ ${requiredPlayers} คน กรุณาเพิ่มจำนวนคนที่พร้อม หรือลดจำนวนทีมลง`);
                        return;
                    }

                    // Shuffle array function
                    function shuffle(array) {
                        for (let i = array.length - 1; i > 0; i--) {
                            const j = Math.floor(Math.random() * (i + 1));
                            [array[i], array[j]] = [array[j], array[i]];
                        }
                        return array;
                    }

                    // Sort players by tier value roughly
                    const tierValues = { 'S': 5, 'A': 4, 'B': 3, 'C': 2, 'Beginner': 1 };
                    availPlayers = shuffle(availPlayers); // Shuffle first to avoid same pattern among same tiers
                    availPlayers.sort((a, b) => tierValues[b.skill_tier] - tierValues[a.skill_tier]);

                    // Snake draft to balance teams
                    let teamsData = Array.from({length: count}, () => []);
                    for (let i = 0; i < requiredPlayers; i++) {
                        // Determine which team gets the player
                        let round = Math.floor(i / count);
                        let teamIdx = round % 2 === 0 ? (i % count) : (count - 1 - (i % count));
                        teamsData[teamIdx].push(availPlayers[i]);
                    }

                    // Now fill the selects
                    for (let i = 1; i <= count; i++) {
                        let select1 = document.querySelector(`select[name="teams[${i}][p1]"]`);
                        let select2 = document.querySelector(`select[name="teams[${i}][p2]"]`);
                        
                        if (teamsData[i-1] && teamsData[i-1].length >= 2) {
                            select1.value = teamsData[i-1][0].id;
                            select2.value = teamsData[i-1][1].id;
                        }
                    }
                }

                // --- WHEEL SPINNER SCRIPT ---
                let targetTeamIndex = null;
                let wheelPlayers = [];
                let draftP1 = null;
                let draftP2 = null;
                let currentAngle = 0;
                let isSpinning = false;
                let animationId = null;
                let lastSegmentIndex = -1;
                let audioCtx = null;

                function initAudio() {
                    if (!audioCtx) {
                        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    }
                    if (audioCtx && audioCtx.state === 'suspended') {
                        audioCtx.resume();
                    }
                }

                function playTickSound() {
                    try {
                        initAudio();
                        if (!audioCtx) return;
                        let osc = audioCtx.createOscillator();
                        let gain = audioCtx.createGain();
                        
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(600, audioCtx.currentTime);
                        osc.frequency.exponentialRampToValueAtTime(150, audioCtx.currentTime + 0.05);
                        
                        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.05);
                        
                        osc.connect(gain);
                        gain.connect(audioCtx.destination);
                        osc.start();
                        osc.stop(audioCtx.currentTime + 0.05);
                    } catch (e) {
                        console.error('Audio error:', e);
                    }
                }

                function playTriumphSound() {
                    try {
                        initAudio();
                        if (!audioCtx) return;
                        const now = audioCtx.currentTime;
                        const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
                        notes.forEach((freq, idx) => {
                            let osc = audioCtx.createOscillator();
                            let gain = audioCtx.createGain();
                            
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(freq, now + idx * 0.1);
                            
                            gain.gain.setValueAtTime(0.1, now + idx * 0.1);
                            gain.gain.setValueAtTime(0.1, now + idx * 0.1 + 0.15);
                            gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.1 + 0.3);
                            
                            osc.connect(gain);
                            gain.connect(audioCtx.destination);
                            osc.start(now + idx * 0.1);
                            osc.stop(now + idx * 0.1 + 0.3);
                        });
                    } catch (e) {
                        console.error('Audio error:', e);
                    }
                }

                function openWheelModalForTeam(teamIndex) {
                    initAudio();
                    targetTeamIndex = teamIndex;
                    document.getElementById('wheelModalTitle').innerText = `สุ่มจับคู่ผู้เล่นสำหรับ ทีมที่ ${teamIndex}`;
                    
                    // Reset draft state
                    draftP1 = null;
                    draftP2 = null;
                    document.getElementById('draft_p1_display').innerText = '-';
                    document.getElementById('draft_p1_actions').classList.add('hidden');
                    document.getElementById('draft_p2_display').innerText = '-';
                    document.getElementById('draft_p2_actions').classList.add('hidden');
                    
                    document.getElementById('btnSpin').disabled = false;
                    document.getElementById('btnSpin').classList.remove('opacity-50', 'cursor-not-allowed');
                    updateBtnSpinText();
                    
                    document.getElementById('btnConfirmDraft').classList.add('hidden');
                    document.getElementById('btnConfirmDraft').classList.remove('flex');
                    
                    // Scan other teams' dropdown selects to exclude players
                    const excludeIds = new Set();
                    const count = parseInt(document.getElementById('teamCount').value);
                    for (let t = 1; t <= count; t++) {
                        if (t === teamIndex) continue;
                        
                        const select1 = document.querySelector(`select[name="teams[${t}][p1]"]`);
                        const select2 = document.querySelector(`select[name="teams[${t}][p2]"]`);
                        if (select1 && select1.value) excludeIds.add(parseInt(select1.value));
                        if (select2 && select2.value) excludeIds.add(parseInt(select2.value));
                    }
                    
                    // Build Checklist
                    const listContainer = document.getElementById('wheelPlayersList');
                    listContainer.innerHTML = '';
                    
                    const availablePlayers = players.filter(p => !excludeIds.has(p.id));
                    
                    if (availablePlayers.length === 0) {
                        listContainer.innerHTML = '<div class="text-xs text-stone-400 text-center py-4">ไม่มีผู้เล่นที่ว่างอยู่</div>';
                    } else {
                        availablePlayers.forEach(player => {
                            let isChecked = false;
                            if (rememberedWheelSelection === null) {
                                isChecked = player.status === 'Ready';
                            } else {
                                isChecked = rememberedWheelSelection.includes(String(player.id));
                            }
                            
                            const item = document.createElement('div');
                            item.className = 'wheel-player-item flex items-center justify-between p-2 rounded-lg bg-cream-50 hover:bg-cream-100/70 border border-cream-200 transition duration-150';
                            item.setAttribute('data-status', player.status);
                            if (currentViewFilter !== 'all' && player.status !== currentViewFilter) {
                                item.style.display = 'none';
                            }
                            
                            const colors = {
                                'S': 'bg-amber-100 text-amber-800 border-amber-200',
                                'A': 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                'B': 'bg-blue-100 text-blue-800 border-blue-200',
                                'C': 'bg-teal-100 text-teal-800 border-teal-200',
                                'Beginner': 'bg-emerald-100 text-emerald-800 border-emerald-200'
                            };
                            const badgeColor = colors[player.skill_tier] || 'bg-stone-100 text-stone-800 border-stone-200';
                            
                            item.innerHTML = `
                                <label class="flex items-center gap-2 cursor-pointer flex-1 py-1">
                                    <input type="checkbox" value="${player.id}" class="wheel-player-checkbox accent-brown-800 w-4 h-4" ${isChecked ? 'checked' : ''} onchange="syncWheelPoolFromCheckboxes()">
                                    <span class="text-xs font-medium text-stone-800">${player.name}</span>
                                </label>
                                <span class="text-[10px] px-2 py-0.5 rounded-full border ${badgeColor} font-semibold uppercase tracking-wider">${player.skill_tier}</span>
                            `;
                            listContainer.appendChild(item);
                        });
                    }
                    
                    // Sync pool and show modal
                    syncWheelPoolFromCheckboxes();
                    
                    const modal = document.getElementById('wheelModal');
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }

                function closeWheelModal() {
                    if (animationId) {
                        cancelAnimationFrame(animationId);
                        animationId = null;
                    }
                    isSpinning = false;
                    
                    const modal = document.getElementById('wheelModal');
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }

                function syncWheelPoolFromCheckboxes() {
                    const checkedCheckboxes = document.querySelectorAll('.wheel-player-checkbox:checked');
                    const checkedIds = Array.from(checkedCheckboxes).map(cb => String(cb.value));
                    
                    rememberedWheelSelection = checkedIds;
                    
                    wheelPlayers = players.filter(p => checkedIds.includes(String(p.id)));
                    
                    // Filter out currently drafted players in this modal session
                    if (draftP1) {
                        wheelPlayers = wheelPlayers.filter(p => String(p.id) !== String(draftP1.id));
                    }
                    if (draftP2) {
                        wheelPlayers = wheelPlayers.filter(p => String(p.id) !== String(draftP2.id));
                    }
                    
                    drawWheel();
                }

                function drawWheel() {
                    const canvas = document.getElementById('wheelCanvas');
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    const width = canvas.width;
                    const height = canvas.height;
                    const cx = width / 2;
                    const cy = height / 2;
                    const radius = Math.min(cx, cy) - 10;
                    
                    ctx.clearRect(0, 0, width, height);
                    
                    if (wheelPlayers.length === 0) {
                        ctx.beginPath();
                        ctx.arc(cx, cy, radius, 0, 2 * Math.PI);
                        ctx.fillStyle = '#EBE5D9';
                        ctx.fill();
                        ctx.lineWidth = 4;
                        ctx.strokeStyle = '#5C4A3D';
                        ctx.stroke();
                        
                        ctx.fillStyle = '#5C4A3D';
                        ctx.font = 'bold 16px Kanit, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('ไม่มีผู้เล่นบนวงล้อ', cx, cy);
                        return;
                    }
                    
                    const sliceAngle = (2 * Math.PI) / wheelPlayers.length;
                    const colors = {
                        'S': '#F59E0B',        // Amber
                        'A': '#6366F1',        // Indigo
                        'B': '#3B82F6',        // Blue
                        'C': '#14B8A6',        // Teal
                        'Beginner': '#10B981'  // Emerald
                    };
                    
                    wheelPlayers.forEach((player, i) => {
                        const start = currentAngle + i * sliceAngle;
                        const end = start + sliceAngle;
                        
                        ctx.beginPath();
                        ctx.moveTo(cx, cy);
                        ctx.arc(cx, cy, radius, start, end);
                        ctx.closePath();
                        
                        ctx.fillStyle = colors[player.skill_tier] || '#8C7A6B';
                        ctx.fill();
                        
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
                        ctx.stroke();
                        
                        ctx.save();
                        ctx.translate(cx, cy);
                        ctx.rotate(start + sliceAngle / 2);
                        ctx.fillStyle = '#FFFFFF';
                        ctx.font = 'bold 12px Kanit, sans-serif';
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'middle';
                        
                        let displayName = player.name;
                        if (displayName.length > 10) displayName = displayName.substring(0, 9) + '..';
                        
                        ctx.fillText(displayName, radius - 15, 0);
                        ctx.restore();
                    });
                    
                    ctx.beginPath();
                    ctx.arc(cx, cy, radius, 0, 2 * Math.PI);
                    ctx.lineWidth = 4;
                    ctx.strokeStyle = '#5C4A3D';
                    ctx.stroke();
                    
                    ctx.beginPath();
                    ctx.arc(cx, cy, 25, 0, 2 * Math.PI);
                    ctx.fillStyle = '#5C4A3D';
                    ctx.fill();
                    ctx.lineWidth = 3;
                    ctx.strokeStyle = '#FFFFFF';
                    ctx.stroke();
                }

                function spinWheel() {
                    if (isSpinning || wheelPlayers.length === 0) return;
                    
                    initAudio();
                    isSpinning = true;
                    let winnerIdx;
                    let validIndices = [];
                    
                    // Smart Balancing Logic for Player 2
                    if (draftP1 !== null) {
                        const getTierVal = (tier) => {
                            const t = { 'S': 5, 'A': 4, 'B': 3, 'C': 2, 'Beginner': 1 };
                            return t[tier] || 1;
                        };
                        const p1Val = getTierVal(draftP1.skill_tier);
                        
                        for (let i = 0; i < wheelPlayers.length; i++) {
                            const p2Val = getTierVal(wheelPlayers[i].skill_tier);
                            const sum = p1Val + p2Val;
                            // A balanced pair should sum between 5 and 7 (e.g. S+Beginner=6, A+C=6, B+B=6)
                            if (sum >= 5 && sum <= 7) {
                                validIndices.push(i);
                            }
                        }
                    }
                    
                    if (validIndices.length > 0) {
                        // Pick randomly from the balanced candidates
                        winnerIdx = validIndices[Math.floor(Math.random() * validIndices.length)];
                    } else {
                        // Fallback to pure random if it's Player 1 or no balanced players left
                        winnerIdx = Math.floor(Math.random() * wheelPlayers.length);
                    }
                    
                    const winner = wheelPlayers[winnerIdx];
                    
                    const sliceAngle = (2 * Math.PI) / wheelPlayers.length;
                    const minRotations = 5;
                    const maxRotations = 8;
                    const rotations = Math.floor(minRotations + Math.random() * (maxRotations - minRotations + 1));
                    
                    const randomOffset = (Math.random() * 0.7 + 0.15) * sliceAngle;
                    const targetWinnerAngle = -Math.PI / 2 - (winnerIdx * sliceAngle + randomOffset);
                    
                    const startAngle = currentAngle % (2 * Math.PI);
                    const destinationAngle = targetWinnerAngle - (rotations * 2 * Math.PI);
                    
                    const duration = 4500 + Math.random() * 1000;
                    const startTime = performance.now();
                    
                    lastSegmentIndex = -1;
                    
                    function animate(currentTime) {
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        
                        const ease = 1 - Math.pow(1 - progress, 3);
                        currentAngle = startAngle + (destinationAngle - startAngle) * ease;
                        
                        const sliceWidth = (2 * Math.PI) / wheelPlayers.length;
                        let normalizedAngle = (-Math.PI / 2 - currentAngle) % (2 * Math.PI);
                        if (normalizedAngle < 0) normalizedAngle += 2 * Math.PI;
                        let currentSegmentIndex = Math.floor(normalizedAngle / sliceWidth);
                        
                        if (currentSegmentIndex !== lastSegmentIndex && !isNaN(currentSegmentIndex)) {
                            playTickSound();
                            lastSegmentIndex = currentSegmentIndex;
                        }
                        
                        drawWheel();
                        
                        if (progress < 1) {
                            animationId = requestAnimationFrame(animate);
                        } else {
                            isSpinning = false;
                            handleWinnerDrawn(winner);
                        }
                    }
                    
                    animationId = requestAnimationFrame(animate);
                }

                function handleWinnerDrawn(winner) {
                    playTriumphSound();
                    
                    confetti({
                        particleCount: 80,
                        spread: 60,
                        origin: { y: 0.7 }
                    });
                    
                    if (draftP1 === null) {
                        draftP1 = winner;
                        document.getElementById('draft_p1_display').innerText = `${winner.name} (${winner.skill_tier})`;
                        document.getElementById('draft_p1_actions').classList.remove('hidden');
                        updateBtnSpinText();
                        syncWheelPoolFromCheckboxes();
                    } else if (draftP2 === null) {
                        draftP2 = winner;
                        document.getElementById('draft_p2_display').innerText = `${winner.name} (${winner.skill_tier})`;
                        document.getElementById('draft_p2_actions').classList.remove('hidden');
                        
                        document.getElementById('btnSpin').disabled = true;
                        document.getElementById('btnSpin').classList.add('opacity-50', 'cursor-not-allowed');
                        updateBtnSpinText();
                        
                        const confirmBtn = document.getElementById('btnConfirmDraft');
                        confirmBtn.innerText = `ยืนยันเลือกคู่นี้เข้า ทีมที่ ${targetTeamIndex}`;
                        confirmBtn.classList.remove('hidden');
                        confirmBtn.classList.add('flex');
                        
                        confetti({
                            particleCount: 150,
                            spread: 80,
                            origin: { y: 0.6 }
                        });
                    }
                }

                function removeDraftPlayer(slotNum) {
                    if (isSpinning) return;
                    
                    if (slotNum === 1) {
                        draftP1 = null;
                        document.getElementById('draft_p1_display').innerText = '-';
                        document.getElementById('draft_p1_actions').classList.add('hidden');
                    } else {
                        draftP2 = null;
                        document.getElementById('draft_p2_display').innerText = '-';
                        document.getElementById('draft_p2_actions').classList.add('hidden');
                    }
                    
                    document.getElementById('btnSpin').disabled = false;
                    document.getElementById('btnSpin').classList.remove('opacity-50', 'cursor-not-allowed');
                    updateBtnSpinText();
                    
                    document.getElementById('btnConfirmDraft').classList.add('hidden');
                    document.getElementById('btnConfirmDraft').classList.remove('flex');
                    
                    syncWheelPoolFromCheckboxes();
                }

                function updateBtnSpinText() {
                    const btn = document.getElementById('btnSpin');
                    if (draftP1 === null) {
                        btn.innerText = 'SPIN';
                    } else if (draftP2 === null) {
                        btn.innerText = 'SPIN 2';
                    } else {
                        btn.innerText = 'DONE';
                    }
                }

                function resetDraft() {
                    if (isSpinning) return;
                    
                    draftP1 = null;
                    draftP2 = null;
                    document.getElementById('draft_p1_display').innerText = '-';
                    document.getElementById('draft_p1_actions').classList.add('hidden');
                    document.getElementById('draft_p2_display').innerText = '-';
                    document.getElementById('draft_p2_actions').classList.add('hidden');
                    
                    document.getElementById('btnSpin').disabled = false;
                    document.getElementById('btnSpin').classList.remove('opacity-50', 'cursor-not-allowed');
                    updateBtnSpinText();
                    
                    document.getElementById('btnConfirmDraft').classList.add('hidden');
                    document.getElementById('btnConfirmDraft').classList.remove('flex');
                    
                    syncWheelPoolFromCheckboxes();
                }

                function confirmDraft() {
                    if (!draftP1 || !draftP2) return;
                    
                    const select1 = document.querySelector(`select[name="teams[${targetTeamIndex}][p1]"]`);
                    const select2 = document.querySelector(`select[name="teams[${targetTeamIndex}][p2]"]`);
                    
                    if (select1 && select2) {
                        select1.value = draftP1.id;
                        select2.value = draftP2.id;
                    }
                    
                    closeWheelModal();
                }

                function applyViewFilter(filter) {
                    currentViewFilter = filter;
                    
                    // Update tab styles
                    const tabs = ['all', 'Ready', 'Break'];
                    const mappedFilter = filter;
                    
                    tabs.forEach(t => {
                        const btn = document.getElementById('viewBtn_' + t);
                        if (!btn) return;
                        if (t === mappedFilter) {
                            btn.className = 'flex-1 bg-white shadow-sm text-[10px] py-1 rounded font-medium transition text-green-700 border border-cream-200';
                        } else {
                            btn.className = 'flex-1 text-[10px] py-1 rounded font-medium transition text-stone-500 hover:text-stone-700';
                        }
                    });
                    
                    // Filter items
                    const items = document.querySelectorAll('.wheel-player-item');
                    items.forEach(item => {
                        const status = item.getAttribute('data-status');
                        if (filter === 'all' || status === filter) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }

                function setWheelPoolFilter(type) {
                    const items = document.querySelectorAll('.wheel-player-item');
                    items.forEach(item => {
                        if (item.style.display !== 'none') {
                            const cb = item.querySelector('.wheel-player-checkbox');
                            if (cb) {
                                if (type === 'all') cb.checked = true;
                                else if (type === 'none') cb.checked = false;
                            }
                        }
                    });
                    syncWheelPoolFromCheckboxes();
                }

                async function submitQuickAddPlayer() {
                    const nameInput = document.getElementById('quickAddName');
                    const name = nameInput.value.trim();
                    const tier = document.getElementById('quickAddTier').value;
                    
                    if (!name) {
                        alert('กรุณากรอกชื่อผู้เล่น');
                        return;
                    }
                    
                    const btn = document.querySelector('#wheelModal button[onclick="submitQuickAddPlayer()"]');
                    const originalText = btn.innerText;
                    btn.disabled = true;
                    btn.innerText = '...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'quick_add_player');
                        formData.append('name', name);
                        formData.append('tier', tier);
                        
                        const response = await fetch('api_tournament.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            const newPlayer = data.player;
                            
                            // 1. Add to page global players list
                            players.push(newPlayer);
                            
                            // 2. Clear input
                            nameInput.value = '';
                            
                            // 3. Append option to all team select dropdowns on parent page
                            addNewPlayerOption(newPlayer);
                            
                            // 4. Reload checklist inside modal for this drafting session
                            openWheelModalForTeam(targetTeamIndex);
                            
                            // Visual chime
                            confetti({
                                particleCount: 30,
                                angle: 60,
                                spread: 55,
                                origin: { x: 0.8, y: 0.8 }
                            });
                        } else {
                            alert(data.message || 'เกิดข้อผิดพลาด');
                        }
                    } catch (e) {
                        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                        console.error(e);
                    } finally {
                        btn.disabled = false;
                        btn.innerText = originalText;
                    }
                }

                function addNewPlayerOption(player) {
                    const selects = document.querySelectorAll('#teamsContainer select');
                    selects.forEach(select => {
                        const option = document.createElement('option');
                        option.value = player.id;
                        option.textContent = `${player.name} (${player.skill_tier})`;
                        select.appendChild(option);
                    });
                }
            </script>
            
        <?php else: ?>
            <!-- Manage Active Tournament -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-cream-200 pb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-semibold text-brown-900 mb-1"><?= htmlspecialchars($activeTournament['name']) ?></h2>
                    <p class="text-stone-500 text-sm font-light">สถานะ: <?= $activeTournament['status'] == 'Ongoing' ? 'กำลังแข่งขัน' : 'ร่าง/รอเริ่ม' ?></p>
                </div>
                <div>
                    <form method="POST" action="api_tournament.php" onsubmit="return confirm('ยืนยันระบบจะล้างข้อมูลทัวร์นาเมนต์ปัจจุบันออกทั้งหมด?');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="tournament_id" value="<?= $activeTournament['id'] ?>">
                        <button type="submit" class="bg-white border border-red-200 hover:bg-red-50 text-red-500 font-medium py-2 px-4 rounded-full shadow-sm hover:shadow-md transition text-sm">
                            จบทัวร์นาเมนต์ / ยกเลิก
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Match List -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="text-xl font-medium text-brown-900 mb-4">ตารางการแข่งขัน</h3>
                    
                    <?php foreach($matches as $m): ?>
                        <div class="bg-white border <?= $m['status'] == 'Ongoing' ? 'border-orange-300 shadow-md' : ($m['status'] == 'Completed' ? 'border-green-200 bg-green-50/30' : 'border-cream-200 shadow-sm') ?> rounded-2xl p-5 flex flex-col md:flex-row justify-between items-center gap-4 transition">
                            <div class="flex-1 flex justify-center items-center gap-4 w-full md:w-auto">
                                <div class="text-right flex-1">
                                    <div class="font-medium text-brown-900"><?= htmlspecialchars($m['t1_name']) ?></div>
                                    <?php if($m['status'] == 'Completed'): ?>
                                        <div class="text-2xl font-bold <?= $m['t1_score'] > $m['t2_score'] ? 'text-brown-800' : 'text-stone-400' ?>"><?= $m['t1_score'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs font-bold text-stone-400 tracking-widest px-2">VS</div>
                                <div class="text-left flex-1">
                                    <div class="font-medium text-brown-900"><?= htmlspecialchars($m['t2_name']) ?></div>
                                    <?php if($m['status'] == 'Completed'): ?>
                                        <div class="text-2xl font-bold <?= $m['t2_score'] > $m['t1_score'] ? 'text-brown-800' : 'text-stone-400' ?>"><?= $m['t2_score'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="w-full md:w-48 text-center md:text-right border-t md:border-t-0 md:border-l border-cream-100 pt-4 md:pt-0 pl-0 md:pl-4">
                                <?php if($m['status'] == 'Pending'): ?>
                                    <!-- Deploy Match Form -->
                                    <form method="POST" action="api_tournament.php" class="flex flex-col gap-2">
                                        <input type="hidden" name="action" value="deploy">
                                        <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                        
                                        <select name="court_id" required class="text-xs w-full bg-white border border-cream-200 rounded p-2 outline-none focus:border-brown-400">
                                            <option value="">-- เลือกสนามว่าง --</option>
                                            <?php foreach($courts as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="w-full bg-brown-800 hover:bg-brown-900 text-white font-medium py-1.5 rounded text-xs shadow-sm transition">
                                            นำลงสนาม
                                        </button>
                                    </form>
                                <?php elseif($m['status'] == 'Ongoing'): ?>
                                    <div class="text-[11px] font-semibold text-orange-600 bg-orange-100 px-2 py-1 rounded inline-block mb-2">ที่ <?= htmlspecialchars($m['court_name']) ?></div>
                                    <button type="button" onclick="openTournamentScoreModal(<?= $m['id'] ?>, '<?= addslashes($m['t1_name']) ?>', '<?= addslashes($m['t2_name']) ?>')" class="w-full bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-1.5 rounded text-xs shadow-sm transition">
                                        ใส่คะแนน
                                    </button>
                                <?php elseif($m['status'] == 'Completed'): ?>
                                    <div class="text-sm font-medium text-green-600 flex items-center justify-center md:justify-end gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                        จบเกม
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Teams Info Sidebar -->
                <div class="lg:col-span-1">
                    <h3 class="text-xl font-medium text-brown-900 mb-4">ข้อมูลทีม</h3>
                    <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-cream-200 p-6 space-y-4">
                        <?php foreach($teams as $t): ?>
                            <div class="border-b border-cream-100 pb-3 last:border-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <div class="font-semibold text-brown-900 mb-1"><?= htmlspecialchars($t['team_name']) ?></div>
                                    <button type="button" onclick="openEditTeamModal(<?= $t['id'] ?>, '<?= addslashes($t['team_name']) ?>', <?= $t['p1'] ?>, <?= $t['p2'] ?>)" class="text-brown-500 hover:text-brown-700 bg-cream-50 hover:bg-cream-100 p-1.5 rounded-lg border border-cream-200 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                    </button>
                                </div>
                                <div class="text-sm text-stone-600 flex flex-col gap-0.5">
                                    <span>- <?= htmlspecialchars($t['p1_name']) ?> (<?= $t['p1_tier'] ?>)</span>
                                    <span>- <?= htmlspecialchars($t['p2_name']) ?> (<?= $t['p2_tier'] ?>)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

            <!-- Edit Team Modal -->
            <div id="editTeamModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
                <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-sm overflow-hidden">
                    <div class="p-5 border-b border-cream-100 flex justify-between items-center bg-cream-50">
                        <h3 class="text-xl font-semibold text-brown-900">เปลี่ยนตัวผู้เล่น</h3>
                        <button onclick="closeEditTeamModal()" class="text-brown-400 hover:text-brown-600 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <form method="POST" action="api_tournament.php" class="p-6">
                        <input type="hidden" name="action" value="edit_team">
                        <input type="hidden" id="edit_team_id" name="team_id" value="">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-brown-800 mb-2">เลือกผู้เล่นคนที่ 1:</label>
                            <select id="edit_p1" name="p1" required class="w-full bg-white border border-cream-200 rounded-xl px-4 py-3 text-stone-700 outline-none focus:border-brown-400">
                                <?php foreach($allPlayers as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['skill_tier'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-brown-800 mb-2">เลือกผู้เล่นคนที่ 2:</label>
                            <select id="edit_p2" name="p2" required class="w-full bg-white border border-cream-200 rounded-xl px-4 py-3 text-stone-700 outline-none focus:border-brown-400">
                                <?php foreach($allPlayers as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['skill_tier'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex gap-3">
                            <button type="button" onclick="closeEditTeamModal()" class="w-1/3 bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-3 rounded-xl shadow-sm transition text-sm">
                                ยกเลิก
                            </button>
                            <button type="submit" class="w-2/3 bg-brown-800 hover:bg-brown-900 text-white font-medium py-3 rounded-xl shadow-sm transition flex justify-center items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
                </div>
            </div>

            <!-- Tournament Score Modal -->
            <div id="tourneyScoreModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-50 items-center justify-center p-4">
                <div class="bg-white rounded-3xl shadow-xl border border-cream-200 w-full max-w-sm overflow-hidden">
                    <div class="p-5 border-b border-cream-100 flex justify-between items-center bg-cream-50">
                        <h3 class="text-xl font-semibold text-brown-900">บันทึกคะแนนทัวร์นาเมนต์</h3>
                        <button onclick="closeTournamentScoreModal()" class="text-brown-400 hover:text-brown-600 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <form method="POST" action="api_tournament.php" class="p-6">
                        <input type="hidden" name="action" value="submit_score">
                        <input type="hidden" id="ts_match_id" name="match_id" value="">

                        <div class="mb-4 text-center">
                            <div id="ts_t1_name" class="font-medium text-lg text-brown-900 truncate bg-cream-100 rounded-lg p-2"></div>
                            <div class="mt-3">
                                <input type="number" name="t1_score" required min="0" max="99" class="text-center w-24 text-3xl font-bold bg-white border border-brown-300 rounded-xl py-3 text-stone-800 outline-none focus:border-brown-500 focus:ring-2 focus:ring-brown-200">
                            </div>
                        </div>

                        <div class="my-4 border-t border-dashed border-cream-200 w-1/2 mx-auto"></div>

                        <div class="mb-8 text-center">
                            <div id="ts_t2_name" class="font-medium text-lg text-brown-900 truncate bg-cream-100 rounded-lg p-2"></div>
                            <div class="mt-3">
                                <input type="number" name="t2_score" required min="0" max="99" class="text-center w-24 text-3xl font-bold bg-white border border-brown-300 rounded-xl py-3 text-stone-800 outline-none focus:border-brown-500 focus:ring-2 focus:ring-brown-200">
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button type="button" onclick="closeTournamentScoreModal()" class="w-1/3 bg-white border border-brown-200 hover:bg-cream-100 text-brown-800 font-medium py-3 rounded-xl shadow-sm transition text-sm">
                                ยกเลิก
                            </button>
                            <button type="submit" class="w-2/3 bg-brown-800 hover:bg-brown-900 text-white font-medium py-3 rounded-xl shadow-sm transition">
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openTournamentScoreModal(id, t1, t2) {
                    document.getElementById('ts_match_id').value = id;
                    document.getElementById('ts_t1_name').innerText = t1;
                    document.getElementById('ts_t2_name').innerText = t2;
                    document.getElementById('tourneyScoreModal').classList.remove('hidden');
                    document.getElementById('tourneyScoreModal').classList.add('flex');
                }
                function closeTournamentScoreModal() {
                    document.getElementById('tourneyScoreModal').classList.add('hidden');
                    document.getElementById('tourneyScoreModal').classList.remove('flex');
                }
                function openEditTeamModal(teamId, teamName, p1, p2) {
                    document.getElementById('edit_team_id').value = teamId;
                    document.getElementById('edit_p1').value = p1;
                    document.getElementById('edit_p2').value = p2;
                    document.getElementById('editTeamModal').classList.remove('hidden');
                    document.getElementById('editTeamModal').classList.add('flex');
                }
                function closeEditTeamModal() {
                    document.getElementById('editTeamModal').classList.add('hidden');
                    document.getElementById('editTeamModal').classList.remove('flex');
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
