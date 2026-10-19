# Plugin Nguyen Ha VI-EN: blog song ngữ Việt – Anh bản địa

Plugin `plugins/nguyen-ha-vi-en/` thêm phần tiếng Anh dưới `/en/` cho `https://blog.rentbikehanoi.com/`. Tiếng Việt vẫn nằm ở nguyên chỗ hiện tại. **Không có bước nào trong tài liệu này, và không có workflow nào, bật tính năng trên website đang chạy**: bật là quyết định của chủ sở hữu, sau khi xem trước trên staging.

Plugin chạy độc lập (có ZIP riêng, không cần theme hay plugin đồng hành của repository này), không dùng Polylang, WPML hay plugin đa ngôn ngữ nào, không dịch tự động, không gọi API trả phí và không bao giờ tự đăng bài tiếng Anh.

## 1. Quy tắc bảo vệ (luôn đúng)

| Quy tắc | Cách plugin giữ |
|---|---|
| ~500 bài tiếng Việt không bị sửa, xóa, đổi slug, URL, canonical, tiêu đề hay trường SEO | Plugin không ghi vào bài viết nào. Dữ liệu duy nhất nó ghi: một tùy chọn riêng (danh sách cặp bài `nh_vien_translation_pairs_v1`), quy tắc rewrite của chính nó (một nút bấm, và khi gỡ kích hoạt để rút lại), và khi bạn bấm nút, một trang **nháp** `en`. |
| Cài hoặc kích hoạt plugin không làm đổi website | Công tắc kép: hằng số `NH_VIEN_ENABLE_EN_ROUTES` **và** quản trị viên bấm "Store the rewrite rules". Trước đó plugin không đổi URL, truy vấn, trang hay thẻ nào. Trên production cần thêm hằng số thứ hai `NH_VIEN_ALLOW_PRODUCTION`. |
| Không tự đăng bài tiếng Anh | Một bài tiếng Anh chỉ hiện khi **cả hai** bài của cặp đã xuất bản, slug dùng được và không có trang nào chiếm địa chỉ đó. Nháp, bài chưa ghép, slug lạ → 404 ở mọi địa chỉ. |
| Không dùng plugin đa ngôn ngữ, không API trả phí | Chỉ mã trong thư mục plugin; không gọi dịch vụ ngoài; không nạp JavaScript. |

## 2. Cách hoạt động

**Cặp bài.** Mỗi bài tiếng Anh là một bài WordPress bình thường (tiêu đề, nội dung, slug, mô tả, SEO riêng), ghép một–một với bài tiếng Việt tương ứng ở *Công cụ → VI/EN Links* (nhập ID hai bài). Cặp hoạt động hai chiều: từ bài Việt sang bài Anh và ngược lại.

| Địa chỉ | Nội dung |
|---|---|
| `/en/` và `/en/page/2/` | Trang chủ tiếng Anh: danh sách bài Anh được phép hiện. Nếu có **trang đã xuất bản** tại `/en/` (nội dung `[nh_vien_posts]`), trang đó là trang chủ tiếng Anh và không có trang 2. |
| `/en/{slug}/` (và `/en/{slug}/2/`) | Bài tiếng Anh. Slug chỉ gồm chữ thường, số, gạch ngang/gạch dưới; không dùng từ dành riêng (`page`, `category`, `tag`, `author`, `search`, `feed`, …). |
| `/en/category/{đường dẫn}/`, `/en/tag/{slug}/` (có phân trang) | Danh mục và thẻ chỉ liệt kê bài tiếng Anh; danh mục không có bài Anh → 404. |
| `/en/?s=…` | Tìm kiếm chỉ trong bài tiếng Anh (không lập chỉ mục, `noindex`). |
| `/{slug}/` của một bài Anh đã ghép | **301** sang `/en/{slug}/` (chống nội dung trùng). |
| Trang tĩnh dưới `/en/…` | Giữ nguyên địa chỉ và thắng bài Anh cùng slug. |
| `/en/feed/`, trang tác giả, lưu trữ theo ngày | Không có (404). Feed chính vẫn là tiếng Việt. |

