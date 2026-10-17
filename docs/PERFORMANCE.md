# Hiệu năng

Mọi con số dưới đây đến từ các lần đo đã chạy thật bằng [tools/measure-scale.sh](../tools/measure-scale.sh) và [tests/bin/measure.mjs](../tests/bin/measure.mjs). Chúng mô tả **máy phát triển này** (máy chủ PHP tích hợp, 4 nhân dùng chung với các tiến trình kiểm thử khác, MariaDB), **không phải hosting thật** và **không phải dữ liệu Core Web Vitals thực tế**. Dùng chúng để so sánh các trang và các bản phát hành với nhau, không phải để hứa hẹn con số trên production.

## 1. Tài nguyên tĩnh (đo từ bản build hiện tại)

| Tài nguyên | Kích thước | Gzip | Cách nạp |
|---|---|---|---|
| CSS tới hạn `assets/css/critical.min.css` | 24.364 B | 5.800 B | Nội tuyến trong `<head>` (không chặn thêm yêu cầu) |
| CSS chính `assets/css/main.min.css` | 9.291 B | 2.744 B | `media="print"` + `onload` (không chặn render), có `<noscript>` |
| JavaScript `assets/js/theme.min.js` | 7.405 B | 2.758 B | Một file, `defer` |
| Font | 0 | 0 | Font hệ thống, không tải font nào |
| Thư viện, bên thứ ba | 0 | 0 | Không (có test kiểm tra) |

Trang chủ mẫu (1.000 bài, không ảnh): HTML 137 KB thô, 22 KB gzip (gồm CSS tới hạn, sprite biểu tượng SVG 16 KB và CSS nội tuyến của lõi WordPress: `global-styles` 12 KB do `theme.json`, cùng CSS của các khối đang dùng). Có 2 yêu cầu stylesheet (một là bản `noscript`), 1 script của theme, và các script nội tuyến của lõi WordPress (emoji, speculation rules). Theme không tắt các tính năng này của lõi.

Ảnh: theme không đăng ký kích thước ảnh mới; `srcset`/`sizes` của lõi; `width`/`height` lấy từ tệp đính kèm; đúng **một** ảnh `eager` + `fetchpriority=high` cho mỗi trang (ảnh đầu tiên có thật trong hàng đầu tiên), mọi ảnh khác `lazy`; avatar luôn `lazy`.

## 2. Quy mô: 1.000, 10.000 và 50.000 bài viết

Mỗi bảng là một lần chạy `tools/measure-scale.sh <số bài> <cổng> <thư mục WordPress>` (hoặc `tests/bin/measure.mjs` trên site đã có) trên site dựng mới: WordPress 7.1.3, PHP 8.3.6, máy chủ PHP tích hợp, 4 nhân, MariaDB 10.11 với bộ đệm 128 MB mặc định, plugin đồng hành bật, vùng widget để trống, 12 bài mỗi trang, 80 từ khác nhau trong toàn bộ dữ liệu mẫu. Mỗi trang được tải 3 lần: lần đầu là **lạnh** (cache mảnh của theme đã bị xóa), hai lần sau là **ấm** (giá trị tốt nhất). "Truy vấn" là số câu SQL của cả trang, "DB ms" là thời gian chúng chạy khi ấm.

### 2.1. 50.000 bài, tìm kiếm gốc của WordPress (chưa dựng chỉ mục)

| page | status | KB | cold ms | warm ms | cold queries | warm queries | warm db ms | peak MB |
|---|---|---|---|---|---|---|---|---|
| home | 200 | 109 | 200 | 109 | 52 | 52 | 44.2 | 5.1 |
| article (newest) | 200 | 90 | 130 | 43 | 47 | 38 | 11.5 | 4.9 |
| article (mid archive) | 200 | 90 | 124 | 35 | 51 | 42 | 5.3 | 4.9 |
| home page 2 | 200 | 86 | 53 | 46 | 26 | 26 | 19.8 | 4.9 |
| home page 50 | 200 | 86 | 124 | 44 | 26 | 26 | 19.3 | 4.9 |
| home last pages | 200 | 82 | 143 | 52 | 25 | 25 | 20.6 | 4.9 |
| hub category | 200 | 92 | 174 | 43 | 59 | 31 | 15.4 | 5 |
| hub category page 20 | 200 | 87 | 222 | 49 | 29 | 29 | 17.7 | 4.9 |
| child category | 200 | 88 | 46 | 34 | 33 | 31 | 7.5 | 4.9 |
| child category page 5 | 200 | 87 | 35 | 34 | 27 | 27 | 5.3 | 4.9 |
| tag | 200 | 86 | 66 | 39 | 34 | 34 | 9.4 | 4.9 |
| author | 200 | 87 | 131 | 56 | 32 | 29 | 15.2 | 4.9 |
| author page 100 | 200 | 88 | 181 | 38 | 30 | 30 | 11.6 | 4.9 |
| year archive | 200 | 86 | 82 | 54 | 26 | 26 | 29.3 | 5 |
| search common word | 200 | 89 | 4581 | 2664 | 33 | 33 | 2521.2 | 5 |
| search two words | 200 | 89 | 4274 | 3417 | 34 | 34 | 3366.5 | 5 |
| search page 5 | 200 | 89 | 2572 | 2598 | 33 | 33 | 2487.8 | 5 |
| search rare word | 200 | 72 | 5235 | 3702 | 17 | 17 | 3635.1 | 4.7 |
| 404 | 404 | 75 | 107 | 38 | 32 | 28 | 5 | 4.9 |

