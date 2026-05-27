// Shared file upload handlers — include in tool pages
(function() {
  // Configuration
  const config = {
    maxSize: 5242880, // 5 MB
    maxDimension: 2000,
    imageQuality: 0.85,
    allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']
  };
  
  // State
  let _upFile = null;

  // Expose _upFile as a global so tool pages can check it in getFormData
  Object.defineProperty(window, '_upFile', { get: function() { return _upFile; }, configurable: true });
  
  // DOM Elements
  const getElements = () => ({
    upFile: document.getElementById('upFile'),
    upArea: document.getElementById('upArea'),
    upPlaceholder: document.getElementById('upPlaceholder'),
    upPreview: document.getElementById('upPreview'),
    upImg: document.getElementById('upImg'),
    upName: document.getElementById('upName')
  });
  
  // Error display
  const showError = (message) => {
    // Remove existing error
    const existingError = document.querySelector('.upload-error');
    if (existingError) existingError.remove();
    
    // Create error element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'upload-error';
    errorDiv.style.color = 'var(--danger)';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    
    // Insert after upload area
    const area = getElements().upArea;
    if (area) area.parentNode.insertBefore(errorDiv, area.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (errorDiv.parentNode) errorDiv.remove();
    }, 5000);
  };
  
  // Handle file upload
  const handleUpload = (input) => {
    const { upPlaceholder, upPreview, upImg, upName } = getElements();
    if (!upPlaceholder || !upPreview || !upImg || !upName) return;
    
    if (!input.files || !input.files[0]) {
      removeUpload();
      return;
    }
    
    const f = input.files[0];
    
    // Validate file size
    if (f.size > config.maxSize) {
      showError('File too large (max 5 MB).');
      input.value = '';
      return;
    }
    
    // Validate file type
    if (!config.allowedTypes.includes(f.type)) {
      showError('Unsupported file type. Use JPG, PNG, WEBP, or PDF.');
      input.value = '';
      return;
    }
    
    _upFile = f;
    upPlaceholder.style.display = 'none';
    upPreview.style.display = 'block';
    
    // Handle image preview and resizing
    if (f.type.startsWith('image/')) {
      upImg.style.display = 'block';
      
      const r = new FileReader();
      r.onload = function(e) {
        const img = new Image();
        img.onload = function() {
          // Resize large images if needed
          if (img.width > config.maxDimension || img.height > config.maxDimension) {
            const c = document.createElement('canvas');
            const scale = Math.min(config.maxDimension / img.width, config.maxDimension / img.height);
            c.width = Math.round(img.width * scale);
            c.height = Math.round(img.height * scale);
            const ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0, c.width, c.height);
            
            c.toBlob(function(blob) {
              if (blob) {
                _upFile = new File([blob], f.name, {type: f.type, lastModified: Date.now()});
                // Update preview with resized image
                upImg.src = URL.createObjectURL(_upFile);
              }
            }, f.type, config.imageQuality);
          } else {
            // No resizing needed, keep original
            upImg.src = e.target.result;
          }
        };
        img.onerror = function() {
          showError('Failed to load image preview.');
          upImg.src = '';
        };
        img.src = e.target.result;
      };
      r.onerror = function() {
        showError('Failed to read file.');
        upImg.style.display = 'none';
      };
      r.readAsDataURL(f);
    } else {
      // For PDFs and other files
      upImg.style.display = 'none';
      upImg.src = '';
      
      // Show PDF icon for PDF files
      if (f.type === 'application/pdf') {
        const pdfIcon = document.createElement('span');
        pdfIcon.innerHTML = '&lt;!-- PDF icon would go here --&gt;📄'; // Using emoji as placeholder
        pdfIcon.style.display = 'inline-block';
        pdfIcon.style.width = '100%';
        pdfIcon.style.textAlign = 'center';
        pdfIcon.style.fontSize = '48px';
        pdfIcon.style.margin = '10px 0';
        upPreview.insertBefore(pdfIcon, upImg);
      }
    }
    
    upName.textContent = f.name;
  };
  
  // Remove upload
  const removeUpload = () => {
    _upFile = null;
    const { upFile, upPlaceholder, upPreview, upImg, upName } = getElements();
    
    if (upFile) upFile.value = '';
    if (upPlaceholder) upPlaceholder.style.display = '';
    if (upPreview) upPreview.style.display = 'none';
    if (upImg) {
      upImg.src = '';
      upImg.style.display = 'none';
    }
    if (upName) upName.textContent = '';
    
    // Remove any PDF icons that might have been added
    if (upPreview) {
      const pdfIcons = upPreview.querySelectorAll('span');
      pdfIcons.forEach(icon => {
        if (icon.textContent.includes('📄')) icon.remove();
      });
    }
  };
  
  // Drag and drop functionality
  const initDragDrop = () => {
    const { upArea } = getElements();
    if (!upArea) return;
    
    upArea.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.classList.add('drag-over');
    });
    
    upArea.addEventListener('dragleave', function() {
      this.classList.remove('drag-over');
    });
    
    upArea.addEventListener('drop', function(e) {
      e.preventDefault();
      this.classList.remove('drag-over');
      
      const dt = e.dataTransfer;
      if (dt.files && dt.files[0]) {
        // Create a new FileList since input.files is read-only
        const fileInput = getElements().upFile;
        if (fileInput) {
          // Create a new DataTransfer object to set files
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(dt.files[0]);
          fileInput.files = dataTransfer.files;
          handleUpload(fileInput);
        }
      }
    });
  };
  
  // ── Hash-based category scroll ───────────────────────────────────
  function scrollToCategory() {
    var hash = window.location.hash.replace('#', '');
    if (!hash) return;
    var el = document.querySelector('.cat[data-cat="' + hash + '"]');
    if (el) {
      setTimeout(function() { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
    }
  }
  // Initialize on DOM load
  document.addEventListener('DOMContentLoaded', function() {
    initDragDrop();
    injectSearchBar();
    scrollToCategory();
    // Inject tour script
    if (!document.getElementById('tour-script')) {
      var ts = document.createElement('script');
      ts.id = 'tour-script';
      ts.src = '/tools/_tour.js';
      document.body.appendChild(ts);
    }
  });
  window.addEventListener('hashchange', function() {
    scrollToCategory();
  });
  
  // Expose functions globally if needed (but keep state private)
  window.handleUpload = handleUpload;
  window.removeUpload = removeUpload;
  window.getUploadedFile = () => _upFile;
  window.injectSearchBar = injectSearchBar;

  // ── UpToDate-style search bar injection ──────────────────────────
  function injectSearchBar() {
    if (document.getElementById('uptodate-search')) return;
    // Skip on the tools listing page — it has its own nav
    if (document.querySelector('.tool-srch')) return;
    var bar = document.createElement('div');
    bar.id = 'uptodate-search';
    bar.style.cssText = 'position:fixed;top:0;left:0;right:0;height:48px;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.06);display:flex;align-items:center;padding:0 16px;z-index:1000;gap:12px';
    bar.innerHTML = '<a href="/" style="font-weight:700;font-size:15px;color:#1a2d3a;text-decoration:none;white-space:nowrap">MedTech<span style="color:#38b8ae">AI</span></a>' +
      '<span style="color:#d1d5db;font-size:14px">|</span>' +
      '<form action="/tools/" method="get" style="flex:1;max-width:480px;display:flex">' +
      '<input type="text" name="q" placeholder="Search drugs, topics, tools\u2026" style="flex:1;height:32px;padding:0 10px;border:1px solid #e5e7eb;border-radius:6px 0 0 6px;font-size:13px;font-family:inherit;color:#1a2d3a;outline:none;background:#f9fafb" aria-label="Search">' +
      '<button type="submit" style="height:32px;padding:0 12px;background:#0066aa;color:#fff;border:none;border-radius:0 6px 6px 0;font-size:12px;font-weight:600;cursor:pointer">Search</button></form>' +
      '<span style="flex:1"></span>' +
      '<a href="/about/" style="font-size:12px;color:#6b7280;text-decoration:none">About</a>' +
      '<a href="/contact/" style="font-size:12px;color:#6b7280;text-decoration:none">Contact</a>';
    document.body.prepend(bar);
    document.body.style.paddingTop = '48px';
  }
})();