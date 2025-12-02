<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once '../app/core/db.php';

if ($_POST) {
    $new_pass = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($new_pass !== $confirm) {
        $error = "كلمتا المرور غير متطابقتين.";
    } elseif (strlen($new_pass) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?")
            ->execute([$hashed, $_SESSION['user_id']]);
        $_SESSION['force_change'] = 0;
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تغيير كلمة المرور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">تغيير كلمة المرور</div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label>تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">تحديث كلمة المرور</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>