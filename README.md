# ChatDesk 💬

กล่องข้อความ LINE สำหรับทีมดูแลลูกค้า — ดูบทสนทนาระหว่างบอท LINE กับลูกค้า และตอบกลับลูกค้าได้จากหน้าเว็บเดียว

- **ดูความการได้**: รับข้อความลูกค้า/บอทผ่าน n8n → เก็บใน MySQL → แสดงแบบเรียลไทม์ (poll)
- **ตอบกลับ**: พนักงานพิมพ์ตอบ → ส่งออก LINE จริง ผ่าน LINE Push API (ผ่าน n8n)
- **มีเดีย**: ส่งภาพ / วิดีโอ / สติกเกอร์
- **จัดการ**: เปิด/ปิดบอทต่อห้อง, ปิด/เปิดเคส, ลบห้อง, ลบข้อความ (soft delete), ค้นหา, ฟิลเตอร์

## สถาปัตยกรรมแบบย่อ

```
LINE ── webhook ──▶ n8n ──▶ api/incoming.php ──▶ MySQL
LINE ◀── push ──── n8n ◀── api/send.php ◀───── พนักงาน
```

## เริ่มต้นใช้งาน

```bash
git clone https://github.com/Ittipolint/ChatDesk.git chatdesk
cd chatdesk
cp config.sample.php config.php        # ตั้งค่า db / n8n / admin
mysql -u USER -p chatdesk < schema.sql # สร้างตาราง
```

อัปโหลดทั้งชุดขึ้น server (PHP + MySQL + Apache) แล้วเข้าสู่ระบบ — ดูรายละเอียดใน **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**

## เอกสาร

| เอกสาร | รายละเอียด |
|---|---|
| [docs/SOFTWARE_SPEC.md](docs/SOFTWARE_SPEC.md) | Software Requirement Specification (SRS) ฉบับเต็ม |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | คู่มือติดตั้งบน server ใหม่ |
| [schema.sql](schema.sql) | โครงสร้างฐานข้อมูล |
| [config.sample.php](config.sample.php) | ตัวอย่าง config |

## ข้อกำหนดระบบ

- PHP 7.4+ (พรี `pdo_mysql`, `curl`, `fileinfo`, `mbstring`)
- MySQL 5.7+ / MariaDB 10.2+
- n8n + LINE Messaging API (ดู docs/DEPLOYMENT.md)

## หมายเหตุสำคัญ

- LINE Messaging API **ไม่มี** read receipt สำหรับข้อความที่บอทส่งออก → สถานะ "อ่านแล้ว" หมายถึงพนักงานเปิดดูในหน้าจอ ChatDesk เท่านั้น และ **ไม่มี** API ลบข้อความที่ส่งไปแล้ว — การลบข้อความเป็น soft delete ในระบบเท่านั้น

## License

© 2026 Ittipolint — สงวนสิทธิ์ทุกประการ