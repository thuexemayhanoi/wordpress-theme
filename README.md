# Nguyen Ha Editorial

Theme WordPress cổ điển (classic) cho blog biên tập kiến thức du lịch và xe máy, thiết kế cho website `https://blog.rentbikehanoi.com/` và quy mô từ vài trăm đến hơn 50.000 bài viết. Kèm một plugin đồng hành tùy chọn, **Nguyen Ha SEO Companion**, giữ dữ liệu biên tập (tóm tắt, FAQ, nguồn, Topic Cluster) để dữ liệu không mất khi đổi theme.

> **Trạng thái:** bản phát hành ứng viên cho **staging**. Chưa kích hoạt trên production và chưa được kiểm tra trên website thật; mọi kết quả kiểm thử dưới đây chạy trên môi trường dựng sẵn (xem [Kiểm thử](#kiểm-thử-và-giới-hạn-đã-biết)). Đọc [docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md) trước khi kích hoạt.

- Theme: `nguyen-ha-editorial` (phiên bản trong `style.css`) · yêu cầu WordPress ≥ 6.3, PHP ≥ 7.4.
- Plugin: `plugins/nguyen-ha-seo-companion` (ZIP riêng) · cùng yêu cầu.
- Giấy phép: GPL-2.0-or-later ([LICENSE](LICENSE)).

## Nguyên tắc: theme chỉ trình bày, SEO thuộc về plugin SEO

| Thành phần | Làm | Không làm |
|---|---|---|
| **Theme** | Bố cục, menu, trang chủ, hub/danh mục, bài viết, tìm kiếm, ảnh, dark mode, truy cập (a11y), hiệu năng | Không in `<title>` tùy biến, meta description, canonical, robots, Open Graph, JSON-LD, hreflang; không `noindex`; không ghi vào bài viết, danh mục, người dùng hay các tùy chọn site |
| **Yoast SEO / Rank Math** (plugin SEO của site) | Title, meta, canonical, robots, OG, sitemap, đồ thị JSON-LD | — |
| **Plugin đồng hành** | Dữ liệu biên tập `nh_*`, Topic Cluster, hồ sơ doanh nghiệp *do chủ sở hữu nhập*, bổ sung schema khi cần, chỉ mục tìm kiếm, làm mới cache | Không tự bịa dữ liệu, không in trùng đồ thị schema với plugin SEO |

Theme chạy độc lập: không có plugin đồng hành vẫn hiển thị đầy đủ, chỉ thiếu phần dữ liệu biên tập. Không dữ liệu doanh nghiệp, số điện thoại, giá hay đánh giá nào được ghi cứng trong mã (có test quét mã nguồn); thông tin liên hệ chỉ xuất hiện khi chủ sở hữu nhập vào plugin.

Chi tiết: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/SEO-RESPONSIBILITY.md](docs/SEO-RESPONSIBILITY.md), [docs/DATA-CONTRACT.md](docs/DATA-CONTRACT.md). Lịch sử thay đổi và các lỗi đã sửa: [CHANGELOG.md](CHANGELOG.md).

## Tính năng (chỉ những gì đã có mã và có test)

- **Trang:** trang chủ kiểu tạp chí (chế độ "bài mới nhất" và "trang tĩnh"), hub/danh mục cha–con, trang Pillar, bài viết, trang tĩnh (mặc định, rộng, pillar), tìm kiếm, tác giả, lưu trữ, 404, đính kèm, phân trang.
- **Điều hướng:** header cố định (tắt được), mega menu cha–con truy cập được bằng bàn phím, menu di động, menu dự phòng dựng từ cây danh mục khi chưa gán menu, breadcrumb luôn trùng `BreadcrumbList` mà công cụ tìm kiếm đọc (theo danh sách của Yoast khi có Yoast; nếu không thì theo danh mục chính).
- **Bài viết:** mục lục H2/H3 dựng phía máy chủ, thời gian đọc, tóm tắt/ý chính/FAQ/nguồn (chỉ khi có dữ liệu thật), hộp tác giả (chỉ từ dữ liệu người dùng đã nhập), bài liên quan theo tầng cluster → danh mục → danh mục cha, bài trước/sau trong cùng silo, hộp chủ đề/pillar, CTA do chủ sở hữu cấu hình.
- **Tìm kiếm:** tìm kiếm của WordPress có tô sáng từ khóa, gợi ý khi gõ qua REST có sẵn của lõi (không dịch vụ ngoài); plugin đồng hành có thể thêm chỉ mục FULLTEXT cho site lớn.
- **Giao diện:** sáng/tối (tôn trọng cài đặt hệ thống), responsive, một màu nhấn tùy chỉnh được kiểm tra độ tương phản WCAG AA, `prefers-reduced-motion`, `forced-colors`, bản in.
- **Hiệu năng:** CSS tới hạn nội tuyến, CSS chính không chặn render, một file JS `defer`, font hệ thống, không thư viện/bên thứ ba, đúng một ảnh LCP mỗi trang, cache mảnh có phiên bản, không truy vấn quét toàn bảng bài viết.
- **Cấu hình ở một nơi:** *Appearance → Customize* (6 nhóm: Brand, Layout, Article, Homepage, CTA, Footer), schema thiết lập duy nhất ở `inc/config.php`.
- **Dịch:** `.pot` và bản dịch tiếng Việt đầy đủ.

## Bảo vệ website đang chạy

Các cam kết dưới đây đều có test tự động (xem [docs/TESTING.md](docs/TESTING.md)):

- Hiển thị trang **không** thay đổi bài viết, trang, danh mục (ID, cha, slug), người dùng, permalink hay các tùy chọn site/quy tắc rewrite; không gọi mạng ra ngoài.
- Theme không in thẻ SEO nào; đúng một H1 mỗi trang; không bỏ qua cấp tiêu đề; một canonical/robots/JSON-LD duy nhất là của plugin SEO.
- Trang chủ giữ nguyên H1 và đoạn dẫn đã được khóa: hai ô Customizer (*Homepage headline*, *Homepage introduction*) tái tạo đúng từng ký tự văn bản baseline.
- Bài đăng qua REST API bởi workflow AI Writer (xem [docs/AI-WRITER-INTEGRATION.md](docs/AI-WRITER-INTEGRATION.md)) xuất hiện đúng danh mục, hub, trang chủ, bài liên quan và tìm kiếm ngay sau khi đăng; bản nháp/riêng tư/hẹn giờ không bao giờ lộ.
- Mọi file PHP của repository dừng nếu bị gọi trực tiếp; thư mục `tests/`, `tools/`, `docs/`, `plugins/` có `.htaccess` chặn truy cập web và không nằm trong ZIP cài đặt.

## Cài đặt, cập nhật, quay lại theme cũ

| Việc | Tài liệu |
|---|---|
| Cài bằng ZIP hoặc Deployer for Git; điều đã biết và chưa xác minh về Deployer | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) |
| Kiểm tra trước khi kích hoạt (cấu hình bắt buộc, đối chiếu SEO, giao diện, theo dõi) | [docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md) |
| Quay về theme Glass hiện tại (từ wp-admin, từ CSDL, về plugin đồng hành) | [docs/ROLLBACK.md](docs/ROLLBACK.md) |
| Plugin đồng hành: cài, cấu hình, chế độ schema, chỉ mục tìm kiếm, WP-CLI | [docs/COMPANION-PLUGIN.md](docs/COMPANION-PLUGIN.md) |

