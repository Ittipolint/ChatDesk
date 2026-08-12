<?php
/**
 * ChatDesk — ไฟล์ตั้งค่าหลัก (ตัวอย่าง)
 * -----------------------------------------------------------------------------
 * คัดลอกไฟล์นี้เป็น config.php แล้วแก้ค่าให้ตรงกับ server ของคุณ
 *   cp config.sample.php config.php
 *
 * (getenv() ใช้ตอนรันด้วย Docker เท่านั้น — บน hosting จะใช้ค่าหลัง , เสมอ)
 */

if (!function_exists('cd_env')) {
    function cd_env($key, $default)
    {
        if (getenv('CD_ENV_CONFIG') !== '1') {
            return $default;
        }
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        if (is_bool($default)) {
            return in_array(strtolower($v), array('1', 'true', 'yes', 'on'), true);
        }
        if (is_int($default)) {
            return (int) $v;
        }
        return $v;
    }
}

return array(

    /* ---------------------------------------------------------------------
     * 1) ฐานข้อมูล — ใช้ database เดียวกับระบบอื่นได้ (ตารางขึ้นต้นด้วย cd_)
     * ------------------------------------------------------------------- */
    'db' => array(
        'host'    => cd_env('DB_HOST', 'localhost'),
        'port'    => cd_env('DB_PORT', 3306),
        'name'    => cd_env('DB_NAME', 'chatdesk'),        // เปลี่ยนเป็นชื่อ database ของคุณ
        'user'    => cd_env('DB_USER', 'chatdesk'),        // เปลี่ยนเป็น username ของคุณ
        'pass'    => cd_env('DB_PASS', 'your_db_password'), // เปลี่ยนเป็น password ของคุณ
        'charset' => 'utf8mb4',
    ),

    /* ---------------------------------------------------------------------
     * 2) การเชื่อมต่อ n8n
     * ------------------------------------------------------------------- */
    'n8n' => array(
        // URL ของ webhook ที่ใช้ "ส่งข้อความออกไปหาลูกค้าทาง LINE"
        // (workflow: ChatDesk Manager — Push) — ใส่ URL จริงของคุณ
        'push_url' => cd_env('N8N_PUSH_URL', 'https://your-n8n-host/webhook/chatdesk-push'),

        // URL ของ webhook ที่ใช้ "ส่งข้อความออกไปหาลูกค้าทาง Facebook Messenger"
        // (workflow: ChatDesk Manager — Push FB) — ใส่ URL จริงของคุณ
        'push_fb_url' => cd_env('N8N_PUSH_FB_URL', 'https://your-n8n-host/webhook/chatdesk-push-fb'),

        'timeout'  => cd_env('N8N_TIMEOUT', 30),

        // รหัสลับที่ n8n ต้องส่งมาด้วยตอนยิงเข้า api/incoming.php
        // เว้นว่าง = ไม่ตรวจ (สะดวกตอนทดสอบ) — แนะนำให้ตั้งค่าในการใช้งานจริง
        'secret'   => cd_env('N8N_SECRET', ''),
    ),

    /* ---------------------------------------------------------------------
     * 3) หน้าจัดการ
     * ------------------------------------------------------------------- */
    'app' => array(
        'title'        => cd_env('APP_TITLE', 'ChatDesk'),
        'subtitle'     => cd_env('APP_SUBTITLE', 'กล่องข้อความ LINE & Facebook Messenger'),
        'timezone'     => cd_env('APP_TIMEZONE', 'Asia/Bangkok'),

        // path ที่ติดตั้ง app เทียบกับ root ของเว็บ (เช่น '/week7/chatdesk')
        // ติดตั้งไว้ที่ root ตรง ๆ ให้เว้นว่าง
        'web_path'=> cd_env('APP_WEB_PATH', ''),

        // ทุกกี่วินาทีให้หน้าจอไปเช็คข้อความใหม่
        'poll_inbox'   => cd_env('APP_POLL_INBOX', 5),
        'poll_thread'  => cd_env('APP_POLL_THREAD', 3),

        // ความยาวข้อความสูงสุดที่พนักงานพิมพ์ได้ (LINE จำกัด 5000 ตัวอักษร)
        'max_length'   => cd_env('APP_MAX_LENGTH', 2000),

        // ปิดบอทอัตโนมัติเมื่อพนักงานพิมพ์ตอบเอง (แนะนำให้เปิดไว้)
        'auto_mute_bot' => cd_env('APP_AUTO_MUTE_BOT', true),
    ),

    /* ---------------------------------------------------------------------
     * 4) บัญชีผู้ดูแล — เปลี่ยนรหัสก่อนใช้งานจริงเสมอ
     *    ใส่รหัสตรง ๆ หรือใส่ค่า password_hash() ที่ขึ้นต้นด้วย $2y$ ก็ได้
     * ------------------------------------------------------------------- */
    'admin' => array(
        'user' => cd_env('ADMIN_USER', 'admin'),
        'pass' => cd_env('ADMIN_PASS', 'change_this_password'),
    ),

    'debug' => cd_env('APP_DEBUG', false),
);