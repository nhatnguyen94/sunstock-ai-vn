import os
import sys
import io
import json
import logging
import time # Thêm thư viện time
from datetime import datetime, timedelta

# Fix encoding issue on Windows
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

from vnstock import Vnstock

# Suppress all logs below WARNING level
logging.getLogger().setLevel(logging.WARNING)

# Check for symbol argument
if len(sys.argv) < 2:
    print(json.dumps({"error": "Stock symbol is required"}))
    sys.exit(1)

symbols_arg = sys.argv[1]
symbols = [s.strip().upper() for s in symbols_arg.split(',') if s.strip()]

# Define date range (last 30 days)
end_date = datetime.today()
start_date = end_date - timedelta(days=365) # Lấy dữ liệu 1 năm
start = start_date.strftime('%Y-%m-%d')
end = end_date.strftime('%Y-%m-%d')

results = {}
errors = {}

# Khởi tạo vnstock
stock_instance = Vnstock()

for symbol in symbols:
    try:
        # Sử dụng lại instance đã khởi tạo
        stock = stock_instance.stock(symbol=symbol, source='VCI')
        df = stock.quote.history(start=start, end=end)

        if df.empty:
            results[symbol] = []
        else:
            results[symbol] = json.loads(df.to_json(orient='records', force_ascii=False))
        
        # Thêm độ trễ nhỏ sau mỗi lần gọi API thành công
        time.sleep(1) # 60 requests/phút -> 1 request/giây

    except Exception as e:
        errors[symbol] = str(e)
        # Nếu có lỗi, cũng nên có một khoảng nghỉ ngắn
        time.sleep(1)

output = {"data": results, "errors": errors}

# Output JSON data only (clean, single line)
print(json.dumps(output, ensure_ascii=False))
