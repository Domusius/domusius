<?php
// install/install.php - første gangs installation af Domusius
error_reporting(E_ALL);
ini_set('display_errors', 1);

$lockFile = __DIR__ . '/install.lock';
if (file_exists($lockFile)) {
    header('Location: ../index.php');
    exit;
}

$error   = null;   // samlet fejlbesked øverst
$errors  = [];     // fejl pr. felt: ['db_host' => 'tekst', ...]
$success = false;

// Husk det, brugeren har skrevet (adgangskoder huskes ikke af sikkerhedsgrunde)
$old = [
    'db_host'     => trim($_POST['db_host']     ?? ''),
    'db_name'     => trim($_POST['db_name']     ?? ''),
    'db_user'     => trim($_POST['db_user']     ?? ''),
    'admin_email' => trim($_POST['admin_email'] ?? ''),
    'site_name'   => ($_SERVER['REQUEST_METHOD'] === 'POST')
                        ? trim($_POST['site_name'] ?? '')
                        : 'Domusius',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost    = $old['db_host'];
    $dbName    = $old['db_name'];
    $dbUser    = $old['db_user'];
    $dbPass    = $_POST['db_pass']   ?? '';
    $adminMail = $old['admin_email'];
    $adminPass = $_POST['admin_pass'] ?? '';
    $siteName  = $old['site_name'];

    /* 0️⃣ Tjek felterne, før vi rører databasen */
    if ($dbHost === '') {
        $errors['db_host'] = 'Skriv database host.';
    }
    if ($dbName === '') {
        $errors['db_name'] = 'Skriv et navn til databasen.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $errors['db_name'] = 'Kun bogstaver (a-z), tal og understreg.';
    }
    if ($dbUser === '') {
        $errors['db_user'] = 'Skriv database bruger.';
    }
    if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = 'Skriv en gyldig e-mailadresse.';
    }
    if ($siteName === '') {
        $errors['site_name'] = 'Skriv et navn til systemet (f.eks. Domusius).';
    } elseif (mb_strlen($siteName) > 50) {
        $errors['site_name'] = 'Navnet må højst være 50 tegn.';
    }
    if (strlen($adminPass) < 8) {
        $errors['admin_pass'] = 'Adgangskoden skal være mindst 8 tegn.';
    }

    if (!$errors && !extension_loaded('pdo_mysql')) {
        $error = 'PHP-udvidelsen "pdo_mysql" er ikke slået til på serveren. '
               . 'Slå den til i PHP-indstillingerne (på Synology: Web Station > Scripting-sprog > PHP-profil > Udvidelser), og prøv igen.';
    }

    if (!$errors && !$error) {
        try {
            /* 1️⃣ Forbind uden database */
            try {
                $pdo = new PDO(
                    "mysql:host=$dbHost;charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'Access denied') !== false) {
                    $errors['db_user'] = 'Brugernavn eller adgangskode blev afvist.';
                    $errors['db_pass'] = 'Tjek adgangskoden.';
                } else {
                    $errors['db_host'] = 'Kunne ikke forbinde til hosten.';
                }
                throw new Exception('Forbindelsen til databasen fejlede: ' . $msg);
            }

            /* 2️⃣ Opret database (navnet er tjekket ovenfor) */
            $pdo->exec("
                CREATE DATABASE IF NOT EXISTS `$dbName`
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_unicode_ci
            ");
            $pdo->exec("USE `$dbName`");

            /* 3️⃣ Kør setup_db.sql - én sætning ad gangen, så fejl bliver vist */
            $sqlFile = __DIR__ . '/DB/setup_db.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('setup_db.sql blev ikke fundet');
            }

            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);             // fjern kommentarlinjer
            $statements = preg_split('/;\s*[\r\n]+/', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    throw new Exception(
                        'Fejl i setup_db.sql: ' . $e->getMessage()
                        . ' (ved: ' . substr($statement, 0, 60) . '...)'
                    );
                }
            }

            /* 4️⃣ Opret admin (kun hvis ikke findes) */
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, status)
                SELECT 'admin', ?, ?, 'admin', 'active'
                WHERE NOT EXISTS (
                    SELECT 1 FROM users WHERE username = 'admin'
                )
            ");
            $stmt->execute([$adminMail, $hash]);

            /* 4b Gem systemets navn i databasen (kan ændres senere under Admin > System) */
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_name', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$siteName]);

            /* 5️⃣ Gem config.php (var_export sørger for, at ' og \ i adgangskoder går godt) */
            $configPath = __DIR__ . '/../config/config.php';
            if (!is_dir(dirname($configPath))) {
                mkdir(dirname($configPath), 0755, true);
            }

            $config  = "<?php\n";
            $config .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $config .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $config .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $config .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $config .= <<<'PHP'

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Databaseforbindelse fejlede');
}

