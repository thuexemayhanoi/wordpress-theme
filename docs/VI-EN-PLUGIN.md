# Plugin Nguyen Ha VI-EN: blog song ngữ Việt – Anh bản địa

Plugin `plugins/nguyen-ha-vi-en/` (phiên bản **0.5.1**) thêm phần tiếng Anh dưới `/en/` cho `https://blog.rentbikehanoi.com/`. Tiếng Việt vẫn nằm ở nguyên chỗ hiện tại. **Không có bước nào trong tài liệu này, và không có workflow nào, bật tính năng trên website đang chạy**: bật là quyết định của chủ sở hữu, sau khi xem trước trên staging.

Plugin chạy độc lập (có ZIP riêng, không cần theme hay plugin đồng hành của repository này), không dùng Polylang, WPML hay plugin đa ngôn ngữ nào, không dịch tự động, không gọi API trả phí và không bao giờ tự đăng bài tiếng Anh.

## 1. Quy tắc bảo vệ (luôn đúng)

| Quy tắc | Cách plugin giữ |
|---|---|
| ~500 bài tiếng Việt không bị sửa, xóa, đổi slug, URL, canonical, tiêu đề hay trường SEO | Plugin không ghi vào bài viết, trang hay danh mục nào. Dữ liệu duy nhất nó ghi: vài tùy chọn riêng (`nh_vien_translation_pairs_v1`, `nh_vien_independent_en_posts_v1`, `nh_vien_english_categories_v1`, `nh_vien_page_pairs_v1` và công tắc `nh_vien_gate_v1`), quy tắc rewrite của chính nó (lưu khi bạn bật hoặc bấm nút; rút lại khi tắt hoặc gỡ kích hoạt, **chỉ rút quy tắc của plugin**) và, khi bạn bấm nút, một trang **nháp** `en`. |
| Cài, cập nhật hoặc kích hoạt plugin không làm đổi website | Plugin **tắt** cho tới khi quản trị viên bật nó (mục 3). Trước đó nó không đổi URL, truy vấn, trang hay thẻ nào. Gỡ kích hoạt đóng lại và quên công tắc, nên kích hoạt lại luôn bắt đầu ở trạng thái đóng. |
| Không tự đăng bài tiếng Anh | Một bài tiếng Anh chỉ hiện khi đã **xuất bản**, slug dùng được, không có trang nào chiếm địa chỉ đó (và, với bài ghép cặp, bài tiếng Việt của cặp cũng đã xuất bản). Chưa đủ điều kiện → 404 ở mọi địa chỉ. Bài chưa đăng ký là tiếng Anh là bài thường, giữ nguyên địa chỉ gốc và không bị đổi. |
| Không dùng plugin đa ngôn ngữ, không API trả phí | Chỉ mã trong thư mục plugin; không gọi dịch vụ ngoài; không nạp JavaScript. |

## 2. Cách hoạt động

**Ba loại nội dung tiếng Anh** (tất cả đều do bạn tạo và tự viết; không có bản dịch tự động):

1. **Bài ghép cặp** — một bài tiếng Anh ghép một–một với bài tiếng Việt tương ứng ở *Công cụ → VI/EN Links* (nhập ID hai bài). Mỗi bài giữ tiêu đề, nội dung, slug, mô tả và SEO riêng. Cặp hoạt động hai chiều (hreflang và bộ chuyển ngôn ngữ). Khi **ghép**, bài tiếng Anh phải đang là **nháp** (hoặc chờ duyệt), như bài độc lập: nhờ vậy một bài tiếng Việt đang chạy không bao giờ trở thành "bài tiếng Anh" chỉ vì nhập nhầm ID; ghép xong bạn mới xuất bản bài tiếng Anh. Một bài đã là phía tiếng Anh của một cặp thì không đăng ký thêm làm bài độc lập, và không gỡ được một bài khỏi danh sách nếu việc đó làm mồ côi liên kết.
2. **Bài tiếng Anh độc lập** — không cần bản tiếng Việt. Tạo bài ở trạng thái **nháp** (hoặc chờ duyệt), **đăng ký ID** ở *Independent English articles* rồi mới xuất bản. Plugin từ chối đăng ký bài đã xuất bản, nên không thể gắn nhãn "tiếng Anh" cho một bài tiếng Việt đang chạy. Bài độc lập không có hreflang (không có đối ứng).
3. **Trang tiếng Anh** — trang WordPress con của trang `en` (`/en/about/`, `/en/prices/`, `/en/contact/`…). Hai trang tương đương (cùng ý định tìm kiếm) có thể được **ghép** ở *English pages / About, Prices, Contact* để có hreflang; trang không có đối ứng vẫn hợp lệ.

