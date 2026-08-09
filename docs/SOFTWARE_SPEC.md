# ChatDesk — Software Requirement Specification (SRS)

| รายการ | รายละเอียด |
|---|---|
| ชื่อระบบ | ChatDesk — กล่องข้อความ LINE สำหรับทีมดูแลลูกค้า |
| เวอร์ชัน | 1.0.0 |
| วันที่ | 9 สิงหาคม 2569 |
| ประเภทเอกสาร | Software Requirement Specification (SRS) |
| หมายเลขเอกสาร | CD-SRS-001 |

---

## 1. บทนำ (Introduction)

### 1.1 วัตถุประสงค์
ChatDesk คือเว็บแอปพลิเคชันสำหรับทีมดูแลลูกค้า (Support Agent) ใช้ดูบทสนทนาระหว่าง **บอท LINE กับลูกค้า** และให้เจ้าหน้าที่**ตอบกลับลูกค้าทาง LINE ได้โดยตรง**จากหน้าจอเดียว โดยไม่ต้องเปิดแอป LINE เอง

### 1.2 ขอบเขตของระบบ
- รับข้อความจากลูกค้าทาง LINE (ผ่าน n8n ที่ต่อกับ LINE Messaging API)
- รับข้อความที่บอท LINE ตอบกลับอัตโนมัติ (ผ่าน n8n)
- แสดงรายการห้องแชท พร้อมจำนวนข้อความที่ยังไม่อ่าน
- เปิดดูบทสนทนาแบบเรียลไทม์ (poll ทุก 2-5 วินาที)
- เจ้าหน้าที่พิมพ์ตอบกลับลูกค้า ทาง LINE จริง (ผ่าน n8n → LINE Push API)
- ส่งภาพ / วิดีโอ / สติกเกอร์
- เปิด/ปิดบอทต่อห้อง, ปิด/เปิดเคส, ลบห้องแชท, ลบข้อความ (soft delete เฉพาะข้อความบอท/เจ้าหน้าที่)
- จัดการมุมมอง: ฟิลเตอร์ทั้งหมด/ยังไม่อ่าน/คนดูแล/ปิดแล้ว + ค้นหา

### 1.3 ผู้ใช้ของระบบ
| กลุ่มผู้ใช้ | บทบาท |
|---|---|
| ผู้ดูแลระบบ / เจ้าหน้าที่ | เข้าสู่ระบบผ่านหน้าเว็บ, ดูแชท, ตอบกลับ, จัดการเคส |
| ระบบอัตโนมัติ (n8n) | ส่งข้อความเข้า-ออกผ่าน `api/incoming.php` และ `api/upload.php` |

### 1.4 ข้อกำหนดของสภาพแวดล้อม (Environment)
| รายการ | ข้อกำหนด |
|---|---|
| ภาษา/แพลตฟอร์ม | PHP 7.4+ (แนะนำ 8.x) พร้อม PDO_MySQL + cURL + fileinfo |
| ฐานข้อมูล | MySQL 5.7+ / MariaDB 10.2+ (utf8mb4) |
| เว็บเซิร์ฟเวอร์ | Apache (รองรับ .htaccess) หรือ nginx |
| ฝั่งอัตโนมัติ | n8n (self-hosted) ต่อกับ LINE Messaging API |
| เบราว์เซอร์ | Chrome / Edge / Safari / Firefox (ไม่รองรับ IE) |

---

## 2. สถาปัตยกรรมระบบ (System Architecture)

```
 ลูกค้า (LINE app)
      │  (1) ส่งข้อความ
      ▼
 LINE Messaging API ── webhook ──▶ n8n (Workflow: ChatDesk Manager — LINE)
                                        │  (2) POST api/incoming.php
                                        ▼
                              ┌─────────────────────────┐
                              │   ChatDesk (PHP + MySQL) │
                              │  index.php / api/*.php   │
                              └──────────┬──────────────┘
                                         │  (3) เจ้าหน้าที่เปิดหน้าเว็บ/ตอบกลับ
                                         ▼
                              เจ้าหน้าที่ (Support Agent)

 การตอบกลับ:  agent → api/send.php → n8n (ChatDesk Manager — Push) → LINE Push API → ลูกค้า
```

