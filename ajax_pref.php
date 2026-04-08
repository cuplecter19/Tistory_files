<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_db.php');

header('Content-Type: application/json; charset=utf-8');

@ini_set('display_errors', '0');
error_reporting(0);

gcal_ensure_tables();

$pref_table = $g5['prefix'].'calendar_user_pref';
$mb_id = $is_member ? $member['mb_id'] : '';

$VALID_THEMES = array('sakura','ocean','melon','kuromi','mocha','lemon');
$HEADER_UPLOAD_DATA_DIR = 'calendar_header';
$HEADER_MAX_BYTES = 5 * 1024 * 1024;
$HEADER_ALLOWED_MIME_EXT = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
);

function cal_starts_with($text, $prefix) {
    return strpos($text, $prefix) === 0;
}

function cal_is_safe_header_filename($filename) {
    if (!is_string($filename) || $filename === '') return false;
    if ($filename === '.' || $filename === '..') return false;
    if (strpos($filename, '..') !== false) return false;
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) return false;
    return true;
}

function cal_is_private_ip_address($ip) {
    if (!is_string($ip) || $ip === '') return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        if ($long === false) return false;
        $ranges = array(
            array(ip2long('10.0.0.0'), ip2long('10.255.255.255')),
            array(ip2long('172.16.0.0'), ip2long('172.31.255.255')),
            array(ip2long('192.168.0.0'), ip2long('192.168.255.255')),
            array(ip2long('127.0.0.0'), ip2long('127.255.255.255')),
            array(ip2long('169.254.0.0'), ip2long('169.254.255.255')),
            array(ip2long('0.0.0.0'), ip2long('0.255.255.255'))
        );
        foreach ($ranges as $range) {
            if ($long >= $range[0] && $long <= $range[1]) return true;
        }
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $lower = strtolower($ip);
        if ($lower === '::1') return true;
        if (cal_starts_with($lower, 'fc') || cal_starts_with($lower, 'fd')) return true;
        if (preg_match('/^fe[89a-f]/', $lower)) return true;
    }
    return false;
}

function cal_extract_local_header_filename($src, $upload_data_dir) {
    if (!is_string($src) || $src === '') return false;

    $src = trim($src);
    $parts = parse_url($src);
    if (!is_array($parts)) {
        $parts = array();
        $parts['path'] = $src;
    }

    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), array('http', 'https'))) return false;

    if (isset($parts['host'])) {
        $src_host = strtolower($parts['host']);
        $site_host = parse_url(G5_URL, PHP_URL_HOST);
        $data_host = parse_url(G5_DATA_URL, PHP_URL_HOST);
        $site_host = is_string($site_host) ? strtolower($site_host) : '';
        $data_host = is_string($data_host) ? strtolower($data_host) : '';
        if ($src_host !== $site_host && $src_host !== $data_host) return false;
    }

    $path = isset($parts['path']) ? $parts['path'] : '';
    if ($path === '') return false;
    $path = str_replace('\\', '/', $path);
    $path = rawurldecode($path);
    if (strpos($path, "\0") !== false) return false;
    if (preg_match('#/(?:\.{1,2})(?:/|$)#', $path)) return false;
    if (substr($path, 0, 1) !== '/') $path = '/'.$path;
    $path = preg_replace('#/+#', '/', $path);

    $upload_tail = trim($upload_data_dir, '/').'/';
    $prefixes = array('/'.$upload_tail, '/data/'.$upload_tail);
    $data_base_path = parse_url(G5_DATA_URL, PHP_URL_PATH);
    if (is_string($data_base_path) && $data_base_path !== '') {
        $prefixes[] = rtrim($data_base_path, '/').'/'.$upload_tail;
    }

    foreach ($prefixes as $prefix) {
        if (cal_starts_with($path, $prefix)) {
            $filename = basename($path);
            if (cal_is_safe_header_filename($filename)) {
                return $filename;
            }
            return false;
        }
    }

    // 경로 형태가 달라도 동일 사이트 내 calendar_header 파일이면 허용
    $upload_dir_name = trim($upload_data_dir, '/');
    if ($upload_dir_name !== '' && preg_match('#/'.preg_quote($upload_dir_name, '#').'/([^/]+)$#i', $path, $m) && cal_is_safe_header_filename($m[1])) {
        return $m[1];
    }
    return false;
}

function cal_is_local_header_src($src, $upload_data_dir) {
    return cal_extract_local_header_filename($src, $upload_data_dir) !== false;
}

function cal_normalize_local_header_src($src, $upload_data_dir) {
    $filename = cal_extract_local_header_filename($src, $upload_data_dir);
    if ($filename === false) return '';
    return rtrim(G5_DATA_URL, '/').'/'.trim($upload_data_dir, '/').'/'.$filename;
}

