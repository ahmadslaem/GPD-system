(function () {
  var NDS = window.NDS || {};

  NDS.api = {
    baseUrl: window.API_BASE_URL || 'http://127.0.0.1:8000/api',

    setBaseUrl: function (url) {
      this.baseUrl = url;
      return this;
    },

    getToken: function () {
      return sessionStorage.getItem('nds_token');
    },

    setToken: function (token) {
      if (token) {
        sessionStorage.setItem('nds_token', token);
      }
    },

    clearToken: function () {
      sessionStorage.removeItem('nds_token');
    },

    getSession: function () {
      var raw = sessionStorage.getItem('nds_user');
      return raw ? JSON.parse(raw) : null;
    },

    setSession: function (user) {
      if (user) {
        sessionStorage.setItem('nds_user', JSON.stringify(user));
      }
    },

    clearSession: function () {
      sessionStorage.removeItem('nds_user');
      this.clearToken();
    },

    request: async function (path, options) {
      var method = (options && options.method) || 'GET';
      var headers = Object.assign({
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }, (options && options.headers) || {});

      var token = this.getToken();
      if (token && !headers.Authorization) {
        headers.Authorization = 'Bearer ' + token;
      }

      var config = {
        method: method,
        headers: headers
      };

      if (options && options.body !== undefined) {
        config.body = JSON.stringify(options.body);
      }

      var url = path.indexOf('http') === 0 ? path : this.baseUrl + path;
      var response = await fetch(url, config);

      var contentType = response.headers.get('content-type') || '';
      var data = contentType.includes('application/json')
        ? await response.json()
        : await response.text();

      if (!response.ok) {
        if (response.status === 401) {
          this.clearSession();
        }
        var errorMessage = data && (data.message || data.error || data.errors)
          ? (data.message || data.error || JSON.stringify(data.errors))
          : 'فشل الطلب';
        throw new Error(errorMessage);
      }

      return data;
    },

    login: async function (username, password) {
      return this.request('/login', {
        method: 'POST',
        body: {
          email: username,
          password: password
        }
      });
    },

    logout: async function () {
      return this.request('/logout', { method: 'POST' });
    },

    profile: async function () {
      return this.request('/profile');
    },

    camps: async function (params) {
      return this.request('/camps' + this.query(params));
    },

    selectCamp: async function (campId) {
      return this.request('/user/select-camp', {
        method: 'PATCH',
        body: { camp_id: campId }
      });
    },

    dashboard: async function () {
      return this.request('/dashboard');
    },

    families: async function () {
      return this.request('/families');
    },

    family: async function (id) {
      return this.request('/families/' + encodeURIComponent(id));
    },

    createFamily: async function (payload) {
      return this.request('/families', {
        method: 'POST',
        body: payload
      });
    },

    updateFamily: async function (id, payload) {
      return this.request('/families/' + encodeURIComponent(id), {
        method: 'PUT',
        body: payload
      });
    },

    deleteFamily: async function (id) {
      return this.request('/families/' + encodeURIComponent(id), {
        method: 'DELETE'
      });
    },

    checkNationalId: async function (nationalId) {
      return this.request('/families/check/' + encodeURIComponent(nationalId));
    },

    addMember: async function (familyId, payload) {
      return this.request('/families/' + encodeURIComponent(familyId) + '/members', {
        method: 'POST',
        body: payload
      });
    },

    updateMember: async function (memberId, payload) {
      return this.request('/members/' + encodeURIComponent(memberId), {
        method: 'PUT',
        body: payload
      });
    },

    deleteMember: async function (memberId) {
      return this.request('/members/' + encodeURIComponent(memberId), {
        method: 'DELETE'
      });
    },

    transfers: async function (params) {
      return this.request('/transfer-requests' + this.query(params));
    },

    createTransfer: async function (payload) {
      return this.request('/transfer-requests', {
        method: 'POST',
        body: payload
      });
    },

    approveTransfer: async function (id, managerNote) {
      return this.request('/transfer-requests/' + encodeURIComponent(id) + '/approve', {
        method: 'PATCH',
        body: { manager_note: managerNote || '' }
      });
    },

    rejectTransfer: async function (id, managerNote) {
      return this.request('/transfer-requests/' + encodeURIComponent(id) + '/reject', {
        method: 'PATCH',
        body: { manager_note: managerNote || '' }
      });
    },

    users: async function () {
      return this.request('/users');
    },

    usersStatistics: async function () {
      return this.request('/users/statistics');
    },

    createUser: async function (payload) {
      return this.request('/users', {
        method: 'POST',
        body: payload
      });
    },

    updateUser: async function (id, payload) {
      return this.request('/users/' + encodeURIComponent(id), {
        method: 'PUT',
        body: payload
      });
    },

    deleteUser: async function (id) {
      return this.request('/users/' + encodeURIComponent(id), {
        method: 'DELETE'
      });
    },

    toggleUserStatus: async function (id) {
      return this.request('/users/' + encodeURIComponent(id) + '/toggle-status', {
        method: 'POST'
      });
    },

    report: async function (type, params) {
      return this.request('/reports/' + encodeURIComponent(type) + this.query(params));
    },

    reportExportUrl: function (type, format, params) {
      return this.baseUrl + '/reports/' + encodeURIComponent(type) + '/export/' + encodeURIComponent(format) + this.query(params);
    },

    downloadFile: async function (path, filename) {
      var headers = { 'Accept': '*/*' };
      var token = this.getToken();
      if (token) {
        headers.Authorization = 'Bearer ' + token;
      }

      var url = path.indexOf('http') === 0 ? path : this.baseUrl + path;
      var response = await fetch(url, {
        method: 'GET',
        headers: headers
      });

      if (!response.ok) {
        if (response.status === 401) {
          this.clearSession();
        }

        var contentType = response.headers.get('content-type') || '';
        var data = contentType.includes('application/json')
          ? await response.json()
          : await response.text();
        var errorMessage = data && (data.message || data.error || data.errors)
          ? (data.message || data.error || JSON.stringify(data.errors))
          : 'فشل تحميل الملف';
        throw new Error(errorMessage);
      }

      var blob = await response.blob();
      var disposition = response.headers.get('content-disposition') || '';
      var match = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i);
      var downloadName = filename || (match ? decodeURIComponent(match[1] || match[2]) : 'download');
      var objectUrl = URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = objectUrl;
      link.download = downloadName;
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1000);
    },

    search: async function (scope, keyword) {
      return this.request('/search/' + encodeURIComponent(scope) + this.query({ keyword: keyword }));
    },

    query: function (params) {
      params = params || {};
      var query = Object.keys(params)
        .filter(function (key) { return params[key] !== undefined && params[key] !== null && params[key] !== ''; })
        .map(function (key) { return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]); })
        .join('&');
      return query ? '?' + query : '';
    }
  };

  window.NDS = NDS;
})();
