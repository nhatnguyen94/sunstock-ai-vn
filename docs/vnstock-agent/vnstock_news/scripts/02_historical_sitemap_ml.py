import os
import asyncio
import pandas as pd
from vnstock_news import AsyncBatchCrawler, SITES_CONFIG

async def fetch_historical_news_for_ml():
    print("=== LẤY DỮ LIỆU LỊCH SỬ KHỐI LƯỢNG LỚN QUA SITEMAP ===")
    print(">> Use Case: Thu thập kho dữ liệu khổng lồ (vài tháng đến nhiều năm) chuyên phục vụ phân tích dữ liệu AI (NLP / Machine Learning).\n")
    
    site = "cafef" # Báo điển hình cho chứng khoán
    config = SITES_CONFIG[site]
    
    # Lấy sitemap tĩnh (hoặc sitemap hiện hành)
    sitemap_url = config.get("sitemap_url") or config.get("sitemap", {}).get("current_url")
    
    if not sitemap_url:
        print("Báo này không hỗ trợ Sitemap trong config.")
        return

    # Khởi tạo Async Crawler
    # Đặt max_concurrency 3-5 để phân luồng tải nhanh, tránh bị khóa IP
    crawler = AsyncBatchCrawler(site_name=site, max_concurrency=3)
    
    print(f"⏳ Đang tải hàng loạt bài viết từ sitemap: {sitemap_url} ...")
    
    # CRITICAL: Truyền url của Sitemap (không truyền 'cafef' vào sources)
    df = await crawler.fetch_articles_async(
        sources=[sitemap_url],
        top_n=3,      # Demo lấy 3 bài. Thực tế ML/NLP bạn có thể bỏ limit hoặc đặt số lượng cực lớn.
        within="365d"   # Giới hạn bài trong 1 năm gốc
    )
    
    if not df.empty:
        os.makedirs("output", exist_ok=True)
        csv_file = "output/02_historical_sitemap.csv"
        df.to_csv(csv_file, index=False, encoding="utf-8-sig")
        # Trong data có chứa luôn markdown_content - là mỏ vàng để huấn luyện AI
        print("\n✅ Hoàn tất tải dữ liệu lịch sử:")
        print(df[['title', 'publish_time', 'author']].head())
        print(f"\n=> Thể hiện lượng ký tự Content Markdown bài 1: {len(df.iloc[0].get('markdown_content', ''))} ký tự.")
        print(f"📁 Dữ liệu lịch sử đã được lưu progressive vào file: {csv_file}")
    else:
        print("Không tải được dữ liệu.")

if __name__ == "__main__":
    asyncio.run(fetch_historical_news_for_ml())
