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
    private const DEF_GERANTE_SMS = '+2250716222201';

    // Keys in DB
    private const K_TWILIO_SID = 'twilio.sid';
    private const K_TWILIO_TOKEN = 'twilio.token';
    private const K_WHATSAPP_FROM = 'twilio.whatsapp.from';
    private const K_GERANTE_WA = 'gerante.whatsapp';
    private const K_GERANTE_SMS = 'gerante.sms';
    // OCR / IA keys
    private const K_OCR_TESSERACT_PATH = 'ocr.tesseract.path';
    private const K_OCR_LANG = 'ocr.lang';
    private const K_OCR_PSM = 'ocr.psm';
    private const K_OCR_ENGINE = 'ocr.engine'; // 'tesseract' | 'paddle'

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

    // OCR / IA configuration
    public static function ocrTesseractPath(): string
    {
        $default = stripos(PHP_OS_FAMILY, 'Windows') !== false
            ? 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'
            : 'tesseract';
        return self::repo()->get(self::K_OCR_TESSERACT_PATH) ?? $default;
    }
    public static function ocrLang(): string
    {
        return self::repo()->get(self::K_OCR_LANG) ?? 'fra+eng';
    }
    public static function ocrPsm(): string
    {
        return self::repo()->get(self::K_OCR_PSM) ?? '6';
    }
    public static function ocrEngine(): string
    {
        $v = self::repo()->get(self::K_OCR_ENGINE) ?? 'tesseract';
        $v = in_array($v, ['tesseract', 'paddle'], true) ? $v : 'tesseract';
        return $v;
    }

    public static function setOcrTesseractPath(?string $v): bool
    {
        return self::repo()->set(self::K_OCR_TESSERACT_PATH, $v);
    }
    public static function setOcrLang(?string $v): bool
    {
        return self::repo()->set(self::K_OCR_LANG, $v);
    }
    public static function setOcrPsm(?string $v): bool
    {
        return self::repo()->set(self::K_OCR_PSM, $v);
    }
    public static function setOcrEngine(?string $v): bool
    {
        return self::repo()->set(self::K_OCR_ENGINE, $v);
    }

    // OpenAI / GPT configuration via variables d'environnement
    public static function openAiApiKey(): string
    {
        return getenv('OPENAI_API_KEY') ?: '';
    }
    public static function openAiModel(): string
    {
        // Choix par défaut raisonnable; ajustable via OPENAI_MODEL
        return getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
    }
    public static function openAiBaseUrl(): string
    {
        $u = getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1';
        return rtrim($u, '/');
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
