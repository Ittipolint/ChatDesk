<?php
/**
 * ChatDesk — API: พนักงานพิมพ์ตอบลูกค้า (รองรับ ส่งภาพ/วิดีโอ/สติกเกอร์)
 * POST JSON: { conversationId, text?, type?, mediaUrl?, mediaPreviewUrl?, stickerPackage?, stickerId?, csrf }
 *
 * type = text | image | video | sticker (ไม่ระบุ = text)
 *
 * บันทึกข้อความ → ยิงออกไปทาง n8n → LINE / Facebook Messenger (ตาม channel ของห้อง)
 * ถ้าตั้ง auto_mute_bot ไว้ จะปิดบอทของห้องนี้ให้อัตโนมัติ เพื่อไม่ให้บอทตอบแทรก
 */

require_once dirname(__DIR__) . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cd_json(array('ok' => false, 'error' => 'ต้องเรียกด้วย POST เท่านั้น'), 405);
}

cd_require_admin();

$input = cd_input();
if (!cd_csrf_check(isset($input['csrf']) ? $input['csrf'] : '')) {
    cd_json(array('ok' => false, 'error' => 'หมดเวลาใช้งาน กรุณารีเฟรชหน้าแล้วลองใหม่'), 403);
}

if (!cd_db_ready()) {
    cd_json(array('ok' => false, 'error' => 'ยังไม่ได้สร้างตารางในฐานข้อมูล'), 500);
}

$convId = isset($input['conversationId']) ? (int) $input['conversationId'] : 0;
$text   = isset($input['text']) ? trim((string) $input['text']) : '';
$type   = isset($input['type']) ? preg_replace('/[^a-z]/', '', strtolower($input['type'])) : 'text';
if ($type === '') {
    $type = 'text';
}
if (!in_array($type, array('text', 'image', 'video', 'sticker'), true)) {
    cd_json(array('ok' => false, 'error' => 'ไม่รู้จักประเภทข้อความนี้'), 422);
}

if ($type === 'text' && $text === '') {
    cd_json(array('ok' => false, 'error' => 'กรุณาพิมพ์ข้อความ'), 422);
}
$max = (int) $CFG['app']['max_length'];
if (mb_strlen($text) > $max) {
    $text = mb_substr($text, 0, $max);
}

/* ตรวจ payload ของมีเดีย */
$media = array();
if ($type === 'image' || $type === 'video') {
    $media['mediaUrl']        = isset($input['mediaUrl']) ? trim((string) $input['mediaUrl']) : '';
    $media['mediaPreviewUrl'] = isset($input['mediaPreviewUrl']) ? trim((string) $input['mediaPreviewUrl']) : '';
    if (!preg_match('~^https?://~i', $media['mediaUrl']) || $media['mediaUrl'] === '') {
        cd_json(array('ok' => false, 'error' => 'ไม่พบ URL ของไฟล์'), 422);
    }
    if ($media['mediaPreviewUrl'] !== '' && !preg_match('~^https?://~i', $media['mediaPreviewUrl'])) {
        cd_json(array('ok' => false, 'error' => 'URL ตัวอย่างไฟล์ไม่ถูกต้อง'), 422);
    }
} elseif ($type === 'sticker') {
    $media['stickerPackage'] = isset($input['stickerPackage']) ? trim((string) $input['stickerPackage']) : '';
    $media['stickerId']      = isset($input['stickerId']) ? trim((string) $input['stickerId']) : '';
    if ($media['stickerPackage'] === '' || $media['stickerId'] === '') {
        cd_json(array('ok' => false, 'error' => 'ข้อมูลสติกเกอร์ไม่ครบ'), 422);
    }
}

$st = cd_db()->prepare('SELECT * FROM cd_conversations WHERE id = ? LIMIT 1');
$st->execute(array($convId));
$conv = $st->fetch();
if (!$conv) {
    cd_json(array('ok' => false, 'error' => 'ไม่พบห้องแชทนี้'), 404);
}

/* ปิดบอทให้อัตโนมัติเมื่อคนเข้ามาตอบเอง */
$muted = false;
if (!empty($CFG['app']['auto_mute_bot']) && (int) $conv['bot_enabled'] === 1) {
    cd_db()->prepare('UPDATE cd_conversations SET bot_enabled = 0 WHERE id = ?')->execute(array($convId));
    cd_save_message($convId, 'system', 'พนักงานเข้ามาดูแลแชทนี้ — บอทหยุดตอบอัตโนมัติ');
    $muted = true;
}

/* ส่งออกไปยังปลายทางตามช่องทาง (LINE / Facebook Messenger) ผ่าน n8n */
if ($conv['channel'] === 'fb') {
    $res = cd_push_fb($conv['external_user_id'], $text, $type, $media);
} else {
    $res = cd_push_line($conv['external_user_id'], $text, $type, $media);
}

$messageId = cd_save_message($convId, 'agent', $text !== '' ? $text : '[' . $type . ']', array(
    'type'    => $type,
    'media_url'        => isset($media['mediaUrl']) ? $media['mediaUrl'] : null,
    'media_preview_url' => isset($media['mediaPreviewUrl']) ? $media['mediaPreviewUrl'] : null,
    'sticker_package'  => isset($media['stickerPackage']) ? $media['stickerPackage'] : null,
    'sticker_id'       => isset($media['stickerId']) ? $media['stickerId'] : null,
    'status' => $res['ok'] ? 'ok' : 'error',
    'error'  => $res['ok'] ? null : $res['error'],
    'raw'    => isset($res['raw']) ? $res['raw'] : null,
));

if (!$res['ok']) {
    cd_json(array(
        'ok'        => false,
        'error'     => 'ส่งไม่สำเร็จ: ' . $res['error'],
        'messageId' => $messageId,
        'botMuted'  => $muted,
    ), 502);
}

cd_json(array(
    'ok'        => true,
    'messageId' => $messageId,
    'botMuted'  => $muted,
    'time'      => date('H:i'),
));
