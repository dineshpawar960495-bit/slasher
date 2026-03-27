<?php
session_start();
require 'db.php';

// --- 1. AUTHENTICATION & GLOBAL STATE ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// --- 2. THE TRANSACTION ROUTER (Processes all actions) ---

// ACTION: Set Budget
if (isset($_POST['set_budget'])) {
    $new_budget = (float)$_POST['budget_amt'];
    $conn->query("UPDATE users SET monthly_budget = $new_budget WHERE id = $user_id");
    header("Location: dashboard.php?msg=Budget Locked"); exit();
}

// ACTION: Add Subscription
if (isset($_POST['add_sub_action'])) {
    $name = mysqli_real_escape_string($conn, $_POST['service_name']);
    $amt = (float)$_POST['amount'];
    $cat = $_POST['category'];
    $cycle = $_POST['billing_cycle'];
    $last_use = !empty($_POST['last_accessed']) ? $_POST['last_accessed'] : date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, service_name, amount, category, billing_cycle, last_accessed) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $name, $amt, $cat, $cycle, $last_use);
    $stmt->execute();
    header("Location: dashboard.php?msg=Tracking Established"); exit();
}

// ACTION: Slash & Save (Cancellation logic)
if (isset($_GET['slash_id'])) {
    $sub_id = (int)$_GET['slash_id'];
    $sub = $conn->query("SELECT amount, service_name FROM subscriptions WHERE id = $sub_id AND user_id = $user_id")->fetch_assoc();
    if ($sub) {
        $saved = $sub['amount'];
        $conn->query("UPDATE users SET wallet_balance = wallet_balance + $saved WHERE id = $user_id");
        $conn->query("DELETE FROM subscriptions WHERE id = $sub_id");
        $conn->query("INSERT INTO alerts (user_id, message) VALUES ($user_id, 'RECOVERED ₹$saved from {$sub['service_name']}')");
    }
    header("Location: dashboard.php?msg=Asset Slashed"); exit();
}

// ACTION: List on Marketplace
if (isset($_GET['list_market'])) {
    $sub_id = (int)$_GET['list_market'];
    // Verify ownership before listing
    $check = $conn->query("SELECT id FROM subscriptions WHERE id = $sub_id AND user_id = $user_id");
    if($check->num_rows > 0) {
        $conn->query("INSERT INTO marketplace (owner_id, sub_id, price_per_day, is_available) VALUES ($user_id, $sub_id, 25.00, 1)");
    }
    header("Location: dashboard.php?msg=Listed Globally"); exit();
}

// ACTION: Swap Logic (Accept/Reject Handshake)
if (isset($_GET['swap_id']) && isset($_GET['swap_action'])) {
    $s_id = (int)$_GET['swap_id'];
    $action = $_GET['swap_action'];
    $swap = $conn->query("SELECT * FROM swap_requests WHERE id = $s_id AND receiver_id = $user_id")->fetch_assoc();
    
    if ($swap) {
        if ($action == 'accept') {
            $conn->query("UPDATE subscriptions SET user_id = {$swap['sender_id']} WHERE id = {$swap['receiver_sub_id']}");
            $conn->query("UPDATE subscriptions SET user_id = {$swap['receiver_id']} WHERE id = {$swap['sender_sub_id']}");
            $conn->query("UPDATE swap_requests SET status = 'Accepted' WHERE id = $s_id");
            $conn->query("INSERT INTO alerts (user_id, message) VALUES ({$swap['sender_id']}, 'Your swap was Accepted!')");
        } else {
            $conn->query("UPDATE swap_requests SET status = 'Rejected' WHERE id = $s_id");
        }
    }
    header("Location: dashboard.php?msg=Handshake Resolved"); exit();
}

