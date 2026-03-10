<?php
// crypto.php: Handles AES-GCM decryption for blocked incoming payloads
define('AEAD_KEY', 'Xq9F3vT7mD2kP1nYzH4wR6cM8bJ5gL0s');

function decrypt_payload($input_json) {
    if (empty($input_json)) return null;

    $req = json_decode($input_json, true);
    
    // Strict structure enforcement
    if (!is_array($req) || empty($req['encrypted_payload']) || empty($req['iv'])) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Security Error: Invalid or missing encryption headers.']));
    }

    $cipher_b64 = $req['encrypted_payload'];
    $iv_b64 = $req['iv'];

    $cipher = base64_decode($cipher_b64, true);
    $iv = base64_decode($iv_b64, true);

    if ($cipher === false || $iv === false || strlen($iv) !== 12) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Security Error: Malformed encryption parameters.']));
    }

    $tag_length = 16;
    if (strlen($cipher) <= $tag_length) {
         http_response_code(403);
         die(json_encode(['status' => 'error', 'message' => 'Security Error: Ciphertext too short.']));
    }

    $tag = substr($cipher, -$tag_length);
    $ciphertext = substr($cipher, 0, -$tag_length);

    // AES-256-GCM Decryption
    $decrypted = openssl_decrypt(
        $ciphertext, 
        'aes-256-gcm', 
        AEAD_KEY, 
        OPENSSL_RAW_DATA, 
        $iv, 
        $tag
    );

    if ($decrypted === false) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Security Error: Cryptographic verification failed (Tampering detected).']));
    }

    $payload = json_decode($decrypted, true);
    if (!is_array($payload)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Security Error: Payload structure is invalid after decryption.']));
    }

    return $payload;
}
?>
