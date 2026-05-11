/**
 * Kenzi Chat admin — connect, configure, and disconnect flows.
 *
 * Handles the Kenzi Connect popup for workspace linking, the configure
 * step that mints platform credentials, and the disconnect flow.
 * Uses postMessage with source + origin verification for secure
 * cross-origin communication with the Connect popup.
 *
 * Configuration is provided via wp_localize_script as `window.kenziChatAdmin`.
 *
 * @package Kenzi\Chat
 */
(function () {
  "use strict";

  const config = window.kenziChatAdmin || {};
  if (!config.connectUrl || !config.restBase) return;

  // Kenzi app origin — derived once from the connect URL.
  const kenziAppOrigin = new URL(config.connectUrl).origin;

  // Reference to the popup window, held at module scope for source checks.
  let popupRef = null;

  // Root element — all state renders target this container.
  const root = document.getElementById("kenzi-settings");
  if (!root) return;

  /**
   * Call a plugin REST endpoint.
   *
   * @param {string} method HTTP method.
   * @param {string} path Route path (e.g. "/kenzi/connect").
   * @param {object|null} body JSON body (omitted for GET).
   * @returns {Promise<{ok: boolean, status: number, data: any}>}
   */
  function restCall(method, path, body) {
    const options = {
      method: method,
      headers: {
        "X-WP-Nonce": config.restNonce,
      },
      credentials: "same-origin",
    };

    if (body !== undefined && body !== null) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
    }

    return fetch(config.restBase + path, options)
      .then(function (r) {
        return r.json().then(
          function (data) { return { ok: r.ok, status: r.status, data: data }; },
          function () { return { ok: false, status: r.status, data: null }; }
        );
      })
      .catch(function () {
        return { ok: false, status: 0, data: null };
      });
  }

  // -- Connect popup (§5) --

  /**
   * Open the Kenzi Connect popup with the given query params.
   *
   * Must be called synchronously inside a click handler so popup
   * blockers honor the user activation. The popup communicates back
   * via postMessage — see the listener below.
   */
  function openConnectPopup(params) {
    popupRef = null;
    const url = new URL("/connect", config.connectUrl);
    url.searchParams.set("key", config.instanceKey);

    for (const k in params) {
      url.searchParams.set(k, params[k]);
    }

    popupRef = window.open(url.toString(), "kenzi_connect");
    if (popupRef) {
      popupRef.focus();
    } else {
      alert(config.i18n.popupBlocked);
    }
  }

  window.kenziConnect = function () {
    const params = { type: config.platformType };
    if (config.supportedGrants) {
      params.supported_grants = config.supportedGrants;
    }
    openConnectPopup(params);
  };

  window.kenziConnectCommerce = function () {
    openConnectPopup({ type: config.platformType, supported_grants: "commerce" });
  };

  // -- Disconnect (§10) --

  /**
   * Disconnect the current workspace after user confirmation.
   */
  window.kenziDisconnect = function () {
    if (!confirm(config.i18n.confirmDisconnect)) {
      return;
    }

    restCall("POST", "/kenzi/disconnect").then(function (result) {
      if (result.ok) {
        renderConnectButton();
      } else {
        alert(config.i18n.disconnectFailed);
      }
    });
  };

  // -- Settings page renderer --

  /**
   * Single rendering entry point for all Kenzi API responses.
   *
   * Every Kenzi-proxied endpoint (GET /integration, POST /configure,
   * PATCH {claim: null}) returns the same eight-field projection —
   * pipe any of them through here to update the page in place.
   */
  function renderConnection(result) {
    // HTTP error dispatch (§9.2).
    if (!result.ok) {
      if (result.status === 401 || result.status === 403 || result.status === 404) {
        // Best-effort: clear local state. Render connect button regardless
        // of disconnect outcome — the user can reconnect either way.
        restCall("POST", "/kenzi/disconnect").then(function () {
          renderConnectButton(config.i18n.connectionReset);
        });
        return;
      }
      // 502/504/network — transient error.
      renderConnectButton(config.i18n.unreachable);
      return;
    }

    const integration = result.data;

    // Two-state dispatch: connected (configured + claimed) or error.
    if (integration.configured === true && integration.claimed === true) {
      renderSettings(integration);
    } else {
      renderError();
    }
  }

  function renderConnectButton(errorMessage) {
    let html = "<h2>Connection</h2>";

    if (errorMessage) {
      html +=
        '<div class="notice notice-error inline"><p>' +
        esc(errorMessage) +
        "</p></div>";
    }

    html +=
      '<p class="kenzi-status-prompt">' +
      esc(config.i18n.connectPrompt) +
      "</p>" +
      '<button type="button" onclick="kenziConnect()" class="kenzi-btn-connect">' +
      '<svg width="22" height="22" viewBox="0 0 240 240" fill="none" xmlns="http://www.w3.org/2000/svg">' +
      '<circle cx="120" cy="120" r="110" fill="#6cb7b7"/>' +
      '<path d="M155.8,72.56c-9.96-11.74-25.65-18.33-42.32-18.33-31.53,0-57.5,25.97-57.5,57.5s25.97,57.5,57.5,57.5c11.23,0,22.04-3.34,31.3-9.56,2.17-1.44,2.71-4.45,1.26-6.62-1.44-2.17-4.45-2.71-6.62-1.26-7.56,5.06-16.39,7.78-25.54,7.78-25.18,0-45.64-20.45-45.64-45.64s20.45-45.64,45.64-45.64c13.47,0,26.3,5.57,34.26,14.95,6.45,7.63,9.17,17.19,7.61,26.98-.12.92-3.17,22.41-28.1,22.41-4.1,0-7.35-1.48-9.96-4.52-3.44-3.94-4.95-9.55-5.17-14.57,21.42-2.68,30.03-16.53,30.43-17.19,1.48-2.48.72-5.76-1.76-7.24-2.48-1.48-5.73-.73-7.2,1.76-.3.48-6.85,10.53-23.64,12.29l8.5-18.4c1.22-2.66.06-5.79-2.6-7.01-2.66-1.22-5.8-.06-7.02,2.6l-24.35,52.84c-1.22,2.66-.06,5.79,2.6,7.01.72.33,1.48.49,2.22.49,2,.0,3.92-1.15,4.82-3.08l8.14-17.68c1.02,5.27,3.18,10.7,7.04,15.18,4.64,5.38,10.87,8.22,18.02,8.22,27.25,0,37.18-20.62,38.63-31.6,2.05-12.83-1.49-25.37-10-35.39Z" fill="#ece8e0"/>' +
      "</svg>" +
      "<span>" +
      esc(config.i18n.connectButton) +
      "</span>" +
      "</button>";

    root.innerHTML = html + widgetSection(false);
  }

  function renderError() {
    root.innerHTML =
      "<h2>Connection</h2>" +
      '<div class="notice notice-warning inline"><p>' +
      esc(config.i18n.somethingWrong) +
      "</p></div>" +
      '<button type="button" class="button kenzi-btn-disconnect" onclick="kenziDisconnect()">' +
      esc(config.i18n.disconnect) +
      "</button>" +
      widgetSection(false);
  }

  function renderSettings(data) {
    const workspaceName =
      data.claim && data.claim.workspace ? data.claim.workspace.name : "Kenzi";

    let html =
      "<h2>Connection</h2>" +
      '<table class="form-table"><tr>' +
      '<th scope="row">Workspace</th><td>' +
      '<p class="kenzi-status-connected">' +
      '<span class="dashicons dashicons-yes-alt"></span> ' +
      esc(config.i18n.connectedTo.replace("%s", workspaceName)) +
      "</p>";

    // Forward-compatibility for unknown status values.
    if (data.status !== "active") {
      html +=
        '<div class="notice notice-warning inline"><p>' +
        esc(config.i18n.unknownStatus.replace("%s", data.status)) +
        "</p></div>";
    }

    // Enable Commerce button — shown when WooCommerce is active but the
    // current integration doesn't have the commerce grant.
    if (
      config.hasWooCommerce &&
      !(data.claim && data.claim.grants && data.claim.grants.indexOf("commerce") !== -1)
    ) {
      html +=
        '<button type="button" class="button button-primary" onclick="kenziConnectCommerce()" style="margin-right:8px">' +
        esc(config.i18n.enableCommerce) +
        "</button>";
    }

    html +=
      '<button type="button" class="button kenzi-btn-disconnect" onclick="kenziDisconnect()">' +
      esc(config.i18n.disconnect) +
      "</button>" +
      "</td></tr></table>";

    root.innerHTML = html + widgetSection(true);
  }

  function widgetSection(connected) {
    const disabled = connected ? "" : " disabled";
    const checked = config.widgetEnabled ? " checked" : "";
    const hint = connected ? config.i18n.widgetHint : config.i18n.widgetHintDisabled;

    return "<h2>Widget Settings</h2>" +
      '<form method="post" action="">' +
      '<input type="hidden" name="_kenzi_widget_nonce" value="' + esc(config.widgetNonce) + '">' +
      '<input type="hidden" name="kenzi_save_widget" value="1">' +
      '<table class="form-table"><tr>' +
      '<th scope="row">' + esc(config.i18n.widgetLabel) + '</th><td>' +
      "<label>" +
      '<input type="checkbox" name="widget_enabled" value="1"' + checked + disabled + "> " +
      esc(config.i18n.widgetLabel) +
      "</label>" +
      '<p class="description">' + esc(hint) + "</p>" +
      "</td></tr></table>" +
      '<p class="submit">' +
      '<button type="submit" class="button button-primary"' + disabled + ">" +
      esc(config.i18n.saveChanges) +
      "</button></p></form>";
  }

  function esc(str) {
    const el = document.createElement("span");
    el.textContent = str || "";
    return el.innerHTML;
  }

  // -- Page load: fetch integration state and render --

  if (!config.isConnected) {
    renderConnectButton();
  } else {
    restCall("GET", "/kenzi/integration").then(renderConnection);
  }

  // -- postMessage listener (§5.3) --

  /**
   * Listen for postMessage from the Kenzi Connect popup.
   *
   * Verifies source (must be the popup we opened), origin (must be
   * the configured Kenzi app origin), and payload shape. Then calls
   * POST /kenzi/connect followed by POST /kenzi/configure.
   */
  window.addEventListener("message", function (event) {
    // 1. Source check — must be the popup we opened.
    if (event.source !== popupRef) return;

    // 2. Origin check — must be the configured Kenzi app origin.
    if (event.origin !== kenziAppOrigin) return;

    // 3. Payload shape — the three fields we actually use.
    const payload = event.data || {};
    if (
      typeof payload.shared_secret !== "string" ||
      typeof payload.workspace_id !== "string" ||
      !Array.isArray(payload.grants)
    ) {
      console.warn("[kenzi] postMessage payload rejected — missing or malformed fields", payload);
      return;
    }

    // Prevent duplicate processing if the popup posts twice.
    popupRef = null;

    // Show spinner while the connect + configure calls run.
    root.innerHTML =
      '<p><span class="spinner is-active" style="float:none;margin:0 4px 0 0"></span>' +
      esc(config.i18n.connecting) +
      "</p>";

    // §5.4: Hand off to local controllers — connect then configure.
    restCall("POST", "/kenzi/connect", {
      shared_secret: payload.shared_secret,
      workspace_id: payload.workspace_id,
      grants: payload.grants,
    })
      .then(function (result) {
        if (!result.ok) {
          renderError();
          return;
        }

        return restCall("POST", "/kenzi/configure");
      })
      .then(function (result) {
        if (!result) return; // connect failed, already rendered error

        // Configure proxies the Kenzi projection — render it directly.
        renderConnection(result);
      })
      .catch(function () {
        renderError();
      });
  });
})();
