package com.smartforum;

public class UserSession {
    private static UserSession instance;

    private int id;
    private String fname;
    private String lname;
    private String email;
    private String role;
    private String token;

    private UserSession() {}

    public static UserSession getInstance() {
        if (instance == null) instance = new UserSession();
        return instance;
    }

    public void setUser(int id, String fname, String lname, String email, String role, String token) {
        this.id = id;
        this.fname = fname;
        this.lname = lname;
        this.email = email;
        this.role = role;
        this.token = token;
    }

    public void clear() { instance = null; }

    public int getId()       { return id; }
    public String getFname() { return fname; }
    public String getLname() { return lname; }
    public String getEmail() { return email; }
    public String getRole()  { return role; }
    public String getToken() { return token; }
    public String getFullName() { return fname + " " + lname; }
}
