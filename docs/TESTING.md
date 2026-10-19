# Kiểm thử

Tài liệu này nói **điều gì đã được chạy thật, ở đâu, cho kết quả gì**, điều gì kiểm thử đã tìm ra, và điều gì **chưa** được kiểm tra. Số liệu lấy từ các lần chạy trên cam kết `4a531e1` của nhánh `feature/premium-seo-theme`; một lớp không chạy được trong môi trường phát triển (không truy cập được wordpress.org) được ghi rõ là chạy ở GitHub Actions và nêu kết quả của lần chạy đó.

## 1. Các lớp kiểm thử và kết quả

### 1.1 GitHub Actions (cam kết mã `4a531e1`, lần chạy [#26](https://github.com/thuexemayhanoi/wordpress-theme/actions/runs/39036634764): mọi job đạt, kể cả job tạo artifact phát hành)

Các commit sau `4a531e1` chỉ đổi tài liệu và một bước chẩn đoán của workflow (chỉ chạy khi một job thất bại); không có mã của theme hay plugin nào đổi.

| Lớp | Môi trường | Kết quả |
|---|---|---|
| PHPCS (WordPress Coding Standards + PHPCompatibilityWP từ PHP 7.4) | job `static` | 0 lỗi, 0 cảnh báo |
| Build asset khớp nguồn; `.pot` theme (160 chuỗi) và plugin (250 chuỗi) cập nhật; 10 test của `qa-report.py`; đóng gói và `verify-package.sh` | job `static` | đạt |
| Cú pháp PHP, mọi tệp PHP (197) | PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 (ảnh chính thức) | 6 trên 6 đạt |
| PHPUnit theme, ba cấu hình: `/%category%/%postname%/`, `/%postname%/` (cấu trúc của site thật), multisite | WordPress 7.1.3, PHP 8.3, MySQL 8.0.46 | 214 test, 7.961 assertion: đạt ở cả ba cấu hình, 0 bỏ qua |
| PHPUnit plugin, một site và multisite | như trên | 618 test: một site 9.909 assertion (6 bỏ qua); multisite 9.932 assertion (1 bỏ qua); đạt |
| PHPUnit trên WordPress mới nhất (chỉ để tham khảo, không chặn phát hành) | WordPress "latest" | theme 214 test / 7.961 assertion (hai cấu trúc permalink) và plugin 618 / 9.909 (6 bỏ qua): đạt |
| PHPUnit trên PHP 7.4.33 | ảnh `php:7.4-cli-alpine` | theme 214 test / 7.961 assertion (hai cấu trúc permalink) và plugin 618 / 9.914 (7 bỏ qua): đạt |
| Smoke HTTP mọi loại trang (22 địa chỉ) và dấu vân tay nội dung trước/sau lượt xem | WordPress 6.3.5 (plugin tắt và bật) và 6.5.5 (bật) | 3 trên 3 đạt: 0 lỗi, dấu vân tay không đổi |
| Trình duyệt Playwright + axe, Chromium | mobile 375, tablet 768, desktop 1440 px; WordPress 7.1.3 | 285 (mobile) + 197 (tablet) + 303 (desktop) test: 780 đạt, 0 thất bại, 5 bỏ qua (xem dưới) |
| So sánh thẻ head, JSON-LD, FAQ và breadcrumb với plugin SEO thật trên 5 loại trang (trang chủ, bài có FAQ, danh mục, tìm kiếm, 404) | Yoast SEO 28.7 · Rank Math 1.0.280 · không có plugin SEO | 5 trên 5 trang đạt ở cả ba |

### 1.2 Máy phát triển (cùng mã)

| Lớp | Kết quả |
|---|---|
| `vendor/bin/phpcs`, `npm run build:check`, `tools/check-pot.sh` (theme và plugin), `unittest` của công cụ (10 test) | đạt |
| PHPUnit theme (mặc định, `/%postname%/`, multisite) | 214 test, 7.961 assertion: đạt ở cả ba cấu hình |
| PHPUnit plugin (một site, multisite) | 618 test: một site 9.909 assertion (6 bỏ qua); multisite 9.932 assertion (1 bỏ qua); đạt |
| Trình duyệt Playwright + axe trên site thử cục bộ (WordPress 7.1.3, ba viewport, 6,5 phút) | 785 test: 780 đạt, 0 thất bại, 5 bỏ qua |
| Đo hiệu năng ở 1.000, 10.000 và 50.000 bài | số liệu trong [PERFORMANCE.md](PERFORMANCE.md) |