**Quan trọng:**

- Không kích hoạt theme này trên production khi chưa qua staging; không ghi đè theme Glass hiện tại (cài thêm, đừng thay thế).
- Nếu Deployer for Git theo dõi một nhánh và bật tự cập nhật, mọi commit trên nhánh đó đổi file trên website. **Không theo dõi nhánh phát triển trên production** và không merge vào `main` trước khi chủ sở hữu duyệt bản xem trước.
- Repository không chứa mật khẩu, Application Password, token hay bí mật nào; đừng thêm vào.

## Phát triển

```bash
composer install && npm ci          # công cụ kiểm thử và build
vendor/bin/phpcs                    # WordPress Coding Standards + PHPCompatibilityWP (PHP 7.4+)
npm run build                       # sinh assets/css|js từ assets/src (đã commit, không cần build khi triển khai)
npm run build:check                 # bản build khớp nguồn
WP_CORE_DIR=/đường/dẫn/wordpress vendor/bin/phpunit --testsuite theme
WP_CORE_DIR=/đường/dẫn/wordpress vendor/bin/phpunit --testsuite plugin
bash tools/package.sh && bash tools/verify-package.sh dist     # ZIP theme + ZIP plugin + manifest + SHA256SUMS
```

Báo cáo sẵn sàng chỉ-đọc cho staging/production, kèm dấu vân tay nội dung để so sánh trước và sau khi kích hoạt: `wp eval-file tools/staging-preflight.php content` (xem [docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md)).

