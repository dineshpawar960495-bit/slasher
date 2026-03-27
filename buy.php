<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// --- 1. GET USER WALLET DATA ---
$user = $conn->query("SELECT wallet_balance FROM users WHERE id = $user_id")->fetch_assoc();
$wallet = $user['wallet_balance'];

// --- 2. HANDLE PURCHASE LOGIC ---
if (isset($_GET['buy_id'])) {
    $item_id = (int)$_GET['buy_id'];
    
    // Static Catalog Data (In a real app, this comes from a 'products' table)
    $catalog = [
        1 => ['name' => 'Netflix Premium', 'price' => 499, 'market' => 649],
        2 => ['name' => 'Spotify Family', 'price' => 149, 'market' => 199],
        3 => ['name' => 'Gold Gym Access', 'price' => 1200, 'market' => 1800],
        4 => ['name' => 'Adobe Creative Cloud', 'price' => 999, 'market' => 1450],
        5 => ['name' => 'Cult.fit Pass', 'price' => 850, 'market' => 1100],
        6 => ['name' => 'YouTube Premium', 'price' => 99, 'market' => 129]
    ];

    if (isset($catalog[$item_id])) {
        $item = $catalog[$item_id];
        if ($wallet >= $item['price']) {
            // Deduct from Wallet
            $new_balance = $wallet - $item['price'];
            $conn->query("UPDATE users SET wallet_balance = $new_balance WHERE id = $user_id");
            
            // Add to Subscriptions table automatically
            $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, service_name, amount, category, billing_cycle, last_accessed) VALUES (?, ?, ?, 'Premium Purchase', 'Monthly', NOW())");
            $stmt->bind_param("isd", $user_id, $item['name'], $item['price']);
            $stmt->execute();

            header("Location: buy.php?purchase=success&item=" . urlencode($item['name']));
            exit();
        } else {
            header("Location: buy.php?purchase=failed");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slasher | Premium Storefront</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; color: #f8fafc; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); }
        .card-hover:hover { transform: translateY(-10px); border-color: rgba(139, 92, 246, 0.5); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5); }
        .discount-badge { background: linear-gradient(90deg, #f43f5e 0%, #fb923c 100%); }
        .buy-gradient { background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%); }
    </style>
</head>
<body class="p-4 md:p-10 pb-32">

    <?php if(isset($_GET['purchase']) && $_GET['purchase'] == 'success'): ?>
    <div id="success-popup" class="fixed inset-0 z-[500] flex items-center justify-center bg-slate-950/90 backdrop-blur-md">
        <div class="glass p-10 rounded-[3rem] text-center max-w-sm animate-bounce">
            <div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-emerald-500/20">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-2xl font-black text-white italic">Purchase Confirmed!</h2>
            <p class="text-slate-400 text-sm mt-2 mb-8">Successfully added <?php echo $_GET['item']; ?> to your active assets.</p>
            <button onclick="window.location.href='dashboard.php'" class="w-full py-4 rounded-2xl bg-white text-black font-black uppercase tracking-widest text-xs">View Dashboard</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto space-y-12">
        
        <header class="flex flex-col md:flex-row justify-between items-end gap-6">
            <div>
                <a href="dashboard.php" class="text-purple-500 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4 hover:gap-4 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7" stroke-width="3"/></svg> Return to Dashboard
                </a>
                <h1 class="text-5xl font-black italic tracking-tighter">Premium Store<span class="text-purple-500">.</span></h1>
                <p class="text-slate-500 mt-2 font-medium italic">Direct licensing with up to 25% platform discount.</p>
            </div>

            <div class="glass px-8 py-4 rounded-3xl border-purple-500/20 flex items-center gap-6">
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Available Credits</p>
                    <h3 class="text-2xl font-black text-emerald-400 italic">₹<?php echo number_format($wallet, 2); ?></h3>
                </div>
                <div class="h-10 w-[1px] bg-white/10"></div>
                <button class="bg-purple-600 text-white p-2 rounded-xl"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="3"/></svg></button>
            </div>
        </header>

        <div class="flex gap-4 overflow-x-auto pb-4 no-scrollbar">
            <button class="px-8 py-3 rounded-full bg-white text-black font-black text-xs uppercase shadow-xl">All Assets</button>
            <button class="px-8 py-3 rounded-full glass text-slate-400 font-bold text-xs uppercase hover:text-white transition-all">Entertainment</button>
            <button class="px-8 py-3 rounded-full glass text-slate-400 font-bold text-xs uppercase hover:text-white transition-all">Nearby Gyms</button>
            <button class="px-8 py-3 rounded-full glass text-slate-400 font-bold text-xs uppercase hover:text-white transition-all">Software</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <?php
            $products = [
                ['id' => 1, 'name' => 'Netflix Premium', 'cat' => 'Entertainment', 'market' => 649, 'our' => 499, 'img' => 'https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?auto=format&fit=crop&w=800&q=80'],
                ['id' => 2, 'name' => 'Spotify Family', 'cat' => 'Music', 'market' => 199, 'our' => 149, 'img' => 'https://images.unsplash.com/photo-1614680376593-902f74cf0d41?auto=format&fit=crop&w=800&q=80'],
                ['id' => 3, 'name' => 'Gold\'s Gym Plus', 'cat' => 'Fitness', 'market' => 1800, 'our' => 1200, 'img' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=800&q=80'],
                ['id' => 4, 'name' => 'Adobe Creative Cloud', 'cat' => 'Software', 'market' => 1450, 'our' => 999, 'img' => 'https://images.unsplash.com/photo-1626785774573-4b799315345d?auto=format&fit=crop&w=800&q=80'],
                ['id' => 5, 'name' => 'Cult.fit Elite', 'cat' => 'Fitness', 'market' => 1100, 'our' => 850, 'img' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?auto=format&fit=crop&w=800&q=80'],
                ['id' => 6, 'name' => 'YouTube Premium', 'cat' => 'Entertainment', 'market' => 129, 'our' => 99, 'img' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?auto=format&fit=crop&w=800&q=80']
            ];

            foreach($products as $p):
                $discount = round((($p['market'] - $p['our']) / $p['market']) * 100);
            ?>
            <div class="glass rounded-[3rem] overflow-hidden card-hover transition-all duration-500 flex flex-col border-white/5 relative">
                
                <div class="absolute top-6 left-6 z-10 discount-badge px-4 py-1.5 rounded-full text-[10px] font-black text-white shadow-xl shadow-rose-500/20">
                    SAVE <?php echo $discount; ?>%
                </div>

                <div class="h-56 w-full relative overflow-hidden">
                    <img src="<?php echo $p['img']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 opacity-60">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#020617] to-transparent"></div>
                </div>

                <div class="p-8 flex flex-col flex-1">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="text-[9px] font-black text-purple-400 uppercase tracking-[0.2em]"><?php echo $p['cat']; ?></span>
                            <h4 class="text-2xl font-black text-white italic tracking-tighter mt-1"><?php echo $p['name']; ?></h4>
                        </div>
                    </div>

                    <div class="bg-white/5 rounded-3xl p-5 mb-8 border border-white/5">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-slate-500 text-xs font-bold">Standard Price</span>
                            <span class="text-slate-500 text-xs font-black line-through">₹<?php echo $p['market']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-white text-sm font-black italic tracking-tight">Slasher Offer</span>
                            <span class="text-emerald-400 text-xl font-black italic tracking-tighter">₹<?php echo $p['our']; ?></span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="buy.php?buy_id=<?php echo $p['id']; ?>" class="w-full flex items-center justify-center gap-3 buy-gradient text-white font-black py-5 rounded-[2rem] shadow-xl shadow-purple-500/20 hover:scale-[1.02] transition-all active:scale-95 text-xs uppercase tracking-widest">
                            Instant Checkout
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6" stroke-width="3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

    </div>

    <nav class="lg:hidden fixed bottom-8 left-1/2 -translate-x-1/2 w-[90%] max-w-[420px] z-[150]">
        <div class="glass flex items-center justify-around p-6 rounded-[3rem] shadow-2xl border border-white/10 relative">
            <a href="dashboard.php" class="text-slate-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
            <a href="buy.php" class="text-purple-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></a>
            <a href="logout.php" class="text-slate-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg></a>
        </div>
    </nav>

</body>
</html>