| Địa chỉ | Nội dung |
|---|---|
| `/en/` và `/en/page/2/` | Trang chủ tiếng Anh. Nếu có **trang đã xuất bản** tại `/en/`, trang đó là trang chủ tiếng Anh (theme Nguyen Ha Editorial dùng mẫu biên tập riêng: tiêu đề trang là H1, phần tóm tắt là đoạn dẫn, nội dung là phần giới thiệu, rồi lưới bài tiếng Anh nổi bật, chủ đề và bài mới; không có trang 2). Chưa có trang thì `/en/` là danh sách bài tiếng Anh được phép hiện. |
| `/en/{slug}/` (và `/en/{slug}/2/`) | Bài tiếng Anh. Slug chỉ gồm chữ thường, số, gạch ngang/gạch dưới; không dùng từ dành riêng (`page`, `category`, `tag`, `author`, `search`, `feed`, …). |
| `/en/category/{đường dẫn}/`, `/en/tag/{slug}/` (có phân trang) | Danh mục và thẻ chỉ liệt kê bài tiếng Anh; danh mục không có bài Anh → 404. |
| `/en/?s=…` | Tìm kiếm chỉ trong nội dung tiếng Anh: bài tiếng Anh **và các trang tiếng Anh** (không lập chỉ mục, `noindex`). Tìm kiếm tiếng Việt không bao giờ trả bài hay trang tiếng Anh. |
| `/{slug}/` của một bài tiếng Anh đã được phép hiện | **301** sang `/en/{slug}/` (chống nội dung trùng); chưa được phép hiện thì 404. |
| `/en/{slug-cũ}/` của bài tiếng Anh đã đổi slug | **301** sang địa chỉ mới, như WordPress làm cho mọi bài (dùng chính dữ liệu slug cũ của WordPress). Không đoán: slug cũ phải đang trống và chỉ thuộc đúng một bài tiếng Anh đang được phép hiện. |
| Trang tĩnh dưới `/en/…` (cả trang sâu hơn một cấp, như `/en/about/team/`) | Giữ nguyên địa chỉ, thắng bài tiếng Anh cùng slug, và luôn được trả lời bằng **tiếng Anh** (menu, bộ chuyển ngôn ngữ, cache mảnh của tiếng Anh). Địa chỉ nào dưới `/en/` mà không có gì trả lời là 404 tiếng Anh. |
| `/en/?p=…`, `?page_id=…`, `?author=…`, `?feed=…` | Danh sách tiếng Anh trên `/en/` chỉ nhận trang số, từ khóa tìm kiếm, danh mục và thẻ của chính tuyến đường đó; các biến truy vấn khác trên địa chỉ bị bỏ, nên không thể biến danh sách thành bài, trang hay lưu trữ tiếng Việt. |
| `/en/feed/`, trang tác giả, lưu trữ theo ngày | Không có (404). Feed chính vẫn là tiếng Việt, và các trang tiếng Anh **không quảng bá feed nào** (không có thẻ `<link rel="alternate" type="application/rss+xml">`) vì feed của website là tiếng Việt và `/en/{slug}/feed/` không tồn tại. |

**Danh mục tiếng Anh.** Tạo danh mục **rỗng** trong *Bài viết → Danh mục*, rồi đăng ký ID ở *English parent and child categories* **trước khi** gán bài; đăng ký danh mục cha trước, rồi các danh mục con (một danh mục con chỉ nằm dưới cha tiếng Anh). Plugin không đổi tên, slug hay cha của bất kỳ danh mục nào và từ chối đăng ký danh mục đã có bài. Danh mục tiếng Anh không hiện trong danh sách, sơ đồ trang hay widget của phần tiếng Việt.

**Mỗi danh sách một ngôn ngữ.** Trang chủ, danh mục, thẻ, tìm kiếm, widget bài mới, bài trước/sau và **gợi ý khi gõ ở ô tìm kiếm** (trong thân trang, địa chỉ REST `/wp-json/wp/v2/search` mang thêm `nh_vien_lang=vi|en`) của phần tiếng Việt không bao giờ liệt kê bài tiếng Anh, và ngược lại; client REST khác không gửi tham số đó thì vẫn thấy cả hai ngôn ngữ. Với danh sách có thể trả **trang** (tìm kiếm, mọi loại nội dung), trang `en` và các trang con của nó thuộc về tiếng Anh. Một danh sách tự nêu ID bài (`post__in`) cũng giữ đúng ngôn ngữ, và danh sách loại trừ (`post__not_in`, ví dụ "bài liên quan" không gồm bài đang đọc) vẫn được tôn trọng: WordPress bỏ qua `post__not_in` ngay khi có `post__in`, nên plugin tự trừ ID khỏi danh sách. Các trang `/en/…` dùng ngôn ngữ `en_US` (`<html lang="en-US">`, chuỗi của WordPress và theme bằng tiếng Anh); logo và ô tìm kiếm của theme dẫn về `/en/`.

