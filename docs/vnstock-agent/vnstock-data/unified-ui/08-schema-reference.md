# Standardized Data Schemas Reference (Từ Điển Schema)

Tài liệu quy chuẩn Schema cho toàn bộ hệ thống Unified UI của vnstock-data. Mỗi cấu trúc đầu ra đều được định nghĩa bắt buộc bao gồm kiểu dữ liệu (`Dtype`), ý nghĩa (`Meaning`), và dữ liệu mẫu (`Sample`).

> **Status**: Đây là Data Dictionary (từ điển) cho Output Pandas DataFrame, giúp Data Scientists, Quants và AI Agent dễ dàng nắm bắt kiểu dữ liệu để lập trình mô hình.

## Cụm Company Layer

### `company.info`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`short_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`sector`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`industry`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`profile`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`history`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`num_employees`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`founded_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`listing_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`charter_capital`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`issued_share`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`website`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`address`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`phone`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`email`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`tax_id`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `company.margin_ratio`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`broker_code`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`broker_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`margin_rate`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`prev_margin_rate`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`margin_per`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`updated_at`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `company.officers`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`position`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`from_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`total_shares`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`rate`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `company.shareholders`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`total_shares`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`rate`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `company.subsidiaries`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`rate`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`sub_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`charter_capital`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

## Cụm Derivatives Layer

### `derivatives.warrant.info`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`isin`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`issuer`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exercise_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`exercise_ratio`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`issue_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`maturity_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`listed_share`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`warrant_type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_room`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`break_even_point_diff`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

## Cụm Equity Layer

### `equity.list`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`org_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`icb_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`listing_type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

## Cụm Events Layer

### `events.calendar`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`event_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`event_title`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`ex_right_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`record_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`payout_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ratio`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`organ_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`public_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`issue_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`event_type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `events.market`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`event_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`event_type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`duration`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

## 3. Fundamental Layer (Tài chính Cơ bản)

### `fundamental.equity.balance_sheet`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`report_period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `fundamental.equity.cash_flow`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`report_period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `fundamental.equity.income_statement`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`report_period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `fundamental.equity.note`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`report_period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `fundamental.equity.ratio`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`report_period`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

## Cụm Industry Layer

### `industry.list`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`icb_code`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`icb_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`icb_name_en`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`icb_level`** | `object / float64` | Thuộc tính dữ liệu | `...` |

## 5. Insights Layer (Lọc Tín hiệu & Mảng Sâu)

### `insights.ranking.deal`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`last_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`last_updated`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price_change_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change_pct_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`deal_volume_spike_20d_pct`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `insights.ranking.foreign_buy`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`net_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `insights.ranking.foreign_sell`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`net_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `insights.ranking.gainer`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`last_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`last_updated`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price_change_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change_pct_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`avg_volume_20d`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`volume_spike_20d_pct`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `insights.ranking.loser`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`last_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`last_updated`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price_change_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change_pct_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `insights.ranking.value`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`last_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`last_updated`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price_change_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change_pct_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `insights.ranking.volume`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`last_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`last_updated`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price_change_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change_pct_1d`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`avg_volume_20d`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`volume_spike_20d_pct`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `insights.valuation.evaluation`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `insights.valuation.pb`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `insights.valuation.pe`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

## 4. Macro Layer (Kinh tế Vĩ mô)

### `macro.commodity`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`report_time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`buy`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`sell`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`ron95`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`ron92`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`oil_do`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `macro.currency`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`report_time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

### `macro.economy`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`report_time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

## 1. Market Layer (Thị trường & Giao dịch)

### `market.crypto.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `BTCUSDT` |

### `market.crypto.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `BTCUSDT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `BINANCE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch | `154300` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch | `154300` |

