package com.smartforum.model;

public record GroupHighlight(String name, String detail) {
    public static GroupHighlight none(String detail) {
        return new GroupHighlight("—", detail);
    }
}
