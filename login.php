<?php
session_start();
require 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_auth'] = true;
        $_SESSION['admin_id'] = $user['id'];
        header("Location: admin.php");
        exit;
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Badminton System</title>
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
<body class="bg-cream-50 min-h-screen flex items-center justify-center p-4 text-stone-800">

    <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] p-8 md:p-10 w-full max-w-sm border border-cream-200">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-semibold text-brown-900 mb-1">Admin Login</h2>
            <p class="text-brown-500 text-sm font-light">ระบบจัดการสนามแบดมินตัน</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-100 text-red-600 p-3 rounded-xl mb-6 text-sm text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-5">
                <label class="block text-brown-600 text-sm font-medium mb-1.5 ml-1">ชื่อผู้ใช้</label>
                <input type="text" name="username" required class="w-full bg-cream-50 border border-cream-200 rounded-xl px-4 py-3 text-stone-800 focus:outline-none focus:border-brown-400 focus:ring-1 focus:ring-brown-400 transition">
            </div>
            
            <div class="mb-8">
                <label class="block text-brown-600 text-sm font-medium mb-1.5 ml-1">รหัสผ่าน</label>
                <input type="password" name="password" required class="w-full bg-cream-50 border border-cream-200 rounded-xl px-4 py-3 text-stone-800 focus:outline-none focus:border-brown-400 focus:ring-1 focus:ring-brown-400 transition">
            </div>
            
            <button type="submit" class="w-full bg-brown-800 hover:bg-brown-900 text-white font-medium py-3 px-4 rounded-xl shadow-sm hover:shadow-md transition duration-200">
                เข้าสู่ระบบ
            </button>
        </form>
        
        <div class="mt-8 text-center border-t border-cream-100 pt-6">
            <a href="normal.php" class="text-brown-400 hover:text-brown-600 text-sm transition font-light">← กลับไปหน้าตารางคะแนน</a>
        </div>
    </div>

</body>
</html>
