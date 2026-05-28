# Vnstock News - Hướng Dẫn Toàn Diện

## Giới Thiệu

**vnstock_news** là thư viện Python cung cấp khả năng thu thập tin tức từ các trang báo Việt Nam bằng hai phương pháp chính:

1. **RSS Feed Aggregation** - Kết nối trực tiếp đến các luồng RSS của báo
2. **Web Scraping via Sitemap** - Sử dụng sitemap (bản đồ chỉ mục web) để duyệt và lấy toàn bộ bài viết

**Hầu hết các báo đều hỗ trợ cả RSS và Sitemap** vì đây là tiêu chuẩn web. vnstock_news cung cấp cấu hình sẵn nhưng bạn có thể tùy biến cho bất kỳ website nào có RSS/sitemap ví dụ để phân tích website đối thủ hoặc tối ưu SEO của chính mình.

### Đặc Điểm Chính

- **📰 Hỗ trợ 21 trang báo**: VnExpress, Tuổi Trẻ, CafeF, Dân Trí, Thanh Niên, Znews, Tiền Phong, v.v.
- **🌐 Hai phương thức lấy dữ liệu**: RSS (cập nhật mới nhất) + Sitemap (lịch sử toàn bộ)
- **⚡ Hỗ trợ bất đồng bộ (async)**: Tải nhiều bài cùng lúc nhanh chóng
- **💾 Caching thông minh**: Lưu cache để tránh tải lại cùng một bài
- **🧹 Cleaning & Validation**: Tự động làm sạch, chuẩn hóa nội dung loại bỏ link rác/media tự động.
- **📊 Phân tích xu hướng**: Tìm ra chủ đề/keyword phổ biến từ tin tức
- **🔄 Trình Cào Hợp Nhất**: Xử lý tự động chuyển đổi thông minh giữa RSS và Sitemap nếu web lỗi.

### ✨ Điểm Mới Trong Phiên Bản 2.2.0 (Tháng 04/2026)

- Nhận diện và trích xuất thông minh cấu trúc Tags.
- Cấu hình linh hoạt sử dụng cơ chế **Fallback Selectors Array**: tự động thử nhiều bộ quy tắc CSS khác nhau để chống lại việc thay đổi layout giữa các chuyên mục.
- Tích hợp động cơ **JS DataLayer Extraction**: có khả năng luồn sâu vào JSON Script để bóc tách siêu dữ liệu.
- Crawler hợp nhất xử lý fallback RSS/Sitemap mượt mà, tự động bỏ qua các link phân trang, ảnh và video không phải bài viết.
- Bộ giải mã thời gian chuẩn hoá mọi định dạng thời gian về ISO format (VD: "15 phút trước" -> YYYY-MM-DD HH:MM:SS), vá lỗi NaT gây lỗi parser ở các version cũ.
- Cập nhật, phục hồi kết nối thành công 100% tỷ lệ cào, fix hoàn toàn tình trạng thiếu hụt siêu dữ liệu cho hàng loạt báo (Dân Trí, Tiền Phong, NLD, Thanh Niên, Znews, VietNamNet...) và bỏ qua lỗi SSL khi gặp trang cũ.

### Cấu Trúc Package

```
vnstock_news/
├── __init__.py           # Khởi tạo, export main classes
├── main.py              # Module monitoring tin tức tự động
├── api/
│   └── enhanced.py      # EnhancedNewsCrawler - API trong gói tài trợ
├── core/                # Core modules
│   ├── base.py          # BaseParser - base class
│   ├── crawler.py       # Crawler - unified crawler
│   ├── batch.py         # BatchCrawler - đồng bộ batch
│   ├── rss.py           # RSS parser
│   ├── sitemap.py       # Sitemap parser
│   └── news.py          # News article parser
├── async_crawlers/      # Async implementations
│   └── async_batch.py   # AsyncBatchCrawler - bất đồng bộ batch
├── config/              # Cấu hình
│   ├── sites.py         # SITES_CONFIG - config cho tất cả báo
│   ├── sitemap_resolver.py  # Dynamic sitemap URL resolution
│   ├── dynamic_config.py    # Cấu hình động
│   ├── const.py         # Constants
│   └── vietnamese-stopwords.txt  # Vietnamese stopwords
├── trending/            # Phân tích xu hướng
│   └── analyzer.py      # TrendingAnalyzer
└── utils/               # Utilities
    ├── cache.py         # Caching system
    ├── cleaner.py       # ContentCleaner
    ├── validators.py    # Validation
    └── helpers.py       # Helper functions
```

