# n8n Workflows ที่จำเป็น

โฟลเดอร์นี้เก็บ export ของ workflow n8n ที่ใช้กับ ChatDesk (import ผ่าน n8n UI: **Import workflow from file**)

| ไฟล์ | ชื่อ workflow | หน้าที่ |
|---|---|---|
| `chatdesk-manager-line.workflow.json` | ChatDesk Manager — LINE | รับ webhook จาก LINE -> เรียกบอท (Gemini) -> ส่งข้อความเข้า ChatDesk |
| `chatdesk-manager-push.workflow.json` | ChatDesk Manager — Push | รับคำขอจาก `api/send.php` -> push ข้อความไป LINE |

## หลัง import ต้องแก้ค่า

Workflow นี้ยังมี URL ของ server เดิมกำกับอยู่ (`https://ittipolint-sbu.veya.co.th/week7/chatdesk/...`) — แก้เป็น URL ของ server ใหม่:

**ChatDesk Manager — LINE**
- โนด `Send to ChatDesk` -> URL ไปที่ `https://your-domain/chatdesk/api/incoming.php`
- โนด `Upload Media` -> URL ไปที่ `https://your-domain/chatdesk/api/upload.php`
- ตั้ง webhook URL ของ workflow นี้ใน LINE Developer Portal (Webhook URL)
- ถ้าตั้งค่า `n8n.secret` ไว้ ให้เพิ่ม header `X-ChatDesk-Secret` ให้ตรงกัน

**ChatDesk Manager — Push**
- URL ของ webhook โนด ต้องตรงกับ `n8n.push_url` ใน `config.php`
- โนด `Send LINE Push API` ใช้ Credential ของ LINE (channel access token) ของ channel ตัวเอง

## ข้อกำหนด

- ต้องติดตั้ง community node `@aotoki/n8n-nodes-line-messaging` ใน n8n ก่อน import workflow ที่ใช้ node นี้
- โนด AI Agent ใช้ Google Gemini (ต้องมี API key ใน n8n credentials)

ดูรายละเอียดการตั้งค่าเต็มใน docs/DEPLOYMENT.md