PASS/FAIL/SKIP là số của từng lần chạy, không gộp; "bỏ qua" luôn có lý do: ở lần chạy một site, 6 test của plugin chỉ áp dụng cho mạng nhiều site; ở lần chạy multisite, 1 test chỉ áp dụng cho một site; trên PHP 7.4 (ảnh Alpine không có Node) thêm 1 test chạy kịch bản JavaScript của trang quản trị; ở job trình duyệt `desktop`, 5 test của `seo-plugins.spec.mjs` bị bỏ qua vì biến `NH_SEO_PLUGIN` không đặt (job `seo-plugins` chạy chúng thật, mục 3).

## 2. Chạy lại từng lớp trên máy của bạn

```bash
composer install && npm ci                       # công cụ phát triển (không thuộc ZIP cài đặt)

vendor/bin/phpcs                                  # WordPress Coding Standards + PHPCompatibilityWP (PHP 7.4 trở lên)
npm run build:check                               # CSS/JS tối ưu đúng với nguồn, cú pháp hợp lệ
bash tools/check-pot.sh "wp" . nguyen-ha-editorial languages/nguyen-ha-editorial.pot --skip-theme-json
bash tools/check-pot.sh "wp" plugins/nguyen-ha-seo-companion nguyen-ha-seo-companion plugins/nguyen-ha-seo-companion/languages/nguyen-ha-seo-companion.pot
python3 -I -m unittest discover -s tools -p 'test_*.py'
bash tools/package.sh && bash tools/verify-package.sh dist

# PHPUnit cần một thư mục WordPress đã giải nén và một CSDL dùng để thử (BỊ XÓA SẠCH các bảng có tiền tố wptests_)
export WP_CORE_DIR=/duong/dan/wordpress WP_TESTS_DB_NAME=wp_phpunit WP_TESTS_DB_USER=wp WP_TESTS_DB_PASSWORD=wp
vendor/bin/phpunit --testsuite theme
NH_TEST_PERMALINKS=/%postname%/ vendor/bin/phpunit --testsuite theme      # cấu trúc permalink của website thật
vendor/bin/phpunit --testsuite plugin
WP_MULTISITE=1 vendor/bin/phpunit --testsuite theme                       # và plugin: mạng nhiều site

# Kiểm tra qua HTTP và trình duyệt cần một site thử đang chạy (tests/bin/setup-site.sh, seed-fixtures.php, serve.sh)
python3 -I tests/bin/smoke.py http://127.0.0.1:8080
NH_BASE_URL=http://127.0.0.1:8080 NH_ALLOW_PROTECTED_LINKS=1 npm run test:e2e
node tests/e2e/summarize.mjs                                              # bảng kết quả
```

Chi tiết bộ kiểm thử trình duyệt (biến môi trường, dự án viewport, thẻ `@desktop-only`…): [tests/e2e/README.md](../tests/e2e/README.md).

## 3. GitHub Actions (`.github/workflows/theme-qa.yml`)

Chạy khi push lên `main` và `feature/**`, khi mở pull request, và thủ công. Chỉ dùng các action chính thức của GitHub; mọi bước khác là lệnh shell mà bạn có thể chạy lại như trên.

