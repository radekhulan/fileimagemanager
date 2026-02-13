/**
 * File Image Manager - TinyMCE Plugin
 *
 * Usage:
 *   tinymce.init({
 *     external_plugins: {
 *       fileimagemanager: '/public/tinymce/plugin.js'
 *     },
 *     toolbar: '... fileimagemanager',
 *     fileimagemanager_url: '/public/',          // File manager URL (default: auto-detect from plugin path)
 *     fileimagemanager_crossdomain: false,        // Cross-domain mode (default: false)
 *     fileimagemanager_title: 'File Image Manager', // Dialog title
 *   });
 */
(function () {
  'use strict';

  var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'avif'];
  var videoExts = ['mp4', 'webm', 'ogg'];
  var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];

  function getExtension(url) {
    return (url.split('?')[0].split('.').pop() || '').toLowerCase();
  }

  function escapeHtmlAttr(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function toRelativeUrl(url) {
    if (url && typeof url === 'string') {
      try {
        var urlObj = new URL(url, window.location.origin);
        if (urlObj.origin === window.location.origin) {
          return urlObj.pathname;
        }
      } catch (e) {
        return url.replace(/^https?:\/\/[^\/]+/, '');
      }
    }
    return url;
  }

  tinymce.PluginManager.add('fileimagemanager', function (editor) {
    editor.options.register('fileimagemanager_url', { processor: 'string', default: '' });
    editor.options.register('fileimagemanager_crossdomain', { processor: 'boolean', default: false });
    editor.options.register('fileimagemanager_title', { processor: 'string', default: 'File Image Manager' });

    // Derive expected origin for postMessage validation
    var expectedOrigin = window.location.origin;

    function getBaseUrl() {
      var pluginUrl = editor.options.get('fileimagemanager_url');
      if (pluginUrl) {
        try {
          expectedOrigin = new URL(pluginUrl, window.location.origin).origin;
        } catch (e) { /* keep default */ }
        return pluginUrl;
      }

      var scripts = document.querySelectorAll('script[src*="fileimagemanager"][src*="plugin"]');
      for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].getAttribute('src');
        if (src) {
          var base = src.replace(/\/tinymce\/plugin(\.min)?\.js(\?.*)?$/, '/');
          if (base !== src) {
            try {
              expectedOrigin = new URL(base, window.location.origin).origin;
            } catch (e) { /* keep default */ }
            return base;
          }
        }
      }
      return '/public/';
    }

    function isValidOrigin(eventOrigin) {
      return eventOrigin === window.location.origin || eventOrigin === expectedOrigin;
    }

    function openManager(callback, filetype) {
      var base = getBaseUrl();
      var crossdomain = editor.options.get('fileimagemanager_crossdomain') ? '1' : '0';
      var sep = base.indexOf('?') === -1 ? '?' : '&';
      var url = base + sep + 'editor=tinymce&popup=1&crossdomain=' + crossdomain;
      if (filetype) url += '&type=' + filetype;
      var title = editor.options.get('fileimagemanager_title') || 'File Image Manager';

      var width = window.innerWidth - 20;
      var height = window.innerHeight - 40;
      if (width > 1800) width = 1800;
      if (height > 1200) height = 1200;

      editor.focus(true);

      var dialogApi = null;

      function handler(e) {
        if (e.data && e.data.sender === 'fileimagemanager' && isValidOrigin(e.origin)) {
          window.removeEventListener('message', handler);
          callback(toRelativeUrl(e.data.url));
          if (dialogApi) dialogApi.close();
        }
      }

      window.addEventListener('message', handler);

      dialogApi = editor.windowManager.openUrl({
        title: title,
        url: url,
        width: width,
        height: height,
        onClose: function () {
          window.removeEventListener('message', handler);
        },
      });
    }

    function insertFromManager(url) {
      var ext = getExtension(url);
      var selectedHtml = editor.selection.getContent();
      var safeUrl = escapeHtmlAttr(url);

      if (selectedHtml) {
        editor.insertContent('<a href="' + safeUrl + '">' + selectedHtml + '</a>');
      } else if (imageExts.indexOf(ext) !== -1) {
        editor.insertContent('<img src="' + safeUrl + '" alt="" />');
      } else if (videoExts.indexOf(ext) !== -1) {
        editor.insertContent('<video src="' + safeUrl + '" controls></video>');
      } else if (audioExts.indexOf(ext) !== -1) {
        editor.insertContent('<audio src="' + safeUrl + '" controls></audio>');
      } else {
        var filename = escapeHtml(url.split('/').pop() || 'file');
        editor.insertContent('<a href="' + safeUrl + '">' + filename + '</a>');
      }
    }

    // Auto-set file_picker_callback for image/media/link dialogs
    editor.options.set('file_picker_types', 'file image media');
    editor.options.set('file_picker_callback', function (cb, _value, _meta) {
      openManager(function (url) {
        cb(url, { alt: '' });
      }, _meta && _meta.filetype);
    });

    // Toolbar button
    editor.ui.registry.addButton('fileimagemanager', {
      icon: 'browse',
      tooltip: 'File Image Manager',
      onAction: function () {
        openManager(function (url) {
          insertFromManager(url);
        });
      },
    });

    // Menu item
    editor.ui.registry.addMenuItem('fileimagemanager', {
      icon: 'browse',
      text: 'File Image Manager',
      onAction: function () {
        openManager(function (url) {
          insertFromManager(url);
        });
      },
    });

    return {
      getMetadata: function () {
        return {
          name: 'File Image Manager',
          url: 'https://github.com/radekhulan/fileimagemanager',
        };
      },
    };
  });
})();
