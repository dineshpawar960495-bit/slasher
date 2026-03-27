<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- 1. Fetch User & Wallet ---
$user_stmt = $conn->prepare("SELECT full_name, wallet_balance FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$firstName = explode(' ', trim($user_data['full_name']))[0];
$wallet = $user_data['wallet_balance'];

// --- 2. Handle Add Subscription ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_sub'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $amount = (float)$_POST['amount'];
    $cat = $_POST['category'];
    $cycle = $_POST['cycle'];
    $last_use = $_POST['last_use'] ?: date('Y-m-d'); // User input for demo realism

    $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, service_name, amount, category, billing_cycle, last_accessed) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $name, $amount, $cat, $cycle, $last_use);
    $stmt->execute();
    header("Location: dashboard.php");
    exit();
}

// --- 3. Handle "Slash to Wallet" (The Saving Feature) ---
if (isset($_GET['slash_id'])) {
    $sub_id = (int)$_GET['slash_id'];
    
    // Get sub amount before deleting
    $get_sub = $conn->query("SELECT amount FROM subscriptions WHERE id = $sub_id AND user_id = $user_id");
    if($row = $get_sub->fetch_assoc()) {
        $saved_amt = $row['amount'];
        // Delete sub and add to wallet
        $conn->query("DELETE FROM subscriptions WHERE id = $sub_id");
        $conn->query("UPDATE users SET wallet_balance = wallet_balance + $saved_amt WHERE id = $user_id");
        header("Location: dashboard.php?saved=$saved_amt");
        exit();
    }
}

// --- 4. Calculations Engine ---
$totalMonthly = 0;
$unusedSubs = [];
$res = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id");

