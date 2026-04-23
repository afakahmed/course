<?php
// ─── SMTP CONFIGURATION ─────────────────────────
$smtp_host = 'smtp.gmail.com'; 
$smtp_port = 465; 
$smtp_user = 'saiyedkhan207@gmail.com'; 
$smtp_pass = 'cscv jpsk gbnh fmxl';          
// ──────────────────────────────────────────────────────────────────────────

function send_smtp_mail($to, $subject, $body, $user, $pass, $host, $port, $from_name) {
    $context = stream_context_create([
        'ssl' =>[
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) return "Connection failed: $errstr ($errno)";

    $read_server = function($socket) { 
        $response = "";
        while($str = fgets($socket, 515)) {
            $response .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        return $response;
    };
    
    $read_server($socket);
    fputs($socket, "EHLO {$host}\r\n"); $read_server($socket);
    fputs($socket, "AUTH LOGIN\r\n"); $read_server($socket);
    fputs($socket, base64_encode($user) . "\r\n"); $read_server($socket);
    fputs($socket, base64_encode($pass) . "\r\n"); $read_server($socket); 
    fputs($socket, "MAIL FROM: <{$user}>\r\n"); $read_server($socket);
    fputs($socket, "RCPT TO: <{$to}>\r\n"); $read_server($socket);
    fputs($socket, "DATA\r\n"); $read_server($socket);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$user}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$subject}\r\n";

    fputs($socket, "$headers\r\n$body\r\n.\r\n"); 
    $read_server($socket);
    fputs($socket, "QUIT\r\n"); 
    fclose($socket);

    return true; 
}

// ─── PHP: Handle Payment Submission ───────────────────────────────────────────
$message_status = '';
$message_type   = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payment') {
    $recipient_email  = filter_var(trim($_POST['recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $recipient_name   = htmlspecialchars(trim($_POST['recipient_name']  ?? ''));
    $amount           = floatval($_POST['amount'] ?? 0);
    $note             = htmlspecialchars(trim($_POST['note'] ?? ''));
    $sender_name      = htmlspecialchars(trim($_POST['sender_name']  ?? 'Wallet User'));
    $sender_email     = filter_var(trim($_POST['sender_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $tx_id            = number_format(mt_rand(100000000000, 999999999999), 0, '', '');
    $timestamp        = date('Y-m-d H:i:s T');
 
    if (!$recipient_email) {
        $message_status = 'Invalid recipient email address.';
        $message_type   = 'error';
    } elseif ($amount <= 0) {
        $message_status = 'Amount must be greater than zero.';
        $message_type   = 'error';
    } else {
        // ── Email Template to Recipient ───────────────────────────────────────
        $subject_to = "You received {$amount} Points via JavaGoat Wallet";
        $body_to = "
        <!DOCTYPE html>
        <html>
        <body style='margin:0;padding:0;background:#f3f4f6;font-family:Georgia,serif;'>
          <div style='max-width:560px;margin:40px auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
            <div style='background:linear-gradient(135deg,#6366f1 0%,#a855f7 50%,#ec4899 100%);padding:40px 32px;text-align:center;'>
              <div style='font-size:48px;margin-bottom:8px;color:#ffffff;'>◈</div>
              <h1 style='color:#ffffff;font-size:22px;letter-spacing:0.15em;font-weight:600;margin:0;text-transform:uppercase;'>JavaGoat Wallet</h1>
            </div>
            <div style='padding:36px 32px;'>
              <p style='color:#64748b;font-size:13px;text-transform:uppercase;letter-spacing:0.12em;margin:0 0 4px;'>Points Received</p>
              <div style='font-size:48px;font-weight:700;color:#0f172a;margin:8px 0 24px;'>" . number_format($amount, 2) . " <span style='font-size:18px;color:#64748b;'>Pts</span></div>
              <hr style='border:none;border-top:1px solid #e2e8f0;margin:0 0 24px;'/>
              <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='padding:8px 0;color:#64748b;font-size:13px;width:40%;'>From</td><td style='padding:8px 0;color:#0f172a;font-size:14px;font-weight:500;'>{$sender_name}</td></tr>
                <tr><td style='padding:8px 0;color:#64748b;font-size:13px;'>To</td><td style='padding:8px 0;color:#0f172a;font-size:14px;font-weight:500;'>{$recipient_name}</td></tr>
                <tr><td style='padding:8px 0;color:#64748b;font-size:13px;'>Transaction ID</td><td style='padding:8px 0;color:#6366f1;font-family:monospace;font-size:13px;letter-spacing:0.08em;font-weight:600;'>TXN {$tx_id}</td></tr>
                <tr><td style='padding:8px 0;color:#64748b;font-size:13px;'>Date & Time</td><td style='padding:8px 0;color:#0f172a;font-size:13px;'>{$timestamp}</td></tr>
                " . ($note ? "<tr><td style='padding:8px 0;color:#64748b;font-size:13px;vertical-align:top;'>Note</td><td style='padding:8px 0;color:#0f172a;font-size:14px;font-style:italic;'>&ldquo;{$note}&rdquo;</td></tr>" : "") . "
              </table>
            </div>
          </div>
        </body>
        </html>";
 
        // ── Send to Recipient ───────────────────────────────────────────────
        $sent_recipient = send_smtp_mail($recipient_email, $subject_to, $body_to, $smtp_user, $smtp_pass, $smtp_host, $smtp_port, "JavaGoat Wallet");
 
        // ── Confirmation Email to Sender ────────────────────────────────────
        if ($sent_recipient === true) {
            if ($sender_email) {
                $subject_from = "Points Sent: {$amount} Pts - Transaction ID: {$tx_id}";
                $body_from = "
                <!DOCTYPE html>
                <html>
                <body style='margin:0;padding:0;background:#f3f4f6;font-family:Georgia,serif;'>
                  <div style='max-width:560px;margin:40px auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
                    <div style='background:linear-gradient(135deg,#6366f1 0%,#a855f7 50%,#ec4899 100%);padding:40px 32px;text-align:center;'>
                      <h1 style='color:#ffffff;font-size:22px;letter-spacing:0.15em;font-weight:600;margin:0;text-transform:uppercase;'>JavaGoat Wallet</h1>
                    </div>
                    <div style='padding:36px 32px;'>
                      <p style='color:#64748b;font-size:13px;text-transform:uppercase;letter-spacing:0.12em;margin:0 0 4px;'>Payment Sent ✓</p>
                      <div style='font-size:48px;font-weight:700;color:#0f172a;margin:8px 0 24px;'>" . number_format($amount, 2) . " <span style='font-size:18px;color:#64748b;'>Pts</span></div>
                      <hr style='border:none;border-top:1px solid #e2e8f0;margin:0 0 24px;'/>
                      <table style='width:100%;border-collapse:collapse;'>
                        <tr><td style='padding:8px 0;color:#64748b;font-size:13px;width:40%;'>To</td><td style='padding:8px 0;color:#0f172a;font-size:14px;font-weight:500;'>{$recipient_name}</td></tr>
                        <tr><td style='padding:8px 0;color:#64748b;font-size:13px;'>Transaction ID</td><td style='padding:8px 0;color:#6366f1;font-family:monospace;font-size:13px;font-weight:600;'>TXN {$tx_id}</td></tr>
                      </table>
                    </div>
                  </div>
                </body>
                </html>";
                send_smtp_mail($sender_email, $subject_from, $body_from, $smtp_user, $smtp_pass, $smtp_host, $smtp_port, "JavaGoat Receipt");
            }
            $message_status = "TXN {$tx_id}";
            $message_type   = 'success';
        } else {
            $message_status = "Mail Error: " . $sent_recipient;
            $message_type   = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>JavaGoat Wallet</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Mono:wght@300;400;500;600&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<!-- HTML5 QR Code Library -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
/* ─── Reset & Light Theme Variables ──────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:         #f0fdfa; /* Light base */
  --surface:    #ffffff;
  --surface2:   #f8fafc;
  --border:     #e2e8f0;
  --border2:    #cbd5e1;
  
  /* Vibrant Accents */
  --primary:    #6366f1; /* Indigo */
  --primary-hover:#4f46e5;
  --secondary:  #ec4899; /* Pink */
  --accent:     #a855f7; /* Purple */
  
  --text:       #0f172a; /* Dark text */
  --muted:      #64748b;
  --muted2:     #94a3b8;
  
  --success:    #10b981;
  --success-bg: #d1fae5;
  --error:      #ef4444;
  --error-bg:   #fee2e2;
  
  --font-serif: 'Playfair Display', Georgia, serif;
  --font-mono:  'DM Mono', monospace;
  --font-sans:  'DM Sans', sans-serif;
  
  --radius:     16px;
  --radius-sm:  10px;
  
  --shadow-sm:  0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow:     0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
  --shadow-lg:  0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
}

body{
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-sans);
  min-height: 100vh;
  overflow-x: hidden;
  position: relative;
}

/* ─── Animated Colorful Background Blobs ─────────────────────────────────── */
.bg-shapes {
  position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
  background-color: #f1f5f9; /* Soft backing */
}
.bg-shapes::before, .bg-shapes::after {
  content: ''; position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.6;
}
.bg-shapes::before {
  width: 450px; height: 450px; background: #c7d2fe; /* Soft indigo */
  top: -100px; left: -100px; animation: float1 12s infinite alternate ease-in-out;
}
.bg-shapes::after {
  width: 500px; height: 500px; background: #fce7f3; /* Soft pink */
  bottom: -150px; right: -100px; animation: float2 14s infinite alternate ease-in-out;
}
@keyframes float1 { 100% { transform: translate(80px, 80px); } }
@keyframes float2 { 100% { transform: translate(-80px, -80px); } }

/* ─── Navigation & SPA Layout ───────────────────────────────────────────── */
.top-nav {
    position: relative; z-index: 10;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-sm);
}
.nav-brand {
    font-family: var(--font-serif);
    font-size: 22px; color: var(--text); font-weight: 700;
    display: flex; align-items: center; gap: 8px;
}
.nav-brand span { font-size: 26px; color: var(--primary); }
.nav-links { display: flex; gap: 15px; }
.nav-link {
    color: var(--muted); font-size: 15px; font-weight: 600;
    cursor: pointer; padding: 8px 16px; border-radius: 20px;
    transition: all 0.3s ease;
}
.nav-link:hover { color: var(--primary); background: var(--surface2); }
.nav-link.active { color: #fff; background: var(--text); box-shadow: var(--shadow); }

.page {
  position: relative; z-index: 1;
  max-width: 700px;
  margin: 40px auto;
  padding: 0 20px 60px;
}
.view-section { display: none; animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.view-section.active { display: block; }

@keyframes fadeIn { from{opacity:0; transform:translateY(15px);} to{opacity:1; transform:translateY(0);} }

/* ─── Shared UI Elements ────────────────────────────────────────────────── */
.section-label { 
  font-family: var(--font-mono); font-size: 11px; font-weight: 600;
  letter-spacing: .2em; color: var(--muted); text-transform: uppercase; 
  margin-bottom: 24px; display: flex; align-items: center; gap: 12px; 
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.panel { 
  background: var(--surface); border: 1px solid var(--border); 
  border-radius: var(--radius); padding: 32px; margin-bottom: 25px;
  box-shadow: var(--shadow-lg);
}

/* ─── Vibrant Wallet Card ─────────────────────────────────────────────────── */
.wallet-card { 
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
  border: none; border-radius: 24px; padding: 36px; position: relative; 
  overflow: hidden; margin-bottom: 24px;
  box-shadow: 0 15px 35px -5px rgba(168, 85, 247, 0.4);
  color: #fff;
}
/* Abstract Overlay pattern inside wallet card */
.wallet-card::before {
  content:''; position:absolute; top:0; right:0; width:300px; height:300px;
  background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 60%);
  transform: translate(30%, -30%); pointer-events: none;
}
.balance-label { 
  font-family: var(--font-mono); font-size: 11px; font-weight: 500;
  letter-spacing: .15em; color: rgba(255,255,255,0.8); text-transform: uppercase; margin-bottom: 8px; 
}
.balance-amount { 
  font-family: var(--font-serif); font-size: 52px; font-weight: 700; 
  color: #fff; line-height: 1; margin-bottom: 4px; 
  text-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.balance-currency { font-size: 18px; font-weight: 500; color: rgba(255,255,255,0.85); margin-left: 8px; font-family: var(--font-sans); }
.wallet-meta { 
  display: grid; grid-template-columns: 1fr 1fr; gap: 14px; 
  border-top: 1px solid rgba(255,255,255,0.2); padding-top: 24px; margin-top: 24px; 
}
.meta-key { font-size: 10px; font-family: var(--font-mono); color: rgba(255,255,255,0.7); font-weight: 600; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 6px; }
.meta-val { font-size: 14px; color: #fff; font-family: var(--font-mono); font-weight: 500; }
.meta-val.chip { 
  background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);
  border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; padding: 4px 10px; 
  display: inline-block; color: #fff; font-size: 11px; font-weight: 600; 
}

/* ─── Stats ────────────────────────────────────────────────────────────── */
.stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 25px; }
.stat-card { 
  background: var(--surface); border: 1px solid var(--border); 
  border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow);
  transition: transform 0.3s ease;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-num { font-family: var(--font-mono); font-size: 24px; color: var(--text); font-weight: 600; margin-bottom: 4px; }
.stat-desc { font-size: 12px; color: var(--muted); font-weight: 500; letter-spacing: .05em; text-transform: uppercase; }

/* ─── Recent Transactions ────────────────────────────────────────────────── */
.tx-list { display: flex; flex-direction: column; gap: 1px; }
.tx-item { display: flex; align-items: center; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--border); }
.tx-item:last-child { border-bottom: none; }
.tx-avatar { 
  width: 44px; height: 44px; border-radius: 12px; 
  display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; 
}
.tx-item:nth-child(1) .tx-avatar { background: #e0e7ff; color: #4f46e5; }
.tx-item:nth-child(2) .tx-avatar { background: #dcfce7; color: #15803d; }
.tx-info { flex: 1; }
.tx-name { font-size: 15px; color: var(--text); font-weight: 600; }
.tx-date { font-size: 12px; color: var(--muted); font-family: var(--font-sans); margin-top: 4px; }
.tx-amount { font-family: var(--font-mono); font-size: 15px; font-weight: 600; }
.tx-amount.out { color: var(--error); }
.tx-amount.in { color: var(--success); }

/* ─── Forms & Buttons ────────────────────────────────────────────────────── */
.field { margin-bottom: 20px; }
label { 
  display: block; font-size: 12px; font-weight: 600; 
  font-family: var(--font-sans); color: var(--text); 
  margin-bottom: 8px; 
}
input, textarea { 
  width: 100%; background: var(--surface); border: 2px solid var(--border); 
  border-radius: var(--radius-sm); color: var(--text); 
  font-family: var(--font-sans); font-size: 15px; font-weight: 500;
  padding: 14px 16px; outline: none; transition: all 0.2s ease;
}
input::placeholder, textarea::placeholder { color: var(--muted2); font-weight: 400;}
input:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.15); }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.amount-wrap { position: relative; }
.amount-prefix { 
  position: absolute; right: 16px; top: 50%; transform: translateY(-50%); 
  font-family: var(--font-mono); font-size: 16px; font-weight: 600; 
  color: var(--primary); pointer-events: none; 
}
.amount-wrap input { 
  padding-right: 60px; font-size: 24px; font-family: var(--font-mono); 
  color: var(--primary); font-weight: 600; 
}

.btn-send { 
  width: 100%; padding: 18px; 
  background: linear-gradient(135deg, var(--primary), var(--accent)); 
  border: none; border-radius: var(--radius-sm); color: #fff; 
  font-family: var(--font-sans); font-size: 16px; font-weight: 700; letter-spacing: .05em; 
  cursor: pointer; box-shadow: 0 6px 20px rgba(99,102,241,.3); 
  margin-top: 10px; transition: all 0.3s ease;
}
.btn-send:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(99,102,241,.4); }

.btn-scan { 
  background: #e0e7ff; color: var(--primary); 
  border: 2px dashed #a5b4fc; border-radius: var(--radius-sm); 
  padding: 14px; width: 100%; cursor: pointer; 
  font-family: var(--font-sans); font-size: 14px; font-weight: 600; 
  margin-bottom: 24px; transition: all 0.2s ease;
}
.btn-scan:hover { background: #c7d2fe; border-color: var(--primary); }
#reader-container { margin-bottom: 24px; border-radius: 12px; overflow: hidden; background: #fff; border: 1px solid var(--border); display: none; box-shadow: var(--shadow); }
#reader { width: 100%; }
.btn-cancel { background: var(--error); color: white; padding: 12px; width: 100%; border: none; cursor: pointer; font-weight: 700; font-family: var(--font-sans);}

/* ─── Status & Receipts ──────────────────────────────────────────────────── */
.toast { display: flex; align-items: flex-start; gap: 14px; padding: 18px 20px; border-radius: var(--radius-sm); border: 1px solid; margin-bottom: 24px; box-shadow: var(--shadow-sm);}
.toast.success { background: var(--success-bg); border-color: rgba(16,185,129,.3); color: #065f46; }
.toast.error  { background: var(--error-bg); border-color: rgba(239,68,68,.3); color: #991b1b; }
.toast-title { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
.toast-msg { font-size: 13px; opacity: .9; font-family: var(--font-mono); font-weight: 500;}

.receipt { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 25px; box-shadow: var(--shadow-lg);}
.receipt-header { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 20px; display: flex; align-items: center; justify-content: space-between; }
.receipt-title { font-family: var(--font-serif); color: var(--text); font-size: 18px; font-weight: 700; }
.receipt-badge { background: var(--success-bg); border: 1px solid rgba(16,185,129,.3); color: var(--success); font-family: var(--font-mono); font-weight: 600; font-size: 11px; padding: 6px 12px; border-radius: 20px; letter-spacing: .05em; }
.receipt-body { padding: 24px; }
.receipt-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border2); font-size: 14px; }
.receipt-row:last-child { border-bottom: none; padding-bottom: 0;}
.receipt-row:first-child { padding-top: 0; }
.receipt-key { color: var(--muted); font-weight: 500; font-size: 13px; }
.receipt-val { color: var(--text); font-family: var(--font-mono); font-weight: 600; font-size: 13px; text-align: right; }
.receipt-val.gold { color: var(--primary); font-size: 18px; font-weight: 700; }
.receipt-val.tx-id { color: var(--accent); background: #f3e8ff; padding: 4px 8px; border-radius: 6px; }

@media(max-width:760px){
  .top-nav { flex-direction: column; gap: 15px; padding: 15px; }
  .field-row { grid-template-columns: 1fr; gap: 0; }
  .wallet-card { padding: 25px; }
  .balance-amount { font-size: 40px; }
}
</style>
</head>
<body>
<!-- Colorful animated abstract background blobs -->
<div class="bg-shapes"></div>

<!-- Top Navigation -->
<nav class="top-nav">
  <div class="nav-brand"><span>◈</span> JavaGoat Wallet</div>
  <div class="nav-links">
    <a class="nav-link active" onclick="switchView('dashboard')" id="nav-dashboard">Dashboard</a>
    <a class="nav-link" onclick="switchView('send')" id="nav-send">Send Points</a>
    <a class="nav-link" onclick="switchView('history')" id="nav-history">History</a>
  </div>
</nav>

<div class="page">
  
  <!-- ================== VIEW 1: DASHBOARD ================== -->
  <div id="view-dashboard" class="view-section active">
    <div class="wallet-card">
      <div class="balance-label">Total Points Balance</div>
      <div class="balance-amount" id="display-balance">12,480<span class="balance-currency">Pts</span></div>
      <div class="wallet-meta">
        <div>
          <div class="meta-key">Account ID</div>
          <div class="meta-val">JG-4829</div>
        </div>
        <div>
          <div class="meta-key">Status</div>
          <div class="meta-val chip">● Verified</div>
        </div>
      </div>
    </div>
    
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-num">4,200</div>
        <div class="stat-desc">Points Earned</div>
      </div>
      <div class="stat-card">
        <div class="stat-num">1,840</div>
        <div class="stat-desc">Points Spent</div>
      </div>
    </div>
  </div>

  <!-- ================== VIEW 2: SEND POINTS ================== -->
  <div id="view-send" class="view-section">
    
    <!-- Status Toast / Receipts -->
    <?php if ($message_status): ?>
      <div class="toast <?= $message_type ?>">
        <div class="toast-body">
          <?php if($message_type==='success'): ?>
            <div class="toast-title">Points Sent Successfully ✅</div>
            <div class="toast-msg">Transaction ID: <?= htmlspecialchars($message_status) ?></div>
          <?php else: ?>
            <div class="toast-title">Transaction Failed ❌</div>
            <div class="toast-msg"><?= htmlspecialchars($message_status) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($message_type === 'success'): ?>
      <div class="receipt">
        <div class="receipt-header">
          <span class="receipt-title">Payment Receipt</span>
          <span class="receipt-badge">CONFIRMED</span>
        </div>
        <div class="receipt-body">
          <div class="receipt-row">
            <span class="receipt-key">Transaction ID</span>
            <span class="receipt-val tx-id">TXN <?= htmlspecialchars(explode('-', $message_status)[0] ?? '') ?><?= htmlspecialchars(substr($message_status, strpos($message_status,'-')+1)) ?></span>
          </div>
          <div class="receipt-row">
            <span class="receipt-key">Points Sent</span>
            <span class="receipt-val gold"><?= number_format(floatval($_POST['amount']),2) ?> Pts</span>
          </div>
          <div class="receipt-row">
            <span class="receipt-key">Recipient</span>
            <span class="receipt-val"><?= htmlspecialchars($_POST['recipient_name'] ?? '') ?></span>
          </div>
          <div class="receipt-row">
            <span class="receipt-key">Email</span>
            <span class="receipt-val"><?= htmlspecialchars($_POST['recipient_email'] ?? '') ?></span>
          </div>
          <div class="receipt-row">
            <span class="receipt-key">Timestamp</span>
            <span class="receipt-val"><?= $timestamp ?></span>
          </div>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="panel">
      <div class="section-label">Transfer Points</div>

      <!-- QR Scanner Trigger -->
      <button type="button" class="btn-scan" onclick="startScanner()">📷 Scan Checkout QR Code</button>
      <div id="reader-container">
        <div id="reader"></div>
        <button type="button" class="btn-cancel" onclick="stopScanner()">Cancel Scanner</button>
      </div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="send_payment"/>

        <div class="field">
          <label>Amount (Points)</label>
          <div class="amount-wrap">
            <input type="number" name="amount" id="amount-field" min="1" step="0.01" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" placeholder="0.00" required/>
            <span class="amount-prefix">Pts</span>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Your Name</label>
            <input type="text" name="sender_name" value="<?= htmlspecialchars($_POST['sender_name'] ?? 'John Doe') ?>" required/>
          </div>
          <div class="field">
            <label>Your Email</label>
            <input type="email" name="sender_email" value="<?= htmlspecialchars($_POST['sender_email'] ?? 'johndoe@email.com') ?>"/>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Recipient Name</label>
            <input type="text" id="recipient_name" name="recipient_name" value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>" placeholder="e.g. Java Goat" required/>
          </div>
          <div class="field">
            <label>Recipient Email</label>
            <input type="email" id="recipient_email" name="recipient_email" value="<?= htmlspecialchars($_POST['recipient_email'] ?? '') ?>" placeholder="recipient@email.com" required/>
          </div>
        </div>

        <div class="field">
          <label>Transaction Note</label>
          <textarea id="note" name="note" placeholder="What is this for?"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-send" id="submit-btn">Send Points →</button>
      </form>
    </div>
  </div>

  <!-- ================== VIEW 3: HISTORY ================== -->
  <div id="view-history" class="view-section">
    <div class="panel">
      <div class="section-label">Recent Transactions</div>
      <div class="tx-list">
        <div class="tx-item">
          <div class="tx-avatar">📚</div>
          <div class="tx-info">
            <div class="tx-name">JavaGoat Course Checkout</div>
            <div class="tx-date">Just now</div>
          </div>
          <div class="tx-amount out">-249.00 Pts</div>
        </div>
        <div class="tx-item">
          <div class="tx-avatar">👤</div>
          <div class="tx-info">
            <div class="tx-name">Admin Rewards</div>
            <div class="tx-date">Yesterday · 09:15</div>
          </div>
          <div class="tx-amount in">+500.00 Pts</div>
        </div>
      </div>
    </div>
  </div>

</div>
 
<script>
// Tab Switching Logic
function switchView(viewId) {
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
    document.getElementById('view-' + viewId).classList.add('active');
    document.getElementById('nav-' + viewId).classList.add('active');
}

// Check if PHP handled a form POST so we stay on the 'send' tab to show receipts
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    switchView('send');
<?php else: ?>
    switchView('dashboard');
<?php endif; ?>

// Live balance visual deduction
const amountField = document.getElementById('amount-field');
if (amountField) {
  amountField.addEventListener('input', function() {
    const v = parseFloat(this.value) || 0;
    const base = 12480;
    const remaining = base - v;
    if (v > 0 && remaining >= 0) {
      document.getElementById('display-balance').innerHTML =
        `${remaining.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}<span class="balance-currency">Pts</span>`;
    } else {
      document.getElementById('display-balance').innerHTML = `12,480<span class="balance-currency">Pts</span>`;
    }
  });
}

// Disable button on submit
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('submit-btn');
  btn.innerHTML = 'Processing... ⏳';
  btn.disabled = true;
});

// HTML5 QR Scanner Logic
let html5QrCode;

function startScanner() {
    document.getElementById('reader-container').style.display = 'block';
    if (!html5QrCode) { html5QrCode = new Html5Qrcode("reader"); }
    
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText, decodedResult) => {
            try {
                // Expecting JSON from the JavaGoat dynamically generated QR
                let data = JSON.parse(decodedText);
                
                document.getElementById('recipient_email').value = data.email || '';
                document.getElementById('recipient_name').value = data.name || '';
                document.getElementById('amount-field').value = data.amount || '';
                document.getElementById('note').value = data.txn_name || '';
                
                stopScanner();
                alert("QR Code scanned & details filled successfully!");
                amountField.dispatchEvent(new Event('input')); // Trigger balance deduction preview
            } catch(e) {
                console.error("Not a valid JSON format", e);
                alert("Invalid QR format detected. Please scan a valid JavaGoat Checkout QR.");
            }
        },
        (errorMessage) => { /* Background scan err */ }
    ).catch((err) => {
        alert("Camera initialization error: " + err);
        document.getElementById('reader-container').style.display = 'none';
    });
}

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            document.getElementById('reader-container').style.display = 'none';
        }).catch(err => {
            console.error("Failed to stop scanner", err);
        });
    }
}
</script>
</body>
</html>
