# Ai chịu trách nhiệm phần SEO nào

Nguyên tắc: **mỗi đầu ra SEO có đúng một chủ sở hữu**. Trên website thật, chủ sở hữu là Yoast SEO. Theme không in gì thuộc SEO; plugin đồng hành chỉ thêm những gì plugin SEO không có, và chỉ khi đủ điều kiện. Mục tiêu là không bao giờ in trùng (hai canonical, hai đồ thị JSON-LD, hai `Organization`) và không bao giờ đổi thứ đang chạy.

## 1. Bảng phân công

| Đầu ra | Khi có plugin SEO (Yoast/Rank Math/…) | Khi không có plugin SEO | Theme in? |
|---|---|---|---|
| `<title>` | Plugin SEO | Lõi WordPress (theme khai báo `title-tag`) | Không |
| Meta description | Plugin SEO | Không có | Không |
| `rel="canonical"` | Plugin SEO | Lõi WordPress (trang đơn) | Không |
| `robots` (index/noindex, `max-image-preview`…) | Plugin SEO và lõi (`wp_robots`) | Lõi | Không, không bao giờ `noindex` |
| Open Graph, Twitter Card | Plugin SEO | Không có | Không |
| Sitemap, `robots.txt` | Plugin SEO / lõi | Lõi | Không |
| JSON-LD: đồ thị (`Organization`, `WebSite`, `WebPage`, `Article`, `Person`, `BreadcrumbList`, `ImageObject`, `SearchAction`) | Plugin SEO (một đồ thị) | Plugin đồng hành, chế độ *full* (một đồ thị tối thiểu) | Không |
| JSON-LD: `FAQPage` | Plugin đồng hành, khối **riêng**, chỉ khi bài có ≥ 2 câu hỏi **hiển thị trên trang** và không có khối FAQ của Yoast/Rank Math trong nội dung | Gộp vào đồ thị *full* với cùng điều kiện | Không |
| JSON-LD: `LocalBusiness` | Chỉ khi hồ sơ doanh nghiệp **đầy đủ, do chủ sở hữu nhập**, bài/trang trang chủ được bật, và không có plugin Local SEO | Như bên trái | Không |
| Breadcrumb hiển thị | Theme (HTML có `aria-label`, không microdata) | Theme | Có (phần nhìn thấy) |
| `BreadcrumbList` JSON-LD | Yoast (theme khai báo `yoast-seo-breadcrumbs` để Yoast giữ nó) | Đồ thị *full* của plugin đồng hành | Không |
| Đánh giá, xếp hạng, giá, ưu đãi | Không ai | Không ai | Không (có test quét mã nguồn) |

Dữ liệu doanh nghiệp (tên, địa chỉ, điện thoại, giờ mở cửa) **không có giá trị mặc định** ở bất kỳ đâu: chỉ chủ sở hữu mới nhập được (Settings → Nguyen Ha SEO Companion). Không có dữ liệu hoặc hồ sơ chưa đầy đủ thì không có `LocalBusiness`.

## 2. Plugin đồng hành chọn chế độ như thế nào

`Settings → Nguyen Ha SEO Companion → Structured data`:

| Chế độ | Ý nghĩa |
|---|---|
| **auto** (mặc định) | `extras` khi phát hiện plugin SEO, ngược lại `full` |
| **extras** | Chỉ in các khối riêng `FAQPage` và `LocalBusiness` (đủ điều kiện ở bảng trên); không bao giờ sửa đồ thị của plugin SEO |
| **full** | Một `@graph` duy nhất cho site không có plugin SEO |
| **off** | Không in gì |

Phát hiện plugin SEO dùng hằng số/lớp đã nạp (không gọi vào mã của plugin khác): Yoast SEO (`WPSEO_VERSION`), Rank Math (`RANK_MATH_VERSION`/`RankMath`), All in One SEO, SEOPress, The SEO Framework, SmartCrawl, Slim SEO, Squirrly. Bộ lọc `nh_seo_detected_seo_plugin` và `nh_seo_schema_mode` cho phép ghi đè.

**Giữ chế độ *auto* khi có plugin SEO.** Ép chế độ *full* trong lúc Yoast hoạt động sẽ in một đồ thị thứ hai (trùng `Organization`, `WebSite`…): đó là hành vi có chủ ý của lựa chọn thủ công, không phải lỗi.