**หลักการไหลของข้อมูล (Data Flow):**
1. ลูกค้าส่งข้อความใน LINE → LINE ส่ง webhook ไปยัง n8n
2. n8n เรียก `POST api/incoming.php` ด้วย `sender=customer` → ระบบบันทึกข้อความ และตอบกลับ `botEnabled` ให้ n8n ใช้ตัดสินใจว่าบอทควรตอบหรือไม่
3. หลังบอทตอบเสร็จ n8n เรียก `api/incoming.php` อีกครั้งด้วย `sender=bot` → บันทึกคำตอบของบอท
4. เจ้าหน้าที่เปิดหน้าเว็บ (login) → ดูรายการห้องแชทและข้อความ
5. เจ้าหน้าที่พิมพ์ตอบ → `api/send.php` → เรียก n8n Push workflow → LINE ส่งข้อความให้ลูกค้า → บันทึกข้อความ `sender=agent` ลงฐานข้อมูล

---

## 3. โครงสร้างซอร์สโค้ด (Directory Structure)

```
chatdesk/
├── index.php              # หน้าหลัก + หน้าล็อกอิน (entry point)
├── config.php             # ค่าตั้งค่า (สร้างจาก config.sample.php)
├── config.sample.php      # ตัวอย่างค่าตั้งค่า (สำหรับ deploy)
├── schema.sql             # โครงสร้างฐานข้อมูล
├── .htaccess              # ค่าความปลอดภัยระดับ root
├── api/
│   ├── inbox.php          # GET รายการห้องแชท + จำนวนนับ (poll)
│   ├── thread.php         # GET ข้อความในห้อง (รองรับ since สำหรับ poll)
│   ├── send.php           # POST ส่งข้อความ/ภาพ/วิดีโอ/สติกเกอร์ออก LINE
│   ├── action.php         # POST คำสั่งจัดการห้อง (bot_on/off, close, delete...)
│   ├── incoming.php       # POST รับข้อความจาก n8n (customer/bot)
│   └── upload.php         # POST อัปโหลดไฟล์มีเดีย (web / n8n)
├── inc/
│   ├── bootstrap.php      # โหลด config, session, DB, helpers
│   ├── db.php             # PDO connection + cd_db_ready()
│   ├── helpers.php        # ฟังก์ชันหลักทั้งหมด
│   └── .htaccess          # ป้องกันการเข้าถึงโฟลเดอร์นี้โดยตรง
├── assets/
│   ├── css/app.css        # สไตล์ทั้งหมด
│   └── js/app.js          # ตรรกะฝั่ง client (AJAX, polling, UI)
├── uploads/
│   └── .htaccess          # ห้ามรัน PHP ในโฟลเดอร์มีเดีย
└── docs/
    ├── SOFTWARE_SPEC.md   # เอกสารฉบับนี้
    └── DEPLOYMENT.md      # คู่มือติดตั้งบน server ใหม่
```

---

## 4. ฐานข้อมูล (Database Design)

### 4.1 ตาราง `cd_conversations` — ห้องแชท (1 แถว = 1 LINE user)
| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| id | int unsigned AI PK | รหัสห้อง |
| channel | varchar(20) | ช่องทาง (ค่าเริ่มต้น `line`) |
| external_user_id | varchar(64) | LINE userId (`Uxxxx...`) — **unique ร่วมกับ channel** |
| display_name | varchar(150) | ชื่อที่แสดงของลูกค้า |
| picture_url | varchar(255) | URL รูปโปรไฟล์ |
| status | enum('open','closed') | สถานะเคส |
| bot_enabled | tinyint(1) | 1=บอทตอบได้, 0=คนดูแลเอง |
| unread_count | int unsigned | จำนวนข้อความที่ยังไม่อ่าน |
| last_message_at | datetime | เวลาข้อความล่าสุด |
| last_message_text | varchar(255) | ตัวอย่างข้อความล่าสุด (แสดงในรายการ) |
| created_at | datetime | เวลาสร้างห้อง |

Indexes: `PRIMARY(id)`, `UNIQUE uk_channel_user(channel, external_user_id)`, `KEY idx_last_message(last_message_at)`, `KEY idx_status(status, last_message_at)`

