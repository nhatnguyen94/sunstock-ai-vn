import os
import asyncio
import pandas as pd
from vnstock_news import AsyncBatchCrawler

async def custom_site_crawl():
    print("=== CẤU HÌNH CUSTOM CHO BÁO LẠ (CHƯA ĐƯỢC HỖ TRỢ BỞI THƯ VIỆN) ===")
    print(">> Use Case: Cần linh hoạt cào ngay lập tức 1 trang chứng khoán/tin tức chuyên biệt mà không cần chờ tác giả update bản phân phối.\n")

    custom_cfg = {
        "site_name": "My Custom Site",
        "domain": "thanhnien.vn",
        "config": {
            # Các CSS selector bắt cứng chi tiết từng block trong cấu trúc HTML của báo
            "title_selector": {"tag": "h1", "class": "detail-title"},
            "short_desc_selector": {"tag": "h2", "class": "detail-sapo"},
            "content_selector": {"tag": "div", "class": "detail-cmain"},
            "publish_time_selector": {"tag": "div", "class": "detail-time"},
            "author_selector": {"tag": "div", "class": "author-name"}
        }
    }
    
    # ⚠️ CRITICAL: KHÔNG truyền `site_name` vào AsyncBatchCrawler để hệ thống hiểu bạn dùng `custom_config`
    crawler = AsyncBatchCrawler(custom_config=custom_cfg, max_concurrency=2, debug=False)
    
    sources = ["https://thanhnien.vn/rss/home.rss"]
    print("⏳ Đang phân tách dữ liệu HTML gốc từ Custom Source bằng thư viện...")
    
    df = await crawler.fetch_articles_async(
        sources=sources,
        top_n=2  # Demo lấy 2 bài
    )
    
    if not df.empty:
        os.makedirs("output", exist_ok=True)
        csv_file = "output/04_custom_source.csv"
        df.to_csv(csv_file, index=False, encoding="utf-8-sig")
        print("\n✅ Hoàn tất tải! Tự động convert HTML sang nội dung chuẩn!")
        print(df[['title', 'publish_time']])
        print(f"📁 Nội dung Custom Parser đã lưu về: {csv_file}")
    else:
        print("Không tải được dữ liệu.")

if __name__ == "__main__":
    asyncio.run(custom_site_crawl())