**Menu, chân trang, widget tiếng Anh.** Trong các trang tiếng Anh, vị trí menu `primary` dùng menu có slug `primary-en` và `footer` dùng `footer-en` (quy ước cũ `{slug-menu-việt}-en` vẫn nhận). Khi chưa có menu tiếng Anh, plugin hiện một menu nhỏ **chỉ tiếng Anh** (Home, các trang About/Prices/Contact đã xuất bản dưới `/en/`, tối đa sáu danh mục gốc **đã có bài tiếng Anh**, không có danh mục rỗng vì liên kết của chúng sẽ là 404) và không bao giờ lấy mục của menu tiếng Việt: WordPress tự chọn "menu đầu tiên có mục" khi vị trí không có menu, nên plugin trả lời trước khi WordPress kịp tìm (bộ lọc `pre_wp_nav_menu`). Chân trang tiếng Anh của theme có bốn vùng widget riêng `footer-en-1` … `footer-en-4` (không dùng widget tiếng Việt) và, khi vùng trống, dùng thông tin liên hệ chung đã xác minh của plugin đồng hành. Cache mảnh của theme và tiêu đề trang tiếng Anh cũng tách khỏi tiếng Việt.

**Bộ chuyển ngôn ngữ.** Hai liên kết VI và EN, mục tiêu chạm 44 px, dùng được bằng bàn phím và trình đọc màn hình; trên bài viết dẫn tới bản dịch, ở nơi khác dẫn về trang chủ của ngôn ngữ kia, và ẩn khi không có nơi để dẫn. Theme Nguyen Ha Editorial đặt nó ngay **sau** liên kết "Skip to content"; theme khác nhận nó ở `wp_body_open()`; theme không gọi `wp_body_open()` nhận nó phía trên nội dung bài; ở mọi nơi khác dùng shortcode `[nh_vien_switcher]`.

**SEO.** `hreflang` (vi, en, x-default) giữa hai bài của một cặp, giữa hai trang đã ghép và giữa hai trang chủ; canonical riêng cho từng ngôn ngữ; breadcrumb tiếng Anh; địa chỉ tiếng Anh trong sơ đồ trang. Với **Yoast SEO** (plugin SEO của website): Yoast vẫn là chủ của tiêu đề, canonical và **một** `sitemap_index.xml`; plugin chỉ dùng các filter được tài liệu hóa của Yoast để (1) lấy canonical và `og:url` là địa chỉ `/en/…`, tôn trọng canonical biên tập viên đã gõ tay, (2) ghi breadcrumb và `BreadcrumbList` tiếng Anh, (3) thêm trang chủ tiếng Anh vào sơ đồ bài viết, và mỗi danh mục/thẻ mà Yoast đưa vào sơ đồ thì có địa chỉ `/en/…` tương ứng: danh mục/thẻ **chỉ có bài Anh** được đổi địa chỉ ngay trong mục của chúng (địa chỉ không có `/en/` của chúng trả 404, và nhờ vậy sơ đồ không bao giờ rỗng vì lý do ngôn ngữ nên Yoast không trả 404 cho sơ đồ đó), danh mục/thẻ dùng ở cả hai ngôn ngữ giữ mục cũ và có thêm mục `/en/…` ngay sau, (4) bỏ bài Anh chưa được phép hiện khỏi sơ đồ. Không có plugin SEO, sơ đồ của WordPress liệt kê bài Anh ở địa chỉ `/en/…` và bỏ các danh mục/thẻ chỉ có bài Anh, nhưng không thêm trang chủ hay lưu trữ tiếng Anh (WordPress không cho plugin thêm mục vào sơ đồ này). Yoast không in hreflang cho các bài riêng lẻ nên plugin in hreflang (cho cặp bài, cặp trang và hai trang chủ).

**Cache.** Địa chỉ `/en/…` là URL riêng nên cache trang theo URL tự tách hai ngôn ngữ. Cache mảnh của theme Nguyen Ha Editorial có hậu tố ngôn ngữ; khi thêm hoặc bỏ một cặp, một bài hay danh mục tiếng Anh, hoặc bật/tắt công tắc, plugin báo cho cache mảnh của theme và hàng đợi xóa cache trang của plugin đồng hành (cả hai tùy chọn). Khi một bài của cặp đổi, plugin đồng hành còn được nhắc xóa **bản kia của cặp**, trang chủ `/en/` và các lưu trữ tiếng Anh của bài (bộ lọc `nh_seo_purge_post_urls`). Với cache khác, hãy xóa cache sau khi thay đổi.

## 3. Cài đặt và công tắc

