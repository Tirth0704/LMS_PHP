<?php

class CloudinaryService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config['cloudinary'] ?? [];
    }

    public function isConfigured(): bool
    {
        $cloudName = $this->config['cloud_name'] ?? getenv('CLOUDINARY_CLOUD_NAME');
        return !empty($cloudName);
    }

    public function uploadFile(string $filePath, string $publicId = ''): ?string
    {
        $cloudName = $this->config['cloud_name'] ?? getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = $this->config['api_key'] ?? getenv('CLOUDINARY_API_KEY');
        $apiSecret = $this->config['api_secret'] ?? getenv('CLOUDINARY_API_SECRET');
        $uploadPreset = $this->config['upload_preset'] ?? getenv('CLOUDINARY_UPLOAD_PRESET');

        if (empty($cloudName) || !is_file($filePath)) {
            return null;
        }

        $timestamp = time();
        $url = "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload";

        $postData = [
            'file' => new CURLFile($filePath, 'application/pdf', basename($filePath)),
            'timestamp' => $timestamp,
        ];

        if (!empty($uploadPreset)) {
            $postData['upload_preset'] = $uploadPreset;
        } elseif (!empty($apiKey) && !empty($apiSecret)) {
            $postData['api_key'] = $apiKey;
            $paramsToSign = ['timestamp' => $timestamp];
            if ($publicId !== '') {
                $postData['public_id'] = $publicId;
                $paramsToSign['public_id'] = $publicId;
            }
            ksort($paramsToSign);
            $signStr = [];
            foreach ($paramsToSign as $k => $v) {
                $signStr[] = "{$k}={$v}";
            }
            $stringToSign = implode('&', $signStr) . $apiSecret;
            $postData['signature'] = sha1($stringToSign);
        } else {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $json = json_decode($response, true);
            return $json['secure_url'] ?? null;
        }

        return null;
    }
}