### 2.2. 50.000 bài, đã dựng chỉ mục tìm kiếm của plugin đồng hành (50.014 hàng)

| page | status | KB | cold ms | warm ms | cold queries | warm queries | warm db ms | peak MB |
|---|---|---|---|---|---|---|---|---|
| home | 200 | 109 | 89 | 78 | 52 | 52 | 44.9 | 5.1 |
| article (newest) | 200 | 91 | 169 | 53 | 47 | 38 | 22.9 | 4.9 |
| article (mid archive) | 200 | 91 | 414 | 37 | 51 | 42 | 6.5 | 4.9 |
| home page 2 | 200 | 86 | 74 | 75 | 26 | 26 | 27.6 | 4.9 |
| home page 50 | 200 | 86 | 50 | 113 | 26 | 26 | 50.9 | 4.9 |
| home last pages | 200 | 82 | 86 | 47 | 25 | 25 | 21.2 | 4.9 |
| hub category | 200 | 92 | 214 | 64 | 59 | 31 | 28.4 | 5 |
| hub category page 20 | 200 | 88 | 68 | 52 | 29 | 29 | 19.5 | 4.9 |
| child category | 200 | 88 | 493 | 50 | 33 | 31 | 11.1 | 4.9 |
| child category page 5 | 200 | 88 | 50 | 83 | 27 | 27 | 8.6 | 4.9 |
| tag | 200 | 87 | 122 | 54 | 34 | 34 | 15.7 | 4.9 |
| author | 200 | 88 | 66 | 33 | 32 | 29 | 5.9 | 4.9 |
| author page 100 | 200 | 88 | 863 | 193 | 30 | 30 | 36.8 | 4.9 |
| year archive | 200 | 87 | 209 | 53 | 26 | 26 | 21.2 | 5 |
| search common word | 200 | 89 | 3899 | 1525 | 33 | 33 | 1438.1 | 5 |
| search two words | 200 | 89 | 1876 | 1376 | 32 | 32 | 1314.6 | 5 |
| search page 5 | 200 | 90 | 1284 | 1174 | 32 | 32 | 1138.8 | 5 |
| search rare word | 200 | 72 | 109 | 35 | 17 | 17 | 8.8 | 4.7 |
| 404 | 404 | 75 | 52 | 25 | 32 | 28 | 3.7 | 4.9 |

### 2.3. 10.000 bài

| page | status | KB | cold ms | warm ms | cold queries | warm queries | warm db ms | peak MB |
|---|---|---|---|---|---|---|---|---|
| home | 200 | 108 | 84 | 69 | 52 | 52 | 19.9 | 5.1 |
| home page 2 | 200 | 85 | 44 | 42 | 26 | 26 | 12.7 | 4.9 |
| article (mid archive) | 200 | 90 | 76 | 69 | 47 | 38 | 12.8 | 4.9 |
| home page 50 | 200 | 86 | 63 | 40 | 26 | 26 | 11.9 | 4.9 |
| home last pages | 200 | 76 | 28 | 35 | 24 | 24 | 12.2 | 4.8 |
| hub category | 200 | 92 | 64 | 52 | 59 | 31 | 23.4 | 5 |
| hub category page 20 | 200 | 87 | 45 | 32 | 29 | 29 | 8.6 | 4.9 |
| child category | 200 | 88 | 45 | 37 | 33 | 31 | 5.6 | 4.9 |
| child category page 5 | 200 | 87 | 39 | 34 | 27 | 27 | 7.6 | 4.9 |
| tag | 200 | 86 | 65 | 38 | 34 | 34 | 14.7 | 4.9 |
| author | 200 | 87 | 36 | 35 | 32 | 29 | 10.2 | 4.9 |
| author page 100 | 200 | 88 | 42 | 35 | 30 | 30 | 11.2 | 4.9 |
| year archive | 200 | 86 | 38 | 30 | 27 | 27 | 6.9 | 5 |
| search common word | 200 | 88 | 200 | 95 | 33 | 33 | 70.1 | 5 |
| search two words | 200 | 89 | 342 | 391 | 34 | 34 | 355.1 | 5 |
| search page 5 | 200 | 89 | 94 | 92 | 33 | 33 | 62.5 | 5 |
| search rare word | 200 | 72 | 343 | 303 | 17 | 17 | 275.9 | 4.7 |
| 404 | 404 | 75 | 34 | 48 | 32 | 28 | 4.5 | 4.9 |

