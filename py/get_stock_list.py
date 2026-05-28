import os
import sys
import io
import json
import logging
import pandas as pd
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
logging.getLogger().setLevel(logging.WARNING)

from vnstock import Vnstock

try:
    # Khởi tạo vnstock
    vnstock_instance = Vnstock()
    
    # Sử dụng phương thức mới để lấy danh sách
    df_stocks = vnstock_instance.listing.all_symbols()
    df_etfs = vnstock_instance.listing.all_etf().to_frame(name='symbol')
    
    df = pd.concat([df_stocks, df_etfs], ignore_index=True)
    # Output proper JSON, replacing NaN with null
    print(df.to_json(orient='records', force_ascii=False))
except Exception as e:
    print(json.dumps({"error": str(e)}))