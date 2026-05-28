document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('vi-VN', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    // Initialize time and update every second
    updateTime();
    setInterval(updateTime, 1000);

    // Refresh page function
    window.refreshPage = function() {
        const refreshIcon = document.getElementById('refresh-icon');
        refreshIcon.style.animation = 'spin 1s linear infinite';
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    };

    // Copy to clipboard functionality
    const rateValues = document.querySelectorAll('.rate-value');
    rateValues.forEach(rate => {
        if (rate.textContent.trim() !== '-' && rate.textContent.trim() !== '') {
            rate.addEventListener('click', function() {
                const text = this.textContent.trim();
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        showCopyFeedback(this);
                    }).catch(err => {
                        console.error('Copy failed:', err);
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        showCopyFeedback(this);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                    document.body.removeChild(textArea);
                }
            });
        }
    });

    function showCopyFeedback(element) {
        const originalText = element.textContent;
        const originalBg = element.style.background;
        
        element.textContent = 'Đã sao chép!';
        element.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        element.style.color = 'white';
        element.style.transform = 'scale(1.05)';
        
        setTimeout(() => {
            element.textContent = originalText;
            element.style.background = originalBg;
            element.style.color = '';
            element.style.transform = '';
        }, 1500);
    }

    // Enhanced date input functionality
    const dateInput = document.getElementById('search_date');
    const searchBtn = document.getElementById('search-button');
    const dateDisplay = document.getElementById('date-display');
    const clearDateBtn = document.getElementById('clear-date');
    const dateHelp = document.getElementById('date-help');
    
    if (dateInput) {
        // Function to update date display
        function updateDateDisplay(dateValue) {
            if (dateValue) {
                const date = new Date(dateValue);
                const formattedDate = date.toLocaleDateString('vi-VN');
                const diffText = getDateDiff(date);
                
                dateDisplay.innerHTML = `
                    <i class="bi bi-check-circle"></i>
                    Ngày đã chọn: ${formattedDate} (${diffText})
                `;
                dateDisplay.classList.add('show');
                clearDateBtn.classList.add('show');
                
                // Update input styling
                dateInput.classList.add('has-value');
                
                // Update button
                searchBtn.innerHTML = '<i class="bi bi-search"></i> Tìm kiếm';
                searchBtn.style.opacity = '1';
                
                // Hide help text
                if (dateHelp) {
                    dateHelp.style.display = 'none';
                }
            } else {
                dateDisplay.classList.remove('show');
                clearDateBtn.classList.remove('show');
                dateInput.classList.remove('has-value');
                
                // Reset input styling
                dateInput.style.borderColor = '#e5e7eb';
                dateInput.style.background = '#f8fafc';
                
                // Update button
                searchBtn.innerHTML = '<i class="bi bi-search"></i> Tìm kiếm';
                searchBtn.style.opacity = '0.7';
                
                // Show help text
                if (dateHelp) {
                    dateHelp.style.display = 'flex';
                }
            }
        }

        // Function to calculate date difference
        function getDateDiff(date) {
            const now = new Date();
            const diffTime = now - date;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) return 'hôm nay';
            if (diffDays === 1) return 'hôm qua';
            if (diffDays === -1) return 'ngày mai';
            if (diffDays > 0) return `${diffDays} ngày trước`;
            return `${Math.abs(diffDays)} ngày nữa`;
        }

        // Date input change event
        dateInput.addEventListener('change', function() {
            updateDateDisplay(this.value);
        });

        // Date input input event (for real-time updates)
        dateInput.addEventListener('input', function() {
            updateDateDisplay(this.value);
        });

        // Clear date button
        clearDateBtn.addEventListener('click', function() {
            dateInput.value = '';
            updateDateDisplay('');
            dateInput.focus();
        });

        // Initialize on page load
        if (dateInput.value) {
            updateDateDisplay(dateInput.value);
        }

        // Fix for browsers that don't show date value
        dateInput.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary-blue)';
            this.style.background = 'white';
        });
        
        dateInput.addEventListener('blur', function() {
            if (!this.value) {
                this.style.borderColor = '#e5e7eb';
                this.style.background = '#f8fafc';
            }
        });
    }

    // Enhanced form validation
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const dateValue = dateInput.value;
            if (!dateValue) {
                e.preventDefault();
                
                // Show error feedback
                dateInput.style.borderColor = '#ef4444';
                dateInput.style.background = '#fef2f2';
                
                // Add shake animation
                dateInput.style.animation = 'shake 0.5s';
                setTimeout(() => {
                    dateInput.style.animation = '';
                    dateInput.style.borderColor = '#e5e7eb';
                    dateInput.style.background = '#f8fafc';
                }, 2000);
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.style.cssText = `
                    color: #ef4444;
                    font-size: 0.875rem;
                    margin-top: 0.5rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                `;
                errorMsg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Vui lòng chọn ngày trước khi tìm kiếm';
                
                const existingError = dateInput.parentNode.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                dateInput.parentNode.parentNode.appendChild(errorMsg);
                
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.remove();
                    }
                }, 3000);
                
                dateInput.focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('.search-btn');
            const originalContent = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Đang tìm...';
            
            // Reset after 10 seconds if no response
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalContent;
            }, 10000);
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R for refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            refreshPage();
        }
        
        // Ctrl/Cmd + P for print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('search_date');
            if (searchInput && searchInput.value) {
                window.location.href = window._exchangeRateUrl || '/exchange-rate';
            }
        }
    });
});

// Add shake animation to CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);

// ApexCharts: Key Rates Bar Chart