function cal_remove_local_header_file($src, $upload_data_dir) {
    $filename = cal_extract_local_header_filename($src, $upload_data_dir);
    if ($filename === false) return;
    $path = rtrim(G5_DATA_PATH, '/').'/'.trim($upload_data_dir, '/').'/'.$filename;
    if (is_file($path)) @unlink($path);
}

function cal_ensure_header_upload_dir($upload_data_dir) {
    $dir = rtrim(G5_DATA_PATH, '/').'/'.trim($upload_data_dir, '/');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_dir($dir) || !is_writable($dir)) return false;
    return $dir;
}

function cal_make_random_token() {
    $rand = '';
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(8);
        if ($bytes !== false) $rand = bin2hex($bytes);
    }
    if ($rand === '') $rand = substr(sha1(uniqid('', true)), 0, 16);
    return $rand;
}

function cal_max_size_error($max_bytes) {
    $mb = $max_bytes / (1024 * 1024);
    if ($mb === intval($mb)) return 'max '.intval($mb).'MB';
    return 'max '.round($mb, 2).'MB';
}

function cal_fetch_remote_image_binary($url, $max_bytes, &$error) {
    $error = '';
    $body = false;
    $original_host = parse_url($url, PHP_URL_HOST);
    $original_host = is_string($original_host) ? strtolower($original_host) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $body = '';
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'calendar-header-fetcher');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$body, $max_bytes) {
            $body .= $chunk;
            if (strlen($body) > $max_bytes) return 0;
            return strlen($chunk);
        });
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        $result = curl_exec($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        $write_error_no = defined('CURLE_WRITE_ERROR') ? CURLE_WRITE_ERROR : 23;
        if ($result === false || $curl_errno === $write_error_no) {
            if (strlen($body) > $max_bytes) {
                $error = cal_max_size_error($max_bytes);
            } else {
                $error = 'image download failed';
            }
            $body = false;
        }
        if ($body !== false && ($status < 200 || $status >= 300)) {
            $error = 'image download failed';
            $body = false;
        }
        if ($body !== false && is_string($effective_url) && $effective_url !== '') {
            $effective_error = '';
            if (!cal_validate_remote_image_url($effective_url, $effective_error)) {
                $error = 'blocked host';
                $body = false;
            } else {
                $effective_host = parse_url($effective_url, PHP_URL_HOST);
                $effective_host = is_string($effective_host) ? strtolower($effective_host) : '';
                if ($original_host !== '' && $effective_host !== '' && $effective_host !== $original_host) {
                    $error = 'blocked host';
                    $body = false;
                }
            }
        }
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => 15,
                'max_redirects' => 3,
                'follow_location' => 1,
                'protocol_version' => 1.1,
                'header'  => "User-Agent: calendar-header-fetcher\r\n"
            )
        ));
        $fp = @fopen($url, 'rb', false, $context);
        if ($fp) {
            $body = @stream_get_contents($fp, $max_bytes + 1);
            @fclose($fp);
        } else {
            $body = false;
        }
        if ($body === false) $error = 'image download failed';
    }

    if (!is_string($body) || $body === '') {
        if ($error === '') $error = 'image download failed';
        return false;
    }
    if (strlen($body) > $max_bytes) {
        $error = cal_max_size_error($max_bytes);
        return false;
    }
    return $body;
}

function cal_detect_image_mime($binary, $allowed_mime_ext) {
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_buffer($finfo, $binary);
            finfo_close($finfo);
        }
    }
    if (!$mime && function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($binary);
        if (is_array($info) && isset($info['mime'])) $mime = $info['mime'];
    }
    if (isset($allowed_mime_ext[$mime])) return $mime;
    return '';
}

function cal_save_image_binary($binary, $upload_data_dir, $max_bytes, $allowed_mime_ext, &$error) {
    $error = '';
    if (!is_string($binary) || $binary === '') {
        $error = 'invalid image data';
        return false;
    }
    if (strlen($binary) > $max_bytes) {
        $error = cal_max_size_error($max_bytes);
        return false;
    }

    $mime = cal_detect_image_mime($binary, $allowed_mime_ext);
    if (!$mime) {
        $error = 'invalid image type';
        return false;
    }

    $dir = cal_ensure_header_upload_dir($upload_data_dir);
    if ($dir === false) {
        $error = 'upload dir not writable';
        return false;
    }

    $filename = 'header_'.cal_make_random_token().'.'.$allowed_mime_ext[$mime];
    $path = $dir.'/'.$filename;
    $written = @file_put_contents($path, $binary, LOCK_EX);
    if ($written === false) {
        $error = 'save failed';
        return false;
    }
    return rtrim(G5_DATA_URL, '/').'/'.trim($upload_data_dir, '/').'/'.$filename;
}

