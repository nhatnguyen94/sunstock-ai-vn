import os
import pandas as pd
from vnstock_news import Crawler

def track_realtime_news():
    print("=== THEO DÕI TIN NHANH TỨC THỜI (REALTIME) QUA RSS ===")
    print(">> Use Case: Lấy nhanh các tin nóng nhất trong ngày, phù hợp cho bot cảnh báo, cập nhật tin chứng khoán liên tục.\n")
    
    # 1. Khởi tạo Crawler với báo có cấu hình sẵn (ví dụ: cafebiz)
    # LƯU Ý: Khởi tạo bằng site_name sẽ tự động load các cấu hình RSS/Sitemap có sẵn trong hệ thống.
    crawler = Crawler(site_name="dantri")
    
    # 2. Lấy 5 bài mới nhất từ danh sách RSS
    articles = crawler.get_articles_from_feed(limit_per_feed=5)
    
    df = pd.DataFrame(articles)
    if not df.empty:
        os.makedirs("output", exist_ok=True)
        csv_file = "output/01_realtime_rss.csv"
        df.to_csv(csv_file, index=False, encoding="utf-8-sig")
        print(df[['title', 'publish_time', 'url']].head())
        print(f"\n=> Tốc độ cực nhanh, hoàn tất lấy tổng cộng {len(df)} bài viết từ RSS.")
        print(f"📁 Dữ liệu đã được lưu thành công vào file: {csv_file}")
    else:
        print("Không lấy được bài viết.")

if __name__ == "__main__":
    track_realtime_news()
