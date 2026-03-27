<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// --- 1. BACKEND ACTION HANDLERS ---

// A. Add Subscription
if (isset($_POST['add_sub'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $amt = (float)$_POST['amount'];
    $cat = $_POST['category'];
    $cycle = $_POST['cycle'];
    $last_use = $_POST['last_use'] ?: date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, service_name, amount, category, billing_cycle, last_accessed) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $name, $amt, $cat, $cycle, $last_use);
    $stmt->execute();
    header("Location: dashboard.php?msg=Subscription Added"); exit();
}

// B. Swap Logic (Accept/Reject)
if (isset($_GET['swap_action'])) {
    $swap_id = (int)$_GET['swap_id'];
    $action = $_GET['swap_action'];
    
    if ($action === 'accept') {
        $swap = $conn->query("SELECT * FROM swap_requests WHERE id = $swap_id")->fetch_assoc();
        // Swap User IDs for the subs
        $conn->query("UPDATE subscriptions SET user_id = {$swap['sender_id']} WHERE id = {$swap['receiver_sub_id']}");
        $conn->query("UPDATE subscriptions SET user_id = {$swap['receiver_id']} WHERE id = {$swap['sender_sub_id']}");
        $conn->query("UPDATE swap_requests SET status = 'Accepted' WHERE id = $swap_id");
        $conn->query("INSERT INTO alerts (user_id, message) VALUES ({$swap['sender_id']}, 'Your swap request was accepted!')");
    } else {
        $conn->query("UPDATE swap_requests SET status = 'Rejected' WHERE id = $swap_id");
    }
    header("Location: dashboard.php?msg=Swap " . ucfirst($action) . "ed"); exit();
}

// C. Budget Logic
if (isset($_POST['set_budget'])) {
    $new_budget = (float)$_POST['budget_amt'];
    $conn->query("UPDATE users SET monthly_budget = $new_budget WHERE id = $user_id");
    header("Location: dashboard.php?msg=Budget Updated"); exit();
}

