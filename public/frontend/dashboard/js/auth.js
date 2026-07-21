/*
================================================================
  auth.js — نظام الصلاحيات المركزي
  يُحمَّل في كل صفحة قبل layout.js
================================================================
  الأدوار:
    admin   → مدير النظام       (كل الصلاحيات)
    manager → مدير المنظمة      (عرض + تقارير + طلبات النقل)
    data_entry → موظف إدخال بيانات (محدود بمخيمه)
================================================================
*/

var NDS = NDS || {};

/* ── تعريف الصلاحيات لكل دور ─────────────────────────────── */
NDS.permissions = {
  admin: {
    pages:    ['index','register','search','transfers','reports','families','users','settings'],
    actions:  ['view_all','edit_all','delete','manage_users','manage_camps',
               'approve_transfers','create_transfer','view_reports','export',
               'register_family','edit_family','search_local','search_global',
               'view_vuln','view_dashboard_full']
  },
  manager: {
    pages:    ['index','search','transfers','reports','families'],
    actions:  ['view_all','approve_transfers','create_transfer','view_reports',
               'export','search_global','view_vuln','view_dashboard_full',
               'edit_family']
  },
  data_entry: {
    pages:    ['index','register','search','transfers'],
    actions:  ['register_family','edit_family','search_local',
               'create_transfer','view_transfer_status',
               'view_dashboard_camp']
  }
};

/* ── دوال المساعدة ─────────────────────────────────────────── */
NDS.auth = {

  /* جلب الجلسة */
  getSession: function () {
    var s = sessionStorage.getItem('nds_user');
    return s ? JSON.parse(s) : null;
  },

  /* هل المستخدم مسجل الدخول؟ */
  isLoggedIn: function () {
    return !!this.getSession();
  },

  /* هل للمستخدم صلاحية معينة؟ */
  can: function (action) {
    var s = this.getSession();
    if (!s) return false;
    var perms = NDS.permissions[s.role];
    return perms && perms.actions.indexOf(action) !== -1;
  },

  /* هل للمستخدم وصول لصفحة معينة؟ */
  canAccessPage: function (page) {
    var s = this.getSession();
    if (!s) return false;
    var perms = NDS.permissions[s.role];
    return perms && perms.pages.indexOf(page) !== -1;
  },

  /* حماية الصفحة — يُستدعى في بداية كل صفحة */
  guardPage: function (pageKey) {
    if (!this.isLoggedIn()) {
      window.location.href = 'login.html';
      return false;
    }
    if (!this.canAccessPage(pageKey)) {
      window.location.href = 'index.html';
      return false;
    }
    return true;
  },

  /* الدور الحالي */
  role: function () {
    var s = this.getSession();
    return s ? s.role : null;
  },

  /* المخيم الحالي (للموظف فقط) */
  camp: function () {
    var s = this.getSession();
    return s ? s.camp : null;
  },

  /* الاسم */
  name: function () {
    var s = this.getSession();
    return s ? s.name : '';
  },

  /* هل هو موظف إدخال؟ */
  isStaff: function () { return this.role() === 'data_entry'; },

  /* هل هو مدير؟ */
  isManager: function () { return this.role() === 'manager'; },

  /* هل هو مدير نظام؟ */
  isAdmin: function () { return this.role() === 'admin'; },

  /* هل هو مدير أو أدمن؟ */
  isManagerOrAdmin: function () {
    var r = this.role();
    return r === 'admin' || r === 'manager';
  }
};

/*
  تطبيق الصلاحيات على DOM بعد تحميل الصفحة:
  - العناصر التي عليها data-require="action" تُخفى إن لم يكن للمستخدم الصلاحية
  - العناصر التي عليها data-role="admin,manager" تُخفى للأدوار الأخرى
*/
NDS.applyPermissions = function () {
  var self = this.auth;

  /* data-require="action" */
  $('[data-require]').each(function () {
    var action = $(this).data('require');
    if (!self.can(action)) {
      $(this).remove();
    }
  });

  /* data-role="admin,manager" */
  $('[data-role]').each(function () {
    var roles = $(this).data('role').split(',').map(function(r){ return r.trim(); });
    if (roles.indexOf(self.role()) === -1) {
      $(this).remove();
    }
  });

  /* data-hide-role="data_entry" — يُخفى من هذا الدور */
  $('[data-hide-role]').each(function () {
    var roles = $(this).data('hide-role').split(',').map(function(r){ return r.trim(); });
    if (roles.indexOf(self.role()) !== -1) {
      $(this).remove();
    }
  });
};