1. Lấy `nguyen-ha-vi-en-<phiên bản>.zip` từ artifact `release-<sha>` của một lần chạy Theme QA **xanh** (đối chiếu `SHA256SUMS`/`manifest.json`). ZIP chỉ chứa plugin (không có thư mục `tests/`).
2. *Plugins → Add New → Upload Plugin*, cài, kích hoạt. **Chưa có gì thay đổi.**
3. Tạo nội dung tiếng Anh ở dạng **nháp** (mục 2), đăng ký/ghép theo hướng dẫn ở *Công cụ → VI/EN Links*.
4. **Bật** phần tiếng Anh bằng một trong hai cách (không bao giờ trộn):

   **a. Nút trong WordPress (không phải sửa tệp).** *Công cụ → VI/EN Links → Switch English routing on.* Chỉ quản trị viên (`manage_options`) và có nonce. Plugin lưu quy tắc rewrite trong cùng bước và báo ngay kết quả; nếu WordPress không lưu được, mọi thứ được hoàn lại như cũ. Trên website **production** (loại môi trường WordPress không phải `local`, `development` hay `staging`; mặc định là `production` nếu `wp-config.php` không khai báo `WP_ENVIRONMENT_TYPE`) nút còn đòi bạn **tick xác nhận**: đã có bản sao lưu mới và đã kiểm tra trên staging. Quyết định được lưu cùng **địa chỉ của website**: sao chép cơ sở dữ liệu sang địa chỉ khác (bản staging, tên miền mới, đổi http sang https) thì phần tiếng Anh **vẫn đóng** cho tới khi có người bật lại ở đó, nên không thể "vô tình" bật production bằng cách đẩy dữ liệu staging lên. Nút **Switch English routing off** tắt lại (xem mục 6).

   **b. `wp-config.php`.** Khi `wp-config.php` có `NH_VIEN_ENABLE_EN_ROUTES`, hằng số **quyết định một mình** và nút không hiện: `define( 'NH_VIEN_ENABLE_EN_ROUTES', true );` mở cổng (trên production cần thêm `define( 'NH_VIEN_ALLOW_PRODUCTION', true );`, do chủ sở hữu đặt sau khi kiểm tra staging) rồi bấm **Store the rewrite rules** một lần. Bất kỳ giá trị nào khác `true` thì đóng. Đây cũng là **phanh khẩn cấp**: `define( 'NH_VIEN_ENABLE_EN_ROUTES', false );` tắt phần tiếng Anh ngay lập tức, bất kể nút trong WordPress nói gì, kể cả khi không vào được trang quản trị. Trên production, `NH_VIEN_ALLOW_PRODUCTION` đặt khác `true` còn cấm nút bật.

   Màn hình *VI/EN Links* ghi rõ: đang bật hay tắt, **ai quyết định** (wp-config hay nút, ai bấm và lúc nào), loại môi trường, đã lưu quy tắc chưa, "English pages are live" Có/Không, và lý do khi Không.
5. Viết bài/trang tiếng Anh, ghép hoặc đăng ký, kiểm tra, rồi **xuất bản** từng bài. Tới lúc đó mới có gì công khai.
6. (Tùy chọn) nút *Create a draft English home page* tạo trang nháp `en` có `[nh_vien_posts]`; bạn tự chỉnh và tự xuất bản.

Plugin từ chối bật (và từ chối lưu quy tắc) khi website dùng đường dẫn tĩnh dạng "Plain" hoặc bắt đầu bằng `/index.php/` (địa chỉ của plugin không có `index.php`), khi đã có một **bài** xuất bản với slug `en`, hoặc khi có **danh mục** slug `en` mà cấu trúc đường dẫn có `%category%` (các bài của danh mục đó đã nằm dưới `/en/` và sẽ bị trang chủ tiếng Anh che mất).

## 4. Danh sách kiểm tra staging (bắt buộc trước khi bật production)

Làm trên **bản sao** có cùng theme, cùng plugin SEO, cùng cấu trúc permalink và dữ liệu giống production. Đạt = mọi mục đạt; còn mục chưa đạt thì **không** bật production. (Nếu staging của nhà cung cấp tự coi mình là `production`, nút sẽ đòi tick xác nhận như production; tick sau khi đã kiểm tra bản sao.)

