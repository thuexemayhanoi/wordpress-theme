# Kiến trúc Nguyen Ha Editorial v2

Tài liệu này mô tả cách theme và plugin đồng hành được chia việc, dữ liệu đi qua đâu, và vì sao mỗi quyết định thiết kế được đưa ra. Mọi con số trong tài liệu đến từ các lần đo/kiểm thử đã chạy thật (xem [TESTING.md](TESTING.md) và [PERFORMANCE.md](PERFORMANCE.md)).

## 1. Ba thành phần, ba trách nhiệm

| Thành phần | Sở hữu | Không bao giờ làm |
|---|---|---|
| **Theme** `nguyen-ha-editorial` | Trình bày: bố cục, menu, trang chủ, hub/archive, bài viết, tìm kiếm, ảnh, CSS/JS, dark mode, a11y | In `<title>` tùy biến, meta description, canonical, robots, Open Graph, JSON-LD, hreflang |
| **Plugin SEO của site** (Yoast SEO trên production) | Title, meta, canonical, robots, OG/Twitter, sitemap, đồ thị JSON-LD | — |
| **Plugin đồng hành** `nguyen-ha-seo-companion` | Dữ liệu cần sống sót khi đổi theme: tóm tắt/FAQ/nguồn, Topic Cluster, hồ sơ doanh nghiệp *do chủ sở hữu nhập*, phần mở rộng schema, chỉ mục tìm kiếm, làm mới cache trang | Tự bịa dữ liệu, tự noindex, in trùng đồ thị schema với plugin SEO |

Ranh giới chi tiết và cách tránh trùng lặp: [SEO-RESPONSIBILITY.md](SEO-RESPONSIBILITY.md). Hợp đồng dữ liệu giữa hai thành phần: [DATA-CONTRACT.md](DATA-CONTRACT.md).

Theme chạy độc lập: không có plugin đồng hành thì mọi thứ vẫn hiển thị, chỉ thiếu phần dữ liệu biên tập (không có gì bị bịa ra để thế chỗ).

## 2. Cấu trúc thư mục

```
style.css, functions.php, theme.json    header theme, bootstrap, cấu hình block editor (tối thiểu)
header.php, footer.php, searchform.php  khung trang dùng chung
front-page.php, home.php, index.php     trang chủ (chế độ "bài mới nhất" và "trang tĩnh")
category.php, archive.php, author.php   hub / lưu trữ
single.php, page.php, attachment.php    bài viết, trang, tệp đính kèm
search.php, 404.php                     tìm kiếm, không tìm thấy
page-templates/                         template-pillar.php, template-fullwidth.php
template-parts/{cards,components,home}  thành phần dùng chung (sửa một nơi, đồng bộ mọi trang)
inc/                                    20 module, mỗi module một trách nhiệm
assets/src/{css,js}                     mã nguồn; assets/css|js là bản build đã commit (không cần build khi triển khai)
languages/                              .pot và vi.po/vi.mo
tools/                                  build, đóng gói, kiểm tra (không vào ZIP cài đặt)
tests/                                  PHPUnit, Playwright, công cụ đo (không vào ZIP cài đặt)
plugins/nguyen-ha-seo-companion/        mã nguồn plugin đồng hành (ZIP riêng)
```

### Module `inc/` (nạp theo thứ tự trong `functions.php`)

