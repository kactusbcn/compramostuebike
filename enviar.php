<?php
// Recibe el formulario de compramostuebike.com y envía el lead con la foto por correo.
header('Content-Type: application/json; charset=utf-8');

const TO   = 'ventas@compramostuebike.com';   // destinatario de los leads
const FROM = 'hola@compramostuebike.com';     // remitente (buzón de tu dominio)
const MAX_BYTES = 10 * 1024 * 1024;

function out($ok, $msg = '') {
    echo json_encode(['ok' => $ok, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'Método no permitido');
}

// Campo trampa: si un bot lo rellena, fingimos éxito y no enviamos nada.
if (!empty($_POST['website'])) {
    out(true);
}

$fields = ['nombre', 'tipo', 'km', 'bateria', 'marca', 'modelo', 'cuadro', 'velocidades', 'precio', 'telefono', 'email', 'ciudad'];
$data = [];
foreach ($fields as $f) {
    $v = isset($_POST[$f]) ? trim(strip_tags((string)$_POST[$f])) : '';
    $data[$f] = mb_substr($v, 0, 300);
}

foreach (['nombre', 'tipo', 'telefono', 'email', 'ciudad'] as $req) {
    if ($data[$req] === '') {
        out(false, 'Faltan datos obligatorios');
    }
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    out(false, 'El correo no es válido');
}
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    out(false, 'Falta la foto de la bici');
}

$file = $_FILES['foto'];
if ($file['size'] > MAX_BYTES) {
    out(false, 'La foto pesa más de 10 MB');
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (strpos($mime, 'image/') !== 0) {
    out(false, 'El archivo no es una imagen');
}

// Registro local de leads (una línea por lead), protegido por leads/.htaccess
$dir = __DIR__ . '/leads';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$fh = @fopen($dir . '/leads.csv', 'a');
if ($fh) {
    fputcsv($fh, array_merge([date('c')], array_values($data)));
    fclose($fh);
}

// Correo con los datos y la foto adjunta
$subject = 'Nuevo lead: ' . $data['tipo'] . ' ' . $data['marca'] . ' ' . $data['modelo'];
$body = "Nuevo lead desde compramostuebike.com\n\n";
foreach ($fields as $f) {
    $body .= ucfirst($f) . ': ' . $data[$f] . "\n";
}
$body .= "\nFecha: " . date('d/m/Y H:i') . "\n";

$boundary = md5(uniqid('', true));
$name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
$msg  = "--$boundary\r\n";
$msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
$msg .= $body . "\r\n";
$msg .= "--$boundary\r\n";
$msg .= "Content-Type: $mime; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n";
$msg .= "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n";
$msg .= chunk_split(base64_encode(file_get_contents($file['tmp_name']))) . "\r\n";
$msg .= "--$boundary--";

$headers  = "From: compramostuebike.com <" . FROM . ">\r\n";
$headers .= "Reply-To: " . $data['nombre'] . " <" . $data['email'] . ">\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$sent = mail(TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg, $headers);
out($sent, $sent ? '' : 'No se pudo enviar. Inténtalo otra vez.');
