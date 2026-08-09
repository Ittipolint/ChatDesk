# ตั้งค่า Facebook Messenger (สำหรับ ChatDesk V2)

คู่มือเตรียมฝั่ง Facebook ก่อนผูกกับ workflow n8n
เขียนครั้งเดียว และถ้าอยากให้ผู้ใช้ทั่วไปคุยได้ต้องผ่าน **App Review** ก่อน

---

## 1. สร้าง Facebook Page

1. ไปที่ https://www.facebook.com/pages/create → สร้าง Page สำหรับธุรกิจ
2. จด **Page ID** ไว้

## 2. สร้าง Meta App

1. ไปที่ https://developers.facebook.com/apps → **Create App**
2. เลือก use case: **Other → Business** แล้วกด Next
3. ตั้งชื่อ App → Create → ในหน้า App ไปที่ **Add Product** → เลือก **Messenger**

## 3. เชื่อม Page และรับ Page Access Token

1. ใน **App → Messenger → Settings**
2. เลือก Page ที่จะผูก
3. ไปที่ **Access Tokens** → **Generate token** สำหรับ Page นั้น
   - scope ที่ควรมี: `pages_messaging`, `pages_show_list`, `pages_read_engagement`
4. คัดลอก **Page Access Token** (ขึ้นต้น `EA...`) — เป็นค่า `PAGE_TOKEN`
5. ไปที่ **Settings → Basic** จด **App ID** และ **App Secret**

### ยืดอายุ token (เฉพาะเมื่อนำไปใช้กับระบบจริง)

```text
GET https://graph.facebook.com/v20.0/oauth/access_token
    ?grant_type=fb_exchange_token
    &client_id=APP_ID
    &client_secret=APP_SECRET
    &fb_exchange_token=<SHORT_TOKEN ที่ได้จากข้อ 3>
```

คืนค่า token ที่อยู่ได้ ~60 วัน (จาก short-lived ~1 ชม.)

## 4. กำหนด Verify Token

- เป็น string ที่เรากำหนดเอง เช่น `cdk_mfb_8f3a1e` — ใช้ตอบ webhook verification ครั้งแรก
- ไม่ต้องเป็นความลับ แต่ไม่ควรตั้งค่าง่ายใน guessing

## 5. ตั้งค่า Webhook บน Meta

1. ใน **Messenger → Setup Webhook**
2. กรอก:
   - **Callback URL**: `https://<host-n8n>/webhook/chatdesk-messenger`
   - **Verify Token**: ค่าจากข้อ 4
3. กด **Verify and Save** — ในขั้นนี้ n8n workflow ต้องตอบ `hub.challenge` กลับ (ดู docs/ARCHITECTURE_V2.md §5.1)
4. จากนั้น **Subscribe page webhook = Messenger → messages** (เลือก field `messages` เป็นหลัก)

## 6. ข้อควรรู้ / ข้อจำกัด

| เรื่อง | รายละเอียด |
|---|---|
| Echo | ข้อความที่เราส่งออกไปจะย้อนกลับมาใน webhook ด้วย `message.is_echo=true` — ห้ามตอบซ้ำ (loop) |
| PSID | เป็นเฉพาะ Page — ผู้ใช้คนเดียวกันคุยกับอีก Page จะได้ PSID ต่างกัน |
| App Review | ถ้าผู้ที่ใช้งานจริงต้องพยักให้ Submit ผ่านถ้าไม่ใช่ tester/admin |
| Token หมดอายุ | เมื่อ Graph ตอบ error รหัส 190 / 400 → ต้อง refresh Page Access Token |
| มีเดียขาเข้า | `attachments[].payload.url` เป็น URL ชั่วคราว — ต้องดาวน์โหลดเข้า `api/upload.php` ทันที |
| Sandbox | ทดสอบใน Developer Mode + เพิ่ม tester ก่อน ลดความซับซ้อนของ Review |

## 7. ค่าที่ต้องไปใส่ในระบบ

| ชื่อ | เก็บที่ไหน | ใช้ทำอะไร |
|---|---|---|
| `PAGE_TOKEN` | n8n credential (Messenger Page Access Token) | อ่าน profile / ส่งข้อความกลับ |
| `APP_SECRET` | n8n credential | verify signature `X-Hub-Signature-256` |
| `VERIFY_TOKEN` | n8n `Set Config` | ตอบ webhook verification |
| `CHATDESK_SECRET` | n8n `Set Config` | เหมือน LINE — ชั้นความปลอดภัยของ `incoming.php` |