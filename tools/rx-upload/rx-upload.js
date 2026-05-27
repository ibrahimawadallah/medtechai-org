// ICONS map — 16×16 SVG icon definitions for every service (summary, pharmacy, interactions, safety, labs, g6pd, pregnancy, recommendations, urgency)
var ICONS = {
  summary: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="3" y="2" width="10" height="12" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="6" x2="10.5" y2="6" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="8.5" x2="10.5" y2="8.5" stroke="currentColor" stroke-width="1.5"/><line x1="5.5" y1="11" x2="8.5" y2="11" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 2v-1h3v1" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  pharmacy: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="4.5" y="2" width="7" height="12" rx="3.5" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="8" y1="2" x2="8" y2="14" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 1.5"/></svg>',
  interactions: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M2 8l3-3v2h6V5l3 3-3 3v-2H5v2L2 8z" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  safety: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 1.5l6 2.5v4c0 3.5-2.5 6.5-6 7.5-3.5-1-6-4-6-7.5V4L8 1.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6 8.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  labs: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M6 14a3 3 0 01-3-3V2h6v9a3 3 0 01-3 3z" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="4" y1="5.5" x2="8" y2="5.5" stroke="currentColor" stroke-width="1.5"/></svg>',
  recommendations: '<svg viewBox="0 0 16 16" width="16" height="16"><rect x="2" y="2.5" width="12" height="11" rx="1.5" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M5.5 7.5l1.5 1.5 3-3" stroke="currentColor" fill="none" stroke-width="1.5"/><line x1="5.5" y1="11" x2="9.5" y2="11" stroke="currentColor" stroke-width="1.5"/></svg>',
  g6pd: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 2.5C6 5.5 4 8 4 10c0 2.2 1.8 4 4 4s4-1.8 4-4c0-2-2-4.5-4-7.5z" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>',
  pregnancy: '<svg viewBox="0 0 16 16" width="16" height="16"><circle cx="8" cy="4" r="2" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M4 14c0-3 1.8-4.5 4-4.5s4 1.5 4 4.5" stroke="currentColor" fill="none" stroke-width="1.5"/><circle cx="11" cy="3" r="1.2" stroke="currentColor" fill="none" stroke-width="1.2"/></svg>',
  urgency: '<svg viewBox="0 0 16 16" width="16" height="16"><path d="M8 1.5c-2.5 0-4 2-4 4.5v2L2.5 10.5h11L12 8V6c0-2.5-1.5-4.5-4-4.5z" stroke="currentColor" fill="none" stroke-width="1.5"/><path d="M6.5 11.5a1.5 1.5 0 003 0" stroke="currentColor" fill="none" stroke-width="1.5"/>'
};

// icon(name) — returns SVG string for given service name
function icon(name) { return ICONS[name] || ''; }

