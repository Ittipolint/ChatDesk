# ChatDesk V2.0.0 — สถาปัตยกรรม Multi-Channel (LINE + Facebook Messenger)

> สถานะ: **วางสถาปัตยกรรม (Design / Storm)** — ยังไม่เริ่มเขียนโค้ด
> Branch: `V2`  (แยกจาก `main` ที่เป็นเวอร์ชัน 1.0.0)

---

## 1. ภาพรวม

ChatDesk เวอร์ชัน 1 รองรับ **LINE เพียงช่องทางเดียว** ทุกจุดของระบบถูกฝังคำว่า "LINE" ไว้
ทั้งใน schema, API, n8n workflow และหน้า UI

เวอร์ชัน 2.0.0 มีเป้าหมาย: **เพิ่มช่องทาง Facebook Messenger** โดยไม่ทำลายข้อมูล LINE เดิม
และเปิดทางให้เพิ่มช่องทางอื่น (Instagram, WhatsApp, Web Chat) ในอนาคตด้วยรูปแบบเดียวกัน

**หลักการออกแบบ (Design Principle):** *แยก "ช่องทาง" ออกจาก "แกนของระบบ"*

```
        ┌─────────────┐     ┌────────────┐
        │   LINE      │────▶│            │
        └─────────────┘     │            │
        ┌─────────────┐     │   n8n      │      ┌─────────────┐      ┌──────────┐
        │ Messenger   │────▶│ (adapter)  │─────▶│ incoming.php│─────▶│  MySQL   │
        └─────────────┘     │            │      └─────────────┘      └──────────┘
        ┌─────────────┐     │            │             ▲
        │  (future)   │────▶│            │             │
        └─────────────┘     └────────────┘     ┌───────┴──────┐
                                                │  ChatDesk UI │
                                                │  (พนักงาน)    │
                                                └───────▲──────┘
                                                        │ POST
                                        ┌───────────────┴───────────────┐
                                        │  n8n push (per channel)        │
                                        │  LINE push / Messenger Graph   │
                                        └───────────────────────────────┘
```

---

## 2. สถาปัตยกรรมปัจจุบัน (V1) ที่ต้องรู้

| ส่วน | ไฟล์ | บทบาท |
|---|---|---|
| รับข้อความ | `n8n/chatdesk-manager-line.workflow.json` | webhook LINE → parse → profile → ส่ง `incoming.php` → ถ้าเปิดบอท ให้ Gemini ตอบ → ส่งกลับ LINE → แจ้ง `incoming.php` (sender=bot) |
| API รับ | `api/incoming.php` | บันทึกห้อง/ข้อความ, คืน `botEnabled` |
| API ส่งออก | `api/send.php` | พนักงานพิมพ์ตอบ → เรียก n8n push → LINE |
| n8n push | `n8n/chatdesk-manager-push.workflow.json` | รับจาก `send.php` → ส่งข้อความไป LINE |
| UI | `index.php`, `assets/js/app.js`, `api/inbox.php` `thread.php` | รายการห้อง, เปิดห้อง, ตอบกลับ, เปิด/ปิดบอท |

**ข้อเท็จจริงที่ช่วยงาน V2:**
- ตาราง `cd_conversations` มีคอลัมน์ `channel` และ `external_user_id` อยู่แล้ว + unique key `(channel, external_user_id)` → **รองรับ multi-channel ระดับ DB ได้โดยไม่ต้อง alter schema ใหม่**
- `cd_find_or_create_conversation($userId, $displayName, $pictureUrl, $channel = 'line')` รับพารามิเตอร์ `channel` อยู่แล้ว
- ที่ต้องแก้จริง ๆ คือ: (ก) n8n workflow ของ Messenger, (ข) ฟังก์ชันส่งออกที่ hardcode LINE, (ค) UI ที่แสดง/กรอง channel

---

## 3. สถาปัตยกรรมเป้าหมาย (V2)

### 3.1 หลักการ: Canonical Message

ทุกช่องทางต้องแปลงเป็น **โครงสร้างข้อความมาตรฐาน** เดียวกันก่อนเข้า `incoming.php`

