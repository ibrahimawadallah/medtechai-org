(function() {
var ep = '/api/tools/suggest';
var style = document.createElement('style');
style.textContent =
'.sg-wrap{position:relative}' +
'.sg-drop{position:absolute;top:100%;left:0;right:0;z-index:1000;background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:240px;overflow-y:auto;display:none;margin-top:2px}' +
'.sg-drop.on{display:block}' +
'.sg-item{padding:10px 14px;font-size:13px;color:var(--slate);cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .1s}' +
'.sg-item:last-child{border-bottom:none}' +
'.sg-item:hover,.sg-item.active{background:var(--blue-bg);color:var(--blue)}' +
'.sg-item em{font-style:normal;font-weight:600;color:var(--blue)}' +
'.sg-empty{padding:10px 14px;font-size:12px;color:var(--slate4);text-align:center}' +
'.sg-loading{padding:10px 14px;font-size:12px;color:var(--slate4);text-align:center}';
document.head.appendChild(style);

function debounce(fn, ms) {
  var timer;
  return function() {
    var ctx = this, args = arguments;
    clearTimeout(timer);
    timer = setTimeout(function() { fn.apply(ctx, args); }, ms);
  };
}

function highlight(text, query) {
  if (!query) return text;
  var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
  return text.replace(re, '<em>$1</em>');
}

function setupSuggest(input) {
  var wrap = document.createElement('div');
  wrap.className = 'sg-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);

  var drop = document.createElement('div');
  drop.className = 'sg-drop';
  wrap.appendChild(drop);

  var activeIdx = -1;
  var items = [];

  function hide() { drop.classList.remove('on'); activeIdx = -1; }

  function fetch(q) {
    if (q.length < 2) { drop.classList.remove('on'); return; }
    drop.innerHTML = '<div class="sg-loading">Searching…</div>';
    drop.classList.add('on');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', ep, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
      try {
        var data = JSON.parse(xhr.responseText);
        render(q, data.suggestions || []);
      } catch(e) { render(q, []); }
    };
    xhr.onerror = function() { render(q, []); };
    xhr.send(JSON.stringify({q: q, category: input.getAttribute('data-suggest') || 'drug'}));
  }

  function render(q, list) {
    if (!list.length) {
      drop.innerHTML = '<div class="sg-empty">No suggestions</div>';
      drop.classList.add('on');
      items = []; activeIdx = -1;
      return;
    }
    var html = '';
    for (var i = 0; i < list.length; i++) {
      html += '<div class="sg-item" data-idx="' + i + '">' + highlight(h(list[i]), q) + '</div>';
    }
    drop.innerHTML = html;
    drop.classList.add('on');
    items = drop.querySelectorAll('.sg-item');
    activeIdx = -1;

    items.forEach(function(el) {
      el.addEventListener('mousedown', function(e) {
        e.preventDefault();
        input.value = list[parseInt(this.getAttribute('data-idx'))];
        hide();
        input.focus();
      });
    });
  }

  function h(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  var doFetch = debounce(function() { fetch(input.value); }, 200);

  input.addEventListener('input', doFetch);

  input.addEventListener('keydown', function(e) {
    if (!drop.classList.contains('on') || !items.length) return;
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        if (activeIdx < items.length - 1) activeIdx++;
        else activeIdx = 0;
        break;
      case 'ArrowUp':
        e.preventDefault();
        if (activeIdx > 0) activeIdx--;
        else activeIdx = items.length - 1;
        break;
      case 'Enter':
      case 'Tab':
        if (activeIdx >= 0 && activeIdx < items.length) {
          e.preventDefault();
          items[activeIdx].click();
        }
        return;
      case 'Escape':
        hide();
        return;
      default: return;
    }
    items.forEach(function(el, i) { el.classList.toggle('active', i === activeIdx); });
    if (items[activeIdx]) items[activeIdx].scrollIntoView({block: 'nearest'});
  });

  input.addEventListener('blur', function() { setTimeout(hide, 200); });
  input.addEventListener('focus', function() { if (input.value.length >= 2) doFetch(); });
}

document.addEventListener('DOMContentLoaded', function() {
  var inputs = document.querySelectorAll('[data-suggest]');
  for (var i = 0; i < inputs.length; i++) setupSuggest(inputs[i]);
});
})();