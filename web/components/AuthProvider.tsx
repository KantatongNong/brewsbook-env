"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import { forceRefreshToken, initKeycloak, login, logout } from "@/lib/keycloak";
import type { Me } from "@/lib/types";

interface AuthValue {
  ready: boolean;
  authenticated: boolean;
  profile: Me | null;
  profileError: string | null;
  login: () => void;
  logout: () => void;
  reload: () => Promise<void>;
  refreshSession: () => Promise<void>;
}

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);
  const [profile, setProfile] = useState<Me | null>(null);
  const [profileError, setProfileError] = useState<string | null>(null);

  const loadProfile = useCallback(async () => {
    setProfileError(null);
    try {
      const me = await api<Me>("/api/me");
      setProfile(me);
    } catch (error) {
      setProfile(null);
      setProfileError(error instanceof Error ? error.message : "Could not load profile");
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    initKeycloak()
      .then(async (kc) => {
        if (cancelled) return;
        setAuthenticated(Boolean(kc.authenticated));
        if (kc.authenticated) {
          await loadProfile();
        }
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setProfileError(error instanceof Error ? error.message : "Keycloak failed to start");
        }
      })
      .finally(() => {
        if (!cancelled) setReady(true);
      });
    return () => {
      cancelled = true;
    };
  }, [loadProfile]);

  const value = useMemo<AuthValue>(
    () => ({
      ready,
      authenticated,
      profile,
      profileError,
      login: () => {
        void login();
      },
      logout: () => {
        void logout();
      },
      reload: loadProfile,
      refreshSession: async () => {
        await forceRefreshToken();
        await loadProfile();
      },
    }),
    [authenticated, loadProfile, profile, profileError, ready],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const value = useContext(AuthContext);
  if (!value) {
    throw new Error("useAuth must be used inside AuthProvider");
  }
  return value;
}
