# Nhật ký thay đổi

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/); phiên bản theo [SemVer](https://semver.org/lang/vi/). Mỗi mục dưới đây có commit và kiểm thử tương ứng trong lịch sử của nhánh `feature/premium-seo-theme`.

## [Chưa phát hành] — plugin `nguyen-ha-vi-en` 0.5.1: công tắc trong WordPress Admin (20/10/2026)

- Công tắc bật/tắt phần tiếng Anh chuyển vào *Công cụ → VI/EN Links* (quản trị viên, nonce), lưu bằng Options API (`nh_vien_routes_enabled_v1`, autoload): không cần `NH_VIEN_ENABLE_EN_ROUTES`/`NH_VIEN_ALLOW_PRODUCTION` trong `wp-config.php` nữa. Cài đặt đã lưu thắng hằng số; hằng số của 0.4–0.5 vẫn hoạt động khi chưa lưu; `NH_VIEN_ENABLE_EN_ROUTES === false` là công tắc khẩn cấp.
- Bật: preflight (permalink, bài ở `/en/`, trang ở địa chỉ dành riêng dưới `/en/`, rewrite rule/post type/taxonomy lạ chiếm `/en/`, trang chủ tiếng Anh, môi trường, plugin SEO), đăng ký và lưu quy tắc rewrite đúng một lần, smoke test tự gọi `/en/` (header `X-NH-VIEN-Lang: en`), tự quay lại trạng thái trước nếu không lưu được quy tắc hoặc `/en/` không trả lời bằng tiếng Anh; production cần ô xác nhận. Tắt: chỉ rút quy tắc của plugin khỏi quy tắc đã lưu. Nút *Undo the last switch*.
- REST API `/wp-json/nh-vien/v1/` (quản trị viên, application password): trạng thái, công tắc, undo, đăng ký danh mục/bài tiếng Anh, cặp bài và cặp trang — để pipeline phát hành làm được việc của màn hình Công cụ với cùng kiểm tra.
- Bài xuất bản chỉ trong danh mục tiếng Anh đã đăng ký được tự đăng ký là bài tiếng Anh (`wp_after_insert_post`), không bao giờ lọt vào danh sách tiếng Việt.
- Kiểm thử offline mới `tests/settings.php`, `tests/rest.php`; mở rộng `routes.php`, `boot.php`, `links.php`. Theme không đổi.

## [2.0.1] — Sửa nhỏ giao diện mobile (17/10/2026)

- Bài không có ảnh đại diện hiển thị nhãn danh mục thực thay vì biểu tượng sách mặc định; không tạo ảnh giả và không thay đổi ảnh WordPress. Nhãn tự xuống dòng khi không gian hẹp hoặc người dùng tăng khoảng cách chữ để tránh cắt chữ ở tablet.
- Nút nổi "Hỏi nhanh" của Local Site Assistant Lite được thu gọn còn 44 × 44 px và đưa sát mép trái trên màn hình điện thoại để giảm che nội dung. Plugin/chatbot giữ nguyên.
- Không chỉnh sửa menu WordPress hoặc dữ liệu SEO từ theme. Liên kết "Trang chủ" của menu #26 đang được WordPress quản lý và phải cập nhật tại nguồn.

## [Chưa phát hành] — plugin `nguyen-ha-vi-en` 0.4.0 và hai dòng trong theme (nhánh `feature/vi-en-complete-20261019`)

- Plugin mới `plugins/nguyen-ha-vi-en` (ZIP riêng): phần tiếng Anh dưới `/en/` cho blog, bài ghép cặp bằng tay, mỗi danh sách một ngôn ngữ, bộ chuyển ngôn ngữ, hreflang, canonical và sơ đồ trang tương thích Yoast SEO. **Tắt cho đến khi chủ sở hữu bật công tắc**; không sửa bài tiếng Việt. Xem [docs/VI-EN-PLUGIN.md](docs/VI-EN-PLUGIN.md).
- Theme: `header.php` gọi thêm `do_action( 'nh_editorial_after_skip_link' )` ngay sau liên kết "Skip to content", và `inc/setup.php` khai báo `add_theme_support( 'nguyen-ha-vi-en-switcher' )`, để bộ chuyển ngôn ngữ của plugin nằm **sau** liên kết bỏ qua. Không có plugin thì không in gì và không đổi HTML nào.

## [Chưa phát hành] — theme 2.0.0 và plugin 1.0.0 (nhánh `feature/premium-seo-theme`)

Đây là bản viết lại hoàn toàn so với theme 1.0.0 trên `main`. Tên thư mục, tên theme và text domain giữ nguyên (`nguyen-ha-editorial`). Chưa kích hoạt trên website thật.

### Thêm — theme

- Kiến trúc module: `functions.php` chỉ nạp 20 module trong `inc/` (cấu hình, màu, biểu tượng, dữ liệu biên tập, cache, thiết lập, Customizer, tài nguyên, hiệu năng, chủ đề/silo, bài liên quan, breadcrumb, mục lục, tác giả, điều hướng, tìm kiếm, thẻ mẫu…) và `template-parts/` dùng chung: sửa một nơi, mọi trang đồng bộ.
- Trang: trang chủ kiểu tạp chí (chế độ "bài mới nhất" và "trang tĩnh"), hub/danh mục cha–con, mẫu trang Pillar và trang rộng, bài viết, trang tĩnh, tìm kiếm, tác giả, lưu trữ, đính kèm, 404, phân trang.
- Điều hướng: header cố định, mega menu cha–con truy cập bằng bàn phím, menu di động, menu dự phòng dựng từ cây danh mục, breadcrumb luôn trùng `BreadcrumbList` mà công cụ tìm kiếm đọc (theo danh sách của Yoast khi có Yoast; nếu không thì theo danh mục chính).
- Bài viết: mục lục H2/H3 dựng phía máy chủ, thời gian đọc, tóm tắt/ý chính/FAQ/nguồn (chỉ khi có dữ liệu thật), hộp tác giả, bài liên quan theo tầng (cluster → danh mục → danh mục cha), bài trước/sau trong cùng silo, hộp chủ đề/pillar, CTA do chủ sở hữu cấu hình.
- Tìm kiếm có tô sáng từ khóa và gợi ý khi gõ qua REST có sẵn của lõi (không dịch vụ ngoài); gợi ý tự tắt trên site rất lớn nếu chưa có chỉ mục.
- Giao diện sáng/tối, màu nhấn tùy chỉnh được kiểm tra độ tương phản WCAG AA, `prefers-reduced-motion`, `forced-colors`, bản in, `screenshot.png`.
- Customizer: 6 nhóm (Brand, Layout, Article, Homepage, CTA, Footer) sinh từ một schema duy nhất.
- Trang chủ giữ H1 và đoạn dẫn đã được khóa: hai ô *Homepage headline* / *Homepage introduction* tái tạo đúng từng ký tự văn bản baseline; thông báo nhắc nhở có thể tắt cho đến khi nhập.
- Bản dịch tiếng Việt đầy đủ (160 chuỗi theme, 250 chuỗi plugin) và `.pot`.
- Hiệu năng: CSS tới hạn nội tuyến, CSS chính không chặn render, một JS `defer`, font hệ thống, không thư viện, một ảnh LCP mỗi trang, cache mảnh có phiên bản, đếm bài của tác giả không dùng `SQL_CALC_FOUND_ROWS`, không truy vấn quét toàn bảng bài viết.

### Thêm — plugin `nguyen-ha-seo-companion` 1.0.0 (mới)

- Dữ liệu biên tập sống sót khi đổi theme: tóm tắt, ý chính, FAQ, nguồn, ngày cập nhật thật, từ khóa, Pillar, Topic Cluster (taxonomy riêng, không công khai), thông tin tác giả; đăng ký REST có kiểm quyền và làm sạch.
- Hồ sơ doanh nghiệp do chủ sở hữu nhập: không có giá trị mặc định nào.
- Dữ liệu có cấu trúc: chế độ *auto/extras/full/off*; cạnh plugin SEO chỉ thêm khối riêng `FAQPage` (câu hỏi hiển thị) và `LocalBusiness` (hồ sơ đầy đủ + cờ bật), không bao giờ sửa đồ thị của Yoast/Rank Math, không có đánh giá/xếp hạng/giá.
- Chỉ mục tìm kiếm FULLTEXT (bỏ dấu tiếng Việt) với lập chỉ mục tăng dần, dựng chỉ mục hàng loạt từ trang Tools hoặc WP-CLI, sắp theo độ liên quan, có phương án dự phòng về tìm kiếm gốc.
- Làm mới cache trang một lần ở cuối request với tập URL đầy đủ (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, hook chung).
- WP-CLI: `wp nh-seo reindex`, `index-status`, `purge`.
- Gỡ plugin chỉ xóa dữ liệu của chính nó (không bao giờ xóa meta, term hay nội dung).

### Thêm — công cụ, kiểm thử, CI, tài liệu

- Bộ kiểm thử PHPUnit cho theme và plugin (đơn site và multisite; hai cấu trúc permalink; chạy cả trên PHP 7.4); bộ kiểm thử trình duyệt Playwright + axe (Chromium, ba viewport); so sánh với Yoast SEO và Rank Math thật trong CI; kiểm tra HTTP trên WordPress 6.3.5 và 6.5.5; kiểm tra quy tắc bảo vệ website (hiển thị trang không đổi nội dung, không gọi mạng ra ngoài, mọi file PHP dừng khi bị gọi trực tiếp, không bịa dữ liệu doanh nghiệp).
- GitHub Actions `Theme QA`: PHPCS/WPCS/PHPCompatibilityWP, cú pháp PHP 7.4–8.4, build asset, `.pot`, đóng gói và kiểm tra ZIP, PHPUnit (WordPress 7.1.3 và bản mới nhất làm tín hiệu, MySQL 8.0, multisite, PHP 7.4), smoke trên WordPress 6.3.5 và 6.5.5, trình duyệt (Playwright + axe), Yoast SEO / Rank Math thật, báo cáo QA và artifact phát hành chỉ khi mọi cổng đạt; không tạo tag, Release hay nhánh, không liên hệ website thật.
- Công cụ: `tools/package.sh`, `verify-package.sh`, `check-pot.sh`, `qa-report.py` (có test), `measure-scale.sh`, `staging-preflight.php` (báo cáo chỉ-đọc với dấu vân tay nội dung), `tests/bin/*` (dựng site thử, dữ liệu mẫu, đo, smoke).
- Tài liệu: README, ARCHITECTURE, SEO-RESPONSIBILITY, DATA-CONTRACT, COMPANION-PLUGIN, AI-WRITER-INTEGRATION, DEPLOYMENT, STAGING-CHECKLIST, ROLLBACK, TESTING, PERFORMANCE.

### Thay đổi so với theme 1.0.0

- `Requires at least` từ 6.0 lên 6.3; PHP tối thiểu vẫn 7.4.
- CSS không còn nằm trong `style.css` (chỉ còn phần khai báo); `template-parts/content-card.php` được thay bằng `template-parts/cards/*` và `template-parts/components/*`.
- `style.css` không còn khai báo `accessibility-ready` (chưa có kiểm tra của chương trình theme WordPress.org).

### Đã loại bỏ

- `assets/js/theme.js` (của 1.0.0): không còn nơi nào nạp nó, trước đây vẫn nằm trong gói cài đặt; một test chặn file thừa quay lại.

### Sửa — lỗi thật do kiểm thử và rà soát phát hiện

**Rà soát độc lập theme (8 phát hiện, đã sửa cả 8; mỗi phát hiện có test hồi quy từng thất bại trước khi sửa):**

- *Cao:* bài viết/trang đặt mật khẩu in tóm tắt, ý chính, FAQ, nguồn và thời gian đọc ngay cạnh ô nhập mật khẩu cho mọi khách.
- *Trung bình:* trang chủ chậm 5–6 giây ở lần tải đầu sau mỗi chỉnh sửa trên site 50.000 bài (mỗi lần lưu bài đã xuất bản làm trống cache các danh sách, và truy vấn `has_password` buộc CSDL đọc nguyên dòng bài viết); khách có thể ghi thêm hàng vào `wp_options` bằng `/?author=<số>`.
- *Thấp:* bộ bọc bảng (bảng lồng nhau hoặc rất lớn làm mất bài); trang đính kèm nêu tên bài cha đã vào thùng rác; số bài của tác giả không làm mới khi xóa người dùng; mảng lưu trong meta in chữ "Array".
- *Menu và tìm kiếm khi không có JavaScript:* các quy tắc không-JS thua `[hidden]{display:none!important}` nên mega menu và ô tìm kiếm không với tới được; tiêu đề cố định chiếm 62% màn hình (sửa cùng các phát hiện của bộ trình duyệt bên dưới).

**Rà soát độc lập plugin (8 phát hiện, đã sửa cả 8; test hồi quy, trong đó phần multisite chạy trên cài đặt mạng):**

- *Trung bình:* REST lộ mọi trường `nh_*` của bài đặt mật khẩu cho khách; `FAQPage` JSON-LD in cho FAQ không hiển thị (mẫu trang rộng và trang chủ tĩnh không in FAQ); chỉ mục tìm kiếm vẫn báo "Ready" khi đã lệch nội dung (plugin từng bị tắt, nhập liệu bỏ qua hook, WP-Cron không chạy).
- *Thấp:* tên cluster có dấu `\` bị áp dụng lại ở mỗi lần lưu; trên multisite, bài lưu khi đang `switch_to_blog()` được lập chỉ mục và xóa cache nhầm site; URL lưu trữ trong dữ liệu cấu trúc mang theo chuỗi truy vấn của khách (`?utm_source=…`); `nh_keywords` công khai qua REST; cluster chỉ do bản nháp dùng bị liệt kê công khai.

**Bộ kiểm thử trình duyệt (785 kiểm thử trên ba viewport; lần chạy đầu thất bại 156, quy về 13 lỗi thật, đã sửa; trừ một mục chủ sở hữu quyết định):**

- Tiêu đề widget ở chân trang vô hình ở chế độ sáng (tỷ lệ tương phản 1:1); liên kết thương hiệu có `aria-label` khác chữ hiển thị (WCAG 2.5.3, làm hỏng 92 kiểm thử axe); hai vùng `role="search"` cùng tên; `aria-labelledby` trỏ tới tiêu đề không tồn tại ở phần bình luận; chữ `<mark>` trong kết quả tìm kiếm ở chế độ tối dưới 4,5:1.
- Khi in: câu trả lời FAQ bị mất (Chromium ẩn `<details>` đóng qua `::details-content`) và chế độ tối in chữ gần như trắng trên giấy trắng.
- Ảnh đại diện tác giả bị bóp thành hình bầu dục; thẻ rút gọn tràn khung ở 320 px với chữ 200%; trạng thái gợi ý tìm kiếm đọc "1 suggestions available"; hộp Pillar ở đầu trang hub gây dịch chuyển bố cục nhỏ vì CSS của nó nằm ở tệp tải muộn (đã chuyển vào CSS tới hạn); menu xuống dòng và tên site bị cắt khi cửa sổ rộng 961–1100 px (điểm chuyển sang menu ngăn kéo nâng lên 1180 px).
- Phát hiện thêm khi chữa: mục lục và liên kết neo đáp xuống cách tiêu đề 99 px vì `scroll-padding` của `html` và `scroll-margin` của tiêu đề cộng dồn; điểm đánh dấu "mục hiện tại" của mục lục phụ thuộc vào dải theo phần trăm chiều cao màn hình nên chỉ đúng nhờ độ lệch kép đó (nay tính từ chính đường neo); tìm kiếm không có từ khóa (`/?s=`) liệt kê mọi bài dưới tiêu đề "Results for “”" (nay là lời nhắc và các chủ đề, truy vấn rẻ); ô nhập mật khẩu của bài được bảo vệ chưa có kiểu; widget chân trang xếp sát nhau.
- *Quyết định của chủ sở hữu, không đổi:* WordPress liệt kê bài đặt mật khẩu trong danh sách với tiền tố "Protected:". Ẩn chúng bằng `has_password` làm truy vấn danh mục chậm (khoảng 2 s so với 0,1 s ở 50.000 bài) nên theme giữ hành vi của lõi; xem [docs/STAGING-CHECKLIST.md](docs/STAGING-CHECKLIST.md) mục B.7.

**Job `seo-plugins` của CI, Yoast SEO 28.7 thật cài từ wordpress.org (lần chạy đầu: 3 kiểm thử đỏ ở mỗi nhánh):**

- Breadcrumb hiển thị khác `BreadcrumbList` của Yoast: với bài viết, Yoast mặc định không liệt kê danh mục (`Trang chủ › Bài viết`, hai mục) trong khi theme hiện `Trang chủ › Danh mục › Danh mục con › Bài viết` (bốn mục); với trang tìm kiếm Yoast ghi "You searched for …", theme ghi "Search results for: …". Theme khai báo `yoast-seo-breadcrumbs`, tức hứa hiển thị breadcrumb của Yoast, nhưng lại hiển thị của riêng nó. Nay theme đọc danh sách từ bề mặt tài liệu hóa của Yoast (`YoastSEO()->meta->for_current_page()->breadcrumbs`), bỏ các crumb mà Yoast giấu khỏi dữ liệu có cấu trúc, và giữ trail của riêng mình khi plugin đồng hành ở chế độ *full*, khi không có plugin SEO, hoặc khi bộ lọc `nh_editorial_breadcrumbs_follow_seo_plugin` trả về `false`. Bộ đọc làm việc với getter ma thuật không có `__isset()` (một test như vậy thất bại với bản đầu dùng `isset()`).
- Không có plugin SEO, `BreadcrumbList` của plugin đồng hành nêu `/search/x/` cho trang đang ở `/?s=x`: crumb tìm kiếm của theme và plugin nay là địa chỉ khách đã tới (thuật toán đã dùng cho trang lưu trữ: trang đầu, không tham số theo dõi).
- Crumb mà plugin SEO giao cho theme có thể thiếu địa chỉ ở một mục giữa: mẫu in `<a href="">`; nay in tên dưới dạng chữ.
- Rank Math 1.0.280 (cùng job): không in canonical, mô tả hay JSON-LD nào trên site mẫu. Đó **không** phải lỗi của theme: Rank Math không nạp phần giao diện trước cho đến khi chủ sở hữu kết nối tài khoản hoặc chọn bỏ qua bước đăng ký của trình thiết lập (probe trong CI chỉ ra `\RankMath\Admin\Registration` ở trạng thái `invalid`). Job nay chọn bỏ qua bước đó (`rank_math_registration_skip`) và cả năm trang đạt; điều này được ghi trong [docs/SEO-RESPONSIBILITY.md](docs/SEO-RESPONSIBILITY.md) cho chủ sở hữu nếu từng chuyển sang Rank Math.

**Lỗi tìm thấy trong quá trình phát triển:**

- Chỉnh sửa bản nháp làm vô hiệu hóa cache mảnh (sự kiện gán term chạy ở mỗi lần lưu): cache chỉ vô hiệu khi bài đã xuất bản hoặc tập term đổi thật.
- Thẻ bài lặp thuộc tính `id`; bài lặp lại giữa các khối ở trang chủ; ảnh LCP không được chọn khi thẻ đầu tiên không có ảnh; avatar không lazy; liên kết tác giả rỗng khi tác giả chưa có tên hiển thị.
- Câu trả lời FAQ còn sót `href="alert(1)"` sau khi lọc `javascript:`; ngày "cập nhật" được phép ở tương lai (theme và plugin).
- Xếp hạng hub bỏ qua bài ở danh mục con khi danh mục cha không có bài trực tiếp (đúng cấu trúc của blog thật).
- Bộ lọc bọc bảng chạy cả trong feed.
- `mb_strtoupper()` được gọi không kiểm tra trên mọi trang (host thiếu mbstring sẽ lỗi nghiêm trọng toàn site).
- Plugin: nhận ID lỏng lẻo, khóa không vô hướng, khối FAQ nằm trong khối dùng lại không được phát hiện (có thể in `FAQPage` thứ hai).
- Bài kiểm thử so khớp `SHOW CREATE TABLE` viết theo cách in của MariaDB làm job MySQL 8.0 thất bại; kỳ vọng nay chấp nhận cả hai cách in (bảng vẫn đúng).

### Giới hạn đã biết

Xem [docs/TESTING.md](docs/TESTING.md) mục 5 và [docs/PERFORMANCE.md](docs/PERFORMANCE.md): chưa kiểm tra với website thật, với cấu hình Deployer for Git thật và với *cấu hình* Yoast của website (CI chạy Yoast SEO 28.7 và Rank Math 1.0.280 thật, cấu hình mặc định, trên site mẫu); chưa có dữ liệu Core Web Vitals thực tế; chưa chạy trên MySQL 5.7; trình duyệt chỉ Chromium; chưa thử với trình đọc màn hình thật.

## [1.0.0] — theme ban đầu (`main`)

Theme cổ điển nhẹ cho blog du lịch/xe máy: thẻ bài ba cột, hero, menu di động, chế độ màu sáng/tối, bài liên quan đơn giản; CSS trong `style.css`; một JavaScript.
