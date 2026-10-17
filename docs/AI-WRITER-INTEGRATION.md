# Tích hợp với workflow viết bài (wp-aiwriter)

Workflow viết bài nằm ở repository riêng `thuexemayhanoi/wp-aiwriter`. Repository này **không sửa** nó và không can thiệp vào QA hay publish. Tài liệu này ghi lại điều đã đọc từ mã nguồn của nó (chế độ chỉ đọc, ngày 17/10/2026) và điều theme/plugin đảm bảo.

## 1. Publisher làm gì

`scripts/engine.py` tạo bài bằng WordPress REST API (`POST /wp/v2/posts`) với đúng các trường sau và không gì khác:

```json
{ "title": "…", "slug": "excel-<id>-<slug>", "status": "publish | draft",
  "content": "<!-- wp-aiwriter:matrix_id=…;sha256=… -->\n<HTML từ Markdown>",
  "categories": [<một ID danh mục lá>] }
```

- Trạng thái `draft` được dùng khi chuỗi danh mục không khớp hoàn toàn; không bao giờ ghi đè, xóa, khôi phục hay chạm vào Thùng rác.
- Sau mỗi POST nó đọc lại bài (private rồi public) và mở permalink công khai để kiểm tra tiêu đề/nội dung hiển thị (`verify()`), rồi ghi biên nhận.
- Nó **không gửi meta** (`nh_summary`, `nh_faq`…), nên toàn bộ phần "biên tập thêm" của theme là tùy chọn và theme phải đẹp với bài thường.

Ba hệ thống SEO chỉ đọc của repository đó (`seo_audit.py`, `link_matrix.py`, `seo_protection.py`) so sánh site thật với một baseline: URL/slug, canonical, `noindex`, tiêu đề, danh mục, **các kiểu JSON-LD**, H1, `robots.txt`, sitemap, số từ của `content.rendered` qua REST. Baseline hiện tại (525 trang) cho thấy site đang xuất Yoast SEO: `Article, BreadcrumbList, ImageObject, Organization, Person, WebPage, WebSite (+SearchAction)`, robots `index, follow, max-image-preview:large, …`, permalink dạng `/<slug>/`.

## 2. Điều theme và plugin đảm bảo cho luồng đó

| Yêu cầu | Cách đáp ứng | Bằng chứng |
|---|---|---|
| Bài đúng danh mục, không thêm/bớt danh mục | Theme/plugin không bao giờ ghi term `category`; cluster dùng taxonomy riêng `topic_cluster` | `test-publish-flow.php` (REST trả đúng `categories`) |
| Readback công khai nhìn thấy đúng nội dung đã gửi | Theme/plugin không đổi `content.rendered` qua REST: bộ lọc bao bảng chỉ chạy ở trang đơn front-end, phần dự phòng của plugin tắt khi theme khai báo hỗ trợ | Test so sánh byte-by-byte với kết quả của lõi WordPress |
| Permalink hiện tiêu đề và nội dung, một H1 | `single.php` | `test-publish-flow.php`, `test-templates-structure.php` |
| Hub/category, trang chủ tự cập nhật | Cache có phiên bản bị vô hiệu hóa **ở cuối chính request REST** | `test-publish-flow.php` |
| Bài liên quan, bài kế/trước, tìm kiếm nhận bài mới | Tính lại sau khi phiên bản cache đổi; tìm kiếm gốc thấy ngay; chỉ mục của plugin được cập nhật khi đăng (xem COMPANION-PLUGIN.md) | Như trên |
| Bản nháp (danh mục không khớp) vô hình | Mọi truy vấn của theme chỉ lấy `publish`; REST công khai trả 401/403/404 cho bản nháp | Như trên |
| Không đổi URL/slug/canonical/robots/schema đang chạy | Theme không in bất kỳ thẻ SEO nào; plugin ở chế độ *extras* khi phát hiện Yoast; theme khai báo `yoast-seo-breadcrumbs` để Yoast giữ `BreadcrumbList` dù tùy chọn breadcrumb của Yoast tắt | `test-templates-structure.php` (không meta/canonical/JSON-LD từ theme), test plugin về chế độ schema |
| Không tự noindex | Không có mã nào phát `noindex`; trang tìm kiếm do lõi/Yoast xử lý | Quét mã nguồn + test |

## 3. Nếu sau này publisher gửi meta

Hợp đồng nằm ở [DATA-CONTRACT.md](DATA-CONTRACT.md). Payload mẫu (mọi khóa `meta` đều đã đăng ký REST, được làm sạch ở plugin và xác thực lại ở theme):

```json
{ "meta": { "nh_summary": "Trả lời một câu.",
            "nh_takeaways": ["Ý chính 1", "Ý chính 2"],
            "nh_faq": [{ "question": "Câu hỏi?", "answer": "<p>Câu trả lời.</p>" }],
            "nh_sources": [{ "title": "Nguồn", "url": "https://…" }],
            "nh_content_updated": "2026-10-17",
            "nh_topic_cluster": "Thuê xe số" } }
```
Ngày cập nhật không được sau hôm nay (múi giờ site); `nh_faq` chỉ thành dữ liệu có cấu trúc khi chính các câu hỏi đó hiển thị trên trang; khóa lạ bị từ chối.

Đọc lại qua REST: tài khoản có quyền sửa bài (Application Password của workflow) thấy mọi khóa `nh_*`; khách chỉ thấy các khóa công khai của bài đã xuất bản (không có `nh_keywords`, là ghi chú làm việc của biên tập viên) và **không thấy khóa nào** của bài đặt mật khẩu trừ khi gửi đúng mật khẩu (xem [DATA-CONTRACT.md](DATA-CONTRACT.md) mục 1).

## 4. Tác động khi kích hoạt theme mới lên các công cụ SEO của repo đó (cần theo dõi)

Khi chủ sở hữu kích hoạt theme (không thuộc nhiệm vụ này), lần chạy `seo_protection.py --mode check` đầu tiên có thể báo:

- `h1_changed` (low) nếu H1 hiện tại khác tiêu đề bài;
- `dynamic_content_changed` (info) vì HTML trang đổi dù `modified` không đổi — không có số từ thay đổi vì `content.rendered` không đổi;
- mất kiểu JSON-LD **chỉ khi** theme cũ in thêm JSON-LD ngoài Yoast: theme mới không in gì, đồ thị Yoast giữ nguyên.

Đối chiếu bằng baseline sau khi xem trước trên staging (xem [STAGING-CHECKLIST.md](STAGING-CHECKLIST.md)); duyệt thay đổi có chủ đích bằng `--accept … --reason …` theo hướng dẫn của repository đó.
