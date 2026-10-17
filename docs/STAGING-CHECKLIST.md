# Danh sách kiểm tra trước khi kích hoạt (staging / xem trước)

Dùng danh sách này trên bản sao staging, hoặc trên production **khi theme mới đã được cài nhưng chưa kích hoạt**. Mỗi mục có tiêu chí đạt/không đạt. Đừng kích hoạt khi còn mục chưa đạt.

> Website đích: `https://blog.rentbikehanoi.com/` (WordPress 7.1.3, Yoast SEO 28.7, theme hiện tại Nguyễn Hà Glass 2026). Một website khác trên cùng hosting (`thuexemaynguyentu.com`, theme Flatsome Child) **không liên quan và không được chạm tới**.

## A. Chuẩn bị

- [ ] Có sao lưu **mới** (CSDL + `wp-content`) tạo từ hPanel, đã xác nhận mở được; đã làm các bước 0.3 và 0.4 của [ROLLBACK.md](ROLLBACK.md).
- [ ] Theme Glass còn nguyên trong `wp-content/themes/`.
- [ ] Cài theme (ZIP hoặc Deployer, xem [DEPLOYMENT.md](DEPLOYMENT.md)) và, nếu muốn, plugin đồng hành; **chưa kích hoạt**.
- [ ] Chạy báo cáo chỉ-đọc `tools/staging-preflight.php` (cần repository hoặc chép riêng tệp này lên máy chủ; nó không ghi gì và không kích hoạt gì) và **lưu kết quả**:
  ```bash
  wp eval-file tools/staging-preflight.php content headline="<H1 đã duyệt>" lead="<đoạn dẫn đã duyệt>"
  ```
  Tham số viết không có dấu gạch (WP-CLI từ chối `--tùy-chọn` lạ). Báo cáo kiểm tra phiên bản WordPress/PHP, theme đã cài đúng chưa, plugin SEO đang sở hữu SEO, cấu trúc danh mục, H1 so với văn bản đã duyệt, và in **dấu vân tay** của bài viết, trang, danh mục, gán danh mục, các tùy chọn được bảo vệ và quy tắc rewrite. Chạy lại **ngay sau khi kích hoạt** và **sau khi rollback**: các dấu vân tay phải giống hệt (nếu lệch, có thứ đã bị đổi). Mã thoát khác 0 khi có mục `FAIL`.
- [ ] Snippet WPCode **#1192 "Homepage SEO Lock"** đang bật (nó khóa các tùy chọn `blogname`, `blogdescription`, `show_on_front`, `page_on_front`, `page_for_posts` và tiêu đề/mô tả/canonical trang chủ của Yoast). Theme này không ghi vào các tùy chọn đó và không in các thẻ đó.

## B. Cấu hình bắt buộc trước khi kích hoạt (khớp các giá trị đang được khóa)

1. **H1 và lead trang chủ.** Theme lấy H1 từ *Appearance → Customize → Homepage → Homepage headline* và đoạn dẫn từ *Homepage introduction*; nếu để trống, chúng là tên site và khẩu hiệu. Baseline được bảo vệ (`data/seo/homepage-protection-baseline-20261017.json` của repository `wp-aiwriter`) là:
   - H1: `Thuê Xe Máy Hà Nội – Bảng Giá, Thủ Tục & Kinh Nghiệm`
   - Lead: `Tìm hiểu các dòng xe số, xe ga, xe 50cc và xe điện. Tham khảo giá thuê theo ngày, tuần, tháng cùng hướng dẫn đặt cọc, giấy tờ và nhận xe tại Hà Nội.`

   Để không có khoảng trống giữa lúc kích hoạt và lúc nhập, đặt trước (thay `nguyen-ha-editorial` bằng tên thư mục thật của theme):
   ```bash
   wp option update theme_mods_nguyen-ha-editorial \
     '{"nh_home_headline":"Thuê Xe Máy Hà Nội – Bảng Giá, Thủ Tục & Kinh Nghiệm","nh_home_subline":"Tìm hiểu các dòng xe số, xe ga, xe 50cc và xe điện. Tham khảo giá thuê theo ngày, tuần, tháng cùng hướng dẫn đặt cọc, giấy tờ và nhận xe tại Hà Nội."}' --format=json
   ```
   Test `test-homepage-lock.php` chứng minh hai thiết lập này tái tạo đúng từng ký tự (gạch ngang dài, dấu `&`, chữ hoa) và trang chủ có đúng một H1. Đạt khi: H1 hiển thị bằng đúng văn bản baseline.
2. **Menu.** Gán menu cho vị trí **Primary Navigation** và **Footer Navigation** (*Appearance → Menus*). Gán menu của theme cũ không tự động mang sang. Nếu chưa gán, theme dựng menu chính từ cây danh mục (3 hub, mỗi hub có panel con) để site vẫn có điều hướng cha/con.
3. **Widget.** Theme có 1 vùng bên bài viết (`article-sidebar`, giữ id cũ) và 4 cột chân trang. Cột chân trang trống sẽ hiện nội dung mặc định (thương hiệu + mạng xã hội, menu chân trang, chủ đề phổ biến, thông tin liên hệ nếu có). Trên site mới cài, WordPress xếp widget mặc định (Archives, Categories…) vào hai vùng đầu: hãy xóa chúng nếu không muốn.
4. **Pillar.** Với mỗi danh mục hub (*Dịch vụ*, *Khu vực*, *Cẩm nang*, và các danh mục con nếu muốn), chọn **trang pillar** ở trường "Pillar page" của plugin. Theme không tự đoán theo slug nên không tạo liên kết nội bộ hàng loạt ngoài ý muốn.
5. **Plugin đồng hành (tùy chọn).** Nếu cài: *Settings → Nguyen Ha SEO Companion*. Chế độ schema phải hiển thị **extras (đã phát hiện plugin SEO)**; chỉ điền hồ sơ doanh nghiệp bằng dữ liệu **chủ sở hữu xác nhận** (không có giá trị mặc định nào được điền sẵn). Nếu muốn bật chỉ mục tìm kiếm, bấm dựng chỉ mục (xem COMPANION-PLUGIN.md); trước đó tìm kiếm là tìm kiếm gốc của WordPress.

