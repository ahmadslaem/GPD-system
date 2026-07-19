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
          username: username,
          email: username,
          password: password
        }
      });
    },

    addMember: async function (familyId, payload) {
      return this.request('/family-members', {
        method: 'POST',
        body: Object.assign({ family_id: familyId }, payload)
      });
    }
  };

  window.NDS = NDS;
})();
