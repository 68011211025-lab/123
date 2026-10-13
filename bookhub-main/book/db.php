<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host   = getenv('DB_HOST');
$user   = getenv('DB_USER');
$pass   = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME') ?: 'defaultdb';
$port   = (int) (getenv('DB_PORT') ?: 21928);
$ca     = __DIR__ . '/ca.pem';

try {
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    if (file_exists($ca)) {
        $conn->ssl_set(null, null, $ca, null, null);
    }

    $conn->real_connect(
        $host,
        $user,
        $pass,
        $dbname,
        $port,
        null,
        MYSQLI_CLIENT_SSL
    );

    $conn->set_charset('utf8mb4');

} catch (Throwable $ex) {
    error_log('DB connect error: ' . $ex->getMessage());
    http_response_code(500);
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ กรุณาตรวจสอบการตั้งค่าระบบ');
}