### `market.crypto.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (L1) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch Mua | `154300` |
| `..._2 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (L1) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch Bán | `154300` |
| `..._2 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.crypto.trades` / `market.crypto.intraday`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá khớp lệnh | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch khớp lệnh | `154300` |
| **`side`** | `object (str)` | Phe giao dịch định danh | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | Mã giao dịch định danh | `1002341` |

### `market.crypto.vwap`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`mins`** | `int64` | Thời lượng thống kê (phút) | `5` |
| **`price`** | `float64` | Mức giá trung bình | `85500.0` |
| **`close_time`** | `datetime64[ns]` | Thời điểm thống kê | `2024-03-15 09:15:00` |

### `market.crypto.daily_stats` / `market.crypto.rolling_stats`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh | `BTCUSDT` |
| **`price_change`** | `float64` | Thay đổi giá trị tuyệt đối | `120.5` |
| **`price_change_percent`** | `float64` | Phần trăm thay đổi | `1.5` |
| **`weighted_avg_price`** | `float64` | Giá TB trọng số | `85500.0` |
| **`open_price`** | `float64` | Mở cửa | `85000.0` |
| **`high_price`** | `float64` | Cao nhất | `86000.0` |
| **`low_price`** | `float64` | Thấp nhất | `84000.0` |
| **`last_price`** | `float64` | Nhường nhất | `85500.0` |
| **`volume`** | `float64` | Tổng khối lượng Base Asset | `154300` |
| **`quote_volume`** | `float64` | Tổng khối lượng Quote Asset | `85500000.0` |
| **`open_time`** | `datetime64[ns]` | Thời gian mở cửa | `2024-03-14 09:15:00` |
| **`close_time`** | `datetime64[ns]` | Thời gian đóng cửa | `2024-03-15 09:15:00` |
| **`count`** | `int64` | Số lượng lệnh đã khớp | `1000` |

### `market.crypto.last_price`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh | `BTCUSDT` |
| **`price`** | `float64` | Mức giá khớp cuối cùng | `85500.0` |

### `market.crypto.reference_price`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh | `BTCUSDT` |
| **`reference_price`** | `float64` | Mức giá tham chiếu | `85500.0` |
| **`timestamp`** | `datetime64[ns]` | Dấu thời gian | `2024-03-15 09:15:00` |

### `market.forex.ohlcv` / `market.commodity.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian (Mặc định GMT+7) | `2024-03-15 07:00:00` |
| **`open`** | `float64` | Mức giá mở cửa | `85500.0` |
| **`high`** | `float64` | Mức giá cao nhất | `85500.0` |
| **`low`** | `float64` | Mức giá thấp nhất | `85500.0` |
| **`close`** | `float64` | Mức giá đóng cửa | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Tick count | `154300` |
| **`value`** | `float64` | Giá trị giao dịch tĩnh | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán | `EURUSD` |

### `market.forex.intraday` / `market.commodity.intraday`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian Tick (GMT+7) | `2024-03-15 09:15:00` |
| **`ask`** | `float64` | Mức giá Bán | `85500.0` |
| **`bid`** | `float64` | Mức giá Mua | `85490.0` |
| **`ask_vol`** | `float64` | Volume khối lượng Ask | `154300` |
| **`bid_vol`** | `float64` | Volume khối lượng Bid | `154400` |


### `Market().equity("SYMBOL").block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `Market().equity("SYMBOL").foreign_flow`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`buy_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`buy_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`sell_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`net_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`net_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `Market().equity("SYMBOL").futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `Market().equity("SYMBOL").index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `Market().equity("SYMBOL").odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `Market().equity("SYMBOL").ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `Market().equity("SYMBOL").order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `Market().equity("SYMBOL").proprietary_flow`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`buy_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`buy_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`sell_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`net_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`net_val`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `Market().equity("SYMBOL").quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `Market().equity("SYMBOL").summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`high_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`dividend`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`beta`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`eps`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`bvps`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`market_cap`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`roe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`change_1m`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`change_1y`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`dividend_yield`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`foreign_ownership_pct`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `Market().equity("SYMBOL").trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `Market().equity("SYMBOL").volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `Market().equity("SYMBOL").warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.etf.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.etf.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.etf.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.etf.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.etf.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.etf.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.etf.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.etf.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`high_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`dividend`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`beta`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`eps`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`bvps`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`market_cap`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`roe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`change_1m`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`change_1y`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`dividend_yield`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`foreign_ownership_pct`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.etf.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.etf.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.etf.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.forex.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.forex.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.forex.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.forex.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.forex.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.forex.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.forex.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.forex.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`high_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`dividend`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`beta`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`eps`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`bvps`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`market_cap`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`roe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`change_1m`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`change_1y`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`dividend_yield`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`foreign_ownership_pct`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.forex.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.forex.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.forex.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.fund.asset_holding`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`asset_type`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`asset_percent`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.fund.history`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`nav`** | `object / float64` | Thuộc tính dữ liệu | `...` |

