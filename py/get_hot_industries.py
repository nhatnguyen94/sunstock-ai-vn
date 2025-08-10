import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
import json
from vnstock import Listing

listing = Listing()

# Nhận tham số limit từ argv, mặc định là 30
limit = 30
if len(sys.argv) > 1:
    try:
        limit = int(sys.argv[1])
    except:
        limit = 30

df_industries = listing.symbols_by_industries()
hot_icb_names = ['Ngân hàng', 'Bất động sản', 'Công nghệ Thông tin']
hot_stocks = df_industries[df_industries['icb_name3'].isin(hot_icb_names)]

result = hot_stocks.head(limit).to_dict(orient='records')
print(json.dumps(result, ensure_ascii=False))