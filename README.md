# BetaTech Backend API

REST API cho website thương mại điện tử bán laptop BetaTech, được xây dựng bằng
Laravel. Đây là backend thuộc đồ án tốt nghiệp ngành Hệ thống thông tin của sinh
viên **Lê Thị Ngọc Ly - 2251162068 - 64HTTT1**, Trường Đại học Thủy lợi.

## Chức năng

- Xác thực người dùng bằng Laravel Sanctum.
- Quản lý danh mục, sản phẩm và biến thể.
- Xử lý voucher, đơn hàng và tồn kho.
- Thanh toán COD và Stripe.
- Quản lý đánh giá sản phẩm.
- Trợ lý tư vấn sản phẩm tích hợp Gemini API.
- Gửi hóa đơn qua email.
- API quản trị sản phẩm, đơn hàng, voucher và thống kê doanh thu.

## Công nghệ

- PHP 8.3
- Laravel 13
- Laravel Sanctum
- Eloquent ORM
- SQLite hoặc MySQL
- Stripe PHP SDK
- Google Gemini API

## Cài đặt

Yêu cầu PHP `>= 8.3`, Composer và SQLite hoặc MySQL.

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

Trên Windows PowerShell, thay hai lệnh tạo file bằng:

```powershell
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
```

API mặc định chạy tại `http://127.0.0.1:8000/api/v1`.

## Cấu hình môi trường

Các cấu hình chính trong `.env`:

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

Stripe và Gemini là cấu hình tùy chọn theo chức năng sử dụng. Không commit file
`.env`, khóa API hoặc thông tin thanh toán thật.

## Tài khoản admin mẫu

Sau khi chạy seeder:

```text
Email: admin@betatech.com
Mật khẩu: giá trị ADMIN_PASSWORD trong .env
```

Nếu chưa cấu hình `ADMIN_PASSWORD`, seeder dùng `Admin123456`. Cần đổi mật khẩu
này trước khi triển khai.

## API tiêu biểu

| Phương thức | Endpoint | Mô tả |
| --- | --- | --- |
| `GET` | `/api/v1/products` | Danh sách sản phẩm |
| `GET` | `/api/v1/products/{id}` | Chi tiết sản phẩm |
| `POST` | `/api/v1/register` | Đăng ký |
| `POST` | `/api/v1/login` | Đăng nhập |
| `POST` | `/api/v1/orders` | Tạo đơn hàng |
| `GET` | `/api/v1/orders` | Lịch sử đơn hàng |
| `POST` | `/api/v1/payment/intent` | Tạo thanh toán Stripe |
| `POST` | `/api/v1/chat` | Tư vấn qua trợ lý AI |
| `GET` | `/api/v1/admin/products` | Quản lý sản phẩm |
| `GET` | `/api/v1/admin/orders` | Quản lý đơn hàng |
| `GET` | `/api/v1/admin/stats/revenue-by-day` | Thống kê doanh thu |

Các endpoint tài khoản và mua hàng yêu cầu Bearer Token. Endpoint `/admin/*`
yêu cầu tài khoản quản trị.

## Kiểm thử

```bash
composer test
```

## Frontend

Ứng dụng React của dự án:
[github.com/lengocly/laptop-fe](https://github.com/lengocly/laptop-fe)

## Tác giả

**Lê Thị Ngọc Ly**

Đồ án: *Xây dựng hệ thống website thương mại điện tử bán laptop cho cửa hàng
công nghệ BetaTech*.