### 4.2 ตาราง `cd_messages` — ข้อความ
| คอลัมน์ | ชนิด | คำอธิบาย |
|---|---|---|
| id | bigint unsigned AI PK | รหัสข้อความ |
| conversation_id | int unsigned FK | อ้างอิง `cd_conversations.id` (ON DELETE CASCADE) |
| sender | enum('customer','bot','agent','system') | ผู้ส่ง |
| content | text | เนื้อหาข้อความ |
| message_type | varchar(20) | text / image / video / sticker |
| media_url | varchar(500) | URL ไฟล์มีเดีย |
| media_preview_url | varchar(500) | URL ตัวอย่าง |
| sticker_package / sticker_id | varchar(50) | ข้อมูลสติกเกอร์ |
| external_message_id | varchar(64) | id ข้อความจาก LINE (กันซ้ำ) — unique |
| status | enum('ok','error') | สถานะการส่งออก LINE |
| delivered_at | datetime | เวลาที่ส่งถึง LINE (กัน backfill) |
| read_at | datetime | เวลาที่ถือว่าอ่านแล้ว (ในหน้าจอ ChatDesk) |
| deleted_at | datetime | เวลาที่ลบ (soft delete, NULL = ยังไม่ลบ) |
| error_message | varchar(500) | ข้อความ error ตอนส่งล้มเหลว |
| raw | mediumtext | payload ดิบจาก n8n (ใช้ debug) |
| created_at | datetime | เวลาบันทึก |

Indexes: `PRIMARY(id)`, `UNIQUE uk_external_msg(external_message_id)`, `KEY idx_conversation(conversation_id, id)`, `KEY idx_created(created_at)`, FK `conversation_id → cd_conversations.id`

### 4.3 หมายเหตุการออกแบบ
- **soft delete** ข้อความ: ใช้ `deleted_at` แทนการ `DELETE` เพื่อเก็บข้อมูลสำหรับ audit — thread จะไม่แสดงแถวที่ `deleted_at IS NOT NULL`
- **กันข้อความซ้ำจาก LINE**: ใช้ `external_message_id` + UNIQUE key (LINE ส่ง webhook ซ้ำได้)
- ใช้ `utf8mb4_unicode_ci` รองรับภาษาไทย/อิโมจิ

---

## 5. API Specification

รูปแบบ: JSON ทุกตัวตอบกลับ `{ "ok": true/false, ... }`; API สำหรับผู้ดูแลต้องการ session login (`cd_require_admin`); API ที่รับจาก n8n ใช้ secret ทางเลือก

### 5.1 `GET api/inbox.php`
พารามิเตอร์: `filter` (all|unread|human|closed), `q` (ค้นหา)
ตอบกลับ: `{ ok, items: [{ id, name, picture, userId, status, botEnabled, unread, preview, time }], counts: { open, unread, human, closed } }`

### 5.2 `GET api/thread.php`
พารามิเตอร์: `id` (conversationId), `since` (id ข้อความล่าสุดที่ฝั่ง client มี — ใช้ poll)
- ถ้า `since=0` → mark read ข้อความของ customer และเคลียร์ `unread_count`
ตอบกลับ: `{ ok, conversation: {...}, messages: [...], lastId }`

### 5.3 `POST api/send.php` (ต้องมี csrf + session)
Payload: `{ conversationId, text?, type?, mediaUrl?, mediaPreviewUrl?, stickerPackage?, stickerId?, csrf }`
- type: text|image|video|sticker (default text)
- บันทึกข้อความ `sender=agent` แล้ว push ผ่าน n8n ไป LINE
- ถ้า `auto_mute_bot=true` → ปิดบอทห้องนั้นอัตโนมัติ (`botMuted=true` ใน response)
ตอบกลับ: `{ ok, messageId, botMuted, time }` หรือ 502 `{ ok:false, error, messageId, botMuted }` เมื่อ LINE ส่งล้มเหลว

### 5.4 `POST api/action.php` (ต้องมี csrf + session)
Payload: `{ conversationId, action, messageId?, csrf }`
actions: `bot_on`, `bot_off`, `close`, `reopen`, `mark_read`, `delete_message` (ต้องมี `messageId`, ลบได้เฉพาะ sender=bot|agent), `delete` (ลบทั้งห้อง)
ตอบกลับ: `{ ok, ... }`

