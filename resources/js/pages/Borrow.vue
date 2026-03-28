<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import DashboardCard from '@/components/Cards/Admin/Dashboard/DashboardCard.vue';
import DashboardCardIcon from '@/components/Icon/DashboardCardIcon.vue';
import DashboardIcon2 from '@/components/Icon/DashboardIcon2.vue';
import DashboardIcon3 from '@/components/Icon/DashboardIcon3.vue';
import Plus from '@/components/Icon/Plus.vue';
import SmallIcon from '@/components/Icon/SmallIcon.vue';
import SmallIcon2 from '@/components/Icon/SmallIcon2.vue';
import TwoPerson from '@/components/Icon/TwoPerson.vue';

// defineOptions({ layout: null });

const props = defineProps({
  borrowRows: {
    type: [Array, Object],
    default: () => ({ data: [] }),
  },
  borrowerProfiles: {
    type: Array,
    default: () => [],
  },
  borrowItemsCatalog: {
    type: Array,
    default: () => [],
  },
  borrowItemsByRfid: {
    type: Object,
    default: () => ({}),
  },
  dashboardStats: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', status: '', perPage: 10 }),
  },
});

let rfidBuffer = '';
let lastKeyTime = 0;
let scanFinalizeTimer = null;
const SCAN_TIMEOUT = 300;
const AUTO_FINALIZE_DELAY = 120;
const BORROW_ITEMS_EXAMPLE = computed(() => props.borrowItemsCatalog);

const isScanning = ref(true);
const lastScanned = ref('');
const scanPulse = ref(false);
const SCAN_TERMINATORS = new Set(['Enter', 'NumpadEnter', 'Tab']);

const registeredStudents = computed(() => props.borrowerProfiles);

const rows = computed(() => {
  if (Array.isArray(props.borrowRows?.data)) return props.borrowRows.data;
  if (Array.isArray(props.borrowRows)) return props.borrowRows;
  return [];
});
const stats = computed(() => props.dashboardStats);

const cardIcons = [TwoPerson, DashboardCardIcon, DashboardIcon2, DashboardIcon3];
const smallIcons = [Plus, SmallIcon, SmallIcon2, SmallIcon2];

const dashboardCards = computed(() => cardIcons.map((icon, index) => {
  const stat = Array.isArray(stats.value) ? stats.value[index] ?? {} : {};

  return {
    count: String(stat.value ?? '0'),
    text: String(stat.label ?? `Metric ${index + 1}`),
    subText: index === 0 ? 'Updated moments ago' : 'Live borrowing analytics',
    icon,
    smallIcon: smallIcons[index],
  };
}));

const currentPage = computed(() => props.borrowRows?.current_page ?? 1);
const lastPage = computed(() => props.borrowRows?.last_page ?? 1);
const fromRow = computed(() => props.borrowRows?.from ?? 0);
const toRow = computed(() => props.borrowRows?.to ?? 0);
const totalRows = computed(() => props.borrowRows?.total ?? rows.value.length);

const searchFilter = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');
const perPageFilter = ref(Number(props.filters?.perPage ?? 10));