// --- 2. DATA ENGINE ---
$user_stmt = $conn->prepare("SELECT full_name, wallet_balance, monthly_budget FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id); $user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$firstName = explode(' ', trim($user_data['full_name']))[0];
$budget = ($user_data['monthly_budget'] > 0) ? $user_data['monthly_budget'] : 5000;

$totalMonthly = 0; $unusedSubs = [];
$res = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id");
while($row = $res->fetch_assoc()) {
    $monthlyVal = ($row['billing_cycle'] == 'Monthly') ? $row['amount'] : ($row['amount'] / 12);
    $totalMonthly += $monthlyVal;
    $daysIdle = (new DateTime())->diff(new DateTime($row['last_accessed']))->format("%a");
    if($daysIdle > 15) { $row['days_idle'] = $daysIdle; $unusedSubs[] = $row; }
}
$budgetUsage = ($budget > 0) ? ($totalMonthly / $budget) * 100 : 0;
$unread_alerts = $conn->query("SELECT * FROM alerts WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slasher | Economy Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; color: #f8fafc; }
        .glass { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); }
        .modal { transition: all 0.3s ease; opacity: 0; pointer-events: none; visibility: hidden; z-index: 1000; }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .wallet-card { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); box-shadow: 0 20px 50px -15px rgba(139, 92, 246, 0.4); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .alert-card { animation: slideIn 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards; }
    </style>
</head>
<body class="p-4 md:p-10 pb-32">

    <div id="alert-stack" class="fixed top-6 right-6 space-y-3 w-80 z-[200]">
        <?php while($alert = $unread_alerts->fetch_assoc()): ?>
            <div class="alert-card glass border-l-4 border-emerald-500 p-4 rounded-2xl shadow-2xl pointer-events-auto">
                <div class="flex justify-between">
                    <p class="text-xs font-bold text-white"><?php echo $alert['message']; ?></p>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-slate-500">×</button>
                </div>
            </div>
        <?php endwhile; $conn->query("UPDATE alerts SET is_read = 1 WHERE user_id = $user_id"); ?>
    </div>

    <div class="max-w-7xl mx-auto space-y-10">
        
        <header class="flex flex-col md:flex-row justify-between items-center gap-6">
            <div>
                <h1 class="text-4xl font-black italic tracking-tighter">Slasher<span class="text-purple-500">.</span></h1>
                <div class="flex items-center gap-4 mt-2">
                    <p class="text-slate-500 font-medium">Hello, <?php echo $firstName; ?>.</p>
                    <a href="logout.php" class="text-[10px] font-black uppercase tracking-widest bg-rose-500/10 text-rose-500 px-4 py-2 rounded-xl hover:bg-rose-500 hover:text-white transition-all">Sign Out</a>
                </div>
            </div>

            <div class="glass p-6 rounded-[2.5rem] w-full md:w-96">
                <div class="flex justify-between text-[10px] font-black uppercase mb-3">
                    <span class="text-slate-400 tracking-widest">Efficiency</span>
                    <span class="<?php echo ($budgetUsage > 90) ? 'text-rose-500' : 'text-purple-400'; ?> tracking-tighter">₹<?php echo number_format($totalMonthly); ?> / ₹<?php echo number_format($budget); ?></span>
                </div>
                <div class="w-full bg-slate-800/50 h-3 rounded-full overflow-hidden">
                    <div class="bg-purple-500 h-full transition-all duration-1000" style="width: <?php echo min($budgetUsage, 100); ?>%"></div>
                </div>
                <button onclick="document.getElementById('budget-modal').classList.add('active')" class="mt-3 text-[10px] font-bold text-slate-500 hover:text-white transition-all underline underline-offset-4">SET BUDGET LIMIT</button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="wallet-card p-8 rounded-[3rem]">
                <p class="text-white/70 text-xs font-bold uppercase tracking-widest">Slasher Wallet</p>
                <h2 class="text-4xl font-black text-white mt-2">₹<?php echo number_format($user_data['wallet_balance'], 2); ?></h2>
                <p class="text-white/50 text-[10px] mt-4 italic font-medium">Saved from optimized burn.</p>
            </div>
            <div class="glass p-8 rounded-[3rem] border-rose-500/20 bg-rose-500/5">
                <p class="text-rose-500 text-xs font-bold uppercase tracking-widest">Waste Detection</p>
                <h2 class="text-4xl font-black text-white mt-2"><?php echo count($unusedSubs); ?> Apps</h2>
                <p class="text-slate-500 text-[10px] mt-4 italic font-medium">Idle for over 15 days.</p>
            </div>
            <div class="glass p-8 rounded-[3rem] border-emerald-500/20 bg-emerald-500/5 flex flex-col justify-center">
                <p class="text-emerald-500 text-xs font-bold uppercase tracking-widest">P2P Marketplace</p>
                <h2 class="text-3xl font-black text-white mt-2 italic tracking-tighter">Verified</h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 space-y-10">
                <div class="glass rounded-[3rem] p-10 border-indigo-500/20">
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-3 h-3 bg-indigo-500 rounded-full animate-ping"></div>
                        <h3 class="text-2xl font-black italic tracking-tighter">Live Negotiations</h3>
                    </div>
                    <div class="space-y-4">
                        <?php
                        $swaps = $conn->query("SELECT sr.*, s.service_name as my_sub, s2.service_name as their_sub, u.full_name as sender_name FROM swap_requests sr JOIN subscriptions s ON sr.receiver_sub_id = s.id JOIN subscriptions s2 ON sr.sender_sub_id = s2.id JOIN users u ON sr.sender_id = u.id WHERE sr.receiver_id = $user_id AND sr.status = 'Pending'");
                        if($swaps->num_rows > 0): while($s = $swaps->fetch_assoc()): ?>
                            <div class="flex flex-col md:flex-row md:items-center justify-between bg-white/5 p-6 rounded-[2rem] border border-white/5 gap-4 transition-all hover:bg-white/10">
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo explode(' ', $s['sender_name'])[0]; ?> wants to swap <span class="text-purple-400"><?php echo $s['their_sub']; ?></span></p>
                                    <p class="text-[10px] text-slate-500 font-medium">In exchange for your <span class="text-white"><?php echo $s['my_sub']; ?></span></p>
                                </div>
                                <div class="flex gap-3">
                                    <a href="dashboard.php?swap_id=<?php echo $s['id']; ?>&swap_action=accept" class="bg-emerald-600 text-[10px] font-black px-6 py-3 rounded-xl uppercase tracking-widest shadow-lg shadow-emerald-500/20">Accept</a>
                                    <a href="dashboard.php?swap_id=<?php echo $s['id']; ?>&swap_action=reject" class="bg-rose-500/20 text-rose-500 text-[10px] font-black px-6 py-3 rounded-xl uppercase tracking-widest">Decline</a>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center py-10 text-slate-600 italic text-sm">No incoming swap proposals.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass rounded-[3rem] p-10">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-black italic tracking-tighter text-white">Marketplace Discovery</h3>
                        <button onclick="document.getElementById('add-sub-modal').classList.add('active')" class="bg-purple-600 hover:bg-purple-500 text-white font-bold px-6 py-3 rounded-2xl text-[11px] uppercase tracking-widest transition-all shadow-xl shadow-purple-500/20 active:scale-95">+ Track New Sub</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php
                        $market = $conn->query("SELECT m.*, s.service_name, s.amount as sub_price, u.full_name FROM marketplace m JOIN subscriptions s ON m.sub_id = s.id JOIN users u ON m.owner_id = u.id WHERE m.is_available = 1 LIMIT 4");
                        while($item = $market->fetch_assoc()): ?>
                            <div class="bg-slate-900/40 border border-white/5 p-8 rounded-[2.5rem] hover:border-purple-500/50 transition-all group relative">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <h4 class="text-xl font-black text-white italic"><?php echo $item['service_name']; ?></h4>
                                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Owner: <?php echo explode(' ', $item['full_name'])[0]; ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-emerald-400 font-black text-lg leading-none">₹<?php echo $item['price_per_day']; ?></p>
                                        <p class="text-[8px] text-slate-500 font-bold uppercase mt-1">Per Day</p>
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <?php if($item['owner_id'] != $user_id): ?>
                                        <a href="dashboard.php?rent_id=<?php echo $item['id']; ?>" class="flex-1 bg-white text-black text-[10px] font-black py-4 rounded-2xl text-center uppercase tracking-widest hover:bg-slate-200">Rent</a>
                                        <a href="dashboard.php?request_swap=<?php echo $item['sub_id']; ?>&owner=<?php echo $item['owner_id']; ?>" class="flex-1 border border-white/10 text-[10px] font-black py-4 rounded-2xl text-center uppercase tracking-widest hover:bg-white/5">Swap</a>
                                    <?php else: ?>
                                        <button disabled class="flex-1 bg-slate-800 text-slate-500 text-[10px] font-black py-4 rounded-2xl text-center uppercase cursor-not-allowed">Yours</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div class="bg-gradient-to-br from-indigo-900/30 to-slate-950/30 border border-indigo-500/20 p-8 rounded-[3rem]">
                    <h4 class="text-indigo-400 text-[11px] font-black uppercase tracking-widest mb-6">Idle Slasher AI 💡</h4>
                    <?php if(count($unusedSubs) > 0): foreach($unusedSubs as $unused): ?>
                        <div class="glass p-5 rounded-3xl border-l-4 border-indigo-500 mb-4 bg-white/5">
                            <p class="text-white font-bold text-sm italic"><?php echo $unused['service_name']; ?></p>
                            <p class="text-[10px] text-slate-400 mt-2 font-medium">Idle for <?php echo $unused['days_idle']; ?> days. Exchange or Slash to save ₹<?php echo $unused['amount']; ?>.</p>
                            <button class="w-full mt-5 bg-indigo-600 text-[10px] font-black py-4 rounded-2xl uppercase tracking-tighter hover:bg-indigo-500 shadow-lg shadow-indigo-500/20">Optimize Now</button>
                        </div>
                    <?php endforeach; else: ?>
                        <p class="text-slate-600 text-xs italic">All active subs are frequently utilized.</p>
                    <?php endif; ?>
                </div>

                <div class="glass p-8 rounded-[3rem] border-emerald-500/20">
                    <h4 class="text-emerald-500 text-[11px] font-black uppercase tracking-widest mb-6">Portfolio Index</h4>
                    <div class="space-y-4">
                        <?php 
                        $my_subs = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id");
                        while($sub = $my_subs->fetch_assoc()): ?>
                            <div class="flex justify-between items-center group">
                                <div>
                                    <p class="text-xs font-bold text-white"><?php echo $sub['service_name']; ?></p>
                                    <p class="text-[9px] text-slate-600 uppercase font-black tracking-tighter italic">₹<?php echo number_format($sub['amount']); ?></p>
                                </div>
                                <a href="dashboard.php?list_market=<?php echo $sub['id']; ?>" class="text-[9px] font-black text-purple-400 bg-purple-500/10 px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-all uppercase tracking-widest">Lease</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="budget-modal" class="modal fixed inset-0 flex items-center justify-center bg-slate-950/95 backdrop-blur-2xl p-6">
        <div class="glass max-w-sm w-full p-10 rounded-[3.5rem] text-center border-purple-500/30">
            <h2 class="text-2xl font-black italic mb-2">Spending Cap.</h2>
            <p class="text-slate-500 text-xs mb-10">Limit your monthly subscription bleed.</p>
            <form action="dashboard.php" method="POST" class="space-y-6">
                <input type="number" name="budget_amt" value="<?php echo $budget; ?>" class="w-full bg-slate-900/50 border border-slate-700 p-6 rounded-3xl text-white font-black text-3xl text-center outline-none focus:ring-4 ring-purple-500/20">
                <button type="submit" name="set_budget" class="w-full bg-purple-600 py-5 rounded-3xl font-black uppercase tracking-widest text-sm shadow-2xl shadow-purple-500/30 active:scale-95 transition-all">Lock Limit</button>
                <button type="button" onclick="this.closest('.modal').classList.remove('active')" class="text-slate-600 text-[11px] font-bold uppercase tracking-widest hover:text-white">Close Window</button>
            </form>
        </div>
    </div>

    <div id="add-sub-modal" class="modal fixed inset-0 flex items-center justify-center bg-slate-950/95 backdrop-blur-2xl p-6">
        <div class="glass max-w-md w-full p-10 rounded-[3.5rem] border-purple-500/30 shadow-2xl">
            <h2 class="text-2xl font-black italic mb-8">Add Asset</h2>
            <form action="dashboard.php" method="POST" class="space-y-6">
                <input type="hidden" name="add_sub" value="1">
                <input type="text" name="name" placeholder="Service Name (Netflix, Spotify...)" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-2xl text-white font-bold outline-none focus:ring-2 ring-purple-500/50" required>
                <div class="grid grid-cols-2 gap-4">
                    <input type="number" step="0.01" name="amount" placeholder="Amount (₹)" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-2xl text-white font-bold outline-none focus:ring-2 ring-purple-500/50" required>
                    <select name="cycle" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-2xl text-white font-bold outline-none focus:ring-2 ring-purple-500/50">
                        <option value="Monthly">Monthly</option>
                        <option value="Yearly">Yearly</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] text-slate-500 font-bold uppercase tracking-widest ml-2">Last Access Simulation</label>
                    <input type="date" name="last_use" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-2xl text-white font-bold outline-none focus:ring-2 ring-purple-500/50">
                </div>
                <select name="category" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-2xl text-white font-bold outline-none focus:ring-2 ring-purple-500/50">
                    <option value="Entertainment">Entertainment</option>
                    <option value="Software">Software</option>
                    <option value="Utilities">Utilities</option>
                    <option value="Fitness">Fitness</option>
                </select>
                <button type="submit" class="w-full bg-purple-600 py-5 rounded-2xl font-black uppercase tracking-widest shadow-2xl shadow-purple-500/30">Track Subscription</button>
                <button type="button" onclick="this.closest('.modal').classList.remove('active')" class="w-full text-slate-500 text-[11px] font-bold uppercase hover:text-white">Dismiss</button>
            </form>
        </div>
    </div>

    <nav class="lg:hidden fixed bottom-8 left-1/2 -translate-x-1/2 w-[90%] max-w-[420px] z-[150]">
        <div class="glass flex items-center justify-around p-5 rounded-[2.5rem] shadow-2xl border border-white/10 relative">
            <a href="dashboard.php" class="text-purple-500 transition-transform active:scale-90"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
            <button onclick="document.getElementById('add-sub-modal').classList.add('active')" class="w-16 h-16 bg-purple-600 rounded-full flex items-center justify-center shadow-2xl -mt-16 border-8 border-[#020617] text-white transition-all active:scale-90"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2.5"/></svg></button>
            <a href="logout.php" class="text-slate-500 transition-transform active:scale-90"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg></a>
        </div>
    </nav>

</body>
</html>