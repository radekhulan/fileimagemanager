/**
 * File Image Manager - TinyMCE Plugin
 *
 * Usage:
 *   tinymce.init({
 *     external_plugins: {
 *       fileimagemanager: '/public/tinymce/plugin.js'
 *     },
 *     toolbar: '... fileimagemanager',
 *     fileimagemanager_url: '/public/',             // File manager URL (default: auto-detect from plugin path)
 *     fileimagemanager_crossdomain: false,           // Cross-domain mode (default: false)
 *     fileimagemanager_title: 'File Image Manager',  // Dialog title
 *     fileimagemanager_dragdrop: true,               // Drag & drop images onto the editor (default: true)
 *   });
 *
 * Drag & drop: when enabled (and allowed by the server config `dragdrop_upload`),
 * dropping image files straight onto the editor uploads them to the configured
 * folder (server option `dragdrop_path`, e.g. cms/{YYYY}/{MM}/{DD}) and opens a
 * small window to insert each one — as a preview linked to the full image, or as
 * the full image. Works with multiple editors on one page (handlers are per editor).
 */
(function () {
  'use strict';

  var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'avif'];
  var videoExts = ['mp4', 'webm', 'ogg'];
  var audioExts = ['mp3', 'wav', 'ogg', 'm4a'];

  // Per-base-URL session cache shared across all editors on the page.
  var sessions = {};
  var stylesInjected = false;

  function getExtension(url) {
    return (url.split('?')[0].split('.').pop() || '').toLowerCase();
  }

  function escapeHtmlAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function toRelativeUrl(url) {
    if (url && typeof url === 'string') {
      try {
        var urlObj = new URL(url, window.location.origin);
        if (urlObj.origin === window.location.origin) {
          return urlObj.pathname + urlObj.search;
        }
      } catch (e) {
        return url.replace(/^https?:\/\/[^\/]+/, '');
      }
    }
    return url;
  }

  // Load (and cache) the manager session: CSRF token, client config, translations.
  function loadSession(base) {
    if (sessions[base]) return sessions[base];
    sessions[base] = fetch(base + 'api/session/init', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        return { csrf: (d && d.csrfToken) || '', config: (d && d.config) || {}, t: (d && d.translations) || {} };
      })
      .catch(function () {
        sessions[base] = null; // allow retry on next attempt
        return { csrf: '', config: {}, t: {} };
      });
    return sessions[base];
  }

  function tr(session, key, fallback) {
    return (session && session.t && session.t[key]) || fallback;
  }

  function injectStyles() {
    if (stylesInjected) return;
    stylesInjected = true;
    var css =
      '.fim-dd-overlay{position:fixed;inset:0;z-index:2000000;display:flex;align-items:center;justify-content:center;' +
      'background:rgba(15,23,42,.55);font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}' +
      '.fim-dd-modal{background:#fff;color:#0f172a;border-radius:12px;box-shadow:0 30px 60px rgba(0,0,0,.35);' +
      'width:min(900px,94vw);max-height:88vh;display:flex;flex-direction:column;overflow:hidden}' +
      '.fim-dd-head{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid #e2e8f0}' +
      '.fim-dd-head h3{margin:0;font-size:15px;font-weight:600}' +
      '.fim-dd-x{margin-left:auto;border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#64748b;padding:0 4px}' +
      '.fim-dd-x:hover{color:#0f172a}' +
      '.fim-dd-body{padding:16px;overflow:auto}' +
      '.fim-dd-status{padding:28px 16px;text-align:center;color:#64748b}' +
      '.fim-dd-spin{width:26px;height:26px;border:3px solid #cbd5e1;border-top-color:#2563eb;border-radius:50%;' +
      'margin:0 auto 12px;animation:fim-dd-rot .8s linear infinite}' +
      '@keyframes fim-dd-rot{to{transform:rotate(360deg)}}' +
      '.fim-dd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}' +
      '.fim-dd-item{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;display:flex;flex-direction:column}' +
      '.fim-dd-thumb{height:150px;background:#f1f5f9 center/cover no-repeat;border-bottom:1px solid #e2e8f0}' +
      '.fim-dd-name{padding:7px 10px 0;font-size:12px;color:#475569;word-break:break-all}' +
      '.fim-dd-btns{display:flex;gap:6px;padding:10px}' +
      '.fim-dd-btn{flex:1;cursor:pointer;border:1px solid #cbd5e1;background:#fff;color:#1e293b;border-radius:7px;' +
      'padding:7px 8px;font-size:12.5px;font-weight:550;transition:background .12s,border-color .12s}' +
      '.fim-dd-btn:hover{background:#f1f5f9}' +
      '.fim-dd-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}' +
      '.fim-dd-btn.primary:hover{background:#1d4ed8}' +
      '.fim-dd-item.is-done{box-shadow:inset 0 0 0 2px #16a34a}' +
      '.fim-dd-item.is-done .fim-dd-name::after{content:" \\2713";color:#16a34a;font-weight:700}' +
      '.fim-dd-foot{display:flex;justify-content:flex-end;gap:10px;padding:12px 16px;border-top:1px solid #e2e8f0}' +
      '.fim-dd-foot .fim-dd-btn{flex:0 0 auto;min-width:110px}' +
      '.fim-dd-err{color:#b91c1c}';
    var el = document.createElement('style');
    el.setAttribute('data-fim-dragdrop', '1');
    el.textContent = css;
    document.head.appendChild(el);
  }

  tinymce.PluginManager.add('fileimagemanager', function (editor) {
    editor.options.register('fileimagemanager_url', { processor: 'string', default: '' });
    editor.options.register('fileimagemanager_crossdomain', { processor: 'boolean', default: false });
    editor.options.register('fileimagemanager_title', { processor: 'string', default: 'File Image Manager' });
    editor.options.register('fileimagemanager_dragdrop', { processor: 'boolean', default: true });

    // Derive expected origin for postMessage validation
    var expectedOrigin = window.location.origin;

    function getBaseUrl() {
      var pluginUrl = editor.options.get('fileimagemanager_url');
      if (pluginUrl) {
        try {
          expectedOrigin = new URL(pluginUrl, window.location.origin).origin;
        } catch (e) { /* keep default */ }
        return pluginUrl.slice(-1) === '/' ? pluginUrl : pluginUrl + '/';
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

    // ----------------------------------------------------------------------
    //  Drag & drop image upload
    // ----------------------------------------------------------------------
    function imageFilesFromDataTransfer(dt) {
      var out = [];
      if (!dt) return out;
      var list = dt.files;
      if (!list || !list.length) return out;
      for (var i = 0; i < list.length; i++) {
        var f = list[i];
        if (f && f.type && f.type.indexOf('image/') === 0) out.push(f);
      }
      return out;
    }

    function uploadDropped(base, files) {
      return loadSession(base).then(function (session) {
        if (session && session.config && session.config.dragDropUpload === false) {
          return { disabled: true, session: session };
        }
        var fd = new FormData();
        for (var i = 0; i < files.length; i++) fd.append('files[]', files[i], files[i].name);
        return fetch(base + 'api/upload/dragdrop', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-CSRF-Token': session.csrf },
          body: fd,
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (!res.ok || !res.data || res.data.success === false) {
              throw new Error((res.data && res.data.error) || 'Upload failed');
            }
            return { files: res.data.files || [], session: session };
          });
      });
    }

    function insertPreviewLink(item) {
      var full = escapeHtmlAttr(toRelativeUrl(item.url));
      var thumb = escapeHtmlAttr(toRelativeUrl(item.thumbUrl || item.url));
      editor.insertContent('<a href="' + full + '"><img src="' + thumb + '" alt="" /></a>');
    }

    function insertFullImage(item) {
      var full = escapeHtmlAttr(toRelativeUrl(item.url));
      editor.insertContent('<img src="' + full + '" alt="" />');
    }

    function openInsertWindow(session, files) {
      injectStyles();
      var overlay = document.createElement('div');
      overlay.className = 'fim-dd-overlay';

      function close() {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        document.removeEventListener('keydown', onKey);
      }
      function onKey(e) { if (e.key === 'Escape') close(); }
      document.addEventListener('keydown', onKey);
      overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(); });

      var insertPreviewLabel = tr(session, 'DragDrop_insert_preview', 'Insert preview');
      var insertImageLabel = tr(session, 'DragDrop_insert_image', 'Insert image');

      var itemsHtml = files.map(function (f, idx) {
        var thumb = escapeHtmlAttr(toRelativeUrl(f.thumbUrl || f.url));
        return '<div class="fim-dd-item">' +
          '<div class="fim-dd-thumb" style="background-image:url(\'' + thumb + '\')"></div>' +
          '<div class="fim-dd-name">' + escapeHtml(f.name || '') + '</div>' +
          '<div class="fim-dd-btns">' +
          '<button type="button" class="fim-dd-btn primary" data-act="preview" data-i="' + idx + '">' + escapeHtml(insertPreviewLabel) + '</button>' +
          '<button type="button" class="fim-dd-btn" data-act="image" data-i="' + idx + '">' + escapeHtml(insertImageLabel) + '</button>' +
          '</div></div>';
      }).join('');

      var closeLabel = tr(session, 'DragDrop_close', 'Close');
      overlay.innerHTML =
        '<div class="fim-dd-modal" role="dialog" aria-modal="true">' +
        '<div class="fim-dd-head"><h3>' + escapeHtml(tr(session, 'DragDrop_uploaded', 'Uploaded images')) + '</h3>' +
        '<button type="button" class="fim-dd-x" aria-label="' + escapeHtmlAttr(closeLabel) + '">&times;</button></div>' +
        '<div class="fim-dd-body"><div class="fim-dd-grid">' + itemsHtml + '</div></div>' +
        '<div class="fim-dd-foot"><button type="button" class="fim-dd-btn primary fim-dd-close">' + escapeHtml(closeLabel) + '</button></div>' +
        '</div>';

      overlay.querySelector('.fim-dd-x').addEventListener('click', close);
      overlay.querySelector('.fim-dd-close').addEventListener('click', close);
      overlay.querySelector('.fim-dd-grid').addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.fim-dd-btn') : null;
        if (!btn) return;
        var item = files[parseInt(btn.getAttribute('data-i'), 10)];
        if (!item) return;
        if (btn.getAttribute('data-act') === 'preview') insertPreviewLink(item);
        else insertFullImage(item);
        // Keep the window open so the other images can still be inserted;
        // auto-close only when there was a single image.
        if (files.length === 1) { close(); return; }
        var card = btn.closest('.fim-dd-item');
        if (card) card.classList.add('is-done');
      });

      document.body.appendChild(overlay);
    }

    function openStatusWindow(session) {
      injectStyles();
      var overlay = document.createElement('div');
      overlay.className = 'fim-dd-overlay';
      overlay.innerHTML =
        '<div class="fim-dd-modal"><div class="fim-dd-body"><div class="fim-dd-status">' +
        '<div class="fim-dd-spin"></div>' + escapeHtml(tr(session, 'Uploading', 'Uploading...')) +
        '</div></div></div>';
      document.body.appendChild(overlay);
      return {
        remove: function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); },
        error: function (msg) {
          overlay.querySelector('.fim-dd-body').innerHTML =
            '<div class="fim-dd-status fim-dd-err">' + escapeHtml(msg) + '</div>';
          setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 2500);
        },
      };
    }

    function setCaretFromPoint(x, y) {
      try {
        var doc = editor.getDoc();
        var rng = null;
        if (doc.caretRangeFromPoint) {
          rng = doc.caretRangeFromPoint(x, y);
        } else if (doc.caretPositionFromPoint) {
          var p = doc.caretPositionFromPoint(x, y);
          if (p) { rng = doc.createRange(); rng.setStart(p.offsetNode, p.offset); rng.collapse(true); }
        }
        if (rng) editor.selection.setRng(rng);
      } catch (e) { /* ignore */ }
    }

    function onDragOver(e) {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;
      if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
        e.preventDefault();
      }
    }

    function onDrop(e) {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;
      var files = imageFilesFromDataTransfer(e.dataTransfer);
      if (!files.length) return; // let TinyMCE handle non-image drops normally

      var base = getBaseUrl();
      var warm = sessions[base] && sessions[base].config;
      // If the server has the feature switched off, don't hijack the drop.
      if (warm && warm.dragDropUpload === false) return;

      e.preventDefault();
      e.stopImmediatePropagation();
      setCaretFromPoint(e.clientX, e.clientY);

      var status = openStatusWindow(null);
      uploadDropped(base, files).then(function (result) {
        status.remove();
        if (!result) return;
        if (result.disabled) return; // server disabled mid-flight
        if (result.files && result.files.length) {
          openInsertWindow(result.session, result.files);
        }
      }).catch(function (err) {
        status.error((err && err.message) || 'Upload failed');
      });
    }

    editor.on('init', function () {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;
      // Warm the session (CSRF + config + translations) so the first drop is instant.
      loadSession(getBaseUrl());
      var doc = editor.getDoc();
      if (doc) {
        doc.addEventListener('dragover', onDragOver, true);
        doc.addEventListener('drop', onDrop, true);
      }
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
