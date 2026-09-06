<?php

namespace App\Helpers;

class ContactHelper
{
    /**
     * Format phone number for WhatsApp (remove + and spaces).
     */
    public static function formatPhoneForWhatsApp(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // WhatsApp links need country code. Default local 10-digit numbers to India.
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }

    /**
     * Get WhatsApp URL.
     */
    public static function getWhatsAppUrl(string $phone, bool $isMobile = null): string
    {
        $formattedPhone = self::formatPhoneForWhatsApp($phone);

        if ($isMobile === null) {
            $isMobile = self::isMobileDevice();
        }

        if ($isMobile) {
            return "https://wa.me/{$formattedPhone}";
        }

        return "https://web.whatsapp.com/send?phone={$formattedPhone}";
    }

    /**
     * Get call URL.
     */
    public static function getCallUrl(string $phone): string
    {
        // Format phone for tel: protocol
        $formattedPhone = preg_replace('/[^0-9+]/', '', $phone);

        // Add + if not present and phone starts with country code
        if (!str_starts_with($formattedPhone, '+')) {
            if (strlen($formattedPhone) === 10) {
                $formattedPhone = '+91' . $formattedPhone;
            } else {
                $formattedPhone = '+' . $formattedPhone;
            }
        }

        return "tel:{$formattedPhone}";
    }

    /**
     * Check if device is mobile.
     */
    public static function isMobileDevice(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $mobileAgents = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];

        foreach ($mobileAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }

        return false;
    }
}
