<?php

class EmailValidator
{
    /**
     * التحقق من صحة البريد الإلكتروني
     * @param string $email البريد الإلكتروني
     * @return array ['is_valid' => bool, 'message' => string]
     */
    public static function validate($email)
    {
        $email = trim((string)$email);

        if (empty($email)) {
            return [
                'is_valid' => false,
                'message' => 'البريد الإلكتروني مطلوب'
            ];
        }

        if (strlen($email) > 150) {
            return [
                'is_valid' => false,
                'message' => 'البريد الإلكتروني طويل جداً (حد أقصى 150 حرف)'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'is_valid' => false,
                'message' => 'صيغة البريد الإلكتروني غير صحيحة'
            ];
        }

        if (!self::hasValidDomain($email)) {
            return [
                'is_valid' => false,
                'message' => 'نطاق البريد الإلكتروني غير صالح'
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'صحيح'
        ];
    }

    /**
     * التحقق من صحة نطاق البريد الإلكتروني
     * @param string $email البريد الإلكتروني
     * @return bool هل النطاق صحيح
     */
    private static function hasValidDomain($email)
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];

        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return false;
        }

        return true;
    }

    /**
     * تنظيف وتطهير البريد الإلكتروني
     * @param string $email البريد الإلكتروني
     * @return string البريد المنظف
     */
    public static function sanitize($email)
    {
        $email = trim((string)$email);
        $email = strtolower($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        return $email;
    }

    /**
     * الكشف عن رسائل البريد المريبة
     * @param string $subject الموضوع
     * @param string $body محتوى الرسالة
     * @return array ['is_suspicious' => bool, 'reasons' => array]
     */
    public static function detectSpam($subject, $body)
    {
        $reasons = [];
        $isSuspicious = false;

        $combined = strtolower($subject . ' ' . $body);

        $spamPatterns = [
            'verify.*account' => 'طلب التحقق من الحساب (نمط مريب)',
            'confirm.*password' => 'طلب تأكيد كلمة المرور (نمط مريب)',
            'click.*urgent' => 'طلب عاجل للنقر (نمط مريب)',
            'act.*immediately' => 'طلب التصرف الفوري (نمط مريب)',
            'update.*payment' => 'طلب تحديث البيانات المالية (نمط مريب)',
            'suspended|blocked' => 'تصريح بإيقاف الحساب (نمط مريب)',
            'bitcoin|ethereum|crypto' => 'عملات رقمية (احتمال احتيال)',
            'lottery|prize|claim' => 'جائزة/يانصيب (احتمال احتيال)',
        ];

        foreach ($spamPatterns as $pattern => $reason) {
            if (preg_match('/' . $pattern . '/i', $combined)) {
                $reasons[] = $reason;
                $isSuspicious = true;
            }
        }

        $excessiveCapitals = preg_match_all('/[A-Z]{5,}/', $subject);
        if ($excessiveCapitals > 2) {
            $reasons[] = 'أحرف كبيرة مفرطة (نمط مريب)';
            $isSuspicious = true;
        }

        $excessiveExclamation = substr_count($subject . $body, '!!!') + substr_count($subject . $body, '???');
        if ($excessiveExclamation > 5) {
            $reasons[] = 'علامات ترقيم مفرطة (نمط مريب)';
            $isSuspicious = true;
        }

        return [
            'is_suspicious' => $isSuspicious,
            'reasons' => $reasons
        ];
    }

    /**
     * البحث عن روابط مريبة في المحتوى
     * @param string $content محتوى الرسالة
     * @return array ['has_suspicious_links' => bool, 'links' => array]
     */
    public static function findSuspiciousLinks($content)
    {
        $suspiciousLinks = [];
        
        if (preg_match_all('/(https?:\/\/[^\s]+)/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (self::isLinkSuspicious($url)) {
                    $suspiciousLinks[] = $url;
                }
            }
        }

        return [
            'has_suspicious_links' => count($suspiciousLinks) > 0,
            'links' => $suspiciousLinks
        ];
    }

    /**
     * التحقق من مدى استريبة الرابط
     * @param string $url الرابط
     * @return bool هل الرابط مريب
     */
    private static function isSuspicious($url)
    {
        $url = strtolower($url);

        if (preg_match('/javascript:|data:|vbscript:/i', $url)) {
            return true;
        }

        $suspiciousKeywords = [
            'bit.ly',
            'tinyurl',
            'short.link',
            'goo.gl',
            'ow.ly',
        ];

        foreach ($suspiciousKeywords as $keyword) {
            if (strpos($url, $keyword) !== false) {
                return true;
            }
        }

        if (preg_match('/^https?:\/\/(\d+\.)+\d+\//', $url)) {
            return true;
        }

        return false;
    }

    /**
     * التحقق من استريبة الرابط بشكل أدق
     * @param string $url الرابط
     * @return bool هل الرابط مريب
     */
    private static function isLinkSuspicious($url)
    {
        return self::isSuspiciousUrl($url);
    }

    /**
     * التحقق من سلامة الرابط
     * @param string $url الرابط
     * @return bool هل الرابط آمن
     */
    public static function isSuspiciousUrl($url)
    {
        $url = strtolower(trim($url));

        if (!preg_match('/^https?:\/\//', $url)) {
            return true;
        }

        $suspiciousPatterns = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'about:',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($url, $pattern) === 0) {
                return true;
            }
        }

        $shortenerServices = [
            'bit.ly', 'tinyurl.com', 'short.link', 'goo.gl', 'ow.ly',
            'j.mp', 'buff.ly', 'adf.ly', 'shorte.st',
        ];

        foreach ($shortenerServices as $service) {
            if (strpos($url, $service) !== false) {
                return true;
            }
        }

        if (preg_match('/^https?:\/\/(\d{1,3}\.){3}\d{1,3}/', $url)) {
            return true;
        }

        return false;
    }

    /**
     * الحصول على قائمة البريد الإلكترونات غير الصحيحة
     * @param array $emails قائمة البريدات
     * @return array البريدات غير الصحيحة
     */
    public static function filterInvalidEmails($emails)
    {
        $invalid = [];
        
        foreach ($emails as $email) {
            $validation = self::validate($email);
            if (!$validation['is_valid']) {
                $invalid[] = [
                    'email' => $email,
                    'reason' => $validation['message']
                ];
            }
        }

        return $invalid;
    }

    /**
     * إنشاء قائمة نظيفة من البريدات
     * @param array $emails قائمة البريدات
     * @return array البريدات النظيفة والصحيحة
     */
    public static function sanitizeEmailList($emails)
    {
        $clean = [];
        
        foreach ($emails as $email) {
            $sanitized = self::sanitize($email);
            $validation = self::validate($sanitized);
            
            if ($validation['is_valid']) {
                $clean[] = $sanitized;
            }
        }

        return array_unique($clean);
    }
}