---

## Các Loại Dữ Liệu Có Thể Lấy

### 1. **RSS Feed Data** (Tin Mới Nhất)

- ✅ Nhanh nhất, thường cập nhật hàng giờ
- ✅ Bài viết mới nhất từ báo
- ❌ Chỉ lấy được những bài mới trong vài ngày
- 💡 Dùng cho: Monitoring tin tức real-time, cập nhật hàng ngày

**Ví dụ**: RSS feed của VnExpress cập nhật tin mới nhất mỗi giờ

### 2. **Sitemap Data** (Lịch Sử - Năm nay, Năm ngoái)

- ✅ Lấy được toàn bộ bài viết có trong sitemap (thường là 1-2 năm gần nhất)
- ✅ Nhiều báo sắp xếp sitemap theo tháng/năm (ví dụ: `news-2024-12.xml`, `news-2025-01.xml`)
- ✅ Có thể lấy hàng ngàn bài cũ từ một nguồn
- ❌ Chậm hơn RSS, cần curl từng bài
- 💡 Dùng cho: Xây dựng database tin tức lịch sử, phân tích dài hạn

**Ví dụ**: Báo PLO có sitemap chia theo tháng `https://plo.vn/sitemaps/news-2024-12.xml`

**Tất cả các báo đều hỗ trợ cả RSS và Sitemap** vì đây là tiêu chuẩn web. Bạn có thể dùng RSS để lấy tin mới nhất hoặc Sitemap để lấy lịch sử.

### 3. **Dynamic Sitemap** (Sitemap Tự Động Cập Nhật)

- Một số báo như **PLO**, **Tuổi Trẻ** có sitemap thay đổi theo tháng/năm
- Thư viện tự động phát hiện tháng hiện tại và gọi đúng sitemap
- Ví dụ: Tháng 1/2025 → `news-2025-01.xml`

---

## Các Trang Báo Được Hỗ Trợ

| STT | Tên Báo                | Tên Config        | Loại Hình | RSS | Sitemap | Mô Tả/Ghi Chú                     |
| --- | ---------------------- | ----------------- | --------- | --- | ------- | --------------------------------- |
| 1   | **Nhân Dân**           | nhandan           | Cơ quan TW | ✅   | ✅       | Cơ quan trung ương của Đảng Cộng Sản Việt Nam |
| 2   | **Tiền Phong**         | tienphong         | Cơ quan TW | ✅   | ✅       | Cơ quan trung ương của Đoàn TNCS Hồ Chí Minh |
| 3   | **VietNamNet**         | vietnamnet        | Bộ Ngành  | ✅   | ✅       | Cơ quan chủ quản Bộ Dân Tộc và Tôn Giáo |
| 4   | **Dân Trí**            | dantri            | Bộ Ngành  | ✅   | ✅       | Cơ quan của Bộ Nội vụ |
| 5   | **VnExpress**          | vnexpress         | Bộ Ngành  | ✅   | ✅       | Thuộc Bộ Khoa học và Công nghệ |
| 6   | **Báo Đầu Tư**         | baodautu          | Bộ Ngành  | ✅   | ✅       | Thuộc Bộ Tài chính |
| 7   | **Thời Báo Tài Chính** | thoibaotaichinhvietnam | Bộ Ngành | ✅   | ✅       | Báo điện tử thuộc Bộ Tài Chính |
| 8   | **Thanh Niên**         | thanhnien         | Tổ chức TW | ✅   | ✅       | Diễn đàn của Hội LHTN Việt Nam |
| 9   | **Tuổi Trẻ**           | tuoitre           | Địa phương | ✅   | ✅       | Cơ quan báo của Thành Đoàn TP.HCM |
| 10  | **Người Lao Động**     | nld               | Địa phương | ✅   | ✅       | Quản lý bởi Thành ủy TP.HCM |
| 11  | **Pháp Luật TP.HCM**   | plo               | Địa phương | ✅   | ✅       | Cơ quan chủ quản: UBND TP.HCM |
| 12  | **Kinh Tế Sài Gòn**    | ktsg              | Địa phương | ✅   | ✅       | Tạp chí Kinh tế Sài Gòn của UBND Tp. HCM |
| 13  | **VnEconomy**          | vneconomy         | Chuyên ngành| ✅   | ✅       | Tạp chí của Hội Khoa học Kinh tế Việt Nam |
| 14  | **Diễn Đàn Doanh Nghiệp**| dddn            | Chuyên ngành| ✅   | ✅       | Cơ quan của Liên đoàn Thương mại và Công nghiệp VN |
| 15  | **PetroTimes**         | petrotimes        | Chuyên ngành| ✅   | ✅       | Tạp chí của Hội Dầu khí Việt Nam |
| 16  | **Znews (Tri thức)**   | znews             | Chuyên ngành| ✅   | ✅       | Tạp chí điện tử của Hội Xuất bản Việt Nam |
| 17  | **CafeF**              | cafef             | Trang tin | ✅   | ✅       | Trang thông tin điện tử của khối VCCorp |
| 18  | **CafeBiz**            | cafebiz           | Trang tin | ✅   | ✅       | Trang thông tin điện tử của khối VCCorp |
| 19  | **VietStock**          | vietstock         | Trang tin | ✅   | ✅       | Cổng thông tin Tài chính, Chứng khoán (CTCP Tài Việt) |
| 20  | **24h**                | 24h               | Tổng hợp  | ✅   | ✅       | Trang tin điện tử tổng hợp 24h |
| 21  | **Người Quan Sát**     | nguoiquansat      | Tổng hợp  | ✅   | ✅       | Trang TTĐTTH của báo Đầu tư đổi mới INTECH |