| Job | Kiểm tra gì | Cổng phát hành |
|---|---|---|
| `static` | PHPCS (WPCS + PHPCompatibilityWP), build asset, `.pot` của theme và plugin, test của `qa-report.py`, đóng gói và `verify-package.sh` | có |
| `php-syntax` | `php -l` mọi file PHP với ảnh PHP chính thức 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 | có |
| `phpunit` | Theme (hai cấu trúc permalink) và plugin trên WordPress 7.1.3, MySQL 8.0, rồi cả hai trên multisite; một lần chạy trên bản WordPress mới nhất chỉ để tham khảo, không chặn phát hành | có (7.1.3) |
| `php74` | Theme (hai permalink) và plugin trên **PHP 7.4** (ảnh `php:7.4-cli-alpine`, công cụ phát triển được Composer giải quyết lại cho PHP 7.4) | có |
| `min-wp` | Cài WordPress 6.3.5 (có và không có plugin đồng hành) và 6.5.5, kích hoạt theme, nạp dữ liệu mẫu, `smoke.py` duyệt mọi loại trang, rồi so dấu vân tay nội dung trước/sau (việc xem trang không được đổi bài viết, danh mục, tùy chọn, quy tắc rewrite) | có |
| `e2e` | Playwright + axe trên Chromium, ba viewport (mobile 375, tablet 768, desktop 1440), mỗi viewport một job với site riêng | có |
| `seo-plugins` | Cài **Yoast SEO**, **Rank Math** từ wordpress.org (và một lần không có plugin SEO nào): đúng một canonical, một mô tả, một đồ thị JSON-LD, FAQPage chỉ khi câu hỏi hiển thị, breadcrumb trùng `BreadcrumbList`, theme không có microdata. Rank Math không in thẻ SEO nào cho đến khi chủ sở hữu kết nối tài khoản hoặc bỏ qua bước đăng ký, nên job chọn bỏ qua (xem [SEO-RESPONSIBILITY.md](SEO-RESPONSIBILITY.md) mục 5); khi một job này đỏ, nó in ra các thẻ đã được in, tùy chọn và đoạn mã nguồn của plugin để lần sau không phải đoán | có |
| `vi-en-site` | Plugin `nguyen-ha-vi-en` **cài từ ZIP của chính nó** (`tools/package.sh`) trên site WordPress thật (MySQL 8.0): WordPress 7.1.3 với theme Editorial + Yoast SEO, theme Editorial không có plugin SEO, theme khối Twenty Twenty-Five (không có plugin đồng hành), và WordPress 6.3.5 (bản tối thiểu của plugin). Chạy `plugins/nguyen-ha-vi-en/tests/site/run.php` qua HTTP: trang tiếng Việt **giống hệt** khi cổng đóng và khi cổng mở chưa ghép bài; trạng thái/chuyển hướng/404, mỗi danh sách một ngôn ngữ, bộ chuyển ngôn ngữ, hreflang/canonical/sơ đồ (JSON-LD, `sitemap_index.xml` khi có Yoast), an ninh (XSS, biến truy vấn thô, CSRF, quyền), cache tách ngôn ngữ, màn hình quản trị, trang chủ tiếng Anh dạng trang, theme cổ điển không gọi `wp_body_open()`, công tắc đóng lại (không gì đổi), và **gỡ kích hoạt** (không địa chỉ `/en/…` nào còn trả bản sao trang chủ, trang Việt giống hệt bản chụp đầu). Sau đó bộ `dashboard` (kích hoạt lại plugin, không có hằng số trong `wp-config.php`): công tắc trong *Công cụ → VI/EN Links* qua HTTP với đăng nhập thật (nonce sai/thiếu, khách, người đăng ký, GET và chế độ lạ bị từ chối; bật và tắt với quy tắc rewrite được lưu/rút và trang Việt giống hệt; website production có xác nhận; `NH_VIEN_ALLOW_PRODUCTION`; CSDL chép sang địa chỉ khác; hằng số `NH_VIEN_ENABLE_EN_ROUTES` làm phanh khẩn cấp; đường dẫn tĩnh Plain; bài giữ địa chỉ `/en/`; gỡ và kích hoạt lại). Rồi bộ Playwright + axe của bộ chuyển ngôn ngữ ở 375/768/1440 px. Chi tiết: [VI-EN-PLUGIN.md](VI-EN-PLUGIN.md) | có |
| `release` | `tools/qa-report.py` tổng hợp: **chỉ khi mọi cổng ở trên đạt** mới tạo artifact phát hành (ZIP theme, ZIP plugin đồng hành, ZIP plugin VI-EN, `manifest.json`, `SHA256SUMS`, báo cáo QA). Không tạo tag, không tạo GitHub Release, không đẩy nhánh, không liên hệ website thật. | — |

Artifact `release-<commit>` chỉ tồn tại khi mọi cổng đạt; `packages` (ZIP) luôn được tải lên từ job `static` để kiểm tra thủ công; các artifact `junit-*` và, khi có lỗi, `e2e-report-*`/`seo-report-*` (báo cáo HTML, trace, ảnh chụp) giữ 14 ngày.

