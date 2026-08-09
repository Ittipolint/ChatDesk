<?php
/**
 * ChatDesk — API: คำสั่งจัดการห้องแชท
 * POST JSON: { conversationId, action, csrf }
 *   action = bot_on | bot_off | close | reopen | mark_read | delete
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
$action = isset($input['action']) ? (string) $input['action'] : '';

$pdo = cd_db();
$st  = $pdo->prepare('SELECT * FROM cd_conversations WHERE id = ? LIMIT 1');
$st->execute(array($convId));
$conv = $st->fetch();
if (!$conv) {
    cd_json(array('ok' => false, 'error' => 'ไม่พบห้องแชทนี้'), 404);
}

switch ($action) {

    case 'bot_on':
        $pdo->prepare('UPDATE cd_conversations SET bot_enabled = 1 WHERE id = ?')->execute(array($convId));
        cd_save_message($convId, 'system', 'เปิดให้บอทตอบห้องนี้อีกครั้ง');
        cd_json(array('ok' => true, 'botEnabled' => true, 'message' => 'บอทกลับมาตอบห้องนี้แล้ว'));

    case 'bot_off':
        $pdo->prepare('UPDATE cd_conversations SET bot_enabled = 0 WHERE id = ?')->execute(array($convId));
        cd_save_message($convId, 'system', 'ปิดบอทห้องนี้ — พนักงานดูแลเอง');
        cd_json(array('ok' => true, 'botEnabled' => false, 'message' => 'ปิดบอทแล้ว พนักงานดูแลเอง'));

    case 'close':
        $pdo->prepare('UPDATE cd_conversations SET status = \'closed\', unread_count = 0 WHERE id = ?')
            ->execute(array($convId));
        cd_save_message($convId, 'system', 'ปิดเคสนี้แล้ว');
        cd_json(array('ok' => true, 'status' => 'closed', 'message' => 'ปิดเคสแล้ว'));

    case 'reopen':
        $pdo->prepare('UPDATE cd_conversations SET status = \'open\' WHERE id = ?')->execute(array($convId));
        cd_save_message($convId, 'system', 'เปิดเคสนี้ขึ้นมาใหม่');
        cd_json(array('ok' => true, 'status' => 'open', 'message' => 'เปิดเคสใหม่แล้ว'));

    case 'mark_read':
        $pdo->prepare('UPDATE cd_conversations SET unread_count = 0 WHERE id = ?')->execute(array($convId));
        cd_mark_message_read($convId, 'customer');
        cd_json(array('ok' => true, 'message' => 'ทำเครื่องหมายว่าอ่านแล้ว'));

    case 'delete_message':
        $msgId = isset($input['messageId']) ? (int) $input['messageId'] : 0;
        if ($msgId <= 0) {
            cd_json(array('ok' => false, 'error' => 'ต้องระบุ messageId'), 422);
        }
        if (!cd_delete_message($msgId)) {
            cd_json(array('ok' => false, 'error' => 'ลบข้อความนี้ไม่ได้ (ลบได้เฉพาะข้อความของบอทหรือเจ้าหน้าที่)'), 422);
        }
        cd_json(array('ok' => true, 'deleted' => true, 'messageId' => $msgId, 'message' => 'ลบข้อความแล้ว'));

    case 'delete':
        $pdo->prepare('DELETE FROM cd_conversations WHERE id = ?')->execute(array($convId));
        cd_json(array('ok' => true, 'deleted' => true, 'message' => 'ลบห้องแชทนี้แล้ว'));
}

cd_json(array('ok' => false, 'error' => 'ไม่รู้จักคำสั่งนี้'), 422);
