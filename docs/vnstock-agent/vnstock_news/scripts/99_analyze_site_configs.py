import asyncio
import pandas as pd
from vnstock_news import AsyncBatchCrawler
from vnstock_news.config.sites import SITES_CONFIG, SUPPORTED_SITES

# Tắt cảnh báo parser của pd.to_datetime để tránh nhiễu log terminal
import warnings
warnings.filterwarnings("ignore")

async def test_all_sites():
    print(f"=== TIẾN HÀNH KIỂM TRA SỨC KHỎE (HEALTH CHECK) CHO {len(SUPPORTED_SITES)} TRANG BÁO ===")
    
    results = []
    
    for site in SUPPORTED_SITES:
        print(f"\n⏳ Đang phân tích Config của trang: [{site.upper()}] ...")
        
        crawler = AsyncBatchCrawler(site_name=site, max_concurrency=2)
        cfg = SITES_CONFIG[site]
        
        sources = []
        if "rss" in cfg and cfg["rss"].get("urls"):
            sources = [cfg["rss"]["urls"][0]]     # Lấy feed RSS đầu tiên
        elif "sitemap_url" in cfg:
            sources = [cfg["sitemap_url"]]          # Lấy sitemap gốc
            
        if not sources:
            print(f"⚠️ {site}: Không có cấu hình Sitemap hoặc RSS để lấy link.")
            results.append({
                "site": site,
                "status": "FAIL (No Source)",
                "note": "Missing both RSS/Sitemap in config"
            })
            continue

        try:
            # Lấy đúng 2 bài đầu tiên cho nhẹ/nhanh
            df = await crawler.fetch_articles_async(sources=sources, top_n=2)
            
            if df.empty:
                print(f"❌ {site}: Pipeline trả về DataFrame rỗng!")
                results.append({"site": site, "status": "EMPTY"})
            else:
                # Đánh giá tỷ lệ điền đầy của các target col
                target_cols = ['title', 'content', 'markdown_content', 'publish_time', 'author', 'short_description', 'image_url', 'category']
                
                # Check rỗng trên dòng đầu tiên (hoặc tính len)
                row = df.iloc[0]
                
                # Hàm check xem value có hợp lệ không (not None, not NaN, k rỗng)
                def _is_valid(val):
                    if pd.isna(val) or val is None: return False
                    if isinstance(val, str) and not val.strip(): return False
                    return True
                
                report_row = {"site": site, "status": "SUCCESS"}
                
                print(">>> Chi tiết Parsing:")
                for col in target_cols:
                    if col in df.columns:
                        has_val = _is_valid(row.get(col))
                        report_row[col] = "✅" if has_val else "❌"
                        print(f"    - {col}: {'✅ Có' if has_val else '❌ Thiếu'}")
                    else:
                        report_row[col] = "➖" # Không tồn tại trong df
                        print(f"    - {col}: ➖ (Not in logic)")
                        
                results.append(report_row)
                
        except Exception as e:
            print(f"🔥 {site}: Sinh lỗi quá trình chạy lô: {e}")
            results.append({
                "site": site,
                "status": "ERROR",
                "note": str(e)
            })

    # Tính toán tổng hợp
    df_results = pd.DataFrame(results)
    print("\n\n===============================================")
    print("BẢNG TỔNG SẮP TÍNH TOÀN VẸN CỦA TẤT CẢ CONFIGS")
    print("===============================================")
    print(df_results.to_string(index=False))
    
    csv_out = "config_health_report.csv"
    df_results.to_csv(csv_out, index=False, encoding='utf-8-sig')
    print(f"\n📁 Đã lưu lịch sử Health Check ra file: {csv_out}")

if __name__ == "__main__":
    asyncio.run(test_all_sites())
