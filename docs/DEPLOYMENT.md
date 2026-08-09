# ChatDesk — คู่มือติดตั้งบน Server ใหม่ (Deployment)

เอกสารนี้สำหรับติดตั้ง ChatDesk บน server ตัวใหม่ มี 2 วิธี: **Docker** (แนะนำ) หรือ **Apache + PHP + MySQL**

## ข้อกำหนดเบื้องต้น

| รายการ | ข้อกำหนด |
|---|---|
| PHP | 7.4+ (แนะนำ 8.x) พร้อม extension: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`, `json` |
| ฐานข้อมูล | MySQL 5.7+ หรือ MariaDB 10.2+ (utf8mb4) |
| เว็บเซิร์ฟเวอร์ | Apache (รองรับ `.htaccess`) หรือ nginx (ตั้ง rule เทียบเท่า) |
| ระบบอัตโนมัติ | n8n ที่ต่อกับ LINE Messaging API (ใช้ส่ง/รับข้อความ LINE) |

หรือใช้ **Docker** ไม่ต้องติดตั้ง PHP/MySQL เอง (ดูหัวข้อถัดไป)

## วิธีที่ 1: ติดตั้งด้วย Docker (แนะนำ)

มี image พร้อมใช้บน **GitHub Packages (ghcr.io)** และ `docker-compose.yml` ใน repo:
compose จะ start ทั้งหมดรวมกัน 3 service คือ ChatDesk + MariaDB + **n8n** (ในเครื่องเดียว):

```bash
# 1. คัดลอก .env.example → .env แล้วแก้รหัสผ่าน + N8N_ENCRYPTION_KEY (สำคัญ)
#    cp .env.example .env
# 2. สร้าง .env ตามคำแนะนำ (ดูตารางด้านล่าง)
# 3. รัน (จะ pull/build image + สร้าง MariaDB + import schema.sql ให้อัตโนมัติ)
docker compose up -d
```

เปิดใช้งาน:
- ChatDesk: http://localhost:8080 (login ตาม `ADMIN_USER`/`ADMIN_PASS`)
- n8n: http://localhost:5678 (user management ปิดอยู่ — เปิดเข้าได้เลย)

> **หมายเหตุจัดวงจร**: `N8N_PUSH_URL` ชี้ไป `http://n8n:5678/webhook/chatdesk-push` (ภายใน docker network) — ไม่ต้องแก้
> โครงข่ายงาน populate ให้อัตโนมัติแล้ว (ดูหัวข้อ "ตั้งค่า n8n" ด้านล่าง)

### ตัวแปร env ที่ตั้งได้ (ไฟล์ `.env`)

| ตัวแปร | ค่าเริ่มต้น | ความหมาย |
|---|---|---|
| `CD_ENV_CONFIG` | `1` | บังคับให้ config อ่านค่าจาก env แทน default |
| `DB_HOST` | `db` | โฮสต์ฐานข้อมูล (ชี้ service `db`) |
| `DB_NAME` / `DB_USER` / `DB_PASS` | — | ข้อมูลฐานข้อมูล |
| `MARIADB_ROOT_PASSWORD` | — | root ของ MariaDB |
| `N8N_PUSH_URL` | `http://n8n:5678/webhook/chatdesk-push` | URL webhook push ของ n8n (ใน network นี้ชี้แบบนี้) |
| `N8N_SECRET` | `''` | secret ที่ n8n ส่งมาด้วย (ถ้าตั้ง ให้แก้เป็นค่าจริง) |
| `ADMIN_USER` / `ADMIN_PASS` | `admin` / — | บัญชีผู้ดูแลหน้าเว็บ |
| `APP_WEB_PATH` | `''` | path ที่ติดตั้ง (ติดตั้งที่ root เว้นว่าง) |
| `APP_TITLE` / `APP_SUBTITLE` / `APP_TIMEZONE` | — | ข้อความ/เวลา |
| `APP_POLL_INBOX` / `APP_POLL_THREAD` | `5` / `3` | ความถี่ poll (วินาที) |
| `APP_MAX_LENGTH` | `2000` | ความยาวข้อความสูงสุด |
| `APP_AUTO_MUTE_BOT` | `true` | ปิดบอทอัตโนมัติเมื่อพนักงานตอบ |
| `APP_DEBUG` | `false` | โหมด debug |
| `N8N_ENCRYPTION_KEY` | (ต้องตั้ง) | ใช้เข้ารหัส credentials ของ n8n — **ห้ามเปลี่ยนภายหลัง** |