- [ ] Có sao lưu **mới** (CSDL + `wp-content`) đã mở thử được; biết cách quay lại (mục 6).
- [ ] Trước khi bật, lưu HTML của trang chủ, một danh mục, một bài, `/?s=xe`, `/feed/`; sau khi **bật** (chưa ghép/đăng ký bài nào) các trang đó **giống hệt**. Sau khi **tắt** lại cũng giống hệt.
- [ ] Chạy `wp eval-file tools/staging-preflight.php content` (chỉ đọc) **trước** và **sau** khi bật, không thêm nội dung nào giữa hai lần: dấu vân tay của bài viết/trang, danh mục/thẻ, gán danh mục, tùy chọn được bảo vệ (địa chỉ, tiêu đề, trang chủ, permalink) và **toàn bộ chữ của bài/trang** phải giống hệt; chỉ dòng `rewrite rules` đổi (plugin lưu quy tắc của nó). Trong phòng thử của dự án kết quả đúng như vậy: bài tiếng Việt không bị chạm tới.
- [ ] Không có trang hay bài nào đã dùng `/en/` hoặc `/en/…` ngoài ý muốn. Một **bài** đã xuất bản có slug `en` làm plugin từ chối bật (màn hình ghi "Address /en/ used by a post"); một **trang** đã xuất bản ở `/en/` trở thành trang chủ tiếng Anh; một trang dưới `/en/…` giữ địa chỉ và làm ẩn bài Anh cùng slug (màn hình báo trạng thái của từng cặp).
- [ ] Một bài Việt đã ghép: có hreflang `vi`, `en`, `x-default` đúng hai chiều; một canonical; bộ chuyển ngôn ngữ dẫn đúng. Bài/trang tiếng Anh không có đối ứng: không có hreflang.
- [ ] Bài Anh: `<html lang="en-US">`, canonical là `/en/{slug}/`, JSON-LD (`WebPage`/`Article`/`BreadcrumbList`) có `url` là `/en/{slug}/`; địa chỉ `/{slug}/` trả **301** sang `/en/{slug}/` và không bị vòng lặp.
- [ ] `/sitemap_index.xml` vẫn là **một** sơ đồ; bài Anh xuất hiện ở `/en/{slug}/`, không có nháp, không có địa chỉ gốc của bài Anh.
- [ ] `/en/`, các danh mục và thẻ tiếng Anh: chỉ có bài Anh, phân trang đúng, canonical riêng; `/en/?s=` là `noindex`; danh mục tiếng Anh chưa có bài trả 404.
- [ ] Trang chủ, danh mục và tìm kiếm tiếng Việt **không** chứa bài Anh hay danh mục Anh; bài trước/sau, widget "bài mới" và gợi ý khi gõ ở ô tìm kiếm không lẫn ngôn ngữ.
- [ ] Trang chủ tiếng Anh, menu `primary-en`/`footer-en`, chân trang và các trang About/Prices/Contact: **không có chữ tiếng Việt** lọt vào; số điện thoại, địa chỉ, giá và chính sách khớp dữ liệu doanh nghiệp thật (không sao chép dữ liệu của website khác).
- [ ] Bộ chuyển ngôn ngữ nhìn thấy và dùng được ở điện thoại (375 px), máy tính bảng (768 px), máy tính (1440 px), bằng bàn phím và trình đọc màn hình; không có cuộn ngang.
- [ ] Cache trang/CDN: `/en/…` được lưu riêng, đã xóa cache sau mỗi thay đổi; không có nội dung ngôn ngữ này hiện ở URL của ngôn ngữ kia.
- [ ] Bài Anh chưa được phép hiện (nháp, bài Việt chưa xuất bản, slug lạ, trang đang chiếm địa chỉ) và mọi địa chỉ `/en/…` không có thật: tất cả 404; không có "soft 404" (trang 200 nhưng rỗng).
- [ ] Thử gỡ: bấm **Switch English routing off** → `/en/…` trở lại như khi chưa có plugin và trang tiếng Việt vẫn như cũ; thử phanh khẩn cấp (`NH_VIEN_ENABLE_EN_ROUTES` = `false`); gỡ kích hoạt plugin → trang tiếng Việt vẫn như cũ.

## 5. Bật trên production

Chỉ khi mục 4 đạt hoàn toàn và chủ sở hữu duyệt bằng văn bản, trong một khung giờ đã định:

1. Sao lưu CSDL + `wp-content` (từ hPanel) và xác nhận mở được.
2. Cài ZIP (plugin đang tắt), kích hoạt (chưa thay đổi gì).
3. Tạo/nhập nội dung tiếng Anh ở dạng **nháp** và đăng ký danh mục, bài, trang như trên staging. **Không dùng lại ID số của staging** — kiểm tra lại ID của môi trường đang làm.
4. Chạy `wp eval-file tools/staging-preflight.php content` và lưu kết quả; rồi *Công cụ → VI/EN Links → Switch English routing on*, tick xác nhận (hoặc đặt **cả hai** hằng số trong `wp-config.php` rồi bấm "Store the rewrite rules"); chạy lại lệnh dấu vân tay: chỉ dòng `rewrite rules` được đổi. Kiểm tra trên chính website: `/en/` chưa có gì công khai (còn nháp), trang tiếng Việt giống hệt.
5. Xuất bản trang chủ `/en/`, các trang con và từng bài đã duyệt; kiểm tra các mục chính của mục 4 trên chính website.
6. Đưa `https://blog.rentbikehanoi.com/sitemap_index.xml` vào Google Search Console nếu chưa có (không cần gửi lại sơ đồ riêng cho `/en/`), theo dõi Coverage, hreflang và canonical trong vài tuần.

Plugin **không** tự kích hoạt, không tự bật, không tự merge và không tự đưa gì lên production: `main` chỉ đổi khi chủ sở hữu quyết định. Merge một nhánh vào `main` trên GitHub **không** triển khai gì lên website; website chỉ đổi khi ZIP được cài và công tắc được bật.