while($row = $res->fetch_assoc()) {
    $monthlyVal = ($row['billing_cycle'] == 'Monthly') ? $row['amount'] : ($row['amount'] / 12);
    $totalMonthly += $monthlyVal;

    // Logic: If not used in > 20 days, flag as unused
    $lastDate = new DateTime($row['last_accessed']);
    $today = new DateTime();
    $daysIdle = $today->diff($lastDate)->format("%a");

    if($daysIdle > 20) {
        $row['days_idle'] = $daysIdle;
        $unusedSubs[] = $row;
    }
}
$totalYearly = $totalMonthly * 12;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Slasher | Smart Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; color: #f8fafc; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        .modal { transition: all 0.3s ease; opacity: 0; pointer-events: none; visibility: hidden; }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .wallet-gradient { background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%); }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">

    <div class="max-w-7xl mx-auto space-y-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h1 class="text-3xl font-black">Hello, <span class="text-purple-500 italic"><?php echo $firstName; ?>!</span> 👋</h1>
                <p class="text-slate-500 font-medium">Your subscription health is looking <?php echo (count($unusedSubs) > 0) ? 'risky' : 'excellent'; ?>.</p>
            </div>
            
            <div class="wallet-gradient p-5 rounded-[2rem] flex items-center gap-6 shadow-xl shadow-purple-500/20">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/70">Slasher Wallet</p>
                    <h3 class="text-2xl font-black text-white">₹<?php echo number_format($wallet, 2); ?></h3>
                </div>
                <div class="h-10 w-[1px] bg-white/20"></div>
                <button onclick="toggleModal(true)" class="bg-white/20 hover:bg-white/30 p-3 rounded-xl transition-all">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="glass p-6 rounded-[2rem]">
                <p class="text-slate-500 text-xs font-bold uppercase">Monthly Burn</p>
                <h2 class="text-3xl font-black mt-2">₹<?php echo number_format($totalMonthly, 2); ?></h2>
            </div>
            <div class="glass p-6 rounded-[2rem]">
                <p class="text-slate-500 text-xs font-bold uppercase">Annual Loss</p>
                <h2 class="text-3xl font-black mt-2 text-slate-400">₹<?php echo number_format($totalYearly, 2); ?></h2>
            </div>
            <div class="glass p-6 rounded-[2rem] border-rose-500/20 bg-rose-500/5">
                <p class="text-rose-500 text-xs font-bold uppercase tracking-widest">Unused Waste</p>
                <h2 class="text-3xl font-black mt-2 text-white"><?php echo count($unusedSubs); ?> <span class="text-sm font-normal text-slate-500">Apps</span></h2>
            </div>
            <div class="glass p-6 rounded-[2rem] border-emerald-500/20 bg-emerald-500/5">
                <p class="text-emerald-500 text-xs font-bold uppercase tracking-widest">Auto-Deduction</p>
                <h2 class="text-3xl font-black mt-2 text-white">Active</h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 glass rounded-[2.5rem] overflow-hidden">
                <div class="p-8 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-xl font-bold">Active Portfolio</h3>
                    <div class="flex gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Live Syncing</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-800/50 text-slate-500 text-[10px] uppercase font-bold tracking-widest">
                            <tr>
                                <th class="px-8 py-4">Service</th>
                                <th class="px-8 py-4">Status</th>
                                <th class="px-8 py-4">Monthly</th>
                                <th class="px-8 py-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <?php 
                            $subs_table = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id ORDER BY amount DESC");
                            while($row = $subs_table->fetch_assoc()): 
                            ?>
                                <tr class="hover:bg-slate-800/20 transition-colors group">
                                    <td class="px-8 py-6 font-bold text-white"><?php echo $row['service_name']; ?></td>
                                    <td class="px-8 py-6">
                                        <span class="text-[10px] font-black uppercase text-slate-500 italic">Last used <?php echo $row['last_accessed']; ?></span>
                                    </td>
                                    <td class="px-8 py-6 font-black text-white">₹<?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="px-8 py-6">
                                        <a href="dashboard.php?slash_id=<?php echo $row['id']; ?>" class="text-rose-500 hover:text-rose-400 text-xs font-bold flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                            SLASH <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gradient-to-br from-rose-900/40 to-slate-900/40 border border-rose-500/20 p-8 rounded-[2.5rem]">
                    <h4 class="text-rose-400 text-xs font-black uppercase tracking-widest mb-4">Idle Alert 🚨</h4>
                    <?php if(count($unusedSubs) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach($unusedSubs as $unused): ?>
                                <div class="bg-slate-900/60 p-4 rounded-2xl border border-white/5">
                                    <p class="text-white font-bold"><?php echo $unused['service_name']; ?></p>
                                    <p class="text-slate-400 text-xs mt-1 italic">Unused for <?php echo $unused['days_idle']; ?> days.</p>
                                    <p class="text-emerald-400 text-xs mt-2 font-bold italic">Potential: Move ₹<?php echo $unused['amount']; ?> to Wallet</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-slate-400 text-sm italic">Great job! All your subscriptions are being actively used.</p>
                    <?php endif; ?>
                </div>

                <div class="glass p-8 rounded-[2.5rem] border-purple-500/20">
                    <h4 class="text-purple-400 text-xs font-black uppercase mb-2 tracking-widest">Auto-Deduction Logic</h4>
                    <p class="text-slate-300 text-sm">Every time you "Slash" a subscription, the amount is automatically diverted to your <span class="text-white font-bold">Slasher Wallet</span> as savings instead of going to the provider.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="add-modal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl">
        <div class="glass max-w-lg w-full p-10 rounded-[3rem] shadow-2xl scale-95 transition-all">
            <div class="flex justify-between items-center mb-10">
                <h2 class="text-2xl font-black text-white italic">New Burn Source.</h2>
                <button onclick="toggleModal(false)" class="text-slate-500 hover:text-white text-3xl">&times;</button>
            </div>

            <form action="dashboard.php" method="POST" class="space-y-6">
                <input type="hidden" name="add_sub" value="1">
                
                <input type="text" name="name" placeholder="Service Name (e.g. Netflix)" class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500 transition-all" required>

                <div class="grid grid-cols-2 gap-4">
                    <input type="number" step="0.01" name="amount" placeholder="Amount (₹)" class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500 transition-all" required>
                    <select name="cycle" class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500 transition-all">
                        <option value="Monthly">Monthly</option>
                        <option value="Yearly">Yearly</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] text-slate-500 font-bold uppercase ml-2">Simulate Last Access Date</label>
                    <input type="date" name="last_use" class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500 transition-all">
                </div>

                <select name="category" class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500 transition-all">
                    <option value="Entertainment">Entertainment</option>
                    <option value="Software">Software</option>
                    <option value="Utilities">Utilities</option>
                    <option value="Fitness">Fitness</option>
                </select>

                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-95 uppercase tracking-widest text-sm">
                    Track Subscription
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleModal(show) {
            const m = document.getElementById('add-modal');
            if(show) { m.classList.add('active'); m.firstElementChild.classList.add('scale-100'); }
            else { m.classList.remove('active'); m.firstElementChild.classList.remove('scale-100'); }
        }
    </script>
</body>
</html>