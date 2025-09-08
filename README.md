
# Expense Tracker — PHP + Supabase (Postgres) + TailwindCSS

เวอร์ชันนี้ใช้ **Supabase** แทน MySQL โดยสื่อสารผ่าน **REST (PostgREST)** จาก PHP

## คุณสมบัติ
- เพิ่ม/แก้ไข/ลบ รายการรายรับ-รายจ่าย
- เลือกวัน/เดือน/ปี พร้อมหมายเหตุ
- คัดกรองตามช่วงวันที่
- รวมยอด: รายรับ / รายจ่าย / คงเหลือ
- ปลอดภัยพื้นฐาน: CSRF token
- TailwindCSS via CDN

## ตั้งค่า Supabase
1. สร้าง Project ใน Supabase
2. ไปที่ **SQL Editor** แล้วรันสคริปต์นี้เพื่อสร้างตาราง:
```sql
create table if not exists public.transactions (
  id bigserial primary key,
  user_id uuid null, -- ถ้าใช้ Supabase Auth ให้ผูกกับ auth.uid()
  tx_date date not null,
  type text not null check (type in ('income','expense')),
  amount numeric(12,2) not null check (amount >= 0),
  note text null,
  inserted_at timestamptz not null default now()
);
-- เปิดใช้ RLS (แนะนำ)
alter table public.transactions enable row level security;
-- นโยบายตัวอย่าง (ถ้าใช้ Auth): ให้เจ้าของข้อมูลอ่าน/เขียนแถวของตัวเองได้
create policy "select own rows" on public.transactions
  for select using (auth.uid() is not null and user_id = auth.uid());
create policy "insert own rows" on public.transactions
  for insert with check (auth.uid() is not null and user_id = auth.uid());
create policy "update own rows" on public.transactions
  for update using (auth.uid() is not null and user_id = auth.uid());
create policy "delete own rows" on public.transactions
  for delete using (auth.uid() is not null and user_id = auth.uid());
```
> ถ้าทดสอบแบบไม่ล็อกอิน ให้ปิด RLS ชั่วคราวหรือเขียน policy เปิดกว้าง (ไม่แนะนำบนโปรดักชัน)

3. คัดลอกค่า **Project URL** และ **anon key** จาก **Project Settings → API**

## ตั้งค่าโปรเจ็กต์ PHP
- คัดลอก `.env.example` เป็น `.env` แล้วตั้งค่า:
```
SUPABASE_URL=https://YOUR-PROJECT.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOi...
SUPABASE_SCHEMA=public
SUPABASE_TABLE=transactions
```
> ถ้าคุณใช้ Supabase Auth ฝั่งเซิร์ฟเวอร์นี้ไม่ได้ทำเซสชัน/ JWT ให้ ในตัวอย่างจึงไม่ได้แนบ `Authorization: Bearer <user_jwt>` ของผู้ใช้จริง ๆ หากต้องการ multi-user เต็มรูปแบบ ให้รวมระบบ login แล้วส่ง JWT ของผู้ใช้มาที่ PHP หรือย้ายไปใช้ frontend + supabase-js

## รัน
```bash
php -S localhost:8080
```
แล้วเปิด `http://localhost:8080`

## ไฟล์
- `index.php` — UI + เรียกใช้ REST ของ Supabase
- `supabase.php` — ฟังก์ชันเรียก REST (cURL)
- `csrf.php` — CSRF helpers
- `.env.example` — ตัวอย่าง environment variables