## 6. Quay lại

1. **Nhanh nhất:** *Công cụ → VI/EN Links → Switch English routing off.* Quy tắc rewrite của plugin bị rút khỏi dữ liệu đã lưu (mọi quy tắc khác giữ nguyên), `/en/…` trở lại như trước khi bật. Trang tiếng Việt không đổi.
2. **Không vào được trang quản trị:** đặt `define( 'NH_VIEN_ENABLE_EN_ROUTES', false );` trong `wp-config.php` — phần tiếng Anh tắt ngay; mọi địa chỉ `/en/…` còn quy tắc đã lưu trả 404 cho tới khi bạn vào được và bấm "Store the rewrite rules" hoặc lưu *Cài đặt → Đường dẫn tĩnh*.
3. Gỡ kích hoạt plugin nếu muốn. Gỡ kích hoạt tự rút các quy tắc của plugin và quên công tắc, nên dù làm sai thứ tự cũng **không** còn địa chỉ `/en/…` nào trả về bản sao của trang chủ tiếng Việt. Sau đó địa chỉ `/en/{slug}/` cũ trả 404 hoặc được WordPress dẫn về địa chỉ gốc của bài tiếng Anh.
4. Các danh sách (cặp bài, bài/danh mục tiếng Anh đã đăng ký, cặp trang) vẫn nằm trong CSDL để bật lại không phải làm lại; xóa tay các tùy chọn trong mục 1 nếu muốn.
5. Trang tiếng Anh dạng **trang WordPress** dưới `/en/…` đã xuất bản vẫn truy cập được ở địa chỉ riêng của chúng khi plugin tắt (đó là trang WordPress bình thường); đưa chúng về nháp nếu không muốn công khai.

Trang tiếng Việt không đổi ở mọi bước (kiểm thử CI so sánh HTML với bản chụp ban đầu).

## 7. Đã kiểm thử gì

| Lớp | Nội dung | Nơi chạy |
|---|---|---|
| Kiểm thử offline (PHP thuần) | `tests/smoke.php`, `links.php`, `routes.php`, `gate.php`, `gate-config.php`, `boot.php`, `query.php`, `content.php`, `cache.php`, `seo.php`: công tắc và phanh khẩn cấp, xác nhận production, địa chỉ website, hoàn tác khi không lưu được quy tắc, quy tắc rewrite (lưu lên đầu mà không tạo lại quy tắc của plugin khác, chỉ rút đúng quy tắc của mình), slug và slug cũ, cặp bài, nonce/quyền, phạm vi truy vấn (kể cả `post__in`/`post__not_in` và trang trong tìm kiếm), biến truy vấn của danh sách, ngôn ngữ của trang sâu dưới `/en/`, menu tiếng Anh dự phòng và thẻ feed, một truy vấn cho các slug công khai, hook theo từng môi trường, mục sơ đồ Yoast, xóa cache trang. Kiểm tra đột biến bằng tay: sửa cố ý từng đoạn mã quan trọng, test phải thất bại | PHP 7.4 – 8.4 trong CI |
| Site WordPress thật qua HTTP | `tests/site/run.php`: trạng thái và chuyển hướng, ngôn ngữ từng danh sách, bộ chuyển ngôn ngữ, hreflang/canonical/sơ đồ, an ninh (XSS, tham số thô, CSRF, quyền), cache tách ngôn ngữ, màn hình quản trị, trang chủ tiếng Anh dạng trang, công tắc đóng lại, **trang tiếng Việt giống hệt khi bật/tắt**, gỡ kích hoạt; và bộ `dashboard` (188 kiểm tra): nonce sai/thiếu, khách, người đăng ký, GET và chế độ lạ bị từ chối; bật/tắt (quy tắc được lưu/rút, trang Việt giống hệt); website production (xác nhận, `NH_VIEN_ALLOW_PRODUCTION` đúng/sai); CSDL chép sang địa chỉ khác; hằng số `wp-config.php` (phanh khẩn cấp); đường dẫn tĩnh Plain; bài giữ địa chỉ `/en/`; gỡ và kích hoạt lại; và (khối 9) trang tiếng Anh khi **không có menu tiếng Anh**, bài đổi slug, trang sâu hai cấp dưới `/en/`, biến truy vấn lạ trên `/en/`, tìm kiếm có trang, thẻ feed. Khối 9 đã được chạy thử trên bản plugin **trước** các sửa lỗi và thất bại đúng chỗ (menu tiếng Việt lọt vào trang tiếng Anh, bài đổi slug trả 404, `/en/?feed=rss2` trả feed, tìm kiếm tiếng Việt thấy trang tiếng Anh), rồi đạt sau khi sửa | WordPress 7.1.3 và 6.3.5; theme Editorial có và không có Yoast SEO; theme khối mặc định; theme cổ điển không gọi `wp_body_open()` |
| Trình duyệt | `tests/e2e/switcher.spec.mjs`: bộ chuyển ngôn ngữ ở 375/768/1440 px, vùng chạm, bàn phím, thứ tự sau liên kết bỏ qua, axe (sáng và tối) | Chromium trong CI |
| Đóng gói | `tools/verify-package.sh`: thư mục và tệp bắt buộc, không có `tests/`, bảo vệ truy cập trực tiếp ở mọi tệp, một phiên bản ở mọi nơi, không phụ thuộc theme hay plugin đồng hành | CI (job `static`) |
| Hiệu năng | Đo số truy vấn cơ sở dữ liệu mỗi trang trên site thử 500 bài Việt + 200 bài Anh (mục 10) | Phòng thử cục bộ (SQLite) |

