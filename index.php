<?php
/**
 * ChatDesk — หน้ากล่องข้อความ (ต้องเข้าสู่ระบบ)
 */
require_once __DIR__ . '/inc/bootstrap.php';

/* --------------------------------- ออกจากระบบ ------------------------------ */
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: index.php');
    exit;
}

/* --------------------------------- เข้าสู่ระบบ ----------------------------- */
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    if (hash_equals((string) $CFG['admin']['user'], (string) $_POST['login_user'])
        && cd_check_password((string) $_POST['login_pass'])) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    usleep(400000);
}

if (!cd_is_admin()) {
    ?><!doctype html>
    <html lang="th"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><title>เข้าสู่ระบบ • <?= e($CFG['app']['title']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= cd_asset_ver('assets/css/app.css') ?>"></head>
    <body class="login-page">
      <form class="login-card" method="post">
        <div class="logo">💬</div>
        <h1><?= e($CFG['app']['title']) ?></h1>
        <p class="sub"><?= e($CFG['app']['subtitle']) ?></p>
        <?php if ($loginError): ?><div class="alert"><?= e($loginError) ?></div><?php endif; ?>
        <input type="text" name="login_user" placeholder="ชื่อผู้ใช้" required autofocus autocomplete="username">
        <input type="password" name="login_pass" placeholder="รหัสผ่าน" required autocomplete="current-password">
        <button type="submit">เข้าสู่ระบบ</button>
      </form>
    </body></html>
    <?php
    exit;
}

$dbReady = cd_db_ready();
$csrf    = cd_csrf_token();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= e($CFG['app']['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=<?= cd_asset_ver('assets/css/app.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
</head>
<body>

<header class="topbar">
  <div class="brand"><span>💬</span> <?= e($CFG['app']['title']) ?> <small><?= e($CFG['app']['subtitle']) ?></small></div>
  <div class="topright">
    <span class="live"><span class="dot" id="liveDot"></span><span id="liveText">กำลังเชื่อมต่อ</span></span>
    <a href="?logout=1" class="danger">ออกจากระบบ</a>
  </div>
</header>

<?php if (!$dbReady): ?>
<div class="setup-warn">
  ⚠️ ยังไม่พบตารางในฐานข้อมูล — import ไฟล์ <code>schema.sql</code> ผ่าน phpMyAdmin แล้วตรวจค่า <code>config.php</code>
</div>
<?php endif; ?>

<div class="layout">

  <!-- ============================ รายการห้องแชท ============================ -->
  <section class="panel list-panel">
    <div class="list-head">
      <input type="search" id="searchBox" placeholder="ค้นหาชื่อ หรือข้อความ">
    </div>
    <div class="tabs" id="tabs">
      <button class="tab on" data-filter="all">ทั้งหมด <span class="n" id="n-all">0</span></button>
      <button class="tab" data-filter="unread">ยังไม่อ่าน <span class="n" id="n-unread">0</span></button>
      <button class="tab" data-filter="human">คนดูแล <span class="n" id="n-human">0</span></button>
      <button class="tab" data-filter="closed">ปิดแล้ว <span class="n" id="n-closed">0</span></button>
    </div>
    <ul class="conv-list" id="convList"></ul>
    <p class="empty" id="listEmpty" hidden>ยังไม่มีข้อความจากลูกค้า</p>
  </section>

  <!-- ============================== ห้องแชท =============================== -->
  <section class="panel chat-panel">

    <div class="placeholder" id="placeholder">
      <div class="ph-icon">💬</div>
      <p>เลือกลูกค้าจากรายการทางซ้าย<br>เพื่อดูบทสนทนาและพิมพ์ตอบ</p>
    </div>

    <div class="chat-wrap" id="chatWrap" hidden>
      <div class="chat-head">
        <div class="who">
          <div class="avatar" id="convAvatar">👤</div>
          <div>
            <h2 id="convName">—</h2>
            <p id="convMeta">—</p>
          </div>
        </div>
        <div class="head-actions">
          <button type="button" class="btn bot-btn" id="btnBot" title="สลับให้บอทตอบ / คนตอบ">
            <span id="botIcon">🤖</span> <span id="botLabel">บอทตอบอยู่</span>
          </button>
          <button type="button" class="btn" id="btnClose">ปิดเคส</button>
          <button type="button" class="btn danger" id="btnDelete">ลบ</button>
        </div>
      </div>

      <div class="thread" id="thread"></div>

      <form class="composer" id="composer">
        <button type="button" class="icon-btn" id="btnSticker" title="สติกเกอร์">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 12.8a9.5 9.5 0 1 1-9.5-9.5 6.5 6.5 0 0 1 9.5 9.5z"/><path d="M12.5 3.3a6.5 6.5 0 0 0 8.2 8.2"/>
            <path d="M8 13.5a2 2 0 1 0 0-.1"/><path d="M14.5 13.5a2 2 0 1 0 0-.1"/><path d="M9.5 17.5h5"/>
          </svg>
        </button>
        <button type="button" class="icon-btn" id="btnUpload" title="ส่งภาพ / วิดีโอ">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>
          </svg>
        </button>
        <input type="file" id="fileInput" accept="image/*,video/mp4,video/quicktime" hidden>
        <textarea id="msgInput" rows="1" maxlength="<?= (int) $CFG['app']['max_length'] ?>"
                  placeholder="พิมพ์ข้อความถึงลูกค้า… (Enter = ส่ง, Shift+Enter = ขึ้นบรรทัดใหม่)"></textarea>
        <button type="submit" id="btnSend" title="ส่ง">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>
          </svg>
        </button>
      </form>
      <div class="sticker-panel" id="stickerPanel" hidden></div>
      <p class="composer-note" id="composerNote">พิมพ์ตอบแล้วระบบจะปิดบอทห้องนี้ให้อัตโนมัติ</p>
    </div>
  </section>
</div>

<div class="toast" id="toast" hidden></div>

<script>
window.CD = {
  csrf: <?= json_encode($csrf) ?>,
  pollInbox: <?= (int) $CFG['app']['poll_inbox'] ?>,
  pollThread: <?= (int) $CFG['app']['poll_thread'] ?>,
  maxLength: <?= (int) $CFG['app']['max_length'] ?>,
  api: {
    inbox:  'api/inbox.php',
    thread: 'api/thread.php',
    send:   'api/send.php',
    action: 'api/action.php',
    upload: 'api/upload.php'
  }
};
</script>
<script src="assets/js/app.js?v=<?= cd_asset_ver('assets/js/app.js') ?>"></script>
</body>
</html>
