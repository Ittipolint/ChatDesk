<?php
/**
 * ChatDesk — API: รายการห้องแชท (หน้าจอเรียกซ้ำทุกไม่กี่วินาที)
 * GET ?filter=all|unread|mine|closed&q=คำค้น
 */

require_once dirname(__DIR__) . '/inc/bootstrap.php';

cd_require_admin();

if (!cd_db_ready()) {
    cd_json(array('ok' => false, 'error' => 'ยังไม่ได้สร้างตารางในฐานข้อมูล'), 500);
}

$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : 'all';
$q      = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$where = array();
$args  = array();

switch ($filter) {
    case 'unread':
        $where[] = 'c.unread_count > 0';
        break;
    case 'human':                       // ห้องที่คนดูแลอยู่ (ปิดบอทแล้ว)
        $where[] = 'c.bot_enabled = 0 AND c.status = \'open\'';
        break;
    case 'closed':
        $where[] = 'c.status = \'closed\'';
        break;
    default:
        $where[] = 'c.status = \'open\'';
}

if ($q !== '') {
    $where[] = '(c.display_name LIKE ? OR c.external_user_id LIKE ? OR EXISTS
                 (SELECT 1 FROM cd_messages m WHERE m.conversation_id = c.id AND m.content LIKE ?))';
    $like = '%' . $q . '%';
    $args[] = $like; $args[] = $like; $args[] = $like;
}

$sql = 'SELECT c.id, c.channel, c.external_user_id, c.display_name, c.picture_url,
               c.status, c.bot_enabled, c.unread_count, c.last_message_at, c.last_message_text
          FROM cd_conversations c';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.last_message_at IS NULL, c.last_message_at DESC, c.id DESC LIMIT 100';

$st = cd_db()->prepare($sql);
$st->execute($args);

$rows = array();
foreach ($st->fetchAll() as $r) {
    $rows[] = array(
        'id'          => (int) $r['id'],
        'name'        => $r['display_name'] !== null && $r['display_name'] !== ''
                         ? $r['display_name'] : ('ลูกค้า #' . $r['id']),
        'picture'     => $r['picture_url'],
        'userId'      => $r['external_user_id'],
        'status'      => $r['status'],
        'botEnabled'  => ((int) $r['bot_enabled'] === 1),
        'unread'      => (int) $r['unread_count'],
        'preview'     => (string) $r['last_message_text'],
        'time'        => $r['last_message_at'] ? date('d/m H:i', strtotime($r['last_message_at'])) : '',
    );
}

$counts = cd_db()->query(
    'SELECT
       SUM(status = \'open\')                          AS open_count,
       SUM(unread_count > 0 AND status = \'open\')     AS unread_count,
       SUM(bot_enabled = 0 AND status = \'open\')      AS human_count,
       SUM(status = \'closed\')                        AS closed_count
     FROM cd_conversations'
)->fetch();

cd_json(array(
    'ok'     => true,
    'items'  => $rows,
    'counts' => array(
        'open'   => (int) $counts['open_count'],
        'unread' => (int) $counts['unread_count'],
        'human'  => (int) $counts['human_count'],
        'closed' => (int) $counts['closed_count'],
    ),
));
