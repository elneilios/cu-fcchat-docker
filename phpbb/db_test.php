<?php
// Database connection test script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Test both private and public hostnames
$hosts_to_test = [
    'Private (mysql.railway.internal)' => ['host' => 'mysql.railway.internal', 'port' => '3306'],
    'Public (switchyard.proxy.rlwy.net)' => ['host' => 'switchyard.proxy.rlwy.net', 'port' => '25869']
];

$dbname = 'railway';
$dbuser = 'root';
$dbpasswd = 'lAjbGuLDXxSMsZbgSHGIGpcGBlzUiCSm';

foreach ($hosts_to_test as $test_name => $config) {
    $dbhost = $config['host'];
    $dbport = $config['port'];
    
    echo "<hr><h2>Testing: $test_name</h2>";
    echo "<p><strong>Connection details:</strong><br>";
    echo "Host: $dbhost<br>";
    echo "Port: $dbport<br>";
    echo "Database: $dbname<br>";
    echo "User: $dbuser</p>";

// Test 1: DNS resolution
echo "<h3>Test 1: DNS Resolution</h3>";
$ip = gethostbyname($dbhost);
echo "Resolved IP: $ip<br>";
if ($ip == $dbhost) {
    echo "<span style='color:red'>⚠️ DNS resolution failed</span><br>";
} else {
    echo "<span style='color:green'>✓ DNS resolved successfully</span><br>";
}

// Test 2: Port connectivity
echo "<h3>Test 2: Port Connectivity</h3>";
$connection = @fsockopen($dbhost, $dbport, $errno, $errstr, 5);
if (!$connection) {
    echo "<span style='color:red'>⚠️ Cannot connect to port: $errstr ($errno)</span><br>";
} else {
    echo "<span style='color:green'>✓ Port is reachable</span><br>";
    fclose($connection);
}

// Test 3: MySQL connection
echo "<h3>Test 3: MySQL Connection</h3>";
$mysqli = mysqli_init();
$mysqli->options(MYSQLI_INIT_COMMAND, "SET NAMES utf8");
$mysqli->options(MYSQLI_SET_CHARSET_NAME, "utf8");
$mysqli->real_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport);

if ($mysqli->connect_error) {
    echo "<span style='color:red'>⚠️ Connection failed: " . $mysqli->connect_error . "</span><br>";
    echo "Error code: " . $mysqli->connect_errno . "<br>";
} else {
    echo "<span style='color:green'>✓ MySQL connection successful!</span><br>";
    
    // Test query
    $result = $mysqli->query("SELECT COUNT(*) as user_count FROM phpbb_users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Users in database:</strong> " . $row['user_count'] . "</p>";
    }
    
    $mysqli->close();
}
}

echo "<hr><h3>Summary</h3>";
echo "<p>If private networking works, update config.php to use mysql.railway.internal:3306 for better performance.</p>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
