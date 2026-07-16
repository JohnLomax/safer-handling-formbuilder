<?php
declare(strict_types=1);

require_once __DIR__ . '/database_bridge.php';

/**
 * Persist feedback submissions in the shared SQLite database.
 */

function feedbackLoggerPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = appDatabasePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    feedbackLoggerEnsureSchema($pdo);

    return $pdo;
}

function feedbackLoggerEnsureSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS feedback_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            issue_faced TEXT NOT NULL,
            description TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_submissions_created_at ON feedback_submissions(created_at)');

    feedbackLoggerEnsureColumn($pdo, 'feedback_submissions', 'resolved_at', 'TEXT');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_submissions_resolved_at ON feedback_submissions(resolved_at)');

    $ready = true;
}

function feedbackLoggerEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll() : [];

    foreach ($columns as $info) {
        if (($info['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
}

function feedbackLoggerNow(): string
{
    return gmdate('Y-m-d H:i:s');
}

function feedbackLoggerCreate(string $issueFaced, string $description): int
{
    $pdo = feedbackLoggerPdo();
    $stmt = $pdo->prepare(
        'INSERT INTO feedback_submissions (issue_faced, description, created_at)
         VALUES (:issue_faced, :description, :created_at)'
    );
    $stmt->execute([
        ':issue_faced' => $issueFaced,
        ':description' => $description,
        ':created_at' => feedbackLoggerNow(),
    ]);

    return (int)$pdo->lastInsertId();
}
