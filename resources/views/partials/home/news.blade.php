@use('Carbon\Carbon')
{{-- Section "Tin tức" + the signup banner for guests: the end of the page. --}}
<div id="homeNews" class="home-end">
    <!-- 4. NEWS -->
    @if($blocks['news'] && isset($news) && $news->isNotEmpty())
    <section class="info-section" data-aos="fade-up">
        <h3>
            <i class="bi bi-newspaper" style="color: var(--primary-blue); margin-right: 10px;"></i>
            Tin tức thị trường mới nhất
            <span style="font-size: 0.7em; color: var(--text-secondary); font-weight: 400;">
                (VnExpress · CafeF · Dân Trí)
            </span>
        </h3>
        
        <div class="news-grid news-grid-home">
            @foreach($news->take(3) as $item)
            <article class="news-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                @if($item->image_url)
                <div class="news-image">
                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                    <div class="news-date-badge">
                        <i class="bi bi-clock"></i>
                        {{ $item->published_at->diffForHumans() }}
                    </div>
                </div>
                @endif
                
                <div class="news-content">
                    <a href="{{ $item->url }}" target="_blank" rel="noopener" class="news-title">
                        {{ $item->title }}
                    </a>
                    
                    <p class="news-description">
                        {{ $item->description }}
                    </p>
                    
                    <div class="news-meta">
                        <div class="news-date">
                            <i class="bi bi-calendar3"></i>
                            {{ $item->published_at->format('d/m/Y H:i') }}
                        </div>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener" class="news-read-more">
                            Đọc thêm <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="{{ route('news.index') }}"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--light-blue);color:var(--primary-blue);padding:0.75rem 1.5rem;border-radius:25px;text-decoration:none;font-weight:500;transition:all 0.3s ease;"
               onmouseover="this.style.background='var(--primary-blue)';this.style.color='white'"
               onmouseout="this.style.background='var(--light-blue)';this.style.color='var(--primary-blue)'">
                <i class="bi bi-newspaper"></i>
                Xem tất cả tin tức
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>

    @endif

    <!-- CTA Banner for guests -->
    @guest
    <section data-aos="zoom-in" style="margin-bottom:3rem;">
        <div style="background:linear-gradient(135deg,var(--primary-blue),#7c3aed);border-radius:20px;padding:3rem 2rem;text-align:center;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-20px;right:-20px;width:150px;height:150px;background:rgba(255,255,255,0.05);border-radius:50%;"></div>
            <div style="position:absolute;bottom:-30px;left:-10px;width:100px;height:100px;background:rgba(255,255,255,0.04);border-radius:50%;"></div>
            <h3 style="color:white;font-size:1.8rem;font-weight:800;margin-bottom:0.75rem;position:relative;">
                <i class="bi bi-briefcase" style="color:#fbbf24;"></i>
                Quản lý danh mục đầu tư
            </h3>
            <p style="color:rgba(255,255,255,0.85);font-size:1.05rem;max-width:550px;margin:0 auto 2rem;position:relative;line-height:1.7;">
                Đăng ký tài khoản miễn phí để theo dõi danh mục cổ phiếu cá nhân, tính toán lãi/lỗ tự động và nhận cảnh báo giá mục tiêu.
            </p>
            <div class="d-flex flex-wrap justify-content-center" style="gap:12px;position:relative;">
                <a href="{{ route('register') }}" style="background:#fbbf24;color:#1e3a5f;border-radius:12px;padding:0.875rem 2rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:1rem;transition:all 0.2s;">
                    <i class="bi bi-person-plus-fill"></i> Tạo tài khoản miễn phí
                </a>
                <a href="{{ route('login') }}" style="background:rgba(255,255,255,0.15);color:white;border:2px solid rgba(255,255,255,0.4);border-radius:12px;padding:0.875rem 2rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:1rem;transition:all 0.2s;">
                    <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                </a>
            </div>
        </div>
    </section>
    @endguest
</div>
