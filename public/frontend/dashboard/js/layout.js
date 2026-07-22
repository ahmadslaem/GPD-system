/*
================================================================
  layout.js — الهيكل المشترك بين جميع الصفحات
  يُحقن السايدبار والتوبار في كل صفحة تلقائياً
================================================================
*/
$(document).ready(function () {

  /* ── حقن السايدبار ─────────────────────────────────────── */
  var sidebarHTML = `
    <nav id="sidebar">
      <div class="sidebar-brand d-flex align-items-center gap-2 mb-4">
        <div class="brand-icon"><i class="fa-solid fa-house-circle-check"></i></div>
        <div>
          <div class="brand-title">إدارة النازحين</div>
          <div class="brand-sub">قطاع غزة</div>
        </div>
        <button class="btn-close-sidebar d-lg-none ms-auto" id="btnCloseSidebar">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="sidebar-user d-flex align-items-center gap-2 mb-3" id="sidebarUserInfo">
        <div class="user-avatar" id="sidebarUserAvatar">م.أ</div>
        <div>
          <div class="user-name" id="sidebarUserName">محمد أبو حماد</div>
          <div class="user-role">
            <span class="badge-role" id="sidebarUserRole">مدير النظام</span>
          </div>
        </div>
      </div>

      <!-- المخيم المحدد (يظهر فقط لموظف الإدخال) -->
      <div class="d-none mx-2 mb-3" id="campBadgeWrap">
        <div style="background:rgba(26,107,181,0.15);border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-location-dot" style="color:#7ab0e0;font-size:13px;"></i>
          <div>
            <div style="font-size:10px;color:rgba(255,255,255,0.5);">المخيم المحدد</div>
            <div style="font-size:12.5px;color:#fff;font-weight:600;" id="campBadgeText">—</div>
          </div>
        </div>
      </div>

      <ul class="nav flex-column sidebar-nav">
        <li class="nav-section-label">الرئيسية</li>
        <li class="nav-item">
          <a href="index.html" class="nav-link" data-page="index.html">
            <i class="fa-solid fa-gauge-high nav-icon"></i><span>لوحة التحكم</span>
          </a>
        </li>
        <li class="nav-section-label">إدارة البيانات</li>
        <li class="nav-item">
          <a href="register.html" class="nav-link" data-page="register.html">
            <i class="fa-solid fa-user-plus nav-icon"></i><span>تسجيل أسرة</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="search.html" class="nav-link" data-page="search.html">
            <i class="fa-solid fa-magnifying-glass nav-icon"></i><span>البحث عن أسرة</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="transfers.html" class="nav-link d-flex justify-content-between align-items-center" data-page="transfers.html">
            <span><i class="fa-solid fa-right-left nav-icon"></i><span>طلبات النقل</span></span>
          </a>
        </li>
        <li class="nav-section-label manager-only">التقارير</li>
        <li class="nav-item manager-only">
          <a href="reports.html" class="nav-link" data-page="reports.html">
            <i class="fa-solid fa-chart-bar nav-icon"></i><span>التقارير والإحصاءات</span>
          </a>
        </li>
        <li class="nav-item manager-only">
          <a href="families.html" class="nav-link" data-page="families.html">
            <i class="fa-solid fa-database nav-icon"></i><span>قاعدة بيانات الأسر</span>
          </a>
        </li>
        <li class="nav-section-label manager-only">الإدارة</li>
        <li class="nav-item manager-only">
          <a href="users.html" class="nav-link" data-page="users.html">
            <i class="fa-solid fa-users-gear nav-icon"></i><span>إدارة المستخدمين</span>
          </a>
        </li>
        <li class="nav-item manager-only">
          <a href="settings.html" class="nav-link" data-page="settings.html">
            <i class="fa-solid fa-gear nav-icon"></i><span>الإعدادات</span>
          </a>
        </li>
      </ul>

      <div class="sidebar-footer mt-auto">
        <a href="login.html" class="nav-link logout-link" id="btnLogout">
          <i class="fa-solid fa-arrow-right-from-bracket nav-icon"></i>
          <span>تسجيل الخروج</span>
        </a>
      </div>
    </nav>
  `;

  /* ── حقن التوبار ───────────────────────────────────────── */
  var topbarHTML = `
    <header id="topbar" class="d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button class="btn-toggle-sidebar d-lg-none" id="btnOpenSidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.html" style="color:#5a6a7a;font-size:13px;">الرئيسية</a></li>
            <li class="breadcrumb-item active" id="breadcrumbCurrent" style="font-size:13px;"></li>
          </ol>
        </nav>
      </div>
      <div class="d-flex align-items-center gap-2 gap-md-3">
        <div class="topbar-search d-none d-md-flex align-items-center">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text" id="quickSearch" class="form-control" placeholder="بحث برقم الهوية..." />
        </div>
        <div class="position-relative">
          <button class="btn-icon" id="btnNotifications" title="الإشعارات">
            <i class="fa-solid fa-bell"></i>
          </button>
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">الإشعارات</div>
            <ul class="notif-list">
              <li class="notif-item notif-info">
                <i class="fa-solid fa-circle-info"></i>
                <div><div class="notif-title">لا توجد إشعارات جديدة</div><div class="notif-time">الآن</div></div>
              </li>
            </ul>
          </div>
        </div>
        <div class="topbar-user d-flex align-items-center gap-2">
          <div class="user-avatar-sm" id="topbarAvatar">م.أ</div>
          <span class="d-none d-md-block user-name-sm" id="topbarName">محمد أبو حماد</span>
        </div>
      </div>
    </header>
    <div id="sidebarOverlay" class="sidebar-overlay d-lg-none"></div>
  `;

  /* ── تطبيق الحقن ───────────────────────────────────────── */
  if ($('#app-wrapper').length) {
    $('#app-wrapper').prepend(sidebarHTML);
    $('#main-content').prepend(topbarHTML);
    initLayout();
  }

  /* ── بيانات الجلسة (Session) ───────────────────────────── */
  function getSession() {
    var s = sessionStorage.getItem('nds_user');
    return s ? JSON.parse(s) : null;
  }

  function initLayout() {
    var session = getSession();

    /* إذا لا يوجد جلسة → ارجع لتسجيل الدخول */
    if (!session) {
      window.location.href = 'login.html';
      return;
    }

    /* تعيين بيانات المستخدم */
    var initials = session.name.split(' ').slice(0,2).map(w => w[0]).join('.');
    $('#sidebarUserAvatar, #topbarAvatar').text(initials);
    $('#sidebarUserName, #topbarName').text(session.name);
    $('#sidebarUserRole').text(session.roleLabel);

    /* إخفاء عناصر المدير إذا كان موظف إدخال */
    if (session.role === 'data_entry') {
      $('.manager-only').hide();
      /* عرض بادج المخيم */
      $('#campBadgeWrap').removeClass('d-none');
      $('#campBadgeText').text(session.camp || 'غير محدد');
    }

    /* تفعيل الرابط النشط */
    var page = window.location.pathname.split('/').pop() || 'index.html';
    var activePage = page === 'edit-family.html' ? 'families.html' : page;
    $('.sidebar-nav .nav-link[data-page="' + activePage + '"]').addClass('active');

    /* Breadcrumb */
    var titles = {
      'index.html':     'لوحة التحكم',
      'register.html':  'تسجيل أسرة',
      'search.html':    'البحث',
      'transfers.html': 'طلبات النقل',
      'reports.html':   'التقارير',
      'families.html':  'قاعدة البيانات',
      'edit-family.html': 'تعديل أسرة',
      'users.html':     'المستخدمون',
      'settings.html':  'الإعدادات'
    };
    $('#breadcrumbCurrent').text(titles[page] || titles[activePage] || '');

    /* أحداث السايدبار */
    $(document).on('click', '#btnOpenSidebar',  function () { openSidebar(); });
    $(document).on('click', '#btnCloseSidebar', function () { closeSidebar(); });
    $(document).on('click', '#sidebarOverlay',  function () { closeSidebar(); });
    $(window).on('resize', function () { if ($(window).width() >= 992) closeSidebar(); });

    /* الإشعارات */
    $(document).on('click', '#btnNotifications', function (e) {
      e.stopPropagation();
      $('#notifDropdown').toggleClass('show');
    });
    $(document).on('click', function (e) {
      if (!$(e.target).closest('#btnNotifications,#notifDropdown').length) {
        $('#notifDropdown').removeClass('show');
      }
    });

    /* البحث السريع */
    $(document).on('keypress', '#quickSearch', function (e) {
      if (e.which === 13 && $(this).val().trim()) {
        window.location.href = 'search.html?q=' + encodeURIComponent($(this).val().trim());
      }
    });

    /* تسجيل الخروج */
    $(document).on('click', '#btnLogout', async function (e) {
      e.preventDefault();
      try {
        if (window.NDS && NDS.api && NDS.api.getToken()) {
          await NDS.api.logout();
        }
      } catch (error) {
      }
      if (window.NDS && NDS.api) {
        NDS.api.clearSession();
      } else {
        sessionStorage.removeItem('nds_user');
        sessionStorage.removeItem('nds_token');
      }
      window.location.href = 'login.html';
    });
  }

  function openSidebar() {
    $('#sidebar').addClass('sidebar-open');
    $('#sidebarOverlay').addClass('active');
    $('body').css('overflow','hidden');
  }
  function closeSidebar() {
    $('#sidebar').removeClass('sidebar-open');
    $('#sidebarOverlay').removeClass('active');
    $('body').css('overflow','');
  }

});