---

## Phương Thức Thu Thập Dữ Liệu

### 1. **RSS Parser** - Lấy từ RSS Feed

```python
from vnstock_news import Crawler
import pandas as pd

crawler = Crawler(site_name="vnexpress")
articles = crawler.get_articles_from_feed(limit_per_feed=20)  # Returns List[Dict]

# Convert to DataFrame nếu cần
df = pd.DataFrame(articles)
print(df.head())
```

**Ưu điểm**: Nhanh, dễ, cập nhật liên tục  
**Nhược điểm**: Chỉ lấy được bài mới nhất (thường 1-2 tuần)

### 2. **Sitemap Parser** - Lấy từ Sitemap XML

```python
from vnstock_news import Crawler
import pandas as pd

crawler = Crawler(site_name="cafef")
# get_articles() will use sitemap as fallback if RSS not available
articles = crawler.get_articles(limit=100)  # Returns List[Dict]

# Convert to DataFrame
df = pd.DataFrame(articles)
print(df.head())
```

**Ưu điểm**: Lấy được lịch sử nhiều tháng/năm  
**Nhược điểm**: Chậm hơn (phải duyệt từng bài)

### 3. **Batch Crawler** - Lấy Hàng Loạt (Đồng Bộ)

```python
from vnstock_news import BatchCrawler

crawler = BatchCrawler(site_name="cafef", debug=False)
articles = crawler.fetch_articles(limit=500)
# Tải 500 bài, tự động lưu vào file
```

**Ưu điểm**: Đơn giản, có resume nếu bị lỗi  
**Nhược điểm**: Chậm vì chạy từng bài một

### 4. **Async Batch Crawler** - Lấy Hàng Loạt (Bất Đồng Bộ)

```python
import asyncio
from vnstock_news import AsyncBatchCrawler

async def main():
    crawler = AsyncBatchCrawler(site_name="cafef")
    articles = await crawler.fetch_articles_async(
        sources=["https://cafef.vn/latest-news-sitemap.xml"],
        top_n=500
    )

asyncio.run(main())
# Tải 500 bài nhanh hơn gấp 10 lần
```

**Ưu điểm**: Nhanh, concurrent requests  
**Nhược điểm**: Phức tạp hơn, cần async/await

### 5. **EnhancedNewsCrawler**

```python
from vnstock_news import EnhancedNewsCrawler

crawler = EnhancedNewsCrawler(
    cache_enabled=True,
    cache_ttl=3600
)

articles = await crawler.fetch_articles_async(
    sources=["https://cafef.vn/latest-news-sitemap.xml"],
    site_name="cafef",
    max_articles=100,
    time_frame="7d",
    clean_content=True
)
# Đầy đủ tính năng: cache, cleaning, validation
```

