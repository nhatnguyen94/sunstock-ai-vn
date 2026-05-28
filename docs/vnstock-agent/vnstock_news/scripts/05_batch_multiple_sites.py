import os
import asyncio
import pandas as pd
from vnstock_news import AsyncBatchCrawler, SITES_CONFIG
import warnings
warnings.filterwarnings("ignore")

async def fetch_multiple_sites():
    print("=== TẢI TIN TỪ HÀNG LOẠT BÁO CÙNG LÚC (DATA PIPELINE) ===")
    print(">> Use Case: Chạy tự động (cronjob) quy mô chắt lọc lượng tin khổng lồ từ nhiều báo cho ra 1 file dataset chuẩn chỉ.\n")
    
    sites = ["znews", "dantri", "24h"]
    all_articles = []
    
    for site in sites:
        crawler = AsyncBatchCrawler(site_name=site, max_concurrency=3)
        cfg = SITES_CONFIG[site]
        
        # Chọn nguồn (Source) ưu tiên RSS cho tính tức thời
        sources = []
        if "rss" in cfg and cfg["rss"].get("urls"):
            sources = [cfg["rss"]["urls"][0]]
        elif "sitemap_url" in cfg:
            sources = [cfg["sitemap_url"]]
            
        if not sources:
            continue
            
        print(f"⏳ Đang xếp hàng chạy nền báo [{site.upper()}] ...")
        
        try:
            # ⚠️ Truyền list URL (sources) chứ không truyền chuỗi site_name
            df = await crawler.fetch_articles_async(
                sources=sources,
                top_n=2
            )
            if not df.empty:
                df['source_site'] = site
                all_articles.append(df)
        except Exception as e:
            print(f"❌ Lỗi khi cào {site}: {e}")
            
    if all_articles:
        final_df = pd.concat(all_articles, ignore_index=True)
        os.makedirs("output", exist_ok=True)
        csv_file = "output/05_batch_multiple.csv"
        final_df.to_csv(csv_file, index=False, encoding="utf-8-sig")
        print("\n✅ TỔNG KẾT PIPELINE HOÀN HẢO:")
        print(final_df[['source_site', 'title', 'publish_time']])
        print(f"📁 Master Dataset đã được compile và lưu thành công tại: {csv_file}")
    else:
        print("Không cào được tin nào.")

if __name__ == "__main__":
    asyncio.run(fetch_multiple_sites())
