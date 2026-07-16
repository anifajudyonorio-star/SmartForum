package com.smartforum.util;

import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiMapper;
import com.smartforum.model.ForumUser;
import com.smartforum.service.AppSession;

import java.util.Optional;

public final class ApiSupport {
    private ApiSupport() {
    }

    public static boolean useApi() {
        return NetworkMonitor.isOnline() && SessionManager.getInstance().isLoggedIn();
    }

    public static void bootstrapFromProperties() {
        String token = System.getProperty("sf.token", "").trim();
        String email = System.getProperty("sf.email", "").trim();
        String password = System.getProperty("sf.password", "");

        if (token.isEmpty() && !email.isEmpty() && !password.isEmpty()) {
            token = Optional.ofNullable(ApiClient.login(email, password)).orElse("");
        }

        if (!token.isEmpty()) {
            SessionManager.getInstance().setToken(token);
            ApiClient.fetchCurrentUser().ifPresent(user -> {
                SessionManager.getInstance().setUser(user.getId(), user.getName());
                AppSession.getInstance().setCurrentUser(user);
            });
            return;
        }

        int userId = parseIntProperty("sf.userId", 2);
        String userName = System.getProperty("sf.userName", "Anifa Onorio");
        SessionManager.getInstance().setSession("", userId, userName);
    }

    public static Optional<JsonObject> login(String email, String password) {
        Optional<JsonObject> response = ApiClient.loginResponse(email, password);
        response.ifPresent(json -> {
            if (json.has("token")) {
                SessionManager.getInstance().setToken(json.get("token").getAsString());
            }
            if (json.has("user")) {
                ForumUser user = ApiMapper.toForumUser(json.getAsJsonObject("user"));
                SessionManager.getInstance().setUser(user.getId(), user.getName());
                AppSession.getInstance().setCurrentUser(user);
            }
        });
        return response;
    }

    private static int parseIntProperty(String key, int fallback) {
        try {
            return Integer.parseInt(System.getProperty(key, String.valueOf(fallback)));
        } catch (NumberFormatException ex) {
            return fallback;
        }
    }
}