PHP;

            if (file_put_contents($configPath, $config) === false) {
                throw new Exception('Kunne ikke skrive config/config.php. Tjek skriverettigheder på mappen "config".');
            }

            /* 6️⃣ Lås installationen */
            file_put_contents($lockFile, 'installed');

            $success = true;

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif (!$error) {
        $error = 'Ret de felter, der er markeret med rødt.';
    }
}

// Hjælpere til formularen
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cls($errors, $k) { return isset($errors[$k]) ? ' class="has-error"' : ''; }
function msg($errors, $k) { return isset($errors[$k]) ? '<div class="field-error">' . h($errors[$k]) . '</div>' : ''; }
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Installation – Domusius</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        input.has-error { border-color: #dc3545 !important; background: #fff5f5; }
        .field-error { color: #dc3545; font-size: 0.85em; margin-top: 4px; }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <h1>Domusius</h1>
    <div class="login-subtitle">Første gangs installation</div>

    <?php if ($success): ?>
        <div class="login-success">
            ✅ Installation fuldført
        </div>
        <a href="../index.php">
            <button>➡ Gå til systemet</button>
        </a>

    <?php else: ?>

        <?php if ($error): ?>
            <div class="login-error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" novalidate>

            <div class="form-group">
                <label>Database host</label>
                <input name="db_host" value="<?= h($old['db_host']) ?>"<?= cls($errors, 'db_host') ?> placeholder="localhost / sqlxxx.infinityfree.com">
                <?= msg($errors, 'db_host') ?>
            </div>

            <div class="form-group">
                <label>Database navn</label>
                <input name="db_name" value="<?= h($old['db_name']) ?>"<?= cls($errors, 'db_name') ?> placeholder="Domusius">
                <?= msg($errors, 'db_name') ?>
            </div>

            <div class="form-group">
                <label>Database bruger</label>
                <input name="db_user" value="<?= h($old['db_user']) ?>"<?= cls($errors, 'db_user') ?>>
                <?= msg($errors, 'db_user') ?>
            </div>

            <div class="form-group">
                <label>Database adgangskode</label>
                <input name="db_pass" type="password"<?= cls($errors, 'db_pass') ?>>
                <?= msg($errors, 'db_pass') ?>
            </div>

            <hr style="margin:25px 0; border:none; border-top:1px solid #eee;">

            <div class="form-group">
                <label>Systemets navn</label>
                <input name="site_name" value="<?= h($old['site_name']) ?>"<?= cls($errors, 'site_name') ?>>
                <?= msg($errors, 'site_name') ?>
            </div>

            <div class="form-group">
                <label>Admin email</label>
                <input name="admin_email" type="email" value="<?= h($old['admin_email']) ?>"<?= cls($errors, 'admin_email') ?>>
                <?= msg($errors, 'admin_email') ?>
            </div>

            <div class="form-group">
                <label>Admin adgangskode (mindst 8 tegn)</label>
                <input name="admin_pass" type="password"<?= cls($errors, 'admin_pass') ?>>
                <?= msg($errors, 'admin_pass') ?>
            </div>

            <button type="submit">Installer system</button>

        </form>

    <?php endif; ?>

    <div class="login-footer">
        &copy; <?= date('Y') ?> Udviklet af Espersen-Glasius
    </div>
</div>

</body>
</html>
