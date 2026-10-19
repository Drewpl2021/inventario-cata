<?php

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el enlace para restablecer la contraseña (plantilla propia en español).
 */
class RestablecerPassword extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notifiable): MailMessage
    {
        $datos = [
            'usuario' => $notifiable,
            'url' => route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]),
            'minutos' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ];

        return (new MailMessage)
            ->subject('Restablecer su contraseña · SIBIP')
            ->view(['html' => 'emails.restablecer-password', 'text' => 'emails.restablecer-password-texto'], $datos);
    }
}
