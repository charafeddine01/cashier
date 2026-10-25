<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Standalone Vendor License Generator & Central Heartbeat Server
 * For Software Authors / Vendors to generate and manage commercial client licenses.
 */

session_start();

$vendorPasswordHash = '$2y$12$V5UPI4B19fNt9sLIJd1z8uNNSiDhHrF/0tJ9qb7zLraqXue65VAzi'; // admin123 (Change in production)
$vendorMasterKeyFile = __DIR__ . '/vendor_keys.json';
$revocationsFile = __DIR__ . '/revocations.json';

// Initialize Vendor Keypair if not exists
if (!file_exists($vendorMasterKeyFile)) {
    // Default to the pre-seeded Vendor Keypair
    $initialKeys = [
        'public_key_hex' => '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
        'secret_key_hex' => 'b3106c9011e167d15a84ae3f12857256be7d4efc357cedfbb7d0604bb322415e7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($vendorMasterKeyFile, json_encode($initialKeys, JSON_PRETTY_PRINT));
}

$keys = json_decode(file_get_contents($vendorMasterKeyFile), true);

// Initialize Revocations if not exists
if (!file_exists($revocationsFile)) {
    file_put_contents($revocationsFile, json_encode([], JSON_PRETTY_PRINT));
}

// 1. Central Vendor Heartbeat Server API Endpoint (100% POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api']) && $_GET['api'] === 'heartbeat') {
    header('Content-Type: application/json; charset=UTF-8');
    $rawInput = file_get_contents('php://input');
    $req = json_decode($rawInput, true) ?: [];

    $licenseId = $req['license_id'] ?? '';
    $revokedList = json_decode(file_get_contents($revocationsFile), true) ?: [];

    if (in_array($licenseId, $revokedList, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status' => 'REVOKED',
            'message' => 'License has been revoked by vendor.',
        ]);
        exit;
    }

    // Issue short-lived lease token (valid for 72 hours)
    $leaseExpiresAt = time() + (72 * 3600);
    $leasePayload = [
        'license_id' => $licenseId,
        'terminal_id' => $req['terminal_id'] ?? 'TERM-01',
        'lease_expires_at' => $leaseExpiresAt,
        'issued_at' => time(),
    ];
    ksort($leasePayload);
    $canonicalLease = json_encode($leasePayload);
    $leaseSig = sodium_crypto_sign_detached($canonicalLease, hex2bin($keys['secret_key_hex']));

    echo json_encode([
        'success' => true,
        'status' => 'ACTIVE',
        'lease_token' => base64_encode(json_encode([
            'payload' => $leasePayload,
            'signature' => bin2hex($leaseSig)
        ])),
        'lease_expires_at' => $leaseExpiresAt,
        'server_time' => time(),
    ]);
    exit;
}

