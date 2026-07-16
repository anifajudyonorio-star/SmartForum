package com.smartforum.model;

public class Group {
    private int id;
    private String name;
    private String description;

    public int getId() { return id; }
    public String getName() { return name; }
    public String getDescription() { return description; }

    @Override
    public String toString() { return name; }
}