function cal_save_data_uri_image($src, $upload_data_dir, $max_bytes, $allowed_mime_ext, &$error) {
    $error = '';
    if (!preg_match('/^data:image\/([a-z0-9.+-]+);base64,(.+)$/i', $src, $m)) {
        $error = 'invalid image src';
        return false;
    }
    $raw_type = strtolower($m[1]);
    if ($raw_type === 'jpg') $raw_type = 'jpeg';
    $expected_mime = 'image/'.$raw_type;
    if (!isset($allowed_mime_ext[$expected_mime])) {
        $error = 'invalid image type';
        return false;
    }
    // Some clients can convert '+' to spaces during HTTP transmission; normalize before decoding.
    $binary = base64_decode(str_replace(' ', '+', $m[2]), true);
    if ($binary === false) {
        $error = 'invalid image src';
        return false;
    }
    return cal_save_image_binary($binary, $upload_data_dir, $max_bytes, $allowed_mime_ext, $error);
}

function cal_validate_remote_image_url($src, &$error) {
    $error = '';
    if (filter_var($src, FILTER_VALIDATE_URL) === false) {
        $error = 'invalid image src';
        return false;
    }
    $parts = parse_url($src);
    if (!is_array($parts) || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'))) {
        $error = 'invalid image src';
        return false;
    }
    if (empty($parts['host'])) {
        $error = 'invalid image src';
        return false;
    }

    $host = strtolower($parts['host']);
    if ($host === 'localhost') {
        $error = 'blocked host';
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP) && cal_is_private_ip_address($host)) {
        $error = 'blocked host';
        return false;
    }

    if (function_exists('gethostbynamel')) {
        $ips = @gethostbynamel($host);
        if (is_array($ips) && count($ips) > 0) {
            foreach ($ips as $ip) {
                if (cal_is_private_ip_address($ip)) {
                    $error = 'blocked host';
                    return false;
                }
            }
        }
    }

    return true;
}

function cal_save_remote_image($src, $upload_data_dir, $max_bytes, $allowed_mime_ext, &$error) {
    $error = '';
    if (!cal_validate_remote_image_url($src, $error)) return false;
    $binary = cal_fetch_remote_image_binary($src, $max_bytes, $error);
    if ($binary === false) return false;
    return cal_save_image_binary($binary, $upload_data_dir, $max_bytes, $allowed_mime_ext, $error);
}

function cal_parse_header_image_payload($input) {
    if (is_array($input)) {
        return array('ok' => true, 'data' => $input);
    }

    if (!is_string($input)) {
        return array('ok' => false, 'data' => null);
    }

    $raw = trim($input);
    if ($raw === '' || strtolower($raw) === 'null') {
        return array('ok' => true, 'data' => null);
    }

    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        return array('ok' => true, 'data' => $parsed);
    }

    // 레거시/이중 인코딩 대응
    if (is_string($parsed)) {
        $parsed2 = json_decode($parsed, true);
        if (is_array($parsed2)) {
            return array('ok' => true, 'data' => $parsed2);
        }
        $raw = trim($parsed);
    }

    // 레거시 포맷: src 문자열만 전송된 경우
    return array(
        'ok' => true,
        'data' => array(
            'src' => $raw,
            'type' => 'url'
        )
    );
}