**Mỗi danh sách một ngôn ngữ.** Trang chủ, danh mục, thẻ, tìm kiếm, widget bài mới, bài trước/sau và **gợi ý khi gõ ở ô tìm kiếm** (trong thân trang, địa chỉ REST `/wp-json/wp/v2/search` mang thêm `nh_vien_lang=vi|en`) của phần tiếng Việt không bao giờ liệt kê bài tiếng Anh, và ngược lại; client REST khác không gửi tham số đó thì vẫn thấy cả hai ngôn ngữ. Các trang `/en/…` dùng ngôn ngữ `en_US` (`<html lang="en-US">`, chuỗi của WordPress và theme bằng tiếng Anh); logo và ô tìm kiếm của theme dẫn về `/en/`; menu `{slug}-en` (ví dụ `primary-en`) thay menu tiếng Việt cùng vị trí khi có.

**Bộ chuyển ngôn ngữ.** Hai liên kết VI và EN, mục tiêu chạm 44 px, dùng được bằng bàn phím và trình đọc màn hình; trên bài viết dẫn tới bản dịch, ở nơi khác dẫn về trang chủ của ngôn ngữ kia, và ẩn khi không có nơi để dẫn. Theme Nguyen Ha Editorial đặt nó ngay **sau** liên kết "Skip to content"; theme khác nhận nó ở `wp_body_open()`; theme không gọi `wp_body_open()` nhận nó phía trên nội dung bài; ở mọi nơi khác dùng shortcode `[nh_vien_switcher]`.

**SEO.** `hreflang` (vi, en, x-default) giữa hai bài của một cặp và giữa hai trang chủ; canonical riêng cho từng ngôn ngữ; breadcrumb tiếng Anh; địa chỉ tiếng Anh trong sơ đồ trang. Với **Yoast SEO** (plugin SEO của website): Yoast vẫn là chủ của tiêu đề, canonical và **một** `sitemap_index.xml`; plugin chỉ dùng các filter được tài liệu hóa của Yoast để (1) lấy canonical và `og:url` là địa chỉ `/en/…`, tôn trọng canonical biên tập viên đã gõ tay, (2) ghi breadcrumb và `BreadcrumbList` tiếng Anh, (3) thêm trang chủ, danh mục và thẻ tiếng Anh vào các sơ đồ trang Yoast đã có, (4) bỏ bài Anh chưa được phép hiện khỏi sơ đồ. Yoast không in hreflang cho các bài riêng lẻ nên plugin in hreflang (chỉ cho cặp bài và hai trang chủ).

**Cache.** Địa chỉ `/en/…` là URL riêng nên cache trang theo URL tự tách hai ngôn ngữ. Cache mảnh của theme Nguyen Ha Editorial có hậu tố ngôn ngữ; khi thêm hoặc bỏ một cặp, plugin báo cho cache mảnh của theme và hàng đợi xóa cache trang của plugin đồng hành (cả hai tùy chọn). Với cache khác, hãy xóa cache sau khi ghép bài.

## 3. Cài đặt và công tắc

1. Lấy `nguyen-ha-vi-en-<phiên bản>.zip` từ artifact `release-<sha>` của một lần chạy Theme QA **xanh** (đối chiếu `SHA256SUMS`/`manifest.json`). ZIP chỉ chứa plugin (không có thư mục `tests/`).
2. *Plugins → Add New → Upload Plugin*, cài, kích hoạt. **Chưa có gì thay đổi.**
3. (Staging) thêm vào `wp-config.php`: `define( 'NH_VIEN_ENABLE_EN_ROUTES', true );` và đảm bảo môi trường là `staging`/`development`/`local` (`define( 'WP_ENVIRONMENT_TYPE', 'staging' );`).
4. *Công cụ → VI/EN Links → Store the rewrite rules* (nút chỉ có tác dụng với quản trị viên, có nonce; lưu lại *Cài đặt → Đường dẫn tĩnh* cũng lưu các quy tắc). Màn hình nêu rõ "English pages are live" là Yes/No và lý do.
5. Viết bài tiếng Anh dạng **nháp**, ghép với bài tiếng Việt, kiểm tra, rồi xuất bản.
6. (Tùy chọn) nút *Create a draft English home page* tạo trang nháp `en` có `[nh_vien_posts]`; bạn tự chỉnh và tự xuất bản.

## 4. Danh sách kiểm tra staging (bắt buộc trước khi bật production)

Làm trên **bản sao** có cùng theme, cùng plugin SEO, cùng cấu trúc permalink và dữ liệu giống production. Đạt = mọi mục đạt; còn mục chưa đạt thì **không** bật production.

