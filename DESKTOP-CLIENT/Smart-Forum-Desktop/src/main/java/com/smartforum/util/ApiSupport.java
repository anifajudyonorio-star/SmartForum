package com.smartforum.util;

import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiMapper;
import com.smartforum.model.ForumUser;
import com.smartforum.service.AppSession;
import com.smartforum.service.ForumDataCache;

import java.util.Optional;

/**
 * Laravel API session helpers. The desktop client always talks to the Laravel backend;
 * temporary disconnects are handled by {@link NetworkMonitor} + {@link OfflineQueue}.
 */
public final class ApiSupport {
    private ApiSupport() {
    }

    public static void bootstrapFromProperties() {
        String token = System.getProperty("sf.token", "").trim();
        String email = System.getProperty("sf.email", "").trim();
        String password = System.getProperty("sf.password", "");

        if (token.isEmpty() && !email.isEmpty() && !password.isEmpty()) {
            token = Optional.ofNullable(ApiClient.login(email, password)).orElse("");
        }

        if (!token.isEmpty()) {
            ForumDataCache.clearAll();
            SessionManager.getInstance().setToken(token);
            ApiClient.fetchCurrentUser().ifPresent(user -> {
                SessionManager.getInstance().setUser(user.getId(), user.getName());
                AppSession.getInstance().setCurrentUser(user);
            });
            return;
        }

        // No token — do not bootstrap a fake offline user/session.
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
}
