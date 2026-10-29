<?php
require __DIR__ . '/../../../vendor/autoload.php';

use Geotab\MyGeotabException;

$deviceExceptionCount = [];
$deviceNames = [];
$error = null;
$post = fn(string $k, string $d = '') => is_string($_POST[$k] ?? null) ? $_POST[$k] : $d;
$submitted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$server = $post('server') ?: 'my.geotab.com';
if ($submitted && !preg_match('/^([a-z0-9-]+\.)*geotab\.com$/iD', $server)) {
    $error = 'Server must be a geotab.com hostname.';
}

if ($submitted && $error === null) {
    try {
        $lastWeek = new DateTime();
        $lastWeek->modify('-1 week');

        $api = new Geotab\API(
            $post('username'),
            $post('password'),
            $post('database'),
            $server
        );
        $api->authenticate();

        $exceptions = $api->get('ExceptionEvent', [
            'search' => [
                'ruleSearch' => ['id' => 'RulePostedSpeedingId'],
                'fromDate'   => $lastWeek->format('c'),
            ],
        ]);

        $deviceExceptionCount = GeotabPHP\ExceptionCalculator::GetExceptionCountByDevice($exceptions ?? []);
        $deviceNames = GeotabPHP\ExceptionCalculator::GetDeviceNames($api, array_keys($deviceExceptionCount));
    } catch (MyGeotabException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Unable to retrieve speeding violations. Please try again.';
    }
}

$hasResults = count($deviceExceptionCount) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Speeding Violations — MyGeotab PHP</title>
    <link rel="stylesheet" href="https://unpkg.com/@geotab/zenith@3.12.3/dist/index.css">
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <header class="app-header">
        <span class="app-header__title">Top Speeding Violations This Week</span>
    </header>

    <main class="app-main">
        <div class="zen-card-container zen-card-shadow connect-card">
            <h2 class="form-heading">Connect to MyGeotab</h2>

            <?php if ($error): ?>
            <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
                <div class="form-field">
                    <label class="zen-body-s-700 field-label" for="f-username">Username</label>
                    <input id="f-username" class="zen-text-input field-input" type="email"
                           name="username" placeholder="user@example.com"
                           value="<?= htmlspecialchars($post('username')) ?>" required>
                </div>

                <div class="form-field">
                    <label class="zen-body-s-700 field-label" for="f-password">Password</label>
                    <input id="f-password" class="zen-text-input field-input" type="password"
                           name="password" required>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label class="zen-body-s-700 field-label" for="f-database">Database</label>
                        <input id="f-database" class="zen-text-input field-input" type="text"
                               name="database" placeholder="DatabaseName"
                               value="<?= htmlspecialchars($post('database')) ?>" required>
                    </div>
                    <div class="form-field">
                        <label class="zen-body-s-700 field-label" for="f-server">Server</label>
                        <input id="f-server" class="zen-text-input field-input" type="text"
                               name="server"
                               value="<?= htmlspecialchars($post('server', 'my.geotab.com')) ?>">
                    </div>
                </div>

                <button type="submit" name="submit" class="zen-button zen-button--primary btn-submit">
                    Connect
                </button>
            </form>
        </div>

        <?php if ($hasResults): ?>
        <div>
            <div class="results-heading">
                <h2>Results</h2>
                <span class="results-count"><?= count($deviceExceptionCount) ?> vehicles</span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Vehicle</th>
                            <th>Speeding Exceptions (7 days)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        foreach ($deviceExceptionCount as $deviceId => $count):
                            $rankClass = match ($i) {
                                1 => 'rank-badge--gold',
                                2 => 'rank-badge--silver',
                                3 => 'rank-badge--bronze',
                                default => '',
                            };
                            $countClass = $count >= 20 ? 'count-badge--high'
                                        : ($count >= 10 ? 'count-badge--medium' : '');
                            $name = $deviceNames[$deviceId] ?? 'Unknown';
                        ?>
                        <tr>
                            <td><span class="rank-badge <?= $rankClass ?>"><?= $i ?></span></td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><span class="count-badge <?= $countClass ?>"><?= $count ?></span></td>
                        </tr>
                        <?php $i++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($submitted && !$error): ?>
        <div class="zen-card-container zen-card-shadow empty-state">
            <p class="zen-body-m-400-short" style="color:var(--text-secondary);margin:0">
                No speeding exceptions found in the last 7 days.
            </p>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
