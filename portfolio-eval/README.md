# ระบบประเมิน Portfolio (PHP + MySQL)

ระบบให้อาจารย์ 2 ท่านประเมิน Portfolio นักศึกษาแบบอิสระต่อกัน (Rubric 1–5 ถ่วงน้ำหนัก 20/40/10/30, เกณฑ์ผ่าน ≥ 80)
Admin จัดการผู้ใช้/ไฟล์/การมอบหมาย และส่งออกผลเป็น Excel แยก Sheet ตามสาขา

## สิ่งที่ต้องมี
- PHP 8.1 ขึ้นไป (extension: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`)
- MySQL 5.7+ / MariaDB 10.3+
- ผู้ใช้ต้องต่ออินเทอร์เน็ตได้ (โหลด Bootstrap / ฟอนต์ Sarabun จาก CDN)

## ติดตั้งบน XAMPP (Windows) — 5 ขั้นตอน
1. แตกไฟล์ ZIP ไว้ที่ `C:\xampp\htdocs\portfolio-eval`
2. เปิด XAMPP Control Panel แล้วกด Start ทั้ง **Apache** และ **MySQL**
3. เปิดไฟล์ `app/config.php` แล้วแก้
   - `'base_url' => '/portfolio-eval/public'`
   - ข้อมูล `db` (ค่าเริ่มต้นของ XAMPP: user `root`, รหัสผ่านว่าง — ใช้ได้เลย)
4. เปิดเบราว์เซอร์ไปที่ `http://localhost/portfolio-eval/public/setup.php` กรอกข้อมูล Admin คนแรก แล้วกด "ติดตั้งระบบ"
5. **ลบไฟล์ `public/setup.php` ทิ้ง** แล้วเข้าสู่ระบบที่ `http://localhost/portfolio-eval/public/login.php`

> ถ้าตั้ง Document Root ของเว็บชี้ไปที่โฟลเดอร์ `public` โดยตรง (แนะนำสำหรับใช้งานจริง) ให้เว้น `base_url` เป็น `''`

## ติดตั้งบนโฮสต์จริง
- อัปโหลดทั้งโฟลเดอร์ แล้วตั้ง Document Root ไปที่ `public/` (โฟลเดอร์ `app`, `storage`, `database` จะไม่เปิดให้เว็บเห็น)
- ถ้าตั้ง Document Root ไม่ได้ ให้ย้ายเนื้อหาใน `public/` ไปไว้ `public_html` และแก้ path `require __DIR__ . '/../app/...'` ให้ตรง
- สร้างฐานข้อมูลเปล่าใน phpMyAdmin (utf8mb4) แล้วใส่ชื่อ/ผู้ใช้/รหัสผ่านใน `app/config.php` จากนั้นรัน `setup.php`
- ให้โฟลเดอร์ `storage/portfolios/` เขียนได้ (chmod 775)
- เปิด HTTPS ก่อนใช้งานจริง (ระบบตั้ง cookie แบบ secure ให้อัตโนมัติเมื่อเข้าผ่าน HTTPS)

## ลำดับการใช้งาน
1. **Admin → เกณฑ์ Rubric**: นำข้อความเกณฑ์ 5 ระดับ × 4 หัวข้อ จากเอกสาร Rubric ปี 2570 มากรอก (ตอนนี้เป็นข้อความตัวอย่าง)
2. **Admin → ผู้ใช้งาน**: สร้างบัญชีอาจารย์ (Staff ID + รหัสผ่านเริ่มต้น + สาขา) — อาจารย์ต้องเปลี่ยนรหัสผ่านตอนเข้าครั้งแรก
3. **Admin → นักศึกษา & Portfolio**: เพิ่มนักศึกษา แนบ PDF หรือใส่ลิงก์ (หรือนำเข้า CSV: `student_code,full_name,branch_code,link`)
4. **Admin → มอบหมายผู้ประเมิน**: เลือกอาจารย์ 2 ท่านต่อนักศึกษา (เลือกได้เฉพาะสาขาเดียวกัน)
5. **อาจารย์**: เข้าสู่ระบบ → เปิดรายชื่อ → อ่านเอกสารทางซ้าย ให้คะแนนทางขวา → ส่งผล (ระบบบันทึกร่างอัตโนมัติ)
6. **Admin → ผลการประเมิน / ส่งออก Excel**: ดูคะแนนอาจารย์ทั้งสองท่าน คะแนนเฉลี่ย ผ่าน/ไม่ผ่าน และปลดล็อกผลได้ (มีบันทึก log)

## ตั้งค่าที่แก้ได้ใน `app/config.php`
เกณฑ์ผ่าน (`pass_threshold`), น้ำหนักแต่ละหัวข้อ (`weights`, รวมต้อง = 100), เกณฑ์เตือนคะแนนต่างกันมาก (`diff_flag`), ขนาดไฟล์สูงสุด (`max_upload_mb`)

## ปัญหาที่พบบ่อย
| อาการ | วิธีแก้ |
|---|---|
| อัปโหลด PDF ไม่ได้ | เพิ่ม `upload_max_filesize` และ `post_max_size` ใน php.ini (เช่น 25M) แล้วรีสตาร์ท Apache |
| ส่งออก Excel ไม่ได้ | เปิด `extension=zip` ใน php.ini |
| หน้าเว็บไม่มี CSS / ลิงก์เพี้ยน | ตรวจค่า `base_url` ใน `app/config.php` |
| ลิงก์ Google Drive ไม่แสดงในกรอบ | ตั้งสิทธิ์เป็น "ทุกคนที่มีลิงก์" หรือกด "เปิดในแท็บใหม่" |
| อาจารย์ลืมรหัสผ่าน | Admin → ผู้ใช้งาน → ปุ่มกุญแจ (รีเซ็ตรหัสผ่าน) |

## โครงสร้างโปรเจกต์
```
public/   หน้าเว็บทั้งหมด (login, teacher/, admin/, file.php, setup.php, assets/)
app/      โค้ดหลัก config, db, auth, csrf, score, helpers, xlsx, layout/
storage/  ไฟล์ PDF Portfolio (นอกเว็บ เปิดผ่าน file.php ที่ตรวจสิทธิ์เท่านั้น)
database/ schema.sql
```