### 5.5 `POST api/incoming.php` (จาก n8n — ตรวจ secret ถ้าตั้งค่า)
Payload: `{ userId, text|message|reply|content, sender?, displayName?, pictureUrl?, messageId?, messageType?, mediaUrl?, secret? }`
- sender: `customer` (default) หรือ `bot`
- พฤติกรรม: หา/สร้างห้อง → บันทึกข้อความ → ตอบ `{ ok, conversationId, messageId, duplicate, botEnabled, displayName }`
- `duplicate=true` เมื่อ LINE ส่งข้อความซ้ำ (external_message_id ซ้ำ)

### 5.6 `POST api/upload.php`
รองรับ 2 แบบ: multipart `file` (จากหน้าเว็บ) หรือ raw body + header `X-ChatDesk-Filename` (จาก n8n)
- ตรวจสิทธิ์: admin (web) หรือ n8n (มี secret / header `X-ChatDesk-Filename`)
- อนุญาต: jpg jpeg png gif webp (ภาพ), mp4 mov m4v (วิดีโอ), จำกัด 50 MB, ตรวจ MIME ด้วย finfo
- ตอบกลับ: `{ ok, url, type, ext, size }`

---

## 6. ฟังก์ชันหลักฝั่ง Backend (inc/helpers.php)

| ฟังก์ชัน | หน้าที่ |
|---|---|
| `cd_find_or_create_conversation($userId, $name, $pic)` | หาห้องตาม userId หรือสร้างใหม่ |
| `cd_save_message($convId, $sender, $content, $extra)` | บันทึกข้อความ + อัปเดต summary/unread; คืน id หรือ 0 ถ้าซ้ำ |
| `cd_thread($convId, $limit, $sinceId)` | ดึงข้อความ (ไม่รวมที่ soft-delete) เรียงเก่า→ใหม่ |
| `cd_format_message($row)` | จัดรูปให้ client (เพิ่ม html, ticks, time) |
| `cd_text_html($text)` | escape + linkify + newline → HTML |
| `cd_delete_message($messageId)` | soft delete (เฉพาะ bot/agent) + แก้ summary ห้อง |
| `cd_mark_message_read($convId, $senderFilter)` | ตั้ง read_at |
| `cd_push_line($userId, $text, $type, $media)` | ส่งข้อความออก LINE ผ่าน webhook n8n (cURL) |
| `cd_json($data, $status)` | ตอบ JSON มาตรฐาน |
| `cd_require_admin()` | ตรวจสิทธิ์ admin (401 ถ้าไม่อนุญาต) |
| `cd_csrf_check($token)` | ตรวจ CSRF token |

---

## 7. ฟังก์ชันฝั่ง Client (assets/js/app.js)

| ฟังก์ชัน | หน้าที่ |
|---|---|
| `loadInbox()` | ดึงรายการห้องแชททุก N วินาที (poll) — วาดใหม่เฉพาะเมื่อข้อมูลเปลี่ยน |
| `openConversation(id)` | เปิดห้อง: โหลด thread ครั้งแรก, ตั้งชื่อ/รูป, bot state |
| `pollThread()` | ดึงข้อความใหม่ด้วย `since` ทุก M วินาที |
| `bubble(m)` | สร้าง UI ข้อความ (sender, media, ticks, ปุ่มลบ) |
| `bodyFor(m)` | แสดงมีเดีย (image/video/sticker) หรือ HTML |
| composer submit | ส่งข้อความ → `api/send.php` → poll กลับ |
| `sendMedia` / `sendSticker` | ส่งภาพ/วิดีโอ/สติกเกอร์ |
| `doAction(action)` | bot_on/off, close/reopen, delete |
| ปุ่ม `.msg-del` | ลบข้อความ (confirm → `action=delete_message`) |

### 7.1 ตัวบ่งชี้สถานะข้อความ (ticks)
- `✓` เทา — ส่งแล้ว (ข้อความที่เราส่ง ยังไม่มีข้อมูลว่าลูกค้าอ่านหรือยัง)
- `✓✓` เทา — ข้อความลูกค้า ยังไม่ได้เปิดดูในหน้านี้
- `✓✓` น้ำเงิน (indigo) — เปิดดูแล้ว (ในหน้าจอ ChatDesk)
- `!` แดง — ส่งไม่สำเร็จ