**Chưa kiểm chứng (không khẳng định là ổn):**

- Website thật và staging thật: môi trường này không có staging; mọi kiểm thử chạy trên site dựng sẵn bằng dữ liệu giả. Mục 4 là việc phải làm trên staging.
- Cấu hình Yoast thật của website (kiểu bài, taxonomy trong breadcrumb, schema); CI dùng Yoast mặc định từ wordpress.org.
- Rank Math, All in One SEO, SEOPress, The SEO Framework: được nhận diện (không in canonical thứ hai) nhưng không có kiểm thử.
- WordPress multisite; trình duyệt ngoài Chromium; thiết bị thật và trình đọc màn hình; Core Web Vitals thực địa.
- Cache trang/CDN thật (LiteSpeed Cache, Cloudflare…): chỉ kiểm tra bằng các hook chung.

## 8. Giới hạn đã biết

- Một site cho mỗi lần cài; multisite chưa kiểm thử.
- Bài viết được ghép cặp hoặc đăng ký là tiếng Anh; trang tĩnh ghép theo ID (About, Prices, Contact…); các loại nội dung khác không được plugin dịch.
- Không có feed tiếng Anh; số bài WordPress lưu cho danh mục/thẻ và widget "Archives" theo tháng đếm cả hai ngôn ngữ.
- Logo và ô tìm kiếm đổi ngôn ngữ nhờ `home_url( '/' )`; theme dựng các liên kết đó bằng cách khác (một số theme khối) vẫn dẫn về trang chủ tiếng Việt.
- Chỉ bài tiếng Anh có slug ASCII đơn giản mới truy cập được; mục menu, widget và văn bản gõ tay không tự dịch.
- Khi có trang tĩnh tại `/en/` thì trang đó là trang chủ tiếng Anh và không có `/en/page/2/`.
- Không có plugin SEO: sơ đồ của WordPress không liệt kê trang chủ và lưu trữ tiếng Anh.
- Quyết định của nút gắn với **địa chỉ website** (`home`): đổi địa chỉ (ví dụ http sang https) làm phần tiếng Anh đóng lại cho tới khi bấm bật lại; màn hình nói rõ điều đó.
- **Không xóa thư mục plugin bằng FTP khi đang bật**, và trên multisite đừng gỡ kích hoạt "cho toàn mạng": cả hai không chạy mã gỡ kích hoạt nên quy tắc `/en/…` còn nằm trong dữ liệu và, thiếu plugin, WordPress trả trang chủ tiếng Việt cho các địa chỉ đó. Hãy bấm **Switch English routing off** (hoặc gỡ kích hoạt từng site) trước. Nếu lỡ xảy ra: *Cài đặt → Đường dẫn tĩnh → Lưu thay đổi* để WordPress tạo lại quy tắc.
- Không gán **bài tiếng Việt** vào danh mục đã khai báo là tiếng Anh: danh mục đó là của phần tiếng Anh, bài Việt gán nhầm sẽ không hiện ở danh sách Việt của danh mục ấy.
- Plugin chỉ điều khiển các danh sách, tìm kiếm và liên kết **ở phía trang web**: REST API (`/wp-json/wp/v2/posts`) vẫn trả cả hai ngôn ngữ (biên tập viên và tích hợp cần thấy mọi bài), lưu trữ tác giả và theo ngày chỉ có ở phần tiếng Việt (không có bài tiếng Anh), oEmbed của bài tiếng Anh hoạt động (WordPress tìm được bài ở `/en/{slug}/`) nhưng địa chỉ khung nhúng `/en/{slug}/embed/` bị WordPress chuyển 301 về chính bài, và truy vấn trực tiếp vào cơ sở dữ liệu (không qua `WP_Query`) của theme hay plugin khác không được lọc.
- Danh sách tiếng Anh chỉ nhận trang số, từ khóa, danh mục và thẻ từ địa chỉ: các tham số lọc khác của WordPress (`?cat=`, `?author=`, `?m=`) bị bỏ trên `/en/`, nên không có bộ lọc theo ngày hay tác giả cho phần tiếng Anh.