/* ── GET: 설정 불러오기 (로그인 불필요 – 헤더 이미지는 전역 공유) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 전역 헤더 이미지는 __global__ 레코드에서 읽기 (비로그인 방문자도 접근 가능)
    $global_row = sql_fetch("SELECT header_image FROM {$pref_table} WHERE mb_id='__global__'");
    $header_image = null;
    if ($global_row && $global_row['header_image']) {
        $header_image = json_decode($global_row['header_image'], true);
    }

    // 테마는 개인별로 저장 (로그인한 경우에만)
    $theme = 'sakura';
    if ($is_member && $mb_id) {
        $user_row = sql_fetch("SELECT theme FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        if ($user_row && $user_row['theme'] && in_array($user_row['theme'], $VALID_THEMES)) {
            $theme = $user_row['theme'];
        }
    }

    echo json_encode(array(
        'success' => true,
        'theme' => $theme,
        'header_image' => $header_image
    ));
    exit;
}

/* ── POST: 설정 저장 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = isset($_POST['theme']) ? trim($_POST['theme']) : '';
    $has_header_image_input = array_key_exists('header_image', $_POST);
    $header_image_input = $has_header_image_input ? $_POST['header_image'] : null;

    // Validate theme
    if ($theme && !in_array($theme, $VALID_THEMES)) {
        $theme = '';
    }

    // 테마 저장: 로그인한 회원만 가능 (개인 설정)
    if ($theme) {
        if (!$is_member) {
            echo json_encode(array('success'=>false,'error'=>'login required')); exit;
        }
        $sets_theme = array(
            "theme='".sql_real_escape_string($theme)."'",
            "updated_at=NOW()"
        );
        $existing = sql_fetch("SELECT mb_id FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        if ($existing && $existing['mb_id']) {
            sql_query("UPDATE {$pref_table} SET ".implode(',', $sets_theme)." WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        } else {
            sql_query("INSERT INTO {$pref_table}
                SET mb_id='".sql_real_escape_string($mb_id)."',
                    theme='".sql_real_escape_string($theme)."',
                    header_image=NULL,
                    updated_at=NOW()");
        }
        echo json_encode(array('success'=>true));
        exit;
    }

    // 헤더 이미지 저장: 관리자만 가능 (전역 공유 설정)
    if ($has_header_image_input) {
        if (!$is_admin) {
            echo json_encode(array('success'=>false,'error'=>'admin required')); exit;
        }

        $header_image_json = null;
        $payload = cal_parse_header_image_payload($header_image_input);
        if (!$payload['ok']) {
            echo json_encode(array('success'=>false,'error'=>'invalid header_image data')); exit;
        }
        $parsed = $payload['data'];
        if (is_array($parsed) && isset($parsed['src'])) {
            $src = trim($parsed['src']);
            if ($src !== '') {
                $final_src = '';
                $save_error = '';
                if (cal_is_local_header_src($src, $HEADER_UPLOAD_DATA_DIR)) {
                    $final_src = cal_normalize_local_header_src($src, $HEADER_UPLOAD_DATA_DIR);
                } elseif (preg_match('/^data:image\//i', $src)) {
                    $final_src = cal_save_data_uri_image($src, $HEADER_UPLOAD_DATA_DIR, $HEADER_MAX_BYTES, $HEADER_ALLOWED_MIME_EXT, $save_error);
                } else {
                    $final_src = cal_save_remote_image($src, $HEADER_UPLOAD_DATA_DIR, $HEADER_MAX_BYTES, $HEADER_ALLOWED_MIME_EXT, $save_error);
                }

                if (!$final_src) {
                    echo json_encode(array('success'=>false,'error'=>$save_error ? $save_error : 'invalid image src')); exit;
                }

                $safe = array(
                    'src' => $final_src,
                    'type' => 'file',
                    'height' => isset($parsed['height']) ? max(60, min(400, intval($parsed['height']))) : 160,
                    'fit' => isset($parsed['fit']) && in_array($parsed['fit'], array('cover','contain','fill')) ? $parsed['fit'] : 'cover'
                );
                $header_image_json = json_encode($safe);
            }
        } elseif (is_array($parsed)) {
            echo json_encode(array('success'=>false,'error'=>'invalid header_image data')); exit;
        } elseif ($parsed !== null) {
            echo json_encode(array('success'=>false,'error'=>'invalid header_image data')); exit;
        }

        // 전역 설정 UPSERT (__global__ 레코드)
        $global_existing = sql_fetch("SELECT mb_id, header_image FROM {$pref_table} WHERE mb_id='__global__'");
        $old_src = '';
        if ($global_existing && !empty($global_existing['header_image'])) {
            $old_data = json_decode($global_existing['header_image'], true);
            if (is_array($old_data) && !empty($old_data['src'])) {
                $old_src = $old_data['src'];
            }
        }

        $new_src = '';
        if ($header_image_json) {
            $new_data = json_decode($header_image_json, true);
            if (is_array($new_data) && !empty($new_data['src'])) {
                $new_src = $new_data['src'];
            }
        }

        $himg_val = $header_image_json ? "'".sql_real_escape_string($header_image_json)."'" : "NULL";
        if ($global_existing && $global_existing['mb_id']) {
            sql_query("UPDATE {$pref_table} SET header_image={$himg_val}, updated_at=NOW() WHERE mb_id='__global__'");
        } else {
            sql_query("INSERT INTO {$pref_table}
                SET mb_id='__global__',
                    theme='sakura',
                    header_image={$himg_val},
                    updated_at=NOW()");
        }

        if ($old_src && $old_src !== $new_src) {
            cal_remove_local_header_file($old_src, $HEADER_UPLOAD_DATA_DIR);
        }

        echo json_encode(array('success'=>true));
        exit;
    }

    echo json_encode(array('success'=>false,'error'=>'no data')); exit;
}

echo json_encode(array('success'=>false,'error'=>'invalid method'));
