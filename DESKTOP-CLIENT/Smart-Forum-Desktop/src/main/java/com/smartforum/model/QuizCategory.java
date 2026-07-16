package com.smartforum.model;

public class QuizCategory {

    private int id;
    private String categoryName;
    private String description;
    private String createdBy;

    public QuizCategory() {}

    public QuizCategory(int id, String categoryName, String description, String createdBy) {
        this.id = id;
        this.categoryName = categoryName;
        this.description = description;
        this.createdBy = createdBy;
    }

    public QuizCategory(String categoryName, String description, String createdBy) {
        this.categoryName = categoryName;
        this.description = description;
        this.createdBy = createdBy;
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getCategoryName() { return categoryName; }
    public void setCategoryName(String categoryName) { this.categoryName = categoryName; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public String getCreatedBy() { return createdBy; }
    public void setCreatedBy(String createdBy) { this.createdBy = createdBy; }

    @Override
    public String toString() { return categoryName; }
}
