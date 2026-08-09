# ChatDesk — คู่มือติดตั้งบน Server ใหม่ (Deployment)

เอกสารนี้สำหรับติดตั้ง ChatDesk บน server ตัวใหม่ (Apache + PHP + MySQL)

## ข้อกำหนดเบื้องต้น

| รายการ | ข้อกำหนด |
|---|---|
| PHP | 7.4+ (แนะนำ 8.x) พร้อม extension: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`, `json` |
| ฐานข้อมูล | MySQL 5.7+ หรือ MariaDB 10.2+ (utf8mb4) |
| เว็บเซิร์ฟเวอร์ | Apache (รองรับ `.htaccess`) หรือ nginx (ตั้ง rule เทียบเท่า) |
| ระบบอัตโนมัติ | n8n ที่ต่อกับ LINE Messaging API (ใช้ส่ง/รับข้อความ LINE) |

## ขั้นตอนติดตั้ง

### 1. ดาวน์โหลดซอร์สโค้ด

```bash
git clone https://github.com/Ittipolint/ChatDesk.git chatdesk
cd chatdesk
```

หรือดาวน์โหลดเป็น ZIP แล้วแตกไฟล์

### 2. สร้างฐานข้อมูล

สร้าง database ใหม่ เช่น `chatdesk` แล้ว import ไฟล์ `schema.sql`:

```bash
mysql -u USER -p chatdesk < schema.sql
```

หรือใช้ phpMyAdmin: create database → Import → เลือก `schema.sql`

### 3. ตั้งค่า config

```bash
cp config.sample.php config.php
```

แก้ไฟล์ `config.php`:

| พารามิเตอร์ | ตัวอย่าง | ความหมาย |
|---|---|---|
| `db.host` | `localhost` | โฮสต์ฐานข้อมูล |
| `db.name` | `chatdesk` | ชื่อฐานข้อมูล |
| `db.user` / `db.pass` | `chatdesk` / `password` | ผู้ใช้ / รหัสผ่านฐานข้อมูล |
| `n8n.push_url` | `https://n8n.example.com/webhook/chatdesk-push` | URL webhook ของ n8n ที่ใช้ push ไป LINE |
| `n8n.secret` | `secret123` | รหัสลับ (กำหนดเองแล้วเอาไปใส่ใน n8n ด้วย) |
| `admin.user` / `admin.pass` | `admin` / `strong-password` | บัญชีผู้ดูแลหน้าเว็บ |
| `app.poll_inbox` / `app.poll_thread` | `5` / `3` | ความถี่ poll (วินาที) |
| `debug` | `false` | เปิดเฉพาะตอน troubleshoot |

> **สำคัญ**: เปลี่ยน `admin.pass` ทันทีหลังติดตั้ง ใช้ค่า `'$2y$...'` (จาก `password_hash()`) ได้

### 4. อัปโหลดไฟล์ขึ้น server

อัปโหลดทั้งชุดไปยังโฟลเดอร์ root ของเว็บของคุณ โดยคงโครงสร้างเดิม:

```
index.php
config.php
config.sample.php
schema.sql
.htaccess
api/   <- action.php, inbox.php, incoming.php, send.php, thread.php, upload.php
inc/   <- bootstrap.php, db.php, helpers.php, .htaccess
assets/css/app.css
assets/js/app.js
uploads/.htaccess
docs/
```

ตั้งค่าสิทธิ์โฟลเดอร์ `uploads/` ให้เขียนได้:

```bash
chmod 755 uploads/
```

### 5. ทดสอบ

1. เปิด `https://your-domain/chatdesk/` → ควรเห็นหน้าเข้าสู่ระบบ
2. เข้าสู่ระบบด้วยบัญชี admin → เห็นหน้ารายการห้องแชท
3. ถ้าเห็นแถบ "ยังไม่พบตารางในฐานข้อมูล" → import `schema.sql` ยังไม่เสร็จ หรือค่า `config.php` ผิด

> หมายเหตุ: ระบบใช้ `filemtime` เป็น cache-buster (`?v=`) อยู่แล้ว ดังนั้นเมื่อ update ไฟล์ css/js มักไม่ต้องบังคับ refresh

## 6. ตั้งค่า n8n (หลังติดตั้ง ChatDesk)

ต้องมีสองเวิร์กโฟลใน n8n ของคุณ:

**A. เวิร์กโฟลรับข้อความจาก LINE (ChatDesk Manager — LINE)**
- ใช้ Webhook trigger รับข้อความจาก LINE (เมื่อมีคนส่งข้อความหา bot)
- หลังรับแล้ว POST ไป `https://your-domain/chatdesk/api/incoming.php`
- ใส่ secret ใน header `X-ChatDesk-Secret` (หรือฟิลด์ `secret` ใน body) ถ้าตั้งค่าของ `n8n.secret`

**B. เวิร์กโฟล Push (ChatDesk Manager — Push)**
- ใช้ Webhook รับ POST ที่ URL ตรงกับ `n8n.push_url` ที่ตั้งไว้ใน config
- ในเวิร์กโฟล รับตัวแปรจาก payload `userId`, `text`, `type` (text/image/video/sticker) แล้ว Push ผ่าน LINE Messaging API (`POST https://api.line.me/v2/bot/message/push`)
- กรณีมีเดีย จะมี `mediaUrl`, `mediaPreviewUrl`, `stickerPackage`, `stickerId` เพิ่มตาม type

LINE Messaging API credential (channel access token) เก็บอยู่ใน n8n Credentials — ไม่ต้องใส่ไว้ในโค้ด

## 7. Production Checklist

- [ ] รหัสผ่าน admin เปลี่ยนแล้ว
- [ ] `n8n.secret` ตั้งไว้และให้ n8n ส่งด้วยทุกครั้ง
- [ ] HTTPS บังคับใช้ (session cookie จะ Secure อัตโนมัติ)
- [ ] `uploads/` ทำการเขียนได้ และมี `.htaccess` ป้อง PHP
- [ ] ทดสอบสร้างข้อความจาก LINE จริงแล้วเห็น ห้องแชทฝั่งเว็บ

## Troubleshooting

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| หน้า login "ตั้งค่าไม่ถูกต้อง" | ค่า DB/จำนวนผิด หรือยังไม่ได้ import schema.sql |
| POST api 403 | CSRF หมด — รีเฟรชหน้าแล้วลองใหม่ |
| ปุ่มตอบ "ส่งไม่สำเร็จ" | ตรวจ `n8n.push_url` / เครดาน n8n กับ LINE |
| ข้อความลูกค้าไม่เข้า | ตรวจ incoming URL กับ secret กับ n8n |
| อัปโหลดไม่ได้ "เขียนไม่ได้" | chmod uploads |
| หน้าไอ แจ้ง "456" | ลองรีเซ็น / เติม text ใหม่ |

## Backup / Restore

- **Database**: export ตาราง `cd_*` ผ่าน phpMyAdmin เป็นระยะ
- **uploads/**: เก็บสำเนาโฟลเดอร์ `uploads/` ไว้ด้วย (มีไฟล์มีเดีย)
- **Restore**: สร้าง DB → import → วางไฟล์+uploads กลับตำแหน่งเดิม