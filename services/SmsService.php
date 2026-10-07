<?php
// services/SmsService.php - Abstraction for Client SMS Notifications

require_once __DIR__ . '/../db.php';

class SmsService {
    private $provider;
    private $apiKey;
    private $senderId;
    private $templateId;

    public function __construct() {
        // Read configuration from environment variables or defined constants with fallback defaults
        $this->provider   = getenv('SMS_PROVIDER')   ?: (defined('SMS_PROVIDER')   ? SMS_PROVIDER   : 'MOCK');
        $this->apiKey     = getenv('SMS_API_KEY')     ?: (defined('SMS_API_KEY')     ? SMS_API_KEY     : '');
        $this->senderId   = getenv('SMS_SENDER_ID')   ?: (defined('SMS_SENDER_ID')   ? SMS_SENDER_ID   : 'AGENCY');
        $this->templateId = getenv('SMS_TEMPLATE_ID') ?: (defined('SMS_TEMPLATE_ID') ? SMS_TEMPLATE_ID : '');
    }

    /**
     * Send SMS to client recipient.
     * Guaranteed to never throw an unhandled exception or disrupt client transaction flow.
     */
    public function sendSms($clientId, $phone, $message, $templateId = null) {
        $templateId = $templateId ?: $this->templateId;
        $db = getDbConnection();
        $status = 'FAILED';
        $providerResponse = '';

        try {
            if (empty($phone)) {
                $providerResponse = 'Skipped: Phone number empty';
            } elseif ($this->provider === 'MOCK' || empty($this->apiKey)) {
                // Mock Provider mode (Safe default when API keys are not supplied)
                $status = 'MOCK_SENT';
                $providerResponse = json_encode([
                    'status' => 'success',
                    'mode' => 'mock_provider',
                    'message' => 'SMS logged to database. Configured SMS Provider: ' . $this->provider,
                    'phone' => $phone,
                    'sender_id' => $this->senderId
                ]);
            } else {
                // Generic HTTP SMS Provider Interface
                // Can integrate with Twilio, MSG91, Fast2SMS, etc.
                $providerResponse = $this->dispatchHttpSms($phone, $message, $templateId);
                $status = 'SENT';
            }
        } catch (Exception $e) {
            $status = 'FAILED';
            $providerResponse = 'SMS Dispatch Error: ' . $e->getMessage();
            error_log($providerResponse);
        }

        // Log SMS dispatch result into database
        try {
            $stmt = $db->prepare("INSERT INTO sms_logs (client_id, recipient_phone, message, status, provider_response) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $phone, $message, $status, $providerResponse]);
        } catch (Exception $e) {
            // Silently swallow database log error so core transaction continues
        }

        return [
            'success' => ($status === 'SENT' || $status === 'MOCK_SENT'),
            'status' => $status,
            'response' => $providerResponse
        ];
    }

    /**
     * Internal HTTP dispatcher wrapper for external SMS APIs
     */
    private function dispatchHttpSms($phone, $message, $templateId) {
        // HTTP API interface wrapper using cURL or stream context
        $url = getenv('SMS_GATEWAY_URL') ?: 'https://api.sms-provider.com/v1/send';
        
        $postData = json_encode([
            'api_key' => $this->apiKey,
            'sender' => $this->senderId,
            'to' => $phone,
            'message' => $message,
            'template_id' => $templateId
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        return $result;
    }
}
