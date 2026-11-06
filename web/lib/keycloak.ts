import type Keycloak from "keycloak-js";

let keycloak: Keycloak | null = null;
let initPromise: Promise<Keycloak> | null = null;

export function initKeycloak(): Promise<Keycloak> {
  if (typeof window === "undefined") {
    return Promise.reject(new Error("Keycloak runs in the browser"));
  }
  if (!initPromise) {
    initPromise = (async () => {
      const KeycloakJs = (await import("keycloak-js")).default;
      const kc = new KeycloakJs({
        url: process.env.NEXT_PUBLIC_KEYCLOAK_URL || "http://localhost:8081",
        realm: "brewsbook",
        clientId: process.env.NEXT_PUBLIC_OIDC_CLIENT_ID || "brewsbook-nextjs",
      });
      keycloak = kc;
      await kc.init({
        onLoad: "check-sso",
        pkceMethod: "S256",
        checkLoginIframe: false,
        silentCheckSsoRedirectUri: `${window.location.origin}/silent-check-sso.html`,
      });
      return kc;
    })().catch((error: unknown) => {
      initPromise = null;
      keycloak = null;
      throw error;
    });
  }
  return initPromise;
}

export async function getAccessToken(): Promise<string> {
  const kc = await initKeycloak();
  if (!kc.authenticated || !kc.token) {
    throw new Error("Not signed in");
  }
  await kc.updateToken(30);
  if (!kc.token) {
    throw new Error("Not signed in");
  }
  return kc.token;
}

export async function forceRefreshToken(): Promise<void> {
  const kc = await initKeycloak();
  if (!kc.authenticated) return;
  await kc.updateToken(100000);
}

export async function login(): Promise<void> {
  const kc = await initKeycloak();
  await kc.login();
}

export async function logout(): Promise<void> {
  const kc = await initKeycloak();
  await kc.logout({ redirectUri: `${window.location.origin}/` });
}
