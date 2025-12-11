<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// === CSRF: 1. إنشاء الرمز السري للجلسة ===
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
// ==========================================

require_once '../../app/core/db.php';

$cycles = $pdo->query("SELECT * FROM evaluation_cycles WHERE status = 'active'")->fetchAll();
$selected_cycle = $_GET['cycle'] ?? ($cycles ? $cycles[0]['id'] : null);
$tab = $_GET['tab'] ?? 'numeric'; // numeric أو text
$error = ''; // لتخزين أخطاء CSRF والتحقق

// === منطق التحقق من CSRF الموحد ===
function check_csrf($csrf_token) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        return "خطأ أمني: طلب غير صالح أو منتهي الصلاحية (CSRF).";
    }
    // تجديد رمز CSRF لمنع هجمات إعادة التشغيل
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) {}
    return null;
}
// =================================

// --- معالجة الحقول الرقمية ---
if ($_POST && isset($_POST['add_numeric_field']) && $selected_cycle) {
    $error = check_csrf($csrf_token);
    if (!$error) {
        $title_ar = trim($_POST['title_ar']);
        $max_score = (int)$_POST['max_score'];
        if ($title_ar && $max_score > 0) {
            $pdo->prepare("INSERT INTO evaluation_fields (cycle_id, title_ar, max_score) VALUES (?, ?, ?)")
                ->execute([$selected_cycle, $title_ar, $max_score]);
            header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_added");
            exit;
        }
    }
}

