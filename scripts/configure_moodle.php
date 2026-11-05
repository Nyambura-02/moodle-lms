<?php
// ==============================================================================
// GlobeCon LMS - Cloud Auto-Configuration Engine
// Resolves Railway & Render environment variables, verifies database reachability,
// generates production config.php, and auto-initializes empty databases.
// ==============================================================================

echo "[*] GlobeCon LMS Cloud Configuration Engine Starting..." . PHP_EOL;

// 1. Resolve Target Port
$port = getenv('PORT') ?: '80';

// 2. Resolve Public URL
$site_url = getenv('MOODLE_URL');
if (empty($site_url)) {
    if ($render_url = getenv('RENDER_EXTERNAL_URL')) {
        $site_url = $render_url;
    } elseif ($railway_pub = getenv('RAILWAY_PUBLIC_DOMAIN')) {
        $site_url = 'https://' . $railway_pub;
    } elseif ($railway_static = getenv('RAILWAY_STATIC_URL')) {
        $site_url = 'https://' . $railway_static;
    } else {
        $site_url = 'http://localhost:' . $port;
    }
}
$site_url = rtrim($site_url, '/');
echo "[*] Resolved Public Site URL: {$site_url}" . PHP_EOL;

// 3. Resolve Database Credentials from all common Cloud PaaS variables
$raw_db_url = getenv('DATABASE_URL')
    ?: getenv('MYSQL_URL')
    ?: getenv('MYSQL_PRIVATE_URL')
    ?: getenv('JAWSDB_URL')
    ?: getenv('CLEARDB_DATABASE_URL')
    ?: getenv('POSTGRES_URL')
    ?: getenv('POSTGRESQL_URL');

$db_type = getenv('DB_TYPE') ?: 'mariadb';
$db_host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: getenv('DATABASE_HOST') ?: getenv('PGHOST');
$db_port = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: getenv('DATABASE_PORT') ?: getenv('PGPORT');
$db_name = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DATABASE_NAME') ?: getenv('PGDATABASE');
$db_user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: getenv('DATABASE_USER') ?: getenv('PGUSER');
$db_pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('DATABASE_PASSWORD') ?: getenv('PGPASSWORD') ?: '';
$db_prefix = getenv('DB_PREFIX') ?: 'mdl_';

if (!empty($raw_db_url)) {
    echo "[*] Found Database URL variable. Parsing..." . PHP_EOL;
    $parsed = parse_url($raw_db_url);
    if (!empty($parsed['scheme'])) {
        if (strpos($parsed['scheme'], 'postgres') !== false) {
            $db_type = 'pgsql';
            $db_port = $db_port ?: 5432;
        } else {
            $db_type = 'mariadb';
            $db_port = $db_port ?: 3306;
        }
    }
    if (!empty($parsed['host'])) $db_host = $parsed['host'];
    if (!empty($parsed['port'])) $db_port = (int)$parsed['port'];
    if (!empty($parsed['user'])) $db_user = urldecode($parsed['user']);
    if (isset($parsed['pass']))  $db_pass = urldecode($parsed['pass']);
    if (!empty($parsed['path'])) $db_name = ltrim(urldecode($parsed['path']), '/');
}

// Fallbacks if not set
$is_unlinked = empty($db_host);
if (empty($db_host)) $db_host = 'database';
if (empty($db_port)) $db_port = ($db_type === 'pgsql') ? 5432 : 3306;
if (empty($db_name)) $db_name = ($db_type === 'pgsql') ? 'moodle' : 'railway';
if (empty($db_user)) $db_user = ($db_type === 'pgsql') ? 'postgres' : 'root';

if ($db_type === 'mysql') $db_type = 'mysqli';

echo "[*] Database Driver: {$db_type}" . PHP_EOL;
echo "[*] Database Host: {$db_host}:{$db_port}" . PHP_EOL;
echo "[*] Database Name: {$db_name}" . PHP_EOL;
echo "[*] Database User: {$db_user}" . PHP_EOL;

// 4. Test Database Reachability with Retry Loop
$is_ip = filter_var($db_host, FILTER_VALIDATE_IP) !== false;
$dns_resolvable = $is_ip || ($db_host === 'localhost') || (gethostbyname($db_host) !== $db_host);

$max_attempts = $dns_resolvable ? 25 : 3;
$connected = false;

echo "[*] Testing database connectivity to {$db_host}:{$db_port}..." . PHP_EOL;

if (!$dns_resolvable) {
    echo "[!] WARNING: Hostname '{$db_host}' cannot be resolved via DNS." . PHP_EOL;
    echo "[!] Tip: In Railway, click '+ Create' -> 'Database' -> 'MySQL', then in your web service go to 'Variables' -> 'New Variable' -> 'Add Reference' -> 'MYSQL_URL'." . PHP_EOL;
}

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $fp = @fsockopen($db_host, (int)$db_port, $errno, $errstr, 2);
    if ($fp) {
        fclose($fp);
        $connected = true;
        echo "[✓] Database port is OPEN and responding! (Attempt {$attempt})" . PHP_EOL;
        break;
    }
    echo "[!] Waiting for database at {$db_host}:{$db_port}... (Attempt {$attempt}/{$max_attempts})" . PHP_EOL;
    sleep(2);
}

