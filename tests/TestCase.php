<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createTestDatabase();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    protected function createTestDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                supervisor_id INTEGER,
                manager_id INTEGER,
                role TEXT DEFAULT 'employee'
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS evaluation_cycles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                year INTEGER NOT NULL,
                status TEXT DEFAULT 'active',
                start_date TEXT,
                end_date TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_evaluations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                cycle_id INTEGER NOT NULL,
                evaluator_id INTEGER,
                evaluator_role TEXT CHECK(evaluator_role IN ('manager', 'supervisor')),
                total_score REAL,
                status TEXT DEFAULT 'draft',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, cycle_id, evaluator_role)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                `key` TEXT UNIQUE NOT NULL,
                value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                cycle_id INTEGER,
                to_email TEXT,
                subject TEXT,
                body TEXT,
                email_type TEXT,
                status TEXT,
                error_message TEXT,
                metadata TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT UNIQUE NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_evaluation_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                cycle_id INTEGER NOT NULL,
                unique_token TEXT NOT NULL,
                expires_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(employee_id, cycle_id)
            )
        ");

        return $pdo;
    }

    protected function seedTestData(): void
    {
        // Insert default settings
        $settings = [
            ['auto_send_eval', '1'],
            ['evaluation_method', 'average_complete'],
            ['evaluation_email_manager_only_enabled', '1'],
            ['evaluation_email_available_score_mode', 'any'],
            ['evaluation_email_average_complete_mode', 'waiting_supervisor_plus_final'],
            ['smtp_host', 'smtp.example.com'],
            ['smtp_port', '587'],
            ['smtp_user', 'test@example.com'],
            ['smtp_pass', 'password'],
            ['smtp_secure', 'tls'],
            ['smtp_from_email', 'noreply@example.com'],
            ['smtp_from_name', 'HR System'],
        ];

        foreach ($settings as $setting) {
            $this->pdo->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?)")
                ->execute($setting);
        }

        // Insert test users
        $users = [
            [1, 'أحمد محمد', 'ahmed@example.com', null, null, 'manager'],
            [2, 'فاطمة علي', 'fatima@example.com', null, 1, 'supervisor'],
            [3, 'محمد خالد', 'mohammed@example.com', 2, 1, 'employee'],
            [4, 'سارة أحمد', 'sarah@example.com', null, 1, 'employee'], // No supervisor
            [5, 'عمر حسن', null, 2, 1, 'employee'], // No email
        ];

        foreach ($users as $user) {
            $this->pdo->prepare("INSERT INTO users (id, name, email, supervisor_id, manager_id, role) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute($user);
        }

        // Insert test cycle
        $this->pdo->prepare("INSERT INTO evaluation_cycles (id, year, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)")
            ->execute([1, 2024, 'active', '2024-01-01', '2024-12-31']);

        // Insert email template
        $this->pdo->prepare("INSERT INTO email_templates (type, subject, body) VALUES (?, ?, ?)")
            ->execute(['evaluation_complete', 'تم استكمال التقييم', '<p>تم استكمال تقييمك</p>']);
    }

    protected function createEvaluation(int $employeeId, int $cycleId, string $evaluatorRole, ?float $score = null): void
    {
        $evaluatorId = $evaluatorRole === 'manager' ? 1 : 2;
        $this->pdo->prepare("
            INSERT INTO employee_evaluations (employee_id, cycle_id, evaluator_id, evaluator_role, total_score, status)
            VALUES (?, ?, ?, ?, ?, 'submitted')
        ")->execute([$employeeId, $cycleId, $evaluatorId, $evaluatorRole, $score]);
    }

    protected function setSetting(string $key, string $value): void
    {
        $this->pdo->prepare("UPDATE system_settings SET value = ? WHERE `key` = ?")
            ->execute([$value, $key]);
    }

    protected function getEmailLogs(): array
    {
        return $this->pdo->query("SELECT * FROM email_logs")->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getEmailLogsByType(string $type): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM email_logs WHERE email_type = ?");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getSuccessfulEmailCount(int $employeeId, int $cycleId, string $emailType): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM email_logs 
            WHERE employee_id = ? AND cycle_id = ? AND email_type = ? AND status = 'success'
        ");
        $stmt->execute([$employeeId, $cycleId, $emailType]);
        return (int)$stmt->fetchColumn();
    }
}