---

## Output Data Structure

Các phương thức trả về **List[Dict]** hoặc **DataFrame** tùy theo crawler:

- `Crawler.get_articles_from_feed()` → **List[Dict]**
- `Crawler.get_articles()` → **List[Dict]**
- `BatchCrawler.fetch_articles()` → **DataFrame**
- `AsyncBatchCrawler.fetch_articles_async()` → **DataFrame**

Các cột dữ liệu:

| Cột                 | Kiểu     | Mô Tả                 |
| ------------------- | -------- | --------------------- |
| `url`               | string   | URL bài viết          |
| `title`             | string   | Tiêu đề               |
| `short_description` | string   | Tóm tắt ngắn/Sapo     |
| `content`           | string   | Nội dung bài viết     |
| `publish_time`      | datetime | Thời gian đăng        |
| `author`            | string   | Tác giả               |
| `category`          | string   | Chuyên mục            |
| `tags`              | string   | Từ khóa/Tags (nếu có) |
| `view_counts`       | integer  | Lượt xem (nếu có)     |
| `image_url`         | string   | URL hình ảnh (nếu có) |
| `source`            | string   | Tên config của báo    |

### Ví dụ Output:

```python
# Từ List[Dict]
articles = [{'url': 'https://cafef.vn/...', 'title': 'Thị trường chứng khoán...', ...}, ...]

# Convert to DataFrame
df = pd.DataFrame(articles)
print(df.head())
```

```
              url                   title  short_description  publish_time        source
0  https://cafef.vn/...  Thị trường chứng...  Dự báo tăng từ...  2025-01-15 10:30    cafef
1  https://cafef.vn/...  VN-Index chiếm...  Nhà đầu tư lo...  2025-01-14 09:15    cafef
```

---

## Cài Đặt & Import

### Cài Đặt Thư Viện

Các gói thư viện vnstock_data được cài đặt **chung** thông qua chương trình cài đặt của Vnstock. Để cài đặt và kích hoạt vnstock_data, vui lòng tham khảo hướng dẫn chi tiết tại:

