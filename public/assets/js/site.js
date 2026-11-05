// Mobile Navigation Drawer & Backdrop
const menuToggle = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('#mobile-nav');
const mobileBackdrop = document.querySelector('#mobile-nav-backdrop');
const mobileClose = document.querySelector('.mobile-nav-close');

function openMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.add('is-open');
    mobileBackdrop?.classList.add('is-open');
    menuToggle?.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
}

function closeMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.remove('is-open');
    mobileBackdrop?.classList.remove('is-open');
    menuToggle?.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (mobileNav?.classList.contains('is-open')) {
        closeMobileNav();
    } else {
        openMobileNav();
    }
});

mobileClose?.addEventListener('click', closeMobileNav);
mobileBackdrop?.addEventListener('click', closeMobileNav);

mobileNav?.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeMobileNav);
});

// Close mobile nav on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNav?.classList.contains('is-open')) {
        closeMobileNav();
    }
});

// Slider functionality
function initSlider() {
    const slider = document.querySelector('.slider-section');
    if (!slider) return;

    const slides = slider.querySelectorAll('.slide-item');
    const dots = slider.querySelectorAll('.slider-dot');
    const prevBtn = slider.querySelector('.slider-prev');
    const nextBtn = slider.querySelector('.slider-next');
    if (slides.length <= 1) return;

    let current = 0;
    let timer = null;

    function goTo(index) {
        slides[current]?.classList.remove('is-active');
        dots[current]?.classList.remove('is-active');
        current = (index + slides.length) % slides.length;
        slides[current]?.classList.add('is-active');
        dots[current]?.classList.add('is-active');
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    prevBtn?.addEventListener('click', () => { prev(); restartTimer(); });
    nextBtn?.addEventListener('click', () => { next(); restartTimer(); });

    dots.forEach((dot, idx) => {
        dot.addEventListener('click', (e) => {
            e.preventDefault();
            goTo(idx);
            restartTimer();
        });
    });

    // Touch swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;

    slider.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        clearInterval(timer);
    }, { passive: true });

    slider.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
        startTimer();
    }, { passive: true });

    function handleSwipe() {
        const diff = touchEndX - touchStartX;
        if (Math.abs(diff) > 40) {
            if (diff < 0) {
                next(); // swiped left
            } else {
                prev(); // swiped right
            }
        }
    }

    function startTimer() {
        clearInterval(timer);
        timer = setInterval(next, 5500);
    }
    function restartTimer() {
        clearInterval(timer);
        startTimer();
    }

    slider.addEventListener('mouseenter', () => clearInterval(timer));
    slider.addEventListener('mouseleave', () => startTimer());

    startTimer();
}

// FAQ Accordion exclusive toggle
function initFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    if (!faqItems.length) return;

    faqItems.forEach(item => {
        item.addEventListener('toggle', () => {
            if (item.open) {
                faqItems.forEach(other => {
                    if (other !== item && other.open) {
                        other.open = false;
                    }
                });
            }
        });
    });
}

// Copy article link to clipboard
function copyArticleLink(btn, url) {
    function showToast() {
        const tip = document.getElementById('copyTooltip');
        if (tip) {
            tip.style.display = 'inline-block';
            setTimeout(() => { tip.style.display = 'none'; }, 2500);
        }
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(showToast).catch(() => fallbackCopy(url, showToast));
    } else {
        fallbackCopy(url, showToast);
    }
}

function fallbackCopy(url, cb) {
    const el = document.createElement('textarea');
    el.value = url;
    el.setAttribute('readonly', '');
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    try {
        document.execCommand('copy');
        cb();
    } catch (e) {
        prompt('Bağlantıyı kopyalayabilirsiniz:', url);
    }
    document.body.removeChild(el);
}

// Visitor stay duration heartbeat tracker
function initVisitorHeartbeat() {
    if (!window.VISITOR_LOG_ID) return;
    const logId = window.VISITOR_LOG_ID;
    const startTime = Date.now();

    function sendHeartbeat(isFinal = false) {
        const seconds = Math.floor((Date.now() - startTime) / 1000);
        if (seconds < 1) return;

        const payload = JSON.stringify({ log_id: logId, seconds: seconds });
        if (isFinal && navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('/api/heartbeat.php', blob);
        } else {
            fetch('/api/heartbeat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: isFinal
            }).catch(() => {});
        }
    }

    // Ping every 15 seconds
    setInterval(() => {
        if (!document.hidden) {
            sendHeartbeat(false);
        }
    }, 15000);

    // Send on visibility change or page exit
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            sendHeartbeat(false);
        }
    });

    window.addEventListener('pagehide', () => {
        sendHeartbeat(true);
    });
    window.addEventListener('beforeunload', () => {
        sendHeartbeat(true);
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initSlider();
        initFaqAccordion();
        initVisitorHeartbeat();
    });
} else {
    initSlider();
    initFaqAccordion();
    initVisitorHeartbeat();
}
