<?php
namespace IDM\Membership;
if (!defined('ABSPATH')) exit;

class Auth {
    const COOKIE_NAME = 'idm_member'; // ←独自会員用クッキー名
    const COOKIE_TTL  = 1209600;      // 14日

    public function login(int $member_id): void {
        $exp = time() + self::COOKIE_TTL;
        $payload = $member_id.'|'.$exp;
        $sig = hash_hmac('sha256', $payload, wp_salt('auth').'idm-membership');
        $val = $payload.'|'.$sig;
        $this->set_cookie($val, $exp);
    }

    public function current_member() {
        if (empty($_COOKIE[self::COOKIE_NAME])) return 0;
        $parts = explode('|', $_COOKIE[self::COOKIE_NAME]);
        if (count($parts) !== 3) return 0;

        [$mid, $exp, $sig] = $parts;
        if ((int)$exp < time()) return 0;

        $calc = hash_hmac('sha256', $mid.'|'.$exp, wp_salt('auth').'idm-membership');
        if (!hash_equals($calc, $sig)) return 0;

        global $wpdb;
        $t = $wpdb->prefix.'idm_members';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND status=1", (int)$mid), ARRAY_A);
        return $row ?: 0;
    }

    public function logout(): void {
        // ★WP本体のログアウトはしない！
        // wp_logout();
        // wp_clear_auth_cookie();

        $this->set_cookie('', time() - YEAR_IN_SECONDS); // 独自クッキーだけ破棄
    }

    private function set_cookie(string $value, int $exp): void {
        $opts = [
            'expires'  => $exp,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::COOKIE_NAME, $value, $opts);
        if (COOKIEPATH !== SITECOOKIEPATH) {
            $opts['path'] = SITECOOKIEPATH;
            setcookie(self::COOKIE_NAME, $value, $opts);
        }
    }
}
