import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./app/**/*.{ts,tsx}", "./components/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        ink: "#140e0b",
        panel: "#241910",
        cream: "#f6efe4",
        foam: "#d9cbb8",
        copper: "#e08a3c",
        "copper-2": "#f3b06a",
        hop: "#6fbf8b",
        rosewine: "#d4655d",
      },
      fontFamily: {
        sans: ["Outfit", "ui-sans-serif", "system-ui", "sans-serif"],
        display: ["Fraunces", "Georgia", "serif"],
      },
      boxShadow: {
        panel: "0 24px 60px rgba(0, 0, 0, 0.28)",
      },
    },
  },
  plugins: [],
};

export default config;
