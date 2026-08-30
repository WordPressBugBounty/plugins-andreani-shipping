/**
 * Andreani — tracking live para el cliente final.
 * Sobre la Heartbeat API de WordPress: en cada tick pide el numero de seguimiento
 * de la orden y, cuando aparece, lo pinta sin recargar.
 */
(function () {
  var cfg = window.andreaniTracking;
  if (!cfg || !cfg.orderId || !cfg.orderKey || typeof jQuery === "undefined") {
    return;
  }

  var $ = jQuery;
  var done = false;

  function setHeartbeatSpeed(speed) {
    if (window.wp && wp.heartbeat && typeof wp.heartbeat.interval === "function") {
      wp.heartbeat.interval(speed);
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function render(res) {
    var box = document.querySelector(".andreani-live-tracking");
    if (!box) {
      return;
    }
    var dd =
      '<code>' + escapeHtml(res.tracking_number) + "</code>";
    if (res.tracking_url) {
      dd +=
        ' <a href="' +
        encodeURI(res.tracking_url) +
        '" target="_blank" rel="noopener" class="andreani-track-link">' +
        escapeHtml(cfg.trackText) +
        "</a>";
    }
    box.innerHTML = "<dt>" + escapeHtml(cfg.labelReady) + "</dt><dd>" + dd + "</dd>";
    box.classList.remove("is-pending");
    box.classList.add("is-ready");
  }

  setHeartbeatSpeed("fast");

  $(document).on("heartbeat-send", function (event, data) {
    if (done) {
      return;
    }
    data.andreani_track = { order_id: cfg.orderId, order_key: cfg.orderKey };
  });

  $(document).on("heartbeat-tick", function (event, data) {
    var res = data.andreani_track;
    if (!res || !res.done || !res.tracking_number) {
      return;
    }
    done = true;
    render(res);
    setHeartbeatSpeed("standard");
  });
})();
