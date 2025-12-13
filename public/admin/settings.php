<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// توليد رمز CSRF
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// توليد رمز CSRF للخروج
if (empty($_SESSION['logout_csrf_token'])) {
    try {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['logout_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$logout_csrf_token = $_SESSION['logout_csrf_token'];

require_once '../../app/core/db.php';
require_once '../../app/core/Logger.php';
require_once '../../app/core/EvaluationCalculator.php';

$msg = '';
$error = '';

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: رمز CSRF غير صالح.";
    } else {
        // 1. تحديث اسم الشركة
        $company_name = trim($_POST['company_name']);
        if ($company_name) {
            $stmt = $pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = 'company_name'");
            $stmt->execute([$company_name]);
        }

        // 2. معالجة رفع الشعار
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['company_logo']['tmp_name'];
            $fileName = $_FILES['company_logo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'gif', 'png', 'jpeg'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = '../../storage/uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                
                // اسم موحد للشعار
                $newFileName = 'logo.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET `value` = ? WHERE `key` = 'logo_path'");
                    $stmt->execute([$newFileName]);
                } else {
                    $error = "فشل في نقل الملف المرفوع.";
                }
            } else {
                $error = "صيغة الملف غير مدعومة. يرجى رفع صورة (jpg, png, gif).";
            }
        }
        
        if (empty($error)) {
            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
            header('Location: settings.php?msg=saved');
            exit;
        }
    }
}

// معالجة حفظ طريقة احتساب التقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation_method'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "خطأ أمني: رمز CSRF غير صالح.";
    } else {
        $evaluation_method = $_POST['evaluation_method'] ?? 'manager_only';
        $calculator = new EvaluationCalculator($pdo);
        
        try {
            $old_method = $calculator->getEvaluationMethod();
            
            if ($calculator->setEvaluationMethod($evaluation_method)) {
                $logger = new Logger($pdo);
                $old_method_name = $calculator->getMethodName($old_method);
                $new_method_name = $calculator->getMethodName($evaluation_method);
                $logger->log('settings', "تم تغيير طريقة احتساب التقييمات من '{$old_method_name}' إلى '{$new_method_name}'");
                
                try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
                header('Location: settings.php?msg=evaluation_method_saved');
                exit;
            } else {
                $error = "فشل في حفظ طريقة الحساب.";
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

// القيم الحالية
$current_company_name = $system_settings['company_name'] ?? 'شركة البراق للنقل الجوي';
$current_logo = $system_settings['logo_path'] ?? 'logo.png';

// جلب طريقة احتساب التقييم الحالية
$calculator = new EvaluationCalculator($pdo);
$current_evaluation_method = $calculator->getEvaluationMethod();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إعدادات النظام</title>
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
    <h3><i class="fas fa-cogs"></i> إعدادات النظام</h3>
    <hr>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
        <div class="alert alert-success">تم حفظ الإعدادات بنجاح.</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'evaluation_method_saved'): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حفظ طريقة احتساب التقييمات بنجاح.</div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-sliders-h"></i> الهوية العامة
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">اسم الشركة / النظام</label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($current_company_name) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">شعار النظام (Logo)</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="border p-2 rounded bg-light">
                                    <img src="../../storage/uploads/<?= htmlspecialchars($current_logo) ?>?v=<?= time() ?>" alt="Logo" style="max-height: 60px;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="company_logo" class="form-control" accept="image/*">
                                    <small class="text-muted">يفضل صورة شفافة (PNG).</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="save_settings" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-calculator"></i> طريقة احتساب التقييمات
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-3">
                            <p class="text-muted small mb-3">
                                <i class="fas fa-info-circle"></i> 
                                يحدد هذا الإعداد كيفية حساب التقييم النهائي للموظف عند وجود تقييمين (من المدير والمشرف).
                            </p>
                            
                            <div class="form-check mb-3 p-3 border rounded <?= $current_evaluation_method == 'manager_only' ? 'bg-light border-primary' : '' ?>">
                                <input class="form-check-input" type="radio" name="evaluation_method" id="method_manager" 
                                       value="manager_only" <?= $current_evaluation_method == 'manager_only' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="method_manager">
                                    <strong>تقييم مدير الإدارة فقط</strong>
                                    <br><small class="text-muted">التقييم النهائي = تقييم المدير (الطريقة الافتراضية)</small>
                                </label>
                            </div>
                            
                            <div class="form-check p-3 border rounded <?= $current_evaluation_method == 'average' ? 'bg-light border-primary' : '' ?>">
                                <input class="form-check-input" type="radio" name="evaluation_method" id="method_average" 
                                       value="average" <?= $current_evaluation_method == 'average' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="method_average">
                                    <strong>متوسط تقييمي المدير والمشرف</strong>
                                    <br><small class="text-muted">التقييم النهائي = (تقييم المدير + تقييم المشرف) ÷ 2</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning small mb-3">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>ملاحظة:</strong> تغيير هذا الإعداد سيؤثر على جميع التقارير والإحصائيات الحالية والمستقبلية.
                        </div>
                        
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-muted small">
                                <i class="fas fa-check-circle text-success"></i> 
                                الطريقة الحالية: <strong><?= $calculator->getMethodName() ?></strong>
                            </div>
                            <button type="submit" name="save_evaluation_method" class="btn btn-success">
                                <i class="fas fa-save"></i> حفظ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-tools"></i> إعدادات متقدمة
                </div>
                <div class="card-body">
                    <a href="email_settings.php" class="btn btn-outline-dark w-100 d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-envelope-open-text text-primary ms-2"></i> إعدادات البريد (SMTP)</span>
                        <i class="fas fa-chevron-left small"></i>
                    </a>
                    
                    <a href="backups.php" class="btn btn-outline-dark w-100 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-database text-success ms-2"></i> النسخ الاحتياطي</span>
                        <i class="fas fa-chevron-left small"></i>
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-user-shield"></i> حسابي
                </div>
                <div class="card-body text-center">
                    <a href="../change_password.php" class="btn btn-outline-dark w-100">
                        <i class="fas fa-key"></i> تغيير كلمة المرور
                    </a>
                </div>
            </div>
            
             <div class="card">
                <div class="card-header bg-dark text-white">
                    <i class="fas fa-info-circle"></i> معلومات النظام
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="fas fa-code-branch text-muted ms-2"></i> <strong>الإصدار:</strong> 2.5</li>
                        <li class="mb-2"><i class="fab fa-php text-muted ms-2"></i> <strong>PHP:</strong> <?= phpversion() ?></li>
                        <li><i class="fas fa-database text-muted ms-2"></i> <strong>قاعدة البيانات:</strong> متصل</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>
<script src="../assets/js/search.js"></script>
</body>
</html>