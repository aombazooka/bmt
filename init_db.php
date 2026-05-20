<?php
// init_db.php
$host = '127.0.0.1';
$user = 'admin';
$pass = 'admin';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB
    $pdo->exec("CREATE DATABASE IF NOT EXISTS badminton_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE badminton_db");

    // Drop tables if we want a fresh start
    $pdo->exec("DROP TABLE IF EXISTS matches");
    $pdo->exec("DROP TABLE IF EXISTS queue");
    $pdo->exec("DROP TABLE IF EXISTS tournament_matches");
    $pdo->exec("DROP TABLE IF EXISTS tournament_teams");
    $pdo->exec("DROP TABLE IF EXISTS tournaments");
    $pdo->exec("DROP TABLE IF EXISTS courts");
    $pdo->exec("DROP TABLE IF EXISTS players");
    $pdo->exec("DROP TABLE IF EXISTS events");
    $pdo->exec("DROP TABLE IF EXISTS users");

    // 1. users
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. events
    $pdo->exec("CREATE TABLE events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('active', 'closed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. players
    $pdo->exec("CREATE TABLE players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        skill_tier ENUM('S', 'A', 'B', 'C', 'Beginner') NOT NULL,
        status ENUM('Ready', 'Playing', 'Break') DEFAULT 'Ready',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. courts
    $pdo->exec("CREATE TABLE courts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        status ENUM('Available', 'Occupied') DEFAULT 'Available'
    )");

    // 5. matches
    $pdo->exec("CREATE TABLE matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT,
        court_id INT,
        type ENUM('Singles', 'Doubles') NOT NULL,
        t1p1 INT NOT NULL,
        t1p2 INT DEFAULT NULL,
        t2p1 INT NOT NULL,
        t2p2 INT DEFAULT NULL,
        team1_score INT DEFAULT 0,
        team2_score INT DEFAULT 0,
        status ENUM('Ongoing', 'Completed') DEFAULT 'Ongoing',
        start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_time DATETIME DEFAULT NULL,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (court_id) REFERENCES courts(id),
        FOREIGN KEY (t1p1) REFERENCES players(id),
        FOREIGN KEY (t1p2) REFERENCES players(id),
        FOREIGN KEY (t2p1) REFERENCES players(id),
        FOREIGN KEY (t2p2) REFERENCES players(id)
    )");

    // 6. queue
    $pdo->exec("CREATE TABLE queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        p1 INT NOT NULL,
        p2 INT DEFAULT NULL,
        p3 INT DEFAULT NULL,
        p4 INT DEFAULT NULL,
        type ENUM('Singles', 'Doubles') NOT NULL,
        status ENUM('Waiting', 'Matched', 'Cancelled') DEFAULT 'Waiting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (p1) REFERENCES players(id),
        FOREIGN KEY (p2) REFERENCES players(id),
        FOREIGN KEY (p3) REFERENCES players(id),
        FOREIGN KEY (p4) REFERENCES players(id)
    )");

    // 7. tournaments
    $pdo->exec("CREATE TABLE tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('Draft', 'Ongoing', 'Completed') DEFAULT 'Draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. tournament_teams
    $pdo->exec("CREATE TABLE tournament_teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        team_name VARCHAR(100) NOT NULL,
        p1 INT NOT NULL,
        p2 INT NOT NULL,
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (p1) REFERENCES players(id),
        FOREIGN KEY (p2) REFERENCES players(id)
    )");

    // 9. tournament_matches
    $pdo->exec("CREATE TABLE tournament_matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        team1_id INT NOT NULL,
        team2_id INT NOT NULL,
        court_id INT DEFAULT NULL,
        t1_score INT DEFAULT 0,
        t2_score INT DEFAULT 0,
        status ENUM('Pending', 'Ongoing', 'Completed') DEFAULT 'Pending',
        start_time DATETIME DEFAULT NULL,
        end_time DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (team1_id) REFERENCES tournament_teams(id) ON DELETE CASCADE,
        FOREIGN KEY (team2_id) REFERENCES tournament_teams(id) ON DELETE CASCADE,
        FOREIGN KEY (court_id) REFERENCES courts(id)
    )");

    // Seed Admin (password: 1234)
    $hash = password_hash('1234', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password) VALUES ('admin', '$hash')");

    // Seed Event
    $pdo->exec("INSERT INTO events (name) VALUES ('รอบตีแบดมินตันวันนี้')");

    // Seed Courts (5 สนาม)
    $pdo->exec("INSERT INTO courts (name) VALUES ('สนาม 1'), ('สนาม 2'), ('สนาม 3'), ('สนาม 4'), ('สนาม 5')");

    // Seed Players
    $players = [
        ['name' => 'พี่เอก', 'tier' => 'S'],
        ['name' => 'น้องปอนด์', 'tier' => 'A'],
        ['name' => 'พี่หมู', 'tier' => 'A'],
        ['name' => 'น้องแก้ว', 'tier' => 'B'],
        ['name' => 'เต้', 'tier' => 'B'],
        ['name' => 'โบ๊ท', 'tier' => 'B'],
        ['name' => 'มายด์', 'tier' => 'C'],
        ['name' => 'โจ', 'tier' => 'C'],
        ['name' => 'โอ๊ต', 'tier' => 'C'],
        ['name' => 'เจน', 'tier' => 'Beginner'],
        ['name' => 'ใหม่', 'tier' => 'Beginner'],
        ['name' => 'ปู', 'tier' => 'Beginner']
    ];

    $stmt = $pdo->prepare("INSERT INTO players (name, skill_tier) VALUES (:name, :tier)");
    foreach ($players as $p) {
        $stmt->execute([':name' => $p['name'], ':tier' => $p['tier']]);
    }

    echo "Database structure and seeder created successfully!";

} catch (\PDOException $e) {
    die("DB Init Error: " . $e->getMessage());
}
?>