> **แนะนำ**: เปลี่ยน `DB_PASS` — `ADMIN_PASS` — `MARIADB_ROOT_PASSWORD` — `N8N_ENCRYPTION_KEY` ทุกครั้งก่อนลงใช้งานจริง
> สุ่ม key: `openssl rand -hex 32`

### ใช้ image โดยตรง (ไม่ใช้ compose)

```bash
docker pull ghcr.io/ittipolint/chatdesk:latest
docker run -d -p 8080:80 \
  -e CD_ENV_CONFIG=1 -e DB_HOST=db -e DB_NAME=chatdesk \
  -e DB_USER=chatdesk -e DB_PASS=xxx -e ADMIN_PASS=xxx \
  ghcr.io/ittipolint/chatdesk:latest
```

หมายเหตุ: ต้องมีฐานข้อมูลที่ container เข้าถึงได้ (ชี้ hostname ของ MySQL ผ่าน `DB_HOST`)

## วิธีที่ 2: ติดตั้งแบบดั้งเดิม (Apache + PHP + MySQL)

### 1. ดาวน์โหลดซอร์สโค้ด

```bash
git clone https://github.com/Ittipolint/ChatDesk.git chatdesk
cd chatdesk
```

หรือดาวน์โหลด ZIP จาก GitHub Release แล้วแตกไฟล์

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
| `app.web_path` | `''` | path ที่ติดตั้ง เช่น `/week7/chatdesk` หรือเว้นว่างถ้าอยู่ root |
| `n8n.push_url` | `https://n8n.example.com/webhook/chatdesk-push` | URL webhook ของ n8n ที่ใช้ push ไป LINE |
| `n8n.secret` | `secret123` | รหัสลับ (กำหนดเอง แล้วเอาไปใส่ใน n8n ด้วย) |
| `admin.user` / `admin.pass` | `admin` / `strong-password` | บัญชีผู้ดูแลหน้าเว็บ |
| `app.poll_inbox` / `app.poll_thread` | `5` / `3` | ความถี่ poll (วินาที) |
| `debug` | `false` | เปิดเฉพาะตอน troubleshoot |

> **สำคัญ**: เปลี่ยน `admin.pass` ทันทีหลังติดตั้ง ใช้ค่า `'$2y$...'` (จาก `password_hash()`) ได้

### 4. อัปโหลดไฟล์ขึ้น server

อัปโหลดทั้งชุดไปยังโฟลเดอร์ root ของเว็บ โดยคงโครงสร้างเดิม:

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

ต้องมีสองเวิร์กโฟลใน n8n ของคุณ (อยู่ใน repo `n8n/*.workflow.json`):

**A. เวิร์กโฟลรับข้อความจาก LINE (ChatDesk Manager — LINE)**
- ใช้ Webhook trigger รับข้อความจาก LINE (เมื่อมีคนส่งข้อความหา bot)
- หลังรับแล้ว POST ไป `https://your-domain/chatdesk/api/incoming.php`
- ใส่ secret ใน header `X-ChatDesk-Secret` (หรือฟิลด์ `secret` ใน body) ถ้าตั้งค่า `n8n.secret`

