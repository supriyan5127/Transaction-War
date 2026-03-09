<?php
// crypto.php: Handles AES-GCM decryption for blocked incoming payloads
define('AEAD_KEY', 'Xq9F3vT7mD2kP1nYzH4wR6cM8bJ5gL0s');

function decrypt_payload($input_json) {
    if (empty($input_json)) return null;

    $req = json_decode($input_json, true);
    
    // If not encrypted structure, immediately reject to enforce encryption
    if (!isset($req['encrypted_payload']) || !isset($req['iv'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security Error: Unencrypted payloads are blocked.']);
        exit;
    }

    $cipher_b64 = $req['encrypted_payload'];
    $iv_b64 = $req['iv'];

    $cipher = base64_decode($cipher_b64);
    $iv = base64_decode($iv_b64);

    // GCM needs the auth tag appended to the ciphertext in PHP
    // In WebCrypto API, the auth tag (16 bytes) is appended to the cipher text natively
    $tag_length = 16;
    $tag = substr($cipher, -$tag_length);
    $ciphertext = substr($cipher, 0, -$tag_length);

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
        echo json_encode(['status' => 'error', 'message' => 'Security Error: Tampered Payload Detected.']);
        exit;
    }

    // Return the original payload associative array
    return json_decode($decrypted, true);
}
?>
