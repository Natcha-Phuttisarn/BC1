// ลดขนาดรูปฝั่งผู้ใช้ก่อนอัปโหลด เพื่อให้หน้าเว็บโหลดเร็วขึ้น
(function () {
  function resize(file, maxSide, quality) {
    return new Promise(function (resolve) {
      if (!file.type || file.type.indexOf('image/') !== 0 || !window.createImageBitmap) return resolve(file);
      createImageBitmap(file).then(function (bitmap) {
        var scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 1600000) return resolve(file);
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          resolve(blob ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }) : file);
        }, 'image/jpeg', quality);
      }).catch(function () { resolve(file); });
    });
  }
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.matches('input[type="file"]')) return;
    var files = Array.prototype.slice.call(input.files || []);
    if (!files.some(function (f) { return f.type.indexOf('image/') === 0; })) return;
    Promise.all(files.map(function (file) { return resize(file, 1600, 0.84); })).then(function (optimized) {
      try {
        var transfer = new DataTransfer();
        optimized.forEach(function (file) { transfer.items.add(file); });
        input.files = transfer.files;
      } catch (_) {}
    });
  });
})();