- [ ] Có sao lưu **mới** (CSDL + `wp-content`) đã mở thử được; biết cách quay lại (mục 6).
- [ ] Trước khi bật, lưu HTML của trang chủ, một danh mục, một bài, `/?s=xe`, `/feed/`; sau khi bật hằng số và bấm "Store the rewrite rules" (chưa ghép bài nào) các trang đó **giống hệt**.
- [ ] Không có trang hay bài nào đã dùng `/en/` hoặc `/en/…` ngoài ý muốn (*Công cụ → VI/EN Links* báo khi một cặp bị ẩn vì trang chiếm địa chỉ).
- [ ] Một bài Việt đã ghép: có hreflang `vi`, `en`, `x-default` đúng hai chiều; một canonical; bộ chuyển ngôn ngữ dẫn đúng.
- [ ] Bài Anh tương ứng: `<html lang="en-US">`, canonical là `/en/{slug}/`, JSON-LD (`WebPage`/`Article`/`BreadcrumbList`) có `url` là `/en/{slug}/`; địa chỉ `/{slug}/` trả **301** sang `/en/{slug}/` và không bị vòng lặp.
- [ ] `/sitemap_index.xml` vẫn là **một** sơ đồ; bài Anh xuất hiện ở `/en/{slug}/`, không có nháp, không có địa chỉ gốc của bài Anh.
- [ ] `/en/`, một danh mục và một thẻ tiếng Anh: chỉ có bài Anh, phân trang đúng, canonical riêng; `/en/?s=` là `noindex`.
- [ ] Trang chủ, danh mục và tìm kiếm tiếng Việt **không** chứa bài Anh; bài trước/sau, widget "bài mới" và gợi ý khi gõ ở ô tìm kiếm không lẫn ngôn ngữ.
- [ ] Bộ chuyển ngôn ngữ nhìn thấy và dùng được ở điện thoại (375 px), máy tính bảng (768 px), máy tính (1440 px), bằng bàn phím và trình đọc màn hình; không có cuộn ngang.
- [ ] Cache trang/CDN: `/en/…` được lưu riêng, đã xóa cache sau khi ghép bài; không có nội dung ngôn ngữ này hiện ở URL của ngôn ngữ kia.
- [ ] Bài nháp, bài chưa ghép, slug lạ, trang đang chiếm địa chỉ: tất cả 404; không có "soft 404" (trang 200 nhưng rỗng).
- [ ] Thử gỡ: bỏ hằng số → `/en/…` thành 404 và trang tiếng Việt vẫn như cũ; bấm "Store the rewrite rules"; gỡ kích hoạt plugin → trang tiếng Việt vẫn như cũ.

## 5. Bật trên production

Chỉ khi mục 4 đạt hoàn toàn và chủ sở hữu duyệt bằng văn bản:

1. Sao lưu CSDL + `wp-content` (từ hPanel) và xác nhận mở được.
2. Cài ZIP, kích hoạt (chưa thay đổi gì).
3. Thêm **cả hai** hằng số vào `wp-config.php`: `NH_VIEN_ENABLE_EN_ROUTES` và `NH_VIEN_ALLOW_PRODUCTION` (cùng giá trị `true`).
4. Bấm "Store the rewrite rules". Mở `/en/` và một bài đã ghép; kiểm tra các mục chính của mục 4 trên chính website.
5. Đưa `https://blog.rentbikehanoi.com/sitemap_index.xml` vào Google Search Console nếu chưa có (không cần gửi lại sơ đồ riêng cho `/en/`), theo dõi Coverage trong vài tuần.

Plugin **không** tự kích hoạt, không tự merge và không tự đưa gì lên production: `main` chỉ đổi khi chủ sở hữu quyết định.

## 6. Quay lại

1. Xóa hằng số `NH_VIEN_ENABLE_EN_ROUTES` (và `NH_VIEN_ALLOW_PRODUCTION`): plugin ngừng ngay mọi thay đổi và `/en/…` trả 404.
2. *Công cụ → VI/EN Links → Store the rewrite rules*: quy tắc rewrite tiếng Anh bị rút khỏi dữ liệu đã lưu.
3. Gỡ kích hoạt plugin nếu muốn. Gỡ kích hoạt cũng tự rút các quy tắc của plugin, nên dù làm sai thứ tự cũng **không** còn địa chỉ `/en/…` nào trả về bản sao của trang chủ tiếng Việt. Sau đó địa chỉ `/en/{slug}/` cũ trả 404 hoặc được WordPress dẫn về địa chỉ gốc của bài tiếng Anh.
4. Danh sách cặp bài vẫn nằm trong CSDL (tùy chọn `nh_vien_translation_pairs_v1`) để bật lại không phải làm lại; xóa tay nếu muốn.

