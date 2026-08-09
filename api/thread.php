<?php
/**
 * ChatDesk — API: ข้อความในห้องแชท
 * GET ?id=<conversationId>&since=<messageId>
 *   since = เอาเฉพาะข้อความที่ใหม่กว่า (ใช้ตอนหน้าจอเช็คข้อความใหม่ทุกไม่กี่วินาที)
 */

require_once dirname(__DIR__) . '/inc/bootstrap.php';

cd_require_admin();

if (!cd_db_ready()) {
    cd_json(array('ok' => false, 'error' => 'ยังไม่ได้สร้างตารางในฐานข้อมูล'), 500);
}

$convId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$since  = isset($_GET['since']) ? (int) $_GET['since'] : 0;

$st = cd_db()->prepare('SELECT * FROM cd_conversations WHERE id = ? LIMIT 1');
$st->execute(array($convId));
$conv = $st->fetch();
if (!$conv) {
    cd_json(array('ok' => false, 'error' => 'ไม่พบห้องแชทนี้'), 404);
}

$messages = array();
$lastId   = $since;

// เปิดอ่านห้องแล้ว → ให้ถือว่าข้อความลูกค้าถูกอ่านแล้ว (ตีเส้นเป็น "อ่านแล้ว")
if ($since === 0) {
    cd_mark_message_read($convId, 'customer');
}

foreach (cd_thread($convId, 200, $since) as $row) {
    $m = cd_format_message($row);
    $messages[] = $m;
    if ($m['id'] > $lastId) {
        $lastId = $m['id'];
    }
}

// เปิดอ่านแล้ว = เคลียร์ตัวเลขข้อความใหม่
if ($since === 0 && (int) $conv['unread_count'] > 0) {
    cd_db()->prepare('UPDATE cd_conversations SET unread_count = 0 WHERE id = ?')->execute(array($convId));
    $conv['unread_count'] = 0;
}

cd_json(array(
    'ok' => true,
    'conversation' => array(
        'id'         => (int) $conv['id'],
        'name'       => $conv['display_name'] !== null && $conv['display_name'] !== ''
                        ? $conv['display_name'] : ('ลูกค้า #' . $conv['id']),
        'picture'    => $conv['picture_url'],
        'userId'     => $conv['external_user_id'],
        'channel'    => $conv['channel'],
        'status'     => $conv['status'],
        'botEnabled' => ((int) $conv['bot_enabled'] === 1),
        'since'      => date('d/m/Y H:i', strtotime($conv['created_at'])),
    ),
    'messages' => $messages,
    'lastId'   => $lastId,
));
