import sys
import io
from vnstock import register_user

# Fix encoding issue on Windows for printing
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

# Read API key from command-line argument
if len(sys.argv) < 2:
    print("Lỗi: API key không được cung cấp làm tham số.", file=sys.stderr)
    sys.exit(1)

api_key = sys.argv[1]

try:
    # Register the API key with the vnstock library
    register_user(api_key=api_key)
    print(f"Thành công! API key '{api_key[:15]}...' đã được đăng ký cho vnstock.")
    print("Bây giờ bạn có thể chạy lại 'php artisan queue:work' để đồng bộ dữ liệu.")

except Exception as e:
    print(f"Đã xảy ra lỗi khi đăng ký API key: {e}", file=sys.stderr)
    sys.exit(1)
