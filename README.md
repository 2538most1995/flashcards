# เว็บแอปแฟลชการ์ด 500 คำศัพท์พร้อมสอบครูผู้ช่วย 2569 (MySQL Edition)
> ระบบ Flashcards ท่องจำคำศัพท์ตามหลัก Active Recall & Leitner Spaced Repetition พร้อมจัดเก็บข้อมูลลงฐานข้อมูล MySQL อัตโนมัติ และรองรับการใช้งานบนมือถือ iPhone, iPad และคอมพิวเตอร์

---

## 🌟 ฟังก์ชันและจุดเด่นของระบบ

1. **ฐานข้อมูล MySQL แท้ (ปลอดภัยต่อ Production Server):**
   - คำศัพท์ทั้ง 500 คำจัดเก็บในตาราง `flashcards` ในฐานข้อมูล MySQL
   - **ความปลอดภัยเมื่อขึ้น Server จริง**: ไฟล์ `config/config.php` ถูกเพิ่มใน `.gitignore` จะ **ไม่ถูกอัปโหลดขึ้น GitHub** ทำให้ไม่ไปทับชื่อฐานข้อมูล, ชื่อผู้ใช้ หรือรหัสผ่านบน Server จริง
   - มีไฟล์แม่แบบ `config/config.example.php` สำหรับนำไปตั้งค่าบนเครื่องใหม่หรือ Server จริง
   - โค้ดเชื่อมต่อฐานข้อมูลใช้คำสั่ง `CREATE TABLE IF NOT EXISTS` และจะ Seed คำศัพท์เฉพาะเมื่อตารางยังว่างอยู่เท่านั้น จึง **ไม่ทับข้อมูลเก่าหรือลบข้อมูลเดิมบน Server จริง**

2. **รองรับอุปกรณ์มือถือ iPhone & iPad เต็มรูปแบบ:**
   - 👆 **Touch Swipe Gestures**: ใช้นิ้วปัดซ้าย (👈) เพื่อดูคำถัดไป และปัดขวา (👉) เพื่อดูคำก่อนหน้า
   - 📱 **Dynamic Viewport (`100dvh`)**: ป้องกันปัญหาแถบเครื่องมือของ Safari บน iPhone เลื่อนแล้วหน้าจอกระตุก
   - 🛡️ **iOS Safe Area Insets**: เว้นระยะขอบจอโค้ง ติ่งหน้าจอ (Notch), Dynamic Island และแถบ Home Bar อัตโนมัติ
   - 🎯 **Touch Target Size (44px+)**: ปุ่มขนาดพอดีนิ้วมือ แตะง่าย ไม่ดีเลย์ (`touch-action: manipulation`)
   - 🔊 **iOS Safari Audio Unlock**: ปลดล็อกระบบเสียงบน iOS สามารถกดฟังเสียงอ่านเจ้าของภาษาได้ทันที

3. **ระบบการเรียนรู้ Active Recall & Leitner System:**
   - 🔄 **3D Card Flip**: คลิกการ์ดหรือกด <kbd>Spacebar</kbd> / <kbd>Enter</kbd> เพื่อพลิกดูคำเฉลย
   - 🧠 **Cloze Deletion**: มีประโยคบริบทเว้นช่องว่าง `[ ______ ]` ให้สมองดึงคำศัพท์ออกมาก่อนดูเฉลย
   - 🔊 **Dual Audio Pronunciation**: ฟังเสียงสำเนียงเจ้าของภาษาคมชัด (MP3 Stream + Web Speech API fallback)
   - 🚦 **ปุ่มประเมินผล 3 ระดับ (บันทึกลง MySQL ทันที):**
     - 🔴 **ยังจำไม่ได้ (กด 1)** -> `learning`
     - 🟡 **พอจำได้ (กด 2)** -> `review`
     - 🟢 **จำได้แม่น (กด 3)** -> `mastered`
   - 🗂️ **ตัวกรองหมวดหมู่ครบทั้ง 7 Parts of Speech**: Noun (100), Verb (100), Adj (100), Adv (100), Prep (50), Conj (32), Det (18)
   - 🔍 **ค้นหาคำศัพท์ Real-time**: ค้นได้ทั้งคำภาษาอังกฤษ คำแปล คำอ่าน หรือประโยค
   - 🔀 **Shuffle Mode**: สุ่มลำดับการ์ด
   - 🔢 **Jump to Card**: ข้ามไปยังคำศัพท์หมายเลขที่ต้องการได้ทันที

