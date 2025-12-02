<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
require_once '../app/core/db.php';

$user_id = $_SESSION['user_id'];

// جلب الإشعارات غير المقروءة
$unread_notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC
");
$unread_notifications->execute([$user_id]);
$unread_notifications = $unread_notifications->fetchAll();

echo json_encode([
    'count' => count($unread_notifications),
    'notifications' => $unread_notifications
]);
?>