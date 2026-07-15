package com.smartforum.util;

public class SessionManager {
    private static SessionManager instance;

    private String token;
    private int userId;
    private String userName;

    private SessionManager() {}

    public static SessionManager getInstance() {
        if (instance == null) instance = new SessionManager();
        return instance;
    }

    public String getToken() { return token; }
    public int getUserId() { return userId; }
    public String getUserName() { return userName; }

    public void setSession(String token, int userId, String userName) {
        this.token = token;
        this.userId = userId;
        this.userName = userName;
    }

    public boolean isLoggedIn() { return token != null && !token.isEmpty(); }

    public void clear() {
        token = null;
        userId = 0;
        userName = null;
    }
}