// esc(s) — HTML-escapes a string (null-safe)
function esc(s) { return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// badge(label) — urgency badge HTML
function badge(l) {
  var m = { 'emergency':'badge-red', 'urgent':'badge-amber', 'routine':'badge-blue' };
  var c = m[(l||'').toLowerCase()] || 'badge-blue';
  return '<span class="badge ' + c + '">' + esc((l||'ROUTINE').toUpperCase()) + '</span>';
}

// autoGrow(el) — auto-expands textarea on input
function autoGrow(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }

// getFormData() — validates text or file present; if both missing shows alert and returns null
var EP = '/api/tools/rx-upload';
function getFormData() {
  var t = document.getElementById('f_text').value.trim();
  if (!t && !_upFile) { alert('Upload a document or paste text to analyze.'); return null; }
  var d = { text: t };
  if (_upFile) {
    var fd = new FormData();
    for (var k in d) fd.append(k, d[k]);
    fd.append('file', _upFile);
    return fd;
  }
  return d;
}

// runTool() — disables button, shows spinner + "Analyzing…", POSTs to API, calls renderDashboard on success, restores button on error/finally
async function runTool() {
  var btn = document.querySelector('.rx-analyze-btn');
  var sp = document.getElementById('spinner');
  var txt = document.getElementById('btnText');
  var body = getFormData();
  if (!body) return;
  btn.disabled = true; sp.classList.add('on'); txt.textContent = 'Analyzing…';
  try {
    var opts = { method: 'POST' };
    if (body instanceof FormData) { opts.body = body; }
    else { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(body); }
    var res = await fetch(EP, opts);
    var data = await res.json();
    if (data.data) renderDashboard(data.data);
    else showFallback(data);
  } catch (e) {
    document.getElementById('resultBody').innerHTML = '<div class="r-error"><span class="r-error-icon">⚠</span><div><strong>Connection Error</strong><br>Check that the server is running.</div></div>';
    document.getElementById('placeholderCard').style.display = 'none';
    document.getElementById('resultPanel').classList.add('on');
  } finally {
    btn.disabled = false; sp.classList.remove('on'); txt.textContent = 'Analyze Document';
  }
}

// showFallback(data) — shows error or raw JSON response
function showFallback(d) {
  var h = d.html || (d.error ? '<div class="r-error">' + esc(d.error) + '</div>' : '<pre>' + esc(JSON.stringify(d, null, 2)) + '</pre>');
  document.getElementById('resultBody').innerHTML = h;
  document.getElementById('placeholderCard').style.display = 'none';
  document.getElementById('resultPanel').classList.add('on');
}

// resetTool() — clear everything
function resetTool() {
  document.getElementById('placeholderCard').style.display = '';
  document.getElementById('resultPanel').classList.remove('on');
  document.getElementById('resultBody').innerHTML = '';
  document.getElementById('f_text').value = '';
  removeUpload();
}

// renderDashboard(data) — injects SVG icons into sidebar items, renders section cards with matching icons in headers
function renderDashboard(data) {
  var html = '';
  var hasPharmacy = data.pharmacy && data.pharmacy.drugsFound && data.pharmacy.drugsFound.length;
  var hasLabs = data.labs && data.labs.abnormalValues && data.labs.abnormalValues.length;

  // Show/hide sidebar items + inject icons
  document.getElementById('servicesList').querySelectorAll('.rx-service-item').forEach(function(el) {
    var section = el.getAttribute('data-section');
    // Inject icon before text
    var txt = el.textContent.trim();
    el.innerHTML = icon(section) + ' ' + txt;
    if (section === 'summary') { el.style.display = ''; return; }
    if (section === 'pharmacy' || section === 'interactions' || section === 'safety') {
      el.style.display = hasPharmacy ? '' : 'none';
      return;
    }
    if (section === 'labs') {
      el.style.display = hasLabs ? '' : 'none';
      return;
    }
  });

  // Summary section (always shown)
  html += '<div class="rx-card"><div class="rx-card-header teal"><span>' + icon('summary') + ' Clinical Summary</span>';
  html += '<span>' + badge(data.urgency || 'Routine') + '</span></div>';
  html += '<div class="rx-card-body">' + esc(data.summary || 'No summary available.') + '</div></div>';

  // Pharmacy section
  if (hasPharmacy) {
    html += '<div class="rx-card"><div class="rx-card-header navy"><span>' + icon('pharmacy') + ' Pharmacy Services</span></div><div class="rx-card-body">';
    html += '<div class="rx-drug-list">';
    data.pharmacy.drugDetails.forEach(function(drug) {
      html += '<div class="rx-drug-card"><div class="rx-drug-name">' + esc(drug.genericName) + '</div>';
      html += '<div class="rx-drug-meta">' + esc(drug.drugClass) + ' · ' + esc(drug.adultDosing) + '</div>';
      html += '<div class="rx-drug-indications">' + esc(drug.indications ? drug.indications.join(', ') : '') + '</div></div>';
    });
    html += '</div></div></div>';

    // Interactions
    if (data.pharmacy.interactions && data.pharmacy.interactions.length) {
      html += '<div class="rx-card"><div class="rx-card-header amber"><span>' + icon('interactions') + ' Drug Interactions</span></div><div class="rx-card-body">';
      html += '<div class="r-list">';
      data.pharmacy.interactions.forEach(function(ix) {
        var sevClass = ix.severity === 'high' ? 'badge-red' : (ix.severity === 'moderate' ? 'badge-amber' : 'badge-green');
        html += '<div class="rx-ix-item"><span class="badge ' + sevClass + '">' + esc(ix.severity.toUpperCase()) + '</span> ';
        html += '<strong>' + esc(ix.between.join(' + ')) + '</strong><br>';
        html += '<span class="rx-ix-desc">' + esc(ix.description) + '</span></div>';
      });
      html += '</div></div></div>';
    }

    // G6PD check
    if (data.pharmacy.g6pdCheck && data.pharmacy.g6pdCheck.length) {
      html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('g6pd') + ' G6PD Check</span></div><div class="rx-card-body">';
      data.pharmacy.g6pdCheck.forEach(function(g) {
        var cls = g.riskLevel === 'Contraindicated' || g.riskLevel === 'High Risk' ? 'badge-red' : (g.riskLevel === 'Moderate Risk' ? 'badge-amber' : 'badge-green');
        html += '<div><span class="badge ' + cls + '">' + esc(g.riskLevel.toUpperCase()) + '</span> <strong>' + esc(g.drug) + '</strong></div>';
      });
      html += '</div></div>';
    }

    // Pregnancy check
    if (data.pharmacy.pregnancyCheck && data.pharmacy.pregnancyCheck.fdaCategory) {
      html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('pregnancy') + ' Pregnancy Safety</span></div><div class="rx-card-body">';
      html += '<span class="badge ' + (data.pharmacy.pregnancyCheck.safety === 'Avoid' ? 'badge-red' : 'badge-amber') + '">' + esc(data.pharmacy.pregnancyCheck.fdaCategory) + '</span> ';
      html += esc(data.pharmacy.pregnancyCheck.risk);
      html += '</div></div>';
    }
  }

  // Labs section
  if (hasLabs) {
    html += '<div class="rx-card"><div class="rx-card-header green"><span>' + icon('labs') + ' Lab Analysis</span></div><div class="rx-card-body">';
    html += '<div class="r-list">';
    data.labs.abnormalValues.forEach(function(lab) {
      var flagClass = lab.flag === 'Critical' ? 'r-alert-red' : (lab.flag === 'High' || lab.flag === 'Low' ? 'r-alert-amber' : 'r-alert-green');
      html += '<div class="r-alert ' + flagClass + '"><div><strong>' + esc(lab.test) + '</strong> ';
      html += '<span class="badge ' + (lab.flag === 'Critical' ? 'badge-red' : (lab.flag === 'High' || lab.flag === 'Low' ? 'badge-amber' : 'badge-green')) + '">' + esc(lab.flag) + '</span> ';
      html += '<span style="font-weight:700;margin-left:4px">' + esc(lab.value) + '</span><br>';
      if (lab.normalRange) html += '<span style="font-size:11px;color:var(--slate5)">NR: ' + esc(lab.normalRange) + '</span><br>';
      html += '<span style="font-size:12px">' + esc(lab.interpretation) + '</span></div></div>';
    });
    html += '</div>';
    if (data.labs.overallAssessment) html += '<div class="rx-assessment">' + esc(data.labs.overallAssessment) + '</div>';
    html += '</div></div>';
  }

  // Recommendations
  if (data.recommendations && data.recommendations.length) {
    html += '<div class="rx-card"><div class="rx-card-header purple"><span>' + icon('recommendations') + ' Recommendations</span></div><div class="rx-card-body">';
    html += '<ul class="r-list">';
    data.recommendations.forEach(function(r) { html += '<li>' + esc(r) + '</li>'; });
    html += '</ul></div></div>';
  }

  document.getElementById('resultBody').innerHTML = html;
  document.getElementById('placeholderCard').style.display = 'none';
  document.getElementById('resultPanel').classList.add('on');

  // Highlight sidebar item on scroll
  setupScrollSpy();
}

// setupScrollSpy() — sidebar click-to-scroll, icon-injected items clickable
function setupScrollSpy() {
  var items = document.querySelectorAll('.rx-service-item');
  var cards = document.querySelectorAll('.rx-card');
  items.forEach(function(item) {
    item.addEventListener('click', function() {
      var target = this.getAttribute('data-section');
      cards.forEach(function(c) {
        if (c.querySelector('.rx-card-header') && c.querySelector('.rx-card-header').textContent.toLowerCase().includes(target)) {
          c.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  });
}

// Ctrl+Enter keydown listener on textarea triggers runTool
document.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && e.ctrlKey) runTool();
});