### 2.4. 1.000 bài

| page | status | KB | cold ms | warm ms | cold queries | warm queries | warm db ms | peak MB |
|---|---|---|---|---|---|---|---|---|
| home | 200 | 108 | 62 | 42 | 52 | 52 | 10.2 | 5.1 |
| home page 2 | 200 | 86 | 31 | 29 | 26 | 26 | 5.2 | 4.9 |
| article (mid archive) | 200 | 89 | 103 | 40 | 47 | 38 | 5.1 | 4.9 |
| home page 50 | 200 | 85 | 36 | 33 | 26 | 26 | 7.9 | 4.9 |
| home last pages | 200 | 76 | 30 | 67 | 23 | 23 | 40.4 | 4.8 |
| hub category | 200 | 91 | 61 | 33 | 59 | 31 | 7.7 | 5 |
| hub category page 20 | not present at this size | | | | | | | |
| child category | 200 | 86 | 37 | 37 | 33 | 31 | 11.9 | 4.9 |
| child category page 5 | not present at this size | | | | | | | |
| tag | 200 | 84 | 46 | 32 | 34 | 34 | 4.8 | 4.9 |
| author | 200 | 87 | 33 | 29 | 32 | 29 | 4.7 | 4.9 |
| author page 100 | not present at this size | | | | | | | |
| year archive | 200 | 86 | 70 | 59 | 27 | 27 | 9.9 | 5 |
| search common word | 200 | 89 | 41 | 35 | 33 | 33 | 9.3 | 5 |
| search two words | 200 | 88 | 61 | 61 | 36 | 36 | 34.7 | 5 |
| search page 5 | 200 | 89 | 92 | 40 | 33 | 33 | 16 | 5 |
| search rare word | 200 | 72 | 55 | 50 | 17 | 17 | 28.6 | 4.7 |
| 404 | 404 | 75 | 44 | 25 | 28 | 28 | 4.3 | 4.9 |

("Không có ở quy mô này" nghĩa là trang phân trang đó không tồn tại với số bài ít như vậy; không phải lỗi.)

## 3. Đọc kết quả

- **Trang do theme dựng gần như không đổi theo số bài.** Số truy vấn như nhau ở cả ba quy mô (23–52 khi cache ấm, tối đa 59 ở lần tải lạnh đầu tiên của trang hub). Thời gian CSDL của các trang không phải tìm kiếm tăng chậm: trang chủ 10 ms (1.000 bài), 20 ms (10.000), 44 ms (50.000), phần lớn là câu đếm `SQL_CALC_FOUND_ROWS` của danh sách chính; mọi trang khác không quá 51 ms ở 50.000 bài. Cả trang khi ấm: 25–67 ms (1.000 bài), 30–69 ms (10.000), 34–109 ms (50.000, lần chạy dùng tìm kiếm gốc) và 25–193 ms ở lần chạy thứ hai, ngay sau khi dựng chỉ mục. Mẫu đơn lẻ trên máy này dao động gấp hai đến ba lần: hãy so sánh bậc độ lớn, không so từng mili giây. Phân trang xa đã được đo ở trang cuối (trang 4.167).
- **Cache lạnh rẻ.** Trang đầu tiên sau khi nội dung đổi (cache mảnh trống) mất 35–222 ms ở 50.000 bài (lần chạy thứ hai có một mẫu 863 ms ở trang 100 của tác giả). Trước khi rà soát độc lập, cùng yêu cầu mất 5–6 giây ở trang chủ vì mỗi lần lưu bài đã xuất bản xóa cache các danh sách, và các truy vấn danh sách lọc `has_password` bắt CSDL đọc nguyên dòng bài viết (khoảng 2 s so với 0,1 s cho cùng truy vấn không lọc). Nay chỉ thay đổi ngày, mật khẩu, slug, tác giả, bài cha hay thứ tự mới làm mất hiệu lực, và danh sách loại bài có mật khẩu bằng một truy vấn theo khóa chính.
- **Tìm kiếm là điểm duy nhất tăng theo quy mô.** Tìm kiếm gốc của WordPress quét `post_title`/`post_excerpt`/`post_content` bằng `LIKE '%…%'`: 35–61 ms ở 1.000 bài, 92–391 ms ở 10.000 bài, **2,6–5,2 giây ở 50.000 bài** (lạnh và ấm). Với chỉ mục của plugin đồng hành, từ hiếm trả lời trong 35 ms; từ có mặt trong gần như mọi bài (dữ liệu mẫu chỉ có 80 từ khác nhau, nên đây là trường hợp xấu nhất nhân tạo) vẫn mất 1,2–1,5 giây vì phải ghép và xếp hạng mọi bài khớp. Dựng chỉ mục cho 50.014 bài mất 66 giây (một tiến trình WP-CLI, 200 bài mỗi lô).
- **Gợi ý khi gõ** tự tắt khi site có hơn 15.000 bài đã xuất bản mà chưa có chỉ mục (bộ lọc `nh_editorial_suggest_article_limit` của theme, 0 để tắt cơ chế này); khi chỉ mục đang phục vụ tìm kiếm thì gợi ý bật lại.
- **Trang quản trị của plugin:** so sánh chỉ mục với nội dung (hiển thị trong trạng thái ở thẻ Tools) mất khoảng 2,4 giây ở 50.000 bài vì phải đọc mọi bài; màn hình quản trị dùng lại kết quả trong năm phút, WP-CLI luôn so lại, và lượt xem trang của khách không bao giờ chạy nó.

