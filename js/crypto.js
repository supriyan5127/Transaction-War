const SECRET_KEY_STRING = 'Xq9F3vT7mD2kP1nYzH4wR6cM8bJ5gL0s'; // Must be secure 32 bytes

async function getEncryptionKey() {
    const enc = new TextEncoder();
    return await crypto.subtle.importKey(
        'raw', 
        enc.encode(SECRET_KEY_STRING), 
        { name: 'AES-GCM' }, 
        false, 
        ['encrypt']
    );
}

// Intercept original fetch
const originalFetch = window.fetch;

window.fetch = async function () {
    let [resource, config] = arguments;

    // We only encrypt POST requests hitting our API
    if (config && config.method === 'POST' && resource.includes('api/')) {
        try {
            const rawBody = config.body; // Can be FormData or JSON String
            
            // Generate a random IV for AES-GCM
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const key = await getEncryptionKey();
            const enc = new TextEncoder();
            
            let dataToEncrypt = '';
            
            // Handle FormData (Profile Updates) vs JSON (Auth/Transfers)
            if (rawBody instanceof FormData) {
                // Not encrypting binary file uploads for simplicity, just the text fields
                const plainObject = {};
                rawBody.forEach((value, key) => { 
                    if(typeof value === 'string') plainObject[key] = value; 
                });
                dataToEncrypt = JSON.stringify(plainObject);
                // Attach the original form data to still pass the file natively
                config.encryptedFormData = true; 
            } else {
                dataToEncrypt = rawBody;
            }

            // Encrypt
            const encryptedBuffer = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv: iv },
                key,
                enc.encode(dataToEncrypt)
            );

            // Convert to Base64
            const encryptedArray = Array.from(new Uint8Array(encryptedBuffer));
            const ivArray = Array.from(iv);
            const cipherTextB64 = btoa(String.fromCharCode.apply(null, encryptedArray));
            const ivB64 = btoa(String.fromCharCode.apply(null, ivArray));

            const securePayload = JSON.stringify({
                encrypted_payload: cipherTextB64,
                iv: ivB64
            });

            // If it was form data, we still need to send the file.
            if (config.encryptedFormData) {
                rawBody.append('secure_data', securePayload);
                config.body = rawBody; // File stays raw, text is encrypted inside 'secure_data'
            } else {
                config.body = securePayload;
                // Ensure headers
                config.headers = {
                    ...config.headers,
                    'Content-Type': 'application/json'
                };
            }
            
        } catch (e) {
            console.error('Encryption failed:', e);
            // Block the request if encryption fails to prevent plain-text leaks
            return Promise.reject(new Error("Security Error: Failed to encrypt payload."));
        }
    }
    
    // Proceed with the (now encrypted) fetch
    return originalFetch(resource, config);
};