Dựng site thử, nạp dữ liệu mẫu và chạy kiểm tra HTTP: `tests/bin/setup-site.sh`, `tests/bin/seed-fixtures.php`, `tests/bin/serve.sh`, `tests/bin/smoke.py` (xem [docs/TESTING.md](docs/TESTING.md)). Dữ liệu mẫu là dữ liệu giả trên tên miền `example.org`.

GitHub Actions (`.github/workflows/theme-qa.yml`) chạy trên push vào `feature/**` và `main` và trên mọi pull request. Artifact phát hành (ZIP theme, ZIP plugin, manifest, báo cáo QA) chỉ được tạo khi mọi cổng bắt buộc đạt; workflow không tạo tag, không tạo GitHub Release, không đẩy nhánh và không liên hệ website production.

## Kiểm thử và giới hạn đã biết

Số liệu thật của từng lớp kiểm thử, lỗi đã tìm và đã sửa, và những gì **chưa** kiểm tra được nằm trong [docs/TESTING.md](docs/TESTING.md) và [docs/PERFORMANCE.md](docs/PERFORMANCE.md). Tóm tắt trung thực:

- Đã chạy thật (máy phát triển và GitHub Actions): PHPUnit (theme + plugin; đơn site và multisite; hai cấu trúc permalink; cả trên PHP 7.4), PHPCS/WPCS/PHPCompatibilityWP, kiểm tra cú pháp PHP 7.4–8.4, build asset, smoke HTTP trên WordPress 6.3.5 và 6.5.5, bộ kiểm thử trình duyệt Playwright + axe (Chromium; 375, 768 và 1440 px), so sánh thẻ head, JSON-LD và breadcrumb với **Yoast SEO 28.7** và **Rank Math 1.0.280** thật (cài từ wordpress.org trong CI) và với site không có plugin SEO, đo hiệu năng ở 1.000, 10.000 và 50.000 bài.
- **Chưa** có: dữ liệu Core Web Vitals thực tế (chỉ đo phòng thí nghiệm, trên máy phát triển, không phải hosting thật); kiểm tra trên website thật và trên cấu hình Deployer for Git thật; đối chiếu với *cấu hình* Yoast của website (CI dùng Yoast 28.7 mặc định trên site mẫu; cần staging); Firefox, Safari, trình đọc màn hình và MySQL 5.7.
- Theme mới **không** là bản sao của theme Glass hiện tại: shortcode/block/template riêng của Glass có thể hiển thị khác. Kiểm tra từng trang trong 18 trang tĩnh trước khi kích hoạt.

---

## English summary

**Nguyen Ha Editorial** is a classic WordPress theme (WordPress ≥ 6.3, PHP ≥ 7.4, GPL-2.0-or-later) for an editorial blog about travel and motorbikes, built for `blog.rentbikehanoi.com` and for sites from hundreds to 50,000+ posts, plus an optional companion plugin that keeps editorial data (summary, FAQ, sources, topic clusters) independent of the theme.

- **The theme only presents.** It never prints a title override, meta description, canonical, robots, Open Graph or JSON-LD, never adds `noindex`, and never writes to posts, terms, users or site options. The SEO plugin (Yoast SEO / Rank Math) owns all SEO output; the companion plugin owns editorial data and only adds schema the SEO plugin lacks, never a second graph.
- **Protected by tests:** page views mutate nothing, make no outgoing request, print one H1 and no SEO markup, keep the approved homepage headline, and show content published through the REST API (as the AI Writer does) in the right category, hub, related posts and search without ever exposing drafts, private or scheduled posts.
- **Not production-verified.** Everything was tested on disposable sites (PHPUnit on PHP 7.4 and 8.3, PHPCS, PHP 7.4–8.4 syntax, HTTP smoke on WordPress 6.3.5/6.5.5/7.1.3, a Playwright + axe browser suite on Chromium at three viewports, the real Yoast SEO 28.7 and Rank Math 1.0.280 installed from wordpress.org in CI, lab measurements up to 50,000 posts). Field Core Web Vitals, the live Deployer for Git configuration and the live Yoast configuration are unverified; use a staging site first ([docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md)). Do not merge to `main` or activate on production before the owner has reviewed a preview, because a branch tracked by Deployer for Git updates the live files on every commit.
