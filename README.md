*************************************************************************************
การเข้าใช้งาน (กลุ่มริมหน้าต่าง)
1. สำหรับ User LINEOA
   Scan QR Code เป็นเพื่อกับ LINEOA ChatDesk เพื่อคุยกับ Bot หรือคน (ตามที่ระบบหลังบ้านกำหนด)
   <img width="1254" height="1254" alt="ChatDesk QR" src="https://github.com/user-attachments/assets/60cb2aa2-88ae-4520-8621-406e4a4d360f" />

3. สำหรับ Admin
   URL: https://ittipolint-sbu.veya.co.th/week7/chatdesk/index.php
   User id: admin, password: 10203040
*************************************************************************************

# ChatDesk 💬

กล่องข้อความ LINE สำหรับทีมดูแลลูกค้า — ดูบทสนทนาระหว่างบอท LINE กับลูกค้า และตอบกลับลูกค้าได้จากหน้าเว็บเดียว

- **ดูบทสนทนา**: รับข้อความลูกค้า/บอทผ่าน n8n → เก็บใน MySQL → แสดงแบบเรียลไทม์ (poll)
- **ตอบกลับ**: พนักงานพิมพ์ตอบ → ส่งออก LINE จริง ผ่าน LINE Push API (ผ่าน n8n)
- **มีเดีย**: ส่งภาพ / วิดีโอ / สติกเกอร์
- **จัดการ**: เปิด/ปิดบอทต่อห้อง, ปิด/เปิดเคส, ลบห้อง, ลบข้อความ (soft delete), ค้นหา, ฟิลเตอร์

> **หมายเหตุ**: branch หลัก `main` นี้คือ **version สำหรับติดตั้งบนเครื่องทั่วไป (Apache + PHP + MySQL)** ที่โฮสต์บนอินเทอร์เน็ต — สำหรับ version Docker (docker compose พร้อม n8n ในตัว) ให้ใช้ branch [`docker`](https://github.com/Ittipolint/ChatDesk/tree/docker)

## สถาปัตยกรรมแบบย่อ

```
LINE ── webhook ──▶ n8n ──▶ api/incoming.php ──▶ MySQL
LINE ◀── push ──── n8n ◀── api/send.php ◀───── พนักงาน
```

## เริ่มต้นใช้งาน

ติดตั้งแบบดั้งเดิม (PHP + MySQL + Apache) — เหมาะสำหรับ web hosting:

```bash
git clone https://github.com/Ittipolint/ChatDesk.git chatdesk
cd chatdesk
cp config.sample.php config.php        # ตั้งค่า db / n8n / admin
mysql -u USER -p chatdesk < schema.sql # สร้างตาราง
```

อัปโหลดทั้งชุดขึ้น server แล้วเข้าสู่ระบบ — ดูรายละเอียดใน **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**

## เอกสาร

| เอกสาร | รายละเอียด |
|---|---|
| [docs/SOFTWARE_SPEC.md](docs/SOFTWARE_SPEC.md) | Software Requirement Specification (SRS) ฉบับเต็ม |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | คู่มือติดตั้งบน server ใหม่ |
| [schema.sql](schema.sql) | โครงสร้างฐานข้อมูล |
| [config.sample.php](config.sample.php) | ตัวอย่าง config |

## ข้อกำหนดระบบ

- PHP 7.4+ (พร้อม `pdo_mysql`, `curl`, `fileinfo`, `mbstring`)
- MySQL 5.7+ / MariaDB 10.2+
- n8n + LINE Messaging API (ดู docs/DEPLOYMENT.md)

## หมายเหตุสำคัญ

- LINE Messaging API **ไม่มี** read receipt สำหรับข้อความที่บอทส่งออก → สถานะ "อ่านแล้ว" หมายถึงพนักงานเปิดดูในหน้าจอ ChatDesk เท่านั้น และ **ไม่มี** API ลบข้อความที่ส่งไปแล้ว — การลบข้อความเป็น soft delete ในระบบเท่านั้น

## License

© 2026 Ittipolint — ไม่สงวนสิทธิ์ทุกประการ