// ACTION: Rent from Market
if (isset($_GET['rent_id'])) {
    $m_id = (int)$_GET['rent_id'];
    $item = $conn->query("SELECT m.*, u.wallet_balance FROM marketplace m JOIN users u ON m.owner_id = u.id WHERE m.id = $m_id")->fetch_assoc();
    if ($item && $item['owner_id'] != $user_id) {
        $price = $item['price_per_day'];
        $conn->query("UPDATE users SET wallet_balance = wallet_balance - $price WHERE id = $user_id");
        $conn->query("UPDATE users SET wallet_balance = wallet_balance + $price WHERE id = {$item['owner_id']}");
        $conn->query("UPDATE marketplace SET is_available = 0 WHERE id = $m_id");
        $conn->query("INSERT INTO alerts (user_id, message) VALUES ({$item['owner_id']}, 'Someone just rented your asset!')");
    }
    header("Location: dashboard.php?msg=Rental Complete"); exit();
}

// --- 3. ANALYTICS ENGINE & DATA FETCH ---
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$firstName = explode(' ', trim($user_data['full_name'] ?? 'Slasher'))[0];
$budget = ($user_data['monthly_budget'] > 0) ? $user_data['monthly_budget'] : 5000;

$totalMonthly = 0; $unusedKillList = [];
$subs_res = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id");
while($row = $subs_res->fetch_assoc()) {
    $mVal = ($row['billing_cycle'] == 'Monthly') ? $row['amount'] : ($row['amount'] / 12);
    $totalMonthly += $mVal;
    $days = (new DateTime())->diff(new DateTime($row['last_accessed']))->format("%a");
    if($days > 15) { $row['idle_days'] = $days; $unusedKillList[] = $row; }
}
$totalYearly = $totalMonthly * 12;
$budgetUsage = ($totalMonthly / $budget) * 100;
$unread_alerts = $conn->query("SELECT * FROM alerts WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slasher | All-In-One Fintech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; color: #f8fafc; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); }
        .modal { transition: all 0.3s ease; opacity: 0; pointer-events: none; visibility: hidden; z-index: 1000; }
        .modal.active { opacity: 1; pointer-events: auto; visibility: visible; }
        .wallet-card { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); box-shadow: 0 20px 40px -10px rgba(139, 92, 246, 0.3); }
        ::-webkit-scrollbar { display: none; }
        @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-pop { animation: popIn 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards; }
    </style>