const submitFilters = (page = 1) => {
  router.get('/borrow', {
    search: searchFilter.value,
    status: statusFilter.value,
    perPage: perPageFilter.value,
    page,
  }, {
    replace: true,
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  searchFilter.value = '';
  statusFilter.value = '';
  perPageFilter.value = 10;
  submitFilters(1);
};

const goToPage = (page) => {
  if (page < 1 || page > lastPage.value || page === currentPage.value) return;
  submitFilters(page);
};

const statusStyle = (status) => {
  const map = {
    'Borrowed':          'bg-emerald-100 text-emerald-700',
    'Available':         'bg-emerald-100 text-emerald-700',
    'Overdue':           'bg-red-100 text-red-700',
    'Returned':          'bg-sky-100 text-sky-700',
    'Damaged':           'bg-slate-200 text-slate-600',
    'Under Maintenance': 'bg-amber-100 text-amber-700',
  };
  return map[status] || 'bg-gray-100 text-gray-600';
};

const stopScanner = () => {
  isScanning.value = false;
  window.removeEventListener('keydown', handleKeydown);
  if (scanFinalizeTimer) {
    clearTimeout(scanFinalizeTimer);
    scanFinalizeTimer = null;
  }
};

const startScanner = () => {
  rfidBuffer = '';
  isScanning.value = true;
  window.removeEventListener('keydown', handleKeydown);
  window.addEventListener('keydown', handleKeydown);
};

const finalizeScan = () => {
  const scannedValue = rfidBuffer.trim();
  if (!scannedValue) return;

  stopScanner();
  lastScanned.value = scannedValue;
  showUserInfo(scannedValue);
  rfidBuffer = '';
};

const handleKeydown = (event) => {
  if (!isScanning.value) return;

  const activeEl = document.activeElement;
  if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT' || activeEl.isContentEditable)) return;

  const currentTime = Date.now();

  if (currentTime - lastKeyTime > SCAN_TIMEOUT) {
    rfidBuffer = '';
  }

  lastKeyTime = currentTime;

  if (SCAN_TERMINATORS.has(event.key)) {
    event.preventDefault();
    if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
    finalizeScan();
  } else if (event.key.length === 1) {
    rfidBuffer += event.key;

    if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
    scanFinalizeTimer = setTimeout(() => {
      finalizeScan();
      scanFinalizeTimer = null;
    }, AUTO_FINALIZE_DELAY);
  }
};

const triggerTestPopup = () => {
  const sortedProfiles = [...registeredStudents.value].sort((a, b) =>
    String(a.studentId ?? '').localeCompare(String(b.studentId ?? '')),
  );
  const demoRfid = sortedProfiles[0]?.rfid || '';
  if (!demoRfid) {
    Swal.fire({
      icon: 'info',
      title: 'No RFID profiles found',
      text: 'Seed borrower profiles first to test the popup.',
      confirmButtonColor: '#2563eb',
    });
    return;
  }

  stopScanner();
  lastScanned.value = demoRfid;
  showUserInfo(demoRfid);
};

const escapeHtml = (value = '') => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');

const findStudentByRfid = (rfid) => {
  const normalizedRfid = String(rfid).trim().toLowerCase();
  return registeredStudents.value.find((student) => student.rfid.toLowerCase() === normalizedRfid) || null;
};

const getBorrowItems = (borrower) => {
  const rfidKey = String(borrower?.rfid ?? '').trim().toLowerCase();
  if (!rfidKey) return [];

  const mappedItems = props.borrowItemsByRfid?.[rfidKey];
  if (Array.isArray(mappedItems)) {
    return mappedItems;
  }

  return [];
};

const showResultPopup = (confirmed, student, borrowItems) => {
  const itemsListHtml = borrowItems
    .map((item) => `<div style="font-size:11px; color:#1f2937;">• ${escapeHtml(item.name)} <span style="color:#64748b;">(${escapeHtml(item.id)})</span></div>`)
    .join('');

  if (confirmed) {
    Swal.fire({
      html: `
        <div style="font-family:'DM Sans',sans-serif; padding:4px 0;">
          <div style="display:flex; flex-direction:column; align-items:center; gap:14px;">

            <!-- Success Icon -->
            <div style="width:56px; height:56px; background:linear-gradient(135deg,#22c55e,#16a34a); border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(34,197,94,0.4);">
              <svg width="28" height="28" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
            </div>
            <div style="text-align:center;">
              <div style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:3px;">Borrowing Successful!</div>
              <div style="font-size:12px; color:#64748b; font-weight:500;">Transaction has been recorded.</div>
            </div>

            <!-- Student ID Card -->
            <div style="width:100%; background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%); border-radius:14px; overflow:hidden; box-shadow:0 6px 24px rgba(37,99,235,0.3); position:relative;">
              <div style="padding:10px 14px 8px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:white; font-weight:700; font-size:10px; letter-spacing:0.8px;">STUDENT ID</span>
                <span style="background:#22c55e; color:white; font-size:8px; font-weight:700; padding:2px 8px; border-radius:99px; letter-spacing:0.5px;">✓ CONFIRMED</span>
              </div>
              <div style="padding:4px 14px 12px; display:flex; gap:12px; align-items:center;">
                <div style="flex-shrink:0; width:56px; height:66px; border-radius:8px; overflow:hidden; border:2px solid rgba(255,255,255,0.3); box-shadow:0 3px 8px rgba(0,0,0,0.3);">
                  <img src="https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(student.name)}&backgroundColor=b6e3f4" style="width:100%; height:100%; object-fit:cover; background:#dbeafe;" />
                </div>
                <div style="flex:1; display:flex; flex-direction:column; gap:4px;">
                  <div style="color:white; font-weight:700; font-size:13px; line-height:1.2;">${escapeHtml(student.name)}</div>
                  <div style="color:rgba(255,255,255,0.7); font-size:10px;">${escapeHtml(student.strand)} &bull; ${escapeHtml(student.section)}</div>
                  <div style="color:rgba(255,255,255,0.7); font-size:10px;">${escapeHtml(student.year)} &bull; ${escapeHtml(student.role)}</div>
                  <div style="color:#93c5fd; font-size:9px; font-family:monospace; letter-spacing:1px;">${escapeHtml(student.studentId)}</div>
                </div>
              </div>
              <div style="background:rgba(0,0,0,0.2); padding:5px 14px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:rgba(255,255,255,0.4); font-size:7px; letter-spacing:1px; text-transform:uppercase;">Academic Year 2025–2026</span>
                <span style="color:rgba(255,255,255,0.4); font-size:7px; letter-spacing:1px;">${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
              </div>
            </div>

            <!-- Transaction Details -->
            <div style="width:100%; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:12px 14px; display:flex; flex-direction:column; gap:8px;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#16a34a; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Borrowed Items</span>
                <span style="color:#0f172a; font-size:11px; font-weight:700;">${borrowItems.length} item${borrowItems.length !== 1 ? 's' : ''}</span>
              </div>
              <div style="height:1px; background:#dcfce7;"></div>
              <div style="display:flex; flex-direction:column; gap:4px;">
                ${itemsListHtml}
              </div>
              <div style="height:1px; background:#dcfce7;"></div>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#16a34a; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Time</span>
                <span style="color:#0f172a; font-size:11px; font-weight:600;">${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            </div>

          </div>
        </div>`,
      showConfirmButton: true,
      confirmButtonText: 'Done',
      confirmButtonColor: '#16a34a',
      allowOutsideClick: false,
      allowEscapeKey: false,
      customClass: { popup: 'swal-result-popup', confirmButton: 'swal-confirm-btn' },
    }).then(() => startScanner());

  } else {
    Swal.fire({
      html: `
        <div style="font-family:'DM Sans',sans-serif; padding:4px 0;">
          <div style="display:flex; flex-direction:column; align-items:center; gap:14px;">

            <!-- Denied Icon -->
            <div style="width:56px; height:56px; background:linear-gradient(135deg,#ef4444,#b91c1c); border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 24px rgba(239,68,68,0.4);">
              <svg width="28" height="28" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </div>
            <div style="text-align:center;">
              <div style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:3px;">Borrowing Denied</div>
              <div style="font-size:12px; color:#64748b; font-weight:500;">The request has been cancelled.</div>
            </div>

            <!-- Student ID Card -->
            <div style="width:100%; background:linear-gradient(135deg,#3f1a1a 0%,#7f1d1d 100%); border-radius:14px; overflow:hidden; box-shadow:0 6px 24px rgba(239,68,68,0.3); position:relative;">
              <div style="padding:10px 14px 8px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:white; font-weight:700; font-size:10px; letter-spacing:0.8px;">STUDENT ID</span>
                <span style="background:#ef4444; color:white; font-size:8px; font-weight:700; padding:2px 8px; border-radius:99px; letter-spacing:0.5px;">✕ DENIED</span>
              </div>
              <div style="padding:4px 14px 12px; display:flex; gap:12px; align-items:center;">
                <div style="flex-shrink:0; width:56px; height:66px; border-radius:8px; overflow:hidden; border:2px solid rgba(255,255,255,0.2); box-shadow:0 3px 8px rgba(0,0,0,0.3); filter:grayscale(60%);">
                  <img src="https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(student.name)}&backgroundColor=b6e3f4" style="width:100%; height:100%; object-fit:cover; background:#dbeafe;" />
                </div>
                <div style="flex:1; display:flex; flex-direction:column; gap:4px;">
                  <div style="color:white; font-weight:700; font-size:13px; line-height:1.2;">${escapeHtml(student.name)}</div>
                  <div style="color:rgba(255,255,255,0.65); font-size:10px;">${escapeHtml(student.strand)} &bull; ${escapeHtml(student.section)}</div>
                  <div style="color:rgba(255,255,255,0.65); font-size:10px;">${escapeHtml(student.year)} &bull; ${escapeHtml(student.role)}</div>
                  <div style="color:#fca5a5; font-size:9px; font-family:monospace; letter-spacing:1px;">${escapeHtml(student.studentId)}</div>
                </div>
              </div>
              <div style="background:rgba(0,0,0,0.25); padding:5px 14px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:rgba(255,255,255,0.35); font-size:7px; letter-spacing:1px; text-transform:uppercase;">Academic Year 2025–2026</span>
                <span style="color:rgba(255,255,255,0.35); font-size:7px; letter-spacing:1px;">${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
              </div>
            </div>

            <!-- Denial Details -->
            <div style="width:100%; background:#fff5f5; border:1px solid #fee2e2; border-radius:12px; padding:12px 14px; display:flex; flex-direction:column; gap:8px;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#dc2626; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Requested Items</span>
                <span style="color:#0f172a; font-size:11px; font-weight:700;">${borrowItems.length} item${borrowItems.length !== 1 ? 's' : ''}</span>
              </div>
              <div style="height:1px; background:#fee2e2;"></div>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#dc2626; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Status</span>
                <span style="background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700; padding:3px 12px; border-radius:99px;">Denied</span>
              </div>
              <div style="height:1px; background:#fee2e2;"></div>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#dc2626; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Time</span>
                <span style="color:#0f172a; font-size:11px; font-weight:600;">${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
              </div>
            </div>

          </div>
        </div>`,
      showConfirmButton: true,
      confirmButtonText: 'Close',
      confirmButtonColor: '#ef4444',
      allowOutsideClick: false,
      allowEscapeKey: false,
      customClass: { popup: 'swal-result-popup', confirmButton: 'swal-confirm-btn' },
    }).then(() => startScanner());
  }
};

const showUserInfo = (rfid) => {
  isScanning.value = false;
  scanPulse.value = true;
  setTimeout(() => (scanPulse.value = false), 600);

  const student = findStudentByRfid(rfid);
  if (!student) {
    Swal.fire({
      icon: 'warning',
      title: 'RFID not registered',
      text: `No student found for RFID: ${rfid}`,
      confirmButtonColor: '#ef4444',
    }).then(() => startScanner());
    return;
  }

  const studentStrandSection = `${student.strand} ${student.section}`;
  const borrowItems = getBorrowItems(student);
  if (borrowItems.length === 0) {
    Swal.fire({
      icon: 'info',
      title: 'No active borrowing',
      text: `${student.name} has no active borrowed items.`,
      confirmButtonColor: '#2563eb',
    }).then(() => startScanner());
    return;
  }

  const confirmedBarcodes = new Set();

  let barcodeBuffer = '';
  let barcodeLastKeyTime = 0;
  let barcodeFinalizeTimer = null;
  let barcodeKeydownHandler = null;

  const finalizeBarcode = (barcodeInput) => {
    const scannedCode = barcodeBuffer.trim();
    if (!scannedCode) return;

    barcodeInput.value = scannedCode;
    barcodeInput.dispatchEvent(new Event('input', { bubbles: true }));

    const matchedItem = borrowItems.find((item) => item.barcode === scannedCode);
    if (matchedItem) {
      confirmedBarcodes.add(matchedItem.barcode);
      renderBorrowItemsStatus();
      updateScanFeedback(`Confirmed: ${matchedItem.name}`, '#065f46');
      barcodeInput.value = '';
    } else {
      updateScanFeedback(`Barcode ${scannedCode} is not in this borrow list.`, '#b91c1c');
    }

    barcodeBuffer = '';
  };

  const getBorrowItemsHtml = () => borrowItems
    .map((item) => {
      const isConfirmedItem = confirmedBarcodes.has(item.barcode);

      return `
        <div style="display:grid; grid-template-columns: auto 1fr auto auto; align-items:center; gap:8px;">
          <span style="width:18px; height:18px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; ${isConfirmedItem ? 'background:#16a34a; color:#ffffff;' : 'background:#e2e8f0; color:#64748b;'}">
            ${isConfirmedItem ? '✓' : '•'}
          </span>
          <span style="color:#0f172a; font-size:12px; font-weight:700;">${escapeHtml(item.name)}</span>
          <span style="color:#0f172a; font-size:10px; font-weight:600; font-family:monospace;">${escapeHtml(item.barcode)}</span>
          <span style="background:${isConfirmedItem ? '#dcfce7' : '#dbeafe'}; color:${isConfirmedItem ? '#15803d' : '#1d4ed8'}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:99px;">${isConfirmedItem ? 'Confirmed' : escapeHtml(item.type)}</span>
        </div>
      `;
    })
    .join('<div style="height:1px; background:#e2e8f0;"></div>');

  const renderBorrowItemsStatus = () => {
    const popup = Swal.getPopup();
    if (!popup) return;

    const container = popup.querySelector('#borrow-items-list');
    const counter = popup.querySelector('#confirmed-items-counter');

    if (container) {
      container.innerHTML = getBorrowItemsHtml();
    }

    if (counter) {
      counter.textContent = `${confirmedBarcodes.size}/${borrowItems.length} confirmed`;
    }
  };

  const updateScanFeedback = (message, color) => {
    const popup = Swal.getPopup();
    if (!popup) return;

    const feedback = popup.querySelector('#scan-feedback');
    if (!feedback) return;

    feedback.textContent = message;
    feedback.style.color = color;
  };

  Swal.fire({
    html: `
      <div style="font-family:'DM Sans',sans-serif; padding:0; display:flex; gap:16px; align-items:flex-start;">
        <div style="flex:0 0 220px; min-width:0;">

        <!-- Student ID Card -->
        <div style="background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%); border-radius:16px; overflow:hidden; box-shadow:0 8px 32px rgba(37,99,235,0.25);">
          <div style="padding:14px 18px 10px; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div style="width:26px; height:26px; background:rgba(255,255,255,0.2); border-radius:6px; display:flex; align-items:center; justify-content:center;">
                <svg width="14" height="14" fill="none" stroke="white" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                </svg>
              </div>
              <span style="color:white; font-weight:700; font-size:12px; letter-spacing:0.5px;">STUDENT ID</span>
            </div>
            <span style="background:rgba(255,255,255,0.15); color:#86efac; font-size:9px; font-weight:700; padding:3px 10px; border-radius:99px; letter-spacing:1px; border:1px solid rgba(134,239,172,0.4);">● ACTIVE</span>
          </div>
          <div style="padding:8px 18px 14px; display:flex; gap:14px; align-items:flex-start;">
            <div style="flex-shrink:0;">
              <div style="width:78px; height:92px; border-radius:10px; overflow:hidden; border:3px solid rgba(255,255,255,0.3); box-shadow:0 4px 12px rgba(0,0,0,0.3);">
                <img src="https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(student.name)}&backgroundColor=b6e3f4"
                  style="width:100%; height:100%; object-fit:cover; background:#dbeafe;" />
              </div>
              <div style="text-align:center; margin-top:5px;">
                <span style="background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.8); font-size:8px; font-weight:600; padding:2px 7px; border-radius:4px; letter-spacing:0.5px;">PHOTO</span>
              </div>
            </div>
            <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
              <div>
                <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Full Name</div>
                <div style="color:white; font-weight:700; font-size:14px;">${escapeHtml(student.name)}</div>
              </div>
              <div>
                <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Strand & Section</div>
                <div style="color:white; font-weight:600; font-size:12px;">${escapeHtml(studentStrandSection)}</div>
              </div>
              <div style="display:flex; gap:16px;">
                <div>
                  <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Role</div>
                  <div style="color:white; font-weight:600; font-size:12px;">${escapeHtml(student.role)}</div>
                </div>
                <div>
                  <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Year</div>
                  <div style="color:white; font-weight:600; font-size:12px;">${escapeHtml(student.year)}</div>
                </div>
              </div>
              <div>
                <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Student ID</div>
                <div style="color:#bfdbfe; font-weight:600; font-size:11px; font-family:monospace; letter-spacing:1px;">${escapeHtml(student.studentId)}</div>
              </div>
              <div>
                <div style="color:rgba(255,255,255,0.5); font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">RFID</div>
                <div style="color:#bfdbfe; font-weight:600; font-size:11px; font-family:monospace; letter-spacing:1px;">${escapeHtml(student.rfid)}</div>
              </div>
            </div>
          </div>
          <div style="background:rgba(0,0,0,0.2); padding:7px 18px; display:flex; justify-content:space-between; align-items:center;">
            <span style="color:rgba(255,255,255,0.45); font-size:8px; letter-spacing:1px; text-transform:uppercase;">Academic Year 2025–2026</span>
            <div style="display:flex; gap:3px;">
              <div style="width:18px; height:3px; background:rgba(255,255,255,0.6); border-radius:2px;"></div>
              <div style="width:8px;  height:3px; background:rgba(255,255,255,0.3); border-radius:2px;"></div>
              <div style="width:13px; height:3px; background:rgba(255,255,255,0.4); border-radius:2px;"></div>
            </div>
          </div>
        </div>
        </div>

        <!-- RIGHT: Items + Barcode -->
        <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:10px;">

        <!-- Item Info -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 16px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <div style="font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">Items to Borrow (${borrowItems.length})</div>
            <div id="confirmed-items-counter" style="font-size:10px; color:#64748b; font-weight:700;">0/${borrowItems.length} confirmed</div>
          </div>
          <div id="borrow-items-list" style="display:flex; flex-direction:column; gap:8px;">
            ${getBorrowItemsHtml()}
          </div>
        </div>

        <!-- Barcode Confirmation -->
        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:12px 16px; text-align:left;">
          <div style="font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#9a3412; margin-bottom:8px;">Barcode Verification Required</div>
          <div style="font-size:12px; color:#7c2d12; margin-bottom:8px;">
            Scan each item barcode. Only confirmed items will be borrowed.
          </div>
          <input
            id="barcode-confirm-input"
            type="text"
            autocomplete="off"
            spellcheck="false"
            placeholder="Waiting for barcode scanner..."
            style="width:100%; padding:8px 10px; border:1px solid #fdba74; border-radius:8px; font-size:12px; color:#7c2d12; background:#fff; font-family:monospace;"
          />
          <div id="scan-feedback" style="font-size:11px; margin-top:8px; color:#7c2d12;">Waiting for barcode scan...</div>
        </div>
        </div>

      </div>`,
      showConfirmButton: true,
      showCancelButton: false,
      showCloseButton: true,
      confirmButtonText: '✓ Confirm Borrowing',
      confirmButtonColor: '#2563eb',
      allowOutsideClick: false,
      allowEscapeKey: false,
      focusConfirm: true,
      didOpen: () => {
        const popup = Swal.getPopup();
        if (!popup) return;

        const barcodeInput = popup.querySelector('#barcode-confirm-input');
        if (!barcodeInput) return;

        barcodeInput.focus();

        barcodeKeydownHandler = (event) => {
          const currentTime = Date.now();

          if (currentTime - barcodeLastKeyTime > SCAN_TIMEOUT) {
            barcodeBuffer = '';
          }

          barcodeLastKeyTime = currentTime;

          if (SCAN_TERMINATORS.has(event.key)) {
            event.preventDefault();
            if (barcodeFinalizeTimer) clearTimeout(barcodeFinalizeTimer);
            finalizeBarcode(barcodeInput);
          } else if (event.key.length === 1) {
            barcodeBuffer += event.key;

            if (barcodeFinalizeTimer) clearTimeout(barcodeFinalizeTimer);
            barcodeFinalizeTimer = setTimeout(() => {
              finalizeBarcode(barcodeInput);
              barcodeFinalizeTimer = null;
            }, AUTO_FINALIZE_DELAY);
          }
        };

        document.addEventListener('keydown', barcodeKeydownHandler, true);
      },
      preConfirm: () => {
        if (confirmedBarcodes.size === 0) {
          Swal.showValidationMessage('Scan at least one item barcode before confirming.');
          return false;
        }

        return borrowItems.filter((item) => confirmedBarcodes.has(item.barcode));
      },
      willClose: () => {
        if (barcodeFinalizeTimer) clearTimeout(barcodeFinalizeTimer);
        if (barcodeKeydownHandler) {
          document.removeEventListener('keydown', barcodeKeydownHandler, true);
        }
      },
    customClass: {
      popup:         'swal-id-popup',
      confirmButton: 'swal-confirm-btn',
      closeButton:   'swal-close-btn',
      actions:       'swal-actions',
    },
  }).then((result) => {
    const confirmedItems = result.isConfirmed && Array.isArray(result.value) ? result.value : [];
    showResultPopup(result.isConfirmed, student, confirmedItems);
  });
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  if (scanFinalizeTimer) clearTimeout(scanFinalizeTimer);
});
</script>

<template>
  <div class="min-h-screen bg-[#f5f6fa]">
    <div class="p-4 space-y-4">
      <div class="flex flex-wrap gap-4">
        <div
          v-for="(item, index) in dashboardCards"
          :key="`${item.text}-${index}`"
          class="min-w-60 flex-1"
        >
          <DashboardCard
            :count="item.count"
            :text="item.text"
            :sub-text="item.subText"
            :icon="item.icon"
            :small_icon="item.smallIcon"
          />
        </div>
      </div>

      <div class="w-full rounded-[10px] bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 pb-4">
          <div class="flex items-center gap-3">
            <h1 class="text-[20px] font-medium">Borrowing Overview</h1>
            <div
              class="flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition-all duration-300"
              :class="isScanning ? 'border border-emerald-200 bg-emerald-50 text-emerald-600' : 'border border-amber-200 bg-amber-50 text-amber-600'"
            >
              <span class="inline-block h-2 w-2 rounded-full" :class="isScanning ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400'"></span>
              {{ isScanning ? 'Ready to Scan' : 'Scanner Paused' }}
            </div>
          </div>

          <div class="flex items-center gap-3 flex-wrap justify-end">
            <div class="relative">
              <svg class="absolute left-3 top-2.5 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="searchFilter"
                type="text"
                placeholder="Search borrower/item/RFID"
                class="pl-8 pr-4 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 w-52 outline-none focus:border-blue-400"
                @keyup.enter="submitFilters(1)"
              />
            </div>
            <select
              v-model="statusFilter"
              class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 font-medium outline-none focus:border-blue-400"
            >
              <option value="">All Status</option>
              <option value="active">Borrowed</option>
              <option value="overdue">Overdue</option>
              <option value="returned">Returned</option>
              <option value="damaged">Damaged</option>
              <option value="lost">Lost</option>
            </select>
            <select
              v-model="perPageFilter"
              class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 font-medium outline-none focus:border-blue-400"
              @change="submitFilters(1)"
            >
              <option :value="5">5 / page</option>
              <option :value="10">10 / page</option>
              <option :value="20">20 / page</option>
              <option :value="50">50 / page</option>
            </select>
            <button
              class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors"
              @click="submitFilters(1)"
            >
              Apply
            </button>
            <button
              class="px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <div class="min-w-290">
            <div class="flex px-4 text-sm text-gray-400">
              <span class="w-42.5">Borrowers</span>
              <span class="w-22.5">ID</span>
              <span class="w-22.5">Role</span>
              <span class="w-35">Item</span>
              <span class="w-20 text-center">Number</span>
              <span class="w-30">Date</span>
              <span class="w-30">Status</span>
              <span class="w-30">Borrowed Time</span>
              <span class="w-27.5">Return Time</span>
              <span class="w-30">Borrowed Hours</span>
            </div>

            <div class="mt-2 flex flex-col gap-3">
              <div
                v-for="(row, i) in rows"
                :key="i"
                class="flex items-center rounded-[10px] border border-gray-200 p-4 transition-colors hover:bg-blue-50/30"
              >
                <span class="w-42.5 truncate font-medium text-gray-700">{{ row.name }}</span>
                <span class="w-22.5 text-gray-500">{{ row.id }}</span>
                <span class="w-22.5" :class="row.role === 'Teacher' ? 'font-semibold text-blue-600' : 'text-gray-500'">{{ row.role }}</span>
                <span class="w-35 truncate text-gray-600">{{ row.item }}</span>
                <span class="w-20 text-center text-gray-500">{{ row.number }}</span>
                <span class="w-30 whitespace-nowrap text-gray-500">{{ row.date }}</span>
                <span class="w-30">
                  <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap', statusStyle(row.status)]">
                    {{ row.status }}
                  </span>
                </span>
                <span class="w-30 font-mono text-xs text-gray-500">{{ row.timeIn }}</span>
                <span class="w-27.5 font-mono text-xs" :class="row.timeOut === '00:00' ? 'text-red-400' : 'text-gray-500'">{{ row.timeOut }}</span>
                <span class="w-30 text-xs font-medium text-gray-600">{{ row.hours }}</span>
              </div>

              <div
                v-if="rows.length === 0"
                class="rounded-[10px] border border-dashed border-gray-200 px-6 py-8 text-center text-sm text-gray-400"
              >
                No borrowing records found for the current filters.
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-xs text-gray-500">
          <div>
            Showing {{ fromRow }} to {{ toRow }} of {{ totalRows }} records
          </div>
          <div class="flex items-center gap-2">
            <button
              class="px-2.5 py-1 border border-gray-200 rounded-md bg-white hover:bg-gray-50 disabled:opacity-40"
              :disabled="currentPage <= 1"
              @click="goToPage(currentPage - 1)"
            >
              Prev
            </button>
            <span>Page {{ currentPage }} of {{ lastPage }}</span>
            <button
              class="px-2.5 py-1 border border-gray-200 rounded-md bg-white hover:bg-gray-50 disabled:opacity-40"
              :disabled="currentPage >= lastPage"
              @click="goToPage(currentPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating RFID Toggle Button -->
    <button
      type="button"
      class="fixed bottom-6 right-6 flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg text-sm font-semibold transition-all duration-300 z-50 select-none cursor-pointer"
      :class="scanPulse
        ? 'bg-emerald-500 text-white scale-110'
        : isScanning
          ? 'bg-white text-gray-700 border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300'
          : 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100'"
      :title="isScanning ? 'Click to disable RFID scanner' : 'Click to enable RFID scanner'"
      @click="!scanPulse && (isScanning ? stopScanner() : startScanner())"
    >
      <!-- RFID Card icon -->
      <svg class="w-5 h-5" :class="isScanning ? 'text-emerald-500' : 'text-amber-400'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="1.8"/>
        <rect x="5" y="9" width="5" height="4" rx="1" stroke-width="1.5"/>
        <path stroke-linecap="round" stroke-width="1.5" d="M15 10.5a1.5 1.5 0 0 1 0 3"/>
        <path stroke-linecap="round" stroke-width="1.5" d="M17.5 8.5a4 4 0 0 1 0 7"/>
      </svg>
      <span>{{ scanPulse ? 'Scanned!' : isScanning ? 'RFID Listening' : 'Scanner Paused' }}</span>
      <span v-if="isScanning && !scanPulse" class="w-2 h-2 rounded-full bg-emerald-500 animate-ping absolute top-2 right-2"></span>
    </button>

    <button
      type="button"
      class="fixed bottom-20 right-6 px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold shadow-md hover:bg-blue-700 transition-colors z-50"
      @click="triggerTestPopup"
    >
      Test RFID Popup
    </button>

  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap');

/* ── Popup 1: ID card + item ── */
.swal-id-popup {
  border-radius: 20px !important;
  padding: 20px !important;
  width: 760px !important;
  max-width: 95vw !important;
  font-family: 'DM Sans', sans-serif !important;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
}
.swal-id-popup .swal2-html-container {
  overflow: visible !important;
  text-align: left !important;
  margin: 0 !important;
  padding: 0 !important;
}

/* ── Popup 2: result ── */
.swal-result-popup {
  border-radius: 20px !important;
  padding: 28px 24px 24px !important;
  max-width: 420px !important;
  font-family: 'DM Sans', sans-serif !important;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
}

.swal-actions {
  margin-top: 16px !important;
  gap: 10px !important;
}

.swal-confirm-btn {
  border-radius: 10px !important;
  font-family: 'DM Sans', sans-serif !important;
  font-weight: 700 !important;
  font-size: 13px !important;
  padding: 10px 28px !important;
  letter-spacing: 0.3px !important;
}

/* ── X close button top-right ── */
.swal-close-btn {
  width: 30px !important;
  height: 30px !important;
  border-radius: 50% !important;
  background: #f1f5f9 !important;
  color: #64748b !important;
  font-size: 20px !important;
  top: 10px !important;
  right: 10px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: background 0.15s, color 0.15s !important;
}

.swal-close-btn:hover {
  background: #fee2e2 !important;
  color: #ef4444 !important;
}
</style>