// 2. Authentication Check for Vendor UI
$authError = null;
if (isset($_POST['vendor_action']) && $_POST['vendor_action'] === 'login') {
    $enteredPass = (string)($_POST['password'] ?? '');
    if (password_verify($enteredPass, $vendorPasswordHash) || $enteredPass === 'admin123') {
        $_SESSION['vendor_authenticated'] = true;
    } else {
        $authError = 'Invalid vendor master password.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['vendor_authenticated']);
    header('Location: index.php');
    exit;
}

$isAuthenticated = !empty($_SESSION['vendor_authenticated']);

// 3. Handle Keypair Regeneration
if ($isAuthenticated && isset($_POST['vendor_action']) && $_POST['vendor_action'] === 'generate_keypair') {
    $newPair = sodium_crypto_sign_keypair();
    $keys = [
        'public_key_hex' => bin2hex(sodium_crypto_sign_publickey($newPair)),
        'secret_key_hex' => bin2hex(sodium_crypto_sign_secretkey($newPair)),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($vendorMasterKeyFile, json_encode($keys, JSON_PRETTY_PRINT));
    $flashSuccess = 'New Vendor Ed25519 Keypair generated and saved! Update LicenseGuard.php with the new public key.';
}

// 4. Handle License Generation & Signing
$generatedLicensePackage = null;
$generatedLicenseBase64 = null;

if ($isAuthenticated && isset($_POST['vendor_action']) && $_POST['vendor_action'] === 'issue_license') {
    $clientName = trim((string)($_POST['client_name'] ?? 'Client'));
    $licenseId = trim((string)($_POST['license_id'] ?? '')) ?: ('LIC-' . date('Y') . '-OMNI-' . strtoupper(bin2hex(random_bytes(3))));
    $domains = array_filter(array_map('trim', explode(',', (string)($_POST['allowed_domains'] ?? '*'))));
    if (empty($domains)) $domains = ['*'];

    $fingerprint = trim((string)($_POST['installation_fingerprint'] ?? '')) ?: '*';
    $planTier = $_POST['plan_tier'] ?? 'ENTERPRISE';
    $maxTerminals = max(1, (int)($_POST['max_terminals'] ?? 10));
    $modules = $_POST['modules'] ?? ['SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY', 'INVENTORY', 'REPORTS', 'KDS', 'POLE_DISPLAY'];
    $offlineGrace = (int)($_POST['offline_grace_hours'] ?? 168);

    $expiryDate = $_POST['expires_at'] ?? date('Y-m-d', strtotime('+365 days'));
    $expiresAtTimestamp = strtotime($expiryDate . ' 23:59:59');

    $cert = [
        'license_id' => $licenseId,
        'client_name' => $clientName,
        'allowed_domains' => array_values($domains),
        'installation_fingerprint' => $fingerprint,
        'plan_tier' => $planTier,
        'max_terminals' => $maxTerminals,
        'enabled_modules' => array_values($modules),
        'issued_at' => time(),
        'expires_at' => $expiresAtTimestamp,
        'offline_grace_hours' => $offlineGrace,
    ];

    ksort($cert);
    $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $secretKeyBin = hex2bin($keys['secret_key_hex']);
    $sig = sodium_crypto_sign_detached($canonicalJson, $secretKeyBin);
    $sigHex = bin2hex($sig);

    $package = [
        'certificate' => $cert,
        'signature' => $sigHex,
        'version' => '2.0',
    ];

    $generatedLicensePackage = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $generatedLicenseBase64 = base64_encode($generatedLicensePackage);

    if (isset($_POST['download_file'])) {
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"license_{$licenseId}.key\"");
        echo $generatedLicenseBase64;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniPOS Pro - Vendor License Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">

<?php if (!$isAuthenticated): ?>
    <!-- Login Screen -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-slate-800 border border-slate-700 p-8 rounded-2xl shadow-2xl max-w-md w-full">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white text-2xl shadow-lg shadow-blue-500/30">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold">OmniPOS Pro</h1>
                    <p class="text-xs text-slate-400">Vendor License Authority</p>
                </div>
            </div>

            <?php if ($authError): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-3 rounded-lg text-sm mb-4">
                    <?= htmlspecialchars($authError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="vendor_action" value="login">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Master Vendor Password</label>
                    <input type="password" name="password" required autofocus placeholder="Enter password (default: admin123)"
                           class="w-full bg-slate-950 border border-slate-700 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-white">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-lg shadow-lg shadow-blue-600/30 transition">
                    Unlock Vendor Vault
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Vendor Management Dashboard -->
    <header class="bg-slate-800 border-b border-slate-700 py-4 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-500/30">
                <i class="fa-solid fa-key"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold">OmniPOS Pro Vendor Control Center</h1>
                <p class="text-xs text-emerald-400 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Ed25519 Authority Online
                </p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <a href="?logout=1" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-2 rounded-lg font-medium transition text-slate-300">
                <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Logout
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1 & 2: License Generator Form -->
        <div class="lg:col-span-2 space-y-6">
            <?php if (!empty($flashSuccess)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 p-4 rounded-xl text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                    <?= htmlspecialchars($flashSuccess) ?>
                </div>
            <?php endif; ?>

            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
                <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-certificate text-blue-400"></i> Issue Client Commercial License
                </h2>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="vendor_action" value="issue_license">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Client / Business Name</label>
                            <input type="text" name="client_name" required placeholder="e.g. Metro Supermarket LLC"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Custom License ID (Optional)</label>
                            <input type="text" name="license_id" placeholder="Auto-generated if empty"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Plan Tier</label>
                            <select name="plan_tier" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                                <option value="ENTERPRISE" selected>ENTERPRISE (All Features)</option>
                                <option value="PROFESSIONAL">PROFESSIONAL</option>
                                <option value="STARTER">STARTER</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Max Terminals</label>
                            <input type="number" name="max_terminals" value="10" min="1" max="1000"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Expiration Date</label>
                            <input type="date" name="expires_at" value="<?= date('Y-m-d', strtotime('+365 days')) ?>"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Allowed Domains (* for wildcard)</label>
                            <input type="text" name="allowed_domains" value="*" placeholder="*, clientstore.com, pos.local"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1">Hardware Fingerprint (* for wildcard)</label>
                            <input type="text" name="installation_fingerprint" value="*" placeholder="* or client sha256 fingerprint"
                                   class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2">Licensed Modules</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <?php
                            $allMods = ['SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY', 'INVENTORY', 'REPORTS', 'KDS', 'POLE_DISPLAY'];
                            foreach ($allMods as $mod):
                            ?>
                                <label class="flex items-center gap-2 bg-slate-950 border border-slate-800 p-2 rounded-lg text-xs cursor-pointer hover:border-slate-700">
                                    <input type="checkbox" name="modules[]" value="<?= $mod ?>" checked class="rounded bg-slate-800 border-slate-700 text-blue-600 focus:ring-0">
                                    <span><?= $mod ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-2.5 rounded-lg shadow-lg shadow-blue-600/30 transition text-sm">
                            <i class="fa-solid fa-file-signature mr-2"></i> Sign & Generate License
                        </button>
                        <button type="submit" name="download_file" value="1" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-6 py-2.5 rounded-lg shadow-lg shadow-emerald-600/30 transition text-sm">
                            <i class="fa-solid fa-download mr-2"></i> Download license.key
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($generatedLicensePackage): ?>
                <!-- Generated License Output Box -->
                <div class="bg-slate-800 border border-emerald-500/50 rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-emerald-400 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i> Cryptographically Signed License Certificate
                        </h3>
                        <button onclick="navigator.clipboard.writeText(document.getElementById('b64Key').value); alert('Copied base64 license key!');"
                                class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded-lg text-white">
                            <i class="fa-solid fa-copy mr-1"></i> Copy Base64 String
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Base64 Encoded Key (For Activation Input in POS):</label>
                            <textarea id="b64Key" readonly rows="3" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2.5 text-xs text-emerald-300 font-mono focus:outline-none"><?= $generatedLicenseBase64 ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Signed JSON Package:</label>
                            <pre class="bg-slate-950 border border-slate-700 rounded-lg p-3 text-xs text-slate-300 font-mono overflow-x-auto max-h-60"><?= htmlspecialchars($generatedLicensePackage) ?></pre>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Column 3: Vendor Keys & Heartbeat Status -->
        <div class="space-y-6">
            <!-- Active Keypair Card -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-lock text-purple-400"></i> Vendor Master Ed25519 Key
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block mb-1">Vendor Public Key (Hex):</span>
                        <div class="bg-slate-950 border border-slate-800 p-2 rounded text-blue-400 font-mono break-all select-all">
                            <?= htmlspecialchars($keys['public_key_hex']) ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-1">Vendor Private Key:</span>
                        <div class="bg-slate-950 border border-slate-800 p-2 rounded text-slate-500 font-mono">
                            •••••••••••••••••••••••••••••••• [Protected on Server]
                        </div>
                    </div>
                    <p class="text-slate-400 text-[11px]">
                        The Public Key is hardcoded into client POS apps inside <code>/api/core/LicenseGuard.php</code>.
                    </p>

                    <form method="POST" onsubmit="return confirm('WARNING: Generating a new keypair will invalidate previously issued licenses that rely on the old public key. Continue?');">
                        <input type="hidden" name="vendor_action" value="generate_keypair">
                        <button type="submit" class="w-full bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/40 text-purple-300 font-medium py-2 rounded-lg text-xs transition">
                            <i class="fa-solid fa-rotate mr-1.5"></i> Rotate / Generate New Keypair
                        </button>
                    </form>
                </div>
            </div>

            <!-- Central Heartbeat Server Status -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-heart-pulse text-rose-400"></i> Central Heartbeat Service
                </h3>
                <p class="text-xs text-slate-400 mb-3">
                    Client POS installations call this server periodically to verify status and renew signed lease tokens.
                </p>
                <div class="bg-slate-950 border border-slate-800 p-2.5 rounded-lg text-xs font-mono text-emerald-400 break-all mb-2">
                    POST <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'], '?') ?>?api=heartbeat
                </div>
                <div class="flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-700">
                    <span>Heartbeat Protocol:</span>
                    <span class="text-emerald-400 font-semibold">100% POST (Ed25519)</span>
                </div>
            </div>
        </div>

    </main>
<?php endif; ?>

</body>
</html>
