<?php
namespace Api\Services;

use PHPMailer\PHPMailer\PHPMailer;

class EmailPedido {
    public static function enviar(array $resumo): bool {
        if (($_ENV['APP_DEMO'] ?? 'false') === 'true' || ($_ENV['MAIL_ENABLED'] ?? 'false') !== 'true') return false;
        $remetente = $_ENV['SMTP_FROM'] ?? '';
        if (empty($_ENV['SMTP_HOST']) || !filter_var($remetente, FILTER_VALIDATE_EMAIL)) return false;
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 587);
            $mail->SMTPAuth = !empty($_ENV['SMTP_USER']);
            $mail->Username = $_ENV['SMTP_USER'] ?? '';
            $mail->Password = $_ENV['SMTP_PASS'] ?? '';
            $mail->SMTPSecure = $mail->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout = 5;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($remetente, 'Nuvix');
            $mail->addAddress($resumo['email']);
            $mail->Subject = 'Pedido #' . $resumo['idPedido'] . ' registrado na Nuvix';
            $mail->isHTML(true);
            $total = number_format($resumo['total'], 2, ',', '.');
            $mail->Body = '<h1>Pedido registrado</h1><p>Seu pedido #' . (int) $resumo['idPedido'] . ' foi registrado. Total: R$ ' . $total . '.</p><p>O pagamento ainda não foi realizado.</p>';
            $mail->AltBody = 'Seu pedido #' . $resumo['idPedido'] . ' foi registrado. Total: R$ ' . $total . '. O pagamento ainda não foi realizado.';
            return $mail->send();
        } catch (\Throwable $e) {
            // Falha de notificação não desfaz um pedido já registrado.
            error_log('Nuvix: confirmação por e-mail não enviada. Confira a configuração SMTP.');
            return false;
        }
    }
}
