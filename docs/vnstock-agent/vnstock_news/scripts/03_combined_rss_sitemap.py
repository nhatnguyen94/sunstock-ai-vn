import os
import pandas as pd
from vnstock_news import Crawler

def fetch_combined_news():
    print("=== DÙNG KẾT HỢP RSS + SITEMAP ĐỂ GIẢI QUYẾT BÀI TOÁN BÙ ĐẮP DỮ LIỆU ===")
    print(">> Use Case: Lấy nhanh các tin hôm nay qua RSS, cào thêm Sitemap cho các tin bị sót/trôi trên web đảm bảo không lỡ nhịp thị trường.\n")
    
    crawler = Crawler(site_name="cafebiz")
    
    print("1. Đang quét RSS (Lấy tin siêu nhanh mới nhất)...")
    rss_list = crawler.get_articles_from_feed(limit_per_feed=10)
    rss_df = pd.DataFrame(rss_list) if rss_list else pd.DataFrame()
    
    print("2. Đang quét Sitemap (Lọc các tin mới bù đắp)...")
    # Sử dụng get_latest_articles() trả về Metadata XML của sitemap
    sm_list = crawler.get_latest_articles(limit=20) 
    sitemap_df = pd.DataFrame(sm_list) if sm_list else pd.DataFrame()
    
    print("3. Đang gộp và xử lý trùng lặp...")
    if not rss_df.empty and not sitemap_df.empty:
        # Chuẩn hoá tên cột (Vì có báo sitemap sẽ trả loc thay cho url)
        if 'loc' in sitemap_df.columns:
            sitemap_df = sitemap_df.rename(columns={'loc': 'url'})
            
        all_articles = pd.concat([rss_df, sitemap_df], ignore_index=True)
        # Loại bỏ các link URL bị trùng giữa 2 phương pháp
        before_dedup = len(all_articles)
        all_articles = all_articles.drop_duplicates(subset=['url'])
        after_dedup = len(all_articles)
        
        os.makedirs("output", exist_ok=True)
        csv_file = "output/03_combined_data.csv"
        all_articles.to_csv(csv_file, index=False, encoding="utf-8-sig")
        
        print(f"\n✅ Gộp thành công! Lấy được {after_dedup} bài viết duy nhất (loại bỏ {before_dedup - after_dedup} bài trùng lặp).")
        print(all_articles[['url']].head(3))
        print(f"📁 Dữ liệu tổng hợp đã được lưu an toàn tại: {csv_file}")
    else:
        print("Không kết hợp được do thiếu nguồn RSS hoặc Sitemap.")

if __name__ == "__main__":
    fetch_combined_news()