if (!$connected) {
    echo "[!] WARNING: Unable to establish TCP connection to {$db_host}:{$db_port}." . PHP_EOL;
    echo "[!] Please ensure your MySQL or PostgreSQL service is created and linked in Railway / Render." . PHP_EOL;
}

// 5. Always Write Production config.php
$sslproxy = (strpos($site_url, 'https://') === 0) ? 'true' : 'false';

$dbcollation_line = ($db_type === 'pgsql') ? "" : "  'dbcollation' => 'utf8mb4_unicode_ci',\n";

$config_content = "<?php  // Moodle configuration file\n"
    . "unset(\$CFG);\n"
    . "global \$CFG;\n"
    . "\$CFG = new stdClass();\n\n"
    . "\$CFG->dbtype    = '{$db_type}';\n"
    . "\$CFG->dblibrary = 'native';\n"
    . "\$CFG->dbhost    = '{$db_host}';\n"
    . "\$CFG->dbname    = '{$db_name}';\n"
    . "\$CFG->dbuser    = '{$db_user}';\n"
    . "\$CFG->dbpass    = '{$db_pass}';\n"
    . "\$CFG->prefix    = '{$db_prefix}';\n"
    . "\$CFG->dboptions = array (\n"
    . "  'dbpersist' => 0,\n"
    . "  'dbport' => {$db_port},\n"
    . "  'dbsocket' => '',\n"
    . $dbcollation_line
    . ");\n\n"
    . "\$CFG->wwwroot   = '{$site_url}';\n"
    . "\$CFG->dataroot  = '/var/www/moodledata';\n"
    . "\$CFG->admin     = 'admin';\n\n"
    . "\$CFG->directorypermissions = 0777;\n"
    . "\$CFG->sslproxy  = {$sslproxy};\n\n"
    . "\$CFG->timezone = 'Africa/Nairobi';\n"
    . "\$CFG->country  = 'KE';\n\n"
    . "\$CFG->themedesignermode = false;\n\n"
    . "require_once(__DIR__ . '/lib/setup.php');\n";

$target_file = '/var/www/html/config.php';
file_put_contents($target_file, $config_content);
chmod($target_file, 0644);
echo "[✓] Successfully written production config.php" . PHP_EOL;

// 6. Check if Database is connected and Auto-Install Schema if empty
if ($connected) {
    try {
        $dsn = ($db_type === 'pgsql')
            ? "pgsql:host={$db_host};port={$db_port};dbname={$db_name}"
            : "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = ($db_type === 'pgsql')
            ? $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
            : $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $table_count = count($tables);
        echo "[*] Connected to database engine successfully. Found {$table_count} existing tables." . PHP_EOL;

        if ($table_count === 0 && getenv('AUTO_INSTALL_DATABASE') !== 'false') {
            $admin_pass = getenv('MOODLE_ADMIN_PASSWORD') ?: 'Admin@GlobeCon2026!';
            $admin_user = getenv('MOODLE_ADMIN_USER') ?: 'admin';
            $admin_email = getenv('MOODLE_ADMIN_EMAIL') ?: 'support@globecon.co.ke';
            $site_fullname = getenv('MOODLE_SITE_FULLNAME') ?: 'GlobeCon Convergence Solutions LMS';
            $site_shortname = getenv('MOODLE_SITE_SHORTNAME') ?: 'GlobeCon LMS';

            echo "[*] Empty database detected. Auto-installing Moodle schema via CLI..." . PHP_EOL;
            $cli_script = '/var/www/html/admin/cli/install_database.php';
            if (file_exists($cli_script)) {
                $cmd = sprintf(
                    'php %s --lang=en --adminuser=%s --adminpass=%s --adminemail=%s --fullname=%s --shortname=%s --agree-license',
                    escapeshellarg($cli_script),
                    escapeshellarg($admin_user),
                    escapeshellarg($admin_pass),
                    escapeshellarg($admin_email),
                    escapeshellarg($site_fullname),
                    escapeshellarg($site_shortname)
                );
                passthru($cmd, $return_var);
                if ($return_var === 0) {
                    echo "[✓] Moodle schema successfully installed!" . PHP_EOL;
                    echo "[*] Admin Username: {$admin_user}" . PHP_EOL;
                    echo "[*] Admin Password: {$admin_pass}" . PHP_EOL;
                } else {
                    echo "[!] CLI install exited with code {$return_var}." . PHP_EOL;
                }
            }
        }
    } catch (Exception $e) {
        echo "[*] Database notice: " . $e->getMessage() . PHP_EOL;
    }
}
