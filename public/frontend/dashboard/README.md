# نظام إدارة الأسر النازحة — دليل المشروع
## Displaced Families Data Management System

---

## هيكل المجلدات (Project Structure)

```
dashboard/
│
├── index.html          ← الصفحة الرئيسية (لوحة التحكم)
│
├── css/
│   └── style.css       ← جميع التنسيقات المخصصة
│
├── js/
│   └── main.js         ← كود jQuery والتفاعلية
│
└── README.md           ← هذا الملف
```

---

## التقنيات المستخدمة

| التقنية | الإصدار | الدور في المشروع |
|---------|---------|-----------------|
| HTML5 | — | هيكل الصفحة والمحتوى |
| CSS3 | — | التنسيق والتصميم والاستجابة |
| Bootstrap 5.3 RTL | 5.3.3 | الشبكة، المكونات، الاستجابة |
| jQuery | 3.7.1 | التفاعلية والأنيميشن |
| Font Awesome | 6.5 | الأيقونات |
| Google Fonts (Tajawal) | — | الخط العربي |

---

## كيفية فتح المشروع في VS Code

1. افتح VS Code
2. اذهب إلى: File → Open Folder
3. اختر مجلد `dashboard`
4. ثبّت امتداد **Live Server** من Extensions
5. كليك يمين على `index.html` → Open with Live Server

---

## مكونات الواجهة وربطها بالكود

### 1. القائمة الجانبية (Sidebar)
- **HTML**: عنصر `<nav id="sidebar">` يحتوي على `<ul class="nav flex-column">`
- **Bootstrap**: `d-flex flex-column`, `nav`, `nav-item`, `nav-link`
- **CSS**: `#sidebar` في style.css — اللون الداكن + الأبعاد
- **jQuery**: `$('#btnOpenSidebar').on('click', ...)` للموبايل

### 2. الشريط العلوي (Topbar)
- **HTML**: `<header id="topbar">`
- **Bootstrap**: `d-flex align-items-center justify-content-between`
- **CSS**: `position: sticky; top: 0` — يبقى ثابتاً عند التمرير
- **jQuery**: `$('#btnNotifications')` لقائمة الإشعارات

### 3. بطاقات الإحصاءات (Stat Cards)
- **HTML**: `<div class="stat-card stat-card--blue">`
- **Bootstrap Grid**: `col-12 col-sm-6 col-xl-3` — 1/2/4 أعمدة
- **CSS**: `.stat-card` + ألوان متغيرة بـ `--color-*`
- **jQuery**: `animateCounter()` — تأثير العد التصاعدي

### 4. التوزيع الجغرافي (Geo Bars)
- **HTML**: `<div class="geo-bar-fill" data-width="82">`
- **CSS**: `transition: width 1s ease` — الحركة السلسة
- **jQuery**: `$bar.css('width', targetWidth + '%')` بتأخير متفاوت

### 5. أشرطة الضعف (Vulnerability Progress Bars)
- **HTML**: `<div class="progress-bar bg-danger" data-width="25">`
- **Bootstrap**: مكون `progress` الجاهز
- **jQuery**: تحريك الـ width بعد تحميل الصفحة

### 6. الجدول (Table)
- **HTML**: `<table class="table table-hover">`
- **Bootstrap**: `table-responsive` للتمرير الأفقي على الموبايل
- **Bootstrap**: `d-none d-md-table-cell` لإخفاء الأعمدة على الموبايل
- **jQuery**: `$('#tableSearch').on('input', ...)` للفلترة

---

## الاستجابة (Responsive Breakpoints)

| الحجم | الشاشة | السلوك |
|-------|--------|--------|
| xs < 576px | موبايل صغير | السايدبار مخفي، عمود واحد |
| sm ≥ 576px | موبايل كبير | البطاقات 2×2 |
| md ≥ 768px | تابلت | البحث يظهر في التوبار |
| lg ≥ 992px | ديسكتوب | السايدبار دائم + 4 بطاقات |
| xl ≥ 1200px | شاشة كبيرة | خط أكبر، سايدبار أوسع |

---

## روابط CDN المستخدمة (للعمل بدون إنترنت: حمّلها محلياً)

```html
<!-- Bootstrap RTL -->
https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css
https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js

<!-- jQuery -->
https://code.jquery.com/jquery-3.7.1.min.js

<!-- Font Awesome -->
https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css

<!-- Google Fonts -->
https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700
```
