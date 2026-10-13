// ยืนยันก่อนส่งฟอร์มที่มี data-confirm (ใช้แทน inline onclick เพราะ CSP ไม่อนุญาต inline script)
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
