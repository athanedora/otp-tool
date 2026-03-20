<?php
header('Content-Type: application/json');

const STORE_FILE = __DIR__ . '/password_store.json';

function json_out(array $arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function key_from_secret(string $secret): string {
    return hash('sha256', $secret, true);
}

function decrypt_store(string $encoded, string $secret): array {
    $raw = base64_decode(trim($encoded), true);
    if ($raw === false || strlen($raw) < 17) {
        throw new Exception('Invalid encrypted store format.');
    }

    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $key = key_from_secret($secret);

    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new Exception('Decryption failed. Wrong secret or bad file content.');
    }

    $obj = json_decode($plain, true);
    if (!is_array($obj)) {
        throw new Exception('Decrypted content is not valid JSON.');
    }

    if (!isset($obj['passwords']) || !is_array($obj['passwords'])) {
        throw new Exception('Invalid store JSON structure: passwords array missing.');
    }

    return $obj;
}

function encrypt_store(array $obj, string $secret): string {
    $plain = json_encode($obj, JSON_UNESCAPED_SLASHES);
    if ($plain === false) {
        throw new Exception('JSON encode failed.');
    }

    $iv = random_bytes(16);
    $key = key_from_secret($secret);

    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new Exception('Encryption failed.');
    }

    return base64_encode($iv . $cipher);
}

$action = $_GET['action'] ?? '';
$input = get_json_input();
$secret = trim($input['secret'] ?? '');

if ($secret === '') {
    json_out(['success' => false, 'message' => 'Secret is required.'], 400);
}

try {
    if (!file_exists(STORE_FILE)) {
        throw new Exception('Store file not found: ' . STORE_FILE);
    }

    if (!is_readable(STORE_FILE)) {
        throw new Exception('Store file is not readable.');
    }

    if ($action === 'count') {
        $encoded = trim((string) file_get_contents(STORE_FILE));
        if ($encoded === '') {
            throw new Exception('Store file is empty.');
        }

        $obj = decrypt_store($encoded, $secret);
        json_out([
            'success' => true,
            'count' => count($obj['passwords']),
            'updated_at' => $obj['updated_at'] ?? null,
        ]);
    }

    if ($action === 'consume') {
        $fp = fopen(STORE_FILE, 'c+');
        if (!$fp) {
            throw new Exception('Unable to open store file.');
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new Exception('Unable to lock store file.');
            }

            rewind($fp);
            $encoded = trim((string) stream_get_contents($fp));
            if ($encoded === '') {
                throw new Exception('Store file is empty.');
            }

            $obj = decrypt_store($encoded, $secret);

            if (empty($obj['passwords'])) {
                flock($fp, LOCK_UN);
                fclose($fp);
                json_out(['success' => false, 'message' => 'No passwords left.']);
            }

            $otp = array_shift($obj['passwords']);
            $obj['updated_at'] = gmdate('c');
            $newEncoded = encrypt_store($obj, $secret);

            rewind($fp);
            if (!ftruncate($fp, 0)) {
                throw new Exception('Failed to truncate store file.');
            }
            if (fwrite($fp, $newEncoded) === false) {
                throw new Exception('Failed to write updated store file.');
            }
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            json_out([
                'success' => true,
                'password' => $otp,
                'remaining' => count($obj['passwords']),
            ]);
        } catch (Throwable $e) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw $e;
        }
    }

    json_out(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    json_out(['success' => false, 'message' => $e->getMessage()], 500);
}