</head>
<body class="p-4 md:p-10 pb-32">

    <div id="alert-stack" class="fixed top-6 right-6 space-y-3 w-80 z-[200]">
        <?php while($alert = $unread_alerts->fetch_assoc()): ?>
            <div class="glass border-l-4 border-purple-500 p-5 rounded-2xl shadow-2xl animate-pop">
                <div class="flex justify-between items-start">
                    <p class="text-xs font-bold text-white"><?php echo $alert['message']; ?></p>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-slate-500 hover:text-white">×</button>
                </div>
            </div>
        <?php endwhile; $conn->query("UPDATE alerts SET is_read = 1 WHERE user_id = $user_id"); ?>
    </div>

    <div class="max-w-7xl mx-auto space-y-10">
        
        <header class="flex flex-col lg:flex-row justify-between items-center gap-8">
            <div class="text-center lg:text-left">
                <h1 class="text-5xl font-black italic tracking-tighter">Slasher<span class="text-purple-500">.</span></h1>
                <div class="flex items-center justify-center lg:justify-start gap-4 mt-3">
                    <p class="text-slate-400 font-medium">Hello, <span class="text-white"><?php echo $firstName; ?></span></p>
                    <a href="logout.php" class="text-[9px] font-black bg-rose-500/10 text-rose-500 px-4 py-1.5 rounded-lg uppercase hover:bg-rose-500 hover:text-white transition-all">Sign Out</a>
                </div>
            </div>

            <div class="glass p-8 rounded-[3rem] w-full max-w-md shadow-2xl">
                <div class="flex justify-between text-[10px] font-black uppercase mb-4 tracking-widest text-slate-400">
                    <span>Financial Cap</span>
                    <span class="<?php echo ($budgetUsage > 90) ? 'text-rose-500' : 'text-purple-400'; ?>">₹<?php echo number_format($totalMonthly); ?> / ₹<?php echo number_format($budget); ?></span>
                </div>
                <div class="w-full bg-slate-800/50 h-3 rounded-full overflow-hidden mb-4">
                    <div class="bg-purple-500 h-full transition-all duration-1000 shadow-[0_0_15px_#8b5cf6]" style="width: <?php echo min($budgetUsage, 100); ?>%"></div>
                </div>
                <button onclick="document.getElementById('budget-modal').classList.add('active')" class="text-[10px] font-black text-slate-500 hover:text-white transition-all underline underline-offset-8 uppercase tracking-tighter">Adjust Spending Limit</button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="wallet-card p-10 rounded-[3.5rem] relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 opacity-10 group-hover:scale-110 transition-transform duration-700">
                    <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8z"/></svg>
                </div>
                <p class="text-white/70 text-xs font-bold uppercase tracking-widest">Savings E-Wallet</p>
                <h2 class="text-5xl font-black text-white mt-2">₹<?php echo number_format($user_data['wallet_balance'], 2); ?></h2>
            </div>

            <div class="glass p-10 rounded-[3.5rem] border-rose-500/20 bg-rose-500/[0.02]">
                <p class="text-rose-500 text-xs font-bold uppercase tracking-widest">Yearly Bleed</p>
                <h2 class="text-5xl font-black text-white mt-2">₹<?php echo number_format($totalYearly, 2); ?></h2>
                <p class="text-slate-500 text-[10px] mt-4 italic font-bold">12-Month Projected Burn</p>
            </div>

            <button onclick="toggleAddModal(true)" class="glass p-10 rounded-[3.5rem] border-emerald-500/20 bg-emerald-500/[0.02] hover:bg-emerald-500/10 transition-all text-left group">
                <p class="text-emerald-500 text-xs font-bold uppercase tracking-widest group-hover:translate-x-1 transition-transform">+ Add Asset</p>
                <h2 class="text-3xl font-black text-white mt-2">Track New Sub</h2>
                <p class="text-slate-500 text-[10px] mt-4 italic font-bold">Click to update portfolio</p>
            </button>
        </div>

        <?php if(!empty($unusedKillList)): ?>
        <section class="animate-pulse-slow">
            <h3 class="text-[10px] font-black text-rose-500 uppercase tracking-[0.4em] mb-6 ml-4 flex items-center gap-3">
                <span class="w-3 h-3 bg-rose-500 rounded-full animate-ping"></span> 
                Waste Detection AI: Recommendations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($unusedKillList as $item): ?>
                    <div class="glass border-rose-500/30 p-8 rounded-[2.5rem] bg-rose-500/[0.03] flex items-center justify-between group">
                        <div>
                            <h4 class="text-white font-black text-lg italic leading-none"><?php echo $item['service_name']; ?></h4>
                            <p class="text-[10px] text-rose-400 font-bold uppercase mt-2 tracking-widest">Idle for <?php echo $item['idle_days']; ?> Days</p>
                        </div>
                        <a href="dashboard.php?slash_id=<?php echo $item['id']; ?>" class="bg-rose-600 hover:bg-rose-500 text-white font-black text-[10px] px-5 py-3 rounded-2xl uppercase tracking-widest transition-all shadow-xl shadow-rose-900/40">Slash</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 space-y-12">
                
                <div class="glass rounded-[3rem] p-10 border-indigo-500/20">
                    <div class="flex items-center gap-4 mb-10">
                        <div class="w-4 h-4 bg-indigo-500 rounded-full animate-ping"></div>
                        <h3 class="text-3xl font-black italic tracking-tighter">Live Handshakes</h3>
                    </div>
                    <div class="space-y-6">
                        <?php
                        $swaps = $conn->query("SELECT sr.*, s1.service_name as my_sub, s2.service_name as their_sub, u.full_name FROM swap_requests sr JOIN subscriptions s1 ON sr.receiver_sub_id = s1.id JOIN subscriptions s2 ON sr.sender_sub_id = s2.id JOIN users u ON sr.sender_id = u.id WHERE sr.receiver_id = $user_id AND sr.status = 'Pending'");
                        if($swaps->num_rows > 0): while($s = $swaps->fetch_assoc()): ?>
                            <div class="flex flex-col md:flex-row md:items-center justify-between bg-white/[0.03] p-8 rounded-[2.5rem] border border-white/5 group hover:bg-white/[0.06] transition-all">
                                <div>
                                    <p class="text-lg font-black text-white italic"><?php echo explode(' ', $s['full_name'])[0]; ?> wants to Swap.</p>
                                    <p class="text-xs text-slate-400 mt-1 font-medium">Their <span class="text-purple-400 font-bold"><?php echo $s['their_sub']; ?></span> for your <span class="text-emerald-400 font-bold"><?php echo $s['my_sub']; ?></span></p>
                                </div>
                                <div class="flex gap-4 mt-4 md:mt-0">
                                    <a href="dashboard.php?swap_id=<?php echo $s['id']; ?>&swap_action=accept" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black px-8 py-3 rounded-2xl text-xs uppercase tracking-widest shadow-2xl shadow-emerald-900/20">Accept</a>
                                    <a href="dashboard.php?swap_id=<?php echo $s['id']; ?>&swap_action=reject" class="bg-white/10 text-slate-400 hover:text-white font-black px-8 py-3 rounded-2xl text-xs uppercase tracking-widest">Ignore</a>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <p class="text-center py-10 text-slate-600 italic font-medium">No pending exchange proposals at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass rounded-[3.5rem] overflow-hidden shadow-2xl border-white/5">
                    <div class="p-10 border-b border-white/5 flex justify-between items-center bg-white/[0.01]">
                        <h3 class="text-2xl font-black italic tracking-tighter italic">Active Assets Portfolio</h3>
                        <span class="text-[10px] font-black bg-purple-500 text-white px-4 py-2 rounded-xl uppercase"><?php echo $subs_res->num_rows; ?> Tracking</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-800/40 text-[9px] font-black uppercase tracking-[0.3em] text-slate-500">
                                <tr>
                                    <th class="px-10 py-6">Service</th>
                                    <th class="px-10 py-6">Monthly Burn</th>
                                    <th class="px-10 py-6 text-center">Protocol</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php 
                                $final_subs = $conn->query("SELECT * FROM subscriptions WHERE user_id = $user_id ORDER BY amount DESC");
                                while($sub = $final_subs->fetch_assoc()): ?>
                                    <tr class="group hover:bg-white/[0.02] transition-all">
                                        <td class="px-10 py-8">
                                            <div class="flex items-center gap-5">
                                                <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center font-black text-xl italic text-white border border-white/10 group-hover:border-purple-500/50 transition-all">
                                                    <?php echo substr($sub['service_name'], 0, 1); ?>
                                                </div>
                                                <div>
                                                    <p class="text-base font-black text-white italic"><?php echo $sub['service_name']; ?></p>
                                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1"><?php echo $sub['category']; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-10 py-8">
                                            <p class="text-xl font-black text-white italic tracking-tight">₹<?php echo number_format($sub['amount']); ?></p>
                                        </td>
                                        <td class="px-10 py-8">
                                            <div class="flex justify-center gap-6 opacity-0 group-hover:opacity-100 transition-all">
                                                <a href="dashboard.php?list_market=<?php echo $sub['id']; ?>" class="text-[10px] font-black text-purple-400 hover:text-white transition-colors uppercase tracking-widest">Lease to Market</a>
                                                <a href="dashboard.php?slash_id=<?php echo $sub['id']; ?>" onclick="return confirm('Recycle this sub into savings?')" class="text-[10px] font-black text-rose-500 hover:text-white transition-colors uppercase tracking-widest">Slash</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="glass rounded-[3.5rem] p-12 border-white/5">
                    <h3 class="text-3xl font-black italic mb-12 tracking-tighter">P2P Network Discovery</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php
                        $market = $conn->query("SELECT m.*, s.service_name, u.full_name, u.id as owner_uid FROM marketplace m JOIN subscriptions s ON m.sub_id = s.id JOIN users u ON m.owner_id = u.id WHERE m.owner_id != $user_id AND m.is_available = 1 LIMIT 6");
                        if($market->num_rows > 0): while($item = $market->fetch_assoc()): ?>
                            <div class="bg-slate-900/60 border border-white/5 p-8 rounded-[3rem] group hover:border-purple-500/40 transition-all hover:-translate-y-2 duration-500">
                                <div class="flex justify-between items-start mb-8">
                                    <div>
                                        <h4 class="text-2xl font-black text-white italic leading-none tracking-tighter"><?php echo $item['service_name']; ?></h4>
                                        <p class="text-[10px] text-slate-500 font-bold uppercase mt-3 italic tracking-widest">Host: <?php echo explode(' ', $item['full_name'])[0]; ?></p>
                                    </div>
                                    <span class="text-emerald-400 font-black text-xl italic">₹<?php echo $item['price_per_day']; ?><span class="text-[9px] text-slate-600 block text-right mt-1">/DAY</span></span>
                                </div>
                                <div class="flex gap-4">
                                    <a href="dashboard.php?rent_id=<?php echo $item['id']; ?>" class="flex-1 bg-white text-black text-[11px] font-black py-4 rounded-2xl text-center uppercase tracking-widest shadow-xl">Rent Sub</a>
                                    <a href="dashboard.php?request_swap=<?php echo $item['sub_id']; ?>&owner=<?php echo $item['owner_uid']; ?>" class="flex-1 border border-white/10 text-white text-[11px] font-black py-4 rounded-2xl text-center uppercase tracking-widest hover:bg-white/10 transition-all">Swap</a>
                               
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <p class="text-slate-600 italic text-sm col-span-2 text-center py-20 border-2 border-dashed border-white/5 rounded-[3rem]">P2P Network is currently quiet. Check back later.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
<div class="glass border-dashed border-2 border-white/10 p-8 rounded-[3rem] flex flex-col items-center justify-center text-center group hover:border-purple-500/40 transition-all cursor-pointer" onclick="window.location.href='buy.php'">
    <div class="w-16 h-16 rounded-full bg-purple-600/20 flex items-center justify-center text-purple-500 mb-4 group-hover:scale-110 transition-transform">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
    </div>
    <h4 class="text-white font-black italic text-lg">Missing an App?</h4>
    <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest mt-1">Get original subscriptions</p>
    <a href="buy.php" class="mt-6 text-[10px] font-black text-purple-400 underline underline-offset-4 group-hover:text-white transition-colors">EXPLORE CATALOG →</a>
