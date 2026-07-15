<?php
/**
 * Live GPS Feed — MyGeotab PHP example
 *
 * Demonstrates GetFeed with LogRecord to stream real-time GPS positions
 * from all vehicles in a database, polling every 5 seconds.
 *
 * Run:
 *   php -S localhost:7001 -t examples/gps-feed/web
 */
session_start();
require __DIR__ . '/../../../vendor/autoload.php';

use Geotab\MyGeotabException;

$error = null;

// ── Logout ────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Login form submitted ──────────────────────────────────────────
if (isset($_POST['submit'])) {
    try {
        $api = new Geotab\API(
            $_POST['username'],
            $_POST['password'],
            $_POST['database'],
            $_POST['server'] ?: 'my.geotab.com'
        );
        $api->authenticate();

        $cred = $api->getCredentials();
        $_SESSION['credentials'] = [
            'username'  => $cred->getUsername(),
            'sessionId' => $cred->getSessionId(),
            'database'  => $cred->getDatabase(),
            'server'    => $cred->getServer(),
        ];

        // Advance the feed to the current position so we only see new data.
        $feed = $api->call('GetFeed', ['typeName' => 'LogRecord', 'resultsLimit' => 1]);
        $_SESSION['fromVersion'] = $feed['toVersion'] ?? '';

        // Cache device names for display (up to 500 devices).
        $devices = $api->get('Device', ['resultsLimit' => 500]) ?? [];
        $names = [];
        foreach ($devices as $d) {
            if (!empty($d['id'])) {
                $names[$d['id']] = $d['name'] ?? $d['id'];
            }
        }
        $_SESSION['deviceNames'] = $names;

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;

    } catch (MyGeotabException $e) {
        $error = $e->getMessage();
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$authenticated = isset($_SESSION['credentials']);
$deviceNames   = $_SESSION['deviceNames'] ?? [];
$database      = $authenticated ? ($_SESSION['credentials']['database'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live GPS Feed — MyGeotab PHP</title>
    <link rel="stylesheet" href="https://unpkg.com/@geotab/zenith@3.12.3/dist/index.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="app-header">
        <span class="app-header__title">Live GPS Feed</span>
        <?php if ($authenticated): ?>
        <div class="app-header__actions">
            <span class="zen-body-s-400" style="color:rgba(255,255,255,0.7)">
                <?= htmlspecialchars($database) ?>
            </span>
            <a href="?logout" class="zen-button btn-disconnect">Disconnect</a>
        </div>
        <?php endif; ?>
    </header>

    <main class="app-main">

    <?php if (!$authenticated): ?>
    <!-- ── Login ─────────────────────────────────────────────── -->
    <div class="zen-card-container zen-card-shadow login-card">
        <h2 class="form-heading">Connect to MyGeotab</h2>

        <?php if ($error): ?>
        <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-field">
                <label class="zen-body-s-700 field-label" for="f-username">Username</label>
                <input id="f-username" class="zen-text-input field-input" type="email"
                       name="username" placeholder="user@example.com"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>

            <div class="form-field">
                <label class="zen-body-s-700 field-label" for="f-password">Password</label>
                <input id="f-password" class="zen-text-input field-input"
                       type="password" name="password" required>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label class="zen-body-s-700 field-label" for="f-database">Database</label>
                    <input id="f-database" class="zen-text-input field-input" type="text"
                           name="database" placeholder="DatabaseName"
                           value="<?= htmlspecialchars($_POST['database'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label class="zen-body-s-700 field-label" for="f-server">Server</label>
                    <input id="f-server" class="zen-text-input field-input" type="text"
                           name="server"
                           value="<?= htmlspecialchars($_POST['server'] ?? 'my.geotab.com') ?>">
                </div>
            </div>

            <button type="submit" name="submit"
                    class="zen-button zen-button--primary btn-connect">
                Connect
            </button>
        </form>
    </div>

    <?php else: ?>
    <!-- ── Live feed ─────────────────────────────────────────── -->
    <div class="feed-toolbar">
        <div class="feed-status">
            <span class="pulse-dot" id="pulse"></span>
            <span id="status-text">Connecting…</span>
        </div>
        <span class="feed-meta" id="record-meta"></span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time (UTC)</th>
                    <th>Vehicle</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Speed</th>
                    <th>Map</th>
                </tr>
            </thead>
            <tbody id="feed-body">
                <tr class="waiting-row">
                    <td colspan="6">Waiting for GPS data…</td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
    (() => {
        const MAX_ROWS = 100;
        const POLL_MS  = 5000;

        // Device names injected from PHP session
        const deviceNames = <?= json_encode($deviceNames, JSON_HEX_TAG) ?>;

        let totalReceived = 0;
        let pollCount     = 0;

        const pulse      = document.getElementById('pulse');
        const statusText = document.getElementById('status-text');
        const metaEl     = document.getElementById('record-meta');
        const tbody      = document.getElementById('feed-body');

        function deviceName(id) {
            return deviceNames[id] || id;
        }

        function formatUtc(iso) {
            try {
                return new Date(iso).toISOString().replace('T', ' ').substring(0, 19);
            } catch {
                return iso;
            }
        }

        function speedClass(kmh) {
            if (kmh >= 130) return 'speed-badge--vfast';
            if (kmh >= 100) return 'speed-badge--fast';
            return '';
        }

        function setStatus(live, text) {
            pulse.className  = 'pulse-dot' + (live === true  ? ' pulse-dot--live'
                                            : live === false ? ' pulse-dot--error' : '');
            statusText.textContent = text;
        }

        async function poll() {
            try {
                const res  = await fetch('feed.php');
                const data = await res.json();

                if (!res.ok || data.error) {
                    setStatus(false, 'Error: ' + (data.error ?? res.statusText));
                    return;
                }

                pollCount++;
                const records = data.records ?? [];
                totalReceived += records.length;

                setStatus(true, 'Live · polled ' + pollCount + '× · last '
                    + new Date().toISOString().substring(11, 19) + ' UTC');

                metaEl.textContent = totalReceived
                    ? totalReceived + ' record' + (totalReceived === 1 ? '' : 's') + ' received'
                    : '';

                if (records.length === 0) return;

                // Remove "waiting" placeholder on first real data
                tbody.querySelector('.waiting-row')?.remove();

                // Prepend newest records at the top
                [...records].reverse().forEach(r => {
                    const lat  = parseFloat(r.latitude  ?? 0).toFixed(5);
                    const lon  = parseFloat(r.longitude ?? 0).toFixed(5);
                    const kmh  = Math.round(r.speed ?? 0);
                    const mapUrl = `https://maps.google.com/?q=${lat},${lon}`;

                    const tr = document.createElement('tr');
                    tr.className = 'row-new';
                    tr.innerHTML = `
                        <td>${formatUtc(r.dateTime)}</td>
                        <td>${deviceName(r.device?.id ?? '')}</td>
                        <td style="font-variant-numeric:tabular-nums">${lat}</td>
                        <td style="font-variant-numeric:tabular-nums">${lon}</td>
                        <td><span class="speed-badge ${speedClass(kmh)}">${kmh} km/h</span></td>
                        <td><a class="map-link" href="${mapUrl}" target="_blank" rel="noopener">Map ↗</a></td>
                    `;
                    tbody.insertBefore(tr, tbody.firstChild);
                });

                // Trim rows beyond the max to keep the DOM tidy
                const rows = tbody.querySelectorAll('tr:not(.waiting-row)');
                rows.forEach((row, i) => { if (i >= MAX_ROWS) row.remove(); });

            } catch (err) {
                setStatus(false, 'Network error — retrying…');
            }
        }

        // First poll immediately, then every 5 seconds
        poll();
        setInterval(poll, POLL_MS);
    })();
    </script>
    <?php endif; ?>

    </main>
</body>
</html>
