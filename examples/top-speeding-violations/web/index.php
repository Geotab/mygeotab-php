<?php
require __DIR__ . '/../../../vendor/autoload.php';

$deviceExceptionCount = [];
if (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $database = $_POST['database'];
    $server = $_POST['server'];
    $lastWeek = new DateTime();
    $lastWeek->modify("-1 week");
    $api = new Geotab\API($username, $password, $database, $server);
    $api->authenticate();
    $exceptions = $api->get("ExceptionEvent", [
        "search" => [
            "ruleSearch" => [ "id" => "RulePostedSpeedingId" ],
            "fromDate" => $lastWeek->format("c")    //ISO8601
        ]
    ]);
    $deviceExceptionCount = GeotabPHP\ExceptionCalculator::GetExceptionCountByDevice($exceptions);
    $deviceNames = GeotabPHP\ExceptionCalculator::GetDeviceNames($api, array_keys($deviceExceptionCount));
}
?>
<html>
<head>
    <title>MyGeotab PHP API</title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <header>
        <h1>Top Speeding Violations This Week</h1>
    </header>
    <article>
        <div id="authenticate">
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post">
                <input type="text" placeholder="Username" name="username" />
                <input type="password" placeholder="Password" name="password" />
                <input type="text" placeholder="Database" name="database" />
                <input type="text" placeholder="Server" name="server" value="my.geotab.com" />
                <input type="submit" name="submit" value="Connect" />
            </form>
        </div>
        <?php
        if (count($deviceExceptionCount) > 0) {
            ?>
            <table>
                <thead>
                    <th>#</th>
                    <th>Vehicle</th>
                    <th># speeding exceptions</th>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($deviceExceptionCount as $deviceId => $count) {
                        ?>
                        <tr>
                            <td><?=$i?></td>
                            <td><?=array_key_exists($deviceId, $deviceNames) ? $deviceNames[$deviceId] : "Unknown"?></td>
                            <td align="center"><?=$count?></td>
                        </tr>
                        <?php
                        $i++;
                    }
                    ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </article>
    <footer>
        <span id="logo"></span>
    </footer>
</body>
</html>
