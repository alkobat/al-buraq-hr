<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$csrf_token = $_SESSION['csrf_token'];

// CSRF للخروج (استدعاء روتيني)
if (empty($_SESSION['logout_csrf_token'])) {
    try { $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php';

// إعداد مسار المجلد
$backupDir = '../../storage/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    // حماية المجلد من الوصول المباشر
    file_put_contents($backupDir . '.htaccess', 'Deny from all');
}

$msg = '';
$error = '';

// === العمليات ===

// 1. إنشاء نسخة احتياطية جديدة
if (isset($_POST['create_backup'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: رمز CSRF غير صالح.";
    } else {
        try {
            $tables = [];
            $result = $pdo->query('SHOW TABLES');
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sqlScript = "-- Al-Buraq HR System Backup\n";
            $sqlScript .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                // هيكل الجدول
                $row = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM);
                $sqlScript .= "\n\n" . $row[1] . ";\n\n";

                // البيانات
                $result = $pdo->query('SELECT * FROM ' . $table);
                $columnCount = $result->columnCount();

                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $sqlScript .= "INSERT INTO $table VALUES(";
                    for ($j = 0; $j < $columnCount; $j++) {
                        $row[$j] = addslashes($row[$j] ?? '');
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $sqlScript .= '"' . $row[$j] . '"';
                        } else {
                            $sqlScript .= '""';
                        }
                        if ($j < ($columnCount - 1)) {
                            $sqlScript .= ',';
                        }
                    }
                    $sqlScript .= ");\n";
                }
            }
            $sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";

            $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            file_put_contents($backupDir . $backupFile, $sqlScript);

            $logger = new Logger($pdo);
            $logger->log('create', "تم إنشاء نسخة احتياطية للنظام: $backupFile");

            $msg = "تم إنشاء النسخة الاحتياطية بنجاح: $backupFile";
        } catch (Exception $e) {
            $error = "حدث خطأ أثناء النسخ: " . $e->getMessage();
        }
    }
}

// 2. تحميل نسخة
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filePath = $backupDir . $file;
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $error = "الملف غير موجود.";
    }
}

// 3. حذف نسخة
if (isset($_POST['delete_backup'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني.";
    } else {
        $file = basename($_POST['filename']);
        $filePath = $backupDir . $file;
        if (file_exists($filePath)) {
            unlink($filePath);
            $logger = new Logger($pdo);
            $logger->log('delete', "تم حذف النسخة الاحتياطية: $file");
            $msg = "تم حذف الملف بنجاح.";
        }
    }
}

// 4. استعادة نسخة (Restore)
if (isset($_POST['restore_backup'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني.";
    } else {
        $file = basename($_POST['filename']);
        $filePath = $backupDir . $file;
        if (file_exists($filePath)) {
            // قراءة الملف وتنفيذ الاستعلامات
            // ملاحظة: هذا التنفيذ بسيط وقد لا يعمل مع ملفات ضخمة جداً (أكبر من ذاكرة PHP)
            // لكنه كافٍ للأنظمة المتوسطة.
            try {
                $sql = file_get_contents($filePath);
                $pdo->exec($sql);
                
                $logger = new Logger($pdo);
                $logger->log('update', "تم استعادة النظام من النسخة: $file");
                $msg = "تم استعادة قاعدة البيانات بنجاح من $file.";
            } catch (Exception $e) {
                $error = "فشل الاستعادة: " . $e->getMessage();
            }
        }
    }
}

// قراءة الملفات الموجودة
$backups = glob($backupDir . '*.sql');
rsort($backups); // الأحدث أولاً
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>النسخ الاحتياطي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php 
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-database"></i> إدارة النسخ الاحتياطي</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" name="create_backup" class="btn btn-success" onclick="return confirm('هل أنت متأكد من إنشاء نسخة جديدة؟')">
                <i class="fas fa-plus-circle"></i> إنشاء نسخة جديدة
            </button>
        </form>
    </div>
    <hr>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>تنبيه هام:</strong> استعادة النسخة الاحتياطية ستقوم بمسح جميع البيانات الحالية واستبدالها ببيانات النسخة المختارة. هذه العملية لا يمكن التراجع عنها.
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            قائمة النسخ المحفوظة
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>اسم الملف</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الحجم</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">لا توجد نسخ احتياطية محفوظة.</td></tr>
                        <?php else: ?>
                            <?php foreach ($backups as $backupPath): 
                                $filename = basename($backupPath);
                                $filesize = round(filesize($backupPath) / 1024, 2) . ' KB';
                                $filetime = date('Y-m-d H:i:s', filemtime($backupPath));
                            ?>
                            <tr>
                                <td dir="ltr" class="text-end fw-bold"><?= $filename ?></td>
                                <td><?= $filetime ?></td>
                                <td><?= $filesize ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?download=<?= $filename ?>" class="btn btn-sm btn-primary" title="تحميل"><i class="fas fa-download"></i></a>
                                        
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="filename" value="<?= $filename ?>">
                                            <button type="submit" name="restore_backup" class="btn btn-sm btn-warning" onclick="return confirm('تحذير خطير: سيتم حذف البيانات الحالية واستبدالها بهذه النسخة. هل أنت متأكد تماماً؟')" title="استعادة">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="filename" value="<?= $filename ?>">
                                            <button type="submit" name="delete_backup" class="btn btn-sm btn-danger" onclick="return confirm('حذف الملف نهائياً؟')" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="internet-status"><span class="badge bg-success">متصل</span></div>
<script src="../assets/js/search.js"></script>
</body>
</html>