Trang tiếng Việt không đổi ở mọi bước (kiểm thử CI so sánh HTML với bản chụp ban đầu).

## 7. Đã kiểm thử gì

| Lớp | Nội dung | Nơi chạy |
|---|---|---|
| Kiểm thử offline (PHP thuần) | `tests/smoke.php`, `links.php`, `routes.php`, `boot.php`, `query.php`: công tắc, quy tắc, slug, cặp bài, nonce/quyền, phạm vi truy vấn, hook theo từng môi trường | PHP 7.4 – 8.4 trong CI |
| Site WordPress thật qua HTTP | `tests/site/run.php`: trạng thái và chuyển hướng, ngôn ngữ từng danh sách, bộ chuyển ngôn ngữ, hreflang/canonical/sơ đồ, an ninh (XSS, tham số thô, CSRF, quyền), cache tách ngôn ngữ, màn hình quản trị, trang chủ tiếng Anh dạng trang, công tắc đóng lại, **trang tiếng Việt giống hệt khi bật/tắt**, gỡ kích hoạt | WordPress 7.1.3 và 6.3.5; theme Editorial có và không có Yoast SEO; theme khối mặc định; theme cổ điển không gọi `wp_body_open()` |
| Trình duyệt | `tests/e2e/switcher.spec.mjs`: bộ chuyển ngôn ngữ ở 375/768/1440 px, vùng chạm, bàn phím, thứ tự sau liên kết bỏ qua, axe (sáng và tối) | Chromium trong CI |
| Đóng gói | `tools/verify-package.sh`: thư mục và tệp bắt buộc, không có `tests/`, bảo vệ truy cập trực tiếp ở mọi tệp, một phiên bản ở mọi nơi, không phụ thuộc theme hay plugin đồng hành | CI (job `static`) |

**Chưa kiểm chứng (không khẳng định là ổn):**

- Website thật và staging thật: môi trường này không có staging; mọi kiểm thử chạy trên site dựng sẵn bằng dữ liệu giả. Mục 4 là việc phải làm trên staging.
- Cấu hình Yoast thật của website (kiểu bài, taxonomy trong breadcrumb, schema); CI dùng Yoast mặc định từ wordpress.org.
- Rank Math, All in One SEO, SEOPress, The SEO Framework: được nhận diện (không in canonical thứ hai) nhưng không có kiểm thử.
- WordPress multisite; trình duyệt ngoài Chromium; thiết bị thật và trình đọc màn hình; Core Web Vitals thực địa.
- Cache trang/CDN thật (LiteSpeed Cache, Cloudflare…): chỉ kiểm tra bằng các hook chung.

## 8. Giới hạn đã biết

- Một site cho mỗi lần cài; multisite chưa kiểm thử.
- Không có feed tiếng Anh; số bài WordPress lưu cho danh mục/thẻ và widget "Archives" theo tháng đếm cả hai ngôn ngữ.
- Logo và ô tìm kiếm đổi ngôn ngữ nhờ `home_url( '/' )`; theme dựng các liên kết đó bằng cách khác (một số theme khối) vẫn dẫn về trang chủ tiếng Việt.
- Chỉ bài tiếng Anh có slug ASCII đơn giản mới truy cập được; mục menu, widget và văn bản gõ tay không tự dịch.
- Khi có trang tĩnh tại `/en/` thì trang đó là trang chủ tiếng Anh và không có `/en/page/2/`.

## 9. Lệnh kiểm tra nhanh

```bash
for t in plugins/nguyen-ha-vi-en/tests/*.php; do php "$t"; done            # offline, chỉ cần PHP
bash tools/package.sh && bash tools/verify-package.sh dist                    # ZIP độc lập + kiểm tra ZIP
# Site thật qua HTTP (dựng site bằng tests/bin/setup-site.sh, xem docs/TESTING.md):
NH_VIEN_SEED_STEP=content wp eval-file plugins/nguyen-ha-vi-en/tests/site/seed.php
php plugins/nguyen-ha-vi-en/tests/site/run.php --base=http://127.0.0.1:8080 --fixture=wp-content/nh-vien-fixture.json --suite=main
NH_BASE_URL=http://127.0.0.1:8080 npx playwright test -c plugins/nguyen-ha-vi-en/tests/e2e/playwright.config.mjs
```
