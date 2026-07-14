(function productEnrichmentEditor(global) {
  'use strict';

  var WRAPPER_START = '<div class="pdp-enrichment-inner" style="max-width: 720px; margin-left: auto; margin-right: auto; padding: 24px 16px; width: 100%; box-sizing: border-box; font-family: inherit; color: inherit;">';
  var WRAPPER_END = '</div>';
  var CALLOUT_TEMPLATE =
    '<div style="border-left: 3px solid #2a7a6e; padding: 12px 16px; margin-bottom: 16px; background: #f5faf9;">'
    + '<p style="margin: 0; font-size: 0.95rem; line-height: 1.6;">'
    + '<strong>Heading</strong> Supporting text goes here.'
    + '</p></div>';
  var PDF_LINK_TEMPLATE =
    '<p><a href="{{PDF_URL}}" target="_blank" rel="noopener noreferrer">{{PDF_LINK_TEXT}}</a></p>';

  function isComplexHtml(html) {
    var value = String(html || '').trim();
    if (!value) {
      return false;
    }

    return /style\s*=|<div[\s>]/i.test(value);
  }

  function normalizeSavedHtml(html) {
    return String(html || '')
      .replace(/<ol(\s[^>]*)?>/gi, '<ul$1>')
      .replace(/<\/ol>/gi, '</ul>')
      .replace(/\uFEFF/g, '')
      .trim();
  }

  function getPdfLinkText(root) {
    var input = root.closest('form')?.querySelector('#pdf_link_text');
    var text = input ? String(input.value || '').trim() : '';
    return text || 'Product Information Sheet';
  }

  function buildPdfLinkHtml(root) {
    return PDF_LINK_TEMPLATE.replace('{{PDF_LINK_TEXT}}', getPdfLinkText(root));
  }

  function insertAtCursor(textarea, snippet) {
    var start = textarea.selectionStart ?? textarea.value.length;
    var end = textarea.selectionEnd ?? start;
    var before = textarea.value.slice(0, start);
    var after = textarea.value.slice(end);
    textarea.value = before + snippet + after;
    textarea.focus();
    var cursor = start + snippet.length;
    textarea.setSelectionRange(cursor, cursor);
  }

  function syncToTextarea(quill, textarea) {
    textarea.value = normalizeSavedHtml(quill.root.innerHTML);
  }

  function loadVisualFromHtml(quill, html) {
    quill.setText('');
    if (!html) {
      return;
    }

    var delta = quill.clipboard.convert({ html: normalizeSavedHtml(html) });
    quill.setContents(delta, 'silent');
  }

  function initEditor(root) {
    var textarea = root.querySelector('#enrichment_html');
    var quillMount = root.querySelector('[data-pe-quill-mount]');
    var visualBtn = root.querySelector('[data-mode="visual"]');
    var htmlBtn = root.querySelector('[data-mode="html"]');
    var form = root.closest('form');

    if (!textarea || !quillMount || !visualBtn || !htmlBtn || !form || typeof global.Quill !== 'function') {
      return;
    }

    var quill = new global.Quill(quillMount, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          [{ size: ['small', false, 'large', 'huge'] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'bullet' }],
          [{ align: [] }],
          ['link'],
          ['blockquote'],
          ['clean'],
        ],
      },
      placeholder: 'Write product page enrichment content…',
    });

    var mode = isComplexHtml(textarea.value) ? 'html' : 'visual';

    function setMode(nextMode, force) {
      if (nextMode === 'visual' && mode === 'html' && !force) {
        if (isComplexHtml(textarea.value)) {
          var proceed = global.confirm(
            'This content includes styled layout blocks. Visual mode may simplify formatting. Continue?'
          );
          if (!proceed) {
            return;
          }
        }
        loadVisualFromHtml(quill, textarea.value);
      }

      if (nextMode === 'html' && mode === 'visual') {
        syncToTextarea(quill, textarea);
      }

      mode = nextMode;
      visualBtn.classList.toggle('is-active', mode === 'visual');
      htmlBtn.classList.toggle('is-active', mode === 'html');
      quillMount.hidden = mode !== 'visual';
      textarea.hidden = mode !== 'html';
    }

    root.querySelectorAll('[data-insert]').forEach(function (button) {
      button.addEventListener('click', function () {
        var action = button.getAttribute('data-insert');

        if (mode === 'html') {
          if (action === 'pdf-link') {
            insertAtCursor(textarea, buildPdfLinkHtml(root));
          } else if (action === 'callout') {
            insertAtCursor(textarea, CALLOUT_TEMPLATE);
          } else if (action === 'wrapper') {
            insertAtCursor(textarea, WRAPPER_START + '\n\n' + WRAPPER_END);
          }
          return;
        }

        if (action === 'pdf-link') {
          quill.clipboard.dangerouslyPasteHTML(buildPdfLinkHtml(root), 'user');
        } else if (action === 'callout') {
          quill.clipboard.dangerouslyPasteHTML(CALLOUT_TEMPLATE, 'user');
        } else if (action === 'wrapper') {
          quill.clipboard.dangerouslyPasteHTML(WRAPPER_START + WRAPPER_END, 'user');
        }
      });
    });

    visualBtn.addEventListener('click', function () {
      setMode('visual');
    });

    htmlBtn.addEventListener('click', function () {
      setMode('html');
    });

    form.addEventListener('submit', function () {
      if (mode === 'visual') {
        syncToTextarea(quill, textarea);
      } else {
        textarea.value = normalizeSavedHtml(textarea.value);
      }
    });

    if (mode === 'visual') {
      loadVisualFromHtml(quill, textarea.value);
      setMode('visual', true);
    } else {
      setMode('html', true);
    }
  }

  function boot() {
    document.querySelectorAll('[data-pe-enrichment-editor]').forEach(initEditor);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window);
