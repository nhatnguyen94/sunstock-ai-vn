document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const priceInput = document.getElementById('buy_price');
    const totalElement = document.getElementById('totalInvestment');
    const percentElement = document.getElementById('portfolioPercent');
    
    // Portfolio current value for percentage calculation
    function calculateTotal() {
        const quantity = parseInt(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;
        
        // Format total investment
        totalElement.textContent = new Intl.NumberFormat('vi-VN').format(total) + '₫';
        
        // Calculate percentage of portfolio
        const percent = portfolioCurrentValue > 0 ? (total / portfolioCurrentValue) * 100 : 0;
        percentElement.textContent = percent.toFixed(2) + '%';
    }
    
    quantityInput.addEventListener('input', calculateTotal);
    priceInput.addEventListener('input', calculateTotal);
    
    // Auto-fetch stock name when symbol is entered (mock functionality)
    const symbolInput = document.getElementById('stock_symbol');
    const nameInput = document.getElementById('stock_name');
    
    symbolInput.addEventListener('blur', function() {
        const symbol = this.value.toUpperCase();
        if (symbol && !nameInput.value) {
            // Mock stock name lookup - in real app, call API
            const stockNames = {
                'VCB': 'Ngân hàng TMCP Ngoại thương Việt Nam',
                'FPT': 'Công ty Cổ phần FPT',
                'VNM': 'Công ty Cổ phần Sữa Việt Nam',
                'VIC': 'Tập đoàn Vingroup',
                'GAS': 'Tổng Công ty Khí Việt Nam',
                'MSN': 'Công ty Cổ phần Tập đoàn Masan',
                'SAB': 'Tổng Công ty Sabeco',
                'HPG': 'Công ty Cổphần Tập đoàn Hòa Phát',
            };
            
            if (stockNames[symbol]) {
                nameInput.value = stockNames[symbol];
            }
        }
    });
});
