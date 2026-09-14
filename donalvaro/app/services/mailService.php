<?php

declare(strict_types=1);

/**
 * mailService
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 23 jul 2026
 */
class mailService
{
    private customMailer $mailer;
    private newSmarty $template;

    public function __construct()
    {
        $this->mailer = new customMailer();
        $this->template = new newSmarty();
    }

    public function sendAccountActivation(
        string $email,
        string $name,
        string $activationUrl
    ): array {
        $email = strtolower(trim($email));
        $name = trim($name);
        $activationUrl = trim($activationUrl);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico de destino no es válido.',
                            'EMAIL_INVALID'
                    );
        }

        if ($activationUrl === '') {
            return serviceResponse::error(
                            'No se ha podido generar el enlace de activación.',
                            'ACTIVATION_URL_EMPTY'
                    );
        }

        try {
            $this->template->assign([
                'user_name' => $name,
                'activation_url' => $activationUrl
            ]);

            $body = $this->template->fetch(
                    'emails/account_activation.html'
            );

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    'Activa tu cuenta en Don Álvaro Digital',
                    $body,
                    'Activa tu cuenta accediendo al siguiente enlace: '
                    . $activationUrl
            );

            if (!$sent) {
                return serviceResponse::error(
                                'La cuenta se ha creado, pero no se ha podido enviar el correo de activación.',
                                'ACCOUNT_ACTIVATION_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de activación enviado correctamente.',
                            'ACCOUNT_ACTIVATION_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            $log = new logsModel('mail', 'mailService.log');

            $log->error(
                    'Error al preparar o enviar el correo de activación',
                    [
                        'email' => $email,
                        'exception' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
            );

            return serviceResponse::error(
                            'No se ha podido preparar el correo de activación.',
                            'ACCOUNT_ACTIVATION_EMAIL_TEMPLATE_ERROR'
                    );
        }
    }

    public function sendSetPassword(
            string $email,
            string $name,
            string $setPasswordUrl
    ): array {

        $email = strtolower(trim($email));
        $name = trim($name);
        $setPasswordUrl = trim($setPasswordUrl);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico de destino no es válido.',
                            'EMAIL_INVALID'
                    );
        }

        if ($setPasswordUrl === '') {
            return serviceResponse::error(
                            'No se ha podido generar el enlace para establecer la contraseña.',
                            'SET_PASSWORD_URL_EMPTY'
                    );
        }

        try {

            $this->template->assign([
                'user_name' => $name,
                'set_password_url' => $setPasswordUrl
            ]);

            $body = $this->template->fetch(
                    'emails/set_password.html'
            );

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    'Establece tu contraseña en Don Álvaro Digital',
                    $body,
                    'Se ha creado una cuenta para ti en Don Álvaro Digital. '
                    . 'Establece tu contraseña accediendo al siguiente enlace: '
                    . $setPasswordUrl
            );

            if (!$sent) {
                return serviceResponse::error(
                                'La cuenta se ha creado, pero no se ha podido enviar el correo para establecer la contraseña.',
                                'SET_PASSWORD_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo para establecer la contraseña enviado correctamente.',
                            'SET_PASSWORD_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            $log = new logsModel('mail', 'mailService.log');

            $log->error(
                    'Error al preparar o enviar el correo para establecer la contraseña',
                    [
                        'email' => $email,
                        'exception' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
            );

            return serviceResponse::error(
                            'No se ha podido preparar el correo para establecer la contraseña.',
                            'SET_PASSWORD_EMAIL_TEMPLATE_ERROR'
                    );
        }
    }
    
    
    public function sendReservationAccess(
            array $user,
            array $facility,
            array $reservation,
            string $pin,
            string $valid_from,
            string $valid_until
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $facility_name = trim((string) ($facility['name'] ?? ''));
        $reference = trim((string) ($reservation['reference'] ?? ''));
        $pin = trim($pin);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'RESERVATION_ACCESS_EMAIL_INVALID'
                    );
        }

        if ($facility_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la instalación de la reserva.',
                            'RESERVATION_ACCESS_FACILITY_EMPTY'
                    );
        }

        if ($reference === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la referencia de la reserva.',
                            'RESERVATION_ACCESS_REFERENCE_EMPTY'
                    );
        }

        if ($pin === '') {
            return serviceResponse::error(
                            'No se ha podido determinar el PIN de acceso.',
                            'RESERVATION_ACCESS_PIN_EMPTY'
                    );
        }

        if ($valid_from === '' || $valid_until === '') {
            return serviceResponse::error(
                            'No se ha podido determinar el periodo de validez del PIN.',
                            'RESERVATION_ACCESS_DATES_EMPTY'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('facility_name', $facility_name);
            $this->template->assign('reference', $reference);
            $this->template->assign('pin', $pin);
            $this->template->assign('valid_from', date('d/m/Y H:i', strtotime($valid_from)));
            $this->template->assign('valid_until', date('d/m/Y H:i', strtotime($valid_until)));

            $body = $this->template->fetch('emails/reservation_access.html');

            $subject = 'Reserva confirmada - ' . $reference;

            $plain_text = 'Su reserva ' . $reference
                    . ' para ' . $facility_name
                    . ' está confirmada. '
                    . 'Código de acceso: ' . $pin . '. '
                    . 'Válido desde ' . date('d/m/Y H:i', strtotime($valid_from))
                    . ' hasta ' . date('d/m/Y H:i', strtotime($valid_until)) . '.';

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'La reserva se ha confirmado, pero no se ha podido enviar el correo con el código de acceso.',
                                'RESERVATION_ACCESS_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo con el código de acceso enviado correctamente.',
                            'RESERVATION_ACCESS_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_ACCESS_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendPendingReservation(
            array $user,
            array $facility,
            array $reservation
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $facility_name = trim((string) ($facility['name'] ?? ''));
        $reference = trim((string) ($reservation['reference'] ?? ''));
        $instructions = trim((string) ($facility['pending_payment_instructions'] ?? ''));
        $booking_conditions = trim((string) ($facility['booking_conditions'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'PENDING_RESERVATION_EMAIL_INVALID'
                    );
        }

        if ($facility_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la instalación de la reserva.',
                            'PENDING_RESERVATION_FACILITY_EMPTY'
                    );
        }

        if ($reference === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la referencia de la reserva.',
                            'PENDING_RESERVATION_REFERENCE_EMPTY'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('facility_name', $facility_name);
            $this->template->assign('reference', $reference);
            $this->template->assign('start_at', date('d/m/Y H:i', strtotime($reservation['start_at'])));
            $this->template->assign('end_at', date('d/m/Y H:i', strtotime($reservation['end_at'])));
            $this->template->assign('people_count', (int) ($reservation['people_count'] ?? 0));
            $this->template->assign('total_amount', number_format((float) ($reservation['total_amount'] ?? 0), 2, ',', '.'));
            $this->template->assign('pending_payment_instructions', $instructions);
            $this->template->assign('booking_conditions', $booking_conditions);

            $body = $this->template->fetch('emails/reservation_pending.html');

            $subject = 'Reserva pendiente - ' . $reference;

            $plain_text = 'Su reserva ' . $reference
                    . ' para ' . $facility_name
                    . ' se ha registrado y está pendiente de confirmación. '
                    . 'Importe: ' . number_format((float) ($reservation['total_amount'] ?? 0), 2, ',', '.') . ' €.';

            if ($instructions !== '') {
                $plain_text .= ' ' . $instructions;
            }

            if ($booking_conditions !== '') {
                $plain_text .= ' Condiciones de reserva: ' . $booking_conditions;
            }

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'La reserva se ha creado, pero no se ha podido enviar el correo informativo.',
                                'PENDING_RESERVATION_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de reserva pendiente enviado correctamente.',
                            'PENDING_RESERVATION_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'PENDING_RESERVATION_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendReservationCancelled(
            array $user,
            array $facility,
            array $reservation,
            string $status_code,
            bool $access_revoked = false,
            ?string $reason = null
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $facility_name = trim((string) ($facility['name'] ?? ''));
        $reference = trim((string) ($reservation['reference'] ?? ''));
        $reason = $reason !== null ? trim($reason) : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'RESERVATION_CANCELLED_EMAIL_INVALID'
                    );
        }

        if ($facility_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la instalación de la reserva.',
                            'RESERVATION_CANCELLED_FACILITY_EMPTY'
                    );
        }

        if ($reference === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la referencia de la reserva.',
                            'RESERVATION_CANCELLED_REFERENCE_EMPTY'
                    );
        }

        if ($status_code === 'cancelled') {

            $status_title = 'Reserva cancelada';
            $status_message = 'Su reserva ha sido cancelada y ya no se encuentra activa.';
        } elseif ($status_code === 'rejected') {

            $status_title = 'Reserva rechazada';
            $status_message = 'Su reserva ha sido rechazada y ya no se encuentra activa.';
        } else {

            return serviceResponse::error(
                            'El estado indicado no es válido para este correo.',
                            'RESERVATION_CANCELLED_STATUS_INVALID'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('facility_name', $facility_name);
            $this->template->assign('reference', $reference);
            $this->template->assign('status_title', $status_title);
            $this->template->assign('status_message', $status_message);
            $this->template->assign('start_at', date('d/m/Y H:i', strtotime($reservation['start_at'])));
            $this->template->assign('end_at', date('d/m/Y H:i', strtotime($reservation['end_at'])));
            $this->template->assign('access_revoked', $access_revoked);
            $this->template->assign('reason', $reason);

            $body = $this->template->fetch('emails/reservation_cancelled.html');

            $subject = $status_title . ' - ' . $reference;

            $plain_text = $status_message
                    . ' Referencia: ' . $reference . '. '
                    . 'Instalación: ' . $facility_name . '. '
                    . 'Periodo: '
                    . date('d/m/Y H:i', strtotime($reservation['start_at']))
                    . ' - '
                    . date('d/m/Y H:i', strtotime($reservation['end_at']))
                    . '.';

            if ($reason !== null && $reason !== '') {
                $plain_text .= ' Motivo: ' . $reason . '.';
            }

            if ($access_revoked) {
                $plain_text .= ' El código de acceso asociado a esta reserva ha sido revocado.';
            }

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'El estado de la reserva se ha actualizado, pero no se ha podido enviar el correo informativo.',
                                'RESERVATION_CANCELLED_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo informativo enviado correctamente.',
                            'RESERVATION_CANCELLED_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_CANCELLED_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendReservationConfirmed(
            array $user,
            array $facility,
            array $reservation
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $facility_name = trim((string) ($facility['name'] ?? ''));
        $reference = trim((string) ($reservation['reference'] ?? ''));
        $booking_conditions = trim((string) ($facility['booking_conditions'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'RESERVATION_CONFIRMED_EMAIL_INVALID'
                    );
        }

        if ($facility_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la instalación de la reserva.',
                            'RESERVATION_CONFIRMED_FACILITY_EMPTY'
                    );
        }

        if ($reference === '') {
            return serviceResponse::error(
                            'No se ha podido determinar la referencia de la reserva.',
                            'RESERVATION_CONFIRMED_REFERENCE_EMPTY'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('facility_name', $facility_name);
            $this->template->assign('reference', $reference);
            $this->template->assign('start_at', date('d/m/Y H:i', strtotime($reservation['start_at'])));
            $this->template->assign('end_at', date('d/m/Y H:i', strtotime($reservation['end_at'])));
            $this->template->assign('people_count', (int) ($reservation['people_count'] ?? 0));
            $this->template->assign('total_amount', number_format((float) ($reservation['total_amount'] ?? 0), 2, ',', '.'));
            $this->template->assign('booking_conditions', $booking_conditions);

            $body = $this->template->fetch('emails/reservation_confirmed.html');

            $subject = 'Reserva confirmada - ' . $reference;

            $plain_text = 'Su reserva ' . $reference
                    . ' para ' . $facility_name
                    . ' ha sido confirmada. '
                    . 'Periodo: '
                    . date('d/m/Y H:i', strtotime($reservation['start_at']))
                    . ' - '
                    . date('d/m/Y H:i', strtotime($reservation['end_at']))
                    . '.';

            if ($booking_conditions !== '') {
                $plain_text .= ' Condiciones de reserva: ' . $booking_conditions;
            }

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'La reserva se ha confirmado, pero no se ha podido enviar el correo informativo.',
                                'RESERVATION_CONFIRMED_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de confirmación enviado correctamente.',
                            'RESERVATION_CONFIRMED_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_CONFIRMED_EMAIL_EXCEPTION'
                    );
        }
    }
    
    public function sendPendingBonus(
            array $user,
            string $facilities,
            array $bonus
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $facilities = trim($facilities);
        $bonus_name = trim((string) ($bonus['name'] ?? ''));
        $instructions = trim((string) ($bonus['pending_payment_instructions'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'PENDING_BONUS_EMAIL_INVALID'
                    );
        }

        if ($facilities === '') {
            return serviceResponse::error(
                            'No se han podido determinar las instalaciones del bono.',
                            'PENDING_BONUS_FACILITIES_EMPTY'
                    );
        }

        if ($bonus_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar el bono adquirido.',
                            'PENDING_BONUS_NAME_EMPTY'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('facilities', $facilities);
            $this->template->assign('bonus_name', $bonus_name);
            $this->template->assign('pending_payment_instructions', $instructions);
            $this->template->assign('bonus_type', (string) ($bonus['bonus_type'] ?? bonusesModel::TYPE_USES));
            $this->template->assign('validity_mode', (string) ($bonus['validity_mode'] ?? bonusesModel::VALIDITY_DAYS));
            $this->template->assign('total_uses', (int) ($bonus['total_uses'] ?? 0));
            $this->template->assign('validity_days', (int) ($bonus['validity_days'] ?? 0));
            $this->template->assign('price', number_format((float) ($bonus['price'] ?? 0), 2, ',', '.'));

            $body = $this->template->fetch('emails/bonus_pending.html');

            $subject = 'Bono pendiente de confirmación - ' . $bonus_name;

            $plain_text = 'Su bono "' . $bonus_name . '" se ha registrado correctamente y está pendiente de confirmación. '
                    . 'Instalaciones disponibles: ' . $facilities . '. '
                    . 'Importe: ' . number_format((float) ($bonus['price'] ?? 0), 2, ',', '.') . ' €.';

            if ($instructions !== '') {
                $plain_text .= ' ' . $instructions;
            }

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'El bono se ha registrado, pero no se ha podido enviar el correo informativo.',
                                'PENDING_BONUS_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de bono pendiente enviado correctamente.',
                            'PENDING_BONUS_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'PENDING_BONUS_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendBonusConfirmed(
            array $user,
            array $bonus,
            ?array $access_result = null
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $bonus_name = trim((string) ($bonus['name'] ?? $bonus['bonus_name'] ?? ''));
        $bonus_type = (string) ($bonus['bonus_type'] ?? bonusesModel::TYPE_USES);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'BONUS_CONFIRMED_EMAIL_INVALID'
                    );
        }

        if ($bonus_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar el bono.',
                            'BONUS_CONFIRMED_NAME_EMPTY'
                    );
        }

        try {

            $bonus_pin = null;
            $access_facilities = [];
            $reservation_facilities = [];

            if ($bonus_type === bonusesModel::TYPE_TIME && !empty($access_result)) {
                $bonus_pin = !empty($access_result['pin']) ? (string) $access_result['pin'] : null;
                $access_facilities = $access_result['access_facilities'] ?? [];
                $reservation_facilities = $access_result['reservation_facilities'] ?? [];
            }

            $valid_from = date('d/m/Y', strtotime($bonus['valid_from']));
            $expires_at = !empty($bonus['expires_at']) ? date('d/m/Y', strtotime($bonus['expires_at'])) : null;

            $this->template->assign('name', $name);
            $this->template->assign('bonus_name', $bonus_name);
            $this->template->assign('bonus_type', $bonus_type);
            $this->template->assign('total_uses', (int) ($bonus['total_uses'] ?? 0));
            $this->template->assign('price', number_format((float) ($bonus['purchase_price'] ?? 0), 2, ',', '.'));
            $this->template->assign('valid_from', $valid_from);
            $this->template->assign('expires_at', $expires_at);

            $this->template->assign('bonus_pin', $bonus_pin);
            $this->template->assign('access_facilities', $access_facilities);
            $this->template->assign('reservation_facilities', $reservation_facilities);

            $body = $this->template->fetch('emails/bonus_confirmed.html');

            $subject = 'Bono confirmado - ' . $bonus_name;

            $plain_text = 'Su bono "' . $bonus_name . '" ha sido confirmado y ya está activo. '
                    . 'Válido desde ' . $valid_from . '.';

            if (!empty($expires_at)) {
                $plain_text .= ' Fecha de caducidad: ' . $expires_at . '.';
            }

            if ($bonus_type === bonusesModel::TYPE_TIME) {

                if (!empty($bonus_pin) && !empty($access_facilities)) {

                    $facility_names = array_map(
                            fn($facility) => (string) ($facility['name'] ?? ''),
                            $access_facilities
                    );

                    $facility_names = array_filter($facility_names);

                    $plain_text .= ' PIN de acceso: ' . $bonus_pin . '.';

                    if (!empty($facility_names)) {
                        $plain_text .= ' Este PIN puede utilizarse durante la vigencia del bono en: '
                                . implode(', ', $facility_names) . '.';
                    }
                }

                if (!empty($reservation_facilities)) {

                    $facility_names = array_map(
                            fn($facility) => (string) ($facility['name'] ?? ''),
                            $reservation_facilities
                    );

                    $facility_names = array_filter($facility_names);

                    if (!empty($facility_names)) {
                        $plain_text .= ' Para acceder a ' . implode(', ', $facility_names)
                                . ' es necesario realizar previamente una reserva. '
                                . 'Se generará un PIN específico para cada reserva.';
                    }
                }
            }

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'El bono se ha activado, pero no se ha podido enviar el correo informativo.',
                                'BONUS_CONFIRMED_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de confirmación del bono enviado correctamente.',
                            'BONUS_CONFIRMED_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'BONUS_CONFIRMED_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendBonusCancelled(
            array $user,
            array $bonus
    ): array {

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $bonus_name = trim((string) ($bonus['bonus_name'] ?? $bonus['name'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico del cliente no es válido.',
                            'BONUS_CANCELLED_EMAIL_INVALID'
                    );
        }

        if ($bonus_name === '') {
            return serviceResponse::error(
                            'No se ha podido determinar el bono.',
                            'BONUS_CANCELLED_NAME_EMPTY'
                    );
        }

        try {

            $this->template->assign('name', $name);
            $this->template->assign('bonus_name', $bonus_name);
            $this->template->assign('bonus_type', (string) ($bonus['bonus_type'] ?? bonusesModel::TYPE_USES));
            $this->template->assign('remaining_uses', (int) ($bonus['remaining_uses'] ?? 0));
            $this->template->assign('purchase_price', number_format((float) ($bonus['purchase_price'] ?? 0), 2, ',', '.'));

            $body = $this->template->fetch('emails/bonus_cancelled.html');

            $subject = 'Bono cancelado - ' . $bonus_name;

            $plain_text = 'Su bono "' . $bonus_name . '" ha sido cancelado y ya no se encuentra disponible.';

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'El bono se ha eliminado, pero no se ha podido enviar el correo informativo.',
                                'BONUS_CANCELLED_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Correo de cancelación del bono enviado correctamente.',
                            'BONUS_CANCELLED_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'BONUS_CANCELLED_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendGatewayOffline(array $gateway): array {

        $email = defined('_SYSTEM_ALERT_EMAIL') ? trim((string) _SYSTEM_ALERT_EMAIL) : '';
        $name = defined('_SYSTEM_ALERT_NAME') ? trim((string) _SYSTEM_ALERT_NAME) : 'Pueblo Digital - Alertas';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico de alertas del sistema no es válido.',
                            'SYSTEM_ALERT_EMAIL_INVALID'
                    );
        }

        try {

            $gateway_name = trim((string) ($gateway['gateway_name'] ?? ''));
            $gateway_mac = trim((string) ($gateway['gateway_mac'] ?? ''));
            $ttlock_gateway_id = (int) ($gateway['ttlock_gateway_id'] ?? 0);

            $subject = 'Gateway TTLock desconectado - ' . ($gateway_name !== '' ? $gateway_name : $ttlock_gateway_id);

            $body = '<p>Se ha detectado que un gateway TTLock está desconectado.</p>'
                    . '<p><strong>Nombre:</strong> ' . htmlspecialchars($gateway_name) . '</p>'
                    . '<p><strong>TTLock ID:</strong> ' . $ttlock_gateway_id . '</p>'
                    . '<p><strong>MAC:</strong> ' . htmlspecialchars($gateway_mac) . '</p>'
                    . '<p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>';

            $plain_text = 'Se ha detectado que un gateway TTLock está desconectado. '
                    . 'Nombre: ' . $gateway_name . '. '
                    . 'TTLock ID: ' . $ttlock_gateway_id . '. '
                    . 'MAC: ' . $gateway_mac . '. '
                    . 'Fecha: ' . date('d/m/Y H:i:s') . '.';

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'No se ha podido enviar el aviso de gateway desconectado.',
                                'GATEWAY_OFFLINE_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Aviso de gateway desconectado enviado correctamente.',
                            'GATEWAY_OFFLINE_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'GATEWAY_OFFLINE_EMAIL_EXCEPTION'
                    );
        }
    }

    public function sendGatewayOnline(array $gateway): array {

        $email = defined('_SYSTEM_ALERT_EMAIL') ? trim((string) _SYSTEM_ALERT_EMAIL) : '';
        $name = defined('_SYSTEM_ALERT_NAME') ? trim((string) _SYSTEM_ALERT_NAME) : 'Pueblo Digital - Alertas';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return serviceResponse::error(
                            'El correo electrónico de alertas del sistema no es válido.',
                            'SYSTEM_ALERT_EMAIL_INVALID'
                    );
        }

        try {

            $gateway_name = trim((string) ($gateway['gateway_name'] ?? ''));
            $gateway_mac = trim((string) ($gateway['gateway_mac'] ?? ''));
            $ttlock_gateway_id = (int) ($gateway['ttlock_gateway_id'] ?? 0);

            $subject = 'Gateway TTLock reconectado - ' . ($gateway_name !== '' ? $gateway_name : $ttlock_gateway_id);

            $body = '<p>El gateway TTLock vuelve a estar conectado.</p>'
                    . '<p><strong>Nombre:</strong> ' . htmlspecialchars($gateway_name) . '</p>'
                    . '<p><strong>TTLock ID:</strong> ' . $ttlock_gateway_id . '</p>'
                    . '<p><strong>MAC:</strong> ' . htmlspecialchars($gateway_mac) . '</p>'
                    . '<p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>';

            $plain_text = 'El gateway TTLock vuelve a estar conectado. '
                    . 'Nombre: ' . $gateway_name . '. '
                    . 'TTLock ID: ' . $ttlock_gateway_id . '. '
                    . 'MAC: ' . $gateway_mac . '. '
                    . 'Fecha: ' . date('d/m/Y H:i:s') . '.';

            $this->mailer->setAddress([
                [
                    'email' => $email,
                    'name' => $name
                ]
            ]);

            $sent = $this->mailer->sendStandardMail(
                    $subject,
                    $body,
                    $plain_text
            );

            if (!$sent) {
                return serviceResponse::error(
                                'No se ha podido enviar el aviso de gateway reconectado.',
                                'GATEWAY_ONLINE_EMAIL_NOT_SENT'
                        );
            }

            return serviceResponse::success(
                            'Aviso de gateway reconectado enviado correctamente.',
                            'GATEWAY_ONLINE_EMAIL_SENT'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'GATEWAY_ONLINE_EMAIL_EXCEPTION'
                    );
        }
    }
}
