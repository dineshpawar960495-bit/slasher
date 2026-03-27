<?php
session_start();
require 'db.php';

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $sub_id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM subscriptions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sub_id, $user_id);
    $stmt->execute();
}
header("Location: dashboard.php");
exit();