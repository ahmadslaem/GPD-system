/*
================================================================
  main.js — نظام إدارة الأسر النازحة
================================================================
  التقنية المستخدمة: jQuery 3.7
  
  المهام:
  1. فتح/إغلاق السايدبار على الموبايل
  2. قائمة الإشعارات المنسدلة
  3. تأثير العداد التصاعدي في البطاقات
  4. تحريك الأشرطة الجغرافية
  5. تحريك أشرطة Bootstrap Progress
  6. فلترة الجدول بالبحث
  7. تفعيل الرابط النشط في القائمة
================================================================
*/


/*
  $(document).ready() — ينتظر jQuery حتى يكتمل تحميل DOM
  كل الكود يُوضع بداخله لضمان وجود العناصر قبل التفاعل معها
*/
$(document).ready(function () {


  /* ============================================================
    1. السايدبار (Sidebar Toggle) — للموبايل والتابلت
    
    الفكرة:
    - عند الضغط على #btnOpenSidebar  → أضف .sidebar-open للسايدبار
                                       وأظهر الـ overlay
    - عند الضغط على #btnCloseSidebar → أزل .sidebar-open
    - عند الضغط على الـ overlay      → أغلق السايدبار
    
    CSS المرتبط:
    - #sidebar.sidebar-open { transform: translateX(0) }
    - .sidebar-overlay.active { opacity: 1 }
  ============================================================ */
  function openSidebar() {
    $('#sidebar').addClass('sidebar-open');
    $('#sidebarOverlay').addClass('active');
    $('body').css('overflow', 'hidden'); /* امنع التمرير خلف السايدبار */
  }

  function closeSidebar() {
    $('#sidebar').removeClass('sidebar-open');
    $('#sidebarOverlay').removeClass('active');
    $('body').css('overflow', '');
  }

  $('#btnOpenSidebar').on('click', openSidebar);
  $('#btnCloseSidebar').on('click', closeSidebar);
  $('#sidebarOverlay').on('click', closeSidebar);

  /* اغلق السايدبار تلقائياً عند تغيير حجم الشاشة للديسكتوب */
  $(window).on('resize', function () {
    if ($(window).width() >= 992) {
      closeSidebar();
    }
  });


  /* ============================================================
    2. قائمة الإشعارات المنسدلة (Notifications Dropdown)
    
    jQuery المستخدم:
    - .toggleClass('show')  → يضيف/يزيل class بضربة واحدة
    - $(document).on('click') → يغلق القائمة عند الضغط خارجها
    - .closest()            → يتحقق إذا الضغط كان داخل الـ dropdown
  ============================================================ */
  $('#btnNotifications').on('click', function (e) {
    e.stopPropagation(); /* منع انتشار الحدث للـ document */
    $('#notifDropdown').toggleClass('show');
  });

  /* إغلاق الإشعارات عند الضغط في أي مكان آخر */
  $(document).on('click', function (e) {
    if (!$(e.target).closest('#btnNotifications, #notifDropdown').length) {
      $('#notifDropdown').removeClass('show');
    }
  });


  /* ============================================================
    3. تأثير العداد التصاعدي (Counter Animation)
    
    الفكرة: كل بطاقة إحصاء تبدأ من 0 وتصل للقيمة المستهدفة
    
    jQuery المستخدم:
    - data('target')     → قراءة القيمة من data-target=""
    - .text()            → تغيير النص
    - $({})              → animate على كائن مخصص (Counter Trick)
    
    تقنية الـ Counter:
    jQuery لا يدعم animate على الأرقام مباشرة،
    لذا نستخدم $.animate على كائن مؤقت { count: 0 }
    ونحدّث الـ DOM في كل خطوة
  ============================================================ */
  function animateCounter($el) {
    var target = parseInt($el.data('target')); /* القيمة النهائية */
    var duration = 1500; /* مدة التحريك بالميلي ثانية */

    /* jQuery animate على كائن مؤقت */
    $({ count: 0 }).animate(
      { count: target },
      {
        duration: duration,
        easing: 'swing',
        step: function () {
          /* في كل خطوة: حدّث النص بالقيمة الحالية مع فواصل الآلاف */
          $el.text(Math.floor(this.count).toLocaleString('ar-EG'));
        },
        complete: function () {
          /* عند الانتهاء: اعرض القيمة الدقيقة */
          $el.text(target.toLocaleString('ar-EG'));
        }
      }
    );
  }

  /* شغّل العداد لكل .stat-card__value */
  $('.stat-card__value').each(function () {
    animateCounter($(this));
  });


  /* ============================================================
    4. تحريك الأشرطة الجغرافية (Geo Bars Animation)
    
    الفكرة:
    - كل .geo-bar-fill عنده data-width="82" (نسبة مئوية)
    - نستخدم jQuery .animate() لتغيير الـ width من 0 إلى القيمة
    
    jQuery المستخدم:
    - .each()         → تكرار على كل شريط
    - .data('width')  → قراءة data-width
    - .delay()        → تأخير متفاوت لتأثير متتالي
    - .animate()      → تحريك الـ CSS property
    
    ملاحظة: CSS Transition في style.css يتكفل بالسلاسة
  ============================================================ */
  setTimeout(function () {
    /* نستخدم setTimeout بدلاً من .delay() لأن width CSS transition
       تحتاج لحظة بعد تحميل الصفحة لتعمل */
    $('.geo-bar-fill').each(function (index) {
      var $bar = $(this);
      var targetWidth = $bar.data('width'); /* القراءة من data-width */

      setTimeout(function () {
        /* CSS transition في style.css تتكفل بالحركة السلسة */
        $bar.css('width', targetWidth + '%');
      }, index * 120); /* تأخير متفاوت: 0, 120, 240, 360ms... */
    });
  }, 300); /* انتظر 300ms بعد تحميل الصفحة */


  /* ============================================================
    5. تحريك Bootstrap Progress Bars
    
    نفس فكرة الأشرطة الجغرافية، لكن مع Bootstrap progress bars
    التي تستخدم aria-valuenow و width
  ============================================================ */
  setTimeout(function () {
    $('.progress-bar[data-width]').each(function (index) {
      var $bar = $(this);
      var targetWidth = $bar.data('width');

      setTimeout(function () {
        $bar.css('width', targetWidth + '%');
        $bar.attr('aria-valuenow', targetWidth);
      }, index * 200);
    });
  }, 400);


  /* ============================================================
    6. البحث والفلترة في الجدول (Table Search Filter)
    
    الفكرة:
    - عند الكتابة في #tableSearch → ابحث في صفوف الجدول
    - إذا النص موجود في الصف → أظهره
    - إذا غير موجود → أخفِه
    
    jQuery المستخدم:
    - .on('input')    → حدث الكتابة في حقل النص
    - .val()          → قراءة قيمة الحقل
    - .text().toLowerCase() → النص بحروف صغيرة للمقارنة
    - .show() / .hide()     → إظهار/إخفاء الصفوف
    - .includes()           → JS String method للبحث
  ============================================================ */
  $('#tableSearch').on('input', function () {
    var searchText = $(this).val().toLowerCase().trim();

    /* ابحث في كل صف في tbody */
    $('#familiesTable tbody tr').each(function () {
      var rowText = $(this).text().toLowerCase();

      if (searchText === '' || rowText.includes(searchText)) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });

    /* اعرض رسالة "لا توجد نتائج" إذا كل الصفوف مخفية */
    var visibleRows = $('#familiesTable tbody tr:visible').length;
    if (visibleRows === 0 && searchText !== '') {
      /* إذا لم يكن الصف موجوداً بعد، أضفه */
      if ($('#noResultsRow').length === 0) {
        $('#familiesTable tbody').append(
          '<tr id="noResultsRow"><td colspan="8" class="text-center text-muted py-4">' +
          '<i class="fa-solid fa-search me-2"></i>لا توجد نتائج مطابقة للبحث' +
          '</td></tr>'
        );
      }
    } else {
      $('#noResultsRow').remove();
    }
  });


  /* ============================================================
    7. البحث السريع في التوبار (Quick Search)
    
    عند الضغط على Enter في حقل البحث السريع،
    انقل المستخدم لصفحة البحث مع الـ query
  ============================================================ */
  $('#quickSearch').on('keypress', function (e) {
    if (e.which === 13) { /* مفتاح Enter */
      var searchVal = $(this).val().trim();
      if (searchVal.length > 0) {
        /* انقل لصفحة البحث مع query parameter */
        window.location.href = 'search.html?q=' + encodeURIComponent(searchVal);
      }
    }
  });


  /* ============================================================
    8. تفعيل الرابط النشط (Active Nav Link)
    
    jQuery يحدد الرابط المطابق للصفحة الحالية
    ويضيف له class="active"
  ============================================================ */
  var currentPage = window.location.pathname.split('/').pop() || 'index.html';

  $('.sidebar-nav .nav-link').each(function () {
    var linkHref = $(this).attr('href');
    if (linkHref === currentPage) {
      $(this).addClass('active');
    } else {
      $(this).removeClass('active');
    }
  });


  /* ============================================================
    9. زر التحديث (Refresh Button)
  ============================================================ */
  $('#btnRefresh').on('click', function () {
    var $icon = $(this).find('i');
    $icon.addClass('fa-spin'); /* Bootstrap FA spin animation */

    /* محاكاة التحديث (في المشروع الحقيقي: AJAX call) */
    setTimeout(function () {
      $icon.removeClass('fa-spin');
    }, 1500);
  });


  /* ============================================================
    10. نظام الـ Tooltips (Bootstrap Tooltips)
    
    Bootstrap 5 لا يفعّل الـ tooltips تلقائياً،
    يجب تفعيلها يدوياً بـ jQuery أو JS
  ============================================================ */
  /* تفعيل كل العناصر التي لها data-bs-toggle="tooltip" */
  var tooltipEls = $('[title]').filter('button, a');
  tooltipEls.each(function () {
    /* استخدام Bootstrap Tooltip API */
    new bootstrap.Tooltip(this, {
      placement: 'top',
      trigger: 'hover'
    });
  });


  /* ============================================================
    11. حدث الضغط على صفوف الجدول
    
    jQuery Event Delegation — يعمل حتى مع الصفوف المضافة لاحقاً
    .on('click', 'selector', handler)
  ============================================================ */
  $('#familiesTable').on('click', '.btn-outline-primary', function () {
    /* استخرج رقم الأسرة من نفس الصف */
    var familyId = $(this).closest('tr').find('.family-id').text().trim();
    alert('عرض ملف الأسرة: ' + familyId);
    /* في المشروع الحقيقي: window.location.href = 'family-profile.html?id=' + familyId */
  });


}); /* نهاية $(document).ready() */