> **หมายเหตุสำคัญ**: LINE Messaging API **ไม่มี** read-receipt webhook สำหรับข้อความที่บอท/เจ้าหน้าที่ส่งออกไป และ **ไม่มี** API ให้ลบ/ถอนข้อความที่ส่งไปแล้ว ตัวบ่งชี้ "อ่านแล้ว" จึงหมายถึง "เปิดดูในหน้าจอ ChatDesk" เท่านั้น และการลบข้อความเป็นการ soft-delete ในระบบ (ลูกค้ายังเห็นข้อความใน LINE)

---

## 8. ข้อกำหนดด้านความปลอดภัย (Security Requirements)

1. **ล็อกอิน**: session-based (`admin_ok`), กัน timing attack ด้วย `usleep(400000)` หลัง login ผิด
2. **รหัสผ่าน**: เปรียบเทียบแบบ timing-safe (`hash_equals`); รองรับ `password_hash()` (`$2y$`)
3. **CSRF**: ทุก POST จากหน้าจัดการต้องมี token (ใน `$_SESSION['csrf']`)
4. **Session cookie**: `HttpOnly`, `Secure` (เมื่อ HTTPS), `SameSite=Lax`, ชื่อ `CHATDESK`
5. **SQL**: ใช้ PDO prepared statement ทุกที่ (`ATTR_EMULATE_PREPARES=false`)
6. **XSS**: escape ผ่าน `e()` / `cd_text_html()`; JSON ตอบกลับใช้ `JSON_HEX_TAG|JSON_HEX_AMP`
7. **ไฟล์อัปโหลด**: ตรวจ extension + MIME (`finfo`), สุ่มชื่อไฟล์, ห้ามรัน PHP ใน `uploads/` (`.htaccess`), จำกัดขนาด 50 MB
8. **การเปิดเผยไฟล์**: `.htaccess` ระดับ root ปิด access ไฟล์ `config.php`, `schema.sql`; โฟลเดอร์ `inc/` กันเข้าโดยตรง
9. **n8n secret**: `incoming.php`/`upload.php` ตรวจ secret ถ้าตั้งค่าไว้ (เพื่อให้เฉพาะ n8n ที่รู้รหัสเรียกได้)
10. **SSRF**: `cd_push_line` จำกัดเฉพาะ http/https และไม่ให้ redirect (`FOLLOWLOCATION=false`)
11. **header ความปลอดภัย**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`

---

## 9. ข้อกำหนดที่ไม่ใช่ฟังก์ชัน (Non-Functional Requirements)

| ด้าน | ข้อกำหนด |
|---|---|
| ประสิทธิภาพ | รายการห้องแชทถูกวาดใหม่เฉพาะเมื่อข้อมูลเปลี่ยน (signature diff); poll ใช้ `since` ดึงเฉพาะข้อความใหม่ |
| ความพร้อมใช้งาน | การเชื่อมต่อ n8n ล้มเหลว ไม่ทำให้ระบบพัง — บันทึก error_message ไว้ให้เห็น |
| ความเข้ากันได้ | PHP 7.4+, MySQL 5.7+/MariaDB, Apache/nginx, เบราว์เซอร์สมัยใหม่ |
| การบำรุงรักษา | แยก config, ฟังก์ชันช่วยเหลือไว้ใน inc/, โค้ดมีคำอธิบายภาษาไทย |
| การย้ายระบบ | `config.sample.php` + `schema.sql` + คู่มือ DEPLOYMENT.md ใช้ติดตั้ง server ใหม่ได้ |

---

## 10. สมมติฐานและข้อจำกัด (Assumptions & Constraints)

1. LINE Messaging API ไม่แจ้ง read receipt ให้บอทสำหรับแชท 1:1 → สถานะอ่านเป็น "เปิดดูใน ChatDesk" เท่านั้น
2. LINE ไม่มี API ให้ลบ/ถอนข้อความที่ส่งไปแล้ว → การลบข้อความเป็น soft delete ในระบบเท่านั้น
3. ระบบพึ่งพา n8n ในการรับ webhook และ Push ไป LINE (channel access token เก็บใน n8n ไม่ใช่บน hosting)
4. รองรับการสนทนาแบบ 1:1 (ไม่ใช่กลุ่ม/ห้อง)
5. การแจ้งเตือนแบบ push/poll ใช้ polling (ไม่ใช่ WebSocket) เหมาะกับผู้ใช้หลักจำนวนน้อย