</div>
            <div class="space-y-10">
                <div class="bg-gradient-to-br from-indigo-900/40 to-slate-950/40 border border-indigo-500/20 p-10 rounded-[3.5rem] shadow-2xl">
                    <h4 class="text-indigo-400 text-[11px] font-black uppercase tracking-widest mb-6 flex items-center gap-3">
                        <span class="w-3 h-3 bg-indigo-500 rounded-full animate-ping"></span>
                        Handshake Intel
                    </h4>
                    <p class="text-[12px] text-slate-400 leading-relaxed italic font-medium">The P2P engine is currently verifying global handshakes. You can swap your idle subscriptions for high-value access without increasing your burn.</p>
                    <div class="h-[1px] bg-white/10 my-8"></div>
                    <p class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Active nodes: 124 Peers Online</p>
                </div>

                <div class="glass p-10 rounded-[3.5rem] border-purple-500/20 shadow-2xl">
                    <h4 class="text-purple-400 text-[11px] font-black uppercase tracking-widest mb-8">Asset Liquidity</h4>
                    <p class="text-slate-400 text-[11px] leading-loose">By leasing your tracking assets to the Marketplace, you can generate <span class="text-white font-bold tracking-tighter">Passive Wallet Credits</span> to cover your own Cloud and Utility bills.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="budget-modal" class="modal fixed inset-0 flex items-center justify-center bg-slate-950/95 backdrop-blur-2xl p-6">
        <div class="glass max-w-sm w-full p-12 rounded-[4rem] text-center border-purple-500/30 shadow-2xl animate-pop">
            <h2 class="text-3xl font-black italic text-white mb-2 tracking-tighter">Budget Control.</h2>
            <p class="text-slate-500 text-xs mb-10 font-medium">Set your monthly subscription burn limit.</p>
            <form action="dashboard.php" method="POST" class="space-y-8">
                <div class="relative">
                    <span class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-500 font-bold text-2xl italic">₹</span>
                    <input type="number" name="budget_amt" value="<?php echo $budget; ?>" class="w-full bg-slate-900/50 border border-slate-700 p-6 rounded-3xl text-white font-black text-4xl text-center outline-none focus:ring-4 ring-purple-500/20 transition-all">
                </div>
                <button type="submit" name="set_budget" class="w-full bg-purple-600 hover:bg-purple-500 py-6 rounded-3xl font-black uppercase tracking-[0.2em] text-sm shadow-2xl shadow-purple-500/30 active:scale-95 transition-all">Lock Limit</button>
                <button type="button" onclick="this.closest('.modal').classList.remove('active')" class="text-slate-600 text-[11px] font-bold uppercase tracking-widest hover:text-white transition-colors">Dismiss</button>
            </form>
        </div>
    </div>

    <div id="add-sub-modal" class="modal fixed inset-0 flex items-center justify-center bg-slate-950/95 backdrop-blur-2xl p-6">
        <div class="glass max-w-md w-full p-12 rounded-[4rem] border-purple-500/30 shadow-2xl animate-pop" id="add-modal-content">
            <h2 class="text-4xl font-black italic text-white mb-10 tracking-tighter italic leading-none text-center">Track New Burn.</h2>
            <form action="dashboard.php" method="POST" class="space-y-6">
                <input type="hidden" name="add_sub_action" value="1">
                
                <div class="space-y-2">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest ml-3">Service Name</label>
                    <input type="text" name="service_name" placeholder="Netflix, Spotify, AWS..." class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-3xl text-white font-bold outline-none focus:ring-4 ring-purple-500/10" required>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest ml-3">Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-3xl text-white font-bold outline-none focus:ring-4 ring-purple-500/10" required>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest ml-3">Billing Cycle</label>
                        <select name="billing_cycle" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-3xl text-white font-bold outline-none focus:ring-4 ring-purple-500/10"><option value="Monthly">Monthly</option><option value="Yearly">Yearly</option></select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest ml-3">Classification</label>
                    <select name="category" class="w-full bg-slate-900/50 border border-slate-700 p-5 rounded-3xl text-white font-bold outline-none"><option value="Entertainment">Entertainment</option><option value="Software">Software</option><option value="Fitness">Fitness</option></select>
                </div>

                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 py-6 rounded-3xl font-black uppercase tracking-[0.2em] text-sm shadow-2xl active:scale-95 transition-all">Establish Link</button>
                <button type="button" onclick="toggleAddModal(false)" class="w-full text-slate-600 text-[11px] font-bold uppercase tracking-widest mt-4">Close Protocol</button>
            </form>
        </div>
    </div>

    <nav class="lg:hidden fixed bottom-8 left-1/2 -translate-x-1/2 w-[90%] max-w-[440px] z-[150]">
        <div class="glass flex items-center justify-around p-6 rounded-[3rem] shadow-2xl border border-white/10 relative">
            <a href="dashboard.php" class="text-purple-500 transition-transform active:scale-90"><svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
            <button onclick="toggleAddModal(true)" class="w-20 h-20 bg-purple-600 rounded-full flex items-center justify-center shadow-2xl -mt-20 border-[10px] border-[#020617] text-white transition-all active:scale-90 shadow-purple-500/40"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg></button>
            <a href="logout.php" class="text-slate-500 transition-transform active:scale-90"><svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg></a>
        </div>
    </nav>

    <script>
        function toggleAddModal(show) {
            const m = document.getElementById('add-sub-modal');
            if(show) { m.classList.add('active'); }
            else { m.classList.remove('active'); }
        }

        // Auto-fade alerts after 7 seconds
        setTimeout(() => { 
            const stack = document.getElementById('alert-stack');
            if(stack) stack.style.opacity = '0';
            setTimeout(() => stack.remove(), 500);
        }, 7000);
    </script>
</body>
</html>