```jsonc
{
  "channel":   "fb",                    // line | fb | ig | wa | web
  "userId":    "12345678901234567",     // PSID ของ Messenger / Uxxxx ของ LINE
  "text":      "สวัสดีค่ะ",
  "messageType": "text",                // text | image | video | sticker | audio | file | fallback
  "messageId": "m_xxxx",                // กันซ้ำ (ใน Messenger = message.mid)
  "displayName": "สมชาย ใจดี",
  "pictureUrl": "https://graph.facebook.com/.../picture",
  "timestamp": 1717000000000,
  "seq":       0,
  "mediaUrl":  null,                    // เฉพาะมีเดีย (ดู §5.4)
  "mediaPreviewUrl": null
}
```

`incoming.php` ต้องรับ `channel` ใน payload และส่งต่อเข้า `cd_find_or_create_conversation(..., $channel)`.

### 3.2 ชุด workflow n8n ใหม่ (ต่อจาก V1)

| ชื่อ workflow | รับจาก | ส่งถึง | บทบาท |
|---|---|---|---|
| ChatDesk Manager — LINE (เดิม) | LINE webhook | `incoming.php` → Gemini → LINE push | ไม่เปลี่ยน (ยกเว้นเพิ่ม `channel:"line"` ใน payload) |
| ChatDesk Manager — **Messenger** (ใหม่) | Messenger webhook | `incoming.php` → Gemini → **Graph API** | รับ/ตอบผ่าน Facebook |
| ChatDesk Manager — Push LINE (เดิม) | `send.php` | LINE | ไม่เปลี่ยน |
| ChatDesk Manager — **Push Messenger** (ใหม่) | `send.php` | **Graph API** | push ข้อความพนักงานไป Messenger |

### 3.3 การส่งออกที่ฝั่ง PHP ต้องกลายเป็น per-channel

`inc/helpers.php` ฟังก์ชัน `cd_push_line()` → เปลี่ยนเป็น **router**

```php
function cd_push_message($conv, $text, $type, $media)
{
    $channel = $conv['channel'];
    $userId  = $conv['external_user_id'];
    if ($channel === 'fb') {
        return cd_push_messenger($userId, $text, $type, $media);   // Graph API
    }
    return cd_push_line($userId, $text, $type, $media);            // เดิม (ค่า default)
}
```

`api/send.php` เปลี่ยนจาก `cd_push_line($conv['external_user_id'], ...)` เป็น
`cd_push_message($conv, ...)` — ที่เหลือ (บันทึกข้อความ, bot mute) ไม่ต้องแตะ

---

## 4. การเตรียมฝั่ง Meta (Facebook Messenger)

> รายละเอียดขั้นตอนคลิกทีละเมนู → ดู **[docs/MESSENGER_SETUP.md](MESSENGER_SETUP.md)**

ต้องมีสิ่งนี้ก่อนเริ่มเขียน n8n workflow:

1. **Facebook Page** ที่จะให้ลูกค้าพิมพ์คุย
2. **Meta App** (developers.facebook.com) → เพิ่ม product **Messenger**
3. **Page Access Token** (มีสิทธิ์ `pages_messaging`, `pages_show_list`, `pages_read_engagement`)
4. **App Secret** (ใช้ verify signature `X-Hub-Signature-256` + ใช้กับ token ที่ยืดอายุ)
5. **Verify Token** — string ที่เรากำหนดเอง ใช้ตอบ webhook verification

---

## 5. สิ่งเฉพาะของ Messenger ที่ n8n workflow ต้องจัดการ

### 5.1 Webhook verification (ตอนแรกกด "Verify and Save" ใน Meta)

Messenger ส่ง **GET** มาที่ webhook URL พร้อม query:

```
GET /webhook/chatdesk-messenger?hub.mode=subscribe&hub.verify_token=MY_TOKEN&hub.challenge=1234
```

- n8n webhook ต้องเปิดรับทั้ง GET (สำหรับ verify) และ POST (สำหรับ event)
- Code node ตรวจ `hub.verify_token` === config → ตอบ `hub.challenge` (ค่าเดียว ตัวเลข string) ด้วย HTTP 200
- ถ้า token ไม่ตรง → ตอบ 403

### 5.2 โครงสร้าง webhook event (POST)

