<?php
declare(strict_types=1);

/**
 * Vendra Showcase & Central License Authority 1-Click Web Installer
 * Designed for hosting.com / cPanel shared hosting (https://vendra.charafeddine.xyz/install/)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$portalDir = realpath(__DIR__ . '/..');
$lockFile = $portalDir . '/config/installed.lock';
$envFile = $portalDir . '/config/env.php';
$schemaFile = $portalDir . '/database/vendra_portal_schema.sql';
$uploadsDir = $portalDir . '/uploads/screenshots';
$configDir = $portalDir . '/config';

$isLocked = file_exists($lockFile);
$lockData = null;
if ($isLocked) {
    $rawLock = @file_get_contents($lockFile);
    $lockData = json_decode($rawLock ?: '{}', true) ?: [];
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    if ($isLocked) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Vendra Portal is already installed and locked. Delete config/installed.lock via cPanel to re-install.',
        ]);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) $data = $_POST;

    $action = $data['action'] ?? '';

    switch ($action) {
        case 'check_prerequisites':
            $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
            $extensions = [
                'pdo'       => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'sodium'    => extension_loaded('sodium'),
                'openssl'   => extension_loaded('openssl'),
                'json'      => extension_loaded('json'),
            ];

            if (!is_dir($configDir)) @mkdir($configDir, 0775, true);
            if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);

            $directories = [
                'config'  => ['path' => 'portal/config', 'writable' => is_writable($configDir)],
                'uploads' => ['path' => 'portal/uploads', 'writable' => is_writable($uploadsDir)],
            ];

            $allPassed = $phpOk && $extensions['pdo'] && $extensions['sodium'] && $extensions['openssl'] && $directories['config']['writable'];

            echo json_encode([
                'success'     => true,
                'php_version' => PHP_VERSION,
                'php_ok'      => $phpOk,
                'extensions'  => $extensions,
                'directories' => $directories,
                'all_passed'  => $allPassed,
            ]);
            exit;

        case 'test_db':
            $driver = $data['driver'] ?? 'mysql';
            $host = trim((string)($data['host'] ?? 'localhost'));
            $port = (int)($data['port'] ?? 3306);
            $dbname = trim((string)($data['database'] ?? ''));
            $user = trim((string)($data['username'] ?? ''));
            $pass = (string)($data['password'] ?? '');

            if ($driver === 'sqlite') {
                echo json_encode(['success' => true, 'message' => 'SQLite connection ready.']);
                exit;
            }

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
                echo json_encode(['success' => true, 'message' => "Connection successful! MySQL: {$ver}"]);
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            }
            exit;

        case 'install':
            $driver = $data['driver'] ?? 'mysql';
            $host = trim((string)($data['host'] ?? 'localhost'));
            $port = (int)($data['port'] ?? 3306);
            $dbname = trim((string)($data['database'] ?? ''));
            $user = trim((string)($data['username'] ?? ''));
            $pass = (string)($data['password'] ?? '');

            $adminEmail = trim((string)($data['admin_email'] ?? 'charafeddine@vendra.xyz'));
            $adminPassword = (string)($data['admin_password'] ?? 'VendorMasterPassword2026!');

            try {
                if ($driver === 'sqlite') {
                    $sqlitePath = $portalDir . '/database/vendra_portal.sqlite';
                    $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                } else {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
                    ]);
                }

                // Run schema
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    if ($driver === 'sqlite') {
                        $sql = preg_replace('/ENGINE=\w+ DEFAULT CHARSET=\w+ COLLATE=[\w_]+/i', '', $sql);
                        $sql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $sql);
                        $sql = preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
                        $sql = preg_replace('/AUTO_INCREMENT/i', '', $sql);
                        $sql = preg_replace('/INT UNSIGNED/i', 'INTEGER', $sql);
                        $sql = preg_replace('/BIGINT UNSIGNED/i', 'INTEGER', $sql);
                        $sql = preg_replace('/MEDIUMTEXT/i', 'TEXT', $sql);
                        $sql = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $sql);
                        $sql = preg_replace('/JSON/i', 'TEXT', $sql);
                        $sql = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
                        $sql = preg_replace('/ON DUPLICATE KEY UPDATE[^;]+/i', '', $sql);
                        $sql = preg_replace('/,\s*INDEX\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
                        $statements = array_filter(array_map('trim', explode(';', $sql)));
                        foreach ($statements as $stmt) {
                            if (!empty($stmt)) {
                                try { $pdo->exec($stmt); } catch (\Throwable $t) {}
                            }
                        }
                    } else {
                        $pdo->exec($sql);
                    }
                }

                // Generate / verify Ed25519 vendor keypair
                $kp = sodium_crypto_sign_keypair();
                $pubKeyHex = bin2hex(sodium_crypto_sign_publickey($kp));
                $secKeyHex = bin2hex(sodium_crypto_sign_secretkey($kp));

                // If user prefers standard preset key
                if (!empty($data['use_master_preset'])) {
                    $pubKeyHex = '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4';
                    $secKeyHex = 'b3106c9011e167d15a84ae3f12857256be7d4efc357cedfbb7d0604bb322415e7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4';
                }

                $passHash = password_hash($adminPassword, PASSWORD_BCRYPT);
                $secSalt = bin2hex(random_bytes(32));
                $jwtSecret = bin2hex(random_bytes(32));

                // Write env.php
                $envContent = "<?php\ndeclare(strict_types=1);\n\nreturn [\n";
                $envContent .= "    'db' => [\n";
                $envContent .= "        'driver'    => " . var_export($driver, true) . ",\n";
                $envContent .= "        'host'      => " . var_export($host, true) . ",\n";
                $envContent .= "        'port'      => " . $port . ",\n";
                $envContent .= "        'database'  => " . var_export($driver === 'sqlite' ? $portalDir . '/database/vendra_portal.sqlite' : $dbname, true) . ",\n";
                $envContent .= "        'username'  => " . var_export($user, true) . ",\n";
                $envContent .= "        'password'  => " . var_export($pass, true) . ",\n";
                $envContent .= "        'charset'   => 'utf8mb4',\n";
                $envContent .= "    ],\n";
                $envContent .= "    'security_salt'         => " . var_export($secSalt, true) . ",\n";
                $envContent .= "    'jwt_secret'            => " . var_export($jwtSecret, true) . ",\n";
                $envContent .= "    'admin_password'        => " . var_export($adminPassword, true) . ",\n";
                $envContent .= "    'admin_password_hash'   => " . var_export($passHash, true) . ",\n";
                $envContent .= "    'admin_email'           => " . var_export($adminEmail, true) . ",\n";
                $envContent .= "    'vendor_public_key_hex' => " . var_export($pubKeyHex, true) . ",\n";
                $envContent .= "    'vendor_secret_key_hex' => " . var_export($secKeyHex, true) . ",\n";
                $envContent .= "    'installed_at'          => " . time() . ",\n";
                $envContent .= "];\n";

                file_put_contents($envFile, $envContent);
                @chmod($envFile, 0640);

                // Write installed.lock
                $lockContent = [
                    'installed' => true,
                    'installed_at' => time(),
                    'installed_at_iso' => date('Y-m-d H:i:s'),
                    'domain' => 'vendra.charafeddine.xyz',
                    'admin_email' => $adminEmail,
                    'vendor_public_key' => $pubKeyHex,
                ];
                file_put_contents($lockFile, json_encode($lockContent, JSON_PRETTY_PRINT));
                @chmod($lockFile, 0444);

                echo json_encode([
                    'success' => true,
                    'message' => 'Vendra Portal & License Server successfully installed and locked!',
                    'public_key' => $pubKeyHex,
                ]);
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Installation failed: ' . $e->getMessage()]);
            }
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendra Portal — 1-Click Installer (vendra.charafeddine.xyz)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-emerald-600 selection:text-white">

  <!-- Header -->
  <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-emerald-500/20">
        V
      </div>
      <div>
        <h1 class="font-extrabold text-base tracking-tight flex items-center gap-2">
          <span>Vendra Vault</span>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            Showcase & License Server Installer
          </span>
        </h1>
        <p class="text-xs text-slate-400">https://vendra.charafeddine.xyz</p>
      </div>
    </div>
    <div class="text-xs text-slate-400 font-mono">
      <?= $isLocked ? '<span class="text-amber-400 font-bold">● LOCKED</span>' : '<span class="text-emerald-400 font-bold animate-pulse">● READY</span>' ?>
    </div>
  </header>

  <!-- Main Body -->
  <main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 flex flex-col justify-center">

    <?php if ($isLocked): ?>
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center space-y-6 shadow-2xl">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <div>
          <h2 class="text-2xl font-black text-white">Vendra Portal is Installed & Locked</h2>
          <p class="text-xs text-slate-400 mt-2">The Central License Server on vendra.charafeddine.xyz is operational.</p>
        </div>

        <?php if ($lockData): ?>
          <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 text-xs font-mono text-left space-y-2">
            <div class="flex justify-between"><span class="text-slate-500">Domain:</span><span class="text-white"><?= htmlspecialchars($lockData['domain'] ?? 'vendra.charafeddine.xyz') ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Admin Email:</span><span class="text-slate-200"><?= htmlspecialchars($lockData['admin_email'] ?? '') ?></span></div>
            <div class="flex justify-between"><span class="text-slate-500">Installed Date:</span><span class="text-slate-200"><?= htmlspecialchars($lockData['installed_at_iso'] ?? '') ?></span></div>
            <div class="pt-2 border-t border-slate-800"><span class="text-slate-500 block mb-1">Vendor Public Key:</span><code class="text-emerald-400 text-[10px] break-all"><?= htmlspecialchars($lockData['vendor_public_key'] ?? '') ?></code></div>
          </div>
        <?php endif; ?>

        <div class="flex justify-center gap-3 pt-2">
          <a href="/" class="px-6 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition">
            Open Vendra Showcase &rarr;
          </a>
        </div>
      </div>

    <?php else: ?>

      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
        <div>
          <h2 class="text-2xl font-black text-white">Vendra Portal 1-Click Setup</h2>
          <p class="text-xs text-slate-400 mt-1">Configure your MySQL database and master Ed25519 cryptographic key on hosting.com.</p>
        </div>

        <!-- Database Fields -->
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Database Engine</label>
              <select id="db-driver" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                <option value="mysql">MySQL / MariaDB (hosting.com cPanel)</option>
                <option value="sqlite">SQLite (Local Zero-Config Preview)</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Database Host</label>
              <input type="text" id="db-host" value="localhost" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Database Name</label>
              <input type="text" id="db-name" placeholder="charafed_vendra" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Database Username</label>
              <input type="text" id="db-user" placeholder="charafed_user" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
            </div>
            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Database Password</label>
              <input type="password" id="db-pass" placeholder="••••••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
            </div>
          </div>

          <!-- Vendor Master Admin -->
          <div class="border-t border-slate-800 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Vendor Admin Email</label>
              <input type="email" id="admin-email" value="charafeddine@vendra.xyz" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Vendor Master Password</label>
              <input type="password" id="admin-pass" value="VendorMasterPassword2026!" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
            </div>
            <div class="sm:col-span-2 flex items-center gap-2">
              <input type="checkbox" id="use-preset-key" checked class="rounded bg-slate-950 border-slate-800">
              <label for="use-preset-key" class="text-xs text-slate-300">Use standard Vendra Vendor Master Keypair (matches Cashier client pre-configuration)</label>
            </div>
          </div>
        </div>

        <div id="status-box" class="hidden p-3.5 rounded-2xl text-xs"></div>

        <div class="flex justify-between items-center pt-4 border-t border-slate-800">
          <button onclick="testConnection()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition">
            Test Connection
          </button>
          <button id="btn-install" onclick="runInstall()" class="px-6 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-lg shadow-emerald-600/20">
            Install & Lock Vendra Portal
          </button>
        </div>
      </div>

    <?php endif; ?>

  </main>

  <footer class="border-t border-slate-800/80 px-6 py-4 text-center text-xs text-slate-500">
    Vendra POS &bull; Official Portal for vendra.charafeddine.xyz on hosting.com
  </footer>

  <script>
    async function testConnection() {
      const box = document.getElementById('status-box');
      box.classList.remove('hidden');
      box.className = 'p-3.5 rounded-2xl text-xs bg-slate-950 border border-slate-800 text-slate-300';
      box.innerText = 'Testing MySQL connection...';

      const res = await fetch('/install/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'test_db',
          driver: document.getElementById('db-driver').value,
          host: document.getElementById('db-host').value,
          database: document.getElementById('db-name').value,
          username: document.getElementById('db-user').value,
          password: document.getElementById('db-pass').value,
        })
      });
      const data = await res.json();
      box.className = 'p-3.5 rounded-2xl text-xs ' + (data.success ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 font-bold' : 'bg-rose-500/10 border border-rose-500/30 text-rose-300');
      box.innerText = data.message;
    }

    async function runInstall() {
      const btn = document.getElementById('btn-install');
      const box = document.getElementById('status-box');
      btn.disabled = true;
      btn.innerText = 'Installing Portal...';

      const res = await fetch('/install/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'install',
          driver: document.getElementById('db-driver').value,
          host: document.getElementById('db-host').value,
          database: document.getElementById('db-name').value,
          username: document.getElementById('db-user').value,
          password: document.getElementById('db-pass').value,
          admin_email: document.getElementById('admin-email').value,
          admin_password: document.getElementById('admin-pass').value,
          use_master_preset: document.getElementById('use-preset-key').checked,
        })
      });
      const data = await res.json();
      if (data.success) {
        box.classList.remove('hidden');
        box.className = 'p-3.5 rounded-2xl text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 font-bold';
        box.innerText = data.message;
        setTimeout(() => window.location.reload(), 1500);
      } else {
        box.classList.remove('hidden');
        box.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/30 text-rose-300';
        box.innerText = data.message;
        btn.disabled = false;
        btn.innerText = 'Install & Lock Vendra Portal';
      }
    }
  </script>
</body>
</html>