## 4. Lỗi do kiểm thử và rà soát tìm ra (và đã sửa)

Mỗi lỗi của theme và plugin dưới đây được **tái hiện bằng một kiểm thử thất bại trước khi sửa** rồi mới sửa; các lỗi chỉ lộ ra trên máy thật của GitHub (dòng "GitHub Actions") được tái hiện bằng chính lần chạy CI đỏ. Danh sách đầy đủ theo từng commit nằm trong [CHANGELOG.md](../CHANGELOG.md).

| Nguồn phát hiện | Số lỗi | Điều đã tìm ra (rút gọn) | Kiểm thử hồi quy |
|---|---|---|---|
| Rà soát độc lập theme | 8 | **Cao:** bài/trang đặt mật khẩu in tóm tắt, FAQ, nguồn cạnh ô nhập mật khẩu. **Trung bình:** trang chủ chậm 5–6 s ở lần tải đầu sau mỗi chỉnh sửa trên 50.000 bài; khách ghi thêm hàng vào `wp_options` bằng `/?author=<số>`. **Thấp:** bộ bọc bảng làm mất bài; trang đính kèm nêu bài cha trong thùng rác; số bài tác giả không làm mới; mảng trong meta in "Array"; menu và tìm kiếm không dùng được khi không có JavaScript | `NH_Test_Review_Findings`, `NH_Test_Browser_Findings` |
| Rà soát độc lập plugin | 8 | **Trung bình:** REST lộ `nh_*` của bài đặt mật khẩu; `FAQPage` in cho FAQ không hiển thị; chỉ mục tìm kiếm báo "Ready" khi đã lệch nội dung. **Thấp:** tên cluster có `\` áp dụng lại mỗi lần lưu; multisite lập chỉ mục/xóa cache nhầm site; URL lưu trữ mang chuỗi truy vấn của khách; `nh_keywords` công khai; cluster của bản nháp bị liệt kê | `NH_Test_Data_Rest`, `NH_Test_Data_Cluster_Sync`, `NH_Test_Shared_Trail`, `NH_Test_Search_Drift`, `NH_Test_Search_Index` (multisite), `NH_Test_Cache_Dispatch`, `NH_Test_Plugin_Integration` |
| Bộ kiểm thử trình duyệt (lần chạy đầu: 156 thất bại) | 13 | Chữ tiêu đề widget chân trang vô hình ở chế độ sáng (tương phản 1:1); `aria-label` của liên kết thương hiệu khác chữ hiển thị (WCAG 2.5.3, làm hỏng 92 kiểm thử axe); hai vùng `role="search"` cùng tên; `aria-labelledby` trỏ tới tiêu đề không có; `<mark>` ở chế độ tối dưới 4,5:1; in: mất câu trả lời FAQ và chế độ tối in chữ gần như trắng; avatar bị bóp; thẻ tràn khung ở 320 px với chữ 200%; "1 suggestions available"; hộp Pillar gây dịch chuyển bố cục; menu xuống dòng ở 961–1100 px; mục lục/neo đáp cách tiêu đề 99 px và điểm "mục hiện tại" sai; `/?s=` liệt kê mọi bài | `NH_Test_Browser_Findings` (phía máy chủ) và chính bộ trình duyệt trong CI |
| GitHub Actions (môi trường thật) | 6 | MySQL 8.0 in `SHOW CREATE TABLE` khác MariaDB (kỳ vọng của test viết theo MariaDB); fetch tích hợp của Node 22 ném `assert(!this.paused)` khi đọc phản hồi của máy chủ PHP tích hợp (mọi job trình duyệt dừng trước khi chạy test); `GLOB_BRACE` không có trên musl (ảnh PHP 7.4 Alpine); phán định cấu trúc dữ liệu đòi đúng một khối JSON-LD nên sẽ thất bại với mọi bài có FAQ khi dùng plugin SEO thật (khối `FAQPage` riêng là thiết kế); với Yoast SEO 28.7 thật, breadcrumb hiển thị khác `BreadcrumbList` của Yoast (bài viết: Yoast không liệt kê danh mục theo mặc định; tìm kiếm: "You searched for"); không có plugin SEO, crumb tìm kiếm nêu `/search/x/` cho trang đang ở `/?s=x` | `NH_Test_Breadcrumb_Source`, `NH_Test_Breadcrumb_Parity`, `NH_Test_Shared_Trail`, `seo-schema-selftest.spec.mjs`, job `seo-plugins` |
| Trong lúc phát triển | 20+ | Chỉnh bản nháp làm vô hiệu cache mảnh; thẻ lặp `id`; ảnh LCP không được chọn; `href="alert(1)"` còn sót sau lọc `javascript:`; ngày "cập nhật" ở tương lai; xếp hạng hub bỏ qua bài ở danh mục con; `mb_strtoupper()` gọi không kiểm tra; ID lỏng lẻo và khóa không vô hướng trong plugin; khối FAQ trong khối dùng lại không được phát hiện… | Kiểm thử hồi quy nằm trong cùng commit sửa lỗi |

## 5. Chưa được kiểm tra, hoặc chỉ kiểm tra một phần

Không mục nào dưới đây bị che giấu hay coi là "đã ổn": đó là những gì kiểm thử **không** chứng minh.

1. **Website thật.** Theme và plugin chưa được cài hoặc kích hoạt trên `blog.rentbikehanoi.com` và chưa chạm vào hosting; mọi kiểm thử chạy trên site dựng sẵn (dữ liệu giả `example.org`, 22 danh mục, 18 trang, `/%postname%/` giống cấu trúc của site thật). Đối chiếu SEO với baseline 525 trang (`wp-aiwriter`) chỉ làm được trên staging: dùng [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md).
2. **Deployer for Git.** Chưa xác minh cấu hình trên WordPress thật nên **không** khẳng định tự đồng bộ hoạt động; xem [DEPLOYMENT.md](DEPLOYMENT.md) (điều đã biết và chưa biết, cách cài bằng ZIP, cách quay lại).
3. **Cấu hình Yoast của website.** CI cài Yoast SEO **28.7** thật (đúng phiên bản của website) từ wordpress.org và so sánh head, JSON-LD và breadcrumb với theme và plugin đồng hành; nhưng đó là Yoast với cấu hình mặc định trên site mẫu. Cách website thật đã cấu hình Yoast (kiểu bài, breadcrumb, schema, taxonomy trong breadcrumb) chỉ kiểm được trên staging.
4. **Core Web Vitals thực tế.** Không có dữ liệu thực địa; số đo trong [PERFORMANCE.md](PERFORMANCE.md) là số đo phòng thí nghiệm trên máy phát triển, không phải hosting thật; không chạy Lighthouse hay đo INP.
5. **Trình duyệt và trợ năng.** Chỉ Chromium (ba viewport 375/768/1440); chưa thử Firefox, Safari, thiết bị thật hay trình đọc màn hình. axe chỉ bắt được một phần các lỗi trợ năng; kiểm tra bằng tay bằng bàn phím và trình đọc màn hình là việc của staging.
6. **Phiên bản môi trường.** WordPress: bộ đầy đủ trên 7.1.3, smoke trên 6.3.5 và 6.5.5 (các bản trung gian không chạy); PHP: PHPUnit trên 7.4 và 8.3 cộng kiểm tra cú pháp 7.4–8.4; MySQL 8.0 (CI) và MariaDB (máy phát triển), **chưa** MySQL 5.7. Bộ nhớ đệm và plugin thật (LiteSpeed Cache, WP Rocket…) chỉ được thử qua các hook chung, không chạy plugin thật.
7. **Quy mô.** Đo ở 1.000, 10.000 và 50.000 bài; chưa đo 100.000 bài hay nhiều tác giả đồng thời.
8. **AI Writer.** Luồng đăng REST được mô phỏng bằng cách gọi chính các điểm cuối REST với dữ liệu như workflow gửi; workflow `wp-aiwriter` thật chưa được chạy và repository đó không bị sửa.
9. **Bài đặt mật khẩu.** WordPress liệt kê chúng trong danh sách với tiền tố "Protected:"; theme không ẩn chúng (đánh đổi hiệu năng, quyết định của chủ sở hữu, xem [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md) mục B.7).
10. **Theme Glass.** Theme mới không phải bản sao của Glass: shortcode, block hoặc template riêng của Glass có thể hiển thị khác. Cần xem từng trang trong 18 trang tĩnh trước khi kích hoạt.
