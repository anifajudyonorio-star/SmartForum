package com.smartforum.model;

public class Quiz {

    private int id;
    private int categoryId;
    private String categoryName;
    private String title;
    private String description;
    private int duration;
    private int totalMarks;
    private int participationMarks;
    private String startDate;
    private String endDate;

    public Quiz() {}

    public Quiz(int id, int categoryId, String categoryName, String title,
                String description, int duration, int totalMarks,
                String startDate, String endDate) {
        this.id = id;
        this.categoryId = categoryId;
        this.categoryName = categoryName;
        this.title = title;
        this.description = description;
        this.duration = duration;
        this.totalMarks = totalMarks;
        this.startDate = startDate;
        this.endDate = endDate;
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getCategoryId() { return categoryId; }
    public void setCategoryId(int categoryId) { this.categoryId = categoryId; }

    public String getCategoryName() { return categoryName; }
    public void setCategoryName(String categoryName) { this.categoryName = categoryName; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public int getDuration() { return duration; }
    public void setDuration(int duration) { this.duration = duration; }

    public int getTotalMarks() { return totalMarks; }
    public void setTotalMarks(int totalMarks) { this.totalMarks = totalMarks; }

    public int getParticipationMarks() { return participationMarks; }
    public void setParticipationMarks(int participationMarks) { this.participationMarks = participationMarks; }

    public String getStartDate() { return startDate; }
    public void setStartDate(String startDate) { this.startDate = startDate; }

    public String getEndDate() { return endDate; }
    public void setEndDate(String endDate) { this.endDate = endDate; }

    @Override
    public String toString() { return title; }
}
