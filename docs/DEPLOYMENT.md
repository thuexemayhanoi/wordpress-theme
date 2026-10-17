# Cài đặt, cập nhật và phát hành

Repository GitHub là nguồn mã gốc. **Không có bước nào trong tài liệu này, và không có workflow nào, kích hoạt theme hoặc ghi vào website đang chạy.** Kích hoạt là quyết định của chủ sở hữu sau khi xem trước (xem [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md)).

## 1. Điều đã biết và điều chưa xác minh

| Mục | Trạng thái | Nguồn |
|---|---|---|
| Website đích | `https://blog.rentbikehanoi.com/`, WordPress 7.1.3, theme đang chạy **Nguyễn Hà Glass 2026** (`nguyen-ha-glass-2026`), **Yoast SEO 28.7**, trang chủ là danh sách bài mới (`page_on_front = 0`), permalink `/<slug>/` | Tài liệu của repository `wp-aiwriter` (ngày 17/10/2026) |
| Plugin **Deployer for Git** đã cài trên website | Chủ sở hữu cho biết; v1 README của repository này mô tả: *Deployer for Git → Install Theme*, Provider GitHub, URL repository, Branch, repository công khai (không chọn Private), chọn **Install Theme** (chỉ cài đặt) | README v1 do chủ sở hữu viết |
| Cấu hình Deployer thực tế (nhánh nào được theo dõi, có webhook tự cập nhật không, nó tải bằng `git clone` hay bằng ZIP của GitHub, thư mục theme mà nó tạo) | **CHƯA XÁC MINH.** Không có quyền truy cập website từ môi trường này; không khẳng định tự đồng bộ đang hoạt động | — |
| Hosting | Hostinger; sao lưu hằng tuần theo lời chủ sở hữu nhưng điểm khôi phục gần nhất **chưa xác minh**; bản sao lưu 04/10/2026 đã cũ và **không có theme Glass hiện tại** nên không dùng để khôi phục | Tài liệu của `wp-aiwriter` |

## 2. Hai đường cài đặt (chọn một và giữ nhất quán)

Tên thư mục của theme là danh tính của nó: thiết lập Customizer lưu theo tên thư mục (`theme_mods_<tên thư mục>`). Cài bằng hai đường khác nhau sẽ tạo hai theme riêng với hai bộ thiết lập.

### A. ZIP (khuyến nghị cho lần cài đầu và cho production)

1. Lấy `nguyen-ha-editorial-<phiên bản>.zip` và `nguyen-ha-seo-companion-<phiên bản>.zip` từ artifact `release-<sha>` của một lần chạy GitHub Actions **xanh** (Actions → *Theme QA* → run → Artifacts). Đối chiếu SHA-256 với `SHA256SUMS` / `manifest.json` trong cùng artifact.
2. **Plugins → Add New → Upload Plugin** → cài plugin đồng hành (tùy chọn nhưng khuyến nghị). **Đừng kích hoạt vội**: xem checklist.
3. **Appearance → Themes → Add New → Upload Theme** → cài theme. **Không bấm Activate.**
4. Cập nhật về sau: tải ZIP mới và dùng "Replace current with uploaded" (WordPress tự đề nghị khi trùng tên thư mục). Theme đã kích hoạt vẫn giữ thiết lập.

ZIP chỉ chứa file chạy được của theme (không `tests/`, `tools/`, `docs/`, `plugins/`, `node_modules/`, nguồn CSS/JS, file cấu hình phát triển); `tools/verify-package.sh` kiểm tra điều đó và cả cú pháp PHP lẫn tính nhất quán phiên bản.

### B. Deployer for Git (theo README của chủ sở hữu)

1. **Deployer for Git → Install Theme**; Provider **GitHub**; Repository `https://github.com/thuexemayhanoi/wordpress-theme`; chọn **Branch**; repository công khai nên không chọn Private; chọn **Install Theme** (chỉ cài đặt). **Không kích hoạt.**
2. Style.css nằm ở thư mục gốc của repository nên Deployer nhận ra theme.
3. Plugin đồng hành **không** nằm ở gốc nên Deployer không cài nó như plugin; cài nó từ ZIP (đường A, bước 2).

Những điều cần kiểm tra sau lần cài đầu (vì chưa xác minh):

- **Thư mục theme** Deployer tạo là gì (ví dụ `wordpress-theme` hay `nguyen-ha-editorial`). Ghi lại; đó là tên thiết lập của theme.
- **Nó có kéo cả repository không?** Nếu nó dùng `git clone`, thư mục theme sẽ chứa cả `tests/`, `tools/`, `docs/`, `plugins/`. Chúng không có điểm vào WordPress, mọi file PHP đều dừng nếu bị gọi trực tiếp và mỗi thư mục có `.htaccess` chặn truy cập web (Apache/LiteSpeed), nhưng vẫn là file thừa trên production. Nếu nó dùng ZIP của GitHub (`zipball`), `.gitattributes` đã loại các thư mục đó. Đường A không có vấn đề này.
- **Nhánh nào được theo dõi và có tự cập nhật không.** Nếu một nhánh được theo dõi với cập nhật tự động, mọi commit đẩy lên nhánh đó thay đổi file của theme đã cài (kể cả khi chưa kích hoạt). Khi theme đã là theme đang chạy, điều đó thay đổi website ngay. Vì vậy: **chỉ theo dõi nhánh ổn định (`main` sau khi chủ sở hữu đã duyệt), không theo dõi `feature/premium-seo-theme` trên production.**
- Pull Request của đợt này **không được merge vào `main` trước khi chủ sở hữu xem bản xem trước**, chính vì `main` có thể là nhánh Deployer theo dõi.

## 3. Quy trình phát hành (do con người thực hiện)

1. Pull Request vào `main` xanh trên GitHub Actions (job *QA report and release artifact* thành công; nó fail nếu bất kỳ cổng bắt buộc nào lỗi hoặc không chạy).
2. Chủ sở hữu xem bản xem trước/staging theo [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md).
3. Chủ sở hữu merge. Nếu Deployer theo dõi `main` với tự cập nhật, đây là thời điểm file theme trên server thay đổi.
4. Tạo tag/release thủ công nếu muốn (workflow **không** tự tạo tag hay GitHub Release, để không vô tình kích hoạt webhook).
5. Chỉ sau đó mới kích hoạt theme trong cửa sổ bảo trì ngắn, với đường rollback sẵn sàng ([ROLLBACK.md](ROLLBACK.md)).

## 4. Điều workflow làm và không làm

| Làm | Không làm |
|---|---|
| Kiểm tra PHP 7.4–8.4, WPCS, PHPCompatibilityWP, asset build, POT, PHPUnit (WP 7.1.3, ma trận permalink, multisite), đóng gói ZIP, kiểm tra ZIP, dựng báo cáo QA + manifest | Tag, GitHub Release, push nhánh, gọi website production, đọc secret |
| Chỉ tạo artifact `release-<sha>` khi **mọi** cổng bắt buộc xanh | Merge, kích hoạt theme, gọi webhook Deployer |