**🔗 [Hướng Dẫn Cài Đặt Vnstock](https://vnstocks.com/onboard-member)**

Sau khi hoàn thành cài đặt, bạn có thể bắt đầu sử dụng các module trong vnstock_data ngay lập tức.

### Import

```python
# Cách 1: Import từ package chính
from vnstock_news import (
    Crawler,
    BatchCrawler,
    AsyncBatchCrawler,
    EnhancedNewsCrawler,
    SITES_CONFIG
)

# Cách 2: Import từ module riêng
from vnstock_news.core.crawler import Crawler
from vnstock_news.core.batch import BatchCrawler
from vnstock_news.api.enhanced import EnhancedNewsCrawler
from vnstock_news.config.sites import SITES_CONFIG
```

---

## Yêu Cầu & Giới Hạn

### Yêu Cầu Hệ Thống

- **Python**: 3.10+
- **Libraries**: requests, pandas, beautifulsoup4, feedparser, aiohttp

### Giới Hạn & Lưu Ý Quan Trọng

⚠️ **NGƯỜI DÙNG TỰ CHỊU TRÁCH NHIỆM** với các vấn đề sau:

1. **Bản Quyền & Thuê Bao**
   - Nội dung báo có liên quan đến vấn đề bản quyền khi sử dụng lại
   - Chỉ lấy để học tập, nghiên cứu cá nhân
   - Không lấy để tái xuất bản, thương mại hoá

2. **Rate Limiting & Block IP**
   - Tránh gửi quá nhiều request (mỗi báo có giới hạn)
   - Thêm delay giữa các request: `request_delay=2.0`
   - Lưu ý việc lạm dụng có thể bị chặn IP hoặc rủi ro pháp lý

3. **Terms of Service**
   - Đọc kỹ ToS của trang báo trước khi lấy dữ liệu
   - Một số báo cấm crawling trong ToS của họ

4. **Robot.txt & Sitemap.xml**
   - Hãy kiểm tra `/robots.txt` của báo
   - Sitemap thường được phép truy cập

---

## Cấu Trúc Tài Liệu

1. **01-overview.md** (Tệp này) - Giới thiệu, cấu trúc, cách sử dụng cơ bản
2. **02-crawlers.md** - Chi tiết các crawler (Crawler, Batch, Async, Enhanced)
3. **03-sitemap-rss-guide.md** - Hướng dẫn tìm và thiết lập Sitemap/RSS
4. **04-trending-analysis.md** - Phân tích xu hướng, keyword từ tin tức
5. **05-best-practices.md** - Best practices, tips, xử lý lỗi
6. **README.md** - Hướng dẫn tổng quan và navigation

---

## Quickstart - Bắt Đầu Nhanh

Để nhanh chóng áp dụng vào ứng dụng thực tế, chúng tôi chia rẽ ròi **5 Use Case - Templates hoàn chỉnh** (code Python chạy ngay được) tại thư mục con `scripts/`. Hãy chọn template đúng với Use Case mà hệ thống của bạn cần:

1. **[`scripts/01_realtime_rss_tracking.py`](scripts/01_realtime_rss_tracking.py)**: Dùng cho Tracking Live/Bot Alerts chứng khoán. Chỉ lấy tin tức tức thời nhạy bén nhất trong ngày qua RSS.
2. **[`scripts/02_historical_sitemap_ml.py`](scripts/02_historical_sitemap_ml.py)**: Dùng cho ML/NLP. Cào dữ liệu lịch sử số lượng cực lớn trong quá khứ thông qua Sitemap.
3. **[`scripts/03_combined_rss_sitemap.py`](scripts/03_combined_rss_sitemap.py)**: Bài toán thực tế kết hợp cả 2 nguồn: Lấy RSS tức thời và quét Sitemap bù đắp các tin bị trôi mất.
4. **[`scripts/04_custom_site_parsing.py`](scripts/04_custom_site_parsing.py)**: Hướng dẫn nâng cao Custom XPath/CSS cho báo bất kỳ chưa có trong thư viện.
5. **[`scripts/05_batch_multiple_sites.py`](scripts/05_batch_multiple_sites.py)**: Chạy Data Pipeline: Thu thập siêu tốc từ hàng loạt nhiều tờ báo cùng lúc qua `AsyncBatchCrawler`.

👉 **Cách dùng:**
Từ terminal trong thư mục của bạn, chạy file với trình thông dịch đã cài vnstock_news:
```bash
python docs/vnstock_news/scripts/01_realtime_rss_tracking.py
```

---

## Tối Ưu Hóa & Tips

### 🚀 Tăng Tốc Độ

1. **Dùng AsyncBatchCrawler** - Nhanh gấp 5-10 lần
2. **Tăng concurrency** - Nhưng nhớ tránh block IP
3. **Bật cache** - Tránh lấy lại cùng một bài

### 🛡️ Tránh Bị Block

1. **Thêm delay** - `request_delay=2.0` giữa mỗi request
2. **Giới hạn concurrency** - `max_concurrency=3` để không quá tải
3. **Rotate user-agent** - Thay đổi User-Agent để không bị phát hiện
4. **Xác suất ngừng** - Nếu bị 403/429, dừng lại

### 💾 Quản Lý Bộ Nhớ

1. **Xử lý dữ liệu theo batch** - Không load hết vào RAM
2. **Xóa cache cũ** - Cache chỉ lưu N ngày gần nhất
3. **Lưu thường xuyên** - Tránh mất dữ liệu khi bị lỗi

---

## Các Lỗi Thường Gặp

| Lỗi               | Nguyên Nhân                | Giải Pháp                                    |
| ----------------- | -------------------------- | -------------------------------------------- |
| `ConnectionError` | Mất kết nối internet       | Kiểm tra WiFi, thử lại                       |
| `HTTPError 429`   | Quá nhiều request          | Tăng `request_delay`, giảm `max_concurrency` |
| `HTTPError 403`   | Bị block IP                | Đợi vài giờ, dùng VPN                        |
| `Timeout Error`   | Server chậm                | Tăng timeout, thử lại                        |
| `Parsing Error`   | Cấu trúc trang đã thay đổi | Báo lỗi tại GitHub, dùng custom_config       |

---

## Tài Liệu Tham Khảo

- **Vnstock Official**: https://vnstocks.com/
- **Robots.txt Guide**: https://www.robotstxt.org/
- **Sitemap Protocol**: https://www.sitemaps.org/
- **RSS Standard**: https://www.rssboard.org/
