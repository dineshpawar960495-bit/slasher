<?php
session_start();
include 'db.php';

// Redirect if there's no email in session (prevents direct URL access)
if (!isset($_SESSION['temp_email'])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = $_POST['otp'];
    $email = $_SESSION['temp_email'];

    // 1. Check if OTP matches for this email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND otp_code = ?");
    $stmt->bind_param("ss", $email, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // 2. Update user as verified and clear the OTP (security best practice)
        $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL WHERE id = ?");
        $update->bind_param("i", $user['id']);
        
        if ($update->execute()) {
            // 3. Set the official login session
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['temp_email']); // Clean up temp session
            
            $success = "Account verified! Redirecting to dashboard...";
            header("refresh:2;url=dashboard.php");
        }
    } else {
        $error = "Invalid OTP code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Slasher | Verify OTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="glass w-full max-w-[400px] p-8 rounded-[2.5rem] shadow-2xl text-center">
        <div class="w-16 h-16 bg-purple-500/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
            <svg class="text-purple-500 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-black text-white mb-2">Verify Identity</h1>
        <p class="text-slate-400 text-sm mb-8">We sent a 6-digit code to <br><span class="text-purple-400 font-semibold"><?php echo $_SESSION['temp_email']; ?></span></p>

        <?php if($error): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs py-3 rounded-xl mb-6"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs py-3 rounded-xl mb-6"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="verify.php" method="POST" class="space-y-6">
            <input type="text" name="otp" maxlength="6" placeholder="0 0 0 0 0 0" 
                class="w-full bg-slate-800/50 border border-slate-700 p-4 rounded-2xl text-white text-center text-2xl font-bold tracking-[0.5em] outline-none focus:ring-2 ring-purple-500 transition-all" required autofocus>
            
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-purple-500/20">
                Verify & Launch
            </button>
        </form>

        <p class="mt-8 text-slate-500 text-xs">
            Didn't get the code? <a href="login.php" class="text-slate-300 hover:underline">Try another email</a>
        </p>
    </div>

</body>
</html>