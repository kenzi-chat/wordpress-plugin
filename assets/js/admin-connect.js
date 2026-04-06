/**
 * Kenzi Chat admin — connect and disconnect flows.
 *
 * Handles the Kenzi Connect popup for workspace linking and the
 * disconnect flow that notifies the Kenzi backend before clearing
 * local settings. Uses postMessage with a cryptographic nonce for
 * secure cross-origin communication with the Connect popup.
 *
 * Configuration is provided via wp_localize_script as `window.kenziChatAdmin`.
 *
 * @package Kenzi\Chat
 */
(function () {
  "use strict";

  const config = window.kenziChatAdmin || {};

  // Cryptographic nonce for postMessage replay protection.
  let currentNonce = null;

  /**
   * Generate a 32-byte hex nonce using the Web Crypto API.
   */
  function generateNonce() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
  }

  /**
   * Open the Kenzi Connect popup for workspace linking.
   *
   * The popup communicates back via postMessage with the connection
   * credentials (workspace_id, secret, etc.) which are then saved
   * via an AJAX call to the WordPress backend.
   */
  window.kenziConnect = function () {
    currentNonce = generateNonce();

    const params = {
      platform: "wordpress",
      instance_key: config.instanceKey,
      origin: config.storeUrl,
      nonce: currentNonce,
      api_url: config.apiUrl,
      admin_url: config.adminUrl,
    };

    // Include detected capabilities so the backend knows what to request
    // in the consent screen. The user's actual grant is returned in the
    // postMessage payload and is independent of what we request here.
    if (config.capabilities && config.capabilities.length > 0) {
      params.requested_capabilities = config.capabilities.join(",");
    }

    const connectUrl = config.connectUrl + "?" + new URLSearchParams(params);

    const width = 500;
    const height = 600;
    const left = Math.round((screen.width - width) / 2);
    const top = Math.round((screen.height - height) / 2);
    const popup = window.open(
      connectUrl,
      "kenzi_connect",
      `popup,width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`,
    );

    if (!popup || popup.closed) {
      alert(config.i18n.popupBlocked);
      currentNonce = null;
    }
  };

  /**
   * Disconnect the current workspace after user confirmation.
   *
   * Notifies the Kenzi backend to mark the integration as disconnected,
   * then clears the local connection settings.
   */
  window.kenziDisconnect = function () {
    if (!confirm(config.i18n.confirmDisconnect)) {
      return;
    }

    fetch(config.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "kenzi_disconnect",
        _wpnonce: config.nonces.disconnect,
      }),
    })
      .then((r) => r.json())
      .then((result) => {
        if (result.success) {
          window.location.href = config.settingsUrl;
        } else {
          alert(
            config.i18n.disconnectFailed +
              " " +
              (result.data || "Unknown error — please check your connection and try again"),
          );
        }
      })
      .catch(() => alert(config.i18n.disconnectFailedRetry));
  };

  /**
   * Listen for postMessage from the Kenzi Connect popup.
   *
   * Validates the message origin against the expected Kenzi server origin,
   * verifies the cryptographic nonce to prevent replay attacks, then
   * persists the connection credentials via AJAX.
   */
  window.addEventListener("message", function (event) {
    // Only accept messages from the Kenzi server origin.
    const expectedOrigin = new URL(config.connectUrl).origin;
    if (event.origin !== expectedOrigin) {
      return;
    }

    if (event.data?.type === "kenzi_connected") {
      // Verify the nonce matches what we generated for this session.
      if (event.data.nonce !== currentNonce) {
        alert(config.i18n.securityFailed);
        return;
      }

      // Close the popup now that we have the credentials.
      if (event.source && typeof event.source.close === "function") {
        event.source.close();
      }
      currentNonce = null;

      // Save the connection credentials via AJAX.
      const params = {
        action: "kenzi_save_connection",
        _wpnonce: config.nonces.save,
        workspace_id: event.data.workspace_id || "",
        workspace_name: event.data.workspace_name || "",
        secret: event.data.shared_secret || "",
        integration_id: String(event.data.integration_id || ""),
      };

      if (Array.isArray(event.data.capabilities)) {
        params.capabilities = event.data.capabilities.join(",");
      }

      fetch(config.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(params),
      })
        .then((r) => r.json())
        .then((result) => {
          if (result.success) {
            window.location.href = config.settingsUrl;
          } else {
            alert(
              config.i18n.saveFailed + " " + (result.data || "Unknown error"),
            );
          }
        })
        .catch(() => alert(config.i18n.saveFailedRetry));
    }
  });
})();
