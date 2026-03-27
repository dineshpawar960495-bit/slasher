<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Slasher | Secure Access</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --accent: #8b5cf6;
            --bg-dark: #020617;
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-dark);
            color: #f8fafc;
            overflow-x: hidden;
        }

        .glass { 
            background: rgba(15, 23, 42, 0.75); 
            backdrop-filter: blur(16px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
        }

        /* Smooth Form Transitions */
        .form-container {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hidden-form { 
            display: none; 
            opacity: 0;
            transform: translateY(10px);
        }

        /* OTP Input Styling */
        .otp-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
            transform: translateY(-2px);
        }

        /* Toast Animation */
        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-animate { animation: slideIn 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards; }

        /* Modal Logic */
        #otp-modal {
            transition: visibility 0s, opacity 0.4s ease;
        }
        #otp-modal.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        #modal-content {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        #otp-modal.active #modal-content { transform: scale(1); }

        /* Button Loading Pulse */
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
            position: relative;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-6">

    <div id="toast-container" class="fixed top-6 right-6 z-[100] space-y-3 pointer-events-none"></div>

    <div class="glass w-full max-w-[420px] p-10 rounded-[2.5rem] shadow-2xl relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex p-3 bg-purple-500/10 rounded-2xl mb-4">
                <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <h1 id="form-title" class="text-3xl font-black text-white tracking-tight">Welcome Back</h1>
            <p id="form-subtitle" class="text-slate-400 mt-2 text-sm font-medium">Log in to manage your recurring burn.</p>
        </div>

        <form id="login-form" class="form-container space-y-5">
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Email</label>
                <input type="email" name="email" placeholder="name@company.com" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500/50 transition-all" required>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Password</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500/50 transition-all" required>
            </div>
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-extrabold py-4 rounded-2xl transition-all shadow-lg shadow-purple-900/20 active:scale-[0.98]">
                Access Account
            </button>
            <p class="text-center text-slate-500 text-sm mt-4">New here? <a href="javascript:void(0)" id="to-register" class="text-purple-400 font-bold hover:text-purple-300 transition-colors">Create Account</a></p>
        </form>

        <form id="register-form" class="form-container space-y-5 hidden-form">
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Full Name</label>
                <input type="text" name="name" placeholder="Dinesh Pawar" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500/50 transition-all" required>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Work Email</label>
                <input type="email" name="email" placeholder="name@company.com" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500/50 transition-all" required>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1">Password</label>
                <input type="password" name="password" placeholder="Create a strong password" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white outline-none focus:ring-2 ring-purple-500/50 transition-all" required>
            </div>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold py-4 rounded-2xl transition-all shadow-lg shadow-emerald-900/20 active:scale-[0.98]">
                Get Started Free
            </button>
            <p class="text-center text-slate-500 text-sm mt-4">Already a member? <a href="javascript:void(0)" id="to-login" class="text-emerald-400 font-bold hover:text-emerald-300 transition-colors">Sign In</a></p>
        </form>
    </div>

    <div id="otp-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 backdrop-blur-md opacity-0 invisible transition-all duration-300">
        <div id="modal-content" class="glass max-w-[380px] w-full p-10 rounded-[2.5rem] shadow-2xl scale-90">
            <div class="w-20 h-20 bg-emerald-500/10 rounded-3xl flex items-center justify-center mx-auto mb-8 text-emerald-500">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L22 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h2 class="text-2xl font-black text-white text-center mb-2">Check your mail</h2>
            <p class="text-slate-400 text-sm text-center mb-8">We sent a 6-digit code. Enter it below to verify.</p>

            <form id="otp-form" class="space-y-8">
                <div class="flex justify-between gap-2" id="otp-inputs">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                    <input type="text" maxlength="1" class="otp-input w-12 h-14 bg-slate-900/50 border border-slate-800 rounded-xl text-center text-white text-xl font-black focus:ring-2 ring-purple-500 outline-none transition-all">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-2xl transition-all">Verify Code</button>
            </form>
            <button onclick="closeOTPModal()" class="mt-6 text-slate-500 text-xs w-full hover:text-slate-300">Entered wrong email? Go back</button>
        </div>
    </div>

    <script>
        // --- UI Switching ---
        const loginForm = document.getElementById('login-form');
        const regForm = document.getElementById('register-form');
        const title = document.getElementById('form-title');
        const sub = document.getElementById('form-subtitle');

        document.getElementById('to-register').onclick = () => {
            loginForm.classList.add('hidden-form');
            setTimeout(() => {
                loginForm.style.display = 'none';
                regForm.style.display = 'block';
                setTimeout(() => regForm.classList.remove('hidden-form'), 50);
                title.innerText = "Join Slasher";
                sub.innerText = "The smartest way to kill idle subscriptions.";
            }, 400);
        };

        document.getElementById('to-login').onclick = () => {
            regForm.classList.add('hidden-form');
            setTimeout(() => {
                regForm.style.display = 'none';
                loginForm.style.display = 'block';
                setTimeout(() => loginForm.classList.remove('hidden-form'), 50);
                title.innerText = "Welcome Back";
                sub.innerText = "Log in to manage your recurring burn.";
            }, 400);
        };

        // --- Toast Engine ---
        function showToast(msg, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const isSuccess = type === 'success';
            
            toast.className = `toast-animate glass flex items-center gap-3 p-4 pr-10 rounded-2xl border ${isSuccess ? 'border-emerald-500/30 text-emerald-400' : 'border-rose-500/30 text-rose-400'} shadow-2xl pointer-events-auto`;
            toast.innerHTML = `
                <div class="w-6 h-6 rounded-full bg-current/10 flex items-center justify-center text-[10px]">${isSuccess ? '✓' : '✕'}</div>
                <p class="text-sm font-bold tracking-tight">${msg}</p>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 500);
            }, 4000);
        }

        // --- Auth Logic ---
        async function handleAuth(e, action) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.innerText;
            
            btn.classList.add('btn-loading');
            btn.innerText = "Processing...";

            const formData = new FormData(e.target);
            formData.append('action', action);
            
            try {
                const res = await fetch('auth.php', { method: 'POST', body: formData });
                const text = await res.text();

                if(text.trim() === "success") {
                    showToast("Access Granted. Redirecting...");
                    setTimeout(() => window.location.href = "dashboard.php", 1200);
                } 
                else if(text.trim() === "otp_verify") {
                    showToast("Verification code sent to email!");
                    openOTPModal();
                } 
                else {
                    showToast(text, "error");
                }
            } catch (err) {
                showToast("Server connection failed", "error");
            } finally {
                btn.classList.remove('btn-loading');
                btn.innerText = originalText;
            }
        }

        loginForm.onsubmit = (e) => handleAuth(e, 'login');
        regForm.onsubmit = (e) => handleAuth(e, 'register');

        // --- OTP Logic ---
        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, i) => {
            input.oninput = () => {
                if (input.value && i < otpInputs.length - 1) otpInputs[i + 1].focus();
            };
            input.onkeydown = (e) => {
                if (e.key === 'Backspace' && !input.value && i > 0) otpInputs[i - 1].focus();
            };
        });

        document.getElementById('otp-form').onsubmit = async (e) => {
            e.preventDefault();
            const code = Array.from(otpInputs).map(i => i.value).join('');
            
            if(code.length < 6) return showToast("Enter full 6-digit code", "error");

            const fd = new FormData();
            fd.append('otp', code);
            
            const res = await fetch('verify.php', { method: 'POST', body: fd });
            const result = await res.text();

            if(result.includes("verified") || result.trim() === "success") {
                showToast("Security Verified! Launching...");
                setTimeout(() => window.location.href = "dashboard.php", 1500);
            } else {
                showToast("Invalid security code", "error");
            }
        };

        function openOTPModal() { document.getElementById('otp-modal').classList.add('active'); }
        function closeOTPModal() { document.getElementById('otp-modal').classList.remove('active'); }
    </script>
</body>
</html>