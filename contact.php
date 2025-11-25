<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Metoda nije dopuštena.', 405);
}

$recipientEmail = 'perfoo@yahoo.com';

// SMTP postavke - prilagodite za produkciju kako bi slanje bilo pouzdano
$smtpConfig = [
    'enabled' => true,
    'host' => 'mail.betapi.hr',
    'username' => 'info@betapi.hr',
    'password' => 'PROMIJENITE_ME',
    'port' => 587,
    'encryption' => 'tls',
];

$formType = isset($_POST['form_type']) ? trim((string) $_POST['form_type']) : '';
$name = isset($_POST['ime']) ? trim((string) $_POST['ime']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$message = isset($_POST['poruka']) ? trim((string) $_POST['poruka']) : '';
$position = isset($_POST['pozicija']) ? trim((string) $_POST['pozicija']) : '';
$phoneRaw = isset($_POST['telefon']) ? trim((string) $_POST['telefon']) : '';
$phone = $phoneRaw !== '' ? preg_replace('/[^\d+]/', '', $phoneRaw) : '';

if ($formType === '' && $position !== '') {
    $formType = 'application';
} elseif ($formType === '') {
    $formType = 'contact';
}

$errors = [];

if ($name === '') {
    $errors[] = 'Polje "Ime i Prezime" je obavezno.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Upišite ispravan email.';
}

if ($message === '') {
    $errors[] = 'Polje "Poruka" je obavezno.';
}

if (!in_array($formType, ['contact', 'application'], true)) {
    $errors[] = 'Nevažeći tip forme.';
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

$subject = $formType === 'application' ? 'Prijava za poziciju s web stranice' : 'Kontakt poruka s web stranice';

$safe = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$bodyParts = [
    'Ime i prezime' => $name,
    'Email' => $email,
];

if ($formType === 'application') {
    $bodyParts['Pozicija'] = $position !== '' ? $position : 'Nije navedeno';
    $bodyParts['Telefon'] = $phone !== '' ? $phone : 'Nije naveden';
}

$bodyParts['Poruka'] = $message;

$bodyHtml = '<h2>Nova poruka s web stranice</h2><table cellspacing="0" cellpadding="6" border="0">';
foreach ($bodyParts as $label => $value) {
    $bodyHtml .= '<tr><td><strong>' . $safe($label) . '</strong></td><td>' . nl2br($safe($value)) . '</td></tr>';
}
$bodyHtml .= '</table>';

$bodyTextLines = [];
foreach ($bodyParts as $label => $value) {
    $bodyTextLines[] = $label . ': ' . $value;
}
$bodyText = implode("\n", $bodyTextLines);

// Pokušaj učitavanja PHPMailer-a ako postoji
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        $phpMailerPath = __DIR__ . '/phpmailer/src/PHPMailer.php';
        $smtpPath = __DIR__ . '/phpmailer/src/SMTP.php';
        $exceptionPath = __DIR__ . '/phpmailer/src/Exception.php';
        if (is_file($phpMailerPath)) {
            require_once $exceptionPath;
            require_once $smtpPath;
            require_once $phpMailerPath;
        }
    }
}

$mailSent = false;
$mailError = '';

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        if ($smtpConfig['enabled']) {
            $mailer->isSMTP();
            $mailer->Host = $smtpConfig['host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpConfig['username'];
            $mailer->Password = $smtpConfig['password'];
            $mailer->Port = $smtpConfig['port'];
            $mailer->SMTPSecure = $smtpConfig['encryption'];
        } else {
            $mailer->isMail();
        }
        $mailer->setFrom('info@betapi.hr', 'BETAPI web');
        $mailer->addAddress($recipientEmail);
        $mailer->addReplyTo($email, $name ?: 'Posjetitelj');
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $bodyHtml;
        $mailer->AltBody = $bodyText;
        $mailer->send();
        $mailSent = true;
    } catch (Throwable $e) {
        $mailError = $e->getMessage();
    }
}

if (!$mailSent) {
    $headers = [
        'From: BETAPI web <info@betapi.hr>',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $mailSent = mail($recipientEmail, $subject, $bodyText, implode("\r\n", $headers));
}

if ($mailSent) {
    respond(true, 'Hvala na poruci! Javit ćemo Vam se uskoro.');
}

$errorMessage = 'Došlo je do pogreške pri slanju poruke. Molimo pokušajte ponovno kasnije.';
if ($mailError !== '') {
    error_log('Slanje poruke nije uspjelo: ' . $mailError);
}

respond(false, $errorMessage, 500);
