<?php

class SecurityManager
{
    private static $encryptionKey = null;

    /**
     * تشفير نص باستخدام AES-256-GCM
     * @param string $plaintext النص المراد تشفيره
     * @return string النص المشفر (base64 مع IV و tag)
     */
    public static function encrypt($plaintext)
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('فشل التشفير: ' . openssl_error_string());
        }

        $encrypted = base64_encode($iv . $tag . $ciphertext);
        return $encrypted;
    }

    /**
     * فك التشفير من نص مشفر
     * @param string $encrypted النص المشفر (base64)
     * @return string النص الأصلي
     */
    public static function decrypt($encrypted)
    {
        try {
            $key = self::getEncryptionKey();
            $data = base64_decode($encrypted, true);
            
            if ($data === false) {
                throw new RuntimeException('فشل فك تشفير البيانات المشفرة: invalid base64');
            }

            $iv = substr($data, 0, 16);
            $tag = substr($data, 16, 16);
            $ciphertext = substr($data, 32);

            if (strlen($iv) !== 16 || strlen($tag) !== 16) {
                throw new RuntimeException('بيانات التشفير تالفة');
            }

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plaintext === false) {
                throw new RuntimeException('فشل فك التشفير أو البيانات تالفة');
            }

            return $plaintext;
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على مفتاح التشفير الثابت (256-بت = 32 بايت)
     * يجب أن يكون المفتاح معرّف في environment أو كثابت
     * @return string مفتاح التشفير (32 بايت)
     */
    private static function getEncryptionKey()
    {
        if (self::$encryptionKey === null) {
            $key = getenv('ENCRYPTION_KEY');
            
            if (!$key) {
                $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '';
            }

            if (empty($key)) {
                throw new RuntimeException('مفتاح التشفير غير محدد. يجب تعريف ENCRYPTION_KEY في المتغيرات البيئية');
            }

            if (strlen($key) < 32) {
                $key = hash('sha256', $key, true);
            } else {
                $key = substr($key, 0, 32);
            }

            self::$encryptionKey = $key;
        }

        return self::$encryptionKey;
    }

    /**
     * إنشاء hash آمن للبريد الإلكتروني (للخصوصية في السجلات)
     * @param string $email البريد الإلكتروني
     * @return string hash آمن
     */
    public static function hashEmail($email)
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * التحقق من سلامة رابط (للقضاء على الروابط المريبة)
     * @param string $url الرابط المراد التحقق منه
     * @return bool هل الرابط آمن
     */
    public static function isSafeUrl($url)
    {
        $url = strtolower($url);
        
        $suspiciousPatterns = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'about:',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($url, $pattern) === 0) {
                return false;
            }
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    /**
     * تطهير محتوى الرسالة من محتويات مريبة
     * @param string $content محتوى الرسالة
     * @return string محتوى نظيف
     */
    public static function sanitizeEmailContent($content)
    {
        $content = strip_tags($content, '<p><br><strong><em><a><ul><li><div><span>');
        
        $content = preg_replace_callback('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', function($matches) {
            if (!self::isSafeUrl($matches[1])) {
                return '';
            }
            return $matches[0];
        }, $content);

        return $content;
    }

    /**
     * التحقق من قوة كلمة المرور (للمتطلبات الأمنية)
     * @param string $password كلمة المرور
     * @return array ['is_strong' => bool, 'message' => string]
     */
    public static function validatePasswordStrength($password)
    {
        $result = [
            'is_strong' => true,
            'message' => 'قوية'
        ];

        if (strlen($password) < 8) {
            $result['is_strong'] = false;
            $result['message'] = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
            return $result;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $result['is_strong'] = false;
            $result['message'] = 'يجب أن تحتوي على حرف كبير';
            return $result;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $result['is_strong'] = false;
            $result['message'] = 'يجب أن تحتوي على حرف صغير';
            return $result;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $result['is_strong'] = false;
            $result['message'] = 'يجب أن تحتوي على رقم';
            return $result;
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
            $result['is_strong'] = false;
            $result['message'] = 'يجب أن تحتوي على رمز خاص';
            return $result;
        }

        return $result;
    }
}