| Module | Việc làm |
|---|---|
| `config` | Một schema duy nhất cho mọi thiết lập trình bày (mặc định, bộ lọc dữ liệu, điều khiển Customizer). Getter `nh_editorial_option()`, `nh_editorial_business()` (luôn trả đủ khóa, rỗng nếu chưa có dữ liệu), `nh_editorial_cta()`, `nh_editorial_social_links()` |
| `color` | Tính màu nhấn đạt WCAG AA trên cả nền trắng lẫn nền chip, cho chế độ sáng và tối |
| `icons` | Sprite SVG nội tuyến, `nh_editorial_icon()` |
| `extras` | Đọc và **xác thực lại** summary/takeaways/FAQ/sources/ngày cập nhật từ post meta; escape khi in; không sinh nội dung |
| `seo-compat` | Không in gì. Danh mục chính (primary category) giống Yoast/Rank Math, tiêu đề thuần |
| `cache` | Cache mảnh có phiên bản (xem §5) |
| `setup` | `add_theme_support`, menu, widget, lọc excerpt, khai báo `yoast-seo-breadcrumbs` và `nguyen-ha-companion` |
| `customizer` | Sinh bảng điều khiển từ schema |
| `assets` | CSS tới hạn nội tuyến + CSS chính không chặn render, một JS `defer`, script chế độ màu chạy trước khi vẽ |
| `performance` | Thuộc tính ảnh theo ngữ cảnh, đúng một ảnh LCP mỗi trang, avatar luôn lazy, đếm tác giả không dùng `SQL_CALC_FOUND_ROWS`, tắt gợi ý tìm kiếm khi site lớn mà chưa có chỉ mục |
| `topics` | Động cơ topical authority: silo, pillar, hub, bài kế/trước trong silo, hub con, bài hỗ trợ |
| `related-posts` | Bài liên quan theo tầng: cluster → danh mục → danh mục cha → cấp cao nhất |
| `breadcrumbs` | Theo danh sách mà Yoast in thành `BreadcrumbList` (đọc từ bề mặt `meta` của Yoast); không có thì cùng một đường dẫn với `nh_seo_breadcrumb_trail()` của plugin (có test so sánh) |
| `toc` | Mục lục H2/H3 dựng phía máy chủ, id ổn định, không va chạm |
| `author` | Hồ sơ tác giả chỉ từ dữ liệu người dùng đã nhập |
| `navigation` | Mega menu truy cập được (nút mở/đóng + panel), menu dự phòng dựng từ cây danh mục |
| `search` | Truy vấn tìm kiếm, highlight, gợi ý qua REST `/wp/v2/search` có sẵn của WordPress |
| `template-tags` | Thẻ dùng chung: meta, phân trang, ảnh, bảng cuộn |
| `schema-presentation`, `admin-notices` | Giữ phần hiển thị khớp phần dữ liệu có cấu trúc; thông báo gợi ý plugin (có thể tắt) |

## 3. Luồng dữ liệu của một bài viết

```
publisher (REST)  ──► WordPress (post + category [+ meta nh_*])
                          │  shutdown: theme tăng phiên bản cache 1 lần
                          │            plugin làm mới cache trang + cập nhật chỉ mục tìm kiếm
                          ▼
single.php:  breadcrumb(primary category) → H1 → meta → ảnh → [tóm tắt, ý chính] → mục lục
             → nội dung → [FAQ] → [nguồn] → thẻ → CTA → hộp chủ đề/pillar → [hộp tác giả]
             → trước/sau trong silo → bài liên quan → bình luận
```
Các khối trong ngoặc vuông chỉ xuất hiện khi dữ liệu thật tồn tại. Publisher hiện tại không gửi meta, nên bài của nó hiển thị bản "bài thường" đầy đủ (breadcrumb, mục lục từ H2, bài liên quan, trước/sau).

## 4. Topical authority: Pillar–Hub–Silo

- **Hub** = danh mục cấp cao nhất. Số bài là số bài thật của cả cây bên dưới (đếm bằng một truy vấn COUNT nhẹ), nên hub cha không có bài trực tiếp (như *Dịch vụ*, *Khu vực* trên blog thật) vẫn được xếp và đếm đúng. Danh mục mặc định ("Uncategorized") không bao giờ là hub khi đã có hub thật.
- **Silo** của một bài = Topic Cluster (term) → alias `nh_topic_cluster` → danh mục chính. Danh mục chính = danh mục chính của Yoast/Rank Math nếu đã chọn và còn gắn, nếu không thì danh mục sâu nhất không phải mặc định.
- **Pillar** của một bài = `nh_pillar_id` của bài → của cluster → của danh mục (và tổ tiên). Chỉ liên kết tới trang đã xuất bản và công khai. Theme **không** tự đoán pillar theo slug: chủ sở hữu chọn trang pillar cho danh mục (trường "Pillar page" của plugin), nên không có liên kết nội bộ hàng loạt bất ngờ khi kích hoạt.
- **Bài liên quan**: cùng cluster trước, rồi cùng danh mục, rồi danh mục cha, rồi cấp cao nhất; chỉ bài đã xuất bản, không có mật khẩu; loại bài hiện tại; mới nhất trước.
- **Trước/sau**: trong cùng silo, theo ngày + ID để phân xử khi trùng ngày.
- Mọi truy vấn trên trả về **danh sách ID** được cache; không trang nào quét toàn bộ bảng bài viết.

## 5. Cache

`inc/cache.php`: transient khóa theo `md5( tên | phiên bản toàn cục )`. Phiên bản là một option autoload; mọi thay đổi cấu trúc (đăng/gỡ bài, đổi ngày, mật khẩu, slug, gán/sửa term, pillar/cluster meta, sticky, Customizer, đổi theme) yêu cầu tăng phiên bản **một lần ở `shutdown`**, nên hàng loạt thay đổi trong một request chỉ tốn một lần ghi. Chỉnh sửa bản nháp không làm vô hiệu hóa cache, và lưu lại một bài đã xuất bản chỉ làm mất hiệu lực khi ngày, mật khẩu, slug, tác giả, bài cha hay thứ tự thật sự đổi (trước đây mỗi lần lưu làm trống mọi danh sách: 5–6 s cho yêu cầu kế tiếp ở 50.000 bài). Chỉ lưu ID và cấu trúc term nhỏ, không lưu HTML, nên tiêu đề/ảnh/nội dung luôn là trạng thái hiện tại. Có object cache thì tự dùng; không có thì chạy trên transient của hosting chia sẻ.