**B. เวิร์กโฟล Push (ChatDesk Manager — Push)**
- ใช้ Webhook รับ POST ที่ URL ตรงกับ `n8n.push_url` ที่ตั้งไว้ใน config
- ในเวิร์กโฟล รับตัวแปรจาก payload `userId`, `text`, `type` (text/image/video/sticker) แล้ว Push ผ่าน LINE Messaging API (`POST https://api.line.me/v2/bot/message/push`)
- กรณีมีเดีย จะมี `mediaUrl`, `mediaPreviewUrl`, `stickerPackage`, `stickerId` เพิ่มตาม type

LINE Messaging API credential (channel access token) เก็บอยู่ใน n8n Credentials — ไม่ต้องใส่ไว้ในโค้ด

### 6.1 ตั้งค่าอัตโนมัติใน Docker (compose)

เมื่อ compose start ขึ้น ภาษา `n8n` จะมี node LINE + workflow 2 ตัว import เข้าแล้ว แต่ยังต้องทำ 2 ขั้นผ่าน UI:

1. เปิด http://localhost:5678
2. ไป **Credentials** → สร้าง 3 ตัว แล้วผูกกับ node ตาม workflow:
   - **Line Messaging API** (`lineMessagingApi`): ใส่ Channel Access Token + Channel Secret ของ LINE
   - **HTTP Header Auth** (`httpHeaderAuth`): name = `Authorization`, value = `Bearer <access token>`
   - **Google Gemini** (`googlePalmApi`): host = `https://generativelanguage.googleapis.com`, API key
3. เปิด workflow ทั้ง 2 (toggle Active) — ตรวจจากหน้า `http://localhost:5678/home/workflows`
4. ตั้ง Webhook URL ของ workflow LINE ที่ **LINE Developer Console** (ต้องเป็น public URL — ใช้ tunnel เช่น Cloudflare Tunnel/ngrok ถ้าอยู่หลัง NAT)

> workflow ไฟล์ที่ import มี credential ref หาย (เพราะไม่ควร commit secret) ดังนั้นต้องผูก credential ใหม่ทุกครั้งที่ deploy ใหม่ ค่า URL ภายใน (เช่น `http://chatdesk/...`) ถูกตั้งไว้พร้อม
> `n8n/push.php` ตรวจได้ว่า node ทั้งหมดมี credential ครบไหมดูในหน้า Individual workflow เป็นหลัก

## 7. Production Checklist

- [ ] รหัสผ่าน admin เปลี่ยนแล้ว
- [ ] `n8n.secret` ตั้งไว้และให้ n8n ส่งด้วยทุกครั้ง
- [ ] HTTPS บังคับใช้ (session cookie จะ Secure อัตโนมัติ)
- [ ] `uploads/` เขียนได้ และมี `.htaccess` ป้องกัน PHP
- [ ] ทดสอบส่งข้อความจาก LINE จริง แล้วเห็นห้องแชทฝั่งเว็บ

## Troubleshooting

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| หน้า login "ตั้งค่าไม่ถูกต้อง" | ค่า DB ผิด หรือยังไม่ได้ import schema.sql |
| POST api 403 | CSRF หมด — รีเฟรชหน้าแล้วลองใหม่ |
| ปุ่มตอบ "ส่งไม่สำเร็จ" | ตรวจ `n8n.push_url` / credential n8n กับ LINE |
| ข้อความลูกค้าไม่เข้า | ตรวจ incoming URL กับ secret กับ n8n |
| อัปโหลดไม่ได้ "เขียนไม่ได้" | chmod uploads |
| หน้าแจ้ง HTTP 500 | ตรวจ error log / เปิด `debug=true` |

## Backup / Restore

- **Database**: export ตาราง `cd_*` ผ่าน phpMyAdmin เป็นระยะ
- **uploads/**: เก็บสำเนาโฟลเดอร์ `uploads/` ไว้ด้วย (มีไฟล์มีเดีย)
- **Restore**: สร้าง DB → import → วางไฟล์+uploads กลับตำแหน่งเดิม
