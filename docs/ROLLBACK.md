# Rollback về theme cũ

Theme đang chạy trên production là **Nguyễn Hà Glass 2026** (`nguyen-ha-glass-2026`, V2.4.0). Mục tiêu: nếu sau khi kích hoạt Nguyen Ha Editorial có lỗi, đưa website về trạng thái cũ trong vài phút mà **không động tới bài viết, trang, danh mục, người dùng hay cấu hình SEO**.

## 0. Điều kiện để rollback luôn khả thi (làm trước khi kích hoạt)

1. **Không xóa** theme Glass và không đổi tên thư mục của nó. Cài theme mới *thêm vào*, không thay thế.
2. **Sao lưu mới và kiểm tra được** cả cơ sở dữ liệu lẫn `wp-content` ngay trước khi kích hoạt. Bản sao lưu 04/10/2026 đã cũ và không chứa theme Glass hiện tại: **không dùng nó để khôi phục** (và không bao giờ khôi phục lên website khác trên cùng hosting, như `thuexemaynguyentu.com`).
3. Lưu lại các dữ liệu cấu hình mà việc đổi theme có thể xáo trộn (WP-CLI hoặc phpMyAdmin; thay `wp_` bằng tiền tố bảng thật):

```bash
wp option get sidebars_widgets --format=json > sidebars_widgets.backup.json
wp option get theme_mods_nguyen-ha-glass-2026 --format=json > glass-theme-mods.backup.json
wp option get nav_menu_locations --format=json 2>/dev/null || true
wp option get stylesheet; wp option get template; wp option get current_theme
```
   (Các tệp backup này có thể chứa dữ liệu cấu hình: giữ riêng tư, đừng đưa lên GitHub.)
4. Ghi lại hiện trạng để so sánh: ảnh chụp trang chủ, một bài viết, một danh mục; và kết quả `seo_protection.py --mode check` của repository `wp-aiwriter` ngay trước khi kích hoạt.

## 1. Rollback thông thường (quản trị còn truy cập được)

1. **Appearance → Themes → Nguyễn Hà Glass 2026 → Activate.**
2. Hoặc WP-CLI: `wp theme activate nguyen-ha-glass-2026`.
3. Nếu widget bị xáo trộn: `wp option update sidebars_widgets --format=json < sidebars_widgets.backup.json`.
4. Kiểm tra: trang chủ hiện đúng H1/lead đã được khóa, bài viết, danh mục, tìm kiếm, menu; chạy lại `seo_protection.py --mode check` và đảm bảo sạch.

Cài đặt Customizer của theme mới nằm ở `theme_mods_<tên thư mục của theme mới>` và **không bị mất** khi chuyển đi; nếu kích hoạt lại sau này, chúng vẫn còn.

## 2. Rollback khẩn cấp (không vào được wp-admin, ví dụ lỗi nghiêm trọng)

Cách này chỉ đổi hai dòng cấu hình theme; không xóa gì. Làm bằng phpMyAdmin/hPanel hoặc WP-CLI:

```sql
UPDATE wp_options SET option_value = 'nguyen-ha-glass-2026' WHERE option_name IN ('template', 'stylesheet');
UPDATE wp_options SET option_value = 'Nguyễn Hà Glass 2026' WHERE option_name = 'current_theme';
```
Đừng chỉ đổi tên/xóa thư mục theme mới: khi thư mục theme đang hoạt động biến mất, WordPress chuyển sang theme mặc định của lõi (không phải Glass).

## 3. Plugin đồng hành

- **Hủy kích hoạt**: không mất dữ liệu; mọi dữ liệu biên tập (tóm tắt, FAQ, nguồn, Topic Cluster, Pillar, thông tin tác giả) nằm trong post/term/user meta và còn nguyên. Theme vẫn chạy (chỉ thiếu phần biên tập thêm nếu theme không tự đọc meta; theme đọc cùng các khóa meta nên vẫn hiển thị).
- **Xóa plugin**: gỡ các option và transient của chính nó (`nh_seo_*`: **hồ sơ doanh nghiệp, thiết lập, trạng thái chỉ mục tìm kiếm**), bảng chỉ mục tìm kiếm và lịch cron của nó. Nó **không bao giờ** xóa post/term/user meta, term, taxonomy hay nội dung. Muốn giữ hồ sơ doanh nghiệp, xuất/ghi lại trước khi xóa.

## 4. Deployer for Git

Nếu theme được cài qua Deployer và nó được theo dõi nhánh nào đó: sau rollback, hãy đảm bảo không còn webhook nào có thể kích hoạt lại theme mới. Việc này chỉ liên quan tới cấu hình trên website; chưa xác minh được từ repository này.

## 5. Kiểm tra sau rollback

- `curl -I https://blog.rentbikehanoi.com/` trả 200, canonical đúng, H1 trang chủ đúng baseline.
- Một bài viết bất kỳ: 200, canonical bằng permalink, JSON-LD đủ các kiểu như baseline (Article, BreadcrumbList, WebPage, WebSite, Organization, Person, ImageObject).
- Sitemap và `robots.txt` không đổi.