## 4. Khuyến nghị cho website thật

- **Bật object cache bền vững** (Redis hoặc Memcached, nếu hosting cho phép). Cache mảnh của theme (bài liên quan, bài kế/trước, danh sách trang chủ, menu) dùng transient; không có object cache bền vững, mỗi transient là một hàng trong `wp_options`, tức là ghi CSDL khi cache được dựng lại và hai lần đọc khi dùng. Với object cache, cùng cache nằm trong bộ nhớ.
- **Dựng chỉ mục tìm kiếm** (Settings → Nguyen Ha SEO Companion → Tools hoặc `wp nh-seo reindex`) khi site có hàng chục nghìn bài.
- **Xóa các widget mặc định của WordPress** (Archives, Categories…) khỏi cột chân trang nếu không dùng: bảng 2.x ở trên chạy với vùng widget trống; trên site mới cài các khối này thêm vài truy vấn mỗi trang.
- **Page cache** (LiteSpeed Cache trên Hostinger) là lớp giảm tải lớn nhất cho khách: plugin đồng hành làm mới đúng các URL bị ảnh hưởng sau mỗi thay đổi nội dung (xem [COMPANION-PLUGIN.md](COMPANION-PLUGIN.md)).

## 5. Đo lại

```bash
export WP_CLI="php /đường/dẫn/wp-cli.phar"            # hoặc wp
# tạo CSDL trống và cấp quyền cho DB_USER (mặc định wp/wp), rồi:
bash tools/measure-scale.sh 1000  8061 /đường/dẫn/wordpress     # dựng site, nạp bài, đo, in bảng
bash tools/measure-scale.sh 10000 8062 /đường/dẫn/wordpress
RUNS=3 WITH_PLUGIN=1 bash tools/measure-scale.sh 50000 8063 /đường/dẫn/wordpress   # lâu: vài chục phút
# trên một site đã có: NH_LAST_PAGE=<trang cuối> NH_MID_POST=<ID một bài giữa> node tests/bin/measure.mjs http://127.0.0.1:8063 3
```

`tests/bin/mu-metrics.php` (mu-plugin chỉ dùng để đo, được script chép vào site thử) ghi thời gian và số truy vấn của máy chủ vào header phản hồi. Script từ chối mọi URL không phải cục bộ.

## 6. Giới hạn của các con số này

- Máy phát triển, máy chủ PHP tích hợp, 4 nhân dùng chung với các tiến trình khác: dùng để so sánh trang với trang và bản phát hành với bản phát hành, **không phải** dự báo cho hosting thật (Hostinger, LiteSpeed, OPcache, PHP-FPM).
- Không có dữ liệu Core Web Vitals thực tế (field data) và không chạy Lighthouse/INP; kích thước tài nguyên và số yêu cầu là số đo thật, điểm số thì không.
- Không đo tải đồng thời (nhiều khách cùng lúc) và không đo hiệu năng quản trị trừ trạng thái chỉ mục ở trên.
- Dữ liệu mẫu là nhân tạo (80 từ, mỗi bài ngắn): nó cho đúng số truy vấn và đúng độ phức tạp của truy vấn, nhưng không cho đúng kích thước bài viết thật (bài thật dài hơn, nên tìm kiếm gốc chậm hơn).

