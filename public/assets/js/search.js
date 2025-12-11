// === وظيفة عامة لإدارة الشريط الجانبي على الهواتف ===
function toggleSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebar) {
        // في الشاشات الصغيرة، أضف/أزل كلاس "active"
        if (window.innerWidth <= 767) {
            sidebar.classList.toggle('active');
        }
    }
}

// === وظيفة بحث متقدمة ===
function setupSearch() {
    const searchInput = document.getElementById('global-search');
    const searchResults = document.getElementById('search-results');

    if (!searchInput || !searchResults) return;

    searchInput.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();

        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        // استخدم AJAX للبحث (تم التعديل لاستخدام GET)
        fetch(`../search-handler.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                // (مُعدَّل) التحقق من وجود مفتاح النتائج في الاستجابة
                if (data && data.results && data.results.length > 0) {
                    let html = '';
                    data.results.forEach(item => {
                        // (مُعدَّل) عرض الاسم والدور والإدارة والبريد
                        html += `<div class="search-result-item" onclick="location.href='${item.url}'">
                                    <strong>${item.name}</strong><br>
                                    <small class="text-muted">
                                        ${item.role_ar ? item.role_ar : ''}
                                        ${item.dept ? ' - ' + item.dept : ''}
                                        <br>
                                        ${item.email}
                                    </small>
                                </div>`;
                    });
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';
                } else {
                    searchResults.innerHTML = '<div class="search-result-item">لا توجد نتائج</div>';
                    searchResults.style.display = 'block';
                }
            })
            .catch(err => {
                console.error('خطأ في البحث:', err);
                searchResults.innerHTML = '<div class="search-result-item">حدث خطأ أثناء البحث</div>';
                searchResults.style.display = 'block';
            });
    });

    // إخفاء النتائج عند النقر خارجها
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // إخفاء النتائج عند الضغط على مفتاح Escape
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            searchInput.value = '';
        }
    });
}

// === وظيفة نسخ الرابط (اختياري) ===
function setupCopyLinkButtons() {
    document.querySelectorAll('.copy-link-btn').forEach(button => {
        button.addEventListener('click', function () {
            const link = this.getAttribute('data-link');
            navigator.clipboard.writeText(link).then(() => {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 2000);
            }).catch(err => {
                console.error('فشل في نسخ الرابط: ', err);
                alert('فشل في نسخ الرابط. يرجى المحاولة يدويًّا.');
            });
        });
    });
}

// (جديد) === وظيفة إظهار حالة الإنترنت (المُنظَّمة) ===
function checkInternet() {
    fetch('https://www.google.com', { method: 'HEAD', mode: 'no-cors' })
        .then(() => {
            const statusElement = document.getElementById('internet-status');
            if (statusElement) {
                statusElement.innerHTML = '<span class="badge bg-success">متصل</span>';
            }
        })
        .catch(() => {
            const statusElement = document.getElementById('internet-status');
            if (statusElement) {
                statusElement.innerHTML = '<span class="badge bg-danger">غير متصل</span>';
            }
        });
}

// === وظيفة لعرض الشريط الجانبي عند الضغط على زر (في الهواتف فقط) ===
function setupMobileToggle() {
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'toggle-sidebar-btn';
    toggleBtn.className = 'btn btn-primary toggle-sidebar';
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    toggleBtn.style.position = 'fixed';
    toggleBtn.style.top = '10px';
    toggleBtn.style.left = '10px';
    toggleBtn.style.zIndex = '1001';
    toggleBtn.style.padding = '8px 12px';
    toggleBtn.style.fontSize = '14px';
    toggleBtn.style.borderRadius = '5px';
    toggleBtn.onclick = toggleSidebar;

    if (window.innerWidth <= 767) {
        document.body.appendChild(toggleBtn);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth <= 767) {
            if (!document.getElementById('toggle-sidebar-btn')) {
                document.body.appendChild(toggleBtn);
            }
        } else {
            const existingBtn = document.getElementById('toggle-sidebar-btn');
            if (existingBtn) {
                existingBtn.remove();
            }
        }
    });
}

// (مُعدَّل) === بدء التشغيل عند تحميل الصفحة ===
document.addEventListener('DOMContentLoaded', function () {
    setupSearch();
    setupCopyLinkButtons();
    
    // (جديد) تشغيل فحص الإنترنت عند التحميل والتكرار
    checkInternet();
    setInterval(checkInternet, 10000); 

    setupMobileToggle();

    // تحقق من حالة الشاشة عند التحميل
    if (window.innerWidth <= 767) {
        document.querySelector('.admin-sidebar')?.classList.add('mobile');
    }
});

// === إغلاق القائمة عند النقر على أي مكان في الصفحة (في الهواتف) ===
document.addEventListener('click', function (e) {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.getElementById('toggle-sidebar-btn');
    
    if (window.innerWidth <= 767 && sidebar && sidebar.classList.contains('active')) {
        if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
            sidebar.classList.remove('active');
        }
    }
});