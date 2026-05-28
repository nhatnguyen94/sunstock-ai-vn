async function updatePrices(portfolioId) {
    const refreshIcon = document.getElementById('refresh-icon');
    refreshIcon.classList.add('fa-spin');
    
    try {
        const response = await fetch(`/portfolio/${portfolioId}/update-prices`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh page to show updated prices
        } else {
            alert(result.message || 'Không thể cập nhật giá');
        }
    } catch (error) {
        alert('Có lỗi xảy ra khi cập nhật giá');
    } finally {
        refreshIcon.classList.remove('fa-spin');
    }
}

async function getRebalanceSuggestions(portfolioId) {
    try {
        const response = await fetch(`/portfolio/${portfolioId}/rebalance-suggestions`);
        const result = await response.json();
        
        if (result.success) {
            let content = '<div>Chưa có gợi ý rebalance.</div>';
            
            if (result.suggestions && result.suggestions.length > 0) {
                content = '<ul class="list-group">';
                result.suggestions.forEach(suggestion => {
                    content += `<li class="list-group-item">
                        <strong>${suggestion.symbol}</strong>: ${suggestion.reason}
                        <br><small>Hiện tại: ${suggestion.current_percent}% → Đề xuất: ${suggestion.suggested_percent}%</small>
                    </li>`;
                });
                content += '</ul>';
            }
            
            document.getElementById('rebalanceContent').innerHTML = content;
            $('#rebalanceModal').modal('show');
        }
    } catch (error) {
        alert('Có lỗi xảy ra khi tạo gợi ý rebalance');
    }
}

function editItem(itemId) {
    // Simple edit functionality - you can enhance this
    const newQuantity = prompt('Nhập số lượng mới:');
    const newPrice = prompt('Nhập giá mua mới:');
    
    if (newQuantity && newPrice) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/portfolio/item/${itemId}/update`;
        
        form.innerHTML = `
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
            <input type="hidden" name="quantity" value="${newQuantity}">
            <input type="hidden" name="buy_price" value="${newPrice}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}
