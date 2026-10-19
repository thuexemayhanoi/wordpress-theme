# Nhật ký thay đổi

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/); phiên bản theo [SemVer](https://semver.org/lang/vi/). Mỗi mục dưới đây có commit và kiểm thử tương ứng trong lịch sử của nhánh `feature/premium-seo-theme`.

## [2.0.1] — Sửa nhỏ giao diện mobile (17/10/2026)

- Bài không có ảnh đại diện hiển thị nhãn danh mục thực thay vì biểu tượng sách mặc định; không tạo ảnh giả và không thay đổi ảnh WordPress. Nhãn tự xuống dòng khi không gian hẹp hoặc người dùng tăng khoảng cách chữ để tránh cắt chữ ở tablet.
- Nút nổi "Hỏi nhanh" của Local Site Assistant Lite được thu gọn còn 44 × 44 px và đưa sát mép trái trên màn hình điện thoại để giảm che nội dung. Plugin/chatbot giữ nguyên.
- Không chỉnh sửa menu WordPress hoặc dữ liệu SEO từ theme. Liên kết "Trang chủ" của menu #26 đang được WordPress quản lý và phải cập nhật tại nguồn.

## [Chưa phát hành] — plugin `nguyen-ha-vi-en` 0.5.1 (nhánh `feature/vi-en-final-20261019`)

Hợp nhất hai nhánh 0.5.1 cạnh tranh nhau (#9 và #10) thành một thiết kế duy nhất, cộng sửa hiệu năng và sửa các lỗi do hai lần rà soát độc lập tìm ra. Vẫn **tắt cho đến khi quản trị viên bật**; không sửa bài tiếng Việt; chưa kích hoạt trên website thật.

- Công tắc trong WordPress: *Công cụ → VI/EN Links → Switch English routing on/off*, chỉ quản trị viên và có nonce, **không phải sửa `wp-config.php`**. Bật lưu quy tắc rewrite trong cùng bước và hoàn tác nếu WordPress không lưu được; tắt chỉ rút quy tắc của plugin (không flush, quy tắc của plugin khác giữ nguyên) và luôn đóng được. Gỡ kích hoạt quên công tắc.
- `wp-config.php` vẫn là cơ quan quyết định: hằng số `NH_VIEN_ENABLE_EN_ROUTES` (khi đã khai báo) quyết định một mình và là **phanh khẩn cấp** (`false` tắt ngay, bất kể nút); `NH_VIEN_ALLOW_PRODUCTION` khác `true` cấm nút bật trên production.
- An toàn production: quyết định gắn với **địa chỉ website** (chép cơ sở dữ liệu staging lên địa chỉ khác không bật được gì) và, trên production, đòi tick xác nhận đã sao lưu và đã kiểm tra staging. Từ chối khi đường dẫn tĩnh là "Plain" hoặc bắt đầu bằng `/index.php/`, khi một bài đã xuất bản có slug `en`, hoặc khi có danh mục slug `en` mà cấu trúc đường dẫn có `%category%`.
- Hiệu năng: 0.5.0 hỏi WordPress "trang nào chiếm địa chỉ dưới `/en/`" riêng cho từng bài tiếng Anh trên **mọi** trang (đo: 241–258 truy vấn mỗi trang so với 26–47 khi không có plugin, với 200 bài Anh); 0.5.1 hỏi một lần cho cả yêu cầu. Danh sách slug của các bài tiếng Anh công khai chỉ lấy **hai cột** bằng một truy vấn có cache theo lần đổi bài cuối (trước đó nạp cả nội dung từng bài: +4,9 MB bộ nhớ mỗi trang với 200 bài dài 20 KB), và thẻ/danh mục của các bài đó được đọc một lần, chỉ tra cha cho danh mục.

**Sửa sau khi rà soát độc lập (mỗi mục có kiểm thử; các kiểm thử mới trên site thật đã được chạy thử trên bản trước khi sửa và thất bại đúng chỗ):**

- *Dữ liệu và quy tắc rewrite:* bật đặt tám quy tắc lên đầu dữ liệu đã lưu mà **không tạo lại** các quy tắc khác (một quy tắc plugin khác chỉ đăng ký trong một số yêu cầu không còn bị mất); tắt và gỡ kích hoạt chỉ rút đúng quy tắc của plugin (cùng biểu thức **và** cùng truy vấn) và báo có còn sót không; tắt kiểm tra công tắc đã được lưu trước khi động tới quy tắc; bộ canh gác khi cổng đóng chỉ cần còn **một** quy tắc của plugin; bộ quy tắc bị cắt bớt trả 404 thay vì danh sách tiếng Việt.
- *Cặp bài:* phía tiếng Anh của một cặp mới phải còn là nháp hoặc chờ duyệt (một bài tiếng Việt đang chạy không thể thành tiếng Anh vì nhập nhầm ID); phía tiếng Anh của cặp không đăng ký thêm làm bài độc lập; không gỡ được liên kết nếu việc đó làm mồ côi một bài; ID lưu trong tùy chọn được đọc nghiêm ngặt.
- *Menu tiếng Anh:* khi chưa có `primary-en`, WordPress chọn "menu đầu tiên có mục" nên **menu tiếng Việt lọt vào mọi trang tiếng Anh**; plugin giờ trả lời trước (`pre_wp_nav_menu`) bằng menu nhỏ chỉ tiếng Anh, chỉ liệt kê danh mục gốc đã có bài tiếng Anh.
- *Danh sách:* danh sách tự nêu ID bài (`post__in`) giữ đúng ngôn ngữ và `post__not_in` của người gọi vẫn được tôn trọng (WordPress bỏ qua `post__not_in` khi có `post__in`); trang `en` và các trang con thuộc tiếng Anh trong tìm kiếm; `post__in = [0]` ("không có gì") không biến thành "mọi bài tiếng Anh"; truy vấn thẻ/danh mục tiếng Anh không tự lọc mất kết quả của chính nó (đã tìm ra nhờ chạy trên WordPress thật).
- *Địa chỉ:* địa chỉ cũ của bài tiếng Anh đã đổi slug được chuyển 301; trang sâu hơn một cấp dưới `/en/` (`/en/about/team/`) và mọi địa chỉ khác dưới `/en/` được trả lời bằng tiếng Anh (trước đó dùng menu và cache của tiếng Việt); `?p=`, `?page_id=`, `?author=`, `?feed=` trên `/en/` không còn biến danh sách thành trang khác (và `/en/?feed=rss2` không còn trả feed); trang tiếng Anh không quảng bá feed (liên kết feed bình luận của bài tiếng Anh dẫn tới 404).
- *Cache:* plugin đồng hành xóa cache trang của **bản kia của cặp**, trang chủ `/en/` và lưu trữ tiếng Anh khi một bài đổi.
- *Khác:* tránh lỗi nghiêm trọng khi lọc thẻ cho sơ đồ Yoast trước khi có truy vấn chính.
- Kiểm thử: 10 script offline (`smoke`, `links`, `routes`, `gate`, `gate-config`, `boot`, `query`, `content`, `cache`, `seo`) có kiểm tra đột biến bằng tay (sửa cố ý từng đoạn mã quan trọng, test phải thất bại); bộ site thật qua HTTP gồm `main` 182, `homepage` 10, `inert` 20, `deactivated` 10 và `dashboard` **188** kiểm tra (đã thêm khối 9 cho các sửa lỗi trên). Tài liệu: [docs/VI-EN-PLUGIN.md](docs/VI-EN-PLUGIN.md), `plugins/nguyen-ha-vi-en/readme.txt`.

## [Chưa phát hành] — plugin `nguyen-ha-vi-en` 0.5.0 (đã vào `main`)

- Bài tiếng Anh độc lập (không cần bản tiếng Việt; đăng ký khi còn nháp), cây danh mục tiếng Anh rỗng đăng ký trước, ghép trang tiếng Việt với trang tiếng Anh tương đương dưới `/en/`.
- Theme: mẫu trang chủ tiếng Anh kiểu biên tập (`page-templates/template-english-home.php`), chân trang tiếng Anh với bốn vùng widget `footer-en-1…4`, menu `primary-en`/`footer-en`, cache mảnh tách theo ngôn ngữ. Hướng dẫn thiết lập an toàn (staging trước): [docs/EN-HOME-SETUP.md](docs/EN-HOME-SETUP.md); hiện trạng nháp trong WordPress: [docs/EN-STAGE3-WORDPRESS-INVENTORY.md](docs/EN-STAGE3-WORDPRESS-INVENTORY.md).

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
