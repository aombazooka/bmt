<?php
require 'db.php';

try {
    $pdo->exec("ALTER TABLE players ADD COLUMN total_matches INT DEFAULT 0");
    $pdo->exec("ALTER TABLE players ADD COLUMN total_wins INT DEFAULT 0");
    $pdo->exec("ALTER TABLE players ADD COLUMN total_losses INT DEFAULT 0");
    $pdo->exec("ALTER TABLE players ADD COLUMN total_points INT DEFAULT 0");
    echo "Migration successful\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
?>
