(function () {
  const DEFAULT_MESSAGES = {
    retry: {
      success: "Envío reintentado correctamente.",
      error: "No se pudo reintentar. Intentá nuevamente más tarde.",
      network: "Error de red. Intentá nuevamente más tarde.",
      missing: "Faltan datos para reintentar el envío.",
    },
    label: {
      success: "Etiqueta descargada correctamente.",
      error: "No se pudo obtener la etiqueta.",
      network: "Error de red. Intentá nuevamente más tarde.",
      missing: "Faltan datos para descargar la etiqueta.",
      generating: "Generando etiqueta...",
    },
    copy: {
      success: "Copiado al portapapeles.",
      error: "No se pudo copiar al portapapeles.",
      empty: "No hay texto para copiar.",
    },
  };

  function createNoticeHTML(message, type) {
    const cls = "notice notice-" + (type || "info") + " is-dismissible";
    return `<div class="${cls}"><p>${escapeHtml(
      message || ""
    )}</p><button type="button" class="notice-dismiss" aria-label="Cerrar aviso"><span class="screen-reader-text">Cerrar aviso</span></button></div>`;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function insertNoticeNear(el, message, type) {
    const html = createNoticeHTML(message, type);
    const card =
      el.closest(".woocommerce-column--andreani") ||
      el.closest(".woocommerce-column") ||
      el.parentNode;
    const container = card || document.body;

    const tmp = document.createElement("div");
    tmp.innerHTML = html;
    const notice = tmp.firstElementChild;

    container.insertBefore(notice, container.firstChild);

    const dismiss = notice.querySelector(".notice-dismiss");
    if (dismiss) {
      dismiss.addEventListener("click", () => {
        if (notice && notice.parentNode) notice.parentNode.removeChild(notice);
      });
    }
    return notice;
  }

  function getAjaxData(btn, action, nonceKey = "nonce") {
    const ajaxUrl =
      btn.getAttribute("data-ajax-url") ||
      (typeof window !== "undefined" && typeof window.ajaxurl !== "undefined"
        ? window.ajaxurl
        : "") ||
      (typeof andreani_ajax !== "undefined" ? andreani_ajax.url : "");

    const actionName = btn.getAttribute("data-action") || action;
    const nonce =
      btn.getAttribute("data-nonce") ||
      (typeof andreani_ajax !== "undefined"
        ? andreani_ajax[nonceKey] || andreani_ajax.nonce
        : "");
    const orderId = btn.getAttribute("data-order-id") || "";

    return { ajaxUrl, action: actionName, nonce, orderId };
  }

  function handleAjaxRequest(btn, config) {
    const { ajaxUrl, action, nonce, orderId } = getAjaxData(
      btn,
      config.action,
      config.nonceKey
    );

    if (!ajaxUrl || !action || !orderId) {
      insertNoticeNear(btn, config.messages.missing, "error");
      return;
    }

    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = config.loadingText;

    const form = new FormData();
    form.append("action", action);
    form.append("nonce", nonce);
    form.append("order_id", orderId);

    fetch(ajaxUrl, { method: "POST", credentials: "same-origin", body: form })
      .then((r) => r.json())
      .then((json) => {
        if (json && json.success) {
          if (config.onSuccess) {
            config.onSuccess(btn, json, oldText);
          } else {
            insertNoticeNear(btn, config.messages.success, "success");
            btn.textContent = oldText;
            btn.disabled = false;
          }
        } else {
          const msg = json?.data?.message || config.messages.error;
          const type = json?.data?.type || "error";
          insertNoticeNear(btn, msg, type);
          btn.disabled = false;
          btn.textContent = oldText;
        }
      })
      .catch(() => {
        insertNoticeNear(btn, config.messages.network, "error");
        btn.disabled = false;
        btn.textContent = oldText;
      });
  }

  function onClickRetry(e) {
    const btn = e.currentTarget;
    if (!btn || btn.disabled) return;

    handleAjaxRequest(btn, {
      action: "andreani_retry_order",
      loadingText: "Reintentando...",
      messages: DEFAULT_MESSAGES.retry,
      onSuccess: (btn, json, oldText) => {
        insertNoticeNear(
          btn,
          json.data?.message || DEFAULT_MESSAGES.retry.success,
          json.data?.type || "success"
        );
        btn.textContent = "Listo, actualizando...";
        setTimeout(() => location.reload(), 800);
      },
    });
  }

  function onClickDownloadLabel(e) {
    const btn = e.currentTarget;
    if (!btn || btn.disabled) return;

    handleAjaxRequest(btn, {
      action: "andreani_get_etiqueta",
      nonceKey: "nonce_etiqueta",
      loadingText: DEFAULT_MESSAGES.label.generating,
      messages: DEFAULT_MESSAGES.label,
      onSuccess: (btn, json, oldText) => {
        if (json.data?.pdf) {
          try {
            const byteCharacters = atob(
              json.data.pdf.replace(/^data:application\/pdf;base64,/, "")
            );
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
              byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], { type: "application/pdf" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = (json.data.filename || "etiqueta.pdf").replace(
              /[^a-zA-Z0-9_.-]/g,
              "_"
            );
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
              URL.revokeObjectURL(url);
              if (a.parentNode) a.parentNode.removeChild(a);
            }, 500);
            insertNoticeNear(btn, DEFAULT_MESSAGES.label.success, "success");
            btn.textContent = oldText;
            btn.disabled = false;
          } catch (err) {
            insertNoticeNear(btn, DEFAULT_MESSAGES.label.error, "error");
            btn.textContent = oldText;
            btn.disabled = false;
          }
        } else {
          insertNoticeNear(btn, DEFAULT_MESSAGES.label.error, "error");
          btn.textContent = oldText;
          btn.disabled = false;
        }
      },
    });
  }

  function onClickCopy(e) {
    const element = e.currentTarget;
    const textToCopy =
      element.getAttribute("data-copy-text") || element.textContent || "";

    if (!textToCopy) return;

    try {
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard
          .writeText(textToCopy)
          .then(() => showCopySuccess(element))
          .catch(() => fallbackCopy(textToCopy, element));
      } else {
        fallbackCopy(textToCopy, element);
      }
    } catch (err) {
      fallbackCopy(textToCopy, element);
    }
  }

  function fallbackCopy(text, element) {
    try {
      const textArea = document.createElement("textarea");
      textArea.value = text;
      Object.assign(textArea.style, {
        position: "fixed",
        left: "-999999px",
        top: "-999999px",
      });
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      document.execCommand("copy");
      textArea.remove();
      showCopySuccess(element);
    } catch (err) {}
  }

  function showCopySuccess(element) {
    try {
      element.classList.add("copied");

      const existing = element.parentNode.querySelector(
        ".andreani-inline-copied"
      );
      if (existing) existing.remove();

      const inline = document.createElement("span");
      inline.className = "andreani-inline-copied";
      inline.textContent = "copiado!";
      element.parentNode.insertBefore(inline, element.nextSibling);

      setTimeout(() => {
        if (inline && inline.parentNode) inline.parentNode.removeChild(inline);
        element.classList.remove("copied");
      }, 900);
    } catch (err) {}
  }

  function bind() {
    const buttons = document.querySelectorAll("button.andreani-retry");
    buttons.forEach((btn) => {
      if (!btn.dataset.bound) {
        btn.addEventListener("click", onClickRetry);
        btn.dataset.bound = "1";
      }
    });

    const labelBtns = document.querySelectorAll(
      "button.andreani-download-label"
    );
    labelBtns.forEach((btn) => {
      if (!btn.dataset.bound) {
        btn.addEventListener("click", onClickDownloadLabel);
        btn.dataset.bound = "1";
      }
    });

    const copyBtns = document.querySelectorAll(".andreani-copy-number");
    copyBtns.forEach((element) => {
      if (!element.dataset.bound) {
        element.addEventListener("click", onClickCopy);
        element.dataset.bound = "1";
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bind);
  } else {
    bind();
  }

  if (typeof MutationObserver !== "undefined") {
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
          for (let node of mutation.addedNodes) {
            if (node.nodeType === 1) {
              const hasRetryBtn =
                node.querySelector &&
                node.querySelector("button.andreani-retry");
              const hasLabelBtn =
                node.querySelector &&
                node.querySelector("button.andreani-download-label");
              const hasCopyBtn =
                node.querySelector &&
                node.querySelector(".andreani-copy-number");

              if (hasRetryBtn || hasLabelBtn || hasCopyBtn) {
                setTimeout(bind, 100);
                break;
              }
            }
          }
        }
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  if (typeof jQuery !== "undefined") {
    jQuery(document).ready(function () {
      bind();
    });
  }
})();
