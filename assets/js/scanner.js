/**
 * QR Code Camera Scanner Integration
 * Uses html5-qrcode CDN to verify orders at the canteen counter
 */

const ScannerApp = {
  scanner: null,
  isScanning: false,
  targetOrderId: null,

  openModal(targetOrderId = null) {
    this.targetOrderId = targetOrderId;
    const modal = document.getElementById('scanner-modal');
    if (modal) modal.classList.add('active');

    const modalTitle = document.getElementById('scanner-modal-title');
    if (modalTitle) {
      modalTitle.innerHTML = targetOrderId 
        ? `📷 Scan Student QR (#${targetOrderId.replace('ORD-', '')})`
        : `📷 Scan Customer QR Code`;
    }

    const manualInput = document.getElementById('manual-qr-input');
    if (manualInput) {
      manualInput.value = '';
      manualInput.placeholder = targetOrderId 
        ? `e.g. #${targetOrderId.replace('ORD-', '')}` 
        : 'e.g. #CA75FB or ORD-6AAE...';
    }

    const resultBox = document.getElementById('scan-result-box');
    if (resultBox) resultBox.innerHTML = '';

    this.startScanner();
  },

  closeModal() {
    this.stopScanner();
    const modal = document.getElementById('scanner-modal');
    if (modal) modal.classList.remove('active');
    const resultBox = document.getElementById('scan-result-box');
    if (resultBox) resultBox.innerHTML = '';
    this.targetOrderId = null;
  },

  async startScanner() {
    if (this.isScanning) return;
    const readerEl = document.getElementById('qr-reader');
    if (!readerEl) return;

    readerEl.style.minHeight = '260px';
    readerEl.style.background = '#000';
    readerEl.style.border = 'none';
    readerEl.style.borderRadius = '12px';
    readerEl.innerHTML = `
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 260px; color: #fff; text-align: center; padding: 1.5rem;">
        <i data-lucide="camera" style="width: 36px; height: 36px; margin-bottom: 0.75rem; color: var(--accent-primary);"></i>
        <p style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.35rem;">Requesting Camera Permission...</p>
        <small style="font-size: 0.8rem; color: #94a3b8;">Please tap <strong>Allow</strong> when prompted by your browser</small>
      </div>
    `;
    if (window.lucide) lucide.createIcons();

    if (typeof Html5Qrcode === 'undefined') {
      alert('QR Scanner library is still loading. Please try again in a moment.');
      return;
    }

    try {
      // 1. Request camera permission and detect devices
      let cameraConfig = { facingMode: "environment" };
      try {
        const cameras = await Html5Qrcode.getCameras();
        if (cameras && cameras.length > 0) {
          const backCam = cameras.find(c => /back|rear|environment/i.test(c.label)) || cameras[cameras.length - 1];
          cameraConfig = backCam.id;
        }
      } catch (camErr) {
        console.warn("getCameras threw, falling back to facingMode constraint:", camErr);
      }

      // 2. Clean up any existing scanner instance
      if (this.scanner) {
        try { await this.scanner.stop(); } catch(e){}
        try { this.scanner.clear(); } catch(e){}
      }

      readerEl.innerHTML = '';
      this.scanner = new Html5Qrcode("qr-reader");
      this.isScanning = true;

      await this.scanner.start(
        cameraConfig,
        {
          fps: 15,
          qrbox: (viewfinderWidth, viewfinderHeight) => {
            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
            const qrEdgeSize = Math.floor(minEdge * 0.75);
            return { width: qrEdgeSize, height: qrEdgeSize };
          },
          aspectRatio: 1.0
        },
        (decodedText) => this.onScanSuccess(decodedText),
        (err) => {} // frame non-match, continue scanning
      );
    } catch (err) {
      console.warn("Camera start failed:", err);
      this.isScanning = false;
      this.showPermissionError(err);
    }
  },

  showPermissionError(err) {
    const readerEl = document.getElementById('qr-reader');
    if (!readerEl) return;

    const isHttpNonLocal = location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';

    readerEl.style.minHeight = '260px';
    readerEl.style.background = 'var(--bg-secondary)';
    readerEl.style.border = '2px dashed var(--border-subtle)';
    readerEl.style.borderRadius = '12px';
    readerEl.style.display = 'flex';
    readerEl.style.flexDirection = 'column';
    readerEl.style.alignItems = 'center';
    readerEl.style.justifyContent = 'center';
    readerEl.style.padding = '1.5rem 1rem';
    readerEl.style.textAlign = 'center';

    let content = `
      <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📷</div>
      <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem; color: #dc2626;">Camera Permission Required</h3>
      <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem; max-width: 320px; line-height: 1.4;">
        Please grant camera access in your browser to scan student QR vouchers directly.
      </p>
      <button type="button" class="btn btn-primary btn-sm" onclick="ScannerApp.startScanner()" style="display:inline-flex; align-items:center; gap:0.5rem; margin-bottom: 0.75rem;">
        <i data-lucide="refresh-cw"></i> <span>Allow Camera & Start Scanning</span>
      </button>
    `;

    if (isHttpNonLocal) {
      const httpsUrl = `https://${location.hostname}${location.pathname}`;
      content += `
        <div style="border-top: 1px solid var(--border-subtle); margin-top: 0.5rem; padding-top: 0.75rem; width: 100%;">
          <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.5rem;">
            Mobile Chrome blocks camera access on plain HTTP. Switch to secure HTTPS:
          </p>
          <a href="${httpsUrl}" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.4rem; font-size: 0.8rem; background: rgba(37, 99, 235, 0.1); color: #2563eb; border-color: #2563eb;">
            <i data-lucide="shield-check"></i> <span>Open via Secure HTTPS</span>
          </a>
        </div>
      `;
    }

    readerEl.innerHTML = content;
    if (window.lucide) lucide.createIcons();
  },

  stopScanner() {
    if (this.scanner && this.isScanning) {
      this.scanner.stop().then(() => {
        this.scanner.clear();
        this.isScanning = false;
      }).catch(err => {
        console.error("Error stopping scanner:", err);
        this.isScanning = false;
      });
    }
  },

  async onScanSuccess(decodedText) {
    // Decoded format: ORDERGO:order_id:user_id:total or voucher code
    this.stopScanner();
    const resultBox = document.getElementById('scan-result-box');
    if (resultBox) {
      resultBox.innerHTML = `<div style="text-align:center; padding:1rem; color: var(--text-muted);"><i data-lucide="loader" class="spin"></i> Verifying student QR code...</div>`;
      if (window.lucide) lucide.createIcons();
    }

    try {
      const url = typeof apiUrl === 'function' ? apiUrl('qr_verify.php') : '/api/qr_verify.php';
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_data: decodedText })
      });
      const data = await res.json();

      if (data.success) {
        const orderIdDisplay = (data.order.order_id || '').replace('ORD-','');
        if (resultBox) {
          resultBox.innerHTML = `
            <div style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid #10b981; border-radius: 8px; padding: 1.25rem; text-align: center; margin-top: 1rem;">
              <h4 style="color: #059669; font-size: 1.1rem; margin-bottom: 0.5rem;">✅ Order Verified & Completed!</h4>
              <p style="margin-bottom: 0.25rem;">Order: <strong>#${orderIdDisplay}</strong></p>
              <p style="margin-bottom: 0.5rem; color: var(--text-secondary);">Customer: <strong>${data.order.customer_name || 'Guest'}</strong></p>
              <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">Pickup completed automatically! Closing...</div>
              <button class="btn btn-primary btn-sm" onclick="ScannerApp.closeModal(); if (typeof StaffApp !== 'undefined') StaffApp.fetchOrders(false);">Close</button>
            </div>
          `;
        }

        if (typeof StaffApp !== 'undefined' && StaffApp.playChime) {
          StaffApp.playChime();
        }

        if (typeof Cart !== 'undefined' && Cart.showToast) {
          Cart.showToast(`✅ Order #${orderIdDisplay} verified and pickup completed!`, 'success');
        }

        if (typeof StaffApp !== 'undefined') {
          StaffApp.fetchOrders(false);
        }

        // Automatically close modal after 1.8 seconds so staff workflow is frictionless
        setTimeout(() => {
          const modal = document.getElementById('scanner-modal');
          if (modal && modal.classList.contains('active')) {
            ScannerApp.closeModal();
            if (typeof StaffApp !== 'undefined') StaffApp.fetchOrders(false);
          }
        }, 1800);
      } else {
        if (resultBox) {
          resultBox.innerHTML = `
            <div style="background: rgba(239, 68, 68, 0.1); border: 1.5px solid #ef4444; border-radius: 8px; padding: 1rem; text-align: center; margin-top: 1rem;">
              <h4 style="color: #dc2626; margin-bottom: 0.5rem;">❌ Verification Failed</h4>
              <p style="font-size: 0.85rem; margin-bottom: 0.75rem;">${data.error || 'Invalid QR code.'}</p>
              <button class="btn btn-secondary btn-sm" onclick="ScannerApp.startScanner();">Scan Again</button>
            </div>
          `;
        }
      }
    } catch (err) {
      alert('Verification request failed. Please check network.');
    }
  },

  verifyManual() {
    const input = document.getElementById('manual-qr-input');
    if (!input) return;
    const text = input.value.trim();
    if (!text) {
      alert('Please enter the Order ID or voucher text');
      return;
    }
    this.onScanSuccess(text);
  }
};