```jsonc
{
  "object": "page",
  "entry": [
    {
      "id": "PAGE_ID",
      "time": 1717000000000,
      "messaging": [
        {
          "sender": { "id": "PSID_ผู้ใช้" },
          "recipient": { "id": "PAGE_ID" },
          "timestamp": 1717000000000,
          "message": {
            "mid": "m_xxxxxxxx",
            "text": "สวัสดี",
            "is_echo": false,                 // ← ห้ามตอบ echo (ข้อความที่ระบบส่งออกเอง)
            "attachments": [ { "type": "image", "payload": { "url": "..." } } ]
          }
        }
      ]
    }
  ]
}
```

**จุดที่ต้องกรอง (สำคัญมาก):**
- `messaging.message.is_echo === true` → **ข้าม** (เป็นข้อความสะท้อนของข้อความที่เราส่งไป — ไม่ใช่ลูกค้า)
- `messaging.message.text` ว่าง + ไม่มี `attachments` → ข้าม
- event ที่ไม่มี `message` (เช่น `read`, `delivery`, `postback`) → ข้ามในรอบนี้ (รอบ V2)

### 5.3 อ่านโปรไฟล์ผู้ใช้

```
GET https://graph.facebook.com/{PSID}?fields=first_name,last_name,profile_pic&access_token=PAGE_TOKEN
```

- n8n ใช้ node **HTTP Request** (ไม่ต้องใช้ node LINE)
- PSID ที่เก็บใน DB = `external_user_id` เช่นเดียวกับ LINE
- ถ้า token หมดอายุ → ระบบส่งข้อความ fail และเห็น `error_message` ใน UI (กลไกเดิมทำงานอยู่แล้ว)

### 5.4 มีเดีย (image / video / sticker / audio / file)

**ขาเข้า (ลูกค้า → เรา):**
- `attachments[].payload.url` คือ URL ชั่วคราว ที่ต้อง**ดาวน์โหลดมาฝั่งเรา**ภายในระยะเวลาจำกัด
  (ใช้ได้ไม่กี่ชั่วโมง — อย่าเก็บ URL ดิบลง DB แล้วต้องใช้ทีหลัง)
- วิธี: code node เรียก `GET attachments[].payload.url` แล้วอัปโหลดผ่าน `api/upload.php`
  (เหมือนขั้นตอน "Get Media Content → Upload Media" ที่ LINE workflow มีอยู่แล้ว)
- ประเภท map: `image`→image, `video`→video, `audio`→audio, `file`→file, `sticker`→sticker

**ขาออก (เรา → ลูกค้า):**
- text → `POST /me/messages` body `{recipient:{id}, message:{text}}`
- image/video/file/audio → ต้อง**อัปโหลดไฟล์ไป Graph API ก่อน**เพื่อได้ `attachment_id`:
  1. `POST /me/message_attachments` multipart (ไฟล์ + `message` field)
  2. แล้ว `POST /me/messages` ด้วย `{attachment:{type, payload:{attachment_id}}}`
- sticker ของ Messenger ใช้ url ของ sticker (type=image) แทน packageId/stickerId อย่าง LINE

### 5.5 ส่งข้อความกลับ (Reply)

n8n workflow **Push Messenger** ใช้ node HTTP Request:

```
POST https://graph.facebook.com/v20.0/me/messages?access_token=PAGE_TOKEN
Content-Type: application/json

{ "recipient": { "id": "<PSID>" },
  "message":   { "text": "..." } }
```

- ใส่ `messaging_type`: `RESPONSE` (ตอบกลับบทสนทนา) — ค่า default ก็พอใน V2
- **ขออนุมัติ (App Review / advanced access)** จำเป็นเมื่อจะปล่อยจริงกับผู้ใช้อื่นที่ไม่ใช่ testers
  — ดู docs/MESSENGER_SETUP.md

### 5.6 ความปลอดภัย

- **Verify signature**: Meta ส่ง header `X-Hub-Signature-256: sha256=<hmac>` (key = App Secret, body ดิบ)
  — ตรวจใน node Webhook/Code ก่อน process event
- `CHATDESK_SECRET` ที่ส่งเข้า `incoming.php` ยังใช้ได้เหมือนเดิม (เป็น layer ที่สองนอกเหนือจาก Graph)
- Page Access Token / App Secret เก็บใน **n8n credentials** ไม่ใช่ config ใน repo