6. **Menu chính và điểm chuyển sang menu ngăn kéo.** Menu đầy đủ chỉ hiện khi cửa sổ rộng hơn nội dung (1180 px); hẹp hơn là nút menu. Giá trị này nằm ở hai nơi phải sửa cùng nhau: `assets/src/css/03-header.css` (media query) và `assets/src/js/navigation.js` (`MOBILE`), rồi `npm run build`. Với menu thật của site, xem header ở 1024, 1280 và 1440 px: nếu menu dài hơn bốn mục mà xuống dòng hoặc tên site bị cắt, nâng điểm chuyển hoặc rút gọn menu. Bộ e2e chỉ thử với menu mẫu bốn mục.
7. **Bài viết đặt mật khẩu.** WordPress liệt kê chúng trong trang chủ, danh mục và tìm kiếm với tiền tố "Protected:" (hành vi mặc định của lõi). Theme **không** ẩn chúng khỏi các danh sách chính, vì lọc `has_password` trên truy vấn danh mục làm chậm trang (đo trên 50.000 bài: khoảng 2 s so với 0,1 s); các thành phần do theme dựng (nổi bật, liên quan, bài trước/sau, trang chủ) đã loại chúng bằng cách rẻ hơn. Nếu không muốn tiêu đề các bài đó xuất hiện công khai, chuyển chúng sang trạng thái *Riêng tư* thay vì đặt mật khẩu. Bộ e2e chạy với `NH_ALLOW_PROTECTED_LINKS=1` để báo cáo (không coi là lỗi) các liên kết đó.

## C. Đối chiếu SEO (trên staging hoặc bằng cách xem trước)

Dùng baseline 525 trang trong `data/seo/protection-baseline.json` (repository `wp-aiwriter`). Với trang chủ và một mẫu đủ rộng (mỗi danh mục một bài, mỗi trang tĩnh), kiểm tra:

- [ ] HTTP 200 (trang 404 thật trả 404), không chuyển hướng mới, URL/slug không đổi.
- [ ] Đúng **một** `<link rel="canonical">`, bằng giá trị baseline.
- [ ] `robots` meta đúng như baseline (`index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`); không có `noindex` mới.
- [ ] Một khối `application/ld+json` là đồ thị của Yoast, với các kiểu giống baseline: `Article, BreadcrumbList, ImageObject, Organization, Person, WebPage, WebSite` (+ `SearchAction`). Không `Organization`, `WebSite` hay `BreadcrumbList` thứ hai. Theme không in JSON-LD nào. Plugin đồng hành (chế độ *extras*, tự chọn khi phát hiện Yoast) chỉ thêm một khối **riêng** `FAQPage` cho bài có FAQ hiển thị trên trang (và `LocalBusiness` chỉ khi chủ sở hữu bật và hồ sơ đầy đủ); bài do workflow AI Writer đăng không có FAQ nên không có khối thêm nào.
- [ ] `<title>` và meta description của trang chủ và một số bài đúng như trước.
- [ ] H1 mỗi trang là đúng một phần tử, văn bản giống baseline.
- [ ] Sitemap và `robots.txt` không đổi.
- [ ] Chạy `python scripts/seo_protection.py --mode check` của repository `wp-aiwriter` sau khi kích hoạt trên staging: không có phát hiện mức `critical` hoặc `high`.

## D. Kiểm tra giao diện và truy cập

- [ ] Trang chủ, một bài viết dài có bảng, một bài có H2/H3, danh mục cha (3 cấp), danh mục lá, tìm kiếm có kết quả và không có kết quả, 404, từng trang trong 18 trang tĩnh (đặc biệt trang từng dùng template/shortcode riêng của Glass), trang tác giả.
- [ ] Điện thoại (375 px), máy tính bảng (768 px), máy tính (1440 px): không cuộn ngang, menu mở/đóng bằng bàn phím và chạm, chế độ tối, mục lục, FAQ.
- [ ] Tìm kiếm không có từ khóa (`/?s=`) hiện lời nhắc và các chủ đề, không liệt kê mọi bài viết.
- [ ] Bộ e2e (`npm run test:e2e` với `NH_BASE_URL` trỏ tới staging) xanh; xem [TESTING.md](TESTING.md).
- [ ] PageSpeed/Lighthouse của staging (chỉ là số đo phòng thí nghiệm; chưa có dữ liệu Core Web Vitals thực tế của theme này).

## E. Kích hoạt và theo dõi

- [ ] Kích hoạt trong cửa sổ ngắn; kiểm tra lại mục B.1 và C ngay lập tức trên production, và chạy lại `tools/staging-preflight.php` (dấu vân tay phải bằng lần chạy trước khi kích hoạt).
- [ ] Theo dõi 24–48 giờ: Search Console (lập chỉ mục, Core Web Vitals thực tế), lần chạy `seo_protection.py` hằng ngày của `wp-aiwriter`, nhật ký lỗi PHP.
- [ ] Nếu có bất thường: [ROLLBACK.md](ROLLBACK.md).
