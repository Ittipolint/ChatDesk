# n8n Workflows ที่จำเป็น

โฟลเดอร์นี้เก็บ export ของ workflow n8n ที่ใช้กับ ChatDesk

| ไฟล์ | ชื่อ workflow | หน้าที่ |
|---|---|---|
| `chatdesk-manager-line.workflow.json` | ChatDesk Manager — LINE | รับ webhook จาก LINE -> เรียกบอท (Gemini) -> ส่งข้อความเข้า ChatDesk |
| `chatdesk-manager-push.workflow.json` | ChatDesk Manager — Push | รับคำขอจาก `api/send.php` -> push ข้อความไป LINE |
| `chatdesk-manager-fb.workflow.json` | ChatDesk Manager — FB | รับ webhook จาก Facebook Messenger (Graph API) -> เรียกบอท (Gemini) -> ส่งข้อความเข้า ChatDesk |
| `chatdesk-manager-push-fb.workflow.json` | ChatDesk Manager — Push FB | รับคำขอจาก `api/send.php` -> push ข้อความไป Facebook Messenger (Graph API) |

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

**ChatDesk Manager — FB** — node `Set Config` มีค่าให้แก้เป็น URL ของ server:
- `CHATDESK_URL` -> URL ที่ `api/incoming.php` (เช่น `https://your-domain/chatdesk/api/incoming.php`)
- `CHATDESK_SECRET` -> ใส่ค่าให้ตรงกับ `n8n.secret` ใน config (ถ้าตั้งไว้)
- webhook path ของ node `Webhook` ต้องตรงกับค่า Veriy Token / Callback ที่ตั้งใน Facebook Developer
- node `Get FB Profile` / `Send FB` / `Send FB Push API` ใช้ credential `FB Page Access Token`

**ChatDesk Manager — Push FB**
- webhook path `chatdesk-push-fb` ต้องตรงกับ `N8N_PUSH_FB_URL` / `n8n.push_fb_url`
- node `Send FB Push API` ใช้ credential `FB Page Access Token`

## Credentials ที่ต้องสร้างใน n8n

| Credential | ประเภท | ใช้กับ node | ค่าที่ต้องใส่ |
|---|---|---|---|
| Line Messaging API | `lineMessagingApi` | Get Profile / Get Media Content / Send LINE | Channel Access Token + Channel Secret |
| LINE HTTP Header Auth | `httpHeaderAuth` | Send LINE Push API (Push workflow) | name = `Authorization`, value = `Bearer <token>` |
| FB HTTP Header Auth | `httpHeaderAuth` | Get FB Profile / Send FB / Send FB Push API | name = `Authorization`, value = `Bearer <Page Access Token>` (long-lived `EAAG...`) |
| Google Gemini | `googlePalmApi` | Google Gemini Chat Model | host = `https://generativelanguage.googleapis.com`, API key |

## ขั้นตอนตั้งค่า Facebook Messenger (ครั้งแรก)

1. ใน Facebook Developer → สร้าง/เปิด App → เพิ่ม product **Messenger**
2. ใส่ Callback URL = `https://<n8n-host>/webhook/<path ที่ตั้งใน node Webhook ของ workflow FB>`
   และ Verify Token = ค่าที่ตั้งเอง (เช่น `DTR1234`) — เวิร์กโฟลจะตอบ `hub.challenge` ให้อัตโนมัติ
3. รับ Page Access Token (ระยะยาว) จากหน้า Messenger → Settings → กำหนดสิทธิ์
   `pages_messaging`, `pages_read_engagement`, `pages_manage_metadata`
4. นำ token ไปใส่ใน credential `FB Page Access Token` (เป็นค่า `Bearer <token>`)

> **หมายเหตุ**: Messenger (FB) ส่ง media เป็น URL ให้อัตโนมัติ (ไม่ต้องดาวน์โหลดซ้ำแบบ LINE)
> และ **ไม่มี** sticker API แบบ LINE — ห้องช่องทาง `fb` ในหน้าเว็บจึงซ่อนปุ่มสติกเกอร์

## ข้อกำหนด

- LINE: ต้องติดตั้ง community node `@aotoki/n8n-nodes-line-messaging` — ใน version Docker ติดตั้งไว้แล้วใน image (`docker/n8n/Dockerfile`) ถ้า n8n เดิมต้องติดตั้งเอง
- Facebook Messenger: ไม่ต้องใช้ community node เพิ่ม — ใช้ node `HTTP Request` แบบ built-in ต่อกับ Graph API โดยตรง
- โนด AI Agent ใช้ Google Gemini (ต้องมี API key ใน n8n credentials)

ดูรายละเอียดการตั้งค่าเต็มใน docs/DEPLOYMENT.md