---

## 6. การเปลี่ยนแปลงฝั่ง PHP / DB / UI (สรุปเป็น checklist)

| ไฟล์ | สิ่งที่ต้องเปลี่ยน | ระดับ |
|---|---|---|
| `api/incoming.php` | รับ `channel` (default `line`), ส่งต่อให้ `cd_find_or_create_conversation` | ง่าย |
| `inc/helpers.php` | เพิ่ม `cd_push_message()` (router) + `cd_push_messenger()` (Graph API); `cd_find_or_create_conversation` รับ channel จาก payload แล้ว | กลาง |
| `api/send.php` | เรียก `cd_push_message($conv, ...)` แทน `cd_push_line($conv['external_user_id'], ...)` | ง่าย |
| `api/inbox.php` | เพิ่ม filter `?channel=line|fb|all`, คืน `channel` ใน item ให้ UI ใส่ป้าย | ง่าย |
| `api/thread.php` | (optional) คืน `channel` ของห้อง | ง่าย |
| `index.php` / `assets/js/app.js` / `assets/css/app.css` | แสดงป้ายช่องทาง (LINE/FB), filter tab ต่อช่องทาง, จัด layout avatar ตาม channel | กลาง |
| `schema.sql` | **ไม่ต้องแก้** (มี `channel` แล้ว) — อาจเพิ่ม index เอาไว้ | ไม่จำเป็น |
| `config.sample.php` / `.env.example` | เพิ่ม `fb.page_token`, `fb.app_secret`, `fb.verify_token` (เก็บจริงใน n8n credential ก็ได้) | ง่าย |
| `inc/bootstrap.php` | bump `CD_VERSION` → `2.0.0` | ง่าย |

**Schema หมายเหตุ:** ในอนาคตถ้าอยากให้ช่องทางแสดงข้อมูลต่างกันมาก (เช่น preferred language,
locale ของ Messenger) ให้เพิ่มคอลัมน์ได้ใน migration แยก — V2 ยังไม่จำเป็น.

---

## 7. ลำดับการทำ (Roadmap V2.0.0)

| Phase | งาน | ตรวจสอบสำเร็จเมื่อ |
|---|---|---|
| **P1 — Messenger เข้ามาได้** | Meta setup + n8n workflow Messenger (verify, parse, profile, incoming.php, media) | ข้อความลูกค้าจาก Messenger ปรากฏใน inbox ChatDesk |
| **P2 — ตอบกลับได้** | n8n Push Messenger + `cd_push_messenger()` + `send.php` router | พนักงานพิมพ์ตอบจาก UI แล้วลูกค้า Messenger เห็นข้อความ |
| **P3 — บอททั้งสองช่องทาง** | ต่อ Gemini ใน workflow Messenger (copy จาก LINE), `channel` ใน payload ครบทั้ง bot/customer | บอทตอบอัตโนมัติใน Messenger เช่นเดียวกับ LINE |
| **P4 — UI polish** | ป้าย/ฟิลเตอร์ช่องทาง, จัด avatar, รายงานแยกช่องทาง | ผู้ใช้แยก LINE vs FB ได้ในหน้าเดียว |
| **P5 — เตรียม release** | bump version, อัปเดต README/docs, ทดสอบ App Review ของ Meta | tag `v2.0.0` |

---

## 8. หมายเหตุ

- **ระวัง echo loop**: ถ้าไม่กรอง `is_echo` บอทจะเห็นข้อความตัวเองซ้ำแล้วตอบวนไม่จบ
- **PSID เป็นต่อ Page**: ผู้ใช้คนเดียวกันคุยกับอีก Page จะได้ PSID ต่างกัน — ปกติ แยกห้องตาม `(channel, external_user_id)` ซึ่ง unique อยู่แล้ว
- **LINE ที่มีอยู่เดิมไม่กระทบ**: ค่า default `channel='line'` ในทุกจุดที่ไม่ได้ระบุ ทำให้ข้อมูล V1 ยังทำงานเหมือนเดิม
- เวอร์ชันปัจจุบัน: `1.0.0` (main) → `2.0.0` (V2)