## 9. Lệnh kiểm tra nhanh

```bash
for t in plugins/nguyen-ha-vi-en/tests/*.php; do php "$t"; done            # offline, chỉ cần PHP
bash tools/package.sh && bash tools/verify-package.sh dist                    # ZIP độc lập + kiểm tra ZIP
# Site thật qua HTTP (dựng site bằng tests/bin/setup-site.sh, xem docs/TESTING.md):
NH_VIEN_SEED_STEP=content wp eval-file plugins/nguyen-ha-vi-en/tests/site/seed.php
php plugins/nguyen-ha-vi-en/tests/site/run.php --base=http://127.0.0.1:8080 --fixture=wp-content/nh-vien-fixture.json --suite=main
# Bộ "dashboard" (cần plugin được kích hoạt lại, không có hằng số trong wp-config.php):
php plugins/nguyen-ha-vi-en/tests/site/run.php --base=http://127.0.0.1:8080 --fixture=wp-content/nh-vien-fixture.json --suite=dashboard --wp="wp --allow-root --path=/duong/dan/wordpress"
NH_BASE_URL=http://127.0.0.1:8080 npx playwright test -c plugins/nguyen-ha-vi-en/tests/e2e/playwright.config.mjs
```

## 10. Hiệu năng

Số truy vấn cơ sở dữ liệu và bộ nhớ đỉnh mỗi trang, cùng một site thử: 500 bài Việt, 100 bài Anh ghép cặp và 100 bài Anh độc lập (mỗi bài Anh khoảng 20 KB nội dung), trang `/en/` đang nháp có sáu trang con đã xuất bản; WordPress 7.1.3 trên SQLite, theme Nguyen Ha Editorial; đo bằng `tests/bin/mu-metrics.php`, lần đo thứ hai trở đi, máy chủ khởi động lại sau mỗi lần đổi bản plugin. "Cổng đóng" là plugin đã kích hoạt nhưng chưa bật (như khi chưa có plugin).

| Trang | Cổng đóng | 0.5.0 | 0.5.1 trước các sửa lỗi rà soát | **0.5.1 cuối** |
|---|---:|---:|---:|---:|
| `/` (trang chủ Việt) | 48 | 250 | 51 | **54** |
| Một bài Việt đã ghép | 44 | 248 | 49 | **53** |
| Một danh mục Việt | 33 | 241 | 42 | **42** |
| Một bài Anh ghép cặp | — | 258 | 53 | **53** |
| `/en/` | — | 244 | 39 | **36** |
| Một danh mục Anh | — | 249 | 44 | **41** |
| `/en/about/` | 27 | 244 | 39 | **35** |
| **Bộ nhớ đỉnh, mọi trang (MB)** | 6,4–6,8 | 11,4–11,8 | 11,1–11,5 | **6,6–7,0** |

- Bản 0.5.0 hỏi WordPress "có trang nào chiếm địa chỉ này dưới `/en/` không" **riêng cho từng bài tiếng Anh**, trên mọi trang kể cả trang tiếng Việt: thêm khoảng một truy vấn cho mỗi bài tiếng Anh (+200 truy vấn mỗi trang với 200 bài; ước tính khoảng +500 với 500 bài). Bản 0.5.1 hỏi một lần cho cả yêu cầu (trang `/en/` và các trang con đã xuất bản), nên chi phí không còn tăng theo số bài.
- Cả 0.5.0 lẫn 0.5.1 trước khi rà soát nạp **cả nội dung** của mọi bài tiếng Anh công khai trên mọi trang (bộ chuyển ngôn ngữ và hreflang của trang chủ cần biết đã có bài tiếng Anh nào chưa), nên 200 bài dài 20 KB tốn thêm khoảng **4,9 MB** bộ nhớ và dữ liệu chuyển từ cơ sở dữ liệu cho mỗi yêu cầu, kể cả trang tiếng Việt. Bản cuối chỉ lấy hai cột (`ID`, `post_name`) bằng một truy vấn có cache theo lần đổi bài cuối: thêm khoảng 0,3 MB. Đổi lại, một vài bài mà trang thực sự hiển thị được nạp khi cần chứ không phải nạp sẵn: +3 truy vấn nhỏ ở trang tiếng Việt so với bản trước (51 → 54 ở trang chủ), vẫn ít hơn 0.5.0 khoảng 196 truy vấn.
- Số liệu đo trên SQLite trong môi trường thử; dùng để so sánh giữa các bản, không phải thời gian trên hosting thật. Máy chủ tích hợp của PHP nhớ kết quả giải quyết liên kết tượng trưng, nên khi đổi bản plugin trong phòng thử phải khởi động lại máy chủ, nếu không bản cũ vẫn trả lời một lúc (lần đo đầu của dự án đã bị lệch vì điều này).
