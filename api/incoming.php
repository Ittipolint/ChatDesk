<?php
/**
 * ChatDesk — API: รับข้อความจาก n8n (ทั้งของลูกค้าและของบอท)
 *
 * n8n เรียกไฟล์นี้ 2 จังหวะ
 *   1) ทันทีที่ลูกค้าส่งข้อความเข้ามา  → sender = "customer"
 *      ไฟล์นี้จะคืน botEnabled กลับไปบอกว่า "ห้องนี้ให้บอทตอบได้ไหม"
 *   2) หลังบอทตอบเสร็จ                → sender = "bot"   (เก็บไว้ให้เห็นในหน้าจอ)
 *
 * POST JSON:
 *   {
 *     "userId":      "Uxxxxxxxx",        (จำเป็น)
 *     "text":        "ข้อความ",           (จำเป็น)
 *     "sender":      "customer" | "bot",  (ไม่ใส่ = customer)
 *     "displayName": "ชื่อใน LINE",
 *     "pictureUrl":  "url รูปโปรไฟล์",
 *     "messageId":   "id ข้อความของ LINE",  (ใส่ไว้กันข้อความซ้ำ)
 *     "secret":      "รหัสลับ"             (เฉพาะเมื่อตั้งค่าไว้)
 *   }
 */

require_once dirname(__DIR__) . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cd_json(array('ok' => false, 'error' => 'ต้องเรียกด้วย POST เท่านั้น'), 405);
}

$input = cd_input();

/* ---- รหัสลับ: ตรวจเฉพาะเมื่อตั้งค่าไว้ ---- */
$secret = (string) $CFG['n8n']['secret'];
if ($secret !== '') {
    $given = isset($input['secret']) ? (string) $input['secret']
           : (isset($_SERVER['HTTP_X_CHATDESK_SECRET']) ? (string) $_SERVER['HTTP_X_CHATDESK_SECRET'] : '');
    if (!hash_equals($secret, $given)) {
        cd_json(array('ok' => false, 'error' => 'รหัสลับไม่ถูกต้อง'), 403);
    }
}

if (!cd_db_ready()) {
    cd_json(array('ok' => false, 'error' => 'ยังไม่ได้สร้างตารางในฐานข้อมูล — import schema.sql ก่อน'), 500);
}

/* ------------------------------- ตรวจข้อมูล ------------------------------- */

$userId = isset($input['userId']) ? trim((string) $input['userId']) : '';
if ($userId === '') {
    cd_json(array('ok' => false, 'error' => 'ไม่พบ userId ของลูกค้า'), 422);
}

$text = '';
foreach (array('text', 'message', 'reply', 'content') as $k) {
    if (isset($input[$k]) && is_scalar($input[$k]) && trim((string) $input[$k]) !== '') {
        $text = trim((string) $input[$k]);
        break;
    }
}
$type = isset($input['messageType']) ? preg_replace('/[^a-z]/', '', strtolower($input['messageType'])) : 'text';
if ($type === '') {
    $type = 'text';
}
if ($text === '' && $type !== 'text') {
    $text = array(
        'image'   => '[ภาพ]',
        'video'   => '[วิดีโอ]',
        'sticker' => '[สติกเกอร์]',
        'audio'   => '[เสียง]',
        'file'    => '[ไฟล์]',
    );
    $text = isset($text[$type]) ? $text[$type] : ('[' . $type . ']');
}
if ($text === '') {
    cd_json(array('ok' => false, 'error' => 'ไม่พบข้อความ — ให้ส่งมาในคีย์ "text"'), 422);
}

$sender = isset($input['sender']) && $input['sender'] === 'bot' ? 'bot' : 'customer';

/* ------------------------------ บันทึกข้อมูล ------------------------------ */

$conv = cd_find_or_create_conversation(
    $userId,
    isset($input['displayName']) ? mb_substr(trim((string) $input['displayName']), 0, 150) : null,
    isset($input['pictureUrl']) ? mb_substr(trim((string) $input['pictureUrl']), 0, 255) : null
);

$mediaExtra = array();
if ($type !== 'text') {
    $mediaExtra['media_url']        = isset($input['mediaUrl']) ? mb_substr(trim((string) $input['mediaUrl']), 0, 500) : null;
    $mediaExtra['media_preview_url'] = isset($input['mediaPreviewUrl']) ? mb_substr(trim((string) $input['mediaPreviewUrl']), 0, 500) : null;
    $mediaExtra['sticker_package']   = isset($input['stickerPackage']) ? mb_substr(trim((string) $input['stickerPackage']), 0, 50) : null;
    $mediaExtra['sticker_id']        = isset($input['stickerId']) ? mb_substr(trim((string) $input['stickerId']), 0, 50) : null;
}

$messageId = cd_save_message($conv['id'], $sender, mb_substr($text, 0, 8000), array_merge(array(
    'type'        => $type,
    'external_id' => isset($input['messageId']) ? mb_substr((string) $input['messageId'], 0, 64) : null,
    'raw'         => json_encode($input, JSON_UNESCAPED_UNICODE),
), $mediaExtra));

/* --------- บอกกลับไปว่าห้องนี้ให้บอทตอบได้ไหม (n8n ใช้ตัดสินใจ) --------- */

// อ่านค่าล่าสุดเสมอ เพราะพนักงานอาจเพิ่งกดปิดบอทไปเมื่อครู่
$st = cd_db()->prepare('SELECT bot_enabled, status FROM cd_conversations WHERE id = ?');
$st->execute(array($conv['id']));
$state = $st->fetch();

cd_json(array(
    'ok'             => true,
    'conversationId' => (int) $conv['id'],
    'messageId'      => $messageId,
    'duplicate'      => ($messageId === 0),
    'botEnabled'     => ((int) $state['bot_enabled'] === 1),
    'displayName'    => $conv['display_name'],
));