Một bài đăng qua REST (như publisher) làm cache được làm mới ở cuối chính request đó, trước khi khách nào kịp xem: test `test-publish-flow.php` kiểm tra hub, category, trang chủ, bài kế, bài liên quan và tìm kiếm.

## 6. Hiệu năng

- CSS tới hạn (≈24 KB min, 5,8 KB gzip) nội tuyến; CSS chính nạp không chặn (`media="print"` + `onload`, có `<noscript>`); một file JS `defer` (≈7,4 KB min, 2,8 KB gzip); font hệ thống; không thư viện, không yêu cầu bên thứ ba (test kiểm tra).
- Ảnh: không đăng ký kích thước mới; `sizes` theo bố cục; width/height từ attachment; đúng **một** ảnh `eager` + `fetchpriority=high` mỗi trang (ảnh đầu tiên có thật trong hàng đầu), còn lại `lazy`; avatar luôn lazy.
- Truy vấn: 23–52 truy vấn/trang khi cache ấm (bằng nhau ở 1.000 và 50.000 bài) và 4–51 ms CSDL ở 50.000 bài cho mọi trang trừ tìm kiếm (xem [PERFORMANCE.md](PERFORMANCE.md)). Hai điểm yếu cố hữu của lõi WordPress được xử lý: đếm bài của tác giả (`SQL_CALC_FOUND_ROWS` 0,63 s → COUNT có cache 2 ms) và tìm kiếm `LIKE '%từ%'` (2,6–5,2 s ở 50.000 bài → plugin có chỉ mục FULLTEXT, từ hiếm 35 ms; không có chỉ mục thì gợi ý khi gõ tự tắt ở trên 15.000 bài).

## 7. Truy cập (WCAG 2.2 AA là mục tiêu)

Skip link; landmark duy nhất (một `main`, `header`, `footer`); nhãn duy nhất cho mọi `nav`; một H1 mỗi trang, không nhảy cấp tiêu đề (test kiểm tra 17 ngữ cảnh); nút disclosure `aria-expanded/aria-controls` cho mega menu và menu di động, Esc đóng; `scroll-padding` cho header cố định; tôn trọng `prefers-reduced-motion`, `forced-colors`; bảng rộng nằm trong vùng cuộn focus được; FAQ/mục lục là `<details>`; liên kết ảnh thẻ là trang trí (`aria-hidden`, `tabindex=-1`) để mỗi thẻ chỉ có một điểm dừng Tab. Điểm neo của mục lục và liên kết `#` đáp xuống đúng dưới header (một khai báo `scroll-padding-top` ở `html`), mục "hiện tại" của mục lục tính theo chính đường neo đó, và không có JavaScript thì menu và ô tìm kiếm vẫn mở được. Kết quả axe-core theo từng loại trang (17 loại, sáng và tối, các trạng thái tương tác) nằm trong [TESTING.md](TESTING.md).

## 8. Bảo mật

Escape khi in (`esc_html/esc_attr/esc_url`, `wp_kses` có danh sách thẻ); dữ liệu meta được xác thực lại ở phía theme dù plugin đã làm sạch; không dùng `eval`/`unserialize`/`$_REQUEST` thô (test quét mã nguồn); `$_GET` chỉ qua `absint`/làm sạch; không có endpoint ghi dữ liệu trong theme (nonce/quyền chỉ cần ở plugin, nơi có biểu mẫu quản trị: meta box, trang cài đặt, trường term/user — được test về nonce, quyền, autosave, dữ liệu bị giả mạo). Bộ test `test-output-escaping.php` lưu dữ liệu độc hại vào mọi trường rồi render trang thật. Nội dung đặt mật khẩu: theme không in tóm tắt, ý chính, FAQ, nguồn hay thời gian đọc cạnh ô nhập mật khẩu (`nh_editorial_get_extras()` trả về rỗng khi `post_password_required()`), và plugin gỡ mọi khóa `nh_*` khỏi phản hồi REST của bài đó trừ khi có mật khẩu hoặc quyền sửa bài; khách cũng không đọc được `nh_keywords` và không thấy cluster chỉ do bản nháp dùng.