4. **ระบบจัดการข้อมูล (CRUD & Multi-user):**
   - ➕ **เพิ่มคำศัพท์ใหม่**: เพิ่มคำศัพท์ลง MySQL ได้โดยตรงผ่านหน้าเว็บ
   - ✏️ **แก้ไขคำศัพท์**: แก้ไขคำแปล, คำอ่าน, ตัวอย่างประโยค, หรือสูตรข้อสอบของการ์ดที่กำลังดูอยู่ได้ทันที
   - 🗑️ **ลบคำศัพท์**: จัดการลบคำศัพท์ที่ไม่ต้องการออกจากฐานข้อมูลได้
   - 👤 **ระบบจัดการโปรไฟล์ผู้เรียน**: สลับผู้เรียนหรือสร้างโปรไฟล์ใหม่ เพื่อให้ผู้เรียนแต่ละคนมีประวัติการจำแยกกัน
   - 📊 **สถิติความจำแยกตามหมวดหมู่**: แสดงเปอร์เซ็นต์ความก้าวหน้าของแต่ละประเภทคำ

---

## 🚀 การติดตั้งและตั้งค่าฐานข้อมูล

### บนเครื่อง Local (MAMP):
- **URL เข้าใช้งาน:** [http://localhost:8888/](http://localhost:8888/)
- **MAMP MySQL Port:** `8889` (Host: `127.0.0.1`, User: `root`, Pass: `root`)
- **Database Name:** `flashcards_db`

### บน Server จริง (Production Server):
1. ดึงโค้ดจาก Git:
   ```bash
   git pull origin main
   ```
2. หากยังไม่มีไฟล์ `config/config.php` ให้คัดลอกจากแม่แบบ:
   ```bash
   cp config/config.example.php config/config.php
   ```
3. แก้ไข `config/config.php` ให้ตรงกับฐานข้อมูลของ Server จริง (เช่น Host, Username, Password, Database Name, Port 3306) หรือกำหนดผ่าน Environment Variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)

---

## 📁 โครงสร้างโปรเจกต์

```text
flashcards/
├── index.php                 # หน้าเว็บแอป Flashcards หลัก
├── index.html                # ไฟล์ทางเข้าสำรอง Redirect ไปยัง index.php
├── .htaccess                 # จัดการ URL Rewrite, DirectoryIndex และ Access Control
├── .gitignore                # ป้องกัน config/config.php ไม่ให้ขึ้น Git
├── db_setup.sql              # ไฟล์ SQL Schema สำรองสำหรับ Import ผ่าน phpMyAdmin
├── README.md                 # คู่มือการใช้งาน
├── config/
│   ├── config.example.php    # แม่แบบการตั้งค่าสำหรับขึ้น Server จริง (ขึ้น Git)
│   ├── config.php            # การตั้งค่าของเครื่องตนเอง (ไม่ขึ้น Git เพื่อความปลอดภัย)
│   └── database.php          # ตัวเชื่อมต่อ PDO และ Auto-Migration/Seeder ที่ปลอดภัย
├── backend/
│   ├── cards.php             # API ดึงและจัดการคำศัพท์ (GET, POST CRUD)
│   ├── progress.php          # API บันทึกและดึงผลการจำ (Leitner Status)
│   ├── users.php             # API จัดการโปรไฟล์ผู้เรียน
│   └── stats.php             # API สถิติภาพรวมแยกตามหมวดหมู่
└── data/
    └── kru_whan_500_flashcards.json # ชุดข้อมูลคำศัพท์ต้นฉบับ 500 คำ
```

---

## ⌨️ คีย์ลัดบนคีย์บอร์ด

| ปุ่มคีย์ลัด | คำสั่ง |
| :---: | :--- |
| <kbd>Spacebar</kbd> หรือ <kbd>Enter</kbd> | พลิกดูเฉลยด้านหลัง / พลิกกลับด้านหน้า |
| <kbd>→</kbd> หรือ <kbd>J</kbd> / <kbd>L</kbd> | ไปยังคำศัพท์ถัดไป (Next Card) |
| <kbd>←</kbd> หรือ <kbd>H</kbd> | ย้อนกลับคำศัพท์ก่อนหน้า (Previous Card) |
| <kbd>1</kbd> | ประเมิน: **🔴 ยังจำไม่ได้** (บันทึกลง MySQL) |
| <kbd>2</kbd> | ประเมิน: **🟡 พอจำได้** (บันทึกลง MySQL) |
| <kbd>3</kbd> | ประเมิน: **🟢 จำได้แม่น** (บันทึกลง MySQL) |
| <kbd>S</kbd> | ออกเสียงคำศัพท์สำเนียงเจ้าของภาษา (Speech Synthesis) |
