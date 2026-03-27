<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- REGISTRATION LOGIC ---
    if ($action == 'register') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $otp = rand(100000, 999999);

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, otp_code) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $pass, $otp);

        if ($stmt->execute()) {
            sendOTP($email, $otp);
            $_SESSION['temp_email'] = $email;
            echo "otp_verify";
        } else {
            echo "Email already exists.";
        }
    }

    // --- LOGIN LOGIC ---
    if ($action == 'login') {
        $email = $_POST['email'];
        $pass = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($pass, $user['password'])) {
                if ($user['is_verified'] == 0) {
                    echo "verify_needed";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    echo "success";
                }
            } else {
                echo "Invalid password.";
            }
        } else {
            echo "User not found.";
        }
    }
}

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use your SMTP provider
        $mail->SMTPAuth = true;
        $mail->Username = 'dineshpawar960495@gmail.com'; 
        $mail->Password = 'raxofafbhzykclce'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('noreply@slasher.com', 'Subscription Slasher');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Security Code';
        $mail->Body = "Your OTP for Subscription Slasher is: <b>$otp</b>";
        $mail->send();
    } catch (Exception $e) {}
}
?>