// 主脚本：增强交互体验
(function () {
    'use strict';

    // ============ Header scroll effect ============
    const header = document.querySelector('.site-header');
    if (header) {
        let lastScroll = 0;
        window.addEventListener('scroll', function () {
            const currentScroll = window.pageYOffset;
            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            lastScroll = currentScroll;
        }, { passive: true });
    }

    // ============ Alert auto-dismiss ============
    document.querySelectorAll('.alert[data-auto-close]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // ============ Logout confirmation ============
    document.querySelectorAll('.logout-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('确定要退出登录吗？')) {
                e.preventDefault();
            }
        });
    });

    // ============ Copy link functionality ============
    document.querySelectorAll('.copy-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.dataset.copy || '';
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(function () {
                    var originalText = btn.textContent;
                    btn.textContent = '已复制';
                    btn.style.background = '#10b981';
                    btn.style.color = '#fff';
                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.style.background = '';
                        btn.style.color = '';
                    }, 1500);
                });
            }
        });
    });

    // ============ Card hover tilt effect (subtle) ============
    document.querySelectorAll('.home-section, .card-link, .post-card').forEach(function (card) {
        card.addEventListener('mouseenter', function () {
            card.style.transform = 'translateY(-4px)';
        });
        card.addEventListener('mouseleave', function () {
            card.style.transform = '';
        });
    });

    // ============ Smooth anchor scrolling ============
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href');
            if (targetId === '#') return;
            var target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                var headerOffset = 80;
                var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ============ Form validation enhancement ============
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var isValid = true;
            form.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (input) {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#ef4444';
                    input.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.12)';
                } else {
                    input.style.borderColor = '';
                    input.style.boxShadow = '';
                }
            });
            if (!isValid) {
                e.preventDefault();
                var firstInvalid = form.querySelector('input[required]:invalid, select[required]:invalid, textarea[required]:invalid, input[style*="border-color: rgb(239, 68, 68)"]');
                if (firstInvalid) firstInvalid.focus();
            }
        });
        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            input.addEventListener('input', function () {
                if (input.style.borderColor === 'rgb(239, 68, 68)') {
                    input.style.borderColor = '';
                    input.style.boxShadow = '';
                }
            });
        });
    });

    // ============ Button loading state ============
    document.querySelectorAll('button[type="submit"], .btn-loading').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.classList.contains('loading')) return;
            var originalHTML = btn.innerHTML;
            btn.classList.add('loading');
            btn.innerHTML = '<span class="spinner"></span> 处理中...';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';
            setTimeout(function () {
                btn.classList.remove('loading');
                btn.innerHTML = originalHTML;
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
            }, 2000);
        });
    });

    // ============ Fade-in on scroll ============
    var observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.home-section, .card-link, .post-card, .comment').forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // ============ Back to top button ============
    var backToTop = document.createElement('button');
    backToTop.innerHTML = '↑';
    backToTop.style.cssText = [
        'position: fixed',
        'bottom: 30px',
        'right: 30px',
        'width: 48px',
        'height: 48px',
        'border-radius: 50%',
        'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'color: #fff',
        'font-size: 20px',
        'font-weight: bold',
        'box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4)',
        'cursor: pointer',
        'border: none',
        'opacity: 0',
        'transform: translateY(20px)',
        'transition: all 0.3s ease',
        'z-index: 999',
        'display: flex',
        'align-items: center',
        'justify-content: center'
    ].join(';');

    document.body.appendChild(backToTop);

    window.addEventListener('scroll', function () {
        if (window.pageYOffset > 400) {
            backToTop.style.opacity = '1';
            backToTop.style.transform = 'translateY(0)';
        } else {
            backToTop.style.opacity = '0';
            backToTop.style.transform = 'translateY(20px)';
        }
    }, { passive: true });

    backToTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    backToTop.addEventListener('mouseenter', function () {
        backToTop.style.transform = 'translateY(-4px) scale(1.05)';
        backToTop.style.boxShadow = '0 8px 30px rgba(102, 126, 234, 0.5)';
    });

    backToTop.addEventListener('mouseleave', function () {
        backToTop.style.transform = '';
        backToTop.style.boxShadow = '';
    });

    // ============ Form CSRF check (soft) ============
    document.querySelectorAll('form').forEach(function (form) {
        if (form.method.toLowerCase() === 'post' && !form.querySelector('[name="csrf_token"]')) {
            // 不强制阻断，仅记录
        }
    });

    // ============ Touch device optimization ============
    var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    if (isTouchDevice) {
        document.body.classList.add('touch-device');
        // Reduce animation intensity on touch devices
        document.querySelectorAll('.home-section, .card-link').forEach(function (el) {
            el.style.transition = 'box-shadow 0.2s ease, border-color 0.2s ease';
        });
    }

})();