Không in gì cho 404, bản xem trước, bản nháp, nội dung riêng tư hoặc có mật khẩu; trong chế độ *extras* cũng không in cho trang kết quả tìm kiếm. JSON được mã hóa với `JSON_HEX_TAG`/`JSON_HEX_AMP` nên không giá trị nào đóng được thẻ `script`.

## 3. Điều theme bảo đảm (và test nào chứng minh)

| Cam kết | Test |
|---|---|
| Mã nguồn theme không chứa `application/ld+json`, `rel="canonical"`, `name="description"`, `og:`, `twitter:`, `robots`, `hreflang` | `NH_Test_Theme_Source::test_the_theme_never_prints_seo_owned_markup` |
| Trang thực tế không có meta description, Open Graph, JSON-LD do theme in; tối đa một canonical (của lõi) | `NH_Test_Homepage_Lock`, `NH_Test_Template_Structure` |
| Không có `noindex` mới, không đổi URL/slug/ID/cha của danh mục, không ghi tùy chọn site | `NH_Test_No_Content_Mutation` (so sánh trước/sau khi hiển thị 16 loại trang) |
| Đúng một H1 mỗi trang, không nhảy cấp tiêu đề, breadcrumb khớp `nh_seo_breadcrumb_trail()` của plugin | `NH_Test_Template_Structure`, `NH_Test_Breadcrumb_Parity` |
| Plugin và theme không in trùng đồ thị khi có Yoast; FAQPage chỉ khi FAQ hiển thị | `test-schema-*` (plugin), `NH_Test_Plugin_Integration` |
| Bài đăng qua REST (AI Writer) không đổi `content.rendered` và đúng danh mục | `NH_Test_Publish_Flow` |

## 4. Kiểm tra trên staging (xem [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md), mục C)

1. Xem mã nguồn trang của một bài, một danh mục và trang chủ: đúng **một** `<link rel="canonical">`, đúng **một** khối JSON-LD của Yoast, không khối nào do theme in; chỉ bài có FAQ hiển thị mới có thêm một khối `FAQPage`.
2. Các kiểu JSON-LD so với baseline của repository `wp-aiwriter` (`Article, BreadcrumbList, ImageObject, Organization, Person, WebPage, WebSite`, `SearchAction`): không kiểu nào biến mất; không `Organization`/`WebSite`/`BreadcrumbList` thứ hai.
3. `robots` meta như trước; sitemap và `robots.txt` không đổi.
4. Chạy `seo_protection.py --mode check` của repository `wp-aiwriter`; xem mục 4 của [AI-WRITER-INTEGRATION.md](AI-WRITER-INTEGRATION.md) cho những cảnh báo có thể gặp lần đầu.

## 5. Giới hạn

- Bộ kiểm thử PHPUnit mô phỏng plugin SEO bằng hằng số/lớp giả để kiểm tra logic phát hiện; việc đối chiếu với **Yoast SEO và Rank Math thật** do job `seo-plugins` của GitHub Actions thực hiện (cài từ wordpress.org trên runner) — xem [TESTING.md](TESTING.md) để biết kết quả đã chạy. Môi trường phát triển này không truy cập được wordpress.org.
- Phiên bản Yoast SEO của website thật (28.7) và cấu hình của nó chưa được đối chiếu; cần staging.
- Đã kiểm tra thủ công qua HTTP trên site thử (WordPress 6.5.5) bằng cách định nghĩa hằng số `WPSEO_VERSION` trong một mu-plugin tạm: chế độ tự chuyển sang *extras*, trang chủ, bài thường, trang tĩnh, danh mục và tìm kiếm **không** in JSON-LD nào, chỉ bài có FAQ hiển thị in thêm một khối `FAQPage`; bỏ hằng số thì trở lại đồ thị đầy đủ (*full*). Đó là mô phỏng hằng số, không phải Yoast thật.
- Không phát hiện được khối FAQ nằm trong template part hoặc shortcode; không nhận biết plugin SEO lạ nếu không có bộ lọc `nh_seo_detected_seo_plugin`; cấu trúc tùy chọn module của Rank Math Local SEO và hằng số của Yoast Local SEO lấy theo hiểu biết, chưa chạy với plugin thật.
