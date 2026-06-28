# BetaTech Backend API

REST API cho website thương mại điện tử bán laptop **BetaTech**, xây dựng bằng **Laravel**.

---

## Thông tin đồ án

| | |
|---|---|
| **Đề tài** | Xây dựng hệ thống website thương mại điện tử bán laptop cho cửa hàng công nghệ BetaTech |
| **Sinh viên** | Lê Thị Ngọc Ly |
| **MSSV** | 2251162068 |
| **Lớp** | 64HTTT1 |
| **Ngành** | Hệ thống thông tin |
| **Trường** | Đại học Thủy lợi |
| **Frontend** | [github.com/lengocly/laptop-fe](https://github.com/lengocly/laptop-fe) |

---

## Giới thiệu

Backend cung cấp REST API cho frontend React, xử lý nghiệp vụ thương mại điện tử: quản lý sản phẩm, đơn hàng, tồn kho, voucher, thanh toán và thống kê. Hệ thống sử dụng Laravel Sanctum để xác thực người dùng.

---

## Chức năng

- Xác thực người dùng (đăng ký, đăng nhập) bằng Laravel Sanctum
- Quản lý danh mục, sản phẩm và biến thể (màu sắc, cấu hình)
- Quản lý tồn kho, đặt trước và hoàn trả khi hủy đơn
- Xử lý voucher và áp dụng giảm giá
- Tạo đơn hàng, tính phí vận chuyển (GHN)
- Thanh toán COD và Stripe (webhook xác nhận)
- Quản lý và đánh giá sản phẩm
- Tìm kiếm sản phẩm bằng hình ảnh (Google Cloud Vision)
- Trợ lý tư vấn sản phẩm tích hợp Gemini API
- Gửi hóa đơn qua email
- API quản trị: sản phẩm, đơn hàng, voucher, người dùng, thống kê doanh thu

---

## Công nghệ sử dụng

| Công nghệ | Mục đích |
|---|---|
| PHP 8.3 | Ngôn ngữ backend |
| Laravel 13 | Framework |
| Laravel Sanctum | Xác thực API token |
| Eloquent ORM | Truy vấn cơ sở dữ liệu |
| SQLite / MySQL | Lưu trữ dữ liệu |
| Stripe PHP SDK | Thanh toán thẻ quốc tế |
| Google Cloud Vision | Tìm kiếm sản phẩm bằng ảnh |
| Google Gemini API | Trợ lý tư vấn AI |

---

## Yêu cầu hệ thống

- PHP >= 8.3
- Composer 2.x
- SQLite hoặc MySQL
- PHP extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`

---

## Cài đặt và chạy

```bash
git clone https://github.com/lengocly/laptop-be.git
cd laptop-be
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Trên Windows PowerShell:

```powershell
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

API mặc định chạy tại: **http://127.0.0.1:8000/api/v1**

---

## Cấu hình môi trường

Các biến quan trọng trong file `.env`:

```env
APP_NAME=BetaTech
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite

ADMIN_PASSWORD=your_secure_admin_password

STRIPE_KEY=pk_test_your_key
STRIPE_SECRET=sk_test_your_key
STRIPE_WEBHOOK_SECRET=whsec_your_secret

GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash
```

| Biến | Mô tả |
|---|---|
| `ADMIN_PASSWORD` | Mật khẩu tài khoản admin khi seed |
| `STRIPE_*` | Cấu hình Stripe (tùy chọn) |
| `GEMINI_API_KEY` | Khóa Gemini cho trợ lý AI (tùy chọn) |


---

## Tài khoản demo

Sau khi chạy seeder:

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị | admin@betatech.com | Giá trị `ADMIN_PASSWORD` trong `.env` (mặc định `Admin123456`) |
| Khách hàng | khach@betatech.com | `Khach123456` |

---

## Dữ liệu mẫu (Seeder)

| Seeder | Nội dung |
|---|---|
| `AdminUserSeeder` | Tài khoản quản trị |
| `CategorySeeder` | Danh mục laptop (hãng) và phụ kiện |
| `ProductSeeder` | Sản phẩm mẫu |
| `ProductVariantSeeder` | Biến thể (màu, cấu hình) |
| `VoucherSeeder` | Voucher BETATECH100K, SALE5... |
| `DemoUserSeeder` | Tài khoản khách demo |
| `OrderSeeder` | 6 đơn hàng mẫu (pending, delivered...) |

**Tạo lại toàn bộ dữ liệu mẫu:**

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

**Chỉ giữ admin, nhập dữ liệu thủ công qua giao diện:**

```bash
php artisan migrate:fresh --seed --seeder=FreshManualSeeder
php artisan storage:link
```

> Cảnh báo: `migrate:fresh` xóa toàn bộ dữ liệu trong database.

---

## API tiêu biểu

| Phương thức | Endpoint | Mô tả |
|---|---|---|
| `GET` | `/api/v1/products` | Danh sách sản phẩm |
| `GET` | `/api/v1/products/{id}` | Chi tiết sản phẩm |
| `POST` | `/api/v1/register` | Đăng ký tài khoản |
| `POST` | `/api/v1/login` | Đăng nhập |
| `POST` | `/api/v1/orders` | Tạo đơn hàng |
| `GET` | `/api/v1/orders` | Lịch sử đơn hàng |
| `POST` | `/api/v1/payment/intent` | Tạo thanh toán Stripe |
| `POST` | `/api/v1/chat` | Tư vấn qua trợ lý AI |
| `POST` | `/api/v1/image-search` | Tìm kiếm sản phẩm bằng ảnh |
| `GET` | `/api/v1/admin/products` | Quản lý sản phẩm (admin) |
| `GET` | `/api/v1/admin/orders` | Quản lý đơn hàng (admin) |
| `GET` | `/api/v1/admin/stats/revenue-by-day` | Thống kê doanh thu (admin) |

Các endpoint tài khoản và mua hàng yêu cầu Bearer Token. Nhóm `/admin/*` yêu cầu tài khoản quản trị.

---

## Cấu trúc thư mục

```text
app/
├── Http/Controllers/Api/   # Controller xử lý API
├── Models/                 # Model Eloquent
├── Services/               # Logic nghiệp vụ (tồn kho, thanh toán...)
├── Enums/                  # Trạng thái đơn hàng, thanh toán...
database/
├── migrations/             # Migration cơ sở dữ liệu
└── seeders/                # Dữ liệu mẫu
routes/
└── api.php                 # Định nghĩa route API
```

---

## Kiểm thử

```bash
composer test
```

Kết quả kiểm thử API bằng Postman được lưu trong tài liệu đồ án kèm theo.

---

## Liên kết

- Frontend React: [github.com/lengocly/laptop-fe](https://github.com/lengocly/laptop-fe)

---

## Tác giả

**Lê Thị Ngọc Ly** — MSSV 2251162068 — Lớp 64HTTT1

*Đồ án tốt nghiệp ngành Hệ thống thông tin, Trường Đại học Thủy lợi.*