### `market.fund.industry_holding`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`industry`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`net_asset_percent`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.fund.top_holding`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`update_at`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`industry`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`net_asset_percent`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`asset_type`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.futures.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.futures.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.futures.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.futures.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.futures.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.futures.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.futures.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.futures.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.futures.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.futures.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.futures.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.index.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.index.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.index.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.index.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.index.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.index.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.index.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.index.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.index.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.index.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.index.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.market_wide.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.market_wide.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.market_wide.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.market_wide.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.market_wide.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.market_wide.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.market_wide.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.market_wide.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`high_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_52w`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`dividend`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`beta`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`eps`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`bvps`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`market_cap`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`pe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`pb`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`roe`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`change_1m`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`change_1y`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`dividend_yield`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`foreign_ownership_pct`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.market_wide.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.market_wide.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.market_wide.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.warrant.block_trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.warrant.futures_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`first_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`last_trading_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`underlying_symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.warrant.index_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`price_change`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`percent_change`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`advances`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`declines`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`no_change`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`accumulated_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`accumulated_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`put_through_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`previous_close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.warrant.odd_lot`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.warrant.ohlcv`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`open`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

### `market.warrant.order_book`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |

### `market.warrant.quote`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`match_vol`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`bid_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`bid_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `bid_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `bid_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`ask_price_1`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_1`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`ask_price_2`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ask_vol_2`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `ask_price_3` | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| `ask_vol_3` | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| `..._4 to 10` | `...` | *(Tương tự cho các cấp độ tiếp theo)* | `...` |
| **`basis`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`open_interest`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`foreign_buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`foreign_sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |

### `market.warrant.summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

### `market.warrant.trades`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`time`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`side`** | `object (str)` | Phe giao dịch (Buy/Sell/Unknown) | `Buy` |
| **`match_type`** | `object (str)` | Phân loại khớp lệnh | `Normal` |
| **`id`** | `object (str)` | ID Nhận diện duy nhất | `812xcz-3` |

### `market.warrant.volume_profile`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`buy_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`sell_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`unknown_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`total_volume`** | `int64 / float64` | Lượng giao dịch / Cổ phiếu | `154300` |
| **`match_percent`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |

### `market.warrant.warrant_summary`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`reference_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`ceiling_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`floor_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`open_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`high_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`low_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`close_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`break_even_point`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`intrinsic_value`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |
| **`underlying_price`** | `float64` | Mức giá / Giá trị tài sản (VND) | `85500.0` |

## 2. Reference Layer (Dữ liệu Tham chiếu & Doanh nghiệp)

### `reference.fund.list`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`ticker`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`organ_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`organ_short_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`fund_type`** | `float64` | Tỷ lệ / Chỉ số đánh giá | `0.15` |
| **`management_fee`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`inception_date`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |
| **`nav`** | `object / float64` | Thuộc tính dữ liệu | `...` |
| **`nav_update_at`** | `datetime64[ns]` | Dấu thời gian / Ngày thực thi | `2024-03-15 09:15:00` |

## Cụm Search Layer

### `search.symbol`
| Column Name (Cột) | Dtype | Ý Nghĩa (Meaning) | Sample Value |
|-------------------|-------|-------------------|--------------|
| **`symbol`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |
| **`name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`exchange`** | `object (str)` | Sàn giao dịch | `HOSE` |
| **`short_name`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`description`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`name_en`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`name_local`** | `object (str)` | Tên / Phân loại / Mô tả | `Công ty CP FPT` |
| **`symbol_id`** | `object (str)` | Mã định danh chứng khoán / Chỉ số | `FPT` |

