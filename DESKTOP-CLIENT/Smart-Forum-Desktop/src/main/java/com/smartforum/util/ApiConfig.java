package com.smartforum.util;

/**
 * Server URL for the desktop client. Override for deployment:
 * <ul>
 *   <li>System property: {@code -Dsf.api.url=https://forum.example.com}</li>
 *   <li>Environment variable: {@code SMARTFORUM_API_URL=https://forum.example.com}</li>
 * </ul>
 */
public final class ApiConfig {
    private static final String DEFAULT_BASE_URL = "http://127.0.0.1:8000";

    private ApiConfig() {
    }

    public static String baseUrl() {
        String property = System.getProperty("sf.api.url", "").trim();
        if (!property.isEmpty()) {
            return stripTrailingSlash(property);
        }

        String env = System.getenv("SMARTFORUM_API_URL");
        if (env != null && !env.isBlank()) {
            return stripTrailingSlash(env.trim());
        }

        return DEFAULT_BASE_URL;
    }

    public static String apiBaseUrl() {
        return baseUrl() + "/api";
    }

    private static String stripTrailingSlash(String url) {
        while (url.endsWith("/")) {
            url = url.substring(0, url.length() - 1);
        }
        return url;
    }
}
