/**
 * Namespaced wrapper around Web Storage.
 *
 * Only UI state and the session reference belong here. Never store passwords,
 * API secrets or anything the backend must be able to trust.
 */
import { appConfig } from "../config/app.config.js";

function makeStore(backing) {
  const key = (name) => `${appConfig.storagePrefix}${name}`;

  return {
    get(name, fallback = null) {
      try {
        const raw = backing.getItem(key(name));
        return raw === null ? fallback : JSON.parse(raw);
      } catch {
        return fallback;
      }
    },
    set(name, value) {
      try {
        backing.setItem(key(name), JSON.stringify(value));
        return true;
      } catch {
        return false;
      }
    },
    remove(name) {
      try {
        backing.removeItem(key(name));
      } catch {
        /* storage unavailable — ignore */
      }
    },
    clear() {
      try {
        Object.keys(backing)
          .filter((k) => k.startsWith(appConfig.storagePrefix))
          .forEach((k) => backing.removeItem(k));
      } catch {
        /* ignore */
      }
    },
  };
}

export const storage = makeStore(window.localStorage);
export const sessionStore = makeStore(window.sessionStorage);
