# n8n Workflows ที่จำเป็น

โฟลเดอร์นี้เก็บ export ของ workflow n8n ที่ใช้กับ ChatDesk

| ไฟล์ | ชื่อ workflow | หน้าที่ |
|---|---|---|
| `chatdesk-manager-line.workflow.json` | ChatDesk Manager — LINE | รับ webhook จาก LINE -> เรียกบอท (Gemini) -> ส่งข้อความเข้า ChatDesk |
| `chatdesk-manager-push.workflow.json` | ChatDesk Manager — Push | รับคำขอจาก `api/send.php` -> push ข้อความไป LINE |

## import ผ่าน n8n UI

1. เปิด n8n (http://localhost:5678) → **Import workflow from file** → เลือกไฟล์
2. สร้าง Credentials แล้วผูกกับ node (อ่านด้านล่าง)
3. กด **Active** ทั้งสอง workflow

## หลัง import ต้องแก้ค่า

**ChatDesk Manager — LINE** — node `Set Config` มีค่าให้แก้เป็น URL ของ server:
- `CHATDESK_URL` -> URL ที่ `api/incoming.php` (เช่น `https://your-domain/chatdesk/api/incoming.php`)
- `CHATDESK_UPLOAD_URL` -> URL ที่ `api/upload.php`
- `CHATDESK_SECRET` -> ใส่ค่าให้ตรงกับ `n8n.secret` ใน config (ถ้าตั้งไว้)

> สำหรับ version **Docker compose** ให้ใช้ branch `docker` — ค่า URL ตั้งเป็น `http://chatdesk/api/...` (ภายใน docker network) และใช้ไฟล์ `docker-chatdesk-manager-*.workflow.json`

**ChatDesk Manager — Push**
- webhook path `chatdesk-push` ต้องตรงกับ `N8N_PUSH_URL` / `n8n.push_url`
- node `Send LINE Push API` ใช้ credential `httpHeaderAuth` (ค่า `Authorization: Bearer <access token>`)

## Credentials ที่ต้องสร้างใน n8n

| Credential | ประเภท | ใช้กับ node | ค่าที่ต้องใส่ |
|---|---|---|---|
| Line Messaging API | `lineMessagingApi` | Get Profile / Get Media Content / Send LINE | Channel Access Token + Channel Secret |
| LINE HTTP Header Auth | `httpHeaderAuth` | Send LINE Push API (Push workflow) | name = `Authorization`, value = `Bearer <token>` |
| Google Gemini | `googlePalmApi` | Google Gemini Chat Model | host = `https://generativelanguage.googleapis.com`, API key |

## ข้อกำหนด

- ต้องติดตั้ง community node `@aotoki/n8n-nodes-line-messaging` — ใน version Docker ติดตั้งไว้แล้วใน image (`docker/n8n/Dockerfile`) ถ้า n8n เดิมต้องติดตั้งเอง
- โนด AI Agent ใช้ Google Gemini (ต้องมี API key ใน n8n credentials)

ดูรายละเอียดการตั้งค่าเต็มใน docs/DEPLOYMENT.md