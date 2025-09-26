<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SettingsRepository.php';

class AppConfig
{
    private static ?PDO $pdo = null;
    private static ?SettingsRepository $repo = null;

    private static function repo(): SettingsRepository
    {
        if (self::$repo === null) {
            self::$pdo = Database::getConnection();
            self::$repo = new SettingsRepository(self::$pdo);
        }
        return self::$repo;
    }

    // Default fallback constants (used if DB not set)
    private const DEF_TWILIO_SID = 'ACded19f6cd55b2ba3d18c13f438f1e878';
    private const DEF_TWILIO_TOKEN = '7f1136b112e6d8cb4a6af94223d0872e';
    private const DEF_WHATSAPP_FROM = 'whatsapp:+2250711048002';
    private const DEF_GERANTE_WA = '+2250788202420';
    private const DEF_GERANTE_SMS = '2250788202420';

    // Keys in DB
    private const K_TWILIO_SID = 'twilio.sid';
    private const K_TWILIO_TOKEN = 'twilio.token';
    private const K_WHATSAPP_FROM = 'twilio.whatsapp.from';
    private const K_GERANTE_WA = 'gerante.whatsapp';
    private const K_GERANTE_SMS = 'gerante.sms';

    public static function twilioSid(): string
    {
        return self::repo()->get(self::K_TWILIO_SID) ?? self::DEF_TWILIO_SID;
    }
    public static function twilioToken(): string
    {
        return self::repo()->get(self::K_TWILIO_TOKEN) ?? self::DEF_TWILIO_TOKEN;
    }
    public static function whatsappFrom(): string
    {
        return self::repo()->get(self::K_WHATSAPP_FROM) ?? self::DEF_WHATSAPP_FROM;
    }
    public static function geranteWhatsapp(): string
    {
        return self::repo()->get(self::K_GERANTE_WA) ?? self::DEF_GERANTE_WA;
    }
    public static function geranteSms(): string
    {
        return self::repo()->get(self::K_GERANTE_SMS) ?? self::DEF_GERANTE_SMS;
    }

    // Setters (utilisés par l'admin UI)
    public static function setTwilioSid(?string $v): bool
    {
        return self::repo()->set(self::K_TWILIO_SID, $v);
    }
    public static function setTwilioToken(?string $v): bool
    {
        return self::repo()->set(self::K_TWILIO_TOKEN, $v);
    }
    public static function setWhatsappFrom(?string $v): bool
    {
        return self::repo()->set(self::K_WHATSAPP_FROM, $v);
    }
    public static function setGeranteWhatsapp(?string $v): bool
    {
        return self::repo()->set(self::K_GERANTE_WA, $v);
    }
    public static function setGeranteSms(?string $v): bool
    {
        return self::repo()->set(self::K_GERANTE_SMS, $v);
    }
}
