import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
import json
from vnstock import Listing

# Nhận tham số limit từ argv, mặc định là 30
limit = 30
if len(sys.argv) > 1:
    try:
        limit = int(sys.argv[1])
    except:
        limit = 30

listing = Listing()

df_industries = listing.symbols_by_industries()
df_symbols = listing.all_symbols()

# Tên ngành hot (API mới đổi 'Công nghệ Thông tin' → 'Công nghệ và thông tin')
hot_icb_names = ['Ngân hàng', 'Bất động sản', 'Công nghệ và thông tin']
hot_stocks = df_industries[df_industries['industry_name'].isin(hot_icb_names)].copy()

# Join với all_symbols để lấy tên công ty (organ_name)
hot_stocks = hot_stocks.merge(df_symbols[['symbol', 'organ_name']], on='symbol', how='left')

# Rename để tương thích với blade template cũ
hot_stocks = hot_stocks.rename(columns={'industry_name': 'icb_name3'})
hot_stocks['organ_name'] = hot_stocks['organ_name'].fillna(hot_stocks['symbol'])

result = hot_stocks.head(limit)[['symbol', 'organ_name', 'icb_name3']].to_dict(orient='records')
print(json.dumps(result, ensure_ascii=False))