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
 * dropping files straight onto the editor uploads them to the configured folder
 * (server option `dragdrop_path`, e.g. cms/{YYYY}/{MM}/{DD}) and opens a small
 * window to insert each one — images as a preview linked to the full image or as
 * the full image, other files (PDF, …) as a link. Hovering a tile shows a red
 * cross that deletes that upload (file + thumbnail) server-side and removes it
 * from the window, no confirmation. Works with multiple editors on one page
 * (handlers are per editor).
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
    // Styles are hardened with !important + an explicit reset so a host page's
    // global button/typography CSS can't override the window (colours, centring…).
    var css =
      '.fim-dd-overlay{position:fixed!important;inset:0!important;z-index:2147483600!important;display:flex!important;align-items:center!important;justify-content:center!important;' +
      'background:rgba(15,23,42,.55)!important;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif!important}' +
      '.fim-dd-overlay *,.fim-dd-overlay *::before,.fim-dd-overlay *::after{box-sizing:border-box!important;font-family:inherit!important}' +
      '.fim-dd-modal{background:#fff!important;color:#0f172a!important;border-radius:12px!important;box-shadow:0 30px 60px rgba(0,0,0,.35)!important;' +
      'width:min(900px,94vw)!important;max-height:88vh!important;display:flex!important;flex-direction:column!important;overflow:hidden!important}' +
      '.fim-dd-head{display:flex!important;align-items:center!important;gap:10px!important;padding:13px 16px!important;border-bottom:1px solid #e2e8f0!important}' +
      '.fim-dd-head h3{margin:0!important;font-size:15px!important;font-weight:600!important;color:#0f172a!important}' +
      '.fim-dd-x{box-sizing:border-box!important;margin:0 0 0 auto!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;' +
      'width:32px!important;height:32px!important;border:0!important;background:transparent!important;font-size:22px!important;line-height:1!important;cursor:pointer!important;color:#64748b!important;padding:0!important}' +
      '.fim-dd-x:hover{color:#0f172a!important}' +
      '.fim-dd-body{padding:16px!important;overflow:auto!important}' +
      '.fim-dd-status{padding:28px 16px!important;text-align:center!important;color:#64748b!important}' +
      '.fim-dd-spin{width:26px;height:26px;border:3px solid #cbd5e1;border-top-color:#2563eb;border-radius:50%;' +
      'margin:0 auto 12px;animation:fim-dd-rot .8s linear infinite}' +
      '@keyframes fim-dd-rot{to{transform:rotate(360deg)}}' +
      '.fim-dd-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))!important;gap:14px!important}' +
      '.fim-dd-item{position:relative!important;border:1px solid #e2e8f0!important;border-radius:10px!important;overflow:hidden!important;display:flex!important;flex-direction:column!important}' +
      '.fim-dd-del{box-sizing:border-box!important;position:absolute!important;top:8px!important;right:8px!important;z-index:3!important;' +
      'width:28px!important;height:28px!important;padding:0!important;margin:0!important;border:0!important;border-radius:50%!important;' +
      'display:none!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;' +
      'background:rgba(220,38,38,.92)!important;color:#fff!important;font-size:16px!important;line-height:1!important;' +
      'box-shadow:0 2px 6px rgba(0,0,0,.3)!important;transition:background .12s!important}' +
      '.fim-dd-item:hover .fim-dd-del,.fim-dd-del:focus{display:inline-flex!important}' +
      '.fim-dd-del:hover{background:#b91c1c!important;color:#fff!important}' +
      '.fim-dd-del:disabled{opacity:.55!important;cursor:default!important}' +
      '.fim-dd-item.is-removing{opacity:.45!important;pointer-events:none!important}' +
      '.fim-dd-thumb{height:150px!important;background:#f1f5f9!important;border-bottom:1px solid #e2e8f0!important;display:flex!important;align-items:center!important;justify-content:center!important;overflow:hidden!important}' +
      '.fim-dd-thumb .fim-dd-img{width:100%!important;height:100%!important;object-fit:cover!important;display:block!important;margin:0!important;border:0!important;max-width:none!important;border-radius:0!important}' +
      '.fim-dd-thumb.fim-dd-file{flex-direction:column!important;gap:6px!important;background:#f8fafc!important;color:#64748b!important}' +
      '.fim-dd-file svg{width:42px!important;height:42px!important}' +
      '.fim-dd-file span{font-size:11px!important;font-weight:600!important;letter-spacing:.04em!important;color:#94a3b8!important}' +
      '.fim-dd-name{margin:0!important;padding:7px 10px 0!important;font-size:12px!important;color:#475569!important;word-break:break-all!important;text-align:left!important}' +
      '.fim-dd-btns{display:flex!important;gap:6px!important;padding:10px!important}' +
      '.fim-dd-btn{box-sizing:border-box!important;flex:1 1 auto!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;text-align:center!important;' +
      '-webkit-appearance:none!important;appearance:none!important;cursor:pointer!important;margin:0!important;border:1px solid #cbd5e1!important;background:#fff!important;color:#1e293b!important;' +
      'border-radius:7px!important;padding:8px 10px!important;min-height:38px!important;width:auto!important;font-family:inherit!important;font-size:12.5px!important;font-weight:600!important;line-height:1.2!important;' +
      'letter-spacing:normal!important;text-transform:none!important;text-decoration:none!important;text-shadow:none!important;box-shadow:none!important;transition:background .12s,border-color .12s}' +
      '.fim-dd-btn:hover{background:#f1f5f9!important;color:#0f172a!important}' +
      '.fim-dd-btn.primary{background:#2563eb!important;border-color:#2563eb!important;color:#fff!important}' +
      '.fim-dd-btn.primary:hover{background:#1d4ed8!important;border-color:#1d4ed8!important;color:#fff!important}' +
      '.fim-dd-item.is-done{box-shadow:inset 0 0 0 2px #16a34a!important}' +
      '.fim-dd-item.is-done .fim-dd-name::after{content:" \\2713";color:#16a34a;font-weight:700}' +
      '.fim-dd-foot{display:flex!important;justify-content:flex-end!important;gap:10px!important;padding:12px 16px!important;border-top:1px solid #e2e8f0!important}' +
      '.fim-dd-foot .fim-dd-btn{flex:0 0 auto!important;min-width:120px!important}' +
      '.fim-dd-err{color:#b91c1c!important}';
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
    function filesFromDataTransfer(dt) {
      var out = [];
      if (!dt || !dt.files) return out;
      for (var i = 0; i < dt.files.length; i++) {
        if (dt.files[i]) out.push(dt.files[i]);
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

    function deleteUploaded(base, session, item) {
      return fetch(base + 'api/upload/dragdrop/delete', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': (session && session.csrf) || '',
        },
        body: JSON.stringify({ path: (item && item.path) || '' }),
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (!res.ok || !res.data || res.data.success === false) {
            throw new Error((res.data && res.data.error) || 'Delete failed');
          }
          return true;
        });
    }

    function insertPreviewLink(item, insertionPoint) {
      if (insertionPoint) insertionPoint.restore();
      var full = escapeHtmlAttr(toRelativeUrl(item.url));
      var thumb = escapeHtmlAttr(toRelativeUrl(item.thumbUrl || item.url));
      editor.insertContent('<a href="' + full + '"><img src="' + thumb + '" alt="" /></a>');
      if (insertionPoint) insertionPoint.snapshot();
    }

    function insertFullImage(item, insertionPoint) {
      if (insertionPoint) insertionPoint.restore();
      var full = escapeHtmlAttr(toRelativeUrl(item.url));
      editor.insertContent('<img src="' + full + '" alt="" />');
      if (insertionPoint) insertionPoint.snapshot();
    }

    function insertFileLink(item, insertionPoint) {
      if (insertionPoint) insertionPoint.restore();
      var full = escapeHtmlAttr(toRelativeUrl(item.url));
      var name = escapeHtml(item.name || (item.url.split('/').pop()) || 'file');
      editor.insertContent('<a href="' + full + '">' + name + '</a>');
      if (insertionPoint) insertionPoint.snapshot();
    }

    function openInsertWindow(session, files, base, insertionPoint) {
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
      var insertLinkLabel = tr(session, 'DragDrop_insert_link', 'Insert link');
      var deleteLabel = tr(session, 'DragDrop_delete', 'Delete');
      var fileIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';

      var itemsHtml = files.map(function (f, idx) {
        var head, btns;
        if (f.isImage !== false) {
          var thumb = escapeHtmlAttr(toRelativeUrl(f.thumbUrl || f.url));
          var full = escapeHtmlAttr(toRelativeUrl(f.url));
          head = '<div class="fim-dd-thumb"><img class="fim-dd-img" src="' + thumb + '" data-full="' + full + '" alt=""></div>';
          btns =
            '<button type="button" class="fim-dd-btn primary" data-act="preview" data-i="' + idx + '">' + escapeHtml(insertPreviewLabel) + '</button>' +
            '<button type="button" class="fim-dd-btn" data-act="image" data-i="' + idx + '">' + escapeHtml(insertImageLabel) + '</button>';
        } else {
          var ext = ((f.name || '').split('.').pop() || '').toUpperCase();
          head = '<div class="fim-dd-thumb fim-dd-file">' + fileIcon + '<span>' + escapeHtml(ext) + '</span></div>';
          btns = '<button type="button" class="fim-dd-btn primary" data-act="link" data-i="' + idx + '">' + escapeHtml(insertLinkLabel) + '</button>';
        }
        var delBtn = '<button type="button" class="fim-dd-del" data-del="' + idx + '" aria-label="' +
          escapeHtmlAttr(deleteLabel) + '" title="' + escapeHtmlAttr(deleteLabel) + '">&times;</button>';
        return '<div class="fim-dd-item" data-idx="' + idx + '">' + delBtn + head +
          '<div class="fim-dd-name">' + escapeHtml(f.name || '') + '</div>' +
          '<div class="fim-dd-btns">' + btns + '</div></div>';
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
        // Delete (red cross): remove the file on the server, then drop the tile.
        var delBtn = e.target.closest ? e.target.closest('.fim-dd-del') : null;
        if (delBtn) {
          var card = delBtn.closest('.fim-dd-item');
          var di = parseInt(delBtn.getAttribute('data-del'), 10);
          var ditem = files[di];
          if (!ditem || !card || card.classList.contains('is-removing')) return;
          card.classList.add('is-removing');
          delBtn.disabled = true;
          deleteUploaded(base, session, ditem).then(function () {
            files[di] = null; // keep index-based insert buttons valid
            if (card.parentNode) card.parentNode.removeChild(card);
            // Nothing left to insert → close the window.
            if (!overlay.querySelector('.fim-dd-item')) close();
          }).catch(function () {
            // Re-enable so the user can retry on failure.
            card.classList.remove('is-removing');
            delBtn.disabled = false;
          });
          return;
        }

        var btn = e.target.closest ? e.target.closest('.fim-dd-btn') : null;
        if (!btn) return;
        var item = files[parseInt(btn.getAttribute('data-i'), 10)];
        if (!item) return;
        var act = btn.getAttribute('data-act');
        if (act === 'preview') insertPreviewLink(item, insertionPoint);
        else if (act === 'image') insertFullImage(item, insertionPoint);
        else insertFileLink(item, insertionPoint);
        // Keep the window open so the other files can still be inserted;
        // auto-close only when there was a single file.
        if (files.length === 1) { close(); return; }
        var card = btn.closest('.fim-dd-item');
        if (card) card.classList.add('is-done');
      });

      // If a thumbnail fails to load, fall back to the full image.
      Array.prototype.forEach.call(overlay.querySelectorAll('.fim-dd-img'), function (img) {
        img.addEventListener('error', function handler() {
          img.removeEventListener('error', handler);
          var full = img.getAttribute('data-full');
          if (full && img.getAttribute('src') !== full) { img.src = full; }
        });
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

    function createInsertionPoint() {
      var bookmark = null;

      function snapshot() {
        try {
          if (editor.selection && editor.selection.getBookmark) {
            bookmark = editor.selection.getBookmark(2, true);
          }
        } catch (e) {
          bookmark = null;
        }
      }

      function restore() {
        try {
          if (editor.focus) editor.focus(true);
          if (bookmark && editor.selection && editor.selection.moveToBookmark) {
            editor.selection.moveToBookmark(bookmark);
          }
        } catch (e) { /* ignore */ }
      }

      snapshot();

      return {
        restore: restore,
        snapshot: snapshot,
      };
    }

    function handleDirectInsert(base, files, insertionPoint) {
      if (!files.length) return false;

      var warm = sessions[base] && sessions[base].config;
      // If the server has the feature switched off, don't hijack the insert.
      if (warm && warm.dragDropUpload === false) return false;

      var status = openStatusWindow(null);
      uploadDropped(base, files).then(function (result) {
        status.remove();
        if (!result) return;
        if (result.disabled) return; // server disabled mid-flight
        if (result.files && result.files.length) {
          openInsertWindow(result.session, result.files, base, insertionPoint);
        }
      }).catch(function (err) {
        status.error((err && err.message) || 'Upload failed');
      });

      return true;
    }

    function onDragOver(e) {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;
      if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
        e.preventDefault();
      }
    }

    function onDrop(e) {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;

      var dt = e.dataTransfer;
      var hasFiles = dt && dt.types && Array.prototype.indexOf.call(dt.types, 'Files') !== -1;
      if (!hasFiles) return; // not a file drop (text/html/url) — let TinyMCE handle it

      var base = getBaseUrl();
      var warm = sessions[base] && sessions[base].config;
      // If the server has the feature switched off, don't hijack the drop.
      if (warm && warm.dragDropUpload === false) return;

      // It IS a file drop and the feature is on: take over so the browser can never
      // navigate to the dropped file (which would lose the editor content).
      e.preventDefault();
      e.stopImmediatePropagation();

      var files = filesFromDataTransfer(dt);
      if (!files.length) return;
      setCaretFromPoint(e.clientX, e.clientY);
      handleDirectInsert(base, files, createInsertionPoint());
    }

    function onPaste(e) {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;

      var dt = e.clipboardData;
      var hasFiles = dt && dt.types && Array.prototype.indexOf.call(dt.types || [], 'Files') !== -1;
      if (!hasFiles) return; // normal text/html paste — let TinyMCE handle it

      var base = getBaseUrl();
      var warm = sessions[base] && sessions[base].config;
      // If the server has the feature switched off, keep TinyMCE's native paste behavior.
      if (warm && warm.dragDropUpload === false) return;

      var files = filesFromDataTransfer(dt);
      if (!files.length) return;

      e.preventDefault();
      e.stopImmediatePropagation();
      handleDirectInsert(base, files, createInsertionPoint());
    }

    editor.on('init', function () {
      if (!editor.options.get('fileimagemanager_dragdrop')) return;
      // Warm the session (CSRF + config + translations) so the first drop is instant.
      loadSession(getBaseUrl());
      var doc = editor.getDoc();
      if (doc) {
        doc.addEventListener('dragover', onDragOver, true);
        doc.addEventListener('drop', onDrop, true);
        doc.addEventListener('paste', onPaste, true);
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