if (isset($_GET['delete_numeric'])) {
    $pdo->prepare("DELETE FROM evaluation_fields WHERE id = ?")->execute([$_GET['delete_numeric']]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_deleted");
    exit;
}

if ($_POST && isset($_POST['edit_numeric_field'])) {
    $error = check_csrf($csrf_token);
    if (!$error) {
        $id = $_POST['field_id'];
        $title_ar = trim($_POST['title_ar']);
        $max_score = (int)$_POST['max_score'];
        $pdo->prepare("UPDATE evaluation_fields SET title_ar = ?, max_score = ? WHERE id = ?")
            ->execute([$title_ar, $max_score, $id]);
        header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=numeric&msg=numeric_edited");
        exit;
    }
}

// --- معالجة الحقول النصية ---
if ($_POST && isset($_POST['add_text_field']) && $selected_cycle) {
    $error = check_csrf($csrf_token);
    if (!$error) {
        $title_ar = trim($_POST['text_title_ar']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        if ($title_ar) {
            $pdo->prepare("INSERT INTO evaluation_custom_text_fields (cycle_id, title_ar, is_required) VALUES (?, ?, ?)")
                ->execute([$selected_cycle, $title_ar, $is_required]);
            header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_added");
            exit;
        }
    }
}

if (isset($_GET['delete_text'])) {
    $pdo->prepare("DELETE FROM evaluation_custom_text_fields WHERE id = ?")->execute([$_GET['delete_text']]);
    header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_deleted");
    exit;
}

if ($_POST && isset($_POST['edit_text_field'])) {
    $error = check_csrf($csrf_token);
    if (!$error) {
        $id = $_POST['field_id'];
        $title_ar = trim($_POST['text_title_ar']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $pdo->prepare("UPDATE evaluation_custom_text_fields SET title_ar = ?, is_required = ? WHERE id = ?")
            ->execute([$title_ar, $is_required, $id]);
        header("Location: evaluation-fields.php?cycle=$selected_cycle&tab=text&msg=text_edited");
        exit;
    }
}

// جلب البيانات
$numeric_fields = $text_fields = [];
if ($selected_cycle) {
    $nf_stmt = $pdo->prepare("SELECT * FROM evaluation_fields WHERE cycle_id = ? ORDER BY order_index");
    $nf_stmt->execute([$selected_cycle]);
    $numeric_fields = $nf_stmt->fetchAll();
    
    $tf_stmt = $pdo->prepare("SELECT * FROM evaluation_custom_text_fields WHERE cycle_id = ? ORDER BY order_index");
    $tf_stmt->execute([$selected_cycle]);
    $text_fields = $tf_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>مجالات التقييم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body class="admin-dashboard">

<?php 
// استدعاء شريط التنقل الموحد
$current_page = basename(__FILE__);
require_once '_sidebar_nav.php'; 
?>

<main class="admin-main-content">
    <h3><i class="fas fa-list"></i> مجالات التقييم</h3>
    <hr>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($cycles)): ?>
        <div class="alert alert-warning">لا توجد دورات تقييم نشطة. يرجى إنشاء دورة أولاً.</div>
    <?php else: ?>
        <form method="GET" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <select name="cycle" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($cycles as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_cycle ? 'selected' : '' ?>>
                            <?= $c['year'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'numeric' ? 'active' : '' ?>" 
                   href="?cycle=<?= $selected_cycle ?>&tab=numeric">
                    <i class="fas fa-hashtag"></i> الحقول الرقمية
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'text' ? 'active' : '' ?>" 
                   href="?cycle=<?= $selected_cycle ?>&tab=text">
                    <i class="fas fa-font"></i> الحقول النصية
                </a>
            </li>
        </ul>

        <?php if (isset($_GET['msg'])): ?>
            <?php
            $msg_map = [
                'numeric_added' => 'تمت إضافة المجال الرقمي.',
                'numeric_edited' => 'تم تحديث المجال الرقمي.',
                'numeric_deleted' => 'تم حذف المجال الرقمي.',
                'text_added' => 'تمت إضافة الحقل النصي.',
                'text_edited' => 'تم تحديث الحقل النصي.',
                'text_deleted' => 'تم حذف الحقل النصي.'
            ];
            $msg_key = $_GET['msg'];
            if (isset($msg_map[$msg_key])): ?>
                <div class="alert alert-success"><?= $msg_map[$msg_key] ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'numeric'): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-plus"></i> إضافة مجال تقييم رقمي
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label>عنوان المجال (بالعربية) <span class="text-danger">*</span></label>
                                <input type="text" name="title_ar" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>الدرجة القصوى <span class="text-danger">*</span></label>
                                <input type="number" name="max_score" class="form-control" min="1" max="100" required>
                            </div>
                        </div>
                        <button type="submit" name="add_numeric_field" class="btn btn-success">إضافة المجال</button>
                    </form>
                </div>
            </div>

            <?php if ($numeric_fields): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> المجالات الرقمية الحالية (دورة <?= $cycles[array_search($selected_cycle, array_column($cycles, 'id'))]['year'] ?>)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead><tr><th>المجال</th><th>الدرجة القصوى</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($numeric_fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['title_ar']) ?></td>
                                    <td><?= $f['max_score'] ?></td>
                                    <td>
                                        <a href="javascript:void(0);" 
                                            class="btn btn-sm btn-warning me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editNumericModal"
                                            data-id="<?= $f['id'] ?>"
                                            data-title="<?= htmlspecialchars($f['title_ar']) ?>"
                                            data-score="<?= $f['max_score'] ?>">
                                            تعديل
                                        </a>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=numeric&delete_numeric=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف المجال؟')">حذف</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-success">
                                    <td><strong>المجموع</strong></td>
                                    <td><strong><?= array_sum(array_column($numeric_fields, 'max_score')) ?>/100</strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="modal fade" id="editNumericModal" tabindex="-1" aria-labelledby="editNumericModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="field_id" id="edit-numeric-id">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editNumericModalLabel">تعديل مجال رقمي</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label>عنوان المجال (بالعربية)</label>
                                    <input type="text" name="title_ar" id="edit-numeric-title" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>الدرجة القصوى</label>
                                    <input type="number" name="max_score" id="edit-numeric-score" class="form-control" min="1" max="100" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" name="edit_numeric_field" class="btn btn-warning">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($tab === 'text'): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-plus"></i> إضافة حقل نصي جديد
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="mb-3">
                            <label>عنوان الحقل (بالعربية) <span class="text-danger">*</span></label>
                            <input type="text" name="text_title_ar" class="form-control" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_required" class="form-check-input" id="is_required">
                            <label class="form-check-label" for="is_required">هل الحقل إلزامي؟</label>
                        </div>
                        <button type="submit" name="add_text_field" class="btn btn-success">إضافة الحقل</button>
                    </form>
                </div>
            </div>

            <?php if ($text_fields): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> الحقول النصية الحالية (دورة <?= $cycles[array_search($selected_cycle, array_column($cycles, 'id'))]['year'] ?>)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead><tr><th>العنوان</th><th>إلزامي</th><th>الإجراءات</th></tr></thead>
                            <tbody>
                                <?php foreach ($text_fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['title_ar']) ?></td>
                                    <td>
                                        <?php if ($f['is_required']): ?>
                                            <span class="badge bg-success">نعم</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لا</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0);" 
                                            class="btn btn-sm btn-warning me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTextModal"
                                            data-id="<?= $f['id'] ?>"
                                            data-title="<?= htmlspecialchars($f['title_ar']) ?>"
                                            data-required="<?= $f['is_required'] ?>">
                                            تعديل
                                        </a>
                                        <a href="?cycle=<?= $selected_cycle ?>&tab=text&delete_text=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف الحقل؟')">حذف</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="modal fade" id="editTextModal" tabindex="-1" aria-labelledby="editTextModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="field_id" id="edit-text-id">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editTextModalLabel">تعديل حقل نصي</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label>عنوان الحقل (بالعربية)</label>
                                    <input type="text" name="text_title_ar" id="edit-text-title" class="form-control" required>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_required" class="form-check-input" id="edit-text-required">
                                    <label class="form-check-label" for="edit-text-required">هل الحقل إلزامي؟</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" name="edit_text_field" class="btn btn-warning">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
    <?php endif; ?>
</main>

<div id="internet-status">
    <span class="badge bg-success">متصل</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editNumericModal = document.getElementById('editNumericModal');
    if (editNumericModal) {
        editNumericModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const score = button.getAttribute('data-score');
            
            const modalId = editNumericModal.querySelector('#edit-numeric-id');
            const modalTitle = editNumericModal.querySelector('#edit-numeric-title');
            const modalScore = editNumericModal.querySelector('#edit-numeric-score');

            modalId.value = id;
            modalTitle.value = title;
            modalScore.value = score;
        });
    }


    const editTextModal = document.getElementById('editTextModal');
    if (editTextModal) {
        editTextModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');
            const required = button.getAttribute('data-required'); 
            
            const modalId = editTextModal.querySelector('#edit-text-id');
            const modalTitle = editTextModal.querySelector('#edit-text-title');
            const modalRequired = editTextModal.querySelector('#edit-text-required');

            modalId.value = id;
            modalTitle.value = title;
            modalRequired.checked = required == '1'; 
        });
    }
});
</script>

</body>
</html>